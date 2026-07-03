<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Boot payload for the JAtelier generator island: the "JA" styles, whether the
 * client-side background removal is offered, and the composite geometry (so the
 * browser crop frame matches the final image). The geometry's single source of
 * truth is config/composite.php — exposed here rather than duplicated client-side.
 *
 * @property array{styles: mixed, bg_removal: bool, portrait: array, ja: array} $resource
 */
class GeneratorConfigResource extends JsonResource
{
	public static $wrap = null;

	/**
	 * @return array<string, mixed>
	 */
	public function toArray(Request $request): array
	{
		$portrait = $this->resource['portrait'];
		$ja = $this->resource['ja'];

		return [
			'styles' => $this->resource['styles'],
			'bg_removal' => (bool) $this->resource['bg_removal'],
			'geometry' => [
				'portrait' => ['x' => $portrait['x'], 'y' => $portrait['y'], 'w' => $portrait['width'], 'h' => $portrait['height']],
				'ja' => ['x' => $ja['x'], 'y' => $ja['y'], 'w' => $ja['width'], 'h' => $ja['height']],
			],
		];
	}
}
