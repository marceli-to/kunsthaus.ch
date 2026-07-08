<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\URL;

/**
 * Shapes the submit response: { uuid, status, download_url }. No `data` envelope
 * (the frontend reads these at the top level) and no internal paths / email are
 * exposed. The download URL is a temporary signed route (7 days) so the visitor
 * can save the permanent composite off the private disk.
 *
 * @mixin \App\Models\GeneratedImage
 */
class SubmittedImageResource extends JsonResource
{
	public static $wrap = null;

	/**
	 * @return array<string, mixed>
	 */
	public function toArray(Request $request): array
	{
		return [
			'uuid' => $this->uuid,
			'status' => $this->status->value,
			'download_url' => URL::temporarySignedRoute(
				'images.download',
				now()->addDays(7),
				['uuid' => $this->uuid],
			),
		];
	}
}
