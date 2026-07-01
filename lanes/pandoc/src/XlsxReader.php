<?php

declare(strict_types=1);

namespace PortLibs\Pandoc;

final class XlsxReader
{
    private const OFFICE_DOCUMENT_RELATIONSHIP = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument';
    private const RELATIONSHIP_NAMESPACE = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships';
    private const MAX_XML_PART_BYTES = 8_388_608;
    private const MAX_MEDIA_METADATA_BYTES = 16_777_216;
    private const EMUS_PER_PIXEL = 9525;
    private const FEATURE_SPECS = [
        'drawing' => [
            'relationshipTypes' => ['http://schemas.openxmlformats.org/officeDocument/2006/relationships/drawing'],
            'contentTypes' => ['application/vnd.openxmlformats-officedocument.drawing+xml'],
            'pathMarkers' => ['/drawings/'],
            'rootNamespace' => 'http://schemas.openxmlformats.org/drawingml/2006/spreadsheetDrawing',
            'rootLocalName' => 'wsDr',
        ],
        'image' => [
            'relationshipTypes' => ['http://schemas.openxmlformats.org/officeDocument/2006/relationships/image'],
            'contentTypes' => ['image/png', 'image/jpeg', 'image/gif', 'image/bmp', 'image/tiff', 'image/svg+xml'],
            'pathMarkers' => ['/media/'],
            'rootNamespace' => null,
            'rootLocalName' => null,
            'xml' => false,
        ],
        'chart' => [
            'relationshipTypes' => ['http://schemas.openxmlformats.org/officeDocument/2006/relationships/chart'],
            'contentTypes' => ['application/vnd.openxmlformats-officedocument.drawingml.chart+xml'],
            'pathMarkers' => ['/charts/'],
            'rootNamespace' => 'http://schemas.openxmlformats.org/drawingml/2006/chart',
            'rootLocalName' => 'chartSpace',
        ],
        'pivotTable' => [
            'relationshipTypes' => ['http://schemas.openxmlformats.org/officeDocument/2006/relationships/pivotTable'],
            'contentTypes' => ['application/vnd.openxmlformats-officedocument.spreadsheetml.pivottable+xml'],
            'pathMarkers' => ['/pivotTables/'],
            'rootNamespace' => 'http://schemas.openxmlformats.org/spreadsheetml/2006/main',
            'rootLocalName' => 'pivotTableDefinition',
        ],
        'pivotCacheDefinition' => [
            'relationshipTypes' => ['http://schemas.openxmlformats.org/officeDocument/2006/relationships/pivotCacheDefinition'],
            'contentTypes' => ['application/vnd.openxmlformats-officedocument.spreadsheetml.pivotcachedefinition+xml'],
            'pathMarkers' => ['/pivotCache/'],
            'rootNamespace' => 'http://schemas.openxmlformats.org/spreadsheetml/2006/main',
            'rootLocalName' => 'pivotCacheDefinition',
        ],
        'pivotCacheRecords' => [
            'relationshipTypes' => ['http://schemas.openxmlformats.org/officeDocument/2006/relationships/pivotCacheRecords'],
            'contentTypes' => ['application/vnd.openxmlformats-officedocument.spreadsheetml.pivotcacherecords+xml'],
            'pathMarkers' => ['/pivotCache/'],
            'rootNamespace' => 'http://schemas.openxmlformats.org/spreadsheetml/2006/main',
            'rootLocalName' => 'pivotCacheRecords',
        ],
        'slicer' => [
            'relationshipTypes' => ['http://schemas.microsoft.com/office/2007/relationships/slicer'],
            'contentTypes' => ['application/vnd.ms-excel.slicer+xml'],
            'pathMarkers' => ['/slicers/'],
            'rootNamespace' => 'http://schemas.microsoft.com/office/spreadsheetml/2009/9/main',
            'rootLocalName' => 'slicers',
        ],
        'slicerCache' => [
            'relationshipTypes' => ['http://schemas.microsoft.com/office/2007/relationships/slicerCache'],
            'contentTypes' => ['application/vnd.ms-excel.slicercache+xml'],
            'pathMarkers' => ['/slicerCaches/'],
            'rootNamespace' => 'http://schemas.microsoft.com/office/spreadsheetml/2009/9/main',
            'rootLocalName' => 'slicerCaches',
        ],
        'comments' => [
            'relationshipTypes' => ['http://schemas.openxmlformats.org/officeDocument/2006/relationships/comments'],
            'contentTypes' => ['application/vnd.openxmlformats-officedocument.spreadsheetml.comments+xml'],
            'pathMarkers' => ['/comments'],
            'rootNamespace' => 'http://schemas.openxmlformats.org/spreadsheetml/2006/main',
            'rootLocalName' => 'comments',
        ],
    ];

    public function read(string $bytes): AstNode
    {
        $package = ZipPackage::fromString($bytes);

        return $this->readPackage($package, strlen($bytes));
    }

    private function readPackage(ZipPackage $package, int $sourceBytes): AstNode
    {
        $rootRelationships = OpcRelationships::fromPackage($package, '/');
        $workbookRelationship = $this->workbookRelationship($rootRelationships);
        $workbookPart = OpcPackagePath::stripQueryAndFragment($rootRelationships->resolveTarget($workbookRelationship));
        $workbook = $this->loadPackageXml($package, $workbookPart, 'XLSX workbook');
        $workbookInfo = $this->parseWorkbook($workbook);
        $sheets = $workbookInfo['sheets'];
        $workbookRelationships = $this->relationshipsOrEmpty($package, $workbookPart);
        $sharedStrings = $this->readSharedStrings($package, $workbookRelationships);
        $styles = $this->readStyles($package, $workbookRelationships);
        $contentTypeReview = $this->readContentTypesReview($package);
        $featureMetadata = $this->readFeatureMetadata($package, $contentTypeReview['types']);

        $blocks = [];
        $sheetReviews = [];
        $tableCount = 0;
        $formulaCellCount = 0;
        $formulaCachedValueCount = 0;
        $errorCellCount = 0;
        $tablePartCount = 0;
        $autoFilterCount = 0;
        $autoFilterDetailCount = 0;
        $commentCount = 0;
        foreach ($sheets as $sheet) {
            $relationship = $workbookRelationships->byId($sheet['relationshipId']);
            if (!$relationship instanceof OpcRelationship) {
                throw new \RuntimeException('XLSX sheet relationship not found: ' . $sheet['relationshipId']);
            }
            if ($relationship->isExternal()) {
                throw new \RuntimeException('XLSX external worksheet relationships are not supported');
            }

            $sheetPart = OpcPackagePath::stripQueryAndFragment($workbookRelationships->resolveTarget($relationship));
            $sheetDocument = $this->loadPackageXml($package, $sheetPart, 'XLSX worksheet ' . $sheet['name']);
            $sheetRelationships = $this->relationshipsOrEmpty($package, $sheetPart);
            $sheetComments = $this->parseSheetComments($package, $sheetPart, $sheetRelationships);
            $sheetDiagnostics = $this->parseSheetDiagnostics($sheetDocument);
            $sheetTableMetadata = $this->parseSheetTableMetadata($package, $sheetPart, $sheetDocument, $sheetRelationships);
            $cells = $this->parseSheetCells($sheetDocument, $sharedStrings, $styles, $workbookInfo['date1904'], $sheetRelationships, $sheetComments['commentsByCell']);
            $table = $this->cellsToTable($sheet['name'], $cells);
            $blocks[] = new AstNode('heading', [
                'level' => 2,
                'id' => 'sheet-' . $sheet['index'],
                'text' => $sheet['name'],
            ], [new AstNode('text', ['text' => $sheet['name']])]);
            if ($table instanceof AstNode) {
                $blocks[] = $table;
                $tableCount++;
            }

            $formulaCellCount += $sheetDiagnostics['formulaCellCount'];
            $formulaCachedValueCount += $sheetDiagnostics['formulaCachedValueCount'];
            $errorCellCount += $sheetDiagnostics['errorCellCount'];
            $tablePartCount += count($sheetTableMetadata['tableParts']);
            $autoFilterCount += count($sheetTableMetadata['autoFilterRanges']);
            $autoFilterDetailCount += count($sheetTableMetadata['autoFilters']);
            $commentCount += $sheetComments['commentCount'];

            $sheetReviews[] = [
                'index' => $sheet['index'],
                'name' => $sheet['name'],
                'relationshipId' => $sheet['relationshipId'],
                'partName' => ltrim($sheetPart, '/'),
                'state' => $sheet['state'],
                'hidden' => $sheet['hidden'],
                'veryHidden' => $sheet['veryHidden'],
                'cellCount' => count($cells),
                'hyperlinkCount' => count(array_filter($cells, static fn (array $cell): bool => ($cell['url'] ?? '') !== '')),
                'mergedCellCount' => count(array_filter($cells, static fn (array $cell): bool => (int) ($cell['colspan'] ?? 1) > 1 || (int) ($cell['rowspan'] ?? 1) > 1)),
                'formulaCellCount' => $sheetDiagnostics['formulaCellCount'],
                'formulaCachedValueCount' => $sheetDiagnostics['formulaCachedValueCount'],
                'formulaDiagnostics' => $sheetDiagnostics['formulaDiagnostics'],
                'errorCellCount' => $sheetDiagnostics['errorCellCount'],
                'errorDiagnostics' => $sheetDiagnostics['errorDiagnostics'],
                'commentCount' => $sheetComments['commentCount'],
                'comments' => $sheetComments['comments'],
                'commentDiagnostics' => $sheetComments['commentDiagnostics'],
                'autoFilterRanges' => $sheetTableMetadata['autoFilterRanges'],
                'autoFilters' => $sheetTableMetadata['autoFilters'],
                'tableParts' => $sheetTableMetadata['tableParts'],
                'tablePartDiagnostics' => $sheetTableMetadata['tablePartDiagnostics'],
                'tableEmitted' => $table instanceof AstNode,
            ];
        }

        return new AstNode('document', [
            'sourceFormat' => 'xlsx',
            'meta' => [],
            'xlsx' => [
                'reader' => self::class,
                'readerScope' => 'pinned-pandoc-xlsx-reader',
                'sourceBytes' => $sourceBytes,
                'entryCount' => count($package->names()),
                'workbookPart' => ltrim($workbookPart, '/'),
                'sheetCount' => count($sheets),
                'tableCount' => $tableCount,
                'hiddenSheetPolicy' => 'emit-all-sheets-record-visibility',
                'hiddenSheetCount' => count(array_filter($sheets, static fn (array $sheet): bool => $sheet['hidden'])),
                'veryHiddenSheetCount' => count(array_filter($sheets, static fn (array $sheet): bool => $sheet['veryHidden'])),
                'workbookProperties' => $workbookInfo['workbookProperties'],
                'calculationProperties' => $workbookInfo['calculationProperties'],
                'definedNameCount' => $workbookInfo['definedNameCount'],
                'externalReferenceCount' => $workbookInfo['externalReferenceCount'],
                'sharedStringCount' => count($sharedStrings),
                'styleFontCount' => count($styles['fonts']),
                'styleCellStyleFormatCount' => count($styles['cellStyleFormats']),
                'styleCellFormatCount' => count($styles['cellFormats']),
                'styleNamedCellStyleCount' => count($styles['cellStyles']),
                'styleBorderCount' => count($styles['borders']),
                'styleCustomNumberFormatCount' => count($styles['customNumberFormats']),
                'cellStyles' => $styles['cellStyles'],
                'date1904' => $workbookInfo['date1904'],
                'formulaCellCount' => $formulaCellCount,
                'formulaCachedValueCount' => $formulaCachedValueCount,
                'errorCellCount' => $errorCellCount,
                'commentCount' => $commentCount,
                'tablePartCount' => $tablePartCount,
                'autoFilterCount' => $autoFilterCount,
                'autoFilterDetailCount' => $autoFilterDetailCount,
                'sheets' => $sheetReviews,
                'featureMetadata' => $featureMetadata,
                'contentTypesAvailable' => $contentTypeReview['available'],
                'contentTypesParseError' => $contentTypeReview['parseError'],
                'payloadExposurePolicy' => 'xml-text-only',
                'formulaPolicy' => 'cached-values-only-no-formula-evaluation',
                'upstreamEvidence' => [
                    'denominator' => 1,
                    'fixtures' => [
                        'test/xlsx-reader/basic.xlsx',
                        'test/xlsx-reader/basic.native',
                    ],
                    'source' => 'Pandoc 912bfa5e src/Text/Pandoc/Readers/Xlsx.hs and src/Text/Pandoc/Readers/Xlsx/{Parse,Sheets,Cells}.hs',
                ],
            ],
        ], $blocks);
    }

    private function workbookRelationship(OpcRelationships $relationships): OpcRelationship
    {
        foreach ($relationships->all() as $relationship) {
            if (
                str_contains($relationship->type, 'officeDocument')
                && str_contains($relationship->target, 'workbook')
            ) {
                return $relationship;
            }
        }

        $relationship = $relationships->firstOfType(self::OFFICE_DOCUMENT_RELATIONSHIP);
        if ($relationship instanceof OpcRelationship) {
            return $relationship;
        }

        throw new \RuntimeException('XLSX package does not declare a workbook relationship');
    }

    private function relationshipsOrEmpty(ZipPackage $package, string $sourcePart): OpcRelationships
    {
        if (!OpcRelationships::packageHasRelationshipsForSource($package, $sourcePart)) {
            return new OpcRelationships($sourcePart);
        }

        return OpcRelationships::fromPackage($package, $sourcePart);
    }

    private function loadPackageXml(ZipPackage $package, string $partName, string $label): \DOMDocument
    {
        $xml = $package->read($partName, self::MAX_XML_PART_BYTES);

        return XmlHtmlDom::loadXmlDocument($xml, $label, false);
    }

    /**
     * @return array{available:bool, parseError:?string, types:?OpcContentTypes}
     */
    private function readContentTypesReview(ZipPackage $package): array
    {
        if (!$package->has('[Content_Types].xml')) {
            return [
                'available' => false,
                'parseError' => null,
                'types' => null,
            ];
        }

        try {
            return [
                'available' => true,
                'parseError' => null,
                'types' => OpcContentTypes::fromXml($package->read('[Content_Types].xml', self::MAX_XML_PART_BYTES)),
            ];
        } catch (\Throwable $exception) {
            return [
                'available' => true,
                'parseError' => $exception->getMessage(),
                'types' => null,
            ];
        }
    }

    /**
     * @return array{
     *     summary:array<string, mixed>,
     *     byKind:array<string, array{count:int, existingCount:int, missingCount:int, externalCount:int, relationshipCount:int, issueCount:int, issueCodes:list<string>, items:list<array<string, mixed>>}>,
     *     items:list<array<string, mixed>>
     * }
     */
    private function readFeatureMetadata(ZipPackage $package, ?OpcContentTypes $contentTypes): array
    {
        $itemsByKey = [];
        foreach ($package->names() as $name) {
            if (str_ends_with($name, '/')) {
                continue;
            }

            try {
                $partName = OpcPackagePath::canonicalPartName($name);
            } catch (\InvalidArgumentException) {
                continue;
            }

            if ($partName === '/[Content_Types].xml' || OpcRelationships::isRelationshipPartName($partName)) {
                continue;
            }

            $contentType = $this->contentTypeInfo($contentTypes, $partName);
            $kind = $this->featureKindForPart($partName, $contentType['contentTypeBase']);
            if ($kind === null) {
                continue;
            }

            $key = $this->featureItemKey($kind, $partName, null);
            $itemsByKey[$key] = $this->featureItem($kind, $partName, null, true, false, $contentType);
        }

        $relationshipInventory = $this->packageRelationshipSets($package);
        foreach ($relationshipInventory['sets'] as $relationshipSet) {
            $relationships = $relationshipSet['relationships'];
            foreach ($relationships->all() as $relationship) {
                $kind = $this->featureKindForRelationship($relationship);
                if ($kind === null) {
                    continue;
                }

                $targetPart = null;
                $externalTarget = null;
                $targetSuffix = [
                    'targetQuery' => null,
                    'targetFragment' => null,
                    'targetSuffix' => '',
                ];

                if ($relationship->isExternal()) {
                    $externalTarget = $relationship->target;
                    $targetSuffix = $this->targetSuffix($relationship->target);
                    $contentType = $this->emptyContentTypeInfo('external');
                    $key = $this->featureItemKey($kind, null, $relationshipSet['sourcePart'] . ':' . $relationship->id);
                    if (!isset($itemsByKey[$key])) {
                        $itemsByKey[$key] = $this->featureItem($kind, null, $externalTarget, false, true, $contentType);
                    }
                } else {
                    try {
                        $resolvedTarget = $relationships->resolveTarget($relationship);
                        $targetSuffix = $this->targetSuffix($resolvedTarget);
                        $targetPart = OpcPackagePath::stripQueryAndFragment($resolvedTarget);
                    } catch (\Throwable $exception) {
                        $externalTarget = $relationship->target;
                        $contentType = $this->emptyContentTypeInfo('unresolved');
                        $key = $this->featureItemKey($kind, null, $relationshipSet['sourcePart'] . ':' . $relationship->id);
                        if (!isset($itemsByKey[$key])) {
                            $itemsByKey[$key] = $this->featureItem($kind, null, $externalTarget, false, false, $contentType);
                            $itemsByKey[$key]['issues'][] = $this->featureIssueStem($kind) . '-target-resolution-error';
                            $itemsByKey[$key]['targetResolutionError'] = $exception->getMessage();
                        }
                    }

                    if ($targetPart !== null) {
                        $contentType = $this->contentTypeInfo($contentTypes, $targetPart);
                        $key = $this->featureItemKey($kind, $targetPart, null);
                        if (!isset($itemsByKey[$key])) {
                            $itemsByKey[$key] = $this->featureItem(
                                $kind,
                                $targetPart,
                                null,
                                $package->has($targetPart),
                                false,
                                $contentType
                            );
                        }
                    }
                }

                $itemsByKey[$key]['relationshipCount']++;
                $itemsByKey[$key]['relationshipRefs'][] = [
                    'sourcePart' => ltrim($relationshipSet['sourcePart'], '/'),
                    'relationshipPart' => ltrim($relationshipSet['relationshipPart'], '/'),
                    'id' => $relationship->id,
                    'type' => $relationship->type,
                    'target' => $relationship->target,
                    'targetMode' => $relationship->targetMode,
                    'targetPart' => $targetPart === null ? null : ltrim($targetPart, '/'),
                    'targetQuery' => $targetSuffix['targetQuery'],
                    'targetFragment' => $targetSuffix['targetFragment'],
                    'targetSuffix' => $targetSuffix['targetSuffix'],
                ];
            }
        }

        $items = array_values($itemsByKey);
        usort($items, static function (array $left, array $right): int {
            return [
                $left['kind'],
                $left['partName'] ?? '',
                $left['externalTarget'] ?? '',
            ] <=> [
                $right['kind'],
                $right['partName'] ?? '',
                $right['externalTarget'] ?? '',
            ];
        });

        foreach ($items as $index => $item) {
            $items[$index] = $this->finalizeFeatureItem($package, $item);
        }

        return $this->featureMetadataSummary($items, $relationshipInventory['parseErrors']);
    }

    /**
     * @return array{sets:list<array{sourcePart:string, relationshipPart:string, relationships:OpcRelationships}>, parseErrors:list<array{relationshipPart:string, sourcePart:?string, error:string}>}
     */
    private function packageRelationshipSets(ZipPackage $package): array
    {
        $sets = [];
        $parseErrors = [];
        foreach ($package->names() as $name) {
            if (str_ends_with($name, '/')) {
                continue;
            }

            try {
                $relationshipPart = OpcPackagePath::canonicalPartName($name);
                if (!OpcRelationships::isRelationshipPartName($relationshipPart)) {
                    continue;
                }

                $sourcePart = OpcRelationships::sourcePartNameForRelationshipPart($relationshipPart);
            } catch (\Throwable $exception) {
                $parseErrors[] = [
                    'relationshipPart' => $name,
                    'sourcePart' => null,
                    'error' => $exception->getMessage(),
                ];
                continue;
            }

            try {
                $sets[] = [
                    'sourcePart' => $sourcePart,
                    'relationshipPart' => $relationshipPart,
                    'relationships' => OpcRelationships::fromPackage($package, $sourcePart),
                ];
            } catch (\Throwable $exception) {
                $parseErrors[] = [
                    'relationshipPart' => ltrim($relationshipPart, '/'),
                    'sourcePart' => ltrim($sourcePart, '/'),
                    'error' => $exception->getMessage(),
                ];
            }
        }

        return [
            'sets' => $sets,
            'parseErrors' => $parseErrors,
        ];
    }

    /**
     * @return array{contentType:?string, contentTypeBase:?string, contentTypeSource:string, contentTypeDefaultExtension:?string, contentTypeOverridePartName:?string}
     */
    private function contentTypeInfo(?OpcContentTypes $contentTypes, string $partName): array
    {
        if (!$contentTypes instanceof OpcContentTypes) {
            return $this->emptyContentTypeInfo('unavailable');
        }

        $resolution = $contentTypes->contentTypeResolutionForPart($partName);
        $contentType = $resolution['contentType'];

        return [
            'contentType' => $contentType,
            'contentTypeBase' => is_string($contentType) ? $this->contentTypeBase($contentType) : null,
            'contentTypeSource' => $resolution['contentTypeSource'],
            'contentTypeDefaultExtension' => $resolution['defaultExtension'],
            'contentTypeOverridePartName' => $resolution['overridePartName'] === null
                ? null
                : ltrim((string) $resolution['overridePartName'], '/'),
        ];
    }

    /**
     * @return array{contentType:?string, contentTypeBase:?string, contentTypeSource:string, contentTypeDefaultExtension:?string, contentTypeOverridePartName:?string}
     */
    private function emptyContentTypeInfo(string $source): array
    {
        return [
            'contentType' => null,
            'contentTypeBase' => null,
            'contentTypeSource' => $source,
            'contentTypeDefaultExtension' => null,
            'contentTypeOverridePartName' => null,
        ];
    }

    private function contentTypeBase(string $contentType): string
    {
        return strtolower(trim(explode(';', $contentType, 2)[0]));
    }

    private function featureKindForRelationship(OpcRelationship $relationship): ?string
    {
        foreach (self::FEATURE_SPECS as $kind => $spec) {
            if (in_array($relationship->type, $spec['relationshipTypes'], true)) {
                return $kind;
            }
        }

        return null;
    }

    private function featureKindForPart(string $partName, ?string $contentTypeBase): ?string
    {
        foreach (self::FEATURE_SPECS as $kind => $spec) {
            if ($contentTypeBase !== null && in_array($contentTypeBase, $spec['contentTypes'], true)) {
                return $kind;
            }
        }

        $path = strtolower($partName);
        if (str_contains($path, '/pivotcache/pivotcachedefinition')) {
            return 'pivotCacheDefinition';
        }
        if (str_contains($path, '/pivotcache/pivotcacherecords')) {
            return 'pivotCacheRecords';
        }
        if (str_contains($path, '/slicercaches/')) {
            return 'slicerCache';
        }

        foreach (self::FEATURE_SPECS as $kind => $spec) {
            foreach ($spec['pathMarkers'] as $marker) {
                if (str_contains($path, strtolower($marker))) {
                    return $kind;
                }
            }
        }

        return null;
    }

    private function featureItemKey(string $kind, ?string $partName, ?string $externalKey): string
    {
        if ($partName !== null) {
            return $kind . ':part:' . ltrim($partName, '/');
        }

        return $kind . ':external:' . (string) $externalKey;
    }

    /**
     * @param array{contentType:?string, contentTypeBase:?string, contentTypeSource:string, contentTypeDefaultExtension:?string, contentTypeOverridePartName:?string} $contentType
     * @return array<string, mixed>
     */
    private function featureItem(
        string $kind,
        ?string $partName,
        ?string $externalTarget,
        bool $exists,
        bool $external,
        array $contentType
    ): array {
        $spec = self::FEATURE_SPECS[$kind];
        $contentTypeMatchesExpected = $contentType['contentTypeBase'] === null
            ? null
            : in_array($contentType['contentTypeBase'], $spec['contentTypes'], true);
        $issueStem = $this->featureIssueStem($kind);
        $issues = [];
        if ($external) {
            $issues[] = 'external-' . $issueStem . '-part';
        } elseif (!$exists) {
            $issues[] = 'missing-' . $issueStem . '-part';
        }
        if (!$external && $contentType['contentTypeSource'] !== 'unavailable') {
            if ($contentType['contentTypeBase'] === null) {
                $issues[] = 'missing-' . $issueStem . '-content-type';
            } elseif ($contentTypeMatchesExpected !== true) {
                $issues[] = 'unexpected-' . $issueStem . '-content-type';
            }
        }

        return [
            'kind' => $kind,
            'partName' => $partName === null ? null : ltrim($partName, '/'),
            'externalTarget' => $externalTarget,
            'exists' => $exists,
            'external' => $external,
            'contentType' => $contentType['contentType'],
            'contentTypeBase' => $contentType['contentTypeBase'],
            'contentTypeSource' => $contentType['contentTypeSource'],
            'contentTypeDefaultExtension' => $contentType['contentTypeDefaultExtension'],
            'contentTypeOverridePartName' => $contentType['contentTypeOverridePartName'],
            'contentTypeMatchesExpected' => $contentTypeMatchesExpected,
            'expectedContentTypes' => $spec['contentTypes'],
            'expectedRootNamespace' => $spec['rootNamespace'],
            'expectedRootLocalName' => $spec['rootLocalName'],
            'rootNamespace' => null,
            'rootLocalName' => null,
            'validRoot' => null,
            'xmlParseError' => null,
            'relationshipCount' => 0,
            'relationshipRefs' => [],
            'issues' => $issues,
            'reviewPolicy' => $issueStem . '-metadata-only',
            'byteExposurePolicy' => $issueStem . '-part-bytes-blocked',
        ];
    }

    /**
     * @param array<string, mixed> $item
     * @return array<string, mixed>
     */
    private function finalizeFeatureItem(ZipPackage $package, array $item): array
    {
        if (($item['exists'] ?? false) !== true || ($item['external'] ?? false) === true || !is_string($item['partName'] ?? null)) {
            $item['issues'] = array_values(array_unique($item['issues']));
            return $item;
        }

        $partName = (string) $item['partName'];
        $kind = (string) $item['kind'];
        $issueStem = $this->featureIssueStem($kind);
        if ((self::FEATURE_SPECS[$kind]['xml'] ?? true) === false) {
            return $this->finalizeBinaryFeatureItem($package, $item, $partName, $issueStem);
        }

        try {
            $document = $this->loadPackageXml($package, $partName, 'XLSX ' . $issueStem . ' metadata ' . $partName);
            $root = $document->documentElement;
            if ($root instanceof \DOMElement) {
                $item['rootNamespace'] = $root->namespaceURI;
                $item['rootLocalName'] = $root->localName;
                $item['validRoot'] = $root->namespaceURI === $item['expectedRootNamespace']
                    && $root->localName === $item['expectedRootLocalName'];
                if ($item['validRoot'] !== true) {
                    $item['issues'][] = 'unexpected-' . $issueStem . '-root';
                }
            } else {
                $item['validRoot'] = false;
                $item['issues'][] = 'missing-' . $issueStem . '-root';
            }
        } catch (\Throwable $exception) {
            $item['validRoot'] = false;
            $item['xmlParseError'] = $exception->getMessage();
            $item['issues'][] = 'invalid-' . $issueStem . '-xml';
        }

        $item['issues'] = array_values(array_unique($item['issues']));

        return $item;
    }

    /**
     * @param array<string, mixed> $item
     * @return array<string, mixed>
     */
    private function finalizeBinaryFeatureItem(ZipPackage $package, array $item, string $partName, string $issueStem): array
    {
        try {
            $entry = $package->entry($partName);
            $item['byteSize'] = $entry->uncompressedSize;
            $bytes = $package->read($partName, self::MAX_MEDIA_METADATA_BYTES);
            $item['sha256'] = hash('sha256', $bytes);
            $dimensions = $this->imageDimensions($bytes);
            $item['imageWidthPixels'] = $dimensions['width'] ?? null;
            $item['imageHeightPixels'] = $dimensions['height'] ?? null;
            if ($dimensions === null) {
                $item['issues'][] = 'unknown-' . $issueStem . '-dimensions';
            }
        } catch (\Throwable $exception) {
            $item['byteSize'] ??= null;
            $item['sha256'] = null;
            $item['imageWidthPixels'] = null;
            $item['imageHeightPixels'] = null;
            $item['issues'][] = $issueStem . '-metadata-read-error';
            $item['metadataReadError'] = $exception->getMessage();
        }

        if ((string) ($item['kind'] ?? '') === 'image') {
            $item['drawingAnchors'] = $this->imageDrawingAnchors($package, $item);
            $item['drawingAnchorCount'] = count($item['drawingAnchors']);
        }

        $item['issues'] = array_values(array_unique($item['issues']));

        return $item;
    }

    /**
     * @return array{width:int, height:int}|null
     */
    private function imageDimensions(string $bytes): ?array
    {
        $length = strlen($bytes);
        if ($length >= 24 && substr($bytes, 0, 8) === "\x89PNG\r\n\x1a\n") {
            $size = unpack('Nwidth/Nheight', substr($bytes, 16, 8));

            return is_array($size) ? ['width' => (int) $size['width'], 'height' => (int) $size['height']] : null;
        }

        if ($length >= 10 && (substr($bytes, 0, 6) === 'GIF87a' || substr($bytes, 0, 6) === 'GIF89a')) {
            $size = unpack('vwidth/vheight', substr($bytes, 6, 4));

            return is_array($size) ? ['width' => (int) $size['width'], 'height' => (int) $size['height']] : null;
        }

        if ($length >= 4 && substr($bytes, 0, 2) === "\xff\xd8") {
            return $this->jpegDimensions($bytes);
        }

        return null;
    }

    /**
     * @return array{width:int, height:int}|null
     */
    private function jpegDimensions(string $bytes): ?array
    {
        $length = strlen($bytes);
        $offset = 2;
        while ($offset + 9 < $length) {
            if ($bytes[$offset] !== "\xff") {
                $offset++;
                continue;
            }
            while ($offset < $length && $bytes[$offset] === "\xff") {
                $offset++;
            }
            if ($offset >= $length) {
                return null;
            }

            $marker = ord($bytes[$offset]);
            $offset++;
            if ($marker === 0xd9 || $marker === 0xda) {
                return null;
            }
            if ($offset + 2 > $length) {
                return null;
            }

            $segmentLength = unpack('nlength', substr($bytes, $offset, 2));
            if (!is_array($segmentLength)) {
                return null;
            }
            $segmentLength = (int) $segmentLength['length'];
            if ($segmentLength < 2 || $offset + $segmentLength > $length) {
                return null;
            }

            if (in_array($marker, [0xc0, 0xc1, 0xc2, 0xc3, 0xc5, 0xc6, 0xc7, 0xc9, 0xca, 0xcb, 0xcd, 0xce, 0xcf], true)) {
                $size = unpack('nheight/nwidth', substr($bytes, $offset + 3, 4));

                return is_array($size) ? ['width' => (int) $size['width'], 'height' => (int) $size['height']] : null;
            }

            $offset += $segmentLength;
        }

        return null;
    }

    /**
     * @param array<string, mixed> $item
     * @return list<array<string, mixed>>
     */
    private function imageDrawingAnchors(ZipPackage $package, array $item): array
    {
        $anchors = [];
        foreach (($item['relationshipRefs'] ?? []) as $relationshipRef) {
            if (!is_array($relationshipRef)) {
                continue;
            }
            $sourcePart = (string) ($relationshipRef['sourcePart'] ?? '');
            $relationshipId = (string) ($relationshipRef['id'] ?? '');
            if ($sourcePart === '' || $relationshipId === '' || !str_contains(strtolower($sourcePart), '/drawings/')) {
                continue;
            }
            if (!$package->has($sourcePart)) {
                continue;
            }

            try {
                $document = $this->loadPackageXml($package, $sourcePart, 'XLSX drawing anchors ' . $sourcePart);
            } catch (\Throwable) {
                continue;
            }

            $root = $document->documentElement;
            if (!$root instanceof \DOMElement) {
                continue;
            }

            foreach ($this->descendantElements($root, 'blip') as $blip) {
                if ($this->relationshipReferenceId($blip) !== $relationshipId) {
                    continue;
                }
                $anchor = $this->ancestorDrawingAnchor($blip);
                if (!$anchor instanceof \DOMElement) {
                    continue;
                }

                $anchors[] = $this->drawingAnchorMetadata($anchor, $sourcePart, $relationshipId, $relationshipRef);
            }
        }

        return $anchors;
    }

    private function relationshipReferenceId(\DOMElement $element): string
    {
        foreach (['embed', 'link', 'id'] as $localName) {
            $id = $element->getAttributeNS(self::RELATIONSHIP_NAMESPACE, $localName);
            if ($id !== '') {
                return $id;
            }
            if ($element->hasAttribute('r:' . $localName)) {
                return $element->getAttribute('r:' . $localName);
            }
        }

        foreach ($element->attributes ?? [] as $attribute) {
            if ($attribute instanceof \DOMAttr && in_array($attribute->localName, ['embed', 'link', 'id'], true)) {
                return $attribute->value;
            }
        }

        return '';
    }

    private function ancestorDrawingAnchor(\DOMElement $element): ?\DOMElement
    {
        $node = $element->parentNode;
        while ($node instanceof \DOMNode) {
            if ($node instanceof \DOMElement && in_array($node->localName, ['twoCellAnchor', 'oneCellAnchor', 'absoluteAnchor'], true)) {
                return $node;
            }
            $node = $node->parentNode;
        }

        return null;
    }

    /**
     * @return array<string, mixed>
     */
    private function drawingAnchorMetadata(\DOMElement $anchor, string $sourcePart, string $relationshipId, array $relationshipRef): array
    {
        $extent = $this->firstChildElement($anchor, 'ext');
        $position = $this->firstChildElement($anchor, 'pos');
        $properties = $this->firstDescendantElement($anchor, 'cNvPr');
        $from = $this->drawingMarker($this->firstChildElement($anchor, 'from'));
        $to = $this->drawingMarker($this->firstChildElement($anchor, 'to'));
        $extentEmu = $extent instanceof \DOMElement ? [
            'cx' => $this->integerAttribute($extent, 'cx'),
            'cy' => $this->integerAttribute($extent, 'cy'),
        ] : null;
        $positionEmu = $position instanceof \DOMElement ? [
            'x' => $this->integerAttribute($position, 'x'),
            'y' => $this->integerAttribute($position, 'y'),
        ] : null;

        return [
            'sourcePart' => ltrim($sourcePart, '/'),
            'relationshipId' => $relationshipId,
            'targetPart' => is_string($relationshipRef['targetPart'] ?? null) ? $relationshipRef['targetPart'] : null,
            'targetQuery' => is_string($relationshipRef['targetQuery'] ?? null) ? $relationshipRef['targetQuery'] : null,
            'targetFragment' => is_string($relationshipRef['targetFragment'] ?? null) ? $relationshipRef['targetFragment'] : null,
            'targetSuffix' => is_string($relationshipRef['targetSuffix'] ?? null) ? $relationshipRef['targetSuffix'] : '',
            'anchorType' => $anchor->localName,
            'from' => $from,
            'fromCell' => $this->drawingMarkerCellReference($from),
            'to' => $to,
            'toCell' => $this->drawingMarkerCellReference($to),
            'positionEmu' => $positionEmu,
            'positionPixels' => $this->drawingPointPixels($positionEmu),
            'extentEmu' => $extentEmu,
            'extentPixels' => $this->drawingExtentPixels($extentEmu),
            'name' => $properties instanceof \DOMElement && trim($properties->getAttribute('name')) !== '' ? trim($properties->getAttribute('name')) : null,
            'description' => $properties instanceof \DOMElement && trim($properties->getAttribute('descr')) !== '' ? trim($properties->getAttribute('descr')) : null,
        ];
    }

    /**
     * @return array{column:int|null, row:int|null, columnOffsetEmu:int|null, rowOffsetEmu:int|null, columnOffsetPixels:float|null, rowOffsetPixels:float|null}|null
     */
    private function drawingMarker(?\DOMElement $marker): ?array
    {
        if (!$marker instanceof \DOMElement) {
            return null;
        }

        $markerData = [
            'column' => $this->firstChildIntegerText($marker, 'col'),
            'row' => $this->firstChildIntegerText($marker, 'row'),
            'columnOffsetEmu' => $this->firstChildIntegerText($marker, 'colOff'),
            'rowOffsetEmu' => $this->firstChildIntegerText($marker, 'rowOff'),
        ];

        $markerData['columnOffsetPixels'] = $this->emuToPixels($markerData['columnOffsetEmu']);
        $markerData['rowOffsetPixels'] = $this->emuToPixels($markerData['rowOffsetEmu']);

        return $markerData;
    }

    /**
     * @param array<string, mixed>|null $marker
     */
    private function drawingMarkerCellReference(?array $marker): ?string
    {
        $column = $marker['column'] ?? null;
        $row = $marker['row'] ?? null;
        if (!is_int($column) || !is_int($row) || $column < 0 || $row < 0) {
            return null;
        }

        return $this->cellReferenceFromCoordinates($row + 1, $column + 1);
    }

    /**
     * @param array{x:int|null, y:int|null}|null $point
     * @return array{x:float|null, y:float|null}|null
     */
    private function drawingPointPixels(?array $point): ?array
    {
        if ($point === null) {
            return null;
        }

        return [
            'x' => $this->emuToPixels($point['x']),
            'y' => $this->emuToPixels($point['y']),
        ];
    }

    /**
     * @param array{cx:int|null, cy:int|null}|null $extent
     * @return array{width:float|null, height:float|null}|null
     */
    private function drawingExtentPixels(?array $extent): ?array
    {
        if ($extent === null) {
            return null;
        }

        return [
            'width' => $this->emuToPixels($extent['cx']),
            'height' => $this->emuToPixels($extent['cy']),
        ];
    }

    private function emuToPixels(?int $emu): ?float
    {
        return $emu === null ? null : round($emu / self::EMUS_PER_PIXEL, 4);
    }

    /**
     * @param list<array<string, mixed>> $items
     * @param list<array{relationshipPart:string, sourcePart:?string, error:string}> $relationshipParseErrors
     * @return array{
     *     summary:array<string, mixed>,
     *     byKind:array<string, array{count:int, existingCount:int, missingCount:int, externalCount:int, relationshipCount:int, issueCount:int, issueCodes:list<string>, items:list<array<string, mixed>>}>,
     *     items:list<array<string, mixed>>
     * }
     */
    private function featureMetadataSummary(array $items, array $relationshipParseErrors): array
    {
        $byKind = [];
        $summary = [
            'readerPolicy' => 'xlsx-feature-metadata-only',
            'byteExposurePolicy' => 'xlsx-feature-part-bytes-blocked',
            'nonGoals' => [
                'chart-rendering',
                'pivot-computation',
                'formula-evaluation',
                'scripting',
            ],
            'relationshipParseErrorCount' => count($relationshipParseErrors),
            'relationshipParseErrors' => $relationshipParseErrors,
            'count' => count($items),
            'existingCount' => count(array_filter($items, static fn (array $item): bool => ($item['exists'] ?? false) === true)),
            'missingCount' => count(array_filter($items, static fn (array $item): bool => in_array('missing-' . self::featureIssueStemStatic((string) $item['kind']) . '-part', $item['issues'] ?? [], true))),
            'externalCount' => count(array_filter($items, static fn (array $item): bool => ($item['external'] ?? false) === true)),
            'relationshipCount' => array_sum(array_map(static fn (array $item): int => (int) ($item['relationshipCount'] ?? 0), $items)),
            'issueCount' => count(array_filter($items, static fn (array $item): bool => ($item['issues'] ?? []) !== [])),
            'issueCodes' => $this->featureIssueCodes($items),
            'countsByKind' => [],
            'existingCountsByKind' => [],
            'missingCountsByKind' => [],
            'externalCountsByKind' => [],
            'relationshipCountsByKind' => [],
            'issueCountsByKind' => [],
            'issueCodesByKind' => [],
        ];

        foreach (array_keys(self::FEATURE_SPECS) as $kind) {
            $kindItems = array_values(array_filter($items, static fn (array $item): bool => ($item['kind'] ?? '') === $kind));
            $issueCodes = $this->featureIssueCodes($kindItems);
            $byKind[$kind] = [
                'count' => count($kindItems),
                'existingCount' => count(array_filter($kindItems, static fn (array $item): bool => ($item['exists'] ?? false) === true)),
                'missingCount' => count(array_filter($kindItems, static fn (array $item): bool => in_array('missing-' . self::featureIssueStemStatic($kind) . '-part', $item['issues'] ?? [], true))),
                'externalCount' => count(array_filter($kindItems, static fn (array $item): bool => ($item['external'] ?? false) === true)),
                'relationshipCount' => array_sum(array_map(static fn (array $item): int => (int) ($item['relationshipCount'] ?? 0), $kindItems)),
                'issueCount' => count(array_filter($kindItems, static fn (array $item): bool => ($item['issues'] ?? []) !== [])),
                'issueCodes' => $issueCodes,
                'items' => $kindItems,
            ];
            $summary['countsByKind'][$kind] = $byKind[$kind]['count'];
            $summary['existingCountsByKind'][$kind] = $byKind[$kind]['existingCount'];
            $summary['missingCountsByKind'][$kind] = $byKind[$kind]['missingCount'];
            $summary['externalCountsByKind'][$kind] = $byKind[$kind]['externalCount'];
            $summary['relationshipCountsByKind'][$kind] = $byKind[$kind]['relationshipCount'];
            $summary['issueCountsByKind'][$kind] = $byKind[$kind]['issueCount'];
            $summary['issueCodesByKind'][$kind] = $issueCodes;
        }

        return [
            'summary' => $summary,
            'byKind' => $byKind,
            'items' => $items,
        ];
    }

    /**
     * @param list<array<string, mixed>> $items
     * @return list<string>
     */
    private function featureIssueCodes(array $items): array
    {
        $issueCodes = [];
        foreach ($items as $item) {
            foreach (($item['issues'] ?? []) as $issue) {
                if (is_string($issue) && !in_array($issue, $issueCodes, true)) {
                    $issueCodes[] = $issue;
                }
            }
        }
        sort($issueCodes);

        return $issueCodes;
    }

    private function featureIssueStem(string $kind): string
    {
        return self::featureIssueStemStatic($kind);
    }

    private static function featureIssueStemStatic(string $kind): string
    {
        $stem = preg_replace('/(?<!^)[A-Z]/', '-$0', $kind);

        return strtolower(is_string($stem) ? $stem : $kind);
    }

    /**
     * @return array{targetQuery:?string, targetFragment:?string, targetSuffix:string}
     */
    private function targetSuffix(string $target): array
    {
        $offset = strcspn($target, '?#');
        $suffix = substr($target, $offset);
        $query = null;
        $fragment = null;
        if ($suffix !== '') {
            if ($suffix[0] === '?') {
                $fragmentOffset = strpos($suffix, '#');
                if ($fragmentOffset === false) {
                    $query = substr($suffix, 1);
                } else {
                    $query = substr($suffix, 1, $fragmentOffset - 1);
                    $fragment = substr($suffix, $fragmentOffset + 1);
                }
            } elseif ($suffix[0] === '#') {
                $fragment = substr($suffix, 1);
            }
        }

        return [
            'targetQuery' => $query,
            'targetFragment' => $fragment,
            'targetSuffix' => $suffix,
        ];
    }

    /**
     * @return array{
     *     date1904:bool,
     *     workbookProperties:array<string, bool|string|null>,
     *     calculationProperties:array<string, bool|int|string|null>,
     *     definedNameCount:int,
     *     externalReferenceCount:int,
     *     sheets:list<array{index:int, name:string, relationshipId:string, state:string, hidden:bool, veryHidden:bool}>
     * }
     */
    private function parseWorkbook(\DOMDocument $document): array
    {
        $root = XmlHtmlDom::rootElement($document, 'workbook');
        if (!$root instanceof \DOMElement) {
            throw new \RuntimeException('XLSX workbook XML must have a workbook root');
        }

        $sheetsElement = $this->firstChildElement($root, 'sheets');
        if (!$sheetsElement instanceof \DOMElement) {
            throw new \RuntimeException('XLSX workbook XML is missing <sheets>');
        }

        $sheets = [];
        $index = 1;
        foreach ($this->childElements($sheetsElement, 'sheet') as $sheetElement) {
            $name = trim($sheetElement->getAttribute('name'));
            if ($name === '') {
                $name = 'Sheet' . $index;
            }
            $relationshipId = $this->relationshipId($sheetElement);
            if ($relationshipId === '') {
                throw new \RuntimeException('XLSX workbook sheet is missing r:id');
            }

            $state = match (strtolower(trim($sheetElement->getAttribute('state')))) {
                'hidden' => 'hidden',
                'veryhidden' => 'veryHidden',
                default => 'visible',
            };
            $sheets[] = [
                'index' => $index,
                'name' => $name,
                'relationshipId' => $relationshipId,
                'state' => $state,
                'hidden' => $state !== 'visible',
                'veryHidden' => $state === 'veryHidden',
            ];
            $index++;
        }

        $workbookProperties = $this->firstChildElement($root, 'workbookPr');
        $date1904 = $workbookProperties instanceof \DOMElement
            ? ($this->booleanAttribute($workbookProperties, 'date1904') ?? false)
            : false;
        $calculationProperties = $this->firstChildElement($root, 'calcPr');
        $definedNames = $this->firstChildElement($root, 'definedNames');
        $externalReferences = $this->firstChildElement($root, 'externalReferences');

        return [
            'date1904' => $date1904,
            'workbookProperties' => [
                'date1904' => $date1904,
                'filterPrivacy' => $workbookProperties instanceof \DOMElement ? $this->booleanAttribute($workbookProperties, 'filterPrivacy') : null,
                'backupFile' => $workbookProperties instanceof \DOMElement ? $this->booleanAttribute($workbookProperties, 'backupFile') : null,
                'showObjects' => $workbookProperties instanceof \DOMElement && $workbookProperties->hasAttribute('showObjects')
                    ? $workbookProperties->getAttribute('showObjects')
                    : null,
                'updateLinks' => $workbookProperties instanceof \DOMElement && $workbookProperties->hasAttribute('updateLinks')
                    ? $workbookProperties->getAttribute('updateLinks')
                    : null,
                'codeNamePresent' => $workbookProperties instanceof \DOMElement && trim($workbookProperties->getAttribute('codeName')) !== '',
            ],
            'calculationProperties' => [
                'present' => $calculationProperties instanceof \DOMElement,
                'calcId' => $calculationProperties instanceof \DOMElement ? $this->integerAttribute($calculationProperties, 'calcId') : null,
                'calcMode' => $calculationProperties instanceof \DOMElement && $calculationProperties->hasAttribute('calcMode')
                    ? $calculationProperties->getAttribute('calcMode')
                    : null,
                'fullCalcOnLoad' => $calculationProperties instanceof \DOMElement ? $this->booleanAttribute($calculationProperties, 'fullCalcOnLoad') : null,
                'forceFullCalc' => $calculationProperties instanceof \DOMElement ? $this->booleanAttribute($calculationProperties, 'forceFullCalc') : null,
                'iterate' => $calculationProperties instanceof \DOMElement ? $this->booleanAttribute($calculationProperties, 'iterate') : null,
            ],
            'definedNameCount' => $definedNames instanceof \DOMElement ? count($this->childElements($definedNames, 'definedName')) : 0,
            'externalReferenceCount' => $externalReferences instanceof \DOMElement ? count($this->childElements($externalReferences, 'externalReference')) : 0,
            'sheets' => $sheets,
        ];
    }

    /**
     * @return list<string>
     */
    private function readSharedStrings(ZipPackage $package, OpcRelationships $workbookRelationships): array
    {
        $relationship = $this->firstRelationshipWithTarget($workbookRelationships, 'sharedStrings');
        if (!$relationship instanceof OpcRelationship || $relationship->isExternal()) {
            return [];
        }

        $part = OpcPackagePath::stripQueryAndFragment($workbookRelationships->resolveTarget($relationship));
        $document = $this->loadPackageXml($package, $part, 'XLSX shared strings');
        $root = XmlHtmlDom::rootElement($document, 'sst');
        if (!$root instanceof \DOMElement) {
            return [];
        }

        $strings = [];
        foreach ($this->childElements($root, 'si') as $stringElement) {
            $directText = $this->firstChildElement($stringElement, 't');
            if ($directText instanceof \DOMElement) {
                $strings[] = $directText->textContent;
                continue;
            }

            $strings[] = $this->allDescendantText($stringElement);
        }

        return $strings;
    }

    /**
     * @return array{
     *     fonts:list<array<string, mixed>>,
     *     fills:list<array<string, mixed>>,
     *     borders:list<array<string, mixed>>,
     *     cellFormats:list<array<string, mixed>>,
     *     customNumberFormats:array<int, string>
     * }
     */
    private function readStyles(ZipPackage $package, OpcRelationships $workbookRelationships): array
    {
        $empty = [
            'fonts' => [],
            'fills' => [],
            'borders' => [],
            'cellStyleFormats' => [],
            'cellFormats' => [],
            'cellStyles' => [],
            'customNumberFormats' => [],
        ];
        $relationship = $this->firstRelationshipWithTarget($workbookRelationships, 'styles');
        if (!$relationship instanceof OpcRelationship || $relationship->isExternal()) {
            return $empty;
        }

        $part = OpcPackagePath::stripQueryAndFragment($workbookRelationships->resolveTarget($relationship));
        $document = $this->loadPackageXml($package, $part, 'XLSX styles');
        $root = XmlHtmlDom::rootElement($document, 'styleSheet');
        if (!$root instanceof \DOMElement) {
            return $empty;
        }

        $customNumberFormats = [];
        $numFmts = $this->firstChildElement($root, 'numFmts');
        if ($numFmts instanceof \DOMElement) {
            foreach ($this->childElements($numFmts, 'numFmt') as $formatElement) {
                $id = trim($formatElement->getAttribute('numFmtId'));
                if (preg_match('/^\d+$/', $id) === 1) {
                    $customNumberFormats[(int) $id] = $formatElement->getAttribute('formatCode');
                }
            }
        }

        $fontsList = [];
        $fonts = $this->firstChildElement($root, 'fonts');
        if ($fonts instanceof \DOMElement) {
            foreach ($this->childElements($fonts, 'font') as $fontElement) {
                $fontsList[] = $this->parseStyleFont($fontElement);
            }
        }

        $fillsList = [];
        $fills = $this->firstChildElement($root, 'fills');
        if ($fills instanceof \DOMElement) {
            foreach ($this->childElements($fills, 'fill') as $fillElement) {
                $fillsList[] = $this->parseStyleFill($fillElement);
            }
        }

        $bordersList = [];
        $borders = $this->firstChildElement($root, 'borders');
        if ($borders instanceof \DOMElement) {
            foreach ($this->childElements($borders, 'border') as $borderElement) {
                $bordersList[] = $this->parseStyleBorder($borderElement);
            }
        }

        $cellStyleFormats = [];
        $cellStyleXfs = $this->firstChildElement($root, 'cellStyleXfs');
        if ($cellStyleXfs instanceof \DOMElement) {
            foreach ($this->childElements($cellStyleXfs, 'xf') as $xfElement) {
                $cellStyleFormats[] = $this->parseStyleXf($xfElement, $fontsList, $fillsList, $bordersList, $customNumberFormats, null);
            }
        }

        $cellStyles = $this->parseCellStyles($root);
        $cellStylesByXfId = [];
        foreach ($cellStyles as $cellStyle) {
            $xfId = $cellStyle['xfId'] ?? null;
            if (is_int($xfId) && !array_key_exists($xfId, $cellStylesByXfId)) {
                $cellStylesByXfId[$xfId] = $cellStyle;
            }
        }

        $cellFormats = [];
        $cellXfs = $this->firstChildElement($root, 'cellXfs');
        if ($cellXfs instanceof \DOMElement) {
            foreach ($this->childElements($cellXfs, 'xf') as $xfElement) {
                $xfId = $this->integerAttribute($xfElement, 'xfId');
                $baseStyle = $xfId !== null && array_key_exists($xfId, $cellStyleFormats)
                    ? $cellStyleFormats[$xfId]
                    : null;
                $style = $this->parseStyleXf($xfElement, $fontsList, $fillsList, $bordersList, $customNumberFormats, $baseStyle);
                if ($xfId !== null && array_key_exists($xfId, $cellStylesByXfId)) {
                    $namedStyle = $cellStylesByXfId[$xfId];
                    $style['cellStyleName'] = $namedStyle['name'] ?? null;
                    $style['cellStyleBuiltinId'] = $namedStyle['builtinId'] ?? null;
                    $style['cellStyleCustomBuiltin'] = $namedStyle['customBuiltin'] ?? null;
                }
                $cellFormats[] = $style;
            }
        }

        return [
            'fonts' => $fontsList,
            'fills' => $fillsList,
            'borders' => $bordersList,
            'cellStyleFormats' => $cellStyleFormats,
            'cellFormats' => $cellFormats,
            'cellStyles' => $cellStyles,
            'customNumberFormats' => $customNumberFormats,
        ];
    }

    /**
     * @param list<array<string, mixed>> $fontsList
     * @param list<array<string, mixed>> $fillsList
     * @param list<array<string, mixed>> $bordersList
     * @param array<int, string> $customNumberFormats
     * @param array<string, mixed>|null $baseStyle
     * @return array<string, mixed>
     */
    private function parseStyleXf(\DOMElement $xfElement, array $fontsList, array $fillsList, array $bordersList, array $customNumberFormats, ?array $baseStyle): array
    {
        $style = $baseStyle ?? $this->defaultCellStyle();
        $xfId = $this->integerAttribute($xfElement, 'xfId');
        if ($xfId !== null) {
            $style['xfId'] = $xfId;
        }

        foreach ([
            'applyNumberFormat',
            'applyFont',
            'applyFill',
            'applyBorder',
            'applyAlignment',
            'applyProtection',
            'quotePrefix',
            'pivotButton',
        ] as $attribute) {
            $value = $this->booleanAttribute($xfElement, $attribute);
            if ($value !== null) {
                $style[$attribute] = $value;
            }
        }

        $fontId = $this->integerAttribute($xfElement, 'fontId');
        if ($fontId !== null) {
            $font = $fontsList[$fontId] ?? $this->defaultStyleFont();
            $style['fontId'] = $fontId;
            $style['bold'] = $font['bold'];
            $style['italic'] = $font['italic'];
            $style['underline'] = $font['underline'];
            $style['strike'] = $font['strike'];
            $style['fontName'] = $font['name'];
            $style['fontSize'] = $font['size'];
            $style['fontColor'] = $font['color'];
            $style['fontColorMetadata'] = $font['colorMetadata'];
            $style['fontFamily'] = $font['family'];
            $style['fontCharset'] = $font['charset'];
            $style['fontScheme'] = $font['scheme'];
            $style['fontUnderlineStyle'] = $font['underlineStyle'];
            $style['fontVerticalAlign'] = $font['verticalAlign'];
        }

        $fillId = $this->integerAttribute($xfElement, 'fillId');
        if ($fillId !== null) {
            $fill = $fillsList[$fillId] ?? $this->defaultStyleFill();
            $style['fillId'] = $fillId;
            $style['fillPatternType'] = $fill['patternType'];
            $style['fillForegroundColor'] = $fill['foregroundColor'];
            $style['fillBackgroundColor'] = $fill['backgroundColor'];
            $style['fillForegroundColorMetadata'] = $fill['foregroundColorMetadata'];
            $style['fillBackgroundColorMetadata'] = $fill['backgroundColorMetadata'];
        }

        $borderId = $this->integerAttribute($xfElement, 'borderId');
        if ($borderId !== null) {
            $border = $bordersList[$borderId] ?? $this->defaultStyleBorder();
            $style['borderId'] = $borderId;
            $style['borderLeftStyle'] = $border['leftStyle'];
            $style['borderLeftColor'] = $border['leftColor'];
            $style['borderRightStyle'] = $border['rightStyle'];
            $style['borderRightColor'] = $border['rightColor'];
            $style['borderTopStyle'] = $border['topStyle'];
            $style['borderTopColor'] = $border['topColor'];
            $style['borderBottomStyle'] = $border['bottomStyle'];
            $style['borderBottomColor'] = $border['bottomColor'];
            $style['borderDiagonalStyle'] = $border['diagonalStyle'];
            $style['borderDiagonalColor'] = $border['diagonalColor'];
            $style['borderLeftColorMetadata'] = $border['leftColorMetadata'];
            $style['borderRightColorMetadata'] = $border['rightColorMetadata'];
            $style['borderTopColorMetadata'] = $border['topColorMetadata'];
            $style['borderBottomColorMetadata'] = $border['bottomColorMetadata'];
            $style['borderDiagonalColorMetadata'] = $border['diagonalColorMetadata'];
            $style['borderDiagonalUp'] = $border['diagonalUp'];
            $style['borderDiagonalDown'] = $border['diagonalDown'];
            $style['borderOutline'] = $border['outline'];
        }

        $numFmtId = $this->integerAttribute($xfElement, 'numFmtId');
        if ($numFmtId !== null) {
            $style['numFmtId'] = $numFmtId;
            $style['formatCode'] = $customNumberFormats[$numFmtId] ?? $this->builtInNumberFormat($numFmtId);
        }

        $alignment = $this->firstChildElement($xfElement, 'alignment');
        if ($alignment instanceof \DOMElement) {
            $alignmentTextAttributes = [
                'horizontal' => 'horizontalAlign',
                'vertical' => 'verticalAlign',
            ];
            foreach ($alignmentTextAttributes as $source => $target) {
                $value = trim($alignment->getAttribute($source));
                if ($value !== '') {
                    $style[$target] = $value;
                }
            }

            foreach ([
                'wrapText',
                'shrinkToFit',
                'justifyLastLine',
            ] as $attribute) {
                $value = $this->booleanAttribute($alignment, $attribute);
                if ($value !== null) {
                    $style[$attribute] = $value;
                }
            }

            foreach ([
                'textRotation',
                'indent',
                'relativeIndent',
                'readingOrder',
            ] as $attribute) {
                $value = $this->integerAttribute($alignment, $attribute);
                if ($value !== null) {
                    $style[$attribute] = $value;
                }
            }
        }

        $protection = $this->firstChildElement($xfElement, 'protection');
        if ($protection instanceof \DOMElement) {
            foreach (['locked', 'hidden'] as $attribute) {
                $value = $this->booleanAttribute($protection, $attribute);
                if ($value !== null) {
                    $style[$attribute] = $value;
                }
            }
        }

        return $style;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function parseCellStyles(\DOMElement $root): array
    {
        $cellStylesElement = $this->firstChildElement($root, 'cellStyles');
        if (!$cellStylesElement instanceof \DOMElement) {
            return [];
        }

        $cellStyles = [];
        foreach ($this->childElements($cellStylesElement, 'cellStyle') as $cellStyleElement) {
            $name = trim($cellStyleElement->getAttribute('name'));
            $cellStyles[] = [
                'name' => $name !== '' ? $name : null,
                'xfId' => $this->integerAttribute($cellStyleElement, 'xfId'),
                'builtinId' => $this->integerAttribute($cellStyleElement, 'builtinId'),
                'customBuiltin' => $this->booleanAttribute($cellStyleElement, 'customBuiltin'),
                'hidden' => $this->booleanAttribute($cellStyleElement, 'hidden'),
                'iLevel' => $this->integerAttribute($cellStyleElement, 'iLevel'),
            ];
        }

        return $cellStyles;
    }

    /**
     * @return array<string, mixed>
     */
    private function parseStyleFont(\DOMElement $fontElement): array
    {
        $name = $this->firstChildElement($fontElement, 'name');
        $size = $this->firstChildElement($fontElement, 'sz');
        $color = $this->firstChildElement($fontElement, 'color');
        $underline = $this->firstChildElement($fontElement, 'u');
        $family = $this->firstChildElement($fontElement, 'family');
        $charset = $this->firstChildElement($fontElement, 'charset');
        $scheme = $this->firstChildElement($fontElement, 'scheme');
        $verticalAlign = $this->firstChildElement($fontElement, 'vertAlign');
        $colorMetadata = $color instanceof \DOMElement ? $this->styleColorMetadata($color) : [];
        $underlineStyle = null;
        if ($underline instanceof \DOMElement) {
            $underlineValue = strtolower(trim($underline->getAttribute('val')));
            $underlineStyle = $underlineValue !== '' ? $underlineValue : 'single';
        }

        return [
            'bold' => $this->firstChildElement($fontElement, 'b') instanceof \DOMElement,
            'italic' => $this->firstChildElement($fontElement, 'i') instanceof \DOMElement,
            'underline' => $underline instanceof \DOMElement && strtolower(trim($underline->getAttribute('val'))) !== 'none',
            'strike' => $this->firstChildElement($fontElement, 'strike') instanceof \DOMElement,
            'name' => $name instanceof \DOMElement && trim($name->getAttribute('val')) !== '' ? trim($name->getAttribute('val')) : null,
            'size' => $size instanceof \DOMElement && is_numeric(trim($size->getAttribute('val'))) ? (float) trim($size->getAttribute('val')) : null,
            'color' => $colorMetadata['token'] ?? null,
            'colorMetadata' => $colorMetadata,
            'family' => $family instanceof \DOMElement ? $this->integerAttribute($family, 'val') : null,
            'charset' => $charset instanceof \DOMElement ? $this->integerAttribute($charset, 'val') : null,
            'scheme' => $scheme instanceof \DOMElement && trim($scheme->getAttribute('val')) !== '' ? trim($scheme->getAttribute('val')) : null,
            'underlineStyle' => $underlineStyle,
            'verticalAlign' => $verticalAlign instanceof \DOMElement && trim($verticalAlign->getAttribute('val')) !== '' ? trim($verticalAlign->getAttribute('val')) : null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function defaultStyleFont(): array
    {
        return [
            'bold' => false,
            'italic' => false,
            'underline' => false,
            'strike' => false,
            'name' => null,
            'size' => null,
            'color' => null,
            'colorMetadata' => [],
            'family' => null,
            'charset' => null,
            'scheme' => null,
            'underlineStyle' => null,
            'verticalAlign' => null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function parseStyleFill(\DOMElement $fillElement): array
    {
        $patternFill = $this->firstChildElement($fillElement, 'patternFill');
        if (!$patternFill instanceof \DOMElement) {
            return $this->defaultStyleFill();
        }

        $foreground = $this->firstChildElement($patternFill, 'fgColor');
        $background = $this->firstChildElement($patternFill, 'bgColor');
        $foregroundMetadata = $foreground instanceof \DOMElement ? $this->styleColorMetadata($foreground) : [];
        $backgroundMetadata = $background instanceof \DOMElement ? $this->styleColorMetadata($background) : [];

        return [
            'patternType' => trim($patternFill->getAttribute('patternType')) !== '' ? trim($patternFill->getAttribute('patternType')) : null,
            'foregroundColor' => $foregroundMetadata['token'] ?? null,
            'backgroundColor' => $backgroundMetadata['token'] ?? null,
            'foregroundColorMetadata' => $foregroundMetadata,
            'backgroundColorMetadata' => $backgroundMetadata,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function defaultStyleFill(): array
    {
        return [
            'patternType' => null,
            'foregroundColor' => null,
            'backgroundColor' => null,
            'foregroundColorMetadata' => [],
            'backgroundColorMetadata' => [],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function parseStyleBorder(\DOMElement $borderElement): array
    {
        $left = $this->parseStyleBorderSide($this->firstChildElement($borderElement, 'left'));
        $right = $this->parseStyleBorderSide($this->firstChildElement($borderElement, 'right'));
        $top = $this->parseStyleBorderSide($this->firstChildElement($borderElement, 'top'));
        $bottom = $this->parseStyleBorderSide($this->firstChildElement($borderElement, 'bottom'));
        $diagonal = $this->parseStyleBorderSide($this->firstChildElement($borderElement, 'diagonal'));

        return [
            'leftStyle' => $left['style'],
            'leftColor' => $left['color'],
            'rightStyle' => $right['style'],
            'rightColor' => $right['color'],
            'topStyle' => $top['style'],
            'topColor' => $top['color'],
            'bottomStyle' => $bottom['style'],
            'bottomColor' => $bottom['color'],
            'diagonalStyle' => $diagonal['style'],
            'diagonalColor' => $diagonal['color'],
            'leftColorMetadata' => $left['colorMetadata'],
            'rightColorMetadata' => $right['colorMetadata'],
            'topColorMetadata' => $top['colorMetadata'],
            'bottomColorMetadata' => $bottom['colorMetadata'],
            'diagonalColorMetadata' => $diagonal['colorMetadata'],
            'diagonalUp' => $this->booleanAttribute($borderElement, 'diagonalUp'),
            'diagonalDown' => $this->booleanAttribute($borderElement, 'diagonalDown'),
            'outline' => $this->booleanAttribute($borderElement, 'outline'),
        ];
    }

    /**
     * @return array{style:?string, color:?string, colorMetadata:array<string, mixed>}
     */
    private function parseStyleBorderSide(?\DOMElement $sideElement): array
    {
        if (!$sideElement instanceof \DOMElement) {
            return [
                'style' => null,
                'color' => null,
                'colorMetadata' => [],
            ];
        }

        $color = $this->firstChildElement($sideElement, 'color');
        $colorMetadata = $color instanceof \DOMElement ? $this->styleColorMetadata($color) : [];

        return [
            'style' => trim($sideElement->getAttribute('style')) !== '' ? trim($sideElement->getAttribute('style')) : null,
            'color' => $colorMetadata['token'] ?? null,
            'colorMetadata' => $colorMetadata,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function defaultStyleBorder(): array
    {
        return [
            'leftStyle' => null,
            'leftColor' => null,
            'rightStyle' => null,
            'rightColor' => null,
            'topStyle' => null,
            'topColor' => null,
            'bottomStyle' => null,
            'bottomColor' => null,
            'diagonalStyle' => null,
            'diagonalColor' => null,
            'leftColorMetadata' => [],
            'rightColorMetadata' => [],
            'topColorMetadata' => [],
            'bottomColorMetadata' => [],
            'diagonalColorMetadata' => [],
            'diagonalUp' => null,
            'diagonalDown' => null,
            'outline' => null,
        ];
    }

    private function styleColorValue(\DOMElement $colorElement): ?string
    {
        $metadata = $this->styleColorMetadata($colorElement);

        return is_string($metadata['token'] ?? null) ? $metadata['token'] : null;
    }

    /**
     * @return array<string, mixed>
     */
    private function styleColorMetadata(\DOMElement $colorElement): array
    {
        foreach (['rgb', 'indexed', 'theme', 'auto'] as $attribute) {
            $value = trim($colorElement->getAttribute($attribute));
            if ($value !== '') {
                $metadata = [
                    'source' => $attribute,
                    'value' => $value,
                    'token' => $attribute . ':' . $value,
                ];
                if ($attribute === 'rgb') {
                    $argb = strtoupper($value);
                    if (preg_match('/^[0-9A-F]{8}$/', $argb) === 1) {
                        $metadata['argb'] = $argb;
                        $metadata['alpha'] = substr($argb, 0, 2);
                        $metadata['rgb'] = substr($argb, 2);
                    } elseif (preg_match('/^[0-9A-F]{6}$/', $argb) === 1) {
                        $metadata['rgb'] = $argb;
                    }
                } elseif (in_array($attribute, ['indexed', 'theme'], true) && preg_match('/^-?\d+$/', $value) === 1) {
                    $metadata[$attribute] = (int) $value;
                } elseif ($attribute === 'auto') {
                    $metadata['auto'] = in_array(strtolower($value), ['1', 'true', 'on'], true);
                }

                $tint = trim($colorElement->getAttribute('tint'));
                if ($tint !== '' && is_numeric($tint)) {
                    $metadata['tint'] = (float) $tint;
                }

                return $metadata;
            }
        }

        return [];
    }

    /**
     * @return array<string, mixed>
     */
    private function defaultCellStyle(): array
    {
        return [
            'fontId' => null,
            'fillId' => null,
            'borderId' => null,
            'xfId' => null,
            'cellStyleName' => null,
            'cellStyleBuiltinId' => null,
            'cellStyleCustomBuiltin' => null,
            'bold' => false,
            'italic' => false,
            'underline' => false,
            'strike' => false,
            'fontName' => null,
            'fontSize' => null,
            'fontColor' => null,
            'fontColorMetadata' => [],
            'fontFamily' => null,
            'fontCharset' => null,
            'fontScheme' => null,
            'fontUnderlineStyle' => null,
            'fontVerticalAlign' => null,
            'fillPatternType' => null,
            'fillForegroundColor' => null,
            'fillBackgroundColor' => null,
            'fillForegroundColorMetadata' => [],
            'fillBackgroundColorMetadata' => [],
            'numFmtId' => null,
            'formatCode' => null,
            'horizontalAlign' => null,
            'verticalAlign' => null,
            'wrapText' => null,
            'textRotation' => null,
            'indent' => null,
            'relativeIndent' => null,
            'shrinkToFit' => null,
            'readingOrder' => null,
            'justifyLastLine' => null,
            'locked' => null,
            'hidden' => null,
            'applyNumberFormat' => null,
            'applyFont' => null,
            'applyFill' => null,
            'applyBorder' => null,
            'applyAlignment' => null,
            'applyProtection' => null,
            'quotePrefix' => null,
            'pivotButton' => null,
            'borderLeftStyle' => null,
            'borderLeftColor' => null,
            'borderRightStyle' => null,
            'borderRightColor' => null,
            'borderTopStyle' => null,
            'borderTopColor' => null,
            'borderBottomStyle' => null,
            'borderBottomColor' => null,
            'borderDiagonalStyle' => null,
            'borderDiagonalColor' => null,
            'borderLeftColorMetadata' => [],
            'borderRightColorMetadata' => [],
            'borderTopColorMetadata' => [],
            'borderBottomColorMetadata' => [],
            'borderDiagonalColorMetadata' => [],
            'borderDiagonalUp' => null,
            'borderDiagonalDown' => null,
            'borderOutline' => null,
        ];
    }

    private function firstRelationshipWithTarget(OpcRelationships $relationships, string $needle): ?OpcRelationship
    {
        foreach ($relationships->all() as $relationship) {
            if (str_contains($relationship->target, $needle)) {
                return $relationship;
            }
        }

        return null;
    }

    /**
     * @param list<string> $sharedStrings
     * @param array{
     *     fonts:list<array<string, mixed>>,
     *     fills:list<array<string, mixed>>,
     *     borders:list<array<string, mixed>>,
     *     cellFormats:list<array<string, mixed>>,
     *     customNumberFormats:array<int, string>
     * } $styles
     * @param array<string, list<array<string, mixed>>> $commentsByCell
     * @return array<string, array{
     *     row:int,
     *     column:int,
     *     ref:string,
     *     valueType:string,
     *     text:string,
     *     bold:bool,
     *     italic:bool,
     *     empty:bool,
     *     url:string,
     *     title:string,
     *     colspan:int,
     *     rowspan:int,
     *     covered:bool
     * }>
     */
    private function parseSheetCells(\DOMDocument $document, array $sharedStrings, array $styles, bool $date1904, OpcRelationships $relationships, array $commentsByCell): array
    {
        $root = XmlHtmlDom::rootElement($document, 'worksheet');
        if (!$root instanceof \DOMElement) {
            throw new \RuntimeException('XLSX worksheet XML must have a worksheet root');
        }

        $hyperlinks = $this->parseHyperlinks($root, $relationships);
        $mergeRegions = $this->parseMergeRegions($root);
        $sheetData = $this->firstChildElement($root, 'sheetData');
        if (!$sheetData instanceof \DOMElement) {
            return [];
        }

        $cells = [];
        foreach ($this->childElements($sheetData, 'row') as $rowElement) {
            foreach ($this->childElements($rowElement, 'c') as $cellElement) {
                $cell = $this->parseCell($cellElement, $sharedStrings, $styles, $date1904, $hyperlinks, $commentsByCell);
                if ($cell !== null) {
                    $cells[$cell['row'] . ':' . $cell['column']] = $cell;
                }
            }
        }

        $this->applyMergeRegions($cells, $mergeRegions);

        return $cells;
    }

    /**
     * @param list<string> $sharedStrings
     * @param array{
     *     fonts:list<array<string, mixed>>,
     *     fills:list<array<string, mixed>>,
     *     cellFormats:list<array<string, mixed>>,
     *     customNumberFormats:array<int, string>
     * } $styles
     * @param array<string, array{url:string, title:string}> $hyperlinks
     * @param array<string, list<array<string, mixed>>> $commentsByCell
     * @return array{
     *     row:int,
     *     column:int,
     *     ref:string,
     *     valueType:string,
     *     text:string,
     *     bold:bool,
     *     italic:bool,
     *     empty:bool,
     *     url:string,
     *     title:string,
     *     colspan:int,
     *     rowspan:int,
     *     covered:bool
     * }|null
     */
    private function parseCell(\DOMElement $cellElement, array $sharedStrings, array $styles, bool $date1904, array $hyperlinks, array $commentsByCell): ?array
    {
        $ref = trim($cellElement->getAttribute('r'));
        if ($ref === '') {
            return null;
        }
        $cellRef = $this->parseCellReference($ref);
        if ($cellRef === null) {
            return null;
        }

        $cellType = trim($cellElement->getAttribute('t'));
        $styleIndex = trim($cellElement->getAttribute('s'));
        $styleIndex = preg_match('/^\d+$/', $styleIndex) === 1 ? (int) $styleIndex : null;
        $style = $this->styleForIndex($styleIndex, $styles);
        $valueElement = $this->firstChildElement($cellElement, 'v');
        $rawValue = $valueElement instanceof \DOMElement ? $valueElement->textContent : '';
        $empty = false;
        $valueType = 'text';
        $numericValue = null;
        $numberFormatSection = null;
        $numberFormatKind = null;

        if ($cellType === 's') {
            if (preg_match('/^-?\d+$/', trim($rawValue)) === 1) {
                $index = (int) trim($rawValue);
                if ($index >= 0 && array_key_exists($index, $sharedStrings)) {
                    $text = $sharedStrings[$index];
                } else {
                    $text = '';
                    $empty = true;
                    $valueType = 'empty';
                }
            } else {
                $text = '';
                $empty = true;
                $valueType = 'empty';
            }
        } elseif ($cellType === 'inlineStr') {
            $inlineString = $this->firstChildElement($cellElement, 'is');
            $text = $inlineString instanceof \DOMElement ? $this->allDescendantText($inlineString) : '';
            $empty = $text === '';
            $valueType = $empty ? 'empty' : 'text';
        } elseif ($cellType === 'e') {
            $text = trim($rawValue);
            $empty = $text === '';
            $valueType = $empty ? 'empty' : 'error';
        } elseif (trim($rawValue) === '') {
            $text = '';
            $empty = true;
            $valueType = 'empty';
        } elseif (is_numeric(trim($rawValue))) {
            $number = (float) trim($rawValue);
            $numericValue = $number;
            $numberFormatSection = $this->selectedNumberFormatSectionForStyle($style, $number);
            if ($this->isDateStyle($style)) {
                $text = $this->formatDateSerial($number, $style, $date1904);
                $valueType = 'date';
            } else {
                $text = $this->formatNumberForStyle($number, $style);
                $valueType = 'number';
            }
            $numberFormatKind = $this->numberFormatKindForStyle($style, $numberFormatSection);
        } else {
            $text = $rawValue;
        }

        $hyperlink = $hyperlinks[$ref] ?? ['url' => '', 'title' => ''];
        $comments = $commentsByCell[$ref] ?? [];

        return [
            'row' => $cellRef['row'],
            'column' => $cellRef['column'],
            'ref' => $ref,
            'styleIndex' => $styleIndex,
            'valueType' => $valueType,
            'rawValue' => $numericValue === null ? null : trim($rawValue),
            'numericValue' => $numericValue,
            'numberFormatSection' => $numberFormatSection,
            'numberFormatKind' => $numberFormatKind,
            'text' => $text,
            'bold' => $style['bold'],
            'italic' => $style['italic'],
            'underline' => $style['underline'],
            'strike' => $style['strike'],
            'style' => $style,
            'empty' => $empty,
            'url' => $hyperlink['url'],
            'title' => $hyperlink['title'],
            'comments' => $comments,
            'colspan' => 1,
            'rowspan' => 1,
            'covered' => false,
        ];
    }

    /**
     * @return array{column:int, row:int}|null
     */
    private function parseCellReference(string $ref): ?array
    {
        if (preg_match('/^([A-Za-z]+)([1-9][0-9]*)$/', $ref, $matches) !== 1) {
            return null;
        }

        $column = 0;
        foreach (str_split($matches[1]) as $char) {
            $column = $column * 26 + (ord(strtoupper($char)) - ord('A') + 1);
        }

        return [
            'column' => $column,
            'row' => (int) $matches[2],
        ];
    }

    private function formatNumber(float $number): string
    {
        if (is_finite($number) && floor($number) === $number) {
            return number_format($number, 1, '.', '');
        }

        $formatted = rtrim(rtrim(sprintf('%.15G', $number), '0'), '.');

        return $formatted === '' ? '0.0' : $formatted;
    }

    /**
     * @param array{bold:bool, italic:bool, numFmtId:int|null, formatCode:string|null} $style
     */
    private function formatNumberForStyle(float $number, array $style): string
    {
        $formatCode = $style['formatCode'];
        if ($formatCode === null || $formatCode === 'General') {
            return $this->formatNumber($number);
        }

        $formatSection = $this->selectNumberFormatSection($formatCode, $number);
        if (strcasecmp(trim($formatSection), 'General') === 0 || trim($formatSection) === '') {
            return $this->formatNumber($number);
        }

        $normalizedSection = $this->numberFormatCodeForFormatting($formatSection);
        $isPercent = str_contains($formatSection, '%');
        if ($isPercent) {
            $number *= 100;
        }

        $decimals = $this->numberFormatDecimalPlaces($normalizedSection);
        $useGrouping = preg_match('/[0#?],[0#?]{3}/', $normalizedSection) === 1;
        $currency = $this->numberFormatCurrencySymbol($formatSection);
        $negative = $number < 0;
        $displayNumber = $negative ? abs($number) : $number;

        $text = $decimals === null
            ? $this->formatNumber($displayNumber)
            : number_format($displayNumber, $decimals, '.', $useGrouping ? ',' : '');

        if ($currency !== '') {
            $text = $currency . $text;
        }

        if ($negative) {
            $text = str_contains($formatSection, '(') && str_contains($formatSection, ')')
                ? '(' . $text . ')'
                : '-' . $text;
        }

        return $isPercent ? $text . '%' : $text;
    }

    /**
     * @param array{formatCode:string|null} $style
     */
    private function selectedNumberFormatSectionForStyle(array $style, float $number): ?string
    {
        $formatCode = $style['formatCode'];
        if (!is_string($formatCode) || $formatCode === '' || strcasecmp($formatCode, 'General') === 0) {
            return null;
        }

        return $this->selectNumberFormatSection($formatCode, $number);
    }

    /**
     * @param array{numFmtId:int|null, formatCode:string|null} $style
     */
    private function numberFormatKindForStyle(array $style, ?string $formatSection): ?string
    {
        if ($formatSection === null || trim($formatSection) === '') {
            return null;
        }

        if ($this->isDateStyle($style)) {
            $formatCode = (string) ($style['formatCode'] ?? '');
            if ($this->isElapsedTimeFormat($formatCode)) {
                return 'elapsed-time';
            }

            $normalized = strtolower($this->numberFormatCodeForDetection($formatCode));
            $hasTime = preg_match('/[hs]/', $normalized) === 1;
            $hasDate = preg_match('/[yd]/', $normalized) === 1;
            if ($hasTime && !$hasDate) {
                return 'time';
            }

            return $hasTime ? 'date-time' : 'date';
        }

        $normalizedSection = $this->numberFormatCodeForFormatting($formatSection);
        if (str_contains($formatSection, '%')) {
            return 'percentage';
        }
        if ($this->numberFormatCurrencySymbol($formatSection) !== '') {
            return 'currency';
        }
        if (preg_match('/e[+-]?[0#]+/i', $normalizedSection) === 1) {
            return 'scientific';
        }
        if (str_contains($normalizedSection, '/')) {
            return 'fraction';
        }

        return 'number';
    }

    private function selectNumberFormatSection(string $formatCode, float $number): string
    {
        $sections = explode(';', $formatCode);
        if (count($sections) === 1) {
            return $sections[0];
        }

        if ($number < 0) {
            return trim($sections[1] ?? '') !== '' ? $sections[1] : $sections[0];
        }
        if ($number == 0.0 && isset($sections[2]) && trim($sections[2]) !== '') {
            return $sections[2];
        }

        return $sections[0];
    }

    private function numberFormatDecimalPlaces(string $formatSection): ?int
    {
        if (preg_match('/\.([0#?]+)/', $formatSection, $matches) === 1) {
            return strlen($matches[1]);
        }
        if (preg_match('/[0#?]/', $formatSection) === 1) {
            return 0;
        }

        return null;
    }

    private function numberFormatCurrencySymbol(string $formatSection): string
    {
        if (preg_match('/\[\$([^-\\]]+)/', $formatSection, $matches) === 1) {
            return $matches[1];
        }

        return str_contains($formatSection, '$') ? '$' : '';
    }

    /**
     * @param array{bold:bool, italic:bool, numFmtId:int|null, formatCode:string|null} $style
     */
    private function isDateStyle(array $style): bool
    {
        $numFmtId = $style['numFmtId'];
        if ($numFmtId !== null && in_array($numFmtId, [14, 15, 16, 17, 22, 27, 30, 36, 45, 46, 47, 50, 57], true)) {
            return true;
        }

        $formatCode = $style['formatCode'];
        if ($formatCode === null) {
            return false;
        }

        $normalized = strtolower($this->numberFormatCodeForDetection($formatCode));
        if ($normalized === '') {
            return false;
        }

        return preg_match('/[ymdhs]/', $normalized) === 1
            && preg_match('/[0#?]/', $normalized) !== 1;
    }

    /**
     * @param array{bold:bool, italic:bool, numFmtId:int|null, formatCode:string|null} $style
     */
    private function formatDateSerial(float $serial, array $style, bool $date1904): string
    {
        $rawFormatCode = (string) ($style['formatCode'] ?? '');
        if ($this->isElapsedTimeFormat($rawFormatCode)) {
            return $this->formatElapsedTimeSerial($serial, $rawFormatCode);
        }

        $days = (int) floor($serial);
        $fraction = $serial - $days;
        if ($fraction < 0) {
            $fraction += 1.0;
            $days--;
        }

        $date = new \DateTimeImmutable($date1904 ? '1904-01-01 00:00:00 UTC' : '1899-12-31 00:00:00 UTC');
        if (!$date1904 && $days >= 60) {
            $days--;
        }
        if ($days !== 0) {
            $date = $date->modify(($days >= 0 ? '+' : '') . $days . ' days') ?: $date;
        }

        $seconds = (int) round($fraction * 86400);
        if ($seconds !== 0) {
            $date = $date->modify('+' . $seconds . ' seconds') ?: $date;
        }

        $formatCode = strtolower($this->numberFormatCodeForDetection($rawFormatCode));
        $hasTime = preg_match('/[hs]/', $formatCode) === 1;
        $hasDate = preg_match('/[yd]/', $formatCode) === 1;
        if ($hasTime && !$hasDate) {
            return $date->format($this->timeFormatForStyle($rawFormatCode));
        }

        return $hasTime ? $date->format('Y-m-d ' . $this->timeFormatForStyle($rawFormatCode)) : $date->format('Y-m-d');
    }

    private function timeFormatForStyle(string $formatCode): string
    {
        $hasSeconds = preg_match('/s+/i', $this->numberFormatCodeForDetection($formatCode)) === 1;
        $usesMeridiem = preg_match('/am\/pm|a\/p/i', $formatCode) === 1;
        if ($usesMeridiem) {
            return $hasSeconds ? 'g:i:s A' : 'g:i A';
        }

        return $hasSeconds ? 'H:i:s' : 'H:i';
    }

    private function isElapsedTimeFormat(string $formatCode): bool
    {
        return preg_match('/\[(h|m|s)\]/i', $formatCode) === 1;
    }

    private function formatElapsedTimeSerial(float $serial, string $formatCode): string
    {
        $negative = $serial < 0;
        $totalSeconds = (int) round(abs($serial) * 86400);
        $prefix = $negative ? '-' : '';
        if (preg_match('/\[m\]/i', $formatCode) === 1) {
            $minutes = intdiv($totalSeconds, 60);
            $seconds = $totalSeconds % 60;

            return $prefix . sprintf('%d:%02d', $minutes, $seconds);
        }
        if (preg_match('/\[s\]/i', $formatCode) === 1) {
            return $prefix . (string) $totalSeconds;
        }

        $hours = intdiv($totalSeconds, 3600);
        $minutes = intdiv($totalSeconds % 3600, 60);
        $seconds = $totalSeconds % 60;

        return str_contains(strtolower($formatCode), ':ss')
            ? $prefix . sprintf('%d:%02d:%02d', $hours, $minutes, $seconds)
            : $prefix . sprintf('%d:%02d', $hours, $minutes);
    }

    private function numberFormatCodeForDetection(string $formatCode): string
    {
        $formatCode = preg_replace('/"[^"]*"/', '', $formatCode) ?? $formatCode;
        $formatCode = preg_replace('/\[[^\]]+\]/', '', $formatCode) ?? $formatCode;
        $formatCode = preg_replace('/\\\\./', '', $formatCode) ?? $formatCode;
        $formatCode = explode(';', $formatCode)[0] ?? $formatCode;

        return trim($formatCode);
    }

    private function numberFormatCodeForFormatting(string $formatCode): string
    {
        $formatCode = preg_replace('/"[^"]*"/', '', $formatCode) ?? $formatCode;
        $formatCode = preg_replace('/\[[^\]]+\]/', '', $formatCode) ?? $formatCode;
        $formatCode = preg_replace('/\\\\./', '', $formatCode) ?? $formatCode;
        $formatCode = str_replace(['_', '*'], '', $formatCode);

        return trim($formatCode);
    }

    private function builtInNumberFormat(int $id): ?string
    {
        return [
            0 => 'General',
            1 => '0',
            2 => '0.00',
            3 => '#,##0',
            4 => '#,##0.00',
            5 => '$#,##0;($#,##0)',
            6 => '$#,##0;[Red]($#,##0)',
            7 => '$#,##0.00;($#,##0.00)',
            8 => '$#,##0.00;[Red]($#,##0.00)',
            9 => '0%',
            10 => '0.00%',
            11 => '0.00E+00',
            12 => '# ?/?',
            13 => '# ??/??',
            14 => 'm/d/yy',
            15 => 'd-mmm-yy',
            16 => 'd-mmm',
            17 => 'mmm-yy',
            18 => 'h:mm AM/PM',
            19 => 'h:mm:ss AM/PM',
            20 => 'h:mm',
            21 => 'h:mm:ss',
            22 => 'm/d/yy h:mm',
            27 => '[$-404]e/m/d',
            30 => 'm/d/yy',
            36 => '[$-404]e/m/d',
            37 => '#,##0;(#,##0)',
            38 => '#,##0;[Red](#,##0)',
            39 => '#,##0.00;(#,##0.00)',
            40 => '#,##0.00;[Red](#,##0.00)',
            41 => '_(* #,##0_);_(* (#,##0);_(* "-"_);_(@_)',
            42 => '_($* #,##0_);_($* (#,##0);_($* "-"_);_(@_)',
            43 => '_(* #,##0.00_);_(* (#,##0.00);_(* "-"??_);_(@_)',
            44 => '_($* #,##0.00_);_($* (#,##0.00);_($* "-"??_);_(@_)',
            45 => 'mm:ss',
            46 => '[h]:mm:ss',
            47 => 'mmss.0',
            48 => '##0.0E+0',
            49 => '@',
            50 => '[$-404]e/m/d',
            57 => '[$-404]e/m/d',
        ][$id] ?? null;
    }

    /**
     * @param array{
     *     fonts:list<array<string, mixed>>,
     *     fills:list<array<string, mixed>>,
     *     cellFormats:list<array<string, mixed>>,
     *     customNumberFormats:array<int, string>
     * } $styles
     * @return array<string, mixed>
     */
    private function styleForIndex(?int $styleIndex, array $styles): array
    {
        $default = $this->defaultCellStyle();
        if ($styleIndex === null) {
            return $default;
        }

        if (array_key_exists($styleIndex, $styles['cellFormats'])) {
            return $styles['cellFormats'][$styleIndex];
        }

        $font = $styles['fonts'][$styleIndex] ?? null;
        if (is_array($font)) {
            return array_replace($default, [
                'bold' => (bool) ($font['bold'] ?? false),
                'italic' => (bool) ($font['italic'] ?? false),
                'underline' => (bool) ($font['underline'] ?? false),
                'strike' => (bool) ($font['strike'] ?? false),
                'fontName' => $font['name'] ?? null,
                'fontSize' => $font['size'] ?? null,
                'fontColor' => $font['color'] ?? null,
                'fontColorMetadata' => $font['colorMetadata'] ?? [],
                'fontFamily' => $font['family'] ?? null,
                'fontCharset' => $font['charset'] ?? null,
                'fontScheme' => $font['scheme'] ?? null,
                'fontUnderlineStyle' => $font['underlineStyle'] ?? null,
                'fontVerticalAlign' => $font['verticalAlign'] ?? null,
            ]);
        }

        return $default;
    }

    /**
     * @param array<string, array{
     *     row:int,
     *     column:int,
     *     ref:string,
     *     valueType:string,
     *     text:string,
     *     bold:bool,
     *     italic:bool,
     *     empty:bool,
     *     url:string,
     *     title:string,
     *     colspan:int,
     *     rowspan:int,
     *     covered:bool
     * }> $cells
     */
    private function cellsToTable(string $sheetName, array $cells): ?AstNode
    {
        if ($cells === []) {
            return null;
        }

        $rows = array_column($cells, 'row');
        $columns = array_column($cells, 'column');
        $minRow = min($rows);
        $maxRow = max($rows);
        $minColumn = min($columns);
        $maxColumn = max($columns);

        $grid = [];
        for ($row = $minRow; $row <= $maxRow; $row++) {
            $gridRow = [];
            for ($column = $minColumn; $column <= $maxColumn; $column++) {
                $gridRow[] = $cells[$row . ':' . $column] ?? null;
            }
            $grid[] = $gridRow;
        }

        $header = array_shift($grid);
        if (!is_array($header)) {
            return null;
        }
        while ($grid !== [] && $this->isEmptyRow($grid[count($grid) - 1])) {
            array_pop($grid);
        }

        return new AstNode('table', [
            'caption' => '',
            'alignments' => array_fill(0, count($header), 'default'),
            'xlsxSheetName' => $sheetName,
        ], [
            new AstNode('table_head', [], [
                $this->tableRow($header, true),
            ]),
            new AstNode('table_body', [], array_map(
                fn (array $row): AstNode => $this->tableRow($row, false),
                $grid
            )),
        ]);
    }

    /**
     * @param list<array<string, mixed>|null> $row
     */
    private function tableRow(array $row, bool $header): AstNode
    {
        $cells = [];
        foreach ($row as $columnIndex => $cell) {
            if (is_array($cell) && ($cell['covered'] ?? false) === true) {
                continue;
            }
            $text = is_array($cell) ? (string) $cell['text'] : '';
            $attrs = [
                'header' => $header,
                'text' => $text,
                'sourceCell' => is_array($cell) ? (string) $cell['ref'] : null,
                'sourceColumn' => $columnIndex,
                'xlsxValueType' => is_array($cell) ? (string) $cell['valueType'] : 'empty',
            ];
            if (is_array($cell)) {
                $attrs += $this->cellValueAttributes($cell);
                $attrs += $this->cellStyleAttributes($cell);
                $commentAttributes = $this->cellCommentAttributes($cell);
                if ($commentAttributes !== []) {
                    $attrs += $commentAttributes;
                }
                $horizontalAlign = (string) ($cell['style']['horizontalAlign'] ?? '');
                if (in_array($horizontalAlign, ['left', 'right', 'center'], true)) {
                    $attrs['align'] = $horizontalAlign;
                }
            }
            if (is_array($cell) && (int) ($cell['colspan'] ?? 1) > 1) {
                $attrs['colspan'] = (int) $cell['colspan'];
            }
            if (is_array($cell) && (int) ($cell['rowspan'] ?? 1) > 1) {
                $attrs['rowspan'] = (int) $cell['rowspan'];
            }

            $cells[] = new AstNode('table_cell', $attrs, [
                new AstNode('plain', [], $this->cellInlines(is_array($cell) ? $cell : null)),
            ]);
        }

        return new AstNode('table_row', ['header' => $header], $cells);
    }

    /**
     * @param array<string, mixed>|null $cell
     * @return list<AstNode>
     */
    private function cellInlines(?array $cell): array
    {
        if ($cell === null || ($cell['empty'] ?? true) === true) {
            return [];
        }

        $inlines = [new AstNode('text', ['text' => (string) $cell['text']])];
        if (($cell['bold'] ?? false) === true) {
            $inlines = [new AstNode('strong', [], $inlines)];
        }
        if (($cell['italic'] ?? false) === true) {
            $inlines = [new AstNode('emph', [], $inlines)];
        }
        if (($cell['underline'] ?? false) === true) {
            $inlines = [new AstNode('underline', [], $inlines)];
        }
        if (($cell['strike'] ?? false) === true) {
            $inlines = [new AstNode('strikeout', [], $inlines)];
        }
        if (($cell['url'] ?? '') !== '') {
            $inlines = [new AstNode('link', [
                'url' => (string) $cell['url'],
                'title' => (string) ($cell['title'] ?? ''),
            ], $inlines)];
        }

        return $inlines;
    }

    /**
     * @param array<string, mixed> $cell
     * @return array<string, mixed>
     */
    private function cellValueAttributes(array $cell): array
    {
        $attrs = [];
        $map = [
            'rawValue' => 'xlsxRawValue',
            'numericValue' => 'xlsxNumericValue',
            'numberFormatSection' => 'xlsxNumberFormatSection',
            'numberFormatKind' => 'xlsxNumberFormatKind',
        ];

        foreach ($map as $source => $target) {
            $value = $cell[$source] ?? null;
            if ($value === null || $value === '') {
                continue;
            }
            $attrs[$target] = $value;
        }

        return $attrs;
    }

    /**
     * @param array<string, mixed> $cell
     * @return array<string, mixed>
     */
    private function cellStyleAttributes(array $cell): array
    {
        $style = $cell['style'] ?? [];
        if (!is_array($style)) {
            $style = [];
        }

        $attrs = [];
        $map = [
            'styleIndex' => 'xlsxStyleIndex',
            'xfId' => 'xlsxStyleXfId',
            'cellStyleName' => 'xlsxCellStyleName',
            'cellStyleBuiltinId' => 'xlsxCellStyleBuiltinId',
            'cellStyleCustomBuiltin' => 'xlsxCellStyleCustomBuiltin',
            'numFmtId' => 'xlsxNumberFormatId',
            'formatCode' => 'xlsxNumberFormatCode',
            'fontId' => 'xlsxFontId',
            'fontName' => 'xlsxFontName',
            'fontSize' => 'xlsxFontSize',
            'fontColor' => 'xlsxFontColor',
            'fontColorMetadata' => 'xlsxFontColorMetadata',
            'fontFamily' => 'xlsxFontFamily',
            'fontCharset' => 'xlsxFontCharset',
            'fontScheme' => 'xlsxFontScheme',
            'fontUnderlineStyle' => 'xlsxFontUnderlineStyle',
            'fontVerticalAlign' => 'xlsxFontVerticalAlign',
            'fillId' => 'xlsxFillId',
            'fillPatternType' => 'xlsxFillPatternType',
            'fillForegroundColor' => 'xlsxFillForegroundColor',
            'fillBackgroundColor' => 'xlsxFillBackgroundColor',
            'fillForegroundColorMetadata' => 'xlsxFillForegroundColorMetadata',
            'fillBackgroundColorMetadata' => 'xlsxFillBackgroundColorMetadata',
            'borderId' => 'xlsxBorderId',
            'borderLeftStyle' => 'xlsxBorderLeftStyle',
            'borderLeftColor' => 'xlsxBorderLeftColor',
            'borderLeftColorMetadata' => 'xlsxBorderLeftColorMetadata',
            'borderRightStyle' => 'xlsxBorderRightStyle',
            'borderRightColor' => 'xlsxBorderRightColor',
            'borderRightColorMetadata' => 'xlsxBorderRightColorMetadata',
            'borderTopStyle' => 'xlsxBorderTopStyle',
            'borderTopColor' => 'xlsxBorderTopColor',
            'borderTopColorMetadata' => 'xlsxBorderTopColorMetadata',
            'borderBottomStyle' => 'xlsxBorderBottomStyle',
            'borderBottomColor' => 'xlsxBorderBottomColor',
            'borderBottomColorMetadata' => 'xlsxBorderBottomColorMetadata',
            'borderDiagonalStyle' => 'xlsxBorderDiagonalStyle',
            'borderDiagonalColor' => 'xlsxBorderDiagonalColor',
            'borderDiagonalColorMetadata' => 'xlsxBorderDiagonalColorMetadata',
            'borderDiagonalUp' => 'xlsxBorderDiagonalUp',
            'borderDiagonalDown' => 'xlsxBorderDiagonalDown',
            'borderOutline' => 'xlsxBorderOutline',
            'horizontalAlign' => 'xlsxHorizontalAlign',
            'verticalAlign' => 'xlsxVerticalAlign',
            'wrapText' => 'xlsxWrapText',
            'textRotation' => 'xlsxTextRotation',
            'indent' => 'xlsxIndent',
            'relativeIndent' => 'xlsxRelativeIndent',
            'shrinkToFit' => 'xlsxShrinkToFit',
            'readingOrder' => 'xlsxReadingOrder',
            'justifyLastLine' => 'xlsxJustifyLastLine',
            'locked' => 'xlsxLocked',
            'hidden' => 'xlsxHidden',
            'applyNumberFormat' => 'xlsxApplyNumberFormat',
            'applyFont' => 'xlsxApplyFont',
            'applyFill' => 'xlsxApplyFill',
            'applyBorder' => 'xlsxApplyBorder',
            'applyAlignment' => 'xlsxApplyAlignment',
            'applyProtection' => 'xlsxApplyProtection',
            'quotePrefix' => 'xlsxQuotePrefix',
            'pivotButton' => 'xlsxPivotButton',
        ];

        foreach ($map as $source => $target) {
            $value = $source === 'styleIndex' ? ($cell['styleIndex'] ?? null) : ($style[$source] ?? null);
            if ($value === null || $value === '') {
                continue;
            }
            $attrs[$target] = $value;
        }

        if (($style['underline'] ?? false) === true) {
            $attrs['xlsxUnderline'] = true;
        }
        if (($style['strike'] ?? false) === true) {
            $attrs['xlsxStrike'] = true;
        }

        return $attrs;
    }

    /**
     * @param array<string, mixed> $cell
     * @return array<string, mixed>
     */
    private function cellCommentAttributes(array $cell): array
    {
        $comments = $cell['comments'] ?? [];
        if (!is_array($comments) || $comments === []) {
            return [];
        }

        return [
            'xlsxCommentCount' => count($comments),
            'xlsxComments' => $comments,
            'xlsxCommentAuthors' => array_values(array_unique(array_filter(array_map(
                static fn (mixed $comment): ?string => is_array($comment) && is_string($comment['author'] ?? null) ? $comment['author'] : null,
                $comments
            )))),
        ];
    }

    /**
     * @return array{
     *     commentCount:int,
     *     comments:list<array<string, mixed>>,
     *     commentsByCell:array<string, list<array<string, mixed>>>,
     *     commentDiagnostics:list<string>
     * }
     */
    private function parseSheetComments(ZipPackage $package, string $sheetPart, OpcRelationships $relationships): array
    {
        $comments = [];
        $commentsByCell = [];
        $diagnostics = [];

        foreach ($relationships->all() as $relationship) {
            if ($relationship->type !== 'http://schemas.openxmlformats.org/officeDocument/2006/relationships/comments') {
                continue;
            }
            if ($relationship->isExternal()) {
                $diagnostics[] = 'comments-external-relationship-skipped:' . $relationship->id;
                continue;
            }

            try {
                $part = OpcPackagePath::stripQueryAndFragment($relationships->resolveTarget($relationship));
            } catch (\Throwable $exception) {
                $diagnostics[] = 'comments-target-resolution-error:' . $relationship->id;
                continue;
            }

            if (!$package->has($part)) {
                $diagnostics[] = 'comments-part-missing:' . ltrim($part, '/');
                continue;
            }

            try {
                $document = $this->loadPackageXml($package, $part, 'XLSX comments ' . $relationship->id . ' for ' . ltrim($sheetPart, '/'));
            } catch (\InvalidArgumentException|\RuntimeException) {
                $diagnostics[] = 'comments-part-unreadable:' . ltrim($part, '/');
                continue;
            }

            $root = XmlHtmlDom::rootElement($document, 'comments');
            if (!$root instanceof \DOMElement) {
                $diagnostics[] = 'comments-root-missing:' . ltrim($part, '/');
                continue;
            }

            $authors = [];
            $authorsElement = $this->firstChildElement($root, 'authors');
            if ($authorsElement instanceof \DOMElement) {
                foreach ($this->childElements($authorsElement, 'author') as $authorElement) {
                    $authors[] = $authorElement->textContent;
                }
            }

            $commentList = $this->firstChildElement($root, 'commentList');
            if (!$commentList instanceof \DOMElement) {
                continue;
            }

            foreach ($this->childElements($commentList, 'comment') as $commentElement) {
                $ref = trim($commentElement->getAttribute('ref'));
                if ($this->parseCellReference($ref) === null) {
                    $diagnostics[] = 'comments-invalid-ref:' . ($ref === '' ? '<empty>' : $ref);
                    continue;
                }

                $authorId = $this->integerAttribute($commentElement, 'authorId');
                $textElement = $this->firstChildElement($commentElement, 'text');
                $text = $textElement instanceof \DOMElement ? $this->allDescendantText($textElement) : '';
                $record = [
                    'ref' => $ref,
                    'authorId' => $authorId,
                    'author' => $authorId !== null ? ($authors[$authorId] ?? null) : null,
                    'text' => $text,
                    'textBytes' => strlen($text),
                    'textSha256' => hash('sha256', $text),
                    'partName' => ltrim($part, '/'),
                    'relationshipId' => $relationship->id,
                ];
                $comments[] = $record;
                $commentsByCell[$ref] ??= [];
                $commentsByCell[$ref][] = $record;
            }
        }

        return [
            'commentCount' => count($comments),
            'comments' => $comments,
            'commentsByCell' => $commentsByCell,
            'commentDiagnostics' => array_values(array_unique($diagnostics)),
        ];
    }

    /**
     * @return array{
     *     formulaCellCount:int,
     *     formulaCachedValueCount:int,
     *     formulaDiagnostics:list<array{ref:string, formulaType:string, sharedIndex:int|null, hasCachedValue:bool, cachedValueType:string, formulaTextBytes:int, formulaSha256:string}>,
     *     errorCellCount:int,
     *     errorDiagnostics:list<array{ref:string, code:string, fromFormula:bool}>
     * }
     */
    private function parseSheetDiagnostics(\DOMDocument $document): array
    {
        $root = XmlHtmlDom::rootElement($document, 'worksheet');
        if (!$root instanceof \DOMElement) {
            return [
                'formulaCellCount' => 0,
                'formulaCachedValueCount' => 0,
                'formulaDiagnostics' => [],
                'errorCellCount' => 0,
                'errorDiagnostics' => [],
            ];
        }

        $sheetData = $this->firstChildElement($root, 'sheetData');
        if (!$sheetData instanceof \DOMElement) {
            return [
                'formulaCellCount' => 0,
                'formulaCachedValueCount' => 0,
                'formulaDiagnostics' => [],
                'errorCellCount' => 0,
                'errorDiagnostics' => [],
            ];
        }

        $formulaDiagnostics = [];
        $errorDiagnostics = [];
        foreach ($this->childElements($sheetData, 'row') as $rowElement) {
            foreach ($this->childElements($rowElement, 'c') as $cellElement) {
                $ref = trim($cellElement->getAttribute('r'));
                if ($ref === '' || $this->parseCellReference($ref) === null) {
                    continue;
                }

                $formulaElement = $this->firstChildElement($cellElement, 'f');
                $valueElement = $this->firstChildElement($cellElement, 'v');
                $rawValue = $valueElement instanceof \DOMElement ? trim($valueElement->textContent) : '';
                if ($formulaElement instanceof \DOMElement) {
                    $formulaText = $formulaElement->textContent;
                    $formulaDiagnostics[] = [
                        'ref' => $ref,
                        'formulaType' => trim($formulaElement->getAttribute('t')) !== '' ? trim($formulaElement->getAttribute('t')) : 'normal',
                        'sharedIndex' => $this->integerAttribute($formulaElement, 'si'),
                        'hasCachedValue' => $rawValue !== '',
                        'cachedValueType' => $this->cachedValueTypeForCell($cellElement),
                        'formulaTextBytes' => strlen($formulaText),
                        'formulaSha256' => hash('sha256', $formulaText),
                    ];
                }

                if (trim($cellElement->getAttribute('t')) === 'e' && $rawValue !== '') {
                    $errorDiagnostics[] = [
                        'ref' => $ref,
                        'code' => $rawValue,
                        'fromFormula' => $formulaElement instanceof \DOMElement,
                    ];
                }
            }
        }

        return [
            'formulaCellCount' => count($formulaDiagnostics),
            'formulaCachedValueCount' => count(array_filter($formulaDiagnostics, static fn (array $diagnostic): bool => $diagnostic['hasCachedValue'])),
            'formulaDiagnostics' => $formulaDiagnostics,
            'errorCellCount' => count($errorDiagnostics),
            'errorDiagnostics' => $errorDiagnostics,
        ];
    }

    /**
     * @return array{
     *     autoFilterRanges:list<string>,
     *     autoFilters:list<array<string, mixed>>,
     *     tableParts:list<array<string, mixed>>,
     *     tablePartDiagnostics:list<string>
     * }
     */
    private function parseSheetTableMetadata(ZipPackage $package, string $sheetPart, \DOMDocument $document, OpcRelationships $relationships): array
    {
        $root = XmlHtmlDom::rootElement($document, 'worksheet');
        if (!$root instanceof \DOMElement) {
            return ['autoFilterRanges' => [], 'autoFilters' => [], 'tableParts' => [], 'tablePartDiagnostics' => []];
        }

        $autoFilterRanges = [];
        $autoFilters = [];
        $worksheetAutoFilter = $this->firstChildElement($root, 'autoFilter');
        if ($worksheetAutoFilter instanceof \DOMElement) {
            $this->appendAutoFilterRange($autoFilterRanges, $worksheetAutoFilter->getAttribute('ref'));
            $autoFilters[] = $this->parseAutoFilter($worksheetAutoFilter, 'worksheet');
        }

        $tableParts = [];
        $diagnostics = [];
        $tablePartsElement = $this->firstChildElement($root, 'tableParts');
        if (!$tablePartsElement instanceof \DOMElement) {
            return [
                'autoFilterRanges' => $autoFilterRanges,
                'autoFilters' => $autoFilters,
                'tableParts' => [],
                'tablePartDiagnostics' => [],
            ];
        }

        foreach ($this->childElements($tablePartsElement, 'tablePart') as $tablePartElement) {
            $relationshipId = $this->relationshipId($tablePartElement);
            if ($relationshipId === '') {
                $diagnostics[] = 'table-part-missing-relationship-id';
                continue;
            }

            $relationship = $relationships->byId($relationshipId);
            if (!$relationship instanceof OpcRelationship) {
                $diagnostics[] = 'table-part-relationship-missing:' . $relationshipId;
                continue;
            }
            if ($relationship->isExternal()) {
                $diagnostics[] = 'table-part-external-relationship-skipped:' . $relationshipId;
                continue;
            }

            $part = OpcPackagePath::stripQueryAndFragment($relationships->resolveTarget($relationship));
            $available = $package->has($part);
            $tablePart = [
                'relationshipId' => $relationshipId,
                'partName' => ltrim($part, '/'),
                'available' => $available,
            ];
            if (!$available) {
                $diagnostics[] = 'table-part-missing:' . ltrim($part, '/');
                $tableParts[] = $tablePart;
                continue;
            }

            try {
                $tableDocument = $this->loadPackageXml($package, $part, 'XLSX table ' . $relationshipId . ' for ' . ltrim($sheetPart, '/'));
            } catch (\InvalidArgumentException|\RuntimeException) {
                $diagnostics[] = 'table-part-unreadable:' . ltrim($part, '/');
                $tableParts[] = $tablePart;
                continue;
            }
            $tableMetadata = $this->parseTablePart($tableDocument);
            if (($tableMetadata['autoFilterRef'] ?? null) !== null) {
                $this->appendAutoFilterRange($autoFilterRanges, (string) $tableMetadata['autoFilterRef']);
            }
            if (is_array($tableMetadata['autoFilter'] ?? null)) {
                $tableAutoFilter = $tableMetadata['autoFilter'];
                $tableAutoFilter['tableRelationshipId'] = $relationshipId;
                $tableAutoFilter['tablePartName'] = ltrim($part, '/');
                $autoFilters[] = $tableAutoFilter;
            }
            $tableParts[] = array_merge($tablePart, $tableMetadata);
        }

        return [
            'autoFilterRanges' => $autoFilterRanges,
            'autoFilters' => $autoFilters,
            'tableParts' => $tableParts,
            'tablePartDiagnostics' => array_values(array_unique($diagnostics)),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function parseTablePart(\DOMDocument $document): array
    {
        $root = XmlHtmlDom::rootElement($document, 'table');
        if (!$root instanceof \DOMElement) {
            return [
                'id' => null,
                'name' => null,
                'displayName' => null,
                'ref' => null,
                'autoFilterRef' => null,
                'autoFilter' => null,
                'headerRowCount' => null,
                'totalsRowCount' => null,
                'totalsRowShown' => null,
                'published' => null,
                'columnCount' => 0,
                'columns' => [],
                'columnNames' => [],
                'autoFilterColumnCount' => 0,
                'autoFilterColumns' => [],
                'tableStyleInfo' => null,
            ];
        }

        $autoFilter = $this->firstChildElement($root, 'autoFilter');
        $tableColumns = $this->firstChildElement($root, 'tableColumns');
        $columns = $tableColumns instanceof \DOMElement ? $this->parseTableColumns($tableColumns) : [];
        $autoFilterMetadata = $autoFilter instanceof \DOMElement ? $this->parseAutoFilter($autoFilter, 'table') : null;

        return [
            'id' => $this->integerAttribute($root, 'id'),
            'name' => $root->hasAttribute('name') ? $root->getAttribute('name') : null,
            'displayName' => $root->hasAttribute('displayName') ? $root->getAttribute('displayName') : null,
            'ref' => $root->hasAttribute('ref') ? $root->getAttribute('ref') : null,
            'autoFilterRef' => $autoFilter instanceof \DOMElement && trim($autoFilter->getAttribute('ref')) !== ''
                ? trim($autoFilter->getAttribute('ref'))
                : null,
            'autoFilter' => $autoFilterMetadata,
            'headerRowCount' => $this->integerAttribute($root, 'headerRowCount'),
            'totalsRowCount' => $this->integerAttribute($root, 'totalsRowCount'),
            'totalsRowShown' => $this->booleanAttribute($root, 'totalsRowShown'),
            'published' => $this->booleanAttribute($root, 'published'),
            'columnCount' => count($columns),
            'columns' => $columns,
            'columnNames' => array_values(array_filter(
                array_map(
                    static fn (array $column): ?string => is_string($column['name'] ?? null) ? $column['name'] : null,
                    $columns
                ),
                static fn (?string $name): bool => $name !== null && $name !== ''
            )),
            'autoFilterColumnCount' => is_array($autoFilterMetadata) ? (int) ($autoFilterMetadata['filterColumnCount'] ?? 0) : 0,
            'autoFilterColumns' => is_array($autoFilterMetadata['filterColumns'] ?? null) ? $autoFilterMetadata['filterColumns'] : [],
            'tableStyleInfo' => $this->parseTableStyleInfo($this->firstChildElement($root, 'tableStyleInfo')),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function parseAutoFilter(\DOMElement $autoFilter, string $source): array
    {
        $filterColumns = [];
        foreach ($this->childElements($autoFilter, 'filterColumn') as $filterColumn) {
            $filterColumns[] = $this->parseFilterColumn($filterColumn);
        }

        $ref = trim($autoFilter->getAttribute('ref'));

        return [
            'source' => $source,
            'ref' => $ref !== '' ? $ref : null,
            'filterColumnCount' => count($filterColumns),
            'filterColumns' => $filterColumns,
            'sortState' => $this->parseSortState($this->firstChildElement($autoFilter, 'sortState')),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function parseFilterColumn(\DOMElement $filterColumn): array
    {
        $filters = $this->firstChildElement($filterColumn, 'filters');
        $customFilters = $this->firstChildElement($filterColumn, 'customFilters');
        $dynamicFilter = $this->firstChildElement($filterColumn, 'dynamicFilter');
        $top10 = $this->firstChildElement($filterColumn, 'top10');
        $colorFilter = $this->firstChildElement($filterColumn, 'colorFilter');
        $iconFilter = $this->firstChildElement($filterColumn, 'iconFilter');

        $details = null;
        $type = null;
        if ($filters instanceof \DOMElement) {
            $type = 'filters';
            $details = $this->parseFilters($filters);
        } elseif ($customFilters instanceof \DOMElement) {
            $type = 'customFilters';
            $details = $this->parseCustomFilters($customFilters);
        } elseif ($dynamicFilter instanceof \DOMElement) {
            $type = 'dynamicFilter';
            $details = $this->parseDynamicFilter($dynamicFilter);
        } elseif ($top10 instanceof \DOMElement) {
            $type = 'top10';
            $details = $this->parseTop10Filter($top10);
        } elseif ($colorFilter instanceof \DOMElement) {
            $type = 'colorFilter';
            $details = [
                'dxfId' => $this->integerAttribute($colorFilter, 'dxfId'),
                'cellColor' => $this->booleanAttribute($colorFilter, 'cellColor'),
            ];
        } elseif ($iconFilter instanceof \DOMElement) {
            $type = 'iconFilter';
            $details = [
                'iconSet' => trim($iconFilter->getAttribute('iconSet')) !== '' ? trim($iconFilter->getAttribute('iconSet')) : null,
                'iconId' => $this->integerAttribute($iconFilter, 'iconId'),
            ];
        }

        return [
            'colId' => $this->integerAttribute($filterColumn, 'colId'),
            'hiddenButton' => $this->booleanAttribute($filterColumn, 'hiddenButton'),
            'showButton' => $this->booleanAttribute($filterColumn, 'showButton'),
            'filterType' => $type,
            'details' => $details,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function parseFilters(\DOMElement $filters): array
    {
        $values = [];
        foreach ($this->childElements($filters, 'filter') as $filter) {
            $values[] = $filter->getAttribute('val');
        }

        $dateGroups = [];
        foreach ($this->childElements($filters, 'dateGroupItem') as $dateGroupItem) {
            $dateGroups[] = [
                'year' => $this->integerAttribute($dateGroupItem, 'year'),
                'month' => $this->integerAttribute($dateGroupItem, 'month'),
                'day' => $this->integerAttribute($dateGroupItem, 'day'),
                'hour' => $this->integerAttribute($dateGroupItem, 'hour'),
                'minute' => $this->integerAttribute($dateGroupItem, 'minute'),
                'second' => $this->integerAttribute($dateGroupItem, 'second'),
                'dateTimeGrouping' => trim($dateGroupItem->getAttribute('dateTimeGrouping')) !== '' ? trim($dateGroupItem->getAttribute('dateTimeGrouping')) : null,
            ];
        }

        return [
            'blank' => $this->booleanAttribute($filters, 'blank'),
            'calendarType' => trim($filters->getAttribute('calendarType')) !== '' ? trim($filters->getAttribute('calendarType')) : null,
            'values' => $values,
            'dateGroups' => $dateGroups,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function parseCustomFilters(\DOMElement $customFilters): array
    {
        $filters = [];
        foreach ($this->childElements($customFilters, 'customFilter') as $customFilter) {
            $filters[] = [
                'operator' => trim($customFilter->getAttribute('operator')) !== '' ? trim($customFilter->getAttribute('operator')) : null,
                'value' => $customFilter->hasAttribute('val') ? $customFilter->getAttribute('val') : null,
            ];
        }

        return [
            'and' => $this->booleanAttribute($customFilters, 'and'),
            'filters' => $filters,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function parseDynamicFilter(\DOMElement $dynamicFilter): array
    {
        return [
            'type' => trim($dynamicFilter->getAttribute('type')) !== '' ? trim($dynamicFilter->getAttribute('type')) : null,
            'value' => $this->numericAttribute($dynamicFilter, 'val'),
            'maxValue' => $this->numericAttribute($dynamicFilter, 'maxVal'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function parseTop10Filter(\DOMElement $top10): array
    {
        return [
            'top' => $this->booleanAttribute($top10, 'top'),
            'percent' => $this->booleanAttribute($top10, 'percent'),
            'value' => $this->numericAttribute($top10, 'val'),
            'filterValue' => $this->numericAttribute($top10, 'filterVal'),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function parseSortState(?\DOMElement $sortState): ?array
    {
        if (!$sortState instanceof \DOMElement) {
            return null;
        }

        $conditions = [];
        foreach ($this->childElements($sortState, 'sortCondition') as $condition) {
            $conditions[] = [
                'ref' => trim($condition->getAttribute('ref')) !== '' ? trim($condition->getAttribute('ref')) : null,
                'descending' => $this->booleanAttribute($condition, 'descending'),
                'sortBy' => trim($condition->getAttribute('sortBy')) !== '' ? trim($condition->getAttribute('sortBy')) : null,
                'customList' => trim($condition->getAttribute('customList')) !== '' ? trim($condition->getAttribute('customList')) : null,
                'dxfId' => $this->integerAttribute($condition, 'dxfId'),
            ];
        }

        return [
            'ref' => trim($sortState->getAttribute('ref')) !== '' ? trim($sortState->getAttribute('ref')) : null,
            'caseSensitive' => $this->booleanAttribute($sortState, 'caseSensitive'),
            'columnSort' => $this->booleanAttribute($sortState, 'columnSort'),
            'conditionCount' => count($conditions),
            'conditions' => $conditions,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function parseTableColumns(\DOMElement $tableColumns): array
    {
        $columns = [];
        foreach ($this->childElements($tableColumns, 'tableColumn') as $tableColumn) {
            $columns[] = [
                'id' => $this->integerAttribute($tableColumn, 'id'),
                'name' => $tableColumn->hasAttribute('name') ? $tableColumn->getAttribute('name') : null,
                'uniqueName' => $tableColumn->hasAttribute('uniqueName') ? $tableColumn->getAttribute('uniqueName') : null,
                'totalsRowFunction' => trim($tableColumn->getAttribute('totalsRowFunction')) !== '' ? trim($tableColumn->getAttribute('totalsRowFunction')) : null,
                'totalsRowLabel' => $tableColumn->hasAttribute('totalsRowLabel') ? $tableColumn->getAttribute('totalsRowLabel') : null,
                'queryTableFieldId' => $this->integerAttribute($tableColumn, 'queryTableFieldId'),
                'dataDxfId' => $this->integerAttribute($tableColumn, 'dataDxfId'),
                'headerRowDxfId' => $this->integerAttribute($tableColumn, 'headerRowDxfId'),
                'totalsRowDxfId' => $this->integerAttribute($tableColumn, 'totalsRowDxfId'),
            ];
        }

        return $columns;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function parseTableStyleInfo(?\DOMElement $tableStyleInfo): ?array
    {
        if (!$tableStyleInfo instanceof \DOMElement) {
            return null;
        }

        return [
            'name' => $tableStyleInfo->hasAttribute('name') ? $tableStyleInfo->getAttribute('name') : null,
            'showFirstColumn' => $this->booleanAttribute($tableStyleInfo, 'showFirstColumn'),
            'showLastColumn' => $this->booleanAttribute($tableStyleInfo, 'showLastColumn'),
            'showRowStripes' => $this->booleanAttribute($tableStyleInfo, 'showRowStripes'),
            'showColumnStripes' => $this->booleanAttribute($tableStyleInfo, 'showColumnStripes'),
        ];
    }

    private function cachedValueTypeForCell(\DOMElement $cellElement): string
    {
        $valueElement = $this->firstChildElement($cellElement, 'v');
        if (!$valueElement instanceof \DOMElement || trim($valueElement->textContent) === '') {
            return 'missing';
        }

        $cellType = trim($cellElement->getAttribute('t'));

        return match ($cellType) {
            's' => 'shared-string',
            'inlineStr' => 'inline-string',
            'b' => 'boolean',
            'e' => 'error',
            'str' => 'formula-string',
            default => is_numeric(trim($valueElement->textContent)) ? 'number' : 'text',
        };
    }

    /**
     * @return array<string, array{url:string, title:string}>
     */
    private function parseHyperlinks(\DOMElement $worksheet, OpcRelationships $relationships): array
    {
        $hyperlinksElement = $this->firstChildElement($worksheet, 'hyperlinks');
        if (!$hyperlinksElement instanceof \DOMElement) {
            return [];
        }

        $hyperlinks = [];
        foreach ($this->childElements($hyperlinksElement, 'hyperlink') as $hyperlinkElement) {
            $ref = trim($hyperlinkElement->getAttribute('ref'));
            $refs = [];
            $cellReference = $this->parseCellReference($ref);
            if ($cellReference !== null) {
                $refs[] = $ref;
            } else {
                $range = $this->parseCellRange($ref);
                if ($range !== null) {
                    for ($row = $range['firstRow']; $row <= $range['lastRow']; $row++) {
                        for ($column = $range['firstColumn']; $column <= $range['lastColumn']; $column++) {
                            $refs[] = $this->cellReferenceFromCoordinates($row, $column);
                        }
                    }
                }
            }
            if ($refs === []) {
                continue;
            }

            $url = '';
            $relationshipId = $this->relationshipId($hyperlinkElement);
            if ($relationshipId !== '') {
                $relationship = $relationships->byId($relationshipId);
                if ($relationship instanceof OpcRelationship) {
                    if ($relationship->isExternal()) {
                        $preflight = $relationship->externalTargetPreflight();
                        if ($preflight['allowed']) {
                            $url = $relationship->target;
                        }
                    } else {
                        $url = ltrim(OpcPackagePath::stripQueryAndFragment($relationships->resolveTarget($relationship)), '/');
                    }
                }
            }

            $location = trim($hyperlinkElement->getAttribute('location'));
            if ($url === '' && $location !== '') {
                $url = '#' . $location;
            }
            if ($url === '') {
                continue;
            }

            foreach ($refs as $cellRef) {
                $hyperlinks[$cellRef] = [
                    'url' => $url,
                    'title' => trim($hyperlinkElement->getAttribute('tooltip')),
                ];
            }
        }

        return $hyperlinks;
    }

    /**
     * @return list<array{firstRow:int, firstColumn:int, lastRow:int, lastColumn:int}>
     */
    private function parseMergeRegions(\DOMElement $worksheet): array
    {
        $mergeCellsElement = $this->firstChildElement($worksheet, 'mergeCells');
        if (!$mergeCellsElement instanceof \DOMElement) {
            return [];
        }

        $regions = [];
        foreach ($this->childElements($mergeCellsElement, 'mergeCell') as $mergeCellElement) {
            $range = $this->parseCellRange(trim($mergeCellElement->getAttribute('ref')));
            if ($range === null) {
                continue;
            }
            if ($range['firstRow'] === $range['lastRow'] && $range['firstColumn'] === $range['lastColumn']) {
                continue;
            }
            $regions[] = $range;
        }

        return $regions;
    }

    /**
     * @param array<string, array<string, mixed>> $cells
     * @param list<array{firstRow:int, firstColumn:int, lastRow:int, lastColumn:int}> $mergeRegions
     */
    private function applyMergeRegions(array &$cells, array $mergeRegions): void
    {
        foreach ($mergeRegions as $region) {
            $topLeftKey = $region['firstRow'] . ':' . $region['firstColumn'];
            if (!isset($cells[$topLeftKey])) {
                continue;
            }

            $cells[$topLeftKey]['colspan'] = $region['lastColumn'] - $region['firstColumn'] + 1;
            $cells[$topLeftKey]['rowspan'] = $region['lastRow'] - $region['firstRow'] + 1;

            for ($row = $region['firstRow']; $row <= $region['lastRow']; $row++) {
                for ($column = $region['firstColumn']; $column <= $region['lastColumn']; $column++) {
                    if ($row === $region['firstRow'] && $column === $region['firstColumn']) {
                        continue;
                    }

                    $cells[$row . ':' . $column] = [
                        'row' => $row,
                        'column' => $column,
                        'ref' => $this->cellReferenceFromCoordinates($row, $column),
                        'valueType' => 'empty',
                        'text' => '',
                        'bold' => false,
                        'italic' => false,
                        'empty' => true,
                        'url' => '',
                        'title' => '',
                        'colspan' => 1,
                        'rowspan' => 1,
                        'covered' => true,
                    ];
                }
            }
        }
    }

    /**
     * @return array{firstRow:int, firstColumn:int, lastRow:int, lastColumn:int}|null
     */
    private function parseCellRange(string $range): ?array
    {
        if (preg_match('/^([A-Za-z]+[1-9][0-9]*):([A-Za-z]+[1-9][0-9]*)$/', $range, $matches) !== 1) {
            return null;
        }

        $first = $this->parseCellReference($matches[1]);
        $last = $this->parseCellReference($matches[2]);
        if ($first === null || $last === null) {
            return null;
        }

        return [
            'firstRow' => min($first['row'], $last['row']),
            'firstColumn' => min($first['column'], $last['column']),
            'lastRow' => max($first['row'], $last['row']),
            'lastColumn' => max($first['column'], $last['column']),
        ];
    }

    private function cellReferenceFromCoordinates(int $row, int $column): string
    {
        $letters = '';
        while ($column > 0) {
            $column--;
            $letters = chr(ord('A') + ($column % 26)) . $letters;
            $column = intdiv($column, 26);
        }

        return $letters . $row;
    }

    /**
     * @param list<array<string, mixed>|null> $row
     */
    private function isEmptyRow(array $row): bool
    {
        foreach ($row as $cell) {
            if ($cell === null || ($cell['empty'] ?? false) === true) {
                continue;
            }
            if (trim((string) ($cell['text'] ?? '')) !== '') {
                return false;
            }
        }

        return true;
    }

    private function relationshipId(\DOMElement $element): string
    {
        $id = $element->getAttributeNS(self::RELATIONSHIP_NAMESPACE, 'id');
        if ($id !== '') {
            return $id;
        }
        if ($element->hasAttribute('r:id')) {
            return $element->getAttribute('r:id');
        }

        foreach ($element->attributes ?? [] as $attribute) {
            if ($attribute instanceof \DOMAttr && $attribute->localName === 'id') {
                return $attribute->value;
            }
        }

        return '';
    }

    private function booleanAttribute(\DOMElement $element, string $attribute): ?bool
    {
        if (!$element->hasAttribute($attribute)) {
            return null;
        }

        return in_array(strtolower(trim($element->getAttribute($attribute))), ['1', 'true', 'on'], true);
    }

    private function integerAttribute(\DOMElement $element, string $attribute): ?int
    {
        $value = trim($element->getAttribute($attribute));
        if (preg_match('/^-?\d+$/', $value) !== 1) {
            return null;
        }

        return (int) $value;
    }

    private function numericAttribute(\DOMElement $element, string $attribute): int|float|null
    {
        $value = trim($element->getAttribute($attribute));
        if ($value === '' || !is_numeric($value)) {
            return null;
        }

        return str_contains($value, '.') || stripos($value, 'e') !== false ? (float) $value : (int) $value;
    }

    /**
     * @param list<string> $ranges
     */
    private function appendAutoFilterRange(array &$ranges, string $range): void
    {
        $range = trim($range);
        if ($range === '' || !($this->parseCellRange($range) !== null || $this->parseCellReference($range) !== null)) {
            return;
        }

        if (!in_array($range, $ranges, true)) {
            $ranges[] = $range;
        }
    }

    /**
     * @return list<\DOMElement>
     */
    private function childElements(\DOMElement $parent, string $localName): array
    {
        $children = [];
        foreach ($parent->childNodes as $child) {
            if ($child instanceof \DOMElement && $child->localName === $localName) {
                $children[] = $child;
            }
        }

        return $children;
    }

    /**
     * @return list<\DOMElement>
     */
    private function descendantElements(\DOMElement $parent, string $localName): array
    {
        $children = [];
        foreach ($parent->getElementsByTagName('*') as $child) {
            if ($child instanceof \DOMElement && $child->localName === $localName) {
                $children[] = $child;
            }
        }

        return $children;
    }

    private function firstChildElement(\DOMElement $parent, string $localName): ?\DOMElement
    {
        foreach ($parent->childNodes as $child) {
            if ($child instanceof \DOMElement && $child->localName === $localName) {
                return $child;
            }
        }

        return null;
    }

    private function firstDescendantElement(\DOMElement $parent, string $localName): ?\DOMElement
    {
        foreach ($parent->getElementsByTagName('*') as $child) {
            if ($child instanceof \DOMElement && $child->localName === $localName) {
                return $child;
            }
        }

        return null;
    }

    private function firstChildIntegerText(\DOMElement $parent, string $localName): ?int
    {
        $child = $this->firstChildElement($parent, $localName);
        if (!$child instanceof \DOMElement || preg_match('/^-?\d+$/', trim($child->textContent)) !== 1) {
            return null;
        }

        return (int) trim($child->textContent);
    }

    private function allDescendantText(\DOMElement $element): string
    {
        $texts = [];
        foreach ($element->getElementsByTagName('*') as $child) {
            if ($child instanceof \DOMElement && $child->localName === 't') {
                $text = $child->textContent;
                if ($text !== '') {
                    $texts[] = $text;
                }
            }
        }

        return implode('', $texts);
    }
}
