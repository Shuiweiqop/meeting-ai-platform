# Testing — Pest, CI

Not sunk into code: nothing blocks a test file living in the wrong directory or missing `Queue::fake()` except CI eventually catching a flaky/real-API-calling run. See [../AGENTS.md](../AGENTS.md) "Status of enforcement".

## Why `Queue::fake()` is required on any test hitting an upload/retry endpoint
`ProcessMeetingJob` calls the real Gemini API and real FFmpeg binary in `handle()`. `phpunit.xml` sets `QUEUE_CONNECTION=sync`, which means without `Queue::fake()`, dispatching the job in a test executes it inline immediately — a real network call to Gemini, in CI, using the fake key set in `.github/workflows/ci.yml` (`GEMINI_API_KEY=fake-key-for-ci`), which will fail or hang rather than skip. `Queue::fake()` + `Queue::assertPushed(ProcessMeetingJob::class, ...)` tests that the dispatch happened without ever running the job body.

## Why every Feature test needs `RefreshDatabase`, and how it's actually wired
`tests/Pest.php` applies `RefreshDatabase` globally to everything under `tests/Feature` via `pest()->extend(TestCase::class)->use(RefreshDatabase::class)->in('Feature')`. A test file placed outside `tests/Feature`/`tests/Unit` does not get this binding — it would share database state across tests with no isolation, producing failures that depend on run order rather than the code under test.

## `Storage::fake('public')` for upload tests
Without it, a test writing an "uploaded" file writes to the real `storage/app/public` disk on whatever machine runs the test — including CI, where it either pollutes the runner or fails on a missing directory `storage/app/public/meetings/` was never guaranteed to exist.

## Running
- `php artisan test --parallel` or `./vendor/bin/pest --parallel` — this is the exact command CI runs (`.github/workflows/ci.yml`).
- CI also runs `./vendor/bin/pint --test` before the test suite (config in `pint.json`) — style violations fail the build; run `./vendor/bin/pint` locally before pushing.
- CI also runs `npm run build` after PHP tests pass — a `.jsx` change that breaks the Vite build fails CI even if every Pest test is green.

## Guard tests that encode conventions
- `tests/Unit/AgentsDocGuardTest.php` pins factual claims made in `AGENTS.md`/`.agents/*.md` (stage enum ↔ `Show.jsx`, no hardcoded Gemini model, reverb absent from `composer dev`, no static-analysis package). If it fails after your change, the doc is now stale — update the doc and the test's expectation in the same diff.
- `tests/Unit/GeminiJsonTest.php` + `tests/Feature/ProcessMeetingPipelineTest.php` cover Gemini response parsing and `Meeting::transitionTo()` — pipeline *parsing* logic is testable without network; only the FFmpeg/Gemini transport calls remain untested by design.

## Definition of Done for a backend change
- [ ] New/changed controller action has an auth-guard test (guest redirect) if newly authenticated, and an ownership/authorization test matching whichever `abort_if` variant applies (see [http-layer.md](http-layer.md) — single-owner, owner-or-member, or owner-or-assignee)
- [ ] Any test dispatching `ProcessMeetingJob` uses `Queue::fake()` — job internals themselves are not exercised against real Gemini/FFmpeg in this suite, by design
- [ ] `php artisan test --parallel` passes
- [ ] `npm run build` succeeds if any `.jsx` changed
- [ ] No migration edited in place — a new migration was added if the schema changed (see [data-model.md](data-model.md))
