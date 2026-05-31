<?php

namespace App\Http\Controllers;

use App\Models\Meeting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class SharedMeetingController extends Controller
{
    public function show(string $token): Response
    {
        $meeting = Meeting::where('share_token', $token)
            ->where('status', 'completed')
            ->firstOrFail();

        $meeting->load(['transcript', 'aiSummary', 'todoItems.assignee']);

        return Inertia::render('Share/Show', ['meeting' => $meeting]);
    }

    public function generate(Meeting $meeting): RedirectResponse
    {
        abort_if($meeting->user_id !== Auth::id(), 403);
        abort_if($meeting->status !== 'completed', 422);

        if (! $meeting->share_token) {
            $meeting->update(['share_token' => Str::random(32)]);
        }

        return back()->with('shareUrl', url("/share/{$meeting->share_token}"));
    }

    public function revoke(Meeting $meeting): RedirectResponse
    {
        abort_if($meeting->user_id !== Auth::id(), 403);

        $meeting->update(['share_token' => null]);

        return back()->with('success', 'Share link revoked.');
    }
}
