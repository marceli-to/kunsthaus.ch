<?php

namespace App\Mail;

use App\Models\GeneratedImage;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * The "email me a copy" delivery on submit (Phase 4): the finished composite,
 * sent immediately to the visitor's address. Queued (database) so the public
 * /api/submit request returns without blocking on mail transport — the download
 * link is available instantly; this copy trails by a queue cycle.
 */
class SubmittedImageCopy extends Mailable implements ShouldQueue
{
	use Queueable, SerializesModels;

	public function __construct(public GeneratedImage $image) {}

	public function envelope(): Envelope
	{
		return new Envelope(
			subject: 'Ihr «JA zum Kunsthaus» Bild',
		);
	}

	public function content(): Content
	{
		return new Content(
			markdown: 'mail.submitted-image-copy',
			with: ['name' => $this->image->fullName()],
		);
	}

	/**
	 * @return array<int, Attachment>
	 */
	public function attachments(): array
	{
		return [
			Attachment::fromStorageDisk('local', $this->image->final_path)
				->as('ja-zum-kunsthaus.jpg')
				->withMime('image/jpeg'),
		];
	}
}
