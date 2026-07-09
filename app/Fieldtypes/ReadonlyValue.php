<?php

namespace App\Fieldtypes;

use BackedEnum;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Statamic\Fields\Fieldtype;
use Throwable;

/**
 * Read-only value display for the moderation view: shows a value in a solid,
 * always-visible box (a "—" placeholder when empty), instead of Statamic's
 * dashed read-only rendering that hides empty values. Purely presentational —
 * `process()` returns the value untouched, so the underlying column is written
 * back unchanged on save (safe for real columns; combine with read_only for
 * accessor-only fields).
 *
 * The `as` config controls formatting in the listing column: `datetime`,
 * `date`, `bool`, or plain text (default). An `options` map (value => label)
 * renders a label instead of the raw value (e.g. an enum status). The
 * publish-form component does its own display formatting from the raw value.
 */
class ReadonlyValue extends Fieldtype
{
	public function preProcess($data)
	{
		// Hand the JS component a stable ISO string for dates, and the scalar
		// value for backed enums (e.g. the status column) so `options` can map it.
		if ($data instanceof CarbonInterface) {
			return $data->toIso8601String();
		}

		if ($data instanceof BackedEnum) {
			return $data->value;
		}

		return $data;
	}

	public function process($data)
	{
		return $data;
	}

	/**
	 * Format the value for the listing column (Statamic won't know it's a date).
	 */
	public function preProcessIndex($data)
	{
		if ($data === null || $data === '') {
			return null;
		}

		if ($data instanceof BackedEnum) {
			$data = $data->value;
		}

		$options = $this->config('options');
		if (is_array($options) && array_key_exists($data, $options)) {
			return $options[$data];
		}

		$as = $this->config('as');

		if ($as === 'bool') {
			return $data ? 'Ja' : 'Nein';
		}

		if ($as === 'date' || $as === 'datetime') {
			try {
				$date = Carbon::parse($data)->timezone('Europe/Zurich');

				return $as === 'date' ? $date->format('d.m.Y') : $date->format('d.m.Y, H:i');
			} catch (Throwable) {
				return $data;
			}
		}

		return $data;
	}
}
