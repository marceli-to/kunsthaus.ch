<?php

namespace App\Http\Controllers;

use App\Http\Resources\GeneratorConfigResource;
use App\Services\JaStyleRepository;

/**
 * Boot payload for the JAtelier generator island (styles + bg-removal flag +
 * composite geometry). See GeneratorConfigResource for the shape.
 */
class GeneratorConfigController extends Controller
{
	public function __invoke(JaStyleRepository $styles): GeneratorConfigResource
	{
		return new GeneratorConfigResource([
			'styles' => $styles->all(),
			'bg_removal' => config('composite.bg_removal'),
			'portrait' => config('composite.portrait'),
			'ja' => config('composite.ja'),
			'upload' => config('composite.upload'),
		]);
	}
}
