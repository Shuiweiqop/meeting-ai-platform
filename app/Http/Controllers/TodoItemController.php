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

        $todos = $query->get();

        $counts = TodoItem::where('assigned_to', Auth::id())
            ->selectRaw("
                count(*) as all,
                sum(status = 'pending') as pending,
                sum(status = 'in_progress') as in_progress,
                sum(status = 'completed') as completed
            ")
            ->first();

        return Inertia::render('Todos/Index', [
            'todos'        => $todos,
            'counts'       => $counts,
            'activeStatus' => $status ?? 'all',
        ]);
    }

    public function update(Request $request, TodoItem $todoItem): JsonResponse
    {
        abort_if($todoItem->meeting->user_id !== Auth::id(), 403);

        $request->validate(['status' => ['required', 'in:pending,in_progress,completed']]);

        $todoItem->update(['status' => $request->status]);

        return response()->json(['status' => $todoItem->status]);
    }
}
