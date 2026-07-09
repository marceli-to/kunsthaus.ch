<?php

namespace Tests\Feature;

use App\Actions\PublishGeneratedImage;
use App\Enums\GeneratedImageStatus;
use App\Models\GeneratedImage;
use App\Notifications\ImagePublished;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * Tests the single publish path: PublishGeneratedImage promotes a submitted image
 * to published and — via the model observer's `saved` hook — notifies the creator
 * exactly once. Both the CP action and a direct status change converge here, so
 * the dedupe guard (notified_at) is the important behaviour.
 */
class PublishGeneratedImageTest extends TestCase
{
	use RefreshDatabase;

	protected function setUp(): void
	{
		parent::setUp();

		Notification::fake();
	}

	public function test_it_publishes_a_submitted_image_and_notifies_the_creator_once(): void
	{
		$image = GeneratedImage::factory()->create(); // submitted

		$published = app(PublishGeneratedImage::class)->handle($image);

		$this->assertTrue($published);

		$image->refresh();
		$this->assertSame(GeneratedImageStatus::Published, $image->status);
		$this->assertNotNull($image->published_at);
		$this->assertNotNull($image->notified_at);

		Notification::assertCount(1);
		Notification::assertSentOnDemand(
			ImagePublished::class,
			fn ($notification, $channels, $notifiable) => $notifiable->routes['mail'] === $image->user_email,
		);
	}

	public function test_it_is_a_noop_for_a_non_submitted_image(): void
	{
		$image = GeneratedImage::factory()->rejected()->create();

		$this->assertFalse(app(PublishGeneratedImage::class)->handle($image));

		$this->assertSame(GeneratedImageStatus::Rejected, $image->fresh()->status);
		Notification::assertNothingSent();
	}

	public function test_it_neither_republishes_nor_re_notifies_on_a_second_call(): void
	{
		$image = GeneratedImage::factory()->create();

		$publisher = app(PublishGeneratedImage::class);
		$this->assertTrue($publisher->handle($image));   // publishes + notifies
		$this->assertFalse($publisher->handle($image));  // already published → no-op

		Notification::assertCount(1);
	}

	public function test_notify_once_does_nothing_for_a_still_submitted_image(): void
	{
		$image = GeneratedImage::factory()->create(); // submitted

		app(PublishGeneratedImage::class)->notifyOnce($image);

		Notification::assertNothingSent();
		$this->assertNull($image->fresh()->notified_at);
	}

	public function test_notify_once_does_not_resend_when_already_notified(): void
	{
		// published + notified_at already set → creation does not re-send (guard),
		// and neither does an explicit notifyOnce.
		$image = GeneratedImage::factory()->notified()->create();

		app(PublishGeneratedImage::class)->notifyOnce($image);

		Notification::assertNothingSent();
	}
}
