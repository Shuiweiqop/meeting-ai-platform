<?php

namespace App\Mail;

use App\Models\TodoItem;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class TodoAssignedMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public readonly TodoItem $todo) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "New action item: {$this->todo->title}",
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.todo-assigned',
            with: ['todo' => $this->todo->load('meeting')],
        );
    }
}
