<?php

use App\Support\GeminiJson;

it('decodes plain JSON', function () {
    expect(GeminiJson::decode('{"summary": "ok", "key_points": ["a"]}'))
        ->toBe(['summary' => 'ok', 'key_points' => ['a']]);
});

it('strips bare code fences', function () {
    expect(GeminiJson::decode("```\n[{\"start\": 0.0}]\n```"))
        ->toBe([['start' => 0.0]]);
});

it('strips ```json fences', function () {
    expect(GeminiJson::decode("```json\n{\"Speaker A\": \"Nigel\"}\n```"))
        ->toBe(['Speaker A' => 'Nigel']);
});

it('tolerates surrounding whitespace', function () {
    expect(GeminiJson::decode("  \n```json\n[1, 2]\n```  \n"))
        ->toBe([1, 2]);
});

it('returns null for malformed JSON instead of throwing', function () {
    expect(GeminiJson::decode('Sure! Here is the transcript you asked for.'))
        ->toBeNull();
});
