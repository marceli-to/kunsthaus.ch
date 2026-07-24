<?php

namespace App\Services;

use Illuminate\Support\Collection;
use Statamic\Contracts\Entries\Entry as EntryContract;
use Statamic\Facades\Entry;

/**
 * Die öffentlich erreichbaren Statamic-Einträge — eine einzige Definition für
 * alle Stellen, die "was ist auf der Website adressierbar?" beantworten müssen
 * (aktuell die XML-Sitemap und die IndexNow-Vollmeldung).
 *
 * Nur `pages` und `blog` sind routable Frontend-Inhalte. Die Collection
 * `ja_styles` hat keine `route:` (Generator-Daten, keine Seiten) und fällt
 * darum raus. Gefiltert wird auf publiziert, öffentlich sichtbar (das entfernt
 * auch zukünftig datierte Blogposts, die per date_behavior privat sind) und
 * tatsächlich URL-adressierbar.
 */
class RoutableEntries
{
	/** Collections mit `route:` — alles andere ist keine Seite. */
	public const COLLECTIONS = ['pages', 'blog'];

	/** @return Collection<int, EntryContract> */
	public function all(): Collection
	{
		return Entry::query()
			->whereIn('collection', self::COLLECTIONS)
			->where('published', true)
			->get()
			->reject(fn ($entry) => $entry->private())
			->reject(fn ($entry) => (bool) $entry->value('noindex'))
			->filter(fn ($entry) => $entry->url())
			->values();
	}

	/**
	 * Absolute URLs, alphabetisch — stabile Reihenfolge für Sitemap und Ping.
	 *
	 * @return Collection<int, string>
	 */
	public function urls(): Collection
	{
		return $this->all()
			->map(fn ($entry) => $entry->absoluteUrl())
			->sort()
			->values();
	}
}
