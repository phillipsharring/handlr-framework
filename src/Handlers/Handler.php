<?php

declare(strict_types=1);

namespace Handlr\Handlers;

/**
 * Interface for business logic handlers.
 *
 * Handlers encapsulate domain/business logic separate from HTTP concerns.
 * They receive typed input and return structured results, making them
 * easy to test and reuse across different contexts (HTTP, CLI, events, etc.).
 *
 * ## Basic implementation
 *
 * ```php
 * class CreateUserHandler implements Handler
 * {
 *     public function __construct(
 *         private UsersTable $users,
 *         private Mailer $mailer
 *     ) {}
 *
 *     public function handle(array|HandlerInput $input): ?HandlerResult
 *     {
 *         // $input is typically a typed HandlerInput object
 *         $user = new UserRecord([
 *             'name' => $input->name,
 *             'email' => $input->email,
 *         ]);
 *
 *         $this->users->insert($user);
 *         $this->mailer->sendWelcome($user);
 *
 *         return HandlerResult::ok(['user' => $user->toArray()]);
 *     }
 * }
 * ```
 *
 * ## Usage with a Pipe (HTTP layer)
 *
 * ```php
 * class CreateUserPipe implements Pipe
 * {
 *     public function handle(Request $request): Response
 *     {
 *         $input = $request->asInput(CreateUserInput::class);
 *         $handler = new CreateUserHandler($this->users, $this->mailer);
 *         $result = $handler->handle($input);
 *
 *         return $result?->toResponse() ?? Response::json(['error' => 'No result']);
 *     }
 * }
 * ```
 *
 * ## Usage in tests
 *
 * ```php
 * public function testCreatesUser(): void
 * {
 *     $handler = new CreateUserHandler($this->users, $this->mailer);
 *     $input = new CreateUserInput(['name' => 'John', 'email' => 'john@example.com']);
 *
 *     $result = $handler->handle($input);
 *
 *     $this->assertTrue($result->success);
 *     $this->assertEquals('John', $result->data['user']['name']);
 * }
 * ```
 *
 * ## Usage from CLI or events
 *
 * ```php
 * // CLI command
 * $handler = $container->get(CreateUserHandler::class);
 * $result = $handler->handle(['name' => 'John', 'email' => 'john@example.com']);
 *
 * // Event listener
 * $handler->handle($event->payload);
 * ```
 */
interface Handler
{
    /**
     * Execute the handler's business logic.
     *
     * @param array|HandlerInput $input Raw array or typed input object
     *
     * @return HandlerResult|null Result with success/failure status and data
     */
    public function handle(array|HandlerInput $input): ?HandlerResult;
}
