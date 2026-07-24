<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Validator;

/**
 * Validates the PRIVATE employee delivery step (/jatelier). Unlike the public
 * SubmitImageRequest there is NO publish-consent — these images are for private
 * use (e.g. social media) and are never stored in the database or published.
 * Only the employee's email and the referenced temp preview are needed; the
 * name/style/bg flag already live (sanitised) in the preview sidecar.
 */
class DeliverImageRequest extends FormRequest
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
			'preview_id.required' => 'Die Vorschau fehlt. Bitte erstellen Sie das Bild erneut.',
		];
	}
}
