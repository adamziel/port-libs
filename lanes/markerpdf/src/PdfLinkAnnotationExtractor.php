<?php

declare(strict_types=1);

namespace PortLibs\MarkerPDF;

final class PdfLinkAnnotationExtractor
{
    /**
     * Native boundary for PDF page /Annots link actions.
     *
     * @return list<array{pnum: int, page_object: int, links: list<array<string, mixed>>}>
     */
    public function extractPageLinks(string $pdfBytes): array
    {
        $objects = $this->pdfObjects($pdfBytes);
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
                $context
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

                        $link = $this->linkForSpan($span, $links);
                        if ($link === null) {
                            continue;
                        }

                        $page['blocks'][$blockIndex]['lines'][$lineIndex]['spans'][$spanIndex]['link_rect'] = $link['rect'];
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

                        if (is_string($link['uri'] ?? null) && ($link['is_safe_uri'] ?? false) === true) {
                            $page['blocks'][$blockIndex]['lines'][$lineIndex]['spans'][$spanIndex]['link_uri'] = $link['uri'];
                        }

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
     * @return array<string, mixed>|null
     */
    private function linkForSpan(array $span, array $links): ?array
    {
        $bbox = $this->bbox($span['bbox'] ?? null);
        if ($bbox === null) {
            return null;
        }

        foreach ($links as $link) {
            if ($this->bboxesIntersect($bbox, $link['rect'])) {
                return $link;
            }
        }

        return null;
    }

    /**
     * @param array<int, string> $objects
     * @param array<int, array<string, mixed>> $structureReviewsByAnnotationObject
     * @param array<string, mixed> $context
     * @return list<array<string, mixed>>
     */
    private function linksFromPageObject(
        string $pageBody,
        array $objects,
        PdfActionReviewExtractor $actionReviewer,
        array $structureReviewsByAnnotationObject,
        array $context
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
                $context
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
        $annots = $this->valueAfterName($pageBody, 'Annots');
        if ($annots === null) {
            return [];
        }

        return $this->annotationBodiesFromValue($annots, $objects);
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
     * @param array<int, string> $objects
     * @param array<int, array<string, mixed>> $structureReviewsByAnnotationObject
     * @param array<string, mixed> $context
     * @return array<string, mixed>|null
     */
    private function linkFromAnnotationBody(
        string $annotationBody,
        array $objects,
        PdfActionReviewExtractor $actionReviewer,
        ?int $annotationObject,
        array $structureReviewsByAnnotationObject,
        array $context
    ): ?array
    {
        $subtype = $this->annotationSubtype($annotationBody);
        if (!in_array($subtype, ['Link', 'Widget'], true)) {
            return null;
        }

        if ($this->annotationHiddenFromLinkImport($annotationBody, $objects)) {
            return null;
        }

        $rect = $this->rectFromAnnotation($annotationBody, $objects);
        if ($rect === null) {
            return null;
        }

        $review = $actionReviewer->reviewAnnotationActions($annotationBody);
        $review['actions'] = $this->withLinkTargetContextRows($review['actions'], $context);
        $review['additional_actions'] = $this->withLinkTargetContextRows($review['additional_actions'], $context);
        $primary = $this->primaryLinkAction($review['actions']);
        if ($primary === null) {
            return null;
        }

        $link = $primary + [
            'rect' => $rect,
            'annotation_object' => $annotationObject,
            'annotation_subtype' => $subtype,
            'widget_annotation' => $subtype === 'Widget',
            'actions' => $review['actions'],
            'additional_actions' => $review['additional_actions'],
            'executes_on_import' => false,
        ];

        if ($annotationObject !== null && isset($structureReviewsByAnnotationObject[$annotationObject])) {
            $link += $structureReviewsByAnnotationObject[$annotationObject];
        }

        return $link;
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
        return preg_match('/\/Subtype\s*\/([A-Za-z0-9_.-]+)\b/', $annotationBody, $match) === 1
            ? $match[1]
            : null;
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
        if (preg_match('/\/' . preg_quote($name, '/') . '\b/s', $body, $match, PREG_OFFSET_CAPTURE) !== 1) {
            return null;
        }

        $offset = $match[0][1] + strlen($match[0][0]);
        while ($offset < strlen($body) && ctype_space($body[$offset])) {
            $offset++;
        }

        if ($offset >= strlen($body)) {
            return null;
        }

        if ($body[$offset] === '[') {
            $endOffset = null;
            $this->readPdfArrayAt($body, $offset, $endOffset);
            return $endOffset === null ? null : substr($body, $offset, $endOffset - $offset);
        }

        if (substr($body, $offset, 2) === '<<') {
            $endOffset = null;
            $this->readPdfDictionaryAt($body, $offset, $endOffset);
            return $endOffset === null ? null : substr($body, $offset, $endOffset - $offset);
        }

        if ($body[$offset] === '(') {
            $endOffset = $this->skipLiteralString($body, $offset);
            return substr($body, $offset, $endOffset - $offset);
        }

        if ($body[$offset] === '<') {
            $endOffset = $this->skipHexString($body, $offset);
            return substr($body, $offset, $endOffset - $offset);
        }

        $end = $offset;
        while ($end < strlen($body) && !ctype_space($body[$end]) && !str_contains('[]()<>{}/%', $body[$end])) {
            $end++;
        }
        if ($end < strlen($body) && $body[$end] === '/') {
            return substr($body, $offset, $end - $offset);
        }

        if (preg_match('/\G\d+\s+\d+\s+R\b/s', $body, $ref, 0, $offset) === 1) {
            return $ref[0];
        }

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
        if (preg_match('/^(\d+)\s+\d+\s+R\b/', $trimmed, $match) !== 1) {
            return $value;
        }

        $objectNumber = (int) $match[1];
        if (!isset($objects[$objectNumber])) {
            return $value;
        }

        return trim($objects[$objectNumber]);
    }

    private function isSafeUri(string $uri): bool
    {
        $trimmed = trim($uri);
        if ($trimmed === '') {
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
}
