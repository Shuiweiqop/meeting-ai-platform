<?php

namespace App\Http\Controllers;

use App\Models\Meeting;
use App\Models\Team;
use App\Models\TodoItem;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(): Response
    {
        $userId = Auth::id();

        $recentMeetings = Meeting::where('user_id', $userId)
            ->latest()
            ->limit(5)
            ->get(['id', 'title', 'status', 'created_at']);

        $myTodos = TodoItem::where('assigned_to', $userId)
            ->whereIn('status', ['pending', 'in_progress'])
            ->with('meeting:id,title')
            ->latest()
            ->limit(10)
            ->get(['id', 'title', 'status', 'meeting_id']);

        $teamIds = Team::where('owner_id', $userId)
            ->orWhereHas('members', fn ($q) => $q->where('users.id', $userId))
            ->pluck('id');

        $teamActivity = Meeting::whereIn('team_id', $teamIds)
            ->with(['user:id,name', 'team:id,name'])
            ->latest()
            ->limit(5)
            ->get(['id', 'title', 'status', 'created_at', 'user_id', 'team_id']);

        return Inertia::render('Dashboard', [
            'recentMeetings' => $recentMeetings,
            'myTodos' => $myTodos,
            'teamActivity' => $teamActivity,
        ]);
    }
}
