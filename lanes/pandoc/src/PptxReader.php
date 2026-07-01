<?php

declare(strict_types=1);

namespace PortLibs\Pandoc;

final class PptxReader
{
    private const OFFICE_DOCUMENT_RELATIONSHIP = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument';
    private const RELATIONSHIP_NAMESPACE = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships';
    private const COMMENT_AUTHORS_PART = '/ppt/commentAuthors.xml';
    private const MAX_XML_PART_BYTES = 8_388_608;
    private const EMUS_PER_INCH = 914_400;
    private const DEFAULT_SLIDE_WIDTH_EMU = 9_144_000;
    private const DEFAULT_SLIDE_HEIGHT_EMU = 6_858_000;

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
        $slideSize = $this->presentationSlideSize($presentation);
        $presentationRelationships = $this->relationshipsOrEmpty($package, $presentationPart);
        $tableStyles = $this->presentationTableStyles($package, $presentationRelationships);
        $commentAuthors = $this->commentAuthors($package);

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
            $slideContext = $this->slideContext($package, $slideRelationships);
            $slideComments = $this->slideComments($package, $slideRelationships, $commentAuthors);
            $imageIssues = [];
            $slideBlocks = $this->slideToBlocks($package, $slide['index'], $slideDocument, $slideRelationships, $slideContext, $slideComments, $tableStyles, $imageIssues);
            foreach ($slideBlocks as $block) {
                $blocks[] = $block;
            }

            $richMedia = $this->collectRichMediaReviews($slideBlocks);
            $charts = $this->collectChartReviews($slideBlocks);
            $slideReviews[] = [
                'index' => $slide['index'],
                'relationshipId' => $slide['relationshipId'],
                'partName' => ltrim($slidePart, '/'),
                'blockCount' => count($slideBlocks),
                'context' => $this->slideContextReview($slideContext),
                'commentCount' => count($slideComments),
                'comments' => $slideComments,
                'imageIssueCount' => count($imageIssues),
                'imageIssues' => $imageIssues,
                'richMediaCount' => count($richMedia),
                'richMedia' => $richMedia,
                'chartCount' => count($charts),
                'charts' => $charts,
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
                'slideSize' => $slideSize,
                'slides' => $slideReviews,
                'commentAuthors' => $commentAuthors,
                'tableStyles' => $tableStyles,
                'payloadExposurePolicy' => 'xml-text-and-media-reference-only',
                'upstreamEvidence' => [
                    'denominator' => 1,
                    'covered' => 1,
                    'fixtures' => [
                        'test/pptx-reader/basic.pptx',
                        'test/pptx-reader/basic.native',
                    ],
                    'fixtureCommit' => '612e143fbe6d735b612c4800d21e61b7d44e4dca',
                    'source' => 'Pandoc test/Tests/Readers/Pptx.hs plus src/Text/Pandoc/Readers/Pptx.hs and src/Text/Pandoc/Readers/Pptx/{Parse,Slides,Shapes,SmartArt}.hs',
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
     * @return array{cx:int, cy:int, width:int, height:int, emusPerInch:int, source:string}
     */
    private function presentationSlideSize(\DOMDocument $document): array
    {
        $root = XmlHtmlDom::rootElement($document, 'presentation');
        if (!$root instanceof \DOMElement) {
            throw new \RuntimeException('PPTX presentation XML must have a presentation root');
        }

        $sizeElement = $this->firstChildElement($root, 'sldSz');
        $source = 'default';
        $widthEmu = self::DEFAULT_SLIDE_WIDTH_EMU;
        $heightEmu = self::DEFAULT_SLIDE_HEIGHT_EMU;
        if ($sizeElement instanceof \DOMElement) {
            $source = 'presentation';
            $widthEmu = $this->presentationSizeAttribute($sizeElement, 'cx');
            $heightEmu = $this->presentationSizeAttribute($sizeElement, 'cy');
        }

        return [
            'cx' => $widthEmu,
            'cy' => $heightEmu,
            'width' => intdiv($widthEmu, self::EMUS_PER_INCH),
            'height' => intdiv($heightEmu, self::EMUS_PER_INCH),
            'emusPerInch' => self::EMUS_PER_INCH,
            'source' => $source,
        ];
    }

    private function presentationSizeAttribute(\DOMElement $element, string $name): int
    {
        return $this->integerAttribute($element, $name) ?? 0;
    }

    /**
     * @param array<string, mixed> $slideContext
     * @param list<array<string, mixed>> $slideComments
     * @param array<string, mixed> $tableStyles
     * @param list<array<string, mixed>> $imageIssues
     * @return list<AstNode>
     */
    private function slideToBlocks(ZipPackage $package, int $slideIndex, \DOMDocument $document, OpcRelationships $slideRelationships, array $slideContext, array $slideComments, array $tableStyles, array &$imageIssues): array
    {
        $root = XmlHtmlDom::rootElement($document, 'sld');
        if (!$root instanceof \DOMElement) {
            throw new \RuntimeException('PPTX slide XML must have a slide root');
        }

        $title = $this->slideTitle($root);
        if ($title === '') {
            $title = (string) ($slideContext['layoutTitle'] ?? $slideContext['masterTitle'] ?? '');
        }
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

        $zOrder = 0;
        foreach ($this->childElements($spTree, null) as $shapeElement) {
            if (!$this->isDrawableShapeElement($shapeElement)) {
                continue;
            }
            $zOrder++;
            if ($this->isTitlePlaceholder($shapeElement)) {
                continue;
            }
            foreach ($this->shapeToBlocks($package, $shapeElement, $slideRelationships, $slideContext, $tableStyles, $zOrder, $imageIssues) as $block) {
                $blocks[] = $block;
            }
        }

        foreach ($this->commentsToBlocks($slideComments) as $commentBlock) {
            $blocks[] = $commentBlock;
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
     * @return array<string, mixed>
     */
    private function slideContext(ZipPackage $package, OpcRelationships $slideRelationships): array
    {
        $context = [
            'layoutPart' => '',
            'masterPart' => '',
            'layoutTitle' => '',
            'masterTitle' => '',
            'themePart' => '',
            'theme' => [],
            'layoutPlaceholders' => [],
            'masterPlaceholders' => [],
        ];

        $layoutRelationship = $this->firstRelationshipWithTypeSuffix($slideRelationships, '/slideLayout');
        if (!$layoutRelationship instanceof OpcRelationship || $layoutRelationship->isExternal()) {
            return $context;
        }

        $layoutPart = OpcPackagePath::stripQueryAndFragment($slideRelationships->resolveTarget($layoutRelationship));
        $context['layoutPart'] = ltrim($layoutPart, '/');
        $layoutDocument = $this->optionalPackageXml($package, $layoutPart, 'PPTX slide layout');
        if (!$layoutDocument instanceof \DOMDocument) {
            return $context;
        }

        $layoutRoot = XmlHtmlDom::rootElement($layoutDocument);
        if ($layoutRoot instanceof \DOMElement) {
            $context['layoutTitle'] = $this->slideTitle($layoutRoot);
            $context['layoutPlaceholders'] = $this->placeholderBlocksByKey($layoutRoot);
        }

        $layoutRelationships = $this->relationshipsOrEmpty($package, $layoutPart);
        $masterRelationship = $this->firstRelationshipWithTypeSuffix($layoutRelationships, '/slideMaster');
        if (!$masterRelationship instanceof OpcRelationship || $masterRelationship->isExternal()) {
            return $context;
        }

        $masterPart = OpcPackagePath::stripQueryAndFragment($layoutRelationships->resolveTarget($masterRelationship));
        $context['masterPart'] = ltrim($masterPart, '/');
        $masterDocument = $this->optionalPackageXml($package, $masterPart, 'PPTX slide master');
        if (!$masterDocument instanceof \DOMDocument) {
            return $context;
        }

        $masterRoot = XmlHtmlDom::rootElement($masterDocument);
        if ($masterRoot instanceof \DOMElement) {
            $context['masterTitle'] = $this->slideTitle($masterRoot);
            $context['masterPlaceholders'] = $this->placeholderBlocksByKey($masterRoot);
        }

        $masterRelationships = $this->relationshipsOrEmpty($package, $masterPart);
        $themeRelationship = $this->firstRelationshipWithTypeSuffix($masterRelationships, '/theme');
        if (!$themeRelationship instanceof OpcRelationship || $themeRelationship->isExternal()) {
            return $context;
        }

        $themePart = OpcPackagePath::stripQueryAndFragment($masterRelationships->resolveTarget($themeRelationship));
        $context['themePart'] = ltrim($themePart, '/');
        $themeDocument = $this->optionalPackageXml($package, $themePart, 'PPTX theme');
        if (!$themeDocument instanceof \DOMDocument) {
            return $context;
        }

        $themeRoot = XmlHtmlDom::rootElement($themeDocument, 'theme');
        if ($themeRoot instanceof \DOMElement) {
            $context['theme'] = $this->themeSummary($themeRoot);
        }

        return $context;
    }

    /**
     * @param array<string, mixed> $slideContext
     * @return array<string, mixed>
     */
    private function slideContextReview(array $slideContext): array
    {
        $review = [];
        foreach (['layoutPart', 'masterPart', 'themePart'] as $key) {
            $value = (string) ($slideContext[$key] ?? '');
            if ($value !== '') {
                $review[$key] = $value;
            }
        }

        $layoutPlaceholderKeys = $this->placeholderBucketKeys($slideContext['layoutPlaceholders'] ?? []);
        if ($layoutPlaceholderKeys !== []) {
            $review['layoutPlaceholderCount'] = count($layoutPlaceholderKeys);
            $review['layoutPlaceholderKeys'] = $layoutPlaceholderKeys;
        }

        $masterPlaceholderKeys = $this->placeholderBucketKeys($slideContext['masterPlaceholders'] ?? []);
        if ($masterPlaceholderKeys !== []) {
            $review['masterPlaceholderCount'] = count($masterPlaceholderKeys);
            $review['masterPlaceholderKeys'] = $masterPlaceholderKeys;
        }

        $theme = $slideContext['theme'] ?? [];
        if (is_array($theme) && $theme !== []) {
            $review['theme'] = $theme;
        }

        return $review;
    }

    /**
     * @return array<string, mixed>
     */
    private function presentationTableStyles(ZipPackage $package, OpcRelationships $presentationRelationships): array
    {
        $relationship = $this->firstRelationshipWithTypeSuffix($presentationRelationships, '/tableStyles');
        if (!$relationship instanceof OpcRelationship) {
            return [];
        }

        $summary = [
            'relationshipId' => $relationship->id,
            'relationshipType' => $relationship->type,
            'target' => $relationship->target,
            'external' => $relationship->isExternal(),
            'partName' => '',
            'defaultStyleId' => '',
            'styleCount' => 0,
            'styles' => [],
            'issues' => [],
        ];

        if ($relationship->isExternal()) {
            $summary['externalTargetPolicy'] = $relationship->externalTargetPreflight();
            $summary['issues'][] = 'external-table-styles-part';

            return $summary;
        }

        $partName = OpcPackagePath::stripQueryAndFragment($presentationRelationships->resolveTarget($relationship));
        $summary['partName'] = ltrim($partName, '/');
        $document = $this->optionalPackageXml($package, $partName, 'PPTX table styles');
        if (!$document instanceof \DOMDocument) {
            $summary['issues'][] = 'missing-or-invalid-table-styles-part';

            return $summary;
        }

        $root = XmlHtmlDom::rootElement($document, 'tblStyleLst');
        if (!$root instanceof \DOMElement) {
            $summary['issues'][] = 'unexpected-table-styles-root';

            return $summary;
        }

        $defaultStyleId = trim($root->getAttribute('def'));
        if ($defaultStyleId !== '') {
            $summary['defaultStyleId'] = $defaultStyleId;
        }

        $styles = [];
        foreach ($this->childElements($root, 'tblStyle') as $styleElement) {
            $styleId = trim($styleElement->getAttribute('styleId'));
            if ($styleId === '') {
                continue;
            }

            $style = [
                'id' => $styleId,
                'sourcePart' => (string) $summary['partName'],
                'relationshipId' => $relationship->id,
            ];
            $name = trim($styleElement->getAttribute('styleName'));
            if ($name !== '') {
                $style['name'] = $name;
            }
            if ($defaultStyleId !== '' && $styleId === $defaultStyleId) {
                $style['default'] = true;
            }

            $styleParts = $this->tableStyleDefinitionParts($styleElement);
            if ($styleParts !== []) {
                $style['parts'] = $styleParts;
            }

            $styles[$styleId] = $style;
        }
        ksort($styles, SORT_STRING);

        $summary['styles'] = $styles;
        $summary['styleCount'] = count($styles);

        return $summary;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function tableStyleDefinitionParts(\DOMElement $styleElement): array
    {
        $parts = [];
        foreach ($this->childElements($styleElement, null) as $partElement) {
            if ($partElement->localName === 'extLst') {
                continue;
            }

            $part = $this->tableStylePartMetadata($partElement);
            if ($part !== []) {
                $parts[$partElement->localName] = $part;
            }
        }

        return $parts;
    }

    /**
     * @return array<string, mixed>
     */
    private function tableStylePartMetadata(\DOMElement $partElement): array
    {
        $part = [];
        $textStyle = $this->firstChildElement($partElement, 'tcTxStyle');
        if ($textStyle instanceof \DOMElement) {
            $metadata = $this->tableTextStyleMetadata($textStyle);
            if ($metadata !== []) {
                $part['text'] = $metadata;
            }
        }

        $cellStyle = $this->firstChildElement($partElement, 'tcStyle');
        if ($cellStyle instanceof \DOMElement) {
            $metadata = $this->tableCellStyleContainerMetadata($cellStyle);
            if ($metadata !== []) {
                $part['cell'] = $metadata;
            }
        }

        $background = $this->firstChildElement($partElement, 'fill') ?? $this->firstChildElement($partElement, 'fillRef');
        if ($background instanceof \DOMElement) {
            $color = $this->drawingColorValue($background);
            if ($color !== '') {
                $part['fillColor'] = $color;
            }
        }

        return $part;
    }

    /**
     * @return list<string>
     */
    private function placeholderBucketKeys(mixed $placeholders): array
    {
        if (!is_array($placeholders)) {
            return [];
        }

        $keys = array_values(array_filter(array_keys($placeholders), static fn (int|string $key): bool => is_string($key) && $key !== ''));
        sort($keys, SORT_STRING);

        return $keys;
    }

    /**
     * @return array<string, mixed>
     */
    private function themeSummary(\DOMElement $themeRoot): array
    {
        $theme = [];
        $name = trim($themeRoot->getAttribute('name'));
        if ($name !== '') {
            $theme['name'] = $name;
        }

        $colorScheme = $this->themeColorScheme($themeRoot);
        if ($colorScheme !== []) {
            $theme['colorScheme'] = $colorScheme;
        }

        $fontScheme = $this->themeFontScheme($themeRoot);
        if ($fontScheme !== []) {
            $theme['fontScheme'] = $fontScheme;
        }

        return $theme;
    }

    /**
     * @return array<string, mixed>
     */
    private function themeColorScheme(\DOMElement $themeRoot): array
    {
        $colorScheme = $this->firstDescendantElement($themeRoot, 'clrScheme');
        if (!$colorScheme instanceof \DOMElement) {
            return [];
        }

        $colors = [];
        foreach ($this->childElements($colorScheme, null) as $colorElement) {
            if (!in_array($colorElement->localName, ['dk1', 'lt1', 'dk2', 'lt2', 'accent1', 'accent2', 'accent3', 'accent4', 'accent5', 'accent6', 'hlink', 'folHlink'], true)) {
                continue;
            }

            $value = $this->drawingColorValue($colorElement);
            if ($value !== '') {
                $colors[$colorElement->localName] = $value;
            }
        }

        if ($colors === []) {
            return [];
        }

        $scheme = ['colors' => $colors];
        $name = trim($colorScheme->getAttribute('name'));
        if ($name !== '') {
            $scheme = ['name' => $name] + $scheme;
        }

        return $scheme;
    }

    /**
     * @return array<string, string>
     */
    private function themeFontScheme(\DOMElement $themeRoot): array
    {
        $fontScheme = $this->firstDescendantElement($themeRoot, 'fontScheme');
        if (!$fontScheme instanceof \DOMElement) {
            return [];
        }

        $fonts = [];
        $name = trim($fontScheme->getAttribute('name'));
        if ($name !== '') {
            $fonts['name'] = $name;
        }

        foreach (['majorFont' => 'major', 'minorFont' => 'minor'] as $elementName => $prefix) {
            $fontElement = $this->firstChildElement($fontScheme, $elementName);
            if (!$fontElement instanceof \DOMElement) {
                continue;
            }

            foreach (['latin' => 'Latin', 'ea' => 'EastAsia', 'cs' => 'ComplexScript'] as $source => $target) {
                $sourceElement = $this->firstChildElement($fontElement, $source);
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

    private function optionalPackageXml(ZipPackage $package, string $partName, string $label): ?\DOMDocument
    {
        try {
            return $this->loadPackageXml($package, $partName, $label);
        } catch (\InvalidArgumentException | \RuntimeException) {
            return null;
        }
    }

    /**
     * @return array<string, array<string, string>>
     */
    private function commentAuthors(ZipPackage $package): array
    {
        if (!$package->has(self::COMMENT_AUTHORS_PART)) {
            return [];
        }

        $document = $this->optionalPackageXml($package, self::COMMENT_AUTHORS_PART, 'PPTX comment authors');
        if (!$document instanceof \DOMDocument) {
            return [];
        }

        $root = XmlHtmlDom::rootElement($document);
        if (!$root instanceof \DOMElement) {
            return [];
        }

        $authors = [];
        foreach ($this->childElements($root, 'cmAuthor') as $authorElement) {
            $id = $authorElement->getAttribute('id');
            if ($id === '') {
                continue;
            }

            $author = [];
            foreach (['name', 'initials', 'lastIdx'] as $attribute) {
                $value = $authorElement->getAttribute($attribute);
                if ($value !== '') {
                    $author[$attribute] = $value;
                }
            }
            $authors[$id] = $author;
        }

        return $authors;
    }

    /**
     * @param array<string, array<string, string>> $commentAuthors
     * @return list<array<string, mixed>>
     */
    private function slideComments(ZipPackage $package, OpcRelationships $slideRelationships, array $commentAuthors): array
    {
        $comments = [];
        foreach ($slideRelationships->all() as $relationship) {
            if (!str_ends_with($relationship->type, '/comments') || $relationship->isExternal()) {
                continue;
            }

            $partName = OpcPackagePath::stripQueryAndFragment($slideRelationships->resolveTarget($relationship));
            $document = $this->optionalPackageXml($package, $partName, 'PPTX slide comments');
            if (!$document instanceof \DOMDocument) {
                continue;
            }

            $root = XmlHtmlDom::rootElement($document);
            if (!$root instanceof \DOMElement) {
                continue;
            }

            $index = 0;
            foreach ($root->getElementsByTagName('*') as $commentElement) {
                if (!$commentElement instanceof \DOMElement || !in_array($commentElement->localName, ['cm', 'comment'], true)) {
                    continue;
                }

                $index++;
                $authorId = $commentElement->getAttribute('authorId');
                $author = $commentAuthors[$authorId] ?? [];
                $text = $this->commentText($commentElement);
                if ($text === '') {
                    continue;
                }

                $comment = [
                    'id' => $commentElement->getAttribute('idx') !== '' ? $commentElement->getAttribute('idx') : ltrim($partName, '/') . '#' . $index,
                    'authorId' => $authorId,
                    'author' => $author['name'] ?? '',
                    'initials' => $author['initials'] ?? '',
                    'date' => $commentElement->getAttribute('dt'),
                    'text' => $text,
                    'partName' => ltrim($partName, '/'),
                ];

                $position = $this->firstChildElement($commentElement, 'pos');
                if ($position instanceof \DOMElement) {
                    foreach (['x', 'y'] as $attribute) {
                        $value = $this->integerAttribute($position, $attribute);
                        if ($value !== null) {
                            $comment[$attribute] = $value;
                        }
                    }
                }

                $comments[] = $comment;
            }
        }

        return $comments;
    }

    private function commentText(\DOMElement $commentElement): string
    {
        $textElement = $this->firstChildElement($commentElement, 'text');
        if ($textElement instanceof \DOMElement) {
            return trim($this->allDescendantText($textElement));
        }

        return trim($this->drawingText($commentElement));
    }

    /**
     * @param list<array<string, mixed>> $comments
     * @return list<AstNode>
     */
    private function commentsToBlocks(array $comments): array
    {
        if ($comments === []) {
            return [];
        }

        $children = [];
        foreach ($comments as $comment) {
            $id = (string) ($comment['id'] ?? '');
            $author = (string) ($comment['author'] ?? '');
            $date = (string) ($comment['date'] ?? '');
            $text = (string) ($comment['text'] ?? '');
            $label = $author !== '' ? 'Comment by ' . $author : 'Comment';
            $children[] = new AstNode('paragraph', ['text' => $label . ': ' . $text], [
                new AstNode('span', [
                    'classes' => ['comment-start'],
                    'attributes' => array_filter([
                        'id' => $id,
                        'author' => $author,
                        'date' => $date,
                    ], static fn (string $value): bool => $value !== ''),
                ], $this->textInlines($label)),
                new AstNode('text', ['text' => ': ' . $text]),
            ]);
        }

        return [new AstNode('div', [
            'classes' => ['pptx-comments'],
            'attributes' => ['count' => (string) count($comments)],
            'pptxComments' => $comments,
        ], $children)];
    }

    /**
     * @param list<AstNode> $blocks
     * @return list<array<string, string|bool>>
     */
    private function collectRichMediaReviews(array $blocks): array
    {
        $media = [];
        $seen = [];
        foreach ($blocks as $block) {
            foreach ($this->collectRichMediaReviewsFromNode($block) as $record) {
                $key = (string) ($record['relationshipId'] ?? '') . "\0" . (string) ($record['partName'] ?? '') . "\0" . (string) ($record['target'] ?? '');
                if (isset($seen[$key])) {
                    continue;
                }
                $seen[$key] = true;
                $media[] = $record;
            }
        }

        return $media;
    }

    /**
     * @return list<array<string, string|bool>>
     */
    private function collectRichMediaReviewsFromNode(AstNode $node): array
    {
        $media = [];
        $record = $node->attr('pptxMedia');
        if (is_array($record)) {
            $media[] = $record;
        }
        foreach ($node->children as $child) {
            array_push($media, ...$this->collectRichMediaReviewsFromNode($child));
        }

        return $media;
    }

    /**
     * @param list<AstNode> $blocks
     * @return list<array<string, mixed>>
     */
    private function collectChartReviews(array $blocks): array
    {
        $charts = [];
        $seen = [];
        foreach ($blocks as $block) {
            foreach ($this->collectChartReviewsFromNode($block) as $record) {
                $key = (string) ($record['relationshipId'] ?? '') . "\0" . (string) ($record['partName'] ?? '') . "\0" . (string) ($record['title'] ?? '');
                if (isset($seen[$key])) {
                    continue;
                }
                $seen[$key] = true;
                $charts[] = $record;
            }
        }

        return $charts;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function collectChartReviewsFromNode(AstNode $node): array
    {
        $charts = [];
        $record = $node->attr('pptxChart');
        if (is_array($record)) {
            $charts[] = $record;
        }
        foreach ($node->children as $child) {
            array_push($charts, ...$this->collectChartReviewsFromNode($child));
        }

        return $charts;
    }

    private function firstRelationshipWithTypeSuffix(OpcRelationships $relationships, string $suffix): ?OpcRelationship
    {
        foreach ($relationships->all() as $relationship) {
            if (str_ends_with($relationship->type, $suffix)) {
                return $relationship;
            }
        }

        return null;
    }

    /**
     * @return array<string, list<AstNode>>
     */
    private function placeholderBlocksByKey(\DOMElement $root): array
    {
        $spTree = $this->shapeTree($root);
        if (!$spTree instanceof \DOMElement) {
            return [];
        }

        $placeholders = [];
        foreach ($this->childElements($spTree, 'sp') as $shapeElement) {
            $keys = $this->placeholderLookupKeys($shapeElement);
            if ($keys === []) {
                continue;
            }
            $textBody = $this->firstChildElement($shapeElement, 'txBody');
            if (!$textBody instanceof \DOMElement) {
                continue;
            }
            $paragraphs = $this->parseParagraphs($textBody);
            if (!$this->paragraphsContainText($paragraphs)) {
                continue;
            }
            $blocks = $this->paragraphsToBlocks($paragraphs);
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
     * @return list<AstNode>
     */
    private function inheritedPlaceholderBlocks(\DOMElement $shapeElement, array $slideContext): array
    {
        $keys = $this->placeholderLookupKeys($shapeElement);
        if ($keys === []) {
            return [];
        }

        foreach (['layoutPlaceholders', 'masterPlaceholders'] as $bucket) {
            $placeholders = $slideContext[$bucket] ?? [];
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

    /**
     * @return list<string>
     */
    private function placeholderLookupKeys(\DOMElement $shapeElement): array
    {
        $placeholder = $this->placeholderElement($shapeElement);
        if (!$placeholder instanceof \DOMElement) {
            return [];
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

    private function placeholderElement(\DOMElement $shapeElement): ?\DOMElement
    {
        foreach ($this->childElements($shapeElement, null) as $child) {
            if (!str_starts_with($child->localName, 'nv')) {
                continue;
            }

            $nonVisualProperties = $this->firstChildElement($child, 'nvPr');

            return $nonVisualProperties instanceof \DOMElement ? $this->firstChildElement($nonVisualProperties, 'ph') : null;
        }

        return null;
    }

    /**
     * @param array<string, mixed> $slideContext
     * @param array<string, mixed> $tableStyles
     * @param list<array<string, mixed>> $imageIssues
     * @return list<AstNode>
     */
    private function shapeToBlocks(ZipPackage $package, \DOMElement $shapeElement, OpcRelationships $slideRelationships, array $slideContext, array $tableStyles, int $zOrder, array &$imageIssues): array
    {
        if ($shapeElement->localName === 'sp') {
            $textBody = $this->firstChildElement($shapeElement, 'txBody');
            if (!$textBody instanceof \DOMElement) {
                return $this->withShapeMetadata(
                    $this->inheritedPlaceholderBlocks($shapeElement, $slideContext),
                    $shapeElement,
                    $zOrder
                );
            }

            $paragraphs = $this->parseParagraphs($textBody);
            if ($this->paragraphsContainText($paragraphs)) {
                return $this->withShapeMetadata(
                    $this->paragraphsToBlocks($paragraphs),
                    $shapeElement,
                    $zOrder
                );
            }

            $blocks = $this->inheritedPlaceholderBlocks($shapeElement, $slideContext);
            $blocks = $blocks !== [] ? $blocks : $this->paragraphsToBlocks($paragraphs);

            return $this->withShapeMetadata($blocks, $shapeElement, $zOrder);
        }

        if ($shapeElement->localName === 'pic') {
            $image = $this->pictureNode($package, $shapeElement, $slideRelationships, $imageIssues);
            $blocks = $image instanceof AstNode ? [new AstNode('paragraph', [], [$image])] : [];
            foreach ($this->richMediaBlocks($shapeElement, $slideRelationships) as $mediaBlock) {
                $blocks[] = $mediaBlock;
            }

            return $this->withShapeMetadata($blocks, $shapeElement, $zOrder);
        }

        if ($shapeElement->localName !== 'graphicFrame') {
            return $this->withShapeMetadata($this->richMediaBlocks($shapeElement, $slideRelationships), $shapeElement, $zOrder);
        }

        $graphicData = $this->graphicDataElement($shapeElement);
        if (!$graphicData instanceof \DOMElement) {
            return $this->withShapeMetadata($this->richMediaBlocks($shapeElement, $slideRelationships), $shapeElement, $zOrder);
        }

        $uri = $graphicData->getAttribute('uri');
        if (str_contains($uri, 'table')) {
            $table = $this->firstDescendantElement($graphicData, 'tbl');

            return $this->withShapeMetadata($table instanceof \DOMElement ? [$this->tableNode($table, $tableStyles, $slideContext)] : [], $shapeElement, $zOrder);
        }
        if (str_contains($uri, 'chart')) {
            $chart = $this->chartNode($package, $graphicData, $slideRelationships);

            return $this->withShapeMetadata($chart instanceof AstNode ? [$chart] : [], $shapeElement, $zOrder);
        }
        if (str_contains($uri, 'diagram')) {
            $diagram = $this->diagramNode($package, $graphicData, $slideRelationships);

            return $this->withShapeMetadata($diagram instanceof AstNode ? [$diagram] : [], $shapeElement, $zOrder);
        }

        return $this->withShapeMetadata([$this->paragraph('[Graphic: other: ' . $uri . ']')], $shapeElement, $zOrder);
    }

    private function isDrawableShapeElement(\DOMElement $element): bool
    {
        return in_array($element->localName, ['sp', 'pic', 'graphicFrame', 'grpSp', 'cxnSp', 'contentPart'], true);
    }

    /**
     * @param list<AstNode> $blocks
     * @return list<AstNode>
     */
    private function withShapeMetadata(array $blocks, \DOMElement $shapeElement, int $zOrder): array
    {
        if ($blocks === []) {
            return [];
        }

        $metadata = $this->shapeMetadata($shapeElement, $zOrder);

        return array_map(fn (AstNode $block): AstNode => $this->withShapeMetadataNode($block, $metadata), $blocks);
    }

    /**
     * @param array<string, mixed> $metadata
     */
    private function withShapeMetadataNode(AstNode $node, array $metadata): AstNode
    {
        $attrs = $node->attrs;
        $existing = $attrs['pptxShape'] ?? [];
        $attrs['pptxShape'] = is_array($existing) ? array_replace($metadata, $existing) : $metadata;

        $children = [];
        $changed = false;
        foreach ($node->children as $child) {
            if ($child->type === 'image') {
                $children[] = $this->withShapeMetadataNode($child, $metadata);
                $changed = true;
                continue;
            }

            $children[] = $child;
        }

        return new AstNode($node->type, $attrs, $changed ? $children : $node->children);
    }

    /**
     * @return array<string, mixed>
     */
    private function shapeMetadata(\DOMElement $shapeElement, int $zOrder): array
    {
        $metadata = [
            'element' => $shapeElement->localName,
            'zOrder' => $zOrder,
        ];

        $properties = $this->nonVisualDrawingProperties($shapeElement);
        if ($properties instanceof \DOMElement) {
            foreach (['id', 'name', 'descr', 'title'] as $attribute) {
                $value = $properties->getAttribute($attribute);
                if ($value !== '') {
                    $metadata[$attribute] = $value;
                }
            }
        }

        $placeholder = $this->placeholderElement($shapeElement);
        if ($placeholder instanceof \DOMElement) {
            $type = $placeholder->getAttribute('type') !== '' ? $placeholder->getAttribute('type') : 'obj';
            $metadata['placeholderType'] = $type;
            if ($placeholder->getAttribute('idx') !== '') {
                $metadata['placeholderIndex'] = $placeholder->getAttribute('idx');
            }
        }

        $layout = $this->shapeTransformMetadata($shapeElement);
        if ($layout !== []) {
            $metadata['layout'] = $layout;
        }

        return $metadata;
    }

    private function nonVisualDrawingProperties(\DOMElement $shapeElement): ?\DOMElement
    {
        foreach ($this->childElements($shapeElement, null) as $child) {
            if (!str_starts_with($child->localName, 'nv')) {
                continue;
            }

            $properties = $this->firstChildElement($child, 'cNvPr');
            if ($properties instanceof \DOMElement) {
                return $properties;
            }
        }

        return null;
    }

    /**
     * @return array<string, int>
     */
    private function shapeTransformMetadata(\DOMElement $shapeElement): array
    {
        $properties = $this->firstChildElement($shapeElement, 'spPr')
            ?? $this->firstChildElement($shapeElement, 'grpSpPr')
            ?? $shapeElement;
        $transform = $this->firstChildElement($properties, 'xfrm') ?? $this->firstChildElement($shapeElement, 'xfrm');
        if (!$transform instanceof \DOMElement) {
            return [];
        }

        $layout = [];
        $offset = $this->firstChildElement($transform, 'off');
        if ($offset instanceof \DOMElement) {
            foreach (['x', 'y'] as $attribute) {
                $value = $this->integerAttribute($offset, $attribute);
                if ($value !== null) {
                    $layout[$attribute] = $value;
                }
            }
        }

        $extent = $this->firstChildElement($transform, 'ext');
        if ($extent instanceof \DOMElement) {
            foreach (['cx', 'cy'] as $attribute) {
                $value = $this->integerAttribute($extent, $attribute);
                if ($value !== null) {
                    $layout[$attribute] = $value;
                }
            }
        }

        $rotation = $this->integerAttribute($transform, 'rot');
        if ($rotation !== null) {
            $layout['rot'] = $rotation;
        }

        return $layout;
    }

    private function integerAttribute(\DOMElement $element, string $name): ?int
    {
        $value = $element->getAttribute($name);

        return preg_match('/^-?\d+$/', $value) === 1 ? (int) $value : null;
    }

    private function xmlBooleanAttribute(\DOMElement $element, string $name): ?bool
    {
        if (!$element->hasAttribute($name)) {
            return null;
        }

        return $this->xmlBooleanValue($element->getAttribute($name));
    }

    private function xmlBooleanValue(string $value): bool
    {
        $value = strtolower(trim($value));

        return in_array($value, ['1', 'true', 'on'], true);
    }

    private function drawingColorValue(\DOMElement $container): string
    {
        $value = $this->drawingColorValueFromElement($container);
        if ($value !== '') {
            return $value;
        }

        foreach ($container->getElementsByTagName('*') as $element) {
            if (!$element instanceof \DOMElement) {
                continue;
            }

            $value = $this->drawingColorValueFromElement($element);
            if ($value !== '') {
                return $value;
            }
        }

        return '';
    }

    private function drawingColorValueFromElement(\DOMElement $element): string
    {
        if ($element->localName === 'srgbClr') {
            $value = strtoupper(trim($element->getAttribute('val')));

            return preg_match('/^[0-9A-F]{6}$/', $value) === 1 ? $value : '';
        }

        if ($element->localName === 'sysClr') {
            $value = strtoupper(trim($element->getAttribute('lastClr')));
            if (preg_match('/^[0-9A-F]{6}$/', $value) === 1) {
                return $value;
            }

            $system = trim($element->getAttribute('val'));

            return $system !== '' ? 'system:' . $system : '';
        }

        if ($element->localName === 'schemeClr') {
            $value = trim($element->getAttribute('val'));

            return $value !== '' ? 'theme:' . $value : '';
        }

        if ($element->localName === 'prstClr') {
            $value = trim($element->getAttribute('val'));

            return $value !== '' ? 'preset:' . $value : '';
        }

        return '';
    }

    /**
     * @return list<AstNode>
     */
    private function richMediaBlocks(\DOMElement $shapeElement, OpcRelationships $slideRelationships): array
    {
        $blocks = [];
        foreach ($this->richMediaReferences($shapeElement, $slideRelationships) as $media) {
            $label = $this->richMediaLabel($media);
            $attributes = [
                'kind' => (string) $media['kind'],
                'relationship-id' => (string) $media['relationshipId'],
            ];
            if (($media['partName'] ?? '') !== '') {
                $attributes['src'] = (string) $media['partName'];
            } else {
                $attributes['target'] = (string) $media['target'];
                $attributes['target-mode'] = 'external';
            }

            $blocks[] = new AstNode('div', [
                'classes' => ['pptx-rich-media', 'pptx-' . (string) $media['kind']],
                'attributes' => $attributes,
                'pptxMedia' => $media,
            ], [$this->paragraph('[PPTX ' . (string) $media['kind'] . ': ' . $label . ']')]);
        }

        return $blocks;
    }

    /**
     * @return list<array<string, string|bool>>
     */
    private function richMediaReferences(\DOMElement $shapeElement, OpcRelationships $slideRelationships): array
    {
        $media = [];
        $seen = [];
        foreach ($shapeElement->getElementsByTagName('*') as $element) {
            if (!$element instanceof \DOMElement || !$this->isRichMediaElement($element)) {
                continue;
            }

            foreach (['embed', 'link', 'id'] as $relationshipAttribute) {
                $relationshipId = $this->relationshipId($element, $relationshipAttribute);
                if ($relationshipId === '' || isset($seen[$relationshipId])) {
                    continue;
                }

                $relationship = $slideRelationships->byId($relationshipId);
                if (!$relationship instanceof OpcRelationship) {
                    continue;
                }

                $kind = $this->relationshipRichMediaKind($relationship, $element);
                if ($kind === '') {
                    continue;
                }

                $seen[$relationshipId] = true;
                $partName = '';
                if (!$relationship->isExternal()) {
                    $partName = ltrim(OpcPackagePath::stripQueryAndFragment($slideRelationships->resolveTarget($relationship)), '/');
                }

                $media[] = [
                    'kind' => $kind,
                    'relationshipId' => $relationshipId,
                    'relationshipType' => $relationship->type,
                    'target' => $relationship->target,
                    'partName' => $partName,
                    'external' => $relationship->isExternal(),
                ];
            }
        }

        return $media;
    }

    private function isRichMediaElement(\DOMElement $element): bool
    {
        return in_array($element->localName, ['audioFile', 'videoFile', 'wavAudioFile', 'media', 'audio', 'video'], true);
    }

    private function relationshipRichMediaKind(OpcRelationship $relationship, \DOMElement $element): string
    {
        $target = strtolower($relationship->target);
        $haystack = strtolower($relationship->type . ' ' . $target . ' ' . $element->localName);
        if (str_contains($haystack, 'audio') || str_contains($haystack, 'wav') || preg_match('/\.(?:aac|m4a|mp3|oga|ogg|wav|wma)(?:$|[?#])/i', $target) === 1) {
            return 'audio';
        }
        if (str_contains($haystack, 'video') || str_contains($haystack, 'movie') || preg_match('/\.(?:avi|m4v|mov|mp4|mpeg|mpg|ogv|webm|wmv)(?:$|[?#])/i', $target) === 1) {
            return 'video';
        }

        return str_contains($haystack, '/media') || $element->localName === 'media' ? 'media' : '';
    }

    /**
     * @param array<string, string|bool> $media
     */
    private function richMediaLabel(array $media): string
    {
        $partName = (string) ($media['partName'] ?? '');
        if ($partName !== '') {
            $base = basename($partName);

            return $base === '' ? $partName : $base;
        }

        $target = (string) ($media['target'] ?? '');
        $base = basename(strtok($target, '?#') ?: $target);

        return $base === '' ? $target : $base;
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

    /**
     * @param list<array{level:int, bullet:bool, text:string}> $paragraphs
     */
    private function paragraphsContainText(array $paragraphs): bool
    {
        foreach ($paragraphs as $paragraph) {
            if (trim($paragraph['text']) !== '') {
                return true;
            }
        }

        return false;
    }

    /**
     * @param list<array<string, mixed>> $imageIssues
     */
    private function pictureNode(ZipPackage $package, \DOMElement $pictureElement, OpcRelationships $slideRelationships, array &$imageIssues): ?AstNode
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
            $imageIssues[] = ['issue' => 'missing-image-relationship-id'];

            return null;
        }

        $relationship = $slideRelationships->byId($relationshipId);
        if (!$relationship instanceof OpcRelationship) {
            $imageIssues[] = [
                'issue' => 'unknown-image-relationship',
                'relationshipId' => $relationshipId,
            ];

            return null;
        }

        if ($relationship->isExternal()) {
            $imageIssues[] = [
                'issue' => 'external-image-target',
                'relationshipId' => $relationshipId,
                'target' => $relationship->target,
                'externalTargetPolicy' => $relationship->externalTargetPreflight(),
            ];

            return null;
        }

        $mediaPart = OpcPackagePath::stripQueryAndFragment($slideRelationships->resolveTarget($relationship));
        $partName = ltrim($mediaPart, '/');
        if (!$package->has($mediaPart)) {
            $imageIssues[] = [
                'issue' => 'missing-image-part',
                'relationshipId' => $relationshipId,
                'target' => $relationship->target,
                'partName' => $partName,
            ];

            return null;
        }

        return new AstNode('image', [
            'url' => $partName,
            'src' => $partName,
            'title' => $title,
            'alt' => $alt,
            'relationshipId' => $relationshipId,
        ], $this->textInlines($alt));
    }

    private function graphicDataElement(\DOMElement $graphicFrame): ?\DOMElement
    {
        $graphic = $this->firstChildElement($graphicFrame, 'graphic');

        return $graphic instanceof \DOMElement ? $this->firstChildElement($graphic, 'graphicData') : null;
    }

    private function chartNode(ZipPackage $package, \DOMElement $graphicData, OpcRelationships $slideRelationships): ?AstNode
    {
        $chartElement = $this->firstDescendantElement($graphicData, 'chart');
        if (!$chartElement instanceof \DOMElement) {
            return null;
        }

        $relationshipId = $this->relationshipId($chartElement, 'id');
        $chart = [
            'relationshipId' => $relationshipId,
            'relationshipType' => '',
            'target' => '',
            'partName' => '',
            'external' => false,
            'title' => '',
            'chartType' => 'unknown',
            'series' => [],
            'externalDataRelationshipIds' => [],
            'issues' => [],
            'byteExposurePolicy' => 'chart-part-bytes-blocked',
            'reviewPolicy' => 'chart-metadata-and-cache-values-only',
        ];
        if ($relationshipId === '') {
            $chart['issues'][] = 'missing-chart-relationship-id';

            return $this->chartReviewNode($chart);
        }

        $relationship = $slideRelationships->byId($relationshipId);
        if (!$relationship instanceof OpcRelationship) {
            $chart['issues'][] = 'unknown-chart-relationship';

            return $this->chartReviewNode($chart);
        }

        $chart['relationshipType'] = $relationship->type;
        $chart['target'] = $relationship->target;
        $chart['external'] = $relationship->isExternal();
        if ($relationship->isExternal()) {
            $chart['issues'][] = 'external-chart-part';

            return $this->chartReviewNode($chart);
        }

        $chartPart = OpcPackagePath::stripQueryAndFragment($slideRelationships->resolveTarget($relationship));
        $chart['partName'] = ltrim($chartPart, '/');
        $document = $this->optionalPackageXml($package, $chartPart, 'PPTX chart');
        if (!$document instanceof \DOMDocument) {
            $chart['issues'][] = 'missing-or-invalid-chart-part';

            return $this->chartReviewNode($chart);
        }

        $root = XmlHtmlDom::rootElement($document, 'chartSpace');
        if (!$root instanceof \DOMElement) {
            $chart['issues'][] = 'unexpected-chart-root';

            return $this->chartReviewNode($chart);
        }

        $chartRelationships = $this->relationshipsOrEmpty($package, $chartPart);

        return $this->chartReviewNode(array_replace($chart, $this->chartSummary($root, $chartRelationships)));
    }

    /**
     * @param array<string, mixed> $chart
     */
    private function chartReviewNode(array $chart): AstNode
    {
        $chartType = (string) ($chart['chartType'] ?? 'unknown');
        $chartClass = strtolower((string) preg_replace('/[^a-z0-9_-]+/i', '-', $chartType));
        $chartClass = trim($chartClass, '-') !== '' ? trim($chartClass, '-') : 'unknown';
        $title = (string) ($chart['title'] ?? '');
        $label = $title !== '' ? $title : ((string) ($chart['partName'] ?? '') !== '' ? (string) $chart['partName'] : (string) ($chart['relationshipId'] ?? 'unknown'));
        $attributes = array_filter([
            'type' => $chartType,
            'relationship-id' => (string) ($chart['relationshipId'] ?? ''),
            'src' => (string) ($chart['partName'] ?? ''),
            'title' => $title,
            'series-count' => (string) count(is_array($chart['series'] ?? null) ? $chart['series'] : []),
            'plot-count' => (string) count(is_array($chart['plots'] ?? null) ? $chart['plots'] : []),
        ], static fn (string $value): bool => $value !== '');

        $children = [$this->paragraph('[PPTX chart: ' . $label . ']')];
        foreach ((is_array($chart['series'] ?? null) ? $chart['series'] : []) as $series) {
            if (!is_array($series)) {
                continue;
            }
            $summary = $this->chartSeriesSummaryText($series);
            if ($summary !== '') {
                $children[] = $this->paragraph($summary);
            }
        }

        return new AstNode('div', [
            'classes' => ['pptx-chart', 'pptx-chart-' . $chartClass],
            'attributes' => $attributes,
            'pptxChart' => $chart,
        ], $children);
    }

    /**
     * @return array<string, mixed>
     */
    private function chartSummary(\DOMElement $chartSpace, OpcRelationships $chartRelationships): array
    {
        $chartElement = $this->firstDescendantElement($chartSpace, 'chart');
        if (!$chartElement instanceof \DOMElement) {
            return [];
        }

        $summary = [];
        $titleElement = $this->firstDescendantElement($chartElement, 'title');
        if ($titleElement instanceof \DOMElement) {
            $title = $this->chartElementText($titleElement);
            if ($title !== '') {
                $summary['title'] = $title;
            }
        }

        $plots = [];
        $series = [];
        foreach ($this->chartTypeElements($chartElement) as $chartTypeElement) {
            $plot = $this->chartPlotSummary($chartTypeElement);
            $plots[] = $plot;
            foreach ((is_array($plot['series'] ?? null) ? $plot['series'] : []) as $plotSeries) {
                if (is_array($plotSeries)) {
                    $series[] = $plotSeries;
                }
            }
        }
        if ($plots !== []) {
            $summary['chartType'] = (string) ($plots[0]['type'] ?? 'unknown');
            $summary['plots'] = $plots;
            $summary['chartTypes'] = array_values(array_unique(array_map(
                static fn (array $plot): string => (string) ($plot['type'] ?? 'unknown'),
                $plots
            )));
            $summary['chartTypeCount'] = count($plots);
            if ($series !== []) {
                $summary['series'] = $series;
            }
        }

        $axes = $this->chartAxes($chartElement);
        if ($axes !== []) {
            $summary['axes'] = $axes;
        }

        $externalDataRelationshipIds = [];
        $externalDataRelationships = [];
        foreach ($chartSpace->getElementsByTagName('*') as $element) {
            if (!$element instanceof \DOMElement || $element->localName !== 'externalData') {
                continue;
            }

            $relationshipId = $this->relationshipId($element, 'id');
            if ($relationshipId !== '') {
                $externalDataRelationshipIds[] = $relationshipId;
                $relationship = $chartRelationships->byId($relationshipId);
                if ($relationship instanceof OpcRelationship) {
                    $externalDataRelationships[] = $this->chartRelationshipMetadata($relationship, $chartRelationships);
                }
            }
        }
        if ($externalDataRelationshipIds !== []) {
            $summary['externalDataRelationshipIds'] = array_values(array_unique($externalDataRelationshipIds));
        }
        if ($externalDataRelationships !== []) {
            $summary['externalDataRelationships'] = $externalDataRelationships;
        }

        return $summary;
    }

    /**
     * @return list<\DOMElement>
     */
    private function chartTypeElements(\DOMElement $chartElement): array
    {
        $plotArea = $this->firstChildElement($chartElement, 'plotArea');
        if (!$plotArea instanceof \DOMElement) {
            return [];
        }

        $elements = [];
        foreach ($this->childElements($plotArea, null) as $element) {
            if (!str_ends_with($element->localName, 'Chart')) {
                continue;
            }
            if ($element->localName === 'chart') {
                continue;
            }

            $elements[] = $element;
        }

        return $elements;
    }

    /**
     * @return array<string, mixed>
     */
    private function chartPlotSummary(\DOMElement $chartTypeElement): array
    {
        $type = $this->chartTypeName($chartTypeElement);
        $plot = ['type' => $type];

        foreach ([
            'barDir' => 'barDirection',
            'grouping' => 'grouping',
            'scatterStyle' => 'scatterStyle',
            'radarStyle' => 'radarStyle',
            'ofPieType' => 'ofPieType',
            'holeSize' => 'holeSize',
        ] as $source => $target) {
            $element = $this->firstChildElement($chartTypeElement, $source);
            if ($element instanceof \DOMElement && $element->getAttribute('val') !== '') {
                $plot[$target] = $element->getAttribute('val');
            }
        }

        $varyColors = $this->firstChildElement($chartTypeElement, 'varyColors');
        if ($varyColors instanceof \DOMElement && $varyColors->hasAttribute('val')) {
            $plot['varyColors'] = $this->xmlBooleanValue($varyColors->getAttribute('val'));
        }

        $axisIds = [];
        foreach ($this->childElements($chartTypeElement, 'axId') as $axisIdElement) {
            $axisId = trim($axisIdElement->getAttribute('val'));
            if ($axisId !== '') {
                $axisIds[] = $axisId;
            }
        }
        if ($axisIds !== []) {
            $plot['axisIds'] = array_values(array_unique($axisIds));
        }

        $series = [];
        foreach ($this->childElements($chartTypeElement, 'ser') as $seriesElement) {
            $series[] = $this->chartSeries($seriesElement, $type);
        }
        $plot['seriesCount'] = count($series);
        if ($series !== []) {
            $plot['series'] = $series;
        }

        return $plot;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function chartAxes(\DOMElement $chartElement): array
    {
        $plotArea = $this->firstChildElement($chartElement, 'plotArea');
        if (!$plotArea instanceof \DOMElement) {
            return [];
        }

        $axes = [];
        foreach ($this->childElements($plotArea, null) as $axisElement) {
            if (!in_array($axisElement->localName, ['catAx', 'dateAx', 'valAx', 'serAx'], true)) {
                continue;
            }

            $axis = ['type' => $axisElement->localName];
            foreach (['axId' => 'id', 'crossAx' => 'crossAxisId'] as $source => $target) {
                $element = $this->firstChildElement($axisElement, $source);
                if ($element instanceof \DOMElement && $element->getAttribute('val') !== '') {
                    $axis[$target] = $element->getAttribute('val');
                }
            }

            $position = $this->firstChildElement($axisElement, 'axPos');
            if ($position instanceof \DOMElement && $position->getAttribute('val') !== '') {
                $axis['position'] = $position->getAttribute('val');
            }

            $title = $this->firstChildElement($axisElement, 'title');
            if ($title instanceof \DOMElement) {
                $text = $this->chartElementText($title);
                if ($text !== '') {
                    $axis['title'] = $text;
                }
            }

            $numberFormat = $this->firstChildElement($axisElement, 'numFmt');
            if ($numberFormat instanceof \DOMElement) {
                $format = trim($numberFormat->getAttribute('formatCode'));
                if ($format !== '') {
                    $axis['numberFormat'] = $format;
                }
                if ($numberFormat->hasAttribute('sourceLinked')) {
                    $axis['sourceLinked'] = $this->xmlBooleanValue($numberFormat->getAttribute('sourceLinked'));
                }
            }

            $axes[] = $axis;
        }

        return $axes;
    }

    /**
     * @return array<string, mixed>
     */
    private function chartRelationshipMetadata(OpcRelationship $relationship, OpcRelationships $chartRelationships): array
    {
        $metadata = [
            'relationshipId' => $relationship->id,
            'relationshipType' => $relationship->type,
            'target' => $relationship->target,
            'external' => $relationship->isExternal(),
        ];

        if ($relationship->isExternal()) {
            $metadata['externalTargetPolicy'] = $relationship->externalTargetPreflight();

            return $metadata;
        }

        $partName = OpcPackagePath::stripQueryAndFragment($chartRelationships->resolveTarget($relationship));
        if ($partName !== '') {
            $metadata['partName'] = ltrim($partName, '/');
        }

        return $metadata;
    }

    private function chartTypeName(\DOMElement $chartTypeElement): string
    {
        $type = preg_replace('/Chart$/', '', $chartTypeElement->localName) ?? $chartTypeElement->localName;

        return strtolower($type);
    }

    /**
     * @return array<string, mixed>
     */
    private function chartSeries(\DOMElement $seriesElement, string $plotType = ''): array
    {
        $series = [
            'name' => '',
            'categories' => $this->chartCacheValuesFor($seriesElement, ['cat', 'xVal']),
            'values' => $this->chartCacheValuesFor($seriesElement, ['val', 'yVal']),
        ];
        if ($plotType !== '') {
            $series['plotType'] = $plotType;
        }

        $index = $this->firstChildElement($seriesElement, 'idx');
        if ($index instanceof \DOMElement && $index->getAttribute('val') !== '') {
            $series['index'] = $index->getAttribute('val');
        }

        $order = $this->firstChildElement($seriesElement, 'order');
        if ($order instanceof \DOMElement && $order->getAttribute('val') !== '') {
            $series['order'] = $order->getAttribute('val');
        }

        $text = $this->firstChildElement($seriesElement, 'tx');
        if ($text instanceof \DOMElement) {
            $series['name'] = $this->chartElementText($text);
        }

        return $series;
    }

    /**
     * @param list<string> $containers
     * @return list<string>
     */
    private function chartCacheValuesFor(\DOMElement $seriesElement, array $containers): array
    {
        foreach ($this->childElements($seriesElement, null) as $child) {
            if (in_array($child->localName, $containers, true)) {
                return $this->chartCacheValues($child);
            }
        }

        return [];
    }

    /**
     * @return list<string>
     */
    private function chartCacheValues(\DOMElement $container): array
    {
        $values = [];
        foreach ($container->getElementsByTagName('*') as $point) {
            if (!$point instanceof \DOMElement || $point->localName !== 'pt') {
                continue;
            }

            $value = $this->firstChildElement($point, 'v');
            if ($value instanceof \DOMElement) {
                $text = $this->chartElementText($value);
                if ($text !== '') {
                    $values[] = $text;
                }
            }
        }
        if ($values !== []) {
            return $values;
        }

        foreach ($container->getElementsByTagName('*') as $value) {
            if (!$value instanceof \DOMElement || $value->localName !== 'v') {
                continue;
            }

            $text = $this->chartElementText($value);
            if ($text !== '') {
                $values[] = $text;
            }
        }

        return $values;
    }

    private function chartElementText(\DOMElement $element): string
    {
        $texts = [];
        if (in_array($element->localName, ['t', 'v'], true)) {
            $text = trim($this->allDescendantText($element));
            if ($text !== '') {
                $texts[] = $text;
            }
        }

        foreach ($element->getElementsByTagName('*') as $child) {
            if (!$child instanceof \DOMElement || !in_array($child->localName, ['t', 'v'], true)) {
                continue;
            }

            $text = trim($this->allDescendantText($child));
            if ($text !== '') {
                $texts[] = $text;
            }
        }

        return trim(implode(' ', $texts));
    }

    /**
     * @param array<string, mixed> $series
     */
    private function chartSeriesSummaryText(array $series): string
    {
        $name = (string) ($series['name'] ?? '');
        $categories = is_array($series['categories'] ?? null) ? $series['categories'] : [];
        $values = is_array($series['values'] ?? null) ? $series['values'] : [];
        $pairs = [];
        $count = max(count($categories), count($values));
        for ($index = 0; $index < $count; $index++) {
            $category = is_string($categories[$index] ?? null) ? $categories[$index] : '';
            $value = is_string($values[$index] ?? null) ? $values[$index] : '';
            if ($category === '' && $value === '') {
                continue;
            }
            $pairs[] = $category !== '' ? $category . '=' . $value : $value;
        }

        if ($pairs === []) {
            return $name;
        }

        return ($name !== '' ? $name : 'Series') . ': ' . implode('; ', $pairs);
    }

    /**
     * @param array<string, mixed> $tableStyles
     */
    private function tableNode(\DOMElement $tableElement, array $tableStyles, array $slideContext): AstNode
    {
        $theme = is_array($slideContext['theme'] ?? null) ? $slideContext['theme'] : [];
        $rows = [];
        foreach ($this->childElements($tableElement, 'tr') as $rowElement) {
            $row = [];
            foreach ($this->childElements($rowElement, 'tc') as $cellElement) {
                $row[] = $this->tableCellData($cellElement, $theme);
            }
            $rows[] = $row;
        }

        $header = array_shift($rows) ?? [];
        $attrs = [
            'caption' => '',
            'alignments' => array_fill(0, count($header), 'default'),
            'pptxTable' => true,
        ];
        $style = $this->tableStyleMetadata($tableElement, $tableStyles);
        if ($style !== []) {
            $attrs['pptxTableStyle'] = $style;
        }
        $columnWidths = $this->tableColumnWidths($tableElement);
        if ($columnWidths !== []) {
            $attrs['columnWidths'] = $columnWidths;
        }

        return new AstNode('table', $attrs, [
            new AstNode('table_head', [], [$this->tableRow($header, true)]),
            new AstNode('table_body', [], array_map(fn (array $row): AstNode => $this->tableRow($row, false), $rows)),
        ]);
    }

    /**
     * @return array{attrs:array<string, mixed>, text:string}
     */
    private function tableCellData(\DOMElement $cellElement, array $theme): array
    {
        $text = $this->drawingText($cellElement);
        $attrs = ['text' => $text];
        $pptxCell = [];

        foreach (['gridSpan' => 'colspan', 'rowSpan' => 'rowspan'] as $source => $target) {
            $value = $this->integerAttribute($cellElement, $source);
            if ($value !== null && $value > 1) {
                $attrs[$target] = $value;
                $pptxCell[$source] = $value;
            }
        }

        foreach (['hMerge', 'vMerge'] as $source) {
            $value = $this->xmlBooleanAttribute($cellElement, $source);
            if ($value !== null) {
                $pptxCell[$source] = $value;
            }
        }

        if ($pptxCell !== []) {
            $attrs['pptxCell'] = $pptxCell;
        }

        $style = $this->tableCellStyleMetadata($cellElement, $theme);
        if ($style !== []) {
            $attrs['pptxCellStyle'] = $style;
            if (isset($style['fillColor'])) {
                $attrs['backgroundColor'] = $style['fillColor'];
            }
            if (isset($style['verticalAlign'])) {
                $attrs['verticalAlign'] = $style['verticalAlign'];
            }
        }

        return ['attrs' => $attrs, 'text' => $text];
    }

    /**
     * @param list<array{attrs:array<string, mixed>, text:string}> $row
     */
    private function tableRow(array $row, bool $header): AstNode
    {
        return new AstNode('table_row', ['header' => $header], array_map(
            fn (array $cell): AstNode => $this->tableCellNode($cell, $header),
            $row
        ));
    }

    /**
     * @param array{attrs:array<string, mixed>, text:string} $cell
     */
    private function tableCellNode(array $cell, bool $header): AstNode
    {
        $attrs = $cell['attrs'];
        $attrs['header'] = $header;
        $text = $cell['text'];

        return new AstNode('table_cell', $attrs, [
            new AstNode('plain', [], $this->textInlines($text)),
        ]);
    }

    /**
     * @param array<string, mixed> $tableStyles
     * @return array<string, mixed>
     */
    private function tableStyleMetadata(\DOMElement $tableElement, array $tableStyles): array
    {
        $properties = $this->firstChildElement($tableElement, 'tblPr');
        if (!$properties instanceof \DOMElement) {
            return [];
        }

        $style = [];
        foreach (['firstRow', 'firstCol', 'lastRow', 'lastCol', 'bandRow', 'bandCol'] as $attribute) {
            $value = $this->xmlBooleanAttribute($properties, $attribute);
            if ($value !== null) {
                $style[$attribute] = $value;
            }
        }

        $styleId = $this->firstChildElement($properties, 'tableStyleId');
        if ($styleId instanceof \DOMElement) {
            $id = trim($this->allDescendantText($styleId));
            if ($id !== '') {
                $style['id'] = $id;
            }
        }

        $styleCatalog = $tableStyles['styles'] ?? [];
        $styleId = is_string($style['id'] ?? null) ? $style['id'] : '';
        $resolvedStyle = is_array($styleCatalog) && $styleId !== '' && is_array($styleCatalog[$styleId] ?? null)
            ? $styleCatalog[$styleId]
            : [];
        if ($resolvedStyle !== []) {
            foreach (['name', 'sourcePart', 'relationshipId'] as $key) {
                $value = $resolvedStyle[$key] ?? null;
                if (is_string($value) && $value !== '') {
                    $style[$key] = $value;
                }
            }
            if (is_array($resolvedStyle['parts'] ?? null)) {
                $style['parts'] = $resolvedStyle['parts'];
            }
            if (($resolvedStyle['default'] ?? false) === true) {
                $style['default'] = true;
            }
        }

        return $style;
    }

    /**
     * @return list<int>
     */
    private function tableColumnWidths(\DOMElement $tableElement): array
    {
        $grid = $this->firstChildElement($tableElement, 'tblGrid');
        if (!$grid instanceof \DOMElement) {
            return [];
        }

        $widths = [];
        foreach ($this->childElements($grid, 'gridCol') as $column) {
            $width = $this->integerAttribute($column, 'w');
            if ($width !== null) {
                $widths[] = $width;
            }
        }

        return $widths;
    }

    /**
     * @return array<string, mixed>
     */
    private function tableTextStyleMetadata(\DOMElement $textStyle): array
    {
        $style = [];
        foreach (['b' => 'bold', 'i' => 'italic'] as $source => $target) {
            if ($textStyle->hasAttribute($source)) {
                $style[$target] = $this->xmlBooleanValue($textStyle->getAttribute($source));
            }
        }

        $fontRef = $this->firstChildElement($textStyle, 'fontRef');
        if ($fontRef instanceof \DOMElement) {
            $index = trim($fontRef->getAttribute('idx'));
            if ($index !== '') {
                $style['fontRef'] = $index;
            }

            $color = $this->drawingColorValue($fontRef);
            if ($color !== '') {
                $style['fontRefColor'] = $color;
            }
        }

        $color = $this->drawingColorValue($textStyle);
        if ($color !== '') {
            $style['textColor'] = $color;
        }

        return $style;
    }

    /**
     * @return array<string, mixed>
     */
    private function tableCellStyleContainerMetadata(\DOMElement $properties): array
    {
        $style = [];
        $fillColor = $this->tableFillColor($properties);
        if ($fillColor !== '') {
            $style['fillColor'] = $fillColor;
        }

        $borders = [];
        $borderStyles = [];
        $borderNames = ['lnL' => 'left', 'lnR' => 'right', 'lnT' => 'top', 'lnB' => 'bottom', 'lnTlToBr' => 'diagonalDown', 'lnBlToTr' => 'diagonalUp'];
        foreach ($this->childElements($properties, null) as $child) {
            if (!isset($borderNames[$child->localName])) {
                continue;
            }

            $border = $this->tableLineStyleMetadata($child);
            if ($border === []) {
                continue;
            }

            $side = $borderNames[$child->localName];
            $borderStyles[$side] = $border;
            if (is_string($border['color'] ?? null) && $border['color'] !== '') {
                $borders[$side] = $border['color'];
            }
        }
        if ($borders !== []) {
            $style['borders'] = $borders;
        }
        if ($borderStyles !== []) {
            $style['borderStyles'] = $borderStyles;
        }

        return $style;
    }

    private function tableFillColor(\DOMElement $properties): string
    {
        foreach ($this->childElements($properties, null) as $child) {
            if (!in_array($child->localName, ['solidFill', 'fill', 'fillRef'], true)) {
                continue;
            }

            $color = $this->drawingColorValue($child);
            if ($color !== '') {
                return $color;
            }
        }

        return '';
    }

    /**
     * @return array<string, mixed>
     */
    private function tableLineStyleMetadata(\DOMElement $line): array
    {
        $style = [];
        $color = $this->drawingColorValue($line);
        if ($color !== '') {
            $style['color'] = $color;
        }

        $width = $this->integerAttribute($line, 'w');
        if ($width !== null) {
            $style['width'] = $width;
        }

        foreach (['cap', 'cmpd', 'algn'] as $attribute) {
            $value = trim($line->getAttribute($attribute));
            if ($value !== '') {
                $style[$attribute] = $value;
            }
        }

        $dash = $this->firstChildElement($line, 'prstDash');
        if ($dash instanceof \DOMElement && $dash->getAttribute('val') !== '') {
            $style['dash'] = $dash->getAttribute('val');
        }

        return $style;
    }

    /**
     * @return array<string, mixed>
     */
    private function withResolvedThemeColors(array $style, array $theme): array
    {
        $fillColor = is_string($style['fillColor'] ?? null) ? $style['fillColor'] : '';
        $resolvedFillColor = $this->resolveThemeColor($fillColor, $theme);
        if ($resolvedFillColor !== '' && $resolvedFillColor !== $fillColor) {
            $style['resolvedFillColor'] = $resolvedFillColor;
        }

        $borders = is_array($style['borders'] ?? null) ? $style['borders'] : [];
        $resolvedBorders = [];
        foreach ($borders as $side => $color) {
            if (!is_string($side) || !is_string($color)) {
                continue;
            }

            $resolved = $this->resolveThemeColor($color, $theme);
            if ($resolved !== '' && $resolved !== $color) {
                $resolvedBorders[$side] = $resolved;
            }
        }
        if ($resolvedBorders !== []) {
            $style['resolvedBorders'] = $resolvedBorders;
        }

        if (is_array($style['borderStyles'] ?? null)) {
            foreach ($style['borderStyles'] as $side => $border) {
                if (!is_string($side) || !is_array($border)) {
                    continue;
                }

                $color = is_string($border['color'] ?? null) ? $border['color'] : '';
                $resolved = $this->resolveThemeColor($color, $theme);
                if ($resolved !== '' && $resolved !== $color) {
                    $style['borderStyles'][$side]['resolvedColor'] = $resolved;
                }
            }
        }

        return $style;
    }

    private function resolveThemeColor(string $color, array $theme): string
    {
        if (!str_starts_with($color, 'theme:')) {
            return $color;
        }

        $key = substr($color, 6);
        $colors = $theme['colorScheme']['colors'] ?? [];

        return is_array($colors) && is_string($colors[$key] ?? null) ? $colors[$key] : '';
    }

    /**
     * @return array<string, mixed>
     */
    private function tableCellStyleMetadata(\DOMElement $cellElement, array $theme): array
    {
        $properties = $this->firstChildElement($cellElement, 'tcPr');
        if (!$properties instanceof \DOMElement) {
            return [];
        }

        $style = $this->tableCellStyleContainerMetadata($properties);
        foreach (['anchor' => 'verticalAlign', 'vert' => 'textDirection'] as $source => $target) {
            $value = trim($properties->getAttribute($source));
            if ($value !== '') {
                $style[$target] = $value;
            }
        }
        foreach (['marL' => 'marginLeft', 'marR' => 'marginRight', 'marT' => 'marginTop', 'marB' => 'marginBottom'] as $source => $target) {
            $value = $this->integerAttribute($properties, $source);
            if ($value !== null) {
                $style[$target] = $value;
            }
        }
        $style = $this->withResolvedThemeColors($style, $theme);

        return $style;
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
        $placeholder = $this->placeholderElement($shapeElement);
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
