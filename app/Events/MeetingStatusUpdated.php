<?php

namespace App\Events;

use App\Models\Meeting;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MeetingStatusUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public readonly Meeting $meeting) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel("meetings.{$this->meeting->id}")];
    }

    public function broadcastWith(): array
    {
        return [
            'status'           => $this->meeting->status,
            'processing_stage' => $this->meeting->processing_stage,
        ];
    }

    // Only broadcast the event name, not the full class path
    public function broadcastAs(): string
    {
        return 'MeetingStatusUpdated';
    }
}
