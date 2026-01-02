<?php

declare(strict_types=1);

namespace Handlr\Validation\Sanitizers;

interface Sanitizer
{
    public function sanitize(mixed $value, array $ruleArgs = []);
}
