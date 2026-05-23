<?php

declare(strict_types=1);

namespace PortLibs\MarkerPDF;

use InvalidArgumentException;

final class LayoutOrderer
{
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
        float $batchMultiplier = 1.0
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

        $assignedPages = min(count($pages), count($orderResults));
        for ($index = 0; $index < $assignedPages; $index++) {
            if (!is_array($orderResults[$index])) {
                throw new InvalidArgumentException('Supplied ordering predictions must be arrays.');
            }
            $pages[$index]['order'] = $orderResults[$index];
        }

        return [
            'pages' => $pages,
            'plan' => [
                'image_count' => count($images),
                'page_count' => count($pages),
                'layout_bbox_counts' => $bboxCounts,
                'requested_bboxes' => $requestedBboxes,
                'order_result_count' => count($orderResults),
                'assigned_pages' => $assignedPages,
                'batch_size' => $this->batchSize($batchMultiplier),
                'order_max_bboxes' => $maxBboxes,
            ],
        ];
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
                foreach ($orderBoxes as $orderBox) {
                    $position = (int) ($orderBox['position'] ?? 0);
                    $orderBbox = $this->rescaleOrderBbox($page, $this->bbox($orderBox));
                    $intersection = $this->intersectionPct($this->bbox($block), $orderBbox);

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
                array_push($newBlocks, ...$this->sortBlockGroup($blockGroup));
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
        $groups = [];
        foreach ($blocks as $index => $block) {
            $bbox = $this->bbox($block);
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
            return array_values(array_filter($order['bboxes'], static fn (mixed $box): bool => is_array($box)));
        }
        if (isset($page['order_bboxes']) && is_array($page['order_bboxes'])) {
            return array_values(array_filter($page['order_bboxes'], static fn (mixed $box): bool => is_array($box)));
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

            $bbox = $this->bboxValue($box['bbox'] ?? null);
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
        if (!is_array($value) || count($value) !== 4) {
            return null;
        }

        foreach ($value as $item) {
            if (!is_float($item) && !is_int($item)) {
                return null;
            }
        }

        return array_map(static fn (float|int $item): float => (float) $item, array_values($value));
    }

    /**
     * @param array<string, mixed> $page
     * @param list<float> $bbox
     * @return list<float>
     */
    private function rescaleOrderBbox(array $page, array $bbox): array
    {
        $order = $page['order'] ?? [];
        $imageBbox = is_array($order) ? ($order['image_bbox'] ?? null) : null;
        $pageBbox = $page['bbox'] ?? null;

        if (
            is_array($imageBbox)
            && count($imageBbox) === 4
            && is_array($pageBbox)
            && count($pageBbox) === 4
        ) {
            return $this->rescaleBbox(
                array_map(static fn (float|int $value): float => (float) $value, array_values($imageBbox)),
                array_map(static fn (float|int $value): float => (float) $value, array_values($pageBbox)),
                $bbox
            );
        }

        return $bbox;
    }

    /**
     * @param array<string, mixed> $block
     * @return list<float>
     */
    private function bbox(array $block): array
    {
        if (isset($block['bbox']) && is_array($block['bbox']) && count($block['bbox']) === 4) {
            return array_map(static fn (float|int $value): float => (float) $value, array_values($block['bbox']));
        }

        $lineBoxes = [];
        foreach (($block['lines'] ?? []) as $line) {
            if (is_array($line) && isset($line['bbox']) && is_array($line['bbox']) && count($line['bbox']) === 4) {
                $lineBoxes[] = array_map(static fn (float|int $value): float => (float) $value, array_values($line['bbox']));
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
