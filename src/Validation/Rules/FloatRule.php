<?php

declare(strict_types=1);

namespace Handlr\Validation\Rules;

class FloatRule extends BaseRule
{
    public function validate($value, array $ruleArgs = [], array $data = []): bool
    {
        $isValid = true;

        if (!is_numeric($value)) {
            $this->errorMessage = 'The %s value must be a numeric value.';
            $isValid = false;
        }

        // Check minimum value
        if ($isValid && ($ruleArgs['min'] ?? false) && (float)$value < (float)$ruleArgs['min']) {
            $this->errorMessage = 'The %s value must be at least ' . $ruleArgs['min'] . '.';
            $isValid = false;
        }

        // Check maximum value
        if ($isValid && ($ruleArgs['max'] ?? false) && (float)$value > (float)$ruleArgs['max']) {
            $this->errorMessage = 'The %s value must not exceed ' . $ruleArgs['max'] . '.';
            $isValid = false;
        }

        return $isValid;
    }
}
