<?php

declare(strict_types=1);

namespace Handlr\Validation\Rules;

/**
 * Validates that a value is valid JSON.
 *
 * Accepts:
 * - A JSON string (must decode without error)
 * - An array or object (inherently JSON-encodable)
 *
 * Usage: `'json'`
 */
class JsonRule extends BaseRule
{
    public function validate($value, array $ruleArgs = [], array $data = []): bool
    {
        // Arrays and objects are inherently valid JSON-encodable values
        if (is_array($value) || is_object($value)) {
            return true;
        }

        if (!is_string($value)) {
            $this->errorMessage = 'The %s field must be valid JSON.';
            return false;
        }

        json_decode($value);

        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->errorMessage = 'The %s field must be valid JSON.';
            return false;
        }

        return true;
    }
}
