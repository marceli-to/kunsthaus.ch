<?php

namespace App\Http\Controllers;

use App\Services\RoutableEntries;
use Illuminate\Http\Response;

/**
 * Renders /sitemap.xml from the routable Statamic entries. Stays in sync with
 * content automatically: whenever a page or blog post is published (or a blog
 * post's date passes), it appears here on the next request.
 *
 * Welche Einträge öffentlich sind, entscheidet RoutableEntries — dieselbe
 * Definition nutzt die IndexNow-Meldung, damit Sitemap und Ping nie
 * auseinanderlaufen.
 *
 * Absolute URLs come from the site URL (config('app.url')), so the output is
 * correct per environment without hard-coding the production domain.
 */
class SitemapController extends Controller
{
	public function __invoke(RoutableEntries $entries): Response
	{
		$urls = $entries->all()
			->map(fn ($entry) => [
				'loc' => $entry->absoluteUrl(),
				'lastmod' => optional($entry->lastModified())->toAtomString(),
			])
			->sortBy('loc')
			->values();

		return response()
			->view('sitemap', ['urls' => $urls])
			->header('Content-Type', 'application/xml');
	}
}
