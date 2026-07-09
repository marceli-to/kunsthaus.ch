<?php

namespace App\Tags;

use App\Enums\GeneratedImageStatus;
use App\Models\GeneratedImage;
use App\Services\CompositeService;
use Illuminate\Support\Facades\Storage;
use Statamic\Tags\Tags;

/**
 * `{{ jatelier_images }}` — the published JAtelier composites, for the public
 * supporter block. Yields only moderator-approved images (newest first), each
 * with its public URLs: `url` is the cropped web-version, `full_url` the full
 * 1080×1350 composite. Both are static files on the public disk (served from
 * /storage). Missing renditions are self-healed on first render so images
 * published before the public-disk flow existed still appear.
 */
class JatelierImages extends Tags
{
	public function __construct(private readonly CompositeService $composites)
	{
		//
	}

	/**
	 * @return array<int, array<string, string>>
	 */
	public function index(): array
	{
		$public = Storage::disk('public');

		return GeneratedImage::where('status', GeneratedImageStatus::Published)
			->orderByDesc('published_at')
			->get()
			->map(function (GeneratedImage $image) use ($public) {
				$this->composites->ensurePublicVersions($image);
				$paths = CompositeService::publicPaths($image);

				if (! $public->exists($paths['web'])) {
					return null;
				}

				return [
					'url' => $public->url($paths['web']),
					'full_url' => $public->url($paths['final']),
					'first_name' => $image->first_name,
					'last_name' => $image->last_name,
					'full_name' => $image->fullName(),
				];
			})
			->filter()
			->values()
			->all();
	}
}
