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
    ) {}

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

    public function getPostParams(): array
    {
        return $this->post;
    }

    public function getBody(): string
    {
        return $this->body;
    }

    /**
     * @throws JsonException
     */
    public function getParsedBody(): array
    {
        return json_decode($this->body, true, 512, JSON_THROW_ON_ERROR);
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

    /**
     * @throws Exception
     * @throws JsonException
     */
    public function asInput($class): HandlerInput
    {
        if (!class_exists($class)) {
            throw new Exception("Input class $class does not exist.");
        }

        return new $class($this->getParsedBody(), new Validator(
            new RuleValidatorFactory(),
            new SanitizerFactory()
        ));
    }
}
