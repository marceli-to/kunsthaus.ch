<?php

namespace Database\Factories;

use App\Enums\GeneratedImageStatus;
use App\Models\GeneratedImage;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * Builds GeneratedImage records for the moderation / publish lifecycle tests.
 * The default state is a freshly submitted record (consent given, awaiting
 * review); the published() / rejected() / notified() states advance it along the
 * lifecycle. The uuid is left to the model's `creating` hook so it matches
 * production.
 *
 * @extends Factory<GeneratedImage>
 */
class GeneratedImageFactory extends Factory
{
	protected $model = GeneratedImage::class;

	/**
	 * @return array<string, mixed>
	 */
	public function definition(): array
	{
		$uuid = (string) Str::uuid();

		return [
			'first_name' => fake()->firstName(),
			'last_name' => fake()->lastName(),
			'ja_style' => fake()->randomElement(['oil', 'ink', 'watercolour']),
			'background_removed' => fake()->boolean(),
			'source_image_path' => "images/{$uuid}/source.jpg",
			'final_path' => "images/{$uuid}/final.jpg",
			'status' => GeneratedImageStatus::Submitted,
			'user_email' => fake()->unique()->safeEmail(),
			'consent_at' => now(),
			'published_at' => null,
			'notified_at' => null,
		];
	}

	/**
	 * A moderator-approved image (creator not yet notified).
	 */
	public function published(): static
	{
		return $this->state(fn (array $attributes) => [
			'status' => GeneratedImageStatus::Published,
			'published_at' => now(),
		]);
	}

	/**
	 * A moderator-declined image.
	 */
	public function rejected(): static
	{
		return $this->state(fn (array $attributes) => [
			'status' => GeneratedImageStatus::Rejected,
		]);
	}

	/**
	 * A published image whose creator has already been notified — used to assert
	 * the dedupe guard doesn't send a second mail.
	 */
	public function notified(): static
	{
		return $this->published()->state(fn (array $attributes) => [
			'notified_at' => now(),
		]);
	}
}
