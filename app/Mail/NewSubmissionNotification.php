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
 * Moderation heads-up (Phase 4): notifies the team address
 * (config('composite.notify_address')) that a visitor has submitted a portrait
 * awaiting review. Carries the finished composite as an attachment so the
 * moderator gets a preview without opening the Control Panel. Queued (database)
 * so the public /api/submit request returns without blocking on mail transport.
 */
class NewSubmissionNotification extends Mailable implements ShouldQueue
{
	use Queueable, SerializesModels;

	public function __construct(public GeneratedImage $image) {}

	public function envelope(): Envelope
	{
		return new Envelope(
			subject: 'Bild freigeben «JA zum Kunsthaus»',
		);
	}

	public function content(): Content
	{
		return new Content(
			markdown: 'mail.new-submission-notification',
			with: [
				'name' => $this->image->fullName(),
				'email' => $this->image->user_email,
				'style' => $this->image->ja_style,
			],
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
