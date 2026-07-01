# Frontend domain — React/Inertia pages, Zustand stores, Echo/Reverb

MUST NOT read this file for backend-only (controller/job/model) work — see [../AGENTS.md](../AGENTS.md) routing table.

## Structure
- `resources/js/Pages/<Domain>/<Action>.jsx` mirrors controller method names (`Meeting/Index`, `Meeting/Show`, `Meeting/Edit`, `Meeting/Upload`, `Team/Create`, etc.) — new pages MUST follow this `Inertia::render('Domain/Action', ...)` ↔ file path convention exactly.
- `Components/` is shared UI (mostly Breeze scaffolding: `PrimaryButton`, `Modal`, `TextInput`, etc.) plus `GlobalAudioPlayer.jsx`, which is always mounted once in `AuthenticatedLayout` — do not remount it inside individual pages.
- `stores/audioStore.js` — single global Zustand store for cross-page audio playback. `seekTo` is a placeholder function replaced at runtime via `registerSeek()` from `GlobalAudioPlayer` — this indirection exists so any page's transcript UI can command playback in the *global* (not page-local) audio element without prop drilling.

## Real-time status pattern (MUST follow for any new pipeline-status UI)
`Meeting/Show.jsx` listens via Laravel Echo:
```js
Echo.private(`meetings.${id}`).listen('.MeetingStatusUpdated', (e) => { ... })
```
- Channel authorization is server-side in `routes/channels.php` (uploader-only).
- A 15s fallback poll is kept alongside the WebSocket listener for resilience — do not remove the fallback poll when adding new real-time features; Reverb can drop connections silently.
- `processing_stage` values driving `StageTracker` must match exactly the stage-key strings dispatched by `ProcessMeetingJob::updateStage()` (`extracting_audio`, `transcribing`, `mapping_speakers`, `summarizing`) — if you add a pipeline stage, update `STAGES` in `Show.jsx` in the same change.

## State/data-fetching conventions
- Prefer Inertia's `router.reload({ only: [...] })` / props for server-driven state; use local `fetch` + optimistic UI only where the codebase already does (todo toggle in `Todos/Index.jsx`, `Meeting/Show.jsx` optimistic todos) — don't introduce a third data-fetching pattern (e.g. SWR/React Query) without asking.
- Search/filter inputs use debouncing (350ms in `Meeting/Index.jsx`) or `useDeferredValue` (local-only search in `Todos/Index.jsx`) depending on whether the search hits the server or filters an already-loaded page — match whichever pattern fits: server round-trip → debounce + Inertia visit; local filter → `useDeferredValue`.

## Scope discipline specific to frontend
- MUST NOT change Tailwind config or add a new CSS framework without asking.
- MUST NOT introduce a global state manager other than Zustand (already chosen) or bypass Inertia's routing with client-side routing libraries.
