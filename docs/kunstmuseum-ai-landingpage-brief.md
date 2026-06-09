# Build Brief: "Ja zum Kunstmuseum" — Fundraising Landing Page with AI Image Generation

## Goal
Prototype a single-page fundraising landing page for an art museum. The page is mostly static marketing content, with **one interactive feature**: a chat-style box where a visitor describes how their personal *"Ja zum Kunstmuseum"* image should look, and the app generates it with an AI image model. The visitor can then view, download, and (stretch) share the result.

This is a **prototype** — prioritise a working end-to-end flow and clean structure over production hardening. Where a production concern is deliberately deferred, it is marked `[PROD]` below; implement a minimal version or stub, do not build the full thing.

## Tech stack (use exactly this unless blocked)
- **Laravel 13** (PHP 8.4; 8.3 is the minimum), running under Laravel Herd locally.
- **Vue 3** (Composition API, `<script setup>`) mounted into a Blade view via Vite.
- **Tailwind CSS** for styling.
- **Inertia is NOT required** — a single Blade page with a mounted Vue island is simpler for this. Use that approach.
- Image generation via **OpenAI `gpt-image-1`** using the `openai-php/laravel` package. Abstract the call behind a service class so the provider can be swapped later (e.g. to Google Imagen 4 or FLUX) without touching the controller or frontend.
- **Optional:** Laravel 13 ships a first-party **Laravel AI SDK**. Check whether it exposes a clean image-generation primitive; if it does and it's simpler than `openai-php/laravel`, use it instead — but only if it doesn't compromise the swappable-provider requirement below. If unsure, default to `openai-php/laravel`.

> Note: the final site may live in Statamic, but build this as plain Laravel + Vue. The feature is a controller + service + Vue component that drops into any front-end chrome later.

## Architecture / request flow
```
Vue component (prompt box)
   → POST /api/generate-image  { prompt: string }
       → ImageGenerationController
           → validate + throttle
           → PromptBuilder (wraps user text in a branded template)
           → ImageGenerator service (calls OpenAI gpt-image-1)
           → store image to storage/app/public/generations
           → return { id, url }
   ← Vue renders the image with download button
```
Keep the request **synchronous** for the prototype with a clear loading state on the frontend. (Async/queued generation is `[PROD]` — see below.)

## Build in phases. Verify each phase works before moving on.

### Phase 1 — Scaffold
- Fresh Laravel 13 app (or confirm with me if one exists in the cwd).
- Install + configure Vite, Vue 3, Tailwind.
- Single route `/` rendering a Blade view with a mounted Vue app.
- Placeholder landing content: hero headline "Ja zum Kunstmuseum", a short paragraph, and a section that will hold the generator. Use sensible warm/cultural-institution styling — neutral, not garish. Real copy and branding are out of scope.

### Phase 2 — Image generation backend
- Install `openai-php/laravel`; add `OPENAI_API_KEY` to `.env` and `.env.example`.
- `app/Services/PromptBuilder.php`: takes raw user input, returns a wrapped prompt. Template (tune freely):
  > "A celebratory, artistic poster illustration in a warm, painterly style supporting an art museum. The poster features: {USER_INPUT}. Include the text 'Ja zum Kunstmuseum' tastefully integrated. High quality, gallery-worthy, suitable for a public cultural campaign."
- `app/Services/ImageGenerator.php`: interface `ImageGeneratorContract` + an `OpenAiImageGenerator` implementation calling `gpt-image-1` at 1024×1024. Bind in a service provider. Returns raw image data.
- `app/Http/Controllers/ImageGenerationController.php`: `POST /api/generate-image`.
  - Validate `prompt`: required, string, min 3, max 300 chars.
  - Build prompt → generate → save PNG to `storage/app/public/generations/{uuid}.png` → return `{ "id": uuid, "url": "/storage/generations/{uuid}.png" }`.
  - Run `php artisan storage:link`.
- Wrap the API call in try/catch; return a clean JSON error (`{ "error": "..." }`, HTTP 422/500) the frontend can display.

### Phase 3 — Guardrails (minimal but present)
These exist because this endpoint spends real money per call and is public. Implement the **light** version now:
- **Rate limiting:** apply `throttle:10,1` (10/min per IP) to the route, plus a hard global daily cap — a simple cache counter (`Cache::increment`) that returns a friendly "daily limit reached" error past N generations. Set N low (e.g. 100) for the prototype.
- **Input guardrail:** a small denylist check in `PromptBuilder` that rejects obviously off-brand/unsafe input with a 422 before any API call. Keep it simple; rely on the provider's own safety filter for the rest.
- `[PROD]` Full moderation (OpenAI moderation endpoint pre-check), bot protection (Cloudflare Turnstile): **stub or skip**, leave a `// PROD:` comment marking where it goes.

### Phase 4 — Vue frontend
- A `<ImageGenerator>` component:
  - Textarea / input with placeholder e.g. "Beschreibe dein Ja zum Kunstmuseum…", a char counter, and a generate button.
  - Loading state: disable input, show an animated placeholder (~5–15s generations are normal — make the wait feel intentional, not broken).
  - On success: show the image, a **Download** button, and a "generate another" reset.
  - On error: show the returned error message inline, re-enable the form.
- Clean, responsive Tailwind. Mobile-first.

### Phase 5 — Polish
- Empty/loading/error/success states all handled.
- Basic README section: env vars, `npm run dev`, how to test the flow.

## `[PROD]` — explicitly OUT OF SCOPE for this prototype
Do not build these. Just leave clear `// PROD:` markers where they'd attach:
- Queued/async generation (job + job-status polling or Reverb/Echo websockets).
- Persistent shareable URLs + dynamic OG/social-card meta tags for viral sharing.
- Full content moderation pipeline + Turnstile/captcha.
- Real branding, copy, fonts, legal/imprint, analytics.
- Cost dashboards / per-user accounting.

## Environment variables needed
```
OPENAI_API_KEY=sk-...
IMAGE_DAILY_CAP=100
```

## Acceptance criteria (prototype is "done" when)
1. `/` loads with the landing content and the generator visible.
2. Typing a description and clicking generate returns a real AI image in the UI within a normal wait, with a working loading state.
3. The generated image can be downloaded.
4. Rapid repeat requests are throttled with a friendly message; the daily cap works.
5. Empty/too-short input and API failures show clean inline errors, never a stack trace.
6. The OpenAI call sits behind `ImageGeneratorContract` so swapping providers touches one file.

## How to work
- Work phase by phase; after each phase, briefly state what you did and how to verify it, then continue.
- Prefer small, readable files over cleverness — this is a prototype I'll iterate on.
- If a decision is genuinely ambiguous (e.g. an existing app in the cwd, or a missing API key), pause and ask rather than guessing destructively.
- Don't install dependencies beyond those listed without flagging why.
