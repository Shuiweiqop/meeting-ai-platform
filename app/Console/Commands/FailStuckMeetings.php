<?php

namespace App\Console\Commands;

use App\Jobs\ProcessMeetingJob;
use App\Models\Meeting;
use Illuminate\Bus\UniqueLock;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class FailStuckMeetings extends Command
{
    protected $signature = 'meetings:fail-stuck {--minutes=30 : Minutes without progress before a processing meeting is considered stuck}';

    protected $description = 'Mark meetings stuck in processing as failed so their owners can retry them';

    public function handle(): int
    {
        $threshold = now()->subMinutes((int) $this->option('minutes'));

        // updated_at is a reliable progress heartbeat: every stage transition
        // goes through Meeting::transitionTo(), which touches the row.
        $stuck = Meeting::where('status', 'processing')
            ->where('updated_at', '<', $threshold)
            ->get();

        foreach ($stuck as $meeting) {
            $lastProgress = $meeting->updated_at?->toDateTimeString();
            $meeting->transitionTo('failed');

            // A lost job (dead worker) never releases its ShouldBeUnique lock,
            // which would silently swallow the owner's retry dispatch for up
            // to $uniqueFor seconds — release it so retry actually works.
            (new UniqueLock(Cache::store()))->release(new ProcessMeetingJob($meeting));

            Log::warning("FailStuckMeetings [{$meeting->id}]: no progress since {$lastProgress}, marked failed.");
        }

        $this->info("Marked {$stuck->count()} stuck meeting(s) as failed.");

        return self::SUCCESS;
    }
}
