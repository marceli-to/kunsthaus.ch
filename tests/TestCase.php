<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
	protected function setUp(): void
	{
		parent::setUp();

		// Runway resources (GeneratedImage) are search-indexed on every save. Point
		// the local search driver at a throwaway directory so tests never read or
		// pollute the developer's real storage/statamic/search index.
		config(['statamic.search.drivers.local.path' => storage_path('framework/testing/statamic-search')]);
	}
}
