<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreMeetingRequest;
use App\Jobs\ProcessMeetingJob;
use App\Models\Meeting;
use App\Models\Team;
use FFMpeg\FFMpeg;
use FFMpeg\Format\Audio\Mp3;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class MeetingController extends Controller
{
    public function index(): Response
    {
        $meetings = Meeting::where('user_id', Auth::id())->latest()->get();

        return Inertia::render('Meeting/Index', [
            'meetings' => $meetings,
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
        $isVideo  = str_starts_with($uploaded->getMimeType(), 'video/');

        if ($isVideo) {
            $audioPath = $this->extractAudio($uploaded->getRealPath());
        } else {
            $audioPath = $uploaded->store('meetings', 'public');
        }

        $meeting = Meeting::create([
            'user_id'     => Auth::id(),
            'team_id'     => $request->team_id ?: null,
            'title'       => $request->title,
            'description' => $request->description,
            'audio_path'  => $audioPath,
            'status'      => 'pending',
        ]);

        ProcessMeetingJob::dispatch($meeting);

        return redirect()->route('meetings.index')->with('success', 'Meeting uploaded successfully.');
    }

    private function extractAudio(string $videoPath): string
    {
        $filename  = 'meetings/' . Str::uuid() . '.mp3';
        $outputPath = Storage::disk('public')->path($filename);

        Storage::disk('public')->makeDirectory('meetings');

        $ffmpeg = FFMpeg::create();
        $video  = $ffmpeg->open($videoPath);
        $video->save(new Mp3(), $outputPath);

        return $filename;
    }

    public function show(Meeting $meeting): Response
    {
        abort_if($meeting->user_id !== Auth::id(), 403);

        return Inertia::render('Meeting/Show', [
            'meeting' => $meeting->load(['transcript', 'aiSummary', 'todoItems.assignee']),
        ]);
    }

    public function edit(Meeting $meeting): Response
    {
        abort_if($meeting->user_id !== Auth::id(), 403);

        return Inertia::render('Meeting/Edit', [
            'meeting' => $meeting,
        ]);
    }

    public function update(Request $request, Meeting $meeting): RedirectResponse
    {
        abort_if($meeting->user_id !== Auth::id(), 403);

        $validated = $request->validate([
            'title'       => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
        ]);

        $meeting->update($validated);

        return redirect()->route('meetings.show', $meeting)->with('success', 'Meeting updated.');
    }

    public function destroy(Meeting $meeting): RedirectResponse
    {
        abort_if($meeting->user_id !== Auth::id(), 403);

        if ($meeting->audio_path) {
            Storage::disk('public')->delete($meeting->audio_path);
        }

        $meeting->delete();

        return redirect()->route('meetings.index')->with('success', 'Meeting deleted.');
    }
}
