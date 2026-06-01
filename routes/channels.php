<?php

use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

// Private channel — only the meeting uploader can listen
Broadcast::channel('meetings.{meetingId}', function ($user, $meetingId) {
    return \App\Models\Meeting::where('id', $meetingId)
        ->where('user_id', $user->id)
        ->exists();
});
