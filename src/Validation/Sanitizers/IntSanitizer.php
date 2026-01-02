<?php

declare(strict_types=1);

namespace Handlr\Validation\Sanitizers;

class IntSanitizer implements Sanitizer
{
    public function sanitize($value, array $ruleArgs = []): int
    {
        // Use FILTER_SANITIZE_NUMBER_FLOAT w/ FILTER_FLAG_ALLOW_FRACTION so
        // that we can strip the decimals. That way, 123.45 becomes 123
        // instead of 12345, which is not what we want
        return (int)filter_var($value, FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION | FILTER_FLAG_ALLOW_THOUSAND);
    }
}
