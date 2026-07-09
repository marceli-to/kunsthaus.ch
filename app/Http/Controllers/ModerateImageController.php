<?php

namespace App\Http\Controllers;

use App\Actions\PublishGeneratedImage;
use App\Enums\GeneratedImageStatus;
use App\Models\GeneratedImage;
use Illuminate\Http\Response;
use Statamic\Facades\User;

/**
 * Moderation endpoints behind the CP session — the targets of the "Freigeben" /
 * "Ablehnen" buttons rendered by the moderation_actions fieldtype on the detail
 * page. Both are POST + CP-authenticated + permission-checked; the JS reloads
 * the page on success so the status reflects the change.
 */
class ModerateImageController extends Controller
{
	public function publish(string $uuid, PublishGeneratedImage $publisher): Response
	{
		// PublishGeneratedImage::handle is a no-op unless the record is submitted,
		// and the observer notifies the creator exactly once (dedupe on notified_at).
		$publisher->handle($this->authorizedImage($uuid));

		return response()->noContent();
	}

	public function reject(string $uuid): Response
	{
		$image = $this->authorizedImage($uuid);

		if ($image->status === GeneratedImageStatus::Submitted) {
			$image->update(['status' => GeneratedImageStatus::Rejected]);
		}

		return response()->noContent();
	}

	private function authorizedImage(string $uuid): GeneratedImage
	{
		abort_unless(User::current()?->can('edit generated_image'), 403);

		return GeneratedImage::where('uuid', $uuid)->firstOrFail();
	}
}
