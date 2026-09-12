<?php

namespace App\Http\Controllers;

use App\Models\Transcript;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class TranscriptController extends Controller
{
    /**
     * Save an edited transcript. Only the meeting's manager (uploader) may
     * correct it — team members have read access but must not rewrite the
     * record others rely on. `content` is re-derived so PDF/Slack stay in sync.
     */
    public function update(Request $request, Transcript $transcript): JsonResponse
    {
        abort_unless($transcript->meeting->isManageableBy(Auth::user()), 403);

        $validated = $request->validate([
            'segments' => ['required', 'array'],
            'segments.*.start' => ['required', 'numeric'],
            'segments.*.speaker' => ['nullable', 'string', 'max:255'],
            'segments.*.text' => ['nullable', 'string'],
        ]);

        // Normalise to the exact shape stored by the pipeline.
        $segments = array_map(fn ($s) => [
            'start' => (float) $s['start'],
            'speaker' => (string) ($s['speaker'] ?? ''),
            'text' => (string) ($s['text'] ?? ''),
        ], $validated['segments']);

        $transcript->update([
            'segments' => $segments,
            'content' => Transcript::contentFromSegments($segments),
        ]);

        return response()->json(['segments' => $segments]);
    }
}
