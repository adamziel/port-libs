<?php

declare(strict_types=1);

namespace PortLibs\MarkerPDF;

use InvalidArgumentException;

final class LayoutAnnotator
{
    private const AMBIGUOUS_PAGE_MARKER_WRAPPER = '__markerpdf_ambiguous_page_marker_wrapper';
    private const LAYOUT_RESULT_PAGE_MARKER_KEYS = [
        'page_index',
        'doc_page_index',
        'document_page_index',
        'source_page_index',
        'selected_page_index',
        'trimmed_page_index',
        'relative_page_index',
        'pnum',
        'page',
        'pdftext_page',
        'source_page',
        'document_page',
        'page_number',
        'selected_page_number',
        'trimmed_page_number',
        'relative_page_number',
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
        'prediction',
        'result',
        'model_output',
        'output',
    ];
    private const LAYOUT_RESULT_PAGE_MARKER_FIELD_GROUPS = [
        ['page_index', 'doc_page_index', 'document_page_index', 'source_page_index', 'page_range', 'source_page_range', 'document_page_range', 'page_indices', 'source_page_indices', 'document_page_indices'],
        ['selected_page_index', 'trimmed_page_index', 'relative_page_index', 'selected_page_range', 'trimmed_page_range', 'relative_page_range', 'selected_page_indices', 'trimmed_page_indices', 'relative_page_indices'],
        ['pnum', 'page', 'pdftext_page', 'source_page', 'document_page'],
        ['page_number'],
        ['selected_page_number', 'trimmed_page_number', 'relative_page_number'],
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
     * @param list<array<string, mixed>> $layoutResults
     * @return array{
     *     pages: list<array<string, mixed>>,
     *     plan: array{image_count: int, page_count: int, detection_result_count: int, layout_result_count: int, assigned_pages: int, batch_size: int}
     * }
     */
    public function runWithSuppliedLayouts(
        array $images,
        array $pages,
        array $layoutResults,
        float $batchMultiplier = 1.0
    ): array {
        $pages = array_values($pages);
        $layoutResults = array_values($layoutResults);
        $assignedPages = 0;
        $assignmentSlots = min(count($pages), count($layoutResults));

        for ($index = 0; $index < $assignmentSlots; $index++) {
            if (PdfPageArtifactSelector::isMissingPageArtifact($layoutResults[$index])) {
                continue;
            }
            if (!is_array($layoutResults[$index])) {
                throw new InvalidArgumentException('Supplied layout predictions must be arrays.');
            }
            if ($this->hasAmbiguousLayoutPayloadWrapper($layoutResults[$index]) || $this->hasMalformedLayoutPageMarkers($layoutResults[$index])) {
                continue;
            }
            $pages[$index]['layout'] = $this->sanitizeSuppliedLayoutResult($layoutResults[$index]);
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
     * Supplied adapters sometimes wrap selected page identity and pdftext page
     * copies around the model payload. Marker layout metadata only needs the
     * geometry and scalar page markers before annotation.
     *
     * @param array<string, mixed> $layoutResult
     * @return array<string, mixed>
     */
    private function sanitizeSuppliedLayoutResult(array $layoutResult): array
    {
        $sanitized = [];
        $payload = $this->layoutResultPayloadSource($layoutResult);
        foreach (['image_bbox', 'bboxes'] as $key) {
            if (array_key_exists($key, $payload)) {
                $sanitized[$key] = $payload[$key];
            }
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
     * Adapter serializers may wrap the Surya `LayoutResult` object under a
     * typed result key while keeping page identity at the outer level.
     *
     * @param array<string, mixed> $layoutResult
     * @return array<string, mixed>
     */
    private function layoutResultPayloadSource(array $layoutResult): array
    {
        $sources = [];
        $this->collectLayoutResultPayloadSources($layoutResult, $sources);

        foreach ($sources as $source) {
            if ($this->hasLayoutPayload($source)) {
                return $source;
            }
        }

        return $layoutResult;
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
            $bbox = $this->bbox($box);
            if ($bbox === null) {
                continue;
            }

            $layoutBoxes[] = [
                'label' => (string) ($box['label'] ?? ''),
                'bbox' => $bbox,
            ];
        }

        return $layoutBoxes;
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
            return $this->bbox($value['bbox'])
                ?? $this->bboxFromNamedFields($value)
                ?? $this->polygonBbox($value['polygon'] ?? null);
        }

        return $this->bboxFromNamedFields($value)
            ?? $this->polygonBbox($value['polygon'] ?? null)
            ?? $this->bboxFromCoordinateList($value);
    }

    /**
     * @param mixed $bbox
     * @return list<float>|null
     */
    private function bboxFromCoordinateList(mixed $bbox): ?array
    {
        if (!is_array($bbox) || count($bbox) !== 4) {
            return null;
        }

        $coordinates = $this->numericCoordinates(array_values($bbox));
        if ($coordinates === null) {
            return null;
        }

        return $this->canonicalBbox($coordinates);
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

        return null;
    }

    /**
     * @param mixed $polygon
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
            if (!is_array($point) || count($point) !== 2) {
                return null;
            }

            $coordinates = $this->numericCoordinates(array_values($point));
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
