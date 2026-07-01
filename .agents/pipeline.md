# Pipeline domain — ProcessMeetingJob, Gemini, FFmpeg, real-time progress

MUST NOT read this file for pure controller/route/model work — see [../AGENTS.md](../AGENTS.md) routing table.

## Single request flow (upload → completed)
1. `MeetingController::store` / `ChunkUploadController::merge` — save upload to `storage/app/public/meetings/`, create `Meeting` row (`status: pending`), dispatch `ProcessMeetingJob`.
2. `ProcessMeetingJob::handle()` — sequential stages, each calling `updateStage()` which persists `meetings.processing_stage` and broadcasts `MeetingStatusUpdated` on `PrivateChannel('meetings.{id}')`:
   - `extracting_audio` — FFmpeg normalizes video/audio → mp3 (only if a temp path is produced; progress broadcast every 5%)
   - `transcribing` — Gemini `generateContent` with audio Blob → JSON array of `{start, speaker, text}` segments (`parseSegments()` degrades to a single wrapped segment on invalid JSON, never throws)
   - `mapping_speakers` — second Gemini pass replaces generic `Speaker A/B` labels with real participant names (skipped if no generic labels found)
   - `summarizing` — `Concurrency::run()` fires summary + todo-extraction prompts in parallel
3. On success: `status: completed`, `processing_stage: null`, broadcast, cleanup temp audio, then email + Slack notifications.
4. On failure: `failed(Throwable $e)` hook sets `status: failed`, clears stage, broadcasts, logs — this is the ONLY place that catches pipeline errors. Do not add try/catch inside `handle()` stages; let exceptions bubble to `failed()`.

## Per-layer rules
- **Job stages MUST NOT swallow exceptions.** Gemini/FFmpeg failures should propagate up to `failed()`. Only JSON-parsing of Gemini's *own* response is defensively handled (`parseSegments`, `mapSpeakers` json_decode checks) because malformed AI output is expected, not exceptional.
- **MUST NOT** change `$tries = 3`, `$timeout = 600`, `$uniqueFor = 3600` on `ProcessMeetingJob` without asking first — these bound retry cost against the Gemini API and prevent duplicate dispatch for the same meeting (`ShouldBeUnique` + `uniqueId()`).
- **MUST** call `updateStage()` (not a bare `$meeting->update()`) when introducing a new pipeline stage, so the broadcast + persisted stage stay in sync.
- **MUST** clean up `$extractedAudioPath` via `cleanupExtractedAudio()` in both the success path and `failed()` — never leave orphaned temp mp3s in `storage/app/public/meetings/`.

## Gemini prompts
- Model is hardcoded as `gemini-2.5-flash` in three places (`transcribe`, `mapSpeakers`, `summarize`'s two closures) — MUST confirm with user before changing model string, it must change in all call sites together.
- All prompts demand "ONLY valid JSON — no markdown, no code fences" and responses are stripped via `preg_replace('/^```(?:json)?\s*|\s*```$/s', ...)` before `json_decode`. Any new Gemini call MUST follow this same strip-then-decode pattern for consistency.

## FFmpeg
- Two independent extraction paths exist: `MeetingController::extractAudio()` (non-chunked upload, video-only) and `ProcessMeetingJob::extractAudio()` (always runs, any format, with progress broadcast). Keep them behaviorally consistent if you touch one.
- Supported upload extensions are gated by `ChunkUploadController::ALLOWED_EXT` — the job's `resolveMimeType()` match arm list MUST stay in sync with anything added there.
