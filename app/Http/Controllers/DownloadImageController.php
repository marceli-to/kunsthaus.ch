<?php

namespace App\Http\Controllers;

use App\Models\GeneratedImage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Streams a submitted composite from the PRIVATE "local" disk as a download.
 * Reachable only via the temporary signed URL handed to the visitor on submit
 * (`signed` middleware). Resolved by uuid so the auto-increment id stays private.
 */
class DownloadImageController extends Controller
{
	public function __invoke(Request $request, string $uuid): StreamedResponse
	{
		$image = GeneratedImage::where('uuid', $uuid)->firstOrFail();

		$disk = Storage::disk('local');
		abort_unless($disk->exists($image->final_path), 404);

		return $disk->download($image->final_path, 'ja-zum-kunsthaus.jpg', [
			'Content-Type' => 'image/jpeg',
		]);
	}
}
