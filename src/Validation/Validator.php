<?php

declare(strict_types=1);

namespace Handlr\Validation;

use Handlr\Validation\Rules\RuleValidator;
use Handlr\Validation\Rules\RuleValidatorFactory;
use Handlr\Validation\Sanitizers\SanitizerFactory;

class Validator
{
    public function __construct(
        private readonly RuleValidatorFactory $ruleValidatorFactory,
        private readonly SanitizerFactory $sanitizerFactory,
    ) {}

    private const RULES_WITHOUT_SANITIZATION = [
        'required',
        'date',
        'array'
    ];
    private const SANITIZER_SUBSTITUTIONS = [
        'uuid'        => 'string',
        'uuid7'       => 'string',
        'gcsFilename' => 'string',
    ];
    private const VALID_DEFAULT_TYPES = ['int', 'string', 'bool', 'float', 'array'];

    private array $errors = [];
    private array $sanitized = [];

    public function errors(): array
    {
        return $this->errors;
    }

    public function sanitized(?string $key = null): mixed
    {
        if ($key === null) {
            return $this->sanitized;
        }

        return $this->sanitized[$key] ?? null;
    }

    public function isValid(): bool
    {
        return empty($this->errors);
    }

    public function validate(array $data, array $rules): bool
    {
        foreach ($rules as $field => $ruleSet) {
            $value = $data[$field] ?? null;
            $this->validateRule($data, $field, $ruleSet, $value);
        }

        return $this->isValid();
    }

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
     * This parses a SINGLE rule's string
     */
    private function parseRuleString(string $ruleString): array
    {
        // Split the rule string into the rule name and arguments string
        $ruleArray = explode('|', $ruleString, 2);

        // destructuring causes a warning when rule string was just one value, e.g. 'string'
        // so, don't do this: [ $ruleName, $argsString ] = explode...
        $ruleName = $ruleArray[0] ?? '';
        $argsString = $ruleArray[1] ?? '';

        // Parse the arguments string into a keyed array
        $ruleArgs = [];
        if ($argsString) {
            foreach (explode(',', $argsString) as $argString) {
                // Support both key:value and standalone flags (e.g., 'trim')
                if (str_contains($argString, ':')) {
                    [$key, $value] = explode(':', $argString, 2);
                    $ruleArgs[$key] = $value;
                } else {
                    // No colon means it's a boolean flag
                    $ruleArgs[$argString] = true;
                }
            }
        }

        // Return an array
        return [$ruleName, $ruleArgs];
    }

    private function addRuleErrors(RuleValidator $ruleValidator): void
    {
        $this->errors[$ruleValidator->field] = $ruleValidator->getErrorMessage();
        foreach ($ruleValidator->getOtherFieldErrors() as $oField => $oError) {
            $this->errors[$oField] = $oError;
        }
    }
}
