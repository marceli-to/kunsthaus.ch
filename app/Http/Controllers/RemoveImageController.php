<?php

namespace App\Http\Controllers;

use App\Models\GeneratedImage;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

/**
 * Tokenised remove / unsubscribe link (from the publish email). Reachable only
 * via the long-lived signed URL in the notification (`signed` middleware).
 * Deleting the record fires the observer, which wipes both private files — the
 * FADP deletion path. Idempotent: an already-removed uuid shows the same
 * confirmation rather than a 404, so a re-click doesn't look broken.
 */
class RemoveImageController extends Controller
{
	public function __invoke(Request $request, string $uuid): View
	{
		GeneratedImage::where('uuid', $uuid)->first()?->delete();

		return view('images.removed');
	}
}
