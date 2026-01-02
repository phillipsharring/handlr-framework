<?php

declare(strict_types=1);

namespace Handlr\Core;

use InvalidArgumentException;
use Throwable;

final class RequestException extends InvalidArgumentException
{
    public function __construct(
        string $message,
        private readonly int $statusCode = Response::HTTP_BAD_REQUEST,
        ?Throwable $previous = null
    ) {
        parent::__construct($message, 0, $previous);
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }
}
