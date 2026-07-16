<?php

declare(strict_types=1);

namespace PortLibs\MarkerPDF;

use InvalidArgumentException;

final class LayoutOrderer
{
    private const AMBIGUOUS_PAGE_MARKER_WRAPPER = '__markerpdf_ambiguous_page_marker_wrapper';
    private const ENVELOPE_PAGE_KEY_MARKER = '__markerpdf_envelope_page_key_marker';
    private const ORDER_RESULT_PAGE_MARKER_KEYS = [
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
    private const ORDER_RESULT_PAGE_MARKER_METADATA_WRAPPERS = [
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
    private const ORDER_RESULT_PAGE_MARKER_WRAPPERS = [
        'metadata',
        'page_metadata',
        'page_meta',
        'page_info',
        'page_data',
        'page_result',
        'result_metadata',
        'artifact_metadata',
        'order',
        'order_result',
        'prediction',
        'result',
        'model_output',
        'output',
        'source',
        'pdftext',
    ];
    private const ORDER_RESULT_PAYLOAD_WRAPPERS = [
        'order',
        'order_result',
        'page_data',
        'page_result',
        'result_metadata',
        'artifact_metadata',
        'prediction',
        'result',
        'model_output',
        'output',
    ];
    private const ORDER_RESULT_DIRECT_PAYLOAD_ENVELOPES = [
        'pages',
        'dictionary_output',
        'pdftext',
        'page_map',
        'pageMap',
    ];
    private const ORDER_RESULT_PAGE_MARKER_FIELD_GROUPS = [
        ['page_index', 'pageIndex', 'page_idx', 'doc_page_index', 'docPageIndex', 'doc_page_idx', 'document_page_index', 'documentPageIndex', 'document_page_idx', 'source_page_index', 'sourcePageIndex', 'source_page_idx', 'page_range', 'pageRange', 'source_page_range', 'sourcePageRange', 'document_page_range', 'documentPageRange', 'page_indices', 'pageIndices', 'source_page_indices', 'sourcePageIndices', 'document_page_indices', 'documentPageIndices'],
        ['selected_page_index', 'selectedPageIndex', 'selected_page_idx', 'trimmed_page_index', 'trimmedPageIndex', 'trimmed_page_idx', 'relative_page_index', 'relativePageIndex', 'relative_page_idx', 'selected_page_range', 'selectedPageRange', 'trimmed_page_range', 'trimmedPageRange', 'relative_page_range', 'relativePageRange', 'selected_page_indices', 'selectedPageIndices', 'trimmed_page_indices', 'trimmedPageIndices', 'relative_page_indices', 'relativePageIndices'],
        ['pnum', 'pnums', 'page', 'page_id', 'pageId', 'page_ids', 'pageIds', 'pdftext_page', 'pdftextPage', 'pdftext_pages', 'pdftextPages', 'pdftext_page_id', 'pdftextPageId', 'pdftext_page_ids', 'pdftextPageIds', 'source_page', 'sourcePage', 'source_pages', 'sourcePages', 'source_page_id', 'sourcePageId', 'source_page_ids', 'sourcePageIds', 'document_page', 'documentPage', 'document_pages', 'documentPages', 'document_page_id', 'documentPageId', 'document_page_ids', 'documentPageIds', 'doc_page_id', 'docPageId', 'doc_page_ids', 'docPageIds'],
        ['page_number', 'pageNumber', 'page_numbers', 'pageNumbers', 'page_num', 'pageNum', 'page_nums', 'pageNums', 'doc_page_number', 'docPageNumber', 'doc_page_numbers', 'docPageNumbers', 'document_page_number', 'documentPageNumber', 'document_page_numbers', 'documentPageNumbers', 'pdftext_page_number', 'pdftextPageNumber', 'pdftext_page_numbers', 'pdftextPageNumbers', 'source_page_number', 'sourcePageNumber', 'source_page_numbers', 'sourcePageNumbers'],
        ['selected_page_number', 'selectedPageNumber', 'selected_page_numbers', 'selectedPageNumbers', 'trimmed_page_number', 'trimmedPageNumber', 'trimmed_page_numbers', 'trimmedPageNumbers', 'relative_page_number', 'relativePageNumber', 'relative_page_numbers', 'relativePageNumbers', 'selected_page_num', 'selectedPageNum', 'selected_page_nums', 'selectedPageNums', 'trimmed_page_num', 'trimmedPageNum', 'trimmed_page_nums', 'trimmedPageNums', 'relative_page_num', 'relativePageNum', 'relative_page_nums', 'relativePageNums'],
    ];
    private const ORDER_RESULT_POSITION_KEYS = [
        'position',
        'reading_order',
        'readingOrder',
        'order_position',
        'orderPosition',
        'order_index',
        'orderIndex',
        'rank',
        'sequence',
        'sort_order',
        'sortOrder',
    ];

    private MarkerSettings $settings;

    public function __construct(?MarkerSettings $settings = null)
    {
        $this->settings = $settings ?? new MarkerSettings();
    }

    public function batchSize(float $batchMultiplier = 1.0): int
    {
        $configured = $this->settings->get('ORDER_BATCH_SIZE');
        $base = $configured !== null ? (int) $configured : 6;

        return (int) ($base * $batchMultiplier);
    }

    /**
     * Native boundary for marker.layout.order::surya_order. The Surya ordering
     * model result is supplied by the caller, while this preserves the upstream
     * page/layout bbox planning and zip-style assignment behavior.
     *
     * @param list<mixed> $images
     * @param list<array<string, mixed>> $pages
     * @param list<array<string, mixed>|\stdClass> $orderResults
     * @return array{
     *     pages: list<array<string, mixed>>,
     *     plan: array{image_count: int, page_count: int, layout_bbox_counts: list<int>, requested_bboxes: list<list<list<float>>>, order_result_count: int, assigned_pages: int, batch_size: int, order_max_bboxes: int}
     * }
     */
    public function runWithSuppliedOrder(
        array $images,
        array $pages,
        array $orderResults,
        float $batchMultiplier = 1.0,
        array $pageRange = []
    ): array {
        $images = PdfPageArtifactSelector::normalizeSuppliedArtifacts($images);
        $pages = array_values($pages);
        $orderResults = PdfPageArtifactSelector::normalizeSuppliedArtifacts($orderResults);
        $maxBboxes = (int) $this->settings->get('ORDER_MAX_BBOXES');
        $requestedBboxes = [];
        $bboxCounts = [];

        foreach ($pages as $page) {
            $bboxes = $this->layoutBboxesForOrdering($page, $maxBboxes);
            $requestedBboxes[] = $bboxes;
            $bboxCounts[] = count($bboxes);
        }

        $assignedPages = 0;
        $assignmentSlots = min(count($pages), count($orderResults));
        for ($index = 0; $index < $assignmentSlots; $index++) {
            if (PdfPageArtifactSelector::isMissingPageArtifact($orderResults[$index])) {
                continue;
            }
            if (!is_array($orderResults[$index])) {
                throw new InvalidArgumentException('Supplied ordering predictions must be arrays.');
            }
            $sourceIndex = $this->integerValue($pageRange[$index] ?? null) ?? $index;
            if (
                $this->hasAmbiguousOrderPayloadWrapper($orderResults[$index])
                || $this->hasMalformedOrderPageMarkers($orderResults[$index])
                || $this->hasAmbiguousDirectOrderPayloadEnvelope($orderResults[$index], $pages[$index], $index, $sourceIndex)
            ) {
                continue;
            }
            if (!$this->orderResultPayloadMatchesPage($orderResults[$index], $pages[$index], $index, $sourceIndex)) {
                continue;
            }
            $pages[$index]['order'] = $this->sanitizeSuppliedOrderResult(
                $orderResults[$index],
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
                'layout_bbox_counts' => $bboxCounts,
                'requested_bboxes' => $requestedBboxes,
                'order_result_count' => PdfPageArtifactSelector::countPresentArtifacts($orderResults),
                'assigned_pages' => $assignedPages,
                'batch_size' => $this->batchSize($batchMultiplier),
                'order_max_bboxes' => $maxBboxes,
            ],
        ];
    }

    /**
     * @param array<string, mixed> $orderResult
     * @param array<string, mixed>|null $page
     */
    private function orderResultPayloadMatchesPage(array $orderResult, ?array $page, int $selectedIndex, ?int $sourceIndex): bool
    {
        $payload = $this->orderResultPayloadSource($orderResult, $page, $selectedIndex, $sourceIndex);
        $sources = $this->orderResultPageMarkerSources($payload);
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
     * Supplied adapters sometimes carry page identity by wrapping the ordering
     * payload with pdftext dictionaries. Upstream page.order is the Surya
     * ordering result, so keep only ordering geometry plus scalar page markers.
     *
     * @param array<string, mixed> $orderResult
     * @return array<string, mixed>
     */
    private function sanitizeSuppliedOrderResult(
        array $orderResult,
        ?array $page = null,
        int $selectedIndex = 0,
        ?int $sourceIndex = null
    ): array
    {
        $sanitized = [];
        $payload = $this->orderResultPayloadSource($orderResult, $page, $selectedIndex, $sourceIndex);

        $hasImageBbox = array_key_exists('image_bbox', $payload);
        $imageBbox = $this->bboxValueFromRecordField($payload, 'image_bbox', [
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
            $sanitized['bboxes'] = $this->sanitizeSuppliedOrderBboxes(
                $payload['bboxes'],
                $page,
                $selectedIndex,
                $sourceIndex,
                $hasImageBbox && !$hasUsableImageBbox,
                $payload
            );
        }

        foreach ($this->orderResultPageMarkerSources($orderResult) as $source) {
            foreach (self::ORDER_RESULT_PAGE_MARKER_KEYS as $key) {
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
     * Adapter serializers may wrap the Surya `OrderResult` object under a
     * typed result key while keeping page identity at the outer level.
     *
     * @param array<string, mixed> $orderResult
     * @return array<string, mixed>
     */
    private function orderResultPayloadSource(
        array $orderResult,
        ?array $page = null,
        int $selectedIndex = 0,
        ?int $sourceIndex = null
    ): array
    {
        $sources = [];
        $this->collectOrderResultPayloadSources($orderResult, $sources);

        foreach ($sources as $source) {
            if ($this->hasOrderPayload($source)) {
                return $source;
            }
            $directEnvelopePayload = $this->directOrderResultPayloadEnvelopeSource($source, $page, $selectedIndex, $sourceIndex);
            if ($directEnvelopePayload !== null) {
                return $directEnvelopePayload;
            }
        }

        $directEnvelopePayload = $this->directOrderResultPayloadEnvelopeSource($orderResult, $page, $selectedIndex, $sourceIndex);
        if ($directEnvelopePayload !== null) {
            return $directEnvelopePayload;
        }

        return $orderResult;
    }

    /**
     * Cached supplied artifacts sometimes keep selected page identity at the
     * top level and store order-result payloads inside a pdftext-like
     * pages/dictionary_output/pdftext envelope. Singleton payloads are accepted
     * directly. Source-page keyed maps are accepted only when exactly one
     * candidate matches the selected page identity.
     *
     * @param array<string, mixed> $orderResult
     * @return array<string, mixed>|null
     */
    private function directOrderResultPayloadEnvelopeSource(
        array $orderResult,
        ?array $page = null,
        int $selectedIndex = 0,
        ?int $sourceIndex = null
    ): ?array
    {
        foreach (self::ORDER_RESULT_DIRECT_PAYLOAD_ENVELOPES as $key) {
            $value = $this->normalizeDirectOrderResultPayloadEnvelopeValue($orderResult[$key] ?? null);
            if (!is_array($value)) {
                continue;
            }

            $candidates = $this->directOrderResultPayloadEnvelopeCandidates($value);
            if (count($candidates) === 1) {
                $candidate = $candidates[0];
                if ($this->hasOrderPayload($candidate)) {
                    return $candidate;
                }

                continue;
            }

            $matched = $this->matchingDirectOrderResultPayloadEnvelopeCandidates(
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
     * A typed `order_result` wrapper may carry current page metadata while its
     * direct pdftext-style payload envelope contains several unmarked result
     * dictionaries. Upstream receives one order result per selected page, so a
     * multi-payload envelope is trusted only when exactly one inner candidate
     * matches the selected page identity.
     *
     * @param array<string, mixed> $orderResult
     */
    private function hasAmbiguousDirectOrderPayloadEnvelope(
        array $orderResult,
        ?array $page,
        int $selectedIndex,
        ?int $sourceIndex
    ): bool {
        $sources = [];
        $this->collectOrderResultPayloadSources($orderResult, $sources);

        foreach ($sources as $source) {
            foreach (self::ORDER_RESULT_DIRECT_PAYLOAD_ENVELOPES as $key) {
                $value = $this->normalizeDirectOrderResultPayloadEnvelopeValue($source[$key] ?? null);
                if (!is_array($value)) {
                    continue;
                }

                $candidates = array_values(array_filter(
                    $this->directOrderResultPayloadEnvelopeCandidates($value),
                    fn (array $candidate): bool => $this->hasOrderPayload($candidate)
                ));
                if (count($candidates) <= 1) {
                    continue;
                }

                $matched = $this->matchingDirectOrderResultPayloadEnvelopeCandidates(
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
    private function matchingDirectOrderResultPayloadEnvelopeCandidates(
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
            if (!$this->hasOrderPayload($candidate)) {
                continue;
            }
            $sources = $this->orderResultPageMarkerSources($candidate);
            if (!$this->pageMarkerSourcesMatchPage($sources, $page, $selectedIndex, $sourceIndex)) {
                continue;
            }

            if ($this->orderResultPageMarkerSourcesHaveMarkers($sources)) {
                $markedMatches[] = $candidate;
                continue;
            }

            $unmarkedMatches[] = $candidate;
        }

        return $markedMatches !== [] ? $markedMatches : $unmarkedMatches;
    }

    /**
     * Typed supplied-order wrappers may preserve pdftext-style envelopes as raw
     * JSON strings. Decode only at explicit payload-envelope keys so arbitrary
     * scalar payload text remains review-only data.
     */
    private function normalizeDirectOrderResultPayloadEnvelopeValue(mixed $value): mixed
    {
        if (is_string($value)) {
            $decoded = $this->decodeDirectOrderResultPayloadJsonEnvelope($value);
            if ($decoded !== null) {
                return PdfPageArtifactSelector::normalizeSuppliedArtifactValue($decoded);
            }
        }

        return PdfPageArtifactSelector::normalizeSuppliedArtifactValue($value);
    }

    private function decodeDirectOrderResultPayloadJsonEnvelope(string $value): mixed
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
    private function directOrderResultPayloadEnvelopeCandidates(array $value): array
    {
        $candidates = $this->dictionaryWrapperValues($value);
        if (count($candidates) === 1 && $this->hasOrderPayload($candidates[0])) {
            return $candidates;
        }
        if (array_is_list($value) || $this->hasOrderPayload($value)) {
            return $candidates;
        }

        $mapped = [];
        foreach ($value as $key => $candidate) {
            $pageKey = $this->integerValue($key);
            if (!is_array($candidate) || array_is_list($candidate)) {
                continue;
            }

            if (!$this->hasOrderPayload($candidate)) {
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
    private function collectOrderResultPayloadSources(array $artifact, array &$sources, int $depth = 0): void
    {
        $sources[] = $artifact;
        if ($depth >= 2) {
            return;
        }

        foreach (self::ORDER_RESULT_PAYLOAD_WRAPPERS as $key) {
            $value = $artifact[$key] ?? null;
            if (!is_array($value)) {
                continue;
            }

            foreach ($this->dictionaryWrapperValues($value) as $wrapperValue) {
                $this->collectOrderResultPayloadSources($wrapperValue, $sources, $depth + 1);
            }
        }
    }

    /**
     * @param array<string, mixed> $artifact
     */
    private function hasAmbiguousOrderPayloadWrapper(array $artifact, int $depth = 0): bool
    {
        if ($depth >= 2) {
            return false;
        }

        foreach (self::ORDER_RESULT_PAYLOAD_WRAPPERS as $key) {
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
                    if ($this->hasAmbiguousOrderPayloadWrapper($dictionary, $depth + 1)) {
                        return true;
                    }
                }

                continue;
            }

            if ($this->hasAmbiguousOrderPayloadWrapper($value, $depth + 1)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<string, mixed> $source
     */
    private function hasOrderPayload(array $source): bool
    {
        return array_key_exists('image_bbox', $source) || array_key_exists('bboxes', $source);
    }

    /**
     * @param mixed $boxes
     * @return list<array{position: int, bbox: list<float>}>
     */
    private function sanitizeSuppliedOrderBboxes(
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
        foreach ($boxes as $index => $box) {
            if (!is_array($box)) {
                continue;
            }
            if (!$this->pageMarkerSourcesMatchPage($this->orderResultPageMarkerSources($box), $page, $selectedIndex, $sourceIndex)) {
                continue;
            }

            $bbox = $this->bboxValueWithSharedCoordinateOrder($box, $sharedBboxOrderSource);
            if ($bbox === null) {
                continue;
            }
            if ($rejectNormalizedBboxesWithoutImageExtent && $this->isNormalizedOrderBbox($bbox)) {
                continue;
            }
            if ($this->rectWidth($bbox) <= 0.0 || $this->rectHeight($bbox) <= 0.0) {
                continue;
            }

            $position = $this->orderBoxPosition($box, $index + 1);
            if ($position === null) {
                continue;
            }

            $sanitized[] = [
                'position' => $position,
                'bbox' => $bbox,
            ];
        }

        return $sanitized;
    }

    /**
     * @param array<string, mixed> $box
     */
    private function orderBoxPosition(array $box, int $fallback): ?int
    {
        foreach (self::ORDER_RESULT_POSITION_KEYS as $key) {
            if (!array_key_exists($key, $box)) {
                continue;
            }

            return $this->integerValue($box[$key]);
        }

        return $fallback;
    }

    /**
     * @param array<string, mixed> $box
     * @param array<string, mixed>|null $sharedBboxOrderSource
     * @return list<float>|null
     */
    private function bboxValueWithSharedCoordinateOrder(array $box, ?array $sharedBboxOrderSource): ?array
    {
        if ($this->bboxCoordinateOrder($box) !== null) {
            return $this->bboxValue($box);
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
                'order_bboxes_bbox_order',
                'order_bboxes_coordinate_order',
                'order_bboxes_coordinate_format',
                'order_bboxes_bbox_coordinate_order',
                'order_bboxes_bbox_coordinate_format',
                'order_bboxes_bbox_format',
            ]);

        if ($sharedOrder !== null && isset($box['bbox']) && is_array($box['bbox'])) {
            $bbox = $this->bboxFromCoordinateList($box['bbox'], $sharedOrder);
            if ($bbox !== null) {
                return $bbox;
            }
        }

        return $this->bboxValue($box);
    }

    /**
     * Row-level page markers are adapter metadata. Upstream Surya order rows are
     * zipped with the selected page after document trimming, so mixed cached row
     * payloads from another source page must not influence current-page order.
     *
     * @param list<array<string, mixed>> $sources
     */
    private function pageMarkerSourcesMatchPage(array $sources, ?array $page, int $selectedIndex, ?int $sourceIndex): bool
    {
        $hasMarkers = false;
        foreach ($sources as $source) {
            if (($source[self::AMBIGUOUS_PAGE_MARKER_WRAPPER] ?? false) === true || $this->orderResultPageMarkerSourceHasMalformedMarkers($source)) {
                return false;
            }

            if ($this->integerFields($source, [self::ENVELOPE_PAGE_KEY_MARKER]) !== []) {
                $hasMarkers = true;
            }
            foreach (self::ORDER_RESULT_PAGE_MARKER_FIELD_GROUPS as $fields) {
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

        foreach ($this->integerFieldsFromSources($sources, self::ORDER_RESULT_PAGE_MARKER_FIELD_GROUPS[0]) as $marker) {
            if ($marker !== $sourceIndex) {
                return false;
            }
        }
        foreach ($this->integerFieldsFromSources($sources, self::ORDER_RESULT_PAGE_MARKER_FIELD_GROUPS[1]) as $marker) {
            if ($marker !== $selectedIndex) {
                return false;
            }
        }
        foreach ($this->integerFieldsFromSources($sources, self::ORDER_RESULT_PAGE_MARKER_FIELD_GROUPS[2]) as $marker) {
            if ($marker !== $pageNumber) {
                return false;
            }
        }
        foreach ($this->integerFieldsFromSources($sources, self::ORDER_RESULT_PAGE_MARKER_FIELD_GROUPS[3]) as $marker) {
            if ($marker !== $pageNumber + 1) {
                return false;
            }
        }
        foreach ($this->integerFieldsFromSources($sources, self::ORDER_RESULT_PAGE_MARKER_FIELD_GROUPS[4]) as $marker) {
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
     * @param array<string, mixed> $orderResult
     * @return list<array<string, mixed>>
     */
    private function orderResultPageMarkerSources(array $orderResult): array
    {
        $sources = [];
        $this->collectOrderResultPageMarkerSources($orderResult, $sources, 0, self::ORDER_RESULT_PAGE_MARKER_METADATA_WRAPPERS);

        if ($this->orderResultPageMarkerSourcesHaveMarkers($sources)) {
            return $sources;
        }

        $sources = [];
        $this->collectOrderResultPageMarkerSources($orderResult, $sources, 0, self::ORDER_RESULT_PAGE_MARKER_WRAPPERS);

        return $sources;
    }

    /**
     * @param array<string, mixed> $artifact
     * @param list<array<string, mixed>> $sources
     * @param list<string> $wrapperKeys
     */
    private function collectOrderResultPageMarkerSources(array $artifact, array &$sources, int $depth, array $wrapperKeys): void
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
                $this->collectOrderResultPageMarkerSources($wrapperValue, $sources, $depth + 1, $wrapperKeys);
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
     * Typed ordering payload wrappers follow the same rule so stale model
     * payload page markers cannot override trusted adapter metadata.
     *
     * @param list<array<string, mixed>> $sources
     */
    private function orderResultPageMarkerSourcesHaveMarkers(array $sources): bool
    {
        foreach ($sources as $source) {
            if (($source[self::AMBIGUOUS_PAGE_MARKER_WRAPPER] ?? false) === true || $this->orderResultPageMarkerSourceHasMalformedMarkers($source)) {
                return true;
            }

            if ($this->integerFields($source, [self::ENVELOPE_PAGE_KEY_MARKER]) !== []) {
                return true;
            }
            foreach (self::ORDER_RESULT_PAGE_MARKER_FIELD_GROUPS as $fields) {
                if ($this->integerFields($source, $fields) !== []) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * @param array<string, mixed> $orderResult
     */
    private function hasMalformedOrderPageMarkers(array $orderResult): bool
    {
        foreach ($this->orderResultPageMarkerSources($orderResult) as $source) {
            if ($this->orderResultPageMarkerSourceHasMalformedMarkers($source)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<string, mixed> $source
     */
    private function orderResultPageMarkerSourceHasMalformedMarkers(array $source): bool
    {
        foreach (self::ORDER_RESULT_PAGE_MARKER_FIELD_GROUPS as $fields) {
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
     * @param list<array<string, mixed>> $pages
     * @return list<array<string, mixed>>
     */
    public function sortBlocksInReadingOrder(array $pages): array
    {
        foreach ($pages as $pageIndex => $page) {
            $blocks = array_values($page['blocks'] ?? []);
            $orderBoxes = $this->orderBoxes($page);
            $layoutOrder = null;
            if ($orderBoxes === [] && !$this->hasOrderArtifact($page)) {
                $layoutOrder = $this->layoutOrderBoxes($page);
                $orderBoxes = $layoutOrder['boxes'];
            }
            $usingLayoutOrder = $layoutOrder !== null && $orderBoxes !== [];
            $blockPositions = [];
            $maxPosition = 0;

            foreach ($blocks as $blockIndex => $block) {
                $blockBbox = $this->blockBboxForOrdering($page, $block);
                foreach ($orderBoxes as $orderBox) {
                    $position = (int) ($orderBox['position'] ?? 0);
                    $orderBbox = $usingLayoutOrder
                        ? $this->bbox($orderBox)
                        : $this->rescaleOrderBbox($page, $this->bbox($orderBox));
                    $intersection = $this->intersectionPct($blockBbox, $orderBbox);
                    $maxPosition = max($maxPosition, $position);

                    if ($usingLayoutOrder && $intersection <= 0.0) {
                        continue;
                    }

                    if (!isset($blockPositions[$blockIndex]) || $intersection > $blockPositions[$blockIndex]['intersection']) {
                        $blockPositions[$blockIndex] = [
                            'intersection' => $intersection,
                            'position' => $position,
                            'bbox' => $orderBbox,
                            'box' => $orderBox,
                        ];
                    }
                }
            }

            $blockGroups = [];
            $matchedBlockCount = 0;
            $unmatchedBlockCount = 0;
            foreach ($blocks as $blockIndex => $block) {
                if (isset($blockPositions[$blockIndex])) {
                    $position = (int) $blockPositions[$blockIndex]['position'];
                    if ($usingLayoutOrder) {
                        $matchedBlockCount++;
                        $block = $this->withLayoutReadingOrderDiagnostic($block, $blockPositions[$blockIndex]);
                    }
                } else {
                    $maxPosition++;
                    $position = $maxPosition;
                    if ($usingLayoutOrder) {
                        $unmatchedBlockCount++;
                        $block = $this->withUnmatchedLayoutReadingOrderDiagnostic($block, $position);
                    }
                }
                $blockGroups[$position][] = $block;
            }

            ksort($blockGroups, SORT_NUMERIC);
            $newBlocks = [];
            foreach ($blockGroups as $blockGroup) {
                array_push($newBlocks, ...$this->sortBlockGroupForPage($page, $blockGroup));
            }

            $pages[$pageIndex]['blocks'] = $this->pinHeadersAndFooters($newBlocks);
            if ($usingLayoutOrder && $layoutOrder !== null) {
                $pages[$pageIndex]['layout_reading_order_diagnostics'] = $this->layoutReadingOrderPageDiagnostics(
                    $layoutOrder,
                    count($blocks),
                    $matchedBlockCount,
                    $unmatchedBlockCount
                );
            }
        }

        return $pages;
    }

    /**
     * @param list<array<string, mixed>> $blocks
     * @return list<array<string, mixed>>
     */
    public function sortBlockGroup(array $blocks, float $tolerance = 1.25): array
    {
        return $this->sortBlockGroupByBbox($blocks, $tolerance, fn (array $block): array => $this->bbox($block));
    }

    /**
     * @param array<string, mixed> $page
     * @param list<array<string, mixed>> $blocks
     * @return list<array<string, mixed>>
     */
    private function sortBlockGroupForPage(array $page, array $blocks, float $tolerance = 1.25): array
    {
        return $this->sortBlockGroupByBbox(
            $blocks,
            $tolerance,
            fn (array $block): array => $this->blockBboxForOrdering($page, $block)
        );
    }

    /**
     * @param list<array<string, mixed>> $blocks
     * @param callable(array<string, mixed>): list<float> $bboxForBlock
     * @return list<array<string, mixed>>
     */
    private function sortBlockGroupByBbox(array $blocks, float $tolerance, callable $bboxForBlock): array
    {
        $groups = [];
        foreach ($blocks as $index => $block) {
            $bbox = $bboxForBlock($block);
            $sortKey = $tolerance > 0.0 ? round($bbox[1] / $tolerance) * $tolerance : $bbox[1];
            $key = (string) $sortKey;
            if (!isset($groups[$key])) {
                $groups[$key] = [
                    'sort_key' => $sortKey,
                    'blocks' => [],
                ];
            }
            $groups[$key]['blocks'][] = [
                'index' => $index,
                'left' => $bbox[0],
                'block' => $block,
            ];
        }

        usort(
            $groups,
            static fn (array $left, array $right): int => $left['sort_key'] <=> $right['sort_key']
        );

        $sorted = [];
        foreach ($groups as $group) {
            $groupBlocks = $group['blocks'];
            usort(
                $groupBlocks,
                static fn (array $left, array $right): int => ($left['left'] <=> $right['left']) ?: ($left['index'] <=> $right['index'])
            );
            foreach ($groupBlocks as $entry) {
                $sorted[] = $entry['block'];
            }
        }

        return $sorted;
    }

    /**
     * @param list<float> $origDim
     * @param list<float> $newDim
     * @param list<float> $bbox
     * @return list<float>
     */
    public function rescaleBbox(array $origDim, array $newDim, array $bbox): array
    {
        $pageWidth = $newDim[2] - $newDim[0];
        $pageHeight = $newDim[3] - $newDim[1];
        $detectedWidth = $origDim[2] - $origDim[0];
        $detectedHeight = $origDim[3] - $origDim[1];

        if ($pageWidth == 0.0 || $pageHeight == 0.0 || $detectedWidth == 0.0 || $detectedHeight == 0.0) {
            return $bbox;
        }

        $widthScaler = $detectedWidth / $pageWidth;
        $heightScaler = $detectedHeight / $pageHeight;

        return [
            $bbox[0] / $widthScaler,
            $bbox[1] / $heightScaler,
            $bbox[2] / $widthScaler,
            $bbox[3] / $heightScaler,
        ];
    }

    /**
     * @param list<float> $left
     * @param list<float> $right
     */
    public function intersectionPct(array $left, array $right): float
    {
        $xLeft = max($left[0], $right[0]);
        $yTop = max($left[1], $right[1]);
        $xRight = min($left[2], $right[2]);
        $yBottom = min($left[3], $right[3]);

        if ($xRight < $xLeft || $yBottom < $yTop) {
            return 0.0;
        }

        $leftArea = ($left[2] - $left[0]) * ($left[3] - $left[1]);
        if ($leftArea == 0.0) {
            return 0.0;
        }

        return (($xRight - $xLeft) * ($yBottom - $yTop)) / $leftArea;
    }

    /**
     * @param array<string, mixed> $page
     * @return list<array<string, mixed>>
     */
    private function orderBoxes(array $page): array
    {
        $order = $page['order'] ?? [];
        if (is_array($order) && isset($order['bboxes']) && is_array($order['bboxes'])) {
            return $this->sanitizeSuppliedOrderBboxes($order['bboxes']);
        }
        if (isset($page['order_bboxes']) && is_array($page['order_bboxes'])) {
            return $this->sanitizeSuppliedOrderBboxes($page['order_bboxes']);
        }

        return [];
    }

    /**
     * @param array<string, mixed> $page
     */
    private function hasOrderArtifact(array $page): bool
    {
        $order = $page['order'] ?? null;
        if (is_array($order) && (array_key_exists('bboxes', $order) || array_key_exists('image_bbox', $order))) {
            return true;
        }

        return array_key_exists('order_bboxes', $page);
    }

    /**
     * @param array<string, mixed> $page
     * @return array{boxes: list<array<string, mixed>>, diagnostics: array<string, mixed>}
     */
    private function layoutOrderBoxes(array $page): array
    {
        $layoutBoxes = $this->layoutBoxesForReadingOrder($page);
        if ($layoutBoxes === []) {
            return [
                'boxes' => [],
                'diagnostics' => [
                    'layout_box_count' => 0,
                    'full_width_box_count' => 0,
                    'max_column_count' => 0,
                ],
            ];
        }

        $pageBbox = $this->pageBboxForLayoutOrdering($page, $layoutBoxes);
        $ordered = $this->sortLayoutBoxesForReadingOrder($layoutBoxes, $pageBbox);
        $boxes = [];
        $fullWidthBoxCount = 0;
        $maxColumnCount = 0;

        foreach ($ordered as $index => $box) {
            $columnCount = (int) ($box['column_count'] ?? 1);
            $maxColumnCount = max($maxColumnCount, $columnCount);
            if (($box['full_width'] ?? false) === true) {
                $fullWidthBoxCount++;
            }

            $boxes[] = [
                'position' => $index + 1,
                'bbox' => $box['bbox'],
                'label' => $box['label'],
                'layout_index' => $box['layout_index'],
                'section' => $box['section'] ?? 0,
                'column' => $box['column'] ?? 0,
                'column_count' => $columnCount,
                'full_width' => ($box['full_width'] ?? false) === true,
            ];
        }

        return [
            'boxes' => $boxes,
            'diagnostics' => [
                'layout_box_count' => count($boxes),
                'full_width_box_count' => $fullWidthBoxCount,
                'max_column_count' => $maxColumnCount,
            ],
        ];
    }

    /**
     * @param array<string, mixed> $page
     * @return list<array<string, mixed>>
     */
    private function layoutBoxesForReadingOrder(array $page): array
    {
        $layout = $page['layout'] ?? [];
        if (is_array($layout) && isset($layout['bboxes']) && is_array($layout['bboxes'])) {
            $boxes = $layout['bboxes'];
        } elseif (isset($page['layout_boxes']) && is_array($page['layout_boxes'])) {
            $boxes = $page['layout_boxes'];
        } else {
            $boxes = [];
        }

        $layoutBoxes = [];
        foreach ($boxes as $index => $box) {
            if (!is_array($box)) {
                continue;
            }

            $bbox = $this->bboxValue($box);
            if ($bbox === null || $this->rectWidth($bbox) <= 0.0 || $this->rectHeight($bbox) <= 0.0) {
                continue;
            }

            $layoutBoxes[] = [
                'layout_index' => $this->integerValue($index) ?? count($layoutBoxes),
                'label' => $this->layoutBoxLabel($box),
                'bbox' => $this->rescaleLayoutBbox($page, $bbox),
            ];
        }

        return $layoutBoxes;
    }

    /**
     * @param array<string, mixed> $box
     */
    private function layoutBoxLabel(array $box): string
    {
        foreach (['label', 'block_type', 'type', 'category', 'name'] as $key) {
            if (isset($box[$key]) && is_scalar($box[$key])) {
                return (string) $box[$key];
            }
        }

        return '';
    }

    /**
     * @param list<array<string, mixed>> $layoutBoxes
     * @return list<float>
     */
    private function pageBboxForLayoutOrdering(array $page, array $layoutBoxes): array
    {
        $pageBbox = $this->bboxValue($page['bbox'] ?? null);
        if ($pageBbox !== null && $this->rectWidth($pageBbox) > 0.0 && $this->rectHeight($pageBbox) > 0.0) {
            return $pageBbox;
        }

        $boxes = array_values(array_filter(
            array_map(static fn (array $box): array => $box['bbox'], $layoutBoxes),
            fn (array $bbox): bool => $this->rectWidth($bbox) > 0.0 && $this->rectHeight($bbox) > 0.0
        ));

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
     * @param list<array<string, mixed>> $layoutBoxes
     * @param list<float> $pageBbox
     * @return list<array<string, mixed>>
     */
    private function sortLayoutBoxesForReadingOrder(array $layoutBoxes, array $pageBbox): array
    {
        $sorted = $this->sortLayoutBoxesTopLeft($layoutBoxes);
        $ordered = [];
        $pending = [];
        $section = 0;

        foreach ($sorted as $box) {
            if ($this->layoutBoxSpansReadingWidth($box['bbox'], $pageBbox)) {
                if ($pending !== []) {
                    array_push($ordered, ...$this->sortLayoutSectionBoxes($pending, $pageBbox, $section));
                    $pending = [];
                    $section++;
                }

                $box['section'] = $section;
                $box['column'] = 0;
                $box['column_count'] = 1;
                $box['full_width'] = true;
                $ordered[] = $box;
                $section++;
                continue;
            }

            $pending[] = $box;
        }

        if ($pending !== []) {
            array_push($ordered, ...$this->sortLayoutSectionBoxes($pending, $pageBbox, $section));
        }

        return $ordered;
    }

    /**
     * @param list<array<string, mixed>> $boxes
     * @param list<float> $pageBbox
     * @return list<array<string, mixed>>
     */
    private function sortLayoutSectionBoxes(array $boxes, array $pageBbox, int $section): array
    {
        $columns = $this->layoutColumnGroups($boxes, $pageBbox);
        $columnCount = count($columns);
        $ordered = [];

        foreach ($columns as $columnIndex => $columnBoxes) {
            foreach ($this->sortLayoutBoxesTopLeft($columnBoxes) as $box) {
                $box['section'] = $section;
                $box['column'] = $columnIndex;
                $box['column_count'] = $columnCount;
                $box['full_width'] = false;
                $ordered[] = $box;
            }
        }

        return $ordered;
    }

    /**
     * @param list<array<string, mixed>> $boxes
     * @param list<float> $pageBbox
     * @return list<list<array<string, mixed>>>
     */
    private function layoutColumnGroups(array $boxes, array $pageBbox): array
    {
        if (count($boxes) < 2) {
            return [$boxes];
        }

        $pageWidth = $this->rectWidth($pageBbox);
        if ($pageWidth <= 0.0) {
            return [$this->sortLayoutBoxesTopLeft($boxes)];
        }

        $entries = [];
        foreach ($boxes as $index => $box) {
            $bbox = $box['bbox'];
            $entries[] = [
                'index' => $index,
                'center' => ($bbox[0] + $bbox[2]) / 2.0,
                'box' => $box,
            ];
        }

        usort(
            $entries,
            static fn (array $left, array $right): int => ($left['center'] <=> $right['center']) ?: ($left['index'] <=> $right['index'])
        );

        $threshold = max(36.0, $pageWidth * 0.18);
        $groups = [];
        $current = [];
        $previousCenter = null;
        foreach ($entries as $entry) {
            if ($previousCenter !== null && $entry['center'] - $previousCenter > $threshold) {
                $groups[] = $current;
                $current = [];
            }

            $current[] = $entry['box'];
            $previousCenter = $entry['center'];
        }
        if ($current !== []) {
            $groups[] = $current;
        }

        if (count($groups) < 2 || $this->layoutColumnSpread($groups) < $pageWidth * 0.25) {
            return [$this->sortLayoutBoxesTopLeft($boxes)];
        }

        usort(
            $groups,
            fn (array $left, array $right): int => $this->layoutColumnLeft($left) <=> $this->layoutColumnLeft($right)
        );

        return $groups;
    }

    /**
     * @param list<list<array<string, mixed>>> $groups
     */
    private function layoutColumnSpread(array $groups): float
    {
        $centers = [];
        foreach ($groups as $group) {
            $centers[] = $this->layoutColumnCenter($group);
        }

        return max($centers) - min($centers);
    }

    /**
     * @param list<array<string, mixed>> $boxes
     */
    private function layoutColumnLeft(array $boxes): float
    {
        return min(array_map(static fn (array $box): float => (float) $box['bbox'][0], $boxes));
    }

    /**
     * @param list<array<string, mixed>> $boxes
     */
    private function layoutColumnCenter(array $boxes): float
    {
        $left = min(array_map(static fn (array $box): float => (float) $box['bbox'][0], $boxes));
        $right = max(array_map(static fn (array $box): float => (float) $box['bbox'][2], $boxes));

        return ($left + $right) / 2.0;
    }

    /**
     * @param list<array<string, mixed>> $boxes
     * @return list<array<string, mixed>>
     */
    private function sortLayoutBoxesTopLeft(array $boxes): array
    {
        usort(
            $boxes,
            static function (array $left, array $right): int {
                $leftBbox = $left['bbox'];
                $rightBbox = $right['bbox'];

                return ($leftBbox[1] <=> $rightBbox[1])
                    ?: ($leftBbox[0] <=> $rightBbox[0])
                    ?: (($left['layout_index'] ?? 0) <=> ($right['layout_index'] ?? 0));
            }
        );

        return $boxes;
    }

    /**
     * @param list<float> $bbox
     * @param list<float> $pageBbox
     */
    private function layoutBoxSpansReadingWidth(array $bbox, array $pageBbox): bool
    {
        $pageWidth = $this->rectWidth($pageBbox);
        if ($pageWidth <= 0.0) {
            return false;
        }

        $width = $this->rectWidth($bbox);
        $pageCenter = ($pageBbox[0] + $pageBbox[2]) / 2.0;

        return $width >= $pageWidth * 0.58
            || ($width >= $pageWidth * 0.48 && $bbox[0] <= $pageCenter && $bbox[2] >= $pageCenter);
    }

    /**
     * @param array<string, mixed> $page
     * @param list<float> $bbox
     * @return list<float>
     */
    private function rescaleLayoutBbox(array $page, array $bbox): array
    {
        $layout = $page['layout'] ?? [];
        $imageBbox = is_array($layout) ? $this->bboxValue($layout['image_bbox'] ?? null) : null;
        $imageBbox ??= $this->bboxValue($page['layout_image_bbox'] ?? null);
        $pageBbox = $this->bboxValue($page['bbox'] ?? null);

        if ($imageBbox !== null && $pageBbox !== null) {
            $rotation = $this->normalizedRotation((int) round((float) ($page['rotation'] ?? 0)));
            $bbox = $this->expandNormalizedOrderBbox($bbox, $imageBbox);
            if ($this->shouldRotateUnrotatedOrderImage($imageBbox, $pageBbox, $rotation)) {
                $bbox = $this->rotateUnrotatedImageBbox($bbox, $imageBbox, $rotation);
                $imageBbox = [
                    0.0,
                    0.0,
                    $this->rectHeight($imageBbox),
                    $this->rectWidth($imageBbox),
                ];
            }

            return $this->rescaleBbox($imageBbox, $pageBbox, $bbox);
        }

        return $bbox;
    }

    /**
     * @param array<string, mixed> $block
     * @param array{intersection: float, position: int, bbox: list<float>, box: array<string, mixed>} $match
     * @return array<string, mixed>
     */
    private function withLayoutReadingOrderDiagnostic(array $block, array $match): array
    {
        $box = $match['box'];
        $label = (string) ($box['label'] ?? '');
        $position = (int) $match['position'];

        $block['order_position'] = $position;
        $block['reading_order_source'] = 'layout';
        if ($label !== '') {
            $block['layout_label'] = $label;
        }
        $block['layout_reading_order'] = $this->compactReadingOrderDiagnostic([
            'source' => 'layout_boxes_fallback',
            'position' => $position,
            'layout_index' => $box['layout_index'] ?? null,
            'layout_label' => $label,
            'section' => $box['section'] ?? null,
            'column' => $box['column'] ?? null,
            'column_count' => $box['column_count'] ?? null,
            'full_width' => ($box['full_width'] ?? false) === true,
            'intersection_pct' => round((float) $match['intersection'], 4),
            'order_bbox' => $match['bbox'],
            'review_only' => true,
            'visible_text_source' => false,
        ]);

        return $block;
    }

    /**
     * @param array<string, mixed> $block
     * @return array<string, mixed>
     */
    private function withUnmatchedLayoutReadingOrderDiagnostic(array $block, int $position): array
    {
        $block['order_position'] = $position;
        $block['reading_order_source'] = 'source_order_after_layout';
        $block['layout_reading_order'] = [
            'source' => 'source_order_after_layout_fallback',
            'position' => $position,
            'review_only' => true,
            'visible_text_source' => false,
        ];

        return $block;
    }

    /**
     * @param array{boxes: list<array<string, mixed>>, diagnostics: array<string, mixed>} $layoutOrder
     * @return array<string, mixed>
     */
    private function layoutReadingOrderPageDiagnostics(array $layoutOrder, int $blockCount, int $matchedBlockCount, int $unmatchedBlockCount): array
    {
        return [
            'review_target' => 'marker_layout_reading_order_reconstruction',
            'source' => 'layout_boxes_fallback',
            'block_count' => $blockCount,
            'layout_box_count' => (int) ($layoutOrder['diagnostics']['layout_box_count'] ?? count($layoutOrder['boxes'])),
            'matched_block_count' => $matchedBlockCount,
            'unmatched_block_count' => $unmatchedBlockCount,
            'full_width_box_count' => (int) ($layoutOrder['diagnostics']['full_width_box_count'] ?? 0),
            'max_column_count' => (int) ($layoutOrder['diagnostics']['max_column_count'] ?? 0),
            'review_only' => true,
            'visible_text_source' => false,
        ];
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function compactReadingOrderDiagnostic(array $row): array
    {
        return array_filter(
            $row,
            static fn (mixed $value): bool => $value !== null && $value !== '' && $value !== []
        );
    }

    /**
     * @param array<string, mixed> $page
     * @return list<list<float>>
     */
    private function layoutBboxesForOrdering(array $page, int $maxBboxes): array
    {
        $layout = $page['layout'] ?? [];
        if (is_array($layout) && isset($layout['bboxes']) && is_array($layout['bboxes'])) {
            $boxes = $layout['bboxes'];
        } elseif (isset($page['layout_boxes']) && is_array($page['layout_boxes'])) {
            $boxes = $page['layout_boxes'];
        } else {
            $boxes = [];
        }

        $bboxes = [];
        foreach ($boxes as $box) {
            if (!is_array($box)) {
                continue;
            }

            $bbox = $this->bboxValue($box);
            if ($bbox === null) {
                continue;
            }

            $bboxes[] = $bbox;
            if (count($bboxes) >= $maxBboxes) {
                break;
            }
        }

        return $bboxes;
    }

    /**
     * @return list<float>|null
     */
    private function bboxValue(mixed $value): ?array
    {
        if (!is_array($value)) {
            return null;
        }

        if (array_key_exists('bbox', $value)) {
            return $this->bboxValueFromRecordField($value, 'bbox')
                ?? $this->bboxFromNamedFields($value)
                ?? $this->polygonAliasBbox($value);
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
    private function bboxValueFromRecordField(
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

        return $this->bboxValue($value);
    }

    /**
     * @param mixed $value
     * @return list<float>|null
     */
    private function bboxFromCoordinateList(mixed $value, ?string $coordinateOrder = null): ?array
    {
        if (!is_array($value) || !array_is_list($value) || count($value) !== 4) {
            return null;
        }

        $coordinates = $this->numericCoordinates(array_values($value));
        if ($coordinates === null) {
            return null;
        }
        if ($coordinateOrder !== null) {
            $coordinates = $this->applyBboxCoordinateOrder($coordinates, $coordinateOrder);
        }

        return $this->normalizeRect($coordinates);
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
            ['x0', 'y0', 'x1', 'y1'],
            ['x1', 'y1', 'x2', 'y2'],
            ['x_start', 'y_start', 'x_end', 'y_end'],
            ['xmin', 'ymin', 'xmax', 'ymax'],
            ['min_x', 'min_y', 'max_x', 'max_y'],
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
                return $this->normalizeRect($coordinates);
            }
        }

        foreach ([
            ['x', 'y', 'width', 'height'],
            ['x0', 'y0', 'width', 'height'],
            ['left', 'top', 'width', 'height'],
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
                return $this->normalizeRect([
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
                return $this->normalizeRect([$first[0], $first[1], $second[0], $second[1]]);
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
     * @param list<mixed> $values
     * @return list<float>|null
     */
    private function numericCoordinates(array $values): ?array
    {
        $bbox = [];
        foreach ($values as $item) {
            $number = $this->numericValue($item);
            if ($number === null) {
                return null;
            }
            $bbox[] = $number;
        }

        return $bbox;
    }

    private function numericValue(mixed $value): ?float
    {
        if (is_int($value) || is_float($value)) {
            $number = (float) $value;

            return is_finite($number) ? $number : null;
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
                $x = $this->numericValue($values[$index]);
                $y = $this->numericValue($values[$index + 1]);
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

        foreach ([['x', 'y'], ['x0', 'y0'], ['left', 'top']] as $keys) {
            [$xKey, $yKey] = $keys;
            if (!array_key_exists($xKey, $point) || !array_key_exists($yKey, $point)) {
                continue;
            }

            $x = $this->numericValue($point[$xKey]);
            $y = $this->numericValue($point[$yKey]);
            if ($x !== null && $y !== null) {
                return [$x, $y];
            }
        }

        if (array_is_list($point) && count($point) === 2) {
            $x = $this->numericValue($point[0]);
            $y = $this->numericValue($point[1]);
            if ($x !== null && $y !== null) {
                return [$x, $y];
            }
        }

        return null;
    }

    /**
     * @param array<string, mixed> $page
     * @param list<float> $bbox
     * @return list<float>
     */
    private function rescaleOrderBbox(array $page, array $bbox): array
    {
        $order = $page['order'] ?? [];
        $imageBbox = is_array($order) ? $this->bboxValue($order['image_bbox'] ?? null) : null;
        $pageBbox = $this->bboxValue($page['bbox'] ?? null);

        if ($imageBbox !== null && $pageBbox !== null) {
            $rotation = $this->normalizedRotation((int) round((float) ($page['rotation'] ?? 0)));
            $bbox = $this->expandNormalizedOrderBbox($bbox, $imageBbox);
            if ($this->shouldRotateUnrotatedOrderImage($imageBbox, $pageBbox, $rotation)) {
                $bbox = $this->rotateUnrotatedImageBbox($bbox, $imageBbox, $rotation);
                $imageBbox = [
                    0.0,
                    0.0,
                    $this->rectHeight($imageBbox),
                    $this->rectWidth($imageBbox),
                ];
            }

            return $this->rescaleBbox($imageBbox, $pageBbox, $bbox);
        }

        return $bbox;
    }

    /**
     * @param list<float> $bbox
     * @param list<float> $imageBbox
     * @return list<float>
     */
    private function expandNormalizedOrderBbox(array $bbox, array $imageBbox): array
    {
        $imageWidth = $this->rectWidth($imageBbox);
        $imageHeight = $this->rectHeight($imageBbox);
        if ($imageWidth <= 2.0 || $imageHeight <= 2.0 || !$this->isNormalizedOrderBbox($bbox)) {
            return $bbox;
        }

        return $this->normalizeRect([
            $imageBbox[0] + ($bbox[0] * $imageWidth),
            $imageBbox[1] + ($bbox[1] * $imageHeight),
            $imageBbox[0] + ($bbox[2] * $imageWidth),
            $imageBbox[1] + ($bbox[3] * $imageHeight),
        ]);
    }

    /**
     * @param list<float> $bbox
     */
    private function isNormalizedOrderBbox(array $bbox): bool
    {
        foreach ($bbox as $part) {
            if ($part < -0.5 || $part > 1.5) {
                return false;
            }
        }

        return $this->rectWidth($bbox) <= 2.0 && $this->rectHeight($bbox) <= 2.0;
    }

    /**
     * Upstream pdftext normalizes 90/270-degree pages by swapping the page bbox
     * axes before layout ordering. Some native preview/order fixtures still
     * carry pre-rotation image dimensions, so rotate those model boxes into the
     * same page-local coordinates before maximum-overlap matching.
     *
     * @param list<float> $imageBbox
     * @param list<float> $pageBbox
     */
    private function shouldRotateUnrotatedOrderImage(array $imageBbox, array $pageBbox, int $rotation): bool
    {
        if (!in_array($rotation, [90, 270], true)) {
            return false;
        }

        $imageWidth = $this->rectWidth($imageBbox);
        $imageHeight = $this->rectHeight($imageBbox);
        $pageWidth = $this->rectWidth($pageBbox);
        $pageHeight = $this->rectHeight($pageBbox);
        if ($imageWidth <= 0.0 || $imageHeight <= 0.0 || $pageWidth <= 0.0 || $pageHeight <= 0.0) {
            return false;
        }

        $imageRatio = $imageWidth / $imageHeight;
        $pageRatio = $pageWidth / $pageHeight;
        $unrotatedPageRatio = $pageHeight / $pageWidth;

        return abs($imageRatio - $unrotatedPageRatio) + 0.000001 < abs($imageRatio - $pageRatio);
    }

    /**
     * @param list<float> $bbox
     * @param list<float> $imageBbox
     * @return list<float>
     */
    private function rotateUnrotatedImageBbox(array $bbox, array $imageBbox, int $rotation): array
    {
        $width = $this->rectWidth($imageBbox);
        $height = $this->rectHeight($imageBbox);
        $x1 = $bbox[0] - $imageBbox[0];
        $y1 = $bbox[1] - $imageBbox[1];
        $x2 = $bbox[2] - $imageBbox[0];
        $y2 = $bbox[3] - $imageBbox[1];

        return $this->normalizeRect(match ($rotation) {
            90 => [$height - $y2, $x1, $height - $y1, $x2],
            270 => [$y1, $width - $x2, $y2, $width - $x1],
            default => [$x1, $y1, $x2, $y2],
        });
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
     * @param list<float> $bbox
     * @return list<float>
     */
    private function normalizeRect(array $bbox): array
    {
        return [
            min($bbox[0], $bbox[2]),
            min($bbox[1], $bbox[3]),
            max($bbox[0], $bbox[2]),
            max($bbox[1], $bbox[3]),
        ];
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
     * @param array<string, mixed> $block
     * @return list<float>
     */
    private function bbox(array $block): array
    {
        $bbox = $this->bboxValue($block['bbox'] ?? (array_is_list($block) ? $block : null));
        if ($bbox !== null) {
            return $bbox;
        }

        $lineBoxes = [];
        foreach (($block['lines'] ?? []) as $line) {
            if (!is_array($line)) {
                continue;
            }

            $lineBbox = $this->bboxValue($line['bbox'] ?? null);
            if ($lineBbox !== null) {
                $lineBoxes[] = $lineBbox;
            }
        }

        if ($lineBoxes === []) {
            return [0.0, 0.0, 0.0, 0.0];
        }

        return [
            min(array_column($lineBoxes, 0)),
            min(array_column($lineBoxes, 1)),
            max(array_column($lineBoxes, 2)),
            max(array_column($lineBoxes, 3)),
        ];
    }

    /**
     * @param array<string, mixed> $page
     * @param array<string, mixed> $block
     * @return list<float>
     */
    private function blockBboxForOrdering(array $page, array $block): array
    {
        $bbox = $this->bbox($block);
        if (!$this->usesPdfPageUserSpaceBlockBbox($page, $block)) {
            return $bbox;
        }

        $pageBbox = $this->bboxValue($page['pdf_page_bbox'] ?? $page['page_bbox'] ?? null);
        if ($pageBbox === null) {
            return $bbox;
        }

        $rotation = $this->normalizedRotation((int) round((float) ($block['page_rotation'] ?? $page['rotation'] ?? 0)));
        $userUnit = $this->positiveNumber($block['page_user_unit'] ?? $page['page_user_unit'] ?? $page['user_unit'] ?? null) ?? 1.0;

        return $this->pageRectToPdftextDisplayRect($bbox, $pageBbox, $rotation, $userUnit);
    }

    /**
     * @param array<string, mixed> $page
     * @param array<string, mixed> $block
     */
    private function usesPdfPageUserSpaceBlockBbox(array $page, array $block): bool
    {
        $coordinateSpace = $block['bbox_coordinate_space']
            ?? $block['block_bbox_coordinate_space']
            ?? $page['block_bbox_coordinate_space']
            ?? null;

        return in_array($coordinateSpace, ['pdf_page_user_space', 'pdf_page_space', 'page_user_space'], true);
    }

    /**
     * @param list<float> $rect
     * @param list<float> $pageBbox
     * @return list<float>
     */
    private function pageRectToPdftextDisplayRect(array $rect, array $pageBbox, int $rotation, float $userUnit): array
    {
        $pageBox = $this->normalizeRect($pageBbox);
        $sourceRect = $this->normalizeRect($rect);
        $width = $pageBox[2] - $pageBox[0];
        $height = $pageBox[3] - $pageBox[1];

        if ($width == 0.0 || $height == 0.0) {
            return $sourceRect;
        }

        $x1 = $sourceRect[0] - $pageBox[0];
        $y1 = $sourceRect[1] - $pageBox[1];
        $x2 = $sourceRect[2] - $pageBox[0];
        $y2 = $sourceRect[3] - $pageBox[1];

        $mapped = $this->normalizeRect(match ($this->normalizedRotation($rotation)) {
            90 => [$y1, $x1, $y2, $x2],
            180 => [$width - $x2, $y1, $width - $x1, $y2],
            270 => [$height - $y2, $width - $x2, $height - $y1, $width - $x1],
            default => [$x1, $height - $y2, $x2, $height - $y1],
        });

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

    private function positiveNumber(mixed $value): ?float
    {
        if (!is_int($value) && !is_float($value)) {
            return null;
        }

        $number = (float) $value;
        return $number > 0.0 ? $number : null;
    }

    /**
     * @param list<array<string, mixed>> $blocks
     * @return list<array<string, mixed>>
     */
    private function pinHeadersAndFooters(array $blocks): array
    {
        $headers = [];
        $regular = [];
        $footers = [];

        foreach ($blocks as $block) {
            $type = (string) ($block['block_type'] ?? $block['type'] ?? '');
            if ($type === 'Page-header') {
                $headers[] = $block;
            } elseif (in_array($type, ['Footnote', 'Page-footer'], true)) {
                $footers[] = $block;
            } else {
                $regular[] = $block;
            }
        }

        return array_merge($headers, $regular, $footers);
    }
}
