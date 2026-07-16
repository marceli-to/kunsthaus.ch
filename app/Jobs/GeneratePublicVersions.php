<?php

namespace App\Jobs;

use App\Models\GeneratedImage;
use App\Services\CompositeService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Renders a published image's public renditions (full copy + cropped
 * web-version) off the request. Dispatched from the observer on publish so the
 * moderator's "Freigeben" click returns immediately instead of waiting on GD.
 *
 * Idempotent — `ensurePublicVersions` skips when both public files already exist,
 * so a retry (or the supporter-block tag's synchronous self-heal getting there
 * first) is harmless. The model is re-resolved on run; if it was deleted in the
 * meantime the job simply has nothing to do.
 */
class GeneratePublicVersions implements ShouldQueue
{
	use Queueable;

	/** Retry a transient failure — idempotent, so retries are harmless. */
	public int $tries = 3;

	/** @var array<int, int> seconds before each retry */
	public array $backoff = [60, 300];

	public function __construct(public GeneratedImage $image) {}

	public function handle(CompositeService $composites): void
	{
		$composites->ensurePublicVersions($this->image);
	}
}
