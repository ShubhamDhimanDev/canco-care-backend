<?php

namespace App\Services;

use Symfony\Component\HtmlSanitizer\HtmlSanitizer as SymfonySanitizer;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerConfig;

/**
 * Allow-list sanitiser for editor-authored HTML (stories, events, pages).
 *
 * Scripts, styles, iframes, event-handler attributes and non-http(s)/mailto/tel
 * link schemes are removed. Links always get rel="noopener noreferrer" and lose
 * any target attribute (same-tab navigation is the accessible default).
 * Run on write; the API serves the sanitised result as-is.
 */
class HtmlSanitizer
{
    private readonly SymfonySanitizer $sanitizer;

    public function __construct()
    {
        $this->sanitizer = new SymfonySanitizer(
            (new HtmlSanitizerConfig)
                ->allowSafeElements()
                ->allowLinkSchemes(['https', 'http', 'mailto', 'tel'])
                ->allowRelativeLinks()
                ->allowMediaSchemes(['https'])
                ->allowRelativeMedias(false)
                ->forceAttribute('a', 'rel', 'noopener noreferrer')
                ->dropAttribute('target', 'a')
        );
    }

    public function sanitize(string $html): string
    {
        return $this->sanitizer->sanitize($html);
    }
}
