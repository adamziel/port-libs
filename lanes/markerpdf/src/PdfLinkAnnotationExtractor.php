<?php

declare(strict_types=1);

namespace PortLibs\MarkerPDF;

final class PdfLinkAnnotationExtractor
{
    private const DEFAULT_PAGE_BBOX = [0.0, 0.0, 612.0, 792.0];
    private const BORDER_STYLE_NAMES = [
        'S' => 'solid',
        'D' => 'dashed',
        'B' => 'beveled',
        'I' => 'inset',
        'U' => 'underline',
    ];
    private const HIGHLIGHT_MODE_LABELS = [
        'N' => 'none',
        'I' => 'invert',
        'O' => 'outline',
        'P' => 'push',
        'T' => 'toggle',
    ];

    /** @var array<int, array<int, string>> */
    private array $objectBodiesByGeneration = [];

    /**
     * Native boundary for PDF page /Annots link actions.
     *
     * @return list<array{pnum: int, page_object: int, links: list<array<string, mixed>>}>
     */
    public function extractPageLinks(string $pdfBytes): array
    {
        $objects = $this->pdfObjects($pdfBytes);
        $this->objectBodiesByGeneration = $this->pdfObjectBodiesByGeneration($pdfBytes);
        $actionReviewer = new PdfActionReviewExtractor($pdfBytes);
        $structureReviewsByAnnotationObject = $this->annotationStructureReviewsByObject($pdfBytes);
        $context = $this->linkReviewContext($pdfBytes);
        $pageObjectNumbers = $this->orderedPageObjectNumbers($objects);
        $pages = [];

        foreach ($pageObjectNumbers as $pnum => $pageObjectNumber) {
            if (!isset($objects[$pageObjectNumber])) {
                continue;
            }

            $links = $this->linksFromPageObject(
                $objects[$pageObjectNumber],
                $objects,
                $actionReviewer,
                $structureReviewsByAnnotationObject,
                $context,
                $this->pageGeometry($pageObjectNumber, $objects)
            );
            if ($links === []) {
                continue;
            }

            $pages[] = [
                'pnum' => $pnum,
                'page_object' => $pageObjectNumber,
                'links' => $links,
            ];
        }

        return $pages;
    }

    /**
     * Applies extracted PDF link annotations to supplied Marker/pdftext page
     * spans by bbox intersection, preserving the original page/block shape.
     *
     * @param list<array<string, mixed>> $pages
     * @return list<array<string, mixed>>
     */
    public function applyLinksToPages(array $pages, string $pdfBytes): array
    {
        $linksByPage = [];
        foreach ($this->extractPageLinks($pdfBytes) as $pageLinks) {
            $linksByPage[$pageLinks['pnum']] = $pageLinks['links'];
        }

        $out = [];
        foreach (array_values($pages) as $index => $page) {
            if (!is_array($page)) {
                continue;
            }

            $pnum = isset($page['pnum']) ? (int) $page['pnum'] : $index;
            $links = $linksByPage[$pnum] ?? [];
            if ($links === []) {
                $out[] = $page;
                continue;
            }

            $page['links'] = $links;
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

                        $candidate = $this->linkForSpan($span, $links, $page);
                        if ($candidate === null) {
                            continue;
                        }

                        $link = $candidate['link'];
                        $page['blocks'][$blockIndex]['lines'][$lineIndex]['spans'][$spanIndex]['link_rect'] = $candidate['rect'];
                        $page['blocks'][$blockIndex]['lines'][$lineIndex]['spans'][$spanIndex]['link_rect_coordinate_space'] = $candidate['coordinate_space'];
                        $page['blocks'][$blockIndex]['lines'][$lineIndex]['spans'][$spanIndex]['link_page_rect'] = $link['rect'];
                        if (array_key_exists('visible_rect', $link)) {
                            $page['blocks'][$blockIndex]['lines'][$lineIndex]['spans'][$spanIndex]['link_visible_page_rect'] = $link['visible_rect'];
                        }
                        if (array_key_exists('pdftext_rect', $link)) {
                            $page['blocks'][$blockIndex]['lines'][$lineIndex]['spans'][$spanIndex]['link_pdftext_rect'] = $link['pdftext_rect'];
                        }
                        if (array_key_exists('pdftext_visible_rect', $link)) {
                            $page['blocks'][$blockIndex]['lines'][$lineIndex]['spans'][$spanIndex]['link_pdftext_visible_rect'] = $link['pdftext_visible_rect'];
                        }
                        foreach ([
                            'rect_clipped_to_page' => 'link_page_rect_clipped_to_page',
                            'rect_inside_page_bbox' => 'link_page_rect_inside_page_bbox',
                        ] as $sourceKey => $spanKey) {
                            if (array_key_exists($sourceKey, $link)) {
                                $page['blocks'][$blockIndex]['lines'][$lineIndex]['spans'][$spanIndex][$spanKey] = $link[$sourceKey];
                            }
                        }
                        if (array_key_exists('quad_index', $candidate)) {
                            $quadIndex = $candidate['quad_index'];
                            $page['blocks'][$blockIndex]['lines'][$lineIndex]['spans'][$spanIndex]['link_quad_index'] = $quadIndex;
                            $page['blocks'][$blockIndex]['lines'][$lineIndex]['spans'][$spanIndex]['link_quad_rect'] = $candidate['rect'];
                            $page['blocks'][$blockIndex]['lines'][$lineIndex]['spans'][$spanIndex]['link_page_quad_rect'] = $link['quad_rects'][$quadIndex] ?? null;
                            $page['blocks'][$blockIndex]['lines'][$lineIndex]['spans'][$spanIndex]['link_pdftext_quad_rect'] = $link['pdftext_quad_rects'][$quadIndex] ?? null;
                            if (array_key_exists('visible_quad_position', $candidate)) {
                                $visibleQuadPosition = $candidate['visible_quad_position'];
                                $page['blocks'][$blockIndex]['lines'][$lineIndex]['spans'][$spanIndex]['link_visible_page_quad_rect'] = $link['visible_quad_rects'][$visibleQuadPosition] ?? $candidate['rect'];
                                $page['blocks'][$blockIndex]['lines'][$lineIndex]['spans'][$spanIndex]['link_pdftext_visible_quad_rect'] = $link['pdftext_visible_quad_rects'][$visibleQuadPosition] ?? null;
                            }
                        }
                        $page['blocks'][$blockIndex]['lines'][$lineIndex]['spans'][$spanIndex]['link_annotation_object'] = $link['annotation_object'];
                        $page['blocks'][$blockIndex]['lines'][$lineIndex]['spans'][$spanIndex]['link_annotation_subtype'] = $link['annotation_subtype'];
                        $page['blocks'][$blockIndex]['lines'][$lineIndex]['spans'][$spanIndex]['link_widget_annotation'] = $link['widget_annotation'];
                        $page['blocks'][$blockIndex]['lines'][$lineIndex]['spans'][$spanIndex]['link_action_type'] = $link['action_type'];
                        $page['blocks'][$blockIndex]['lines'][$lineIndex]['spans'][$spanIndex]['link_safety'] = $link['safety'];
                        $page['blocks'][$blockIndex]['lines'][$lineIndex]['spans'][$spanIndex]['link_executes_on_import'] = false;
                        $page['blocks'][$blockIndex]['lines'][$lineIndex]['spans'][$spanIndex]['link_actions_review'] = $link['actions'];
                        $page['blocks'][$blockIndex]['lines'][$lineIndex]['spans'][$spanIndex]['link_additional_actions_review'] = $link['additional_actions'];
                        if (array_key_exists('struct_parent', $link)) {
                            $page['blocks'][$blockIndex]['lines'][$lineIndex]['spans'][$spanIndex]['link_struct_parent'] = $link['struct_parent'];
                        }
                        if (is_array($link['structure_parent'] ?? null)) {
                            $page['blocks'][$blockIndex]['lines'][$lineIndex]['spans'][$spanIndex]['link_structure_parent'] = $link['structure_parent'];
                        }
                        foreach ([
                            'inherited_widget_link_keys' => 'link_inherited_widget_keys',
                            'widget_field_parent_object' => 'link_widget_field_parent_object',
                            'widget_field_chain' => 'link_widget_field_chain',
                            'widget_link_action_source' => 'link_widget_action_source',
                            'widget_link_field_sources' => 'link_widget_field_sources',
                        ] as $sourceKey => $spanKey) {
                            if (array_key_exists($sourceKey, $link)) {
                                $page['blocks'][$blockIndex]['lines'][$lineIndex]['spans'][$spanIndex][$spanKey] = $link[$sourceKey];
                            }
                        }

                        if (is_string($link['uri'] ?? null) && ($link['is_safe_uri'] ?? false) === true) {
                            $page['blocks'][$blockIndex]['lines'][$lineIndex]['spans'][$spanIndex]['link_uri'] = $link['uri'];
                        }

                        if (($link['safety'] ?? null) === 'remote-document-review') {
                            foreach ([
                                'file' => 'link_remote_file',
                                'destination' => 'link_remote_destination',
                                'destination_page' => 'link_remote_destination_page',
                                'view_mode' => 'link_remote_view_mode',
                                'view_position' => 'link_remote_view_position',
                                'view_parameters' => 'link_remote_view_parameters',
                                'new_window' => 'link_remote_new_window',
                            ] as $sourceKey => $spanKey) {
                                if (array_key_exists($sourceKey, $link)) {
                                    $page['blocks'][$blockIndex]['lines'][$lineIndex]['spans'][$spanIndex][$spanKey] = $link[$sourceKey];
                                }
                            }
                        } else {
                            if (array_key_exists('destination', $link)) {
                                $page['blocks'][$blockIndex]['lines'][$lineIndex]['spans'][$spanIndex]['link_destination'] = $link['destination'];
                            }
                            if (array_key_exists('destination_page', $link)) {
                                $page['blocks'][$blockIndex]['lines'][$lineIndex]['spans'][$spanIndex]['link_destination_page'] = $link['destination_page'];
                            }
                            if (array_key_exists('view_mode', $link)) {
                                $page['blocks'][$blockIndex]['lines'][$lineIndex]['spans'][$spanIndex]['link_view_mode'] = $link['view_mode'];
                            }
                            if (array_key_exists('view_position', $link)) {
                                $page['blocks'][$blockIndex]['lines'][$lineIndex]['spans'][$spanIndex]['link_view_position'] = $link['view_position'];
                            }
                            if (array_key_exists('view_parameters', $link)) {
                                $page['blocks'][$blockIndex]['lines'][$lineIndex]['spans'][$spanIndex]['link_view_parameters'] = $link['view_parameters'];
                            }
                        }
                        foreach ([
                            'destination_page_label' => 'link_destination_page_label',
                            'target_display_duration' => 'link_target_display_duration',
                            'target_page_transition' => 'link_target_page_transition',
                            'target_page_actions' => 'link_target_page_actions',
                            'target_outline_titles' => 'link_target_outline_titles',
                            'target_outline_levels' => 'link_target_outline_levels',
                            'document_metadata_dates' => 'link_document_metadata_dates',
                        ] as $sourceKey => $spanKey) {
                            if (array_key_exists($sourceKey, $link)) {
                                $page['blocks'][$blockIndex]['lines'][$lineIndex]['spans'][$spanIndex][$spanKey] = $link[$sourceKey];
                            }
                        }
                        foreach ([
                            'contents' => 'link_annotation_contents',
                            'title' => 'link_annotation_title',
                            'border_color' => 'link_annotation_border_color',
                            'highlight_mode' => 'link_annotation_highlight_mode',
                            'highlight_mode_label' => 'link_annotation_highlight_mode_label',
                            'border' => 'link_annotation_border',
                        ] as $sourceKey => $spanKey) {
                            if (array_key_exists($sourceKey, $link)) {
                                $page['blocks'][$blockIndex]['lines'][$lineIndex]['spans'][$spanIndex][$spanKey] = $link[$sourceKey];
                            }
                        }
                    }
                }
            }

            $out[] = $page;
        }

        return $out;
    }

    /**
     * @param array<string, mixed> $span
     * @param list<array<string, mixed>> $links
     * @param array<string, mixed> $page
     * @return array{link: array<string, mixed>, rect: list<float>, coordinate_space: string, quad_index?: int, visible_quad_position?: int}|null
     */
    private function linkForSpan(array $span, array $links, array $page): ?array
    {
        $bbox = $this->bbox($span['bbox'] ?? null);
        if ($bbox === null) {
            return null;
        }

        foreach ($links as $link) {
            foreach ($this->linkRectCandidatesForPage($link, $page) as $candidate) {
                if ($this->bboxesIntersect($bbox, $candidate['rect'])) {
                    return [
                        'link' => $link,
                        'rect' => $candidate['rect'],
                        'coordinate_space' => $candidate['coordinate_space'],
                    ]
                        + (array_key_exists('quad_index', $candidate) ? ['quad_index' => $candidate['quad_index']] : [])
                        + (array_key_exists('visible_quad_position', $candidate) ? ['visible_quad_position' => $candidate['visible_quad_position']] : []);
                }
            }
        }

        return null;
    }

    /**
     * @param array<int, string> $objects
     * @param array<int, array<string, mixed>> $structureReviewsByAnnotationObject
     * @param array<string, mixed> $context
     * @param array{bbox: list<float>, rotation: int, user_unit: float, display_bbox: list<float>} $pageGeometry
     * @return list<array<string, mixed>>
     */
    private function linksFromPageObject(
        string $pageBody,
        array $objects,
        PdfActionReviewExtractor $actionReviewer,
        array $structureReviewsByAnnotationObject,
        array $context,
        array $pageGeometry
    ): array {
        $annotationBodies = $this->annotationBodiesForPage($pageBody, $objects);
        $links = [];

        foreach ($annotationBodies as $annotation) {
            $link = $this->linkFromAnnotationBody(
                $annotation['body'],
                $objects,
                $actionReviewer,
                $annotation['object'],
                $structureReviewsByAnnotationObject,
                $context,
                $pageGeometry
            );
            if ($link !== null) {
                $links[] = $link;
            }
        }

        return $links;
    }

    /**
     * @param array<int, string> $objects
     * @return list<array{body: string, object: int|null}>
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

        return $this->dictionaryValueAfterName($dictionary, $name);
    }

    /**
     * @param array<int, string> $objects
     * @return list<array{body: string, object: int|null}>
     */
    private function annotationBodiesFromValue(string $value, array $objects): array
    {
        $value = trim($value);
        if ($value === '') {
            return [];
        }

        $reference = $this->objectReferenceFromValue($value);
        if ($reference !== null) {
            $objectNumber = $reference['object'];
            $objectBody = $this->objectBodyForReference($objectNumber, $reference['generation'], $objects);
            if ($objectBody === null) {
                return [];
            }

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
     * @param array<int, string> $objects
     * @return list<array{body: string, object: int|null}>
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
            $this->skipWhitespaceAndComments($arrayBody, $offset);
            if ($offset >= $length) {
                break;
            }

            $endOffset = null;
            $value = $this->valueStartingAtOffsetWithEnd($arrayBody, $offset, $endOffset);
            if ($value === null || $endOffset === null || $endOffset <= $offset) {
                $offset++;
                continue;
            }

            $value = trim($value);
            if (str_starts_with($value, '<<')) {
                $dictionary = $this->readPdfDictionaryAt($value, 0);
                if ($dictionary !== null) {
                    $annotations[] = ['body' => $dictionary, 'object' => null];
                }
                $offset = $endOffset;
                continue;
            }

            if (preg_match('/^(\d+)\s+(\d+)\s+R\b/s', $value, $match) === 1) {
                $objectNumber = (int) $match[1];
                $objectBody = $this->objectBodyForReference($objectNumber, (int) $match[2], $objects);
                $dictionary = $objectBody === null ? null : $this->dictionaryObjectBody($objectBody);
                if ($dictionary !== null) {
                    $annotations[] = ['body' => $dictionary, 'object' => $objectNumber];
                }
                $offset = $endOffset;
                continue;
            }

            $offset = $endOffset;
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
     * @param array<int, string> $objects
     * @param array<int, array<string, mixed>> $structureReviewsByAnnotationObject
     * @param array<string, mixed> $context
     * @param array{bbox: list<float>, rotation: int, user_unit: float, display_bbox: list<float>} $pageGeometry
     * @return array<string, mixed>|null
     */
    private function linkFromAnnotationBody(
        string $annotationBody,
        array $objects,
        PdfActionReviewExtractor $actionReviewer,
        ?int $annotationObject,
        array $structureReviewsByAnnotationObject,
        array $context,
        array $pageGeometry
    ): ?array
    {
        $subtype = $this->annotationSubtype($annotationBody);
        if (!in_array($subtype, ['Link', 'Widget'], true)) {
            return null;
        }

        if ($this->annotationHiddenFromLinkImport($annotationBody, $objects)) {
            return null;
        }

        $effectiveAnnotation = $subtype === 'Widget'
            ? $this->effectiveWidgetLinkAnnotationBody($annotationBody, $objects)
            : [
                'body' => $annotationBody,
                'inherited_keys' => [],
                'field_chain' => [],
                'field_sources' => [],
            ];

        $rect = $this->rectFromAnnotation($annotationBody, $objects);
        if ($rect === null) {
            return null;
        }
        $visibleRect = $this->intersectRects($rect, $pageGeometry['bbox']);
        if (!$this->rectHasArea($visibleRect)) {
            return null;
        }

        $pdftextRect = $this->pageRectToPdftextRect($rect, $pageGeometry);
        $pdftextVisibleRect = $this->pageRectToPdftextRect($visibleRect, $pageGeometry);
        $quadPoints = $this->quadPointsFromAnnotation($annotationBody, $objects);
        $quadRects = array_map(fn (array $quad): array => $this->rectFromQuad($quad), $quadPoints);
        $pdftextQuadRects = array_map(fn (array $quadRect): array => $this->pageRectToPdftextRect($quadRect, $pageGeometry), $quadRects);
        $visibleQuadRects = [];
        $pdftextVisibleQuadRects = [];
        $visibleQuadSourceIndexes = [];
        $excludedQuadRectCount = 0;
        $quadRectsClipped = false;
        foreach ($quadRects as $quadIndex => $quadRect) {
            $visibleQuadRect = $this->intersectRects($quadRect, $pageGeometry['bbox']);
            if (!$this->rectHasArea($visibleQuadRect)) {
                $excludedQuadRectCount++;
                continue;
            }

            if (!$this->rectsApproximatelyEqual($quadRect, $visibleQuadRect)) {
                $quadRectsClipped = true;
            }

            $visibleQuadRects[] = $visibleQuadRect;
            $pdftextVisibleQuadRects[] = $this->pageRectToPdftextRect($visibleQuadRect, $pageGeometry);
            $visibleQuadSourceIndexes[] = (int) $quadIndex;
        }
        if ($quadPoints !== [] && $visibleQuadRects === []) {
            return null;
        }

        $review = $actionReviewer->reviewAnnotationActions($effectiveAnnotation['body']);
        $review['actions'] = $this->withLinkTargetContextRows($review['actions'], $context);
        $review['additional_actions'] = $this->withLinkTargetContextRows($review['additional_actions'], $context);
        $primary = $this->primaryLinkAction($review['actions']);
        if ($primary === null) {
            return null;
        }

        $link = $primary + [
            'rect' => $rect,
            'visible_rect' => $visibleRect,
            'pdftext_rect' => $pdftextRect,
            'pdftext_visible_rect' => $pdftextVisibleRect,
            'page_bbox' => $pageGeometry['bbox'],
            'page_rotation' => $pageGeometry['rotation'],
            'page_user_unit' => $pageGeometry['user_unit'],
            'display_page_bbox' => $pageGeometry['display_bbox'],
            'rect_inside_page_bbox' => $this->rectsApproximatelyEqual($rect, $visibleRect),
            'rect_clipped_to_page' => !$this->rectsApproximatelyEqual($rect, $visibleRect),
            'annotation_object' => $annotationObject,
            'annotation_subtype' => $subtype,
            'widget_annotation' => $subtype === 'Widget',
            'actions' => $review['actions'],
            'additional_actions' => $review['additional_actions'],
            'executes_on_import' => false,
        ] + $this->presentationReviewFromAnnotation($annotationBody, $objects);
        if ($quadPoints !== []) {
            $link['quad_points'] = $quadPoints;
            $link['quad_rects'] = $quadRects;
            $link['pdftext_quad_rects'] = $pdftextQuadRects;
            $link['visible_quad_rects'] = $visibleQuadRects;
            $link['pdftext_visible_quad_rects'] = $pdftextVisibleQuadRects;
            $link['visible_quad_source_indexes'] = $visibleQuadSourceIndexes;
            $link['quad_rects_clipped_to_page'] = $quadRectsClipped;
            $link['quad_rects_excluded_by_page_bbox'] = $excludedQuadRectCount;
        }

        if ($effectiveAnnotation['inherited_keys'] !== []) {
            $fieldChain = $effectiveAnnotation['field_chain'];
            $primaryFromField = in_array('A', $effectiveAnnotation['inherited_keys'], true)
                || in_array('Dest', $effectiveAnnotation['inherited_keys'], true);
            $link['inherited_widget_link_keys'] = $effectiveAnnotation['inherited_keys'];
            $link['widget_field_parent_object'] = $fieldChain[0] ?? null;
            $link['widget_field_chain'] = $fieldChain;
            $link['widget_link_action_source'] = $primaryFromField ? 'field_parent' : 'annotation';
            $link['widget_link_field_sources'] = $effectiveAnnotation['field_sources'];
        }

        if ($annotationObject !== null && isset($structureReviewsByAnnotationObject[$annotationObject])) {
            $link += $structureReviewsByAnnotationObject[$annotationObject];
            if (is_int($link['struct_parent'] ?? null) && is_array($link['structure_parent'] ?? null)) {
                $link['actions'] = PdfActionReviewExtractor::actionsWithAnnotationStructureParentContext(
                    $link['actions'],
                    $annotationObject,
                    $link['struct_parent'],
                    $link['structure_parent']
                );
                $link['additional_actions'] = PdfActionReviewExtractor::actionsWithAnnotationStructureParentContext(
                    $link['additional_actions'],
                    $annotationObject,
                    $link['struct_parent'],
                    $link['structure_parent']
                );
            }
        }

        return $link;
    }

    /**
     * Split AcroForm widgets sometimes keep activation actions on the terminal
     * field while the page /Annots entry owns the visible widget geometry.
     * Inherit only link-relevant action keys, never page membership or payload
     * text, so detached field-only widgets remain outside the page boundary.
     *
     * @param array<int, string> $objects
     * @return array{body: string, inherited_keys: list<string>, field_chain: list<int>, field_sources: array<string, int>}
     */
    private function effectiveWidgetLinkAnnotationBody(string $annotationBody, array $objects): array
    {
        $fieldChain = $this->widgetFieldParentChain($annotationBody, $objects);
        if ($fieldChain === []) {
            return [
                'body' => $annotationBody,
                'inherited_keys' => [],
                'field_chain' => [],
                'field_sources' => [],
            ];
        }

        $additions = [];
        $fieldSources = [];
        foreach (['A', 'AA', 'Dest'] as $key) {
            if ($this->valueAfterName($annotationBody, $key) !== null) {
                continue;
            }

            foreach ($fieldChain as $fieldObject) {
                $fieldBody = isset($objects[$fieldObject])
                    ? ($this->dictionaryObjectBody($objects[$fieldObject]) ?? trim($objects[$fieldObject]))
                    : '';
                if ($fieldBody === '') {
                    continue;
                }

                $value = $this->valueAfterName($fieldBody, $key);
                if ($value === null) {
                    continue;
                }

                $additions[] = '/' . $key . ' ' . trim($value);
                $fieldSources[$key] = $fieldObject;
                break;
            }
        }

        if ($additions === []) {
            return [
                'body' => $annotationBody,
                'inherited_keys' => [],
                'field_chain' => $fieldChain,
                'field_sources' => [],
            ];
        }

        return [
            'body' => rtrim($annotationBody) . ' ' . implode(' ', $additions),
            'inherited_keys' => array_keys($fieldSources),
            'field_chain' => $fieldChain,
            'field_sources' => $fieldSources,
        ];
    }

    /**
     * @param array<int, string> $objects
     * @return list<int>
     */
    private function widgetFieldParentChain(string $annotationBody, array $objects): array
    {
        $chain = [];
        $seen = [];
        $parent = $this->referenceAfterName($annotationBody, 'Parent');

        while ($parent !== null && !isset($seen[$parent]) && isset($objects[$parent])) {
            $seen[$parent] = true;
            $chain[] = $parent;

            $parentBody = $this->dictionaryObjectBody($objects[$parent]) ?? trim($objects[$parent]);
            $parent = $this->referenceAfterName($parentBody, 'Parent');
        }

        return $chain;
    }

    /**
     * @return array{
     *     page_labels: list<string>,
     *     page_presentations_by_page: array<int, array<string, mixed>>,
     *     outline_rows: list<array<string, mixed>>,
     *     document_metadata_dates: array<string, string>
     * }
     */
    private function linkReviewContext(string $pdfBytes): array
    {
        $navigation = (new PdfOutlineExtractor())->getNavigationReviewMetadata($pdfBytes, false);
        $pagePresentationsByPage = [];
        foreach (($navigation['page_presentations'] ?? []) as $pagePresentation) {
            if (!is_array($pagePresentation)) {
                continue;
            }

            $pageIndex = $pagePresentation['pnum'] ?? null;
            if (is_int($pageIndex)) {
                $pagePresentationsByPage[$pageIndex] = $pagePresentation;
            }
        }

        return [
            'page_labels' => (new PdfTextExtractor())->extractPageLabels($pdfBytes),
            'page_presentations_by_page' => $pagePresentationsByPage,
            'outline_rows' => is_array($navigation['outline'] ?? null) ? $navigation['outline'] : [],
            'document_metadata_dates' => $this->documentMetadataDates($pdfBytes),
        ];
    }

    /**
     * @return array<string, string>
     */
    private function documentMetadataDates(string $pdfBytes): array
    {
        $metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdfBytes);
        $dates = [];

        foreach ([
            'created_at',
            'created_at_utc',
            'modified_at',
            'modified_at_utc',
            'metadata_date',
            'metadata_date_utc',
        ] as $field) {
            if (is_string($metadata[$field] ?? null) && $metadata[$field] !== '') {
                $dates[$field] = $metadata[$field];
            }
        }

        return $dates;
    }

    /**
     * @param list<array<string, mixed>> $rows
     * @param array<string, mixed> $context
     * @return list<array<string, mixed>>
     */
    private function withLinkTargetContextRows(array $rows, array $context): array
    {
        foreach ($rows as $index => $row) {
            $rows[$index] = $this->withLinkTargetContext($row, $context);
        }

        return $rows;
    }

    /**
     * @param array<string, mixed> $row
     * @param array<string, mixed> $context
     * @return array<string, mixed>
     */
    private function withLinkTargetContext(array $row, array $context): array
    {
        if (($row['safety'] ?? null) !== 'local-destination') {
            return $row;
        }

        $pageIndex = $row['destination_page'] ?? $row['page'] ?? null;
        if (!is_int($pageIndex)) {
            return $row;
        }

        $pageLabels = $context['page_labels'] ?? [];
        if (is_array($pageLabels)) {
            $row['destination_page_label'] = $pageLabels[$pageIndex] ?? (string) ($pageIndex + 1);
        }

        $pagePresentationsByPage = $context['page_presentations_by_page'] ?? [];
        if (is_array($pagePresentationsByPage) && isset($pagePresentationsByPage[$pageIndex]) && is_array($pagePresentationsByPage[$pageIndex])) {
            $pagePresentation = $pagePresentationsByPage[$pageIndex];
            $row['target_display_duration'] = $pagePresentation['display_duration'] ?? null;
            $row['target_page_transition'] = $pagePresentation['transition'] ?? null;
            $row['target_page_actions'] = is_array($pagePresentation['actions'] ?? null)
                ? $pagePresentation['actions']
                : [];
        }

        $outlineSummary = $this->matchingOutlineTargetSummary($row, $context['outline_rows'] ?? []);
        if ($outlineSummary !== []) {
            $row += $outlineSummary;
        }

        $documentDates = $context['document_metadata_dates'] ?? [];
        if (is_array($documentDates) && $documentDates !== []) {
            $row['document_metadata_dates'] = $documentDates;
        }

        return $row;
    }

    /**
     * @param array<string, mixed> $row
     * @param mixed $outlineRows
     * @return array<string, mixed>
     */
    private function matchingOutlineTargetSummary(array $row, mixed $outlineRows): array
    {
        if (!is_array($outlineRows)) {
            return [];
        }

        $destination = is_string($row['destination'] ?? null) ? $row['destination'] : null;
        $pageIndex = $row['destination_page'] ?? $row['page'] ?? null;
        if ($destination === null || !is_int($pageIndex)) {
            return [];
        }

        $titles = [];
        $levels = [];
        foreach ($outlineRows as $outline) {
            if (!is_array($outline)) {
                continue;
            }
            if (($outline['page'] ?? null) !== $pageIndex || ($outline['destination'] ?? null) !== $destination) {
                continue;
            }

            $title = $outline['title'] ?? null;
            if (is_string($title) && $title !== '' && !in_array($title, $titles, true)) {
                $titles[] = $title;
            }

            $level = $outline['level'] ?? null;
            if (is_int($level) && !in_array($level, $levels, true)) {
                $levels[] = $level;
            }
        }

        if ($titles === []) {
            return [];
        }

        $summary = ['target_outline_titles' => $titles];
        if ($levels !== []) {
            $summary['target_outline_levels'] = $levels;
        }

        return $summary;
    }

    /**
     * Reuses the general annotation extractor's singular /StructParent
     * ParentTree review so promoted Link/Widget link rows keep tagged-PDF
     * context without making structure text visible content.
     *
     * @return array<int, array<string, mixed>>
     */
    private function annotationStructureReviewsByObject(string $pdfBytes): array
    {
        $reviews = [];
        foreach ((new PdfAnnotationExtractor())->extractPageAnnotations($pdfBytes) as $page) {
            foreach (($page['annotations'] ?? []) as $annotation) {
                if (!is_array($annotation)) {
                    continue;
                }

                $object = $annotation['annotation_object'] ?? null;
                $structureParent = $annotation['structure_parent'] ?? null;
                if (!is_int($object) || !is_array($structureParent)) {
                    continue;
                }

                $review = ['structure_parent' => $structureParent];
                foreach ([
                    'struct_parent',
                    'struct_parent_source',
                    'struct_parent_field_object',
                    'struct_parent_field_chain',
                ] as $key) {
                    if (array_key_exists($key, $annotation)) {
                        $review[$key] = $annotation[$key];
                    }
                }

                $reviews[$object] = $review;
            }
        }

        return $reviews;
    }

    private function annotationSubtype(string $annotationBody): ?string
    {
        $value = $this->valueAfterName($annotationBody, 'Subtype');
        if ($value === null) {
            return null;
        }

        $trimmed = trim($value);
        if ($trimmed === '' || $trimmed[0] !== '/') {
            return null;
        }

        $end = $this->skipPdfName($trimmed, 0);
        return $this->decodePdfName(substr($trimmed, 1, $end - 1));
    }

    /**
     * @param array<int, string> $objects
     */
    private function annotationHiddenFromLinkImport(string $annotationBody, array $objects): bool
    {
        $flags = $this->integerAfterName($annotationBody, 'F', $objects) ?? 0;

        return ($flags & 1) !== 0
            || ($flags & 2) !== 0
            || ($flags & 32) !== 0;
    }

    /**
     * @param list<array<string, mixed>> $actions
     * @return array<string, mixed>|null
     */
    private function primaryLinkAction(array $actions): ?array
    {
        foreach ($actions as $action) {
            if (($action['chained'] ?? false) === true) {
                continue;
            }

            if (
                ($action['safety'] ?? null) === 'review-uri'
                || ($action['safety'] ?? null) === 'local-destination'
                || ($action['safety'] ?? null) === 'remote-document-review'
            ) {
                return $action;
            }
        }

        return null;
    }

    /**
     * @param array<int, string> $objects
     * @return list<float>|null
     */
    private function rectFromAnnotation(string $annotationBody, array $objects = []): ?array
    {
        $value = $this->valueAfterName($annotationBody, 'Rect');
        if ($value === null) {
            return null;
        }

        $value = $this->resolveIndirectObjectValue($value, $objects);
        if (!str_starts_with(trim($value), '[')) {
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
     * Link annotations may use /QuadPoints to constrain the clickable text
     * area inside a larger /Rect. The native importer uses those quads for
     * span matching while retaining /Rect for page-level review metadata.
     *
     * @param array<int, string> $objects
     * @return list<list<float>>
     */
    private function quadPointsFromAnnotation(string $annotationBody, array $objects = []): array
    {
        $value = $this->valueAfterName($annotationBody, 'QuadPoints');
        if ($value === null) {
            return [];
        }

        $value = $this->resolveIndirectObjectValue($value, $objects);
        if (!str_starts_with(trim($value), '[')) {
            return [];
        }

        $arrayBody = $this->arrayBodyFromValue($value);
        if ($arrayBody === null) {
            return [];
        }

        $numbers = $this->numbersFromPdfArray($arrayBody);
        $quads = [];
        for ($offset = 0, $count = count($numbers); $offset + 7 < $count; $offset += 8) {
            $quads[] = array_slice($numbers, $offset, 8);
        }

        return $quads;
    }

    /**
     * @param array<int, string> $objects
     * @return array<string, mixed>
     */
    private function presentationReviewFromAnnotation(string $annotationBody, array $objects): array
    {
        $review = [];

        $contents = $this->stringValueAfterName($annotationBody, 'Contents', $objects);
        if ($contents !== null) {
            $review['contents'] = $contents;
        }

        $title = $this->stringValueAfterName($annotationBody, 'T', $objects);
        if ($title !== null) {
            $review['title'] = $title;
        }

        $color = $this->colorValueAfterName($annotationBody, 'C', $objects);
        if ($color !== null) {
            $review['border_color'] = $color;
        }

        $highlightMode = $this->highlightModeFromAnnotation($annotationBody);
        if ($highlightMode !== null) {
            $review['highlight_mode'] = $highlightMode;
            $review['highlight_mode_label'] = self::HIGHLIGHT_MODE_LABELS[$highlightMode] ?? 'unknown';
        }

        $border = $this->borderFromAnnotation($annotationBody, $objects);
        if ($border !== null) {
            $review['border'] = $border;
        }

        return $review;
    }

    /**
     * @param array<int, string> $objects
     * @return array{space: string, components: list<float>, hex: string|null}|null
     */
    private function colorValueAfterName(string $body, string $name, array $objects): ?array
    {
        $arrayBody = $this->arrayBodyValueAfterName($body, $name, $objects);
        if ($arrayBody === null) {
            return null;
        }

        $components = array_map(fn (float $component): float => $this->clamp($component), $this->numbersFromPdfArray($arrayBody));
        $count = count($components);
        if ($count === 0) {
            return [
                'space' => 'transparent',
                'components' => [],
                'hex' => null,
            ];
        }

        if ($count === 1) {
            return [
                'space' => 'DeviceGray',
                'components' => $components,
                'hex' => $this->rgbHex([$components[0], $components[0], $components[0]]),
            ];
        }

        if ($count === 3) {
            return [
                'space' => 'DeviceRGB',
                'components' => $components,
                'hex' => $this->rgbHex($components),
            ];
        }

        if ($count === 4) {
            [$cyan, $magenta, $yellow, $black] = $components;

            return [
                'space' => 'DeviceCMYK',
                'components' => $components,
                'hex' => $this->rgbHex([
                    (1.0 - $cyan) * (1.0 - $black),
                    (1.0 - $magenta) * (1.0 - $black),
                    (1.0 - $yellow) * (1.0 - $black),
                ]),
            ];
        }

        return [
            'space' => 'DeviceN',
            'components' => $components,
            'hex' => null,
        ];
    }

    private function highlightModeFromAnnotation(string $annotationBody): ?string
    {
        $value = $this->valueAfterName($annotationBody, 'H');
        if ($value === null) {
            return null;
        }

        $trimmed = trim($value);
        if ($trimmed === '' || $trimmed[0] !== '/') {
            return null;
        }

        $end = $this->skipPdfName($trimmed, 0);
        return $this->decodePdfName(substr($trimmed, 1, $end - 1));
    }

    /**
     * @param array<int, string> $objects
     * @return array<string, mixed>|null
     */
    private function borderFromAnnotation(string $annotationBody, array $objects): ?array
    {
        $bs = $this->valueAfterName($annotationBody, 'BS');
        if ($bs !== null) {
            $dictionary = $this->dictionaryBodyFromValue($bs, $objects);
            if ($dictionary !== null) {
                $width = $this->floatValueAfterName($dictionary, 'W', $objects) ?? 1.0;
                $styleCode = $this->nameValueAfterName($dictionary, 'S') ?? 'S';
                $dashPattern = $this->arrayNumbersAfterName($dictionary, 'D', $objects) ?? [];

                return [
                    'source' => 'BS',
                    'width' => $width,
                    'style' => $width <= 0.0 ? 'none' : (self::BORDER_STYLE_NAMES[$styleCode] ?? strtolower($styleCode)),
                    'style_code' => $styleCode,
                    'dash_pattern' => $dashPattern,
                    'horizontal_corner_radius' => null,
                    'vertical_corner_radius' => null,
                ];
            }
        }

        $arrayBody = $this->arrayBodyValueAfterName($annotationBody, 'Border', $objects);
        if ($arrayBody === null) {
            return null;
        }

        $numbers = $this->numbersFromPdfArray($arrayBody);
        if (count($numbers) < 3) {
            return null;
        }

        $dashPattern = $this->dashPatternFromBorderArrayBody($arrayBody);
        $width = (float) $numbers[2];

        return [
            'source' => 'Border',
            'width' => $width,
            'style' => $width <= 0.0 ? 'none' : ($dashPattern === [] ? 'solid' : 'dashed'),
            'style_code' => $dashPattern === [] ? 'S' : 'D',
            'dash_pattern' => $dashPattern,
            'horizontal_corner_radius' => (float) $numbers[0],
            'vertical_corner_radius' => (float) $numbers[1],
        ];
    }

    /**
     * @return list<float>
     */
    private function dashPatternFromBorderArrayBody(string $arrayBody): array
    {
        $offset = 0;
        while (($start = strpos($arrayBody, '[', $offset)) !== false) {
            $endOffset = null;
            $body = $this->readPdfArrayAt($arrayBody, $start, $endOffset);
            if ($body === null || $endOffset === null) {
                break;
            }

            return $this->numbersFromPdfArray($body);
        }

        return [];
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
     * @param list<float> $a
     * @param list<float> $b
     * @return list<float>
     */
    private function intersectRects(array $a, array $b): array
    {
        $left = max($a[0], $b[0]);
        $bottom = max($a[1], $b[1]);
        $right = min($a[2], $b[2]);
        $top = min($a[3], $b[3]);

        return [
            $left,
            $bottom,
            max($left, $right),
            max($bottom, $top),
        ];
    }

    /**
     * @param list<float> $rect
     */
    private function rectHasArea(array $rect): bool
    {
        return $this->rectWidth($rect) > 0.000001 && $this->rectHeight($rect) > 0.000001;
    }

    /**
     * @param list<float> $a
     * @param list<float> $b
     */
    private function rectsApproximatelyEqual(array $a, array $b): bool
    {
        for ($index = 0; $index < 4; $index++) {
            if (abs($a[$index] - $b[$index]) > 0.000001) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param array<string, mixed> $link
     * @param array<string, mixed> $page
     * @return list<array{rect: list<float>, coordinate_space: string, quad_index?: int, visible_quad_position?: int}>
     */
    private function linkRectCandidatesForPage(array $link, array $page): array
    {
        $usesPdftextGeometry = $this->pageLooksLikePdftextGeometry($link, $page);
        if (array_key_exists('quad_points', $link)) {
            $quadRects = $usesPdftextGeometry
                ? ($link['pdftext_visible_quad_rects'] ?? [])
                : ($link['visible_quad_rects'] ?? []);
            if (!is_array($quadRects) || $quadRects === []) {
                return [];
            }

            $sourceIndexes = is_array($link['visible_quad_source_indexes'] ?? null)
                ? array_values($link['visible_quad_source_indexes'])
                : [];
            $candidates = [];
            foreach (array_values($quadRects) as $quadPosition => $quadRect) {
                $rect = $this->bbox($quadRect);
                if ($rect === null) {
                    continue;
                }

                $candidates[] = [
                    'rect' => $rect,
                    'coordinate_space' => $usesPdftextGeometry ? 'marker_pdftext_display' : 'pdf_page_user_space',
                    'quad_index' => is_int($sourceIndexes[$quadPosition] ?? null) ? $sourceIndexes[$quadPosition] : (int) $quadPosition,
                    'visible_quad_position' => (int) $quadPosition,
                ];
            }

            return $candidates;
        }

        if ($usesPdftextGeometry) {
            $rect = $this->bbox($link['pdftext_visible_rect'] ?? $link['pdftext_rect'] ?? null);
            return $rect === null ? [] : [[
                'rect' => $rect,
                'coordinate_space' => 'marker_pdftext_display',
            ]];
        }

        $rect = $this->bbox($link['visible_rect'] ?? $link['rect'] ?? null);
        return $rect === null ? [] : [[
            'rect' => $rect,
            'coordinate_space' => 'pdf_page_user_space',
        ]];
    }

    /**
     * @param array<string, mixed> $link
     * @param array<string, mixed> $page
     */
    private function pageLooksLikePdftextGeometry(array $link, array $page): bool
    {
        if (!array_key_exists('bbox', $page) || !array_key_exists('rotation', $page)) {
            return false;
        }
        if (!is_int($page['rotation']) && !is_float($page['rotation'])) {
            return false;
        }

        $pageBbox = $this->bbox($page['bbox']);
        $displayBbox = $this->bbox($link['display_page_bbox'] ?? null);
        if ($pageBbox === null || $displayBbox === null) {
            return false;
        }

        $pageRotation = $this->normalizedRotation((int) round((float) $page['rotation']));
        $linkRotation = $this->normalizedRotation((int) ($link['page_rotation'] ?? 0));
        if ($pageRotation !== $linkRotation) {
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

        $objectBody = $this->objectBodyForReferenceValue($value, $objects);
        if ($objectBody === null) {
            return null;
        }

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

        $objectBody = $this->objectBodyForReferenceValue($value, $objects);
        if ($objectBody === null) {
            return null;
        }

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
     * @param array<int, string> $objects
     */
    private function actionDictionaryBody(string $annotationBody, array $objects): ?string
    {
        $value = $this->valueAfterName($annotationBody, 'A');
        if ($value === null) {
            return null;
        }

        $value = trim($value);
        if (str_starts_with($value, '<<')) {
            return $this->readPdfDictionaryAt($value, 0);
        }

        if (preg_match('/^(\d+)\s+\d+\s+R\b/', $value, $match) === 1) {
            $objectNumber = (int) $match[1];
            return isset($objects[$objectNumber]) ? $this->dictionaryObjectBody($objects[$objectNumber]) : null;
        }

        return null;
    }

    private function valueAfterName(string $body, string $name): ?string
    {
        $dictionary = str_starts_with(ltrim($body), '<<') ? $this->dictionaryObjectBody($body) : null;
        $value = $dictionary === null
            ? $this->dictionaryValueAfterName($body, $name)
            : $this->dictionaryValueAfterName($dictionary, $name);
        if ($value !== null) {
            return $value;
        }

        if (preg_match('/\/' . preg_quote($name, '/') . '\b/s', $body, $match, PREG_OFFSET_CAPTURE) !== 1) {
            return null;
        }

        $offset = $match[0][1] + strlen($match[0][0]);
        return $this->valueStartingAtOffsetWithEnd($body, $offset);
    }

    private function dictionaryValueAfterName(string $dictionary, string $name): ?string
    {
        $offset = 0;
        $length = strlen($dictionary);
        while ($offset < $length) {
            $this->skipWhitespaceAndComments($dictionary, $offset);
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

    private function skipWhitespaceAndComments(string $value, int &$offset): void
    {
        $length = strlen($value);
        while ($offset < $length) {
            while ($offset < $length && ctype_space($value[$offset])) {
                $offset++;
            }

            if (($value[$offset] ?? '') !== '%') {
                return;
            }

            while ($offset < $length && $value[$offset] !== "\n" && $value[$offset] !== "\r") {
                $offset++;
            }
        }
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

            $offset = $valueOffset;
        }

        return null;
    }

    /**
     * @param array<int, string> $objects
     */
    private function stringValueAfterName(string $body, string $name, array $objects): ?string
    {
        $value = $this->valueAfterName($body, $name);
        if ($value === null) {
            return null;
        }

        $value = trim($this->resolveIndirectObjectValue($value, $objects));
        if ($value === '') {
            return null;
        }

        if ($value[0] === '(') {
            $endOffset = $this->skipLiteralString($value, 0);
            return $this->decodePdfStringBytes($this->decodeLiteralString(substr($value, 1, $endOffset - 2)));
        }

        if ($value[0] === '<' && substr($value, 0, 2) !== '<<') {
            $endOffset = $this->skipHexString($value, 0);
            $hex = preg_replace('/\s+/', '', substr($value, 1, $endOffset - 2)) ?? '';
            if ($hex === '') {
                return null;
            }
            if (strlen($hex) % 2 === 1) {
                $hex .= '0';
            }

            $bytes = hex2bin($hex);
            return $bytes === false ? null : $this->decodePdfStringBytes($bytes);
        }

        if ($value[0] === '/') {
            $endOffset = $this->skipPdfName($value, 0);
            return $this->decodePdfName(substr($value, 1, $endOffset - 1));
        }

        return null;
    }

    /**
     * @param array<int, string> $objects
     */
    private function arrayBodyValueAfterName(string $body, string $name, array $objects): ?string
    {
        $value = $this->valueAfterName($body, $name);
        if ($value === null) {
            return null;
        }

        $value = trim($this->resolveIndirectObjectValue($value, $objects));
        if (!str_starts_with($value, '[')) {
            return null;
        }

        return $this->arrayBodyFromValue($value);
    }

    /**
     * @param array<int, string> $objects
     */
    private function dictionaryBodyFromValue(string $value, array $objects): ?string
    {
        $value = trim($this->resolveIndirectObjectValue($value, $objects));
        if (str_starts_with($value, '<<')) {
            return $this->readPdfDictionaryAt($value, 0);
        }

        return $this->dictionaryObjectBody($value);
    }

    /**
     * @param array<int, string> $objects
     */
    private function floatValueAfterName(string $body, string $name, array $objects): ?float
    {
        return $this->numberValueAfterName($body, $name, $objects);
    }

    private function nameValueAfterName(string $body, string $name): ?string
    {
        $value = $this->valueAfterName($body, $name);
        if ($value === null) {
            return null;
        }

        $trimmed = trim($value);
        if ($trimmed === '' || $trimmed[0] !== '/') {
            return null;
        }

        $endOffset = $this->skipPdfName($trimmed, 0);
        return $this->decodePdfName(substr($trimmed, 1, $endOffset - 1));
    }

    /**
     * @param array<int, string> $objects
     * @return list<float>|null
     */
    private function arrayNumbersAfterName(string $body, string $name, array $objects): ?array
    {
        $arrayBody = $this->arrayBodyValueAfterName($body, $name, $objects);
        return $arrayBody === null ? null : $this->numbersFromPdfArray($arrayBody);
    }

    /**
     * @param array<int, string> $objects
     */
    private function integerAfterName(string $body, string $name, array $objects = []): ?int
    {
        $value = $this->valueAfterName($body, $name);
        if ($value === null) {
            return null;
        }

        $value = $this->resolveIndirectObjectValue($value, $objects);
        if (preg_match('/^[+-]?\d+/', trim($value), $match) !== 1) {
            return null;
        }

        return (int) $match[0];
    }

    /**
     * @param array<int, string> $objects
     */
    private function resolveIndirectObjectValue(string $value, array $objects): string
    {
        $trimmed = trim($value);
        $reference = $this->objectReferenceFromValue($trimmed);
        if ($reference === null) {
            return $value;
        }

        $body = $this->objectBodyForReference($reference['object'], $reference['generation'], $objects);
        if ($body === null) {
            return $value;
        }

        return $body;
    }

    private function isSafeUri(string $uri): bool
    {
        $trimmed = trim($uri);
        if ($trimmed === '') {
            return false;
        }

        if (preg_match('/[\x00-\x20\x7F]/', $uri) === 1) {
            return false;
        }

        if (preg_match('/^[a-z][a-z0-9+.-]*:/i', $trimmed, $match) === 1) {
            return in_array(strtolower(rtrim($match[0], ':')), ['http', 'https', 'mailto', 'ftp'], true);
        }

        return str_starts_with($trimmed, '#') || str_starts_with($trimmed, '/') || str_starts_with($trimmed, './') || str_starts_with($trimmed, '../');
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
     * @return array<int, array<int, string>>
     */
    private function pdfObjectBodiesByGeneration(string $pdfBytes): array
    {
        $objects = [];
        if (!preg_match_all('/(\d+)\s+(\d+)\s+obj\b(.*?)\bendobj/s', $pdfBytes, $matches, PREG_SET_ORDER)) {
            return $objects;
        }

        foreach ($matches as $match) {
            $objects[(int) $match[1]][(int) $match[2]] = trim($match[3]);
        }

        return $objects;
    }

    /**
     * @return array{object: int, generation: int}|null
     */
    private function objectReferenceFromValue(string $value): ?array
    {
        if (preg_match('/^(\d+)\s+(\d+)\s+R\b/', trim($value), $match) !== 1) {
            return null;
        }

        return [
            'object' => (int) $match[1],
            'generation' => (int) $match[2],
        ];
    }

    /**
     * @param array<int, string> $objects
     */
    private function objectBodyForReferenceValue(string $value, array $objects): ?string
    {
        $reference = $this->objectReferenceFromValue($value);
        return $reference === null
            ? null
            : $this->objectBodyForReference($reference['object'], $reference['generation'], $objects);
    }

    /**
     * @param array<int, string> $objects
     */
    private function objectBodyForReference(int $objectNumber, int $generation, array $objects): ?string
    {
        if (array_key_exists($generation, $this->objectBodiesByGeneration[$objectNumber] ?? [])) {
            return $this->objectBodiesByGeneration[$objectNumber][$generation];
        }

        if ($generation === 0 && isset($objects[$objectNumber])) {
            return trim($objects[$objectNumber]);
        }

        return null;
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
     * @return list<int>
     */
    private function objectReferences(string $value): array
    {
        if (!preg_match_all('/(\d+)\s+\d+\s+R\b/', $value, $matches)) {
            return [];
        }

        return array_map('intval', $matches[1]);
    }

    private function indirectObjectNumberFromValue(string $value): ?int
    {
        return preg_match('/^(\d+)\s+\d+\s+R\b/', trim($value), $match) === 1 ? (int) $match[1] : null;
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
            if ($char === '%') {
                while ($index < $length && $value[$index] !== "\n" && $value[$index] !== "\r") {
                    $index++;
                }
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

    /**
     * @param list<float> $components
     */
    private function rgbHex(array $components): string
    {
        $parts = [];
        foreach (array_slice($components, 0, 3) as $component) {
            $parts[] = str_pad(dechex((int) round($this->clamp((float) $component) * 255)), 2, '0', STR_PAD_LEFT);
        }

        return '#' . implode('', $parts);
    }

    private function clamp(float $value): float
    {
        return max(0.0, min(1.0, $value));
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
