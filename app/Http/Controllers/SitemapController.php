<?php

namespace App\Http\Controllers;

use Illuminate\Http\Response;
use Statamic\Facades\Entry;

/**
 * Renders /sitemap.xml from the routable Statamic entries. Stays in sync with
 * content automatically: whenever a page or blog post is published (or a blog
 * post's date passes), it appears here on the next request.
 *
 * Only the `pages` and `blog` collections are routable front-end content. The
 * `ja_styles` collection has no `route:` (generator data, not pages) and is
 * skipped. Entries are filtered to published, publicly viewable (this also drops
 * future-dated blog posts, which are private per the collection's date_behavior)
 * and actually URL-addressable.
 *
 * Absolute URLs come from the site URL (config('app.url')), so the output is
 * correct per environment without hard-coding the production domain.
 */
class SitemapController extends Controller
{
	public function __invoke(): Response
	{
		$urls = Entry::query()
			->whereIn('collection', ['pages', 'blog'])
			->where('published', true)
			->get()
			->reject(fn ($entry) => $entry->private())
			->filter(fn ($entry) => $entry->url())
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
