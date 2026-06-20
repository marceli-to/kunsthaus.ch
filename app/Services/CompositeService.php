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

        // 2. Portrait — EXIF-orient, strip metadata (via re-decode), cover-fit.
        $p = $cfg['portrait'];
        $portrait = $this->manager->read($portraitPath)
            ->orient()
            ->cover($p['width'], $p['height']);
        $canvas->place($portrait, 'top-left', $p['x'], $p['y']);

        // 3. Chosen "JA" style PNG — contain-fit (keep aspect, keep alpha).
        $j = $cfg['ja'];
        $ja = $this->manager->read($jaPngPath)->scaleDown($j['width'], $j['height']);
        // Centre the scaled JA within its zone.
        $jaX = $j['x'] + (int) (($j['width'] - $ja->width()) / 2);
        $jaY = $j['y'] + (int) (($j['height'] - $ja->height()) / 2);
        $canvas->place($ja, 'top-left', $jaX, $jaY);

        // 4. Name + branding text.
        $name = trim($firstName.' '.$lastName);
        if ($name !== '') {
            $this->writeText($canvas, $name, $cfg['name']);
        }
        $this->writeText($canvas, $cfg['branding']['text'], $cfg['branding']);

        // 5. Flatten to JPEG on the public temp "previews" disk by UUID.
        // PROD: previews are pre-consent — serve via a signed/expiring route off
        // a private disk rather than a public URL, and let app:prune-previews
        // (Phase 4) sweep anything older than ~24h.
        $previewId = (string) \Illuminate\Support\Str::uuid();
        $relativeDir = 'previews';
        $absoluteDir = storage_path("app/public/{$relativeDir}");
        if (! is_dir($absoluteDir) && ! mkdir($absoluteDir, 0775, true) && ! is_dir($absoluteDir)) {
            throw new RuntimeException('Could not create previews directory.');
        }

        $absolutePath = "{$absoluteDir}/{$previewId}.jpg";
        $canvas->toJpeg(quality: 90)->save($absolutePath);

        $url = rtrim(config('app.url'), '/')."/storage/{$relativeDir}/{$previewId}.jpg";

        return [$previewId, $url];
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
