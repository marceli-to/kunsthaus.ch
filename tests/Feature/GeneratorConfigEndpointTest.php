<?php

namespace Tests\Feature;

use App\Services\JaStyleRepository;
use Illuminate\Support\Collection;
use Mockery;
use Tests\TestCase;

/**
 * Feature-tests GET /api/generator: the boot payload for the generator island.
 * It must expose the published styles, the bg-removal flag and the composite
 * geometry (the single source of truth for the browser crop frame). The
 * repository is mocked so the test does not depend on Statamic entries.
 */
class GeneratorConfigEndpointTest extends TestCase
{
	protected function tearDown(): void
	{
		Mockery::close();

		parent::tearDown();
	}

	private function bindStyles(Collection $styles): void
	{
		$repo = Mockery::mock(JaStyleRepository::class);
		$repo->shouldReceive('all')->andReturn($styles);
		$this->app->instance(JaStyleRepository::class, $repo);
	}

	public function test_it_returns_styles_bg_removal_flag_and_geometry(): void
	{
		$this->bindStyles(collect([
			['key' => 'oil', 'label' => 'Öl', 'url' => 'https://example.test/oil.png'],
			['key' => 'ink', 'label' => 'Tusche', 'url' => 'https://example.test/ink.png'],
		]));

		config()->set('composite.bg_removal', true);

		$response = $this->getJson('/api/generator');

		$response->assertOk()
			->assertJsonPath('bg_removal', true)
			->assertJsonPath('styles.0.key', 'oil')
			->assertJsonPath('styles.1.key', 'ink')
			->assertJsonCount(2, 'styles');
	}

	public function test_geometry_mirrors_the_composite_config(): void
	{
		$this->bindStyles(collect([]));

		$response = $this->getJson('/api/generator');

		$portrait = config('composite.portrait');
		$ja = config('composite.ja');

		$response->assertOk()
			->assertJsonPath('geometry.portrait', [
				'x' => $portrait['x'], 'y' => $portrait['y'],
				'w' => $portrait['width'], 'h' => $portrait['height'],
			])
			->assertJsonPath('geometry.ja', [
				'x' => $ja['x'], 'y' => $ja['y'],
				'w' => $ja['width'], 'h' => $ja['height'],
			]);
	}

	public function test_bg_removal_flag_is_cast_to_a_boolean(): void
	{
		$this->bindStyles(collect([]));
		config()->set('composite.bg_removal', 0);

		$this->getJson('/api/generator')
			->assertOk()
			->assertJsonPath('bg_removal', false);
	}
}
