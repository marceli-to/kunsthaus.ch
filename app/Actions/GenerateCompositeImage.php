<?php

namespace App\Actions;

use App\Exceptions\ImageGenerationException;
use App\Exceptions\UnknownStyleException;
use App\Services\CompositeService;
use App\Services\JaStyleRepository;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Orchestrates the deterministic composite pipeline (NO AI, no DB record yet):
 * resolve the chosen "JA" style PNG server-side from its key (never trusting a
 * client path), then composite the portrait + style + name via CompositeService.
 *
 * The composite, the source portrait and a sidecar of the submit metadata are
 * persisted to the private disk keyed by preview id, so /api/submit can promote
 * them on consent without a re-upload.
 */
class GenerateCompositeImage
{
	public function __construct(
		private readonly JaStyleRepository $styles,
		private readonly CompositeService $composite,
	) {}

	/**
	 * @return array{preview_id: string, url: string}
	 *
	 * @throws UnknownStyleException  when the style key doesn't resolve
	 * @throws ImageGenerationException  when the composite pipeline fails
	 */
	public function handle(
		UploadedFile $portrait,
		string $styleKey,
		string $firstName,
		string $lastName,
		bool $backgroundRemoved,
	): array {
		$stylePath = $this->styles->pathForKey($styleKey);
		if ($stylePath === null) {
			throw new UnknownStyleException;
		}

		try {
			$result = $this->composite->build(
				portraitPath: $portrait->getRealPath(),
				portraitExt: strtolower($portrait->getClientOriginalExtension() ?: 'jpg'),
				jaPngPath: $stylePath,
				firstName: $firstName,
				lastName: $lastName,
				meta: [
					'first_name' => $firstName,
					'last_name' => $lastName,
					'ja_style' => $styleKey,
					'background_removed' => $backgroundRemoved,
				],
			);
		} catch (Throwable $e) {
			Log::error('Composite generation failed', ['error' => $e->getMessage()]);

			throw new ImageGenerationException(previous: $e);
		}

		return ['preview_id' => $result['preview_id'], 'url' => $result['url']];
	}
}
