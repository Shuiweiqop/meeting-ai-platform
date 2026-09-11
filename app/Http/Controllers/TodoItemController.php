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
        $isOwner = $todoItem->meeting->user_id === Auth::id();
        $isAssignee = $todoItem->assigned_to === Auth::id();

        abort_if(! $isOwner && ! $isAssignee, 403);

        // Both fields are optional so the same endpoint serves the status toggle
        // and the due-date picker; only what's present is written.
        $validated = $request->validate([
            'status' => ['sometimes', 'in:pending,in_progress,completed'],
            'due_date' => ['sometimes', 'nullable', 'date'],
        ]);

        abort_if(empty($validated), 422, 'Nothing to update.');

        $todoItem->update($validated);

        return response()->json([
            'status' => $todoItem->status,
            'due_date' => $todoItem->due_date?->toDateString(),
        ]);
    }
}
