# Ja zum Kunstmuseum — Fundraising Landing Page (Prototype)

A single-page fundraising landing page for an art museum. Mostly static marketing
content plus **one interactive feature**: a box where a visitor describes how their
personal *"Ja zum Kunstmuseum"* poster should look, and the app generates it with an
AI image model. The result can be viewed and downloaded.

> **Prototype** — prioritises a clean end-to-end flow over production hardening.
> Deferred production concerns are marked with `// PROD:` comments in the code.

## Stack

- **Laravel 13** (PHP 8.4) under Laravel Herd
- **Vue 3** (`<script setup>`) mounted as an island into a Blade view via Vite
- **Tailwind CSS v4**
- Image generation via the first-party **Laravel AI SDK** (`laravel/ai`) →
  OpenAI **`gpt-image-1`** at 1024×1024

## How it works

```
Vue <ImageGenerator>  ──POST /api/generate-image { prompt }──▶  ImageGenerationController
                                                                  ├─ validate (3–300 chars)
                                                                  ├─ throttle 10/min + daily cap
                                                                  ├─ PromptBuilder (brand template + denylist)
                                                                  └─ ImageGeneratorContract ─▶ Laravel AI SDK ─▶ OpenAI
                                                                       │
                       ◀── { id, url } ── stores PNG to storage/app/public/generations/{uuid}.png
```

The OpenAI call sits behind `App\Contracts\ImageGeneratorContract`. Swapping providers
(e.g. to Gemini Imagen or xAI) is a **single change in `config/image.php`** — controller
and frontend never reference a concrete provider.

## Setup

```bash
composer install
npm install

cp .env.example .env        # if you don't already have a .env
php artisan key:generate
php artisan migrate
php artisan storage:link
```

### Required environment variables

```dotenv
OPENAI_API_KEY=sk-...       # OpenAI key with gpt-image-1 access
IMAGE_PROVIDER=openai       # Laravel AI SDK provider
IMAGE_MODEL=gpt-image-1     # image model
IMAGE_QUALITY=medium        # low | medium | high (see note below)
IMAGE_DAILY_CAP=100         # hard global generations/day ceiling
```

> **Quality vs. timeout:** generation is **synchronous**, so it must finish within
> Herd's FastCGI read timeout (~30s). `gpt-image-1` at `high` quality regularly runs
> longer and returns a **502** from nginx; `medium` (~18–20s) and `low` (~17s) fit
> comfortably and look great. Keep `IMAGE_QUALITY=medium` unless/until generation is
> moved to a queue (`[PROD]`), after which `high` becomes safe.

The app is served by Herd at **https://kunstmuseum.ch.test**. The database is SQLite
(`database/database.sqlite`), created automatically by `php artisan migrate`.

## Run

```bash
npm run dev      # Vite dev server (HMR) — keep running while developing
# Herd serves the PHP app; visit https://kunstmuseum.ch.test
```

For a production-style build of the assets:

```bash
npm run build
```

## Testing the flow

1. Open **https://kunstmuseum.ch.test** — landing content + generator are visible.
2. Type a description (e.g. *"ein Sonnenaufgang über dem Museum mit fröhlichen Menschen"*)
   and click **Bild generieren**. After a short wait (~5–15s) the AI image appears.
3. Click **Bild herunterladen** to download the PNG, or **Neues Bild gestalten** to reset.

Edge cases to try:
- **Too short / empty** input → inline validation error, no API call.
- **Off-brand input** (denylist) → friendly 422, no API call.
- **Rapid repeats** → `throttle:10,1` returns *Too Many Attempts*.
- **Daily cap** → past `IMAGE_DAILY_CAP` generations, a friendly "Tageslimit erreicht" message.
- **API failure** (e.g. missing key) → clean inline error, never a stack trace.

## Key files

| Concern | File |
| --- | --- |
| Landing page | `resources/views/landing.blade.php` |
| Vue component | `resources/js/components/ImageGenerator.vue` |
| Controller | `app/Http/Controllers/ImageGenerationController.php` |
| Prompt template + denylist | `app/Services/PromptBuilder.php` |
| Generator contract | `app/Contracts/ImageGeneratorContract.php` |
| Laravel AI implementation | `app/Services/LaravelAiImageGenerator.php` |
| Provider/model/cap config | `config/image.php` |
| API route | `routes/api.php` |

## Deferred (`[PROD]`, out of scope)

Marked with `// PROD:` in code where they'd attach:

- Queued/async generation (job + status polling or websockets)
- Persistent shareable URLs + dynamic OG/social-card meta tags
- Full content moderation (OpenAI moderation endpoint) + Turnstile/captcha
- Real branding, copy, fonts, legal/imprint, analytics
- Cost dashboards / per-user accounting
