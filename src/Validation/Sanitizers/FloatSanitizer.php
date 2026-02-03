<?php

declare(strict_types=1);

namespace Handlr\Validation\Sanitizers;

/**
 * Sanitizes values to floats.
 *
 * Strips non-numeric characters while preserving decimal points.
 */
class FloatSanitizer implements Sanitizer
{
    public function sanitize($value, array $ruleArgs = []): float
    {
        return (float)filter_var(
            $value,
            FILTER_SANITIZE_NUMBER_FLOAT,
            FILTER_FLAG_ALLOW_FRACTION | FILTER_FLAG_ALLOW_THOUSAND
        );
    }
}
