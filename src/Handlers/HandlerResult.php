<?php

declare(strict_types=1);

namespace Handlr\Handlers;

class HandlerResult
{
    public function __construct(
        public readonly ?bool $success = null,
        public readonly mixed $data = null,
        public readonly array $errors = [],
        public readonly array $meta = [],
    ) {}

    public function ok(mixed $data = null, array $meta = []): HandlerResult {
        return new self(true, $data, [], $meta);
    }

    public function fail(array $errors, array $meta = []): self {
        return new self(false, null, $errors, $meta);
    }
}
