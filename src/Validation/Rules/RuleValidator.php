<?php

declare(strict_types=1);

namespace Handlr\Validation\Rules;

/**
 * Interface for validation rules.
 *
 * Each rule validates a specific constraint (e.g., required, email, min length).
 * Rules are instantiated by RuleValidatorFactory based on the rule name.
 */
interface RuleValidator
{
    /**
     * Validate a value against this rule.
     *
     * @param mixed $value    The value to validate
     * @param array $ruleArgs Arguments parsed from the rule string (e.g., ['min' => '3'])
     * @param array $data     Full input data (for rules that need other fields)
     *
     * @return bool True if valid
     */
    public function validate(mixed $value, array $ruleArgs, array $data = []): bool;

    /**
     * Set the field name being validated (for error messages).
     */
    public function setField(string $field): self;

    /**
     * Get the error message for this rule.
     */
    public function getErrorMessage(): string;

    /**
     * Get errors for other fields (e.g., confirmed rule affects two fields).
     *
     * @return array<string, string> Field => error message
     */
    public function getOtherFieldErrors(): array;
}
