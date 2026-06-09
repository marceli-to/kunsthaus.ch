<?php

namespace App\Contracts;

interface ImageGeneratorContract
{
    /**
     * Generate an image from a (already wrapped) prompt and return the raw
     * binary image data (e.g. PNG bytes). Throwing is fine — the controller
     * is responsible for turning failures into clean JSON errors.
     */
    public function generate(string $prompt): string;
}
