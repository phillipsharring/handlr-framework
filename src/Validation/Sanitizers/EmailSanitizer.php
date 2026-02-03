<?php

declare(strict_types=1);

namespace Handlr\Validation\Sanitizers;

/**
 * Sanitizes email addresses.
 *
 * Trims whitespace and removes illegal email characters.
 */
class EmailSanitizer implements Sanitizer
{
    public function sanitize($value, array $ruleArgs = []): string
    {
        return (string) trim(filter_var($value, FILTER_SANITIZE_EMAIL));
    }
}
