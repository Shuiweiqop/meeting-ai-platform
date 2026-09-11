<?php

namespace App\Support;

use Illuminate\Support\Carbon;

/**
 * Minimal RFC 5545 iCalendar writer. Kept separate from the controller because
 * the format has non-obvious rules that break silently in Google Calendar /
 * Outlook if wrong: lines MUST be CRLF-terminated, text values MUST escape
 * comma/semicolon/backslash/newline, and every VEVENT needs a stable unique UID.
 */
final class IcsBuilder
{
    private array $lines = [];

    public function __construct(string $calendarName)
    {
        $this->lines[] = 'BEGIN:VCALENDAR';
        $this->lines[] = 'VERSION:2.0';
        $this->lines[] = 'PRODID:-//Meeting AI//Calendar//EN';
        $this->lines[] = 'CALSCALE:GREGORIAN';
        $this->lines[] = 'X-WR-CALNAME:'.$this->escape($calendarName);
    }

    /** A timed event (used for meetings, which have a clock time). */
    public function event(string $uid, Carbon $start, string $summary, int $durationMinutes = 60, ?string $description = null): self
    {
        $this->lines[] = 'BEGIN:VEVENT';
        $this->lines[] = 'UID:'.$uid;
        $this->lines[] = 'DTSTAMP:'.Carbon::now('UTC')->format('Ymd\THis\Z');
        $this->lines[] = 'DTSTART:'.$start->copy()->utc()->format('Ymd\THis\Z');
        $this->lines[] = 'DTEND:'.$start->copy()->addMinutes($durationMinutes)->utc()->format('Ymd\THis\Z');
        $this->lines[] = 'SUMMARY:'.$this->escape($summary);
        if ($description !== null && $description !== '') {
            $this->lines[] = 'DESCRIPTION:'.$this->escape($description);
        }
        $this->lines[] = 'END:VEVENT';

        return $this;
    }

    /** An all-day event (used for todo due dates, which have no clock time). */
    public function allDayEvent(string $uid, Carbon $day, string $summary): self
    {
        $this->lines[] = 'BEGIN:VEVENT';
        $this->lines[] = 'UID:'.$uid;
        $this->lines[] = 'DTSTAMP:'.Carbon::now('UTC')->format('Ymd\THis\Z');
        $this->lines[] = 'DTSTART;VALUE=DATE:'.$day->format('Ymd');
        $this->lines[] = 'SUMMARY:'.$this->escape($summary);
        $this->lines[] = 'END:VEVENT';

        return $this;
    }

    public function toString(): string
    {
        $lines = [...$this->lines, 'END:VCALENDAR'];

        // iCalendar requires CRLF line endings.
        return implode("\r\n", $lines)."\r\n";
    }

    private function escape(string $text): string
    {
        return str_replace(
            ['\\', ',', ';', "\n"],
            ['\\\\', '\\,', '\\;', '\\n'],
            $text,
        );
    }
}
