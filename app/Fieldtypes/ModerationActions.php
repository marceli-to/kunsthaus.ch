<?php

namespace App\Fieldtypes;

use Statamic\Fields\Fieldtype;

/**
 * Renders the "Freigeben" / "Ablehnen" moderation buttons on the CP detail page.
 * The value is the model's `moderation` accessor payload (status + POST URLs +
 * CSRF). Read-only / computed — mark the blueprint field `save: false` so this
 * accessor (no DB column) is never written.
 */
class ModerationActions extends Fieldtype
{
	public function preProcess($data)
	{
		return $data;
	}

	public function process($data)
	{
		return $data;
	}

	/**
	 * In the listing, show a plain status label instead of the raw payload
	 * (the buttons only make sense on the detail page).
	 */
	public function preProcessIndex($data)
	{
		$status = is_array($data) ? ($data['status'] ?? null) : null;

		return match ($status) {
			'submitted' => 'Eingereicht',
			'published' => 'Veröffentlicht',
			'rejected' => 'Abgelehnt',
			default => null,
		};
	}
}

