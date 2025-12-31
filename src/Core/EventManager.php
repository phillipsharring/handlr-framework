<?php

declare(strict_types=1);

namespace Handlr\Core;

use Handlr\Handlers\HandlerInput;
use Handlr\Handlers\Handler;

class EventManager
{
    private array $handlers = [];

    public function register(string $eventName, Handler $handler): void
    {
        $this->handlers[$eventName][] = $handler;
    }

    public function dispatch(string $eventName, array|HandlerInput $input = []): void
    {
        foreach ($this->handlers[$eventName] ?? [] as $handler) {
            $handler->handle($input);
        }
    }

    public function dispatchNow(string $eventName, array|HandlerInput $input = []): void
    {
        $this->dispatch($eventName, $input);
    }
}
