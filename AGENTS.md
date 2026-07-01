# AGENTS.md — Meeting AI Platform (Router)

Laravel 13 + React/Inertia app that turns uploaded meeting audio/video into transcripts, AI summaries, and action items via Gemini.

## Commands
Requires: Docker (MySQL on `3307`, Redis on `16379`), PHP 8.3, Node 20, FFmpeg on PATH.
- `composer install && npm install`
- `docker start meeting_ai_mysql meeting_ai_redis` (or `docker-compose up -d`)
- `composer run dev` — runs server + queue:listen + pail logs + vite concurrently
- `php artisan reverb:start` — WebSocket server (separate terminal, not in `composer dev`)
- `php artisan test --parallel` or `./vendor/bin/pest --parallel` — full suite (uses SQLite in-memory, see `phpunit.xml`)
- `npm run build` — production frontend build

## Routing table — read exactly ONE before touching that area, MUST NOT read the others speculatively
| Task touches | Read |
|---|---|
| `ProcessMeetingJob`, Gemini prompts, FFmpeg extraction, WebSocket progress broadcasts | [.agents/pipeline.md](.agents/pipeline.md) |
| Controllers, routes, form requests, authorization (`abort_if`) | [.agents/http-layer.md](.agents/http-layer.md) |
| Eloquent models, migrations, factories | [.agents/data-model.md](.agents/data-model.md) |
| React/Inertia pages, Zustand stores, Echo/Reverb frontend wiring | [.agents/frontend.md](.agents/frontend.md) |
| Pest tests, CI workflow | [.agents/testing.md](.agents/testing.md) |
| Logging conventions, error handling, retries | [.agents/defense.md](.agents/defense.md) |

## Permissions
Free to do: add/edit Pest tests, add log statements following the existing convention, run migrations locally, run `composer dev`/`npm run dev`.
MUST ASK FIRST: schema changes to already-migrated columns, changing `.env.example` secrets/keys, editing `docker-compose.yml` ports, force-pushing, deleting migrations, changing `ProcessMeetingJob::$tries`/`$timeout`/`$uniqueFor` (see [.agents/pipeline.md](.agents/pipeline.md)).

## Priority Order (higher wins on conflict)
1. Data integrity — never leave a `meeting.status` in an inconsistent state (e.g. `processing` forever) or silently drop a partial transcript/summary. If a user instruction would cause this, warn and stop before proceeding.
2. This file + `.agents/*.md`
3. Explicit user instruction for the current task
4. Inferred convention from surrounding code

## Scope discipline
- Touch only files required by the task. No drive-by renames, reformatting, or "cleanup" of unrelated code.
- If you spot an unrelated bug or magic value, propose it to the user — do not fix it inline.
- One task = one focused diff. If the true scope turns out much larger than requested, stop and ask before continuing.
- Hardcoded values that need a "confirm before changing" flag: `gemini-2.5-flash` model string, `ProcessMeetingJob` chunk/retry constants, `ChunkUploadController::ALLOWED_EXT`, pagination sizes (12 for meetings, 20 for todos) — these were tuned deliberately, not arbitrary.
