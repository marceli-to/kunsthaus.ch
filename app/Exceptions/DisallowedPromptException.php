<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Thrown when visitor input fails the local input guardrail (denylist),
 * before any paid provider call. The controller maps this to HTTP 422.
 */
class DisallowedPromptException extends RuntimeException
{
}
