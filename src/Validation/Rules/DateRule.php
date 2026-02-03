<?php

declare(strict_types=1);

namespace Handlr\Validation\Rules;

use DateTime;

/**
 * Validates that a value is a valid date string.
 *
 * Rule args:
 * - `format` - Date format (default: `'Y-m-d'`)
 *
 * Usage: `'date'`, `'date|format:Y-m-d H:i:s'`, `'date|format:m/d/Y'`
 */
class DateRule extends BaseRule
{
    public function validate($value, array $ruleArgs = [], array $data = []): bool
    {
        // is the date valid?
        $format = $ruleArgs['format'] ?? 'Y-m-d';
        $date = DateTime::createFromFormat($format, $value);

        if (!$date || $date->format($format) !== $value) {
            $this->errorMessage = "The %s value must be a valid date in the format $format.";
            return false;
        }

        return true;
    }
}
