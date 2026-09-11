<?php

use App\Models\Meeting;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

// Private channel — the uploader and (for team meetings) team members can
// listen, matching Meeting::isAccessibleBy so live progress reaches everyone
// who can open the meeting page.
Broadcast::channel('meetings.{meetingId}', function ($user, $meetingId) {
    $meeting = Meeting::with('team')->find($meetingId);

    return $meeting && $meeting->isAccessibleBy($user);
});
