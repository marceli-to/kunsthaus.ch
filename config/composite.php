<?php

/*
|--------------------------------------------------------------------------
| Composite template layout — PLACEHOLDER
|--------------------------------------------------------------------------
|
| ⚠️  EVERY number/colour/font below is a PLACEHOLDER. The real canvas size,
| zone positions, typefaces and colours are a DESIGN DELIVERABLE. This file
| exists so the pipeline produces a recognisable image now; do not treat these
| values as final. When the design lands, update this single file.
|
| Coordinates are pixels on the canvas. Origin is top-left.
|
*/

return [

    // Output canvas (4:5 portrait — social-friendly placeholder).
    'canvas' => [
        'width' => 1080,
        'height' => 1350,
        'background' => 'ffffff', // white, per brief (portrait placed on white)
    ],

    // Where the visitor's portrait is drawn (cover-fit into this box).
    'portrait' => [
        'x' => 90,
        'y' => 90,
        'width' => 900,
        'height' => 780,
    ],

    // The chosen "JA" style PNG (contain-fit into this box, keeps aspect).
    'ja' => [
        'x' => 240,
        'y' => 700,
        'width' => 600,
        'height' => 360,
    ],

    // Rendered "Vorname Name".
    'name' => [
        'x' => 540,          // centre x (text is centre-anchored)
        'y' => 1140,
        'size' => 52,
        'color' => '1c1a17',
        'font' => 'resources/fonts/Fraunces-SemiBold.ttf',
        'align' => 'center',
    ],

    // Fixed campaign branding/slogan.
    'branding' => [
        'text' => 'Ja zum Kunsthaus',
        'x' => 540,
        'y' => 1230,
        'size' => 34,
        'color' => 'b4543a',
        'font' => 'resources/fonts/InstrumentSans-Medium.ttf',
        'align' => 'center',
    ],

    // Upload validation limits.
    'upload' => [
        'max_kb' => 12288,         // 12 MB
        'min_dimension' => 200,    // px (shortest side)
        'mimes' => ['jpeg', 'jpg', 'png', 'webp'],
    ],
];
