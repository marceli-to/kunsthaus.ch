<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Image Generation Provider & Model
    |--------------------------------------------------------------------------
    |
    | Which Laravel AI SDK provider + model to use for generation. Swapping
    | provider here (e.g. to "gemini" / "imagen-4") is the only change needed
    | to move off OpenAI — controller and frontend stay untouched.
    |
    */

    'provider' => env('IMAGE_PROVIDER', 'openai'),
    'model' => env('IMAGE_MODEL', 'gpt-image-1'),

    // 'low' | 'medium' | 'high'. gpt-image-1 'high' can run >30s and trip
    // Herd's FastCGI timeout for synchronous requests — 'medium' is a safe,
    // good-looking default (~18s). Bump to 'high' only with async generation.
    'quality' => env('IMAGE_QUALITY', 'medium'),
    'timeout' => (int) env('IMAGE_TIMEOUT', 120),

    /*
    |--------------------------------------------------------------------------
    | Daily Generation Cap
    |--------------------------------------------------------------------------
    |
    | A hard global ceiling on generations per day. This endpoint spends real
    | money per call, so past this count we return a friendly error instead.
    |
    */

    'daily_cap' => (int) env('IMAGE_DAILY_CAP', 100),

];
