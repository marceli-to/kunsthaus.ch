# Deployment — cron setup (queue + scheduler)

The target is a **Swiss shared host** (Hostpoint / Cyon / Metanet) with **no
persistent processes** — so nothing that normally runs as a long-lived daemon
(the queue worker, the Laravel scheduler) may run as one. Both are driven from
**per-minute cron** instead. See `kunsthaus-build-brief-v7.md` → *Deployment /
hosting* for the host constraints this follows from.

## The two cron entries

Add both to the hosting account's crontab (adjust `/path/to/app` and the PHP
binary to the host's paths):

```cron
# Queue — drain background jobs (mails + public-image rendering).
* * * * * cd /path/to/app && php artisan queue:work --stop-when-empty --max-time=50 >> /dev/null 2>&1

# Scheduler — run due scheduled commands (e.g. app:prune-previews, when built).
* * * * * cd /path/to/app && php artisan schedule:run >> /dev/null 2>&1
```

`--stop-when-empty` makes the worker exit once the queue is empty (no daemon);
`--max-time=50` caps each run under the one-minute cron interval so runs never
overlap. `sync` is the fallback (see below) if the host can't run the queue line.

## What the queue actually drains

`QUEUE_CONNECTION=database` (`config/queue.php`, the `jobs` table). Everything the
JAtelier pipeline pushes is a **queued** job — nothing sends or renders inline in
the request:

| Queued work | Class | Enqueued when |
|-------------|-------|---------------|
| Submission notice → moderators | `App\Mail\NewSubmissionNotification` | a visitor submits (`SubmitGeneratedImage`) |
| Publish notice → creator (composite attached) | `App\Notifications\ImagePublished` | an image is published (`PublishGeneratedImage::notifyOnce`) |
| Public renditions (full copy + cropped web-version) | `App\Jobs\GeneratePublicVersions` | an image is published (observer `saved`) |

So until the queue cron runs, an approved image's **email is not sent** and its
**public files are not rendered**. (The public files have a safety net — the
`{{ jatelier_images }}` tag self-heals them synchronously on the next supporter-
page render — but the mail does not: it only goes out when the worker runs.)

## What the scheduler drains — currently nothing

The `schedule:run` line is **forward-looking**. No scheduled command is
registered yet (`bootstrap/app.php` has no `->withSchedule(...)`), and the
planned `app:prune-previews` sweep of orphaned `storage/app/private/previews/`
temp files (Build Brief Phase 8) is **not built**. Add the cron entry now so it's
ready, but it's a no-op until that command exists and is scheduled.

## `sync` fallback (skip the queue cron)

If the host can't run the per-minute queue worker, set `QUEUE_CONNECTION=sync`.
Jobs then run **inline** in the request that dispatches them:

- Publishing an image blocks the moderator's "Freigeben" click on the GD crop +
  mail send (a brief, acceptable delay — it's a CP action).
- A submission blocks the visitor's request on the notification mail.

Correctness is unchanged (the publish dedupe guard and idempotent job both hold);
only the timing moves into the request. With `sync`, the queue cron is unneeded.

## Prerequisites for mail to actually leave the host

- **Mail transport:** `MAIL_MAILER=log` in dev/`.env.example` only writes to
  `storage/logs/laravel.log`. Set a real transactional provider (Postmark /
  Resend / SES) in production, or queued mail is drained into the log and never
  delivered.
- **Non-prod catch-all:** outside production, `MAIL_TO` (if set) redirects ALL
  mail to one inbox (`AppServiceProvider`). Leave it empty in production so real
  recipients are used.
- **Failed jobs:** a job that throws is recorded in the `failed_jobs` table.
  Inspect with `php artisan queue:failed`, retry with `php artisan queue:retry
  all`. Worth a periodic check after go-live.

## Verifying on the host

```bash
# One manual drain — should send any pending mail + render public images:
php artisan queue:work --stop-when-empty

# Anything stuck?
php artisan queue:failed
```

In **local dev** nothing drains the queue automatically either — run
`php artisan queue:work --stop-when-empty` by hand after publishing to send the
queued mail and generate the public renditions.
