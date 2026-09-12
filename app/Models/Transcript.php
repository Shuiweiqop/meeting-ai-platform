<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['meeting_id', 'content', 'segments', 'language'])]
class Transcript extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return ['segments' => 'array'];
    }

    /**
     * Plain-text `content` is derived from `segments` (the source of truth) as
     * "Speaker: text" blocks. PDF export and Slack read `content`, so any code
     * that writes `segments` must re-derive `content` through here — this is the
     * single definition, shared by ProcessMeetingJob and the segment editor, so
     * the two can't drift (see .agents/data-model.md).
     *
     * @param  array<int, array{start?: float, speaker?: string, text?: string}>  $segments
     */
    public static function contentFromSegments(array $segments): string
    {
        return collect($segments)
            ->map(fn ($s) => trim((! empty($s['speaker']) ? "{$s['speaker']}: " : '').($s['text'] ?? '')))
            ->implode("\n\n");
    }

    public function meeting(): BelongsTo
    {
        return $this->belongsTo(Meeting::class);
    }
}
