<?php

namespace App\Http\Controllers;

use App\Actions\DeliverPrivateImage;
use App\Http\Requests\DeliverImageRequest;

/**
 * Private JAtelier delivery (/jatelier, employee page): emails the composite to
 * the employee and returns a signed download URL — with NO database record and
 * NO publishing. Validation lives in DeliverImageRequest, the mail + URL logic in
 * DeliverPrivateImage, and the expired-preview case surfaces as a rendered domain
 * exception — so this stays thin.
 */
class DeliverPrivateImageController extends Controller
{
	public function __invoke(DeliverImageRequest $request, DeliverPrivateImage $deliver): array
	{
		$downloadUrl = $deliver->handle(
			previewId: $request->validated('preview_id'),
			email: $request->validated('email'),
		);

		return ['download_url' => $downloadUrl];
	}
}
