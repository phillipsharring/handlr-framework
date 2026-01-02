<?php

declare(strict_types=1);

namespace Handlr\Validation\Rules;

class EmailRule extends BaseRule
{
    public function validate($value, array $ruleArgs = [], array $data = []): bool
    {
        $email = trim($value);
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->errorMessage = 'The %s value must be a valid email address.';
            return false;
        }

        return true;
    }
}
