<?php

namespace App\Services;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Intervention\Image\ImageManager;
use Intervention\Image\Typography\FontFactory;

/**
 * Deterministic image compositing (NO AI). Layers the visitor's portrait, the
 * chosen "JA" style PNG, the rendered name and the fixed campaign branding onto
 * the template canvas using Intervention Image's GD driver (no Imagick — the
 * target Swiss shared host may not have it).
 *
 * Reading the portrait and re-encoding both the source copy and the flattened
 * result strips all source EXIF metadata (incl. GPS); orientation is applied
 * first so phone photos are upright.
 *
 * Everything is written to the PRIVATE "local" disk under `previews/` and served
 * via a temporary signed route — the composite is pre-consent, so it is never
 * exposed at a guessable public URL. On submit (Phase 4) these temp files are
 * promoted to permanent private storage; orphans are swept by app:prune-previews.
 * PROD: build app:prune-previews (Phase 8) to sweep `private/previews/` > ~24h.
 */
class CompositeService
{
	/**
	 * Temp working directory on the private "local" disk (storage/app/private).
	 */
	private const TEMP_DIR = 'previews';

	private ImageManager $manager;

	public function __construct()
	{
		// GD driver explicitly — do not depend on Imagick.
		$this->manager = ImageManager::gd();
	}

	/**
	 * Build the composite, persist the (EXIF-stripped) source portrait, the
	 * flattened composite and a sidecar of the submit metadata — all to the
	 * private disk, keyed by a fresh preview id. Returns the preview id, a signed
	 * temporary URL for the composite, and the two relative paths so the submit
	 * action can promote them without re-uploading.
	 *
	 * @param  string  $portraitPath  absolute path to the uploaded portrait
	 * @param  string  $portraitExt   source portrait extension (jpg/png/webp)
	 * @param  string  $jaPngPath     absolute path to the chosen JA style PNG
	 * @param  array<string, mixed>  $meta  sidecar metadata (name, style, bg flag)
	 * @return array{preview_id: string, url: string, source_path: string, composite_path: string}
	 */
	public function build(
		string $portraitPath,
		string $portraitExt,
		string $jaPngPath,
		string $firstName,
		string $lastName,
		array $meta,
	): array {
		$cfg = config('composite');

		// 1. White canvas.
		$canvas = $this->manager->create($cfg['canvas']['width'], $cfg['canvas']['height'])
			->fill($cfg['canvas']['background']);

		// 2. Portrait — EXIF-orient, strip metadata (via re-decode), cover-fit
		// into its fixed box. Top gravity keeps the face when cropping a tall
		// photo; the JA card below overlaps the lower part.
		$p = $cfg['portrait'];
		$portraitImage = $this->manager->read($portraitPath)->orient();
		$portrait = (clone $portraitImage)->cover($p['width'], $p['height'], $p['gravity'] ?? 'center');
		$canvas->place($portrait, 'top-left', $p['x'], $p['y']);

		// 3. Chosen "JA" style card — cover-fit its fixed (square) box and draw
		// ON TOP of the portrait so it reads as a held sign.
		$j = $cfg['ja'];
		$ja = $this->manager->read($jaPngPath)->cover($j['width'], $j['height']);
		$canvas->place($ja, 'top-left', $j['x'], $j['y']);

		// 4. Name line — "{Vorname} {Name} sagt", accent blue. The name is
		// uppercased; the " sagt" suffix stays lowercase (per the template).
		// Auto-shrink so long names never overflow the canvas.
		$name = trim($firstName.' '.$lastName);
		if ($name !== '') {
			$nameCfg = $cfg['name'];
			if ($nameCfg['uppercase'] ?? false) {
				$name = mb_strtoupper($name, 'UTF-8');
			}
			$line = $name.($nameCfg['suffix'] ?? '');
			$nameCfg['size'] = $this->fitFontSize(
				$line,
				base_path($nameCfg['font']),
				(int) $nameCfg['size'],
				(int) ($nameCfg['max_width'] ?? $cfg['canvas']['width']),
				(int) ($nameCfg['min_size'] ?? 12),
			);
			$this->writeText($canvas, $line, $nameCfg);
		}

		// 5. Fixed footer logo lockup — contain-fit into its box, centred.
		$f = $cfg['footer'];
		$footer = $this->manager->read(base_path($f['image']))->scaleDown($f['width'], $f['height']);
		$footerX = $f['x'] + (int) (($f['width'] - $footer->width()) / 2);
		$footerY = $f['y'] + (int) (($f['height'] - $footer->height()) / 2);
		$canvas->place($footer, 'top-left', $footerX, $footerY);

		// 6. Persist to the private disk, keyed by a fresh preview id.
		$previewId = (string) Str::uuid();
		$disk = Storage::disk('local');

		// Source portrait — re-encoded from the EXIF-oriented decode above so the
		// moderation copy carries no GPS/metadata either. Kept for review only.
		$ext = in_array($portraitExt, ['jpg', 'jpeg', 'png', 'webp'], true) ? $portraitExt : 'jpg';
		$sourcePath = self::TEMP_DIR."/{$previewId}.src.{$ext}";
		$disk->put($sourcePath, (string) $portraitImage->encodeByExtension($ext));

		// Flattened composite preview (JPEG).
		$compositePath = self::TEMP_DIR."/{$previewId}.jpg";
		$disk->put($compositePath, (string) $canvas->toJpeg(quality: 90));

		// Sidecar metadata so /api/submit only needs {preview_id, email, consent}
		// and the server owns the (already-sanitised) name/style/bg flag — the
		// client can't change what's baked into the composite between preview and
		// submit.
		$disk->put(self::TEMP_DIR."/{$previewId}.json", json_encode($meta + [
			'source_path' => $sourcePath,
			'composite_path' => $compositePath,
		], JSON_THROW_ON_ERROR));

		// Signed temp URL so the browser <img>/download can read the private file.
		$url = URL::temporarySignedRoute('previews.show', now()->addMinutes(30), ['preview' => $previewId]);

		return [
			'preview_id' => $previewId,
			'url' => $url,
			'source_path' => $sourcePath,
			'composite_path' => $compositePath,
		];
	}

	/**
	 * Largest font size (≤ $startSize, ≥ $minSize) at which $text fits within
	 * $maxWidth for the given TTF. Keeps long names from overflowing the canvas.
	 */
	private function fitFontSize(string $text, string $fontPath, int $startSize, int $maxWidth, int $minSize): int
	{
		for ($size = $startSize; $size > $minSize; $size--) {
			$box = imagettfbbox($size, 0, $fontPath, $text);
			$width = abs($box[2] - $box[0]);
			if ($width <= $maxWidth) {
				return $size;
			}
		}

		return $minSize;
	}

	/**
	 * Draw a centre-anchored line of text from the layout config.
	 *
	 * @param  array<string, mixed>  $zone
	 */
	private function writeText($canvas, string $text, array $zone): void
	{
		$fontPath = base_path($zone['font']);

		$canvas->text($text, $zone['x'], $zone['y'], function (FontFactory $font) use ($zone, $fontPath) {
			$font->filename($fontPath);
			$font->size($zone['size']);
			$font->color($zone['color']);
			$font->align($zone['align'] ?? 'center');
			$font->valign('top');
		});
	}
}
