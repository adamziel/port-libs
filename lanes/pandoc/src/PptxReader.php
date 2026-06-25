<?php

declare(strict_types=1);

namespace PortLibs\Pandoc;

final class PptxReader
{
    private const REL_NS = 'http://schemas.openxmlformats.org/package/2006/relationships';
    private const P_NS = 'http://schemas.openxmlformats.org/presentationml/2006/main';
    private const A_NS = 'http://schemas.openxmlformats.org/drawingml/2006/main';
    private const R_NS = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships';
    private const CP_NS = 'http://schemas.openxmlformats.org/package/2006/metadata/core-properties';
    private const DC_NS = 'http://purl.org/dc/elements/1.1/';
    private const DCTERMS_NS = 'http://purl.org/dc/terms/';

    /**
     * @param array<string, mixed> $options
     */
    public function __construct(private readonly array $options = [])
    {
    }

    public function read(string $bytes): AstNode
    {
        $path = tempnam(sys_get_temp_dir(), 'pandoc-pptx-');
        if ($path === false) {
            throw new \RuntimeException('Unable to create temporary PPTX path.');
        }

        try {
            if (file_put_contents($path, $bytes) === false) {
                throw new \RuntimeException('Unable to write temporary PPTX package.');
            }

            return $this->readPptxFile($path);
        } finally {
            @unlink($path);
        }
    }

    public function readPptxFile(string $path): AstNode
    {
        $package = ZipOpcPackage::open($path, 'PPTX');

        try {
            $entries = $package->entryNames();
            $presentationPath = $this->presentationPath($package);
            $presentationXml = $package->requireRead($presentationPath, 'PPTX package is missing presentation.xml.');
            $presentationRels = $this->relationships($package->read($this->relationshipsPath($presentationPath)) ?? '');
            $coreXml = $package->read('docProps/core.xml') ?? '';

            $slides = [];
            foreach ($this->slideReferences($presentationXml) as $index => $reference) {
                $relationship = $presentationRels[$reference['relationshipId']] ?? null;
                if (!is_array($relationship) || ($relationship['target'] ?? '') === '') {
                    continue;
                }
                if (($relationship['mode'] ?? '') === 'External') {
                    continue;
                }

                $slidePath = $this->resolveRelationshipTarget($presentationPath, (string) $relationship['target']);
                $slideXml = $package->read($slidePath);
                if (!is_string($slideXml)) {
                    continue;
                }

                $slideRels = $this->relationships($package->read($this->relationshipsPath($slidePath)) ?? '');
                $notesPath = $this->notesSlidePath($slidePath, $slideRels);
                $slides[] = $this->slide(
                    $slideXml,
                    $slidePath,
                    $slideRels,
                    $index + 1,
                    $reference['slideId'],
                    is_string($notesPath) ? ($package->read($notesPath) ?? '') : ''
                );
            }
        } finally {
            $package->close();
        }

        $metadata = $coreXml !== '' ? $this->coreProperties($this->loadXml($coreXml, 'PPTX core properties')) : [];
        $size = $this->slideSize($presentationXml);
        $metadata['pptxPresentationPath'] = $presentationPath;
        $metadata['pptxSlideCount'] = count($slides);
        $metadata['pptxPackageEntries'] = count($entries);
        $metadata['pptxSlideSize'] = $size;
        $metadata['pptxMediaFiles'] = array_values(array_filter(
            array_map(fn (string $entry): string => ZipOpcPackage::normalizePath($entry), $entries),
            static fn (string $entry): bool => str_starts_with($entry, 'ppt/media/')
        ));

        if ($slides === []) {
            $slides[] = new AstNode('paragraph', ['text' => 'No readable PPTX slides were found.'], [
                new AstNode('text', ['text' => 'No readable PPTX slides were found.']),
            ]);
        }

        return new AstNode('document', ['meta' => $metadata], $slides);
    }

    private function presentationPath(ZipOpcPackage $package): string
    {
        $rels = $this->relationships($package->read('_rels/.rels') ?? '');
        foreach ($rels as $relationship) {
            $type = (string) ($relationship['type'] ?? '');
            $target = (string) ($relationship['target'] ?? '');
            if ($target !== '' && str_ends_with($type, '/officeDocument')) {
                return ltrim(ZipOpcPackage::normalizePath($target), '/');
            }
        }

        return 'ppt/presentation.xml';
    }

    /**
     * @return list<array{slideId:string,relationshipId:string}>
     */
    private function slideReferences(string $presentationXml): array
    {
        $dom = $this->loadXml($presentationXml, 'PPTX presentation.xml');
        $references = [];
        foreach ($dom->getElementsByTagNameNS(self::P_NS, 'sldId') as $element) {
            if (!$element instanceof \DOMElement) {
                continue;
            }
            $relationshipId = $this->attr($element, self::R_NS, 'id');
            if ($relationshipId === '') {
                continue;
            }
            $references[] = [
                'slideId' => $element->getAttribute('id'),
                'relationshipId' => $relationshipId,
            ];
        }

        return $references;
    }

    /**
     * @return array{widthEmu:int,heightEmu:int,widthInches:float,heightInches:float}
     */
    private function slideSize(string $presentationXml): array
    {
        $dom = $this->loadXml($presentationXml, 'PPTX presentation.xml');
        $size = $dom->getElementsByTagNameNS(self::P_NS, 'sldSz')->item(0);
        $width = $size instanceof \DOMElement && ctype_digit($size->getAttribute('cx')) ? (int) $size->getAttribute('cx') : 9144000;
        $height = $size instanceof \DOMElement && ctype_digit($size->getAttribute('cy')) ? (int) $size->getAttribute('cy') : 6858000;

        return [
            'widthEmu' => $width,
            'heightEmu' => $height,
            'widthInches' => round($width / 914400, 4),
            'heightInches' => round($height / 914400, 4),
        ];
    }

    /**
     * @param array<string, array{target:string,type:string,mode:string}> $relationships
     */
    private function slide(string $slideXml, string $slidePath, array $relationships, int $number, string $slideId, string $notesXml): AstNode
    {
        $dom = $this->loadXml($slideXml, 'PPTX slide ' . $slidePath);
        $shapeTree = $this->firstElementByLocalName($dom, 'spTree');
        $title = $shapeTree instanceof \DOMElement ? $this->slideTitle($shapeTree) : '';
        $blocks = [
            new AstNode('heading', [
                'id' => 'slide-' . $number,
                'level' => 2,
                'text' => $title !== '' ? $title : 'Slide ' . $number,
            ], [new AstNode('text', ['text' => $title !== '' ? $title : 'Slide ' . $number])]),
        ];

        if ($shapeTree instanceof \DOMElement) {
            foreach ($shapeTree->childNodes as $child) {
                if (!$child instanceof \DOMElement) {
                    continue;
                }
                foreach ($this->shapeBlocks($child, $relationships, $slidePath, true) as $block) {
                    $blocks[] = $block;
                }
            }
        }

        foreach ($this->notesBlocks($notesXml) as $block) {
            $blocks[] = $block;
        }

        return new AstNode('div', [
            'id' => 'slide-' . $number . '-content',
            'classes' => ['pptx-slide'],
            'attributes' => [
                'data-pandoc-source' => 'pptx',
                'data-pptx-slide-number' => (string) $number,
                'data-pptx-slide-id' => $slideId,
                'data-pptx-slide-path' => $slidePath,
            ],
        ], $blocks);
    }

    /**
     * @param array<string, array{target:string,type:string,mode:string}> $relationships
     * @return list<AstNode>
     */
    private function shapeBlocks(\DOMElement $shape, array $relationships, string $slidePath, bool $skipTitle): array
    {
        if ($shape->localName === 'sp') {
            if ($skipTitle && $this->isTitlePlaceholder($shape)) {
                return [];
            }
            $textBody = $this->firstChildElementByLocalName($shape, 'txBody');
            if (!$textBody instanceof \DOMElement) {
                return [];
            }

            return $this->paragraphsToBlocks($this->drawingParagraphs($textBody, $relationships, $slidePath));
        }

        if ($shape->localName === 'pic') {
            $image = $this->picture($shape, $relationships, $slidePath);

            return $image instanceof AstNode ? [new AstNode('paragraph', [], [$image])] : [];
        }

        if ($shape->localName === 'graphicFrame') {
            $table = $this->graphicFrameTable($shape, $relationships, $slidePath);
            if ($table instanceof AstNode) {
                return [$table];
            }
        }

        if ($shape->localName === 'grpSp') {
            $blocks = [];
            foreach ($shape->childNodes as $child) {
                if ($child instanceof \DOMElement) {
                    array_push($blocks, ...$this->shapeBlocks($child, $relationships, $slidePath, $skipTitle));
                }
            }

            return $blocks;
        }

        return [];
    }

    private function slideTitle(\DOMElement $shapeTree): string
    {
        foreach ($shapeTree->childNodes as $child) {
            if (!$child instanceof \DOMElement || $child->localName !== 'sp' || !$this->isTitlePlaceholder($child)) {
                continue;
            }
            $title = trim($this->drawingText($child));
            if ($title !== '') {
                return $title;
            }
        }

        return '';
    }

    private function isTitlePlaceholder(\DOMElement $shape): bool
    {
        foreach ($shape->getElementsByTagNameNS(self::P_NS, 'ph') as $placeholder) {
            if (!$placeholder instanceof \DOMElement) {
                continue;
            }
            $type = $placeholder->getAttribute('type');
            if (in_array($type, ['title', 'ctrTitle'], true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<string, array{target:string,type:string,mode:string}> $relationships
     * @return list<array{level:int,ordered:bool,attrs:array<string,mixed>,node:AstNode}>
     */
    private function drawingParagraphs(\DOMElement $textBody, array $relationships, string $slidePath): array
    {
        $paragraphs = [];
        foreach ($textBody->childNodes as $child) {
            if (!$child instanceof \DOMElement || $child->localName !== 'p') {
                continue;
            }
            $inlines = $this->drawingInlines($child, $relationships, $slidePath);
            $text = $this->plainText($inlines);
            if ($text === '' && $inlines === []) {
                continue;
            }
            $list = $this->paragraphListAttributes($child);
            $paragraphs[] = [
                'level' => $list['level'],
                'ordered' => $list['ordered'],
                'attrs' => $list['attrs'],
                'node' => new AstNode($list['ordered'] || $list['bullet'] ? 'plain' : 'paragraph', ['text' => $text], $inlines),
            ];
        }

        return $paragraphs;
    }

    /**
     * @param list<array{level:int,ordered:bool,attrs:array<string,mixed>,node:AstNode}> $paragraphs
     * @return list<AstNode>
     */
    private function paragraphsToBlocks(array $paragraphs): array
    {
        $blocks = [];
        $pending = [];
        $flush = function () use (&$blocks, &$pending): void {
            if ($pending === []) {
                return;
            }
            $index = 0;
            array_push($blocks, ...$this->listBlocksAtLevel($pending, $index, max(1, (int) $pending[0]['level'])));
            $pending = [];
        };

        foreach ($paragraphs as $paragraph) {
            if ($paragraph['ordered'] || $paragraph['attrs'] !== [] || $paragraph['level'] > 0) {
                $pending[] = $paragraph;
                continue;
            }
            $flush();
            $blocks[] = $paragraph['node'];
        }
        $flush();

        return $blocks;
    }

    /**
     * @param list<array{level:int,ordered:bool,attrs:array<string,mixed>,node:AstNode}> $records
     * @return list<AstNode>
     */
    private function listBlocksAtLevel(array $records, int &$index, int $level): array
    {
        $blocks = [];
        $count = count($records);
        while ($index < $count) {
            $record = $records[$index];
            $recordLevel = max(1, (int) $record['level']);
            if ($recordLevel < $level) {
                break;
            }
            if ($recordLevel > $level) {
                break;
            }
            $ordered = (bool) $record['ordered'];
            $attrs = $ordered ? $record['attrs'] : [];
            $items = [];
            while ($index < $count) {
                $record = $records[$index];
                $recordLevel = max(1, (int) $record['level']);
                if ($recordLevel < $level || $recordLevel > $level || (bool) $record['ordered'] !== $ordered) {
                    break;
                }
                $index++;
                $children = [$record['node']];
                while ($index < $count && max(1, (int) $records[$index]['level']) > $level) {
                    $nestedLevel = max(1, (int) $records[$index]['level']);
                    array_push($children, ...$this->listBlocksAtLevel($records, $index, $nestedLevel));
                }
                $items[] = new AstNode('list_item', [], $children);
            }
            $blocks[] = new AstNode($ordered ? 'ordered_list' : 'bullet_list', $attrs, $items);
        }

        return $blocks;
    }

    /**
     * @return array{bullet:bool,ordered:bool,level:int,attrs:array<string,mixed>}
     */
    private function paragraphListAttributes(\DOMElement $paragraph): array
    {
        $properties = $this->firstChildElementByLocalName($paragraph, 'pPr');
        if (!$properties instanceof \DOMElement) {
            return ['bullet' => false, 'ordered' => false, 'level' => 0, 'attrs' => []];
        }
        foreach ($properties->childNodes as $child) {
            if ($child instanceof \DOMElement && $child->localName === 'buNone') {
                return ['bullet' => false, 'ordered' => false, 'level' => 0, 'attrs' => []];
            }
        }

        $level = ctype_digit($properties->getAttribute('lvl')) ? ((int) $properties->getAttribute('lvl')) + 1 : 1;
        foreach ($properties->childNodes as $child) {
            if ($child instanceof \DOMElement && $child->localName === 'buAutoNum') {
                return [
                    'bullet' => false,
                    'ordered' => true,
                    'level' => $level,
                    'attrs' => ['style' => $this->autoNumberStyle($child->getAttribute('type')), 'start' => max(1, (int) ($child->getAttribute('startAt') ?: '1'))],
                ];
            }
        }
        foreach ($properties->childNodes as $child) {
            if ($child instanceof \DOMElement && in_array($child->localName, ['buChar', 'buBlip', 'buFont'], true)) {
                return ['bullet' => true, 'ordered' => false, 'level' => $level, 'attrs' => []];
            }
        }

        return ['bullet' => false, 'ordered' => false, 'level' => 0, 'attrs' => []];
    }

    private function autoNumberStyle(string $type): string
    {
        return match (true) {
            str_contains($type, 'alphaLc') => 'lower_alpha',
            str_contains($type, 'alphaUc') => 'upper_alpha',
            str_contains($type, 'romanLc') => 'lower_roman',
            str_contains($type, 'romanUc') => 'upper_roman',
            default => 'decimal',
        };
    }

    /**
     * @param array<string, array{target:string,type:string,mode:string}> $relationships
     * @return list<AstNode>
     */
    private function drawingInlines(\DOMElement $container, array $relationships, string $slidePath): array
    {
        $inlines = [];
        foreach ($container->childNodes as $child) {
            if (!$child instanceof \DOMElement) {
                continue;
            }
            if ($child->localName === 'r' || $child->localName === 'fld') {
                array_push($inlines, ...$this->drawingRun($child, $relationships, $slidePath));
                continue;
            }
            if ($child->localName === 'br') {
                $inlines[] = new AstNode('linebreak');
                continue;
            }
            if ($child->localName === 'hyperlink') {
                array_push($inlines, ...$this->drawingInlines($child, $relationships, $slidePath));
            }
        }

        return $this->mergeAdjacentText($inlines);
    }

    /**
     * @param array<string, array{target:string,type:string,mode:string}> $relationships
     * @return list<AstNode>
     */
    private function drawingRun(\DOMElement $run, array $relationships, string $slidePath): array
    {
        $text = '';
        foreach ($run->childNodes as $child) {
            if ($child instanceof \DOMElement && $child->localName === 't') {
                $text .= $child->textContent;
            }
        }
        if ($text === '') {
            return [];
        }

        $node = new AstNode('text', ['text' => $text]);
        $properties = $this->firstChildElementByLocalName($run, 'rPr');
        if ($properties instanceof \DOMElement) {
            if ($properties->getAttribute('b') === '1' || $properties->getAttribute('b') === 'true') {
                $node = new AstNode('strong', [], [$node]);
            }
            if ($properties->getAttribute('i') === '1' || $properties->getAttribute('i') === 'true') {
                $node = new AstNode('emph', [], [$node]);
            }
            $underline = $properties->getAttribute('u');
            if ($underline !== '' && $underline !== 'none') {
                $node = new AstNode('underline', [], [$node]);
            }
            $strike = $properties->getAttribute('strike');
            if ($strike !== '' && $strike !== 'noStrike') {
                $node = new AstNode('strikeout', [], [$node]);
            }
            $linkId = $this->hyperlinkRelationshipId($properties);
            if ($linkId !== '') {
                $link = $relationships[$linkId] ?? null;
                if (is_array($link)) {
                    $url = (string) ($link['target'] ?? '');
                    if (($link['mode'] ?? '') !== 'External' && $url !== '') {
                        $url = $this->resolveRelationshipTarget($slidePath, $url);
                    }
                    $node = new AstNode('link', ['url' => $url, 'title' => ''], [$node]);
                }
            }
        }

        return [$node];
    }

    private function hyperlinkRelationshipId(\DOMElement $properties): string
    {
        foreach ($properties->getElementsByTagNameNS(self::A_NS, 'hlinkClick') as $link) {
            if ($link instanceof \DOMElement) {
                return $this->attr($link, self::R_NS, 'id');
            }
        }

        return '';
    }

    /**
     * @param array<string, array{target:string,type:string,mode:string}> $relationships
     */
    private function picture(\DOMElement $picture, array $relationships, string $slidePath): ?AstNode
    {
        $embedId = '';
        foreach ($picture->getElementsByTagNameNS(self::A_NS, 'blip') as $blip) {
            if (!$blip instanceof \DOMElement) {
                continue;
            }
            $embedId = $this->attr($blip, self::R_NS, 'embed');
            if ($embedId === '') {
                $embedId = $this->attr($blip, self::R_NS, 'link');
            }
            if ($embedId !== '') {
                break;
            }
        }
        if ($embedId === '') {
            return null;
        }
        $relationship = $relationships[$embedId] ?? null;
        if (!is_array($relationship)) {
            return null;
        }

        $url = (string) ($relationship['target'] ?? '');
        if (($relationship['mode'] ?? '') !== 'External') {
            $url = $this->resolveRelationshipTarget($slidePath, $url);
        }

        $name = '';
        $alt = '';
        foreach ($picture->getElementsByTagNameNS(self::P_NS, 'cNvPr') as $properties) {
            if ($properties instanceof \DOMElement) {
                $name = $properties->getAttribute('name');
                $alt = $properties->getAttribute('descr');
                break;
            }
        }

        return new AstNode('image', [
            'url' => $url,
            'title' => $name,
            'alt' => $alt !== '' ? $alt : $name,
            'attributes' => ['data-pptx-relationship-id' => $embedId],
        ]);
    }

    /**
     * @param array<string, array{target:string,type:string,mode:string}> $relationships
     */
    private function graphicFrameTable(\DOMElement $frame, array $relationships, string $slidePath): ?AstNode
    {
        foreach ($frame->getElementsByTagNameNS(self::A_NS, 'tbl') as $table) {
            if (!$table instanceof \DOMElement) {
                continue;
            }
            $rows = [];
            foreach ($table->childNodes as $rowNode) {
                if (!$rowNode instanceof \DOMElement || $rowNode->localName !== 'tr') {
                    continue;
                }
                $cells = [];
                foreach ($rowNode->childNodes as $cellNode) {
                    if (!$cellNode instanceof \DOMElement || $cellNode->localName !== 'tc') {
                        continue;
                    }
                    $textBody = $this->firstChildElementByLocalName($cellNode, 'txBody');
                    $paragraphs = $textBody instanceof \DOMElement
                        ? $this->paragraphsToBlocks($this->drawingParagraphs($textBody, $relationships, $slidePath))
                        : [];
                    $text = $textBody instanceof \DOMElement ? trim($this->drawingText($textBody)) : '';
                    if ($paragraphs === [] && $text !== '') {
                        $paragraphs[] = new AstNode('plain', ['text' => $text], [new AstNode('text', ['text' => $text])]);
                    }
                    $cells[] = new AstNode('table_cell', ['text' => $text], $paragraphs);
                }
                if ($cells !== []) {
                    $rows[] = new AstNode('table_row', [], $cells);
                }
            }
            if ($rows === []) {
                return null;
            }

            $head = array_shift($rows);

            return new AstNode('table', [], [
                new AstNode('table_head', [], [$head]),
                new AstNode('table_body', [], $rows),
            ]);
        }

        return null;
    }

    /**
     * @return list<AstNode>
     */
    private function notesBlocks(string $notesXml): array
    {
        if ($notesXml === '') {
            return [];
        }

        try {
            $dom = $this->loadXml($notesXml, 'PPTX notes slide');
        } catch (\InvalidArgumentException) {
            return [];
        }

        $blocks = [];
        foreach ($dom->getElementsByTagNameNS(self::P_NS, 'sp') as $shape) {
            if (!$shape instanceof \DOMElement || $this->isTitlePlaceholder($shape)) {
                continue;
            }
            $textBody = $this->firstChildElementByLocalName($shape, 'txBody');
            if (!$textBody instanceof \DOMElement) {
                continue;
            }
            foreach ($this->paragraphsToBlocks($this->drawingParagraphs($textBody, [], '')) as $block) {
                $blocks[] = $block;
            }
        }

        if ($blocks === []) {
            return [];
        }

        return [new AstNode('div', [
            'classes' => ['pptx-notes'],
            'attributes' => ['data-pandoc-source' => 'pptx-notes'],
        ], $blocks)];
    }

    /**
     * @param array<string, array{target:string,type:string,mode:string}> $relationships
     */
    private function notesSlidePath(string $slidePath, array $relationships): ?string
    {
        foreach ($relationships as $relationship) {
            $type = (string) ($relationship['type'] ?? '');
            $target = (string) ($relationship['target'] ?? '');
            if ($target !== '' && str_ends_with($type, '/notesSlide')) {
                return $this->resolveRelationshipTarget($slidePath, $target);
            }
        }

        return null;
    }

    private function drawingText(\DOMElement $element): string
    {
        $parts = [];
        foreach ($element->getElementsByTagNameNS(self::A_NS, 't') as $text) {
            if ($text instanceof \DOMElement && $text->textContent !== '') {
                $parts[] = $text->textContent;
            }
        }

        return trim(preg_replace('/\s+/u', ' ', implode(' ', $parts)) ?? implode(' ', $parts));
    }

    /**
     * @return array<string, mixed>
     */
    private function coreProperties(\DOMDocument $dom): array
    {
        $meta = [];
        foreach ($dom->getElementsByTagName('*') as $element) {
            if (!$element instanceof \DOMElement) {
                continue;
            }
            $text = trim(preg_replace('/\s+/', ' ', $element->textContent) ?? $element->textContent);
            if ($text === '') {
                continue;
            }
            $key = match ($element->namespaceURI . ' ' . $element->localName) {
                self::DC_NS . ' title' => 'title',
                self::DC_NS . ' creator' => 'author',
                self::DC_NS . ' description' => 'description',
                self::CP_NS . ' keywords' => 'keywords',
                self::DCTERMS_NS . ' created',
                self::DCTERMS_NS . ' modified' => 'date',
                default => '',
            };
            if ($key === '') {
                continue;
            }
            $meta[$key] = $text;
            if ($key === 'title') {
                $meta['titleInlines'] = [new AstNode('text', ['text' => $text])];
            }
        }

        return $meta;
    }

    /**
     * @return array<string, array{target:string,type:string,mode:string}>
     */
    private function relationships(string $xml): array
    {
        if ($xml === '') {
            return [];
        }

        try {
            $dom = $this->loadXml($xml, 'PPTX relationships');
        } catch (\InvalidArgumentException) {
            return [];
        }

        $relationships = [];
        foreach ($dom->getElementsByTagNameNS(self::REL_NS, 'Relationship') as $relationship) {
            if (!$relationship instanceof \DOMElement) {
                continue;
            }
            $id = $relationship->getAttribute('Id');
            if ($id === '') {
                continue;
            }
            $relationships[$id] = [
                'target' => $relationship->getAttribute('Target'),
                'type' => $relationship->getAttribute('Type'),
                'mode' => $relationship->getAttribute('TargetMode'),
            ];
        }

        return $relationships;
    }

    private function relationshipsPath(string $partPath): string
    {
        $partPath = ZipOpcPackage::normalizePath($partPath);
        $dir = ZipOpcPackage::dirname($partPath);
        $base = basename($partPath);

        return ($dir === '' ? '' : $dir . '/') . '_rels/' . $base . '.rels';
    }

    private function resolveRelationshipTarget(string $sourcePartPath, string $target): string
    {
        $target = trim($target);
        if ($target === '' || preg_match('/^[a-z][a-z0-9+.-]*:/i', $target) === 1 || str_starts_with($target, '//')) {
            return $target;
        }
        if (str_starts_with($target, '/')) {
            return ZipOpcPackage::normalizePath(ltrim($target, '/'));
        }

        return ZipOpcPackage::normalizePath(ZipOpcPackage::dirname($sourcePartPath) . '/' . $target);
    }

    private function loadXml(string $xml, string $label): \DOMDocument
    {
        $dom = new \DOMDocument();
        $previous = libxml_use_internal_errors(true);
        $ok = $dom->loadXML($xml, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
        $errors = libxml_get_errors();
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        if (!$ok) {
            $message = $errors !== [] ? trim($errors[0]->message) : 'unknown XML parse error';
            throw new \InvalidArgumentException($label . ' is not valid XML: ' . $message);
        }

        return $dom;
    }

    private function firstElementByLocalName(\DOMDocument $dom, string $localName): ?\DOMElement
    {
        foreach ($dom->getElementsByTagName('*') as $element) {
            if ($element instanceof \DOMElement && $element->localName === $localName) {
                return $element;
            }
        }

        return null;
    }

    private function firstChildElementByLocalName(\DOMElement $element, string $localName): ?\DOMElement
    {
        foreach ($element->childNodes as $child) {
            if ($child instanceof \DOMElement && $child->localName === $localName) {
                return $child;
            }
        }

        return null;
    }

    private function attr(\DOMElement $element, string $namespace, string $name): string
    {
        if ($element->hasAttributeNS($namespace, $name)) {
            return $element->getAttributeNS($namespace, $name);
        }

        return '';
    }

    /**
     * @param list<AstNode> $inlines
     */
    private function plainText(array $inlines): string
    {
        $text = '';
        foreach ($inlines as $inline) {
            $text .= match ($inline->type) {
                'text', 'code' => (string) $inline->attr('text', ''),
                'linebreak', 'softbreak' => ' ',
                'image' => (string) $inline->attr('alt', ''),
                default => $this->plainText($inline->children),
            };
        }

        return trim(preg_replace('/\s+/u', ' ', $text) ?? $text);
    }

    /**
     * @param list<AstNode> $nodes
     * @return list<AstNode>
     */
    private function mergeAdjacentText(array $nodes): array
    {
        $merged = [];
        foreach ($nodes as $node) {
            $last = $merged[array_key_last($merged)] ?? null;
            if ($last instanceof AstNode && $last->type === 'text' && $node->type === 'text') {
                array_pop($merged);
                $merged[] = new AstNode('text', ['text' => (string) $last->attr('text', '') . (string) $node->attr('text', '')]);
                continue;
            }
            $merged[] = $node;
        }

        return $merged;
    }
}
