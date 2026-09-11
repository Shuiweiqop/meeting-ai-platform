<?php

use App\Jobs\ProcessMeetingJob;
use App\Models\Meeting;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

// ─── Auth guards ──────────────────────────────────────────────────────────────

it('redirects guests from meetings index', function () {
    $this->get(route('meetings.index'))->assertRedirect(route('login'));
});

it('redirects guests from upload page', function () {
    $this->get(route('meetings.create'))->assertRedirect(route('login'));
});

// ─── Meeting list ─────────────────────────────────────────────────────────────

it('shows only the authenticated user\'s meetings', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();

    Meeting::factory()->create(['user_id' => $user->id,  'title' => 'My Meeting']);
    Meeting::factory()->create(['user_id' => $other->id, 'title' => 'Other Meeting']);

    $this->actingAs($user)
        ->get(route('meetings.index'))
        ->assertOk()
        ->assertSee('My Meeting')
        ->assertDontSee('Other Meeting');
});

it('filters meetings by title search', function () {
    $user = User::factory()->create();
    Meeting::factory()->create(['user_id' => $user->id, 'title' => 'Q2 Planning']);
    Meeting::factory()->create(['user_id' => $user->id, 'title' => 'Design Review']);

    $this->actingAs($user)
        ->get(route('meetings.index', ['search' => 'Q2']))
        ->assertOk()
        ->assertSee('Q2 Planning')
        ->assertDontSee('Design Review');
});

it('filters meetings by status', function () {
    $user = User::factory()->create();
    Meeting::factory()->create(['user_id' => $user->id, 'title' => 'Done', 'status' => 'completed']);
    Meeting::factory()->create(['user_id' => $user->id, 'title' => 'Waiting', 'status' => 'pending']);

    $this->actingAs($user)
        ->get(route('meetings.index', ['status' => 'completed']))
        ->assertOk()
        ->assertSee('Done')
        ->assertDontSee('Waiting');
});

// ─── Upload ───────────────────────────────────────────────────────────────────

it('uploads an audio file and dispatches processing job', function () {
    Queue::fake();
    Storage::fake('local');

    $user = User::factory()->create();
    $file = UploadedFile::fake()->create('meeting.mp3', 500, 'audio/mpeg');

    $this->actingAs($user)
        ->post(route('meetings.store'), [
            'title' => 'Sprint Planning',
            'audio_file' => $file,
        ])
        ->assertRedirect(route('meetings.index'));

    $this->assertDatabaseHas('meetings', [
        'user_id' => $user->id,
        'title' => 'Sprint Planning',
        'status' => 'pending',
    ]);

    Queue::assertPushed(ProcessMeetingJob::class);
    Storage::disk('local')->assertExists('meetings/'.$file->hashName());
});

it('stores the meeting_date when provided on upload', function () {
    Queue::fake();
    Storage::fake('local');

    $user = User::factory()->create();
    $file = UploadedFile::fake()->create('meeting.mp3', 500, 'audio/mpeg');

    $this->actingAs($user)
        ->post(route('meetings.store'), [
            'title' => 'Dated Meeting',
            'meeting_date' => '2026-08-14 09:30:00',
            'audio_file' => $file,
        ])
        ->assertRedirect(route('meetings.index'));

    $meeting = Meeting::where('title', 'Dated Meeting')->firstOrFail();
    expect($meeting->meeting_date->format('Y-m-d H:i'))->toBe('2026-08-14 09:30');
});

it('rejects upload without a title', function () {
    $user = User::factory()->create();
    $file = UploadedFile::fake()->create('meeting.mp3', 100, 'audio/mpeg');

    $this->actingAs($user)
        ->post(route('meetings.store'), ['audio_file' => $file])
        ->assertSessionHasErrors('title');
});

it('rejects upload without a file', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('meetings.store'), ['title' => 'No File'])
        ->assertSessionHasErrors('audio_file');
});

// ─── Show / auth ──────────────────────────────────────────────────────────────

it('owner can view their meeting detail', function () {
    $user = User::factory()->create();
    $meeting = Meeting::factory()->create(['user_id' => $user->id, 'title' => 'My Meeting']);

    $this->actingAs($user)
        ->get(route('meetings.show', $meeting))
        ->assertOk()
        ->assertSee('My Meeting');
});

it('returns 403 when another user tries to view a meeting', function () {
    $owner = User::factory()->create();
    $other = User::factory()->create();
    $meeting = Meeting::factory()->create(['user_id' => $owner->id]);

    $this->actingAs($other)
        ->get(route('meetings.show', $meeting))
        ->assertForbidden();
});

// ─── Delete ───────────────────────────────────────────────────────────────────

it('owner can delete their meeting', function () {
    Storage::fake('local');

    $user = User::factory()->create();
    $meeting = Meeting::factory()->create([
        'user_id' => $user->id,
        'audio_path' => 'meetings/test.mp3',
    ]);

    $this->actingAs($user)
        ->delete(route('meetings.destroy', $meeting))
        ->assertRedirect(route('meetings.index'));

    $this->assertDatabaseMissing('meetings', ['id' => $meeting->id]);
});

it('returns 403 when another user tries to delete a meeting', function () {
    $owner = User::factory()->create();
    $other = User::factory()->create();
    $meeting = Meeting::factory()->create(['user_id' => $owner->id]);

    $this->actingAs($other)
        ->delete(route('meetings.destroy', $meeting))
        ->assertForbidden();

    $this->assertDatabaseHas('meetings', ['id' => $meeting->id]);
});

// ─── Audio streaming ──────────────────────────────────────────────────────────

it('streams audio to the meeting owner', function () {
    Storage::fake('local');
    Storage::disk('local')->put('meetings/test.mp3', 'fake-audio-bytes');

    $user = User::factory()->create();
    $meeting = Meeting::factory()->create(['user_id' => $user->id, 'audio_path' => 'meetings/test.mp3']);

    $this->actingAs($user)
        ->get(route('meetings.audio', $meeting))
        ->assertOk();
});

it('returns 403 when another user requests the audio', function () {
    Storage::fake('local');
    Storage::disk('local')->put('meetings/test.mp3', 'fake-audio-bytes');

    $owner = User::factory()->create();
    $other = User::factory()->create();
    $meeting = Meeting::factory()->create(['user_id' => $owner->id, 'audio_path' => 'meetings/test.mp3']);

    $this->actingAs($other)
        ->get(route('meetings.audio', $meeting))
        ->assertForbidden();
});

it('redirects guests requesting audio', function () {
    $meeting = Meeting::factory()->create();

    $this->get(route('meetings.audio', $meeting))->assertRedirect(route('login'));
});

it('returns 404 when the audio file is missing from disk', function () {
    Storage::fake('local');

    $user = User::factory()->create();
    $meeting = Meeting::factory()->create(['user_id' => $user->id, 'audio_path' => 'meetings/gone.mp3']);

    $this->actingAs($user)
        ->get(route('meetings.audio', $meeting))
        ->assertNotFound();
});

// ─── Retry ────────────────────────────────────────────────────────────────────

it('owner can retry a failed meeting', function () {
    Queue::fake();

    $user = User::factory()->create();
    $meeting = Meeting::factory()->create([
        'user_id' => $user->id,
        'status' => 'failed',
    ]);

    $this->actingAs($user)
        ->post(route('meetings.retry', $meeting))
        ->assertRedirect();

    expect($meeting->fresh()->status)->toBe('pending');
    Queue::assertPushed(ProcessMeetingJob::class);
});

it('cannot retry a meeting that is not failed', function () {
    $user = User::factory()->create();
    $meeting = Meeting::factory()->create([
        'user_id' => $user->id,
        'status' => 'completed',
    ]);

    $this->actingAs($user)
        ->post(route('meetings.retry', $meeting))
        ->assertStatus(422);
});
