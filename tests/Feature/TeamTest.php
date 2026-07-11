<?php

use App\Models\Team;
use App\Models\User;

// ─── Create ───────────────────────────────────────────────────────────────────

it('authenticated user can create a team', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('teams.store'), ['name' => 'Engineering'])
        ->assertRedirect();

    $this->assertDatabaseHas('teams', [
        'name' => 'Engineering',
        'owner_id' => $user->id,
    ]);

    // Creator is attached to pivot table as owner
    $team = Team::where('name', 'Engineering')->first();
    expect($team->members->contains($user))->toBeTrue();
});

it('rejects team creation without a name', function () {
    $this->actingAs(User::factory()->create())
        ->post(route('teams.store'), [])
        ->assertSessionHasErrors('name');
});

// ─── Show / access ────────────────────────────────────────────────────────────

it('team owner can view the team page', function () {
    $owner = User::factory()->create();
    $team = Team::factory()->create(['owner_id' => $owner->id]);
    $team->members()->attach($owner->id, ['role' => 'owner']);

    $this->actingAs($owner)
        ->get(route('teams.show', $team))
        ->assertOk();
});

it('team member can view the team page', function () {
    $owner = User::factory()->create();
    $member = User::factory()->create();
    $team = Team::factory()->create(['owner_id' => $owner->id]);
    $team->members()->attach($owner->id, ['role' => 'owner']);
    $team->members()->attach($member->id, ['role' => 'member']);

    $this->actingAs($member)
        ->get(route('teams.show', $team))
        ->assertOk();
});

it('non-member cannot view a team page', function () {
    $owner = User::factory()->create();
    $outsider = User::factory()->create();
    $team = Team::factory()->create(['owner_id' => $owner->id]);
    $team->members()->attach($owner->id, ['role' => 'owner']);

    $this->actingAs($outsider)
        ->get(route('teams.show', $team))
        ->assertForbidden();
});

// ─── Add member ───────────────────────────────────────────────────────────────

it('owner can add a member by email', function () {
    $owner = User::factory()->create();
    $invite = User::factory()->create();
    $team = Team::factory()->create(['owner_id' => $owner->id]);
    $team->members()->attach($owner->id, ['role' => 'owner']);

    $this->actingAs($owner)
        ->post(route('teams.members.add', $team), ['email' => $invite->email])
        ->assertRedirect();

    expect($team->members()->where('users.id', $invite->id)->exists())->toBeTrue();
});

it('returns error when inviting a non-existent email', function () {
    $owner = User::factory()->create();
    $team = Team::factory()->create(['owner_id' => $owner->id]);
    $team->members()->attach($owner->id, ['role' => 'owner']);

    $this->actingAs($owner)
        ->post(route('teams.members.add', $team), ['email' => 'nobody@example.com'])
        ->assertSessionHasErrors('email');
});

it('returns error when adding an existing member', function () {
    $owner = User::factory()->create();
    $member = User::factory()->create();
    $team = Team::factory()->create(['owner_id' => $owner->id]);
    $team->members()->attach($owner->id, ['role' => 'owner']);
    $team->members()->attach($member->id, ['role' => 'member']);

    $this->actingAs($owner)
        ->post(route('teams.members.add', $team), ['email' => $member->email])
        ->assertSessionHasErrors('email');
});

it('non-owner cannot add members', function () {
    $owner = User::factory()->create();
    $member = User::factory()->create();
    $invite = User::factory()->create();
    $team = Team::factory()->create(['owner_id' => $owner->id]);
    $team->members()->attach($owner->id, ['role' => 'owner']);
    $team->members()->attach($member->id, ['role' => 'member']);

    $this->actingAs($member)
        ->post(route('teams.members.add', $team), ['email' => $invite->email])
        ->assertForbidden();
});

// ─── Remove member ────────────────────────────────────────────────────────────

it('owner can remove a member', function () {
    $owner = User::factory()->create();
    $member = User::factory()->create();
    $team = Team::factory()->create(['owner_id' => $owner->id]);
    $team->members()->attach($owner->id, ['role' => 'owner']);
    $team->members()->attach($member->id, ['role' => 'member']);

    $this->actingAs($owner)
        ->delete(route('teams.members.remove', [$team, $member]))
        ->assertRedirect();

    expect($team->members()->where('users.id', $member->id)->exists())->toBeFalse();
});

it('owner cannot remove themselves', function () {
    $owner = User::factory()->create();
    $team = Team::factory()->create(['owner_id' => $owner->id]);
    $team->members()->attach($owner->id, ['role' => 'owner']);

    $this->actingAs($owner)
        ->delete(route('teams.members.remove', [$team, $owner]))
        ->assertStatus(422);
});

// ─── Delete team ──────────────────────────────────────────────────────────────

it('owner can delete a team', function () {
    $owner = User::factory()->create();
    $team = Team::factory()->create(['owner_id' => $owner->id]);
    $team->members()->attach($owner->id, ['role' => 'owner']);

    $this->actingAs($owner)
        ->delete(route('teams.destroy', $team))
        ->assertRedirect(route('teams.index'));

    $this->assertDatabaseMissing('teams', ['id' => $team->id]);
});

it('non-owner cannot delete a team', function () {
    $owner = User::factory()->create();
    $member = User::factory()->create();
    $team = Team::factory()->create(['owner_id' => $owner->id]);
    $team->members()->attach($owner->id, ['role' => 'owner']);
    $team->members()->attach($member->id, ['role' => 'member']);

    $this->actingAs($member)
        ->delete(route('teams.destroy', $team))
        ->assertForbidden();

    $this->assertDatabaseHas('teams', ['id' => $team->id]);
});
