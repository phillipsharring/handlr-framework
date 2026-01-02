<?php

declare(strict_types=1);

namespace Handlr\Validation\Sanitizers;

class EmailSanitizer implements Sanitizer
{
    public function sanitize($value, array $ruleArgs = []): string
    {
        return (string) trim(filter_var($value, FILTER_SANITIZE_EMAIL));
    }
}
