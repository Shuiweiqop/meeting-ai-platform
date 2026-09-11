# HTTP layer — Controllers, routes, form requests, authorization

Not sunk into code: no Policy classes, no middleware-based ownership check, no arch test enforcing this. It's a convention held together by every controller doing the same thing by hand — see [../AGENTS.md](../AGENTS.md) "Status of enforcement".

## Authorization shape — match the one for your resource
Every mutating/viewing action checks authorization with `abort_if`/`abort_unless(...)` as the first line, before any read or mutation:
- **Meeting — view vs. manage are split** (`Meeting::isAccessibleBy` / `isManageableBy`, the single source of truth). *View* (show, audio, exportPdf) is the uploader **or any member of the meeting's team**; *manage* (edit, update, destroy, retry, share-link generate/revoke) is the uploader only. `abort_unless($meeting->isAccessibleBy(Auth::user()), 403)` vs. `isManageableBy`. Widening viewing must never widen mutation — that's why they're two methods, not one. Any new meeting action must call the right one; don't re-derive `user_id === Auth::id()` inline (it silently excludes team members from a read action, or lets a member mutate someone's recording).
- **Owner-or-member** (`TeamController::show`): `abort_if($team->owner_id !== Auth::id() && ! $team->members()->where('users.id', Auth::id())->exists(), 403)`.
- **Owner-or-assignee** (`TodoItemController::update`): uploader of the meeting or the todo's assignee can update it.

The broadcast channel `meetings.{id}` (routes/channels.php) uses `isAccessibleBy` too, so team members receive live progress for meetings they can open. `team_id` is nullable — a personal meeting is never visible to anyone but its uploader, which `isAccessibleBy` enforces by guarding on `team_id !== null` first.

Picking the wrong check is the actual risk: applying uploader-only logic to a read action locks out team members who should see the meeting; applying view logic to a mutation lets a member delete someone else's recording. Check whether the action is a read or a mutation before copying a pattern.

## Calendar scope: personal vs team view, and what the .ics export must mirror
`CalendarController` (`__invoke` for the grid, `export` for `.ics`) has two scopes that must stay in sync between the two methods and their tests: **personal view** (no `?team=`) shows the user's own meetings *plus* their dated todos; **team view** (`?team={id}`, validated against teams the user owns or belongs to) shows that team's meetings and *no* todos (todos are assignment-scoped, not team-scoped). The `.ics` export applies the exact same scoping — if you change one, change the other, or the download silently disagrees with the on-screen calendar. `.ics` formatting lives in `App\Support\IcsBuilder` (CRLF, escaping, unique UIDs); don't hand-build iCalendar strings in the controller.

## Audio is served through a guarded route, never a public URL
Recordings live on the private `local` disk; `MeetingController::audio` (`GET /meetings/{meeting}/audio`) is the only way to reach them, behind the single-owner `abort_if`. Never store meeting audio on the `public` disk or link it via `/storage/...` — that hands out the uploader's raw recording to anyone holding the URL, with no revocation. The response is a `BinaryFileResponse` because `<audio>` seeking needs HTTP Range support.

## The one route with no auth
`SharedMeetingController::show` (`/share/{token}`) is intentionally outside the `auth` middleware group — it's the public share-link view. It scopes by `share_token` + `status = 'completed'`, not by user. Anything added to this method must not assume `Auth::id()` is available, and must not eager-load or expose fields beyond what a public viewer should see (e.g. don't add the uploader's email to the `Inertia::render` payload here). Links expire: `show` renders `Share/Expired` (not the meeting) unless `Meeting::hasActiveShareLink()` — the single definition of "token exists and not expired". `generate` sets a 30-day `share_expires_at`; `revoke` nulls both fields. A null expiry means non-expiring (legacy rows) — new links always carry one.

## Slack webhook is an SSRF surface — validate the host
The user-supplied `slack_webhook_url` is POSTed to by the queue worker, so an arbitrary URL is a server-side request forgery vector. `App\Rules\SlackWebhookUrl` (host must be `hooks.slack.com` over https) guards it at save time in `ProfileUpdateRequest`; `ProcessMeetingJob::notifySlack` re-checks the host before sending (defense in depth, for rows predating the rule). The allowed host constant lives in the rule — reuse `SlackWebhookUrl::ALLOWED_HOST`, don't hardcode it a third time. Any new place that sends to a user-supplied URL needs the same host check.

## Broadcast channel auth lives in `routes/channels.php`, not a controller
`Broadcast::channel('meetings.{meetingId}', ...)` authorizes via `Meeting::isAccessibleBy($user)` — the same rule as the view actions above — so the uploader and team members both receive live progress. Keep this callback and the controllers' read check on the same method: if they drift, a member either sees the page with a frozen progress bar (channel too strict) or gets events for a meeting they can't open (channel too loose). A rejected subscription is silent — no events, no error — so a mismatch here fails quietly.

## The one controller with a try/catch
`ChunkUploadController::merge` wraps the chunk-stitching loop in try/catch specifically to `@unlink($finalAbsPath)` before rethrowing — cleaning up a half-written file, not suppressing the error. Every other controller lets exceptions bubble to Laravel's default handler. If you add another multi-step file operation in a controller, this "cleanup then rethrow" shape is the one to copy; a catch that doesn't rethrow would return a 200-looking response for a request that actually failed halfway.
