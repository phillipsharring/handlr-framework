<?php

declare(strict_types=1);

namespace Handlr\Config;

use Adbar\Dot;

final class Config
{
    private array $config = [];

    private function __construct(private Dot $dot) {}

    public function load(array $config): void
    {
        $this->dot($config);
    }

    public function get(string $key, $default = null)
    {
        $keys = explode('.', $key);
        $value = $this->config;

        foreach ($keys as $keyPart) {
            if (isset($value[$keyPart])) {
                $value = $value[$keyPart];
            } else {
                return $default;
            }
        }

        return $value;
    }
}
