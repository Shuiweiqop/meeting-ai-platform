# Testing domain — Pest, CI

MUST NOT read this file for pure implementation work unless writing/updating tests — see [../AGENTS.md](../AGENTS.md) routing table.

## Conventions — MUST follow
- Tests are Pest (`it('...', fn () => ...)`), not PHPUnit classes. Live under `tests/Feature/<Domain>Test.php`, one file per controller/domain (`MeetingTest`, `TeamTest`, `TodoAndShareTest`, `ProfileTest`, `Auth/*`).
- Every Feature test extends `TestCase` with `RefreshDatabase` (wired globally in `tests/Pest.php` via `pest()->extend(...)->use(RefreshDatabase::class)->in('Feature')`) — MUST NOT add a new test outside `tests/Feature`/`tests/Unit` or it won't get this trait.
- `Queue::fake()` MUST be used when testing any endpoint that dispatches `ProcessMeetingJob` (upload, retry) — do not let real Gemini/FFmpeg calls run in tests. Assert with `Queue::assertPushed(ProcessMeetingJob::class, ...)`.
- `Storage::fake('public')` for any test touching file upload — never write to the real `storage/app/public` disk from a test.
- Auth-guard tests (`redirects guests from X`) are expected for every new authenticated route — follow the existing pattern of one `it(...)` per guarded route at the top of the relevant test file.
- Ownership tests (403 for non-owners) are expected for every new user-scoped action, mirroring `abort_if` checks in [.agents/http-layer.md](http-layer.md).

## Running tests
- `php artisan test --parallel` (matches CI) or `./vendor/bin/pest --parallel`.
- Config: SQLite in-memory (`phpunit.xml`), `QUEUE_CONNECTION=sync` is overridden — but `Queue::fake()` still needed per-test since sync would otherwise execute jobs inline.
- CI (`.github/workflows/ci.yml`): PHP 8.3 + SQLite → `php artisan test --parallel`, then Node 20 → `npm run build`. `GEMINI_API_KEY` is set to a fake value in CI — no live API calls are ever made in the test suite or CI.

## Definition of Done for any backend change
- [ ] New/changed controller action has an auth-guard test (if newly authenticated) and an ownership test (if user-scoped)
- [ ] New/changed job stage or Gemini call path has `Queue::fake()` coverage at the dispatch site (not the job internals — job internals calling real Gemini are not unit-tested, by design)
- [ ] `php artisan test --parallel` passes
- [ ] `npm run build` succeeds if any `.jsx` changed
- [ ] No migration edited in place — new migration added if schema changed
