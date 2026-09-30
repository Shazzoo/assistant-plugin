<?php

namespace Shazzoo\Assistant\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * Een gesprek dat de bezoeker zelf heeft meegestuurd, met zijn contactgegevens.
 *
 * Dit is het moment waarop er wél een dossier ontstaat: het gaat naar de ingestelde
 * mailbox en valt daar onder hetzelfde beleid als andere leads. De plugin zelf
 * bewaart het niet.
 */
class ConversationShared extends Mailable
{
    use Queueable;

    /**
     * @param  array{name: string, email: ?string, phone: ?string, note: ?string}  $visitor
     * @param  list<array{role: 'user'|'assistant', content: string}>  $messages  Het ongeschoonde gesprek.
     */
    public function __construct(
        public array $visitor,
        public array $messages,
        public ?string $page,
        public string $sessionNumber,
        public string $assistantName,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            replyTo: $this->visitor['email'] ? [new Address($this->visitor['email'], $this->visitor['name'])] : [],
            subject: "{$this->assistantName}: {$this->visitor['name']} wil dat we terugkomen op een gesprek",
        );
    }

    public function content(): Content
    {
        return new Content(markdown: 'assistant::mail-conversation-shared');
    }
}
