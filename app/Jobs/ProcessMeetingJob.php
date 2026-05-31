<?php

namespace App\Jobs;

use App\Mail\MeetingProcessedMail;
use App\Mail\TodoAssignedMail;
use App\Models\AiSummary;
use App\Models\Meeting;
use App\Models\TodoItem;
use App\Models\Transcript;
use App\Models\User;
use Gemini\Data\Blob;
use Gemini\Enums\MimeType;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

class ProcessMeetingJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;
    public int $timeout = 600;

    public function __construct(public readonly Meeting $meeting) {}

    public function handle(): void
    {
        $this->meeting->update(['status' => 'processing']);
        Log::info("ProcessMeetingJob [{$this->meeting->id}]: started.");

        $transcriptText = $this->transcribe();

        Transcript::create([
            'meeting_id' => $this->meeting->id,
            'content'    => $transcriptText,
            'language'   => null,
        ]);

        Log::info("ProcessMeetingJob [{$this->meeting->id}]: transcript saved.");

        $this->summarize($transcriptText);

        Log::info("ProcessMeetingJob [{$this->meeting->id}]: summary + todos saved.");

        $this->meeting->update(['status' => 'completed']);
        Log::info("ProcessMeetingJob [{$this->meeting->id}]: done.");

        $this->sendNotifications();
    }

    private function sendNotifications(): void
    {
        $meeting = $this->meeting->load(['user', 'todoItems.assignee']);

        // Notify uploader
        Mail::to($meeting->user)->queue(new MeetingProcessedMail($meeting));

        // Notify each unique assignee (skip if same as uploader)
        $meeting->todoItems
            ->filter(fn ($t) => $t->assignee && $t->assignee->id !== $meeting->user_id)
            ->groupBy('assigned_to')
            ->each(function ($todos) {
                Mail::to($todos->first()->assignee)->queue(new TodoAssignedMail($todos->first()));
            });
    }

    private function transcribe(): string
    {
        $audioPath = Storage::disk('public')->path($this->meeting->audio_path);
        $mimeType  = $this->resolveMimeType($this->meeting->audio_path);
        $audioData = base64_encode(file_get_contents($audioPath));

        $client = \Gemini::client(config('services.gemini.key'));

        $response = $client->generativeModel(model: 'gemini-2.5-flash')
            ->generateContent([
                new Blob(mimeType: $mimeType, data: $audioData),
                'Transcribe this audio recording accurately. ' .
                'If multiple speakers are present, add speaker labels (e.g. Speaker A:). ' .
                'Return only the transcript text, no commentary.',
            ]);

        return $response->text();
    }

    private function summarize(string $transcript): void
    {
        $client = \Gemini::client(config('services.gemini.key'));

        $prompt = <<<PROMPT
You are an expert meeting analyst. Analyse the following meeting transcript and respond with ONLY a valid JSON object — no markdown, no code fences, no commentary.

Required JSON structure:
{
  "summary": "2-4 sentence overview of the meeting",
  "key_points": ["point 1", "point 2", "point 3"],
  "todos": [
    {
      "title": "short action item title",
      "description": "optional detail or context",
      "assignee_name": "first name or full name of the person responsible, or empty string if unknown"
    }
  ]
}

TRANSCRIPT:
PROMPT;

        $response = $client->generativeModel(model: 'gemini-2.5-flash')
            ->generateContent([$prompt . "\n\n" . $transcript]);

        $raw  = $response->text();
        $json = preg_replace('/^```(?:json)?\s*|\s*```$/s', '', trim($raw));
        $data = json_decode($json, true) ?? [];

        AiSummary::create([
            'meeting_id'   => $this->meeting->id,
            'summary'      => $data['summary'] ?? 'No summary generated.',
            'key_points'   => $data['key_points'] ?? [],
            'raw_response' => $raw,
        ]);

        foreach ($data['todos'] ?? [] as $todo) {
            $assignee = null;
            if (!empty($todo['assignee_name'])) {
                $assignee = User::where('name', 'like', '%' . $todo['assignee_name'] . '%')->first();
            }

            TodoItem::create([
                'meeting_id'  => $this->meeting->id,
                'assigned_to' => $assignee?->id,
                'title'       => $todo['title'] ?? 'Untitled task',
                'description' => $todo['description'] ?? null,
                'due_date'    => null,
                'status'      => 'pending',
            ]);
        }
    }

    private function resolveMimeType(string $path): MimeType
    {
        return match (strtolower(pathinfo($path, PATHINFO_EXTENSION))) {
            'wav'         => MimeType::AUDIO_WAV,
            'm4a'         => MimeType::AUDIO_AAC,
            'ogg'         => MimeType::AUDIO_OGG,
            'flac'        => MimeType::AUDIO_FLAC,
            default       => MimeType::AUDIO_MP3,
        };
    }

    public function failed(\Throwable $e): void
    {
        $this->meeting->update(['status' => 'failed']);
        Log::error("ProcessMeetingJob [{$this->meeting->id}]: failed — {$e->getMessage()}");
    }
}
