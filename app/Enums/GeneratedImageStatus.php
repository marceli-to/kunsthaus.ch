<?php

namespace App\Enums;

/**
 * Moderation lifecycle for a submitted JAtelier image. Backed by a string so it
 * casts cleanly to the SQLite `status` column (no native enum type there).
 *
 *   submitted → the visitor confirmed + consented; awaits review.
 *   published → a moderator approved it; the creator is notified once.
 *   rejected  → a moderator declined it; silent (no notification).
 */
enum GeneratedImageStatus: string
{
	case Submitted = 'submitted';
	case Published = 'published';
	case Rejected = 'rejected';

	/**
	 * German label for the Control Panel.
	 */
	public function label(): string
	{
		return match ($this) {
			self::Submitted => 'Eingereicht',
			self::Published => 'Veröffentlicht',
			self::Rejected => 'Abgelehnt',
		};
	}
}
