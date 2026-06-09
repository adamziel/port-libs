<?php

declare(strict_types=1);

namespace PortLibs\Pandoc;

final class XmlHtml5Dom
{
    private const HTML_FLAGS = LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING;
    private const XML_FLAGS = LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING;

    public static function parseHtmlDocument(string $html): ?\DOMDocument
    {
        return self::loadHtml('<?xml encoding="UTF-8">' . $html);
    }

    public static function parseHtmlDocumentBody(string $html): ?\DOMElement
    {
        $document = self::parseHtmlDocument($html);

        return $document instanceof \DOMDocument ? self::htmlBody($document) : null;
    }

    public static function parseHtmlFragment(string $html): ?\DOMDocument
    {
        return self::loadHtml('<?xml encoding="UTF-8"><!doctype html><html><body>' . $html . '</body></html>');
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
        if (preg_match('/<!DOCTYPE\b/i', $xml) === 1) {
            throw new \InvalidArgumentException($label . ' must not declare a doctype');
        }

        $previous = libxml_use_internal_errors(true);
        $document = new \DOMDocument('1.0', 'UTF-8');
        $document->resolveExternals = false;
        $document->substituteEntities = false;

        try {
            $loaded = $document->loadXML($xml, self::XML_FLAGS);
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }

        if (!$loaded || !$document->documentElement instanceof \DOMElement) {
            throw new \InvalidArgumentException('Unable to parse ' . $label);
        }

        return $document;
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
            $html = '';
            foreach ($node->childNodes as $child) {
                $serialized = $document->saveHTML($child);
                if ($serialized === false) {
                    throw new \RuntimeException('Failed to serialize HTML fragment node');
                }

                $html .= $serialized;
            }

            return $html;
        }

        $html = $document->saveHTML($node);
        if ($html === false) {
            throw new \RuntimeException('Failed to serialize HTML fragment node');
        }

        return $html;
    }

    private static function loadHtml(string $source): ?\DOMDocument
    {
        $previous = libxml_use_internal_errors(true);
        $document = new \DOMDocument('1.0', 'UTF-8');
        $document->resolveExternals = false;
        $document->substituteEntities = false;

        try {
            $loaded = $document->loadHTML($source, self::HTML_FLAGS);
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }

        return $loaded ? $document : null;
    }
}
