<?php

namespace App\Mail;

use App\Models\CoverAsk;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class CoverMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public CoverAsk $ask,
        public string $kind,
        public ?string $url = null,
    ) {}

    public function envelope(): Envelope
    {
        $when = $this->ask->cover->when();
        $class = $this->ask->cover->class_label;
        $subject = match ($this->kind) {
            'filled' => $class.' on '.$when.' is filled',
            'closed' => $class.' on '.$when.' was closed',
            'passed' => $class.' on '.$when.' has moved on',
            default => 'Can you teach '.$class.' on '.$when.'?',
        };

        return new Envelope(subject: $subject);
    }

    public function content(): Content
    {
        return new Content(text: 'mail.cover');
    }
}
