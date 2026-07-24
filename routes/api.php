<?php

use App\Http\Controllers\DeliverPrivateImageController;
use App\Http\Controllers\GenerateImageController;
use App\Http\Controllers\GeneratorConfigController;
use App\Http\Controllers\SubmitImageController;
use Illuminate\Support\Facades\Route;

// Boot payload for the generator island: "JA" styles + bg-removal flag +
// composite geometry (config/composite.php), so the crop frame matches output.
Route::get('/generator', GeneratorConfigController::class);

// Deterministic composite pipeline (no AI). Receives the browser-framed portrait
// (possibly already background-removed), the chosen "JA" style and name,
// composites them onto the campaign template via GD, and returns a preview.
// 10 requests/min per IP — public upload+processing endpoint.
Route::post('/generate', GenerateImageController::class)->middleware('throttle:10,1');

// Confirm/submit (Phase 4): the visitor consented → promote the temp preview to
// permanent private storage, create the GeneratedImage record (status=submitted,
// consent_at) and queue the copy email. Only needs {preview_id, email, consent}.
// 20 requests/min per IP.
Route::post('/submit', SubmitImageController::class)->middleware('throttle:20,1');

// Private delivery (/jatelier, employee page): email the composite to the
// employee for their own use + return a signed download URL. NO database record,
// NO publishing — these images never reach the site. Only needs {preview_id,
// email}. 20 requests/min per IP.
Route::post('/deliver', DeliverPrivateImageController::class)->middleware('throttle:20,1');
