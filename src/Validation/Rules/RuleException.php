<?php

declare(strict_types=1);

namespace Handlr\Validation\Rules;

use InvalidArgumentException;

/**
 * Exception thrown when a rule is misconfigured (e.g., missing required argument).
 */
class RuleException extends InvalidArgumentException {}
