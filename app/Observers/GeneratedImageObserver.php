<?php

namespace App\Observers;

use App\Actions\PublishGeneratedImage;
use App\Enums\GeneratedImageStatus;
use App\Models\GeneratedImage;
use Illuminate\Support\Facades\Storage;

/**
 * Two lifecycle hooks:
 *
 *  - `saved`: once a record is persisted as `published`, fire the publish
 *    notification exactly once (dedupe on notified_at). This is the single
 *    convergence point for BOTH the "Freigeben & benachrichtigen" action and a
 *    moderator flipping the status select directly.
 *  - `deleting`: remove ALL stored files (acceptance criterion 10 + the FADP
 *    deletion path). Both paths live on the PRIVATE "local" disk.
 */
class GeneratedImageObserver
{
	public function __construct(private readonly PublishGeneratedImage $publisher) {}

	public function saved(GeneratedImage $image): void
	{
		if ($image->status === GeneratedImageStatus::Published) {
			$this->publisher->notifyOnce($image);
		}
	}

	public function deleting(GeneratedImage $image): void
	{
		$paths = array_filter([$image->source_image_path, $image->final_path]);

		if ($paths !== []) {
			Storage::disk('local')->delete($paths);
		}
	}
}
