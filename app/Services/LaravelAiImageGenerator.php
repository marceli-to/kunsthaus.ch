<?php

namespace App\Services;

use App\Contracts\ImageGeneratorContract;
use Laravel\Ai\Image;

/**
 * Image generator backed by the first-party Laravel AI SDK.
 *
 * Provider + model are driven by config (`image.provider` / `image.model`),
 * so swapping from OpenAI gpt-image-1 to e.g. Gemini Imagen or xAI is a config
 * change only — the controller and frontend never reference a provider.
 */
class LaravelAiImageGenerator implements ImageGeneratorContract
{
    public function __construct(
        protected string $provider = 'openai',
        protected string $model = 'gpt-image-1',
        protected string $quality = 'medium',
        protected int $timeout = 120,
    ) {}

    public function generate(string $prompt): string
    {
        $response = Image::of($prompt)
            ->square()          // -> 1024x1024 for OpenAI
            ->quality($this->quality)
            ->timeout($this->timeout)
            ->generate(provider: $this->provider, model: $this->model);

        // Raw decoded image bytes (PNG for gpt-image-1).
        return $response->firstImage()->content();
    }
}
