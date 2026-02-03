<?php

declare(strict_types=1);

namespace Handlr\Validation\Rules;

/**
 * Validates that a value is a valid RFC4122 UUID (any version 1-8).
 *
 * Expects canonical hyphenated format: `xxxxxxxx-xxxx-Mxxx-Nxxx-xxxxxxxxxxxx`
 *
 * Usage: `'uuid'`
 */
class UuidRule extends BaseRule
{
    // Canonical hyphenated UUID, any RFC4122 version (1-8) + correct variant.
    private const REGEX = '/^[0-9a-f]{8}-[0-9a-f]{4}-[1-8][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i';

    public function validate($value, array $ruleArgs, array $data = []): bool
    {
        if (!preg_match(self::REGEX, $value)) {
            $this->errorMessage = 'The %s field must be a valid UUID.';
            return false;
        }

        return true;
    }
}
