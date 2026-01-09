<?php

declare(strict_types=1);

namespace Handlr\Core;

use Exception;
use Handlr\Handlers\HandlerInput;
use Handlr\Validation\Rules\RuleValidatorFactory;
use Handlr\Validation\Sanitizers\SanitizerFactory;
use Handlr\Validation\Validator;
use JsonException;

class Request
{
    public function __construct(
        private array $query,
        private array $post,
        private string $body,
        private array $server,
        private array $headers
    ) {
        $this->body = trim($this->body);
    }

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

    public function getQueryParams(): array
    {
        return $this->query;
    }

    public function query(string $key, $default = null)
    {
        return $this->query[$key] ?? $default;
    }

    public function getPostParams(): array
    {
        return $this->post;
    }

    public function getBody(): string
    {
        return $this->body;
    }

    /**
     * @throws RequestException
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

    public function getHeader(string $name): ?string
    {
        return $this->headers[$name] ?? null;
    }

    public function getMethod(): string
    {
        return strtoupper($this->server['REQUEST_METHOD'] ?? 'GET');
    }

    public function getUri(): string
    {
        return $this->server['REQUEST_URI'] ?? '/';
    }

    public function isAuthenticated(): bool
    {
        // Add logic for checking authentication
        return isset($this->headers['Authorization']);
    }
}
