<?php

declare(strict_types=1);

namespace Handlr\Core;

use Exception;
use Handlr\Handlers\HandlerInput;
use Handlr\Validation\Rules\RuleValidatorFactory;
use Handlr\Validation\Sanitizers\SanitizerFactory;
use Handlr\Validation\Validator;
use JsonException;

/**
 * HTTP request wrapper providing access to all request data.
 *
 * Encapsulates query params, POST data, JSON body, headers, and route parameters.
 * Typically created via fromGlobals() at the start of request handling.
 *
 * @example Create from PHP globals:
 *     $request = Request::fromGlobals();
 *
 * @example Access different data sources:
 *     $request->query('page', 1);              // GET param with default
 *     $request->getParsedBody();               // JSON body as array
 *     $request->getRouteParam('id');           // URL param like /users/{id}
 *     $request->getHeader('Authorization');    // Request header
 */
class Request
{
    /** @var array<string, mixed> Route parameters extracted from URL (e.g., {id} segments) */
    private array $routeParams = [];

    /**
     * @param array<string, mixed> $query Query string parameters ($_GET)
     * @param array<string, mixed> $post Form POST parameters ($_POST)
     * @param string $body Raw request body (php://input)
     * @param array<string, mixed> $server Server variables ($_SERVER)
     * @param array<string, string> $headers Request headers
     */
    public function __construct(
        private array $query,
        private array $post,
        private string $body,
        private array $server,
        private array $headers
    ) {
        $this->body = trim($this->body);
    }

    /**
     * Create a Request instance from PHP superglobals.
     *
     * This is the standard way to create a Request at the start of handling.
     *
     * @return self Request populated from $_GET, $_POST, php://input, $_SERVER, and headers
     *
     * @example
     *     $request = Request::fromGlobals();
     */
    public static function fromGlobals(): self
    {
        return new self(
            $_GET,
            $_POST,
            file_get_contents('php://input'),
            $_SERVER,
            getallheaders()
        );
    }

    /**
     * Get all query string parameters.
     *
     * @return array<string, mixed> All GET parameters
     *
     * @example For URL /search?q=hello&page=2:
     *     $request->getQueryParams(); // ['q' => 'hello', 'page' => '2']
     */
    public function getQueryParams(): array
    {
        return $this->query;
    }

    /**
     * Get a single query string parameter.
     *
     * @param string $key Parameter name
     * @param mixed $default Value if parameter is not set
     * @return mixed Parameter value or default
     *
     * @example For URL /search?q=hello&page=2:
     *     $request->query('q');              // 'hello'
     *     $request->query('page', 1);        // '2'
     *     $request->query('limit', 20);      // 20 (default, param not set)
     */
    public function query(string $key, $default = null)
    {
        return $this->query[$key] ?? $default;
    }

    /**
     * Get all POST form parameters.
     *
     * Note: For JSON APIs, use getParsedBody() instead. This only contains
     * traditional form POST data (application/x-www-form-urlencoded or multipart).
     *
     * @return array<string, mixed> All POST parameters
     */
    public function getPostParams(): array
    {
        return $this->post;
    }

    /**
     * Get the raw request body as a string.
     *
     * Use this for non-JSON payloads or when you need the unparsed body.
     * For JSON APIs, prefer getParsedBody() which handles parsing.
     *
     * @return string Raw body content (trimmed)
     *
     * @example
     *     $xml = $request->getBody(); // Raw XML string
     */
    public function getBody(): string
    {
        return $this->body;
    }

    /**
     * Parse and return the JSON request body as an array.
     *
     * This is the primary method for reading JSON API request data.
     * Returns empty array for empty body, throws on invalid JSON.
     *
     * @return array<string, mixed> Parsed JSON data
     * @throws RequestException If body contains invalid JSON (returns 400 status)
     *
     * @example For POST body {"name": "John", "email": "john@example.com"}:
     *     $data = $request->getParsedBody();
     *     $name = $data['name'];  // 'John'
     *     $email = $data['email']; // 'john@example.com'
     */
    public function getParsedBody(): array
    {
        if (trim($this->body) === '') {
            return [];
        }

        try {
            return json_decode($this->body, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw new RequestException('Invalid JSON body', Response::HTTP_BAD_REQUEST, $e);
        }
    }

    /**
     * Get a request header value.
     *
     * Header names are case-sensitive as received from getallheaders().
     *
     * @param string $name Header name (e.g., 'Authorization', 'Content-Type')
     * @return string|null Header value or null if not present
     *
     * @example
     *     $token = $request->getHeader('Authorization'); // 'Bearer eyJ...'
     *     $type = $request->getHeader('Content-Type');   // 'application/json'
     */
    public function getHeader(string $name): ?string
    {
        return $this->headers[$name] ?? null;
    }

    /**
     * Get the HTTP request method.
     *
     * @return string Uppercase method name (GET, POST, PUT, DELETE, PATCH, etc.)
     *
     * @example
     *     $request->getMethod(); // 'POST'
     */
    public function getMethod(): string
    {
        return strtoupper($this->server['REQUEST_METHOD'] ?? 'GET');
    }

    /**
     * Get the request URI including query string.
     *
     * @return string Request URI (e.g., '/users/123?include=posts')
     *
     * @example
     *     $request->getUri(); // '/api/users/123'
     */
    public function getUri(): string
    {
        return $this->server['REQUEST_URI'] ?? '/';
    }

    /**
     * Check if the request has an Authorization header.
     *
     * Simple check for presence of Authorization header. For actual
     * authentication, validate the token value separately.
     *
     * @return bool True if Authorization header is present
     *
     * @example
     *     if ($request->isAuthenticated()) {
     *         $token = $request->getHeader('Authorization');
     *         // Validate token...
     *     }
     */
    public function isAuthenticated(): bool
    {
        // Add logic for checking authentication
        return isset($this->headers['Authorization']);
    }

    /**
     * Set route parameters extracted from the URL pattern.
     *
     * Called by the router after matching a route with dynamic segments.
     * Not typically called by application code.
     *
     * @param array<string, string> $params Route parameters (e.g., ['id' => '123'])
     */
    public function setRouteParams(array $params): void
    {
        $this->routeParams = $params;
    }

    /**
     * Get all route parameters.
     *
     * @return array<string, string> All URL parameters
     *
     * @example For route /users/{id}/posts/{postId} matched against /users/5/posts/42:
     *     $request->getRouteParams(); // ['id' => '5', 'postId' => '42']
     */
    public function getRouteParams(): array
    {
        return $this->routeParams;
    }

    /**
     * Get a single route parameter.
     *
     * Route parameters come from dynamic URL segments defined in routes,
     * like {id} in '/users/{id}'.
     *
     * @param string $key Parameter name as defined in route
     * @param mixed $default Value if parameter is not set
     * @return mixed Parameter value or default
     *
     * @example For route /users/{id} matched against /users/123:
     *     $request->getRouteParam('id');           // '123'
     *     $request->getRouteParam('foo', 'bar');   // 'bar' (not in route)
     */
    public function getRouteParam(string $key, $default = null)
    {
        return $this->routeParams[$key] ?? $default;
    }

    /**
     * Check if the client expects a JSON response.
     *
     * Checks if the Accept header includes 'application/json'.
     * Useful for content negotiation or error formatting.
     *
     * @return bool True if client accepts JSON
     *
     * @example
     *     if ($request->wantsJson()) {
     *         return Response::json(['error' => 'Not found']);
     *     } else {
     *         return Response::html('<h1>Not found</h1>');
     *     }
     */
    public function wantsJson(): bool
    {
        $accept = $this->getHeader('Accept') ?? '';

        return str_contains($accept, 'application/json');
    }
}
