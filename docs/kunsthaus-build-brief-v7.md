# Build Brief v7: "Ja zum Kunsthaus" — Campaign Image Generator (no AI)

> Supersedes v6. v7 is the **deployment-ready** version: background removal is locked to a **client-side (in-browser)** approach, and a **Deployment / hosting** section pins the target to a Swiss shared host with the resulting constraints (no queue daemon, cron-driven scheduler, GD compositing). Everything else is unchanged from v6.
>
> Reference (a comparable Swiss campaign that shipped this mechanism): https://kunstmuseum-ja.ch/mitmachen/ — the client considers its execution clunky; a clean Vue flow is the chance to out-craft it.

## Goal
A mostly-static Statamic landing page for an art museum (the *Kunsthaus*) fundraising/vote campaign. A visitor uploads a portrait, selects a "JA" rendered in a chosen **painting technique** from a dropdown, and enters their name; the app composites portrait + chosen "JA" + name (+ fixed branding) into a shareable image. The visitor can download it and/or have it emailed; the image is stored in the backend for review, and approved images may appear in a public supporter gallery. **No generative AI anywhere** — the Kunsthaus explicitly does not want to promote AI.

## Scope history (how we got here)
- **v1–v5:** AI chatbot generating the "JA" — dropped; the Kunsthaus rejects AI on the campaign page.
- **v6:** replaced AI with a dropdown of pre-made painting-technique "JA" assets + deterministic name rendering.
- **v7:** background removal locked to **client-side**; Swiss shared-hosting deployment pinned.

## Final image layout
A fixed template canvas compositing these layers:
- the **portrait** (optionally background-removed onto white, done in the browser),
- the **selected "JA"** style asset,
- the **name** (Vorname/Name) as rendered text,
- **fixed campaign branding/slogan** ("Ja zum Kunsthaus").

**Exact layout (canvas size, zone positions, fonts, colours) is a design deliverable.** Build against a clearly-marked placeholder if it isn't ready, and flag it — don't guess positions.

## Core architectural decision (unchanged)
**One application.** Statamic 6 runs inside a normal Laravel app; the landing page, the image pipeline, the moderation queue, and the public gallery all live in one Laravel 13 codebase, one database, one auth system. No Breeze, no separate backend, no cross-API fetch. Generated-image records are a plain Eloquent model surfaced in the Control Panel via Runway.

## Tech stack (use exactly this unless blocked)
- **Laravel 13** (PHP 8.4; 8.3 minimum), deployed to a **Swiss shared host** (see Deployment).
- **Statamic 6** (`statamic/cms ^6.0`; supports Laravel 13). Carbon 3 required. CP uses Inertia 2, not 3 — don't scaffold via Laravel's Inertia-3 starter kits.
- **Vue 3** (`<script setup>`) island + **Tailwind CSS v4**.
- **Image compositing:** **Intervention Image using the GD driver** — do NOT depend on Imagick being installed on shared hosting.
- **Background removal: client-side, in the browser** — `@imgly/background-removal` or Transformers.js running a **permissively-licensed** model (BiRefNet/MODNet, MIT/Apache; avoid non-commercial models like RMBG-1.4). Runs on the visitor's device: zero server cost, no external processor, raw photo stays local. No server-side ML (incompatible with shared hosting). Hosted API (Photoroom) is documented as a fallback only.
- **Queue:** database driver, processed **without a long-running daemon** — `sync`, or a cron-triggered `queue:work --stop-when-empty` (shared hosting has no persistent workers). See Deployment.
- **Transactional email provider** (Postmark / Resend / SES), not raw SMTP.
- **No AI / LLM / generative image dependencies of any kind.**

## "JA" styles (design + content)
- The designer produces one **transparent PNG per painting technique** (oil, watercolour, charcoal, ink, …) of the word "JA".
- Manage these as a small **Statamic collection** (or Runway resource): each entry = label + asset, so the client can add/reorder/replace styles in the CP without a deploy. The frontend dropdown is populated from it with a thumbnail preview per option.

## Background removal — locked to client-side
- **Approach:** the cut-out runs **in the visitor's browser** (`@imgly/background-removal` or Transformers.js + a permissive model). Your server does nothing for it; only the finished cut-out is uploaded. This fits shared hosting (no server ML), costs nothing per image, and keeps the raw photo on the device.
- **Make it user-optional:** a "remove background / place on white" toggle. Always allow keeping the original background as a fallback.
- **Caveats to handle:**
  - *Weak devices:* many users are on mid/low-end phones without WebGPU (WASM-on-CPU fallback — a few seconds, not a crash). Show a progress indicator; **test on a cheap Android** before committing.
  - *Model licence:* use an MIT/Apache-licensed model (BiRefNet/MODNet); `@imgly` is AGPL with a paid commercial option; RMBG-1.4 is non-commercial. Confirm the licence is valid for commercial use — this is a paid client deliverable.
  - First-load model download (cached thereafter), a few–tens of MB.
- **Fallback (only if browser quality/perf disappoints in testing):** Photoroom hosted API — works on shared hosting (just an HTTPS call) but reintroduces a per-image cost, an external processor (needs an FADP data-processing agreement + privacy-notice disclosure), and the external-AI-vendor optics. Keep behind a config flag if implemented.

## The creation pipeline (deterministic — no AI)
Synchronous; show a brief loading state.
1. **Portrait** — chosen in the browser. If the user enabled background removal, the **cut-out happens client-side**; only the processed image is uploaded. Server-side: validate type/size/dimensions; **respect EXIF orientation**; **strip EXIF metadata** before publishing (removes GPS). Store to temp disk by UUID.
2. **Select "JA" style** — from the dropdown (assets above).
3. **Name** — user enters Vorname/Name; rendered as text in the template's name zone (font/placement from design). **Sanitise/profanity-check** this free-text field — it's published UGC.
4. **Composite** — layer portrait + chosen "JA" + name + branding onto the template canvas (Intervention/GD); flatten to the preview on temp disk.

## User flow — state machine
1. **(no record yet)** — Visitor picks a photo (optionally background-removed in-browser), a "JA" style, and a name → composite renders → visitor **sees the preview**. No public exposure, no DB record yet.
2. **`submitted`** — Visitor confirms ("use it"). Minimal form: **email (required)** + **consent checkbox**. On submit: promote temp files to permanent storage, create the `GeneratedImage` record (`status = submitted`, name, `user_email`, `consent_at`). **The user can download the image now and/or have it emailed immediately.**
3. **`published`** — Admin approves in the Control Panel. Sets `published_at`; if a gallery is used, the image appears there and the "published" notification is queued (dedupe-guarded).
4. **`rejected`** — Admin declines. Silent unless the client wants a soft notice.

Only `published` records appear publicly. Records are created on submit; a scheduled command prunes orphaned temp files older than ~24h.

## Data model: `GeneratedImage`
- `id`, `uuid`
- `first_name`, `last_name`
- `ja_style` (selected style key/reference)
- `background_removed` (bool)
- `source_image_path` (uploaded portrait — kept for moderation), `final_path` (composite)
- `status` — `submitted` | `published` | `rejected` (default `submitted`)
- `user_email`
- `consent_at`, `published_at` (nullable), `notified_at` (nullable)
- timestamps

Deleting a record deletes **all** its stored files.

## Build in phases. Verify each before moving on.

### Phase 1 — Scaffold
- Fresh Laravel 13 app on PHP 8.4 (confirm if one exists in the cwd). Install Statamic 6; confirm the CP loads.
- Vite + Vue 3 + Tailwind v4; mount a Vue island into a Statamic template.
- Placeholder landing content (hero, paragraph, generator section, empty gallery). Restrained, cultural-institution styling — visibly cleaner than the reference.
- Create the "JA styles" Statamic collection with a couple of placeholder assets.

### Phase 2 — Upload + composite pipeline (preview, no record)
- Frontend: portrait picker (drag/drop + file picker, client-side validation, show the chosen photo); an optional **"remove background" toggle** that runs the **client-side cutout**; a **"JA" style dropdown** (thumbnails from the collection); **Vorname/Name** inputs.
- `POST /api/generate` (multipart, receives the possibly-already-cutout image) → validate inputs → run server pipeline (EXIF normalise/strip → composite portrait + JA + name + branding via GD) → return `{ preview_id, url }`. Synchronous; brief loading state.
- Try/catch → clean JSON errors, never a stack trace.

### Phase 3 — Guardrails
- Rate-limit `/api/generate` per IP (public upload+processing endpoint).

### Phase 4 — Confirm / submit + immediate delivery
- After preview, a "Use this image" button reveals a minimal form: **email (required)** + **consent checkbox** ("I have the right to use this photo and the name entered, and I agree my image may be published and that you'll email me a copy").
- `POST /api/submit` → validate (consent required) → promote temp files → create `GeneratedImage` (`status = submitted`, `consent_at = now()`).
- Offer **immediate download** and an **email-to-me** of the finished image. (Optional social-share buttons — recommended; the reference's viral engine.)
- Scheduled `app:prune-previews` deletes orphaned temp files older than 24h.

### Phase 5 — Moderation in the Control Panel (Runway)
- Register `GeneratedImage` as a Runway resource. Blueprint shows the **final composite, source portrait, rendered name, email, status, timestamps**.
- Listing filterable by status; restrict with Runway permissions to moderators only.
- **Reviewer checklist** (surface as CP guidance) — before publishing, confirm: the portrait is appropriate/safe/acceptable to publish (no third-party/non-consenting subjects; due care re minors); the **entered name/text is appropriate** (free-text UGC in a public image); the cut-out (if used) looks clean.
- Explicit **"Publish & notify"** action (or observer on `submitted → published`) sets `published_at` and, if a gallery is used, dispatches the notification.

### Phase 6 — Publish notification (no daemon)
- A Laravel **Notification** (mail channel) to `user_email` on publication. Subject ~ "Dein Bild wurde veröffentlicht". Attach the final composite.
- Dispatch to the **database queue**, processed by the cron-triggered `queue:work --stop-when-empty` (or send `sync` if you prefer — it's a CP action, a brief block is acceptable). **Do not rely on a persistent worker.**
- **Dedupe guard:** only send if `notified_at` is null; set it after dispatch.
- Tokenised remove/unsubscribe link (ties to deletion).

### Phase 7 — Public gallery (confirm scope) + privacy/consent
- **Gallery (confirm the client still wants one — the reference had a supporter gallery + counter):** landing-page section listing `published` composites via a Runway tag / Antlers loop, newest first, paginated.
- **Privacy/consent (Swiss FADP / GDPR — architectural minimum; wording is the client's/lawyer's):**
  - Strong posture by design: with client-side cutout the **raw portrait can stay on the device** (only the finished image is uploaded), and a Swiss host keeps stored data in Switzerland — so what you store is the composite + email, in CH.
  - Consent at submit (`consent_at`) covering the **uploaded image, the entered name, and publication**; a one-line privacy note linking the client's policy; a **deletion path** (CP delete + tokenised email link) removing all files; EXIF stripped (Phase 2).

### Phase 8 — Polish
- All states handled: choosing photo (+ optional cutout) / selecting style + name / preview / submitted-confirmation (download + email) / error.
- README: env vars, the cron entries (scheduler + queue), mail config, the composite template + JA assets, deployment steps, full end-to-end test.

## Deployment / hosting
**Target: a capable Swiss shared host** — Hostpoint, Cyon, or Metanet are all confirmed to run this stack (SSH, Git, Composer, PHP 8.4/8.5, cron). If the client's data-residency story matters (it does), Metanet markets Swiss-only data under revFADP most explicitly; Hostpoint is the most reliable generalist; Cyon is dev-friendly but see the Composer note. Avoid bottom-tier plans — give the Statamic CP memory headroom.

Constraints and how to handle them:
- **Document root → `/public`.** Point the site's docroot at Laravel's `public/` (symlink or vhost setting); never expose the app root.
- **PHP `memory_limit` ≥ 256M** for the Statamic CP (and Composer). Hostpoint exposes this via `.user.ini`.
- **Composer may OOM on shared RAM** (Cyon documents this explicitly). Fallback: run `composer install` locally and deploy the `vendor/` folder, or build via Git with a controlled memory limit.
- **No persistent processes.** Drive both the scheduler and the queue from **cron**:
  - scheduler: `* * * * * php /path/artisan schedule:run` (runs `app:prune-previews`),
  - queue: a per-minute `php /path/artisan queue:work --stop-when-empty --max-time=50` (or use `sync` and skip this).
- **Compositing via GD** (Imagick may be absent) — already set in the stack.
- **Assets:** build Vite assets locally and deploy the compiled output; no Node required on the host.
- **SSL:** Let's Encrypt (all three include it).

## Cost note (your reference)
Essentially **zero variable cost**: no AI/image-generation spend, and client-side cutout has no per-image fee. The only way a per-image cost reappears is if you fall back to the Photoroom API. Hosting is a flat Swiss shared-hosting fee.

## Out of scope (do NOT build; leave `// PROD:` markers)
- Any AI / LLM / generative image features (explicitly excluded by the client).
- Server-side background removal / ML (incompatible with shared hosting).
- Turnstile/captcha.
- Real branding, copy, fonts, legal/imprint text, analytics.
- Inertia 3, multi-site, GraphQL delivery.

## Acceptance criteria
1. Landing page loads with hero + generator (photo + optional cutout + JA-style dropdown + name) + (if in scope) empty gallery.
2. The composite **preview** renders within a brief wait (portrait — optionally background-removed **in the browser** — + chosen "JA" + name + branding) — no DB record yet.
3. The background cutout runs **client-side**; the raw photo is not sent to any third-party service.
4. Phone-photo orientation is correct (EXIF respected) and metadata is stripped.
5. "Use it" captures email + consent (required), creates a `submitted` record, and the user can **download and/or be emailed** the finished image.
6. The Control Panel shows the composite + source portrait + name for review, restricted to moderators; the reviewer checklist is visible.
7. Publishing sets `published_at`; if a gallery is in scope the image appears there and the creator gets the email — exactly once — and the notification is delivered **without a running queue daemon** (cron or `sync`).
8. The scheduler (`prune-previews`) runs from cron.
9. Rate limit enforces with a friendly message; invalid uploads, missing fields, and pipeline failures show clean inline errors (never a stack trace).
10. Deleting a record from the CP removes all its stored files.
11. **No AI/LLM/image-generation calls anywhere in the codebase**, and **no server-side ML**.

## How to work
- Work phase by phase; after each, state what you did and how to verify, then continue.
- **Verify before coding:** Runway's Laravel 13 Composer constraint; the chosen client-side cutout library + a commercially-licensed model; and the host's PHP 8.4 / SSH / cron / docroot / `memory_limit` capabilities.
- Confirm two product points with the client before the relevant phases: (a) whether a public supporter gallery is in scope; (b) the privacy-notice wording for storing the image + email and publishing it.
- The composite template + JA style assets are design inputs — build against clearly-marked placeholders if not ready, and flag rather than guessing.
- Prefer small, readable files. Don't add dependencies beyond those listed without flagging why.
- If a decision is genuinely ambiguous (existing app in cwd, missing credentials/template/assets/host details), pause and ask rather than guessing destructively.
