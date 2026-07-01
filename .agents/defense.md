# Defense domain — logging, error handling, retries

MUST NOT read this file for pure feature work unless adding logging/error handling — see [../AGENTS.md](../AGENTS.md) routing table.

## Logging convention — MUST follow, this is the only pattern in the codebase
Format: `Log::info("<ClassName> [{$id}]: <message>.")`, established throughout `ProcessMeetingJob`. Example:
```php
Log::info("ProcessMeetingJob [{$this->meeting->id}]: transcript saved ({$count} segments).");
Log::warning("ProcessMeetingJob [{$this->meeting->id}]: transcribe returned invalid JSON — wrapping as single segment.");
Log::error("ProcessMeetingJob [{$this->meeting->id}]: failed — {$e->getMessage()}");
```
- Grep-able prefix is `<ClassName> [<domain-key-id>]:` — MUST keep this exact shape (class name literal, id in brackets, colon, then message) so `grep "ProcessMeetingJob \["` (or any other class) reliably finds all lifecycle events for one entity in `storage/logs`.
- `Log::info` — normal stage transitions and milestones. `Log::warning` — degraded-but-recovered (e.g. AI returned bad JSON, code fell back). `Log::error` — terminal failure, paired with `failed()` hook only.
- Never log raw Gemini API keys, webhook URLs, or full audio file contents. Logging a truncated prompt or a count/summary of AI output is fine (see `speaker map` log which logs the parsed map, not the raw prompt).

## Error handling — layering, MUST NOT contradict this
- **Controllers**: do not catch exceptions from jobs/services. Let them bubble to Laravel's default exception handler (renders Inertia error page / JSON 500). The one exception-catching controller method (`ChunkUploadController::merge`) only catches to clean up a partial file, then rethrows — it does not suppress the error.
- **Jobs**: `ProcessMeetingJob::handle()` itself has no try/catch around its stages — retries (`$tries = 3`) and the `failed()` hook are the error-handling mechanism, not inline catches. The only in-job try/catch-equivalent is defensive `json_decode` validation of Gemini's own response text (expected to sometimes be malformed), not of transport/API failures.
- **FORBIDDEN**: adding a blanket `try { ... } catch (\Throwable $e) { Log::error(...); return; }` inside a job stage — this would silently mark a stage "successful" while actually failing, leaving `meetings.status` stuck or wrongly advanced. If a stage can fail, let it throw so `failed()` and the queue's retry mechanism handle it.

## Retry semantics (don't change without asking)
- `ProcessMeetingJob`: `tries=3`, `timeout=600s`, `uniqueFor=3600s` via `ShouldBeUnique` + `uniqueId() = meeting.id`. This means: a second dispatch for the same meeting within 1 hour is a no-op, and each attempt gets up to 10 minutes before Laravel kills it as timed-out (counts as a failed try).
- `MeetingController::retry()` is the user-facing manual retry: it deletes prior partial `Transcript`/`AiSummary`/`TodoItem` rows, resets `status: pending`, and re-dispatches — MUST keep this cleanup-then-redispatch order if touched, otherwise stale data can coexist with newly generated data.
