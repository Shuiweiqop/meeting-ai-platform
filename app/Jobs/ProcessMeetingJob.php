<?php

namespace App\Jobs;

use App\Enums\ProcessingStage;
use App\Events\MeetingStatusUpdated;
use App\Mail\MeetingProcessedMail;
use App\Mail\TodoAssignedMail;
use App\Models\AiSummary;
use App\Models\Meeting;
use App\Models\TodoItem;
use App\Models\Transcript;
use App\Models\User;
use App\Rules\SlackWebhookUrl;
use App\Support\GeminiJson;
use FFMpeg\FFMpeg;
use FFMpeg\Format\Audio\Mp3 as Mp3Format;
use Gemini\Client;
use Gemini\Data\Blob;
use Gemini\Data\UploadedFile;
use Gemini\Enums\FileState;
use Gemini\Enums\MimeType;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Concurrency;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

class ProcessMeetingJob implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 600;

    public int $uniqueFor = 3600;

    // Keep safely under the ~20 MB Gemini inline-request cap (prompt included).
    private const INLINE_AUDIO_LIMIT_BYTES = 15 * 1024 * 1024;

    private ?string $extractedAudioPath = null;

    public function __construct(public readonly Meeting $meeting) {}

    public function uniqueId(): string
    {
        return (string) $this->meeting->id;
    }

    public function handle(): void
    {
        $this->meeting->transitionTo('processing');
        Log::info("ProcessMeetingJob [{$this->meeting->id}]: started.");

        $participantNames = $this->resolveParticipantNames();

        // Stage 0: Normalize audio via FFmpeg (handles video→audio too)
        $this->updateStage(ProcessingStage::ExtractingAudio);
        $audioStoragePath = $this->extractAudio();

        // Stage 1: Transcribe audio → timestamped segments
        $this->updateStage(ProcessingStage::Transcribing);
        $segments = $this->transcribe($participantNames, $audioStoragePath);

        // Stage 2: Map generic "Speaker A" labels to real names
        $this->updateStage(ProcessingStage::MappingSpeakers);
        if (count($participantNames) > 0) {
            $segments = $this->mapSpeakers($segments, $participantNames);
        }

        // Build plain-text content from segments for backwards compatibility
        $content = collect($segments)
            ->map(fn ($s) => trim(($s['speaker'] ? "{$s['speaker']}: " : '').$s['text']))
            ->implode("\n\n");

        Transcript::create([
            'meeting_id' => $this->meeting->id,
            'content' => $content,
            'segments' => $segments,
            'language' => null,
        ]);

        Log::info("ProcessMeetingJob [{$this->meeting->id}]: transcript saved ({$this->countSegments($segments)} segments).");

        // Stage 3: Concurrent summary + todos generation
        $this->updateStage(ProcessingStage::Summarizing);
        $this->summarize($content);

        Log::info("ProcessMeetingJob [{$this->meeting->id}]: summary + todos saved.");

        $this->meeting->transitionTo('completed');
        Log::info("ProcessMeetingJob [{$this->meeting->id}]: done.");

        $this->cleanupExtractedAudio();

        try {
            $this->sendNotifications();
        } catch (\Throwable $e) {
            // The meeting is already completed — letting this bubble would retry
            // the whole job and end with failed() marking a processed meeting as
            // failed. Recoverable by design, unlike a stage failure (pipeline.md).
            Log::warning("ProcessMeetingJob [{$this->meeting->id}]: notifications failed after completion — {$e->getMessage()}");
        }
    }

    private function updateStage(ProcessingStage $stage): void
    {
        $this->meeting->transitionTo('processing', $stage);
    }

    // ─── Audio extraction ─────────────────────────────────────────────────────

    private function extractAudio(): string
    {
        $sourcePath = Storage::disk('local')->path($this->meeting->audio_path);
        $relativePath = 'meetings/tmp_'.$this->meeting->id.'.mp3';
        $outputPath = Storage::disk('local')->path($relativePath);

        $format = new Mp3Format;
        $lastPct = -1;

        $format->on('progress', function ($media, $format, $percentage) use (&$lastPct) {
            $pct = (int) $percentage;
            if ($pct - $lastPct >= 5) {
                $lastPct = $pct;
                broadcast(new MeetingStatusUpdated($this->meeting, $pct));
            }
        });

        FFMpeg::create()->open($sourcePath)->save($format, $outputPath);

        $this->extractedAudioPath = $relativePath;
        Log::info("ProcessMeetingJob [{$this->meeting->id}]: audio extracted → {$relativePath}");

        return $relativePath;
    }

    private function cleanupExtractedAudio(): void
    {
        if ($this->extractedAudioPath) {
            Storage::disk('local')->delete($this->extractedAudioPath);
            $this->extractedAudioPath = null;
        }
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
    private function transcribe(array $participantNames = [], ?string $audioStoragePath = null): array
    {
        $storagePath = $audioStoragePath ?? $this->meeting->audio_path;
        $audioPath = Storage::disk('local')->path($storagePath);
        $mimeType = $this->resolveMimeType($storagePath);

        $nameHint = count($participantNames) > 0
            ? 'The following people may be present: '.implode(', ', $participantNames).'. '.
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

        $client = \Gemini::client(config('services.gemini.key'));
        $raw = $client->generativeModel(model: config('services.gemini.model'))
            ->generateContent([$this->audioPartFor($client, $audioPath, $mimeType), $prompt])
            ->text();

        return $this->parseSegments($raw);
    }

    /**
     * Inline base64 audio is capped by the Gemini API (~20 MB per request) and
     * by PHP memory (base64 inflates ~33%), so long meetings hard-fail if sent
     * inline. Larger files go through the Files API: upload once, poll until
     * ACTIVE, reference by URI. Uploaded files auto-expire after 48 h.
     */
    private function audioPartFor(Client $client, string $audioPath, MimeType $mimeType): Blob|UploadedFile
    {
        if (filesize($audioPath) <= self::INLINE_AUDIO_LIMIT_BYTES) {
            return new Blob(mimeType: $mimeType, data: base64_encode(file_get_contents($audioPath)));
        }

        $file = $client->files()->upload(
            filename: $audioPath,
            mimeType: $mimeType,
            displayName: "meeting_{$this->meeting->id}",
        );
        Log::info("ProcessMeetingJob [{$this->meeting->id}]: audio uploaded to Files API → {$file->uri}");

        $deadline = time() + 300;
        while (! $file->state->complete()) {
            if (time() >= $deadline) {
                throw new \RuntimeException('Gemini Files API file processing timed out after 300s.');
            }
            sleep(5);
            $file = $client->files()->metadataGet($file->uri);
        }

        if ($file->state !== FileState::Active) {
            throw new \RuntimeException("Gemini Files API file ended in state [{$file->state->value}].");
        }

        return new UploadedFile(fileUri: $file->uri, mimeType: $mimeType);
    }

    private function parseSegments(string $raw): array
    {
        $segments = GeminiJson::decode($raw);

        if (! is_array($segments) || empty($segments)) {
            Log::warning("ProcessMeetingJob [{$this->meeting->id}]: transcribe returned invalid JSON — wrapping as single segment.");

            return [['start' => 0.0, 'speaker' => '', 'text' => trim($raw)]];
        }

        // Normalise keys
        return array_map(fn ($s) => [
            'start' => (float) ($s['start'] ?? 0.0),
            'speaker' => (string) ($s['speaker'] ?? ''),
            'text' => (string) ($s['text'] ?? ''),
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

        $raw = \Gemini::client(config('services.gemini.key'))
            ->generativeModel(model: config('services.gemini.model'))
            ->generateContent([$prompt."\n\nFULL TRANSCRIPT:\n".$this->segmentsToText($segments)])
            ->text();

        $map = GeminiJson::decode($raw);

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

        $summaryData = GeminiJson::decode($summaryRaw) ?? [];

        AiSummary::create([
            'meeting_id' => $this->meeting->id,
            'summary' => $summaryData['summary'] ?? 'No summary generated.',
            'key_points' => $summaryData['key_points'] ?? [],
            'raw_response' => $summaryRaw,
        ]);

        $todos = GeminiJson::decode($todosRaw) ?? [];

        foreach ($todos as $todo) {
            $assignee = null;
            if (! empty($todo['assignee_name'])) {
                $assignee = User::where('name', 'like', '%'.$todo['assignee_name'].'%')->first();
            }
            TodoItem::create([
                'meeting_id' => $this->meeting->id,
                'assigned_to' => $assignee?->id,
                'title' => $todo['title'] ?? 'Untitled task',
                'description' => $todo['description'] ?? null,
                'due_date' => null,
                'status' => 'pending',
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

        return \Gemini::client($apiKey)->generativeModel(model: config('services.gemini.model'))
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

        return \Gemini::client($apiKey)->generativeModel(model: config('services.gemini.model'))
            ->generateContent([$prompt])->text();
    }

    // ─── Notifications ────────────────────────────────────────────────────────

    private function sendNotifications(): void
    {
        $meeting = $this->meeting->load(['uploader', 'todoItems.assignee']);
        Mail::to($meeting->uploader)->queue(new MeetingProcessedMail($meeting));
        $meeting->todoItems
            ->filter(fn ($t) => $t->assignee && $t->assignee->id !== $meeting->user_id)
            ->groupBy('assigned_to')
            ->each(fn ($todos) => Mail::to($todos->first()->assignee)->queue(new TodoAssignedMail($todos->first())));
        $this->notifySlack($meeting);
    }

    private function notifySlack($meeting): void
    {
        $webhookUrl = $meeting->uploader->slack_webhook_url;
        if (! $webhookUrl) {
            return;
        }

        // Defense in depth: input validation blocks non-Slack hosts at save
        // time, but a row that predates that rule could still be POSTed to here.
        // Re-check the host so the worker never sends to an arbitrary URL (SSRF).
        if (parse_url($webhookUrl, PHP_URL_HOST) !== SlackWebhookUrl::ALLOWED_HOST) {
            Log::warning("ProcessMeetingJob [{$this->meeting->id}]: skipped Slack notify — non-Slack webhook host.");

            return;
        }

        $summary = $meeting->aiSummary?->summary ?? 'No summary generated.';
        $todoLines = $meeting->todoItems->map(fn ($t) => "• {$t->title}".($t->assignee ? " → {$t->assignee->name}" : ''))->implode("\n");
        $text = "*Meeting Ready: {$meeting->title}*\n\n{$summary}";
        if ($todoLines) {
            $text .= "\n\n*Action Items:*\n{$todoLines}";
        }
        Http::post($webhookUrl, ['text' => $text]);
    }

    // ─── Helpers ─────────────────────────────────────────────────────────────

    private function resolveParticipantNames(): array
    {
        $this->meeting->loadMissing(['uploader', 'team.members']);
        $names = collect([$this->meeting->uploader->name]);
        if ($this->meeting->team) {
            $names = $names->merge($this->meeting->team->members->pluck('name'));
        }

        return $names->unique()->values()->toArray();
    }

    private function resolveMimeType(string $path): MimeType
    {
        return match (strtolower(pathinfo($path, PATHINFO_EXTENSION))) {
            'wav' => MimeType::AUDIO_WAV,
            'm4a' => MimeType::AUDIO_AAC,
            'ogg' => MimeType::AUDIO_OGG,
            'flac' => MimeType::AUDIO_FLAC,
            default => MimeType::AUDIO_MP3,
        };
    }

    public function failed(\Throwable $e): void
    {
        $this->cleanupExtractedAudio();
        $this->meeting->transitionTo('failed');
        Log::error("ProcessMeetingJob [{$this->meeting->id}]: failed — {$e->getMessage()}");
    }
}
