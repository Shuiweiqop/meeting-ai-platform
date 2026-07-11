<?php

namespace App\Support;

/**
 * Gemini sometimes wraps JSON in markdown code fences despite being told
 * not to. Every call site expecting structured output must decode through
 * here so the fence-strip step cannot be forgotten.
 */
final class GeminiJson
{
    public static function decode(string $raw): mixed
    {
        return json_decode(preg_replace('/^```(?:json)?\s*|\s*```$/s', '', trim($raw)), true);
    }
}
