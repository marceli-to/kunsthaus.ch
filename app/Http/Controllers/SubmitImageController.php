<?php

namespace App\Http\Controllers;

use App\Actions\SubmitGeneratedImage;
use App\Http\Requests\SubmitImageRequest;
use App\Http\Resources\SubmittedImageResource;

/**
 * Confirm/submit (Phase 4): the visitor consented to store + publish. Validation
 * lives in SubmitImageRequest, the promote-and-record logic in
 * SubmitGeneratedImage, and the two failure modes surface as rendered domain
 * exceptions — so this stays thin.
 */
class SubmitImageController extends Controller
{
	public function __invoke(SubmitImageRequest $request, SubmitGeneratedImage $submit): SubmittedImageResource
	{
		$image = $submit->handle(
			previewId: $request->validated('preview_id'),
			email: $request->validated('email'),
		);

		return new SubmittedImageResource($image);
	}
}
