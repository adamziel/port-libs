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
    private const ANNOTATION_FLAGS = [
        1 => 'invisible',
        2 => 'hidden',
        3 => 'print',
        4 => 'no_zoom',
        5 => 'no_rotate',
        6 => 'no_view',
        7 => 'read_only',
        8 => 'locked',
        9 => 'toggle_no_view',
        10 => 'locked_contents',
    ];

    /** @var array<int, array<int, string>> */
    private array $objectBodiesByGeneration = [];

    /** @var array<int, true> */
    private array $xrefFreeObjectNumbers = [];

    /** @var array<int, true> */
    private array $xrefSelectedGenerationZeroObjects = [];

    /** @var array<int, true> */
    private array $xrefStreamSuppressedObjectNumbers = [];

    /**
     * Native boundary for PDF page /Annots link actions.
     *
     * @return list<array{pnum: int, page_object: int, links: list<array<string, mixed>>}>
     */
    public function extractPageLinks(string $pdfBytes): array
    {
        $objects = $this->pdfObjects($pdfBytes);
        $this->objectBodiesByGeneration = $this->pdfObjectBodiesByGeneration($pdfBytes);
        $this->xrefFreeObjectNumbers = PdfXrefFreeObjectMap::freeObjectNumbers($pdfBytes);
        foreach (array_keys($this->xrefFreeObjectNumbers) as $objectNumber) {
            unset($objects[$objectNumber], $this->objectBodiesByGeneration[$objectNumber]);
        }

        $actionReviewer = new PdfActionReviewExtractor($pdfBytes);
        $structureReviewsByAnnotationObject = $this->annotationStructureReviewsByObject($pdfBytes);
        $context = $this->linkReviewContext($pdfBytes);
        $pageObjectReferences = $this->orderedPageObjectReferences($objects);
        $pages = [];

        foreach ($pageObjectReferences as $pnum => $pageReference) {
            $pageObjectNumber = $pageReference['object'];
            $pageGeneration = $pageReference['generation'];
            $pageBody = $this->objectBodyForReference($pageObjectNumber, $pageGeneration, $objects)
                ?? ($objects[$pageObjectNumber] ?? null);
            if ($pageBody === null) {
                continue;
            }

            $links = $this->linksFromPageObject(
                $pageObjectNumber,
                $pageGeneration,
                $pageBody,
                $objects,
                $actionReviewer,
                $structureReviewsByAnnotationObject,
                $context,
                $this->pageGeometry($pageObjectNumber, $objects, $pageGeneration)
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

                        $matchingPage = $page;
                        $matchingPage['pnum'] = $pnum;
                        $candidate = $this->linkForSpan($span, $links, $matchingPage);
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
                        if (array_key_exists('annotation_generation', $link)) {
                            $page['blocks'][$blockIndex]['lines'][$lineIndex]['spans'][$spanIndex]['link_annotation_generation'] = $link['annotation_generation'];
                        }
                        $page['blocks'][$blockIndex]['lines'][$lineIndex]['spans'][$spanIndex]['link_annotation_subtype'] = $link['annotation_subtype'];
                        $page['blocks'][$blockIndex]['lines'][$lineIndex]['spans'][$spanIndex]['link_annotation_flags'] = $link['annotation_flags'];
                        $page['blocks'][$blockIndex]['lines'][$lineIndex]['spans'][$spanIndex]['link_annotation_flag_names'] = $link['annotation_flag_names'];
                        $page['blocks'][$blockIndex]['lines'][$lineIndex]['spans'][$spanIndex]['link_annotation_visibility'] = $link['annotation_visibility'];
                        $page['blocks'][$blockIndex]['lines'][$lineIndex]['spans'][$spanIndex]['link_widget_annotation'] = $link['widget_annotation'];
                        $page['blocks'][$blockIndex]['lines'][$lineIndex]['spans'][$spanIndex]['link_action_type'] = $link['action_type'];
                        $page['blocks'][$blockIndex]['lines'][$lineIndex]['spans'][$spanIndex]['link_safety'] = $link['safety'];
                        $page['blocks'][$blockIndex]['lines'][$lineIndex]['spans'][$spanIndex]['link_executes_on_import'] = false;
                        $page['blocks'][$blockIndex]['lines'][$lineIndex]['spans'][$spanIndex]['link_actions_review'] = $link['actions'];
                        $page['blocks'][$blockIndex]['lines'][$lineIndex]['spans'][$spanIndex]['link_additional_actions_review'] = $link['additional_actions'];
                        if (array_key_exists('previous_uri_actions', $link)) {
                            $page['blocks'][$blockIndex]['lines'][$lineIndex]['spans'][$spanIndex]['link_previous_uri_actions'] = $link['previous_uri_actions'];
                        }
                        if (array_key_exists('struct_parent', $link)) {
                            $page['blocks'][$blockIndex]['lines'][$lineIndex]['spans'][$spanIndex]['link_struct_parent'] = $link['struct_parent'];
                        }
                        if (is_array($link['structure_parent'] ?? null)) {
                            $page['blocks'][$blockIndex]['lines'][$lineIndex]['spans'][$spanIndex]['link_structure_parent'] = $link['structure_parent'];
                        }
                        foreach ([
                            'inherited_widget_link_keys' => 'link_inherited_widget_keys',
                            'widget_field_parent_object' => 'link_widget_field_parent_object',
                            'widget_field_parent_generation' => 'link_widget_field_parent_generation',
                            'widget_field_chain' => 'link_widget_field_chain',
                            'widget_field_chain_generations' => 'link_widget_field_chain_generations',
                            'widget_link_action_source' => 'link_widget_action_source',
                            'widget_link_field_sources' => 'link_widget_field_sources',
                            'widget_link_field_source_generations' => 'link_widget_field_source_generations',
                        ] as $sourceKey => $spanKey) {
                            if (array_key_exists($sourceKey, $link)) {
                                $page['blocks'][$blockIndex]['lines'][$lineIndex]['spans'][$spanIndex][$spanKey] = $link[$sourceKey];
                            }
                        }

                        if (is_string($link['uri'] ?? null) && ($link['is_safe_uri'] ?? false) === true) {
                            $page['blocks'][$blockIndex]['lines'][$lineIndex]['spans'][$spanIndex]['link_uri'] = $link['uri'];
                        }
                        foreach ([
                            'raw_uri' => 'link_raw_uri',
                            'uri_base' => 'link_uri_base',
                        ] as $sourceKey => $spanKey) {
                            if (is_string($link[$sourceKey] ?? null) && $link[$sourceKey] !== '') {
                                $page['blocks'][$blockIndex]['lines'][$lineIndex]['spans'][$spanIndex][$spanKey] = $link[$sourceKey];
                            }
                        }
                        foreach ([
                            'uri_relative' => 'link_uri_relative',
                            'uri_resolved_from_base' => 'link_uri_resolved_from_base',
                        ] as $sourceKey => $spanKey) {
                            if (array_key_exists($sourceKey, $link)) {
                                $page['blocks'][$blockIndex]['lines'][$lineIndex]['spans'][$spanIndex][$spanKey] = (bool) $link[$sourceKey];
                            }
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

        $spanArea = $this->rectArea($bbox);
        if ($spanArea <= 0.0) {
            return null;
        }

        $best = null;
        $bestScore = null;
        foreach (array_values($links) as $linkOrder => $link) {
            foreach ($this->linkRectCandidatesForPage($link, $page) as $candidateOrder => $candidate) {
                if (!$this->bboxesIntersect($bbox, $candidate['rect'])) {
                    continue;
                }

                $intersection = $this->intersectRects($bbox, $candidate['rect']);
                if (!$this->rectHasArea($intersection)) {
                    continue;
                }

                $score = [
                    'span_coverage' => $this->rectArea($intersection) / $spanArea,
                    'candidate_area' => $this->rectArea($candidate['rect']),
                    'intersection_area' => $this->rectArea($intersection),
                    'link_order' => $linkOrder,
                    'candidate_order' => $candidateOrder,
                ];
                if ($bestScore !== null && !$this->linkCandidateBeats($score, $bestScore)) {
                    continue;
                }

                $bestScore = $score;
                $best = [
                    'link' => $link,
                    'rect' => $candidate['rect'],
                    'coordinate_space' => $candidate['coordinate_space'],
                ]
                    + (array_key_exists('quad_index', $candidate) ? ['quad_index' => $candidate['quad_index']] : [])
                    + (array_key_exists('visible_quad_position', $candidate) ? ['visible_quad_position' => $candidate['visible_quad_position']] : []);
            }
        }

        return $best;
    }

    /**
     * @param array{span_coverage: float, candidate_area: float, intersection_area: float, link_order: int, candidate_order: int} $candidate
     * @param array{span_coverage: float, candidate_area: float, intersection_area: float, link_order: int, candidate_order: int} $incumbent
     */
    private function linkCandidateBeats(array $candidate, array $incumbent): bool
    {
        $epsilon = 0.000001;

        if (abs($candidate['span_coverage'] - $incumbent['span_coverage']) > $epsilon) {
            return $candidate['span_coverage'] > $incumbent['span_coverage'];
        }

        if (abs($candidate['candidate_area'] - $incumbent['candidate_area']) > $epsilon) {
            return $candidate['candidate_area'] < $incumbent['candidate_area'];
        }

        if (abs($candidate['intersection_area'] - $incumbent['intersection_area']) > $epsilon) {
            return $candidate['intersection_area'] > $incumbent['intersection_area'];
        }

        if ($candidate['link_order'] !== $incumbent['link_order']) {
            return $candidate['link_order'] < $incumbent['link_order'];
        }

        return $candidate['candidate_order'] < $incumbent['candidate_order'];
    }

    /**
     * @param array<int, string> $objects
     * @param array<int|string, array<string, mixed>> $structureReviewsByAnnotationObject
     * @param array<string, mixed> $context
     * @param array{bbox: list<float>, rotation: int, user_unit: float, display_bbox: list<float>} $pageGeometry
     * @return list<array<string, mixed>>
     */
    private function linksFromPageObject(
        int $pageObjectNumber,
        ?int $pageGeneration,
        string $pageBody,
        array $objects,
        PdfActionReviewExtractor $actionReviewer,
        array $structureReviewsByAnnotationObject,
        array $context,
        array $pageGeometry
    ): array {
        $annotationBodies = $this->annotationBodiesForPage($pageObjectNumber, $pageGeneration, $pageBody, $objects);
        $links = [];

        foreach ($annotationBodies as $annotation) {
            $link = $this->linkFromAnnotationBody(
                $annotation['body'],
                $objects,
                $actionReviewer,
                $annotation['object'],
                $annotation['generation'] ?? null,
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
     * @return list<array{body: string, object: int|null, generation?: int|null}>
     */
    private function annotationBodiesForPage(int $pageObjectNumber, ?int $pageGeneration, string $pageBody, array $objects): array
    {
        $annots = $this->pageDictionaryValueAfterName($pageBody, 'Annots');
        if ($annots === null) {
            return [];
        }

        return $this->annotationBodiesFromValue($annots, $objects, $pageObjectNumber, $pageGeneration);
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
     * @return list<array{body: string, object: int|null, generation?: int|null}>
     */
    private function annotationBodiesFromValue(string $value, array $objects, int $pageObjectNumber, ?int $pageGeneration): array
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
                return $this->annotationBodiesFromArray($this->arrayBodyFromValue($objectBody), $objects, $pageObjectNumber, $pageGeneration);
            }

            $dictionary = $this->dictionaryObjectBody($objectBody);
            return $dictionary === null || !$this->annotationBelongsToPage($dictionary, $pageObjectNumber, $pageGeneration) ? [] : [[
                'body' => $dictionary,
                'object' => $objectNumber,
                'generation' => $reference['generation'],
            ]];
        }

        if (str_starts_with($value, '[')) {
            return $this->annotationBodiesFromArray($this->arrayBodyFromValue($value), $objects, $pageObjectNumber, $pageGeneration);
        }

        if (str_starts_with($value, '<<')) {
            $dictionary = $this->readPdfDictionaryAt($value, 0);
            return $dictionary === null || !$this->annotationBelongsToPage($dictionary, $pageObjectNumber, $pageGeneration)
                ? []
                : [['body' => $dictionary, 'object' => null, 'generation' => null]];
        }

        return [];
    }

    /**
     * @param array<int, string> $objects
     * @return list<array{body: string, object: int|null, generation?: int|null}>
     */
    private function annotationBodiesFromArray(?string $arrayBody, array $objects, int $pageObjectNumber, ?int $pageGeneration): array
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
                if ($dictionary !== null && $this->annotationBelongsToPage($dictionary, $pageObjectNumber, $pageGeneration)) {
                    $annotations[] = ['body' => $dictionary, 'object' => null, 'generation' => null];
                }
                $offset = $endOffset;
                continue;
            }

            if (preg_match('/^(\d+)\s+(\d+)\s+R\b/s', $value, $match) === 1) {
                $objectNumber = (int) $match[1];
                $objectBody = $this->objectBodyForReference($objectNumber, (int) $match[2], $objects);
                $dictionary = $objectBody === null ? null : $this->dictionaryObjectBody($objectBody);
                if ($dictionary !== null && $this->annotationBelongsToPage($dictionary, $pageObjectNumber, $pageGeneration)) {
                    $annotations[] = [
                        'body' => $dictionary,
                        'object' => $objectNumber,
                        'generation' => (int) $match[2],
                    ];
                }
                $offset = $endOffset;
                continue;
            }

            $offset = $endOffset;
        }

        return $annotations;
    }

    private function annotationBelongsToPage(string $annotationBody, int $pageObjectNumber, ?int $pageGeneration): bool
    {
        $pageReference = $this->referenceValueAfterName($annotationBody, 'P');

        if ($pageReference === null) {
            return true;
        }

        if ($pageReference['object'] !== $pageObjectNumber) {
            return false;
        }

        return $pageGeneration === null || $pageReference['generation'] === $pageGeneration;
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
        ?int $annotationGeneration,
        array $structureReviewsByAnnotationObject,
        array $context,
        array $pageGeometry
    ): ?array
    {
        $subtype = $this->annotationSubtype($annotationBody, $objects);
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
        if (!$this->rectHasArea($visibleRect) && $quadPoints === []) {
            return null;
        }

        $review = $actionReviewer->reviewAnnotationActions($effectiveAnnotation['body']);
        $review['actions'] = $this->withLinkTargetContextRows($review['actions'], $context);
        $review['additional_actions'] = $this->withLinkTargetContextRows($review['additional_actions'], $context);
        $review['previous_uri_actions'] = $this->withLinkTargetContextRows($review['previous_uri_actions'] ?? [], $context);
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
            'annotation_generation' => $annotationGeneration,
            'annotation_subtype' => $subtype,
            'widget_annotation' => $subtype === 'Widget',
            'actions' => $review['actions'],
            'additional_actions' => $review['additional_actions'],
            'executes_on_import' => false,
        ]
            + $this->annotationFlagReviewFromAnnotation($annotationBody, $objects)
            + $this->presentationReviewFromAnnotation($annotationBody, $objects);
        if ($review['previous_uri_actions'] !== []) {
            $link['previous_uri_actions'] = $review['previous_uri_actions'];
        }
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
            $link['widget_field_parent_generation'] = $effectiveAnnotation['field_chain_generations'][0] ?? null;
            $link['widget_field_chain'] = $fieldChain;
            $link['widget_field_chain_generations'] = $effectiveAnnotation['field_chain_generations'];
            $link['widget_link_action_source'] = $primaryFromField ? 'field_parent' : 'annotation';
            $link['widget_link_field_sources'] = $effectiveAnnotation['field_sources'];
            $link['widget_link_field_source_generations'] = $effectiveAnnotation['field_source_generations'];
        }

        $structureReview = null;
        if ($annotationObject !== null) {
            if ($annotationGeneration !== null) {
                $structureReview = $structureReviewsByAnnotationObject[
                    $this->annotationReferenceKey($annotationObject, $annotationGeneration)
                ] ?? null;
            }

            if ($structureReview === null && isset($structureReviewsByAnnotationObject[$annotationObject])) {
                $candidate = $structureReviewsByAnnotationObject[$annotationObject];
                $hasExactReferences = is_array($candidate['structure_parent']['annotation_references'] ?? null)
                    && $candidate['structure_parent']['annotation_references'] !== [];
                if ($annotationGeneration === null || !$hasExactReferences) {
                    $structureReview = $candidate;
                }
            }
        }

        if ($annotationObject !== null && $structureReview !== null) {
            $link += $structureReview;
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
                if (isset($link['previous_uri_actions'])) {
                    $link['previous_uri_actions'] = PdfActionReviewExtractor::actionsWithAnnotationStructureParentContext(
                        $link['previous_uri_actions'],
                        $annotationObject,
                        $link['struct_parent'],
                        $link['structure_parent']
                    );
                }
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
     * @return array{body: string, inherited_keys: list<string>, field_chain: list<int>, field_chain_generations: list<int>, field_sources: array<string, int>, field_source_generations: array<string, int>}
     */
    private function effectiveWidgetLinkAnnotationBody(string $annotationBody, array $objects): array
    {
        $fieldReferences = $this->widgetFieldParentReferenceChain($annotationBody, $objects);
        $fieldChain = array_map(static fn (array $reference): int => $reference['object'], $fieldReferences);
        $fieldGenerations = array_map(static fn (array $reference): int => $reference['generation'], $fieldReferences);

        if ($fieldReferences === []) {
            return [
                'body' => $annotationBody,
                'inherited_keys' => [],
                'field_chain' => [],
                'field_chain_generations' => [],
                'field_sources' => [],
                'field_source_generations' => [],
            ];
        }

        $additions = [];
        $fieldSources = [];
        $fieldSourceGenerations = [];
        foreach (['A', 'AA', 'Dest'] as $key) {
            if ($this->valueAfterName($annotationBody, $key) !== null) {
                continue;
            }

            foreach ($fieldReferences as $fieldReference) {
                $fieldObject = $fieldReference['object'];
                $fieldGeneration = $fieldReference['generation'];
                $fieldObjectBody = $this->objectBodyForReference($fieldObject, $fieldGeneration, $objects);
                $fieldBody = $fieldObjectBody !== null
                    ? ($this->dictionaryObjectBody($fieldObjectBody) ?? trim($fieldObjectBody))
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
                $fieldSourceGenerations[$key] = $fieldGeneration;
                break;
            }
        }

        if ($additions === []) {
            return [
                'body' => $annotationBody,
                'inherited_keys' => [],
                'field_chain' => $fieldChain,
                'field_chain_generations' => $fieldGenerations,
                'field_sources' => [],
                'field_source_generations' => [],
            ];
        }

        return [
            'body' => rtrim($annotationBody) . ' ' . implode(' ', $additions),
            'inherited_keys' => array_keys($fieldSources),
            'field_chain' => $fieldChain,
            'field_chain_generations' => $fieldGenerations,
            'field_sources' => $fieldSources,
            'field_source_generations' => $fieldSourceGenerations,
        ];
    }

    /**
     * @param array<int, string> $objects
     * @return list<array{object: int, generation: int}>
     */
    private function widgetFieldParentReferenceChain(string $annotationBody, array $objects): array
    {
        $chain = [];
        $seen = [];
        $parent = $this->referenceValueAfterName($annotationBody, 'Parent');

        while ($parent !== null) {
            $parentKey = $this->annotationReferenceKey($parent['object'], $parent['generation']);
            if (isset($seen[$parentKey])) {
                break;
            }

            $parentObjectBody = $this->objectBodyForReference($parent['object'], $parent['generation'], $objects);
            if ($parentObjectBody === null) {
                break;
            }

            $seen[$parentKey] = true;
            $chain[] = $parent;

            $parentBody = $this->dictionaryObjectBody($parentObjectBody) ?? trim($parentObjectBody);
            $parent = $this->referenceValueAfterName($parentBody, 'Parent');
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
     * @return array<int|string, array<string, mixed>>
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
                $generation = $annotation['annotation_generation'] ?? null;
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

                if (is_int($generation)) {
                    $reviews[$this->annotationReferenceKey($object, $generation)] = $review;
                } else {
                    $reviews[$object] = $review;
                }
            }
        }

        return $reviews;
    }

    private function annotationReferenceKey(int $objectNumber, int $generation): string
    {
        return $objectNumber . ':' . $generation;
    }

    /**
     * @param array<int, string> $objects
     */
    private function annotationSubtype(string $annotationBody, array $objects = []): ?string
    {
        return $this->nameValueAfterName($annotationBody, 'Subtype', $objects);
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
     * @param array<int, string> $objects
     * @return array{annotation_flags: int, annotation_flag_names: list<string>, annotation_visibility: string}
     */
    private function annotationFlagReviewFromAnnotation(string $annotationBody, array $objects): array
    {
        $flags = $this->integerAfterName($annotationBody, 'F', $objects) ?? 0;

        return [
            'annotation_flags' => $flags,
            'annotation_flag_names' => $this->annotationFlagNames($flags),
            'annotation_visibility' => $this->annotationVisibility($flags),
        ];
    }

    /**
     * @return list<string>
     */
    private function annotationFlagNames(int $flags): array
    {
        $names = [];
        foreach (self::ANNOTATION_FLAGS as $bit => $name) {
            if ($this->hasFlagBit($flags, $bit)) {
                $names[] = $name;
            }
        }

        return $names;
    }

    private function annotationVisibility(int $flags): string
    {
        if ($this->hasFlagBit($flags, 2)) {
            return 'hidden';
        }

        if ($this->hasFlagBit($flags, 1)) {
            return 'invisible';
        }

        if ($this->hasFlagBit($flags, 6)) {
            return 'no_view';
        }

        return 'visible';
    }

    private function hasFlagBit(int $flags, int $bit): bool
    {
        return ($flags & (1 << ($bit - 1))) !== 0;
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

        $numbers = $this->numbersFromPdfArray($arrayBody, $objects);
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

        return $this->quadPointGroupsFromPdfArray($arrayBody, $objects);
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

        $highlightMode = $this->highlightModeFromAnnotation($annotationBody, $objects);
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

        $components = array_map(fn (float $component): float => $this->clamp($component), $this->numbersFromPdfArray($arrayBody, $objects));
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

    /**
     * @param array<int, string> $objects
     */
    private function highlightModeFromAnnotation(string $annotationBody, array $objects = []): ?string
    {
        return $this->nameValueAfterName($annotationBody, 'H', $objects);
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
                $styleCode = $this->nameValueAfterName($dictionary, 'S', $objects) ?? 'S';
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

        $numbers = $this->numbersFromPdfArray($arrayBody, $objects);
        if (count($numbers) < 3) {
            return null;
        }

        $dashPattern = $this->dashPatternFromBorderArrayBody($arrayBody, $objects);
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
    private function dashPatternFromBorderArrayBody(string $arrayBody, array $objects = []): array
    {
        $offset = 0;
        while (($start = strpos($arrayBody, '[', $offset)) !== false) {
            $endOffset = null;
            $body = $this->readPdfArrayAt($arrayBody, $start, $endOffset);
            if ($body === null || $endOffset === null) {
                break;
            }

            return $this->numbersFromPdfArray($body, $objects);
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
        if ($this->selfDestinationWithoutExplicitPosition($link, $page)) {
            return [];
        }

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
     * pdftext/PDFium skips same-page destination refs when the destination has
     * no concrete position. Keep the annotation row reviewable, but avoid
     * attaching a no-op self jump to WordPress spans.
     *
     * @param array<string, mixed> $link
     * @param array<string, mixed> $page
     */
    private function selfDestinationWithoutExplicitPosition(array $link, array $page): bool
    {
        if (($link['safety'] ?? null) !== 'local-destination') {
            return false;
        }

        $destinationPage = $link['destination_page'] ?? null;
        $pageNumber = $page['pnum'] ?? null;
        if (!is_int($destinationPage) || (!is_int($pageNumber) && !is_float($pageNumber))) {
            return false;
        }

        return $destinationPage === (int) $pageNumber
            && !$this->localDestinationHasExplicitPosition($link);
    }

    /**
     * @param array<string, mixed> $link
     */
    private function localDestinationHasExplicitPosition(array $link): bool
    {
        $position = $link['view_position'] ?? [];
        if (!is_array($position)) {
            return false;
        }

        foreach ($position as $value) {
            if (is_int($value) || is_float($value)) {
                return true;
            }
        }

        return false;
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
     * @param list<float> $rect
     */
    private function rectArea(array $rect): float
    {
        return $this->rectWidth($rect) * $this->rectHeight($rect);
    }

    /**
     * @param array<int, string> $objects
     * @return array{bbox: list<float>, rotation: int, user_unit: float, display_bbox: list<float>}
     */
    private function pageGeometry(int $pageObjectNumber, array $objects, ?int $pageGeneration = null): array
    {
        $pageBody = $pageGeneration === null
            ? ($objects[$pageObjectNumber] ?? '')
            : ($this->objectBodyForReference($pageObjectNumber, $pageGeneration, $objects) ?? ($objects[$pageObjectNumber] ?? ''));
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
        $parent = $this->referenceValueAfterName($pageBody, 'Parent');
        while ($parent !== null) {
            $parentKey = $this->annotationReferenceKey($parent['object'], $parent['generation']);
            if (isset($seen[$parentKey])) {
                break;
            }

            $parentBody = $this->objectBodyForReference($parent['object'], $parent['generation'], $objects);
            if ($parentBody === null) {
                break;
            }

            $seen[$parentKey] = true;
            $ancestors[] = $parentBody;
            $parent = $this->referenceValueAfterName($parentBody, 'Parent');
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
            return $arrayBody === null ? null : $this->boxFromNumbers($arrayBody, $objects);
        }

        $objectBody = $this->objectBodyForReferenceValue($value, $objects);
        if ($objectBody === null) {
            return null;
        }

        if (!str_starts_with($objectBody, '[')) {
            return null;
        }

        $arrayBody = $this->arrayBodyFromValue($objectBody);
        return $arrayBody === null ? null : $this->boxFromNumbers($arrayBody, $objects);
    }

    /**
     * @return list<float>|null
     */
    private function boxFromNumbers(string $body, array $objects = []): ?array
    {
        $numbers = $this->numbersFromPdfArray($body, $objects);
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

    /**
     * @return array{object: int, generation: int}|null
     */
    private function referenceValueAfterName(string $body, string $name): ?array
    {
        $value = $this->valueAfterName($body, $name);
        return $value === null ? null : $this->objectReferenceFromValue($value);
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
        $this->skipWhitespaceAndComments($body, $offset);

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

    /**
     * @param array<int, string> $objects
     */
    private function nameValueAfterName(string $body, string $name, array $objects = []): ?string
    {
        $value = $this->valueAfterName($body, $name);
        if ($value === null) {
            return null;
        }

        $trimmed = trim($this->resolveIndirectObjectValue($value, $objects));
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
        return $arrayBody === null ? null : $this->numbersFromPdfArray($arrayBody, $objects);
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
        $this->xrefSelectedGenerationZeroObjects = [];
        $this->xrefStreamSuppressedObjectNumbers = [];
        $definitions = $this->pdfObjectDefinitions($pdfBytes);
        $objects = [];
        if ($definitions === []) {
            return $objects;
        }

        foreach ($definitions as $definition) {
            $objects[$definition['object_id']] = $definition['body'];
        }

        $xrefEntries = $this->xrefStreamEntriesFromLatestStartxref($pdfBytes, $definitions);
        if ($xrefEntries === []) {
            return $objects;
        }

        foreach (array_keys($xrefEntries) as $objectNumber) {
            $this->xrefStreamSuppressedObjectNumbers[(int) $objectNumber] = true;
        }

        $selectedObjects = $this->objectsFromXrefStreamEntries($definitions, $xrefEntries);
        if ($selectedObjects === []) {
            $this->xrefStreamSuppressedObjectNumbers = [];
            return $objects;
        }

        $selectedObjects = $this->withCompressedObjectStreamObjects($selectedObjects, $xrefEntries);
        foreach ($definitions as $definition) {
            if (array_key_exists($definition['object_id'], $xrefEntries)) {
                continue;
            }

            $selectedObjects[$definition['object_id']] = $definition['body'];
        }
        ksort($selectedObjects, SORT_NUMERIC);

        return $selectedObjects;
    }

    /**
     * @return list<array{object_id: int, generation: int, body: string, offset: int}>
     */
    private function pdfObjectDefinitions(string $pdfBytes): array
    {
        if (!preg_match_all('/(\d+)\s+(\d+)\s+obj\b(.*?)\bendobj/s', $pdfBytes, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE)) {
            return [];
        }

        $definitions = [];
        foreach ($matches as $match) {
            $definitions[] = [
                'object_id' => (int) $match[1][0],
                'generation' => (int) $match[2][0],
                'body' => $match[3][0],
                'offset' => (int) $match[0][1],
            ];
        }

        return $definitions;
    }

    /**
     * @param list<array{object_id: int, generation: int, body: string, offset: int}> $definitions
     * @return array<int, array{type: int, generation?: int, offset?: int, object_stream?: int, index?: int, index_is_explicit?: bool}>
     */
    private function xrefStreamEntriesFromLatestStartxref(string $pdfBytes, array $definitions): array
    {
        $offset = $this->latestStartxrefOffset($pdfBytes);
        if ($offset === null) {
            return [];
        }

        $section = $this->xrefStreamSectionAtOffset($offset, $definitions);
        return $section === null ? [] : $this->xrefStreamEntriesFromSection($section);
    }

    /**
     * @param list<array{object_id: int, generation: int, body: string, offset: int}> $definitions
     * @return array{dictionary: string, body: string}|null
     */
    private function xrefStreamSectionAtOffset(int $offset, array $definitions): ?array
    {
        foreach ($definitions as $definition) {
            if ($definition['offset'] !== $offset) {
                continue;
            }

            $dictionary = $this->dictionaryObjectBody($definition['body']);
            if ($dictionary === null || $this->nameValueAfterName($dictionary, 'Type') !== 'XRef') {
                return null;
            }

            return [
                'dictionary' => $dictionary,
                'body' => $definition['body'],
            ];
        }

        return null;
    }

    /**
     * @param array{dictionary: string, body: string} $section
     * @return array<int, array{type: int, generation?: int, offset?: int, object_stream?: int, index?: int, index_is_explicit?: bool}>
     */
    private function xrefStreamEntriesFromSection(array $section): array
    {
        $decoded = $this->decodedStreamBytes($section['body'], $section['dictionary']);
        if ($decoded === null) {
            return [];
        }

        $widths = $this->xrefStreamFieldWidths($section['dictionary']);
        if ($widths === null) {
            return [];
        }

        $entryWidth = array_sum($widths);
        if ($entryWidth <= 0) {
            return [];
        }

        $decodedEntryCount = intdiv(strlen($decoded), $entryWidth);
        $entries = [];
        $fieldOffset = 0;
        foreach ($this->xrefStreamIndexRanges($section['dictionary'], $decodedEntryCount) as $range) {
            for ($row = 0; $row < $range['count'] && $fieldOffset + $entryWidth <= strlen($decoded); $row++) {
                $objectNumber = $range['first'] + $row;
                $type = $widths[0] === 0 ? 1 : $this->xrefStreamFieldValue($decoded, $fieldOffset, $widths[0]);
                $fieldTwo = $this->xrefStreamFieldValue($decoded, $fieldOffset, $widths[1]);
                $fieldThree = $this->xrefStreamFieldValue($decoded, $fieldOffset, $widths[2]);

                if ($type === 1) {
                    $entries[$objectNumber] = [
                        'type' => 1,
                        'offset' => $fieldTwo,
                        'generation' => $fieldThree,
                    ];
                    continue;
                }

                if ($type === 2 && $fieldTwo > 0) {
                    $entries[$objectNumber] = [
                        'type' => 2,
                        'object_stream' => $fieldTwo,
                        'index' => $fieldThree,
                        'index_is_explicit' => $widths[2] > 0,
                    ];
                    continue;
                }

                $entries[$objectNumber] = [
                    'type' => $type,
                    'generation' => $fieldThree,
                    'offset' => $fieldTwo,
                ];
            }
        }
        ksort($entries, SORT_NUMERIC);

        return $entries;
    }

    /**
     * @return array{0: int, 1: int, 2: int}|null
     */
    private function xrefStreamFieldWidths(string $dictionary): ?array
    {
        $arrayBody = $this->arrayBodyValueAfterName($dictionary, 'W', []);
        if ($arrayBody === null) {
            return null;
        }

        $numbers = $this->numbersFromPdfArray($arrayBody);
        if (count($numbers) < 3) {
            return null;
        }

        $widths = [];
        foreach (array_slice($numbers, 0, 3) as $number) {
            if ($number < 0.0 || abs($number - round($number)) > 0.000001) {
                return null;
            }
            $widths[] = (int) round($number);
        }

        return [$widths[0], $widths[1], $widths[2]];
    }

    /**
     * @return list<array{first: int, count: int}>
     */
    private function xrefStreamIndexRanges(string $dictionary, int $decodedEntryCount): array
    {
        $arrayBody = $this->arrayBodyValueAfterName($dictionary, 'Index', []);
        if ($arrayBody === null) {
            $size = $this->directIntegerAfterName($dictionary, 'Size');

            return [[
                'first' => 0,
                'count' => $size === null ? $decodedEntryCount : min($size, $decodedEntryCount),
            ]];
        }

        $numbers = $this->numbersFromPdfArray($arrayBody);
        $ranges = [];
        $consumed = 0;
        for ($index = 0, $count = count($numbers); $index + 1 < $count; $index += 2) {
            $first = $numbers[$index];
            $rowCount = $numbers[$index + 1];
            if ($first < 0.0 || $rowCount < 0.0 || abs($first - round($first)) > 0.000001 || abs($rowCount - round($rowCount)) > 0.000001) {
                continue;
            }

            $boundedCount = min((int) round($rowCount), max(0, $decodedEntryCount - $consumed));
            $ranges[] = [
                'first' => (int) round($first),
                'count' => $boundedCount,
            ];
            $consumed += $boundedCount;
        }

        return $ranges;
    }

    private function xrefStreamFieldValue(string $bytes, int &$offset, int $width): int
    {
        $value = 0;
        for ($index = 0; $index < $width; $index++) {
            $value = ($value << 8) + ord($bytes[$offset] ?? "\0");
            $offset++;
        }

        return $value;
    }

    /**
     * @param list<array{object_id: int, generation: int, body: string, offset: int}> $definitions
     * @param array<int, array{type: int, generation?: int, offset?: int, object_stream?: int, index?: int, index_is_explicit?: bool}> $xrefEntries
     * @return array<int, string>
     */
    private function objectsFromXrefStreamEntries(array $definitions, array $xrefEntries): array
    {
        $definitionsByOffset = [];
        foreach ($definitions as $definition) {
            $definitionsByOffset[$definition['offset']] = $definition;
        }

        $objects = [];
        foreach ($xrefEntries as $objectNumber => $entry) {
            if (($entry['type'] ?? null) !== 1 || !isset($entry['offset'])) {
                continue;
            }

            $definition = $definitionsByOffset[$entry['offset']] ?? null;
            if ($definition === null
                || $definition['object_id'] !== $objectNumber
                || $definition['generation'] !== ($entry['generation'] ?? 0)
            ) {
                continue;
            }

            $objects[$objectNumber] = $definition['body'];
            unset($this->xrefStreamSuppressedObjectNumbers[(int) $objectNumber]);
            if (($entry['generation'] ?? 0) === 0) {
                $this->xrefSelectedGenerationZeroObjects[(int) $objectNumber] = true;
            }
        }
        ksort($objects, SORT_NUMERIC);

        return $objects;
    }

    /**
     * @param array<int, string> $objects
     * @param array<int, array{type: int, generation?: int, offset?: int, object_stream?: int, index?: int, index_is_explicit?: bool}> $xrefEntries
     * @return array<int, string>
     */
    private function withCompressedObjectStreamObjects(array $objects, array $xrefEntries): array
    {
        $expanded = $objects;
        foreach ($xrefEntries as $objectNumber => $entry) {
            if (($entry['type'] ?? null) !== 2 || isset($expanded[$objectNumber])) {
                continue;
            }

            $body = $this->objectStreamMemberBody($expanded, $entry, (int) $objectNumber);
            if ($body === null) {
                continue;
            }

            $expanded[$objectNumber] = $body;
            unset($this->xrefStreamSuppressedObjectNumbers[(int) $objectNumber]);
            $this->xrefSelectedGenerationZeroObjects[(int) $objectNumber] = true;
        }
        ksort($expanded, SORT_NUMERIC);

        return $expanded;
    }

    /**
     * @param array<int, string> $objects
     * @param array{type: int, object_stream?: int, index?: int, index_is_explicit?: bool} $xrefEntry
     */
    private function objectStreamMemberBody(array $objects, array $xrefEntry, int $requestedObjectNumber): ?string
    {
        $objectStreamNumber = $xrefEntry['object_stream'] ?? null;
        if (!is_int($objectStreamNumber) || !isset($objects[$objectStreamNumber])) {
            return null;
        }

        $objectStreamBody = $objects[$objectStreamNumber];
        $dictionary = $this->dictionaryObjectBody($objectStreamBody);
        if ($dictionary === null || $this->nameValueAfterName($dictionary, 'Type') !== 'ObjStm') {
            return null;
        }

        $declaredCount = $this->directIntegerAfterName($dictionary, 'N');
        $firstOffset = $this->directIntegerAfterName($dictionary, 'First');
        if ($declaredCount === null || $declaredCount < 1 || $firstOffset === null || $firstOffset < 0) {
            return null;
        }

        $decoded = $this->decodedStreamBytes($objectStreamBody, $dictionary);
        if ($decoded === null || $firstOffset > strlen($decoded)) {
            return null;
        }

        $members = $this->objectStreamHeaderMembers(substr($decoded, 0, $firstOffset), $declaredCount);
        if ($members === []) {
            return null;
        }

        $memberIndex = $this->objectStreamSelectedMemberIndex($members, $xrefEntry, $requestedObjectNumber);
        if ($memberIndex === null) {
            return null;
        }

        $data = substr($decoded, $firstOffset);
        $start = $members[$memberIndex]['offset'];
        if ($start < 0 || $start >= strlen($data)) {
            return null;
        }

        $end = strlen($data);
        foreach ($members as $index => $member) {
            if ($index === $memberIndex || $member['offset'] <= $start) {
                continue;
            }
            $end = min($end, $member['offset']);
        }
        if ($end <= $start) {
            return null;
        }

        return trim(substr($data, $start, $end - $start));
    }

    /**
     * @return list<array{object_id: int, offset: int}>
     */
    private function objectStreamHeaderMembers(string $header, int $declaredCount): array
    {
        if (preg_match_all('/\d+/', $header, $matches) < 1) {
            return [];
        }

        $members = [];
        $tokens = $matches[0];
        for ($index = 0, $count = count($tokens); $index + 1 < $count && count($members) < $declaredCount; $index += 2) {
            $members[] = [
                'object_id' => (int) $tokens[$index],
                'offset' => (int) $tokens[$index + 1],
            ];
        }

        return $members;
    }

    /**
     * @param list<array{object_id: int, offset: int}> $members
     * @param array{type: int, index?: int, index_is_explicit?: bool} $xrefEntry
     */
    private function objectStreamSelectedMemberIndex(array $members, array $xrefEntry, int $requestedObjectNumber): ?int
    {
        $requestedIndex = $xrefEntry['index'] ?? null;
        if (is_int($requestedIndex) && ($xrefEntry['index_is_explicit'] ?? true) === true) {
            if (!isset($members[$requestedIndex]) || $members[$requestedIndex]['object_id'] !== $requestedObjectNumber) {
                return null;
            }

            return $requestedIndex;
        }

        foreach ($members as $index => $member) {
            if ($member['object_id'] === $requestedObjectNumber) {
                return $index;
            }
        }

        return null;
    }

    private function decodedStreamBytes(string $body, string $dictionary): ?string
    {
        $decoded = $this->streamBytesFromBody($body, $dictionary);
        if ($decoded === null) {
            return null;
        }

        foreach ($this->filterNamesAfterName($dictionary, 'Filter') as $filter) {
            $next = match ($filter) {
                'FlateDecode', 'Fl' => $this->decodeFlateStream($decoded),
                'ASCIIHexDecode', 'AHx' => $this->decodeAsciiHexStream($decoded),
                default => null,
            };
            if ($next === null) {
                return null;
            }
            $decoded = $next;
        }

        return $decoded;
    }

    private function streamBytesFromBody(string $body, string $dictionary): ?string
    {
        $dictOffset = strpos($body, '<<');
        if ($dictOffset === false) {
            return null;
        }

        $endOffset = null;
        if ($this->readPdfDictionaryAt($body, $dictOffset, $endOffset) === null || $endOffset === null) {
            return null;
        }

        $offset = $endOffset;
        $this->skipWhitespaceAndComments($body, $offset);
        if (substr($body, $offset, strlen('stream')) !== 'stream') {
            return null;
        }

        $start = $offset + strlen('stream');
        if (substr($body, $start, 2) === "\r\n") {
            $start += 2;
        } elseif (($body[$start] ?? '') === "\n" || ($body[$start] ?? '') === "\r") {
            $start++;
        }

        $end = strpos($body, 'endstream', $start);
        if ($end === false || $end < $start) {
            return null;
        }

        $stream = substr($body, $start, $end - $start);
        $length = $this->directIntegerAfterName($dictionary, 'Length');
        if ($length !== null && $length >= 0 && $length <= strlen($stream)) {
            return substr($stream, 0, $length);
        }

        return preg_replace("/\r\n$|\n$|\r$/", '', $stream) ?? $stream;
    }

    /**
     * @return list<string>
     */
    private function filterNamesAfterName(string $dictionary, string $name): array
    {
        $value = $this->valueAfterName($dictionary, $name);
        if ($value === null) {
            return [];
        }

        $trimmed = trim($value);
        if ($trimmed === '') {
            return [];
        }

        if ($trimmed[0] === '/') {
            $endOffset = $this->skipPdfName($trimmed, 0);
            return [$this->decodePdfName(substr($trimmed, 1, $endOffset - 1))];
        }

        if ($trimmed[0] !== '[') {
            return [];
        }

        $arrayBody = $this->arrayBodyFromValue($trimmed);
        if ($arrayBody === null) {
            return [];
        }

        $filters = [];
        $offset = 0;
        $length = strlen($arrayBody);
        while ($offset < $length) {
            $this->skipWhitespaceAndComments($arrayBody, $offset);
            if ($offset >= $length) {
                break;
            }

            if (($arrayBody[$offset] ?? '') !== '/') {
                $endOffset = null;
                $value = $this->valueStartingAtOffsetWithEnd($arrayBody, $offset, $endOffset);
                $offset = $value !== null && $endOffset !== null && $endOffset > $offset ? $endOffset : $offset + 1;
                continue;
            }

            $endOffset = $this->skipPdfName($arrayBody, $offset);
            $filters[] = $this->decodePdfName(substr($arrayBody, $offset + 1, $endOffset - $offset - 1));
            $offset = $endOffset;
        }

        return $filters;
    }

    private function directIntegerAfterName(string $body, string $name): ?int
    {
        $value = $this->valueAfterName($body, $name);
        if ($value === null) {
            return null;
        }

        $trimmed = trim($value);
        if (preg_match('/^[+-]?\d+/', $trimmed, $match) !== 1) {
            return null;
        }

        return (int) $match[0];
    }

    private function decodeFlateStream(string $bytes): ?string
    {
        $decoded = @gzuncompress($bytes);
        if ($decoded === false) {
            $decoded = @gzinflate($bytes);
        }
        if ($decoded === false) {
            $decoded = @gzdecode($bytes);
        }

        return $decoded === false ? null : $decoded;
    }

    private function decodeAsciiHexStream(string $bytes): ?string
    {
        $body = strstr($bytes, '>', true);
        if ($body === false) {
            $body = $bytes;
        }

        $hex = preg_replace('/\s+/', '', $body);
        if ($hex === null || preg_match('/^[\da-fA-F]*$/', $hex) !== 1) {
            return null;
        }
        if (strlen($hex) % 2 === 1) {
            $hex .= '0';
        }

        $decoded = hex2bin($hex);

        return $decoded === false ? null : $decoded;
    }

    private function latestStartxrefOffset(string $pdfBytes): ?int
    {
        if (preg_match_all('/\bstartxref\s+([+-]?\d+)/s', $pdfBytes, $matches) < 1) {
            return null;
        }

        $offset = (int) end($matches[1]);

        return $offset >= 0 && $offset < strlen($pdfBytes) ? $offset : null;
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
        if (isset($this->xrefFreeObjectNumbers[$objectNumber])) {
            return null;
        }

        if ($generation === 0 && isset($this->xrefSelectedGenerationZeroObjects[$objectNumber], $objects[$objectNumber])) {
            return trim($objects[$objectNumber]);
        }

        if (isset($this->xrefStreamSuppressedObjectNumbers[$objectNumber])) {
            return null;
        }

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
        return array_map(
            static fn (array $reference): int => $reference['object'],
            $this->orderedPageObjectReferences($objects)
        );
    }

    /**
     * @return list<array{object: int, generation: int}>
     * @param array<int, string> $objects
     */
    private function orderedPageObjectReferences(array $objects): array
    {
        foreach ($objects as $body) {
            if (preg_match('/\/Type\s*\/Catalog\b/', $body) !== 1) {
                continue;
            }

            $pagesReference = $this->referenceValueAfterName($body, 'Pages');
            if ($pagesReference === null) {
                continue;
            }

            $pages = $this->pageObjectReferencesFromTree($pagesReference['object'], $pagesReference['generation'], $objects);
            if ($pages !== []) {
                return $pages;
            }
        }

        $pages = [];
        foreach ($objects as $objectNumber => $body) {
            if (preg_match('/\/Type\s*\/Page\b/', $body) === 1) {
                $pages[] = ['object' => $objectNumber, 'generation' => 0];
            }
        }

        return $pages;
    }

    /**
     * @return list<array{object: int, generation: int}>
     * @param array<int, string> $objects
     * @param array<string, true> $seen
     */
    private function pageObjectReferencesFromTree(int $objectNumber, int $generation, array $objects, array $seen = []): array
    {
        $key = $this->annotationReferenceKey($objectNumber, $generation);
        if (isset($seen[$key])) {
            return [];
        }

        $body = $this->objectBodyForReference($objectNumber, $generation, $objects);
        if ($body === null) {
            return [];
        }

        $seen[$key] = true;
        if (preg_match('/\/Type\s*\/Page\b/', $body) === 1) {
            return [['object' => $objectNumber, 'generation' => $generation]];
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
        foreach ($this->objectReferenceValues($arrayBody) as $childReference) {
            foreach ($this->pageObjectReferencesFromTree($childReference['object'], $childReference['generation'], $objects, $seen) as $pageReference) {
                $pages[] = $pageReference;
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
     * @return list<array{object: int, generation: int}>
     */
    private function objectReferenceValues(string $value): array
    {
        $references = [];
        $offset = 0;
        $length = strlen($value);

        while ($offset < $length) {
            $this->skipWhitespaceAndComments($value, $offset);
            if ($offset >= $length) {
                break;
            }

            $endOffset = null;
            $item = $this->valueStartingAtOffsetWithEnd($value, $offset, $endOffset);
            if ($item === null || $endOffset === null || $endOffset <= $offset) {
                $offset++;
                continue;
            }

            if (preg_match('/^(\d+)\s+(\d+)\s+R\b/s', trim($item), $match) === 1) {
                $references[] = [
                    'object' => (int) $match[1],
                    'generation' => (int) $match[2],
                ];
            }

            $offset = $endOffset;
        }

        return $references;
    }

    /**
     * @return list<int>
     */
    private function objectReferences(string $value): array
    {
        return array_map(
            static fn (array $reference): int => $reference['object'],
            $this->objectReferenceValues($value)
        );
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
            if ($char === '%') {
                while ($index < $length && $value[$index] !== "\n" && $value[$index] !== "\r") {
                    $index++;
                }
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
    private function numbersFromPdfArray(string $arrayBody, array $objects = [], int $depth = 0): array
    {
        if ($depth > 8) {
            return [];
        }

        $numbers = [];
        $offset = 0;
        $length = strlen($arrayBody);
        while ($offset < $length) {
            $this->skipWhitespaceAndComments($arrayBody, $offset);
            if ($offset >= $length) {
                break;
            }

            if ($arrayBody[$offset] === '(') {
                $offset = $this->skipLiteralString($arrayBody, $offset);
                continue;
            }
            if ($arrayBody[$offset] === '<' && substr($arrayBody, $offset, 2) === '<<') {
                $endDictionary = null;
                $this->readPdfDictionaryAt($arrayBody, $offset, $endDictionary);
                $offset = $endDictionary ?? ($offset + 2);
                continue;
            }
            if ($arrayBody[$offset] === '<') {
                $offset = $this->skipHexString($arrayBody, $offset);
                continue;
            }
            if ($arrayBody[$offset] === '[') {
                $endArray = null;
                $nested = $this->readPdfArrayAt($arrayBody, $offset, $endArray);
                if ($nested !== null && $endArray !== null) {
                    array_push($numbers, ...$this->numbersFromPdfArray($nested, $objects, $depth + 1));
                    $offset = $endArray;
                    continue;
                }
            }

            if (preg_match('/\G(\d+)\s+(\d+)\s+R\b/s', $arrayBody, $match, 0, $offset) === 1) {
                $objectBody = $this->objectBodyForReference((int) $match[1], (int) $match[2], $objects);
                if ($objectBody !== null && preg_match('/^[+-]?(?:\d+(?:\.\d*)?|\.\d+)$/', trim($objectBody)) === 1) {
                    $numbers[] = (float) trim($objectBody);
                }
                $offset += strlen($match[0]);
                continue;
            }

            if (preg_match('/\G[+-]?(?:\d+(?:\.\d*)?|\.\d+)/', $arrayBody, $match, 0, $offset) === 1) {
                $numbers[] = (float) $match[0];
                $offset += strlen($match[0]);
                continue;
            }

            $offset++;
        }

        return $numbers;
    }

    /**
     * Link /QuadPoints are flat groups of eight numeric coordinates. Treat a
     * malformed token as a group boundary so later valid groups are preserved
     * without recombining coordinates into a synthetic clickable rectangle.
     *
     * @param array<int, string> $objects
     * @return list<list<float>>
     */
    private function quadPointGroupsFromPdfArray(string $arrayBody, array $objects = []): array
    {
        $groups = [];
        $current = [];
        $offset = 0;
        $length = strlen($arrayBody);

        while ($offset < $length) {
            $this->skipWhitespaceAndComments($arrayBody, $offset);
            if ($offset >= $length) {
                break;
            }

            $numberEnd = null;
            $number = $this->numericArrayElementAtOffset($arrayBody, $offset, $objects, $numberEnd);
            if ($numberEnd !== null && $numberEnd > $offset) {
                if ($number === null) {
                    $current = [];
                    $offset = $numberEnd;
                    continue;
                }

                $current[] = $number;
                if (count($current) === 8) {
                    $groups[] = $current;
                    $current = [];
                }
                $offset = $numberEnd;
                continue;
            }

            $current = [];
            $valueEnd = null;
            $value = $this->valueStartingAtOffsetWithEnd($arrayBody, $offset, $valueEnd);
            if ($value !== null && $valueEnd !== null && $valueEnd > $offset) {
                $offset = $valueEnd;
                continue;
            }

            $offset++;
        }

        return $groups;
    }

    /**
     * @param array<int, string> $objects
     */
    private function numericArrayElementAtOffset(string $arrayBody, int $offset, array $objects, ?int &$endOffset): ?float
    {
        if (preg_match('/\G(\d+)\s+(\d+)\s+R\b/s', $arrayBody, $match, 0, $offset) === 1) {
            $endOffset = $offset + strlen($match[0]);
            $objectBody = $this->objectBodyForReference((int) $match[1], (int) $match[2], $objects);
            if ($objectBody !== null && preg_match('/^[+-]?(?:\d+(?:\.\d*)?|\.\d+)$/', trim($objectBody)) === 1) {
                return (float) trim($objectBody);
            }

            return null;
        }

        if (preg_match('/\G[+-]?(?:\d+(?:\.\d*)?|\.\d+)(?=$|[\s\[\]\(\)<>{}\/%])/s', $arrayBody, $match, 0, $offset) === 1) {
            $endOffset = $offset + strlen($match[0]);
            return (float) $match[0];
        }

        $endOffset = null;
        return null;
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
