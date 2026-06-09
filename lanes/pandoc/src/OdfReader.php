<?php

declare(strict_types=1);

namespace PortLibs\Pandoc;

final class OdfReader
{
    public const MIMETYPE = 'application/vnd.oasis.opendocument.text';
    private const OFFICE_NS = 'urn:oasis:names:tc:opendocument:xmlns:office:1.0';
    private const TEXT_NS = 'urn:oasis:names:tc:opendocument:xmlns:text:1.0';
    private const STYLE_NS = 'urn:oasis:names:tc:opendocument:xmlns:style:1.0';
    private const TABLE_NS = 'urn:oasis:names:tc:opendocument:xmlns:table:1.0';
    private const DRAW_NS = 'urn:oasis:names:tc:opendocument:xmlns:drawing:1.0';
    private const FORM_NS = 'urn:oasis:names:tc:opendocument:xmlns:form:1.0';
    private const SVG_NS = 'urn:oasis:names:tc:opendocument:xmlns:svg-compatible:1.0';
    private const XLINK_NS = 'http://www.w3.org/1999/xlink';
    private const SCRIPT_NS = 'urn:oasis:names:tc:opendocument:xmlns:script:1.0';
    private const MANIFEST_NS = 'urn:oasis:names:tc:opendocument:xmlns:manifest:1.0';
    private const CONFIG_NS = 'urn:oasis:names:tc:opendocument:xmlns:config:1.0';
    private const DC_NS = 'http://purl.org/dc/elements/1.1/';
    private const META_NS = 'urn:oasis:names:tc:opendocument:xmlns:meta:1.0';
    private const FO_NS = 'urn:oasis:names:tc:opendocument:xmlns:xsl-fo-compatible:1.0';
    private const MATH_NS = 'http://www.w3.org/1998/Math/MathML';
    private const CHART_NS = 'urn:oasis:names:tc:opendocument:xmlns:chart:1.0';
    private const XML_NS = 'http://www.w3.org/XML/1998/namespace';

    /** @var array<string, array<string, mixed>> */
    private array $trackedChanges = [];

    /** @var array<string, array<string, mixed>> */
    private array $manifestByPart = [];

    private string $manifestVersion = '';

    /** @var array<string, array<string, mixed>> */
    private array $formControlsById = [];

    /** @var array<string, mixed> */
    private array $contentDeclarations = [];

    /** @var array<int, int> */
    private array $listContinuationStartCounters = [];

    /** @var array<string, array<int, int>> */
    private array $listContinuationStartCountersById = [];

    /** @var list<string> */
    private array $currentListStyleNames = [];

    private int $currentListLevel = 0;

    /** @var array<string, int> */
    private array $headingAnchorUses = [];

    /** @var array<string, array<string, mixed>> */
    private array $variableFieldValuesByName = [];

    /** @var array<string, mixed> */
    private array $packageMetadata = [];

    /** @var array<string, mixed> */
    private array $packageSettings = [];

    /**
     * @return array{
     *     document:AstNode,
     *     metadata:array<string, mixed>,
     *     manifest:list<array<string, mixed>>,
     *     styles:array<string, mixed>,
     *     fontFaces:array<string, mixed>,
     *     listStyles:array<string, mixed>,
     *     tableTemplates:array<string, mixed>,
     *     pageLayouts:array<string, mixed>,
     *     masterPages:array<string, mixed>,
     *     settings:array<string, mixed>,
     *     contentDeclarations:array<string, mixed>,
     *     media:list<array<string, mixed>>,
     *     trackedChanges:list<array<string, mixed>>,
     *     importReport:array<string, mixed>
     * }
     */
    public function readPackage(ZipPackage $package): array
    {
        $this->assertOdtMimetype($package);

        $manifest = $this->readManifest($package);
        $this->manifestByPart = $this->manifestByPart($manifest);
        $styleCatalog = $this->readStyles($package);
        $metadata = $this->readMeta($package);
        $settings = $this->readSettings($package);
        $content = $this->readContent($package, $styleCatalog, $metadata, $settings);
        $contentStats = $this->contentNodeStats($content['blocks']);
        $styleCatalog = $content['styleCatalog'];
        $media = $this->mediaReport($package, $manifest);
        $encryptedItems = $this->encryptedManifestItems($manifest);

        $document = new AstNode('document', [
            'source' => 'odt',
            'metadata' => $metadata,
            'title' => (string) ($metadata['title'] ?? ''),
            'manifest' => [
                'mimetype' => self::MIMETYPE,
                'version' => $this->manifestVersion === '' ? null : $this->manifestVersion,
                'items' => $manifest,
            ],
            'styles' => [
                'count' => count($styleCatalog['styles']),
                'items' => array_values($styleCatalog['styles']),
            ],
            'fontFaces' => [
                'count' => count($styleCatalog['fontFaces']),
                'items' => array_values($styleCatalog['fontFaces']),
            ],
            'listStyles' => [
                'count' => count($styleCatalog['listStyles']),
                'items' => array_values($styleCatalog['listStyles']),
            ],
            'tableTemplates' => [
                'count' => count($styleCatalog['tableTemplates']),
                'items' => array_values($styleCatalog['tableTemplates']),
            ],
            'pageLayouts' => [
                'count' => count($styleCatalog['pageLayouts']),
                'items' => array_values($styleCatalog['pageLayouts']),
            ],
            'masterPages' => [
                'count' => count($styleCatalog['masterPages']),
                'items' => array_values($styleCatalog['masterPages']),
            ],
            'settings' => $settings,
            'contentDeclarations' => $content['contentDeclarations'],
            'trackedChanges' => [
                'count' => count($content['trackedChanges']),
                'items' => $content['trackedChanges'],
            ],
        ], $content['blocks']);

        return [
            'document' => $document,
            'metadata' => $metadata,
            'manifest' => $manifest,
            'styles' => $styleCatalog['styles'],
            'fontFaces' => $styleCatalog['fontFaces'],
            'listStyles' => $styleCatalog['listStyles'],
            'tableTemplates' => $styleCatalog['tableTemplates'],
            'pageLayouts' => $styleCatalog['pageLayouts'],
            'masterPages' => $styleCatalog['masterPages'],
            'settings' => $settings,
            'contentDeclarations' => $content['contentDeclarations'],
            'media' => $media,
            'trackedChanges' => $content['trackedChanges'],
            'importReport' => [
                'mimetype' => self::MIMETYPE,
                'manifest' => [
                    'count' => count($manifest),
                    'version' => $this->manifestVersion === '' ? null : $this->manifestVersion,
                    'items' => $manifest,
                    'missingItems' => array_values(array_filter(
                        $manifest,
                        static fn (array $item): bool => ($item['exists'] ?? false) !== true,
                    )),
                    'encryptedCount' => count($encryptedItems),
                    'encryptedItems' => $encryptedItems,
                ],
                'metadata' => $metadata,
                'styles' => [
                    'count' => count($styleCatalog['styles']),
                    'items' => array_values($styleCatalog['styles']),
                    'pageLayoutCount' => count($styleCatalog['pageLayouts']),
                    'masterPageCount' => count($styleCatalog['masterPages']),
                    'tableTemplateCount' => count($styleCatalog['tableTemplates']),
                    'fontFaceCount' => count($styleCatalog['fontFaces']),
                    'fontFaces' => array_values($styleCatalog['fontFaces']),
                    'styleMapCount' => $this->styleMapCount($styleCatalog['styles']),
                ],
                'listStyles' => [
                    'count' => count($styleCatalog['listStyles']),
                    'items' => array_values($styleCatalog['listStyles']),
                ],
                'tableTemplates' => [
                    'count' => count($styleCatalog['tableTemplates']),
                    'items' => array_values($styleCatalog['tableTemplates']),
                ],
                'pageLayouts' => [
                    'count' => count($styleCatalog['pageLayouts']),
                    'items' => array_values($styleCatalog['pageLayouts']),
                ],
                'masterPages' => [
                    'count' => count($styleCatalog['masterPages']),
                    'items' => array_values($styleCatalog['masterPages']),
                ],
                'settings' => $settings,
                'contentDeclarations' => $content['contentDeclarations'],
                'media' => [
                    'count' => count($media),
                    'items' => $media,
                ],
                'trackedChanges' => [
                    'count' => count($content['trackedChanges']),
                    'items' => $content['trackedChanges'],
                ],
                'encryption' => [
                    'count' => count($encryptedItems),
                    'encryptedParts' => array_values(array_filter(
                        array_map(static fn (array $item): ?string => is_string($item['part'] ?? null) ? $item['part'] : null, $encryptedItems),
                        static fn (?string $part): bool => $part !== null && $part !== ''
                    )),
                    'items' => $encryptedItems,
                ],
                'content' => [
                    'blockCount' => count($content['blocks']),
                    'automaticStyleCount' => $content['automaticStyleCount'],
                    'blockquoteCount' => $contentStats['blockquoteCount'],
                    'noteCount' => $contentStats['noteCount'],
                    'bookmarkCount' => $contentStats['bookmarkCount'],
                    'bookmarkReferenceCount' => $contentStats['bookmarkReferenceCount'],
                    'referenceMarkCount' => $contentStats['referenceMarkCount'],
                    'referenceReferenceCount' => $contentStats['referenceReferenceCount'],
                    'indexMarkCount' => $contentStats['indexMarkCount'],
                    'sequenceCount' => $contentStats['sequenceCount'],
                    'fieldCount' => $contentStats['fieldCount'],
                    'metaSpanCount' => $contentStats['metaSpanCount'],
                    'placeholderCount' => $contentStats['placeholderCount'],
                    'rubyCount' => $contentStats['rubyCount'],
                    'softPageBreakCount' => $contentStats['softPageBreakCount'],
                    'citationCount' => $contentStats['citationCount'],
                    'eventListenerCount' => $contentStats['eventListenerCount'],
                    'annotationRangeCount' => $contentStats['annotationRangeCount'],
                    'trackedChangeCount' => $contentStats['trackedChangeCount'],
                    'mathCount' => $contentStats['mathCount'],
                    'embeddedObjectCount' => $contentStats['embeddedObjectCount'],
                    'missingEmbeddedObjectCount' => $contentStats['missingEmbeddedObjectCount'],
                    'chartObjectCount' => $contentStats['chartObjectCount'],
                    'chartMetadataCount' => $contentStats['chartMetadataCount'],
                    'chartTitleCount' => $contentStats['chartTitleCount'],
                    'chartAxisCount' => $contentStats['chartAxisCount'],
                    'chartLegendCount' => $contentStats['chartLegendCount'],
                    'formControlCount' => $contentStats['formControlCount'],
                    'missingFormControlCount' => $contentStats['missingFormControlCount'],
                    'formControlOptionCount' => $contentStats['formControlOptionCount'],
                    'selectedFormControlOptionCount' => $contentStats['selectedFormControlOptionCount'],
                    'sectionCount' => $contentStats['sectionCount'],
                    'linkedSectionCount' => $contentStats['linkedSectionCount'],
                    'protectedSectionCount' => $contentStats['protectedSectionCount'],
                    'conditionalSectionCount' => $contentStats['conditionalSectionCount'],
                    'hiddenSectionCount' => $contentStats['hiddenSectionCount'],
                    'tableOfContentsCount' => $contentStats['tableOfContentsCount'],
                    'generatedIndexCount' => $contentStats['generatedIndexCount'],
                    'tableCaptionCount' => $contentStats['tableCaptionCount'],
                    'preformattedCodeBlockCount' => $contentStats['preformattedCodeBlockCount'],
                    'continuedListCount' => $contentStats['continuedListCount'],
                    'listHeaderCount' => $contentStats['listHeaderCount'],
                    'imageListStyleCount' => $contentStats['imageListStyleCount'],
                    'listTextPropertyCount' => $contentStats['listTextPropertyCount'],
                    'tableTemplateReferenceCount' => $contentStats['tableTemplateReferenceCount'],
                    'tablePrintRangeCount' => $contentStats['tablePrintRangeCount'],
                    'tableScenarioCount' => $contentStats['tableScenarioCount'],
                    'activeTableScenarioCount' => $contentStats['activeTableScenarioCount'],
                    'tableColumnDefinitionCount' => $contentStats['tableColumnDefinitionCount'],
                    'hiddenTableColumnCount' => $contentStats['hiddenTableColumnCount'],
                    'tableRowDefinitionCount' => $contentStats['tableRowDefinitionCount'],
                    'hiddenTableRowCount' => $contentStats['hiddenTableRowCount'],
                    'repeatedTableRowCount' => $contentStats['repeatedTableRowCount'],
                    'truncatedTableRowRepeatCount' => $contentStats['truncatedTableRowRepeatCount'],
                    'tableCoveredCellCount' => $contentStats['tableCoveredCellCount'],
                    'tableCoveredCellMetadataCount' => $contentStats['tableCoveredCellMetadataCount'],
                    'tableCellAnnotationCount' => $contentStats['tableCellAnnotationCount'],
                    'tableStyledCellCount' => $contentStats['tableStyledCellCount'],
                    'tableProtectedCellCount' => $contentStats['tableProtectedCellCount'],
                    'tablePrintHiddenCellCount' => $contentStats['tablePrintHiddenCellCount'],
                    'frameCaptionCount' => $contentStats['frameCaptionCount'],
                    'noteConfigurationCount' => (int) ($content['contentDeclarations']['noteConfigurationCount'] ?? 0),
                    'noteConfigurationSeparatorCount' => (int) ($content['contentDeclarations']['noteConfigurationSeparatorCount'] ?? 0),
                    'lineNumberingConfigurationCount' => (int) ($content['contentDeclarations']['lineNumberingConfigurationCount'] ?? 0),
                    'lineNumberingSeparatorCount' => (int) ($content['contentDeclarations']['lineNumberingSeparatorCount'] ?? 0),
                    'contentValidationCount' => (int) ($content['contentDeclarations']['contentValidationCount'] ?? 0),
                    'contentValidationConditionCount' => (int) ($content['contentDeclarations']['contentValidationConditionCount'] ?? 0),
                    'contentValidationMessageCount' => (int) ($content['contentDeclarations']['contentValidationMessageCount'] ?? 0),
                    'labelRangeCount' => (int) ($content['contentDeclarations']['labelRangeCount'] ?? 0),
                    'calculationSettingCount' => (int) ($content['contentDeclarations']['calculationSettingCount'] ?? 0),
                    'namedExpressionCount' => (int) ($content['contentDeclarations']['namedExpressionCount'] ?? 0),
                    'namedRangeCount' => (int) ($content['contentDeclarations']['namedRangeCount'] ?? 0),
                    'namedFormulaExpressionCount' => (int) ($content['contentDeclarations']['namedFormulaExpressionCount'] ?? 0),
                    'databaseRangeCount' => (int) ($content['contentDeclarations']['databaseRangeCount'] ?? 0),
                    'databaseSubtotalRuleCount' => (int) ($content['contentDeclarations']['databaseSubtotalRuleCount'] ?? 0),
                    'databaseSubtotalFieldCount' => (int) ($content['contentDeclarations']['databaseSubtotalFieldCount'] ?? 0),
                    'dataPilotTableCount' => (int) ($content['contentDeclarations']['dataPilotTableCount'] ?? 0),
                    'dataPilotFieldCount' => (int) ($content['contentDeclarations']['dataPilotFieldCount'] ?? 0),
                    'dataPilotSubtotalCount' => (int) ($content['contentDeclarations']['dataPilotSubtotalCount'] ?? 0),
                    'dataPilotMemberCount' => (int) ($content['contentDeclarations']['dataPilotMemberCount'] ?? 0),
                    'tableTrackedChangeCount' => (int) ($content['contentDeclarations']['tableTrackedChangeCount'] ?? 0),
                    'ddeConnectionDeclarationCount' => (int) ($content['contentDeclarations']['ddeConnectionDeclarationCount'] ?? 0),
                    'drawLayerCount' => (int) ($content['contentDeclarations']['drawLayerCount'] ?? 0),
                    'hiddenDrawLayerCount' => (int) ($content['contentDeclarations']['hiddenDrawLayerCount'] ?? 0),
                    'protectedDrawLayerCount' => (int) ($content['contentDeclarations']['protectedDrawLayerCount'] ?? 0),
                    'frameLayerReferenceCount' => $contentStats['frameLayerReferenceCount'],
                ],
            ],
        ];
    }

    public function readDocument(ZipPackage $package): AstNode
    {
        return $this->readPackage($package)['document'];
    }

    private function assertOdtMimetype(ZipPackage $package): void
    {
        $package->assertStoredFirstEntry('mimetype', self::MIMETYPE, 'ODT mimetype entry');
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function readManifest(ZipPackage $package): array
    {
        if (!$package->has('META-INF/manifest.xml')) {
            throw new \RuntimeException('ODT package is missing META-INF/manifest.xml');
        }

        $dom = self::loadXml($package->read('META-INF/manifest.xml'), 'ODT manifest XML');
        $root = $dom->documentElement;
        if (!$root instanceof \DOMElement || $root->localName !== 'manifest' || $root->namespaceURI !== self::MANIFEST_NS) {
            throw new \InvalidArgumentException('ODT manifest XML must use manifest:manifest as its root element');
        }

        $this->manifestVersion = self::attr($root, self::MANIFEST_NS, 'version');
        $items = [];
        foreach (self::childElements($root, 'file-entry', self::MANIFEST_NS) as $entryElement) {
            $fullPath = self::attr($entryElement, self::MANIFEST_NS, 'full-path');
            if ($fullPath === '') {
                throw new \RuntimeException('ODT manifest file-entry is missing manifest:full-path');
            }

            $mediaType = self::attr($entryElement, self::MANIFEST_NS, 'media-type');
            $version = self::attr($entryElement, self::MANIFEST_NS, 'version');
            $preferredViewMode = self::attr($entryElement, self::MANIFEST_NS, 'preferred-view-mode');
            $declaredSize = self::nullableInt(self::attr($entryElement, self::MANIFEST_NS, 'size'));
            $encryptionElement = self::firstChildElement($entryElement, 'encryption-data', self::MANIFEST_NS);
            $encrypted = $encryptionElement instanceof \DOMElement;
            $part = $fullPath === '/' ? null : $this->manifestPackagePart($fullPath);
            $exists = $part === null ? true : $package->has($part);
            $zipEntry = $exists && $part !== null ? $package->entry($part) : null;

            $items[] = [
                'fullPath' => $fullPath,
                'part' => $part,
                'mediaType' => $mediaType,
                'version' => $version === '' ? null : $version,
                'preferredViewMode' => $preferredViewMode === '' ? null : $preferredViewMode,
                'exists' => $exists,
                'byteLength' => $zipEntry instanceof ZipPackageEntry ? $zipEntry->uncompressedSize : null,
                'crc32' => $zipEntry instanceof ZipPackageEntry ? $zipEntry->crc32Hex() : null,
                'declaredSize' => $declaredSize,
                'encrypted' => $encrypted,
                'canExposeBytes' => !$encrypted,
                'encryption' => $encrypted ? $this->encryptionData($encryptionElement) : null,
            ];
        }

        if ($items === []) {
            throw new \RuntimeException('ODT manifest does not contain file entries');
        }

        return $items;
    }

    /**
     * @return array<string, mixed>
     */
    private function encryptionData(\DOMElement $element): array
    {
        $data = self::withoutEmpty([
            'checksumType' => self::nullable(self::attr($element, self::MANIFEST_NS, 'checksum-type')),
            'checksum' => self::nullable(self::attr($element, self::MANIFEST_NS, 'checksum')),
        ]);

        $algorithm = self::firstChildElement($element, 'algorithm', self::MANIFEST_NS);
        if ($algorithm instanceof \DOMElement) {
            $initialisationVector = self::attr($algorithm, self::MANIFEST_NS, 'initialisation-vector');
            if ($initialisationVector === '') {
                $initialisationVector = self::attr($algorithm, self::MANIFEST_NS, 'initialization-vector');
            }
            $data['algorithm'] = self::withoutEmpty([
                'name' => self::nullable(self::attr($algorithm, self::MANIFEST_NS, 'algorithm-name')),
                'initialisationVector' => self::nullable($initialisationVector),
            ]);
        }

        $keyDerivation = self::firstChildElement($element, 'key-derivation', self::MANIFEST_NS);
        if ($keyDerivation instanceof \DOMElement) {
            $data['keyDerivation'] = self::withoutEmpty([
                'name' => self::nullable(self::attr($keyDerivation, self::MANIFEST_NS, 'key-derivation-name')),
                'iterationCount' => self::nullableInt(self::attr($keyDerivation, self::MANIFEST_NS, 'iteration-count')),
                'salt' => self::nullable(self::attr($keyDerivation, self::MANIFEST_NS, 'salt')),
            ]);
        }

        $startKeyGeneration = self::firstChildElement($element, 'start-key-generation', self::MANIFEST_NS);
        if ($startKeyGeneration instanceof \DOMElement) {
            $data['startKeyGeneration'] = self::withoutEmpty([
                'name' => self::nullable(self::attr($startKeyGeneration, self::MANIFEST_NS, 'start-key-generation-name')),
                'keySize' => self::nullableInt(self::attr($startKeyGeneration, self::MANIFEST_NS, 'key-size')),
            ]);
        }

        return self::withoutEmpty($data);
    }

    /**
     * @return array{styles:array<string, array<string, mixed>>, fontFaces:array<string, array<string, mixed>>, listStyles:array<string, array<string, mixed>>, tableTemplates:array<string, array<string, mixed>>, pageLayouts:array<string, array<string, mixed>>, masterPages:array<string, array<string, mixed>>}
     */
    private function readStyles(ZipPackage $package): array
    {
        $catalog = [
            'styles' => [],
            'fontFaces' => [],
            'listStyles' => [],
            'tableTemplates' => [],
            'pageLayouts' => [],
            'masterPages' => [],
        ];

        if (!$package->has('styles.xml')) {
            return $catalog;
        }

        $dom = self::loadXml($package->read('styles.xml'), 'ODT styles XML');
        $root = $dom->documentElement;
        if (!$root instanceof \DOMElement || $root->localName !== 'document-styles' || $root->namespaceURI !== self::OFFICE_NS) {
            throw new \InvalidArgumentException('ODT styles.xml must use office:document-styles as its root element');
        }

        $this->mergeStyleCollections($catalog, $this->styleCollectionsFromRoot($root));

        return $catalog;
    }

    /**
     * @param array{styles:array<string, array<string, mixed>>, fontFaces:array<string, array<string, mixed>>, listStyles:array<string, array<string, mixed>>, tableTemplates:array<string, array<string, mixed>>, pageLayouts:array<string, array<string, mixed>>, masterPages:array<string, array<string, mixed>>} $styleCatalog
     * @param array<string, mixed> $settings
     * @return array{blocks:list<AstNode>, styleCatalog:array{styles:array<string, array<string, mixed>>, fontFaces:array<string, array<string, mixed>>, listStyles:array<string, array<string, mixed>>, tableTemplates:array<string, array<string, mixed>>, pageLayouts:array<string, array<string, mixed>>, masterPages:array<string, array<string, mixed>>}, automaticStyleCount:int, trackedChanges:list<array<string, mixed>>, contentDeclarations:array<string, mixed>}
     */
    private function readContent(ZipPackage $package, array $styleCatalog, array $metadata, array $settings): array
    {
        if (!$package->has('content.xml')) {
            throw new \RuntimeException('ODT package is missing content.xml');
        }

        $dom = self::loadXml($package->read('content.xml'), 'ODT content XML');
        $root = $dom->documentElement;
        if (!$root instanceof \DOMElement || $root->localName !== 'document-content' || $root->namespaceURI !== self::OFFICE_NS) {
            throw new \InvalidArgumentException('ODT content.xml must use office:document-content as its root element');
        }

        $contentStyles = $this->styleCollectionsFromRoot($root, $styleCatalog['fontFaces']);
        $this->mergeStyleCollections($styleCatalog, $contentStyles);
        $body = self::firstChildElement($root, 'body', self::OFFICE_NS);
        $text = $body instanceof \DOMElement ? self::firstChildElement($body, 'text', self::OFFICE_NS) : null;
        if (!$text instanceof \DOMElement) {
            throw new \RuntimeException('ODT content.xml is missing office:body/office:text');
        }

        $this->trackedChanges = $this->trackedChangesFromText($text);
        $this->formControlsById = $this->formControlsFromText($text);
        $this->contentDeclarations = $this->contentDeclarationsFromText($text, $root);
        $this->listContinuationStartCounters = [];
        $this->listContinuationStartCountersById = [];
        $this->currentListStyleNames = [];
        $this->currentListLevel = 0;
        $this->headingAnchorUses = [];
        $this->variableFieldValuesByName = [];
        $this->packageMetadata = $metadata;
        $this->packageSettings = $settings;

        return [
            'blocks' => $this->blockNodes($text, $package, $styleCatalog),
            'styleCatalog' => $styleCatalog,
            'automaticStyleCount' => count($contentStyles['styles']) + count($contentStyles['listStyles']) + count($contentStyles['tableTemplates']) + count($contentStyles['pageLayouts']) + count($contentStyles['masterPages']),
            'trackedChanges' => array_values($this->trackedChanges),
            'contentDeclarations' => $this->contentDeclarations,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function readMeta(ZipPackage $package): array
    {
        if (!$package->has('meta.xml')) {
            return [];
        }

        $dom = self::loadXml($package->read('meta.xml'), 'ODT meta XML');
        $root = $dom->documentElement;
        if (!$root instanceof \DOMElement || $root->localName !== 'document-meta' || $root->namespaceURI !== self::OFFICE_NS) {
            throw new \InvalidArgumentException('ODT meta.xml must use office:document-meta as its root element');
        }

        $metaElement = self::firstChildElement($root, 'meta', self::OFFICE_NS);
        if (!$metaElement instanceof \DOMElement) {
            throw new \RuntimeException('ODT meta.xml is missing office:meta');
        }

        $metadata = [
            'keywords' => [],
            'userDefined' => [],
            'userDefinedDetails' => [],
        ];
        foreach (self::childElements($metaElement) as $child) {
            if ($child->namespaceURI === self::DC_NS) {
                $name = $child->localName;
                $text = self::normalizedText($child);
                if (in_array($name, ['title', 'creator', 'description', 'language', 'date', 'subject'], true)) {
                    $metadata[$name] = $text;
                } else {
                    $metadata['dc:' . $name] = $text;
                }
                continue;
            }

            if ($child->namespaceURI !== self::META_NS) {
                continue;
            }

            if ($child->localName === 'keyword') {
                $metadata['keywords'][] = self::normalizedText($child);
                continue;
            }
            if ($child->localName === 'initial-creator') {
                $metadata['initialCreator'] = self::normalizedText($child);
                continue;
            }
            if ($child->localName === 'creation-date') {
                $created = self::normalizedText($child);
                $metadata['created'] = $created;
                $creationTime = self::odfTimeDurationFromDateTime($created);
                if ($creationTime !== null) {
                    $metadata['creationTime'] = $creationTime;
                }
                continue;
            }
            if ($child->localName === 'editing-cycles') {
                $metadata['editingCycles'] = self::normalizedText($child);
                continue;
            }
            if (in_array($child->localName, ['generator', 'editing-duration', 'modification-date', 'modification-time', 'printed-by', 'print-date', 'print-time'], true)) {
                $text = self::normalizedText($child);
                if ($text !== '') {
                    $metadata[self::camelCase($child->localName)] = $text;
                }
                continue;
            }
            if ($child->localName === 'template') {
                $template = $this->metaTemplateMetadata($child);
                if ($template !== []) {
                    $metadata['template'] = $template;
                }
                continue;
            }
            if ($child->localName === 'auto-reload') {
                $autoReload = $this->metaAutoReloadMetadata($child);
                if ($autoReload !== []) {
                    $metadata['autoReload'] = $autoReload;
                }
                continue;
            }
            if ($child->localName === 'hyperlink-behaviour') {
                $hyperlinkBehaviour = $this->metaHyperlinkBehaviourMetadata($child);
                if ($hyperlinkBehaviour !== []) {
                    $metadata['hyperlinkBehaviour'] = $hyperlinkBehaviour;
                }
                continue;
            }
            if ($child->localName === 'document-statistic') {
                $metadata['statistics'] = $this->documentStatistics($child);
                continue;
            }
            if ($child->localName === 'user-defined') {
                $userDefined = $this->metaUserDefinedMetadata($child);
                $name = (string) ($userDefined['name'] ?? '');
                if ($name !== '') {
                    $metadata['userDefined'][$name] = (string) ($userDefined['displayValue'] ?? '');
                    if ($this->hasTypedUserDefinedMetadata($userDefined)) {
                        unset($userDefined['name']);
                        $metadata['userDefinedDetails'][$name] = $userDefined;
                    }
                }
            }
        }

        if ($metadata['keywords'] === []) {
            unset($metadata['keywords']);
        }
        if ($metadata['userDefined'] === []) {
            unset($metadata['userDefined']);
        }
        if ($metadata['userDefinedDetails'] === []) {
            unset($metadata['userDefinedDetails']);
        }

        return $metadata;
    }

    /**
     * @return array{count:int,itemCount:int,mapEntryCount:int,sets:list<array<string, mixed>>,setsByName:array<string, array<string, mixed>>}
     */
    private function readSettings(ZipPackage $package): array
    {
        $empty = [
            'count' => 0,
            'itemCount' => 0,
            'mapEntryCount' => 0,
            'sets' => [],
            'setsByName' => [],
        ];
        if (!$package->has('settings.xml')) {
            return $empty;
        }

        $dom = self::loadXml($package->read('settings.xml'), 'ODT settings.xml');
        $root = $dom->documentElement;
        if (!$root instanceof \DOMElement || $root->localName !== 'document-settings' || $root->namespaceURI !== self::OFFICE_NS) {
            throw new \InvalidArgumentException('ODT settings.xml must use office:document-settings as its root element');
        }

        $settingsElement = self::firstChildElement($root, 'settings', self::OFFICE_NS);
        if (!$settingsElement instanceof \DOMElement) {
            throw new \RuntimeException('ODT settings.xml is missing office:settings');
        }

        $sets = [];
        $setsByName = [];
        $itemCount = 0;
        $mapEntryCount = 0;
        foreach (self::childElements($settingsElement, 'config-item-set', self::CONFIG_NS) as $setElement) {
            $name = self::attr($setElement, self::CONFIG_NS, 'name');
            if ($name === '') {
                continue;
            }

            $set = $this->settingsContainerDefinition($setElement) + ['name' => $name];
            $sets[] = $set;
            $setsByName[$name] = $set;
            $itemCount += (int) ($set['itemCount'] ?? 0);
            $mapEntryCount += (int) ($set['mapEntryCount'] ?? 0);
        }

        return [
            'count' => count($sets),
            'itemCount' => $itemCount,
            'mapEntryCount' => $mapEntryCount,
            'sets' => $sets,
            'setsByName' => $setsByName,
        ];
    }

    /**
     * @return array{itemCount:int,mapEntryCount:int,items:list<array<string, mixed>>,itemsByName:array<string, array<string, mixed>>,maps:list<array<string, mixed>>,mapsByName:array<string, array<string, mixed>>}
     */
    private function settingsContainerDefinition(\DOMElement $container): array
    {
        $items = [];
        $itemsByName = [];
        $maps = [];
        $mapsByName = [];
        $itemCount = 0;
        $mapEntryCount = 0;

        foreach (self::childElements($container) as $child) {
            if ($this->isElement($child, self::CONFIG_NS, 'config-item')) {
                $item = $this->settingsConfigItemDefinition($child);
                if ($item === []) {
                    continue;
                }

                $items[] = $item;
                $name = (string) ($item['name'] ?? '');
                if ($name !== '') {
                    $itemsByName[$name] = $item;
                }
                $itemCount++;
                continue;
            }

            if ($this->isElement($child, self::CONFIG_NS, 'config-item-map-indexed')
                || $this->isElement($child, self::CONFIG_NS, 'config-item-map-named')
            ) {
                $map = $this->settingsConfigMapDefinition($child);
                if ($map === []) {
                    continue;
                }

                $maps[] = $map;
                $name = (string) ($map['name'] ?? '');
                if ($name !== '') {
                    $mapsByName[$name] = $map;
                }
                $itemCount += (int) ($map['itemCount'] ?? 0);
                $mapEntryCount += (int) ($map['mapEntryCount'] ?? 0);
            }
        }

        return [
            'itemCount' => $itemCount,
            'mapEntryCount' => $mapEntryCount,
            'items' => $items,
            'itemsByName' => $itemsByName,
            'maps' => $maps,
            'mapsByName' => $mapsByName,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function settingsConfigItemDefinition(\DOMElement $item): array
    {
        $name = self::attr($item, self::CONFIG_NS, 'name');
        if ($name === '') {
            return [];
        }

        $type = self::attr($item, self::CONFIG_NS, 'type');
        $value = self::normalizedText($item);
        $definition = self::withoutEmpty([
            'name' => $name,
            'type' => self::nullable($type),
            'value' => $value,
            'typedValue' => $this->settingsConfigTypedValue($value, $type),
        ]);

        return $definition;
    }

    private function settingsConfigTypedValue(string $value, string $type): mixed
    {
        $type = strtolower(trim($type));
        if (in_array($type, ['boolean', 'bool'], true)) {
            return self::nullableBool($value);
        }
        if (in_array($type, ['int', 'integer', 'long', 'short'], true)) {
            return preg_match('/^-?\d+$/', $value) === 1 ? (int) $value : $value;
        }
        if (in_array($type, ['float', 'double'], true)) {
            return is_numeric($value) ? (float) $value : $value;
        }

        return $value;
    }

    /**
     * @return array<string, mixed>
     */
    private function settingsConfigMapDefinition(\DOMElement $map): array
    {
        $name = self::attr($map, self::CONFIG_NS, 'name');
        if ($name === '') {
            return [];
        }

        $entries = [];
        $entriesByName = [];
        $itemCount = 0;
        $mapEntryCount = 0;
        foreach (self::childElements($map, 'config-item-map-entry', self::CONFIG_NS) as $entryElement) {
            $entry = $this->settingsContainerDefinition($entryElement);
            $entry['index'] = count($entries);
            $entryName = self::attr($entryElement, self::CONFIG_NS, 'name');
            if ($entryName !== '') {
                $entry['name'] = $entryName;
                $entriesByName[$entryName] = $entry;
            }

            $entries[] = $entry;
            $itemCount += (int) ($entry['itemCount'] ?? 0);
            $mapEntryCount += 1 + (int) ($entry['mapEntryCount'] ?? 0);
        }

        $definition = [
            'name' => $name,
            'type' => $map->localName === 'config-item-map-named' ? 'named' : 'indexed',
            'entryCount' => count($entries),
            'itemCount' => $itemCount,
            'mapEntryCount' => $mapEntryCount,
            'entries' => $entries,
        ];
        if ($entriesByName !== []) {
            $definition['entriesByName'] = $entriesByName;
        }

        return $definition;
    }

    /**
     * @return array<string, mixed>
     */
    private function metaUserDefinedMetadata(\DOMElement $element): array
    {
        $stringValue = $this->odfMetaTypedAttribute($element, 'string-value');
        $value = $this->odfMetaTypedAttribute($element, 'value');
        $dateValue = $this->odfMetaTypedAttribute($element, 'date-value');
        $timeValue = $this->odfMetaTypedAttribute($element, 'time-value');
        $booleanValue = self::nullableBool($this->odfMetaTypedAttribute($element, 'boolean-value'));
        $visibleText = self::normalizedText($element);

        $metadata = self::withoutEmpty([
            'name' => self::nullable(self::attr($element, self::META_NS, 'name')),
            'valueType' => self::nullable($this->odfMetaTypedAttribute($element, 'value-type')),
            'value' => self::nullable($value),
            'currency' => self::nullable($this->odfMetaTypedAttribute($element, 'currency')),
            'booleanValue' => $booleanValue,
            'stringValue' => self::nullable($stringValue),
            'dateValue' => self::nullable($dateValue),
            'timeValue' => self::nullable($timeValue),
        ]);
        $metadata['displayValue'] = $this->metaUserDefinedDisplayValue($visibleText, $metadata);

        return $metadata;
    }

    private function odfMetaTypedAttribute(\DOMElement $element, string $localName): string
    {
        $value = self::attr($element, self::META_NS, $localName);
        if ($value !== '') {
            return $value;
        }

        return self::attr($element, self::OFFICE_NS, $localName);
    }

    /**
     * @param array<string, mixed> $metadata
     */
    private function metaUserDefinedDisplayValue(string $visibleText, array $metadata): string
    {
        if ($visibleText !== '') {
            return $visibleText;
        }

        foreach (['stringValue', 'value', 'dateValue', 'timeValue', 'booleanValue'] as $name) {
            $value = $metadata[$name] ?? null;
            if (is_bool($value)) {
                return $value ? 'true' : 'false';
            }
            if (is_scalar($value) && (string) $value !== '') {
                return (string) $value;
            }
        }

        return '';
    }

    /**
     * @param array<string, mixed> $metadata
     */
    private function hasTypedUserDefinedMetadata(array $metadata): bool
    {
        foreach (['valueType', 'value', 'currency', 'booleanValue', 'stringValue', 'dateValue', 'timeValue'] as $name) {
            if (array_key_exists($name, $metadata)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array<string, string>
     */
    private function metaTemplateMetadata(\DOMElement $element): array
    {
        /** @var array<string, string> $metadata */
        $metadata = self::withoutEmpty([
            'href' => self::nullable(self::attr($element, self::XLINK_NS, 'href')),
            'type' => self::nullable(self::attr($element, self::XLINK_NS, 'type')),
            'title' => self::nullable(self::attr($element, self::XLINK_NS, 'title')),
            'date' => self::nullable(self::attr($element, self::META_NS, 'date')),
            'show' => self::nullable(self::attr($element, self::XLINK_NS, 'show')),
            'actuate' => self::nullable(self::attr($element, self::XLINK_NS, 'actuate')),
        ]);

        return $metadata;
    }

    /**
     * @return array<string, string>
     */
    private function metaAutoReloadMetadata(\DOMElement $element): array
    {
        /** @var array<string, string> $metadata */
        $metadata = self::withoutEmpty([
            'href' => self::nullable(self::attr($element, self::XLINK_NS, 'href')),
            'type' => self::nullable(self::attr($element, self::XLINK_NS, 'type')),
            'show' => self::nullable(self::attr($element, self::XLINK_NS, 'show')),
            'actuate' => self::nullable(self::attr($element, self::XLINK_NS, 'actuate')),
            'delay' => self::nullable(self::attr($element, self::META_NS, 'delay')),
        ]);

        return $metadata;
    }

    /**
     * @return array<string, string>
     */
    private function metaHyperlinkBehaviourMetadata(\DOMElement $element): array
    {
        /** @var array<string, string> $metadata */
        $metadata = self::withoutEmpty([
            'show' => self::nullable(self::attr($element, self::XLINK_NS, 'show')),
            'targetFrameName' => self::nullable(self::attr($element, self::OFFICE_NS, 'target-frame-name')),
        ]);

        return $metadata;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function trackedChangesFromText(\DOMElement $text): array
    {
        $trackedChanges = self::firstChildElement($text, 'tracked-changes', self::TEXT_NS);
        if (!$trackedChanges instanceof \DOMElement) {
            return [];
        }

        $changes = [];
        foreach (self::childElements($trackedChanges, 'changed-region', self::TEXT_NS) as $region) {
            $id = self::attr($region, self::TEXT_NS, 'id');
            if ($id === '') {
                continue;
            }

            $changeElement = $this->firstTrackedChangeElement($region);
            if (!$changeElement instanceof \DOMElement) {
                continue;
            }

            $changeInfo = self::firstChildElement($changeElement, 'change-info', self::OFFICE_NS);
            $comments = [];
            if ($changeInfo instanceof \DOMElement) {
                foreach (self::childElements($changeInfo, 'p', self::TEXT_NS) as $paragraph) {
                    $textContent = self::normalizedText($paragraph);
                    if ($textContent !== '') {
                        $comments[] = $textContent;
                    }
                }
            }

            $entry = [
                'id' => $id,
                'type' => $changeElement->localName,
                'creator' => $changeInfo instanceof \DOMElement ? $this->changeInfoText($changeInfo, 'creator') : '',
                'date' => $changeInfo instanceof \DOMElement ? $this->changeInfoText($changeInfo, 'date') : '',
                'comments' => $comments,
                'text' => $this->trackedChangeBodyText($changeElement),
            ];

            $changes[$id] = array_filter(
                $entry,
                static fn (mixed $value): bool => $value !== '' && $value !== []
            );
        }

        return $changes;
    }

    private function firstTrackedChangeElement(\DOMElement $region): ?\DOMElement
    {
        foreach (self::childElements($region) as $child) {
            if ($this->isElement($child, self::TEXT_NS, 'insertion')
                || $this->isElement($child, self::TEXT_NS, 'deletion')
                || $this->isElement($child, self::TEXT_NS, 'format-change')
            ) {
                return $child;
            }
        }

        return null;
    }

    private function changeInfoText(\DOMElement $changeInfo, string $name): string
    {
        $element = self::firstChildElement($changeInfo, $name, self::DC_NS);

        return $element instanceof \DOMElement ? self::normalizedText($element) : '';
    }

    private function trackedChangeBodyText(\DOMElement $changeElement): string
    {
        $parts = [];
        foreach ($changeElement->childNodes as $child) {
            if ($child instanceof \DOMElement && $this->isElement($child, self::OFFICE_NS, 'change-info')) {
                continue;
            }
            if ($child instanceof \DOMText || $child instanceof \DOMCdataSection) {
                $text = trim($child->textContent);
                if ($text !== '') {
                    $parts[] = $text;
                }
                continue;
            }
            if ($child instanceof \DOMElement) {
                $text = self::normalizedText($child);
                if ($text !== '') {
                    $parts[] = $text;
                }
            }
        }

        $text = implode(' ', $parts);

        return trim(preg_replace('/\s+/u', ' ', $text) ?? $text);
    }

    /**
     * @return array<string, int>
     */
    private function documentStatistics(\DOMElement $element): array
    {
        $statistics = [];
        foreach (['page-count', 'table-count', 'image-count', 'object-count', 'paragraph-count', 'word-count', 'character-count'] as $name) {
            $value = self::attr($element, self::META_NS, $name);
            if ($value !== '' && ctype_digit($value)) {
                $statistics[self::camelCase($name)] = (int) $value;
            }
        }

        return $statistics;
    }

    /**
     * @param array{styles:array<string, array<string, mixed>>, listStyles:array<string, array<string, mixed>>} $catalog
     * @return list<AstNode>
     */
    private function blockNodes(\DOMElement $parent, ZipPackage $package, array $catalog, bool $skipDirectAnnotations = false): array
    {
        $blocks = [];
        foreach ($parent->childNodes as $child) {
            if (!$child instanceof \DOMElement) {
                continue;
            }

            if ($this->isElement($child, self::TEXT_NS, 'h')) {
                $blocks[] = $this->headingNode($child, $catalog, $package);
                continue;
            }
            if ($this->isElement($child, self::TEXT_NS, 'p')) {
                $paragraph = $this->paragraphNode($child, $catalog, $package);
                if ($paragraph !== null) {
                    $blocks[] = $paragraph;
                }
                continue;
            }
            if ($this->isElement($child, self::TEXT_NS, 'list')) {
                $blocks[] = $this->listNode($child, $package, $catalog);
                continue;
            }
            if ($this->isElement($child, self::TEXT_NS, 'section')) {
                $blocks[] = $this->sectionNode($child, $package, $catalog);
                continue;
            }
            if ($this->isElement($child, self::TEXT_NS, 'table-of-content')) {
                $tableOfContents = $this->tableOfContentsNode($child, $package, $catalog);
                if ($tableOfContents !== null) {
                    $blocks[] = $tableOfContents;
                }
                continue;
            }
            if ($this->generatedIndexType($child) !== null) {
                $generatedIndex = $this->generatedIndexNode($child, $package, $catalog);
                if ($generatedIndex !== null) {
                    $blocks[] = $generatedIndex;
                }
                continue;
            }
            if ($this->isElement($child, self::TABLE_NS, 'table')) {
                $blocks[] = $this->tableNode($child, $package, $catalog);
                continue;
            }
            if ($this->isElement($child, self::DRAW_NS, 'frame')) {
                $block = $this->frameBlockNode($child, $package, $catalog);
                if ($block !== null) {
                    $blocks[] = $block;
                }
                continue;
            }
            if ($this->isElement($child, self::DRAW_NS, 'control')) {
                $control = $this->formControlNode($child, null, false);
                if ($control !== null) {
                    $blocks[] = $control;
                }
                continue;
            }
            if ($this->isElement($child, self::OFFICE_NS, 'annotation')) {
                if ($skipDirectAnnotations) {
                    continue;
                }
                $blocks[] = $this->annotationBlockNode($child, $package, $catalog);
            }
        }

        return $this->postProcessBlocks($blocks);
    }

    /**
     * @param list<AstNode> $blocks
     * @return list<AstNode>
     */
    private function postProcessBlocks(array $blocks): array
    {
        $processed = [];
        for ($index = 0, $count = count($blocks); $index < $count; $index++) {
            $block = $blocks[$index];
            $next = $blocks[$index + 1] ?? null;
            if ($block->type === 'table' && $next instanceof AstNode && $this->nodeHasClass($next, 'odf-table-caption')) {
                $processed[] = $this->tableWithFollowingCaption($block, $next);
                $index++;
                continue;
            }

            $processed[] = $block;
        }

        return $processed;
    }

    private function tableWithFollowingCaption(AstNode $table, AstNode $caption): AstNode
    {
        $captionText = trim((string) $caption->attr('text', ''));
        if ($captionText === '') {
            $captionText = trim($this->plainBlockText($caption->children));
        }
        if ($captionText === '') {
            return $table;
        }

        $captionBlocks = $caption->children;
        $captionInlines = [];
        foreach ($captionBlocks as $block) {
            if ($block instanceof AstNode && $block->type === 'paragraph') {
                $captionInlines = $block->children;
                break;
            }
        }

        $sourceAttributes = [
            'classes' => ['odf-table-caption'],
            'attributes' => [
                'data-odf-table-caption-source' => 'following-paragraph',
            ],
        ];
        $styleName = (string) $caption->attr('styleName', '');
        if ($styleName !== '') {
            $sourceAttributes['attributes']['data-odf-table-caption-style-name'] = $styleName;
        }

        $attrs = $table->attrs;
        $attrs['caption'] = $captionText;
        if ($captionInlines !== []) {
            $attrs['captionInlines'] = $captionInlines;
        }
        if ($captionBlocks !== []) {
            $attrs['captionBlocks'] = $captionBlocks;
        }
        $attrs['captionSource'] = [
            'source' => 'odf-table-caption-paragraph',
            'element' => 'text:p',
            'sourceElement' => 'text:p',
            'position' => 'following-table',
            'sourcePosition' => 'following-table',
            'styleName' => $styleName === '' ? null : $styleName,
            'sourceAttributes' => $sourceAttributes,
        ];
        $attrs['odfCaptionParagraph'] = true;

        $tableName = trim((string) ($attrs['tableName'] ?? ''));

        return TableGeometry::withReviewPacket(
            new AstNode('table', $attrs, $table->children),
            ['idPrefix' => $tableName === '' ? 'odf-table' : $tableName]
        );
    }

    /**
     * @param array{styles:array<string, array<string, mixed>>, listStyles:array<string, array<string, mixed>>} $catalog
     */
    private function headingNode(\DOMElement $heading, array $catalog, ?ZipPackage $package = null): AstNode
    {
        $styleName = self::attr($heading, self::TEXT_NS, 'style-name');
        $style = $this->resolveStyle($styleName, $catalog);
        $level = self::intAttr($heading, self::TEXT_NS, 'outline-level', (int) ($style['headingLevel'] ?? 1));
        $level = max(1, min(6, $level));
        $inlines = $this->coalesceTextNodes($this->inlineNodes($heading, $catalog, $package));
        $headingAnchor = $this->extractHeadingBookmarkAnchor($inlines);
        $inlines = $headingAnchor['inlines'];
        $text = $this->plainInlineText($inlines);
        $attributeAnchor = $headingAnchor['anchor'] === null ? $this->headingAttributeAnchor($heading) : null;
        $attrs = [
            'level' => $level,
            'sourceFormat' => 'odt',
            'text' => $text,
            'id' => $headingAnchor['anchor'] === null
                ? ($attributeAnchor === null
                    ? $this->uniqueHeadingAnchor($text)
                    : $this->uniqueHeadingAnchorFromBase($attributeAnchor['sourceId']))
                : $this->uniqueHeadingAnchorFromBase($headingAnchor['anchor']['id']),
        ];
        if ($headingAnchor['anchor'] !== null) {
            $this->addHeadingBookmarkAnchorAttrs($attrs, $headingAnchor['anchor']);
        } elseif ($attributeAnchor !== null) {
            $this->addHeadingAttributeAnchorAttrs($attrs, $attributeAnchor);
        }
        if ($styleName !== '') {
            $attrs['styleName'] = $styleName;
            $attrs['style'] = $style;
        }

        return new AstNode('heading', $attrs, $inlines);
    }

    /**
     * @param array{styles:array<string, array<string, mixed>>, listStyles:array<string, array<string, mixed>>} $catalog
     */
    private function paragraphNode(\DOMElement $paragraph, array $catalog, ?ZipPackage $package = null): ?AstNode
    {
        $styleName = self::attr($paragraph, self::TEXT_NS, 'style-name');
        $style = $this->resolveStyle($styleName, $catalog);
        $inlines = $this->coalesceTextNodes($this->inlineNodes($paragraph, $catalog, $package));
        $text = $this->plainInlineText($inlines);
        if ($inlines === [] && trim($text) === '') {
            return null;
        }

        $attrs = [
            'sourceFormat' => 'odt',
            'text' => $text,
        ];
        if ($styleName !== '') {
            $attrs['styleName'] = $styleName;
            $attrs['style'] = $style;
        }

        if ($this->isPreformattedParagraphStyle($styleName, $style)) {
            $codeAttrs = $attrs;
            $codeAttrs['odfPreformatted'] = true;
            $codeAttrs['attributes'] = [
                'data-odf-preformatted' => 'true',
            ];
            if ($styleName !== '') {
                $codeAttrs['attributes']['data-odf-style-name'] = $styleName;
            }

            return new AstNode('code_block', $codeAttrs);
        }

        $headingLevel = (int) ($style['headingLevel'] ?? 0);
        if ($headingLevel > 0) {
            $attrs['level'] = max(1, min(6, $headingLevel));
            $headingAnchor = $this->extractHeadingBookmarkAnchor($inlines);
            $inlines = $headingAnchor['inlines'];
            $inlines = $this->paragraphTextStyledInlineNodes($inlines, $styleName, $style);
            $text = $this->plainInlineText($inlines);
            $attributeAnchor = $headingAnchor['anchor'] === null ? $this->headingAttributeAnchor($paragraph) : null;
            $attrs['text'] = $text;
            $attrs['id'] = $headingAnchor['anchor'] === null
                ? ($attributeAnchor === null
                    ? $this->uniqueHeadingAnchor($text)
                    : $this->uniqueHeadingAnchorFromBase($attributeAnchor['sourceId']))
                : $this->uniqueHeadingAnchorFromBase($headingAnchor['anchor']['id']);
            if ($headingAnchor['anchor'] !== null) {
                $this->addHeadingBookmarkAnchorAttrs($attrs, $headingAnchor['anchor']);
            } elseif ($attributeAnchor !== null) {
                $this->addHeadingAttributeAnchorAttrs($attrs, $attributeAnchor);
            }

            return new AstNode('heading', $attrs, $inlines);
        }

        $inlines = $this->paragraphTextStyledInlineNodes($inlines, $styleName, $style);
        $text = $this->plainInlineText($inlines);
        $attrs['text'] = $text;

        $node = new AstNode('paragraph', $attrs, $inlines);
        if ($styleName === 'Table') {
            return new AstNode('div', [
                'sourceFormat' => 'odt',
                'styleName' => $styleName,
                'style' => $style,
                'text' => $text,
                'tableCaption' => true,
                'classes' => ['caption', 'odf-table-caption'],
                'attributes' => [
                    'data-odf-table-caption-style-name' => $styleName,
                ],
            ], [$node]);
        }
        if ($this->currentListLevel === 0 && $this->isBlockquoteParagraphStyle($style)) {
            $quoteAttrs = $attrs;
            $quoteAttrs['classes'] = ['odf-blockquote'];

            return new AstNode('blockquote', $quoteAttrs, [$node]);
        }

        return $node;
    }

    /**
     * @param array{styles:array<string, array<string, mixed>>, listStyles:array<string, array<string, mixed>>} $catalog
     */
    private function listNode(\DOMElement $list, ZipPackage $package, array $catalog): AstNode
    {
        $previousLevel = $this->currentListLevel;
        $this->currentListLevel++;

        try {
            return $this->listNodeAtCurrentLevel($list, $package, $catalog);
        } finally {
            $this->currentListLevel = $previousLevel;
        }
    }

    /**
     * @param array{styles:array<string, array<string, mixed>>, listStyles:array<string, array<string, mixed>>} $catalog
     */
    private function listNodeAtCurrentLevel(\DOMElement $list, ZipPackage $package, array $catalog): AstNode
    {
        $explicitStyleName = self::attr($list, self::TEXT_NS, 'style-name');
        $inheritedStyleName = $explicitStyleName === '' ? $this->currentListStyleName() : '';
        $styleName = $explicitStyleName !== '' ? $explicitStyleName : $inheritedStyleName;
        $level = max(1, $this->currentListLevel);
        $definition = $this->listDefinition($styleName, $level, $catalog);
        $ordered = $definition['type'] === 'number';
        $continueNumbering = strtolower(self::attr($list, self::TEXT_NS, 'continue-numbering')) === 'true';
        $continueListId = self::attr($list, self::TEXT_NS, 'continue-list');
        $listId = self::attr($list, self::TEXT_NS, 'id');
        $listIdAttribute = $listId === '' ? '' : 'text:id';
        if ($listId === '') {
            $listId = self::attr($list, self::XML_NS, 'id');
            $listIdAttribute = $listId === '' ? '' : 'xml:id';
        }
        $continueFromNamedList = $continueListId !== '';
        $defaultStart = max(1, (int) ($definition['start'] ?? 1));
        $explicitStart = self::nullableInt(self::attr($list, self::TEXT_NS, 'start-value'));
        $continuedStart = $this->continuedListStart($level, $continueListId, $defaultStart);
        $start = ($continueNumbering || $continueFromNamedList)
            ? $continuedStart
            : ($explicitStart === null ? $defaultStart : max(1, $explicitStart));
        $attrs = [
            'sourceFormat' => 'odt',
            'listLevel' => $level,
        ];
        if ($explicitStyleName !== '') {
            $attrs['styleName'] = $explicitStyleName;
        } elseif ($inheritedStyleName !== '') {
            $attrs['inheritedStyleName'] = $inheritedStyleName;
        }
        if ($continueNumbering || $continueFromNamedList) {
            $attrs['continued'] = true;
        }
        $htmlAttributes = [];
        if ($listId !== '') {
            $attrs['listId'] = $listId;
            $attrs['listIdAttribute'] = $listIdAttribute;
            $htmlAttributes['data-odf-list-id'] = $listId;
            $htmlAttributes['data-odf-list-id-attribute'] = $listIdAttribute;
        }
        if ($continueListId !== '') {
            $attrs['continueList'] = $continueListId;
            $htmlAttributes['data-odf-list-continue-list'] = $continueListId;
        }
        if ($continueFromNamedList) {
            $htmlAttributes['data-odf-list-continued'] = 'true';
        }
        if (($definition['type'] ?? '') === 'image') {
            $attrs['listImageStyle'] = true;
            $imageMetadata = $definition['image'] ?? [];
            if (is_array($imageMetadata) && $imageMetadata !== []) {
                $attrs['listImageMetadata'] = $imageMetadata;
            }
            $levelProperties = $definition['levelProperties'] ?? [];
            if (is_array($levelProperties) && $levelProperties !== []) {
                $attrs['listLevelProperties'] = $levelProperties;
            }
            $this->addImageListStyleHtmlAttributes($htmlAttributes, $definition);
        }
        $textProperties = $definition['textProperties'] ?? [];
        if (is_array($textProperties) && $textProperties !== []) {
            $attrs['listTextProperties'] = $textProperties;
            $this->addListTextPropertyHtmlAttributes($htmlAttributes, $textProperties);
        }
        if ($ordered) {
            $attrs['style'] = $this->orderedListStyle((string) ($definition['format'] ?? '1'));
            $attrs['start'] = $start;
            $attrs['styleStart'] = $defaultStart;
            $attrs['startSource'] = $continueFromNamedList
                ? 'continue-list'
                : ($explicitStart === null || $continueNumbering ? 'style-start-value' : 'list-start-value');
            if ($explicitStart !== null && !$continueNumbering && !$continueFromNamedList) {
                $attrs['explicitStartValue'] = max(1, $explicitStart);
            }
            $numPrefix = (string) ($definition['numPrefix'] ?? '');
            $numSuffix = (string) ($definition['numSuffix'] ?? '');
            $delimiter = $this->orderedListDelimiter($numPrefix, $numSuffix);
            if ($delimiter !== '') {
                $attrs['delimiter'] = $delimiter;
            }
            if ($numPrefix !== '') {
                $attrs['numberPrefix'] = $numPrefix;
            }
            if ($numSuffix !== '') {
                $attrs['numberSuffix'] = $numSuffix;
            }
        } else {
            $attrs['format'] = ($definition['type'] ?? '') === 'image'
                ? 'image'
                : (string) ($definition['bulletChar'] ?? 'bullet');
        }
        if ($htmlAttributes !== []) {
            $attrs['htmlAttributes'] = $htmlAttributes;
        }

        $items = [];
        $numberedItemCount = 0;
        $pushedStyleName = $this->pushCurrentListStyleName($styleName, $catalog);
        try {
            foreach (self::childElements($list) as $child) {
                $isListHeader = $this->isElement($child, self::TEXT_NS, 'list-header');
                if (!$this->isElement($child, self::TEXT_NS, 'list-item') && !$isListHeader) {
                    continue;
                }

                $itemBlocks = $this->blockNodes($child, $package, $catalog);
                $itemAttrs = [
                    'sourceFormat' => 'odt',
                ];
                if ($isListHeader) {
                    $itemAttrs['listHeader'] = true;
                    $itemAttrs['classes'] = ['odf-list-header'];
                    $itemAttrs['attributes'] = [
                        'data-odf-list-header' => 'true',
                        'data-odf-list-level' => (string) $level,
                    ];
                } else {
                    $numberedItemCount++;
                }

                $items[] = new AstNode('list_item', $itemAttrs, $itemBlocks);
            }
        } finally {
            if ($pushedStyleName) {
                array_pop($this->currentListStyleNames);
            }
        }

        $nextStart = $start + $numberedItemCount;
        $this->listContinuationStartCounters[$level] = $nextStart;
        if ($listId !== '') {
            $this->listContinuationStartCountersById[$listId][$level] = $nextStart;
        }

        return new AstNode($ordered ? 'ordered_list' : 'bullet_list', $attrs, $items);
    }

    private function continuedListStart(int $level, string $continueListId, int $defaultStart): int
    {
        if ($continueListId !== '') {
            $byLevel = $this->listContinuationStartCountersById[$continueListId] ?? null;
            if (is_array($byLevel)) {
                return max(1, (int) ($byLevel[$level] ?? $defaultStart));
            }
        }

        return max(1, $this->listContinuationStartCounters[$level] ?? $defaultStart);
    }

    private function currentListStyleName(): string
    {
        if ($this->currentListStyleNames === []) {
            return '';
        }

        return $this->currentListStyleNames[array_key_last($this->currentListStyleNames)];
    }

    /**
     * @param array{listStyles:array<string, array<string, mixed>>} $catalog
     */
    private function pushCurrentListStyleName(string $styleName, array $catalog): bool
    {
        if ($styleName === '' || !isset($catalog['listStyles'][$styleName])) {
            return false;
        }

        $this->currentListStyleNames[] = $styleName;

        return true;
    }

    /**
     * @param array<string, string> $htmlAttributes
     * @param array<string, mixed> $definition
     */
    private function addImageListStyleHtmlAttributes(array &$htmlAttributes, array $definition): void
    {
        $htmlAttributes['data-odf-list-image-style'] = 'true';

        $imageMetadata = $definition['image'] ?? [];
        if (is_array($imageMetadata)) {
            foreach ($imageMetadata as $name => $value) {
                if (!is_scalar($value) || (string) $value === '') {
                    continue;
                }
                $htmlAttributes['data-odf-list-image-' . self::kebabCase((string) $name)] = is_bool($value)
                    ? ($value ? 'true' : 'false')
                    : (string) $value;
            }
        }

        $levelProperties = $definition['levelProperties'] ?? [];
        if (!is_array($levelProperties)) {
            return;
        }
        foreach ($levelProperties as $name => $value) {
            if ($name === 'labelAlignment' || !is_scalar($value) || (string) $value === '') {
                continue;
            }
            $htmlAttributes['data-odf-list-level-' . self::kebabCase((string) $name)] = (string) $value;
        }

        $labelAlignment = $levelProperties['labelAlignment'] ?? [];
        if (!is_array($labelAlignment)) {
            return;
        }
        foreach ($labelAlignment as $name => $value) {
            if (!is_scalar($value) || (string) $value === '') {
                continue;
            }
            $htmlAttributes['data-odf-list-label-' . self::kebabCase((string) $name)] = (string) $value;
        }
    }

    /**
     * @param array<string, string> $htmlAttributes
     * @param array<string, mixed> $textProperties
     */
    private function addListTextPropertyHtmlAttributes(array &$htmlAttributes, array $textProperties): void
    {
        $htmlAttributes['data-odf-list-text-property-count'] = (string) count($textProperties);

        foreach ($textProperties as $name => $value) {
            if ($name === 'fontFace' && is_array($value)) {
                foreach ($value as $fontFaceName => $fontFaceValue) {
                    if (is_bool($fontFaceValue)) {
                        $htmlAttributes['data-odf-list-text-font-face-' . self::kebabCase((string) $fontFaceName)] = $fontFaceValue ? 'true' : 'false';
                        continue;
                    }
                    if (!is_scalar($fontFaceValue) || (string) $fontFaceValue === '') {
                        continue;
                    }
                    $htmlAttributes['data-odf-list-text-font-face-' . self::kebabCase((string) $fontFaceName)] = (string) $fontFaceValue;
                }
                continue;
            }

            if (is_bool($value)) {
                $htmlAttributes['data-odf-list-text-' . self::kebabCase((string) $name)] = $value ? 'true' : 'false';
                continue;
            }
            if (!is_scalar($value) || (string) $value === '') {
                continue;
            }
            $htmlAttributes['data-odf-list-text-' . self::kebabCase((string) $name)] = (string) $value;
        }
    }

    /**
     * @param array{styles:array<string, array<string, mixed>>, listStyles:array<string, array<string, mixed>>} $catalog
     */
    private function sectionNode(\DOMElement $section, ZipPackage $package, array $catalog): AstNode
    {
        $name = self::attr($section, self::TEXT_NS, 'name');
        $styleName = self::attr($section, self::TEXT_NS, 'style-name');
        $classes = ['odf-section'];
        $attributes = [];
        $attrs = [
            'sourceFormat' => 'odt',
            'classes' => $classes,
            'attributes' => $attributes,
        ];
        if ($name !== '') {
            $attrs['id'] = self::slug($name);
            $attrs['attributes']['data-odf-section-name'] = $name;
        }
        if ($styleName !== '') {
            $attrs['styleName'] = $styleName;
            $attrs['attributes']['data-odf-section-style-name'] = $styleName;
        }

        $protectedValue = strtolower(self::attr($section, self::TEXT_NS, 'protected'));
        if ($protectedValue !== '') {
            $protected = in_array($protectedValue, ['true', '1'], true);
            $attrs['protected'] = $protected;
            $attrs['attributes']['data-odf-section-protected'] = $protected ? 'true' : 'false';
        }

        $protectionKey = self::attr($section, self::TEXT_NS, 'protection-key');
        if ($protectionKey !== '') {
            $attrs['protectionKeyPresent'] = true;
            $attrs['attributes']['data-odf-section-protection-key-present'] = 'true';
        }
        $digestAlgorithm = self::attr($section, self::TEXT_NS, 'protection-key-digest-algorithm');
        if ($digestAlgorithm !== '') {
            $attrs['protectionKeyDigestAlgorithm'] = $digestAlgorithm;
            $attrs['attributes']['data-odf-section-protection-key-digest-algorithm'] = $digestAlgorithm;
        }

        $condition = self::attr($section, self::TEXT_NS, 'condition');
        if ($condition !== '') {
            $attrs['sectionCondition'] = $condition;
            $attrs['classes'][] = 'odf-conditional-section';
            $attrs['attributes']['data-odf-section-condition'] = $condition;
        }
        $hidden = self::nullableBool(self::attr($section, self::TEXT_NS, 'is-hidden'));
        if ($hidden !== null) {
            $attrs['sectionHidden'] = $hidden;
            $attrs['attributes']['data-odf-section-hidden'] = $hidden ? 'true' : 'false';
            if ($hidden) {
                $attrs['classes'][] = 'odf-hidden-section';
            }
        }
        $display = self::attr($section, self::TEXT_NS, 'display');
        if ($display !== '') {
            $attrs['sectionDisplay'] = $display;
            $attrs['attributes']['data-odf-section-display'] = $display;
        }

        $sectionSource = self::firstChildElement($section, 'section-source', self::TEXT_NS);
        if ($sectionSource instanceof \DOMElement) {
            $source = $this->sectionSourceMetadata($sectionSource);
            if ($source !== []) {
                $attrs['sectionSource'] = $source;
                $attrs['classes'][] = 'odf-linked-section';
                foreach ($source as $name => $value) {
                    $attributeName = $name === 'sectionName' ? 'name' : self::kebabCase($name);
                    $attrs['attributes']['data-odf-section-source-' . $attributeName] = $value;
                }
            }
        }
        if (($attrs['protected'] ?? false) === true) {
            $attrs['classes'][] = 'odf-protected-section';
        }

        return new AstNode('div', $attrs, $this->blockNodes($section, $package, $catalog));
    }

    /**
     * @param array{styles:array<string, array<string, mixed>>, listStyles:array<string, array<string, mixed>>} $catalog
     */
    private function tableOfContentsNode(\DOMElement $tableOfContents, ZipPackage $package, array $catalog): ?AstNode
    {
        $name = self::attr($tableOfContents, self::TEXT_NS, 'name');
        $styleName = self::attr($tableOfContents, self::TEXT_NS, 'style-name');
        $attrs = [
            'sourceFormat' => 'odt',
            'classes' => ['odf-table-of-contents'],
            'attributes' => [],
        ];

        if ($name !== '') {
            $attrs['id'] = self::slug($name);
            $attrs['tableOfContentsName'] = $name;
            $attrs['attributes']['data-odf-toc-name'] = $name;
        }
        if ($styleName !== '') {
            $attrs['styleName'] = $styleName;
            $attrs['attributes']['data-odf-toc-style-name'] = $styleName;
        }

        $protected = self::nullableBool(self::attr($tableOfContents, self::TEXT_NS, 'protected'));
        if ($protected !== null) {
            $attrs['protected'] = $protected;
            $attrs['attributes']['data-odf-toc-protected'] = $protected ? 'true' : 'false';
            if ($protected) {
                $attrs['classes'][] = 'odf-protected-table-of-contents';
            }
        }

        $protectionKey = self::attr($tableOfContents, self::TEXT_NS, 'protection-key');
        if ($protectionKey !== '') {
            $attrs['protectionKeyPresent'] = true;
            $attrs['attributes']['data-odf-toc-protection-key-present'] = 'true';
        }
        $digestAlgorithm = self::attr($tableOfContents, self::TEXT_NS, 'protection-key-digest-algorithm');
        if ($digestAlgorithm !== '') {
            $attrs['protectionKeyDigestAlgorithm'] = $digestAlgorithm;
            $attrs['attributes']['data-odf-toc-protection-key-digest-algorithm'] = $digestAlgorithm;
        }

        $source = self::firstChildElement($tableOfContents, 'table-of-content-source', self::TEXT_NS);
        if ($source instanceof \DOMElement) {
            $sourceMetadata = $this->tableOfContentsSourceMetadata($source);
            if ($sourceMetadata !== []) {
                $attrs['tableOfContentsSource'] = $sourceMetadata;
                $this->addTableOfContentsSourceAttributes($attrs['attributes'], $sourceMetadata);
            }
        }

        $children = [];
        foreach (self::childElements($tableOfContents) as $child) {
            if ($this->isElement($child, self::TEXT_NS, 'index-title')) {
                $titleBlocks = $this->blockNodes($child, $package, $catalog);
                if ($titleBlocks !== []) {
                    $children[] = new AstNode('div', [
                        'sourceFormat' => 'odt',
                        'classes' => ['odf-index-title'],
                        'attributes' => [
                            'data-odf-index-title' => 'true',
                        ],
                    ], $titleBlocks);
                }
                continue;
            }
            if ($this->isElement($child, self::TEXT_NS, 'index-body')) {
                $bodyBlocks = $this->blockNodes($child, $package, $catalog);
                if ($bodyBlocks !== []) {
                    $children[] = new AstNode('div', [
                        'sourceFormat' => 'odt',
                        'classes' => ['odf-index-body'],
                        'attributes' => [
                            'data-odf-index-body' => 'true',
                        ],
                    ], $bodyBlocks);
                }
            }
        }

        if ($children === [] && $attrs['attributes'] === []) {
            return null;
        }

        return new AstNode('div', $attrs, $children);
    }

    /**
     * @return array<string, mixed>
     */
    private function tableOfContentsSourceMetadata(\DOMElement $source): array
    {
        $metadata = self::withoutEmpty([
            'outlineLevel' => self::nullableInt(self::attr($source, self::TEXT_NS, 'outline-level')),
            'relativeTabStopPosition' => self::nullableBool(self::attr($source, self::TEXT_NS, 'relative-tab-stop-position')),
            'useIndexMarks' => self::nullableBool(self::attr($source, self::TEXT_NS, 'use-index-marks')),
            'useIndexSourceStyles' => self::nullableBool(self::attr($source, self::TEXT_NS, 'use-index-source-styles')),
            'useObjects' => self::nullableBool(self::attr($source, self::TEXT_NS, 'use-objects')),
            'useTables' => self::nullableBool(self::attr($source, self::TEXT_NS, 'use-tables')),
            'useGraphics' => self::nullableBool(self::attr($source, self::TEXT_NS, 'use-graphics')),
        ]);

        $sourceStyles = $this->tableOfContentsSourceStyles($source);
        if ($sourceStyles !== []) {
            $metadata['sourceStyles'] = $sourceStyles;
        }

        $templates = $this->tableOfContentsTemplates($source);
        if ($templates !== []) {
            $metadata['templates'] = $templates;
        }

        return $metadata;
    }

    /**
     * @return list<array{outlineLevel?:int, styleNames:list<string>}>
     */
    private function tableOfContentsSourceStyles(\DOMElement $source): array
    {
        $styles = [];
        foreach (self::childElements($source, 'index-source-styles', self::TEXT_NS) as $sourceStyles) {
            $styleNames = [];
            foreach (self::childElements($sourceStyles, 'index-source-style', self::TEXT_NS) as $sourceStyle) {
                $styleName = self::attr($sourceStyle, self::TEXT_NS, 'style-name');
                if ($styleName !== '') {
                    $styleNames[] = $styleName;
                }
            }

            $entry = self::withoutEmpty([
                'outlineLevel' => self::nullableInt(self::attr($sourceStyles, self::TEXT_NS, 'outline-level')),
                'styleNames' => $styleNames,
            ]);
            if ($entry !== []) {
                $styles[] = $entry;
            }
        }

        return $styles;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function tableOfContentsTemplates(\DOMElement $source): array
    {
        $templates = [];
        foreach (self::childElements($source) as $template) {
            if (!$this->isElement($template, self::TEXT_NS, 'index-title-template')
                && !$this->isElement($template, self::TEXT_NS, 'table-of-content-entry-template')) {
                continue;
            }

            $templates[] = self::withoutEmpty([
                'type' => $template->localName === 'index-title-template' ? 'title' : 'entry',
                'outlineLevel' => self::nullableInt(self::attr($template, self::TEXT_NS, 'outline-level')),
                'styleName' => self::nullable(self::attr($template, self::TEXT_NS, 'style-name')),
                'components' => $this->tableOfContentsTemplateComponents($template),
            ]);
        }

        return $templates;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function tableOfContentsTemplateComponents(\DOMElement $template): array
    {
        $components = [];
        foreach (self::childElements($template) as $component) {
            if ($component->namespaceURI !== self::TEXT_NS) {
                continue;
            }

            $componentMetadata = self::withoutEmpty([
                'type' => $component->localName,
                'styleName' => self::attr($component, self::TEXT_NS, 'style-name'),
                'name' => self::attr($component, self::TEXT_NS, 'name'),
                'display' => self::attr($component, self::TEXT_NS, 'display'),
                'outlineLevel' => self::nullableInt(self::attr($component, self::TEXT_NS, 'outline-level')),
                'chapterFormat' => self::attr($component, self::TEXT_NS, 'chapter-format'),
                'bibliographyDataField' => self::attr($component, self::TEXT_NS, 'bibliography-data-field'),
                'leaderChar' => self::attr($component, self::STYLE_NS, 'leader-char'),
                'leaderText' => self::attr($component, self::STYLE_NS, 'leader-text'),
                'tabStopType' => self::attr($component, self::STYLE_NS, 'type'),
                'tabStopPosition' => self::attr($component, self::STYLE_NS, 'position'),
                'href' => self::attr($component, self::XLINK_NS, 'href'),
                'xlinkType' => self::attr($component, self::XLINK_NS, 'type'),
                'xlinkShow' => self::attr($component, self::XLINK_NS, 'show'),
                'xlinkActuate' => self::attr($component, self::XLINK_NS, 'actuate'),
            ]);
            if ($componentMetadata !== []) {
                $components[] = $componentMetadata;
            }
        }

        return $components;
    }

    /**
     * @param array<string, string> $attributes
     * @param array<string, mixed> $sourceMetadata
     */
    private function addTableOfContentsSourceAttributes(array &$attributes, array $sourceMetadata): void
    {
        foreach ($sourceMetadata as $name => $value) {
            if (is_bool($value)) {
                $attributes['data-odf-toc-source-' . self::kebabCase($name)] = $value ? 'true' : 'false';
                continue;
            }
            if (is_int($value) || is_string($value)) {
                $attributes['data-odf-toc-source-' . self::kebabCase($name)] = (string) $value;
            }
        }

        if (isset($sourceMetadata['sourceStyles']) && is_array($sourceMetadata['sourceStyles'])) {
            $attributes['data-odf-toc-source-style-count'] = (string) count($sourceMetadata['sourceStyles']);
        }
        if (isset($sourceMetadata['templates']) && is_array($sourceMetadata['templates'])) {
            $attributes['data-odf-toc-template-count'] = (string) count($sourceMetadata['templates']);
        }
    }

    /**
     * @param array{styles:array<string, array<string, mixed>>, listStyles:array<string, array<string, mixed>>} $catalog
     */
    private function generatedIndexNode(\DOMElement $index, ZipPackage $package, array $catalog): ?AstNode
    {
        $type = $this->generatedIndexType($index);
        if ($type === null) {
            return null;
        }

        $element = $index->localName;
        $name = self::attr($index, self::TEXT_NS, 'name');
        $styleName = self::attr($index, self::TEXT_NS, 'style-name');
        $attrs = [
            'sourceFormat' => 'odt',
            'generatedIndexType' => $type,
            'generatedIndexElement' => $element,
            'classes' => ['odf-generated-index', 'odf-' . $element],
            'attributes' => [
                'data-odf-index-type' => $type,
                'data-odf-index-element' => $element,
            ],
        ];

        if ($name !== '') {
            $attrs['id'] = self::slug($name);
            $attrs['generatedIndexName'] = $name;
            $attrs['attributes']['data-odf-index-name'] = $name;
        }
        if ($styleName !== '') {
            $attrs['styleName'] = $styleName;
            $attrs['attributes']['data-odf-index-style-name'] = $styleName;
        }

        $protected = self::nullableBool(self::attr($index, self::TEXT_NS, 'protected'));
        if ($protected !== null) {
            $attrs['protected'] = $protected;
            $attrs['attributes']['data-odf-index-protected'] = $protected ? 'true' : 'false';
            if ($protected) {
                $attrs['classes'][] = 'odf-protected-generated-index';
            }
        }

        $protectionKey = self::attr($index, self::TEXT_NS, 'protection-key');
        if ($protectionKey !== '') {
            $attrs['protectionKeyPresent'] = true;
            $attrs['attributes']['data-odf-index-protection-key-present'] = 'true';
        }
        $digestAlgorithm = self::attr($index, self::TEXT_NS, 'protection-key-digest-algorithm');
        if ($digestAlgorithm !== '') {
            $attrs['protectionKeyDigestAlgorithm'] = $digestAlgorithm;
            $attrs['attributes']['data-odf-index-protection-key-digest-algorithm'] = $digestAlgorithm;
        }

        $source = self::firstChildElement($index, $this->generatedIndexSourceElementName($element), self::TEXT_NS);
        if ($source instanceof \DOMElement) {
            $sourceMetadata = $this->generatedIndexSourceMetadata($source);
            if ($sourceMetadata !== []) {
                $attrs['generatedIndexSource'] = $sourceMetadata;
                $this->addGeneratedIndexSourceAttributes($attrs['attributes'], $sourceMetadata);
            }
        }

        $children = $this->generatedIndexTitleAndBodyNodes($index, $package, $catalog);
        if ($children === [] && count($attrs['attributes']) <= 2) {
            return null;
        }

        return new AstNode('div', $attrs, $children);
    }

    private function generatedIndexType(\DOMElement $element): ?string
    {
        if ($element->namespaceURI !== self::TEXT_NS) {
            return null;
        }

        return match ($element->localName) {
            'alphabetical-index' => 'alphabetical',
            'bibliography' => 'bibliography',
            'illustration-index' => 'illustration',
            'object-index' => 'object',
            'table-index' => 'table',
            'user-index' => 'user',
            default => null,
        };
    }

    private function generatedIndexSourceElementName(string $indexElementName): string
    {
        return $indexElementName === 'bibliography' ? 'bibliography-source' : $indexElementName . '-source';
    }

    /**
     * @return array<string, mixed>
     */
    private function generatedIndexSourceMetadata(\DOMElement $source): array
    {
        $metadata = array_merge(
            ['element' => $source->localName],
            $this->odfElementMetadataAttributes($source, [self::TEXT_NS])
        );

        $sourceStyles = $this->tableOfContentsSourceStyles($source);
        if ($sourceStyles !== []) {
            $metadata['sourceStyles'] = $sourceStyles;
        }

        $templates = $this->generatedIndexTemplates($source);
        if ($templates !== []) {
            $metadata['templates'] = $templates;
        }

        return self::withoutEmpty($metadata);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function generatedIndexTemplates(\DOMElement $source): array
    {
        $templates = [];
        foreach (self::childElements($source) as $template) {
            if ($template->namespaceURI !== self::TEXT_NS) {
                continue;
            }
            if ($template->localName !== 'index-title-template' && !str_ends_with($template->localName, '-entry-template')) {
                continue;
            }

            $templates[] = self::withoutEmpty(array_merge([
                'type' => $template->localName === 'index-title-template' ? 'title' : 'entry',
                'element' => $template->localName,
            ], $this->odfElementMetadataAttributes($template, [self::TEXT_NS]), [
                'components' => $this->tableOfContentsTemplateComponents($template),
            ]));
        }

        return $templates;
    }

    /**
     * @param list<string> $namespaces
     * @return array<string, mixed>
     */
    private function odfElementMetadataAttributes(\DOMElement $element, array $namespaces): array
    {
        $attributes = [];
        if (!$element->hasAttributes()) {
            return $attributes;
        }

        foreach ($element->attributes as $attribute) {
            if (!$attribute instanceof \DOMAttr || !in_array((string) $attribute->namespaceURI, $namespaces, true)) {
                continue;
            }

            $attributes[self::camelCase($attribute->localName)] = self::typedOdfAttributeValue($attribute->value);
        }

        return self::withoutEmpty($attributes);
    }

    /**
     * @param array<string, string> $attributes
     * @param array<string, mixed> $sourceMetadata
     */
    private function addGeneratedIndexSourceAttributes(array &$attributes, array $sourceMetadata): void
    {
        foreach ($sourceMetadata as $name => $value) {
            if (is_bool($value)) {
                $attributes['data-odf-index-source-' . self::kebabCase((string) $name)] = $value ? 'true' : 'false';
                continue;
            }
            if (is_int($value) || is_string($value)) {
                $attributes['data-odf-index-source-' . self::kebabCase((string) $name)] = (string) $value;
            }
        }

        if (isset($sourceMetadata['sourceStyles']) && is_array($sourceMetadata['sourceStyles'])) {
            $attributes['data-odf-index-source-style-count'] = (string) count($sourceMetadata['sourceStyles']);
        }
        if (isset($sourceMetadata['templates']) && is_array($sourceMetadata['templates'])) {
            $attributes['data-odf-index-template-count'] = (string) count($sourceMetadata['templates']);
        }
    }

    /**
     * @param array{styles:array<string, array<string, mixed>>, listStyles:array<string, array<string, mixed>>} $catalog
     * @return list<AstNode>
     */
    private function generatedIndexTitleAndBodyNodes(\DOMElement $index, ZipPackage $package, array $catalog): array
    {
        $children = [];
        foreach (self::childElements($index) as $child) {
            if ($this->isElement($child, self::TEXT_NS, 'index-title')) {
                $titleBlocks = $this->blockNodes($child, $package, $catalog);
                if ($titleBlocks !== []) {
                    $children[] = new AstNode('div', [
                        'sourceFormat' => 'odt',
                        'classes' => ['odf-index-title'],
                        'attributes' => [
                            'data-odf-index-title' => 'true',
                        ],
                    ], $titleBlocks);
                }
                continue;
            }

            if ($this->isElement($child, self::TEXT_NS, 'index-body')) {
                $bodyBlocks = $this->blockNodes($child, $package, $catalog);
                if ($bodyBlocks !== []) {
                    $children[] = new AstNode('div', [
                        'sourceFormat' => 'odt',
                        'classes' => ['odf-index-body'],
                        'attributes' => [
                            'data-odf-index-body' => 'true',
                        ],
                    ], $bodyBlocks);
                }
            }
        }

        return $children;
    }

    /**
     * @return array<string, string>
     */
    private function sectionSourceMetadata(\DOMElement $sectionSource): array
    {
        $source = self::withoutEmpty([
            'href' => self::nullable(self::attr($sectionSource, self::XLINK_NS, 'href')),
            'sectionName' => self::nullable(self::attr($sectionSource, self::TEXT_NS, 'section-name')),
            'filterName' => self::nullable(self::attr($sectionSource, self::TEXT_NS, 'filter-name')),
            'type' => self::nullable(self::attr($sectionSource, self::XLINK_NS, 'type')),
            'show' => self::nullable(self::attr($sectionSource, self::XLINK_NS, 'show')),
            'actuate' => self::nullable(self::attr($sectionSource, self::XLINK_NS, 'actuate')),
        ]);

        return array_map(static fn (mixed $value): string => (string) $value, $source);
    }

    /**
     * @param array{styles:array<string, array<string, mixed>>, listStyles:array<string, array<string, mixed>>, tableTemplates:array<string, array<string, mixed>>} $catalog
     */
    private function tableNode(\DOMElement $table, ZipPackage $package, array $catalog): AstNode
    {
        $columnDefinitions = $this->tableColumnDefinitions($table, $catalog);
        $columnWidths = $this->tableColumnWidths($columnDefinitions);
        $children = [];
        $headerRows = [];
        $bodyRows = [];
        foreach (self::childElements($table) as $child) {
            if ($this->isElement($child, self::TABLE_NS, 'table-header-rows')) {
                foreach (self::childElements($child, 'table-row', self::TABLE_NS) as $row) {
                    array_push($headerRows, ...$this->repeatedRows($row, $package, $catalog, $columnDefinitions));
                }
                continue;
            }
            if ($this->isElement($child, self::TABLE_NS, 'table-row')) {
                array_push($bodyRows, ...$this->repeatedRows($child, $package, $catalog, $columnDefinitions));
            }
        }

        if ($headerRows !== []) {
            $children[] = new AstNode('table_head', [], $headerRows);
        }
        $children[] = new AstNode('table_body', [], $bodyRows);

        $attrs = [
            'sourceFormat' => 'odt',
            'caption' => '',
        ];
        $tableName = self::attr($table, self::TABLE_NS, 'name');
        if ($tableName !== '') {
            $attrs['caption'] = $tableName;
            $attrs['tableName'] = $tableName;
            $attrs['htmlAttributes'] = [
                'data-odf-table-name' => $tableName,
            ];
        }

        $printRanges = $this->tablePrintRanges($table);
        if ($printRanges !== []) {
            $attrs['odfPrintRanges'] = $printRanges;
            $attrs['printRangeCount'] = count($printRanges);
            $attrs['htmlAttributes']['data-odf-table-print-range-count'] = (string) count($printRanges);
            $attrs['htmlAttributes']['data-odf-table-print-ranges'] = implode(';', $printRanges);
        }

        $scenarios = $this->tableScenarios($table);
        if ($scenarios !== []) {
            $activeScenarioCount = $this->activeTableScenarioCount($scenarios);
            $attrs['odfTableScenarios'] = $scenarios;
            $attrs['scenarioCount'] = count($scenarios);
            $attrs['activeScenarioCount'] = $activeScenarioCount;
            $attrs['htmlAttributes'] = array_merge(
                is_array($attrs['htmlAttributes'] ?? null) ? $attrs['htmlAttributes'] : [],
                $this->tableScenarioHtmlAttributes($scenarios),
            );
            $classes = is_array($attrs['classes'] ?? null) ? $attrs['classes'] : [];
            $classes[] = 'odf-table-scenario';
            $attrs['classes'] = array_values(array_unique($classes));
        }

        $styleName = self::attr($table, self::TABLE_NS, 'style-name');
        if ($styleName !== '') {
            $attrs['styleName'] = $styleName;
            $attrs['htmlAttributes']['data-odf-table-style-name'] = $styleName;
        }

        $templateName = self::attr($table, self::TABLE_NS, 'template-name');
        if ($templateName !== '') {
            $template = $catalog['tableTemplates'][$templateName] ?? null;
            $attrs['templateName'] = $templateName;
            $attrs['htmlAttributes']['data-odf-table-template-name'] = $templateName;
            $attrs['htmlAttributes']['data-odf-table-template-exists'] = is_array($template) ? 'true' : 'false';
            $classes = is_array($attrs['classes'] ?? null) ? $attrs['classes'] : [];
            $classes[] = 'odf-table-template';
            $attrs['classes'] = array_values(array_unique($classes));
            if (is_array($template)) {
                $attrs['tableTemplate'] = $template;
                $attrs['htmlAttributes']['data-odf-table-template-style-count'] = (string) count($this->tableTemplateStyleNames($template));
            } else {
                $attrs['classes'][] = 'odf-missing-table-template';
            }
        }

        $protectedValue = strtolower(self::attr($table, self::TABLE_NS, 'protected'));
        if ($protectedValue !== '') {
            $protected = in_array($protectedValue, ['true', '1'], true);
            $attrs['protected'] = $protected;
            $attrs['htmlAttributes']['data-odf-table-protected'] = $protected ? 'true' : 'false';
        }

        $protectionKey = self::attr($table, self::TABLE_NS, 'protection-key');
        if ($protectionKey !== '') {
            $attrs['protectionKeyPresent'] = true;
            $attrs['htmlAttributes']['data-odf-table-protection-key-present'] = 'true';
        }

        $digestAlgorithm = self::attr($table, self::TABLE_NS, 'protection-key-digest-algorithm');
        if ($digestAlgorithm !== '') {
            $attrs['protectionKeyDigestAlgorithm'] = $digestAlgorithm;
            $attrs['htmlAttributes']['data-odf-table-protection-key-digest-algorithm'] = $digestAlgorithm;
        }
        if ($columnWidths !== []) {
            $attrs['widths'] = $columnWidths;
        }
        if ($columnDefinitions !== []) {
            $summary = $this->tableColumnSummary($columnDefinitions);
            $attrs['odfTableColumns'] = $columnDefinitions;
            $attrs['odfTableColumnSummary'] = $summary;
            $attrs['htmlAttributes']['data-odf-table-column-count'] = (string) $summary['count'];
            if ($summary['hiddenCount'] > 0) {
                $attrs['htmlAttributes']['data-odf-table-hidden-column-count'] = (string) $summary['hiddenCount'];
            }
            if ($summary['repeatedColumnCount'] > 0) {
                $attrs['htmlAttributes']['data-odf-table-repeated-column-count'] = (string) $summary['repeatedColumnCount'];
            }
        }

        return TableGeometry::withReviewPacket(new AstNode('table', $attrs, $children), [
            'idPrefix' => $tableName === '' ? 'odf-table' : $tableName,
        ]);
    }

    /**
     * @param array{styles:array<string, array<string, mixed>>, listStyles:array<string, array<string, mixed>>} $catalog
     * @param list<array<string, mixed>> $columnDefinitions
     * @return list<AstNode>
     */
    private function repeatedRows(\DOMElement $row, ZipPackage $package, array $catalog, array $columnDefinitions): array
    {
        $declaredRepeat = max(1, self::intAttr($row, self::TABLE_NS, 'number-rows-repeated', 1));
        $repeat = min(32, $declaredRepeat);
        $rows = [];
        for ($index = 0; $index < $repeat; $index++) {
            $rows[] = $this->tableRowNode($row, $package, $catalog, $columnDefinitions, [
                'repeatIndex' => $repeat > 1 ? $index + 1 : null,
                'sourceRepeat' => $repeat > 1 ? $repeat : null,
                'declaredRepeat' => $declaredRepeat > 1 ? $declaredRepeat : null,
                'repeatTruncated' => $declaredRepeat > $repeat ? true : null,
            ]);
        }

        return $rows;
    }

    /**
     * @param array{styles:array<string, array<string, mixed>>, listStyles:array<string, array<string, mixed>>} $catalog
     * @param list<array<string, mixed>> $columnDefinitions
     * @param array{repeatIndex?:int|null,sourceRepeat?:int|null,declaredRepeat?:int|null,repeatTruncated?:bool|null} $repeatMetadata
     */
    private function tableRowNode(\DOMElement $row, ZipPackage $package, array $catalog, array $columnDefinitions, array $repeatMetadata = []): AstNode
    {
        $cells = [];
        $rowCoveredCells = [];
        $columnIndex = 0;
        $lastCellStartColumn = null;
        $lastCellColspan = 1;
        $lastCellCoveredOffset = 0;
        $rowDefaultCellStyleName = self::attr($row, self::TABLE_NS, 'default-cell-style-name');
        foreach (self::childElements($row) as $cellElement) {
            if ($this->isElement($cellElement, self::TABLE_NS, 'covered-table-cell')) {
                $declaredRepeat = max(1, self::intAttr($cellElement, self::TABLE_NS, 'number-columns-repeated', 1));
                $repeat = min(32, $declaredRepeat);
                for ($index = 0; $index < $repeat; $index++) {
                    $coversPreviousCell = $lastCellStartColumn !== null && $lastCellCoveredOffset < max(0, $lastCellColspan - 1);
                    $sourceColumn = $coversPreviousCell
                        ? $lastCellStartColumn + 1 + $lastCellCoveredOffset
                        : $columnIndex;
                    $metadata = $this->coveredTableCellMetadata($cellElement, $package, $catalog, [
                        'sourceColumn' => $sourceColumn,
                        'repeatIndex' => $repeat > 1 ? $index + 1 : null,
                        'sourceRepeat' => $repeat > 1 ? $repeat : null,
                        'declaredRepeat' => $declaredRepeat > 1 ? $declaredRepeat : null,
                        'repeatTruncated' => $declaredRepeat > $repeat ? true : null,
                    ]);

                    if ($coversPreviousCell && $this->appendCoveredTableCellMetadata($cells, $metadata)) {
                        $lastCellCoveredOffset++;
                        continue;
                    }

                    $rowCoveredCells[] = $metadata;
                    $columnIndex++;
                    if ($coversPreviousCell) {
                        $lastCellCoveredOffset++;
                    }
                }
                continue;
            }
            if (!$this->isElement($cellElement, self::TABLE_NS, 'table-cell')) {
                continue;
            }

            $repeat = min(32, max(1, self::intAttr($cellElement, self::TABLE_NS, 'number-columns-repeated', 1)));
            $colspan = max(1, self::intAttr($cellElement, self::TABLE_NS, 'number-columns-spanned', 1));
            for ($index = 0; $index < $repeat; $index++) {
                $cellStartColumn = $columnIndex;
                $cells[] = $this->tableCellNode($cellElement, $package, $catalog, [
                    'rowDefaultCellStyleName' => $rowDefaultCellStyleName,
                    'columnDefaultCellStyleName' => $this->columnDefaultCellStyleName($columnDefinitions, $columnIndex),
                ]);
                $columnIndex += $colspan;
                $lastCellStartColumn = $cellStartColumn;
                $lastCellColspan = $colspan;
                $lastCellCoveredOffset = 0;
            }
        }

        $attrs = $this->tableRowMetadata($row, $repeatMetadata);
        if ($rowCoveredCells !== []) {
            $attrs['odfCoveredCells'] = $rowCoveredCells;
            $attrs['coveredCellCount'] = count($rowCoveredCells);
            if ($this->coveredTableCellsHaveReviewMetadata($rowCoveredCells)) {
                $attrs['htmlAttributes'] = array_merge(
                    is_array($attrs['htmlAttributes'] ?? null) ? $attrs['htmlAttributes'] : [],
                    $this->coveredTableCellHtmlAttributes($rowCoveredCells),
                );
                $attrs['classes'] = array_values(array_unique(array_merge(
                    is_array($attrs['classes'] ?? null) ? $attrs['classes'] : [],
                    ['odf-covered-table-row'],
                )));
            }
        }

        return new AstNode('table_row', $attrs, $cells);
    }

    /**
     * @param array{styles:array<string, array<string, mixed>>, listStyles:array<string, array<string, mixed>>} $catalog
     * @param array{sourceColumn:int,repeatIndex?:int|null,sourceRepeat?:int|null,declaredRepeat?:int|null,repeatTruncated?:bool|null} $sourceMetadata
     * @return array<string, mixed>
     */
    private function coveredTableCellMetadata(\DOMElement $cell, ZipPackage $package, array $catalog, array $sourceMetadata): array
    {
        $blocks = $this->blockNodes($cell, $package, $catalog);
        $styleName = self::attr($cell, self::TABLE_NS, 'style-name');
        $style = $this->resolveStyle($styleName, $catalog);
        $styleProperties = is_array($style['tableCellProperties'] ?? null) ? $style['tableCellProperties'] : [];
        $metadata = $this->tableCellMetadata($cell);

        return self::withoutEmpty([
            'element' => 'covered-table-cell',
            'sourceColumn' => $sourceMetadata['sourceColumn'],
            'repeatIndex' => $sourceMetadata['repeatIndex'] ?? null,
            'sourceRepeat' => $sourceMetadata['sourceRepeat'] ?? null,
            'declaredRepeat' => $sourceMetadata['declaredRepeat'] ?? null,
            'repeatTruncated' => ($sourceMetadata['repeatTruncated'] ?? false) === true ? true : null,
            'styleName' => self::nullable($styleName),
            'style' => $style !== [] ? $style : null,
            'styleProperties' => $styleProperties !== [] ? $styleProperties : null,
            'cellMetadata' => $metadata !== [] ? $metadata : null,
            'text' => self::nullable($this->plainBlockText($blocks)),
        ]);
    }

    /**
     * @param list<AstNode> $cells
     * @param array<string, mixed> $metadata
     */
    private function appendCoveredTableCellMetadata(array &$cells, array $metadata): bool
    {
        $lastIndex = array_key_last($cells);
        if ($lastIndex === null) {
            return false;
        }

        $cell = $cells[$lastIndex];
        $coveredCells = $cell->attr('odfCoveredCells', []);
        if (!is_array($coveredCells) || !array_is_list($coveredCells)) {
            $coveredCells = [];
        }
        $coveredCells[] = $metadata;

        $attrs = $cell->attrs;
        $attrs['odfCoveredCells'] = $coveredCells;
        $attrs['coveredCellCount'] = count($coveredCells);
        if ($this->coveredTableCellsHaveReviewMetadata($coveredCells)) {
            $attrs['htmlAttributes'] = array_merge(
                is_array($attrs['htmlAttributes'] ?? null) ? $attrs['htmlAttributes'] : [],
                $this->coveredTableCellHtmlAttributes($coveredCells),
            );
            $attrs['classes'] = array_values(array_unique(array_merge(
                is_array($attrs['classes'] ?? null) ? $attrs['classes'] : [],
                ['odf-covered-cell-source'],
            )));
        }

        $cells[$lastIndex] = new AstNode($cell->type, $attrs, $cell->children);

        return true;
    }

    /**
     * @param list<array<string, mixed>> $coveredCells
     */
    private function coveredTableCellsHaveReviewMetadata(array $coveredCells): bool
    {
        foreach ($coveredCells as $metadata) {
            if (!is_array($metadata)) {
                continue;
            }
            if ($this->coveredTableCellHasReviewMetadata($metadata)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<string, mixed> $metadata
     */
    private function coveredTableCellHasReviewMetadata(array $metadata): bool
    {
        foreach (['styleName', 'styleProperties', 'cellMetadata', 'text', 'sourceRepeat', 'declaredRepeat', 'repeatTruncated'] as $key) {
            if (array_key_exists($key, $metadata)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param list<array<string, mixed>> $coveredCells
     * @return array<string, string>
     */
    private function coveredTableCellHtmlAttributes(array $coveredCells): array
    {
        $sourceColumns = [];
        $styleNames = [];
        $textCount = 0;
        $valueCount = 0;
        $repeatedCount = 0;
        $truncatedRepeatCount = 0;

        foreach ($coveredCells as $metadata) {
            if (!is_array($metadata)) {
                continue;
            }
            if (isset($metadata['sourceColumn']) && is_numeric($metadata['sourceColumn'])) {
                $sourceColumns[] = (string) ((int) $metadata['sourceColumn']);
            }
            $styleName = (string) ($metadata['styleName'] ?? '');
            if ($styleName !== '') {
                $styleNames[] = $styleName;
            }
            if ((string) ($metadata['text'] ?? '') !== '') {
                $textCount++;
            }
            if (is_array($metadata['cellMetadata'] ?? null) && $metadata['cellMetadata'] !== []) {
                $valueCount++;
            }
            if (is_numeric($metadata['sourceRepeat'] ?? null) && (int) $metadata['sourceRepeat'] > 1) {
                $repeatedCount++;
            }
            if (($metadata['repeatTruncated'] ?? false) === true) {
                $truncatedRepeatCount++;
            }
        }

        return self::withoutEmpty([
            'data-odf-covered-cell-count' => (string) count($coveredCells),
            'data-odf-covered-cell-source-columns' => $sourceColumns === [] ? null : implode(',', array_values(array_unique($sourceColumns))),
            'data-odf-covered-cell-style-names' => $styleNames === [] ? null : implode(',', array_values(array_unique($styleNames))),
            'data-odf-covered-cell-text-count' => $textCount > 0 ? (string) $textCount : null,
            'data-odf-covered-cell-value-count' => $valueCount > 0 ? (string) $valueCount : null,
            'data-odf-covered-cell-repeated-count' => $repeatedCount > 0 ? (string) $repeatedCount : null,
            'data-odf-covered-cell-truncated-repeat-count' => $truncatedRepeatCount > 0 ? (string) $truncatedRepeatCount : null,
        ]);
    }

    /**
     * @param array{repeatIndex?:int|null,sourceRepeat?:int|null,declaredRepeat?:int|null,repeatTruncated?:bool|null} $repeatMetadata
     * @return array<string, mixed>
     */
    private function tableRowMetadata(\DOMElement $row, array $repeatMetadata): array
    {
        $styleName = self::attr($row, self::TABLE_NS, 'style-name');
        $defaultCellStyleName = self::attr($row, self::TABLE_NS, 'default-cell-style-name');
        $visibility = self::attr($row, self::TABLE_NS, 'visibility');
        $hidden = in_array(strtolower($visibility), ['collapse', 'filter'], true);

        $metadata = self::withoutEmpty([
            'styleName' => self::nullable($styleName),
            'defaultCellStyleName' => self::nullable($defaultCellStyleName),
            'visibility' => self::nullable($visibility),
            'hidden' => $hidden ? true : null,
            'repeatIndex' => $repeatMetadata['repeatIndex'] ?? null,
            'sourceRepeat' => $repeatMetadata['sourceRepeat'] ?? null,
            'declaredRepeat' => $repeatMetadata['declaredRepeat'] ?? null,
            'repeatTruncated' => ($repeatMetadata['repeatTruncated'] ?? false) === true ? true : null,
        ]);
        if ($metadata === []) {
            return [];
        }

        $htmlAttributes = self::withoutEmpty([
            'data-odf-row-style-name' => self::nullable($styleName),
            'data-odf-row-default-cell-style-name' => self::nullable($defaultCellStyleName),
            'data-odf-row-visibility' => self::nullable($visibility),
            'data-odf-row-hidden' => $hidden ? 'true' : null,
            'data-odf-row-repeat-index' => isset($metadata['repeatIndex']) ? (string) $metadata['repeatIndex'] : null,
            'data-odf-row-source-repeat' => isset($metadata['sourceRepeat']) ? (string) $metadata['sourceRepeat'] : null,
            'data-odf-row-declared-repeat' => isset($metadata['declaredRepeat']) ? (string) $metadata['declaredRepeat'] : null,
            'data-odf-row-repeat-truncated' => ($metadata['repeatTruncated'] ?? false) === true ? 'true' : null,
        ]);

        $classes = [];
        if ($hidden) {
            $classes[] = 'odf-hidden-table-row';
        }
        if (isset($metadata['sourceRepeat']) && (int) $metadata['sourceRepeat'] > 1) {
            $classes[] = 'odf-repeated-table-row';
        }

        $attrs = $metadata;
        $attrs['odfTableRowMetadata'] = $metadata;
        $attrs['htmlAttributes'] = $htmlAttributes;
        if ($classes !== []) {
            $attrs['classes'] = $classes;
        }

        return $attrs;
    }

    /**
     * @param list<array<string, mixed>> $columnDefinitions
     */
    private function columnDefaultCellStyleName(array $columnDefinitions, int $columnIndex): string
    {
        $column = $columnDefinitions[$columnIndex] ?? null;
        if (!is_array($column)) {
            return '';
        }

        return is_string($column['defaultCellStyleName'] ?? null) ? $column['defaultCellStyleName'] : '';
    }

    /**
     * @param array{rowDefaultCellStyleName?:string,columnDefaultCellStyleName?:string} $defaultStyles
     * @return array{name:string,source:string}|array{}
     */
    private function effectiveDefaultCellStyle(array $defaultStyles): array
    {
        $rowDefault = (string) ($defaultStyles['rowDefaultCellStyleName'] ?? '');
        if ($rowDefault !== '') {
            return [
                'name' => $rowDefault,
                'source' => 'row',
            ];
        }

        $columnDefault = (string) ($defaultStyles['columnDefaultCellStyleName'] ?? '');
        if ($columnDefault !== '') {
            return [
                'name' => $columnDefault,
                'source' => 'column',
            ];
        }

        return [];
    }

    /**
     * @param array{styles:array<string, array<string, mixed>>, listStyles:array<string, array<string, mixed>>} $catalog
     * @param array{rowDefaultCellStyleName?:string,columnDefaultCellStyleName?:string} $defaultStyles
     */
    private function tableCellNode(\DOMElement $cell, ZipPackage $package, array $catalog, array $defaultStyles = []): AstNode
    {
        $annotations = $this->tableCellAnnotations($cell, $package, $catalog);
        $blocks = $this->blockNodes($cell, $package, $catalog, $annotations !== []);
        $attrs = [
            'sourceFormat' => 'odt',
            'text' => $this->plainBlockText($blocks),
        ];
        $colspan = self::intAttr($cell, self::TABLE_NS, 'number-columns-spanned', 1);
        $rowspan = self::intAttr($cell, self::TABLE_NS, 'number-rows-spanned', 1);
        $styleName = self::attr($cell, self::TABLE_NS, 'style-name');
        $defaultStyle = $styleName === '' ? $this->effectiveDefaultCellStyle($defaultStyles) : [];
        if ($styleName === '' && isset($defaultStyle['name'])) {
            $styleName = (string) $defaultStyle['name'];
        }
        $style = $this->resolveStyle($styleName, $catalog);
        if ($colspan > 1) {
            $attrs['colspan'] = $colspan;
        }
        if ($rowspan > 1) {
            $attrs['rowspan'] = $rowspan;
        }
        if ($styleName !== '') {
            $attrs['styleName'] = $styleName;
            if ($style !== []) {
                $attrs['style'] = $style;
            }
            if ($defaultStyle !== []) {
                $attrs['defaultCellStyleName'] = (string) $defaultStyle['name'];
                $attrs['defaultCellStyleSource'] = (string) $defaultStyle['source'];
            }
        }
        $htmlAttributes = [];
        $classes = [];
        $metadata = $this->tableCellMetadata($cell);
        if ($metadata !== []) {
            $attrs['odfCellMetadata'] = $metadata;
            $htmlAttributes = $this->tableCellMetadataHtmlAttributes($metadata);
            if ($this->tableCellMetadataHasTypedValue($metadata)) {
                $classes[] = 'odf-table-cell-value';
            }
            if (isset($metadata['formula'])) {
                $classes[] = 'odf-table-cell-formula';
            }
            if (isset($metadata['contentValidationName'])) {
                $classes[] = 'odf-table-cell-validation';
                $validation = $this->contentValidationByName((string) $metadata['contentValidationName']);
                if ($validation !== null) {
                    $attrs['odfContentValidation'] = $validation;
                }
                $htmlAttributes = array_merge(
                    $htmlAttributes,
                    $this->tableCellContentValidationHtmlAttributes($validation)
                );
            }
        }
        if ($annotations !== []) {
            $attrs['odfCellAnnotations'] = $annotations;
            $attrs['annotationCount'] = count($annotations);
            $htmlAttributes = array_merge(
                $htmlAttributes,
                $this->tableCellAnnotationHtmlAttributes($annotations),
            );
            $classes[] = 'odf-table-cell-annotation';
        }

        $styleProperties = is_array($style['tableCellProperties'] ?? null) ? $style['tableCellProperties'] : [];
        if ($styleProperties !== []) {
            $attrs['odfCellStyleProperties'] = $styleProperties;
            $htmlAttributes = array_merge(
                $htmlAttributes,
                $this->tableCellStyleHtmlAttributes($styleName, $styleProperties),
            );
            if ($defaultStyle !== []) {
                $htmlAttributes['data-odf-cell-default-style-name'] = (string) $defaultStyle['name'];
                $htmlAttributes['data-odf-cell-default-style-source'] = (string) $defaultStyle['source'];
            }
            array_push($classes, ...$this->tableCellStyleClasses($styleProperties));
        }
        $styleMaps = is_array($style['styleMaps'] ?? null) ? $style['styleMaps'] : [];
        if ($styleMaps !== []) {
            $attrs['odfCellStyleMaps'] = $styleMaps;
            $htmlAttributes = array_merge(
                $htmlAttributes,
                $this->styleMapHtmlAttributes('data-odf-cell-style-map', $styleMaps),
            );
            $classes[] = 'odf-table-cell-style-map';
        }

        if ($htmlAttributes !== []) {
            $attrs['htmlAttributes'] = $htmlAttributes;
        }
        if ($classes !== []) {
            $attrs['classes'] = array_values(array_unique($classes));
        }

        return new AstNode('table_cell', $attrs, $blocks);
    }

    /**
     * @param array{styles:array<string, array<string, mixed>>, listStyles:array<string, array<string, mixed>>} $catalog
     * @return list<array<string, mixed>>
     */
    private function tableCellAnnotations(\DOMElement $cell, ZipPackage $package, array $catalog): array
    {
        $annotations = [];
        foreach (self::childElements($cell, 'annotation', self::OFFICE_NS) as $annotation) {
            $metadata = $this->annotationMetadata($annotation);
            $blocks = $this->blockNodes($annotation, $package, $catalog);
            $text = $this->plainBlockText($blocks);
            $name = self::attr($annotation, self::OFFICE_NS, 'name');

            $entry = self::withoutEmpty([
                'name' => self::nullable($name),
                'author' => self::nullable($metadata['author']),
                'date' => self::nullable($metadata['date']),
                'text' => self::nullable($text),
                'blockCount' => $blocks === [] ? null : count($blocks),
            ]);
            if ($entry !== []) {
                $annotations[] = $entry;
            }
        }

        return $annotations;
    }

    /**
     * @param list<array<string, mixed>> $annotations
     * @return array<string, string>
     */
    private function tableCellAnnotationHtmlAttributes(array $annotations): array
    {
        $authors = [];
        $dates = [];
        $textCount = 0;
        foreach ($annotations as $annotation) {
            $author = (string) ($annotation['author'] ?? '');
            if ($author !== '') {
                $authors[] = $author;
            }
            $date = (string) ($annotation['date'] ?? '');
            if ($date !== '') {
                $dates[] = $date;
            }
            if ((string) ($annotation['text'] ?? '') !== '') {
                $textCount++;
            }
        }

        return self::withoutEmpty([
            'data-odf-cell-annotation-count' => (string) count($annotations),
            'data-odf-cell-annotation-authors' => $authors === [] ? null : implode(',', array_values(array_unique($authors))),
            'data-odf-cell-annotation-dates' => $dates === [] ? null : implode(',', array_values(array_unique($dates))),
            'data-odf-cell-annotation-text-count' => $textCount > 0 ? (string) $textCount : null,
        ]);
    }

    /**
     * @return array<string, bool|string>
     */
    private function tableCellMetadata(\DOMElement $cell): array
    {
        return self::withoutEmpty([
            'formula' => self::nullable(self::attr($cell, self::TABLE_NS, 'formula')),
            'valueType' => self::nullable(self::attr($cell, self::OFFICE_NS, 'value-type')),
            'value' => self::nullable(self::attr($cell, self::OFFICE_NS, 'value')),
            'currency' => self::nullable(self::attr($cell, self::OFFICE_NS, 'currency')),
            'stringValue' => self::nullable(self::attr($cell, self::OFFICE_NS, 'string-value')),
            'dateValue' => self::nullable(self::attr($cell, self::OFFICE_NS, 'date-value')),
            'timeValue' => self::nullable(self::attr($cell, self::OFFICE_NS, 'time-value')),
            'booleanValue' => self::nullableBool(self::attr($cell, self::OFFICE_NS, 'boolean-value')),
            'contentValidationName' => self::nullable(self::attr($cell, self::TABLE_NS, 'content-validation-name')),
        ]);
    }

    /**
     * @param array<string, bool|string> $metadata
     * @return array<string, string>
     */
    private function tableCellMetadataHtmlAttributes(array $metadata): array
    {
        $attributes = [];
        $map = [
            'formula' => 'data-odf-cell-formula',
            'valueType' => 'data-odf-cell-value-type',
            'value' => 'data-odf-cell-value',
            'currency' => 'data-odf-cell-currency',
            'stringValue' => 'data-odf-cell-string-value',
            'dateValue' => 'data-odf-cell-date-value',
            'timeValue' => 'data-odf-cell-time-value',
            'booleanValue' => 'data-odf-cell-boolean-value',
            'contentValidationName' => 'data-odf-cell-content-validation-name',
        ];

        foreach ($map as $source => $target) {
            if (!array_key_exists($source, $metadata)) {
                continue;
            }

            $value = $metadata[$source];
            $attributes[$target] = is_bool($value) ? ($value ? 'true' : 'false') : $value;
        }

        return $attributes;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function contentValidationByName(string $name): ?array
    {
        if ($name === '') {
            return null;
        }

        $validationsByName = $this->contentDeclarations['contentValidationsByName'] ?? null;
        if (!is_array($validationsByName) || !is_array($validationsByName[$name] ?? null)) {
            return null;
        }

        return $validationsByName[$name];
    }

    /**
     * @param array<string, mixed>|null $validation
     * @return array<string, string>
     */
    private function tableCellContentValidationHtmlAttributes(?array $validation): array
    {
        $attributes = [
            'data-odf-cell-content-validation-exists' => $validation === null ? 'false' : 'true',
        ];
        if ($validation === null) {
            return $attributes;
        }

        $condition = $validation['condition'] ?? null;
        if (is_scalar($condition) && (string) $condition !== '') {
            $attributes['data-odf-cell-content-validation-condition'] = (string) $condition;
        }

        $allowEmptyCell = $validation['allowEmptyCell'] ?? null;
        if (is_bool($allowEmptyCell)) {
            $attributes['data-odf-cell-content-validation-allow-empty-cell'] = $allowEmptyCell ? 'true' : 'false';
        }

        return $attributes;
    }

    /**
     * @param array<string, bool|string> $metadata
     */
    private function tableCellMetadataHasTypedValue(array $metadata): bool
    {
        foreach (['valueType', 'value', 'currency', 'stringValue', 'dateValue', 'timeValue', 'booleanValue'] as $key) {
            if (array_key_exists($key, $metadata)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return list<string>
     */
    private function tablePrintRanges(\DOMElement $table): array
    {
        $raw = trim(self::attr($table, self::TABLE_NS, 'print-ranges'));
        if ($raw === '') {
            return [];
        }

        $ranges = preg_split('/\s+/', $raw, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $normalized = [];
        foreach ($ranges as $range) {
            $range = trim($range);
            if ($range !== '') {
                $normalized[] = $range;
            }
        }

        return array_values(array_unique($normalized));
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function tableScenarios(\DOMElement $table): array
    {
        $scenarios = [];
        foreach (self::childElements($table, 'scenario', self::TABLE_NS) as $scenario) {
            $definition = $this->tableScenarioDefinition($scenario);
            if ($definition !== []) {
                $scenarios[] = $definition;
            }
        }

        return $scenarios;
    }

    /**
     * @return array<string, mixed>
     */
    private function tableScenarioDefinition(\DOMElement $scenario): array
    {
        $ranges = $this->tableScenarioRanges(self::attr($scenario, self::TABLE_NS, 'scenario-ranges'));

        return self::withoutEmpty([
            'name' => self::nullable(self::attr($scenario, self::TABLE_NS, 'name')),
            'displayBorder' => self::nullableBool(self::attr($scenario, self::TABLE_NS, 'display-border')),
            'borderColor' => self::nullable(self::attr($scenario, self::TABLE_NS, 'border-color')),
            'copyBack' => self::nullableBool(self::attr($scenario, self::TABLE_NS, 'copy-back')),
            'copyStyles' => self::nullableBool(self::attr($scenario, self::TABLE_NS, 'copy-styles')),
            'copyFormulas' => self::nullableBool(self::attr($scenario, self::TABLE_NS, 'copy-formulas')),
            'isActive' => self::nullableBool(self::attr($scenario, self::TABLE_NS, 'is-active')),
            'scenarioRanges' => $ranges === [] ? null : $ranges,
            'comment' => self::nullable(self::attr($scenario, self::TABLE_NS, 'comment')),
        ]);
    }

    /**
     * @return list<string>
     */
    private function tableScenarioRanges(string $raw): array
    {
        $raw = trim($raw);
        if ($raw === '') {
            return [];
        }

        $ranges = preg_split('/\s+/', $raw, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $normalized = [];
        foreach ($ranges as $range) {
            $range = trim($range);
            if ($range !== '') {
                $normalized[] = $range;
            }
        }

        return array_values(array_unique($normalized));
    }

    /**
     * @param list<array<string, mixed>> $scenarios
     */
    private function activeTableScenarioCount(array $scenarios): int
    {
        $activeCount = 0;
        foreach ($scenarios as $scenario) {
            if (($scenario['isActive'] ?? false) === true) {
                $activeCount++;
            }
        }

        return $activeCount;
    }

    /**
     * @param list<array<string, mixed>> $scenarios
     * @return array<string, string>
     */
    private function tableScenarioHtmlAttributes(array $scenarios): array
    {
        $names = [];
        $ranges = [];
        foreach ($scenarios as $scenario) {
            $name = (string) ($scenario['name'] ?? '');
            if ($name !== '') {
                $names[] = $name;
            }
            $scenarioRanges = $scenario['scenarioRanges'] ?? [];
            if (is_array($scenarioRanges)) {
                foreach ($scenarioRanges as $range) {
                    if (is_scalar($range) && (string) $range !== '') {
                        $ranges[] = (string) $range;
                    }
                }
            }
        }

        return self::withoutEmpty([
            'data-odf-table-scenario-count' => (string) count($scenarios),
            'data-odf-table-active-scenario-count' => (string) $this->activeTableScenarioCount($scenarios),
            'data-odf-table-scenario-names' => $names === [] ? null : implode(',', array_values(array_unique($names))),
            'data-odf-table-scenario-ranges' => $ranges === [] ? null : implode(';', array_values(array_unique($ranges))),
        ]);
    }

    /**
     * @param array<string, mixed> $properties
     * @return array<string, string>
     */
    private function tableCellStyleHtmlAttributes(string $styleName, array $properties): array
    {
        $attributes = [];
        if ($styleName !== '') {
            $attributes['data-odf-cell-style-name'] = $styleName;
        }

        foreach ([
            'backgroundColor' => 'data-odf-cell-background-color',
            'verticalAlign' => 'data-odf-cell-vertical-align',
            'writingMode' => 'data-odf-cell-writing-mode',
            'cellProtect' => 'data-odf-cell-protect',
            'printContent' => 'data-odf-cell-print-content',
            'repeatContent' => 'data-odf-cell-repeat-content',
            'shrinkToFit' => 'data-odf-cell-shrink-to-fit',
        ] as $source => $target) {
            if (!array_key_exists($source, $properties)) {
                continue;
            }

            $value = $properties[$source];
            if (is_bool($value)) {
                $attributes[$target] = $value ? 'true' : 'false';
                continue;
            }
            if (is_scalar($value)) {
                $attributes[$target] = (string) $value;
            }
        }

        $style = $this->tableCellStyleCss($properties);
        if ($style !== '') {
            $attributes['style'] = $style;
        }

        return $attributes;
    }

    /**
     * @param list<array<string, string>> $styleMaps
     * @return array<string, string>
     */
    private function styleMapHtmlAttributes(string $prefix, array $styleMaps): array
    {
        $attributes = [
            $prefix . '-count' => (string) count($styleMaps),
        ];
        foreach ($styleMaps as $index => $styleMap) {
            $number = $index + 1;
            foreach ([
                'condition' => 'condition',
                'applyStyleName' => 'apply-style-name',
                'baseCellAddress' => 'base-cell-address',
            ] as $source => $target) {
                $value = $styleMap[$source] ?? '';
                if ($value !== '') {
                    $attributes[$prefix . '-' . $number . '-' . $target] = $value;
                }
            }
        }

        return $attributes;
    }

    /**
     * @param array<string, mixed> $properties
     * @return list<string>
     */
    private function tableCellStyleClasses(array $properties): array
    {
        $classes = ['odf-table-cell-style'];
        $background = (string) ($properties['backgroundColor'] ?? '');
        if ($background !== '') {
            $classes[] = 'odf-table-cell-background';
        }
        if ((string) ($properties['cellProtect'] ?? '') !== '') {
            $classes[] = 'odf-table-cell-protected';
        }
        if (($properties['printContent'] ?? null) === false) {
            $classes[] = 'odf-table-cell-print-hidden';
        }

        $verticalAlign = strtolower((string) ($properties['verticalAlign'] ?? ''));
        if (in_array($verticalAlign, ['baseline', 'top', 'middle', 'bottom'], true)) {
            $classes[] = 'odf-table-cell-vertical-align-' . $verticalAlign;
        }

        return $classes;
    }

    /**
     * @param array<string, mixed> $properties
     */
    private function tableCellStyleCss(array $properties): string
    {
        $styles = [];
        $backgroundColor = (string) ($properties['backgroundColor'] ?? '');
        if ($this->isSafeCssColor($backgroundColor)) {
            $styles[] = 'background-color:' . $backgroundColor;
        }

        $verticalAlign = strtolower((string) ($properties['verticalAlign'] ?? ''));
        if (in_array($verticalAlign, ['baseline', 'top', 'middle', 'bottom'], true)) {
            $styles[] = 'vertical-align:' . $verticalAlign;
        }

        $border = (string) ($properties['border'] ?? '');
        if ($this->isSafeCssBorder($border)) {
            $styles[] = 'border:' . $border;
        }

        foreach ([
            'padding' => 'padding',
            'paddingTop' => 'padding-top',
            'paddingRight' => 'padding-right',
            'paddingBottom' => 'padding-bottom',
            'paddingLeft' => 'padding-left',
        ] as $source => $target) {
            $value = (string) ($properties[$source] ?? '');
            if ($this->isSafeCssLength($value)) {
                $styles[] = $target . ':' . $value;
            }
        }

        return implode('; ', $styles);
    }

    private function isSafeCssColor(string $value): bool
    {
        $value = trim($value);
        if ($value === '') {
            return false;
        }

        return strtolower($value) === 'transparent'
            || preg_match('/^#[0-9A-Fa-f]{3}(?:[0-9A-Fa-f]{3})?(?:[0-9A-Fa-f]{2})?$/', $value) === 1;
    }

    private function isSafeCssLength(string $value): bool
    {
        $value = trim($value);
        if ($value === '') {
            return false;
        }

        return preg_match('/^(?:0|[0-9]+(?:\.[0-9]+)?(?:cm|mm|in|pt|pc|px|em|rem|%)?)$/i', $value) === 1;
    }

    private function isSafeCssBorder(string $value): bool
    {
        $value = trim($value);
        if ($value === '') {
            return false;
        }

        $parts = preg_split('/\s+/', $value, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        if ($parts === [] || count($parts) > 3) {
            return false;
        }

        $allowedStyles = ['none', 'hidden', 'dotted', 'dashed', 'solid', 'double', 'groove', 'ridge', 'inset', 'outset'];
        foreach ($parts as $part) {
            $normalized = strtolower($part);
            if (in_array($normalized, $allowedStyles, true)
                || $this->isSafeCssLength($part)
                || $this->isSafeCssColor($part)
            ) {
                continue;
            }

            return false;
        }

        return true;
    }

    /**
     * @param array{styles:array<string, array<string, mixed>>, listStyles:array<string, array<string, mixed>>} $catalog
     * @return list<array<string, mixed>>
     */
    private function tableColumnDefinitions(\DOMElement $table, array $catalog): array
    {
        $columns = [];
        $sourceIndex = 0;
        foreach (self::childElements($table, 'table-column', self::TABLE_NS) as $column) {
            $sourceIndex++;
            $declaredRepeat = max(1, self::intAttr($column, self::TABLE_NS, 'number-columns-repeated', 1));
            $repeat = min(32, $declaredRepeat);
            $styleName = self::attr($column, self::TABLE_NS, 'style-name');
            $style = $this->resolveStyle($styleName, $catalog);
            $columnProperties = is_array($style['tableColumnProperties'] ?? null) ? $style['tableColumnProperties'] : [];
            $width = (string) ($columnProperties['columnWidth'] ?? '');
            $widthPoints = $this->lengthToPoints($width);
            $visibility = self::attr($column, self::TABLE_NS, 'visibility');
            $defaultCellStyleName = self::attr($column, self::TABLE_NS, 'default-cell-style-name');
            $hidden = in_array(strtolower($visibility), ['collapse', 'filter'], true);

            for ($index = 0; $index < $repeat; $index++) {
                $columns[] = self::withoutEmpty([
                    'index' => count($columns) + 1,
                    'sourceIndex' => $sourceIndex,
                    'repeatIndex' => $repeat > 1 ? $index + 1 : null,
                    'sourceRepeat' => $repeat > 1 ? $repeat : null,
                    'declaredRepeat' => $declaredRepeat > $repeat ? $declaredRepeat : null,
                    'repeatTruncated' => $declaredRepeat > $repeat ? true : null,
                    'styleName' => self::nullable($styleName),
                    'defaultCellStyleName' => self::nullable($defaultCellStyleName),
                    'visibility' => self::nullable($visibility),
                    'hidden' => $hidden,
                    'width' => $width === '' ? null : $width,
                    'widthPoints' => $widthPoints,
                    'relativeWidth' => $columnProperties['relativeColumnWidth'] ?? null,
                    'useOptimalWidth' => $columnProperties['useOptimalColumnWidth'] ?? null,
                ]);
            }
        }

        return $columns;
    }

    /**
     * @param list<array<string, mixed>> $columns
     * @return list<float>
     */
    private function tableColumnWidths(array $columns): array
    {
        $widths = array_map(
            static fn (array $column): ?float => isset($column['widthPoints']) && is_numeric($column['widthPoints'])
                ? (float) $column['widthPoints']
                : null,
            $columns
        );
        $positive = array_values(array_filter($widths, static fn (?float $width): bool => $width !== null && $width > 0.0));
        if ($positive === []) {
            return [];
        }

        $total = array_sum($positive);
        if ($total <= 0.0) {
            return [];
        }

        return array_map(static fn (float $width): float => $width / $total, $positive);
    }

    /**
     * @param list<array<string, mixed>> $columns
     * @return array{count:int, sourceCount:int, hiddenCount:int, repeatedColumnCount:int, truncatedRepeatCount:int}
     */
    private function tableColumnSummary(array $columns): array
    {
        $sourceIndexes = [];
        $hiddenCount = 0;
        $repeatedColumnCount = 0;
        $truncatedRepeatCount = 0;
        foreach ($columns as $column) {
            if (isset($column['sourceIndex']) && is_scalar($column['sourceIndex'])) {
                $sourceIndexes[(string) $column['sourceIndex']] = true;
            }
            if (($column['hidden'] ?? false) === true) {
                $hiddenCount++;
            }
            if (isset($column['sourceRepeat']) && is_numeric($column['sourceRepeat']) && (int) $column['sourceRepeat'] > 1) {
                $repeatedColumnCount++;
            }
            if (($column['repeatTruncated'] ?? false) === true) {
                $truncatedRepeatCount++;
            }
        }

        return [
            'count' => count($columns),
            'sourceCount' => count($sourceIndexes),
            'hiddenCount' => $hiddenCount,
            'repeatedColumnCount' => $repeatedColumnCount,
            'truncatedRepeatCount' => $truncatedRepeatCount,
        ];
    }

    /**
     * @param array{styles:array<string, array<string, mixed>>, listStyles:array<string, array<string, mixed>>} $catalog
     */
    private function frameBlockNode(\DOMElement $frame, ZipPackage $package, array $catalog): ?AstNode
    {
        $textBoxCaptionImage = $this->frameTextBoxCaptionImageNode($frame, $catalog, $package);
        if ($textBoxCaptionImage instanceof AstNode) {
            return new AstNode('figure', [
                'sourceFormat' => 'odt',
                'caption' => (string) $textBoxCaptionImage->attr('alt', ''),
            ], [$textBoxCaptionImage]);
        }

        $textBox = self::firstChildElement($frame, 'text-box', self::DRAW_NS);
        if ($textBox instanceof \DOMElement) {
            $name = self::attr($frame, self::DRAW_NS, 'name');
            $attrs = [
                'sourceFormat' => 'odt',
                'classes' => ['odf-text-box'],
                'attributes' => [],
            ];
            if ($name !== '') {
                $attrs['attributes']['data-odf-frame-name'] = $name;
            }
            $frameMetadata = $this->textBoxFrameMetadata($frame);
            if ($frameMetadata !== []) {
                $attrs['odfFrameMetadata'] = $frameMetadata;
                $attrs['attributes'] = $attrs['attributes'] + $this->frameMetadataAttributes($frameMetadata);
            }

            return new AstNode('div', $attrs, $this->blockNodes($textBox, $package, $catalog));
        }

        $control = self::firstChildElement($frame, 'control', self::DRAW_NS);
        if ($control instanceof \DOMElement) {
            return $this->formControlNode($control, $frame, false);
        }

        $objectOle = $this->frameObjectOleNode($frame, $package, false);
        if ($objectOle instanceof AstNode) {
            return $objectOle;
        }

        $math = $this->frameObjectMathNode($frame, $package);
        if ($math instanceof AstNode) {
            return new AstNode('paragraph', [
                'sourceFormat' => 'odt',
                'text' => (string) $math->attr('text', ''),
            ], [$math]);
        }

        $object = $this->frameObjectNode($frame, $package, false);
        if ($object instanceof AstNode) {
            return $object;
        }

        $image = $this->frameImageNode($frame, $package);
        if (!$image instanceof AstNode) {
            return null;
        }

        $captionMetadata = $this->frameCaptionMetadata($frame, $package, $catalog);
        $caption = (string) ($captionMetadata['text'] ?? $image->attr('alt', ''));
        $attrs = [
            'sourceFormat' => 'odt',
            'caption' => $caption,
        ];
        if ($captionMetadata !== []) {
            $attrs['classes'] = ['odf-frame-caption'];
            $attrs['odfFrameCaption'] = $captionMetadata;
            $attrs['attributes'] = [
                'data-odf-frame-caption-source' => 'draw:caption',
            ];
            if (isset($captionMetadata['frameName'])) {
                $attrs['attributes']['data-odf-frame-caption-frame-name'] = (string) $captionMetadata['frameName'];
            }
        }

        return new AstNode('figure', $attrs, [$image]);
    }

    /**
     * @param array{styles:array<string, array<string, mixed>>, listStyles:array<string, array<string, mixed>>} $catalog
     */
    private function annotationBlockNode(\DOMElement $annotation, ZipPackage $package, array $catalog): AstNode
    {
        $note = $this->annotationNoteNode($annotation, $package, $catalog);

        return new AstNode('blockquote', [
            'sourceFormat' => 'odt',
            'classes' => ['odf-annotation'],
        ], $note->children);
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function formControlsFromText(\DOMElement $text): array
    {
        $forms = self::firstChildElement($text, 'forms', self::OFFICE_NS);
        if (!$forms instanceof \DOMElement) {
            return [];
        }

        $controls = [];
        $this->collectFormControls($forms, [], $controls);

        return $controls;
    }

    /**
     * @param array<string, mixed> $formMetadata
     * @param array<string, array<string, mixed>> $controls
     */
    private function collectFormControls(\DOMElement $container, array $formMetadata, array &$controls): void
    {
        if ($this->isElement($container, self::FORM_NS, 'form')) {
            $formMetadata = array_merge($formMetadata, $this->formDefinition($container));
        }

        foreach (self::childElements($container) as $child) {
            if ($this->isElement($child, self::FORM_NS, 'form')) {
                $this->collectFormControls($child, $formMetadata, $controls);
                continue;
            }

            if ($child->namespaceURI !== self::FORM_NS || !in_array($child->localName, self::formControlElementNames(), true)) {
                continue;
            }

            $control = $this->formControlDefinition($child, $formMetadata);
            if ($control !== null) {
                $controls[(string) $control['id']] = $control;
            }
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function formDefinition(\DOMElement $form): array
    {
        $targetFrame = self::attr($form, self::FORM_NS, 'target-frame');
        if ($targetFrame === '') {
            $targetFrame = self::attr($form, self::OFFICE_NS, 'target-frame-name');
        }

        return self::withoutEmpty([
            'name' => self::nullable(self::attr($form, self::FORM_NS, 'name')),
            'action' => self::nullable($this->formAction($form)),
            'method' => self::nullable(self::attr($form, self::FORM_NS, 'method')),
            'enctype' => self::nullable(self::attr($form, self::FORM_NS, 'enctype')),
            'targetFrame' => self::nullable($targetFrame),
            'command' => self::nullable(self::attr($form, self::FORM_NS, 'command')),
            'commandType' => self::nullable(self::attr($form, self::FORM_NS, 'command-type')),
            'datasource' => self::nullable(self::attr($form, self::FORM_NS, 'datasource')),
            'filter' => self::nullable(self::attr($form, self::FORM_NS, 'filter')),
            'order' => self::nullable(self::attr($form, self::FORM_NS, 'order')),
            'applyFilter' => self::nullableBool(self::attr($form, self::FORM_NS, 'apply-filter')),
            'ignoreResult' => self::nullableBool(self::attr($form, self::FORM_NS, 'ignore-result')),
            'escapeProcessing' => self::nullableBool(self::attr($form, self::FORM_NS, 'escape-processing')),
            'navigationMode' => self::nullable(self::attr($form, self::FORM_NS, 'navigation-mode')),
            'tabCycle' => self::nullable(self::attr($form, self::FORM_NS, 'tab-cycle')),
            'masterFields' => self::nullable(self::attr($form, self::FORM_NS, 'master-fields')),
            'detailFields' => self::nullable(self::attr($form, self::FORM_NS, 'detail-fields')),
            'defaultControlImplementation' => self::nullable(self::attr($form, self::FORM_NS, 'control-implementation')),
            'xlinkType' => self::nullable(self::attr($form, self::XLINK_NS, 'type')),
            'xlinkShow' => self::nullable(self::attr($form, self::XLINK_NS, 'show')),
            'xlinkActuate' => self::nullable(self::attr($form, self::XLINK_NS, 'actuate')),
        ]);
    }

    private function formAction(\DOMElement $form): string
    {
        $href = self::attr($form, self::XLINK_NS, 'href');
        if ($href !== '') {
            return $href;
        }

        $action = self::attr($form, self::FORM_NS, 'action');
        if ($action !== '') {
            return $action;
        }

        return self::attr($form, self::FORM_NS, 'href');
    }

    /**
     * @return ?array<string, mixed>
     */
    private function formControlDefinition(\DOMElement $control, array $formMetadata): ?array
    {
        $id = self::attr($control, self::FORM_NS, 'id');
        if ($id === '') {
            $id = self::attr($control, self::FORM_NS, 'name');
        }
        if ($id === '') {
            return null;
        }

        return self::withoutEmpty(array_merge([
            'id' => $id,
            'type' => $control->localName,
            'name' => self::nullable(self::attr($control, self::FORM_NS, 'name')),
            'label' => self::nullable(self::attr($control, self::FORM_NS, 'label')),
            'implementation' => self::nullable(self::attr($control, self::FORM_NS, 'control-implementation')),
            'value' => self::nullable(self::attr($control, self::FORM_NS, 'value')),
            'currentValue' => self::nullable(self::attr($control, self::FORM_NS, 'current-value')),
            'currentState' => self::nullable(self::attr($control, self::FORM_NS, 'current-state')),
            'linkedCell' => self::nullable(self::attr($control, self::FORM_NS, 'linked-cell')),
            'sourceCellRange' => self::nullable(self::attr($control, self::FORM_NS, 'source-cell-range')),
            'listSource' => self::nullable(self::attr($control, self::FORM_NS, 'list-source')),
            'listSourceType' => self::nullable(self::attr($control, self::FORM_NS, 'list-source-type')),
            'boundColumn' => self::nullableInt(self::attr($control, self::FORM_NS, 'bound-column')),
            'dropdown' => self::nullableBool(self::attr($control, self::FORM_NS, 'dropdown')),
            'multiple' => self::nullableBool(self::attr($control, self::FORM_NS, 'multiple')),
            'automaticCompletion' => self::nullableBool(self::attr($control, self::FORM_NS, 'automatic-completion')),
            'tabIndex' => self::nullableInt(self::attr($control, self::FORM_NS, 'tab-index')),
            'href' => self::nullable(self::attr($control, self::XLINK_NS, 'href')),
            'disabled' => self::nullableBool(self::attr($control, self::FORM_NS, 'disabled')),
            'printable' => self::nullableBool(self::attr($control, self::FORM_NS, 'printable')),
            'formMetadata' => $formMetadata,
        ], $this->formControlOptionMetadata($control), $this->prefixedFormMetadata($formMetadata)));
    }

    /**
     * @param array<string, mixed> $formMetadata
     * @return array<string, mixed>
     */
    private function prefixedFormMetadata(array $formMetadata): array
    {
        $prefixed = [];
        foreach ($formMetadata as $name => $value) {
            if (!is_scalar($value)) {
                continue;
            }

            $prefixed['form' . ucfirst($name)] = $value;
        }

        return $prefixed;
    }

    /**
     * @return array<string, mixed>
     */
    private function formControlOptionMetadata(\DOMElement $control): array
    {
        $options = [];
        foreach (self::childElements($control) as $child) {
            if ($child->namespaceURI !== self::FORM_NS || !in_array($child->localName, ['option', 'item'], true)) {
                continue;
            }

            $label = self::attr($child, self::FORM_NS, 'label');
            if ($label === '') {
                $label = self::normalizedText($child);
            }

            $value = self::attr($child, self::FORM_NS, 'value');
            $selected = self::nullableBool(self::attr($child, self::FORM_NS, 'current-selected'))
                ?? self::nullableBool(self::attr($child, self::FORM_NS, 'selected'));

            $option = self::withoutEmpty([
                'element' => $child->localName,
                'label' => self::nullable($label),
                'value' => self::nullable($value),
                'selected' => $selected,
                'disabled' => self::nullableBool(self::attr($child, self::FORM_NS, 'disabled')),
            ]);
            if ($option !== []) {
                $options[] = $option;
            }
        }

        if ($options === []) {
            return [];
        }

        $selectedOptions = array_values(array_filter(
            $options,
            static fn (array $option): bool => ($option['selected'] ?? false) === true
        ));
        $selectedLabels = $this->formControlOptionValues($selectedOptions, 'label');
        $selectedValues = $this->formControlOptionValues($selectedOptions, 'value');

        return self::withoutEmpty([
            'options' => $options,
            'optionCount' => count($options),
            'selectedOptionCount' => count($selectedOptions),
            'selectedOptionLabels' => $selectedLabels === [] ? null : implode(', ', $selectedLabels),
            'selectedOptionValues' => $selectedValues === [] ? null : implode(', ', $selectedValues),
        ]);
    }

    /**
     * @param list<array<string, mixed>> $options
     * @return list<string>
     */
    private function formControlOptionValues(array $options, string $name): array
    {
        $values = [];
        foreach ($options as $option) {
            $value = $option[$name] ?? null;
            if (is_scalar($value) && (string) $value !== '') {
                $values[] = (string) $value;
            }
        }

        return $values;
    }

    /**
     * @return array<string, mixed>
     */
    private function contentDeclarationsFromText(\DOMElement $text, ?\DOMElement $contentRoot = null): array
    {
        $noteConfigurations = [];
        $noteConfigurationsByClass = [];
        $noteConfigurationSeparatorCount = 0;
        foreach (self::childElements($text, 'notes-configuration', self::TEXT_NS) as $configuration) {
            $entry = $this->noteConfigurationDefinition($configuration);
            $noteClass = (string) ($entry['noteClass'] ?? '');
            if ($noteClass === '') {
                continue;
            }

            $noteConfigurations[] = $entry;
            $noteConfigurationsByClass[$noteClass] = $entry;
            if (is_array($entry['footnoteSeparator'] ?? null)) {
                $noteConfigurationSeparatorCount++;
            }
        }

        $lineNumberingConfiguration = $this->lineNumberingConfigurationFromText($text);
        $lineNumberingConfigurationCount = $lineNumberingConfiguration === [] ? 0 : 1;
        $lineNumberingSeparatorCount = is_array($lineNumberingConfiguration['separator'] ?? null) ? 1 : 0;

        $sequenceDeclarations = [];
        $sequenceDecls = self::firstChildElement($text, 'sequence-decls', self::TEXT_NS);
        if ($sequenceDecls instanceof \DOMElement) {
            foreach (self::childElements($sequenceDecls, 'sequence-decl', self::TEXT_NS) as $decl) {
                $name = self::attr($decl, self::TEXT_NS, 'name');
                if ($name === '') {
                    continue;
                }

                $sequenceDeclarations[$name] = self::withoutEmpty([
                    'name' => $name,
                    'displayOutlineLevel' => self::nullableInt(self::attr($decl, self::TEXT_NS, 'display-outline-level')),
                    'separationCharacter' => self::nullable(self::attr($decl, self::TEXT_NS, 'separation-character')),
                ]);
            }
        }

        $variableDeclarations = [];
        $variableDecls = self::firstChildElement($text, 'variable-decls', self::TEXT_NS);
        if ($variableDecls instanceof \DOMElement) {
            foreach (self::childElements($variableDecls, 'variable-decl', self::TEXT_NS) as $decl) {
                $name = self::attr($decl, self::TEXT_NS, 'name');
                if ($name === '') {
                    continue;
                }

                $variableDeclarations[$name] = self::withoutEmpty([
                    'name' => $name,
                    'valueType' => self::nullable(self::attr($decl, self::OFFICE_NS, 'value-type')),
                    'value' => self::nullable(self::attr($decl, self::OFFICE_NS, 'value')),
                    'stringValue' => self::nullable(self::attr($decl, self::OFFICE_NS, 'string-value')),
                    'dateValue' => self::nullable(self::attr($decl, self::OFFICE_NS, 'date-value')),
                    'timeValue' => self::nullable(self::attr($decl, self::OFFICE_NS, 'time-value')),
                    'booleanValue' => self::nullableBool(self::attr($decl, self::OFFICE_NS, 'boolean-value')),
                    'currency' => self::nullable(self::attr($decl, self::OFFICE_NS, 'currency')),
                ]);
            }
        }

        $userFieldDeclarations = [];
        $userFieldDecls = self::firstChildElement($text, 'user-field-decls', self::TEXT_NS);
        if ($userFieldDecls instanceof \DOMElement) {
            foreach (self::childElements($userFieldDecls, 'user-field-decl', self::TEXT_NS) as $decl) {
                $name = self::attr($decl, self::TEXT_NS, 'name');
                if ($name === '') {
                    continue;
                }

                $dateValue = self::attr($decl, self::OFFICE_NS, 'date-value');
                if ($dateValue === '') {
                    $dateValue = self::attr($decl, self::TEXT_NS, 'date-value');
                }
                $timeValue = self::attr($decl, self::OFFICE_NS, 'time-value');
                if ($timeValue === '') {
                    $timeValue = self::attr($decl, self::TEXT_NS, 'time-value');
                }
                $booleanValue = self::nullableBool(self::attr($decl, self::OFFICE_NS, 'boolean-value'));

                $userFieldDeclarations[$name] = self::withoutEmpty([
                    'name' => $name,
                    'valueType' => self::nullable(self::attr($decl, self::OFFICE_NS, 'value-type')),
                    'value' => self::nullable(self::attr($decl, self::OFFICE_NS, 'value')),
                    'stringValue' => self::nullable(self::attr($decl, self::OFFICE_NS, 'string-value')),
                    'dateValue' => self::nullable($dateValue),
                    'timeValue' => self::nullable($timeValue),
                    'booleanValue' => $booleanValue,
                    'currency' => self::nullable(self::attr($decl, self::OFFICE_NS, 'currency')),
                ]);
            }
        }

        $namedExpressions = $this->namedExpressionsFromText($text);
        $namedExpressionsByName = [];
        $namedRangeCount = 0;
        $namedFormulaExpressionCount = 0;
        foreach ($namedExpressions as $expression) {
            $name = (string) ($expression['name'] ?? '');
            if ($name !== '') {
                $namedExpressionsByName[$name] = $expression;
            }
            if (($expression['type'] ?? '') === 'range') {
                $namedRangeCount++;
            } elseif (($expression['type'] ?? '') === 'expression') {
                $namedFormulaExpressionCount++;
            }
        }

        $contentValidations = $this->contentValidationsFromText($text);
        $contentValidationsByName = [];
        $contentValidationConditionCount = 0;
        $contentValidationMessageCount = 0;
        foreach ($contentValidations as $validation) {
            $name = (string) ($validation['name'] ?? '');
            if ($name !== '') {
                $contentValidationsByName[$name] = $validation;
            }
            if ((string) ($validation['condition'] ?? '') !== '') {
                $contentValidationConditionCount++;
            }
            if (is_array($validation['helpMessage'] ?? null)) {
                $contentValidationMessageCount++;
            }
            if (is_array($validation['errorMessage'] ?? null)) {
                $contentValidationMessageCount++;
            }
        }

        $labelRanges = $this->labelRangesFromText($text);
        $labelRangeOrientationCounts = [];
        foreach ($labelRanges as $labelRange) {
            $orientation = (string) ($labelRange['orientation'] ?? '');
            if ($orientation !== '') {
                $labelRangeOrientationCounts[$orientation] = ($labelRangeOrientationCounts[$orientation] ?? 0) + 1;
            }
        }
        $calculationSettings = $this->calculationSettingsFromText($text);

        $databaseRanges = $this->databaseRangesFromText($text);
        $databaseRangesByName = [];
        $databaseSubtotalRuleCount = 0;
        $databaseSubtotalFieldCount = 0;
        foreach ($databaseRanges as $range) {
            $name = (string) ($range['name'] ?? '');
            if ($name !== '') {
                $databaseRangesByName[$name] = $range;
            }
            $subtotalRules = $range['subtotalRules'] ?? null;
            if (!is_array($subtotalRules)) {
                continue;
            }

            $databaseSubtotalRuleCount += (int) ($subtotalRules['ruleCount'] ?? 0);
            $databaseSubtotalFieldCount += (int) ($subtotalRules['fieldCount'] ?? 0);
        }

        $dataPilotTables = $this->dataPilotTablesFromText($text);
        $dataPilotTablesByName = [];
        $dataPilotFieldCount = 0;
        $dataPilotSubtotalCount = 0;
        $dataPilotMemberCount = 0;
        foreach ($dataPilotTables as $table) {
            $name = (string) ($table['name'] ?? '');
            if ($name !== '') {
                $dataPilotTablesByName[$name] = $table;
            }

            $fields = $table['fields'] ?? [];
            if (!is_array($fields)) {
                continue;
            }
            $dataPilotFieldCount += count($fields);
            foreach ($fields as $field) {
                if (!is_array($field)) {
                    continue;
                }
                $dataPilotSubtotalCount += $this->dataPilotFieldSubtotalCount($field);
                $dataPilotMemberCount += $this->dataPilotFieldMemberCount($field);
            }
        }

        $tableTrackedChanges = $this->tableTrackedChangesFromText($text);
        $tableTrackedChangesById = [];
        $tableTrackedChangeActionCounts = [];
        foreach ($tableTrackedChanges as $change) {
            $id = (string) ($change['id'] ?? '');
            if ($id !== '') {
                $tableTrackedChangesById[$id] = $change;
            }

            $actionType = (string) ($change['actionType'] ?? '');
            if ($actionType !== '') {
                $tableTrackedChangeActionCounts[$actionType] = ($tableTrackedChangeActionCounts[$actionType] ?? 0) + 1;
            }
        }

        $ddeConnectionDeclarations = $this->ddeConnectionDeclarationsFromText($text);
        $ddeConnectionDeclarationsByName = [];
        foreach ($ddeConnectionDeclarations as $declaration) {
            $name = (string) ($declaration['name'] ?? '');
            if ($name !== '') {
                $ddeConnectionDeclarationsByName[$name] = $declaration;
            }
        }

        $drawLayers = $this->drawLayerDeclarationsFromRoot($contentRoot ?? $text);
        $drawLayersByName = [];
        $hiddenDrawLayerCount = 0;
        $protectedDrawLayerCount = 0;
        foreach ($drawLayers as $layer) {
            $name = (string) ($layer['name'] ?? '');
            if ($name !== '') {
                $drawLayersByName[$name] = $layer;
            }
            if (($layer['hidden'] ?? false) === true) {
                $hiddenDrawLayerCount++;
            }
            if (($layer['protected'] ?? false) === true) {
                $protectedDrawLayerCount++;
            }
        }

        return [
            'noteConfigurationCount' => count($noteConfigurations),
            'noteConfigurationSeparatorCount' => $noteConfigurationSeparatorCount,
            'noteConfigurations' => $noteConfigurations,
            'noteConfigurationsByClass' => $noteConfigurationsByClass,
            'lineNumberingConfigurationCount' => $lineNumberingConfigurationCount,
            'lineNumberingSeparatorCount' => $lineNumberingSeparatorCount,
            'lineNumberingConfiguration' => $lineNumberingConfiguration,
            'sequenceDeclarationCount' => count($sequenceDeclarations),
            'sequenceDeclarations' => $sequenceDeclarations,
            'variableDeclarationCount' => count($variableDeclarations),
            'variableDeclarations' => $variableDeclarations,
            'userFieldDeclarationCount' => count($userFieldDeclarations),
            'userFieldDeclarations' => $userFieldDeclarations,
            'contentValidationCount' => count($contentValidations),
            'contentValidationConditionCount' => $contentValidationConditionCount,
            'contentValidationMessageCount' => $contentValidationMessageCount,
            'contentValidations' => $contentValidations,
            'contentValidationsByName' => $contentValidationsByName,
            'labelRangeCount' => count($labelRanges),
            'labelRanges' => $labelRanges,
            'labelRangeOrientationCounts' => $labelRangeOrientationCounts,
            'calculationSettingCount' => $calculationSettings === [] ? 0 : 1,
            'calculationSettings' => $calculationSettings,
            'namedExpressionCount' => count($namedExpressions),
            'namedRangeCount' => $namedRangeCount,
            'namedFormulaExpressionCount' => $namedFormulaExpressionCount,
            'namedExpressions' => $namedExpressions,
            'namedExpressionsByName' => $namedExpressionsByName,
            'databaseRangeCount' => count($databaseRanges),
            'databaseRanges' => $databaseRanges,
            'databaseRangesByName' => $databaseRangesByName,
            'databaseSubtotalRuleCount' => $databaseSubtotalRuleCount,
            'databaseSubtotalFieldCount' => $databaseSubtotalFieldCount,
            'dataPilotTableCount' => count($dataPilotTables),
            'dataPilotFieldCount' => $dataPilotFieldCount,
            'dataPilotSubtotalCount' => $dataPilotSubtotalCount,
            'dataPilotMemberCount' => $dataPilotMemberCount,
            'dataPilotTables' => $dataPilotTables,
            'dataPilotTablesByName' => $dataPilotTablesByName,
            'tableTrackedChangeCount' => count($tableTrackedChanges),
            'tableTrackedChanges' => $tableTrackedChanges,
            'tableTrackedChangesById' => $tableTrackedChangesById,
            'tableTrackedChangeActionCounts' => $tableTrackedChangeActionCounts,
            'ddeConnectionDeclarationCount' => count($ddeConnectionDeclarations),
            'ddeConnectionDeclarations' => $ddeConnectionDeclarations,
            'ddeConnectionDeclarationsByName' => $ddeConnectionDeclarationsByName,
            'drawLayerCount' => count($drawLayers),
            'hiddenDrawLayerCount' => $hiddenDrawLayerCount,
            'protectedDrawLayerCount' => $protectedDrawLayerCount,
            'drawLayers' => $drawLayers,
            'drawLayersByName' => $drawLayersByName,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function lineNumberingConfigurationFromText(\DOMElement $text): array
    {
        $configuration = self::firstChildElement($text, 'linenumbering-configuration', self::TEXT_NS);
        if (!$configuration instanceof \DOMElement) {
            $configuration = self::firstChildElement($text, 'line-numbering-configuration', self::TEXT_NS);
        }

        if (!$configuration instanceof \DOMElement) {
            return [];
        }

        return $this->lineNumberingConfigurationDefinition($configuration);
    }

    /**
     * @return array<string, mixed>
     */
    private function lineNumberingConfigurationDefinition(\DOMElement $configuration): array
    {
        $separator = self::firstChildElement($configuration, 'linenumbering-separator', self::TEXT_NS);
        if (!$separator instanceof \DOMElement) {
            $separator = self::firstChildElement($configuration, 'line-numbering-separator', self::TEXT_NS);
        }

        return self::withoutEmpty([
            'numberLines' => self::nullableBool(self::attr($configuration, self::TEXT_NS, 'number-lines')),
            'styleName' => self::nullable(self::attr($configuration, self::TEXT_NS, 'style-name')),
            'offset' => self::nullable(self::attr($configuration, self::TEXT_NS, 'offset')),
            'numberPosition' => self::nullable(self::attr($configuration, self::TEXT_NS, 'number-position')),
            'increment' => self::nullableInt(self::attr($configuration, self::TEXT_NS, 'increment')),
            'countEmptyLines' => self::nullableBool(self::attr($configuration, self::TEXT_NS, 'count-empty-lines')),
            'countInTextBoxes' => self::nullableBool(self::attr($configuration, self::TEXT_NS, 'count-in-text-boxes')),
            'restartOnPage' => self::nullableBool(self::attr($configuration, self::TEXT_NS, 'restart-on-page')),
            'numFormat' => self::nullable(self::attr($configuration, self::STYLE_NS, 'num-format')),
            'numLetterSync' => self::nullableBool(self::attr($configuration, self::STYLE_NS, 'num-letter-sync')),
            'separator' => $separator instanceof \DOMElement ? $this->lineNumberingSeparatorDefinition($separator) : null,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function lineNumberingSeparatorDefinition(\DOMElement $separator): array
    {
        return self::withoutEmpty([
            'increment' => self::nullableInt(self::attr($separator, self::TEXT_NS, 'increment')),
            'text' => self::nullable(self::normalizedText($separator)),
        ]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function drawLayerDeclarationsFromRoot(\DOMElement $root): array
    {
        $layers = [];
        foreach ($root->getElementsByTagNameNS(self::DRAW_NS, 'layer-set') as $layerSet) {
            if (!$layerSet instanceof \DOMElement) {
                continue;
            }

            foreach (self::childElements($layerSet, 'layer', self::DRAW_NS) as $layer) {
                $definition = $this->drawLayerDefinition($layer);
                if ($definition !== []) {
                    $layers[] = $definition;
                }
            }
        }

        return $layers;
    }

    /**
     * @return array<string, mixed>
     */
    private function drawLayerDefinition(\DOMElement $layer): array
    {
        $name = self::attr($layer, self::DRAW_NS, 'name');
        if ($name === '') {
            return [];
        }

        $display = self::attr($layer, self::DRAW_NS, 'display');
        $protected = self::nullableBool(self::attr($layer, self::DRAW_NS, 'protected'));
        $hidden = in_array(strtolower($display), ['false', 'hidden', 'none'], true);

        return self::withoutEmpty([
            'name' => $name,
            'display' => self::nullable($display),
            'protected' => $protected,
            'hidden' => $hidden ? true : null,
        ]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function contentValidationsFromText(\DOMElement $text): array
    {
        $validations = [];
        foreach (self::childElements($text, 'content-validations', self::TABLE_NS) as $container) {
            foreach (self::childElements($container, 'content-validation', self::TABLE_NS) as $validation) {
                $definition = $this->contentValidationDefinition($validation);
                if ($definition !== []) {
                    $validations[] = $definition;
                }
            }
        }

        return $validations;
    }

    /**
     * @return array<string, mixed>
     */
    private function contentValidationDefinition(\DOMElement $validation): array
    {
        $name = self::attr($validation, self::TABLE_NS, 'name');
        if ($name === '') {
            return [];
        }

        $helpMessage = self::firstChildElement($validation, 'help-message', self::TABLE_NS);
        $errorMessage = self::firstChildElement($validation, 'error-message', self::TABLE_NS);
        $errorMacro = self::firstChildElement($validation, 'error-macro', self::TABLE_NS);

        return self::withoutEmpty([
            'name' => $name,
            'condition' => self::nullable(self::attr($validation, self::TABLE_NS, 'condition')),
            'baseCellAddress' => self::nullable(self::attr($validation, self::TABLE_NS, 'base-cell-address')),
            'allowEmptyCell' => self::nullableBool(self::attr($validation, self::TABLE_NS, 'allow-empty-cell')),
            'displayList' => self::nullable(self::attr($validation, self::TABLE_NS, 'display-list')),
            'helpMessage' => $helpMessage instanceof \DOMElement ? $this->contentValidationMessageDefinition($helpMessage) : null,
            'errorMessage' => $errorMessage instanceof \DOMElement ? $this->contentValidationMessageDefinition($errorMessage) : null,
            'errorMacro' => $errorMacro instanceof \DOMElement ? $this->contentValidationErrorMacroDefinition($errorMacro) : null,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function contentValidationMessageDefinition(\DOMElement $message): array
    {
        return self::withoutEmpty([
            'title' => self::nullable(self::attr($message, self::TABLE_NS, 'title')),
            'display' => self::nullableBool(self::attr($message, self::TABLE_NS, 'display')),
            'messageType' => self::nullable(self::attr($message, self::TABLE_NS, 'message-type')),
            'text' => self::nullable(self::normalizedText($message)),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function contentValidationErrorMacroDefinition(\DOMElement $macro): array
    {
        return self::withoutEmpty([
            'name' => self::nullable(self::attr($macro, self::TABLE_NS, 'name')),
            'execute' => self::nullableBool(self::attr($macro, self::TABLE_NS, 'execute')),
            'macroName' => self::nullable(self::attr($macro, self::TABLE_NS, 'macro-name')),
            'scriptLanguage' => self::nullable(self::attr($macro, self::SCRIPT_NS, 'language')),
            'href' => self::nullable(self::attr($macro, self::XLINK_NS, 'href')),
        ]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function labelRangesFromText(\DOMElement $text): array
    {
        $ranges = [];
        foreach (self::childElements($text, 'label-ranges', self::TABLE_NS) as $container) {
            foreach (self::childElements($container, 'label-range', self::TABLE_NS) as $range) {
                $definition = $this->labelRangeDefinition($range);
                if ($definition !== []) {
                    $ranges[] = $definition;
                }
            }
        }

        return $ranges;
    }

    /**
     * @return array<string, mixed>
     */
    private function labelRangeDefinition(\DOMElement $range): array
    {
        return self::withoutEmpty([
            'labelCellRangeAddress' => self::nullable(self::attr($range, self::TABLE_NS, 'label-cell-range-address')),
            'dataCellRangeAddress' => self::nullable(self::attr($range, self::TABLE_NS, 'data-cell-range-address')),
            'orientation' => self::nullable(self::attr($range, self::TABLE_NS, 'orientation')),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function calculationSettingsFromText(\DOMElement $text): array
    {
        foreach (self::childElements($text, 'calculation-settings', self::TABLE_NS) as $settings) {
            $definition = $this->calculationSettingsDefinition($settings);
            if ($definition !== []) {
                return $definition;
            }
        }

        return [];
    }

    /**
     * @return array<string, mixed>
     */
    private function calculationSettingsDefinition(\DOMElement $settings): array
    {
        return self::withoutEmpty([
            'caseSensitive' => self::nullableBool(self::attr($settings, self::TABLE_NS, 'case-sensitive')),
            'precisionAsShown' => self::nullableBool(self::attr($settings, self::TABLE_NS, 'precision-as-shown')),
            'searchCriteriaMustApplyToWholeCell' => self::nullableBool(self::attr($settings, self::TABLE_NS, 'search-criteria-must-apply-to-whole-cell')),
            'automaticFindLabels' => self::nullableBool(self::attr($settings, self::TABLE_NS, 'automatic-find-labels')),
            'useRegularExpressions' => self::nullableBool(self::attr($settings, self::TABLE_NS, 'use-regular-expressions')),
            'useWildcards' => self::nullableBool(self::attr($settings, self::TABLE_NS, 'use-wildcards')),
            'nullYear' => self::nullableInt(self::attr($settings, self::TABLE_NS, 'null-year')),
            'iteration' => self::nullableBool(self::attr($settings, self::TABLE_NS, 'iteration')),
            'iterationCount' => self::nullableInt(self::attr($settings, self::TABLE_NS, 'iteration-count')),
            'iterationTolerance' => self::nullable(self::attr($settings, self::TABLE_NS, 'iteration-tolerance')),
        ]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function ddeConnectionDeclarationsFromText(\DOMElement $text): array
    {
        $declarations = [];
        foreach (self::childElements($text, 'dde-connection-decls', self::TEXT_NS) as $container) {
            foreach (self::childElements($container, 'dde-connection-decl', self::TEXT_NS) as $declaration) {
                $name = self::attr($declaration, self::OFFICE_NS, 'name');
                if ($name === '') {
                    continue;
                }

                $declarations[] = self::withoutEmpty([
                    'name' => $name,
                    'ddeApplication' => self::nullable(self::attr($declaration, self::OFFICE_NS, 'dde-application')),
                    'ddeTopic' => self::nullable(self::attr($declaration, self::OFFICE_NS, 'dde-topic')),
                    'ddeItem' => self::nullable(self::attr($declaration, self::OFFICE_NS, 'dde-item')),
                    'automaticUpdate' => self::nullableBool(self::attr($declaration, self::OFFICE_NS, 'automatic-update')),
                    'conversionMode' => self::nullable(self::attr($declaration, self::OFFICE_NS, 'conversion-mode')),
                ]);
            }
        }

        return $declarations;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function namedExpressionsFromText(\DOMElement $text): array
    {
        $expressions = [];
        foreach (self::childElements($text, 'named-expressions', self::TABLE_NS) as $container) {
            foreach (self::childElements($container) as $entry) {
                if ($entry->namespaceURI !== self::TABLE_NS) {
                    continue;
                }

                if ($entry->localName === 'named-range') {
                    $definition = $this->namedRangeDefinition($entry);
                } elseif ($entry->localName === 'named-expression') {
                    $definition = $this->namedExpressionDefinition($entry);
                } else {
                    continue;
                }

                if ($definition !== []) {
                    $expressions[] = $definition;
                }
            }
        }

        return $expressions;
    }

    /**
     * @return array<string, mixed>
     */
    private function namedRangeDefinition(\DOMElement $range): array
    {
        $name = self::attr($range, self::TABLE_NS, 'name');
        if ($name === '') {
            return [];
        }

        return self::withoutEmpty([
            'type' => 'range',
            'element' => 'named-range',
            'name' => $name,
            'cellRangeAddress' => self::nullable(self::attr($range, self::TABLE_NS, 'cell-range-address')),
            'baseCellAddress' => self::nullable(self::attr($range, self::TABLE_NS, 'base-cell-address')),
            'rangeUsableAs' => self::nullable(self::attr($range, self::TABLE_NS, 'range-usable-as')),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function namedExpressionDefinition(\DOMElement $expression): array
    {
        $name = self::attr($expression, self::TABLE_NS, 'name');
        if ($name === '') {
            return [];
        }

        return self::withoutEmpty([
            'type' => 'expression',
            'element' => 'named-expression',
            'name' => $name,
            'expression' => self::nullable(self::attr($expression, self::TABLE_NS, 'expression')),
            'baseCellAddress' => self::nullable(self::attr($expression, self::TABLE_NS, 'base-cell-address')),
        ]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function databaseRangesFromText(\DOMElement $text): array
    {
        $ranges = [];
        foreach (self::childElements($text, 'database-ranges', self::TABLE_NS) as $container) {
            foreach (self::childElements($container, 'database-range', self::TABLE_NS) as $rangeElement) {
                $range = $this->databaseRangeDefinition($rangeElement);
                if ($range !== []) {
                    $ranges[] = $range;
                }
            }
        }

        return $ranges;
    }

    /**
     * @return array<string, mixed>
     */
    private function databaseRangeDefinition(\DOMElement $range): array
    {
        $definition = self::withoutEmpty([
            'name' => self::nullable(self::attr($range, self::TABLE_NS, 'name')),
            'targetRangeAddress' => self::nullable(self::attr($range, self::TABLE_NS, 'target-range-address')),
            'containsHeader' => self::nullableBool(self::attr($range, self::TABLE_NS, 'contains-header')),
            'displayFilterButtons' => self::nullableBool(self::attr($range, self::TABLE_NS, 'display-filter-buttons')),
            'isSelection' => self::nullableBool(self::attr($range, self::TABLE_NS, 'is-selection')),
            'onUpdateKeepStyles' => self::nullableBool(self::attr($range, self::TABLE_NS, 'on-update-keep-styles')),
            'onUpdateKeepSize' => self::nullableBool(self::attr($range, self::TABLE_NS, 'on-update-keep-size')),
            'hasPersistentData' => self::nullableBool(self::attr($range, self::TABLE_NS, 'has-persistent-data')),
            'orientation' => self::nullable(self::attr($range, self::TABLE_NS, 'orientation')),
            'refreshDelay' => self::nullable(self::attr($range, self::TABLE_NS, 'refresh-delay')),
        ]);

        $source = $this->databaseRangeSource($range);
        if ($source !== []) {
            $definition['source'] = $source;
        }

        $filter = self::firstChildElement($range, 'filter', self::TABLE_NS);
        if ($filter instanceof \DOMElement) {
            $filterMetadata = $this->databaseFilterDefinition($filter);
            if ($filterMetadata !== []) {
                $definition['filter'] = $filterMetadata;
            }
        }

        $sort = self::firstChildElement($range, 'sort', self::TABLE_NS);
        if ($sort instanceof \DOMElement) {
            $sortMetadata = $this->databaseSortDefinition($sort);
            if ($sortMetadata !== []) {
                $definition['sort'] = $sortMetadata;
            }
        }

        $subtotalRules = self::firstChildElement($range, 'subtotal-rules', self::TABLE_NS);
        if ($subtotalRules instanceof \DOMElement) {
            $subtotalMetadata = $this->databaseSubtotalRulesDefinition($subtotalRules);
            if ($subtotalMetadata !== []) {
                $definition['subtotalRules'] = $subtotalMetadata;
            }
        }

        return self::withoutEmpty($definition);
    }

    /**
     * @return array<string, mixed>
     */
    private function databaseRangeSource(\DOMElement $range): array
    {
        foreach (self::childElements($range) as $child) {
            if ($child->namespaceURI !== self::TABLE_NS) {
                continue;
            }

            if ($child->localName === 'database-source-table') {
                return self::withoutEmpty([
                    'type' => 'table',
                    'databaseName' => self::nullable(self::attr($child, self::TABLE_NS, 'database-name')),
                    'tableName' => self::nullable(self::attr($child, self::TABLE_NS, 'table-name')),
                ]);
            }

            if ($child->localName === 'database-source-query') {
                return self::withoutEmpty([
                    'type' => 'query',
                    'databaseName' => self::nullable(self::attr($child, self::TABLE_NS, 'database-name')),
                    'queryName' => self::nullable(self::attr($child, self::TABLE_NS, 'query-name')),
                ]);
            }

            if ($child->localName === 'database-source-sql') {
                return self::withoutEmpty([
                    'type' => 'sql',
                    'databaseName' => self::nullable(self::attr($child, self::TABLE_NS, 'database-name')),
                    'sqlStatement' => self::nullable(self::attr($child, self::TABLE_NS, 'sql-statement')),
                    'parseSqlStatement' => self::nullableBool(self::attr($child, self::TABLE_NS, 'parse-sql-statement')),
                ]);
            }
        }

        return [];
    }

    /**
     * @return array<string, mixed>
     */
    private function databaseFilterDefinition(\DOMElement $filter): array
    {
        $conditions = [];
        foreach (self::childElements($filter) as $child) {
            $condition = $this->databaseFilterExpression($child);
            if ($condition !== []) {
                $conditions[] = $condition;
            }
        }

        return self::withoutEmpty([
            'targetRangeAddress' => self::nullable(self::attr($filter, self::TABLE_NS, 'target-range-address')),
            'conditionSourceRangeAddress' => self::nullable(self::attr($filter, self::TABLE_NS, 'condition-source-range-address')),
            'displayDuplicates' => self::nullableBool(self::attr($filter, self::TABLE_NS, 'display-duplicates')),
            'conditions' => $conditions,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function databaseFilterExpression(\DOMElement $element): array
    {
        if ($element->namespaceURI !== self::TABLE_NS) {
            return [];
        }

        if ($element->localName === 'filter-condition') {
            return self::withoutEmpty([
                'type' => 'condition',
                'fieldNumber' => self::nullableInt(self::attr($element, self::TABLE_NS, 'field-number')),
                'caseSensitive' => self::nullableBool(self::attr($element, self::TABLE_NS, 'case-sensitive')),
                'dataType' => self::nullable(self::attr($element, self::TABLE_NS, 'data-type')),
                'value' => self::nullable(self::attr($element, self::TABLE_NS, 'value')),
                'operator' => self::nullable(self::attr($element, self::TABLE_NS, 'operator')),
            ]);
        }

        if ($element->localName === 'filter-and' || $element->localName === 'filter-or') {
            $conditions = [];
            foreach (self::childElements($element) as $child) {
                $condition = $this->databaseFilterExpression($child);
                if ($condition !== []) {
                    $conditions[] = $condition;
                }
            }

            return self::withoutEmpty([
                'type' => $element->localName === 'filter-and' ? 'and' : 'or',
                'conditions' => $conditions,
            ]);
        }

        return [];
    }

    /**
     * @return array<string, mixed>
     */
    private function databaseSortDefinition(\DOMElement $sort): array
    {
        return self::withoutEmpty([
            'caseSensitive' => self::nullableBool(self::attr($sort, self::TABLE_NS, 'case-sensitive')),
            'language' => self::nullable(self::attr($sort, self::TABLE_NS, 'language')),
            'country' => self::nullable(self::attr($sort, self::TABLE_NS, 'country')),
            'algorithm' => self::nullable(self::attr($sort, self::TABLE_NS, 'algorithm')),
            'sortBy' => $this->databaseSortByFields($sort),
        ]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function databaseSortByFields(\DOMElement $container): array
    {
        $sortBy = [];
        foreach (self::childElements($container, 'sort-by', self::TABLE_NS) as $sortByElement) {
            $sortBy[] = self::withoutEmpty([
                'fieldNumber' => self::nullableInt(self::attr($sortByElement, self::TABLE_NS, 'field-number')),
                'dataType' => self::nullable(self::attr($sortByElement, self::TABLE_NS, 'data-type')),
                'order' => self::nullable(self::attr($sortByElement, self::TABLE_NS, 'order')),
            ]);
        }

        return $sortBy;
    }

    /**
     * @return array<string, mixed>
     */
    private function databaseSubtotalRulesDefinition(\DOMElement $subtotalRules): array
    {
        $sortGroups = [];
        $rules = [];
        foreach (self::childElements($subtotalRules) as $child) {
            if ($this->isElement($child, self::TABLE_NS, 'sort-groups')) {
                $sortGroups = $this->databaseSortGroupsDefinition($child);
                continue;
            }
            if ($this->isElement($child, self::TABLE_NS, 'subtotal-rule')) {
                $rule = $this->databaseSubtotalRuleDefinition($child);
                if ($rule !== []) {
                    $rules[] = $rule;
                }
            }
        }

        $fieldCount = 0;
        foreach ($rules as $rule) {
            $fieldCount += (int) ($rule['fieldCount'] ?? 0);
        }

        return self::withoutEmpty([
            'bindStylesToContent' => self::nullableBool(self::attr($subtotalRules, self::TABLE_NS, 'bind-styles-to-content')),
            'caseSensitive' => self::nullableBool(self::attr($subtotalRules, self::TABLE_NS, 'case-sensitive')),
            'pageBreaksOnGroupChange' => self::nullableBool(self::attr($subtotalRules, self::TABLE_NS, 'page-breaks-on-group-change')),
            'sortGroups' => $sortGroups,
            'rules' => $rules,
            'ruleCount' => $rules === [] ? null : count($rules),
            'fieldCount' => $fieldCount === 0 ? null : $fieldCount,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function databaseSortGroupsDefinition(\DOMElement $sortGroups): array
    {
        $sortBy = $this->databaseSortByFields($sortGroups);

        return self::withoutEmpty([
            'caseSensitive' => self::nullableBool(self::attr($sortGroups, self::TABLE_NS, 'case-sensitive')),
            'sortBy' => $sortBy,
            'sortFieldCount' => $sortBy === [] ? null : count($sortBy),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function databaseSubtotalRuleDefinition(\DOMElement $rule): array
    {
        $fields = [];
        foreach (self::childElements($rule, 'subtotal-field', self::TABLE_NS) as $field) {
            $entry = self::withoutEmpty([
                'fieldNumber' => self::nullableInt(self::attr($field, self::TABLE_NS, 'field-number')),
                'function' => self::nullable(self::attr($field, self::TABLE_NS, 'function')),
            ]);
            if ($entry !== []) {
                $fields[] = $entry;
            }
        }

        return self::withoutEmpty([
            'groupByFieldNumber' => self::nullableInt(self::attr($rule, self::TABLE_NS, 'group-by-field-number')),
            'fields' => $fields,
            'fieldCount' => $fields === [] ? null : count($fields),
        ]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function dataPilotTablesFromText(\DOMElement $text): array
    {
        $tables = [];
        foreach (self::childElements($text, 'data-pilot-tables', self::TABLE_NS) as $container) {
            foreach (self::childElements($container, 'data-pilot-table', self::TABLE_NS) as $table) {
                $definition = $this->dataPilotTableDefinition($table);
                if ($definition !== []) {
                    $tables[] = $definition;
                }
            }
        }

        return $tables;
    }

    /**
     * @return array<string, mixed>
     */
    private function dataPilotTableDefinition(\DOMElement $table): array
    {
        $name = self::attr($table, self::TABLE_NS, 'name');
        if ($name === '') {
            return [];
        }

        $fields = [];
        foreach (self::childElements($table, 'data-pilot-field', self::TABLE_NS) as $field) {
            $definition = $this->dataPilotFieldDefinition($field);
            if ($definition !== []) {
                $fields[] = $definition;
            }
        }

        $definition = self::withoutEmpty([
            'name' => $name,
            'applicationData' => self::nullable(self::attr($table, self::TABLE_NS, 'application-data')),
            'targetRangeAddress' => self::nullable(self::attr($table, self::TABLE_NS, 'target-range-address')),
            'buttons' => self::nullableBool(self::attr($table, self::TABLE_NS, 'buttons')),
            'showFilterButton' => self::nullableBool(self::attr($table, self::TABLE_NS, 'show-filter-button')),
            'drillDownOnDoubleClick' => self::nullableBool(self::attr($table, self::TABLE_NS, 'drill-down-on-double-click')),
            'grandTotal' => self::nullable(self::attr($table, self::TABLE_NS, 'grand-total')),
            'ignoreEmptyRows' => self::nullableBool(self::attr($table, self::TABLE_NS, 'ignore-empty-rows')),
            'identifyCategories' => self::nullableBool(self::attr($table, self::TABLE_NS, 'identify-categories')),
            'source' => $this->dataPilotSourceDefinition($table),
            'fields' => $fields,
            'fieldCount' => $fields === [] ? null : count($fields),
        ]);

        return $definition;
    }

    /**
     * @return array<string, mixed>
     */
    private function dataPilotSourceDefinition(\DOMElement $table): array
    {
        foreach (self::childElements($table) as $child) {
            if ($child->namespaceURI !== self::TABLE_NS) {
                continue;
            }

            if ($child->localName === 'source-cell-range') {
                return self::withoutEmpty([
                    'type' => 'cell-range',
                    'cellRangeAddress' => self::nullable(self::attr($child, self::TABLE_NS, 'cell-range-address')),
                ]);
            }

            if ($child->localName === 'source-sql') {
                return self::withoutEmpty([
                    'type' => 'sql',
                    'databaseName' => self::nullable(self::attr($child, self::TABLE_NS, 'database-name')),
                    'sqlStatement' => self::nullable(self::attr($child, self::TABLE_NS, 'sql-statement')),
                    'parseSqlStatement' => self::nullableBool(self::attr($child, self::TABLE_NS, 'parse-sql-statement')),
                ]);
            }

            if ($child->localName === 'source-table') {
                return self::withoutEmpty([
                    'type' => 'table',
                    'databaseName' => self::nullable(self::attr($child, self::TABLE_NS, 'database-name')),
                    'tableName' => self::nullable(self::attr($child, self::TABLE_NS, 'table-name')),
                ]);
            }

            if ($child->localName === 'source-query') {
                return self::withoutEmpty([
                    'type' => 'query',
                    'databaseName' => self::nullable(self::attr($child, self::TABLE_NS, 'database-name')),
                    'queryName' => self::nullable(self::attr($child, self::TABLE_NS, 'query-name')),
                ]);
            }

            if ($child->localName === 'source-service') {
                return self::withoutEmpty([
                    'type' => 'service',
                    'name' => self::nullable(self::attr($child, self::TABLE_NS, 'name')),
                    'sourceName' => self::nullable(self::attr($child, self::TABLE_NS, 'source-name')),
                    'objectName' => self::nullable(self::attr($child, self::TABLE_NS, 'object-name')),
                    'userName' => self::nullable(self::attr($child, self::TABLE_NS, 'user-name')),
                    'passwordPresent' => self::attr($child, self::TABLE_NS, 'password') !== '' ? true : null,
                ]);
            }
        }

        return [];
    }

    /**
     * @return array<string, mixed>
     */
    private function dataPilotFieldDefinition(\DOMElement $field): array
    {
        $levels = [];
        foreach (self::childElements($field, 'data-pilot-level', self::TABLE_NS) as $level) {
            $definition = $this->dataPilotLevelDefinition($level);
            if ($definition !== []) {
                $levels[] = $definition;
            }
        }

        $subtotals = $this->dataPilotSubtotalsFromContainer($field);
        $members = $this->dataPilotMembersFromContainer($field);

        return self::withoutEmpty([
            'sourceFieldName' => self::nullable(self::attr($field, self::TABLE_NS, 'source-field-name')),
            'orientation' => self::nullable(self::attr($field, self::TABLE_NS, 'orientation')),
            'function' => self::nullable(self::attr($field, self::TABLE_NS, 'function')),
            'usedHierarchy' => self::nullableInt(self::attr($field, self::TABLE_NS, 'used-hierarchy')),
            'selectedPage' => self::nullable(self::attr($field, self::TABLE_NS, 'selected-page')),
            'levels' => $levels,
            'levelCount' => $levels === [] ? null : count($levels),
            'subtotals' => $subtotals,
            'subtotalCount' => $subtotals === [] ? null : count($subtotals),
            'members' => $members,
            'memberCount' => $members === [] ? null : count($members),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function dataPilotLevelDefinition(\DOMElement $level): array
    {
        $subtotals = $this->dataPilotSubtotalsFromContainer($level);
        $members = $this->dataPilotMembersFromContainer($level);

        return self::withoutEmpty([
            'showEmpty' => self::nullableBool(self::attr($level, self::TABLE_NS, 'show-empty')),
            'displayEmpty' => self::nullableBool(self::attr($level, self::TABLE_NS, 'display-empty')),
            'repeatItemLabels' => self::nullableBool(self::attr($level, self::TABLE_NS, 'repeat-item-labels')),
            'subtotals' => $subtotals,
            'subtotalCount' => $subtotals === [] ? null : count($subtotals),
            'members' => $members,
            'memberCount' => $members === [] ? null : count($members),
        ]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function dataPilotSubtotalsFromContainer(\DOMElement $container): array
    {
        $subtotals = [];
        foreach (self::childElements($container) as $child) {
            if ($this->isElement($child, self::TABLE_NS, 'data-pilot-subtotals')) {
                foreach (self::childElements($child, 'data-pilot-subtotal', self::TABLE_NS) as $subtotal) {
                    $definition = $this->dataPilotSubtotalDefinition($subtotal);
                    if ($definition !== []) {
                        $subtotals[] = $definition;
                    }
                }
                continue;
            }

            if ($this->isElement($child, self::TABLE_NS, 'data-pilot-subtotal')) {
                $definition = $this->dataPilotSubtotalDefinition($child);
                if ($definition !== []) {
                    $subtotals[] = $definition;
                }
            }
        }

        return $subtotals;
    }

    /**
     * @return array<string, mixed>
     */
    private function dataPilotSubtotalDefinition(\DOMElement $subtotal): array
    {
        return self::withoutEmpty([
            'function' => self::nullable(self::attr($subtotal, self::TABLE_NS, 'function')),
        ]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function dataPilotMembersFromContainer(\DOMElement $container): array
    {
        $members = [];
        foreach (self::childElements($container) as $child) {
            if ($this->isElement($child, self::TABLE_NS, 'data-pilot-members')) {
                foreach (self::childElements($child, 'data-pilot-member', self::TABLE_NS) as $member) {
                    $definition = $this->dataPilotMemberDefinition($member);
                    if ($definition !== []) {
                        $members[] = $definition;
                    }
                }
                continue;
            }

            if ($this->isElement($child, self::TABLE_NS, 'data-pilot-member')) {
                $definition = $this->dataPilotMemberDefinition($child);
                if ($definition !== []) {
                    $members[] = $definition;
                }
            }
        }

        return $members;
    }

    /**
     * @return array<string, mixed>
     */
    private function dataPilotMemberDefinition(\DOMElement $member): array
    {
        return self::withoutEmpty([
            'name' => self::nullable(self::attr($member, self::TABLE_NS, 'name')),
            'display' => self::nullableBool(self::attr($member, self::TABLE_NS, 'display')),
            'showDetails' => self::nullableBool(self::attr($member, self::TABLE_NS, 'show-details')),
        ]);
    }

    /**
     * @param array<string, mixed> $field
     */
    private function dataPilotFieldSubtotalCount(array $field): int
    {
        $count = is_array($field['subtotals'] ?? null) ? count($field['subtotals']) : 0;
        $levels = $field['levels'] ?? [];
        if (!is_array($levels)) {
            return $count;
        }
        foreach ($levels as $level) {
            if (is_array($level) && is_array($level['subtotals'] ?? null)) {
                $count += count($level['subtotals']);
            }
        }

        return $count;
    }

    /**
     * @param array<string, mixed> $field
     */
    private function dataPilotFieldMemberCount(array $field): int
    {
        $count = is_array($field['members'] ?? null) ? count($field['members']) : 0;
        $levels = $field['levels'] ?? [];
        if (!is_array($levels)) {
            return $count;
        }
        foreach ($levels as $level) {
            if (is_array($level) && is_array($level['members'] ?? null)) {
                $count += count($level['members']);
            }
        }

        return $count;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function tableTrackedChangesFromText(\DOMElement $text): array
    {
        $changes = [];
        foreach (self::childElements($text, 'tracked-changes', self::TABLE_NS) as $container) {
            foreach (self::childElements($container, 'tracked-change', self::TABLE_NS) as $change) {
                $definition = $this->tableTrackedChangeDefinition($change);
                if ($definition !== []) {
                    $changes[] = $definition;
                }
            }
        }

        return $changes;
    }

    /**
     * @return array<string, mixed>
     */
    private function tableTrackedChangeDefinition(\DOMElement $change): array
    {
        $id = self::attr($change, self::TABLE_NS, 'id');
        if ($id === '') {
            $id = self::attr($change, self::XML_NS, 'id');
        }
        if ($id === '') {
            return [];
        }

        $changeInfo = self::firstChildElement($change, 'change-info', self::OFFICE_NS);
        $comments = [];
        if ($changeInfo instanceof \DOMElement) {
            foreach (self::childElements($changeInfo, 'p', self::TEXT_NS) as $paragraph) {
                $text = self::normalizedText($paragraph);
                if ($text !== '') {
                    $comments[] = $text;
                }
            }
        }

        $action = [];
        foreach (self::childElements($change) as $child) {
            if ($child->namespaceURI !== self::TABLE_NS) {
                continue;
            }

            $action = $this->tableTrackedChangeActionDefinition($child);
            break;
        }

        return self::withoutEmpty([
            'id' => $id,
            'acceptanceState' => self::nullable(self::attr($change, self::TABLE_NS, 'acceptance-state')),
            'rejectingChangeId' => self::nullable(self::attr($change, self::TABLE_NS, 'rejecting-change-id')),
            'creator' => $changeInfo instanceof \DOMElement ? $this->changeInfoText($changeInfo, 'creator') : '',
            'date' => $changeInfo instanceof \DOMElement ? $this->changeInfoText($changeInfo, 'date') : '',
            'comments' => $comments,
            'actionType' => $action['element'] ?? null,
            'action' => $action,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function tableTrackedChangeActionDefinition(\DOMElement $action): array
    {
        $previous = [];
        $nested = [];
        foreach (self::childElements($action) as $child) {
            if ($child->namespaceURI !== self::TABLE_NS) {
                continue;
            }

            $entry = $this->tableTrackedChangeChildDefinition($child);
            if ($entry === []) {
                continue;
            }

            if ($child->localName === 'previous') {
                $previous[] = $entry;
                continue;
            }

            $nested[] = $entry;
        }

        return self::withoutEmpty([
            'element' => $action->localName,
            'attributes' => $this->odfElementMetadataAttributes($action, [self::TABLE_NS, self::OFFICE_NS]),
            'text' => self::nullable(self::normalizedText($action)),
            'previous' => $previous,
            'children' => $nested,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function tableTrackedChangeChildDefinition(\DOMElement $element): array
    {
        return self::withoutEmpty([
            'element' => $element->localName,
            'attributes' => $this->odfElementMetadataAttributes($element, [self::TABLE_NS, self::OFFICE_NS]),
            'text' => self::nullable(self::normalizedText($element)),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function noteConfigurationDefinition(\DOMElement $configuration): array
    {
        $noteClass = self::attr($configuration, self::TEXT_NS, 'note-class');
        if ($noteClass === '') {
            $noteClass = 'footnote';
        }
        $footnoteSeparator = self::firstChildElement($configuration, 'footnote-sep', self::STYLE_NS);

        return self::withoutEmpty([
            'noteClass' => $noteClass,
            'citationStyleName' => self::nullable(self::attr($configuration, self::TEXT_NS, 'citation-style-name')),
            'citationBodyStyleName' => self::nullable(self::attr($configuration, self::TEXT_NS, 'citation-body-style-name')),
            'defaultStyleName' => self::nullable(self::attr($configuration, self::TEXT_NS, 'default-style-name')),
            'masterPageName' => self::nullable(self::attr($configuration, self::TEXT_NS, 'master-page-name')),
            'startValue' => self::nullableInt(self::attr($configuration, self::TEXT_NS, 'start-value')),
            'numFormat' => self::nullable(self::attr($configuration, self::STYLE_NS, 'num-format')),
            'numPrefix' => self::nullable(self::attr($configuration, self::STYLE_NS, 'num-prefix')),
            'numSuffix' => self::nullable(self::attr($configuration, self::STYLE_NS, 'num-suffix')),
            'numLetterSync' => self::nullableBool(self::attr($configuration, self::STYLE_NS, 'num-letter-sync')),
            'footnotesPosition' => self::nullable(self::attr($configuration, self::TEXT_NS, 'footnotes-position')),
            'startNumberingAt' => self::nullable(self::attr($configuration, self::TEXT_NS, 'start-numbering-at')),
            'noteContinuationNoticeForward' => self::nullable(self::attr($configuration, self::TEXT_NS, 'note-continuation-notice-forward')),
            'noteContinuationNoticeBackward' => self::nullable(self::attr($configuration, self::TEXT_NS, 'note-continuation-notice-backward')),
            'footnoteSeparator' => $footnoteSeparator instanceof \DOMElement ? $this->footnoteSeparatorDefinition($footnoteSeparator) : null,
        ]);
    }

    /**
     * @return array<string, string>
     */
    private function footnoteSeparatorDefinition(\DOMElement $separator): array
    {
        return self::withoutEmpty([
            'width' => self::nullable(self::attr($separator, self::STYLE_NS, 'width')),
            'distanceBeforeSep' => self::nullable(self::attr($separator, self::STYLE_NS, 'distance-before-sep')),
            'distanceAfterSep' => self::nullable(self::attr($separator, self::STYLE_NS, 'distance-after-sep')),
            'lineStyle' => self::nullable(self::attr($separator, self::STYLE_NS, 'line-style')),
            'adjustment' => self::nullable(self::attr($separator, self::STYLE_NS, 'adjustment')),
            'relWidth' => self::nullable(self::attr($separator, self::STYLE_NS, 'rel-width')),
            'color' => self::nullable(self::attr($separator, self::STYLE_NS, 'color')),
        ]);
    }

    private function formControlNode(\DOMElement $controlReference, ?\DOMElement $frame, bool $inline): ?AstNode
    {
        $controlId = self::attr($controlReference, self::DRAW_NS, 'control');
        if ($controlId === '') {
            $controlId = self::attr($controlReference, self::FORM_NS, 'id');
        }
        if ($controlId === '') {
            return null;
        }

        $definition = $this->formControlsById[$controlId] ?? null;
        $exists = is_array($definition);
        $type = $exists ? (string) ($definition['type'] ?? '') : '';
        $label = $this->formControlLabel($controlId, $definition, $frame);

        $attributes = [
            'data-odf-control-id' => $controlId,
        ];
        if ($type !== '') {
            $attributes['data-odf-control-type'] = $type;
        }
        $attributes['data-odf-control-exists'] = $exists ? 'true' : 'false';

        if (is_array($definition)) {
            foreach ([
                'formName',
                'name',
                'label',
                'implementation',
                'value',
                'currentValue',
                'currentState',
                'linkedCell',
                'sourceCellRange',
                'listSource',
                'listSourceType',
                'boundColumn',
                'dropdown',
                'multiple',
                'automaticCompletion',
                'optionCount',
                'selectedOptionCount',
                'selectedOptionLabels',
                'selectedOptionValues',
                'tabIndex',
                'href',
                'disabled',
                'printable',
                'formAction',
                'formMethod',
                'formEnctype',
                'formTargetFrame',
                'formCommand',
                'formCommandType',
                'formDatasource',
                'formFilter',
                'formOrder',
                'formApplyFilter',
                'formIgnoreResult',
                'formEscapeProcessing',
                'formNavigationMode',
                'formTabCycle',
                'formMasterFields',
                'formDetailFields',
                'formDefaultControlImplementation',
                'formXlinkType',
                'formXlinkShow',
                'formXlinkActuate',
            ] as $name) {
                if (!array_key_exists($name, $definition)) {
                    continue;
                }
                $value = $definition[$name];
                $attributes['data-odf-control-' . self::kebabCase($name)] = is_bool($value)
                    ? ($value ? 'true' : 'false')
                    : (string) $value;
            }
        }

        $frameMetadata = $frame instanceof \DOMElement ? $this->formControlFrameMetadata($frame) : [];
        foreach ($frameMetadata as $name => $value) {
            $attributes['data-odf-control-' . self::kebabCase($name)] = $value;
        }

        $classes = ['odf-form-control'];
        if ($type !== '') {
            $classes[] = 'odf-control-' . self::formControlClassSuffix($type);
        }
        if (!$exists) {
            $classes[] = 'odf-missing-form-control';
        }

        $attrs = [
            'sourceFormat' => 'odt-form-control',
            'controlId' => $controlId,
            'exists' => $exists,
            'classes' => $classes,
            'attributes' => $attributes,
        ];
        if ($type !== '') {
            $attrs['controlType'] = $type;
        }
        if (is_array($definition)) {
            $attrs['formControl'] = $definition;
        }
        foreach ($frameMetadata as $name => $value) {
            $attrs[$name] = $value;
        }

        $text = new AstNode('text', ['text' => $label]);
        if ($inline) {
            return new AstNode('span', $attrs, [$text]);
        }

        return new AstNode('div', $attrs, [
            new AstNode('paragraph', [
                'sourceFormat' => 'odt',
                'text' => $label,
            ], [$text]),
        ]);
    }

    /**
     * @param ?array<string, mixed> $definition
     */
    private function formControlLabel(string $controlId, ?array $definition, ?\DOMElement $frame): string
    {
        if (is_array($definition)) {
            foreach (['label', 'currentValue', 'value', 'selectedOptionLabels', 'name'] as $name) {
                $value = (string) ($definition[$name] ?? '');
                if ($value !== '') {
                    return $value;
                }
            }
        }

        if ($frame instanceof \DOMElement) {
            $title = self::firstChildElement($frame, 'title', self::SVG_NS);
            if ($title instanceof \DOMElement && self::normalizedText($title) !== '') {
                return self::normalizedText($title);
            }
            $name = self::attr($frame, self::DRAW_NS, 'name');
            if ($name !== '') {
                return $name;
            }
        }

        return $controlId;
    }

    /**
     * @return array<string, string>
     */
    private function formControlFrameMetadata(\DOMElement $frame): array
    {
        $metadata = [];
        $name = self::attr($frame, self::DRAW_NS, 'name');
        if ($name !== '') {
            $metadata['frameName'] = $name;
        }

        foreach (['width', 'height'] as $dimension) {
            $value = self::attr($frame, self::SVG_NS, $dimension);
            if ($value !== '') {
                $metadata[$dimension] = $value;
            }
        }

        return $metadata;
    }

    /**
     * @return list<string>
     */
    private static function formControlElementNames(): array
    {
        return [
            'button',
            'checkbox',
            'combobox',
            'currency',
            'date',
            'file',
            'fixed-text',
            'formatted-text',
            'generic-control',
            'grid',
            'hidden',
            'image',
            'image-frame',
            'listbox',
            'numeric',
            'password',
            'pattern',
            'radio',
            'text',
            'textarea',
            'time',
            'value-range',
        ];
    }

    private static function formControlClassSuffix(string $type): string
    {
        $suffix = strtolower(preg_replace('/[^A-Za-z0-9]+/', '-', $type) ?? '');
        $suffix = trim($suffix, '-');

        return $suffix === '' ? 'unknown' : $suffix;
    }

    /**
     * @param array{styles:array<string, array<string, mixed>>, listStyles:array<string, array<string, mixed>>} $catalog
     * @return list<AstNode>
     */
    private function inlineNodes(\DOMElement $parent, array $catalog, ?ZipPackage $package = null): array
    {
        $children = [];
        foreach ($parent->childNodes as $child) {
            $children[] = $child;
        }

        return $this->inlineNodesFromNodeList($children, $catalog, $package);
    }

    /**
     * @param list<\DOMNode> $children
     * @param array{styles:array<string, array<string, mixed>>, listStyles:array<string, array<string, mixed>>} $catalog
     * @return list<AstNode>
     */
    private function inlineNodesFromNodeList(array $children, array $catalog, ?ZipPackage $package = null): array
    {
        $nodes = [];
        for ($index = 0, $count = count($children); $index < $count; $index++) {
            $child = $children[$index];
            if ($child instanceof \DOMText || $child instanceof \DOMCdataSection) {
                if ($child->textContent !== '') {
                    $nodes[] = new AstNode('text', ['text' => $child->textContent]);
                }
                continue;
            }
            if (!$child instanceof \DOMElement) {
                continue;
            }

            if ($this->isElement($child, self::TEXT_NS, 'change-start')) {
                $changeId = self::attr($child, self::TEXT_NS, 'change-id');
                $range = $changeId === '' ? null : $this->trackedChangeRange($children, $index, $changeId);
                if ($range !== null) {
                    $inner = $this->coalesceTextNodes($this->inlineNodesFromNodeList($range['nodes'], $catalog, $package));
                    $node = $this->trackedChangeSpanNode($changeId, $inner);
                    if ($node instanceof AstNode) {
                        $nodes[] = $node;
                    }
                    $index = $range['endIndex'];
                    continue;
                }
            }
            if ($this->isElement($child, self::TEXT_NS, 'change')) {
                $changeId = self::attr($child, self::TEXT_NS, 'change-id');
                $node = $this->standaloneTrackedChangeNode($changeId);
                if ($node instanceof AstNode) {
                    $nodes[] = $node;
                }
                continue;
            }
            if ($this->isElement($child, self::TEXT_NS, 'change-end')) {
                continue;
            }
            if ($this->isElement($child, self::TEXT_NS, 'span')) {
                array_push($nodes, ...$this->spanNodes($child, $catalog, $package));
                continue;
            }
            if ($this->isElement($child, self::TEXT_NS, 'ruby')) {
                $ruby = $this->rubyNode($child, $catalog, $package);
                if ($ruby instanceof AstNode) {
                    $nodes[] = $ruby;
                }
                continue;
            }
            if ($this->isElement($child, self::TEXT_NS, 'meta') || $this->isElement($child, self::TEXT_NS, 'meta-field')) {
                $meta = $this->metaSpanNode($child, $catalog, $package);
                if ($meta instanceof AstNode) {
                    $nodes[] = $meta;
                }
                continue;
            }
            if ($this->isElement($child, self::TEXT_NS, 'a')) {
                $nodes[] = $this->linkNode($child, $catalog, $package);
                continue;
            }
            if ($this->isElement($child, self::TEXT_NS, 's')) {
                $spaceCount = max(1, self::intAttr($child, self::TEXT_NS, 'c', 1));
                $nodes[] = new AstNode('text', ['text' => str_repeat(' ', $spaceCount)]);
                continue;
            }
            if ($this->isElement($child, self::TEXT_NS, 'tab')) {
                $nodes[] = new AstNode('text', ['text' => ' ']);
                continue;
            }
            if ($this->isElement($child, self::TEXT_NS, 'line-break')) {
                $nodes[] = new AstNode('linebreak');
                continue;
            }
            if ($this->isElement($child, self::TEXT_NS, 'soft-page-break')) {
                $nodes[] = $this->softPageBreakNode();
                continue;
            }
            if ($this->isIndexMarkStartElement($child)) {
                $markId = self::attr($child, self::TEXT_NS, 'id');
                $range = $markId === '' ? null : $this->indexMarkRange($children, $index, $markId, $this->indexMarkEndElementName($child->localName));
                if ($range !== null) {
                    $inner = $this->coalesceTextNodes($this->inlineNodesFromNodeList($range['nodes'], $catalog, $package));
                    $node = $this->indexMarkNode($child, $catalog, $package, $inner);
                    if ($node instanceof AstNode) {
                        $nodes[] = $node;
                    }
                    $index = $range['endIndex'];
                    continue;
                }
            }
            if ($this->isIndexMarkElement($child)) {
                $indexMark = $this->indexMarkNode($child, $catalog, $package);
                if ($indexMark instanceof AstNode) {
                    $nodes[] = $indexMark;
                }
                continue;
            }
            if ($this->isIndexMarkEndElement($child)) {
                continue;
            }
            if ($this->isTextFieldElement($child)) {
                $field = $this->fieldNode($child, $catalog, $package);
                if ($field instanceof AstNode) {
                    $nodes[] = $field;
                }
                continue;
            }
            if ($this->isElement($child, self::TEXT_NS, 'placeholder')) {
                $placeholder = $this->placeholderNode($child, $catalog, $package);
                if ($placeholder instanceof AstNode) {
                    $nodes[] = $placeholder;
                }
                continue;
            }
            if ($this->isElement($child, self::TEXT_NS, 'sequence')) {
                $sequence = $this->sequenceNode($child, $catalog, $package);
                if ($sequence instanceof AstNode) {
                    $nodes[] = $sequence;
                }
                continue;
            }
            if ($this->isElement($child, self::TEXT_NS, 'bibliography-mark')) {
                $citation = $this->bibliographyMarkNode($child, $catalog, $package);
                if ($citation instanceof AstNode) {
                    $nodes[] = $citation;
                }
                continue;
            }
            if ($this->isElement($child, self::DRAW_NS, 'frame')) {
                $control = self::firstChildElement($child, 'control', self::DRAW_NS);
                if ($control instanceof \DOMElement) {
                    $controlNode = $this->formControlNode($control, $child, true);
                    if ($controlNode instanceof AstNode) {
                        $nodes[] = $controlNode;
                    }
                    continue;
                }
                $textBoxCaptionImage = $this->frameTextBoxCaptionImageNode($child, $catalog, $package);
                if ($textBoxCaptionImage instanceof AstNode) {
                    $nodes[] = $textBoxCaptionImage;
                    continue;
                }
                $image = $this->frameImageNode($child, $package);
                if ($image instanceof AstNode) {
                    $nodes[] = $image;
                    continue;
                }
                $math = $this->frameObjectMathNode($child, $package);
                if ($math instanceof AstNode) {
                    $nodes[] = $math;
                    continue;
                }
                $objectOle = $this->frameObjectOleNode($child, $package, true);
                if ($objectOle instanceof AstNode) {
                    $nodes[] = $objectOle;
                    continue;
                }
                $object = $this->frameObjectNode($child, $package, true);
                if ($object instanceof AstNode) {
                    $nodes[] = $object;
                }
                continue;
            }
            if ($this->isElement($child, self::DRAW_NS, 'control')) {
                $control = $this->formControlNode($child, null, true);
                if ($control instanceof AstNode) {
                    $nodes[] = $control;
                }
                continue;
            }
            if ($this->isElement($child, self::TEXT_NS, 'note')) {
                $nodes[] = $this->noteNode($child, $package, $catalog);
                continue;
            }
            if ($this->isElement($child, self::TEXT_NS, 'bookmark-start') || $this->isElement($child, self::TEXT_NS, 'bookmark')) {
                $bookmark = $this->bookmarkAnchorNode($child);
                if ($bookmark instanceof AstNode) {
                    $nodes[] = $bookmark;
                }
                continue;
            }
            if ($this->isElement($child, self::TEXT_NS, 'reference-mark-start')) {
                $name = self::attr($child, self::TEXT_NS, 'name');
                $range = $name === '' ? null : $this->referenceMarkRange($children, $index, $name);
                if ($range !== null) {
                    $inner = $this->coalesceTextNodes($this->inlineNodesFromNodeList($range['nodes'], $catalog, $package));
                    $referenceMark = $this->referenceMarkAnchorNode($child, $inner);
                    if ($referenceMark instanceof AstNode) {
                        $nodes[] = $referenceMark;
                    }
                    $index = $range['endIndex'];
                    continue;
                }
            }
            if ($this->isElement($child, self::TEXT_NS, 'reference-mark') || $this->isElement($child, self::TEXT_NS, 'reference-mark-start')) {
                $referenceMark = $this->referenceMarkAnchorNode($child);
                if ($referenceMark instanceof AstNode) {
                    $nodes[] = $referenceMark;
                }
                continue;
            }
            if ($this->isElement($child, self::TEXT_NS, 'reference-mark-end')) {
                continue;
            }
            if ($this->isElement($child, self::TEXT_NS, 'bookmark-ref')) {
                $bookmarkRef = $this->bookmarkReferenceNode($child, $catalog, $package);
                if ($bookmarkRef instanceof AstNode) {
                    $nodes[] = $bookmarkRef;
                }
                continue;
            }
            if ($this->isElement($child, self::TEXT_NS, 'reference-ref')) {
                $reference = $this->referenceReferenceNode($child, $catalog, $package);
                if ($reference instanceof AstNode) {
                    $nodes[] = $reference;
                }
                continue;
            }
            if ($this->isElement($child, self::OFFICE_NS, 'annotation')) {
                $annotationName = self::attr($child, self::OFFICE_NS, 'name');
                $range = $annotationName === '' ? null : $this->annotationRange($children, $index, $annotationName);
                if ($range !== null) {
                    $inner = $this->coalesceTextNodes($this->inlineNodesFromNodeList($range['nodes'], $catalog, $package));
                    $node = $this->annotationRangeSpanNode($child, $annotationName, $inner, $catalog, $package);
                    if ($node instanceof AstNode) {
                        $nodes[] = $node;
                    }
                    $index = $range['endIndex'];
                    continue;
                }

                $nodes[] = $this->annotationNoteNode($child, null, $catalog);
                continue;
            }
            if ($this->isElement($child, self::OFFICE_NS, 'annotation-end')) {
                continue;
            }
        }

        return $nodes;
    }

    /**
     * @param array{styles:array<string, array<string, mixed>>, listStyles:array<string, array<string, mixed>>} $catalog
     */
    private function rubyNode(\DOMElement $ruby, array $catalog, ?ZipPackage $package): ?AstNode
    {
        $base = self::firstChildElement($ruby, 'ruby-base', self::TEXT_NS);
        $baseNodes = $base instanceof \DOMElement
            ? $this->coalesceTextNodes($this->inlineNodes($base, $catalog, $package))
            : $this->coalesceTextNodes($this->rubyFallbackBaseNodes($ruby, $catalog, $package));
        if ($baseNodes === []) {
            return null;
        }

        $rubyText = self::firstChildElement($ruby, 'ruby-text', self::TEXT_NS);
        $annotation = '';
        $textStyleName = '';
        if ($rubyText instanceof \DOMElement) {
            $annotationNodes = $this->coalesceTextNodes($this->inlineNodes($rubyText, $catalog, $package));
            $annotation = $this->plainInlineText($annotationNodes);
            if ($annotation === '') {
                $annotation = self::normalizedText($rubyText);
            }
            $textStyleName = self::attr($rubyText, self::TEXT_NS, 'style-name');
        }

        $styleName = self::attr($ruby, self::TEXT_NS, 'style-name');
        $attrs = [
            'sourceFormat' => 'odt',
            'classes' => ['odf-ruby'],
            'attributes' => [],
        ];
        if ($annotation !== '') {
            $attrs['rubyText'] = $annotation;
            $attrs['attributes']['data-odf-ruby-text'] = $annotation;
        }
        if ($styleName !== '') {
            $attrs['rubyStyleName'] = $styleName;
            $attrs['attributes']['data-odf-ruby-style-name'] = $styleName;
        }
        if ($textStyleName !== '') {
            $attrs['rubyTextStyleName'] = $textStyleName;
            $attrs['attributes']['data-odf-ruby-text-style-name'] = $textStyleName;
        }

        return new AstNode('span', $attrs, $baseNodes);
    }

    /**
     * @param array{styles:array<string, array<string, mixed>>, listStyles:array<string, array<string, mixed>>} $catalog
     * @return list<AstNode>
     */
    private function rubyFallbackBaseNodes(\DOMElement $ruby, array $catalog, ?ZipPackage $package): array
    {
        $children = [];
        foreach ($ruby->childNodes as $child) {
            if ($child instanceof \DOMElement && $this->isElement($child, self::TEXT_NS, 'ruby-text')) {
                continue;
            }
            $children[] = $child;
        }

        return $this->inlineNodesFromNodeList($children, $catalog, $package);
    }

    /**
     * @param array{styles:array<string, array<string, mixed>>, listStyles:array<string, array<string, mixed>>} $catalog
     */
    private function linkNode(\DOMElement $link, array $catalog, ?ZipPackage $package): AstNode
    {
        $attrs = [
            'url' => self::fixRelativeLink(self::attr($link, self::XLINK_NS, 'href')),
        ];
        $title = self::attr($link, self::OFFICE_NS, 'title');
        if ($title !== '') {
            $attrs['title'] = $title;
        }

        $metadata = self::withoutEmpty([
            'name' => self::nullable(self::attr($link, self::OFFICE_NS, 'name')),
            'styleName' => self::nullable(self::attr($link, self::TEXT_NS, 'style-name')),
            'visitedStyleName' => self::nullable(self::attr($link, self::TEXT_NS, 'visited-style-name')),
            'targetFrameName' => self::nullable(self::attr($link, self::OFFICE_NS, 'target-frame-name')),
            'type' => self::nullable(self::attr($link, self::XLINK_NS, 'type')),
            'show' => self::nullable(self::attr($link, self::XLINK_NS, 'show')),
            'actuate' => self::nullable(self::attr($link, self::XLINK_NS, 'actuate')),
        ]);
        $eventListeners = $this->eventListenersMetadata($link);
        if ($eventListeners !== []) {
            $metadata['eventListeners'] = $eventListeners;
            $metadata['eventListenerCount'] = count($eventListeners);
        }
        if ($metadata !== []) {
            $attrs['sourceFormat'] = 'odt';
            $attrs['odfLinkMetadata'] = $metadata;
            $attrs['classes'] = ['odf-link'];
            $attrs['attributes'] = [];
            foreach ($metadata as $name => $value) {
                if (!is_scalar($value)) {
                    continue;
                }
                $attrs['attributes']['data-odf-link-' . self::kebabCase((string) $name)] = (string) $value;
            }
            $attrs['attributes'] += $this->eventListenerAttributes('data-odf-link', $eventListeners);
        }

        return new AstNode('link', $attrs, $this->coalesceTextNodes($this->inlineNodes($link, $catalog, $package)));
    }

    /**
     * @return list<array<string, string>>
     */
    private function eventListenersMetadata(\DOMElement $element): array
    {
        $container = self::firstChildElement($element, 'event-listeners', self::OFFICE_NS);
        if (!$container instanceof \DOMElement) {
            return [];
        }

        $listeners = [];
        foreach (self::childElements($container, 'event-listener', self::SCRIPT_NS) as $listener) {
            $metadata = self::withoutEmpty([
                'eventName' => self::nullable(self::attr($listener, self::SCRIPT_NS, 'event-name')),
                'language' => self::nullable(self::attr($listener, self::SCRIPT_NS, 'language')),
                'href' => self::nullable(self::attr($listener, self::XLINK_NS, 'href')),
                'type' => self::nullable(self::attr($listener, self::XLINK_NS, 'type')),
                'show' => self::nullable(self::attr($listener, self::XLINK_NS, 'show')),
                'actuate' => self::nullable(self::attr($listener, self::XLINK_NS, 'actuate')),
                'macroName' => self::nullable(self::attr($listener, self::SCRIPT_NS, 'macro-name')),
            ]);
            if ($metadata === []) {
                continue;
            }

            $listeners[] = array_map(static fn (mixed $value): string => (string) $value, $metadata);
        }

        return $listeners;
    }

    /**
     * @param list<array<string, string>> $listeners
     * @return array<string, string>
     */
    private function eventListenerAttributes(string $prefix, array $listeners): array
    {
        if ($listeners === []) {
            return [];
        }

        $attributes = [
            $prefix . '-event-listener-count' => (string) count($listeners),
        ];
        foreach ($listeners as $index => $listener) {
            $ordinal = $index + 1;
            foreach ($listener as $name => $value) {
                if ($value === '') {
                    continue;
                }
                $attributeName = $name === 'eventName' ? 'name' : self::kebabCase($name);
                $attributes[$prefix . '-event-' . $ordinal . '-' . $attributeName] = $value;
            }
        }

        return $attributes;
    }

    private static function fixRelativeLink(string $uri): string
    {
        if (!str_starts_with($uri, '../')) {
            return $uri;
        }

        return substr($uri, 3);
    }

    private function softPageBreakNode(): AstNode
    {
        return new AstNode('span', [
            'sourceFormat' => 'odt',
            'softPageBreak' => true,
            'classes' => ['odf-soft-page-break'],
            'attributes' => [
                'data-odf-soft-page-break' => 'true',
            ],
        ]);
    }

    private function isIndexMarkElement(\DOMElement $element): bool
    {
        if ($element->namespaceURI !== self::TEXT_NS) {
            return false;
        }

        return in_array($element->localName, [
            'toc-mark',
            'toc-mark-start',
            'alphabetical-index-mark',
            'alphabetical-index-mark-start',
            'user-index-mark',
            'user-index-mark-start',
        ], true);
    }

    private function isIndexMarkStartElement(\DOMElement $element): bool
    {
        if ($element->namespaceURI !== self::TEXT_NS) {
            return false;
        }

        return in_array($element->localName, [
            'toc-mark-start',
            'alphabetical-index-mark-start',
            'user-index-mark-start',
        ], true);
    }

    private function isIndexMarkEndElement(\DOMElement $element): bool
    {
        if ($element->namespaceURI !== self::TEXT_NS) {
            return false;
        }

        return in_array($element->localName, [
            'toc-mark-end',
            'alphabetical-index-mark-end',
            'user-index-mark-end',
        ], true);
    }

    private function indexMarkEndElementName(string $startElement): string
    {
        return substr($startElement, 0, -strlen('-start')) . '-end';
    }

    /**
     * @param list<\DOMNode> $children
     * @return ?array{nodes:list<\DOMNode>, endIndex:int}
     */
    private function indexMarkRange(array $children, int $startIndex, string $id, string $endElement): ?array
    {
        $range = [];
        for ($index = $startIndex + 1, $count = count($children); $index < $count; $index++) {
            $child = $children[$index];
            if ($child instanceof \DOMElement && $this->isElement($child, self::TEXT_NS, $endElement)) {
                $endId = self::attr($child, self::TEXT_NS, 'id');
                if ($endId === $id) {
                    return [
                        'nodes' => $range,
                        'endIndex' => $index,
                    ];
                }
            }

            $range[] = $child;
        }

        return null;
    }

    /**
     * @param array{styles:array<string, array<string, mixed>>, listStyles:array<string, array<string, mixed>>} $catalog
     * @param list<AstNode>|null $children
     */
    private function indexMarkNode(\DOMElement $mark, array $catalog, ?ZipPackage $package, ?array $children = null): ?AstNode
    {
        $children ??= $this->coalesceTextNodes($this->inlineNodes($mark, $catalog, $package));
        if ($children === []) {
            $text = (string) ($this->indexMarkMetadata($mark)['stringValue'] ?? '');
            if ($text === '') {
                $text = self::normalizedText($mark);
            }
            if ($text === '') {
                return null;
            }
            $children = [new AstNode('text', ['text' => $text])];
        }

        $type = $this->indexMarkType($mark->localName);
        $metadata = $this->indexMarkMetadata($mark);
        $attributes = [
            'data-odf-index-mark-type' => $type,
            'data-odf-index-mark-element' => $mark->localName,
        ];
        foreach ($metadata as $name => $value) {
            if ($value === null || $value === '' || is_array($value)) {
                continue;
            }
            $attributes['data-odf-index-mark-' . self::kebabCase((string) $name)] = is_bool($value)
                ? ($value ? 'true' : 'false')
                : (string) $value;
        }

        return new AstNode('span', [
            'sourceFormat' => 'odt',
            'indexMarkType' => $type,
            'indexMarkElement' => $mark->localName,
            'indexMarkMetadata' => $metadata,
            'classes' => ['odf-index-mark', 'odf-index-mark-' . $type],
            'attributes' => $attributes,
        ], $children);
    }

    private function indexMarkType(string $element): string
    {
        if (str_starts_with($element, 'toc-mark')) {
            return 'toc';
        }
        if (str_starts_with($element, 'user-index-mark')) {
            return 'user';
        }

        return 'alphabetical';
    }

    /**
     * @return array<string, mixed>
     */
    private function indexMarkMetadata(\DOMElement $mark): array
    {
        return $this->odfElementMetadataAttributes($mark, [self::TEXT_NS]);
    }

    private function isTextFieldElement(\DOMElement $element): bool
    {
        if ($element->namespaceURI !== self::TEXT_NS) {
            return false;
        }

        return in_array($element->localName, [
            'variable-set',
            'variable-get',
            'variable-input',
            'user-field-get',
            'user-field-input',
            'expression',
            'measure',
            'text-input',
            'drop-down',
            'script',
            'execute-macro',
            'dde-connection',
            'conditional-text',
            'hidden-text',
            'hidden-paragraph',
            'database-display',
            'database-name',
            'database-next',
            'database-row-number',
            'database-row-select',
            'page-number',
            'page-count',
            'page-variable-set',
            'page-variable-get',
            'chapter',
            'file-name',
            'template-name',
            'line-number',
            'word-count',
            'sentence-count',
            'paragraph-count',
            'character-count',
            'table-count',
            'image-count',
            'object-count',
            'date',
            'time',
            'title',
            'subject',
            'description',
            'keywords',
            'user-defined',
            'initial-creator',
            'creation-date',
            'creation-time',
            'modification-date',
            'modification-time',
            'printed-by',
            'print-date',
            'print-time',
            'editing-cycles',
            'editing-duration',
            'author-name',
            'author-initials',
            'sender-firstname',
            'sender-lastname',
            'sender-initials',
            'sender-title',
            'sender-position',
            'sender-email',
            'sender-phone-private',
            'sender-phone-work',
            'sender-fax',
            'sender-company',
            'sender-street',
            'sender-city',
            'sender-postal-code',
            'sender-country',
            'sender-state-or-province',
        ], true);
    }

    /**
     * @param array{styles:array<string, array<string, mixed>>, listStyles:array<string, array<string, mixed>>} $catalog
     */
    private function placeholderNode(\DOMElement $placeholder, array $catalog, ?ZipPackage $package): ?AstNode
    {
        $children = $this->coalesceTextNodes($this->inlineNodes($placeholder, $catalog, $package));
        if ($children === []) {
            $text = self::normalizedText($placeholder);
            if ($text === '') {
                return null;
            }
            $children = [new AstNode('text', ['text' => $text])];
        }

        $metadata = $this->placeholderMetadata($placeholder);
        $placeholderType = (string) ($metadata['type'] ?? 'unknown');
        $attributes = [];
        foreach ($metadata as $name => $value) {
            if ($value === null || $value === '') {
                continue;
            }
            $attributes['data-odf-placeholder-' . self::kebabCase((string) $name)] = (string) $value;
        }

        return new AstNode('span', self::withoutEmpty([
            'sourceFormat' => 'odt',
            'placeholderType' => $placeholderType,
            'placeholderMetadata' => $metadata,
            'classes' => ['odf-placeholder', 'odf-placeholder-' . self::placeholderClassSuffix($placeholderType)],
            'attributes' => $attributes,
        ]), $children);
    }

    /**
     * @return array<string, string>
     */
    private function placeholderMetadata(\DOMElement $placeholder): array
    {
        return self::withoutEmpty([
            'type' => self::nullable(self::attr($placeholder, self::TEXT_NS, 'placeholder-type')),
            'description' => self::nullable(self::attr($placeholder, self::TEXT_NS, 'description')),
            'styleName' => self::nullable(self::attr($placeholder, self::TEXT_NS, 'style-name')),
        ]);
    }

    private static function placeholderClassSuffix(string $type): string
    {
        $suffix = strtolower(preg_replace('/[^A-Za-z0-9]+/', '-', $type) ?? '');
        $suffix = trim($suffix, '-');

        return $suffix === '' ? 'unknown' : $suffix;
    }

    /**
     * @param array{styles:array<string, array<string, mixed>>, listStyles:array<string, array<string, mixed>>} $catalog
     */
    private function metaSpanNode(\DOMElement $meta, array $catalog, ?ZipPackage $package): ?AstNode
    {
        $children = $this->coalesceTextNodes($this->inlineNodes($meta, $catalog, $package));
        $metadata = $this->metaSpanMetadata($meta);
        if ($children === []) {
            $text = $this->metaSpanFallbackText($metadata);
            if ($text === '') {
                return null;
            }
            $children = [new AstNode('text', ['text' => $text])];
        }

        $metaType = $meta->localName;
        $classes = ['odf-meta'];
        if ($metaType === 'meta-field') {
            $classes[] = 'odf-meta-field';
        }

        $attributes = [
            'data-odf-meta-type' => $metaType,
        ];
        foreach ($metadata as $name => $value) {
            if ($value === null || $value === '' || is_array($value)) {
                continue;
            }
            $attributes['data-odf-meta-' . self::kebabCase((string) $name)] = is_bool($value)
                ? ($value ? 'true' : 'false')
                : (string) $value;
        }

        return new AstNode('span', [
            'sourceFormat' => 'odt',
            'metaType' => $metaType,
            'metaMetadata' => $metadata,
            'classes' => $classes,
            'attributes' => $attributes,
        ], $children);
    }

    /**
     * @return array<string, mixed>
     */
    private function metaSpanMetadata(\DOMElement $meta): array
    {
        $sourceId = self::attr($meta, self::XML_NS, 'id');
        if ($sourceId === '') {
            $sourceId = self::attr($meta, self::TEXT_NS, 'id');
        }
        $valueType = self::attr($meta, self::OFFICE_NS, 'value-type');
        if ($valueType === '') {
            $valueType = self::attr($meta, self::TEXT_NS, 'value-type');
        }
        $stringValue = self::attr($meta, self::OFFICE_NS, 'string-value');
        if ($stringValue === '') {
            $stringValue = self::attr($meta, self::TEXT_NS, 'string-value');
        }
        $dateValue = self::attr($meta, self::OFFICE_NS, 'date-value');
        if ($dateValue === '') {
            $dateValue = self::attr($meta, self::TEXT_NS, 'date-value');
        }
        $timeValue = self::attr($meta, self::OFFICE_NS, 'time-value');
        if ($timeValue === '') {
            $timeValue = self::attr($meta, self::TEXT_NS, 'time-value');
        }

        return self::withoutEmpty([
            'sourceId' => self::nullable($sourceId),
            'name' => self::nullable(self::attr($meta, self::TEXT_NS, 'name')),
            'description' => self::nullable(self::attr($meta, self::TEXT_NS, 'description')),
            'valueType' => self::nullable($valueType),
            'stringValue' => self::nullable($stringValue),
            'value' => self::nullable(self::attr($meta, self::OFFICE_NS, 'value')),
            'booleanValue' => self::nullableBool(self::attr($meta, self::OFFICE_NS, 'boolean-value')),
            'dateValue' => self::nullable($dateValue),
            'timeValue' => self::nullable($timeValue),
            'fixed' => self::nullableBool(self::attr($meta, self::TEXT_NS, 'fixed')),
            'styleName' => self::nullable(self::attr($meta, self::TEXT_NS, 'style-name')),
        ]);
    }

    /**
     * @param array<string, mixed> $metadata
     */
    private function metaSpanFallbackText(array $metadata): string
    {
        foreach (['stringValue', 'value', 'dateValue', 'timeValue', 'booleanValue', 'sourceId', 'name'] as $key) {
            $value = $metadata[$key] ?? null;
            if ($value === null || $value === '' || is_array($value)) {
                continue;
            }

            return is_bool($value) ? ($value ? 'true' : 'false') : (string) $value;
        }

        return '';
    }

    /**
     * @param array{styles:array<string, array<string, mixed>>, listStyles:array<string, array<string, mixed>>} $catalog
     */
    private function fieldNode(\DOMElement $field, array $catalog, ?ZipPackage $package): ?AstNode
    {
        $children = $this->coalesceTextNodes($this->inlineNodes($field, $catalog, $package));
        $metadata = $this->fieldMetadata($field);
        if ($this->isElement($field, self::TEXT_NS, 'dde-connection')) {
            $metadata = $this->fieldMetadataWithDeclarations($field, $metadata);
        }
        $this->rememberVariableFieldValue($field, $metadata);
        if ($children === []) {
            $metadata = $this->fieldMetadataWithDeclarations($field, $metadata);
            $metadata = $this->fieldMetadataWithPackageMetadata($field, $metadata);
            $this->rememberVariableFieldValue($field, $metadata);
            $text = $this->fieldFallbackText($field, $metadata);
            if ($text === '') {
                return null;
            }
            $children = [new AstNode('text', ['text' => $text])];
        }

        $fieldType = $field->localName;
        $attributes = [
            'data-odf-field-type' => $fieldType,
        ];
        foreach ($metadata as $name => $value) {
            if ($value === null || $value === '' || is_array($value)) {
                continue;
            }
            $attributes['data-odf-field-' . self::kebabCase((string) $name)] = is_bool($value)
                ? ($value ? 'true' : 'false')
                : (string) $value;
        }

        $attrs = [
            'sourceFormat' => 'odt',
            'fieldType' => $fieldType,
            'fieldMetadata' => $metadata,
            'classes' => ['odf-field', 'odf-field-' . $fieldType],
            'attributes' => $attributes,
        ];
        if (isset($metadata['name']) && is_string($metadata['name']) && $metadata['name'] !== '') {
            $attrs['fieldName'] = $metadata['name'];
        }

        return new AstNode('span', $attrs, $children);
    }

    /**
     * @return array<string, mixed>
     */
    private function fieldMetadata(\DOMElement $field): array
    {
        $dateValue = self::attr($field, self::TEXT_NS, 'date-value');
        if ($dateValue === '') {
            $dateValue = self::attr($field, self::OFFICE_NS, 'date-value');
        }
        $timeValue = self::attr($field, self::TEXT_NS, 'time-value');
        if ($timeValue === '') {
            $timeValue = self::attr($field, self::OFFICE_NS, 'time-value');
        }
        $fixed = self::attr($field, self::TEXT_NS, 'fixed');
        $stringValue = self::attr($field, self::OFFICE_NS, 'string-value');
        if ($stringValue === '') {
            $stringValue = self::attr($field, self::TEXT_NS, 'string-value');
        }
        $currentValue = self::attr($field, self::TEXT_NS, 'current-value');
        if ($currentValue === '') {
            $currentValue = self::attr($field, self::OFFICE_NS, 'current-value');
        }
        $styleName = self::attr($field, self::TEXT_NS, 'style-name');
        if ($styleName === '') {
            $styleName = self::attr($field, self::STYLE_NS, 'data-style-name');
        }
        $numPrefix = $field->hasAttributeNS(self::STYLE_NS, 'num-prefix')
            ? $field->getAttributeNS(self::STYLE_NS, 'num-prefix')
            : '';
        $numSuffix = $field->hasAttributeNS(self::STYLE_NS, 'num-suffix')
            ? $field->getAttributeNS(self::STYLE_NS, 'num-suffix')
            : '';

        $metadata = self::withoutEmpty([
            'name' => self::nullable(self::attr($field, self::TEXT_NS, 'name')),
            'kind' => self::nullable(self::attr($field, self::TEXT_NS, 'kind')),
            'description' => self::nullable(self::attr($field, self::TEXT_NS, 'description')),
            'refName' => self::nullable(self::attr($field, self::TEXT_NS, 'ref-name')),
            'formula' => self::nullable(self::attr($field, self::TEXT_NS, 'formula')),
            'condition' => self::nullable(self::attr($field, self::TEXT_NS, 'condition')),
            'display' => self::nullable(self::attr($field, self::TEXT_NS, 'display')),
            'databaseName' => self::nullable(self::attr($field, self::TEXT_NS, 'database-name')),
            'tableName' => self::nullable(self::attr($field, self::TEXT_NS, 'table-name')),
            'tableType' => self::nullable(self::attr($field, self::TEXT_NS, 'table-type')),
            'columnName' => self::nullable(self::attr($field, self::TEXT_NS, 'column-name')),
            'rowNumber' => self::nullable(self::attr($field, self::TEXT_NS, 'row-number')),
            'connectionName' => self::nullable(self::attr($field, self::TEXT_NS, 'connection-name')),
            'href' => self::nullable(self::attr($field, self::XLINK_NS, 'href')),
            'xlinkType' => self::nullable(self::attr($field, self::XLINK_NS, 'type')),
            'scriptLanguage' => self::nullable(self::attr($field, self::SCRIPT_NS, 'language')),
            'outlineLevel' => self::nullableInt(self::attr($field, self::TEXT_NS, 'outline-level')),
            'valueType' => self::nullable(self::attr($field, self::OFFICE_NS, 'value-type')),
            'value' => self::nullable(self::attr($field, self::OFFICE_NS, 'value')),
            'currency' => self::nullable(self::attr($field, self::OFFICE_NS, 'currency')),
            'booleanValue' => self::nullableBool(self::attr($field, self::OFFICE_NS, 'boolean-value')),
            'currentValue' => self::nullable($currentValue),
            'stringValue' => self::nullable($stringValue),
            'stringValueIfTrue' => self::nullable(self::attr($field, self::TEXT_NS, 'string-value-if-true')),
            'stringValueIfFalse' => self::nullable(self::attr($field, self::TEXT_NS, 'string-value-if-false')),
            'dateValue' => self::nullable($dateValue),
            'timeValue' => self::nullable($timeValue),
            'selectPage' => self::nullable(self::attr($field, self::TEXT_NS, 'select-page')),
            'pageAdjust' => self::nullable(self::attr($field, self::TEXT_NS, 'page-adjust')),
            'dateAdjust' => self::nullable(self::attr($field, self::TEXT_NS, 'date-adjust')),
            'timeAdjust' => self::nullable(self::attr($field, self::TEXT_NS, 'time-adjust')),
            'numFormat' => self::nullable(self::attr($field, self::STYLE_NS, 'num-format')),
            'numPrefix' => self::nullable($numPrefix),
            'numSuffix' => self::nullable($numSuffix),
            'numLetterSync' => self::nullableBool(self::attr($field, self::STYLE_NS, 'num-letter-sync')),
            'formatSource' => self::nullable(self::attr($field, self::TEXT_NS, 'format-source')),
            'styleName' => self::nullable($styleName),
        ]);

        if ($this->isElement($field, self::TEXT_NS, 'drop-down')) {
            $metadata = array_merge($metadata, $this->dropDownFieldMetadata($field));
        }

        if ($fixed !== '') {
            $metadata['fixed'] = in_array(strtolower($fixed), ['true', '1'], true);
        }

        return $metadata;
    }

    /**
     * @param array<string, mixed> $metadata
     * @return array<string, mixed>
     */
    private function fieldMetadataWithPackageMetadata(\DOMElement $field, array $metadata): array
    {
        $source = $this->senderFieldSettingsMetadata($field->localName);
        if ($source === [] && $this->packageMetadata !== []) {
            $source = match ($field->localName) {
                'title' => $this->fieldStringMetadata('title'),
                'subject' => $this->fieldStringMetadata('subject'),
                'description' => $this->fieldStringMetadata('description'),
                'keywords' => $this->fieldKeywordsMetadata(),
                'author-name' => $this->fieldStringMetadata('creator'),
                'initial-creator' => $this->fieldStringMetadata('initialCreator'),
                'creation-date' => $this->fieldDateMetadata('created'),
                'creation-time' => $this->fieldTimeMetadata('creationTime'),
                'modification-date' => $this->fieldDateMetadata('modificationDate'),
                'modification-time' => $this->fieldTimeMetadata('modificationTime'),
                'printed-by' => $this->fieldStringMetadata('printedBy'),
                'print-date' => $this->fieldDateMetadata('printDate'),
                'print-time' => $this->fieldTimeMetadata('printTime'),
                'editing-cycles' => $this->fieldStringMetadata('editingCycles'),
                'editing-duration' => $this->fieldStringMetadata('editingDuration'),
                'template-name' => $this->fieldTemplateMetadata(),
                'user-defined' => $this->fieldUserDefinedPackageMetadata((string) ($metadata['name'] ?? '')),
                default => [],
            };
        }

        if ($source === []) {
            return $metadata;
        }

        $metadata = $this->fillMissingFieldMetadata($metadata, $source);
        if (isset($source['stringValue']) && (!isset($metadata['stringValue']) || $metadata['stringValue'] === '')) {
            $metadata['stringValue'] = $source['stringValue'];
        }
        foreach (['settingsSource', 'settingsSet', 'settingsName'] as $name) {
            if (isset($source[$name]) && (!isset($metadata[$name]) || $metadata[$name] === '')) {
                $metadata[$name] = $source[$name];
            }
        }
        if (!isset($metadata['metadataSource']) && !isset($source['settingsSource'])) {
            $metadata['metadataSource'] = 'meta.xml';
        }

        return $metadata;
    }

    /**
     * @return array<string, string>
     */
    private function senderFieldSettingsMetadata(string $fieldType): array
    {
        $settingNamesByField = [
            'sender-firstname' => ['FirstName', 'GivenName'],
            'sender-lastname' => ['LastName', 'FamilyName', 'Surname'],
            'sender-initials' => ['Initials'],
            'sender-title' => ['Title'],
            'sender-position' => ['Position'],
            'sender-email' => ['EMail', 'Email'],
            'sender-phone-private' => ['TelephoneHome', 'PhonePrivate'],
            'sender-phone-work' => ['TelephoneWork', 'PhoneWork'],
            'sender-fax' => ['Fax'],
            'sender-company' => ['Company'],
            'sender-street' => ['Street'],
            'sender-city' => ['City'],
            'sender-postal-code' => ['Zip', 'PostalCode'],
            'sender-country' => ['Country'],
            'sender-state-or-province' => ['State', 'StateOrProvince'],
        ];

        $settingNames = $settingNamesByField[$fieldType] ?? [];
        if ($settingNames === []) {
            return [];
        }

        $setsByName = $this->packageSettings['setsByName'] ?? [];
        if (!is_array($setsByName)) {
            return [];
        }

        foreach (['ooo:user-settings', 'ooo:configuration-settings'] as $setName) {
            $set = $setsByName[$setName] ?? null;
            if (!is_array($set)) {
                continue;
            }

            $items = $set['itemsByName'] ?? [];
            if (!is_array($items)) {
                continue;
            }

            foreach ($settingNames as $settingName) {
                $item = $items[$settingName] ?? null;
                if (!is_array($item)) {
                    continue;
                }

                $value = $item['typedValue'] ?? $item['value'] ?? null;
                if (is_bool($value)) {
                    $value = $value ? 'true' : 'false';
                }
                if (!is_scalar($value) || (string) $value === '') {
                    continue;
                }

                return [
                    'stringValue' => (string) $value,
                    'settingsSource' => 'settings.xml',
                    'settingsSet' => $setName,
                    'settingsName' => $settingName,
                ];
            }
        }

        return [];
    }

    /**
     * @return array<string, string>
     */
    private function fieldStringMetadata(string $name): array
    {
        $value = $this->packageMetadata[$name] ?? null;
        if (!is_scalar($value) || (string) $value === '') {
            return [];
        }

        return ['stringValue' => (string) $value];
    }

    /**
     * @return array<string, string>
     */
    private function fieldKeywordsMetadata(): array
    {
        $keywords = $this->packageMetadata['keywords'] ?? null;
        if (!is_array($keywords)) {
            return [];
        }

        $values = [];
        foreach ($keywords as $keyword) {
            if (is_scalar($keyword) && (string) $keyword !== '') {
                $values[] = (string) $keyword;
            }
        }

        return $values === [] ? [] : ['stringValue' => implode(', ', $values)];
    }

    /**
     * @return array<string, string>
     */
    private function fieldDateMetadata(string $name): array
    {
        $value = $this->packageMetadata[$name] ?? null;
        if (!is_scalar($value) || (string) $value === '') {
            return [];
        }

        return ['dateValue' => (string) $value];
    }

    /**
     * @return array<string, string>
     */
    private function fieldTimeMetadata(string $name): array
    {
        $value = $this->packageMetadata[$name] ?? null;
        if (!is_scalar($value) || (string) $value === '') {
            return [];
        }

        return ['timeValue' => (string) $value];
    }

    /**
     * @return array<string, string>
     */
    private function fieldTemplateMetadata(): array
    {
        $template = $this->packageMetadata['template'] ?? null;
        if (!is_array($template)) {
            return [];
        }

        foreach (['href', 'title'] as $name) {
            $value = $template[$name] ?? null;
            if (is_scalar($value) && (string) $value !== '') {
                return ['stringValue' => (string) $value];
            }
        }

        return [];
    }

    /**
     * @return array<string, mixed>
     */
    private function fieldUserDefinedPackageMetadata(string $name): array
    {
        if ($name === '') {
            return [];
        }

        $details = $this->packageMetadata['userDefinedDetails'][$name] ?? null;
        if (is_array($details)) {
            $metadata = [];
            foreach (['valueType', 'value', 'currency', 'booleanValue', 'stringValue', 'dateValue', 'timeValue'] as $key) {
                if (array_key_exists($key, $details)) {
                    $metadata[$key] = $details[$key];
                }
            }

            $displayValue = $details['displayValue'] ?? null;
            $hasConcreteValue = false;
            foreach (['value', 'booleanValue', 'dateValue', 'timeValue'] as $key) {
                if (array_key_exists($key, $metadata)) {
                    $hasConcreteValue = true;
                    break;
                }
            }
            if (!$hasConcreteValue && !isset($metadata['stringValue']) && is_scalar($displayValue) && (string) $displayValue !== '') {
                $metadata['stringValue'] = (string) $displayValue;
            }

            return self::withoutEmpty($metadata);
        }

        $value = $this->packageMetadata['userDefined'][$name] ?? null;
        if (!is_scalar($value) || (string) $value === '') {
            return [];
        }

        return ['stringValue' => (string) $value];
    }

    /**
     * @return array{labels?:list<array{value:string, selected:bool}>, labelCount?:int, selectedValue?:string}
     */
    private function dropDownFieldMetadata(\DOMElement $field): array
    {
        $labels = [];
        $selectedValue = '';
        foreach (self::childElements($field, 'label', self::TEXT_NS) as $label) {
            $value = self::attr($label, self::TEXT_NS, 'value');
            if ($value === '') {
                $value = self::normalizedText($label);
            }
            if ($value === '') {
                continue;
            }

            $selected = self::nullableBool(self::attr($label, self::TEXT_NS, 'current-selected'))
                ?? self::nullableBool(self::attr($label, self::TEXT_NS, 'selected'))
                ?? false;
            if ($selected && $selectedValue === '') {
                $selectedValue = $value;
            }

            $labels[] = [
                'value' => $value,
                'selected' => $selected,
            ];
        }

        if ($selectedValue === '' && isset($labels[0])) {
            $selectedValue = $labels[0]['value'];
        }

        return self::withoutEmpty([
            'labels' => $labels,
            'labelCount' => $labels === [] ? null : count($labels),
            'selectedValue' => self::nullable($selectedValue),
        ]);
    }

    /**
     * @param array<string, mixed> $metadata
     * @return array<string, mixed>
     */
    private function fieldMetadataWithDeclarations(\DOMElement $field, array $metadata): array
    {
        if ($this->isElement($field, self::TEXT_NS, 'dde-connection')) {
            return $this->fieldMetadataWithDdeDeclaration($metadata);
        }

        if ($this->isElement($field, self::TEXT_NS, 'variable-set')
            || $this->isElement($field, self::TEXT_NS, 'variable-get')
            || $this->isElement($field, self::TEXT_NS, 'variable-input')) {
            return $this->fieldMetadataWithVariableState($field, $metadata);
        }

        if (!$this->isElement($field, self::TEXT_NS, 'user-field-get')
            && !$this->isElement($field, self::TEXT_NS, 'user-field-input')) {
            return $metadata;
        }

        $name = (string) ($metadata['name'] ?? '');
        if ($name === '') {
            return $metadata;
        }

        $userFieldDeclarations = $this->contentDeclarations['userFieldDeclarations'] ?? [];
        if (!is_array($userFieldDeclarations)) {
            return $metadata;
        }

        $declaration = $userFieldDeclarations[$name] ?? null;
        if (!is_array($declaration)) {
            return $metadata;
        }

        foreach (['valueType', 'value', 'stringValue', 'dateValue', 'timeValue', 'booleanValue', 'currency'] as $key) {
            if (!array_key_exists($key, $declaration)) {
                continue;
            }
            if (!array_key_exists($key, $metadata) || $metadata[$key] === null || $metadata[$key] === '') {
                $metadata[$key] = $declaration[$key];
            }
        }
        $metadata['declared'] = true;

        return $metadata;
    }

    /**
     * @param array<string, mixed> $metadata
     * @return array<string, mixed>
     */
    private function fieldMetadataWithVariableState(\DOMElement $field, array $metadata): array
    {
        $name = (string) ($metadata['name'] ?? '');
        if ($name === '') {
            return $metadata;
        }

        $declared = false;
        $declarations = $this->contentDeclarations['variableDeclarations'] ?? [];
        if (is_array($declarations)) {
            $declaration = $declarations[$name] ?? null;
            if (is_array($declaration)) {
                $metadata = $this->fillMissingFieldMetadata($metadata, $declaration);
                $declared = true;
            }
        }

        if ($this->isElement($field, self::TEXT_NS, 'variable-get')) {
            $metadata = $this->fillMissingFieldMetadata(
                $metadata,
                $this->variableFieldValuesByName[$name] ?? []
            );
        }

        if ($declared) {
            $metadata['declared'] = true;
        }

        return $metadata;
    }

    /**
     * @param array<string, mixed> $metadata
     */
    private function rememberVariableFieldValue(\DOMElement $field, array $metadata): void
    {
        if (!$this->isElement($field, self::TEXT_NS, 'variable-set')
            && !$this->isElement($field, self::TEXT_NS, 'variable-input')) {
            return;
        }

        $name = (string) ($metadata['name'] ?? '');
        if ($name === '') {
            return;
        }

        $value = [];
        foreach (['valueType', 'value', 'stringValue', 'dateValue', 'timeValue', 'booleanValue', 'currency', 'currentValue'] as $key) {
            if (!array_key_exists($key, $metadata) || $metadata[$key] === null || $metadata[$key] === '') {
                continue;
            }
            $value[$key] = $metadata[$key];
        }

        if ($value !== []) {
            $this->variableFieldValuesByName[$name] = $value;
        }
    }

    /**
     * @param array<string, mixed> $metadata
     * @param array<string, mixed> $source
     * @return array<string, mixed>
     */
    private function fillMissingFieldMetadata(array $metadata, array $source): array
    {
        foreach (['valueType', 'value', 'stringValue', 'dateValue', 'timeValue', 'booleanValue', 'currency', 'currentValue'] as $key) {
            if (!array_key_exists($key, $source)) {
                continue;
            }
            if (!array_key_exists($key, $metadata) || $metadata[$key] === null || $metadata[$key] === '') {
                $metadata[$key] = $source[$key];
            }
        }

        return $metadata;
    }

    /**
     * @param array<string, mixed> $metadata
     * @return array<string, mixed>
     */
    private function fieldMetadataWithDdeDeclaration(array $metadata): array
    {
        $connectionName = (string) ($metadata['connectionName'] ?? '');
        if ($connectionName === '') {
            return $metadata;
        }

        $declarations = $this->contentDeclarations['ddeConnectionDeclarationsByName'] ?? [];
        if (!is_array($declarations)) {
            return $metadata;
        }

        $declaration = $declarations[$connectionName] ?? null;
        if (!is_array($declaration)) {
            return $metadata;
        }

        foreach (['ddeApplication', 'ddeTopic', 'ddeItem', 'automaticUpdate', 'conversionMode'] as $key) {
            if (!array_key_exists($key, $declaration)) {
                continue;
            }
            if (!array_key_exists($key, $metadata) || $metadata[$key] === null || $metadata[$key] === '') {
                $metadata[$key] = $declaration[$key];
            }
        }
        $metadata['declared'] = true;

        return $metadata;
    }

    /**
     * @param array<string, mixed> $metadata
     */
    private function fieldFallbackText(\DOMElement $field, array $metadata): string
    {
        $text = self::normalizedText($field);
        if ($text !== '') {
            return $text;
        }

        foreach (['selectedValue', 'stringValue', 'stringValueIfTrue', 'stringValueIfFalse', 'value', 'currentValue', 'dateValue', 'timeValue', 'booleanValue', 'rowNumber', 'databaseName'] as $name) {
            $value = $metadata[$name] ?? null;
            if (is_bool($value)) {
                return $value ? 'true' : 'false';
            }
            if (is_scalar($value) && (string) $value !== '') {
                return (string) $value;
            }
        }

        return '';
    }

    /**
     * @param array{styles:array<string, array<string, mixed>>, listStyles:array<string, array<string, mixed>>} $catalog
     */
    private function sequenceNode(\DOMElement $sequence, array $catalog, ?ZipPackage $package): ?AstNode
    {
        $children = $this->coalesceTextNodes($this->inlineNodes($sequence, $catalog, $package));
        if ($children === []) {
            $text = self::normalizedText($sequence);
            if ($text === '') {
                return null;
            }
            $children = [new AstNode('text', ['text' => $text])];
        }

        $attributes = [];
        foreach ([
            'name' => 'data-odf-sequence-name',
            'formula' => 'data-odf-sequence-formula',
            'ref-name' => 'data-odf-sequence-ref-name',
            'num-format' => 'data-odf-sequence-num-format',
        ] as $source => $target) {
            $value = self::attr($sequence, self::TEXT_NS, $source);
            if ($value !== '') {
                $attributes[$target] = $value;
            }
        }

        return new AstNode('span', [
            'sourceFormat' => 'odt',
            'classes' => ['odf-sequence'],
            'attributes' => $attributes,
        ], $children);
    }

    /**
     * @param array{styles:array<string, array<string, mixed>>, listStyles:array<string, array<string, mixed>>} $catalog
     */
    private function bibliographyMarkNode(\DOMElement $mark, array $catalog, ?ZipPackage $package): ?AstNode
    {
        $identifier = trim(self::attr($mark, self::TEXT_NS, 'identifier'));
        if ($identifier === '') {
            return null;
        }

        $children = $this->coalesceTextNodes($this->inlineNodes($mark, $catalog, $package));
        $displayText = $this->plainInlineText($children);
        if ($displayText === '') {
            $displayText = self::normalizedText($mark);
        }

        $sourceText = '[@' . $identifier . ']';
        if ($children === []) {
            $children = [new AstNode('text', ['text' => $displayText === '' ? $sourceText : $displayText])];
        }

        $attrs = [
            'sourceFormat' => 'odt',
            'id' => $identifier,
            'text' => $sourceText,
            'mode' => 'normal',
            'displayText' => $displayText === '' ? $sourceText : $displayText,
        ];
        $number = self::nullableInt(self::attr($mark, self::TEXT_NS, 'number'));
        if ($number !== null) {
            $attrs['citationNumber'] = $number;
        }

        return new AstNode('citation', $attrs, $children);
    }

    /**
     * @param list<\DOMNode> $children
     * @return ?array{nodes:list<\DOMNode>, endIndex:int}
     */
    private function trackedChangeRange(array $children, int $startIndex, string $changeId): ?array
    {
        $range = [];
        for ($index = $startIndex + 1, $count = count($children); $index < $count; $index++) {
            $child = $children[$index];
            if ($child instanceof \DOMElement && $this->isElement($child, self::TEXT_NS, 'change-end')) {
                $endChangeId = self::attr($child, self::TEXT_NS, 'change-id');
                if ($endChangeId === $changeId) {
                    return [
                        'nodes' => $range,
                        'endIndex' => $index,
                    ];
                }
            }

            $range[] = $child;
        }

        return null;
    }

    /**
     * @param list<AstNode> $children
     */
    private function trackedChangeSpanNode(string $changeId, array $children): ?AstNode
    {
        if ($changeId === '') {
            return null;
        }

        $change = $this->trackedChanges[$changeId] ?? [
            'id' => $changeId,
            'type' => 'change',
        ];
        if ($children === []) {
            $text = (string) ($change['text'] ?? '');
            if ($text === '') {
                return null;
            }
            $children = [new AstNode('text', ['text' => $text])];
        }

        $type = $this->trackedChangeType($change);
        $attrs = [
            'sourceFormat' => 'odt',
            'classes' => ['odf-change', 'odf-' . $type],
            'attributes' => [
                'data-odf-change-id' => $changeId,
                'data-odf-change-type' => $type,
            ],
        ];
        foreach (['creator' => 'data-odf-change-creator', 'date' => 'data-odf-change-date'] as $source => $target) {
            $value = (string) ($change[$source] ?? '');
            if ($value !== '') {
                $attrs['attributes'][$target] = $value;
            }
        }

        return new AstNode('span', $attrs, $children);
    }

    private function standaloneTrackedChangeNode(string $changeId): ?AstNode
    {
        if ($changeId === '' || !isset($this->trackedChanges[$changeId])) {
            return null;
        }

        $change = $this->trackedChanges[$changeId];
        if ($this->trackedChangeType($change) !== 'deletion') {
            return $this->trackedChangeSpanNode($changeId, []);
        }

        $text = (string) ($change['text'] ?? '');
        if ($text === '') {
            return null;
        }

        return $this->trackedChangeSpanNode($changeId, [new AstNode('text', ['text' => $text])]);
    }

    /**
     * @param array<string, mixed> $change
     */
    private function trackedChangeType(array $change): string
    {
        $type = (string) ($change['type'] ?? 'change');
        $type = strtolower(preg_replace('/[^a-zA-Z0-9-]+/', '-', $type) ?? 'change');
        $type = trim($type, '-');

        return $type === '' ? 'change' : $type;
    }

    /**
     * @param array{styles:array<string, array<string, mixed>>, listStyles:array<string, array<string, mixed>>} $catalog
     * @return list<AstNode>
     */
    private function spanNodes(\DOMElement $span, array $catalog, ?ZipPackage $package = null): array
    {
        $styleName = self::attr($span, self::TEXT_NS, 'style-name');
        $style = $this->resolveStyle($styleName, $catalog);
        $children = $this->coalesceTextNodes($this->inlineNodes($span, $catalog, $package));
        if ($children === []) {
            return [];
        }

        if ($this->isInlineCodeTextStyle($styleName)) {
            return [new AstNode('code', [
                'sourceFormat' => 'odt',
                'text' => $this->plainInlineText($children),
                'styleName' => $styleName,
                'attributes' => [
                    'data-odf-style-name' => $styleName,
                ],
            ])];
        }

        return $this->styledInlineNodes($children, $styleName, $style, true);
    }

    /**
     * @param list<AstNode> $children
     * @param array<string, mixed> $style
     * @return list<AstNode>
     */
    private function paragraphTextStyledInlineNodes(array $children, string $styleName, array $style): array
    {
        return $this->styledInlineNodes($children, $styleName, $style, false);
    }

    /**
     * @param list<AstNode> $children
     * @param array<string, mixed> $style
     * @return list<AstNode>
     */
    private function styledInlineNodes(array $children, string $styleName, array $style, bool $wrapStyleNameWithoutTextProperties): array
    {
        $properties = $style['textProperties'] ?? [];
        $hasTextProperties = is_array($properties) && $this->hasTextPropertyModifiers($properties);

        if ($styleName !== '' && ($wrapStyleNameWithoutTextProperties || $hasTextProperties)) {
            $children = [new AstNode('span', [
                'sourceFormat' => 'odt',
                'styleName' => $styleName,
                'attributes' => ['data-odf-style-name' => $styleName],
            ], $children)];
        }

        if (!is_array($properties)) {
            return $children;
        }

        return $this->applyTextPropertyModifiers($children, $properties);
    }

    /**
     * @param array<string, mixed> $properties
     */
    private function hasTextPropertyModifiers(array $properties): bool
    {
        foreach (['bold', 'italic', 'underline', 'strikeout', 'smallCaps', 'superscript', 'subscript'] as $property) {
            if (($properties[$property] ?? false) === true) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param list<AstNode> $children
     * @param array<string, mixed> $properties
     * @return list<AstNode>
     */
    private function applyTextPropertyModifiers(array $children, array $properties): array
    {
        if (($properties['bold'] ?? false) === true) {
            $children = [new AstNode('strong', [], $children)];
        }
        if (($properties['italic'] ?? false) === true) {
            $children = [new AstNode('emph', [], $children)];
        }
        if (($properties['underline'] ?? false) === true) {
            $children = [new AstNode('underline', [], $children)];
        }
        if (($properties['strikeout'] ?? false) === true) {
            $children = [new AstNode('strikeout', [], $children)];
        }
        if (($properties['smallCaps'] ?? false) === true) {
            $children = [new AstNode('small_caps', [], $children)];
        }
        if (($properties['superscript'] ?? false) === true) {
            $children = [new AstNode('superscript', [], $children)];
        }
        if (($properties['subscript'] ?? false) === true) {
            $children = [new AstNode('subscript', [], $children)];
        }

        return $children;
    }

    /**
     * @param array{styles:array<string, array<string, mixed>>, listStyles:array<string, array<string, mixed>>} $catalog
     */
    private function noteNode(\DOMElement $note, ?ZipPackage $package, array $catalog): AstNode
    {
        $noteClass = self::attr($note, self::TEXT_NS, 'note-class');
        $noteClass = $noteClass === '' ? 'footnote' : $noteClass;
        $citation = self::firstChildElement($note, 'note-citation', self::TEXT_NS);
        $body = self::firstChildElement($note, 'note-body', self::TEXT_NS);
        $blocks = [];
        if ($body instanceof \DOMElement) {
            $blocks = $package instanceof ZipPackage
                ? $this->blockNodes($body, $package, $catalog)
                : $this->noteFallbackBlocks($body, $catalog);
        }

        $attrs = [
            'sourceFormat' => 'odt',
            'noteClass' => $noteClass,
        ];
        $id = self::attr($note, self::TEXT_NS, 'id');
        if ($id !== '') {
            $attrs['id'] = $id;
        }
        if ($citation instanceof \DOMElement) {
            $attrs['citation'] = $this->noteCitationText($citation, $catalog, $package);
        }
        $configuration = $this->noteConfigurationForClass($noteClass);
        if ($configuration !== []) {
            $attrs['noteConfiguration'] = $configuration;
        }

        return new AstNode('note', $attrs, $blocks);
    }

    /**
     * @param array{styles:array<string, array<string, mixed>>, listStyles:array<string, array<string, mixed>>} $catalog
     */
    private function noteCitationText(\DOMElement $citation, array $catalog, ?ZipPackage $package): string
    {
        $text = trim($this->plainInlineText($this->coalesceTextNodes($this->inlineNodes($citation, $catalog, $package))));
        if ($text !== '') {
            return $text;
        }

        return self::normalizedText($citation);
    }

    /**
     * @return array<string, mixed>
     */
    private function noteConfigurationForClass(string $noteClass): array
    {
        $configurations = $this->contentDeclarations['noteConfigurationsByClass'] ?? [];
        if (!is_array($configurations)) {
            return [];
        }

        $configuration = $configurations[$noteClass] ?? null;

        return is_array($configuration) ? $configuration : [];
    }

    /**
     * @param array{styles:array<string, array<string, mixed>>, listStyles:array<string, array<string, mixed>>} $catalog
     * @return list<AstNode>
     */
    private function noteFallbackBlocks(\DOMElement $body, array $catalog): array
    {
        $blocks = [];
        foreach (self::childElements($body, 'p', self::TEXT_NS) as $paragraph) {
            $node = $this->paragraphNode($paragraph, $catalog);
            if ($node instanceof AstNode) {
                $blocks[] = $node;
            }
        }

        return $blocks;
    }

    private function bookmarkAnchorNode(\DOMElement $bookmark): ?AstNode
    {
        $name = self::attr($bookmark, self::TEXT_NS, 'name');
        if ($name === '') {
            return null;
        }

        return new AstNode('span', [
            'sourceFormat' => 'odt',
            'id' => self::bookmarkId($name),
            'classes' => ['anchor', 'odf-bookmark'],
            'attributes' => [
                'data-odf-bookmark-name' => $name,
            ],
        ]);
    }

    /**
     * @param list<AstNode>|null $children
     */
    private function referenceMarkAnchorNode(\DOMElement $referenceMark, ?array $children = null): ?AstNode
    {
        $name = self::attr($referenceMark, self::TEXT_NS, 'name');
        if ($name === '') {
            return null;
        }

        $isRange = $children !== null;
        $attributes = [
            'data-odf-reference-name' => $name,
        ];
        if ($isRange) {
            $attributes['data-odf-reference-range'] = 'true';
        }

        return new AstNode('span', [
            'sourceFormat' => 'odt',
            'id' => self::referenceId($name),
            'classes' => $isRange ? ['odf-reference-mark', 'odf-reference-mark-range'] : ['anchor', 'odf-reference-mark'],
            'referenceName' => $name,
            'referenceRange' => $isRange,
            'attributes' => $attributes,
        ], $children ?? []);
    }

    /**
     * @param list<\DOMNode> $children
     * @return ?array{nodes:list<\DOMNode>, endIndex:int}
     */
    private function referenceMarkRange(array $children, int $startIndex, string $name): ?array
    {
        $range = [];
        for ($index = $startIndex + 1, $count = count($children); $index < $count; $index++) {
            $child = $children[$index];
            if ($child instanceof \DOMElement && $this->isElement($child, self::TEXT_NS, 'reference-mark-end')) {
                $endName = self::attr($child, self::TEXT_NS, 'name');
                if ($endName === $name) {
                    return [
                        'nodes' => $range,
                        'endIndex' => $index,
                    ];
                }
            }

            $range[] = $child;
        }

        return null;
    }

    /**
     * @param array{styles:array<string, array<string, mixed>>, listStyles:array<string, array<string, mixed>>} $catalog
     */
    private function bookmarkReferenceNode(\DOMElement $reference, array $catalog, ?ZipPackage $package): ?AstNode
    {
        $name = self::attr($reference, self::TEXT_NS, 'ref-name');
        if ($name === '') {
            $name = self::attr($reference, self::TEXT_NS, 'name');
        }
        if ($name === '') {
            return null;
        }

        $children = $this->coalesceTextNodes($this->inlineNodes($reference, $catalog, $package));
        if ($children === []) {
            $children = [new AstNode('text', ['text' => $name])];
        }

        $attrs = [
            'sourceFormat' => 'odt',
            'url' => '#' . self::bookmarkId($name),
            'classes' => ['odf-bookmark-ref'],
            'attributes' => [
                'data-odf-ref-name' => $name,
            ],
        ];
        $format = self::attr($reference, self::TEXT_NS, 'reference-format');
        if ($format !== '') {
            $attrs['attributes']['data-odf-reference-format'] = $format;
        }

        return new AstNode('link', $attrs, $children);
    }

    /**
     * @param array{styles:array<string, array<string, mixed>>, listStyles:array<string, array<string, mixed>>} $catalog
     */
    private function referenceReferenceNode(\DOMElement $reference, array $catalog, ?ZipPackage $package): ?AstNode
    {
        $name = self::attr($reference, self::TEXT_NS, 'ref-name');
        if ($name === '') {
            $name = self::attr($reference, self::TEXT_NS, 'name');
        }
        if ($name === '') {
            return null;
        }

        $children = $this->coalesceTextNodes($this->inlineNodes($reference, $catalog, $package));
        if ($children === []) {
            $children = [new AstNode('text', ['text' => $name])];
        }

        $attrs = [
            'sourceFormat' => 'odt',
            'url' => '#' . self::referenceId($name),
            'classes' => ['odf-reference-ref'],
            'attributes' => [
                'data-odf-ref-name' => $name,
            ],
        ];
        $format = self::attr($reference, self::TEXT_NS, 'reference-format');
        if ($format !== '') {
            $attrs['attributes']['data-odf-reference-format'] = $format;
        }

        return new AstNode('link', $attrs, $children);
    }

    /**
     * @param list<\DOMNode> $children
     * @return ?array{nodes:list<\DOMNode>, endIndex:int}
     */
    private function annotationRange(array $children, int $startIndex, string $name): ?array
    {
        $range = [];
        for ($index = $startIndex + 1, $count = count($children); $index < $count; $index++) {
            $child = $children[$index];
            if ($child instanceof \DOMElement && $this->isElement($child, self::OFFICE_NS, 'annotation-end')) {
                $endName = self::attr($child, self::OFFICE_NS, 'name');
                if ($endName === $name) {
                    return [
                        'nodes' => $range,
                        'endIndex' => $index,
                    ];
                }
            }

            $range[] = $child;
        }

        return null;
    }

    /**
     * @param list<AstNode> $children
     * @param array{styles:array<string, array<string, mixed>>, listStyles:array<string, array<string, mixed>>} $catalog
     */
    private function annotationRangeSpanNode(
        \DOMElement $annotation,
        string $name,
        array $children,
        array $catalog,
        ?ZipPackage $package
    ): ?AstNode {
        $note = $this->annotationNoteNode($annotation, $package, $catalog);
        if ($this->annotationNoteHasReviewContent($note)) {
            $children[] = $note;
        }
        if ($children === []) {
            return null;
        }

        $metadata = $this->annotationMetadata($annotation);
        $attributes = [
            'data-odf-annotation-name' => $name,
        ];
        foreach ($metadata as $key => $value) {
            if ($value !== '') {
                $attributes['data-odf-annotation-' . self::kebabCase($key)] = $value;
            }
        }

        return new AstNode('span', [
            'sourceFormat' => 'odt',
            'annotationName' => $name,
            'annotationMetadata' => $metadata,
            'classes' => ['odf-annotation-range'],
            'attributes' => $attributes,
        ], $children);
    }

    private function annotationNoteHasReviewContent(AstNode $note): bool
    {
        return $note->children !== []
            || (string) $note->attr('author', '') !== ''
            || (string) $note->attr('date', '') !== '';
    }

    /**
     * @return array{author:string,date:string}
     */
    private function annotationMetadata(\DOMElement $annotation): array
    {
        $creator = self::firstChildElement($annotation, 'creator', self::DC_NS);
        $date = self::firstChildElement($annotation, 'date', self::DC_NS);

        return [
            'author' => $creator instanceof \DOMElement ? self::normalizedText($creator) : '',
            'date' => $date instanceof \DOMElement ? self::normalizedText($date) : '',
        ];
    }

    /**
     * @param array{styles:array<string, array<string, mixed>>, listStyles:array<string, array<string, mixed>>} $catalog
     */
    private function annotationNoteNode(\DOMElement $annotation, ?ZipPackage $package, array $catalog): AstNode
    {
        $metadata = $this->annotationMetadata($annotation);
        $blocks = $package instanceof ZipPackage
            ? $this->blockNodes($annotation, $package, $catalog)
            : $this->annotationInlineFallbackBlocks($annotation, $catalog);

        return new AstNode('note', [
            'sourceFormat' => 'odt',
            'author' => $metadata['author'],
            'date' => $metadata['date'],
        ], $blocks);
    }

    /**
     * @param array{styles:array<string, array<string, mixed>>, listStyles:array<string, array<string, mixed>>} $catalog
     * @return list<AstNode>
     */
    private function annotationInlineFallbackBlocks(\DOMElement $annotation, array $catalog): array
    {
        $blocks = [];
        foreach (self::childElements($annotation, 'p', self::TEXT_NS) as $paragraph) {
            $node = $this->paragraphNode($paragraph, $catalog);
            if ($node instanceof AstNode) {
                $blocks[] = $node;
            }
        }

        return $blocks;
    }

    private function frameImageNode(\DOMElement $frame, ?ZipPackage $package): ?AstNode
    {
        $image = self::firstChildElement($frame, 'image', self::DRAW_NS);
        if (!$image instanceof \DOMElement) {
            return null;
        }

        $href = self::fixRelativeLink(self::attr($image, self::XLINK_NS, 'href'));
        if ($href === '') {
            return null;
        }

        $title = self::firstChildElement($image, 'title', self::SVG_NS)
            ?? self::firstChildElement($frame, 'title', self::SVG_NS);
        $desc = self::firstChildElement($image, 'desc', self::SVG_NS)
            ?? self::firstChildElement($frame, 'desc', self::SVG_NS);
        $name = self::attr($frame, self::DRAW_NS, 'name');
        $alt = $desc instanceof \DOMElement ? self::normalizedText($desc) : ($title instanceof \DOMElement ? self::normalizedText($title) : $name);
        $part = $this->manifestPackagePart($href);
        $manifestItem = $this->manifestByPart[$part] ?? null;
        $encrypted = is_array($manifestItem) && ($manifestItem['encrypted'] ?? false) === true;
        $attrs = [
            'url' => $href,
            'alt' => $alt,
            'sourceFormat' => 'odt',
            'sourcePart' => $part,
        ];
        $dimensions = $this->frameImageDimensions($frame, $image);
        if ($dimensions !== []) {
            $attrs += $dimensions;
        }
        $linkMetadata = $this->frameImageLinkMetadata($image);
        if ($linkMetadata['metadata'] !== []) {
            $attrs['odfImageMetadata'] = $linkMetadata['metadata'];
            $attributes = $attrs['attributes'] ?? [];
            if (!is_array($attributes)) {
                $attributes = [];
            }
            $attrs['attributes'] = $attributes + $linkMetadata['attributes'];
        }
        $frameMetadata = $this->frameMetadata($frame);
        if ($frameMetadata !== []) {
            $attrs['odfFrameMetadata'] = $frameMetadata;
            $attributes = $attrs['attributes'] ?? [];
            if (!is_array($attributes)) {
                $attributes = [];
            }
            $attrs['attributes'] = $attributes + $this->frameMetadataAttributes($frameMetadata);
        }
        if (is_array($manifestItem)) {
            $attrs['mediaType'] = $manifestItem['mediaType'] ?? null;
            $attrs['encrypted'] = $encrypted;
            $attrs['canExposeBytes'] = !$encrypted;
            $attrs['declaredSize'] = $manifestItem['declaredSize'] ?? null;
            $attrs['encryption'] = $manifestItem['encryption'] ?? null;
        }
        if ($title instanceof \DOMElement) {
            $attrs['title'] = self::normalizedText($title);
        }
        if ($package instanceof ZipPackage && $package->has($part) && !$encrypted) {
            $attrs['bytes'] = strlen($package->read($part));
        }

        return new AstNode('image', $attrs, $alt === '' ? [] : [new AstNode('text', ['text' => $alt])]);
    }

    /**
     * @param array{styles:array<string, array<string, mixed>>, listStyles:array<string, array<string, mixed>>} $catalog
     * @return array<string, string|int>
     */
    private function frameCaptionMetadata(\DOMElement $frame, ZipPackage $package, array $catalog): array
    {
        $caption = self::firstChildElement($frame, 'caption', self::DRAW_NS);
        if (!$caption instanceof \DOMElement) {
            return [];
        }

        $blocks = $this->blockNodes($caption, $package, $catalog);
        $text = $this->plainBlockText($blocks);
        if ($text === '') {
            $text = trim($this->plainInlineText($this->coalesceTextNodes($this->inlineNodes($caption, $catalog, $package))));
        }
        if ($text === '') {
            $text = trim(self::normalizedText($caption));
        }
        if ($text === '') {
            return [];
        }

        $paragraphCount = count(self::childElements($caption, 'p', self::TEXT_NS));
        $frameName = self::attr($frame, self::DRAW_NS, 'name');

        return self::withoutEmpty([
            'sourceElement' => 'draw:caption',
            'text' => preg_replace('/\s+/u', ' ', $text) ?? $text,
            'frameName' => self::nullable($frameName),
            'paragraphCount' => $paragraphCount > 0 ? $paragraphCount : null,
        ]);
    }

    /**
     * @param array{styles:array<string, array<string, mixed>>, listStyles:array<string, array<string, mixed>>} $catalog
     */
    private function frameTextBoxCaptionImageNode(\DOMElement $frame, array $catalog, ?ZipPackage $package): ?AstNode
    {
        $textBox = self::firstChildElement($frame, 'text-box', self::DRAW_NS);
        if (!$textBox instanceof \DOMElement) {
            return null;
        }

        foreach (self::childElements($textBox, 'p', self::TEXT_NS) as $paragraph) {
            $nodes = $this->coalesceTextNodes($this->inlineNodes($paragraph, $catalog, $package));
            foreach ($nodes as $index => $node) {
                if (!$node instanceof AstNode || $node->type !== 'image') {
                    continue;
                }

                $captionNodes = array_slice($nodes, $index + 1);
                $caption = trim($this->plainInlineText($captionNodes));

                return $this->captionedTextBoxImageNode(
                    $node,
                    $frame,
                    $caption,
                    $caption === '' ? $node->children : [new AstNode('text', ['text' => $caption])]
                );
            }
        }

        return null;
    }

    /**
     * @param list<AstNode> $children
     */
    private function captionedTextBoxImageNode(AstNode $image, \DOMElement $frame, string $caption, array $children): AstNode
    {
        $attrs = $image->attrs;
        if ($caption !== '') {
            $attrs['alt'] = $caption;
        }

        $title = (string) $image->attr('title', '');
        if ($title !== '' && !str_starts_with($title, 'fig:')) {
            $attrs['title'] = 'fig:' . $title;
        } elseif ($title !== '') {
            $attrs['title'] = $title;
        }

        $classes = $attrs['classes'] ?? [];
        if (!is_array($classes)) {
            $classes = [];
        }
        $classes[] = 'odf-text-box-image-caption';
        $attrs['classes'] = array_values(array_unique(array_filter(
            array_map(static fn (mixed $class): string => (string) $class, $classes),
            static fn (string $class): bool => $class !== ''
        )));

        $attributes = $attrs['attributes'] ?? [];
        if (!is_array($attributes)) {
            $attributes = [];
        }
        $attributes['data-odf-text-box-caption'] = 'true';
        $frameName = self::attr($frame, self::DRAW_NS, 'name');
        if ($frameName !== '') {
            $attributes['data-odf-text-box-frame-name'] = $frameName;
        }
        $attrs['attributes'] = $attributes;

        return new AstNode('image', $attrs, $children);
    }

    /**
     * @return array{width?:string,height?:string,attributes?:array<string, string>}
     */
    private function frameImageDimensions(\DOMElement $frame, \DOMElement $image): array
    {
        $attributes = [];
        foreach (['width', 'height'] as $name) {
            $value = self::attr($image, self::SVG_NS, $name);
            if ($value === '') {
                $value = self::attr($frame, self::SVG_NS, $name);
            }
            if ($value === '') {
                continue;
            }

            $attributes[$name] = $value;
        }

        if ($attributes === []) {
            return [];
        }

        return $attributes + ['attributes' => $attributes];
    }

    /**
     * @return array{metadata:array<string, string>,attributes:array<string, string>}
     */
    private function frameImageLinkMetadata(\DOMElement $image): array
    {
        $metadata = self::withoutEmpty([
            'xlinkType' => self::nullable(self::attr($image, self::XLINK_NS, 'type')),
            'xlinkShow' => self::nullable(self::attr($image, self::XLINK_NS, 'show')),
            'xlinkActuate' => self::nullable(self::attr($image, self::XLINK_NS, 'actuate')),
        ]);
        $attributes = [];
        foreach ($metadata as $name => $value) {
            $attributes['data-odf-image-' . self::kebabCase($name)] = (string) $value;
        }

        return [
            'metadata' => array_map(static fn (mixed $value): string => (string) $value, $metadata),
            'attributes' => $attributes,
        ];
    }

    /**
     * @return array<string, string>
     */
    private function frameMetadata(\DOMElement $frame): array
    {
        $metadata = self::withoutEmpty([
            'name' => self::nullable(self::attr($frame, self::DRAW_NS, 'name')),
            'styleName' => self::nullable(self::attr($frame, self::DRAW_NS, 'style-name')),
            'anchorType' => self::nullable(self::attr($frame, self::TEXT_NS, 'anchor-type')),
            'anchorPageNumber' => self::nullable(self::attr($frame, self::TEXT_NS, 'anchor-page-number')),
            'x' => self::nullable(self::attr($frame, self::SVG_NS, 'x')),
            'y' => self::nullable(self::attr($frame, self::SVG_NS, 'y')),
            'zIndex' => self::nullable(self::attr($frame, self::DRAW_NS, 'z-index')),
        ] + $this->frameLayerMetadata($frame));
        if ($metadata === [] || array_keys($metadata) === ['name']) {
            return [];
        }

        return array_map(static fn (mixed $value): string => (string) $value, $metadata);
    }

    /**
     * @param array<string, string> $metadata
     * @return array<string, string>
     */
    private function frameMetadataAttributes(array $metadata): array
    {
        $attributes = [];
        foreach ($metadata as $name => $value) {
            $attributes['data-odf-frame-' . self::kebabCase($name)] = $value;
        }

        return $attributes;
    }

    /**
     * @return array<string, string>
     */
    private function textBoxFrameMetadata(\DOMElement $frame): array
    {
        $metadata = self::withoutEmpty([
            'name' => self::nullable(self::attr($frame, self::DRAW_NS, 'name')),
            'styleName' => self::nullable(self::attr($frame, self::DRAW_NS, 'style-name')),
            'anchorType' => self::nullable(self::attr($frame, self::TEXT_NS, 'anchor-type')),
            'anchorPageNumber' => self::nullable(self::attr($frame, self::TEXT_NS, 'anchor-page-number')),
            'x' => self::nullable(self::attr($frame, self::SVG_NS, 'x')),
            'y' => self::nullable(self::attr($frame, self::SVG_NS, 'y')),
            'width' => self::nullable(self::attr($frame, self::SVG_NS, 'width')),
            'height' => self::nullable(self::attr($frame, self::SVG_NS, 'height')),
            'zIndex' => self::nullable(self::attr($frame, self::DRAW_NS, 'z-index')),
        ] + $this->frameLayerMetadata($frame));
        if ($metadata === [] || array_keys($metadata) === ['name']) {
            return [];
        }

        return array_map(static fn (mixed $value): string => (string) $value, $metadata);
    }

    /**
     * @return array<string, string>
     */
    private function frameLayerMetadata(\DOMElement $frame): array
    {
        $layerName = self::attr($frame, self::DRAW_NS, 'layer');
        if ($layerName === '') {
            return [];
        }

        $metadata = [
            'layer' => $layerName,
            'layerExists' => 'false',
        ];
        $layersByName = $this->contentDeclarations['drawLayersByName'] ?? [];
        $layer = is_array($layersByName) && is_array($layersByName[$layerName] ?? null)
            ? $layersByName[$layerName]
            : null;
        if ($layer === null) {
            return $metadata;
        }

        $metadata['layerExists'] = 'true';
        foreach ([
            'display' => 'layerDisplay',
            'hidden' => 'layerHidden',
            'protected' => 'layerProtected',
        ] as $source => $target) {
            if (!array_key_exists($source, $layer)) {
                continue;
            }

            $value = $layer[$source];
            $metadata[$target] = is_bool($value) ? ($value ? 'true' : 'false') : (string) $value;
        }

        return $metadata;
    }

    private function frameObjectMathNode(\DOMElement $frame, ?ZipPackage $package): ?AstNode
    {
        if (!$package instanceof ZipPackage) {
            return null;
        }

        $object = self::firstChildElement($frame, 'object', self::DRAW_NS);
        if (!$object instanceof \DOMElement) {
            return null;
        }

        $href = self::attr($object, self::XLINK_NS, 'href');
        if ($href === '') {
            return null;
        }

        [$objectPath, $contentPart] = $this->objectContentPart($href);
        if (!$package->has($contentPart)) {
            return null;
        }

        $dom = self::loadXml($package->read($contentPart), 'ODT MathML object ' . $contentPart);
        $math = $this->firstMathElement($dom);
        if (!$math instanceof \DOMElement) {
            return null;
        }

        $mathml = $this->mathElementXml($math);
        if ($mathml === '') {
            return null;
        }

        return new AstNode('math', [
            'sourceFormat' => 'odt-mathml',
            'display' => true,
            'text' => $this->mathPlainText($math),
            'mathml' => $mathml,
            'objectPath' => $objectPath,
            'sourcePart' => $contentPart,
        ]);
    }

    private function frameObjectOleNode(\DOMElement $frame, ?ZipPackage $package, bool $inline): ?AstNode
    {
        $object = self::firstChildElement($frame, 'object-ole', self::DRAW_NS);
        if (!$object instanceof \DOMElement) {
            return null;
        }

        $href = self::attr($object, self::XLINK_NS, 'href');
        if ($href === '') {
            return null;
        }

        $part = $this->manifestPackagePart($href);
        $objectPath = rtrim($part, '/');
        if ($objectPath === '') {
            return null;
        }

        $manifestItem = $this->objectManifestItem($part, $objectPath);
        $sourcePart = is_array($manifestItem) && is_string($manifestItem['part'] ?? null)
            ? (string) $manifestItem['part']
            : $part;
        $containedParts = $package instanceof ZipPackage ? $this->objectContainedParts($package, $objectPath) : [];
        $encrypted = is_array($manifestItem) && ($manifestItem['encrypted'] ?? false) === true;
        $mediaType = is_array($manifestItem) ? (string) ($manifestItem['mediaType'] ?? '') : '';
        $containedByteLength = $encrypted ? null : $this->containedPartsByteLength($package, $containedParts);
        $exists = $containedParts !== [] || ($package instanceof ZipPackage && $package->has($sourcePart) && !str_ends_with($sourcePart, '/'));
        $label = $this->objectLabel($frame, $object, $objectPath);

        $attributes = [
            'data-odf-object-type' => 'ole',
            'data-odf-object-href' => $href,
            'data-odf-object-path' => $objectPath,
            'data-odf-object-source-part' => $sourcePart,
        ];
        if ($mediaType !== '') {
            $attributes['data-odf-object-media-type'] = $mediaType;
        }
        $attributes['data-odf-object-exists'] = $exists ? 'true' : 'false';
        $attributes['data-odf-object-contained-part-count'] = (string) count($containedParts);
        if ($containedByteLength !== null && $containedByteLength > 0) {
            $attributes['data-odf-object-contained-byte-length'] = (string) $containedByteLength;
        }
        $attributes['data-odf-object-can-expose-bytes'] = 'false';
        if ($encrypted) {
            $attributes['data-odf-object-encrypted'] = 'true';
        }

        $attrs = [
            'sourceFormat' => 'odt-object-ole',
            'objectType' => 'ole',
            'href' => $href,
            'objectPath' => $objectPath,
            'sourcePart' => $sourcePart,
            'mediaType' => $mediaType === '' ? null : $mediaType,
            'exists' => $exists,
            'encrypted' => $encrypted,
            'canExposeBytes' => false,
            'containedParts' => $containedParts,
            'containedPartCount' => count($containedParts),
            'containedByteLength' => $containedByteLength,
            'classes' => ['odf-embedded-object', 'odf-object-ole'],
            'attributes' => $attributes,
        ];
        if (is_array($manifestItem)) {
            $attrs['manifestFullPath'] = $manifestItem['fullPath'] ?? null;
            $attrs['declaredSize'] = $manifestItem['declaredSize'] ?? null;
            $attrs['manifestExists'] = $manifestItem['exists'] ?? null;
            $attrs['encryption'] = $manifestItem['encryption'] ?? null;
        }

        $text = new AstNode('text', ['text' => $label]);
        if ($inline) {
            return new AstNode('span', $attrs, [$text]);
        }

        return new AstNode('div', $attrs, [
            new AstNode('paragraph', [
                'sourceFormat' => 'odt',
                'text' => $label,
            ], [$text]),
        ]);
    }

    private function frameObjectNode(\DOMElement $frame, ?ZipPackage $package, bool $inline): ?AstNode
    {
        $object = self::firstChildElement($frame, 'object', self::DRAW_NS);
        if (!$object instanceof \DOMElement) {
            return null;
        }

        $href = self::attr($object, self::XLINK_NS, 'href');
        if ($href === '') {
            return null;
        }

        $part = $this->manifestPackagePart($href);
        $objectPath = rtrim($part, '/');
        if ($objectPath === '') {
            return null;
        }

        $manifestItem = $this->objectManifestItem($part, $objectPath);
        $sourcePart = is_array($manifestItem) && is_string($manifestItem['part'] ?? null)
            ? (string) $manifestItem['part']
            : $part;
        $containedParts = $package instanceof ZipPackage ? $this->objectContainedParts($package, $objectPath) : [];
        $encrypted = is_array($manifestItem) && ($manifestItem['encrypted'] ?? false) === true;
        $mediaType = is_array($manifestItem) ? (string) ($manifestItem['mediaType'] ?? '') : '';
        $objectType = $this->objectTypeForMediaType($mediaType);
        $containedByteLength = $encrypted ? null : $this->containedPartsByteLength($package, $containedParts);
        $exists = $containedParts !== [] || ($package instanceof ZipPackage && $package->has($sourcePart) && !str_ends_with($sourcePart, '/'));
        $label = $this->objectLabel($frame, $object, $objectPath);

        $attributes = [
            'data-odf-object-type' => $objectType,
            'data-odf-object-href' => $href,
            'data-odf-object-path' => $objectPath,
            'data-odf-object-source-part' => $sourcePart,
        ];
        if ($mediaType !== '') {
            $attributes['data-odf-object-media-type'] = $mediaType;
        }
        $attributes['data-odf-object-exists'] = $exists ? 'true' : 'false';
        $attributes['data-odf-object-contained-part-count'] = (string) count($containedParts);
        if ($containedByteLength !== null && $containedByteLength > 0) {
            $attributes['data-odf-object-contained-byte-length'] = (string) $containedByteLength;
        }
        $attributes['data-odf-object-can-expose-bytes'] = 'false';
        if ($encrypted) {
            $attributes['data-odf-object-encrypted'] = 'true';
        }
        $chartMetadata = $this->chartObjectMetadata($package, $objectPath, $objectType, $encrypted);
        foreach ($this->chartObjectHtmlAttributes($chartMetadata) as $name => $value) {
            $attributes[$name] = $value;
        }

        $attrs = [
            'sourceFormat' => $objectType === 'object' ? 'odt-object' : 'odt-object-' . $objectType,
            'objectType' => $objectType,
            'href' => $href,
            'objectPath' => $objectPath,
            'sourcePart' => $sourcePart,
            'mediaType' => $mediaType === '' ? null : $mediaType,
            'exists' => $exists,
            'encrypted' => $encrypted,
            'canExposeBytes' => false,
            'containedParts' => $containedParts,
            'containedPartCount' => count($containedParts),
            'containedByteLength' => $containedByteLength,
            'classes' => ['odf-embedded-object', 'odf-object-' . self::objectClassSuffix($objectType)],
            'attributes' => $attributes,
        ];
        if (is_array($manifestItem)) {
            $attrs['manifestFullPath'] = $manifestItem['fullPath'] ?? null;
            $attrs['declaredSize'] = $manifestItem['declaredSize'] ?? null;
            $attrs['manifestExists'] = $manifestItem['exists'] ?? null;
            $attrs['encryption'] = $manifestItem['encryption'] ?? null;
        }
        if ($chartMetadata !== []) {
            $attrs['chartMetadata'] = $chartMetadata;
        }

        $text = new AstNode('text', ['text' => $label]);
        if ($inline) {
            return new AstNode('span', $attrs, [$text]);
        }

        return new AstNode('div', $attrs, [
            new AstNode('paragraph', [
                'sourceFormat' => 'odt',
                'text' => $label,
            ], [$text]),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function chartObjectMetadata(?ZipPackage $package, string $objectPath, string $objectType, bool $encrypted): array
    {
        if ($objectType !== 'chart' || $encrypted || !$package instanceof ZipPackage) {
            return [];
        }

        $contentPart = rtrim($objectPath, '/') . '/content.xml';
        if (!$package->has($contentPart)) {
            return [];
        }

        try {
            $dom = self::loadXml($package->read($contentPart), 'ODT chart object ' . $contentPart);
        } catch (\InvalidArgumentException) {
            return [
                'sourcePart' => $contentPart,
                'parseError' => true,
            ];
        }

        $chart = $dom->getElementsByTagNameNS(self::CHART_NS, 'chart')->item(0);
        if (!$chart instanceof \DOMElement) {
            return [
                'sourcePart' => $contentPart,
                'chartMissing' => true,
            ];
        }

        $plotArea = self::firstChildElement($chart, 'plot-area', self::CHART_NS);
        $chartClass = self::attr($chart, self::CHART_NS, 'class');
        $title = $this->chartTitleMetadata($chart);
        $axes = $this->chartAxesMetadata($chart);
        $legend = $this->chartLegendMetadata($chart);
        $categories = $this->chartCategoriesMetadata($chart);
        $series = $this->chartSeriesMetadata($chart);

        $metadata = self::withoutEmpty([
            'sourcePart' => $contentPart,
            'chartClass' => self::nullable($chartClass),
            'chartClassName' => self::nullable(self::chartClassName($chartClass)),
            'styleName' => self::nullable(self::attr($chart, self::CHART_NS, 'style-name')),
            'title' => $title,
            'cellRangeAddress' => $plotArea instanceof \DOMElement ? self::nullable(self::attr($plotArea, self::TABLE_NS, 'cell-range-address')) : null,
            'dataSourceHasLabels' => $plotArea instanceof \DOMElement ? self::nullable(self::attr($plotArea, self::CHART_NS, 'data-source-has-labels')) : null,
            'axisCount' => $axes === [] ? null : count($axes),
            'axes' => $axes,
            'legend' => $legend,
            'categories' => $categories,
            'seriesCount' => $series === [] ? null : count($series),
            'series' => $series,
        ]);

        return $metadata;
    }

    /**
     * @return array<string, string>
     */
    private function chartTitleMetadata(\DOMElement $container): array
    {
        $title = self::firstChildElement($container, 'title', self::CHART_NS);
        if (!$title instanceof \DOMElement) {
            return [];
        }

        $entry = self::withoutEmpty([
            'text' => self::nullable(self::normalizedText($title)),
            'styleName' => self::nullable(self::attr($title, self::CHART_NS, 'style-name')),
            'x' => self::nullable(self::attr($title, self::SVG_NS, 'x')),
            'y' => self::nullable(self::attr($title, self::SVG_NS, 'y')),
            'width' => self::nullable(self::attr($title, self::SVG_NS, 'width')),
            'height' => self::nullable(self::attr($title, self::SVG_NS, 'height')),
        ]);

        return array_map(static fn (mixed $value): string => (string) $value, $entry);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function chartAxesMetadata(\DOMElement $chart): array
    {
        $axes = [];
        foreach ($chart->getElementsByTagNameNS(self::CHART_NS, 'axis') as $axis) {
            if (!$axis instanceof \DOMElement) {
                continue;
            }

            $title = $this->chartTitleMetadata($axis);
            $categories = $this->chartCategoriesMetadata($axis);
            $entry = self::withoutEmpty([
                'dimension' => self::nullable(self::attr($axis, self::CHART_NS, 'dimension')),
                'name' => self::nullable(self::attr($axis, self::CHART_NS, 'name')),
                'styleName' => self::nullable(self::attr($axis, self::CHART_NS, 'style-name')),
                'title' => $title,
                'categories' => $categories,
            ]);
            if ($entry !== []) {
                $axes[] = $entry;
            }
        }

        return $axes;
    }

    /**
     * @return array<string, string>
     */
    private function chartLegendMetadata(\DOMElement $chart): array
    {
        $legend = self::firstChildElement($chart, 'legend', self::CHART_NS);
        if (!$legend instanceof \DOMElement) {
            return [];
        }

        $entry = self::withoutEmpty([
            'position' => self::nullable(self::attr($legend, self::CHART_NS, 'legend-position')),
            'align' => self::nullable(self::attr($legend, self::CHART_NS, 'legend-align')),
            'styleName' => self::nullable(self::attr($legend, self::CHART_NS, 'style-name')),
            'x' => self::nullable(self::attr($legend, self::SVG_NS, 'x')),
            'y' => self::nullable(self::attr($legend, self::SVG_NS, 'y')),
            'width' => self::nullable(self::attr($legend, self::SVG_NS, 'width')),
            'height' => self::nullable(self::attr($legend, self::SVG_NS, 'height')),
        ]);

        return array_map(static fn (mixed $value): string => (string) $value, $entry);
    }

    /**
     * @return list<array<string, string>>
     */
    private function chartCategoriesMetadata(\DOMElement $chart): array
    {
        $categories = [];
        foreach ($chart->getElementsByTagNameNS(self::CHART_NS, 'categories') as $category) {
            if (!$category instanceof \DOMElement) {
                continue;
            }

            $entry = self::withoutEmpty([
                'cellRangeAddress' => self::nullable(self::attr($category, self::TABLE_NS, 'cell-range-address')),
            ]);
            if ($entry !== []) {
                $categories[] = array_map(static fn (mixed $value): string => (string) $value, $entry);
            }
        }

        return $categories;
    }

    /**
     * @return list<array<string, string>>
     */
    private function chartSeriesMetadata(\DOMElement $chart): array
    {
        $series = [];
        foreach ($chart->getElementsByTagNameNS(self::CHART_NS, 'series') as $seriesElement) {
            if (!$seriesElement instanceof \DOMElement) {
                continue;
            }

            $entry = self::withoutEmpty([
                'valuesCellRangeAddress' => self::nullable(self::attr($seriesElement, self::CHART_NS, 'values-cell-range-address')),
                'labelCellAddress' => self::nullable(self::attr($seriesElement, self::CHART_NS, 'label-cell-address')),
                'attachedAxis' => self::nullable(self::attr($seriesElement, self::CHART_NS, 'attached-axis')),
                'chartClass' => self::nullable(self::attr($seriesElement, self::CHART_NS, 'class')),
                'styleName' => self::nullable(self::attr($seriesElement, self::CHART_NS, 'style-name')),
            ]);
            if ($entry !== []) {
                $series[] = array_map(static fn (mixed $value): string => (string) $value, $entry);
            }
        }

        return $series;
    }

    /**
     * @param array<string, mixed> $metadata
     * @return array<string, string>
     */
    private function chartObjectHtmlAttributes(array $metadata): array
    {
        if ($metadata === []) {
            return [];
        }

        $attributes = [];
        foreach ([
            'sourcePart' => 'data-odf-chart-source-part',
            'chartClassName' => 'data-odf-chart-class',
            'cellRangeAddress' => 'data-odf-chart-cell-range',
            'dataSourceHasLabels' => 'data-odf-chart-data-source-has-labels',
            'seriesCount' => 'data-odf-chart-series-count',
            'axisCount' => 'data-odf-chart-axis-count',
        ] as $name => $attributeName) {
            $value = $metadata[$name] ?? null;
            if (is_scalar($value) && (string) $value !== '') {
                $attributes[$attributeName] = (string) $value;
            }
        }

        $title = $metadata['title'] ?? [];
        if (is_array($title)) {
            $text = $title['text'] ?? null;
            if (is_scalar($text) && (string) $text !== '') {
                $attributes['data-odf-chart-title'] = (string) $text;
            }
        }
        $legend = $metadata['legend'] ?? [];
        if (is_array($legend)) {
            foreach ([
                'position' => 'data-odf-chart-legend-position',
                'align' => 'data-odf-chart-legend-align',
            ] as $name => $attributeName) {
                $value = $legend[$name] ?? null;
                if (is_scalar($value) && (string) $value !== '') {
                    $attributes[$attributeName] = (string) $value;
                }
            }
        }
        $categories = $metadata['categories'] ?? [];
        if (is_array($categories) && is_array($categories[0] ?? null)) {
            $range = $categories[0]['cellRangeAddress'] ?? null;
            if (is_scalar($range) && (string) $range !== '') {
                $attributes['data-odf-chart-categories-range'] = (string) $range;
            }
        }
        if (($metadata['parseError'] ?? false) === true) {
            $attributes['data-odf-chart-parse-error'] = 'true';
        }
        if (($metadata['chartMissing'] ?? false) === true) {
            $attributes['data-odf-chart-missing'] = 'true';
        }

        return $attributes;
    }

    private static function chartClassName(string $chartClass): string
    {
        $chartClass = trim($chartClass);
        if ($chartClass === '') {
            return '';
        }

        $parts = explode(':', $chartClass);
        $name = (string) end($parts);
        if ($name === '') {
            $name = $chartClass;
        }
        $name = preg_replace('/[^A-Za-z0-9._-]+/', '-', $name) ?? '';
        $name = trim($name, '-');

        return $name;
    }

    private function objectTypeForMediaType(string $mediaType): string
    {
        $base = strtolower(trim(explode(';', $mediaType, 2)[0]));

        return match ($base) {
            'application/vnd.oasis.opendocument.chart' => 'chart',
            'application/vnd.oasis.opendocument.graphics' => 'graphics',
            'application/vnd.oasis.opendocument.presentation' => 'presentation',
            'application/vnd.oasis.opendocument.spreadsheet' => 'spreadsheet',
            'application/vnd.oasis.opendocument.text' => 'text',
            default => 'object',
        };
    }

    private static function objectClassSuffix(string $type): string
    {
        $suffix = strtolower(preg_replace('/[^A-Za-z0-9]+/', '-', $type) ?? '');
        $suffix = trim($suffix, '-');

        return $suffix === '' ? 'object' : $suffix;
    }

    /**
     * @return ?array<string, mixed>
     */
    private function objectManifestItem(string $part, string $objectPath): ?array
    {
        $candidates = [$part];
        if (!str_ends_with($part, '/')) {
            $candidates[] = $part . '/';
        }
        $candidates[] = $objectPath;
        $candidates[] = $objectPath . '/';

        foreach (array_unique($candidates) as $candidate) {
            if (isset($this->manifestByPart[$candidate])) {
                return $this->manifestByPart[$candidate];
            }
        }

        return null;
    }

    /**
     * @return list<string>
     */
    private function objectContainedParts(ZipPackage $package, string $objectPath): array
    {
        $prefix = rtrim($objectPath, '/') . '/';
        $parts = [];
        foreach ($package->names() as $name) {
            if ($name === $objectPath || str_starts_with($name, $prefix)) {
                if (!str_ends_with($name, '/')) {
                    $parts[] = $name;
                }
            }
        }

        sort($parts);

        return $parts;
    }

    /**
     * @param list<string> $parts
     */
    private function containedPartsByteLength(?ZipPackage $package, array $parts): ?int
    {
        if (!$package instanceof ZipPackage || $parts === []) {
            return null;
        }

        $bytes = 0;
        foreach ($parts as $part) {
            if (!$package->has($part)) {
                continue;
            }
            $bytes += $package->entry($part)->uncompressedSize;
        }

        return $bytes;
    }

    private function objectLabel(\DOMElement $frame, \DOMElement $object, string $objectPath): string
    {
        $desc = self::firstChildElement($frame, 'desc', self::SVG_NS)
            ?? self::firstChildElement($object, 'desc', self::SVG_NS);
        if ($desc instanceof \DOMElement && self::normalizedText($desc) !== '') {
            return self::normalizedText($desc);
        }

        $title = self::firstChildElement($frame, 'title', self::SVG_NS)
            ?? self::firstChildElement($object, 'title', self::SVG_NS);
        if ($title instanceof \DOMElement && self::normalizedText($title) !== '') {
            return self::normalizedText($title);
        }

        $name = self::attr($frame, self::DRAW_NS, 'name');
        if ($name !== '') {
            return $name;
        }

        $basename = basename($objectPath);

        return $basename === '' ? 'Embedded ODF object' : $basename;
    }

    /**
     * @return array{0:string,1:string}
     */
    private function objectContentPart(string $href): array
    {
        $part = $this->manifestPackagePart($href);
        if (str_ends_with($part, '/content.xml')) {
            return [substr($part, 0, -strlen('/content.xml')), $part];
        }

        $objectPath = rtrim($part, '/');

        return [$objectPath, $objectPath . '/content.xml'];
    }

    private function firstMathElement(\DOMDocument $dom): ?\DOMElement
    {
        $math = $dom->getElementsByTagNameNS(self::MATH_NS, 'math')->item(0);

        return $math instanceof \DOMElement ? $math : null;
    }

    private function mathElementXml(\DOMElement $math): string
    {
        $xml = $math->ownerDocument instanceof \DOMDocument ? $math->ownerDocument->saveXML($math) : '';

        return trim(is_string($xml) ? $xml : '');
    }

    private function mathPlainText(\DOMElement $math): string
    {
        $parts = [];
        $this->collectMathText($math, $parts);
        $text = implode('', $parts);

        return trim(preg_replace('/\s+/u', ' ', $text) ?? $text);
    }

    /**
     * @param list<string> $parts
     */
    private function collectMathText(\DOMNode $node, array &$parts): void
    {
        if ($node instanceof \DOMElement && $node->namespaceURI === self::MATH_NS && in_array($node->localName, ['annotation', 'annotation-xml'], true)) {
            return;
        }

        if ($node instanceof \DOMText || $node instanceof \DOMCdataSection) {
            $parts[] = $node->textContent;

            return;
        }

        foreach ($node->childNodes as $child) {
            $this->collectMathText($child, $parts);
        }
    }

    /**
     * @param array<string, array<string, mixed>> $inheritedFontFaces
     * @return array{styles:array<string, array<string, mixed>>, fontFaces:array<string, array<string, mixed>>, listStyles:array<string, array<string, mixed>>, tableTemplates:array<string, array<string, mixed>>, pageLayouts:array<string, array<string, mixed>>, masterPages:array<string, array<string, mixed>>}
     */
    private function styleCollectionsFromRoot(\DOMElement $root, array $inheritedFontFaces = []): array
    {
        $fontFaces = array_replace($inheritedFontFaces, $this->fontFaceDeclarations($root));

        $styles = [];
        foreach ($root->getElementsByTagNameNS(self::STYLE_NS, 'style') as $style) {
            if (!$style instanceof \DOMElement) {
                continue;
            }
            $name = self::attr($style, self::STYLE_NS, 'name');
            if ($name === '') {
                continue;
            }
            $styles[$name] = $this->styleDefinition($style, $fontFaces);
        }

        $listStyles = [];
        foreach ($root->getElementsByTagNameNS(self::TEXT_NS, 'list-style') as $listStyle) {
            if (!$listStyle instanceof \DOMElement) {
                continue;
            }
            $name = self::attr($listStyle, self::STYLE_NS, 'name');
            if ($name === '') {
                continue;
            }
            $listStyles[$name] = $this->listStyleDefinition($listStyle, $fontFaces);
        }

        $tableTemplates = [];
        foreach ($root->getElementsByTagNameNS(self::TABLE_NS, 'table-template') as $tableTemplate) {
            if (!$tableTemplate instanceof \DOMElement) {
                continue;
            }
            $name = self::attr($tableTemplate, self::TABLE_NS, 'name');
            if ($name === '') {
                continue;
            }
            $tableTemplates[$name] = $this->tableTemplateDefinition($tableTemplate);
        }

        $pageLayouts = [];
        foreach ($root->getElementsByTagNameNS(self::STYLE_NS, 'page-layout') as $pageLayout) {
            if (!$pageLayout instanceof \DOMElement) {
                continue;
            }
            $name = self::attr($pageLayout, self::STYLE_NS, 'name');
            if ($name === '') {
                continue;
            }
            $pageLayouts[$name] = $this->pageLayoutDefinition($pageLayout);
        }

        $masterPages = [];
        foreach ($root->getElementsByTagNameNS(self::STYLE_NS, 'master-page') as $masterPage) {
            if (!$masterPage instanceof \DOMElement) {
                continue;
            }
            $name = self::attr($masterPage, self::STYLE_NS, 'name');
            if ($name === '') {
                continue;
            }
            $masterPages[$name] = $this->masterPageDefinition($masterPage);
        }

        return [
            'styles' => $styles,
            'fontFaces' => $fontFaces,
            'listStyles' => $listStyles,
            'tableTemplates' => $tableTemplates,
            'pageLayouts' => $pageLayouts,
            'masterPages' => $masterPages,
        ];
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function fontFaceDeclarations(\DOMElement $root): array
    {
        $container = self::firstChildElement($root, 'font-face-decls', self::OFFICE_NS);
        if (!$container instanceof \DOMElement) {
            return [];
        }

        $fontFaces = [];
        foreach (self::childElements($container, 'font-face', self::STYLE_NS) as $fontFace) {
            $name = self::attr($fontFace, self::STYLE_NS, 'name');
            if ($name === '') {
                continue;
            }

            $fontFaces[$name] = self::withoutEmpty([
                'name' => $name,
                'fontFamily' => self::nullable(self::attr($fontFace, self::SVG_NS, 'font-family')),
                'fontFamilyGeneric' => self::nullable(self::attr($fontFace, self::STYLE_NS, 'font-family-generic')),
                'fontPitch' => self::nullable(strtolower(self::attr($fontFace, self::STYLE_NS, 'font-pitch'))),
                'fontCharset' => self::nullable(self::attr($fontFace, self::STYLE_NS, 'font-charset')),
            ]);
        }

        return $fontFaces;
    }

    /**
     * @param array<string, array<string, mixed>> $fontFaces
     * @return array<string, mixed>
     */
    private function styleDefinition(\DOMElement $style, array $fontFaces = []): array
    {
        $definition = [
            'name' => self::attr($style, self::STYLE_NS, 'name'),
            'family' => self::attr($style, self::STYLE_NS, 'family'),
            'displayName' => self::nullable(self::attr($style, self::STYLE_NS, 'display-name')),
            'parentName' => self::nullable(self::attr($style, self::STYLE_NS, 'parent-style-name')),
            'listStyleName' => self::nullable(self::attr($style, self::STYLE_NS, 'list-style-name')),
            'masterPageName' => self::nullable(self::attr($style, self::STYLE_NS, 'master-page-name')),
            'headingLevel' => self::nullableInt(self::attr($style, self::STYLE_NS, 'default-outline-level')),
            'textProperties' => [],
            'paragraphProperties' => [],
            'tableColumnProperties' => [],
            'tableCellProperties' => [],
        ];

        $textProperties = self::firstChildElement($style, 'text-properties', self::STYLE_NS);
        if ($textProperties instanceof \DOMElement) {
            $definition['textProperties'] = $this->textProperties($textProperties, $fontFaces);
        }

        $paragraphProperties = self::firstChildElement($style, 'paragraph-properties', self::STYLE_NS);
        if ($paragraphProperties instanceof \DOMElement) {
            $definition['paragraphProperties'] = $this->paragraphProperties($paragraphProperties);
        }

        $columnProperties = self::firstChildElement($style, 'table-column-properties', self::STYLE_NS);
        if ($columnProperties instanceof \DOMElement) {
            $definition['tableColumnProperties'] = self::withoutEmpty([
                'columnWidth' => self::nullable(self::attr($columnProperties, self::STYLE_NS, 'column-width')),
                'relativeColumnWidth' => self::nullable(self::attr($columnProperties, self::STYLE_NS, 'rel-column-width')),
                'useOptimalColumnWidth' => self::nullableBool(self::attr($columnProperties, self::STYLE_NS, 'use-optimal-column-width')),
            ]);
        }

        $cellProperties = self::firstChildElement($style, 'table-cell-properties', self::STYLE_NS);
        if ($cellProperties instanceof \DOMElement) {
            $definition['tableCellProperties'] = $this->tableCellProperties($cellProperties);
        }
        $styleMaps = $this->styleMapDefinitions($style);
        if ($styleMaps !== []) {
            $definition['styleMaps'] = $styleMaps;
        }

        return $definition;
    }

    /**
     * @return list<array<string, string>>
     */
    private function styleMapDefinitions(\DOMElement $style): array
    {
        $maps = [];
        foreach (self::childElements($style, 'map', self::STYLE_NS) as $map) {
            $definition = self::withoutEmpty([
                'condition' => self::nullable(self::attr($map, self::STYLE_NS, 'condition')),
                'applyStyleName' => self::nullable(self::attr($map, self::STYLE_NS, 'apply-style-name')),
                'baseCellAddress' => self::nullable(self::attr($map, self::STYLE_NS, 'base-cell-address')),
            ]);
            if ($definition !== []) {
                $maps[] = array_map(static fn (mixed $value): string => (string) $value, $definition);
            }
        }

        return $maps;
    }

    /**
     * @return array<string, mixed>
     */
    private function tableCellProperties(\DOMElement $properties): array
    {
        return self::withoutEmpty([
            'backgroundColor' => self::nullable(self::attr($properties, self::FO_NS, 'background-color')),
            'border' => self::nullable(self::attr($properties, self::FO_NS, 'border')),
            'padding' => self::nullable(self::attr($properties, self::FO_NS, 'padding')),
            'paddingTop' => self::nullable(self::attr($properties, self::FO_NS, 'padding-top')),
            'paddingRight' => self::nullable(self::attr($properties, self::FO_NS, 'padding-right')),
            'paddingBottom' => self::nullable(self::attr($properties, self::FO_NS, 'padding-bottom')),
            'paddingLeft' => self::nullable(self::attr($properties, self::FO_NS, 'padding-left')),
            'verticalAlign' => self::nullable(strtolower(self::attr($properties, self::STYLE_NS, 'vertical-align'))),
            'writingMode' => self::nullable(self::attr($properties, self::STYLE_NS, 'writing-mode')),
            'cellProtect' => self::nullable(self::attr($properties, self::STYLE_NS, 'cell-protect')),
            'printContent' => self::nullableBool(self::attr($properties, self::STYLE_NS, 'print-content')),
            'repeatContent' => self::nullableBool(self::attr($properties, self::STYLE_NS, 'repeat-content')),
            'shrinkToFit' => self::nullableBool(self::attr($properties, self::STYLE_NS, 'shrink-to-fit')),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function paragraphProperties(\DOMElement $properties): array
    {
        $result = self::withoutEmpty([
            'textAlign' => self::nullable(self::attr($properties, self::FO_NS, 'text-align')),
            'breakBefore' => self::nullable(self::attr($properties, self::FO_NS, 'break-before')),
            'breakAfter' => self::nullable(self::attr($properties, self::FO_NS, 'break-after')),
            'keepTogether' => self::nullable(self::attr($properties, self::FO_NS, 'keep-together')),
            'keepWithNext' => self::nullable(self::attr($properties, self::FO_NS, 'keep-with-next')),
            'autoTextIndent' => self::nullableBool(self::attr($properties, self::STYLE_NS, 'auto-text-indent')),
        ]);

        foreach ([
            'textIndent' => [self::FO_NS, 'text-indent'],
            'marginLeft' => [self::FO_NS, 'margin-left'],
        ] as $target => [$namespace, $attribute]) {
            $value = self::attr($properties, $namespace, $attribute);
            if ($value === '') {
                continue;
            }

            $result[$target] = $value;
            if (str_ends_with($value, '%')) {
                $result[$target . 'Percent'] = (float) rtrim($value, '%');
                continue;
            }

            $points = $this->lengthToPoints($value);
            if ($points !== null) {
                $result[$target . 'Points'] = $points;
            }
        }

        return self::withoutEmpty($result);
    }

    /**
     * @return array<string, mixed>
     */
    private function pageLayoutDefinition(\DOMElement $pageLayout): array
    {
        $definition = self::withoutEmpty([
            'name' => self::attr($pageLayout, self::STYLE_NS, 'name'),
            'pageUsage' => self::nullable(self::attr($pageLayout, self::STYLE_NS, 'page-usage')),
            'properties' => [],
        ]);

        $properties = self::firstChildElement($pageLayout, 'page-layout-properties', self::STYLE_NS);
        if ($properties instanceof \DOMElement) {
            $definition['properties'] = $this->pageLayoutProperties($properties);
        }

        return $definition;
    }

    /**
     * @return array<string, mixed>
     */
    private function pageLayoutProperties(\DOMElement $properties): array
    {
        $lengthAttributes = [
            'pageWidth' => [self::FO_NS, 'page-width'],
            'pageHeight' => [self::FO_NS, 'page-height'],
            'margin' => [self::FO_NS, 'margin'],
            'marginTop' => [self::FO_NS, 'margin-top'],
            'marginRight' => [self::FO_NS, 'margin-right'],
            'marginBottom' => [self::FO_NS, 'margin-bottom'],
            'marginLeft' => [self::FO_NS, 'margin-left'],
        ];
        $result = [];
        foreach ($lengthAttributes as $target => [$namespace, $attribute]) {
            $value = self::attr($properties, $namespace, $attribute);
            if ($value === '') {
                continue;
            }

            $result[$target] = $value;
            $points = $this->lengthToPoints($value);
            if ($points !== null) {
                $result[$target . 'Points'] = $points;
            }
        }

        return self::withoutEmpty($result + [
            'printOrientation' => self::nullable(self::attr($properties, self::STYLE_NS, 'print-orientation')),
            'writingMode' => self::nullable(self::attr($properties, self::STYLE_NS, 'writing-mode')),
            'numFormat' => self::nullable(self::attr($properties, self::STYLE_NS, 'num-format')),
            'firstPageNumber' => self::nullable(self::attr($properties, self::STYLE_NS, 'first-page-number')),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function masterPageDefinition(\DOMElement $masterPage): array
    {
        return self::withoutEmpty([
            'name' => self::attr($masterPage, self::STYLE_NS, 'name'),
            'displayName' => self::nullable(self::attr($masterPage, self::STYLE_NS, 'display-name')),
            'pageLayoutName' => self::nullable(self::attr($masterPage, self::STYLE_NS, 'page-layout-name')),
            'nextStyleName' => self::nullable(self::attr($masterPage, self::STYLE_NS, 'next-style-name')),
            'drawStyleName' => self::nullable(self::attr($masterPage, self::DRAW_NS, 'style-name')),
            'headerText' => $this->masterPageTextBlocks($masterPage, 'header'),
            'headerLeftText' => $this->masterPageTextBlocks($masterPage, 'header-left'),
            'footerText' => $this->masterPageTextBlocks($masterPage, 'footer'),
            'footerLeftText' => $this->masterPageTextBlocks($masterPage, 'footer-left'),
        ]);
    }

    /**
     * @return list<string>
     */
    private function masterPageTextBlocks(\DOMElement $masterPage, string $containerName): array
    {
        $container = self::firstChildElement($masterPage, $containerName, self::STYLE_NS);
        if (!$container instanceof \DOMElement) {
            return [];
        }

        $texts = [];
        foreach ($container->getElementsByTagNameNS(self::TEXT_NS, 'p') as $paragraph) {
            if (!$paragraph instanceof \DOMElement) {
                continue;
            }

            $text = self::normalizedText($paragraph);
            if ($text !== '') {
                $texts[] = $text;
            }
        }

        return $texts;
    }

    /**
     * @param array<string, array<string, mixed>> $fontFaces
     * @return array<string, mixed>
     */
    private function textProperties(\DOMElement $properties, array $fontFaces = []): array
    {
        $fontWeight = strtolower(self::attr($properties, self::FO_NS, 'font-weight'));
        $fontStyle = strtolower(self::attr($properties, self::FO_NS, 'font-style'));
        $underline = strtolower(self::attr($properties, self::STYLE_NS, 'text-underline-style'));
        $strikeout = strtolower(self::attr($properties, self::STYLE_NS, 'text-line-through-style'));
        $variant = strtolower(self::attr($properties, self::FO_NS, 'font-variant'));
        $position = strtolower(self::attr($properties, self::STYLE_NS, 'text-position'));
        $fontName = self::attr($properties, self::STYLE_NS, 'font-name');
        $directPitch = strtolower(self::attr($properties, self::STYLE_NS, 'font-pitch'));

        $result = [];
        if ($fontName !== '') {
            $result['fontName'] = $fontName;
            if (isset($fontFaces[$fontName])) {
                $result['fontFace'] = $fontFaces[$fontName];
            }
        }

        $fontPitch = $directPitch;
        if ($fontPitch === '' && $fontName !== '' && isset($fontFaces[$fontName]['fontPitch'])) {
            $fontPitch = strtolower((string) $fontFaces[$fontName]['fontPitch']);
        }
        if ($fontPitch !== '') {
            $result['fontPitch'] = $fontPitch;
            if ($fontPitch === 'fixed') {
                $result['fixedPitch'] = true;
            }
        }

        if ($fontWeight === 'bold' || preg_match('/^[1-9]00$/', $fontWeight) === 1) {
            $result['bold'] = true;
        }
        if ($fontStyle === 'italic' || $fontStyle === 'oblique') {
            $result['italic'] = true;
        }
        if ($underline !== '' && $underline !== 'none') {
            $result['underline'] = true;
        }
        if ($strikeout !== '' && $strikeout !== 'none') {
            $result['strikeout'] = true;
        }
        if ($variant === 'small-caps') {
            $result['smallCaps'] = true;
        }
        if (str_contains($position, 'super')) {
            $result['superscript'] = true;
        } elseif (str_contains($position, 'sub')) {
            $result['subscript'] = true;
        }

        return $result;
    }

    /**
     * @param array<string, array<string, mixed>> $fontFaces
     * @return array{name:string, levels:array<int, array<string, mixed>>}
     */
    private function listStyleDefinition(\DOMElement $listStyle, array $fontFaces = []): array
    {
        $levels = [];
        foreach (self::childElements($listStyle) as $levelStyle) {
            if (!$this->isElement($levelStyle, self::TEXT_NS, 'list-level-style-bullet')
                && !$this->isElement($levelStyle, self::TEXT_NS, 'list-level-style-number')
                && !$this->isElement($levelStyle, self::TEXT_NS, 'list-level-style-image')
            ) {
                continue;
            }

            $level = max(1, self::intAttr($levelStyle, self::TEXT_NS, 'level', 1));
            $type = match ($levelStyle->localName) {
                'list-level-style-number' => 'number',
                'list-level-style-image' => 'image',
                default => 'bullet',
            };
            $levelDefinition = [
                'type' => $type,
                'level' => $level,
                'format' => self::attr($levelStyle, self::STYLE_NS, 'num-format'),
                'numPrefix' => self::attr($levelStyle, self::STYLE_NS, 'num-prefix'),
                'numSuffix' => self::attr($levelStyle, self::STYLE_NS, 'num-suffix'),
                'bulletChar' => self::attr($levelStyle, self::TEXT_NS, 'bullet-char'),
                'start' => self::intAttr($levelStyle, self::TEXT_NS, 'start-value', 1),
            ];
            if ($type === 'image') {
                $levelDefinition['image'] = $this->listLevelImageMetadata($levelStyle);
            }
            $levelProperties = $this->listLevelProperties($levelStyle);
            if ($levelProperties !== []) {
                $levelDefinition['levelProperties'] = $levelProperties;
            }
            $textProperties = self::firstChildElement($levelStyle, 'text-properties', self::STYLE_NS);
            if ($textProperties instanceof \DOMElement) {
                $listTextProperties = $this->textProperties($textProperties, $fontFaces);
                if ($listTextProperties !== []) {
                    $levelDefinition['textProperties'] = $listTextProperties;
                }
            }

            $levels[$level] = $levelDefinition;
        }

        return [
            'name' => self::attr($listStyle, self::STYLE_NS, 'name'),
            'levels' => $levels,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function listLevelImageMetadata(\DOMElement $levelStyle): array
    {
        return self::withoutEmpty([
            'href' => self::nullable(self::attr($levelStyle, self::XLINK_NS, 'href')),
            'type' => self::nullable(self::attr($levelStyle, self::XLINK_NS, 'type')),
            'show' => self::nullable(self::attr($levelStyle, self::XLINK_NS, 'show')),
            'actuate' => self::nullable(self::attr($levelStyle, self::XLINK_NS, 'actuate')),
            'title' => self::nullable(self::attr($levelStyle, self::XLINK_NS, 'title')),
            'width' => self::nullable(self::attr($levelStyle, self::SVG_NS, 'width')),
            'height' => self::nullable(self::attr($levelStyle, self::SVG_NS, 'height')),
            'embeddedBinaryData' => self::firstChildElement($levelStyle, 'binary-data', self::OFFICE_NS) instanceof \DOMElement ? true : null,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function listLevelProperties(\DOMElement $levelStyle): array
    {
        $properties = self::firstChildElement($levelStyle, 'list-level-properties', self::STYLE_NS);
        if (!$properties instanceof \DOMElement) {
            return [];
        }

        $metadata = self::withoutEmpty([
            'minLabelWidth' => self::nullable(self::attr($properties, self::TEXT_NS, 'min-label-width')),
            'minLabelDistance' => self::nullable(self::attr($properties, self::TEXT_NS, 'min-label-distance')),
            'spaceBefore' => self::nullable(self::attr($properties, self::TEXT_NS, 'space-before')),
            'positionAndSpaceMode' => self::nullable(self::attr($properties, self::TEXT_NS, 'list-level-position-and-space-mode')),
        ]);

        $alignment = self::firstChildElement($properties, 'list-level-label-alignment', self::STYLE_NS);
        if ($alignment instanceof \DOMElement) {
            $metadata['labelAlignment'] = self::withoutEmpty([
                'labelFollowedBy' => self::nullable(self::attr($alignment, self::TEXT_NS, 'label-followed-by')),
                'listTabStopPosition' => self::nullable(self::attr($alignment, self::TEXT_NS, 'list-tab-stop-position')),
                'textIndent' => self::nullable(self::attr($alignment, self::FO_NS, 'text-indent')),
                'marginLeft' => self::nullable(self::attr($alignment, self::FO_NS, 'margin-left')),
            ]);
            if ($metadata['labelAlignment'] === []) {
                unset($metadata['labelAlignment']);
            }
        }

        return $metadata;
    }

    /**
     * @return array{name:string, styles:array<string, string>}
     */
    private function tableTemplateDefinition(\DOMElement $tableTemplate): array
    {
        $styles = [];
        foreach ([
            'first-row-start-column' => 'firstRowStartColumn',
            'first-row-end-column' => 'firstRowEndColumn',
            'first-column' => 'firstColumn',
            'last-column' => 'lastColumn',
            'first-row' => 'firstRow',
            'last-row' => 'lastRow',
            'body' => 'body',
            'odd-rows' => 'oddRows',
            'even-rows' => 'evenRows',
            'odd-columns' => 'oddColumns',
            'even-columns' => 'evenColumns',
        ] as $attribute => $name) {
            $value = self::attr($tableTemplate, self::TABLE_NS, $attribute);
            if ($value !== '') {
                $styles[$name] = $value;
            }
        }

        return [
            'name' => self::attr($tableTemplate, self::TABLE_NS, 'name'),
            'styles' => $styles,
        ];
    }

    /**
     * @param array<string, mixed> $template
     * @return list<string>
     */
    private function tableTemplateStyleNames(array $template): array
    {
        $styles = $template['styles'] ?? [];
        if (!is_array($styles)) {
            return [];
        }

        return array_values(array_filter(
            array_map(static fn (mixed $value): string => (string) $value, $styles),
            static fn (string $value): bool => $value !== ''
        ));
    }

    /**
     * @param array<string, array<string, mixed>> $styles
     */
    private function styleMapCount(array $styles): int
    {
        $count = 0;
        foreach ($styles as $style) {
            $styleMaps = $style['styleMaps'] ?? [];
            if (is_array($styleMaps)) {
                $count += count($styleMaps);
            }
        }

        return $count;
    }

    /**
     * @param array{styles:array<string, array<string, mixed>>, fontFaces:array<string, array<string, mixed>>, listStyles:array<string, array<string, mixed>>, tableTemplates:array<string, array<string, mixed>>, pageLayouts:array<string, array<string, mixed>>, masterPages:array<string, array<string, mixed>>} $target
     * @param array{styles:array<string, array<string, mixed>>, fontFaces:array<string, array<string, mixed>>, listStyles:array<string, array<string, mixed>>, tableTemplates:array<string, array<string, mixed>>, pageLayouts:array<string, array<string, mixed>>, masterPages:array<string, array<string, mixed>>} $source
     */
    private function mergeStyleCollections(array &$target, array $source): void
    {
        foreach ($source['styles'] as $name => $style) {
            $target['styles'][$name] = $style;
        }
        foreach ($source['fontFaces'] as $name => $fontFace) {
            $target['fontFaces'][$name] = $fontFace;
        }
        foreach ($source['listStyles'] as $name => $style) {
            $target['listStyles'][$name] = $style;
        }
        foreach ($source['tableTemplates'] as $name => $template) {
            $target['tableTemplates'][$name] = $template;
        }
        foreach ($source['pageLayouts'] as $name => $layout) {
            $target['pageLayouts'][$name] = $layout;
        }
        foreach ($source['masterPages'] as $name => $page) {
            $target['masterPages'][$name] = $page;
        }
    }

    /**
     * @param array{styles:array<string, array<string, mixed>>, listStyles:array<string, array<string, mixed>>} $catalog
     * @return array<string, mixed>
     */
    private function resolveStyle(string $name, array $catalog, array $seen = []): array
    {
        if ($name === '' || !isset($catalog['styles'][$name]) || isset($seen[$name])) {
            return [];
        }

        $style = $catalog['styles'][$name];
        $seen[$name] = true;
        $parentName = (string) ($style['parentName'] ?? '');
        $parent = $this->resolveStyle($parentName, $catalog, $seen);
        if ($parent === []) {
            return $style;
        }

        return $this->mergeResolvedStyle($parent, $style);
    }

    /**
     * @param array<string, mixed> $parent
     * @param array<string, mixed> $style
     * @return array<string, mixed>
     */
    private function mergeResolvedStyle(array $parent, array $style): array
    {
        $merged = $parent;
        foreach ($style as $key => $value) {
            if (is_array($value) && isset($merged[$key]) && is_array($merged[$key])) {
                $merged[$key] = array_merge($merged[$key], array_filter(
                    $value,
                    static fn (mixed $entry): bool => $entry !== null && $entry !== ''
                ));
                continue;
            }
            if ($value !== null && $value !== '') {
                $merged[$key] = $value;
            }
        }

        return $merged;
    }

    /**
     * @param array<string, mixed> $style
     */
    private function isBlockquoteParagraphStyle(array $style): bool
    {
        $properties = $style['paragraphProperties'] ?? [];
        if (!is_array($properties)) {
            return false;
        }

        return $this->isQuoteWidth(
            (string) ($properties['textIndent'] ?? ''),
            (string) ($properties['marginLeft'] ?? '')
        );
    }

    /**
     * @param array<string, mixed> $style
     */
    private function isPreformattedParagraphStyle(string $styleName, array $style): bool
    {
        if ($style === []) {
            return false;
        }

        return $styleName === 'Preformatted_20_Text'
            || (string) ($style['parentName'] ?? '') === 'Preformatted_20_Text';
    }

    private function isInlineCodeTextStyle(string $styleName): bool
    {
        return $styleName === 'Source_Text' || $styleName === 'Source_20_Text';
    }

    private function isQuoteWidth(string $textIndent, string $marginLeft): bool
    {
        $indent = $this->quoteMeasure($textIndent);
        $margin = $this->quoteMeasure($marginLeft);
        $pointThreshold = 5.0 * 2.83464567;
        $percentThreshold = 5.0;

        if (($indent['unit'] ?? '') === 'pt' && $indent['value'] > $pointThreshold) {
            return true;
        }
        if (($margin['unit'] ?? '') === 'pt' && $margin['value'] > $pointThreshold) {
            return true;
        }
        if (($indent['unit'] ?? '') === 'pt' && ($margin['unit'] ?? '') === 'pt' && $indent['value'] + $margin['value'] > $pointThreshold) {
            return true;
        }
        if (($indent['unit'] ?? '') === 'percent' && $indent['value'] > $percentThreshold) {
            return true;
        }
        if (($margin['unit'] ?? '') === 'percent' && $margin['value'] > $percentThreshold) {
            return true;
        }
        if (($indent['unit'] ?? '') === 'percent' && ($margin['unit'] ?? '') === 'percent' && $indent['value'] + $margin['value'] > $percentThreshold) {
            return true;
        }

        return false;
    }

    /**
     * @return array{unit:'pt'|'percent', value:float}|null
     */
    private function quoteMeasure(string $value): ?array
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }
        if (preg_match('/^([0-9]+(?:\.[0-9]+)?)%$/', $value, $matches) === 1) {
            return [
                'unit' => 'percent',
                'value' => (float) $matches[1],
            ];
        }

        $points = $this->lengthToPoints($value);
        if ($points === null) {
            return null;
        }

        return [
            'unit' => 'pt',
            'value' => $points,
        ];
    }

    /**
     * @param array{styles:array<string, array<string, mixed>>, listStyles:array<string, array<string, mixed>>} $catalog
     * @return array<string, mixed>
     */
    private function listDefinition(string $styleName, int $level, array $catalog): array
    {
        $listStyle = $catalog['listStyles'][$styleName] ?? null;
        if (!is_array($listStyle)) {
            return [
                'type' => 'bullet',
                'level' => $level,
                'bulletChar' => 'bullet',
            ];
        }

        $levels = $listStyle['levels'] ?? [];
        if (!is_array($levels)) {
            return [
                'type' => 'bullet',
                'level' => $level,
                'bulletChar' => 'bullet',
            ];
        }

        $definition = $levels[$level] ?? null;
        if (!is_array($definition)) {
            $nearestLowerLevel = null;
            foreach ($levels as $candidateLevel => $candidateDefinition) {
                if (!is_array($candidateDefinition)) {
                    continue;
                }
                if (!is_int($candidateLevel) && !ctype_digit((string) $candidateLevel)) {
                    continue;
                }

                $candidateLevel = (int) $candidateLevel;
                if ($candidateLevel >= $level) {
                    continue;
                }
                if ($nearestLowerLevel === null || $candidateLevel > $nearestLowerLevel) {
                    $nearestLowerLevel = $candidateLevel;
                    $definition = $candidateDefinition;
                }
            }
        }
        if (!is_array($definition)) {
            $definition = reset($levels);
        }

        return is_array($definition) ? $definition : [
            'type' => 'bullet',
            'level' => $level,
            'bulletChar' => 'bullet',
        ];
    }

    private function orderedListStyle(string $format): string
    {
        return match ($format) {
            'a' => 'lower_alpha',
            'A' => 'upper_alpha',
            'i' => 'lower_roman',
            'I' => 'upper_roman',
            default => 'decimal',
        };
    }

    private function orderedListDelimiter(string $prefix, string $suffix): string
    {
        return match ([$prefix, $suffix]) {
            ['', '.'] => 'period',
            ['', ')'] => 'one_paren',
            ['(', ')'] => 'two_parens',
            default => ($prefix !== '' || $suffix !== '') ? 'default' : '',
        };
    }

    /**
     * @param list<array<string, mixed>> $manifest
     * @return list<array<string, mixed>>
     */
    private function mediaReport(ZipPackage $package, array $manifest): array
    {
        $media = [];
        foreach ($manifest as $item) {
            $part = $item['part'] ?? null;
            $mediaType = (string) ($item['mediaType'] ?? '');
            if (!is_string($part) || $part === '') {
                continue;
            }
            if (str_ends_with($part, '/')) {
                continue;
            }
            if (in_array($part, ['content.xml', 'styles.xml', 'meta.xml', 'settings.xml'], true)) {
                continue;
            }
            if ($mediaType === '' || $this->isXmlMediaType($mediaType)) {
                continue;
            }

            $encrypted = ($item['encrypted'] ?? false) === true;
            $entry = $package->has($part) ? $package->entry($part) : null;
            $media[] = [
                'fullPath' => $item['fullPath'],
                'part' => $part,
                'mediaType' => $mediaType,
                'exists' => $entry instanceof ZipPackageEntry,
                'byteLength' => !$encrypted && $entry instanceof ZipPackageEntry ? $entry->uncompressedSize : null,
                'crc32' => !$encrypted && $entry instanceof ZipPackageEntry ? $entry->crc32Hex() : null,
                'storedByteLength' => $entry instanceof ZipPackageEntry ? $entry->uncompressedSize : null,
                'storedCrc32' => $entry instanceof ZipPackageEntry ? $entry->crc32Hex() : null,
                'declaredSize' => $item['declaredSize'] ?? null,
                'preferredViewMode' => $item['preferredViewMode'] ?? null,
                'encrypted' => $encrypted,
                'canExposeBytes' => !$encrypted,
                'encryption' => $item['encryption'] ?? null,
            ];
        }

        return $media;
    }

    private function isXmlMediaType(string $mediaType): bool
    {
        $base = strtolower(trim(explode(';', $mediaType, 2)[0]));

        return $base === 'text/xml'
            || $base === 'application/xml'
            || str_ends_with($base, '+xml');
    }

    /**
     * @param list<array<string, mixed>> $manifest
     * @return array<string, array<string, mixed>>
     */
    private function manifestByPart(array $manifest): array
    {
        $byPart = [];
        foreach ($manifest as $item) {
            $part = $item['part'] ?? null;
            if (is_string($part) && $part !== '') {
                $byPart[$part] = $item;
            }
        }

        return $byPart;
    }

    /**
     * @param list<array<string, mixed>> $manifest
     * @return list<array<string, mixed>>
     */
    private function encryptedManifestItems(array $manifest): array
    {
        return array_values(array_filter(
            $manifest,
            static fn (array $item): bool => ($item['encrypted'] ?? false) === true
        ));
    }

    /**
     * @param list<AstNode> $nodes
     * @return array{blockquoteCount:int, noteCount:int, bookmarkCount:int, bookmarkReferenceCount:int, referenceMarkCount:int, referenceReferenceCount:int, indexMarkCount:int, sequenceCount:int, fieldCount:int, metaSpanCount:int, placeholderCount:int, rubyCount:int, softPageBreakCount:int, citationCount:int, eventListenerCount:int, annotationRangeCount:int, trackedChangeCount:int, mathCount:int, embeddedObjectCount:int, missingEmbeddedObjectCount:int, chartObjectCount:int, chartMetadataCount:int, formControlCount:int, missingFormControlCount:int, formControlOptionCount:int, selectedFormControlOptionCount:int, sectionCount:int, linkedSectionCount:int, protectedSectionCount:int, conditionalSectionCount:int, hiddenSectionCount:int, tableOfContentsCount:int, generatedIndexCount:int, tableCaptionCount:int, preformattedCodeBlockCount:int, continuedListCount:int, listHeaderCount:int, tableTemplateReferenceCount:int, tablePrintRangeCount:int, tableScenarioCount:int, activeTableScenarioCount:int, tableColumnDefinitionCount:int, hiddenTableColumnCount:int, tableRowDefinitionCount:int, hiddenTableRowCount:int, repeatedTableRowCount:int, truncatedTableRowRepeatCount:int}
     */
    private function contentNodeStats(array $nodes): array
    {
        $stats = [
            'blockquoteCount' => 0,
            'noteCount' => 0,
            'bookmarkCount' => 0,
            'bookmarkReferenceCount' => 0,
            'referenceMarkCount' => 0,
            'referenceReferenceCount' => 0,
            'indexMarkCount' => 0,
            'sequenceCount' => 0,
            'fieldCount' => 0,
            'metaSpanCount' => 0,
            'placeholderCount' => 0,
            'rubyCount' => 0,
            'softPageBreakCount' => 0,
            'citationCount' => 0,
            'eventListenerCount' => 0,
            'annotationRangeCount' => 0,
            'trackedChangeCount' => 0,
            'mathCount' => 0,
            'embeddedObjectCount' => 0,
            'missingEmbeddedObjectCount' => 0,
            'chartObjectCount' => 0,
            'chartMetadataCount' => 0,
            'chartTitleCount' => 0,
            'chartAxisCount' => 0,
            'chartLegendCount' => 0,
            'formControlCount' => 0,
            'missingFormControlCount' => 0,
            'formControlOptionCount' => 0,
            'selectedFormControlOptionCount' => 0,
            'sectionCount' => 0,
            'linkedSectionCount' => 0,
            'protectedSectionCount' => 0,
            'conditionalSectionCount' => 0,
            'hiddenSectionCount' => 0,
            'tableOfContentsCount' => 0,
            'generatedIndexCount' => 0,
            'tableCaptionCount' => 0,
            'preformattedCodeBlockCount' => 0,
            'continuedListCount' => 0,
            'listHeaderCount' => 0,
            'imageListStyleCount' => 0,
            'listTextPropertyCount' => 0,
            'tableTemplateReferenceCount' => 0,
            'tablePrintRangeCount' => 0,
            'tableScenarioCount' => 0,
            'activeTableScenarioCount' => 0,
            'tableColumnDefinitionCount' => 0,
            'hiddenTableColumnCount' => 0,
            'tableRowDefinitionCount' => 0,
            'hiddenTableRowCount' => 0,
            'repeatedTableRowCount' => 0,
            'truncatedTableRowRepeatCount' => 0,
            'tableCoveredCellCount' => 0,
            'tableCoveredCellMetadataCount' => 0,
            'tableCellAnnotationCount' => 0,
            'tableStyledCellCount' => 0,
            'tableProtectedCellCount' => 0,
            'tablePrintHiddenCellCount' => 0,
            'frameLayerReferenceCount' => 0,
            'frameCaptionCount' => 0,
        ];
        foreach ($nodes as $node) {
            if ($node->type === 'note') {
                $stats['noteCount']++;
            }
            if ($node->type === 'blockquote' && $this->nodeHasClass($node, 'odf-blockquote')) {
                $stats['blockquoteCount']++;
            }
            if ($node->type === 'div' && $this->nodeHasClass($node, 'odf-section')) {
                $stats['sectionCount']++;
            }
            if ($node->type === 'div' && $this->nodeHasClass($node, 'odf-linked-section')) {
                $stats['linkedSectionCount']++;
            }
            if ($node->type === 'div' && $this->nodeHasClass($node, 'odf-protected-section')) {
                $stats['protectedSectionCount']++;
            }
            if ($node->type === 'div' && $this->nodeHasClass($node, 'odf-conditional-section')) {
                $stats['conditionalSectionCount']++;
            }
            if ($node->type === 'div' && $this->nodeHasClass($node, 'odf-hidden-section')) {
                $stats['hiddenSectionCount']++;
            }
            if ($node->type === 'div' && $this->nodeHasClass($node, 'odf-table-of-contents')) {
                $stats['tableOfContentsCount']++;
            }
            if ($node->type === 'div' && $this->nodeHasClass($node, 'odf-generated-index')) {
                $stats['generatedIndexCount']++;
            }
            if ($node->type === 'div' && $this->nodeHasClass($node, 'odf-table-caption')) {
                $stats['tableCaptionCount']++;
            }
            if ($node->type === 'figure' && $this->nodeHasClass($node, 'odf-frame-caption')) {
                $stats['frameCaptionCount']++;
            }
            if ($node->type === 'table' && $node->attr('odfCaptionParagraph') === true) {
                $stats['tableCaptionCount']++;
            }
            if ($node->type === 'code_block' && $node->attr('odfPreformatted') === true) {
                $stats['preformattedCodeBlockCount']++;
            }
            if ($node->type === 'table' && (string) $node->attr('templateName', '') !== '') {
                $stats['tableTemplateReferenceCount']++;
            }
            if ($node->type === 'table') {
                $printRanges = $node->attr('odfPrintRanges', []);
                if (is_array($printRanges)) {
                    $stats['tablePrintRangeCount'] += count($printRanges);
                }
            }
            if ($node->type === 'table') {
                $scenarios = $node->attr('odfTableScenarios', []);
                if (is_array($scenarios)) {
                    $stats['tableScenarioCount'] += count($scenarios);
                    $stats['activeTableScenarioCount'] += $this->activeTableScenarioCount($scenarios);
                }
            }
            if ($node->type === 'table') {
                $columns = $node->attr('odfTableColumns', []);
                if (is_array($columns)) {
                    $stats['tableColumnDefinitionCount'] += count($columns);
                    foreach ($columns as $column) {
                        if (is_array($column) && ($column['hidden'] ?? false) === true) {
                            $stats['hiddenTableColumnCount']++;
                        }
                    }
                }
            }
            if ($node->type === 'table_row' && $node->attr('odfTableRowMetadata', []) !== []) {
                $stats['tableRowDefinitionCount']++;
                if ($node->attr('hidden') === true) {
                    $stats['hiddenTableRowCount']++;
                }
                if (is_numeric($node->attr('sourceRepeat', 0)) && (int) $node->attr('sourceRepeat', 0) > 1) {
                    $stats['repeatedTableRowCount']++;
                }
                if ($node->attr('repeatTruncated') === true) {
                    $stats['truncatedTableRowRepeatCount']++;
                }
            }
            $coveredCells = $node->attr('odfCoveredCells', []);
            if (is_array($coveredCells) && $coveredCells !== []) {
                $stats['tableCoveredCellCount'] += count($coveredCells);
                foreach ($coveredCells as $coveredCell) {
                    if (is_array($coveredCell) && $this->coveredTableCellHasReviewMetadata($coveredCell)) {
                        $stats['tableCoveredCellMetadataCount']++;
                    }
                }
            }
            if ($node->type === 'table_cell' && $node->attr('odfCellStyleProperties', []) !== []) {
                $stats['tableStyledCellCount']++;
                $properties = $node->attr('odfCellStyleProperties', []);
                if (is_array($properties) && (string) ($properties['cellProtect'] ?? '') !== '') {
                    $stats['tableProtectedCellCount']++;
                }
                if (is_array($properties) && ($properties['printContent'] ?? null) === false) {
                    $stats['tablePrintHiddenCellCount']++;
                }
            }
            $cellAnnotations = $node->attr('odfCellAnnotations', []);
            if ($node->type === 'table_cell' && is_array($cellAnnotations)) {
                $stats['tableCellAnnotationCount'] += count($cellAnnotations);
            }
            if ($node->type === 'span' && $this->nodeHasClass($node, 'odf-bookmark')) {
                $stats['bookmarkCount']++;
            }
            if ($node->type === 'link' && $this->nodeHasClass($node, 'odf-bookmark-ref')) {
                $stats['bookmarkReferenceCount']++;
            }
            if ($node->type === 'span' && $this->nodeHasClass($node, 'odf-reference-mark')) {
                $stats['referenceMarkCount']++;
            }
            if ($node->type === 'link' && $this->nodeHasClass($node, 'odf-reference-ref')) {
                $stats['referenceReferenceCount']++;
            }
            if ($node->type === 'span' && $this->nodeHasClass($node, 'odf-index-mark')) {
                $stats['indexMarkCount']++;
            }
            if ($node->type === 'span' && $this->nodeHasClass($node, 'odf-sequence')) {
                $stats['sequenceCount']++;
            }
            if ($node->type === 'span' && $this->nodeHasClass($node, 'odf-field')) {
                $stats['fieldCount']++;
            }
            if ($node->type === 'span' && $this->nodeHasClass($node, 'odf-meta')) {
                $stats['metaSpanCount']++;
            }
            if ($node->type === 'span' && $this->nodeHasClass($node, 'odf-placeholder')) {
                $stats['placeholderCount']++;
            }
            if ($node->type === 'span' && $this->nodeHasClass($node, 'odf-ruby')) {
                $stats['rubyCount']++;
            }
            if ($node->type === 'span' && $this->nodeHasClass($node, 'odf-soft-page-break')) {
                $stats['softPageBreakCount']++;
            }
            if ($node->type === 'citation') {
                $stats['citationCount']++;
            }
            if ($node->type === 'link') {
                $linkMetadata = $node->attr('odfLinkMetadata', []);
                $eventListeners = is_array($linkMetadata) ? ($linkMetadata['eventListeners'] ?? []) : [];
                if (is_array($eventListeners)) {
                    $stats['eventListenerCount'] += count($eventListeners);
                }
            }
            if ($node->type === 'span' && $this->nodeHasClass($node, 'odf-annotation-range')) {
                $stats['annotationRangeCount']++;
            }
            if ($node->type === 'span' && $this->nodeHasClass($node, 'odf-change')) {
                $stats['trackedChangeCount']++;
            }
            if ($node->type === 'math') {
                $stats['mathCount']++;
            }
            if (($node->type === 'span' || $node->type === 'div') && $this->nodeHasClass($node, 'odf-embedded-object')) {
                $stats['embeddedObjectCount']++;
                if ($node->attr('exists') !== true) {
                    $stats['missingEmbeddedObjectCount']++;
                }
                if ($node->attr('objectType') === 'chart') {
                    $stats['chartObjectCount']++;
                    $chartMetadata = $node->attr('chartMetadata');
                    if (is_array($chartMetadata)) {
                        $stats['chartMetadataCount']++;
                        $title = $chartMetadata['title'] ?? null;
                        if (is_array($title) && is_scalar($title['text'] ?? null) && (string) $title['text'] !== '') {
                            $stats['chartTitleCount']++;
                        }
                        $axes = $chartMetadata['axes'] ?? [];
                        if (is_array($axes)) {
                            $stats['chartAxisCount'] += count($axes);
                        }
                        $legend = $chartMetadata['legend'] ?? null;
                        if (is_array($legend) && $legend !== []) {
                            $stats['chartLegendCount']++;
                        }
                    }
                }
            }
            if (($node->type === 'span' || $node->type === 'div') && $this->nodeHasClass($node, 'odf-form-control')) {
                $stats['formControlCount']++;
                if ($node->attr('exists') !== true) {
                    $stats['missingFormControlCount']++;
                }
                $formControl = $node->attr('formControl');
                if (is_array($formControl)) {
                    $options = $formControl['options'] ?? [];
                    if (is_array($options)) {
                        $stats['formControlOptionCount'] += count($options);
                        foreach ($options as $option) {
                            if (is_array($option) && ($option['selected'] ?? false) === true) {
                                $stats['selectedFormControlOptionCount']++;
                            }
                        }
                    }
                }
            }
            $frameMetadata = $node->attr('odfFrameMetadata');
            if (is_array($frameMetadata) && (string) ($frameMetadata['layer'] ?? '') !== '') {
                $stats['frameLayerReferenceCount']++;
            }
            if (($node->type === 'ordered_list' || $node->type === 'bullet_list') && $node->attr('continued') === true) {
                $stats['continuedListCount']++;
            }
            if ($node->type === 'list_item' && $node->attr('listHeader') === true) {
                $stats['listHeaderCount']++;
            }
            if (($node->type === 'ordered_list' || $node->type === 'bullet_list') && $node->attr('listImageStyle') === true) {
                $stats['imageListStyleCount']++;
            }
            $listTextProperties = $node->attr('listTextProperties', []);
            if (($node->type === 'ordered_list' || $node->type === 'bullet_list')
                && is_array($listTextProperties)
                && $listTextProperties !== []
            ) {
                $stats['listTextPropertyCount']++;
            }

            $childStats = $this->contentNodeStats($node->children);
            foreach ($childStats as $name => $count) {
                $stats[$name] += $count;
            }
        }

        return $stats;
    }

    private function nodeHasClass(AstNode $node, string $class): bool
    {
        $classes = $node->attr('classes', []);
        if (!is_array($classes)) {
            return false;
        }

        return in_array($class, array_map(static fn (mixed $value): string => (string) $value, $classes), true);
    }

    private function manifestPackagePart(string $path): string
    {
        $path = preg_replace('/[#?].*$/', '', $path) ?? $path;
        $path = rawurldecode($path);
        $path = ltrim($path, '/');
        while (str_starts_with($path, './')) {
            $path = substr($path, 2);
        }
        if ($path === '') {
            throw new \RuntimeException('ODT package part path must not be empty');
        }
        if (str_contains($path, '..') || str_contains($path, '\\') || preg_match('/^[A-Za-z][A-Za-z0-9+.-]*:/', $path) === 1) {
            throw new \InvalidArgumentException('ODT package part path is not a safe package-relative path: ' . $path);
        }

        return $path;
    }

    /**
     * @return list<AstNode>
     */
    private function coalesceTextNodes(array $nodes): array
    {
        $coalesced = [];
        foreach ($nodes as $node) {
            if ($node->type === 'text' && $coalesced !== []) {
                $last = $coalesced[count($coalesced) - 1];
                if ($last->type === 'text') {
                    $attrs = $last->attrs;
                    $attrs['text'] = (string) ($attrs['text'] ?? '') . (string) $node->attr('text', '');
                    $coalesced[count($coalesced) - 1] = new AstNode('text', $attrs, $last->children);
                    continue;
                }
            }

            $coalesced[] = $node;
        }

        return $coalesced;
    }

    /**
     * @param list<AstNode> $inlines
     * @return array{inlines:list<AstNode>, anchor:?array{id:string, bookmarkName:string}}
     */
    private function extractHeadingBookmarkAnchor(array $inlines): array
    {
        $filtered = [];
        $anchor = null;
        foreach ($inlines as $node) {
            if ($anchor === null && $this->nodeHasClass($node, 'odf-bookmark')) {
                $id = (string) $node->attr('id', '');
                $attributes = $node->attr('attributes', []);
                $bookmarkName = is_array($attributes) ? (string) ($attributes['data-odf-bookmark-name'] ?? '') : '';
                if ($id !== '' && $bookmarkName !== '') {
                    $anchor = [
                        'id' => $id,
                        'bookmarkName' => $bookmarkName,
                    ];
                    continue;
                }
            }

            $filtered[] = $node;
        }

        return [
            'inlines' => $filtered,
            'anchor' => $anchor,
        ];
    }

    /**
     * @param array<string, mixed> $attrs
     * @param array{id:string, bookmarkName:string} $anchor
     */
    private function addHeadingBookmarkAnchorAttrs(array &$attrs, array $anchor): void
    {
        $attrs['odfHeadingAnchor'] = [
            'source' => 'bookmark',
            'bookmarkName' => $anchor['bookmarkName'],
            'id' => $attrs['id'],
        ];
        $attrs['attributes']['data-odf-heading-anchor-source'] = 'bookmark';
        $attrs['attributes']['data-odf-heading-bookmark-name'] = $anchor['bookmarkName'];
        $attrs['attributes']['data-odf-heading-anchor-id'] = $attrs['id'];
    }

    /**
     * @return ?array{sourceId:string, attributeName:string}
     */
    private function headingAttributeAnchor(\DOMElement $heading): ?array
    {
        $textId = self::attr($heading, self::TEXT_NS, 'id');
        if ($textId !== '') {
            return [
                'sourceId' => $textId,
                'attributeName' => 'text:id',
            ];
        }

        $xmlId = self::attr($heading, self::XML_NS, 'id');
        if ($xmlId !== '') {
            return [
                'sourceId' => $xmlId,
                'attributeName' => 'xml:id',
            ];
        }

        return null;
    }

    /**
     * @param array<string, mixed> $attrs
     * @param array{sourceId:string, attributeName:string} $anchor
     */
    private function addHeadingAttributeAnchorAttrs(array &$attrs, array $anchor): void
    {
        $attrs['odfHeadingAnchor'] = [
            'source' => 'attribute',
            'attributeName' => $anchor['attributeName'],
            'sourceId' => $anchor['sourceId'],
            'id' => $attrs['id'],
        ];
        $attrs['attributes']['data-odf-heading-anchor-source'] = 'attribute';
        $attrs['attributes']['data-odf-heading-source-attribute'] = $anchor['attributeName'];
        $attrs['attributes']['data-odf-heading-source-id'] = $anchor['sourceId'];
        $attrs['attributes']['data-odf-heading-anchor-id'] = $attrs['id'];
    }

    /**
     * @param list<AstNode> $nodes
     */
    private function plainInlineText(array $nodes): string
    {
        $text = '';
        foreach ($nodes as $node) {
            if ($node->type === 'text') {
                $text .= (string) $node->attr('text', '');
                continue;
            }
            if ($node->type === 'code') {
                $text .= (string) $node->attr('text', '');
                continue;
            }
            if ($node->type === 'linebreak') {
                $text .= "\n";
                continue;
            }
            if ($node->type === 'image') {
                $text .= (string) $node->attr('alt', '');
                continue;
            }
            if ($node->type === 'math') {
                $text .= (string) $node->attr('text', '');
                continue;
            }
            if ($node->type === 'note') {
                continue;
            }
            $text .= $this->plainInlineText($node->children);
        }

        return $text;
    }

    /**
     * @param list<AstNode> $blocks
     */
    private function plainBlockText(array $blocks): string
    {
        $parts = [];
        foreach ($blocks as $block) {
            if ($block->children !== []) {
                $parts[] = $this->plainInlineText($block->children);
            } else {
                $parts[] = (string) $block->attr('text', '');
            }
        }

        return trim(implode(' ', array_filter($parts, static fn (string $part): bool => $part !== '')));
    }

    private function lengthToPoints(string $length): ?float
    {
        if ($length === '' || preg_match('/^([0-9]+(?:\.[0-9]+)?)(cm|mm|in|pt)$/', $length, $matches) !== 1) {
            return null;
        }

        $value = (float) $matches[1];

        return match ($matches[2]) {
            'cm' => $value * 28.3464567,
            'mm' => $value * 2.83464567,
            'in' => $value * 72.0,
            default => $value,
        };
    }

    private function isElement(\DOMElement $element, string $namespace, string $localName): bool
    {
        return $element->namespaceURI === $namespace && $element->localName === $localName;
    }

    private static function loadXml(string $xml, string $label): \DOMDocument
    {
        $dom = new \DOMDocument();
        $previous = libxml_use_internal_errors(true);
        try {
            $loaded = $dom->loadXML($xml, LIBXML_NONET);
            if (!$loaded) {
                $errors = libxml_get_errors();
                $message = $errors === [] ? 'unknown XML parse error' : trim($errors[0]->message);
                throw new \InvalidArgumentException("Unable to parse {$label}: {$message}");
            }
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }

        return $dom;
    }

    /**
     * @return list<\DOMElement>
     */
    private static function childElements(\DOMElement $element, ?string $localName = null, ?string $namespace = null): array
    {
        $elements = [];
        foreach ($element->childNodes as $child) {
            if (!$child instanceof \DOMElement) {
                continue;
            }
            if ($localName !== null && $child->localName !== $localName) {
                continue;
            }
            if ($namespace !== null && $child->namespaceURI !== $namespace) {
                continue;
            }
            $elements[] = $child;
        }

        return $elements;
    }

    private static function firstChildElement(\DOMElement $element, ?string $localName = null, ?string $namespace = null): ?\DOMElement
    {
        foreach (self::childElements($element, $localName, $namespace) as $child) {
            return $child;
        }

        return null;
    }

    private static function attr(\DOMElement $element, string $namespace, string $name): string
    {
        return trim($element->getAttributeNS($namespace, $name));
    }

    private static function intAttr(\DOMElement $element, string $namespace, string $name, int $default): int
    {
        $value = self::attr($element, $namespace, $name);

        return ctype_digit($value) ? (int) $value : $default;
    }

    private static function nullableInt(string $value): ?int
    {
        return ctype_digit($value) ? (int) $value : null;
    }

    private static function nullableBool(string $value): ?bool
    {
        $value = strtolower(trim($value));
        if ($value === '') {
            return null;
        }

        return in_array($value, ['true', '1', 'yes', 'checked'], true);
    }

    private static function typedOdfAttributeValue(string $value): mixed
    {
        $normalized = strtolower(trim($value));
        if ($normalized === 'true' || $normalized === 'false') {
            return $normalized === 'true';
        }

        if (preg_match('/^-?\d+$/', $value) === 1) {
            return (int) $value;
        }

        return $value;
    }

    private static function nullable(string $value): ?string
    {
        return $value === '' ? null : $value;
    }

    /**
     * @param array<string, mixed> $values
     * @return array<string, mixed>
     */
    private static function withoutEmpty(array $values): array
    {
        return array_filter(
            $values,
            static fn (mixed $value): bool => $value !== null && $value !== '' && $value !== []
        );
    }

    private static function normalizedText(\DOMElement $element): string
    {
        $text = preg_replace('/\s+/u', ' ', $element->textContent) ?? $element->textContent;

        return trim($text);
    }

    private static function camelCase(string $name): string
    {
        return preg_replace_callback(
            '/-([a-z])/',
            static fn (array $match): string => strtoupper($match[1]),
            $name
        ) ?? $name;
    }

    private static function odfTimeDurationFromDateTime(string $value): ?string
    {
        if (preg_match('/T(\d{2}):(\d{2})(?::(\d{2})(?:[.,]\d+)?)?(?:Z|[+-]\d{2}:?\d{2})?$/', trim($value), $match) !== 1) {
            return null;
        }

        return sprintf(
            'PT%02dH%02dM%02dS',
            (int) $match[1],
            (int) $match[2],
            isset($match[3]) && $match[3] !== '' ? (int) $match[3] : 0
        );
    }

    private static function kebabCase(string $name): string
    {
        $name = preg_replace('/(?<!^)[A-Z]/', '-$0', $name) ?? $name;

        return strtolower($name);
    }

    private function uniqueHeadingAnchor(string $text): string
    {
        $base = self::headingAnchorBase($text);

        return $this->uniqueHeadingAnchorFromBase($base);
    }

    private function uniqueHeadingAnchorFromBase(string $base): string
    {
        $base = $base === '' ? 'section' : $base;
        $seen = $this->headingAnchorUses[$base] ?? 0;
        $this->headingAnchorUses[$base] = $seen + 1;

        return $seen === 0 ? $base : $base . '-' . $seen;
    }

    private static function headingAnchorBase(string $text): string
    {
        $id = strtolower(trim($text));
        $id = preg_replace('/[^\pL\pN]+/u', '-', $id) ?? '';
        $id = trim($id, '-');

        return $id === '' ? 'section' : $id;
    }

    private static function slug(string $value): string
    {
        $slug = strtolower(preg_replace('/[^A-Za-z0-9]+/', '-', trim($value)) ?? '');
        $slug = trim($slug, '-');

        return $slug === '' ? 'odf-section' : $slug;
    }

    private static function bookmarkId(string $name): string
    {
        $id = strtolower(preg_replace('/[^A-Za-z0-9]+/', '-', trim($name)) ?? '');
        $id = trim($id, '-');

        return $id === '' ? 'odf-bookmark' : $id;
    }

    private static function referenceId(string $name): string
    {
        $id = strtolower(preg_replace('/[^A-Za-z0-9]+/', '-', trim($name)) ?? '');
        $id = trim($id, '-');

        return $id === '' ? 'odf-reference' : $id;
    }
}
