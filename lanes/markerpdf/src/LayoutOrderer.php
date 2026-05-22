<?php

declare(strict_types=1);

namespace PortLibs\MarkerPDF;

final class LayoutOrderer
{
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
