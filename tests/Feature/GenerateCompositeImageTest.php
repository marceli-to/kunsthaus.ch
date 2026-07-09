<?php

namespace Tests\Feature;

use App\Actions\GenerateCompositeImage;
use App\Exceptions\ImageGenerationException;
use App\Exceptions\UnknownStyleException;
use App\Services\CompositeService;
use App\Services\JaStyleRepository;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Mockery;
use RuntimeException;
use Tests\TestCase;

/**
 * Tests the orchestration in GenerateCompositeImage: it resolves the chosen
 * style key server-side (never trusting a client path), delegates to
 * CompositeService, and maps the two failure modes to domain exceptions. The
 * repository and composite service are mocked so this stays fast and focused on
 * control flow.
 */
class GenerateCompositeImageTest extends TestCase
{
	protected function tearDown(): void
	{
		Mockery::close();

		parent::tearDown();
	}

	public function test_it_resolves_the_style_and_returns_the_composite_result(): void
	{
		$portrait = UploadedFile::fake()->create('portrait.jpg');

		$styles = Mockery::mock(JaStyleRepository::class);
		$styles->shouldReceive('pathForKey')
			->once()
			->with('oil')
			->andReturn('/abs/path/oil.png');

		$composite = Mockery::mock(CompositeService::class);
		$composite->shouldReceive('build')
			->once()
			->with(
				Mockery::on(fn ($arg) => is_string($arg)), // portraitPath
				Mockery::type('string'),                   // portraitExt
				'/abs/path/oil.png',                       // jaPngPath
				'Marcel',
				'Stadelmann',
				Mockery::on(fn ($meta) => is_array($meta) && $meta['ja_style'] === 'oil' && $meta['background_removed'] === true),
			)
			->andReturn([
				'preview_id' => 'preview-uuid',
				'url' => 'https://example.test/previews/preview-uuid',
				'source_path' => 'previews/preview-uuid.src.jpg',
				'composite_path' => 'previews/preview-uuid.jpg',
			]);

		$result = (new GenerateCompositeImage($styles, $composite))
			->handle($portrait, 'oil', 'Marcel', 'Stadelmann', true);

		$this->assertSame([
			'preview_id' => 'preview-uuid',
			'url' => 'https://example.test/previews/preview-uuid',
		], $result);
	}

	public function test_it_throws_unknown_style_when_the_key_does_not_resolve(): void
	{
		$portrait = UploadedFile::fake()->create('portrait.jpg');

		$styles = Mockery::mock(JaStyleRepository::class);
		$styles->shouldReceive('pathForKey')->once()->with('bogus')->andReturnNull();

		$composite = Mockery::mock(CompositeService::class);
		$composite->shouldNotReceive('build');

		$this->expectException(UnknownStyleException::class);

		(new GenerateCompositeImage($styles, $composite))
			->handle($portrait, 'bogus', 'Marcel', 'Stadelmann', false);
	}

	public function test_it_wraps_composite_failures_in_an_image_generation_exception(): void
	{
		Log::spy();

		$portrait = UploadedFile::fake()->create('portrait.jpg');

		$styles = Mockery::mock(JaStyleRepository::class);
		$styles->shouldReceive('pathForKey')->once()->andReturn('/abs/path/oil.png');

		$composite = Mockery::mock(CompositeService::class);
		$composite->shouldReceive('build')->once()->andThrow(new RuntimeException('GD blew up'));

		try {
			(new GenerateCompositeImage($styles, $composite))
				->handle($portrait, 'oil', 'Marcel', 'Stadelmann', false);
			$this->fail('Expected ImageGenerationException was not thrown.');
		} catch (ImageGenerationException $e) {
			$this->assertInstanceOf(RuntimeException::class, $e->getPrevious());
			$this->assertSame('GD blew up', $e->getPrevious()->getMessage());
		}

		Log::shouldHaveReceived('error')
			->once()
			->with('Composite generation failed', ['error' => 'GD blew up']);
	}
}
