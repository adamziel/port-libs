<?php

declare(strict_types=1);

namespace PortLibs\Pandoc;

final class XmlHtml5Dom
{
    public static function parseHtmlDocument(string $html): ?\DOMDocument
    {
        try {
            return Html5Dom::parseHtmlDocument($html);
        } catch (\RuntimeException) {
            return null;
        }
    }

    public static function parseHtmlDocumentBody(string $html): ?\DOMElement
    {
        $document = self::parseHtmlDocument($html);

        return $document instanceof \DOMDocument ? self::htmlBody($document) : null;
    }

    public static function parseHtmlFragment(string $html): ?\DOMDocument
    {
        try {
            $body = Html5Dom::parseHtmlFragment($html);
        } catch (\RuntimeException) {
            return null;
        }

        return $body->ownerDocument instanceof \DOMDocument ? $body->ownerDocument : null;
    }

    public static function parseHtmlFragmentBody(string $html): ?\DOMElement
    {
        $document = self::parseHtmlFragment($html);

        return $document instanceof \DOMDocument ? self::htmlBody($document) : null;
    }

    public static function htmlBody(\DOMDocument $document): ?\DOMElement
    {
        $body = $document->getElementsByTagName('body')->item(0);

        return $body instanceof \DOMElement ? $body : null;
    }

    public static function parseXmlDocument(string $xml, string $label = 'XML'): \DOMDocument
    {
        return XmlHtmlDom::loadXmlDocument($xml, $label);
    }

    public static function serializeHtmlFragment(\DOMNode $node): string
    {
        if ($node instanceof \DOMDocument) {
            $body = self::htmlBody($node);

            return $body instanceof \DOMElement ? self::serializeHtmlFragment($body) : '';
        }

        $document = $node->ownerDocument;
        if (!$document instanceof \DOMDocument) {
            throw new \InvalidArgumentException('HTML fragment node must belong to a DOMDocument');
        }

        if ($node instanceof \DOMElement && strtolower($node->localName) === 'body') {
            return Html5Dom::serializeHtmlChildren($node);
        }

        return XmlHtmlDom::serializeHtmlNode($node);
    }
}
