<?php

declare(strict_types=1);

namespace PortLibs\MarkerPDF;

final class LayoutAnnotator
{
    private LayoutOrderer $layout;

    public function __construct(?LayoutOrderer $layout = null)
    {
        $this->layout = $layout ?? new LayoutOrderer();
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
            $bbox = $this->bbox($box['bbox'] ?? null);
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

        return $this->layout->rescaleBbox($imageBbox, $pageBbox, $bbox);
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
        if (isset($value['bbox'])) {
            return $this->bbox($value['bbox']);
        }

        $values = array_values($value);
        if (count($values) !== 4) {
            return null;
        }

        foreach ($values as $item) {
            if (!is_float($item) && !is_int($item)) {
                return null;
            }
        }

        return array_map(static fn (float|int $item): float => (float) $item, $values);
    }
}
