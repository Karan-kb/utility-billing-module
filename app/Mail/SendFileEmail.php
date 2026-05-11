<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class SendFileEmail extends Mailable
{
    use Queueable, SerializesModels;

    public $subjectText;
    public $bodyText;           // Plain text or data for view
    public $files = [];         // Array of file paths (or UploadedFile objects)

    /**
     * Create a new message instance.
     */
    public function __construct($subject, $body, array $files = [])
    {
        $this->subjectText = $subject;
        $this->bodyText    = $body;
        $this->files       = $files;
    }

    /**
     * Get the message envelope (subject, from, etc.).
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->subjectText,
        );
    }

    /**
     * Get the message content (you can use plain text or Blade view).
     */
   
public function content(): Content
{
    return new Content(
        view: 'emails.simple-text',   
    );
}
    /**
     * Get the attachments for the message.
     */
    public function attachments(): array
    {
        $attachments = [];

        foreach ($this->files as $file) {
            if (is_string($file) && file_exists($file)) {
                // File from path (most common)
                $attachments[] = Attachment::fromPath($file);
            } 
            elseif ($file instanceof \Illuminate\Http\UploadedFile) {
                // Uploaded file from form request
                $attachments[] = Attachment::fromPath($file->getRealPath())
                    ->as($file->getClientOriginalName())
                    ->withMime($file->getMimeType());
            }
        }

        return $attachments;
    }
}