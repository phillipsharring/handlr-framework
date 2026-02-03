<?php

declare(strict_types=1);

namespace Handlr\Validation\Sanitizers;

/**
 * Interface for value sanitizers.
 *
 * Sanitizers clean/transform values after validation passes.
 * Each type rule has a corresponding sanitizer (e.g., StringRule → StringSanitizer).
 */
interface Sanitizer
{
    /**
     * Sanitize a value.
     *
     * @param mixed $value    The value to sanitize
     * @param array $ruleArgs Arguments from the rule (e.g., ['trim' => true])
     *
     * @return mixed Sanitized value
     */
    public function sanitize(mixed $value, array $ruleArgs = []);
}
