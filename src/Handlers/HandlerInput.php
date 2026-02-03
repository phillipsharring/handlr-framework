<?php

declare(strict_types=1);

namespace Handlr\Handlers;

use Handlr\Validation\Validator;

/**
 * Interface for typed handler input objects.
 *
 * Input objects extract, sanitize, and validate raw request data into
 * strongly-typed properties. This separates input handling from business
 * logic and provides a clear contract for what data a handler expects.
 *
 * ## Basic implementation
 *
 * ```php
 * class CreateUserInput implements HandlerInput
 * {
 *     public string $name;
 *     public string $email;
 *     public ?string $bio = null;
 *
 *     public function __construct(array $body = [], ?Validator $validator = null)
 *     {
 *         $this->name = trim($body['name'] ?? '');
 *         $this->email = strtolower(trim($body['email'] ?? ''));
 *         $this->bio = isset($body['bio']) ? trim($body['bio']) : null;
 *
 *         $validator?->validate([
 *             'name' => ['required', 'min:2', 'max:100'],
 *             'email' => ['required', 'email'],
 *             'bio' => ['max:500'],
 *         ], $this);
 *     }
 * }
 * ```
 *
 * ## With validation
 *
 * ```php
 * class UpdateProfileInput implements HandlerInput
 * {
 *     public string $displayName;
 *     public array $settings;
 *
 *     public function __construct(array $body = [], ?Validator $validator = null)
 *     {
 *         $this->displayName = trim($body['display_name'] ?? '');
 *         $this->settings = $body['settings'] ?? [];
 *
 *         // Validator throws ValidationException on failure
 *         $validator?->validate([
 *             'displayName' => ['required', 'min:1', 'max:50'],
 *             'settings' => ['array'],
 *         ], $this);
 *     }
 * }
 * ```
 *
 * ## Usage from Request
 *
 * ```php
 * // In a Pipe - automatically injects validator
 * $input = $request->asInput(CreateUserInput::class);
 *
 * // Manual instantiation (e.g., in tests)
 * $input = new CreateUserInput(['name' => 'John', 'email' => 'john@example.com']);
 * ```
 *
 * ## Usage in Handler
 *
 * ```php
 * class CreateUserHandler implements Handler
 * {
 *     public function handle(array|HandlerInput $input): ?HandlerResult
 *     {
 *         // Type-safe access to validated properties
 *         $user = new UserRecord([
 *             'name' => $input->name,
 *             'email' => $input->email,
 *             'bio' => $input->bio,
 *         ]);
 *
 *         // ...
 *     }
 * }
 * ```
 *
 * ## Benefits
 *
 * - **Type safety**: Properties are strongly typed
 * - **Validation**: Rules enforced at construction time
 * - **Sanitization**: Data cleaned before use (trim, lowercase, etc.)
 * - **Testability**: Easy to instantiate with test data
 * - **Documentation**: Input class clearly defines expected data shape
 */
interface HandlerInput
{
    /**
     * Create a new input instance from raw data.
     *
     * @param array          $body      Raw input data (typically from request body)
     * @param Validator|null $validator Optional validator for input validation
     */
    public function __construct(array $body = [], ?Validator $validator = null);
}
