<?php

declare(strict_types=1);

namespace Handlr\Config;

use Adbar\Dot;

final class Config
{
    public function __construct(public Dot $dot) {}

    public function load(array $config): void
    {
        // Replace the entire config payload.
        $this->dot->setArray($config);
    }

    public function get(string $key, $default = null)
    {
        return $this->dot->get($key, $default);
    }
}
