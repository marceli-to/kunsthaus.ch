<?php

namespace App\Services;

use Illuminate\Support\Collection;
use Statamic\Facades\Entry;

/**
 * Reads the "JA" painting-technique styles from the Statamic `ja_styles`
 * collection. Shared by the public API (populates the dropdown) and the
 * composite pipeline (resolves the chosen style's PNG on disk) — so the server
 * never trusts a client-supplied file path, only a known style key.
 */
class JaStyleRepository
{
	/**
	 * All published styles as plain arrays: { key, label, url }.
	 *
	 * @return Collection<int, array{key: string, label: string, url: string}>
	 */
	public function all(): Collection
	{
		return Entry::query()
			->where('collection', 'ja_styles')
			->get()
			->filter(fn ($entry) => $entry->published())
			->map(fn ($entry) => [
				'key' => $entry->slug(),
				'label' => (string) $entry->get('title'),
				'url' => $this->assetUrl($entry),
				'order' => (int) ($entry->get('order') ?? 0),
			])
			->filter(fn ($s) => $s['url'] !== '')
			->sortBy('order')
			->values()
			->map(fn ($s) => ['key' => $s['key'], 'label' => $s['label'], 'url' => $s['url']]);
	}

	/**
	 * Absolute filesystem path to a style's PNG, or null if the key is unknown.
	 * Used by the composite pipeline.
	 */
	public function pathForKey(string $key): ?string
	{
		$entry = Entry::query()
			->where('collection', 'ja_styles')
			->where('slug', $key)
			->get()
			->first(fn ($entry) => $entry->published());

		if (!$entry) {
			return null;
		}

		$asset = $entry->augmentedValue('asset')->value();
		$asset = is_iterable($asset) ? collect($asset)->first() : $asset;

		$path = $asset?->resolvedPath();

		return ($path && is_file($path)) ? $path : null;
	}

	private function assetUrl($entry): string
	{
		$asset = $entry->augmentedValue('asset')->value();
		$asset = is_iterable($asset) ? collect($asset)->first() : $asset;

		return $asset?->url() ?? '';
	}
}
