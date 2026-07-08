<?php

/*
|--------------------------------------------------------------------------
| Composite template layout — 1080×1350 "JA" campaign image
|--------------------------------------------------------------------------
|
| Coordinates are pixels on the canvas. Origin is top-left. Each visual part
| has a FIXED box; images are cover-cropped into their box so an over-tall or
| over-wide upload is cropped rather than distorted.
|
| Layer order (bottom → top): white canvas → portrait → JA style card →
| name line ("{Vorname} {Name} SAGT") → footer logo lockup.
|
| The name line renders in Futura PT Medium. Positions are a first pass; nudge
| the numbers here.
|
*/

return [

	// Whether the client-side "remove background" toggle is offered in the UI.
	// PROD: @imgly/background-removal is AGPL-3.0 — buy the commercial licence
	// or swap to a permissive model (BiRefNet/MODNet) before enabling in prod.
	'bg_removal' => (bool) env('COMPOSITE_BG_REMOVAL', true),

	// Output canvas — fixed 1080×1350 (4:5), white background.
	'canvas' => [
		'width' => 1080,
		'height' => 1350,
		'background' => 'ffffff',
	],

	// Visitor's portrait — a fixed 16:10 landscape RECTANGLE. The visitor
	// frames their photo into it in the browser (pan-only crop) and uploads the
	// framed image, so the server does NOT auto-crop — it just fits the
	// pre-framed image here. The browser crop UI reads this box + the `ja` box
	// (via data-attributes) so the frame always matches the final image —
	// change these numbers and the crop frame follows. Layout is STACKED:
	// portrait on top, the (smaller) JA card below it.
	'portrait' => [
		'x' => 199,       // (1080 - 682) / 2 — centred column
		'y' => 0,         // bleeds to the top edge
		'width' => 682,
		'height' => 420,  // 682 / 420 ≈ 16:10 landscape
	],

	// Chosen "JA" style card (square 684² tile) — cover-fit into this box,
	// flush below the portrait, sharing the same centred 682px column.
	'ja' => [
		'x' => 199,
		'y' => 420,       // flush under the portrait band (0 + 420)
		'width' => 682,
		'height' => 682,
	],

	// Rendered name line: "{Vorname} {Name} sagt" — centre-anchored, accent
	// blue. The NAME is uppercased; the " sagt" suffix stays lowercase (per the
	// template: "ANDREAS HUGI sagt").
	'name' => [
		'x' => 540,          // centre x (text is centre-anchored)
		'y' => 1130,
		'size' => 34,        // ≈ 32pt in the template artboard; nudge to taste
		'color' => '0000ff', // accent blue
		'font' => 'resources/fonts/FuturaPT-Medium.ttf',
		'align' => 'center',
		'uppercase' => true, // applies to the name only, not the suffix
		'suffix' => ' sagt',
		'max_width' => 940, // long names auto-shrink to fit within this width
		'min_size' => 22,   // don't shrink below this
	],

	// Fixed footer logo lockup ("JA ZUM KUNSTHAUS. ZU ZÜRICH.") — contain-fit
	// into this box (keeps aspect), centred, near the bottom. 563px wide per the
	// template; height 114 keeps the 1362×276 logo aspect.
	'footer' => [
		'x' => 259,          // (1080 - 563) / 2 — centred
		'y' => 1180,
		'width' => 563,
		'height' => 114,
		'image' => 'resources/composite/footer-logo.png',
	],

	// Upload validation limits.
	'upload' => [
		'max_kb' => 12288,         // 12 MB
		'min_dimension' => 200,    // px (shortest side)
		'mimes' => ['jpeg', 'jpg', 'png', 'webp'],
	],
];
