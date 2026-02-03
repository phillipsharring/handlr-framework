<?php

declare(strict_types=1);

namespace Handlr\Validation\Rules;

/**
 * Validates that a field has a matching confirmation field.
 *
 * Expects a `{field}_confirmation` field in the input data. For example,
 * if validating `password`, expects `password_confirmation` to match.
 *
 * Usage: `'confirmed'` (apply to the base field, e.g., password)
 */
class ConfirmedRule extends BaseRule
{
    public function validate($value, array $ruleArgs = [], array $data = []): bool
    {
        $confirmationField = "{$this->field}_confirmation";
        $confirmationValue = $data[$confirmationField] ?? null;

        if ($value !== $confirmationValue) {
            $this->otherFieldErrors[$confirmationField] = "This confirmation does not match the {$this->field}.";
            return false;
        }

        return true;
    }
}
