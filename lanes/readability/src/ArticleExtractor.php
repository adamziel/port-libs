<?php

declare(strict_types=1);

namespace PortLibs\Readability;

final class ArticleExtractor
{
    private const UNLIKELY_CANDIDATE_PATTERN = '/-ad-|ad-container|ad-mobile|ai2html|banner|breadcrumbs|combx|comment|community|cover-wrap|dfp-slot|disqus|extra|footer|gdpr|header|js_ad|legends|menu|related|remark|replies|rss|shoutbox|sidebar|skyscraper|social|sponsor|supplemental|ad-break|agegate|pagination|pager|popup|yom-remote/i';
    private const OK_MAYBE_CANDIDATE_PATTERN = '/and|article|body|column|content|main|mathjax|shadow/i';
    private const SHARE_ELEMENT_PATTERN = '/(\b|_)(share|sharedaddy)(\b|_)/i';
    private const ALLOWED_VIDEO_PATTERN = '~//(www\.)?((dailymotion|youtube|youtube-nocookie|player\.vimeo|v\.qq|bilibili|live\.bilibili)\.com|(archive|upload\.wikimedia)\.org|player\.twitch\.tv)~i';
    private const PRESENTATIONAL_ATTRIBUTES = [
        'align',
        'background',
        'bgcolor',
        'border',
        'cellpadding',
        'cellspacing',
        'frame',
        'hspace',
        'rules',
        'style',
        'valign',
        'vspace',
    ];
    private const DEPRECATED_SIZE_ATTRIBUTE_TAGS = [
        'TABLE',
        'TH',
        'TD',
        'HR',
        'PRE',
    ];
    private const UNLIKELY_ROLES = [
        'menu',
        'menubar',
        'complementary',
        'navigation',
        'alert',
        'alertdialog',
        'dialog',
    ];

    public function extract(string $html, ?string $url = null): Article
    {
        $dom = $this->loadHtmlDocument($html);
        $this->replaceElementsByTagName($dom, 'font', 'span');
        $xpath = new \DOMXPath($dom);
        $effectiveBaseUri = $this->effectiveBaseUri($xpath, $url);
        $this->unwrapNoscriptImages($xpath, $dom);
        $this->fixLazyImages($xpath);
        $jsonLdMetadata = $this->jsonLdMetadata($xpath);

        foreach ($xpath->query('//script|//style|//noscript|//nav|//footer|//aside|//form') ?: [] as $node) {
            $node->parentNode?->removeChild($node);
        }
        $this->cleanUnsafeEmbeds($xpath);
        $this->removeUnlikelyCandidates($xpath);

        $title = $this->title($xpath, $dom);
        $best = $this->bestContentNode($xpath) ?? $dom->documentElement;
        if ($best instanceof \DOMElement) {
            $best = $this->promoteSingleArticleCandidate($best);
            $this->removePlatformArticleChrome($best);
            $this->removeOutOfBandFigureWrappers($best);
            $this->removeInteractiveArticleChrome($best);
            $this->removeDuplicateTitleHeader($best, $title);
            $this->removeLeadingBylineActionBar($best);
            $this->demoteHeadingOnes($best);
            $best = $this->postProcessContent($best, $effectiveBaseUri, $url);
        }
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
                $jsonLdMetadata['byline'] ?? '',
                '//meta[@name="author"]/@content',
                '//meta[@property="article:author"]/@content',
                '//*[contains(concat(" ", normalize-space(@class), " "), " byline ")]',
                '//*[contains(concat(" ", normalize-space(@class), " "), " author ")]',
            ]),
            $jsonLdMetadata['siteName'] ?? $this->metadataValue($xpath, [
                '//meta[@property="og:site_name"]/@content',
                '//meta[@name="application-name"]/@content',
            ]),
            $this->metadataValue($xpath, [
                '//meta[@name="parsely-pub-date"]/@content',
                $jsonLdMetadata['publishedTime'] ?? '',
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

        $dom = $this->loadHtmlDocument($html);
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
        $dom = $this->loadHtmlDocument('<main>' . $article->contentHtml . '</main>');
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
            } elseif ($tag === 'table') {
                $blocks[] = '<!-- wp:table -->' . "\n" . '<figure class="wp-block-table">' . $html . '</figure>' . "\n" . '<!-- /wp:table -->';
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
            '//meta[@name="description"]/@content',
            '//meta[@property="og:description"]/@content',
            '//meta[@name="twitter:description"]/@content',
            '//meta[@property="twitter:description"]/@content',
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

    private function loadHtmlDocument(string $html): \DOMDocument
    {
        $dom = new \DOMDocument();
        $previous = libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="UTF-8" ?>' . $html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $remove = [];
        foreach ($dom->childNodes as $child) {
            if ($child instanceof \DOMProcessingInstruction) {
                $remove[] = $child;
            }
        }
        foreach ($remove as $child) {
            $dom->removeChild($child);
        }

        return $dom;
    }

    /**
     * @param list<string> $queries
     */
    private function metadataValue(\DOMXPath $xpath, array $queries): ?string
    {
        foreach ($queries as $query) {
            if (!str_starts_with($query, '/')) {
                $value = trim($query);

                if ($value !== '') {
                    return $value;
                }

                continue;
            }

            $value = $this->cleanMetadataString($xpath->query($query)?->item(0)?->nodeValue ?? '');
            if ($value !== '') {
                return $value;
            }
        }

        return null;
    }

    private function cleanMetadataString(string $value): string
    {
        return trim(html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    }

    /**
     * @return array{byline?: string, siteName?: string, publishedTime?: string}
     */
    private function jsonLdMetadata(\DOMXPath $xpath): array
    {
        $metadata = [];
        foreach ($xpath->query('//script[contains(translate(@type, "ABCDEFGHIJKLMNOPQRSTUVWXYZ", "abcdefghijklmnopqrstuvwxyz"), "ld+json")]') ?: [] as $node) {
            $decoded = $this->decodeJsonLd($node->textContent);
            foreach ($this->jsonLdNodes($decoded) as $entry) {
                if (!isset($metadata['byline']) && array_key_exists('author', $entry)) {
                    $byline = $this->jsonLdAuthorNames($entry['author']);
                    if ($byline !== null) {
                        $metadata['byline'] = $byline;
                    }
                }

                if (!isset($metadata['siteName']) && array_key_exists('publisher', $entry)) {
                    $siteName = $this->jsonLdName($entry['publisher']);
                    if ($siteName !== null) {
                        $metadata['siteName'] = $siteName;
                    }
                }

                foreach (['datePublished', 'dateCreated'] as $key) {
                    if (!isset($metadata['publishedTime']) && isset($entry[$key]) && is_string($entry[$key]) && trim($entry[$key]) !== '') {
                        $metadata['publishedTime'] = trim($entry[$key]);
                    }
                }
            }
        }

        return $metadata;
    }

    private function decodeJsonLd(string $text): mixed
    {
        $json = trim($text);
        $json = preg_replace('/^\s*<!\[CDATA\[\s*/', '', $json) ?? $json;
        $json = preg_replace('/\s*\]\]>\s*$/', '', $json) ?? $json;
        $json = trim($json);

        $decoded = json_decode($json, true);
        if (json_last_error() === JSON_ERROR_NONE) {
            return $decoded;
        }

        $decoded = json_decode(html_entity_decode($json, ENT_QUOTES | ENT_HTML5, 'UTF-8'), true);

        return json_last_error() === JSON_ERROR_NONE ? $decoded : null;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function jsonLdNodes(mixed $decoded): array
    {
        if (!is_array($decoded)) {
            return [];
        }

        if (array_is_list($decoded)) {
            $nodes = [];
            foreach ($decoded as $entry) {
                $nodes = array_merge($nodes, $this->jsonLdNodes($entry));
            }

            return $nodes;
        }

        $nodes = [$decoded];
        if (isset($decoded['@graph'])) {
            $nodes = array_merge($nodes, $this->jsonLdNodes($decoded['@graph']));
        }

        return $nodes;
    }

    private function jsonLdAuthorNames(mixed $author): ?string
    {
        if (is_string($author)) {
            $author = trim($author);

            return $author !== '' && !filter_var($author, FILTER_VALIDATE_URL) ? $author : null;
        }

        if (!is_array($author)) {
            return null;
        }

        $authors = array_is_list($author) ? $author : [$author];
        $names = [];
        foreach ($authors as $entry) {
            $name = $this->jsonLdName($entry);
            if ($name !== null) {
                $names[] = $name;
            }
        }

        return $names === [] ? null : implode(', ', $names);
    }

    private function jsonLdName(mixed $value): ?string
    {
        if (is_string($value)) {
            $value = trim($value);

            return $value === '' ? null : $value;
        }

        if (!is_array($value)) {
            return null;
        }

        if (isset($value['name']) && is_string($value['name']) && trim($value['name']) !== '') {
            return trim($value['name']);
        }

        if (array_is_list($value)) {
            foreach ($value as $entry) {
                $name = $this->jsonLdName($entry);
                if ($name !== null) {
                    return $name;
                }
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

    private function effectiveBaseUri(\DOMXPath $xpath, ?string $documentUri): ?string
    {
        if ($documentUri === null || trim($documentUri) === '') {
            return null;
        }

        $baseHref = trim($xpath->query('//base[@href]/@href')?->item(0)?->nodeValue ?? '');
        if ($baseHref === '') {
            return $documentUri;
        }

        return $this->resolveUri($baseHref, $documentUri);
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

            $this->removeTinyDataUriPlaceholder($node);

            $class = strtolower($node->getAttribute('class'));
            $hasUsableSource = $this->hasLoadedImageSource($node) && !str_contains($class, 'lazy');
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

    private function removeTinyDataUriPlaceholder(\DOMElement $image): void
    {
        $src = trim($image->getAttribute('src'));
        if (!$this->isTinyNonSvgBase64Image($src) || !$this->hasAlternativeImageAttribute($image)) {
            return;
        }

        $image->removeAttribute('src');
    }

    private function hasLoadedImageSource(\DOMElement $image): bool
    {
        if (trim($image->getAttribute('src')) !== '') {
            return true;
        }

        $srcset = trim($image->getAttribute('srcset'));

        return $srcset !== '' && strtolower($srcset) !== 'null';
    }

    private function hasAlternativeImageAttribute(\DOMElement $image): bool
    {
        foreach ($image->attributes ?: [] as $attribute) {
            if (strtolower($attribute->name) === 'src') {
                continue;
            }

            if ($this->looksLikeImageUrl($attribute->value) || $this->looksLikeSrcset($attribute->value)) {
                return true;
            }
        }

        return false;
    }

    private function isTinyNonSvgBase64Image(string $src): bool
    {
        if (preg_match('~^data:([^;,]+);base64,([a-z0-9+/=]+)$~i', $src, $matches) !== 1) {
            return false;
        }

        if (strtolower($matches[1]) === 'image/svg+xml') {
            return false;
        }

        return strlen($matches[2]) < 133;
    }

    private function singleImageFromHtml(string $html): ?\DOMElement
    {
        $dom = $this->loadHtmlDocument('<div>' . $html . '</div>');
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

    private function cleanUnsafeEmbeds(\DOMXPath $xpath): void
    {
        $remove = [];
        foreach ($xpath->query('//object|//embed|//iframe') ?: [] as $node) {
            if (!$node instanceof \DOMElement || $this->isAllowedVideoEmbed($node)) {
                continue;
            }

            $remove[] = $node;
        }

        foreach ($remove as $node) {
            $node->parentNode?->removeChild($node);
        }
    }

    private function isAllowedVideoEmbed(\DOMElement $node): bool
    {
        foreach ($node->attributes ?: [] as $attribute) {
            if (preg_match(self::ALLOWED_VIDEO_PATTERN, $attribute->value) === 1) {
                return true;
            }
        }

        return strtolower($node->tagName) === 'object'
            && preg_match(self::ALLOWED_VIDEO_PATTERN, $this->innerHtml($node)) === 1;
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

    private function removePlatformArticleChrome(\DOMElement $scope): void
    {
        $directArticle = null;
        $elementChildren = [];
        foreach ($scope->childNodes as $child) {
            if ($child instanceof \DOMElement) {
                $elementChildren[] = $child;
            }

            if ($child instanceof \DOMElement && strtolower($child->tagName) === 'article') {
                if ($directArticle instanceof \DOMElement) {
                    return;
                }

                $directArticle = $child;
            }
        }

        if (!$directArticle instanceof \DOMElement) {
            if (count($elementChildren) === 1 && $this->containsSingleArticle($elementChildren[0])) {
                $this->removePlatformArticleChrome($elementChildren[0]);
            }

            return;
        }

        $trailingText = '';
        for ($node = $directArticle->nextSibling; $node instanceof \DOMNode; $node = $node->nextSibling) {
            $trailingText .= ' ' . $this->normalizedNodeText($node);
        }

        if (!$this->looksLikePlatformChrome($trailingText)) {
            return;
        }

        $remove = [];
        for ($node = $directArticle->nextSibling; $node instanceof \DOMNode; $node = $node->nextSibling) {
            $remove[] = $node;
        }

        foreach ($remove as $node) {
            $node->parentNode?->removeChild($node);
        }
    }

    private function promoteSingleArticleCandidate(\DOMElement $scope): \DOMElement
    {
        if (strtolower($scope->tagName) === 'article') {
            return $scope;
        }

        $articles = $scope->getElementsByTagName('article');
        if ($articles->length !== 1) {
            return $scope;
        }

        $article = $articles->item(0);
        if (!$article instanceof \DOMElement) {
            return $scope;
        }

        $scopeText = mb_strlen($this->normalizeWhitespace($scope->textContent));
        $articleText = mb_strlen($this->normalizeWhitespace($article->textContent));
        if ($articleText < 140 || ($scopeText > 0 && ($articleText / $scopeText) < 0.5)) {
            return $scope;
        }

        return $article;
    }

    private function containsSingleArticle(\DOMElement $scope): bool
    {
        $articles = $scope->getElementsByTagName('article');

        return $articles->length === 1;
    }

    private function looksLikePlatformChrome(string $text): bool
    {
        $text = $this->normalizeWhitespace($text);
        if ($text === '') {
            return false;
        }

        foreach ([
            'More From Medium',
            'Discover Medium',
            'Related reads',
            'Write the first response',
            'Written by ',
            ' claps Written by',
            'Welcome to a place where words matter',
        ] as $marker) {
            if (str_contains($text, $marker)) {
                return true;
            }
        }

        return false;
    }

    private function removeOutOfBandFigureWrappers(\DOMElement $scope): void
    {
        $xpath = new \DOMXPath($scope->ownerDocument);
        $remove = [];
        foreach ($xpath->query('.//div[not(ancestor::figure)]', $scope) ?: [] as $node) {
            if (!$node instanceof \DOMElement || !$this->isOutOfBandFigureWrapper($xpath, $node)) {
                continue;
            }

            $remove[] = $node;
        }

        foreach ($remove as $node) {
            $node->parentNode?->removeChild($node);
        }
    }

    private function removeInteractiveArticleChrome(\DOMElement $scope): void
    {
        $xpath = new \DOMXPath($scope->ownerDocument);
        $remove = [];
        foreach ($xpath->query('.//button|.//input|.//textarea|.//select', $scope) ?: [] as $node) {
            if ($node instanceof \DOMElement) {
                $remove[] = $node;
            }
        }

        foreach ($xpath->query('.//a[@href]', $scope) ?: [] as $node) {
            if (!$node instanceof \DOMElement) {
                continue;
            }

            $href = strtolower($node->getAttribute('href'));
            if (str_contains($href, '/share/')
                || str_contains($href, 'source=post_actions_')
                || str_contains($href, 'source=post_sidebar')
                || str_contains($href, 'source=follow_footer')) {
                $remove[] = $node;
            }
        }

        foreach ($remove as $node) {
            $node->parentNode?->removeChild($node);
        }
    }

    private function removeLeadingBylineActionBar(\DOMElement $scope): void
    {
        $xpath = new \DOMXPath($scope->ownerDocument);
        $firstHeading = $xpath->query('.//h1|.//h2|.//h3', $scope)?->item(0);
        if (!$firstHeading instanceof \DOMElement) {
            return;
        }

        $remove = [];
        foreach ($this->elementsBefore($xpath, $scope, $firstHeading) as $node) {
            if (!$this->isLeadingBylineChrome($xpath, $node)) {
                continue;
            }

            $remove[] = $node;
        }

        foreach (array_reverse($remove) as $node) {
            if (!$this->hasRemovedAncestor($node, $remove)) {
                $node->parentNode?->removeChild($node);
            }
        }
    }

    /**
     * @return list<\DOMElement>
     */
    private function elementsBefore(\DOMXPath $xpath, \DOMElement $scope, \DOMElement $end): array
    {
        $nodes = [];
        foreach ($xpath->query('.//*', $scope) ?: [] as $node) {
            if ($node === $end) {
                break;
            }

            if ($node instanceof \DOMElement) {
                $nodes[] = $node;
            }
        }

        return $nodes;
    }

    private function isLeadingBylineChrome(\DOMXPath $xpath, \DOMElement $node): bool
    {
        if (($xpath->query('.//img|.//picture|.//figure|.//video|.//iframe', $node)?->length ?? 0) > 0) {
            return false;
        }

        $text = $this->normalizeWhitespace($node->textContent);
        if ($text === '' || mb_strlen($text) > 180) {
            return false;
        }

        $matchString = strtolower($node->getAttribute('class') . ' ' . $node->getAttribute('id') . ' ' . $text);
        if (preg_match('/\b(byline|author|dateline|writtenby|p-author)\b/', $matchString) === 1) {
            return true;
        }

        if (preg_match('/\b(?:jan|feb|mar|apr|may|jun|jul|aug|sep|sept|oct|nov|dec)[a-z]*\.?\s+\d{1,2},\s+\d{4}\b/i', $text) === 1) {
            return true;
        }

        return preg_match('/\b\d+\s+min\s+read\b/i', $text) === 1;
    }

    /**
     * @param list<\DOMElement> $removed
     */
    private function hasRemovedAncestor(\DOMElement $node, array $removed): bool
    {
        for ($parent = $node->parentNode; $parent instanceof \DOMElement; $parent = $parent->parentNode) {
            foreach ($removed as $candidate) {
                if ($candidate === $parent) {
                    return true;
                }
            }
        }

        return false;
    }

    private function isOutOfBandFigureWrapper(\DOMXPath $xpath, \DOMElement $node): bool
    {
        $figures = $xpath->query('.//figure', $node);
        if (($figures?->length ?? 0) !== 1) {
            return false;
        }

        $figure = $figures?->item(0);
        if (!$figure instanceof \DOMElement) {
            return false;
        }

        if (($xpath->query('.//img|.//picture', $node)?->length ?? 0) !== 1) {
            return false;
        }

        if (($xpath->query('.//p|.//blockquote|.//ul|.//ol|.//pre|.//table|.//iframe', $node)?->length ?? 0) > 0) {
            return false;
        }

        $captionText = $this->normalizeWhitespace($figure->textContent);
        if ($captionText === '' || mb_strlen($captionText) > 80) {
            return false;
        }

        if ($this->normalizeWhitespace($this->textOutsideDescendant($node, $figure)) !== '') {
            return false;
        }

        return mb_strlen($this->siblingText($node)) >= 200;
    }

    private function textOutsideDescendant(\DOMNode $node, \DOMNode $excluded): string
    {
        $text = '';
        foreach ($node->childNodes as $child) {
            if ($child === $excluded) {
                continue;
            }

            if ($this->nodeContains($child, $excluded)) {
                $text .= ' ' . $this->textOutsideDescendant($child, $excluded);
                continue;
            }

            $text .= ' ' . ($child->textContent ?? '');
        }

        return $text;
    }

    private function nodeContains(\DOMNode $node, \DOMNode $descendant): bool
    {
        for ($parent = $descendant->parentNode; $parent instanceof \DOMNode; $parent = $parent->parentNode) {
            if ($parent === $node) {
                return true;
            }
        }

        return false;
    }

    private function siblingText(\DOMElement $node): string
    {
        $parent = $node->parentNode;
        if (!$parent instanceof \DOMNode) {
            return '';
        }

        $text = '';
        foreach ($parent->childNodes as $sibling) {
            if ($sibling === $node) {
                continue;
            }

            $text .= ' ' . ($sibling->textContent ?? '');
        }

        return $this->normalizeWhitespace($text);
    }

    private function removeDuplicateTitleHeader(\DOMElement $scope, string $title): void
    {
        if (trim($title) === '') {
            return;
        }

        $xpath = new \DOMXPath($scope->ownerDocument);
        foreach ($xpath->query('.//h1|.//h2', $scope) ?: [] as $heading) {
            if (!$heading instanceof \DOMElement || !$this->headerDuplicatesTitle($heading, $title)) {
                continue;
            }

            $heading->parentNode?->removeChild($heading);
            return;
        }
    }

    private function headerDuplicatesTitle(\DOMElement $heading, string $title): bool
    {
        $tag = strtolower($heading->tagName);
        if ($tag !== 'h1' && $tag !== 'h2') {
            return false;
        }

        return $this->textSimilarity($title, $this->normalizeWhitespace($heading->textContent)) > 0.75;
    }

    private function textSimilarity(string $textA, string $textB): float
    {
        $tokensA = $this->tokenizeComparableText($textA);
        $tokensB = $this->tokenizeComparableText($textB);
        if ($tokensA === [] || $tokensB === []) {
            return 0.0;
        }

        $uniqueTokensB = array_values(array_filter(
            $tokensB,
            static fn (string $token): bool => !in_array($token, $tokensA, true),
        ));
        $tokensBText = implode(' ', $tokensB);
        if ($tokensBText === '') {
            return 0.0;
        }

        return 1.0 - (mb_strlen(implode(' ', $uniqueTokensB)) / mb_strlen($tokensBText));
    }

    /**
     * @return list<string>
     */
    private function tokenizeComparableText(string $text): array
    {
        $tokens = preg_split('/[^\p{L}\p{N}]+/u', mb_strtolower($text)) ?: [];

        return array_values(array_filter($tokens, static fn (string $token): bool => $token !== ''));
    }

    private function demoteHeadingOnes(\DOMElement $scope): void
    {
        $xpath = new \DOMXPath($scope->ownerDocument);
        $headings = [];
        foreach ($xpath->query('.//h1', $scope) ?: [] as $heading) {
            if ($heading instanceof \DOMElement) {
                $headings[] = $heading;
            }
        }

        foreach ($headings as $heading) {
            $this->replaceElementTag($heading, 'h2');
        }
    }

    private function postProcessContent(\DOMElement $scope, ?string $baseUri, ?string $documentUri): \DOMElement
    {
        $this->fixRelativeUris($scope, $baseUri, $documentUri);
        $this->wrapPhrasingContentInDivs($scope);
        $scope = $this->convertPhrasingDivsToParagraphs($scope);
        $scope = $this->simplifyNestedElements($scope);
        $scope = $this->collapseSingleParagraphDivs($scope);
        $this->removeEmptyParagraphs($scope);
        $scope = $this->unwrapSingleCellTables($scope);
        $this->removeCommentNodes($scope);
        $this->cleanPresentationalAttributes($scope);
        $this->cleanClasses($scope);

        return $scope;
    }

    private function replaceElementsByTagName(\DOMDocument $dom, string $tagName, string $replacementTagName): void
    {
        $nodes = [];
        foreach ($dom->getElementsByTagName($tagName) as $node) {
            if ($node instanceof \DOMElement) {
                $nodes[] = $node;
            }
        }

        foreach ($nodes as $node) {
            $this->replaceElementTag($node, $replacementTagName);
        }
    }

    private function removeCommentNodes(\DOMNode $node): void
    {
        for ($index = $node->childNodes->length - 1; $index >= 0; $index--) {
            $child = $node->childNodes->item($index);
            if (!$child instanceof \DOMNode) {
                continue;
            }

            if ($child instanceof \DOMComment) {
                $node->removeChild($child);
                continue;
            }

            if ($child instanceof \DOMElement) {
                $this->removeCommentNodes($child);
            }
        }
    }

    private function cleanPresentationalAttributes(\DOMElement $node): void
    {
        if (strtolower($node->tagName) === 'svg') {
            return;
        }

        foreach (self::PRESENTATIONAL_ATTRIBUTES as $attribute) {
            $node->removeAttribute($attribute);
        }

        if (in_array(strtoupper($node->tagName), self::DEPRECATED_SIZE_ATTRIBUTE_TAGS, true)) {
            $node->removeAttribute('width');
            $node->removeAttribute('height');
        }

        foreach ($node->childNodes as $child) {
            if ($child instanceof \DOMElement) {
                $this->cleanPresentationalAttributes($child);
            }
        }
    }

    private function removeEmptyParagraphs(\DOMElement $scope): void
    {
        $paragraphs = [];
        foreach ($scope->getElementsByTagName('p') as $paragraph) {
            if ($paragraph instanceof \DOMElement) {
                $paragraphs[] = $paragraph;
            }
        }

        foreach ($paragraphs as $paragraph) {
            if ($this->normalizeWhitespace($paragraph->textContent) !== '') {
                continue;
            }

            if (($paragraph->getElementsByTagName('img')->length
                + $paragraph->getElementsByTagName('embed')->length
                + $paragraph->getElementsByTagName('object')->length
                + $paragraph->getElementsByTagName('iframe')->length) > 0) {
                continue;
            }

            $paragraph->parentNode?->removeChild($paragraph);
        }
    }

    private function fixRelativeUris(\DOMElement $scope, ?string $baseUri, ?string $documentUri): void
    {
        $document = $scope->ownerDocument;
        if (!$document instanceof \DOMDocument) {
            return;
        }

        $links = [];
        foreach ($scope->getElementsByTagName('a') as $link) {
            if ($link instanceof \DOMElement) {
                $links[] = $link;
            }
        }

        foreach ($links as $link) {
            $href = $link->getAttribute('href');
            if ($href === '') {
                continue;
            }

            if (str_starts_with($href, 'javascript:')) {
                $replacement = null;
                if ($link->childNodes->length === 1 && $link->firstChild instanceof \DOMText) {
                    $replacement = $document->createTextNode($link->textContent);
                } else {
                    $replacement = $document->createElement('span');
                    while ($link->firstChild instanceof \DOMNode) {
                        $replacement->appendChild($link->firstChild);
                    }
                }

                $link->parentNode?->replaceChild($replacement, $link);
                continue;
            }

            $link->setAttribute('href', $this->toAbsoluteUri($href, $baseUri, $documentUri));
        }

        foreach (['img', 'picture', 'figure', 'video', 'audio', 'source'] as $tag) {
            $mediaNodes = [];
            foreach ($scope->getElementsByTagName($tag) as $media) {
                if ($media instanceof \DOMElement) {
                    $mediaNodes[] = $media;
                }
            }

            foreach ($mediaNodes as $media) {
                foreach (['src', 'poster'] as $attribute) {
                    $value = $media->getAttribute($attribute);
                    if ($value !== '') {
                        $media->setAttribute($attribute, $this->toAbsoluteUri($value, $baseUri, $documentUri));
                    }
                }

                $srcset = $media->getAttribute('srcset');
                if ($srcset === '' || $baseUri === null) {
                    continue;
                }

                $media->setAttribute('srcset', preg_replace_callback(
                    '/(\S+)(\s+[\d.]+[xw])?(\s*(?:,|$))/',
                    fn (array $matches): string => $this->toAbsoluteUri($matches[1], $baseUri, $documentUri)
                        . ($matches[2] ?? '')
                        . $matches[3],
                    $srcset,
                ) ?? $srcset);
            }
        }
    }

    private function toAbsoluteUri(string $uri, ?string $baseUri, ?string $documentUri): string
    {
        if ($baseUri === null || $uri === '') {
            return $uri;
        }

        if ($baseUri === $documentUri && str_starts_with($uri, '#')) {
            return $uri;
        }

        return $this->resolveUri($uri, $baseUri);
    }

    private function resolveUri(string $uri, string $baseUri): string
    {
        if ($uri === '') {
            return $uri;
        }

        if (preg_match('/^[A-Za-z][A-Za-z0-9+.-]*:/', $uri) === 1) {
            return $uri;
        }

        $base = parse_url($baseUri);
        if ($base === false || !isset($base['scheme'], $base['host'])) {
            return $uri;
        }

        if (str_starts_with($uri, '//')) {
            return $base['scheme'] . ':' . $uri;
        }

        if (str_starts_with($uri, '#')) {
            return $this->stripFragment($baseUri) . $uri;
        }

        $parts = parse_url($uri);
        if ($parts === false) {
            return $uri;
        }

        $basePath = $base['path'] ?? '/';
        if ($basePath === '') {
            $basePath = '/';
        }

        $relativePath = $parts['path'] ?? '';
        if ($relativePath === '') {
            $path = $basePath;
        } elseif (str_starts_with($relativePath, '/')) {
            $path = $relativePath;
        } else {
            $baseDirectory = str_ends_with($basePath, '/')
                ? $basePath
                : preg_replace('~/[^/]*$~', '/', $basePath);
            $path = ($baseDirectory === null ? '/' : $baseDirectory) . $relativePath;
        }

        $query = array_key_exists('query', $parts) ? '?' . $parts['query'] : '';
        $fragment = array_key_exists('fragment', $parts) ? '#' . $parts['fragment'] : '';

        return $base['scheme'] . '://' . $this->urlAuthority($base) . $this->normalizeUrlPath($path) . $query . $fragment;
    }

    /**
     * @param array<string, int|string> $parts
     */
    private function urlAuthority(array $parts): string
    {
        $authority = '';
        if (isset($parts['user'])) {
            $authority .= $parts['user'];
            if (isset($parts['pass'])) {
                $authority .= ':' . $parts['pass'];
            }
            $authority .= '@';
        }

        $authority .= $parts['host'];
        if (isset($parts['port'])) {
            $authority .= ':' . $parts['port'];
        }

        return $authority;
    }

    private function normalizeUrlPath(string $path): string
    {
        $leadingSlash = str_starts_with($path, '/');
        $trailingSlash = str_ends_with($path, '/');
        $segments = [];
        foreach (explode('/', $path) as $segment) {
            if ($segment === '' || $segment === '.') {
                continue;
            }

            if ($segment === '..') {
                array_pop($segments);
                continue;
            }

            $segments[] = $segment;
        }

        $normalized = ($leadingSlash ? '/' : '') . implode('/', $segments);
        if ($normalized === '') {
            $normalized = $leadingSlash ? '/' : '.';
        }

        if ($trailingSlash && $normalized !== '/') {
            $normalized .= '/';
        }

        return $normalized;
    }

    private function stripFragment(string $uri): string
    {
        $position = strpos($uri, '#');

        return $position === false ? $uri : substr($uri, 0, $position);
    }

    private function collapseSingleParagraphDivs(\DOMElement $scope): \DOMElement
    {
        do {
            $changed = false;
            foreach ($this->singleParagraphDivCandidates($scope) as $node) {
                if (!$node->parentNode instanceof \DOMNode || $this->hasReadabilityId($node)) {
                    continue;
                }

                if (!$this->hasSingleTagInsideElement($node, 'p') || $this->linkDensity($node) >= 0.25 || $this->hasMediaPayload($node)) {
                    continue;
                }

                $child = $this->firstElementChild($node);
                if (!$child instanceof \DOMElement) {
                    continue;
                }

                $node->removeChild($child);
                $node->parentNode->replaceChild($child, $node);
                if ($node === $scope) {
                    $scope = $child;
                }

                $changed = true;
                break;
            }
        } while ($changed);

        return $scope;
    }

    private function wrapPhrasingContentInDivs(\DOMElement $scope): void
    {
        $document = $scope->ownerDocument;
        if (!$document instanceof \DOMDocument) {
            return;
        }

        foreach ($this->divCandidates($scope) as $node) {
            if (!$node->parentNode instanceof \DOMNode && $node !== $scope) {
                continue;
            }

            $child = $node->firstChild;
            while ($child instanceof \DOMNode) {
                $nextSibling = $child->nextSibling;
                if (!$this->isPhrasingContent($child)) {
                    $child = $nextSibling;
                    continue;
                }

                $fragment = $document->createDocumentFragment();
                while ($child instanceof \DOMNode && $this->isPhrasingContent($child)) {
                    $nextSibling = $child->nextSibling;
                    $fragment->appendChild($child);
                    $child = $nextSibling;
                }

                while ($fragment->firstChild instanceof \DOMNode && $this->isWhitespaceNode($fragment->firstChild)) {
                    $fragment->removeChild($fragment->firstChild);
                }

                while ($fragment->lastChild instanceof \DOMNode && $this->isWhitespaceNode($fragment->lastChild)) {
                    $fragment->removeChild($fragment->lastChild);
                }

                if ($fragment->firstChild instanceof \DOMNode) {
                    $paragraph = $document->createElement('p');
                    $paragraph->appendChild($fragment);
                    $node->insertBefore($paragraph, $nextSibling);
                }
            }
        }
    }

    private function isPhrasingContent(\DOMNode $node): bool
    {
        if ($node instanceof \DOMText) {
            return true;
        }

        if (!$node instanceof \DOMElement) {
            return false;
        }

        $tag = strtoupper($node->tagName);
        if (in_array($tag, [
            'ABBR',
            'AUDIO',
            'B',
            'BDO',
            'BR',
            'BUTTON',
            'CITE',
            'CODE',
            'DATA',
            'DATALIST',
            'DFN',
            'EM',
            'EMBED',
            'I',
            'IMG',
            'INPUT',
            'KBD',
            'LABEL',
            'MARK',
            'MATH',
            'METER',
            'NOSCRIPT',
            'OBJECT',
            'OUTPUT',
            'PROGRESS',
            'Q',
            'RUBY',
            'SAMP',
            'SCRIPT',
            'SELECT',
            'SMALL',
            'SPAN',
            'STRONG',
            'SUB',
            'SUP',
            'TEXTAREA',
            'TIME',
            'VAR',
            'WBR',
        ], true)) {
            return true;
        }

        if (!in_array($tag, ['A', 'DEL', 'INS'], true)) {
            return false;
        }

        foreach ($node->childNodes as $child) {
            if (!$this->isPhrasingContent($child)) {
                return false;
            }
        }

        return true;
    }

    private function isWhitespaceNode(\DOMNode $node): bool
    {
        if ($node instanceof \DOMText) {
            return trim($node->textContent) === '';
        }

        return $node instanceof \DOMElement && strtoupper($node->tagName) === 'BR';
    }

    private function convertPhrasingDivsToParagraphs(\DOMElement $scope): \DOMElement
    {
        do {
            $changed = false;
            foreach ($this->divCandidates($scope) as $node) {
                if (!$node->parentNode instanceof \DOMNode || $this->hasReadabilityId($node)) {
                    continue;
                }

                if ($this->hasChildBlockElement($node)) {
                    continue;
                }

                $replacement = $this->replaceElementTag($node, 'p');
                if ($node === $scope) {
                    $scope = $replacement;
                }

                $changed = true;
                break;
            }
        } while ($changed);

        return $scope;
    }

    /**
     * @return list<\DOMElement>
     */
    private function divCandidates(\DOMElement $scope): array
    {
        $nodes = [];
        if (strtolower($scope->tagName) === 'div') {
            $nodes[] = $scope;
        }

        foreach ($scope->getElementsByTagName('div') as $node) {
            if ($node instanceof \DOMElement) {
                $nodes[] = $node;
            }
        }

        return $nodes;
    }

    private function hasChildBlockElement(\DOMElement $element): bool
    {
        foreach ($element->childNodes as $child) {
            if (!$child instanceof \DOMElement) {
                continue;
            }

            if (in_array(strtolower($child->tagName), ['blockquote', 'dl', 'div', 'img', 'ol', 'p', 'pre', 'table', 'ul'], true)) {
                return true;
            }

            if ($this->hasChildBlockElement($child)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return list<\DOMElement>
     */
    private function singleParagraphDivCandidates(\DOMElement $scope): array
    {
        $nodes = [];
        if (strtolower($scope->tagName) === 'div') {
            $nodes[] = $scope;
        }

        foreach ($scope->getElementsByTagName('div') as $node) {
            if ($node instanceof \DOMElement) {
                $nodes[] = $node;
            }
        }

        return $nodes;
    }

    private function linkDensity(\DOMElement $node): float
    {
        $textLength = mb_strlen($this->normalizeWhitespace($node->textContent));
        if ($textLength === 0) {
            return 0.0;
        }

        $linkText = '';
        foreach ($node->getElementsByTagName('a') as $link) {
            $linkText .= ' ' . $this->normalizeWhitespace($link->textContent);
        }

        return mb_strlen($this->normalizeWhitespace($linkText)) / $textLength;
    }

    private function hasMediaPayload(\DOMElement $node): bool
    {
        foreach (['img', 'embed', 'object', 'iframe', 'picture', 'video'] as $tag) {
            if ($node->getElementsByTagName($tag)->length > 0) {
                return true;
            }
        }

        return false;
    }

    private function unwrapSingleCellTables(\DOMElement $scope): \DOMElement
    {
        foreach ($this->tableCandidates($scope) as $table) {
            if (!$table->parentNode instanceof \DOMNode && $table !== $scope) {
                continue;
            }

            $body = $this->hasSingleTagInsideElement($table, 'tbody')
                ? $this->firstElementChild($table)
                : $table;
            if (!$body instanceof \DOMElement || !$this->hasSingleTagInsideElement($body, 'tr')) {
                continue;
            }

            $row = $this->firstElementChild($body);
            if (!$row instanceof \DOMElement || !$this->hasSingleTagInsideElement($row, 'td')) {
                continue;
            }

            $cell = $this->firstElementChild($row);
            if (!$cell instanceof \DOMElement) {
                continue;
            }

            $replacement = $this->singleCellTableReplacement($cell);
            $table->parentNode?->replaceChild($replacement, $table);
            if ($table === $scope) {
                $scope = $replacement;
            }
        }

        return $scope;
    }

    /**
     * @return list<\DOMElement>
     */
    private function tableCandidates(\DOMElement $scope): array
    {
        $nodes = [];
        if (strtolower($scope->tagName) === 'table') {
            $nodes[] = $scope;
        }

        foreach ($scope->getElementsByTagName('table') as $node) {
            if ($node instanceof \DOMElement) {
                $nodes[] = $node;
            }
        }

        return $nodes;
    }

    private function singleCellTableReplacement(\DOMElement $cell): \DOMElement
    {
        $document = $cell->ownerDocument;
        $tagName = $this->allChildrenArePhrasingContent($cell) ? 'p' : 'div';
        $replacement = $document->createElement($tagName);
        foreach ($cell->attributes ?: [] as $attribute) {
            $replacement->setAttribute($attribute->name, $attribute->value);
        }

        while ($cell->firstChild instanceof \DOMNode) {
            $replacement->appendChild($cell->firstChild);
        }

        return $replacement;
    }

    private function allChildrenArePhrasingContent(\DOMElement $element): bool
    {
        foreach ($element->childNodes as $child) {
            if (!$this->isPhrasingContent($child)) {
                return false;
            }
        }

        return true;
    }

    private function simplifyNestedElements(\DOMElement $scope): \DOMElement
    {
        do {
            $changed = false;
            foreach ($this->nestedSimplificationCandidates($scope) as $node) {
                if (!$node->parentNode instanceof \DOMNode || $this->hasReadabilityId($node)) {
                    continue;
                }

                if ($this->isElementWithoutContent($node)) {
                    if ($node === $scope) {
                        continue;
                    }

                    $node->parentNode->removeChild($node);
                    $changed = true;
                    break;
                }

                if (!$this->hasSingleTagInsideElement($node, 'div') && !$this->hasSingleTagInsideElement($node, 'section')) {
                    continue;
                }

                $child = $this->firstElementChild($node);
                if (!$child instanceof \DOMElement) {
                    continue;
                }

                $this->copyElementAttributes($child, $node);
                $node->removeChild($child);
                $node->parentNode->replaceChild($child, $node);
                if ($node === $scope) {
                    $scope = $child;
                }

                $changed = true;
                break;
            }
        } while ($changed);

        return $scope;
    }

    /**
     * @return list<\DOMElement>
     */
    private function nestedSimplificationCandidates(\DOMElement $scope): array
    {
        $nodes = [];
        if ($this->isSimplifiableContainer($scope)) {
            $nodes[] = $scope;
        }

        foreach ($scope->getElementsByTagName('*') as $node) {
            if ($node instanceof \DOMElement && $this->isSimplifiableContainer($node)) {
                $nodes[] = $node;
            }
        }

        return $nodes;
    }

    private function isSimplifiableContainer(\DOMElement $node): bool
    {
        return in_array(strtolower($node->tagName), ['div', 'section'], true);
    }

    private function hasReadabilityId(\DOMElement $node): bool
    {
        return str_starts_with($node->getAttribute('id'), 'readability');
    }

    private function isElementWithoutContent(\DOMElement $node): bool
    {
        $text = $this->normalizeWhitespace($node->textContent);
        if ($text !== '') {
            return false;
        }

        $elementChildren = 0;
        foreach ($node->childNodes as $child) {
            if ($child instanceof \DOMElement) {
                $elementChildren++;
            }
        }

        return $elementChildren === 0
            || $elementChildren === ($node->getElementsByTagName('br')->length + $node->getElementsByTagName('hr')->length);
    }

    private function hasSingleTagInsideElement(\DOMElement $element, string $tag): bool
    {
        $elementChildren = [];
        foreach ($element->childNodes as $child) {
            if ($child instanceof \DOMText && preg_match('/\S/', $child->textContent) === 1) {
                return false;
            }

            if ($child instanceof \DOMElement) {
                $elementChildren[] = $child;
            }
        }

        return count($elementChildren) === 1 && strtolower($elementChildren[0]->tagName) === $tag;
    }

    private function firstElementChild(\DOMElement $element): ?\DOMElement
    {
        foreach ($element->childNodes as $child) {
            if ($child instanceof \DOMElement) {
                return $child;
            }
        }

        return null;
    }

    private function copyElementAttributes(\DOMElement $target, \DOMElement $source): void
    {
        foreach ($source->attributes ?: [] as $attribute) {
            $target->setAttribute($attribute->name, $attribute->value);
        }
    }

    private function cleanClasses(\DOMElement $node): void
    {
        $classes = array_values(array_filter(
            preg_split('/\s+/', $node->getAttribute('class')) ?: [],
            static fn (string $class): bool => $class === 'page',
        ));
        if ($classes === []) {
            $node->removeAttribute('class');
        } else {
            $node->setAttribute('class', implode(' ', $classes));
        }

        foreach ($node->childNodes as $child) {
            if ($child instanceof \DOMElement) {
                $this->cleanClasses($child);
            }
        }
    }

    private function replaceElementTag(\DOMElement $element, string $tagName): \DOMElement
    {
        $replacement = $element->ownerDocument->createElement($tagName);
        foreach ($element->attributes ?: [] as $attribute) {
            $replacement->setAttribute($attribute->name, $attribute->value);
        }

        while ($element->firstChild instanceof \DOMNode) {
            $replacement->appendChild($element->firstChild);
        }

        $element->parentNode?->replaceChild($replacement, $element);

        return $replacement;
    }

    private function normalizedNodeText(\DOMNode $node): string
    {
        return $this->normalizeWhitespace($node->textContent ?? '');
    }

    private function normalizeWhitespace(string $text): string
    {
        return trim(preg_replace('/\s+/', ' ', $text) ?? '');
    }

    private function bestContentNode(\DOMXPath $xpath): ?\DOMNode
    {
        $best = null;
        $bestScore = PHP_INT_MIN;
        foreach ($xpath->query('//article|//main|//section|//div|//body') ?: [] as $node) {
            $text = trim(preg_replace('/\s+/', ' ', $node->textContent) ?? '');
            if ($node instanceof \DOMElement
                && $this->looksLikePlatformChrome($text)
                && ($xpath->query('.//article', $node)?->length ?? 0) === 0) {
                continue;
            }

            $paragraphs = $xpath->query('.//p', $node)?->length ?? 0;
            $score = strlen($text) + (substr_count($text, ',') * 20) + ($paragraphs * 80) + $this->semanticContentWeight($node) + $this->contentClassWeight($node);
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

    private function contentClassWeight(\DOMNode $node): int
    {
        if (!$node instanceof \DOMElement) {
            return 0;
        }

        $matchString = strtolower($node->getAttribute('class') . ' ' . $node->getAttribute('id'));
        if (preg_match('/\b(article-body|article__body|entry-content|post-content|article-content)\b/', $matchString) === 1) {
            return 10000;
        }

        if (preg_match('/\b(content|main-content)\b/', $matchString) === 1) {
            return 1500;
        }

        return 0;
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
