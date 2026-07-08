<?php

namespace App\Actions;

use App\Enums\GeneratedImageStatus;
use App\Models\GeneratedImage;
use App\Notifications\ImagePublished;
use Illuminate\Support\Facades\Notification;

/**
 * The single publish path (Phase 5/6). Called by the CP "Freigeben &
 * benachrichtigen" action AND by the model observer (if a moderator flips the
 * status select directly), so both entry points share one dedupe guard: the
 * creator is notified at most once.
 */
class PublishGeneratedImage
{
	/**
	 * Promote a submitted image to published and notify the creator once.
	 * Returns true if it published (was submitted), false otherwise.
	 */
	public function handle(GeneratedImage $image): bool
	{
		if ($image->status !== GeneratedImageStatus::Submitted) {
			return false;
		}

		$image->forceFill([
			'status' => GeneratedImageStatus::Published,
			'published_at' => $image->published_at ?? now(),
		])->save();

		// The observer's `saved` hook (below) fires the notification once the row
		// is persisted as published — so both this action and a direct status
		// change via the CP select converge on the same single dispatch.
		return true;
	}

	/**
	 * Dedupe guard: dispatch the publish notification only if it hasn't been sent,
	 * then stamp notified_at immediately (quietly, to avoid re-triggering the
	 * observer) so a re-save can't send a second mail. Called from the observer.
	 */
	public function notifyOnce(GeneratedImage $image): void
	{
		if ($image->notified_at !== null || $image->status !== GeneratedImageStatus::Published) {
			return;
		}

		Notification::route('mail', $image->user_email)->notify(new ImagePublished($image));

		$image->forceFill(['notified_at' => now()])->saveQuietly();
	}
}
