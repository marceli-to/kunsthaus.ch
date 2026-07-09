<?php

namespace App\Fieldtypes;

use Statamic\Fields\Fieldtype;

/**
 * Read-only image preview: renders a URL value as an inline <img> in the CP
 * publish form (the moderator's finished composite), with a "full size" link
 * beneath. The URL is served same-origin behind the CP session, so the browser
 * loads it directly. Never writes — the value is a computed accessor with no
 * column, so it must not persist.
 */
class ImagePreview extends Fieldtype
{
	public function preProcess($data)
	{
		return $data;
	}

	public function process($data)
	{
		return $data;
	}
}
