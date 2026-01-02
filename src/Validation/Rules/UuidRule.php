<?php

declare(strict_types=1);

namespace Handlr\Validation\Rules;

class UuidRule extends BaseRule
{
    private const REGEX = '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/';

    public function validate($value, array $ruleArgs, array $data = []): bool
    {
        if (!preg_match(self::REGEX, $value)) {
            $this->errorMessage = 'The %s field must be a valid UUID.';
            return false;
        }

        return true;
    }
}
