<?php

declare(strict_types=1);

namespace Handlr\Core;

use JsonException;

/**
 * Immutable HTTP response builder with fluent interface.
 *
 * All `with*` methods return a new Response instance (immutable pattern).
 * Chain methods to build the response, then call send() to output it.
 *
 * COMMON USAGE PATTERNS:
 *
 * @example Return JSON from a handler (most common):
 *     return (new Response())->withJson(['status' => 'success', 'data' => $user]);
 *
 * @example Return JSON with specific status code:
 *     return (new Response())->withJson(['error' => 'Not found'], Response::HTTP_NOT_FOUND);
 *
 * @example Return validation error (422):
 *     return (new Response())->withJson([
 *         'status' => 'error',
 *         'errors' => ['email' => 'Invalid email format']
 *     ], Response::HTTP_UNPROCESSABLE_ENTITY);
 *
 * @example Redirect to another URL:
 *     return (new Response())->withRedirect('/login');
 *
 * @example Return HTML page:
 *     return (new Response())->withHtml('<h1>Hello</h1>');
 *
 * @example Custom response with headers:
 *     return (new Response())
 *         ->withStatus(Response::HTTP_OK)
 *         ->withHeader('X-Custom-Header', 'value')
 *         ->withHeader('Content-Type', 'text/plain')
 *         ->withBody('Plain text response');
 *
 * @example Using with Presenter:
 *     return (new Response())->withJson(
 *         $presenter->withSingleData($user)->success()
 *     );
 */
class Response
{
    /** @var int 400 - Bad request (malformed syntax, invalid JSON) */
    public const int HTTP_BAD_REQUEST = 400;

    /** @var int 404 - Resource not found */
    public const int HTTP_NOT_FOUND = 404;

    /** @var int 200 - Success (default) */
    public const int HTTP_OK = 200;

    /** @var int 500 - Internal server error */
    public const int HTTP_SERVER_ERROR = 500;

    /** @var int 302 - Temporary redirect */
    public const int HTTP_TEMPORARY_REDIRECT = 302;

    /** @var int 422 - Validation failed (use for form/input validation errors) */
    public const int HTTP_UNPROCESSABLE_ENTITY = 422;

    /** @var int HTTP status code */
    private int $statusCode = self::HTTP_OK;

    /** @var array<string, string> Response headers */
    private array $headers = [];

    /** @var string Response body content */
    private string $body = '';

    /**
     * Set the HTTP status code.
     *
     * @param int $statusCode HTTP status code (use class constants)
     * @return self New Response instance with updated status
     *
     * @example
     *     $response->withStatus(Response::HTTP_NOT_FOUND);
     *     $response->withStatus(201); // Created
     */
    public function withStatus(int $statusCode): self
    {
        $clone = clone $this;
        $clone->statusCode = $statusCode;
        return $clone;
    }

    /**
     * Add or replace a response header.
     *
     * @param string $name Header name (e.g., 'Content-Type', 'X-Custom')
     * @param string $value Header value
     * @return self New Response instance with added header
     *
     * @example
     *     $response->withHeader('Cache-Control', 'no-cache');
     *     $response->withHeader('X-Request-Id', $requestId);
     */
    public function withHeader(string $name, string $value): self
    {
        $clone = clone $this;
        $clone->headers[$name] = $value;
        return $clone;
    }

    /**
     * Set the raw response body.
     *
     * For JSON responses, prefer withJson() which handles encoding.
     * For HTML responses, prefer withHtml() which sets Content-Type.
     *
     * @param string $body Raw body content
     * @return self New Response instance with body
     *
     * @example
     *     $response->withBody('Plain text content');
     *     $response->withBody(file_get_contents('template.html'));
     */
    public function withBody(string $body): self
    {
        $clone = clone $this;
        $clone->body = $body;
        return $clone;
    }

    /**
     * Set response body as HTML with proper Content-Type.
     *
     * Sets Content-Type to 'text/html; charset=utf-8'.
     *
     * @param string $html HTML content
     * @param int $statusCode HTTP status code (default: 200)
     * @return self New Response instance configured for HTML
     *
     * @example Return an HTML page:
     *     return (new Response())->withHtml('<h1>Welcome</h1>');
     *
     * @example Return HTML error page:
     *     return (new Response())->withHtml('<h1>Not Found</h1>', Response::HTTP_NOT_FOUND);
     */
    public function withHtml(string $html, int $statusCode = self::HTTP_OK): self
    {
        return $this
            ->withHeader('Content-Type', 'text/html; charset=utf-8')
            ->withBody($html)
            ->withStatus($statusCode);
    }

    /**
     * Set response body as JSON with proper Content-Type.
     *
     * THIS IS THE MOST COMMON METHOD FOR API RESPONSES.
     *
     * Sets Content-Type to 'application/json'. Handles JSON encoding errors
     * gracefully by returning a 500 error response.
     *
     * @param array<string, mixed> $data Data to JSON-encode
     * @param int|null $statusCode HTTP status code (null = keep current, default 200)
     * @return self New Response instance configured for JSON
     *
     * @example Success response:
     *     return (new Response())->withJson(['status' => 'success']);
     *
     * @example Success with data:
     *     return (new Response())->withJson([
     *         'status' => 'success',
     *         'data' => ['id' => 123, 'name' => 'John']
     *     ]);
     *
     * @example Error with status code:
     *     return (new Response())->withJson(
     *         ['status' => 'error', 'message' => 'User not found'],
     *         Response::HTTP_NOT_FOUND
     *     );
     *
     * @example Validation error (422):
     *     return (new Response())->withJson([
     *         'status' => 'error',
     *         'message' => 'Validation failed',
     *         'errors' => ['email' => 'Required', 'password' => 'Too short']
     *     ], Response::HTTP_UNPROCESSABLE_ENTITY);
     */
    public function withJson(array $data, ?int $statusCode = null): self
    {
        try {
            $json = json_encode($data, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            error_log("JSON encoding failed: " . $e->getMessage());

            return $this
                ->withHeader('Content-Type', 'application/json')
                ->withBody('{"error":"JSON encoding failed","message":"' . addslashes($e->getMessage()) . '"}')
                ->withStatus(self::HTTP_SERVER_ERROR);
        }

        $response = $this
            ->withHeader('Content-Type', 'application/json')
            ->withBody($json);

        // Only set status if explicitly provided; otherwise preserve current status
        if ($statusCode !== null) {
            $response = $response->withStatus($statusCode);
        }

        return $response;
    }

    /**
     * Set response to redirect to another URL.
     *
     * Sets the Location header and appropriate status code.
     *
     * @param string $url URL to redirect to (absolute or relative)
     * @param int $statusCode HTTP status code (default: 302 temporary redirect)
     * @return self New Response instance configured for redirect
     *
     * @example Temporary redirect (302):
     *     return (new Response())->withRedirect('/dashboard');
     *
     * @example Permanent redirect (301):
     *     return (new Response())->withRedirect('/new-url', 301);
     *
     * @example Redirect to external URL:
     *     return (new Response())->withRedirect('https://example.com/oauth');
     */
    public function withRedirect(string $url, int $statusCode = self::HTTP_TEMPORARY_REDIRECT): self
    {
        return $this
            ->withHeader('Location', $url)
            ->withStatus($statusCode);
    }

    /**
     * Send the response to the client.
     *
     * Outputs the HTTP status code, all headers, and body.
     * This should be called once at the end of request handling.
     * Typically called by the framework, not directly in handlers.
     */
    public function send(): void
    {
        http_response_code($this->statusCode);
        foreach ($this->headers as $name => $value) {
            header("{$name}: {$value}");
        }
        echo $this->body;
    }
}
