<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Validator;

/**
 * Validates the confirm/submit step (Phase 4): the visitor's email, the required
 * FADP consent, and that the referenced temp preview still exists. The name,
 * style and background flag are NOT accepted here — the server already holds them
 * (sanitised) in the preview sidecar, so they can't be changed after the image
 * was composited.
 */
class SubmitImageRequest extends FormRequest
{
	public function authorize(): bool
	{
		return true;
	}

	/**
	 * @return array<string, mixed>
	 */
	public function rules(): array
	{
		return [
			'preview_id' => ['required', 'uuid'],
			'email' => ['required', 'email', 'max:255'],
			'consent' => ['required', 'accepted'],
		];
	}

	/**
	 * The temp composite + sidecar must still be on disk. Checked after the base
	 * rules so a malformed uuid doesn't touch the filesystem.
	 */
	public function withValidator(Validator $validator): void
	{
		$validator->after(function (Validator $validator) {
			if ($validator->errors()->has('preview_id')) {
				return;
			}

			$previewId = $this->input('preview_id');
			$disk = Storage::disk('local');

			$exists = $disk->exists("previews/{$previewId}.jpg")
				&& $disk->exists("previews/{$previewId}.json");

			if (! $exists) {
				$validator->errors()->add('preview_id', 'Die Vorschau ist abgelaufen. Bitte erstellen Sie das Bild erneut.');
			}
		});
	}

	/**
	 * @return array<string, string>
	 */
	public function messages(): array
	{
		return [
			'email.required' => 'Bitte geben Sie Ihre E-Mail-Adresse ein.',
			'email.email' => 'Bitte geben Sie eine gültige E-Mail-Adresse ein.',
			'consent.required' => 'Bitte bestätigen Sie die Einwilligung.',
			'consent.accepted' => 'Bitte bestätigen Sie die Einwilligung.',
			'preview_id.required' => 'Die Vorschau fehlt. Bitte erstellen Sie das Bild erneut.',
		];
	}
}
