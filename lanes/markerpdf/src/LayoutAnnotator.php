<?php

declare(strict_types=1);

namespace PortLibs\MarkerPDF;

use InvalidArgumentException;

final class LayoutAnnotator
{
    private const AMBIGUOUS_PAGE_MARKER_WRAPPER = '__markerpdf_ambiguous_page_marker_wrapper';
    private const ENVELOPE_PAGE_KEY_MARKER = '__markerpdf_envelope_page_key_marker';
    private const LAYOUT_RESULT_PAGE_MARKER_KEYS = [
        'page_index',
        'pageIndex',
        'page_idx',
        'doc_page_index',
        'docPageIndex',
        'doc_page_idx',
        'document_page_index',
        'documentPageIndex',
        'document_page_idx',
        'source_page_index',
        'sourcePageIndex',
        'source_page_idx',
        'selected_page_index',
        'selectedPageIndex',
        'selected_page_idx',
        'trimmed_page_index',
        'trimmedPageIndex',
        'trimmed_page_idx',
        'relative_page_index',
        'relativePageIndex',
        'relative_page_idx',
        'pnum',
        'page',
        'page_id',
        'pageId',
        'page_ids',
        'pageIds',
        'pdftext_page',
        'pdftextPage',
        'pdftext_pages',
        'pdftextPages',
        'pdftext_page_id',
        'pdftextPageId',
        'pdftext_page_ids',
        'pdftextPageIds',
        'source_page',
        'sourcePage',
        'source_pages',
        'sourcePages',
        'source_page_id',
        'sourcePageId',
        'source_page_ids',
        'sourcePageIds',
        'document_page',
        'documentPage',
        'document_pages',
        'documentPages',
        'document_page_id',
        'documentPageId',
        'document_page_ids',
        'documentPageIds',
        'doc_page_id',
        'docPageId',
        'doc_page_ids',
        'docPageIds',
        'page_number',
        'pageNumber',
        'page_numbers',
        'pageNumbers',
        'page_num',
        'pageNum',
        'page_nums',
        'pageNums',
        'doc_page_number',
        'docPageNumber',
        'doc_page_numbers',
        'docPageNumbers',
        'document_page_number',
        'documentPageNumber',
        'document_page_numbers',
        'documentPageNumbers',
        'pdftext_page_number',
        'pdftextPageNumber',
        'pdftext_page_numbers',
        'pdftextPageNumbers',
        'source_page_number',
        'sourcePageNumber',
        'source_page_numbers',
        'sourcePageNumbers',
        'selected_page_number',
        'selectedPageNumber',
        'selected_page_numbers',
        'selectedPageNumbers',
        'trimmed_page_number',
        'trimmedPageNumber',
        'trimmed_page_numbers',
        'trimmedPageNumbers',
        'relative_page_number',
        'relativePageNumber',
        'relative_page_numbers',
        'relativePageNumbers',
        'selected_page_num',
        'selectedPageNum',
        'selected_page_nums',
        'selectedPageNums',
        'trimmed_page_num',
        'trimmedPageNum',
        'trimmed_page_nums',
        'trimmedPageNums',
        'relative_page_num',
        'relativePageNum',
        'relative_page_nums',
        'relativePageNums',
    ];
    private const LAYOUT_RESULT_PAGE_MARKER_METADATA_WRAPPERS = [
        'metadata',
        'page_metadata',
        'page_meta',
        'page_info',
        'page_data',
        'page_result',
        'result_metadata',
        'artifact_metadata',
        'pdftext_source',
    ];
    private const LAYOUT_RESULT_PAGE_MARKER_WRAPPERS = [
        'metadata',
        'page_metadata',
        'page_meta',
        'page_info',
        'page_data',
        'page_result',
        'result_metadata',
        'artifact_metadata',
        'layout',
        'layout_result',
        'prediction',
        'result',
        'model_output',
        'output',
        'source',
        'pdftext',
    ];
    private const LAYOUT_RESULT_PAYLOAD_WRAPPERS = [
        'layout',
        'layout_result',
        'page_data',
        'page_result',
        'result_metadata',
        'artifact_metadata',
        'prediction',
        'result',
        'model_output',
        'output',
    ];
    private const LAYOUT_RESULT_DIRECT_PAYLOAD_ENVELOPES = [
        'pages',
        'dictionary_output',
        'pdftext',
        'page_map',
        'pageMap',
    ];
    private const LAYOUT_RESULT_PAGE_MARKER_FIELD_GROUPS = [
        ['page_index', 'pageIndex', 'page_idx', 'doc_page_index', 'docPageIndex', 'doc_page_idx', 'document_page_index', 'documentPageIndex', 'document_page_idx', 'source_page_index', 'sourcePageIndex', 'source_page_idx', 'page_range', 'pageRange', 'source_page_range', 'sourcePageRange', 'document_page_range', 'documentPageRange', 'page_indices', 'pageIndices', 'source_page_indices', 'sourcePageIndices', 'document_page_indices', 'documentPageIndices'],
        ['selected_page_index', 'selectedPageIndex', 'selected_page_idx', 'trimmed_page_index', 'trimmedPageIndex', 'trimmed_page_idx', 'relative_page_index', 'relativePageIndex', 'relative_page_idx', 'selected_page_range', 'selectedPageRange', 'trimmed_page_range', 'trimmedPageRange', 'relative_page_range', 'relativePageRange', 'selected_page_indices', 'selectedPageIndices', 'trimmed_page_indices', 'trimmedPageIndices', 'relative_page_indices', 'relativePageIndices'],
        ['pnum', 'pnums', 'page', 'page_id', 'pageId', 'page_ids', 'pageIds', 'pdftext_page', 'pdftextPage', 'pdftext_pages', 'pdftextPages', 'pdftext_page_id', 'pdftextPageId', 'pdftext_page_ids', 'pdftextPageIds', 'source_page', 'sourcePage', 'source_pages', 'sourcePages', 'source_page_id', 'sourcePageId', 'source_page_ids', 'sourcePageIds', 'document_page', 'documentPage', 'document_pages', 'documentPages', 'document_page_id', 'documentPageId', 'document_page_ids', 'documentPageIds', 'doc_page_id', 'docPageId', 'doc_page_ids', 'docPageIds'],
        ['page_number', 'pageNumber', 'page_numbers', 'pageNumbers', 'page_num', 'pageNum', 'page_nums', 'pageNums', 'doc_page_number', 'docPageNumber', 'doc_page_numbers', 'docPageNumbers', 'document_page_number', 'documentPageNumber', 'document_page_numbers', 'documentPageNumbers', 'pdftext_page_number', 'pdftextPageNumber', 'pdftext_page_numbers', 'pdftextPageNumbers', 'source_page_number', 'sourcePageNumber', 'source_page_numbers', 'sourcePageNumbers'],
        ['selected_page_number', 'selectedPageNumber', 'selected_page_numbers', 'selectedPageNumbers', 'trimmed_page_number', 'trimmedPageNumber', 'trimmed_page_numbers', 'trimmedPageNumbers', 'relative_page_number', 'relativePageNumber', 'relative_page_numbers', 'relativePageNumbers', 'selected_page_num', 'selectedPageNum', 'selected_page_nums', 'selectedPageNums', 'trimmed_page_num', 'trimmedPageNum', 'trimmed_page_nums', 'trimmedPageNums', 'relative_page_num', 'relativePageNum', 'relative_page_nums', 'relativePageNums'],
    ];

    private LayoutOrderer $layout;
    private MarkerSettings $settings;

    public function __construct(?LayoutOrderer $layout = null, ?MarkerSettings $settings = null)
    {
        $this->layout = $layout ?? new LayoutOrderer();
        $this->settings = $settings ?? new MarkerSettings();
    }

    public function batchSize(float $batchMultiplier = 1.0): int
    {
        $configured = $this->settings->get('LAYOUT_BATCH_SIZE');
        $base = $configured !== null ? (int) $configured : 6;

        return (int) ($base * $batchMultiplier);
    }

    /**
     * Native boundary for marker.layout.layout::surya_layout. Layout model
     * predictions are supplied by the caller so this slice does not load Surya.
     *
     * @param list<mixed> $images
     * @param list<array<string, mixed>> $pages
     * @param list<array<string, mixed>|\stdClass> $layoutResults
     * @return array{
     *     pages: list<array<string, mixed>>,
     *     plan: array{image_count: int, page_count: int, detection_result_count: int, layout_result_count: int, assigned_pages: int, batch_size: int}
     * }
     */
    public function runWithSuppliedLayouts(
        array $images,
        array $pages,
        array $layoutResults,
        float $batchMultiplier = 1.0,
        array $pageRange = []
    ): array {
        $images = PdfPageArtifactSelector::normalizeSuppliedArtifacts($images);
        $pages = array_values($pages);
        $layoutResults = PdfPageArtifactSelector::normalizeSuppliedArtifacts($layoutResults);
        $assignedPages = 0;
        $assignmentSlots = min(count($pages), count($layoutResults));

        for ($index = 0; $index < $assignmentSlots; $index++) {
            if (PdfPageArtifactSelector::isMissingPageArtifact($layoutResults[$index])) {
                continue;
            }
            if (!is_array($layoutResults[$index])) {
                throw new InvalidArgumentException('Supplied layout predictions must be arrays.');
            }
            $sourceIndex = $this->integerValue($pageRange[$index] ?? null) ?? $index;
            if (
                $this->hasAmbiguousLayoutPayloadWrapper($layoutResults[$index])
                || $this->hasMalformedLayoutPageMarkers($layoutResults[$index])
                || $this->hasAmbiguousDirectLayoutPayloadEnvelope($layoutResults[$index], $pages[$index], $index, $sourceIndex)
            ) {
                continue;
            }
            if (!$this->layoutResultPayloadMatchesPage($layoutResults[$index], $pages[$index], $index, $sourceIndex)) {
                continue;
            }
            $pages[$index]['layout'] = $this->sanitizeSuppliedLayoutResult(
                $layoutResults[$index],
                $pages[$index],
                $index,
                $sourceIndex
            );
            $assignedPages++;
        }

        return [
            'pages' => $pages,
            'plan' => [
                'image_count' => PdfPageArtifactSelector::countPresentArtifacts($images),
                'page_count' => count($pages),
                'detection_result_count' => count(array_filter(
                    $pages,
                    static fn (array $page): bool => array_key_exists('text_lines', $page)
                )),
                'layout_result_count' => PdfPageArtifactSelector::countPresentArtifacts($layoutResults),
                'assigned_pages' => $assignedPages,
                'batch_size' => $this->batchSize($batchMultiplier),
            ],
        ];
    }

    /**
     * @param array<string, mixed> $layoutResult
     * @param array<string, mixed>|null $page
     */
    private function layoutResultPayloadMatchesPage(array $layoutResult, ?array $page, int $selectedIndex, ?int $sourceIndex): bool
    {
        $payload = $this->layoutResultPayloadSource($layoutResult, $page, $selectedIndex, $sourceIndex);
        $sources = $this->layoutResultPageMarkerSources($payload);
        $envelopePageKeys = $this->integerFieldsFromSources($sources, [self::ENVELOPE_PAGE_KEY_MARKER]);
        if ($envelopePageKeys === []) {
            return true;
        }

        $sourceIndex ??= $selectedIndex;
        $pageNumber = $this->pageMarkerNumber($page) ?? $sourceIndex;
        foreach ($envelopePageKeys as $marker) {
            if (
                $marker !== $sourceIndex
                && $marker !== $pageNumber
                && $marker !== $pageNumber + 1
            ) {
                return false;
            }
        }

        return true;
    }

    /**
     * Supplied adapters sometimes wrap selected page identity and pdftext page
     * copies around the model payload. Marker layout metadata only needs the
     * geometry and scalar page markers before annotation.
     *
     * @param array<string, mixed> $layoutResult
     * @return array<string, mixed>
     */
    private function sanitizeSuppliedLayoutResult(
        array $layoutResult,
        ?array $page = null,
        int $selectedIndex = 0,
        ?int $sourceIndex = null
    ): array
    {
        $sanitized = [];
        $payload = $this->layoutResultPayloadSource($layoutResult, $page, $selectedIndex, $sourceIndex);

        $hasImageBbox = array_key_exists('image_bbox', $payload);
        $imageBbox = $this->bboxFromRecordField($payload, 'image_bbox', [
            'image_bbox_order',
            'image_bbox_coordinate_order',
            'image_bbox_coordinate_format',
            'image_bbox_format',
            'image_coordinate_order',
            'image_coordinate_format',
        ], false);
        $hasUsableImageBbox = $imageBbox !== null && $this->rectWidth($imageBbox) > 0.0 && $this->rectHeight($imageBbox) > 0.0;
        if ($hasUsableImageBbox) {
            $sanitized['image_bbox'] = $imageBbox;
        }

        if (array_key_exists('bboxes', $payload)) {
            $sanitized['bboxes'] = $this->sanitizeSuppliedLayoutBboxes(
                $payload['bboxes'],
                $page,
                $selectedIndex,
                $sourceIndex,
                $hasImageBbox && !$hasUsableImageBbox,
                $payload
            );
        }

        foreach ($this->layoutResultPageMarkerSources($layoutResult) as $source) {
            foreach (self::LAYOUT_RESULT_PAGE_MARKER_KEYS as $key) {
                if (!array_key_exists($key, $source) || array_key_exists($key, $sanitized)) {
                    continue;
                }

                $value = $this->integerValue($source[$key]);
                if ($value !== null) {
                    $sanitized[$key] = $value;
                }
            }
        }

        return $sanitized;
    }

    /**
     * @param mixed $boxes
     * @return list<array{label: string, bbox: list<float>}>
     */
    private function sanitizeSuppliedLayoutBboxes(
        mixed $boxes,
        ?array $page = null,
        int $selectedIndex = 0,
        ?int $sourceIndex = null,
        bool $rejectNormalizedBboxesWithoutImageExtent = false,
        ?array $sharedBboxOrderSource = null
    ): array
    {
        if (!is_array($boxes) || !array_is_list($boxes)) {
            return [];
        }

        $sanitized = [];
        foreach ($boxes as $box) {
            if (!is_array($box)) {
                continue;
            }
            if (!$this->pageMarkerSourcesMatchPage($this->layoutResultPageMarkerSources($box), $page, $selectedIndex, $sourceIndex)) {
                continue;
            }

            $label = (string) ($box['label'] ?? '');
            $bbox = $label === 'Table'
                ? $this->tableRegionBboxWithSharedCoordinateOrder($box, $sharedBboxOrderSource)
                : $this->bboxWithSharedCoordinateOrder($box, $sharedBboxOrderSource);
            if ($bbox === null || $this->rectWidth($bbox) <= 0.0 || $this->rectHeight($bbox) <= 0.0) {
                continue;
            }
            if ($rejectNormalizedBboxesWithoutImageExtent && $this->isNormalizedLayoutBbox($bbox)) {
                continue;
            }

            $sanitized[] = [
                'label' => $label,
                'bbox' => $bbox,
            ];
        }

        return $sanitized;
    }

    /**
     * @param array<string, mixed> $box
     * @param array<string, mixed>|null $sharedBboxOrderSource
     * @return list<float>|null
     */
    private function tableRegionBboxWithSharedCoordinateOrder(array $box, ?array $sharedBboxOrderSource): ?array
    {
        return $this->polygonBbox($box['polygon'] ?? null)
            ?? $this->bboxWithSharedCoordinateOrder($box, $sharedBboxOrderSource);
    }

    /**
     * @param array<string, mixed> $box
     * @param array<string, mixed>|null $sharedBboxOrderSource
     * @return list<float>|null
     */
    private function bboxWithSharedCoordinateOrder(array $box, ?array $sharedBboxOrderSource): ?array
    {
        if ($this->bboxCoordinateOrder($box) !== null) {
            return $this->bbox($box);
        }

        $sharedOrder = $sharedBboxOrderSource === null
            ? null
            : $this->bboxCoordinateOrder($sharedBboxOrderSource, [
                'bboxes_bbox_order',
                'bboxes_coordinate_order',
                'bboxes_coordinate_format',
                'bboxes_bbox_coordinate_order',
                'bboxes_bbox_coordinate_format',
                'bboxes_bbox_format',
                'layout_bboxes_bbox_order',
                'layout_bboxes_coordinate_order',
                'layout_bboxes_coordinate_format',
                'layout_bboxes_bbox_coordinate_order',
                'layout_bboxes_bbox_coordinate_format',
                'layout_bboxes_bbox_format',
            ]);

        if ($sharedOrder !== null && isset($box['bbox']) && is_array($box['bbox'])) {
            $bbox = $this->bboxFromCoordinateList($box['bbox'], $sharedOrder);
            if ($bbox !== null) {
                return $bbox;
            }
        }

        return $this->bbox($box);
    }

    /**
     * Row-level page markers are adapter metadata. Upstream layout detections
     * are zipped with the selected page after document trimming, so mixed cached
     * row payloads from another source page must not annotate current blocks.
     *
     * @param list<array<string, mixed>> $sources
     */
    private function pageMarkerSourcesMatchPage(array $sources, ?array $page, int $selectedIndex, ?int $sourceIndex): bool
    {
        $hasMarkers = false;
        foreach ($sources as $source) {
            if (($source[self::AMBIGUOUS_PAGE_MARKER_WRAPPER] ?? false) === true || $this->layoutResultPageMarkerSourceHasMalformedMarkers($source)) {
                return false;
            }

            if ($this->integerFields($source, [self::ENVELOPE_PAGE_KEY_MARKER]) !== []) {
                $hasMarkers = true;
            }
            foreach (self::LAYOUT_RESULT_PAGE_MARKER_FIELD_GROUPS as $fields) {
                if ($this->integerFields($source, $fields) !== []) {
                    $hasMarkers = true;
                }
            }
        }

        if (!$hasMarkers) {
            return true;
        }

        $sourceIndex ??= $selectedIndex;
        $pageNumber = $this->pageMarkerNumber($page) ?? $sourceIndex;

        foreach ($this->integerFieldsFromSources($sources, self::LAYOUT_RESULT_PAGE_MARKER_FIELD_GROUPS[0]) as $marker) {
            if ($marker !== $sourceIndex) {
                return false;
            }
        }
        foreach ($this->integerFieldsFromSources($sources, self::LAYOUT_RESULT_PAGE_MARKER_FIELD_GROUPS[1]) as $marker) {
            if ($marker !== $selectedIndex) {
                return false;
            }
        }
        foreach ($this->integerFieldsFromSources($sources, self::LAYOUT_RESULT_PAGE_MARKER_FIELD_GROUPS[2]) as $marker) {
            if ($marker !== $pageNumber) {
                return false;
            }
        }
        foreach ($this->integerFieldsFromSources($sources, self::LAYOUT_RESULT_PAGE_MARKER_FIELD_GROUPS[3]) as $marker) {
            if ($marker !== $pageNumber + 1) {
                return false;
            }
        }
        foreach ($this->integerFieldsFromSources($sources, self::LAYOUT_RESULT_PAGE_MARKER_FIELD_GROUPS[4]) as $marker) {
            if ($marker !== $selectedIndex + 1) {
                return false;
            }
        }
        foreach ($this->integerFieldsFromSources($sources, [self::ENVELOPE_PAGE_KEY_MARKER]) as $marker) {
            if (
                $marker !== $sourceIndex
                && $marker !== $pageNumber
            ) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param array<string, mixed>|null $page
     */
    private function pageMarkerNumber(?array $page): ?int
    {
        if ($page === null) {
            return null;
        }

        $direct = $this->integerValue($page['pnum'] ?? $page['page'] ?? null);
        if ($direct !== null) {
            return $direct;
        }

        $source = $page['pdftext_source'] ?? null;
        return is_array($source) ? $this->integerValue($source['page'] ?? null) : null;
    }

    /**
     * Adapter serializers may wrap the Surya `LayoutResult` object under a
     * typed result key while keeping page identity at the outer level.
     *
     * @param array<string, mixed> $layoutResult
     * @return array<string, mixed>
     */
    private function layoutResultPayloadSource(
        array $layoutResult,
        ?array $page = null,
        int $selectedIndex = 0,
        ?int $sourceIndex = null
    ): array
    {
        $sources = [];
        $this->collectLayoutResultPayloadSources($layoutResult, $sources);

        foreach ($sources as $source) {
            if ($this->hasLayoutPayload($source)) {
                return $source;
            }
            $directEnvelopePayload = $this->directLayoutResultPayloadEnvelopeSource($source, $page, $selectedIndex, $sourceIndex);
            if ($directEnvelopePayload !== null) {
                return $directEnvelopePayload;
            }
        }

        $directEnvelopePayload = $this->directLayoutResultPayloadEnvelopeSource($layoutResult, $page, $selectedIndex, $sourceIndex);
        if ($directEnvelopePayload !== null) {
            return $directEnvelopePayload;
        }

        return $layoutResult;
    }

    /**
     * Cached supplied artifacts sometimes keep selected page identity at the
     * top level and store layout-result payloads inside a pdftext-like
     * pages/dictionary_output/pdftext envelope. Singleton payloads are accepted
     * directly. Source-page keyed maps are accepted only when exactly one
     * candidate matches the selected page identity.
     *
     * @param array<string, mixed> $layoutResult
     * @return array<string, mixed>|null
     */
    private function directLayoutResultPayloadEnvelopeSource(
        array $layoutResult,
        ?array $page = null,
        int $selectedIndex = 0,
        ?int $sourceIndex = null
    ): ?array
    {
        foreach (self::LAYOUT_RESULT_DIRECT_PAYLOAD_ENVELOPES as $key) {
            $value = $this->normalizeDirectLayoutResultPayloadEnvelopeValue($layoutResult[$key] ?? null);
            if (!is_array($value)) {
                continue;
            }

            $candidates = $this->directLayoutResultPayloadEnvelopeCandidates($value);
            if (count($candidates) === 1) {
                $candidate = $candidates[0];
                if ($this->hasLayoutPayload($candidate)) {
                    return $candidate;
                }

                continue;
            }

            $matched = $this->matchingDirectLayoutResultPayloadEnvelopeCandidates(
                $candidates,
                $page,
                $selectedIndex,
                $sourceIndex
            );
            if (count($matched) === 1) {
                return $matched[0];
            }
        }

        return null;
    }

    /**
     * A typed `layout_result` wrapper can carry a trusted outer page marker
     * while its direct pdftext-style payload envelope contains several
     * unmarked layout predictions. Marker receives one layout result per
     * selected page, so the native adapter must fail closed unless one inner
     * payload is selected unambiguously.
     *
     * @param array<string, mixed> $layoutResult
     */
    private function hasAmbiguousDirectLayoutPayloadEnvelope(
        array $layoutResult,
        ?array $page,
        int $selectedIndex,
        ?int $sourceIndex
    ): bool {
        $sources = [];
        $this->collectLayoutResultPayloadSources($layoutResult, $sources);

        foreach ($sources as $source) {
            foreach (self::LAYOUT_RESULT_DIRECT_PAYLOAD_ENVELOPES as $key) {
                $value = $this->normalizeDirectLayoutResultPayloadEnvelopeValue($source[$key] ?? null);
                if (!is_array($value)) {
                    continue;
                }

                $candidates = array_values(array_filter(
                    $this->directLayoutResultPayloadEnvelopeCandidates($value),
                    fn (array $candidate): bool => $this->hasLayoutPayload($candidate)
                ));
                if (count($candidates) <= 1) {
                    continue;
                }

                $matched = $this->matchingDirectLayoutResultPayloadEnvelopeCandidates(
                    $candidates,
                    $page,
                    $selectedIndex,
                    $sourceIndex
                );
                if (count($matched) !== 1) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * @param list<array<string, mixed>> $candidates
     * @return list<array<string, mixed>>
     */
    private function matchingDirectLayoutResultPayloadEnvelopeCandidates(
        array $candidates,
        ?array $page,
        int $selectedIndex,
        ?int $sourceIndex
    ): array {
        if ($candidates === []) {
            return [];
        }

        $markedMatches = [];
        $unmarkedMatches = [];
        foreach ($candidates as $candidate) {
            if (!$this->hasLayoutPayload($candidate)) {
                continue;
            }
            $sources = $this->layoutResultPageMarkerSources($candidate);
            if (!$this->pageMarkerSourcesMatchPage($sources, $page, $selectedIndex, $sourceIndex)) {
                continue;
            }

            if ($this->layoutResultPageMarkerSourcesHaveMarkers($sources)) {
                $markedMatches[] = $candidate;
                continue;
            }

            $unmarkedMatches[] = $candidate;
        }

        return $markedMatches !== [] ? $markedMatches : $unmarkedMatches;
    }

    /**
     * Typed supplied-layout wrappers may preserve pdftext-style envelopes as raw
     * JSON strings. Decode only at explicit payload-envelope keys so arbitrary
     * scalar payload text remains review-only data.
     */
    private function normalizeDirectLayoutResultPayloadEnvelopeValue(mixed $value): mixed
    {
        if (is_string($value)) {
            $decoded = $this->decodeDirectLayoutResultPayloadJsonEnvelope($value);
            if ($decoded !== null) {
                return PdfPageArtifactSelector::normalizeSuppliedArtifactValue($decoded);
            }
        }

        return PdfPageArtifactSelector::normalizeSuppliedArtifactValue($value);
    }

    private function decodeDirectLayoutResultPayloadJsonEnvelope(string $value): mixed
    {
        $trimmed = trim($value);
        if (str_starts_with($trimmed, "\xEF\xBB\xBF")) {
            $trimmed = trim(substr($trimmed, 3));
        }
        if ($trimmed === '' || !in_array($trimmed[0], ['[', '{'], true)) {
            return null;
        }

        try {
            $decoded = json_decode($trimmed, false, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return null;
        }

        return is_array($decoded) || $decoded instanceof \stdClass ? $decoded : null;
    }

    /**
     * Direct payload envelopes may be serialized either as a singleton list or
     * as a source-page keyed object map. Multi-dictionary maps remain ambiguous.
     *
     * @param array<mixed> $value
     * @return list<array<string, mixed>>
     */
    private function directLayoutResultPayloadEnvelopeCandidates(array $value): array
    {
        $candidates = $this->dictionaryWrapperValues($value);
        if (count($candidates) === 1 && $this->hasLayoutPayload($candidates[0])) {
            return $candidates;
        }
        if (array_is_list($value) || $this->hasLayoutPayload($value)) {
            return $candidates;
        }

        $mapped = [];
        foreach ($value as $key => $candidate) {
            $pageKey = $this->integerValue($key);
            if (!is_array($candidate) || array_is_list($candidate)) {
                continue;
            }

            if (!$this->hasLayoutPayload($candidate)) {
                continue;
            }

            if ($pageKey !== null) {
                $candidate[self::ENVELOPE_PAGE_KEY_MARKER] = $pageKey;
            }
            $mapped[] = $candidate;
        }

        return $mapped;
    }

    /**
     * @param array<string, mixed> $artifact
     * @param list<array<string, mixed>> $sources
     */
    private function collectLayoutResultPayloadSources(array $artifact, array &$sources, int $depth = 0): void
    {
        $sources[] = $artifact;
        if ($depth >= 2) {
            return;
        }

        foreach (self::LAYOUT_RESULT_PAYLOAD_WRAPPERS as $key) {
            $value = $artifact[$key] ?? null;
            if (!is_array($value)) {
                continue;
            }

            foreach ($this->dictionaryWrapperValues($value) as $wrapperValue) {
                $this->collectLayoutResultPayloadSources($wrapperValue, $sources, $depth + 1);
            }
        }
    }

    /**
     * @param array<string, mixed> $artifact
     */
    private function hasAmbiguousLayoutPayloadWrapper(array $artifact, int $depth = 0): bool
    {
        if ($depth >= 2) {
            return false;
        }

        foreach (self::LAYOUT_RESULT_PAYLOAD_WRAPPERS as $key) {
            $value = $artifact[$key] ?? null;
            if (!is_array($value)) {
                continue;
            }

            if (array_is_list($value)) {
                $dictionaries = [];
                foreach ($value as $item) {
                    if (is_array($item) && !array_is_list($item)) {
                        $dictionaries[] = $item;
                    }
                }
                if (count($dictionaries) > 1) {
                    return true;
                }
                foreach ($dictionaries as $dictionary) {
                    if ($this->hasAmbiguousLayoutPayloadWrapper($dictionary, $depth + 1)) {
                        return true;
                    }
                }

                continue;
            }

            if ($this->hasAmbiguousLayoutPayloadWrapper($value, $depth + 1)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<string, mixed> $source
     */
    private function hasLayoutPayload(array $source): bool
    {
        return array_key_exists('image_bbox', $source) || array_key_exists('bboxes', $source);
    }

    /**
     * @param array<string, mixed> $layoutResult
     * @return list<array<string, mixed>>
     */
    private function layoutResultPageMarkerSources(array $layoutResult): array
    {
        $sources = [];
        $this->collectLayoutResultPageMarkerSources($layoutResult, $sources, 0, self::LAYOUT_RESULT_PAGE_MARKER_METADATA_WRAPPERS);

        if ($this->layoutResultPageMarkerSourcesHaveMarkers($sources)) {
            return $sources;
        }

        $sources = [];
        $this->collectLayoutResultPageMarkerSources($layoutResult, $sources, 0, self::LAYOUT_RESULT_PAGE_MARKER_WRAPPERS);

        return $sources;
    }

    /**
     * @param array<string, mixed> $artifact
     * @param list<array<string, mixed>> $sources
     * @param list<string> $wrapperKeys
     */
    private function collectLayoutResultPageMarkerSources(array $artifact, array &$sources, int $depth, array $wrapperKeys): void
    {
        $sources[] = $artifact;
        if ($depth >= 2) {
            return;
        }

        foreach ($wrapperKeys as $key) {
            $value = $artifact[$key] ?? null;
            if (!is_array($value)) {
                continue;
            }

            $wrapperValues = $this->dictionaryWrapperValues($value);
            if (array_is_list($value) && count($wrapperValues) > 1) {
                $sources[] = [self::AMBIGUOUS_PAGE_MARKER_WRAPPER => true];
                continue;
            }

            foreach ($wrapperValues as $wrapperValue) {
                $this->collectLayoutResultPageMarkerSources($wrapperValue, $sources, $depth + 1, $wrapperKeys);
            }
        }
    }

    /**
     * @param array<mixed> $value
     * @return list<array<string, mixed>>
     */
    private function dictionaryWrapperValues(array $value): array
    {
        if (!array_is_list($value)) {
            return [$value];
        }

        $dictionaries = [];
        foreach ($value as $item) {
            if (is_array($item) && !array_is_list($item)) {
                $dictionaries[] = $item;
            }
        }

        return $dictionaries;
    }

    /**
     * A nested pdftext dictionary is usually a copied page payload. Use its page
     * markers only as a fallback when adapter metadata has no page identity.
     * Typed layout payload wrappers follow the same rule so stale model payload
     * page markers cannot override trusted adapter metadata.
     *
     * @param list<array<string, mixed>> $sources
     */
    private function layoutResultPageMarkerSourcesHaveMarkers(array $sources): bool
    {
        foreach ($sources as $source) {
            if (($source[self::AMBIGUOUS_PAGE_MARKER_WRAPPER] ?? false) === true || $this->layoutResultPageMarkerSourceHasMalformedMarkers($source)) {
                return true;
            }

            if ($this->integerFields($source, [self::ENVELOPE_PAGE_KEY_MARKER]) !== []) {
                return true;
            }
            foreach (self::LAYOUT_RESULT_PAGE_MARKER_FIELD_GROUPS as $fields) {
                if ($this->integerFields($source, $fields) !== []) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * @param array<string, mixed> $layoutResult
     */
    private function hasMalformedLayoutPageMarkers(array $layoutResult): bool
    {
        foreach ($this->layoutResultPageMarkerSources($layoutResult) as $source) {
            if ($this->layoutResultPageMarkerSourceHasMalformedMarkers($source)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<string, mixed> $source
     */
    private function layoutResultPageMarkerSourceHasMalformedMarkers(array $source): bool
    {
        foreach (self::LAYOUT_RESULT_PAGE_MARKER_FIELD_GROUPS as $fields) {
            foreach ($fields as $field) {
                if (!array_key_exists($field, $source)) {
                    continue;
                }

                if (!$this->isValidPageMarkerValue($source[$field])) {
                    return true;
                }
            }
        }

        return false;
    }

    private function isValidPageMarkerValue(mixed $value): bool
    {
        if ($this->integerValue($value) !== null) {
            return true;
        }

        if (!is_array($value) || !array_is_list($value) || $value === []) {
            return false;
        }

        foreach ($value as $item) {
            if ($this->integerValue($item) === null) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param array<string, mixed> $artifact
     * @param list<string> $fields
     * @return list<int>
     */
    private function integerFields(array $artifact, array $fields): array
    {
        $values = [];
        foreach ($fields as $field) {
            if (!array_key_exists($field, $artifact)) {
                continue;
            }

            array_push($values, ...$this->integerValues($artifact[$field]));
        }

        return array_values(array_unique($values, SORT_REGULAR));
    }

    /**
     * @param list<array<string, mixed>> $sources
     * @param list<string> $fields
     * @return list<int>
     */
    private function integerFieldsFromSources(array $sources, array $fields): array
    {
        $values = [];
        foreach ($sources as $source) {
            array_push($values, ...$this->integerFields($source, $fields));
        }

        return array_values(array_unique($values, SORT_REGULAR));
    }

    private function integerValue(mixed $value): ?int
    {
        if (is_int($value)) {
            return $value;
        }

        if (is_float($value)) {
            if (!is_finite($value)) {
                return null;
            }
            if (floor($value) === $value) {
                return (int) $value;
            }
        }

        if (is_string($value)) {
            $trimmed = trim($value);
            if (preg_match('/^[+-]?\d+$/', $trimmed) === 1) {
                return (int) $trimmed;
            }
            if (preg_match('/^[+-]?\d+\.0+$/', $trimmed) === 1) {
                return (int) $trimmed;
            }
        }

        if (is_array($value) && array_is_list($value)) {
            $values = [];
            foreach ($value as $item) {
                $integer = $this->integerValue($item);
                if ($integer !== null) {
                    $values[] = $integer;
                }
            }
            $values = array_values(array_unique($values, SORT_REGULAR));

            return count($values) === 1 ? $values[0] : null;
        }

        return null;
    }

    /**
     * @return list<int>
     */
    private function integerValues(mixed $value): array
    {
        $single = $this->integerValue($value);
        if ($single !== null) {
            return [$single];
        }

        if (!is_array($value) || !array_is_list($value)) {
            return [];
        }

        $values = [];
        foreach ($value as $item) {
            $integer = $this->integerValue($item);
            if ($integer !== null) {
                $values[] = $integer;
            }
        }

        return array_values(array_unique($values, SORT_REGULAR));
    }

    /**
     * Native boundary for marker.layout.layout::annotate_block_types.
     *
     * @param list<array<string, mixed>> $pages
     * @return list<array<string, mixed>>
     */
    public function annotateBlockTypes(array $pages, string $defaultBlockType = 'Text'): array
    {
        foreach ($pages as $pageIndex => $page) {
            $blocks = array_values(array_filter(
                $page['blocks'] ?? [],
                static fn (mixed $block): bool => is_array($block)
            ));
            $layoutBoxes = $this->layoutBoxes($page);
            $maxIntersections = [];

            foreach ($blocks as $blockIndex => $block) {
                foreach ($layoutBoxes as $layoutIndex => $layoutBox) {
                    $layoutBbox = $this->rescaleLayoutBbox($page, $layoutBox['bbox']);
                    $intersection = $this->layout->intersectionPct($this->blockBbox($block), $layoutBbox);

                    if (!isset($maxIntersections[$blockIndex]) || $intersection > $maxIntersections[$blockIndex]['intersection']) {
                        $maxIntersections[$blockIndex] = [
                            'intersection' => $intersection,
                            'layout_index' => $layoutIndex,
                            'label' => $layoutBox['label'],
                        ];
                    }
                }
            }

            foreach ($blocks as $blockIndex => $block) {
                $blockType = null;
                if (
                    isset($maxIntersections[$blockIndex])
                    && $maxIntersections[$blockIndex]['intersection'] > 0.0
                ) {
                    $blockType = $maxIntersections[$blockIndex]['label'];
                }
                $blocks[$blockIndex] = $this->withBlockType($block, $blockType);
            }

            foreach ($blocks as $blockIndex => $block) {
                if ($this->blockType($block) !== null) {
                    continue;
                }

                $closestBlockIndex = null;
                $closestDistance = null;
                foreach ($blocks as $otherIndex => $otherBlock) {
                    if ($otherIndex === $blockIndex || $this->blockType($otherBlock) === null) {
                        continue;
                    }

                    $distances = [$this->distance($this->blockBbox($block), $this->blockBbox($otherBlock))];
                    foreach (($otherBlock['lines'] ?? []) as $line) {
                        if (!is_array($line)) {
                            continue;
                        }
                        $distances[] = $this->distance($this->blockBbox($block), $this->lineBbox($line));
                    }

                    $distance = min($distances);
                    if ($closestBlockIndex === null || $closestDistance === null || $distance < $closestDistance) {
                        $closestBlockIndex = $otherIndex;
                        $closestDistance = $distance;
                    }
                }

                if ($closestBlockIndex !== null) {
                    $blocks[$blockIndex] = $this->withBlockType($block, $this->blockType($blocks[$closestBlockIndex]));
                }
            }

            foreach ($blocks as $blockIndex => $block) {
                if ($this->blockType($block) === null) {
                    $blocks[$blockIndex] = $this->withBlockType($block, $defaultBlockType);
                }
            }

            $page['blocks'] = $this->mergeIntersectingBlocks($blocks, $maxIntersections);
            $pages[$pageIndex] = $page;
        }

        return $pages;
    }

    /**
     * @param array<string, mixed> $page
     * @return list<array{label: string, bbox: list<float>}>
     */
    private function layoutBoxes(array $page): array
    {
        $boxes = [];
        $layout = $page['layout'] ?? [];
        if (is_array($layout) && isset($layout['bboxes']) && is_array($layout['bboxes'])) {
            $boxes = $layout['bboxes'];
        } elseif (isset($page['layout_boxes']) && is_array($page['layout_boxes'])) {
            $boxes = $page['layout_boxes'];
        }

        $layoutBoxes = [];
        foreach ($boxes as $box) {
            if (!is_array($box)) {
                continue;
            }
            $label = (string) ($box['label'] ?? '');
            $bbox = $label === 'Table'
                ? $this->tableRegionBbox($box)
                : $this->bbox($box);
            if ($bbox === null) {
                continue;
            }

            $layoutBoxes[] = [
                'label' => $label,
                'bbox' => $bbox,
            ];
        }

        return $layoutBoxes;
    }

    /**
     * @param array<string, mixed> $box
     * @return list<float>|null
     */
    private function tableRegionBbox(array $box): ?array
    {
        return $this->polygonBbox($box['polygon'] ?? null)
            ?? $this->bbox($box);
    }

    /**
     * @param array<string, mixed> $page
     * @param list<float> $bbox
     * @return list<float>
     */
    private function rescaleLayoutBbox(array $page, array $bbox): array
    {
        $layout = $page['layout'] ?? [];
        $imageBbox = is_array($layout) ? $this->bbox($layout['image_bbox'] ?? null) : null;
        $pageBbox = $this->bbox($page['bbox'] ?? null);

        if ($imageBbox === null || $pageBbox === null) {
            return $bbox;
        }

        $bbox = $this->expandNormalizedLayoutBbox($bbox, $imageBbox);

        return $this->layout->rescaleBbox($imageBbox, $pageBbox, $bbox);
    }

    /**
     * @param list<float> $bbox
     * @param list<float> $imageBbox
     * @return list<float>
     */
    private function expandNormalizedLayoutBbox(array $bbox, array $imageBbox): array
    {
        $imageWidth = $this->rectWidth($imageBbox);
        $imageHeight = $this->rectHeight($imageBbox);
        if ($imageWidth <= 2.0 || $imageHeight <= 2.0 || !$this->isNormalizedLayoutBbox($bbox)) {
            return $bbox;
        }

        return $this->canonicalBbox([
            $imageBbox[0] + ($bbox[0] * $imageWidth),
            $imageBbox[1] + ($bbox[1] * $imageHeight),
            $imageBbox[0] + ($bbox[2] * $imageWidth),
            $imageBbox[1] + ($bbox[3] * $imageHeight),
        ]);
    }

    /**
     * @param list<float> $bbox
     */
    private function isNormalizedLayoutBbox(array $bbox): bool
    {
        foreach ($bbox as $part) {
            if ($part < -0.5 || $part > 1.5) {
                return false;
            }
        }

        return $this->rectWidth($bbox) <= 2.0 && $this->rectHeight($bbox) <= 2.0;
    }

    /**
     * @param list<float> $bbox
     */
    private function rectWidth(array $bbox): float
    {
        return max(0.0, $bbox[2] - $bbox[0]);
    }

    /**
     * @param list<float> $bbox
     */
    private function rectHeight(array $bbox): float
    {
        return max(0.0, $bbox[3] - $bbox[1]);
    }

    /**
     * @param list<array<string, mixed>> $blocks
     * @param array<int, array{intersection: float, layout_index: int, label: string}> $maxIntersections
     * @return list<array<string, mixed>>
     */
    private function mergeIntersectingBlocks(array $blocks, array $maxIntersections): array
    {
        $newBlocks = [];
        $currentLayoutIndex = null;
        $currentBlock = null;
        $currentLabels = [];

        foreach ($blocks as $blockIndex => $block) {
            $intersection = $maxIntersections[$blockIndex] ?? null;
            if ($intersection === null || $intersection['intersection'] == 0.0) {
                if ($currentBlock !== null) {
                    $newBlocks[] = $this->mergedBlock($currentBlock, $currentLabels);
                }
                $currentLayoutIndex = null;
                $currentBlock = null;
                $currentLabels = [];
                $newBlocks[] = $block;
                continue;
            }

            if ($intersection['layout_index'] !== $currentLayoutIndex) {
                if ($currentBlock !== null) {
                    $newBlocks[] = $this->mergedBlock($currentBlock, $currentLabels);
                }
                $currentBlock = $block;
                $currentLayoutIndex = $intersection['layout_index'];
                $currentLabels = [(string) $this->blockType($block)];
                continue;
            }

            $currentBlock['lines'] = array_merge(
                is_array($currentBlock['lines'] ?? null) ? $currentBlock['lines'] : [],
                is_array($block['lines'] ?? null) ? $block['lines'] : []
            );
            $currentLabels[] = (string) $this->blockType($block);
        }

        if ($currentBlock !== null) {
            $newBlocks[] = $this->mergedBlock($currentBlock, $currentLabels);
        }

        return $newBlocks;
    }

    /**
     * @param array<string, mixed> $block
     * @param list<string> $labels
     * @return array<string, mixed>
     */
    private function mergedBlock(array $block, array $labels): array
    {
        $block['bbox'] = $this->bboxFromLines($block['lines'] ?? []);
        $label = $this->mostCommonLabel($labels);
        $block['block_type'] = $label;
        $block['type'] = $label;

        return $block;
    }

    /**
     * @param list<string> $labels
     */
    private function mostCommonLabel(array $labels): string
    {
        $counts = [];
        foreach ($labels as $label) {
            $counts[$label] = ($counts[$label] ?? 0) + 1;
        }

        arsort($counts, SORT_NUMERIC);

        return (string) array_key_first($counts);
    }

    /**
     * @param array<string, mixed> $block
     */
    private function withBlockType(array $block, ?string $blockType): array
    {
        if ($blockType === null) {
            unset($block['block_type'], $block['type']);
            return $block;
        }

        $block['block_type'] = $blockType;
        $block['type'] = $blockType;

        return $block;
    }

    /**
     * @param array<string, mixed> $block
     */
    private function blockType(array $block): ?string
    {
        if (array_key_exists('block_type', $block) && $block['block_type'] !== null) {
            return (string) $block['block_type'];
        }
        if (array_key_exists('type', $block) && $block['type'] !== null) {
            return (string) $block['type'];
        }

        return null;
    }

    /**
     * @param array<string, mixed> $block
     * @return list<float>
     */
    private function blockBbox(array $block): array
    {
        return $this->bbox($block['bbox'] ?? null) ?? $this->bboxFromLines($block['lines'] ?? []);
    }

    /**
     * @param list<mixed> $lines
     * @return list<float>
     */
    private function bboxFromLines(array $lines): array
    {
        $boxes = [];
        foreach ($lines as $line) {
            if (!is_array($line)) {
                continue;
            }
            $bbox = $this->lineBbox($line);
            if ($bbox !== [0.0, 0.0, 0.0, 0.0]) {
                $boxes[] = $bbox;
            }
        }

        if ($boxes === []) {
            return [0.0, 0.0, 0.0, 0.0];
        }

        return [
            min(array_column($boxes, 0)),
            min(array_column($boxes, 1)),
            max(array_column($boxes, 2)),
            max(array_column($boxes, 3)),
        ];
    }

    /**
     * @param array<string, mixed> $line
     * @return list<float>
     */
    private function lineBbox(array $line): array
    {
        return $this->bbox($line['bbox'] ?? null) ?? [0.0, 0.0, 0.0, 0.0];
    }

    /**
     * @param list<float> $left
     * @param list<float> $right
     */
    private function distance(array $left, array $right): float
    {
        $leftCenter = [($left[0] + $left[2]) / 2.0, ($left[1] + $left[3]) / 2.0];
        $rightCenter = [($right[0] + $right[2]) / 2.0, ($right[1] + $right[3]) / 2.0];

        return sqrt(($leftCenter[0] - $rightCenter[0]) ** 2 + ($leftCenter[1] - $rightCenter[1]) ** 2);
    }

    /**
     * @param mixed $value
     * @return list<float>|null
     */
    private function bbox(mixed $value): ?array
    {
        if (!is_array($value)) {
            return null;
        }

        if (array_key_exists('bbox', $value)) {
            return $this->bboxFromRecordField($value, 'bbox')
                ?? $this->bboxFromNamedFields($value)
                ?? $this->polygonAliasBbox($value)
                ?? $this->polygonBbox($value);
        }

        return $this->bboxFromNamedFields($value)
            ?? $this->polygonAliasBbox($value)
            ?? $this->polygonBbox($value)
            ?? $this->bboxFromCoordinateList($value);
    }

    /**
     * @param array<string, mixed> $record
     * @param list<string> $orderKeys
     * @return list<float>|null
     */
    private function bboxFromRecordField(
        array $record,
        string $field,
        array $orderKeys = [],
        bool $includeGenericOrderKeys = true
    ): ?array
    {
        if (!array_key_exists($field, $record)) {
            return null;
        }

        $value = $record[$field];
        if (is_array($value)) {
            $order = $this->bboxCoordinateOrder($record, $orderKeys, $includeGenericOrderKeys);
            if ($order !== null) {
                $bbox = $this->bboxFromCoordinateList($value, $order);
                if ($bbox !== null) {
                    return $bbox;
                }
            }
        }

        return $this->bbox($value);
    }

    /**
     * @param mixed $bbox
     * @return list<float>|null
     */
    private function bboxFromCoordinateList(mixed $bbox, ?string $coordinateOrder = null): ?array
    {
        if (!is_array($bbox) || count($bbox) !== 4) {
            return null;
        }

        $coordinates = $this->numericCoordinates(array_values($bbox));
        if ($coordinates === null) {
            return null;
        }
        if ($coordinateOrder !== null) {
            $coordinates = $this->applyBboxCoordinateOrder($coordinates, $coordinateOrder);
        }

        return $this->canonicalBbox($coordinates);
    }

    /**
     * @param array<string, mixed> $record
     * @param list<string> $preferredKeys
     */
    private function bboxCoordinateOrder(
        array $record,
        array $preferredKeys = [],
        bool $includeGenericKeys = true
    ): ?string
    {
        $keys = $preferredKeys;
        if ($includeGenericKeys) {
            $keys = [
                ...$keys,
                'bbox_order',
                'bbox_coordinate_order',
                'bbox_coordinate_format',
                'bbox_format',
                'coordinate_order',
                'coordinate_format',
            ];
        }
        $keys = array_values(array_unique($keys));

        foreach ($keys as $key) {
            if (!isset($record[$key]) || !is_scalar($record[$key])) {
                continue;
            }

            $order = $this->canonicalBboxCoordinateOrder((string) $record[$key]);
            if ($order !== null) {
                return $order;
            }
        }

        return null;
    }

    private function canonicalBboxCoordinateOrder(string $order): ?string
    {
        $normalized = strtolower(trim($order));
        $normalized = preg_replace('/[^a-z0-9]+/', '_', $normalized) ?? $normalized;
        $normalized = trim($normalized, '_');

        return match ($normalized) {
            'xyxy',
            'x1_y1_x2_y2',
            'x0_y0_x1_y1',
            'xmin_ymin_xmax_ymax',
            'x_min_y_min_x_max_y_max',
            'left_top_right_bottom' => 'xyxy',
            'xxyy',
            'x1_x2_y1_y2',
            'x0_x1_y0_y1',
            'xmin_xmax_ymin_ymax',
            'x_min_x_max_y_min_y_max',
            'left_right_top_bottom' => 'xxyy',
            'yxyx',
            'y1_x1_y2_x2',
            'y0_x0_y1_x1',
            'ymin_xmin_ymax_xmax',
            'y_min_x_min_y_max_x_max',
            'top_left_bottom_right' => 'yxyx',
            'yyxx',
            'y1_y2_x1_x2',
            'y0_y1_x0_x1',
            'ymin_ymax_xmin_xmax',
            'y_min_y_max_x_min_x_max',
            'top_bottom_left_right' => 'yyxx',
            default => null,
        };
    }

    /**
     * @param list<float> $raw
     * @return list<float>
     */
    private function applyBboxCoordinateOrder(array $raw, string $order): array
    {
        return match ($order) {
            'xxyy' => [$raw[0], $raw[2], $raw[1], $raw[3]],
            'yxyx' => [$raw[1], $raw[0], $raw[3], $raw[2]],
            'yyxx' => [$raw[2], $raw[0], $raw[3], $raw[1]],
            default => $raw,
        };
    }

    /**
     * @param array<string, mixed> $record
     * @return list<float>|null
     */
    private function bboxFromNamedFields(array $record): ?array
    {
        foreach ([
            ['x1', 'y1', 'x2', 'y2'],
            ['x_start', 'y_start', 'x_end', 'y_end'],
            ['left', 'top', 'right', 'bottom'],
        ] as $keys) {
            [$x1, $y1, $x2, $y2] = $keys;
            if (
                !array_key_exists($x1, $record)
                || !array_key_exists($y1, $record)
                || !array_key_exists($x2, $record)
                || !array_key_exists($y2, $record)
            ) {
                continue;
            }

            $coordinates = $this->numericCoordinates([$record[$x1], $record[$y1], $record[$x2], $record[$y2]]);
            if ($coordinates !== null) {
                return $this->canonicalBbox($coordinates);
            }
        }

        foreach ([
            ['x', 'y', 'width', 'height'],
            ['x0', 'y0', 'width', 'height'],
            ['left', 'top', 'width', 'height'],
            ['x', 'y', 'w', 'h'],
            ['x0', 'y0', 'w', 'h'],
            ['left', 'top', 'w', 'h'],
        ] as $keys) {
            [$x, $y, $width, $height] = $keys;
            if (
                !array_key_exists($x, $record)
                || !array_key_exists($y, $record)
                || !array_key_exists($width, $record)
                || !array_key_exists($height, $record)
            ) {
                continue;
            }

            $coordinates = $this->numericCoordinates([$record[$x], $record[$y], $record[$width], $record[$height]]);
            if ($coordinates !== null) {
                return $this->canonicalBbox([
                    $coordinates[0],
                    $coordinates[1],
                    $coordinates[0] + $coordinates[2],
                    $coordinates[1] + $coordinates[3],
                ]);
            }
        }

        return $this->bboxFromPointPairFields($record);
    }

    /**
     * @param array<string, mixed> $record
     * @return list<float>|null
     */
    private function bboxFromPointPairFields(array $record): ?array
    {
        foreach ([
            ['top_left', 'bottom_right'],
            ['upper_left', 'lower_right'],
            ['top_right', 'bottom_left'],
            ['upper_right', 'lower_left'],
            ['tl', 'br'],
            ['tr', 'bl'],
        ] as $keys) {
            [$firstKey, $secondKey] = $keys;
            if (!array_key_exists($firstKey, $record) || !array_key_exists($secondKey, $record)) {
                continue;
            }

            $first = $this->pointCoordinates($record[$firstKey]);
            $second = $this->pointCoordinates($record[$secondKey]);
            if ($first !== null && $second !== null) {
                return $this->canonicalBbox([$first[0], $first[1], $second[0], $second[1]]);
            }
        }

        return null;
    }

    /**
     * Supplied layout/order sidecars can serialize Surya-style four-corner
     * geometry under the same aliases accepted by the table handoff.
     *
     * @param array<string, mixed> $record
     * @return list<float>|null
     */
    private function polygonAliasBbox(array $record): ?array
    {
        foreach ($this->polygonGeometryKeys() as $key) {
            if (!array_key_exists($key, $record)) {
                continue;
            }

            $bbox = $this->polygonBbox($record[$key]);
            if ($bbox !== null) {
                return $bbox;
            }
        }

        return null;
    }

    /**
     * @return list<string>
     */
    private function polygonGeometryKeys(): array
    {
        return [
            'polygon',
            'points',
            'vertices',
            'quad',
            'quadrilateral',
            'quadrilateral_points',
            'polygon_points',
            'polygon_vertices',
            'polygon_quad',
            'polygon_quadrilateral',
        ];
    }

    /**
     * @param mixed $polygon
     * @return list<float>|null
     */
    private function polygonBbox(mixed $polygon): ?array
    {
        if (!is_array($polygon)) {
            return null;
        }

        $values = array_values($polygon);
        if (count($values) === 8) {
            $points = [];
            for ($index = 0; $index < 8; $index += 2) {
                $x = $this->numericScalar($values[$index]);
                $y = $this->numericScalar($values[$index + 1]);
                if ($x === null || $y === null) {
                    return null;
                }
                $points[] = [$x, $y];
            }

            return $this->bboxFromPoints($points);
        }

        if (count($values) !== 4) {
            return null;
        }

        $points = [];
        foreach ($values as $point) {
            $coordinates = $this->pointCoordinates($point);
            if ($coordinates === null) {
                return null;
            }
            $points[] = $coordinates;
        }

        return $this->bboxFromPoints($points);
    }

    /**
     * @param list<array{0: float, 1: float}> $points
     * @return list<float>
     */
    private function bboxFromPoints(array $points): array
    {
        $xs = [];
        $ys = [];
        foreach ($points as $point) {
            $xs[] = $point[0];
            $ys[] = $point[1];
        }

        return [
            min($xs),
            min($ys),
            max($xs),
            max($ys),
        ];
    }

    /**
     * @return array{0: float, 1: float}|null
     */
    private function pointCoordinates(mixed $point): ?array
    {
        if (!is_array($point)) {
            return null;
        }

        foreach ($this->pointCoordinateFieldSets() as $keys) {
            [$xKey, $yKey] = $keys;
            if (!array_key_exists($xKey, $point) || !array_key_exists($yKey, $point)) {
                continue;
            }

            $x = $this->numericScalar($point[$xKey]);
            $y = $this->numericScalar($point[$yKey]);
            if ($x !== null && $y !== null) {
                return [$x, $y];
            }
        }

        if (array_is_list($point) && count($point) === 2) {
            $x = $this->numericScalar($point[0]);
            $y = $this->numericScalar($point[1]);
            if ($x !== null && $y !== null) {
                return [$x, $y];
            }
        }

        return null;
    }

    /**
     * @return list<array{0: string, 1: string}>
     */
    private function pointCoordinateFieldSets(): array
    {
        return [
            ['x', 'y'],
            ['x0', 'y0'],
            ['x1', 'y1'],
            ['xmin', 'ymin'],
            ['xmax', 'ymax'],
            ['x_min', 'y_min'],
            ['x_max', 'y_max'],
            ['x_start', 'y_start'],
            ['x_end', 'y_end'],
            ['start_x', 'start_y'],
            ['end_x', 'end_y'],
            ['left', 'top'],
            ['right', 'bottom'],
            ['right', 'top'],
            ['left', 'bottom'],
            ['cx', 'cy'],
            ['center_x', 'center_y'],
            ['x_center', 'y_center'],
        ];
    }

    /**
     * @param list<mixed> $values
     * @return list<float>|null
     */
    private function numericCoordinates(array $values): ?array
    {
        $out = [];
        foreach ($values as $value) {
            $number = $this->numericScalar($value);
            if ($number === null) {
                return null;
            }
            $out[] = $number;
        }

        return $out;
    }

    private function numericScalar(mixed $value): ?float
    {
        if (is_int($value) || is_float($value)) {
            return is_finite((float) $value) ? (float) $value : null;
        }
        if (is_string($value)) {
            $trimmed = trim($value);
            if ($trimmed !== '' && is_numeric($trimmed)) {
                $number = (float) $trimmed;

                return is_finite($number) ? $number : null;
            }
        }

        return null;
    }

    /**
     * @param list<float> $bbox
     * @return list<float>
     */
    private function canonicalBbox(array $bbox): array
    {
        return [
            min($bbox[0], $bbox[2]),
            min($bbox[1], $bbox[3]),
            max($bbox[0], $bbox[2]),
            max($bbox[1], $bbox[3]),
        ];
    }
}
