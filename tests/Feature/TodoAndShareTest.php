<?php

use App\Models\Meeting;
use App\Models\TodoItem;
use App\Models\User;

// ─── Todo toggle ──────────────────────────────────────────────────────────────

it('assignee can toggle a todo to completed', function () {
    $owner = User::factory()->create();
    $assignee = User::factory()->create();
    $meeting = Meeting::factory()->create(['user_id' => $owner->id]);
    $todo = TodoItem::factory()->create([
        'meeting_id' => $meeting->id,
        'assigned_to' => $assignee->id,
        'status' => 'pending',
    ]);

    $this->actingAs($assignee)
        ->patch(route('todo-items.update', $todo), ['status' => 'completed'])
        ->assertOk()
        ->assertJson(['status' => 'completed']);

    expect($todo->fresh()->status)->toBe('completed');
});

it('assignee can toggle a todo back to pending', function () {
    $owner = User::factory()->create();
    $assignee = User::factory()->create();
    $meeting = Meeting::factory()->create(['user_id' => $owner->id]);
    $todo = TodoItem::factory()->create([
        'meeting_id' => $meeting->id,
        'assigned_to' => $assignee->id,
        'status' => 'completed',
    ]);

    $this->actingAs($assignee)
        ->patch(route('todo-items.update', $todo), ['status' => 'pending'])
        ->assertOk();

    expect($todo->fresh()->status)->toBe('pending');
});

it('rejects invalid status values', function () {
    $owner = User::factory()->create();
    $assignee = User::factory()->create();
    $meeting = Meeting::factory()->create(['user_id' => $owner->id]);
    $todo = TodoItem::factory()->create([
        'meeting_id' => $meeting->id,
        'assigned_to' => $assignee->id,
    ]);

    $this->actingAs($assignee)
        ->patch(route('todo-items.update', $todo), ['status' => 'invalid_status'])
        ->assertSessionHasErrors('status');
});

it('non-owner of meeting cannot toggle a todo', function () {
    $owner = User::factory()->create();
    $outsider = User::factory()->create();
    $meeting = Meeting::factory()->create(['user_id' => $owner->id]);
    $todo = TodoItem::factory()->create(['meeting_id' => $meeting->id]);

    $this->actingAs($outsider)
        ->patch(route('todo-items.update', $todo), ['status' => 'completed'])
        ->assertForbidden();
});

it('assignee can set and clear a todo due date', function () {
    $owner = User::factory()->create();
    $assignee = User::factory()->create();
    $meeting = Meeting::factory()->create(['user_id' => $owner->id]);
    $todo = TodoItem::factory()->create([
        'meeting_id' => $meeting->id,
        'assigned_to' => $assignee->id,
    ]);

    $this->actingAs($assignee)
        ->patch(route('todo-items.update', $todo), ['due_date' => '2026-08-20'])
        ->assertOk();
    expect($todo->fresh()->due_date->toDateString())->toBe('2026-08-20');

    $this->actingAs($assignee)
        ->patch(route('todo-items.update', $todo), ['due_date' => null])
        ->assertOk();
    expect($todo->fresh()->due_date)->toBeNull();
});

it('rejects an empty todo update', function () {
    $owner = User::factory()->create();
    $meeting = Meeting::factory()->create(['user_id' => $owner->id]);
    $todo = TodoItem::factory()->create(['meeting_id' => $meeting->id, 'assigned_to' => $owner->id]);

    $this->actingAs($owner)
        ->patch(route('todo-items.update', $todo), [])
        ->assertStatus(422);
});

// ─── Share link ───────────────────────────────────────────────────────────────

it('owner can generate a share link for a completed meeting', function () {
    $user = User::factory()->create();
    $meeting = Meeting::factory()->create([
        'user_id' => $user->id,
        'status' => 'completed',
    ]);

    $this->actingAs($user)
        ->post(route('meetings.share.generate', $meeting))
        ->assertRedirect();

    expect($meeting->fresh()->share_token)->not->toBeNull();
});

it('cannot generate share link for a non-completed meeting', function () {
    $user = User::factory()->create();
    $meeting = Meeting::factory()->create([
        'user_id' => $user->id,
        'status' => 'processing',
    ]);

    $this->actingAs($user)
        ->post(route('meetings.share.generate', $meeting))
        ->assertStatus(422);
});

it('public share page returns 200 for valid token', function () {
    $user = User::factory()->create();
    $meeting = Meeting::factory()->create([
        'user_id' => $user->id,
        'status' => 'completed',
        'share_token' => 'abc123testtoken',
    ]);

    $this->get(route('share.show', 'abc123testtoken'))
        ->assertOk()
        ->assertSee($meeting->title);
});

it('public share page returns 404 for invalid token', function () {
    $this->get(route('share.show', 'nonexistent-token'))
        ->assertNotFound();
});

it('share page is not accessible for non-completed meetings', function () {
    $user = User::factory()->create();
    Meeting::factory()->create([
        'user_id' => $user->id,
        'status' => 'processing',
        'share_token' => 'processingtoken',
    ]);

    $this->get(route('share.show', 'processingtoken'))
        ->assertNotFound();
});

it('owner can revoke a share link', function () {
    $user = User::factory()->create();
    $meeting = Meeting::factory()->create([
        'user_id' => $user->id,
        'status' => 'completed',
        'share_token' => 'sometoken',
    ]);

    $this->actingAs($user)
        ->delete(route('meetings.share.revoke', $meeting))
        ->assertRedirect();

    expect($meeting->fresh()->share_token)->toBeNull();
});
