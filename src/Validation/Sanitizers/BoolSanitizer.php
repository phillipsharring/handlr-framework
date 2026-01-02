<?php

declare(strict_types=1);

namespace Handlr\Validation\Sanitizers;

class BoolSanitizer implements Sanitizer
{
    public function sanitize($value, array $ruleArgs = []): bool
    {
        $bool = is_array($value) ? 'false' : strtolower(trim((string)$value));
        return match ($bool) {
            'y' => true,
            'n' => false,
            default => filter_var(strtolower($bool), FILTER_VALIDATE_BOOL),
        };
    }
}
