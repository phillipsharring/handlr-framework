<?php

declare(strict_types=1);

namespace Handlr\Validation;

use Handlr\Core\Container\Container;
use Handlr\Validation\Rules\BaseRule;
use Handlr\Validation\Rules\RuleValidator;
use Handlr\Validation\Sanitizers\Sanitizer;

class Validator
{
    private const array RULES_WITHOUT_SANITIZATION = [
        'confirmed',
        'exists',
        'max',
        'min',
        'required',
        'unique',
    ];

    private array $errors = [];

    private array $sanitized = [];

    public function errors(): array
    {
        return $this->errors;
    }

    public function sanitized(): array
    {
        return $this->sanitized;
    }

    public function isValid(): bool
    {
        return empty($this->errors);
    }

    public function validate(array $data, array $rules): bool
    {
        foreach ($rules as $field => $ruleSet) {
            $value = $data[$field] ?? null;
            $this->processRuleset($data, $field, $ruleSet, $value);
        }

        return $this->isValid();
    }

    private function processRuleset(array $data, string $field, array $ruleSet, $value): void
    {
        foreach ($ruleSet as $rule) {
            [$ruleName, $ruleArgs] = $this->parseRule($rule);
            $ruleValidator = $this->getRuleValidator($ruleName, $field);

            if (!$ruleValidator->validate($value, $ruleArgs, $data)) {
                $this->addRuleErrors($ruleValidator);
                return;
            }

            if (in_array($ruleName, self::RULES_WITHOUT_SANITIZATION, true)) {
                continue;
            }

            $valueSanitizer = $this->getValueSanitizer($ruleName);
            $this->sanitized[$field] = $valueSanitizer->sanitize($value, $ruleArgs);
        }
    }

    private function parseRule(string $rule): array
    {
        $parts = explode(':', $rule, 2);
        return [$parts[0], isset($parts[1]) ? explode(',', $parts[1]) : []];
    }

    private function addRuleErrors(RuleValidator $ruleValidator): void
    {
        $this->errors[$ruleValidator->field] = $ruleValidator->getErrorMessage();
        foreach ($ruleValidator->getOtherFieldErrors() as $oField => $oError) {
            $this->errors[$oField] = $oError;
        }
    }

    private function getRuleValidator(string $ruleName, string $field): RuleValidator
    {
        $ruleValidatorNamespace = 'Handlr\\Validation\\Rules\\';
        $ruleValidatorClass = $ruleValidatorNamespace . ucfirst($ruleName) . 'Rule';

        if (!class_exists($ruleValidatorClass)) {
            throw new ValidationException("Validator for $ruleName not found.");
        }

        var_dump($ruleValidatorClass);

        /** @var BaseRule $ruleValidator */
        $ruleValidator = new Container()->get($ruleValidatorClass);
        $ruleValidator->setField($field);

        return $ruleValidator;
    }

    private function getValueSanitizer(string $ruleName): Sanitizer
    {
        $valueSanitizerNamespace = 'Handlr\\Validation\\Sanitizers\\';
        $valueSanitizerClass = $valueSanitizerNamespace . ucfirst($ruleName) . 'Sanitizer';

        if (!class_exists($valueSanitizerClass)) {
            throw new ValidationException("Sanitizer for $ruleName not found.");
        }

        return new $valueSanitizerClass();
    }
}
