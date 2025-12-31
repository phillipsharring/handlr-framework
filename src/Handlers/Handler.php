<?php

declare(strict_types=1);

namespace Handlr\Handlers;

interface Handler
{
    public function handle(array|HandlerInput $input): ?HandlerResult;
}
