<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Private JAtelier delivery (/jatelier, employee page): emails the finished
 * composite to the employee for their OWN use (e.g. social media). Carries the
 * image as an attachment, read from the temp preview on the PRIVATE "local" disk
 * — there is no GeneratedImage record, so we reference the file by path.
 *
 * Queued (database) so the /api/deliver request returns without blocking on mail
 * transport. The preview file survives until app:prune-previews sweeps it (~24h),
 * which comfortably outlasts the queue drain.
 */
class PrivateImageDelivery extends Mailable implements ShouldQueue
{
	use Queueable, SerializesModels;

	/** Retry a transient transport failure instead of failing permanently. */
	public int $tries = 3;

	/** @var array<int, int> seconds before each retry */
	public array $backoff = [60, 300];

	/**
	 * @param  string  $compositePath  relative path on the "local" disk
	 */
	public function __construct(
		public string $compositePath,
		public string $firstName,
		public string $lastName,
	) {}

	public function envelope(): Envelope
	{
		return new Envelope(
			subject: 'Ihr «JA zum Kunsthaus» Bild',
		);
	}

	public function content(): Content
	{
		return new Content(
			markdown: 'mail.private-image-delivery',
			with: [
				'name' => trim($this->firstName.' '.$this->lastName),
			],
		);
	}

	/**
	 * @return array<int, Attachment>
	 */
	public function attachments(): array
	{
		return [
			Attachment::fromStorageDisk('local', $this->compositePath)
				->as('ja-zum-kunsthaus.jpg')
				->withMime('image/jpeg'),
		];
	}
}
