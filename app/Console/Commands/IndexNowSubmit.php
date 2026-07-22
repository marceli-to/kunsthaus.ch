<?php

namespace App\Console\Commands;

use App\Jobs\SubmitToIndexNow;
use App\Services\RoutableEntries;
use Illuminate\Console\Command;

/**
 * Meldet alle öffentlichen URLs auf einen Schlag an IndexNow — für die
 * Erstanmeldung, nach einem Key-Wechsel oder wenn die Queue mal stillstand und
 * einzelne Meldungen verloren gingen.
 *
 * Läuft synchron (dispatchSync), damit man das Ergebnis direkt im Terminal
 * bzw. im Log sieht und nicht auf einen Worker warten muss.
 *
 *   php artisan indexnow:submit
 *   php artisan indexnow:submit --dry-run
 */
class IndexNowSubmit extends Command
{
	protected $signature = 'indexnow:submit {--dry-run : Nur anzeigen, was gemeldet würde}';

	protected $description = 'Meldet alle öffentlichen Seiten-URLs an IndexNow';

	public function handle(RoutableEntries $entries): int
	{
		if (! config('indexnow.key')) {
			$this->error('Kein IndexNow-Key konfiguriert (config/indexnow.php).');

			return self::FAILURE;
		}

		$urls = $entries->urls();

		if ($urls->isEmpty()) {
			$this->warn('Keine öffentlichen URLs gefunden.');

			return self::SUCCESS;
		}

		$this->line($urls->implode(PHP_EOL));

		if ($this->option('dry-run')) {
			$this->comment($urls->count().' URL(s) — nicht gemeldet (--dry-run).');

			return self::SUCCESS;
		}

		SubmitToIndexNow::dispatchSync($urls->all());

		$this->info($urls->count().' URL(s) an IndexNow gemeldet.');

		return self::SUCCESS;
	}
}
