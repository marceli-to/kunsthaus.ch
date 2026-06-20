<?php

namespace App\Http\Controllers;

use App\Services\CompositeService;
use App\Services\JaStyleRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class GenerateController extends Controller
{
    /**
     * Deterministic composite pipeline (NO AI, no DB record yet).
     *
     * Receives the portrait (possibly already background-removed in the
     * browser), the chosen "JA" style key and the visitor's name; composites
     * them onto the campaign template via GD and returns a preview URL.
     */
    public function __invoke(
        Request $request,
        JaStyleRepository $styles,
        CompositeService $composite,
    ): JsonResponse {
        $cfg = config('composite.upload');

        $validated = $request->validate([
            'portrait' => [
                'required', 'image',
                'mimes:'.implode(',', $cfg['mimes']),
                'max:'.$cfg['max_kb'],
                'dimensions:min_width='.$cfg['min_dimension'].',min_height='.$cfg['min_dimension'],
            ],
            'ja_style' => ['required', 'string', 'max:64'],
            'first_name' => ['required', 'string', 'max:40'],
            'last_name' => ['nullable', 'string', 'max:40'],
        ], [
            'portrait.required' => 'Bitte wähle ein Foto.',
            'portrait.image' => 'Die Datei muss ein Bild sein.',
            'portrait.dimensions' => 'Das Bild ist zu klein (min. '.$cfg['min_dimension'].'px).',
            'portrait.max' => 'Das Bild ist zu gross (max. '.round($cfg['max_kb'] / 1024).' MB).',
            'first_name.required' => 'Bitte gib einen Vornamen ein.',
        ]);

        // Resolve the JA PNG server-side from the known key — never trust a
        // client-supplied file path.
        $jaPath = $styles->pathForKey($validated['ja_style']);
        if ($jaPath === null) {
            return response()->json([
                'message' => 'Unbekannter «JA»-Stil.',
                'errors' => ['ja_style' => ['Bitte wähle einen verfügbaren Stil.']],
            ], 422);
        }

        // Sanitise the free-text name — it is published UGC.
        // PROD: replace with a maintained profanity list / moderation service.
        $firstName = $this->sanitiseName($validated['first_name']);
        $lastName = $this->sanitiseName($validated['last_name'] ?? '');

        try {
            [$previewId, $url] = $composite->build(
                portraitPath: $request->file('portrait')->getRealPath(),
                jaPngPath: $jaPath,
                firstName: $firstName,
                lastName: $lastName,
            );
        } catch (Throwable $e) {
            Log::error('Composite generation failed', ['error' => $e->getMessage()]);

            return response()->json([
                'message' => 'Das Bild konnte nicht erstellt werden. Bitte versuche es erneut.',
            ], 500);
        }

        return response()->json([
            'preview_id' => $previewId,
            'url' => $url,
        ]);
    }

    /**
     * Trim, collapse whitespace and strip control/markup characters from a
     * name so it renders cleanly and can't inject markup downstream.
     */
    private function sanitiseName(string $value): string
    {
        $value = strip_tags($value);
        $value = preg_replace('/[\x00-\x1F\x7F<>]/u', '', $value) ?? '';

        return trim(preg_replace('/\s+/u', ' ', $value) ?? '');
    }
}
