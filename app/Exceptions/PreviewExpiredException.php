<?php

namespace App\Exceptions;

use Illuminate\Http\JsonResponse;
use RuntimeException;

/**
 * The temp preview referenced on submit no longer exists — it expired, was
 * pruned, or the sidecar is unreadable. Renders as a 422 with a field error so
 * the frontend can prompt the visitor to regenerate.
 */
class PreviewExpiredException extends RuntimeException
{
	public function render(): JsonResponse
	{
		return response()->json([
			'message' => 'Die Vorschau ist abgelaufen. Bitte erstellen Sie das Bild erneut.',
			'errors' => ['preview_id' => ['Die Vorschau ist nicht mehr verfügbar.']],
		], 422);
	}
}
