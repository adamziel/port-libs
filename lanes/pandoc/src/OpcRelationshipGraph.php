<?php

declare(strict_types=1);

namespace PortLibs\Pandoc;

final class OpcRelationshipGraph
{
    public const OFFICE_DOCUMENT_RELATIONSHIP_TYPE = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument';
    public const DIGITAL_SIGNATURE_ORIGIN_RELATIONSHIP_TYPE = 'http://schemas.openxmlformats.org/package/2006/relationships/digital-signature/origin';
    public const DIGITAL_SIGNATURE_SIGNATURE_RELATIONSHIP_TYPE = 'http://schemas.openxmlformats.org/package/2006/relationships/digital-signature/signature';
    public const RELATIONSHIP_TRANSFORM_ALGORITHM = 'http://schemas.openxmlformats.org/package/2006/RelationshipTransform';
    public const XML_SIGNATURE_NAMESPACE_URI = 'http://www.w3.org/2000/09/xmldsig#';
    public const DIGITAL_SIGNATURE_NAMESPACE_URI = 'http://schemas.openxmlformats.org/package/2006/digital-signature';
    public const EMBEDDED_PACKAGE_RELATIONSHIP_TYPE = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships/package';
    public const EMBEDDED_OBJECT_RELATIONSHIP_TYPE = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships/oleObject';
    public const WORDPROCESSING_OFFICE_DOCUMENT_CONTENT_TYPES = [
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml',
        'application/vnd.ms-word.document.macroEnabled.main+xml',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.template.main+xml',
        'application/vnd.ms-word.template.macroEnabledTemplate.main+xml',
    ];

    private const RELATIONSHIP_PART_CONTENT_TYPE = 'application/vnd.openxmlformats-package.relationships+xml';
    private const DIGITAL_SIGNATURE_ORIGIN_CONTENT_TYPE = 'application/vnd.openxmlformats-package.digital-signature-origin';
    private const DIGITAL_SIGNATURE_XML_SIGNATURE_CONTENT_TYPE = 'application/vnd.openxmlformats-package.digital-signature-xmlsignature+xml';
    private const EMBEDDED_PACKAGE_CONTENT_TYPE = 'application/vnd.openxmlformats-officedocument.package';
    private const EMBEDDED_OBJECT_CONTENT_TYPE = 'application/vnd.openxmlformats-officedocument.oleObject';

    /**
     * @param array<string, OpcRelationships> $relationshipsBySource
     * @param array<string, string> $packagePartNamesByEquivalenceKey
     */
    private function __construct(
        private readonly ZipPackage $package,
        private readonly OpcContentTypes $contentTypes,
        private readonly array $relationshipsBySource,
        private readonly array $packagePartNamesByEquivalenceKey,
    ) {
        foreach (array_keys($relationshipsBySource) as $sourcePartName) {
            $this->relationshipSourceNamesByEquivalenceKey[self::partNameEquivalenceKey($sourcePartName)] = $sourcePartName;
        }
    }

    /** @var array<string, string> */
    private array $relationshipSourceNamesByEquivalenceKey = [];

    public static function fromPackage(ZipPackage $package): self
    {
        if (!$package->has('[Content_Types].xml')) {
            throw new \RuntimeException('OPC package is missing [Content_Types].xml');
        }

        foreach (self::preflightPackagePartNameEquivalence($package) as $partNameEquivalence) {
            if ($partNameEquivalence['valid']) {
                continue;
            }

            throw new \RuntimeException(
                'OPC package contains equivalent part names that differ only by ASCII case: '
                . implode(', ', $partNameEquivalence['equivalentPartNames'])
            );
        }

        $contentTypes = OpcContentTypes::fromXml($package->read('[Content_Types].xml'));
        $relationshipsBySource = [];

        foreach ($package->names() as $name) {
            if (!self::isRelationshipPartName($name)) {
                continue;
            }

            $relationshipPartName = OpcPackagePath::canonicalPartName($name);
            if ($contentTypes->contentTypeForPart($relationshipPartName) !== self::RELATIONSHIP_PART_CONTENT_TYPE) {
                continue;
            }

            $sourcePartName = OpcRelationships::sourcePartNameForRelationshipPart($relationshipPartName);
            if ($sourcePartName !== '/' && OpcRelationships::isRelationshipPartName($sourcePartName)) {
                continue;
            }

            if (isset($relationshipsBySource[$sourcePartName])) {
                throw new \RuntimeException('Duplicate OPC relationship part source: ' . $sourcePartName);
            }

            $relationshipsBySource[$sourcePartName] = OpcRelationships::fromXml(
                $package->read($relationshipPartName),
                $sourcePartName,
            );
        }

        return new self(
            $package,
            $contentTypes,
            $relationshipsBySource,
            self::packagePartNamesByEquivalenceKey($package),
        );
    }

    /**
     * @return list<array{partName:string, equivalenceKey:string, equivalentPartNames:list<string>, valid:bool, issues:list<string>}>
     */
    public static function preflightPackagePartNameEquivalence(ZipPackage $package): array
    {
        $preflight = [];
        $indexesByEquivalenceKey = [];

        foreach ($package->names() as $name) {
            if (str_ends_with($name, '/')) {
                continue;
            }

            $partName = OpcPackagePath::canonicalPartName($name);
            $equivalenceKey = self::partNameEquivalenceKey($partName);
            $preflight[] = [
                'partName' => $partName,
                'equivalenceKey' => $equivalenceKey,
                'equivalentPartNames' => [],
                'valid' => true,
                'issues' => [],
            ];
            $indexesByEquivalenceKey[$equivalenceKey][] = array_key_last($preflight);
        }

        foreach ($indexesByEquivalenceKey as $rowIndexes) {
            if (count($rowIndexes) < 2) {
                continue;
            }

            $partNames = [];
            foreach ($rowIndexes as $rowIndex) {
                $partNames[] = $preflight[$rowIndex]['partName'];
            }
            sort($partNames, SORT_STRING);

            foreach ($rowIndexes as $rowIndex) {
                $preflight[$rowIndex]['equivalentPartNames'] = $partNames;
                $preflight[$rowIndex]['valid'] = false;
                $preflight[$rowIndex]['issues'] = ['equivalent-part-name-case-collision'];
            }
        }

        return $preflight;
    }

    /**
     * @return list<array{partName:string, contentType:?string, relationshipSource:?string, relationshipSourceIsRelationshipPart:?bool, sourceExists:?bool, duplicateRelationshipPartNames:list<string>, loaded:bool, relationshipCount:?int, valid:bool, issues:list<string>, parseError:?string}>
     */
    public static function preflightRelationshipPartsInPackage(ZipPackage $package): array
    {
        if (!$package->has('[Content_Types].xml')) {
            throw new \RuntimeException('OPC package is missing [Content_Types].xml');
        }

        $contentTypes = OpcContentTypes::fromXml($package->read('[Content_Types].xml'));
        $packagePartNamesByEquivalenceKey = self::packagePartNamesByEquivalenceKey($package);
        $preflight = [];
        $sourceIndexes = [];
        foreach ($package->names() as $name) {
            if (!self::isRelationshipPartName($name)) {
                continue;
            }

            $partName = OpcPackagePath::canonicalPartName($name);
            $contentType = $contentTypes->contentTypeForPart($partName);
            $relationshipSource = null;
            $relationshipSourceIsRelationshipPart = null;
            $sourceExists = null;
            $loaded = false;
            $relationshipCount = null;
            $parseError = null;
            $issues = [];

            try {
                $relationshipSource = OpcRelationships::sourcePartNameForRelationshipPart($partName);
                $relationshipSourceIsRelationshipPart = $relationshipSource !== '/'
                    && self::isRelationshipPartName($relationshipSource);
                $sourceExists = $relationshipSource === '/'
                    || isset($packagePartNamesByEquivalenceKey[self::partNameEquivalenceKey($relationshipSource)]);
            } catch (\InvalidArgumentException $exception) {
                $issues[] = 'invalid-relationship-part-name';
                $parseError = $exception->getMessage();
            }

            if ($contentType === null) {
                $issues[] = 'missing-content-type';
            } elseif ($contentType !== self::RELATIONSHIP_PART_CONTENT_TYPE) {
                $issues[] = 'invalid-relationship-content-type';
            }

            if ($relationshipSourceIsRelationshipPart === true) {
                $issues[] = 'relationship-part-source';
            }

            if ($sourceExists === false) {
                $issues[] = 'orphan-relationship-part';
            }

            $issues = array_values(array_unique($issues));
            $preflight[] = [
                'partName' => $partName,
                'contentType' => $contentType,
                'relationshipSource' => $relationshipSource,
                'relationshipSourceIsRelationshipPart' => $relationshipSourceIsRelationshipPart,
                'sourceExists' => $sourceExists,
                'duplicateRelationshipPartNames' => [],
                'loaded' => $loaded,
                'relationshipCount' => $relationshipCount,
                'valid' => $issues === [],
                'issues' => $issues,
                'parseError' => $parseError,
            ];

            if ($relationshipSource !== null) {
                $sourceIndexes[$relationshipSource][] = array_key_last($preflight);
            }
        }

        foreach ($sourceIndexes as $rowIndexes) {
            if (count($rowIndexes) < 2) {
                continue;
            }

            $partNames = [];
            foreach ($rowIndexes as $rowIndex) {
                $partNames[] = $preflight[$rowIndex]['partName'];
            }
            sort($partNames, SORT_STRING);

            foreach ($rowIndexes as $rowIndex) {
                $preflight[$rowIndex]['duplicateRelationshipPartNames'] = $partNames;
                $preflight[$rowIndex]['issues'][] = 'duplicate-relationship-source';
                $preflight[$rowIndex]['issues'] = array_values(array_unique($preflight[$rowIndex]['issues']));
                $preflight[$rowIndex]['valid'] = false;
            }
        }

        foreach ($preflight as &$row) {
            if ($row['issues'] !== [] || $row['relationshipSource'] === null) {
                continue;
            }

            try {
                $relationships = OpcRelationships::fromXml($package->read($row['partName']), $row['relationshipSource']);
                $row['loaded'] = true;
                $row['relationshipCount'] = count($relationships->all());
            } catch (\Throwable $exception) {
                $row['issues'][] = 'malformed-relationship-xml';
                $row['issues'] = array_values(array_unique($row['issues']));
                $row['parseError'] = $exception->getMessage();
                $row['valid'] = false;
            }
        }
        unset($row);

        return $preflight;
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
        return isset($this->relationshipsBySource[$this->relationshipSourceNameForEquivalent($sourcePartName)]);
    }

    public function relationshipsForSource(string $sourcePartName = '/'): ?OpcRelationships
    {
        return $this->relationshipsBySource[$this->relationshipSourceNameForEquivalent($sourcePartName)] ?? null;
    }

    public function requireRelationshipsForSource(string $sourcePartName = '/'): OpcRelationships
    {
        $sourcePartName = $this->relationshipSourceNameForEquivalent($sourcePartName);
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

        $target = $relationships->resolveTarget($relationship);
        if ($relationship->isExternal()) {
            return $target;
        }

        return $this->packageEquivalentTarget($target);
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
            if (!$relationship->isExternal()) {
                $target = $this->packageEquivalentTarget($target);
            }
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
     * @return list<array{id:string, type:string, relationshipTypeKind:string, relationshipTypeScheme:?string, relationshipTypeValid:bool, relationshipTypeIssues:list<string>, target:string, contentType:?string, external:bool, exists:?bool, relationshipPartTarget:bool, externalTargetKind:?string, externalTargetScheme:?string, externalTargetAllowed:?bool, externalTargetRequiresBaseUri:?bool, externalTargetRewriteBasePart:?string, externalTargetRewriteReason:?string, valid:bool, issues:list<string>}>
     */
    public function preflightTargetsForSource(string $sourcePartName = '/', ?string $relationshipType = null): array
    {
        $sourcePartName = $this->relationshipSourceNameForEquivalent($sourcePartName);
        $relationships = $this->relationshipsForSource($sourcePartName);
        if (!$relationships instanceof OpcRelationships) {
            return [];
        }

        $items = $relationshipType === null
            ? $relationships->all()
            : $relationships->ofType($relationshipType);

        $preflight = [];
        foreach ($items as $relationship) {
            $typePreflight = $relationship->relationshipTypePreflight();
            if ($relationship->isExternal()) {
                $externalTarget = $relationship->externalTargetPreflight();
                $externalRewrite = self::externalTargetRewritePolicy($sourcePartName, $externalTarget);
                $issues = array_values(array_unique(array_merge($typePreflight['issues'], $externalTarget['issues'])));
                $preflight[] = [
                    'id' => $relationship->id,
                    'type' => $relationship->type,
                    'relationshipTypeKind' => $typePreflight['kind'],
                    'relationshipTypeScheme' => $typePreflight['scheme'],
                    'relationshipTypeValid' => $typePreflight['valid'],
                    'relationshipTypeIssues' => $typePreflight['issues'],
                    'target' => $relationship->target,
                    'contentType' => null,
                    'external' => true,
                    'exists' => null,
                    'relationshipPartTarget' => false,
                    'externalTargetKind' => $externalTarget['kind'],
                    'externalTargetScheme' => $externalTarget['scheme'],
                    'externalTargetAllowed' => $externalTarget['allowed'],
                    'externalTargetRequiresBaseUri' => $externalRewrite['requiresBaseUri'],
                    'externalTargetRewriteBasePart' => $externalRewrite['basePart'],
                    'externalTargetRewriteReason' => $externalRewrite['reason'],
                    'valid' => $issues === [],
                    'issues' => $issues,
                ];
                continue;
            }

            try {
                $target = $relationships->resolveTarget($relationship);
            } catch (\InvalidArgumentException $exception) {
                $issues = array_values(array_unique(array_merge($typePreflight['issues'], ['invalid-target'])));
                $preflight[] = [
                    'id' => $relationship->id,
                    'type' => $relationship->type,
                    'relationshipTypeKind' => $typePreflight['kind'],
                    'relationshipTypeScheme' => $typePreflight['scheme'],
                    'relationshipTypeValid' => $typePreflight['valid'],
                    'relationshipTypeIssues' => $typePreflight['issues'],
                    'target' => $relationship->target,
                    'contentType' => null,
                    'external' => false,
                    'exists' => null,
                    'relationshipPartTarget' => false,
                    'externalTargetKind' => null,
                    'externalTargetScheme' => null,
                    'externalTargetAllowed' => null,
                    'externalTargetRequiresBaseUri' => null,
                    'externalTargetRewriteBasePart' => null,
                    'externalTargetRewriteReason' => null,
                    'valid' => false,
                    'issues' => $issues,
                ];
                continue;
            }

            $targetPartName = OpcPackagePath::stripQueryAndFragment($target);
            $target = $this->packageEquivalentTarget($target);
            $targetPartName = OpcPackagePath::stripQueryAndFragment($target);
            $exists = $this->packagePartNameForEquivalent($targetPartName) !== null;
            $contentType = $this->contentTypes->contentTypeForPart($targetPartName);
            $relationshipPartTarget = self::isRelationshipPartName($targetPartName);
            $issues = $typePreflight['issues'];

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
                'relationshipTypeKind' => $typePreflight['kind'],
                'relationshipTypeScheme' => $typePreflight['scheme'],
                'relationshipTypeValid' => $typePreflight['valid'],
                'relationshipTypeIssues' => $typePreflight['issues'],
                'target' => $target,
                'contentType' => $contentType,
                'external' => false,
                'exists' => $exists,
                'relationshipPartTarget' => $relationshipPartTarget,
                'externalTargetKind' => null,
                'externalTargetScheme' => null,
                'externalTargetAllowed' => null,
                'externalTargetRequiresBaseUri' => null,
                'externalTargetRewriteBasePart' => null,
                'externalTargetRewriteReason' => null,
                'valid' => $issues === [],
                'issues' => array_values(array_unique($issues)),
            ];
        }

        return $preflight;
    }

    /**
     * @return list<array{partName:string, contentType:?string, relationshipPart:bool, relationshipSource:?string, relationshipSourceIsRelationshipPart:?bool, relationshipSourceLoaded:?bool, sourceExists:?bool, valid:bool, issues:list<string>}>
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
            $relationshipSourceLoaded = null;
            $sourceExists = null;
            $issues = [];

            if ($contentType === null) {
                $issues[] = 'missing-content-type';
            }

            if ($relationshipPart) {
                $relationshipSource = OpcRelationships::sourcePartNameForRelationshipPart($partName);
                $relationshipSourceIsRelationshipPart = $relationshipSource !== '/'
                    && OpcRelationships::isRelationshipPartName($relationshipSource);
                $sourceExists = $relationshipSource === '/'
                    || $this->packagePartNameForEquivalent($relationshipSource) !== null;
                $relationshipSourceLoaded = $this->hasRelationshipsForSource($relationshipSource);

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
                'relationshipSourceLoaded' => $relationshipSourceLoaded,
                'sourceExists' => $sourceExists,
                'valid' => $issues === [],
                'issues' => $issues,
            ];
        }

        return $preflight;
    }

    /**
     * @return list<array{partName:string, contentType:string, exists:bool, relationshipPart:bool, relationshipSource:?string, relationshipSourceIsRelationshipPart:?bool, relationshipSourceLoaded:?bool, sourceExists:?bool, valid:bool, issues:list<string>}>
     */
    public function preflightContentTypeOverrides(): array
    {
        $preflight = [];
        foreach ($this->contentTypes->overrides() as $partName => $contentType) {
            $exists = $this->packagePartNameForEquivalent($partName) !== null;
            $relationshipPart = self::isRelationshipPartName($partName);
            $relationshipSource = null;
            $relationshipSourceIsRelationshipPart = null;
            $relationshipSourceLoaded = null;
            $sourceExists = null;
            $issues = [];

            if (!$exists) {
                $issues[] = 'override-target-missing-part';
            }

            if ($relationshipPart) {
                $relationshipSource = OpcRelationships::sourcePartNameForRelationshipPart($partName);
                $relationshipSourceIsRelationshipPart = $relationshipSource !== '/'
                    && OpcRelationships::isRelationshipPartName($relationshipSource);
                $sourceExists = $relationshipSource === '/'
                    || $this->packagePartNameForEquivalent($relationshipSource) !== null;
                $relationshipSourceLoaded = $this->hasRelationshipsForSource($relationshipSource);

                if ($contentType !== self::RELATIONSHIP_PART_CONTENT_TYPE) {
                    $issues[] = 'invalid-relationship-content-type';
                }

                if ($relationshipSourceIsRelationshipPart) {
                    $issues[] = 'relationship-part-source';
                }

                if (!$sourceExists) {
                    $issues[] = 'relationship-override-source-missing';
                }
            }

            $preflight[] = [
                'partName' => $partName,
                'contentType' => $contentType,
                'exists' => $exists,
                'relationshipPart' => $relationshipPart,
                'relationshipSource' => $relationshipSource,
                'relationshipSourceIsRelationshipPart' => $relationshipSourceIsRelationshipPart,
                'relationshipSourceLoaded' => $relationshipSourceLoaded,
                'sourceExists' => $sourceExists,
                'valid' => $issues === [],
                'issues' => array_values(array_unique($issues)),
            ];
        }

        return $preflight;
    }

    /**
     * @return list<array{source:string, id:string, type:string, relationshipTypeKind:string, relationshipTypeScheme:?string, relationshipTypeValid:bool, relationshipTypeIssues:list<string>, target:string, targetPart:?string, contentType:?string, external:bool, exists:?bool, relationshipPartTarget:bool, externalTargetKind:?string, externalTargetScheme:?string, externalTargetAllowed:?bool, externalTargetRequiresBaseUri:?bool, externalTargetRewriteBasePart:?string, externalTargetRewriteReason:?string, valid:bool, issues:list<string>}>
     */
    public function preflightAllRelationshipTargets(?string $relationshipType = null): array
    {
        $preflight = [];
        foreach ($this->sourcePartNames() as $sourcePartName) {
            foreach ($this->preflightTargetsForSource($sourcePartName, $relationshipType) as $target) {
                $preflight[] = [
                    'source' => $sourcePartName,
                    'id' => $target['id'],
                    'type' => $target['type'],
                    'relationshipTypeKind' => $target['relationshipTypeKind'],
                    'relationshipTypeScheme' => $target['relationshipTypeScheme'],
                    'relationshipTypeValid' => $target['relationshipTypeValid'],
                    'relationshipTypeIssues' => $target['relationshipTypeIssues'],
                    'target' => $target['target'],
                    'targetPart' => self::targetPartFromPreflightTarget($target),
                    'contentType' => $target['contentType'],
                    'external' => $target['external'],
                    'exists' => $target['exists'],
                    'relationshipPartTarget' => $target['relationshipPartTarget'],
                    'externalTargetKind' => $target['externalTargetKind'],
                    'externalTargetScheme' => $target['externalTargetScheme'],
                    'externalTargetAllowed' => $target['externalTargetAllowed'],
                    'externalTargetRequiresBaseUri' => $target['externalTargetRequiresBaseUri'],
                    'externalTargetRewriteBasePart' => $target['externalTargetRewriteBasePart'],
                    'externalTargetRewriteReason' => $target['externalTargetRewriteReason'],
                    'valid' => $target['valid'],
                    'issues' => $target['issues'],
                ];
            }
        }

        return $preflight;
    }

    /**
     * @return list<array{type:string, relationshipCount:int, sourceCount:int, sources:list<string>, idsBySource:array<string, list<string>>, internalCount:int, externalCount:int, validCount:int, invalidCount:int, relationshipTypeValid:bool, relationshipTypeIssues:list<string>, targetParts:list<string>, contentTypes:list<string>, issues:list<string>}>
     */
    public function relationshipTypeInventory(?string $sourcePartName = null): array
    {
        $sourcePartNames = $sourcePartName === null
            ? $this->sourcePartNames()
            : [OpcPackagePath::canonicalPartName($sourcePartName, true)];

        $byType = [];
        foreach ($sourcePartNames as $source) {
            foreach ($this->preflightTargetsForSource($source) as $target) {
                $type = $target['type'];
                if (!isset($byType[$type])) {
                    $byType[$type] = [
                        'type' => $type,
                        'relationshipCount' => 0,
                        'sourceCount' => 0,
                        'sources' => [],
                        'idsBySource' => [],
                        'internalCount' => 0,
                        'externalCount' => 0,
                        'validCount' => 0,
                        'invalidCount' => 0,
                        'relationshipTypeValid' => true,
                        'relationshipTypeIssues' => [],
                        'targetParts' => [],
                        'contentTypes' => [],
                        'issues' => [],
                    ];
                }

                if (!isset($byType[$type]['idsBySource'][$source])) {
                    $byType[$type]['idsBySource'][$source] = [];
                    $byType[$type]['sources'][] = $source;
                    $byType[$type]['sourceCount']++;
                }

                $byType[$type]['relationshipCount']++;
                $byType[$type]['idsBySource'][$source][] = $target['id'];
                if ($target['external']) {
                    $byType[$type]['externalCount']++;
                } else {
                    $byType[$type]['internalCount']++;
                }

                if ($target['valid']) {
                    $byType[$type]['validCount']++;
                } else {
                    $byType[$type]['invalidCount']++;
                }

                if (!$target['relationshipTypeValid']) {
                    $byType[$type]['relationshipTypeValid'] = false;
                }

                foreach ($target['relationshipTypeIssues'] as $issue) {
                    self::appendUniqueString($byType[$type]['relationshipTypeIssues'], $issue);
                }

                $targetPart = self::targetPartFromPreflightTarget($target);
                if ($targetPart !== null) {
                    self::appendUniqueString($byType[$type]['targetParts'], $targetPart);
                }

                if ($target['contentType'] !== null) {
                    self::appendUniqueString($byType[$type]['contentTypes'], $target['contentType']);
                }

                foreach ($target['issues'] as $issue) {
                    self::appendUniqueString($byType[$type]['issues'], $issue);
                }
            }
        }

        ksort($byType, SORT_STRING);
        foreach ($byType as &$entry) {
            sort($entry['sources'], SORT_STRING);
            ksort($entry['idsBySource'], SORT_STRING);
            sort($entry['targetParts'], SORT_STRING);
            sort($entry['contentTypes'], SORT_STRING);
            sort($entry['relationshipTypeIssues'], SORT_STRING);
            sort($entry['issues'], SORT_STRING);
        }
        unset($entry);

        return array_values($byType);
    }

    /**
     * @return list<array{contentType:string, packagePartCount:int, overrideCount:int, defaultPartCount:int, relationshipPartCount:int, relationshipSourceCount:int, relationshipTargetReferenceCount:int, relationshipTargetPartCount:int, reachableTargetCount:int, missingOverrideCount:int, invalidPackagePartCount:int, parts:list<string>, overrideParts:list<string>, defaultParts:list<string>, relationshipParts:list<string>, relationshipSources:list<string>, relationshipTargetParts:list<string>, reachableTargetParts:list<string>, missingOverrideParts:list<string>, relationshipTargetReferences:list<array{source:string, id:string, targetPart:string, valid:bool, issues:list<string>}>, issues:list<string>}>
     */
    public function contentTypeInventory(): array
    {
        $inventory = [];
        $overridePartNamesByEquivalenceKey = [];
        foreach ($this->contentTypes->overrides() as $partName => $contentType) {
            $overridePartNamesByEquivalenceKey[self::partNameEquivalenceKey($partName)] = $partName;
            $entry =& self::contentTypeInventoryEntry($inventory, $contentType);
            self::appendUniqueString($entry['overrideParts'], $partName);
            unset($entry);
        }

        foreach ($this->preflightPackageParts() as $part) {
            $contentType = $part['contentType'];
            if ($contentType === null) {
                continue;
            }

            $partName = $part['partName'];
            $entry =& self::contentTypeInventoryEntry($inventory, $contentType);
            self::appendUniqueString($entry['parts'], $partName);

            if (isset($overridePartNamesByEquivalenceKey[self::partNameEquivalenceKey($partName)])) {
                self::appendUniqueString($entry['overrideParts'], $partName);
            } else {
                self::appendUniqueString($entry['defaultParts'], $partName);
            }

            if ($part['relationshipPart']) {
                self::appendUniqueString($entry['relationshipParts'], $partName);
                if ($part['relationshipSource'] !== null) {
                    self::appendUniqueString($entry['relationshipSources'], $part['relationshipSource']);
                }
            }

            if (!$part['valid']) {
                $entry['invalidPackagePartCount']++;
                foreach ($part['issues'] as $issue) {
                    self::appendUniqueString($entry['issues'], $issue);
                }
            }
            unset($entry);
        }

        foreach ($this->preflightContentTypeOverrides() as $override) {
            $entry =& self::contentTypeInventoryEntry($inventory, $override['contentType']);
            self::appendUniqueString($entry['overrideParts'], $override['partName']);
            if (!$override['exists']) {
                self::appendUniqueString($entry['missingOverrideParts'], $override['partName']);
            }

            foreach ($override['issues'] as $issue) {
                self::appendUniqueString($entry['issues'], $issue);
            }
            unset($entry);
        }

        foreach ($this->preflightAllRelationshipTargets() as $target) {
            if ($target['external'] || $target['targetPart'] === null || $target['contentType'] === null) {
                continue;
            }

            $entry =& self::contentTypeInventoryEntry($inventory, $target['contentType']);
            self::appendUniqueString($entry['relationshipTargetParts'], $target['targetPart']);
            $entry['relationshipTargetReferences'][] = [
                'source' => $target['source'],
                'id' => $target['id'],
                'targetPart' => $target['targetPart'],
                'valid' => $target['valid'],
                'issues' => $target['issues'],
            ];
            foreach ($target['issues'] as $issue) {
                self::appendUniqueString($entry['issues'], $issue);
            }
            unset($entry);
        }

        foreach ($this->reachableTargetsForSource('/') as $target) {
            if ($target['external'] || $target['targetPart'] === null || $target['contentType'] === null) {
                continue;
            }

            $entry =& self::contentTypeInventoryEntry($inventory, $target['contentType']);
            self::appendUniqueString($entry['reachableTargetParts'], $target['targetPart']);
            foreach ($target['issues'] as $issue) {
                self::appendUniqueString($entry['issues'], $issue);
            }
            unset($entry);
        }

        ksort($inventory, SORT_STRING);
        foreach ($inventory as &$entry) {
            sort($entry['parts'], SORT_STRING);
            sort($entry['overrideParts'], SORT_STRING);
            sort($entry['defaultParts'], SORT_STRING);
            sort($entry['relationshipParts'], SORT_STRING);
            sort($entry['relationshipSources'], SORT_STRING);
            sort($entry['relationshipTargetParts'], SORT_STRING);
            sort($entry['reachableTargetParts'], SORT_STRING);
            sort($entry['missingOverrideParts'], SORT_STRING);
            sort($entry['issues'], SORT_STRING);
            usort(
                $entry['relationshipTargetReferences'],
                static fn (array $left, array $right): int => [
                    $left['source'],
                    $left['id'],
                    $left['targetPart'],
                ] <=> [
                    $right['source'],
                    $right['id'],
                    $right['targetPart'],
                ]
            );

            $entry['packagePartCount'] = count($entry['parts']);
            $entry['overrideCount'] = count($entry['overrideParts']);
            $entry['defaultPartCount'] = count($entry['defaultParts']);
            $entry['relationshipPartCount'] = count($entry['relationshipParts']);
            $entry['relationshipSourceCount'] = count($entry['relationshipSources']);
            $entry['relationshipTargetReferenceCount'] = count($entry['relationshipTargetReferences']);
            $entry['relationshipTargetPartCount'] = count($entry['relationshipTargetParts']);
            $entry['reachableTargetCount'] = count($entry['reachableTargetParts']);
            $entry['missingOverrideCount'] = count($entry['missingOverrideParts']);
        }
        unset($entry);

        return array_values($inventory);
    }

    /**
     * @return array{valid:bool, packagePartsValid:bool, contentTypeOverridesValid:bool, relationshipTargetsValid:bool, packageParts:list<array{partName:string, contentType:?string, relationshipPart:bool, relationshipSource:?string, relationshipSourceIsRelationshipPart:?bool, relationshipSourceLoaded:?bool, sourceExists:?bool, valid:bool, issues:list<string>}>, contentTypeOverrides:list<array{partName:string, contentType:string, exists:bool, relationshipPart:bool, relationshipSource:?string, relationshipSourceIsRelationshipPart:?bool, relationshipSourceLoaded:?bool, sourceExists:?bool, valid:bool, issues:list<string>}>, relationshipTargets:list<array{source:string, id:string, type:string, relationshipTypeKind:string, relationshipTypeScheme:?string, relationshipTypeValid:bool, relationshipTypeIssues:list<string>, target:string, targetPart:?string, contentType:?string, external:bool, exists:?bool, relationshipPartTarget:bool, externalTargetKind:?string, externalTargetScheme:?string, externalTargetAllowed:?bool, externalTargetRequiresBaseUri:?bool, externalTargetRewriteBasePart:?string, externalTargetRewriteReason:?string, valid:bool, issues:list<string>}>}
     */
    public function preflightPackageConsistency(): array
    {
        $packageParts = $this->preflightPackageParts();
        $contentTypeOverrides = $this->preflightContentTypeOverrides();
        $relationshipTargets = $this->preflightAllRelationshipTargets();
        $packagePartsValid = self::allRowsValid($packageParts);
        $contentTypeOverridesValid = self::allRowsValid($contentTypeOverrides);
        $relationshipTargetsValid = self::allRowsValid($relationshipTargets);

        return [
            'valid' => $packagePartsValid && $contentTypeOverridesValid && $relationshipTargetsValid,
            'packagePartsValid' => $packagePartsValid,
            'contentTypeOverridesValid' => $contentTypeOverridesValid,
            'relationshipTargetsValid' => $relationshipTargetsValid,
            'packageParts' => $packageParts,
            'contentTypeOverrides' => $contentTypeOverrides,
            'relationshipTargets' => $relationshipTargets,
        ];
    }

    /**
     * @param list<string> $expectedContentTypes
     * @return array{relationshipCount:int, expectedContentTypes:list<string>, valid:bool, issues:list<string>, relationships:list<array{source:string, id:string, type:string, relationshipTypeKind:string, relationshipTypeScheme:?string, relationshipTypeValid:bool, relationshipTypeIssues:list<string>, target:string, targetPart:?string, contentType:?string, external:bool, exists:?bool, relationshipPartTarget:bool, externalTargetKind:?string, externalTargetScheme:?string, externalTargetAllowed:?bool, externalTargetRequiresBaseUri:?bool, externalTargetRewriteBasePart:?string, externalTargetRewriteReason:?string, valid:bool, issues:list<string>}>}
     */
    public function preflightOfficeDocumentRoot(array $expectedContentTypes = []): array
    {
        $expectedContentTypes = array_values(array_unique($expectedContentTypes));
        $targets = $this->preflightTargetsForSource('/', self::OFFICE_DOCUMENT_RELATIONSHIP_TYPE);
        $relationshipCount = count($targets);
        $issues = [];
        if ($relationshipCount === 0) {
            $issues[] = 'missing-office-document-relationship';
        } elseif ($relationshipCount > 1) {
            $issues[] = 'multiple-office-document-relationships';
        }

        $relationships = [];
        foreach ($targets as $target) {
            $targetIssues = $target['issues'];
            if ($target['external']) {
                $targetIssues[] = 'external-office-document-target';
            }

            if (
                $expectedContentTypes !== []
                && !$target['external']
                && $target['contentType'] !== null
                && !in_array($target['contentType'], $expectedContentTypes, true)
            ) {
                $targetIssues[] = 'invalid-office-document-content-type';
            }

            $targetIssues = array_values(array_unique($targetIssues));
            $relationships[] = [
                'source' => '/',
                'id' => $target['id'],
                'type' => $target['type'],
                'relationshipTypeKind' => $target['relationshipTypeKind'],
                'relationshipTypeScheme' => $target['relationshipTypeScheme'],
                'relationshipTypeValid' => $target['relationshipTypeValid'],
                'relationshipTypeIssues' => $target['relationshipTypeIssues'],
                'target' => $target['target'],
                'targetPart' => self::targetPartFromPreflightTarget($target),
                'contentType' => $target['contentType'],
                'external' => $target['external'],
                'exists' => $target['exists'],
                'relationshipPartTarget' => $target['relationshipPartTarget'],
                'externalTargetKind' => $target['externalTargetKind'],
                'externalTargetScheme' => $target['externalTargetScheme'],
                'externalTargetAllowed' => $target['externalTargetAllowed'],
                'externalTargetRequiresBaseUri' => $target['externalTargetRequiresBaseUri'],
                'externalTargetRewriteBasePart' => $target['externalTargetRewriteBasePart'],
                'externalTargetRewriteReason' => $target['externalTargetRewriteReason'],
                'valid' => $targetIssues === [],
                'issues' => $targetIssues,
            ];
        }

        return [
            'relationshipCount' => $relationshipCount,
            'expectedContentTypes' => $expectedContentTypes,
            'valid' => $issues === [] && array_reduce(
                $relationships,
                static fn (bool $valid, array $relationship): bool => $valid && $relationship['valid'],
                true,
            ),
            'issues' => $issues,
            'relationships' => $relationships,
        ];
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
     * @return list<array{source:string, id:string, type:string, kind:string, target:string, targetPart:?string, contentType:?string, expectedContentType:string, external:bool, exists:?bool, relationshipPartTarget:bool, relationshipTypeKind:string, relationshipTypeScheme:?string, relationshipTypeValid:bool, relationshipTypeIssues:list<string>, externalTargetKind:?string, externalTargetScheme:?string, externalTargetAllowed:?bool, externalTargetRequiresBaseUri:?bool, externalTargetRewriteBasePart:?string, externalTargetRewriteReason:?string, valid:bool, issues:list<string>}>
     */
    public function preflightEmbeddedPackages(string $sourcePartName = '/'): array
    {
        $sourcePartName = OpcPackagePath::canonicalPartName($sourcePartName, true);
        $preflight = [];

        foreach ($this->preflightTargetsForSource($sourcePartName) as $target) {
            $kind = null;
            $expectedContentType = null;
            $invalidContentTypeIssue = null;

            if ($target['type'] === self::EMBEDDED_PACKAGE_RELATIONSHIP_TYPE) {
                $kind = 'embedded-package';
                $expectedContentType = self::EMBEDDED_PACKAGE_CONTENT_TYPE;
                $invalidContentTypeIssue = 'invalid-embedded-package-content-type';
            } elseif ($target['type'] === self::EMBEDDED_OBJECT_RELATIONSHIP_TYPE) {
                $kind = 'embedded-object';
                $expectedContentType = self::EMBEDDED_OBJECT_CONTENT_TYPE;
                $invalidContentTypeIssue = 'invalid-embedded-object-content-type';
            } else {
                continue;
            }

            $issues = $target['issues'];
            if (!$target['external'] && $target['contentType'] !== null && $target['contentType'] !== $expectedContentType) {
                $issues[] = $invalidContentTypeIssue;
            }
            $issues = array_values(array_unique($issues));

            $preflight[] = [
                'source' => $sourcePartName,
                'id' => $target['id'],
                'type' => $target['type'],
                'kind' => $kind,
                'target' => $target['target'],
                'targetPart' => self::targetPartFromPreflightTarget($target),
                'contentType' => $target['contentType'],
                'expectedContentType' => $expectedContentType,
                'external' => $target['external'],
                'exists' => $target['exists'],
                'relationshipPartTarget' => $target['relationshipPartTarget'],
                'relationshipTypeKind' => $target['relationshipTypeKind'],
                'relationshipTypeScheme' => $target['relationshipTypeScheme'],
                'relationshipTypeValid' => $target['relationshipTypeValid'],
                'relationshipTypeIssues' => $target['relationshipTypeIssues'],
                'externalTargetKind' => $target['externalTargetKind'],
                'externalTargetScheme' => $target['externalTargetScheme'],
                'externalTargetAllowed' => $target['externalTargetAllowed'],
                'externalTargetRequiresBaseUri' => $target['externalTargetRequiresBaseUri'],
                'externalTargetRewriteBasePart' => $target['externalTargetRewriteBasePart'],
                'externalTargetRewriteReason' => $target['externalTargetRewriteReason'],
                'valid' => $issues === [],
                'issues' => $issues,
            ];
        }

        return $preflight;
    }

    /**
     * @param list<string> $sourceIds
     * @param list<string> $sourceTypes
     * @return array{source:string, sourceIds:list<string>, sourceTypes:list<string>, unmatchedSourceIds:list<string>, unmatchedSourceTypes:list<string>, valid:bool, issues:list<string>, relationships:list<array{source:string, id:string, type:string, selectedBySourceId:bool, selectedBySourceType:bool, relationshipTypeKind:string, relationshipTypeScheme:?string, relationshipTypeValid:bool, relationshipTypeIssues:list<string>, target:string, targetPart:?string, contentType:?string, external:bool, exists:?bool, relationshipPartTarget:bool, externalTargetKind:?string, externalTargetScheme:?string, externalTargetAllowed:?bool, externalTargetRequiresBaseUri:?bool, externalTargetRewriteBasePart:?string, externalTargetRewriteReason:?string, valid:bool, issues:list<string>}>}
     */
    public function preflightRelationshipSelector(string $sourcePartName = '/', array $sourceIds = [], array $sourceTypes = []): array
    {
        $sourcePartName = $this->relationshipSourceNameForEquivalent($sourcePartName);
        $sourceIds = self::normalizeSelectorValues($sourceIds, 'OPC relationship selector SourceId');
        $sourceTypes = self::normalizeSelectorValues($sourceTypes, 'OPC relationship selector SourceType');

        foreach ($sourceIds as $sourceId) {
            self::assertSelectorSourceId($sourceId);
        }

        $targets = $this->preflightTargetsForSource($sourcePartName);
        $knownIds = [];
        $knownTypes = [];
        $relationships = [];

        foreach ($targets as $target) {
            $knownIds[$target['id']] = true;
            $knownTypes[$target['type']] = true;

            $selectedBySourceId = in_array($target['id'], $sourceIds, true);
            $selectedBySourceType = in_array($target['type'], $sourceTypes, true);
            if (!$selectedBySourceId && !$selectedBySourceType) {
                continue;
            }

            $relationships[] = [
                'source' => $sourcePartName,
                'id' => $target['id'],
                'type' => $target['type'],
                'selectedBySourceId' => $selectedBySourceId,
                'selectedBySourceType' => $selectedBySourceType,
                'relationshipTypeKind' => $target['relationshipTypeKind'],
                'relationshipTypeScheme' => $target['relationshipTypeScheme'],
                'relationshipTypeValid' => $target['relationshipTypeValid'],
                'relationshipTypeIssues' => $target['relationshipTypeIssues'],
                'target' => $target['target'],
                'targetPart' => self::targetPartFromPreflightTarget($target),
                'contentType' => $target['contentType'],
                'external' => $target['external'],
                'exists' => $target['exists'],
                'relationshipPartTarget' => $target['relationshipPartTarget'],
                'externalTargetKind' => $target['externalTargetKind'],
                'externalTargetScheme' => $target['externalTargetScheme'],
                'externalTargetAllowed' => $target['externalTargetAllowed'],
                'externalTargetRequiresBaseUri' => $target['externalTargetRequiresBaseUri'],
                'externalTargetRewriteBasePart' => $target['externalTargetRewriteBasePart'],
                'externalTargetRewriteReason' => $target['externalTargetRewriteReason'],
                'valid' => $target['valid'],
                'issues' => $target['issues'],
            ];
        }

        $unmatchedSourceIds = array_values(array_filter(
            $sourceIds,
            static fn (string $sourceId): bool => !isset($knownIds[$sourceId]),
        ));
        $unmatchedSourceTypes = array_values(array_filter(
            $sourceTypes,
            static fn (string $sourceType): bool => !isset($knownTypes[$sourceType]),
        ));

        $issues = [];
        if (!isset($this->relationshipsBySource[$sourcePartName])) {
            $issues[] = 'relationship-source-not-loaded';
        }

        if ($sourceIds === [] && $sourceTypes === []) {
            $issues[] = 'empty-relationship-selector';
        }

        if ($unmatchedSourceIds !== []) {
            $issues[] = 'unmatched-source-id';
        }

        if ($unmatchedSourceTypes !== []) {
            $issues[] = 'unmatched-source-type';
        }

        $relationshipsValid = array_reduce(
            $relationships,
            static fn (bool $valid, array $relationship): bool => $valid && $relationship['valid'],
            true,
        );

        return [
            'source' => $sourcePartName,
            'sourceIds' => $sourceIds,
            'sourceTypes' => $sourceTypes,
            'unmatchedSourceIds' => $unmatchedSourceIds,
            'unmatchedSourceTypes' => $unmatchedSourceTypes,
            'valid' => $issues === [] && $relationshipsValid,
            'issues' => $issues,
            'relationships' => $relationships,
        ];
    }

    /**
     * @param list<string> $sourceIds
     * @param list<string> $sourceTypes
     * @return array{source:string, relationshipPartName:string, transformAlgorithm:string, sourceIds:list<string>, sourceTypes:list<string>, relationshipIds:list<string>, relationshipCount:int, selectorValid:bool, relationshipTargetsValid:bool, valid:bool, issues:list<string>, relationships:list<array{source:string, id:string, type:string, selectedBySourceId:bool, selectedBySourceType:bool, relationshipTypeKind:string, relationshipTypeScheme:?string, relationshipTypeValid:bool, relationshipTypeIssues:list<string>, target:string, targetPart:?string, contentType:?string, external:bool, exists:?bool, relationshipPartTarget:bool, externalTargetKind:?string, externalTargetScheme:?string, externalTargetAllowed:?bool, externalTargetRequiresBaseUri:?bool, externalTargetRewriteBasePart:?string, externalTargetRewriteReason:?string, valid:bool, issues:list<string>}>, relationshipXml:?string}
     */
    public function materializeRelationshipTransform(string $sourcePartName = '/', array $sourceIds = [], array $sourceTypes = []): array
    {
        $sourcePartName = $this->relationshipSourceNameForEquivalent($sourcePartName);
        $selector = $this->preflightRelationshipSelector($sourcePartName, $sourceIds, $sourceTypes);
        $relationships = $this->relationshipsBySource[$sourcePartName] ?? null;

        $selectedForTransform = [];
        if ($relationships instanceof OpcRelationships) {
            foreach ($relationships->all() as $relationship) {
                if (
                    in_array($relationship->id, $selector['sourceIds'], true)
                    || in_array($relationship->type, $selector['sourceTypes'], true)
                ) {
                    $selectedForTransform[] = $relationship;
                }
            }
        }

        usort(
            $selectedForTransform,
            static fn (OpcRelationship $left, OpcRelationship $right): int => strcmp($left->id, $right->id),
        );

        $relationshipTargetsValid = array_reduce(
            $selector['relationships'],
            static fn (bool $valid, array $relationship): bool => $valid && $relationship['valid'],
            true,
        );
        $issues = $selector['issues'];
        if (!$relationshipTargetsValid) {
            $issues[] = 'selected-relationship-target-issues';
        }

        return [
            'source' => $sourcePartName,
            'relationshipPartName' => OpcRelationships::relationshipPartNameForSource($sourcePartName),
            'transformAlgorithm' => self::RELATIONSHIP_TRANSFORM_ALGORITHM,
            'sourceIds' => $selector['sourceIds'],
            'sourceTypes' => $selector['sourceTypes'],
            'relationshipIds' => array_map(
                static fn (OpcRelationship $relationship): string => $relationship->id,
                $selectedForTransform,
            ),
            'relationshipCount' => count($selectedForTransform),
            'selectorValid' => $selector['valid'],
            'relationshipTargetsValid' => $relationshipTargetsValid,
            'valid' => $issues === [],
            'issues' => array_values(array_unique($issues)),
            'relationships' => $selector['relationships'],
            'relationshipXml' => $relationships instanceof OpcRelationships
                ? self::relationshipTransformXml($selectedForTransform)
                : null,
        ];
    }

    /**
     * @return list<array{signaturePart:string, referenceIndex:int, referenceUri:string, relationshipPartName:?string, referenceTargetContentType:?string, referenceContentType:?string, referenceContentTypeMatches:?bool, source:?string, transformAlgorithm:string, sourceIds:list<string>, sourceTypes:list<string>, followingCanonicalizationAlgorithm:?string, followedByCanonicalization:bool, relationshipIds:list<string>, relationshipCount:int, selectorValid:?bool, relationshipTargetsValid:?bool, valid:bool, issues:list<string>, parseError:?string, relationships:list<array{source:string, id:string, type:string, selectedBySourceId:bool, selectedBySourceType:bool, relationshipTypeKind:string, relationshipTypeScheme:?string, relationshipTypeValid:bool, relationshipTypeIssues:list<string>, target:string, targetPart:?string, contentType:?string, external:bool, exists:?bool, relationshipPartTarget:bool, externalTargetKind:?string, externalTargetScheme:?string, externalTargetAllowed:?bool, externalTargetRequiresBaseUri:?bool, externalTargetRewriteBasePart:?string, externalTargetRewriteReason:?string, valid:bool, issues:list<string>}>, relationshipXml:?string}>
     */
    public function preflightSignatureRelationshipTransforms(string $signaturePartName): array
    {
        $signaturePartName = OpcPackagePath::canonicalPartName($signaturePartName);
        if (!$this->package->has($signaturePartName)) {
            throw new \RuntimeException('OPC signature part not found: ' . $signaturePartName);
        }

        $dom = XmlHtmlDom::loadXmlDocument($this->package->read($signaturePartName), 'OPC digital signature XML');
        $rows = [];
        $rowsByRelationshipPart = [];
        $referenceIndex = -1;

        foreach ($dom->getElementsByTagNameNS(self::XML_SIGNATURE_NAMESPACE_URI, 'Reference') as $reference) {
            if (!$reference instanceof \DOMElement) {
                continue;
            }

            $referenceIndex++;
            $referenceUri = $reference->hasAttribute('URI') ? $reference->getAttribute('URI') : '';
            $transforms = self::xmlSignatureReferenceTransforms($reference);
            foreach ($transforms as $transformIndex => $transform) {
                if ($transform->getAttribute('Algorithm') !== self::RELATIONSHIP_TRANSFORM_ALGORITHM) {
                    continue;
                }

                $relationshipPartName = null;
                $referenceTargetContentType = null;
                $referenceContentType = null;
                $referenceContentTypeMatches = null;
                $sourcePartName = null;
                $parseError = null;
                $issues = [];

                if ($referenceUri === '') {
                    $issues[] = 'missing-reference-uri';
                } else {
                    try {
                        $resolvedReference = OpcPackagePath::resolveInternalTarget($signaturePartName, $referenceUri);
                        $relationshipPartName = OpcPackagePath::stripQueryAndFragment($resolvedReference);
                        if (!OpcRelationships::isRelationshipPartName($relationshipPartName)) {
                            $issues[] = 'reference-not-relationship-part';
                        } else {
                            $referenceTargetContentType = $this->contentTypes->contentTypeForPart($relationshipPartName);
                            $sourcePartName = OpcRelationships::sourcePartNameForRelationshipPart($relationshipPartName);
                            $rowsByRelationshipPart[$relationshipPartName][] = count($rows);
                        }
                    } catch (\InvalidArgumentException $exception) {
                        $issues[] = 'invalid-reference-uri';
                        $parseError = $exception->getMessage();
                    }
                }

                $referenceContentTypeQuery = self::referenceContentTypeQuery($referenceUri);
                $referenceContentType = $referenceContentTypeQuery['contentType'];
                $issues = array_merge($issues, $referenceContentTypeQuery['issues']);
                if ($parseError === null && $referenceContentTypeQuery['parseError'] !== null) {
                    $parseError = $referenceContentTypeQuery['parseError'];
                }

                if ($referenceContentType !== null) {
                    $referenceContentTypeMatches = $referenceTargetContentType === $referenceContentType;
                    if (!$referenceContentTypeMatches) {
                        $issues[] = 'reference-content-type-mismatch';
                    }
                }

                $selector = self::relationshipTransformSelectors($transform);
                $issues = array_merge($issues, $selector['issues']);
                $followingCanonicalizationAlgorithm = self::followingTransformAlgorithm($transforms, $transformIndex);
                $followedByCanonicalization = self::isCanonicalizationTransformAlgorithm($followingCanonicalizationAlgorithm);
                if (!$followedByCanonicalization) {
                    $issues[] = 'relationship-transform-not-followed-by-canonicalization';
                }

                $relationshipIds = [];
                $relationshipCount = 0;
                $selectorValid = null;
                $relationshipTargetsValid = null;
                $relationships = [];
                $relationshipXml = null;

                if ($sourcePartName !== null) {
                    try {
                        $materialized = $this->materializeRelationshipTransform(
                            $sourcePartName,
                            $selector['sourceIds'],
                            $selector['sourceTypes'],
                        );
                        $relationshipIds = $materialized['relationshipIds'];
                        $relationshipCount = $materialized['relationshipCount'];
                        $selectorValid = $materialized['selectorValid'];
                        $relationshipTargetsValid = $materialized['relationshipTargetsValid'];
                        $relationships = $materialized['relationships'];
                        $relationshipXml = $materialized['relationshipXml'];
                        $issues = array_merge($issues, $materialized['issues']);
                    } catch (\InvalidArgumentException $exception) {
                        $issues[] = 'invalid-relationship-transform-selector';
                        $parseError = $exception->getMessage();
                    }
                }

                $issues = array_values(array_unique($issues));
                $rows[] = [
                    'signaturePart' => $signaturePartName,
                    'referenceIndex' => $referenceIndex,
                    'referenceUri' => $referenceUri,
                    'relationshipPartName' => $relationshipPartName,
                    'referenceTargetContentType' => $referenceTargetContentType,
                    'referenceContentType' => $referenceContentType,
                    'referenceContentTypeMatches' => $referenceContentTypeMatches,
                    'source' => $sourcePartName,
                    'transformAlgorithm' => self::RELATIONSHIP_TRANSFORM_ALGORITHM,
                    'sourceIds' => $selector['sourceIds'],
                    'sourceTypes' => $selector['sourceTypes'],
                    'followingCanonicalizationAlgorithm' => $followingCanonicalizationAlgorithm,
                    'followedByCanonicalization' => $followedByCanonicalization,
                    'relationshipIds' => $relationshipIds,
                    'relationshipCount' => $relationshipCount,
                    'selectorValid' => $selectorValid,
                    'relationshipTargetsValid' => $relationshipTargetsValid,
                    'valid' => $issues === [],
                    'issues' => $issues,
                    'parseError' => $parseError,
                    'relationships' => $relationships,
                    'relationshipXml' => $relationshipXml,
                ];
            }
        }

        foreach ($rowsByRelationshipPart as $rowIndexes) {
            if (count($rowIndexes) < 2) {
                continue;
            }

            foreach ($rowIndexes as $rowIndex) {
                $rows[$rowIndex]['issues'][] = 'multiple-relationship-transforms-for-part';
                $rows[$rowIndex]['issues'] = array_values(array_unique($rows[$rowIndex]['issues']));
                $rows[$rowIndex]['valid'] = false;
            }
        }

        return $rows;
    }

    /**
     * @return list<array{source:string, depth:int, id:string, type:string, relationshipTypeKind:string, relationshipTypeScheme:?string, relationshipTypeValid:bool, relationshipTypeIssues:list<string>, target:string, targetPart:?string, contentType:?string, external:bool, exists:?bool, relationshipPartTarget:bool, externalTargetKind:?string, externalTargetScheme:?string, externalTargetAllowed:?bool, externalTargetRequiresBaseUri:?bool, externalTargetRewriteBasePart:?string, externalTargetRewriteReason:?string, valid:bool, issues:list<string>}>
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
            $source = $this->relationshipSourceNameForEquivalent($source);
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
                    'relationshipTypeKind' => $target['relationshipTypeKind'],
                    'relationshipTypeScheme' => $target['relationshipTypeScheme'],
                    'relationshipTypeValid' => $target['relationshipTypeValid'],
                    'relationshipTypeIssues' => $target['relationshipTypeIssues'],
                    'target' => $target['target'],
                    'targetPart' => $targetPart,
                    'contentType' => $target['contentType'],
                    'external' => $target['external'],
                    'exists' => $target['exists'],
                    'relationshipPartTarget' => $target['relationshipPartTarget'],
                    'externalTargetKind' => $target['externalTargetKind'],
                    'externalTargetScheme' => $target['externalTargetScheme'],
                    'externalTargetAllowed' => $target['externalTargetAllowed'],
                    'externalTargetRequiresBaseUri' => $target['externalTargetRequiresBaseUri'],
                    'externalTargetRewriteBasePart' => $target['externalTargetRewriteBasePart'],
                    'externalTargetRewriteReason' => $target['externalTargetRewriteReason'],
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

    /**
     * @return list<\DOMElement>
     */
    private static function xmlSignatureReferenceTransforms(\DOMElement $reference): array
    {
        $transforms = [];
        foreach ($reference->childNodes as $child) {
            if (
                !$child instanceof \DOMElement
                || $child->namespaceURI !== self::XML_SIGNATURE_NAMESPACE_URI
                || $child->localName !== 'Transforms'
            ) {
                continue;
            }

            foreach ($child->childNodes as $transform) {
                if (
                    $transform instanceof \DOMElement
                    && $transform->namespaceURI === self::XML_SIGNATURE_NAMESPACE_URI
                    && $transform->localName === 'Transform'
                ) {
                    $transforms[] = $transform;
                }
            }
        }

        return $transforms;
    }

    /**
     * @return array{sourceIds:list<string>, sourceTypes:list<string>, issues:list<string>}
     */
    private static function relationshipTransformSelectors(\DOMElement $transform): array
    {
        $sourceIds = [];
        $sourceTypes = [];
        $issues = [];

        foreach ($transform->childNodes as $child) {
            if (($child instanceof \DOMText || $child instanceof \DOMCdataSection) && trim($child->nodeValue ?? '') === '') {
                continue;
            }

            if (!$child instanceof \DOMElement) {
                if (($child->nodeValue ?? '') !== '') {
                    $issues[] = 'unsupported-relationship-transform-content';
                }
                continue;
            }

            if ($child->namespaceURI !== self::DIGITAL_SIGNATURE_NAMESPACE_URI) {
                $issues[] = 'unsupported-relationship-transform-child';
                continue;
            }

            if ($child->localName === 'RelationshipReference') {
                $issues = array_merge($issues, self::relationshipTransformSelectorShapeIssues($child, ['SourceId']));
                $sourceId = $child->getAttribute('SourceId');
                if ($sourceId === '') {
                    $issues[] = 'missing-source-id';
                    continue;
                }

                if (!in_array($sourceId, $sourceIds, true)) {
                    $sourceIds[] = $sourceId;
                }
                continue;
            }

            if ($child->localName === 'RelationshipGroupReference' || $child->localName === 'RelationshipsGroupReference') {
                $issues = array_merge($issues, self::relationshipTransformSelectorShapeIssues($child, ['SourceType']));
                $sourceType = $child->getAttribute('SourceType');
                if ($sourceType === '') {
                    $issues[] = 'missing-source-type';
                    continue;
                }

                if (!in_array($sourceType, $sourceTypes, true)) {
                    $sourceTypes[] = $sourceType;
                }
                continue;
            }

            $issues[] = 'unsupported-relationship-transform-child';
        }

        return [
            'sourceIds' => $sourceIds,
            'sourceTypes' => $sourceTypes,
            'issues' => array_values(array_unique($issues)),
        ];
    }

    /**
     * @param list<string> $allowedAttributes
     * @return list<string>
     */
    private static function relationshipTransformSelectorShapeIssues(\DOMElement $element, array $allowedAttributes): array
    {
        $issues = [];
        foreach ($element->attributes as $attribute) {
            if (!$attribute instanceof \DOMAttr) {
                continue;
            }

            if (OpcMarkupCompatibility::isNamespaceDeclaration($attribute)) {
                continue;
            }

            if (($attribute->namespaceURI ?? '') !== '' || !in_array($attribute->name, $allowedAttributes, true)) {
                self::appendUniqueString($issues, 'unsupported-relationship-transform-selector-attribute');
            }
        }

        foreach ($element->childNodes as $child) {
            if ($child instanceof \DOMElement) {
                self::appendUniqueString($issues, 'unsupported-relationship-transform-selector-child');
                continue;
            }

            if (($child instanceof \DOMText || $child instanceof \DOMCdataSection) && trim($child->nodeValue ?? '') !== '') {
                self::appendUniqueString($issues, 'unsupported-relationship-transform-selector-content');
            }
        }

        return $issues;
    }

    /**
     * @param list<\DOMElement> $transforms
     */
    private static function followingTransformAlgorithm(array $transforms, int $transformIndex): ?string
    {
        $following = $transforms[$transformIndex + 1] ?? null;
        if (!$following instanceof \DOMElement || !$following->hasAttribute('Algorithm')) {
            return null;
        }

        return $following->getAttribute('Algorithm');
    }

    private static function isCanonicalizationTransformAlgorithm(?string $algorithm): bool
    {
        return in_array($algorithm, [
            'http://www.w3.org/TR/2001/REC-xml-c14n-20010315',
            'http://www.w3.org/TR/2001/REC-xml-c14n-20010315#WithComments',
            'http://www.w3.org/2001/10/xml-exc-c14n#',
            'http://www.w3.org/2001/10/xml-exc-c14n#WithComments',
            'http://www.w3.org/2006/12/xml-c14n11',
            'http://www.w3.org/2006/12/xml-c14n11#WithComments',
        ], true);
    }

    /**
     * @return array{contentType:?string, issues:list<string>, parseError:?string}
     */
    private static function referenceContentTypeQuery(string $referenceUri): array
    {
        $queryStart = strpos($referenceUri, '?');
        if ($queryStart === false) {
            return [
                'contentType' => null,
                'issues' => [],
                'parseError' => null,
            ];
        }

        $queryEnd = strpos($referenceUri, '#', $queryStart + 1);
        $query = $queryEnd === false
            ? substr($referenceUri, $queryStart + 1)
            : substr($referenceUri, $queryStart + 1, $queryEnd - $queryStart - 1);

        $contentType = null;
        $issues = [];
        $parseError = null;
        foreach (explode('&', $query) as $pair) {
            if ($pair === '') {
                continue;
            }

            $parts = explode('=', $pair, 2);
            try {
                $name = self::decodeReferenceQueryComponent($parts[0]);
                $value = self::decodeReferenceQueryComponent($parts[1] ?? '');
            } catch (\InvalidArgumentException $exception) {
                self::appendUniqueString($issues, 'invalid-reference-content-type-query');
                $parseError = $exception->getMessage();
                continue;
            }

            if ($name !== 'ContentType') {
                continue;
            }

            if ($contentType !== null) {
                self::appendUniqueString($issues, 'duplicate-reference-content-type-query');
                continue;
            }

            if ($value === '') {
                self::appendUniqueString($issues, 'empty-reference-content-type-query');
                continue;
            }

            $contentType = $value;
        }

        return [
            'contentType' => $contentType,
            'issues' => $issues,
            'parseError' => $parseError,
        ];
    }

    private static function decodeReferenceQueryComponent(string $value): string
    {
        if (preg_match('/%(?![0-9A-Fa-f]{2})/', $value) === 1) {
            throw new \InvalidArgumentException('OPC signature reference query contains malformed percent escape');
        }

        return rawurldecode($value);
    }

    /**
     * @param list<string> $values
     * @return list<string>
     */
    private static function normalizeSelectorValues(array $values, string $label): array
    {
        $normalized = [];
        foreach ($values as $value) {
            if (!is_string($value)) {
                throw new \InvalidArgumentException($label . ' values must be strings');
            }

            if ($value === '') {
                throw new \InvalidArgumentException($label . ' values must be non-empty strings');
            }

            if (!in_array($value, $normalized, true)) {
                $normalized[] = $value;
            }
        }

        return $normalized;
    }

    private static function assertSelectorSourceId(string $sourceId): void
    {
        if (preg_match('/^[A-Za-z_][A-Za-z0-9._-]*$/D', $sourceId) !== 1) {
            throw new \InvalidArgumentException('OPC relationship selector SourceId must be an XML NCName-style identifier');
        }
    }

    /**
     * @param list<OpcRelationship> $relationships
     */
    private static function relationshipTransformXml(array $relationships): string
    {
        $xml = '<Relationships xmlns="' . self::escapeXmlAttribute(OpcRelationships::NAMESPACE_URI) . '">';
        foreach ($relationships as $relationship) {
            $xml .= '<Relationship'
                . ' Id="' . self::escapeXmlAttribute($relationship->id) . '"'
                . ' Target="' . self::escapeXmlAttribute($relationship->target) . '"'
                . ' TargetMode="' . self::escapeXmlAttribute($relationship->targetMode) . '"'
                . ' Type="' . self::escapeXmlAttribute($relationship->type) . '"'
                . '></Relationship>';
        }

        return $xml . '</Relationships>';
    }

    private static function escapeXmlAttribute(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_XML1, 'UTF-8');
    }

    private static function isRelationshipPartName(string $name): bool
    {
        return OpcRelationships::isRelationshipPartName($name);
    }

    private static function partNameEquivalenceKey(string $partName): string
    {
        return strtolower($partName);
    }

    /**
     * @return array<string, string>
     */
    private static function packagePartNamesByEquivalenceKey(ZipPackage $package): array
    {
        $partNamesByEquivalenceKey = [];
        foreach ($package->names() as $name) {
            if (str_ends_with($name, '/')) {
                continue;
            }

            $partName = OpcPackagePath::canonicalPartName($name);
            $partNamesByEquivalenceKey[self::partNameEquivalenceKey($partName)] = $partName;
        }

        return $partNamesByEquivalenceKey;
    }

    private function packagePartNameForEquivalent(string $partName): ?string
    {
        $partName = OpcPackagePath::canonicalPartName($partName);

        return $this->packagePartNamesByEquivalenceKey[self::partNameEquivalenceKey($partName)] ?? null;
    }

    private function packageEquivalentTarget(string $target): string
    {
        $split = strcspn($target, '?#');
        $partName = substr($target, 0, $split);
        $suffix = substr($target, $split);
        if ($partName === '') {
            return $target;
        }

        $equivalentPartName = $this->packagePartNameForEquivalent($partName);

        return ($equivalentPartName ?? $partName) . $suffix;
    }

    private function relationshipSourceNameForEquivalent(string $sourcePartName): string
    {
        $sourcePartName = OpcPackagePath::canonicalPartName($sourcePartName, true);

        return $this->relationshipSourceNamesByEquivalenceKey[self::partNameEquivalenceKey($sourcePartName)] ?? $sourcePartName;
    }

    /**
     * @param list<array{valid:bool}> $rows
     */
    private static function allRowsValid(array $rows): bool
    {
        foreach ($rows as $row) {
            if ($row['valid'] !== true) {
                return false;
            }
        }

        return true;
    }

    /**
     * @return array{contentType:string, packagePartCount:int, overrideCount:int, defaultPartCount:int, relationshipPartCount:int, relationshipSourceCount:int, relationshipTargetReferenceCount:int, relationshipTargetPartCount:int, reachableTargetCount:int, missingOverrideCount:int, invalidPackagePartCount:int, parts:list<string>, overrideParts:list<string>, defaultParts:list<string>, relationshipParts:list<string>, relationshipSources:list<string>, relationshipTargetParts:list<string>, reachableTargetParts:list<string>, missingOverrideParts:list<string>, relationshipTargetReferences:list<array{source:string, id:string, targetPart:string, valid:bool, issues:list<string>}>, issues:list<string>}
     */
    private static function &contentTypeInventoryEntry(array &$inventory, string $contentType): array
    {
        if (!isset($inventory[$contentType])) {
            $inventory[$contentType] = [
                'contentType' => $contentType,
                'packagePartCount' => 0,
                'overrideCount' => 0,
                'defaultPartCount' => 0,
                'relationshipPartCount' => 0,
                'relationshipSourceCount' => 0,
                'relationshipTargetReferenceCount' => 0,
                'relationshipTargetPartCount' => 0,
                'reachableTargetCount' => 0,
                'missingOverrideCount' => 0,
                'invalidPackagePartCount' => 0,
                'parts' => [],
                'overrideParts' => [],
                'defaultParts' => [],
                'relationshipParts' => [],
                'relationshipSources' => [],
                'relationshipTargetParts' => [],
                'reachableTargetParts' => [],
                'missingOverrideParts' => [],
                'relationshipTargetReferences' => [],
                'issues' => [],
            ];
        }

        return $inventory[$contentType];
    }

    /**
     * @param list<string> $values
     */
    private static function appendUniqueString(array &$values, string $value): void
    {
        if (!in_array($value, $values, true)) {
            $values[] = $value;
        }
    }

    /**
     * @param array{kind:string, scheme:?string, allowed:bool, issues:list<string>} $externalTarget
     * @return array{requiresBaseUri:bool, basePart:?string, reason:?string}
     */
    private static function externalTargetRewritePolicy(string $sourcePartName, array $externalTarget): array
    {
        $kind = $externalTarget['kind'];
        if ($kind === 'relative-reference' || $kind === 'fragment-reference') {
            return [
                'requiresBaseUri' => true,
                'basePart' => OpcPackagePath::canonicalPartName($sourcePartName, true),
                'reason' => $kind === 'relative-reference'
                    ? 'external-target-relative-reference'
                    : 'external-target-fragment-reference',
            ];
        }

        return [
            'requiresBaseUri' => false,
            'basePart' => null,
            'reason' => null,
        ];
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
