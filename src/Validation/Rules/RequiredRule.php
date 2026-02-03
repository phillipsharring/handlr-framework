<?php

declare(strict_types=1);

namespace Handlr\Validation\Rules;

/**
 * Validates that a value is present and non-empty.
 *
 * Fails on: `null`, `''` (empty string), `[]` (empty array)
 *
 * Usage: `'required'`
 */
class RequiredRule extends BaseRule
{
    public function validate($value, array $ruleArgs, array $data = []): bool
    {
        $isValid = true;

        if ($value === null) {
            $isValid = false;
            $this->errorMessage = 'The %s field is required.';
        }

        if ($isValid && is_string($value)) {
            $isValid = trim($value) !== '';
            $this->errorMessage = 'The %s cannot be an empty string.';
        }

        if ($isValid && is_array($value)) {
            $isValid = !empty($value);
            $this->errorMessage = 'The %s cannot be an empty array.';
        }

        return $isValid;
    }
}
