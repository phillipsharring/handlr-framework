<?php

declare(strict_types=1);

namespace Handlr\Validation\Rules;

/**
 * Validates that a value is a string with optional length constraints.
 *
 * Rule args:
 * - `min` - Minimum string length
 * - `max` - Maximum string length
 *
 * Usage: `'string'`, `'string|min:3'`, `'string|max:100'`, `'string|min:3,max:100'`
 */
class StringRule extends BaseRule
{
    public function validate($value, array $ruleArgs = [], array $data = []): bool
    {
        $isValid = true;

        if (!is_string($value)) {
            $this->errorMessage = 'The value must be a string.';
            $isValid = false;
        }

        // max length
        if ($isValid && ($ruleArgs['max'] ?? false) && strlen($value) > $ruleArgs['max']) {
            $this->errorMessage = 'The %s value must not exceed ' . $ruleArgs['max'] . ' characters.';
            $isValid = false;
        }

        // min length
        if ($isValid && ($ruleArgs['min'] ?? false) && strlen($value) < $ruleArgs['min']) {
            $this->errorMessage = 'The %s value must be at least ' . $ruleArgs['min'] . ' characters long.';
            $isValid = false;
        }

        return $isValid;
    }
}
