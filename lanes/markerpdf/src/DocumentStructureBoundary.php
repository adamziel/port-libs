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
