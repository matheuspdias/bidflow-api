<?php

declare(strict_types=1);

namespace App\Modules\Notification\Infrastructure\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * One Mailable for every notification type rather than a class per type —
 * the two types this phase introduces (outbid, auction_won) share the same
 * shape (a subject and a one-line message), and a queued dispatch already
 * carries all of it in $data. ShouldQueue is what routes this through
 * Horizon instead of sending inline on the request/consumer thread.
 */
final class NotificationMail extends Mailable implements ShouldQueue
{
    use Queueable;
    use SerializesModels;

    /**
     * @param  array<string, mixed>  $data
     */
    public function __construct(
        private readonly string $type,
        private readonly array $data,
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->subjectFor());
    }

    public function content(): Content
    {
        return new Content(htmlString: '<p>'.e($this->messageFor()).'</p>');
    }

    private function subjectFor(): string
    {
        return match ($this->type) {
            'outbid' => 'You have been outbid',
            'auction_won' => 'You won an auction!',
            default => 'BidFlow notification',
        };
    }

    private function messageFor(): string
    {
        return match ($this->type) {
            'outbid' => "Someone placed a higher bid on \"{$this->data['auction_name']}\". The new price is {$this->data['new_amount']}.",
            'auction_won' => "Congratulations! You won \"{$this->data['auction_name']}\" for {$this->data['final_price']}.",
            default => 'You have a new notification.',
        };
    }
}
