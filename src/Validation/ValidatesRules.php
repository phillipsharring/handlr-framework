<?php

declare(strict_types=1);

namespace Handlr\Validation;

/**
 * Trait for adding validation to Input classes.
 *
 * Use this trait in HandlerInput classes to validate request data. Implement
 * `getValidator()` and `getBody()`, then call `runValidation()` with your rules.
 *
 * ## Usage
 *
 * ```php
 * class LoginInput implements HandlerInput
 * {
 *     use ValidatesRules;
 *
 *     public string $email;
 *     public string $password;
 *
 *     public function __construct(private array $body, private Validator $validator)
 *     {
 *         $this->email = $body['email'] ?? '';
 *         $this->password = $body['password'] ?? '';
 *     }
 *
 *     protected function getValidator(): Validator { return $this->validator; }
 *     protected function getBody(): array { return $this->body; }
 *
 *     public function validate(): array
 *     {
 *         return $this->runValidation([
 *             'email' => ['required', 'email'],
 *             'password' => ['required'],
 *         ]);
 *     }
 * }
 * ```
 *
 * ## Multiple Validation Methods
 *
 * Create different validation methods for different operations:
 *
 * ```php
 * public function validateForCreate(): array
 * {
 *     return $this->runValidation([
 *         'name' => ['required', 'string|min:1,max:255'],
 *         'description' => ['nullable', 'string'],
 *     ]);
 * }
 *
 * public function validateForUpdate(): array
 * {
 *     return $this->runValidation([
 *         'id' => ['required', 'uuid'],
 *         'name' => ['required', 'string|min:1,max:255'],
 *     ]);
 * }
 * ```
 *
 * @see Validator For rule syntax details
 */
trait ValidatesRules
{
    /** Return the Validator instance (typically injected via constructor) */
    abstract protected function getValidator(): Validator;

    /** Return the raw request body data */
    abstract protected function getBody(): array;

    /**
     * Run validation and return errors.
     *
     * @param array $rules Validation rules keyed by field name
     * @return array Errors array (empty if valid)
     */
    public function runValidation(array $rules): array
    {
        $validator = $this->getValidator();
        $validator->validate($this->getBody(), $rules);
        return $validator->errors();
    }
}
