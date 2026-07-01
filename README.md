# Ja zum Kunsthaus — Campaign Image Generator (Prototype)

A mostly-static **Statamic 6** landing page for the *Kunsthaus* vote/fundraising
campaign with one interactive feature: a visitor uploads a portrait, picks a
**"JA" painting technique**, enters their name, and the app composites a
shareable poster image — **deterministically, with no generative AI anywhere**.

> **Prototype scope (Build Brief v7, Phases 1–2).** Upload → composite → preview.
> No DB record, moderation, email or live gallery yet — those are later phases,
> stubbed with `// PROD:` / `PROD:` markers. See `docs/kunsthaus-build-brief-v7.md`.

## Stack

- **Laravel 13** (PHP 8.4) under Laravel Herd
- **Statamic 6** (flat-file content + eloquent CP users) — landing page, JA-styles
  collection, Control Panel
- **Vue 3** (`<script setup>`) island mounted into the Antlers `home` template via Vite
- **Tailwind CSS v4**
- **Intervention Image (GD driver)** for server-side compositing — no Imagick dependency
- **@imgly/background-removal** for the optional **client-side** (in-browser) cutout

## What works in this prototype

- Statamic landing page at `/` (hero, "why", generator, **placeholder** gallery) and CP at `/cp`.
- `ja_styles` Statamic collection (label + transparent-PNG asset + order) → drives the dropdown.
- Generator island: drag/drop portrait picker, optional in-browser background removal
  (progress UI, raw photo never leaves the device), JA-style picker with thumbnails, Vorname/Name.
- `POST /api/generate`: validates upload, **respects EXIF orientation and strips EXIF/GPS**,
  sanitises the name, composites portrait + JA + name + branding onto the template canvas (GD),
  returns `{ preview_id, url }`. Synchronous, clean JSON errors, rate-limited 10/min/IP.
- `GET /api/ja-styles`: JSON list for the dropdown.

## Local setup

```bash
composer install
npm install
cp .env.example .env && php artisan key:generate
php artisan migrate
php artisan storage:link
npm run build           # or: npm run dev

# Statamic CP assets (public/vendor) are gitignored — (re)publish them on deploy:
php artisan vendor:publish --tag=statamic --force

# Create a Control Panel user (or use the seeded one if present):
php artisan statamic:make:user
```

Visit `https://kunsthaus.ch.test/` (Herd) and `/cp` for the Control Panel.

## ⚠️ Design / asset deliverables (placeholders flagged, not guessed)

- **Composite layout** — canvas size, zone positions, fonts and colours live in
  `config/composite.php` and are **placeholders**. Update that one file when the design lands.
- **"JA" style PNGs** — `storage/app/public/ja-styles/*.png` are watermarked "Platzhalter"
  placeholders. The client/designer delivers the real transparent PNGs; replace them in the
  CP (collection *JA-Stile*).
- **Bundled fonts** — `resources/fonts/` ships OFL fonts (Fraunces, Instrument Sans) so the
  GD composite renders on shared hosting; swap for the final campaign typefaces.

## Licence note — background removal

`@imgly/background-removal` is **AGPL-3.0 with a paid commercial option**. This is a paid
client deliverable, so before production either buy the @imgly commercial licence **or** swap
to a permissively-licensed model (BiRefNet/MODNet, MIT/Apache). The cutout is gated by the
`VITE_ENABLE_BG_REMOVAL` flag (`config/app.php` → `bg_removal_enabled`) so the swap is contained.

## Not yet built (later phases — see brief)

Confirm/submit + `GeneratedImage` record + immediate download/email (Phase 4), Runway moderation
in the CP (Phase 5), publish notification via cron-driven queue (Phase 6), live gallery + consent
wording (Phase 7), Turnstile/captcha. All marked in code with `PROD:`.

## Deployment target

Swiss shared host (Hostpoint/Cyon/Metanet): docroot → `public/`, PHP ≥ 8.4, `memory_limit ≥ 256M`,
compositing via GD, assets built locally and deployed, scheduler + queue driven by cron
(no persistent daemon). Details in the brief's *Deployment / hosting* section.
