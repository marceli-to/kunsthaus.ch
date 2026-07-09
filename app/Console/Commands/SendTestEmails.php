<?php

namespace App\Console\Commands;

use App\Enums\GeneratedImageStatus;
use App\Mail\NewSubmissionNotification;
use App\Models\GeneratedImage;
use App\Notifications\ImagePublished;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Send every JAtelier e-mail to an inbox for a visual / theme check — the whole
 * point is to eyeball the Kunsthaus mail theme (header logo, accent, footer)
 * without driving the real submit/publish flow.
 *
 * Uses an UNSAVED demo GeneratedImage plus a throwaway attachment file on the
 * 'local' disk, so nothing touches the database. Sends synchronously
 * (sendNow / notifyNow) so it works without a queue worker. Outside production
 * every message is still funnelled to MAIL_TO by AppServiceProvider — so with
 * MailHog running you'll see them all there regardless of --to.
 *
 *   php artisan jatelier:test-emails
 *   php artisan jatelier:test-emails --to=me@example.com
 *   php artisan jatelier:test-emails --only=published
 */
class SendTestEmails extends Command
{
	protected $signature = 'jatelier:test-emails
		{--to= : Recipient (defaults to MAIL_TO, else the notify address). Note: non-prod redirects all mail to MAIL_TO.}
		{--only= : Send just one — "submitted" or "published"}';

	protected $description = 'Send the JAtelier e-mails to an inbox to preview the Kunsthaus mail theme';

	public function handle(): int
	{
		$to = $this->option('to')
			?? config('mail.to')
			?? config('composite.notify_address');

		if (! $to) {
			$this->error('No recipient: pass --to=, or set MAIL_TO / MAIL_NOTIFY in .env.');

			return self::FAILURE;
		}

		$only = $this->option('only');
		$image = $this->makeDemoImage();

		try {
			if ($only === null || $only === 'submitted') {
				// Moderation heads-up to the team address.
				Mail::to($to)->sendNow(new NewSubmissionNotification($image));
				$this->line('  ✓ NewSubmissionNotification (submit → team)');
			}

			if ($only === null || $only === 'published') {
				// Publish confirmation to the visitor (with remove link).
				Notification::route('mail', $to)->notifyNow(new ImagePublished($image));
				$this->line('  ✓ ImagePublished (publish → visitor)');
			}
		} finally {
			// Drop the throwaway attachment file + its folder.
			Storage::disk('local')->deleteDirectory("images/{$image->uuid}");
		}

		$mailTo = config('mail.to');
		$dest = (! $this->getLaravel()->isProduction() && $mailTo) ? $mailTo." (via MAIL_TO)" : $to;
		$this->info("Sent to {$dest}.");

		return self::SUCCESS;
	}

	/**
	 * An unsaved demo record backed by a real (throwaway) file on the local disk,
	 * so the mailables' attachments resolve. Not persisted — no DB row.
	 */
	private function makeDemoImage(): GeneratedImage
	{
		$uuid = (string) Str::uuid();
		$finalPath = "images/{$uuid}/final.jpg";

		// A placeholder attachment payload (the campaign logo stands in for the
		// composite). Named .jpg with an image/jpeg mime — fine for a preview.
		Storage::disk('local')->put($finalPath, file_get_contents(public_path('img/logo.png')));

		return new GeneratedImage([
			'first_name' => 'Test',
			'last_name' => 'Besucher',
			'ja_style' => 'aquarell',
			'background_removed' => false,
			'final_path' => $finalPath,
			'status' => GeneratedImageStatus::Submitted,
			'user_email' => 'test-besucher@example.com',
		])->forceFill(['uuid' => $uuid]);
	}
}
