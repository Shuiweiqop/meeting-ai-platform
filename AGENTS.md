# AGENTS.md — Meeting AI Platform (Router)

Laravel 13 + React/Inertia app that turns uploaded meeting audio/video into transcripts, AI summaries, and action items via Gemini.

## Commands (verified against composer.json / package.json / docker-compose.yml)
Requires: Docker (MySQL container on host port `3307`, Redis on `16379` — see `docker-compose.yml`), PHP 8.3, Node 20, FFmpeg binary on PATH (used by `php-ffmpeg/php-ffmpeg`, not bundled).
- `composer install && npm install`
- `docker compose up -d` (or `docker start meeting_ai_mysql meeting_ai_redis` if containers already exist)
- `composer run dev` — one command, six concurrent processes via `concurrently`: `php artisan serve` + `queue:listen` + `pail` (log tail) + `vite` + `reverb:start` (WebSocket server) + `schedule:work` (scheduler for `meetings:fail-stuck`). Defined in `composer.json` → `scripts.dev`. Killing the command (`--kill-others`) stops all six.
- The only thing `composer dev` does NOT start is Docker (MySQL + Redis) — start that first with `docker compose up -d`.
- `php artisan test --parallel` or `./vendor/bin/pest --parallel` — matches `.github/workflows/ci.yml` exactly
- `npm run build` — production frontend build (also the CI step)

## Read the file your task touches. When unsure, read it.
| Task touches | Read |
|---|---|
| `ProcessMeetingJob`, Gemini prompts, FFmpeg extraction, stage/progress broadcasts | [.agents/pipeline.md](.agents/pipeline.md) |
| Controllers, routes, form requests, the `abort_if` ownership checks | [.agents/http-layer.md](.agents/http-layer.md) |
| Eloquent models, migrations, factories | [.agents/data-model.md](.agents/data-model.md) |
| React/Inertia pages, Zustand audio store, Echo/Reverb frontend wiring | [.agents/frontend.md](.agents/frontend.md) |
| Pest tests, CI workflow | [.agents/testing.md](.agents/testing.md) |

Changes spanning several areas (especially anything touching `meetings.status`/`processing_stage`): read all relevant files. Missing one costs far more than reading an extra one.

If your task doesn't match any row above: don't guess at project-specific convention — ask, or say what you don't know, before inventing a pattern.

## Permissions
Free to do: add/edit Pest tests, run migrations locally, run `composer dev`/`npm run dev`.
Ask first: editing an already-committed migration's `up()` (see [.agents/data-model.md](.agents/data-model.md)), changing `.env.example` secrets/keys, editing `docker-compose.yml` ports, changing `ProcessMeetingJob::$tries`/`$timeout`/`$uniqueFor` (see [.agents/pipeline.md](.agents/pipeline.md)), force-pushing.

## Priority Order (higher wins on conflict)
1. Data integrity — never leave `meetings.status` inconsistent with `processing_stage` (e.g. `status=processing` with no forward progress, or `completed` with a deleted transcript). If a requested change would risk this, say so and stop before making it.
2. This file + `.agents/*.md`
3. The user's explicit instruction for the current task
4. Convention inferred from surrounding code

## Scope discipline
A task modifies the minimum coherent set of files required to solve one problem. Unrelated cleanup belongs in a different diff. If you spot an unrelated bug or magic value, propose it — don't fix it inline. If the real scope turns out to be much larger than requested, stop and ask.

## Logging (cross-cutting — applies in every domain file above, not just one)
Every log line in this codebase follows `Log::info("<ClassName> [{$id}]: <message>.")` (established in `ProcessMeetingJob`, e.g. `Log::info("ProcessMeetingJob [{$this->meeting->id}]: transcript saved.")`). Keep the literal class name + bracketed id + colon shape in any new log line — it's what makes `grep 'ProcessMeetingJob \['` in `storage/logs` return one entity's full lifecycle instead of nothing. `info` = normal milestone, `warning` = degraded-but-recovered, `error` = terminal failure (paired with a `failed()`-style hook, not a bare catch — see [.agents/pipeline.md](.agents/pipeline.md)). Never log the Gemini API key, Slack webhook URL, or raw audio bytes.

## Status of enforcement in this repo (read before assuming a rule below is checked)
Enforced by a build or test today:
- **Pint** runs in CI (`./vendor/bin/pint --test`, config in `pint.json`) — style violations fail the build.
- **`status`/`processing_stage` coupling** — `Meeting::transitionTo()` is the only writer of the pair and throws `LogicException` on an inconsistent combination (see [.agents/data-model.md](.agents/data-model.md)).
- **Stage vocabulary PHP↔JS** — `App\Enums\ProcessingStage` is cross-checked against `Show.jsx` `STAGES` by `tests/Unit/AgentsDocGuardTest.php`.
- **Gemini fence-stripping** — centralized in `App\Support\GeminiJson::decode()`, unit-tested; a hardcoded Gemini model string outside `config/services.php` fails `AgentsDocGuardTest`.
- **Doc drift** — `tests/Unit/AgentsDocGuardTest.php` pins the factual claims in this file (reverb absent from `composer dev`, no static-analysis package). If it fails, update the doc in the same diff as the code change.

Still prose-enforced only: no PHPStan/Larastan, no ESLint config, no Pest arch tests, and no custom global exception-handler logic (`bootstrap/app.php`'s `withExceptions()` body is empty; `pestphp/pest-plugin-arch` is only a transitive dependency). Authorization `abort_if` shapes, the no-try/catch rule in `ProcessMeetingJob::handle()`, and migration immutability are **not** checked by any tool — violating them does not fail a build.
