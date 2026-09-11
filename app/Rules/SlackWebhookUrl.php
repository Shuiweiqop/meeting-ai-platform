<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Restricts a Slack webhook URL to Slack's own host over HTTPS. Without this,
 * an arbitrary URL is later POSTed to by the queue worker (notifySlack), which
 * is a server-side request forgery vector — the field could point at internal
 * addresses. The allowed host is the single source of truth for both this rule
 * and the defense-in-depth check in ProcessMeetingJob::isAllowedSlackHost().
 */
class SlackWebhookUrl implements ValidationRule
{
    public const ALLOWED_HOST = 'hooks.slack.com';

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $parts = parse_url((string) $value);

        if (($parts['scheme'] ?? null) !== 'https' || ($parts['host'] ?? null) !== self::ALLOWED_HOST) {
            $fail('The :attribute must be a Slack webhook URL (https://'.self::ALLOWED_HOST.'/…).');
        }
    }
}
