<?php

use App\Http\Controllers\ImageGenerationController;
use Illuminate\Support\Facades\Route;

// 10 requests/min per IP. The hard global daily cap lives in the controller.
Route::post('/generate-image', ImageGenerationController::class)
    ->middleware('throttle:10,1');
