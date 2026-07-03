<?php

namespace App\Http\Controllers;

use App\Services\JaStyleRepository;
use Illuminate\Http\JsonResponse;

/**
 * Boot payload for the JAtelier generator island: the "JA" styles, whether the
 * client-side background removal is offered, and the composite geometry (so the
 * browser crop frame matches the final image). Single source of truth for the
 * geometry is config/composite.php — the server needs it to build the image, so
 * the frontend reads it from here rather than having it duplicated/serialised.
 */
class GeneratorController extends Controller
{
    public function __invoke(JaStyleRepository $styles): JsonResponse
    {
        $portrait = config('composite.portrait');
        $ja = config('composite.ja');

        return response()->json([
            'styles' => $styles->all(),
            'bg_removal' => (bool) config('composite.bg_removal'),
            'geometry' => [
                'portrait' => ['x' => $portrait['x'], 'y' => $portrait['y'], 'w' => $portrait['width'], 'h' => $portrait['height']],
                'ja' => ['x' => $ja['x'], 'y' => $ja['y'], 'w' => $ja['width'], 'h' => $ja['height']],
            ],
        ]);
    }
}
