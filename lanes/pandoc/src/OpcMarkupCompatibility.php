<?php

declare(strict_types=1);

namespace PortLibs\Pandoc;

final class OpcMarkupCompatibility
{
    public const NAMESPACE_URI = 'http://schemas.openxmlformats.org/markup-compatibility/2006';

    private const XMLNS_NAMESPACE_URI = 'http://www.w3.org/2000/xmlns/';

    private function __construct()
    {
    }

    /**
     * @return array<string, true>
     */
    public static function ignorableNamespacesForElement(\DOMElement $element, string $packageNamespace, string $label): array
    {
        $value = $element->getAttributeNS(self::NAMESPACE_URI, 'Ignorable');
        if (trim($value) === '') {
            return [];
        }

        $namespaces = [];
        foreach (preg_split('/\s+/', trim($value)) ?: [] as $prefix) {
            if ($prefix === '' || preg_match('/^[A-Za-z_][A-Za-z0-9._-]*$/D', $prefix) !== 1) {
                throw new \InvalidArgumentException($label . ' mc:Ignorable contains invalid prefix: ' . $prefix);
            }

            $namespace = $element->lookupNamespaceURI($prefix);
            if ($namespace === null || $namespace === '') {
                throw new \InvalidArgumentException($label . ' mc:Ignorable references undeclared prefix: ' . $prefix);
            }

            if ($namespace === $packageNamespace || $namespace === self::NAMESPACE_URI || $namespace === self::XMLNS_NAMESPACE_URI) {
                throw new \InvalidArgumentException($label . ' mc:Ignorable cannot ignore core package namespaces: ' . $prefix);
            }

            $namespaces[$namespace] = true;
        }

        return $namespaces;
    }

    public static function isNamespaceDeclaration(\DOMAttr $attribute): bool
    {
        return $attribute->namespaceURI === self::XMLNS_NAMESPACE_URI;
    }

    public static function isIgnorableDeclaration(\DOMAttr $attribute): bool
    {
        return $attribute->namespaceURI === self::NAMESPACE_URI && $attribute->localName === 'Ignorable';
    }

    public static function isProcessContentDeclaration(\DOMAttr $attribute): bool
    {
        return $attribute->namespaceURI === self::NAMESPACE_URI && $attribute->localName === 'ProcessContent';
    }

    /**
     * @param array<string, true> $ignorableNamespaces
     * @return array<string, true>
     */
    public static function processContentElementsForElement(\DOMElement $element, array $ignorableNamespaces, string $label): array
    {
        $value = $element->getAttributeNS(self::NAMESPACE_URI, 'ProcessContent');
        if (trim($value) === '') {
            return [];
        }

        $elements = [];
        foreach (preg_split('/\s+/', trim($value)) ?: [] as $name) {
            if (preg_match('/^([A-Za-z_][A-Za-z0-9._-]*):([A-Za-z_][A-Za-z0-9._-]*|\*)$/D', $name, $matches) !== 1) {
                throw new \InvalidArgumentException($label . ' mc:ProcessContent contains invalid QName: ' . $name);
            }

            $namespace = $element->lookupNamespaceURI($matches[1]);
            if ($namespace === null || $namespace === '') {
                throw new \InvalidArgumentException($label . ' mc:ProcessContent references undeclared prefix: ' . $matches[1]);
            }

            if (!isset($ignorableNamespaces[$namespace])) {
                throw new \InvalidArgumentException($label . ' mc:ProcessContent must reference ignorable extension namespaces: ' . $matches[1]);
            }

            $elements[$namespace . "\0" . $matches[2]] = true;
        }

        return $elements;
    }

    /**
     * @param array<string, true> $ignorableNamespaces
     * @param array<string, true> $processContentElements
     * @return list<\DOMElement>
     */
    public static function packageChildElements(
        \DOMElement $element,
        string $packageNamespace,
        array $ignorableNamespaces,
        array $processContentElements,
        string $unsupportedElementMessage,
        string $textContentMessage,
    ): array {
        $children = [];

        foreach ($element->childNodes as $child) {
            if ($child instanceof \DOMElement) {
                if ($child->namespaceURI === $packageNamespace) {
                    $children[] = $child;
                    continue;
                }

                if (self::isIgnorableExtensionElement($child, $ignorableNamespaces)) {
                    if (self::shouldProcessContent($child, $processContentElements)) {
                        array_push(
                            $children,
                            ...self::packageChildElements(
                                $child,
                                $packageNamespace,
                                $ignorableNamespaces,
                                $processContentElements,
                                $unsupportedElementMessage,
                                $textContentMessage
                            )
                        );
                    }

                    continue;
                }

                throw new \InvalidArgumentException($unsupportedElementMessage);
            }

            if (($child instanceof \DOMText || $child instanceof \DOMCdataSection) && trim($child->nodeValue ?? '') !== '') {
                throw new \InvalidArgumentException($textContentMessage);
            }
        }

        return $children;
    }

    /**
     * @param array<string, true> $ignorableNamespaces
     */
    public static function isIgnorableExtensionAttribute(\DOMAttr $attribute, array $ignorableNamespaces): bool
    {
        $namespace = $attribute->namespaceURI ?? '';

        return $namespace !== '' && isset($ignorableNamespaces[$namespace]);
    }

    /**
     * @param array<string, true> $ignorableNamespaces
     */
    public static function isIgnorableExtensionElement(\DOMElement $element, array $ignorableNamespaces): bool
    {
        $namespace = $element->namespaceURI ?? '';

        return $namespace !== '' && isset($ignorableNamespaces[$namespace]);
    }

    /**
     * @param array<string, true> $processContentElements
     */
    private static function shouldProcessContent(\DOMElement $element, array $processContentElements): bool
    {
        $namespace = $element->namespaceURI ?? '';

        return isset($processContentElements[$namespace . "\0" . $element->localName])
            || isset($processContentElements[$namespace . "\0*"]);
    }
}
