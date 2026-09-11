<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreMeetingRequest;
use App\Jobs\ProcessMeetingJob;
use App\Models\Meeting;
use App\Models\Team;
use Barryvdh\DomPDF\Facade\Pdf;
use FFMpeg\FFMpeg;
use FFMpeg\Format\Audio\Mp3;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class MeetingController extends Controller
{
    public function index(Request $request): Response
    {
        $search = $request->get('search');
        $status = $request->get('status');

        $meetings = Meeting::where('user_id', Auth::id())
            ->when($search, fn ($q) => $q->where('title', 'like', "%{$search}%"))
            ->when($status, fn ($q) => $q->where('status', $status))
            ->latest()
            ->paginate(12)
            ->withQueryString();

        return Inertia::render('Meeting/Index', [
            'meetings' => $meetings,
            'filters' => ['search' => $search ?? '', 'status' => $status ?? ''],
        ]);
    }

    public function create(): Response
    {
        $teams = Team::where('owner_id', Auth::id())
            ->orWhereHas('members', fn ($q) => $q->where('users.id', Auth::id()))
            ->get(['id', 'name']);

        return Inertia::render('Meeting/Upload', ['teams' => $teams]);
    }

    public function store(StoreMeetingRequest $request): RedirectResponse
    {
        $uploaded = $request->file('audio_file');
        $isVideo = str_starts_with($uploaded->getMimeType(), 'video/');

        if ($isVideo) {
            $audioPath = $this->extractAudio($uploaded->getRealPath());
        } else {
            $audioPath = $uploaded->store('meetings', 'local');
        }

        $meeting = Meeting::create([
            'user_id' => Auth::id(),
            'team_id' => $request->team_id ?: null,
            'title' => $request->title,
            'description' => $request->description,
            'audio_path' => $audioPath,
            'status' => 'pending',
            'meeting_date' => $request->meeting_date ?: null,
        ]);

        ProcessMeetingJob::dispatch($meeting);

        return redirect()->route('meetings.index')->with('success', 'Meeting uploaded successfully.');
    }

    private function extractAudio(string $videoPath): string
    {
        $filename = 'meetings/'.Str::uuid().'.mp3';
        $outputPath = Storage::disk('local')->path($filename);

        Storage::disk('local')->makeDirectory('meetings');

        $ffmpeg = FFMpeg::create();
        $video = $ffmpeg->open($videoPath);
        $video->save(new Mp3, $outputPath);

        return $filename;
    }

    public function show(Meeting $meeting): Response
    {
        abort_unless($meeting->isAccessibleBy(Auth::user()), 403);

        return Inertia::render('Meeting/Show', [
            'meeting' => $meeting->load(['transcript', 'aiSummary', 'todoItems.assignee']),
        ]);
    }

    // Audio lives on the private local disk (never the public one — recordings
    // must not be reachable without this ownership check). BinaryFileResponse
    // handles HTTP Range requests, which <audio> seeking depends on.
    public function audio(Meeting $meeting): BinaryFileResponse
    {
        abort_unless($meeting->isAccessibleBy(Auth::user()), 403);
        abort_unless($meeting->audio_path && Storage::disk('local')->exists($meeting->audio_path), 404);

        return response()->file(Storage::disk('local')->path($meeting->audio_path));
    }

    public function retry(Meeting $meeting): RedirectResponse
    {
        abort_unless($meeting->isManageableBy(Auth::user()), 403);
        abort_if($meeting->status !== 'failed', 422);

        // Clean up previous partial results
        $meeting->transcript?->delete();
        $meeting->aiSummary?->delete();
        $meeting->todoItems()->delete();
        $meeting->transitionTo('pending');

        ProcessMeetingJob::dispatch($meeting);

        return back()->with('success', 'Meeting queued for reprocessing.');
    }

    public function exportPdf(Meeting $meeting): HttpResponse
    {
        abort_unless($meeting->isAccessibleBy(Auth::user()), 403);

        $meeting->load(['transcript', 'aiSummary', 'todoItems.assignee']);

        $pdf = Pdf::loadView('pdf.meeting', ['meeting' => $meeting])
            ->setPaper('a4', 'portrait');

        $filename = Str::slug($meeting->title).'-summary.pdf';

        return $pdf->download($filename);
    }

    public function edit(Meeting $meeting): Response
    {
        abort_unless($meeting->isManageableBy(Auth::user()), 403);

        return Inertia::render('Meeting/Edit', [
            'meeting' => $meeting,
        ]);
    }

    public function update(Request $request, Meeting $meeting): RedirectResponse
    {
        abort_unless($meeting->isManageableBy(Auth::user()), 403);

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
        ]);

        $meeting->update($validated);

        return redirect()->route('meetings.show', $meeting)->with('success', 'Meeting updated.');
    }

    public function destroy(Meeting $meeting): RedirectResponse
    {
        abort_unless($meeting->isManageableBy(Auth::user()), 403);

        if ($meeting->audio_path) {
            Storage::disk('local')->delete($meeting->audio_path);
        }

        $meeting->delete();

        return redirect()->route('meetings.index')->with('success', 'Meeting deleted.');
    }
}
