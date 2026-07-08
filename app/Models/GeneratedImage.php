<?php

namespace App\Models;

use App\Enums\GeneratedImageStatus;
use App\Observers\GeneratedImageObserver;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use StatamicRadPack\Runway\Traits\HasRunwayResource;

/**
 * A submitted JAtelier campaign image (Phase 4+). Surfaced in the Control Panel
 * as a Runway resource for moderation. `source_image_path` / `final_path` are
 * relative paths on the PRIVATE "local" disk — never public. The observer wipes
 * those files when a record is deleted (CP delete or the tokenised remove link).
 */
#[Fillable([
	'first_name',
	'last_name',
	'ja_style',
	'background_removed',
	'source_image_path',
	'final_path',
	'status',
	'user_email',
	'consent_at',
	'published_at',
	'notified_at',
])]
#[ObservedBy(GeneratedImageObserver::class)]
class GeneratedImage extends Model
{
	use HasRunwayResource;

	protected static function booted(): void
	{
		// Public identity + token subject (download / remove URLs), independent
		// of the auto-increment id.
		static::creating(function (self $image) {
			$image->uuid ??= (string) Str::uuid();
		});
	}

	/**
	 * @return array<string, string>
	 */
	protected function casts(): array
	{
		return [
			'background_removed' => 'boolean',
			'status' => GeneratedImageStatus::class,
			'consent_at' => 'datetime',
			'published_at' => 'datetime',
			'notified_at' => 'datetime',
		];
	}

	public function fullName(): string
	{
		return trim($this->first_name.' '.$this->last_name);
	}

	/**
	 * Moderator-only link to the final composite (private disk, signed CP route).
	 * Surfaced read-only in the Runway blueprint so reviewers can open the image.
	 */
	protected function finalUrl(): Attribute
	{
		return Attribute::get(fn () => $this->cpFileUrl('final'));
	}

	/**
	 * Moderator-only link to the source portrait (kept for review).
	 */
	protected function sourceUrl(): Attribute
	{
		return Attribute::get(fn () => $this->cpFileUrl('source'));
	}

	private function cpFileUrl(string $which): ?string
	{
		if (! $this->uuid) {
			return null;
		}

		return route('cp.generated-images.file', ['uuid' => $this->uuid, 'which' => $which]);
	}
}
