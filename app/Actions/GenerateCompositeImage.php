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
	public function handle(UploadedFile $portrait, string $styleKey, string $firstName, string $lastName): array
	{
		$stylePath = $this->styles->pathForKey($styleKey);
		if ($stylePath === null) {
			throw new UnknownStyleException;
		}

		try {
			[$previewId, $url] = $this->composite->build(
				portraitPath: $portrait->getRealPath(),
				jaPngPath: $stylePath,
				firstName: $firstName,
				lastName: $lastName,
			);
		} catch (Throwable $e) {
			Log::error('Composite generation failed', ['error' => $e->getMessage()]);

			throw new ImageGenerationException(previous: $e);
		}

		return ['preview_id' => $previewId, 'url' => $url];
	}
}
