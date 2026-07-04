<?php

declare(strict_types=1);

namespace PortLibs\Pandoc;

final class HtmlReader
{
    private const MICRODATA_MAX_ITEMS = 32;
    private const MICRODATA_MAX_PROPERTIES_PER_ITEM = 64;
    private const MICRODATA_MAX_VALUE_BYTES = 512;

    private readonly MarkdownReader $reader;

    /**
     * @param array<string, mixed> $options
     */
    public function __construct(private readonly array $options = [])
    {
        $this->reader = new MarkdownReader(array_replace(['htmlNativeDivs' => true, 'htmlReader' => true], $options));
    }

    public function read(string $bytes): AstNode
    {
        $standaloneImageChildren = self::standaloneImageFragmentChildren($bytes);
        if ($standaloneImageChildren !== null) {
            $children = $standaloneImageChildren;
            $consumedFootnoteContainerCount = 0;
            $attrs = [];
        } elseif (($standaloneProgressChildren = $this->standaloneProgressFragmentChildren($bytes)) !== null) {
            $children = $standaloneProgressChildren;
            $consumedFootnoteContainerCount = 0;
            $attrs = [];
        } elseif (($standaloneTransparentChildren = $this->standaloneTransparentInlineFragmentChildren($bytes)) !== null) {
            $children = $standaloneTransparentChildren;
            $consumedFootnoteContainerCount = 0;
            $attrs = [];
        } else {
            $delegated = self::delegateHtmlBytes($bytes);
            $document = $this->reader->read($delegated['bytes']);
            [$children, $consumedFootnoteContainerCount] = self::containsAstNodeType($document->children, 'note')
                ? self::stripConsumedHtmlFootnoteContainers($document->children)
                : [$document->children, 0];
            if ($delegated['implicitPlainBody']) {
                $children = self::restoreImplicitPlainBody($children);
            }
            $children = $this->restoreImplicitNativeMainPlainBlocks($bytes, $children);
            $children = self::stripHtmlRawInlineWrappers($children);
            $attrs = $document->attrs;
        }
        $meta = $attrs['meta'] ?? [];
        if (!is_array($meta)) {
            $meta = [];
        }

        $attrs['sourceFormat'] = 'html';
        $attrs['meta'] = array_replace($meta, [
            'sourceFormat' => 'html',
            'reader' => self::class,
            'readerScope' => 'bounded-html-reader',
            'htmlReaderDelegate' => MarkdownReader::class,
            'htmlNativeDivs' => (bool) (($this->options['htmlNativeDivs'] ?? true)),
            'sourceBytes' => strlen($bytes),
            'sourceSha256' => hash('sha256', $bytes),
            'payloadExposurePolicy' => 'html-dom-text-and-structural-metadata-only',
            'htmlFootnoteContainerPolicy' => 'doc-endnotes-containers-consumed-after-note-resolution',
            'htmlConsumedFootnoteContainerCount' => $consumedFootnoteContainerCount,
        ], $this->microdataMetadata($bytes));

        return new AstNode('document', $attrs, $children);
    }

    /**
     * @return list<AstNode>|null
     */
    private static function standaloneImageFragmentChildren(string $bytes): ?array
    {
        if (preg_match('/^\s*<img\b[^>]*>\s*$/is', $bytes) !== 1) {
            return null;
        }

        $previous = libxml_use_internal_errors(true);
        $dom = new \DOMDocument('1.0', 'UTF-8');
        $loaded = $dom->loadHTML(
            '<?xml encoding="UTF-8"><!doctype html><html><body>' . $bytes . '</body></html>',
            LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING
        );
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        if (!$loaded) {
            return null;
        }

        $body = $dom->getElementsByTagName('body')->item(0);
        if (!$body instanceof \DOMElement) {
            return null;
        }

        $image = null;
        foreach ($body->childNodes as $child) {
            if ($child instanceof \DOMText && trim($child->wholeText) === '') {
                continue;
            }
            if (!$child instanceof \DOMElement || strtolower($child->localName) !== 'img' || $image instanceof \DOMElement) {
                return null;
            }
            $image = $child;
        }

        if (!$image instanceof \DOMElement) {
            return null;
        }

        $alt = $image->getAttribute('alt');
        $attrs = self::standaloneImageAttrs($image);
        $attrs['url'] = $image->getAttribute('src');
        $attrs['alt'] = $alt;
        $title = $image->getAttribute('title');
        if ($title !== '') {
            $attrs['title'] = $title;
        }

        $children = $alt === '' ? [] : [new AstNode('text', ['text' => $alt])];
        $imageNode = new AstNode('image', $attrs, $children);

        return [new AstNode('plain', ['text' => $alt], [$imageNode])];
    }

    /**
     * @return list<AstNode>|null
     */
    private function standaloneProgressFragmentChildren(string $bytes): ?array
    {
        if (preg_match('/<progress\b/i', $bytes) !== 1) {
            return null;
        }

        $previous = libxml_use_internal_errors(true);
        $dom = new \DOMDocument('1.0', 'UTF-8');
        $loaded = $dom->loadHTML(
            '<?xml encoding="UTF-8"><!doctype html><html><body>' . $bytes . '</body></html>',
            LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING
        );
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        if (!$loaded) {
            return null;
        }

        $body = $dom->getElementsByTagName('body')->item(0);
        if (!$body instanceof \DOMElement) {
            return null;
        }

        $children = [];
        $pendingInlineSource = '';
        $sawTopLevelProgress = false;
        foreach ($body->childNodes as $child) {
            if ($child instanceof \DOMElement && strtolower($child->localName) === 'progress') {
                $this->appendStandaloneProgressPlainBlock($children, $pendingInlineSource);
                $pendingInlineSource = '';
                $this->appendStandaloneProgressPlainBlock($children, self::htmlChildNodesSource($child));
                $sawTopLevelProgress = true;
                continue;
            }

            if ($child instanceof \DOMElement && !self::isStandaloneProgressInlineSiblingName(strtolower($child->localName))) {
                return null;
            }

            $pendingInlineSource .= self::htmlNodeSource($child);
        }

        if (!$sawTopLevelProgress) {
            return null;
        }

        $this->appendStandaloneProgressPlainBlock($children, $pendingInlineSource);

        return $children;
    }

    /**
     * @param list<AstNode> $children
     */
    private function appendStandaloneProgressPlainBlock(array &$children, string $inlineSource): void
    {
        $plain = $this->plainBlockFromHtmlInlineSource($inlineSource);
        if ($plain instanceof AstNode) {
            $children[] = $plain;
        }
    }

    private function plainBlockFromHtmlInlineSource(string $inlineSource): ?AstNode
    {
        $inlineSource = trim($inlineSource);
        if ($inlineSource === '') {
            return null;
        }

        $reader = new MarkdownReader(array_replace(
            ['htmlNativeDivs' => true, 'htmlReader' => true],
            $this->options,
            ['htmlRawHtml' => false]
        ));
        $document = $reader->read($inlineSource);
        if (count($document->children) !== 1) {
            return null;
        }

        $block = $document->children[0];
        if ($block->type === 'plain') {
            return $block;
        }
        if ($block->type === 'paragraph') {
            return self::paragraphToPlain($block);
        }

        return null;
    }

    private static function isStandaloneProgressInlineSiblingName(string $name): bool
    {
        return in_array($name, [
            'a',
            'abbr',
            'b',
            'bdi',
            'bdo',
            'br',
            'cite',
            'code',
            'data',
            'dfn',
            'em',
            'i',
            'kbd',
            'mark',
            'meter',
            'q',
            's',
            'samp',
            'small',
            'span',
            'strong',
            'sub',
            'sup',
            'time',
            'tt',
            'u',
            'var',
            'wbr',
        ], true);
    }

    private static function htmlChildNodesSource(\DOMElement $element): string
    {
        $source = '';
        foreach ($element->childNodes as $child) {
            $source .= self::htmlNodeSource($child);
        }

        return $source;
    }

    private static function htmlNodeSource(\DOMNode $node): string
    {
        if ($node instanceof \DOMText || $node instanceof \DOMCdataSection) {
            return htmlspecialchars($node->nodeValue ?? '', ENT_NOQUOTES | ENT_SUBSTITUTE, 'UTF-8');
        }

        $document = $node->ownerDocument;
        if (!$document instanceof \DOMDocument) {
            return '';
        }

        $source = $document->saveHTML($node);

        return is_string($source) ? $source : '';
    }

    /**
     * @return list<AstNode>|null
     */
    private function standaloneTransparentInlineFragmentChildren(string $bytes): ?array
    {
        if (self::standaloneTransparentInlineFragmentTag($bytes) !== 'time') {
            return null;
        }

        $reader = new MarkdownReader(array_replace(
            ['htmlNativeDivs' => true, 'htmlReader' => true],
            $this->options,
            ['htmlRawHtml' => false]
        ));
        $document = $reader->read($bytes);
        if (count($document->children) !== 1 || $document->children[0]->type !== 'paragraph') {
            return null;
        }

        return [self::paragraphToPlain($document->children[0])];
    }

    private static function standaloneTransparentInlineFragmentTag(string $bytes): ?string
    {
        $trimmed = trim($bytes);
        if (preg_match('/^<([A-Za-z][A-Za-z0-9:-]*)\b/', $trimmed, $match) !== 1) {
            return null;
        }

        $tag = strtolower($match[1]);
        if (!in_array($tag, ['time'], true)) {
            return null;
        }

        $previous = libxml_use_internal_errors(true);
        $dom = new \DOMDocument('1.0', 'UTF-8');
        $loaded = $dom->loadHTML(
            '<?xml encoding="UTF-8"><!doctype html><html><body>' . $bytes . '</body></html>',
            LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING
        );
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        if (!$loaded) {
            return null;
        }

        $body = $dom->getElementsByTagName('body')->item(0);
        if (!$body instanceof \DOMElement) {
            return null;
        }

        $element = null;
        foreach ($body->childNodes as $child) {
            if ($child instanceof \DOMText && trim($child->wholeText) === '') {
                continue;
            }
            if (!$child instanceof \DOMElement || strtolower($child->localName) !== $tag || $element instanceof \DOMElement) {
                return null;
            }
            $element = $child;
        }

        return $element instanceof \DOMElement ? $tag : null;
    }

    /**
     * @return array<string, mixed>
     */
    private static function standaloneImageAttrs(\DOMElement $image): array
    {
        $id = '';
        $classes = [];
        $attributes = [];
        $htmlAttributes = [];

        foreach ($image->attributes as $attribute) {
            if (!$attribute instanceof \DOMAttr) {
                continue;
            }

            $name = strtolower($attribute->name);
            if (in_array($name, ['src', 'alt', 'title'], true)) {
                continue;
            }

            $value = trim($attribute->value);
            if ($name === 'id') {
                $id = $value;
                if ($value !== '') {
                    $htmlAttributes['id'] = $value;
                }
                continue;
            }

            if ($name === 'class') {
                $classes = preg_split('/\s+/', $value, -1, PREG_SPLIT_NO_EMPTY) ?: [];
                if ($classes !== []) {
                    $htmlAttributes['class'] = implode(' ', $classes);
                }
                continue;
            }

            $key = str_starts_with($name, 'data-') ? substr($name, 5) : $name;
            if ($key === '') {
                continue;
            }

            $attributes[$key] = $value;
            $htmlAttributes[$name] = $value;
        }

        $attrs = [];
        if ($id !== '') {
            $attrs['id'] = $id;
        }
        if ($classes !== []) {
            $attrs['classes'] = $classes;
        }
        if ($attributes !== []) {
            $attrs['attributes'] = $attributes;
        }
        if ($htmlAttributes !== []) {
            $attrs['htmlAttributes'] = $htmlAttributes;
        }

        return $attrs;
    }

    /**
     * @return array{bytes: string, implicitPlainBody: bool}
     */
    private static function delegateHtmlBytes(string $bytes): array
    {
        $trimmed = ltrim($bytes);
        if (self::isHeadOrBodyFragment($trimmed)) {
            return [
                'bytes' => '<html>' . $bytes . '</html>',
                'implicitPlainBody' => self::hasImplicitPlainBody($trimmed),
            ];
        }

        if (self::isInlineFragmentStart($trimmed)) {
            return ['bytes' => $bytes, 'implicitPlainBody' => true];
        }

        if (self::isTransparentInlineFragmentStart($trimmed)) {
            return [
                'bytes' => '<html><body>' . $bytes . '</body></html>',
                'implicitPlainBody' => true,
            ];
        }

        if (preg_match('/^<(output|select)\b/i', $trimmed, $match) !== 1) {
            return ['bytes' => $bytes, 'implicitPlainBody' => false];
        }

        $tag = strtolower($match[1]);
        if (preg_match('/<\/' . preg_quote($tag, '/') . '\s*>/i', $trimmed) !== 1) {
            return ['bytes' => $bytes, 'implicitPlainBody' => false];
        }

        return [
            'bytes' => '<form data-html-reader-boundary="form-control">' . $bytes . '</form>',
            'implicitPlainBody' => false,
        ];
    }

    private static function isHeadOrBodyFragment(string $trimmed): bool
    {
        return preg_match('/^<(head|body)\b/i', $trimmed) === 1
            && preg_match('/^<html\b/i', $trimmed) !== 1;
    }

    private static function hasImplicitPlainBody(string $trimmed): bool
    {
        if (preg_match('/<body\b[^>]*>(.*?)(?:<\/body\s*>|<\/html\s*>|$)/is', $trimmed, $match) !== 1) {
            return false;
        }

        $body = trim((string) preg_replace('/<\/head\s*>/i', '', $match[1]));
        if ($body === '') {
            return false;
        }

        return preg_match(self::htmlBlockContainerPattern(), $body) !== 1;
    }

    private static function isInlineFragmentStart(string $trimmed): bool
    {
        return preg_match(
            '/^<(?:a|bdo|code|em|kbd|mark|q|samp|span|strong|sub|sup|tt|u|var)\b/i',
            $trimmed
        ) === 1;
    }

    private static function isTransparentInlineFragmentStart(string $trimmed): bool
    {
        return preg_match('/^<(?:bdi|data|meter)\b/i', $trimmed) === 1;
    }

    private static function htmlBlockContainerPattern(): string
    {
        return '/<(?:address|article|aside|blockquote|details|div|dl|fieldset|figcaption|figure|footer|form|h[1-6]|header|hr|main|nav|ol|p|pre|section|table|ul)\b/i';
    }

    /**
     * @param list<AstNode> $children
     * @return list<AstNode>
     */
    private static function restoreImplicitPlainBody(array $children): array
    {
        if (count($children) !== 1 || $children[0]->type !== 'paragraph') {
            return $children;
        }

        return [new AstNode('plain', $children[0]->attrs, $children[0]->children)];
    }

    /**
     * @param list<AstNode> $children
     * @return list<AstNode>
     */
    private function restoreImplicitNativeMainPlainBlocks(string $bytes, array $children): array
    {
        if (($this->options['htmlNativeDivs'] ?? true) !== true) {
            return $children;
        }

        try {
            $dom = Html5Dom::parseHtmlDocument($bytes);
        } catch (\Throwable) {
            return $children;
        }

        $body = $dom->getElementsByTagName('body')->item(0);
        if (!$body instanceof \DOMElement) {
            return $children;
        }

        $main = self::firstHtmlElement($body, 'main');
        if (!$main instanceof \DOMElement || !self::htmlElementHasOnlyInlineContent($main)) {
            return $children;
        }

        if (count($children) !== 1) {
            return $children;
        }

        $child = $children[0];
        if ($child->type === 'paragraph') {
            return [self::paragraphToPlain($child)];
        }

        if ($child->type === 'div' && count($child->children) === 1 && $child->children[0]->type === 'paragraph') {
            return [new AstNode('div', $child->attrs, [self::paragraphToPlain($child->children[0])])];
        }

        return $children;
    }

    private static function firstHtmlElement(\DOMElement $root, string $name): ?\DOMElement
    {
        if (strtolower($root->localName) === $name) {
            return $root;
        }

        foreach ($root->getElementsByTagName($name) as $element) {
            if ($element instanceof \DOMElement) {
                return $element;
            }
        }

        return null;
    }

    private static function htmlElementHasOnlyInlineContent(\DOMElement $element): bool
    {
        $hasContent = false;
        foreach ($element->childNodes as $child) {
            if ($child instanceof \DOMText || $child instanceof \DOMCdataSection) {
                if (trim($child->nodeValue ?? '') !== '') {
                    $hasContent = true;
                }
                continue;
            }

            if (!$child instanceof \DOMElement) {
                continue;
            }

            if (self::isHtmlBlockContainerName(strtolower($child->localName))) {
                return false;
            }
            $hasContent = true;
        }

        return $hasContent;
    }

    private static function isHtmlBlockContainerName(string $name): bool
    {
        return in_array($name, [
            'address',
            'article',
            'aside',
            'blockquote',
            'details',
            'div',
            'dl',
            'fieldset',
            'figcaption',
            'figure',
            'footer',
            'form',
            'h1',
            'h2',
            'h3',
            'h4',
            'h5',
            'h6',
            'header',
            'hr',
            'main',
            'nav',
            'ol',
            'p',
            'pre',
            'section',
            'table',
            'ul',
        ], true);
    }

    private static function paragraphToPlain(AstNode $paragraph): AstNode
    {
        return new AstNode('plain', $paragraph->attrs, $paragraph->children);
    }

    /**
     * @param list<AstNode> $children
     * @return list<AstNode>
     */
    private static function stripHtmlRawInlineWrappers(array $children): array
    {
        $stripped = [];
        foreach ($children as $child) {
            if (self::isHtmlRawInlineWrapper($child)) {
                continue;
            }

            $stripped[] = new AstNode(
                $child->type,
                self::stripHtmlRawInlineWrapperAttrs($child->attrs),
                self::stripHtmlRawInlineWrappers($child->children)
            );
        }

        return $stripped;
    }

    private static function isHtmlRawInlineWrapper(AstNode $node): bool
    {
        if ($node->type !== 'raw_html_inline') {
            return false;
        }

        $html = trim((string) ($node->attrs['html'] ?? $node->attrs['text'] ?? ''));

        return self::isHtmlRawInlineWrapperSource($html);
    }

    /**
     * @param array<string, mixed> $attrs
     * @return array<string, mixed>
     */
    private static function stripHtmlRawInlineWrapperAttrs(array $attrs): array
    {
        foreach ($attrs as $key => $value) {
            $attrs[$key] = $key === 'text' && is_string($value)
                ? self::stripHtmlRawInlineWrapperText($value)
                : self::stripHtmlRawInlineWrapperValue($value);
        }

        return $attrs;
    }

    private static function stripHtmlRawInlineWrapperText(string $text): string
    {
        return (string) preg_replace('/<\/?(?:progress|time)(?:\s[^>]*)?>/i', '', $text);
    }

    private static function stripHtmlRawInlineWrapperValue(mixed $value): mixed
    {
        if ($value instanceof AstNode) {
            if (self::isHtmlRawInlineWrapper($value)) {
                return null;
            }

            return new AstNode(
                $value->type,
                self::stripHtmlRawInlineWrapperAttrs($value->attrs),
                self::stripHtmlRawInlineWrappers($value->children)
            );
        }

        if (!is_array($value)) {
            return $value;
        }

        if (array_is_list($value)) {
            $stripped = [];
            foreach ($value as $child) {
                if ($child instanceof AstNode && self::isHtmlRawInlineWrapper($child)) {
                    continue;
                }

                $stripped[] = self::stripHtmlRawInlineWrapperValue($child);
            }

            return $stripped;
        }

        foreach ($value as $key => $child) {
            $value[$key] = self::stripHtmlRawInlineWrapperValue($child);
        }

        return $value;
    }

    private static function isHtmlRawInlineWrapperSource(string $html): bool
    {
        return preg_match('/^<(?:progress|time)(?:\s[^>]*)?>$/i', $html) === 1
            || preg_match('/^<\/(?:progress|time)\s*>$/i', $html) === 1;
    }

    /**
     * @param list<AstNode> $children
     */
    private static function containsAstNodeType(array $children, string $type): bool
    {
        foreach ($children as $child) {
            if ($child->type === $type || self::containsAstNodeType($child->children, $type)) {
                return true;
            }

            foreach ($child->attrs as $value) {
                if (self::attributeValueContainsAstNodeType($value, $type)) {
                    return true;
                }
            }
        }

        return false;
    }

    private static function attributeValueContainsAstNodeType(mixed $value, string $type): bool
    {
        if ($value instanceof AstNode) {
            return $value->type === $type
                || self::containsAstNodeType($value->children, $type)
                || self::attributeValueContainsAstNodeType($value->attrs, $type);
        }

        if (!is_array($value)) {
            return false;
        }

        foreach ($value as $child) {
            if (self::attributeValueContainsAstNodeType($child, $type)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param list<AstNode> $children
     * @return array{0: list<AstNode>, 1: int}
     */
    private static function stripConsumedHtmlFootnoteContainers(array $children): array
    {
        $removed = 0;

        return [self::stripConsumedHtmlFootnoteContainerChildren($children, $removed), $removed];
    }

    /**
     * @param list<AstNode> $children
     * @return list<AstNode>
     */
    private static function stripConsumedHtmlFootnoteContainerChildren(array $children, int &$removed): array
    {
        $stripped = [];
        foreach ($children as $child) {
            if (self::isConsumedHtmlFootnoteContainerNode($child)) {
                $removed++;
                continue;
            }

            $nestedRemovedBefore = $removed;
            $nestedChildren = self::stripConsumedHtmlFootnoteContainerChildren($child->children, $removed);
            if ($removed !== $nestedRemovedBefore) {
                $child = new AstNode($child->type, $child->attrs, $nestedChildren);
            }

            $stripped[] = $child;
        }

        return $stripped;
    }

    private static function isConsumedHtmlFootnoteContainerNode(AstNode $node): bool
    {
        if ($node->type !== 'div') {
            return false;
        }

        if (self::htmlNodeAttributeValue($node, 'role') === 'doc-endnotes') {
            return true;
        }

        return in_array(self::htmlNodeSemanticType($node), ['footnotes', 'rearnotes'], true);
    }

    private static function htmlNodeSemanticType(AstNode $node): string
    {
        $type = self::htmlNodeAttributeValue($node, 'type');
        if ($type !== '') {
            return $type;
        }

        return self::htmlNodeAttributeValue($node, 'epub:type');
    }

    private static function htmlNodeAttributeValue(AstNode $node, string $name): string
    {
        foreach (['htmlAttributes', 'attributes'] as $attributeSet) {
            $attributes = $node->attrs[$attributeSet] ?? [];
            if (!is_array($attributes) || !array_key_exists($name, $attributes)) {
                continue;
            }

            $value = $attributes[$name];
            if (is_scalar($value)) {
                return strtolower(trim((string) $value));
            }
        }

        return '';
    }

    /**
     * @return array<string, mixed>
     */
    private function microdataMetadata(string $bytes): array
    {
        $base = [
            'htmlMicrodataReviewPolicy' => 'html-microdata-metadata-only',
            'htmlMicrodataItemLimit' => self::MICRODATA_MAX_ITEMS,
            'htmlMicrodataPropertyLimitPerItem' => self::MICRODATA_MAX_PROPERTIES_PER_ITEM,
            'htmlMicrodataValueByteLimit' => self::MICRODATA_MAX_VALUE_BYTES,
        ];

        try {
            $dom = Html5Dom::parseHtmlDocument($bytes);
        } catch (\Throwable) {
            return $base + [
                'htmlMicrodataParseStatus' => 'unavailable',
                'htmlMicrodataItemCount' => 0,
                'htmlMicrodataReportedItemCount' => 0,
                'htmlMicrodataTopLevelItemCount' => 0,
                'htmlMicrodataPropertyCount' => 0,
                'htmlMicrodataPropertyNames' => [],
                'htmlMicrodataItems' => [],
                'htmlMicrodataTopLevelItemIndexes' => [],
                'htmlMicrodataDiagnostics' => ['html-microdata-dom-parse-failed'],
            ];
        }

        $idIndex = self::microdataElementIdIndex($dom);
        $baseHref = self::htmlDocumentBaseHref($dom);
        $itemElements = self::microdataItemElements($dom);
        $items = [];
        $topLevelIndexes = [];
        $globalPropertyNames = [];
        $globalPropertyCount = 0;
        $diagnostics = [];
        $reportedItemCount = min(count($itemElements), self::MICRODATA_MAX_ITEMS);

        if (count($itemElements) > self::MICRODATA_MAX_ITEMS) {
            $diagnostics[] = 'html-microdata-item-limit-exceeded';
        }

        foreach (array_slice($itemElements, 0, self::MICRODATA_MAX_ITEMS) as $index => $element) {
            [$item, $itemDiagnostics] = self::microdataItemSummary($element, $idIndex, $baseHref);
            $items[] = $item;
            array_push($diagnostics, ...$itemDiagnostics);

            if (!self::hasAncestorItemScope($element)) {
                $topLevelIndexes[] = $index;
            }

            $globalPropertyCount += (int) $item['propertyCount'];
            foreach ($item['propertyNames'] as $name) {
                if (!in_array($name, $globalPropertyNames, true)) {
                    $globalPropertyNames[] = $name;
                }
            }
        }

        return $base + [
            'htmlMicrodataParseStatus' => 'parsed',
            'htmlMicrodataItemCount' => count($itemElements),
            'htmlMicrodataReportedItemCount' => $reportedItemCount,
            'htmlMicrodataTopLevelItemCount' => count($topLevelIndexes),
            'htmlMicrodataPropertyCount' => $globalPropertyCount,
            'htmlMicrodataPropertyNames' => $globalPropertyNames,
            'htmlMicrodataItems' => $items,
            'htmlMicrodataTopLevelItemIndexes' => $topLevelIndexes,
            'htmlMicrodataDiagnostics' => array_values(array_unique($diagnostics)),
        ];
    }

    /**
     * @return array<string, \DOMElement>
     */
    private static function microdataElementIdIndex(\DOMDocument $dom): array
    {
        $index = [];
        foreach (self::documentElements($dom) as $element) {
            if ($element->hasAttribute('id')) {
                $id = $element->getAttribute('id');
                if ($id !== '' && !isset($index[$id])) {
                    $index[$id] = $element;
                }
            }
        }

        return $index;
    }

    /**
     * @return list<\DOMElement>
     */
    private static function microdataItemElements(\DOMDocument $dom): array
    {
        return array_values(array_filter(
            self::documentElements($dom),
            static fn (\DOMElement $element): bool => $element->hasAttribute('itemscope')
        ));
    }

    /**
     * @return list<\DOMElement>
     */
    private static function documentElements(\DOMDocument $dom): array
    {
        $root = $dom->documentElement;
        if (!$root instanceof \DOMElement) {
            return [];
        }

        $elements = [$root];
        foreach ($root->getElementsByTagName('*') as $element) {
            if ($element instanceof \DOMElement) {
                $elements[] = $element;
            }
        }

        return $elements;
    }

    /**
     * @param array<string, \DOMElement> $idIndex
     * @return array{0: array<string, mixed>, 1: list<string>}
     */
    private static function microdataItemSummary(\DOMElement $element, array $idIndex, ?string $baseHref): array
    {
        $properties = [];
        $seenPropertyElements = [];
        $diagnostics = [];
        foreach ($element->childNodes as $child) {
            self::collectMicrodataProperties($child, $properties, $seenPropertyElements, $diagnostics, $baseHref);
        }

        $itemrefIds = self::spaceSeparatedTokens($element->getAttribute('itemref'));
        $missingItemrefIds = [];
        foreach ($itemrefIds as $id) {
            if (!isset($idIndex[$id])) {
                $missingItemrefIds[] = $id;
                $diagnostics[] = 'missing-itemref:' . $id;
                continue;
            }

            self::collectMicrodataProperties($idIndex[$id], $properties, $seenPropertyElements, $diagnostics, $baseHref);
        }

        $properties = array_slice($properties, 0, self::MICRODATA_MAX_PROPERTIES_PER_ITEM);
        $propertyNameCounts = self::microdataPropertyNameCounts($properties);

        $summary = [
            'microdataReviewPolicy' => 'html-microdata-metadata-only',
            'elementName' => XmlHtmlDom::htmlElementName($element),
            'itemTypes' => self::spaceSeparatedTokens($element->getAttribute('itemtype')),
            'itemId' => $element->hasAttribute('itemid') ? $element->getAttribute('itemid') : null,
            'itemrefIds' => $itemrefIds,
            'missingItemrefIds' => $missingItemrefIds,
            'propertyCount' => count($properties),
            'propertyNames' => array_keys($propertyNameCounts),
            'propertyNameCounts' => $propertyNameCounts,
            'properties' => $properties,
        ];

        if ($element->hasAttribute('id')) {
            $summary['elementId'] = $element->getAttribute('id');
        }
        if (count($seenPropertyElements) > self::MICRODATA_MAX_PROPERTIES_PER_ITEM) {
            $diagnostics[] = 'html-microdata-property-limit-exceeded';
        }

        return [$summary, $diagnostics];
    }

    /**
     * @param list<array<string, mixed>> $properties
     * @param array<string, true> $seenPropertyElements
     * @param list<string> $diagnostics
     */
    private static function collectMicrodataProperties(
        \DOMNode $node,
        array &$properties,
        array &$seenPropertyElements,
        array &$diagnostics,
        ?string $baseHref
    ): void {
        if (!$node instanceof \DOMElement) {
            return;
        }

        $hasItemScope = $node->hasAttribute('itemscope');
        $hasItemProp = $node->hasAttribute('itemprop');
        if ($hasItemProp) {
            $propertyKey = self::microdataElementPath($node);
            if (!isset($seenPropertyElements[$propertyKey])) {
                $seenPropertyElements[$propertyKey] = true;
                if (count($properties) < self::MICRODATA_MAX_PROPERTIES_PER_ITEM) {
                    $properties[] = self::microdataPropertySummary($node, $baseHref);
                } else {
                    $diagnostics[] = 'html-microdata-property-limit-exceeded';
                }
            }
            if ($hasItemScope) {
                return;
            }
        } elseif ($hasItemScope) {
            return;
        }

        foreach ($node->childNodes as $child) {
            self::collectMicrodataProperties($child, $properties, $seenPropertyElements, $diagnostics, $baseHref);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private static function microdataPropertySummary(\DOMElement $element, ?string $baseHref): array
    {
        [$value, $valueSource, $valueType] = self::microdataPropertyValue($element, $baseHref);
        $summary = [
            'elementName' => XmlHtmlDom::htmlElementName($element),
            'itempropRaw' => $element->getAttribute('itemprop'),
            'names' => self::spaceSeparatedTokens($element->getAttribute('itemprop')),
            'value' => self::boundedMicrodataValue($value),
            'valueSource' => $valueSource,
            'valueType' => $valueType,
        ];

        if ($element->hasAttribute('id')) {
            $summary['elementId'] = $element->getAttribute('id');
        }
        if ($element->hasAttribute('itemscope')) {
            $summary['item'] = self::microdataItemReference($element);
        }

        return $summary;
    }

    /**
     * @return array{0: string, 1: string, 2: string}
     */
    private static function microdataPropertyValue(\DOMElement $element, ?string $baseHref): array
    {
        if ($element->hasAttribute('itemscope')) {
            return [Html5Dom::normalizedText($element), 'item', 'item'];
        }

        $name = XmlHtmlDom::htmlElementName($element);
        if ($name === 'meta') {
            return [$element->getAttribute('content'), 'content', 'string'];
        }
        if (in_array($name, ['audio', 'embed', 'iframe', 'img', 'source', 'track', 'video'], true)) {
            return [self::resolveHtmlUrl($element->getAttribute('src'), $baseHref), 'src', 'url'];
        }
        if (in_array($name, ['a', 'area', 'link'], true)) {
            return [self::resolveHtmlUrl($element->getAttribute('href'), $baseHref), 'href', 'url'];
        }
        if ($name === 'object') {
            return [self::resolveHtmlUrl($element->getAttribute('data'), $baseHref), 'data', 'url'];
        }
        if ($name === 'data' || $name === 'meter') {
            return [$element->getAttribute('value'), 'value', 'string'];
        }
        if ($name === 'time' && $element->hasAttribute('datetime')) {
            return [$element->getAttribute('datetime'), 'datetime', 'datetime'];
        }

        return [Html5Dom::normalizedText($element), 'text', 'string'];
    }

    /**
     * @return array<string, mixed>
     */
    private static function microdataItemReference(\DOMElement $element): array
    {
        $reference = [
            'elementName' => XmlHtmlDom::htmlElementName($element),
            'itemTypes' => self::spaceSeparatedTokens($element->getAttribute('itemtype')),
            'itemId' => $element->hasAttribute('itemid') ? $element->getAttribute('itemid') : null,
            'text' => self::boundedMicrodataValue(Html5Dom::normalizedText($element)),
        ];

        if ($element->hasAttribute('id')) {
            $reference['elementId'] = $element->getAttribute('id');
        }

        return $reference;
    }

    /**
     * @param list<array<string, mixed>> $properties
     * @return array<string, int>
     */
    private static function microdataPropertyNameCounts(array $properties): array
    {
        $counts = [];
        foreach ($properties as $property) {
            foreach ($property['names'] as $name) {
                $counts[$name] = ($counts[$name] ?? 0) + 1;
            }
        }

        return $counts;
    }

    /**
     * @return list<string>
     */
    private static function spaceSeparatedTokens(string $value): array
    {
        $value = trim($value);
        if ($value === '') {
            return [];
        }

        $tokens = preg_split('/\s+/u', $value) ?: [];

        return array_values(array_filter($tokens, static fn (string $token): bool => $token !== ''));
    }

    private static function hasAncestorItemScope(\DOMElement $element): bool
    {
        $parent = $element->parentNode;
        while ($parent instanceof \DOMElement) {
            if ($parent->hasAttribute('itemscope')) {
                return true;
            }
            $parent = $parent->parentNode;
        }

        return false;
    }

    private static function microdataElementPath(\DOMElement $element): string
    {
        $segments = [];
        $node = $element;
        while ($node instanceof \DOMElement) {
            $index = 1;
            $sibling = $node->previousSibling;
            while ($sibling instanceof \DOMNode) {
                if ($sibling instanceof \DOMElement) {
                    $index++;
                }
                $sibling = $sibling->previousSibling;
            }
            $segments[] = XmlHtmlDom::htmlElementName($node) . '[' . $index . ']';
            $parent = $node->parentNode;
            $node = $parent instanceof \DOMElement ? $parent : null;
        }

        return implode('/', array_reverse($segments));
    }

    private static function boundedMicrodataValue(string $value): string
    {
        if (strlen($value) <= self::MICRODATA_MAX_VALUE_BYTES) {
            return $value;
        }

        return substr($value, 0, self::MICRODATA_MAX_VALUE_BYTES);
    }

    private static function htmlDocumentBaseHref(\DOMDocument $dom): ?string
    {
        foreach ($dom->getElementsByTagName('base') as $node) {
            if (!$node instanceof \DOMElement) {
                continue;
            }

            $href = trim($node->getAttribute('href'));
            if ($href !== '') {
                return $href;
            }
        }

        return null;
    }

    private static function resolveHtmlUrl(string $url, ?string $baseHref): string
    {
        return XmlHtmlDom::resolveHtmlResourceUrlReference($url, $baseHref) ?? $url;
    }
}
