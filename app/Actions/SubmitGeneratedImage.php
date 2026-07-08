<?php

namespace App\Actions;

use App\Enums\GeneratedImageStatus;
use App\Exceptions\ImageGenerationException;
use App\Exceptions\PreviewExpiredException;
use App\Mail\SubmittedImageCopy;
use App\Models\GeneratedImage;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

/**
 * Confirm/submit (Phase 4): the visitor consented, so promote the temp preview
 * files to permanent PRIVATE storage, create the GeneratedImage record with
 * consent_at, and queue the "copy" email. Reads the (already-sanitised) name /
 * style / bg flag from the preview sidecar — never from the client — so what is
 * stored matches what was composited.
 */
class SubmitGeneratedImage
{
	private const TEMP_DIR = 'previews';

	/**
	 * @throws PreviewExpiredException  when the temp preview/sidecar is gone
	 * @throws ImageGenerationException  when promotion/record creation fails
	 */
	public function handle(string $previewId, string $email): GeneratedImage
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

		$tempComposite = $meta['composite_path'];
		$tempSource = $meta['source_path'];

		if (! $disk->exists($tempComposite) || ! $disk->exists($tempSource)) {
			throw new PreviewExpiredException;
		}

		$uuid = (string) Str::uuid();
		$sourceExt = pathinfo($tempSource, PATHINFO_EXTENSION) ?: 'jpg';
		$finalPath = "images/{$uuid}/final.jpg";
		$sourcePath = "images/{$uuid}/source.{$sourceExt}";

		try {
			// Filesystem rename within the same private disk — no re-encode.
			$disk->move($tempComposite, $finalPath);
			$disk->move($tempSource, $sourcePath);

			$image = GeneratedImage::create([
				'uuid' => $uuid,
				'first_name' => $meta['first_name'],
				'last_name' => $meta['last_name'],
				'ja_style' => $meta['ja_style'],
				'background_removed' => (bool) ($meta['background_removed'] ?? false),
				'source_image_path' => $sourcePath,
				'final_path' => $finalPath,
				'status' => GeneratedImageStatus::Submitted,
				'user_email' => $email,
				'consent_at' => now(),
			]);
		} catch (Throwable $e) {
			// Best-effort rollback of any moved files so we don't strand them.
			$disk->delete([$finalPath, $sourcePath]);
			Log::error('Image submission failed', ['error' => $e->getMessage()]);

			throw new ImageGenerationException(previous: $e);
		}

		// Temp preview is consumed; drop the sidecar.
		$disk->delete($sidecarPath);

		// Immediate copy to the visitor (queued — see the mailable).
		Mail::to($image->user_email)->send(new SubmittedImageCopy($image));

		return $image;
	}
}
