<?php

namespace Tests\Unit;

use App\Http\Requests\GenerateImageRequest;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

/**
 * Unit-tests the name sanitisation on GenerateImageRequest. Names are published
 * UGC, so this is a small security surface: it must strip markup and control
 * characters and collapse whitespace. We exercise the private sanitiser
 * directly via reflection so no HTTP/validation plumbing is needed.
 */
class GenerateImageRequestTest extends TestCase
{
	private function sanitise(string $value): string
	{
		$method = new ReflectionMethod(GenerateImageRequest::class, 'sanitiseName');
		$method->setAccessible(true);

		return $method->invoke(new GenerateImageRequest, $value);
	}

	public function test_it_trims_surrounding_whitespace(): void
	{
		$this->assertSame('Marcel', $this->sanitise('  Marcel  '));
	}

	public function test_it_collapses_internal_whitespace(): void
	{
		$this->assertSame('Anna Maria', $this->sanitise("Anna\t\n  Maria"));
	}

	public function test_it_strips_html_tags(): void
	{
		$this->assertSame('alert', $this->sanitise('<script>alert</script>'));
	}

	public function test_it_drops_content_after_an_opening_angle_bracket(): void
	{
		// strip_tags() treats "<b>" as a tag, so everything from "<" is removed.
		// The point is that no markup survives — the exact trailing loss is fine.
		$this->assertSame('a', $this->sanitise('a<b>'));
	}

	public function test_it_strips_a_literal_greater_than_sign(): void
	{
		// A stray ">" with no preceding "<" is removed by the control/markup regex.
		$this->assertSame('ab', $this->sanitise('a>b'));
	}

	public function test_it_strips_control_characters(): void
	{
		$this->assertSame('AB', $this->sanitise("A\x00\x07\x1FB"));
	}

	public function test_it_preserves_unicode_letters_and_accents(): void
	{
		$this->assertSame('Zoë Müller', $this->sanitise('Zoë Müller'));
	}

	public function test_it_returns_empty_string_for_markup_only_input(): void
	{
		$this->assertSame('', $this->sanitise('<>'));
	}
}
