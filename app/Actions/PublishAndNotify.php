<?php

namespace App\Actions;

use App\Models\GeneratedImage;
use Illuminate\Database\Eloquent\Model;
use Statamic\Actions\Action;

/**
 * Control Panel action on a submitted GeneratedImage: "Freigeben &
 * benachrichtigen". Promotes it to published and (via the observer) emails the
 * creator their published image exactly once. Only shown on `submitted` records.
 *
 * Auto-discovered by Statamic (any Action subclass under app/Actions). The
 * domain logic lives in PublishGeneratedImage so it stays testable + shared with
 * the observer.
 */
class PublishAndNotify extends Action
{
	protected $icon = 'eye';

	public static function title()
	{
		return 'Freigeben & benachrichtigen';
	}

	public function visibleTo($item)
	{
		return $item instanceof GeneratedImage
			&& $item->status === \App\Enums\GeneratedImageStatus::Submitted;
	}

	public function visibleToBulk($items)
	{
		return $items->every(fn ($item) => $this->visibleTo($item));
	}

	public function authorize($user, $item)
	{
		return $user->can('edit generated_image');
	}

	public function confirmationText()
	{
		return 'Dieses Bild freigeben und den Ersteller benachrichtigen?';
	}

	public function buttonText()
	{
		return 'Freigeben|:count Bilder freigeben';
	}

	public function run($items, $values)
	{
		$publisher = app(PublishGeneratedImage::class);

		$items->each(fn (Model $image) => $publisher->handle($image));

		return trans_choice('Bild freigegeben|Bilder freigegeben', $items->count());
	}
}
