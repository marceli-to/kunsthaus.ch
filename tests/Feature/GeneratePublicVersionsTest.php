<?php

namespace Tests\Feature;

use App\Enums\GeneratedImageStatus;
use App\Jobs\GeneratePublicVersions;
use App\Models\GeneratedImage;
use App\Services\CompositeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\ImageManager;
use Tests\TestCase;

/**
 * The public-publishing flow: on approval a job renders the two public
 * renditions (full copy + left/right-cropped web-version) off the request, and
 * deleting a record wipes them (FADP). The job is idempotent so retries and the
 * supporter-block tag's self-heal can't collide.
 */
class GeneratePublicVersionsTest extends TestCase
{
	use RefreshDatabase;

	protected function setUp(): void
	{
		parent::setUp();

		Storage::fake('local');
		Storage::fake('public');
		Notification::fake();
	}

	/**
	 * Put a real JPEG of the given size on the faked private disk at the image's
	 * final_path, so the service has something to copy + crop.
	 */
	private function putFinal(GeneratedImage $image, int $w = 1080, int $h = 1350): void
	{
		$jpeg = (string) ImageManager::gd()->create($w, $h)->fill('ffffff')->toJpeg();
		Storage::disk('local')->put($image->final_path, $jpeg);
	}

	public function test_publishing_dispatches_the_public_versions_job(): void
	{
		Queue::fake();

		$image = GeneratedImage::factory()->create(); // submitted
		$image->update(['status' => GeneratedImageStatus::Published]);

		Queue::assertPushed(
			GeneratePublicVersions::class,
			fn (GeneratePublicVersions $job) => $job->image->is($image),
		);
	}

	public function test_job_copies_the_full_final_and_renders_a_cropped_web_version(): void
	{
		$image = GeneratedImage::factory()->create(['status' => GeneratedImageStatus::Published]);
		$this->putFinal($image, 1080, 1350);

		(new GeneratePublicVersions($image))->handle(app(CompositeService::class));

		$paths = CompositeService::publicPaths($image);
		$public = Storage::disk('public');
		$public->assertExists($paths['final']);
		$public->assertExists($paths['web']);

		// Full copy keeps 1080×1350; web is centre-cropped left+right to the
		// configured aspect (1280×2170 ≈ 0.59) at the final's native height.
		[$fw, $fh] = getimagesizefromstring($public->get($paths['final']));
		$this->assertSame([1080, 1350], [$fw, $fh]);

		[$ww, $wh] = getimagesizefromstring($public->get($paths['web']));
		$web = config('composite.web');
		$this->assertSame(1350, $wh); // native height kept
		$this->assertSame((int) round(1350 * $web['aspect_width'] / $web['aspect_height']), $ww);
	}

	public function test_ensure_is_idempotent_and_does_not_reencode_existing_files(): void
	{
		$image = GeneratedImage::factory()->create(['status' => GeneratedImageStatus::Published]);
		$this->putFinal($image);

		$service = app(CompositeService::class);
		$service->ensurePublicVersions($image);

		$paths = CompositeService::publicPaths($image);
		$public = Storage::disk('public');
		$before = $public->get($paths['web']);

		// Second run must be a no-op (both files already present).
		$service->ensurePublicVersions($image);

		$this->assertSame($before, $public->get($paths['web']));
	}

	public function test_deleting_a_published_record_wipes_the_public_renditions(): void
	{
		$image = GeneratedImage::factory()->create(['status' => GeneratedImageStatus::Published]);
		$this->putFinal($image);
		app(CompositeService::class)->ensurePublicVersions($image);

		$dir = CompositeService::publicDir($image);
		Storage::disk('public')->assertExists("{$dir}/web.jpg");

		$image->delete();

		Storage::disk('public')->assertMissing("{$dir}/web.jpg");
		Storage::disk('public')->assertMissing("{$dir}/final.jpg");
	}
}
