<?php

namespace App\Http\Controllers;

use App\Actions\GenerateCompositeImage;
use App\Http\Requests\GenerateImageRequest;
use App\Http\Resources\ImagePreviewResource;

/**
 * Generates a composite preview (NO AI, no DB record yet). Validation lives in
 * GenerateImageRequest, orchestration in GenerateCompositeImage, and the two
 * failure modes surface as rendered domain exceptions — so this stays thin.
 */
class GenerateImageController extends Controller
{
	public function __invoke(GenerateImageRequest $request, GenerateCompositeImage $generate): ImagePreviewResource
	{
		$preview = $generate->handle(
			portrait: $request->file('portrait'),
			styleKey: $request->validated('ja_style'),
			firstName: $request->firstName(),
			lastName: $request->lastName(),
		);

		return new ImagePreviewResource($preview);
	}
}
