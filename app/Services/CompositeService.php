<?php

namespace App\Services;

use Intervention\Image\ImageManager;
use Intervention\Image\Typography\FontFactory;
use RuntimeException;

/**
 * Deterministic image compositing (NO AI). Layers the visitor's portrait, the
 * chosen "JA" style PNG, the rendered name and the fixed campaign branding onto
 * the template canvas using Intervention Image's GD driver (no Imagick — the
 * target Swiss shared host may not have it).
 *
 * Reading the portrait and re-encoding the flattened result strips all source
 * EXIF metadata (incl. GPS); orientation is applied first so phone photos are
 * upright.
 */
class CompositeService
{
	private ImageManager $manager;

	public function __construct()
	{
		// GD driver explicitly — do not depend on Imagick.
		$this->manager = ImageManager::gd();
	}

	/**
	 * Build the composite and write a flattened JPEG preview to the public
	 * "previews" folder. Returns [previewId, absolute public URL].
	 *
	 * @param  string  $portraitPath  absolute path to the uploaded portrait
	 * @param  string  $jaPngPath     absolute path to the chosen JA style PNG
	 * @return array{0: string, 1: string}
	 */
	public function build(string $portraitPath, string $jaPngPath, string $firstName, string $lastName): array
	{
		$cfg = config('composite');

		// 1. White canvas.
		$canvas = $this->manager->create($cfg['canvas']['width'], $cfg['canvas']['height'])
			->fill($cfg['canvas']['background']);

		// 2. Portrait — EXIF-orient, strip metadata (via re-decode), cover-fit
		// into its fixed box. Top gravity keeps the face when cropping a tall
		// photo; the JA card below overlaps the lower part.
		$p = $cfg['portrait'];
		$portrait = $this->manager->read($portraitPath)
			->orient()
			->cover($p['width'], $p['height'], $p['gravity'] ?? 'center');
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

		// 6. Flatten to JPEG on the public temp "previews" disk by UUID.
		// PROD: previews are pre-consent — serve via a signed/expiring route off
		// a private disk rather than a public URL, and let app:prune-previews
		// (Phase 4) sweep anything older than ~24h.
		$previewId = (string) \Illuminate\Support\Str::uuid();
		$relativeDir = 'previews';
		$absoluteDir = storage_path("app/public/{$relativeDir}");
		if (!is_dir($absoluteDir) && !mkdir($absoluteDir, 0775, true) && !is_dir($absoluteDir)) {
			throw new RuntimeException('Could not create previews directory.');
		}

		$absolutePath = "{$absoluteDir}/{$previewId}.jpg";
		$canvas->toJpeg(quality: 90)->save($absolutePath);

		$url = rtrim(config('app.url'), '/')."/storage/{$relativeDir}/{$previewId}.jpg";

		return [$previewId, $url];
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
