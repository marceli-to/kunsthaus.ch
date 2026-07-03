<?php

namespace App\Exceptions;

use Illuminate\Http\JsonResponse;
use RuntimeException;

/**
 * The composite pipeline failed (GD error, unwritable disk, …). The underlying
 * cause is chained as $previous for logging; the client sees a generic message.
 */
class ImageGenerationException extends RuntimeException
{
	public function render(): JsonResponse
	{
		return response()->json([
			'message' => 'Das Bild konnte nicht erstellt werden. Bitte versuche es erneut.',
		], 500);
	}
}
