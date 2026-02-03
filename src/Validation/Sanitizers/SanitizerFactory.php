<?php

declare(strict_types=1);

namespace Handlr\Validation\Sanitizers;

use Handlr\Core\Container\Container;
use Handlr\Validation\ValidationException;

/**
 * Factory for creating sanitizers.
 *
 * Maps type names to sanitizer classes: `'string'` → `StringSanitizer::class`
 */
class SanitizerFactory
{
    private const SANITIZER_NAMESPACE = 'Handlr\\Validation\\Sanitizers\\';

    /**
     * Create a sanitizer instance.
     *
     * @param string $type Type name (e.g., 'string', 'int', 'email')
     *
     * @throws ValidationException If sanitizer class doesn't exist
     */
    public function create(string $type = ''): Sanitizer
    {
        $sanitizerClass = self::SANITIZER_NAMESPACE . ucfirst($type) . 'Sanitizer';

        if (!class_exists($sanitizerClass)) {
            throw new ValidationException("Sanitizer for $type not found.");
        }

        return (new Container())->get($sanitizerClass);
    }
}
