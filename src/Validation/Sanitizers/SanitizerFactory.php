<?php

declare(strict_types=1);

namespace Handlr\Validation\Sanitizers;

use Handlr\Core\Container\Container;
use Handlr\Validation\ValidationException;

class SanitizerFactory
{
    private const SANITIZER_NAMESPACE = 'Handlr\\Validation\\Sanitizers\\';

    public function create(string $type = ''): Sanitizer
    {
        $sanitizerClass = self::SANITIZER_NAMESPACE . ucfirst($type) . 'Sanitizer';

        if (!class_exists($sanitizerClass)) {
            throw new ValidationException("Sanitizer for $type not found.");
        }

        return (new Container())->get($sanitizerClass);
    }
}
