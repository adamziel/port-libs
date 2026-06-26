<?php

declare(strict_types=1);

namespace PortLibs\Pandoc;

final class PptxReader
{
    private const REL_NS = 'http://schemas.openxmlformats.org/package/2006/relationships';
    private const P_NS = 'http://schemas.openxmlformats.org/presentationml/2006/main';
    private const A_NS = 'http://schemas.openxmlformats.org/drawingml/2006/main';
    private const C_NS = 'http://schemas.openxmlformats.org/drawingml/2006/chart';
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
                $context = $this->slideContext($package, $slidePath, $slideRels);
                $notesPath = $this->notesSlidePath($slidePath, $slideRels);
                $slides[] = $this->slide(
                    $slideXml,
                    $slidePath,
                    $slideRels,
                    $index + 1,
                    $reference['slideId'],
                    is_string($notesPath) ? ($package->read($notesPath) ?? '') : '',
                    $context,
                    $package
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
                return ZipOpcPackage::normalizePathStrict(ltrim($target, '/'));
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
    private function slide(
        string $slideXml,
        string $slidePath,
        array $relationships,
        int $number,
        string $slideId,
        string $notesXml,
        array $context,
        ZipOpcPackage $package
    ): AstNode
    {
        $dom = $this->loadXml($slideXml, 'PPTX slide ' . $slidePath);
        $shapeTree = $this->firstElementByLocalName($dom, 'spTree');
        $title = $shapeTree instanceof \DOMElement ? $this->slideTitle($shapeTree) : '';
        if ($title === '') {
            $title = (string) ($context['layoutTitle'] ?? $context['masterTitle'] ?? '');
        }
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
                foreach ($this->shapeBlocks($child, $relationships, $slidePath, true, $context, $package) as $block) {
                    $blocks[] = $block;
                }
            }
        }

        foreach ($this->notesBlocks($notesXml) as $block) {
            $blocks[] = $block;
        }

        $background = $this->backgroundColor($dom, is_array($context['themeColors'] ?? null) ? $context['themeColors'] : [])
            ?: (string) ($context['layoutBackgroundColor'] ?? $context['masterBackgroundColor'] ?? '');
        $attributes = [
            'data-pandoc-source' => 'pptx',
            'data-pptx-slide-number' => (string) $number,
            'data-pptx-slide-id' => $slideId,
            'data-pptx-slide-path' => $slidePath,
            'data-pptx-layout-path' => (string) ($context['layoutPath'] ?? ''),
            'data-pptx-master-path' => (string) ($context['masterPath'] ?? ''),
            'data-pptx-theme-path' => (string) ($context['themePath'] ?? ''),
        ];
        if ($background !== '') {
            $attributes['data-pptx-background-color'] = $background;
            $attributes['style'] = 'background-color:' . $background;
        }

        return new AstNode('div', [
            'id' => 'slide-' . $number . '-content',
            'classes' => ['pptx-slide'],
            'attributes' => $attributes,
        ], $blocks);
    }

    /**
     * @param array<string, array{target:string,type:string,mode:string}> $relationships
     * @return list<AstNode>
     */
    private function shapeBlocks(
        \DOMElement $shape,
        array $relationships,
        string $slidePath,
        bool $skipTitle,
        array $context,
        ZipOpcPackage $package
    ): array
    {
        if ($shape->localName === 'sp') {
            if ($skipTitle && $this->isTitlePlaceholder($shape)) {
                return [];
            }
            $textBody = $this->firstChildElementByLocalName($shape, 'txBody');
            if (!$textBody instanceof \DOMElement) {
                return $this->inheritedPlaceholderBlocks($shape, $context);
            }

            $paragraphs = $this->drawingParagraphs($textBody, $relationships, $slidePath);
            if ($paragraphs === []) {
                return $this->inheritedPlaceholderBlocks($shape, $context);
            }

            return $this->paragraphsToBlocks($paragraphs);
        }

        if ($shape->localName === 'pic') {
            $image = $this->picture($shape, $relationships, $slidePath);

            return $image instanceof AstNode ? [new AstNode('paragraph', [], [$image])] : [];
        }

        if ($shape->localName === 'graphicFrame') {
            $table = $this->graphicFrameTable($shape, $relationships, $slidePath, $context);
            if ($table instanceof AstNode) {
                return [$table];
            }
            $chart = $this->graphicFrameChart($shape, $relationships, $slidePath, $package);
            if ($chart instanceof AstNode) {
                return [$chart];
            }
        }

        if ($shape->localName === 'grpSp') {
            $blocks = [];
            foreach ($shape->childNodes as $child) {
                if ($child instanceof \DOMElement) {
                    array_push($blocks, ...$this->shapeBlocks($child, $relationships, $slidePath, $skipTitle, $context, $package));
                }
            }

            return $blocks;
        }

        return [];
    }

    /**
     * @return list<AstNode>
     */
    private function inheritedPlaceholderBlocks(\DOMElement $shape, array $context): array
    {
        $keys = $this->placeholderLookupKeys($shape);
        if ($keys === []) {
            return [];
        }

        foreach (['layoutPlaceholders', 'masterPlaceholders'] as $bucket) {
            $placeholders = $context[$bucket] ?? [];
            if (!is_array($placeholders)) {
                continue;
            }
            foreach ($keys as $key) {
                $blocks = $placeholders[$key] ?? null;
                if (is_array($blocks) && $blocks !== []) {
                    return $blocks;
                }
            }
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

        $attributes = array_merge(
            ['data-pptx-relationship-id' => $embedId],
            $this->shapeGeometryAttributes($picture, 'data-pptx-picture'),
            $this->pictureCropAttributes($picture)
        );

        return new AstNode('image', [
            'url' => $url,
            'title' => $name,
            'alt' => $alt !== '' ? $alt : $name,
            'attributes' => $attributes,
        ]);
    }

    /**
     * @return array<string, string>
     */
    private function shapeGeometryAttributes(\DOMElement $shape, string $prefix): array
    {
        $properties = $this->firstChildElementByLocalName($shape, 'spPr');
        if (!$properties instanceof \DOMElement) {
            return [];
        }
        $transform = $this->firstChildElementByLocalName($properties, 'xfrm');
        if (!$transform instanceof \DOMElement) {
            return [];
        }

        $attrs = [];
        if ($transform->hasAttribute('rot')) {
            $attrs[$prefix . '-rotation'] = $transform->getAttribute('rot');
        }
        $offset = $this->firstChildElementByLocalName($transform, 'off');
        if ($offset instanceof \DOMElement) {
            foreach (['x', 'y'] as $axis) {
                if ($offset->hasAttribute($axis)) {
                    $attrs[$prefix . '-offset-' . $axis . '-emu'] = $offset->getAttribute($axis);
                }
            }
        }
        $extent = $this->firstChildElementByLocalName($transform, 'ext');
        if ($extent instanceof \DOMElement) {
            foreach (['cx', 'cy'] as $axis) {
                if ($extent->hasAttribute($axis)) {
                    $attrs[$prefix . '-extent-' . $axis . '-emu'] = $extent->getAttribute($axis);
                }
            }
        }

        return $attrs;
    }

    /**
     * @return array<string, string>
     */
    private function pictureCropAttributes(\DOMElement $picture): array
    {
        foreach ($picture->getElementsByTagNameNS(self::A_NS, 'srcRect') as $crop) {
            if (!$crop instanceof \DOMElement) {
                continue;
            }
            $attrs = [];
            foreach ([
                'l' => 'left',
                'r' => 'right',
                't' => 'top',
                'b' => 'bottom',
            ] as $attribute => $side) {
                if ($crop->hasAttribute($attribute)) {
                    $attrs['data-pptx-crop-' . $side] = $crop->getAttribute($attribute);
                }
            }

            return $attrs;
        }

        return [];
    }

    /**
     * @param array<string, array{target:string,type:string,mode:string}> $relationships
     */
    private function graphicFrameTable(\DOMElement $frame, array $relationships, string $slidePath, array $context): ?AstNode
    {
        $themeColors = is_array($context['themeColors'] ?? null) ? $context['themeColors'] : [];
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
                    if ($this->drawingBoolAttribute($cellNode, 'hMerge') || $this->drawingBoolAttribute($cellNode, 'vMerge')) {
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
                    $attrs = ['text' => $text];
                    $colspan = ctype_digit($cellNode->getAttribute('gridSpan')) ? (int) $cellNode->getAttribute('gridSpan') : 1;
                    $rowspan = ctype_digit($cellNode->getAttribute('rowSpan')) ? (int) $cellNode->getAttribute('rowSpan') : 1;
                    if ($colspan > 1) {
                        $attrs['colspan'] = $colspan;
                    }
                    if ($rowspan > 1) {
                        $attrs['rowspan'] = $rowspan;
                    }
                    $htmlAttributes = $this->tableCellHtmlAttributes($cellNode, $textBody, $themeColors);
                    if ($htmlAttributes !== []) {
                        $attrs['htmlAttributes'] = $htmlAttributes;
                    }
                    $cells[] = new AstNode('table_cell', $attrs, $paragraphs);
                }
                if ($cells !== []) {
                    $rowAttrs = [];
                    if (ctype_digit($rowNode->getAttribute('h'))) {
                        $rowAttrs['htmlAttributes'] = ['data-pptx-row-height-emu' => $rowNode->getAttribute('h')];
                    }
                    $rows[] = new AstNode('table_row', $rowAttrs, $cells);
                }
            }
            if ($rows === []) {
                return null;
            }

            $head = array_shift($rows);

            $tableAttrs = $this->tableHtmlAttributes($table, $themeColors);

            return new AstNode('table', $tableAttrs !== [] ? ['htmlAttributes' => $tableAttrs] : [], [
                new AstNode('table_head', [], [$head]),
                new AstNode('table_body', [], $rows),
            ]);
        }

        return null;
    }

    /**
     * @param array<string, string> $themeColors
     * @return array<string, string>
     */
    private function tableCellHtmlAttributes(\DOMElement $cell, ?\DOMElement $textBody, array $themeColors): array
    {
        $properties = $this->firstChildElementByLocalName($cell, 'tcPr');
        if (!$properties instanceof \DOMElement) {
            return [];
        }

        $attrs = [];
        $styles = [];
        $fill = $this->solidFillColor($properties, $themeColors);
        if ($fill !== '') {
            $attrs['data-pptx-fill-color'] = $fill;
            $styles[] = 'background-color:' . $fill;
        }
        $border = $this->tableCellBorderColor($properties, $themeColors);
        if ($border !== '') {
            $attrs['data-pptx-border-color'] = $border;
            $styles[] = 'border-color:' . $border;
        }
        $alignment = $textBody instanceof \DOMElement ? $this->paragraphAlignment($textBody) : '';
        if ($alignment !== '') {
            $styles[] = 'text-align:' . $alignment;
        }
        foreach ([
            'marL' => 'left',
            'marR' => 'right',
            'marT' => 'top',
            'marB' => 'bottom',
        ] as $attribute => $side) {
            if (!ctype_digit($properties->getAttribute($attribute))) {
                continue;
            }
            $emu = (int) $properties->getAttribute($attribute);
            $attrs['data-pptx-margin-' . $side . '-emu'] = (string) $emu;
            $styles[] = 'padding-' . $side . ':' . $this->emuToPoints($emu) . 'pt';
        }
        $direction = $properties->getAttribute('vert');
        if ($direction !== '') {
            $attrs['data-pptx-text-direction'] = $direction;
        }
        $anchor = $properties->getAttribute('anchor');
        $vertical = match ($anchor) {
            'mid', 'ctr' => 'middle',
            'b', 'bottom' => 'bottom',
            't', 'top' => 'top',
            default => '',
        };
        if ($vertical !== '') {
            $styles[] = 'vertical-align:' . $vertical;
        }
        foreach ($this->tableCellBorderDetails($properties, $themeColors) as $side => $details) {
            $color = (string) ($details['color'] ?? '');
            $width = (string) ($details['width'] ?? '');
            $dash = (string) ($details['dash'] ?? '');
            if ($color !== '') {
                $attrs['data-pptx-border-' . $side . '-color'] = $color;
            }
            if ($width !== '') {
                $attrs['data-pptx-border-' . $side . '-width-emu'] = $width;
            }
            if ($dash !== '') {
                $attrs['data-pptx-border-' . $side . '-dash'] = $dash;
            }
            if ($color !== '' || $width !== '') {
                $styles[] = 'border-' . $side . ':' . ($width !== '' ? $this->emuToPoints((int) $width) : '1') . 'pt solid ' . ($color !== '' ? $color : 'currentColor');
            }
        }
        if ($styles !== []) {
            $attrs['style'] = implode(';', $styles);
        }

        return $attrs;
    }

    /**
     * @param array<string, string> $themeColors
     * @return array<string, string>
     */
    private function tableHtmlAttributes(\DOMElement $table, array $themeColors): array
    {
        $attrs = [];
        $grid = [];
        foreach ($table->getElementsByTagNameNS(self::A_NS, 'gridCol') as $column) {
            if ($column instanceof \DOMElement && ctype_digit($column->getAttribute('w'))) {
                $grid[] = $column->getAttribute('w');
            }
        }
        if ($grid !== []) {
            $attrs['data-pptx-grid-columns-emu'] = implode(',', $grid);
        }
        $properties = $this->firstChildElementByLocalName($table, 'tblPr');
        if ($properties instanceof \DOMElement) {
            foreach (['firstRow', 'lastRow', 'firstCol', 'lastCol', 'bandRow', 'bandCol'] as $flag) {
                if ($properties->hasAttribute($flag)) {
                    $attrs['data-pptx-table-' . strtolower($flag)] = $this->drawingBoolAttribute($properties, $flag) ? 'true' : 'false';
                }
            }
            $styleId = '';
            foreach ($properties->getElementsByTagNameNS(self::A_NS, 'tableStyleId') as $style) {
                if ($style instanceof \DOMElement) {
                    $styleId = trim($style->textContent);
                    break;
                }
            }
            if ($styleId !== '') {
                $attrs['data-pptx-table-style-id'] = $styleId;
            }
            $fill = $this->solidFillColor($properties, $themeColors);
            if ($fill !== '') {
                $attrs['data-pptx-table-fill-color'] = $fill;
            }
        }

        return $attrs;
    }

    private function emuToPoints(int $emu): string
    {
        return rtrim(rtrim(number_format($emu / 12700, 3, '.', ''), '0'), '.');
    }

    /**
     * @param array<string, string> $themeColors
     */
    private function tableCellBorderColor(\DOMElement $properties, array $themeColors): string
    {
        foreach (['lnL', 'lnR', 'lnT', 'lnB'] as $localName) {
            $line = $this->firstChildElementByLocalName($properties, $localName);
            if ($line instanceof \DOMElement) {
                $color = $this->solidFillColor($line, $themeColors);
                if ($color !== '') {
                    return $color;
                }
            }
        }

        return '';
    }

    /**
     * @param array<string, string> $themeColors
     * @return array<string, array{color:string,width:string,dash:string}>
     */
    private function tableCellBorderDetails(\DOMElement $properties, array $themeColors): array
    {
        $details = [];
        foreach ([
            'lnL' => 'left',
            'lnR' => 'right',
            'lnT' => 'top',
            'lnB' => 'bottom',
        ] as $localName => $side) {
            $line = $this->firstChildElementByLocalName($properties, $localName);
            if (!$line instanceof \DOMElement) {
                continue;
            }
            $dash = '';
            foreach ($line->getElementsByTagNameNS(self::A_NS, 'prstDash') as $dashNode) {
                if ($dashNode instanceof \DOMElement) {
                    $dash = $dashNode->getAttribute('val');
                    break;
                }
            }
            $details[$side] = [
                'color' => $this->solidFillColor($line, $themeColors),
                'width' => ctype_digit($line->getAttribute('w')) ? $line->getAttribute('w') : '',
                'dash' => $dash,
            ];
        }

        return $details;
    }

    private function paragraphAlignment(\DOMElement $textBody): string
    {
        foreach ($textBody->getElementsByTagNameNS(self::A_NS, 'pPr') as $properties) {
            if (!$properties instanceof \DOMElement) {
                continue;
            }
            return match ($properties->getAttribute('algn')) {
                'ctr' => 'center',
                'r' => 'right',
                'l' => 'left',
                'just', 'justLow' => 'justify',
                default => '',
            };
        }

        return '';
    }

    /**
     * @param array<string, array{target:string,type:string,mode:string}> $relationships
     */
    private function graphicFrameChart(\DOMElement $frame, array $relationships, string $slidePath, ZipOpcPackage $package): ?AstNode
    {
        foreach ($frame->getElementsByTagNameNS(self::C_NS, 'chart') as $chart) {
            if (!$chart instanceof \DOMElement) {
                continue;
            }
            $relationshipId = $this->attr($chart, self::R_NS, 'id');
            $relationship = $relationships[$relationshipId] ?? null;
            if (!is_array($relationship) || ($relationship['target'] ?? '') === '') {
                continue;
            }
            $chartPath = $this->resolveRelationshipTarget($slidePath, (string) $relationship['target']);
            $chartXml = $package->read($chartPath);
            if (!is_string($chartXml) || $chartXml === '') {
                continue;
            }

            return $this->chartBlock($chartXml, $chartPath, $relationshipId);
        }

        return null;
    }

    private function chartBlock(string $chartXml, string $chartPath, string $relationshipId): AstNode
    {
        $dom = $this->loadXml($chartXml, 'PPTX chart ' . $chartPath);
        $titleNode = $dom->getElementsByTagNameNS(self::C_NS, 'title')->item(0);
        $title = $titleNode instanceof \DOMElement ? $this->drawingText($titleNode) : '';
        $series = $this->chartSeries($dom);
        $chartTypes = $this->chartTypes($dom);
        $children = [];
        if ($title !== '') {
            $children[] = new AstNode('heading', ['level' => 3, 'text' => $title], [new AstNode('text', ['text' => $title])]);
        }
        $table = $this->chartSeriesTable($series);
        if ($table instanceof AstNode) {
            $children[] = $table;
        } elseif ($title !== '') {
            $children[] = new AstNode('paragraph', ['text' => 'Chart: ' . $title], [new AstNode('text', ['text' => 'Chart: ' . $title])]);
        }

        return new AstNode('div', [
            'classes' => ['pptx-chart'],
            'attributes' => [
                'data-pandoc-source' => 'pptx-chart',
                'data-pptx-chart-path' => $chartPath,
                'data-pptx-relationship-id' => $relationshipId,
                'data-pptx-chart-types' => implode(',', $chartTypes),
                'data-pptx-chart-series-count' => (string) count($series),
            ],
        ], $children);
    }

    /**
     * @return list<string>
     */
    private function chartTypes(\DOMDocument $dom): array
    {
        $types = [];
        foreach ($dom->getElementsByTagNameNS(self::C_NS, 'plotArea') as $plotArea) {
            if (!$plotArea instanceof \DOMElement) {
                continue;
            }
            foreach ($plotArea->childNodes as $child) {
                if ($child instanceof \DOMElement && str_ends_with($child->localName, 'Chart')) {
                    $types[] = $child->localName;
                }
            }
        }

        return array_values(array_unique($types));
    }

    /**
     * @return list<array{name:string,categories:list<string>,values:list<string>}>
     */
    private function chartSeries(\DOMDocument $dom): array
    {
        $series = [];
        foreach ($dom->getElementsByTagNameNS(self::C_NS, 'ser') as $ser) {
            if (!$ser instanceof \DOMElement) {
                continue;
            }
            $name = $this->chartTextValue($this->firstChildElementByLocalName($ser, 'tx')) ?: 'Series ' . (count($series) + 1);
            $categories = $this->chartCacheValues($this->firstChildElementByLocalName($ser, 'cat'));
            $values = $this->chartCacheValues($this->firstChildElementByLocalName($ser, 'val'));
            if ($categories === []) {
                $categories = $this->chartCacheValues($this->firstChildElementByLocalName($ser, 'xVal'));
            }
            if ($values === []) {
                $values = $this->chartCacheValues($this->firstChildElementByLocalName($ser, 'yVal'));
            }
            $series[] = [
                'name' => $name,
                'categories' => $categories,
                'values' => $values,
            ];
        }

        return $series;
    }

    /**
     * @param list<array{name:string,categories:list<string>,values:list<string>}> $series
     */
    private function chartSeriesTable(array $series): ?AstNode
    {
        if ($series === []) {
            return null;
        }
        $rowCount = 0;
        foreach ($series as $item) {
            $rowCount = max($rowCount, count($item['categories']), count($item['values']));
        }
        if ($rowCount === 0) {
            return null;
        }

        $headCells = [new AstNode('table_cell', ['text' => 'Category'], [new AstNode('plain', [], [new AstNode('text', ['text' => 'Category'])])])];
        foreach ($series as $item) {
            $headCells[] = new AstNode('table_cell', [
                'text' => $item['name'],
                'htmlAttributes' => ['data-pptx-chart-series' => $item['name']],
            ], [new AstNode('plain', [], [new AstNode('text', ['text' => $item['name']])])]);
        }
        $bodyRows = [];
        for ($index = 0; $index < $rowCount; $index++) {
            $category = $series[0]['categories'][$index] ?? (string) ($index + 1);
            $cells = [new AstNode('table_cell', [
                'text' => $category,
                'htmlAttributes' => ['data-pptx-chart-category-index' => (string) $index],
            ], [new AstNode('plain', [], [new AstNode('text', ['text' => $category])])])];
            foreach ($series as $item) {
                $value = $item['values'][$index] ?? '';
                $cells[] = new AstNode('table_cell', [
                    'text' => $value,
                    'htmlAttributes' => [
                        'data-pptx-chart-series' => $item['name'],
                        'data-pptx-chart-point-index' => (string) $index,
                    ],
                ], [$value === '' ? new AstNode('plain') : new AstNode('plain', [], [new AstNode('text', ['text' => $value])])]);
            }
            $bodyRows[] = new AstNode('table_row', [], $cells);
        }

        return new AstNode('table', [], [
            new AstNode('table_head', [], [new AstNode('table_row', [], $headCells)]),
            new AstNode('table_body', [], $bodyRows),
        ]);
    }

    private function chartTextValue(?\DOMElement $element): string
    {
        if (!$element instanceof \DOMElement) {
            return '';
        }
        foreach ($element->getElementsByTagNameNS(self::C_NS, 'v') as $value) {
            if ($value instanceof \DOMElement && trim($value->textContent) !== '') {
                return trim($value->textContent);
            }
        }

        return $this->drawingText($element);
    }

    /**
     * @return list<string>
     */
    private function chartCacheValues(?\DOMElement $element): array
    {
        if (!$element instanceof \DOMElement) {
            return [];
        }
        $values = [];
        foreach ($element->getElementsByTagNameNS(self::C_NS, 'pt') as $point) {
            if (!$point instanceof \DOMElement) {
                continue;
            }
            $value = $this->chartTextValue($point);
            if ($value !== '') {
                $values[] = $value;
            }
        }

        return $values;
    }

    private function drawingBoolAttribute(\DOMElement $element, string $name): bool
    {
        $value = strtolower($element->getAttribute($name));

        return in_array($value, ['1', 'true', 'on'], true);
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

    /**
     * @param array<string, array{target:string,type:string,mode:string}> $slideRels
     * @return array<string, mixed>
     */
    private function slideContext(ZipOpcPackage $package, string $slidePath, array $slideRels): array
    {
        $context = [
            'layoutPath' => '',
            'masterPath' => '',
            'themePath' => '',
            'layoutTitle' => '',
            'masterTitle' => '',
            'themeColors' => [],
            'layoutPlaceholders' => [],
            'masterPlaceholders' => [],
            'layoutBackgroundColor' => '',
            'masterBackgroundColor' => '',
        ];

        $layoutPath = $this->relationshipTargetByType($slidePath, $slideRels, '/slideLayout');
        if ($layoutPath === null) {
            return $context;
        }
        $context['layoutPath'] = $layoutPath;
        $layoutXml = $package->read($layoutPath) ?? '';
        $layoutRels = $this->relationships($package->read($this->relationshipsPath($layoutPath)) ?? '');
        if ($layoutXml !== '') {
            $context['layoutTitle'] = $this->placeholderTitle($layoutXml, 'PPTX slide layout ' . $layoutPath);
            $context['layoutPlaceholders'] = $this->placeholderBlocksByKey($layoutXml, 'PPTX slide layout ' . $layoutPath, $layoutRels, $layoutPath);
        }
        $masterPath = $this->relationshipTargetByType($layoutPath, $layoutRels, '/slideMaster');
        if ($masterPath !== null) {
            $context['masterPath'] = $masterPath;
            $masterXml = $package->read($masterPath) ?? '';
            $masterRels = $this->relationships($package->read($this->relationshipsPath($masterPath)) ?? '');
            if ($masterXml !== '') {
                $context['masterTitle'] = $this->placeholderTitle($masterXml, 'PPTX slide master ' . $masterPath);
                $context['masterPlaceholders'] = $this->placeholderBlocksByKey($masterXml, 'PPTX slide master ' . $masterPath, $masterRels, $masterPath);
            }
            $themePath = $this->relationshipTargetByType($masterPath, $masterRels, '/theme');
            if ($themePath !== null) {
                $context['themePath'] = $themePath;
            }
        }
        if ($context['themePath'] === '') {
            $themePath = $this->relationshipTargetByType($layoutPath, $layoutRels, '/theme');
            if ($themePath !== null) {
                $context['themePath'] = $themePath;
            }
        }
        if ($context['themePath'] !== '') {
            $themeXml = $package->read((string) $context['themePath']) ?? '';
            if ($themeXml !== '') {
                $context['themeColors'] = $this->themeColors($themeXml);
            }
        }
        if ($layoutXml !== '') {
            $context['layoutBackgroundColor'] = $this->backgroundColorFromXml($layoutXml, 'PPTX slide layout ' . $layoutPath, $context['themeColors']);
        }
        if (($masterXml ?? '') !== '') {
            $context['masterBackgroundColor'] = $this->backgroundColorFromXml($masterXml, 'PPTX slide master ' . ($context['masterPath'] ?? ''), $context['themeColors']);
        }

        return $context;
    }

    private function placeholderTitle(string $xml, string $label): string
    {
        try {
            $dom = $this->loadXml($xml, $label);
        } catch (\InvalidArgumentException) {
            return '';
        }
        $shapeTree = $this->firstElementByLocalName($dom, 'spTree');
        if (!$shapeTree instanceof \DOMElement) {
            return '';
        }

        return $this->slideTitle($shapeTree);
    }

    /**
     * @param array<string, array{target:string,type:string,mode:string}> $relationships
     * @return array<string, list<AstNode>>
     */
    private function placeholderBlocksByKey(string $xml, string $label, array $relationships, string $partPath): array
    {
        try {
            $dom = $this->loadXml($xml, $label);
        } catch (\InvalidArgumentException) {
            return [];
        }
        $shapeTree = $this->firstElementByLocalName($dom, 'spTree');
        if (!$shapeTree instanceof \DOMElement) {
            return [];
        }

        $placeholders = [];
        foreach ($shapeTree->childNodes as $shape) {
            if (!$shape instanceof \DOMElement || $shape->localName !== 'sp') {
                continue;
            }
            $keys = $this->placeholderLookupKeys($shape);
            if ($keys === []) {
                continue;
            }
            $textBody = $this->firstChildElementByLocalName($shape, 'txBody');
            if (!$textBody instanceof \DOMElement) {
                continue;
            }
            $blocks = $this->paragraphsToBlocks($this->drawingParagraphs($textBody, $relationships, $partPath));
            if ($blocks === []) {
                continue;
            }
            foreach ($keys as $key) {
                $placeholders[$key] ??= $blocks;
            }
        }

        return $placeholders;
    }

    /**
     * @return list<string>
     */
    private function placeholderLookupKeys(\DOMElement $shape): array
    {
        foreach ($shape->getElementsByTagNameNS(self::P_NS, 'ph') as $placeholder) {
            if (!$placeholder instanceof \DOMElement) {
                continue;
            }
            $type = $placeholder->getAttribute('type') !== '' ? $placeholder->getAttribute('type') : 'obj';
            $index = $placeholder->getAttribute('idx');
            $keys = [];
            if ($type !== '' && $index !== '') {
                $keys[] = 'type:' . $type . ';idx:' . $index;
            }
            if ($index !== '') {
                $keys[] = 'idx:' . $index;
            }
            if ($type !== '') {
                $keys[] = 'type:' . $type;
            }

            return array_values(array_unique($keys));
        }

        return [];
    }

    /**
     * @return array<string, string>
     */
    private function themeColors(string $xml): array
    {
        try {
            $dom = $this->loadXml($xml, 'PPTX theme');
        } catch (\InvalidArgumentException) {
            return [];
        }
        $scheme = $this->firstElementByLocalName($dom, 'clrScheme');
        if (!$scheme instanceof \DOMElement) {
            return [];
        }

        $colors = [];
        foreach ($scheme->childNodes as $child) {
            if (!$child instanceof \DOMElement) {
                continue;
            }
            $color = $this->colorChoice($child, []);
            if ($color !== '') {
                $colors[$child->localName] = $color;
            }
        }

        return $colors;
    }

    /**
     * @param array<string, string> $themeColors
     */
    private function backgroundColorFromXml(string $xml, string $label, array $themeColors): string
    {
        try {
            $dom = $this->loadXml($xml, $label);
        } catch (\InvalidArgumentException) {
            return '';
        }

        return $this->backgroundColor($dom, $themeColors);
    }

    /**
     * @param array<string, string> $themeColors
     */
    private function backgroundColor(\DOMDocument $dom, array $themeColors): string
    {
        foreach ($dom->getElementsByTagNameNS(self::P_NS, 'bg') as $background) {
            if ($background instanceof \DOMElement) {
                $color = $this->solidFillColor($background, $themeColors);
                if ($color !== '') {
                    return $color;
                }
            }
        }

        return '';
    }

    /**
     * @param array<string, string> $themeColors
     */
    private function solidFillColor(\DOMElement $element, array $themeColors): string
    {
        foreach ($element->getElementsByTagNameNS(self::A_NS, 'solidFill') as $fill) {
            if ($fill instanceof \DOMElement) {
                return $this->colorChoice($fill, $themeColors);
            }
        }

        return '';
    }

    /**
     * @param array<string, string> $themeColors
     */
    private function colorChoice(\DOMElement $element, array $themeColors): string
    {
        foreach ($element->childNodes as $child) {
            if (!$child instanceof \DOMElement) {
                continue;
            }
            if ($child->localName === 'srgbClr') {
                $value = strtoupper($child->getAttribute('val'));
                return preg_match('/^[0-9A-F]{6}$/', $value) === 1 ? '#' . $value : '';
            }
            if ($child->localName === 'schemeClr') {
                $key = $child->getAttribute('val');

                return $themeColors[$key] ?? '';
            }
            if ($child->localName === 'sysClr') {
                $value = strtoupper($child->getAttribute('lastClr'));
                return preg_match('/^[0-9A-F]{6}$/', $value) === 1 ? '#' . $value : '';
            }
        }

        return '';
    }

    /**
     * @param array<string, array{target:string,type:string,mode:string}> $relationships
     */
    private function relationshipTargetByType(string $sourcePath, array $relationships, string $typeSuffix): ?string
    {
        foreach ($relationships as $relationship) {
            $type = (string) ($relationship['type'] ?? '');
            $target = (string) ($relationship['target'] ?? '');
            if ($target !== '' && str_ends_with($type, $typeSuffix)) {
                return $this->resolveRelationshipTarget($sourcePath, $target);
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
            return ZipOpcPackage::normalizePathStrict(ltrim($target, '/'));
        }

        return ZipOpcPackage::normalizePathStrict(ZipOpcPackage::dirname($sourcePartPath) . '/' . $target);
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
