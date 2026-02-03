<?php

declare(strict_types=1);

namespace Handlr\Validation\Rules;

/**
 * Validates that a numeric value is at least a minimum value.
 *
 * Takes the minimum as the first positional arg (required).
 *
 * Usage: `'min|5'`, `'min|0'`, `'min|100'`
 *
 * @throws RuleException If minimum value is not provided or not numeric
 */
class MinRule extends BaseRule
{
    public function validate($value, array $ruleArgs = [], array $data = []): bool
    {
        $this->errorMessage = 'The %s field does not meet the minimum value requirement.';

        // make sure params[0] is a valid number
        if (!isset($ruleArgs[0]) || !is_numeric($ruleArgs[0])) {
            throw new RuleException('MinValidator requires a numeric parameter as the minimum value.');
        }

        $minValue = (float)$ruleArgs[0];

        if (!is_numeric($value)) {
            $this->errorMessage = 'The %s value must be a numeric value.';
            return false;
        }

        if ((float) $value < $minValue) {
            $this->errorMessage = "The %s value must be at least $minValue.";
            return false;
        }

        return true;
    }
}
