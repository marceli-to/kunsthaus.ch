<?php

/**
 * Dev helper: generate clearly-marked PLACEHOLDER "JA" PNGs (one per painting
 * technique) so the dropdown is populated before the designer delivers real
 * transparent-PNG assets. Run: php scripts/make-placeholder-ja.php
 *
 * NOT part of the app runtime. Real assets are a design deliverable.
 */

$font = __DIR__.'/../resources/fonts/Fraunces-SemiBold.ttf';
$outDir = __DIR__.'/../storage/app/public/ja-styles';

if (! is_dir($outDir)) {
    mkdir($outDir, 0775, true);
}

/** @var array<string, array{0:int,1:int,2:int}> $styles label => RGB ink */
$styles = [
    'oel' => [180, 84, 58],      // clay / warm
    'aquarell' => [70, 110, 165], // muted blue
    'tusche' => [28, 26, 23],     // near-black ink
];

$w = 900;
$h = 520;

foreach ($styles as $key => [$r, $g, $b]) {
    $img = imagecreatetruecolor($w, $h);
    imagesavealpha($img, true);
    imagealphablending($img, false);
    imagefill($img, 0, 0, imagecolorallocatealpha($img, 0, 0, 0, 127)); // transparent
    imagealphablending($img, true);

    $ink = imagecolorallocate($img, $r, $g, $b);
    $size = 320;
    $text = 'JA';

    // Centre the text.
    $bbox = imagettfbbox($size, 0, $font, $text);
    $textW = $bbox[2] - $bbox[0];
    $textH = $bbox[1] - $bbox[7];
    $x = (int) (($w - $textW) / 2 - $bbox[0]);
    $y = (int) (($h - $textH) / 2 - $bbox[7]);

    imagettftext($img, $size, 0, $x, $y, $ink, $font, $text);

    // Tiny "Platzhalter" watermark so these never get mistaken for finals.
    $mark = imagecolorallocatealpha($img, $r, $g, $b, 95);
    imagettftext($img, 16, 0, 24, $h - 22, $mark, $font, 'Platzhalter — '.$key);

    $path = "$outDir/$key.png";
    imagepng($img, $path);
    imagedestroy($img);
    echo "wrote $path\n";
}
