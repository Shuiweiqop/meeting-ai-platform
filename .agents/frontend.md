# Frontend — React/Inertia pages, Zustand audio store, Echo/Reverb

Not sunk into code: no React ErrorBoundary exists, no ESLint config exists. See [../AGENTS.md](../AGENTS.md) "Status of enforcement".

## Page-to-controller naming is a real contract, not a convention to imitate for style
`Inertia::render('Domain/Action', ...)` on the PHP side must match `resources/js/Pages/Domain/Action.jsx` exactly, including case — Inertia resolves the component path at runtime with no compile-time check. A mismatch is a runtime 500 (component not found), not a type error caught earlier. When adding a controller method that renders a new page, create the matching file first or the request will fail with no clue pointing at the naming mismatch specifically.

## Why `seekTo` in `audioStore.js` starts as a no-op function
`GlobalAudioPlayer` is mounted once in `AuthenticatedLayout` and owns the actual `<audio>` DOM element; it calls `registerSeek()` on mount to inject the real seek implementation into the store. Any page (e.g. the transcript view) that calls `seekTo()` before `GlobalAudioPlayer` has mounted and registered would otherwise call `undefined()` — the no-op default exists specifically to make that ordering safe. Don't remove the default or add a second `registerSeek()` call site; the store assumes exactly one player instance.

## Why the 15-second fallback poll exists alongside the Echo listener
`Meeting/Show.jsx` listens for `MeetingStatusUpdated` over `Echo.private('meetings.{id}')`, but also polls the same data every 15 seconds. Reverb connections can drop without a client-visible error (no reconnect event fires reliably in all cases tested), so this is not defensive redundancy — it is the actual failure recovery. Removing the poll because "we already have real-time updates" would mean a dropped WebSocket connection leaves the progress UI frozen with no user-facing indication that the meeting is still (or no longer) processing.

## `processing_stage` string values are a shared vocabulary with the backend
`STAGES` in `Show.jsx` (`extracting_audio`, `transcribing`, `mapping_speakers`, `summarizing`) must match `App\Enums\ProcessingStage` exactly — there is no compile-time link between PHP and JS, but `tests/Unit/AgentsDocGuardTest.php` fails if an enum case value is missing from `Show.jsx`. When adding a pipeline stage: add the enum case, add the `STAGES` entry, and the test keeps you honest; the failure mode it prevents is a new stage that never highlights, leaving the tracker stuck one step behind reality.
