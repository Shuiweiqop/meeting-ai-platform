# HTTP layer domain — Controllers, routes, form requests, authorization

MUST NOT read this file for pipeline/job or frontend-only work — see [../AGENTS.md](../AGENTS.md) routing table.

## Naming & structure
- Controllers are thin: validate (via Form Request where the model has one — `StoreMeetingRequest`, `StoreTeamRequest`, `ProfileUpdateRequest` — else inline `$request->validate()`), authorize, delegate to Eloquent/Job, return `Inertia::render()` or `redirect()`.
- Resource routes via `Route::resource()` in `routes/web.php`; anything beyond CRUD (retry, export, share, chunk upload, todo toggle) is a named extra route grouped near its resource.
- `SharedMeetingController::show` is the only controller action reachable without `auth` middleware — it is a public route (`/share/{token}`). MUST NOT add auth-requiring logic there; MUST NOT leak non-public fields (e.g. other users' emails) through it.

## Authorization pattern — MUST follow, do not introduce a second pattern
Every action on a user-owned resource (`Meeting`, `Team` membership actions, `TodoItem`) checks ownership with `abort_if($model->user_id !== Auth::id(), 403)` at the top of the method, before any mutation. Example: [MeetingController.php](../app/Http/Controllers/MeetingController.php).
- FORBIDDEN: relying on route-model-binding scoping alone, or checking ownership after a mutation has started.
- FORBIDDEN: introducing Laravel Policies for this unless asked — the codebase consistently uses inline `abort_if`, not `$this->authorize()`. Match existing style.

## Error handling in this layer
- Controllers MUST NOT catch pipeline/job exceptions — `ProcessMeetingJob::dispatch()` is fire-and-forget; failures surface later via `processing_stage`/`status` polling and WebSocket broadcast, not an HTTP response.
- `ChunkUploadController::merge` is the one controller method with a try/catch (around the chunk-stitching loop) — it exists specifically to clean up the partial output file (`@unlink($finalAbsPath)`) before rethrowing. Follow this same "cleanup then rethrow" shape if you add another multi-step file operation; do not swallow the exception.
- Validation errors: let `ValidationException` bubble (Laravel's default Inertia error-bag handling) — do not manually catch and reformat.

## Adding a new controller action checklist
1. Ownership check (`abort_if`) as the first line, if the resource is user- or team-scoped.
2. Validate input (Form Request if reused elsewhere, inline `validate()` if single-use).
3. Delegate business logic to a model method/Job — do not inline Gemini/FFmpeg calls in a controller.
4. Return `Inertia::render()` (page load) or `redirect()->with('success'|'error', ...)` (mutation) — match existing flash-message key names (`success`) for consistency with `AuthenticatedLayout`'s toast handling.
