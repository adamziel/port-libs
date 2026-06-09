<?php

declare(strict_types=1);

namespace PortLibs\Pandoc;

final class DocxReader
{
    private const STYLE_DOC_DEFAULTS_KEY = "\0docDefaults";

    /**
     * WordprocessingML conditional table-style region names from w:tblStylePr.
     *
     * @var array<string, string>
     */
    private const TABLE_STYLE_CONDITIONAL_REGION_SUFFIXES = [
        'wholeTable' => 'whole-table',
        'firstRow' => 'first-row',
        'lastRow' => 'last-row',
        'firstCol' => 'first-col',
        'lastCol' => 'last-col',
        'band1Vert' => 'band-1-vert',
        'band2Vert' => 'band-2-vert',
        'band1Horz' => 'band-1-horz',
        'band2Horz' => 'band-2-horz',
        'neCell' => 'ne-cell',
        'nwCell' => 'nw-cell',
        'seCell' => 'se-cell',
        'swCell' => 'sw-cell',
    ];

    public const WORDPROCESSINGML_NS = 'http://schemas.openxmlformats.org/wordprocessingml/2006/main';
    public const WORDPROCESSINGML_2010_NS = 'http://schemas.microsoft.com/office/word/2010/wordml';
    public const WORDPROCESSINGML_2012_NS = 'http://schemas.microsoft.com/office/word/2012/wordml';
    public const DRAWINGML_MAIN_NS = 'http://schemas.openxmlformats.org/drawingml/2006/main';
    public const DRAWINGML_PICTURE_NS = 'http://schemas.openxmlformats.org/drawingml/2006/picture';
    public const DRAWINGML_CHART_NS = 'http://schemas.openxmlformats.org/drawingml/2006/chart';
    public const DRAWINGML_DIAGRAM_NS = 'http://schemas.openxmlformats.org/drawingml/2006/diagram';
    public const WORDPROCESSING_DRAWING_NS = 'http://schemas.openxmlformats.org/drawingml/2006/wordprocessingDrawing';
    public const WORDPROCESSING_SHAPE_NS = 'http://schemas.microsoft.com/office/word/2010/wordprocessingShape';
    public const WORDPROCESSING_GROUP_NS = 'http://schemas.microsoft.com/office/word/2010/wordprocessingGroup';
    public const OFFICE_RELATIONSHIPS_NS = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships';
    public const OFFICE_MATH_NS = 'http://schemas.openxmlformats.org/officeDocument/2006/math';
    public const MARKUP_COMPATIBILITY_NS = 'http://schemas.openxmlformats.org/markup-compatibility/2006';
    public const VML_NS = 'urn:schemas-microsoft-com:vml';
    public const OFFICE_VML_NS = 'urn:schemas-microsoft-com:office:office';
    public const CORE_PROPERTIES_NS = 'http://schemas.openxmlformats.org/package/2006/metadata/core-properties';
    public const EXTENDED_PROPERTIES_NS = 'http://schemas.openxmlformats.org/officeDocument/2006/extended-properties';
    public const CUSTOM_PROPERTIES_NS = 'http://schemas.openxmlformats.org/officeDocument/2006/custom-properties';
    public const DOC_PROPS_VT_NS = 'http://schemas.openxmlformats.org/officeDocument/2006/docPropsVTypes';
    public const DC_NS = 'http://purl.org/dc/elements/1.1/';
    public const DCTERMS_NS = 'http://purl.org/dc/terms/';

    public const REL_TYPE_HYPERLINK = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships/hyperlink';
    public const REL_TYPE_IMAGE = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships/image';
    public const REL_TYPE_FOOTNOTES = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships/footnotes';
    public const REL_TYPE_ENDNOTES = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships/endnotes';
    public const REL_TYPE_COMMENTS = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships/comments';
    public const REL_TYPE_COMMENTS_EXTENDED = 'http://schemas.microsoft.com/office/2011/relationships/commentsExtended';
    public const REL_TYPE_STYLES = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles';
    public const REL_TYPE_NUMBERING = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships/numbering';
    public const REL_TYPE_SETTINGS = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships/settings';
    public const REL_TYPE_THEME = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships/theme';
    public const REL_TYPE_ATTACHED_TEMPLATE = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships/attachedTemplate';
    public const REL_TYPE_GLOSSARY_DOCUMENT = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships/glossaryDocument';
    public const REL_TYPE_CORE_PROPERTIES = 'http://schemas.openxmlformats.org/package/2006/relationships/metadata/core-properties';
    public const REL_TYPE_EXTENDED_PROPERTIES = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships/extended-properties';
    public const REL_TYPE_CUSTOM_PROPERTIES = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships/custom-properties';
    public const REL_TYPE_ALTERNATIVE_FORMAT_IMPORT = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships/aFChunk';
    public const REL_TYPE_CHART = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships/chart';
    public const REL_TYPE_CHART_STYLE = 'http://schemas.microsoft.com/office/2011/relationships/chartStyle';
    public const REL_TYPE_CHART_COLOR_STYLE = 'http://schemas.microsoft.com/office/2011/relationships/chartColorStyle';
    public const REL_TYPE_DIAGRAM_DATA = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships/diagramData';
    public const REL_TYPE_DIAGRAM_LAYOUT = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships/diagramLayout';
    public const REL_TYPE_DIAGRAM_QUICK_STYLE = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships/diagramQuickStyle';
    public const REL_TYPE_DIAGRAM_COLORS = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships/diagramColors';
    public const REL_TYPE_OLE_OBJECT = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships/oleObject';
    public const REL_TYPE_EMBEDDED_PACKAGE = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships/package';
    public const REL_TYPE_SUBDOCUMENT = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships/subDocument';
    public const REL_TYPE_CUSTOM_XML = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships/customXml';
    public const REL_TYPE_CUSTOM_XML_PROPS = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships/customXmlProps';
    public const CUSTOM_XML_DATASTORE_NS = 'http://schemas.openxmlformats.org/officeDocument/2006/customXml';

    /**
     * Bounded subset of Pandoc's DOCX symbol font table for common review
     * markers. Keys are post-F000-normalized font codepoints.
     *
     * @var array<string, array<int, int>>
     */
    private const DOCX_SYMBOL_FONT_MAP = [
        'symbol' => [
            0x61 => 0x03b1,
            0x62 => 0x03b2,
            0xb7 => 0x2022,
        ],
        'wingdings' => [
            0x9f => 0x2022,
        ],
        'wingdings-2' => [
            0x50 => 0x2713,
            0x52 => 0x2611,
            0x53 => 0x2612,
        ],
        'wingdings-3' => [
            0x66 => 0x2190,
            0x67 => 0x2192,
            0x68 => 0x2191,
            0x69 => 0x2193,
        ],
    ];

    /**
     * @var array<string, array{next:int, policy:array{numberFormat:string, numberStart:int, numberRestart:string}}>
     */
    private array $noteReferenceState = [];

    /**
     * @var array<string, array<string, mixed>>
     */
    private array $currentStyles = [];

    /**
     * @var array<string, mixed>|null
     */
    private ?array $currentParagraphRunProperties = null;

    /**
     * @var array<string, mixed>|null
     */
    private ?array $currentTableConditionalRunProperties = null;

    /**
     * @var array<string, mixed>|null
     */
    private ?array $currentDefaultRunProperties = null;

    /**
     * @var array{classes:list<string>, attributes:array<string, string>}|null
     */
    private ?array $currentDefaultParagraphMetadata = null;

    /**
     * @var array{classes:list<string>, attributes:array<string, string>}|null
     */
    private ?array $currentTableConditionalParagraphMetadata = null;

    /**
     * @var array<string, string>
     */
    private array $currentThemeFonts = [];

    /**
     * @var array<string, string>
     */
    private array $currentThemeColors = [];

    /**
     * @var array<string, array<string, mixed>>
     */
    private array $currentCustomXmlPartsByStoreItemId = [];

    /**
     * @var list<array<string, mixed>>
     */
    private array $currentCustomXmlStoreItems = [];

    /**
     * @return array{document:AstNode, metadata:array<string, mixed>, documentPart:string, relationships:list<array{id:string, type:string, target:string, contentType:?string, external:bool}>, importReport:array<string, mixed>}
     */
    public function readPackage(ZipPackage $package): array
    {
        $this->noteReferenceState = $this->newNoteReferenceState([]);
        $this->currentCustomXmlPartsByStoreItemId = [];
        $this->currentCustomXmlStoreItems = [];
        $graph = OpcRelationshipGraph::fromPackage($package);
        $documentPart = $graph->firstTargetOfType(OpcRelationshipGraph::OFFICE_DOCUMENT_RELATIONSHIP_TYPE);
        if ($documentPart === null) {
            throw new \RuntimeException('DOCX package does not contain an officeDocument relationship');
        }

        $documentPart = OpcPackagePath::stripQueryAndFragment($documentPart);
        if (!$package->has($documentPart)) {
            throw new \RuntimeException('DOCX package is missing document part: ' . $documentPart);
        }

        $documentRelationships = $graph->relationshipsForSource($documentPart);
        $relationshipPreflight = $graph->preflightTargetsForSource($documentPart);
        $reachableRelationships = $graph->reachableTargetsForSource($documentPart);
        $settings = $this->readSettings($package, $graph, $documentPart);
        $theme = $this->readTheme($package, $graph, $documentPart);
        $themeFonts = $theme['fonts'] ?? [];
        $this->currentThemeFonts = is_array($themeFonts) ? $themeFonts : [];
        $themeColors = $theme['colors']['byName'] ?? [];
        $this->currentThemeColors = is_array($themeColors) ? array_filter(
            $themeColors,
            static fn (mixed $value): bool => is_string($value) && $value !== '',
        ) : [];
        $referencedNotes = $this->loadReferencedNotes($package, $graph, $documentPart);
        $styles = $this->loadStyles($package, $graph, $documentPart);
        $numbering = $this->loadNumbering($package, $graph, $documentPart);
        $documentXml = $package->read($documentPart);
        $specialNotes = $this->specialNoteImportReport($package, $graph, $documentPart);
        $customXmlStore = $this->readCustomXmlStore($package, $graph);
        $this->currentCustomXmlPartsByStoreItemId = $customXmlStore['lookupByStoreItemID'] ?? [];
        $this->currentCustomXmlStoreItems = $customXmlStore['items'] ?? [];
        $document = $this->parseDocumentXml(
            $documentXml,
            $documentPart,
            $package,
            $documentRelationships,
            $referencedNotes,
            $styles,
            $numbering,
        );
        $glossary = $this->readGlossaryDocument($package, $graph, $documentPart, $referencedNotes, $styles, $numbering);
        $metadata = $this->readCoreProperties($package, $graph);
        $documentBackground = $document->attr('docxBackground', []);
        if (is_array($documentBackground) && $documentBackground !== []) {
            $metadata['docxBackground'] = $documentBackground;
        }
        $extendedProperties = $this->readExtendedProperties($package, $graph);
        $customProperties = $this->readCustomProperties($package, $graph);
        $docProperties = [];
        if ($extendedProperties !== []) {
            $metadata['docxExtendedProperties'] = $extendedProperties;
            $docProperties['extended'] = $extendedProperties;
        }
        if ($customProperties !== []) {
            $metadata['docxCustomProperties'] = $customProperties;
            if (isset($customProperties['byName']) && is_array($customProperties['byName'])) {
                $metadata['customProperties'] = $customProperties['byName'];
            }
            $docProperties['custom'] = $customProperties;
        }
        if ($settings !== []) {
            $metadata['docxSettings'] = $settings;
        }
        if ($theme !== []) {
            $metadata['docxTheme'] = $theme;
        }
        if ($glossary !== []) {
            $metadata['docxGlossary'] = $glossary;
        }
        if ($customXmlStore['count'] > 0) {
            $metadata['docxCustomXmlStore'] = [
                'count' => $customXmlStore['count'],
                'boundStoreItemCount' => $customXmlStore['boundStoreItemCount'],
                'issueCount' => $customXmlStore['issueCount'],
                'propertyIssueCount' => $customXmlStore['propertyIssueCount'],
                'propertyIssueCodes' => $customXmlStore['propertyIssueCodes'],
                'items' => $customXmlStore['items'],
                'byStoreItemID' => $customXmlStore['byStoreItemID'],
            ];
        }

        return [
            'document' => $document,
            'metadata' => $metadata,
            'documentPart' => $documentPart,
            'relationships' => $graph->summarizeTargetsForSource($documentPart),
            'importReport' => $this->importReport(
                $package,
                $documentPart,
                $documentRelationships,
                $relationshipPreflight,
                $reachableRelationships,
                $document,
                $this->revisionImportReport($documentXml),
                $this->alternativeFormatImportReport($documentXml, $package, $documentRelationships),
                $specialNotes,
                $docProperties,
                $settings,
                $theme,
                $glossary,
                $customXmlStore,
            ),
        ];
    }

    /**
     * @param list<array{id:string, type:string, target:string, contentType:?string, external:bool, exists:?bool, relationshipPartTarget:bool, externalTargetKind:?string, externalTargetScheme:?string, externalTargetAllowed:?bool, externalTargetRequiresBaseUri:?bool, externalTargetRewriteBasePart:?string, externalTargetRewriteReason:?string, valid:bool, issues:list<string>}> $relationshipPreflight
     * @param list<array{source:string, depth:int, id:string, type:string, target:string, targetPart:?string, contentType:?string, external:bool, exists:?bool, relationshipPartTarget:bool, externalTargetKind:?string, externalTargetScheme:?string, externalTargetAllowed:?bool, externalTargetRequiresBaseUri:?bool, externalTargetRewriteBasePart:?string, externalTargetRewriteReason:?string, valid:bool, issues:list<string>}> $reachableRelationships
     * @param array{insertionCount:int, deletionCount:int, formattingCount:int, items:list<array{type:string, accepted:bool, id:?string, author:?string, date:?string, text:string}>} $revisions
     * @param array<string, mixed> $alternativeFormats
     * @param array<string, mixed> $specialNotes
     * @param array<string, mixed> $docProperties
     * @param array<string, mixed> $settings
     * @param array<string, mixed> $theme
     * @param array<string, mixed> $glossary
     * @param array<string, mixed> $customXmlStore
     * @return array<string, mixed>
     */
    private function importReport(
        ZipPackage $package,
        string $documentPart,
        ?OpcRelationships $documentRelationships,
        array $relationshipPreflight,
        array $reachableRelationships,
        AstNode $document,
        array $revisions,
        array $alternativeFormats,
        array $specialNotes,
        array $docProperties,
        array $settings,
        array $theme,
        array $glossary,
        array $customXmlStore
    ): array {
        $relationshipIssues = [];
        foreach ($reachableRelationships as $relationship) {
            if ($relationship['valid']) {
                continue;
            }

            $relationshipIssues[] = [
                'source' => $relationship['source'],
                'id' => $relationship['id'],
                'type' => $relationship['type'],
                'target' => $relationship['target'],
                'issues' => $relationship['issues'],
            ];
        }

        $notes = $this->notesImportReport($document);
        if ($specialNotes !== []) {
            $notes['specialNotes'] = $specialNotes;
        }

        return [
            'documentPart' => $documentPart,
            'relationshipsPart' => $documentRelationships instanceof OpcRelationships ? $documentRelationships->relationshipPartName() : null,
            'relationshipCount' => count($relationshipPreflight),
            'reachableRelationshipCount' => count($reachableRelationships),
            'relationshipIssues' => $relationshipIssues,
            'media' => $this->mediaImportReport($package, $reachableRelationships, $document),
            'embeddedObjects' => $this->embeddedObjectImportReport($package, $reachableRelationships, $document),
            'subdocuments' => $this->subdocumentImportReport($document),
            'alternativeFormats' => $alternativeFormats,
            'notes' => $notes,
            'revisions' => $revisions,
            'properties' => $docProperties,
            'settings' => $settings,
            'theme' => $theme,
            'glossary' => $glossary,
            'customXmlStore' => [
                'count' => $customXmlStore['count'] ?? 0,
                'boundStoreItemCount' => $customXmlStore['boundStoreItemCount'] ?? 0,
                'issueCount' => $customXmlStore['issueCount'] ?? 0,
                'propertyIssueCount' => $customXmlStore['propertyIssueCount'] ?? 0,
                'propertyIssueCodes' => $customXmlStore['propertyIssueCodes'] ?? [],
                'items' => $customXmlStore['items'] ?? [],
                'byStoreItemID' => $customXmlStore['byStoreItemID'] ?? [],
            ],
            'background' => $document->attr('docxBackground', []),
            'sections' => [
                'count' => count($document->attr('sectionProperties', [])),
                'items' => $document->attr('sectionProperties', []),
            ],
        ];
    }

    /**
     * @return array{count:int, footnoteCount:int, endnoteCount:int, commentCount:int, missingCount:int, items:list<array{id:?string, sourceType:string, missing:bool, customMarkFollows:bool, referenceNumber:?int, referenceLabel:?string, referenceFormat:?string, referenceStart:?int, referenceRestart:?string, referenceNumberingSkipped:bool, blockCount:int, text:string, author:?string, initials:?string, date:?string, commentParaId:?string, commentParentParaId:?string, commentResolved:?bool, commentsExtendedPart:?string}>}
     */
    private function notesImportReport(AstNode $document): array
    {
        $items = [];
        $this->collectNoteImportReportItems($document, $items);

        return [
            'count' => count($items),
            'footnoteCount' => count(array_filter($items, static fn (array $item): bool => $item['sourceType'] === 'footnote')),
            'endnoteCount' => count(array_filter($items, static fn (array $item): bool => $item['sourceType'] === 'endnote')),
            'commentCount' => count(array_filter($items, static fn (array $item): bool => $item['sourceType'] === 'comment')),
            'missingCount' => count(array_filter($items, static fn (array $item): bool => $item['missing'] === true)),
            'items' => $items,
        ];
    }

    /**
     * @param list<array{id:?string, sourceType:string, missing:bool, customMarkFollows:bool, referenceNumber:?int, referenceLabel:?string, referenceFormat:?string, referenceStart:?int, referenceRestart:?string, referenceNumberingSkipped:bool, blockCount:int, text:string, author:?string, initials:?string, date:?string, commentParaId:?string, commentParentParaId:?string, commentResolved:?bool, commentsExtendedPart:?string}> $items
     */
    private function collectNoteImportReportItems(AstNode $node, array &$items): void
    {
        if ($node->type === 'note') {
            $sourceType = $node->attr('sourceType', 'note');
            $referenceNumber = $node->attr('referenceNumber');
            $referenceStart = $node->attr('referenceStart');
            $commentResolved = $node->attr('commentResolved');
            $items[] = [
                'id' => is_string($node->attr('id')) ? $node->attr('id') : null,
                'sourceType' => is_string($sourceType) && $sourceType !== '' ? $sourceType : 'note',
                'missing' => $node->attr('missing') === true,
                'customMarkFollows' => $node->attr('customMarkFollows') === true,
                'referenceNumber' => is_int($referenceNumber) ? $referenceNumber : null,
                'referenceLabel' => is_string($node->attr('referenceLabel')) ? $node->attr('referenceLabel') : null,
                'referenceFormat' => is_string($node->attr('referenceFormat')) ? $node->attr('referenceFormat') : null,
                'referenceStart' => is_int($referenceStart) ? $referenceStart : null,
                'referenceRestart' => is_string($node->attr('referenceRestart')) ? $node->attr('referenceRestart') : null,
                'referenceNumberingSkipped' => $node->attr('referenceNumberingSkipped') === true,
                'blockCount' => count($node->children),
                'text' => $this->plainBlockText($node->children),
                'author' => is_string($node->attr('author')) ? $node->attr('author') : null,
                'initials' => is_string($node->attr('initials')) ? $node->attr('initials') : null,
                'date' => is_string($node->attr('date')) ? $node->attr('date') : null,
                'commentParaId' => is_string($node->attr('commentParaId')) ? $node->attr('commentParaId') : null,
                'commentParentParaId' => is_string($node->attr('commentParentParaId')) ? $node->attr('commentParentParaId') : null,
                'commentResolved' => is_bool($commentResolved) ? $commentResolved : null,
                'commentsExtendedPart' => is_string($node->attr('commentsExtendedPart')) ? $node->attr('commentsExtendedPart') : null,
            ];
        }

        foreach ($node->children as $child) {
            $this->collectNoteImportReportItems($child, $items);
        }
    }

    /**
     * @param list<array{source:string, depth:int, id:string, type:string, target:string, targetPart:?string, contentType:?string, external:bool, exists:?bool, relationshipPartTarget:bool, externalTargetKind:?string, externalTargetScheme:?string, externalTargetAllowed:?bool, externalTargetRequiresBaseUri:?bool, externalTargetRewriteBasePart:?string, externalTargetRewriteReason:?string, valid:bool, issues:list<string>}> $reachableRelationships
     * @return array{count:int, embeddedCount:int, missingCount:int, items:list<array{source:string, id:string, target:string, targetPart:?string, contentType:?string, external:bool, exists:?bool, bytes:?int, usedCount:int, altTexts:list<string>, titles:list<string>, issues:list<string>}>}
     */
    private function mediaImportReport(ZipPackage $package, array $reachableRelationships, AstNode $document): array
    {
        $imageNodesByTarget = $this->imageNodesByMediaTarget($document);
        $items = [];
        foreach ($reachableRelationships as $relationship) {
            if ($relationship['type'] !== self::REL_TYPE_IMAGE) {
                continue;
            }

            $targetPart = $relationship['targetPart'];
            $imageTarget = $targetPart ?? $relationship['target'];
            $imageNodes = $imageNodesByTarget[$imageTarget] ?? [];
            $items[] = [
                'source' => $relationship['source'],
                'id' => $relationship['id'],
                'target' => $relationship['target'],
                'targetPart' => $targetPart,
                'contentType' => $relationship['contentType'],
                'external' => $relationship['external'],
                'exists' => $relationship['exists'],
                'bytes' => $targetPart !== null && $relationship['exists'] === true ? strlen($package->read($targetPart)) : null,
                'usedCount' => count($imageNodes),
                'altTexts' => $this->nonEmptyImageAttrValues($imageNodes, 'alt'),
                'titles' => $this->nonEmptyImageAttrValues($imageNodes, 'title'),
                'issues' => $relationship['issues'],
            ];
        }

        return [
            'count' => count($items),
            'embeddedCount' => count(array_filter(
                $items,
                static fn (array $item): bool => $item['external'] === false && $item['exists'] === true,
            )),
            'missingCount' => count(array_filter(
                $items,
                static fn (array $item): bool => $item['external'] === false && $item['exists'] === false,
            )),
            'items' => $items,
        ];
    }

    /**
     * @param list<array{source:string, depth:int, id:string, type:string, target:string, targetPart:?string, contentType:?string, external:bool, exists:?bool, relationshipPartTarget:bool, externalTargetKind:?string, externalTargetScheme:?string, externalTargetAllowed:?bool, externalTargetRequiresBaseUri:?bool, externalTargetRewriteBasePart:?string, externalTargetRewriteReason:?string, valid:bool, issues:list<string>}> $reachableRelationships
     * @return array{count:int, oleObjectCount:int, packageCount:int, embeddedCount:int, missingCount:int, items:list<array{source:string, id:string, type:string, kind:string, target:string, targetPart:?string, contentType:?string, external:bool, exists:?bool, bytes:?int, usedCount:int, descriptions:list<string>, issues:list<string>}>}
     */
    private function embeddedObjectImportReport(ZipPackage $package, array $reachableRelationships, AstNode $document): array
    {
        $objectNodesByRelationshipId = $this->embeddedObjectNodesByRelationshipId($document);
        $items = [];
        foreach ($reachableRelationships as $relationship) {
            if (!$this->isEmbeddedObjectRelationshipType($relationship['type'])) {
                continue;
            }

            $targetPart = $relationship['targetPart'];
            $objectNodes = $objectNodesByRelationshipId[$relationship['id']] ?? [];
            $items[] = [
                'source' => $relationship['source'],
                'id' => $relationship['id'],
                'type' => $relationship['type'],
                'kind' => $this->embeddedObjectKindFromRelationshipType($relationship['type']),
                'target' => $relationship['target'],
                'targetPart' => $targetPart,
                'contentType' => $relationship['contentType'],
                'external' => $relationship['external'],
                'exists' => $relationship['exists'],
                'bytes' => $targetPart !== null && $relationship['exists'] === true ? strlen($package->read($targetPart)) : null,
                'usedCount' => count($objectNodes),
                'descriptions' => $this->embeddedObjectDescriptions($objectNodes),
                'issues' => $relationship['issues'],
            ];
        }

        return [
            'count' => count($items),
            'oleObjectCount' => count(array_filter(
                $items,
                static fn (array $item): bool => $item['kind'] === 'ole-object',
            )),
            'packageCount' => count(array_filter(
                $items,
                static fn (array $item): bool => $item['kind'] === 'package',
            )),
            'embeddedCount' => count(array_filter(
                $items,
                static fn (array $item): bool => $item['external'] === false && $item['exists'] === true,
            )),
            'missingCount' => count(array_filter(
                $items,
                static fn (array $item): bool => $item['external'] === false && $item['exists'] === false,
            )),
            'items' => $items,
        ];
    }

    /**
     * @return array<string, list<AstNode>>
     */
    private function embeddedObjectNodesByRelationshipId(AstNode $node): array
    {
        $objects = [];
        $this->collectEmbeddedObjectNodesByRelationshipId($node, $objects);

        return $objects;
    }

    /**
     * @param array<string, list<AstNode>> $objects
     */
    private function collectEmbeddedObjectNodesByRelationshipId(AstNode $node, array &$objects): void
    {
        if ($node->type === 'span') {
            $classes = $node->attr('classes', []);
            $attributes = $node->attr('attributes', []);
            if (
                is_array($classes)
                && in_array('docx-embedded-object', $classes, true)
                && is_array($attributes)
                && isset($attributes['data-docx-relationship-id'])
                && is_string($attributes['data-docx-relationship-id'])
                && $attributes['data-docx-relationship-id'] !== ''
            ) {
                $relationshipId = $attributes['data-docx-relationship-id'];
                $objects[$relationshipId] ??= [];
                $objects[$relationshipId][] = $node;
            }
        }

        foreach ($node->children as $child) {
            $this->collectEmbeddedObjectNodesByRelationshipId($child, $objects);
        }
    }

    /**
     * @param list<AstNode> $objectNodes
     * @return list<string>
     */
    private function embeddedObjectDescriptions(array $objectNodes): array
    {
        $descriptions = [];
        foreach ($objectNodes as $objectNode) {
            $description = $this->plainInlineText($objectNode->children);
            if ($description !== '') {
                $descriptions[$description] = true;
            }
        }

        return array_keys($descriptions);
    }

    /**
     * @return array{count:int, externalCount:int, internalCount:int, missingCount:int, issueCount:int, items:list<array{id:?string, type:?string, target:?string, targetPart:?string, contentType:?string, external:?bool, exists:?bool, usedCount:int, descriptions:list<string>, issues:list<string>}>}
     */
    private function subdocumentImportReport(AstNode $document): array
    {
        $itemsByKey = [];
        foreach ($this->subdocumentNodesByReportKey($document) as $key => $nodes) {
            $first = $nodes[0];
            $attributes = $first->attr('attributes', []);
            $issues = [];
            foreach ($nodes as $node) {
                $issues = array_merge($issues, $this->subdocumentNodeIssues($node));
            }
            $issues = array_values(array_unique($issues));

            $external = $this->booleanDataAttribute($attributes, 'data-docx-external');
            $exists = $this->booleanDataAttribute($attributes, 'data-docx-exists');
            $itemsByKey[$key] = [
                'id' => isset($attributes['data-docx-relationship-id']) && is_string($attributes['data-docx-relationship-id']) ? $attributes['data-docx-relationship-id'] : null,
                'type' => isset($attributes['data-docx-relationship-type']) && is_string($attributes['data-docx-relationship-type']) ? $attributes['data-docx-relationship-type'] : null,
                'target' => isset($attributes['data-docx-target']) && is_string($attributes['data-docx-target']) ? $attributes['data-docx-target'] : null,
                'targetPart' => isset($attributes['data-docx-target-part']) && is_string($attributes['data-docx-target-part']) ? $attributes['data-docx-target-part'] : null,
                'contentType' => isset($attributes['data-docx-content-type']) && is_string($attributes['data-docx-content-type']) ? $attributes['data-docx-content-type'] : null,
                'external' => $external,
                'exists' => $exists,
                'usedCount' => count($nodes),
                'descriptions' => $this->subdocumentDescriptions($nodes),
                'issues' => $issues,
            ];
        }

        $items = array_values($itemsByKey);

        return [
            'count' => count($items),
            'externalCount' => count(array_filter($items, static fn (array $item): bool => $item['external'] === true)),
            'internalCount' => count(array_filter($items, static fn (array $item): bool => $item['external'] === false)),
            'missingCount' => count(array_filter(
                $items,
                static fn (array $item): bool => $item['id'] === null
                    || in_array('missing-relationships', $item['issues'], true)
                    || in_array('missing-relationship', $item['issues'], true)
                    || $item['exists'] === false,
            )),
            'issueCount' => count(array_filter($items, static fn (array $item): bool => $item['issues'] !== [])),
            'items' => $items,
        ];
    }

    /**
     * @return array<string, list<AstNode>>
     */
    private function subdocumentNodesByReportKey(AstNode $node): array
    {
        $nodes = [];
        $this->collectSubdocumentNodesByReportKey($node, $nodes);

        return $nodes;
    }

    /**
     * @param array<string, list<AstNode>> $nodes
     */
    private function collectSubdocumentNodesByReportKey(AstNode $node, array &$nodes): void
    {
        if ($node->type === 'span') {
            $classes = $node->attr('classes', []);
            $attributes = $node->attr('attributes', []);
            if (
                is_array($classes)
                && in_array('docx-subdocument', $classes, true)
                && is_array($attributes)
            ) {
                $relationshipId = $attributes['data-docx-relationship-id'] ?? null;
                $key = is_string($relationshipId) && $relationshipId !== '' ? 'id:' . $relationshipId : 'node:' . count($nodes);
                $nodes[$key] ??= [];
                $nodes[$key][] = $node;
            }
        }

        foreach ($node->children as $child) {
            $this->collectSubdocumentNodesByReportKey($child, $nodes);
        }
    }

    /**
     * @param list<AstNode> $subdocumentNodes
     * @return list<string>
     */
    private function subdocumentDescriptions(array $subdocumentNodes): array
    {
        $descriptions = [];
        foreach ($subdocumentNodes as $subdocumentNode) {
            $description = $this->plainInlineText($subdocumentNode->children);
            if ($description !== '') {
                $descriptions[$description] = true;
            }
        }

        return array_keys($descriptions);
    }

    /**
     * @return list<string>
     */
    private function subdocumentNodeIssues(AstNode $node): array
    {
        $attributes = $node->attr('attributes', []);
        if (!is_array($attributes) || !isset($attributes['data-docx-issues']) || !is_string($attributes['data-docx-issues'])) {
            return [];
        }

        return array_values(array_filter(explode(' ', $attributes['data-docx-issues']), static fn (string $issue): bool => $issue !== ''));
    }

    /**
     * @param mixed $attributes
     */
    private function booleanDataAttribute($attributes, string $name): ?bool
    {
        if (!is_array($attributes) || !isset($attributes[$name]) || !is_string($attributes[$name])) {
            return null;
        }

        return match ($attributes[$name]) {
            'true' => true,
            'false' => false,
            default => null,
        };
    }

    /**
     * @return array{count:int, importedCount:int, missingCount:int, externalCount:int, unsupportedCount:int, items:list<array<string, mixed>>}
     */
    private function alternativeFormatImportReport(string $xml, ZipPackage $package, ?OpcRelationships $relationships): array
    {
        $dom = self::loadXml($xml, 'DOCX document XML alternative formats');
        $items = [];

        foreach ($dom->getElementsByTagNameNS(self::WORDPROCESSINGML_NS, 'altChunk') as $chunk) {
            if (!$chunk instanceof \DOMElement) {
                continue;
            }

            $items[] = $this->alternativeFormatItem($chunk, $package, $relationships);
        }

        return [
            'count' => count($items),
            'importedCount' => count(array_filter($items, static fn (array $item): bool => $item['imported'] === true)),
            'missingCount' => count(array_filter($items, static fn (array $item): bool => in_array('missing-in-package', $item['issues'], true))),
            'externalCount' => count(array_filter($items, static fn (array $item): bool => in_array('external-altchunk', $item['issues'], true))),
            'unsupportedCount' => count(array_filter(
                $items,
                static fn (array $item): bool => in_array('unsupported-content-type', $item['issues'], true) || in_array('missing-content-type', $item['issues'], true),
            )),
            'items' => $items,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function alternativeFormatItem(\DOMElement $chunk, ZipPackage $package, ?OpcRelationships $relationships): array
    {
        $id = $this->relationshipAttr($chunk, 'id');
        $item = [
            'id' => $id,
            'relationshipType' => null,
            'target' => null,
            'targetPart' => null,
            'contentType' => null,
            'external' => null,
            'exists' => null,
            'bytes' => null,
            'imported' => false,
            'text' => null,
            'html' => null,
            'encoding' => null,
            'bom' => null,
            'repairs' => null,
            'lineEndings' => null,
            'paragraphCount' => null,
            'issues' => [],
        ];

        if ($id === null || $id === '') {
            $item['issues'][] = 'missing-relationship-id';
            return $item;
        }

        if (!$relationships instanceof OpcRelationships) {
            $item['issues'][] = 'missing-relationships';
            return $item;
        }

        $relationship = $relationships->byId($id);
        if (!$relationship instanceof OpcRelationship) {
            $item['issues'][] = 'unknown-relationship';
            return $item;
        }

        $item['relationshipType'] = $relationship->type;
        $item['external'] = $relationship->isExternal();
        if ($relationship->type !== self::REL_TYPE_ALTERNATIVE_FORMAT_IMPORT) {
            $item['issues'][] = 'unexpected-relationship-type';
            return $item;
        }

        if ($relationship->isExternal()) {
            $item['target'] = $relationship->target;
            $item['issues'][] = 'external-altchunk';
            return $item;
        }

        try {
            $target = $relationships->resolveTarget($relationship);
        } catch (\InvalidArgumentException) {
            $item['issues'][] = 'invalid-target';
            return $item;
        }

        $targetPart = OpcPackagePath::stripQueryAndFragment($target);
        $item['target'] = $target;
        $item['targetPart'] = $targetPart;
        $item['exists'] = $package->has($targetPart);
        $item['contentType'] = $this->contentTypeForPackagePart($package, $targetPart);

        if ($item['exists'] !== true) {
            $item['issues'][] = 'missing-in-package';
            return $item;
        }

        $bytes = $package->read($targetPart);
        $item['bytes'] = strlen($bytes);

        if ($item['contentType'] === null) {
            $item['issues'][] = 'missing-content-type';
            return $item;
        }

        if (!$this->isSupportedAlternativeFormatContentType($item['contentType'])) {
            $item['issues'][] = 'unsupported-content-type';
            return $item;
        }

        if ($this->isPlainTextAlternativeFormatContentType($item['contentType'])) {
            $decoded = UnicodeText::decodeBytes($bytes, $this->charsetForContentType($item['contentType']));
            $blocks = $this->plainTextAlternativeFormatBlocks($decoded['text']);
            $item['text'] = $decoded['text'];
            $item['encoding'] = $decoded['encoding'];
            $item['bom'] = $decoded['bom'];
            $item['repairs'] = $decoded['repairs'];
            $item['lineEndings'] = $decoded['lineEndings'];
            $item['paragraphCount'] = count($blocks);
            $item['imported'] = $blocks !== [];
            if ($blocks === []) {
                $item['issues'][] = 'empty-text';
            }

            return $item;
        }

        try {
            $dom = XmlHtmlDom::loadHtmlFragment($bytes, 'DOCX altChunk HTML ' . $targetPart);
        } catch (\InvalidArgumentException) {
            $item['issues'][] = 'invalid-html';
            return $item;
        }

        $root = XmlHtmlDom::fragmentRoot($dom);
        $item['html'] = XmlHtmlDom::serializeHtmlFragment($dom);
        $item['text'] = $root instanceof \DOMElement ? $this->plainDomText($root) : '';
        $item['imported'] = true;

        return $item;
    }

    /**
     * @return list<AstNode>
     */
    private function alternativeFormatBlocks(\DOMElement $chunk, ZipPackage $package, ?OpcRelationships $relationships): array
    {
        $item = $this->alternativeFormatItem($chunk, $package, $relationships);
        if ($item['imported'] !== true) {
            return [];
        }

        if (is_string($item['text']) && $this->isPlainTextAlternativeFormatContentType((string) $item['contentType'])) {
            return $this->plainTextAlternativeFormatBlocks($item['text'], [
                'sourceFormat' => 'docx-altChunk',
                'id' => $item['id'],
                'target' => $item['target'],
                'targetPart' => $item['targetPart'],
                'contentType' => $item['contentType'],
                'bytes' => $item['bytes'],
                'encoding' => $item['encoding'],
                'bom' => $item['bom'],
                'repairs' => $item['repairs'],
            ]);
        }

        if (!is_string($item['html']) || $item['html'] === '') {
            return [];
        }

        return [new AstNode('raw_html', [
            'format' => 'html',
            'html' => $item['html'],
            'sourceFormat' => 'docx-altChunk',
            'id' => $item['id'],
            'target' => $item['target'],
            'targetPart' => $item['targetPart'],
            'contentType' => $item['contentType'],
            'bytes' => $item['bytes'],
        ])];
    }

    private function contentTypeForPackagePart(ZipPackage $package, string $targetPart): ?string
    {
        if (!$package->has('[Content_Types].xml')) {
            return null;
        }

        return OpcContentTypes::fromXml($package->read('[Content_Types].xml'))->contentTypeForPart($targetPart);
    }

    private function isSupportedAlternativeFormatContentType(string $contentType): bool
    {
        $contentType = $this->alternativeFormatBaseContentType($contentType);

        return in_array($contentType, ['text/html', 'application/xhtml+xml', 'text/plain'], true);
    }

    private function isPlainTextAlternativeFormatContentType(string $contentType): bool
    {
        return $this->alternativeFormatBaseContentType($contentType) === 'text/plain';
    }

    private function alternativeFormatBaseContentType(string $contentType): string
    {
        return strtolower(trim(explode(';', $contentType, 2)[0]));
    }

    private function charsetForContentType(string $contentType): ?string
    {
        if (!preg_match('/;\s*charset=(?:"([^"]+)"|([^;]+))/i', $contentType, $matches)) {
            return null;
        }

        $quoted = (string) ($matches[1] ?? '');
        $unquoted = (string) ($matches[2] ?? '');
        $charset = trim($quoted !== '' ? $quoted : $unquoted);

        return $charset === '' ? null : $charset;
    }

    /**
     * @param array<string, mixed> $attrs
     * @return list<AstNode>
     */
    private function plainTextAlternativeFormatBlocks(string $text, array $attrs = []): array
    {
        $text = trim($text);
        if ($text === '') {
            return [];
        }

        $blocks = [];
        foreach (preg_split('/\n{2,}/u', $text) ?: [] as $paragraph) {
            $paragraph = trim($paragraph);
            if ($paragraph === '') {
                continue;
            }

            $inlines = [];
            foreach (preg_split('/\n/u', $paragraph) ?: [] as $index => $line) {
                if ($index > 0) {
                    $inlines[] = new AstNode('linebreak');
                }
                if ($line !== '') {
                    $inlines[] = new AstNode('text', ['text' => $line]);
                }
            }

            if ($inlines !== []) {
                $blocks[] = new AstNode('paragraph', $attrs, $this->coalesceTextNodes($inlines));
            }
        }

        return $blocks;
    }

    private function plainDomText(\DOMNode $node): string
    {
        if ($node instanceof \DOMText) {
            return trim(preg_replace('/[ \t\r\n\f]+/u', ' ', $node->nodeValue ?? '') ?? ($node->nodeValue ?? ''));
        }

        $parts = [];
        foreach ($node->childNodes as $child) {
            $text = $this->plainDomText($child);
            if ($text !== '') {
                $parts[] = $text;
            }
        }

        return implode(' ', $parts);
    }

    /**
     * @return array<string, list<AstNode>>
     */
    private function imageNodesByMediaTarget(AstNode $node): array
    {
        $images = [];
        $this->collectImageNodesByMediaTarget($node, $images);

        return $images;
    }

    /**
     * @param array<string, list<AstNode>> $images
     */
    private function collectImageNodesByMediaTarget(AstNode $node, array &$images): void
    {
        if ($node->type === 'image') {
            $target = null;
            $sourcePart = $node->attr('sourcePart');
            if (is_string($sourcePart) && $sourcePart !== '') {
                $target = $sourcePart;
            } elseif ($node->attr('external') === true) {
                $url = $node->attr('url');
                if (is_string($url) && $url !== '') {
                    $target = $url;
                }
            }

            if ($target !== null) {
                $images[$target] ??= [];
                $images[$target][] = $node;
            }
        }

        foreach ($node->children as $child) {
            $this->collectImageNodesByMediaTarget($child, $images);
        }
    }

    /**
     * @param list<AstNode> $imageNodes
     * @return list<string>
     */
    private function nonEmptyImageAttrValues(array $imageNodes, string $attr): array
    {
        $values = [];
        foreach ($imageNodes as $imageNode) {
            $value = $imageNode->attr($attr);
            if (is_string($value) && $value !== '') {
                $values[$value] = true;
            }
        }

        return array_keys($values);
    }

    public function readDocument(ZipPackage $package): AstNode
    {
        return $this->readPackage($package)['document'];
    }

    /**
     * @param array<string, AstNode> $referencedNotes
     * @param array<string, array{name:?string, basedOn:?string, headingLevel:?int, numPr:?array{numId:?string, level:?int}}> $styles
     * @param array<string, array<int, array{ordered:bool, style:string, delimiter:string, start:int, format:string}>> $numbering
     */
    private function parseDocumentXml(
        string $xml,
        string $documentPart,
        ZipPackage $package,
        ?OpcRelationships $relationships,
        array $referencedNotes,
        array $styles,
        array $numbering
    ): AstNode {
        $dom = self::loadXml($xml, 'DOCX document XML');
        $root = $dom->documentElement;
        if (!$root instanceof \DOMElement || !$this->isWordElement($root, 'document')) {
            throw new \InvalidArgumentException('DOCX document XML must use a w:document root');
        }

        $body = $this->firstChildElement($root, self::WORDPROCESSINGML_NS, 'body');
        if (!$body instanceof \DOMElement) {
            throw new \InvalidArgumentException('DOCX document XML is missing w:body');
        }

        $previousStyles = $this->currentStyles;
        $previousParagraphRunProperties = $this->currentParagraphRunProperties;
        $previousDefaultRunProperties = $this->currentDefaultRunProperties;
        $previousDefaultParagraphMetadata = $this->currentDefaultParagraphMetadata;
        $styleDefaults = $styles[self::STYLE_DOC_DEFAULTS_KEY] ?? [];
        $this->currentStyles = $styles;
        $this->currentParagraphRunProperties = null;
        $this->currentDefaultRunProperties = isset($styleDefaults['runProperties']) && is_array($styleDefaults['runProperties'])
            ? $styleDefaults['runProperties']
            : null;
        $this->currentDefaultParagraphMetadata = isset($styleDefaults['paragraphMetadata']) && is_array($styleDefaults['paragraphMetadata'])
            ? $styleDefaults['paragraphMetadata']
            : null;
        try {
            $this->noteReferenceState = $this->newNoteReferenceState([]);
            $blocks = $this->bodyChildren(
                $body,
                $package,
                $relationships,
                $referencedNotes,
                $styles,
                $numbering
            );
            $attrs = [
                'sourceFormat' => 'docx',
                'documentPart' => $documentPart,
            ];
            $background = $this->documentBackgroundAttrs($root, $package, $relationships);
            if ($background !== []) {
                $attrs['docxBackground'] = $background;
            }
            $documentNoteReferenceState = $this->noteReferenceState;
            $sectionProperties = $this->sectionProperties($body, $package, $relationships, $referencedNotes, $styles, $numbering);
            $this->noteReferenceState = $documentNoteReferenceState;
            if ($sectionProperties !== []) {
                $attrs['sectionProperties'] = $sectionProperties;
            }

            return new AstNode('document', $attrs, $blocks);
        } finally {
            $this->currentStyles = $previousStyles;
            $this->currentParagraphRunProperties = $previousParagraphRunProperties;
            $this->currentDefaultRunProperties = $previousDefaultRunProperties;
            $this->currentDefaultParagraphMetadata = $previousDefaultParagraphMetadata;
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function documentBackgroundAttrs(\DOMElement $document, ZipPackage $package, ?OpcRelationships $relationships): array
    {
        $background = $this->firstChildElement($document, self::WORDPROCESSINGML_NS, 'background');
        if (!$background instanceof \DOMElement) {
            return [];
        }

        $attrs = [];
        foreach ([
            'color' => 'color',
            'themeColor' => 'themeColor',
            'themeTint' => 'themeTint',
            'themeShade' => 'themeShade',
        ] as $source => $target) {
            $value = trim((string) ($this->wordAttr($background, $source) ?? ''));
            if ($value !== '') {
                $attrs[$target] = $value;
            }
        }

        if (isset($attrs['color']) && preg_match('/^[0-9A-Fa-f]{6}$/D', (string) $attrs['color']) === 1) {
            $attrs['cssBackgroundColor'] = '#' . strtoupper((string) $attrs['color']);
        }

        $vmlBackground = $this->firstChildElement($background, self::VML_NS, 'background');
        $fillContainer = $vmlBackground instanceof \DOMElement ? $vmlBackground : $background;
        if ($vmlBackground instanceof \DOMElement) {
            foreach ([
                'id' => 'vmlId',
                'style' => 'vmlStyle',
            ] as $source => $target) {
                $value = trim($vmlBackground->getAttribute($source));
                if ($value !== '') {
                    $attrs[$target] = $value;
                }
            }
        }

        $fill = $this->firstDescendantElement($fillContainer, self::VML_NS, 'fill');
        if ($fill instanceof \DOMElement) {
            $fillAttrs = $this->documentBackgroundFillAttrs($fill);
            if ($fillAttrs !== []) {
                $attrs['fill'] = $fillAttrs;
            }

            $imageAttrs = $this->documentBackgroundImageAttrs($fill, $package, $relationships);
            if ($imageAttrs !== []) {
                $attrs['image'] = $imageAttrs;
            }
        }

        return $attrs;
    }

    /**
     * @return array<string, string>
     */
    private function documentBackgroundFillAttrs(\DOMElement $fill): array
    {
        $attrs = [];
        $relationshipId = trim((string) ($this->relationshipAttr($fill, 'id') ?? ''));
        if ($relationshipId !== '') {
            $attrs['relationshipId'] = $relationshipId;
        }

        $title = trim((string) ($this->namespacedAttr($fill, self::OFFICE_VML_NS, 'title') ?? ''));
        if ($title !== '') {
            $attrs['title'] = $title;
        }

        foreach (['type', 'color2', 'recolor', 'opacity'] as $name) {
            $value = trim($fill->getAttribute($name));
            if ($value !== '') {
                $attrs[$name] = $value;
            }
        }

        return $attrs;
    }

    /**
     * @return array<string, mixed>
     */
    private function documentBackgroundImageAttrs(
        \DOMElement $fill,
        ZipPackage $package,
        ?OpcRelationships $relationships
    ): array {
        $relationshipId = trim((string) ($this->relationshipAttr($fill, 'id') ?? ''));
        if ($relationshipId === '') {
            return [];
        }

        $attrs = [
            'relationshipId' => $relationshipId,
            'relationshipType' => null,
            'target' => null,
            'targetPart' => null,
            'contentType' => null,
            'external' => null,
            'exists' => null,
            'bytes' => null,
            'issues' => [],
        ];
        if (!$relationships instanceof OpcRelationships) {
            $attrs['issues'][] = 'missing-relationships';

            return $attrs;
        }

        $relationship = $relationships->byId($relationshipId);
        if (!$relationship instanceof OpcRelationship) {
            $attrs['issues'][] = 'unknown-relationship';

            return $attrs;
        }

        $attrs['relationshipType'] = $relationship->type;
        if ($relationship->type !== self::REL_TYPE_IMAGE) {
            $attrs['issues'][] = 'unexpected-relationship-type';

            return $attrs;
        }

        try {
            $target = $relationships->resolveTarget($relationship);
        } catch (\InvalidArgumentException $e) {
            $attrs['issues'][] = 'invalid-target';

            return $attrs;
        }

        $attrs['target'] = $target;
        if ($relationship->isExternal()) {
            $externalTarget = $relationship->externalTargetPreflight();
            $attrs['external'] = true;
            $attrs['externalTargetKind'] = $externalTarget['kind'];
            $attrs['externalTargetScheme'] = $externalTarget['scheme'];
            $attrs['externalTargetAllowed'] = $externalTarget['allowed'];
            $attrs['issues'] = $externalTarget['issues'];

            return $attrs;
        }

        $targetPart = OpcPackagePath::stripQueryAndFragment($target);
        $attrs['targetPart'] = $targetPart;
        $attrs['external'] = false;
        $attrs['exists'] = $package->has($targetPart);
        if ($attrs['exists'] === true) {
            $attrs['bytes'] = strlen($package->read($targetPart));
        } else {
            $attrs['issues'][] = 'missing-package-part';
        }

        $contentType = $this->contentTypeForPackagePart($package, $targetPart);
        if ($contentType !== null) {
            $attrs['contentType'] = $contentType;
        }

        return $attrs;
    }

    /**
     * @return list<array{footnote?:array<string, int|string>, endnote?:array<string, int|string>}>
     */
    private function bodyNoteNumberingPolicySequence(\DOMElement $body): array
    {
        $sections = [];
        foreach ($body->childNodes as $child) {
            if (!$child instanceof \DOMElement) {
                continue;
            }

            $sectionProperties = null;
            if ($this->isWordElement($child, 'p')) {
                $sectionProperties = $this->paragraphSectionProperties($child);
            } elseif ($this->isWordElement($child, 'sectPr')) {
                $sectionProperties = $child;
            }

            if (!$sectionProperties instanceof \DOMElement) {
                continue;
            }

            $sections[] = $this->sectionNoteNumberingPolicies($sectionProperties);
        }

        return $sections;
    }

    /**
     * @return array{footnote?:array<string, int|string>, endnote?:array<string, int|string>}
     */
    private function sectionNoteNumberingPolicies(\DOMElement $sectionProperties): array
    {
        $policies = [];

        $footnoteProperties = $this->sectionNoteProperties($sectionProperties, 'footnotePr');
        if ($footnoteProperties !== []) {
            $policies['footnote'] = $footnoteProperties;
        }

        $endnoteProperties = $this->sectionNoteProperties($sectionProperties, 'endnotePr');
        if ($endnoteProperties !== []) {
            $policies['endnote'] = $endnoteProperties;
        }

        return $policies;
    }

    private function paragraphSectionProperties(\DOMElement $paragraph): ?\DOMElement
    {
        $paragraphProperties = $this->firstChildElement($paragraph, self::WORDPROCESSINGML_NS, 'pPr');

        return $paragraphProperties instanceof \DOMElement
            ? $this->firstChildElement($paragraphProperties, self::WORDPROCESSINGML_NS, 'sectPr')
            : null;
    }

    /**
     * @param array{footnote?:array<string, int|string>, endnote?:array<string, int|string>} $policies
     * @return array<string, array{next:int, policy:array{numberFormat:string, numberStart:int, numberRestart:string}}>
     */
    private function newNoteReferenceState(array $policies): array
    {
        $state = [];
        foreach (['footnote', 'endnote'] as $sourceType) {
            $policy = [
                'numberFormat' => 'decimal',
                'numberStart' => 1,
                'numberRestart' => 'continuous',
            ];

            foreach (($policies[$sourceType] ?? []) as $key => $value) {
                if ($key === 'numberStart') {
                    $policy[$key] = is_int($value) ? max(1, $value) : 1;
                    continue;
                }

                if (in_array($key, ['numberFormat', 'numberRestart'], true) && is_string($value) && $value !== '') {
                    $policy[$key] = $value;
                }
            }

            $state[$sourceType] = [
                'next' => $policy['numberStart'],
                'policy' => $policy,
            ];
        }

        return $state;
    }

    /**
     * @param array{footnote?:array<string, int|string>, endnote?:array<string, int|string>} $policies
     */
    private function transitionNoteReferenceStateForSection(array $policies): void
    {
        $nextState = $this->newNoteReferenceState($policies);
        foreach (['footnote', 'endnote'] as $sourceType) {
            $policy = $nextState[$sourceType]['policy'] ?? null;
            $previousNext = $this->noteReferenceState[$sourceType]['next'] ?? null;
            if (
                is_array($policy)
                && ($policy['numberRestart'] ?? 'continuous') !== 'eachSect'
                && is_int($previousNext)
            ) {
                $nextState[$sourceType]['next'] = $previousNext;
            }
        }

        $this->noteReferenceState = $nextState;
    }

    /**
     * @param array<string, AstNode> $referencedNotes
     * @param array<string, array{name:?string, basedOn:?string, headingLevel:?int, numPr:?array{numId:?string, level:?int}}> $styles
     * @param array<string, array<int, array{ordered:bool, style:string, delimiter:string, start:int, format:string}>> $numbering
     * @return list<array<string, mixed>>
     */
    private function sectionProperties(
        \DOMElement $body,
        ZipPackage $package,
        ?OpcRelationships $relationships,
        array $referencedNotes,
        array $styles,
        array $numbering
    ): array
    {
        $sections = [];
        foreach ($body->childNodes as $child) {
            if (!$child instanceof \DOMElement) {
                continue;
            }

            if ($this->isWordElement($child, 'p')) {
                $properties = $this->firstChildElement($child, self::WORDPROCESSINGML_NS, 'pPr');
                $sectionProperties = $properties instanceof \DOMElement
                    ? $this->firstChildElement($properties, self::WORDPROCESSINGML_NS, 'sectPr')
                    : null;
                if ($sectionProperties instanceof \DOMElement) {
                    $sections[] = $this->sectionPropertyAttrs(
                        $sectionProperties,
                        'paragraph',
                        count($sections),
                        $package,
                        $relationships,
                        $referencedNotes,
                        $styles,
                        $numbering
                    );
                }
                continue;
            }

            if ($this->isWordElement($child, 'sectPr')) {
                $sections[] = $this->sectionPropertyAttrs(
                    $child,
                    'body',
                    count($sections),
                    $package,
                    $relationships,
                    $referencedNotes,
                    $styles,
                    $numbering
                );
            }
        }

        return $sections;
    }

    /**
     * @param array<string, AstNode> $referencedNotes
     * @param array<string, array{name:?string, basedOn:?string, headingLevel:?int, numPr:?array{numId:?string, level:?int}}> $styles
     * @param array<string, array<int, array{ordered:bool, style:string, delimiter:string, start:int, format:string}>> $numbering
     * @return array<string, mixed>
     */
    private function sectionPropertyAttrs(
        \DOMElement $sectionProperties,
        string $source,
        int $index,
        ZipPackage $package,
        ?OpcRelationships $relationships,
        array $referencedNotes,
        array $styles,
        array $numbering
    ): array {
        $attrs = [
            'source' => $source,
            'index' => $index,
        ];

        $pageSize = $this->sectionPageSize($sectionProperties);
        if ($pageSize !== []) {
            $attrs['pageSize'] = $pageSize;
        }

        $margins = $this->sectionMargins($sectionProperties);
        if ($margins !== []) {
            $attrs['margins'] = $margins;
        }

        $columns = $this->sectionColumns($sectionProperties);
        if ($columns !== null) {
            $attrs['columns'] = $columns;
        }

        $sectionType = $this->sectionType($sectionProperties);
        if ($sectionType !== null) {
            $attrs['sectionType'] = $sectionType;
        }

        $titlePage = $this->sectionTitlePage($sectionProperties);
        if ($titlePage !== null) {
            $attrs['titlePage'] = $titlePage;
        }

        $pageNumbering = $this->sectionPageNumbering($sectionProperties);
        if ($pageNumbering !== []) {
            $attrs['pageNumbering'] = $pageNumbering;
        }

        $lineNumbering = $this->sectionLineNumbering($sectionProperties);
        if ($lineNumbering !== []) {
            $attrs['lineNumbering'] = $lineNumbering;
        }

        $documentGrid = $this->sectionDocumentGrid($sectionProperties);
        if ($documentGrid !== []) {
            $attrs['documentGrid'] = $documentGrid;
        }

        $footnoteProperties = $this->sectionNoteProperties($sectionProperties, 'footnotePr');
        if ($footnoteProperties !== []) {
            $attrs['footnoteProperties'] = $footnoteProperties;
        }

        $endnoteProperties = $this->sectionNoteProperties($sectionProperties, 'endnotePr');
        if ($endnoteProperties !== []) {
            $attrs['endnoteProperties'] = $endnoteProperties;
        }

        $headers = $this->sectionReferences(
            $sectionProperties,
            'headerReference',
            $package,
            $relationships,
            $referencedNotes,
            $styles,
            $numbering,
            'hdr'
        );
        if ($headers !== []) {
            $attrs['headers'] = $headers;
        }

        $footers = $this->sectionReferences(
            $sectionProperties,
            'footerReference',
            $package,
            $relationships,
            $referencedNotes,
            $styles,
            $numbering,
            'ftr'
        );
        if ($footers !== []) {
            $attrs['footers'] = $footers;
        }

        return $attrs;
    }

    private function sectionType(\DOMElement $sectionProperties): ?string
    {
        $type = $this->firstChildElement($sectionProperties, self::WORDPROCESSINGML_NS, 'type');
        if (!$type instanceof \DOMElement) {
            return null;
        }

        $value = $this->wordAttr($type, 'val');

        return $value === null || $value === '' ? null : $value;
    }

    private function sectionTitlePage(\DOMElement $sectionProperties): ?bool
    {
        $titlePage = $this->firstChildElement($sectionProperties, self::WORDPROCESSINGML_NS, 'titlePg');

        return $titlePage instanceof \DOMElement ? $this->onOffWordAttr($titlePage, 'val', true) : null;
    }

    /**
     * @return array<string, int|string>
     */
    private function sectionPageNumbering(\DOMElement $sectionProperties): array
    {
        $pageNumbering = $this->firstChildElement($sectionProperties, self::WORDPROCESSINGML_NS, 'pgNumType');
        if (!$pageNumbering instanceof \DOMElement) {
            return [];
        }

        $attrs = [];
        foreach ([
            'fmt' => 'format',
            'chapSep' => 'chapterSeparator',
        ] as $source => $target) {
            $value = $this->wordAttr($pageNumbering, $source);
            if ($value !== null && $value !== '') {
                $attrs[$target] = $value;
            }
        }

        foreach ([
            'start' => 'start',
            'chapStyle' => 'chapterStyle',
        ] as $source => $target) {
            $value = $this->optionalIntWordAttr($pageNumbering, $source);
            if ($value !== null) {
                $attrs[$target] = $value;
            }
        }

        return $attrs;
    }

    /**
     * @return array<string, int|string>
     */
    private function sectionLineNumbering(\DOMElement $sectionProperties): array
    {
        $lineNumbering = $this->firstChildElement($sectionProperties, self::WORDPROCESSINGML_NS, 'lnNumType');
        if (!$lineNumbering instanceof \DOMElement) {
            return [];
        }

        $attrs = [];
        $restart = $this->wordAttr($lineNumbering, 'restart');
        if ($restart !== null && $restart !== '') {
            $attrs['restart'] = $restart;
        }

        foreach ([
            'start' => 'start',
            'countBy' => 'countBy',
            'distance' => 'distanceTwips',
        ] as $source => $target) {
            $value = $this->optionalIntWordAttr($lineNumbering, $source);
            if ($value !== null) {
                $attrs[$target] = $value;
            }
        }

        return $attrs;
    }

    /**
     * @return array<string, int|string>
     */
    private function sectionDocumentGrid(\DOMElement $sectionProperties): array
    {
        $grid = $this->firstChildElement($sectionProperties, self::WORDPROCESSINGML_NS, 'docGrid');
        if (!$grid instanceof \DOMElement) {
            return [];
        }

        $attrs = [];
        $type = $this->wordAttr($grid, 'type');
        if ($type !== null && $type !== '') {
            $attrs['type'] = $type;
        }

        foreach ([
            'linePitch' => 'linePitchTwips',
            'charSpace' => 'charSpaceTwips',
        ] as $source => $target) {
            $value = $this->optionalIntWordAttr($grid, $source);
            if ($value !== null) {
                $attrs[$target] = $value;
            }
        }

        return $attrs;
    }

    /**
     * @return array<string, int|string>
     */
    private function sectionNoteProperties(\DOMElement $sectionProperties, string $localName): array
    {
        $properties = $this->firstChildElement($sectionProperties, self::WORDPROCESSINGML_NS, $localName);
        if (!$properties instanceof \DOMElement) {
            return [];
        }

        $attrs = [];
        foreach ([
            'numFmt' => 'numberFormat',
            'numRestart' => 'numberRestart',
            'pos' => 'position',
        ] as $childName => $attrName) {
            $child = $this->firstChildElement($properties, self::WORDPROCESSINGML_NS, $childName);
            if (!$child instanceof \DOMElement) {
                continue;
            }

            $value = $this->wordAttr($child, 'val');
            if ($value !== null && $value !== '') {
                $attrs[$attrName] = $value;
            }
        }

        $start = $this->firstChildElement($properties, self::WORDPROCESSINGML_NS, 'numStart');
        if ($start instanceof \DOMElement) {
            $value = $this->optionalIntWordAttr($start, 'val');
            if ($value !== null) {
                $attrs['numberStart'] = $value;
            }
        }

        return $attrs;
    }

    /**
     * @return array<string, int|string>
     */
    private function sectionPageSize(\DOMElement $sectionProperties): array
    {
        $pageSize = $this->firstChildElement($sectionProperties, self::WORDPROCESSINGML_NS, 'pgSz');
        if (!$pageSize instanceof \DOMElement) {
            return [];
        }

        $attrs = [];
        $width = $this->optionalIntWordAttr($pageSize, 'w');
        if ($width !== null) {
            $attrs['widthTwips'] = $width;
        }

        $height = $this->optionalIntWordAttr($pageSize, 'h');
        if ($height !== null) {
            $attrs['heightTwips'] = $height;
        }

        $orientation = $this->wordAttr($pageSize, 'orient');
        if ($orientation !== null && $orientation !== '') {
            $attrs['orientation'] = strtolower($orientation);
        } elseif ($width !== null && $height !== null) {
            $attrs['orientation'] = $width > $height ? 'landscape' : 'portrait';
        }

        return $attrs;
    }

    /**
     * @return array<string, int>
     */
    private function sectionMargins(\DOMElement $sectionProperties): array
    {
        $pageMargins = $this->firstChildElement($sectionProperties, self::WORDPROCESSINGML_NS, 'pgMar');
        if (!$pageMargins instanceof \DOMElement) {
            return [];
        }

        $attrs = [];
        foreach (['top', 'right', 'bottom', 'left', 'header', 'footer', 'gutter'] as $name) {
            $value = $this->optionalIntWordAttr($pageMargins, $name);
            if ($value !== null) {
                $attrs[$name . 'Twips'] = $value;
            }
        }

        return $attrs;
    }

    /**
     * @return array{count:int, equalWidth:bool, spaceTwips?:int}|null
     */
    private function sectionColumns(\DOMElement $sectionProperties): ?array
    {
        $columns = $this->firstChildElement($sectionProperties, self::WORDPROCESSINGML_NS, 'cols');
        if (!$columns instanceof \DOMElement) {
            return null;
        }

        $attrs = [
            'count' => max(1, $this->intWordAttr($columns, 'num', 1)),
            'equalWidth' => $this->onOffWordAttr($columns, 'equalWidth', true),
        ];
        $space = $this->optionalIntWordAttr($columns, 'space');
        if ($space !== null) {
            $attrs['spaceTwips'] = $space;
        }

        return $attrs;
    }

    /**
     * @param array<string, AstNode> $referencedNotes
     * @param array<string, array{name:?string, basedOn:?string, headingLevel:?int, numPr:?array{numId:?string, level:?int}}> $styles
     * @param array<string, array<int, array{ordered:bool, style:string, delimiter:string, start:int, format:string}>> $numbering
     * @return list<array<string, mixed>>
     */
    private function sectionReferences(
        \DOMElement $sectionProperties,
        string $localName,
        ZipPackage $package,
        ?OpcRelationships $relationships,
        array $referencedNotes,
        array $styles,
        array $numbering,
        string $rootName
    ): array {
        $references = [];
        foreach ($sectionProperties->childNodes as $child) {
            if (!$child instanceof \DOMElement || !$this->isWordElement($child, $localName)) {
                continue;
            }

            $relationshipId = $this->relationshipAttr($child, 'id');
            if ($relationshipId === null || $relationshipId === '') {
                continue;
            }

            $relationship = $relationships instanceof OpcRelationships ? $relationships->byId($relationshipId) : null;
            $attrs = [
                'id' => $relationshipId,
                'type' => (string) ($this->wordAttr($child, 'type') ?? 'default'),
                'target' => $relationship instanceof OpcRelationship ? $relationships->resolveTarget($relationship) : null,
                'external' => $relationship instanceof OpcRelationship ? $relationship->isExternal() : null,
                'relationshipType' => $relationship instanceof OpcRelationship ? $relationship->type : null,
            ];

            if ($relationship instanceof OpcRelationship && !$relationship->isExternal()) {
                $targetPart = OpcPackagePath::stripQueryAndFragment($relationships->resolveTarget($relationship));
                $attrs['exists'] = $package->has($targetPart);
                if ($attrs['exists'] === true) {
                    if (OpcRelationships::packageHasRelationshipsForSource($package, $targetPart)) {
                        $localRelationships = OpcRelationships::fromPackage($package, $targetPart);
                        $attrs['relationshipsPart'] = $localRelationships->relationshipPartName();
                        $attrs['relationshipCount'] = count($localRelationships->all());
                        $attrs['relationships'] = $this->relationshipTargetSummaries($package, $localRelationships);
                    }

                    $blocks = $this->headerFooterBlocks(
                        $package,
                        $targetPart,
                        $rootName,
                        $referencedNotes,
                        $styles,
                        $numbering
                    );
                    $attrs['blocks'] = $blocks;
                    $attrs['text'] = $this->plainBlockText($blocks);
                }
            }

            $references[] = $attrs;
        }

        return $references;
    }

    /**
     * @return list<array{id:string, type:string, target:string, contentType:?string, external:bool}>
     */
    private function relationshipTargetSummaries(ZipPackage $package, OpcRelationships $relationships): array
    {
        $summary = [];
        foreach ($relationships->all() as $relationship) {
            $target = $relationships->resolveTarget($relationship);
            $summary[] = [
                'id' => $relationship->id,
                'type' => $relationship->type,
                'target' => $target,
                'contentType' => $relationship->isExternal()
                    ? null
                    : $this->contentTypeForPackagePart($package, OpcPackagePath::stripQueryAndFragment($target)),
                'external' => $relationship->isExternal(),
            ];
        }

        return $summary;
    }

    /**
     * @param array<string, AstNode> $referencedNotes
     * @param array<string, array{name:?string, basedOn:?string, headingLevel:?int, numPr:?array{numId:?string, level:?int}}> $styles
     * @param array<string, array<int, array{ordered:bool, style:string, delimiter:string, start:int, format:string}>> $numbering
     * @return list<AstNode>
     */
    private function headerFooterBlocks(
        ZipPackage $package,
        string $partName,
        string $rootName,
        array $referencedNotes,
        array $styles,
        array $numbering
    ): array {
        $dom = self::loadXml($package->read($partName), 'DOCX ' . $rootName . ' XML');
        $root = $dom->documentElement;
        if (!$root instanceof \DOMElement || !$this->isWordElement($root, $rootName)) {
            return [];
        }

        $relationships = OpcRelationships::packageHasRelationshipsForSource($package, $partName)
            ? OpcRelationships::fromPackage($package, $partName)
            : null;

        return $this->blockContainerChildren($root, $package, $relationships, $referencedNotes, $styles, $numbering);
    }

    /**
     * @param array<string, AstNode> $referencedNotes
     * @param array<string, array{name:?string, basedOn:?string, headingLevel:?int, numPr:?array{numId:?string, level:?int}}> $styles
     * @param array<string, array<int, array{ordered:bool, style:string, delimiter:string, start:int, format:string}>> $numbering
     * @return list<AstNode>
     */
    private function bodyChildren(
        \DOMElement $body,
        ZipPackage $package,
        ?OpcRelationships $relationships,
        array $referencedNotes,
        array $styles,
        array $numbering
    ): array
    {
        return $this->blockContainerChildren($body, $package, $relationships, $referencedNotes, $styles, $numbering);
    }

    /**
     * @param array<string, AstNode> $referencedNotes
     * @param array<string, array{name:?string, basedOn:?string, headingLevel:?int, numPr:?array{numId:?string, level:?int}}> $styles
     * @param array<string, array<int, array{ordered:bool, style:string, delimiter:string, start:int, format:string}>> $numbering
     * @return list<AstNode>
     */
    private function blockContainerChildren(
        \DOMElement $container,
        ZipPackage $package,
        ?OpcRelationships $relationships,
        array $referencedNotes,
        array $styles,
        array $numbering
    ): array
    {
        $activeCommentRangeId = null;
        $activeProofError = null;
        $activeProofErrorNodes = [];
        $activePermissionRange = null;
        $activePermissionRangeNodes = [];
        $activeMoveRange = null;
        $activeMoveRangeNodes = [];

        return $this->blockContainerChildrenWithRanges(
            $container,
            $package,
            $relationships,
            $referencedNotes,
            $styles,
            $numbering,
            $activeCommentRangeId,
            $activeProofError,
            $activeProofErrorNodes,
            $activePermissionRange,
            $activePermissionRangeNodes,
            $activeMoveRange,
            $activeMoveRangeNodes
        );
    }

    /**
     * @param array<string, AstNode> $referencedNotes
     * @param array<string, array{name:?string, basedOn:?string, headingLevel:?int, numPr:?array{numId:?string, level:?int}}> $styles
     * @param array<string, array<int, array{ordered:bool, style:string, delimiter:string, start:int, format:string}>> $numbering
     * @param array{kind:string, startType:string}|null $activeProofError
     * @param list<AstNode> $activeProofErrorNodes
     * @param array{classes:list<string>, attributes:array<string, string>}|null $activePermissionRange
     * @param list<AstNode> $activePermissionRangeNodes
     * @param array{type:string, classes:list<string>, attributes:array<string, string>}|null $activeMoveRange
     * @param list<AstNode> $activeMoveRangeNodes
     * @return list<AstNode>
     */
    private function blockContainerChildrenWithRanges(
        \DOMElement $container,
        ZipPackage $package,
        ?OpcRelationships $relationships,
        array $referencedNotes,
        array $styles,
        array $numbering,
        ?string &$activeCommentRangeId,
        ?array &$activeProofError,
        array &$activeProofErrorNodes,
        ?array &$activePermissionRange,
        array &$activePermissionRangeNodes,
        ?array &$activeMoveRange,
        array &$activeMoveRangeNodes
    ): array
    {
        $blocks = [];
        $pendingListParagraphs = [];
        $sectionNotePolicies = $this->isWordElement($container, 'body') ? $this->bodyNoteNumberingPolicySequence($container) : [];
        $sectionNotePolicyIndex = 0;
        if ($sectionNotePolicies !== []) {
            $this->noteReferenceState = $this->newNoteReferenceState($sectionNotePolicies[0]);
        }

        foreach ($container->childNodes as $child) {
            if (!$child instanceof \DOMElement) {
                continue;
            }

            if ($this->isMarkupCompatibilityElement($child, 'AlternateContent')) {
                $this->appendListParagraphs($blocks, $pendingListParagraphs);
                array_push(
                    $blocks,
                    ...$this->alternateContentBlocks($child, $package, $relationships, $referencedNotes, $styles, $numbering)
                );
                continue;
            }

            if ($this->isWordElement($child, 'ins')) {
                $this->appendListParagraphs($blocks, $pendingListParagraphs);
                array_push(
                    $blocks,
                    ...$this->trackedAcceptedChangeBlocks($child, $package, $relationships, $referencedNotes, $styles, $numbering, 'insertion')
                );
                continue;
            }

            if ($this->isWordElement($child, 'moveTo')) {
                $this->appendListParagraphs($blocks, $pendingListParagraphs);
                array_push(
                    $blocks,
                    ...$this->trackedAcceptedChangeBlocks($child, $package, $relationships, $referencedNotes, $styles, $numbering, 'move-to')
                );
                continue;
            }

            if ($this->isWordElement($child, 'del') || $this->isWordElement($child, 'moveFrom')) {
                $this->appendListParagraphs($blocks, $pendingListParagraphs);
                continue;
            }

            if ($this->isWordElement($child, 'p')) {
                $paragraphHasTextboxRuns = $this->paragraphHasTextboxRuns($child);
                $paragraphBlocks = $this->paragraphBlocks(
                    $child,
                    $package,
                    $relationships,
                    $referencedNotes,
                    $styles,
                    $numbering,
                    $activeCommentRangeId,
                    $activeProofError,
                    $activeProofErrorNodes,
                    $activePermissionRange,
                    $activePermissionRangeNodes,
                    $activeMoveRange,
                    $activeMoveRangeNodes
                );
                if (!$paragraphHasTextboxRuns && count($paragraphBlocks) === 1 && $paragraphBlocks[0]->type === 'paragraph') {
                    $paragraph = $paragraphBlocks[0];
                    $listDefinition = $paragraph->type === 'paragraph'
                        ? $this->listDefinitionForParagraph($child, $styles, $numbering)
                        : null;
                    if ($listDefinition !== null) {
                        $pendingListParagraphs[] = [
                            'paragraph' => $paragraph,
                            'definition' => $listDefinition,
                        ];
                        continue;
                    }

                    $this->appendListParagraphs($blocks, $pendingListParagraphs);
                    $blocks[] = $paragraph;
                }
                if ($paragraphBlocks !== [] && ($paragraphHasTextboxRuns || count($paragraphBlocks) > 1 || (isset($paragraphBlocks[0]) && $paragraphBlocks[0]->type !== 'paragraph'))) {
                    $this->appendListParagraphs($blocks, $pendingListParagraphs);
                    array_push($blocks, ...$paragraphBlocks);
                }
                if ($sectionNotePolicies !== [] && $this->paragraphSectionProperties($child) instanceof \DOMElement) {
                    $sectionNotePolicyIndex++;
                    if (isset($sectionNotePolicies[$sectionNotePolicyIndex])) {
                        $this->transitionNoteReferenceStateForSection($sectionNotePolicies[$sectionNotePolicyIndex]);
                    }
                }
                continue;
            }

            if ($this->isWordElement($child, 'tbl')) {
                $this->appendListParagraphs($blocks, $pendingListParagraphs);
                $blocks[] = $this->tableNode(
                    $child,
                    $package,
                    $relationships,
                    $referencedNotes,
                    $styles,
                    $numbering,
                    $activeCommentRangeId,
                    $activeProofError,
                    $activeProofErrorNodes,
                    $activePermissionRange,
                    $activePermissionRangeNodes,
                    $activeMoveRange,
                    $activeMoveRangeNodes
                );
                continue;
            }

            if ($this->isWordElement($child, 'customXml')) {
                $this->appendListParagraphs($blocks, $pendingListParagraphs);
                array_push(
                    $blocks,
                    ...$this->customXmlBlocks($child, $package, $relationships, $referencedNotes, $styles, $numbering)
                );
                continue;
            }

            if ($this->isWordElement($child, 'sdt')) {
                $this->appendListParagraphs($blocks, $pendingListParagraphs);
                array_push(
                    $blocks,
                    ...$this->structuredDocumentTagBlocks($child, $package, $relationships, $referencedNotes, $styles, $numbering)
                );
                continue;
            }

            if ($this->isWordElement($child, 'altChunk')) {
                $this->appendListParagraphs($blocks, $pendingListParagraphs);
                array_push($blocks, ...$this->alternativeFormatBlocks($child, $package, $relationships));
                continue;
            }

            if ($sectionNotePolicies !== [] && $this->isWordElement($child, 'sectPr')) {
                $sectionNotePolicyIndex++;
                if (isset($sectionNotePolicies[$sectionNotePolicyIndex])) {
                    $this->transitionNoteReferenceStateForSection($sectionNotePolicies[$sectionNotePolicyIndex]);
                }
                continue;
            }

            $this->appendListParagraphs($blocks, $pendingListParagraphs);
        }

        $this->appendListParagraphs($blocks, $pendingListParagraphs);

        return $this->captionedDrawingBlocks($blocks, $styles);
    }

    /**
     * @param list<AstNode> $blocks
     * @param array<string, array{name?: string|null, basedOn?: string|null}> $styles
     * @return list<AstNode>
     */
    private function captionedDrawingBlocks(array $blocks, array $styles): array
    {
        $captioned = [];
        $count = count($blocks);
        for ($index = 0; $index < $count; $index++) {
            $block = $blocks[$index];
            $image = $this->singleImageParagraphChild($block);
            if ($image instanceof AstNode && isset($blocks[$index + 1])) {
                $caption = $this->captionParagraphData($blocks[$index + 1], $styles);
                if ($caption !== null) {
                    $captioned[] = new AstNode(
                        'figure',
                        $this->captionedDrawingFigureAttrs($caption),
                        [$this->captionedDrawingImage($image)]
                    );
                    $index++;
                    continue;
                }
            }

            $captioned[] = $block;
        }

        return $captioned;
    }

    private function singleImageParagraphChild(AstNode $block): ?AstNode
    {
        if ($block->type !== 'paragraph' || count($block->children) !== 1) {
            return null;
        }

        $child = $block->children[0];

        return $child->type === 'image' ? $child : null;
    }

    private function captionedDrawingImage(AstNode $image): AstNode
    {
        return new AstNode('image', $image->attrs, []);
    }

    /**
     * @param array<string, array{name?: string|null, basedOn?: string|null}> $styles
     * @return array{style:string, styleName:?string, basedOn:?string, text:string, inlines:list<AstNode>}|null
     */
    private function captionParagraphData(AstNode $block, array $styles): ?array
    {
        if ($block->type !== 'paragraph') {
            return null;
        }

        $styleId = $block->attr('style', null);
        if (!is_string($styleId) || $styleId === '') {
            return null;
        }

        $styleChain = $this->styleChain($styleId, $styles);
        $hasCaptionStyle = $this->isCaptionStyleLabel($styleId);
        foreach ($styleChain as $style) {
            if (
                $this->isCaptionStyleLabel((string) $style['style'])
                || $this->isCaptionStyleLabel((string) ($style['name'] ?? ''))
            ) {
                $hasCaptionStyle = true;
                break;
            }
        }

        if (!$hasCaptionStyle) {
            return null;
        }

        $text = trim($this->plainInlineText($block->children));
        if ($text === '') {
            return null;
        }

        $primaryStyle = $styleChain[0] ?? [
            'style' => $styleId,
            'name' => null,
            'basedOn' => null,
        ];

        return [
            'style' => $styleId,
            'styleName' => is_string($primaryStyle['name'] ?? null) ? $primaryStyle['name'] : null,
            'basedOn' => is_string($primaryStyle['basedOn'] ?? null) ? $primaryStyle['basedOn'] : null,
            'text' => $text,
            'inlines' => $block->children,
        ];
    }

    /**
     * @param array{style:string, styleName:?string, basedOn:?string, text:string, inlines:list<AstNode>} $caption
     * @return array<string, mixed>
     */
    private function captionedDrawingFigureAttrs(array $caption): array
    {
        $attributes = [
            'data-docx-caption-style' => $caption['style'],
            'data-docx-caption-placement' => 'after-drawing',
        ];
        if ($caption['styleName'] !== null) {
            $attributes['data-docx-caption-style-name'] = $caption['styleName'];
        }
        if ($caption['basedOn'] !== null) {
            $attributes['data-docx-caption-based-on'] = $caption['basedOn'];
        }

        $captionSource = [
            'kind' => 'docx-caption-paragraph',
            'placement' => 'after-drawing',
            'style' => $caption['style'],
            'styleName' => $caption['styleName'],
            'basedOn' => $caption['basedOn'],
        ];
        $sequence = $this->captionSequenceMetadata($caption['inlines']);
        if ($sequence !== null) {
            $captionSource['sequence'] = $sequence;
            foreach ([
                'name' => 'data-docx-caption-sequence',
                'result' => 'data-docx-caption-number',
                'instruction' => 'data-docx-caption-field-instruction',
                'format' => 'data-docx-caption-field-format',
                'currentSequence' => 'data-docx-caption-current-sequence',
                'nextSequence' => 'data-docx-caption-next-sequence',
                'hidden' => 'data-docx-caption-hidden',
                'resetNumber' => 'data-docx-caption-reset-number',
                'resetHeadingLevel' => 'data-docx-caption-reset-heading-level',
            ] as $source => $target) {
                if (isset($sequence[$source]) && is_string($sequence[$source]) && $sequence[$source] !== '') {
                    $attributes[$target] = $sequence[$source];
                }
            }
        }

        return [
            'caption' => $caption['text'],
            'captionText' => $caption['text'],
            'captionInlines' => $caption['inlines'],
            'captionSource' => $captionSource,
            'classes' => ['docx-captioned-figure'],
            'attributes' => $attributes,
        ];
    }

    /**
     * @param list<AstNode> $inlines
     * @return array{name?:string, result?:string, instruction?:string, format?:string, currentSequence?:string, nextSequence?:string, hidden?:string, resetNumber?:string, resetHeadingLevel?:string}|null
     */
    private function captionSequenceMetadata(array $inlines): ?array
    {
        $field = $this->firstSequenceFieldNode($inlines);
        if (!$field instanceof AstNode) {
            return null;
        }

        $fieldAttributes = $field->attr('attributes', []);
        if (!is_array($fieldAttributes)) {
            return null;
        }

        $metadata = [];
        foreach ([
            'data-docx-field-sequence' => 'name',
            'data-docx-field-instruction' => 'instruction',
            'data-docx-field-format' => 'format',
            'data-docx-field-current-sequence' => 'currentSequence',
            'data-docx-field-next-sequence' => 'nextSequence',
            'data-docx-field-hidden' => 'hidden',
            'data-docx-field-reset-number' => 'resetNumber',
            'data-docx-field-reset-heading-level' => 'resetHeadingLevel',
        ] as $source => $target) {
            $value = $fieldAttributes[$source] ?? null;
            if (is_string($value) && $value !== '') {
                $metadata[$target] = $value;
            }
        }

        $result = trim($this->plainInlineText($field->children));
        if ($result !== '') {
            $metadata['result'] = $result;
        }

        return $metadata === [] ? null : $metadata;
    }

    /**
     * @param list<AstNode> $nodes
     */
    private function firstSequenceFieldNode(array $nodes): ?AstNode
    {
        foreach ($nodes as $node) {
            if (!$node instanceof AstNode) {
                continue;
            }

            $attributes = $node->attr('attributes', []);
            if (
                $node->type === 'span'
                && is_array($attributes)
                && ($attributes['data-docx-field'] ?? null) === 'seq'
            ) {
                return $node;
            }

            $nested = $this->firstSequenceFieldNode($node->children);
            if ($nested instanceof AstNode) {
                return $nested;
            }
        }

        return null;
    }

    /**
     * @param array<string, array{name?: string|null, basedOn?: string|null}> $styles
     * @return list<array{style:string, name:?string, basedOn:?string}>
     */
    private function styleChain(string $styleId, array $styles): array
    {
        $chain = [];
        $seen = [];
        $current = $styleId;
        while ($current !== '' && !isset($seen[$current])) {
            $seen[$current] = true;
            $style = isset($styles[$current]) && is_array($styles[$current]) ? $styles[$current] : [];
            $name = is_string($style['name'] ?? null) ? $style['name'] : null;
            $basedOn = is_string($style['basedOn'] ?? null) ? $style['basedOn'] : null;
            $chain[] = [
                'style' => $current,
                'name' => $name,
                'basedOn' => $basedOn,
            ];

            if ($basedOn === null || $basedOn === '') {
                break;
            }
            $current = $basedOn;
        }

        return $chain;
    }

    private function isCaptionStyleLabel(string $label): bool
    {
        $normalized = strtolower((string) preg_replace('/[\s_-]+/', '', $label));

        return $normalized === 'caption';
    }

    /**
     * @param array<string, AstNode> $referencedNotes
     * @param array<string, array{name:?string, basedOn:?string, headingLevel:?int, numPr:?array{numId:?string, level:?int}}> $styles
     * @param array<string, array<int, array{ordered:bool, style:string, delimiter:string, start:int, format:string}>> $numbering
     * @return list<AstNode>
     */
    private function trackedAcceptedChangeBlocks(
        \DOMElement $change,
        ZipPackage $package,
        ?OpcRelationships $relationships,
        array $referencedNotes,
        array $styles,
        array $numbering,
        string $type
    ): array {
        $attrs = $this->trackedChangeSpanAttrs($change, $type);
        $blocks = $this->blockContainerChildren($change, $package, $relationships, $referencedNotes, $styles, $numbering);
        if ($blocks !== []) {
            return [new AstNode('div', $attrs, $blocks)];
        }

        $inlines = $this->coalesceTextNodes($this->inlineContainerNodes($change, $package, $relationships, $referencedNotes));
        if ($inlines === []) {
            return [];
        }

        return [new AstNode('paragraph', [], [new AstNode('span', $attrs, $inlines)])];
    }

    /**
     * @param array<string, AstNode> $referencedNotes
     * @param array<string, array{name:?string, basedOn:?string, headingLevel:?int, numPr:?array{numId:?string, level:?int}}> $styles
     * @param array<string, array<int, array{ordered:bool, style:string, delimiter:string, start:int, format:string}>> $numbering
     * @return list<AstNode>
     */
    private function alternateContentBlocks(
        \DOMElement $alternateContent,
        ZipPackage $package,
        ?OpcRelationships $relationships,
        array $referencedNotes,
        array $styles,
        array $numbering
    ): array {
        $selection = $this->alternateContentSelection($alternateContent);
        if (!$selection instanceof \DOMElement) {
            return [];
        }

        return $this->blockContainerChildren($selection, $package, $relationships, $referencedNotes, $styles, $numbering);
    }

    /**
     * @param array<string, AstNode> $referencedNotes
     * @param array<string, array{name:?string, basedOn:?string, headingLevel:?int, numPr:?array{numId:?string, level:?int}}> $styles
     * @param array<string, array<int, array{ordered:bool, style:string, delimiter:string, start:int, format:string}>> $numbering
     * @return list<AstNode>
     */
    private function structuredDocumentTagBlocks(
        \DOMElement $sdt,
        ZipPackage $package,
        ?OpcRelationships $relationships,
        array $referencedNotes,
        array $styles,
        array $numbering
    ): array {
        $content = $this->structuredDocumentTagContent($sdt);
        if (!$content instanceof \DOMElement) {
            return [];
        }

        $blocks = $this->blockContainerChildren($content, $package, $relationships, $referencedNotes, $styles, $numbering);
        if ($blocks !== []) {
            return [new AstNode('div', $this->structuredDocumentTagAttrs($sdt), $blocks)];
        }

        $inlines = $this->inlineContainerNodes($content, $package, $relationships, $referencedNotes);
        if ($inlines === []) {
            return [];
        }

        return [new AstNode('paragraph', [], [new AstNode('span', $this->structuredDocumentTagAttrs($sdt), $inlines)])];
    }

    /**
     * @param array<string, AstNode> $referencedNotes
     * @param array<string, array{name:?string, basedOn:?string, headingLevel:?int, numPr:?array{numId:?string, level:?int}}> $styles
     * @param array<string, array<int, array{ordered:bool, style:string, delimiter:string, start:int, format:string}>> $numbering
     * @param array{kind:string, startType:string}|null $activeProofError
     * @param list<AstNode> $activeProofErrorNodes
     * @param array{classes:list<string>, attributes:array<string, string>}|null $activePermissionRange
     * @param list<AstNode> $activePermissionRangeNodes
     * @param array{type:string, classes:list<string>, attributes:array<string, string>}|null $activeMoveRange
     * @param list<AstNode> $activeMoveRangeNodes
     * @return list<AstNode>
     */
    private function paragraphBlocks(
        \DOMElement $paragraph,
        ZipPackage $package,
        ?OpcRelationships $relationships,
        array $referencedNotes,
        array $styles,
        array $numbering,
        ?string &$activeCommentRangeId,
        ?array &$activeProofError,
        array &$activeProofErrorNodes,
        ?array &$activePermissionRange,
        array &$activePermissionRangeNodes,
        ?array &$activeMoveRange,
        array &$activeMoveRangeNodes
    ): array
    {
        if (!$this->paragraphHasTextboxRuns($paragraph)) {
            $node = $this->paragraphNode(
                $paragraph,
                $package,
                $relationships,
                $referencedNotes,
                $styles,
                $activeCommentRangeId,
                $activeProofError,
                $activeProofErrorNodes,
                $activePermissionRange,
                $activePermissionRangeNodes,
                $activeMoveRange,
                $activeMoveRangeNodes
            );

            return $node instanceof AstNode ? [$node] : [];
        }

        $blocks = [];
        $segmentChildren = [];
        foreach ($paragraph->childNodes as $child) {
            if (!$child instanceof \DOMElement || $this->isWordElement($child, 'pPr')) {
                continue;
            }

            $textboxes = $this->runTextboxContents($child);
            if ($textboxes === []) {
                $segmentChildren[] = $child;
                continue;
            }

            $this->appendParagraphSegment(
                $blocks,
                $paragraph,
                $segmentChildren,
                $package,
                $relationships,
                $referencedNotes,
                $styles,
                $activeCommentRangeId,
                $activeProofError,
                $activeProofErrorNodes,
                $activePermissionRange,
                $activePermissionRangeNodes,
                $activeMoveRange,
                $activeMoveRangeNodes
            );
            foreach ($textboxes as $textbox) {
                $textboxBlocks = $this->blockContainerChildren(
                    $textbox['content'],
                    $package,
                    $relationships,
                    $referencedNotes,
                    $styles,
                    $numbering
                );
                if ($textboxBlocks !== []) {
                    $blocks[] = new AstNode('div', $textbox['attrs'], $textboxBlocks);
                }
            }
            $segmentChildren = [];
        }

        $this->appendParagraphSegment(
            $blocks,
            $paragraph,
            $segmentChildren,
            $package,
            $relationships,
            $referencedNotes,
            $styles,
            $activeCommentRangeId,
            $activeProofError,
            $activeProofErrorNodes,
            $activePermissionRange,
            $activePermissionRangeNodes,
            $activeMoveRange,
            $activeMoveRangeNodes
        );

        return $blocks;
    }

    private function paragraphHasTextboxRuns(\DOMElement $paragraph): bool
    {
        foreach ($paragraph->childNodes as $child) {
            if ($child instanceof \DOMElement && $this->runTextboxContents($child) !== []) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param list<AstNode> $blocks
     * @param list<\DOMElement> $children
     * @param array<string, AstNode> $referencedNotes
     * @param array<string, array{name:?string, basedOn:?string, headingLevel:?int, numPr:?array{numId:?string, level:?int}}> $styles
     * @param array{kind:string, startType:string}|null $activeProofError
     * @param list<AstNode> $activeProofErrorNodes
     * @param array{classes:list<string>, attributes:array<string, string>}|null $activePermissionRange
     * @param list<AstNode> $activePermissionRangeNodes
     * @param array{type:string, classes:list<string>, attributes:array<string, string>}|null $activeMoveRange
     * @param list<AstNode> $activeMoveRangeNodes
     */
    private function appendParagraphSegment(
        array &$blocks,
        \DOMElement $sourceParagraph,
        array $children,
        ZipPackage $package,
        ?OpcRelationships $relationships,
        array $referencedNotes,
        array $styles,
        ?string &$activeCommentRangeId,
        ?array &$activeProofError,
        array &$activeProofErrorNodes,
        ?array &$activePermissionRange,
        array &$activePermissionRangeNodes,
        ?array &$activeMoveRange,
        array &$activeMoveRangeNodes
    ): void {
        if ($children === []) {
            return;
        }

        $segment = $this->paragraphSegmentElement($sourceParagraph, $children);
        if (!$segment instanceof \DOMElement) {
            return;
        }

        $node = $this->paragraphNode(
            $segment,
            $package,
            $relationships,
            $referencedNotes,
            $styles,
            $activeCommentRangeId,
            $activeProofError,
            $activeProofErrorNodes,
            $activePermissionRange,
            $activePermissionRangeNodes,
            $activeMoveRange,
            $activeMoveRangeNodes
        );
        if ($node instanceof AstNode) {
            $blocks[] = $node;
        }
    }

    /**
     * @param list<\DOMElement> $children
     */
    private function paragraphSegmentElement(\DOMElement $sourceParagraph, array $children): ?\DOMElement
    {
        $segment = $sourceParagraph->cloneNode(false);
        if (!$segment instanceof \DOMElement) {
            return null;
        }

        $properties = $this->firstChildElement($sourceParagraph, self::WORDPROCESSINGML_NS, 'pPr');
        if ($properties instanceof \DOMElement) {
            $segment->appendChild($properties->cloneNode(true));
        }

        foreach ($children as $child) {
            $segment->appendChild($child->cloneNode(true));
        }

        return $segment;
    }

    /**
     * @return list<array{content:\DOMElement, attrs:array{classes:list<string>, attributes:array<string, string>}}>
     */
    private function runTextboxContents(\DOMElement $run): array
    {
        if (!$this->isWordElement($run, 'r')) {
            return [];
        }

        $searchRoot = $this->runAlternateContentFallback($run) ?? $run;
        $contents = [];
        foreach ($searchRoot->getElementsByTagNameNS(self::WORDPROCESSINGML_NS, 'pict') as $pict) {
            if (!$pict instanceof \DOMElement) {
                continue;
            }

            foreach ($pict->getElementsByTagNameNS(self::VML_NS, 'textbox') as $textbox) {
                if (!$textbox instanceof \DOMElement) {
                    continue;
                }

                $attrs = $this->vmlTextboxAttrs($textbox, $pict);
                foreach ($textbox->getElementsByTagNameNS(self::WORDPROCESSINGML_NS, 'txbxContent') as $content) {
                    if ($content instanceof \DOMElement) {
                        $contents[] = [
                            'content' => $content,
                            'attrs' => $attrs,
                        ];
                    }
                }
            }
        }

        foreach ($searchRoot->getElementsByTagNameNS(self::WORDPROCESSINGML_NS, 'drawing') as $drawing) {
            if (!$drawing instanceof \DOMElement) {
                continue;
            }

            foreach ($drawing->getElementsByTagNameNS(self::WORDPROCESSINGML_NS, 'txbxContent') as $content) {
                if (
                    $content instanceof \DOMElement
                    && $this->hasAncestorElement($content, self::WORDPROCESSING_SHAPE_NS, 'txbx', $drawing)
                ) {
                    $contents[] = [
                        'content' => $content,
                        'attrs' => $this->drawingTextboxAttrs($content, $drawing),
                    ];
                }
            }
        }

        return $contents;
    }

    /**
     * @return array{classes:list<string>, attributes:array<string, string>}
     */
    private function vmlTextboxAttrs(\DOMElement $textbox, \DOMElement $pict): array
    {
        $classes = ['docx-textbox', 'docx-vml-textbox'];
        $attributes = ['data-docx-textbox-kind' => 'vml'];
        $shape = $this->vmlShapeForTextbox($textbox, $pict);

        if ($shape instanceof \DOMElement) {
            $shapeKind = $shape->localName;
            $attributes['data-docx-shape-kind'] = $shapeKind;

            $suffix = $this->metadataClassSuffix($shapeKind);
            if ($suffix !== null) {
                $classes[] = 'docx-vml-' . $suffix;
            }

            foreach ([
                'id' => 'data-docx-shape-id',
                'alt' => 'data-docx-shape-alt',
                'style' => 'data-docx-shape-style',
            ] as $source => $target) {
                $value = trim($shape->getAttribute($source));
                if ($value !== '') {
                    $attributes[$target] = $value;
                }
            }
        }

        foreach ([
            'inset' => 'data-docx-textbox-inset',
            'style' => 'data-docx-textbox-style',
            'fitshape' => 'data-docx-textbox-fit-shape',
        ] as $source => $target) {
            $value = trim($textbox->getAttribute($source));
            if ($value !== '') {
                $attributes[$target] = $value;
            }
        }

        $insetMode = trim($textbox->getAttributeNS(self::OFFICE_VML_NS, 'insetmode'));
        if ($insetMode !== '') {
            $attributes['data-docx-textbox-inset-mode'] = $insetMode;
        }

        return [
            'classes' => array_values(array_unique($classes)),
            'attributes' => $attributes,
        ];
    }

    /**
     * @return array{classes:list<string>, attributes:array<string, string>}
     */
    private function drawingTextboxAttrs(\DOMElement $content, \DOMElement $drawing): array
    {
        $attributes = ['data-docx-textbox-kind' => 'drawingml'];
        $properties = $this->drawingPropertiesForElement($content, $drawing);
        if ($properties instanceof \DOMElement) {
            foreach ([
                'id' => 'data-docx-docpr-id',
                'name' => 'data-docx-docpr-name',
                'descr' => 'data-docx-docpr-description',
                'title' => 'data-docx-docpr-title',
            ] as $source => $target) {
                $value = trim($properties->getAttribute($source));
                if ($value !== '') {
                    $attributes[$target] = $value;
                }
            }
        }

        $attrs = [
            'classes' => ['docx-textbox', 'docx-drawing-textbox'],
            'attributes' => $attributes,
        ];

        $attrs = $this->mergeNodeMetadataAttrs(
            $attrs,
            $this->drawingGeometryAttrs($this->drawingContainerForElement($content, $drawing))
        );

        return $this->mergeNodeMetadataAttrs(
            $attrs,
            $this->drawingTextboxShapeMetadataAttrs($content, $drawing)
        );
    }

    /**
     * @return array{classes?:list<string>, attributes?:array<string, string>}
     */
    private function drawingTextboxShapeMetadataAttrs(\DOMElement $content, \DOMElement $drawing): array
    {
        $shape = $this->drawingWordprocessingShapeForElement($content, $drawing);
        if (!$shape instanceof \DOMElement) {
            return [];
        }

        $classes = ['docx-drawing-shape'];
        $attributes = [];

        $nonVisual = $this->firstChildElement($shape, self::WORDPROCESSING_SHAPE_NS, 'cNvSpPr');
        if ($nonVisual instanceof \DOMElement) {
            $textBox = $this->normalizedOnOffAttribute($nonVisual, 'txBox');
            if ($textBox !== null) {
                $attributes['data-docx-shape-textbox'] = $textBox;
                if ($textBox === 'true') {
                    $classes[] = 'docx-shape-textbox';
                }
            }
        }

        $shapeProperties = $this->firstChildElement($shape, self::WORDPROCESSING_SHAPE_NS, 'spPr');
        $presetClass = null;
        if ($shapeProperties instanceof \DOMElement) {
            $transform = $this->firstChildElement($shapeProperties, self::DRAWINGML_MAIN_NS, 'xfrm');
            if ($transform instanceof \DOMElement) {
                $hasTransform = false;
                $flipHorizontal = false;
                $flipVertical = false;

                $rotation = trim($transform->getAttribute('rot'));
                if ($rotation !== '') {
                    $attributes['data-docx-shape-rotation'] = $rotation;
                    $hasTransform = true;
                }

                $flip = $this->normalizedOnOffAttribute($transform, 'flipH');
                if ($flip !== null) {
                    $attributes['data-docx-shape-flip-horizontal'] = $flip;
                    $hasTransform = true;
                    $flipHorizontal = $flip === 'true';
                }

                $flip = $this->normalizedOnOffAttribute($transform, 'flipV');
                if ($flip !== null) {
                    $attributes['data-docx-shape-flip-vertical'] = $flip;
                    $hasTransform = true;
                    $flipVertical = $flip === 'true';
                }

                $offset = $this->firstChildElement($transform, self::DRAWINGML_MAIN_NS, 'off');
                if ($offset instanceof \DOMElement) {
                    foreach ([
                        'x' => 'data-docx-shape-offset-x-emu',
                        'y' => 'data-docx-shape-offset-y-emu',
                    ] as $source => $target) {
                        $value = trim($offset->getAttribute($source));
                        if ($value !== '') {
                            $attributes[$target] = $value;
                            $hasTransform = true;
                        }
                    }
                }

                $extent = $this->firstChildElement($transform, self::DRAWINGML_MAIN_NS, 'ext');
                if ($extent instanceof \DOMElement) {
                    foreach ([
                        'cx' => 'data-docx-shape-width-emu',
                        'cy' => 'data-docx-shape-height-emu',
                    ] as $source => $target) {
                        $value = trim($extent->getAttribute($source));
                        if ($value !== '') {
                            $attributes[$target] = $value;
                            $hasTransform = true;
                        }
                    }
                }

                if ($hasTransform) {
                    $classes[] = 'docx-shape-transform';
                    if ($flipHorizontal) {
                        $classes[] = 'docx-shape-flip-horizontal';
                    }
                    if ($flipVertical) {
                        $classes[] = 'docx-shape-flip-vertical';
                    }
                }
            }

            $presetGeometry = $this->firstChildElement($shapeProperties, self::DRAWINGML_MAIN_NS, 'prstGeom');
            if ($presetGeometry instanceof \DOMElement) {
                $preset = trim($presetGeometry->getAttribute('prst'));
                if ($preset !== '') {
                    $attributes['data-docx-shape-preset'] = $preset;
                    $suffix = $this->metadataClassSuffix($preset);
                    if ($suffix !== null) {
                        $presetClass = 'docx-shape-preset-' . $suffix;
                    }
                }
            }
        }

        if ($presetClass !== null) {
            $classes[] = $presetClass;
        }

        $this->appendDrawingTextboxBodyPropertyAttrs($shape, $classes, $attributes);

        if ($classes === ['docx-drawing-shape'] && $attributes === []) {
            return [];
        }

        return [
            'classes' => array_values(array_unique($classes)),
            'attributes' => $attributes,
        ];
    }

    /**
     * @param list<string> $classes
     * @param array<string, string> $attributes
     */
    private function appendDrawingTextboxBodyPropertyAttrs(\DOMElement $shape, array &$classes, array &$attributes): void
    {
        $bodyProperties = $this->firstChildElement($shape, self::WORDPROCESSING_SHAPE_NS, 'bodyPr')
            ?? $this->firstChildElement($shape, self::DRAWINGML_MAIN_NS, 'bodyPr');
        if (!$bodyProperties instanceof \DOMElement) {
            return;
        }

        $bodyClasses = [];
        $hasBodyMetadata = false;
        foreach ([
            'anchor' => 'data-docx-textbox-anchor',
            'wrap' => 'data-docx-textbox-wrap',
            'vert' => 'data-docx-textbox-vertical',
            'numCol' => 'data-docx-textbox-column-count',
            'spcCol' => 'data-docx-textbox-column-spacing-emu',
            'lIns' => 'data-docx-textbox-inset-left-emu',
            'tIns' => 'data-docx-textbox-inset-top-emu',
            'rIns' => 'data-docx-textbox-inset-right-emu',
            'bIns' => 'data-docx-textbox-inset-bottom-emu',
        ] as $source => $target) {
            $value = trim($bodyProperties->getAttribute($source));
            if ($value === '') {
                continue;
            }

            $attributes[$target] = $value;
            $hasBodyMetadata = true;
            if (in_array($source, ['anchor', 'wrap', 'vert'], true)) {
                $suffix = $this->metadataClassSuffix($value);
                if ($suffix !== null) {
                    $bodyClasses[] = match ($source) {
                        'anchor' => 'docx-textbox-anchor-' . $suffix,
                        'wrap' => 'docx-textbox-wrap-' . $suffix,
                        default => 'docx-textbox-vertical-' . $suffix,
                    };
                }
            }
        }

        foreach ([
            'anchorCtr' => 'data-docx-textbox-anchor-center',
            'upright' => 'data-docx-textbox-upright',
            'rtlCol' => 'data-docx-textbox-rtl-columns',
        ] as $source => $target) {
            $value = $this->normalizedOnOffAttribute($bodyProperties, $source);
            if ($value !== null) {
                $attributes[$target] = $value;
                $hasBodyMetadata = true;
            }
        }

        $autofit = $this->drawingTextboxAutofitValue($bodyProperties);
        if ($autofit !== null) {
            $attributes['data-docx-textbox-autofit'] = $autofit;
            $bodyClasses[] = 'docx-textbox-autofit-' . $autofit;
            $hasBodyMetadata = true;
        }

        if ($hasBodyMetadata) {
            $classes[] = 'docx-textbox-body-properties';
            array_push($classes, ...$bodyClasses);
        }
    }

    private function drawingTextboxAutofitValue(\DOMElement $bodyProperties): ?string
    {
        foreach ($bodyProperties->childNodes as $child) {
            if (!$child instanceof \DOMElement || $child->namespaceURI !== self::DRAWINGML_MAIN_NS) {
                continue;
            }

            if ($child->localName === 'noAutofit') {
                return 'none';
            }
            if ($child->localName === 'normAutofit') {
                return 'normal';
            }
            if ($child->localName === 'spAutoFit') {
                return 'shape';
            }
        }

        return null;
    }

    private function drawingWordprocessingShapeForElement(\DOMElement $element, \DOMElement $drawing): ?\DOMElement
    {
        $node = $element->parentNode;
        while ($node instanceof \DOMElement) {
            if ($node->namespaceURI === self::WORDPROCESSING_SHAPE_NS && $node->localName === 'wsp') {
                return $node;
            }
            if ($node === $drawing) {
                break;
            }
            $node = $node->parentNode;
        }

        return null;
    }

    private function normalizedOnOffAttribute(\DOMElement $element, string $name): ?string
    {
        $value = trim($element->getAttribute($name));
        if ($value === '') {
            return null;
        }

        $onOff = $this->onOffStringValue($value);
        if ($onOff === null) {
            return $value;
        }

        return $onOff ? 'true' : 'false';
    }

    private function vmlShapeForTextbox(\DOMElement $textbox, \DOMElement $pict): ?\DOMElement
    {
        $node = $textbox->parentNode;
        while ($node instanceof \DOMElement) {
            if (
                $node->namespaceURI === self::VML_NS
                && in_array($node->localName, ['shape', 'rect', 'oval', 'roundrect', 'group'], true)
            ) {
                return $node;
            }

            if ($node === $pict) {
                break;
            }

            $node = $node->parentNode;
        }

        return null;
    }

    private function hasAncestorElement(\DOMElement $element, string $namespace, string $localName, \DOMElement $stopAt): bool
    {
        $node = $element->parentNode;
        while ($node instanceof \DOMElement) {
            if ($node->namespaceURI === $namespace && $node->localName === $localName) {
                return true;
            }

            if ($node === $stopAt) {
                break;
            }

            $node = $node->parentNode;
        }

        return false;
    }

    private function runAlternateContentFallback(\DOMElement $run): ?\DOMElement
    {
        foreach ($run->childNodes as $child) {
            if (
                $child instanceof \DOMElement
                && $child->namespaceURI === self::MARKUP_COMPATIBILITY_NS
                && $child->localName === 'AlternateContent'
            ) {
                return $this->firstChildElement($child, self::MARKUP_COMPATIBILITY_NS, 'Fallback');
            }
        }

        return null;
    }

    private function alternateContentSelection(\DOMElement $alternateContent): ?\DOMElement
    {
        foreach ($alternateContent->childNodes as $child) {
            if (
                $child instanceof \DOMElement
                && $this->isMarkupCompatibilityElement($child, 'Choice')
                && $this->supportsAlternateContentChoice($child)
            ) {
                return $child;
            }
        }

        return $this->firstChildElement($alternateContent, self::MARKUP_COMPATIBILITY_NS, 'Fallback');
    }

    private function supportsAlternateContentChoice(\DOMElement $choice): bool
    {
        $requires = trim($choice->getAttribute('Requires'));
        if ($requires === '') {
            return false;
        }

        foreach (preg_split('/\s+/', $requires) ?: [] as $prefix) {
            if ($prefix === '' || preg_match('/^[A-Za-z_][A-Za-z0-9._-]*$/D', $prefix) !== 1) {
                return false;
            }

            $namespace = $choice->lookupNamespaceURI($prefix);
            if ($namespace === null || !$this->supportsMarkupCompatibilityNamespace($namespace)) {
                return false;
            }
        }

        return true;
    }

    private function supportsMarkupCompatibilityNamespace(string $namespace): bool
    {
        return in_array($namespace, [
            self::WORDPROCESSINGML_NS,
            self::DRAWINGML_MAIN_NS,
            self::DRAWINGML_PICTURE_NS,
            self::WORDPROCESSING_DRAWING_NS,
            self::WORDPROCESSING_SHAPE_NS,
            self::OFFICE_RELATIONSHIPS_NS,
            self::OFFICE_MATH_NS,
            self::VML_NS,
            self::OFFICE_VML_NS,
        ], true);
    }

    /**
     * @param array<string, AstNode> $referencedNotes
     * @param array<string, array{name:?string, basedOn:?string, headingLevel:?int, numPr:?array{numId:?string, level:?int}}> $styles
     * @param array{kind:string, startType:string}|null $activeProofError
     * @param list<AstNode> $activeProofErrorNodes
     * @param array{classes:list<string>, attributes:array<string, string>}|null $activePermissionRange
     * @param list<AstNode> $activePermissionRangeNodes
     * @param array{type:string, classes:list<string>, attributes:array<string, string>}|null $activeMoveRange
     * @param list<AstNode> $activeMoveRangeNodes
     */
    private function paragraphNode(
        \DOMElement $paragraph,
        ZipPackage $package,
        ?OpcRelationships $relationships,
        array $referencedNotes,
        array $styles,
        ?string &$activeCommentRangeId,
        ?array &$activeProofError,
        array &$activeProofErrorNodes,
        ?array &$activePermissionRange,
        array &$activePermissionRangeNodes,
        ?array &$activeMoveRange,
        array &$activeMoveRangeNodes
    ): ?AstNode
    {
        $previousParagraphRunProperties = $this->currentParagraphRunProperties;
        $this->currentParagraphRunProperties = $this->paragraphRunProperties($paragraph, $styles);
        try {
            $children = $this->paragraphInlines(
                $paragraph,
                $package,
                $relationships,
                $referencedNotes,
                $activeCommentRangeId,
                $activeProofError,
                $activeProofErrorNodes,
                $activePermissionRange,
                $activePermissionRangeNodes,
                $activeMoveRange,
                $activeMoveRangeNodes
            );
        } finally {
            $this->currentParagraphRunProperties = $previousParagraphRunProperties;
        }
        $text = $this->plainInlineText($children);
        if ($children === [] && $text === '') {
            return null;
        }

        $children = $this->applyParagraphMetadata($paragraph, $children, $styles);
        $style = $this->paragraphStyleId($paragraph);
        $headingLevel = $this->paragraphHeadingLevel($paragraph, $styles);
        if ($headingLevel !== null) {
            return new AstNode('heading', [
                'level' => $headingLevel,
                'style' => $style,
                'text' => $text,
                'id' => $this->slugify($text),
            ], $children);
        }

        return new AstNode('paragraph', $style === null ? [] : ['style' => $style], $children);
    }

    /**
     * @param list<AstNode> $children
     * @param array<string, array{name:?string, basedOn:?string, headingLevel:?int, numPr:?array{numId:?string, level:?int}, paragraphMetadata?:array{classes:list<string>, attributes:array<string, string>}|null}> $styles
     * @return list<AstNode>
     */
    private function applyParagraphMetadata(\DOMElement $paragraph, array $children, array $styles): array
    {
        if ($children === []) {
            return [];
        }

        $attrs = $this->paragraphMetadataAttrs($paragraph, $styles);
        if ($attrs === null) {
            return $children;
        }

        return [new AstNode('span', $attrs, $children)];
    }

    /**
     * @param array<string, array{name:?string, basedOn:?string, headingLevel:?int, numPr:?array{numId:?string, level:?int}, paragraphMetadata?:array{classes:list<string>, attributes:array<string, string>}|null}> $styles
     * @return array{classes:list<string>, attributes:array<string, string>}|null
     */
    private function paragraphMetadataAttrs(\DOMElement $paragraph, array $styles): ?array
    {
        $attrs = null;
        if ($this->currentDefaultParagraphMetadata !== null) {
            $attrs = $this->currentDefaultParagraphMetadata;
        }
        $style = $this->paragraphStyleId($paragraph);
        if ($style !== null) {
            $seen = [];
            $attrs = $this->mergeMetadataAttrs(
                $attrs,
                $this->resolveStyleParagraphMetadataAttrs($style, $styles, $seen)
            );
        }
        if ($this->currentTableConditionalParagraphMetadata !== null) {
            $attrs = $this->mergeMetadataAttrs($attrs, $this->currentTableConditionalParagraphMetadata);
        }

        $properties = $this->firstChildElement($paragraph, self::WORDPROCESSINGML_NS, 'pPr');
        if (!$properties instanceof \DOMElement) {
            return $attrs;
        }

        return $this->mergeMetadataAttrs($attrs, $this->paragraphPropertiesMetadataAttrs($properties));
    }

    /**
     * @return array{classes:list<string>, attributes:array<string, string>}|null
     */
    private function paragraphPropertiesMetadataAttrs(\DOMElement $properties, bool $includeFormattingChange = true): ?array
    {
        $classes = [];
        $attributes = [];

        if ($this->hasOnOffChild($properties, 'bidi')) {
            $classes[] = 'docx-paragraph-bidi';
            $classes[] = 'docx-rtl';
            $attributes['data-docx-paragraph-bidi'] = 'true';
            $attributes['dir'] = 'rtl';
        }

        $textDirection = $this->firstChildElement($properties, self::WORDPROCESSINGML_NS, 'textDirection');
        if ($textDirection instanceof \DOMElement) {
            $value = trim((string) ($this->wordAttr($textDirection, 'val') ?? ''));
            if ($value !== '') {
                $classes[] = 'docx-text-direction';
                $suffix = $this->metadataClassSuffix($value);
                if ($suffix !== null) {
                    $classes[] = 'docx-text-direction-' . $suffix;
                }
                $attributes['data-docx-text-direction'] = $value;
            }
        }

        $this->appendParagraphAlignmentMetadata($properties, $classes, $attributes);
        $this->appendParagraphSpacingMetadata($properties, $classes, $attributes);
        $this->appendParagraphIndentMetadata($properties, $classes, $attributes);
        $this->appendParagraphTabsMetadata($properties, $classes, $attributes);
        $this->appendParagraphBorderMetadata($properties, $classes, $attributes);
        $this->appendParagraphFrameMetadata($properties, $classes, $attributes);
        $this->appendParagraphPolicyMetadata($properties, $classes, $attributes);

        if ($this->hasOnOffChild($properties, 'keepNext')) {
            $classes[] = 'docx-keep-next';
            $attributes['data-docx-keep-next'] = 'true';
        }

        if ($this->hasOnOffChild($properties, 'pageBreakBefore')) {
            $classes[] = 'docx-page-break-before';
            $attributes['data-docx-page-break-before'] = 'true';
        }

        if ($includeFormattingChange) {
            $changeAttrs = $this->paragraphFormattingChangeAttrs($properties);
            if ($changeAttrs !== null) {
                array_push($classes, ...$changeAttrs['classes']);
                $attributes = array_replace($attributes, $changeAttrs['attributes']);
            }
        }

        if ($classes === [] && $attributes === []) {
            return null;
        }

        return [
            'classes' => array_values(array_unique($classes)),
            'attributes' => $attributes,
        ];
    }

    /**
     * @return array{classes:list<string>, attributes:array<string, string>}|null
     */
    private function paragraphFormattingChangeAttrs(\DOMElement $properties): ?array
    {
        $change = $this->firstChildElement($properties, self::WORDPROCESSINGML_NS, 'pPrChange');
        if (!$change instanceof \DOMElement) {
            return null;
        }

        $attrs = $this->trackedFormattingChangeAttrs($change, 'paragraph');
        $previous = $this->firstChildElement($change, self::WORDPROCESSINGML_NS, 'pPr');
        if ($previous instanceof \DOMElement) {
            $previousStyle = $this->firstChildElement($previous, self::WORDPROCESSINGML_NS, 'pStyle');
            $styleId = $previousStyle instanceof \DOMElement ? $this->wordAttr($previousStyle, 'val') : null;
            if ($styleId !== null && $styleId !== '') {
                $attrs['attributes']['data-docx-previous-paragraph-style'] = $styleId;
            }

            $previousAttrs = $this->paragraphPropertiesMetadataAttrs($previous, false);
            if ($previousAttrs !== null) {
                foreach ($previousAttrs['attributes'] as $name => $value) {
                    $previousName = $this->previousFormattingAttributeName((string) $name);
                    if ($previousName !== null) {
                        $attrs['attributes'][$previousName] = $value;
                    }
                }
            }
        }

        return $attrs;
    }

    /**
     * @param array<string, array{name:?string, basedOn:?string, headingLevel:?int, numPr:?array{numId:?string, level:?int}, paragraphMetadata?:array{classes:list<string>, attributes:array<string, string>}|null}> $styles
     * @param array<string, bool> $seen
     * @return array{classes:list<string>, attributes:array<string, string>}|null
     */
    private function resolveStyleParagraphMetadataAttrs(?string $styleId, array $styles, array &$seen): ?array
    {
        if ($styleId === null || isset($seen[$styleId]) || !isset($styles[$styleId])) {
            return null;
        }

        $seen[$styleId] = true;
        $style = $styles[$styleId];
        $attrs = $this->resolveStyleParagraphMetadataAttrs($style['basedOn'], $styles, $seen);
        $styleAttrs = $style['paragraphMetadata'] ?? null;
        if (is_array($styleAttrs)) {
            $attrs = $this->mergeMetadataAttrs($attrs, $styleAttrs);
        }

        return $attrs;
    }

    /**
     * @param array{classes:list<string>, attributes:array<string, string>}|null $base
     * @param array{classes:list<string>, attributes:array<string, string>}|null $override
     * @return array{classes:list<string>, attributes:array<string, string>}|null
     */
    private function mergeMetadataAttrs(?array $base, ?array $override): ?array
    {
        if ($base === null) {
            return $override;
        }
        if ($override === null) {
            return $base;
        }

        $baseClasses = $this->removeOverriddenParagraphMetadataClasses(
            $base['classes'],
            $override['attributes'],
        );

        return [
            'classes' => array_values(array_unique([
                ...$baseClasses,
                ...$override['classes'],
            ])),
            'attributes' => array_replace($base['attributes'], $override['attributes']),
        ];
    }

    /**
     * @param list<string> $classes
     * @param array<string, string> $overrideAttributes
     * @return list<string>
     */
    private function removeOverriddenParagraphMetadataClasses(array $classes, array $overrideAttributes): array
    {
        $removeExact = [];
        $removePrefixes = [];

        if (isset($overrideAttributes['data-docx-paragraph-align'])) {
            $removeExact[] = 'docx-paragraph-align';
            $removePrefixes[] = 'docx-align-';
        }
        if (isset($overrideAttributes['data-docx-text-direction'])) {
            $removeExact[] = 'docx-text-direction';
            $removePrefixes[] = 'docx-text-direction-';
        }

        if ($removeExact === [] && $removePrefixes === []) {
            return $classes;
        }

        return array_values(array_filter(
            $classes,
            static function (string $class) use ($removeExact, $removePrefixes): bool {
                if (in_array($class, $removeExact, true)) {
                    return false;
                }

                foreach ($removePrefixes as $prefix) {
                    if (str_starts_with($class, $prefix)) {
                        return false;
                    }
                }

                return true;
            },
        ));
    }

    /**
     * @param list<string> $classes
     * @param array<string, string> $attributes
     */
    private function appendParagraphAlignmentMetadata(\DOMElement $properties, array &$classes, array &$attributes): void
    {
        $alignment = $this->firstChildElement($properties, self::WORDPROCESSINGML_NS, 'jc');
        if (!$alignment instanceof \DOMElement) {
            return;
        }

        $value = trim((string) ($this->wordAttr($alignment, 'val') ?? ''));
        if ($value === '') {
            return;
        }

        $classes[] = 'docx-paragraph-align';
        $suffix = $this->metadataClassSuffix($value);
        if ($suffix !== null) {
            $classes[] = 'docx-align-' . $suffix;
        }
        $attributes['data-docx-paragraph-align'] = $value;
    }

    /**
     * @param list<string> $classes
     * @param array<string, string> $attributes
     */
    private function appendParagraphSpacingMetadata(\DOMElement $properties, array &$classes, array &$attributes): void
    {
        $spacing = $this->firstChildElement($properties, self::WORDPROCESSINGML_NS, 'spacing');
        if (!$spacing instanceof \DOMElement) {
            return;
        }

        $spacingAttributes = [];
        foreach ([
            'before' => 'data-docx-spacing-before-twips',
            'after' => 'data-docx-spacing-after-twips',
            'line' => 'data-docx-spacing-line',
            'beforeLines' => 'data-docx-spacing-before-lines',
            'afterLines' => 'data-docx-spacing-after-lines',
        ] as $source => $target) {
            $value = $this->optionalIntWordAttr($spacing, $source);
            if ($value !== null) {
                $spacingAttributes[$target] = (string) $value;
            }
        }

        $lineRule = trim((string) ($this->wordAttr($spacing, 'lineRule') ?? ''));
        if ($lineRule !== '') {
            $spacingAttributes['data-docx-spacing-line-rule'] = $lineRule;
        }

        if ($spacingAttributes === []) {
            return;
        }

        $classes[] = 'docx-paragraph-spacing';
        $attributes += $spacingAttributes;
    }

    /**
     * @param list<string> $classes
     * @param array<string, string> $attributes
     */
    private function appendParagraphIndentMetadata(\DOMElement $properties, array &$classes, array &$attributes): void
    {
        $indent = $this->firstChildElement($properties, self::WORDPROCESSINGML_NS, 'ind');
        if (!$indent instanceof \DOMElement) {
            return;
        }

        $indentAttributes = [];
        foreach ([
            'left' => 'data-docx-indent-left-twips',
            'right' => 'data-docx-indent-right-twips',
            'start' => 'data-docx-indent-start-twips',
            'end' => 'data-docx-indent-end-twips',
            'firstLine' => 'data-docx-indent-first-line-twips',
            'hanging' => 'data-docx-indent-hanging-twips',
        ] as $source => $target) {
            $value = $this->optionalIntWordAttr($indent, $source);
            if ($value !== null) {
                $indentAttributes[$target] = (string) $value;
            }
        }

        if ($indentAttributes === []) {
            return;
        }

        $classes[] = 'docx-paragraph-indent';
        $attributes += $indentAttributes;
    }

    /**
     * @param list<string> $classes
     * @param array<string, string> $attributes
     */
    private function appendParagraphTabsMetadata(\DOMElement $properties, array &$classes, array &$attributes): void
    {
        $tabs = $this->firstChildElement($properties, self::WORDPROCESSINGML_NS, 'tabs');
        if (!$tabs instanceof \DOMElement) {
            return;
        }

        $tabStopAttributes = [];
        foreach ($tabs->childNodes as $child) {
            if (!$child instanceof \DOMElement || !$this->isWordElement($child, 'tab')) {
                continue;
            }

            $tabAttributes = [];
            $value = trim((string) ($this->wordAttr($child, 'val') ?? ''));
            if ($value !== '') {
                $tabAttributes['val'] = $value;
            }

            $position = $this->optionalIntWordAttr($child, 'pos');
            if ($position !== null) {
                $tabAttributes['pos-twips'] = (string) $position;
            }

            $leader = trim((string) ($this->wordAttr($child, 'leader') ?? ''));
            if ($leader !== '') {
                $tabAttributes['leader'] = $leader;
            }

            if ($tabAttributes === []) {
                continue;
            }

            $tabStopAttributes[] = $tabAttributes;
        }

        if ($tabStopAttributes === []) {
            return;
        }

        $classes[] = 'docx-paragraph-tabs';
        $attributes['data-docx-tab-stop-count'] = (string) count($tabStopAttributes);
        foreach ($tabStopAttributes as $zeroBasedIndex => $tabAttributes) {
            $index = $zeroBasedIndex + 1;
            foreach ($tabAttributes as $name => $tabValue) {
                $attributes['data-docx-tab-' . $index . '-' . $name] = $tabValue;
            }
        }
    }

    /**
     * @param list<string> $classes
     * @param array<string, string> $attributes
     */
    private function appendParagraphBorderMetadata(\DOMElement $properties, array &$classes, array &$attributes): void
    {
        $borders = $this->firstChildElement($properties, self::WORDPROCESSINGML_NS, 'pBdr');
        if (!$borders instanceof \DOMElement) {
            return;
        }

        $borderClasses = [];
        $borderAttributes = [];
        foreach (['top', 'left', 'bottom', 'right', 'between', 'bar'] as $edge) {
            $border = $this->firstChildElement($borders, self::WORDPROCESSINGML_NS, $edge);
            if (!$border instanceof \DOMElement) {
                continue;
            }

            $edgeAttributes = [];
            $value = strtolower(trim((string) ($this->wordAttr($border, 'val') ?? '')));
            if (in_array($value, ['none', 'nil', '0', 'false', 'off'], true)) {
                continue;
            }
            if ($value !== '') {
                $edgeAttributes['val'] = $value;
            }

            foreach ([
                'sz' => 'size-eighth-points',
                'space' => 'space-points',
                'color' => 'color',
                'themeColor' => 'theme-color',
                'themeTint' => 'theme-tint',
                'themeShade' => 'theme-shade',
                'frame' => 'frame',
                'shadow' => 'shadow',
            ] as $source => $target) {
                $metadata = trim((string) ($this->wordAttr($border, $source) ?? ''));
                if ($metadata !== '') {
                    $edgeAttributes[$target] = $metadata;
                }
            }

            if ($edgeAttributes === []) {
                continue;
            }

            $borderClasses[] = 'docx-border-' . $edge;
            $suffix = isset($edgeAttributes['val']) ? $this->metadataClassSuffix($edgeAttributes['val']) : null;
            if ($suffix !== null) {
                $borderClasses[] = 'docx-border-' . $edge . '-' . $suffix;
            }

            foreach ($edgeAttributes as $name => $metadata) {
                $borderAttributes['data-docx-border-' . $edge . '-' . $name] = $metadata;
            }
        }

        if ($borderAttributes === []) {
            return;
        }

        $classes[] = 'docx-paragraph-border';
        array_push($classes, ...$borderClasses);
        $attributes += $borderAttributes;
    }

    /**
     * @param list<string> $classes
     * @param array<string, string> $attributes
     */
    private function appendParagraphFrameMetadata(\DOMElement $properties, array &$classes, array &$attributes): void
    {
        $frame = $this->firstChildElement($properties, self::WORDPROCESSINGML_NS, 'framePr');
        if (!$frame instanceof \DOMElement) {
            return;
        }

        $frameAttributes = [];
        $dropCap = strtolower(trim((string) ($this->wordAttr($frame, 'dropCap') ?? '')));
        if ($dropCap !== '' && !in_array($dropCap, ['none', 'nil', '0', 'false', 'off'], true)) {
            $frameAttributes['drop-cap'] = $dropCap;
        }

        foreach ([
            'wrap' => 'wrap',
            'hAnchor' => 'horizontal-anchor',
            'vAnchor' => 'vertical-anchor',
            'xAlign' => 'horizontal-align',
            'yAlign' => 'vertical-align',
            'hRule' => 'height-rule',
        ] as $source => $target) {
            $value = trim((string) ($this->wordAttr($frame, $source) ?? ''));
            if ($value !== '') {
                $frameAttributes[$target] = $value;
            }
        }

        foreach ([
            'lines' => 'lines',
            'w' => 'width-twips',
            'h' => 'height-twips',
            'hSpace' => 'horizontal-space-twips',
            'vSpace' => 'vertical-space-twips',
            'x' => 'horizontal-position-twips',
            'y' => 'vertical-position-twips',
        ] as $source => $target) {
            $value = $this->optionalIntWordAttr($frame, $source);
            if ($value !== null) {
                $frameAttributes[$target] = (string) $value;
            }
        }

        if ($frame->hasAttributeNS(self::WORDPROCESSINGML_NS, 'anchorLock')) {
            $anchorLock = $this->onOffStringValue($this->wordAttr($frame, 'anchorLock'));
            if ($anchorLock !== null) {
                $frameAttributes['anchor-lock'] = $anchorLock ? 'true' : 'false';
            }
        }

        if ($frameAttributes === []) {
            return;
        }

        $classes[] = 'docx-paragraph-frame';
        if (isset($frameAttributes['drop-cap'])) {
            $classes[] = 'docx-paragraph-drop-cap';
            $suffix = $this->metadataClassSuffix($frameAttributes['drop-cap']);
            if ($suffix !== null) {
                $classes[] = 'docx-drop-cap-' . $suffix;
            }
        }

        if (isset($frameAttributes['wrap'])) {
            $suffix = $this->metadataClassSuffix($frameAttributes['wrap']);
            if ($suffix !== null) {
                $classes[] = 'docx-frame-wrap-' . $suffix;
            }
        }

        foreach ($frameAttributes as $name => $value) {
            $attributes['data-docx-frame-' . $name] = $value;
        }
    }

    /**
     * @param list<string> $classes
     * @param array<string, string> $attributes
     */
    private function appendParagraphPolicyMetadata(\DOMElement $properties, array &$classes, array &$attributes): void
    {
        foreach ([
            'keepLines' => ['keep-lines', 'docx-keep-lines'],
            'widowControl' => ['widow-control', 'docx-widow-control'],
            'contextualSpacing' => ['contextual-spacing', 'docx-contextual-spacing'],
            'mirrorIndents' => ['mirror-indents', 'docx-mirror-indents'],
            'suppressLineNumbers' => ['suppress-line-numbers', 'docx-suppress-line-numbers'],
            'suppressAutoHyphens' => ['suppress-auto-hyphens', 'docx-suppress-auto-hyphens'],
            'snapToGrid' => ['snap-to-grid', 'docx-snap-to-grid'],
        ] as $localName => [$attributeName, $className]) {
            $child = $this->firstChildElement($properties, self::WORDPROCESSINGML_NS, $localName);
            if (!$child instanceof \DOMElement) {
                continue;
            }

            $value = $this->wordAttr($child, 'val');
            $enabled = $value === null || $value === '' ? true : $this->onOffStringValue($value);
            if ($enabled === null) {
                continue;
            }

            $classes[] = 'docx-paragraph-policy';
            $classes[] = $enabled ? $className : $className . '-off';
            $attributes['data-docx-' . $attributeName] = $enabled ? 'true' : 'false';
        }
    }

    /**
     * @param array<string, AstNode> $referencedNotes
     * @param array{kind:string, startType:string}|null $activeProofError
     * @param list<AstNode> $activeProofErrorNodes
     * @param array{classes:list<string>, attributes:array<string, string>}|null $activePermissionRange
     * @param list<AstNode> $activePermissionRangeNodes
     * @param array{type:string, classes:list<string>, attributes:array<string, string>}|null $activeMoveRange
     * @param list<AstNode> $activeMoveRangeNodes
     * @return list<AstNode>
     */
    private function paragraphInlines(
        \DOMElement $paragraph,
        ZipPackage $package,
        ?OpcRelationships $relationships,
        array $referencedNotes,
        ?string &$activeCommentRangeId,
        ?array &$activeProofError,
        array &$activeProofErrorNodes,
        ?array &$activePermissionRange,
        array &$activePermissionRangeNodes,
        ?array &$activeMoveRange,
        array &$activeMoveRangeNodes
    ): array
    {
        $inlines = [];
        $activeCommentRangeNodes = [];
        $activeField = null;

        $emitInlineNodes = function (array $nodes) use (
            &$inlines,
            &$activeCommentRangeId,
            &$activeCommentRangeNodes,
            &$activeProofError,
            &$activeProofErrorNodes,
            &$activePermissionRange,
            &$activePermissionRangeNodes,
            &$activeMoveRange,
            &$activeMoveRangeNodes
        ): void {
            if ($nodes === []) {
                return;
            }

            if ($activeProofError !== null) {
                array_push($activeProofErrorNodes, ...$nodes);
                return;
            }

            if ($activePermissionRange !== null) {
                array_push($activePermissionRangeNodes, ...$nodes);
                return;
            }

            if ($activeMoveRange !== null) {
                array_push($activeMoveRangeNodes, ...$nodes);
                return;
            }

            if ($activeCommentRangeId !== null) {
                array_push($activeCommentRangeNodes, ...$nodes);
                return;
            }

            array_push($inlines, ...$nodes);
        };

        $emitClosedRangeNode = function (AstNode $node, string $closedRange) use (
            &$inlines,
            &$activeCommentRangeId,
            &$activeCommentRangeNodes,
            &$activeProofError,
            &$activeProofErrorNodes,
            &$activePermissionRange,
            &$activePermissionRangeNodes,
            &$activeMoveRange,
            &$activeMoveRangeNodes
        ): void {
            if ($closedRange !== 'proof' && $activeProofError !== null) {
                $activeProofErrorNodes[] = $node;
                return;
            }

            if ($closedRange !== 'permission' && $activePermissionRange !== null) {
                $activePermissionRangeNodes[] = $node;
                return;
            }

            if ($closedRange !== 'move' && $activeMoveRange !== null) {
                $activeMoveRangeNodes[] = $node;
                return;
            }

            if ($activeCommentRangeId !== null) {
                $activeCommentRangeNodes[] = $node;
                return;
            }

            $inlines[] = $node;
        };

        $closeProofError = function (?string $endType = null) use (&$activeProofError, &$activeProofErrorNodes, $emitInlineNodes): void {
            if ($activeProofError === null) {
                return;
            }

            $node = $this->proofErrorRangeNode($activeProofError, $activeProofErrorNodes, $endType);
            $activeProofError = null;
            $activeProofErrorNodes = [];
            if ($node instanceof AstNode) {
                $emitInlineNodes([$node]);
            }
        };

        $closePermissionRange = function (?string $endId = null) use (&$activePermissionRange, &$activePermissionRangeNodes, $emitInlineNodes): void {
            if ($activePermissionRange === null || !$this->permissionRangeEndMatches($activePermissionRange, $endId)) {
                return;
            }

            $node = $this->permissionRangeNode($activePermissionRange, $activePermissionRangeNodes);
            $activePermissionRange = null;
            $activePermissionRangeNodes = [];
            if ($node instanceof AstNode) {
                $emitInlineNodes([$node]);
            }
        };

        $closeMoveRange = function (?string $endType = null, ?string $endId = null) use (&$activeMoveRange, &$activeMoveRangeNodes, $emitInlineNodes): void {
            if ($activeMoveRange === null || !$this->moveRangeEndMatches($activeMoveRange, $endType, $endId)) {
                return;
            }

            $node = $this->moveRangeNode($activeMoveRange, $activeMoveRangeNodes);
            $activeMoveRange = null;
            $activeMoveRangeNodes = [];
            if ($node instanceof AstNode) {
                $emitInlineNodes([$node]);
            }
        };

        $flushOpenProofErrorSegment = function () use (&$activeProofError, &$activeProofErrorNodes, $emitClosedRangeNode): void {
            if ($activeProofError === null || $activeProofErrorNodes === []) {
                return;
            }

            $node = $this->proofErrorRangeNode($activeProofError, $activeProofErrorNodes, null);
            $activeProofErrorNodes = [];
            if ($node instanceof AstNode) {
                $emitClosedRangeNode($node, 'proof');
            }
        };

        $flushOpenPermissionRangeSegment = function () use (&$activePermissionRange, &$activePermissionRangeNodes, $emitClosedRangeNode): void {
            if ($activePermissionRange === null || $activePermissionRangeNodes === []) {
                return;
            }

            $node = $this->permissionRangeNode($activePermissionRange, $activePermissionRangeNodes);
            $activePermissionRangeNodes = [];
            if ($node instanceof AstNode) {
                $emitClosedRangeNode($node, 'permission');
            }
        };

        $flushOpenMoveRangeSegment = function () use (&$activeMoveRange, &$activeMoveRangeNodes, $emitClosedRangeNode): void {
            if ($activeMoveRange === null || $activeMoveRangeNodes === []) {
                return;
            }

            $node = $this->moveRangeNode($activeMoveRange, $activeMoveRangeNodes);
            $activeMoveRangeNodes = [];
            if ($node instanceof AstNode) {
                $emitClosedRangeNode($node, 'move');
            }
        };

        foreach ($paragraph->childNodes as $child) {
            if (!$child instanceof \DOMElement || $this->isWordElement($child, 'pPr')) {
                continue;
            }

            if ($activeField !== null) {
                $nodes = $this->consumeFieldElement($activeField, $child, $package, $relationships, $referencedNotes);
                if ($nodes !== null) {
                    $activeField = null;
                    $emitInlineNodes($nodes);
                }
                continue;
            }

            if ($this->startsComplexField($child)) {
                $activeField = $this->newFieldState();
                $this->consumeFieldElement($activeField, $child, $package, $relationships, $referencedNotes);
                continue;
            }

            if ($this->isWordElement($child, 'proofErr')) {
                $proofStart = $this->proofErrorRangeStart($child);
                if ($proofStart !== null) {
                    $closeProofError(null);
                    $activeProofError = $proofStart;
                    $activeProofErrorNodes = [];
                    continue;
                }

                $proofEnd = $this->proofErrorRangeEndType($child);
                if ($proofEnd !== null) {
                    $closeProofError($proofEnd);
                    continue;
                }
            }

            if ($this->isWordElement($child, 'permStart')) {
                $permissionRange = $this->permissionRangeAttrs($child);
                if ($permissionRange !== null) {
                    $closePermissionRange(null);
                    $activePermissionRange = $permissionRange;
                    $activePermissionRangeNodes = [];
                }
                continue;
            }

            if ($this->isWordElement($child, 'permEnd')) {
                $closePermissionRange($this->wordAttr($child, 'id'));
                continue;
            }

            if ($this->isWordElement($child, 'moveFromRangeStart')) {
                $closeMoveRange(null, null);
                $activeMoveRange = $this->moveRangeStartAttrs($child, 'move-from-range');
                $activeMoveRangeNodes = [];
                continue;
            }

            if ($this->isWordElement($child, 'moveToRangeStart')) {
                $closeMoveRange(null, null);
                $activeMoveRange = $this->moveRangeStartAttrs($child, 'move-to-range');
                $activeMoveRangeNodes = [];
                continue;
            }

            if ($this->isWordElement($child, 'moveFromRangeEnd')) {
                $closeMoveRange('move-from-range', $this->wordAttr($child, 'id'));
                continue;
            }

            if ($this->isWordElement($child, 'moveToRangeEnd')) {
                $closeMoveRange('move-to-range', $this->wordAttr($child, 'id'));
                continue;
            }

            if ($this->isWordElement($child, 'commentRangeStart')) {
                if ($activeCommentRangeId !== null) {
                    $this->appendCommentRangeSpan($inlines, $activeCommentRangeId, $activeCommentRangeNodes, $referencedNotes);
                }

                $activeCommentRangeId = $this->wordAttr($child, 'id');
                $activeCommentRangeNodes = [];
                continue;
            }

            if ($this->isWordElement($child, 'commentRangeEnd')) {
                $endId = $this->wordAttr($child, 'id');
                if ($activeCommentRangeId !== null && ($endId === null || $endId === $activeCommentRangeId)) {
                    $this->appendCommentRangeSpan($inlines, $activeCommentRangeId, $activeCommentRangeNodes, $referencedNotes);
                    $activeCommentRangeId = null;
                    $activeCommentRangeNodes = [];
                }
                continue;
            }

            if ($this->isWordElement($child, 'bookmarkStart')) {
                $nodes = $this->bookmarkStartNodes($child);
                $emitInlineNodes($nodes);
                continue;
            }

            if ($this->isWordElement($child, 'bookmarkEnd')) {
                continue;
            }

            $nodes = $this->inlineNodes($child, $package, $relationships, $referencedNotes);
            $emitInlineNodes($nodes);
        }

        if ($activeField !== null) {
            $nodes = $this->fieldResultNodes($activeField);
            $emitInlineNodes($nodes);
        }
        $flushOpenProofErrorSegment();
        $flushOpenPermissionRangeSegment();
        $flushOpenMoveRangeSegment();
        if ($activeCommentRangeId !== null) {
            $this->appendCommentRangeSpan($inlines, $activeCommentRangeId, $activeCommentRangeNodes, $referencedNotes);
        }

        return $this->coalesceTextNodes($inlines);
    }

    /**
     * @return array{kind:string, startType:string}|null
     */
    private function proofErrorRangeStart(\DOMElement $proofErr): ?array
    {
        $type = $this->wordAttr($proofErr, 'type');
        $kind = match ($type) {
            'spellStart' => 'spelling',
            'gramStart' => 'grammar',
            default => null,
        };

        if ($kind === null || $type === null) {
            return null;
        }

        return [
            'kind' => $kind,
            'startType' => $type,
        ];
    }

    private function proofErrorRangeEndType(\DOMElement $proofErr): ?string
    {
        $type = $this->wordAttr($proofErr, 'type');

        return in_array($type, ['spellEnd', 'gramEnd'], true) ? $type : null;
    }

    /**
     * @param array{kind:string, startType:string} $range
     * @param list<AstNode> $children
     */
    private function proofErrorRangeNode(array $range, array $children, ?string $endType): ?AstNode
    {
        $children = $this->coalesceTextNodes($children);
        if ($children === []) {
            return null;
        }

        $attributes = [
            'data-docx-proof-error' => $range['kind'],
            'data-docx-proof-start' => $range['startType'],
        ];
        if ($endType !== null && $endType !== '') {
            $attributes['data-docx-proof-end'] = $endType;
        }

        return new AstNode('span', [
            'classes' => ['docx-proof-error', 'docx-proof-' . $range['kind']],
            'attributes' => $attributes,
        ], $children);
    }

    /**
     * @return array{classes:list<string>, attributes:array<string, string>}|null
     */
    private function permissionRangeAttrs(\DOMElement $permissionStart): ?array
    {
        $attributes = [];
        $id = $this->wordAttr($permissionStart, 'id');
        if ($id !== null && $id !== '') {
            $attributes['data-docx-permission-id'] = $id;
        }

        $classes = ['docx-permission-range'];
        $group = $this->wordAttr($permissionStart, 'edGrp');
        if ($group !== null && $group !== '') {
            $attributes['data-docx-permission-group'] = $group;
            $classes[] = 'docx-permission-group';
        }

        $user = $this->wordAttr($permissionStart, 'user');
        if ($user !== null && $user !== '') {
            $attributes['data-docx-permission-user'] = $user;
            $classes[] = 'docx-permission-user';
        }

        if ($attributes === []) {
            return null;
        }

        return [
            'classes' => array_values(array_unique($classes)),
            'attributes' => $attributes,
        ];
    }

    /**
     * @param array{classes:list<string>, attributes:array<string, string>} $range
     */
    private function permissionRangeEndMatches(array $range, ?string $endId): bool
    {
        $startId = $range['attributes']['data-docx-permission-id'] ?? null;

        return $endId === null || $endId === '' || $startId === null || $startId === $endId;
    }

    /**
     * @param array{classes:list<string>, attributes:array<string, string>} $range
     * @param list<AstNode> $children
     */
    private function permissionRangeNode(array $range, array $children): ?AstNode
    {
        $children = $this->coalesceTextNodes($children);
        if ($children === []) {
            return null;
        }

        return new AstNode('span', $range, $children);
    }

    /**
     * @return array{type:string, classes:list<string>, attributes:array<string, string>}
     */
    private function moveRangeStartAttrs(\DOMElement $moveStart, string $type): array
    {
        $attributes = [
            'data-docx-change' => $type,
        ];

        foreach ([
            'id' => 'data-docx-change-id',
            'author' => 'data-docx-author',
            'date' => 'data-docx-date',
            'name' => 'data-docx-move-range-name',
        ] as $wordAttr => $htmlAttr) {
            $value = $this->wordAttr($moveStart, $wordAttr);
            if ($value !== null && $value !== '') {
                $attributes[$htmlAttr] = $value;
            }
        }

        return [
            'type' => $type,
            'classes' => ['docx-' . $type],
            'attributes' => $attributes,
        ];
    }

    /**
     * @param array{type:string, classes:list<string>, attributes:array<string, string>} $range
     */
    private function moveRangeEndMatches(array $range, ?string $endType, ?string $endId): bool
    {
        if ($endType !== null && $range['type'] !== $endType) {
            return false;
        }

        $startId = $range['attributes']['data-docx-change-id'] ?? null;

        return $endId === null || $endId === '' || $startId === null || $startId === $endId;
    }

    /**
     * @param array{type:string, classes:list<string>, attributes:array<string, string>} $range
     * @param list<AstNode> $children
     */
    private function moveRangeNode(array $range, array $children): ?AstNode
    {
        if ($range['type'] === 'move-from-range') {
            return null;
        }

        $children = $this->coalesceTextNodes($children);
        if ($children === []) {
            return null;
        }

        return new AstNode('span', [
            'classes' => $range['classes'],
            'attributes' => $range['attributes'],
        ], $children);
    }

    /**
     * @return array{instruction:string, collectingResult:bool, resultNodes:list<AstNode>, formField:array{classes:list<string>, attributes:array<string, string>}|null}
     */
    private function newFieldState(): array
    {
        return [
            'instruction' => '',
            'collectingResult' => false,
            'resultNodes' => [],
            'formField' => null,
        ];
    }

    private function startsComplexField(\DOMElement $element): bool
    {
        return $this->isWordElement($element, 'r') && $this->runFieldCharType($element) === 'begin';
    }

    /**
     * @param array{instruction:string, collectingResult:bool, resultNodes:list<AstNode>, formField:array{classes:list<string>, attributes:array<string, string>}|null} $field
     * @param array<string, AstNode> $referencedNotes
     * @return list<AstNode>|null finalized field nodes, or null while the field remains active
     */
    private function consumeFieldElement(
        array &$field,
        \DOMElement $element,
        ZipPackage $package,
        ?OpcRelationships $relationships,
        array $referencedNotes
    ): ?array {
        $fieldCharType = $this->isWordElement($element, 'r') ? $this->runFieldCharType($element) : null;
        if ($fieldCharType === 'begin') {
            $field['instruction'] .= $this->runInstructionText($element);
            $formField = $this->runFormFieldAttrs($element);
            if ($formField !== null) {
                $field['formField'] = $formField;
            }

            return null;
        }
        if ($fieldCharType === 'separate') {
            $field['instruction'] .= $this->runInstructionText($element);
            $field['collectingResult'] = true;
            return null;
        }
        if ($fieldCharType === 'end') {
            $nodes = $this->fieldResultNodes($field);
            $field = $this->newFieldState();

            return $nodes;
        }

        if (!$field['collectingResult']) {
            $field['instruction'] .= $this->fieldInstructionText($element);
            return null;
        }

        array_push($field['resultNodes'], ...$this->inlineNodes($element, $package, $relationships, $referencedNotes));

        return null;
    }

    /**
     * @param array{instruction:string, collectingResult:bool, resultNodes:list<AstNode>, formField:array{classes:list<string>, attributes:array<string, string>}|null} $field
     * @return list<AstNode>
     */
    private function fieldResultNodes(array $field): array
    {
        $resultNodes = $this->coalesceTextNodes($field['resultNodes']);
        if ($resultNodes === []) {
            return [];
        }

        $attrs = $this->hyperlinkFieldAttrs($field['instruction']);
        if ($attrs === null) {
            $attrs = $this->fieldSpanAttrs($field['instruction'], $field['formField']);
            if ($attrs === null) {
                return $resultNodes;
            }

            return [new AstNode('span', $attrs, $resultNodes)];
        }

        return [new AstNode('link', $attrs, $resultNodes)];
    }

    /**
     * @return list<AstNode>
     */
    private function bookmarkStartNodes(\DOMElement $bookmark): array
    {
        $name = $this->wordAttr($bookmark, 'name');
        if ($name === null || $name === '' || $name === '_GoBack') {
            return [];
        }

        $attrs = [
            'id' => $name,
            'classes' => ['anchor'],
        ];

        $columnFirst = $this->wordAttr($bookmark, 'colFirst');
        $columnLast = $this->wordAttr($bookmark, 'colLast');
        if (($columnFirst !== null && $columnFirst !== '') || ($columnLast !== null && $columnLast !== '')) {
            $attributes = [];
            $bookmarkId = $this->wordAttr($bookmark, 'id');
            if ($bookmarkId !== null && $bookmarkId !== '') {
                $attributes['data-docx-bookmark-id'] = $bookmarkId;
            }
            $attributes['data-docx-bookmark-name'] = $name;
            if ($columnFirst !== null && $columnFirst !== '') {
                $attributes['data-docx-bookmark-col-first'] = $columnFirst;
            }
            if ($columnLast !== null && $columnLast !== '') {
                $attributes['data-docx-bookmark-col-last'] = $columnLast;
            }

            $attrs['classes'] = ['anchor', 'docx-bookmark', 'docx-bookmark-column-range'];
            $attrs['attributes'] = $attributes;
        }

        return [new AstNode('span', $attrs)];
    }

    /**
     * @param list<AstNode> $inlines
     * @param list<AstNode> $children
     * @param array<string, AstNode> $referencedNotes
     */
    private function appendCommentRangeSpan(array &$inlines, ?string $commentId, array $children, array $referencedNotes): void
    {
        $children = $this->coalesceTextNodes($children);
        if ($commentId === null || $commentId === '' || $children === []) {
            array_push($inlines, ...$children);
            return;
        }

        $inlines[] = new AstNode('span', $this->commentRangeSpanAttrs($commentId, $referencedNotes), $children);
    }

    /**
     * @param array<string, AstNode> $referencedNotes
     * @return array{classes:list<string>, attributes:array<string, string>}
     */
    private function commentRangeSpanAttrs(string $commentId, array $referencedNotes): array
    {
        $attributes = [
            'data-docx-comment-id' => $commentId,
        ];

        $comment = $referencedNotes['comment:' . $commentId] ?? null;
        if ($comment instanceof AstNode) {
            foreach ([
                'author' => 'data-docx-comment-author',
                'initials' => 'data-docx-comment-initials',
                'date' => 'data-docx-comment-date',
                'commentParaId' => 'data-docx-comment-para-id',
                'commentParentParaId' => 'data-docx-comment-parent-para-id',
            ] as $source => $target) {
                $value = $comment->attr($source);
                if (is_string($value) && $value !== '') {
                    $attributes[$target] = $value;
                }
            }

            $resolved = $comment->attr('commentResolved');
            if (is_bool($resolved)) {
                $attributes['data-docx-comment-resolved'] = $resolved ? 'true' : 'false';
            }
        }

        return [
            'classes' => ['docx-comment-range'],
            'attributes' => $attributes,
        ];
    }

    /**
     * @param array<string, AstNode> $referencedNotes
     * @return list<AstNode>
     */
    private function inlineNodes(\DOMElement $element, ZipPackage $package, ?OpcRelationships $relationships, array $referencedNotes): array
    {
        if ($this->isWordElement($element, 'r')) {
            return $this->runNodes($element, $package, $relationships, $referencedNotes);
        }

        if ($this->isWordElement($element, 'hyperlink')) {
            return [$this->hyperlinkNode($element, $package, $relationships, $referencedNotes)];
        }

        if ($this->isWordElement($element, 'fldSimple')) {
            return $this->simpleFieldNodes($element, $package, $relationships, $referencedNotes);
        }

        if ($this->isWordElement($element, 'subDoc')) {
            return $this->subdocumentReferenceNodes($element, $package, $relationships);
        }

        if ($this->isMarkupCompatibilityElement($element, 'AlternateContent')) {
            return $this->alternateContentInlineNodes($element, $package, $relationships, $referencedNotes);
        }

        if ($this->isMathElement($element, 'oMath')) {
            return $this->mathNodes($element, false);
        }

        if ($this->isMathElement($element, 'oMathPara')) {
            return $this->mathNodes($element, true);
        }

        if ($this->isWordElement($element, 'customXml')) {
            return $this->customXmlInlineNodes($element, $package, $relationships, $referencedNotes);
        }

        if ($this->isWordElement($element, 'sdt')) {
            return $this->structuredDocumentTagInlineNodes($element, $package, $relationships, $referencedNotes);
        }

        if ($this->isWordElement($element, 'dir') || $this->isWordElement($element, 'bdo')) {
            return $this->directionalInlineNodes($element, $package, $relationships, $referencedNotes);
        }

        if ($this->isWordElement($element, 'ins')) {
            return $this->trackedAcceptedChangeNodes($element, $package, $relationships, $referencedNotes, 'insertion');
        }

        if ($this->isWordElement($element, 'moveTo')) {
            return $this->trackedAcceptedChangeNodes($element, $package, $relationships, $referencedNotes, 'move-to');
        }

        if ($this->isWordElement($element, 'smartTag')) {
            return $this->smartTagNodes($element, $package, $relationships, $referencedNotes);
        }

        if ($this->isWordElement($element, 'del') || $this->isWordElement($element, 'moveFrom')) {
            return [];
        }

        return [];
    }

    /**
     * @param array<string, AstNode> $referencedNotes
     * @return list<AstNode>
     */
    private function alternateContentInlineNodes(
        \DOMElement $alternateContent,
        ZipPackage $package,
        ?OpcRelationships $relationships,
        array $referencedNotes
    ): array {
        $selection = $this->alternateContentSelection($alternateContent);
        if (!$selection instanceof \DOMElement) {
            return [];
        }

        $nodes = [];
        foreach ($selection->childNodes as $child) {
            if (!$child instanceof \DOMElement) {
                continue;
            }

            if ($this->isWordElement($child, 'drawing')) {
                array_push($nodes, ...$this->drawingNodes($child, $package, $relationships));
                continue;
            }

            if ($this->isWordElement($child, 'pict')) {
                array_push($nodes, ...$this->vmlImageNodes($child, $package, $relationships));
                continue;
            }

            array_push($nodes, ...$this->inlineNodes($child, $package, $relationships, $referencedNotes));
        }

        return $this->coalesceTextNodes($nodes);
    }

    /**
     * @param array<string, AstNode> $referencedNotes
     * @return list<AstNode>
     */
    private function structuredDocumentTagInlineNodes(
        \DOMElement $sdt,
        ZipPackage $package,
        ?OpcRelationships $relationships,
        array $referencedNotes
    ): array {
        $content = $this->structuredDocumentTagContent($sdt);
        if (!$content instanceof \DOMElement) {
            return [];
        }

        $children = $this->coalesceTextNodes($this->inlineContainerNodes($content, $package, $relationships, $referencedNotes));
        if ($children === []) {
            return [];
        }

        return [new AstNode('span', $this->structuredDocumentTagAttrs($sdt), $children)];
    }

    private function structuredDocumentTagContent(\DOMElement $sdt): ?\DOMElement
    {
        return $this->firstChildElement($sdt, self::WORDPROCESSINGML_NS, 'sdtContent');
    }

    /**
     * @param array<string, AstNode> $referencedNotes
     * @param array<string, array{name:?string, basedOn:?string, headingLevel:?int, numPr:?array{numId:?string, level:?int}}> $styles
     * @param array<string, array<int, array{ordered:bool, style:string, delimiter:string, start:int, format:string}>> $numbering
     * @return list<AstNode>
     */
    private function customXmlBlocks(
        \DOMElement $customXml,
        ZipPackage $package,
        ?OpcRelationships $relationships,
        array $referencedNotes,
        array $styles,
        array $numbering
    ): array {
        $blocks = $this->blockContainerChildren($customXml, $package, $relationships, $referencedNotes, $styles, $numbering);
        if ($blocks !== []) {
            return [new AstNode('div', $this->customXmlAttrs($customXml), $blocks)];
        }

        $inlines = $this->coalesceTextNodes($this->inlineContainerNodes($customXml, $package, $relationships, $referencedNotes));
        if ($inlines === []) {
            return [];
        }

        return [new AstNode('paragraph', [], [new AstNode('span', $this->customXmlAttrs($customXml), $inlines)])];
    }

    /**
     * @param array<string, AstNode> $referencedNotes
     * @return list<AstNode>
     */
    private function customXmlInlineNodes(
        \DOMElement $customXml,
        ZipPackage $package,
        ?OpcRelationships $relationships,
        array $referencedNotes
    ): array {
        $children = $this->coalesceTextNodes($this->inlineContainerNodes($customXml, $package, $relationships, $referencedNotes));
        if ($children === []) {
            return [];
        }

        return [new AstNode('span', $this->customXmlAttrs($customXml), $children)];
    }

    /**
     * @return array{classes:list<string>, attributes:array<string, string>}
     */
    private function customXmlAttrs(\DOMElement $customXml): array
    {
        $attributes = [];
        foreach ([
            'uri' => 'data-docx-custom-xml-uri',
            'element' => 'data-docx-custom-xml-element',
        ] as $wordAttr => $htmlAttr) {
            $value = $this->wordAttr($customXml, $wordAttr);
            if ($value !== null && $value !== '') {
                $attributes[$htmlAttr] = $value;
            }
        }

        $properties = $this->firstChildElement($customXml, self::WORDPROCESSINGML_NS, 'customXmlPr');
        if ($properties instanceof \DOMElement) {
            foreach ($properties->childNodes as $child) {
                if (!$child instanceof \DOMElement || !$this->isWordElement($child, 'attr')) {
                    continue;
                }

                $name = $this->wordAttr($child, 'name');
                $key = $name === null ? null : $this->customXmlPropertyKey($name);
                if ($key === null) {
                    continue;
                }

                $value = $this->wordAttr($child, 'val');
                if ($value !== null && $value !== '') {
                    $attributes['data-docx-custom-xml-prop-' . $key] = $value;
                }

                $uri = $this->wordAttr($child, 'uri');
                if ($uri !== null && $uri !== '') {
                    $attributes['data-docx-custom-xml-prop-' . $key . '-uri'] = $uri;
                }
            }
        }

        return [
            'classes' => ['docx-custom-xml'],
            'attributes' => $attributes,
        ];
    }

    private function customXmlPropertyKey(string $name): ?string
    {
        return $this->xmlMetadataPropertyKey($name);
    }

    /**
     * @return array{classes:list<string>, attributes:array<string, string>}
     */
    private function structuredDocumentTagAttrs(\DOMElement $sdt): array
    {
        $properties = $this->firstChildElement($sdt, self::WORDPROCESSINGML_NS, 'sdtPr');
        $attributes = [];
        $type = null;

        if ($properties instanceof \DOMElement) {
            foreach ([
                'id' => 'data-docx-sdt-id',
                'alias' => 'data-docx-sdt-alias',
                'tag' => 'data-docx-sdt-tag',
                'lock' => 'data-docx-sdt-lock',
            ] as $localName => $attributeName) {
                $value = $this->structuredDocumentTagPropertyValue($properties, $localName);
                if ($value !== null && $value !== '') {
                    $attributes[$attributeName] = $value;
                }
            }

            $type = $this->structuredDocumentTagType($properties);
            if ($type !== null) {
                $attributes['data-docx-sdt-type'] = $type;
            }

            $placeholder = $this->structuredDocumentTagPlaceholder($properties);
            if ($placeholder !== null && $placeholder !== '') {
                $attributes['data-docx-sdt-placeholder'] = $placeholder;
            }

            $dataBinding = $this->firstChildElement($properties, self::WORDPROCESSINGML_NS, 'dataBinding');
            if ($dataBinding instanceof \DOMElement) {
                $storeItemId = null;
                foreach ([
                    'xpath' => 'data-docx-sdt-xpath',
                    'storeItemID' => 'data-docx-sdt-store-item-id',
                ] as $localName => $attributeName) {
                    $value = $this->wordAttr($dataBinding, $localName);
                    if ($value !== null && $value !== '') {
                        $attributes[$attributeName] = $value;
                        if ($localName === 'storeItemID') {
                            $storeItemId = $value;
                        }
                    }
                }

                foreach ($this->structuredDocumentTagPrefixMappingAttrs($dataBinding) as $name => $value) {
                    $attributes[$name] = $value;
                }

                if ($storeItemId !== null) {
                    foreach ($this->structuredDocumentTagCustomXmlStoreAttrs($storeItemId) as $name => $value) {
                        $attributes[$name] = $value;
                    }
                }
            }

            foreach ($this->structuredDocumentTagDocPartAttrs($properties) as $name => $value) {
                $attributes[$name] = $value;
            }

            foreach ($this->structuredDocumentTagFormControlAttrs($properties) as $name => $value) {
                $attributes[$name] = $value;
            }

            foreach ($this->structuredDocumentTagRepeatingSectionAttrs($properties) as $name => $value) {
                $attributes[$name] = $value;
            }
        }

        $classes = ['docx-content-control'];
        if ($type !== null && $type !== '') {
            $classes[] = 'docx-content-control-' . $type;
        }

        return [
            'classes' => $classes,
            'attributes' => $attributes,
        ];
    }

    /**
     * @return array<string, string>
     */
    private function structuredDocumentTagPrefixMappingAttrs(\DOMElement $dataBinding): array
    {
        $prefixMappings = $this->wordAttr($dataBinding, 'prefixMappings');
        if ($prefixMappings === null || $prefixMappings === '') {
            return [];
        }

        $attributes = [
            'data-docx-sdt-prefix-mappings' => $prefixMappings,
        ];

        $mappings = $this->parseStructuredDocumentTagPrefixMappings($prefixMappings);
        if ($mappings === []) {
            return $attributes;
        }

        $attributes['data-docx-sdt-prefix-count'] = (string) count($mappings);
        foreach ($mappings as $index => $mapping) {
            $prefix = 'data-docx-sdt-prefix-' . ($index + 1);
            $attributes[$prefix . '-name'] = $mapping['name'];
            $attributes[$prefix . '-uri'] = $mapping['uri'];
        }

        return $attributes;
    }

    /**
     * @return list<array{name:string, uri:string}>
     */
    private function parseStructuredDocumentTagPrefixMappings(string $prefixMappings): array
    {
        if (preg_match_all('/(?:^|\s)xmlns(?::([A-Za-z_][A-Za-z0-9._-]*))?\s*=\s*([\'"])(.*?)\2/s', $prefixMappings, $matches, PREG_SET_ORDER) === false) {
            return [];
        }

        $mappings = [];
        foreach ($matches as $match) {
            $uri = $match[3] ?? '';
            if ($uri === '') {
                continue;
            }

            $name = $match[1] ?? '';
            $mappings[] = [
                'name' => $name === '' ? 'default' : $name,
                'uri' => $uri,
            ];
        }

        return $mappings;
    }

    /**
     * @return array<string, string>
     */
    private function structuredDocumentTagCustomXmlStoreAttrs(string $storeItemId): array
    {
        $item = $this->currentCustomXmlPartsByStoreItemId[$this->customXmlStoreLookupKey($storeItemId)] ?? null;
        if (!is_array($item)) {
            if ($this->currentCustomXmlStoreItems === []) {
                return [];
            }

            $issues = $this->customXmlStoreBindingIssues($storeItemId);
            $attributes = [
                'data-docx-sdt-custom-xml-bound' => 'false',
                'data-docx-sdt-custom-xml-requested-store-item-id' => $storeItemId,
                'data-docx-sdt-custom-xml-store-item-count' => (string) count($this->currentCustomXmlStoreItems),
            ];
            if ($issues !== []) {
                $attributes['data-docx-sdt-custom-xml-issue-count'] = (string) count($issues);
                $attributes['data-docx-sdt-custom-xml-issues'] = implode(' ', $issues);
            }

            return $attributes;
        }

        $attributes = [
            'data-docx-sdt-custom-xml-bound' => 'true',
        ];

        foreach ([
            'targetPart' => 'data-docx-sdt-custom-xml-part',
            'contentType' => 'data-docx-sdt-custom-xml-content-type',
            'propertiesPart' => 'data-docx-sdt-custom-xml-properties-part',
            'rootName' => 'data-docx-sdt-custom-xml-root-name',
            'rootNamespace' => 'data-docx-sdt-custom-xml-root-namespace',
            'textPreview' => 'data-docx-sdt-custom-xml-text-preview',
        ] as $source => $target) {
            $value = $item[$source] ?? null;
            if (is_string($value) && $value !== '') {
                $attributes[$target] = $value;
            }
        }

        if (isset($item['bytes']) && is_int($item['bytes'])) {
            $attributes['data-docx-sdt-custom-xml-bytes'] = (string) $item['bytes'];
        }

        $schemaRefs = $item['schemaRefs'] ?? [];
        if (is_array($schemaRefs) && $schemaRefs !== []) {
            $attributes['data-docx-sdt-custom-xml-schema-ref-count'] = (string) count($schemaRefs);
            foreach (array_values($schemaRefs) as $index => $schemaRef) {
                if (!is_string($schemaRef) || $schemaRef === '') {
                    continue;
                }

                $attributes['data-docx-sdt-custom-xml-schema-ref-' . ($index + 1) . '-uri'] = $schemaRef;
            }
        }

        return $attributes;
    }

    /**
     * @return list<string>
     */
    private function customXmlStoreBindingIssues(string $storeItemId): array
    {
        $issues = ['missing-custom-xml-store-item'];
        foreach ($this->currentCustomXmlStoreItems as $item) {
            foreach (['issues', 'propertiesIssues'] as $key) {
                $itemIssues = $item[$key] ?? [];
                if (!is_array($itemIssues)) {
                    continue;
                }

                foreach ($itemIssues as $issue) {
                    if (is_string($issue) && $issue !== '') {
                        $issues[] = $issue;
                    }
                }
            }
        }

        return array_values(array_unique($issues));
    }

    /**
     * @return array<string, string>
     */
    private function structuredDocumentTagDocPartAttrs(\DOMElement $properties): array
    {
        foreach ([
            'docPartObj' => 'object',
            'docPartList' => 'list',
        ] as $localName => $kind) {
            $docPart = $this->firstChildElement($properties, self::WORDPROCESSINGML_NS, $localName);
            if (!$docPart instanceof \DOMElement) {
                continue;
            }

            $attributes = [
                'data-docx-sdt-doc-part-kind' => $kind,
            ];
            $gallery = $this->wordChildValue($docPart, 'docPartGallery');
            if ($gallery !== null) {
                $attributes['data-docx-sdt-doc-part-gallery'] = $gallery;
            }

            $category = $this->wordChildValue($docPart, 'docPartCategory');
            if ($category !== null) {
                $attributes['data-docx-sdt-doc-part-category'] = $category;
            }

            $unique = $this->firstChildElement($docPart, self::WORDPROCESSINGML_NS, 'docPartUnique');
            if ($unique instanceof \DOMElement) {
                $attributes['data-docx-sdt-doc-part-unique'] = $this->onOffWordAttr($unique, 'val', true) ? 'true' : 'false';
            }

            return $attributes;
        }

        return [];
    }

    /**
     * @return array<string, string>
     */
    private function structuredDocumentTagFormControlAttrs(\DOMElement $properties): array
    {
        $attributes = [];

        $checkBox = $this->firstChildElement($properties, self::WORDPROCESSINGML_NS, 'checkBox');
        if ($checkBox instanceof \DOMElement) {
            $checked = $this->firstChildElement($checkBox, self::WORDPROCESSINGML_NS, 'checked');
            if ($checked instanceof \DOMElement) {
                $attributes['data-docx-sdt-checkbox-checked'] = $this->onOffWordAttr($checked, 'val', true) ? 'true' : 'false';
            }

            foreach ([
                'checkedState' => 'checked-state',
                'uncheckedState' => 'unchecked-state',
            ] as $localName => $attributePrefix) {
                $state = $this->firstChildElement($checkBox, self::WORDPROCESSINGML_NS, $localName);
                if (!$state instanceof \DOMElement) {
                    continue;
                }

                foreach ([
                    'val' => 'value',
                    'font' => 'font',
                ] as $source => $target) {
                    $value = $this->wordAttr($state, $source);
                    if ($value !== null && $value !== '') {
                        $attributes['data-docx-sdt-checkbox-' . $attributePrefix . '-' . $target] = $value;
                    }
                }
            }
        }

        foreach ([
            'dropDownList' => 'drop-down-list',
            'comboBox' => 'combo-box',
        ] as $localName => $kind) {
            $list = $this->firstChildElement($properties, self::WORDPROCESSINGML_NS, $localName);
            if (!$list instanceof \DOMElement) {
                continue;
            }

            $attributes['data-docx-sdt-list-kind'] = $kind;
            $lastValue = $this->structuredDocumentTagPropertyValue($list, 'lastValue');
            if ($lastValue !== null && $lastValue !== '') {
                $attributes['data-docx-sdt-list-last-value'] = $lastValue;
            }

            $index = 0;
            foreach ($list->childNodes as $child) {
                if (!$child instanceof \DOMElement || !$this->isWordElement($child, 'listItem')) {
                    continue;
                }

                $index++;
                foreach ([
                    'displayText' => 'display-text',
                    'value' => 'value',
                ] as $source => $target) {
                    $value = $this->wordAttr($child, $source);
                    if ($value !== null && $value !== '') {
                        $attributes['data-docx-sdt-list-item-' . $index . '-' . $target] = $value;
                    }
                }
            }

            if ($index > 0) {
                $attributes['data-docx-sdt-list-item-count'] = (string) $index;
            }

            break;
        }

        $date = $this->firstChildElement($properties, self::WORDPROCESSINGML_NS, 'date');
        if ($date instanceof \DOMElement) {
            $fullDate = $this->wordAttr($date, 'fullDate');
            if ($fullDate !== null && $fullDate !== '') {
                $attributes['data-docx-sdt-date-full-date'] = $fullDate;
            }

            foreach ([
                'dateFormat' => 'format',
                'lid' => 'lang',
                'storeMappedDataAs' => 'store-mapped-data-as',
                'calendar' => 'calendar',
            ] as $localName => $target) {
                $value = $this->structuredDocumentTagPropertyValue($date, $localName);
                if ($value !== null && $value !== '') {
                    $attributes['data-docx-sdt-date-' . $target] = $value;
                }
            }
        }

        return $attributes;
    }

    /**
     * @return array<string, string>
     */
    private function structuredDocumentTagRepeatingSectionAttrs(\DOMElement $properties): array
    {
        $attributes = [];
        $section = $this->firstStructuredDocumentTagPropertyElement($properties, 'repeatingSection');
        if ($section instanceof \DOMElement) {
            $titleElement = $this->firstStructuredDocumentTagPropertyElement($section, 'sectionTitle');
            $title = $titleElement instanceof \DOMElement
                ? $this->wordOrExtensionAttr($titleElement, 'val')
                : $this->wordOrExtensionAttr($section, 'sectionTitle');
            if ($title !== null && $title !== '') {
                $attributes['data-docx-sdt-repeating-section-title'] = $title;
            }

            $insertDeleteLock = $this->firstStructuredDocumentTagPropertyElement($section, 'doNotAllowInsertDeleteSection');
            if ($insertDeleteLock instanceof \DOMElement) {
                $value = $this->wordOrExtensionAttr($insertDeleteLock, 'val');
                $parsed = $this->onOffStringValue($value);
                $attributes['data-docx-sdt-repeating-section-do-not-allow-insert-delete'] = ($parsed ?? true) ? 'true' : 'false';
            }
        }

        $item = $this->firstStructuredDocumentTagPropertyElement($properties, 'repeatingSectionItem');
        if ($item instanceof \DOMElement) {
            $attributes['data-docx-sdt-repeating-section-item'] = 'true';
        }

        return $attributes;
    }

    private function structuredDocumentTagPropertyValue(\DOMElement $properties, string $localName): ?string
    {
        $child = $this->firstChildElement($properties, self::WORDPROCESSINGML_NS, $localName);
        if (!$child instanceof \DOMElement) {
            return null;
        }

        $value = $this->wordAttr($child, 'val');

        return $value === null || $value === '' ? null : $value;
    }

    private function structuredDocumentTagType(\DOMElement $properties): ?string
    {
        foreach ([
            'richText' => 'rich-text',
            'text' => 'text',
            'comboBox' => 'combo-box',
            'dropDownList' => 'drop-down-list',
            'date' => 'date',
            'checkBox' => 'checkbox',
            'picture' => 'picture',
            'repeatingSection' => 'repeating-section',
            'repeatingSectionItem' => 'repeating-section-item',
            'group' => 'group',
            'docPartObj' => 'doc-part',
            'docPartList' => 'doc-part-list',
            'citation' => 'citation',
            'bibliography' => 'bibliography',
            'equation' => 'equation',
        ] as $localName => $type) {
            if ($this->firstStructuredDocumentTagPropertyElement($properties, $localName) instanceof \DOMElement) {
                return $type;
            }
        }

        return null;
    }

    private function firstStructuredDocumentTagPropertyElement(\DOMElement $properties, string $localName): ?\DOMElement
    {
        foreach ([self::WORDPROCESSINGML_NS, self::WORDPROCESSINGML_2012_NS, self::WORDPROCESSINGML_2010_NS] as $namespace) {
            $element = $this->firstChildElement($properties, $namespace, $localName);
            if ($element instanceof \DOMElement) {
                return $element;
            }
        }

        return null;
    }

    private function wordOrExtensionAttr(\DOMElement $element, string $localName): ?string
    {
        $value = $this->wordAttr($element, $localName);
        if ($value !== null && $value !== '') {
            return $value;
        }

        return $this->wordExtensionAttr($element, $localName);
    }

    private function structuredDocumentTagPlaceholder(\DOMElement $properties): ?string
    {
        $placeholder = $this->firstChildElement($properties, self::WORDPROCESSINGML_NS, 'placeholder');
        if (!$placeholder instanceof \DOMElement) {
            return null;
        }

        return $this->structuredDocumentTagPropertyValue($placeholder, 'docPart');
    }

    /**
     * @param array<string, AstNode> $referencedNotes
     * @return list<AstNode>
     */
    private function inlineContainerNodes(\DOMElement $element, ZipPackage $package, ?OpcRelationships $relationships, array $referencedNotes): array
    {
        $inlines = [];
        foreach ($element->childNodes as $child) {
            if ($child instanceof \DOMElement) {
                array_push($inlines, ...$this->inlineNodes($child, $package, $relationships, $referencedNotes));
            }
        }

        return $inlines;
    }

    /**
     * @param array<string, AstNode> $referencedNotes
     * @return list<AstNode>
     */
    private function directionalInlineNodes(
        \DOMElement $element,
        ZipPackage $package,
        ?OpcRelationships $relationships,
        array $referencedNotes
    ): array {
        $children = $this->coalesceTextNodes($this->inlineContainerNodes($element, $package, $relationships, $referencedNotes));
        if ($children === []) {
            return [];
        }

        $attrs = $this->directionalInlineAttrs($element);
        if ($attrs === null) {
            return $children;
        }

        return [new AstNode('span', $attrs, $children)];
    }

    /**
     * @return array{classes:list<string>, attributes:array<string, string>}|null
     */
    private function directionalInlineAttrs(\DOMElement $element): ?array
    {
        $direction = strtolower(trim((string) ($this->wordAttr($element, 'val') ?? '')));
        if ($direction !== 'ltr' && $direction !== 'rtl') {
            return null;
        }

        if ($this->isWordElement($element, 'bdo')) {
            return [
                'classes' => [
                    'docx-direction',
                    'docx-bidi-override',
                    'docx-bdo',
                    'docx-bdo-' . $direction,
                ],
                'attributes' => [
                    'data-docx-direction-kind' => 'override',
                    'data-docx-direction' => $direction,
                    'data-docx-bidi-override' => 'true',
                    'dir' => $direction,
                ],
            ];
        }

        return [
            'classes' => [
                'docx-direction',
                'docx-dir',
                'docx-dir-' . $direction,
            ],
            'attributes' => [
                'data-docx-direction-kind' => 'embedding',
                'data-docx-direction' => $direction,
                'dir' => $direction,
            ],
        ];
    }

    /**
     * @param array<string, AstNode> $referencedNotes
     * @return list<AstNode>
     */
    private function smartTagNodes(
        \DOMElement $smartTag,
        ZipPackage $package,
        ?OpcRelationships $relationships,
        array $referencedNotes
    ): array {
        $children = $this->coalesceTextNodes($this->inlineContainerNodes($smartTag, $package, $relationships, $referencedNotes));
        if ($children === []) {
            return [];
        }

        return [new AstNode('span', $this->smartTagAttrs($smartTag), $children)];
    }

    /**
     * @return array{classes:list<string>, attributes:array<string, string>}
     */
    private function smartTagAttrs(\DOMElement $smartTag): array
    {
        $attributes = [];
        foreach ([
            'uri' => 'data-docx-smart-tag-uri',
            'element' => 'data-docx-smart-tag-element',
        ] as $wordAttr => $htmlAttr) {
            $value = $this->wordAttr($smartTag, $wordAttr);
            if ($value !== null && $value !== '') {
                $attributes[$htmlAttr] = $value;
            }
        }

        $properties = $this->firstChildElement($smartTag, self::WORDPROCESSINGML_NS, 'smartTagPr');
        if ($properties instanceof \DOMElement) {
            foreach ($properties->childNodes as $child) {
                if (!$child instanceof \DOMElement || !$this->isWordElement($child, 'attr')) {
                    continue;
                }

                $name = $this->wordAttr($child, 'name');
                $key = $name === null ? null : $this->smartTagPropertyKey($name);
                if ($key === null) {
                    continue;
                }

                $value = $this->wordAttr($child, 'val');
                if ($value !== null && $value !== '') {
                    $attributes['data-docx-smart-tag-prop-' . $key] = $value;
                }

                $uri = $this->wordAttr($child, 'uri');
                if ($uri !== null && $uri !== '') {
                    $attributes['data-docx-smart-tag-prop-' . $key . '-uri'] = $uri;
                }
            }
        }

        return [
            'classes' => ['docx-smart-tag'],
            'attributes' => $attributes,
        ];
    }

    private function smartTagPropertyKey(string $name): ?string
    {
        return $this->xmlMetadataPropertyKey($name);
    }

    private function xmlMetadataPropertyKey(string $name): ?string
    {
        $key = strtolower(trim($name));
        if ($key === '') {
            return null;
        }

        $key = preg_replace('/[^a-z0-9_.:-]+/', '-', $key) ?? '';
        $key = trim($key, '-');

        return $key === '' ? null : $key;
    }

    /**
     * @param array<string, AstNode> $referencedNotes
     * @return list<AstNode>
     */
    private function trackedAcceptedChangeNodes(
        \DOMElement $change,
        ZipPackage $package,
        ?OpcRelationships $relationships,
        array $referencedNotes,
        string $type
    ): array
    {
        $children = $this->inlineContainerNodes($change, $package, $relationships, $referencedNotes);
        if ($children === []) {
            return [];
        }

        return [new AstNode('span', $this->trackedChangeSpanAttrs($change, $type), $children)];
    }

    /**
     * @return array{classes:list<string>, attributes:array<string, string>}
     */
    private function trackedChangeSpanAttrs(\DOMElement $change, string $type): array
    {
        $attributes = [
            'data-docx-change' => $type,
        ];

        foreach ([
            'id' => 'data-docx-change-id',
            'author' => 'data-docx-author',
            'date' => 'data-docx-date',
        ] as $wordAttr => $htmlAttr) {
            $value = $this->wordAttr($change, $wordAttr);
            if ($value !== null && $value !== '') {
                $attributes[$htmlAttr] = $value;
            }
        }

        return [
            'classes' => ['docx-' . $type],
            'attributes' => $attributes,
        ];
    }

    /**
     * @param array<string, AstNode> $referencedNotes
     * @return list<AstNode>
     */
    private function runNodes(\DOMElement $run, ZipPackage $package, ?OpcRelationships $relationships, array $referencedNotes): array
    {
        $nodes = [];
        foreach ($run->childNodes as $child) {
            array_push($nodes, ...$this->runChildNodes($child, $package, $relationships, $referencedNotes));
        }

        return $this->applyRunStyle($run, $this->coalesceTextNodes($nodes));
    }

    /**
     * @param array<string, AstNode> $referencedNotes
     * @return list<AstNode>
     */
    private function runChildNodes(\DOMNode $child, ZipPackage $package, ?OpcRelationships $relationships, array $referencedNotes): array
    {
        if (!$child instanceof \DOMElement || $this->isWordElement($child, 'rPr')) {
            return [];
        }

        if ($this->isMarkupCompatibilityElement($child, 'AlternateContent')) {
            return $this->alternateContentRunNodes($child, $package, $relationships, $referencedNotes);
        }

        if ($this->isWordElement($child, 't') || $this->isWordElement($child, 'delText')) {
            return [new AstNode('text', ['text' => $child->textContent])];
        }

        if ($this->isWordElement($child, 'tab')) {
            return [new AstNode('text', ['text' => "\t"])];
        }

        if ($this->isWordElement($child, 'ptab')) {
            return $this->positionalTabNodes($child);
        }

        if ($this->isWordElement($child, 'br')) {
            return $this->breakNodes($child);
        }

        if ($this->isWordElement($child, 'cr')) {
            return [new AstNode('linebreak')];
        }

        if ($this->isWordElement($child, 'lastRenderedPageBreak')) {
            return $this->lastRenderedPageBreakNodes();
        }

        if ($this->isWordElement($child, 'pgNum')) {
            return $this->runFieldMarkerNodes('page-number');
        }

        $dateFieldMarker = $this->dateFieldMarkerName($child);
        if ($dateFieldMarker !== null) {
            return $this->runFieldMarkerNodes('date', $dateFieldMarker);
        }

        if ($this->isWordElement($child, 'softHyphen')) {
            return [new AstNode('text', ['text' => "\u{00AD}"])];
        }

        if ($this->isWordElement($child, 'noBreakHyphen')) {
            return [new AstNode('text', ['text' => "\u{2011}"])];
        }

        if ($this->isWordElement($child, 'sym')) {
            $symbol = $this->symbolText($child);

            return $symbol === '' ? [] : [new AstNode('text', ['text' => $symbol])];
        }

        if ($this->isWordElement($child, 'ruby')) {
            return $this->rubyNodes($child, $package, $relationships, $referencedNotes);
        }

        if ($this->isWordElement($child, 'footnoteReference')) {
            $nodes = [];
            $this->appendReferencedNote($nodes, $referencedNotes, 'footnote', $child);

            return $nodes;
        }

        if ($this->isWordElement($child, 'endnoteReference')) {
            $nodes = [];
            $this->appendReferencedNote($nodes, $referencedNotes, 'endnote', $child);

            return $nodes;
        }

        if ($this->isWordElement($child, 'commentReference')) {
            $nodes = [];
            $this->appendReferencedNote($nodes, $referencedNotes, 'comment', $child);

            return $nodes;
        }

        if ($this->isWordElement($child, 'footnoteRef')) {
            return $this->runReferenceMarkerNodes('footnote');
        }

        if ($this->isWordElement($child, 'endnoteRef')) {
            return $this->runReferenceMarkerNodes('endnote');
        }

        if ($this->isWordElement($child, 'annotationRef')) {
            return $this->runReferenceMarkerNodes('annotation');
        }

        if ($this->isMathElement($child, 'oMath')) {
            return $this->mathNodes($child, false);
        }

        if ($this->isMathElement($child, 'oMathPara')) {
            return $this->mathNodes($child, true);
        }

        if ($this->isWordElement($child, 'subDoc')) {
            return $this->subdocumentReferenceNodes($child, $package, $relationships);
        }

        if ($this->isWordElement($child, 'drawing')) {
            return $this->drawingNodes($child, $package, $relationships);
        }

        if ($this->isWordElement($child, 'pict')) {
            return $this->vmlImageNodes($child, $package, $relationships);
        }

        if ($this->isWordElement($child, 'object')) {
            return $this->embeddedObjectNodes($child, $package, $relationships);
        }

        if ($this->isWordElement($child, 'dir') || $this->isWordElement($child, 'bdo')) {
            return $this->directionalInlineNodes($child, $package, $relationships, $referencedNotes);
        }

        return [];
    }

    /**
     * @return list<AstNode>
     */
    private function runReferenceMarkerNodes(string $kind): array
    {
        $kind = match ($kind) {
            'footnote' => 'footnote',
            'endnote' => 'endnote',
            'annotation' => 'annotation',
            default => '',
        };
        if ($kind === '') {
            return [];
        }

        return [new AstNode('span', [
            'classes' => ['docx-reference-marker', 'docx-' . $kind . '-reference-marker'],
            'attributes' => ['data-docx-reference-marker' => $kind],
        ], [new AstNode('text', ['text' => 'DOCX ' . $kind . ' reference marker'])])];
    }

    /**
     * @return list<AstNode>
     */
    private function runFieldMarkerNodes(string $kind, ?string $dateField = null): array
    {
        if ($kind === 'page-number') {
            return [new AstNode('span', [
                'classes' => ['docx-run-field-marker', 'docx-page-number-marker'],
                'attributes' => ['data-docx-run-field-marker' => 'page-number'],
            ], [new AstNode('text', ['text' => 'DOCX page number'])])];
        }

        if ($kind !== 'date' || $dateField === null) {
            return [];
        }

        $classes = ['docx-run-field-marker', 'docx-date-field-marker'];
        $suffix = $this->camelCaseClassSuffix($dateField);
        if ($suffix !== null) {
            $classes[] = 'docx-date-field-' . $suffix;
        }

        return [new AstNode('span', [
            'classes' => $classes,
            'attributes' => [
                'data-docx-run-field-marker' => 'date',
                'data-docx-date-field' => $dateField,
            ],
        ], [new AstNode('text', ['text' => 'DOCX date field: ' . $dateField])])];
    }

    private function dateFieldMarkerName(\DOMNode $node): ?string
    {
        if (!$node instanceof \DOMElement || $node->namespaceURI !== self::WORDPROCESSINGML_NS) {
            return null;
        }

        return in_array($node->localName, [
            'dayShort',
            'dayLong',
            'monthShort',
            'monthLong',
            'yearShort',
            'yearLong',
        ], true) ? $node->localName : null;
    }

    private function camelCaseClassSuffix(string $value): ?string
    {
        $hyphenated = preg_replace('/(?<!^)[A-Z]/', '-$0', $value) ?? $value;

        return $this->metadataClassSuffix($hyphenated);
    }

    /**
     * @param array<string, AstNode> $referencedNotes
     * @return list<AstNode>
     */
    private function rubyNodes(\DOMElement $ruby, ZipPackage $package, ?OpcRelationships $relationships, array $referencedNotes): array
    {
        $base = $this->firstChildElement($ruby, self::WORDPROCESSINGML_NS, 'rubyBase');
        if (!$base instanceof \DOMElement) {
            return [];
        }

        $baseNodes = $this->coalesceTextNodes($this->inlineContainerNodes($base, $package, $relationships, $referencedNotes));
        if ($baseNodes === []) {
            return [];
        }

        $attributes = [];
        $annotation = $this->firstChildElement($ruby, self::WORDPROCESSINGML_NS, 'rt');
        if ($annotation instanceof \DOMElement) {
            $annotationText = trim($this->plainInlineText(
                $this->inlineContainerNodes($annotation, $package, $relationships, $referencedNotes)
            ));
            if ($annotationText !== '') {
                $attributes['data-docx-ruby-text'] = $annotationText;
            }
        }

        $properties = $this->firstChildElement($ruby, self::WORDPROCESSINGML_NS, 'rubyPr');
        if ($properties instanceof \DOMElement) {
            foreach ([
                'rubyAlign' => 'data-docx-ruby-align',
                'lid' => 'data-docx-ruby-lang',
                'hps' => 'data-docx-ruby-hps',
                'hpsRaise' => 'data-docx-ruby-hps-raise',
                'hpsBaseText' => 'data-docx-ruby-hps-base-text',
            ] as $localName => $attributeName) {
                $property = $this->firstChildElement($properties, self::WORDPROCESSINGML_NS, $localName);
                if (!$property instanceof \DOMElement) {
                    continue;
                }

                $value = trim((string) ($this->wordAttr($property, 'val') ?? ''));
                if ($value !== '') {
                    $attributes[$attributeName] = $value;
                }
            }
        }

        return [new AstNode('span', [
            'classes' => ['docx-ruby'],
            'attributes' => $attributes,
        ], $baseNodes)];
    }

    /**
     * @return list<AstNode>
     */
    private function breakNodes(\DOMElement $break): array
    {
        $type = strtolower(trim((string) ($this->wordAttr($break, 'type') ?? 'textWrapping')));
        if ($type === '' || $type === 'textwrapping') {
            return [new AstNode('linebreak')];
        }

        if (!in_array($type, ['page', 'column'], true)) {
            return [new AstNode('span', [
                'classes' => ['docx-break', 'docx-unsupported-break'],
                'attributes' => ['data-docx-break-type' => $type],
            ], [new AstNode('text', ['text' => 'DOCX unsupported break: ' . $type])])];
        }

        $classes = ['docx-break', 'docx-' . $type . '-break'];
        $attributes = ['data-docx-break-type' => $type];
        $clear = trim((string) ($this->wordAttr($break, 'clear') ?? ''));
        if ($clear !== '') {
            $classes[] = 'docx-break-clear';
            $attributes['data-docx-break-clear'] = $clear;
        }

        return [new AstNode('span', [
            'classes' => $classes,
            'attributes' => $attributes,
        ], [new AstNode('text', ['text' => 'DOCX ' . $type . ' break'])])];
    }

    /**
     * @return list<AstNode>
     */
    private function positionalTabNodes(\DOMElement $tab): array
    {
        $classes = ['docx-tab', 'docx-positional-tab'];
        $attributes = ['data-docx-tab-type' => 'positional'];

        $alignment = trim((string) ($this->wordAttr($tab, 'alignment') ?? ''));
        if ($alignment !== '') {
            $attributes['data-docx-tab-alignment'] = $alignment;
            $suffix = $this->metadataClassSuffix($alignment);
            if ($suffix !== null) {
                $classes[] = 'docx-positional-tab-' . $suffix;
            }
        }

        $relativeTo = trim((string) ($this->wordAttr($tab, 'relativeTo') ?? ''));
        if ($relativeTo !== '') {
            $attributes['data-docx-tab-relative-to'] = $relativeTo;
        }

        $leader = trim((string) ($this->wordAttr($tab, 'leader') ?? ''));
        if ($leader !== '') {
            $attributes['data-docx-tab-leader'] = $leader;
            $classes[] = 'docx-positional-tab-leader';
            $suffix = $this->metadataClassSuffix($leader);
            if ($suffix !== null) {
                $classes[] = 'docx-positional-tab-leader-' . $suffix;
            }
        }

        return [new AstNode('span', [
            'classes' => array_values(array_unique($classes)),
            'attributes' => $attributes,
        ], [new AstNode('text', ['text' => 'DOCX positional tab'])])];
    }

    /**
     * @return list<AstNode>
     */
    private function lastRenderedPageBreakNodes(): array
    {
        return [new AstNode('span', [
            'classes' => ['docx-break', 'docx-rendered-page-break'],
            'attributes' => [
                'data-docx-break-type' => 'rendered-page',
                'data-docx-last-rendered-page-break' => 'true',
            ],
        ], [new AstNode('text', ['text' => 'DOCX rendered page break'])])];
    }

    /**
     * @param array<string, AstNode> $referencedNotes
     * @return list<AstNode>
     */
    private function alternateContentRunNodes(
        \DOMElement $alternateContent,
        ZipPackage $package,
        ?OpcRelationships $relationships,
        array $referencedNotes
    ): array {
        $selection = $this->alternateContentSelection($alternateContent);
        if (!$selection instanceof \DOMElement) {
            return [];
        }

        $nodes = [];
        foreach ($selection->childNodes as $child) {
            if (!$child instanceof \DOMElement) {
                continue;
            }

            if ($this->isWordElement($child, 'r')) {
                array_push($nodes, ...$this->runNodes($child, $package, $relationships, $referencedNotes));
                continue;
            }

            array_push($nodes, ...$this->runChildNodes($child, $package, $relationships, $referencedNotes));
        }

        return $this->coalesceTextNodes($nodes);
    }

    private function symbolText(\DOMElement $symbol): string
    {
        $font = $this->symbolFontKey((string) ($this->wordAttr($symbol, 'font') ?? ''));
        $codepoint = $this->symbolCodepoint((string) ($this->wordAttr($symbol, 'char') ?? ''));
        if ($font === null || $codepoint === null) {
            return '';
        }

        $normalizedCodepoint = $codepoint >= 0xf000 ? $codepoint - 0xf000 : $codepoint;
        $mappedCodepoint = self::DOCX_SYMBOL_FONT_MAP[$font][$normalizedCodepoint] ?? $codepoint;

        return $this->codepointToUtf8($mappedCodepoint);
    }

    private function symbolFontKey(string $font): ?string
    {
        $normalized = strtolower(trim(preg_replace('/\s+/u', ' ', $font) ?? $font));

        return match ($normalized) {
            'symbol' => 'symbol',
            'wingdings' => 'wingdings',
            'wingdings 2', 'wingdings2' => 'wingdings-2',
            'wingdings 3', 'wingdings3' => 'wingdings-3',
            default => null,
        };
    }

    private function symbolCodepoint(string $hex): ?int
    {
        $hex = trim($hex);
        if ($hex === '' || preg_match('/^[0-9a-fA-F]+$/', $hex) !== 1) {
            return null;
        }

        return hexdec($hex);
    }

    private function codepointToUtf8(int $codepoint): string
    {
        if ($codepoint < 0 || $codepoint > 0x10ffff || ($codepoint >= 0xd800 && $codepoint <= 0xdfff)) {
            return '';
        }

        if ($codepoint <= 0x7f) {
            return chr($codepoint);
        }

        if ($codepoint <= 0x7ff) {
            return chr(0xc0 | ($codepoint >> 6))
                . chr(0x80 | ($codepoint & 0x3f));
        }

        if ($codepoint <= 0xffff) {
            return chr(0xe0 | ($codepoint >> 12))
                . chr(0x80 | (($codepoint >> 6) & 0x3f))
                . chr(0x80 | ($codepoint & 0x3f));
        }

        return chr(0xf0 | ($codepoint >> 18))
            . chr(0x80 | (($codepoint >> 12) & 0x3f))
            . chr(0x80 | (($codepoint >> 6) & 0x3f))
            . chr(0x80 | ($codepoint & 0x3f));
    }

    /**
     * @return list<AstNode>
     */
    private function mathNodes(\DOMElement $math, bool $display): array
    {
        $text = $this->ommlFormulaText($math);
        if ($text === '') {
            return [];
        }

        return [new AstNode('math', [
            'text' => $text,
            'display' => $display,
            'sourceFormat' => 'docx-omml',
        ])];
    }

    private function ommlFormulaText(\DOMElement $math): string
    {
        return trim($this->ommlText($math));
    }

    private function ommlText(\DOMElement $element): string
    {
        if ($element->namespaceURI !== self::OFFICE_MATH_NS) {
            return '';
        }

        return match ($element->localName) {
            't' => $element->textContent,
            'sSub' => $this->ommlScriptText($element, '_', 'sub'),
            'sSup' => $this->ommlScriptText($element, '^', 'sup'),
            'sSubSup' => $this->ommlSubSupText($element),
            'f' => '\\frac{' . $this->ommlRequiredChildText($element, 'num') . '}{' . $this->ommlRequiredChildText($element, 'den') . '}',
            'rad' => $this->ommlRadicalText($element),
            'nary' => $this->ommlNaryText($element),
            default => $this->ommlChildText($element),
        };
    }

    private function ommlScriptText(\DOMElement $element, string $operator, string $scriptName): string
    {
        $base = $this->ommlRequiredChildText($element, 'e');
        $script = $this->ommlRequiredChildText($element, $scriptName);

        return $base . $operator . '{' . $script . '}';
    }

    private function ommlSubSupText(\DOMElement $element): string
    {
        return $this->ommlRequiredChildText($element, 'e')
            . '_{' . $this->ommlRequiredChildText($element, 'sub') . '}'
            . '^{' . $this->ommlRequiredChildText($element, 'sup') . '}';
    }

    private function ommlRadicalText(\DOMElement $element): string
    {
        $degree = $this->ommlChildNamedText($element, 'deg');
        $body = $this->ommlRequiredChildText($element, 'e');

        if ($degree === '') {
            return '\\sqrt{' . $body . '}';
        }

        return '\\sqrt[' . $degree . ']{' . $body . '}';
    }

    private function ommlNaryText(\DOMElement $element): string
    {
        $body = $this->ommlRequiredChildText($element, 'e');
        $operator = $this->ommlNaryOperatorText($element);
        if ($operator === '') {
            return $body;
        }

        $text = $operator;
        if (!$this->ommlNaryLimitHidden($element, 'sub')) {
            $sub = $this->ommlChildNamedText($element, 'sub');
            if ($sub !== '') {
                $text .= '_{' . $sub . '}';
            }
        }
        if (!$this->ommlNaryLimitHidden($element, 'sup')) {
            $sup = $this->ommlChildNamedText($element, 'sup');
            if ($sup !== '') {
                $text .= '^{' . $sup . '}';
            }
        }

        return $text . ' ' . $body;
    }

    private function ommlNaryOperatorText(\DOMElement $element): string
    {
        $properties = $this->firstChildElement($element, self::OFFICE_MATH_NS, 'naryPr');
        $chr = $properties instanceof \DOMElement
            ? $this->firstChildElement($properties, self::OFFICE_MATH_NS, 'chr')
            : null;
        $value = $chr instanceof \DOMElement
            ? trim((string) ($this->namespacedAttr($chr, self::OFFICE_MATH_NS, 'val') ?? ''))
            : '';

        return match ($value) {
            "\u{2211}" => '\\sum',
            "\u{220f}" => '\\prod',
            "\u{2210}" => '\\coprod',
            "\u{222b}" => '\\int',
            "\u{222e}" => '\\oint',
            "\u{22c2}" => '\\bigcap',
            "\u{22c3}" => '\\bigcup',
            default => $value,
        };
    }

    private function ommlNaryLimitHidden(\DOMElement $element, string $limitName): bool
    {
        $properties = $this->firstChildElement($element, self::OFFICE_MATH_NS, 'naryPr');
        if (!$properties instanceof \DOMElement) {
            return false;
        }

        $hide = $this->firstChildElement(
            $properties,
            self::OFFICE_MATH_NS,
            $limitName === 'sub' ? 'subHide' : 'supHide'
        );
        if (!$hide instanceof \DOMElement) {
            return false;
        }

        return $this->onOffStringValue($this->namespacedAttr($hide, self::OFFICE_MATH_NS, 'val')) !== false;
    }

    private function ommlRequiredChildText(\DOMElement $element, string $localName): string
    {
        $text = $this->ommlChildNamedText($element, $localName);
        if ($text === '') {
            throw new \InvalidArgumentException('DOCX OMML ' . $element->localName . ' is missing m:' . $localName);
        }

        return $text;
    }

    private function ommlChildNamedText(\DOMElement $element, string $localName): string
    {
        $child = $this->firstChildElement($element, self::OFFICE_MATH_NS, $localName);

        return $child instanceof \DOMElement ? trim($this->ommlChildText($child)) : '';
    }

    private function ommlChildText(\DOMElement $element): string
    {
        $text = '';
        foreach ($element->childNodes as $child) {
            if ($child instanceof \DOMElement) {
                $text .= $this->ommlText($child);
            }
        }

        return $text;
    }

    /**
     * @param list<AstNode> $nodes
     * @param array<string, AstNode> $referencedNotes
     */
    private function appendReferencedNote(array &$nodes, array $referencedNotes, string $sourceType, \DOMElement $reference): void
    {
        $id = $this->wordAttr($reference, 'id');
        if ($id === null) {
            return;
        }

        $referenceAttrs = $this->noteReferenceAttrs($reference);
        $referenceAttrs += $this->noteReferenceNumberingAttrs(
            $sourceType,
            ($referenceAttrs['customMarkFollows'] ?? false) === true,
        );
        $key = $sourceType . ':' . $id;
        if (isset($referencedNotes[$key])) {
            $nodes[] = $this->noteWithReferenceAttrs($referencedNotes[$key], $referenceAttrs);
            return;
        }

        $nodes[] = new AstNode('note', [
            'id' => $id,
            'sourceType' => $sourceType,
            'missing' => true,
        ] + $referenceAttrs);
    }

    /**
     * @return array{customMarkFollows?:bool}
     */
    private function noteReferenceAttrs(\DOMElement $reference): array
    {
        if (!$this->onOffWordAttr($reference, 'customMarkFollows', false)) {
            return [];
        }

        return ['customMarkFollows' => true];
    }

    /**
     * @return array{referenceFormat?:string, referenceStart?:int, referenceRestart?:string, referenceNumberingSkipped?:bool, referenceNumber?:int, referenceLabel?:string}
     */
    private function noteReferenceNumberingAttrs(string $sourceType, bool $customMarkFollows): array
    {
        if (!in_array($sourceType, ['footnote', 'endnote'], true)) {
            return [];
        }

        $state = $this->noteReferenceState[$sourceType] ?? null;
        if (!is_array($state)) {
            return [];
        }

        $policy = $state['policy'];
        $format = $policy['numberFormat'];
        $attrs = [
            'referenceFormat' => $format,
            'referenceStart' => $policy['numberStart'],
            'referenceRestart' => $policy['numberRestart'],
        ];

        if ($customMarkFollows) {
            $attrs['referenceNumberingSkipped'] = true;

            return $attrs;
        }

        $number = max(1, $state['next']);
        $this->noteReferenceState[$sourceType]['next'] = $number + 1;

        $attrs['referenceNumber'] = $number;
        $attrs['referenceLabel'] = $this->noteReferenceLabel($number, $format);

        return $attrs;
    }

    private function noteReferenceLabel(int $number, string $format): string
    {
        return match ($format) {
            'decimalZero' => sprintf('%02d', $number),
            'lowerLetter' => $this->letterReferenceLabel($number, false),
            'upperLetter' => $this->letterReferenceLabel($number, true),
            'lowerRoman' => strtolower($this->romanReferenceLabel($number)),
            'upperRoman' => $this->romanReferenceLabel($number),
            default => (string) $number,
        };
    }

    private function letterReferenceLabel(int $number, bool $upper): string
    {
        $number = max(1, $number);
        $label = '';
        while ($number > 0) {
            $number--;
            $label = chr(($upper ? 65 : 97) + ($number % 26)) . $label;
            $number = intdiv($number, 26);
        }

        return $label;
    }

    private function romanReferenceLabel(int $number): string
    {
        $number = max(1, min(3999, $number));
        $map = [
            1000 => 'M',
            900 => 'CM',
            500 => 'D',
            400 => 'CD',
            100 => 'C',
            90 => 'XC',
            50 => 'L',
            40 => 'XL',
            10 => 'X',
            9 => 'IX',
            5 => 'V',
            4 => 'IV',
            1 => 'I',
        ];

        $label = '';
        foreach ($map as $value => $glyph) {
            while ($number >= $value) {
                $label .= $glyph;
                $number -= $value;
            }
        }

        return $label;
    }

    /**
     * @param array<string, mixed> $attrs
     */
    private function noteWithReferenceAttrs(AstNode $note, array $attrs): AstNode
    {
        if ($attrs === []) {
            return $note;
        }

        return new AstNode($note->type, array_replace($note->attrs, $attrs), $note->children);
    }

    /**
     * @param list<AstNode> $nodes
     * @return list<AstNode>
     */
    private function applyRunStyle(\DOMElement $run, array $nodes): array
    {
        if ($nodes === []) {
            return [];
        }

        $properties = $this->firstChildElement($run, self::WORDPROCESSINGML_NS, 'rPr');
        $styleId = $properties instanceof \DOMElement ? $this->runStyleId($properties) : null;
        $seen = [];
        $runProperties = $this->mergeRunProperties(
            $this->currentParagraphRunProperties,
            $this->currentTableConditionalRunProperties,
        );
        $runProperties = $this->mergeRunProperties(
            $runProperties,
            $this->resolveStyleRunProperties($styleId, $seen),
        );
        $runProperties = $this->mergeRunProperties(
            $runProperties,
            $properties instanceof \DOMElement ? $this->runPropertiesFromElement($properties) : null,
        );
        if ($runProperties === null) {
            return $nodes;
        }

        $wrap = static function (string $type, array $children): array {
            return [new AstNode($type, [], $children)];
        };

        if (($runProperties['underline'] ?? false) === true) {
            $nodes = $wrap('underline', $nodes);
        }
        if (($runProperties['strikeout'] ?? false) === true) {
            $nodes = $wrap('strikeout', $nodes);
        }
        if (($runProperties['subscript'] ?? false) === true) {
            $nodes = $wrap('subscript', $nodes);
        } elseif (($runProperties['superscript'] ?? false) === true) {
            $nodes = $wrap('superscript', $nodes);
        }
        if (($runProperties['smallCaps'] ?? false) === true) {
            $nodes = $wrap('small_caps', $nodes);
        }
        if (($runProperties['emph'] ?? false) === true) {
            $nodes = $wrap('emph', $nodes);
        }
        if (($runProperties['strong'] ?? false) === true) {
            $nodes = $wrap('strong', $nodes);
        }

        $metadataAttrs = $runProperties['metadata'] ?? null;
        if ($metadataAttrs !== null) {
            $nodes = [new AstNode('span', $metadataAttrs, $nodes)];
        }

        return $nodes;
    }

    private function runStyleId(\DOMElement $properties): ?string
    {
        $style = $this->firstChildElement($properties, self::WORDPROCESSINGML_NS, 'rStyle');
        if (!$style instanceof \DOMElement) {
            return null;
        }

        $value = $this->wordAttr($style, 'val');

        return $value === null || $value === '' ? null : $value;
    }

    /**
     * @param array<string, true> $seen
     * @return array<string, mixed>|null
     */
    private function resolveStyleRunProperties(?string $styleId, array &$seen): ?array
    {
        if ($styleId === null || isset($seen[$styleId]) || !isset($this->currentStyles[$styleId])) {
            return null;
        }

        $seen[$styleId] = true;
        $style = $this->currentStyles[$styleId];
        $properties = $this->resolveStyleRunProperties(
            is_string($style['basedOn'] ?? null) ? $style['basedOn'] : null,
            $seen
        );
        $styleRunProperties = $style['runProperties'] ?? null;
        if (is_array($styleRunProperties)) {
            $properties = $this->mergeRunProperties($properties, $styleRunProperties);
        }

        return $properties;
    }

    /**
     * @param array<string, array<string, mixed>> $styles
     * @return array<string, mixed>|null
     */
    private function paragraphRunProperties(\DOMElement $paragraph, array $styles): ?array
    {
        return $this->mergeRunProperties(
            $this->currentDefaultRunProperties,
            $this->paragraphStyleRunProperties($paragraph, $styles),
        );
    }

    /**
     * @param array<string, array<string, mixed>> $styles
     * @return array<string, mixed>|null
     */
    private function paragraphStyleRunProperties(\DOMElement $paragraph, array $styles): ?array
    {
        $styleId = $this->paragraphStyleId($paragraph);
        if ($styleId === null || !isset($styles[$styleId]) || ($styles[$styleId]['type'] ?? null) !== 'paragraph') {
            return null;
        }

        $seen = [];

        return $this->resolveParagraphStyleRunProperties($styleId, $styles, $seen);
    }

    /**
     * @param array<string, array<string, mixed>> $styles
     * @param array<string, true> $seen
     * @return array<string, mixed>|null
     */
    private function resolveParagraphStyleRunProperties(?string $styleId, array $styles, array &$seen): ?array
    {
        if (
            $styleId === null
            || isset($seen[$styleId])
            || !isset($styles[$styleId])
            || ($styles[$styleId]['type'] ?? null) !== 'paragraph'
        ) {
            return null;
        }

        $seen[$styleId] = true;
        $style = $styles[$styleId];
        $properties = $this->resolveParagraphStyleRunProperties(
            is_string($style['basedOn'] ?? null) ? $style['basedOn'] : null,
            $styles,
            $seen
        );
        $styleRunProperties = $style['runProperties'] ?? null;
        if (is_array($styleRunProperties)) {
            $properties = $this->mergeRunProperties($properties, $styleRunProperties);
        }

        return $properties;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function runPropertiesFromElement(\DOMElement $properties): ?array
    {
        $runProperties = [];
        foreach ([
            'u' => 'underline',
            'smallCaps' => 'smallCaps',
            'i' => 'emph',
            'b' => 'strong',
        ] as $childName => $propertyName) {
            $value = $this->onOffChildValue($properties, $childName);
            if ($value !== null) {
                $runProperties[$propertyName] = $value;
            }
        }

        $strikeout = null;
        foreach (['strike', 'dstrike'] as $childName) {
            $value = $this->onOffChildValue($properties, $childName);
            if ($value === true) {
                $strikeout = true;
                break;
            }
            if ($value === false && $strikeout === null) {
                $strikeout = false;
            }
        }
        if ($strikeout !== null) {
            $runProperties['strikeout'] = $strikeout;
        }

        $verticalAlign = $this->firstChildElement($properties, self::WORDPROCESSINGML_NS, 'vertAlign');
        if ($verticalAlign instanceof \DOMElement) {
            $value = strtolower(trim((string) ($this->wordAttr($verticalAlign, 'val') ?? '')));
            $runProperties['subscript'] = $value === 'subscript';
            $runProperties['superscript'] = $value === 'superscript';
        }

        $metadata = $this->runMetadataAttrs($properties);
        if ($metadata !== null) {
            $runProperties['metadata'] = $metadata;
        }

        $metadataOverrides = $this->runMetadataOverrideFamilies($properties);
        if ($metadataOverrides !== []) {
            $runProperties['metadataOverrides'] = $metadataOverrides;
        }

        return $runProperties === [] ? null : $runProperties;
    }

    private function onOffChildValue(\DOMElement $properties, string $localName): ?bool
    {
        $child = $this->firstChildElement($properties, self::WORDPROCESSINGML_NS, $localName);
        if (!$child instanceof \DOMElement) {
            return null;
        }

        return $this->onOffWordAttr($child, 'val', true);
    }

    /**
     * @return list<string>
     */
    private function runMetadataOverrideFamilies(\DOMElement $properties): array
    {
        $families = [];
        foreach ([
            'highlight' => 'highlight',
            'shd' => 'shading',
            'color' => 'color',
            'lang' => 'language',
            'rtl' => 'rtl',
            'rFonts' => 'font',
            'vanish' => 'run-hidden',
            'webHidden' => 'run-web-hidden',
            'specVanish' => 'run-spec-hidden',
            'noProof' => 'no-proof',
            'caps' => 'run-caps',
            'outline' => 'run-outline',
            'shadow' => 'run-shadow',
            'emboss' => 'run-emboss',
            'imprint' => 'run-imprint',
            'em' => 'emphasis-mark',
            'effect' => 'text-effect',
            'spacing' => 'run-spacing',
            'w' => 'run-scale',
            'kern' => 'run-kern',
            'position' => 'run-position',
            'fitText' => 'run-fit-text',
        ] as $childName => $family) {
            if ($this->firstChildElement($properties, self::WORDPROCESSINGML_NS, $childName) instanceof \DOMElement) {
                $families[] = $family;
            }
        }

        return $families;
    }

    /**
     * @param array<string, mixed>|null $base
     * @param array<string, mixed>|null $override
     * @return array<string, mixed>|null
     */
    private function mergeRunProperties(?array $base, ?array $override): ?array
    {
        if ($base === null) {
            return $override;
        }
        if ($override === null) {
            return $base;
        }

        $merged = $base;
        foreach (['underline', 'strikeout', 'subscript', 'superscript', 'smallCaps', 'emph', 'strong'] as $name) {
            if (array_key_exists($name, $override)) {
                $merged[$name] = (bool) $override[$name];
            }
        }

        $metadata = $this->mergeRunMetadataAttrs(
            isset($base['metadata']) && is_array($base['metadata']) ? $base['metadata'] : null,
            isset($override['metadata']) && is_array($override['metadata']) ? $override['metadata'] : null,
            isset($override['metadataOverrides']) && is_array($override['metadataOverrides']) ? $override['metadataOverrides'] : [],
        );
        if ($metadata !== null) {
            $merged['metadata'] = $metadata;
        } else {
            unset($merged['metadata']);
        }
        unset($merged['metadataOverrides']);

        return $merged === [] ? null : $merged;
    }

    /**
     * @param array{classes:list<string>, attributes:array<string, string>}|null $base
     * @param array{classes:list<string>, attributes:array<string, string>}|null $override
     * @param list<mixed> $overrideFamilies
     * @return array{classes:list<string>, attributes:array<string, string>}|null
     */
    private function mergeRunMetadataAttrs(?array $base, ?array $override, array $overrideFamilies): ?array
    {
        if ($base === null) {
            return $override;
        }

        $classes = $base['classes'];
        $attributes = $base['attributes'];
        foreach ($overrideFamilies as $family) {
            if (!is_string($family)) {
                continue;
            }

            [$classes, $attributes] = $this->removeRunMetadataFamily($classes, $attributes, $family);
        }

        if ($override !== null) {
            $classes = array_values(array_unique([
                ...$classes,
                ...$override['classes'],
            ]));
            $attributes = array_replace($attributes, $override['attributes']);
        }

        if ($classes === [] && $attributes === []) {
            return null;
        }

        return [
            'classes' => $classes,
            'attributes' => $attributes,
        ];
    }

    /**
     * @param list<string> $classes
     * @param array<string, string> $attributes
     * @return array{0:list<string>, 1:array<string, string>}
     */
    private function removeRunMetadataFamily(array $classes, array $attributes, string $family): array
    {
        $removeExactClasses = [];
        $removeClassPrefixes = [];
        $removeExactAttributes = [];
        $removeAttributePrefixes = [];
        $runEffectFamily = false;
        $runMetricFamily = false;

        if ($family === 'highlight') {
            $removeExactClasses[] = 'docx-highlight';
            $removeClassPrefixes[] = 'docx-highlight-';
            $removeExactAttributes[] = 'data-docx-highlight';
        } elseif ($family === 'shading') {
            $removeExactClasses[] = 'docx-shading';
            $removeAttributePrefixes[] = 'data-docx-shading-';
        } elseif ($family === 'color') {
            array_push($removeExactClasses, 'docx-color', 'docx-theme-color');
            array_push($removeClassPrefixes, 'docx-color-', 'docx-theme-color-');
            array_push(
                $removeExactAttributes,
                'data-docx-color',
                'data-docx-theme-color',
                'data-docx-theme-color-rgb',
                'data-docx-theme-tint',
                'data-docx-theme-shade'
            );
        } elseif ($family === 'language') {
            $removeExactClasses[] = 'docx-language';
            array_push($removeExactAttributes, 'lang', 'data-docx-lang', 'data-docx-lang-bidi', 'data-docx-lang-east-asia');
        } elseif ($family === 'rtl') {
            $removeExactClasses[] = 'docx-rtl';
            $removeExactAttributes[] = 'dir';
        } elseif ($family === 'run-hidden') {
            $removeExactClasses[] = 'docx-run-hidden';
            $removeExactAttributes[] = 'data-docx-run-hidden';
            $runEffectFamily = true;
        } elseif ($family === 'run-web-hidden') {
            $removeExactClasses[] = 'docx-run-web-hidden';
            $removeExactAttributes[] = 'data-docx-run-web-hidden';
            $runEffectFamily = true;
        } elseif ($family === 'run-spec-hidden') {
            $removeExactClasses[] = 'docx-run-spec-hidden';
            $removeExactAttributes[] = 'data-docx-run-spec-hidden';
            $runEffectFamily = true;
        } elseif ($family === 'no-proof') {
            $removeExactClasses[] = 'docx-no-proof';
            $removeExactAttributes[] = 'data-docx-no-proof';
        } elseif ($family === 'run-caps') {
            $removeExactClasses[] = 'docx-run-caps';
            $removeExactAttributes[] = 'data-docx-run-caps';
            $runEffectFamily = true;
        } elseif ($family === 'run-outline') {
            $removeExactClasses[] = 'docx-run-outline';
            $removeExactAttributes[] = 'data-docx-run-outline';
            $runEffectFamily = true;
        } elseif ($family === 'run-shadow') {
            $removeExactClasses[] = 'docx-run-shadow';
            $removeExactAttributes[] = 'data-docx-run-shadow';
            $runEffectFamily = true;
        } elseif ($family === 'run-emboss') {
            $removeExactClasses[] = 'docx-run-emboss';
            $removeExactAttributes[] = 'data-docx-run-emboss';
            $runEffectFamily = true;
        } elseif ($family === 'run-imprint') {
            $removeExactClasses[] = 'docx-run-imprint';
            $removeExactAttributes[] = 'data-docx-run-imprint';
            $runEffectFamily = true;
        } elseif ($family === 'emphasis-mark') {
            $removeExactClasses[] = 'docx-emphasis-mark';
            $removeClassPrefixes[] = 'docx-emphasis-mark-';
            $removeExactAttributes[] = 'data-docx-emphasis-mark';
            $runEffectFamily = true;
        } elseif ($family === 'text-effect') {
            $removeExactClasses[] = 'docx-text-effect';
            $removeClassPrefixes[] = 'docx-text-effect-';
            $removeExactAttributes[] = 'data-docx-text-effect';
            $runEffectFamily = true;
        } elseif ($family === 'run-spacing') {
            array_push($removeExactClasses, 'docx-run-spacing', 'docx-run-spacing-expanded', 'docx-run-spacing-condensed');
            $removeExactAttributes[] = 'data-docx-run-spacing-twips';
            $runMetricFamily = true;
        } elseif ($family === 'run-scale') {
            $removeExactClasses[] = 'docx-run-scale';
            $removeExactAttributes[] = 'data-docx-run-scale-percent';
            $runMetricFamily = true;
        } elseif ($family === 'run-kern') {
            $removeExactClasses[] = 'docx-run-kern';
            $removeExactAttributes[] = 'data-docx-run-kern-half-points';
            $runMetricFamily = true;
        } elseif ($family === 'run-position') {
            array_push($removeExactClasses, 'docx-run-position', 'docx-run-position-raised', 'docx-run-position-lowered');
            $removeExactAttributes[] = 'data-docx-run-position-half-points';
            $runMetricFamily = true;
        } elseif ($family === 'run-fit-text') {
            $removeExactClasses[] = 'docx-run-fit-text';
            array_push($removeExactAttributes, 'data-docx-fit-text-width-twips', 'data-docx-fit-text-id');
            $runMetricFamily = true;
        }

        $classes = array_values(array_filter(
            $classes,
            static function (string $class) use ($removeExactClasses, $removeClassPrefixes): bool {
                if (in_array($class, $removeExactClasses, true)) {
                    return false;
                }

                foreach ($removeClassPrefixes as $prefix) {
                    if (str_starts_with($class, $prefix)) {
                        return false;
                    }
                }

                return true;
            },
        ));

        foreach (array_keys($attributes) as $name) {
            if (in_array($name, $removeExactAttributes, true)) {
                unset($attributes[$name]);
                continue;
            }

            foreach ($removeAttributePrefixes as $prefix) {
                if (str_starts_with((string) $name, $prefix)) {
                    unset($attributes[$name]);
                    break;
                }
            }
        }

        if ($runEffectFamily && !$this->hasRunEffectMetadataClass($classes)) {
            $classes = array_values(array_diff($classes, ['docx-run-effect']));
        }
        if ($runMetricFamily && !$this->hasRunMetricMetadataClass($classes)) {
            $classes = array_values(array_diff($classes, ['docx-run-metrics']));
        }

        if ($family === 'font') {
            $classes = array_values(array_diff($classes, ['docx-font', 'docx-theme-font']));
            foreach (array_keys($attributes) as $name) {
                if (str_starts_with((string) $name, 'data-docx-font-') || str_starts_with((string) $name, 'data-docx-theme-font-')) {
                    unset($attributes[$name]);
                }
            }
        }

        return [$classes, $attributes];
    }

    /**
     * @param list<string> $classes
     */
    private function hasRunEffectMetadataClass(array $classes): bool
    {
        foreach ($classes as $class) {
            if (in_array($class, [
                'docx-run-hidden',
                'docx-run-web-hidden',
                'docx-run-spec-hidden',
                'docx-run-caps',
                'docx-run-outline',
                'docx-run-shadow',
                'docx-run-emboss',
                'docx-run-imprint',
                'docx-emphasis-mark',
                'docx-text-effect',
            ], true)) {
                return true;
            }

            if (str_starts_with($class, 'docx-emphasis-mark-') || str_starts_with($class, 'docx-text-effect-')) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param list<string> $classes
     */
    private function hasRunMetricMetadataClass(array $classes): bool
    {
        foreach ($classes as $class) {
            if (in_array($class, [
                'docx-run-spacing',
                'docx-run-spacing-expanded',
                'docx-run-spacing-condensed',
                'docx-run-scale',
                'docx-run-kern',
                'docx-run-position',
                'docx-run-position-raised',
                'docx-run-position-lowered',
                'docx-run-fit-text',
            ], true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array{classes:list<string>, attributes:array<string, string>}|null
     */
    private function runMetadataAttrs(\DOMElement $properties, bool $includeFormattingChange = true): ?array
    {
        $attrs = null;
        foreach ([
            $this->runReviewMarkupAttrs($properties),
            $this->runColorAttrs($properties),
            $this->runLanguageDirectionAttrs($properties),
            $this->runFontAttrs($properties),
            $this->runEffectAttrs($properties),
            $this->runMetricAttrs($properties),
            $this->runProofingAttrs($properties),
            $includeFormattingChange ? $this->runFormattingChangeAttrs($properties) : null,
        ] as $source) {
            if ($source === null) {
                continue;
            }

            if ($attrs === null) {
                $attrs = $source;
                continue;
            }

            $attrs['classes'] = array_values(array_unique([
                ...$attrs['classes'],
                ...$source['classes'],
            ]));
            $attrs['attributes'] += $source['attributes'];
        }

        return $attrs;
    }

    /**
     * @return array{classes:list<string>, attributes:array<string, string>}|null
     */
    private function runEffectAttrs(\DOMElement $properties): ?array
    {
        $classes = [];
        $attributes = [];

        foreach ([
            'vanish' => ['docx-run-hidden', 'data-docx-run-hidden'],
            'webHidden' => ['docx-run-web-hidden', 'data-docx-run-web-hidden'],
            'specVanish' => ['docx-run-spec-hidden', 'data-docx-run-spec-hidden'],
            'caps' => ['docx-run-caps', 'data-docx-run-caps'],
            'outline' => ['docx-run-outline', 'data-docx-run-outline'],
            'shadow' => ['docx-run-shadow', 'data-docx-run-shadow'],
            'emboss' => ['docx-run-emboss', 'data-docx-run-emboss'],
            'imprint' => ['docx-run-imprint', 'data-docx-run-imprint'],
        ] as $childName => [$class, $attribute]) {
            if ($this->onOffChildValue($properties, $childName) !== true) {
                continue;
            }

            $classes[] = 'docx-run-effect';
            $classes[] = $class;
            $attributes[$attribute] = 'true';
        }

        foreach ([
            'em' => ['docx-emphasis-mark', 'data-docx-emphasis-mark'],
            'effect' => ['docx-text-effect', 'data-docx-text-effect'],
        ] as $childName => [$class, $attribute]) {
            $child = $this->firstChildElement($properties, self::WORDPROCESSINGML_NS, $childName);
            if (!$child instanceof \DOMElement) {
                continue;
            }

            $value = trim((string) ($this->wordAttr($child, 'val') ?? ''));
            if ($value === '' || in_array(strtolower($value), ['none', '0', 'false', 'off'], true)) {
                continue;
            }

            $classes[] = 'docx-run-effect';
            $classes[] = $class;
            $suffix = $this->metadataClassSuffix($value);
            if ($suffix !== null) {
                $classes[] = $class . '-' . $suffix;
            }
            $attributes[$attribute] = $value;
        }

        if ($attributes === []) {
            return null;
        }

        return [
            'classes' => array_values(array_unique($classes)),
            'attributes' => $attributes,
        ];
    }

    /**
     * @return array{classes:list<string>, attributes:array<string, string>}|null
     */
    private function runProofingAttrs(\DOMElement $properties): ?array
    {
        if ($this->onOffChildValue($properties, 'noProof') !== true) {
            return null;
        }

        return [
            'classes' => ['docx-no-proof'],
            'attributes' => [
                'data-docx-no-proof' => 'true',
            ],
        ];
    }

    /**
     * @return array{classes:list<string>, attributes:array<string, string>}|null
     */
    private function runMetricAttrs(\DOMElement $properties): ?array
    {
        $classes = [];
        $attributes = [];

        $spacing = $this->firstChildElement($properties, self::WORDPROCESSINGML_NS, 'spacing');
        if ($spacing instanceof \DOMElement) {
            $value = $this->optionalIntWordAttr($spacing, 'val');
            if ($value !== null) {
                $classes[] = 'docx-run-metrics';
                $classes[] = 'docx-run-spacing';
                if ($value > 0) {
                    $classes[] = 'docx-run-spacing-expanded';
                } elseif ($value < 0) {
                    $classes[] = 'docx-run-spacing-condensed';
                }
                $attributes['data-docx-run-spacing-twips'] = (string) $value;
            }
        }

        $scale = $this->firstChildElement($properties, self::WORDPROCESSINGML_NS, 'w');
        if ($scale instanceof \DOMElement) {
            $value = $this->optionalIntWordAttr($scale, 'val');
            if ($value !== null) {
                $classes[] = 'docx-run-metrics';
                $classes[] = 'docx-run-scale';
                $attributes['data-docx-run-scale-percent'] = (string) $value;
            }
        }

        $kern = $this->firstChildElement($properties, self::WORDPROCESSINGML_NS, 'kern');
        if ($kern instanceof \DOMElement) {
            $value = $this->optionalIntWordAttr($kern, 'val');
            if ($value !== null) {
                $classes[] = 'docx-run-metrics';
                $classes[] = 'docx-run-kern';
                $attributes['data-docx-run-kern-half-points'] = (string) $value;
            }
        }

        $position = $this->firstChildElement($properties, self::WORDPROCESSINGML_NS, 'position');
        if ($position instanceof \DOMElement) {
            $value = $this->optionalIntWordAttr($position, 'val');
            if ($value !== null) {
                $classes[] = 'docx-run-metrics';
                $classes[] = 'docx-run-position';
                if ($value > 0) {
                    $classes[] = 'docx-run-position-raised';
                } elseif ($value < 0) {
                    $classes[] = 'docx-run-position-lowered';
                }
                $attributes['data-docx-run-position-half-points'] = (string) $value;
            }
        }

        $fitText = $this->firstChildElement($properties, self::WORDPROCESSINGML_NS, 'fitText');
        if ($fitText instanceof \DOMElement) {
            $width = $this->optionalIntWordAttr($fitText, 'val');
            $id = trim((string) ($this->wordAttr($fitText, 'id') ?? ''));
            if ($width !== null || $id !== '') {
                $classes[] = 'docx-run-metrics';
                $classes[] = 'docx-run-fit-text';
                if ($width !== null) {
                    $attributes['data-docx-fit-text-width-twips'] = (string) $width;
                }
                if ($id !== '') {
                    $attributes['data-docx-fit-text-id'] = $id;
                }
            }
        }

        if ($attributes === []) {
            return null;
        }

        return [
            'classes' => array_values(array_unique($classes)),
            'attributes' => $attributes,
        ];
    }

    /**
     * @return array{classes:list<string>, attributes:array<string, string>}|null
     */
    private function runFontAttrs(\DOMElement $properties): ?array
    {
        $fonts = $this->firstChildElement($properties, self::WORDPROCESSINGML_NS, 'rFonts');
        if (!$fonts instanceof \DOMElement) {
            return null;
        }

        $classes = [];
        $attributes = [];
        foreach ([
            'ascii' => ['ascii', 'asciiTheme'],
            'hansi' => ['hAnsi', 'hAnsiTheme'],
            'east-asia' => ['eastAsia', 'eastAsiaTheme'],
            'complex-script' => ['cs', 'cstheme'],
        ] as $target => [$directName, $themeName]) {
            $direct = trim((string) ($this->wordAttr($fonts, $directName) ?? ''));
            $themeSlot = trim((string) ($this->wordAttr($fonts, $themeName) ?? ''));

            if ($themeSlot !== '') {
                $attributes['data-docx-theme-font-' . $target] = $themeSlot;
                $classes[] = 'docx-theme-font';
            }

            if ($direct !== '') {
                $attributes['data-docx-font-' . $target] = $direct;
                $classes[] = 'docx-font';
                continue;
            }

            if ($themeSlot !== '') {
                $resolved = $this->themeFontForSlot($themeSlot);
                if ($resolved !== null) {
                    $attributes['data-docx-font-' . $target] = $resolved;
                    $classes[] = 'docx-font';
                }
            }
        }

        if ($attributes === []) {
            return null;
        }

        return [
            'classes' => array_values(array_unique($classes)),
            'attributes' => $attributes,
        ];
    }

    private function themeFontForSlot(string $slot): ?string
    {
        $normalized = strtolower(preg_replace('/[^A-Za-z0-9]+/', '', $slot) ?? $slot);
        $key = match ($normalized) {
            'majorascii', 'majorhansi', 'majorlatin' => 'majorLatin',
            'majoreastasia' => 'majorEastAsia',
            'majorbidi', 'majorcs', 'majorcomplexscript' => 'majorComplexScript',
            'minorascii', 'minorhansi', 'minorlatin' => 'minorLatin',
            'minoreastasia' => 'minorEastAsia',
            'minorbidi', 'minorcs', 'minorcomplexscript' => 'minorComplexScript',
            default => null,
        };

        if ($key === null) {
            return null;
        }

        $font = $this->currentThemeFonts[$key] ?? null;

        return is_string($font) && $font !== '' ? $font : null;
    }

    private function themeColorRgb(string $name): ?string
    {
        if ($this->currentThemeColors === []) {
            return null;
        }

        foreach ([$name, strtolower($name), $this->normalizedThemeColorName($name)] as $candidate) {
            if ($candidate === '') {
                continue;
            }

            $rgb = $this->currentThemeColors[$candidate] ?? null;
            if (is_string($rgb) && $rgb !== '') {
                return $rgb;
            }
        }

        return null;
    }

    private function normalizedThemeColorName(string $name): string
    {
        $normalized = strtolower(preg_replace('/[^A-Za-z0-9]+/', '', $name) ?? $name);

        return match ($normalized) {
            'dark1', 'text1' => 'dk1',
            'light1', 'background1' => 'lt1',
            'dark2', 'text2' => 'dk2',
            'light2', 'background2' => 'lt2',
            'hyperlink' => 'hlink',
            'followedhyperlink', 'folhlink' => 'folHlink',
            default => $name,
        };
    }

    /**
     * @return array{classes:list<string>, attributes:array<string, string>}|null
     */
    private function runFormattingChangeAttrs(\DOMElement $properties): ?array
    {
        $change = $this->firstChildElement($properties, self::WORDPROCESSINGML_NS, 'rPrChange');
        if (!$change instanceof \DOMElement) {
            return null;
        }

        $attrs = $this->trackedFormattingChangeAttrs($change, 'run');
        $previous = $this->firstChildElement($change, self::WORDPROCESSINGML_NS, 'rPr');
        if ($previous instanceof \DOMElement) {
            foreach ($this->previousRunFormattingAttributes($previous) as $name => $value) {
                $attrs['attributes'][$name] = $value;
            }
        }

        return $attrs;
    }

    /**
     * @return array<string, string>
     */
    private function previousRunFormattingAttributes(\DOMElement $properties): array
    {
        $attributes = [];
        $styleId = $this->runStyleId($properties);
        if ($styleId !== null && $styleId !== '') {
            $attributes['data-docx-previous-run-style'] = $styleId;
        }

        foreach ([
            'b' => 'bold',
            'i' => 'italic',
            'u' => 'underline',
            'smallCaps' => 'small-caps',
            'rtl' => 'rtl',
        ] as $childName => $target) {
            $value = $this->onOffChildValue($properties, $childName);
            if ($value !== null) {
                $attributes['data-docx-previous-' . $target] = $value ? 'true' : 'false';
            }
        }

        $strikeout = null;
        foreach (['strike', 'dstrike'] as $childName) {
            $value = $this->onOffChildValue($properties, $childName);
            if ($value === true) {
                $strikeout = true;
                break;
            }
            if ($value === false && $strikeout === null) {
                $strikeout = false;
            }
        }
        if ($strikeout !== null) {
            $attributes['data-docx-previous-strikeout'] = $strikeout ? 'true' : 'false';
        }

        $verticalAlign = $this->firstChildElement($properties, self::WORDPROCESSINGML_NS, 'vertAlign');
        if ($verticalAlign instanceof \DOMElement) {
            $value = strtolower(trim((string) ($this->wordAttr($verticalAlign, 'val') ?? '')));
            if ($value !== '') {
                $attributes['data-docx-previous-vertical-align'] = $value;
            }
        }

        $previousMetadata = $this->runMetadataAttrs($properties, false);
        if ($previousMetadata !== null) {
            foreach ($previousMetadata['attributes'] as $name => $value) {
                $previousName = $this->previousFormattingAttributeName((string) $name);
                if ($previousName !== null) {
                    $attributes[$previousName] = $value;
                }
            }
        }

        return $attributes;
    }

    /**
     * @return array{classes:list<string>, attributes:array<string, string>}
     */
    private function trackedFormattingChangeAttrs(\DOMElement $change, string $scope): array
    {
        $attributes = [
            'data-docx-formatting-change' => $scope,
        ];

        foreach ([
            'id' => 'data-docx-change-id',
            'author' => 'data-docx-author',
            'date' => 'data-docx-date',
        ] as $wordAttr => $htmlAttr) {
            $value = $this->wordAttr($change, $wordAttr);
            if ($value !== null && $value !== '') {
                $attributes[$htmlAttr] = $value;
            }
        }

        return [
            'classes' => ['docx-formatting-change', 'docx-' . $scope . '-formatting-change'],
            'attributes' => $attributes,
        ];
    }

    private function previousFormattingAttributeName(string $name): ?string
    {
        if (!str_starts_with($name, 'data-docx-')) {
            return null;
        }

        return 'data-docx-previous-' . substr($name, strlen('data-docx-'));
    }

    /**
     * @return array{classes:list<string>, attributes:array<string, string>}|null
     */
    private function runReviewMarkupAttrs(\DOMElement $properties): ?array
    {
        $classes = [];
        $attributes = [];

        $highlight = $this->firstChildElement($properties, self::WORDPROCESSINGML_NS, 'highlight');
        if ($highlight instanceof \DOMElement) {
            $value = strtolower(trim((string) ($this->wordAttr($highlight, 'val') ?? '')));
            if ($value !== '' && !in_array($value, ['none', '0', 'false', 'off'], true)) {
                $classes[] = 'docx-highlight';
                $suffix = $this->metadataClassSuffix($value);
                if ($suffix !== null) {
                    $classes[] = 'docx-highlight-' . $suffix;
                }
                $attributes['data-docx-highlight'] = $value;
            }
        }

        $shading = $this->firstChildElement($properties, self::WORDPROCESSINGML_NS, 'shd');
        if ($shading instanceof \DOMElement) {
            $shadingAttributes = [];
            foreach ([
                'val' => 'val',
                'fill' => 'fill',
                'color' => 'color',
                'themeFill' => 'theme-fill',
                'themeColor' => 'theme-color',
            ] as $source => $target) {
                $value = $this->wordAttr($shading, $source);
                if ($value !== null && trim($value) !== '') {
                    $shadingAttributes['data-docx-shading-' . $target] = trim($value);
                }
            }
            foreach ([
                'themeFill' => 'data-docx-shading-theme-fill-rgb',
                'themeColor' => 'data-docx-shading-theme-color-rgb',
            ] as $source => $target) {
                $themeValue = trim((string) ($this->wordAttr($shading, $source) ?? ''));
                $themeRgb = $themeValue !== '' ? $this->themeColorRgb($themeValue) : null;
                if ($themeRgb !== null) {
                    $shadingAttributes[$target] = $themeRgb;
                }
            }

            if ($shadingAttributes !== []) {
                $classes[] = 'docx-shading';
                $attributes += $shadingAttributes;
            }
        }

        if ($classes === [] && $attributes === []) {
            return null;
        }

        return [
            'classes' => array_values(array_unique($classes)),
            'attributes' => $attributes,
        ];
    }

    /**
     * @return array{classes:list<string>, attributes:array<string, string>}|null
     */
    private function runColorAttrs(\DOMElement $properties): ?array
    {
        $color = $this->firstChildElement($properties, self::WORDPROCESSINGML_NS, 'color');
        if (!$color instanceof \DOMElement) {
            return null;
        }

        $classes = [];
        $attributes = [];

        $value = trim((string) ($this->wordAttr($color, 'val') ?? ''));
        if ($value !== '') {
            $attributes['data-docx-color'] = $value;
            $classes[] = 'docx-color';
            $suffix = $this->metadataClassSuffix($value);
            if ($suffix !== null) {
                $classes[] = 'docx-color-' . $suffix;
            }
        }

        $themeColor = trim((string) ($this->wordAttr($color, 'themeColor') ?? ''));
        if ($themeColor !== '') {
            $attributes['data-docx-theme-color'] = $themeColor;
            $classes[] = 'docx-theme-color';
            $suffix = $this->metadataClassSuffix($themeColor);
            if ($suffix !== null) {
                $classes[] = 'docx-theme-color-' . $suffix;
            }
            $themeRgb = $this->themeColorRgb($themeColor);
            if ($themeRgb !== null) {
                $attributes['data-docx-theme-color-rgb'] = $themeRgb;
            }
        }

        foreach ([
            'themeTint' => 'data-docx-theme-tint',
            'themeShade' => 'data-docx-theme-shade',
        ] as $source => $target) {
            $themeValue = trim((string) ($this->wordAttr($color, $source) ?? ''));
            if ($themeValue !== '') {
                $attributes[$target] = $themeValue;
            }
        }

        if ($attributes === []) {
            return null;
        }

        return [
            'classes' => array_values(array_unique($classes)),
            'attributes' => $attributes,
        ];
    }

    /**
     * @return array{classes:list<string>, attributes:array<string, string>}|null
     */
    private function runLanguageDirectionAttrs(\DOMElement $properties): ?array
    {
        $classes = [];
        $attributes = [];

        $language = $this->firstChildElement($properties, self::WORDPROCESSINGML_NS, 'lang');
        if ($language instanceof \DOMElement) {
            $langValues = [];
            foreach ([
                'val' => 'data-docx-lang',
                'bidi' => 'data-docx-lang-bidi',
                'eastAsia' => 'data-docx-lang-east-asia',
            ] as $source => $target) {
                $value = $this->wordAttr($language, $source);
                if ($value !== null && trim($value) !== '') {
                    $langValues[$source] = trim($value);
                    $attributes[$target] = trim($value);
                }
            }

            $htmlLang = $langValues['val'] ?? $langValues['bidi'] ?? $langValues['eastAsia'] ?? null;
            if ($htmlLang !== null) {
                $attributes['lang'] = $htmlLang;
                $classes[] = 'docx-language';
            }
        }

        if ($this->hasOnOffChild($properties, 'rtl')) {
            $attributes['dir'] = 'rtl';
            $classes[] = 'docx-rtl';
        }

        if ($classes === [] && $attributes === []) {
            return null;
        }

        return [
            'classes' => array_values(array_unique($classes)),
            'attributes' => $attributes,
        ];
    }

    private function metadataClassSuffix(string $value): ?string
    {
        $suffix = strtolower(trim($value));
        $suffix = preg_replace('/[^a-z0-9]+/', '-', $suffix) ?? '';
        $suffix = trim($suffix, '-');

        return $suffix === '' ? null : $suffix;
    }

    /**
     * @param array<string, AstNode> $referencedNotes
     */
    private function hyperlinkNode(\DOMElement $hyperlink, ZipPackage $package, ?OpcRelationships $relationships, array $referencedNotes): AstNode
    {
        $children = $this->inlineContainerNodes($hyperlink, $package, $relationships, $referencedNotes);
        $relationshipId = $this->relationshipAttr($hyperlink, 'id');
        $anchor = $this->wordAttr($hyperlink, 'anchor');
        $url = '';

        if ($relationshipId !== null && $relationships instanceof OpcRelationships) {
            $url = $relationships->resolveTarget($relationshipId);
            if ($anchor !== null && $anchor !== '') {
                $url .= '#' . $anchor;
            }
        } elseif ($anchor !== null && $anchor !== '') {
            $url = '#' . $anchor;
        }

        return new AstNode('link', $this->directHyperlinkAttrs($hyperlink, $url, $relationshipId, $anchor), $children);
    }

    /**
     * @return array{url:string, title?:string, classes?:list<string>, attributes?:array<string, string>}
     */
    private function directHyperlinkAttrs(\DOMElement $hyperlink, string $url, ?string $relationshipId, ?string $anchor): array
    {
        $attrs = ['url' => $url];
        $tooltip = $this->wordAttr($hyperlink, 'tooltip');
        $docLocation = $this->wordAttr($hyperlink, 'docLocation');
        $targetFrame = $this->wordAttr($hyperlink, 'tgtFrame');
        $history = $this->wordAttr($hyperlink, 'history');
        $hasDocxMetadata = ($tooltip !== null && $tooltip !== '')
            || ($docLocation !== null && $docLocation !== '')
            || ($targetFrame !== null && $targetFrame !== '')
            || ($history !== null && $history !== '');

        if ($tooltip !== null && $tooltip !== '') {
            $attrs['title'] = $tooltip;
        }
        if (!$hasDocxMetadata) {
            return $attrs;
        }

        $attributes = [];
        if ($tooltip !== null && $tooltip !== '') {
            $attributes['data-docx-tooltip'] = $tooltip;
        }

        foreach ([
            [$relationshipId, 'data-docx-relationship-id'],
            [$anchor, 'data-docx-anchor'],
            [$docLocation, 'data-docx-doc-location'],
            [$targetFrame, 'data-docx-target-frame'],
        ] as [$value, $attributeName]) {
            if (is_string($value) && $value !== '') {
                $attributes[$attributeName] = $value;
            }
        }

        if ($history !== null && $history !== '') {
            $attributes['data-docx-history'] = $this->onOffWordAttr($hyperlink, 'history', false) ? 'true' : 'false';
        }

        $attrs['classes'] = ['docx-hyperlink'];
        $attrs['attributes'] = $attributes;

        return $attrs;
    }

    /**
     * @param array<string, AstNode> $referencedNotes
     * @return list<AstNode>
     */
    private function simpleFieldNodes(\DOMElement $field, ZipPackage $package, ?OpcRelationships $relationships, array $referencedNotes): array
    {
        $children = $this->coalesceTextNodes($this->inlineContainerNodes($field, $package, $relationships, $referencedNotes));
        if ($children === []) {
            return [];
        }

        $attrs = $this->hyperlinkFieldAttrs((string) $this->wordAttr($field, 'instr'));
        if ($attrs === null) {
            $attrs = $this->fieldSpanAttrs((string) $this->wordAttr($field, 'instr'), null);
            if ($attrs === null) {
                return $children;
            }

            return [new AstNode('span', $attrs, $children)];
        }

        return [new AstNode('link', $attrs, $children)];
    }

    /**
     * @return array{url:string, title?:string}|null
     */
    private function hyperlinkFieldAttrs(string $instruction): ?array
    {
        $tokens = $this->fieldInstructionTokens($instruction);
        if ($tokens === [] || strtoupper(array_shift($tokens)) !== 'HYPERLINK') {
            return null;
        }

        $url = null;
        $anchor = null;
        $title = null;
        for ($index = 0, $count = count($tokens); $index < $count; $index++) {
            $token = $tokens[$index];
            if ($token === '') {
                continue;
            }

            if (str_starts_with($token, '\\')) {
                $switch = strtolower(substr($token, 1));
                if (($switch === 'l' || $switch === 'o') && isset($tokens[$index + 1])) {
                    $index++;
                    if ($switch === 'l') {
                        $anchor = $tokens[$index];
                    } else {
                        $title = $tokens[$index];
                    }
                }
                continue;
            }

            $url ??= $token;
        }

        if ($url === null || $url === '') {
            $url = $anchor === null || $anchor === '' ? '' : '#' . $anchor;
        } elseif ($anchor !== null && $anchor !== '') {
            $url .= '#' . $anchor;
        }
        if ($url === '') {
            return null;
        }

        $attrs = ['url' => $url];
        if ($title !== null && $title !== '') {
            $attrs['title'] = $title;
        }

        return $attrs;
    }

    /**
     * @param array{classes:list<string>, attributes:array<string, string>}|null $formField
     * @return array{classes:list<string>, attributes:array<string, string>}|null
     */
    private function fieldSpanAttrs(string $instruction, ?array $formField): ?array
    {
        $tokens = $this->fieldInstructionTokens($instruction);
        if ($tokens === []) {
            return null;
        }

        $fieldNames = [
            'PAGE' => 'page',
            'NUMPAGES' => 'numpages',
            'SECTIONPAGES' => 'sectionpages',
            'DATE' => 'date',
            'TIME' => 'time',
            'CREATEDATE' => 'createdate',
            'SAVEDATE' => 'savedate',
            'PRINTDATE' => 'printdate',
            'AUTHOR' => 'author',
            'COMMENTS' => 'comments',
            'FILENAME' => 'filename',
            'FILESIZE' => 'filesize',
            'KEYWORDS' => 'keywords',
            'LASTSAVEDBY' => 'lastsavedby',
            'SUBJECT' => 'subject',
            'TEMPLATE' => 'template',
            'TITLE' => 'title',
        ];
        $crossReferenceFieldNames = [
            'REF' => 'ref',
            'PAGEREF' => 'pageref',
            'NOTEREF' => 'noteref',
        ];
        $formFieldNames = [
            'FORMTEXT' => ['field' => 'formtext', 'type' => 'text'],
            'FORMCHECKBOX' => ['field' => 'formcheckbox', 'type' => 'checkbox'],
            'FORMDROPDOWN' => ['field' => 'formdropdown', 'type' => 'dropdown'],
        ];
        $dataFieldNames = [
            'MERGEFIELD' => ['field' => 'mergefield', 'type' => 'mail-merge'],
            'DOCVARIABLE' => ['field' => 'docvariable', 'type' => 'document-variable'],
            'DOCPROPERTY' => ['field' => 'docproperty', 'type' => 'document-property'],
        ];
        $generatedFieldNames = [
            'TOC' => ['field' => 'toc', 'type' => 'table-of-contents'],
            'INDEX' => ['field' => 'index', 'type' => 'document-index'],
            'BIBLIOGRAPHY' => ['field' => 'bibliography', 'type' => 'bibliography'],
            'CITATION' => ['field' => 'citation', 'type' => 'citation'],
        ];

        $fieldName = strtoupper(array_shift($tokens));
        if ($fieldName === 'SEQ') {
            return $this->sequenceFieldSpanAttrs($tokens, $instruction);
        }

        if (isset($formFieldNames[$fieldName])) {
            return $this->formFieldSpanAttrs(
                $formFieldNames[$fieldName]['field'],
                $formFieldNames[$fieldName]['type'],
                $tokens,
                $instruction,
                $formField
            );
        }

        if (isset($dataFieldNames[$fieldName])) {
            return $this->dataFieldSpanAttrs(
                $dataFieldNames[$fieldName]['field'],
                $dataFieldNames[$fieldName]['type'],
                $tokens,
                $instruction
            );
        }

        if (isset($generatedFieldNames[$fieldName])) {
            return $this->generatedFieldSpanAttrs(
                $generatedFieldNames[$fieldName]['field'],
                $generatedFieldNames[$fieldName]['type'],
                $tokens,
                $instruction
            );
        }

        if (isset($crossReferenceFieldNames[$fieldName])) {
            return $this->crossReferenceFieldSpanAttrs($crossReferenceFieldNames[$fieldName], $tokens, $instruction);
        }

        if (!isset($fieldNames[$fieldName])) {
            return null;
        }

        $fieldKey = $fieldNames[$fieldName];
        $attributes = [
            'data-docx-field' => $fieldKey,
            'data-docx-field-instruction' => $this->normalizeFieldInstruction($instruction),
        ];

        $format = $this->fieldFormatSwitchValue($tokens);
        if ($format !== null && $format !== '') {
            $attributes['data-docx-field-format'] = $format;
        }

        if ($fieldKey === 'filename' && $this->fieldHasSwitch($tokens, 'p')) {
            $attributes['data-docx-field-path'] = 'true';
            return [
                'classes' => ['docx-field', 'docx-field-' . $fieldKey, 'docx-field-path'],
                'attributes' => $attributes,
            ];
        }

        return [
            'classes' => ['docx-field', 'docx-field-' . $fieldKey],
            'attributes' => $attributes,
        ];
    }

    /**
     * @param list<string> $tokens
     * @param array{classes:list<string>, attributes:array<string, string>}|null $formField
     * @return array{classes:list<string>, attributes:array<string, string>}
     */
    private function formFieldSpanAttrs(
        string $fieldKey,
        string $formType,
        array $tokens,
        string $instruction,
        ?array $formField
    ): array {
        $classes = ['docx-field', 'docx-field-' . $fieldKey, 'docx-form-field', 'docx-form-field-' . $formType];
        $attributes = [
            'data-docx-field' => $fieldKey,
            'data-docx-field-instruction' => $this->normalizeFieldInstruction($instruction),
            'data-docx-form-field-type' => $formType,
        ];

        $format = $this->fieldFormatSwitchValue($tokens);
        if ($format !== null && $format !== '') {
            $attributes['data-docx-field-format'] = $format;
        }

        if ($formField !== null) {
            array_push($classes, ...$formField['classes']);
            foreach ($formField['attributes'] as $name => $value) {
                $attributes[$name] = $value;
            }
        }

        return [
            'classes' => array_values(array_unique($classes)),
            'attributes' => $attributes,
        ];
    }

    /**
     * @param list<string> $tokens
     * @return array{classes:list<string>, attributes:array<string, string>}
     */
    private function dataFieldSpanAttrs(string $fieldKey, string $dataType, array $tokens, string $instruction): array
    {
        $classes = ['docx-field', 'docx-field-' . $fieldKey, 'docx-data-field', 'docx-data-field-' . $dataType];
        $attributes = [
            'data-docx-field' => $fieldKey,
            'data-docx-field-instruction' => $this->normalizeFieldInstruction($instruction),
            'data-docx-data-field-type' => $dataType,
        ];

        $name = $this->fieldTargetToken($tokens);
        if ($name !== null && $name !== '') {
            $attributes['data-docx-data-field-name'] = $name;
        }

        $format = $this->fieldFormatSwitchValue($tokens);
        if ($format !== null && $format !== '') {
            $attributes['data-docx-field-format'] = $format;
        }

        if ($fieldKey === 'mergefield') {
            $beforeText = $this->fieldSwitchValue($tokens, 'b');
            if ($beforeText !== null) {
                $classes[] = 'docx-data-field-before-text';
                $attributes['data-docx-data-field-before-text'] = $beforeText;
            }

            $afterText = $this->fieldSwitchValue($tokens, 'f');
            if ($afterText !== null) {
                $classes[] = 'docx-data-field-after-text';
                $attributes['data-docx-data-field-after-text'] = $afterText;
            }
        }

        return [
            'classes' => array_values(array_unique($classes)),
            'attributes' => $attributes,
        ];
    }

    /**
     * @param list<string> $tokens
     * @return array{classes:list<string>, attributes:array<string, string>}
     */
    private function generatedFieldSpanAttrs(string $fieldKey, string $generatedType, array $tokens, string $instruction): array
    {
        $classes = [
            'docx-field',
            'docx-field-' . $fieldKey,
            'docx-generated-field',
            'docx-generated-field-' . $fieldKey,
        ];
        $attributes = [
            'data-docx-field' => $fieldKey,
            'data-docx-field-instruction' => $this->normalizeFieldInstruction($instruction),
            'data-docx-generated-field-type' => $generatedType,
        ];

        $target = $this->fieldTargetTokenSkippingSwitchValues($tokens, [
            '*',
            '@',
            'b',
            'c',
            'd',
            'e',
            'f',
            'g',
            'k',
            'l',
            'n',
            'o',
            'p',
            's',
            't',
            'v',
            'z',
        ]);
        if ($target !== null && $target !== '') {
            $attributes['data-docx-field-target'] = $target;
        }

        $format = $this->fieldFormatSwitchValue($tokens);
        if ($format !== null && $format !== '') {
            $attributes['data-docx-field-format'] = $format;
        }

        foreach ([
            'b' => 'bookmark',
            'c' => 'columns',
            'd' => 'chapter-separator',
            'e' => 'entry-separator',
            'f' => 'entry-type',
            'g' => 'glossary-separator',
            'k' => 'see-separator',
            'l' => 'locale-id',
            'o' => 'outline-levels',
            'p' => 'page-number-separator',
            's' => 'sequence',
            't' => 'style-levels',
            'v' => 'citation-volume',
        ] as $switch => $attributeSuffix) {
            $value = $this->fieldSwitchValue($tokens, $switch);
            if ($value !== null && $value !== '') {
                $attributes['data-docx-field-' . $attributeSuffix] = $value;
            }
        }

        $omitPageNumbers = $this->fieldOptionalSwitchValue($tokens, 'n');
        if ($this->fieldHasSwitch($tokens, 'n')) {
            $classes[] = 'docx-field-omit-page-numbers';
            $attributes['data-docx-field-omit-page-numbers'] = 'true';
            if ($omitPageNumbers !== null && $omitPageNumbers !== '') {
                $attributes['data-docx-field-omit-page-number-levels'] = $omitPageNumbers;
            }
        }

        if ($this->fieldHasSwitch($tokens, 'h')) {
            $classes[] = 'docx-field-hyperlink';
            $attributes['data-docx-field-hyperlink'] = 'true';
        }

        if ($this->fieldHasSwitch($tokens, 'u')) {
            $classes[] = 'docx-field-outline-levels';
            $attributes['data-docx-field-use-outline-levels'] = 'true';
        }

        if ($this->fieldHasSwitch($tokens, 'z')) {
            $zValue = $this->fieldSwitchValue($tokens, 'z');
            if ($fieldKey === 'toc' && ($zValue === null || $zValue === '')) {
                $classes[] = 'docx-field-hide-web-layout';
                $attributes['data-docx-field-hide-web-layout'] = 'true';
            } elseif ($zValue !== null && $zValue !== '') {
                $attributes['data-docx-field-language-id'] = $zValue;
            }
        }

        return [
            'classes' => array_values(array_unique($classes)),
            'attributes' => $attributes,
        ];
    }

    /**
     * @return array{classes:list<string>, attributes:array<string, string>}|null
     */
    private function runFormFieldAttrs(\DOMElement $run): ?array
    {
        $fieldChar = $this->firstChildElement($run, self::WORDPROCESSINGML_NS, 'fldChar');
        if (!$fieldChar instanceof \DOMElement) {
            return null;
        }

        $formField = $this->firstChildElement($fieldChar, self::WORDPROCESSINGML_NS, 'ffData');
        if (!$formField instanceof \DOMElement) {
            return null;
        }

        return $this->formFieldMetadataAttrs($formField);
    }

    /**
     * @return array{classes:list<string>, attributes:array<string, string>}|null
     */
    private function formFieldMetadataAttrs(\DOMElement $formField): ?array
    {
        $classes = [];
        $attributes = [];

        $name = $this->wordChildValue($formField, 'name');
        if ($name !== null) {
            $classes[] = 'docx-form-field-named';
            $attributes['data-docx-form-field-name'] = $name;
        }

        $enabled = $this->formFieldOnOffChildValue($formField, 'enabled');
        if ($enabled !== null) {
            $classes[] = $enabled ? 'docx-form-field-enabled' : 'docx-form-field-disabled';
            $attributes['data-docx-form-field-enabled'] = $enabled ? 'true' : 'false';
        }

        $calcOnExit = $this->formFieldOnOffChildValue($formField, 'calcOnExit');
        if ($calcOnExit !== null) {
            if ($calcOnExit) {
                $classes[] = 'docx-form-field-calc-on-exit';
            }
            $attributes['data-docx-form-field-calc-on-exit'] = $calcOnExit ? 'true' : 'false';
        }

        foreach ([
            'entryMacro' => 'entry-macro',
            'exitMacro' => 'exit-macro',
        ] as $localName => $targetName) {
            $value = $this->wordChildValue($formField, $localName);
            if ($value !== null) {
                $attributes['data-docx-form-field-' . $targetName] = $value;
            }
        }

        foreach ([
            'helpText' => 'help-text',
            'statusText' => 'status-text',
        ] as $localName => $targetName) {
            $text = $this->firstChildElement($formField, self::WORDPROCESSINGML_NS, $localName);
            if (!$text instanceof \DOMElement) {
                continue;
            }

            $value = trim((string) ($this->wordAttr($text, 'val') ?? ''));
            if ($value !== '') {
                $attributes['data-docx-form-field-' . $targetName] = $value;
            }

            $type = trim((string) ($this->wordAttr($text, 'type') ?? ''));
            if ($type !== '') {
                $attributes['data-docx-form-field-' . $targetName . '-type'] = $type;
            }
        }

        $textInput = $this->firstChildElement($formField, self::WORDPROCESSINGML_NS, 'textInput');
        if ($textInput instanceof \DOMElement) {
            $classes[] = 'docx-form-field-text-input';
            foreach ([
                'type' => 'text-type',
                'default' => 'text-default',
                'format' => 'text-format',
            ] as $localName => $targetName) {
                $value = $this->wordChildValue($textInput, $localName);
                if ($value !== null) {
                    $attributes['data-docx-form-field-' . $targetName] = $value;
                }
            }

            $maxLength = $this->formFieldIntChildValue($textInput, 'maxLength');
            if ($maxLength !== null) {
                $attributes['data-docx-form-field-text-max-length'] = (string) $maxLength;
            }
        }

        $checkBox = $this->firstChildElement($formField, self::WORDPROCESSINGML_NS, 'checkBox');
        if ($checkBox instanceof \DOMElement) {
            $classes[] = 'docx-form-field-checkbox-data';
            $size = $this->formFieldIntChildValue($checkBox, 'size');
            if ($size !== null) {
                $attributes['data-docx-form-field-checkbox-size-half-points'] = (string) $size;
            }

            foreach ([
                'default' => 'checkbox-default',
                'checked' => 'checkbox-checked',
                'sizeAuto' => 'checkbox-size-auto',
            ] as $localName => $targetName) {
                $value = $this->formFieldOnOffChildValue($checkBox, $localName);
                if ($value !== null) {
                    $attributes['data-docx-form-field-' . $targetName] = $value ? 'true' : 'false';
                }
            }
        }

        $dropdown = $this->firstChildElement($formField, self::WORDPROCESSINGML_NS, 'ddList');
        if ($dropdown instanceof \DOMElement) {
            $classes[] = 'docx-form-field-dropdown-data';
            foreach ([
                'default' => 'dropdown-default-index',
                'result' => 'dropdown-result-index',
            ] as $localName => $targetName) {
                $value = $this->formFieldIntChildValue($dropdown, $localName);
                if ($value !== null) {
                    $attributes['data-docx-form-field-' . $targetName] = (string) $value;
                }
            }

            $entries = [];
            foreach ($dropdown->childNodes as $child) {
                if (!$child instanceof \DOMElement || !$this->isWordElement($child, 'listEntry')) {
                    continue;
                }

                $value = trim((string) ($this->wordAttr($child, 'val') ?? ''));
                if ($value !== '') {
                    $entries[] = $value;
                }
            }

            if ($entries !== []) {
                $attributes['data-docx-form-field-dropdown-entry-count'] = (string) count($entries);
                foreach ($entries as $index => $entry) {
                    $attributes['data-docx-form-field-dropdown-entry-' . ($index + 1)] = $entry;
                }
            }
        }

        if ($classes === [] && $attributes === []) {
            return null;
        }

        return [
            'classes' => array_values(array_unique($classes)),
            'attributes' => $attributes,
        ];
    }

    private function formFieldOnOffChildValue(\DOMElement $element, string $localName): ?bool
    {
        $child = $this->firstChildElement($element, self::WORDPROCESSINGML_NS, $localName);
        if (!$child instanceof \DOMElement) {
            return null;
        }

        return $this->onOffWordAttr($child, 'val', true);
    }

    private function formFieldIntChildValue(\DOMElement $element, string $localName): ?int
    {
        $child = $this->firstChildElement($element, self::WORDPROCESSINGML_NS, $localName);

        return $child instanceof \DOMElement ? $this->optionalIntWordAttr($child, 'val') : null;
    }

    /**
     * @param list<string> $tokens
     * @return array{classes:list<string>, attributes:array<string, string>}
     */
    private function sequenceFieldSpanAttrs(array $tokens, string $instruction): array
    {
        $classes = ['docx-field', 'docx-field-seq'];
        $attributes = [
            'data-docx-field' => 'seq',
            'data-docx-field-instruction' => $this->normalizeFieldInstruction($instruction),
        ];

        $sequence = $this->fieldTargetToken($tokens);
        if ($sequence !== null && $sequence !== '') {
            $attributes['data-docx-field-sequence'] = $sequence;
        }

        $format = $this->fieldFormatSwitchValue($tokens);
        if ($format !== null && $format !== '') {
            $attributes['data-docx-field-format'] = $format;
        }

        for ($index = 0, $count = count($tokens); $index < $count; $index++) {
            $token = $tokens[$index];
            if (!str_starts_with($token, '\\')) {
                continue;
            }

            $switch = strtolower(substr($token, 1));
            if ($switch === 'c') {
                $classes[] = 'docx-field-current-sequence';
                $attributes['data-docx-field-current-sequence'] = 'true';
                continue;
            }

            if ($switch === 'h') {
                $classes[] = 'docx-field-hidden';
                $attributes['data-docx-field-hidden'] = 'true';
                continue;
            }

            if ($switch === 'n') {
                $classes[] = 'docx-field-next-sequence';
                $attributes['data-docx-field-next-sequence'] = 'true';
                continue;
            }

            if ($switch === 'r' && isset($tokens[$index + 1]) && !str_starts_with($tokens[$index + 1], '\\')) {
                $classes[] = 'docx-field-reset-number';
                $attributes['data-docx-field-reset-number'] = $tokens[++$index];
                continue;
            }

            if ($switch === 's' && isset($tokens[$index + 1]) && !str_starts_with($tokens[$index + 1], '\\')) {
                $classes[] = 'docx-field-reset-heading-level';
                $attributes['data-docx-field-reset-heading-level'] = $tokens[++$index];
            }
        }

        return [
            'classes' => array_values(array_unique($classes)),
            'attributes' => $attributes,
        ];
    }

    /**
     * @param list<string> $tokens
     * @return array{classes:list<string>, attributes:array<string, string>}
     */
    private function crossReferenceFieldSpanAttrs(string $fieldKey, array $tokens, string $instruction): array
    {
        $classes = ['docx-field', 'docx-field-' . $fieldKey];
        $attributes = [
            'data-docx-field' => $fieldKey,
            'data-docx-field-instruction' => $this->normalizeFieldInstruction($instruction),
        ];

        $target = $this->fieldTargetToken($tokens);
        if ($target !== null && $target !== '') {
            $attributes['data-docx-field-target'] = $target;
        }

        $format = $this->fieldFormatSwitchValue($tokens);
        if ($format !== null && $format !== '') {
            $attributes['data-docx-field-format'] = $format;
        }

        $switches = [
            'h' => ['docx-field-hyperlink', 'data-docx-field-hyperlink'],
            'p' => ['docx-field-relative-position', 'data-docx-field-relative-position'],
            'n' => ['docx-field-number', 'data-docx-field-number'],
            'r' => ['docx-field-relative-number', 'data-docx-field-relative-number'],
            'w' => ['docx-field-full-context', 'data-docx-field-full-context'],
        ];
        foreach ($tokens as $token) {
            if (!str_starts_with($token, '\\')) {
                continue;
            }

            $switch = strtolower(substr($token, 1));
            if (!isset($switches[$switch])) {
                continue;
            }

            [$class, $attribute] = $switches[$switch];
            if (!in_array($class, $classes, true)) {
                $classes[] = $class;
            }
            $attributes[$attribute] = 'true';
        }

        return [
            'classes' => $classes,
            'attributes' => $attributes,
        ];
    }

    /**
     * @param list<string> $tokens
     */
    private function fieldTargetToken(array $tokens): ?string
    {
        for ($index = 0, $count = count($tokens); $index < $count; $index++) {
            $token = $tokens[$index];
            if ($token === '') {
                continue;
            }

            if (str_starts_with($token, '\\')) {
                $switch = strtolower(substr($token, 1));
                if (($switch === '*' || $switch === '@') && isset($tokens[$index + 1])) {
                    $index++;
                }
                continue;
            }

            return $token;
        }

        return null;
    }

    /**
     * @param list<string> $tokens
     */
    private function fieldFormatSwitchValue(array $tokens): ?string
    {
        for ($index = 0, $count = count($tokens); $index < $count; $index++) {
            $token = $tokens[$index];
            if (!str_starts_with($token, '\\')) {
                continue;
            }

            $switch = strtolower(substr($token, 1));
            if (($switch === '*' || $switch === '@') && isset($tokens[$index + 1]) && !str_starts_with($tokens[$index + 1], '\\')) {
                return $tokens[$index + 1];
            }
        }

        return null;
    }

    /**
     * @param list<string> $tokens
     */
    private function fieldSwitchValue(array $tokens, string $switchName): ?string
    {
        $switchName = strtolower($switchName);
        for ($index = 0, $count = count($tokens); $index < $count; $index++) {
            $token = $tokens[$index];
            if (!str_starts_with($token, '\\')) {
                continue;
            }

            $switch = strtolower(substr($token, 1));
            if ($switch === $switchName && isset($tokens[$index + 1]) && !str_starts_with($tokens[$index + 1], '\\')) {
                return $tokens[$index + 1];
            }
        }

        return null;
    }

    /**
     * @param list<string> $tokens
     */
    private function fieldHasSwitch(array $tokens, string $switchName): bool
    {
        $switchName = strtolower($switchName);
        foreach ($tokens as $token) {
            if (str_starts_with($token, '\\') && strtolower(substr($token, 1)) === $switchName) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param list<string> $tokens
     */
    private function fieldOptionalSwitchValue(array $tokens, string $switchName): ?string
    {
        $switchName = strtolower($switchName);
        for ($index = 0, $count = count($tokens); $index < $count; $index++) {
            $token = $tokens[$index];
            if (!str_starts_with($token, '\\') || strtolower(substr($token, 1)) !== $switchName) {
                continue;
            }

            return isset($tokens[$index + 1]) && !str_starts_with($tokens[$index + 1], '\\')
                ? $tokens[$index + 1]
                : null;
        }

        return null;
    }

    /**
     * @param list<string> $tokens
     * @param list<string> $switchesWithValues
     */
    private function fieldTargetTokenSkippingSwitchValues(array $tokens, array $switchesWithValues): ?string
    {
        $switchesWithValues = array_fill_keys(array_map('strtolower', $switchesWithValues), true);
        for ($index = 0, $count = count($tokens); $index < $count; $index++) {
            $token = $tokens[$index];
            if ($token === '') {
                continue;
            }

            if (str_starts_with($token, '\\')) {
                $switch = strtolower(substr($token, 1));
                if (isset($switchesWithValues[$switch]) && isset($tokens[$index + 1]) && !str_starts_with($tokens[$index + 1], '\\')) {
                    $index++;
                }
                continue;
            }

            return $token;
        }

        return null;
    }

    private function normalizeFieldInstruction(string $instruction): string
    {
        return preg_replace('/\s+/u', ' ', trim($instruction)) ?? trim($instruction);
    }

    /**
     * @return list<string>
     */
    private function fieldInstructionTokens(string $instruction): array
    {
        $tokens = [];
        $length = strlen($instruction);
        for ($index = 0; $index < $length;) {
            while ($index < $length && ctype_space($instruction[$index])) {
                $index++;
            }
            if ($index >= $length) {
                break;
            }

            if ($instruction[$index] === '"') {
                $index++;
                $token = '';
                while ($index < $length) {
                    $char = $instruction[$index];
                    if ($char === '"' && ($index === 0 || $instruction[$index - 1] !== '\\')) {
                        $index++;
                        break;
                    }
                    if ($char === '\\' && isset($instruction[$index + 1]) && $instruction[$index + 1] === '"') {
                        $token .= '"';
                        $index += 2;
                        continue;
                    }

                    $token .= $char;
                    $index++;
                }
                $tokens[] = $token;
                continue;
            }

            $start = $index;
            while ($index < $length && !ctype_space($instruction[$index])) {
                $index++;
            }
            $tokens[] = substr($instruction, $start, $index - $start);
        }

        return $tokens;
    }

    private function runFieldCharType(\DOMElement $run): ?string
    {
        $fieldChar = $this->firstChildElement($run, self::WORDPROCESSINGML_NS, 'fldChar');
        if (!$fieldChar instanceof \DOMElement) {
            return null;
        }

        $type = $this->wordAttr($fieldChar, 'fldCharType');

        return $type === null ? null : strtolower($type);
    }

    private function fieldInstructionText(\DOMElement $element): string
    {
        if ($this->isWordElement($element, 'r')) {
            return $this->runInstructionText($element);
        }

        $text = '';
        foreach ($element->childNodes as $child) {
            if ($child instanceof \DOMElement) {
                $text .= $this->fieldInstructionText($child);
            }
        }

        return $text;
    }

    private function runInstructionText(\DOMElement $run): string
    {
        $text = '';
        foreach ($run->childNodes as $child) {
            if ($child instanceof \DOMElement && $this->isWordElement($child, 'instrText')) {
                $text .= $child->textContent;
            }
        }

        return $text;
    }

    /**
     * @return list<AstNode>
     */
    private function drawingNodes(\DOMElement $drawing, ZipPackage $package, ?OpcRelationships $relationships): array
    {
        $nodes = [];
        if ($relationships instanceof OpcRelationships) {
            foreach ($drawing->getElementsByTagNameNS(self::DRAWINGML_MAIN_NS, 'blip') as $blip) {
                if (!$blip instanceof \DOMElement) {
                    continue;
                }

                $embed = $this->relationshipAttr($blip, 'embed');
                $link = $this->relationshipAttr($blip, 'link');
                $relationshipId = $embed ?? $link;
                if ($relationshipId === null) {
                    continue;
                }

                $relationship = $relationships->byId($relationshipId);
                if (!$relationship instanceof OpcRelationship || $relationship->type !== self::REL_TYPE_IMAGE) {
                    continue;
                }

                $docPr = $this->drawingPropertiesForBlip($blip, $drawing);
                $picture = $this->drawingPictureForBlip($blip, $drawing);
                $pictureProperties = $picture instanceof \DOMElement
                    ? $this->drawingPictureNonVisualDrawingProperties($picture)
                    : null;
                $alt = $docPr instanceof \DOMElement ? (string) ($docPr->getAttribute('descr') ?: $docPr->getAttribute('name')) : '';
                if ($alt === '' && $pictureProperties instanceof \DOMElement) {
                    $alt = (string) ($pictureProperties->getAttribute('descr') ?: $pictureProperties->getAttribute('name'));
                }
                $title = $docPr instanceof \DOMElement ? $docPr->getAttribute('title') : '';
                if ($title === '' && $pictureProperties instanceof \DOMElement) {
                    $title = $pictureProperties->getAttribute('title');
                }
                $image = $this->relationshipImageNode(
                    $relationshipId,
                    $relationship,
                    $package,
                    $relationships,
                    $alt,
                    $title,
                    $this->mergeNodeMetadataAttrs(
                        $this->drawingGeometryAttrs($this->drawingContainerForElement($blip, $drawing)),
                        $this->drawingPictureMetadataAttrs($blip, $drawing)
                    )
                );
                if ($image instanceof AstNode) {
                    $nodes[] = $this->drawingHyperlinkWrappedNode($image, $docPr, $relationships);
                }
            }

            array_push($nodes, ...$this->chartDrawingNodes($drawing, $package, $relationships));
            array_push($nodes, ...$this->diagramDrawingNodes($drawing, $package, $relationships));
        }

        array_push($nodes, ...$this->drawingConnectorNodes($drawing));
        array_push($nodes, ...$this->drawingGroupShapeNodes($drawing));
        array_push($nodes, ...$this->drawingTextNodes($drawing));

        return $nodes;
    }

    /**
     * @return list<AstNode>
     */
    private function drawingTextNodes(\DOMElement $drawing): array
    {
        $nodes = [];
        foreach ($drawing->getElementsByTagNameNS(self::DRAWINGML_MAIN_NS, 'txBody') as $textBody) {
            if (!$textBody instanceof \DOMElement) {
                continue;
            }

            $paragraphCount = 0;
            $children = $this->drawingTextBodyInlineNodes($textBody, $paragraphCount);
            if ($children === []) {
                continue;
            }

            $nodes[] = new AstNode(
                'span',
                $this->drawingTextAttrs(
                    $this->drawingPropertiesForElement($textBody, $drawing),
                    $paragraphCount,
                    $this->drawingGeometryAttrs($this->drawingContainerForElement($textBody, $drawing))
                ),
                $children
            );
        }

        return $nodes;
    }

    /**
     * @return list<AstNode>
     */
    private function drawingTextBodyInlineNodes(\DOMElement $textBody, int &$paragraphCount): array
    {
        $nodes = [];
        $paragraphCount = 0;
        foreach ($textBody->childNodes as $child) {
            if (!$child instanceof \DOMElement || $child->namespaceURI !== self::DRAWINGML_MAIN_NS || $child->localName !== 'p') {
                continue;
            }

            $paragraphNodes = $this->drawingTextParagraphInlineNodes($child);
            if ($paragraphNodes === []) {
                continue;
            }

            if ($nodes !== []) {
                $nodes[] = new AstNode('linebreak');
            }
            array_push($nodes, ...$paragraphNodes);
            $paragraphCount++;
        }

        return $this->coalesceTextNodes($nodes);
    }

    /**
     * @return list<AstNode>
     */
    private function drawingTextParagraphInlineNodes(\DOMElement $paragraph): array
    {
        $nodes = [];
        foreach ($paragraph->childNodes as $child) {
            if (!$child instanceof \DOMElement || $child->namespaceURI !== self::DRAWINGML_MAIN_NS) {
                continue;
            }

            if ($child->localName === 'r' || $child->localName === 'fld') {
                array_push($nodes, ...$this->drawingTextRunInlineNodes($child));
                continue;
            }

            if ($child->localName === 'br') {
                $nodes[] = new AstNode('linebreak');
                continue;
            }

            if ($child->localName === 'tab') {
                $nodes[] = new AstNode('text', ['text' => "\t"]);
            }
        }

        $nodes = $this->coalesceTextNodes($nodes);
        if ($nodes === []) {
            return [];
        }

        $properties = $this->firstChildElement($paragraph, self::DRAWINGML_MAIN_NS, 'pPr');
        $attrs = $properties instanceof \DOMElement ? $this->drawingTextParagraphMetadataAttrs($properties) : null;
        if ($attrs === null) {
            return $nodes;
        }

        return [new AstNode('span', $attrs, $nodes)];
    }

    /**
     * @return list<AstNode>
     */
    private function drawingTextRunInlineNodes(\DOMElement $run): array
    {
        $nodes = [];
        foreach ($run->childNodes as $child) {
            if (!$child instanceof \DOMElement || $child->namespaceURI !== self::DRAWINGML_MAIN_NS) {
                continue;
            }

            if ($child->localName === 't') {
                $nodes[] = new AstNode('text', ['text' => $child->textContent]);
                continue;
            }

            if ($child->localName === 'br') {
                $nodes[] = new AstNode('linebreak');
                continue;
            }

            if ($child->localName === 'tab') {
                $nodes[] = new AstNode('text', ['text' => "\t"]);
            }
        }

        $nodes = $this->coalesceTextNodes($nodes);
        if ($nodes === []) {
            return [];
        }

        $properties = $this->firstChildElement($run, self::DRAWINGML_MAIN_NS, 'rPr');
        $attrs = $properties instanceof \DOMElement ? $this->drawingTextRunMetadataAttrs($properties) : null;
        if ($attrs === null) {
            return $nodes;
        }

        return [new AstNode('span', $attrs, $nodes)];
    }

    /**
     * @return array{classes:list<string>, attributes:array<string, string>}|null
     */
    private function drawingTextParagraphMetadataAttrs(\DOMElement $properties): ?array
    {
        $classes = [];
        $attributes = [];

        foreach ([
            'algn' => 'align',
            'lvl' => 'level',
            'marL' => 'margin-left-emu',
            'marR' => 'margin-right-emu',
            'indent' => 'indent-emu',
            'defTabSz' => 'default-tab-size-emu',
        ] as $source => $target) {
            $value = trim($properties->getAttribute($source));
            if ($value === '') {
                continue;
            }

            $attributes['data-docx-drawing-text-paragraph-' . $target] = $value;
            if ($source === 'algn') {
                $suffix = $this->metadataClassSuffix($value);
                if ($suffix !== null) {
                    $classes[] = 'docx-drawing-text-align-' . $suffix;
                }
            }
            if ($source === 'lvl') {
                $classes[] = 'docx-drawing-text-level';
            }
        }

        foreach ([
            'rtl' => 'right-to-left',
            'eaLnBrk' => 'east-asian-line-break',
            'latinLnBrk' => 'latin-line-break',
            'hangingPunct' => 'hanging-punctuation',
        ] as $source => $target) {
            $value = $this->normalizedOnOffAttribute($properties, (string) $source);
            if ($value === null) {
                continue;
            }

            $attributes['data-docx-drawing-text-paragraph-' . $target] = $value;
            if ($value === 'true') {
                $classes[] = 'docx-drawing-text-' . $target;
            }
        }

        foreach ([
            'lnSpc' => 'line-spacing',
            'spcBef' => 'space-before',
            'spcAft' => 'space-after',
        ] as $localName => $target) {
            $spacing = $this->firstChildElement($properties, self::DRAWINGML_MAIN_NS, (string) $localName);
            if (!$spacing instanceof \DOMElement) {
                continue;
            }

            foreach ($spacing->childNodes as $child) {
                if (!$child instanceof \DOMElement || $child->namespaceURI !== self::DRAWINGML_MAIN_NS) {
                    continue;
                }
                if (!in_array($child->localName, ['spcPts', 'spcPct'], true)) {
                    continue;
                }

                $value = trim($child->getAttribute('val'));
                if ($value === '') {
                    continue;
                }

                $classes[] = 'docx-drawing-text-' . $target;
                $attributes['data-docx-drawing-text-paragraph-' . $target . '-kind'] = $child->localName === 'spcPts' ? 'points' : 'percent';
                $attributes['data-docx-drawing-text-paragraph-' . $target . '-value'] = $value;
                break;
            }
        }

        $bulletNone = $this->firstChildElement($properties, self::DRAWINGML_MAIN_NS, 'buNone');
        if ($bulletNone instanceof \DOMElement) {
            $classes[] = 'docx-drawing-text-bullet-none';
            $attributes['data-docx-drawing-text-bullet'] = 'none';
        }

        $bulletChar = $this->firstChildElement($properties, self::DRAWINGML_MAIN_NS, 'buChar');
        if ($bulletChar instanceof \DOMElement) {
            $char = trim($bulletChar->getAttribute('char'));
            if ($char !== '') {
                $classes[] = 'docx-drawing-text-bullet-char';
                $attributes['data-docx-drawing-text-bullet'] = 'char';
                $attributes['data-docx-drawing-text-bullet-char'] = $char;
            }
        }

        $autoNumber = $this->firstChildElement($properties, self::DRAWINGML_MAIN_NS, 'buAutoNum');
        if ($autoNumber instanceof \DOMElement) {
            $classes[] = 'docx-drawing-text-auto-number';
            $attributes['data-docx-drawing-text-bullet'] = 'auto-number';
            foreach ([
                'type' => 'type',
                'startAt' => 'start-at',
            ] as $source => $target) {
                $value = trim($autoNumber->getAttribute($source));
                if ($value !== '') {
                    $attributes['data-docx-drawing-text-auto-number-' . $target] = $value;
                }
            }
        }

        $bulletFont = $this->firstChildElement($properties, self::DRAWINGML_MAIN_NS, 'buFont');
        if ($bulletFont instanceof \DOMElement) {
            $typeface = trim($bulletFont->getAttribute('typeface'));
            if ($typeface !== '') {
                $classes[] = 'docx-drawing-text-bullet-font';
                $attributes['data-docx-drawing-text-bullet-font'] = $typeface;
            }
        }

        if ($classes === [] && $attributes === []) {
            return null;
        }

        array_unshift($classes, 'docx-drawing-text-paragraph-properties');

        return [
            'classes' => array_values(array_unique($classes)),
            'attributes' => $attributes,
        ];
    }

    /**
     * @return array{classes:list<string>, attributes:array<string, string>}|null
     */
    private function drawingTextRunMetadataAttrs(\DOMElement $properties): ?array
    {
        $classes = [];
        $attributes = [];

        foreach ([
            'b' => ['bold', 'docx-drawing-text-bold'],
            'i' => ['italic', 'docx-drawing-text-italic'],
            'kumimoji' => ['kumimoji', 'docx-drawing-text-kumimoji'],
            'dirty' => ['dirty', 'docx-drawing-text-dirty'],
            'smtClean' => ['smart-tag-clean', 'docx-drawing-text-smart-tag-clean'],
            'noProof' => ['no-proof', 'docx-drawing-text-no-proof'],
        ] as $source => [$target, $className]) {
            $value = $this->normalizedOnOffAttribute($properties, (string) $source);
            if ($value === null) {
                continue;
            }

            $attributes['data-docx-drawing-text-' . $target] = $value;
            if ($value === 'true') {
                $classes[] = $className;
            }
        }

        foreach ([
            'u' => ['underline', 'docx-drawing-text-underline'],
            'strike' => ['strike', 'docx-drawing-text-strike'],
            'cap' => ['capitalization', 'docx-drawing-text-capitalization'],
        ] as $source => [$target, $className]) {
            $value = trim($properties->getAttribute($source));
            if ($value === '') {
                continue;
            }

            $attributes['data-docx-drawing-text-' . $target] = $value;
            if (!in_array(strtolower($value), ['none', 'nostrike'], true)) {
                $classes[] = $className;
                $suffix = $this->metadataClassSuffix($value);
                if ($suffix !== null) {
                    $classes[] = $className . '-' . $suffix;
                }
            }
        }

        foreach ([
            'sz' => 'font-size-hundredths-points',
            'baseline' => 'baseline',
            'spc' => 'character-spacing',
            'lang' => 'language',
            'altLang' => 'alternate-language',
        ] as $source => $target) {
            $value = trim($properties->getAttribute($source));
            if ($value !== '') {
                $attributes['data-docx-drawing-text-' . $target] = $value;
            }
        }

        $solidFill = $this->firstChildElement($properties, self::DRAWINGML_MAIN_NS, 'solidFill');
        if ($solidFill instanceof \DOMElement) {
            $color = $this->drawingFirstColorValue($solidFill);
            if ($color !== null) {
                $classes[] = 'docx-drawing-text-fill';
                $attributes['data-docx-drawing-text-fill-color'] = $color;
            }
        }

        $highlight = $this->firstChildElement($properties, self::DRAWINGML_MAIN_NS, 'highlight');
        if ($highlight instanceof \DOMElement) {
            $color = $this->drawingFirstColorValue($highlight);
            if ($color !== null) {
                $classes[] = 'docx-drawing-text-highlight';
                $attributes['data-docx-drawing-text-highlight-color'] = $color;
            }
        }

        foreach ([
            'latin' => 'latin',
            'ea' => 'east-asian',
            'cs' => 'complex-script',
            'sym' => 'symbol',
        ] as $localName => $target) {
            $font = $this->firstChildElement($properties, self::DRAWINGML_MAIN_NS, (string) $localName);
            if (!$font instanceof \DOMElement) {
                continue;
            }

            $typeface = trim($font->getAttribute('typeface'));
            if ($typeface !== '') {
                $classes[] = 'docx-drawing-text-font';
                $attributes['data-docx-drawing-text-font-' . $target] = $typeface;
            }
        }

        if ($classes === [] && $attributes === []) {
            return null;
        }

        array_unshift($classes, 'docx-drawing-text-run-properties');

        return [
            'classes' => array_values(array_unique($classes)),
            'attributes' => $attributes,
        ];
    }

    /**
     * @return array{classes:list<string>, attributes:array<string, string>}
     */
    private function drawingTextAttrs(?\DOMElement $docPr, int $paragraphCount, array $metadataAttrs = []): array
    {
        $attributes = [
            'data-docx-drawing-kind' => 'text',
            'data-docx-drawing-text-paragraphs' => (string) $paragraphCount,
        ];

        if ($docPr instanceof \DOMElement) {
            foreach ([
                'id' => 'data-docx-docpr-id',
                'name' => 'data-docx-docpr-name',
                'descr' => 'data-docx-docpr-descr',
                'title' => 'data-docx-docpr-title',
            ] as $source => $target) {
                $value = $docPr->getAttribute($source);
                if ($value !== '') {
                    $attributes[$target] = $value;
                }
            }
        }

        return $this->mergeNodeMetadataAttrs([
            'classes' => ['docx-drawing-text'],
            'attributes' => $attributes,
        ], $metadataAttrs);
    }

    /**
     * @return list<AstNode>
     */
    private function chartDrawingNodes(\DOMElement $drawing, ZipPackage $package, OpcRelationships $relationships): array
    {
        $nodes = [];
        foreach ($drawing->getElementsByTagNameNS(self::DRAWINGML_CHART_NS, 'chart') as $chart) {
            if (!$chart instanceof \DOMElement) {
                continue;
            }

            $relationshipId = $this->relationshipAttr($chart, 'id');
            if ($relationshipId === null || $relationshipId === '') {
                continue;
            }

            $relationship = $relationships->byId($relationshipId);
            if (!$relationship instanceof OpcRelationship || $relationship->type !== self::REL_TYPE_CHART) {
                continue;
            }

            $docPr = $this->drawingPropertiesForElement($chart, $drawing);
            $geometryAttrs = $this->drawingGeometryAttrs($this->drawingContainerForElement($chart, $drawing));
            $metadataAttrs = $this->mergeNodeMetadataAttrs(
                $geometryAttrs,
                $this->chartPartMetadataAttrs($relationship, $package, $relationships)
            );
            $nodes[] = $this->drawingRelationshipPlaceholderNode(
                'chart',
                $relationship,
                $package,
                $relationships,
                $docPr,
                $metadataAttrs
            );
        }

        return $nodes;
    }

    /**
     * @return array{classes:list<string>, attributes:array<string, string>}
     */
    private function chartPartMetadataAttrs(
        OpcRelationship $chartRelationship,
        ZipPackage $package,
        OpcRelationships $relationships
    ): array {
        $attrs = [
            'classes' => [],
            'attributes' => [],
        ];

        if ($chartRelationship->isExternal()) {
            return $attrs;
        }

        $chartTargetPart = OpcPackagePath::stripQueryAndFragment($relationships->resolveTarget($chartRelationship));
        if (!$package->has($chartTargetPart)) {
            return $attrs;
        }

        try {
            $chartDom = self::loadXml($package->read($chartTargetPart), 'DOCX chart part ' . $chartTargetPart);
        } catch (\InvalidArgumentException) {
            $attrs['classes'][] = 'docx-chart-data-missing';
            $attrs['attributes']['data-docx-chart-external-data-issues'] = 'invalid-chart-part';

            return $attrs;
        }

        $chartRoot = $chartDom->documentElement;
        if (!$chartRoot instanceof \DOMElement) {
            return $attrs;
        }

        $attrs = $this->mergeNodeMetadataAttrs($attrs, $this->chartStyleAndColorAttrs($chartRoot));
        $attrs = $this->mergeNodeMetadataAttrs($attrs, $this->chartTextMetadataAttrs($chartRoot));
        $attrs = $this->mergeNodeMetadataAttrs($attrs, $this->chartPlotAndLegendMetadataAttrs($chartRoot));

        $externalData = $this->firstDescendantElement($chartRoot, self::DRAWINGML_CHART_NS, 'externalData');
        $relationshipPart = OpcRelationships::relationshipPartNameForSource($chartTargetPart);
        if (!OpcRelationships::packageHasRelationshipsForSource($package, $chartTargetPart)) {
            if ($externalData instanceof \DOMElement) {
                $attrs['attributes']['data-docx-chart-relationship-part'] = $relationshipPart;
                $attrs['classes'][] = 'docx-chart-data-missing';
                $attrs['attributes']['data-docx-chart-external-data-issues'] = 'missing-relationship-part';
            }

            return $attrs;
        }

        try {
            $chartRelationships = OpcRelationships::fromPackage($package, $chartTargetPart);
        } catch (\InvalidArgumentException | \RuntimeException) {
            if ($externalData instanceof \DOMElement) {
                $attrs['attributes']['data-docx-chart-relationship-part'] = $relationshipPart;
                $attrs['classes'][] = 'docx-chart-data-missing';
                $attrs['attributes']['data-docx-chart-external-data-issues'] = 'invalid-relationship-part';
            }

            return $attrs;
        }

        $attrs['attributes']['data-docx-chart-relationship-part'] = $relationshipPart;
        $attrs = $this->mergeNodeMetadataAttrs($attrs, $this->chartStyleAndColorRelationshipAttrs($chartRelationships, $package));

        if (!$externalData instanceof \DOMElement) {
            return $attrs;
        }

        $externalDataId = $this->relationshipAttr($externalData, 'id');
        if ($externalDataId !== null && $externalDataId !== '') {
            $attrs['attributes']['data-docx-chart-external-data-id'] = $externalDataId;
        }

        $autoUpdate = $this->chartExternalDataAutoUpdate($externalData);
        if ($autoUpdate !== null) {
            $attrs['attributes']['data-docx-chart-external-data-auto-update'] = $autoUpdate;
        }

        $attrs['attributes']['data-docx-chart-relationship-count'] = (string) count($chartRelationships->all());
        if ($externalDataId === null || $externalDataId === '') {
            $attrs['classes'][] = 'docx-chart-data-missing';
            $attrs['attributes']['data-docx-chart-external-data-issues'] = 'missing-relationship-id';

            return $attrs;
        }

        $externalDataRelationship = $chartRelationships->byId($externalDataId);
        if (!$externalDataRelationship instanceof OpcRelationship) {
            $attrs['classes'][] = 'docx-chart-data-missing';
            $attrs['attributes']['data-docx-chart-external-data-issues'] = 'missing-relationship';

            return $attrs;
        }

        $attrs['classes'][] = 'docx-chart-embedded-data';
        $attrs['attributes']['data-docx-chart-external-data-type'] = $externalDataRelationship->type;
        $targetAttrs = $this->drawingRelationshipTargetAttrs($externalDataRelationship, $package, $chartRelationships);
        foreach ($targetAttrs as $name => $value) {
            $attrs['attributes']['data-docx-chart-external-data-' . $name] = $value;
        }

        $targetPart = $targetAttrs['target-part'] ?? null;
        if (is_string($targetPart) && $targetPart !== '' && $package->has($targetPart)) {
            $attrs['attributes']['data-docx-chart-external-data-bytes'] = (string) strlen($package->read($targetPart));
        }

        return $attrs;
    }

    /**
     * @return array{classes:list<string>, attributes:array<string, string>}
     */
    private function chartStyleAndColorAttrs(\DOMElement $chartRoot): array
    {
        $attrs = [
            'classes' => [],
            'attributes' => [],
        ];

        $style = $this->firstDescendantElement($chartRoot, self::DRAWINGML_CHART_NS, 'style');
        if ($style instanceof \DOMElement) {
            $value = trim($style->getAttribute('val'));
            if ($value !== '') {
                $attrs['classes'][] = 'docx-chart-style';
                $attrs['attributes']['data-docx-chart-style-val'] = $value;
            }
        }

        $colorMapOverride = $this->firstDescendantElement($chartRoot, self::DRAWINGML_CHART_NS, 'clrMapOvr');
        if ($colorMapOverride instanceof \DOMElement) {
            $masterColorMapping = $this->firstChildElement($colorMapOverride, self::DRAWINGML_MAIN_NS, 'masterClrMapping');
            if ($masterColorMapping instanceof \DOMElement) {
                $attrs['classes'][] = 'docx-chart-master-color-mapping';
                $attrs['attributes']['data-docx-chart-color-map'] = 'master';
            }

            $overrideColorMapping = $this->firstChildElement($colorMapOverride, self::DRAWINGML_MAIN_NS, 'overrideClrMapping');
            if ($overrideColorMapping instanceof \DOMElement) {
                $attrs['classes'][] = 'docx-chart-override-color-mapping';
                $attrs['attributes']['data-docx-chart-color-map'] = 'override';
                foreach ([
                    'bg1' => 'background-1',
                    'tx1' => 'text-1',
                    'bg2' => 'background-2',
                    'tx2' => 'text-2',
                    'accent1' => 'accent-1',
                    'accent2' => 'accent-2',
                    'accent3' => 'accent-3',
                    'accent4' => 'accent-4',
                    'accent5' => 'accent-5',
                    'accent6' => 'accent-6',
                    'hlink' => 'hyperlink',
                    'folHlink' => 'followed-hyperlink',
                ] as $source => $suffix) {
                    $this->appendDrawingAttribute($overrideColorMapping, $source, $attrs['attributes'], 'data-docx-chart-color-map-' . $suffix);
                }
            }
        }

        return [
            'classes' => array_values(array_unique($attrs['classes'])),
            'attributes' => $attrs['attributes'],
        ];
    }

    /**
     * @return array{classes:list<string>, attributes:array<string, string>}
     */
    private function chartStyleAndColorRelationshipAttrs(OpcRelationships $chartRelationships, ZipPackage $package): array
    {
        $attrs = [
            'classes' => [],
            'attributes' => [],
        ];

        foreach ([
            'style' => [self::REL_TYPE_CHART_STYLE, 'docx-chart-style-part'],
            'color-style' => [self::REL_TYPE_CHART_COLOR_STYLE, 'docx-chart-color-style-part'],
        ] as $prefix => [$relationshipType, $className]) {
            $relationship = $chartRelationships->firstOfType($relationshipType);
            if (!$relationship instanceof OpcRelationship) {
                continue;
            }

            $attrs['classes'][] = $className;
            $attributePrefix = 'data-docx-chart-' . $prefix;
            $attrs['attributes'][$attributePrefix . '-relationship-id'] = $relationship->id;
            $attrs['attributes'][$attributePrefix . '-relationship-type'] = $relationship->type;
            $targetAttrs = $this->drawingRelationshipTargetAttrs($relationship, $package, $chartRelationships);
            foreach ($targetAttrs as $name => $value) {
                $attrs['attributes'][$attributePrefix . '-' . $name] = $value;
            }

            $targetPart = $targetAttrs['target-part'] ?? null;
            if (is_string($targetPart) && $targetPart !== '' && $package->has($targetPart)) {
                $attrs['attributes'][$attributePrefix . '-bytes'] = (string) strlen($package->read($targetPart));
            }
        }

        return [
            'classes' => array_values(array_unique($attrs['classes'])),
            'attributes' => $attrs['attributes'],
        ];
    }

    /**
     * @return array{classes:list<string>, attributes:array<string, string>}
     */
    private function chartTextMetadataAttrs(\DOMElement $chartRoot): array
    {
        $attrs = [
            'classes' => [],
            'attributes' => [],
        ];

        $chart = $this->firstDescendantElement($chartRoot, self::DRAWINGML_CHART_NS, 'chart');
        if ($chart instanceof \DOMElement) {
            $title = $this->firstChildElement($chart, self::DRAWINGML_CHART_NS, 'title');
            if ($title instanceof \DOMElement) {
                $titleText = $this->chartTextValue($title);
                if ($titleText !== '') {
                    $attrs['classes'][] = 'docx-chart-title';
                    $attrs['attributes']['data-docx-chart-title'] = $this->boundedTextPreview($titleText);
                }
            }
        }

        $series = $this->chartSeriesMetadata($chartRoot);
        if ($series !== []) {
            $attrs['classes'][] = 'docx-chart-series';
            $attrs['attributes']['data-docx-chart-series-count'] = (string) count($series);
            foreach ($series as $index => $item) {
                $prefix = 'data-docx-chart-series-' . ($index + 1);
                foreach (['index', 'order', 'name'] as $key) {
                    if (isset($item[$key]) && $item[$key] !== '') {
                        $attrs['attributes'][$prefix . '-' . $key] = $item[$key];
                    }
                }
            }
        }

        $axes = $this->chartAxisTitleMetadata($chartRoot);
        if ($axes !== []) {
            $attrs['classes'][] = 'docx-chart-axis-title';
            $attrs['attributes']['data-docx-chart-axis-title-count'] = (string) count($axes);
            foreach ($axes as $index => $item) {
                $prefix = 'data-docx-chart-axis-' . ($index + 1);
                foreach (['type', 'id', 'title'] as $key) {
                    if (isset($item[$key]) && $item[$key] !== '') {
                        $attrs['attributes'][$prefix . '-' . $key] = $item[$key];
                    }
                }
            }
        }

        return [
            'classes' => array_values(array_unique($attrs['classes'])),
            'attributes' => $attrs['attributes'],
        ];
    }

    /**
     * @return array{classes:list<string>, attributes:array<string, string>}
     */
    private function chartPlotAndLegendMetadataAttrs(\DOMElement $chartRoot): array
    {
        $attrs = [
            'classes' => [],
            'attributes' => [],
        ];

        $plots = $this->chartPlotMetadata($chartRoot);
        if ($plots !== []) {
            $attrs['classes'][] = 'docx-chart-plot';
            $attrs['attributes']['data-docx-chart-plot-count'] = (string) count($plots);
            foreach ($plots as $index => $plot) {
                if (isset($plot['class']) && is_string($plot['class']) && $plot['class'] !== '') {
                    $attrs['classes'][] = 'docx-chart-plot-' . $plot['class'];
                }

                foreach (array_keys($plot) as $key) {
                    if (is_string($key) && str_starts_with($key, 'data-label-')) {
                        $attrs['classes'][] = 'docx-chart-data-labels';
                        break;
                    }
                }

                $prefix = 'data-docx-chart-plot-' . ($index + 1);
                foreach ($plot as $key => $value) {
                    if ($key === 'class' || $value === '') {
                        continue;
                    }

                    $attrs['attributes'][$prefix . '-' . $key] = $value;
                }
            }
        }

        $legend = $this->chartLegendMetadata($chartRoot);
        if ($legend !== []) {
            $attrs['classes'][] = 'docx-chart-legend';
            foreach ($legend as $key => $value) {
                if ($value !== '') {
                    $attrs['attributes']['data-docx-chart-legend-' . $key] = $value;
                }
            }
        }

        return [
            'classes' => array_values(array_unique($attrs['classes'])),
            'attributes' => $attrs['attributes'],
        ];
    }

    /**
     * @return list<array<string, string>>
     */
    private function chartPlotMetadata(\DOMElement $chartRoot): array
    {
        $chart = $this->firstDescendantElement($chartRoot, self::DRAWINGML_CHART_NS, 'chart');
        if (!$chart instanceof \DOMElement) {
            return [];
        }

        $plotArea = $this->firstChildElement($chart, self::DRAWINGML_CHART_NS, 'plotArea');
        if (!$plotArea instanceof \DOMElement) {
            return [];
        }

        $plots = [];
        foreach ($plotArea->childNodes as $child) {
            if (
                !$child instanceof \DOMElement
                || $child->namespaceURI !== self::DRAWINGML_CHART_NS
                || !str_ends_with($child->localName, 'Chart')
            ) {
                continue;
            }

            $plot = [
                'type' => (string) $child->localName,
                'class' => $this->chartPlotClassSuffix((string) $child->localName),
                'series-count' => (string) $this->chartDirectChildCount($child, 'ser'),
            ];

            foreach ([
                'grouping' => 'grouping',
                'barDir' => 'bar-direction',
                'radarStyle' => 'radar-style',
                'scatterStyle' => 'scatter-style',
                'ofPieType' => 'of-pie-type',
            ] as $localName => $attributeName) {
                $value = $this->chartChildVal($child, (string) $localName);
                if ($value !== null && $value !== '') {
                    $plot[$attributeName] = $value;
                }
            }

            $varyColors = $this->firstChildElement($child, self::DRAWINGML_CHART_NS, 'varyColors');
            if ($varyColors instanceof \DOMElement) {
                $plot['vary-colors'] = $this->normalizedOnOffAttribute($varyColors, 'val') ?? 'true';
            }

            $dataLabels = $this->chartDataLabelMetadata($child);
            if ($dataLabels !== []) {
                foreach ($dataLabels as $key => $value) {
                    $plot['data-label-' . $key] = $value;
                }
            }

            $plots[] = $plot;
        }

        return array_slice($plots, 0, 6);
    }

    private function chartPlotClassSuffix(string $localName): string
    {
        $base = preg_replace('/Chart$/', '', $localName) ?? $localName;
        $base = str_replace('3D', '3d', $base);
        $base = preg_replace('/(?<!^)[A-Z]/', '-$0', $base) ?? $base;
        $base = strtolower($base);
        $base = preg_replace('/[^a-z0-9]+/', '-', $base) ?? $base;

        return trim($base, '-') === '' ? strtolower($localName) : trim($base, '-');
    }

    private function chartDirectChildCount(\DOMElement $element, string $localName): int
    {
        $count = 0;
        foreach ($element->childNodes as $child) {
            if ($child instanceof \DOMElement && $child->namespaceURI === self::DRAWINGML_CHART_NS && $child->localName === $localName) {
                $count++;
            }
        }

        return $count;
    }

    /**
     * @return array<string, string>
     */
    private function chartDataLabelMetadata(\DOMElement $plot): array
    {
        $labels = $this->firstChildElement($plot, self::DRAWINGML_CHART_NS, 'dLbls');
        if (!$labels instanceof \DOMElement) {
            return [];
        }

        $metadata = [];
        $position = $this->chartChildVal($labels, 'dLblPos');
        if ($position !== null && $position !== '') {
            $metadata['position'] = $position;
        }

        foreach ([
            'showLegendKey' => 'show-legend-key',
            'showVal' => 'show-value',
            'showCatName' => 'show-category-name',
            'showSerName' => 'show-series-name',
            'showPercent' => 'show-percent',
            'showBubbleSize' => 'show-bubble-size',
            'showLeaderLines' => 'show-leader-lines',
        ] as $localName => $attributeName) {
            $element = $this->firstChildElement($labels, self::DRAWINGML_CHART_NS, (string) $localName);
            if (!$element instanceof \DOMElement) {
                continue;
            }

            $metadata[$attributeName] = $this->normalizedOnOffAttribute($element, 'val') ?? 'true';
        }

        $separator = $this->firstChildElement($labels, self::DRAWINGML_CHART_NS, 'separator');
        if ($separator instanceof \DOMElement) {
            $separatorText = $this->chartNormalizeText($separator->textContent ?? '');
            if ($separatorText !== '') {
                $metadata['separator'] = $this->boundedTextPreview($separatorText);
            }
        }

        $numberFormat = $this->firstChildElement($labels, self::DRAWINGML_CHART_NS, 'numFmt');
        if ($numberFormat instanceof \DOMElement) {
            $formatCode = trim($numberFormat->getAttribute('formatCode'));
            if ($formatCode !== '') {
                $metadata['number-format'] = $this->boundedTextPreview($formatCode);
            }

            $sourceLinked = $this->normalizedOnOffAttribute($numberFormat, 'sourceLinked');
            if ($sourceLinked !== null) {
                $metadata['source-linked'] = $sourceLinked;
            }
        }

        return $metadata;
    }

    /**
     * @return array<string, string>
     */
    private function chartLegendMetadata(\DOMElement $chartRoot): array
    {
        $chart = $this->firstDescendantElement($chartRoot, self::DRAWINGML_CHART_NS, 'chart');
        if (!$chart instanceof \DOMElement) {
            return [];
        }

        $legend = $this->firstChildElement($chart, self::DRAWINGML_CHART_NS, 'legend');
        if (!$legend instanceof \DOMElement) {
            return [];
        }

        $metadata = [];
        $position = $this->chartChildVal($legend, 'legendPos');
        if ($position !== null && $position !== '') {
            $metadata['position'] = $position;
        }

        $overlay = $this->firstChildElement($legend, self::DRAWINGML_CHART_NS, 'overlay');
        if ($overlay instanceof \DOMElement) {
            $metadata['overlay'] = $this->normalizedOnOffAttribute($overlay, 'val') ?? 'true';
        }

        $layout = $this->firstChildElement($legend, self::DRAWINGML_CHART_NS, 'layout');
        $manualLayout = $layout instanceof \DOMElement ? $this->firstChildElement($layout, self::DRAWINGML_CHART_NS, 'manualLayout') : null;
        if ($manualLayout instanceof \DOMElement) {
            foreach ([
                'x' => 'layout-x',
                'y' => 'layout-y',
                'w' => 'layout-width',
                'h' => 'layout-height',
            ] as $localName => $attributeName) {
                $value = $this->chartChildVal($manualLayout, (string) $localName);
                if ($value !== null && $value !== '') {
                    $metadata[$attributeName] = $value;
                }
            }
        }

        return $metadata;
    }

    private function chartChildVal(\DOMElement $element, string $localName): ?string
    {
        $child = $this->firstChildElement($element, self::DRAWINGML_CHART_NS, $localName);
        if (!$child instanceof \DOMElement) {
            return null;
        }

        $value = trim($child->getAttribute('val'));

        return $value === '' ? null : $value;
    }

    /**
     * @return list<array{index?:string, order?:string, name?:string}>
     */
    private function chartSeriesMetadata(\DOMElement $chartRoot): array
    {
        $series = [];
        foreach ($chartRoot->getElementsByTagNameNS(self::DRAWINGML_CHART_NS, 'ser') as $seriesElement) {
            if (!$seriesElement instanceof \DOMElement) {
                continue;
            }

            $item = [];
            $idx = $this->firstChildElement($seriesElement, self::DRAWINGML_CHART_NS, 'idx');
            if ($idx instanceof \DOMElement && trim($idx->getAttribute('val')) !== '') {
                $item['index'] = trim($idx->getAttribute('val'));
            }

            $order = $this->firstChildElement($seriesElement, self::DRAWINGML_CHART_NS, 'order');
            if ($order instanceof \DOMElement && trim($order->getAttribute('val')) !== '') {
                $item['order'] = trim($order->getAttribute('val'));
            }

            $name = $this->chartTextValue($seriesElement);
            if ($name !== '') {
                $item['name'] = $this->boundedTextPreview($name);
            }

            if ($item !== []) {
                $series[] = $item;
            }
        }

        return array_slice($series, 0, 9);
    }

    /**
     * @return list<array{type:string, id?:string, title:string}>
     */
    private function chartAxisTitleMetadata(\DOMElement $chartRoot): array
    {
        $axes = [];
        foreach ($this->drawingDescendantElementsByLocalNames($chartRoot, ['catAx', 'dateAx', 'valAx', 'serAx']) as $axis) {
            if ($axis->namespaceURI !== self::DRAWINGML_CHART_NS) {
                continue;
            }

            $title = $this->firstChildElement($axis, self::DRAWINGML_CHART_NS, 'title');
            if (!$title instanceof \DOMElement) {
                continue;
            }

            $titleText = $this->chartTextValue($title);
            if ($titleText === '') {
                continue;
            }

            $item = [
                'type' => (string) $axis->localName,
                'title' => $this->boundedTextPreview($titleText),
            ];
            $axisId = $this->firstChildElement($axis, self::DRAWINGML_CHART_NS, 'axId');
            if ($axisId instanceof \DOMElement && trim($axisId->getAttribute('val')) !== '') {
                $item['id'] = trim($axisId->getAttribute('val'));
            }

            $axes[] = $item;
        }

        return array_slice($axes, 0, 9);
    }

    private function chartTextValue(\DOMElement $container): string
    {
        $textContainer = $container;
        if ($container->namespaceURI !== self::DRAWINGML_CHART_NS || $container->localName !== 'tx') {
            $tx = $this->firstChildElement($container, self::DRAWINGML_CHART_NS, 'tx');
            if ($tx instanceof \DOMElement) {
                $textContainer = $tx;
            }
        }

        $rich = $this->firstDescendantElement($textContainer, self::DRAWINGML_CHART_NS, 'rich');
        if ($rich instanceof \DOMElement) {
            $richText = $this->drawingTextFromContainer($rich);
            if ($richText !== '') {
                return $richText;
            }
        }

        $value = $this->firstDescendantElement($textContainer, self::DRAWINGML_CHART_NS, 'v');
        if ($value instanceof \DOMElement) {
            return $this->chartNormalizeText($value->textContent ?? '');
        }

        return '';
    }

    private function drawingTextFromContainer(\DOMElement $container): string
    {
        $paragraphs = [];
        foreach ($container->getElementsByTagNameNS(self::DRAWINGML_MAIN_NS, 'p') as $paragraph) {
            if (!$paragraph instanceof \DOMElement) {
                continue;
            }

            $parts = [];
            foreach ($paragraph->getElementsByTagNameNS(self::DRAWINGML_MAIN_NS, 't') as $text) {
                if ($text instanceof \DOMElement) {
                    $parts[] = $text->textContent ?? '';
                }
            }

            $paragraphText = $this->chartNormalizeText(implode('', $parts));
            if ($paragraphText !== '') {
                $paragraphs[] = $paragraphText;
            }
        }

        return implode(' ', $paragraphs);
    }

    private function chartNormalizeText(string $text): string
    {
        return trim(preg_replace('/[ \t\r\n\f]+/u', ' ', $text) ?? $text);
    }

    private function chartExternalDataAutoUpdate(\DOMElement $externalData): ?string
    {
        $autoUpdate = $this->firstChildElement($externalData, self::DRAWINGML_CHART_NS, 'autoUpdate');
        if (!$autoUpdate instanceof \DOMElement) {
            return null;
        }

        return $this->normalizedOnOffAttribute($autoUpdate, 'val') ?? 'true';
    }

    /**
     * @return list<AstNode>
     */
    private function diagramDrawingNodes(\DOMElement $drawing, ZipPackage $package, OpcRelationships $relationships): array
    {
        $nodes = [];
        foreach ($drawing->getElementsByTagNameNS(self::DRAWINGML_DIAGRAM_NS, 'relIds') as $relIds) {
            if (!$relIds instanceof \DOMElement) {
                continue;
            }

            $relationshipRoles = $this->diagramRelationshipRoles($relIds, $relationships);
            if ($relationshipRoles === []) {
                continue;
            }

            $docPr = $this->drawingPropertiesForElement($relIds, $drawing);
            $attrs = $this->drawingPlaceholderBaseAttrs(
                'diagram',
                $docPr,
                $this->drawingGeometryAttrs($this->drawingContainerForElement($relIds, $drawing))
            );
            foreach ($relationshipRoles as $role => $relationship) {
                $rolePrefix = 'data-docx-diagram-' . $role;
                $attrs['attributes'][$rolePrefix . '-id'] = $relationship->id;
                $attrs['attributes'][$rolePrefix . '-type'] = $relationship->type;
                foreach ($this->drawingRelationshipTargetAttrs($relationship, $package, $relationships) as $name => $value) {
                    $attrs['attributes'][$rolePrefix . '-' . $name] = $value;
                }
            }

            $nodes[] = new AstNode('span', $attrs, [
                new AstNode('text', ['text' => $this->drawingPlaceholderText('diagram', $docPr)]),
            ]);
        }

        return $nodes;
    }

    /**
     * @return array<string, OpcRelationship>
     */
    private function diagramRelationshipRoles(\DOMElement $relIds, OpcRelationships $relationships): array
    {
        $roles = [
            'data' => ['dm', self::REL_TYPE_DIAGRAM_DATA],
            'layout' => ['lo', self::REL_TYPE_DIAGRAM_LAYOUT],
            'quick-style' => ['qs', self::REL_TYPE_DIAGRAM_QUICK_STYLE],
            'colors' => ['cs', self::REL_TYPE_DIAGRAM_COLORS],
        ];

        $matched = [];
        foreach ($roles as $role => [$attributeName, $expectedType]) {
            $relationshipId = $this->relationshipAttr($relIds, $attributeName);
            if ($relationshipId === null || $relationshipId === '') {
                continue;
            }

            $relationship = $relationships->byId($relationshipId);
            if ($relationship instanceof OpcRelationship && $relationship->type === $expectedType) {
                $matched[$role] = $relationship;
            }
        }

        return $matched;
    }

    /**
     * @return list<AstNode>
     */
    private function drawingConnectorNodes(\DOMElement $drawing): array
    {
        $nodes = [];
        foreach ($this->drawingDescendantElementsByLocalNames($drawing, ['cxnSp']) as $connector) {
            if (!$this->isDrawingConnectorElement($connector)) {
                continue;
            }

            $docPr = $this->drawingPropertiesForElement($connector, $drawing);
            $attrs = $this->drawingPlaceholderBaseAttrs(
                'connector',
                $docPr,
                $this->mergeNodeMetadataAttrs(
                    $this->drawingGeometryAttrs($this->drawingContainerForElement($connector, $drawing)),
                    $this->drawingConnectorMetadataAttrs($connector)
                )
            );

            $nodes[] = new AstNode('span', $attrs, [
                new AstNode('text', ['text' => $this->drawingPlaceholderText('connector', $docPr)]),
            ]);
        }

        return $nodes;
    }

    /**
     * @return list<AstNode>
     */
    private function drawingGroupShapeNodes(\DOMElement $drawing): array
    {
        $nodes = [];
        foreach ($this->drawingDescendantElementsByLocalNames($drawing, ['grpSp', 'wgp']) as $group) {
            if (!$this->isDrawingGroupShapeElement($group)) {
                continue;
            }

            $docPr = $this->drawingPropertiesForElement($group, $drawing);
            $attrs = $this->drawingPlaceholderBaseAttrs(
                'group-shape',
                $docPr,
                $this->mergeNodeMetadataAttrs(
                    $this->drawingGeometryAttrs($this->drawingContainerForElement($group, $drawing)),
                    $this->drawingGroupShapeMetadataAttrs($group)
                )
            );

            $nodes[] = new AstNode('span', $attrs, [
                new AstNode('text', ['text' => $this->drawingPlaceholderText('group-shape', $docPr)]),
            ]);
        }

        return $nodes;
    }

    private function isDrawingConnectorElement(\DOMElement $element): bool
    {
        return $element->localName === 'cxnSp'
            && in_array($element->namespaceURI, [self::DRAWINGML_MAIN_NS, self::WORDPROCESSING_SHAPE_NS], true);
    }

    private function isDrawingGroupShapeElement(\DOMElement $element): bool
    {
        return in_array($element->localName, ['grpSp', 'wgp'], true)
            && in_array($element->namespaceURI, [self::DRAWINGML_MAIN_NS, self::WORDPROCESSING_GROUP_NS], true);
    }

    /**
     * @return array{classes?:list<string>, attributes?:array<string, string>}
     */
    private function drawingConnectorMetadataAttrs(\DOMElement $connector): array
    {
        $classes = ['docx-drawing-connector-metadata'];
        $attributes = [];

        $nonVisualDrawing = $this->firstDescendantElementByLocalName($connector, ['cNvPr']);
        if ($nonVisualDrawing instanceof \DOMElement) {
            $classes[] = 'docx-connector-nonvisual';
            foreach ([
                'id' => 'data-docx-connector-nv-id',
                'name' => 'data-docx-connector-nv-name',
                'descr' => 'data-docx-connector-nv-description',
                'title' => 'data-docx-connector-nv-title',
            ] as $source => $target) {
                $this->appendDrawingAttribute($nonVisualDrawing, $source, $attributes, $target);
            }
        }

        $nonVisualConnector = $this->firstDescendantElementByLocalName($connector, ['cNvCxnSpPr', 'cNvCnPr']);
        if ($nonVisualConnector instanceof \DOMElement) {
            $lockAttrs = $this->drawingLockAttrs(
                $nonVisualConnector,
                'cxnSpLocks',
                'data-docx-connector-lock-',
                [
                    'noGrp' => 'no-group',
                    'noSelect' => 'no-select',
                    'noChangeShapeType' => 'no-change-shape-type',
                    'noMove' => 'no-move',
                    'noResize' => 'no-resize',
                ],
                $classes,
                'docx-connector-locks'
            );
            $attributes += $lockAttrs;

            foreach ([
                'stCxn' => 'start',
                'endCxn' => 'end',
            ] as $localName => $position) {
                $connection = $this->firstDescendantElementByLocalName($nonVisualConnector, [$localName]);
                if (!$connection instanceof \DOMElement) {
                    continue;
                }

                $classes[] = 'docx-connector-' . $position . '-connection';
                $this->appendDrawingAttribute($connection, 'id', $attributes, 'data-docx-connector-' . $position . '-id');
                $this->appendDrawingAttribute($connection, 'idx', $attributes, 'data-docx-connector-' . $position . '-index');
            }
        }

        $shapeProperties = $this->firstChildElementByLocalName($connector, ['spPr']);
        if ($shapeProperties instanceof \DOMElement) {
            $this->appendDrawingShapeTransformAttrs($shapeProperties, 'data-docx-connector-', $classes, 'docx-connector-transform', $attributes);

            $presetGeometry = $this->firstChildElementByLocalName($shapeProperties, ['prstGeom']);
            if ($presetGeometry instanceof \DOMElement) {
                $classes[] = 'docx-connector-preset-geometry';
                $this->appendDrawingAttribute($presetGeometry, 'prst', $attributes, 'data-docx-connector-preset-geometry');
            }

            $line = $this->firstChildElementByLocalName($shapeProperties, ['ln']);
            if ($line instanceof \DOMElement) {
                $classes[] = 'docx-connector-line';
                foreach ([
                    'w' => 'data-docx-connector-line-width-emu',
                    'cap' => 'data-docx-connector-line-cap',
                    'cmpd' => 'data-docx-connector-line-compound',
                    'algn' => 'data-docx-connector-line-align',
                ] as $source => $target) {
                    $this->appendDrawingAttribute($line, $source, $attributes, $target);
                }
                $lineColor = $this->drawingFirstColorValue($line);
                if ($lineColor === null) {
                    $solidFill = $this->firstChildElementByLocalName($line, ['solidFill']);
                    $lineColor = $solidFill instanceof \DOMElement ? $this->drawingFirstColorValue($solidFill) : null;
                }
                if ($lineColor !== null) {
                    $attributes['data-docx-connector-line-color'] = $lineColor;
                }
            }
        }

        return [
            'classes' => array_values(array_unique($classes)),
            'attributes' => $attributes,
        ];
    }

    /**
     * @return array{classes?:list<string>, attributes?:array<string, string>}
     */
    private function drawingGroupShapeMetadataAttrs(\DOMElement $group): array
    {
        $classes = ['docx-drawing-group-shape-metadata'];
        $attributes = [
            'data-docx-group-shape-child-count' => (string) $this->drawingGroupShapeChildCount($group),
        ];

        $nonVisualGroupProperties = $this->firstChildElementByLocalName($group, ['nvGrpSpPr']);
        $nonVisualDrawing = $nonVisualGroupProperties instanceof \DOMElement
            ? $this->firstChildElementByLocalName($nonVisualGroupProperties, ['cNvPr'])
            : $this->firstChildElementByLocalName($group, ['cNvPr']);
        if ($nonVisualDrawing instanceof \DOMElement) {
            $classes[] = 'docx-group-shape-nonvisual';
            foreach ([
                'id' => 'data-docx-group-shape-nv-id',
                'name' => 'data-docx-group-shape-nv-name',
                'descr' => 'data-docx-group-shape-nv-description',
                'title' => 'data-docx-group-shape-nv-title',
            ] as $source => $target) {
                $this->appendDrawingAttribute($nonVisualDrawing, $source, $attributes, $target);
            }
        }

        $nonVisualGroup = $this->firstDescendantElementByLocalName($group, ['cNvGrpSpPr']);
        if ($nonVisualGroup instanceof \DOMElement) {
            $attributes += $this->drawingLockAttrs(
                $nonVisualGroup,
                'grpSpLocks',
                'data-docx-group-shape-lock-',
                [
                    'noGrp' => 'no-group',
                    'noUngrp' => 'no-ungroup',
                    'noSelect' => 'no-select',
                    'noRot' => 'no-rotate',
                    'noChangeAspect' => 'no-change-aspect',
                    'noMove' => 'no-move',
                    'noResize' => 'no-resize',
                ],
                $classes,
                'docx-group-shape-locks'
            );
        }

        $groupProperties = $this->firstChildElementByLocalName($group, ['grpSpPr']);
        if ($groupProperties instanceof \DOMElement) {
            $this->appendDrawingShapeTransformAttrs($groupProperties, 'data-docx-group-shape-', $classes, 'docx-group-shape-transform', $attributes);
            $transform = $this->firstChildElementByLocalName($groupProperties, ['xfrm']);
            if ($transform instanceof \DOMElement) {
                $childOffset = $this->firstChildElementByLocalName($transform, ['chOff']);
                if ($childOffset instanceof \DOMElement) {
                    $this->appendDrawingAttribute($childOffset, 'x', $attributes, 'data-docx-group-shape-child-offset-x-emu');
                    $this->appendDrawingAttribute($childOffset, 'y', $attributes, 'data-docx-group-shape-child-offset-y-emu');
                }

                $childExtent = $this->firstChildElementByLocalName($transform, ['chExt']);
                if ($childExtent instanceof \DOMElement) {
                    $this->appendDrawingAttribute($childExtent, 'cx', $attributes, 'data-docx-group-shape-child-width-emu');
                    $this->appendDrawingAttribute($childExtent, 'cy', $attributes, 'data-docx-group-shape-child-height-emu');
                }
            }
        }

        return [
            'classes' => array_values(array_unique($classes)),
            'attributes' => $attributes,
        ];
    }

    /**
     * @param array<string, string> $attributes
     * @param list<string> $classes
     */
    private function appendDrawingShapeTransformAttrs(
        \DOMElement $shapeProperties,
        string $prefix,
        array &$classes,
        string $transformClass,
        array &$attributes
    ): void {
        $transform = $this->firstChildElementByLocalName($shapeProperties, ['xfrm']);
        if (!$transform instanceof \DOMElement) {
            return;
        }

        $hasTransform = false;
        foreach ([
            'rot' => $prefix . 'rotation',
            'flipH' => $prefix . 'flip-horizontal',
            'flipV' => $prefix . 'flip-vertical',
        ] as $source => $target) {
            $value = trim($transform->getAttribute($source));
            if ($value === '') {
                continue;
            }

            $attributes[$target] = $value;
            $hasTransform = true;
        }

        $offset = $this->firstChildElementByLocalName($transform, ['off']);
        if ($offset instanceof \DOMElement) {
            foreach ([
                'x' => $prefix . 'offset-x-emu',
                'y' => $prefix . 'offset-y-emu',
            ] as $source => $target) {
                $value = trim($offset->getAttribute($source));
                if ($value !== '') {
                    $attributes[$target] = $value;
                    $hasTransform = true;
                }
            }
        }

        $extent = $this->firstChildElementByLocalName($transform, ['ext']);
        if ($extent instanceof \DOMElement) {
            foreach ([
                'cx' => $prefix . 'width-emu',
                'cy' => $prefix . 'height-emu',
            ] as $source => $target) {
                $value = trim($extent->getAttribute($source));
                if ($value !== '') {
                    $attributes[$target] = $value;
                    $hasTransform = true;
                }
            }
        }

        if ($hasTransform) {
            $classes[] = $transformClass;
        }
    }

    /**
     * @param array<string, string> $attributes
     * @param list<string> $classes
     * @return array<string, string>
     */
    private function drawingLockAttrs(
        \DOMElement $container,
        string $localName,
        string $prefix,
        array $lockNames,
        array &$classes,
        string $lockClass
    ): array {
        $locks = $this->firstDescendantElementByLocalName($container, [$localName]);
        if (!$locks instanceof \DOMElement) {
            return [];
        }

        $classes[] = $lockClass;
        $attributes = [];
        foreach ($lockNames as $source => $suffix) {
            $value = $this->normalizedOnOffAttribute($locks, (string) $source);
            if ($value === null) {
                continue;
            }

            $attributes[$prefix . $suffix] = $value;
        }

        return $attributes;
    }

    private function drawingGroupShapeChildCount(\DOMElement $group): int
    {
        $count = 0;
        foreach ($group->childNodes as $child) {
            if (!$child instanceof \DOMElement) {
                continue;
            }

            if (in_array($child->localName, ['sp', 'pic', 'graphicFrame', 'cxnSp', 'grpSp', 'wsp'], true)) {
                $count++;
            }
        }

        return $count;
    }

    private function drawingRelationshipPlaceholderNode(
        string $kind,
        OpcRelationship $relationship,
        ZipPackage $package,
        OpcRelationships $relationships,
        ?\DOMElement $docPr,
        array $metadataAttrs = []
    ): AstNode {
        $attrs = $this->drawingPlaceholderBaseAttrs($kind, $docPr, $metadataAttrs);
        $attrs['attributes']['data-docx-relationship-id'] = $relationship->id;
        $attrs['attributes']['data-docx-relationship-type'] = $relationship->type;
        foreach ($this->drawingRelationshipTargetAttrs($relationship, $package, $relationships) as $name => $value) {
            $attrs['attributes']['data-docx-' . $name] = $value;
        }

        return new AstNode('span', $attrs, [
            new AstNode('text', ['text' => $this->drawingPlaceholderText($kind, $docPr)]),
        ]);
    }

    /**
     * @return array{classes:list<string>, attributes:array<string, string>}
     */
    private function drawingPlaceholderBaseAttrs(string $kind, ?\DOMElement $docPr, array $metadataAttrs = []): array
    {
        $attributes = [
            'data-docx-drawing-kind' => $kind,
        ];

        if ($docPr instanceof \DOMElement) {
            foreach ([
                'id' => 'data-docx-docpr-id',
                'name' => 'data-docx-docpr-name',
                'descr' => 'data-docx-docpr-descr',
                'title' => 'data-docx-docpr-title',
            ] as $source => $target) {
                $value = $docPr->getAttribute($source);
                if ($value !== '') {
                    $attributes[$target] = $value;
                }
            }
        }

        return $this->mergeNodeMetadataAttrs([
            'classes' => ['docx-drawing-placeholder', 'docx-drawing-' . $kind],
            'attributes' => $attributes,
        ], $metadataAttrs);
    }

    /**
     * @return array<string, string>
     */
    private function drawingRelationshipTargetAttrs(
        OpcRelationship $relationship,
        ZipPackage $package,
        OpcRelationships $relationships
    ): array {
        $target = $relationships->resolveTarget($relationship);
        $attrs = [
            'target' => $target,
        ];

        if ($relationship->isExternal()) {
            $externalTarget = $relationship->externalTargetPreflight();
            $attrs['external'] = 'true';
            $attrs['external-kind'] = $externalTarget['kind'];
            if ($externalTarget['scheme'] !== null) {
                $attrs['external-scheme'] = $externalTarget['scheme'];
            }
            $attrs['external-allowed'] = $externalTarget['allowed'] ? 'true' : 'false';
            if ($externalTarget['issues'] !== []) {
                $attrs['issues'] = implode(' ', $externalTarget['issues']);
            }

            return $attrs;
        }

        $targetPart = OpcPackagePath::stripQueryAndFragment($target);
        $attrs['target-part'] = $targetPart;
        $attrs['external'] = 'false';
        $attrs['exists'] = $package->has($targetPart) ? 'true' : 'false';
        $contentType = $this->contentTypeForPackagePart($package, $targetPart);
        if ($contentType !== null) {
            $attrs['content-type'] = $contentType;
        }

        return $attrs;
    }

    private function drawingPlaceholderText(string $kind, ?\DOMElement $docPr): string
    {
        $label = '';
        if ($docPr instanceof \DOMElement) {
            $label = (string) (
                $docPr->getAttribute('descr')
                ?: $docPr->getAttribute('title')
                ?: $docPr->getAttribute('name')
            );
        }

        return 'DOCX ' . str_replace('-', ' ', $kind) . ($label === '' ? '' : ': ' . $label);
    }

    /**
     * @return list<AstNode>
     */
    private function vmlImageNodes(\DOMElement $pict, ZipPackage $package, ?OpcRelationships $relationships): array
    {
        if (!$relationships instanceof OpcRelationships) {
            return [];
        }

        $nodes = [];
        foreach ($pict->getElementsByTagNameNS(self::VML_NS, 'imagedata') as $imageData) {
            if (!$imageData instanceof \DOMElement) {
                continue;
            }

            $relationshipId = $this->relationshipAttr($imageData, 'id');
            if ($relationshipId === null || $relationshipId === '') {
                continue;
            }

            $relationship = $relationships->byId($relationshipId);
            if (!$relationship instanceof OpcRelationship || $relationship->type !== self::REL_TYPE_IMAGE) {
                continue;
            }

            $shape = $this->vmlShapeForImageData($imageData, $pict);
            $alt = $shape instanceof \DOMElement ? (string) $shape->getAttribute('alt') : '';
            $title = $this->namespacedAttr($imageData, self::OFFICE_VML_NS, 'title') ?? '';
            if ($alt === '' && $title !== '') {
                $alt = $title;
            }

            $image = $this->relationshipImageNode($relationshipId, $relationship, $package, $relationships, $alt, $title);
            if ($image instanceof AstNode) {
                $nodes[] = $image;
            }
        }

        return $nodes;
    }

    private function relationshipImageNode(
        string $relationshipId,
        OpcRelationship $relationship,
        ZipPackage $package,
        OpcRelationships $relationships,
        string $alt,
        string $title,
        array $metadataAttrs = []
    ): ?AstNode {
        $attrs = $this->drawingImageBaseAttrs($relationshipId, $alt, $title, $metadataAttrs);

        if ($relationship->isExternal()) {
            $externalTarget = $relationship->externalTargetPreflight();
            if (!$externalTarget['allowed']) {
                return null;
            }

            $attrs += [
                'url' => $relationships->resolveTarget($relationship),
                'external' => true,
                'externalTargetKind' => $externalTarget['kind'],
                'externalTargetScheme' => $externalTarget['scheme'],
            ];

            return new AstNode('image', $attrs, $alt === '' ? [] : [new AstNode('text', ['text' => $alt])]);
        }

        $target = OpcPackagePath::stripQueryAndFragment($relationships->resolveTarget($relationship));
        if (!$package->has($target)) {
            return null;
        }

        $attrs += [
            'url' => ltrim($target, '/'),
            'alt' => $alt,
            'sourcePart' => $target,
            'external' => false,
            'bytes' => strlen($package->read($target)),
        ];

        return new AstNode('image', $attrs, $alt === '' ? [] : [new AstNode('text', ['text' => $alt])]);
    }

    private function drawingHyperlinkWrappedNode(AstNode $image, ?\DOMElement $docPr, OpcRelationships $relationships): AstNode
    {
        $attrs = $this->drawingHyperlinkAttrs($docPr, $relationships, 'click');
        if ($attrs === null) {
            return $image;
        }

        $hoverAttrs = $this->drawingHyperlinkAttrs($docPr, $relationships, 'hover');
        if ($hoverAttrs !== null && isset($hoverAttrs['attributes']) && is_array($hoverAttrs['attributes'])) {
            $attrs['attributes'] = array_merge($attrs['attributes'] ?? [], $this->prefixedDrawingHoverAttrs($hoverAttrs['attributes']));
            if (isset($hoverAttrs['url']) && is_string($hoverAttrs['url']) && $hoverAttrs['url'] !== '') {
                $attrs['attributes']['data-docx-drawing-hover-url'] = $hoverAttrs['url'];
            }
            if (isset($hoverAttrs['title']) && is_string($hoverAttrs['title']) && $hoverAttrs['title'] !== '') {
                $attrs['attributes']['data-docx-drawing-hover-tooltip'] = $hoverAttrs['title'];
            }
        }

        return new AstNode('link', $attrs, [$image]);
    }

    /**
     * @return array{url:string, title?:string, classes:list<string>, attributes:array<string, string>}|null
     */
    private function drawingHyperlinkAttrs(?\DOMElement $docPr, OpcRelationships $relationships, string $kind): ?array
    {
        if (!$docPr instanceof \DOMElement) {
            return null;
        }

        $element = $this->firstChildElement(
            $docPr,
            self::DRAWINGML_MAIN_NS,
            $kind === 'hover' ? 'hlinkHover' : 'hlinkClick'
        );
        if (!$element instanceof \DOMElement) {
            return null;
        }

        $relationshipId = trim((string) ($this->relationshipAttr($element, 'id') ?? ''));
        if ($relationshipId === '') {
            return null;
        }

        $relationship = $relationships->byId($relationshipId);
        if (!$relationship instanceof OpcRelationship || $relationship->type !== self::REL_TYPE_HYPERLINK) {
            return null;
        }

        $target = $relationships->resolveTarget($relationship);
        $attributes = [
            'data-docx-drawing-hyperlink' => $kind,
            'data-docx-relationship-id' => $relationshipId,
            'data-docx-relationship-type' => $relationship->type,
        ];
        $attrs = [
            'url' => $target,
            'classes' => ['docx-drawing-hyperlink', 'docx-drawing-hyperlink-' . $kind],
            'attributes' => $attributes,
        ];

        $tooltip = trim($element->getAttribute('tooltip'));
        if ($tooltip !== '') {
            $attrs['title'] = $tooltip;
            $attrs['attributes']['data-docx-tooltip'] = $tooltip;
        }

        foreach ([
            'action' => 'data-docx-action',
            'tgtFrame' => 'data-docx-target-frame',
        ] as $source => $targetAttribute) {
            $value = trim($element->getAttribute($source));
            if ($value !== '') {
                $attrs['attributes'][$targetAttribute] = $value;
            }
        }

        foreach ([
            'history' => 'data-docx-history',
            'highlightClick' => 'data-docx-highlight-click',
            'endSnd' => 'data-docx-end-sound',
        ] as $source => $targetAttribute) {
            $value = trim($element->getAttribute($source));
            if ($value === '') {
                continue;
            }

            $onOff = $this->onOffStringValue($value);
            $attrs['attributes'][$targetAttribute] = $onOff === null ? $value : ($onOff ? 'true' : 'false');
        }

        if (!$relationship->isExternal()) {
            $attrs['attributes']['data-docx-external'] = 'false';

            return $attrs;
        }

        $externalTarget = $relationship->externalTargetPreflight();
        if (!$externalTarget['allowed']) {
            return null;
        }

        $attrs['attributes']['data-docx-external'] = 'true';
        $attrs['attributes']['data-docx-external-kind'] = $externalTarget['kind'];
        if ($externalTarget['scheme'] !== null) {
            $attrs['attributes']['data-docx-external-scheme'] = $externalTarget['scheme'];
        }
        $attrs['attributes']['data-docx-external-allowed'] = 'true';

        return $attrs;
    }

    /**
     * @param array<string, string> $attributes
     * @return array<string, string>
     */
    private function prefixedDrawingHoverAttrs(array $attributes): array
    {
        $prefixed = [];
        foreach ($attributes as $name => $value) {
            if ($name === 'data-docx-drawing-hyperlink') {
                continue;
            }

            $suffix = str_starts_with($name, 'data-docx-')
                ? substr($name, strlen('data-docx-'))
                : $name;
            $prefixed['data-docx-drawing-hover-' . $suffix] = $value;
        }

        return $prefixed;
    }

    /**
     * @return array<string, mixed>
     */
    private function drawingImageBaseAttrs(string $relationshipId, string $alt, string $title, array $metadataAttrs = []): array
    {
        $attrs = [
            'relationshipId' => $relationshipId,
            'alt' => $alt,
        ];

        if ($title !== '') {
            $attrs['title'] = $title;
        }

        return $this->mergeNodeMetadataAttrs($attrs, $metadataAttrs);
    }

    private function drawingPropertiesForBlip(\DOMElement $blip, \DOMElement $drawing): ?\DOMElement
    {
        return $this->drawingPropertiesForElement($blip, $drawing);
    }

    /**
     * @return array{classes?:list<string>, attributes?:array<string, string>}
     */
    private function drawingPictureMetadataAttrs(\DOMElement $blip, \DOMElement $drawing): array
    {
        $picture = $this->drawingPictureForBlip($blip, $drawing);
        if (!$picture instanceof \DOMElement) {
            return [];
        }

        $classes = [];
        $attributes = [];

        $nonVisual = $this->drawingPictureNonVisualMetadataAttrs($picture);
        if ($nonVisual !== []) {
            array_push($classes, ...($nonVisual['classes'] ?? []));
            $attributes += $nonVisual['attributes'] ?? [];
        }

        $effects = $this->drawingPictureEffectMetadataAttrs($picture, $blip);
        if ($effects !== []) {
            array_push($classes, ...($effects['classes'] ?? []));
            $attributes += $effects['attributes'] ?? [];
        }

        $blipFill = $this->firstChildElement($picture, self::DRAWINGML_PICTURE_NS, 'blipFill');
        $sourceRectangle = $blipFill instanceof \DOMElement
            ? $this->firstChildElement($blipFill, self::DRAWINGML_MAIN_NS, 'srcRect')
            : null;
        if ($sourceRectangle instanceof \DOMElement) {
            $hasCrop = false;
            foreach ([
                'l' => 'data-docx-picture-crop-left',
                't' => 'data-docx-picture-crop-top',
                'r' => 'data-docx-picture-crop-right',
                'b' => 'data-docx-picture-crop-bottom',
            ] as $source => $target) {
                $value = trim($sourceRectangle->getAttribute($source));
                if ($value !== '') {
                    $attributes[$target] = $value;
                    $hasCrop = true;
                }
            }

            if ($hasCrop) {
                $classes[] = 'docx-picture-crop';
            }
        }

        $shapeProperties = $this->firstChildElement($picture, self::DRAWINGML_PICTURE_NS, 'spPr');
        $transform = $shapeProperties instanceof \DOMElement
            ? $this->firstChildElement($shapeProperties, self::DRAWINGML_MAIN_NS, 'xfrm')
            : null;
        if ($transform instanceof \DOMElement) {
            $hasTransform = false;
            foreach ([
                'rot' => 'data-docx-picture-rotation',
                'flipH' => 'data-docx-picture-flip-horizontal',
                'flipV' => 'data-docx-picture-flip-vertical',
            ] as $source => $target) {
                $value = trim($transform->getAttribute($source));
                if ($value === '') {
                    continue;
                }

                $attributes[$target] = $value;
                $hasTransform = true;

                $onOff = $this->onOffStringValue($value);
                if ($source === 'flipH' && $onOff === true) {
                    $classes[] = 'docx-picture-flip-horizontal';
                }
                if ($source === 'flipV' && $onOff === true) {
                    $classes[] = 'docx-picture-flip-vertical';
                }
            }

            $offset = $this->firstChildElement($transform, self::DRAWINGML_MAIN_NS, 'off');
            if ($offset instanceof \DOMElement) {
                foreach ([
                    'x' => 'data-docx-picture-offset-x-emu',
                    'y' => 'data-docx-picture-offset-y-emu',
                ] as $source => $target) {
                    $value = trim($offset->getAttribute($source));
                    if ($value !== '') {
                        $attributes[$target] = $value;
                        $hasTransform = true;
                    }
                }
            }

            $extent = $this->firstChildElement($transform, self::DRAWINGML_MAIN_NS, 'ext');
            if ($extent instanceof \DOMElement) {
                foreach ([
                    'cx' => 'data-docx-picture-width-emu',
                    'cy' => 'data-docx-picture-height-emu',
                ] as $source => $target) {
                    $value = trim($extent->getAttribute($source));
                    if ($value !== '') {
                        $attributes[$target] = $value;
                        $hasTransform = true;
                    }
                }
            }

            if ($hasTransform) {
                if (in_array('docx-picture-crop', $classes, true)) {
                    array_splice($classes, 1, 0, ['docx-picture-transform']);
                } else {
                    array_unshift($classes, 'docx-picture-transform');
                }
            }
        }

        if ($classes === [] && $attributes === []) {
            return [];
        }

        return [
            'classes' => array_values(array_unique($classes)),
            'attributes' => $attributes,
        ];
    }

    /**
     * @return array{classes?:list<string>, attributes?:array<string, string>}
     */
    private function drawingPictureEffectMetadataAttrs(\DOMElement $picture, \DOMElement $blip): array
    {
        $blipEffects = $this->drawingBlipEffectMetadataAttrs($blip);
        $shapeEffects = $this->drawingShapeEffectMetadataAttrs($picture);
        if ($blipEffects === [] && $shapeEffects === []) {
            return [];
        }

        $classes = ['docx-picture-effect'];
        $attributes = [];

        if ($blipEffects !== []) {
            $classes[] = 'docx-picture-blip-effect';
            array_push($classes, ...($blipEffects['classes'] ?? []));
            $attributes += $blipEffects['attributes'] ?? [];
        }

        if ($shapeEffects !== []) {
            $classes[] = 'docx-picture-shape-effect';
            array_push($classes, ...($shapeEffects['classes'] ?? []));
            $attributes += $shapeEffects['attributes'] ?? [];
        }

        return [
            'classes' => array_values(array_unique($classes)),
            'attributes' => $attributes,
        ];
    }

    /**
     * @return array{classes?:list<string>, attributes?:array<string, string>}
     */
    private function drawingBlipEffectMetadataAttrs(\DOMElement $blip): array
    {
        $effectNames = [];
        $classes = [];
        $attributes = [];

        foreach ($blip->childNodes as $child) {
            if (!$child instanceof \DOMElement || $child->namespaceURI !== self::DRAWINGML_MAIN_NS) {
                continue;
            }

            if ($child->localName === 'alphaModFix') {
                $effectNames[] = 'alphaModFix';
                $classes[] = 'docx-picture-alpha-mod-fix';
                $this->appendDrawingAttribute($child, 'amt', $attributes, 'data-docx-picture-alpha-mod-fix-amount');
                continue;
            }

            if ($child->localName === 'alphaMod') {
                $effectNames[] = 'alphaMod';
                $classes[] = 'docx-picture-alpha-mod';
                $this->appendDrawingAttribute($child, 'amt', $attributes, 'data-docx-picture-alpha-mod-amount');
                continue;
            }

            if ($child->localName === 'lum') {
                $effectNames[] = 'lum';
                $classes[] = 'docx-picture-luminance';
                $this->appendDrawingAttribute($child, 'bright', $attributes, 'data-docx-picture-luminance-brightness');
                $this->appendDrawingAttribute($child, 'contrast', $attributes, 'data-docx-picture-luminance-contrast');
                continue;
            }

            if ($child->localName === 'duotone') {
                $effectNames[] = 'duotone';
                $classes[] = 'docx-picture-duotone';
                $colors = $this->drawingColorValues($child);
                foreach ($colors as $index => $color) {
                    $attributes['data-docx-picture-duotone-color-' . ($index + 1)] = $color;
                }
                continue;
            }

            if ($child->localName === 'grayscl') {
                $effectNames[] = 'grayscl';
                $classes[] = 'docx-picture-grayscale';
                continue;
            }

            if ($child->localName === 'biLevel') {
                $effectNames[] = 'biLevel';
                $classes[] = 'docx-picture-bi-level';
                $this->appendDrawingAttribute($child, 'thresh', $attributes, 'data-docx-picture-bi-level-threshold');
            }
        }

        if ($effectNames === []) {
            return [];
        }

        $attributes['data-docx-picture-blip-effects'] = implode(' ', array_values(array_unique($effectNames)));

        return [
            'classes' => array_values(array_unique($classes)),
            'attributes' => $attributes,
        ];
    }

    /**
     * @return array{classes?:list<string>, attributes?:array<string, string>}
     */
    private function drawingShapeEffectMetadataAttrs(\DOMElement $picture): array
    {
        $shapeProperties = $this->firstChildElement($picture, self::DRAWINGML_PICTURE_NS, 'spPr');
        $effectList = $shapeProperties instanceof \DOMElement
            ? $this->firstChildElement($shapeProperties, self::DRAWINGML_MAIN_NS, 'effectLst')
            : null;
        if (!$effectList instanceof \DOMElement) {
            return [];
        }

        $effectNames = [];
        $classes = [];
        $attributes = [];

        foreach ($effectList->childNodes as $child) {
            if (!$child instanceof \DOMElement || $child->namespaceURI !== self::DRAWINGML_MAIN_NS) {
                continue;
            }

            if ($child->localName === 'outerShdw') {
                $effectNames[] = 'outerShdw';
                $classes[] = 'docx-picture-outer-shadow';
                $this->appendDrawingShadowEffectAttrs($child, 'outer', $attributes);
                continue;
            }

            if ($child->localName === 'innerShdw') {
                $effectNames[] = 'innerShdw';
                $classes[] = 'docx-picture-inner-shadow';
                $this->appendDrawingShadowEffectAttrs($child, 'inner', $attributes);
                continue;
            }

            if ($child->localName === 'softEdge') {
                $effectNames[] = 'softEdge';
                $classes[] = 'docx-picture-soft-edge';
                $this->appendDrawingAttribute($child, 'rad', $attributes, 'data-docx-picture-soft-edge-radius-emu');
                continue;
            }

            if ($child->localName === 'reflection') {
                $effectNames[] = 'reflection';
                $classes[] = 'docx-picture-reflection';
                $this->appendDrawingReflectionEffectAttrs($child, $attributes);
                continue;
            }

            if ($child->localName === 'glow') {
                $effectNames[] = 'glow';
                $classes[] = 'docx-picture-glow';
                $this->appendDrawingAttribute($child, 'rad', $attributes, 'data-docx-picture-glow-radius-emu');
                $this->appendDrawingColorAttribute($child, $attributes, 'data-docx-picture-glow-color');
                continue;
            }

            if ($child->localName === 'blur') {
                $effectNames[] = 'blur';
                $classes[] = 'docx-picture-blur';
                $this->appendDrawingAttribute($child, 'rad', $attributes, 'data-docx-picture-blur-radius-emu');
                $this->appendDrawingOnOffAttribute($child, 'grow', $attributes, 'data-docx-picture-blur-grow');
            }
        }

        if ($effectNames === []) {
            return [];
        }

        $attributes['data-docx-picture-shape-effects'] = implode(' ', array_values(array_unique($effectNames)));

        return [
            'classes' => array_values(array_unique($classes)),
            'attributes' => $attributes,
        ];
    }

    /**
     * @param array<string, string> $attributes
     */
    private function appendDrawingShadowEffectAttrs(\DOMElement $shadow, string $kind, array &$attributes): void
    {
        $prefix = 'data-docx-picture-shadow-' . $kind . '-';
        foreach ([
            'blurRad' => 'blur-radius-emu',
            'dist' => 'distance-emu',
            'dir' => 'direction',
            'algn' => 'alignment',
        ] as $source => $suffix) {
            $this->appendDrawingAttribute($shadow, $source, $attributes, $prefix . $suffix);
        }

        $this->appendDrawingOnOffAttribute($shadow, 'rotWithShape', $attributes, $prefix . 'rotate-with-shape');
        $this->appendDrawingColorAttribute($shadow, $attributes, $prefix . 'color');
    }

    /**
     * @param array<string, string> $attributes
     */
    private function appendDrawingReflectionEffectAttrs(\DOMElement $reflection, array &$attributes): void
    {
        foreach ([
            'blurRad' => 'data-docx-picture-reflection-blur-radius-emu',
            'dist' => 'data-docx-picture-reflection-distance-emu',
            'dir' => 'data-docx-picture-reflection-direction',
            'stA' => 'data-docx-picture-reflection-start-alpha',
            'endA' => 'data-docx-picture-reflection-end-alpha',
            'stPos' => 'data-docx-picture-reflection-start-position',
            'endPos' => 'data-docx-picture-reflection-end-position',
            'algn' => 'data-docx-picture-reflection-alignment',
        ] as $source => $target) {
            $this->appendDrawingAttribute($reflection, $source, $attributes, $target);
        }

        $this->appendDrawingOnOffAttribute($reflection, 'rotWithShape', $attributes, 'data-docx-picture-reflection-rotate-with-shape');
    }

    /**
     * @param array<string, string> $attributes
     */
    private function appendDrawingAttribute(\DOMElement $element, string $source, array &$attributes, string $target): void
    {
        $value = trim($element->getAttribute($source));
        if ($value !== '') {
            $attributes[$target] = $value;
        }
    }

    /**
     * @param array<string, string> $attributes
     */
    private function appendDrawingOnOffAttribute(\DOMElement $element, string $source, array &$attributes, string $target): void
    {
        $value = $this->normalizedOnOffAttribute($element, $source);
        if ($value !== null) {
            $attributes[$target] = $value;
        }
    }

    /**
     * @param array<string, string> $attributes
     */
    private function appendDrawingColorAttribute(\DOMElement $element, array &$attributes, string $target): void
    {
        $color = $this->drawingFirstColorValue($element);
        if ($color !== null) {
            $attributes[$target] = $color;
        }
    }

    /**
     * @return list<string>
     */
    private function drawingColorValues(\DOMElement $element): array
    {
        $colors = [];
        foreach ($element->childNodes as $child) {
            if (!$child instanceof \DOMElement || $child->namespaceURI !== self::DRAWINGML_MAIN_NS) {
                continue;
            }

            $color = $this->drawingColorValue($child);
            if ($color !== null) {
                $colors[] = $color;
            }
        }

        return $colors;
    }

    private function drawingFirstColorValue(\DOMElement $element): ?string
    {
        foreach ($this->drawingColorValues($element) as $color) {
            return $color;
        }

        return null;
    }

    private function drawingColorValue(\DOMElement $color): ?string
    {
        $value = trim($color->getAttribute('val'));
        if ($value === '' && $color->localName === 'sysClr') {
            $value = trim($color->getAttribute('lastClr'));
        }

        if ($value === '') {
            return null;
        }

        return match ($color->localName) {
            'srgbClr' => 'srgb:' . $value,
            'schemeClr' => 'scheme:' . $value,
            'prstClr' => 'preset:' . $value,
            'sysClr' => 'system:' . $value,
            default => null,
        };
    }

    /**
     * @return array{classes?:list<string>, attributes?:array<string, string>}
     */
    private function drawingPictureNonVisualMetadataAttrs(\DOMElement $picture): array
    {
        $classes = [];
        $attributes = [];

        $properties = $this->drawingPictureNonVisualDrawingProperties($picture);
        if ($properties instanceof \DOMElement) {
            foreach ([
                'id' => 'data-docx-picture-nv-id',
                'name' => 'data-docx-picture-nv-name',
                'descr' => 'data-docx-picture-nv-description',
                'title' => 'data-docx-picture-nv-title',
            ] as $source => $target) {
                $value = trim($properties->getAttribute($source));
                if ($value !== '') {
                    $attributes[$target] = $value;
                }
            }

            $hidden = $this->normalizedOnOffAttribute($properties, 'hidden');
            if ($hidden !== null) {
                $attributes['data-docx-picture-nv-hidden'] = $hidden;
                if ($hidden === 'true') {
                    $classes[] = 'docx-picture-hidden';
                }
            }
        }

        $pictureProperties = $this->drawingPictureNonVisualPictureProperties($picture);
        if ($pictureProperties instanceof \DOMElement) {
            $preferRelativeResize = $this->normalizedOnOffAttribute($pictureProperties, 'preferRelativeResize');
            if ($preferRelativeResize !== null) {
                $attributes['data-docx-picture-prefer-relative-resize'] = $preferRelativeResize;
            }

            $locks = $this->firstChildElement($pictureProperties, self::DRAWINGML_MAIN_NS, 'picLocks');
            if ($locks instanceof \DOMElement) {
                $classes[] = 'docx-picture-locks';
                foreach ([
                    'noChangeAspect' => 'no-change-aspect',
                    'noCrop' => 'no-crop',
                    'noMove' => 'no-move',
                    'noResize' => 'no-resize',
                    'noSelect' => 'no-select',
                ] as $source => $suffix) {
                    $value = $this->normalizedOnOffAttribute($locks, $source);
                    if ($value === null) {
                        continue;
                    }

                    $attributes['data-docx-picture-lock-' . $suffix] = $value;
                    if ($value === 'true') {
                        $classes[] = 'docx-picture-lock-' . $suffix;
                    }
                }
            }
        }

        if ($classes !== [] || $attributes !== []) {
            array_unshift($classes, 'docx-picture-nonvisual');
        }

        if ($classes === [] && $attributes === []) {
            return [];
        }

        return [
            'classes' => array_values(array_unique($classes)),
            'attributes' => $attributes,
        ];
    }

    private function drawingPictureNonVisualDrawingProperties(\DOMElement $picture): ?\DOMElement
    {
        $nonVisual = $this->firstChildElement($picture, self::DRAWINGML_PICTURE_NS, 'nvPicPr');

        return $nonVisual instanceof \DOMElement
            ? $this->firstChildElement($nonVisual, self::DRAWINGML_PICTURE_NS, 'cNvPr')
            : null;
    }

    private function drawingPictureNonVisualPictureProperties(\DOMElement $picture): ?\DOMElement
    {
        $nonVisual = $this->firstChildElement($picture, self::DRAWINGML_PICTURE_NS, 'nvPicPr');

        return $nonVisual instanceof \DOMElement
            ? $this->firstChildElement($nonVisual, self::DRAWINGML_PICTURE_NS, 'cNvPicPr')
            : null;
    }

    private function drawingPictureForBlip(\DOMElement $blip, \DOMElement $drawing): ?\DOMElement
    {
        $node = $blip->parentNode;
        while ($node instanceof \DOMElement) {
            if ($node->namespaceURI === self::DRAWINGML_PICTURE_NS && $node->localName === 'pic') {
                return $node;
            }

            if ($node === $drawing) {
                break;
            }

            $node = $node->parentNode;
        }

        return null;
    }

    private function drawingContainerForElement(\DOMElement $element, \DOMElement $drawing): ?\DOMElement
    {
        $node = $element->parentNode;
        while ($node instanceof \DOMElement) {
            if (
                $node->namespaceURI === self::WORDPROCESSING_DRAWING_NS
                && ($node->localName === 'inline' || $node->localName === 'anchor')
            ) {
                return $node;
            }

            if ($node === $drawing) {
                break;
            }

            $node = $node->parentNode;
        }

        return null;
    }

    private function drawingPropertiesForElement(\DOMElement $element, \DOMElement $drawing): ?\DOMElement
    {
        $container = $this->drawingContainerForElement($element, $drawing);
        if ($container instanceof \DOMElement) {
            return $this->firstChildElement($container, self::WORDPROCESSING_DRAWING_NS, 'docPr');
        }

        return $this->firstDescendantElement($drawing, self::WORDPROCESSING_DRAWING_NS, 'docPr');
    }

    /**
     * @return array{classes?:list<string>, attributes?:array<string, string>}
     */
    private function drawingGeometryAttrs(?\DOMElement $container): array
    {
        if (!$container instanceof \DOMElement) {
            return [];
        }

        $placement = $container->localName === 'anchor' ? 'anchor' : 'inline';
        $classes = ['docx-drawing-geometry', 'docx-drawing-' . $placement];
        $attributes = ['data-docx-drawing-placement' => $placement];
        $hasGeometry = false;

        foreach ([
            'distT' => 'data-docx-distance-top-emu',
            'distB' => 'data-docx-distance-bottom-emu',
            'distL' => 'data-docx-distance-left-emu',
            'distR' => 'data-docx-distance-right-emu',
        ] as $source => $target) {
            $value = trim($container->getAttribute($source));
            if ($value !== '') {
                $attributes[$target] = $value;
                $hasGeometry = true;
            }
        }

        if ($placement === 'anchor') {
            foreach ([
                'simplePos' => 'data-docx-anchor-simple-pos',
                'relativeHeight' => 'data-docx-anchor-relative-height',
                'behindDoc' => 'data-docx-anchor-behind-doc',
                'locked' => 'data-docx-anchor-locked',
                'layoutInCell' => 'data-docx-anchor-layout-in-cell',
                'allowOverlap' => 'data-docx-anchor-allow-overlap',
            ] as $source => $target) {
                $value = trim($container->getAttribute($source));
                if ($value !== '') {
                    $attributes[$target] = $value;
                    $hasGeometry = true;
                }
            }
        }

        $extent = $this->firstChildElement($container, self::WORDPROCESSING_DRAWING_NS, 'extent');
        if ($extent instanceof \DOMElement) {
            foreach ([
                'cx' => 'data-docx-width-emu',
                'cy' => 'data-docx-height-emu',
            ] as $source => $target) {
                $value = trim($extent->getAttribute($source));
                if ($value !== '') {
                    $attributes[$target] = $value;
                    $hasGeometry = true;
                }
            }
        }

        $effectExtent = $this->firstChildElement($container, self::WORDPROCESSING_DRAWING_NS, 'effectExtent');
        if ($effectExtent instanceof \DOMElement) {
            foreach ([
                'l' => 'data-docx-effect-extent-left-emu',
                't' => 'data-docx-effect-extent-top-emu',
                'r' => 'data-docx-effect-extent-right-emu',
                'b' => 'data-docx-effect-extent-bottom-emu',
            ] as $source => $target) {
                $value = trim($effectExtent->getAttribute($source));
                if ($value !== '') {
                    $attributes[$target] = $value;
                    $hasGeometry = true;
                }
            }
        }

        foreach ($container->childNodes as $child) {
            if (!$child instanceof \DOMElement || $child->namespaceURI !== self::WORDPROCESSING_DRAWING_NS) {
                continue;
            }

            if (str_starts_with($child->localName, 'wrap')) {
                $wrapKind = $this->drawingWrapKind($child->localName);
                if ($wrapKind !== null) {
                    $attributes['data-docx-wrap'] = $wrapKind;
                    $classes[] = 'docx-wrap-' . $wrapKind;
                    $hasGeometry = true;
                }

                $wrapText = trim($child->getAttribute('wrapText'));
                if ($wrapText !== '') {
                    $attributes['data-docx-wrap-text'] = $wrapText;
                    $hasGeometry = true;
                }

                continue;
            }

            if ($child->localName === 'positionH') {
                $hasGeometry = $this->appendDrawingPositionAttrs($child, 'h', $attributes) || $hasGeometry;
                continue;
            }

            if ($child->localName === 'positionV') {
                $hasGeometry = $this->appendDrawingPositionAttrs($child, 'v', $attributes) || $hasGeometry;
            }
        }

        if (!$hasGeometry) {
            return [];
        }

        return [
            'classes' => array_values(array_unique($classes)),
            'attributes' => $attributes,
        ];
    }

    private function drawingWrapKind(string $localName): ?string
    {
        return match ($localName) {
            'wrapNone' => 'none',
            'wrapSquare' => 'square',
            'wrapTight' => 'tight',
            'wrapThrough' => 'through',
            'wrapTopAndBottom' => 'top-and-bottom',
            default => null,
        };
    }

    /**
     * @param array<string, string> $attributes
     */
    private function appendDrawingPositionAttrs(\DOMElement $position, string $axis, array &$attributes): bool
    {
        $hasGeometry = false;
        $relativeFrom = trim($position->getAttribute('relativeFrom'));
        if ($relativeFrom !== '') {
            $attributes['data-docx-position-' . $axis . '-relative-from'] = $relativeFrom;
            $hasGeometry = true;
        }

        foreach ($position->childNodes as $child) {
            if (!$child instanceof \DOMElement) {
                continue;
            }

            $value = trim($child->textContent);
            if ($value === '') {
                continue;
            }

            if ($child->localName === 'align') {
                $attributes['data-docx-position-' . $axis . '-align'] = $value;
                $hasGeometry = true;
                continue;
            }

            if ($child->localName === 'posOffset') {
                $attributes['data-docx-position-' . $axis . '-offset-emu'] = $value;
                $hasGeometry = true;
                continue;
            }

            if ($child->localName === 'pctPosHOffset' || $child->localName === 'pctPosVOffset') {
                $attributes['data-docx-position-' . $axis . '-pct-offset'] = $value;
                $hasGeometry = true;
            }
        }

        return $hasGeometry;
    }

    /**
     * @param array<string, mixed> $attrs
     * @param array{classes?:list<string>, attributes?:array<string, string>} $metadataAttrs
     * @return array<string, mixed>
     */
    private function mergeNodeMetadataAttrs(array $attrs, array $metadataAttrs): array
    {
        if (isset($metadataAttrs['classes']) && is_array($metadataAttrs['classes'])) {
            $classes = isset($attrs['classes']) && is_array($attrs['classes']) ? $attrs['classes'] : [];
            $attrs['classes'] = array_values(array_unique(array_merge($classes, $metadataAttrs['classes'])));
        }

        if (isset($metadataAttrs['attributes']) && is_array($metadataAttrs['attributes'])) {
            $attributes = isset($attrs['attributes']) && is_array($attrs['attributes']) ? $attrs['attributes'] : [];
            $attrs['attributes'] = array_replace($attributes, $metadataAttrs['attributes']);
        }

        return $attrs;
    }

    private function vmlShapeForImageData(\DOMElement $imageData, \DOMElement $pict): ?\DOMElement
    {
        $node = $imageData->parentNode;
        while ($node instanceof \DOMElement) {
            if (
                $node->namespaceURI === self::VML_NS
                && in_array($node->localName, ['shape', 'rect', 'oval', 'roundrect', 'group'], true)
            ) {
                return $node;
            }

            if ($node === $pict) {
                break;
            }

            $node = $node->parentNode;
        }

        return null;
    }

    /**
     * @return list<AstNode>
     */
    private function embeddedObjectNodes(\DOMElement $object, ZipPackage $package, ?OpcRelationships $relationships): array
    {
        if (!$relationships instanceof OpcRelationships) {
            return [];
        }

        $nodes = [];
        foreach ($object->getElementsByTagNameNS(self::OFFICE_VML_NS, 'OLEObject') as $oleObject) {
            if (!$oleObject instanceof \DOMElement) {
                continue;
            }

            $relationshipId = $this->relationshipAttr($oleObject, 'id');
            if ($relationshipId === null || $relationshipId === '') {
                continue;
            }

            $relationship = $relationships->byId($relationshipId);
            if (!$relationship instanceof OpcRelationship || !$this->isEmbeddedObjectRelationshipType($relationship->type)) {
                continue;
            }

            $shape = $this->vmlShapeForOleObject($oleObject, $object);
            $nodes[] = $this->embeddedObjectPlaceholderNode($oleObject, $shape, $relationship, $package, $relationships);
        }

        return $nodes;
    }

    private function isEmbeddedObjectRelationshipType(string $relationshipType): bool
    {
        return in_array($relationshipType, [self::REL_TYPE_OLE_OBJECT, self::REL_TYPE_EMBEDDED_PACKAGE], true);
    }

    private function embeddedObjectKindFromRelationshipType(string $relationshipType): string
    {
        return $relationshipType === self::REL_TYPE_EMBEDDED_PACKAGE ? 'package' : 'ole-object';
    }

    private function embeddedObjectPlaceholderNode(
        \DOMElement $oleObject,
        ?\DOMElement $shape,
        OpcRelationship $relationship,
        ZipPackage $package,
        OpcRelationships $relationships
    ): AstNode {
        $kind = $this->embeddedObjectKindFromRelationshipType($relationship->type);
        $attrs = [
            'classes' => ['docx-embedded-object', 'docx-embedded-' . $kind],
            'attributes' => [
                'data-docx-embedded-kind' => $kind,
                'data-docx-relationship-id' => $relationship->id,
                'data-docx-relationship-type' => $relationship->type,
            ],
        ];

        foreach ($this->drawingRelationshipTargetAttrs($relationship, $package, $relationships) as $name => $value) {
            $attrs['attributes']['data-docx-' . $name] = $value;
        }

        if (
            ($attrs['attributes']['data-docx-external'] ?? null) === 'false'
            && ($attrs['attributes']['data-docx-exists'] ?? null) === 'true'
            && isset($attrs['attributes']['data-docx-target-part'])
        ) {
            $attrs['attributes']['data-docx-bytes'] = (string) strlen($package->read($attrs['attributes']['data-docx-target-part']));
        } elseif (
            ($attrs['attributes']['data-docx-external'] ?? null) === 'false'
            && ($attrs['attributes']['data-docx-exists'] ?? null) === 'false'
        ) {
            $attrs['attributes']['data-docx-issues'] = 'missing-in-package';
        }

        foreach ([
            'Type' => 'data-docx-ole-type',
            'ProgID' => 'data-docx-ole-prog-id',
            'ShapeID' => 'data-docx-ole-shape-id',
            'DrawAspect' => 'data-docx-ole-draw-aspect',
            'ObjectID' => 'data-docx-ole-object-id',
            'UpdateMode' => 'data-docx-ole-update-mode',
        ] as $source => $target) {
            $value = trim($oleObject->getAttribute($source));
            if ($value !== '') {
                $attrs['attributes'][$target] = $value;
            }
        }

        if ($shape instanceof \DOMElement) {
            foreach ([
                'id' => 'data-docx-shape-id',
                'alt' => 'data-docx-shape-alt',
                'style' => 'data-docx-shape-style',
            ] as $source => $target) {
                $value = trim($shape->getAttribute($source));
                if ($value !== '') {
                    $attrs['attributes'][$target] = $value;
                }
            }
        }

        return new AstNode('span', $attrs, [
            new AstNode('text', ['text' => $this->embeddedObjectPlaceholderText($kind, $oleObject, $shape)]),
        ]);
    }

    private function embeddedObjectPlaceholderText(string $kind, \DOMElement $oleObject, ?\DOMElement $shape): string
    {
        $label = $shape instanceof \DOMElement ? trim($shape->getAttribute('alt')) : '';
        if ($label === '') {
            $label = trim($oleObject->getAttribute('ProgID'));
        }

        $kindLabel = $kind === 'ole-object' ? 'OLE object' : str_replace('-', ' ', $kind);

        return 'DOCX embedded ' . $kindLabel . ($label === '' ? '' : ': ' . $label);
    }

    /**
     * @return list<AstNode>
     */
    private function subdocumentReferenceNodes(\DOMElement $subDoc, ZipPackage $package, ?OpcRelationships $relationships): array
    {
        $relationshipId = $this->relationshipAttr($subDoc, 'id');
        if ($relationshipId === null || $relationshipId === '') {
            return [$this->subdocumentPlaceholderNode(null, null, $package, $relationships, ['missing-relationship-id'])];
        }

        if (!$relationships instanceof OpcRelationships) {
            return [$this->subdocumentPlaceholderNode($relationshipId, null, $package, $relationships, ['missing-relationships'])];
        }

        $relationship = $relationships->byId($relationshipId);
        if (!$relationship instanceof OpcRelationship) {
            return [$this->subdocumentPlaceholderNode($relationshipId, null, $package, $relationships, ['missing-relationship'])];
        }

        $issues = [];
        if ($relationship->type !== self::REL_TYPE_SUBDOCUMENT) {
            $issues[] = 'unexpected-relationship-type';
        }
        if (!$relationship->isExternal()) {
            $issues[] = 'internal-subdocument-target';
        }

        return [$this->subdocumentPlaceholderNode($relationshipId, $relationship, $package, $relationships, $issues)];
    }

    /**
     * @param list<string> $issues
     */
    private function subdocumentPlaceholderNode(
        ?string $relationshipId,
        ?OpcRelationship $relationship,
        ZipPackage $package,
        ?OpcRelationships $relationships,
        array $issues
    ): AstNode {
        $attrs = [
            'classes' => ['docx-subdocument'],
            'attributes' => [
                'data-docx-subdocument' => 'true',
            ],
        ];

        if ($relationshipId !== null && $relationshipId !== '') {
            $attrs['attributes']['data-docx-relationship-id'] = $relationshipId;
        }

        if ($relationship instanceof OpcRelationship && $relationships instanceof OpcRelationships) {
            $attrs['attributes']['data-docx-relationship-type'] = $relationship->type;
            foreach ($this->drawingRelationshipTargetAttrs($relationship, $package, $relationships) as $name => $value) {
                $attrs['attributes']['data-docx-' . $name] = $value;
            }
        }

        if (
            ($attrs['attributes']['data-docx-external'] ?? null) === 'false'
            && ($attrs['attributes']['data-docx-exists'] ?? null) === 'false'
        ) {
            $issues[] = 'missing-in-package';
        }

        if ($issues !== []) {
            $attrs['attributes']['data-docx-issues'] = implode(' ', array_values(array_unique($issues)));
        }

        return new AstNode('span', $attrs, [
            new AstNode('text', ['text' => $this->subdocumentPlaceholderText($relationshipId, $relationship, $relationships)]),
        ]);
    }

    private function subdocumentPlaceholderText(?string $relationshipId, ?OpcRelationship $relationship, ?OpcRelationships $relationships): string
    {
        $label = null;
        if ($relationship instanceof OpcRelationship && $relationships instanceof OpcRelationships) {
            $label = $relationships->resolveTarget($relationship);
        } elseif ($relationshipId !== null && $relationshipId !== '') {
            $label = $relationshipId;
        }

        return 'DOCX subdocument: ' . ($label ?? 'unresolved');
    }

    private function vmlShapeForOleObject(\DOMElement $oleObject, \DOMElement $object): ?\DOMElement
    {
        $shapeId = trim($oleObject->getAttribute('ShapeID'));
        $fallback = null;
        foreach ($object->getElementsByTagNameNS(self::VML_NS, '*') as $shape) {
            if (!$shape instanceof \DOMElement || !in_array($shape->localName, ['shape', 'rect', 'oval', 'roundrect', 'group'], true)) {
                continue;
            }

            if ($fallback === null) {
                $fallback = $shape;
            }
            if ($shapeId !== '' && trim($shape->getAttribute('id')) === $shapeId) {
                return $shape;
            }
        }

        return $fallback;
    }

    /**
     * @param array<string, AstNode> $referencedNotes
     * @param array<string, array{name:?string, basedOn:?string, headingLevel:?int, numPr:?array{numId:?string, level:?int}}> $styles
     * @param array<string, array<int, array{ordered:bool, style:string, delimiter:string, start:int, format:string}>> $numbering
     * @param array{kind:string, startType:string}|null $activeProofError
     * @param list<AstNode> $activeProofErrorNodes
     * @param array{classes:list<string>, attributes:array<string, string>}|null $activePermissionRange
     * @param list<AstNode> $activePermissionRangeNodes
     * @param array{type:string, classes:list<string>, attributes:array<string, string>}|null $activeMoveRange
     * @param list<AstNode> $activeMoveRangeNodes
     */
    private function tableNode(
        \DOMElement $table,
        ZipPackage $package,
        ?OpcRelationships $relationships,
        array $referencedNotes,
        array $styles,
        array $numbering,
        ?string &$activeCommentRangeId,
        ?array &$activeProofError,
        array &$activeProofErrorNodes,
        ?array &$activePermissionRange,
        array &$activePermissionRangeNodes,
        ?array &$activeMoveRange,
        array &$activeMoveRangeNodes
    ): AstNode
    {
        $rows = [];
        $verticalMerges = [];
        $rowElements = $this->directWordChildElements($table, 'tr');
        $rowCount = count($rowElements);
        $conditionalRegions = $this->tableConditionalStyleRegionsForTable($table, $styles);
        $gridAttrs = $this->tableGridAttrs($table);
        $columnCount = max(
            count($gridAttrs['widths'] ?? []),
            $this->tableInferredColumnCount($rowElements)
        );
        foreach ($rowElements as $rowIndex => $rowElement) {
            $rowRegions = $this->tableConditionalRegionsForRow($conditionalRegions, $rowIndex, $rowCount);
            $rowAttrs = $this->mergeTableRowAttrs(
                $this->tableConditionalRowAttrs($rowRegions),
                $this->tableRowAttrs($rowElement),
            );

            $cells = [];
            $gridBefore = $this->tableRowGridOmissionCount($rowElement, 'gridBefore');
            $gridColumn = $gridBefore;
            if ($gridBefore > 0) {
                $this->clearTableVerticalMergeColumns($verticalMerges, 0, $gridBefore);
                array_push($cells, ...$this->tableRowOmittedGridCells($rowElement, 'before'));
            }

            foreach ($this->directWordChildElements($rowElement, 'tc') as $cellElement) {
                $colspan = $this->tableCellGridSpan($cellElement);
                $verticalMerge = $this->tableCellVerticalMerge($cellElement);
                if ($verticalMerge === 'continue' && isset($verticalMerges[$gridColumn])) {
                    $this->extendTableCellRowspan($rows, $verticalMerges[$gridColumn]['rowIndex'], $verticalMerges[$gridColumn]['cellIndex']);
                    $gridColumn += $colspan;
                    continue;
                }

                $this->clearTableVerticalMergeColumns($verticalMerges, $gridColumn, $colspan);
                $cellRegions = $this->tableConditionalRegionsForCell(
                    $rowRegions,
                    $conditionalRegions,
                    $rowIndex,
                    $rowCount,
                    $gridColumn,
                    $colspan,
                    $columnCount
                );
                $conditionalCellAttrs = $this->tableConditionalCellAttrs($cellRegions);
                $conditionalParagraphMetadata = $this->tableConditionalParagraphMetadata($cellRegions);
                $conditionalRunProperties = $this->tableConditionalRunProperties($cellRegions);
                $attrs = $this->tableCellAttrs($cellElement);
                if ($conditionalCellAttrs !== null) {
                    $attrs = $this->mergeTableCellAttrs($conditionalCellAttrs, $attrs);
                }
                if ($colspan > 1) {
                    $attrs['colspan'] = $colspan;
                }
                $cellBlocks = $this->tableCellBlocks(
                    $cellElement,
                    $package,
                    $relationships,
                    $referencedNotes,
                    $styles,
                    $numbering,
                    $activeCommentRangeId,
                    $activeProofError,
                    $activeProofErrorNodes,
                    $activePermissionRange,
                    $activePermissionRangeNodes,
                    $activeMoveRange,
                    $activeMoveRangeNodes,
                    $conditionalParagraphMetadata,
                    $conditionalRunProperties
                );
                $attrs['text'] = $this->plainBlockText($cellBlocks);

                $cells[] = new AstNode('table_cell', $attrs, $cellBlocks);
                if ($verticalMerge === 'restart') {
                    $rowIndex = count($rows);
                    $cellIndex = count($cells) - 1;
                    for ($column = $gridColumn; $column < $gridColumn + $colspan; $column++) {
                        $verticalMerges[$column] = [
                            'rowIndex' => $rowIndex,
                            'cellIndex' => $cellIndex,
                        ];
                    }
                }
                $gridColumn += $colspan;
            }

            $gridAfter = $this->tableRowGridOmissionCount($rowElement, 'gridAfter');
            if ($gridAfter > 0) {
                $this->clearTableVerticalMergeColumns($verticalMerges, $gridColumn, $gridAfter);
                array_push($cells, ...$this->tableRowOmittedGridCells($rowElement, 'after'));
            }

            $rows[] = new AstNode('table_row', $rowAttrs, $cells);
        }

        $tableAttrs = array_replace($this->tableAttrs($table, $styles), $gridAttrs);

        return TableGeometry::withReviewPacket(new AstNode('table', $tableAttrs, [
            new AstNode('table_body', [], $rows),
        ]), ['idPrefix' => 'docx-table']);
    }

    /**
     * @return list<\DOMElement>
     */
    private function directWordChildElements(\DOMElement $element, string $localName): array
    {
        $children = [];
        foreach ($element->childNodes as $child) {
            if (!$child instanceof \DOMElement || !$this->isWordElement($child, $localName)) {
                continue;
            }

            $children[] = $child;
        }

        return $children;
    }

    /**
     * @return array<string, mixed>
     */
    private function tableAttrs(\DOMElement $table, array $styles = []): array
    {
        $attrs = ['caption' => ''];
        $properties = $this->firstChildElement($table, self::WORDPROCESSINGML_NS, 'tblPr');
        if (!$properties instanceof \DOMElement) {
            return $attrs;
        }

        $caption = $this->tablePropertyValue($properties, 'tblCaption');
        if ($caption !== null) {
            $attrs['caption'] = $caption;
        }

        $styleId = $this->tableStyleId($properties);
        $metadataAttrs = null;
        if ($styleId !== null) {
            $seen = [];
            $metadataAttrs = $this->resolveStyleTableMetadataAttrs($styleId, $styles, $seen);
        }

        $description = $this->tablePropertyValue($properties, 'tblDescription');
        $directAttrs = $this->tablePropertiesMetadataAttrs($properties);
        if ($description !== null) {
            $directAttrs = $this->mergeTableAttrs($directAttrs, [
                'classes' => ['docx-table-metadata'],
                'attributes' => ['data-docx-table-description' => $description],
                'htmlAttributes' => ['aria-description' => $description],
            ]);
        } elseif ($caption !== null) {
            $directAttrs = $this->mergeTableAttrs($directAttrs, [
                'classes' => ['docx-table-metadata'],
            ]);
        }

        $metadataAttrs = $this->mergeTableAttrs($metadataAttrs, $directAttrs);
        if ($metadataAttrs === null) {
            return $attrs;
        }

        $classes = $metadataAttrs['classes'] ?? [];
        $attributes = $metadataAttrs['attributes'] ?? [];
        $htmlAttributes = $metadataAttrs['htmlAttributes'] ?? [];
        if ($classes !== []) {
            $attrs['classes'] = $classes;
        }
        if ($attributes !== []) {
            $attrs['attributes'] = $attributes;
        }
        if ($htmlAttributes !== []) {
            $attrs['htmlAttributes'] = $htmlAttributes;
        }

        return $attrs;
    }

    /**
     * @return array{classes?:list<string>, attributes?:array<string, string>, htmlAttributes?:array<string, string>}|null
     */
    private function tablePropertiesMetadataAttrs(\DOMElement $properties): ?array
    {
        $classes = [];
        $attributes = [];
        $htmlAttributes = [];
        $styles = [];

        $this->appendTableStyleAttrs($properties, $classes, $attributes);
        $this->appendTableWidthAttrs($properties, $classes, $attributes, $styles);
        $this->appendTableAlignmentAttrs($properties, $classes, $attributes);
        $this->appendTableIndentAttrs($properties, $classes, $attributes, $styles);
        $this->appendTableLayoutAttrs($properties, $classes, $attributes);

        if ($classes === [] && $attributes === [] && $styles === []) {
            return null;
        }

        $attrs = [];
        if ($classes !== []) {
            $attrs['classes'] = array_values(array_unique($classes));
        }
        if ($attributes !== []) {
            $attrs['attributes'] = $attributes;
        }
        if ($styles !== []) {
            $htmlAttributes['style'] = implode('; ', $styles);
        }
        if ($htmlAttributes !== []) {
            $attrs['htmlAttributes'] = $htmlAttributes;
        }

        return $attrs;
    }

    private function tableStyleId(\DOMElement $properties): ?string
    {
        $style = $this->firstChildElement($properties, self::WORDPROCESSINGML_NS, 'tblStyle');
        if (!$style instanceof \DOMElement) {
            return null;
        }

        $value = trim((string) ($this->wordAttr($style, 'val') ?? ''));

        return $value === '' ? null : $value;
    }

    /**
     * @param array<string, array{type?:string, name:?string, basedOn:?string, tableMetadata?:array{classes?:list<string>, attributes?:array<string, string>, htmlAttributes?:array<string, string>}|null}> $styles
     * @param array<string, bool> $seen
     * @return array{classes?:list<string>, attributes?:array<string, string>, htmlAttributes?:array<string, string>}|null
     */
    private function resolveStyleTableMetadataAttrs(?string $styleId, array $styles, array &$seen): ?array
    {
        if ($styleId === null || isset($seen[$styleId]) || !isset($styles[$styleId])) {
            return null;
        }

        $style = $styles[$styleId];
        if (($style['type'] ?? null) !== 'table') {
            return null;
        }

        $seen[$styleId] = true;
        $attrs = $this->resolveStyleTableMetadataAttrs($style['basedOn'], $styles, $seen);
        $styleAttrs = $style['tableMetadata'] ?? null;

        return $this->mergeTableAttrs($attrs, is_array($styleAttrs) ? $styleAttrs : null);
    }

    /**
     * @param array<string, array<string, mixed>> $styles
     * @return array<string, array<string, mixed>>
     */
    private function tableConditionalStyleRegionsForTable(\DOMElement $table, array $styles): array
    {
        $properties = $this->firstChildElement($table, self::WORDPROCESSINGML_NS, 'tblPr');
        if (!$properties instanceof \DOMElement) {
            return [];
        }

        $seen = [];

        return $this->resolveStyleTableConditionalRegions($this->tableStyleId($properties), $styles, $seen);
    }

    /**
     * @param array<string, array<string, mixed>> $styles
     * @param array<string, bool> $seen
     * @return array<string, array<string, mixed>>
     */
    private function resolveStyleTableConditionalRegions(?string $styleId, array $styles, array &$seen): array
    {
        if ($styleId === null || isset($seen[$styleId]) || !isset($styles[$styleId])) {
            return [];
        }

        $style = $styles[$styleId];
        if (($style['type'] ?? null) !== 'table') {
            return [];
        }

        $seen[$styleId] = true;
        $regions = $this->resolveStyleTableConditionalRegions(
            is_string($style['basedOn'] ?? null) ? $style['basedOn'] : null,
            $styles,
            $seen
        );

        $styleRegions = is_array($style['tableConditionalRegions'] ?? null) ? $style['tableConditionalRegions'] : [];
        foreach ($styleRegions as $type => $region) {
            if (!is_string($type) || !is_array($region)) {
                continue;
            }

            $regions[$type] = $region;
        }

        return $regions;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function styleTableConditionalRegions(\DOMElement $styleElement): array
    {
        $regions = [];
        foreach ($styleElement->childNodes as $child) {
            if (!$child instanceof \DOMElement || !$this->isWordElement($child, 'tblStylePr')) {
                continue;
            }

            $type = trim((string) ($this->wordAttr($child, 'type') ?? ''));
            if ($type === '') {
                continue;
            }

            $suffix = $this->tableStyleConditionalRegionClassSuffix($type);
            if ($suffix === null) {
                continue;
            }

            $region = [
                'type' => $type,
                'label' => $suffix,
            ];

            $rowProperties = $this->firstChildElement($child, self::WORDPROCESSINGML_NS, 'trPr');
            $region['rowAttrs'] = $this->tableStyleConditionalTableLikeAttrs(
                $rowProperties instanceof \DOMElement ? $this->tableRowAttrs($rowProperties) : null,
                'row',
                $type,
                $suffix
            );

            $cellProperties = $this->firstChildElement($child, self::WORDPROCESSINGML_NS, 'tcPr');
            if ($cellProperties instanceof \DOMElement) {
                $region['cellAttrs'] = $this->tableStyleConditionalTableLikeAttrs(
                    $this->tableCellAttrs($cellProperties),
                    'cell',
                    $type,
                    $suffix
                );
            }

            $paragraphProperties = $this->firstChildElement($child, self::WORDPROCESSINGML_NS, 'pPr');
            if ($paragraphProperties instanceof \DOMElement) {
                $region['paragraphMetadata'] = $this->tableStyleConditionalMetadataAttrs(
                    $this->paragraphPropertiesMetadataAttrs($paragraphProperties),
                    'paragraph',
                    $type,
                    $suffix
                );
            }

            $runProperties = $this->firstChildElement($child, self::WORDPROCESSINGML_NS, 'rPr');
            if ($runProperties instanceof \DOMElement) {
                $region['runProperties'] = $this->tableStyleConditionalRunProperties(
                    $this->runPropertiesFromElement($runProperties),
                    $type,
                    $suffix
                );
            }

            $regions[$type] = $region;
        }

        return $regions;
    }

    /**
     * @param array{classes?:list<string>, attributes?:array<string, string>, htmlAttributes?:array<string, string>}|null $attrs
     * @return array{classes:list<string>, attributes:array<string, string>, htmlAttributes:array<string, string>}
     */
    private function tableStyleConditionalTableLikeAttrs(?array $attrs, string $target, string $type, string $suffix): array
    {
        $classes = is_array($attrs['classes'] ?? null) ? $attrs['classes'] : [];
        $attributes = is_array($attrs['attributes'] ?? null) ? $attrs['attributes'] : [];
        $htmlAttributes = is_array($attrs['htmlAttributes'] ?? null) ? $attrs['htmlAttributes'] : [];
        $classes[] = 'docx-table-style-' . $target . '-region';
        $classes[] = 'docx-table-style-' . $target . '-region-' . $suffix;
        $attributes['data-docx-table-style-' . $target . '-region'] = $type;
        $attributes['data-docx-table-style-' . $target . '-region-label'] = $suffix;
        $htmlAttributes['data-docx-table-style-' . $target . '-region'] = $type;
        $htmlAttributes['data-docx-table-style-' . $target . '-region-label'] = $suffix;

        return [
            'classes' => array_values(array_unique($classes)),
            'attributes' => $attributes,
            'htmlAttributes' => $htmlAttributes,
        ];
    }

    /**
     * @param array{classes:list<string>, attributes:array<string, string>}|null $attrs
     * @return array{classes:list<string>, attributes:array<string, string>}
     */
    private function tableStyleConditionalMetadataAttrs(?array $attrs, string $target, string $type, string $suffix): array
    {
        $classes = $attrs['classes'] ?? [];
        $attributes = $attrs['attributes'] ?? [];
        $classes[] = 'docx-table-style-' . $target . '-region';
        $classes[] = 'docx-table-style-' . $target . '-region-' . $suffix;
        $attributes['data-docx-table-style-' . $target . '-region'] = $type;
        $attributes['data-docx-table-style-' . $target . '-region-label'] = $suffix;

        return [
            'classes' => array_values(array_unique($classes)),
            'attributes' => $attributes,
        ];
    }

    /**
     * @param array<string, mixed>|null $runProperties
     * @return array<string, mixed>
     */
    private function tableStyleConditionalRunProperties(?array $runProperties, string $type, string $suffix): array
    {
        $runProperties ??= [];
        $runProperties['metadata'] = $this->mergeRunMetadataAttrs(
            isset($runProperties['metadata']) && is_array($runProperties['metadata']) ? $runProperties['metadata'] : null,
            $this->tableStyleConditionalMetadataAttrs(null, 'run', $type, $suffix),
            []
        );

        return $runProperties;
    }

    /**
     * @param array<string, array<string, mixed>> $regions
     * @return list<array<string, mixed>>
     */
    private function tableConditionalRegionsForRow(array $regions, int $rowIndex, int $rowCount): array
    {
        if ($rowCount <= 0) {
            return [];
        }

        $active = [];
        if (isset($regions['wholeTable'])) {
            $active[] = $regions['wholeTable'];
        }
        if ($rowIndex > 0 && $rowIndex < $rowCount - 1) {
            if (($rowIndex % 2) === 1 && isset($regions['band1Horz'])) {
                $active[] = $regions['band1Horz'];
            } elseif (($rowIndex % 2) === 0 && isset($regions['band2Horz'])) {
                $active[] = $regions['band2Horz'];
            }
        }
        if ($rowIndex === 0 && isset($regions['firstRow'])) {
            $active[] = $regions['firstRow'];
        }
        if ($rowIndex === $rowCount - 1 && isset($regions['lastRow'])) {
            $active[] = $regions['lastRow'];
        }

        return $active;
    }

    /**
     * @param list<array<string, mixed>> $rowRegions
     * @param array<string, array<string, mixed>> $regions
     * @return list<array<string, mixed>>
     */
    private function tableConditionalRegionsForCell(
        array $rowRegions,
        array $regions,
        int $rowIndex,
        int $rowCount,
        int $gridColumn,
        int $colspan,
        int $columnCount
    ): array
    {
        if ($rowCount <= 0 || $columnCount <= 0) {
            return $rowRegions;
        }

        $active = $rowRegions;
        $startColumn = max(0, $gridColumn);
        $lastColumn = $columnCount - 1;
        $endColumn = min($lastColumn, $startColumn + max(1, $colspan) - 1);

        if ($startColumn === 0 && isset($regions['firstCol'])) {
            $active[] = $regions['firstCol'];
        }
        if ($endColumn === $lastColumn && isset($regions['lastCol'])) {
            $active[] = $regions['lastCol'];
        }

        $isFirstRow = $rowIndex === 0;
        $isLastRow = $rowIndex === $rowCount - 1;
        if ($isFirstRow && $startColumn === 0 && isset($regions['nwCell'])) {
            $active[] = $regions['nwCell'];
        }
        if ($isFirstRow && $endColumn === $lastColumn && isset($regions['neCell'])) {
            $active[] = $regions['neCell'];
        }
        if ($isLastRow && $startColumn === 0 && isset($regions['swCell'])) {
            $active[] = $regions['swCell'];
        }
        if ($isLastRow && $endColumn === $lastColumn && isset($regions['seCell'])) {
            $active[] = $regions['seCell'];
        }

        return $active;
    }

    /**
     * @param list<array<string, mixed>> $regions
     * @return array{classes?:list<string>, attributes?:array<string, string>, htmlAttributes?:array<string, string>}|null
     */
    private function tableConditionalRowAttrs(array $regions): ?array
    {
        $attrs = null;
        foreach ($regions as $region) {
            $regionAttrs = $region['rowAttrs'] ?? null;
            if (is_array($regionAttrs)) {
                $attrs = $this->mergeTableRowAttrs($attrs, $regionAttrs);
            }
        }

        return $attrs;
    }

    /**
     * @param list<array<string, mixed>> $regions
     * @return array{classes?:list<string>, attributes?:array<string, string>, htmlAttributes?:array<string, string>}|null
     */
    private function tableConditionalCellAttrs(array $regions): ?array
    {
        $attrs = null;
        foreach ($regions as $region) {
            $regionAttrs = $region['cellAttrs'] ?? null;
            if (is_array($regionAttrs)) {
                $attrs = $this->mergeTableCellAttrs($attrs, $regionAttrs);
            }
        }

        return $attrs;
    }

    /**
     * @param list<array<string, mixed>> $regions
     * @return array{classes:list<string>, attributes:array<string, string>}|null
     */
    private function tableConditionalParagraphMetadata(array $regions): ?array
    {
        $attrs = null;
        foreach ($regions as $region) {
            $metadata = $region['paragraphMetadata'] ?? null;
            if (is_array($metadata)) {
                $attrs = $this->mergeMetadataAttrs($attrs, $metadata);
            }
        }

        return $attrs;
    }

    /**
     * @param list<array<string, mixed>> $regions
     * @return array<string, mixed>|null
     */
    private function tableConditionalRunProperties(array $regions): ?array
    {
        $properties = null;
        foreach ($regions as $region) {
            $runProperties = $region['runProperties'] ?? null;
            if (is_array($runProperties)) {
                $properties = $this->mergeRunProperties($properties, $runProperties);
            }
        }

        return $properties;
    }

    /**
     * @param array{classes?:list<string>, attributes?:array<string, string>, htmlAttributes?:array<string, string>}|null $base
     * @param array{classes?:list<string>, attributes?:array<string, string>, htmlAttributes?:array<string, string>}|null $override
     * @return array{classes?:list<string>, attributes?:array<string, string>, htmlAttributes?:array<string, string>}|null
     */
    private function mergeTableAttrs(?array $base, ?array $override): ?array
    {
        if ($base === null) {
            return $override;
        }
        if ($override === null) {
            return $base;
        }

        $baseClasses = is_array($base['classes'] ?? null) ? $base['classes'] : [];
        $overrideClasses = is_array($override['classes'] ?? null) ? $override['classes'] : [];
        $overrideAttributes = is_array($override['attributes'] ?? null) ? $override['attributes'] : [];
        $baseClasses = $this->removeOverriddenTableMetadataClasses($baseClasses, $overrideAttributes);

        $merged = [];
        $classes = array_values(array_unique([...$baseClasses, ...$overrideClasses]));
        if ($classes !== []) {
            $merged['classes'] = $classes;
        }

        $attributes = array_replace(
            is_array($base['attributes'] ?? null) ? $base['attributes'] : [],
            $overrideAttributes,
        );
        if ($attributes !== []) {
            $merged['attributes'] = $attributes;
        }

        $htmlAttributes = array_replace(
            is_array($base['htmlAttributes'] ?? null) ? $base['htmlAttributes'] : [],
            is_array($override['htmlAttributes'] ?? null) ? $override['htmlAttributes'] : [],
        );
        $baseStyle = trim((string) (($base['htmlAttributes'] ?? [])['style'] ?? ''));
        $overrideStyle = trim((string) (($override['htmlAttributes'] ?? [])['style'] ?? ''));
        if ($baseStyle !== '' || $overrideStyle !== '') {
            $htmlAttributes['style'] = $this->mergeTableCssStyle($baseStyle, $overrideStyle, $overrideAttributes);
        }
        if ($htmlAttributes !== []) {
            $merged['htmlAttributes'] = $htmlAttributes;
        }

        return $merged === [] ? null : $merged;
    }

    /**
     * @param list<string> $classes
     * @param array<string, string> $overrideAttributes
     * @return list<string>
     */
    private function removeOverriddenTableMetadataClasses(array $classes, array $overrideAttributes): array
    {
        $removeExact = [];
        $removePrefixes = [];

        if (isset($overrideAttributes['data-docx-table-width-type'])) {
            $removeExact[] = 'docx-table-width';
            $removePrefixes[] = 'docx-table-width-';
        }
        if (isset($overrideAttributes['data-docx-table-align'])) {
            $removeExact[] = 'docx-table-align';
            $removePrefixes[] = 'docx-table-align-';
        }
        if (isset($overrideAttributes['data-docx-table-indent-type'])) {
            $removeExact[] = 'docx-table-indent';
            $removePrefixes[] = 'docx-table-indent-';
        }
        if (isset($overrideAttributes['data-docx-table-layout'])) {
            $removeExact[] = 'docx-table-layout';
            $removePrefixes[] = 'docx-table-layout-';
        }

        if ($removeExact === [] && $removePrefixes === []) {
            return $classes;
        }

        return array_values(array_filter(
            $classes,
            static function (string $class) use ($removeExact, $removePrefixes): bool {
                if (in_array($class, $removeExact, true)) {
                    return false;
                }

                foreach ($removePrefixes as $prefix) {
                    if (str_starts_with($class, $prefix)) {
                        return false;
                    }
                }

                return true;
            },
        ));
    }

    /**
     * @param array<string, string> $overrideAttributes
     */
    private function mergeTableCssStyle(string $baseStyle, string $overrideStyle, array $overrideAttributes): string
    {
        $removeProperties = [];
        if (isset($overrideAttributes['data-docx-table-width-type'])) {
            $removeProperties[] = 'width';
        }
        if (isset($overrideAttributes['data-docx-table-indent-type'])) {
            $removeProperties[] = 'margin-left';
        }

        $baseStyle = $this->removeCssStyleProperties($baseStyle, $removeProperties);
        if ($baseStyle === '') {
            return $overrideStyle;
        }
        if ($overrideStyle === '') {
            return $baseStyle;
        }

        return rtrim($baseStyle, ';') . '; ' . ltrim($overrideStyle);
    }

    /**
     * @param list<string> $properties
     */
    private function removeCssStyleProperties(string $style, array $properties): string
    {
        if ($style === '' || $properties === []) {
            return $style;
        }

        $remove = array_fill_keys(array_map('strtolower', $properties), true);
        $declarations = [];
        foreach (explode(';', $style) as $declaration) {
            $declaration = trim($declaration);
            if ($declaration === '') {
                continue;
            }

            [$property] = array_pad(explode(':', $declaration, 2), 2, '');
            if (isset($remove[strtolower(trim($property))])) {
                continue;
            }

            $declarations[] = $declaration;
        }

        return implode('; ', $declarations);
    }

    /**
     * @param list<string> $classes
     * @param array<string, string> $attributes
     */
    private function appendTableStyleAttrs(\DOMElement $properties, array &$classes, array &$attributes): void
    {
        $style = $this->firstChildElement($properties, self::WORDPROCESSINGML_NS, 'tblStyle');
        if (!$style instanceof \DOMElement) {
            return;
        }

        $value = trim((string) ($this->wordAttr($style, 'val') ?? ''));
        if ($value === '') {
            return;
        }

        $classes[] = 'docx-table-style';
        $suffix = $this->metadataClassSuffix($value);
        if ($suffix !== null) {
            $classes[] = 'docx-table-style-' . $suffix;
        }
        $attributes['data-docx-table-style'] = $value;
    }

    /**
     * @param list<string> $classes
     * @param array<string, string> $attributes
     * @param list<string> $styles
     */
    private function appendTableWidthAttrs(\DOMElement $properties, array &$classes, array &$attributes, array &$styles): void
    {
        $width = $this->firstChildElement($properties, self::WORDPROCESSINGML_NS, 'tblW');
        if (!$width instanceof \DOMElement) {
            return;
        }

        $type = strtolower(trim((string) ($this->wordAttr($width, 'type') ?? '')));
        if (!in_array($type, ['dxa', 'pct', 'auto'], true)) {
            return;
        }

        $classes[] = 'docx-table-width';
        $classes[] = 'docx-table-width-' . $type;
        $attributes['data-docx-table-width-type'] = $type;

        $value = trim((string) ($this->wordAttr($width, 'w') ?? ''));
        if ($value === '') {
            return;
        }

        $attributes['data-docx-table-width-value'] = $value;
        if (preg_match('/^\d+(?:\.\d+)?$/D', $value) !== 1) {
            return;
        }

        $numericValue = (float) $value;
        if ($numericValue > 0.0 && $type === 'dxa') {
            $points = $this->formatOpenXmlCssNumber($numericValue / 20.0);
            $attributes['data-docx-table-width-points'] = $points;
            $styles[] = 'width:' . $points . 'pt';
        } elseif ($numericValue > 0.0 && $type === 'pct') {
            $percent = $this->formatOpenXmlCssNumber($numericValue / 50.0);
            $attributes['data-docx-table-width-percent'] = $percent;
            $styles[] = 'width:' . $percent . '%';
        }
    }

    /**
     * @param list<string> $classes
     * @param array<string, string> $attributes
     */
    private function appendTableAlignmentAttrs(\DOMElement $properties, array &$classes, array &$attributes): void
    {
        $alignment = $this->firstChildElement($properties, self::WORDPROCESSINGML_NS, 'jc');
        if (!$alignment instanceof \DOMElement) {
            return;
        }

        $value = strtolower(trim((string) ($this->wordAttr($alignment, 'val') ?? '')));
        $suffix = $this->metadataClassSuffix($value);
        if ($suffix === null) {
            return;
        }

        $classes[] = 'docx-table-align';
        $classes[] = 'docx-table-align-' . $suffix;
        $attributes['data-docx-table-align'] = $value;
    }

    /**
     * @param list<string> $classes
     * @param array<string, string> $attributes
     * @param list<string> $styles
     */
    private function appendTableIndentAttrs(\DOMElement $properties, array &$classes, array &$attributes, array &$styles): void
    {
        $indent = $this->firstChildElement($properties, self::WORDPROCESSINGML_NS, 'tblInd');
        if (!$indent instanceof \DOMElement) {
            return;
        }

        $type = strtolower(trim((string) ($this->wordAttr($indent, 'type') ?? '')));
        if (!in_array($type, ['dxa', 'pct'], true)) {
            return;
        }

        $value = trim((string) ($this->wordAttr($indent, 'w') ?? ''));
        if ($value === '') {
            return;
        }

        $classes[] = 'docx-table-indent';
        $classes[] = 'docx-table-indent-' . $type;
        $attributes['data-docx-table-indent-type'] = $type;
        $attributes['data-docx-table-indent-value'] = $value;

        if (preg_match('/^\d+(?:\.\d+)?$/D', $value) !== 1) {
            return;
        }

        $numericValue = (float) $value;
        if ($numericValue > 0.0 && $type === 'dxa') {
            $points = $this->formatOpenXmlCssNumber($numericValue / 20.0);
            $attributes['data-docx-table-indent-left-points'] = $points;
            $styles[] = 'margin-left:' . $points . 'pt';
        } elseif ($numericValue > 0.0 && $type === 'pct') {
            $percent = $this->formatOpenXmlCssNumber($numericValue / 50.0);
            $attributes['data-docx-table-indent-left-percent'] = $percent;
            $styles[] = 'margin-left:' . $percent . '%';
        }
    }

    /**
     * @param list<string> $classes
     * @param array<string, string> $attributes
     */
    private function appendTableLayoutAttrs(\DOMElement $properties, array &$classes, array &$attributes): void
    {
        $layout = $this->firstChildElement($properties, self::WORDPROCESSINGML_NS, 'tblLayout');
        if (!$layout instanceof \DOMElement) {
            return;
        }

        $value = strtolower(trim((string) ($this->wordAttr($layout, 'type') ?? '')));
        $suffix = $this->metadataClassSuffix($value);
        if ($suffix === null) {
            return;
        }

        $classes[] = 'docx-table-layout';
        $classes[] = 'docx-table-layout-' . $suffix;
        $attributes['data-docx-table-layout'] = $value;
    }

    /**
     * @return array{widths?:list<?float>, columnSources?:list<array<string, mixed>>}
     */
    private function tableGridAttrs(\DOMElement $table): array
    {
        $grid = $this->firstChildElement($table, self::WORDPROCESSINGML_NS, 'tblGrid');
        if (!$grid instanceof \DOMElement) {
            return [];
        }

        $columns = [];
        $totalTwips = 0;
        foreach ($grid->childNodes as $node) {
            if (!$node instanceof \DOMElement || $node->namespaceURI !== self::WORDPROCESSINGML_NS || $node->localName !== 'gridCol') {
                continue;
            }

            $rawWidth = trim((string) ($this->wordAttr($node, 'w') ?? ''));
            $widthTwips = null;
            $issue = null;
            if ($rawWidth === '') {
                $issue = 'missing-width';
            } elseif (preg_match('/^\d+$/D', $rawWidth) !== 1) {
                $issue = 'invalid-width';
            } else {
                $candidate = (int) $rawWidth;
                if ($candidate > 0) {
                    $widthTwips = $candidate;
                    $totalTwips += $candidate;
                } else {
                    $issue = 'non-positive-width';
                }
            }

            $columns[] = [
                'rawWidth' => $rawWidth,
                'widthTwips' => $widthTwips,
                'issue' => $issue,
            ];
        }

        if ($columns === []) {
            return [];
        }

        $widths = [];
        $columnSources = [];
        foreach ($columns as $index => $column) {
            $widthTwips = $column['widthTwips'];
            $source = [
                'kind' => 'docx-tblGrid',
                'column' => $index,
                'gridIndex' => $index,
                'widthTwips' => $widthTwips,
            ];

            if (is_int($widthTwips) && $totalTwips > 0) {
                $width = $this->roundTableGridWidth($widthTwips / $totalTwips);
                $widths[] = $width;
                $source['widthPercent'] = $this->roundTableGridWidth($width * 100.0);
            } else {
                $widths[] = null;
                $source['rawWidth'] = $column['rawWidth'];
                $source['issue'] = $column['issue'];
            }

            $columnSources[] = $source;
        }

        return [
            'widths' => $widths,
            'columnSources' => $columnSources,
        ];
    }

    /**
     * @param list<\DOMElement> $rowElements
     */
    private function tableInferredColumnCount(array $rowElements): int
    {
        $columnCount = 0;
        foreach ($rowElements as $rowElement) {
            $rowColumnCount = $this->tableRowGridOmissionCount($rowElement, 'gridBefore')
                + $this->tableRowGridOmissionCount($rowElement, 'gridAfter');
            foreach ($this->directWordChildElements($rowElement, 'tc') as $cellElement) {
                $rowColumnCount += $this->tableCellGridSpan($cellElement);
            }

            $columnCount = max($columnCount, $rowColumnCount);
        }

        return $columnCount;
    }

    private function roundTableGridWidth(float $value): float
    {
        return round($value, 6);
    }

    private function tablePropertyValue(\DOMElement $properties, string $localName): ?string
    {
        $child = $this->firstChildElement($properties, self::WORDPROCESSINGML_NS, $localName);
        if (!$child instanceof \DOMElement) {
            return null;
        }

        $value = $this->wordAttr($child, 'val');
        if ($value === null) {
            $value = $child->textContent;
        }

        $value = trim($value);

        return $value === '' ? null : $value;
    }

    /**
     * @return array{classes?:list<string>, attributes?:array<string, string>, htmlAttributes?:array<string, string>}
     */
    private function tableRowAttrs(\DOMElement $row): array
    {
        $properties = $this->tableRowPropertiesElement($row);
        if (!$properties instanceof \DOMElement) {
            return [];
        }

        $classes = [];
        $attributes = [];
        $htmlAttributes = [];

        $header = $this->firstChildElement($properties, self::WORDPROCESSINGML_NS, 'tblHeader');
        if ($header instanceof \DOMElement && $this->onOffWordAttr($header, 'val', true)) {
            $classes[] = 'docx-table-row-repeat-header';
            $this->appendTableRowDataAttr($attributes, $htmlAttributes, 'data-docx-table-row-repeat-header', 'true');
        }

        $cantSplit = $this->firstChildElement($properties, self::WORDPROCESSINGML_NS, 'cantSplit');
        if ($cantSplit instanceof \DOMElement && $this->onOffWordAttr($cantSplit, 'val', true)) {
            $classes[] = 'docx-table-row-cant-split';
            $this->appendTableRowDataAttr($attributes, $htmlAttributes, 'data-docx-table-row-cant-split', 'true');
        }

        $this->appendTableRowHeightAttrs($properties, $classes, $attributes, $htmlAttributes);
        $this->appendTableRowOmittedGridAttrs($properties, $classes, $attributes, $htmlAttributes);

        if ($classes === [] && $attributes === [] && $htmlAttributes === []) {
            return [];
        }

        $attrs = [];
        if ($classes !== []) {
            $attrs['classes'] = array_values(array_unique($classes));
        }
        if ($attributes !== []) {
            $attrs['attributes'] = $attributes;
        }
        if ($htmlAttributes !== []) {
            $attrs['htmlAttributes'] = $htmlAttributes;
        }

        return $attrs;
    }

    private function tableRowPropertiesElement(\DOMElement $rowOrProperties): ?\DOMElement
    {
        if ($this->isWordElement($rowOrProperties, 'trPr')) {
            return $rowOrProperties;
        }

        return $this->firstChildElement($rowOrProperties, self::WORDPROCESSINGML_NS, 'trPr');
    }

    /**
     * @param array{classes?:list<string>, attributes?:array<string, string>, htmlAttributes?:array<string, string>}|null $base
     * @param array{classes?:list<string>, attributes?:array<string, string>, htmlAttributes?:array<string, string>} $override
     * @return array{classes?:list<string>, attributes?:array<string, string>, htmlAttributes?:array<string, string>}
     */
    private function mergeTableRowAttrs(?array $base, array $override): array
    {
        if ($base === null) {
            return $override;
        }

        return $this->mergeTableCellAttrs($base, $override);
    }

    /**
     * @param list<string> $classes
     * @param array<string, string> $attributes
     * @param array<string, string> $htmlAttributes
     */
    private function appendTableRowHeightAttrs(
        \DOMElement $properties,
        array &$classes,
        array &$attributes,
        array &$htmlAttributes
    ): void {
        $height = $this->firstChildElement($properties, self::WORDPROCESSINGML_NS, 'trHeight');
        if (!$height instanceof \DOMElement) {
            return;
        }

        $sourceRule = trim((string) ($this->wordAttr($height, 'hRule') ?? 'auto'));
        $rule = match (strtolower($sourceRule)) {
            '', 'auto' => 'auto',
            'atleast' => 'atLeast',
            'exact' => 'exact',
            default => null,
        };
        if ($rule === null) {
            return;
        }

        $classes[] = 'docx-table-row-height';
        $classes[] = 'docx-table-row-height-' . ($rule === 'atLeast' ? 'at-least' : $rule);
        $this->appendTableRowDataAttr($attributes, $htmlAttributes, 'data-docx-table-row-height-rule', $rule);

        $value = trim((string) ($this->wordAttr($height, 'val') ?? ''));
        if ($value === '') {
            return;
        }

        $this->appendTableRowDataAttr($attributes, $htmlAttributes, 'data-docx-table-row-height-value', $value);
        if (preg_match('/^\d+(?:\.\d+)?$/D', $value) !== 1) {
            return;
        }

        $numericValue = (float) $value;
        if ($numericValue <= 0.0) {
            return;
        }

        $points = $this->formatOpenXmlCssNumber($numericValue / 20.0);
        $this->appendTableRowDataAttr($attributes, $htmlAttributes, 'data-docx-table-row-height-points', $points);
        if ($rule === 'exact') {
            $htmlAttributes['style'] = $this->appendCssStyle($htmlAttributes['style'] ?? '', 'height:' . $points . 'pt');
        } elseif ($rule === 'atLeast') {
            $htmlAttributes['style'] = $this->appendCssStyle($htmlAttributes['style'] ?? '', 'min-height:' . $points . 'pt');
        }
    }

    /**
     * @param list<string> $classes
     * @param array<string, string> $attributes
     * @param array<string, string> $htmlAttributes
     */
    private function appendTableRowOmittedGridAttrs(
        \DOMElement $properties,
        array &$classes,
        array &$attributes,
        array &$htmlAttributes
    ): void {
        foreach ([
            'before' => ['gridBefore', 'wBefore'],
            'after' => ['gridAfter', 'wAfter'],
        ] as $position => [$gridName, $widthName]) {
            $count = $this->tableRowGridOmissionCountFromProperties($properties, $gridName);
            if ($count <= 0) {
                continue;
            }

            $classes[] = 'docx-table-row-grid-' . $position;
            $this->appendTableRowDataAttr($attributes, $htmlAttributes, 'data-docx-table-row-grid-' . $position, (string) $count);

            $widthAttrs = $this->tableRowOmittedGridWidthAttrs($properties, $widthName);
            if ($widthAttrs === []) {
                continue;
            }

            $classes[] = 'docx-table-row-width-' . $position;
            $classes[] = 'docx-table-row-width-' . $position . '-' . $widthAttrs['type'];
            foreach ($widthAttrs as $name => $value) {
                $this->appendTableRowDataAttr(
                    $attributes,
                    $htmlAttributes,
                    'data-docx-table-row-width-' . $position . '-' . $name,
                    $value
                );
            }
        }
    }

    /**
     * @return array{type:string, value?:string, points?:string, percent?:string}|array{}
     */
    private function tableRowOmittedGridWidthAttrs(\DOMElement $properties, string $localName): array
    {
        $width = $this->firstChildElement($properties, self::WORDPROCESSINGML_NS, $localName);
        if (!$width instanceof \DOMElement) {
            return [];
        }

        $type = strtolower(trim((string) ($this->wordAttr($width, 'type') ?? 'dxa')));
        if ($type === '') {
            $type = 'dxa';
        }
        if (in_array($type, ['nil', 'none', '0', 'false', 'off'], true)) {
            return [];
        }
        if (!in_array($type, ['dxa', 'pct', 'auto'], true)) {
            return [];
        }

        $attrs = [
            'type' => $type,
        ];
        $value = trim((string) ($this->wordAttr($width, 'w') ?? ''));
        if ($value !== '') {
            $attrs['value'] = $value;
        }
        if ($value === '' || preg_match('/^\d+(?:\.\d+)?$/D', $value) !== 1) {
            return $attrs;
        }

        $numericValue = (float) $value;
        if ($numericValue <= 0.0) {
            return $attrs;
        }
        if ($type === 'dxa') {
            $attrs['points'] = $this->formatOpenXmlCssNumber($numericValue / 20.0);
        } elseif ($type === 'pct') {
            $attrs['percent'] = $this->formatOpenXmlCssNumber($numericValue / 50.0);
        }

        return $attrs;
    }

    private function tableRowGridOmissionCount(\DOMElement $row, string $localName): int
    {
        $properties = $this->tableRowPropertiesElement($row);
        if (!$properties instanceof \DOMElement) {
            return 0;
        }

        return $this->tableRowGridOmissionCountFromProperties($properties, $localName);
    }

    private function tableRowGridOmissionCountFromProperties(\DOMElement $properties, string $localName): int
    {
        $child = $this->firstChildElement($properties, self::WORDPROCESSINGML_NS, $localName);
        if (!$child instanceof \DOMElement) {
            return 0;
        }

        $count = $this->optionalIntWordAttr($child, 'val');

        return $count !== null && $count > 0 ? $count : 0;
    }

    /**
     * @return list<AstNode>
     */
    private function tableRowOmittedGridCells(\DOMElement $row, string $position): array
    {
        $localName = $position === 'before' ? 'gridBefore' : 'gridAfter';
        $count = $this->tableRowGridOmissionCount($row, $localName);
        if ($count <= 0) {
            return [];
        }

        $cells = [];
        for ($index = 1; $index <= $count; $index++) {
            $cells[] = new AstNode(
                'table_cell',
                $this->tableRowOmittedGridCellAttrs($position, $count, $index),
                []
            );
        }

        return $cells;
    }

    /**
     * @return array{classes:list<string>, attributes:array<string, string>, htmlAttributes:array<string, string>, text:string}
     */
    private function tableRowOmittedGridCellAttrs(string $position, int $count, int $index): array
    {
        $attributes = [
            'data-docx-omitted-table-cell' => $position,
            'data-docx-omitted-grid-count' => (string) $count,
            'data-docx-omitted-grid-index' => (string) $index,
        ];

        return [
            'classes' => ['docx-omitted-table-cell', 'docx-omitted-table-cell-' . $position],
            'attributes' => $attributes,
            'htmlAttributes' => [
                ...$attributes,
                'aria-hidden' => 'true',
            ],
            'text' => '',
        ];
    }

    /**
     * @param array<string, string> $attributes
     * @param array<string, string> $htmlAttributes
     */
    private function appendTableRowDataAttr(array &$attributes, array &$htmlAttributes, string $name, string $value): void
    {
        $attributes[$name] = $value;
        $htmlAttributes[$name] = $value;
    }

    private function appendCssStyle(string $existing, string $declaration): string
    {
        $existing = trim($existing);
        if ($existing === '') {
            return $declaration;
        }

        return rtrim($existing, ';') . '; ' . ltrim($declaration);
    }

    /**
     * @return array{classes?:list<string>, attributes?:array<string, string>, htmlAttributes?:array<string, string>}
     */
    private function tableCellAttrs(\DOMElement $cell): array
    {
        $attrs = null;
        foreach ([
            $this->tableCellWidthAttrs($cell),
            $this->tableCellMarginAttrs($cell),
            $this->tableCellBorderAttrs($cell),
            $this->tableCellVerticalAlignmentAttrs($cell),
            $this->tableCellShadingAttrs($cell),
        ] as $source) {
            if ($source === []) {
                continue;
            }

            $attrs = $this->mergeTableCellAttrs($attrs, $source);
        }

        return $attrs ?? [];
    }

    /**
     * @param array{classes?:list<string>, attributes?:array<string, string>, htmlAttributes?:array<string, string>}|null $base
     * @param array{classes?:list<string>, attributes?:array<string, string>, htmlAttributes?:array<string, string>} $override
     * @return array{classes?:list<string>, attributes?:array<string, string>, htmlAttributes?:array<string, string>}
     */
    private function mergeTableCellAttrs(?array $base, array $override): array
    {
        if ($base === null) {
            return $override;
        }

        foreach (['classes', 'attributes', 'htmlAttributes'] as $key) {
            $values = $override[$key] ?? null;
            if (!is_array($values) || $values === []) {
                continue;
            }

            if ($key === 'classes') {
                $base[$key] = array_values(array_unique([
                    ...(is_array($base[$key] ?? null) ? $base[$key] : []),
                    ...$values,
                ]));
                continue;
            }

            if ($key === 'htmlAttributes') {
                $baseValues = is_array($base[$key] ?? null) ? $base[$key] : [];
                $existingStyle = trim((string) ($baseValues['style'] ?? ''));
                $incomingStyle = trim((string) ($values['style'] ?? ''));
                if ($existingStyle !== '' && $incomingStyle !== '') {
                    $values['style'] = rtrim($existingStyle, ';') . '; ' . ltrim($incomingStyle);
                } elseif ($existingStyle !== '' && $incomingStyle === '') {
                    $values['style'] = $existingStyle;
                }

                $base[$key] = array_replace($baseValues, $values);
                continue;
            }

            $base[$key] = array_replace(
                is_array($base[$key] ?? null) ? $base[$key] : [],
                $values,
            );
        }

        return $base;
    }

    private function tableCellPropertiesElement(\DOMElement $cellOrProperties): ?\DOMElement
    {
        if ($this->isWordElement($cellOrProperties, 'tcPr')) {
            return $cellOrProperties;
        }

        return $this->firstChildElement($cellOrProperties, self::WORDPROCESSINGML_NS, 'tcPr');
    }

    /**
     * @return array{classes?:list<string>, attributes?:array<string, string>, htmlAttributes?:array<string, string>}
     */
    private function tableCellMarginAttrs(\DOMElement $cell): array
    {
        $properties = $this->tableCellPropertiesElement($cell);
        if (!$properties instanceof \DOMElement) {
            return [];
        }

        $margins = $this->firstChildElement($properties, self::WORDPROCESSINGML_NS, 'tcMar');
        if (!$margins instanceof \DOMElement) {
            return [];
        }

        $classes = ['docx-cell-margin'];
        $typeClasses = [];
        $attributes = [];
        $styles = [];
        foreach ([
            'top' => 'padding-top',
            'start' => 'padding-inline-start',
            'left' => 'padding-left',
            'bottom' => 'padding-bottom',
            'end' => 'padding-inline-end',
            'right' => 'padding-right',
        ] as $localName => $cssProperty) {
            $margin = $this->firstChildElement($margins, self::WORDPROCESSINGML_NS, $localName);
            if (!$margin instanceof \DOMElement) {
                continue;
            }

            $edgeAttributes = $this->tableCellMarginEdgeAttrs($margin, $cssProperty);
            if ($edgeAttributes === []) {
                continue;
            }

            $classes[] = 'docx-cell-margin-' . $localName;
            $typeClasses[] = 'docx-cell-margin-' . $edgeAttributes['type'];
            foreach ($edgeAttributes as $name => $value) {
                if ($name === 'style') {
                    continue;
                }

                $attributes['data-docx-cell-margin-' . $localName . '-' . $name] = $value;
            }

            if (isset($edgeAttributes['style'])) {
                $styles[] = $edgeAttributes['style'];
            }
        }

        if ($attributes === []) {
            return [];
        }

        $attrs = [
            'classes' => array_values(array_unique([
                ...$classes,
                ...$typeClasses,
            ])),
            'attributes' => $attributes,
        ];
        if ($styles !== []) {
            $attrs['htmlAttributes'] = [
                'style' => implode('; ', $styles),
            ];
        }

        return $attrs;
    }

    /**
     * @return array{type:string, value?:string, points?:string, percent?:string, style?:string}|array{}
     */
    private function tableCellMarginEdgeAttrs(\DOMElement $margin, string $cssProperty): array
    {
        $type = strtolower(trim((string) ($this->wordAttr($margin, 'type') ?? 'dxa')));
        if ($type === '') {
            $type = 'dxa';
        }
        if (in_array($type, ['nil', 'none', '0', 'false', 'off'], true)) {
            return [];
        }
        if (!in_array($type, ['dxa', 'pct', 'auto'], true)) {
            return [];
        }

        $edgeAttributes = [
            'type' => $type,
        ];
        $value = trim((string) ($this->wordAttr($margin, 'w') ?? ''));
        if ($value !== '') {
            $edgeAttributes['value'] = $value;
        }
        if ($value === '' || preg_match('/^\d+(?:\.\d+)?$/D', $value) !== 1) {
            return $edgeAttributes;
        }

        $numericValue = (float) $value;
        if ($numericValue <= 0.0) {
            return $edgeAttributes;
        }
        if ($type === 'dxa') {
            $points = $this->formatOpenXmlCssNumber($numericValue / 20.0);
            $edgeAttributes['points'] = $points;
            $edgeAttributes['style'] = $cssProperty . ':' . $points . 'pt';
        } elseif ($type === 'pct') {
            $percent = $this->formatOpenXmlCssNumber($numericValue / 50.0);
            $edgeAttributes['percent'] = $percent;
            $edgeAttributes['style'] = $cssProperty . ':' . $percent . '%';
        }

        return $edgeAttributes;
    }

    /**
     * @return array{classes?:list<string>, attributes?:array<string, string>, htmlAttributes?:array<string, string>}
     */
    private function tableCellWidthAttrs(\DOMElement $cell): array
    {
        $properties = $this->tableCellPropertiesElement($cell);
        if (!$properties instanceof \DOMElement) {
            return [];
        }

        $width = $this->firstChildElement($properties, self::WORDPROCESSINGML_NS, 'tcW');
        if (!$width instanceof \DOMElement) {
            return [];
        }

        $type = strtolower(trim((string) ($this->wordAttr($width, 'type') ?? '')));
        if (!in_array($type, ['dxa', 'pct', 'auto'], true)) {
            return [];
        }

        $classes = ['docx-cell-width', 'docx-cell-width-' . $type];
        $attributes = [
            'data-docx-cell-width-type' => $type,
        ];

        $value = trim((string) ($this->wordAttr($width, 'w') ?? ''));
        if ($value !== '') {
            $attributes['data-docx-cell-width-value'] = $value;
        }

        $htmlAttributes = [];
        if ($value !== '' && preg_match('/^\d+(?:\.\d+)?$/D', $value) === 1) {
            $numericValue = (float) $value;
            if ($numericValue > 0.0 && $type === 'dxa') {
                $points = $this->formatOpenXmlCssNumber($numericValue / 20.0);
                $attributes['data-docx-cell-width-points'] = $points;
                $htmlAttributes['style'] = 'width:' . $points . 'pt';
            } elseif ($numericValue > 0.0 && $type === 'pct') {
                $percent = $this->formatOpenXmlCssNumber($numericValue / 50.0);
                $attributes['data-docx-cell-width-percent'] = $percent;
                $htmlAttributes['style'] = 'width:' . $percent . '%';
            }
        }

        $attrs = [
            'classes' => $classes,
            'attributes' => $attributes,
        ];
        if ($htmlAttributes !== []) {
            $attrs['htmlAttributes'] = $htmlAttributes;
        }

        return $attrs;
    }

    /**
     * @return array{classes?:list<string>, attributes?:array<string, string>, htmlAttributes?:array<string, string>}
     */
    private function tableCellBorderAttrs(\DOMElement $cell): array
    {
        $properties = $this->tableCellPropertiesElement($cell);
        if (!$properties instanceof \DOMElement) {
            return [];
        }

        $borders = $this->firstChildElement($properties, self::WORDPROCESSINGML_NS, 'tcBorders');
        if (!$borders instanceof \DOMElement) {
            return [];
        }

        $classes = ['docx-cell-border'];
        $attributes = [];
        $styles = [];
        foreach ([
            'top' => 'top',
            'left' => 'left',
            'bottom' => 'bottom',
            'right' => 'right',
            'insideH' => 'inside-h',
            'insideV' => 'inside-v',
            'tl2br' => 'tl2br',
            'tr2bl' => 'tr2bl',
        ] as $localName => $edge) {
            $border = $this->firstChildElement($borders, self::WORDPROCESSINGML_NS, $localName);
            if (!$border instanceof \DOMElement) {
                continue;
            }

            $edgeAttributes = $this->tableCellBorderEdgeAttrs($border);
            if ($edgeAttributes === []) {
                continue;
            }

            $classes[] = 'docx-cell-border-' . $edge;
            $suffix = isset($edgeAttributes['val']) ? $this->tableCellBorderClassSuffix($edgeAttributes['val']) : null;
            if ($suffix !== null) {
                $classes[] = 'docx-cell-border-' . $edge . '-' . $suffix;
            }

            foreach ($edgeAttributes as $name => $metadata) {
                $attributes['data-docx-cell-border-' . $edge . '-' . $name] = $metadata;
            }

            $style = $this->tableCellBorderCssStyle($edge, $edgeAttributes);
            if ($style !== null) {
                $styles[] = $style;
            }
        }

        if ($attributes === []) {
            return [];
        }

        $attrs = [
            'classes' => array_values(array_unique($classes)),
            'attributes' => $attributes,
        ];
        if ($styles !== []) {
            $attrs['htmlAttributes'] = [
                'style' => implode('; ', $styles),
            ];
        }

        return $attrs;
    }

    /**
     * @return array<string, string>
     */
    private function tableCellBorderEdgeAttrs(\DOMElement $border): array
    {
        $edgeAttributes = [];
        $value = trim((string) ($this->wordAttr($border, 'val') ?? ''));
        if (in_array(strtolower($value), ['none', 'nil', '0', 'false', 'off'], true)) {
            return [];
        }
        if ($value !== '') {
            $edgeAttributes['val'] = $value;
        }

        foreach ([
            'space' => 'space-points',
            'color' => 'color',
            'themeColor' => 'theme-color',
            'themeTint' => 'theme-tint',
            'themeShade' => 'theme-shade',
            'frame' => 'frame',
            'shadow' => 'shadow',
        ] as $source => $target) {
            $metadata = trim((string) ($this->wordAttr($border, $source) ?? ''));
            if ($metadata !== '') {
                $edgeAttributes[$target] = $metadata;
            }
        }

        $size = trim((string) ($this->wordAttr($border, 'sz') ?? ''));
        if ($size !== '') {
            $edgeAttributes['size-eighth-points'] = $size;
            if (preg_match('/^\d+(?:\.\d+)?$/D', $size) === 1 && (float) $size > 0.0) {
                $edgeAttributes['width-points'] = $this->formatOpenXmlCssNumber((float) $size / 8.0);
            }
        }

        return $edgeAttributes;
    }

    /**
     * @param array<string, string> $edgeAttributes
     */
    private function tableCellBorderCssStyle(string $edge, array $edgeAttributes): ?string
    {
        $property = match ($edge) {
            'top', 'inside-h' => 'border-top',
            'left', 'inside-v' => 'border-left',
            'bottom' => 'border-bottom',
            'right' => 'border-right',
            default => null,
        };
        if ($property === null) {
            return null;
        }

        $width = $edgeAttributes['width-points'] ?? '';
        $color = $edgeAttributes['color'] ?? '';
        $style = match (strtolower($edgeAttributes['val'] ?? '')) {
            'single', 'thick' => 'solid',
            'double' => 'double',
            'dashed', 'dash' => 'dashed',
            'dotted', 'dot' => 'dotted',
            default => null,
        };

        if ($width === '' || $style === null || preg_match('/^[0-9A-Fa-f]{6}$/D', $color) !== 1) {
            return null;
        }

        return $property . ':' . $width . 'pt ' . $style . ' #' . strtoupper($color);
    }

    private function tableCellBorderClassSuffix(string $value): ?string
    {
        $hyphenated = preg_replace('/(?<!^)[A-Z]/', '-$0', $value) ?? $value;

        return $this->metadataClassSuffix($hyphenated);
    }

    /**
     * @return array{classes?:list<string>, attributes?:array<string, string>, htmlAttributes?:array<string, string>}
     */
    private function tableCellVerticalAlignmentAttrs(\DOMElement $cell): array
    {
        $properties = $this->tableCellPropertiesElement($cell);
        if (!$properties instanceof \DOMElement) {
            return [];
        }

        $alignment = $this->firstChildElement($properties, self::WORDPROCESSINGML_NS, 'vAlign');
        if (!$alignment instanceof \DOMElement) {
            return [];
        }

        $sourceValue = strtolower(trim((string) ($this->wordAttr($alignment, 'val') ?? '')));
        $htmlValue = match ($sourceValue) {
            'top' => 'top',
            'center' => 'middle',
            'bottom' => 'bottom',
            default => null,
        };
        if ($htmlValue === null) {
            return [];
        }

        return [
            'classes' => ['docx-cell-vertical-align', 'docx-cell-vertical-align-' . $sourceValue],
            'attributes' => [
                'data-docx-cell-vertical-align' => $sourceValue,
            ],
            'htmlAttributes' => [
                'valign' => $htmlValue,
            ],
        ];
    }

    /**
     * @return array{classes?:list<string>, attributes?:array<string, string>, htmlAttributes?:array<string, string>}
     */
    private function tableCellShadingAttrs(\DOMElement $cell): array
    {
        $properties = $this->tableCellPropertiesElement($cell);
        if (!$properties instanceof \DOMElement) {
            return [];
        }

        $shading = $this->firstChildElement($properties, self::WORDPROCESSINGML_NS, 'shd');
        if (!$shading instanceof \DOMElement) {
            return [];
        }

        $sourceValues = $this->tableCellShadingSourceValues($shading);

        $shadingValue = strtolower($sourceValues['val'] ?? '');
        if ($shadingValue === 'nil' || $sourceValues === []) {
            return [];
        }

        $classes = ['docx-cell-shading'];
        $suffix = $shadingValue !== '' ? $this->metadataClassSuffix($shadingValue) : null;
        if ($suffix !== null) {
            $classes[] = 'docx-cell-shading-' . $suffix;
        }

        $fill = $sourceValues['fill'] ?? '';
        if (preg_match('/^[0-9A-Fa-f]{6}$/D', $fill) === 1) {
            $classes[] = 'docx-cell-fill-' . strtolower($fill);
        }

        $attributes = [];
        foreach ($sourceValues as $name => $value) {
            $attributes['data-docx-cell-shading-' . $name] = $value;
        }

        $htmlAttributes = [];
        if (preg_match('/^[0-9A-Fa-f]{6}$/D', $fill) === 1) {
            $htmlAttributes['style'] = 'background-color:#' . strtoupper($fill);
        }

        $attrs = [
            'classes' => array_values(array_unique($classes)),
            'attributes' => $attributes,
        ];
        if ($htmlAttributes !== []) {
            $attrs['htmlAttributes'] = $htmlAttributes;
        }

        return $attrs;
    }

    /**
     * @return array<string, string>
     */
    private function tableCellShadingSourceValues(\DOMElement $shading): array
    {
        $sourceValues = [];
        foreach ([
            'val' => 'val',
            'fill' => 'fill',
            'color' => 'color',
            'themeFill' => 'theme-fill',
            'themeFillTint' => 'theme-fill-tint',
            'themeFillShade' => 'theme-fill-shade',
            'themeColor' => 'theme-color',
            'themeTint' => 'theme-tint',
            'themeShade' => 'theme-shade',
        ] as $source => $target) {
            $value = trim((string) ($this->wordAttr($shading, $source) ?? ''));
            if ($value !== '') {
                $sourceValues[$target] = $value;
            }
        }

        return $sourceValues;
    }

    private function formatOpenXmlCssNumber(float $value): string
    {
        $formatted = rtrim(rtrim(number_format($value, 4, '.', ''), '0'), '.');

        return $formatted === '-0' ? '0' : $formatted;
    }

    /**
     * @param array<string, AstNode> $referencedNotes
     * @param array<string, array{name:?string, basedOn:?string, headingLevel:?int, numPr:?array{numId:?string, level:?int}}> $styles
     * @param array<string, array<int, array{ordered:bool, style:string, delimiter:string, start:int, format:string}>> $numbering
     * @param array{kind:string, startType:string}|null $activeProofError
     * @param list<AstNode> $activeProofErrorNodes
     * @param array{classes:list<string>, attributes:array<string, string>}|null $activePermissionRange
     * @param list<AstNode> $activePermissionRangeNodes
     * @param array{type:string, classes:list<string>, attributes:array<string, string>}|null $activeMoveRange
     * @param list<AstNode> $activeMoveRangeNodes
     * @return list<AstNode>
     */
    private function tableCellBlocks(
        \DOMElement $cell,
        ZipPackage $package,
        ?OpcRelationships $relationships,
        array $referencedNotes,
        array $styles,
        array $numbering,
        ?string &$activeCommentRangeId,
        ?array &$activeProofError,
        array &$activeProofErrorNodes,
        ?array &$activePermissionRange,
        array &$activePermissionRangeNodes,
        ?array &$activeMoveRange,
        array &$activeMoveRangeNodes,
        ?array $conditionalParagraphMetadata = null,
        ?array $conditionalRunProperties = null
    ): array
    {
        $previousParagraphMetadata = $this->currentTableConditionalParagraphMetadata;
        $previousRunProperties = $this->currentTableConditionalRunProperties;
        $this->currentTableConditionalParagraphMetadata = $conditionalParagraphMetadata;
        $this->currentTableConditionalRunProperties = $conditionalRunProperties;

        try {
            return $this->blockContainerChildrenWithRanges(
                $cell,
                $package,
                $relationships,
                $referencedNotes,
                $styles,
                $numbering,
                $activeCommentRangeId,
                $activeProofError,
                $activeProofErrorNodes,
                $activePermissionRange,
                $activePermissionRangeNodes,
                $activeMoveRange,
                $activeMoveRangeNodes
            );
        } finally {
            $this->currentTableConditionalParagraphMetadata = $previousParagraphMetadata;
            $this->currentTableConditionalRunProperties = $previousRunProperties;
        }
    }

    private function tableCellGridSpan(\DOMElement $cell): int
    {
        $properties = $this->firstChildElement($cell, self::WORDPROCESSINGML_NS, 'tcPr');
        if (!$properties instanceof \DOMElement) {
            return 1;
        }

        $gridSpan = $this->firstChildElement($properties, self::WORDPROCESSINGML_NS, 'gridSpan');
        if (!$gridSpan instanceof \DOMElement) {
            return 1;
        }

        return max(1, $this->intWordAttr($gridSpan, 'val', 1));
    }

    private function tableCellVerticalMerge(\DOMElement $cell): ?string
    {
        $properties = $this->firstChildElement($cell, self::WORDPROCESSINGML_NS, 'tcPr');
        if (!$properties instanceof \DOMElement) {
            return null;
        }

        $verticalMerge = $this->firstChildElement($properties, self::WORDPROCESSINGML_NS, 'vMerge');
        if (!$verticalMerge instanceof \DOMElement) {
            return null;
        }

        $value = strtolower((string) ($this->wordAttr($verticalMerge, 'val') ?? 'continue'));

        return $value === 'restart' ? 'restart' : 'continue';
    }

    /**
     * @param list<AstNode> $rows
     */
    private function extendTableCellRowspan(array &$rows, int $rowIndex, int $cellIndex): void
    {
        $row = $rows[$rowIndex] ?? null;
        if (!$row instanceof AstNode || !isset($row->children[$cellIndex])) {
            return;
        }

        $cell = $row->children[$cellIndex];
        if (!$cell instanceof AstNode) {
            return;
        }

        $cells = $row->children;
        $attrs = $cell->attrs;
        $attrs['rowspan'] = max(1, (int) ($attrs['rowspan'] ?? 1)) + 1;
        $cells[$cellIndex] = new AstNode($cell->type, $attrs, $cell->children);
        $rows[$rowIndex] = new AstNode($row->type, $row->attrs, $cells);
    }

    /**
     * @param array<int, array{rowIndex:int, cellIndex:int}> $verticalMerges
     */
    private function clearTableVerticalMergeColumns(array &$verticalMerges, int $startColumn, int $colspan): void
    {
        for ($column = $startColumn; $column < $startColumn + $colspan; $column++) {
            unset($verticalMerges[$column]);
        }
    }

    /**
     * @param list<AstNode> $blocks
     * @param list<array{paragraph:AstNode, definition:array{numId:string, level:int, ordered:bool, style:string, delimiter:string, start:int, format:string}}> $pending
     */
    private function appendListParagraphs(array &$blocks, array &$pending): void
    {
        if ($pending === []) {
            return;
        }

        $mutableBlocks = [];
        $stack = [];

        foreach ($pending as $item) {
            $definition = $item['definition'];
            $level = max(0, $definition['level']);
            foreach (array_keys($stack) as $stackLevel) {
                if ($stackLevel >= $level) {
                    unset($stack[$stackLevel]);
                }
            }

            if (!$this->appendNestedListParagraph($mutableBlocks, $stack, $item['paragraph'], $definition)) {
                $mutableBlocks[] = $this->newMutableList($definition);
                $listIndex = count($mutableBlocks) - 1;
                $list =& $mutableBlocks[$listIndex];
                $this->appendMutableListItem($list, $stack, $item['paragraph'], $definition);
                unset($list);
            }
        }

        foreach ($mutableBlocks as $mutableBlock) {
            $blocks[] = $this->astNodeFromMutableListBlock($mutableBlock);
        }

        $pending = [];
    }

    /**
     * @param list<array<string, mixed>> $mutableBlocks
     * @param array<int, array{key:string, lastItem:array<string, mixed>}> $stack
     * @param array{numId:string, level:int, ordered:bool, style:string, delimiter:string, start:int, format:string} $definition
     */
    private function appendNestedListParagraph(array &$mutableBlocks, array &$stack, AstNode $paragraph, array $definition): bool
    {
        $level = max(0, $definition['level']);
        $children =& $mutableBlocks;

        for ($parentLevel = $level - 1; $parentLevel >= 0; $parentLevel--) {
            if (isset($stack[$parentLevel])) {
                $children =& $stack[$parentLevel]['lastItem']['children'];
                break;
            }
        }

        if ($children === $mutableBlocks && $level > 0 && $mutableBlocks === []) {
            unset($children);
            return false;
        }

        $lastIndex = count($children) - 1;
        if ($lastIndex < 0 || !is_array($children[$lastIndex]) || ($children[$lastIndex]['key'] ?? null) !== $this->listDefinitionKey($definition)) {
            $children[] = $this->newMutableList($definition);
            $lastIndex = count($children) - 1;
        }

        $list =& $children[$lastIndex];
        $this->appendMutableListItem($list, $stack, $paragraph, $definition);
        unset($list, $children);

        return true;
    }

    /**
     * @return array{type:string, key:string, attrs:array<string, mixed>, children:list<array<string, mixed>>}
     */
    private function newMutableList(array $definition): array
    {
        $attrs = [
            'sourceFormat' => 'docx',
            'numId' => $definition['numId'],
            'level' => max(0, $definition['level']),
        ];
        if ($definition['ordered']) {
            $attrs['style'] = $definition['style'];
            $attrs['delimiter'] = $definition['delimiter'];
            $attrs['start'] = $definition['start'];
        } else {
            $attrs['format'] = $definition['format'];
        }

        return [
            'type' => $definition['ordered'] ? 'ordered_list' : 'bullet_list',
            'key' => $this->listDefinitionKey($definition),
            'attrs' => $attrs,
            'children' => [],
        ];
    }

    /**
     * @param array{type:string, key:string, attrs:array<string, mixed>, children:list<array<string, mixed>>} $list
     * @param array<int, array{key:string, lastItem:array<string, mixed>}> $stack
     * @param array{numId:string, level:int, ordered:bool, style:string, delimiter:string, start:int, format:string} $definition
     */
    private function appendMutableListItem(array &$list, array &$stack, AstNode $paragraph, array $definition): void
    {
        $itemIndex = count($list['children']);
        $list['children'][] = [
            'type' => 'list_item',
            'attrs' => ['level' => max(0, $definition['level'])],
            'children' => [$paragraph],
        ];

        $level = max(0, $definition['level']);
        $stack[$level] = [
            'key' => $this->listDefinitionKey($definition),
            'lastItem' => &$list['children'][$itemIndex],
        ];
    }

    /**
     * @param array{type:string, key:string, attrs:array<string, mixed>, children:list<mixed>} $node
     */
    private function astNodeFromMutableListBlock(array $node): AstNode
    {
        $children = [];
        foreach ($node['children'] as $child) {
            $children[] = is_array($child) ? $this->astNodeFromMutableListBlock($child) : $child;
        }

        return new AstNode($node['type'], $node['attrs'], $children);
    }

    /**
     * @param array{numId:string, level:int, ordered:bool, style:string, delimiter:string, start:int, format:string} $definition
     */
    private function listDefinitionKey(array $definition): string
    {
        return implode(':', [
            $definition['numId'],
            (string) max(0, $definition['level']),
            $definition['ordered'] ? 'ordered' : 'bullet',
            $definition['style'],
            $definition['delimiter'],
        ]);
    }

    /**
     * @return array<string, AstNode>
     */
    private function loadReferencedNotes(ZipPackage $package, OpcRelationshipGraph $graph, string $documentPart): array
    {
        $commentsExtended = $this->loadCommentsExtendedMetadata($package, $graph, $documentPart);

        return array_replace(
            $this->loadNotePart($package, $graph, $documentPart, self::REL_TYPE_FOOTNOTES, 'footnotes', 'footnote', 'footnote', 'DOCX footnotes XML'),
            $this->loadNotePart($package, $graph, $documentPart, self::REL_TYPE_ENDNOTES, 'endnotes', 'endnote', 'endnote', 'DOCX endnotes XML'),
            $this->loadNotePart($package, $graph, $documentPart, self::REL_TYPE_COMMENTS, 'comments', 'comment', 'comment', 'DOCX comments XML', $commentsExtended),
        );
    }

    /**
     * @param array<string, array{part:string, paraId:string, parentParaId?:string, resolved?:bool}> $commentsExtended
     * @return array<string, AstNode>
     */
    private function loadNotePart(
        ZipPackage $package,
        OpcRelationshipGraph $graph,
        string $documentPart,
        string $relationshipType,
        string $rootName,
        string $itemName,
        string $sourceType,
        string $label,
        array $commentsExtended = []
    ): array {
        $part = $graph->firstTargetOfType($relationshipType, $documentPart);
        if ($part === null) {
            return [];
        }

        $part = OpcPackagePath::stripQueryAndFragment($part);
        if (!$package->has($part)) {
            return [];
        }

        $relationships = $graph->relationshipsForSource($part);
        $dom = self::loadXml($package->read($part), $label);
        $root = $dom->documentElement;
        if (!$root instanceof \DOMElement || !$this->isWordElement($root, $rootName)) {
            return [];
        }

        $notes = [];
        foreach ($root->childNodes as $note) {
            if (!$note instanceof \DOMElement || !$this->isWordElement($note, $itemName)) {
                continue;
            }

            $id = $this->wordAttr($note, 'id');
            $type = strtolower((string) ($this->wordAttr($note, 'type') ?? ''));
            if ($id === null || $id === '' || str_starts_with($id, '-') || $this->specialNoteType($id, $type) !== null) {
                continue;
            }

            $attrs = [
                'id' => $id,
                'sourceType' => $sourceType,
            ];
            if ($sourceType === 'comment') {
                foreach (['author', 'initials', 'date'] as $metadataName) {
                    $metadata = $this->wordAttr($note, $metadataName);
                    if ($metadata !== null && $metadata !== '') {
                        $attrs[$metadataName] = $metadata;
                    }
                }

                foreach ($this->commentExtendedAttrs($note, $commentsExtended) as $key => $value) {
                    $attrs[$key] = $value;
                }
            }

            $notes[$sourceType . ':' . $id] = new AstNode('note', $attrs, $this->noteBlocks($note, $package, $relationships));
        }

        return $notes;
    }

    /**
     * @return array{count:int, footnoteCount:int, endnoteCount:int, items:list<array{sourceType:string, id:string, type:string, part:string, markers:list<string>, blockCount:int, text:string}>}|array{}
     */
    private function specialNoteImportReport(ZipPackage $package, OpcRelationshipGraph $graph, string $documentPart): array
    {
        $items = array_merge(
            $this->specialNoteItems($package, $graph, $documentPart, self::REL_TYPE_FOOTNOTES, 'footnotes', 'footnote', 'footnote', 'DOCX footnotes XML special notes'),
            $this->specialNoteItems($package, $graph, $documentPart, self::REL_TYPE_ENDNOTES, 'endnotes', 'endnote', 'endnote', 'DOCX endnotes XML special notes'),
        );
        if ($items === []) {
            return [];
        }

        $footnoteCount = 0;
        $endnoteCount = 0;
        foreach ($items as $item) {
            if ($item['sourceType'] === 'footnote') {
                $footnoteCount++;
            } elseif ($item['sourceType'] === 'endnote') {
                $endnoteCount++;
            }
        }

        return [
            'count' => count($items),
            'footnoteCount' => $footnoteCount,
            'endnoteCount' => $endnoteCount,
            'items' => $items,
        ];
    }

    /**
     * @return list<array{sourceType:string, id:string, type:string, part:string, markers:list<string>, blockCount:int, text:string}>
     */
    private function specialNoteItems(
        ZipPackage $package,
        OpcRelationshipGraph $graph,
        string $documentPart,
        string $relationshipType,
        string $rootName,
        string $itemName,
        string $sourceType,
        string $label
    ): array {
        $part = $graph->firstTargetOfType($relationshipType, $documentPart);
        if ($part === null) {
            return [];
        }

        $part = OpcPackagePath::stripQueryAndFragment($part);
        if (!$package->has($part)) {
            return [];
        }

        $relationships = $graph->relationshipsForSource($part);
        $dom = self::loadXml($package->read($part), $label);
        $root = $dom->documentElement;
        if (!$root instanceof \DOMElement || !$this->isWordElement($root, $rootName)) {
            return [];
        }

        $items = [];
        foreach ($root->childNodes as $note) {
            if (!$note instanceof \DOMElement || !$this->isWordElement($note, $itemName)) {
                continue;
            }

            $id = $this->wordAttr($note, 'id');
            if ($id === null || $id === '') {
                continue;
            }

            $type = $this->specialNoteType($id, (string) ($this->wordAttr($note, 'type') ?? ''));
            if ($type === null) {
                continue;
            }

            $blocks = $this->noteBlocks($note, $package, $relationships);
            $items[] = [
                'sourceType' => $sourceType,
                'id' => $id,
                'type' => $type,
                'part' => $part,
                'markers' => $this->specialNoteMarkers($note),
                'blockCount' => $this->noteSourceBlockCount($note),
                'text' => $this->plainBlockText($blocks),
            ];
        }

        return $items;
    }

    private function specialNoteType(string $id, string $type): ?string
    {
        $normalized = strtolower(trim($type));
        if ($normalized === 'separator') {
            return 'separator';
        }
        if ($normalized === 'continuationseparator') {
            return 'continuationSeparator';
        }
        if ($normalized === 'continuationnotice') {
            return 'continuationNotice';
        }

        return match ($id) {
            '-1' => 'separator',
            '-2' => 'continuationSeparator',
            '-3' => 'continuationNotice',
            default => null,
        };
    }

    /**
     * @return list<string>
     */
    private function specialNoteMarkers(\DOMElement $note): array
    {
        $markers = [];
        foreach ([
            'separator' => 'separator',
            'continuationSeparator' => 'continuationSeparator',
            'continuationNotice' => 'continuationNotice',
        ] as $elementName => $marker) {
            if ($this->firstDescendantElement($note, self::WORDPROCESSINGML_NS, $elementName) instanceof \DOMElement) {
                $markers[] = $marker;
            }
        }

        return $markers;
    }

    private function noteSourceBlockCount(\DOMElement $note): int
    {
        $count = 0;
        foreach ($note->childNodes as $child) {
            if (!$child instanceof \DOMElement) {
                continue;
            }
            if ($this->isWordElement($child, 'p') || $this->isWordElement($child, 'tbl')) {
                $count++;
            }
        }

        return $count;
    }

    /**
     * @param array<string, array{part:string, paraId:string, parentParaId?:string, resolved?:bool}> $commentsExtended
     * @return array<string, mixed>
     */
    private function commentExtendedAttrs(\DOMElement $comment, array $commentsExtended): array
    {
        $paraId = $this->commentParagraphId($comment);
        if ($paraId === null) {
            return [];
        }

        $attrs = [
            'commentParaId' => $paraId,
        ];
        $extended = $commentsExtended[$paraId] ?? null;
        if (!is_array($extended)) {
            return $attrs;
        }

        $attrs['commentsExtendedPart'] = $extended['part'];
        if (isset($extended['parentParaId']) && is_string($extended['parentParaId']) && $extended['parentParaId'] !== '') {
            $attrs['commentParentParaId'] = $extended['parentParaId'];
        }
        if (array_key_exists('resolved', $extended) && is_bool($extended['resolved'])) {
            $attrs['commentResolved'] = $extended['resolved'];
        }

        return $attrs;
    }

    private function commentParagraphId(\DOMElement $comment): ?string
    {
        foreach ($comment->childNodes as $child) {
            if (!$child instanceof \DOMElement || !$this->isWordElement($child, 'p')) {
                continue;
            }

            return $this->wordExtensionAttr($child, 'paraId');
        }

        return null;
    }

    /**
     * @return array<string, array{part:string, paraId:string, parentParaId?:string, resolved?:bool}>
     */
    private function loadCommentsExtendedMetadata(ZipPackage $package, OpcRelationshipGraph $graph, string $documentPart): array
    {
        $part = $graph->firstTargetOfType(self::REL_TYPE_COMMENTS_EXTENDED, $documentPart);
        if ($part === null) {
            return [];
        }

        $part = OpcPackagePath::stripQueryAndFragment($part);
        if (!$package->has($part)) {
            return [];
        }

        $dom = self::loadXml($package->read($part), 'DOCX commentsExtended XML');
        $root = $dom->documentElement;
        if (!$root instanceof \DOMElement || $root->localName !== 'commentsEx') {
            return [];
        }

        $items = [];
        foreach ($root->childNodes as $comment) {
            if (!$comment instanceof \DOMElement || $comment->localName !== 'commentEx') {
                continue;
            }

            $paraId = $this->wordExtensionAttr($comment, 'paraId');
            if ($paraId === null || $paraId === '') {
                continue;
            }

            $item = [
                'part' => $part,
                'paraId' => $paraId,
            ];

            $parentParaId = $this->wordExtensionAttr($comment, 'paraIdParent');
            if ($parentParaId !== null && $parentParaId !== '') {
                $item['parentParaId'] = $parentParaId;
            }

            $resolved = $this->onOffStringValue($this->wordExtensionAttr($comment, 'done'));
            if ($resolved !== null) {
                $item['resolved'] = $resolved;
            }

            $items[$paraId] = $item;
        }

        return $items;
    }

    /**
     * @return list<AstNode>
     */
    private function noteBlocks(\DOMElement $note, ZipPackage $package, ?OpcRelationships $relationships): array
    {
        return $this->blockContainerChildren($note, $package, $relationships, [], [], []);
    }

    /**
     * @return array<string, array{name:?string, basedOn:?string, headingLevel:?int, numPr:?array{numId:?string, level:?int}, paragraphMetadata:?array{classes:list<string>, attributes:array<string, string>}}>
     */
    private function loadStyles(ZipPackage $package, OpcRelationshipGraph $graph, string $documentPart): array
    {
        $stylesPart = $graph->firstTargetOfType(self::REL_TYPE_STYLES, $documentPart);
        if ($stylesPart === null) {
            return [];
        }

        $stylesPart = OpcPackagePath::stripQueryAndFragment($stylesPart);
        if (!$package->has($stylesPart)) {
            return [];
        }

        $dom = self::loadXml($package->read($stylesPart), 'DOCX styles XML');
        $root = $dom->documentElement;
        if (!$root instanceof \DOMElement || !$this->isWordElement($root, 'styles')) {
            return [];
        }

        $styles = [];
        $docDefaults = $this->stylesDocDefaults($root);
        if ($docDefaults !== []) {
            $styles[self::STYLE_DOC_DEFAULTS_KEY] = $docDefaults;
        }
        foreach ($root->getElementsByTagNameNS(self::WORDPROCESSINGML_NS, 'style') as $styleElement) {
            if (!$styleElement instanceof \DOMElement || $styleElement->parentNode !== $root) {
                continue;
            }

            $type = strtolower((string) ($this->wordAttr($styleElement, 'type') ?? 'paragraph'));
            if (!in_array($type, ['paragraph', 'character', 'table'], true)) {
                continue;
            }

            $styleId = $this->wordAttr($styleElement, 'styleId');
            if ($styleId === null || $styleId === '') {
                continue;
            }

            $name = $this->styleChildValue($styleElement, 'name');
            $basedOn = $this->styleChildValue($styleElement, 'basedOn');
            $properties = $this->firstChildElement($styleElement, self::WORDPROCESSINGML_NS, 'pPr');
            $runProperties = $this->firstChildElement($styleElement, self::WORDPROCESSINGML_NS, 'rPr');
            $tableProperties = $this->firstChildElement($styleElement, self::WORDPROCESSINGML_NS, 'tblPr');

            $styles[$styleId] = [
                'type' => $type,
                'name' => $name,
                'basedOn' => $basedOn,
                'headingLevel' => $type === 'paragraph' ? $this->styleElementHeadingLevel($styleElement) : null,
                'numPr' => $type === 'paragraph' && $properties instanceof \DOMElement ? $this->numberingProperties($properties) : null,
                'paragraphMetadata' => $type === 'paragraph' && $properties instanceof \DOMElement ? $this->paragraphPropertiesMetadataAttrs($properties) : null,
                'runProperties' => $runProperties instanceof \DOMElement ? $this->runPropertiesFromElement($runProperties) : null,
                'tableMetadata' => $type === 'table' ? $this->styleTableMetadataAttrs($tableProperties, $name, $basedOn, $styleElement) : null,
                'tableConditionalRegions' => $type === 'table' ? $this->styleTableConditionalRegions($styleElement) : [],
            ];
        }

        return $styles;
    }

    /**
     * @return array{classes?:list<string>, attributes?:array<string, string>, htmlAttributes?:array<string, string>}|null
     */
    private function styleTableMetadataAttrs(?\DOMElement $properties, ?string $name, ?string $basedOn, ?\DOMElement $styleElement = null): ?array
    {
        $attrs = $properties instanceof \DOMElement ? $this->tablePropertiesMetadataAttrs($properties) : null;

        $styleAttrs = null;
        $attributes = [];
        if ($name !== null && $name !== '') {
            $attributes['data-docx-table-style-name'] = $name;
        }
        if ($basedOn !== null && $basedOn !== '') {
            $attributes['data-docx-table-style-based-on'] = $basedOn;
        }
        if ($attributes !== []) {
            $styleAttrs = [
                'classes' => ['docx-table-style-definition'],
                'attributes' => $attributes,
            ];
        }

        $conditionalAttrs = $styleElement instanceof \DOMElement
            ? $this->styleTableConditionalRegionMetadataAttrs($styleElement)
            : null;

        return $this->mergeTableAttrs($this->mergeTableAttrs($attrs, $styleAttrs), $conditionalAttrs);
    }

    /**
     * @return array{classes?:list<string>, attributes?:array<string, string>}|null
     */
    private function styleTableConditionalRegionMetadataAttrs(\DOMElement $styleElement): ?array
    {
        $classes = [];
        $attributes = [];
        $types = [];
        $index = 0;

        foreach ($styleElement->childNodes as $child) {
            if (!$child instanceof \DOMElement || $child->namespaceURI !== self::WORDPROCESSINGML_NS || $child->localName !== 'tblStylePr') {
                continue;
            }

            $type = trim((string) ($this->wordAttr($child, 'type') ?? ''));
            if ($type === '') {
                continue;
            }

            $suffix = $this->tableStyleConditionalRegionClassSuffix($type);
            if ($suffix === null) {
                continue;
            }

            $index++;
            $types[] = $type;
            $classes[] = 'docx-table-style-conditional';
            $classes[] = 'docx-table-style-conditional-' . $suffix;
            $prefix = 'data-docx-table-style-region-' . $index;
            $attributes[$prefix . '-type'] = $type;
            $attributes[$prefix . '-type-label'] = $suffix;
            foreach ($this->tableStyleConditionalRegionAttributes($child) as $name => $value) {
                $attributes[$prefix . '-' . $name] = $value;
            }
        }

        if ($index === 0) {
            return null;
        }

        $attributes['data-docx-table-style-region-count'] = (string) $index;
        $attributes['data-docx-table-style-region-types'] = implode(' ', $types);

        return [
            'classes' => array_values(array_unique($classes)),
            'attributes' => $attributes,
        ];
    }

    private function tableStyleConditionalRegionClassSuffix(string $type): ?string
    {
        return self::TABLE_STYLE_CONDITIONAL_REGION_SUFFIXES[$type] ?? null;
    }

    /**
     * @return array<string, string>
     */
    private function tableStyleConditionalRegionAttributes(\DOMElement $region): array
    {
        $attributes = [];

        $tableProperties = $this->firstChildElement($region, self::WORDPROCESSINGML_NS, 'tblPr');
        if ($tableProperties instanceof \DOMElement) {
            foreach ($this->prefixedTableStyleRegionTableAttributes($tableProperties) as $name => $value) {
                $attributes[$name] = $value;
            }
        }

        $rowProperties = $this->firstChildElement($region, self::WORDPROCESSINGML_NS, 'trPr');
        if ($rowProperties instanceof \DOMElement) {
            foreach ([
                'tblHeader' => 'row-repeat-header',
                'cantSplit' => 'row-cant-split',
            ] as $localName => $target) {
                $property = $this->firstChildElement($rowProperties, self::WORDPROCESSINGML_NS, $localName);
                if ($property instanceof \DOMElement) {
                    $attributes[$target] = $this->onOffWordAttr($property, 'val', true) ? 'true' : 'false';
                }
            }
        }

        $cellProperties = $this->firstChildElement($region, self::WORDPROCESSINGML_NS, 'tcPr');
        if ($cellProperties instanceof \DOMElement) {
            $this->appendTableStyleRegionCellWidthAttributes($cellProperties, $attributes);
            $this->appendTableStyleRegionCellShadingAttributes($cellProperties, $attributes);
            $this->appendTableStyleRegionCellVerticalAlignAttributes($cellProperties, $attributes);
        }

        $paragraphProperties = $this->firstChildElement($region, self::WORDPROCESSINGML_NS, 'pPr');
        if ($paragraphProperties instanceof \DOMElement) {
            $justification = $this->firstChildElement($paragraphProperties, self::WORDPROCESSINGML_NS, 'jc');
            if ($justification instanceof \DOMElement) {
                $value = strtolower(trim((string) ($this->wordAttr($justification, 'val') ?? '')));
                if ($value !== '') {
                    $attributes['paragraph-align'] = $value;
                }
            }
        }

        $runProperties = $this->firstChildElement($region, self::WORDPROCESSINGML_NS, 'rPr');
        if ($runProperties instanceof \DOMElement) {
            foreach ([
                'b' => 'run-bold',
                'i' => 'run-italic',
                'smallCaps' => 'run-small-caps',
                'strike' => 'run-strike',
            ] as $localName => $target) {
                $property = $this->firstChildElement($runProperties, self::WORDPROCESSINGML_NS, $localName);
                if ($property instanceof \DOMElement) {
                    $attributes[$target] = $this->onOffWordAttr($property, 'val', true) ? 'true' : 'false';
                }
            }

            foreach ([
                'highlight' => 'run-highlight',
                'color' => 'run-color',
                'u' => 'run-underline',
            ] as $localName => $target) {
                $property = $this->firstChildElement($runProperties, self::WORDPROCESSINGML_NS, $localName);
                if (!$property instanceof \DOMElement) {
                    continue;
                }

                $value = trim((string) ($this->wordAttr($property, 'val') ?? ''));
                if ($value !== '') {
                    $attributes[$target] = $value;
                }
            }
        }

        return $attributes;
    }

    /**
     * @return array<string, string>
     */
    private function prefixedTableStyleRegionTableAttributes(\DOMElement $tableProperties): array
    {
        $metadata = $this->tablePropertiesMetadataAttrs($tableProperties);
        if ($metadata === null) {
            return [];
        }

        $attributes = [];
        $metadataAttributes = is_array($metadata['attributes'] ?? null) ? $metadata['attributes'] : [];
        foreach ($metadataAttributes as $name => $value) {
            if (!str_starts_with($name, 'data-docx-table-')) {
                continue;
            }

            $attributes['table-' . substr($name, strlen('data-docx-table-'))] = $value;
        }

        $htmlAttributes = is_array($metadata['htmlAttributes'] ?? null) ? $metadata['htmlAttributes'] : [];
        $style = trim((string) ($htmlAttributes['style'] ?? ''));
        if ($style !== '') {
            $attributes['table-css-style'] = $style;
        }

        return $attributes;
    }

    /**
     * @param array<string, string> $attributes
     */
    private function appendTableStyleRegionCellWidthAttributes(\DOMElement $cellProperties, array &$attributes): void
    {
        $width = $this->firstChildElement($cellProperties, self::WORDPROCESSINGML_NS, 'tcW');
        if (!$width instanceof \DOMElement) {
            return;
        }

        $type = strtolower(trim((string) ($this->wordAttr($width, 'type') ?? '')));
        if (!in_array($type, ['dxa', 'pct', 'auto'], true)) {
            return;
        }

        $attributes['cell-width-type'] = $type;
        $value = trim((string) ($this->wordAttr($width, 'w') ?? ''));
        if ($value === '') {
            return;
        }

        $attributes['cell-width-value'] = $value;
        if (preg_match('/^\d+(?:\.\d+)?$/D', $value) !== 1) {
            return;
        }

        $numericValue = (float) $value;
        if ($numericValue > 0.0 && $type === 'dxa') {
            $attributes['cell-width-points'] = $this->formatOpenXmlCssNumber($numericValue / 20.0);
        } elseif ($numericValue > 0.0 && $type === 'pct') {
            $attributes['cell-width-percent'] = $this->formatOpenXmlCssNumber($numericValue / 50.0);
        }
    }

    /**
     * @param array<string, string> $attributes
     */
    private function appendTableStyleRegionCellShadingAttributes(\DOMElement $cellProperties, array &$attributes): void
    {
        $shading = $this->firstChildElement($cellProperties, self::WORDPROCESSINGML_NS, 'shd');
        if (!$shading instanceof \DOMElement) {
            return;
        }

        foreach ($this->tableCellShadingSourceValues($shading) as $name => $value) {
            $attributes['cell-shading-' . $name] = $value;
        }
    }

    /**
     * @param array<string, string> $attributes
     */
    private function appendTableStyleRegionCellVerticalAlignAttributes(\DOMElement $cellProperties, array &$attributes): void
    {
        $alignment = $this->firstChildElement($cellProperties, self::WORDPROCESSINGML_NS, 'vAlign');
        if (!$alignment instanceof \DOMElement) {
            return;
        }

        $value = strtolower(trim((string) ($this->wordAttr($alignment, 'val') ?? '')));
        if (in_array($value, ['top', 'center', 'bottom'], true)) {
            $attributes['cell-vertical-align'] = $value;
        }
    }

    /**
     * @return array{paragraphMetadata?:array{classes:list<string>, attributes:array<string, string>}, runProperties?:array<string, mixed>}
     */
    private function stylesDocDefaults(\DOMElement $styles): array
    {
        $docDefaults = $this->firstChildElement($styles, self::WORDPROCESSINGML_NS, 'docDefaults');
        if (!$docDefaults instanceof \DOMElement) {
            return [];
        }

        $defaults = [];
        $paragraphDefault = $this->firstChildElement($docDefaults, self::WORDPROCESSINGML_NS, 'pPrDefault');
        $paragraphProperties = $paragraphDefault instanceof \DOMElement
            ? $this->firstChildElement($paragraphDefault, self::WORDPROCESSINGML_NS, 'pPr')
            : null;
        if ($paragraphProperties instanceof \DOMElement) {
            $paragraphMetadata = $this->paragraphPropertiesMetadataAttrs($paragraphProperties);
            if ($paragraphMetadata !== null) {
                $defaults['paragraphMetadata'] = $paragraphMetadata;
            }
        }

        $runDefault = $this->firstChildElement($docDefaults, self::WORDPROCESSINGML_NS, 'rPrDefault');
        $runProperties = $runDefault instanceof \DOMElement
            ? $this->firstChildElement($runDefault, self::WORDPROCESSINGML_NS, 'rPr')
            : null;
        if ($runProperties instanceof \DOMElement) {
            $properties = $this->runPropertiesFromElement($runProperties);
            if ($properties !== null) {
                $defaults['runProperties'] = $properties;
            }
        }

        return $defaults;
    }

    /**
     * @return array<string, mixed>
     */
    private function readTheme(ZipPackage $package, OpcRelationshipGraph $graph, string $documentPart): array
    {
        $documentRelationships = $graph->relationshipsForSource($documentPart);
        if (!$documentRelationships instanceof OpcRelationships) {
            return [];
        }

        $relationship = $documentRelationships->firstOfType(self::REL_TYPE_THEME);
        if (!$relationship instanceof OpcRelationship) {
            return [];
        }

        $relationshipSummary = $this->internalSupportPartRelationshipSummary(
            $relationship,
            $package,
            $documentRelationships,
        );

        $theme = [
            'part' => $relationshipSummary['targetPart'],
            'contentType' => $relationshipSummary['contentType'],
            'relationship' => $relationshipSummary,
        ];

        if ($relationshipSummary['exists'] !== true || !is_string($relationshipSummary['targetPart'])) {
            $theme['issues'] = $relationshipSummary['issues'];

            return $theme;
        }

        $dom = self::loadXml($package->read($relationshipSummary['targetPart']), 'DOCX theme XML');
        $root = $dom->documentElement;
        if (!$root instanceof \DOMElement || $root->namespaceURI !== self::DRAWINGML_MAIN_NS || $root->localName !== 'theme') {
            $theme['issues'] = ['invalid-theme-root'];

            return $theme;
        }

        $name = trim($root->getAttribute('name'));
        if ($name !== '') {
            $theme['name'] = $name;
        }

        $fonts = $this->themeFontScheme($root);
        if ($fonts !== []) {
            $theme['fonts'] = $fonts;
        }

        $colors = $this->themeColorScheme($root);
        if ($colors !== []) {
            $theme['colors'] = $colors;
        }

        return $theme;
    }

    /**
     * @return array<string, string>
     */
    private function themeFontScheme(\DOMElement $theme): array
    {
        $themeElements = $this->firstChildElement($theme, self::DRAWINGML_MAIN_NS, 'themeElements');
        if (!$themeElements instanceof \DOMElement) {
            return [];
        }

        $fontScheme = $this->firstChildElement($themeElements, self::DRAWINGML_MAIN_NS, 'fontScheme');
        if (!$fontScheme instanceof \DOMElement) {
            return [];
        }

        $fonts = [];
        $schemeName = trim($fontScheme->getAttribute('name'));
        if ($schemeName !== '') {
            $fonts['schemeName'] = $schemeName;
        }

        foreach ([
            'majorFont' => 'major',
            'minorFont' => 'minor',
        ] as $fontElementName => $prefix) {
            $fontElement = $this->firstChildElement($fontScheme, self::DRAWINGML_MAIN_NS, $fontElementName);
            if (!$fontElement instanceof \DOMElement) {
                continue;
            }

            foreach ([
                'latin' => 'Latin',
                'ea' => 'EastAsia',
                'cs' => 'ComplexScript',
            ] as $source => $target) {
                $sourceElement = $this->firstChildElement($fontElement, self::DRAWINGML_MAIN_NS, $source);
                if (!$sourceElement instanceof \DOMElement) {
                    continue;
                }

                $typeface = trim($sourceElement->getAttribute('typeface'));
                if ($typeface !== '') {
                    $fonts[$prefix . $target] = $typeface;
                }
            }
        }

        return $fonts;
    }

    /**
     * @return array{schemeName?:string, count:int, items:list<array{name:string, kind:string, value:?string, rgb:?string}>, byName:array<string, string>}
     */
    private function themeColorScheme(\DOMElement $theme): array
    {
        $themeElements = $this->firstChildElement($theme, self::DRAWINGML_MAIN_NS, 'themeElements');
        if (!$themeElements instanceof \DOMElement) {
            return [];
        }

        $colorScheme = $this->firstChildElement($themeElements, self::DRAWINGML_MAIN_NS, 'clrScheme');
        if (!$colorScheme instanceof \DOMElement) {
            return [];
        }

        $items = [];
        $byName = [];
        foreach ($colorScheme->childNodes as $child) {
            if (!$child instanceof \DOMElement || $child->namespaceURI !== self::DRAWINGML_MAIN_NS) {
                continue;
            }

            $name = $child->localName;
            if (!in_array($name, ['dk1', 'lt1', 'dk2', 'lt2', 'accent1', 'accent2', 'accent3', 'accent4', 'accent5', 'accent6', 'hlink', 'folHlink'], true)) {
                continue;
            }

            $color = $this->themeColorSchemeEntry($child);
            if ($color === null) {
                continue;
            }

            $item = ['name' => $name] + $color;
            $items[] = $item;
            if (is_string($item['rgb']) && $item['rgb'] !== '') {
                $byName[$name] = $item['rgb'];
                foreach ($this->themeColorAliases($name) as $alias) {
                    $byName[$alias] = $item['rgb'];
                }
            }
        }

        if ($items === []) {
            return [];
        }

        $scheme = [
            'count' => count($items),
            'items' => $items,
            'byName' => $byName,
        ];
        $schemeName = trim($colorScheme->getAttribute('name'));
        if ($schemeName !== '') {
            $scheme = ['schemeName' => $schemeName] + $scheme;
        }

        return $scheme;
    }

    /**
     * @return array{kind:string, value:?string, rgb:?string}|null
     */
    private function themeColorSchemeEntry(\DOMElement $container): ?array
    {
        foreach ($container->childNodes as $child) {
            if (!$child instanceof \DOMElement || $child->namespaceURI !== self::DRAWINGML_MAIN_NS) {
                continue;
            }

            if ($child->localName === 'srgbClr') {
                $rgb = $this->normalizedRgb($child->getAttribute('val'));
                if ($rgb === null) {
                    continue;
                }

                return [
                    'kind' => 'srgb',
                    'value' => $rgb,
                    'rgb' => $rgb,
                ];
            }

            if ($child->localName === 'sysClr') {
                $value = trim($child->getAttribute('val'));
                $rgb = $this->normalizedRgb($child->getAttribute('lastClr'));

                return [
                    'kind' => 'system',
                    'value' => $value !== '' ? $value : null,
                    'rgb' => $rgb,
                ];
            }

            if ($child->localName === 'prstClr') {
                $value = trim($child->getAttribute('val'));
                if ($value === '') {
                    continue;
                }

                return [
                    'kind' => 'preset',
                    'value' => $value,
                    'rgb' => null,
                ];
            }

            if ($child->localName === 'schemeClr') {
                $value = trim($child->getAttribute('val'));
                if ($value === '') {
                    continue;
                }

                return [
                    'kind' => 'scheme',
                    'value' => $value,
                    'rgb' => null,
                ];
            }
        }

        return null;
    }

    private function normalizedRgb(string $value): ?string
    {
        $value = strtoupper(trim($value));

        return preg_match('/^[0-9A-F]{6}$/D', $value) === 1 ? $value : null;
    }

    /**
     * @return list<string>
     */
    private function themeColorAliases(string $name): array
    {
        return match ($name) {
            'dk1' => ['dark1', 'text1'],
            'lt1' => ['light1', 'background1'],
            'dk2' => ['dark2', 'text2'],
            'lt2' => ['light2', 'background2'],
            'hlink' => ['hyperlink'],
            'folHlink' => ['followedHyperlink', 'followed-hyperlink'],
            default => [],
        };
    }

    /**
     * @return array<string, array<int, array{ordered:bool, style:string, delimiter:string, start:int, format:string, paragraphStyleId?:string}>>
     */
    private function loadNumbering(ZipPackage $package, OpcRelationshipGraph $graph, string $documentPart): array
    {
        $numberingPart = $graph->firstTargetOfType(self::REL_TYPE_NUMBERING, $documentPart);
        if ($numberingPart === null) {
            return [];
        }

        $numberingPart = OpcPackagePath::stripQueryAndFragment($numberingPart);
        if (!$package->has($numberingPart)) {
            return [];
        }

        $dom = self::loadXml($package->read($numberingPart), 'DOCX numbering XML');
        $root = $dom->documentElement;
        if (!$root instanceof \DOMElement || !$this->isWordElement($root, 'numbering')) {
            return [];
        }

        $abstractLevels = [];
        foreach ($root->childNodes as $child) {
            if (!$child instanceof \DOMElement || !$this->isWordElement($child, 'abstractNum')) {
                continue;
            }

            $abstractNumId = $this->wordAttr($child, 'abstractNumId');
            if ($abstractNumId === null || $abstractNumId === '') {
                continue;
            }

            $levels = [];
            foreach ($child->childNodes as $levelElement) {
                if (!$levelElement instanceof \DOMElement || !$this->isWordElement($levelElement, 'lvl')) {
                    continue;
                }

                $level = $this->intWordAttr($levelElement, 'ilvl', 0);
                $levels[$level] = $this->numberingLevelDefinition($levelElement);
            }

            $abstractLevels[$abstractNumId] = $levels;
        }

        $numbering = [];
        foreach ($root->childNodes as $child) {
            if (!$child instanceof \DOMElement || !$this->isWordElement($child, 'num')) {
                continue;
            }

            $numId = $this->wordAttr($child, 'numId');
            if ($numId === null || $numId === '') {
                continue;
            }

            $abstractNumIdElement = $this->firstChildElement($child, self::WORDPROCESSINGML_NS, 'abstractNumId');
            $abstractNumId = $abstractNumIdElement instanceof \DOMElement ? $this->wordAttr($abstractNumIdElement, 'val') : null;
            $levels = $abstractNumId !== null ? ($abstractLevels[$abstractNumId] ?? []) : [];

            foreach ($child->childNodes as $override) {
                if (!$override instanceof \DOMElement || !$this->isWordElement($override, 'lvlOverride')) {
                    continue;
                }

                $level = $this->intWordAttr($override, 'ilvl', 0);
                $overrideLevel = $this->firstChildElement($override, self::WORDPROCESSINGML_NS, 'lvl');
                if ($overrideLevel instanceof \DOMElement) {
                    $levels[$level] = $this->numberingLevelDefinition($overrideLevel);
                }

                $startOverride = $this->firstChildElement($override, self::WORDPROCESSINGML_NS, 'startOverride');
                if ($startOverride instanceof \DOMElement) {
                    $existing = $levels[$level] ?? [
                        'ordered' => true,
                        'style' => 'decimal',
                        'delimiter' => 'period',
                        'start' => 1,
                        'format' => 'decimal',
                    ];
                    $existing['start'] = max(0, $this->intWordAttr($startOverride, 'val', $existing['start']));
                    $levels[$level] = $existing;
                }
            }

            $numbering[$numId] = $levels;
        }

        return $numbering;
    }

    /**
     * @param array<string, array{name:?string, basedOn:?string, headingLevel:?int, numPr:?array{numId:?string, level:?int}}> $styles
     * @param array<string, array<int, array{ordered:bool, style:string, delimiter:string, start:int, format:string, paragraphStyleId?:string}>> $numbering
     * @return array{numId:string, level:int, ordered:bool, style:string, delimiter:string, start:int, format:string}|null
     */
    private function listDefinitionForParagraph(\DOMElement $paragraph, array $styles, array $numbering): ?array
    {
        $numPr = $this->paragraphNumberingProperties($paragraph, $styles, $numbering);
        if ($numPr === null || ($numPr['numId'] ?? null) === null || $numPr['numId'] === '' || $numPr['numId'] === '0') {
            return null;
        }

        $numId = $numPr['numId'];
        $level = max(0, (int) ($numPr['level'] ?? 0));
        $levelDefinition = $numbering[$numId][$level] ?? $numbering[$numId][0] ?? [
            'ordered' => true,
            'style' => 'decimal',
            'delimiter' => 'period',
            'start' => 1,
            'format' => 'decimal',
        ];

        if ($levelDefinition['format'] === 'none') {
            return null;
        }

        return [
            'numId' => $numId,
            'level' => $level,
            'ordered' => $levelDefinition['ordered'],
            'style' => $levelDefinition['style'],
            'delimiter' => $levelDefinition['delimiter'],
            'start' => $levelDefinition['start'],
            'format' => $levelDefinition['format'],
        ];
    }

    /**
     * @return array<string, string>
     */
    private function readCoreProperties(ZipPackage $package, OpcRelationshipGraph $graph): array
    {
        $corePart = $graph->firstTargetOfType(self::REL_TYPE_CORE_PROPERTIES);
        if ($corePart === null) {
            return [];
        }

        $corePart = OpcPackagePath::stripQueryAndFragment($corePart);
        if (!$package->has($corePart)) {
            return [];
        }

        $dom = self::loadXml($package->read($corePart), 'DOCX core properties XML');
        $root = $dom->documentElement;
        if (!$root instanceof \DOMElement || $root->namespaceURI !== self::CORE_PROPERTIES_NS) {
            return [];
        }

        $properties = [];
        $map = [
            'title' => [self::DC_NS, 'title'],
            'creator' => [self::DC_NS, 'creator'],
            'description' => [self::DC_NS, 'description'],
            'subject' => [self::DC_NS, 'subject'],
            'created' => [self::DCTERMS_NS, 'created'],
            'modified' => [self::DCTERMS_NS, 'modified'],
            'lastModifiedBy' => [self::CORE_PROPERTIES_NS, 'lastModifiedBy'],
            'revision' => [self::CORE_PROPERTIES_NS, 'revision'],
        ];

        foreach ($map as $name => [$namespace, $localName]) {
            $node = $this->firstChildElement($root, $namespace, $localName);
            if ($node instanceof \DOMElement && trim($node->textContent) !== '') {
                $properties[$name] = trim($node->textContent);
            }
        }

        return $properties;
    }

    /**
     * @return array<string, mixed>
     */
    private function readExtendedProperties(ZipPackage $package, OpcRelationshipGraph $graph): array
    {
        $packageRelationships = $graph->relationshipsForSource('/');
        if (!$packageRelationships instanceof OpcRelationships) {
            return [];
        }

        $relationship = $packageRelationships->firstOfType(self::REL_TYPE_EXTENDED_PROPERTIES);
        if (!$relationship instanceof OpcRelationship) {
            return [];
        }

        $relationshipSummary = $this->internalSupportPartRelationshipSummary(
            $relationship,
            $package,
            $packageRelationships,
        );

        $properties = [
            'part' => $relationshipSummary['targetPart'],
            'contentType' => $relationshipSummary['contentType'],
            'relationship' => $relationshipSummary,
        ];

        if ($relationshipSummary['issues'] !== []) {
            $properties['issues'] = $relationshipSummary['issues'];
        }

        if ($relationshipSummary['exists'] !== true || !is_string($relationshipSummary['targetPart'])) {
            return $properties;
        }

        $dom = self::loadXml($package->read($relationshipSummary['targetPart']), 'DOCX extended properties XML');
        $root = $dom->documentElement;
        if (!$root instanceof \DOMElement || $root->namespaceURI !== self::EXTENDED_PROPERTIES_NS || $root->localName !== 'Properties') {
            $properties['issues'] = array_values(array_unique(array_merge($properties['issues'] ?? [], ['invalid-extended-properties-root'])));

            return $properties;
        }

        foreach ([
            'template' => 'Template',
            'manager' => 'Manager',
            'company' => 'Company',
            'application' => 'Application',
            'appVersion' => 'AppVersion',
            'hyperlinkBase' => 'HyperlinkBase',
        ] as $target => $source) {
            $value = $this->docPropsChildText($root, self::EXTENDED_PROPERTIES_NS, $source);
            if ($value !== null) {
                $properties[$target] = $value;
            }
        }

        foreach ([
            'pages' => 'Pages',
            'words' => 'Words',
            'characters' => 'Characters',
            'charactersWithSpaces' => 'CharactersWithSpaces',
            'lines' => 'Lines',
            'paragraphs' => 'Paragraphs',
            'docSecurity' => 'DocSecurity',
        ] as $target => $source) {
            $value = $this->docPropsChildText($root, self::EXTENDED_PROPERTIES_NS, $source);
            if ($value !== null && preg_match('/^-?\d+$/', $value) === 1) {
                $properties[$target] = (int) $value;
            }
        }

        foreach ([
            'scaleCrop' => 'ScaleCrop',
            'linksUpToDate' => 'LinksUpToDate',
            'sharedDoc' => 'SharedDoc',
            'hyperlinksChanged' => 'HyperlinksChanged',
        ] as $target => $source) {
            $value = $this->docPropsChildText($root, self::EXTENDED_PROPERTIES_NS, $source);
            $bool = $this->docPropsBoolValue($value);
            if ($bool !== null) {
                $properties[$target] = $bool;
            }
        }

        $headingPairs = $this->docPropsHeadingPairs($root);
        if ($headingPairs !== []) {
            $properties['headingPairs'] = $headingPairs;
        }

        $titlesOfParts = $this->docPropsVectorStrings($root, 'TitlesOfParts');
        if ($titlesOfParts !== []) {
            $properties['titlesOfParts'] = $titlesOfParts;
        }

        return $properties;
    }

    /**
     * @return array<string, mixed>
     */
    private function readCustomProperties(ZipPackage $package, OpcRelationshipGraph $graph): array
    {
        $packageRelationships = $graph->relationshipsForSource('/');
        if (!$packageRelationships instanceof OpcRelationships) {
            return [];
        }

        $relationship = $packageRelationships->firstOfType(self::REL_TYPE_CUSTOM_PROPERTIES);
        if (!$relationship instanceof OpcRelationship) {
            return [];
        }

        $relationshipSummary = $this->internalSupportPartRelationshipSummary(
            $relationship,
            $package,
            $packageRelationships,
        );

        $properties = [
            'part' => $relationshipSummary['targetPart'],
            'contentType' => $relationshipSummary['contentType'],
            'relationship' => $relationshipSummary,
        ];

        if ($relationshipSummary['issues'] !== []) {
            $properties['issues'] = $relationshipSummary['issues'];
        }

        if ($relationshipSummary['exists'] !== true || !is_string($relationshipSummary['targetPart'])) {
            return $properties;
        }

        $dom = self::loadXml($package->read($relationshipSummary['targetPart']), 'DOCX custom properties XML');
        $root = $dom->documentElement;
        if (!$root instanceof \DOMElement || $root->namespaceURI !== self::CUSTOM_PROPERTIES_NS || $root->localName !== 'Properties') {
            $properties['issues'] = array_values(array_unique(array_merge($properties['issues'] ?? [], ['invalid-custom-properties-root'])));

            return $properties;
        }

        $items = [];
        $byName = [];
        $seenNames = [];
        $duplicateNames = [];
        foreach ($root->childNodes as $child) {
            if (!$child instanceof \DOMElement || $child->namespaceURI !== self::CUSTOM_PROPERTIES_NS || $child->localName !== 'property') {
                continue;
            }

            $name = trim($child->getAttribute('name'));
            if ($name === '') {
                continue;
            }

            $valueElement = null;
            foreach ($child->childNodes as $valueChild) {
                if ($valueChild instanceof \DOMElement) {
                    $valueElement = $valueChild;
                    break;
                }
            }

            $typedValue = $valueElement instanceof \DOMElement
                ? $this->docPropsTypedValue($valueElement)
                : ['type' => 'missing', 'value' => null];
            $duplicate = isset($seenNames[$name]);
            if (!$duplicate) {
                $byName[$name] = $typedValue['value'];
            } elseif (!in_array($name, $duplicateNames, true)) {
                $duplicateNames[] = $name;
            }
            $seenNames[$name] = true;

            $pid = trim($child->getAttribute('pid'));
            $item = [
                'name' => $name,
                'fmtid' => trim($child->getAttribute('fmtid')) ?: null,
                'pid' => preg_match('/^-?\d+$/', $pid) === 1 ? (int) $pid : null,
                'valueType' => $typedValue['type'],
                'value' => $typedValue['value'],
                'duplicate' => $duplicate,
            ];
            $items[] = $item;
        }

        $properties['count'] = count($items);
        $properties['duplicateNameCount'] = count($duplicateNames);
        $properties['duplicateNames'] = $duplicateNames;
        $properties['byName'] = $byName;
        $properties['items'] = $items;

        return $properties;
    }

    /**
     * @return array{count:int, boundStoreItemCount:int, issueCount:int, propertyIssueCount:int, propertyIssueCodes:list<string>, items:list<array<string, mixed>>, byStoreItemID:array<string, array<string, mixed>>, lookupByStoreItemID:array<string, array<string, mixed>>}
     */
    private function readCustomXmlStore(ZipPackage $package, OpcRelationshipGraph $graph): array
    {
        $store = [
            'count' => 0,
            'boundStoreItemCount' => 0,
            'issueCount' => 0,
            'propertyIssueCount' => 0,
            'propertyIssueCodes' => [],
            'items' => [],
            'byStoreItemID' => [],
            'lookupByStoreItemID' => [],
        ];

        $packageRelationships = $graph->relationshipsForSource('/');
        if (!$packageRelationships instanceof OpcRelationships) {
            return $store;
        }

        $items = [];
        foreach ($packageRelationships->ofType(self::REL_TYPE_CUSTOM_XML) as $relationship) {
            $items[] = $this->customXmlStoreItem($relationship, $package, $graph, $packageRelationships);
        }

        $seenStoreItemIds = [];
        foreach ($items as &$item) {
            $storeItemId = $item['storeItemID'] ?? null;
            if (!is_string($storeItemId) || $storeItemId === '') {
                continue;
            }

            $lookupKey = $this->customXmlStoreLookupKey($storeItemId);
            if (isset($seenStoreItemIds[$lookupKey])) {
                $item['issues'][] = 'duplicate-store-item-id';
                $item['issues'] = array_values(array_unique($item['issues']));
                continue;
            }

            $seenStoreItemIds[$lookupKey] = true;
        }
        unset($item);

        $byStoreItemId = [];
        $lookupByStoreItemId = [];
        foreach ($items as $item) {
            $storeItemId = $item['storeItemID'] ?? null;
            if (!is_string($storeItemId) || $storeItemId === '' || in_array('duplicate-store-item-id', $item['issues'], true)) {
                continue;
            }

            $byStoreItemId[$storeItemId] = $item;
            $lookupByStoreItemId[$this->customXmlStoreLookupKey($storeItemId)] = $item;
        }

        return [
            'count' => count($items),
            'boundStoreItemCount' => count($byStoreItemId),
            'issueCount' => $this->customXmlStoreIssueCount($items),
            'propertyIssueCount' => $this->customXmlStorePropertyIssueCount($items),
            'propertyIssueCodes' => $this->customXmlStorePropertyIssueCodes($items),
            'items' => $items,
            'byStoreItemID' => $byStoreItemId,
            'lookupByStoreItemID' => $lookupByStoreItemId,
        ];
    }

    /**
     * @param list<array<string, mixed>> $items
     */
    private function customXmlStoreIssueCount(array $items): int
    {
        $count = 0;
        foreach ($items as $item) {
            $issues = $item['issues'] ?? [];
            $propertyIssues = $item['propertiesIssues'] ?? [];
            if ((is_array($issues) && $issues !== []) || (is_array($propertyIssues) && $propertyIssues !== [])) {
                $count++;
            }
        }

        return $count;
    }

    /**
     * @param list<array<string, mixed>> $items
     */
    private function customXmlStorePropertyIssueCount(array $items): int
    {
        $count = 0;
        foreach ($items as $item) {
            $propertyIssues = $item['propertiesIssues'] ?? [];
            if (is_array($propertyIssues) && $propertyIssues !== []) {
                $count++;
            }
        }

        return $count;
    }

    /**
     * @param list<array<string, mixed>> $items
     * @return list<string>
     */
    private function customXmlStorePropertyIssueCodes(array $items): array
    {
        $codes = [];
        foreach ($items as $item) {
            $propertyIssues = $item['propertiesIssues'] ?? [];
            if (!is_array($propertyIssues)) {
                continue;
            }

            foreach ($propertyIssues as $issue) {
                if (is_string($issue) && $issue !== '') {
                    $codes[$issue] = true;
                }
            }
        }

        return array_keys($codes);
    }

    /**
     * @return array<string, mixed>
     */
    private function customXmlStoreItem(
        OpcRelationship $relationship,
        ZipPackage $package,
        OpcRelationshipGraph $graph,
        OpcRelationships $packageRelationships
    ): array {
        $relationshipSummary = $this->internalSupportPartRelationshipSummary(
            $relationship,
            $package,
            $packageRelationships,
        );

        $item = [
            'id' => $relationship->id,
            'relationshipType' => $relationship->type,
            'target' => $relationshipSummary['target'],
            'targetPart' => $relationshipSummary['targetPart'],
            'contentType' => $relationshipSummary['contentType'],
            'external' => $relationshipSummary['external'],
            'exists' => $relationshipSummary['exists'],
            'bytes' => null,
            'rootName' => null,
            'rootNamespace' => null,
            'rootLocalName' => null,
            'textPreview' => null,
            'textLength' => null,
            'storeItemID' => null,
            'schemaRefCount' => 0,
            'schemaRefs' => [],
            'propertiesPart' => null,
            'propertiesContentType' => null,
            'propertiesRelationship' => null,
            'propertiesIssues' => [],
            'issues' => $relationshipSummary['issues'],
        ];

        if (
            is_string($relationshipSummary['contentType'])
            && !$this->contentTypeBaseEquals($relationshipSummary['contentType'], 'application/xml')
            && !$this->contentTypeBaseEquals($relationshipSummary['contentType'], 'text/xml')
        ) {
            $item['issues'][] = 'unexpected-content-type';
        }

        if ($relationshipSummary['exists'] !== true || !is_string($relationshipSummary['targetPart'])) {
            $item['issues'] = array_values(array_unique($item['issues']));

            return $item;
        }

        $bytes = $package->read($relationshipSummary['targetPart']);
        $item['bytes'] = strlen($bytes);

        try {
            $dom = self::loadXml($bytes, 'DOCX custom XML data store item');
            $root = $dom->documentElement;
        } catch (\InvalidArgumentException) {
            $root = null;
            $item['issues'][] = 'invalid-custom-xml';
        }

        if ($root instanceof \DOMElement) {
            $text = trim(preg_replace('/[ \t\r\n\f]+/u', ' ', $root->textContent) ?? $root->textContent);
            $item['rootName'] = $this->qualifiedDomName($root);
            $item['rootNamespace'] = $root->namespaceURI;
            $item['rootLocalName'] = $root->localName;
            $item['textPreview'] = $text === '' ? null : $this->boundedTextPreview($text);
            $item['textLength'] = strlen($text);
        }

        $properties = $this->customXmlStorePropertiesForItem($relationshipSummary['targetPart'], $package, $graph);
        if ($properties !== []) {
            $item['propertiesPart'] = $properties['part'];
            $item['propertiesContentType'] = $properties['contentType'];
            $item['propertiesRelationship'] = $properties['relationship'];
            $item['propertiesIssues'] = $properties['issues'];
            $item['storeItemID'] = $properties['storeItemID'];
            $item['schemaRefs'] = $properties['schemaRefs'];
            $item['schemaRefCount'] = count($properties['schemaRefs']);
        } else {
            $item['issues'][] = 'missing-custom-xml-properties-relationship';
        }

        $item['issues'] = array_values(array_unique($item['issues']));

        return $item;
    }

    /**
     * @return array{part:?string, contentType:?string, relationship:array<string, mixed>, storeItemID:?string, schemaRefs:list<string>, issues:list<string>}|array{}
     */
    private function customXmlStorePropertiesForItem(
        string $itemPart,
        ZipPackage $package,
        OpcRelationshipGraph $graph
    ): array {
        $relationships = $graph->relationshipsForSource($itemPart);
        if (!$relationships instanceof OpcRelationships) {
            return [];
        }

        $relationship = $relationships->firstOfType(self::REL_TYPE_CUSTOM_XML_PROPS);
        if (!$relationship instanceof OpcRelationship) {
            return [];
        }

        $relationshipSummary = $this->internalSupportPartRelationshipSummary(
            $relationship,
            $package,
            $relationships,
        );
        $issues = $relationshipSummary['issues'];

        if (
            is_string($relationshipSummary['contentType'])
            && !$this->contentTypeBaseEquals($relationshipSummary['contentType'], 'application/vnd.openxmlformats-officedocument.customXmlProperties+xml')
        ) {
            $issues[] = 'unexpected-properties-content-type';
        }

        $properties = [
            'part' => $relationshipSummary['targetPart'],
            'contentType' => $relationshipSummary['contentType'],
            'relationship' => $relationshipSummary,
            'storeItemID' => null,
            'schemaRefs' => [],
            'issues' => [],
        ];

        if ($relationshipSummary['exists'] !== true || !is_string($relationshipSummary['targetPart'])) {
            $properties['issues'] = array_values(array_unique($issues));

            return $properties;
        }

        $parsed = $this->customXmlStoreProperties($package->read($relationshipSummary['targetPart']));
        $properties['storeItemID'] = $parsed['storeItemID'];
        $properties['schemaRefs'] = $parsed['schemaRefs'];
        $properties['issues'] = array_values(array_unique(array_merge($issues, $parsed['issues'])));

        return $properties;
    }

    /**
     * @return array{storeItemID:?string, schemaRefs:list<string>, issues:list<string>}
     */
    private function customXmlStoreProperties(string $xml): array
    {
        $properties = [
            'storeItemID' => null,
            'schemaRefs' => [],
            'issues' => [],
        ];

        try {
            $dom = self::loadXml($xml, 'DOCX custom XML data store item properties');
        } catch (\InvalidArgumentException) {
            $properties['issues'][] = 'invalid-custom-xml-properties';

            return $properties;
        }

        $root = $dom->documentElement;
        if (!$root instanceof \DOMElement || $root->namespaceURI !== self::CUSTOM_XML_DATASTORE_NS || $root->localName !== 'datastoreItem') {
            $properties['issues'][] = 'invalid-custom-xml-properties-root';

            return $properties;
        }

        $storeItemId = $this->customXmlDataStoreAttr($root, 'itemID');
        if ($storeItemId !== null && $storeItemId !== '') {
            $properties['storeItemID'] = $storeItemId;
        } else {
            $properties['issues'][] = 'missing-store-item-id';
        }

        $schemaRefs = $this->firstChildElement($root, self::CUSTOM_XML_DATASTORE_NS, 'schemaRefs');
        if ($schemaRefs instanceof \DOMElement) {
            foreach ($schemaRefs->childNodes as $child) {
                if (!$child instanceof \DOMElement || $child->namespaceURI !== self::CUSTOM_XML_DATASTORE_NS || $child->localName !== 'schemaRef') {
                    continue;
                }

                $uri = $this->customXmlDataStoreAttr($child, 'uri');
                if ($uri !== null && $uri !== '') {
                    $properties['schemaRefs'][] = $uri;
                }
            }
        }

        return $properties;
    }

    private function customXmlDataStoreAttr(\DOMElement $element, string $localName): ?string
    {
        return $this->namespacedAttr($element, self::CUSTOM_XML_DATASTORE_NS, $localName);
    }

    private function customXmlStoreLookupKey(string $storeItemId): string
    {
        return strtolower(trim($storeItemId));
    }

    private function contentTypeBaseEquals(string $contentType, string $expected): bool
    {
        return strtolower(trim(explode(';', $contentType, 2)[0])) === strtolower($expected);
    }

    private function qualifiedDomName(\DOMElement $element): string
    {
        $prefix = $element->prefix;

        return is_string($prefix) && $prefix !== '' ? $prefix . ':' . $element->localName : $element->localName;
    }

    private function boundedTextPreview(string $text): string
    {
        if (strlen($text) <= 120) {
            return $text;
        }

        if (function_exists('mb_substr')) {
            return mb_substr($text, 0, 120, 'UTF-8') . '...';
        }

        return substr($text, 0, 120) . '...';
    }

    private function docPropsChildText(\DOMElement $root, string $namespace, string $localName): ?string
    {
        $node = $this->firstChildElement($root, $namespace, $localName);
        if (!$node instanceof \DOMElement) {
            return null;
        }

        $value = trim($node->textContent);

        return $value === '' ? null : $value;
    }

    /**
     * @return list<array{name:string, count:?int}>
     */
    private function docPropsHeadingPairs(\DOMElement $root): array
    {
        $node = $this->firstChildElement($root, self::EXTENDED_PROPERTIES_NS, 'HeadingPairs');
        if (!$node instanceof \DOMElement) {
            return [];
        }

        $values = $this->docPropsVectorValues($node);
        $pairs = [];
        $count = count($values);
        for ($index = 0; $index < $count; $index += 2) {
            $name = $values[$index]['value'] ?? null;
            if (!is_scalar($name) || trim((string) $name) === '') {
                continue;
            }

            $rawCount = $values[$index + 1]['value'] ?? null;
            $pairs[] = [
                'name' => trim((string) $name),
                'count' => is_int($rawCount) ? $rawCount : (is_numeric($rawCount) ? (int) $rawCount : null),
            ];
        }

        return $pairs;
    }

    /**
     * @return list<string>
     */
    private function docPropsVectorStrings(\DOMElement $root, string $localName): array
    {
        $node = $this->firstChildElement($root, self::EXTENDED_PROPERTIES_NS, $localName);
        if (!$node instanceof \DOMElement) {
            return [];
        }

        $strings = [];
        foreach ($this->docPropsVectorValues($node) as $typedValue) {
            if (is_scalar($typedValue['value']) && trim((string) $typedValue['value']) !== '') {
                $strings[] = trim((string) $typedValue['value']);
            }
        }

        return $strings;
    }

    /**
     * @return list<array{type:string, value:mixed}>
     */
    private function docPropsVectorValues(\DOMElement $container): array
    {
        $vector = $this->firstChildElement($container, self::DOC_PROPS_VT_NS, 'vector');
        if (!$vector instanceof \DOMElement) {
            return [];
        }

        $values = [];
        foreach ($vector->childNodes as $child) {
            if (!$child instanceof \DOMElement || $child->namespaceURI !== self::DOC_PROPS_VT_NS) {
                continue;
            }

            $values[] = $this->docPropsTypedValue($child);
        }

        return $values;
    }

    /**
     * @return array{type:string, value:mixed}
     */
    private function docPropsTypedValue(\DOMElement $element): array
    {
        if ($element->namespaceURI === self::DOC_PROPS_VT_NS && $element->localName === 'variant') {
            foreach ($element->childNodes as $child) {
                if ($child instanceof \DOMElement) {
                    return $this->docPropsTypedValue($child);
                }
            }

            return ['type' => 'variant', 'value' => null];
        }

        $type = strtolower($element->localName);
        $text = trim($element->textContent);

        if (in_array($type, ['lpstr', 'lpwstr', 'bstr'], true)) {
            return ['type' => $type, 'value' => $text];
        }

        if (in_array($type, ['i1', 'i2', 'i4', 'i8', 'int', 'ui1', 'ui2', 'ui4', 'ui8', 'uint'], true)) {
            return ['type' => $type, 'value' => preg_match('/^-?\d+$/', $text) === 1 ? (int) $text : null];
        }

        if (in_array($type, ['r4', 'r8', 'decimal'], true)) {
            return ['type' => $type, 'value' => is_numeric($text) ? (float) $text : null];
        }

        if ($type === 'bool') {
            return ['type' => $type, 'value' => $this->docPropsBoolValue($text)];
        }

        if (in_array($type, ['empty', 'null'], true)) {
            return ['type' => $type, 'value' => null];
        }

        return ['type' => $type, 'value' => $text];
    }

    private function docPropsBoolValue(?string $value): ?bool
    {
        if ($value === null) {
            return null;
        }

        $value = strtolower(trim($value));
        if (in_array($value, ['1', 'true', 'on'], true)) {
            return true;
        }
        if (in_array($value, ['0', 'false', 'off'], true)) {
            return false;
        }

        return null;
    }

    /**
     * @return array<string, mixed>
     */
    private function readSettings(ZipPackage $package, OpcRelationshipGraph $graph, string $documentPart): array
    {
        $documentRelationships = $graph->relationshipsForSource($documentPart);
        if (!$documentRelationships instanceof OpcRelationships) {
            return [];
        }

        $relationship = $documentRelationships->firstOfType(self::REL_TYPE_SETTINGS);
        if (!$relationship instanceof OpcRelationship) {
            return [];
        }

        $relationshipSummary = $this->internalSupportPartRelationshipSummary(
            $relationship,
            $package,
            $documentRelationships,
        );

        $settings = [
            'part' => $relationshipSummary['targetPart'],
            'contentType' => $relationshipSummary['contentType'],
            'relationship' => $relationshipSummary,
        ];

        if ($relationshipSummary['exists'] !== true || !is_string($relationshipSummary['targetPart'])) {
            $settings['issues'] = $relationshipSummary['issues'];

            return $settings;
        }

        $dom = self::loadXml($package->read($relationshipSummary['targetPart']), 'DOCX settings XML');
        $root = $dom->documentElement;
        if (!$root instanceof \DOMElement || !$this->isWordElement($root, 'settings')) {
            $settings['issues'] = ['invalid-settings-root'];

            return $settings;
        }

        $settings += [
            'trackRevisions' => $this->settingsOnOff($root, 'trackRevisions'),
            'doNotTrackMoves' => $this->settingsOnOff($root, 'doNotTrackMoves'),
            'doNotTrackFormatting' => $this->settingsOnOff($root, 'doNotTrackFormatting'),
            'evenAndOddHeaders' => $this->settingsOnOff($root, 'evenAndOddHeaders'),
            'updateFields' => $this->settingsOnOff($root, 'updateFields'),
        ];

        $defaultTabStop = $this->settingsIntChildValue($root, 'defaultTabStop');
        if ($defaultTabStop !== null) {
            $settings['defaultTabStopTwips'] = $defaultTabStop;
        }

        $decimalSymbol = $this->settingsStringChildValue($root, 'decimalSymbol');
        if ($decimalSymbol !== null) {
            $settings['decimalSymbol'] = $decimalSymbol;
        }

        $listSeparator = $this->settingsStringChildValue($root, 'listSeparator');
        if ($listSeparator !== null) {
            $settings['listSeparator'] = $listSeparator;
        }

        $documentVariables = $this->settingsDocumentVariables($root);
        if ($documentVariables !== []) {
            $settings['documentVariables'] = $documentVariables;
        }

        $mailMerge = $this->settingsMailMerge($root, $package, $graph, $relationshipSummary['targetPart']);
        if ($mailMerge !== []) {
            $settings['mailMerge'] = $mailMerge;
        }

        $zoom = $this->settingsZoom($root);
        if ($zoom !== []) {
            $settings['zoom'] = $zoom;
        }

        $proofState = $this->settingsProofState($root);
        if ($proofState !== []) {
            $settings['proofState'] = $proofState;
        }

        $documentProtection = $this->settingsDocumentProtection($root);
        if ($documentProtection !== []) {
            $settings['documentProtection'] = $documentProtection;
        }

        $attachedTemplate = $this->settingsAttachedTemplate($root, $package, $graph, $relationshipSummary['targetPart']);
        if ($attachedTemplate !== null) {
            $settings['attachedTemplate'] = $attachedTemplate;
        }

        $compatibility = $this->settingsCompatibility($root);
        if ($compatibility !== []) {
            $settings['compatibility'] = $compatibility;
        }

        return $settings;
    }

    /**
     * @return array{id:string, type:string, target:string, targetPart:?string, contentType:?string, external:bool, exists:?bool, issues:list<string>}
     */
    private function internalSupportPartRelationshipSummary(
        OpcRelationship $relationship,
        ZipPackage $package,
        OpcRelationships $relationships
    ): array {
        $summary = [
            'id' => $relationship->id,
            'type' => $relationship->type,
            'target' => $relationship->target,
            'targetPart' => null,
            'contentType' => null,
            'external' => $relationship->isExternal(),
            'exists' => null,
            'issues' => [],
        ];

        if ($relationship->isExternal()) {
            $summary['issues'][] = 'external-support-part';

            return $summary;
        }

        try {
            $target = $relationships->resolveTarget($relationship);
        } catch (\InvalidArgumentException) {
            $summary['issues'][] = 'invalid-target';

            return $summary;
        }

        $targetPart = OpcPackagePath::stripQueryAndFragment($target);
        $summary['target'] = $target;
        $summary['targetPart'] = $targetPart;
        $summary['contentType'] = $this->contentTypeForPackagePart($package, $targetPart);
        $summary['exists'] = $package->has($targetPart);

        if ($summary['exists'] !== true) {
            $summary['issues'][] = 'missing-in-package';
        }

        if ($summary['contentType'] === null) {
            $summary['issues'][] = 'missing-content-type';
        }

        return $summary;
    }

    /**
     * @param array<string, AstNode> $referencedNotes
     * @param array<string, array{name:?string, basedOn:?string, headingLevel:?int, numPr:?array{numId:?string, level:?int}}> $styles
     * @param array<string, array<int, array{ordered:bool, style:string, delimiter:string, start:int, format:string}>> $numbering
     * @return array<string, mixed>
     */
    private function readGlossaryDocument(
        ZipPackage $package,
        OpcRelationshipGraph $graph,
        string $documentPart,
        array $referencedNotes,
        array $styles,
        array $numbering
    ): array {
        $documentRelationships = $graph->relationshipsForSource($documentPart);
        if (!$documentRelationships instanceof OpcRelationships) {
            return [];
        }

        $relationship = $documentRelationships->firstOfType(self::REL_TYPE_GLOSSARY_DOCUMENT);
        if (!$relationship instanceof OpcRelationship) {
            return [];
        }

        $relationshipSummary = $this->internalSupportPartRelationshipSummary(
            $relationship,
            $package,
            $documentRelationships,
        );
        $glossary = [
            'part' => $relationshipSummary['targetPart'],
            'contentType' => $relationshipSummary['contentType'],
            'relationship' => $relationshipSummary,
            'relationshipsPart' => null,
            'relationshipCount' => 0,
            'relationships' => [],
            'docPartCount' => 0,
            'items' => [],
            'issues' => $relationshipSummary['issues'],
        ];

        if (
            $relationshipSummary['contentType'] !== null
            && $relationshipSummary['contentType'] !== 'application/vnd.openxmlformats-officedocument.wordprocessingml.document.glossary+xml'
        ) {
            $glossary['issues'][] = 'unexpected-content-type';
        }

        if ($relationshipSummary['exists'] !== true || !is_string($relationshipSummary['targetPart'])) {
            $glossary['issues'] = array_values(array_unique($glossary['issues']));

            return $glossary;
        }

        $relationships = $graph->relationshipsForSource($relationshipSummary['targetPart']);
        if ($relationships instanceof OpcRelationships) {
            $glossary['relationshipsPart'] = $relationships->relationshipPartName();
            $glossary['relationshipCount'] = count($relationships->all());
            $glossary['relationships'] = $graph->summarizeTargetsForSource($relationshipSummary['targetPart']);
        }

        $dom = self::loadXml($package->read($relationshipSummary['targetPart']), 'DOCX glossary document XML');
        $root = $dom->documentElement;
        if (!$root instanceof \DOMElement || !$this->isWordElement($root, 'glossaryDocument')) {
            $glossary['issues'][] = 'invalid-glossary-root';
            $glossary['issues'] = array_values(array_unique($glossary['issues']));

            return $glossary;
        }

        $items = [];
        $docParts = $this->firstChildElement($root, self::WORDPROCESSINGML_NS, 'docParts');
        $docPartContainer = $docParts instanceof \DOMElement ? $docParts : $root;
        foreach ($docPartContainer->childNodes as $docPart) {
            if (!$docPart instanceof \DOMElement || !$this->isWordElement($docPart, 'docPart')) {
                continue;
            }

            $items[] = $this->glossaryDocPartItem(
                $docPart,
                $package,
                $relationships,
                $referencedNotes,
                $styles,
                $numbering
            );
        }

        $glossary['items'] = $items;
        $glossary['docPartCount'] = count($items);
        $glossary['issues'] = array_values(array_unique($glossary['issues']));

        return $glossary;
    }

    /**
     * @param array<string, AstNode> $referencedNotes
     * @param array<string, array{name:?string, basedOn:?string, headingLevel:?int, numPr:?array{numId:?string, level:?int}}> $styles
     * @param array<string, array<int, array{ordered:bool, style:string, delimiter:string, start:int, format:string}>> $numbering
     * @return array{name:?string, style:?string, category:?string, gallery:?string, types:list<string>, description:?string, guid:?string, blockCount:int, text:string, blocks:list<AstNode>}
     */
    private function glossaryDocPartItem(
        \DOMElement $docPart,
        ZipPackage $package,
        ?OpcRelationships $relationships,
        array $referencedNotes,
        array $styles,
        array $numbering
    ): array {
        $properties = $this->firstChildElement($docPart, self::WORDPROCESSINGML_NS, 'docPartPr');
        $body = $this->firstChildElement($docPart, self::WORDPROCESSINGML_NS, 'docPartBody');
        $blocks = [];
        if ($body instanceof \DOMElement) {
            $previousNoteReferenceState = $this->noteReferenceState;
            try {
                $blocks = $this->blockContainerChildren($body, $package, $relationships, $referencedNotes, $styles, $numbering);
            } finally {
                $this->noteReferenceState = $previousNoteReferenceState;
            }
        }

        $metadata = $properties instanceof \DOMElement ? $this->glossaryDocPartProperties($properties) : [
            'name' => null,
            'style' => null,
            'category' => null,
            'gallery' => null,
            'types' => [],
            'description' => null,
            'guid' => null,
        ];

        return $metadata + [
            'blockCount' => count($blocks),
            'text' => $this->plainBlockText($blocks),
            'blocks' => $blocks,
        ];
    }

    /**
     * @return array{name:?string, style:?string, category:?string, gallery:?string, types:list<string>, description:?string, guid:?string}
     */
    private function glossaryDocPartProperties(\DOMElement $properties): array
    {
        $category = $this->glossaryDocPartCategory($properties);

        return [
            'name' => $this->wordChildValue($properties, 'name'),
            'style' => $this->wordChildValue($properties, 'style'),
            'category' => $category['name'],
            'gallery' => $category['gallery'],
            'types' => $this->glossaryDocPartTypes($properties),
            'description' => $this->wordChildValue($properties, 'description'),
            'guid' => $this->wordChildValue($properties, 'guid'),
        ];
    }

    /**
     * @return array{name:?string, gallery:?string}
     */
    private function glossaryDocPartCategory(\DOMElement $properties): array
    {
        $category = $this->firstChildElement($properties, self::WORDPROCESSINGML_NS, 'category');
        if (!$category instanceof \DOMElement) {
            return ['name' => null, 'gallery' => null];
        }

        return [
            'name' => $this->wordChildValue($category, 'name'),
            'gallery' => $this->wordChildValue($category, 'gallery'),
        ];
    }

    /**
     * @return list<string>
     */
    private function glossaryDocPartTypes(\DOMElement $properties): array
    {
        $types = $this->firstChildElement($properties, self::WORDPROCESSINGML_NS, 'types');
        if (!$types instanceof \DOMElement) {
            return [];
        }

        $values = [];
        foreach ($types->childNodes as $child) {
            if (!$child instanceof \DOMElement || !$this->isWordElement($child, 'type')) {
                continue;
            }

            $value = $this->wordAttr($child, 'val');
            if ($value !== null && $value !== '') {
                $values[] = $value;
            }
        }

        return $values;
    }

    private function wordChildValue(\DOMElement $element, string $localName): ?string
    {
        $child = $this->firstChildElement($element, self::WORDPROCESSINGML_NS, $localName);
        if (!$child instanceof \DOMElement) {
            return null;
        }

        $value = $this->wordAttr($child, 'val');

        return $value === null || $value === '' ? null : $value;
    }

    private function settingsOnOff(\DOMElement $settings, string $localName): bool
    {
        $child = $this->firstChildElement($settings, self::WORDPROCESSINGML_NS, $localName);

        return $child instanceof \DOMElement && $this->onOffWordAttr($child, 'val', true);
    }

    private function settingsIntChildValue(\DOMElement $settings, string $localName): ?int
    {
        $child = $this->firstChildElement($settings, self::WORDPROCESSINGML_NS, $localName);

        return $child instanceof \DOMElement ? $this->optionalIntWordAttr($child, 'val') : null;
    }

    private function settingsStringChildValue(\DOMElement $settings, string $localName): ?string
    {
        $child = $this->firstChildElement($settings, self::WORDPROCESSINGML_NS, $localName);
        if (!$child instanceof \DOMElement) {
            return null;
        }

        $value = $this->wordAttr($child, 'val');

        return $value === null || $value === '' ? null : $value;
    }

    /**
     * @return array{count:int, duplicateNameCount:int, emptyValueCount:int, duplicateNames:list<string>, byName:array<string, string>, items:list<array{name:string, value:string, duplicate:bool}>}|array{}
     */
    private function settingsDocumentVariables(\DOMElement $settings): array
    {
        $documentVariables = $this->firstChildElement($settings, self::WORDPROCESSINGML_NS, 'docVars');
        if (!$documentVariables instanceof \DOMElement) {
            return [];
        }

        $items = [];
        $byName = [];
        $seen = [];
        $duplicateNames = [];
        $emptyValueCount = 0;

        foreach ($documentVariables->childNodes as $child) {
            if (!$child instanceof \DOMElement || !$this->isWordElement($child, 'docVar')) {
                continue;
            }

            $name = $this->wordAttr($child, 'name');
            if ($name === null || $name === '') {
                continue;
            }

            $value = $this->wordAttr($child, 'val') ?? '';
            if ($value === '') {
                $emptyValueCount++;
            }

            $duplicate = isset($seen[$name]);
            if ($duplicate) {
                $duplicateNames[$name] = true;
            } else {
                $byName[$name] = $value;
                $seen[$name] = true;
            }

            $items[] = [
                'name' => $name,
                'value' => $value,
                'duplicate' => $duplicate,
            ];
        }

        if ($items === []) {
            return [];
        }

        return [
            'count' => count($items),
            'duplicateNameCount' => count($duplicateNames),
            'emptyValueCount' => $emptyValueCount,
            'duplicateNames' => array_keys($duplicateNames),
            'byName' => $byName,
            'items' => $items,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function settingsMailMerge(
        \DOMElement $settings,
        ZipPackage $package,
        OpcRelationshipGraph $graph,
        string $settingsPart
    ): array {
        $mailMerge = $this->firstChildElement($settings, self::WORDPROCESSINGML_NS, 'mailMerge');
        if (!$mailMerge instanceof \DOMElement) {
            return [];
        }

        $metadata = [];
        foreach ([
            'mainDocumentType' => 'mainDocumentType',
            'destination' => 'destination',
            'dataType' => 'dataType',
            'query' => 'query',
            'mailSubject' => 'mailSubject',
        ] as $source => $target) {
            $value = $this->settingsStringChildValue($mailMerge, $source);
            if ($value !== null) {
                $metadata[$target] = $value;
            }
        }

        $connectString = $this->settingsStringChildValue($mailMerge, 'connectString');
        if ($connectString !== null) {
            $metadata['connectStringPresent'] = true;
            $metadata['connectStringLength'] = strlen($connectString);
            $metadata['connectStringSha256'] = hash('sha256', $connectString);
        }

        foreach ([
            'viewMergedData' => 'viewMergedData',
            'linkToQuery' => 'linkToQuery',
            'doNotSuppressBlankLines' => 'doNotSuppressBlankLines',
        ] as $source => $target) {
            $value = $this->settingsOnOffChildValue($mailMerge, $source);
            if ($value !== null) {
                $metadata[$target] = $value;
            }
        }

        foreach ([
            'activeRecord' => 'activeRecord',
            'checkErrors' => 'checkErrors',
        ] as $source => $target) {
            $value = $this->settingsIntChildValue($mailMerge, $source);
            if ($value !== null) {
                $metadata[$target] = $value;
            }
        }

        $relationshipCount = 0;
        $issueCount = 0;
        foreach ([
            'dataSource' => 'dataSource',
            'headerSource' => 'headerSource',
        ] as $source => $target) {
            $summary = $this->settingsRelationshipChildSummary($mailMerge, $source, $package, $graph, $settingsPart);
            if ($summary === null) {
                continue;
            }

            $metadata[$target] = $summary;
            $relationshipCount++;
            if (($summary['issues'] ?? []) !== []) {
                $issueCount++;
            }
        }

        $metadata['relationshipCount'] = $relationshipCount;
        $metadata['issueCount'] = $issueCount;

        return $metadata;
    }

    private function settingsOnOffChildValue(\DOMElement $element, string $localName): ?bool
    {
        $child = $this->firstChildElement($element, self::WORDPROCESSINGML_NS, $localName);
        if (!$child instanceof \DOMElement) {
            return null;
        }

        return $this->onOffWordAttr($child, 'val', true);
    }

    /**
     * @return array{id:?string, relationshipType:?string, target:?string, targetPart:?string, contentType:?string, external:?bool, exists:?bool, externalTargetKind:?string, externalTargetScheme:?string, externalTargetAllowed:?bool, issues:list<string>}|null
     */
    private function settingsRelationshipChildSummary(
        \DOMElement $owner,
        string $localName,
        ZipPackage $package,
        OpcRelationshipGraph $graph,
        string $settingsPart
    ): ?array {
        $child = $this->firstChildElement($owner, self::WORDPROCESSINGML_NS, $localName);
        if (!$child instanceof \DOMElement) {
            return null;
        }

        $id = $this->relationshipAttr($child, 'id');
        $summary = [
            'id' => $id,
            'relationshipType' => null,
            'target' => null,
            'targetPart' => null,
            'contentType' => null,
            'external' => null,
            'exists' => null,
            'externalTargetKind' => null,
            'externalTargetScheme' => null,
            'externalTargetAllowed' => null,
            'issues' => [],
        ];

        if ($id === null || $id === '') {
            $summary['issues'][] = 'missing-relationship-id';

            return $summary;
        }

        $relationships = $graph->relationshipsForSource($settingsPart);
        if (!$relationships instanceof OpcRelationships) {
            $summary['issues'][] = 'missing-relationships';

            return $summary;
        }

        $relationship = $relationships->byId($id);
        if (!$relationship instanceof OpcRelationship) {
            $summary['issues'][] = 'unknown-relationship';

            return $summary;
        }

        $summary['relationshipType'] = $relationship->type;
        if ($relationship->isExternal()) {
            $externalTarget = $relationship->externalTargetPreflight();
            $summary['target'] = $relationship->target;
            $summary['external'] = true;
            $summary['externalTargetKind'] = $externalTarget['kind'];
            $summary['externalTargetScheme'] = $externalTarget['scheme'];
            $summary['externalTargetAllowed'] = $externalTarget['allowed'];
            $summary['issues'] = array_values(array_unique(array_merge($summary['issues'], $externalTarget['issues'])));

            return $summary;
        }

        try {
            $target = $relationships->resolveTarget($relationship);
        } catch (\InvalidArgumentException) {
            $summary['external'] = false;
            $summary['issues'][] = 'invalid-target';

            return $summary;
        }

        $targetPart = OpcPackagePath::stripQueryAndFragment($target);
        $summary['target'] = $target;
        $summary['targetPart'] = $targetPart;
        $summary['contentType'] = $this->contentTypeForPackagePart($package, $targetPart);
        $summary['external'] = false;
        $summary['exists'] = $package->has($targetPart);

        if ($summary['exists'] !== true) {
            $summary['issues'][] = 'missing-in-package';
        }
        if ($summary['contentType'] === null) {
            $summary['issues'][] = 'missing-content-type';
        }

        $summary['issues'] = array_values(array_unique($summary['issues']));

        return $summary;
    }

    /**
     * @return array<string, int|string>
     */
    private function settingsZoom(\DOMElement $settings): array
    {
        $zoom = $this->firstChildElement($settings, self::WORDPROCESSINGML_NS, 'zoom');
        if (!$zoom instanceof \DOMElement) {
            return [];
        }

        $attrs = [];
        $percent = $this->optionalIntWordAttr($zoom, 'percent');
        if ($percent !== null) {
            $attrs['percent'] = $percent;
        }

        $value = $this->wordAttr($zoom, 'val');
        if ($value !== null && $value !== '') {
            $attrs['value'] = $value;
        }

        return $attrs;
    }

    /**
     * @return array<string, string>
     */
    private function settingsProofState(\DOMElement $settings): array
    {
        $proofState = $this->firstChildElement($settings, self::WORDPROCESSINGML_NS, 'proofState');
        if (!$proofState instanceof \DOMElement) {
            return [];
        }

        $attrs = [];
        foreach (['spelling', 'grammar'] as $name) {
            $value = $this->wordAttr($proofState, $name);
            if ($value !== null && $value !== '') {
                $attrs[$name] = $value;
            }
        }

        return $attrs;
    }

    /**
     * @return array<string, bool|int|string>
     */
    private function settingsDocumentProtection(\DOMElement $settings): array
    {
        $protection = $this->firstChildElement($settings, self::WORDPROCESSINGML_NS, 'documentProtection');
        if (!$protection instanceof \DOMElement) {
            return [];
        }

        $attrs = [];
        foreach ([
            'edit' => 'edit',
            'cryptProviderType' => 'cryptProviderType',
            'cryptAlgorithmClass' => 'cryptAlgorithmClass',
            'cryptAlgorithmType' => 'cryptAlgorithmType',
        ] as $source => $target) {
            $value = $this->wordAttr($protection, $source);
            if ($value !== null && $value !== '') {
                $attrs[$target] = $value;
            }
        }

        $attrs['enforcement'] = $this->onOffWordAttr($protection, 'enforcement', false);

        foreach ([
            'cryptAlgorithmSid' => 'cryptAlgorithmSid',
            'cryptSpinCount' => 'cryptSpinCount',
        ] as $source => $target) {
            $value = $this->optionalIntWordAttr($protection, $source);
            if ($value !== null) {
                $attrs[$target] = $value;
            }
        }

        return $attrs;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function settingsAttachedTemplate(
        \DOMElement $settings,
        ZipPackage $package,
        OpcRelationshipGraph $graph,
        string $settingsPart
    ): ?array {
        $attachedTemplate = $this->firstChildElement($settings, self::WORDPROCESSINGML_NS, 'attachedTemplate');
        if (!$attachedTemplate instanceof \DOMElement) {
            return null;
        }

        $id = $this->relationshipAttr($attachedTemplate, 'id');
        $summary = [
            'id' => $id,
            'relationshipType' => null,
            'target' => null,
            'targetPart' => null,
            'contentType' => null,
            'external' => null,
            'exists' => null,
            'externalTargetKind' => null,
            'externalTargetScheme' => null,
            'externalTargetAllowed' => null,
            'issues' => [],
        ];

        if ($id === null || $id === '') {
            $summary['issues'][] = 'missing-relationship-id';

            return $summary;
        }

        $relationships = $graph->relationshipsForSource($settingsPart);
        if (!$relationships instanceof OpcRelationships) {
            $summary['issues'][] = 'missing-relationships';

            return $summary;
        }

        $relationship = $relationships->byId($id);
        if (!$relationship instanceof OpcRelationship) {
            $summary['issues'][] = 'unknown-relationship';

            return $summary;
        }

        $summary['relationshipType'] = $relationship->type;
        if ($relationship->type !== self::REL_TYPE_ATTACHED_TEMPLATE) {
            $summary['issues'][] = 'unexpected-relationship-type';
        }

        if ($relationship->isExternal()) {
            $target = $relationship->target;
            $externalTarget = $relationship->externalTargetPreflight();
            $summary['target'] = $target;
            $summary['external'] = true;
            $summary['externalTargetKind'] = $externalTarget['kind'];
            $summary['externalTargetScheme'] = $externalTarget['scheme'];
            $summary['externalTargetAllowed'] = $externalTarget['allowed'];
            $summary['issues'] = array_values(array_unique(array_merge($summary['issues'], $externalTarget['issues'])));

            return $summary;
        }

        try {
            $target = $relationships->resolveTarget($relationship);
        } catch (\InvalidArgumentException) {
            $summary['external'] = false;
            $summary['issues'][] = 'invalid-target';

            return $summary;
        }

        $targetPart = OpcPackagePath::stripQueryAndFragment($target);
        $summary['target'] = $target;
        $summary['targetPart'] = $targetPart;
        $summary['contentType'] = $this->contentTypeForPackagePart($package, $targetPart);
        $summary['external'] = false;
        $summary['exists'] = $package->has($targetPart);

        if ($summary['exists'] !== true) {
            $summary['issues'][] = 'missing-in-package';
        }
        if ($summary['contentType'] === null) {
            $summary['issues'][] = 'missing-content-type';
        }

        $summary['issues'] = array_values(array_unique($summary['issues']));

        return $summary;
    }

    /**
     * @return list<array{name:string, uri:?string, value:?string}>
     */
    private function settingsCompatibility(\DOMElement $settings): array
    {
        $compatibility = $this->firstChildElement($settings, self::WORDPROCESSINGML_NS, 'compat');
        if (!$compatibility instanceof \DOMElement) {
            return [];
        }

        $items = [];
        foreach ($compatibility->childNodes as $child) {
            if (!$child instanceof \DOMElement || !$this->isWordElement($child, 'compatSetting')) {
                continue;
            }

            $name = $this->wordAttr($child, 'name');
            if ($name === null || $name === '') {
                continue;
            }

            $items[] = [
                'name' => $name,
                'uri' => $this->wordAttr($child, 'uri'),
                'value' => $this->wordAttr($child, 'val'),
            ];
        }

        return $items;
    }

    /**
     * @return array{insertionCount:int, deletionCount:int, formattingCount:int, items:list<array{type:string, accepted:bool, id:?string, author:?string, date:?string, text:string}>}
     */
    private function revisionImportReport(string $xml): array
    {
        $dom = self::loadXml($xml, 'DOCX document XML revisions');
        $root = $dom->documentElement;
        if (!$root instanceof \DOMElement) {
            return [
                'insertionCount' => 0,
                'deletionCount' => 0,
                'formattingCount' => 0,
                'items' => [],
            ];
        }

        $items = [];
        $this->collectTrackedChanges($root, $items);
        $insertionCount = 0;
        $deletionCount = 0;
        $formattingCount = 0;
        foreach ($items as $item) {
            if (in_array($item['type'], ['insertion', 'move-to', 'move-to-range'], true)) {
                $insertionCount++;
            } elseif (in_array($item['type'], ['deletion', 'move-from', 'move-from-range'], true)) {
                $deletionCount++;
            } elseif (in_array($item['type'], ['paragraph-formatting', 'run-formatting'], true)) {
                $formattingCount++;
            }
        }

        return [
            'insertionCount' => $insertionCount,
            'deletionCount' => $deletionCount,
            'formattingCount' => $formattingCount,
            'items' => $items,
        ];
    }

    /**
     * @param list<array{type:string, accepted:bool, id:?string, author:?string, date:?string, text:string}> $items
     */
    private function collectTrackedChanges(\DOMElement $element, array &$items): void
    {
        $type = null;
        if ($this->isWordElement($element, 'ins')) {
            $type = 'insertion';
        } elseif ($this->isWordElement($element, 'del')) {
            $type = 'deletion';
        } elseif ($this->isWordElement($element, 'moveFrom')) {
            $type = 'move-from';
        } elseif ($this->isWordElement($element, 'moveTo')) {
            $type = 'move-to';
        } elseif ($this->isWordElement($element, 'moveFromRangeStart')) {
            $type = 'move-from-range';
        } elseif ($this->isWordElement($element, 'moveToRangeStart')) {
            $type = 'move-to-range';
        } elseif ($this->isWordElement($element, 'pPrChange')) {
            $type = 'paragraph-formatting';
        } elseif ($this->isWordElement($element, 'rPrChange')) {
            $type = 'run-formatting';
        }

        if ($type !== null) {
            $isFormatting = in_array($type, ['paragraph-formatting', 'run-formatting'], true);
            $isMoveRange = in_array($type, ['move-from-range', 'move-to-range'], true);
            $items[] = [
                'type' => $type,
                'accepted' => $isFormatting || in_array($type, ['insertion', 'move-to', 'move-to-range'], true),
                'id' => $this->wordAttr($element, 'id'),
                'author' => $this->wordAttr($element, 'author'),
                'date' => $this->wordAttr($element, 'date'),
                'text' => $isFormatting
                    ? $this->formattingChangeText($element)
                    : ($isMoveRange ? $this->trackedMoveRangeText($element, $type) : $this->trackedChangeText($element)),
            ];

            return;
        }

        foreach ($element->childNodes as $child) {
            if ($child instanceof \DOMElement) {
                $this->collectTrackedChanges($child, $items);
            }
        }
    }

    private function formattingChangeText(\DOMElement $change): string
    {
        $owner = $change;
        $parent = $change->parentNode;
        if ($parent instanceof \DOMElement) {
            if ($this->isWordElement($change, 'pPrChange') && $this->isWordElement($parent, 'pPr') && $parent->parentNode instanceof \DOMElement) {
                $owner = $parent->parentNode;
            } elseif ($this->isWordElement($change, 'rPrChange') && $this->isWordElement($parent, 'rPr') && $parent->parentNode instanceof \DOMElement) {
                $owner = $parent->parentNode;
            }
        }

        return $this->trackedChangeText($owner);
    }

    private function trackedChangeText(\DOMElement $element): string
    {
        $text = $this->trackedChangeTextRaw($element);

        return trim(preg_replace('/[ \t\r\n]+/u', ' ', $text) ?? $text);
    }

    private function trackedChangeTextRaw(\DOMElement $element): string
    {
        if (
            $this->isWordElement($element, 't')
            || $this->isWordElement($element, 'delText')
            || $this->isWordElement($element, 'delInstrText')
            || $this->isMathElement($element, 't')
        ) {
            return $element->textContent;
        }
        if ($this->isWordElement($element, 'tab')) {
            return "\t";
        }
        if ($this->isWordElement($element, 'br')) {
            return "\n";
        }
        if ($this->isWordElement($element, 'softHyphen')) {
            return "\u{00AD}";
        }
        if ($this->isWordElement($element, 'noBreakHyphen')) {
            return "\u{2011}";
        }

        $text = '';
        foreach ($element->childNodes as $child) {
            if ($child instanceof \DOMElement) {
                $text .= $this->trackedChangeTextRaw($child);
            }
        }

        return $text;
    }

    private function trackedMoveRangeText(\DOMElement $start, string $type): string
    {
        $text = '';
        $id = $this->wordAttr($start, 'id');
        for ($sibling = $start->nextSibling; $sibling instanceof \DOMNode; $sibling = $sibling->nextSibling) {
            if (!$sibling instanceof \DOMElement) {
                continue;
            }

            if ($this->isMoveRangeEndElement($sibling, $type, $id)) {
                break;
            }

            $text .= $this->trackedChangeTextRaw($sibling);
        }

        return trim(preg_replace('/[ \t\r\n]+/u', ' ', $text) ?? $text);
    }

    private function isMoveRangeEndElement(\DOMElement $element, string $type, ?string $id): bool
    {
        $expected = match ($type) {
            'move-from-range' => 'moveFromRangeEnd',
            'move-to-range' => 'moveToRangeEnd',
            default => null,
        };
        if ($expected === null || !$this->isWordElement($element, $expected)) {
            return false;
        }

        $endId = $this->wordAttr($element, 'id');

        return $id === null || $id === '' || $endId === null || $endId === '' || $endId === $id;
    }

    /**
     * @param array<string, array{name:?string, basedOn:?string, headingLevel:?int, numPr:?array{numId:?string, level:?int}}> $styles
     */
    private function paragraphHeadingLevel(\DOMElement $paragraph, array $styles): ?int
    {
        $style = $this->paragraphStyleId($paragraph);
        $directStyleLevel = $this->headingLevelFromStyleLabel($style);
        if ($directStyleLevel !== null) {
            return $directStyleLevel;
        }

        $properties = $this->firstChildElement($paragraph, self::WORDPROCESSINGML_NS, 'pPr');
        $outlineLevel = $properties instanceof \DOMElement ? $this->outlineHeadingLevel($properties) : null;
        if ($outlineLevel !== null) {
            return $outlineLevel;
        }

        $seen = [];

        return $this->resolveStyleHeadingLevel($style, $styles, $seen);
    }

    /**
     * @param array<string, array{name:?string, basedOn:?string, headingLevel:?int, numPr:?array{numId:?string, level:?int}}> $styles
     * @param array<string, true> $seen
     */
    private function resolveStyleHeadingLevel(?string $styleId, array $styles, array &$seen): ?int
    {
        if ($styleId === null || isset($seen[$styleId]) || !isset($styles[$styleId])) {
            return null;
        }

        $seen[$styleId] = true;
        $style = $styles[$styleId];
        if ($style['headingLevel'] !== null) {
            return $style['headingLevel'];
        }

        return $this->resolveStyleHeadingLevel($style['basedOn'], $styles, $seen);
    }

    private function styleElementHeadingLevel(\DOMElement $styleElement): ?int
    {
        $styleId = $this->wordAttr($styleElement, 'styleId');
        $level = $this->headingLevelFromStyleLabel($styleId);
        if ($level !== null) {
            return $level;
        }

        $name = $this->styleChildValue($styleElement, 'name');
        $level = $this->headingLevelFromStyleLabel($name);
        if ($level !== null) {
            return $level;
        }

        $properties = $this->firstChildElement($styleElement, self::WORDPROCESSINGML_NS, 'pPr');

        return $properties instanceof \DOMElement ? $this->outlineHeadingLevel($properties) : null;
    }

    private function headingLevelFromStyleLabel(?string $label): ?int
    {
        if ($label === null) {
            return null;
        }

        $normalized = trim(str_replace(['_', '-'], ' ', $label));
        if (preg_match('/^heading\s*([1-6])$/i', $normalized, $match) === 1) {
            return (int) $match[1];
        }

        return null;
    }

    private function outlineHeadingLevel(\DOMElement $properties): ?int
    {
        $outlineLevel = $this->firstChildElement($properties, self::WORDPROCESSINGML_NS, 'outlineLvl');
        if (!$outlineLevel instanceof \DOMElement) {
            return null;
        }

        $value = $this->intWordAttr($outlineLevel, 'val', 9);
        if ($value < 0 || $value > 5) {
            return null;
        }

        return $value + 1;
    }

    private function styleChildValue(\DOMElement $styleElement, string $localName): ?string
    {
        $child = $this->firstChildElement($styleElement, self::WORDPROCESSINGML_NS, $localName);
        if (!$child instanceof \DOMElement) {
            return null;
        }

        $value = $this->wordAttr($child, 'val');

        return $value === null || $value === '' ? null : $value;
    }

    /**
     * @return array{ordered:bool, style:string, delimiter:string, start:int, format:string, paragraphStyleId?:string}
     */
    private function numberingLevelDefinition(\DOMElement $levelElement): array
    {
        $formatElement = $this->firstChildElement($levelElement, self::WORDPROCESSINGML_NS, 'numFmt');
        $format = $formatElement instanceof \DOMElement ? strtolower((string) $this->wordAttr($formatElement, 'val')) : 'decimal';
        $startElement = $this->firstChildElement($levelElement, self::WORDPROCESSINGML_NS, 'start');
        $start = $startElement instanceof \DOMElement ? max(0, $this->intWordAttr($startElement, 'val', 1)) : 1;
        $levelTextElement = $this->firstChildElement($levelElement, self::WORDPROCESSINGML_NS, 'lvlText');
        $levelText = $levelTextElement instanceof \DOMElement ? (string) $this->wordAttr($levelTextElement, 'val') : '%1.';
        $paragraphStyleElement = $this->firstChildElement($levelElement, self::WORDPROCESSINGML_NS, 'pStyle');
        $paragraphStyleId = $paragraphStyleElement instanceof \DOMElement
            ? trim((string) ($this->wordAttr($paragraphStyleElement, 'val') ?? ''))
            : '';

        $definition = [
            'ordered' => !in_array($format, ['bullet', 'none'], true),
            'style' => $this->orderedListStyleForNumberingFormat($format),
            'delimiter' => $this->orderedListDelimiterForLevelText($levelText),
            'start' => $start,
            'format' => $format,
        ];
        if ($paragraphStyleId !== '') {
            $definition['paragraphStyleId'] = $paragraphStyleId;
        }

        return $definition;
    }

    private function orderedListStyleForNumberingFormat(string $format): string
    {
        return match ($format) {
            'lowerletter' => 'lower_alpha',
            'upperletter' => 'upper_alpha',
            'lowerroman' => 'lower_roman',
            'upperroman' => 'upper_roman',
            default => 'decimal',
        };
    }

    private function orderedListDelimiterForLevelText(string $levelText): string
    {
        if (preg_match('/^\(%\d+\)$/', $levelText) === 1) {
            return 'two_parens';
        }
        if (preg_match('/%\d+\)$/', $levelText) === 1) {
            return 'one_paren';
        }

        return 'period';
    }

    /**
     * @param array<string, array{name:?string, basedOn:?string, headingLevel:?int, numPr:?array{numId:?string, level:?int}}> $styles
     * @param array<string, array<int, array{ordered:bool, style:string, delimiter:string, start:int, format:string, paragraphStyleId?:string}>> $numbering
     * @return array{numId:?string, level:?int}|null
     */
    private function paragraphNumberingProperties(\DOMElement $paragraph, array $styles, array $numbering): ?array
    {
        $properties = $this->firstChildElement($paragraph, self::WORDPROCESSINGML_NS, 'pPr');
        $direct = $properties instanceof \DOMElement ? $this->numberingProperties($properties) : null;
        $style = $this->paragraphStyleId($paragraph);
        $seen = [];
        $fromStyle = $this->resolveStyleNumberingProperties($style, $styles, $seen);
        $fromNumberingStyle = $this->numberingPropertiesForParagraphStyle($style, $styles, $numbering);

        $numId = $direct['numId'] ?? $fromStyle['numId'] ?? $fromNumberingStyle['numId'] ?? null;
        if ($numId === null) {
            return null;
        }

        return [
            'numId' => $numId,
            'level' => $direct['level'] ?? $fromStyle['level'] ?? $fromNumberingStyle['level'] ?? 0,
        ];
    }

    /**
     * @param array<string, array{name:?string, basedOn:?string, headingLevel:?int, numPr:?array{numId:?string, level:?int}}> $styles
     * @param array<string, array<int, array{ordered:bool, style:string, delimiter:string, start:int, format:string, paragraphStyleId?:string}>> $numbering
     * @return array{numId:string, level:int}|null
     */
    private function numberingPropertiesForParagraphStyle(?string $styleId, array $styles, array $numbering): ?array
    {
        foreach ($this->paragraphStyleIdChain($styleId, $styles) as $candidateStyleId) {
            foreach ($numbering as $numId => $levels) {
                foreach ($levels as $level => $definition) {
                    if (($definition['paragraphStyleId'] ?? null) === $candidateStyleId) {
                        return [
                            'numId' => (string) $numId,
                            'level' => (int) $level,
                        ];
                    }
                }
            }
        }

        return null;
    }

    /**
     * @param array<string, array{name:?string, basedOn:?string}> $styles
     * @return list<string>
     */
    private function paragraphStyleIdChain(?string $styleId, array $styles): array
    {
        if ($styleId === null || $styleId === '') {
            return [];
        }

        $chain = [];
        $seen = [];
        $current = $styleId;
        while ($current !== null && $current !== '' && !isset($seen[$current])) {
            $chain[] = $current;
            $seen[$current] = true;
            $style = $styles[$current] ?? null;
            $current = is_array($style) && is_string($style['basedOn'] ?? null)
                ? $style['basedOn']
                : null;
        }

        return $chain;
    }

    /**
     * @return array{numId:?string, level:?int}|null
     */
    private function numberingProperties(\DOMElement $properties): ?array
    {
        $numPr = $this->firstChildElement($properties, self::WORDPROCESSINGML_NS, 'numPr');
        if (!$numPr instanceof \DOMElement) {
            return null;
        }

        $numIdElement = $this->firstChildElement($numPr, self::WORDPROCESSINGML_NS, 'numId');
        $levelElement = $this->firstChildElement($numPr, self::WORDPROCESSINGML_NS, 'ilvl');
        $numId = $numIdElement instanceof \DOMElement ? $this->wordAttr($numIdElement, 'val') : null;
        $level = $levelElement instanceof \DOMElement ? $this->intWordAttr($levelElement, 'val', 0) : null;

        if ($numId === null && $level === null) {
            return null;
        }

        return [
            'numId' => $numId,
            'level' => $level,
        ];
    }

    /**
     * @param array<string, array{name:?string, basedOn:?string, headingLevel:?int, numPr:?array{numId:?string, level:?int}}> $styles
     * @param array<string, true> $seen
     * @return array{numId:?string, level:?int}|null
     */
    private function resolveStyleNumberingProperties(?string $styleId, array $styles, array &$seen): ?array
    {
        if ($styleId === null || isset($seen[$styleId]) || !isset($styles[$styleId])) {
            return null;
        }

        $seen[$styleId] = true;
        $style = $styles[$styleId];
        if ($style['numPr'] !== null) {
            return $style['numPr'];
        }

        return $this->resolveStyleNumberingProperties($style['basedOn'], $styles, $seen);
    }

    private function paragraphStyleId(\DOMElement $paragraph): ?string
    {
        $properties = $this->firstChildElement($paragraph, self::WORDPROCESSINGML_NS, 'pPr');
        $style = $properties instanceof \DOMElement ? $this->firstChildElement($properties, self::WORDPROCESSINGML_NS, 'pStyle') : null;
        if (!$style instanceof \DOMElement) {
            return null;
        }

        return $this->wordAttr($style, 'val');
    }

    private function hasOnOffChild(\DOMElement $properties, string $localName): bool
    {
        $child = $this->firstChildElement($properties, self::WORDPROCESSINGML_NS, $localName);
        if (!$child instanceof \DOMElement) {
            return false;
        }

        $value = $this->wordAttr($child, 'val');

        return $value === null || !in_array(strtolower($value), ['0', 'false', 'off', 'none'], true);
    }

    private function hasVertAlign(\DOMElement $properties, string $value): bool
    {
        $child = $this->firstChildElement($properties, self::WORDPROCESSINGML_NS, 'vertAlign');

        return $child instanceof \DOMElement && strtolower((string) $this->wordAttr($child, 'val')) === strtolower($value);
    }

    private function wordAttr(\DOMElement $element, string $localName): ?string
    {
        return $this->namespacedAttr($element, self::WORDPROCESSINGML_NS, $localName);
    }

    private function relationshipAttr(\DOMElement $element, string $localName): ?string
    {
        return $this->namespacedAttr($element, self::OFFICE_RELATIONSHIPS_NS, $localName);
    }

    private function wordExtensionAttr(\DOMElement $element, string $localName): ?string
    {
        foreach ([self::WORDPROCESSINGML_2012_NS, self::WORDPROCESSINGML_2010_NS] as $namespace) {
            $value = $this->namespacedAttr($element, $namespace, $localName);
            if ($value !== null && $value !== '') {
                return $value;
            }
        }

        return null;
    }

    private function namespacedAttr(\DOMElement $element, string $namespace, string $localName): ?string
    {
        if ($element->hasAttributeNS($namespace, $localName)) {
            return $element->getAttributeNS($namespace, $localName);
        }

        return null;
    }

    private function intWordAttr(\DOMElement $element, string $localName, int $default): int
    {
        $value = $this->wordAttr($element, $localName);
        if ($value === null || !preg_match('/^-?\d+$/', $value)) {
            return $default;
        }

        return (int) $value;
    }

    private function optionalIntWordAttr(\DOMElement $element, string $localName): ?int
    {
        $value = $this->wordAttr($element, $localName);
        if ($value === null || !preg_match('/^-?\d+$/', $value)) {
            return null;
        }

        return (int) $value;
    }

    private function onOffWordAttr(\DOMElement $element, string $localName, bool $default): bool
    {
        $value = $this->wordAttr($element, $localName);
        if ($value === null || $value === '') {
            return $default;
        }

        return !in_array(strtolower($value), ['0', 'false', 'off', 'none'], true);
    }

    private function onOffStringValue(?string $value): ?bool
    {
        if ($value === null) {
            return null;
        }

        $value = strtolower(trim($value));
        if (in_array($value, ['1', 'true', 'on'], true)) {
            return true;
        }
        if (in_array($value, ['0', 'false', 'off'], true)) {
            return false;
        }

        return null;
    }

    private function isWordElement(\DOMElement $element, string $localName): bool
    {
        return $element->namespaceURI === self::WORDPROCESSINGML_NS && $element->localName === $localName;
    }

    private function isMarkupCompatibilityElement(\DOMElement $element, string $localName): bool
    {
        return $element->namespaceURI === self::MARKUP_COMPATIBILITY_NS && $element->localName === $localName;
    }

    private function isMathElement(\DOMElement $element, string $localName): bool
    {
        return $element->namespaceURI === self::OFFICE_MATH_NS && $element->localName === $localName;
    }

    private function firstChildElement(\DOMElement $element, string $namespace, string $localName): ?\DOMElement
    {
        foreach ($element->childNodes as $child) {
            if ($child instanceof \DOMElement && $child->namespaceURI === $namespace && $child->localName === $localName) {
                return $child;
            }
        }

        return null;
    }

    private function firstDescendantElement(\DOMElement $element, string $namespace, string $localName): ?\DOMElement
    {
        foreach ($element->getElementsByTagNameNS($namespace, $localName) as $child) {
            if ($child instanceof \DOMElement) {
                return $child;
            }
        }

        return null;
    }

    /**
     * @param list<string> $localNames
     * @return list<\DOMElement>
     */
    private function drawingDescendantElementsByLocalNames(\DOMElement $element, array $localNames): array
    {
        $matched = [];
        foreach ($element->childNodes as $child) {
            if (!$child instanceof \DOMElement) {
                continue;
            }

            if (in_array($child->localName, $localNames, true)) {
                $matched[] = $child;
            }

            array_push($matched, ...$this->drawingDescendantElementsByLocalNames($child, $localNames));
        }

        return $matched;
    }

    /**
     * @param list<string> $localNames
     */
    private function firstChildElementByLocalName(\DOMElement $element, array $localNames): ?\DOMElement
    {
        foreach ($element->childNodes as $child) {
            if ($child instanceof \DOMElement && in_array($child->localName, $localNames, true)) {
                return $child;
            }
        }

        return null;
    }

    /**
     * @param list<string> $localNames
     */
    private function firstDescendantElementByLocalName(\DOMElement $element, array $localNames): ?\DOMElement
    {
        foreach ($this->drawingDescendantElementsByLocalNames($element, $localNames) as $child) {
            return $child;
        }

        return null;
    }

    /**
     * @param list<AstNode> $nodes
     * @return list<AstNode>
     */
    private function coalesceTextNodes(array $nodes): array
    {
        $coalesced = [];
        foreach ($nodes as $node) {
            $lastIndex = count($coalesced) - 1;
            if ($node->type === 'text' && $lastIndex >= 0 && $coalesced[$lastIndex]->type === 'text') {
                $coalesced[$lastIndex] = new AstNode('text', [
                    'text' => (string) $coalesced[$lastIndex]->attr('text', '') . (string) $node->attr('text', ''),
                ]);
                continue;
            }

            $coalesced[] = $node;
        }

        return $coalesced;
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
            $parts[] = $this->plainInlineText($block->children);
        }

        return trim(implode("\n", array_filter($parts, static fn (string $part): bool => $part !== '')));
    }

    private function slugify(string $text): string
    {
        $slug = strtolower(trim($text));
        $slug = preg_replace('/[^\pL\pN]+/u', '-', $slug) ?? '';
        $slug = trim($slug, '-');

        return $slug === '' ? 'section' : $slug;
    }

    private static function loadXml(string $xml, string $label): \DOMDocument
    {
        return XmlHtmlDom::loadXmlDocument($xml, $label);
    }
}
