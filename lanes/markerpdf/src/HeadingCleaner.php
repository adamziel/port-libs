<?php

declare(strict_types=1);

namespace PortLibs\MarkerPDF;

use InvalidArgumentException;

final class HeadingCleaner
{
    private const HEADING_TYPES = ['Title', 'Section-header'];
    private const DEFAULT_HEADING_LEVEL = 2;

    /**
     * @param list<array<string, mixed>> $pages
     * @return list<array<string, mixed>>
     */
    public function splitHeadingBlocks(array $pages, float $intersectionThreshold = 0.7): array
    {
        foreach ($pages as $pageIndex => $page) {
            $headingBoxes = $this->headingBoxes($page);
            if ($headingBoxes === []) {
                continue;
            }

            $newBlocks = [];
            foreach (($page['blocks'] ?? []) as $block) {
                $blockType = $this->blockType($block);
                if ($blockType !== 'Text') {
                    $newBlocks[] = $block;
                    continue;
                }

                $headingLines = [];
                foreach (($block['lines'] ?? []) as $lineIndex => $line) {
                    foreach ($headingBoxes as $headingBox) {
                        if ($this->intersectionPct($this->lineBbox($line), $headingBox['bbox']) > $intersectionThreshold) {
                            $headingLines[] = [$lineIndex, $headingBox['label']];
                            break;
                        }
                    }
                }

                if ($headingLines === []) {
                    $newBlocks[] = $block;
                    continue;
                }

                $start = 0;
                foreach ($headingLines as [$headingLine, $label]) {
                    if ($start < $headingLine) {
                        $newBlocks[] = $this->copyBlockWithLines($block, array_slice($block['lines'], $start, $headingLine - $start), $blockType);
                    }

                    $newBlocks[] = $this->copyBlockWithLines($block, [$block['lines'][$headingLine]], $label);
                    $start = $headingLine + 1;
                    if ($start >= count($block['lines'])) {
                        break;
                    }
                }

                if ($start < count($block['lines'])) {
                    $newBlocks[] = $this->copyBlockWithLines($block, array_slice($block['lines'], $start), $blockType);
                }
            }

            $pages[$pageIndex]['blocks'] = $newBlocks;
        }

        return $pages;
    }

    /**
     * @param list<float|int> $lineHeights
     * @return list<array{0: float, 1: float}>
     */
    public function bucketHeadings(array $lineHeights, int $numLevels = 4, float $mergeThreshold = 0.25): array
    {
        $lineHeights = array_values(array_map(static fn (float|int $height): float => (float) $height, $lineHeights));
        if (count($lineHeights) <= $numLevels) {
            return [];
        }

        sort($lineHeights, SORT_NUMERIC);
        $clusters = $this->clusterHeights($lineHeights, $numLevels);
        $headingRanges = [];
        $rangeMin = null;
        $rangeMax = null;
        $previousMean = null;

        foreach ($clusters as $cluster) {
            $clusterMin = min($cluster);
            $clusterMax = max($cluster);
            $clusterMean = array_sum($cluster) / count($cluster);

            if ($previousMean !== null && $clusterMean * $mergeThreshold < $previousMean) {
                $headingRanges[] = [$rangeMin, $rangeMax];
                $rangeMin = null;
                $rangeMax = null;
            }

            $rangeMin = $rangeMin === null ? $clusterMin : min($rangeMin, $clusterMin);
            $rangeMax = $rangeMax === null ? $clusterMax : max($rangeMax, $clusterMax);
            $previousMean = $clusterMean;
        }

        if ($rangeMin !== null && $rangeMax !== null) {
            $headingRanges[] = [$rangeMin, $rangeMax];
        }

        usort(
            $headingRanges,
            static fn (array $left, array $right): int => $right[1] <=> $left[1]
        );

        return $headingRanges;
    }

    /**
     * @param list<array<string, mixed>> $pages
     * @return list<array<string, mixed>>
     */
    public function inferHeadingLevels(array $pages, float $heightTolerance = 0.99): array
    {
        $lineHeights = [];
        foreach ($pages as $page) {
            foreach (($page['blocks'] ?? []) as $block) {
                if (!in_array($this->blockType($block), self::HEADING_TYPES, true)) {
                    continue;
                }

                foreach (($block['lines'] ?? []) as $line) {
                    $lineHeights[] = $this->lineHeight($line);
                }
            }
        }

        $headingRanges = $this->bucketHeadings($lineHeights);
        foreach ($pages as $pageIndex => $page) {
            foreach (($page['blocks'] ?? []) as $blockIndex => $block) {
                if (!in_array($this->blockType($block), self::HEADING_TYPES, true)) {
                    continue;
                }

                $blockHeights = array_map(fn (array|string $line): float => $this->lineHeight($line), $block['lines'] ?? []);
                if ($blockHeights !== []) {
                    $averageHeight = array_sum($blockHeights) / count($blockHeights);
                    foreach ($headingRanges as $rangeIndex => [$minHeight]) {
                        if ($averageHeight >= $minHeight * $heightTolerance) {
                            $pages[$pageIndex]['blocks'][$blockIndex]['heading_level'] = $rangeIndex + 1;
                            break;
                        }
                    }
                }

                if (!isset($pages[$pageIndex]['blocks'][$blockIndex]['heading_level'])) {
                    $pages[$pageIndex]['blocks'][$blockIndex]['heading_level'] = self::DEFAULT_HEADING_LEVEL;
                }
            }
        }

        return $pages;
    }

    /**
     * Native boundary for marker.cleaners.toc::get_pdf_toc.
     *
     * @return list<array{title: string, level: int, page: int}>
     */
    public function getPdfToc(object $doc, int $maxDepth = 15): array
    {
        if (!method_exists($doc, 'get_toc')) {
            throw new InvalidArgumentException('PDF document adapter must expose get_toc(max_depth).');
        }

        $items = $doc->get_toc($maxDepth);
        if (!is_iterable($items)) {
            throw new InvalidArgumentException('PDF document get_toc(max_depth) must return an iterable list.');
        }

        $toc = [];
        foreach ($items as $item) {
            if (!is_array($item) && !is_object($item)) {
                throw new InvalidArgumentException('PDF TOC item must be an object or array.');
            }

            $title = $this->outlineValue($item, 'title');
            $level = $this->outlineValue($item, 'level');
            $page = $this->outlineValue($item, 'page_index');

            $toc[] = [
                'title' => (string) $title,
                'level' => (int) $level,
                'page' => (int) $page,
            ];
        }

        return $toc;
    }

    /**
     * @param list<array<string, mixed>> $pages
     * @return list<array{title: string, level: int|null, page: int|null}>
     */
    public function computeToc(array $pages): array
    {
        $toc = [];
        foreach ($pages as $page) {
            foreach (($page['blocks'] ?? []) as $block) {
                if (!in_array($this->blockType($block), self::HEADING_TYPES, true)) {
                    continue;
                }

                $toc[] = [
                    'title' => $this->blockText($block),
                    'level' => isset($block['heading_level']) ? (int) $block['heading_level'] : null,
                    'page' => isset($page['pnum']) ? (int) $page['pnum'] : null,
                ];
            }
        }

        return $toc;
    }

    /**
     * @param list<float> $sortedHeights
     * @return list<list<float>>
     */
    private function clusterHeights(array $sortedHeights, int $numLevels): array
    {
        $uniqueCount = count(array_unique(array_map(static fn (float $height): string => (string) $height, $sortedHeights)));
        $clusterCount = max(1, min($numLevels, $uniqueCount, count($sortedHeights)));
        if ($clusterCount === 1) {
            return [$sortedHeights];
        }

        $clusters = [];
        for ($clusterIndex = 0; $clusterIndex < $clusterCount; $clusterIndex++) {
            $start = (int) floor($clusterIndex * count($sortedHeights) / $clusterCount);
            $end = (int) floor(($clusterIndex + 1) * count($sortedHeights) / $clusterCount);
            $clusters[] = array_slice($sortedHeights, $start, max(1, $end - $start));
        }

        for ($iteration = 0; $iteration < 20; $iteration++) {
            $means = array_map(static fn (array $cluster): float => array_sum($cluster) / count($cluster), $clusters);
            $nextClusters = array_fill(0, $clusterCount, []);
            foreach ($sortedHeights as $height) {
                $nearest = 0;
                $nearestDistance = abs($height - $means[0]);
                foreach ($means as $meanIndex => $mean) {
                    $distance = abs($height - $mean);
                    if ($distance < $nearestDistance) {
                        $nearest = $meanIndex;
                        $nearestDistance = $distance;
                    }
                }
                $nextClusters[$nearest][] = $height;
            }

            $nextClusters = array_values(array_filter($nextClusters, static fn (array $cluster): bool => $cluster !== []));
            if ($nextClusters === $clusters) {
                break;
            }
            $clusters = $nextClusters;
            $clusterCount = count($clusters);
        }

        usort(
            $clusters,
            static fn (array $left, array $right): int => (array_sum($left) / count($left)) <=> (array_sum($right) / count($right))
        );

        return $clusters;
    }

    /**
     * @param array<string, mixed> $page
     * @return list<array{label: string, bbox: list<float>}>
     */
    private function headingBoxes(array $page): array
    {
        $boxes = $page['layout_boxes'] ?? $page['layout']['bboxes'] ?? [];
        $result = [];
        foreach ($boxes as $box) {
            $label = (string) ($box['label'] ?? '');
            if (!in_array($label, self::HEADING_TYPES, true)) {
                continue;
            }
            $bbox = $box['bbox'] ?? null;
            if (!is_array($bbox) || count($bbox) !== 4) {
                continue;
            }
            $result[] = [
                'label' => $label,
                'bbox' => array_map(static fn (float|int $value): float => (float) $value, array_values($bbox)),
            ];
        }

        return $result;
    }

    /**
     * @param array<string, mixed> $block
     * @param list<array<string, mixed>|string> $lines
     * @return array<string, mixed>
     */
    private function copyBlockWithLines(array $block, array $lines, string $type): array
    {
        $block['lines'] = array_values($lines);
        $block['type'] = $type;
        $block['block_type'] = $type;
        $block['bbox'] = $this->bboxFromLines($lines);

        return $block;
    }

    /**
     * @param list<array<string, mixed>|string> $lines
     * @return list<float>
     */
    private function bboxFromLines(array $lines): array
    {
        $boxes = array_values(array_filter(array_map(fn (array|string $line): array => $this->lineBbox($line), $lines)));
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
     * @param array<string, mixed>|string $line
     * @return list<float>
     */
    private function lineBbox(array|string $line): array
    {
        if (is_array($line) && isset($line['bbox']) && is_array($line['bbox']) && count($line['bbox']) === 4) {
            return array_map(static fn (float|int $value): float => (float) $value, array_values($line['bbox']));
        }

        return [0.0, 0.0, 0.0, 0.0];
    }

    /**
     * @param array<string, mixed>|string $line
     */
    private function lineHeight(array|string $line): float
    {
        if (is_array($line)) {
            if (isset($line['height'])) {
                return (float) $line['height'];
            }
            $bbox = $this->lineBbox($line);
            return $bbox[3] - $bbox[1];
        }

        return 0.0;
    }

    /**
     * @param list<float> $left
     * @param list<float> $right
     */
    private function intersectionPct(array $left, array $right): float
    {
        $xLeft = max($left[0], $right[0]);
        $yTop = max($left[1], $right[1]);
        $xRight = min($left[2], $right[2]);
        $yBottom = min($left[3], $right[3]);

        if ($xRight < $xLeft || $yBottom < $yTop) {
            return 0.0;
        }

        $leftArea = ($left[2] - $left[0]) * ($left[3] - $left[1]);
        if ($leftArea === 0.0) {
            return 0.0;
        }

        return (($xRight - $xLeft) * ($yBottom - $yTop)) / $leftArea;
    }

    /**
     * @param array<string, mixed> $block
     */
    private function blockType(array $block): string
    {
        return (string) ($block['type'] ?? $block['block_type'] ?? 'Text');
    }

    /**
     * @param array<string, mixed> $block
     */
    private function blockText(array $block): string
    {
        if (isset($block['text'])) {
            return (string) $block['text'];
        }
        if (isset($block['prelim_text'])) {
            return (string) $block['prelim_text'];
        }

        $lines = [];
        foreach (($block['lines'] ?? []) as $line) {
            if (is_string($line)) {
                $lines[] = $line;
                continue;
            }
            if (isset($line['text'])) {
                $lines[] = (string) $line['text'];
                continue;
            }
            if (isset($line['prelim_text'])) {
                $lines[] = (string) $line['prelim_text'];
                continue;
            }
            if (isset($line['spans']) && is_array($line['spans'])) {
                $lines[] = implode('', array_map(static fn (array $span): string => (string) ($span['text'] ?? ''), $line['spans']));
            }
        }

        return implode("\n", $lines);
    }

    /**
     * @param array<string, mixed>|object $item
     */
    private function outlineValue(array|object $item, string $key): mixed
    {
        if (is_array($item) && array_key_exists($key, $item)) {
            return $item[$key];
        }

        if (is_object($item) && (property_exists($item, $key) || isset($item->{$key}))) {
            return $item->{$key};
        }

        throw new InvalidArgumentException("PDF TOC item is missing {$key}.");
    }
}
