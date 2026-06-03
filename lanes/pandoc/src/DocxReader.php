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
        $footnotes = $this->loadFootnotes($package, $graph, $documentPart);
        $document = $this->parseDocumentXml(
            $package->read($documentPart),
            $documentPart,
            $package,
            $documentRelationships,
            $footnotes,
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
     * @param array<string, AstNode> $footnotes
     */
    private function parseDocumentXml(
        string $xml,
        string $documentPart,
        ZipPackage $package,
        ?OpcRelationships $relationships,
        array $footnotes
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

        return new AstNode('document', ['sourceFormat' => 'docx', 'documentPart' => $documentPart], $this->bodyChildren($body, $package, $relationships, $footnotes));
    }

    /**
     * @param array<string, AstNode> $footnotes
     * @return list<AstNode>
     */
    private function bodyChildren(\DOMElement $body, ZipPackage $package, ?OpcRelationships $relationships, array $footnotes): array
    {
        $blocks = [];
        foreach ($body->childNodes as $child) {
            if (!$child instanceof \DOMElement) {
                continue;
            }

            if ($this->isWordElement($child, 'p')) {
                $paragraph = $this->paragraphNode($child, $package, $relationships, $footnotes);
                if ($paragraph instanceof AstNode) {
                    $blocks[] = $paragraph;
                }
                continue;
            }

            if ($this->isWordElement($child, 'tbl')) {
                $blocks[] = $this->tableNode($child, $package, $relationships, $footnotes);
            }
        }

        return $blocks;
    }

    /**
     * @param array<string, AstNode> $footnotes
     */
    private function paragraphNode(\DOMElement $paragraph, ZipPackage $package, ?OpcRelationships $relationships, array $footnotes): ?AstNode
    {
        $children = $this->paragraphInlines($paragraph, $package, $relationships, $footnotes);
        $text = $this->plainInlineText($children);
        if ($children === [] && $text === '') {
            return null;
        }

        $style = $this->paragraphStyleId($paragraph);
        if ($style !== null && preg_match('/^Heading([1-6])$/i', $style, $match) === 1) {
            return new AstNode('heading', [
                'level' => (int) $match[1],
                'style' => $style,
                'text' => $text,
                'id' => $this->slugify($text),
            ], $children);
        }

        return new AstNode('paragraph', $style === null ? [] : ['style' => $style], $children);
    }

    /**
     * @param array<string, AstNode> $footnotes
     * @return list<AstNode>
     */
    private function paragraphInlines(\DOMElement $paragraph, ZipPackage $package, ?OpcRelationships $relationships, array $footnotes): array
    {
        $inlines = [];
        foreach ($paragraph->childNodes as $child) {
            if (!$child instanceof \DOMElement || $this->isWordElement($child, 'pPr')) {
                continue;
            }

            array_push($inlines, ...$this->inlineNodes($child, $package, $relationships, $footnotes));
        }

        return $this->coalesceTextNodes($inlines);
    }

    /**
     * @param array<string, AstNode> $footnotes
     * @return list<AstNode>
     */
    private function inlineNodes(\DOMElement $element, ZipPackage $package, ?OpcRelationships $relationships, array $footnotes): array
    {
        if ($this->isWordElement($element, 'r')) {
            return $this->runNodes($element, $package, $relationships, $footnotes);
        }

        if ($this->isWordElement($element, 'hyperlink')) {
            return [$this->hyperlinkNode($element, $package, $relationships, $footnotes)];
        }

        if ($this->isWordElement($element, 'sdt')) {
            $content = $this->firstChildElement($element, self::WORDPROCESSINGML_NS, 'sdtContent');
            return $content instanceof \DOMElement ? $this->inlineContainerNodes($content, $package, $relationships, $footnotes) : [];
        }

        if ($this->isWordElement($element, 'ins') || $this->isWordElement($element, 'smartTag')) {
            return $this->inlineContainerNodes($element, $package, $relationships, $footnotes);
        }

        if ($this->isWordElement($element, 'del')) {
            return [];
        }

        return [];
    }

    /**
     * @param array<string, AstNode> $footnotes
     * @return list<AstNode>
     */
    private function inlineContainerNodes(\DOMElement $element, ZipPackage $package, ?OpcRelationships $relationships, array $footnotes): array
    {
        $inlines = [];
        foreach ($element->childNodes as $child) {
            if ($child instanceof \DOMElement) {
                array_push($inlines, ...$this->inlineNodes($child, $package, $relationships, $footnotes));
            }
        }

        return $inlines;
    }

    /**
     * @param array<string, AstNode> $footnotes
     * @return list<AstNode>
     */
    private function runNodes(\DOMElement $run, ZipPackage $package, ?OpcRelationships $relationships, array $footnotes): array
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
                $id = $this->wordAttr($child, 'id');
                if ($id !== null && isset($footnotes[$id])) {
                    $nodes[] = $footnotes[$id];
                }
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
     * @param array<string, AstNode> $footnotes
     */
    private function hyperlinkNode(\DOMElement $hyperlink, ZipPackage $package, ?OpcRelationships $relationships, array $footnotes): AstNode
    {
        $children = $this->inlineContainerNodes($hyperlink, $package, $relationships, $footnotes);
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
     * @param array<string, AstNode> $footnotes
     */
    private function tableNode(\DOMElement $table, ZipPackage $package, ?OpcRelationships $relationships, array $footnotes): AstNode
    {
        $rows = [];
        foreach ($table->getElementsByTagNameNS(self::WORDPROCESSINGML_NS, 'tr') as $rowElement) {
            if (!$rowElement instanceof \DOMElement || $rowElement->parentNode !== $table) {
                continue;
            }

            $cells = [];
            foreach ($rowElement->getElementsByTagNameNS(self::WORDPROCESSINGML_NS, 'tc') as $cellElement) {
                if (!$cellElement instanceof \DOMElement || $cellElement->parentNode !== $rowElement) {
                    continue;
                }

                $cellBlocks = [];
                foreach ($cellElement->childNodes as $cellChild) {
                    if ($cellChild instanceof \DOMElement && $this->isWordElement($cellChild, 'p')) {
                        $paragraph = $this->paragraphNode($cellChild, $package, $relationships, $footnotes);
                        if ($paragraph instanceof AstNode) {
                            $cellBlocks[] = $paragraph;
                        }
                    }
                }

                $cells[] = new AstNode('table_cell', [
                    'text' => $this->plainBlockText($cellBlocks),
                ], $cellBlocks);
            }

            $rows[] = new AstNode('table_row', [], $cells);
        }

        return new AstNode('table', ['caption' => ''], [
            new AstNode('table_body', [], $rows),
        ]);
    }

    /**
     * @return array<string, AstNode>
     */
    private function loadFootnotes(ZipPackage $package, OpcRelationshipGraph $graph, string $documentPart): array
    {
        $footnotesPart = $graph->firstTargetOfType(self::REL_TYPE_FOOTNOTES, $documentPart);
        if ($footnotesPart === null) {
            return [];
        }

        $footnotesPart = OpcPackagePath::stripQueryAndFragment($footnotesPart);
        if (!$package->has($footnotesPart)) {
            return [];
        }

        $footnoteRelationships = $graph->relationshipsForSource($footnotesPart);
        $dom = self::loadXml($package->read($footnotesPart), 'DOCX footnotes XML');
        $root = $dom->documentElement;
        if (!$root instanceof \DOMElement || !$this->isWordElement($root, 'footnotes')) {
            return [];
        }

        $notes = [];
        foreach ($root->getElementsByTagNameNS(self::WORDPROCESSINGML_NS, 'footnote') as $footnote) {
            if (!$footnote instanceof \DOMElement) {
                continue;
            }

            $id = $this->wordAttr($footnote, 'id');
            if ($id === null || $id === '' || str_starts_with($id, '-')) {
                continue;
            }

            $blocks = [];
            foreach ($footnote->childNodes as $child) {
                if ($child instanceof \DOMElement && $this->isWordElement($child, 'p')) {
                    $paragraph = $this->paragraphNode($child, $package, $footnoteRelationships, []);
                    if ($paragraph instanceof AstNode) {
                        $blocks[] = $paragraph;
                    }
                }
            }

            $notes[$id] = new AstNode('note', ['id' => $id], $blocks);
        }

        return $notes;
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
