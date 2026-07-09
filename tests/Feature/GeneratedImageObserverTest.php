<?php

namespace Tests\Feature;

use App\Enums\GeneratedImageStatus;
use App\Models\GeneratedImage;
use App\Notifications\ImagePublished;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Tests the model observer: the `saved` hook is the convergence point that
 * notifies the creator once a record is persisted as published (so a moderator
 * flipping the status select directly behaves like the CP action), and the
 * `deleting` hook wipes both private files (acceptance criterion 10 / the FADP
 * deletion path).
 */
class GeneratedImageObserverTest extends TestCase
{
	use RefreshDatabase;

	protected function setUp(): void
	{
		parent::setUp();

		Storage::fake('local');
		Notification::fake();
	}

	public function test_flipping_the_status_to_published_directly_notifies_once(): void
	{
		$image = GeneratedImage::factory()->create(); // submitted

		// Simulate a moderator changing the status select directly (no action).
		$image->update(['status' => GeneratedImageStatus::Published]);

		Notification::assertCount(1);
		Notification::assertSentOnDemand(ImagePublished::class);
		$this->assertNotNull($image->fresh()->notified_at);

		// A further save must not send a second mail.
		$image->update(['published_at' => now()]);
		Notification::assertCount(1);
	}

	public function test_submitted_and_rejected_saves_do_not_notify(): void
	{
		$image = GeneratedImage::factory()->create();          // submitted → no mail
		$image->update(['status' => GeneratedImageStatus::Rejected]); // rejected → silent

		Notification::assertNothingSent();
	}

	public function test_deleting_a_record_wipes_both_private_files(): void
	{
		$image = GeneratedImage::factory()->create();

		$disk = Storage::disk('local');
		$disk->put($image->final_path, 'final-bytes');
		$disk->put($image->source_image_path, 'source-bytes');

		$image->delete();

		$disk->assertMissing($image->final_path);
		$disk->assertMissing($image->source_image_path);
		$this->assertDatabaseCount('generated_images', 0);
	}
}
