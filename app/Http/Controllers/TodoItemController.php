<?php

namespace App\Http\Controllers;

use App\Models\TodoItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class TodoItemController extends Controller
{
    public function index(Request $request): Response
    {
        $status = $request->get('status');

        $query = TodoItem::where('assigned_to', Auth::id())
            ->with('meeting:id,title')
            ->latest();

        if ($status && in_array($status, ['pending', 'in_progress', 'completed'])) {
            $query->where('status', $status);
        }

        $todos = $query->paginate(20)->withQueryString();

        $counts = TodoItem::where('assigned_to', Auth::id())
            ->selectRaw("
                count(*) as total,
                sum(status = 'pending') as pending,
                sum(status = 'in_progress') as in_progress,
                sum(status = 'completed') as completed
            ")
            ->first();

        return Inertia::render('Todos/Index', [
            'todos' => $todos,
            'counts' => $counts,
            'activeStatus' => $status ?? 'all',
        ]);
    }

    public function update(Request $request, TodoItem $todoItem): JsonResponse
    {
        $isManager = $todoItem->meeting->isManageableBy(Auth::user());
        $isAssignee = $todoItem->assigned_to === Auth::id();

        abort_if(! $isManager && ! $isAssignee, 403);

        // status/due_date can be changed by the manager or the assignee; but
        // reassigning (changing who owns the task) is a manager-only action —
        // an assignee must not hand their task off to someone else.
        if ($request->has('assigned_to')) {
            abort_unless($isManager, 403, 'Only the meeting owner can reassign a task.');
        }

        // Fields are optional so one endpoint serves the status toggle, the
        // due-date picker, and reassignment; only what's present is written.
        $validated = $request->validate([
            'status' => ['sometimes', 'in:pending,in_progress,completed'],
            'due_date' => ['sometimes', 'nullable', 'date'],
            'assigned_to' => ['sometimes', 'nullable', 'exists:users,id'],
        ]);

        abort_if(empty($validated), 422, 'Nothing to update.');

        $todoItem->update($validated);
        $todoItem->load('assignee:id,name');

        return response()->json([
            'status' => $todoItem->status,
            'due_date' => $todoItem->due_date?->toDateString(),
            'assignee' => $todoItem->assignee ? ['id' => $todoItem->assignee->id, 'name' => $todoItem->assignee->name] : null,
        ]);
    }
}
