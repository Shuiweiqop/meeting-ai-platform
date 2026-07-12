<?php

use App\Models\Meeting;
use Illuminate\Support\Facades\DB;

it('fails processing meetings with no recent progress and leaves the rest alone', function () {
    $stuck = Meeting::factory()->create(['status' => 'processing', 'processing_stage' => 'transcribing']);
    $fresh = Meeting::factory()->create(['status' => 'processing', 'processing_stage' => 'transcribing']);
    $done = Meeting::factory()->create(['status' => 'completed']);

    // Query-builder update bypasses Eloquent's automatic timestamp touching.
    DB::table('meetings')->where('id', $stuck->id)->update(['updated_at' => now()->subHour()]);
    DB::table('meetings')->where('id', $done->id)->update(['updated_at' => now()->subHour()]);

    $this->artisan('meetings:fail-stuck')->assertSuccessful();

    expect($stuck->fresh())
        ->status->toBe('failed')
        ->processing_stage->toBeNull()
        ->and($fresh->fresh()->status)->toBe('processing')
        ->and($done->fresh()->status)->toBe('completed');
});

it('respects the --minutes threshold', function () {
    $meeting = Meeting::factory()->create(['status' => 'processing', 'processing_stage' => 'transcribing']);
    DB::table('meetings')->where('id', $meeting->id)->update(['updated_at' => now()->subMinutes(10)]);

    $this->artisan('meetings:fail-stuck', ['--minutes' => 60])->assertSuccessful();
    expect($meeting->fresh()->status)->toBe('processing');

    $this->artisan('meetings:fail-stuck', ['--minutes' => 5])->assertSuccessful();
    expect($meeting->fresh()->status)->toBe('failed');
});
