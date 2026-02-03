<?php

declare(strict_types=1);

namespace Handlr\Validation\Sanitizers;

/**
 * Sanitizes URLs by removing illegal URL characters.
 */
class UrlSanitizer implements Sanitizer
{
    public function sanitize(mixed $value, array $ruleArgs = []): string
    {
        return (string)filter_var($value, FILTER_SANITIZE_URL);
    }
}
