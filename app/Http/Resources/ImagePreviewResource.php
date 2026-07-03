<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Shapes the generated composite preview: { preview_id, url }. No `data`
 * envelope — the frontend reads these keys at the top level.
 *
 * @property array{preview_id: string, url: string} $resource
 */
class ImagePreviewResource extends JsonResource
{
	public static $wrap = null;

	/**
	 * @return array<string, mixed>
	 */
	public function toArray(Request $request): array
	{
		return [
			'preview_id' => $this->resource['preview_id'],
			'url' => $this->resource['url'],
		];
	}
}
