<?php

declare(strict_types=1);

namespace PortLibs\Pandoc;

final class OpcContentTypes
{
    public const NAMESPACE_URI = 'http://schemas.openxmlformats.org/package/2006/content-types';

    /** @var array<string, array{extension:string, contentType:string}> */
    private array $defaults = [];

    /** @var array<string, string> */
    private array $overrides = [];

    /** @var array<string, string> */
    private array $overridePartNamesByEquivalenceKey = [];

    public static function fromXml(string $xml): self
    {
        $dom = self::loadXml($xml);
        $root = $dom->documentElement;
        if (!$root instanceof \DOMElement || $root->localName !== 'Types' || $root->namespaceURI !== self::NAMESPACE_URI) {
            throw new \InvalidArgumentException('OPC content-types XML must use the package content-types namespace');
        }

        $ignorableNamespaces = OpcMarkupCompatibility::ignorableNamespacesForElement($root, self::NAMESPACE_URI, 'OPC content-types XML root');
        self::assertRootShape($root, $ignorableNamespaces);

        $types = new self();
        foreach ($root->childNodes as $child) {
            if (!$child instanceof \DOMElement) {
                continue;
            }

            if ($child->namespaceURI !== self::NAMESPACE_URI) {
                if (OpcMarkupCompatibility::isIgnorableExtensionElement($child, $ignorableNamespaces)) {
                    continue;
                }

                throw new \InvalidArgumentException('OPC content-types children must use the package namespace');
            }

            if ($child->localName === 'Default') {
                self::assertRecordShape($child, ['Extension', 'ContentType'], 'OPC Default content-type record', $ignorableNamespaces);
                self::assertXmlDefaultExtension($child->getAttribute('Extension'));
                $types->addDefault($child->getAttribute('Extension'), $child->getAttribute('ContentType'));
                continue;
            }

            if ($child->localName === 'Override') {
                self::assertRecordShape($child, ['PartName', 'ContentType'], 'OPC Override content-type record', $ignorableNamespaces);
                self::assertXmlOverridePartName($child->getAttribute('PartName'));
                $types->addOverride($child->getAttribute('PartName'), $child->getAttribute('ContentType'));
                continue;
            }

            throw new \InvalidArgumentException('Unsupported OPC content-types element: ' . $child->localName);
        }

        return $types;
    }

    public function addDefault(string $extension, string $contentType): void
    {
        $extension = self::normalizeExtension($extension);
        self::assertContentType($contentType);

        if (isset($this->defaults[strtolower($extension)])) {
            throw new \InvalidArgumentException('Duplicate OPC default content type for extension: ' . $extension);
        }

        $this->defaults[strtolower($extension)] = [
            'extension' => $extension,
            'contentType' => $contentType,
        ];
    }

    public function addOverride(string $partName, string $contentType): void
    {
        $partName = OpcPackagePath::canonicalPartNameFromUri($partName);
        self::assertContentType($contentType);
        $equivalenceKey = self::partNameEquivalenceKey($partName);

        if (isset($this->overridePartNamesByEquivalenceKey[$equivalenceKey])) {
            throw new \InvalidArgumentException('Duplicate OPC override content type for part: ' . $partName);
        }

        $this->overrides[$partName] = $contentType;
        $this->overridePartNamesByEquivalenceKey[$equivalenceKey] = $partName;
    }

    public function contentTypeForPart(string $partName): ?string
    {
        $partName = OpcPackagePath::canonicalPartNameFromUri(OpcPackagePath::stripQueryAndFragment($partName));
        if (isset($this->overrides[$partName])) {
            return $this->overrides[$partName];
        }

        $overridePartName = $this->overridePartNamesByEquivalenceKey[self::partNameEquivalenceKey($partName)] ?? null;
        if ($overridePartName !== null) {
            return $this->overrides[$overridePartName];
        }

        $basename = basename($partName);
        $dot = strrpos($basename, '.');
        if ($dot === false || $dot === strlen($basename) - 1) {
            return null;
        }

        $extension = strtolower(substr($basename, $dot + 1));

        return $this->defaults[$extension]['contentType'] ?? null;
    }

    /**
     * @return array<string, string>
     */
    public function defaults(): array
    {
        $defaults = [];
        foreach ($this->defaults as $entry) {
            $defaults[$entry['extension']] = $entry['contentType'];
        }

        return $defaults;
    }

    /**
     * @return array<string, string>
     */
    public function overrides(): array
    {
        return $this->overrides;
    }

    public function toXml(): string
    {
        $dom = new \DOMDocument('1.0', 'UTF-8');
        $dom->formatOutput = true;
        $root = $dom->createElementNS(self::NAMESPACE_URI, 'Types');
        $dom->appendChild($root);

        foreach ($this->defaults as $entry) {
            $default = $dom->createElementNS(self::NAMESPACE_URI, 'Default');
            $default->setAttribute('Extension', $entry['extension']);
            $default->setAttribute('ContentType', $entry['contentType']);
            $root->appendChild($default);
        }

        foreach ($this->overrides as $partName => $contentType) {
            $override = $dom->createElementNS(self::NAMESPACE_URI, 'Override');
            $override->setAttribute('PartName', OpcPackagePath::partNameToUri($partName));
            $override->setAttribute('ContentType', $contentType);
            $root->appendChild($override);
        }

        $xml = $dom->saveXML($root);
        if ($xml === false) {
            throw new \RuntimeException('Failed to serialize OPC content-types XML');
        }

        return $xml;
    }

    /**
     * @param array<string, true> $ignorableNamespaces
     */
    private static function assertRootShape(\DOMElement $root, array $ignorableNamespaces): void
    {
        foreach ($root->attributes as $attribute) {
            if (!$attribute instanceof \DOMAttr) {
                continue;
            }

            if (
                OpcMarkupCompatibility::isNamespaceDeclaration($attribute)
                || OpcMarkupCompatibility::isIgnorableDeclaration($attribute)
                || OpcMarkupCompatibility::isIgnorableExtensionAttribute($attribute, $ignorableNamespaces)
            ) {
                continue;
            }

            throw new \InvalidArgumentException('OPC content-types XML root contains unsupported attribute: ' . $attribute->name);
        }

        foreach ($root->childNodes as $child) {
            if (($child instanceof \DOMText || $child instanceof \DOMCdataSection) && trim($child->nodeValue ?? '') !== '') {
                throw new \InvalidArgumentException('OPC content-types XML root may not contain text content');
            }
        }
    }

    private static function normalizeExtension(string $extension): string
    {
        $extension = ltrim($extension, '.');
        if ($extension === '' || str_contains($extension, '/') || str_contains($extension, '\\') || str_contains($extension, '?') || str_contains($extension, '#')) {
            throw new \InvalidArgumentException('OPC content-type extension must be a simple extension name');
        }

        return $extension;
    }

    private static function assertXmlDefaultExtension(string $extension): void
    {
        if (str_starts_with($extension, '.')) {
            throw new \InvalidArgumentException('OPC Default extension must not include a leading dot');
        }
    }

    private static function assertXmlOverridePartName(string $partName): void
    {
        if (!str_starts_with($partName, '/')) {
            throw new \InvalidArgumentException('OPC Override part name must be an absolute package URI');
        }

        $segments = explode('/', $partName);
        array_shift($segments);
        foreach ($segments as $segment) {
            $decoded = rawurldecode($segment);
            if ($decoded === '' || $decoded === '.' || $decoded === '..') {
                throw new \InvalidArgumentException('OPC Override part name must not contain empty or dot path segments');
            }
        }
    }

    private static function partNameEquivalenceKey(string $partName): string
    {
        return strtolower($partName);
    }

    private static function assertContentType(string $contentType): void
    {
        if ($contentType === '' || preg_match('/[\x00-\x1F\x7F]/', $contentType) === 1) {
            throw new \InvalidArgumentException('OPC content type must be a non-empty MIME type');
        }

        $token = '[A-Za-z0-9!#$%&\'*+.^_`{|}~-]+';
        if (preg_match('/\A' . $token . '\/' . $token . '/', $contentType, $matches) !== 1 || $matches[0] === '') {
            throw new \InvalidArgumentException('OPC content type must be a non-empty MIME type');
        }

        $rest = substr($contentType, strlen($matches[0]));
        while ($rest !== '') {
            if (preg_match('/\A\s*;\s*' . $token . '\s*=\s*(?:' . $token . '|"(?:[^"\\\\\x00-\x1F\x7F]|\\\\[\x20-\x7E])*")/', $rest, $parameter) !== 1) {
                throw new \InvalidArgumentException('OPC content type must be a non-empty MIME type');
            }

            $rest = substr($rest, strlen($parameter[0]));
        }
    }

    /**
     * @param list<string> $allowedAttributes
     * @param array<string, true> $ignorableNamespaces
     */
    private static function assertRecordShape(\DOMElement $element, array $allowedAttributes, string $label, array $ignorableNamespaces): void
    {
        foreach ($element->attributes as $attribute) {
            if (!$attribute instanceof \DOMAttr) {
                continue;
            }

            if (
                OpcMarkupCompatibility::isNamespaceDeclaration($attribute)
                || OpcMarkupCompatibility::isIgnorableExtensionAttribute($attribute, $ignorableNamespaces)
            ) {
                continue;
            }

            if (($attribute->namespaceURI ?? '') !== '' || !in_array($attribute->name, $allowedAttributes, true)) {
                throw new \InvalidArgumentException($label . ' contains unsupported attribute: ' . $attribute->name);
            }
        }

        foreach ($element->childNodes as $child) {
            if ($child instanceof \DOMElement) {
                if (OpcMarkupCompatibility::isIgnorableExtensionElement($child, $ignorableNamespaces)) {
                    continue;
                }

                throw new \InvalidArgumentException($label . ' must be an empty element');
            }

            if (($child instanceof \DOMText || $child instanceof \DOMCdataSection) && trim($child->nodeValue ?? '') !== '') {
                throw new \InvalidArgumentException($label . ' must be an empty element');
            }
        }
    }

    private static function loadXml(string $xml): \DOMDocument
    {
        return XmlHtmlDom::loadXmlDocument($xml, 'OPC content-types XML');
    }
}
