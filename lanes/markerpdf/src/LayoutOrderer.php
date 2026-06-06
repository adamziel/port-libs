<?php

declare(strict_types=1);

namespace PortLibs\MarkerPDF;

use InvalidArgumentException;

final class LayoutOrderer
{
    private const AMBIGUOUS_PAGE_MARKER_WRAPPER = '__markerpdf_ambiguous_page_marker_wrapper';
    private const ORDER_RESULT_PAGE_MARKER_KEYS = [
        'page_index',
        'page_idx',
        'doc_page_index',
        'doc_page_idx',
        'document_page_index',
        'document_page_idx',
        'source_page_index',
        'source_page_idx',
        'selected_page_index',
        'selected_page_idx',
        'trimmed_page_index',
        'trimmed_page_idx',
        'relative_page_index',
        'relative_page_idx',
        'pnum',
        'page',
        'pdftext_page',
        'source_page',
        'document_page',
        'page_number',
        'page_num',
        'selected_page_number',
        'trimmed_page_number',
        'relative_page_number',
        'selected_page_num',
        'trimmed_page_num',
        'relative_page_num',
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
        'prediction',
        'result',
        'model_output',
        'output',
    ];
    private const ORDER_RESULT_PAGE_MARKER_FIELD_GROUPS = [
        ['page_index', 'page_idx', 'doc_page_index', 'doc_page_idx', 'document_page_index', 'document_page_idx', 'source_page_index', 'source_page_idx', 'page_range', 'source_page_range', 'document_page_range', 'page_indices', 'source_page_indices', 'document_page_indices'],
        ['selected_page_index', 'selected_page_idx', 'trimmed_page_index', 'trimmed_page_idx', 'relative_page_index', 'relative_page_idx', 'selected_page_range', 'trimmed_page_range', 'relative_page_range', 'selected_page_indices', 'trimmed_page_indices', 'relative_page_indices'],
        ['pnum', 'page', 'pdftext_page', 'source_page', 'document_page'],
        ['page_number', 'page_num'],
        ['selected_page_number', 'trimmed_page_number', 'relative_page_number', 'selected_page_num', 'trimmed_page_num', 'relative_page_num'],
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
     * @param list<array<string, mixed>> $orderResults
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
        $pages = array_values($pages);
        $orderResults = array_values($orderResults);
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
            if ($this->hasAmbiguousOrderPayloadWrapper($orderResults[$index]) || $this->hasMalformedOrderPageMarkers($orderResults[$index])) {
                continue;
            }
            $pages[$index]['order'] = $this->sanitizeSuppliedOrderResult(
                $orderResults[$index],
                $pages[$index],
                $index,
                $this->integerValue($pageRange[$index] ?? null) ?? $index
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
        $payload = $this->orderResultPayloadSource($orderResult);

        $imageBbox = $this->bboxValue($payload['image_bbox'] ?? null);
        if ($imageBbox !== null) {
            $sanitized['image_bbox'] = $imageBbox;
        }

        if (array_key_exists('bboxes', $payload)) {
            $sanitized['bboxes'] = $this->sanitizeSuppliedOrderBboxes($payload['bboxes'], $page, $selectedIndex, $sourceIndex);
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
    private function orderResultPayloadSource(array $orderResult): array
    {
        $sources = [];
        $this->collectOrderResultPayloadSources($orderResult, $sources);

        foreach ($sources as $source) {
            if ($this->hasOrderPayload($source)) {
                return $source;
            }
        }

        return $orderResult;
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
        ?int $sourceIndex = null
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

            $bbox = $this->bboxValue($box);
            if ($bbox === null) {
                continue;
            }
            if ($this->rectWidth($bbox) <= 0.0 || $this->rectHeight($bbox) <= 0.0) {
                continue;
            }

            $position = $index + 1;
            if (array_key_exists('position', $box)) {
                $positionValue = $this->integerValue($box['position']);
                if ($positionValue === null) {
                    continue;
                }
                $position = $positionValue;
            }

            $sanitized[] = [
                'position' => $position,
                'bbox' => $bbox,
            ];
        }

        return $sanitized;
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
            $blockPositions = [];
            $maxPosition = 0;

            foreach ($blocks as $blockIndex => $block) {
                $blockBbox = $this->blockBboxForOrdering($page, $block);
                foreach ($orderBoxes as $orderBox) {
                    $position = (int) ($orderBox['position'] ?? 0);
                    $orderBbox = $this->rescaleOrderBbox($page, $this->bbox($orderBox));
                    $intersection = $this->intersectionPct($blockBbox, $orderBbox);

                    if (!isset($blockPositions[$blockIndex]) || $intersection > $blockPositions[$blockIndex][0]) {
                        $blockPositions[$blockIndex] = [$intersection, $position];
                    }
                    $maxPosition = max($maxPosition, $position);
                }
            }

            $blockGroups = [];
            foreach ($blocks as $blockIndex => $block) {
                if (isset($blockPositions[$blockIndex])) {
                    $position = $blockPositions[$blockIndex][1];
                } else {
                    $maxPosition++;
                    $position = $maxPosition;
                }
                $blockGroups[$position][] = $block;
            }

            ksort($blockGroups, SORT_NUMERIC);
            $newBlocks = [];
            foreach ($blockGroups as $blockGroup) {
                array_push($newBlocks, ...$this->sortBlockGroupForPage($page, $blockGroup));
            }

            $pages[$pageIndex]['blocks'] = $this->pinHeadersAndFooters($newBlocks);
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
            return $this->bboxValue($value['bbox'])
                ?? $this->bboxFromNamedFields($value)
                ?? $this->polygonBbox($value['polygon'] ?? null);
        }

        return $this->bboxFromNamedFields($value)
            ?? $this->polygonBbox($value['polygon'] ?? null)
            ?? $this->bboxFromCoordinateList($value);
    }

    /**
     * @param mixed $value
     * @return list<float>|null
     */
    private function bboxFromCoordinateList(mixed $value): ?array
    {
        if (!is_array($value) || !array_is_list($value) || count($value) !== 4) {
            return null;
        }

        $coordinates = $this->numericCoordinates(array_values($value));
        if ($coordinates === null) {
            return null;
        }

        return $this->normalizeRect($coordinates);
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

        return null;
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
        if (!is_array($polygon) || count($polygon) !== 4) {
            return null;
        }

        $xs = [];
        $ys = [];
        foreach (array_values($polygon) as $point) {
            $coordinates = $this->pointCoordinates($point);
            if ($coordinates === null) {
                return null;
            }
            $xs[] = $coordinates[0];
            $ys[] = $coordinates[1];
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
