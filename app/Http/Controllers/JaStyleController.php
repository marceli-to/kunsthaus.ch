<?php

namespace App\Http\Controllers;

use App\Services\JaStyleRepository;
use Illuminate\Http\JsonResponse;

class JaStyleController extends Controller
{
    /**
     * Public list of "JA" styles for the generator dropdown.
     */
    public function __invoke(JaStyleRepository $styles): JsonResponse
    {
        return response()->json(['data' => $styles->all()]);
    }
}
