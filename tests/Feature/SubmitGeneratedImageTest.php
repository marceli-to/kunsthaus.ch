<?php

namespace Tests\Feature;

use App\Actions\SubmitGeneratedImage;
use App\Enums\GeneratedImageStatus;
use App\Exceptions\ImageGenerationException;
use App\Exceptions\PreviewExpiredException;
use App\Mail\NewSubmissionNotification;
use App\Models\GeneratedImage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Tests\TestCase;

/**
 * Tests the confirm/submit action: it must read the (server-owned) name / style /
 * bg flag from the preview sidecar, promote the temp composite + source to
 * permanent private storage, create the GeneratedImage record with the consent
 * timestamp, drop the sidecar and queue the moderation heads-up mail. Also covers
 * the two failure modes: an expired preview and a mid-flight failure that must
 * roll the moved files back.
 */
class SubmitGeneratedImageTest extends TestCase
{
	use RefreshDatabase;

	protected function setUp(): void
	{
		parent::setUp();

		Storage::fake('local');
		Mail::fake();
	}

	/**
	 * Seed a temp preview (composite + source + sidecar) on the private disk, as
	 * CompositeService would have, and return the preview id.
	 */
	private function seedPreview(array $metaOverrides = []): string
	{
		$previewId = (string) Str::uuid();
		$disk = Storage::disk('local');

		$compositePath = "previews/{$previewId}.jpg";
		$sourcePath = "previews/{$previewId}.src.jpg";

		$disk->put($compositePath, 'composite-bytes');
		$disk->put($sourcePath, 'source-bytes');
		$disk->put("previews/{$previewId}.json", json_encode(array_merge([
			'first_name' => 'Marcel',
			'last_name' => 'Stadelmann',
			'ja_style' => 'oil',
			'background_removed' => true,
			'source_path' => $sourcePath,
			'composite_path' => $compositePath,
		], $metaOverrides)));

		return $previewId;
	}

	public function test_it_promotes_the_preview_and_creates_a_submitted_record(): void
	{
		$previewId = $this->seedPreview();

		$image = app(SubmitGeneratedImage::class)->handle($previewId, 'visitor@example.test');

		// Record carries the server-owned metadata + consent, status submitted.
		$this->assertSame('Marcel', $image->first_name);
		$this->assertSame('Stadelmann', $image->last_name);
		$this->assertSame('oil', $image->ja_style);
		$this->assertTrue($image->background_removed);
		$this->assertSame('visitor@example.test', $image->user_email);
		$this->assertSame(GeneratedImageStatus::Submitted, $image->status);
		$this->assertNotNull($image->consent_at);
		$this->assertNull($image->published_at);

		$this->assertDatabaseHas('generated_images', [
			'uuid' => $image->uuid,
			'status' => 'submitted',
			'user_email' => 'visitor@example.test',
		]);
	}

	public function test_it_moves_the_temp_files_to_permanent_storage_and_drops_the_sidecar(): void
	{
		$previewId = $this->seedPreview();

		$image = app(SubmitGeneratedImage::class)->handle($previewId, 'visitor@example.test');

		$disk = Storage::disk('local');

		// Promoted out of previews/ into permanent images/{uuid}/… storage, with
		// the new paths recorded on the model (controllers resolve files via these
		// stored paths, not by rebuilding from the record uuid).
		$disk->assertExists($image->final_path);
		$disk->assertExists($image->source_image_path);
		$this->assertMatchesRegularExpression('#^images/[0-9a-f-]{36}/final\.jpg$#', $image->final_path);
		$this->assertMatchesRegularExpression('#^images/[0-9a-f-]{36}/source\.jpg$#', $image->source_image_path);

		// Temp preview consumed.
		$disk->assertMissing("previews/{$previewId}.jpg");
		$disk->assertMissing("previews/{$previewId}.src.jpg");
		$disk->assertMissing("previews/{$previewId}.json");
	}

	public function test_it_queues_the_moderation_notification(): void
	{
		$previewId = $this->seedPreview();

		$image = app(SubmitGeneratedImage::class)->handle($previewId, 'visitor@example.test');

		Mail::assertQueued(NewSubmissionNotification::class, function (NewSubmissionNotification $mail) use ($image) {
			return $mail->image->is($image)
				&& $mail->hasTo(config('composite.notify_address'));
		});
	}

	public function test_it_throws_when_the_preview_sidecar_is_gone(): void
	{
		$this->expectException(PreviewExpiredException::class);

		app(SubmitGeneratedImage::class)->handle((string) Str::uuid(), 'visitor@example.test');
	}

	public function test_it_throws_when_the_referenced_files_are_missing(): void
	{
		// Sidecar exists but the composite/source it points at were swept.
		$previewId = (string) Str::uuid();
		Storage::disk('local')->put("previews/{$previewId}.json", json_encode([
			'first_name' => 'Marcel',
			'last_name' => 'Stadelmann',
			'ja_style' => 'oil',
			'background_removed' => false,
			'source_path' => "previews/{$previewId}.src.jpg",
			'composite_path' => "previews/{$previewId}.jpg",
		]));

		$this->expectException(PreviewExpiredException::class);

		app(SubmitGeneratedImage::class)->handle($previewId, 'visitor@example.test');
	}

	public function test_it_rolls_moved_files_back_when_record_creation_fails(): void
	{
		$previewId = $this->seedPreview();

		// Force the create() to blow up after the files have been moved.
		GeneratedImage::creating(function () {
			throw new RuntimeException('DB down');
		});

		try {
			app(SubmitGeneratedImage::class)->handle($previewId, 'visitor@example.test');
			$this->fail('Expected ImageGenerationException was not thrown.');
		} catch (ImageGenerationException $e) {
			$this->assertInstanceOf(RuntimeException::class, $e->getPrevious());
		}

		// No stranded permanent files and no record.
		$this->assertSame([], Storage::disk('local')->allFiles('images'));
		$this->assertDatabaseCount('generated_images', 0);
	}
}
