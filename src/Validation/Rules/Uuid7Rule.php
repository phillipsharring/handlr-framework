<?php

declare(strict_types=1);

namespace Handlr\Validation\Rules;

class Uuid7Rule extends BaseRule
{
    // Canonical hyphenated UUIDv7 + correct variant.
    private const REGEX = '/^[0-9a-f]{8}-[0-9a-f]{4}-7[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i';

    public function validate($value, array $ruleArgs, array $data = []): bool
    {
        if (!preg_match(self::REGEX, $value)) {
            $this->errorMessage = 'The %s field must be a valid UUIDv7.';
            return false;
        }

        return true;
    }
}
