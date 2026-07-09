<?php

namespace App\Notifications;

use App\Models\GeneratedImage;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\URL;

/**
 * Publish notification (Phase 6): emails the creator once their image is
 * approved, with the finished composite attached and a tokenised remove link
 * (the FADP deletion / unsubscribe path). Queued (database) → drained by the
 * cron `queue:work --stop-when-empty` on the shared host. Dedupe is enforced by
 * PublishGeneratedImage::notifyOnce (notified_at), not here.
 *
 * Delivered on-demand to the free-text address:
 *   Notification::route('mail', $email)->notify(new ImagePublished($image))
 */
class ImagePublished extends Notification implements ShouldQueue
{
	use Queueable;

	public function __construct(public GeneratedImage $image) {}

	/**
	 * @return array<int, string>
	 */
	public function via(object $notifiable): array
	{
		return ['mail'];
	}

	public function toMail(object $notifiable): MailMessage
	{
		$removeUrl = URL::temporarySignedRoute(
			'images.remove',
			now()->addDays(30),
			['uuid' => $this->image->uuid],
		);

		return (new MailMessage)
			->subject('Ja zum Kunsthaus – Ihr Bild wurde veröffentlicht')
			->markdown('mail.image-published', [
				'name' => $this->image->fullName(),
				'removeUrl' => $removeUrl,
			])
			->attachFromStorageDisk('local', $this->image->final_path, 'ja-zum-kunsthaus.jpg', [
				'mime' => 'image/jpeg',
			]);
	}
}
