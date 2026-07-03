<?php

namespace App\Exceptions;

use Illuminate\Http\JsonResponse;
use RuntimeException;

/**
 * The requested "JA" style key doesn't resolve to a published style/asset.
 * Renders as a 422 with a field error so the frontend can flag the dropdown.
 */
class UnknownStyleException extends RuntimeException
{
	public function render(): JsonResponse
	{
		return response()->json([
			'message' => 'Unbekannter «JA»-Stil.',
			'errors' => ['ja_style' => ['Bitte wähle einen verfügbaren Stil.']],
		], 422);
	}
}
