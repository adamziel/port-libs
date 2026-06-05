<?php

declare(strict_types=1);

namespace PortLibs\Pandoc;

final class DocxReader
{
    public const WORDPROCESSINGML_NS = 'http://schemas.openxmlformats.org/wordprocessingml/2006/main';
    public const DRAWINGML_MAIN_NS = 'http://schemas.openxmlformats.org/drawingml/2006/main';
    public const DRAWINGML_PICTURE_NS = 'http://schemas.openxmlformats.org/drawingml/2006/picture';
    public const WORDPROCESSING_DRAWING_NS = 'http://schemas.openxmlformats.org/drawingml/2006/wordprocessingDrawing';
    public const OFFICE_RELATIONSHIPS_NS = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships';
    public const OFFICE_MATH_NS = 'http://schemas.openxmlformats.org/officeDocument/2006/math';
    public const MARKUP_COMPATIBILITY_NS = 'http://schemas.openxmlformats.org/markup-compatibility/2006';
    public const VML_NS = 'urn:schemas-microsoft-com:vml';
    public const OFFICE_VML_NS = 'urn:schemas-microsoft-com:office:office';
    public const CORE_PROPERTIES_NS = 'http://schemas.openxmlformats.org/package/2006/metadata/core-properties';
    public const DC_NS = 'http://purl.org/dc/elements/1.1/';
    public const DCTERMS_NS = 'http://purl.org/dc/terms/';

    public const REL_TYPE_HYPERLINK = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships/hyperlink';
    public const REL_TYPE_IMAGE = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships/image';
    public const REL_TYPE_FOOTNOTES = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships/footnotes';
    public const REL_TYPE_ENDNOTES = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships/endnotes';
    public const REL_TYPE_COMMENTS = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships/comments';
    public const REL_TYPE_STYLES = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles';
    public const REL_TYPE_NUMBERING = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships/numbering';
    public const REL_TYPE_CORE_PROPERTIES = 'http://schemas.openxmlformats.org/package/2006/relationships/metadata/core-properties';
    public const REL_TYPE_ALTERNATIVE_FORMAT_IMPORT = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships/aFChunk';

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
     * @return array{document:AstNode, metadata:array<string, mixed>, documentPart:string, relationships:list<array{id:string, type:string, target:string, contentType:?string, external:bool}>, importReport:array<string, mixed>}
     */
    public function readPackage(ZipPackage $package): array
    {
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
        $referencedNotes = $this->loadReferencedNotes($package, $graph, $documentPart);
        $styles = $this->loadStyles($package, $graph, $documentPart);
        $numbering = $this->loadNumbering($package, $graph, $documentPart);
        $documentXml = $package->read($documentPart);
        $document = $this->parseDocumentXml(
            $documentXml,
            $documentPart,
            $package,
            $documentRelationships,
            $referencedNotes,
            $styles,
            $numbering,
        );
        $metadata = $this->readCoreProperties($package, $graph);

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
            ),
        ];
    }

    /**
     * @param list<array{id:string, type:string, target:string, contentType:?string, external:bool, exists:?bool, relationshipPartTarget:bool, externalTargetKind:?string, externalTargetScheme:?string, externalTargetAllowed:?bool, externalTargetRequiresBaseUri:?bool, externalTargetRewriteBasePart:?string, externalTargetRewriteReason:?string, valid:bool, issues:list<string>}> $relationshipPreflight
     * @param list<array{source:string, depth:int, id:string, type:string, target:string, targetPart:?string, contentType:?string, external:bool, exists:?bool, relationshipPartTarget:bool, externalTargetKind:?string, externalTargetScheme:?string, externalTargetAllowed:?bool, externalTargetRequiresBaseUri:?bool, externalTargetRewriteBasePart:?string, externalTargetRewriteReason:?string, valid:bool, issues:list<string>}> $reachableRelationships
     * @param array{insertionCount:int, deletionCount:int, items:list<array{type:string, accepted:bool, id:?string, author:?string, date:?string, text:string}>} $revisions
     * @param array<string, mixed> $alternativeFormats
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
        array $alternativeFormats
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

        return [
            'documentPart' => $documentPart,
            'relationshipsPart' => $documentRelationships instanceof OpcRelationships ? $documentRelationships->relationshipPartName() : null,
            'relationshipCount' => count($relationshipPreflight),
            'reachableRelationshipCount' => count($reachableRelationships),
            'relationshipIssues' => $relationshipIssues,
            'media' => $this->mediaImportReport($package, $reachableRelationships, $document),
            'alternativeFormats' => $alternativeFormats,
            'revisions' => $revisions,
            'sections' => [
                'count' => count($document->attr('sectionProperties', [])),
                'items' => $document->attr('sectionProperties', []),
            ],
        ];
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
        $sectionProperties = $this->sectionProperties($body, $package, $relationships, $referencedNotes, $styles, $numbering);
        if ($sectionProperties !== []) {
            $attrs['sectionProperties'] = $sectionProperties;
        }

        return new AstNode('document', $attrs, $blocks);
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
        $blocks = [];
        $pendingListParagraphs = [];
        $activeCommentRangeId = null;
        foreach ($container->childNodes as $child) {
            if (!$child instanceof \DOMElement) {
                continue;
            }

            if ($this->isWordElement($child, 'p')) {
                $paragraphHasTextboxRuns = $this->paragraphHasTextboxRuns($child);
                $paragraphBlocks = $this->paragraphBlocks($child, $package, $relationships, $referencedNotes, $styles, $numbering, $activeCommentRangeId);
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
                continue;
            }

            if ($this->isWordElement($child, 'tbl')) {
                $this->appendListParagraphs($blocks, $pendingListParagraphs);
                $blocks[] = $this->tableNode($child, $package, $relationships, $referencedNotes, $styles, $numbering);
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

            $this->appendListParagraphs($blocks, $pendingListParagraphs);
        }

        $this->appendListParagraphs($blocks, $pendingListParagraphs);

        return $blocks;
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
     * @return list<AstNode>
     */
    private function paragraphBlocks(
        \DOMElement $paragraph,
        ZipPackage $package,
        ?OpcRelationships $relationships,
        array $referencedNotes,
        array $styles,
        array $numbering,
        ?string &$activeCommentRangeId
    ): array
    {
        if (!$this->paragraphHasTextboxRuns($paragraph)) {
            $node = $this->paragraphNode($paragraph, $package, $relationships, $referencedNotes, $styles, $activeCommentRangeId);

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

            $this->appendParagraphSegment($blocks, $paragraph, $segmentChildren, $package, $relationships, $referencedNotes, $styles, $activeCommentRangeId);
            foreach ($textboxes as $textbox) {
                array_push($blocks, ...$this->blockContainerChildren($textbox, $package, $relationships, $referencedNotes, $styles, $numbering));
            }
            $segmentChildren = [];
        }

        $this->appendParagraphSegment($blocks, $paragraph, $segmentChildren, $package, $relationships, $referencedNotes, $styles, $activeCommentRangeId);

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
     */
    private function appendParagraphSegment(
        array &$blocks,
        \DOMElement $sourceParagraph,
        array $children,
        ZipPackage $package,
        ?OpcRelationships $relationships,
        array $referencedNotes,
        array $styles,
        ?string &$activeCommentRangeId
    ): void {
        if ($children === []) {
            return;
        }

        $segment = $this->paragraphSegmentElement($sourceParagraph, $children);
        if (!$segment instanceof \DOMElement) {
            return;
        }

        $node = $this->paragraphNode($segment, $package, $relationships, $referencedNotes, $styles, $activeCommentRangeId);
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
     * @return list<\DOMElement>
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

                foreach ($textbox->getElementsByTagNameNS(self::WORDPROCESSINGML_NS, 'txbxContent') as $content) {
                    if ($content instanceof \DOMElement) {
                        $contents[] = $content;
                    }
                }
            }
        }

        return $contents;
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

    /**
     * @param array<string, AstNode> $referencedNotes
     * @param array<string, array{name:?string, basedOn:?string, headingLevel:?int, numPr:?array{numId:?string, level:?int}}> $styles
     */
    private function paragraphNode(
        \DOMElement $paragraph,
        ZipPackage $package,
        ?OpcRelationships $relationships,
        array $referencedNotes,
        array $styles,
        ?string &$activeCommentRangeId
    ): ?AstNode
    {
        $children = $this->paragraphInlines($paragraph, $package, $relationships, $referencedNotes, $activeCommentRangeId);
        $text = $this->plainInlineText($children);
        if ($children === [] && $text === '') {
            return null;
        }

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
     * @param array<string, AstNode> $referencedNotes
     * @return list<AstNode>
     */
    private function paragraphInlines(
        \DOMElement $paragraph,
        ZipPackage $package,
        ?OpcRelationships $relationships,
        array $referencedNotes,
        ?string &$activeCommentRangeId
    ): array
    {
        $inlines = [];
        $activeCommentRangeNodes = [];
        $activeField = null;
        foreach ($paragraph->childNodes as $child) {
            if (!$child instanceof \DOMElement || $this->isWordElement($child, 'pPr')) {
                continue;
            }

            if ($activeField !== null) {
                $nodes = $this->consumeFieldElement($activeField, $child, $package, $relationships, $referencedNotes);
                if ($nodes !== null) {
                    $activeField = null;
                    if ($activeCommentRangeId !== null) {
                        array_push($activeCommentRangeNodes, ...$nodes);
                        continue;
                    }

                    array_push($inlines, ...$nodes);
                }
                continue;
            }

            if ($this->startsComplexField($child)) {
                $activeField = $this->newFieldState();
                $this->consumeFieldElement($activeField, $child, $package, $relationships, $referencedNotes);
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
                if ($activeCommentRangeId !== null) {
                    array_push($activeCommentRangeNodes, ...$nodes);
                    continue;
                }

                array_push($inlines, ...$nodes);
                continue;
            }

            if ($this->isWordElement($child, 'bookmarkEnd')) {
                continue;
            }

            $nodes = $this->inlineNodes($child, $package, $relationships, $referencedNotes);
            if ($activeCommentRangeId !== null) {
                array_push($activeCommentRangeNodes, ...$nodes);
                continue;
            }

            array_push($inlines, ...$nodes);
        }

        if ($activeField !== null) {
            $nodes = $this->fieldResultNodes($activeField);
            if ($activeCommentRangeId !== null) {
                array_push($activeCommentRangeNodes, ...$nodes);
            } else {
                array_push($inlines, ...$nodes);
            }
        }
        if ($activeCommentRangeId !== null) {
            $this->appendCommentRangeSpan($inlines, $activeCommentRangeId, $activeCommentRangeNodes, $referencedNotes);
        }

        return $this->coalesceTextNodes($inlines);
    }

    /**
     * @return array{instruction:string, collectingResult:bool, resultNodes:list<AstNode>}
     */
    private function newFieldState(): array
    {
        return [
            'instruction' => '',
            'collectingResult' => false,
            'resultNodes' => [],
        ];
    }

    private function startsComplexField(\DOMElement $element): bool
    {
        return $this->isWordElement($element, 'r') && $this->runFieldCharType($element) === 'begin';
    }

    /**
     * @param array{instruction:string, collectingResult:bool, resultNodes:list<AstNode>} $field
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
     * @param array{instruction:string, collectingResult:bool, resultNodes:list<AstNode>} $field
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
            $attrs = $this->fieldSpanAttrs($field['instruction']);
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

        return [new AstNode('span', [
            'id' => $name,
            'classes' => ['anchor'],
        ])];
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
            ] as $source => $target) {
                $value = $comment->attr($source);
                if (is_string($value) && $value !== '') {
                    $attributes[$target] = $value;
                }
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
                foreach ([
                    'xpath' => 'data-docx-sdt-xpath',
                    'storeItemID' => 'data-docx-sdt-store-item-id',
                ] as $localName => $attributeName) {
                    $value = $this->wordAttr($dataBinding, $localName);
                    if ($value !== null && $value !== '') {
                        $attributes[$attributeName] = $value;
                    }
                }
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
            'checkbox' => 'checkbox',
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
            if ($this->firstChildElement($properties, self::WORDPROCESSINGML_NS, $localName) instanceof \DOMElement) {
                return $type;
            }
        }

        return null;
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
            if (!$child instanceof \DOMElement || $this->isWordElement($child, 'rPr')) {
                continue;
            }

            if ($this->isWordElement($child, 't') || $this->isWordElement($child, 'delText')) {
                $nodes[] = new AstNode('text', ['text' => $child->textContent]);
                continue;
            }

            if ($this->isWordElement($child, 'tab')) {
                $nodes[] = new AstNode('text', ['text' => "\t"]);
                continue;
            }

            if ($this->isWordElement($child, 'br')) {
                $nodes[] = new AstNode('linebreak');
                continue;
            }

            if ($this->isWordElement($child, 'softHyphen')) {
                $nodes[] = new AstNode('text', ['text' => "\u{00AD}"]);
                continue;
            }

            if ($this->isWordElement($child, 'noBreakHyphen')) {
                $nodes[] = new AstNode('text', ['text' => "\u{2011}"]);
                continue;
            }

            if ($this->isWordElement($child, 'sym')) {
                $symbol = $this->symbolText($child);
                if ($symbol !== '') {
                    $nodes[] = new AstNode('text', ['text' => $symbol]);
                }
                continue;
            }

            if ($this->isWordElement($child, 'footnoteReference')) {
                $this->appendReferencedNote($nodes, $referencedNotes, 'footnote', $child);
                continue;
            }

            if ($this->isWordElement($child, 'endnoteReference')) {
                $this->appendReferencedNote($nodes, $referencedNotes, 'endnote', $child);
                continue;
            }

            if ($this->isWordElement($child, 'commentReference')) {
                $this->appendReferencedNote($nodes, $referencedNotes, 'comment', $child);
                continue;
            }

            if ($this->isMathElement($child, 'oMath')) {
                array_push($nodes, ...$this->mathNodes($child, false));
                continue;
            }

            if ($this->isMathElement($child, 'oMathPara')) {
                array_push($nodes, ...$this->mathNodes($child, true));
                continue;
            }

            if ($this->isWordElement($child, 'drawing')) {
                array_push($nodes, ...$this->drawingNodes($child, $package, $relationships));
                continue;
            }

            if ($this->isWordElement($child, 'pict')) {
                array_push($nodes, ...$this->vmlImageNodes($child, $package, $relationships));
            }
        }

        return $this->applyRunStyle($run, $this->coalesceTextNodes($nodes));
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

        $key = $sourceType . ':' . $id;
        if (isset($referencedNotes[$key])) {
            $nodes[] = $referencedNotes[$key];
        }
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
        if (!$properties instanceof \DOMElement) {
            return $nodes;
        }

        $wrap = static function (string $type, array $children): array {
            return [new AstNode($type, [], $children)];
        };

        if ($this->hasOnOffChild($properties, 'u')) {
            $nodes = $wrap('underline', $nodes);
        }
        if ($this->hasOnOffChild($properties, 'strike') || $this->hasOnOffChild($properties, 'dstrike')) {
            $nodes = $wrap('strikeout', $nodes);
        }
        if ($this->hasVertAlign($properties, 'subscript')) {
            $nodes = $wrap('subscript', $nodes);
        }
        if ($this->hasVertAlign($properties, 'superscript')) {
            $nodes = $wrap('superscript', $nodes);
        }
        if ($this->hasOnOffChild($properties, 'smallCaps')) {
            $nodes = $wrap('small_caps', $nodes);
        }
        if ($this->hasOnOffChild($properties, 'i')) {
            $nodes = $wrap('emph', $nodes);
        }
        if ($this->hasOnOffChild($properties, 'b')) {
            $nodes = $wrap('strong', $nodes);
        }

        return $nodes;
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

        return new AstNode('link', ['url' => $url], $children);
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
            $attrs = $this->fieldSpanAttrs((string) $this->wordAttr($field, 'instr'));
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
     * @return array{classes:list<string>, attributes:array<string, string>}|null
     */
    private function fieldSpanAttrs(string $instruction): ?array
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
        ];

        $fieldName = strtoupper(array_shift($tokens));
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

        return [
            'classes' => ['docx-field', 'docx-field-' . $fieldKey],
            'attributes' => $attributes,
        ];
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
        if (!$relationships instanceof OpcRelationships) {
            return [];
        }

        $nodes = [];
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
            $alt = $docPr instanceof \DOMElement ? (string) ($docPr->getAttribute('descr') ?: $docPr->getAttribute('name')) : '';
            $title = $docPr instanceof \DOMElement ? $docPr->getAttribute('title') : '';
            $image = $this->relationshipImageNode($relationshipId, $relationship, $package, $relationships, $alt, $title);
            if ($image instanceof AstNode) {
                $nodes[] = $image;
            }
        }

        return $nodes;
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
        string $title
    ): ?AstNode {
        $attrs = $this->drawingImageBaseAttrs($relationshipId, $alt, $title);

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

    /**
     * @return array<string, mixed>
     */
    private function drawingImageBaseAttrs(string $relationshipId, string $alt, string $title): array
    {
        $attrs = [
            'relationshipId' => $relationshipId,
            'alt' => $alt,
        ];

        if ($title !== '') {
            $attrs['title'] = $title;
        }

        return $attrs;
    }

    private function drawingPropertiesForBlip(\DOMElement $blip, \DOMElement $drawing): ?\DOMElement
    {
        $node = $blip->parentNode;
        while ($node instanceof \DOMElement) {
            if (
                $node->namespaceURI === self::WORDPROCESSING_DRAWING_NS
                && ($node->localName === 'inline' || $node->localName === 'anchor')
            ) {
                return $this->firstChildElement($node, self::WORDPROCESSING_DRAWING_NS, 'docPr');
            }

            if ($node === $drawing) {
                break;
            }

            $node = $node->parentNode;
        }

        return $this->firstDescendantElement($drawing, self::WORDPROCESSING_DRAWING_NS, 'docPr');
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
     * @param array<string, AstNode> $referencedNotes
     * @param array<string, array{name:?string, basedOn:?string, headingLevel:?int, numPr:?array{numId:?string, level:?int}}> $styles
     * @param array<string, array<int, array{ordered:bool, style:string, delimiter:string, start:int, format:string}>> $numbering
     */
    private function tableNode(
        \DOMElement $table,
        ZipPackage $package,
        ?OpcRelationships $relationships,
        array $referencedNotes,
        array $styles = [],
        array $numbering = []
    ): AstNode
    {
        $rows = [];
        $verticalMerges = [];
        foreach ($table->getElementsByTagNameNS(self::WORDPROCESSINGML_NS, 'tr') as $rowElement) {
            if (!$rowElement instanceof \DOMElement || $rowElement->parentNode !== $table) {
                continue;
            }

            $cells = [];
            $gridColumn = 0;
            foreach ($rowElement->getElementsByTagNameNS(self::WORDPROCESSINGML_NS, 'tc') as $cellElement) {
                if (!$cellElement instanceof \DOMElement || $cellElement->parentNode !== $rowElement) {
                    continue;
                }

                $colspan = $this->tableCellGridSpan($cellElement);
                $verticalMerge = $this->tableCellVerticalMerge($cellElement);
                if ($verticalMerge === 'continue' && isset($verticalMerges[$gridColumn])) {
                    $this->extendTableCellRowspan($rows, $verticalMerges[$gridColumn]['rowIndex'], $verticalMerges[$gridColumn]['cellIndex']);
                    $gridColumn += $colspan;
                    continue;
                }

                $this->clearTableVerticalMergeColumns($verticalMerges, $gridColumn, $colspan);
                $attrs = [];
                if ($colspan > 1) {
                    $attrs['colspan'] = $colspan;
                }
                $cellBlocks = $this->tableCellBlocks($cellElement, $package, $relationships, $referencedNotes, $styles, $numbering);
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

            $rows[] = new AstNode('table_row', [], $cells);
        }

        return TableGeometry::withReviewPacket(new AstNode('table', ['caption' => ''], [
            new AstNode('table_body', [], $rows),
        ]), ['idPrefix' => 'docx-table']);
    }

    /**
     * @param array<string, AstNode> $referencedNotes
     * @param array<string, array{name:?string, basedOn:?string, headingLevel:?int, numPr:?array{numId:?string, level:?int}}> $styles
     * @param array<string, array<int, array{ordered:bool, style:string, delimiter:string, start:int, format:string}>> $numbering
     * @return list<AstNode>
     */
    private function tableCellBlocks(
        \DOMElement $cell,
        ZipPackage $package,
        ?OpcRelationships $relationships,
        array $referencedNotes,
        array $styles = [],
        array $numbering = []
    ): array
    {
        return $this->blockContainerChildren($cell, $package, $relationships, $referencedNotes, $styles, $numbering);
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
        return array_replace(
            $this->loadNotePart($package, $graph, $documentPart, self::REL_TYPE_FOOTNOTES, 'footnotes', 'footnote', 'footnote', 'DOCX footnotes XML'),
            $this->loadNotePart($package, $graph, $documentPart, self::REL_TYPE_ENDNOTES, 'endnotes', 'endnote', 'endnote', 'DOCX endnotes XML'),
            $this->loadNotePart($package, $graph, $documentPart, self::REL_TYPE_COMMENTS, 'comments', 'comment', 'comment', 'DOCX comments XML'),
        );
    }

    /**
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

        $notes = [];
        foreach ($root->childNodes as $note) {
            if (!$note instanceof \DOMElement || !$this->isWordElement($note, $itemName)) {
                continue;
            }

            $id = $this->wordAttr($note, 'id');
            $type = strtolower((string) ($this->wordAttr($note, 'type') ?? ''));
            if (
                $id === null
                || $id === ''
                || str_starts_with($id, '-')
                || in_array($type, ['separator', 'continuationseparator'], true)
            ) {
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
            }

            $notes[$sourceType . ':' . $id] = new AstNode('note', $attrs, $this->noteBlocks($note, $package, $relationships));
        }

        return $notes;
    }

    /**
     * @return list<AstNode>
     */
    private function noteBlocks(\DOMElement $note, ZipPackage $package, ?OpcRelationships $relationships): array
    {
        return $this->blockContainerChildren($note, $package, $relationships, [], [], []);
    }

    /**
     * @return array<string, array{name:?string, basedOn:?string, headingLevel:?int, numPr:?array{numId:?string, level:?int}}>
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
        foreach ($root->getElementsByTagNameNS(self::WORDPROCESSINGML_NS, 'style') as $styleElement) {
            if (!$styleElement instanceof \DOMElement || $styleElement->parentNode !== $root) {
                continue;
            }

            $type = $this->wordAttr($styleElement, 'type');
            if ($type !== null && strtolower($type) !== 'paragraph') {
                continue;
            }

            $styleId = $this->wordAttr($styleElement, 'styleId');
            if ($styleId === null || $styleId === '') {
                continue;
            }

            $name = $this->styleChildValue($styleElement, 'name');
            $basedOn = $this->styleChildValue($styleElement, 'basedOn');
            $properties = $this->firstChildElement($styleElement, self::WORDPROCESSINGML_NS, 'pPr');

            $styles[$styleId] = [
                'name' => $name,
                'basedOn' => $basedOn,
                'headingLevel' => $this->styleElementHeadingLevel($styleElement),
                'numPr' => $properties instanceof \DOMElement ? $this->numberingProperties($properties) : null,
            ];
        }

        return $styles;
    }

    /**
     * @return array<string, array<int, array{ordered:bool, style:string, delimiter:string, start:int, format:string}>>
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
     * @param array<string, array<int, array{ordered:bool, style:string, delimiter:string, start:int, format:string}>> $numbering
     * @return array{numId:string, level:int, ordered:bool, style:string, delimiter:string, start:int, format:string}|null
     */
    private function listDefinitionForParagraph(\DOMElement $paragraph, array $styles, array $numbering): ?array
    {
        $numPr = $this->paragraphNumberingProperties($paragraph, $styles);
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
     * @return array{insertionCount:int, deletionCount:int, items:list<array{type:string, accepted:bool, id:?string, author:?string, date:?string, text:string}>}
     */
    private function revisionImportReport(string $xml): array
    {
        $dom = self::loadXml($xml, 'DOCX document XML revisions');
        $root = $dom->documentElement;
        if (!$root instanceof \DOMElement) {
            return [
                'insertionCount' => 0,
                'deletionCount' => 0,
                'items' => [],
            ];
        }

        $items = [];
        $this->collectTrackedChanges($root, $items);
        $insertionCount = 0;
        $deletionCount = 0;
        foreach ($items as $item) {
            if (in_array($item['type'], ['insertion', 'move-to'], true)) {
                $insertionCount++;
            } elseif (in_array($item['type'], ['deletion', 'move-from'], true)) {
                $deletionCount++;
            }
        }

        return [
            'insertionCount' => $insertionCount,
            'deletionCount' => $deletionCount,
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
        }

        if ($type !== null) {
            $items[] = [
                'type' => $type,
                'accepted' => in_array($type, ['insertion', 'move-to'], true),
                'id' => $this->wordAttr($element, 'id'),
                'author' => $this->wordAttr($element, 'author'),
                'date' => $this->wordAttr($element, 'date'),
                'text' => $this->trackedChangeText($element),
            ];

            return;
        }

        foreach ($element->childNodes as $child) {
            if ($child instanceof \DOMElement) {
                $this->collectTrackedChanges($child, $items);
            }
        }
    }

    private function trackedChangeText(\DOMElement $element): string
    {
        $text = $this->trackedChangeTextRaw($element);

        return trim(preg_replace('/[ \t\r\n]+/u', ' ', $text) ?? $text);
    }

    private function trackedChangeTextRaw(\DOMElement $element): string
    {
        if ($this->isWordElement($element, 't') || $this->isWordElement($element, 'delText')) {
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
     * @return array{ordered:bool, style:string, delimiter:string, start:int, format:string}
     */
    private function numberingLevelDefinition(\DOMElement $levelElement): array
    {
        $formatElement = $this->firstChildElement($levelElement, self::WORDPROCESSINGML_NS, 'numFmt');
        $format = $formatElement instanceof \DOMElement ? strtolower((string) $this->wordAttr($formatElement, 'val')) : 'decimal';
        $startElement = $this->firstChildElement($levelElement, self::WORDPROCESSINGML_NS, 'start');
        $start = $startElement instanceof \DOMElement ? max(0, $this->intWordAttr($startElement, 'val', 1)) : 1;
        $levelTextElement = $this->firstChildElement($levelElement, self::WORDPROCESSINGML_NS, 'lvlText');
        $levelText = $levelTextElement instanceof \DOMElement ? (string) $this->wordAttr($levelTextElement, 'val') : '%1.';

        return [
            'ordered' => !in_array($format, ['bullet', 'none'], true),
            'style' => $this->orderedListStyleForNumberingFormat($format),
            'delimiter' => $this->orderedListDelimiterForLevelText($levelText),
            'start' => $start,
            'format' => $format,
        ];
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
     * @return array{numId:?string, level:?int}|null
     */
    private function paragraphNumberingProperties(\DOMElement $paragraph, array $styles): ?array
    {
        $properties = $this->firstChildElement($paragraph, self::WORDPROCESSINGML_NS, 'pPr');
        $direct = $properties instanceof \DOMElement ? $this->numberingProperties($properties) : null;
        $style = $this->paragraphStyleId($paragraph);
        $seen = [];
        $fromStyle = $this->resolveStyleNumberingProperties($style, $styles, $seen);

        $numId = $direct['numId'] ?? $fromStyle['numId'] ?? null;
        if ($numId === null) {
            return null;
        }

        return [
            'numId' => $numId,
            'level' => $direct['level'] ?? $fromStyle['level'] ?? 0,
        ];
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

    private function isWordElement(\DOMElement $element, string $localName): bool
    {
        return $element->namespaceURI === self::WORDPROCESSINGML_NS && $element->localName === $localName;
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
