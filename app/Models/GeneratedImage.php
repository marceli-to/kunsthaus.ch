<?php

namespace App\Models;

use App\Enums\GeneratedImageStatus;
use App\Observers\GeneratedImageObserver;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
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
	use HasFactory;
	use HasRunwayResource;

	protected static function booted(): void
	{
		// Public identity + token subject (download / remove URLs), independent
		// of the auto-increment id.
		static::creating(function (self $image) {
			$image->uuid ??= (string) Str::uuid();
			$image->remove_token ??= Str::random(48);
		});
	}

	/**
	 * Opaque token behind the remove/unsubscribe link in the publish mail. A
	 * plain path segment (no `?expires=&signature=` query) so the URL doesn't
	 * look like phishing to Safe Browsing. Self-heals rows from before the
	 * column existed; saved quietly so the observer's publish pipeline doesn't
	 * re-fire over a token backfill.
	 */
	public function removeToken(): string
	{
		if (! $this->remove_token) {
			$this->remove_token = Str::random(48);
			$this->saveQuietly();
		}

		return $this->remove_token;
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
	 * CP record title (Runway `title_field`). The moderation blueprint has no
	 * text field for Runway to auto-pick, so this drives the edit-page title and
	 * the listing's title column. Falls back so it's never null (a null title
	 * crashes Statamic's <Head>).
	 *
	 * Deliberately the legacy `getTitleAttribute()` accessor, NOT the new-style
	 * `title(): Attribute`: the resource is search-indexed on every save and
	 * Runway's search resolver does `method_exists($model, 'title')` — a new-style
	 * accessor creates a method literally named `title`, so it would call
	 * `$model->title()` (an Attribute, not the value) and every save (submit /
	 * publish / reject) would throw. The `get…Attribute` name avoids the clash.
	 */
	public function getTitleAttribute(): string
	{
		return $this->fullName() ?: 'Bild #'.$this->getKey();
	}

	/**
	 * Suggested download filename for the composite, personalised with the
	 * visitor's name (e.g. "ja-zum-kunsthaus-vorname-nachname.jpg"). Str::slug
	 * folds umlauts/ß and lowercases, matching the client-side hint in
	 * ResultPreview.vue. Falls back to the generic name when empty.
	 */
	public function downloadFilename(): string
	{
		$slug = Str::slug($this->fullName());

		return $slug ? "ja-zum-kunsthaus-{$slug}.jpg" : 'ja-zum-kunsthaus.jpg';
	}

	/**
	 * Payload for the moderation_actions fieldtype: current status plus the POST
	 * endpoints (and a CSRF token) for the "Freigeben" / "Ablehnen" buttons on
	 * the CP detail page. Computed, never saved.
	 *
	 * @return array<string, mixed>
	 */
	protected function moderation(): Attribute
	{
		return Attribute::get(fn () => [
			'status' => $this->status?->value,
			'publish_url' => $this->uuid ? route('cp.generated-images.publish', ['uuid' => $this->uuid]) : null,
			'reject_url' => $this->uuid ? route('cp.generated-images.reject', ['uuid' => $this->uuid]) : null,
			'csrf' => csrf_token(),
		]);
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
