<?php

declare(strict_types=1);

namespace PortLibs\Readability;

final class ArticleExtractor
{
    private const UNLIKELY_CANDIDATE_PATTERN = '/-ad-|ai2html|banner|breadcrumbs|combx|comment|community|cover-wrap|disqus|extra|footer|gdpr|header|legends|menu|related|remark|replies|rss|shoutbox|sidebar|skyscraper|social|sponsor|supplemental|ad-break|agegate|pagination|pager|popup|yom-remote/i';
    private const OK_MAYBE_CANDIDATE_PATTERN = '/and|article|body|column|content|main|mathjax|shadow/i';
    private const SHARE_ELEMENT_PATTERN = '/(\b|_)(share|sharedaddy)(\b|_)/i';
    private const UNLIKELY_ROLES = [
        'menu',
        'menubar',
        'complementary',
        'navigation',
        'alert',
        'alertdialog',
        'dialog',
    ];

    public function extract(string $html): Article
    {
        $dom = new \DOMDocument();
        $previous = libxml_use_internal_errors(true);
        $dom->loadHTML($html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $xpath = new \DOMXPath($dom);
        $this->unwrapNoscriptImages($xpath, $dom);
        $this->fixLazyImages($xpath);

        foreach ($xpath->query('//script|//style|//noscript|//nav|//footer|//aside|//form') ?: [] as $node) {
            $node->parentNode?->removeChild($node);
        }
        $this->removeUnlikelyCandidates($xpath);

        $title = $this->title($xpath, $dom);
        $best = $this->bestContentNode($xpath) ?? $dom->documentElement;
        $contentHtml = $best instanceof \DOMNode ? $this->innerHtml($best) : '';
        $text = trim(preg_replace('/\s+/', ' ', $best instanceof \DOMNode ? $best->textContent : '') ?? '');
        $excerpt = $this->excerpt($xpath, $best, $text);

        return new Article(
            $title,
            $contentHtml,
            $text,
            $excerpt,
            $this->metadataValue($xpath, [
                '//meta[@name="parsely-author"]/@content',
                '//meta[@name="author"]/@content',
                '//meta[@property="article:author"]/@content',
                '//*[contains(concat(" ", normalize-space(@class), " "), " byline ")]',
                '//*[contains(concat(" ", normalize-space(@class), " "), " author ")]',
            ]),
            $this->metadataValue($xpath, [
                '//meta[@property="og:site_name"]/@content',
                '//meta[@name="application-name"]/@content',
            ]),
            $this->metadataValue($xpath, [
                '//meta[@name="parsely-pub-date"]/@content',
                '//meta[@property="article:published_time"]/@content',
                '//meta[@name="pubdate"]/@content',
                '//time[@datetime]/@datetime',
            ]),
            $this->documentAttribute($dom, 'dir'),
            $this->documentAttribute($dom, 'lang'),
        );
    }

    /**
     * Native PHP port of Mozilla Readability's isProbablyReaderable preflight.
     *
     * @param array{minContentLength?: int|float, minScore?: int|float, visibilityChecker?: callable(\DOMElement): bool}|callable(\DOMElement): bool $options
     */
    public function isProbablyReaderable(string $html, array|callable $options = []): bool
    {
        if (is_callable($options)) {
            $options = ['visibilityChecker' => $options];
        }

        $minContentLength = (float) ($options['minContentLength'] ?? 140);
        $minScore = (float) ($options['minScore'] ?? 20);
        $visibilityChecker = $options['visibilityChecker'] ?? [$this, 'isNodeVisible'];
        if (!is_callable($visibilityChecker)) {
            $visibilityChecker = [$this, 'isNodeVisible'];
        }

        $dom = new \DOMDocument();
        $previous = libxml_use_internal_errors(true);
        $dom->loadHTML($html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $xpath = new \DOMXPath($dom);
        $nodes = [];
        foreach ($xpath->query('//p|//pre|//article') ?: [] as $node) {
            if ($node instanceof \DOMElement) {
                $nodes[spl_object_id($node)] = $node;
            }
        }

        foreach ($xpath->query('//div/br') ?: [] as $br) {
            $parent = $br->parentNode;
            if ($parent instanceof \DOMElement) {
                $nodes[spl_object_id($parent)] = $parent;
            }
        }

        $score = 0.0;
        foreach ($nodes as $node) {
            if (!$visibilityChecker($node)) {
                continue;
            }

            $matchString = $node->getAttribute('class') . ' ' . $node->getAttribute('id');
            if (preg_match(self::UNLIKELY_CANDIDATE_PATTERN, $matchString) === 1
                && preg_match(self::OK_MAYBE_CANDIDATE_PATTERN, $matchString) !== 1) {
                continue;
            }

            if ($this->isListParagraph($node)) {
                continue;
            }

            $textContentLength = mb_strlen(trim($node->textContent));
            if ($textContentLength < $minContentLength) {
                continue;
            }

            $score += sqrt($textContentLength - $minContentLength);
            if ($score > $minScore) {
                return true;
            }
        }

        return false;
    }

    public function toWordPressBlocks(Article $article): string
    {
        $dom = new \DOMDocument();
        $previous = libxml_use_internal_errors(true);
        $dom->loadHTML('<main>' . $article->contentHtml . '</main>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $blocks = [];
        $main = $dom->getElementsByTagName('main')->item(0);
        if (!$main) {
            return '';
        }

        foreach ($main->childNodes as $child) {
            if (!$child instanceof \DOMElement) {
                continue;
            }
            $tag = strtolower($child->tagName);
            $html = trim($dom->saveHTML($child) ?: '');
            if ($html === '') {
                continue;
            }
            if (preg_match('/^h([1-6])$/', $tag, $m)) {
                $blocks[] = '<!-- wp:heading {"level":' . $m[1] . '} -->' . "\n" . $html . "\n" . '<!-- /wp:heading -->';
            } elseif ($tag === 'img') {
                $blocks[] = '<!-- wp:image -->' . "\n" . '<figure class="wp-block-image">' . $html . '</figure>' . "\n" . '<!-- /wp:image -->';
            } else {
                $blocks[] = '<!-- wp:paragraph -->' . "\n" . $html . "\n" . '<!-- /wp:paragraph -->';
            }
        }

        return implode("\n\n", $blocks);
    }

    private function title(\DOMXPath $xpath, \DOMDocument $dom): string
    {
        foreach ([
            '//meta[@name="parsely-title"]/@content',
            '//meta[@property="og:title"]/@content',
            '//meta[@name="twitter:title"]/@content',
            '//title',
            '//h1',
        ] as $query) {
            $node = $xpath->query($query)?->item(0);
            $value = trim($node?->nodeValue ?? '');
            if ($value !== '') {
                return $value;
            }
        }

        return '';
    }

    private function excerpt(\DOMXPath $xpath, ?\DOMNode $best, string $fallbackText): string
    {
        $metadataDescription = $this->metadataValue($xpath, [
            '//meta[@property="og:description"]/@content',
            '//meta[@name="twitter:description"]/@content',
            '//meta[@name="description"]/@content',
        ]);
        if ($metadataDescription !== null) {
            return $metadataDescription;
        }

        if ($best instanceof \DOMNode) {
            foreach ($xpath->query('.//p|.//div', $best) ?: [] as $node) {
                $text = trim(preg_replace('/\s+/', ' ', $node->textContent) ?? '');
                if ($text !== '') {
                    return $text;
                }
            }
        }

        return mb_substr($fallbackText, 0, 180);
    }

    /**
     * @param list<string> $queries
     */
    private function metadataValue(\DOMXPath $xpath, array $queries): ?string
    {
        foreach ($queries as $query) {
            $value = trim($xpath->query($query)?->item(0)?->nodeValue ?? '');
            if ($value !== '') {
                return $value;
            }
        }

        return null;
    }

    private function documentAttribute(\DOMDocument $dom, string $attribute): ?string
    {
        $element = $dom->documentElement;
        if (!$element instanceof \DOMElement) {
            return null;
        }

        $value = trim($element->getAttribute($attribute));

        return $value === '' ? null : $value;
    }

    private function unwrapNoscriptImages(\DOMXPath $xpath, \DOMDocument $dom): void
    {
        foreach ($xpath->query('//img') ?: [] as $img) {
            if (!$img instanceof \DOMElement || $this->imageSourceAttribute($img) !== null) {
                continue;
            }

            $img->parentNode?->removeChild($img);
        }

        $noscripts = [];
        foreach ($xpath->query('//noscript') ?: [] as $node) {
            if ($node instanceof \DOMElement) {
                $noscripts[] = $node;
            }
        }

        foreach ($noscripts as $noscript) {
            $fallback = $this->singleImageFromHtml($this->innerHtml($noscript));
            if (!$fallback instanceof \DOMElement) {
                continue;
            }

            $previous = $this->previousElementSibling($noscript);
            $target = $previous instanceof \DOMElement ? $this->singleImageInElement($previous) : null;
            if ($target instanceof \DOMElement) {
                $this->copyImageAttributes($target, $fallback);
                $noscript->parentNode?->removeChild($noscript);
                continue;
            }

            $imported = $dom->importNode($fallback, true);
            if ($imported instanceof \DOMNode) {
                $noscript->parentNode?->replaceChild($imported, $noscript);
            }
        }
    }

    private function fixLazyImages(\DOMXPath $xpath): void
    {
        foreach ($xpath->query('//img') ?: [] as $node) {
            if (!$node instanceof \DOMElement) {
                continue;
            }

            $class = strtolower($node->getAttribute('class'));
            $hasUsableSource = $this->imageSourceAttribute($node) !== null && !str_contains($class, 'lazy');
            if ($hasUsableSource) {
                continue;
            }

            foreach ($node->attributes ?: [] as $attribute) {
                $name = strtolower($attribute->name);
                if (in_array($name, ['src', 'srcset', 'alt'], true)) {
                    continue;
                }

                $value = trim($attribute->value);
                if ($this->looksLikeSrcset($value)) {
                    $this->setImageAttribute($node, 'srcset', $value);
                    continue;
                }

                if ($this->looksLikeImageUrl($value)) {
                    $this->setImageAttribute($node, 'src', $value);
                }
            }
        }
    }

    private function singleImageFromHtml(string $html): ?\DOMElement
    {
        $dom = new \DOMDocument();
        $previous = libxml_use_internal_errors(true);
        $dom->loadHTML('<div>' . $html . '</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $wrapper = $dom->getElementsByTagName('div')->item(0);
        if (!$wrapper instanceof \DOMElement) {
            return null;
        }

        return $this->singleImageInElement($wrapper);
    }

    private function singleImageInElement(\DOMElement $element): ?\DOMElement
    {
        if (strtolower($element->tagName) === 'img') {
            return $element;
        }

        $images = $element->getElementsByTagName('img');
        if ($images->length !== 1) {
            return null;
        }

        return $images->item(0) instanceof \DOMElement ? $images->item(0) : null;
    }

    private function previousElementSibling(\DOMElement $element): ?\DOMElement
    {
        for ($node = $element->previousSibling; $node instanceof \DOMNode; $node = $node->previousSibling) {
            if ($node instanceof \DOMElement) {
                return $node;
            }
        }

        return null;
    }

    private function imageSourceAttribute(\DOMElement $image): ?string
    {
        foreach (['src', 'srcset', 'data-src', 'data-srcset'] as $attribute) {
            $value = trim($image->getAttribute($attribute));
            if ($value !== '') {
                return $attribute;
            }
        }

        foreach ($image->attributes ?: [] as $attribute) {
            if ($this->looksLikeImageUrl($attribute->value) || $this->looksLikeSrcset($attribute->value)) {
                return $attribute->name;
            }
        }

        return null;
    }

    private function copyImageAttributes(\DOMElement $target, \DOMElement $source): void
    {
        foreach ($source->attributes ?: [] as $attribute) {
            $this->setImageAttribute($target, $attribute->name, $attribute->value);
        }
    }

    private function setImageAttribute(\DOMElement $image, string $name, string $value): void
    {
        $oldValue = trim($image->getAttribute($name));
        if (($name === 'src' || $name === 'srcset') && $oldValue !== '' && $oldValue !== $value) {
            $image->setAttribute($name === 'src' ? 'data-old-src' : 'data-old-srcset', $oldValue);
        }

        $image->setAttribute($name, $value);
    }

    private function looksLikeImageUrl(string $value): bool
    {
        return preg_match('/^\s*\S+\.(?:jpe?g|png|webp|gif)(?:[?#]\S*)?\s*$/i', $value) === 1;
    }

    private function looksLikeSrcset(string $value): bool
    {
        return preg_match('/\S+\.(?:jpe?g|png|webp|gif)(?:[?#]\S*)?\s+\d+(?:\.\d+)?[wx](?:\s*,|\s*$)/i', $value) === 1;
    }

    private function removeUnlikelyCandidates(\DOMXPath $xpath): void
    {
        $remove = [];
        foreach ($xpath->query('//*') ?: [] as $node) {
            if (!$node instanceof \DOMElement) {
                continue;
            }

            $tag = strtoupper($node->tagName);
            if ($tag === 'BODY' || $tag === 'A' || $this->hasAncestorTag($node, ['TABLE', 'CODE'])) {
                continue;
            }

            $role = strtolower($node->getAttribute('role'));
            $matchString = $node->getAttribute('class') . ' ' . $node->getAttribute('id');
            $isUnlikely = preg_match(self::UNLIKELY_CANDIDATE_PATTERN, $matchString) === 1
                && preg_match(self::OK_MAYBE_CANDIDATE_PATTERN, $matchString) !== 1;
            $isShareWidget = preg_match(self::SHARE_ELEMENT_PATTERN, $matchString) === 1;
            if ($isUnlikely || $isShareWidget || in_array($role, self::UNLIKELY_ROLES, true)) {
                $remove[] = $node;
            }
        }

        foreach ($remove as $node) {
            $node->parentNode?->removeChild($node);
        }
    }

    private function isNodeVisible(\DOMElement $node): bool
    {
        $style = $node->getAttribute('style');
        if ($style !== '' && preg_match('/(?:^|;)\s*display\s*:\s*none\s*(?:;|$)/i', $style) === 1) {
            return false;
        }

        if ($node->hasAttribute('hidden')) {
            return false;
        }

        if ($node->getAttribute('aria-hidden') === 'true'
            && !str_contains($node->getAttribute('class'), 'fallback-image')) {
            return false;
        }

        return true;
    }

    private function isListParagraph(\DOMElement $node): bool
    {
        if (strtolower($node->tagName) !== 'p') {
            return false;
        }

        for ($parent = $node->parentNode; $parent instanceof \DOMElement; $parent = $parent->parentNode) {
            if (strtolower($parent->tagName) === 'li') {
                return true;
            }
        }

        return false;
    }

    /**
     * @param list<string> $tags
     */
    private function hasAncestorTag(\DOMElement $node, array $tags): bool
    {
        for ($parent = $node->parentNode; $parent instanceof \DOMElement; $parent = $parent->parentNode) {
            if (in_array(strtoupper($parent->tagName), $tags, true)) {
                return true;
            }
        }

        return false;
    }

    private function bestContentNode(\DOMXPath $xpath): ?\DOMNode
    {
        $best = null;
        $bestScore = -1;
        foreach ($xpath->query('//article|//main|//section|//div|//body') ?: [] as $node) {
            $text = trim(preg_replace('/\s+/', ' ', $node->textContent) ?? '');
            $paragraphs = $xpath->query('.//p', $node)?->length ?? 0;
            $score = strlen($text) + (substr_count($text, ',') * 20) + ($paragraphs * 80) + $this->semanticContentWeight($node);
            if ($score > $bestScore) {
                $bestScore = $score;
                $best = $node;
            }
        }

        return $best;
    }

    private function semanticContentWeight(\DOMNode $node): int
    {
        if (!$node instanceof \DOMElement) {
            return 0;
        }

        return match (strtolower($node->tagName)) {
            'article' => 600,
            'main' => 300,
            'section' => 120,
            'body' => -300,
            default => 0,
        };
    }

    private function innerHtml(\DOMNode $node): string
    {
        $html = '';
        foreach ($node->childNodes as $child) {
            $html .= $node->ownerDocument?->saveHTML($child) ?: '';
        }

        return trim($html);
    }
}
