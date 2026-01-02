<?php

declare(strict_types=1);

namespace Handlr\Validation\Rules;

use Handlr\Core\Container\Container;
use Handlr\Validation\ValidationException;

final class RuleValidatorFactory
{
    private const RULES_NAMESPACE = 'Handlr\\Validation\\Rules\\';

    public function create(string $ruleName, string $field): RuleValidator
    {
        $ruleValidatorClass = self::RULES_NAMESPACE . ucfirst($ruleName) . 'Rule';

        if (!class_exists($ruleValidatorClass)) {
            throw new ValidationException("Validator for $ruleName not found.");
        }

        /** @var BaseRule $ruleValidator */
        $ruleValidator = (new Container())->get($ruleValidatorClass);
        $ruleValidator->setField($field);

        return $ruleValidator;
    }
}
