<?php

declare(strict_types=1);

namespace PortLibs\Pandoc;

final class DocxOpenXmlReader
{
    private const NS_CT = 'http://schemas.openxmlformats.org/package/2006/content-types';
    private const NS_REL = 'http://schemas.openxmlformats.org/package/2006/relationships';
    private const NS_W = 'http://schemas.openxmlformats.org/wordprocessingml/2006/main';
    private const NS_R = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships';
    private const NS_DC = 'http://purl.org/dc/elements/1.1/';
    private const NS_CP = 'http://schemas.openxmlformats.org/package/2006/metadata/core-properties';
    private const NS_DCTERMS = 'http://purl.org/dc/terms/';
    private const NS_A = 'http://schemas.openxmlformats.org/drawingml/2006/main';
    private const NS_WP = 'http://schemas.openxmlformats.org/drawingml/2006/wordprocessingDrawing';
    private const OFFICE_DOCUMENT_REL = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument';

    public function readFile(string $path): AstNode
    {
        if (!is_file($path)) {
            throw new \RuntimeException("DOCX package does not exist: {$path}");
        }

        $zip = new \ZipArchive();
        $status = $zip->open($path);
        if ($status !== true) {
            throw new \RuntimeException("Unable to open DOCX package: {$path}");
        }

        $parts = [];
        for ($index = 0; $index < $zip->numFiles; $index++) {
            $stat = $zip->statIndex($index);
            $name = is_array($stat) ? (string) ($stat['name'] ?? '') : '';
            if ($name === '' || str_ends_with($name, '/')) {
                continue;
            }

            $contents = $zip->getFromIndex($index);
            if (is_string($contents)) {
                $parts[$name] = $contents;
            }
        }
        $zip->close();

        return $this->readPackage($parts);
    }

    /**
     * @param array<string, string> $parts
     */
    public function readPackage(array $parts): AstNode
    {
        $parts = $this->normalizeParts($parts);
        $contentTypes = $this->readContentTypes($parts);
        $rootRelationships = $this->readRelationshipsPart($parts, '_rels/.rels');
        $documentPart = $this->officeDocumentPart($rootRelationships);

        if (!isset($parts[$documentPart])) {
            throw new \RuntimeException("DOCX package is missing {$documentPart}");
        }

        $documentRelationships = $this->readRelationshipsPart($parts, $this->relationshipsPartFor($documentPart));
        $styles = $this->readStyles($parts['word/styles.xml'] ?? '');
        $numbering = $this->readNumbering($parts['word/numbering.xml'] ?? '');
        $meta = $this->readCoreProperties($parts['docProps/core.xml'] ?? '');
        $media = $this->mediaMetadata($parts, $contentTypes);
        $blocks = $this->readDocumentBlocks($parts[$documentPart], $documentRelationships, $contentTypes, $styles, $numbering);

        $attrs = [
            'docx' => [
                'documentPart' => $documentPart,
                'contentTypes' => $contentTypes,
                'rootRelationships' => $rootRelationships,
                'documentRelationships' => $documentRelationships,
                'styles' => $styles,
                'numbering' => $numbering,
                'media' => $media,
            ],
        ];
        if ($meta !== []) {
            $attrs['meta'] = $meta;
        }

        return new AstNode('document', $attrs, $blocks);
    }

    /**
     * @param array<string, string> $parts
     * @return array<string, string>
     */
    private function normalizeParts(array $parts): array
    {
        $normalized = [];
        foreach ($parts as $name => $contents) {
            $partName = $this->normalizePartName((string) $name);
            if ($partName !== '') {
                $normalized[$partName] = $contents;
            }
        }

        return $normalized;
    }

    /**
     * @param array<string, string> $parts
     * @return array{defaults:array<string, string>, overrides:array<string, string>}
     */
    private function readContentTypes(array $parts): array
    {
        if (!isset($parts['[Content_Types].xml'])) {
            return ['defaults' => [], 'overrides' => []];
        }

        $dom = $this->loadXml($parts['[Content_Types].xml'], '[Content_Types].xml');
        $xpath = $this->xpath($dom);
        $defaults = [];
        $overrides = [];

        foreach ($this->elements($xpath, '/ct:Types/ct:Default') as $node) {
            $extension = strtolower($node->getAttribute('Extension'));
            if ($extension !== '') {
                $defaults[$extension] = $node->getAttribute('ContentType');
            }
        }

        foreach ($this->elements($xpath, '/ct:Types/ct:Override') as $node) {
            $partName = $this->normalizePartName($node->getAttribute('PartName'));
            if ($partName !== '') {
                $overrides[$partName] = $node->getAttribute('ContentType');
            }
        }

        return ['defaults' => $defaults, 'overrides' => $overrides];
    }

    /**
     * @param array<string, string> $parts
     * @return array<string, array{id:string, type:string, target:string, targetMode:string, resolvedTarget:string}>
     */
    private function readRelationshipsPart(array $parts, string $partName): array
    {
        if (!isset($parts[$partName])) {
            return [];
        }

        $dom = $this->loadXml($parts[$partName], $partName);
        $xpath = $this->xpath($dom);
        $relationships = [];
        foreach ($this->elements($xpath, '/rel:Relationships/rel:Relationship') as $node) {
            $id = $node->getAttribute('Id');
            if ($id === '') {
                continue;
            }
            $targetMode = $node->getAttribute('TargetMode');
            $target = $node->getAttribute('Target');
            $relationships[$id] = [
                'id' => $id,
                'type' => $node->getAttribute('Type'),
                'target' => $target,
                'targetMode' => $targetMode,
                'resolvedTarget' => $this->resolveRelationshipTarget($partName, $target, $targetMode),
            ];
        }

        return $relationships;
    }

    /**
     * @param array<string, array{id:string, type:string, target:string, targetMode:string, resolvedTarget:string}> $relationships
     */
    private function officeDocumentPart(array $relationships): string
    {
        foreach ($relationships as $relationship) {
            if ($relationship['type'] === self::OFFICE_DOCUMENT_REL) {
                return $relationship['resolvedTarget'];
            }
        }

        return 'word/document.xml';
    }

    /**
     * @return array<string, array{id:string, name:string, headingLevel:int|null}>
     */
    private function readStyles(string $xml): array
    {
        if ($xml === '') {
            return [];
        }

        $dom = $this->loadXml($xml, 'word/styles.xml');
        $xpath = $this->xpath($dom);
        $styles = [];
        foreach ($this->elements($xpath, '/w:styles/w:style[@w:type="paragraph"]') as $style) {
            $styleId = $style->getAttributeNS(self::NS_W, 'styleId');
            if ($styleId === '') {
                continue;
            }

            $name = $this->childAttr($style, 'name', 'val');
            $outline = $this->childElement($this->childElement($style, 'pPr'), 'outlineLvl');
            $headingLevel = null;
            if ($outline instanceof \DOMElement && is_numeric($outline->getAttributeNS(self::NS_W, 'val'))) {
                $headingLevel = min(6, max(1, ((int) $outline->getAttributeNS(self::NS_W, 'val')) + 1));
            } elseif (preg_match('/(?:^|[^a-z])heading\s*([1-6])(?:$|[^0-9])/i', $name, $match) === 1) {
                $headingLevel = (int) $match[1];
            } elseif (preg_match('/^Heading([1-6])$/', $styleId, $match) === 1) {
                $headingLevel = (int) $match[1];
            }

            $styles[$styleId] = [
                'id' => $styleId,
                'name' => $name,
                'headingLevel' => $headingLevel,
            ];
        }

        return $styles;
    }

    /**
     * @return array<string, array{abstractNumId:string, levels:array<int, array{format:string, text:string, start:int}>}>
     */
    private function readNumbering(string $xml): array
    {
        if ($xml === '') {
            return [];
        }

        $dom = $this->loadXml($xml, 'word/numbering.xml');
        $xpath = $this->xpath($dom);
        $abstracts = [];
        foreach ($this->elements($xpath, '/w:numbering/w:abstractNum') as $abstract) {
            $abstractId = $abstract->getAttributeNS(self::NS_W, 'abstractNumId');
            if ($abstractId === '') {
                continue;
            }

            $levels = [];
            foreach ($this->elements($xpath, 'w:lvl', $abstract) as $level) {
                $ilvl = (int) $level->getAttributeNS(self::NS_W, 'ilvl');
                $levels[$ilvl] = [
                    'format' => $this->childAttr($level, 'numFmt', 'val') ?: 'decimal',
                    'text' => $this->childAttr($level, 'lvlText', 'val') ?: '%' . ($ilvl + 1) . '.',
                    'start' => max(1, (int) ($this->childAttr($level, 'start', 'val') ?: '1')),
                ];
            }
            $abstracts[$abstractId] = $levels;
        }

        $numbering = [];
        foreach ($this->elements($xpath, '/w:numbering/w:num') as $num) {
            $numId = $num->getAttributeNS(self::NS_W, 'numId');
            $abstractNumId = $this->childAttr($num, 'abstractNumId', 'val');
            if ($numId === '' || $abstractNumId === '' || !isset($abstracts[$abstractNumId])) {
                continue;
            }

            $levels = $abstracts[$abstractNumId];
            foreach ($this->elements($xpath, 'w:lvlOverride', $num) as $override) {
                $ilvl = (int) $override->getAttributeNS(self::NS_W, 'ilvl');
                $startOverride = $this->childAttr($override, 'startOverride', 'val');
                if ($startOverride !== '') {
                    $levels[$ilvl]['start'] = max(1, (int) $startOverride);
                }
            }

            $numbering[$numId] = [
                'abstractNumId' => $abstractNumId,
                'levels' => $levels,
            ];
        }

        return $numbering;
    }

    /**
     * @return array<string, mixed>
     */
    private function readCoreProperties(string $xml): array
    {
        if ($xml === '') {
            return [];
        }

        $dom = $this->loadXml($xml, 'docProps/core.xml');
        $xpath = $this->xpath($dom);
        $meta = [];
        foreach ([
            'title' => 'string(/cp:coreProperties/dc:title)',
            'creator' => 'string(/cp:coreProperties/dc:creator)',
            'description' => 'string(/cp:coreProperties/dc:description)',
            'subject' => 'string(/cp:coreProperties/dc:subject)',
            'keywords' => 'string(/cp:coreProperties/cp:keywords)',
            'created' => 'string(/cp:coreProperties/dcterms:created)',
            'modified' => 'string(/cp:coreProperties/dcterms:modified)',
        ] as $name => $query) {
            $value = trim((string) $xpath->evaluate($query));
            if ($value !== '') {
                $meta[$name] = $value;
            }
        }

        if (isset($meta['creator'])) {
            $meta['author'] = [$meta['creator']];
            $meta['authors'] = [$meta['creator']];
        }
        if (isset($meta['title'])) {
            $meta['titleInlines'] = [new AstNode('text', ['text' => $meta['title']])];
        }

        return $meta;
    }

    /**
     * @param array<string, string> $parts
     * @param array{defaults:array<string, string>, overrides:array<string, string>} $contentTypes
     * @return array<string, array{path:string, contentType:string, size:int, sha1:string}>
     */
    private function mediaMetadata(array $parts, array $contentTypes): array
    {
        $media = [];
        foreach ($parts as $name => $contents) {
            if (!str_starts_with($name, 'word/media/')) {
                continue;
            }
            $media[$name] = [
                'path' => $name,
                'contentType' => $this->contentTypeFor($name, $contentTypes),
                'size' => strlen($contents),
                'sha1' => sha1($contents),
            ];
        }

        return $media;
    }

    /**
     * @param array<string, array{id:string, type:string, target:string, targetMode:string, resolvedTarget:string}> $relationships
     * @param array{defaults:array<string, string>, overrides:array<string, string>} $contentTypes
     * @param array<string, array{id:string, name:string, headingLevel:int|null}> $styles
     * @param array<string, array{abstractNumId:string, levels:array<int, array{format:string, text:string, start:int}>}> $numbering
     * @return list<AstNode>
     */
    private function readDocumentBlocks(string $xml, array $relationships, array $contentTypes, array $styles, array $numbering): array
    {
        $dom = $this->loadXml($xml, 'word/document.xml');
        $xpath = $this->xpath($dom);
        $blocks = [];
        $currentList = null;

        foreach ($this->elements($xpath, '/w:document/w:body/*') as $bodyChild) {
            if ($bodyChild->namespaceURI === self::NS_W && $bodyChild->localName === 'p') {
                $paragraph = $this->readParagraph($bodyChild, $xpath, $relationships, $contentTypes, $styles);
                if ($paragraph === null) {
                    $this->flushCurrentList($currentList, $blocks);
                    continue;
                }

                $list = $this->paragraphListAttrs($bodyChild, $numbering);
                if ($list !== null && $paragraph->type === 'paragraph') {
                    $key = $list['type'] . ':' . $list['numId'] . ':' . $list['level'] . ':' . ($list['style'] ?? '') . ':' . ($list['delimiter'] ?? '') . ':' . $list['start'];
                    if (!is_array($currentList) || $currentList['key'] !== $key) {
                        $this->flushCurrentList($currentList, $blocks);
                        $attrs = [
                            'docxNumId' => $list['numId'],
                            'docxLevel' => $list['level'],
                        ];
                        if ($list['type'] === 'ordered_list') {
                            $attrs['start'] = $list['start'];
                            $attrs['style'] = $list['style'];
                            $attrs['delimiter'] = $list['delimiter'];
                        } else {
                            $attrs['bulletChar'] = $list['text'];
                        }
                        $currentList = [
                            'key' => $key,
                            'type' => $list['type'],
                            'attrs' => $attrs,
                            'items' => [],
                        ];
                    }

                    $currentList['items'][] = new AstNode('list_item', [], [$paragraph]);
                    continue;
                }

                $this->flushCurrentList($currentList, $blocks);
                $blocks[] = $paragraph;
                continue;
            }

            if ($bodyChild->namespaceURI === self::NS_W && $bodyChild->localName === 'tbl') {
                $this->flushCurrentList($currentList, $blocks);
                $table = $this->readTable($bodyChild, $xpath, $relationships, $contentTypes, $styles);
                if ($table !== null) {
                    $blocks[] = $table;
                }
            }
        }
        $this->flushCurrentList($currentList, $blocks);

        return $blocks;
    }

    /**
     * @param array{key:string, type:string, attrs:array<string, mixed>, items:list<AstNode>}|null $currentList
     * @param list<AstNode> $blocks
     */
    private function flushCurrentList(?array &$currentList, array &$blocks): void
    {
        if (!is_array($currentList)) {
            return;
        }

        $blocks[] = new AstNode($currentList['type'], $currentList['attrs'], $currentList['items']);
        $currentList = null;
    }

    /**
     * @param array<string, array{id:string, type:string, target:string, targetMode:string, resolvedTarget:string}> $relationships
     * @param array{defaults:array<string, string>, overrides:array<string, string>} $contentTypes
     * @param array<string, array{id:string, name:string, headingLevel:int|null}> $styles
     */
    private function readParagraph(\DOMElement $paragraph, \DOMXPath $xpath, array $relationships, array $contentTypes, array $styles): ?AstNode
    {
        $inlines = $this->readParagraphInlines($paragraph, $xpath, $relationships, $contentTypes);
        $text = $this->plainInlineText($inlines);
        if ($inlines === [] && $text === '') {
            return null;
        }

        $attrs = ['text' => $text];
        $styleId = $this->paragraphStyleId($paragraph);
        if ($styleId !== '') {
            $attrs['docxStyleId'] = $styleId;
            $style = $styles[$styleId] ?? null;
            if (is_array($style)) {
                $attrs['docxStyleName'] = $style['name'];
                if ($style['headingLevel'] !== null) {
                    $attrs['level'] = $style['headingLevel'];
                    $attrs['id'] = $this->identifierFromText($text);

                    return new AstNode('heading', $attrs, $inlines);
                }
            }
        }

        return new AstNode('paragraph', $attrs, $inlines);
    }

    /**
     * @param array<string, array{id:string, type:string, target:string, targetMode:string, resolvedTarget:string}> $relationships
     * @param array{defaults:array<string, string>, overrides:array<string, string>} $contentTypes
     * @return list<AstNode>
     */
    private function readParagraphInlines(\DOMElement $paragraph, \DOMXPath $xpath, array $relationships, array $contentTypes): array
    {
        $inlines = [];
        foreach ($paragraph->childNodes as $child) {
            if (!$child instanceof \DOMElement) {
                continue;
            }

            if ($child->namespaceURI === self::NS_W && $child->localName === 'r') {
                array_push($inlines, ...$this->readRun($child, $xpath, $relationships, $contentTypes));
                continue;
            }

            if ($child->namespaceURI === self::NS_W && $child->localName === 'hyperlink') {
                $linkInlines = [];
                foreach ($this->elements($xpath, 'w:r', $child) as $run) {
                    array_push($linkInlines, ...$this->readRun($run, $xpath, $relationships, $contentTypes));
                }
                if ($linkInlines !== []) {
                    $inlines[] = new AstNode('link', $this->hyperlinkAttrs($child, $relationships), $linkInlines);
                }
                continue;
            }

            if (in_array($child->localName, ['ins', 'smartTag', 'sdt'], true)) {
                foreach ($this->elements($xpath, './/w:r', $child) as $run) {
                    array_push($inlines, ...$this->readRun($run, $xpath, $relationships, $contentTypes));
                }
            }
        }

        return $this->mergeAdjacentText($inlines);
    }

    /**
     * @param array<string, array{id:string, type:string, target:string, targetMode:string, resolvedTarget:string}> $relationships
     * @return array<string, mixed>
     */
    private function hyperlinkAttrs(\DOMElement $hyperlink, array $relationships): array
    {
        $relationshipId = $hyperlink->getAttributeNS(self::NS_R, 'id');
        $anchor = $hyperlink->getAttributeNS(self::NS_W, 'anchor');
        $attrs = ['url' => ''];
        if ($relationshipId !== '' && isset($relationships[$relationshipId])) {
            $relationship = $relationships[$relationshipId];
            $attrs['url'] = $relationship['targetMode'] === 'External'
                ? $relationship['target']
                : $relationship['resolvedTarget'];
            $attrs['relationshipId'] = $relationshipId;
            $attrs['relationshipType'] = $relationship['type'];
            $attrs['targetMode'] = $relationship['targetMode'];
        } elseif ($anchor !== '') {
            $attrs['url'] = '#' . $anchor;
        }

        if ($anchor !== '' && $relationshipId !== '') {
            $attrs['url'] .= '#' . $anchor;
        }

        return $attrs;
    }

    /**
     * @param array<string, array{id:string, type:string, target:string, targetMode:string, resolvedTarget:string}> $relationships
     * @param array{defaults:array<string, string>, overrides:array<string, string>} $contentTypes
     * @return list<AstNode>
     */
    private function readRun(\DOMElement $run, \DOMXPath $xpath, array $relationships, array $contentTypes): array
    {
        $inlines = [];
        foreach ($run->childNodes as $child) {
            if (!$child instanceof \DOMElement) {
                continue;
            }

            if ($child->namespaceURI === self::NS_W && $child->localName === 't') {
                $this->appendText($inlines, $child->textContent);
                continue;
            }
            if ($child->namespaceURI === self::NS_W && $child->localName === 'tab') {
                $this->appendText($inlines, "\t");
                continue;
            }
            if ($child->namespaceURI === self::NS_W && in_array($child->localName, ['br', 'cr'], true)) {
                $inlines[] = new AstNode('linebreak');
                continue;
            }
            if ($child->namespaceURI === self::NS_W && $child->localName === 'noBreakHyphen') {
                $this->appendText($inlines, "\u{2011}");
                continue;
            }
            if ($child->namespaceURI === self::NS_W && $child->localName === 'softHyphen') {
                $this->appendText($inlines, "\u{00AD}");
                continue;
            }
            if ($child->namespaceURI === self::NS_W && $child->localName === 'sym') {
                $hex = $child->getAttributeNS(self::NS_W, 'char');
                if ($hex !== '' && ctype_xdigit($hex)) {
                    $this->appendText($inlines, html_entity_decode('&#x' . $hex . ';', ENT_QUOTES | ENT_XML1, 'UTF-8'));
                }
                continue;
            }
            if ($child->namespaceURI === self::NS_W && $child->localName === 'drawing') {
                array_push($inlines, ...$this->readDrawingImages($child, $xpath, $relationships, $contentTypes));
            }
        }

        return $this->wrapRunInlines($run, $inlines);
    }

    /**
     * @param list<AstNode> $inlines
     * @return list<AstNode>
     */
    private function wrapRunInlines(\DOMElement $run, array $inlines): array
    {
        if ($inlines === []) {
            return [];
        }

        $rPr = $this->childElement($run, 'rPr');
        if (!$rPr instanceof \DOMElement) {
            return $inlines;
        }

        $wrappers = [];
        $vertical = $this->childAttr($rPr, 'vertAlign', 'val');
        if ($vertical === 'superscript') {
            $wrappers[] = 'superscript';
        } elseif ($vertical === 'subscript') {
            $wrappers[] = 'subscript';
        }
        if ($this->runPropertyEnabled($rPr, 'smallCaps')) {
            $wrappers[] = 'small_caps';
        }
        if ($this->runPropertyEnabled($rPr, 'u')) {
            $wrappers[] = 'underline';
        }
        if ($this->runPropertyEnabled($rPr, 'strike') || $this->runPropertyEnabled($rPr, 'dstrike')) {
            $wrappers[] = 'strikeout';
        }
        if ($this->runPropertyEnabled($rPr, 'i')) {
            $wrappers[] = 'emph';
        }
        if ($this->runPropertyEnabled($rPr, 'b')) {
            $wrappers[] = 'strong';
        }

        foreach ($wrappers as $type) {
            $inlines = [new AstNode($type, [], $inlines)];
        }

        return $inlines;
    }

    private function runPropertyEnabled(\DOMElement $rPr, string $name): bool
    {
        $property = $this->childElement($rPr, $name);
        if (!$property instanceof \DOMElement) {
            return false;
        }

        $value = strtolower($property->getAttributeNS(self::NS_W, 'val'));
        if ($name === 'u' && $value === 'none') {
            return false;
        }

        return !in_array($value, ['0', 'false', 'off'], true);
    }

    /**
     * @param array<string, array{id:string, type:string, target:string, targetMode:string, resolvedTarget:string}> $relationships
     * @param array{defaults:array<string, string>, overrides:array<string, string>} $contentTypes
     * @return list<AstNode>
     */
    private function readDrawingImages(\DOMElement $drawing, \DOMXPath $xpath, array $relationships, array $contentTypes): array
    {
        $images = [];
        $docPr = $this->firstElement($xpath, './/wp:docPr', $drawing);
        foreach ($this->elements($xpath, './/a:blip', $drawing) as $blip) {
            $relationshipId = $blip->getAttributeNS(self::NS_R, 'embed');
            if ($relationshipId === '') {
                $relationshipId = $blip->getAttributeNS(self::NS_R, 'link');
            }

            $relationship = $relationships[$relationshipId] ?? null;
            if (!is_array($relationship)) {
                continue;
            }

            $isExternal = $relationship['targetMode'] === 'External';
            $url = $isExternal ? $relationship['target'] : $relationship['resolvedTarget'];
            $alt = $docPr instanceof \DOMElement ? trim($docPr->getAttribute('descr')) : '';
            $title = $docPr instanceof \DOMElement ? trim($docPr->getAttribute('title') ?: $docPr->getAttribute('name')) : '';
            $attrs = [
                'url' => $url,
                'relationshipId' => $relationshipId,
                'relationshipType' => $relationship['type'],
                'targetMode' => $relationship['targetMode'],
            ];
            if (!$isExternal) {
                $attrs['mediaPath'] = $relationship['resolvedTarget'];
                $attrs['contentType'] = $this->contentTypeFor($relationship['resolvedTarget'], $contentTypes);
            }
            if ($alt !== '') {
                $attrs['alt'] = $alt;
            }
            if ($title !== '') {
                $attrs['title'] = $title;
            }

            $children = $alt === '' ? [] : [new AstNode('text', ['text' => $alt])];
            $images[] = new AstNode('image', $attrs, $children);
        }

        return $images;
    }

    /**
     * @param array<string, array{abstractNumId:string, levels:array<int, array{format:string, text:string, start:int}>}> $numbering
     * @return array{type:string, numId:string, level:int, start:int, style:string, delimiter:string, text:string}|null
     */
    private function paragraphListAttrs(\DOMElement $paragraph, array $numbering): ?array
    {
        $pPr = $this->childElement($paragraph, 'pPr');
        $numPr = $pPr instanceof \DOMElement ? $this->childElement($pPr, 'numPr') : null;
        if (!$numPr instanceof \DOMElement) {
            return null;
        }

        $numId = $this->childAttr($numPr, 'numId', 'val');
        if ($numId === '' || !isset($numbering[$numId])) {
            return null;
        }

        $ilvl = (int) ($this->childAttr($numPr, 'ilvl', 'val') ?: '0');
        $level = $numbering[$numId]['levels'][$ilvl] ?? ['format' => 'decimal', 'text' => '%' . ($ilvl + 1) . '.', 'start' => 1];
        $format = $level['format'];
        $type = $format === 'bullet' ? 'bullet_list' : 'ordered_list';

        return [
            'type' => $type,
            'numId' => $numId,
            'level' => $ilvl,
            'start' => $level['start'],
            'style' => $this->listStyleForFormat($format),
            'delimiter' => $this->listDelimiterForText($level['text']),
            'text' => $level['text'],
        ];
    }

    private function paragraphStyleId(\DOMElement $paragraph): string
    {
        $pPr = $this->childElement($paragraph, 'pPr');
        if (!$pPr instanceof \DOMElement) {
            return '';
        }

        return $this->childAttr($pPr, 'pStyle', 'val');
    }

    /**
     * @param array<string, array{id:string, type:string, target:string, targetMode:string, resolvedTarget:string}> $relationships
     * @param array{defaults:array<string, string>, overrides:array<string, string>} $contentTypes
     * @param array<string, array{id:string, name:string, headingLevel:int|null}> $styles
     */
    private function readTable(\DOMElement $table, \DOMXPath $xpath, array $relationships, array $contentTypes, array $styles): ?AstNode
    {
        $rows = [];
        foreach ($this->elements($xpath, 'w:tr', $table) as $row) {
            $cells = [];
            foreach ($this->elements($xpath, 'w:tc', $row) as $cell) {
                $blocks = [];
                foreach ($this->elements($xpath, 'w:p', $cell) as $paragraph) {
                    $node = $this->readParagraph($paragraph, $xpath, $relationships, $contentTypes, $styles);
                    if ($node !== null) {
                        $blocks[] = $node;
                    }
                }
                $gridSpan = $this->firstElement($xpath, 'w:tcPr/w:gridSpan', $cell);
                $attrs = ['text' => $this->plainBlockText($blocks)];
                if ($gridSpan instanceof \DOMElement) {
                    $attrs['colspan'] = max(1, (int) ($gridSpan->getAttributeNS(self::NS_W, 'val') ?: '1'));
                }
                $cells[] = new AstNode('table_cell', $attrs, $blocks);
            }
            if ($cells !== []) {
                $rows[] = new AstNode('table_row', [], $cells);
            }
        }

        if ($rows === []) {
            return null;
        }

        return new AstNode('table', ['caption' => '', 'sourceFormat' => 'docx'], [
            new AstNode('table_body', [], $rows),
        ]);
    }

    private function listStyleForFormat(string $format): string
    {
        return match ($format) {
            'lowerLetter' => 'lower_alpha',
            'upperLetter' => 'upper_alpha',
            'lowerRoman' => 'lower_roman',
            'upperRoman' => 'upper_roman',
            default => 'decimal',
        };
    }

    private function listDelimiterForText(string $text): string
    {
        $trimmed = trim($text);
        if (str_starts_with($trimmed, '(') && str_ends_with($trimmed, ')')) {
            return 'two_parens';
        }
        if (str_ends_with($trimmed, ')')) {
            return 'one_paren';
        }

        return 'period';
    }

    /**
     * @param list<AstNode> $nodes
     * @return list<AstNode>
     */
    private function mergeAdjacentText(array $nodes): array
    {
        $merged = [];
        foreach ($nodes as $node) {
            if ($node->type === 'text' && $merged !== [] && end($merged)->type === 'text') {
                $previous = array_pop($merged);
                $merged[] = new AstNode('text', ['text' => (string) $previous->attr('text', '') . (string) $node->attr('text', '')]);
                continue;
            }
            $merged[] = $node;
        }

        return $merged;
    }

    /**
     * @param list<AstNode> $nodes
     */
    private function appendText(array &$nodes, string $text): void
    {
        if ($text === '') {
            return;
        }

        if ($nodes !== [] && end($nodes)->type === 'text') {
            $previous = array_pop($nodes);
            $nodes[] = new AstNode('text', ['text' => (string) $previous->attr('text', '') . $text]);
            return;
        }

        $nodes[] = new AstNode('text', ['text' => $text]);
    }

    /**
     * @param list<AstNode> $nodes
     */
    private function plainInlineText(array $nodes): string
    {
        $text = '';
        foreach ($nodes as $node) {
            $text .= match ($node->type) {
                'text', 'code' => (string) $node->attr('text', ''),
                'softbreak', 'linebreak' => ' ',
                'image' => (string) $node->attr('alt', ''),
                default => $this->plainInlineText($node->children),
            };
        }

        return trim(preg_replace('/\s+/u', ' ', $text) ?? $text);
    }

    /**
     * @param list<AstNode> $blocks
     */
    private function plainBlockText(array $blocks): string
    {
        $parts = [];
        foreach ($blocks as $block) {
            $parts[] = $block->attr('text', $this->plainInlineText($block->children));
        }

        return trim(implode(' ', array_map(static fn (mixed $part): string => (string) $part, $parts)));
    }

    private function identifierFromText(string $text): string
    {
        $id = strtolower(trim(preg_replace('/[^\pL\pN]+/u', '-', $text) ?? $text, '-'));

        return $id === '' ? 'section' : $id;
    }

    /**
     * @param array{defaults:array<string, string>, overrides:array<string, string>} $contentTypes
     */
    private function contentTypeFor(string $partName, array $contentTypes): string
    {
        $partName = $this->normalizePartName($partName);
        if (isset($contentTypes['overrides'][$partName])) {
            return $contentTypes['overrides'][$partName];
        }

        $extension = strtolower(pathinfo($partName, PATHINFO_EXTENSION));
        return $contentTypes['defaults'][$extension] ?? '';
    }

    private function relationshipsPartFor(string $partName): string
    {
        $partName = $this->normalizePartName($partName);
        $directory = dirname($partName);
        $baseName = basename($partName);

        return ($directory === '.' ? '' : $directory . '/') . '_rels/' . $baseName . '.rels';
    }

    private function resolveRelationshipTarget(string $relsPartName, string $target, string $targetMode): string
    {
        if ($targetMode === 'External' || preg_match('/^[A-Za-z][A-Za-z0-9+.-]*:/', $target) === 1) {
            return $target;
        }

        if (str_starts_with($target, '/')) {
            return $this->normalizePartName($target);
        }

        $sourcePart = $this->sourcePartForRelationshipsPart($relsPartName);
        $base = $sourcePart === '' ? '' : dirname($sourcePart);
        $path = ($base === '' || $base === '.' ? '' : $base . '/') . $target;

        return $this->normalizePartName($path);
    }

    private function sourcePartForRelationshipsPart(string $relsPartName): string
    {
        if ($relsPartName === '_rels/.rels') {
            return '';
        }

        if (preg_match('~^(.*)/_rels/([^/]+)\.rels$~', $relsPartName, $match) === 1) {
            return ($match[1] === '' ? '' : $match[1] . '/') . $match[2];
        }

        return '';
    }

    private function normalizePartName(string $partName): string
    {
        $partName = str_replace('\\', '/', $partName);
        $partName = ltrim($partName, '/');
        $segments = [];
        foreach (explode('/', $partName) as $segment) {
            if ($segment === '' || $segment === '.') {
                continue;
            }
            if ($segment === '..') {
                array_pop($segments);
                continue;
            }
            $segments[] = $segment;
        }

        return implode('/', $segments);
    }

    private function loadXml(string $xml, string $partName): \DOMDocument
    {
        $dom = new \DOMDocument();
        $previous = libxml_use_internal_errors(true);
        $ok = $dom->loadXML($xml, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
        if (!$ok) {
            $errors = libxml_get_errors();
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
            $message = $errors === [] ? 'unknown XML parse error' : trim($errors[0]->message);
            throw new \RuntimeException("Invalid DOCX XML part {$partName}: {$message}");
        }
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        return $dom;
    }

    private function xpath(\DOMDocument $dom): \DOMXPath
    {
        $xpath = new \DOMXPath($dom);
        $xpath->registerNamespace('ct', self::NS_CT);
        $xpath->registerNamespace('rel', self::NS_REL);
        $xpath->registerNamespace('w', self::NS_W);
        $xpath->registerNamespace('r', self::NS_R);
        $xpath->registerNamespace('dc', self::NS_DC);
        $xpath->registerNamespace('cp', self::NS_CP);
        $xpath->registerNamespace('dcterms', self::NS_DCTERMS);
        $xpath->registerNamespace('a', self::NS_A);
        $xpath->registerNamespace('wp', self::NS_WP);

        return $xpath;
    }

    /**
     * @return list<\DOMElement>
     */
    private function elements(\DOMXPath $xpath, string $query, ?\DOMNode $context = null): array
    {
        $nodes = $context instanceof \DOMNode ? $xpath->query($query, $context) : $xpath->query($query);
        if (!$nodes instanceof \DOMNodeList) {
            return [];
        }

        $elements = [];
        foreach ($nodes as $node) {
            if ($node instanceof \DOMElement) {
                $elements[] = $node;
            }
        }

        return $elements;
    }

    private function firstElement(\DOMXPath $xpath, string $query, \DOMNode $context): ?\DOMElement
    {
        $elements = $this->elements($xpath, $query, $context);

        return $elements[0] ?? null;
    }

    private function childElement(?\DOMElement $parent, string $localName): ?\DOMElement
    {
        if (!$parent instanceof \DOMElement) {
            return null;
        }

        foreach ($parent->childNodes as $child) {
            if ($child instanceof \DOMElement && $child->namespaceURI === self::NS_W && $child->localName === $localName) {
                return $child;
            }
        }

        return null;
    }

    private function childAttr(\DOMElement $parent, string $childLocalName, string $attrLocalName): string
    {
        $child = $this->childElement($parent, $childLocalName);

        return $child instanceof \DOMElement ? $child->getAttributeNS(self::NS_W, $attrLocalName) : '';
    }
}
