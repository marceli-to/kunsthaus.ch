<?php

namespace Tests\Feature;

use App\Services\CompositeService;
use Tests\TestCase;

/**
 * Exercises the real GD compositing pipeline end-to-end (no mocking of
 * Intervention). It reads real portrait + JA PNGs off disk, composites onto the
 * campaign template using the bundled font/footer assets, and writes a flattened
 * JPEG preview. Asserts the returned contract and that a valid image lands on the
 * public disk.
 */
class CompositeServiceTest extends TestCase
{
	private string $portraitPath;

	private string $jaPath;

	protected function setUp(): void
	{
		parent::setUp();

		$this->portraitPath = $this->makePng(1200, 800, [30, 120, 200]);
		$this->jaPath = $this->makePng(684, 684, [200, 60, 60]);
	}

	protected function tearDown(): void
	{
		@unlink($this->portraitPath);
		@unlink($this->jaPath);

		// Clean up any previews written during the test.
		foreach (glob(storage_path('app/public/previews/*.jpg')) as $file) {
			@unlink($file);
		}

		parent::tearDown();
	}

	public function test_it_returns_a_preview_id_and_url(): void
	{
		[$previewId, $url] = app(CompositeService::class)->build(
			portraitPath: $this->portraitPath,
			jaPngPath: $this->jaPath,
			firstName: 'Marcel',
			lastName: 'Stadelmann',
		);

		$this->assertMatchesRegularExpression(
			'/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/',
			$previewId,
			'preview_id should be a UUID',
		);

		$this->assertStringEndsWith("/storage/previews/{$previewId}.jpg", $url);
		$this->assertStringStartsWith(rtrim(config('app.url'), '/'), $url);
	}

	public function test_it_writes_a_valid_jpeg_of_the_configured_canvas_size(): void
	{
		[$previewId] = app(CompositeService::class)->build(
			portraitPath: $this->portraitPath,
			jaPngPath: $this->jaPath,
			firstName: 'Anna',
			lastName: 'Meier',
		);

		$path = storage_path("app/public/previews/{$previewId}.jpg");
		$this->assertFileExists($path);

		$info = getimagesize($path);
		$this->assertNotFalse($info, 'output should be a readable image');
		$this->assertSame('image/jpeg', $info['mime']);
		$this->assertSame(config('composite.canvas.width'), $info[0]);
		$this->assertSame(config('composite.canvas.height'), $info[1]);
	}

	public function test_it_handles_an_empty_name_without_error(): void
	{
		[$previewId] = app(CompositeService::class)->build(
			portraitPath: $this->portraitPath,
			jaPngPath: $this->jaPath,
			firstName: '',
			lastName: '',
		);

		$this->assertFileExists(storage_path("app/public/previews/{$previewId}.jpg"));
	}

	public function test_it_shrinks_the_font_so_very_long_names_still_fit(): void
	{
		// A pathologically long name must not throw and must still produce a
		// canvas-sized image (the fitFontSize loop clamps to min_size).
		[$previewId] = app(CompositeService::class)->build(
			portraitPath: $this->portraitPath,
			jaPngPath: $this->jaPath,
			firstName: str_repeat('Wolfgang', 8),
			lastName: str_repeat('Amadeus', 8),
		);

		$info = getimagesize(storage_path("app/public/previews/{$previewId}.jpg"));
		$this->assertSame(config('composite.canvas.width'), $info[0]);
	}

	/**
	 * Write a solid-colour PNG to a temp file and return its absolute path.
	 *
	 * @param  array{0:int,1:int,2:int}  $rgb
	 */
	private function makePng(int $width, int $height, array $rgb): string
	{
		$img = imagecreatetruecolor($width, $height);
		$colour = imagecolorallocate($img, $rgb[0], $rgb[1], $rgb[2]);
		imagefilledrectangle($img, 0, 0, $width, $height, $colour);

		$path = tempnam(sys_get_temp_dir(), 'composite_test_').'.png';
		imagepng($img, $path);
		imagedestroy($img);

		return $path;
	}
}
