<?php

declare(strict_types=1);

namespace Handlr\Validation\Rules;

class UrlRule extends BaseRule
{
    public function validate($value, array $ruleArgs = [], array $data = []): bool
    {
        if (!filter_var($value, FILTER_VALIDATE_URL)) {
            $this->errorMessage = 'The %s value must be a valid URL.';
            return false;
        }

        return true;
    }
}
