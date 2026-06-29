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
        $this->reader = new MarkdownReader(array_replace(['htmlNativeDivs' => true], $options));
    }

    public function read(string $bytes): AstNode
    {
        $document = $this->reader->read($bytes);
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
        ], $this->microdataMetadata($bytes));

        return new AstNode('document', $attrs, $document->children);
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
                'htmlMicrodataUrlPropertyCount' => 0,
                'htmlMicrodataExternalUrlPropertyCount' => 0,
                'htmlMicrodataEmptyValueCount' => 0,
                'htmlMicrodataNamelessPropertyCount' => 0,
                'htmlMicrodataTruncatedValueCount' => 0,
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
        $globalUrlPropertyCount = 0;
        $globalExternalUrlPropertyCount = 0;
        $globalEmptyValueCount = 0;
        $globalNamelessPropertyCount = 0;
        $globalTruncatedValueCount = 0;
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
            $globalUrlPropertyCount += (int) $item['urlPropertyCount'];
            $globalExternalUrlPropertyCount += (int) $item['externalUrlPropertyCount'];
            $globalEmptyValueCount += (int) $item['emptyValueCount'];
            $globalNamelessPropertyCount += (int) $item['namelessPropertyCount'];
            $globalTruncatedValueCount += (int) $item['truncatedValueCount'];
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
            'htmlMicrodataUrlPropertyCount' => $globalUrlPropertyCount,
            'htmlMicrodataExternalUrlPropertyCount' => $globalExternalUrlPropertyCount,
            'htmlMicrodataEmptyValueCount' => $globalEmptyValueCount,
            'htmlMicrodataNamelessPropertyCount' => $globalNamelessPropertyCount,
            'htmlMicrodataTruncatedValueCount' => $globalTruncatedValueCount,
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
        $propertyValueCounts = self::microdataPropertyValueCounts($properties);

        $summary = [
            'microdataReviewPolicy' => 'html-microdata-metadata-only',
            'elementName' => XmlHtmlDom::htmlElementName($element),
            'itemTypes' => self::spaceSeparatedTokens($element->getAttribute('itemtype')),
            'itemId' => $element->hasAttribute('itemid') ? $element->getAttribute('itemid') : null,
            'itemrefIds' => $itemrefIds,
            'missingItemrefIds' => $missingItemrefIds,
            'propertyCount' => count($properties),
            'urlPropertyCount' => $propertyValueCounts['urlPropertyCount'],
            'externalUrlPropertyCount' => $propertyValueCounts['externalUrlPropertyCount'],
            'emptyValueCount' => $propertyValueCounts['emptyValueCount'],
            'namelessPropertyCount' => $propertyValueCounts['namelessPropertyCount'],
            'truncatedValueCount' => $propertyValueCounts['truncatedValueCount'],
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
                    [$property, $propertyDiagnostics] = self::microdataPropertySummary($node);
                    $properties[] = $property;
                    array_push($diagnostics, ...$propertyDiagnostics);
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
     * @return array{0: array<string, mixed>, 1: list<string>}
     */
    private static function microdataPropertySummary(\DOMElement $element): array
    {
        [$value, $valueSource, $valueType] = self::microdataPropertyValue($element);
        $boundedValue = self::boundedMicrodataValue($value);
        $summary = [
            'elementName' => XmlHtmlDom::htmlElementName($element),
            'itempropRaw' => $element->getAttribute('itemprop'),
            'names' => self::spaceSeparatedTokens($element->getAttribute('itemprop')),
            'value' => $boundedValue,
            'valueSource' => $valueSource,
            'valueType' => $valueType,
            'valueLengthBytes' => strlen($value),
            'valueTruncated' => $boundedValue !== $value,
            'valueEmpty' => $value === '',
        ];

        if ($valueType === 'url') {
            $summary += self::microdataUrlReview($value);
        }
        if ($element->hasAttribute('id')) {
            $summary['elementId'] = $element->getAttribute('id');
        }
        if ($element->hasAttribute('itemscope')) {
            $summary['item'] = self::microdataItemReference($element);
        }

        return [$summary, self::microdataPropertyDiagnostics($summary)];
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
     * @return array<string, mixed>
     */
    private static function microdataUrlReview(string $value): array
    {
        $trimmed = trim($value);
        if ($trimmed === '') {
            return [
                'valueUrlPolicy' => 'metadata-only-no-fetch',
                'valueUrlKind' => 'empty',
                'valueUrlScheme' => null,
                'valueExternal' => false,
            ];
        }

        if (str_starts_with($trimmed, '//')) {
            return [
                'valueUrlPolicy' => 'metadata-only-no-fetch',
                'valueUrlKind' => 'protocol-relative',
                'valueUrlScheme' => null,
                'valueExternal' => true,
            ];
        }

        if (preg_match('/^([A-Za-z][A-Za-z0-9+.-]*):/', $trimmed, $matches) === 1) {
            $scheme = strtolower($matches[1]);

            return [
                'valueUrlPolicy' => 'metadata-only-no-fetch',
                'valueUrlKind' => in_array($scheme, ['http', 'https'], true) ? 'absolute-http' : 'absolute-non-http',
                'valueUrlScheme' => $scheme,
                'valueExternal' => true,
            ];
        }

        return [
            'valueUrlPolicy' => 'metadata-only-no-fetch',
            'valueUrlKind' => str_starts_with($trimmed, '/') ? 'root-relative' : 'relative',
            'valueUrlScheme' => null,
            'valueExternal' => false,
        ];
    }

    /**
     * @param array<string, mixed> $summary
     * @return list<string>
     */
    private static function microdataPropertyDiagnostics(array $summary): array
    {
        $diagnostics = [];
        $label = self::microdataDiagnosticPropertyLabel($summary);

        if (($summary['names'] ?? []) === []) {
            $diagnostics[] = 'html-microdata-property-without-name';
        }
        if (($summary['valueEmpty'] ?? false) === true) {
            $diagnostics[] = 'html-microdata-empty-property-value:' . $label;
        }
        if (($summary['valueTruncated'] ?? false) === true) {
            $diagnostics[] = 'html-microdata-property-value-truncated:' . $label;
        }
        if (($summary['valueType'] ?? null) === 'url' && ($summary['valueUrlKind'] ?? null) === 'absolute-non-http') {
            $diagnostics[] = 'html-microdata-url-non-http:' . $label;
        }

        return $diagnostics;
    }

    /**
     * @param array<string, mixed> $summary
     */
    private static function microdataDiagnosticPropertyLabel(array $summary): string
    {
        $names = $summary['names'] ?? [];
        if (is_array($names) && isset($names[0]) && is_string($names[0]) && $names[0] !== '') {
            return $names[0];
        }

        $elementName = $summary['elementName'] ?? 'property';

        return is_string($elementName) && $elementName !== '' ? $elementName : 'property';
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
     * @param list<array<string, mixed>> $properties
     * @return array{
     *     urlPropertyCount: int,
     *     externalUrlPropertyCount: int,
     *     emptyValueCount: int,
     *     namelessPropertyCount: int,
     *     truncatedValueCount: int
     * }
     */
    private static function microdataPropertyValueCounts(array $properties): array
    {
        $counts = [
            'urlPropertyCount' => 0,
            'externalUrlPropertyCount' => 0,
            'emptyValueCount' => 0,
            'namelessPropertyCount' => 0,
            'truncatedValueCount' => 0,
        ];

        foreach ($properties as $property) {
            if (($property['valueType'] ?? null) === 'url') {
                $counts['urlPropertyCount']++;
            }
            if (($property['valueExternal'] ?? false) === true) {
                $counts['externalUrlPropertyCount']++;
            }
            if (($property['valueEmpty'] ?? false) === true) {
                $counts['emptyValueCount']++;
            }
            if (($property['names'] ?? []) === []) {
                $counts['namelessPropertyCount']++;
            }
            if (($property['valueTruncated'] ?? false) === true) {
                $counts['truncatedValueCount']++;
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
