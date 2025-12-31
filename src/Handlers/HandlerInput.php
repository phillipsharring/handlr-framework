<?php

declare(strict_types=1);

namespace Handlr\Handlers;

use Handlr\Validation\Validator;

interface HandlerInput
{
    public function __construct(array $body, ?Validator $validator = null);
}
