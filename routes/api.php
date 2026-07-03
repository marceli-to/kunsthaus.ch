<?php

use App\Http\Controllers\GenerateController;
use App\Http\Controllers\GeneratorController;
use Illuminate\Support\Facades\Route;

// Boot payload for the generator island: "JA" styles + bg-removal flag +
// composite geometry (config/composite.php), so the crop frame matches output.
Route::get('/generator', GeneratorController::class);

// Deterministic composite pipeline (no AI). Receives the browser-framed portrait
// (possibly already background-removed), the chosen "JA" style and name,
// composites them onto the campaign template via GD, and returns a preview.
// 10 requests/min per IP — public upload+processing endpoint.
Route::post('/generate', GenerateController::class)
    ->middleware('throttle:10,1');
