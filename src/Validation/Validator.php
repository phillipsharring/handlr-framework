<?php

declare(strict_types=1);

namespace Handlr\Validation;

use Handlr\Validation\Rules\RuleValidator;
use Handlr\Validation\Rules\RuleValidatorFactory;
use Handlr\Validation\Sanitizers\SanitizerFactory;

/**
 * Data validation and sanitization.
 *
 * Validates input data against rules and returns sanitized values.
 * Works with HandlerInput classes or standalone.
 *
 * ## Basic usage
 *
 * ```php
 * $validator = new Validator($ruleFactory, $sanitizerFactory);
 *
 * $isValid = $validator->validate($data, [
 *     'name' => ['required', 'string|min:2,max:100'],
 *     'email' => ['required', 'email'],
 *     'age' => ['int|min:0,max:120'],
 * ]);
 *
 * if ($isValid) {
 *     $sanitized = $validator->sanitized();  // All sanitized values
 *     $name = $validator->sanitized('name'); // Single value
 * } else {
 *     $errors = $validator->errors();  // ['field' => 'error message', ...]
 * }
 * ```
 *
 * ## Rule syntax (DIFFERENT FROM LARAVEL!)
 *
 * Rules use `|` to separate rule name from arguments, and `,` between arguments:
 *
 * ```
 * 'ruleName|arg1,arg2,key:value'
 * ```
 *
 * **NOT like Laravel's `rule:arg1,arg2`** - we use `rule|arg1,arg2`
 *
 * ### Examples
 *
 * ```php
 * // Type rules
 * 'string'                    // Must be a string
 * 'string|min:3'              // String, at least 3 chars
 * 'string|max:100'            // String, at most 100 chars
 * 'string|min:3,max:100'      // String, 3-100 chars
 * 'int'                       // Must be an integer
 * 'int|min:0'                 // Integer >= 0
 * 'int|max:100'               // Integer <= 100
 * 'float'                     // Must be a float
 * 'bool'                      // Must be a boolean
 * 'array'                     // Must be an array
 *
 * // Validation rules
 * 'required'                  // Field must be present and not empty
 * 'email'                     // Must be valid email
 * 'url'                       // Must be valid URL
 * 'uuid'                      // Must be valid UUID
 * 'uuid7'                     // Must be valid UUIDv7
 * 'date'                      // Must be valid date
 * 'in|active,pending,done'    // Must be one of these values
 * 'confirmed'                 // Must match {field}_confirmation
 *
 * // Database rules (require container/db)
 * 'exists|table:users,column:id'     // Must exist in database
 * 'unique|table:users,column:email'  // Must not exist in database
 * ```
 *
 * ## Nullable fields with defaults
 *
 * ```php
 * $rules = [
 *     'status' => ['nullable', 'string', 'default|active'],  // Default if empty
 *     'count' => ['nullable', 'int', 'default|0'],
 *     'enabled' => ['nullable', 'bool', 'default|true'],
 * ];
 * ```
 *
 * ## In HandlerInput classes
 *
 * ```php
 * class CreateUserInput implements HandlerInput
 * {
 *     public string $name;
 *     public string $email;
 *     public int $age;
 *
 *     public function __construct(array $body = [], ?Validator $validator = null)
 *     {
 *         $validator?->validate($body, [
 *             'name' => ['required', 'string|min:2,max:100'],
 *             'email' => ['required', 'email'],
 *             'age' => ['required', 'int|min:0,max:120'],
 *         ]);
 *
 *         if ($validator && !$validator->isValid()) {
 *             throw new ValidationException(json_encode($validator->errors()));
 *         }
 *
 *         // Use sanitized values
 *         $this->name = $validator?->sanitized('name') ?? $body['name'];
 *         $this->email = $validator?->sanitized('email') ?? $body['email'];
 *         $this->age = $validator?->sanitized('age') ?? $body['age'];
 *     }
 * }
 * ```
 *
 * ## Available rules
 *
 * | Rule | Description | Example |
 * |------|-------------|---------|
 * | `required` | Must be present and not empty | `'required'` |
 * | `nullable` | Allow null/empty (skip other rules) | `'nullable'` |
 * | `string` | Must be string, optional min/max | `'string\|min:1,max:255'` |
 * | `int` | Must be integer, optional min/max | `'int\|min:0'` |
 * | `float` | Must be float | `'float'` |
 * | `bool` | Must be boolean | `'bool'` |
 * | `array` | Must be array | `'array'` |
 * | `email` | Valid email address | `'email'` |
 * | `url` | Valid URL | `'url'` |
 * | `uuid` | Valid UUID | `'uuid'` |
 * | `uuid7` | Valid UUIDv7 | `'uuid7'` |
 * | `date` | Valid date | `'date'` |
 * | `in` | Must be in list | `'in\|a,b,c'` |
 * | `min` | Minimum numeric value | `'min\|5'` |
 * | `confirmed` | Must match `{field}_confirmation` | `'confirmed'` |
 * | `exists` | Must exist in DB | `'exists\|table:users,column:id'` |
 * | `unique` | Must not exist in DB | `'unique\|table:users,column:email'` |
 */
class Validator
{
    public function __construct(
        private readonly RuleValidatorFactory $ruleValidatorFactory,
        private readonly SanitizerFactory $sanitizerFactory,
    ) {}

    /** Rules that don't have corresponding sanitizers */
    private const RULES_WITHOUT_SANITIZATION = [
        'required',
        'date',
        'array',
        'in',
    ];

    /** Map rule names to sanitizer names when they differ */
    private const SANITIZER_SUBSTITUTIONS = [
        'uuid'  => 'string',
        'uuid7' => 'string',
    ];

    /** Types that support default value casting */
    private const VALID_DEFAULT_TYPES = ['int', 'string', 'bool', 'float', 'array'];

    private array $errors = [];
    private array $sanitized = [];

    /**
     * Get validation errors.
     *
     * @return array<string, string> Field => error message
     */
    public function errors(): array
    {
        return $this->errors;
    }

    /**
     * Get sanitized values.
     *
     * ```php
     * $all = $validator->sanitized();        // All values
     * $name = $validator->sanitized('name'); // Single value
     * ```
     *
     * @param string|null $key Specific field, or null for all
     *
     * @return mixed Sanitized value(s)
     */
    public function sanitized(?string $key = null): mixed
    {
        if ($key === null) {
            return $this->sanitized;
        }

        return $this->sanitized[$key] ?? null;
    }

    /**
     * Check if validation passed.
     *
     * @return bool True if no errors
     */
    public function isValid(): bool
    {
        return empty($this->errors);
    }

    /**
     * Validate data against rules.
     *
     * @param array $data  Input data to validate
     * @param array $rules Validation rules per field
     *
     * @return bool True if all rules pass
     */
    public function validate(array $data, array $rules): bool
    {
        foreach ($rules as $field => $ruleSet) {
            $value = $data[$field] ?? null;
            $this->validateRule($data, $field, $ruleSet, $value);
        }

        return $this->isValid();
    }

    /**
     * Validate a single field against its rule set.
     */
    private function validateRule(array $data, string $field, array $ruleSet, $value): void
    {
        $isNullable = in_array('nullable', $ruleSet, true);

        if ($isNullable) {
            $default = $this->getDefaultValue($field, $ruleSet);

            if (empty($value) && $value !== '0' && $value !== 0) {
                $this->sanitized[$field] = $default ? $this->castDefaultValue($default, $ruleSet) : null;
                return;
            }
        }

        foreach ($ruleSet as $rule) {
            if ($rule === 'nullable') {
                continue;
            }

            [$ruleName, $ruleArgs] = $this->parseRuleString($rule);

            $ruleValidator = $this->ruleValidatorFactory->create($ruleName, $field);
            if (!$ruleValidator->validate($value, $ruleArgs, $data)) {
                $this->addRuleErrors($ruleValidator);
                return;
            }

            if (in_array($ruleName, self::RULES_WITHOUT_SANITIZATION, true)) {
                $this->sanitized[$field] = $value;
                continue;
            }

            $sanitizerName = self::SANITIZER_SUBSTITUTIONS[$ruleName] ?? $ruleName;
            $valueSanitizer = $this->sanitizerFactory->create($sanitizerName);
            $this->sanitized[$field] = $valueSanitizer->sanitize($value, $ruleArgs);
        }
    }

    /**
     * Extract default value from rule set (e.g., 'default|value').
     */
    private function getDefaultValue(string $field, array &$ruleSet): ?string
    {
        foreach ($ruleSet as $key => $rule) {
            if (strpos($rule, 'default|') === false) {
                continue;
            }

            [, $defaultValue] = explode('|', $rule, 2);
            unset($ruleSet[$key]);

            return $defaultValue;
        }

        return null;
    }

    /**
     * Cast default value to the expected type.
     */
    private function castDefaultValue(?string $defaultValue, array $ruleSet): mixed
    {
        if ($defaultValue === null) {
            return null;
        }

        $expectedType = current(array_intersect($ruleSet, self::VALID_DEFAULT_TYPES)) ?: 'string';

        return match ($expectedType) {
            'int'    => is_numeric($defaultValue) ? (int) $defaultValue : null,
            'float'  => is_numeric($defaultValue) ? (float) $defaultValue : null,
            'bool'   => in_array($defaultValue, ['true', 'false', '1', '0', 'on', 'off'], true)
                ? filter_var($defaultValue, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE)
                : null,
            'array'  => explode(',', $defaultValue),
            default  => (string) $defaultValue,
        };
    }

    /**
     * Parse a rule string into name and arguments.
     *
     * Format: `'ruleName|arg1,arg2,key:value'`
     *
     * - `|` separates rule name from arguments
     * - `,` separates multiple arguments
     * - `:` separates key from value in named args
     *
     * Examples:
     * - `'string'` → `['string', []]`
     * - `'string|min:3'` → `['string', ['min' => '3']]`
     * - `'string|min:3,max:100'` → `['string', ['min' => '3', 'max' => '100']]`
     * - `'in|a,b,c'` → `['in', ['a' => true, 'b' => true, 'c' => true]]`
     */
    private function parseRuleString(string $ruleString): array
    {
        $ruleArray = explode('|', $ruleString, 2);

        $ruleName = $ruleArray[0] ?? '';
        $argsString = $ruleArray[1] ?? '';

        $ruleArgs = [];
        if ($argsString) {
            foreach (explode(',', $argsString) as $argString) {
                if (str_contains($argString, ':')) {
                    [$key, $value] = explode(':', $argString, 2);
                    $ruleArgs[$key] = $value;
                } else {
                    // No colon means it's a boolean flag or enum value
                    $ruleArgs[$argString] = true;
                }
            }
        }

        return [$ruleName, $ruleArgs];
    }

    /**
     * Add errors from a rule validator to the error list.
     */
    private function addRuleErrors(RuleValidator $ruleValidator): void
    {
        $this->errors[$ruleValidator->field] = $ruleValidator->getErrorMessage();
        foreach ($ruleValidator->getOtherFieldErrors() as $oField => $oError) {
            $this->errors[$oField] = $oError;
        }
    }
}
