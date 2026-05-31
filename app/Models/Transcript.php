<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['meeting_id', 'content', 'language'])]
class Transcript extends Model
{
    use HasFactory;

    public function meeting(): BelongsTo
    {
        return $this->belongsTo(Meeting::class);
    }
}
