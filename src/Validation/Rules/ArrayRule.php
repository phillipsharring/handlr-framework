<?php

declare(strict_types=1);

namespace Handlr\Validation\Rules;

/**
 * Validates that a value is an array. Also accepts valid JSON strings.
 *
 * Usage: `'array'`
 */
class ArrayRule extends BaseRule
{
    public function validate($value, array $ruleArgs = [], array $data = []): bool
    {
        $isValid = true;
        if (is_string($value)) {
            $decoded = json_decode($value, true);
            if (json_last_error() !== JSON_ERROR_NONE || !is_array($decoded)) {
                $this->errorMessage = 'Bad JSON';
                $isValid = false;
            }
        }

        if (!is_array($value)) {
            $this->errorMessage = 'The value must be an array.';
            $isValid = false;
        }
        return $isValid;
    }
}
