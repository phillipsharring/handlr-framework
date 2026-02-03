<?php

declare(strict_types=1);

namespace Handlr\Validation\Rules;

/**
 * Validates that a value is boolean or boolean-like.
 *
 * Accepts: `true`, `false`, `'true'`, `'false'`, `'yes'`, `'no'`, `'y'`, `'n'`,
 * `'1'`, `'0'`, `'on'`, `'off'`, `1`, `0`
 *
 * Usage: `'bool'`
 */
class BoolRule extends BaseRule
{
    public function validate($value, array $ruleArgs = [], array $data = []): bool
    {
        // Native booleans are valid, of course
        if (is_bool($value)) {
            return true;
        }

        $error = 'The %s value must be a valid yes/no value (e.g. true, false, yes, no, 1, 0).';

        // Reject arrays and objects
        if (is_array($value) || is_object($value)) {
            $this->errorMessage = $error;
            return false;
        }

        // Sanitize string/number input
        $bool = strtolower(trim((string)$value));

        // 'y' and 'n' aren't covered by FILTER_VALIDATE_BOOL
        if ($bool === 'y' || $bool === 'n') {
            return true;
        }

        $bool = strtolower(trim((string)$value));
        $isValid = !($bool === '' || filter_var($bool, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE) === null);

        if (!$isValid) {
            $this->errorMessage = $error;
        }

        return $isValid;
    }
}
