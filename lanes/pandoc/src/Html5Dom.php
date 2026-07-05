<?php

declare(strict_types=1);

namespace PortLibs\Pandoc;

final class Html5Dom
{
    public const HTML_TREE_CONSTRUCTION_HTML_DOCUMENT = 'Dom\\HTMLDocument';
    public const HTML_TREE_CONSTRUCTION_LEGACY_COMPAT = 'DOMDocument-loadHTML-compat';

    public static function htmlDocumentTreeConstructionBackend(): string
    {
        return self::nativeHtmlDocumentAvailable()
            ? self::HTML_TREE_CONSTRUCTION_HTML_DOCUMENT
            : self::HTML_TREE_CONSTRUCTION_LEGACY_COMPAT;
    }

    public static function htmlFragmentTreeConstructionBackend(string $html): string
    {
        return self::nativeHtmlDocumentAvailable() && !self::hasOrphanTableScopeFragment($html)
            ? self::HTML_TREE_CONSTRUCTION_HTML_DOCUMENT
            : self::HTML_TREE_CONSTRUCTION_LEGACY_COMPAT;
    }

    public static function nativeHtmlDocumentAvailable(): bool
    {
        return class_exists('Dom\\HTMLDocument');
    }

    /**
     * Parse a bounded HTML fragment under a synthetic body element.
     */
    public static function parseHtmlFragment(string $html): \DOMElement
    {
        self::assertNoNullByte($html, 'HTML fragment');
        $preflight = XmlHtmlDom::protectHtmlRcdataElements(
            $html,
            protectTemplateContent: true,
            protectIframeContent: true,
            protectRawTextContent: true,
            protectNoscriptContent: true
        );
        self::assertNoHtmlFragmentDeclarations($preflight, 'HTML fragment');
        $html = XmlHtmlDom::protectHtmlRcdataElements(
            $html,
            protectTemplateContent: true,
            protectIframeContent: true,
            protectNoscriptContent: true
        );

        $dom = self::loadHtml(
            '<!doctype html><html><body>' . $html . '</body></html>',
            'HTML fragment',
            protectRcdata: false,
            preferHtml5TreeConstruction: !self::hasOrphanTableScopeFragment($html)
        );

        return self::requireBody($dom, 'HTML fragment');
    }

    /**
     * Parse a complete HTML document while keeping libxml network access off.
     */
    public static function parseHtmlDocument(string $html): \DOMDocument
    {
        self::assertSafeHtmlDocumentSource($html, 'HTML document');

        return self::loadHtml($html, 'HTML document');
    }

    public static function treeConstructedHtmlSource(string $html): ?string
    {
        return self::html5TreeConstructedSource($html);
    }

    /**
     * Parse one or more XML fragment roots under a synthetic wrapper element.
     */
    public static function parseXmlFragment(string $xml, string $wrapperName = 'pandoc-fragment'): \DOMElement
    {
        if (preg_match('/^[A-Za-z_][A-Za-z0-9_.-]*$/', $wrapperName) !== 1) {
            throw new \InvalidArgumentException('XML fragment wrapper name must be a simple XML name');
        }

        self::assertSafeXmlSource($xml, 'XML fragment');
        $dom = self::loadXml('<' . $wrapperName . '>' . $xml . '</' . $wrapperName . '>', 'XML fragment');
        $root = $dom->documentElement;
        if (!$root instanceof \DOMElement) {
            throw new \RuntimeException('XML fragment parser did not produce a wrapper element');
        }

        return $root;
    }

    /**
     * Parse one complete XML document without external entity expansion.
     */
    public static function parseXmlDocument(string $xml, string $label = 'XML document'): \DOMDocument
    {
        self::assertSafeXmlDocumentSource($xml, $label);

        return self::loadXml($xml, $label);
    }

    public static function serializeHtmlChildren(\DOMElement $element): string
    {
        $document = $element->ownerDocument;
        if (!$document instanceof \DOMDocument) {
            throw new \InvalidArgumentException('HTML element must belong to a DOMDocument');
        }

        return XmlHtmlDom::serializeHtmlChildren($element);
    }

    public static function serializeXmlChildren(\DOMElement $element): string
    {
        $document = $element->ownerDocument;
        if (!$document instanceof \DOMDocument) {
            throw new \InvalidArgumentException('XML element must belong to a DOMDocument');
        }

        $xml = '';
        foreach ($element->childNodes as $child) {
            $serialized = $document->saveXML($child);
            if ($serialized === false) {
                throw new \RuntimeException('Failed to serialize XML fragment child');
            }
            $xml .= $serialized;
        }

        return $xml;
    }

    /**
     * @return list<\DOMElement>
     */
    public static function childElements(\DOMElement $element, ?string $localName = null, ?string $namespace = null): array
    {
        $children = [];
        foreach ($element->childNodes as $child) {
            if (!$child instanceof \DOMElement) {
                continue;
            }
            if (!self::elementMatches($child, $localName, $namespace)) {
                continue;
            }

            $children[] = $child;
        }

        return $children;
    }

    public static function firstChildElement(\DOMElement $element, ?string $localName = null, ?string $namespace = null): ?\DOMElement
    {
        foreach (self::childElements($element, $localName, $namespace) as $child) {
            return $child;
        }

        return null;
    }

    /**
     * @return list<\DOMElement>
     */
    public static function descendantElements(\DOMElement $element, ?string $localName = null, ?string $namespace = null): array
    {
        $descendants = [];
        foreach ($element->getElementsByTagName('*') as $child) {
            if (!$child instanceof \DOMElement || !self::elementMatches($child, $localName, $namespace)) {
                continue;
            }

            $descendants[] = $child;
        }

        return $descendants;
    }

    /**
     * @return array<string, string>
     */
    public static function attributes(\DOMElement $element): array
    {
        $attributes = [];
        $isHtmlForeignElement = self::isHtmlDocumentElement($element) && self::isHtmlForeignElement($element);
        foreach ($element->attributes as $attribute) {
            if ($attribute instanceof \DOMAttr) {
                $name = self::isHtmlDocumentElement($element)
                    ? strtolower($attribute->name)
                    : ($attribute->prefix !== '' ? $attribute->prefix . ':' . $attribute->localName : $attribute->name);
                if ($isHtmlForeignElement) {
                    $name = XmlHtmlDom::adjustHtmlForeignAttributeName($name);
                }
                $attributes[$name] = $attribute->value;
            }
        }

        return $attributes;
    }

    public static function normalizedText(\DOMNode $node): string
    {
        $raw = self::textForNormalization($node);
        $text = preg_replace('/\s+/u', ' ', $raw) ?? $raw;

        return trim($text);
    }

    private static function loadHtml(
        string $html,
        string $label,
        bool $protectRcdata = true,
        bool $preferHtml5TreeConstruction = true
    ): \DOMDocument
    {
        if ($protectRcdata) {
            $html = XmlHtmlDom::protectHtmlRcdataElements(
                $html,
                protectTemplateContent: true,
                protectIframeContent: true,
                protectNoscriptContent: true
            );
        }

        if ($preferHtml5TreeConstruction && self::nativeHtmlDocumentAvailable()) {
            $html5 = self::treeConstructedHtmlSource($html);
            if ($html5 === null) {
                throw new \RuntimeException('Unable to parse ' . $label . ' through Dom\\HTMLDocument');
            }

            try {
                return self::loadLegacyHtml($html5, $label);
            } catch (\Throwable $exception) {
                throw new \RuntimeException('Unable to bridge Dom\\HTMLDocument output for ' . $label, 0, $exception);
            }
        }

        // PHP < 8.4 has no Dom\HTMLDocument; orphan table-scope fragments also
        // use this compatibility path because PHP exposes document parsing, not
        // context-fragment parsing for table rows/cells.
        return self::loadLegacyHtml($html, $label);
    }

    private static function loadLegacyHtml(string $html, string $label): \DOMDocument
    {
        $previous = libxml_use_internal_errors(true);
        $dom = new \DOMDocument('1.0', 'UTF-8');
        $dom->resolveExternals = false;
        $dom->substituteEntities = false;
        $loaded = $dom->loadHTML(
            '<?xml encoding="UTF-8">' . $html,
            LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING
        );
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        if (!$loaded) {
            throw new \RuntimeException('Unable to parse ' . $label);
        }

        return $dom;
    }

    private static function hasOrphanTableScopeFragment(string $html): bool
    {
        $hasTableScopeElement = false;
        foreach (self::htmlStartTagNames($html) as $name) {
            if ($name === 'table') {
                return false;
            }
            if (in_array($name, ['caption', 'col', 'colgroup', 'tbody', 'td', 'tfoot', 'th', 'thead', 'tr'], true)) {
                $hasTableScopeElement = true;
            }
        }

        return $hasTableScopeElement;
    }

    /**
     * @return list<string>
     */
    private static function htmlStartTagNames(string $html): array
    {
        $names = [];
        $offset = 0;
        $length = strlen($html);
        while ($offset < $length) {
            $tagStart = strpos($html, '<', $offset);
            if ($tagStart === false || $tagStart + 1 >= $length) {
                break;
            }

            $next = $html[$tagStart + 1];
            if ($next === '/' || $next === '!' || $next === '?') {
                $offset = $tagStart + 2;
                continue;
            }

            $nameEnd = $tagStart + 1;
            while ($nameEnd < $length && self::isHtmlTagNameChar($html[$nameEnd])) {
                $nameEnd++;
            }
            if ($nameEnd > $tagStart + 1) {
                $names[] = strtolower(substr($html, $tagStart + 1, $nameEnd - $tagStart - 1));
            }
            $tagEnd = self::htmlTagSourceEndOffset($html, $tagStart);
            $offset = $tagEnd === null ? $nameEnd : $tagEnd + 1;
        }

        return $names;
    }

    private static function htmlTagSourceEndOffset(string $html, int $start): ?int
    {
        $quote = null;
        $length = strlen($html);
        for ($offset = $start + 1; $offset < $length; $offset++) {
            $char = $html[$offset];
            if ($quote !== null) {
                if ($char === $quote) {
                    $quote = null;
                }
                continue;
            }

            if ($char === '"' || $char === "'") {
                $quote = $char;
                continue;
            }

            if ($char === '>') {
                return $offset;
            }
        }

        return null;
    }

    private static function isHtmlTagNameChar(string $char): bool
    {
        return ctype_alpha($char) || ctype_digit($char) || in_array($char, ['-', ':'], true);
    }

    private static function html5TreeConstructedSource(string $html): ?string
    {
        if (!self::nativeHtmlDocumentAvailable()) {
            return null;
        }

        $previous = libxml_use_internal_errors(true);
        try {
            $document = \Dom\HTMLDocument::createFromString(
                $html,
                LIBXML_NOERROR | LIBXML_COMPACT,
                'UTF-8'
            );

            return $document->saveHtml();
        } catch (\Throwable) {
            return null;
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
    }

    private static function loadXml(string $xml, string $label): \DOMDocument
    {
        $previous = libxml_use_internal_errors(true);
        $dom = new \DOMDocument('1.0', 'UTF-8');
        $dom->resolveExternals = false;
        $dom->substituteEntities = false;
        $loaded = $dom->loadXML($xml, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        if (!$loaded) {
            throw new \RuntimeException('Unable to parse ' . $label);
        }

        self::assertNoProcessingInstructions($dom, $label);

        return $dom;
    }

    private static function requireBody(\DOMDocument $dom, string $label): \DOMElement
    {
        $body = $dom->getElementsByTagName('body')->item(0);
        if (!$body instanceof \DOMElement) {
            throw new \RuntimeException($label . ' parser did not produce a body element');
        }

        return $body;
    }

    private static function elementMatches(\DOMElement $element, ?string $localName, ?string $namespace): bool
    {
        if ($localName !== null) {
            $matchesRawName = $element->localName === $localName;
            $matchesAdjustedName = self::isHtmlDocumentElement($element)
                && XmlHtmlDom::htmlElementName($element) === $localName;
            if (!$matchesRawName && !$matchesAdjustedName) {
                return false;
            }
        }

        if ($namespace !== null && $element->namespaceURI !== $namespace) {
            return false;
        }

        return true;
    }

    private static function assertSafeXmlSource(string $xml, string $label): void
    {
        self::assertNoNullByte($xml, $label);
        $declarationScanSource = self::sourceForDeclarationScan($xml);
        if (preg_match('/<\?xml\b/i', $declarationScanSource) === 1) {
            throw new \InvalidArgumentException($label . ' must not include an XML declaration');
        }
        if (preg_match('/<\?[A-Za-z_][A-Za-z0-9_.:-]*/', $declarationScanSource) === 1) {
            throw new \InvalidArgumentException($label . ' must not include processing instructions');
        }
        if (preg_match('/<!\s*(?:DOCTYPE|ENTITY|ELEMENT|ATTLIST|NOTATION)\b/i', $declarationScanSource) === 1) {
            throw new \InvalidArgumentException($label . ' must not declare DTDs or entities');
        }
    }

    private static function assertSafeXmlDocumentSource(string $xml, string $label): void
    {
        self::assertNoNullByte($xml, $label);
        $declarationScanSource = self::sourceForDeclarationScan($xml);
        if (preg_match('/<!\s*(?:DOCTYPE|ENTITY|ELEMENT|ATTLIST|NOTATION)\b/i', $declarationScanSource) === 1) {
            throw new \InvalidArgumentException($label . ' must not declare DTDs or entities');
        }
    }

    private static function assertNoHtmlFragmentDeclarations(string $html, string $label): void
    {
        $declarationScanSource = self::sourceForDeclarationScan($html);
        if (preg_match('/<!\s*(?:DOCTYPE|ENTITY|ELEMENT|ATTLIST|NOTATION)\b/i', $declarationScanSource) === 1) {
            throw new \InvalidArgumentException($label . ' must not declare DTDs or entities');
        }
        if (preg_match('/<\?[A-Za-z_][A-Za-z0-9_.:-]*/', $declarationScanSource) === 1) {
            throw new \InvalidArgumentException($label . ' must not include processing instructions');
        }
    }

    private static function assertSafeHtmlDocumentSource(string $html, string $label): void
    {
        self::assertNoNullByte($html, $label);
        $preflight = XmlHtmlDom::protectHtmlRcdataElements(
            $html,
            protectTemplateContent: true,
            protectIframeContent: true,
            protectRawTextContent: true,
            protectNoscriptContent: true
        );
        $declarationScanSource = self::sourceForDeclarationScan($preflight);
        self::assertSimpleHtmlDocumentDoctype($declarationScanSource, $label);
        if (preg_match('/<!\s*DOCTYPE\b[^>]*\[/is', $declarationScanSource) === 1) {
            throw new \InvalidArgumentException($label . ' must not declare DTDs or entities');
        }
        if (preg_match('/<!\s*(?:ENTITY|ELEMENT|ATTLIST|NOTATION)\b/i', $declarationScanSource) === 1) {
            throw new \InvalidArgumentException($label . ' must not declare DTDs or entities');
        }
        if (preg_match('/<\?[A-Za-z_][A-Za-z0-9_.:-]*/', $declarationScanSource) === 1) {
            throw new \InvalidArgumentException($label . ' must not include processing instructions');
        }
    }

    private static function sourceForDeclarationScan(string $source): string
    {
        $length = strlen($source);
        $scan = '';
        $offset = 0;

        while ($offset < $length) {
            if (str_starts_with(substr($source, $offset, 9), '<![CDATA[')) {
                $cdataEnd = strpos($source, ']]>', $offset + 9);
                if ($cdataEnd === false) {
                    $scan .= substr($source, $offset);
                    break;
                }

                $cdataLength = $cdataEnd + 3 - $offset;
                $scan .= str_repeat(' ', $cdataLength);
                $offset += $cdataLength;
                continue;
            }

            if (str_starts_with(substr($source, $offset, 4), '<!--')) {
                $commentEnd = strpos($source, '-->', $offset + 4);
                if ($commentEnd === false) {
                    $scan .= substr($source, $offset);
                    break;
                }

                $commentLength = $commentEnd + 3 - $offset;
                $scan .= str_repeat(' ', $commentLength);
                $offset += $commentLength;
                continue;
            }

            if ($source[$offset] === '<' && self::isHtmlTagStartForDeclarationScan($source, $offset)) {
                [$tagSource, $nextOffset] = self::maskQuotedTagAttributeValuesForDeclarationScan($source, $offset);
                $scan .= $tagSource;
                $offset = $nextOffset;
                continue;
            }

            $scan .= $source[$offset];
            ++$offset;
        }

        return $scan;
    }

    private static function isHtmlTagStartForDeclarationScan(string $source, int $offset): bool
    {
        $length = strlen($source);
        $nameOffset = $offset + 1;
        if ($nameOffset >= $length) {
            return false;
        }

        if ($source[$nameOffset] === '/') {
            ++$nameOffset;
        }

        return $nameOffset < $length && preg_match('/[A-Za-z]/', $source[$nameOffset]) === 1;
    }

    /**
     * @return array{0:string, 1:int}
     */
    private static function maskQuotedTagAttributeValuesForDeclarationScan(string $source, int $offset): array
    {
        $length = strlen($source);
        $tag = '';

        while ($offset < $length) {
            $char = $source[$offset];
            if ($char === '"' || $char === "'") {
                $quoteEnd = strpos($source, $char, $offset + 1);
                if ($quoteEnd === false) {
                    $tag .= substr($source, $offset);

                    return [$tag, $length];
                }

                $tag .= $char . str_repeat(' ', $quoteEnd - $offset - 1) . $char;
                $offset = $quoteEnd + 1;
                continue;
            }

            $tag .= $char;
            ++$offset;
            if ($char === '>') {
                break;
            }
        }

        return [$tag, $offset];
    }

    private static function assertSimpleHtmlDocumentDoctype(string $html, string $label): void
    {
        $doctypeCount = preg_match_all('/<!\s*DOCTYPE\b([^>]*)>/is', $html, $matches);
        if ($doctypeCount === false) {
            return;
        }

        if (preg_match('/<!\s*DOCTYPE\b/is', $html) === 1 && $doctypeCount === 0) {
            throw new \InvalidArgumentException($label . ' must use a complete simple HTML doctype');
        }
        if ($doctypeCount > 1) {
            throw new \InvalidArgumentException($label . ' must not declare multiple doctypes');
        }
        if ($doctypeCount === 0) {
            return;
        }

        $doctypeName = preg_replace('/\s+/u', ' ', trim((string) $matches[1][0])) ?? trim((string) $matches[1][0]);
        if (strcasecmp($doctypeName, 'html') !== 0) {
            throw new \InvalidArgumentException($label . ' must use a simple HTML doctype without external identifiers or subsets');
        }
    }

    private static function assertNoNullByte(string $source, string $label): void
    {
        if (str_contains($source, "\0")) {
            throw new \InvalidArgumentException($label . ' must not contain NUL bytes');
        }
    }

    private static function assertNoProcessingInstructions(\DOMNode $node, string $label): void
    {
        if ($node instanceof \DOMProcessingInstruction) {
            throw new \InvalidArgumentException($label . ' must not include processing instructions');
        }

        foreach ($node->childNodes as $child) {
            self::assertNoProcessingInstructions($child, $label);
        }
    }

    private static function isHtmlDocumentElement(\DOMElement $element): bool
    {
        $document = $element->ownerDocument;
        $root = $document instanceof \DOMDocument ? $document->documentElement : null;

        return $root instanceof \DOMElement && strtolower($root->localName) === 'html';
    }

    private static function isHtmlForeignElement(\DOMElement $element): bool
    {
        $node = $element;
        $child = null;
        $isSelf = true;
        while ($node instanceof \DOMElement) {
            $name = strtolower($node->localName);
            if (!$isSelf && self::isHtmlIntegrationPoint($node)) {
                if (
                    !self::isMathMlTextIntegrationPointName($name)
                    || !$child instanceof \DOMElement
                    || !self::isMathMlTextIntegrationExceptionName(strtolower($child->localName))
                ) {
                    return false;
                }
            }
            if ($name === 'svg' || $name === 'math') {
                return true;
            }
            if ($name === 'html' || $name === 'body') {
                return false;
            }

            $parent = $node->parentNode;
            $child = $node;
            $node = $parent instanceof \DOMElement ? $parent : null;
            $isSelf = false;
        }

        return false;
    }

    private static function isHtmlIntegrationPoint(\DOMElement $element): bool
    {
        $name = strtolower($element->localName);
        if (self::isSvgHtmlIntegrationPointName($name)) {
            return true;
        }
        if (self::isMathMlTextIntegrationPointName($name)) {
            return true;
        }
        if ($name !== 'annotation-xml') {
            return false;
        }

        $encoding = strtolower(trim($element->getAttribute('encoding')));

        return $encoding === 'text/html' || $encoding === 'application/xhtml+xml';
    }

    private static function isSvgHtmlIntegrationPointName(string $name): bool
    {
        return in_array($name, ['foreignobject', 'desc', 'title'], true);
    }

    private static function isMathMlTextIntegrationPointName(string $name): bool
    {
        return in_array($name, ['mi', 'mn', 'mo', 'ms', 'mtext'], true);
    }

    private static function isMathMlTextIntegrationExceptionName(string $name): bool
    {
        return $name === 'mglyph' || $name === 'malignmark';
    }

    private static function textForNormalization(\DOMNode $node): string
    {
        if ($node instanceof \DOMText || $node instanceof \DOMCdataSection) {
            return $node->nodeValue ?? '';
        }

        if ($node instanceof \DOMElement && strtolower($node->localName) === 'br') {
            return "\n";
        }

        $text = '';
        foreach ($node->childNodes as $child) {
            $text .= self::textForNormalization($child);
        }

        return $text;
    }
}
