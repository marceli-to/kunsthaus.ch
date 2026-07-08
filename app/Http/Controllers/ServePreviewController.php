<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Streams a pre-consent composite preview from the PRIVATE "local" disk. The
 * preview is not public — this route is only reachable via the temporary signed
 * URL minted by CompositeService (30-min TTL), enforced by the `signed`
 * middleware. Returns 404 once the temp file is gone (submitted or pruned).
 */
class ServePreviewController extends Controller
{
	public function __invoke(Request $request, string $preview): StreamedResponse
	{
		$disk = Storage::disk('local');
		$path = "previews/{$preview}.jpg";

		abort_unless($disk->exists($path), 404);

		return $disk->response($path, "{$preview}.jpg", [
			'Content-Type' => 'image/jpeg',
			'Cache-Control' => 'private, max-age=1800',
		]);
	}
}
