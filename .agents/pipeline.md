# Pipeline — ProcessMeetingJob, Gemini, FFmpeg, real-time progress

Not sunk into code: nothing here is checked by a test or type today. Read [../AGENTS.md](../AGENTS.md) "Status of enforcement" before assuming otherwise.

## Flow
`MeetingController::store` / `ChunkUploadController::merge` save the upload, create a `Meeting` (`status: pending`), dispatch `ProcessMeetingJob`. The job runs four sequential stages, each calling `updateStage()` (persists `processing_stage` + broadcasts `MeetingStatusUpdated` on `PrivateChannel('meetings.{id}')`):
`extracting_audio` → `transcribing` → `mapping_speakers` → `summarizing`. On success: `status: completed`, `processing_stage: null`, cleanup temp audio, then email + Slack. On any uncaught exception: `failed()` hook sets `status: failed`, clears stage, logs, and cleans up temp audio.

## Why there's no try/catch inside `handle()`
Laravel's retry mechanism (`$tries = 3`) and the `failed()` hook *are* the error handling. If you wrap a stage in `try { ... } catch (\Throwable $e) { Log::error(...); return; }`, the job returns normally — Laravel considers it a successful run, the retry never fires, and `failed()` never runs. The meeting is left stuck in `processing` forever with no transcript, no summary, and no user-visible failure state. This has no test guarding it (see enforcement note above) — think it through by hand before adding any catch inside a stage.

The *only* exception: `parseSegments()` and the `mapSpeakers()` JSON check swallow decode errors from Gemini's own response text, because a malformed AI reply is an expected, recoverable case (falls back to a single wrapped segment / unmapped speaker labels), not a transport failure. That distinction — "the AI said something we can't parse" vs. "the AI/FFmpeg call itself failed" — is the line between what's safe to catch and what must bubble.

## Things that will silently break if changed without checking call sites
- `$tries`, `$timeout`, `$uniqueFor` on `ProcessMeetingJob`, plus `ShouldBeUnique` + `uniqueId() = meeting.id`: a second dispatch for the same meeting within `$uniqueFor` seconds is a silent no-op. Lowering `$uniqueFor` without understanding this means duplicate transcriptions on retry-heavy meetings; raising it means a legitimately stuck meeting can't be re-dispatched until it expires.
- The Gemini model string `gemini-2.5-flash` appears independently in three places (`transcribe`, `mapSpeakers`, and both closures inside `summarize`) — changing it in one place but not the others produces a job that transcribes on one model and summarizes on another with no error, just inconsistent output quality.
- `updateStage()` must be the only way `processing_stage` changes — a bare `$meeting->update(['processing_stage' => ...])` skips the broadcast, and the frontend `StageTracker` (see [frontend.md](frontend.md)) will show a stuck progress bar even though the backend moved on.
- `resolveMimeType()`'s match arms must stay in sync with `ChunkUploadController::ALLOWED_EXT` — adding an extension to one without the other means either an upload that's accepted but transcribed with the wrong MIME type, or a MIME case that can never be reached.

## Gemini response parsing
All three prompt call sites strip markdown fences (`` preg_replace('/^```(?:json)?\s*|\s*```$/s', ...) ``) before `json_decode`. Gemini sometimes wraps JSON in code fences despite being told not to — any new Gemini call that expects structured output needs this same strip step, or `json_decode` silently returns `null` and downstream code (which does `?? []` fallbacks) will produce empty/default records with no error surfaced.
