<?php

declare(strict_types=1);

namespace PortLibs\Pandoc;

final class OpcRelationshipGraph
{
    public const OFFICE_DOCUMENT_RELATIONSHIP_TYPE = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument';
    public const DIGITAL_SIGNATURE_ORIGIN_RELATIONSHIP_TYPE = 'http://schemas.openxmlformats.org/package/2006/relationships/digital-signature/origin';
    public const DIGITAL_SIGNATURE_SIGNATURE_RELATIONSHIP_TYPE = 'http://schemas.openxmlformats.org/package/2006/relationships/digital-signature/signature';

    private const RELATIONSHIP_PART_CONTENT_TYPE = 'application/vnd.openxmlformats-package.relationships+xml';
    private const DIGITAL_SIGNATURE_ORIGIN_CONTENT_TYPE = 'application/vnd.openxmlformats-package.digital-signature-origin';
    private const DIGITAL_SIGNATURE_XML_SIGNATURE_CONTENT_TYPE = 'application/vnd.openxmlformats-package.digital-signature-xmlsignature+xml';

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
     * @return list<array{id:string, type:string, target:string, targetPart:?string, contentType:?string, external:bool, exists:?bool, relationshipPartName:?string, valid:bool, issues:list<string>, signatures:list<array{id:string, type:string, target:string, targetPart:?string, contentType:?string, external:bool, exists:?bool, valid:bool, issues:list<string>}>}>
     */
    public function preflightDigitalSignatures(): array
    {
        $origins = $this->preflightTargetsForSource('/', self::DIGITAL_SIGNATURE_ORIGIN_RELATIONSHIP_TYPE);
        $originCount = count($origins);
        $preflight = [];

        foreach ($origins as $origin) {
            $targetPart = self::targetPartFromPreflightTarget($origin);
            $issues = $origin['issues'];
            $relationshipPartName = null;
            $signatures = [];

            if ($originCount > 1) {
                $issues[] = 'multiple-digital-signature-origins';
            }

            if ($origin['external']) {
                $issues[] = 'external-digital-signature-origin';
            }

            if ($origin['contentType'] !== null && $origin['contentType'] !== self::DIGITAL_SIGNATURE_ORIGIN_CONTENT_TYPE) {
                $issues[] = 'invalid-digital-signature-origin-content-type';
            }

            if (
                $targetPart !== null
                && $origin['external'] === false
                && $origin['exists'] === true
                && $origin['relationshipPartTarget'] === false
            ) {
                if ($this->hasRelationshipsForSource($targetPart)) {
                    $relationshipPartName = OpcRelationships::relationshipPartNameForSource($targetPart);
                    foreach ($this->preflightTargetsForSource($targetPart, self::DIGITAL_SIGNATURE_SIGNATURE_RELATIONSHIP_TYPE) as $signature) {
                        $signatureIssues = $signature['issues'];
                        if ($signature['external']) {
                            $signatureIssues[] = 'external-digital-signature-target';
                        }

                        if ($signature['contentType'] !== null && $signature['contentType'] !== self::DIGITAL_SIGNATURE_XML_SIGNATURE_CONTENT_TYPE) {
                            $signatureIssues[] = 'invalid-digital-signature-content-type';
                        }

                        $signatureIssues = array_values(array_unique($signatureIssues));
                        $signatures[] = [
                            'id' => $signature['id'],
                            'type' => $signature['type'],
                            'target' => $signature['target'],
                            'targetPart' => self::targetPartFromPreflightTarget($signature),
                            'contentType' => $signature['contentType'],
                            'external' => $signature['external'],
                            'exists' => $signature['exists'],
                            'valid' => $signatureIssues === [],
                            'issues' => $signatureIssues,
                        ];
                    }
                } else {
                    $issues[] = 'missing-digital-signature-origin-relationships';
                }
            }

            $issues = array_values(array_unique($issues));
            $preflight[] = [
                'id' => $origin['id'],
                'type' => $origin['type'],
                'target' => $origin['target'],
                'targetPart' => $targetPart,
                'contentType' => $origin['contentType'],
                'external' => $origin['external'],
                'exists' => $origin['exists'],
                'relationshipPartName' => $relationshipPartName,
                'valid' => $issues === [] && array_reduce(
                    $signatures,
                    static fn (bool $valid, array $signature): bool => $valid && $signature['valid'],
                    true
                ),
                'issues' => $issues,
                'signatures' => $signatures,
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

    /**
     * @param array{target:string, external:bool, issues:list<string>} $target
     */
    private static function targetPartFromPreflightTarget(array $target): ?string
    {
        if ($target['external'] || in_array('invalid-target', $target['issues'], true)) {
            return null;
        }

        return OpcPackagePath::stripQueryAndFragment($target['target']);
    }
}
