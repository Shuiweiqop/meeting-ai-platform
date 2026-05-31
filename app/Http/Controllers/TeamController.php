<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreTeamRequest;
use App\Models\Team;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class TeamController extends Controller
{
    public function index(): Response
    {
        $teams = Team::where('owner_id', Auth::id())
            ->orWhereHas('members', fn ($q) => $q->where('users.id', Auth::id()))
            ->with('owner')
            ->withCount('members', 'meetings')
            ->latest()
            ->get();

        return Inertia::render('Team/Index', ['teams' => $teams]);
    }

    public function create(): Response
    {
        return Inertia::render('Team/Create');
    }

    public function store(StoreTeamRequest $request): RedirectResponse
    {
        $team = Team::create([
            'name'     => $request->name,
            'owner_id' => Auth::id(),
        ]);

        $team->members()->attach(Auth::id(), ['role' => 'owner']);

        return redirect()->route('teams.show', $team)->with('success', 'Team created.');
    }

    public function show(Team $team): Response
    {
        abort_if(
            $team->owner_id !== Auth::id() &&
            ! $team->members()->where('users.id', Auth::id())->exists(),
            403
        );

        $team->load([
            'owner',
            'members',
            'meetings' => fn ($q) => $q->latest()->limit(20),
        ]);

        return Inertia::render('Team/Show', ['team' => $team]);
    }

    public function destroy(Team $team): RedirectResponse
    {
        abort_if($team->owner_id !== Auth::id(), 403);

        $team->delete();

        return redirect()->route('teams.index')->with('success', 'Team deleted.');
    }

    public function addMember(Request $request, Team $team): RedirectResponse
    {
        abort_if($team->owner_id !== Auth::id(), 403);

        $request->validate(['email' => ['required', 'email', 'exists:users,email']]);

        $user = User::where('email', $request->email)->first();

        if ($team->members()->where('users.id', $user->id)->exists()) {
            return back()->withErrors(['email' => 'User is already a member.']);
        }

        $team->members()->attach($user->id, ['role' => 'member']);

        return back()->with('success', "{$user->name} added to the team.");
    }

    public function removeMember(Team $team, User $user): RedirectResponse
    {
        abort_if($team->owner_id !== Auth::id(), 403);
        abort_if($user->id === $team->owner_id, 422);

        $team->members()->detach($user->id);

        return back()->with('success', 'Member removed.');
    }
}
