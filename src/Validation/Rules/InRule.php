<?php

declare(strict_types=1);

namespace Handlr\Validation\Rules;

class InRule extends BaseRule
{
    public function validate($value, array $ruleArgs = [], array $data = []): bool
    {
        // Parser stores "in|a,b,c" as ['a' => true, 'b' => true, 'c' => true]
        // So the allowed values are the keys, not the values
        $allowedValues = array_keys($ruleArgs);

        if (!in_array($value, $allowedValues, true)) {
            $this->errorMessage = 'The %s value must be one of ' . implode(', ', $allowedValues) . '.';
            return false;
        }

        return true;
    }
}
