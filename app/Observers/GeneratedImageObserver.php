<?php

namespace App\Observers;

use App\Actions\PublishGeneratedImage;
use App\Enums\GeneratedImageStatus;
use App\Jobs\GeneratePublicVersions;
use App\Models\GeneratedImage;
use App\Services\CompositeService;
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
			// Render the public renditions (full + cropped web-version) off the
			// request so the moderator's "Freigeben" click returns immediately;
			// the supporter-block tag self-heals if a visitor arrives first.
			GeneratePublicVersions::dispatch($image);
			$this->publisher->notifyOnce($image);
		}
	}

	public function deleting(GeneratedImage $image): void
	{
		// Private originals (source portrait + final composite).
		$paths = array_filter([$image->source_image_path, $image->final_path]);

		if ($paths !== []) {
			Storage::disk('local')->delete($paths);
		}

		// Public renditions, if this image was ever published (FADP deletion path).
		Storage::disk('public')->deleteDirectory(CompositeService::publicDir($image));
	}
}
