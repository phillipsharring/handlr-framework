<?php

declare(strict_types=1);

namespace Handlr\Validation\Rules;

/**
 * Base class for validation rules.
 *
 * Extend this class to create new validation rules. Set `$errorMessage`
 * when validation fails (use `%s` as placeholder for field name).
 *
 * ```php
 * class MyRule extends BaseRule
 * {
 *     public function validate(mixed $value, array $ruleArgs, array $data = []): bool
 *     {
 *         if ($value !== 'expected') {
 *             $this->errorMessage = 'The %s field must be "expected".';
 *             return false;
 *         }
 *         return true;
 *     }
 * }
 * ```
 */
abstract class BaseRule implements RuleValidator
{
    /** Field name being validated */
    public string $field;

    /** Error message template (use %s for field name) */
    protected string $errorMessage = '';

    /** Errors for other fields (rare, used by rules like 'confirmed') */
    protected array $otherFieldErrors = [];

    public function setField(string $field): self
    {
        $this->field = $field;
        return $this;
    }

    abstract public function validate(mixed $value, array $ruleArgs, array $data = []): bool;

    public function getErrorMessage(): string
    {
        return sprintf($this->errorMessage, $this->field);
    }

    public function getOtherFieldErrors(): array
    {
        return $this->otherFieldErrors;
    }
}
