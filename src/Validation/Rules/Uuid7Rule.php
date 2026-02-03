<?php

declare(strict_types=1);

namespace Handlr\Validation\Rules;

/**
 * Validates that a value is a valid UUIDv7 (time-ordered UUID).
 *
 * UUIDv7 is the recommended format for database primary keys (sortable by time).
 *
 * Usage: `'uuid7'`
 */
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
