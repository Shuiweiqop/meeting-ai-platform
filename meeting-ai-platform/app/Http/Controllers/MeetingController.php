<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreMeetingRequest;
use App\Models\Meeting;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class MeetingController extends Controller
{
    public function create(): Response
    {
        return Inertia::render('Meeting/Upload');
    }

    public function store(StoreMeetingRequest $request): RedirectResponse
    {
        $path = $request->file('audio_file')->store('meetings', 'public');

        Meeting::create([
            'user_id' => auth()->id(),
            'team_id' => null,
            'title' => $request->title,
            'description' => $request->description,
            'audio_path' => $path,
            'status' => 'pending',
        ]);

        return redirect()->route('dashboard')->with('success', 'Meeting uploaded successfully.');
    }
}
