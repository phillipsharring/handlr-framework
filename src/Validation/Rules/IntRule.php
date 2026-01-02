<?php

declare(strict_types=1);

namespace Handlr\Validation\Rules;

class IntRule extends BaseRule
{
    public function validate($value, array $ruleArgs = [], array $data = []): bool
    {
        $isValid = true;

        // Check if the value is numeric and an integer
        // Yes, this should be non type-safe comparison, == instead of ===
        // Otherwise we will fail validation on '25', but everything coming in
        // from HTTP is going to be a string
        /** @noinspection TypeUnsafeComparisonInspection */
        if (!is_numeric($value) || (int)$value != $value) { // NOSONAR
            $this->errorMessage = 'The %s value must be an integer.';
            $isValid = false;
        }

        // Check minimum value
        if ($isValid && ($ruleArgs['min'] ?? false) && $value < $ruleArgs['min']) {
            $this->errorMessage = 'The %s value must be at least ' . $ruleArgs['min'] . '.';
            $isValid = false;
        }

        // Check maximum value
        if ($isValid && ($ruleArgs['max'] ?? false) && $value > $ruleArgs['max']) {
            $this->errorMessage = 'The %s value must not exceed ' . $ruleArgs['max'] . '.';
            $isValid = false;
        }

        return $isValid;
    }
}
