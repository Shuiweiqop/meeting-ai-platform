<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessMeetingJob;
use App\Models\Meeting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ChunkUploadController extends Controller
{
    private const ALLOWED_EXT = ['mp3', 'wav', 'm4a', 'ogg', 'mp4', 'mov', 'webm', 'mkv', 'avi'];

    public function chunk(Request $request): JsonResponse
    {
        $request->validate([
            'upload_id' => ['required', 'string', 'uuid'],
            'chunk_index' => ['required', 'integer', 'min:0', 'max:9999'],
            'total_chunks' => ['required', 'integer', 'min:1', 'max:500'],
            'chunk' => ['required', 'file', 'max:6144'], // 6 MB per chunk
        ]);

        $request->file('chunk')->storeAs(
            'chunks/'.$request->upload_id,
            'chunk_'.$request->chunk_index,
            'local'
        );

        return response()->json(['ok' => true]);
    }

    public function merge(Request $request): JsonResponse
    {
        $request->validate([
            'upload_id' => ['required', 'string', 'uuid'],
            'filename' => ['required', 'string', 'max:255'],
            'total_chunks' => ['required', 'integer', 'min:1', 'max:500'],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'team_id' => ['nullable', 'exists:teams,id'],
            'meeting_date' => ['nullable', 'date'],
        ]);

        $uploadId = $request->upload_id;
        $totalChunks = (int) $request->total_chunks;
        $ext = strtolower(pathinfo($request->filename, PATHINFO_EXTENSION));

        abort_if(! in_array($ext, self::ALLOWED_EXT, true), 422, 'Unsupported file type.');

        $finalRelPath = 'meetings/'.Str::uuid().'.'.$ext;
        $finalAbsPath = Storage::disk('local')->path($finalRelPath);

        Storage::disk('local')->makeDirectory('meetings');

        $out = fopen($finalAbsPath, 'wb');

        try {
            for ($i = 0; $i < $totalChunks; $i++) {
                $chunkPath = Storage::disk('local')->path("chunks/{$uploadId}/chunk_{$i}");
                abort_if(! file_exists($chunkPath), 422, "Missing chunk {$i}.");
                $in = fopen($chunkPath, 'rb');
                stream_copy_to_stream($in, $out);
                fclose($in);
            }
        } catch (\Throwable $e) {
            fclose($out);
            @unlink($finalAbsPath);
            throw $e;
        }

        fclose($out);
        Storage::disk('local')->deleteDirectory("chunks/{$uploadId}");

        $meeting = Meeting::create([
            'user_id' => Auth::id(),
            'team_id' => $request->team_id ?: null,
            'title' => $request->title,
            'description' => $request->description,
            'audio_path' => $finalRelPath,
            'status' => 'pending',
            'meeting_date' => $request->meeting_date ?: null,
        ]);

        ProcessMeetingJob::dispatch($meeting);

        return response()->json(['meeting_id' => $meeting->id]);
    }
}
