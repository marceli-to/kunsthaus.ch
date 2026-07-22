<?php

namespace Tests\Feature;

use App\Jobs\SubmitToIndexNow;
use App\Listeners\AnnounceEntryToIndexNow;
use App\Services\RoutableEntries;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Statamic\Events\EntrySaved;
use Statamic\Facades\Entry;
use Tests\TestCase;

/**
 * Die IndexNow-Meldung: was gemeldet wird (nur Frontend-Seiten, auch beim
 * Depublizieren) und wie der Job auf Antworten der API reagiert — 4xx sind
 * Konfigurationsfehler und dürfen NICHT endlos wiederholt werden, 5xx/429 schon.
 *
 * Die eigentliche Registrierung der Listener steckt hinter einem
 * Produktions-Riegel im AppServiceProvider; hier wird der Listener darum direkt
 * aufgerufen.
 */
class IndexNowTest extends TestCase
{
	protected function setUp(): void
	{
		parent::setUp();

		config([
			'app.url' => 'https://kunsthaus-ja.ch',
			'indexnow.key' => 'testkey123',
			'indexnow.endpoint' => 'https://api.indexnow.org/indexnow',
		]);
	}

	/**
	 * Ein echter Eintrag aus dem Repo-Content. Frisch gebaute Einträge taugen
	 * hier nicht: `pages` ist eine strukturierte Collection, die URL kommt aus
	 * dem Seitenbaum — ein ungespeicherter Eintrag hat gar keine.
	 */
	private function entry(string $collection = 'pages')
	{
		$entry = Entry::query()->where('collection', $collection)->get()->first();

		$this->assertNotNull($entry, "Kein Eintrag in der Collection [{$collection}] gefunden.");

		return $entry;
	}

	public function test_it_posts_the_expected_indexnow_payload(): void
	{
		Http::fake([
			'api.indexnow.org/*' => Http::response('', 200),
		]);

		(new SubmitToIndexNow(['https://kunsthaus-ja.ch/impressum']))->handle();

		Http::assertSent(function (Request $request) {
			return $request->url() === 'https://api.indexnow.org/indexnow'
				&& $request['host'] === 'kunsthaus-ja.ch'
				&& $request['key'] === 'testkey123'
				&& $request['keyLocation'] === 'https://kunsthaus-ja.ch/testkey123.txt'
				&& $request['urlList'] === ['https://kunsthaus-ja.ch/impressum'];
		});
	}

	public function test_it_deduplicates_urls_and_skips_an_empty_list(): void
	{
		Http::fake();

		$url = 'https://kunsthaus-ja.ch/impressum';
		(new SubmitToIndexNow([$url, $url]))->handle();

		Http::assertSent(fn (Request $request) => $request['urlList'] === [$url]);

		(new SubmitToIndexNow([]))->handle();

		Http::assertSentCount(1);
	}

	public function test_a_rejected_key_is_not_retried(): void
	{
		Http::fake([
			'api.indexnow.org/*' => Http::response('Forbidden', 403),
		]);

		// Kein Throw = der Job gilt als erledigt und landet nicht in der Retry-Schlaufe.
		(new SubmitToIndexNow(['https://kunsthaus-ja.ch/impressum']))->handle();

		Http::assertSentCount(1);
	}

	public function test_a_server_error_is_retried(): void
	{
		Http::fake([
			'api.indexnow.org/*' => Http::response('', 503),
		]);

		$this->expectException(\Illuminate\Http\Client\RequestException::class);

		(new SubmitToIndexNow(['https://kunsthaus-ja.ch/impressum']))->handle();
	}

	public function test_saving_a_page_queues_a_submission(): void
	{
		Queue::fake();

		$entry = $this->entry();

		(new AnnounceEntryToIndexNow)->handle(new EntrySaved($entry));

		Queue::assertPushed(SubmitToIndexNow::class, function (SubmitToIndexNow $job) use ($entry) {
			return $job->urls === [$entry->absoluteUrl()];
		});
	}

	public function test_an_unpublished_page_is_still_announced(): void
	{
		Queue::fake();

		// Depublizieren muss gemeldet werden, damit die Suchmaschine die nun
		// fehlende Seite neu prüft und aus dem Index nimmt.
		(new AnnounceEntryToIndexNow)->handle(new EntrySaved($this->entry()->published(false)));

		Queue::assertPushed(SubmitToIndexNow::class);
	}

	public function test_non_routable_collections_are_ignored(): void
	{
		Queue::fake();

		// ja_styles ist Generator-Daten ohne `route:` — keine Seite, keine Meldung.
		(new AnnounceEntryToIndexNow)->handle(new EntrySaved($this->entry('ja_styles')));

		Queue::assertNothingPushed();
	}

	public function test_the_sitemap_and_the_full_submission_cover_the_same_urls(): void
	{
		$urls = app(RoutableEntries::class)->urls();

		$sitemap = $this->get('/sitemap.xml')->assertOk()->getContent();

		foreach ($urls as $url) {
			$this->assertStringContainsString('<loc>'.htmlspecialchars($url).'</loc>', $sitemap);
		}
	}
}
