<?php

declare(strict_types=1);

namespace PortLibs\Pandoc;

final class OpcRelationships
{
    public const NAMESPACE_URI = 'http://schemas.openxmlformats.org/package/2006/relationships';

    /** @var list<OpcRelationship> */
    private array $relationships = [];

    /** @var array<string, OpcRelationship> */
    private array $byId = [];

    public function __construct(private readonly string $sourcePartName = '/')
    {
        $sourcePartName = OpcPackagePath::canonicalPartName($sourcePartName, true);
        self::assertRelationshipSourcePartName($sourcePartName);
    }

    public static function fromXml(string $xml, string $sourcePartName = '/'): self
    {
        $dom = self::loadXml($xml);
        $root = $dom->documentElement;
        if (!$root instanceof \DOMElement || $root->localName !== 'Relationships' || $root->namespaceURI !== self::NAMESPACE_URI) {
            throw new \InvalidArgumentException('OPC relationships XML must use the package relationships namespace');
        }

        $ignorableNamespaces = OpcMarkupCompatibility::ignorableNamespacesForElement($root, self::NAMESPACE_URI, 'OPC relationships XML root');
        $processContentElements = OpcMarkupCompatibility::processContentElementsForElement($root, $ignorableNamespaces, 'OPC relationships XML root');
        self::assertRootShape($root, $ignorableNamespaces);

        $relationships = new self($sourcePartName);
        foreach (OpcMarkupCompatibility::packageChildElements(
            $root,
            self::NAMESPACE_URI,
            $ignorableNamespaces,
            $processContentElements,
            'OPC relationships XML may only contain Relationship children',
            'OPC relationships XML root may not contain text content'
        ) as $child) {
            if ($child->localName !== 'Relationship') {
                throw new \InvalidArgumentException('OPC relationships XML may only contain Relationship children');
            }

            self::assertRelationshipElementShape($child, $ignorableNamespaces);

            $relationships->add(new OpcRelationship(
                $child->getAttribute('Id'),
                $child->getAttribute('Type'),
                $child->getAttribute('Target'),
                $child->hasAttribute('TargetMode') ? $child->getAttribute('TargetMode') : OpcRelationship::TARGET_MODE_INTERNAL,
            ));
        }

        return $relationships;
    }

    public static function fromPackage(ZipPackage $package, string $sourcePartName = '/'): self
    {
        $relationshipPartName = self::relationshipPartNameForSource($sourcePartName);
        if (!$package->has($relationshipPartName)) {
            throw new \RuntimeException('OPC relationship part not found: ' . $relationshipPartName);
        }

        return self::fromXml($package->read($relationshipPartName), $sourcePartName);
    }

    public static function packageHasRelationshipsForSource(ZipPackage $package, string $sourcePartName = '/'): bool
    {
        return $package->has(self::relationshipPartNameForSource($sourcePartName));
    }

    public static function relationshipPartNameForSource(string $sourcePartName): string
    {
        $sourcePartName = OpcPackagePath::canonicalPartName($sourcePartName, true);
        self::assertRelationshipSourcePartName($sourcePartName);
        if ($sourcePartName === '/') {
            return '/_rels/.rels';
        }

        $sourceUri = OpcPackagePath::partNameToUri($sourcePartName);
        $dir = dirname($sourceUri);
        $base = basename($sourceUri);

        return ($dir === '/' ? '' : $dir) . '/_rels/' . $base . '.rels';
    }

    public static function sourcePartNameForRelationshipPart(string $relationshipPartName): string
    {
        $relationshipPartName = OpcPackagePath::canonicalPartName($relationshipPartName);
        if ($relationshipPartName === '/_rels/.rels') {
            return '/';
        }

        if (!str_ends_with($relationshipPartName, '.rels')) {
            throw new \InvalidArgumentException('OPC relationship part names must end with .rels');
        }

        $marker = '/_rels/';
        $position = strrpos($relationshipPartName, $marker);
        if ($position === false) {
            throw new \InvalidArgumentException('OPC relationship part names must contain a _rels segment');
        }

        $dir = substr($relationshipPartName, 0, $position);
        $base = substr($relationshipPartName, $position + strlen($marker), -5);
        if ($base === '') {
            throw new \InvalidArgumentException('OPC relationship part names must identify a source part');
        }

        return OpcPackagePath::canonicalPartNameFromUri(($dir === '' ? '/' : $dir . '/') . $base);
    }

    public static function isRelationshipPartName(string $partName): bool
    {
        $partName = OpcPackagePath::canonicalPartName($partName);

        return $partName === '/_rels/.rels'
            || (str_ends_with($partName, '.rels') && str_contains($partName, '/_rels/'));
    }

    public function add(OpcRelationship $relationship): void
    {
        if (isset($this->byId[$relationship->id])) {
            throw new \InvalidArgumentException('Duplicate OPC relationship Id: ' . $relationship->id);
        }

        $this->relationships[] = $relationship;
        $this->byId[$relationship->id] = $relationship;
    }

    /**
     * @return list<OpcRelationship>
     */
    public function all(): array
    {
        return $this->relationships;
    }

    public function byId(string $id): ?OpcRelationship
    {
        return $this->byId[$id] ?? null;
    }

    /**
     * @return list<OpcRelationship>
     */
    public function ofType(string $type): array
    {
        return array_values(array_filter(
            $this->relationships,
            static fn (OpcRelationship $relationship): bool => $relationship->type === $type,
        ));
    }

    public function firstOfType(string $type): ?OpcRelationship
    {
        foreach ($this->relationships as $relationship) {
            if ($relationship->type === $type) {
                return $relationship;
            }
        }

        return null;
    }

    public function relationshipPartName(): string
    {
        return self::relationshipPartNameForSource($this->sourcePartName);
    }

    public function resolveTarget(OpcRelationship|string $relationship): string
    {
        if (is_string($relationship)) {
            $resolved = $this->byId($relationship);
            if (!$resolved instanceof OpcRelationship) {
                throw new \InvalidArgumentException('Unknown OPC relationship Id: ' . $relationship);
            }
            $relationship = $resolved;
        }

        if ($relationship->isExternal()) {
            return $relationship->target;
        }

        return OpcPackagePath::resolveInternalTarget($this->sourcePartName, $relationship->target);
    }

    public function toXml(): string
    {
        $dom = new \DOMDocument('1.0', 'UTF-8');
        $dom->formatOutput = true;
        $root = $dom->createElementNS(self::NAMESPACE_URI, 'Relationships');
        $dom->appendChild($root);

        foreach ($this->relationships as $relationship) {
            $element = $dom->createElementNS(self::NAMESPACE_URI, 'Relationship');
            $element->setAttribute('Id', $relationship->id);
            $element->setAttribute('Type', $relationship->type);
            $element->setAttribute('Target', $relationship->target);
            if ($relationship->targetMode !== OpcRelationship::TARGET_MODE_INTERNAL) {
                $element->setAttribute('TargetMode', $relationship->targetMode);
            }
            $root->appendChild($element);
        }

        $xml = $dom->saveXML($root);
        if ($xml === false) {
            throw new \RuntimeException('Failed to serialize OPC relationships XML');
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
                || OpcMarkupCompatibility::isProcessContentDeclaration($attribute)
                || OpcMarkupCompatibility::isIgnorableExtensionAttribute($attribute, $ignorableNamespaces)
            ) {
                continue;
            }

            throw new \InvalidArgumentException('OPC relationships XML root contains unsupported attribute: ' . $attribute->name);
        }

        foreach ($root->childNodes as $child) {
            if (($child instanceof \DOMText || $child instanceof \DOMCdataSection) && trim($child->nodeValue ?? '') !== '') {
                throw new \InvalidArgumentException('OPC relationships XML root may not contain text content');
            }
        }
    }

    private static function loadXml(string $xml): \DOMDocument
    {
        return XmlHtmlDom::loadXmlDocument($xml, 'OPC relationships XML');
    }

    private static function assertRelationshipSourcePartName(string $sourcePartName): void
    {
        if ($sourcePartName !== '/' && self::isRelationshipPartName($sourcePartName)) {
            throw new \InvalidArgumentException('OPC relationship parts must not be relationship sources');
        }
    }

    /**
     * @param array<string, true> $ignorableNamespaces
     */
    private static function assertRelationshipElementShape(\DOMElement $element, array $ignorableNamespaces): void
    {
        $allowedAttributes = ['Id', 'Type', 'Target', 'TargetMode'];
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
                throw new \InvalidArgumentException('OPC Relationship record contains unsupported attribute: ' . $attribute->name);
            }
        }

        foreach ($element->childNodes as $child) {
            if ($child instanceof \DOMElement) {
                if (OpcMarkupCompatibility::isIgnorableExtensionElement($child, $ignorableNamespaces)) {
                    continue;
                }

                throw new \InvalidArgumentException('OPC Relationship record must be an empty element');
            }

            if (($child instanceof \DOMText || $child instanceof \DOMCdataSection) && trim($child->nodeValue ?? '') !== '') {
                throw new \InvalidArgumentException('OPC Relationship record must be an empty element');
            }
        }
    }
}
