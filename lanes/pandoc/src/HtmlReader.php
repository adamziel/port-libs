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
        $document = $this->reader->read($bytes);
        [$children, $consumedFootnoteContainerCount] = self::containsAstNodeType($document->children, 'note')
            ? self::stripConsumedHtmlFootnoteContainers($document->children)
            : [$document->children, 0];
        $attrs = $document->attrs;
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
            [$item, $itemDiagnostics] = self::microdataItemSummary($element, $idIndex);
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
    private static function microdataItemSummary(\DOMElement $element, array $idIndex): array
    {
        $properties = [];
        $seenPropertyElements = [];
        $diagnostics = [];
        foreach ($element->childNodes as $child) {
            self::collectMicrodataProperties($child, $properties, $seenPropertyElements, $diagnostics);
        }

        $itemrefIds = self::spaceSeparatedTokens($element->getAttribute('itemref'));
        $missingItemrefIds = [];
        foreach ($itemrefIds as $id) {
            if (!isset($idIndex[$id])) {
                $missingItemrefIds[] = $id;
                $diagnostics[] = 'missing-itemref:' . $id;
                continue;
            }

            self::collectMicrodataProperties($idIndex[$id], $properties, $seenPropertyElements, $diagnostics);
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
        array &$diagnostics
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
                    $properties[] = self::microdataPropertySummary($node);
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
            self::collectMicrodataProperties($child, $properties, $seenPropertyElements, $diagnostics);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private static function microdataPropertySummary(\DOMElement $element): array
    {
        [$value, $valueSource, $valueType] = self::microdataPropertyValue($element);
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
    private static function microdataPropertyValue(\DOMElement $element): array
    {
        if ($element->hasAttribute('itemscope')) {
            return [Html5Dom::normalizedText($element), 'item', 'item'];
        }

        $name = XmlHtmlDom::htmlElementName($element);
        if ($name === 'meta') {
            return [$element->getAttribute('content'), 'content', 'string'];
        }
        if (in_array($name, ['audio', 'embed', 'iframe', 'img', 'source', 'track', 'video'], true)) {
            return [$element->getAttribute('src'), 'src', 'url'];
        }
        if (in_array($name, ['a', 'area', 'link'], true)) {
            return [$element->getAttribute('href'), 'href', 'url'];
        }
        if ($name === 'object') {
            return [$element->getAttribute('data'), 'data', 'url'];
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
}
