<?php

declare(strict_types=1);

namespace Handlr\Core;

use Handlr\Handlers\HandlerInput;
use Handlr\Handlers\Handler;

/**
 * Simple event dispatcher for registering and triggering event handlers.
 *
 * Allows multiple handlers to be registered for a single event name.
 * When an event is dispatched, all registered handlers are invoked in order.
 *
 * @example
 *     $events = new EventManager();
 *     $events->register('user.created', new SendWelcomeEmailHandler());
 *     $events->register('user.created', new CreateDefaultSettingsHandler());
 *
 *     // Later, dispatch the event
 *     $events->dispatch('user.created', $handlerInput);
 */
class EventManager
{
    /** @var array<string, Handler[]> Handlers indexed by event name */
    private array $handlers = [];

    /**
     * Register a handler for an event.
     *
     * Multiple handlers can be registered for the same event.
     * Handlers are invoked in the order they were registered.
     *
     * @param string $eventName The event identifier (e.g., 'user.created')
     * @param Handler $handler The handler to invoke when the event is dispatched
     */
    public function register(string $eventName, Handler $handler): void
    {
        $this->handlers[$eventName][] = $handler;
    }

    /**
     * Dispatch an event to all registered handlers.
     *
     * Invokes each handler's handle() method with the provided input.
     * If no handlers are registered for the event, this is a no-op.
     *
     * @param string $eventName The event identifier to dispatch
     * @param array|HandlerInput $input Data to pass to each handler
     */
    public function dispatch(string $eventName, array|HandlerInput $input = []): void
    {
        foreach ($this->handlers[$eventName] ?? [] as $handler) {
            $handler->handle($input);
        }
    }

    /**
     * Dispatch an event synchronously (alias for dispatch).
     *
     * Provided for semantic clarity when you want to explicitly indicate
     * synchronous execution. Currently identical to dispatch().
     *
     * @param string $eventName The event identifier to dispatch
     * @param array|HandlerInput $input Data to pass to each handler
     */
    public function dispatchNow(string $eventName, array|HandlerInput $input = []): void
    {
        $this->dispatch($eventName, $input);
    }
}
