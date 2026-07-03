# JAtelier — Progress & Handoff (2026-07-03)

Working log for the **JAtelier** campaign image generator (the "Gestalten Sie
Ihr persönliches JA" feature). Companion to `docs/kunsthaus-build-brief-v7.md`
(the original brief). Read this first when picking the work back up.

---

## 1. What JAtelier is

A visitor uploads a portrait, frames it, picks a hand-painted "JA" style, enters
their name + email, and the app composites a **1080×1350** shareable image:

```
┌───────────────────────────┐  white canvas, 1080×1350
│      [ portrait 16:10 ]    │  ← user-framed (pan-only crop) landscape photo
│                           │
│         ┌─────────┐        │
│         │   JA    │        │  ← chosen painting-style tile (smaller, below)
│         └─────────┘        │
│    VORNAME NAME SAGT       │  ← Futura PT Medium, accent blue, uppercase
│   ┌────────────────────┐   │
│   │ JA ZUM KUNSTHAUS.  │   │  ← fixed footer logo lockup
│   │    ZU ZÜRICH.      │   │
│   └────────────────────┘   │
└───────────────────────────┘
```

No AI anywhere (client requirement). Deterministic GD compositing.

---

## 2. Decisions made today (with rationale)

| Topic | Decision | Why |
|---|---|---|
| **Flow** | `erstellen` renders a **preview only** (no DB record). A later confirm step (Phase 4, not built) captures a **required consent checkbox** → stores + delivers. | Brief requires FADP consent at submit; email is collected up front and held client-side until confirm. |
| **Fields** | `Name` → `last_name` (**required**), `Vorname` → `first_name` (required), `E-Mail` (required). | Matches the design screenshot (both names + email up front). |
| **Background removal** | Optional toggle, client-side `@imgly`, config-gated. | Brief; keeps raw photo on device. |
| **Style picker** | Native styled `<select>`. | Matches screenshot; simplest/robust. |
| **Output format** | "Image #2" format (stacked: portrait → JA card → name → footer), **not** the polished committee template (person physically holding a sign — impossible to auto-generate). | Client picked it. |
| **Portrait framing** | **User-framed, pan-only crop** into a **16:10** rectangle; **no server auto-crop**. Live JA overlay only shown when the JA box overlaps the portrait (it doesn't in the current stacked layout, so it's WYSIWYG). | Client didn't want auto-crop; wanted a landscape rectangle and a smaller JA. |
| **Config delivery** | Geometry lives once in `config/composite.php`; the Vue island fetches it from `GET /api/generator`. **Nothing serialised in Antlers.** | Server builds the image so it must own the geometry; crop frame must match it → single source of truth. |

---

## 3. Architecture

### Backend (Laravel/Statamic)
- `config/composite.php` — **single source of truth** for the composite layout
  (canvas, portrait box, ja box, name, footer, upload limits, `bg_removal`).
  Change numbers here → both the rendered image **and** the browser crop frame follow.
- `app/Services/CompositeService.php` — GD compositing (Intervention, GD driver).
  Portrait cover-fit into its box, JA card cover-fit on top/below, name line
  (`{Vorname} {Name} SAGT`, uppercase, accent blue, `fitFontSize` auto-shrink),
  footer logo contain-fit. EXIF oriented + stripped (re-encode).
- `app/Services/JaStyleRepository.php` — reads the `ja_styles` Statamic
  collection; `all()` (dropdown) + `pathForKey()` (server resolves the PNG from
  the key — never trusts a client path).
- `app/Http/Controllers/GeneratorController.php` — `GET /api/generator` →
  `{ styles, bg_removal, geometry:{portrait,ja} }` (boot payload for the island).
- `app/Http/Controllers/GenerateController.php` — `POST /api/generate`
  (throttle 10/min): validates portrait + style + both names, resolves the JA
  PNG server-side, runs `CompositeService`, returns `{ preview_id, url }`.
  Sanitises names (UGC). **No record created yet** (that's Phase 4).
- `routes/api.php` — the two routes above. (`/api/ja-styles` + `JaStyleController`
  were removed.)

### Frontend
- `resources/views/components/blocks/jatelier.antlers.html` — the blue
  full-bleed section: editorial left column (wordmark, lead, intro, numbered
  steps) + right column mount `<div id="image-generator">` (no config in Antlers).
- `resources/fieldsets/jatelier.yaml` — CP fields for the editorial column.
  Registered as a `jatelier` set in `resources/blueprints/collections/pages/page.yaml`
  and dispatched in `resources/views/components/blocks.antlers.html`.
- `resources/js/app.js` — `createApp(ImageGenerator).mount(el)` (trivial).
- `resources/js/components/ImageGenerator.vue` — the island. Fetches config on
  mount; personal-data fields; **pan-only crop** stage (drag to reposition, no
  zoom) that exports the framed crop client-side (canvas → PNG if bg-removed for
  alpha, else JPEG) and uploads it; style dropdown; `@imgly` cutout; preview +
  download. **DEV prefill** (Stadelmann/Marcel/marcel.stadelmann@gmail.com) fires
  **only on `.test`/`localhost`** hosts.

### Content / assets
- `content/collections/ja_styles/*.md` — **9 style entries** (order 1–9):
  airbrush, aquarell, farbstift, filzstift, oelfarbe (Ölfarbe), schwammtechnik,
  siebdruck, tropftechnik, tusche.
- `content/assets/ja_styles.yaml` — the `ja_styles` asset container (disk
  `ja_styles` → `storage/app/public/ja-styles/`).
- `storage/app/public/ja-styles/*.png` — the 9 tiles (684×684). **Committed**
  (whitelisted in `storage/app/public/.gitignore`), so they travel/deploy.
  Source originals: `~/Desktop/Kunsthaus/Stile_JA_Leinwand_def/` (this laptop only).
- `resources/composite/footer-logo.png` — footer lockup (1362×276, from the
  client's `logo.png`). GD can't render SVG, so it's pre-rasterised.
- `resources/fonts/FuturaPT-Medium.ttf` — the ONLY font now (name line). Legacy
  Fraunces/InstrumentSans + README removed.

---

## 4. Composite geometry (current, in `config/composite.php`)

```
canvas   1080 × 1350, white
portrait 900 × 563  @ (90, 40)     # 16:10 landscape, user-framed
ja       460 × 460  @ (310, 650)   # smaller, centred below portrait (stacked)
name     centre x=540, y=1150, Futura PT, accent blue #0000ff, uppercase, +" SAGT"
footer   480 × 100  @ (300, 1225)  # contain-fit lockup
```
Layout is **stacked** (JA below portrait, no overlap). If you make the JA box
overlap the portrait box again, the Vue crop UI auto-shows the live JA overlay.

---

## 5. Status

### ✅ Done
- Landing block + generator island, styled to the blue design.
- Full preview pipeline end-to-end (`/api/generate`), verified with real styles.
- 9 real style tiles imported; footer logo; Futura PT name line.
- User-framed 16:10 pan-only crop (client export, no server auto-crop).
- Config single-sourced in `config/composite.php` via `/api/generator`.
- Long-name auto-shrink; clean error handling; rate limit.

### ⏳ Pending / next steps
1. **Try the crop with real faces** and nudge geometry in `config/composite.php`
   if the portrait/JA sizes or spacing feel off (it's just numbers).
2. **Phase C/D — confirm → store → deliver** (brief Phase 4):
   - After preview, a "Verwenden" step: preview + **required consent checkbox**.
   - `POST /api/submit` → validate consent → migration + `GeneratedImage` model
     → promote temp preview to permanent, store source portrait, `consent_at`,
     `status = submitted`.
   - Offer **download**, **email-to-me**, and **share** (WhatsApp/social).
3. **Phase 5 — moderation** (Runway resource + reviewer checklist).
4. **Phase 6 — publish notification** (mail, cron/`sync`, dedupe guard).
5. **Phase 7 — public gallery** (if in scope) + privacy/consent wording.
6. **Phase 8 — prune-previews** scheduled command; README/deploy notes.
7. Optional polish: **letter-spacing** on the name line (Intervention has no
   native tracking → manual glyph spacing) to match the example.

### ⚠️ Flags
- `@imgly/background-removal` is **AGPL-3.0** — buy the commercial licence or
  swap to a permissive model (BiRefNet/MODNet) before production.
- The `jatelier` block currently sits **before the FAQ** on the home page (a CP
  re-save moved it there). Drag it right after the intro in the CP if preferred.
- The name-line letter-spacing is not yet applied (see polish item).

---

## 6. How to run / test

```bash
# dev
npm run dev            # or: npm run build   (assets are committed under public/build)
# site: https://kunsthaus.ch.test  (Herd/Valet)

# after content/blueprint/config changes:
php please stache:clear
php artisan config:clear

# quick composite smoke test (uses a stand-in portrait):
curl -sk -X POST https://kunsthaus.ch.test/api/generate \
  -F "portrait=@public/assets/supporter/reto-zogg.png" \
  -F "ja_style=oelfarbe" -F "first_name=Andreas" -F "last_name=Hugi"
```
Test in the browser: JAtelier section → upload photo → drag to frame the 16:10
crop → pick a style → `erstellen` → preview + download. (Form prefills on
`.test`/localhost.)

> The supporter PNGs (e.g. `reto-zogg.png`) used as test portraits already have
> baked-in "… SAGT JA ZU …" text + a cream background — that's the stand-in
> image, not the generator. Real uploads are on white with no text.
