<?php

use App\Models\Meeting;
use App\Models\Team;
use App\Models\Transcript;
use App\Models\User;

function transcriptFor(Meeting $meeting): Transcript
{
    return Transcript::create([
        'meeting_id' => $meeting->id,
        'segments' => [
            ['start' => 0.0, 'speaker' => 'Speaker A', 'text' => 'helo world'],
            ['start' => 5.0, 'speaker' => 'Speaker B', 'text' => 'second line'],
        ],
        'content' => "Speaker A: helo world\n\nSpeaker B: second line",
    ]);
}

it('the uploader can edit the transcript and content is re-derived', function () {
    $user = User::factory()->create();
    $meeting = Meeting::factory()->create(['user_id' => $user->id, 'status' => 'completed']);
    $transcript = transcriptFor($meeting);

    $this->actingAs($user)
        ->patch(route('transcripts.update', $transcript), [
            'segments' => [
                ['start' => 0.0, 'speaker' => 'Nigel', 'text' => 'hello world'],
                ['start' => 5.0, 'speaker' => 'Jefry', 'text' => 'second line'],
            ],
        ])
        ->assertOk();

    $fresh = $transcript->fresh();
    expect($fresh->segments[0]['speaker'])->toBe('Nigel')
        ->and($fresh->segments[0]['text'])->toBe('hello world')
        // content must be re-derived from the edited segments (PDF/Slack read it)
        ->and($fresh->content)->toBe("Nigel: hello world\n\nJefry: second line");
});

it('a team member (non-uploader) cannot edit the transcript', function () {
    $owner = User::factory()->create();
    $member = User::factory()->create();
    $team = Team::factory()->create(['owner_id' => $owner->id]);
    $team->members()->attach($owner->id, ['role' => 'owner']);
    $team->members()->attach($member->id, ['role' => 'member']);
    $meeting = Meeting::factory()->create(['user_id' => $owner->id, 'team_id' => $team->id, 'status' => 'completed']);
    $transcript = transcriptFor($meeting);

    $this->actingAs($member)
        ->patch(route('transcripts.update', $transcript), [
            'segments' => [['start' => 0.0, 'speaker' => 'X', 'text' => 'tampered']],
        ])
        ->assertForbidden();

    expect($transcript->fresh()->segments[0]['text'])->toBe('helo world');
});

it('rejects a transcript update with no segments', function () {
    $user = User::factory()->create();
    $meeting = Meeting::factory()->create(['user_id' => $user->id, 'status' => 'completed']);
    $transcript = transcriptFor($meeting);

    $this->actingAs($user)
        ->patch(route('transcripts.update', $transcript), ['segments' => 'not-an-array'])
        ->assertSessionHasErrors('segments');
});
