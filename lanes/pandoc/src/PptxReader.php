<?php

declare(strict_types=1);

namespace PortLibs\Pandoc;

final class PptxReader
{
    private const OFFICE_DOCUMENT_RELATIONSHIP = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument';
    private const RELATIONSHIP_NAMESPACE = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships';
    private const MAX_XML_PART_BYTES = 8_388_608;

    public function read(string $bytes): AstNode
    {
        $package = ZipPackage::fromString($bytes);

        return $this->readPackage($package, strlen($bytes));
    }

    private function readPackage(ZipPackage $package, int $sourceBytes): AstNode
    {
        $rootRelationships = OpcRelationships::fromPackage($package, '/');
        $presentationRelationship = $this->presentationRelationship($rootRelationships);
        $presentationPart = OpcPackagePath::stripQueryAndFragment($rootRelationships->resolveTarget($presentationRelationship));
        $presentation = $this->loadPackageXml($package, $presentationPart, 'PPTX presentation');
        $slides = $this->parsePresentationSlides($presentation);
        $presentationRelationships = $this->relationshipsOrEmpty($package, $presentationPart);

        $blocks = [];
        $slideReviews = [];
        foreach ($slides as $slide) {
            $relationship = $presentationRelationships->byId($slide['relationshipId']);
            if (!$relationship instanceof OpcRelationship) {
                throw new \RuntimeException('PPTX slide relationship not found: ' . $slide['relationshipId']);
            }
            if ($relationship->isExternal()) {
                throw new \RuntimeException('PPTX external slide relationships are not supported');
            }

            $slidePart = OpcPackagePath::stripQueryAndFragment($presentationRelationships->resolveTarget($relationship));
            $slideDocument = $this->loadPackageXml($package, $slidePart, 'PPTX slide ' . $slide['index']);
            $slideRelationships = $this->relationshipsOrEmpty($package, $slidePart);
            $slideBlocks = $this->slideToBlocks($package, $slide['index'], $slideDocument, $slideRelationships);
            foreach ($slideBlocks as $block) {
                $blocks[] = $block;
            }

            $slideReviews[] = [
                'index' => $slide['index'],
                'relationshipId' => $slide['relationshipId'],
                'partName' => ltrim($slidePart, '/'),
                'blockCount' => count($slideBlocks),
            ];
        }

        return new AstNode('document', [
            'sourceFormat' => 'pptx',
            'meta' => [],
            'pptx' => [
                'reader' => self::class,
                'readerScope' => 'pinned-pandoc-pptx-reader',
                'sourceBytes' => $sourceBytes,
                'entryCount' => count($package->names()),
                'presentationPart' => ltrim($presentationPart, '/'),
                'slideCount' => count($slides),
                'slides' => $slideReviews,
                'payloadExposurePolicy' => 'xml-text-and-media-reference-only',
                'upstreamEvidence' => [
                    'denominator' => 1,
                    'fixtures' => [
                        'test/pptx-reader/basic.pptx',
                        'test/pptx-reader/basic.native',
                    ],
                    'source' => 'Pandoc 912bfa5e src/Text/Pandoc/Readers/Pptx.hs and src/Text/Pandoc/Readers/Pptx/{Parse,Slides,Shapes,SmartArt}.hs',
                ],
            ],
        ], $blocks);
    }

    private function presentationRelationship(OpcRelationships $relationships): OpcRelationship
    {
        foreach ($relationships->all() as $relationship) {
            if (str_ends_with($relationship->type, '/officeDocument') && str_contains($relationship->target, 'presentation')) {
                return $relationship;
            }
        }

        $relationship = $relationships->firstOfType(self::OFFICE_DOCUMENT_RELATIONSHIP);
        if ($relationship instanceof OpcRelationship) {
            return $relationship;
        }

        throw new \RuntimeException('PPTX package does not declare a presentation relationship');
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
        return XmlHtmlDom::loadXmlDocument($package->read($partName, self::MAX_XML_PART_BYTES), $label, false);
    }

    /**
     * @return list<array{index:int, relationshipId:string}>
     */
    private function parsePresentationSlides(\DOMDocument $document): array
    {
        $root = XmlHtmlDom::rootElement($document, 'presentation');
        if (!$root instanceof \DOMElement) {
            throw new \RuntimeException('PPTX presentation XML must have a presentation root');
        }

        $slideIdList = $this->firstChildElement($root, 'sldIdLst');
        if (!$slideIdList instanceof \DOMElement) {
            return [];
        }

        $slides = [];
        $index = 1;
        foreach ($this->childElements($slideIdList, 'sldId') as $slideIdElement) {
            $relationshipId = $this->relationshipId($slideIdElement, 'id');
            if ($relationshipId === '') {
                throw new \RuntimeException('PPTX presentation slide is missing r:id');
            }

            $slides[] = ['index' => $index, 'relationshipId' => $relationshipId];
            $index++;
        }

        return $slides;
    }

    /**
     * @return list<AstNode>
     */
    private function slideToBlocks(ZipPackage $package, int $slideIndex, \DOMDocument $document, OpcRelationships $slideRelationships): array
    {
        $root = XmlHtmlDom::rootElement($document, 'sld');
        if (!$root instanceof \DOMElement) {
            throw new \RuntimeException('PPTX slide XML must have a slide root');
        }

        $title = $this->slideTitle($root);
        if ($title === '') {
            $title = 'Slide ' . $slideIndex;
        }

        $blocks = [
            new AstNode('heading', ['level' => 2, 'id' => 'slide-' . $slideIndex, 'text' => $title], $this->textInlines($title)),
        ];
        $spTree = $this->shapeTree($root);
        if (!$spTree instanceof \DOMElement) {
            return $blocks;
        }

        foreach ($this->childElements($spTree, null) as $shapeElement) {
            if ($this->isTitlePlaceholder($shapeElement)) {
                continue;
            }
            foreach ($this->shapeToBlocks($package, $shapeElement, $slideRelationships) as $block) {
                $blocks[] = $block;
            }
        }

        return $blocks;
    }

    private function shapeTree(\DOMElement $slide): ?\DOMElement
    {
        $commonSlideData = $this->firstChildElement($slide, 'cSld');

        return $commonSlideData instanceof \DOMElement ? $this->firstChildElement($commonSlideData, 'spTree') : null;
    }

    private function slideTitle(\DOMElement $slide): string
    {
        $spTree = $this->shapeTree($slide);
        if (!$spTree instanceof \DOMElement) {
            return '';
        }

        foreach ($this->childElements($spTree, 'sp') as $shapeElement) {
            if ($this->isTitlePlaceholder($shapeElement)) {
                return $this->drawingText($shapeElement);
            }
        }

        return '';
    }

    /**
     * @return list<AstNode>
     */
    private function shapeToBlocks(ZipPackage $package, \DOMElement $shapeElement, OpcRelationships $slideRelationships): array
    {
        if ($shapeElement->localName === 'sp') {
            $textBody = $this->firstChildElement($shapeElement, 'txBody');

            return $textBody instanceof \DOMElement ? $this->paragraphsToBlocks($this->parseParagraphs($textBody)) : [];
        }

        if ($shapeElement->localName === 'pic') {
            $image = $this->pictureNode($shapeElement, $slideRelationships);

            return $image instanceof AstNode ? [new AstNode('paragraph', [], [$image])] : [];
        }

        if ($shapeElement->localName !== 'graphicFrame') {
            return [];
        }

        $graphicData = $this->graphicDataElement($shapeElement);
        if (!$graphicData instanceof \DOMElement) {
            return [];
        }

        $uri = $graphicData->getAttribute('uri');
        if (str_contains($uri, 'table')) {
            $table = $this->firstDescendantElement($graphicData, 'tbl');

            return $table instanceof \DOMElement ? [$this->tableNode($table)] : [];
        }
        if (str_contains($uri, 'diagram')) {
            $diagram = $this->diagramNode($package, $graphicData, $slideRelationships);

            return $diagram instanceof AstNode ? [$diagram] : [];
        }

        return [$this->paragraph('[Graphic: other: ' . $uri . ']')];
    }

    /**
     * @param list<array{level:int, bullet:bool, text:string}> $paragraphs
     * @return list<AstNode>
     */
    private function paragraphsToBlocks(array $paragraphs): array
    {
        if ($paragraphs === []) {
            return [];
        }

        $hasBullets = false;
        foreach ($paragraphs as $paragraph) {
            if ($paragraph['bullet']) {
                $hasBullets = true;
                break;
            }
        }
        if (!$hasBullets) {
            return array_map(fn (array $paragraph): AstNode => $this->paragraph($paragraph['text']), $paragraphs);
        }

        $blocks = [];
        $index = 0;
        $count = count($paragraphs);
        while ($index < $count) {
            $paragraph = $paragraphs[$index];
            if (!$paragraph['bullet']) {
                $blocks[] = $this->paragraph($paragraph['text']);
                $index++;
                continue;
            }

            $items = [];
            $level = $paragraph['level'];
            while ($index < $count && $paragraphs[$index]['bullet'] && $paragraphs[$index]['level'] === $level) {
                $items[] = new AstNode('list_item', [], [
                    new AstNode('plain', [], $this->textInlines($paragraphs[$index]['text'])),
                ]);
                $index++;
            }
            $blocks[] = new AstNode('bullet_list', [], $items);
        }

        return $blocks;
    }

    /**
     * @return list<array{level:int, bullet:bool, text:string}>
     */
    private function parseParagraphs(\DOMElement $textBody): array
    {
        return array_map(
            fn (\DOMElement $paragraphElement): array => [
                'level' => $this->paragraphLevel($paragraphElement),
                'bullet' => $this->paragraphHasBullet($paragraphElement),
                'text' => $this->drawingText($paragraphElement),
            ],
            $this->childElements($textBody, 'p')
        );
    }

    private function paragraphLevel(\DOMElement $paragraphElement): int
    {
        $properties = $this->firstChildElement($paragraphElement, 'pPr');
        if (!$properties instanceof \DOMElement) {
            return 0;
        }

        $level = $properties->getAttribute('lvl');

        return preg_match('/^\d+$/', $level) === 1 ? (int) $level : 0;
    }

    private function paragraphHasBullet(\DOMElement $paragraphElement): bool
    {
        $properties = $this->firstChildElement($paragraphElement, 'pPr');
        if ($properties instanceof \DOMElement && $this->firstChildElement($properties, 'buChar') instanceof \DOMElement) {
            return true;
        }

        foreach ($paragraphElement->getElementsByTagName('*') as $descendant) {
            if ($descendant instanceof \DOMElement && $descendant->localName === 'sym' && str_contains($descendant->getAttribute('typeface'), 'Wingdings')) {
                return true;
            }
        }

        return false;
    }

    private function pictureNode(\DOMElement $pictureElement, OpcRelationships $slideRelationships): ?AstNode
    {
        $nonVisual = $this->firstChildElement($pictureElement, 'nvPicPr');
        $properties = $nonVisual instanceof \DOMElement ? $this->firstChildElement($nonVisual, 'cNvPr') : null;
        $title = $properties instanceof \DOMElement ? $properties->getAttribute('name') : '';
        $alt = $properties instanceof \DOMElement ? $properties->getAttribute('descr') : '';
        $blipFill = $this->firstChildElement($pictureElement, 'blipFill');
        $blip = $blipFill instanceof \DOMElement ? $this->firstChildElement($blipFill, 'blip') : null;
        if (!$blip instanceof \DOMElement) {
            return null;
        }

        $relationshipId = $this->relationshipId($blip, 'embed');
        if ($relationshipId === '') {
            return null;
        }

        $relationship = $slideRelationships->byId($relationshipId);
        if (!$relationship instanceof OpcRelationship || $relationship->isExternal()) {
            return null;
        }

        $mediaPart = OpcPackagePath::stripQueryAndFragment($slideRelationships->resolveTarget($relationship));

        return new AstNode('image', [
            'url' => ltrim($mediaPart, '/'),
            'src' => ltrim($mediaPart, '/'),
            'title' => $title,
            'alt' => $alt,
        ], $this->textInlines($alt));
    }

    private function graphicDataElement(\DOMElement $graphicFrame): ?\DOMElement
    {
        $graphic = $this->firstChildElement($graphicFrame, 'graphic');

        return $graphic instanceof \DOMElement ? $this->firstChildElement($graphic, 'graphicData') : null;
    }

    private function tableNode(\DOMElement $tableElement): AstNode
    {
        $rows = [];
        foreach ($this->childElements($tableElement, 'tr') as $rowElement) {
            $row = [];
            foreach ($this->childElements($rowElement, 'tc') as $cellElement) {
                $row[] = $this->drawingText($cellElement);
            }
            $rows[] = $row;
        }

        $header = array_shift($rows) ?? [];

        return new AstNode('table', [
            'caption' => '',
            'alignments' => array_fill(0, count($header), 'default'),
            'pptxTable' => true,
        ], [
            new AstNode('table_head', [], [$this->tableRow($header, true)]),
            new AstNode('table_body', [], array_map(fn (array $row): AstNode => $this->tableRow($row, false), $rows)),
        ]);
    }

    /**
     * @param list<string> $row
     */
    private function tableRow(array $row, bool $header): AstNode
    {
        return new AstNode('table_row', ['header' => $header], array_map(
            fn (string $text): AstNode => new AstNode('table_cell', ['header' => $header, 'text' => $text], [
                new AstNode('plain', [], $this->textInlines($text)),
            ]),
            $row
        ));
    }

    private function diagramNode(ZipPackage $package, \DOMElement $graphicData, OpcRelationships $slideRelationships): ?AstNode
    {
        $relIds = $this->firstChildElement($graphicData, 'relIds');
        if (!$relIds instanceof \DOMElement) {
            return $this->paragraph('[Diagram parse error: diagram-no-relIds]');
        }

        $dataRelId = $this->relationshipId($relIds, 'dm');
        $layoutRelId = $this->relationshipId($relIds, 'lo');
        if ($dataRelId === '' || $layoutRelId === '') {
            return $this->paragraph('[Diagram parse error: diagram-missing-rels]');
        }

        $dataRelationship = $slideRelationships->byId($dataRelId);
        $layoutRelationship = $slideRelationships->byId($layoutRelId);
        if (!$dataRelationship instanceof OpcRelationship || !$layoutRelationship instanceof OpcRelationship || $dataRelationship->isExternal() || $layoutRelationship->isExternal()) {
            return $this->paragraph('[Diagram parse error: diagram-missing-rels]');
        }

        $dataPart = OpcPackagePath::stripQueryAndFragment($slideRelationships->resolveTarget($dataRelationship));
        $layoutPart = OpcPackagePath::stripQueryAndFragment($slideRelationships->resolveTarget($layoutRelationship));
        $dataDocument = $this->loadPackageXml($package, $dataPart, 'PPTX SmartArt data');
        $layoutDocument = $this->loadPackageXml($package, $layoutPart, 'PPTX SmartArt layout');
        $dataRoot = XmlHtmlDom::rootElement($dataDocument, 'dataModel');
        $layoutRoot = XmlHtmlDom::rootElement($layoutDocument, 'layoutDef');
        if (!$dataRoot instanceof \DOMElement || !$layoutRoot instanceof \DOMElement) {
            return $this->paragraph('[Diagram parse error: diagram-invalid-xml]');
        }

        $layoutType = $this->diagramLayoutType($layoutRoot);
        $children = [];
        foreach ($this->diagramNodes($dataRoot) as $node) {
            $children[] = new AstNode('paragraph', [], [
                new AstNode('strong', [], $this->textInlines($node['text'])),
            ]);
            if ($node['children'] !== []) {
                $children[] = new AstNode('bullet_list', [], array_map(
                    fn (string $child): AstNode => new AstNode('list_item', [], [
                        new AstNode('plain', [], $this->textInlines($child)),
                    ]),
                    $node['children']
                ));
            }
        }

        return new AstNode('div', [
            'classes' => ['smartart', $layoutType],
            'attributes' => ['layout' => $layoutType],
        ], $children);
    }

    private function diagramLayoutType(\DOMElement $layoutRoot): string
    {
        $uniqueId = $layoutRoot->getAttribute('uniqueId');
        if ($uniqueId !== '') {
            $position = strrpos($uniqueId, '/');

            return $position === false ? $uniqueId : substr($uniqueId, $position + 1);
        }

        $title = $this->firstChildElement($layoutRoot, 'title');

        return $title instanceof \DOMElement && $title->hasAttribute('val') ? $title->getAttribute('val') : 'unknown';
    }

    /**
     * @return list<array{text:string, children:list<string>}>
     */
    private function diagramNodes(\DOMElement $dataRoot): array
    {
        $pointList = $this->firstChildElement($dataRoot, 'ptLst');
        $connectionList = $this->firstChildElement($dataRoot, 'cxnLst');
        if (!$pointList instanceof \DOMElement || !$connectionList instanceof \DOMElement) {
            return [];
        }

        $nodeText = [];
        foreach ($this->childElements($pointList, 'pt') as $pointElement) {
            $modelId = $pointElement->getAttribute('modelId');
            $textElement = $this->firstChildElement($pointElement, 't');
            $text = $textElement instanceof \DOMElement ? $this->allDescendantText($textElement) : '';
            if ($modelId !== '' && trim($text) !== '') {
                $nodeText[$modelId] = $text;
            }
        }

        $childrenByParent = [];
        foreach ($this->childElements($connectionList, 'cxn') as $connectionElement) {
            if ($connectionElement->hasAttribute('type')) {
                continue;
            }
            $sourceId = $connectionElement->getAttribute('srcId');
            $destinationId = $connectionElement->getAttribute('destId');
            if ($sourceId === '' || $destinationId === '') {
                continue;
            }
            $childrenByParent[$sourceId] ??= [];
            $childrenByParent[$sourceId][] = $destinationId;
        }

        $parentIds = array_keys($childrenByParent);
        sort($parentIds, SORT_STRING);
        $nodes = [];
        foreach ($parentIds as $parentId) {
            $text = $nodeText[$parentId] ?? '';
            if ($text === '') {
                continue;
            }

            $children = [];
            foreach ($childrenByParent[$parentId] as $childId) {
                $childText = $nodeText[$childId] ?? '';
                if ($childText !== '') {
                    $children[] = $childText;
                }
            }
            $nodes[] = ['text' => $text, 'children' => $children];
        }

        return $nodes;
    }

    private function isTitlePlaceholder(\DOMElement $shapeElement): bool
    {
        if ($shapeElement->localName !== 'sp') {
            return false;
        }

        $nonVisual = $this->firstChildElement($shapeElement, 'nvSpPr');
        $nonVisualProperties = $nonVisual instanceof \DOMElement ? $this->firstChildElement($nonVisual, 'nvPr') : null;
        $placeholder = $nonVisualProperties instanceof \DOMElement ? $this->firstChildElement($nonVisualProperties, 'ph') : null;
        if (!$placeholder instanceof \DOMElement) {
            return false;
        }

        $type = $placeholder->getAttribute('type');

        return $type === 'title' || $type === 'ctrTitle';
    }

    private function paragraph(string $text): AstNode
    {
        return new AstNode('paragraph', ['text' => $text], $this->textInlines($text));
    }

    /**
     * @return list<AstNode>
     */
    private function textInlines(string $text): array
    {
        return $text === '' ? [] : [new AstNode('text', ['text' => $text])];
    }

    private function drawingText(\DOMElement $element): string
    {
        $texts = [];
        foreach ($element->getElementsByTagName('*') as $child) {
            if ($child instanceof \DOMElement && $child->localName === 't' && $child->textContent !== '') {
                $texts[] = $child->textContent;
            }
        }

        return implode(' ', $texts);
    }

    private function allDescendantText(\DOMElement $element): string
    {
        $texts = [];
        foreach ($element->childNodes as $child) {
            if ($child instanceof \DOMText || $child instanceof \DOMCdataSection) {
                if (($child->nodeValue ?? '') !== '') {
                    $texts[] = $child->nodeValue ?? '';
                }
                continue;
            }
            if ($child instanceof \DOMElement) {
                $text = $this->allDescendantText($child);
                if ($text !== '') {
                    $texts[] = $text;
                }
            }
        }

        return implode(' ', $texts);
    }

    private function relationshipId(\DOMElement $element, string $localName): string
    {
        $value = $element->getAttributeNS(self::RELATIONSHIP_NAMESPACE, $localName);
        if ($value !== '') {
            return $value;
        }

        foreach ($element->attributes ?? [] as $attribute) {
            if ($attribute instanceof \DOMAttr && $attribute->localName === $localName) {
                return $attribute->value;
            }
        }

        return '';
    }

    /**
     * @return list<\DOMElement>
     */
    private function childElements(\DOMElement $parent, ?string $localName): array
    {
        $children = [];
        foreach ($parent->childNodes as $child) {
            if ($child instanceof \DOMElement && ($localName === null || $child->localName === $localName)) {
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
}
