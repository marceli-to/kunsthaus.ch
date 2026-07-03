<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates the public composite-generation request: the portrait upload, the
 * chosen "JA" style key and the visitor's name. Upload constraints come from
 * config/composite.php so they stay in sync with the pipeline.
 */
class GenerateImageRequest extends FormRequest
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
		$upload = config('composite.upload');

		return [
			'portrait' => [
				'required', 'image',
				'mimes:'.implode(',', $upload['mimes']),
				'max:'.$upload['max_kb'],
				'dimensions:min_width='.$upload['min_dimension'].',min_height='.$upload['min_dimension'],
			],
			'ja_style' => ['required', 'string', 'max:64'],
			'first_name' => ['required', 'string', 'max:40'],
			'last_name' => ['required', 'string', 'max:40'],
		];
	}

	/**
	 * @return array<string, string>
	 */
	public function messages(): array
	{
		$upload = config('composite.upload');

		return [
			'portrait.required' => 'Bitte wählen Sie ein Foto.',
			'portrait.image' => 'Die Datei muss ein Bild sein.',
			'portrait.dimensions' => 'Das Bild ist zu klein (min. '.$upload['min_dimension'].'px).',
			'portrait.max' => 'Das Bild ist zu gross (max. '.round($upload['max_kb'] / 1024).' MB).',
			'first_name.required' => 'Bitte geben Sie einen Vornamen ein.',
			'last_name.required' => 'Bitte geben Sie einen Namen ein.',
		];
	}

	public function firstName(): string
	{
		return $this->sanitiseName($this->validated('first_name'));
	}

	public function lastName(): string
	{
		return $this->sanitiseName($this->validated('last_name'));
	}

	/**
	 * Trim, collapse whitespace and strip control/markup characters from a name
	 * so it renders cleanly and can't inject markup downstream. It is published
	 * UGC.
	 *
	 * PROD: replace with a maintained profanity list / moderation service.
	 */
	private function sanitiseName(string $value): string
	{
		$value = strip_tags($value);
		$value = preg_replace('/[\x00-\x1F\x7F<>]/u', '', $value) ?? '';

		return trim(preg_replace('/\s+/u', ' ', $value) ?? '');
	}
}
