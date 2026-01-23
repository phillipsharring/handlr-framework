<?php

declare(strict_types=1);

namespace Handlr\Api;

interface PresenterInterface
{
    public function withData(array $data): static;

    public function withSingleData(array $data): static;

    public function withMeta(array $meta): static;

    public function success(?string $message = null): array;

    public function validationError(?string $message = null, array $fieldErrors = []): array;

    public function invariantError(?string $message = null): array;

    public function warning(?string $message = null): array;
}
