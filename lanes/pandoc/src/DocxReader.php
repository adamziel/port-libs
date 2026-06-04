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

    /**
     * @return array{document:AstNode, metadata:array<string, mixed>, documentPart:string, relationships:list<array{id:string, type:string, target:string, contentType:?string, external:bool}>}
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
        $referencedNotes = $this->loadReferencedNotes($package, $graph, $documentPart);
        $styles = $this->loadStyles($package, $graph, $documentPart);
        $numbering = $this->loadNumbering($package, $graph, $documentPart);
        $document = $this->parseDocumentXml(
            $package->read($documentPart),
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
        ];
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

        return new AstNode('document', ['sourceFormat' => 'docx', 'documentPart' => $documentPart], $this->bodyChildren(
            $body,
            $package,
            $relationships,
            $referencedNotes,
            $styles,
            $numbering
        ));
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
        $blocks = [];
        $currentList = null;
        foreach ($body->childNodes as $child) {
            if (!$child instanceof \DOMElement) {
                continue;
            }

            if ($this->isWordElement($child, 'p')) {
                $paragraph = $this->paragraphNode($child, $package, $relationships, $referencedNotes, $styles);
                if ($paragraph instanceof AstNode) {
                    $listDefinition = $paragraph->type === 'paragraph'
                        ? $this->listDefinitionForParagraph($child, $styles, $numbering)
                        : null;
                    if ($listDefinition !== null) {
                        $this->appendListParagraph($blocks, $currentList, $paragraph, $listDefinition);
                        continue;
                    }

                    $currentList = null;
                    $blocks[] = $paragraph;
                }
                continue;
            }

            if ($this->isWordElement($child, 'tbl')) {
                $currentList = null;
                $blocks[] = $this->tableNode($child, $package, $relationships, $referencedNotes, $styles);
                continue;
            }

            $currentList = null;
        }

        return $blocks;
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
        array $styles = []
    ): ?AstNode
    {
        $children = $this->paragraphInlines($paragraph, $package, $relationships, $referencedNotes);
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
    private function paragraphInlines(\DOMElement $paragraph, ZipPackage $package, ?OpcRelationships $relationships, array $referencedNotes): array
    {
        $inlines = [];
        foreach ($paragraph->childNodes as $child) {
            if (!$child instanceof \DOMElement || $this->isWordElement($child, 'pPr')) {
                continue;
            }

            array_push($inlines, ...$this->inlineNodes($child, $package, $relationships, $referencedNotes));
        }

        return $this->coalesceTextNodes($inlines);
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

        if ($this->isWordElement($element, 'sdt')) {
            $content = $this->firstChildElement($element, self::WORDPROCESSINGML_NS, 'sdtContent');
            return $content instanceof \DOMElement ? $this->inlineContainerNodes($content, $package, $relationships, $referencedNotes) : [];
        }

        if ($this->isWordElement($element, 'ins') || $this->isWordElement($element, 'smartTag')) {
            return $this->inlineContainerNodes($element, $package, $relationships, $referencedNotes);
        }

        if ($this->isWordElement($element, 'del')) {
            return [];
        }

        return [];
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

            if ($this->isWordElement($child, 'drawing')) {
                array_push($nodes, ...$this->drawingNodes($child, $package, $relationships));
            }
        }

        return $this->applyRunStyle($run, $this->coalesceTextNodes($nodes));
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
            if ($embed === null) {
                continue;
            }

            $target = OpcPackagePath::stripQueryAndFragment($relationships->resolveTarget($embed));
            if (!$package->has($target)) {
                continue;
            }

            $docPr = $this->firstDescendantElement($drawing, self::WORDPROCESSING_DRAWING_NS, 'docPr');
            $alt = $docPr instanceof \DOMElement ? (string) ($docPr->getAttribute('descr') ?: $docPr->getAttribute('name')) : '';
            $title = $docPr instanceof \DOMElement ? $docPr->getAttribute('title') : '';
            $attrs = [
                'url' => ltrim($target, '/'),
                'alt' => $alt,
                'title' => $title,
                'sourcePart' => $target,
                'bytes' => strlen($package->read($target)),
            ];
            if ($title === '') {
                unset($attrs['title']);
            }

            $nodes[] = new AstNode('image', $attrs, $alt === '' ? [] : [new AstNode('text', ['text' => $alt])]);
        }

        return $nodes;
    }

    /**
     * @param array<string, AstNode> $referencedNotes
     * @param array<string, array{name:?string, basedOn:?string, headingLevel:?int, numPr:?array{numId:?string, level:?int}}> $styles
     */
    private function tableNode(
        \DOMElement $table,
        ZipPackage $package,
        ?OpcRelationships $relationships,
        array $referencedNotes,
        array $styles = []
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
                $cellBlocks = $this->tableCellBlocks($cellElement, $package, $relationships, $referencedNotes, $styles);
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

        return new AstNode('table', ['caption' => ''], [
            new AstNode('table_body', [], $rows),
        ]);
    }

    /**
     * @param array<string, AstNode> $referencedNotes
     * @param array<string, array{name:?string, basedOn:?string, headingLevel:?int, numPr:?array{numId:?string, level:?int}}> $styles
     * @return list<AstNode>
     */
    private function tableCellBlocks(
        \DOMElement $cell,
        ZipPackage $package,
        ?OpcRelationships $relationships,
        array $referencedNotes,
        array $styles = []
    ): array
    {
        $blocks = [];
        foreach ($cell->childNodes as $child) {
            if ($child instanceof \DOMElement && $this->isWordElement($child, 'p')) {
                $paragraph = $this->paragraphNode($child, $package, $relationships, $referencedNotes, $styles);
                if ($paragraph instanceof AstNode) {
                    $blocks[] = $paragraph;
                }
            }
        }

        return $blocks;
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
     * @param array{key:string, index:int}|null $currentList
     * @param array{numId:string, level:int, ordered:bool, style:string, delimiter:string, start:int, format:string} $definition
     */
    private function appendListParagraph(array &$blocks, ?array &$currentList, AstNode $paragraph, array $definition): void
    {
        $key = implode(':', [
            $definition['numId'],
            (string) $definition['level'],
            $definition['ordered'] ? 'ordered' : 'bullet',
            $definition['style'],
            $definition['delimiter'],
        ]);

        if ($currentList === null || $currentList['key'] !== $key) {
            $attrs = [
                'sourceFormat' => 'docx',
                'numId' => $definition['numId'],
                'level' => $definition['level'],
            ];
            if ($definition['ordered']) {
                $attrs['style'] = $definition['style'];
                $attrs['delimiter'] = $definition['delimiter'];
                $attrs['start'] = $definition['start'];
            } else {
                $attrs['format'] = $definition['format'];
            }

            $blocks[] = new AstNode($definition['ordered'] ? 'ordered_list' : 'bullet_list', $attrs, []);
            $currentList = [
                'key' => $key,
                'index' => count($blocks) - 1,
            ];
        }

        $index = $currentList['index'];
        $items = $blocks[$index]->children;
        $items[] = new AstNode('list_item', ['level' => $definition['level']], [$paragraph]);
        $blocks[$index] = new AstNode($blocks[$index]->type, $blocks[$index]->attrs, $items);
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
        $blocks = [];
        foreach ($note->childNodes as $child) {
            if (!$child instanceof \DOMElement) {
                continue;
            }

            if ($this->isWordElement($child, 'p')) {
                $paragraph = $this->paragraphNode($child, $package, $relationships, []);
                if ($paragraph instanceof AstNode) {
                    $blocks[] = $paragraph;
                }
                continue;
            }

            if ($this->isWordElement($child, 'tbl')) {
                $blocks[] = $this->tableNode($child, $package, $relationships, []);
            }
        }

        return $blocks;
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

    private function isWordElement(\DOMElement $element, string $localName): bool
    {
        return $element->namespaceURI === self::WORDPROCESSINGML_NS && $element->localName === $localName;
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
        $previous = libxml_use_internal_errors(true);
        $dom = new \DOMDocument();
        $dom->resolveExternals = false;
        $dom->substituteEntities = false;
        $loaded = $dom->loadXML($xml, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        if (!$loaded) {
            throw new \InvalidArgumentException('Unable to parse ' . $label);
        }

        return $dom;
    }
}
