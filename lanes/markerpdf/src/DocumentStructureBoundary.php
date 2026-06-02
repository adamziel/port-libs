<?php

declare(strict_types=1);

namespace PortLibs\MarkerPDF;

final class DocumentStructureBoundary
{
    private LayoutOrderer $layout;

    public function __construct(?LayoutOrderer $layout = null)
    {
        $this->layout = $layout ?? new LayoutOrderer();
    }

    /**
     * @param array<string, mixed> $page
     * @param list<string> $labels
     * @return list<list<float>>
     */
    public function layoutRegions(array $page, array $labels): array
    {
        $layout = $page['layout'] ?? [];
        $layoutImageBbox = is_array($layout) ? $this->bbox($layout['image_bbox'] ?? null) : null;
        $pageBbox = $this->bbox($page['bbox'] ?? null);

        if ($labels === ['Table']) {
            return $this->tableLayoutRegions($page, $layoutImageBbox, $pageBbox);
        }

        $regions = [];

        foreach ($this->layoutBoxes($page) as $box) {
            $label = (string) ($box['label'] ?? '');
            if (!in_array($label, $labels, true)) {
                continue;
            }

            $bbox = $this->bbox($box['bbox'] ?? null);
            if ($bbox === null) {
                continue;
            }

            $regions[] = $layoutImageBbox !== null && $pageBbox !== null
                ? $this->layout->rescaleBbox($layoutImageBbox, $pageBbox, $bbox)
                : $bbox;
        }

        return $regions;
    }

    /**
     * @param list<float>|null $layoutImageBbox
     * @param list<float>|null $pageBbox
     * @return list<list<float>>
     */
    private function tableLayoutRegions(array $page, ?array $layoutImageBbox, ?array $pageBbox): array
    {
        $tableBoxes = [];
        foreach ($this->layoutBoxes($page) as $box) {
            if (($box['label'] ?? '') !== 'Table') {
                continue;
            }

            $bbox = $this->bbox($box['bbox'] ?? null);
            if ($bbox !== null) {
                $tableBoxes[] = $bbox;
            }
        }

        $regions = [];
        foreach ($this->mergedTableBoxes($tableBoxes) as $bbox) {
            if (($bbox[3] - $bbox[1]) <= 10.0 || ($bbox[2] - $bbox[0]) <= 10.0) {
                continue;
            }

            $regions[] = $layoutImageBbox !== null && $pageBbox !== null
                ? $this->layout->rescaleBbox($layoutImageBbox, $pageBbox, $bbox)
                : $bbox;
        }

        return $regions;
    }

    /**
     * Mirrors tabled.inference.detection::merge_tables before downstream
     * formula/image arbitration uses table regions as protected boundaries.
     *
     * @param list<list<float>> $tableBoxes
     * @return list<list<float>>
     */
    private function mergedTableBoxes(array $tableBoxes): array
    {
        $expansionFactor = 1.02;
        $shrinkFactor = 0.98;
        $ignored = [];

        for ($i = 0; $i < count($tableBoxes); $i++) {
            if (isset($ignored[$i])) {
                continue;
            }

            for ($j = $i + 1; $j < count($tableBoxes); $j++) {
                if (isset($ignored[$j])) {
                    continue;
                }

                $expandedLeft = [
                    $tableBoxes[$i][0] * $shrinkFactor,
                    $tableBoxes[$i][1],
                    $tableBoxes[$i][2] * $expansionFactor,
                    $tableBoxes[$i][3],
                ];
                $expandedRight = [
                    $tableBoxes[$j][0] * $shrinkFactor,
                    $tableBoxes[$j][1],
                    $tableBoxes[$j][2] * $expansionFactor,
                    $tableBoxes[$j][3],
                ];

                if ($this->layout->intersectionPct($expandedLeft, $expandedRight) > 0.0) {
                    $tableBoxes[$i] = [
                        min($tableBoxes[$i][0], $tableBoxes[$j][0]),
                        min($tableBoxes[$i][1], $tableBoxes[$j][1]),
                        max($tableBoxes[$i][2], $tableBoxes[$j][2]),
                        max($tableBoxes[$i][3], $tableBoxes[$j][3]),
                    ];
                    $ignored[$j] = true;
                }
            }
        }

        $merged = [];
        foreach ($tableBoxes as $index => $bbox) {
            if (!isset($ignored[$index])) {
                $merged[] = $bbox;
            }
        }

        return $merged;
    }

    /**
     * @param list<list<float>> $regions
     * @param list<list<float>> $protectedRegions
     * @return list<list<float>>
     */
    public function rejectContainedRegions(array $regions, array $protectedRegions, float $intersectionThreshold): array
    {
        return array_values(array_filter(
            $regions,
            fn (array $region): bool => !$this->isContainedByAny($region, $protectedRegions, $intersectionThreshold)
        ));
    }

    /**
     * @param list<float> $region
     * @param list<list<float>> $protectedRegions
     */
    public function isContainedByAny(array $region, array $protectedRegions, float $intersectionThreshold): bool
    {
        foreach ($protectedRegions as $protectedRegion) {
            if ($this->layout->intersectionPct($region, $protectedRegion) > $intersectionThreshold) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<string, mixed> $page
     * @return list<array<string, mixed>>
     */
    private function layoutBoxes(array $page): array
    {
        $layout = $page['layout'] ?? [];
        if (is_array($layout) && isset($layout['bboxes']) && is_array($layout['bboxes'])) {
            return array_values(array_filter($layout['bboxes'], static fn (mixed $box): bool => is_array($box)));
        }
        if (isset($page['layout_boxes']) && is_array($page['layout_boxes'])) {
            return array_values(array_filter($page['layout_boxes'], static fn (mixed $box): bool => is_array($box)));
        }

        return [];
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
