<?php

declare(strict_types=1);

namespace Handlr\Validation\Sanitizers;

/**
 * Static utility for escaping output in different contexts.
 *
 * Unlike other Sanitizers, this is not used during validation—call directly when rendering output.
 *
 * ```php
 * OutputSanitizer::html($userInput);  // For HTML context
 * OutputSanitizer::url($param);       // For URL parameters
 * OutputSanitizer::js($string);       // For JavaScript strings
 * OutputSanitizer::pdf($text);        // For PDF output (strips non-ASCII)
 * ```
 */
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
