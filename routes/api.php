<?php

use App\Http\Controllers\GenerateController;
use App\Http\Controllers\JaStyleController;
use Illuminate\Support\Facades\Route;

// Populates the generator's "JA" style dropdown from the Statamic collection.
Route::get('/ja-styles', JaStyleController::class);

// Deterministic composite pipeline (no AI). Receives the portrait (possibly
// already background-removed in the browser), the chosen "JA" style and name,
// composites them onto the campaign template via GD, and returns a preview.
// 10 requests/min per IP — public upload+processing endpoint.
Route::post('/generate', GenerateController::class)
    ->middleware('throttle:10,1');

// PROD: Cloudflare Turnstile verification belongs in front of /generate.
