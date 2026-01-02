<?php

namespace Handlr\Validation\Sanitizers;

class OutputSanitizer
{
    private const UNICODE_QUOTE_MAPPINGS = [
        "\xC2\xAB"     => '"',
        "\xC2\xBB"     => '"',
        "\xE2\x80\x98" => "'",
        "\xE2\x80\x99" => "'",
        "\xE2\x80\x9A" => "'",
        "\xE2\x80\x9B" => "'",
        "\xE2\x80\x9C" => '"',
        "\xE2\x80\x9D" => '"',
        "\xE2\x80\x9E" => '"',
        "\xE2\x80\x9F" => '"',
        "\xE2\x80\xB9" => "'",
        "\xE2\x80\xBA" => "'",
    ];

    public static function html(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_HTML5);
    }

    public static function url(string $value): string
    {
        return rawurlencode($value);
    }

    public static function js(string $value): string
    {
        return addslashes($value);
    }

    public static function pdf(string|array $value, bool $preserveLineBreaks = false): string|array
    {
        if (is_array($value)) {
            return array_map(static fn($item) => self::pdf($item, $preserveLineBreaks), $value);
        }

        return $preserveLineBreaks
            ? self::unicodeWithLineBreaks($value)
            : self::unicode($value);
    }

    private static function unicode(string $value): string
    {
        return preg_replace('/[^ -~]/', '', strtr($value, self::UNICODE_QUOTE_MAPPINGS));
    }

    private static function unicodeWithLineBreaks(string $value): string
    {
        return preg_replace('/[^ -~\r\n]/', '', strtr($value, self::UNICODE_QUOTE_MAPPINGS));
    }
}
