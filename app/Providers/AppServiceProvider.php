<?php

namespace App\Providers;

use App\Listeners\AnnounceEntryToIndexNow;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\ServiceProvider;
use Statamic\Events\EntryDeleted;
use Statamic\Events\EntrySaved;
use Statamic\Events\EntryScheduleReached;
use Statamic\Statamic;

class AppServiceProvider extends ServiceProvider
{
	/**
	 * Register any application services.
	 */
	public function register(): void
	{
		//
	}

	/**
	 * Bootstrap any application services.
	 */
	public function boot(): void
	{
		// Outside production, funnel ALL outgoing mail to MAIL_TO (e.g. MailHog)
		// so real recipient addresses are never contacted from dev/staging.
		if (! $this->app->isProduction() && ($catchAll = config('mail.to'))) {
			Mail::alwaysTo($catchAll);
		}

		// IndexNow: Inhaltsänderungen sofort an Bing & Co. melden. Nur aus der
		// Produktion — sonst würden lokale .test-URLs nach aussen gemeldet.
		if ($this->app->isProduction()) {
			Event::listen(
				[EntrySaved::class, EntryScheduleReached::class, EntryDeleted::class],
				AnnounceEntryToIndexNow::class,
			);
		}

		// Custom Control Panel assets (fieldtypes for the moderation view).
		Statamic::vite('app', [
			'input' => [
				'resources/js/cp.js',
				'resources/css/cp.css',
			],
			'hotFile' => public_path('cp-hot'),
			'buildDirectory' => 'vendor/app',
		]);
	}
}
