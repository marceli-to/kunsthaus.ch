<?php

namespace Tests\Feature;

use App\Enums\GeneratedImageStatus;
use App\Mail\NewSubmissionNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Feature-tests POST /api/submit: validation from SubmitImageRequest (email,
 * required FADP consent, and that the referenced preview still exists on disk),
 * the happy-path response shape (SubmittedImageResource → {uuid, status,
 * download_url}) and that a record is created. Exercises the real
 * SubmitGeneratedImage action against a faked private disk.
 */
class SubmitImageEndpointTest extends TestCase
{
	use RefreshDatabase;

	protected function setUp(): void
	{
		parent::setUp();

		Storage::fake('local');
		Mail::fake();
	}

	private function seedPreview(): string
	{
		$previewId = (string) Str::uuid();
		$disk = Storage::disk('local');

		$disk->put("previews/{$previewId}.jpg", 'composite-bytes');
		$disk->put("previews/{$previewId}.src.jpg", 'source-bytes');
		$disk->put("previews/{$previewId}.json", json_encode([
			'first_name' => 'Marcel',
			'last_name' => 'Stadelmann',
			'ja_style' => 'oil',
			'background_removed' => false,
			'source_path' => "previews/{$previewId}.src.jpg",
			'composite_path' => "previews/{$previewId}.jpg",
		]));

		return $previewId;
	}

	private function payload(string $previewId, array $overrides = []): array
	{
		return array_merge([
			'preview_id' => $previewId,
			'email' => 'visitor@example.test',
			'consent' => true,
		], $overrides);
	}

	public function test_it_submits_a_valid_request_and_returns_the_resource(): void
	{
		$previewId = $this->seedPreview();

		$response = $this->postJson('/api/submit', $this->payload($previewId));

		$response->assertCreated()
			->assertJsonPath('status', GeneratedImageStatus::Submitted->value)
			->assertJsonStructure(['uuid', 'status', 'download_url']);

		$this->assertDatabaseHas('generated_images', [
			'uuid' => $response->json('uuid'),
			'user_email' => 'visitor@example.test',
			'status' => 'submitted',
		]);

		Mail::assertQueued(NewSubmissionNotification::class);
	}

	public function test_it_requires_consent(): void
	{
		$previewId = $this->seedPreview();

		$this->postJson('/api/submit', $this->payload($previewId, ['consent' => false]))
			->assertStatus(422)
			->assertJsonValidationErrors('consent');

		$this->assertDatabaseCount('generated_images', 0);
	}

	public function test_it_requires_a_valid_email(): void
	{
		$previewId = $this->seedPreview();

		$this->postJson('/api/submit', $this->payload($previewId, ['email' => 'not-an-email']))
			->assertStatus(422)
			->assertJsonValidationErrors('email');
	}

	public function test_it_rejects_a_malformed_preview_id_without_touching_the_disk(): void
	{
		$this->postJson('/api/submit', $this->payload('not-a-uuid'))
			->assertStatus(422)
			->assertJsonValidationErrors('preview_id');
	}

	public function test_it_rejects_an_expired_preview(): void
	{
		// Well-formed uuid but nothing on disk for it.
		$this->postJson('/api/submit', $this->payload((string) Str::uuid()))
			->assertStatus(422)
			->assertJsonValidationErrors('preview_id');

		$this->assertDatabaseCount('generated_images', 0);
	}
}
