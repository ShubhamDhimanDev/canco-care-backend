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
 * Images (inserted from the media library) keep src, alt, width and height only.
 * Run on write; the API serves the sanitised result as-is.
 */
class HtmlSanitizer
{
    private readonly SymfonySanitizer $sanitizer;

    /**
     * @param  bool|null  $allowInsecureMedia  Plain-http images, for a dev server without TLS (library
     *                                         images are served from APP_URL). Default: only off production
     *                                         and only when APP_URL itself is http.
     */
    public function __construct(?bool $allowInsecureMedia = null)
    {
        $allowInsecureMedia ??= ! app()->isProduction() && str_starts_with((string) config('app.url'), 'http://');

        $this->sanitizer = new SymfonySanitizer(
            (new HtmlSanitizerConfig)
                ->allowSafeElements()
                ->allowLinkSchemes(['https', 'http', 'mailto', 'tel'])
                ->allowRelativeLinks()
                ->allowMediaSchemes($allowInsecureMedia ? ['https', 'http'] : ['https'])
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
