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
    // How long a freshly generated share link stays valid.
    private const SHARE_TTL_DAYS = 30;

    public function show(string $token): Response
    {
        $meeting = Meeting::where('share_token', $token)
            ->where('status', 'completed')
            ->firstOrFail();

        // An expired token exists in the DB but must not grant access — show a
        // clear "expired" page rather than a bare 404, so a recipient knows to
        // ask for a fresh link rather than thinking the meeting is gone.
        if (! $meeting->hasActiveShareLink()) {
            return Inertia::render('Share/Expired');
        }

        $meeting->load(['transcript', 'aiSummary', 'todoItems.assignee']);

        return Inertia::render('Share/Show', ['meeting' => $meeting]);
    }

    public function generate(Meeting $meeting): RedirectResponse
    {
        abort_if($meeting->user_id !== Auth::id(), 403);
        abort_if($meeting->status !== 'completed', 422);

        // Regenerate the token if there isn't an active one (missing or expired),
        // and always (re)set a fresh expiry so "generate" gives a working link.
        if (! $meeting->hasActiveShareLink()) {
            $meeting->share_token = Str::random(32);
        }
        $meeting->share_expires_at = now()->addDays(self::SHARE_TTL_DAYS);
        $meeting->save();

        return back()->with('shareUrl', url("/share/{$meeting->share_token}"));
    }

    public function revoke(Meeting $meeting): RedirectResponse
    {
        abort_if($meeting->user_id !== Auth::id(), 403);

        $meeting->update(['share_token' => null, 'share_expires_at' => null]);

        return back()->with('success', 'Share link revoked.');
    }
}
