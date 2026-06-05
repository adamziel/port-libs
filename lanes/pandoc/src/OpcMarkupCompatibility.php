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
}
