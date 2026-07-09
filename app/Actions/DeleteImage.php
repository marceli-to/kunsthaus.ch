<?php

namespace App\Actions;

use App\Models\GeneratedImage;
use Illuminate\Database\Eloquent\Model;
use Statamic\Actions\Action;

/**
 * Delete a submission (and its stored files, via the model observer's `deleting`
 * hook). Runway's built-in delete is hidden because the resource is read-only,
 * so this restores deletion — the FADP purge path a moderator can trigger from
 * the CP. Shown on both the listing and the detail view.
 */
class DeleteImage extends Action
{
	protected $dangerous = true;

	public static function title()
	{
		return 'Löschen';
	}

	public function visibleTo($item)
	{
		return $item instanceof GeneratedImage;
	}

	public function visibleToBulk($items)
	{
		return $items->every(fn ($item) => $this->visibleTo($item));
	}

	public function authorize($user, $item)
	{
		return $user->can('delete generated_image');
	}

	public function confirmationText()
	{
		return 'Dieses Bild endgültig löschen? Die gespeicherten Dateien werden entfernt.';
	}

	public function buttonText()
	{
		return 'Löschen|:count Bilder löschen';
	}

	public function run($items, $values)
	{
		// delete() fires the observer's `deleting` hook → wipes the private files.
		$items->each(fn (Model $image) => $image->delete());

		return trans_choice('Bild gelöscht|Bilder gelöscht', $items->count());
	}
}
