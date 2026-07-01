<?php

declare(strict_types=1);

namespace PortLibs\Pandoc;

final class PptxWriter
{
    private const GENERATED_TIMESTAMP = '1980-01-01T00:00:00Z';
    private const GENERATED_DOS_TIME = 0;
    private const GENERATED_DOS_DATE = 33;
    private const SLIDE_WIDTH_EMU = 12192000;
    private const SLIDE_HEIGHT_EMU = 6858000;

    private const NS_A = 'http://schemas.openxmlformats.org/drawingml/2006/main';
    private const NS_CP = 'http://schemas.openxmlformats.org/package/2006/metadata/core-properties';
    private const NS_CUSTOM_PROPERTIES = 'http://schemas.openxmlformats.org/officeDocument/2006/custom-properties';
    private const NS_DC = 'http://purl.org/dc/elements/1.1/';
    private const NS_DCTERMS = 'http://purl.org/dc/terms/';
    private const NS_EP = 'http://schemas.openxmlformats.org/officeDocument/2006/extended-properties';
    private const NS_P = 'http://schemas.openxmlformats.org/presentationml/2006/main';
    private const NS_PIC = 'http://schemas.openxmlformats.org/drawingml/2006/picture';
    private const NS_R = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships';
    private const NS_VT = 'http://schemas.openxmlformats.org/officeDocument/2006/docPropsVTypes';
    private const NS_XSI = 'http://www.w3.org/2001/XMLSchema-instance';

    private const REL_OFFICE_DOCUMENT = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument';
    private const REL_CORE_PROPERTIES = 'http://schemas.openxmlformats.org/package/2006/relationships/metadata/core-properties';
    private const REL_EXTENDED_PROPERTIES = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships/extended-properties';
    private const REL_CUSTOM_PROPERTIES = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships/custom-properties';
    private const REL_SLIDE = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships/slide';
    private const REL_SLIDE_LAYOUT = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships/slideLayout';
    private const REL_SLIDE_MASTER = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships/slideMaster';
    private const REL_NOTES_MASTER = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships/notesMaster';
    private const REL_NOTES_SLIDE = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships/notesSlide';
    private const REL_THEME = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships/theme';
    private const REL_TABLE_STYLES = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships/tableStyles';
    private const REL_IMAGE = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships/image';
    private const REL_HYPERLINK = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships/hyperlink';

    private const CT_CORE_PROPERTIES = 'application/vnd.openxmlformats-package.core-properties+xml';
    private const CT_EXTENDED_PROPERTIES = 'application/vnd.openxmlformats-officedocument.extended-properties+xml';
    private const CT_CUSTOM_PROPERTIES = 'application/vnd.openxmlformats-officedocument.custom-properties+xml';
    private const CT_PRESENTATION = 'application/vnd.openxmlformats-officedocument.presentationml.presentation.main+xml';
    private const CT_SLIDE = 'application/vnd.openxmlformats-officedocument.presentationml.slide+xml';
    private const CT_SLIDE_LAYOUT = 'application/vnd.openxmlformats-officedocument.presentationml.slideLayout+xml';
    private const CT_SLIDE_MASTER = 'application/vnd.openxmlformats-officedocument.presentationml.slideMaster+xml';
    private const CT_NOTES_MASTER = 'application/vnd.openxmlformats-officedocument.presentationml.notesMaster+xml';
    private const CT_NOTES_SLIDE = 'application/vnd.openxmlformats-officedocument.presentationml.notesSlide+xml';
    private const CT_TABLE_STYLES = 'application/vnd.openxmlformats-officedocument.presentationml.tableStyles+xml';
    private const CT_THEME = 'application/vnd.openxmlformats-officedocument.theme+xml';
    private const CT_RELATIONSHIPS = 'application/vnd.openxmlformats-package.relationships+xml';

    /** @var array<string, int> */
    private const CORE_PART_ORDER = [
        '[Content_Types].xml' => 0,
        '_rels/.rels' => 1,
        'docProps/core.xml' => 2,
        'docProps/app.xml' => 3,
        'docProps/custom.xml' => 4,
        'ppt/presentation.xml' => 5,
        'ppt/_rels/presentation.xml.rels' => 6,
        'ppt/slideMasters/slideMaster1.xml' => 100,
        'ppt/slideMasters/_rels/slideMaster1.xml.rels' => 101,
        'ppt/slideLayouts/slideLayout1.xml' => 102,
        'ppt/slideLayouts/_rels/slideLayout1.xml.rels' => 103,
        'ppt/theme/theme1.xml' => 104,
        'ppt/tableStyles.xml' => 105,
        'ppt/notesMasters/notesMaster1.xml' => 106,
        'ppt/notesMasters/_rels/notesMaster1.xml.rels' => 107,
    ];

    /** @var list<array{name:string, data:string, contentType:string, extension:string, source:string}> */
    private array $mediaParts = [];

    /** @var array<string, array{name:string, data:string, contentType:string, extension:string, source:string}> */
    private array $mediaPartsByKey = [];

    /** @var list<array{id:int, blocks:list<AstNode>}> */
    private array $endnotes = [];

    /** @var array<int, int> */
    private array $endnoteIdsByObjectId = [];

    private int $nextMediaId = 1;
    private int $endnotesSlideNumber = 0;

    /**
     * @param array<string, mixed> $options
     */
    public function __construct(private readonly array $options = [])
    {
    }

    public function write(AstNode $document): string
    {
        return ZipPackage::build($this->packageParts($document));
    }

    /**
     * @return list<array{name:string, data:string, modifiedDosTime:int, modifiedDosDate:int}>
     */
    public function packageParts(AstNode $document): array
    {
        if ($document->type !== 'document') {
            throw new \InvalidArgumentException('PPTX writer expects a document node');
        }

        $this->resetState();
        $metadata = $this->metadata($document);
        $slides = $this->slides($document, $metadata);
        $this->appendEndnotesSlide($slides, $document);
        $slideParts = [];
        $slideRelationshipParts = [];
        $notesSlideParts = [];
        $notesSlideRelationshipParts = [];
        $notesSlideCount = 0;
        foreach ($slides as $index => $slide) {
            $slideNumber = $index + 1;
            $relationships = [[
                'id' => 'rIdLayout',
                'type' => self::REL_SLIDE_LAYOUT,
                'target' => '../slideLayouts/slideLayout1.xml',
            ]];
            if ($slide['notes'] !== []) {
                $notesSlideCount++;
                $relationships[] = [
                    'id' => 'rIdNotes',
                    'type' => self::REL_NOTES_SLIDE,
                    'target' => '../notesSlides/notesSlide' . $notesSlideCount . '.xml',
                ];
                $notesSlideParts[] = [
                    'name' => 'ppt/notesSlides/notesSlide' . $notesSlideCount . '.xml',
                    'data' => $this->notesSlideXml($slide['notes'], $slideNumber),
                ];
                $notesSlideRelationshipParts[] = [
                    'name' => 'ppt/notesSlides/_rels/notesSlide' . $notesSlideCount . '.xml.rels',
                    'data' => $this->notesSlideRelationshipsXml($slideNumber),
                ];
            }
            $slideParts[] = [
                'name' => 'ppt/slides/slide' . $slideNumber . '.xml',
                'data' => $this->slideXml($slide, $slideNumber, $relationships),
            ];
            $slideRelationshipParts[] = [
                'name' => 'ppt/slides/_rels/slide' . $slideNumber . '.xml.rels',
                'data' => $this->relationshipsXml($relationships),
            ];
        }

        $parts = [
            ['name' => '[Content_Types].xml', 'data' => $this->contentTypesXml(count($slides), $notesSlideCount)],
            ['name' => '_rels/.rels', 'data' => $this->rootRelationshipsXml()],
            ['name' => 'docProps/core.xml', 'data' => $this->corePropertiesXml($metadata)],
            ['name' => 'docProps/app.xml', 'data' => $this->extendedPropertiesXml($metadata, count($slides), $notesSlideCount)],
            ['name' => 'docProps/custom.xml', 'data' => $this->customPropertiesXml($metadata)],
            ['name' => 'ppt/presentation.xml', 'data' => $this->presentationXml(count($slides), $notesSlideCount)],
            ['name' => 'ppt/_rels/presentation.xml.rels', 'data' => $this->presentationRelationshipsXml(count($slides), $notesSlideCount)],
            ['name' => 'ppt/slideMasters/slideMaster1.xml', 'data' => $this->slideMasterXml()],
            ['name' => 'ppt/slideMasters/_rels/slideMaster1.xml.rels', 'data' => $this->slideMasterRelationshipsXml()],
            ['name' => 'ppt/slideLayouts/slideLayout1.xml', 'data' => $this->slideLayoutXml()],
            ['name' => 'ppt/slideLayouts/_rels/slideLayout1.xml.rels', 'data' => $this->slideLayoutRelationshipsXml()],
            ['name' => 'ppt/theme/theme1.xml', 'data' => $this->themeXml()],
            ['name' => 'ppt/tableStyles.xml', 'data' => $this->tableStylesXml()],
        ];
        if ($notesSlideCount > 0) {
            $parts[] = ['name' => 'ppt/notesMasters/notesMaster1.xml', 'data' => $this->notesMasterXml()];
            $parts[] = ['name' => 'ppt/notesMasters/_rels/notesMaster1.xml.rels', 'data' => $this->notesMasterRelationshipsXml()];
        }

        foreach ($slideParts as $part) {
            $parts[] = $part;
        }
        foreach ($slideRelationshipParts as $part) {
            $parts[] = $part;
        }
        foreach ($notesSlideParts as $part) {
            $parts[] = $part;
        }
        foreach ($notesSlideRelationshipParts as $part) {
            $parts[] = $part;
        }
        foreach ($this->mediaParts as $mediaPart) {
            $parts[] = ['name' => $mediaPart['name'], 'data' => $mediaPart['data']];
        }

        return $this->normalizePackageParts($parts);
    }

    private function resetState(): void
    {
        $this->mediaParts = [];
        $this->mediaPartsByKey = [];
        $this->endnotes = [];
        $this->endnoteIdsByObjectId = [];
        $this->nextMediaId = 1;
        $this->endnotesSlideNumber = 0;
    }

    /**
     * @param array<string, mixed> $metadata
     * @return list<array{title:string, titleInlines:list<AstNode>, blocks:list<AstNode>, notes:list<AstNode>, backgroundImage:?string}>
     */
    private function slides(AstNode $document, array $metadata): array
    {
        $slideLevel = $this->slideLevel($document);
        $slides = [];
        $current = null;
        $pendingMetadataNotes = $this->metadataSpeakerNotes($document);

        foreach ($document->children as $block) {
            if ($block->type === 'horizontal_rule') {
                if ($current !== null) {
                    $slides[] = $current;
                    $current = null;
                }
                continue;
            }

            if ($block->type === 'heading' && (int) $block->attr('level', 1) <= $slideLevel) {
                if ($current !== null) {
                    $slides[] = $current;
                }
                $title = $this->blockText($block);
                $current = $this->newSlide(
                    $title !== '' ? $title : 'Slide ' . (count($slides) + 1),
                    $this->backgroundImageForBlock($block),
                    $title !== '' ? $block->children : null
                );
                if ($pendingMetadataNotes !== []) {
                    array_push($current['notes'], ...$pendingMetadataNotes);
                    $pendingMetadataNotes = [];
                }
                continue;
            }

            if ($slideLevel === 0 && $block->type === 'heading' && $current === null) {
                $title = $this->blockText($block);
                $current = $this->newSlide(
                    $title !== '' ? $title : 'Slide ' . (count($slides) + 1),
                    $this->backgroundImageForBlock($block),
                    $title !== '' ? $block->children : null
                );
                if ($pendingMetadataNotes !== []) {
                    array_push($current['notes'], ...$pendingMetadataNotes);
                    $pendingMetadataNotes = [];
                }
                continue;
            }

            if ($this->isSpeakerNotesBlock($block)) {
                if ($current === null) {
                    $current = $this->newSlide((string) $metadata['title']);
                    if ($pendingMetadataNotes !== []) {
                        array_push($current['notes'], ...$pendingMetadataNotes);
                        $pendingMetadataNotes = [];
                    }
                }
                array_push($current['notes'], ...$block->children);
                continue;
            }

            if ($this->isImageOnlyBlock($block) && $current !== null && $this->isImageOnlySlide($current)) {
                $slides[] = $current;
                $current = null;
            }
            if ($current === null) {
                $current = $this->isImageOnlyBlock($block)
                    ? $this->newSlide('', null, [])
                    : $this->newSlide((string) $metadata['title']);
                if ($pendingMetadataNotes !== []) {
                    array_push($current['notes'], ...$pendingMetadataNotes);
                    $pendingMetadataNotes = [];
                }
            }
            $current['blocks'][] = $block;
        }

        if ($current === null) {
            $current = $this->newSlide((string) $metadata['title']);
        }
        if ($pendingMetadataNotes !== []) {
            array_push($current['notes'], ...$pendingMetadataNotes);
        }
        $slides[] = $current;

        return $slides;
    }

    /**
     * @param list<AstNode>|null $titleInlines
     * @return array{title:string, titleInlines:list<AstNode>, blocks:list<AstNode>, notes:list<AstNode>, backgroundImage:?string}
     */
    private function newSlide(string $title, ?string $backgroundImage = null, ?array $titleInlines = null): array
    {
        return [
            'title' => $title,
            'titleInlines' => $titleInlines ?? $this->textInlines($title),
            'blocks' => [],
            'notes' => [],
            'backgroundImage' => $backgroundImage,
        ];
    }

    private function isImageOnlySlide(array $slide): bool
    {
        return $slide['titleInlines'] === []
            && count($slide['blocks']) === 1
            && $this->isImageOnlyBlock($slide['blocks'][0]);
    }

    private function isImageOnlyBlock(AstNode $block): bool
    {
        if ($block->type === 'image') {
            return true;
        }
        if ($block->type !== 'plain' && $block->type !== 'paragraph') {
            return false;
        }

        $imageCount = 0;
        foreach ($block->children as $child) {
            if ($child->type === 'image') {
                $imageCount++;
                continue;
            }
            if ($this->blockText($child) !== '') {
                return false;
            }
        }

        return $imageCount > 0;
    }

    /**
     * @param list<array{title:string, titleInlines:list<AstNode>, blocks:list<AstNode>, notes:list<AstNode>, backgroundImage:?string}> $slides
     */
    private function appendEndnotesSlide(array &$slides, AstNode $document): void
    {
        $this->collectEndnotesFromSlides($slides);
        if ($this->endnotes === []) {
            return;
        }

        $this->endnotesSlideNumber = count($slides) + 1;
        $slide = $this->newSlide($this->endnotesTitle($document));
        $slide['blocks'] = $this->endnoteBlocks();
        $slides[] = $slide;
    }

    /**
     * @param list<array{title:string, titleInlines:list<AstNode>, blocks:list<AstNode>, notes:list<AstNode>, backgroundImage:?string}> $slides
     */
    private function collectEndnotesFromSlides(array $slides): void
    {
        foreach ($slides as $slide) {
            foreach ($slide['blocks'] as $block) {
                $this->collectEndnotesFromNode($block);
            }
        }
    }

    private function collectEndnotesFromNode(AstNode $node): void
    {
        if ($node->type === 'note') {
            $objectId = spl_object_id($node);
            if (!isset($this->endnoteIdsByObjectId[$objectId])) {
                $noteId = count($this->endnotes) + 1;
                $this->endnoteIdsByObjectId[$objectId] = $noteId;
                $this->endnotes[] = ['id' => $noteId, 'blocks' => $node->children];
            }

            return;
        }

        foreach ($node->children as $child) {
            $this->collectEndnotesFromNode($child);
        }
    }

    private function endnotesTitle(AstNode $document): string
    {
        $meta = $document->attr('meta', []);
        $meta = is_array($meta) ? $meta : [];

        return $this->optionString('notes-title')
            ?? $this->optionString('notesTitle')
            ?? $this->metaString($meta, ['notes-title', 'notesTitle'])
            ?? 'Notes';
    }

    /**
     * @return list<AstNode>
     */
    private function endnoteBlocks(): array
    {
        $blocks = [];
        foreach ($this->endnotes as $endnote) {
            $noteBlocks = $endnote['blocks'];
            $prefix = new AstNode('text', ['text' => $endnote['id'] . '. ']);
            if ($noteBlocks !== [] && ($noteBlocks[0]->type === 'plain' || $noteBlocks[0]->type === 'paragraph')) {
                $blocks[] = new AstNode('paragraph', [], array_merge([$prefix], $noteBlocks[0]->children));
                array_push($blocks, ...array_slice($noteBlocks, 1));
                continue;
            }

            $blocks[] = new AstNode('paragraph', [], [$prefix]);
            array_push($blocks, ...$noteBlocks);
        }

        return $blocks;
    }

    private function endnoteId(AstNode $note): ?int
    {
        return $this->endnoteIdsByObjectId[spl_object_id($note)] ?? null;
    }

    private function slideLevel(AstNode $document): int
    {
        foreach (['slideLevel', 'writerSlideLevel'] as $key) {
            if (isset($this->options[$key]) && is_numeric($this->options[$key])) {
                return max(0, (int) $this->options[$key]);
            }
        }

        $meta = $document->attr('meta', []);
        if (is_array($meta)) {
            foreach (['slideLevel', 'writerSlideLevel'] as $key) {
                if (isset($meta[$key]) && is_numeric($meta[$key])) {
                    return max(0, (int) $meta[$key]);
                }
            }
        }

        return 2;
    }

    private function backgroundImageForBlock(AstNode $block): ?string
    {
        $attributes = $block->attr('attributes', []);
        if (!is_array($attributes)) {
            return null;
        }

        foreach (['background-image', 'data-background-image'] as $key) {
            if (isset($attributes[$key]) && is_scalar($attributes[$key])) {
                $source = trim((string) $attributes[$key]);
                if ($source !== '') {
                    return $source;
                }
            }
        }

        return null;
    }

    /**
     * @param array{title:string, titleInlines:list<AstNode>, blocks:list<AstNode>, notes:list<AstNode>, backgroundImage:?string} $slide
     * @param list<array{id:string, type:string, target:string, targetMode?:string}> $relationships
     */
    private function slideXml(array $slide, int $slideNumber, array &$relationships): string
    {
        $shapeId = 2;
        $slot = 0;
        $backgroundXml = $this->slideBackgroundXml($slide['backgroundImage'], $relationships);
        $shapes = [];
        if ($slide['titleInlines'] !== []) {
            $shapes[] = $this->textShapeXml(
                $shapeId++,
                'Title ' . $slideNumber,
                [$this->paragraphXml($slide['titleInlines'], [], $relationships)],
                685800,
                381000,
                10820400,
                914400,
                'title'
            );
        }

        foreach ($slide['blocks'] as $block) {
            foreach ($this->blockShapes($block, $shapeId, $slot, $relationships) as $shapeXml) {
                $shapes[] = $shapeXml;
            }
        }

        return '<?xml version="1.0" encoding="UTF-8"?>' . "\n"
            . '<p:sld xmlns:a="' . self::NS_A . '" xmlns:p="' . self::NS_P . '" xmlns:r="' . self::NS_R . '">' . "\n"
            . '  <p:cSld>' . "\n"
            . $backgroundXml
            . '    <p:spTree>' . "\n"
            . '    <p:nvGrpSpPr><p:cNvPr id="1" name=""/><p:cNvGrpSpPr/><p:nvPr/></p:nvGrpSpPr>' . "\n"
            . '    <p:grpSpPr><a:xfrm><a:off x="0" y="0"/><a:ext cx="' . self::SLIDE_WIDTH_EMU . '" cy="' . self::SLIDE_HEIGHT_EMU . '"/><a:chOff x="0" y="0"/><a:chExt cx="' . self::SLIDE_WIDTH_EMU . '" cy="' . self::SLIDE_HEIGHT_EMU . '"/></a:xfrm></p:grpSpPr>' . "\n"
            . implode("\n", $shapes) . "\n"
            . '    </p:spTree>' . "\n"
            . '  </p:cSld>' . "\n"
            . '  <p:clrMapOvr><a:masterClrMapping/></p:clrMapOvr>' . "\n"
            . '</p:sld>' . "\n";
    }

    /**
     * @param list<array{id:string, type:string, target:string, targetMode?:string}> $relationships
     * @return list<string>
     */
    private function blockShapes(AstNode $block, int &$shapeId, int &$slot, array &$relationships): array
    {
        if ($this->isSpeakerNotesBlock($block)) {
            return [];
        }

        if ($block->type === 'div' || $block->type === 'block_quote') {
            $shapes = [];
            foreach ($block->children as $child) {
                foreach ($this->blockShapes($child, $shapeId, $slot, $relationships) as $shape) {
                    $shapes[] = $shape;
                }
            }

            return $shapes;
        }

        if ($block->type === 'raw_block' && $this->isOpenXmlRaw($block)) {
            $xml = $this->rawOpenXml($block);
            if ($xml === '') {
                return [];
            }

            $this->advanceShapeIdPastRawOpenXml($xml, $shapeId);
            $slot++;

            return [$this->indentRawOpenXml($xml, 4)];
        }

        if ($block->type === 'code_block') {
            $text = (string) $block->attr('text', '');

            return $text === ''
                ? []
                : [$this->textShapeXml(
                    $shapeId++,
                    $this->shapeName($block, 'Code'),
                    [$this->paragraphXml([$this->codeInline($text)], [], $relationships)],
                    ...$this->bodyRect($slot++)
                )];
        }

        if ($block->type === 'table') {
            return [$this->tableShapeXml($block, $shapeId++, $slot++)];
        }

        if ($block->type === 'bullet_list' || $block->type === 'ordered_list') {
            $paragraphs = $this->listParagraphXmls($block, $block->type === 'ordered_list', $relationships);

            return $paragraphs === []
                ? []
                : [$this->textShapeXml($shapeId++, $this->shapeName($block, 'List'), $paragraphs, ...$this->bodyRect($slot++))];
        }

        if ($block->type === 'plain' || $block->type === 'paragraph' || $block->type === 'heading') {
            $shapes = [];
            $nonImageInlines = [];
            foreach ($block->children as $child) {
                if ($child->type === 'image') {
                    $imageSlot = $slot++;
                    $picture = $this->pictureShapeXml($child, $shapeId++, $imageSlot, $relationships);
                    if ($picture !== null) {
                        $shapes[] = $picture;
                        $caption = $this->figureCaption($child);
                        if ($caption !== '') {
                            $shapes[] = $this->textShapeXml(
                                $shapeId++,
                                'Caption',
                                [$this->paragraphXml($this->textInlines($caption), [], $relationships)],
                                ...$this->captionRect($imageSlot)
                            );
                        }
                    } else {
                        $fallback = $this->imageFallbackText($child);
                        if ($fallback !== '') {
                            $nonImageInlines[] = new AstNode('text', ['text' => $fallback]);
                        }
                    }
                    continue;
                }
                $nonImageInlines[] = $child;
            }

            if ($this->inlineText($nonImageInlines) !== '') {
                $shapes[] = $this->textShapeXml(
                    $shapeId++,
                    $this->shapeName($block, $block->type === 'heading' ? 'Heading' : 'Text'),
                    [$this->paragraphXml($nonImageInlines, [], $relationships)],
                    ...$this->bodyRect($slot++)
                );
            }

            return $shapes;
        }

        if ($block->type === 'image') {
            $imageSlot = $slot++;
            $picture = $this->pictureShapeXml($block, $shapeId++, $imageSlot, $relationships);
            if ($picture !== null) {
                $caption = $this->figureCaption($block);
                if ($caption !== '') {
                    return [
                        $picture,
                        $this->textShapeXml(
                            $shapeId++,
                            'Caption',
                            [$this->paragraphXml($this->textInlines($caption), [], $relationships)],
                            ...$this->captionRect($imageSlot)
                        ),
                    ];
                }
            }

            return $picture === null ? [] : [$picture];
        }

        $text = $this->blockText($block);
        if ($text === '') {
            return [];
        }

        return [$this->textShapeXml(
            $shapeId++,
            $this->shapeName($block, 'Text'),
            [$this->paragraphXml($this->textInlines($text), [], $relationships)],
            ...$this->bodyRect($slot++)
        )];
    }

    /**
     * @return array{0:int,1:int,2:int,3:int}
     */
    private function bodyRect(int $slot): array
    {
        $y = 1524000 + ($slot * 838200);
        $height = max(609600, min(1219200, self::SLIDE_HEIGHT_EMU - $y - 304800));

        return [914400, $y, 10363200, $height];
    }

    /**
     * @return array{0:int,1:int,2:int,3:int}
     */
    private function captionRect(int $slot): array
    {
        [$x, $y, $cx, $cy] = $this->bodyRect($slot);

        return [$x, $y + min($cy, 1371600) + 152400, $cx, 457200];
    }

    /**
     * @param list<string> $paragraphs
     */
    private function textShapeXml(int $id, string $name, array $paragraphs, int $x, int $y, int $cx, int $cy, ?string $placeholder = null): string
    {
        $placeholderXml = $placeholder === null ? '' : '<p:ph type="' . $this->xml($placeholder) . '"/>';
        $paragraphXml = $paragraphs === [] ? '<a:p/>' : implode('', $paragraphs);

        return '    <p:sp>' . "\n"
            . '      <p:nvSpPr><p:cNvPr id="' . $id . '" name="' . $this->xml($name) . '"/><p:cNvSpPr txBox="1"/><p:nvPr>' . $placeholderXml . '</p:nvPr></p:nvSpPr>' . "\n"
            . '      <p:spPr><a:xfrm><a:off x="' . $x . '" y="' . $y . '"/><a:ext cx="' . $cx . '" cy="' . $cy . '"/></a:xfrm><a:prstGeom prst="rect"><a:avLst/></a:prstGeom><a:noFill/></p:spPr>' . "\n"
            . '      <p:txBody><a:bodyPr wrap="square"/><a:lstStyle/>' . $paragraphXml . '</p:txBody>' . "\n"
            . '    </p:sp>';
    }

    private function isOpenXmlRaw(AstNode $node): bool
    {
        return $this->rawFormatBase((string) $node->attr('format', '')) === 'openxml';
    }

    private function rawOpenXml(AstNode $node): string
    {
        return trim((string) $node->attr('text', ''));
    }

    private function rawFormatBase(string $format): string
    {
        $format = strtolower(trim($format));
        $format = str_replace('-', '+', $format);

        return explode('+', $format, 2)[0];
    }

    private function indentRawOpenXml(string $xml, int $spaces): string
    {
        $prefix = str_repeat(' ', $spaces);

        return $prefix . str_replace("\n", "\n" . $prefix, trim($xml));
    }

    private function advanceShapeIdPastRawOpenXml(string $xml, int &$shapeId): void
    {
        $nextId = $shapeId + 1;
        if (preg_match_all('/<(?:\w+:)?cNvPr\b[^>]*\bid\s*=\s*(["\'])(\d+)\1/', $xml, $matches) !== false) {
            foreach ($matches[2] ?? [] as $id) {
                $nextId = max($nextId, ((int) $id) + 1);
            }
        }

        $shapeId = $nextId;
    }

    /**
     * @param list<AstNode> $inlines
     * @param array{bullet?:bool, ordered?:bool, level?:int, start?:int} $properties
     * @param list<array{id:string, type:string, target:string, targetMode?:string}> $relationships
     */
    private function paragraphXml(array $inlines, array $properties, array &$relationships): string
    {
        $level = max(0, min(8, (int) ($properties['level'] ?? 0)));
        $propertyChildren = '';
        if (($properties['bullet'] ?? false) === true) {
            $propertyChildren .= '<a:buChar char="&#8226;"/>';
        } elseif (($properties['ordered'] ?? false) === true) {
            $start = max(1, (int) ($properties['start'] ?? 1));
            $propertyChildren .= '<a:buAutoNum type="arabicPeriod" startAt="' . $start . '"/>';
        }
        $paragraphProperties = $propertyChildren !== '' || $level > 0
            ? '<a:pPr lvl="' . $level . '">' . $propertyChildren . '</a:pPr>'
            : '';
        $runs = $this->inlineRuns($inlines, [], $relationships);

        return '<a:p>' . $paragraphProperties . ($runs === [] ? '' : implode('', $runs)) . '</a:p>';
    }

    /**
     * @param list<AstNode> $inlines
     * @param array{bold?:bool, italic?:bool, underline?:bool, strike?:bool, smallCaps?:bool, monospace?:bool, baseline?:int, hyperlinkId?:string, hyperlinkAction?:string} $style
     * @param list<array{id:string, type:string, target:string, targetMode?:string}> $relationships
     * @return list<string>
     */
    private function inlineRuns(array $inlines, array $style, array &$relationships): array
    {
        $runs = [];
        $textBuffer = '';
        $flushText = function () use (&$runs, &$textBuffer, $style): void {
            if ($textBuffer === '') {
                return;
            }
            $runs[] = $this->runXml($textBuffer, $style);
            $textBuffer = '';
        };

        foreach ($inlines as $inline) {
            switch ($inline->type) {
                case 'text':
                    $text = (string) $inline->attr('text', '');
                    if ($text !== '') {
                        $textBuffer .= $text;
                    }
                    break;
                case 'softbreak':
                case 'linebreak':
                    $textBuffer .= ' ';
                    break;
                case 'strong':
                    $flushText();
                    $runs = array_merge($runs, $this->inlineRuns($inline->children, $style + ['bold' => true], $relationships));
                    break;
                case 'emph':
                    $flushText();
                    $runs = array_merge($runs, $this->inlineRuns($inline->children, $style + ['italic' => true], $relationships));
                    break;
                case 'underline':
                    $flushText();
                    $runs = array_merge($runs, $this->inlineRuns($inline->children, $style + ['underline' => true], $relationships));
                    break;
                case 'strikeout':
                    $flushText();
                    $runs = array_merge($runs, $this->inlineRuns($inline->children, $style + ['strike' => true], $relationships));
                    break;
                case 'link':
                    $flushText();
                    $url = (string) $inline->attr('url', $inline->attr('target', ''));
                    $linkStyle = $style;
                    if ($url !== '') {
                        $linkStyle['hyperlinkId'] = $this->addExternalRelationship($relationships, self::REL_HYPERLINK, $url, 'Hyperlink');
                    }
                    $runs = array_merge($runs, $this->inlineRuns($inline->children, $linkStyle, $relationships));
                    break;
                case 'code':
                    $code = (string) $inline->attr('text', '');
                    if ($code !== '') {
                        $flushText();
                        $runs[] = $this->runXml($code, $style + ['monospace' => true]);
                    }
                    break;
                case 'note':
                    $noteId = $this->endnoteId($inline);
                    if ($noteId !== null) {
                        $flushText();
                        $noteStyle = $style + ['baseline' => 30000];
                        if ($this->endnotesSlideNumber > 0) {
                            $noteStyle['hyperlinkId'] = $this->addInternalRelationship(
                                $relationships,
                                self::REL_SLIDE,
                                'slide' . $this->endnotesSlideNumber . '.xml',
                                'Slide'
                            );
                            $noteStyle['hyperlinkAction'] = 'ppaction://hlinksldjump';
                        }
                        $runs[] = $this->runXml((string) $noteId, $noteStyle);
                    }
                    break;
                case 'raw_inline':
                    if ($this->isOpenXmlRaw($inline)) {
                        $xml = $this->rawOpenXml($inline);
                        if ($xml !== '') {
                            $flushText();
                            $runs[] = $xml;
                        }
                    }
                    break;
                case 'raw_html':
                case 'raw_html_inline':
                case 'raw_tex':
                case 'raw_tex_inline':
                case 'raw_markdown':
                    break;
                case 'small_caps':
                case 'smallcaps':
                    $flushText();
                    $runs = array_merge($runs, $this->inlineRuns($inline->children, $style + ['smallCaps' => true], $relationships));
                    break;
                case 'span':
                case 'quoted':
                    $flushText();
                    $runs = array_merge($runs, $this->inlineRuns($inline->children, $style, $relationships));
                    break;
                case 'superscript':
                    $flushText();
                    $runs = array_merge($runs, $this->inlineRuns($inline->children, $style + ['baseline' => 30000], $relationships));
                    break;
                case 'subscript':
                    $flushText();
                    $runs = array_merge($runs, $this->inlineRuns($inline->children, $style + ['baseline' => -25000], $relationships));
                    break;
                case 'image':
                    break;
                default:
                    $text = $this->inlineText([$inline]);
                    if ($text !== '') {
                        $textBuffer .= $text;
                    }
                    break;
            }
        }
        $flushText();

        return $runs;
    }

    /**
     * @param array{bold?:bool, italic?:bool, underline?:bool, strike?:bool, smallCaps?:bool, monospace?:bool, baseline?:int, hyperlinkId?:string, hyperlinkAction?:string} $style
     */
    private function runXml(string $text, array $style): string
    {
        $attrs = ['lang="en-US"'];
        if (($style['bold'] ?? false) === true) {
            $attrs[] = 'b="1"';
        }
        if (($style['italic'] ?? false) === true) {
            $attrs[] = 'i="1"';
        }
        if (($style['underline'] ?? false) === true) {
            $attrs[] = 'u="sng"';
        }
        if (($style['strike'] ?? false) === true) {
            $attrs[] = 'strike="sngStrike"';
        }
        if (($style['smallCaps'] ?? false) === true) {
            $attrs[] = 'cap="small"';
        }
        if (isset($style['baseline'])) {
            $attrs[] = 'baseline="' . (int) $style['baseline'] . '"';
        }
        $children = '';
        if (($style['monospace'] ?? false) === true) {
            $children .= '<a:latin typeface="Courier"/>';
        }
        if (isset($style['hyperlinkId'])) {
            $hyperlinkAttrs = 'r:id="' . $this->xml((string) $style['hyperlinkId']) . '"';
            if (isset($style['hyperlinkAction']) && (string) $style['hyperlinkAction'] !== '') {
                $hyperlinkAttrs .= ' action="' . $this->xml((string) $style['hyperlinkAction']) . '"';
            }
            $children .= '<a:hlinkClick ' . $hyperlinkAttrs . '/>';
        }
        $runProperties = $children === ''
            ? '<a:rPr ' . implode(' ', $attrs) . '/>'
            : '<a:rPr ' . implode(' ', $attrs) . '>' . $children . '</a:rPr>';

        return '<a:r>' . $runProperties . '<a:t>' . $this->xml($text) . '</a:t></a:r>';
    }

    /**
     * @param list<array{id:string, type:string, target:string, targetMode?:string}> $relationships
     * @return list<string>
     */
    private function listParagraphXmls(AstNode $list, bool $ordered, array &$relationships, int $level = 0): array
    {
        $paragraphs = [];
        $start = max(1, (int) $list->attr('start', 1));
        foreach ($list->children as $index => $item) {
            if ($item->type !== 'list_item') {
                continue;
            }
            array_push(
                $paragraphs,
                ...$this->listItemParagraphXmls($item->children, $ordered, $start + $index, $level, $relationships)
            );
        }

        return $paragraphs;
    }

    /**
     * @param list<AstNode> $blocks
     * @param list<array{id:string, type:string, target:string, targetMode?:string}> $relationships
     * @return list<string>
     */
    private function listItemParagraphXmls(array $blocks, bool $ordered, int $start, int $level, array &$relationships): array
    {
        $paragraphs = [];
        $first = true;
        foreach ($blocks as $block) {
            if ($block->type === 'plain' || $block->type === 'paragraph' || $block->type === 'heading') {
                $paragraphs[] = $this->paragraphXml(
                    $block->children,
                    $this->listParagraphProperties($ordered, $start, $level, $first),
                    $relationships
                );
                $first = false;
                continue;
            }
            if ($block->type === 'code_block') {
                $text = (string) $block->attr('text', '');
                if ($text !== '') {
                    $paragraphs[] = $this->paragraphXml(
                        [$this->codeInline($text)],
                        $this->listParagraphProperties($ordered, $start, $level, $first),
                        $relationships
                    );
                    $first = false;
                }
                continue;
            }
            if ($block->type === 'bullet_list' || $block->type === 'ordered_list') {
                $nestedOrdered = $block->type === 'ordered_list';
                array_push($paragraphs, ...$this->listParagraphXmls($block, $nestedOrdered, $relationships, $level + 1));
                continue;
            }
            if ($block->type === 'div' || $block->type === 'block_quote' || $block->type === 'blockquote') {
                array_push(
                    $paragraphs,
                    ...$this->listItemParagraphXmls($block->children, $ordered, $start, $level, $relationships)
                );
                continue;
            }

            $text = $this->blockText($block);
            if ($text !== '') {
                $paragraphs[] = $this->paragraphXml(
                    $this->textInlines($text),
                    $this->listParagraphProperties($ordered, $start, $level, $first),
                    $relationships
                );
                $first = false;
            }
        }

        return $paragraphs;
    }

    /**
     * @return array{bullet?:bool, ordered?:bool, level:int, start?:int}
     */
    private function listParagraphProperties(bool $ordered, int $start, int $level, bool $first): array
    {
        $properties = ['level' => max(0, min(8, $level))];
        if (!$first) {
            return $properties;
        }

        if ($ordered) {
            $properties['ordered'] = true;
            $properties['start'] = $start;
        } else {
            $properties['bullet'] = true;
        }

        return $properties;
    }

    private function pictureShapeXml(AstNode $image, int $shapeId, int $slot, array &$relationships): ?string
    {
        $media = $this->addImageMediaRelationship($image, $relationships);
        if ($media === null) {
            return null;
        }

        [$x, $y, $cx, $cy] = $this->bodyRect($slot);
        $cy = min($cy, 1371600);
        $alt = (string) $image->attr('alt', $this->inlineText($image->children));
        $name = (string) $image->attr('title', '');
        if ($name === '' || str_starts_with($name, 'fig:')) {
            $name = $alt !== '' ? $alt : basename((string) $media['name']);
        }

        return '    <p:pic>' . "\n"
            . '      <p:nvPicPr><p:cNvPr id="' . $shapeId . '" name="' . $this->xml($name) . '" descr="' . $this->xml($alt) . '"/><p:cNvPicPr><a:picLocks noChangeAspect="1"/></p:cNvPicPr><p:nvPr/></p:nvPicPr>' . "\n"
            . '      <p:blipFill><a:blip r:embed="' . $this->xml((string) $media['relationshipId']) . '"/><a:stretch><a:fillRect/></a:stretch></p:blipFill>' . "\n"
            . '      <p:spPr><a:xfrm><a:off x="' . $x . '" y="' . $y . '"/><a:ext cx="' . $cx . '" cy="' . $cy . '"/></a:xfrm><a:prstGeom prst="rect"><a:avLst/></a:prstGeom></p:spPr>' . "\n"
            . '    </p:pic>';
    }

    private function figureCaption(AstNode $image): string
    {
        $title = (string) $image->attr('title', '');
        if (!str_starts_with($title, 'fig:')) {
            return '';
        }

        return $this->imageFallbackText($image);
    }

    /**
     * @param list<array{id:string, type:string, target:string, targetMode?:string}> $relationships
     */
    private function slideBackgroundXml(?string $source, array &$relationships): string
    {
        $source = trim((string) $source);
        if ($source === '') {
            return '';
        }

        $media = $this->addImageMediaRelationship(new AstNode('image', ['url' => $source]), $relationships);
        if ($media === null) {
            return '';
        }

        return '    <p:bg><p:bgPr><a:blipFill dpi="0" rotWithShape="1"><a:blip r:embed="'
            . $this->xml((string) $media['relationshipId'])
            . '"><a:lum/></a:blip><a:srcRect/><a:stretch><a:fillRect/></a:stretch></a:blipFill><a:effectLst/></p:bgPr></p:bg>' . "\n";
    }

    /**
     * @param list<array{id:string, type:string, target:string, targetMode?:string}> $relationships
     * @return array{name:string, relationshipId:string}|null
     */
    private function addImageMediaRelationship(AstNode $image, array &$relationships): ?array
    {
        $source = (string) $image->attr('url', $image->attr('src', ''));
        $resolved = $this->resolveImageMedia($source);
        if ($resolved === null) {
            return null;
        }

        $key = $source !== '' ? 'source:' . $source : 'hash:' . hash('sha256', $resolved['data']);
        if (isset($this->mediaPartsByKey[$key])) {
            $part = $this->mediaPartsByKey[$key];
        } else {
            $extension = $resolved['extension'];
            $part = [
                'name' => 'ppt/media/image' . $this->nextMediaId++ . '.' . $extension,
                'data' => $resolved['data'],
                'contentType' => $resolved['contentType'],
                'extension' => $extension,
                'source' => $source,
            ];
            $this->mediaParts[] = $part;
            $this->mediaPartsByKey[$key] = $part;
        }

        $relationshipId = $this->addInternalRelationship(
            $relationships,
            self::REL_IMAGE,
            '../media/' . basename($part['name']),
            'Image'
        );

        return ['name' => $part['name'], 'relationshipId' => $relationshipId];
    }

    /**
     * @return array{data:string, extension:string, contentType:string}|null
     */
    private function resolveImageMedia(string $source): ?array
    {
        $source = trim($source);
        if ($source === '') {
            return null;
        }

        if (preg_match('/^data:([^;,]+)?(;base64)?,(.*)$/s', $source, $matches) === 1) {
            $mime = $matches[1] !== '' ? strtolower($matches[1]) : 'application/octet-stream';
            $data = isset($matches[2]) && $matches[2] === ';base64'
                ? base64_decode($matches[3], true)
                : rawurldecode($matches[3]);
            if (!is_string($data)) {
                return null;
            }
            $extension = $this->extensionForContentType($mime);
            if ($extension === null) {
                return null;
            }

            return ['data' => $data, 'extension' => $extension, 'contentType' => $mime];
        }

        $resource = $this->configuredMediaResource($source);
        if ($resource !== null) {
            return $resource;
        }

        $path = $this->resolveLocalMediaPath($source);
        if ($path === null || !is_file($path) || !is_readable($path)) {
            return null;
        }

        $data = file_get_contents($path);
        if (!is_string($data)) {
            return null;
        }
        $extension = strtolower((string) pathinfo($path, PATHINFO_EXTENSION));
        $extension = $extension === 'jpeg' ? 'jpg' : $extension;
        $contentType = $this->contentTypeForExtension($extension);
        if ($contentType === null) {
            return null;
        }

        return ['data' => $data, 'extension' => $extension, 'contentType' => $contentType];
    }

    /**
     * @return array{data:string, extension:string, contentType:string}|null
     */
    private function configuredMediaResource(string $source): ?array
    {
        $resources = $this->mediaResourcesOption();
        if ($resources === null) {
            return null;
        }

        $keys = [$source, ltrim($source, '/')];
        foreach ($keys as $key) {
            if (!array_key_exists($key, $resources)) {
                continue;
            }
            $resource = $resources[$key];
            $data = null;
            $contentType = null;
            if (is_string($resource)) {
                $data = $resource;
            } elseif (is_array($resource)) {
                foreach (['contents', 'data'] as $dataKey) {
                    if (isset($resource[$dataKey]) && is_string($resource[$dataKey])) {
                        $data = $resource[$dataKey];
                        break;
                    }
                }
                if (isset($resource['mimeType']) && is_string($resource['mimeType'])) {
                    $contentType = strtolower($resource['mimeType']);
                }
                if ($contentType === null && isset($resource['contentType']) && is_string($resource['contentType'])) {
                    $contentType = strtolower($resource['contentType']);
                }
            }
            if ($data === null) {
                return null;
            }

            $extension = $contentType === null ? null : $this->extensionForContentType($contentType);
            if ($extension === null) {
                $extension = strtolower((string) pathinfo(parse_url($source, PHP_URL_PATH) ?: $source, PATHINFO_EXTENSION));
                $extension = $extension === 'jpeg' ? 'jpg' : $extension;
                $contentType = $this->contentTypeForExtension($extension);
            }
            if ($extension === null || $contentType === null) {
                return null;
            }

            return ['data' => $data, 'extension' => $extension, 'contentType' => $contentType];
        }

        return null;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function mediaResourcesOption(): ?array
    {
        foreach (['mediaResources', 'resources', 'resourceMap', 'media'] as $key) {
            $value = $this->options[$key] ?? null;
            if (is_array($value)) {
                return $value;
            }
        }

        return null;
    }

    private function resolveLocalMediaPath(string $source): ?string
    {
        if ($source === '' || preg_match('/^[A-Za-z][A-Za-z0-9+.-]*:/', $source) === 1) {
            return null;
        }

        $candidates = [];
        if ($source[0] === '/') {
            $candidates[] = $source;
        } else {
            $normalizedSource = str_replace('/', DIRECTORY_SEPARATOR, $source);
            foreach ($this->mediaBasePaths() as $basePath) {
                $candidates[] = rtrim($basePath, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $normalizedSource;
            }
            $cwd = getcwd();
            if (is_string($cwd)) {
                $candidates[] = $cwd . DIRECTORY_SEPARATOR . $normalizedSource;
            }
        }

        foreach ($candidates as $candidate) {
            $real = realpath($candidate);
            if (is_string($real) && is_file($real)) {
                return $real;
            }
        }

        return null;
    }

    /**
     * @return list<string>
     */
    private function mediaBasePaths(): array
    {
        $paths = [];
        foreach (['mediaBasePath', 'resourcePath'] as $key) {
            if (isset($this->options[$key]) && is_string($this->options[$key]) && $this->options[$key] !== '') {
                $paths[] = $this->options[$key];
            }
        }
        foreach (['mediaBasePaths', 'resourcePaths'] as $key) {
            $value = $this->options[$key] ?? [];
            if (is_string($value) && $value !== '') {
                $paths[] = $value;
                continue;
            }
            if (!is_array($value)) {
                continue;
            }
            foreach ($value as $path) {
                if (is_string($path) && $path !== '') {
                    $paths[] = $path;
                }
            }
        }

        return array_values(array_unique($paths));
    }

    private function tableShapeXml(AstNode $table, int $shapeId, int $slot): string
    {
        [$x, $y, $cx, $cy] = $this->bodyRect($slot);
        $rows = $this->tableRows($table);
        if ($rows === []) {
            $rows = [[['text' => '', 'attrs' => []]]];
        }
        $columnCount = max(1, max(array_map('count', $rows)));
        $columnWidth = intdiv($cx, $columnCount);
        $grid = '';
        for ($index = 0; $index < $columnCount; $index++) {
            $grid .= '<a:gridCol w="' . $columnWidth . '"/>';
        }

        $rowXml = '';
        foreach ($rows as $row) {
            $rowXml .= '<a:tr h="370840">';
            foreach ($row as $cell) {
                $attrs = '';
                $colspan = (int) ($cell['attrs']['colspan'] ?? 1);
                $rowspan = (int) ($cell['attrs']['rowspan'] ?? 1);
                if ($colspan > 1) {
                    $attrs .= ' gridSpan="' . $colspan . '"';
                }
                if ($rowspan > 1) {
                    $attrs .= ' rowSpan="' . $rowspan . '"';
                }
                $relationships = [];
                $rowXml .= '<a:tc' . $attrs . '><a:txBody><a:bodyPr/><a:lstStyle/>'
                    . $this->paragraphXml($this->textInlines($cell['text']), [], $relationships)
                    . '</a:txBody><a:tcPr/></a:tc>';
            }
            $rowXml .= '</a:tr>';
        }

        return '    <p:graphicFrame>' . "\n"
            . '      <p:nvGraphicFramePr><p:cNvPr id="' . $shapeId . '" name="' . $this->xml($this->shapeName($table, 'Table')) . '"/><p:cNvGraphicFramePr/><p:nvPr/></p:nvGraphicFramePr>' . "\n"
            . '      <p:xfrm><a:off x="' . $x . '" y="' . $y . '"/><a:ext cx="' . $cx . '" cy="' . $cy . '"/></p:xfrm>' . "\n"
            . '      <a:graphic><a:graphicData uri="http://schemas.openxmlformats.org/drawingml/2006/table"><a:tbl><a:tblPr firstRow="1" bandRow="1"><a:tableStyleId>{5C22544A-7EE6-4342-B048-85BDC9FD1C3A}</a:tableStyleId></a:tblPr><a:tblGrid>' . $grid . '</a:tblGrid>' . $rowXml . '</a:tbl></a:graphicData></a:graphic>' . "\n"
            . '    </p:graphicFrame>';
    }

    /**
     * @return list<list<array{text:string, attrs:array<string, mixed>}>>
     */
    private function tableRows(AstNode $table): array
    {
        $rows = [];
        foreach ($table->children as $section) {
            if ($section->type === 'table_head' || $section->type === 'table_body' || $section->type === 'table_foot') {
                foreach ($section->children as $row) {
                    if ($row->type === 'table_row') {
                        $rows[] = $this->tableRow($row);
                    }
                }
                continue;
            }
            if ($section->type === 'table_row') {
                $rows[] = $this->tableRow($section);
            }
        }

        return array_values(array_filter($rows, static fn (array $row): bool => $row !== []));
    }

    /**
     * @return list<array{text:string, attrs:array<string, mixed>}>
     */
    private function tableRow(AstNode $row): array
    {
        $cells = [];
        foreach ($row->children as $cell) {
            if ($cell->type !== 'table_cell') {
                continue;
            }
            $text = (string) $cell->attr('text', '');
            if ($text === '') {
                $text = $this->blockListText($cell->children);
            }
            $cells[] = ['text' => $text, 'attrs' => $cell->attrs];
        }

        return $cells;
    }

    /**
     * @param list<array{id:string, type:string, target:string, targetMode?:string}> $relationships
     */
    private function addInternalRelationship(array &$relationships, string $type, string $target, string $prefix): string
    {
        $id = 'rId' . $prefix . (count(array_filter(
            $relationships,
            static fn (array $relationship): bool => str_starts_with((string) $relationship['id'], 'rId' . $prefix)
        )) + 1);
        $relationships[] = [
            'id' => $id,
            'type' => $type,
            'target' => $target,
        ];

        return $id;
    }

    /**
     * @param list<array{id:string, type:string, target:string, targetMode?:string}> $relationships
     */
    private function addExternalRelationship(array &$relationships, string $type, string $target, string $prefix): string
    {
        $id = 'rId' . $prefix . (count(array_filter(
            $relationships,
            static fn (array $relationship): bool => str_starts_with((string) $relationship['id'], 'rId' . $prefix)
        )) + 1);
        $relationships[] = [
            'id' => $id,
            'type' => $type,
            'target' => $target,
            'targetMode' => 'External',
        ];

        return $id;
    }

    /**
     * @param list<array{id:string, type:string, target:string, targetMode?:string}> $relationships
     */
    private function relationshipsXml(array $relationships): string
    {
        $rows = [];
        foreach ($relationships as $relationship) {
            $targetMode = isset($relationship['targetMode'])
                ? ' TargetMode="' . $this->xml((string) $relationship['targetMode']) . '"'
                : '';
            $rows[] = '  <Relationship Id="' . $this->xml($relationship['id']) . '" Type="' . $this->xml($relationship['type']) . '" Target="' . $this->xml($relationship['target']) . '"' . $targetMode . '/>';
        }

        return '<?xml version="1.0" encoding="UTF-8"?>' . "\n"
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">' . "\n"
            . implode("\n", $rows) . "\n"
            . '</Relationships>' . "\n";
    }

    private function rootRelationshipsXml(): string
    {
        return $this->relationshipsXml([
            ['id' => 'rId1', 'type' => self::REL_OFFICE_DOCUMENT, 'target' => 'ppt/presentation.xml'],
            ['id' => 'rId2', 'type' => self::REL_CORE_PROPERTIES, 'target' => 'docProps/core.xml'],
            ['id' => 'rId3', 'type' => self::REL_EXTENDED_PROPERTIES, 'target' => 'docProps/app.xml'],
            ['id' => 'rId4', 'type' => self::REL_CUSTOM_PROPERTIES, 'target' => 'docProps/custom.xml'],
        ]);
    }

    private function presentationRelationshipsXml(int $slideCount, int $notesSlideCount): string
    {
        $relationships = [];
        for ($slide = 1; $slide <= $slideCount; $slide++) {
            $relationships[] = [
                'id' => 'rId' . $slide,
                'type' => self::REL_SLIDE,
                'target' => 'slides/slide' . $slide . '.xml',
            ];
        }
        $relationships[] = ['id' => 'rIdMaster', 'type' => self::REL_SLIDE_MASTER, 'target' => 'slideMasters/slideMaster1.xml'];
        if ($notesSlideCount > 0) {
            $relationships[] = ['id' => 'rIdNotesMaster', 'type' => self::REL_NOTES_MASTER, 'target' => 'notesMasters/notesMaster1.xml'];
        }
        $relationships[] = ['id' => 'rIdTableStyles', 'type' => self::REL_TABLE_STYLES, 'target' => 'tableStyles.xml'];

        return $this->relationshipsXml($relationships);
    }

    private function slideMasterRelationshipsXml(): string
    {
        return $this->relationshipsXml([
            ['id' => 'rIdLayout', 'type' => self::REL_SLIDE_LAYOUT, 'target' => '../slideLayouts/slideLayout1.xml'],
            ['id' => 'rIdTheme', 'type' => self::REL_THEME, 'target' => '../theme/theme1.xml'],
        ]);
    }

    private function slideLayoutRelationshipsXml(): string
    {
        return $this->relationshipsXml([
            ['id' => 'rIdMaster', 'type' => self::REL_SLIDE_MASTER, 'target' => '../slideMasters/slideMaster1.xml'],
        ]);
    }

    private function notesMasterRelationshipsXml(): string
    {
        return $this->relationshipsXml([
            ['id' => 'rIdTheme', 'type' => self::REL_THEME, 'target' => '../theme/theme1.xml'],
        ]);
    }

    private function notesSlideRelationshipsXml(int $slideNumber): string
    {
        return $this->relationshipsXml([
            ['id' => 'rId1', 'type' => self::REL_NOTES_MASTER, 'target' => '../notesMasters/notesMaster1.xml'],
            ['id' => 'rId2', 'type' => self::REL_SLIDE, 'target' => '../slides/slide' . $slideNumber . '.xml'],
        ]);
    }

    private function presentationXml(int $slideCount, int $notesSlideCount): string
    {
        $slideIds = [];
        for ($slide = 1; $slide <= $slideCount; $slide++) {
            $slideIds[] = '    <p:sldId id="' . (255 + $slide) . '" r:id="rId' . $slide . '"/>';
        }
        $notesMaster = $notesSlideCount === 0
            ? ''
            : '  <p:notesMasterIdLst><p:notesMasterId r:id="rIdNotesMaster"/></p:notesMasterIdLst>' . "\n";

        return '<?xml version="1.0" encoding="UTF-8"?>' . "\n"
            . '<p:presentation xmlns:a="' . self::NS_A . '" xmlns:p="' . self::NS_P . '" xmlns:r="' . self::NS_R . '">' . "\n"
            . '  <p:sldMasterIdLst><p:sldMasterId id="2147483648" r:id="rIdMaster"/></p:sldMasterIdLst>' . "\n"
            . $notesMaster
            . '  <p:sldIdLst>' . "\n"
            . implode("\n", $slideIds) . "\n"
            . '  </p:sldIdLst>' . "\n"
            . '  <p:sldSz cx="' . self::SLIDE_WIDTH_EMU . '" cy="' . self::SLIDE_HEIGHT_EMU . '" type="screen16x9"/>' . "\n"
            . '  <p:notesSz cx="6858000" cy="9144000"/>' . "\n"
            . '</p:presentation>' . "\n";
    }

    private function notesMasterXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8"?>' . "\n"
            . '<p:notesMaster xmlns:a="' . self::NS_A . '" xmlns:p="' . self::NS_P . '" xmlns:r="' . self::NS_R . '">' . "\n"
            . '  <p:cSld><p:spTree>' . "\n"
            . '    <p:nvGrpSpPr><p:cNvPr id="1" name=""/><p:cNvGrpSpPr/><p:nvPr/></p:nvGrpSpPr><p:grpSpPr/>' . "\n"
            . '    <p:sp><p:nvSpPr><p:cNvPr id="2" name="Slide Image Placeholder"/><p:cNvSpPr/><p:nvPr><p:ph type="sldImg"/></p:nvPr></p:nvSpPr><p:spPr/></p:sp>' . "\n"
            . '    <p:sp><p:nvSpPr><p:cNvPr id="3" name="Notes Placeholder"/><p:cNvSpPr><a:spLocks noGrp="1"/></p:cNvSpPr><p:nvPr><p:ph type="body" idx="1"/></p:nvPr></p:nvSpPr><p:spPr/><p:txBody><a:bodyPr/><a:lstStyle/><a:p/></p:txBody></p:sp>' . "\n"
            . '    <p:sp><p:nvSpPr><p:cNvPr id="4" name="Slide Number Placeholder"/><p:cNvSpPr/><p:nvPr><p:ph type="sldNum" idx="2"/></p:nvPr></p:nvSpPr><p:spPr/><p:txBody><a:bodyPr/><a:lstStyle/><a:p><a:fld id="{00000000-0000-0000-0000-000000000001}" type="slidenum"><a:rPr lang="en-US"/><a:t>#</a:t></a:fld><a:endParaRPr lang="en-US"/></a:p></p:txBody></p:sp>' . "\n"
            . '  </p:spTree></p:cSld>' . "\n"
            . '  <p:clrMap bg1="lt1" tx1="dk1" bg2="lt2" tx2="dk2" accent1="accent1" accent2="accent2" accent3="accent3" accent4="accent4" accent5="accent5" accent6="accent6" hlink="hlink" folHlink="folHlink"/>' . "\n"
            . '</p:notesMaster>' . "\n";
    }

    /**
     * @param list<AstNode> $notes
     */
    private function notesSlideXml(array $notes, int $slideNumber): string
    {
        $paragraphs = $this->notesParagraphXmls($notes);
        $body = $paragraphs === '' ? '<a:p/>' : $paragraphs;

        return '<?xml version="1.0" encoding="UTF-8"?>' . "\n"
            . '<p:notes xmlns:a="' . self::NS_A . '" xmlns:p="' . self::NS_P . '" xmlns:r="' . self::NS_R . '">' . "\n"
            . '  <p:cSld><p:spTree>' . "\n"
            . '    <p:nvGrpSpPr><p:cNvPr id="1" name=""/><p:cNvGrpSpPr/><p:nvPr/></p:nvGrpSpPr><p:grpSpPr/>' . "\n"
            . '    <p:sp><p:nvSpPr><p:cNvPr id="2" name="Slide Image Placeholder 1"/><p:cNvSpPr/><p:nvPr><p:ph type="sldImg"/></p:nvPr></p:nvSpPr><p:spPr/></p:sp>' . "\n"
            . '    <p:sp><p:nvSpPr><p:cNvPr id="3" name="Notes Placeholder 2"/><p:cNvSpPr><a:spLocks noGrp="1"/></p:cNvSpPr><p:nvPr><p:ph type="body" idx="1"/></p:nvPr></p:nvSpPr><p:spPr/><p:txBody><a:bodyPr/><a:lstStyle/>' . $body . '</p:txBody></p:sp>' . "\n"
            . '    <p:sp><p:nvSpPr><p:cNvPr id="4" name="Slide Number Placeholder 3"/><p:cNvSpPr/><p:nvPr><p:ph type="sldNum" idx="2"/></p:nvPr></p:nvSpPr><p:spPr/><p:txBody><a:bodyPr/><a:lstStyle/><a:p><a:fld id="{00000000-0000-0000-0000-' . str_pad((string) $slideNumber, 12, '0', STR_PAD_LEFT) . '}" type="slidenum"><a:rPr lang="en-US"/><a:t>' . $slideNumber . '</a:t></a:fld><a:endParaRPr lang="en-US"/></a:p></p:txBody></p:sp>' . "\n"
            . '  </p:spTree></p:cSld>' . "\n"
            . '  <p:clrMapOvr><a:masterClrMapping/></p:clrMapOvr>' . "\n"
            . '</p:notes>' . "\n";
    }

    private function slideMasterXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8"?>' . "\n"
            . '<p:sldMaster xmlns:a="' . self::NS_A . '" xmlns:p="' . self::NS_P . '" xmlns:r="' . self::NS_R . '">' . "\n"
            . '  <p:cSld><p:spTree><p:nvGrpSpPr><p:cNvPr id="1" name=""/><p:cNvGrpSpPr/><p:nvPr/></p:nvGrpSpPr><p:grpSpPr/></p:spTree></p:cSld>' . "\n"
            . '  <p:sldLayoutIdLst><p:sldLayoutId id="2147483649" r:id="rIdLayout"/></p:sldLayoutIdLst>' . "\n"
            . '  <p:clrMap bg1="lt1" tx1="dk1" bg2="lt2" tx2="dk2" accent1="accent1" accent2="accent2" accent3="accent3" accent4="accent4" accent5="accent5" accent6="accent6" hlink="hlink" folHlink="folHlink"/>' . "\n"
            . '</p:sldMaster>' . "\n";
    }

    private function slideLayoutXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8"?>' . "\n"
            . '<p:sldLayout xmlns:a="' . self::NS_A . '" xmlns:p="' . self::NS_P . '" xmlns:r="' . self::NS_R . '" type="obj" preserve="1">' . "\n"
            . '  <p:cSld name="Title and Content"><p:spTree>' . "\n"
            . '    <p:nvGrpSpPr><p:cNvPr id="1" name=""/><p:cNvGrpSpPr/><p:nvPr/></p:nvGrpSpPr><p:grpSpPr/>' . "\n"
            . '    <p:sp><p:nvSpPr><p:cNvPr id="2" name="Title Placeholder"/><p:cNvSpPr txBox="1"/><p:nvPr><p:ph type="title"/></p:nvPr></p:nvSpPr><p:txBody><a:bodyPr/><a:lstStyle/><a:p/></p:txBody></p:sp>' . "\n"
            . '    <p:sp><p:nvSpPr><p:cNvPr id="3" name="Body Placeholder"/><p:cNvSpPr txBox="1"/><p:nvPr><p:ph type="body" idx="1"/></p:nvPr></p:nvSpPr><p:txBody><a:bodyPr/><a:lstStyle/><a:p/></p:txBody></p:sp>' . "\n"
            . '  </p:spTree></p:cSld>' . "\n"
            . '  <p:clrMapOvr><a:masterClrMapping/></p:clrMapOvr>' . "\n"
            . '</p:sldLayout>' . "\n";
    }

    private function themeXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8"?>' . "\n"
            . '<a:theme xmlns:a="' . self::NS_A . '" name="Port Libs Theme">' . "\n"
            . '  <a:themeElements>' . "\n"
            . '    <a:clrScheme name="Port Libs"><a:dk1><a:srgbClr val="111827"/></a:dk1><a:lt1><a:srgbClr val="FFFFFF"/></a:lt1><a:dk2><a:srgbClr val="1F2937"/></a:dk2><a:lt2><a:srgbClr val="F9FAFB"/></a:lt2><a:accent1><a:srgbClr val="2563EB"/></a:accent1><a:accent2><a:srgbClr val="059669"/></a:accent2><a:accent3><a:srgbClr val="DC2626"/></a:accent3><a:accent4><a:srgbClr val="7C3AED"/></a:accent4><a:accent5><a:srgbClr val="EA580C"/></a:accent5><a:accent6><a:srgbClr val="0891B2"/></a:accent6><a:hlink><a:srgbClr val="2563EB"/></a:hlink><a:folHlink><a:srgbClr val="7C3AED"/></a:folHlink></a:clrScheme>' . "\n"
            . '    <a:fontScheme name="Aptos"><a:majorFont><a:latin typeface="Aptos Display"/></a:majorFont><a:minorFont><a:latin typeface="Aptos"/></a:minorFont></a:fontScheme>' . "\n"
            . '    <a:fmtScheme name="Office"><a:fillStyleLst><a:solidFill><a:schemeClr val="phClr"/></a:solidFill></a:fillStyleLst><a:lnStyleLst><a:ln w="6350"><a:solidFill><a:schemeClr val="phClr"/></a:solidFill></a:ln></a:lnStyleLst><a:effectStyleLst><a:effectStyle><a:effectLst/></a:effectStyle></a:effectStyleLst><a:bgFillStyleLst><a:solidFill><a:schemeClr val="phClr"/></a:solidFill></a:bgFillStyleLst></a:fmtScheme>' . "\n"
            . '  </a:themeElements>' . "\n"
            . '</a:theme>' . "\n";
    }

    private function tableStylesXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8"?>' . "\n"
            . '<a:tblStyleLst xmlns:a="' . self::NS_A . '" def="{5C22544A-7EE6-4342-B048-85BDC9FD1C3A}">' . "\n"
            . '  <a:tblStyle styleId="{5C22544A-7EE6-4342-B048-85BDC9FD1C3A}" styleName="Medium Style 2 - Accent 1"><a:wholeTbl><a:tcTxStyle b="0"/><a:tcStyle><a:fill><a:solidFill><a:schemeClr val="lt1"/></a:solidFill></a:fill></a:tcStyle></a:wholeTbl><a:firstRow><a:tcTxStyle b="1"/></a:firstRow></a:tblStyle>' . "\n"
            . '</a:tblStyleLst>' . "\n";
    }

    private function contentTypesXml(int $slideCount, int $notesSlideCount): string
    {
        $defaults = [
            'rels' => self::CT_RELATIONSHIPS,
            'xml' => 'application/xml',
        ];
        foreach ($this->mediaParts as $mediaPart) {
            $defaults[$mediaPart['extension']] = $mediaPart['contentType'];
        }
        ksort($defaults, SORT_STRING);

        $defaultRows = [];
        foreach ($defaults as $extension => $contentType) {
            $defaultRows[] = '  <Default Extension="' . $this->xml($extension) . '" ContentType="' . $this->xml($contentType) . '"/>';
        }

        $overrides = [
            '/docProps/core.xml' => self::CT_CORE_PROPERTIES,
            '/docProps/app.xml' => self::CT_EXTENDED_PROPERTIES,
            '/docProps/custom.xml' => self::CT_CUSTOM_PROPERTIES,
            '/ppt/presentation.xml' => self::CT_PRESENTATION,
            '/ppt/slideMasters/slideMaster1.xml' => self::CT_SLIDE_MASTER,
            '/ppt/slideLayouts/slideLayout1.xml' => self::CT_SLIDE_LAYOUT,
            '/ppt/theme/theme1.xml' => self::CT_THEME,
            '/ppt/tableStyles.xml' => self::CT_TABLE_STYLES,
        ];
        for ($slide = 1; $slide <= $slideCount; $slide++) {
            $overrides['/ppt/slides/slide' . $slide . '.xml'] = self::CT_SLIDE;
        }
        if ($notesSlideCount > 0) {
            $overrides['/ppt/notesMasters/notesMaster1.xml'] = self::CT_NOTES_MASTER;
            for ($noteSlide = 1; $noteSlide <= $notesSlideCount; $noteSlide++) {
                $overrides['/ppt/notesSlides/notesSlide' . $noteSlide . '.xml'] = self::CT_NOTES_SLIDE;
            }
        }
        ksort($overrides, SORT_STRING);

        $overrideRows = [];
        foreach ($overrides as $partName => $contentType) {
            $overrideRows[] = '  <Override PartName="' . $this->xml($partName) . '" ContentType="' . $this->xml($contentType) . '"/>';
        }

        return '<?xml version="1.0" encoding="UTF-8"?>' . "\n"
            . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">' . "\n"
            . implode("\n", $defaultRows) . "\n"
            . implode("\n", $overrideRows) . "\n"
            . '</Types>' . "\n";
    }

    /**
     * @param array<string, mixed> $metadata
     */
    private function corePropertiesXml(array $metadata): string
    {
        $keywords = $metadata['keywords'] === [] ? '' : implode(', ', $metadata['keywords']);
        $category = (string) ($metadata['category'] ?? '');
        $categoryXml = $category === '' ? '' : '  <cp:category>' . $this->xml($category) . '</cp:category>' . "\n";

        return '<?xml version="1.0" encoding="UTF-8"?>' . "\n"
            . '<cp:coreProperties xmlns:cp="' . self::NS_CP . '" xmlns:dc="' . self::NS_DC . '" xmlns:dcterms="' . self::NS_DCTERMS . '" xmlns:xsi="' . self::NS_XSI . '">' . "\n"
            . '  <dc:title>' . $this->xml((string) $metadata['title']) . '</dc:title>' . "\n"
            . '  <dc:creator>' . $this->xml((string) $metadata['creator']) . '</dc:creator>' . "\n"
            . '  <dc:subject>' . $this->xml((string) $metadata['subject']) . '</dc:subject>' . "\n"
            . '  <cp:keywords>' . $this->xml($keywords) . '</cp:keywords>' . "\n"
            . '  <dc:description>' . $this->xml((string) $metadata['description']) . '</dc:description>' . "\n"
            . $categoryXml
            . '  <cp:lastModifiedBy>Port Libs</cp:lastModifiedBy>' . "\n"
            . '  <dcterms:created xsi:type="dcterms:W3CDTF">' . $this->xml((string) $metadata['modified']) . '</dcterms:created>' . "\n"
            . '  <dcterms:modified xsi:type="dcterms:W3CDTF">' . $this->xml((string) $metadata['modified']) . '</dcterms:modified>' . "\n"
            . '</cp:coreProperties>' . "\n";
    }

    /**
     * @param array<string, mixed> $metadata
     */
    private function customPropertiesXml(array $metadata): string
    {
        $properties = $metadata['customProperties'] ?? [];
        if (!is_array($properties) || $properties === []) {
            return '<?xml version="1.0" encoding="UTF-8"?>' . "\n"
                . '<Properties xmlns="' . self::NS_CUSTOM_PROPERTIES . '" xmlns:vt="' . self::NS_VT . '" />' . "\n";
        }

        $rows = [];
        $pid = 2;
        foreach ($properties as $name => $value) {
            if (!is_string($name)) {
                continue;
            }
            $rows[] = '  <property fmtid="{D5CDD505-2E9C-101B-9397-08002B2CF9AE}" pid="' . $pid++ . '" name="' . $this->xml($name) . '"><vt:lpwstr>' . $this->xml((string) $value) . '</vt:lpwstr></property>';
        }

        if ($rows === []) {
            return '<?xml version="1.0" encoding="UTF-8"?>' . "\n"
                . '<Properties xmlns="' . self::NS_CUSTOM_PROPERTIES . '" xmlns:vt="' . self::NS_VT . '" />' . "\n";
        }

        return '<?xml version="1.0" encoding="UTF-8"?>' . "\n"
            . '<Properties xmlns="' . self::NS_CUSTOM_PROPERTIES . '" xmlns:vt="' . self::NS_VT . '">' . "\n"
            . implode("\n", $rows) . "\n"
            . '</Properties>' . "\n";
    }

    /**
     * @param array<string, mixed> $metadata
     */
    private function extendedPropertiesXml(array $metadata, int $slideCount, int $notesSlideCount): string
    {
        return '<?xml version="1.0" encoding="UTF-8"?>' . "\n"
            . '<Properties xmlns="' . self::NS_EP . '">' . "\n"
            . '  <Application>Port Libs Pandoc</Application>' . "\n"
            . '  <PresentationFormat>On-screen Show (16:9)</PresentationFormat>' . "\n"
            . '  <Slides>' . $slideCount . '</Slides>' . "\n"
            . '  <Notes>' . $notesSlideCount . '</Notes>' . "\n"
            . '  <HiddenSlides>0</HiddenSlides>' . "\n"
            . '  <Company>' . $this->xml((string) $metadata['creator']) . '</Company>' . "\n"
            . '</Properties>' . "\n";
    }

    /**
     * @return list<AstNode>
     */
    private function metadataSpeakerNotes(AstNode $document): array
    {
        $meta = $document->attr('meta', []);
        if (!is_array($meta)) {
            return [];
        }

        return $this->metaBlocks($meta, ['notes', 'speaker-notes', 'speakerNotes']);
    }

    /**
     * @param array<string, mixed> $meta
     * @param list<string> $keys
     * @return list<AstNode>
     */
    private function metaBlocks(array $meta, array $keys): array
    {
        foreach ($keys as $key) {
            if (!array_key_exists($key, $meta)) {
                continue;
            }
            $value = $meta[$key];
            if (is_array($value) && ($value['type'] ?? null) === 'MetaBlocks' && isset($value['value']) && is_array($value['value'])) {
                $blocks = array_values(array_filter($value['value'], static fn (mixed $item): bool => $item instanceof AstNode));
                if ($blocks !== []) {
                    return $blocks;
                }
            }
            if (is_array($value) && array_is_list($value)) {
                $blocks = array_values(array_filter($value, static fn (mixed $item): bool => $item instanceof AstNode));
                if ($blocks !== []) {
                    return $blocks;
                }
            }
            if ($value instanceof AstNode) {
                return [$value];
            }
        }

        return [];
    }

    private function isSpeakerNotesBlock(AstNode $block): bool
    {
        if ($block->type !== 'div') {
            return false;
        }
        $classes = $block->attr('classes', []);

        return is_array($classes) && in_array('notes', $classes, true);
    }

    /**
     * @param list<AstNode> $blocks
     */
    private function notesParagraphXmls(array $blocks): string
    {
        $paragraphs = [];
        foreach ($blocks as $block) {
            if ($block->type === 'plain' || $block->type === 'paragraph' || $block->type === 'heading') {
                $paragraphs[] = $this->notesParagraphXml($block->children);
                continue;
            }
            if ($block->type === 'bullet_list' || $block->type === 'ordered_list') {
                $relationships = [];
                foreach ($this->listParagraphXmls($block, $block->type === 'ordered_list', $relationships) as $paragraph) {
                    $paragraphs[] = $paragraph;
                }
                continue;
            }
            if ($block->type === 'div' || $block->type === 'block_quote') {
                $nested = $this->notesParagraphXmls($block->children);
                if ($nested !== '') {
                    $paragraphs[] = $nested;
                }
                continue;
            }

            $text = $this->blockText($block);
            if ($text !== '') {
                $paragraphs[] = $this->notesParagraphXml($this->textInlines($text));
            }
        }

        return implode('', $paragraphs);
    }

    /**
     * @param list<AstNode> $inlines
     */
    private function notesParagraphXml(array $inlines): string
    {
        $runs = $this->noteInlineRuns($inlines, []);

        return '<a:p>' . ($runs === [] ? '' : implode('', $runs)) . '</a:p>';
    }

    /**
     * @param list<AstNode> $inlines
     * @param array{bold?:bool, italic?:bool, underline?:bool, strike?:bool, smallCaps?:bool, monospace?:bool} $style
     * @return list<string>
     */
    private function noteInlineRuns(array $inlines, array $style): array
    {
        $runs = [];
        $textBuffer = '';
        $flushText = function () use (&$runs, &$textBuffer, $style): void {
            if ($textBuffer === '') {
                return;
            }
            $runs[] = $this->runXml($textBuffer, $style);
            $textBuffer = '';
        };

        foreach ($inlines as $inline) {
            switch ($inline->type) {
                case 'text':
                    $text = (string) $inline->attr('text', '');
                    if ($text !== '') {
                        $textBuffer .= $text;
                    }
                    break;
                case 'softbreak':
                case 'linebreak':
                    $textBuffer .= ' ';
                    break;
                case 'strong':
                    $flushText();
                    $runs = array_merge($runs, $this->noteInlineRuns($inline->children, $style + ['bold' => true]));
                    break;
                case 'emph':
                    $flushText();
                    $runs = array_merge($runs, $this->noteInlineRuns($inline->children, $style + ['italic' => true]));
                    break;
                case 'underline':
                    $flushText();
                    $runs = array_merge($runs, $this->noteInlineRuns($inline->children, $style + ['underline' => true]));
                    break;
                case 'strikeout':
                    $flushText();
                    $runs = array_merge($runs, $this->noteInlineRuns($inline->children, $style + ['strike' => true]));
                    break;
                case 'small_caps':
                case 'smallcaps':
                    $flushText();
                    $runs = array_merge($runs, $this->noteInlineRuns($inline->children, $style + ['smallCaps' => true]));
                    break;
                case 'link':
                case 'span':
                case 'superscript':
                case 'subscript':
                case 'quoted':
                    $flushText();
                    $runs = array_merge($runs, $this->noteInlineRuns($inline->children, $style));
                    break;
                case 'code':
                    $code = (string) $inline->attr('text', '');
                    if ($code !== '') {
                        $flushText();
                        $runs[] = $this->runXml($code, $style + ['monospace' => true]);
                    }
                    break;
                case 'note':
                    break;
                case 'image':
                    $text = $this->imageFallbackText($inline);
                    if ($text !== '') {
                        $textBuffer .= $text;
                    }
                    break;
                default:
                    $text = $this->inlineText([$inline]);
                    if ($text !== '') {
                        $textBuffer .= $text;
                    }
                    break;
            }
        }
        $flushText();

        return $runs;
    }

    /**
     * @return array{title:string, creator:string, subject:string, description:string, category:string, modified:string, keywords:list<string>, customProperties:array<string, string>}
     */
    private function metadata(AstNode $document): array
    {
        $meta = $document->attr('meta', []);
        $meta = is_array($meta) && !array_is_list($meta) ? $meta : [];
        $title = $this->optionString('title')
            ?? $this->metaString($meta, ['title'])
            ?? $this->firstHeadingText($document)
            ?? 'Untitled';

        return [
            'title' => $title,
            'creator' => $this->optionString('author') ?? $this->metaString($meta, ['author', 'creator']) ?? 'Port Libs',
            'subject' => $this->optionString('subject') ?? $this->metaString($meta, ['subject']) ?? '',
            'description' => $this->optionString('description') ?? $this->metaString($meta, ['description']) ?? '',
            'category' => $this->optionString('category') ?? $this->metaString($meta, ['category']) ?? '',
            'modified' => $this->optionString('modified') ?? $this->metaString($meta, ['modified', 'date']) ?? self::GENERATED_TIMESTAMP,
            'keywords' => $this->optionStringList('keywords') ?: $this->metaStringList($meta, ['keywords', 'subject']),
            'customProperties' => $this->customProperties($meta),
        ];
    }

    private function optionString(string $key): ?string
    {
        $value = $this->options[$key] ?? null;

        return is_scalar($value) && (string) $value !== '' ? (string) $value : null;
    }

    /**
     * @return list<string>
     */
    private function optionStringList(string $key): array
    {
        $value = $this->options[$key] ?? null;
        if (is_string($value)) {
            return array_values(array_filter(array_map('trim', preg_split('/[,;]+/', $value) ?: [])));
        }
        if (!is_array($value)) {
            return [];
        }

        return array_values(array_filter(array_map(
            static fn (mixed $item): string => is_scalar($item) ? trim((string) $item) : '',
            $value
        )));
    }

    /**
     * @param array<string, mixed> $meta
     * @param list<string> $keys
     */
    private function metaString(array $meta, array $keys): ?string
    {
        foreach ($keys as $key) {
            if (!array_key_exists($key, $meta)) {
                continue;
            }
            $text = $this->stringFromMetaValue($meta[$key]);
            if ($text !== null && $text !== '') {
                return $text;
            }
        }

        return null;
    }

    /**
     * @param array<string, mixed> $meta
     * @param list<string> $keys
     * @return list<string>
     */
    private function metaStringList(array $meta, array $keys): array
    {
        foreach ($keys as $key) {
            if (!array_key_exists($key, $meta)) {
                continue;
            }
            $value = $meta[$key];
            if (is_array($value) && isset($value['type']) && $value['type'] === 'MetaList' && is_array($value['value'])) {
                $items = [];
                foreach ($value['value'] as $item) {
                    $text = $this->stringFromMetaValue($item);
                    if ($text !== null && $text !== '') {
                        $items[] = $text;
                    }
                }
                if ($items !== []) {
                    return $items;
                }
            }
            if (is_array($value) && array_is_list($value)) {
                $items = [];
                foreach ($value as $item) {
                    $text = $this->stringFromMetaValue($item);
                    if ($text !== null && $text !== '') {
                        $items[] = $text;
                    }
                }
                if ($items !== []) {
                    return $items;
                }
            }
            $text = $this->stringFromMetaValue($value);
            if ($text !== null && $text !== '') {
                return array_values(array_filter(array_map('trim', preg_split('/[,;]+/', $text) ?: [])));
            }
        }

        return [];
    }

    private function stringFromMetaValue(mixed $value): ?string
    {
        if ($value instanceof AstNode) {
            return $this->blockText($value);
        }
        if (is_scalar($value)) {
            return trim((string) $value);
        }
        if (!is_array($value)) {
            return null;
        }
        if (isset($value['type']) && array_key_exists('value', $value)) {
            return match ($value['type']) {
                'MetaInlines' => is_array($value['value']) ? $this->inlineText($value['value']) : null,
                'MetaBlocks' => is_array($value['value']) ? $this->metadataBlockText($value['value']) : null,
                'MetaList' => is_array($value['value']) ? $this->metadataListText($value['value'], '; ') : null,
                default => null,
            };
        }

        $parts = [];
        foreach ($value as $item) {
            $text = $this->stringFromMetaValue($item);
            if ($text !== null && $text !== '') {
                $parts[] = $text;
            }
        }

        return $parts === [] ? null : implode(' ', $parts);
    }

    /**
     * @param array<string, mixed> $meta
     * @return array<string, string>
     */
    private function customProperties(array $meta): array
    {
        $coreKeys = array_fill_keys([
            'author',
            'authorInlines',
            'category',
            'created',
            'creator',
            'date',
            'dateInlines',
            'description',
            'keywords',
            'lang',
            'language',
            'modified',
            'notes',
            'speaker-notes',
            'speakerNotes',
            'subject',
            'title',
            'titleInlines',
        ], true);

        $properties = [];
        foreach ($meta as $key => $value) {
            if (!is_string($key) || isset($coreKeys[$key])) {
                continue;
            }
            $properties[$key] = $this->customPropertyText($value);
        }

        return $properties;
    }

    private function customPropertyText(mixed $value, string $separator = '; '): string
    {
        if (is_array($value)) {
            if (isset($value['type']) && array_key_exists('value', $value)) {
                return match ($value['type']) {
                    'MetaInlines' => is_array($value['value']) ? $this->inlineText($value['value']) : '',
                    'MetaBlocks' => is_array($value['value']) ? $this->metadataBlockText($value['value']) : '',
                    'MetaList' => is_array($value['value']) ? $this->customPropertyListText($value['value'], $separator) : '',
                    default => '',
                };
            }

            return '';
        }

        return is_scalar($value) || $value === null ? (string) $value : '';
    }

    /**
     * @param list<mixed> $items
     */
    private function customPropertyListText(array $items, string $separator): string
    {
        $parts = [];
        foreach ($items as $item) {
            $part = $this->customPropertyText($item, $separator);
            if ($part !== '') {
                $parts[] = $part;
            }
        }

        return implode($separator, $parts);
    }

    /**
     * @param list<mixed> $items
     */
    private function metadataListText(array $items, string $separator): string
    {
        $parts = [];
        foreach ($items as $item) {
            $part = $this->stringFromMetaValue($item);
            if ($part !== null && $part !== '') {
                $parts[] = $part;
            }
        }

        return implode($separator, $parts);
    }

    /**
     * @param list<mixed> $blocks
     */
    private function metadataBlockText(array $blocks): string
    {
        $parts = [];
        foreach ($blocks as $block) {
            if (!$block instanceof AstNode) {
                continue;
            }
            $text = $this->blockText($block);
            if ($text !== '') {
                $parts[] = $text;
            }
        }

        return implode("_x000d_\n", $parts);
    }

    private function firstHeadingText(AstNode $node): ?string
    {
        if ($node->type === 'heading') {
            $text = $this->blockText($node);

            return $text === '' ? null : $text;
        }

        foreach ($node->children as $child) {
            $text = $this->firstHeadingText($child);
            if ($text !== null) {
                return $text;
            }
        }

        return null;
    }

    /**
     * @param list<AstNode> $inlines
     * @return list<AstNode>
     */
    private function textInlines(string $text): array
    {
        return $text === '' ? [] : [new AstNode('text', ['text' => $text])];
    }

    private function codeInline(string $text): AstNode
    {
        return new AstNode('code', ['text' => $text]);
    }

    /**
     * @param list<AstNode> $blocks
     */
    private function blockListText(array $blocks): string
    {
        $parts = [];
        foreach ($blocks as $block) {
            $text = $this->blockText($block);
            if ($text !== '') {
                $parts[] = $text;
            }
        }

        return implode(' ', $parts);
    }

    private function blockText(AstNode $node): string
    {
        if ($node->type === 'raw_inline' || $node->type === 'raw_block') {
            return $this->isOpenXmlRaw($node) ? (string) $node->attr('text', '') : '';
        }
        if ($node->type === 'raw_html' || $node->type === 'raw_html_inline' || $node->type === 'raw_tex' || $node->type === 'raw_tex_inline' || $node->type === 'raw_markdown') {
            return '';
        }
        if ($node->type === 'text' || $node->type === 'code' || $node->type === 'code_block') {
            return (string) $node->attr('text', '');
        }
        if ($node->type === 'softbreak' || $node->type === 'linebreak') {
            return ' ';
        }
        if ($node->type === 'image') {
            return $this->imageFallbackText($node);
        }
        $text = $this->inlineText($node->children);
        if ($text !== '') {
            return $text;
        }
        if (isset($node->attrs['text']) && is_scalar($node->attrs['text'])) {
            return (string) $node->attrs['text'];
        }

        return '';
    }

    /**
     * @param list<AstNode> $inlines
     */
    private function inlineText(array $inlines): string
    {
        $parts = [];
        foreach ($inlines as $inline) {
            $text = $this->blockText($inline);
            if ($text !== '') {
                $parts[] = $text;
            }
        }

        return trim(preg_replace('/\s+/u', ' ', implode('', $parts)) ?? implode('', $parts));
    }

    private function imageFallbackText(AstNode $image): string
    {
        $alt = (string) $image->attr('alt', $this->inlineText($image->children));
        return $alt;
    }

    private function shapeName(AstNode $node, string $fallback): string
    {
        foreach (['title', 'name', 'id'] as $key) {
            if (isset($node->attrs[$key]) && is_scalar($node->attrs[$key]) && (string) $node->attrs[$key] !== '') {
                return (string) $node->attrs[$key];
            }
        }

        return $fallback;
    }

    /**
     * @param list<array<string, mixed>> $parts
     * @return list<array{name:string, data:string, modifiedDosTime:int, modifiedDosDate:int}>
     */
    private function normalizePackageParts(array $parts): array
    {
        $normalized = [];
        $seen = [];
        foreach ($parts as $index => $part) {
            if (!isset($part['name']) || !is_string($part['name'])) {
                throw new \InvalidArgumentException("PPTX package part {$index} is missing a string name");
            }
            if (!array_key_exists('data', $part) || !is_string($part['data'])) {
                throw new \InvalidArgumentException("PPTX package part {$part['name']} is missing string data");
            }
            $name = self::normalizePackagePartName($part['name']);
            $key = strtolower($name);
            if (isset($seen[$key])) {
                throw new \InvalidArgumentException('Duplicate PPTX package part after normalization: ' . $name);
            }
            $seen[$key] = true;
            $normalized[] = [
                'name' => $name,
                'data' => $part['data'],
                'modifiedDosTime' => self::GENERATED_DOS_TIME,
                'modifiedDosDate' => self::GENERATED_DOS_DATE,
            ];
        }

        usort($normalized, static function (array $left, array $right): int {
            $leftOrder = self::CORE_PART_ORDER[$left['name']] ?? (str_starts_with($left['name'], 'ppt/slides/') ? 50 : 1000);
            $rightOrder = self::CORE_PART_ORDER[$right['name']] ?? (str_starts_with($right['name'], 'ppt/slides/') ? 50 : 1000);

            return $leftOrder <=> $rightOrder ?: strcmp($left['name'], $right['name']);
        });

        return $normalized;
    }

    public static function normalizePackagePartName(string $partName): string
    {
        $canonical = OpcPackagePath::canonicalPartNameFromUri($partName);
        if (strtolower($canonical) === '/[content_types].xml') {
            return '[Content_Types].xml';
        }

        return ltrim($canonical, '/');
    }

    private function contentTypeForExtension(string $extension): ?string
    {
        return match (strtolower($extension)) {
            'jpg', 'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'gif' => 'image/gif',
            'svg' => 'image/svg+xml',
            'webp' => 'image/webp',
            default => null,
        };
    }

    private function extensionForContentType(string $contentType): ?string
    {
        return match (strtolower($contentType)) {
            'image/jpeg', 'image/jpg' => 'jpg',
            'image/png' => 'png',
            'image/gif' => 'gif',
            'image/svg+xml' => 'svg',
            'image/webp' => 'webp',
            default => null,
        };
    }

    private function xml(string $value): string
    {
        return htmlspecialchars($value, ENT_XML1 | ENT_COMPAT | ENT_SUBSTITUTE, 'UTF-8');
    }
}
