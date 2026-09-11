<?php

namespace App\Models;

use App\Enums\ProcessingStage;
use App\Events\MeetingStatusUpdated;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

#[Fillable(['team_id', 'user_id', 'title', 'description', 'audio_path', 'status', 'processing_stage', 'duration_seconds', 'share_token', 'share_expires_at', 'meeting_date'])]
class Meeting extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'duration_seconds' => 'integer',
            'meeting_date' => 'datetime',
            'share_expires_at' => 'datetime',
        ];
    }

    /**
     * Whether the public share link is currently usable: a token exists and
     * hasn't expired. The share route and the owner's UI both read this so the
     * "active" definition lives in one place.
     */
    public function hasActiveShareLink(): bool
    {
        return $this->share_token
            && (! $this->share_expires_at || $this->share_expires_at->isFuture());
    }

    /**
     * When the meeting happened, for display and calendar grouping. Falls back
     * to created_at (upload time) when the uploader didn't set a date. Use this
     * everywhere instead of reading meeting_date directly, so the fallback is
     * defined in one place.
     */
    public function occurredAt(): Carbon
    {
        return $this->meeting_date ?? $this->created_at;
    }

    /**
     * The only writer of the status/processing_stage pair. A stage is only
     * meaningful while processing, and every change must broadcast or the
     * frontend StageTracker shows a stale stage — this method makes both
     * invariants structural instead of conventions held by call sites.
     */
    public function transitionTo(string $status, ?ProcessingStage $stage = null): void
    {
        if (! in_array($status, ['pending', 'processing', 'completed', 'failed'], true)) {
            throw new \LogicException("Unknown meeting status [{$status}].");
        }

        if ($stage !== null && $status !== 'processing') {
            throw new \LogicException("processing_stage [{$stage->value}] requires status=processing, got [{$status}].");
        }

        $this->update(['status' => $status, 'processing_stage' => $stage?->value]);
        broadcast(new MeetingStatusUpdated($this));
    }

    /**
     * Who may VIEW this meeting (show page, audio stream, PDF export, todos).
     * The uploader always can; if the meeting belongs to a team, every member
     * of that team can too. This is the single definition of read access —
     * controllers call it instead of re-deriving `user_id === Auth::id()`, so
     * viewing rules can't drift apart across the seven meeting actions.
     */
    public function isAccessibleBy(User $user): bool
    {
        if ($this->user_id === $user->id) {
            return true;
        }

        return $this->team_id !== null && $this->team
            && ($this->team->owner_id === $user->id
                || $this->team->members()->where('users.id', $user->id)->exists());
    }

    /**
     * Who may MANAGE this meeting (edit, update, delete, retry, share links).
     * Only the uploader — team membership grants read access, never the right
     * to change or delete someone else's recording. Kept separate from
     * isAccessibleBy on purpose: widening viewing must not widen mutation.
     */
    public function isManageableBy(User $user): bool
    {
        return $this->user_id === $user->id;
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function transcript(): HasOne
    {
        return $this->hasOne(Transcript::class);
    }

    public function aiSummary(): HasOne
    {
        return $this->hasOne(AiSummary::class);
    }

    public function todoItems(): HasMany
    {
        return $this->hasMany(TodoItem::class);
    }
}
