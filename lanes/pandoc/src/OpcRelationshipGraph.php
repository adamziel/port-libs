<?php

declare(strict_types=1);

namespace PortLibs\Pandoc;

final class OpcRelationshipGraph
{
    public const OFFICE_DOCUMENT_RELATIONSHIP_TYPE = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument';
    public const DIGITAL_SIGNATURE_ORIGIN_RELATIONSHIP_TYPE = 'http://schemas.openxmlformats.org/package/2006/relationships/digital-signature/origin';
    public const DIGITAL_SIGNATURE_SIGNATURE_RELATIONSHIP_TYPE = 'http://schemas.openxmlformats.org/package/2006/relationships/digital-signature/signature';
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
            if ($contentTypes->contentTypeForPart($relationshipPartName) !== self::RELATIONSHIP_PART_CONTENT_TYPE) {
                continue;
            }

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
     * @return list<array{id:string, type:string, relationshipTypeKind:string, relationshipTypeScheme:?string, relationshipTypeValid:bool, relationshipTypeIssues:list<string>, target:string, contentType:?string, external:bool, exists:?bool, relationshipPartTarget:bool, externalTargetKind:?string, externalTargetScheme:?string, externalTargetAllowed:?bool, externalTargetRequiresBaseUri:?bool, externalTargetRewriteBasePart:?string, externalTargetRewriteReason:?string, valid:bool, issues:list<string>}>
     */
    public function preflightTargetsForSource(string $sourcePartName = '/', ?string $relationshipType = null): array
    {
        $sourcePartName = OpcPackagePath::canonicalPartName($sourcePartName, true);
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
            $exists = $this->package->has($targetPartName);
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
                $sourceExists = $relationshipSource === '/' || $this->package->has($relationshipSource);
                $relationshipSourceLoaded = isset($this->relationshipsBySource[$relationshipSource]);

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
            $exists = $this->package->has($partName);
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
                $sourceExists = $relationshipSource === '/' || $this->package->has($relationshipSource);
                $relationshipSourceLoaded = isset($this->relationshipsBySource[$relationshipSource]);

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
        $sourcePartName = OpcPackagePath::canonicalPartName($sourcePartName, true);
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

    private static function isRelationshipPartName(string $name): bool
    {
        return OpcRelationships::isRelationshipPartName($name);
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
