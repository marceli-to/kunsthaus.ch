<?php

namespace Tests\Feature;

use App\Actions\PublishGeneratedImage;
use App\Enums\GeneratedImageStatus;
use App\Http\Controllers\ModerateImageController;
use App\Models\GeneratedImage;
use App\Notifications\ImagePublished;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Mockery;
use Statamic\Facades\User;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

/**
 * Tests the CP moderation endpoints (targets of the "Freigeben" / "Ablehnen"
 * buttons). The controller is exercised directly so the assertions focus on its
 * permission guard and behaviour rather than Statamic's CP session middleware:
 * the "edit generated_image" permission is required, publish routes through
 * PublishGeneratedImage (notify-once), and reject is a silent status change.
 */
class ModerateImageControllerTest extends TestCase
{
	use RefreshDatabase;

	protected function setUp(): void
	{
		parent::setUp();

		Notification::fake();
	}

	protected function tearDown(): void
	{
		Mockery::close();

		parent::tearDown();
	}

	/**
	 * Make Statamic::user() resolve (or not) to a user with the given permission.
	 */
	private function actingAsModerator(bool $can = true): void
	{
		// The user repository's current() is typed to the Statamic User contract,
		// so the mock must implement it.
		$user = Mockery::mock(\Statamic\Contracts\Auth\User::class);
		$user->shouldReceive('can')->with('edit generated_image')->andReturn($can);
		User::shouldReceive('current')->andReturn($user);
	}

	private function controller(): ModerateImageController
	{
		return new ModerateImageController;
	}

	public function test_publish_promotes_a_submitted_image_and_notifies(): void
	{
		$this->actingAsModerator();
		$image = GeneratedImage::factory()->create();

		$response = $this->controller()->publish($image->uuid, app(PublishGeneratedImage::class));

		$this->assertSame(204, $response->getStatusCode());
		$this->assertSame(GeneratedImageStatus::Published, $image->fresh()->status);
		Notification::assertSentOnDemand(ImagePublished::class);
		Notification::assertCount(1);
	}

	public function test_reject_marks_a_submitted_image_rejected_without_notifying(): void
	{
		$this->actingAsModerator();
		$image = GeneratedImage::factory()->create();

		$response = $this->controller()->reject($image->uuid);

		$this->assertSame(204, $response->getStatusCode());
		$this->assertSame(GeneratedImageStatus::Rejected, $image->fresh()->status);
		Notification::assertNothingSent();
	}

	public function test_reject_is_a_noop_for_an_already_published_image(): void
	{
		$this->actingAsModerator();
		$image = GeneratedImage::factory()->notified()->create(); // published, no create-time mail

		$this->controller()->reject($image->uuid);

		$this->assertSame(GeneratedImageStatus::Published, $image->fresh()->status);
	}

	public function test_publish_is_forbidden_without_the_edit_permission(): void
	{
		$this->actingAsModerator(can: false);
		$image = GeneratedImage::factory()->create();

		try {
			$this->controller()->publish($image->uuid, app(PublishGeneratedImage::class));
			$this->fail('Expected a 403 HttpException.');
		} catch (HttpException $e) {
			$this->assertSame(403, $e->getStatusCode());
		}

		$this->assertSame(GeneratedImageStatus::Submitted, $image->fresh()->status);
		Notification::assertNothingSent();
	}

	public function test_reject_is_forbidden_without_the_edit_permission(): void
	{
		$this->actingAsModerator(can: false);
		$image = GeneratedImage::factory()->create();

		try {
			$this->controller()->reject($image->uuid);
			$this->fail('Expected a 403 HttpException.');
		} catch (HttpException $e) {
			$this->assertSame(403, $e->getStatusCode());
		}

		$this->assertSame(GeneratedImageStatus::Submitted, $image->fresh()->status);
	}
}
