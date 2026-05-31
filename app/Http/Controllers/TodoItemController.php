<?php

namespace App\Http\Controllers;

use App\Models\TodoItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class TodoItemController extends Controller
{
    public function update(Request $request, TodoItem $todoItem): JsonResponse
    {
        abort_if($todoItem->meeting->user_id !== Auth::id(), 403);

        $request->validate(['status' => ['required', 'in:pending,in_progress,completed']]);

        $todoItem->update(['status' => $request->status]);

        return response()->json(['status' => $todoItem->status]);
    }
}
