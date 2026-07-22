<?php

namespace App\Listeners;

use App\Jobs\SubmitToIndexNow;
use App\Services\RoutableEntries;
use Statamic\Events\EntryDeleted;
use Statamic\Events\EntrySaved;
use Statamic\Events\EntryScheduleReached;

/**
 * Meldet jede Änderung an einer Frontend-Seite sofort an IndexNow.
 *
 * Angebunden an drei Ereignisse: Speichern (neu, geändert, publiziert oder
 * depubliziert), planmässiges Live-Gehen eines vordatierten Blogposts und
 * Löschen.
 *
 * Gemeldet wird bewusst auch, wenn ein Eintrag depubliziert oder gelöscht
 * wurde: IndexNow ist genau dafür gedacht, dass die Suchmaschine die dann
 * fehlende Seite rasch neu prüft und aus dem Index nimmt. Massgeblich ist
 * allein, ob es überhaupt eine öffentliche URL gibt.
 *
 * Registriert in AppServiceProvider — dort sitzt auch der Produktions-Riegel.
 */
class AnnounceEntryToIndexNow
{
	public function handle(EntrySaved|EntryScheduleReached|EntryDeleted $event): void
	{
		$entry = $event->entry;

		if (! in_array($entry->collectionHandle(), RoutableEntries::COLLECTIONS, true)) {
			return;
		}

		if (! $url = $entry->absoluteUrl()) {
			return;
		}

		SubmitToIndexNow::dispatch([$url]);
	}
}
