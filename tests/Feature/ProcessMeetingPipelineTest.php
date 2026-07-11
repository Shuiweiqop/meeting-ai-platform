<?php

use App\Enums\ProcessingStage;
use App\Jobs\ProcessMeetingJob;
use App\Models\Meeting;

// parseSegments is private by design (only transcribe() may call it); reflection
// lets the suite pin its fallback behaviour without widening the API.
function invokeParseSegments(Meeting $meeting, string $raw): array
{
    $job = new ProcessMeetingJob($meeting);

    return (new ReflectionMethod($job, 'parseSegments'))->invoke($job, $raw);
}

it('wraps an unparseable transcription reply as a single segment', function () {
    $meeting = Meeting::factory()->create();

    $segments = invokeParseSegments($meeting, 'Sure! Here is the transcript.');

    expect($segments)->toHaveCount(1)
        ->and($segments[0])->toBe(['start' => 0.0, 'speaker' => '', 'text' => 'Sure! Here is the transcript.']);
});

it('parses fenced JSON segments and normalises missing keys', function () {
    $meeting = Meeting::factory()->create();

    $raw = "```json\n[{\"start\": \"1.5\", \"text\": \"hello\"}, {\"speaker\": \"Nigel\", \"text\": \"hi\"}]\n```";

    expect(invokeParseSegments($meeting, $raw))->toBe([
        ['start' => 1.5, 'speaker' => '', 'text' => 'hello'],
        ['start' => 0.0, 'speaker' => 'Nigel', 'text' => 'hi'],
    ]);
});

it('clears processing_stage when transitioning to completed', function () {
    $meeting = Meeting::factory()->create(['status' => 'processing', 'processing_stage' => 'summarizing']);

    $meeting->transitionTo('completed');

    expect($meeting->fresh())
        ->status->toBe('completed')
        ->processing_stage->toBeNull();
});

it('refuses a processing_stage on a non-processing status', function () {
    Meeting::factory()->create()->transitionTo('completed', ProcessingStage::Transcribing);
})->throws(LogicException::class);

it('refuses an unknown status', function () {
    Meeting::factory()->create()->transitionTo('done');
})->throws(LogicException::class);
