<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Meldet URLs an die IndexNow-API, damit Bing & Co. sofort neu crawlen statt
 * auf den eigenen Zeitplan zu warten.
 *
 * Läuft über die Queue, damit ein "Publizieren"-Klick im Control Panel nicht
 * auf einen fremden HTTP-Request wartet — und damit eine Störung bei IndexNow
 * niemals das Speichern eines Eintrags scheitern lässt.
 *
 * Fehlerbehandlung: 200/202 ist Erfolg (202 = Key wird noch geprüft). 429 und
 * 5xx sind vorübergehend und werden über die Retries erneut versucht. Alle
 * übrigen 4xx (400 falsches Format, 403 ungültiger Key, 422 URL gehört nicht
 * zum Host) sind Konfigurationsfehler — die werden protokolliert und NICHT
 * wiederholt, ein Retry würde dasselbe Ergebnis liefern.
 */
class SubmitToIndexNow implements ShouldQueue
{
	use Queueable;

	public int $tries = 3;

	/** @var array<int, int> Sekunden vor jedem Retry */
	public array $backoff = [60, 300];

	/** @param array<int, string> $urls */
	public function __construct(public array $urls) {}

	public function handle(): void
	{
		$key = config('indexnow.key');
		$urls = array_values(array_unique($this->urls));

		if (! $key || ! $urls) {
			return;
		}

		$base = rtrim((string) config('app.url'), '/');

		$response = Http::timeout(15)->post(config('indexnow.endpoint'), [
			'host' => parse_url($base, PHP_URL_HOST),
			'key' => $key,
			'keyLocation' => "{$base}/{$key}.txt",
			'urlList' => $urls,
		]);

		if ($response->successful()) {
			Log::info('IndexNow: '.count($urls).' URL(s) gemeldet', ['status' => $response->status()]);

			return;
		}

		Log::warning('IndexNow-Meldung abgelehnt', [
			'status' => $response->status(),
			'body' => $response->body(),
			'urls' => $urls,
		]);

		// Nur vorübergehende Fehler erneut versuchen.
		if ($response->status() === 429 || $response->serverError()) {
			$response->throw();
		}
	}
}
