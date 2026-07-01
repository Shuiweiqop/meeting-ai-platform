# Data model domain — Eloquent models, migrations, factories

MUST NOT read this file for controller/HTTP or pipeline-only work — see [../AGENTS.md](../AGENTS.md) routing table.

## Entity relationships
```
User ──< Meeting >── Team (nullable — a meeting can be personal, no team)
User >──< Team (belongsToMany, pivot: role)   // owner_id also on Team directly
Meeting ──1 Transcript (segments: JSON array of {start, speaker, text})
Meeting ──1 AiSummary  (key_points: JSON array)
Meeting ──< TodoItem >── User (assigned_to, nullable)
```

## Conventions — MUST follow for any new model
- Fillable declared via PHP attribute `#[Fillable([...])]` above the class, NOT a `protected $fillable` property. This project is on Laravel 13's attribute-based model config — match it.
- Hidden fields likewise via `#[Hidden([...])]` (see `User`), not `protected $hidden`.
- Casts stay in a `protected function casts(): array` method (Laravel 11+ style), not `protected $casts`.
- Every model uses `HasFactory` and has a corresponding factory in `database/factories/` — new models MUST get one too (tests rely on factories, not raw `Model::create`).

## Migration naming and ordering
- Dated migrations (`2026_MM_DD_NNNNNN_description.php`) are additive, one concern per file — e.g. `add_share_token_to_meetings`, `add_processing_stage_to_meetings`, `add_segments_to_transcripts`. FORBIDDEN to edit an already-run/committed migration's `up()` — add a new migration instead, since production/CI history depends on the existing one being immutable.
- `make_meetings_team_id_nullable` exists because a meeting can be personal (no team) — don't reintroduce a `NOT NULL` constraint on `meetings.team_id`.

## Key domain invariants (confirm before changing)
- `meetings.status`: `pending` → `processing` → `completed` | `failed`. `processing_stage` is only non-null while `status = processing` (set to `null` on both `completed` and `failed`). Any code path that sets `status` MUST also correctly set/clear `processing_stage` — see [.agents/pipeline.md](pipeline.md).
- `transcripts.segments` is the source of truth for the timestamped transcript; `transcripts.content` is a derived plain-text join of segments kept for backwards compatibility (PDF export, Slack, plain search) — MUST keep both in sync if segment generation logic changes.
- `todo_items.assigned_to` is resolved by fuzzy name match (`User::where('name', 'like', ...)`) against Gemini's free-text `assignee_name` — it can be `null` (unassigned). Don't assume a todo always has an assignee.
