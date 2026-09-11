# Data model — Eloquent models, migrations, factories

Not sunk into code: nothing blocks editing a committed migration or forgetting a factory except this file. See [../AGENTS.md](../AGENTS.md) "Status of enforcement".

## Relationships
```
User ──< Meeting >── Team (team_id nullable — a meeting can be personal)
User >──< Team (belongsToMany, pivot: role)
Meeting ──1 Transcript (segments: JSON array of {start, speaker, text})
Meeting ──1 AiSummary  (key_points: JSON array)
Meeting ──< TodoItem >── User (assigned_to, nullable)
```

## Why `meetings.team_id` must stay nullable
The `make_meetings_team_id_nullable` migration exists because personal (non-team) meetings are a supported case, added after the schema originally required a team. Reintroducing a `NOT NULL` constraint would break every personal-meeting upload silently at the database layer (a `QueryException`, not a validation error the user sees cleanly).

## Why migrations already run must not be edited in place
Editing a committed migration's `up()` changes what a fresh `migrate` produces without changing what already-migrated databases (CI's SQLite, anyone's local MySQL, eventually production) have. The two diverge with no error — a column that "should" exist per the migration file simply won't, on any database that ran the old version. Add a new migration instead.

## `meetings.status` / `processing_stage` coupling
`processing_stage` is non-null only while `status = 'processing'` — both `completed` and `failed` set it back to `null` (see [pipeline.md](pipeline.md)). This pairing is now enforced: `Meeting::transitionTo(string $status, ?ProcessingStage $stage = null)` is the only writer of the pair, throws `LogicException` on an inconsistent combination, and broadcasts `MeetingStatusUpdated` on every change (tested in `tests/Feature/ProcessMeetingPipelineTest.php`). Never write `status` or `processing_stage` via a bare `update()` — that reintroduces the stuck-progress-bar failure mode by skipping the guard and the broadcast.

## `meetings.meeting_date` is optional; read it via `occurredAt()`
`meeting_date` (nullable timestamp) is when the meeting actually happened, set optionally at upload. It's distinct from `created_at` (upload time). Never read `meeting_date` directly for display or grouping — call `Meeting::occurredAt()`, which falls back to `created_at` when it's null, so the fallback lives in one place. The calendar (`CalendarController` → `Meeting/Calendar.jsx`) groups by this; its month filter uses `COALESCE(meeting_date, created_at)` in SQL to match `occurredAt()` exactly, so a row can't be filtered into a month but grouped into a different day.

## `transcripts.content` vs `transcripts.segments`
`segments` (JSON) is the source of truth; `content` (plain text) is a derived join of segments, kept because PDF export, the Slack notification, and (potentially) full-text search read it directly rather than re-deriving from segments. If segment-generation logic changes, `content`'s derivation in `ProcessMeetingJob::handle()` must change with it — nothing will flag content silently going stale relative to segments, since they're independent columns.

## `todo_items.assigned_to` is fuzzy, not exact
It's resolved via `User::where('name', 'like', '%' . $todo['assignee_name'] . '%')->first()` against Gemini's free-text guess at who a todo belongs to. It can be `null` (unmatched or no assignee mentioned), and a `like` match means two users with overlapping name substrings could resolve to the wrong one. Code reading `assigned_to` must handle `null`; code changing the matching logic should know it's approximate by design, not a bug to "fix" into an exact match without confirming that's wanted (an exact match would silently assign fewer todos whenever Gemini's name differs slightly from the stored name, e.g. nickname vs. full name).
