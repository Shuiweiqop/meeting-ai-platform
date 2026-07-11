# HTTP layer — Controllers, routes, form requests, authorization

Not sunk into code: no Policy classes, no middleware-based ownership check, no arch test enforcing this. It's a convention held together by every controller doing the same thing by hand — see [../AGENTS.md](../AGENTS.md) "Status of enforcement".

## Authorization shape — three variants exist, match the one for your resource
Every mutating/viewing action on a user-owned resource checks ownership with `abort_if(...)` as the first line, before any read or mutation:
- **Single-owner** (`Meeting`, most `Team` actions): `abort_if($model->user_id !== Auth::id(), 403)`.
- **Owner-or-member** (`TeamController::show`): `abort_if($team->owner_id !== Auth::id() && ! $team->members()->where('users.id', Auth::id())->exists(), 403)` — a team is visible to its members, not just its owner.
- **Owner-or-assignee** (`TodoItemController::update`): `$isOwner = $todoItem->meeting->user_id === Auth::id(); $isAssignee = $todoItem->assigned_to === Auth::id(); abort_if(! $isOwner && ! $isAssignee, 403)` — either the meeting's uploader or the person a todo is assigned to can toggle its status.

Picking the wrong variant for a new action is the actual risk: applying single-owner logic to a team-scoped or assignment-scoped resource locks out people who should have access (e.g. a team member trying to view a team, or an assignee trying to complete their own todo) rather than merely being over-permissive. Check which relationship — ownership, membership, or assignment — actually governs the resource before copying a pattern.

## The one route with no auth
`SharedMeetingController::show` (`/share/{token}`) is intentionally outside the `auth` middleware group — it's the public share-link view. It scopes by `share_token` + `status = 'completed'`, not by user. Anything added to this method must not assume `Auth::id()` is available, and must not eager-load or expose fields beyond what a public viewer should see (e.g. don't add the uploader's email to the `Inertia::render` payload here).

## Broadcast channel auth lives in `routes/channels.php`, not a controller
`Broadcast::channel('meetings.{meetingId}', ...)` authorizes the **uploader only** (`where('user_id', $user->id)`), mirroring the single-owner variant above. If meeting visibility ever widens (e.g. team members can view a meeting), this channel callback must widen with the controller check or members will see the page but never receive progress updates — a silent mismatch, since a rejected channel subscription just means no events, not an error.

## The one controller with a try/catch
`ChunkUploadController::merge` wraps the chunk-stitching loop in try/catch specifically to `@unlink($finalAbsPath)` before rethrowing — cleaning up a half-written file, not suppressing the error. Every other controller lets exceptions bubble to Laravel's default handler. If you add another multi-step file operation in a controller, this "cleanup then rethrow" shape is the one to copy; a catch that doesn't rethrow would return a 200-looking response for a request that actually failed halfway.
