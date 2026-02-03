<?php

declare(strict_types=1);

namespace Handlr\Core;

use InvalidArgumentException;
use Throwable;

/**
 * Exception for HTTP request errors with an associated status code.
 *
 * Thrown when request processing fails (e.g., invalid JSON body, missing
 * required parameters). The status code is used to generate the HTTP response.
 *
 * @example Throw for invalid JSON:
 *     throw new RequestException('Invalid JSON body', Response::HTTP_BAD_REQUEST);
 *
 * @example Throw for missing resource:
 *     throw new RequestException('User not found', Response::HTTP_NOT_FOUND);
 *
 * @example Catch and convert to response:
 *     try {
 *         $data = $request->getParsedBody();
 *     } catch (RequestException $e) {
 *         return Response::json(['error' => $e->getMessage()], $e->getStatusCode());
 *     }
 */
final class RequestException extends InvalidArgumentException
{
    /**
     * @param string $message Error message describing the request problem
     * @param int $statusCode HTTP status code for the response (default: 400 Bad Request)
     * @param Throwable|null $previous Previous exception for chaining
     */
    public function __construct(
        string $message,
        private readonly int $statusCode = Response::HTTP_BAD_REQUEST,
        ?Throwable $previous = null
    ) {
        parent::__construct($message, 0, $previous);
    }

    /**
     * Get the HTTP status code for this error.
     *
     * @return int HTTP status code (e.g., 400, 404, 422)
     */
    public function getStatusCode(): int
    {
        return $this->statusCode;
    }
}
