<?php

declare(strict_types=1);

namespace Handlr\Validation\Sanitizers;

/**
 * Sanitizes string values by removing control characters.
 *
 * Rule args:
 * - `trim` - Trim whitespace from both ends
 * - `strip_tags` - Remove HTML/PHP tags
 *
 * Always removes control characters (0x00-0x1F, 0x7F).
 */
class StringSanitizer implements Sanitizer
{
    public function sanitize($value, array $ruleArgs = []): string
    {
        // trim whitespace if specified
        if ($ruleArgs['trim'] ?? false) {
            $value = trim($value);
        }

        // optionally strip unwanted characters
        if ($ruleArgs['strip_tags'] ?? false) {
            $value = strip_tags($value);
        }

        // remove control characters or other unwanted characters
        return (string)preg_replace('/[\x00-\x1F\x7F]/', '', $value);
    }
}
