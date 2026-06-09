<?php

namespace App\Services;

use App\Exceptions\DisallowedPromptException;
use Illuminate\Support\Str;

class PromptBuilder
{
    /**
     * Obviously off-brand / unsafe terms rejected before any (paid) API call.
     * Intentionally tiny — the provider's own safety filter handles the rest.
     *
     * PROD: replace with a real moderation pre-check (e.g. OpenAI moderation
     * endpoint) and a maintained policy list.
     *
     * @var list<string>
     */
    protected array $denylist = [
        'nsfw', 'nude', 'naked', 'porn', 'sex', 'gore', 'blood',
        'nazi', 'hitler', 'swastika', 'isis', 'terrorist',
        'kill', 'weapon', 'gun',
    ];

    /**
     * Wrap raw visitor input in the branded campaign template.
     *
     * @throws DisallowedPromptException when the input hits the denylist.
     */
    public function build(string $userInput): string
    {
        $userInput = trim($userInput);

        $this->guard($userInput);

        // NOTE: we intentionally do NOT ask the model to render any text. The
        // 'Ja zum Kunstmuseum' headline is composited on top of the artwork
        // afterwards (see ImageGenerator.vue) so it is always legible, correctly
        // spelled and never cropped — guarantees diffusion models can't make.
        // We also nudge the model to keep the lower third calm (so the overlay
        // stays readable) and to keep margins (so nothing crops at the edges).
        return "A celebratory, artistic illustration in a warm, painterly style "
            ."supporting an art museum. The artwork features: {$userInput}. "
            ."No text, no lettering, no words anywhere in the image. Leave the lower "
            ."third visually calm and uncluttered. Centered composition with "
            ."comfortable margins so nothing important is cropped at the edges. "
            ."High quality, gallery-worthy, suitable for a public cultural campaign.";
    }

    /**
     * Reject obviously off-brand or unsafe input.
     *
     * @throws DisallowedPromptException
     */
    protected function guard(string $userInput): void
    {
        $haystack = Str::lower($userInput);

        foreach ($this->denylist as $term) {
            if (str_contains($haystack, $term)) {
                throw new DisallowedPromptException(
                    'Bitte beschreibe ein positives, museumstaugliches Motiv.'
                );
            }
        }
    }
}
