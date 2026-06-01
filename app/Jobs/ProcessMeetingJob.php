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
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Concurrency;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

class ProcessMeetingJob implements ShouldQueue, ShouldBeUnique
{
    use Queueable;

    public int $tries   = 3;
    public int $timeout = 600;
    public int $uniqueFor = 3600;

    public function __construct(public readonly Meeting $meeting) {}

    public function uniqueId(): string
    {
        return (string) $this->meeting->id;
    }

    public function handle(): void
    {
        $this->meeting->update(['status' => 'processing']);
        Log::info("ProcessMeetingJob [{$this->meeting->id}]: started.");

        $participantNames = $this->resolveParticipantNames();

        // Stage 1: Transcribe audio → timestamped segments
        $this->updateStage('transcribing');
        $segments = $this->transcribe($participantNames);

        // Stage 2: Map generic "Speaker A" labels to real names
        $this->updateStage('mapping_speakers');
        if (count($participantNames) > 0) {
            $segments = $this->mapSpeakers($segments, $participantNames);
        }

        // Build plain-text content from segments for backwards compatibility
        $content = collect($segments)
            ->map(fn ($s) => trim(($s['speaker'] ? "{$s['speaker']}: " : '') . $s['text']))
            ->implode("\n\n");

        Transcript::create([
            'meeting_id' => $this->meeting->id,
            'content'    => $content,
            'segments'   => $segments,
            'language'   => null,
        ]);

        Log::info("ProcessMeetingJob [{$this->meeting->id}]: transcript saved ({$this->countSegments($segments)} segments).");

        // Stage 3: Concurrent summary + todos generation
        $this->updateStage('summarizing');
        $this->summarize($content);

        Log::info("ProcessMeetingJob [{$this->meeting->id}]: summary + todos saved.");

        $this->meeting->update(['status' => 'completed', 'processing_stage' => null]);
        Log::info("ProcessMeetingJob [{$this->meeting->id}]: done.");

        $this->sendNotifications();
    }

    private function updateStage(string $stage): void
    {
        $this->meeting->update(['processing_stage' => $stage]);
    }

    private function countSegments(array $segments): int
    {
        return count($segments);
    }

    // ─── Transcription ───────────────────────────────────────────────────────

    /**
     * Calls Gemini to transcribe audio and returns an array of timestamped segments.
     * Each segment: ['start' => float, 'speaker' => string, 'text' => string]
     */
    private function transcribe(array $participantNames = []): array
    {
        $audioPath = Storage::disk('public')->path($this->meeting->audio_path);
        $mimeType  = $this->resolveMimeType($this->meeting->audio_path);
        $audioData = base64_encode(file_get_contents($audioPath));

        $nameHint = count($participantNames) > 0
            ? 'The following people may be present: ' . implode(', ', $participantNames) . '. ' .
              'Label each speaker by their actual name when identifiable; otherwise use Speaker A, Speaker B, etc.'
            : 'If multiple speakers are present, use Speaker A, Speaker B, etc.';

        $prompt = <<<PROMPT
Transcribe this audio recording with timestamps. {$nameHint}

Return ONLY a valid JSON array — no markdown, no code fences, no commentary.
Each element must follow this exact structure:
{"start": <start time in seconds as a float>, "speaker": "<speaker name or Speaker A>", "text": "<spoken text>"}

Example:
[{"start": 0.0, "speaker": "Nigel", "text": "Good morning everyone."}, {"start": 4.2, "speaker": "Jefry", "text": "Morning! Let's get started."}]
PROMPT;

        $client   = \Gemini::client(config('services.gemini.key'));
        $raw      = $client->generativeModel(model: 'gemini-2.5-flash')
            ->generateContent([new Blob(mimeType: $mimeType, data: $audioData), $prompt])
            ->text();

        return $this->parseSegments($raw);
    }

    private function parseSegments(string $raw): array
    {
        $json     = preg_replace('/^```(?:json)?\s*|\s*```$/s', '', trim($raw));
        $segments = json_decode($json, true);

        if (! is_array($segments) || empty($segments)) {
            Log::warning("ProcessMeetingJob [{$this->meeting->id}]: transcribe returned invalid JSON — wrapping as single segment.");
            return [['start' => 0.0, 'speaker' => '', 'text' => trim($raw)]];
        }

        // Normalise keys
        return array_map(fn ($s) => [
            'start'   => (float) ($s['start']   ?? 0.0),
            'speaker' => (string) ($s['speaker'] ?? ''),
            'text'    => (string) ($s['text']    ?? ''),
        ], $segments);
    }

    // ─── Speaker mapping ─────────────────────────────────────────────────────

    /**
     * Runs a second Gemini pass to replace generic labels with real participant names.
     * Operates on the segments array (not raw text) for clean data.
     */
    private function mapSpeakers(array $segments, array $participantNames): array
    {
        $genericLabels = collect($segments)
            ->pluck('speaker')
            ->unique()
            ->filter(fn ($s) => preg_match('/^Speaker [A-Z]$/i', $s))
            ->values()
            ->toArray();

        if (empty($genericLabels)) {
            Log::info("ProcessMeetingJob [{$this->meeting->id}]: no generic speaker labels — skipping mapSpeakers.");
            return $segments;
        }

        $nameList = implode(', ', $participantNames);
        $labelList = implode(', ', $genericLabels);

        $prompt = <<<PROMPT
A meeting transcript uses these generic speaker labels: {$labelList}
Known participants: {$nameList}

Using context clues (how people address each other, names mentioned), map each generic label to the most likely participant.
Respond with ONLY a valid JSON object — no markdown, no code fences.
Example: {"Speaker A": "Nigel", "Speaker B": "Jefry"}
Use "Unknown" when you cannot determine the speaker.
PROMPT;

        $raw  = \Gemini::client(config('services.gemini.key'))
            ->generativeModel(model: 'gemini-2.5-flash')
            ->generateContent([$prompt . "\n\nFULL TRANSCRIPT:\n" . $this->segmentsToText($segments)])
            ->text();

        $json = preg_replace('/^```(?:json)?\s*|\s*```$/s', '', trim($raw));
        $map  = json_decode($json, true);

        if (! is_array($map)) {
            Log::warning("ProcessMeetingJob [{$this->meeting->id}]: mapSpeakers returned invalid JSON.");
            return $segments;
        }

        Log::info("ProcessMeetingJob [{$this->meeting->id}]: speaker map", $map);

        return array_map(function ($seg) use ($map) {
            $mapped = $map[$seg['speaker']] ?? null;
            if ($mapped && $mapped !== 'Unknown') {
                $seg['speaker'] = $mapped;
            }
            return $seg;
        }, $segments);
    }

    private function segmentsToText(array $segments): string
    {
        return collect($segments)
            ->map(fn ($s) => "[{$s['start']}s] {$s['speaker']}: {$s['text']}")
            ->implode("\n");
    }

    // ─── Summarisation (concurrent) ───────────────────────────────────────────

    private function summarize(string $transcript): void
    {
        $apiKey = config('services.gemini.key');

        [$summaryRaw, $todosRaw] = Concurrency::run([
            fn () => $this->callGeminiForSummary($transcript, $apiKey),
            fn () => $this->callGeminiForTodos($transcript, $apiKey),
        ]);

        $summaryData = json_decode(
            preg_replace('/^```(?:json)?\s*|\s*```$/s', '', trim($summaryRaw)), true
        ) ?? [];

        AiSummary::create([
            'meeting_id'   => $this->meeting->id,
            'summary'      => $summaryData['summary'] ?? 'No summary generated.',
            'key_points'   => $summaryData['key_points'] ?? [],
            'raw_response' => $summaryRaw,
        ]);

        $todos = json_decode(
            preg_replace('/^```(?:json)?\s*|\s*```$/s', '', trim($todosRaw)), true
        ) ?? [];

        foreach ($todos as $todo) {
            $assignee = null;
            if (! empty($todo['assignee_name'])) {
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

    private function callGeminiForSummary(string $transcript, string $apiKey): string
    {
        $prompt = <<<PROMPT
You are an expert meeting analyst. Analyse the following meeting transcript.
Respond with ONLY a valid JSON object — no markdown, no code fences, no commentary.

Required structure:
{"summary": "2-4 sentence overview", "key_points": ["point 1", "point 2", "point 3"]}

TRANSCRIPT:
{$transcript}
PROMPT;
        return \Gemini::client($apiKey)->generativeModel(model: 'gemini-2.5-flash')
            ->generateContent([$prompt])->text();
    }

    private function callGeminiForTodos(string $transcript, string $apiKey): string
    {
        $prompt = <<<PROMPT
You are an expert meeting analyst. Extract all action items from the following meeting transcript.
Respond with ONLY a valid JSON array — no markdown, no code fences, no commentary.

Required structure:
[{"title": "...", "description": "...", "assignee_name": "... or empty string"}]

TRANSCRIPT:
{$transcript}
PROMPT;
        return \Gemini::client($apiKey)->generativeModel(model: 'gemini-2.5-flash')
            ->generateContent([$prompt])->text();
    }

    // ─── Notifications ────────────────────────────────────────────────────────

    private function sendNotifications(): void
    {
        $meeting = $this->meeting->load(['user', 'todoItems.assignee']);
        Mail::to($meeting->user)->queue(new MeetingProcessedMail($meeting));
        $meeting->todoItems
            ->filter(fn ($t) => $t->assignee && $t->assignee->id !== $meeting->user_id)
            ->groupBy('assigned_to')
            ->each(fn ($todos) => Mail::to($todos->first()->assignee)->queue(new TodoAssignedMail($todos->first())));
        $this->notifySlack($meeting);
    }

    private function notifySlack($meeting): void
    {
        $webhookUrl = $meeting->user->slack_webhook_url;
        if (! $webhookUrl) return;
        $summary   = $meeting->aiSummary?->summary ?? 'No summary generated.';
        $todoLines = $meeting->todoItems->map(fn ($t) => "• {$t->title}" . ($t->assignee ? " → {$t->assignee->name}" : ''))->implode("\n");
        $text = "*Meeting Ready: {$meeting->title}*\n\n{$summary}";
        if ($todoLines) $text .= "\n\n*Action Items:*\n{$todoLines}";
        Http::post($webhookUrl, ['text' => $text]);
    }

    // ─── Helpers ─────────────────────────────────────────────────────────────

    private function resolveParticipantNames(): array
    {
        $this->meeting->loadMissing(['user', 'team.members']);
        $names = collect([$this->meeting->user->name]);
        if ($this->meeting->team) {
            $names = $names->merge($this->meeting->team->members->pluck('name'));
        }
        return $names->unique()->values()->toArray();
    }

    private function resolveMimeType(string $path): MimeType
    {
        return match (strtolower(pathinfo($path, PATHINFO_EXTENSION))) {
            'wav'  => MimeType::AUDIO_WAV,
            'm4a'  => MimeType::AUDIO_AAC,
            'ogg'  => MimeType::AUDIO_OGG,
            'flac' => MimeType::AUDIO_FLAC,
            default => MimeType::AUDIO_MP3,
        };
    }

    public function failed(\Throwable $e): void
    {
        $this->meeting->update(['status' => 'failed', 'processing_stage' => null]);
        Log::error("ProcessMeetingJob [{$this->meeting->id}]: failed — {$e->getMessage()}");
    }
}
