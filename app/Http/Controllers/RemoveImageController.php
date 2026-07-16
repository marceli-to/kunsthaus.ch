<?php

namespace App\Http\Controllers;

use App\Models\GeneratedImage;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

/**
 * Tokenised remove / unsubscribe link (from the publish email). The current
 * links carry an opaque per-image token in the path (`/bild-entfernen/{token}`)
 * — a plain-looking URL, because the previous signed `?expires=&signature=`
 * query pattern trips Google Safe Browsing's phishing heuristic. Mails sent
 * before the switch still hit the legacy signed uuid route.
 *
 * Deleting the record fires the observer, which wipes both private files — the
 * FADP deletion path. Idempotent: an already-removed token/uuid shows the same
 * confirmation rather than a 404, so a re-click doesn't look broken.
 */
class RemoveImageController extends Controller
{
	public function byToken(Request $request, string $token): View
	{
		GeneratedImage::where('remove_token', $token)->first()?->delete();

		return view('images.removed');
	}

	/**
	 * Legacy path for links minted before the token switch (signed middleware).
	 */
	public function byUuid(Request $request, string $uuid): View
	{
		GeneratedImage::where('uuid', $uuid)->first()?->delete();

		return view('images.removed');
	}
}
