<?php

use App\Models\Meeting;
use App\Models\Team;
use App\Models\TodoItem;
use App\Models\User;
use Illuminate\Support\Carbon;

it('redirects guests from the calendar', function () {
    $this->get(route('calendar'))->assertRedirect(route('login'));
});

it('groups a meeting under its meeting_date, not its upload date', function () {
    $user = User::factory()->create();
    Meeting::factory()->create([
        'user_id' => $user->id,
        'title' => 'Board Sync',
        'meeting_date' => Carbon::parse('2026-08-14 09:30'),
        'created_at' => Carbon::parse('2026-08-20 12:00'),
    ]);

    $this->actingAs($user)
        ->get(route('calendar', ['month' => '2026-08']))
        ->assertInertia(fn ($page) => $page
            ->component('Meeting/Calendar')
            ->where('byDay.2026-08-14.0.type', 'meeting')
            ->where('byDay.2026-08-14.0.title', 'Board Sync')
            ->where('byDay.2026-08-14.0.time', '09:30')
        );
});

it('falls back to created_at when meeting_date is null', function () {
    $user = User::factory()->create();
    Meeting::factory()->create([
        'user_id' => $user->id,
        'title' => 'Ad-hoc Call',
        'meeting_date' => null,
        'created_at' => Carbon::parse('2026-08-03 15:00'),
    ]);

    $this->actingAs($user)
        ->get(route('calendar', ['month' => '2026-08']))
        ->assertInertia(fn ($page) => $page->where('byDay.2026-08-03.0.title', 'Ad-hoc Call'));
});

it('shows a dated todo assigned to the user on the calendar', function () {
    $user = User::factory()->create();
    $meeting = Meeting::factory()->create(['user_id' => $user->id]);
    TodoItem::factory()->create([
        'meeting_id' => $meeting->id,
        'assigned_to' => $user->id,
        'title' => 'Ship the deck',
        'due_date' => '2026-08-18',
        'status' => 'pending',
    ]);

    $this->actingAs($user)
        ->get(route('calendar', ['month' => '2026-08']))
        ->assertInertia(fn ($page) => $page
            ->where('byDay.2026-08-18.0.type', 'todo')
            ->where('byDay.2026-08-18.0.title', 'Ship the deck')
        );
});

it('hides todos without a due date', function () {
    $user = User::factory()->create();
    $meeting = Meeting::factory()->create(['user_id' => $user->id, 'meeting_date' => null, 'created_at' => Carbon::parse('2026-08-01 10:00')]);
    TodoItem::factory()->create(['meeting_id' => $meeting->id, 'assigned_to' => $user->id, 'due_date' => null]);

    // Only the meeting shows; the undated todo does not.
    $this->actingAs($user)
        ->get(route('calendar', ['month' => '2026-08']))
        ->assertInertia(fn ($page) => $page
            ->where('byDay.2026-08-01.0.type', 'meeting')
            ->count('byDay.2026-08-01', 1)
        );
});

it('does not show another user\'s meetings', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();
    Meeting::factory()->create(['user_id' => $other->id, 'meeting_date' => Carbon::parse('2026-08-10 10:00')]);

    $this->actingAs($user)
        ->get(route('calendar', ['month' => '2026-08']))
        ->assertInertia(fn ($page) => $page->where('byDay', []));
});

it('team filter shows the team\'s meetings and hides personal todos', function () {
    $user = User::factory()->create();
    $team = Team::factory()->create(['owner_id' => $user->id]);
    $team->members()->attach($user->id, ['role' => 'owner']);

    $teamMeeting = Meeting::factory()->create([
        'user_id' => $user->id,
        'team_id' => $team->id,
        'title' => 'Team Standup',
        'meeting_date' => Carbon::parse('2026-08-12 10:00'),
    ]);
    TodoItem::factory()->create(['meeting_id' => $teamMeeting->id, 'assigned_to' => $user->id, 'due_date' => '2026-08-12']);

    $this->actingAs($user)
        ->get(route('calendar', ['month' => '2026-08', 'team' => $team->id]))
        ->assertInertia(fn ($page) => $page
            ->where('activeTeam', $team->id)
            ->where('byDay.2026-08-12.0.type', 'meeting')
            ->count('byDay.2026-08-12', 1) // no todo in team view
        );
});

it('falls back to the current month on invalid input', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('calendar', ['month' => 'not-a-month']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('month', Carbon::now()->format('Y-m')));
});

// ─── .ics export ────────────────────────────────────────────────────────────

it('exports a valid ics with meetings and dated todos', function () {
    $user = User::factory()->create();
    $meeting = Meeting::factory()->create([
        'user_id' => $user->id,
        'title' => 'Q3 Kickoff',
        'meeting_date' => Carbon::parse('2026-08-14 09:30'),
    ]);
    TodoItem::factory()->create([
        'meeting_id' => $meeting->id,
        'assigned_to' => $user->id,
        'title' => 'Prep agenda',
        'due_date' => '2026-08-13',
    ]);

    $res = $this->actingAs($user)->get(route('calendar.export'));

    $res->assertOk();
    expect($res->headers->get('content-type'))->toContain('text/calendar');

    $body = $res->streamedContent();
    expect($body)
        ->toContain('BEGIN:VCALENDAR')
        ->toContain('END:VCALENDAR')
        ->toContain('SUMMARY:Q3 Kickoff')
        ->toContain('SUMMARY:Due: Prep agenda')
        ->toContain("meeting-{$meeting->id}@meeting-ai");
});

it('does not leak another user\'s meetings into the export', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();
    Meeting::factory()->create(['user_id' => $other->id, 'title' => 'Secret Meeting']);

    $body = $this->actingAs($user)->get(route('calendar.export'))->streamedContent();

    expect($body)->not->toContain('Secret Meeting');
});
