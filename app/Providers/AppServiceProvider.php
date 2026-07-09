<?php

namespace App\Providers;

use Illuminate\Support\Facades\Mail;
use Illuminate\Support\ServiceProvider;
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
