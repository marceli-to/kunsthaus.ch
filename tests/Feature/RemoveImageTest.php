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
 * (GET /bild-entfernen/{token}). It is the FADP deletion path: a valid token
 * deletes the record — firing the observer that wipes the private files — and
 * is idempotent so a re-click still shows the confirmation. Mails sent before
 * the token switch still resolve via the legacy signed uuid route.
 */
class RemoveImageTest extends TestCase
{
	use RefreshDatabase;

	protected function setUp(): void
	{
		parent::setUp();

		Storage::fake('local');
	}

	public function test_a_token_link_deletes_the_record_and_its_private_files(): void
	{
		$image = GeneratedImage::factory()->create();

		$disk = Storage::disk('local');
		$disk->put($image->final_path, 'final-bytes');
		$disk->put($image->source_image_path, 'source-bytes');

		$this->get(route('images.remove', ['token' => $image->removeToken()]))->assertOk();

		$this->assertDatabaseCount('generated_images', 0);
		$disk->assertMissing($image->final_path);
		$disk->assertMissing($image->source_image_path);
	}

	public function test_it_is_idempotent_for_an_already_removed_token(): void
	{
		// No record for this token — a re-click must still render the
		// confirmation, not a 404.
		$this->get(route('images.remove', ['token' => Str::random(48)]))->assertOk();

		$this->assertDatabaseCount('generated_images', 0);
	}

	public function test_a_token_is_minted_on_creation(): void
	{
		$image = GeneratedImage::factory()->create();

		$this->assertNotNull($image->remove_token);
	}

	public function test_remove_token_self_heals_for_legacy_rows(): void
	{
		$image = GeneratedImage::factory()->create();
		$image->forceFill(['remove_token' => null])->saveQuietly();

		$token = $image->removeToken();

		$this->assertNotEmpty($token);
		$this->assertDatabaseHas('generated_images', ['id' => $image->id, 'remove_token' => $token]);
	}

	public function test_a_legacy_signed_link_still_deletes_the_record(): void
	{
		$image = GeneratedImage::factory()->create();

		$url = URL::temporarySignedRoute('images.remove.legacy', now()->addDay(), ['uuid' => $image->uuid]);

		$this->get($url)->assertOk();

		$this->assertDatabaseCount('generated_images', 0);
	}

	public function test_an_unsigned_legacy_request_is_rejected_and_keeps_the_record(): void
	{
		$image = GeneratedImage::factory()->create();

		$this->get(route('images.remove.legacy', ['uuid' => $image->uuid]))
			->assertStatus(403);

		$this->assertDatabaseHas('generated_images', ['uuid' => $image->uuid]);
	}
}
