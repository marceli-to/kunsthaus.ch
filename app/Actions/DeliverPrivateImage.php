<?php

namespace App\Actions;

use App\Exceptions\PreviewExpiredException;
use App\Mail\PrivateImageDelivery;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Throwable;

/**
 * Private JAtelier delivery (/jatelier, employee page): the employee wants the
 * composite for their OWN use (e.g. social media). We email it to them and hand
 * back a signed download URL — but, unlike SubmitGeneratedImage, we create NO
 * database record, move NO files and trigger NO moderation/publishing. The temp
 * preview stays in place and is later swept by app:prune-previews.
 *
 * Reads the name/style from the preview sidecar (server-owned, sanitised) — never
 * from the client — so the mail matches what was composited.
 */
class DeliverPrivateImage
{
	private const TEMP_DIR = 'previews';

	/**
	 * @throws PreviewExpiredException  when the temp preview/sidecar is gone
	 */
	public function handle(string $previewId, string $email): string
	{
		$disk = Storage::disk('local');

		$sidecarPath = self::TEMP_DIR."/{$previewId}.json";
		if (! $disk->exists($sidecarPath)) {
			throw new PreviewExpiredException;
		}

		try {
			$meta = json_decode($disk->get($sidecarPath), true, flags: JSON_THROW_ON_ERROR);
		} catch (Throwable $e) {
			throw new PreviewExpiredException;
		}

		$compositePath = $meta['composite_path'];
		if (! $disk->exists($compositePath)) {
			throw new PreviewExpiredException;
		}

		// Email the finished composite to the employee. Queued (see the mailable)
		// so this request returns without blocking on transport. No record is
		// created — the image is private and never published.
		Mail::to($email)->send(new PrivateImageDelivery(
			compositePath: $compositePath,
			firstName: $meta['first_name'] ?? '',
			lastName: $meta['last_name'] ?? '',
		));

		// Fresh signed URL so the employee can also save the composite directly
		// from the browser. Serves the private preview file (pruned within ~24h).
		return URL::temporarySignedRoute(
			'previews.show',
			now()->addHours(2),
			['preview' => $previewId],
		);
	}
}
