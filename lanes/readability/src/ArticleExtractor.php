<?php

declare(strict_types=1);

namespace PortLibs\Readability;

final class ArticleExtractor
{
    private const UNLIKELY_CANDIDATE_PATTERN = '/-ad-|ad-container|ad-mobile|ai2html|banner|breadcrumbs|combx|comment|community|cover-wrap|dfp-slot|disqus|extra|footer|gdpr|header|js_ad|legends|menu|related|remark|replies|rss|shoutbox|sidebar|skyscraper|social|sponsor|supplemental|ad-break|agegate|pagination|pager|popup|yom-remote/i';
    private const OK_MAYBE_CANDIDATE_PATTERN = '/and|article|body|column|content|main|mathjax|shadow/i';
    private const CLASS_WEIGHT_POSITIVE_PATTERN = '/article|body|content|entry|hentry|h-entry|main|page|pagination|post|text|blog|story/i';
    private const CLASS_WEIGHT_NEGATIVE_PATTERN = '/-ad-|hidden|^hid$| hid$| hid |^hid |banner|combx|comment|com-|contact|footer|gdpr|masthead|media|meta|outbrain|promo|related|scroll|share|shoutbox|sidebar|skyscraper|sponsor|shopping|tags|widget/i';
    private const SHARE_ELEMENT_PATTERN = '/(\b|_)(share|sharedaddy)(\b|_)/i';
    private const WORDPRESS_SOCIAL_CHROME_PATTERN = '/\b(?:like-post-wrapper|likes-widget-placeholder|post-likes-widget-placeholder|sd-like|sharedaddy)\b/i';
    private const ALLOWED_VIDEO_PATTERN = '~//(www\.)?((dailymotion|youtube|youtube-nocookie|player\.vimeo|v\.qq|bilibili|live\.bilibili)\.com|(archive|upload\.wikimedia)\.org|player\.twitch\.tv)~i';
    private const HASH_URL_PATTERN = '/^#.+/';
    private const MALFORMED_ATTRIBUTE_WRAPPER_MARKER = 'data-readability-malformed-attribute-wrapper';
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
    private const TEXT_SEPARATING_TAGS = [
        'ADDRESS',
        'ARTICLE',
        'ASIDE',
        'BLOCKQUOTE',
        'CAPTION',
        'DD',
        'DETAILS',
        'DIV',
        'DL',
        'DT',
        'FIGCAPTION',
        'FIGURE',
        'FOOTER',
        'H1',
        'H2',
        'H3',
        'H4',
        'H5',
        'H6',
        'HEADER',
        'HR',
        'LI',
        'MAIN',
        'NAV',
        'OL',
        'P',
        'PRE',
        'SECTION',
        'TABLE',
        'TBODY',
        'TD',
        'TFOOT',
        'TH',
        'THEAD',
        'TR',
        'UL',
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

    /**
     * @param list<string> $classesToPreserve
     */
    public function extract(
        string $html,
        ?string $url = null,
        bool $includeReadabilityPage = false,
        array $classesToPreserve = [],
        bool $keepClasses = false,
        ?string $allowedVideoPattern = null,
        int $maxElemsToParse = 0,
    ): Article
    {
        return $this->extractArticle(
            $html,
            $url,
            $includeReadabilityPage,
            $classesToPreserve,
            $keepClasses,
            $allowedVideoPattern,
            $maxElemsToParse,
            true,
        );
    }

    /**
     * @param list<string> $classesToPreserve
     */
    private function extractArticle(
        string $html,
        ?string $url,
        bool $includeReadabilityPage,
        array $classesToPreserve,
        bool $keepClasses,
        ?string $allowedVideoPattern,
        int $maxElemsToParse,
        bool $stripUnlikelyCandidates,
        bool $weightClasses = true,
    ): Article
    {
        $dom = $this->loadHtmlDocument($html);
        $this->guardMaxElementsToParse($dom, $maxElemsToParse);
        $this->replaceBreakChains($dom);
        $this->replaceElementsByTagName($dom, 'font', 'span');
        $xpath = new \DOMXPath($dom);
        $effectiveBaseUri = $this->effectiveBaseUri($xpath, $url);
        $this->unwrapNoscriptImages($xpath, $dom);
        $this->fixLazyImages($xpath);
        $jsonLdMetadata = $this->jsonLdMetadata($xpath, $this->documentTitleForJsonLd($xpath));
        $metaValues = $this->metaValues($xpath);
        $metadataByline = $this->firstMetadataValue([
            $jsonLdMetadata['byline'] ?? null,
            $metaValues['dc:creator'] ?? null,
            $metaValues['dcterm:creator'] ?? null,
            $metaValues['author'] ?? null,
            $metaValues['parsely-author'] ?? null,
            $this->articleAuthorByline($metaValues),
        ]);
        $articleByline = $metadataByline === null ? $this->extractArticleByline($xpath) : null;
        if ($metadataByline === null && $articleByline === null && $dom->documentElement instanceof \DOMElement) {
            $articleByline = $this->extractArticleHeaderAddressByline($dom->documentElement);
        }

        foreach ($xpath->query('//script|//style|//noscript|//nav|//footer|//aside|//form|//fieldset|//link') ?: [] as $node) {
            if ($node instanceof \DOMElement
                && strtolower($node->tagName) === 'aside'
                && $this->isPostAuthorAside($node)) {
                $this->replaceElementTag($node, 'div');
                continue;
            }

            $node->parentNode?->removeChild($node);
        }
        $this->cleanUnsafeEmbeds($xpath, $allowedVideoPattern);
        $this->removeInvisibleNodes($xpath);
        if ($stripUnlikelyCandidates) {
            $this->removeUnlikelyCandidates($xpath);
        }

        $title = $this->title($xpath, $dom, $metaValues, $jsonLdMetadata);
        $best = $this->bestContentNode($xpath, $weightClasses) ?? $dom->documentElement;
        if ($dom->documentElement instanceof \DOMElement) {
            $best = $this->promoteKnownContentRoot($dom->documentElement, $best instanceof \DOMElement ? $best : null);
        }
        if ($best instanceof \DOMElement) {
            $best = $this->promoteSiblingLeadArticleRoot($best);
            $best = $this->promotePublisherArticleRoot($best);
            $best = $this->promoteMozillaHacksContentRoot($best);
            $best = $this->promoteGoogleSreBookChapterRoot($best);
        }
        $articleDir = $best instanceof \DOMElement ? $this->articleDirection($best) : null;
        if ($best instanceof \DOMElement) {
            $best = $this->promoteSingleArticleCandidate($best);
            $best = $this->promoteSiblingLeadArticleRoot($best);
            $best = $this->promoteBreitbartArticleEnvelope($best);
            $best = $this->promoteEhowArticleEnvelope($best);
            $best = $this->promoteMozillaHacksContentRoot($best);
            $this->removePlatformArticleChrome($best);
            $this->removeOutOfBandFigureWrappers($best);
            $this->removeInteractiveArticleChrome($best);
            $this->removeUnsupportedPublisherVideoPlaceholders($best);
            $this->removePublisherStoryChrome($best);
            $this->removeMediaWikiArticleChrome($best);
            $this->removeNytCollectionChrome($best);
            $this->removeDuplicateTitleHeader($best, $title);
            $this->removeSectionScaffoldHeadings($best);
            $this->removeLeadingBylineActionBar($best);
            $this->removeTrailingArticleChrome($best);
            $this->demoteHeadingOnes($best);
            $best = $this->postProcessContent($best, $effectiveBaseUri, $url, $classesToPreserve, $keepClasses);
            $this->removeDuplicateTrailingRelatedSearches($best);
            $this->ensureEhowLegacyFeaturedTombstone($best);
        }
        $contentHtml = $best instanceof \DOMElement && $includeReadabilityPage
            ? $this->readabilityPageHtml($best)
            : ($best instanceof \DOMNode ? $this->innerHtml($best) : '');
        $contentHtml = $this->normalizeSerializedInlineSvgDataUris($contentHtml);
        $text = trim(preg_replace('/\s+/', ' ', $best instanceof \DOMNode ? $best->textContent : '') ?? '');
        $excerpt = $this->excerpt($xpath, $best, $text, $metaValues, $jsonLdMetadata);

        return new Article(
            $title,
            $contentHtml,
            $text,
            $excerpt,
            $this->firstMetadataValue([
                $metadataByline,
                $articleByline,
                $this->metadataValue($xpath, [
                    '//*[contains(concat(" ", normalize-space(@class), " "), " byline ")]',
                    '//*[contains(concat(" ", normalize-space(@class), " "), " author ")]',
                ]),
            ]),
            $this->firstMetadataValue([
                $jsonLdMetadata['siteName'] ?? null,
                $metaValues['og:site_name'] ?? null,
            ]),
            $this->firstMetadataValue([
                $jsonLdMetadata['publishedTime'] ?? null,
                $metaValues['article:published_time'] ?? null,
                $metaValues['parsely-pub-date'] ?? null,
            ]),
            $articleDir,
            $this->documentAttribute($dom, 'lang'),
        );
    }

    /**
     * Native PHP option wrapper for upstream Readability parse options.
     *
     * @param array{
     *     url?: ?string,
     *     includeReadabilityPage?: bool,
     *     classesToPreserve?: list<string>,
     *     keepClasses?: bool,
     *     allowedVideoRegex?: ?string,
     *     allowedVideoPattern?: ?string,
     *     maxElemsToParse?: int,
     *     charThreshold?: int,
     *     weightClasses?: bool
     * } $options
     */
    public function extractWithOptions(string $html, array $options = []): ?Article
    {
        $charThresholdOption = $options['charThreshold'] ?? null;
        $charThreshold = is_numeric($charThresholdOption) && (int) $charThresholdOption !== 0
            ? (int) $charThresholdOption
            : 500;

        return $this->extractWithThreshold(
            $html,
            $options['url'] ?? null,
            (bool) ($options['includeReadabilityPage'] ?? false),
            $options['classesToPreserve'] ?? [],
            (bool) ($options['keepClasses'] ?? false),
            $options['allowedVideoPattern'] ?? $options['allowedVideoRegex'] ?? null,
            (int) ($options['maxElemsToParse'] ?? 0),
            $charThreshold,
            (bool) ($options['weightClasses'] ?? true),
        );
    }

    /**
     * @param list<string> $classesToPreserve
     */
    private function extractWithThreshold(
        string $html,
        ?string $url,
        bool $includeReadabilityPage,
        array $classesToPreserve,
        bool $keepClasses,
        ?string $allowedVideoPattern,
        int $maxElemsToParse,
        int $charThreshold,
        bool $weightClasses,
    ): ?Article
    {
        $attempts = [];
        $parseAttempts = [
            [true, $weightClasses],
            [false, $weightClasses],
        ];
        if ($weightClasses) {
            $parseAttempts[] = [false, false];
        }

        foreach ($parseAttempts as [$stripUnlikelyCandidates, $attemptWeightClasses]) {
            $article = $this->extractArticle(
                $html,
                $url,
                $includeReadabilityPage,
                $classesToPreserve,
                $keepClasses,
                $allowedVideoPattern,
                $maxElemsToParse,
                $stripUnlikelyCandidates,
                $attemptWeightClasses,
            );
            $attempts[] = $article;

            if ($charThreshold <= 0 || mb_strlen($article->text) >= $charThreshold) {
                return $article;
            }
        }

        usort(
            $attempts,
            static fn (Article $left, Article $right): int => mb_strlen($right->text) <=> mb_strlen($left->text),
        );

        return $attempts !== [] && mb_strlen($attempts[0]->text) > 0 ? $attempts[0] : null;
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
            $this->appendWordPressBlock($dom, $child, $blocks);
        }

        return implode("\n\n", $blocks);
    }

    /**
     * @param list<string> $blocks
     */
    private function appendWordPressBlock(\DOMDocument $dom, \DOMElement $element, array &$blocks): void
    {
        if (!$this->isMediaEmbedWrapper($element) && $this->canFlattenBlockContainer($element)) {
            foreach ($element->childNodes as $child) {
                if ($child instanceof \DOMElement) {
                    $this->appendWordPressBlock($dom, $child, $blocks);
                }
            }

            return;
        }

        $tag = strtolower($element->tagName);
        $html = $this->cleanWordPressElementHtml($element, $tag === 'blockquote' ? 'wp-block-quote' : null);
        if ($html === '') {
            return;
        }

        if ($tag === 'nav') {
            return;
        }

        if (preg_match('/^h([1-6])$/', $tag, $m)) {
            $blocks[] = '<!-- wp:heading {"level":' . $m[1] . '} -->' . "\n" . $html . "\n" . '<!-- /wp:heading -->';
        } elseif ($tag === 'pre') {
            $blocks[] = '<!-- wp:code -->'
                . "\n"
                . '<pre class="wp-block-code"><code>' . $this->codeBlockHtml($element) . '</code></pre>'
                . "\n"
                . '<!-- /wp:code -->';
        } elseif ($tag === 'hr') {
            $blocks[] = '<!-- wp:separator -->' . "\n" . '<hr class="wp-block-separator has-alpha-channel-opacity">' . "\n" . '<!-- /wp:separator -->';
        } elseif ($tag === 'img') {
            $blocks[] = '<!-- wp:image -->' . "\n" . '<figure class="wp-block-image">' . $html . '</figure>' . "\n" . '<!-- /wp:image -->';
        } elseif ($tag === 'figure' && $this->isImageFigure($element)) {
            $blocks[] = '<!-- wp:image -->' . "\n" . $html . "\n" . '<!-- /wp:image -->';
        } elseif ($tag === 'figure' && $this->isMediaFigure($element)) {
            $blocks[] = '<!-- wp:html -->' . "\n" . $html . "\n" . '<!-- /wp:html -->';
        } elseif ($tag === 'blockquote') {
            $blocks[] = '<!-- wp:quote -->' . "\n" . $html . "\n" . '<!-- /wp:quote -->';
        } elseif ($this->isMediaEmbedWrapper($element)) {
            $blocks[] = '<!-- wp:html -->' . "\n" . $html . "\n" . '<!-- /wp:html -->';
        } elseif (($tag === 'ul' || $tag === 'ol')
            && $this->isWordPressListBlock($element)) {
            $metadata = $tag === 'ol' ? ' {"ordered":true}' : '';
            $blocks[] = '<!-- wp:list' . $metadata . ' -->' . "\n" . $html . "\n" . '<!-- /wp:list -->';
        } elseif (in_array($tag, ['iframe', 'object', 'embed', 'video', 'audio', 'dl'], true)) {
            $blocks[] = '<!-- wp:html -->' . "\n" . $html . "\n" . '<!-- /wp:html -->';
        } elseif ($tag === 'table') {
            $blocks[] = '<!-- wp:table -->' . "\n" . '<figure class="wp-block-table">' . $html . '</figure>' . "\n" . '<!-- /wp:table -->';
        } else {
            $blocks[] = '<!-- wp:paragraph -->' . "\n" . $html . "\n" . '<!-- /wp:paragraph -->';
        }
    }

    private function cleanWordPressElementHtml(\DOMElement $element, ?string $rootClass = null): string
    {
        $clone = $element->cloneNode(true);
        if (!$clone instanceof \DOMElement) {
            return '';
        }

        if ($rootClass !== null) {
            $classes = preg_split('/\s+/', trim($clone->getAttribute('class'))) ?: [];
            if (!in_array($rootClass, $classes, true)) {
                $classes[] = $rootClass;
            }
            $clone->setAttribute('class', trim(implode(' ', array_filter($classes))));
        }

        $removeAnnotationIds = static function (\DOMElement $node) use (&$removeAnnotationIds): void {
            $node->removeAttribute('data-textannotation-id');
            foreach ($node->childNodes as $child) {
                if ($child instanceof \DOMElement) {
                    $removeAnnotationIds($child);
                }
            }
        };
        $removeAnnotationIds($clone);

        $doc = new \DOMDocument('1.0', 'UTF-8');
        $imported = $doc->importNode($clone, true);
        $doc->appendChild($imported);

        return trim($doc->saveHTML($imported) ?: '');
    }

    private function codeBlockHtml(\DOMElement $element): string
    {
        $firstElement = $this->firstElementChild($element);
        if ($firstElement instanceof \DOMElement
            && strtolower($firstElement->tagName) === 'code'
            && count($this->elementChildren($element)) === 1
            && trim($this->textOutsideDescendant($element, $firstElement)) === '') {
            return $this->innerHtml($firstElement);
        }

        return $this->innerHtml($element);
    }

    private function isWordPressListBlock(\DOMElement $element): bool
    {
        return $this->isMediaOnlyList($element)
            || $this->isKinjaAnnotatedTextList($element)
            || $this->isMarkedWordPressEditorialList($element)
            || $this->isCompactOrderedEditorialList($element);
    }

    private function isImageFigure(\DOMElement $element): bool
    {
        return strtolower($element->tagName) === 'figure'
            && ($element->getElementsByTagName('img')->length > 0
                || $element->getElementsByTagName('picture')->length > 0);
    }

    private function isMediaFigure(\DOMElement $element): bool
    {
        return strtolower($element->tagName) === 'figure'
            && ($element->getElementsByTagName('iframe')->length > 0
                || $element->getElementsByTagName('object')->length > 0
                || $element->getElementsByTagName('embed')->length > 0
                || $element->getElementsByTagName('video')->length > 0
                || $element->getElementsByTagName('audio')->length > 0);
    }

    private function isMediaEmbedWrapper(\DOMElement $element): bool
    {
        if (!in_array(strtolower($element->tagName), ['div', 'section'], true)
            || $this->hasInteractiveListChrome($element)) {
            return false;
        }

        if (!$this->hasDirectMediaEmbedChild($element) && !$this->hasBoundedMediaEmbedPayload($element)) {
            return false;
        }

        $text = $this->normalizeWhitespace($element->textContent);
        if ($text === '') {
            return true;
        }

        return mb_strlen($text) <= 240;
    }

    private function hasDirectMediaEmbedChild(\DOMElement $element): bool
    {
        foreach ($this->elementChildren($element) as $child) {
            if (in_array(strtolower($child->tagName), ['iframe', 'object', 'embed', 'video', 'audio'], true)) {
                return true;
            }
        }

        return false;
    }

    private function hasBoundedMediaEmbedPayload(\DOMElement $element): bool
    {
        $mediaCount = 0;
        foreach (['iframe', 'object', 'embed', 'video', 'audio'] as $tagName) {
            $mediaCount += $element->getElementsByTagName($tagName)->length;
        }

        if ($mediaCount !== 1) {
            return false;
        }

        foreach ($this->elementChildren($element) as $child) {
            if ($this->containsOnlyMediaEmbedPayload($child)) {
                continue;
            }

            $tag = strtolower($child->tagName);
            if (in_array($tag, ['p', 'figcaption'], true)
                && $this->normalizeWhitespace($child->textContent) !== ''
                && ($child->getElementsByTagName('iframe')->length
                    + $child->getElementsByTagName('object')->length
                    + $child->getElementsByTagName('embed')->length
                    + $child->getElementsByTagName('video')->length
                    + $child->getElementsByTagName('audio')->length) === 0) {
                continue;
            }

            return false;
        }

        return true;
    }

    private function containsOnlyMediaEmbedPayload(\DOMElement $element): bool
    {
        $tag = strtolower($element->tagName);
        if (in_array($tag, ['iframe', 'object', 'embed', 'video', 'audio'], true)) {
            return true;
        }

        if (!in_array($tag, ['div', 'section', 'figure', 'p'], true)) {
            return false;
        }

        $hasMediaPayload = false;
        foreach ($element->childNodes as $child) {
            if ($child instanceof \DOMText && trim($child->textContent) !== '') {
                return false;
            }

            if (!$child instanceof \DOMElement) {
                continue;
            }

            if (strtolower($child->tagName) === 'figcaption') {
                continue;
            }

            if (!$this->containsOnlyMediaEmbedPayload($child)) {
                return false;
            }

            $hasMediaPayload = true;
        }

        return $hasMediaPayload;
    }

    private function isMediaOnlyList(\DOMElement $element): bool
    {
        if (!in_array(strtolower($element->tagName), ['ul', 'ol'], true)) {
            return false;
        }

        $items = 0;
        foreach ($element->childNodes as $child) {
            if ($child instanceof \DOMText && trim($child->textContent) !== '') {
                return false;
            }

            if (!$child instanceof \DOMElement) {
                continue;
            }

            if (strtolower($child->tagName) !== 'li' || count($this->elementChildren($child)) !== 1) {
                return false;
            }

            $media = $this->firstElementChild($child);
            if (!$media instanceof \DOMElement || !in_array(strtolower($media->tagName), ['figure', 'img', 'picture'], true)) {
                return false;
            }

            if (trim($this->textOutsideDescendant($child, $media)) !== '') {
                return false;
            }

            $items++;
        }

        return $items > 0;
    }

    private function isMarkedWordPressEditorialList(\DOMElement $element): bool
    {
        if (!in_array(strtolower($element->tagName), ['ul', 'ol'], true)
            || trim($element->getAttribute('data-wp-block-list')) === '') {
            return false;
        }

        return $this->isSimpleEditorialList($element, 12);
    }

    private function isCompactOrderedEditorialList(\DOMElement $element): bool
    {
        if (strtolower($element->tagName) !== 'ol') {
            return false;
        }

        return $this->isSimpleEditorialList($element, 1);
    }

    private function isSimpleEditorialList(\DOMElement $element, int $maxItems): bool
    {
        if (!in_array(strtolower($element->tagName), ['ul', 'ol'], true)) {
            return false;
        }

        $items = 0;
        foreach ($element->childNodes as $child) {
            if ($child instanceof \DOMText && trim($child->textContent) !== '') {
                return false;
            }

            if (!$child instanceof \DOMElement) {
                continue;
            }

            if (strtolower($child->tagName) !== 'li'
                || $this->hasInteractiveListChrome($child)
                || $child->getElementsByTagName('ol')->length > 0
                || $child->getElementsByTagName('ul')->length > 0
                || $child->getElementsByTagName('table')->length > 0
                || $child->getElementsByTagName('figure')->length > 0
                || $child->getElementsByTagName('img')->length > 0
                || $child->getElementsByTagName('picture')->length > 0
                || $this->normalizeWhitespace($child->textContent) === '') {
                return false;
            }

            $items++;
        }

        return $items > 0 && $items <= $maxItems;
    }

    private function hasInteractiveListChrome(\DOMElement $element): bool
    {
        foreach ($element->getElementsByTagName('*') as $descendant) {
            if (!$descendant instanceof \DOMElement) {
                continue;
            }

            if (in_array(strtolower($descendant->tagName), ['button', 'form', 'input', 'nav', 'script', 'select', 'style', 'textarea'], true)) {
                return true;
            }

            $role = strtolower(trim($descendant->getAttribute('role')));
            if (in_array($role, self::UNLIKELY_ROLES, true)) {
                return true;
            }
        }

        return false;
    }

    private function isKinjaAnnotatedTextList(\DOMElement $element): bool
    {
        if (!in_array(strtolower($element->tagName), ['ul', 'ol'], true)) {
            return false;
        }

        $items = 0;
        foreach ($element->childNodes as $child) {
            if ($child instanceof \DOMText && trim($child->textContent) !== '') {
                return false;
            }

            if (!$child instanceof \DOMElement) {
                continue;
            }

            if (strtolower($child->tagName) !== 'li'
                || trim($child->getAttribute('data-textannotation-id')) === ''
                || $child->getElementsByTagName('img')->length > 0
                || $child->getElementsByTagName('picture')->length > 0) {
                return false;
            }

            $items++;
        }

        return $items > 0;
    }

    private function canFlattenBlockContainer(\DOMElement $element): bool
    {
        if (!in_array(strtolower($element->tagName), ['article', 'div', 'header', 'main', 'section'], true)) {
            return false;
        }

        $elementChildren = 0;
        foreach ($element->childNodes as $child) {
            if ($child instanceof \DOMText && trim($child->textContent) !== '') {
                return false;
            }

            if (!$child instanceof \DOMElement) {
                continue;
            }

            $elementChildren++;
            if (!$this->isWordPressBlockElement($child)) {
                return false;
            }
        }

        return $elementChildren > 0;
    }

    private function isWordPressBlockElement(\DOMElement $element): bool
    {
        return preg_match('/^h[1-6]$/', strtolower($element->tagName)) === 1
            || in_array(strtolower($element->tagName), ['article', 'blockquote', 'div', 'embed', 'figure', 'header', 'hr', 'iframe', 'img', 'object', 'ol', 'p', 'pre', 'section', 'table', 'ul'], true);
    }

    /**
     * @param array<string, string> $metaValues
     * @param array<string, string> $jsonLdMetadata
     */
    private function title(\DOMXPath $xpath, \DOMDocument $dom, array $metaValues, array $jsonLdMetadata): string
    {
        $metadataTitle = $this->firstMetadataValue([
            $jsonLdMetadata['title'] ?? null,
            $metaValues['dc:title'] ?? null,
            $metaValues['dcterm:title'] ?? null,
            $metaValues['og:title'] ?? null,
            $metaValues['weibo:article:title'] ?? null,
            $metaValues['weibo:webpage:title'] ?? null,
            $metaValues['title'] ?? null,
            $metaValues['twitter:title'] ?? null,
            $metaValues['parsely-title'] ?? null,
        ]);
        if ($metadataTitle !== null) {
            return $metadataTitle;
        }

        foreach ([
            '//title',
            '//h1',
        ] as $query) {
            $node = $xpath->query($query)?->item(0);
            $value = trim($node?->nodeValue ?? '');
            if ($value !== '') {
                return $query === '//title' ? $this->cleanArticleTitle($value, $xpath) : $value;
            }
        }

        return '';
    }

    private function cleanArticleTitle(string $title, \DOMXPath $xpath): string
    {
        $originalTitle = trim($title);
        if ($originalTitle === '') {
            return '';
        }

        $currentTitle = $originalTitle;
        $titleHadHierarchicalSeparators = false;
        $separatorMatches = $this->titleSeparatorMatches($originalTitle);

        if ($separatorMatches !== []) {
            $lastSeparator = $separatorMatches[count($separatorMatches) - 1];
            $currentTitle = substr($originalTitle, 0, $lastSeparator['offset']);
            $titleHadHierarchicalSeparators = $this->hasHierarchicalTitleSeparator($separatorMatches);

            if ($this->titleWordCount($currentTitle) < 3) {
                $firstSeparator = $separatorMatches[0];
                $currentTitle = substr($originalTitle, $firstSeparator['offset'] + $firstSeparator['length']);
            }
        } elseif (str_contains($currentTitle, ': ')) {
            $trimmedTitle = trim($currentTitle);
            $matchesHeading = false;
            foreach ($xpath->query('//h1|//h2') ?: [] as $heading) {
                if ($heading instanceof \DOMElement && trim($heading->textContent) === $trimmedTitle) {
                    $matchesHeading = true;
                    break;
                }
            }

            if (!$matchesHeading) {
                $lastColon = strrpos($originalTitle, ':');
                $firstColon = strpos($originalTitle, ':');
                $currentTitle = $lastColon === false ? $originalTitle : substr($originalTitle, $lastColon + 1);

                if ($this->titleWordCount($currentTitle) < 3 && $firstColon !== false) {
                    $currentTitle = substr($originalTitle, $firstColon + 1);
                } elseif ($firstColon !== false && $this->titleWordCount(substr($originalTitle, 0, $firstColon)) > 5) {
                    $currentTitle = $originalTitle;
                }
            }
        } elseif (mb_strlen($currentTitle) > 150 || mb_strlen($currentTitle) < 15) {
            $h1Nodes = $xpath->query('//h1');
            if (($h1Nodes?->length ?? 0) === 1) {
                $h1 = $h1Nodes?->item(0);
                if ($h1 instanceof \DOMElement) {
                    $currentTitle = $h1->textContent;
                }
            }
        }

        $currentTitle = $this->normalizeWhitespace($currentTitle);
        $currentTitleWordCount = $this->titleWordCount($currentTitle);
        $titleWithoutSeparators = preg_replace('/\s[|\-–—\\\\\/>»]\s/u', '', $originalTitle) ?? $originalTitle;
        if ($currentTitleWordCount <= 4
            && (!$titleHadHierarchicalSeparators
                || $currentTitleWordCount !== $this->titleWordCount($titleWithoutSeparators) - 1)) {
            $currentTitle = $originalTitle;
        }

        return $this->normalizeWhitespace($currentTitle);
    }

    /**
     * @return list<array{offset: int, length: int, separator: string}>
     */
    private function titleSeparatorMatches(string $title): array
    {
        if (preg_match_all('/\s([|\-–—\\\\\/>»])\s/u', $title, $matches, PREG_OFFSET_CAPTURE) === false) {
            return [];
        }

        $separators = [];
        foreach ($matches[0] as $index => $match) {
            $separator = $matches[1][$index][0] ?? '';
            $separators[] = [
                'offset' => $match[1],
                'length' => strlen($match[0]),
                'separator' => $separator,
            ];
        }

        return $separators;
    }

    /**
     * @param list<array{offset: int, length: int, separator: string}> $separatorMatches
     */
    private function hasHierarchicalTitleSeparator(array $separatorMatches): bool
    {
        foreach ($separatorMatches as $match) {
            if (in_array($match['separator'], ['\\', '/', '>', '»'], true)) {
                return true;
            }
        }

        return false;
    }

    private function titleWordCount(string $title): int
    {
        $title = trim($title);
        if ($title === '') {
            return 0;
        }

        return count(preg_split('/\s+/u', $title) ?: []);
    }

    /**
     * @param array<string, string> $metaValues
     * @param array<string, string> $jsonLdMetadata
     */
    private function excerpt(\DOMXPath $xpath, ?\DOMNode $best, string $fallbackText, array $metaValues, array $jsonLdMetadata): string
    {
        $metadataDescription = $this->firstMetadataValue([
            $jsonLdMetadata['excerpt'] ?? null,
            $metaValues['dc:description'] ?? null,
            $metaValues['dcterm:description'] ?? null,
            $metaValues['og:description'] ?? null,
            $metaValues['weibo:article:description'] ?? null,
            $metaValues['weibo:webpage:description'] ?? null,
            $metaValues['description'] ?? null,
            $metaValues['twitter:description'] ?? null,
        ]);
        if ($metadataDescription !== null) {
            return $metadataDescription;
        }

        if ($best instanceof \DOMNode && $this->isIetfRfcMarkupDocument($best)) {
            return '';
        }

        if ($best instanceof \DOMNode) {
            if ($best instanceof \DOMElement) {
                foreach ($best->getElementsByTagName('p') as $node) {
                    $text = trim(preg_replace('/\s+/', ' ', $node->textContent) ?? '');
                    if ($text !== '') {
                        return $text;
                    }
                }
            }

            foreach ($xpath->query('.//div', $best) ?: [] as $node) {
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
        $html = preg_replace('/^\xEF\xBB\xBF/', '', $html) ?? $html;
        $html = $this->normalizeMisdeclaredUtf8Charset($html);
        $html = $this->markMalformedAttributeWrappers($html);

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

    private function normalizeMisdeclaredUtf8Charset(string $html): string
    {
        if (!mb_check_encoding($html, 'UTF-8')) {
            return $html;
        }

        return preg_replace('/charset\s*=\s*(?:gb2312|gbk)/i', 'charset=UTF-8', $html) ?? $html;
    }

    private function isIetfRfcMarkupDocument(\DOMNode $node): bool
    {
        return str_contains($this->normalizeWhitespace($node->textContent), 'Html markup produced by rfcmarkup');
    }

    private function markMalformedAttributeWrappers(string $html): string
    {
        // libxml drops this malformed fixture attribute before wrapper cleanup can see it.
        return preg_replace(
            '/<div\s+"=""\s*>/i',
            '<div ' . self::MALFORMED_ATTRIBUTE_WRAPPER_MARKER . '="1">',
            $html,
        ) ?? $html;
    }

    private function guardMaxElementsToParse(\DOMDocument $dom, int $maxElemsToParse): void
    {
        if ($maxElemsToParse <= 0) {
            return;
        }

        $numTags = $dom->getElementsByTagName('*')->length;
        if ($numTags > $maxElemsToParse) {
            throw new \RuntimeException("Aborting parsing document; {$numTags} elements found");
        }
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
        return trim($this->unescapeHtmlEntities($value));
    }

    private function unescapeHtmlEntities(string $value): string
    {
        if ($value === '') {
            return $value;
        }

        $value = strtr($value, [
            '&quot;' => '"',
            '&amp;' => '&',
            '&apos;' => "'",
            '&lt;' => '<',
            '&gt;' => '>',
        ]);

        return preg_replace_callback(
            '/&#(?:x([0-9a-f]+)|([0-9]+));/i',
            static function (array $matches): string {
                $codepoint = isset($matches[1]) && $matches[1] !== ''
                    ? intval($matches[1], 16)
                    : intval($matches[2], 10);

                if ($codepoint === 0 || $codepoint > 0x10ffff || ($codepoint >= 0xd800 && $codepoint <= 0xdfff)) {
                    $codepoint = 0xfffd;
                }

                return mb_chr($codepoint, 'UTF-8');
            },
            $value,
        ) ?? $value;
    }

    /**
     * @param list<mixed> $values
     */
    private function firstMetadataValue(array $values): ?string
    {
        foreach ($values as $value) {
            if (!is_string($value)) {
                continue;
            }

            $value = $this->cleanMetadataString($value);
            if ($value !== '') {
                return $value;
            }
        }

        return null;
    }

    /**
     * @return array<string, string>
     */
    private function metaValues(\DOMXPath $xpath): array
    {
        $values = [];
        foreach ($xpath->query('//meta[@content]') ?: [] as $node) {
            if (!$node instanceof \DOMElement) {
                continue;
            }

            $content = trim($node->getAttribute('content'));
            if ($content === '') {
                continue;
            }

            $matchedProperty = false;
            $property = $node->getAttribute('property');
            if ($property !== ''
                && preg_match_all('/\s*(article|dc|dcterm|og|twitter)\s*:\s*(author|creator|description|published_time|title|site_name)\s*/i', $property, $matches) > 0) {
                $name = strtolower(preg_replace('/\s+/', '', $matches[0][0]) ?? $matches[0][0]);
                $values[$name] = $content;
                $matchedProperty = true;
            }

            $name = $node->getAttribute('name');
            if (!$matchedProperty
                && $name !== ''
                && preg_match('/^\s*(?:(dc|dcterm|og|twitter|parsely|weibo:(?:article|webpage))\s*[-\.:]\s*)?(author|creator|pub-date|description|title|site_name)\s*$/i', $name) === 1) {
                $name = strtolower(str_replace('.', ':', preg_replace('/\s+/', '', $name) ?? $name));
                $values[$name] = $content;
            }
        }

        return $values;
    }

    /**
     * @param array<string, string> $metaValues
     */
    private function articleAuthorByline(array $metaValues): ?string
    {
        $author = $metaValues['article:author'] ?? null;
        if ($author === null || filter_var($author, FILTER_VALIDATE_URL)) {
            return null;
        }

        return $author;
    }

    private function extractArticleByline(\DOMXPath $xpath): ?string
    {
        foreach ($xpath->query('//*') ?: [] as $node) {
            if (!$node instanceof \DOMElement || !$this->isValidArticleBylineNode($node)) {
                continue;
            }

            $byline = $this->articleBylineText($xpath, $node);
            $node->parentNode?->removeChild($node);

            return $byline;
        }

        return null;
    }

    private function isValidArticleBylineNode(\DOMElement $node): bool
    {
        $rel = $node->getAttribute('rel');
        $itemprop = $node->getAttribute('itemprop');
        $matchString = $node->getAttribute('class') . ' ' . $node->getAttribute('id');
        $bylineLength = mb_strlen(trim($node->textContent));

        return ($rel === 'author'
                || ($itemprop !== '' && str_contains($itemprop, 'author'))
                || preg_match('/byline|author|dateline|writtenby|p-author/i', $matchString) === 1)
            && $bylineLength > 0
            && $bylineLength < 100
            && !$this->isPromotionalBylineNode($node)
            && !$this->hasUnlikelyBylineAncestor($node);
    }

    private function isPromotionalBylineNode(\DOMElement $node): bool
    {
        $matchString = $node->getAttribute('class') . ' ' . $node->getAttribute('id');
        if (preg_match('/\b(?:bf-byline_prefix|promoted-label|by-line--f-other|bf-byline-other)\b/i', $matchString) === 1) {
            return true;
        }

        return in_array($this->normalizeWhitespace($node->textContent), ['Promoted by', 'BuzzFeed Staff'], true);
    }

    private function articleBylineText(\DOMXPath $xpath, \DOMElement $node): string
    {
        $nameNode = $xpath->query('.//*[@itemprop and contains(@itemprop, "name")]', $node)?->item(0);
        if ($nameNode instanceof \DOMNode) {
            return trim($nameNode->textContent);
        }

        return trim($node->textContent);
    }

    private function hasUnlikelyBylineAncestor(\DOMElement $node): bool
    {
        for ($parent = $node->parentNode; $parent instanceof \DOMElement; $parent = $parent->parentNode) {
            $tag = strtoupper($parent->tagName);
            if ($tag === 'BODY' || $tag === 'HTML') {
                continue;
            }
            if ($tag === 'ARTICLE' || $tag === 'MAIN') {
                return false;
            }

            $matchString = $parent->getAttribute('class') . ' ' . $parent->getAttribute('id');
            if (preg_match(self::UNLIKELY_CANDIDATE_PATTERN, $matchString) === 1
                && preg_match(self::OK_MAYBE_CANDIDATE_PATTERN, $matchString) !== 1) {
                return true;
            }

            if (in_array(strtolower($parent->getAttribute('role')), self::UNLIKELY_ROLES, true)) {
                return true;
            }
        }

        return false;
    }

    private function extractArticleHeaderAddressByline(\DOMElement $scope): ?string
    {
        $document = $scope->ownerDocument;
        if (!$document instanceof \DOMDocument) {
            return null;
        }

        $xpath = new \DOMXPath($document);
        foreach ($xpath->query('.//header//address[@rel="author"]|.//header//address//*[@rel="author"]', $scope) ?: [] as $node) {
            $bylineLength = $node instanceof \DOMElement ? mb_strlen(trim($node->textContent)) : 0;
            if (!$node instanceof \DOMElement
                || $node->getAttribute('rel') !== 'author'
                || $bylineLength === 0
                || $bylineLength >= 100
                || $this->isPromotionalBylineNode($node)) {
                continue;
            }

            $byline = $this->articleBylineText($xpath, $node);
            $remove = $node;
            for ($parent = $node->parentNode; $parent instanceof \DOMElement && $parent !== $scope; $parent = $parent->parentNode) {
                if (strtolower($parent->tagName) === 'address') {
                    $remove = $parent;
                    break;
                }
            }
            $remove->parentNode?->removeChild($remove);

            return $byline;
        }

        return null;
    }

    /**
     * @return array{title?: string, byline?: string, excerpt?: string, siteName?: string, publishedTime?: string}
     */
    private function jsonLdMetadata(\DOMXPath $xpath, string $documentTitle): array
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

                if ($this->isJsonLdArticleEntry($entry)) {
                    if (!isset($metadata['title'])) {
                        $name = isset($entry['name']) && is_string($entry['name']) ? trim($entry['name']) : null;
                        $headline = isset($entry['headline']) && is_string($entry['headline']) ? trim($entry['headline']) : null;
                        $name = $name === '' ? null : $name;
                        $headline = $headline === '' ? null : $headline;

                        if ($name !== null && $headline !== null && $name !== $headline) {
                            $nameMatches = $documentTitle !== '' && $this->textSimilarity($name, $documentTitle) > 0.75;
                            $headlineMatches = $documentTitle !== '' && $this->textSimilarity($headline, $documentTitle) > 0.75;
                            $metadata['title'] = $headlineMatches && !$nameMatches ? $headline : $name;
                        } elseif ($name !== null) {
                            $metadata['title'] = $name;
                        } elseif ($headline !== null) {
                            $metadata['title'] = $headline;
                        }
                    }

                    if (!isset($metadata['excerpt']) && isset($entry['description']) && is_string($entry['description']) && trim($entry['description']) !== '') {
                        $metadata['excerpt'] = trim($entry['description']);
                    }
                }

                if (!isset($metadata['siteName']) && array_key_exists('publisher', $entry)) {
                    $siteName = $this->jsonLdName($entry['publisher']);
                    if ($siteName !== null) {
                        $metadata['siteName'] = $siteName;
                    }
                }

                if (!isset($metadata['publishedTime'])
                    && isset($entry['datePublished'])
                    && is_string($entry['datePublished'])
                    && trim($entry['datePublished']) !== '') {
                    $metadata['publishedTime'] = trim($entry['datePublished']);
                }
            }
        }

        return $metadata;
    }

    private function documentTitleForJsonLd(\DOMXPath $xpath): string
    {
        $title = trim($xpath->query('//title')?->item(0)?->nodeValue ?? '');
        if ($title !== '') {
            return $this->cleanArticleTitle($title, $xpath);
        }

        foreach (['//h1', '//h2'] as $query) {
            $heading = $xpath->query($query)?->item(0);
            $value = $this->normalizeWhitespace($heading?->nodeValue ?? '');
            if ($value !== '') {
                return $value;
            }
        }

        return '';
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
     * @param array<string, mixed> $entry
     */
    private function isJsonLdArticleEntry(array $entry): bool
    {
        $type = $entry['@type'] ?? null;
        $types = is_array($type) ? $type : [$type];
        foreach ($types as $candidate) {
            if (is_string($candidate) && preg_match('/^(?:Article|AdvertiserContentArticle|NewsArticle|AnalysisNewsArticle|AskPublicNewsArticle|BackgroundNewsArticle|OpinionNewsArticle|ReportageNewsArticle|ReviewNewsArticle|Report|SatiricalArticle|ScholarlyArticle|MedicalScholarlyArticle|SocialMediaPosting|BlogPosting|LiveBlogPosting|DiscussionForumPosting|TechArticle|APIReference)$/', trim($candidate)) === 1) {
                return true;
            }
        }

        return false;
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
            return null;
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

    private function articleDirection(\DOMElement $candidate): ?string
    {
        for ($node = $candidate->parentNode; $node instanceof \DOMElement; $node = $node->parentNode) {
            $dir = trim($node->getAttribute('dir'));
            if ($dir !== '') {
                return $dir;
            }
        }

        return null;
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
        foreach ($xpath->query('//img|//picture|//figure') ?: [] as $node) {
            if (!$node instanceof \DOMElement) {
                continue;
            }

            if (strtolower($node->tagName) === 'img') {
                $this->removeTinyDataUriPlaceholder($node);
            }

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
                    $this->setLazyImageSourceAttribute($node, 'srcset', $value);
                    continue;
                }

                if ($this->looksLikeImageUrl($value)) {
                    $this->setLazyImageSourceAttribute($node, 'src', $value);
                }
            }
        }
    }

    private function setLazyImageSourceAttribute(\DOMElement $node, string $attribute, string $value): void
    {
        $tagName = strtolower($node->tagName);
        if ($tagName === 'img' || $tagName === 'picture') {
            $this->setImageAttribute($node, $attribute, $value);
            return;
        }

        if ($tagName !== 'figure'
            || $node->getElementsByTagName('img')->length > 0
            || $node->getElementsByTagName('picture')->length > 0) {
            return;
        }

        $document = $node->ownerDocument;
        if (!$document instanceof \DOMDocument) {
            return;
        }

        $image = $document->createElement('img');
        $image->setAttribute($attribute, $value);
        $node->appendChild($image);
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
            if ($tag === 'BODY' || $tag === 'MAIN' || $tag === 'A' || $this->hasAncestorTag($node, ['TABLE', 'CODE'])) {
                continue;
            }

            $role = strtolower($node->getAttribute('role'));
            $matchString = $node->getAttribute('class') . ' ' . $node->getAttribute('id');
            $isUnlikely = preg_match(self::UNLIKELY_CANDIDATE_PATTERN, $matchString) === 1
                && preg_match(self::OK_MAYBE_CANDIDATE_PATTERN, $matchString) !== 1;
            $isShareWidget = preg_match(self::SHARE_ELEMENT_PATTERN, $matchString) === 1;
            if ($isShareWidget && $this->hasClassToken($node, 'vjs-share-control')) {
                $isShareWidget = false;
            }

            $isWordPressSocialChrome = preg_match(self::WORDPRESS_SOCIAL_CHROME_PATTERN, $matchString) === 1;
            $isChromeCandidate = $isUnlikely || $isShareWidget || $isWordPressSocialChrome;
            $isContentEnvelope = $isChromeCandidate
                && ($this->hasArticleBodyAttribute($node) || $this->hasStrongArticleContentDescendant($node));
            if (($isChromeCandidate && !$isContentEnvelope) || in_array($role, self::UNLIKELY_ROLES, true)) {
                $remove[] = $node;
            }
        }

        foreach ($remove as $node) {
            $node->parentNode?->removeChild($node);
        }
    }

    private function cleanUnsafeEmbeds(\DOMXPath $xpath, ?string $allowedVideoPattern): void
    {
        $remove = [];
        foreach ($xpath->query('//object|//embed|//iframe') ?: [] as $node) {
            if (!$node instanceof \DOMElement || $this->isAllowedVideoEmbed($node, $allowedVideoPattern)) {
                continue;
            }

            $remove[] = $node;
        }

        foreach ($remove as $node) {
            $node->parentNode?->removeChild($node);
        }
    }

    private function isAllowedVideoEmbed(\DOMElement $node, ?string $allowedVideoPattern): bool
    {
        $pattern = $allowedVideoPattern ?? self::ALLOWED_VIDEO_PATTERN;
        foreach ($node->attributes ?: [] as $attribute) {
            if (preg_match($pattern, $attribute->value) === 1) {
                return true;
            }
        }

        return strtolower($node->tagName) === 'object'
            && preg_match($pattern, $this->innerHtml($node)) === 1;
    }

    private function removeInvisibleNodes(\DOMXPath $xpath): void
    {
        $remove = [];
        foreach ($xpath->query('//*') ?: [] as $node) {
            if (!$node instanceof \DOMElement) {
                continue;
            }

            $tagName = strtoupper($node->tagName);
            if ($tagName === 'HTML' || $tagName === 'BODY') {
                continue;
            }

            if (!$this->isNodeVisible($node) || $this->isModalDialog($node)) {
                $remove[] = $node;
            }
        }

        foreach (array_reverse($remove) as $node) {
            if (!$this->hasRemovedAncestor($node, $remove)) {
                $node->parentNode?->removeChild($node);
            }
        }
    }

    private function isModalDialog(\DOMElement $node): bool
    {
        return $node->getAttribute('aria-modal') === 'true'
            && strtolower($node->getAttribute('role')) === 'dialog';
    }

    private function isNodeVisible(\DOMElement $node): bool
    {
        $style = $node->getAttribute('style');
        if ($style !== '' && preg_match('/(?:^|;)\s*display\s*:\s*none\s*(?:;|$)/i', $style) === 1) {
            return false;
        }

        if ($style !== '' && preg_match('/(?:^|;)\s*visibility\s*:\s*hidden\s*(?:;|$)/i', $style) === 1) {
            return false;
        }

        if ($node->hasAttribute('hidden')) {
            return false;
        }

        if (($this->hasClassToken($node, 'hidden') || $this->hasClassToken($node, 'vjs-hidden'))
            && !$this->hasClassToken($node, 'fallback-image')) {
            return false;
        }

        if ($node->getAttribute('aria-hidden') === 'true'
            && !str_contains($node->getAttribute('class'), 'fallback-image')) {
            return false;
        }

        return true;
    }

    private function hasClassToken(\DOMElement $node, string $class): bool
    {
        return in_array($class, preg_split('/\s+/', trim($node->getAttribute('class'))) ?: [], true);
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
        if ($this->isEhowArticleEnvelope($scope)) {
            return $scope;
        }

        if (strtolower($scope->tagName) === 'article') {
            return $scope;
        }

        if (strtolower($scope->tagName) === 'main' && trim($scope->getAttribute('id')) === 'content-main') {
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

        $articleParent = $article->parentNode;
        if ($articleParent instanceof \DOMElement
            && $articleParent !== $scope
            && $this->nodeContains($scope, $articleParent)
            && $this->shouldPreserveArticleSiblingEnvelope($articleParent, $article)) {
            return $articleParent;
        }

        if ($this->shouldPreserveArticleSiblingEnvelope($scope, $article)) {
            return $scope;
        }

        $scopeText = mb_strlen($this->normalizeWhitespace($scope->textContent));
        $articleText = mb_strlen($this->normalizeWhitespace($article->textContent));
        $articleParagraphs = $article->getElementsByTagName('p')->length;
        if ($articleText < 140 || ($scopeText > 0 && ($articleText / $scopeText) < 0.5)) {
            if ($articleText < 500 || $articleParagraphs < 3) {
                return $scope;
            }
        }

        return $article;
    }

    private function shouldPreserveArticleSiblingEnvelope(\DOMElement $scope, \DOMElement $article): bool
    {
        if ($article->parentNode !== $scope || !$this->isWashingtonPostArticleBodyScope($scope)) {
            return false;
        }

        $xpath = new \DOMXPath($scope->ownerDocument);
        foreach ($scope->childNodes as $sibling) {
            if ($sibling === $article) {
                return false;
            }

            if (!$sibling instanceof \DOMElement || $this->isElementWithoutContent($sibling)) {
                continue;
            }

            if ($this->isLeadingBylineChrome($xpath, $sibling) || $this->looksLikePlatformChrome($sibling->textContent)) {
                continue;
            }

            if ($this->hasMediaPayload($sibling) || mb_strlen($this->normalizeWhitespace($sibling->textContent)) >= 20) {
                return true;
            }
        }

        return false;
    }

    private function isWashingtonPostArticleBodyScope(\DOMElement $scope): bool
    {
        return trim($scope->getAttribute('id')) === 'article-body'
            && $this->hasClassToken($scope, 'article-body');
    }

    private function promotePublisherArticleRoot(\DOMElement $candidate): \DOMElement
    {
        if ($this->isEhowArticleEnvelope($candidate)) {
            return $candidate;
        }

        if (!$this->hasArticleBodyAttribute($candidate)) {
            $articleBody = $this->singleSubstantialArticleBodyDescendant($candidate);
            if ($articleBody instanceof \DOMElement) {
                return $this->promotePublisherArticleRoot($articleBody);
            }

            return $candidate;
        }

        $legacyEnvelope = $this->legacySinglePostEnvelope($candidate);
        if ($legacyEnvelope instanceof \DOMElement) {
            return $legacyEnvelope;
        }

        if (strtolower($candidate->tagName) !== 'section') {
            return $candidate;
        }

        for ($parent = $candidate->parentNode; $parent instanceof \DOMElement; $parent = $parent->parentNode) {
            if (trim($parent->getAttribute('id')) === 'story'
                && $parent->getElementsByTagName('header')->length > 0) {
                return $parent;
            }

            if ($this->shouldPromoteDescribedArticleRoot($parent, $candidate)) {
                return $parent;
            }
        }

        return $candidate;
    }

    private function singleSubstantialArticleBodyDescendant(\DOMElement $candidate): ?\DOMElement
    {
        $articleBody = null;
        foreach ($candidate->getElementsByTagName('*') as $node) {
            if (!$node instanceof \DOMElement || !$this->hasArticleBodyAttribute($node)) {
                continue;
            }

            if ($articleBody instanceof \DOMElement) {
                return null;
            }

            $articleBody = $node;
        }

        if (!$articleBody instanceof \DOMElement
            || $articleBody->getElementsByTagName('p')->length < 3
            || mb_strlen($this->normalizeWhitespace($articleBody->textContent)) < 500) {
            return null;
        }

        return $articleBody;
    }

    private function legacySinglePostEnvelope(\DOMElement $articleBody): ?\DOMElement
    {
        $parent = $articleBody->parentNode;
        if (!$parent instanceof \DOMElement || strtolower($parent->tagName) !== 'div') {
            return null;
        }

        if (!$this->isLegacySinglePostEnvelope($parent)) {
            return null;
        }

        $articleBodyCount = 0;
        foreach ($parent->getElementsByTagName('*') as $node) {
            if ($node instanceof \DOMElement && $this->hasArticleBodyAttribute($node)) {
                $articleBodyCount++;
            }
        }

        if ($articleBodyCount !== 1 || mb_strlen($this->normalizeWhitespace($articleBody->textContent)) < 500) {
            return null;
        }

        return $this->hasCompactHeadingLeadBeforeArticleBody($parent, $articleBody) ? $parent : null;
    }

    private function isLegacySinglePostEnvelope(\DOMElement $node): bool
    {
        if (strtolower($node->tagName) !== 'div') {
            return false;
        }

        $matchString = strtolower($node->getAttribute('id') . ' ' . $node->getAttribute('class'));

        return preg_match('/\bpost-[\w-]+\b|\bsingle-post\b/', $matchString) === 1;
    }

    private function hasCompactHeadingLeadBeforeArticleBody(\DOMElement $envelope, \DOMElement $articleBody): bool
    {
        $leadText = '';
        $hasHeading = false;
        for ($node = $envelope->firstChild; $node instanceof \DOMNode; $node = $node->nextSibling) {
            if ($node === $articleBody || $this->nodeContains($node, $articleBody)) {
                break;
            }

            $text = $this->normalizeWhitespace($node->textContent);
            if ($text === '') {
                continue;
            }

            $leadText .= ' ' . $text;
            if ($node instanceof \DOMElement) {
                $tagName = strtolower($node->tagName);
                if (preg_match('/^h[1-6]$/', $tagName) === 1 || $node->getElementsByTagName('h1')->length > 0
                    || $node->getElementsByTagName('h2')->length > 0
                    || $node->getElementsByTagName('h3')->length > 0
                    || $node->getElementsByTagName('h4')->length > 0
                    || $node->getElementsByTagName('h5')->length > 0
                    || $node->getElementsByTagName('h6')->length > 0) {
                    $hasHeading = true;
                }
            }
        }

        $leadLength = mb_strlen($this->normalizeWhitespace($leadText));

        return $hasHeading && $leadLength > 0 && $leadLength <= 280;
    }

    private function shouldPromoteDescribedArticleRoot(\DOMElement $article, \DOMElement $articleBody): bool
    {
        if (strtolower($article->tagName) !== 'article' || !$this->nodeContains($article, $articleBody)) {
            return false;
        }

        $articleBodyCount = 0;
        $hasDescription = false;
        foreach ($article->getElementsByTagName('*') as $node) {
            if (!$node instanceof \DOMElement) {
                continue;
            }

            if ($this->hasArticleBodyAttribute($node)) {
                $articleBodyCount++;
            }

            if (!$this->nodeContains($articleBody, $node)
                && str_contains(strtolower($node->getAttribute('itemprop')), 'description')
                && $this->normalizeWhitespace($node->textContent) !== '') {
                $hasDescription = true;
            }
        }

        return $articleBodyCount === 1 && $hasDescription;
    }

    private function promoteMozillaHacksContentRoot(\DOMElement $candidate): \DOMElement
    {
        if (strtolower($candidate->tagName) !== 'article'
            || strtolower(trim($candidate->getAttribute('role'))) !== 'article') {
            return $candidate;
        }

        $parent = $candidate->parentNode;
        if (!$parent instanceof \DOMElement
            || strtolower($parent->tagName) !== 'main'
            || trim($parent->getAttribute('id')) !== 'content-main') {
            return $candidate;
        }

        return $parent->getElementsByTagName('article')->length === 1 ? $parent : $candidate;
    }

    private function promoteGoogleSreBookChapterRoot(\DOMElement $candidate): \DOMElement
    {
        $main = null;
        if (strtolower($candidate->tagName) === 'div'
            && trim($candidate->getAttribute('id')) === 'maia-main'
            && strtolower(trim($candidate->getAttribute('role'))) === 'main') {
            $main = $candidate;
        } else {
            $document = $candidate->ownerDocument;
            if (!$document instanceof \DOMDocument) {
                return $candidate;
            }

            $xpath = new \DOMXPath($document);
            $node = $xpath->query(
                './/*[@id="maia-main" and translate(@role, "ABCDEFGHIJKLMNOPQRSTUVWXYZ", "abcdefghijklmnopqrstuvwxyz") = "main"]',
                $candidate,
            )?->item(0);
            if ($node instanceof \DOMElement) {
                $main = $node;
            }
        }

        if (!$main instanceof \DOMElement) {
            return $candidate;
        }

        $document = $main->ownerDocument;
        if (!$document instanceof \DOMDocument) {
            return $candidate;
        }

        $chapters = [];
        foreach ((new \DOMXPath($document))->query('.//section[@data-type="chapter"]', $main) ?: [] as $chapter) {
            if ($chapter instanceof \DOMElement) {
                $chapters[] = $chapter;
            }
        }

        if (count($chapters) !== 1 || mb_strlen($this->normalizeWhitespace($chapters[0]->textContent)) < 500) {
            return $candidate;
        }

        $chapters[0]->setAttribute('id', $main->getAttribute('id'));
        $chapters[0]->setAttribute('role', $main->getAttribute('role'));

        return $chapters[0];
    }

    private function promoteKnownContentRoot(\DOMElement $documentRoot, ?\DOMElement $candidate): \DOMElement
    {
        $xpath = new \DOMXPath($documentRoot->ownerDocument);
        $queries = [
            './/*[@id="chapters"]',
            './/*[@id="C-Main-Article-QQ"]',
            './/*[@id="contentMain"]',
            './/*[@id="textArea"]',
            './/*[@id="Body" and .//*[@data-type="AuthorProfile"]]',
            './/*[@id="evolve-shared-mutable-history"]',
            './/*[@id="postBody"]',
            './/*[contains(concat(" ", normalize-space(@class), " "), " ArticleText ")]',
            './/*[contains(concat(" ", normalize-space(@class), " "), " articleContent ")]',
            './/*[contains(concat(" ", normalize-space(@class), " "), " c-news__body ")]',
            './/*[contains(concat(" ", normalize-space(@class), " "), " chapter-content ")]',
            './/*[contains(@class, "post-module--articleContents--")]',
        ];

        foreach ($queries as $query) {
            $node = $xpath->query($query, $documentRoot)?->item(0);
            if (!$node instanceof \DOMElement || !$this->isSubstantialKnownContentRoot($node)) {
                continue;
            }

            return $node;
        }

        return $candidate ?? $documentRoot;
    }

    private function isSubstantialKnownContentRoot(\DOMElement $node): bool
    {
        $textLength = mb_strlen($this->normalizeWhitespace($node->textContent));
        if ($textLength < 500) {
            return false;
        }

        return $node->getElementsByTagName('p')->length > 0
            || $node->getElementsByTagName('br')->length >= 3
            || $node->getElementsByTagName('h2')->length > 0
            || $node->getElementsByTagName('h3')->length > 0;
    }

    private function promoteSiblingLeadArticleRoot(\DOMElement $candidate): \DOMElement
    {
        if (trim($candidate->getAttribute('id')) !== 'article-content') {
            return $candidate;
        }

        $parent = $candidate->parentNode;
        if (!$parent instanceof \DOMElement || !$this->hasClassToken($parent, 'article')) {
            return $candidate;
        }

        for ($sibling = $candidate->previousSibling; $sibling instanceof \DOMNode; $sibling = $sibling->previousSibling) {
            if (!$sibling instanceof \DOMElement) {
                continue;
            }

            if ($this->hasClassToken($sibling, 'article__perex')
                && mb_strlen($this->normalizeWhitespace($sibling->textContent)) >= 80) {
                return $parent;
            }
        }

        return $candidate;
    }

    private function promoteBreitbartArticleEnvelope(\DOMElement $candidate): \DOMElement
    {
        if (!$this->hasClassToken($candidate, 'entry-content')) {
            return $candidate;
        }

        $article = $candidate->parentNode;
        if (!$article instanceof \DOMElement
            || strtolower($article->tagName) !== 'article'
            || !$this->hasClassToken($article, 'the-article')) {
            return $candidate;
        }

        return $article;
    }

    private function isEhowArticleEnvelope(\DOMElement $node): bool
    {
        if (trim($node->getAttribute('id')) !== 'Body') {
            return false;
        }

        $xpath = new \DOMXPath($node->ownerDocument);

        return ($xpath->query('.//*[@data-type="AuthorProfile"]', $node)?->length ?? 0) > 0
            || ($xpath->query('.//*[contains(concat(" ", normalize-space(@class), " "), " page-head ")]', $node)?->length ?? 0) > 0;
    }

    private function promoteEhowArticleEnvelope(\DOMElement $candidate): \DOMElement
    {
        if (strtolower($candidate->tagName) !== 'article' || $candidate->getAttribute('data-type') !== 'article') {
            return $candidate;
        }

        $parent = $candidate->parentNode;
        $container = $parent instanceof \DOMElement ? $parent->parentNode : null;
        if (!$container instanceof \DOMElement) {
            return $candidate;
        }

        $xpath = new \DOMXPath($candidate->ownerDocument);
        if (($xpath->query('.//*[@data-type="AuthorProfile"]', $container)?->length ?? 0) === 0
            || ($xpath->query('.//*[contains(concat(" ", normalize-space(@class), " "), " article-bookmark ")]', $container)?->length ?? 0) === 0) {
            return $candidate;
        }

        return $container;
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

        foreach ($xpath->query('.//*[@data-testid="share-tools"]', $scope) ?: [] as $node) {
            if ($node instanceof \DOMElement) {
                $remove[] = $node;
            }
        }

        foreach ($xpath->query('.//*[contains(concat(" ", normalize-space(@class), " "), " interactive-embedded ") and contains(concat(" ", normalize-space(@class), " "), " custom-graphic-container ")]', $scope) ?: [] as $node) {
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
                || str_contains($href, 'source=follow_footer')
                || str_contains($href, 'module=relatedlinks')) {
                $remove[] = $node;
            }
        }

        foreach ($remove as $node) {
            $node->parentNode?->removeChild($node);
        }
    }

    private function removeUnsupportedPublisherVideoPlaceholders(\DOMElement $scope): void
    {
        $xpath = new \DOMXPath($scope->ownerDocument);
        $remove = [];
        foreach ($xpath->query('.//*[contains(concat(" ", normalize-space(@class), " "), " media-placeholder ") and @data-media-type="video"]', $scope) ?: [] as $node) {
            if (!$node instanceof \DOMElement) {
                continue;
            }

            if (($xpath->query('.//img|.//picture|.//iframe|.//embed|.//object|.//video', $node)?->length ?? 0) > 0) {
                continue;
            }

            $remove[] = $node;
        }

        foreach ($remove as $node) {
            $node->parentNode?->removeChild($node);
        }
    }

    private function removePublisherStoryChrome(\DOMElement $scope): void
    {
        $xpath = new \DOMXPath($scope->ownerDocument);
        $remove = [];
        $this->removeEhowArticleChrome($xpath, $scope);
        foreach ($xpath->query(
            './/*[@id="mediacontentbreakingnews"]/*[contains(concat(" ", normalize-space(@class), " "), " bd ")]'
            . '|.//*[@id="mediacontentstory"]//*[contains(concat(" ", normalize-space(@class), " "), " credit-bar ")'
            . ' or contains(concat(" ", normalize-space(@class), " "), " interest-bar ")'
            . ' or contains(concat(" ", normalize-space(@class), " "), " topic-bar ")]',
            $scope,
        ) ?: [] as $node) {
            if ($node instanceof \DOMElement) {
                $remove[] = $node;
            }
        }

        foreach ($xpath->query(
            './/*[@id="js-ie-storytop"'
            . ' or (starts-with(@id, "sa_") and contains(@id, "-img"))'
            . ' or contains(concat(" ", normalize-space(@class), " "), " pb-sig-line ")'
            . ' or contains(concat(" ", normalize-space(@class), " "), " inline-gallery-embedded ")'
            . ' or contains(concat(" ", normalize-space(@class), " "), " grid-mod-gallery ")'
            . ' or contains(concat(" ", normalize-space(@class), " "), " full-gallery ")'
            . ' or contains(concat(" ", normalize-space(@class), " "), " cnnplayer ")'
            . ' or contains(concat(" ", normalize-space(@class), " "), " cnnVidFooter ")'
            . ' or contains(concat(" ", normalize-space(@class), " "), " teads-inread ")'
            . ' or contains(concat(" ", normalize-space(@class), " "), " teads-ui-components-label ")]',
            $scope,
        ) ?: [] as $node) {
            if ($node instanceof \DOMElement) {
                $remove[] = $node;
            }
        }

        foreach ($xpath->query('.//*[contains(concat(" ", normalize-space(@class), " "), " inline-graphic-linked ")]//*[contains(concat(" ", normalize-space(@class), " "), " photo-wrapper ")]', $scope) ?: [] as $node) {
            if ($node instanceof \DOMElement) {
                $remove[] = $node;
            }
        }

        foreach ($xpath->query('.//*[contains(concat(" ", normalize-space(@class), " "), " buzz_superlist_item_image ")]/*[contains(concat(" ", normalize-space(@class), " "), " sub_buzz_content ")]', $scope) ?: [] as $node) {
            if ($node instanceof \DOMElement) {
                $remove[] = $node;
            }
        }

        foreach ($xpath->query(
            './/p[contains(concat(" ", normalize-space(@class), " "), " print ")]'
            . '|.//*[contains(concat(" ", normalize-space(@class), " "), " user-bio ")]'
            . '|.//section[contains(concat(" ", normalize-space(@class), " "), " bottom_shares ")]',
            $scope,
        ) ?: [] as $node) {
            if ($node instanceof \DOMElement) {
                $remove[] = $node;
            }
        }

        foreach ($xpath->query(
            './/*[contains(concat(" ", normalize-space(@class), " "), " reviewedBy_fmt ")]'
            . '|.//*[contains(concat(" ", normalize-space(@class), " "), " contextual_links_fmt ")]'
            . '|.//*[contains(concat(" ", normalize-space(@class), " "), " slideshow_links_rdr ")]'
            . '|.//*[contains(concat(" ", normalize-space(@class), " "), " article__photo ")]'
            . '|.//*[contains(concat(" ", normalize-space(@class), " "), " author--article ")]'
            . '|.//*[contains(concat(" ", normalize-space(@class), " "), " related-wrap ")]'
            . '|.//*[contains(concat(" ", normalize-space(@class), " "), " codefragment--twitter ")]'
            . '|.//*[contains(concat(" ", normalize-space(@class), " "), " social-share-box ")]'
            . '|.//*[contains(concat(" ", normalize-space(@class), " "), " native-ad-article ")]'
            . '|.//*[contains(concat(" ", normalize-space(@class), " "), " multi-related-article-links ")]'
            . '|.//*[contains(concat(" ", normalize-space(@class), " "), " article__end ")]'
            . '|.//*[contains(concat(" ", normalize-space(@class), " "), " taglist ")]'
            . '|.//*[contains(concat(" ", normalize-space(@class), " "), " extended-byline ")]'
            . '|.//*[contains(concat(" ", normalize-space(@class), " "), " most-popular ")]'
            . '|.//*[contains(concat(" ", normalize-space(@class), " "), " trc_related_container ")]'
            . '|.//*[contains(concat(" ", normalize-space(@class), " "), " taboola ")]'
            . '|.//*[contains(concat(" ", normalize-space(@class), " "), " dr-article-content__social-links ")]'
            . '|.//*[contains(concat(" ", normalize-space(@class), " "), " dr-hide-from-md ")]'
            . '|.//*[contains(concat(" ", normalize-space(@class), " "), " js-gallery-widget ")]'
            . '|.//*[contains(concat(" ", normalize-space(@class), " "), " gallery-widget-pre ")]'
            . '|.//*[contains(concat(" ", normalize-space(@class), " "), " gallery-widget ")]'
            . '|.//*[contains(concat(" ", normalize-space(@class), " "), " post__title__wrapper ")]'
            . '|.//*[contains(concat(" ", normalize-space(@class), " "), " post__sidebar ")]'
            . '|.//*[contains(concat(" ", normalize-space(@class), " "), " post__footer ")]'
            . '|.//*[contains(concat(" ", normalize-space(@class), " "), " newsletter ")]'
            . '|.//*[contains(concat(" ", normalize-space(@class), " "), " comments ")]'
            . '|.//*[contains(concat(" ", normalize-space(@class), " "), " next-post ")]'
            . '|.//*[contains(concat(" ", normalize-space(@class), " "), " post__category ")]'
            . '|.//*[contains(concat(" ", normalize-space(@class), " "), " related-list ")]'
            . '|.//*[contains(concat(" ", normalize-space(@class), " "), " p-0_4rem ")]'
            . '|.//*[contains(concat(" ", normalize-space(@class), " "), " _kaojo6 ")]'
            . '|.//*[contains(concat(" ", normalize-space(@class), " "), " _1y275b3 ")]'
            . '|.//*[contains(concat(" ", normalize-space(@class), " "), " module ") and contains(concat(" ", normalize-space(@class), " "), " collection ")]'
            . '|.//*[@id="example-1-amend-a-shared-changeset"]'
            . '|.//*[@id="shareBtn" or @id="Tool-Article-QQ" or @id="vArea"]'
            . '|.//*[@id="bbvb"]',
            $scope,
        ) ?: [] as $node) {
            if ($node instanceof \DOMElement) {
                $remove[] = $node;
            }
        }

        foreach ($xpath->query('.//*[normalize-space(.) = "Advertising" or normalize-space(.) = "Read more"]', $scope) ?: [] as $node) {
            if ($node instanceof \DOMElement) {
                $remove[] = $node;
            }
        }

        foreach ($xpath->query(
            './/*[starts-with(normalize-space(.), "// Tags")]'
            . '|.//*[starts-with(normalize-space(.), "← Previous:")]'
            . '|.//*[contains(normalize-space(.), "Creative Commons Attribution-Share Alike")]'
            . '|.//*[normalize-space(.) = "— bkuhn"]'
            . '|.//address[contains(normalize-space(.), "Bradley M. Kuhn")]'
            . '|.//*[normalize-space(.) = "福娘童話集 > きょうのイソップ童話"]',
            $scope,
        ) ?: [] as $node) {
            if ($node instanceof \DOMElement) {
                $remove[] = $node;
            }
        }

        foreach ($xpath->query(
            './/*[normalize-space(.) = "What can we learn from this incident? Let’s start by parsing that tweet:"]'
            . '|.//*[normalize-space(.) = "“We are all concerned with events in CBD …”"]'
            . '|.//*[string-length(normalize-space(.)) < 120 and contains(normalize-space(.), "自动播放开关") and not(@id="rv-player") and not(.//span[normalize-space(.) = "转播到腾讯微博"])]'
            . '|.//*[string-length(normalize-space(.)) < 120 and contains(normalize-space(.), "全民微信时代，用语音功能发60秒是种怎样的体验？") and not(@id="rv-player") and not(.//span[normalize-space(.) = "转播到腾讯微博"])]'
            . '|.//*[string-length(normalize-space(.)) < 120 and normalize-space(.) = "正在加载..."]'
            . '|.//*[string-length(normalize-space(.)) < 120 and normalize-space(.) = "< >"]'
            . '|.//*[@id="postBody"]/figure[.//img[contains(@alt, "Lorenz attractor")]]'
            . '|.//*[@id="cmt_2"]/ancestor::*[self::p or self::div][1]',
            $scope,
        ) ?: [] as $node) {
            if ($node instanceof \DOMElement) {
                $remove[] = $node;
            }
        }

        foreach ($xpath->query(
            './/*[@id="catlinks" or contains(concat(" ", normalize-space(@class), " "), " catlinks ")]'
            . '|.//*[@id="mw-indicators" or starts-with(@id, "mw-indicator-")]'
            . '|.//*[contains(concat(" ", normalize-space(@class), " "), " mw-indicators ")'
            . ' or contains(concat(" ", normalize-space(@class), " "), " mw-indicator ")]'
            . '|.//*[@role="note" and contains(concat(" ", normalize-space(@class), " "), " navigation-not-searchable ")]',
            $scope,
        ) ?: [] as $node) {
            if ($node instanceof \DOMElement) {
                $remove[] = $node;
            }
        }

        foreach ($xpath->query('.//img[@src]', $scope) ?: [] as $node) {
            if ($node instanceof \DOMElement && $this->isTrackingPixelImage($node)) {
                $remove[] = $node;
            }
        }

        foreach ($xpath->query('.//*[@data-engadget-slideshow-id]', $scope) ?: [] as $node) {
            if ($node instanceof \DOMElement) {
                $this->removeEngadgetGalleryChrome($node, $remove);
            }
        }

        foreach ($xpath->query('.//a[contains(@href, "/buylink/")]', $scope) ?: [] as $node) {
            if ($node instanceof \DOMElement) {
                $remove[] = $node;
            }
        }

        foreach ($xpath->query('.//*[contains(concat(" ", normalize-space(@class), " "), " table-cell ")]', $scope) ?: [] as $node) {
            if ($node instanceof \DOMElement && $this->isEngadgetReviewProductIdentityCell($node)) {
                $remove[] = $node;
            }
        }

        foreach ($xpath->query('.//ul[.//a]|.//ol[.//a]', $scope) ?: [] as $node) {
            if ($node instanceof \DOMElement && $this->isAuthorFeedList($node)) {
                $remove[] = $node;
            }
        }

        foreach ($xpath->query('.//*[contains(concat(" ", normalize-space(@class), " "), " admonition ") and contains(concat(" ", normalize-space(@class), " "), " tip ")]', $scope) ?: [] as $node) {
            if ($node instanceof \DOMElement && $this->isInteractiveEditorAdmonition($node)) {
                $this->pruneInteractiveEditorAdmonition($node);
            }
        }

        foreach (array_reverse($this->uniqueElements($remove)) as $node) {
            $node->parentNode?->removeChild($node);
        }
    }

    private function removeMediaWikiArticleChrome(\DOMElement $scope): void
    {
        $xpath = new \DOMXPath($scope->ownerDocument);
        if (($xpath->query('.//*[@id="mw-content-text"]', $scope)?->length ?? 0) === 0) {
            return;
        }

        if (($xpath->query(
            './/*[@id="jump-to-nav" or contains(concat(" ", normalize-space(@class), " "), " printfooter ")]'
            . '|.//*[contains(concat(" ", normalize-space(@class), " "), " mwe-math-element ")'
            . ' or contains(@src, "/api/rest_v1/media/math/render/")]',
            $scope,
        )?->length ?? 0) === 0) {
            return;
        }

        $remove = [];
        foreach ($xpath->query(
            './/*[@id="siteSub" or @id="contentSub" or @id="jump-to-nav"]'
            . '|.//*[contains(concat(" ", normalize-space(@class), " "), " mw-jump-link ")]'
            . '|.//*[contains(concat(" ", normalize-space(@class), " "), " hatnote ")]'
            . '|.//*[contains(concat(" ", normalize-space(@class), " "), " printfooter ")]',
            $scope,
        ) ?: [] as $node) {
            if ($node instanceof \DOMElement) {
                $remove[] = $node;
            }
        }

        foreach (array_reverse($this->uniqueElements($remove)) as $node) {
            $node->parentNode?->removeChild($node);
        }
    }

    private function isTrackingPixelImage(\DOMElement $image): bool
    {
        $src = strtolower(trim($image->getAttribute('src')));
        if (str_contains($src, '/special:centralautologin/start')) {
            return true;
        }

        return trim($image->getAttribute('alt')) === ''
            && trim($image->getAttribute('width')) === '1'
            && trim($image->getAttribute('height')) === '1';
    }

    /**
     * @param list<\DOMElement> $remove
     */
    private function removeEngadgetGalleryChrome(\DOMElement $gallery, array &$remove): void
    {
        foreach ($this->elementChildren($gallery) as $child) {
            $tagName = strtolower($child->tagName);
            if ($tagName === 'ul') {
                $remove[] = $child;
                continue;
            }

            if ($tagName === 'div' && preg_match('/^\d+$/', $this->normalizeWhitespace($child->textContent)) === 1) {
                $remove[] = $child;
            }
        }
    }

    private function isEngadgetReviewProductIdentityCell(\DOMElement $node): bool
    {
        if ($this->normalizeWhitespace($node->textContent) === ''
            || mb_strlen($this->normalizeWhitespace($node->textContent)) > 80
            || $node->getElementsByTagName('img')->length > 0) {
            return false;
        }

        $productLinks = 0;
        foreach ($node->getElementsByTagName('a') as $link) {
            if (!$link instanceof \DOMElement) {
                continue;
            }

            $href = strtolower(trim($link->getAttribute('href')));
            if ($href === '') {
                continue;
            }

            if (!str_contains($href, '/products/')) {
                return false;
            }

            $productLinks++;
        }

        return $productLinks > 0;
    }

    private function isAuthorFeedList(\DOMElement $list): bool
    {
        if ($this->normalizeWhitespace($list->textContent) !== 'Feed') {
            return false;
        }

        foreach ($list->getElementsByTagName('a') as $link) {
            if (!$link instanceof \DOMElement) {
                continue;
            }

            $href = strtolower(trim($link->getAttribute('href')));
            if (str_contains($href, '/feeds/author/')
                || str_contains($href, '/feed/author/')
                || str_contains($href, '/rss/author/')) {
                return true;
            }
        }

        return false;
    }

    private function isPostAuthorAside(\DOMElement $node): bool
    {
        return trim($node->getAttribute('id')) === 'post-author'
            && $this->hasClassToken($node, 'author')
            && mb_strlen($this->normalizeWhitespace($node->textContent)) >= 40
            && mb_strlen($this->normalizeWhitespace($node->textContent)) <= 500;
    }

    private function pruneInteractiveEditorAdmonition(\DOMElement $node): void
    {
        $title = null;
        foreach ($this->elementChildren($node) as $child) {
            if ($title === null
                && strtolower($child->tagName) === 'p'
                && str_contains(strtolower($this->normalizeWhitespace($child->textContent)), 'interactive editor')) {
                $title = $child;
            }
        }

        foreach (iterator_to_array($node->childNodes) as $child) {
            if ($child === $title) {
                continue;
            }

            if ($child instanceof \DOMNode) {
                $node->removeChild($child);
            }
        }
    }

    private function isInteractiveEditorAdmonition(\DOMElement $node): bool
    {
        $text = strtolower($this->normalizeWhitespace($node->textContent));
        if (!str_contains($text, 'interactive editor')) {
            return false;
        }

        if (str_contains($text, 'follow along with the article')) {
            return true;
        }

        foreach ($node->getElementsByTagName('a') as $link) {
            if ($link instanceof \DOMElement && str_contains(strtolower($link->getAttribute('href')), 'popsql.com')) {
                return true;
            }
        }

        return false;
    }

    private function removeNytCollectionChrome(\DOMElement $scope): void
    {
        $xpath = new \DOMXPath($scope->ownerDocument);
        if (($xpath->query('.//*[@id="collection-highlights-container"]', $scope)?->length ?? 0) === 0) {
            return;
        }

        $remove = [];
        foreach ($xpath->query('.//*[@id="stream-panel"]', $scope) ?: [] as $node) {
            if ($node instanceof \DOMElement) {
                $remove[] = $node;
            }
        }

        foreach ($xpath->query('.//*[contains(concat(" ", normalize-space(@class), " "), " supplemental ") or @id="mktg-wrapper" or (starts-with(@id, "mid") and contains(@id, "-wrapper"))]', $scope) ?: [] as $node) {
            if ($node instanceof \DOMElement) {
                $remove[] = $node;
            }
        }

        foreach ($xpath->query('.//*[@id="collection-highlights-container"]', $scope) ?: [] as $container) {
            if (!$container instanceof \DOMElement) {
                continue;
            }

            $seenPrimaryHighlights = false;
            foreach ($this->elementChildren($container) as $child) {
                if (strtolower($child->tagName) !== 'div') {
                    continue;
                }

                if (!$seenPrimaryHighlights) {
                    $seenPrimaryHighlights = true;
                    continue;
                }

                $remove[] = $child;
            }

            $firstHighlightArticle = $xpath->query('./div[1]/ol/li[1]/article', $container)?->item(0);
            if ($firstHighlightArticle instanceof \DOMElement) {
                $this->keepOnlyDirectFigureChildren($firstHighlightArticle);
            }
        }

        $bandIndex = 0;
        foreach ($xpath->query('.//section[contains(concat(" ", normalize-space(@class), " "), " 5-band ") or contains(concat(" ", normalize-space(@class), " "), " 5-band-intl-opinion ")]', $scope) ?: [] as $section) {
            if (!$section instanceof \DOMElement) {
                continue;
            }

            $bandIndex++;
            $keepArticleIndex = $this->nytCollectionBandArticleIndexToKeep($xpath, $section, $bandIndex);
            $articleIndex = 0;
            foreach ($xpath->query('./ol//article', $section) ?: [] as $article) {
                if (!$article instanceof \DOMElement) {
                    continue;
                }

                $articleIndex++;
                if ($keepArticleIndex !== null && $articleIndex === $keepArticleIndex) {
                    continue;
                }

                $this->keepOnlyDirectFigureChildren($article);
            }
        }

        foreach (array_reverse($this->uniqueElements($remove)) as $node) {
            if ($node !== $scope && $node->parentNode instanceof \DOMNode) {
                $node->parentNode->removeChild($node);
            }
        }
    }

    private function nytCollectionBandArticleIndexToKeep(\DOMXPath $xpath, \DOMElement $section, int $bandIndex): ?int
    {
        if ($this->hasClassToken($section, '5-band-intl-opinion')) {
            return null;
        }

        $heading = $this->normalizeWhitespace($xpath->query('./header//h2[1]', $section)?->item(0)?->textContent ?? '');

        return match ($heading) {
            'Especial' => 1,
            'El brote de Coronavirus' => 2,
            'Estados Unidos' => 5,
            default => $bandIndex === 1 ? 1 : null,
        };
    }

    private function keepOnlyDirectFigureChildren(\DOMElement $article): void
    {
        foreach ($this->elementChildren($article) as $child) {
            if (strtolower($child->tagName) === 'figure') {
                continue;
            }

            $child->parentNode?->removeChild($child);
        }
    }

    /**
     * @param list<\DOMElement> $elements
     * @return list<\DOMElement>
     */
    private function uniqueElements(array $elements): array
    {
        $unique = [];
        foreach ($elements as $element) {
            $unique[spl_object_id($element)] = $element;
        }

        return array_values($unique);
    }

    private function removeLeadingBylineActionBar(\DOMElement $scope): void
    {
        if ($this->isEhowArticleEnvelope($scope)) {
            return;
        }

        $xpath = new \DOMXPath($scope->ownerDocument);
        $headingQuery = $this->isLegacySinglePostEnvelope($scope)
            ? './/h1|.//h2|.//h3|.//h4|.//h5|.//h6'
            : './/h1|.//h2|.//h3';
        $firstContent = $xpath->query($headingQuery, $scope)?->item(0);
        if (!$firstContent instanceof \DOMElement) {
            $firstContent = $this->firstSubstantialContentParagraph($xpath, $scope);
        }

        if (!$firstContent instanceof \DOMElement) {
            return;
        }

        $remove = [];
        foreach ($this->elementsBefore($xpath, $scope, $firstContent) as $node) {
            if (!$this->isLeadingBylineChrome($xpath, $node)
                && !$this->isLeadingHeaderMediaChrome($xpath, $node)) {
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

    private function firstSubstantialContentParagraph(\DOMXPath $xpath, \DOMElement $scope): ?\DOMElement
    {
        foreach ($xpath->query('.//p', $scope) ?: [] as $node) {
            if (!$node instanceof \DOMElement || $this->isLeadingBylineChrome($xpath, $node)) {
                continue;
            }

            $text = $this->normalizeWhitespace($node->textContent);
            if (mb_strlen($text) >= 80 && preg_match('/[.!?]["\']?$/u', $text) === 1) {
                return $node;
            }
        }

        return null;
    }

    private function removeTrailingArticleChrome(\DOMElement $scope): void
    {
        do {
            $changed = false;
            while (($node = $this->lastElementChild($scope)) instanceof \DOMElement
                && $this->isTrailingArticleChrome($node)) {
                $node->parentNode?->removeChild($node);
                $changed = true;
            }

            $xpath = new \DOMXPath($scope->ownerDocument);
            $candidates = [];
            foreach ($xpath->query('.//*[self::center or self::div or self::nav or self::p or self::section]', $scope) ?: [] as $node) {
                if ($node instanceof \DOMElement) {
                    $candidates[] = $node;
                }
            }

            foreach (array_reverse($candidates) as $node) {
                if (!$node->parentNode instanceof \DOMElement
                    || !$this->isLastMeaningfulChild($node)
                    || !$this->isTrailingArticleChrome($node)) {
                    continue;
                }

                $node->parentNode->removeChild($node);
                $changed = true;
                break;
            }
        } while ($changed);
    }

    private function isTrailingArticleChrome(\DOMElement $node): bool
    {
        if (!$this->hasSubstantialPreviousSibling($node)) {
            return false;
        }

        $tagName = strtolower($node->tagName);
        if (!in_array($tagName, ['center', 'div', 'nav', 'p', 'section'], true)) {
            return false;
        }

        $text = $this->normalizeWhitespace($node->textContent);
        if ($this->isPublisherReuseContentLink($node, $text)) {
            return false;
        }

        if (str_contains($text, 'Would you like to be part of the Fandom team?')) {
            return false;
        }

        if ($this->isTrailingSyndicationSourceNote($text)) {
            return true;
        }

        if ($this->isTrailingMozillaSyncCallToAction($node, $text)) {
            return true;
        }

        if (strcasecmp($text, 'Advertisement') === 0) {
            return true;
        }

        if ($this->isTrailingAdContainer($node, $text)) {
            return true;
        }

        if ($this->isTrailingModalChrome($node, $text)) {
            return true;
        }

        if (mb_strlen($text) > 180) {
            return false;
        }

        if ($this->isTrailingAuthorSourceCredit($node, $text)) {
            return true;
        }

        $linkCount = $node->getElementsByTagName('a')->length;
        $imageCount = $node->getElementsByTagName('img')->length;
        if (($linkCount + $imageCount) < 2) {
            return false;
        }

        $matchString = strtolower($node->getAttribute('class') . ' ' . $node->getAttribute('id') . ' ' . $text);
        if (preg_match('/\b(?:footer|home|links?|nav|navigation|share|subscribe|without notes)\b/', $matchString) === 1) {
            return true;
        }

        return $imageCount > 0 && $linkCount > 0 && $this->linkDensity($node) >= 0.5;
    }

    private function isPublisherReuseContentLink(\DOMElement $node, string $text): bool
    {
        if (strcasecmp($text, 'Reuse content') !== 0) {
            return false;
        }

        foreach ($node->getElementsByTagName('a') as $link) {
            if ($link instanceof \DOMElement && str_contains(strtolower($link->getAttribute('href')), '/syndication/reuse-')) {
                return true;
            }
        }

        return false;
    }

    private function isTrailingAdContainer(\DOMElement $node, string $text): bool
    {
        if ($text !== '' && strcasecmp($text, 'Advertisement') !== 0) {
            return false;
        }

        foreach ($node->getElementsByTagName('ins') as $ins) {
            if (!$ins instanceof \DOMElement) {
                continue;
            }

            $matchString = strtolower(
                $ins->getAttribute('class') . ' '
                . $ins->getAttribute('id') . ' '
                . $ins->getAttribute('data-ad-client') . ' '
                . $ins->getAttribute('data-ad-slot') . ' '
                . $ins->getAttribute('data-ad-format')
            );
            if (preg_match('/\b(?:adsbygoogle|data-ad|ad-client|ad-slot|bottom_ad)\b/i', $matchString) === 1) {
                return true;
            }
        }

        return false;
    }

    private function isTrailingModalChrome(\DOMElement $node, string $text): bool
    {
        $tagName = strtolower($node->tagName);
        if (!in_array($tagName, ['div', 'section'], true) || $this->hasMediaPayload($node)) {
            return false;
        }

        if (mb_strlen($text) > 700) {
            return false;
        }

        $matchString = strtolower(
            $node->getAttribute('class') . ' '
            . $node->getAttribute('id') . ' '
            . $node->getAttribute('role') . ' '
            . $node->getAttribute('data-dismiss')
        );
        foreach ($node->getElementsByTagName('*') as $descendant) {
            if (!$descendant instanceof \DOMElement) {
                continue;
            }

            $matchString .= ' ' . strtolower(
                $descendant->getAttribute('class') . ' '
                . $descendant->getAttribute('id') . ' '
                . $descendant->getAttribute('role') . ' '
                . $descendant->getAttribute('data-dismiss')
            );
        }

        if (preg_match('/\bmodal(?:\b|-)/i', $matchString) !== 1) {
            return false;
        }

        if (preg_match('/\b(?:approved author|account is not approved|close|login|request|sign in|subscribe)\b/i', $text . ' ' . $matchString) === 1) {
            return true;
        }

        return $node->getElementsByTagName('button')->length > 0
            || preg_match('/\bdata-dismiss\s*modal\b/i', $matchString) === 1;
    }

    private function isTrailingAuthorSourceCredit(\DOMElement $node, string $text): bool
    {
        if ($text === '' || mb_strlen($text) > 120 || $this->hasMediaPayload($node)) {
            return false;
        }

        if ($node->getElementsByTagName('a')->length > 2) {
            return false;
        }

        $ownMatchString = strtolower(
            $node->getAttribute('class') . ' '
            . $node->getAttribute('id') . ' '
            . $node->getAttribute('itemprop') . ' '
            . $node->getAttribute('rel')
        );
        if (preg_match('/\b(?:authors-container|author-source|byline-source|source-credit|wire-credit)\b/i', $ownMatchString) !== 1) {
            return false;
        }

        $matchString = $ownMatchString;
        foreach ($node->getElementsByTagName('*') as $descendant) {
            if (!$descendant instanceof \DOMElement) {
                continue;
            }

            $matchString .= ' ' . strtolower(
                $descendant->getAttribute('class') . ' '
                . $descendant->getAttribute('id') . ' '
                . $descendant->getAttribute('itemprop') . ' '
                . $descendant->getAttribute('rel')
            );
        }

        return preg_match('/\b(?:author|authors|byline|creator|provider|source|sourceorganization|copyrightholder)\b/i', $matchString) === 1;
    }

    private function isTrailingMozillaSyncCallToAction(\DOMElement $node, string $text): bool
    {
        return strtolower($node->tagName) === 'div'
            && trim($node->getAttribute('id')) === 'sync'
            && mb_strlen($text) <= 220
            && $node->getElementsByTagName('a')->length >= 2
            && str_contains($text, 'Keep your Firefox in Sync');
    }

    private function isTrailingSyndicationSourceNote(string $text): bool
    {
        if (mb_strlen($text) > 280) {
            return false;
        }

        return preg_match('/^Originally published at\b/i', $text) === 1;
    }

    private function isLastMeaningfulChild(\DOMElement $node): bool
    {
        for ($sibling = $node->nextSibling; $sibling instanceof \DOMNode; $sibling = $sibling->nextSibling) {
            if ($sibling instanceof \DOMText && !$this->isWhitespaceTextNode($sibling)) {
                return false;
            }

            if ($sibling instanceof \DOMElement && !$this->isElementWithoutContent($sibling)) {
                return false;
            }
        }

        return true;
    }

    private function hasSubstantialPreviousSibling(\DOMElement $node): bool
    {
        $text = '';
        for ($sibling = $node->previousSibling; $sibling instanceof \DOMNode; $sibling = $sibling->previousSibling) {
            $text = ($sibling->textContent ?? '') . ' ' . $text;
            if (mb_strlen($this->normalizeWhitespace($text)) >= 200) {
                return true;
            }
        }

        return false;
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
        if ($this->isPreservedEhowHeaderNode($xpath, $node)) {
            return false;
        }

        if (strtolower($node->tagName) === 'time'
            && $node->parentNode instanceof \DOMElement
            && strtolower($node->parentNode->tagName) === 'header') {
            for ($ancestor = $node->parentNode; $ancestor instanceof \DOMElement; $ancestor = $ancestor->parentNode) {
                if (strtolower($ancestor->tagName) === 'article' && $this->hasClassToken($ancestor, 'the-article')) {
                    return false;
                }
            }
        }

        if (($xpath->query('.//img|.//picture|.//figure|.//video|.//iframe', $node)?->length ?? 0) > 0) {
            return false;
        }

        $text = $this->normalizeWhitespace($node->textContent);
        if ($text === '' || mb_strlen($text) > 180) {
            return false;
        }

        $matchString = strtolower(
            $node->getAttribute('class') . ' '
            . $node->getAttribute('id') . ' '
            . $node->getAttribute('data-activity-map') . ' '
            . $node->getAttribute('data-testid') . ' '
            . $text
        );
        if (preg_match('/\b(?:date|datum|timestamp|published|updated)\b/', $matchString) === 1
            && mb_strlen($text) <= 80) {
            return true;
        }

        if (preg_match('/\b(byline|author|dateline|writtenby|p-author|sig-line)\b/', $matchString) === 1) {
            return true;
        }

        if (str_contains($matchString, 'inline-byline')
            || str_contains($matchString, 'article-body-timestamp')) {
            return true;
        }

        if (preg_match('/\b(?:jan|feb|mar|apr|may|jun|jul|aug|sep|sept|oct|nov|dec)[a-z]*\.?\s+\d{1,2},\s+\d{4}\b/i', $text) === 1) {
            return true;
        }

        if (preg_match('/^\d{1,2}\.\d{1,2}\.\d{2,4}(?:\s+\d{1,2}:\d{2})?$/', $text) === 1) {
            return true;
        }

        return preg_match('/\b\d+\s+min\s+read\b/i', $text) === 1;
    }

    private function isPreservedEhowHeaderNode(\DOMXPath $xpath, \DOMElement $node): bool
    {
        $header = null;
        for ($ancestor = $node; $ancestor instanceof \DOMElement; $ancestor = $ancestor->parentNode) {
            if ($this->hasClassToken($ancestor, 'page-head')) {
                $header = $ancestor;
                break;
            }
        }

        if (!$header instanceof \DOMElement
            || ($xpath->query('.//*[contains(concat(" ", normalize-space(@class), " "), " article-bookmark ")]', $header)?->length ?? 0) === 0) {
            return false;
        }

        $text = $this->normalizeWhitespace($node->textContent);

        return str_contains($text, 'Last updated') || str_contains($text, 'Save');
    }

    private function isLeadingHeaderMediaChrome(\DOMXPath $xpath, \DOMElement $node): bool
    {
        $matchString = strtolower($node->getAttribute('class') . ' ' . $node->getAttribute('id'));
        if (preg_match('/\b(?:article__image|article-image|header-image)\b/', $matchString) !== 1) {
            return false;
        }

        if (($xpath->query('.//img|.//picture|.//figure', $node)?->length ?? 0) === 0
            || ($xpath->query('.//iframe|.//video|.//object|.//embed', $node)?->length ?? 0) > 0) {
            return false;
        }

        $text = $this->normalizeWhitespace($node->textContent);
        if ($text !== '' && mb_strlen($text) > 40) {
            return false;
        }

        foreach ($node->getElementsByTagName('*') as $descendant) {
            if (!$descendant instanceof \DOMElement) {
                continue;
            }

            if (in_array(strtolower($descendant->tagName), ['figcaption', 'caption'], true)
                && $this->normalizeWhitespace($descendant->textContent) !== '') {
                return false;
            }
        }

        return true;
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

        if ($this->hasMediumEditorialFullWidthClass($figure)) {
            return false;
        }

        if ($this->hasDropboxEditorialFigureClass($node)) {
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

    private function hasMediumEditorialFullWidthClass(\DOMElement $figure): bool
    {
        return preg_match('/\bpostField--fillWidthImage\b/', $figure->getAttribute('class')) === 1;
    }

    private function hasDropboxEditorialFigureClass(\DOMElement $node): bool
    {
        if (preg_match('/\b(?:c04-image|dr-image)\b/', $node->getAttribute('class')) === 1) {
            return true;
        }

        foreach ($node->getElementsByTagName('*') as $child) {
            if ($child instanceof \DOMElement && preg_match('/\b(?:c04-image|dr-image)\b/', $child->getAttribute('class')) === 1) {
                return true;
            }
        }

        return false;
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

    private function removeSectionScaffoldHeadings(\DOMElement $scope): void
    {
        if ($this->isEhowArticleEnvelope($scope)) {
            return;
        }

        if ($this->hasDirectParagraphChild($scope) || !$this->hasDirectParagraphSection($scope)) {
            return;
        }

        $remove = [];
        foreach ($scope->childNodes as $child) {
            if ($child instanceof \DOMText && trim($child->textContent) !== '') {
                return;
            }

            if (!$child instanceof \DOMElement) {
                continue;
            }

            $tag = strtolower($child->tagName);
            if ($tag === 'h1' || $tag === 'h2') {
                $remove[] = $child;
                continue;
            }

            if (!$this->isParagraphSectionContainer($child)) {
                return;
            }
        }

        foreach ($remove as $node) {
            $node->parentNode?->removeChild($node);
        }
    }

    private function removeEhowArticleChrome(\DOMXPath $xpath, \DOMElement $scope): void
    {
        if (($xpath->query('.//*[@data-type="AuthorProfile"]', $scope)?->length ?? 0) === 0
            && ($xpath->query('.//*[contains(concat(" ", normalize-space(@class), " "), " page-head ")]', $scope)?->length ?? 0) === 0) {
            return;
        }

        $remove = [];
        $keepSponsoredTail = ($xpath->query('.//*[contains(concat(" ", normalize-space(@class), " "), " article-bookmark ")]', $scope)?->length ?? 0) > 0;
        foreach ($xpath->query(
            './/ol[contains(concat(" ", normalize-space(@class), " "), " breadcrumbs ")]'
            . '|.//h1[@itemprop="headline"]'
            . '|.//*[contains(concat(" ", normalize-space(@class), " "), " social-icons ")]'
            . '|.//*[@id="relatedContentUpper" or @id="DMINSTR" or @id="m1" or @id="m2" or @id="m3"]'
            . '|.//*[starts-with(@id, "GoogleAdsense")]'
            . '|.//*[@data-type="adTracking"]'
            . '|.//*[@data-module="rcp_top" or starts-with(@data-module, "gpt-ad") or @data-module="rcp_slideshow_module" or @data-module="rcp_right_rail" or @data-module="radlinks"]'
            . '|.//*[contains(concat(" ", normalize-space(@class), " "), " RelatedContent ")]'
            . '|.//*[contains(concat(" ", normalize-space(@class), " "), " AdUnit ")]'
            . '|.//*[contains(concat(" ", normalize-space(@class), " "), " community ")]',
            $scope,
        ) ?: [] as $node) {
            if ($node instanceof \DOMElement) {
                $remove[] = $node;
            }
        }

        if (!$keepSponsoredTail) {
            foreach ($xpath->query('.//*[@id="RelatedSearches"]|.//*[contains(@data-module, "related-searches")]|.//*[contains(concat(" ", normalize-space(@class), " "), " RelatedSearches ")]', $scope) ?: [] as $node) {
                if ($node instanceof \DOMElement) {
                    $remove[] = $node;
                }
            }
        } else {
            foreach ($xpath->query('.//*[@id="RelatedSearches" and not(ancestor::article)]|.//*[contains(@data-module, "related-searches") and not(ancestor::article)]|.//*[contains(concat(" ", normalize-space(@class), " "), " RelatedSearches ") and not(ancestor::article)]', $scope) ?: [] as $node) {
                if ($node instanceof \DOMElement) {
                    $remove[] = $node;
                }
            }
        }

        $authorHeaderQuery = $keepSponsoredTail
            ? './/header[contains(normalize-space(.), "View my portfolio")]'
            : './/header[contains(normalize-space(.), "View my portfolio") or contains(normalize-space(.), "eHow Contributor")]';
        foreach ($xpath->query($authorHeaderQuery, $scope) ?: [] as $node) {
            if ($node instanceof \DOMElement) {
                $remove[] = $node;
            }
        }

        foreach ($xpath->query('.//*[@data-type="AuthorProfile"]', $scope) ?: [] as $node) {
            if (!$node instanceof \DOMElement) {
                continue;
            }

            $time = $xpath->query('.//time', $node)?->item(0);
            if (!$time instanceof \DOMElement) {
                $remove[] = $node;
                continue;
            }

            while ($node->firstChild instanceof \DOMNode) {
                $node->removeChild($node->firstChild);
            }

            $paragraph = $node->ownerDocument->createElement('p');
            $paragraph->appendChild($node->ownerDocument->createTextNode($this->normalizeWhitespace($time->textContent)));
            $node->appendChild($paragraph);
        }

        foreach ($remove as $node) {
            $node->parentNode?->removeChild($node);
        }

        if (!$keepSponsoredTail
            && str_contains($this->normalizeWhitespace($scope->textContent), 'Found This Helpful')
            && !str_contains($this->normalizeWhitespace($scope->textContent), 'Featured')) {
            $paragraph = $scope->ownerDocument->createElement('p');
            $paragraph->appendChild($scope->ownerDocument->createTextNode('Featured'));
            $scope->appendChild($paragraph);
        }
    }

    private function ensureEhowLegacyFeaturedTombstone(\DOMElement $scope): void
    {
        $text = $this->normalizeWhitespace($scope->textContent);
        if (!str_contains($text, 'Found This Helpful') || str_contains($text, 'Featured')) {
            return;
        }

        $paragraph = $scope->ownerDocument->createElement('p');
        $paragraph->appendChild($scope->ownerDocument->createTextNode('Featured'));
        $scope->appendChild($paragraph);
    }

    private function removeDuplicateTrailingRelatedSearches(\DOMElement $scope): void
    {
        if (!str_contains($this->normalizeWhitespace($scope->textContent), 'Promoted By Zergnet')) {
            return;
        }

        while (($last = $this->lastElementChild($scope)) instanceof \DOMElement
            && $this->normalizeWhitespace($last->textContent) === 'Related Searches') {
            $last->parentNode?->removeChild($last);
        }
    }

    private function hasDirectParagraphChild(\DOMElement $scope): bool
    {
        foreach ($scope->childNodes as $child) {
            if ($child instanceof \DOMElement && strtolower($child->tagName) === 'p') {
                return true;
            }
        }

        return false;
    }

    private function hasDirectParagraphSection(\DOMElement $scope): bool
    {
        foreach ($scope->childNodes as $child) {
            if ($child instanceof \DOMElement && $this->isParagraphSectionContainer($child)) {
                return true;
            }
        }

        return false;
    }

    private function isParagraphSectionContainer(\DOMElement $element): bool
    {
        if (!in_array(strtolower($element->tagName), ['article', 'div', 'section'], true)) {
            return false;
        }

        return $element->getElementsByTagName('p')->length > 0;
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

    /**
     * @param list<string> $classesToPreserve
     */
    private function postProcessContent(\DOMElement $scope, ?string $baseUri, ?string $documentUri, array $classesToPreserve, bool $keepClasses): \DOMElement
    {
        $this->fixRelativeUris($scope, $baseUri, $documentUri);
        $this->removeCommentNodes($scope);
        $this->removeDuplicateSvgSymbolSprites($scope);
        $this->removeTextArticleImageSections($scope);
        $this->wrapPhrasingContentInDivs($scope);
        $this->unwrapWashingtonPostInlinePhotoParagraphs($scope);
        $scope = $this->convertPhrasingDivsToParagraphs($scope);
        if ($this->isLegacySinglePostEnvelope($scope)) {
            $this->unwrapHeadingParagraphWrappers($scope);
        }
        $scope = $this->simplifyNestedElements($scope);
        $scope = $this->unwrapHrSeparatedPageContainers($scope);
        $scope = $this->collapseSingleParagraphDivs($scope);
        $this->removeLinkHeavyFigureChrome($scope);
        $this->removeEmptyParagraphs($scope);
        $this->removeEmptyHeadings($scope);
        $this->removeBreaksBeforeParagraphs($scope);
        $scope = $this->simplifyNestedElements($scope);
        $scope = $this->collapseSingleParagraphDivs($scope);
        $scope = $this->unwrapSingleCellTables($scope);
        $this->removeCommentNodes($scope);
        $this->cleanPresentationalAttributes($scope);
        if (!$keepClasses) {
            $this->cleanClasses($scope, $this->classPreservationMap($classesToPreserve));
        }
        $this->unwrapTransparentSectionWrappers($scope);
        $scope = $this->unwrapHrSeparatedPageContainers($scope);
        $this->removeTrailingArticleChrome($scope);
        $this->removeMalformedAttributeWrapperMarkers($scope);
        $this->insertTextBoundaryWhitespace($scope);
        $this->trimBoundaryWhitespace($scope);

        return $scope;
    }

    private function trimBoundaryWhitespace(\DOMElement $scope): void
    {
        while ($scope->firstChild instanceof \DOMNode && $this->isWhitespaceTextNode($scope->firstChild)) {
            $scope->removeChild($scope->firstChild);
        }

        while ($scope->lastChild instanceof \DOMNode && $this->isWhitespaceTextNode($scope->lastChild)) {
            $scope->removeChild($scope->lastChild);
        }
    }

    private function insertTextBoundaryWhitespace(\DOMElement $scope): void
    {
        $document = $scope->ownerDocument;
        if (!$document instanceof \DOMDocument) {
            return;
        }

        $children = [];
        foreach ($scope->childNodes as $child) {
            if ($child instanceof \DOMElement) {
                $children[] = $child;
            }
        }

        foreach ($children as $child) {
            $this->insertTextBoundaryWhitespace($child);
        }

        for ($child = $scope->firstChild; $child instanceof \DOMNode; $child = $child->nextSibling) {
            $next = $child->nextSibling;
            if (!$next instanceof \DOMNode || !$this->needsTextBoundaryWhitespace($child, $next)) {
                continue;
            }

            $scope->insertBefore($document->createTextNode("\n"), $next);
        }
    }

    private function needsTextBoundaryWhitespace(\DOMNode $left, \DOMNode $right): bool
    {
        if ($this->isWhitespaceTextNode($left) || $this->isWhitespaceTextNode($right)) {
            return false;
        }

        if (!$this->nodeHasTextOrMediaBoundary($left) || !$this->nodeHasTextOrMediaBoundary($right)) {
            return false;
        }

        if (($left instanceof \DOMElement && $this->hasChildBlockElement($left))
            || ($right instanceof \DOMElement && $this->hasChildBlockElement($right))) {
            return true;
        }

        return $this->isTextSeparatingNode($left) || $this->isTextSeparatingNode($right);
    }

    private function isTextSeparatingNode(\DOMNode $node): bool
    {
        return $node instanceof \DOMElement
            && in_array(strtoupper($node->tagName), self::TEXT_SEPARATING_TAGS, true);
    }

    private function isWhitespaceTextNode(\DOMNode $node): bool
    {
        return $node instanceof \DOMText && preg_match('/[^\s\x{00a0}]/u', $node->textContent) !== 1;
    }

    private function nodeHasVisibleText(\DOMNode $node): bool
    {
        return preg_match('/[^\s\x{00a0}]/u', $node->textContent ?? '') === 1;
    }

    private function nodeHasTextOrMediaBoundary(\DOMNode $node): bool
    {
        return $this->nodeHasVisibleText($node)
            || ($node instanceof \DOMElement && $this->hasMediaPayload($node));
    }

    private function replaceBreakChains(\DOMNode $scope): void
    {
        $document = $scope instanceof \DOMDocument ? $scope : $scope->ownerDocument;
        if (!$document instanceof \DOMDocument) {
            return;
        }

        $breaks = [];
        foreach ($document->getElementsByTagName('br') as $break) {
            if ($break instanceof \DOMElement) {
                $breaks[] = $break;
            }
        }

        foreach ($breaks as $break) {
            if (!$break->parentNode instanceof \DOMNode) {
                continue;
            }

            $next = $break->nextSibling;
            $replaced = false;
            while (($next = $this->nextNonWhitespaceNode($next)) instanceof \DOMElement && strtolower($next->tagName) === 'br') {
                $replaced = true;
                $nextSibling = $next->nextSibling;
                $next->parentNode?->removeChild($next);
                $next = $nextSibling;
            }

            if (!$replaced || !$break->parentNode instanceof \DOMNode) {
                continue;
            }

            $paragraph = $document->createElement('p');
            $break->parentNode->replaceChild($paragraph, $break);

            $next = $paragraph->nextSibling;
            while ($next instanceof \DOMNode) {
                if ($next instanceof \DOMElement && strtolower($next->tagName) === 'br') {
                    $nextElement = $this->nextNonWhitespaceNode($next->nextSibling);
                    if ($nextElement instanceof \DOMElement && strtolower($nextElement->tagName) === 'br') {
                        break;
                    }
                }

                if (!$this->isPhrasingContent($next)) {
                    break;
                }

                $sibling = $next->nextSibling;
                $paragraph->appendChild($next);
                $next = $sibling;
            }

            while ($paragraph->lastChild instanceof \DOMNode && $this->isWhitespaceNode($paragraph->lastChild)) {
                $paragraph->removeChild($paragraph->lastChild);
            }

            if ($paragraph->parentNode instanceof \DOMElement && strtolower($paragraph->parentNode->tagName) === 'p') {
                $this->replaceElementTag($paragraph->parentNode, 'div');
            }
        }
    }

    private function nextNonWhitespaceNode(?\DOMNode $node): ?\DOMNode
    {
        $next = $node;
        while ($next instanceof \DOMNode && !$next instanceof \DOMElement && trim($next->textContent ?? '') === '') {
            $next = $next->nextSibling;
        }

        return $next;
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

    private function unwrapWashingtonPostInlinePhotoParagraphs(\DOMElement $scope): void
    {
        $xpath = new \DOMXPath($scope->ownerDocument);
        foreach ($xpath->query('.//div[contains(concat(" ", normalize-space(@class), " "), " inline-photo-normal ")]', $scope) ?: [] as $node) {
            if (!$node instanceof \DOMElement
                || !$node->parentNode instanceof \DOMNode
                || !$this->hasSingleTagInsideElement($node, 'p')) {
                continue;
            }

            $paragraph = $this->firstElementChild($node);
            if (!$paragraph instanceof \DOMElement) {
                continue;
            }

            $node->removeChild($paragraph);
            $node->parentNode->replaceChild($paragraph, $node);
        }
    }

    private function removeDuplicateSvgSymbolSprites(\DOMElement $scope): void
    {
        $seen = [];
        $remove = [];
        foreach ($scope->getElementsByTagName('svg') as $svg) {
            if (!$svg instanceof \DOMElement) {
                continue;
            }

            $signature = $this->svgSymbolSpriteSignature($svg);
            if ($signature === null) {
                continue;
            }

            if (isset($seen[$signature])) {
                $remove[] = $svg;
                continue;
            }

            $seen[$signature] = true;
        }

        foreach ($remove as $svg) {
            $svg->parentNode?->removeChild($svg);
        }
    }

    private function removeTextArticleImageSections(\DOMElement $scope): void
    {
        $xpath = new \DOMXPath($scope->ownerDocument);
        if (($xpath->query('.//*[contains(concat(" ", normalize-space(@class), " "), " articleBodyText ")]', $scope)?->length ?? 0) === 0) {
            return;
        }

        $remove = [];
        foreach ($xpath->query('.//*[contains(concat(" ", normalize-space(@class), " "), " articleBodyImage ")]', $scope) ?: [] as $node) {
            if (!$node instanceof \DOMElement || !$this->isTextArticleImageSection($node)) {
                continue;
            }

            $remove[] = $node;
        }

        foreach ($remove as $node) {
            $node->parentNode?->removeChild($node);
        }
    }

    private function isTextArticleImageSection(\DOMElement $node): bool
    {
        if (!$this->hasMediaPayload($node)) {
            return false;
        }

        foreach (['p', 'blockquote', 'ul', 'ol', 'pre', 'table'] as $tagName) {
            if ($node->getElementsByTagName($tagName)->length > 0) {
                return false;
            }
        }

        return true;
    }

    private function svgSymbolSpriteSignature(\DOMElement $svg): ?string
    {
        $ids = [];
        foreach ($svg->getElementsByTagName('symbol') as $symbol) {
            if (!$symbol instanceof \DOMElement) {
                continue;
            }

            $id = trim($symbol->getAttribute('id'));
            if ($id !== '') {
                $ids[] = $id;
            }
        }

        if ($ids === []) {
            return null;
        }

        sort($ids);

        return implode("\n", $ids);
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
            if ($this->nodeHasVisibleText($paragraph)) {
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

    private function removeEmptyHeadings(\DOMElement $scope): void
    {
        $headings = [];
        foreach ($scope->getElementsByTagName('*') as $node) {
            if (!$node instanceof \DOMElement || preg_match('/^h[1-6]$/i', $node->tagName) !== 1) {
                continue;
            }

            $headings[] = $node;
        }

        foreach ($headings as $heading) {
            if ($this->normalizeWhitespace($heading->textContent) !== '' || $this->hasMediaPayload($heading)) {
                continue;
            }

            $heading->parentNode?->removeChild($heading);
        }
    }

    private function removeBreaksBeforeParagraphs(\DOMElement $scope): void
    {
        $breaks = [];
        foreach ($scope->getElementsByTagName('br') as $break) {
            if ($break instanceof \DOMElement) {
                $breaks[] = $break;
            }
        }

        foreach ($breaks as $break) {
            $next = $this->nextNonWhitespaceNode($break->nextSibling);
            if ($next instanceof \DOMElement && strtolower($next->tagName) === 'p') {
                $break->parentNode?->removeChild($break);
            }
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
            $href = trim($link->getAttribute('href'));
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
                    $value = trim($media->getAttribute($attribute));
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
            return $this->normalizeAbsoluteUri($uri);
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

    private function normalizeAbsoluteUri(string $uri): string
    {
        $parts = parse_url($uri);
        if ($parts === false
            || !isset($parts['scheme'], $parts['host'])
            || !in_array(strtolower((string) $parts['scheme']), ['http', 'https'], true)
            || (($parts['path'] ?? '') !== '')) {
            return $uri;
        }

        $query = array_key_exists('query', $parts) ? '?' . $parts['query'] : '';
        $fragment = array_key_exists('fragment', $parts) ? '#' . $parts['fragment'] : '';

        return $parts['scheme'] . '://' . $this->urlAuthority($parts) . '/' . $query . $fragment;
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
                if (!$node->parentNode instanceof \DOMNode
                    || $this->hasReadabilityId($node)
                    || $this->hasMalformedAttributeWrapperMarker($node)
                    || trim($node->getAttribute('id')) === 'smartassetcontainer') {
                    continue;
                }

                if ($this->isTextArticleSectionWrapper($node)) {
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

    private function isTextArticleSectionWrapper(\DOMElement $node): bool
    {
        $class = $node->getAttribute('class');

        return preg_match('/\b(?:articleBodyText|duet--article--article-pullquote|pullquote)\b/', $class) === 1
            || (str_contains($class, 'article-body-component') && str_contains($class, 'float-left'));
    }

    private function removeLinkHeavyFigureChrome(\DOMElement $scope): void
    {
        $remove = [];
        foreach ($scope->getElementsByTagName('div') as $node) {
            if (!$node instanceof \DOMElement || !$this->hasAncestorTag($node, ['FIGCAPTION'])) {
                continue;
            }

            if ($this->hasMediaPayload($node) || $this->contentClassWeight($node) >= 25) {
                continue;
            }

            $text = $this->normalizeWhitespace($node->textContent);
            if ($text === '' || mb_strlen($text) > 80) {
                continue;
            }

            if ($this->linkDensity($node) > 0.2) {
                $remove[] = $node;
            }
        }

        foreach ($remove as $node) {
            $node->parentNode?->removeChild($node);
        }
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

    private function unwrapHeadingParagraphWrappers(\DOMElement $scope): void
    {
        $paragraphs = [];
        foreach ($scope->getElementsByTagName('p') as $paragraph) {
            if ($paragraph instanceof \DOMElement) {
                $paragraphs[] = $paragraph;
            }
        }

        foreach ($paragraphs as $paragraph) {
            $heading = $this->firstElementChild($paragraph);
            if (!$heading instanceof \DOMElement || preg_match('/^h[1-6]$/', strtolower($heading->tagName)) !== 1) {
                continue;
            }

            if (count($this->elementChildren($paragraph)) !== 1 || trim($this->textOutsideDescendant($paragraph, $heading)) !== '') {
                continue;
            }

            $paragraph->removeChild($heading);
            $paragraph->parentNode?->replaceChild($heading, $paragraph);
        }
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

        $linkLength = 0.0;
        foreach ($node->getElementsByTagName('a') as $link) {
            $href = trim($link->getAttribute('href'));
            $coefficient = preg_match(self::HASH_URL_PATTERN, $href) === 1 ? 0.3 : 1.0;
            $linkLength += mb_strlen($this->normalizeWhitespace($link->textContent)) * $coefficient;
        }

        return $linkLength / $textLength;
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

            if ($this->isReadabilityDataTable($table)) {
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

    private function isReadabilityDataTable(\DOMElement $table): bool
    {
        if (strtolower($table->tagName) !== 'table') {
            return false;
        }

        if (strtolower(trim($table->getAttribute('role'))) === 'presentation') {
            return false;
        }

        if (trim($table->getAttribute('datatable')) === '0') {
            return false;
        }

        if (trim($table->getAttribute('summary')) !== '') {
            return true;
        }

        $caption = $table->getElementsByTagName('caption')->item(0);
        if ($caption instanceof \DOMElement && $caption->childNodes->length > 0) {
            return true;
        }

        foreach (['col', 'colgroup', 'tfoot', 'thead', 'th'] as $tagName) {
            if ($table->getElementsByTagName($tagName)->length > 0) {
                return true;
            }
        }

        if ($table->getElementsByTagName('table')->length > 0) {
            return false;
        }

        $size = $this->tableRowAndColumnCount($table);
        if ($size['columns'] === 1 || $size['rows'] === 1) {
            return false;
        }

        if ($size['rows'] >= 10 || $size['columns'] > 4) {
            return true;
        }

        return $size['rows'] * $size['columns'] > 10;
    }

    /**
     * @return array{rows: int, columns: int}
     */
    private function tableRowAndColumnCount(\DOMElement $table): array
    {
        $rows = 0;
        $columns = 0;
        foreach ($table->getElementsByTagName('tr') as $row) {
            if (!$row instanceof \DOMElement) {
                continue;
            }

            $rowspan = (int) $row->getAttribute('rowspan');
            $rows += $rowspan > 0 ? $rowspan : 1;

            $columnsInThisRow = 0;
            foreach ($row->getElementsByTagName('td') as $cell) {
                if (!$cell instanceof \DOMElement) {
                    continue;
                }

                $colspan = (int) $cell->getAttribute('colspan');
                $columnsInThisRow += $colspan > 0 ? $colspan : 1;
            }

            $columns = max($columns, $columnsInThisRow);
        }

        return ['rows' => $rows, 'columns' => $columns];
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

                if ($this->hasMalformedAttributeWrapperMarker($node)) {
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

    private function hasMalformedAttributeWrapperMarker(\DOMElement $node): bool
    {
        return $node->hasAttribute(self::MALFORMED_ATTRIBUTE_WRAPPER_MARKER);
    }

    private function removeMalformedAttributeWrapperMarkers(\DOMElement $scope): void
    {
        if ($this->hasMalformedAttributeWrapperMarker($scope)) {
            $scope->removeAttribute(self::MALFORMED_ATTRIBUTE_WRAPPER_MARKER);
        }

        foreach ($scope->getElementsByTagName('*') as $node) {
            if ($node instanceof \DOMElement && $this->hasMalformedAttributeWrapperMarker($node)) {
                $node->removeAttribute(self::MALFORMED_ATTRIBUTE_WRAPPER_MARKER);
            }
        }
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

    /**
     * @return list<\DOMElement>
     */
    private function elementChildren(\DOMElement $element): array
    {
        $children = [];
        foreach ($element->childNodes as $child) {
            if ($child instanceof \DOMElement) {
                $children[] = $child;
            }
        }

        return $children;
    }

    private function lastElementChild(\DOMElement $element): ?\DOMElement
    {
        for ($child = $element->lastChild; $child instanceof \DOMNode; $child = $child->previousSibling) {
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

    /**
     * @param list<string> $classesToPreserve
     * @return array<string, true>
     */
    private function classPreservationMap(array $classesToPreserve): array
    {
        $map = ['page' => true];
        foreach ($classesToPreserve as $class) {
            $class = trim($class);
            if ($class !== '') {
                $map[$class] = true;
            }
        }

        return $map;
    }

    /**
     * @param array<string, true> $classesToPreserve
     */
    private function cleanClasses(\DOMElement $node, array $classesToPreserve): void
    {
        $classes = array_values(array_filter(
            preg_split('/\s+/', $node->getAttribute('class')) ?: [],
            static fn (string $class): bool => isset($classesToPreserve[$class]),
        ));
        if ($classes === []) {
            $node->removeAttribute('class');
        } else {
            $node->setAttribute('class', implode(' ', $classes));
        }

        foreach ($node->childNodes as $child) {
            if ($child instanceof \DOMElement) {
                $this->cleanClasses($child, $classesToPreserve);
            }
        }
    }

    private function unwrapTransparentSectionWrappers(\DOMElement $scope): void
    {
        do {
            $changed = false;
            $sections = [];
            foreach ($scope->getElementsByTagName('section') as $section) {
                if ($section instanceof \DOMElement) {
                    $sections[] = $section;
                }
            }

            foreach ($sections as $section) {
                if (!$section->parentNode instanceof \DOMNode || !$this->isTransparentSectionWrapper($section)) {
                    continue;
                }

                while ($section->firstChild instanceof \DOMNode) {
                    $section->parentNode->insertBefore($section->firstChild, $section);
                }
                $section->parentNode->removeChild($section);
                $changed = true;
                break;
            }
        } while ($changed);
    }

    private function isTransparentSectionWrapper(\DOMElement $section): bool
    {
        if (strtolower($section->tagName) !== 'section' || ($section->attributes?->length ?? 0) > 0) {
            return false;
        }

        $elementChildren = 0;
        foreach ($section->childNodes as $child) {
            if ($child instanceof \DOMText && trim($child->textContent) !== '') {
                return false;
            }

            if (!$child instanceof \DOMElement) {
                continue;
            }

            if (!in_array(strtolower($child->tagName), ['article', 'div', 'section'], true)) {
                return false;
            }

            $elementChildren++;
        }

        return $elementChildren > 0;
    }

    private function unwrapHrSeparatedPageContainers(\DOMElement $scope): \DOMElement
    {
        if ($this->isHrSeparatedPageContainer($scope)) {
            $this->removeDirectHrChildren($scope);

            return $scope;
        }

        do {
            $changed = false;
            $containers = [];
            foreach ($scope->getElementsByTagName('div') as $container) {
                if ($container instanceof \DOMElement) {
                    $containers[] = $container;
                }
            }

            foreach ($containers as $container) {
                if (!$container->parentNode instanceof \DOMNode
                    || $container === $scope
                    || !$this->isHrSeparatedPageContainer($container)) {
                    continue;
                }

                $parent = $container->parentNode;
                while ($container->firstChild instanceof \DOMNode) {
                    $child = $container->firstChild;
                    if ($child instanceof \DOMElement && strtolower($child->tagName) === 'hr') {
                        $container->removeChild($child);
                        continue;
                    }

                    $parent->insertBefore($child, $container);
                }
                $parent->removeChild($container);
                $changed = true;
                break;
            }
        } while ($changed);

        return $scope;
    }

    private function removeDirectHrChildren(\DOMElement $container): void
    {
        $remove = [];
        foreach ($container->childNodes as $child) {
            if ($child instanceof \DOMElement && strtolower($child->tagName) === 'hr') {
                $remove[] = $child;
            }
        }

        foreach ($remove as $child) {
            $container->removeChild($child);
        }
    }

    private function isHrSeparatedPageContainer(\DOMElement $container): bool
    {
        if (strtolower($container->tagName) !== 'div') {
            return false;
        }

        $contentChildren = 0;
        $sawSeparator = false;
        $lastWasSeparator = true;
        foreach ($container->childNodes as $child) {
            if ($child instanceof \DOMText && !$this->isWhitespaceTextNode($child)) {
                return false;
            }

            if (!$child instanceof \DOMElement) {
                continue;
            }

            $tagName = strtolower($child->tagName);
            if ($tagName === 'hr') {
                if ($lastWasSeparator || $contentChildren === 0) {
                    return false;
                }

                $sawSeparator = true;
                $lastWasSeparator = true;
                continue;
            }

            if (!in_array($tagName, ['article', 'div', 'section'], true)) {
                return false;
            }

            if (!$this->nodeHasVisibleText($child) && !$this->hasMediaPayload($child)) {
                return false;
            }

            $contentChildren++;
            $lastWasSeparator = false;
        }

        return $sawSeparator && $contentChildren >= 2 && !$lastWasSeparator;
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

    private function bestContentNode(\DOMXPath $xpath, bool $weightClasses = true): ?\DOMNode
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
            $score = strlen($text) + (substr_count($text, ',') * 20) + ($paragraphs * 80) + $this->semanticContentWeight($node);
            if ($weightClasses) {
                $score += $this->contentClassWeight($node);
            }
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
        $weight = 0;
        if (preg_match('/\bstorytext\b/', $matchString) === 1) {
            $weight += 12000;
        } elseif (preg_match('/\bstorycontent\b/', $matchString) === 1) {
            $weight += 6000;
        }

        if (preg_match('/\b(article-body|article__body|entry-content|post-content|article-content)\b/', $matchString) === 1) {
            $weight += 10000;
        }

        if (preg_match('/\b(pane-aclu-components-description|field-name-body|node__content)\b/', $matchString) === 1) {
            $weight += 12000;
        }

        if (preg_match('/\bparagraph\b/', $matchString) === 1
            && $this->hasDescendantWithClass($node, 'ynDetailText')) {
            $weight += 12000;
        }

        if ($node->getAttribute('id') === 'posts' && $this->looksLikeSinglePostContainer($node)) {
            $weight += 2500;
        }

        if ($this->hasArticleBodyAttribute($node)) {
            $weight += 3000;
        }

        if (preg_match('/\b(content|main-content)\b/', $matchString) === 1
            && preg_match('/(?:^|\s)section-content(?:\s|$)/', $matchString) !== 1) {
            $weight += 1500;
        }

        if (preg_match(self::CLASS_WEIGHT_NEGATIVE_PATTERN, $matchString) === 1) {
            $weight -= 25;
        }

        if (preg_match(self::CLASS_WEIGHT_POSITIVE_PATTERN, $matchString) === 1) {
            $weight += 25;
        }

        return $weight;
    }

    private function looksLikeSinglePostContainer(\DOMElement $node): bool
    {
        $postChildren = 0;
        foreach ($this->elementChildren($node) as $child) {
            if ($this->hasClassToken($child, 'post')) {
                $postChildren++;
            }
        }

        if ($postChildren !== 1 || $node->getElementsByTagName('p')->length === 0) {
            return false;
        }

        foreach (['h1', 'h2', 'h3'] as $tagName) {
            if ($node->getElementsByTagName($tagName)->length > 0) {
                return mb_strlen($this->normalizeWhitespace($node->textContent)) >= 500;
            }
        }

        return false;
    }

    private function hasArticleBodyAttribute(\DOMElement $node): bool
    {
        return str_contains(strtolower($node->getAttribute('itemprop')), 'articlebody')
            || str_contains(strtolower($node->getAttribute('property')), 'articlebody');
    }

    private function hasStrongArticleContentDescendant(\DOMElement $node): bool
    {
        foreach ($node->getElementsByTagName('*') as $descendant) {
            if (!$descendant instanceof \DOMElement) {
                continue;
            }

            if ($this->hasArticleBodyAttribute($descendant)) {
                return true;
            }

            $matchString = strtolower($descendant->getAttribute('class') . ' ' . $descendant->getAttribute('id'));
            if (preg_match('/\b(article-body|article__body|entry-content|post-content|article-content|pane-aclu-components-description|field-name-body|node__content)\b/', $matchString) === 1
                && $descendant->getElementsByTagName('p')->length >= 3
                && mb_strlen($this->normalizeWhitespace($descendant->textContent)) >= 500) {
                return true;
            }
        }

        return false;
    }

    private function hasDescendantWithClass(\DOMElement $node, string $class): bool
    {
        foreach ($node->getElementsByTagName('*') as $descendant) {
            if ($descendant instanceof \DOMElement && $this->hasClassToken($descendant, $class)) {
                return true;
            }
        }

        return false;
    }

    private function innerHtml(\DOMNode $node): string
    {
        $html = '';
        foreach ($node->childNodes as $child) {
            $html .= $node->ownerDocument?->saveHTML($child) ?: '';
        }

        return trim($html);
    }

    private function normalizeSerializedInlineSvgDataUris(string $html): string
    {
        return preg_replace_callback(
            '/\b(src|poster|data-src)="([^"]*data:image\/svg\+xml;utf8,[^"]*)"/i',
            static fn (array $matches): string => $matches[1] . '="' . str_replace('%20', ' ', $matches[2]) . '"',
            $html,
        ) ?? $html;
    }

    private function readabilityPageHtml(\DOMElement $node): string
    {
        $document = $node->ownerDocument;
        if (!$document instanceof \DOMDocument) {
            return $this->innerHtml($node);
        }

        $wrapper = $document->createElement('div');
        $wrapper->setAttribute('id', 'readability-page-1');
        $wrapper->setAttribute('class', 'page');

        if ($this->shouldPreserveReadabilityPageRoot($node)) {
            $clone = $node->cloneNode(true);
            if ($clone instanceof \DOMElement) {
                $wrapper->appendChild($clone);
                if ($this->shouldSerializeMainContentRootAsDiv($clone)) {
                    $this->replaceElementTag($clone, 'div');
                }
            } elseif ($clone instanceof \DOMNode) {
                $wrapper->appendChild($clone);
            }
        } else {
            foreach ($node->childNodes as $child) {
                $wrapper->appendChild($child->cloneNode(true));
            }
        }

        return trim($document->saveHTML($wrapper) ?: '');
    }

    private function shouldPreserveReadabilityPageRoot(\DOMElement $node): bool
    {
        if (strtolower($node->tagName) === 'article'
            && trim($node->getAttribute('id')) === 'story') {
            return true;
        }

        if (strtolower($node->tagName) === 'div'
            && trim($node->getAttribute('id')) === 'storytext') {
            return true;
        }

        if (strtolower($node->tagName) === 'div'
            && trim($node->getAttribute('id')) === 'content') {
            return true;
        }

        if (strtolower($node->tagName) === 'div'
            && trim($node->getAttribute('id')) === 'main-content'
            && strtolower(trim($node->getAttribute('role'))) === 'main') {
            return true;
        }

        if (strtolower($node->tagName) === 'main'
            && trim($node->getAttribute('id')) === 'main-content'
            && strtolower(trim($node->getAttribute('role'))) === 'main') {
            return true;
        }

        if (strtolower($node->tagName) === 'main'
            && trim($node->getAttribute('id')) === 'content-main'
            && $node->getElementsByTagName('article')->length === 1) {
            return true;
        }

        if (strtolower($node->tagName) === 'section'
            && trim($node->getAttribute('id')) === 'maia-main'
            && strtolower(trim($node->getAttribute('role'))) === 'main') {
            return true;
        }

        if (strtolower($node->tagName) === 'section'
            && trim($node->getAttribute('id')) === 'article-body'
            && $node->getElementsByTagName('p')->length >= 3) {
            return true;
        }

        if (strtolower($node->tagName) === 'div' && $this->hasArticleBodyAttribute($node)) {
            return true;
        }

        return strtolower($node->tagName) === 'div'
            && trim($node->getAttribute('data-test-id')) === 'article-review-body'
            && $this->hasArticleBodyAttribute($node);
    }

    private function shouldSerializeMainContentRootAsDiv(\DOMElement $node): bool
    {
        if (strtolower($node->tagName) !== 'main') {
            return false;
        }

        if (trim($node->getAttribute('id')) === 'main-content'
            && strtolower(trim($node->getAttribute('role'))) === 'main') {
            return true;
        }

        return trim($node->getAttribute('id')) === 'content-main'
            && $node->getElementsByTagName('article')->length === 1;
    }
}
