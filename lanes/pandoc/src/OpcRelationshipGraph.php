<?php

declare(strict_types=1);

namespace PortLibs\Pandoc;

final class OpcRelationshipGraph
{
    public const OFFICE_DOCUMENT_RELATIONSHIP_TYPE = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument';

    /**
     * @param array<string, OpcRelationships> $relationshipsBySource
     */
    private function __construct(
        private readonly ZipPackage $package,
        private readonly OpcContentTypes $contentTypes,
        private readonly array $relationshipsBySource,
    ) {
    }

    public static function fromPackage(ZipPackage $package): self
    {
        if (!$package->has('[Content_Types].xml')) {
            throw new \RuntimeException('OPC package is missing [Content_Types].xml');
        }

        $contentTypes = OpcContentTypes::fromXml($package->read('[Content_Types].xml'));
        $relationshipsBySource = [];

        foreach ($package->names() as $name) {
            if (!self::isRelationshipPartName($name)) {
                continue;
            }

            $relationshipPartName = OpcPackagePath::canonicalPartName($name);
            $sourcePartName = OpcRelationships::sourcePartNameForRelationshipPart($relationshipPartName);
            $relationshipsBySource[$sourcePartName] = OpcRelationships::fromXml(
                $package->read($relationshipPartName),
                $sourcePartName,
            );
        }

        return new self($package, $contentTypes, $relationshipsBySource);
    }

    public function package(): ZipPackage
    {
        return $this->package;
    }

    public function contentTypes(): OpcContentTypes
    {
        return $this->contentTypes;
    }

    public function hasRelationshipsForSource(string $sourcePartName = '/'): bool
    {
        return isset($this->relationshipsBySource[OpcPackagePath::canonicalPartName($sourcePartName, true)]);
    }

    public function relationshipsForSource(string $sourcePartName = '/'): ?OpcRelationships
    {
        return $this->relationshipsBySource[OpcPackagePath::canonicalPartName($sourcePartName, true)] ?? null;
    }

    public function requireRelationshipsForSource(string $sourcePartName = '/'): OpcRelationships
    {
        $sourcePartName = OpcPackagePath::canonicalPartName($sourcePartName, true);
        $relationships = $this->relationshipsBySource[$sourcePartName] ?? null;
        if (!$relationships instanceof OpcRelationships) {
            throw new \RuntimeException('OPC relationship part not found: ' . OpcRelationships::relationshipPartNameForSource($sourcePartName));
        }

        return $relationships;
    }

    /**
     * @return list<string>
     */
    public function sourcePartNames(): array
    {
        $sourcePartNames = array_keys($this->relationshipsBySource);
        sort($sourcePartNames, SORT_STRING);

        return array_values($sourcePartNames);
    }

    public function firstTargetOfType(string $relationshipType, string $sourcePartName = '/'): ?string
    {
        $relationships = $this->relationshipsForSource($sourcePartName);
        if (!$relationships instanceof OpcRelationships) {
            return null;
        }

        $relationship = $relationships->firstOfType($relationshipType);
        if (!$relationship instanceof OpcRelationship) {
            return null;
        }

        return $relationships->resolveTarget($relationship);
    }

    /**
     * @return list<array{id:string, type:string, target:string, contentType:?string, external:bool}>
     */
    public function summarizeTargetsForSource(string $sourcePartName = '/', ?string $relationshipType = null): array
    {
        $relationships = $this->relationshipsForSource($sourcePartName);
        if (!$relationships instanceof OpcRelationships) {
            return [];
        }

        $items = $relationshipType === null
            ? $relationships->all()
            : $relationships->ofType($relationshipType);

        $summary = [];
        foreach ($items as $relationship) {
            $target = $relationships->resolveTarget($relationship);
            $summary[] = [
                'id' => $relationship->id,
                'type' => $relationship->type,
                'target' => $target,
                'contentType' => $relationship->isExternal() ? null : $this->contentTypes->contentTypeForPart($target),
                'external' => $relationship->isExternal(),
            ];
        }

        return $summary;
    }

    private static function isRelationshipPartName(string $name): bool
    {
        return str_ends_with($name, '.rels') && str_contains('/' . ltrim($name, '/'), '/_rels/');
    }
}
