<?php

namespace App\Http\Controllers;

use App\Contracts\ImageGeneratorContract;
use App\Exceptions\DisallowedPromptException;
use App\Services\PromptBuilder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

class ImageGenerationController extends Controller
{
    public function __invoke(
        Request $request,
        PromptBuilder $promptBuilder,
        ImageGeneratorContract $generator,
    ): JsonResponse {
        $validated = $request->validate([
            'prompt' => ['required', 'string', 'min:3', 'max:300'],
        ]);

        // PROD: full content moderation (OpenAI moderation endpoint) + bot
        // protection (Cloudflare Turnstile) belong here, before we spend money.

        // Hard global daily cap — this endpoint costs real money per call.
        if ($this->dailyCapReached()) {
            return response()->json([
                'error' => 'Das Tageslimit für Bildgenerierungen ist erreicht. Bitte versuche es morgen erneut.',
            ], 429);
        }

        try {
            $prompt = $promptBuilder->build($validated['prompt']);
        } catch (DisallowedPromptException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }

        try {
            $imageData = $generator->generate($prompt);
        } catch (Throwable $e) {
            Log::error('Image generation failed', ['exception' => $e]);

            return response()->json([
                'error' => 'Die Bildgenerierung ist fehlgeschlagen. Bitte versuche es erneut.',
            ], 500);
        }

        $id = (string) Str::uuid();
        $path = "generations/{$id}.png";
        Storage::disk('public')->put($path, $imageData);

        $this->incrementDailyCount();

        return response()->json([
            'id' => $id,
            'url' => Storage::disk('public')->url($path),
        ]);
    }

    /**
     * Whether today's global generation cap has been reached.
     */
    protected function dailyCapReached(): bool
    {
        return Cache::get($this->dailyCacheKey(), 0) >= config('image.daily_cap');
    }

    /**
     * Record one successful generation against today's cap.
     */
    protected function incrementDailyCount(): void
    {
        $key = $this->dailyCacheKey();

        // Ensure the counter exists (with a ~2 day TTL) before incrementing.
        Cache::add($key, 0, now()->addDays(2));
        Cache::increment($key);
    }

    protected function dailyCacheKey(): string
    {
        return 'image_generations:'.now()->toDateString();
    }
}
