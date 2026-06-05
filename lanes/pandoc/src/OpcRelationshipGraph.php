<?php

declare(strict_types=1);

namespace PortLibs\Pandoc;

final class OpcRelationshipGraph
{
    public const OFFICE_DOCUMENT_RELATIONSHIP_TYPE = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument';
    private const RELATIONSHIP_PART_CONTENT_TYPE = 'application/vnd.openxmlformats-package.relationships+xml';

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
            if ($sourcePartName !== '/' && OpcRelationships::isRelationshipPartName($sourcePartName)) {
                continue;
            }

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

    /**
     * @return list<array{id:string, type:string, target:string, contentType:?string, external:bool, exists:?bool, relationshipPartTarget:bool, externalTargetKind:?string, externalTargetScheme:?string, externalTargetAllowed:?bool, valid:bool, issues:list<string>}>
     */
    public function preflightTargetsForSource(string $sourcePartName = '/', ?string $relationshipType = null): array
    {
        $relationships = $this->relationshipsForSource($sourcePartName);
        if (!$relationships instanceof OpcRelationships) {
            return [];
        }

        $items = $relationshipType === null
            ? $relationships->all()
            : $relationships->ofType($relationshipType);

        $preflight = [];
        foreach ($items as $relationship) {
            if ($relationship->isExternal()) {
                $externalTarget = $relationship->externalTargetPreflight();
                $preflight[] = [
                    'id' => $relationship->id,
                    'type' => $relationship->type,
                    'target' => $relationship->target,
                    'contentType' => null,
                    'external' => true,
                    'exists' => null,
                    'relationshipPartTarget' => false,
                    'externalTargetKind' => $externalTarget['kind'],
                    'externalTargetScheme' => $externalTarget['scheme'],
                    'externalTargetAllowed' => $externalTarget['allowed'],
                    'valid' => $externalTarget['issues'] === [],
                    'issues' => $externalTarget['issues'],
                ];
                continue;
            }

            try {
                $target = $relationships->resolveTarget($relationship);
            } catch (\InvalidArgumentException $exception) {
                $preflight[] = [
                    'id' => $relationship->id,
                    'type' => $relationship->type,
                    'target' => $relationship->target,
                    'contentType' => null,
                    'external' => false,
                    'exists' => null,
                    'relationshipPartTarget' => false,
                    'externalTargetKind' => null,
                    'externalTargetScheme' => null,
                    'externalTargetAllowed' => null,
                    'valid' => false,
                    'issues' => ['invalid-target'],
                ];
                continue;
            }

            $targetPartName = OpcPackagePath::stripQueryAndFragment($target);
            $exists = $this->package->has($targetPartName);
            $contentType = $this->contentTypes->contentTypeForPart($targetPartName);
            $relationshipPartTarget = self::isRelationshipPartName($targetPartName);
            $issues = [];

            if (!$exists) {
                $issues[] = 'missing-in-package';
            }

            if ($contentType === null) {
                $issues[] = 'missing-content-type';
            }

            if ($relationshipPartTarget) {
                $issues[] = 'targets-relationship-part';
            }

            $preflight[] = [
                'id' => $relationship->id,
                'type' => $relationship->type,
                'target' => $target,
                'contentType' => $contentType,
                'external' => false,
                'exists' => $exists,
                'relationshipPartTarget' => $relationshipPartTarget,
                'externalTargetKind' => null,
                'externalTargetScheme' => null,
                'externalTargetAllowed' => null,
                'valid' => $issues === [],
                'issues' => $issues,
            ];
        }

        return $preflight;
    }

    /**
     * @return list<array{partName:string, contentType:?string, relationshipPart:bool, relationshipSource:?string, relationshipSourceIsRelationshipPart:?bool, sourceExists:?bool, valid:bool, issues:list<string>}>
     */
    public function preflightPackageParts(): array
    {
        $preflight = [];
        foreach ($this->package->names() as $name) {
            if ($name === '[Content_Types].xml' || str_ends_with($name, '/')) {
                continue;
            }

            $partName = OpcPackagePath::canonicalPartName($name);
            $contentType = $this->contentTypes->contentTypeForPart($partName);
            $relationshipPart = self::isRelationshipPartName($partName);
            $relationshipSource = null;
            $relationshipSourceIsRelationshipPart = null;
            $sourceExists = null;
            $issues = [];

            if ($contentType === null) {
                $issues[] = 'missing-content-type';
            }

            if ($relationshipPart) {
                $relationshipSource = OpcRelationships::sourcePartNameForRelationshipPart($partName);
                $relationshipSourceIsRelationshipPart = $relationshipSource !== '/'
                    && OpcRelationships::isRelationshipPartName($relationshipSource);
                $sourceExists = $relationshipSource === '/' || $this->package->has($relationshipSource);

                if ($contentType !== null && $contentType !== self::RELATIONSHIP_PART_CONTENT_TYPE) {
                    $issues[] = 'invalid-relationship-content-type';
                }

                if ($relationshipSourceIsRelationshipPart) {
                    $issues[] = 'relationship-part-source';
                }

                if (!$sourceExists) {
                    $issues[] = 'orphan-relationship-part';
                }
            }

            $preflight[] = [
                'partName' => $partName,
                'contentType' => $contentType,
                'relationshipPart' => $relationshipPart,
                'relationshipSource' => $relationshipSource,
                'relationshipSourceIsRelationshipPart' => $relationshipSourceIsRelationshipPart,
                'sourceExists' => $sourceExists,
                'valid' => $issues === [],
                'issues' => $issues,
            ];
        }

        return $preflight;
    }

    /**
     * @return list<array{source:string, depth:int, id:string, type:string, target:string, targetPart:?string, contentType:?string, external:bool, exists:?bool, relationshipPartTarget:bool, externalTargetKind:?string, externalTargetScheme:?string, externalTargetAllowed:?bool, valid:bool, issues:list<string>}>
     */
    public function reachableTargetsForSource(string $sourcePartName = '/', ?string $relationshipType = null): array
    {
        $sourcePartName = OpcPackagePath::canonicalPartName($sourcePartName, true);
        $queue = [[$sourcePartName, $relationshipType, 0]];
        $queuedSources = [$sourcePartName => true];
        $visitedSources = [];
        $targets = [];

        while ($queue !== []) {
            [$source, $filter, $depth] = array_shift($queue);
            if (isset($visitedSources[$source])) {
                continue;
            }

            $visitedSources[$source] = true;

            foreach ($this->preflightTargetsForSource($source, $filter) as $target) {
                $invalidTarget = in_array('invalid-target', $target['issues'], true);
                $targetPart = null;
                if (!$target['external'] && !$invalidTarget) {
                    $targetPart = OpcPackagePath::stripQueryAndFragment($target['target']);
                }

                $targets[] = [
                    'source' => $source,
                    'depth' => $depth,
                    'id' => $target['id'],
                    'type' => $target['type'],
                    'target' => $target['target'],
                    'targetPart' => $targetPart,
                    'contentType' => $target['contentType'],
                    'external' => $target['external'],
                    'exists' => $target['exists'],
                    'relationshipPartTarget' => $target['relationshipPartTarget'],
                    'externalTargetKind' => $target['externalTargetKind'],
                    'externalTargetScheme' => $target['externalTargetScheme'],
                    'externalTargetAllowed' => $target['externalTargetAllowed'],
                    'valid' => $target['valid'],
                    'issues' => $target['issues'],
                ];

                if (
                    $targetPart === null
                    || $target['external']
                    || $invalidTarget
                    || $target['exists'] !== true
                    || $target['relationshipPartTarget']
                    || isset($visitedSources[$targetPart])
                    || isset($queuedSources[$targetPart])
                    || !($this->relationshipsForSource($targetPart) instanceof OpcRelationships)
                ) {
                    continue;
                }

                $queuedSources[$targetPart] = true;
                $queue[] = [$targetPart, null, $depth + 1];
            }
        }

        return $targets;
    }

    private static function isRelationshipPartName(string $name): bool
    {
        return OpcRelationships::isRelationshipPartName($name);
    }
}
