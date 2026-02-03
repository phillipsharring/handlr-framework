<?php

declare(strict_types=1);

namespace Handlr\Validation\Sanitizers;

use HTMLPurifier;
use HTMLPurifier_Config;

/**
 * Sanitizes HTML content using HTMLPurifier for safe rich text.
 *
 * Allowed tags: `p`, `b`, `strong`, `i`, `em`, `u`, `a[href]`, `ul`, `ol`, `li`, `br`, `span[style]`
 * Allowed CSS: `color`, `font-weight`, `font-style`, `text-decoration`
 */
class HtmlSanitizer implements Sanitizer
{
    private HTMLPurifier $purifier;

    public function __construct()
    {
        $config = HTMLPurifier_Config::createDefault();
        $config->set('HTML.Allowed', 'p,b,strong,i,em,u,a[href],ul,ol,li,br,span[style]');
        $config->set('CSS.AllowedProperties', 'color,font-weight,font-style,text-decoration');
        $this->purifier = new HTMLPurifier($config);
    }

    public function sanitize($value, array $ruleArgs = []): string
    {
        return $this->purifier->purify($value);
    }
}
