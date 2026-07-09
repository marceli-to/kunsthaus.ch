<?php

namespace Tests\Feature;

use App\Models\GeneratedImage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Tests the tokenised remove / unsubscribe link from the publish email
 * (GET /images/{uuid}/remove). It is the FADP deletion path: a valid signed
 * request deletes the record — firing the observer that wipes the private files —
 * and is idempotent so a re-click still shows the confirmation. An unsigned
 * request is rejected.
 */
class RemoveImageTest extends TestCase
{
	use RefreshDatabase;

	protected function setUp(): void
	{
		parent::setUp();

		Storage::fake('local');
	}

	private function signedRemoveUrl(string $uuid): string
	{
		return URL::temporarySignedRoute('images.remove', now()->addDay(), ['uuid' => $uuid]);
	}

	public function test_a_signed_link_deletes_the_record_and_its_private_files(): void
	{
		$image = GeneratedImage::factory()->create();

		$disk = Storage::disk('local');
		$disk->put($image->final_path, 'final-bytes');
		$disk->put($image->source_image_path, 'source-bytes');

		$this->get($this->signedRemoveUrl($image->uuid))->assertOk();

		$this->assertDatabaseCount('generated_images', 0);
		$disk->assertMissing($image->final_path);
		$disk->assertMissing($image->source_image_path);
	}

	public function test_an_unsigned_request_is_rejected_and_keeps_the_record(): void
	{
		$image = GeneratedImage::factory()->create();

		$this->get(route('images.remove', ['uuid' => $image->uuid]))
			->assertStatus(403);

		$this->assertDatabaseHas('generated_images', ['uuid' => $image->uuid]);
	}

	public function test_it_is_idempotent_for_an_already_removed_uuid(): void
	{
		// No record for this uuid — a re-click must still render the confirmation,
		// not a 404.
		$this->get($this->signedRemoveUrl((string) Str::uuid()))->assertOk();
	}
}
