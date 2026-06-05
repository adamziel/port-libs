<?php

declare(strict_types=1);

namespace PortLibs\Pandoc;

final class Html5Dom
{
    /**
     * Parse a bounded HTML fragment under a synthetic body element.
     */
    public static function parseHtmlFragment(string $html): \DOMElement
    {
        self::assertNoNullByte($html, 'HTML fragment');
        self::assertNoHtmlFragmentDeclarations($html, 'HTML fragment');

        $dom = self::loadHtml(
            '<!doctype html><html><body>' . $html . '</body></html>',
            'HTML fragment'
        );

        return self::requireBody($dom, 'HTML fragment');
    }

    /**
     * Parse a complete HTML document while keeping libxml network access off.
     */
    public static function parseHtmlDocument(string $html): \DOMDocument
    {
        self::assertNoNullByte($html, 'HTML document');

        return self::loadHtml($html, 'HTML document');
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

        $html = '';
        foreach ($element->childNodes as $child) {
            $html .= XmlHtmlDom::serializeHtmlNode($child);
        }

        return $html;
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

    private static function loadHtml(string $html, string $label): \DOMDocument
    {
        $previous = libxml_use_internal_errors(true);
        $dom = new \DOMDocument('1.0', 'UTF-8');
        $dom->resolveExternals = false;
        $dom->substituteEntities = false;
        $html = XmlHtmlDom::protectHtmlRcdataElements($html);
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
        if (preg_match('/<\?xml\b/i', $xml) === 1) {
            throw new \InvalidArgumentException($label . ' must not include an XML declaration');
        }
        if (preg_match('/<\?[A-Za-z_][A-Za-z0-9_.:-]*/', $xml) === 1) {
            throw new \InvalidArgumentException($label . ' must not include processing instructions');
        }
        if (preg_match('/<!\s*DOCTYPE\b/i', $xml) === 1) {
            throw new \InvalidArgumentException($label . ' must not declare a doctype');
        }
        if (preg_match('/<!\s*ENTITY\b/i', $xml) === 1) {
            throw new \InvalidArgumentException($label . ' must not declare entities');
        }
    }

    private static function assertSafeXmlDocumentSource(string $xml, string $label): void
    {
        self::assertNoNullByte($xml, $label);
        if (preg_match('/<!\s*DOCTYPE\b/i', $xml) === 1) {
            throw new \InvalidArgumentException($label . ' must not declare a doctype');
        }
        if (preg_match('/<!\s*ENTITY\b/i', $xml) === 1) {
            throw new \InvalidArgumentException($label . ' must not declare entities');
        }
    }

    private static function assertNoHtmlFragmentDeclarations(string $html, string $label): void
    {
        if (preg_match('/<!\s*(?:DOCTYPE|ENTITY|ELEMENT|ATTLIST|NOTATION)\b/i', $html) === 1) {
            throw new \InvalidArgumentException($label . ' must not declare DTDs or entities');
        }
        if (preg_match('/<\?[A-Za-z_][A-Za-z0-9_.:-]*/', $html) === 1) {
            throw new \InvalidArgumentException($label . ' must not include processing instructions');
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
        $isSelf = true;
        while ($node instanceof \DOMElement) {
            $name = strtolower($node->localName);
            if (!$isSelf && self::isHtmlIntegrationPoint($node)) {
                return false;
            }
            if ($name === 'svg' || $name === 'math') {
                return true;
            }
            if ($name === 'html' || $name === 'body') {
                return false;
            }

            $parent = $node->parentNode;
            $node = $parent instanceof \DOMElement ? $parent : null;
            $isSelf = false;
        }

        return false;
    }

    private static function isHtmlIntegrationPoint(\DOMElement $element): bool
    {
        $name = strtolower($element->localName);
        if ($name === 'foreignobject') {
            return true;
        }
        if ($name !== 'annotation-xml') {
            return false;
        }

        $encoding = strtolower(trim($element->getAttribute('encoding')));

        return $encoding === 'text/html' || $encoding === 'application/xhtml+xml';
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
