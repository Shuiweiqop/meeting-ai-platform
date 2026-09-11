<?php

namespace App\Http\Controllers;

use App\Models\Meeting;
use App\Models\Team;
use App\Models\TodoItem;
use App\Support\IcsBuilder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CalendarController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $userId = Auth::id();

        // Which month to show. Defaults to the current month; ?month=YYYY-MM
        // navigates. Invalid input falls back rather than 500-ing.
        try {
            $cursor = $request->filled('month')
                ? Carbon::createFromFormat('Y-m', $request->query('month'))->startOfMonth()
                : Carbon::now()->startOfMonth();
        } catch (\Throwable) {
            $cursor = Carbon::now()->startOfMonth();
        }

        $rangeStart = $cursor->copy()->startOfMonth();
        $rangeEnd = $cursor->copy()->endOfMonth();

        // Teams the user can filter by (owner or member), plus the selected one.
        $teams = Team::where('owner_id', $userId)
            ->orWhereHas('members', fn ($q) => $q->where('users.id', $userId))
            ->get(['id', 'name']);

        $teamFilter = $request->query('team');
        $teamFilter = $teams->contains('id', (int) $teamFilter) ? (int) $teamFilter : null;

        $meetingsQuery = Meeting::query()
            ->whereRaw('COALESCE(meeting_date, created_at) BETWEEN ? AND ?', [$rangeStart, $rangeEnd]);

        if ($teamFilter) {
            // Team view: every meeting in that team, whoever uploaded it.
            $meetingsQuery->where('team_id', $teamFilter);
        } else {
            // Personal view: only the user's own meetings.
            $meetingsQuery->where('user_id', $userId);
        }

        $meetings = $meetingsQuery->get(['id', 'title', 'status', 'meeting_date', 'created_at']);

        // Todos are personal — they hang off assignment, not a team — so they
        // only show in the personal view.
        $todos = $teamFilter
            ? collect()
            : TodoItem::where('assigned_to', $userId)
                ->whereNotNull('due_date')
                ->whereBetween('due_date', [$rangeStart->toDateString(), $rangeEnd->toDateString()])
                ->with('meeting:id,title')
                ->get(['id', 'title', 'status', 'due_date', 'meeting_id']);

        $items = collect();

        foreach ($meetings as $m) {
            $items->push([
                'date' => $m->occurredAt()->toDateString(),
                'sort' => $m->occurredAt()->timestamp,
                'entry' => [
                    'type' => 'meeting',
                    'id' => $m->id,
                    'title' => $m->title,
                    'status' => $m->status,
                    'time' => $m->occurredAt()->format('H:i'),
                ],
            ]);
        }

        foreach ($todos as $t) {
            $items->push([
                'date' => $t->due_date->toDateString(),
                'sort' => $t->due_date->timestamp,
                'entry' => [
                    'type' => 'todo',
                    'id' => $t->id,
                    'title' => $t->title,
                    'status' => $t->status,
                    'meeting_id' => $t->meeting_id,
                ],
            ]);
        }

        $byDay = $items
            ->groupBy('date')
            ->map(fn ($group) => $group->sortBy('sort')->pluck('entry')->values());

        return Inertia::render('Meeting/Calendar', [
            'month' => $cursor->format('Y-m'),
            'monthLabel' => $cursor->format('F Y'),
            'prevMonth' => $cursor->copy()->subMonth()->format('Y-m'),
            'nextMonth' => $cursor->copy()->addMonth()->format('Y-m'),
            'today' => Carbon::now()->toDateString(),
            'byDay' => $byDay,
            'teams' => $teams,
            'activeTeam' => $teamFilter,
        ]);
    }

    /**
     * Export meetings (+ the user's dated todos in the personal view) as a
     * downloadable .ics the user can subscribe to in Google Calendar / Outlook.
     * Scope mirrors the calendar view: a team filter exports that team's
     * meetings; otherwise the user's own meetings and todo due dates.
     */
    public function export(Request $request): StreamedResponse
    {
        $userId = Auth::id();

        $teamIds = Team::where('owner_id', $userId)
            ->orWhereHas('members', fn ($q) => $q->where('users.id', $userId))
            ->pluck('id');

        $teamFilter = $request->query('team');
        $teamFilter = $teamIds->contains((int) $teamFilter) ? (int) $teamFilter : null;

        $meetings = Meeting::query()
            ->when($teamFilter,
                fn ($q) => $q->where('team_id', $teamFilter),
                fn ($q) => $q->where('user_id', $userId),
            )
            ->get(['id', 'title', 'status', 'meeting_date', 'created_at']);

        $todos = $teamFilter
            ? collect()
            : TodoItem::where('assigned_to', $userId)
                ->whereNotNull('due_date')
                ->get(['id', 'title', 'due_date']);

        $ics = new IcsBuilder('Meeting AI');

        foreach ($meetings as $m) {
            $ics->event(
                uid: "meeting-{$m->id}@meeting-ai",
                start: $m->occurredAt(),
                summary: $m->title,
                description: 'Status: '.$m->status,
            );
        }

        foreach ($todos as $t) {
            $ics->allDayEvent(
                uid: "todo-{$t->id}@meeting-ai",
                day: $t->due_date,
                summary: 'Due: '.$t->title,
            );
        }

        $body = $ics->toString();

        return response()->streamDownload(
            fn () => print ($body),
            'meeting-ai-calendar.ics',
            ['Content-Type' => 'text/calendar; charset=utf-8'],
        );
    }
}
