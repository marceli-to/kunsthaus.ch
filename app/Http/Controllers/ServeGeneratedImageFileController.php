<?php

namespace App\Http\Controllers;

use App\Models\GeneratedImage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Statamic\Facades\User;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Serves a moderation image (source portrait or final composite) to the Control
 * Panel from the PRIVATE "local" disk. Behind the `statamic.cp.authenticated`
 * group + Runway's "view generated_image" permission, so only signed-in
 * moderators can view UGC. `which` is whitelisted to two paths — never a
 * client-supplied path.
 */
class ServeGeneratedImageFileController extends Controller
{
	public function __invoke(Request $request, string $uuid, string $which): StreamedResponse
	{
		abort_unless(User::current()?->can('view generated_image'), 403);
		abort_unless(in_array($which, ['source', 'final'], true), 404);

		$image = GeneratedImage::where('uuid', $uuid)->firstOrFail();
		$path = $which === 'final' ? $image->final_path : $image->source_image_path;

		$disk = Storage::disk('local');
		abort_unless($disk->exists($path), 404);

		return $disk->response($path, basename($path), [
			'Cache-Control' => 'private, no-store',
		]);
	}
}
