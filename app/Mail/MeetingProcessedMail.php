<?php

namespace App\Mail;

use App\Models\Meeting;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class MeetingProcessedMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public readonly Meeting $meeting) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Meeting ready: {$this->meeting->title}",
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.meeting-processed',
            with: ['meeting' => $this->meeting],
        );
    }
}
