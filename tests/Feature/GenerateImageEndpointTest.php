<?php

namespace Tests\Feature;

use App\Services\CompositeService;
use App\Services\JaStyleRepository;
use Illuminate\Http\UploadedFile;
use Mockery;
use Tests\TestCase;

/**
 * Feature-tests POST /api/generate: request validation (from
 * GenerateImageRequest + config/composite.php), the happy path wiring, and how
 * the two domain exceptions render (422 unknown style, 500 generation failure).
 *
 * JaStyleRepository and CompositeService are swapped for mocks so the test does
 * not depend on Statamic entries or run the real GD pipeline.
 */
class GenerateImageEndpointTest extends TestCase
{
	protected function tearDown(): void
	{
		Mockery::close();

		parent::tearDown();
	}

	private function bindStyleRepository(?string $pathForKey): void
	{
		$styles = Mockery::mock(JaStyleRepository::class);
		$styles->shouldReceive('pathForKey')->andReturn($pathForKey);
		$this->app->instance(JaStyleRepository::class, $styles);
	}

	private function bindComposite(): void
	{
		$composite = Mockery::mock(CompositeService::class);
		$composite->shouldReceive('build')->andReturn([
			'11111111-1111-1111-1111-111111111111',
			'https://example.test/storage/previews/x.jpg',
		]);
		$this->app->instance(CompositeService::class, $composite);
	}

	private function validPayload(array $overrides = []): array
	{
		return array_merge([
			'portrait' => UploadedFile::fake()->image('portrait.jpg', 400, 400),
			'ja_style' => 'oil',
			'first_name' => 'Marcel',
			'last_name' => 'Stadelmann',
		], $overrides);
	}

	public function test_it_generates_a_preview_on_valid_input(): void
	{
		$this->bindStyleRepository('/abs/path/oil.png');
		$this->bindComposite();

		$response = $this->postJson('/api/generate', $this->validPayload());

		$response->assertOk()
			->assertExactJson([
				'preview_id' => '11111111-1111-1111-1111-111111111111',
				'url' => 'https://example.test/storage/previews/x.jpg',
			]);
	}

	public function test_it_sanitises_the_name_before_compositing(): void
	{
		$this->bindStyleRepository('/abs/path/oil.png');

		$composite = Mockery::mock(CompositeService::class);
		$composite->shouldReceive('build')
			->once()
			->with(Mockery::any(), Mockery::any(), 'Marcel', 'Stadelmann')
			->andReturn(['id', 'https://example.test/storage/previews/id.jpg']);
		$this->app->instance(CompositeService::class, $composite);

		$this->postJson('/api/generate', $this->validPayload([
			'first_name' => '  <b>Marcel</b> ',
			'last_name' => "Stadel\tmann",
		]))->assertOk();
	}

	public function test_it_returns_422_for_an_unknown_style(): void
	{
		$this->bindStyleRepository(null); // key does not resolve

		$this->postJson('/api/generate', $this->validPayload(['ja_style' => 'bogus']))
			->assertStatus(422)
			->assertJsonValidationErrors('ja_style');
	}

	public function test_it_requires_a_portrait(): void
	{
		$this->postJson('/api/generate', $this->validPayload(['portrait' => null]))
			->assertStatus(422)
			->assertJsonValidationErrors('portrait');
	}

	public function test_it_rejects_a_non_image_upload(): void
	{
		$this->postJson('/api/generate', $this->validPayload([
			'portrait' => UploadedFile::fake()->create('document.pdf', 100, 'application/pdf'),
		]))->assertStatus(422)->assertJsonValidationErrors('portrait');
	}

	public function test_it_rejects_an_image_below_the_minimum_dimensions(): void
	{
		$min = config('composite.upload.min_dimension');

		$this->postJson('/api/generate', $this->validPayload([
			'portrait' => UploadedFile::fake()->image('tiny.jpg', $min - 1, $min - 1),
		]))->assertStatus(422)->assertJsonValidationErrors('portrait');
	}

	public function test_it_rejects_an_image_over_the_size_limit(): void
	{
		$maxKb = config('composite.upload.max_kb');

		$this->postJson('/api/generate', $this->validPayload([
			'portrait' => UploadedFile::fake()->image('big.jpg', 400, 400)->size($maxKb + 1),
		]))->assertStatus(422)->assertJsonValidationErrors('portrait');
	}

	public function test_it_requires_first_and_last_name(): void
	{
		$this->postJson('/api/generate', $this->validPayload([
			'first_name' => '',
			'last_name' => '',
		]))->assertStatus(422)->assertJsonValidationErrors(['first_name', 'last_name']);
	}

	public function test_it_rejects_names_longer_than_the_limit(): void
	{
		$this->postJson('/api/generate', $this->validPayload([
			'first_name' => str_repeat('a', 41),
		]))->assertStatus(422)->assertJsonValidationErrors('first_name');
	}

	public function test_it_returns_500_when_the_composite_pipeline_fails(): void
	{
		$this->bindStyleRepository('/abs/path/oil.png');

		$composite = Mockery::mock(CompositeService::class);
		$composite->shouldReceive('build')->andThrow(new \RuntimeException('GD blew up'));
		$this->app->instance(CompositeService::class, $composite);

		$this->postJson('/api/generate', $this->validPayload())
			->assertStatus(500)
			->assertJson(['message' => 'Das Bild konnte nicht erstellt werden. Bitte versuche es erneut.']);
	}
}
