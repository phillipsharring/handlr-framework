<?php

declare(strict_types=1);

namespace Handlr\Core;

use JsonException;

class Response
{
    public const int HTTP_BAD_REQUEST = 400;
    public const int HTTP_NOT_FOUND = 404;
    public const int HTTP_OK = 200;
    public const int HTTP_SERVER_ERROR = 500;
    public const int HTTP_TEMPORARY_REDIRECT = 302;
    public const int HTTP_UNPROCESSABLE_ENTITY = 422;

    private int $statusCode = self::HTTP_OK;
    private array $headers = [];
    private string $body = '';

    public function withStatus(int $statusCode): self
    {
        $clone = clone $this;
        $clone->statusCode = $statusCode;
        return $clone;
    }

    public function withHeader(string $name, string $value): self
    {
        $clone = clone $this;
        $clone->headers[$name] = $value;
        return $clone;
    }

    public function withBody(string $body): self
    {
        $clone = clone $this;
        $clone->body = $body;
        return $clone;
    }

    public function withHtml(string $html, int $statusCode = self::HTTP_OK): self
    {
        return $this
            ->withHeader('Content-Type', 'text/html; charset=utf-8')
            ->withBody($html)
            ->withStatus($statusCode);
    }

    public function withJson(array $data, ?int $statusCode = null): self
    {
        try {
            $json = json_encode($data, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            // Avoid hard-failing the response pipeline if JSON encoding fails (e.g. invalid UTF-8).
            // Keep the payload simple and always-valid JSON.
            return $this
                ->withHeader('Content-Type', 'application/json')
                ->withBody('{"error":"JSON encoding failed"}')
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

    public function withRedirect(string $url, int $statusCode = self::HTTP_TEMPORARY_REDIRECT): self
    {
        return $this
            ->withHeader('Location', $url)
            ->withStatus($statusCode);
    }

    public function send(): void
    {
        http_response_code($this->statusCode);
        foreach ($this->headers as $name => $value) {
            header("{$name}: {$value}");
        }
        echo $this->body;
    }
}
