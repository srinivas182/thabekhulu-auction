<?php

namespace App\Domains\Listings\Support;

use Symfony\Component\HtmlSanitizer\HtmlSanitizer;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerConfig;

/**
 * Cleans rich text from the editor before it is stored (spec D4): only
 * basic formatting and safe links survive; scripts, styles, event
 * handlers and embedded frames are removed.
 */
final class HtmlCleaner
{
    public static function clean(?string $html): ?string
    {
        if ($html === null || trim(strip_tags($html)) === '') {
            return null;
        }

        $config = (new HtmlSanitizerConfig)
            ->allowElement('p')->allowElement('br')->allowElement('strong')->allowElement('em')
            ->allowElement('ul')->allowElement('ol')->allowElement('li')
            ->allowElement('h2')->allowElement('h3')->allowElement('blockquote')
            ->allowElement('a', ['href'])
            ->allowLinkSchemes(['https', 'http', 'mailto', 'tel'])
            ->forceAttribute('a', 'rel', 'noopener noreferrer nofollow')
            ->withMaxInputLength(100000);

        return (new HtmlSanitizer($config))->sanitize($html);
    }
}
