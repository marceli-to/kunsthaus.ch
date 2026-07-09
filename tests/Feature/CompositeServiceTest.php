<?php

namespace Tests\Feature;

use App\Services\CompositeService;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Exercises the real GD compositing pipeline end-to-end (no mocking of
 * Intervention). It reads real portrait + JA PNGs off disk, composites onto the
 * campaign template using the bundled font/footer assets, and writes a flattened
 * JPEG preview plus the EXIF-stripped source copy and a metadata sidecar to the
 * PRIVATE "local" disk (faked here). Asserts the returned contract, the files
 * that land on disk and that a valid image of the configured canvas size is
 * produced.
 */
class CompositeServiceTest extends TestCase
{
	private string $portraitPath;

	private string $jaPath;

	protected function setUp(): void
	{
		parent::setUp();

		// The composite is persisted to the private "local" disk; fake it so the
		// test neither touches real storage nor needs cleanup.
		Storage::fake('local');

		$this->portraitPath = $this->makePng(1200, 800, [30, 120, 200]);
		$this->jaPath = $this->makePng(684, 684, [200, 60, 60]);
	}

	protected function tearDown(): void
	{
		@unlink($this->portraitPath);
		@unlink($this->jaPath);

		parent::tearDown();
	}

	/**
	 * @return array{preview_id: string, url: string, source_path: string, composite_path: string}
	 */
	private function build(string $firstName = 'Marcel', string $lastName = 'Stadelmann'): array
	{
		return app(CompositeService::class)->build(
			portraitPath: $this->portraitPath,
			portraitExt: 'png',
			jaPngPath: $this->jaPath,
			firstName: $firstName,
			lastName: $lastName,
			meta: [
				'first_name' => $firstName,
				'last_name' => $lastName,
				'ja_style' => 'oil',
				'background_removed' => false,
			],
		);
	}

	public function test_it_returns_a_preview_id_and_a_signed_url(): void
	{
		$result = $this->build();

		$this->assertMatchesRegularExpression(
			'/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/',
			$result['preview_id'],
			'preview_id should be a UUID',
		);

		// The composite is pre-consent, so it is served via a temporary signed
		// route — never a guessable public URL.
		$this->assertStringContainsString('/previews/'.$result['preview_id'], $result['url']);
		$this->assertStringContainsString('signature=', $result['url']);
	}

	public function test_it_persists_the_composite_source_and_sidecar_to_the_private_disk(): void
	{
		$result = $this->build();

		$disk = Storage::disk('local');

		$disk->assertExists($result['composite_path']);
		$disk->assertExists($result['source_path']);
		$disk->assertExists("previews/{$result['preview_id']}.json");

		$this->assertSame("previews/{$result['preview_id']}.jpg", $result['composite_path']);
		$this->assertStringStartsWith("previews/{$result['preview_id']}.src.", $result['source_path']);

		// The sidecar carries the (server-owned) submit metadata plus the two paths
		// so /api/submit can promote the files without a re-upload.
		$sidecar = json_decode($disk->get("previews/{$result['preview_id']}.json"), true);
		$this->assertSame('oil', $sidecar['ja_style']);
		$this->assertSame('Marcel', $sidecar['first_name']);
		$this->assertFalse($sidecar['background_removed']);
		$this->assertSame($result['composite_path'], $sidecar['composite_path']);
		$this->assertSame($result['source_path'], $sidecar['source_path']);
	}

	public function test_it_writes_a_valid_jpeg_of_the_configured_canvas_size(): void
	{
		$result = $this->build('Anna', 'Meier');

		$bytes = Storage::disk('local')->get($result['composite_path']);
		$info = getimagesizefromstring($bytes);

		$this->assertNotFalse($info, 'output should be a readable image');
		$this->assertSame('image/jpeg', $info['mime']);
		$this->assertSame(config('composite.canvas.width'), $info[0]);
		$this->assertSame(config('composite.canvas.height'), $info[1]);
	}

	public function test_it_handles_an_empty_name_without_error(): void
	{
		$result = $this->build('', '');

		Storage::disk('local')->assertExists($result['composite_path']);
	}

	public function test_it_shrinks_the_font_so_very_long_names_still_fit(): void
	{
		// A pathologically long name must not throw and must still produce a
		// canvas-sized image (the fitFontSize loop clamps to min_size).
		$result = $this->build(str_repeat('Wolfgang', 8), str_repeat('Amadeus', 8));

		$info = getimagesizefromstring(Storage::disk('local')->get($result['composite_path']));
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
