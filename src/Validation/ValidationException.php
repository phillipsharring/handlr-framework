<?php

declare(strict_types=1);

namespace Handlr\Validation;

use InvalidArgumentException;

/**
 * Exception thrown when validation fails or configuration is invalid.
 */
class ValidationException extends InvalidArgumentException {}
