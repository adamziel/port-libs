<?php

declare(strict_types=1);

namespace PortLibs\MarkerPDF;

final class PdfMarkupAnnotationExtractor
{
    private const DEFAULT_PAGE_BBOX = [0.0, 0.0, 612.0, 792.0];
    private const TEXT_MARKUP_SUBTYPES = ['Highlight', 'Underline', 'Squiggly', 'StrikeOut'];

    /**
     * Native boundary for PDF text-markup review annotations.
     *
     * @return list<array{pnum: int, page_object: int, markups: list<array<string, mixed>>}>
     */
    public function extractPageMarkups(string $pdfBytes): array
    {
        $objects = $this->pdfObjects($pdfBytes);
        $actionReviewer = new PdfActionReviewExtractor($pdfBytes);
        $pageObjectNumbers = $this->orderedPageObjectNumbers($objects);
        $pages = [];

        foreach ($pageObjectNumbers as $pnum => $pageObjectNumber) {
            if (!isset($objects[$pageObjectNumber])) {
                continue;
            }

            $pageGeometry = $this->pageGeometry($pageObjectNumber, $objects);
            $markups = $this->markupsFromPageObject($objects[$pageObjectNumber], $objects, $pageGeometry, $actionReviewer);
            if ($markups === []) {
                continue;
            }

            $pages[] = [
                'pnum' => $pnum,
                'page_object' => $pageObjectNumber,
                'markups' => $markups,
            ];
        }

        return $pages;
    }

    /**
     * Applies extracted highlight/underline/squiggly/strikeout annotations to
     * supplied Marker/pdftext page spans by QuadPoints rectangle intersection.
     *
     * @param list<array<string, mixed>> $pages
     * @return list<array<string, mixed>>
     */
    public function applyMarkupsToPages(array $pages, string $pdfBytes): array
    {
        $reviewContextByPage = $this->markupReviewContextByPage($pdfBytes);
        $markupsByPage = [];
        foreach ($this->extractPageMarkups($pdfBytes) as $pageMarkups) {
            $markups = [];
            foreach ($pageMarkups['markups'] as $markup) {
                $markups[] = $this->markupWithReviewContext(
                    $markup,
                    $reviewContextByPage[$pageMarkups['pnum']] ?? null
                );
            }

            $markupsByPage[$pageMarkups['pnum']] = $markups;
        }

        $out = [];
        foreach (array_values($pages) as $index => $page) {
            if (!is_array($page)) {
                continue;
            }

            $pnum = isset($page['pnum']) ? (int) $page['pnum'] : $index;
            $markups = $markupsByPage[$pnum] ?? [];
            if ($markups === []) {
                $out[] = $page;
                continue;
            }

            $page['markup_annotations'] = $markups;
            foreach (($page['blocks'] ?? []) as $blockIndex => $block) {
                if (!is_array($block)) {
                    continue;
                }
                foreach (($block['lines'] ?? []) as $lineIndex => $line) {
                    if (!is_array($line)) {
                        continue;
                    }
                    foreach (($line['spans'] ?? []) as $spanIndex => $span) {
                        if (!is_array($span)) {
                            continue;
                        }

                        $annotations = $this->markupAnnotationsForSpan($span, $markups, $page);
                        if ($annotations === []) {
                            continue;
                        }

                        $existing = $span['review_annotations'] ?? [];
                        if (!is_array($existing)) {
                            $existing = [];
                        }

                        $page['blocks'][$blockIndex]['lines'][$lineIndex]['spans'][$spanIndex]['review_annotations'] = [
                            ...$existing,
                            ...$annotations,
                        ];
                    }
                }
            }

            $out[] = $page;
        }

        return $out;
    }

    /**
     * @param array<string, mixed> $span
     * @param list<array<string, mixed>> $markups
     * @param array<string, mixed> $page
     * @return list<array<string, mixed>>
     */
    private function markupAnnotationsForSpan(array $span, array $markups, array $page): array
    {
        $bbox = $this->bbox($span['bbox'] ?? null);
        if ($bbox === null) {
            return [];
        }

        $annotations = [];
        foreach ($markups as $markup) {
            foreach ($this->quadRectCandidatesForPage($markup, $page) as $candidate) {
                $quadRect = $candidate['rect'];
                if (!$this->bboxesIntersect($bbox, $quadRect)) {
                    continue;
                }

                $annotations[] = [
                    'subtype' => $markup['subtype'],
                    'contents' => $markup['contents'],
                    'author' => $markup['author'],
                    'subject' => $markup['subject'],
                    'modified_at' => $markup['modified_at'],
                    'name' => $markup['name'],
                    'color' => $markup['color'],
                    'opacity' => $markup['opacity'],
                    'border' => $markup['border'],
                    'border_style' => $markup['border_style'],
                    'popup' => $markup['popup'],
                    'actions' => $markup['actions'],
                    'additional_actions' => $markup['additional_actions'],
                    'executes_actions_on_import' => $markup['executes_actions_on_import'],
                    'quad_index' => $candidate['index'],
                    'quad_rect' => $quadRect,
                    'quad_rect_coordinate_space' => $candidate['coordinate_space'],
                    'page_quad_rect' => $markup['quad_rects'][$candidate['index']] ?? null,
                    'pdftext_quad_rect' => $markup['pdftext_quad_rects'][$candidate['index']] ?? null,
                    'annotation_object' => $markup['annotation_object'],
                    'struct_parent' => $markup['struct_parent'],
                    'structure_parent' => $markup['structure_parent'] ?? null,
                    'page_structparent_context' => $markup['page_structparent_context'] ?? null,
                ];
            }
        }

        return $annotations;
    }

    /**
     * @return array<int, array{page_context: array<string, mixed>, markups_by_object: array<int, array<string, mixed>>, markups_by_struct_parent: array<int, array<string, mixed>>}>
     */
    private function markupReviewContextByPage(string $pdfBytes): array
    {
        $contexts = [];
        foreach ((new PdfPagePropertyExtractor())->extractPageReviewMetadata($pdfBytes) as $pageReview) {
            if (!is_array($pageReview)) {
                continue;
            }

            $pnum = $pageReview['pnum'] ?? null;
            if (!is_int($pnum)) {
                continue;
            }

            $pageContext = [
                'source' => 'page_structparent_markup_annotation_context',
                'pnum' => $pnum,
                'page' => $pnum,
                'page_number' => $pageReview['page_number'] ?? ($pnum + 1),
                'page_label' => $pageReview['page_label'] ?? (string) ($pnum + 1),
                'page_object' => $pageReview['page_object'] ?? null,
                'struct_parents' => $pageReview['struct_parents'] ?? null,
                'parent_tree' => $pageReview['parent_tree'] ?? null,
                'review_only' => true,
                'visible_text_source' => false,
            ];

            $contexts[$pnum] = [
                'page_context' => $this->compactContextRow($pageContext),
                'markups_by_object' => [],
                'markups_by_struct_parent' => [],
            ];

            $markupRows = $pageReview['text_markup_annotations'] ?? [];
            if (!is_array($markupRows)) {
                continue;
            }

            foreach ($markupRows as $markupRow) {
                if (!is_array($markupRow)) {
                    continue;
                }

                $structureParent = $this->structureParentContextFromMarkupReview($markupRow);
                if ($structureParent === []) {
                    continue;
                }

                $annotationObject = $markupRow['annotation_object'] ?? null;
                if (is_int($annotationObject)) {
                    $contexts[$pnum]['markups_by_object'][$annotationObject] = $structureParent;
                }

                $structParent = $markupRow['struct_parent'] ?? null;
                if (is_int($structParent)) {
                    $contexts[$pnum]['markups_by_struct_parent'][$structParent] = $structureParent;
                }
            }
        }

        return $contexts;
    }

    /**
     * @param array<string, mixed> $markup
     * @param array{page_context: array<string, mixed>, markups_by_object: array<int, array<string, mixed>>, markups_by_struct_parent: array<int, array<string, mixed>>}|null $pageContext
     * @return array<string, mixed>
     */
    private function markupWithReviewContext(array $markup, ?array $pageContext): array
    {
        if ($pageContext === null) {
            return $markup;
        }

        if (($pageContext['page_context'] ?? []) !== []) {
            $markup['page_structparent_context'] = $pageContext['page_context'];
        }

        $annotationObject = $markup['annotation_object'] ?? null;
        $structParent = $markup['struct_parent'] ?? null;
        $structureParent = null;
        if (is_int($annotationObject)) {
            $structureParent = $pageContext['markups_by_object'][$annotationObject] ?? null;
        }
        if ($structureParent === null && is_int($structParent)) {
            $structureParent = $pageContext['markups_by_struct_parent'][$structParent] ?? null;
        }

        if (is_array($structureParent) && $structureParent !== []) {
            $markup['structure_parent'] = $structureParent;
        }

        return $markup;
    }

    /**
     * @param array<string, mixed> $markupRow
     * @return array<string, mixed>
     */
    private function structureParentContextFromMarkupReview(array $markupRow): array
    {
        $structParent = $markupRow['struct_parent'] ?? null;
        if (!is_int($structParent)) {
            return [];
        }

        $parentTree = is_array($markupRow['parent_tree'] ?? null) ? $markupRow['parent_tree'] : [];
        $row = [
            'source' => 'page_text_markup_annotation_struct_parent_context',
            'key' => $structParent,
            'annotation_object' => $markupRow['annotation_object'] ?? null,
            'current_page_annotation' => true,
            'review_only' => true,
            'visible_text_source' => false,
            'parent_tree' => $parentTree,
        ];

        foreach ([
            'struct_object',
            'raw_role',
            'role',
            'role_mapped',
            'title',
            'language',
            'language_inherited',
            'alternate_text',
            'actual_text',
            'expansion_text',
            'id',
            'classes',
            'revision',
            'namespace',
            'associated_file_count',
            'associated_files',
        ] as $key) {
            if (array_key_exists($key, $markupRow)) {
                $row[$key] = $markupRow[$key];
            }
        }

        return $this->compactContextRow($row);
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function compactContextRow(array $row): array
    {
        return array_filter($row, static fn (mixed $value): bool => $value !== null && $value !== []);
    }

    /**
     * @param array<int, string> $objects
     * @param array{bbox: list<float>, rotation: int, user_unit: float, display_bbox: list<float>} $pageGeometry
     * @return list<array<string, mixed>>
     */
    private function markupsFromPageObject(
        string $pageBody,
        array $objects,
        array $pageGeometry,
        PdfActionReviewExtractor $actionReviewer
    ): array {
        $annotationBodies = $this->annotationBodiesForPage($pageBody, $objects);
        $markups = [];

        foreach ($annotationBodies as $annotation) {
            $markup = $this->markupFromAnnotationBody($annotation['body'], $objects, $annotation['object'], $pageGeometry, $actionReviewer);
            if ($markup !== null) {
                $markups[] = $markup;
            }
        }

        return $markups;
    }

    /**
     * @return array<string, mixed>|null
     * @param array<int, string> $objects
     * @param array{bbox: list<float>, rotation: int, user_unit: float, display_bbox: list<float>} $pageGeometry
     */
    private function markupFromAnnotationBody(
        string $annotationBody,
        array $objects,
        ?int $annotationObject,
        array $pageGeometry,
        PdfActionReviewExtractor $actionReviewer
    ): ?array {
        if (preg_match('/\/Subtype\s*\/(' . implode('|', self::TEXT_MARKUP_SUBTYPES) . ')\b/', $annotationBody, $match) !== 1) {
            return null;
        }

        $quadPoints = $this->quadPointsFromAnnotation($annotationBody);
        if ($quadPoints === []) {
            return null;
        }

        $quadRects = array_map(fn (array $quad): array => $this->rectFromQuad($quad), $quadPoints);
        $pdftextQuadRects = array_map(fn (array $rect): array => $this->pageRectToPdftextRect($rect, $pageGeometry), $quadRects);
        $rect = $this->rectFromAnnotation($annotationBody) ?? $this->unionRect($quadRects);
        if ($rect === null) {
            return null;
        }

        $actionReview = $actionReviewer->reviewAnnotationActions($annotationBody);

        return [
            'subtype' => $match[1],
            'rect' => $rect,
            'quad_points' => $quadPoints,
            'quad_rects' => $quadRects,
            'pdftext_quad_rects' => $pdftextQuadRects,
            'pdftext_rect' => $this->pageRectToPdftextRect($rect, $pageGeometry),
            'page_bbox' => $pageGeometry['bbox'],
            'page_rotation' => $pageGeometry['rotation'],
            'page_user_unit' => $pageGeometry['user_unit'],
            'display_page_bbox' => $pageGeometry['display_bbox'],
            'contents' => $this->stringAfterName($annotationBody, 'Contents'),
            'author' => $this->stringAfterName($annotationBody, 'T'),
            'subject' => $this->stringAfterName($annotationBody, 'Subj'),
            'modified_at' => $this->stringAfterName($annotationBody, 'M'),
            'name' => $this->stringAfterName($annotationBody, 'NM'),
            'color' => $this->floatArrayAfterName($annotationBody, 'C'),
            'opacity' => $this->numberAfterName($annotationBody, 'CA'),
            'border' => $this->borderArrayFromAnnotation($annotationBody),
            'border_style' => $this->borderStyleFromAnnotation($annotationBody, $objects),
            'popup' => $this->popupFromAnnotation($annotationBody, $objects),
            'flags' => $this->integerAfterName($annotationBody, 'F'),
            'struct_parent' => $this->integerAfterName($annotationBody, 'StructParent'),
            'actions' => $actionReview['actions'],
            'additional_actions' => $actionReview['additional_actions'],
            'executes_actions_on_import' => $actionReview['executes_actions_on_import'],
            'annotation_object' => $annotationObject,
        ];
    }

    /**
     * @return list<array{body: string, object: int|null}>
     * @param array<int, string> $objects
     */
    private function annotationBodiesForPage(string $pageBody, array $objects): array
    {
        $annots = $this->pageDictionaryValueAfterName($pageBody, 'Annots');
        if ($annots === null) {
            return [];
        }

        return $this->annotationBodiesFromValue($annots, $objects);
    }

    private function pageDictionaryValueAfterName(string $pageBody, string $name): ?string
    {
        $dictionary = $this->dictionaryObjectBody($pageBody);
        if ($dictionary === null) {
            return $this->valueAfterName($pageBody, $name);
        }

        $offset = 0;
        $length = strlen($dictionary);
        while ($offset < $length) {
            $this->skipWhitespace($dictionary, $offset);
            if ($offset >= $length) {
                break;
            }

            if ($dictionary[$offset] !== '/') {
                $offset++;
                continue;
            }

            $nameEnd = $this->skipPdfName($dictionary, $offset);
            $key = $this->decodePdfName(substr($dictionary, $offset + 1, $nameEnd - $offset - 1));
            $valueEnd = null;
            $value = $this->valueStartingAtOffsetWithEnd($dictionary, $nameEnd, $valueEnd);
            if ($value === null || $valueEnd === null || $valueEnd <= $nameEnd) {
                $offset = max($nameEnd, $offset + 1);
                continue;
            }

            if ($key === $name) {
                return $value;
            }

            $offset = $valueEnd;
        }

        return null;
    }

    /**
     * @return list<array{body: string, object: int|null}>
     * @param array<int, string> $objects
     */
    private function annotationBodiesFromValue(string $value, array $objects): array
    {
        $value = trim($value);
        if ($value === '') {
            return [];
        }

        if (preg_match('/^(\d+)\s+\d+\s+R\b/', $value, $match) === 1) {
            $objectNumber = (int) $match[1];
            if (!isset($objects[$objectNumber])) {
                return [];
            }

            $objectBody = trim($objects[$objectNumber]);
            if (str_starts_with($objectBody, '[')) {
                return $this->annotationBodiesFromArray($this->arrayBodyFromValue($objectBody), $objects);
            }

            $dictionary = $this->dictionaryObjectBody($objectBody);
            return $dictionary === null ? [] : [['body' => $dictionary, 'object' => $objectNumber]];
        }

        if (str_starts_with($value, '[')) {
            return $this->annotationBodiesFromArray($this->arrayBodyFromValue($value), $objects);
        }

        if (str_starts_with($value, '<<')) {
            $dictionary = $this->readPdfDictionaryAt($value, 0);
            return $dictionary === null ? [] : [['body' => $dictionary, 'object' => null]];
        }

        return [];
    }

    /**
     * @return list<array{body: string, object: int|null}>
     * @param array<int, string> $objects
     */
    private function annotationBodiesFromArray(?string $arrayBody, array $objects): array
    {
        if ($arrayBody === null) {
            return [];
        }

        $annotations = [];
        $offset = 0;
        $length = strlen($arrayBody);

        while ($offset < $length) {
            $this->skipWhitespace($arrayBody, $offset);
            if ($offset >= $length) {
                break;
            }

            if (substr($arrayBody, $offset, 2) === '<<') {
                $endOffset = null;
                $dictionary = $this->readPdfDictionaryAt($arrayBody, $offset, $endOffset);
                if ($dictionary === null || $endOffset === null) {
                    $offset++;
                    continue;
                }

                $annotations[] = ['body' => $dictionary, 'object' => null];
                $offset = $endOffset;
                continue;
            }

            if (preg_match('/\G(\d+)\s+\d+\s+R\b/s', $arrayBody, $match, 0, $offset) === 1) {
                $objectNumber = (int) $match[1];
                $dictionary = $this->dictionaryObjectBody($objects[$objectNumber] ?? '');
                if ($dictionary !== null) {
                    $annotations[] = ['body' => $dictionary, 'object' => $objectNumber];
                }
                $offset += strlen($match[0]);
                continue;
            }

            $offset++;
        }

        return $annotations;
    }

    private function skipWhitespace(string $value, int &$offset): void
    {
        while ($offset < strlen($value) && ctype_space($value[$offset])) {
            $offset++;
        }
    }

    /**
     * @return list<list<float>>
     */
    private function quadPointsFromAnnotation(string $annotationBody): array
    {
        $value = $this->valueAfterName($annotationBody, 'QuadPoints');
        if ($value === null || !str_starts_with(trim($value), '[')) {
            return [];
        }

        $arrayBody = $this->arrayBodyFromValue($value);
        if ($arrayBody === null) {
            return [];
        }

        $numbers = $this->numbersFromPdfArray($arrayBody);
        if (count($numbers) < 8) {
            return [];
        }

        $quads = [];
        for ($offset = 0, $count = count($numbers); $offset + 7 < $count; $offset += 8) {
            $quads[] = array_slice($numbers, $offset, 8);
        }

        return $quads;
    }

    /**
     * @return list<float>|null
     */
    private function rectFromAnnotation(string $annotationBody): ?array
    {
        $value = $this->valueAfterName($annotationBody, 'Rect');
        if ($value === null || !str_starts_with(trim($value), '[')) {
            return null;
        }

        $arrayBody = $this->arrayBodyFromValue($value);
        if ($arrayBody === null) {
            return null;
        }

        $numbers = $this->numbersFromPdfArray($arrayBody);
        if (count($numbers) < 4) {
            return null;
        }

        return $this->normalizeRect(array_slice($numbers, 0, 4));
    }

    /**
     * @return array{horizontal_corner_radius: float, vertical_corner_radius: float, width: float, dash_pattern: list<float>, source: string}|null
     */
    private function borderArrayFromAnnotation(string $annotationBody): ?array
    {
        $value = $this->valueAfterName($annotationBody, 'Border');
        if ($value === null || !str_starts_with(trim($value), '[')) {
            return null;
        }

        $arrayBody = $this->arrayBodyFromValue($value);
        if ($arrayBody === null) {
            return null;
        }

        $numbers = $this->numbersFromPdfArray($arrayBody);
        if (count($numbers) < 3) {
            return null;
        }

        $dashPattern = [];
        if (preg_match('/\[[^\[\]]*\]\s*$/s', trim($arrayBody), $dashMatch) === 1) {
            $dashPattern = $this->numbersFromPdfArray(trim($dashMatch[0], '[]'));
        }

        return [
            'horizontal_corner_radius' => (float) $numbers[0],
            'vertical_corner_radius' => (float) $numbers[1],
            'width' => (float) $numbers[2],
            'dash_pattern' => $dashPattern,
            'source' => 'Border',
        ];
    }

    /**
     * @return array{width: float|null, style: string|null, style_name: string|null, dash_pattern: list<float>, source: string}|null
     * @param array<int, string> $objects
     */
    private function borderStyleFromAnnotation(string $annotationBody, array $objects): ?array
    {
        $value = $this->valueAfterName($annotationBody, 'BS');
        if ($value === null) {
            $border = $this->borderArrayFromAnnotation($annotationBody);
            if ($border === null) {
                return null;
            }

            return [
                'width' => $border['width'],
                'style' => $border['dash_pattern'] === [] ? 'solid' : 'dashed',
                'style_name' => $border['dash_pattern'] === [] ? 'S' : 'D',
                'dash_pattern' => $border['dash_pattern'],
                'source' => 'Border',
            ];
        }

        $dictionary = $this->dictionaryFromValue($value, $objects);
        if ($dictionary === null) {
            return null;
        }

        $styleName = $this->nameValueAfterName($dictionary, 'S');
        $style = match ($styleName) {
            'D' => 'dashed',
            'B' => 'beveled',
            'I' => 'inset',
            'U' => 'underline',
            'S' => 'solid',
            default => $styleName,
        };

        $dashPattern = [];
        if (preg_match('/\/D\b\s*(\[[^\[\]]*\])/s', $dictionary, $dashMatch) === 1) {
            $dashArray = $this->arrayBodyFromValue($dashMatch[1]);
            $dashPattern = $dashArray === null ? [] : $this->numbersFromPdfArray($dashArray);
        }

        return [
            'width' => $this->numberAfterName($dictionary, 'W'),
            'style' => $style,
            'style_name' => $styleName,
            'dash_pattern' => $dashPattern,
            'source' => 'BS',
        ];
    }

    /**
     * @return array<string, mixed>|null
     * @param array<int, string> $objects
     */
    private function popupFromAnnotation(string $annotationBody, array $objects): ?array
    {
        $value = $this->valueAfterName($annotationBody, 'Popup');
        if ($value === null) {
            return null;
        }

        $objectNumber = $this->indirectObjectNumberFromValue($value);
        $dictionary = $this->dictionaryFromValue($value, $objects);
        if ($dictionary === null || $this->nameValueAfterName($dictionary, 'Subtype') !== 'Popup') {
            return null;
        }

        return [
            'annotation_object' => $objectNumber,
            'rect' => $this->rectFromAnnotation($dictionary),
            'open' => $this->booleanAfterName($dictionary, 'Open'),
            'contents' => $this->stringAfterName($dictionary, 'Contents'),
            'modified_at' => $this->stringAfterName($dictionary, 'M'),
            'parent_object' => $this->indirectObjectNumberFromValue($this->valueAfterName($dictionary, 'Parent') ?? ''),
        ];
    }

    /**
     * @param list<float> $quad
     * @return list<float>
     */
    private function rectFromQuad(array $quad): array
    {
        $xs = [$quad[0], $quad[2], $quad[4], $quad[6]];
        $ys = [$quad[1], $quad[3], $quad[5], $quad[7]];

        return [
            min($xs),
            min($ys),
            max($xs),
            max($ys),
        ];
    }

    /**
     * @param list<list<float>> $rects
     * @return list<float>|null
     */
    private function unionRect(array $rects): ?array
    {
        if ($rects === []) {
            return null;
        }

        $left = $bottom = INF;
        $right = $top = -INF;
        foreach ($rects as $rect) {
            $left = min($left, $rect[0]);
            $bottom = min($bottom, $rect[1]);
            $right = max($right, $rect[2]);
            $top = max($top, $rect[3]);
        }

        return [$left, $bottom, $right, $top];
    }

    /**
     * @param list<float> $rect
     * @return list<float>
     */
    private function normalizeRect(array $rect): array
    {
        return [
            min($rect[0], $rect[2]),
            min($rect[1], $rect[3]),
            max($rect[0], $rect[2]),
            max($rect[1], $rect[3]),
        ];
    }

    /**
     * @param array<string, mixed> $markup
     * @param array<string, mixed> $page
     * @return list<array{index: int, rect: list<float>, coordinate_space: string}>
     */
    private function quadRectCandidatesForPage(array $markup, array $page): array
    {
        $usesPdftextGeometry = $this->pageLooksLikePdftextGeometry($markup, $page);
        $rects = $usesPdftextGeometry ? ($markup['pdftext_quad_rects'] ?? []) : ($markup['quad_rects'] ?? []);
        $coordinateSpace = $usesPdftextGeometry ? 'marker_pdftext_display' : 'pdf_page_user_space';

        if (!is_array($rects)) {
            return [];
        }

        $candidates = [];
        foreach ($rects as $quadIndex => $rect) {
            $bbox = $this->bbox($rect);
            if ($bbox === null) {
                continue;
            }

            $candidates[] = [
                'index' => (int) $quadIndex,
                'rect' => $bbox,
                'coordinate_space' => $coordinateSpace,
            ];
        }

        return $candidates;
    }

    /**
     * markerPDF receives span bboxes from pdftext, whose page bbox is normalized
     * to display dimensions and carries the page rotation. Legacy supplied tests
     * without that page-level geometry continue to use raw PDF page-space quads.
     *
     * @param array<string, mixed> $markup
     * @param array<string, mixed> $page
     */
    private function pageLooksLikePdftextGeometry(array $markup, array $page): bool
    {
        if (!array_key_exists('bbox', $page) || !array_key_exists('rotation', $page)) {
            return false;
        }
        if (!is_int($page['rotation']) && !is_float($page['rotation'])) {
            return false;
        }

        $pageBbox = $this->bbox($page['bbox']);
        $displayBbox = $this->bbox($markup['display_page_bbox'] ?? null);
        if ($pageBbox === null || $displayBbox === null) {
            return false;
        }

        $pageRotation = $this->normalizedRotation((int) round((float) $page['rotation']));
        $markupRotation = $this->normalizedRotation((int) ($markup['page_rotation'] ?? 0));
        if ($pageRotation !== $markupRotation) {
            return false;
        }

        return abs($this->rectWidth($pageBbox) - $this->rectWidth($displayBbox)) <= 0.5
            && abs($this->rectHeight($pageBbox) - $this->rectHeight($displayBbox)) <= 0.5;
    }

    /**
     * @param list<float> $rect
     * @param array{bbox: list<float>, rotation: int, user_unit: float, display_bbox: list<float>} $pageGeometry
     * @return list<float>
     */
    private function pageRectToPdftextRect(array $rect, array $pageGeometry): array
    {
        $pageBox = $pageGeometry['bbox'];
        $width = $this->rectWidth($pageBox);
        $height = $this->rectHeight($pageBox);
        $left = $pageBox[0];
        $bottom = $pageBox[1];

        $x1 = $rect[0] - $left;
        $y1 = $rect[1] - $bottom;
        $x2 = $rect[2] - $left;
        $y2 = $rect[3] - $bottom;

        $mapped = $this->normalizeRect(match ($this->normalizedRotation($pageGeometry['rotation'])) {
            90 => [$y1, $x1, $y2, $x2],
            180 => [$width - $x2, $y1, $width - $x1, $y2],
            270 => [$height - $y2, $width - $x2, $height - $y1, $width - $x1],
            default => [$x1, $height - $y2, $x2, $height - $y1],
        });

        $userUnit = (float) ($pageGeometry['user_unit'] ?? 1.0);
        if (abs($userUnit - 1.0) <= 0.000001) {
            return $mapped;
        }

        return [
            $mapped[0] * $userUnit,
            $mapped[1] * $userUnit,
            $mapped[2] * $userUnit,
            $mapped[3] * $userUnit,
        ];
    }

    /**
     * @param list<float> $rect
     */
    private function rectWidth(array $rect): float
    {
        return max(0.0, $rect[2] - $rect[0]);
    }

    /**
     * @param list<float> $rect
     */
    private function rectHeight(array $rect): float
    {
        return max(0.0, $rect[3] - $rect[1]);
    }

    private function valueAfterName(string $body, string $name): ?string
    {
        if (preg_match('/\/' . preg_quote($name, '/') . '\b/s', $body, $match, PREG_OFFSET_CAPTURE) !== 1) {
            return null;
        }

        $offset = $match[0][1] + strlen($match[0][0]);
        return $this->valueStartingAtOffsetWithEnd($body, $offset);
    }

    private function valueStartingAtOffsetWithEnd(string $body, int $offset, ?int &$endOffset = null): ?string
    {
        while ($offset < strlen($body) && ctype_space($body[$offset])) {
            $offset++;
        }

        if ($offset >= strlen($body)) {
            return null;
        }

        if (preg_match('/\G\d+\s+\d+\s+R\b/s', $body, $ref, 0, $offset) === 1) {
            $endOffset = $offset + strlen($ref[0]);
            return $ref[0];
        }

        if ($body[$offset] === '[') {
            $arrayEndOffset = null;
            $this->readPdfArrayAt($body, $offset, $arrayEndOffset);
            $endOffset = $arrayEndOffset;
            return $arrayEndOffset === null ? null : substr($body, $offset, $arrayEndOffset - $offset);
        }

        if (substr($body, $offset, 2) === '<<') {
            $dictionaryEndOffset = null;
            $this->readPdfDictionaryAt($body, $offset, $dictionaryEndOffset);
            $endOffset = $dictionaryEndOffset;
            return $dictionaryEndOffset === null ? null : substr($body, $offset, $dictionaryEndOffset - $offset);
        }

        if ($body[$offset] === '(') {
            $literalEndOffset = $this->skipLiteralString($body, $offset);
            $endOffset = $literalEndOffset;
            return substr($body, $offset, $literalEndOffset - $offset);
        }

        if ($body[$offset] === '<') {
            $hexEndOffset = $this->skipHexString($body, $offset);
            $endOffset = $hexEndOffset;
            return substr($body, $offset, $hexEndOffset - $offset);
        }

        if ($body[$offset] === '/') {
            $nameEndOffset = $this->skipPdfName($body, $offset);
            $endOffset = $nameEndOffset;
            return substr($body, $offset, $nameEndOffset - $offset);
        }

        $end = $offset;
        while ($end < strlen($body) && !ctype_space($body[$end]) && !str_contains('[]()<>{}/%', $body[$end])) {
            $end++;
        }

        $endOffset = $end;
        return substr($body, $offset, max(0, $end - $offset));
    }

    private function stringAfterName(string $body, string $name): ?string
    {
        $offset = 0;
        while (preg_match('/\/' . preg_quote($name, '/') . '\b/s', $body, $match, PREG_OFFSET_CAPTURE, $offset) === 1) {
            $valueOffset = $match[0][1] + strlen($match[0][0]);
            while ($valueOffset < strlen($body) && ctype_space($body[$valueOffset])) {
                $valueOffset++;
            }

            if ($valueOffset >= strlen($body)) {
                return null;
            }

            if ($body[$valueOffset] === '(') {
                $endOffset = $this->skipLiteralString($body, $valueOffset);
                return $this->decodePdfStringBytes($this->decodeLiteralString(substr($body, $valueOffset + 1, $endOffset - $valueOffset - 2)));
            }

            if ($body[$valueOffset] === '<' && substr($body, $valueOffset, 2) !== '<<') {
                $endOffset = $this->skipHexString($body, $valueOffset);
                $hex = preg_replace('/\s+/', '', substr($body, $valueOffset + 1, $endOffset - $valueOffset - 2));
                if ($hex === null || $hex === '') {
                    return null;
                }
                if (strlen($hex) % 2 === 1) {
                    $hex .= '0';
                }
                $bytes = hex2bin($hex);
                return $bytes === false ? null : $this->decodePdfStringBytes($bytes);
            }

            $offset = $valueOffset + 1;
        }

        return null;
    }

    /**
     * @return list<float>|null
     */
    private function floatArrayAfterName(string $body, string $name): ?array
    {
        $value = $this->valueAfterName($body, $name);
        if ($value === null || !str_starts_with(trim($value), '[')) {
            return null;
        }

        $arrayBody = $this->arrayBodyFromValue($value);
        if ($arrayBody === null) {
            return null;
        }

        $numbers = $this->numbersFromPdfArray($arrayBody);
        return $numbers === [] ? null : $numbers;
    }

    private function numberAfterName(string $body, string $name): ?float
    {
        $value = $this->valueAfterName($body, $name);
        if ($value === null || preg_match('/^[+-]?(?:\d+(?:\.\d*)?|\.\d+)/', trim($value), $match) !== 1) {
            return null;
        }

        return (float) $match[0];
    }

    private function integerAfterName(string $body, string $name): ?int
    {
        $number = $this->numberAfterName($body, $name);
        return $number === null ? null : (int) $number;
    }

    /**
     * @param array<int, string> $objects
     */
    private function dictionaryFromValue(string $value, array $objects): ?string
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }

        if (str_starts_with($value, '<<')) {
            return $this->readPdfDictionaryAt($value, 0);
        }

        $objectNumber = $this->indirectObjectNumberFromValue($value);
        if ($objectNumber === null || !isset($objects[$objectNumber])) {
            return null;
        }

        return $this->dictionaryObjectBody($objects[$objectNumber]);
    }

    private function indirectObjectNumberFromValue(string $value): ?int
    {
        return preg_match('/^(\d+)\s+\d+\s+R\b/', trim($value), $match) === 1 ? (int) $match[1] : null;
    }

    private function nameValueAfterName(string $body, string $name): ?string
    {
        if (preg_match('/\/' . preg_quote($name, '/') . '\s*\/([^\s\[\]()<>{}\/%]+)/s', $body, $match) !== 1) {
            return null;
        }

        return $this->decodePdfName($match[1]);
    }

    private function booleanAfterName(string $body, string $name): ?bool
    {
        $value = $this->valueAfterName($body, $name);
        if ($value === null) {
            return null;
        }

        return match (trim($value)) {
            'true' => true,
            'false' => false,
            default => null,
        };
    }

    /**
     * @return array<int, string>
     */
    private function pdfObjects(string $pdfBytes): array
    {
        $objects = [];
        if (!preg_match_all('/(\d+)\s+\d+\s+obj\b(.*?)\bendobj/s', $pdfBytes, $matches, PREG_SET_ORDER)) {
            return $objects;
        }

        foreach ($matches as $match) {
            $objects[(int) $match[1]] = $match[2];
        }

        return $objects;
    }

    /**
     * @return list<int>
     * @param array<int, string> $objects
     */
    private function orderedPageObjectNumbers(array $objects): array
    {
        foreach ($objects as $body) {
            if (preg_match('/\/Type\s*\/Catalog\b/', $body) !== 1 || preg_match('/\/Pages\s+(\d+)\s+\d+\s+R\b/s', $body, $match) !== 1) {
                continue;
            }

            $pages = $this->pageObjectNumbersFromTree((int) $match[1], $objects);
            if ($pages !== []) {
                return $pages;
            }
        }

        $pages = [];
        foreach ($objects as $objectNumber => $body) {
            if (preg_match('/\/Type\s*\/Page\b/', $body) === 1) {
                $pages[] = $objectNumber;
            }
        }

        return $pages;
    }

    /**
     * @return list<int>
     * @param array<int, string> $objects
     * @param array<int, true> $seen
     */
    private function pageObjectNumbersFromTree(int $objectNumber, array $objects, array $seen = []): array
    {
        if (isset($seen[$objectNumber]) || !isset($objects[$objectNumber])) {
            return [];
        }

        $seen[$objectNumber] = true;
        $body = $objects[$objectNumber];
        if (preg_match('/\/Type\s*\/Page\b/', $body) === 1) {
            return [$objectNumber];
        }

        $kids = $this->valueAfterName($body, 'Kids');
        if ($kids === null || !str_starts_with(trim($kids), '[')) {
            return [];
        }

        $arrayBody = $this->arrayBodyFromValue($kids);
        if ($arrayBody === null) {
            return [];
        }

        $pages = [];
        foreach ($this->objectReferences($arrayBody) as $childObjectNumber) {
            foreach ($this->pageObjectNumbersFromTree($childObjectNumber, $objects, $seen) as $pageObjectNumber) {
                $pages[] = $pageObjectNumber;
            }
        }

        return $pages;
    }

    /**
     * @param array<int, string> $objects
     * @return array{bbox: list<float>, rotation: int, user_unit: float, display_bbox: list<float>}
     */
    private function pageGeometry(int $pageObjectNumber, array $objects): array
    {
        $pageBody = $objects[$pageObjectNumber] ?? '';
        $inherited = $this->parentInheritedPageGeometry($pageBody, $objects);

        $mediaBox = $this->boxAfterName($pageBody, 'MediaBox', $objects) ?? $inherited['media_box'] ?? self::DEFAULT_PAGE_BBOX;
        $cropBox = $this->boxAfterName($pageBody, 'CropBox', $objects) ?? $inherited['crop_box'] ?? $mediaBox;
        $bbox = $this->intersectBoxes($mediaBox, $cropBox);

        $rotation = $this->rotationAfterName($pageBody, $objects);
        if ($rotation === null) {
            $rotation = $inherited['rotation'] ?? 0;
        }

        $userUnit = $this->numberValueAfterName($pageBody, 'UserUnit', $objects);
        if ($userUnit === null || $userUnit <= 0.0) {
            $userUnit = 1.0;
        }

        return [
            'bbox' => $bbox,
            'rotation' => $rotation,
            'user_unit' => $userUnit,
            'display_bbox' => $this->displayPageBbox($bbox, $rotation, $userUnit),
        ];
    }

    /**
     * @param array<int, string> $objects
     * @return array{media_box?: list<float>, crop_box?: list<float>, rotation?: int}
     */
    private function parentInheritedPageGeometry(string $pageBody, array $objects): array
    {
        $ancestors = [];
        $seen = [];
        $parent = $this->referenceAfterName($pageBody, 'Parent');
        while ($parent !== null && !isset($seen[$parent]) && isset($objects[$parent])) {
            $seen[$parent] = true;
            $ancestors[] = $objects[$parent];
            $parent = $this->referenceAfterName($objects[$parent], 'Parent');
        }

        $inherited = [];
        foreach (array_reverse($ancestors) as $ancestorBody) {
            $mediaBox = $this->boxAfterName($ancestorBody, 'MediaBox', $objects);
            if ($mediaBox !== null) {
                $inherited['media_box'] = $mediaBox;
            }

            $cropBox = $this->boxAfterName($ancestorBody, 'CropBox', $objects);
            if ($cropBox !== null) {
                $inherited['crop_box'] = $cropBox;
            }

            $rotation = $this->rotationAfterName($ancestorBody, $objects);
            if ($rotation !== null) {
                $inherited['rotation'] = $rotation;
            }
        }

        return $inherited;
    }

    /**
     * @param array<int, string> $objects
     * @return list<float>|null
     */
    private function boxAfterName(string $body, string $name, array $objects): ?array
    {
        $value = $this->valueAfterName($body, $name);
        if ($value === null) {
            return null;
        }

        $value = trim($value);
        if (str_starts_with($value, '[')) {
            $arrayBody = $this->arrayBodyFromValue($value);
            return $arrayBody === null ? null : $this->boxFromNumbers($arrayBody);
        }

        $objectNumber = $this->indirectObjectNumberFromValue($value);
        if ($objectNumber === null || !isset($objects[$objectNumber])) {
            return null;
        }

        $objectBody = trim($objects[$objectNumber]);
        if (!str_starts_with($objectBody, '[')) {
            return null;
        }

        $arrayBody = $this->arrayBodyFromValue($objectBody);
        return $arrayBody === null ? null : $this->boxFromNumbers($arrayBody);
    }

    /**
     * @return list<float>|null
     */
    private function boxFromNumbers(string $body): ?array
    {
        $numbers = $this->numbersFromPdfArray($body);
        if (count($numbers) < 4) {
            return null;
        }

        return $this->normalizeRect(array_slice($numbers, 0, 4));
    }

    /**
     * @param array<int, string> $objects
     */
    private function rotationAfterName(string $body, array $objects): ?int
    {
        $number = $this->numberValueAfterName($body, 'Rotate', $objects);
        if ($number === null || abs($number - round($number)) > 0.000001) {
            return null;
        }

        $rotation = (int) round($number);
        if ($rotation % 90 !== 0) {
            return null;
        }

        return $this->normalizedRotation($rotation);
    }

    /**
     * @param array<int, string> $objects
     */
    private function numberValueAfterName(string $body, string $name, array $objects): ?float
    {
        $value = $this->valueAfterName($body, $name);
        if ($value === null) {
            return null;
        }

        $value = trim($value);
        if (preg_match('/^[+-]?(?:\d+(?:\.\d*)?|\.\d+)(?!\s+\d+\s+R\b)/', $value, $match) === 1) {
            return (float) $match[0];
        }

        $objectNumber = $this->indirectObjectNumberFromValue($value);
        if ($objectNumber === null || !isset($objects[$objectNumber])) {
            return null;
        }

        $objectBody = trim($objects[$objectNumber]);
        return preg_match('/^[+-]?(?:\d+(?:\.\d*)?|\.\d+)$/', $objectBody) === 1 ? (float) $objectBody : null;
    }

    private function referenceAfterName(string $body, string $name): ?int
    {
        $value = $this->valueAfterName($body, $name);
        return $value === null ? null : $this->indirectObjectNumberFromValue($value);
    }

    /**
     * @param list<float> $mediaBox
     * @param list<float> $cropBox
     * @return list<float>
     */
    private function intersectBoxes(array $mediaBox, array $cropBox): array
    {
        $left = max($mediaBox[0], $cropBox[0]);
        $bottom = max($mediaBox[1], $cropBox[1]);
        $right = min($mediaBox[2], $cropBox[2]);
        $top = min($mediaBox[3], $cropBox[3]);

        return [
            $left,
            $bottom,
            max($left, $right),
            max($bottom, $top),
        ];
    }

    /**
     * @param list<float> $bbox
     * @return list<float>
     */
    private function displayPageBbox(array $bbox, int $rotation, float $userUnit = 1.0): array
    {
        $width = $this->rectWidth($bbox) * $userUnit;
        $height = $this->rectHeight($bbox) * $userUnit;
        if (in_array($this->normalizedRotation($rotation), [90, 270], true)) {
            [$width, $height] = [$height, $width];
        }

        return [0.0, 0.0, $width, $height];
    }

    private function normalizedRotation(int $rotation): int
    {
        $rotation %= 360;
        if ($rotation < 0) {
            $rotation += 360;
        }

        return in_array($rotation, [0, 90, 180, 270], true) ? $rotation : 0;
    }

    /**
     * @return list<int>
     */
    private function objectReferences(string $value): array
    {
        if (!preg_match_all('/(\d+)\s+\d+\s+R\b/', $value, $matches)) {
            return [];
        }

        return array_map('intval', $matches[1]);
    }

    private function dictionaryObjectBody(string $objectBody): ?string
    {
        $offset = strpos($objectBody, '<<');
        return $offset === false ? null : $this->readPdfDictionaryAt($objectBody, $offset);
    }

    /**
     * @return list<string>
     */
    private function directDictionaries(string $value): array
    {
        $dictionaries = [];
        $offset = 0;
        while (($start = strpos($value, '<<', $offset)) !== false) {
            $endOffset = null;
            $body = $this->readPdfDictionaryAt($value, $start, $endOffset);
            if ($body === null || $endOffset === null) {
                break;
            }
            $dictionaries[] = $body;
            $offset = $endOffset;
        }

        return $dictionaries;
    }

    private function arrayBodyFromValue(string $value): ?string
    {
        $offset = strpos($value, '[');
        return $offset === false ? null : $this->readPdfArrayAt($value, $offset);
    }

    private function readPdfDictionaryAt(string $value, int $offset, ?int &$endOffset = null): ?string
    {
        if (substr($value, $offset, 2) !== '<<') {
            return null;
        }

        $depth = 0;
        $bodyStart = $offset + 2;
        for ($index = $offset, $length = strlen($value); $index < $length - 1; $index++) {
            $char = $value[$index];
            if ($char === '(') {
                $index = $this->skipLiteralString($value, $index) - 1;
                continue;
            }
            if ($char === '<' && substr($value, $index, 2) !== '<<') {
                $index = $this->skipHexString($value, $index) - 1;
                continue;
            }

            $pair = substr($value, $index, 2);
            if ($pair === '<<') {
                $depth++;
                $index++;
                continue;
            }

            if ($pair !== '>>') {
                continue;
            }

            $depth--;
            if ($depth === 0) {
                $endOffset = $index + 2;
                return substr($value, $bodyStart, $index - $bodyStart);
            }
            $index++;
        }

        return null;
    }

    private function readPdfArrayAt(string $value, int $offset, ?int &$endOffset = null): ?string
    {
        if (($value[$offset] ?? '') !== '[') {
            return null;
        }

        $depth = 0;
        $bodyStart = $offset + 1;
        for ($index = $offset, $length = strlen($value); $index < $length; $index++) {
            $char = $value[$index];
            if ($char === '(') {
                $index = $this->skipLiteralString($value, $index) - 1;
                continue;
            }
            if ($char === '<' && substr($value, $index, 2) === '<<') {
                $endDictionary = null;
                $this->readPdfDictionaryAt($value, $index, $endDictionary);
                if ($endDictionary !== null) {
                    $index = $endDictionary - 1;
                    continue;
                }
            }
            if ($char === '<') {
                $index = $this->skipHexString($value, $index) - 1;
                continue;
            }
            if ($char === '[') {
                $depth++;
                continue;
            }
            if ($char !== ']') {
                continue;
            }

            $depth--;
            if ($depth === 0) {
                $endOffset = $index + 1;
                return substr($value, $bodyStart, $index - $bodyStart);
            }
        }

        return null;
    }

    private function skipLiteralString(string $value, int $offset): int
    {
        $depth = 0;
        for ($index = $offset, $length = strlen($value); $index < $length; $index++) {
            $char = $value[$index];
            if ($char === '\\') {
                $index++;
                continue;
            }
            if ($char === '(') {
                $depth++;
                continue;
            }
            if ($char !== ')') {
                continue;
            }

            $depth--;
            if ($depth === 0) {
                return $index + 1;
            }
        }

        return strlen($value);
    }

    private function skipHexString(string $value, int $offset): int
    {
        $end = strpos($value, '>', $offset + 1);
        return $end === false ? strlen($value) : $end + 1;
    }

    private function skipPdfName(string $value, int $offset): int
    {
        $end = $offset + 1;
        while ($end < strlen($value) && !ctype_space($value[$end]) && !str_contains('[]()<>{}/%', $value[$end])) {
            $end++;
        }

        return $end;
    }

    /**
     * @return list<float>
     */
    private function numbersFromPdfArray(string $arrayBody): array
    {
        if (!preg_match_all('/[+-]?(?:\d+(?:\.\d*)?|\.\d+)/', $arrayBody, $matches)) {
            return [];
        }

        return array_map('floatval', $matches[0]);
    }

    /**
     * @return list<float>|null
     */
    private function bbox(mixed $value): ?array
    {
        if (!is_array($value) || count($value) !== 4) {
            return null;
        }

        $bbox = [];
        foreach (array_values($value) as $part) {
            if (!is_int($part) && !is_float($part)) {
                return null;
            }
            $bbox[] = (float) $part;
        }

        return $this->normalizeRect($bbox);
    }

    /**
     * @param list<float> $left
     * @param list<float> $right
     */
    private function bboxesIntersect(array $left, array $right): bool
    {
        return min($left[2], $right[2]) - max($left[0], $right[0]) > 0.0
            && min($left[3], $right[3]) - max($left[1], $right[1]) > 0.0;
    }

    private function decodePdfStringBytes(string $bytes): string
    {
        $prefix = strtolower(bin2hex(substr($bytes, 0, 2)));
        if ($prefix === 'feff') {
            $decoded = iconv('UTF-16BE', 'UTF-8//IGNORE', substr($bytes, 2));
            return $decoded === false ? '' : $decoded;
        }
        if ($prefix === 'fffe') {
            $decoded = iconv('UTF-16LE', 'UTF-8//IGNORE', substr($bytes, 2));
            return $decoded === false ? '' : $decoded;
        }

        return $bytes;
    }

    private function decodeLiteralString(string $value): string
    {
        $value = preg_replace("/\\\\\r\n|\\\\\n|\\\\\r/s", '', $value) ?? $value;

        return preg_replace_callback('/\\\\([0-7]{1,3}|.)/s', static function (array $match): string {
            return match ($match[1]) {
                'n' => "\n",
                'r' => "\r",
                't' => "\t",
                'b' => "\x08",
                'f' => "\x0c",
                '(' => '(',
                ')' => ')',
                '\\' => '\\',
                default => preg_match('/^[0-7]+$/', $match[1]) === 1 ? chr(octdec($match[1]) & 0xff) : $match[1],
            };
        }, $value) ?? $value;
    }

    private function decodePdfName(string $name): string
    {
        return preg_replace_callback('/#([0-9A-Fa-f]{2})/', static fn (array $match): string => chr(hexdec($match[1])), $name) ?? $name;
    }
}
