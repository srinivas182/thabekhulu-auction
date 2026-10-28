<?php

namespace App\Domains\Platform\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/** "Send test email" from Back office » Email provider. */
class TestEmail extends Mailable
{
    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Test email from Thabekhulu auctions');
    }

    public function content(): Content
    {
        return new Content(htmlString: '<p>This is a test email from the Thabekhulu auctions back office.</p><p>If you can read it, email sending works.</p>');
    }
}
