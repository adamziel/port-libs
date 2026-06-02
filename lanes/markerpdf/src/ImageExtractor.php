<?php

declare(strict_types=1);

namespace PortLibs\MarkerPDF;

final class ImageExtractor
{
    private LayoutOrderer $layout;
    private DocumentStructureBoundary $boundaries;

    public function __construct(?LayoutOrderer $layout = null, ?DocumentStructureBoundary $boundaries = null)
    {
        $this->layout = $layout ?? new LayoutOrderer();
        $this->boundaries = $boundaries ?? new DocumentStructureBoundary($this->layout);
    }

    /**
     * @param array<string, mixed> $page
     */
    public function getImageFilename(array $page, int $imageIndex): string
    {
        return (string) ((int) ($page['pnum'] ?? 0)) . '_image_' . $imageIndex . '.png';
    }

    /**
     * @param list<array<string, mixed>> $pages
     * @return array<string, mixed>
     */
    public function imagesToDict(array $pages): array
    {
        $images = [];
        foreach ($pages as $page) {
            if (!isset($page['images']) || !is_array($page['images'])) {
                continue;
            }

            foreach (array_values($page['images']) as $imageIndex => $image) {
                $images[$this->getImageFilename($page, $imageIndex)] = $image;
            }
        }

        return $images;
    }

    /**
     * @param list<array<string, mixed>> $blocks
     * @param list<float|int> $bbox
     */
    public function findInsertBlock(array $blocks, array $bbox): int
    {
        $bbox = $this->bbox($bbox) ?? [0.0, 0.0, 0.0, 0.0];
        $nearestMatch = null;
        $matchDistance = null;

        foreach ($blocks as $index => $block) {
            $blockBbox = $this->blockBbox($block);
            if ($blockBbox === null) {
                continue;
            }

            $distance = sqrt(($blockBbox[1] - $bbox[1]) ** 2 + ($blockBbox[0] - $bbox[0]) ** 2);
            if ($nearestMatch === null || $matchDistance === null || $distance < $matchDistance) {
                $nearestMatch = $index;
                $matchDistance = $distance;
            }
        }

        return $nearestMatch ?? 0;
    }

    /**
     * @param array<string, mixed> $page
     * @return list<array{block_index: int, line_index: int, bbox: list<float>}>
     */
    public function findImageBlocks(array $page, float $intersectionThreshold = 0.7): array
    {
        [$imageBlocks] = $this->collectImageBlocks($page, $intersectionThreshold, false);

        return $imageBlocks;
    }

    /**
     * @param array<string, mixed> $page
     * @param list<mixed> $imagePayloads
     * @return array<string, mixed>
     */
    public function insertImagePlaceholders(
        array $page,
        array $imagePayloads = [],
        float $intersectionThreshold = 0.7
    ): array {
        $page['blocks'] = array_values(array_filter(
            $page['blocks'] ?? [],
            static fn (mixed $block): bool => is_array($block)
        ));
        $page['images'] = [];

        [$imageBlocks, $page] = $this->collectImageBlocks($page, $intersectionThreshold, true);
        foreach ($imageBlocks as $imageIndex => $imageBlock) {
            $blockIndex = $imageBlock['block_index'];
            $lineIndex = $imageBlock['line_index'];
            $bbox = $imageBlock['bbox'];

            if ($blockIndex >= count($page['blocks'])) {
                $blockIndex = count($page['blocks']) - 1;
            }
            if ($blockIndex < 0) {
                continue;
            }

            $imageFilename = $this->getImageFilename($page, $imageIndex);
            $imageSpan = [
                'bbox' => $bbox,
                'text' => "\n\n![{$imageFilename}]({$imageFilename})\n\n",
                'font' => 'Image',
                'rotation' => 0,
                'font_weight' => 0,
                'font_size' => 0,
                'image' => true,
                'span_id' => 'image_' . $imageIndex,
            ];

            if (isset($page['blocks'][$blockIndex]['lines'][$lineIndex]) && is_array($page['blocks'][$blockIndex]['lines'][$lineIndex])) {
                if (!isset($page['blocks'][$blockIndex]['lines'][$lineIndex]['spans']) || !is_array($page['blocks'][$blockIndex]['lines'][$lineIndex]['spans'])) {
                    $page['blocks'][$blockIndex]['lines'][$lineIndex]['spans'] = [];
                }
                $page['blocks'][$blockIndex]['lines'][$lineIndex]['spans'][] = $imageSpan;
            } else {
                if (!isset($page['blocks'][$blockIndex]['lines']) || !is_array($page['blocks'][$blockIndex]['lines'])) {
                    $page['blocks'][$blockIndex]['lines'] = [];
                }
                $page['blocks'][$blockIndex]['lines'][] = [
                    'bbox' => $bbox,
                    'spans' => [$imageSpan],
                ];
            }

            $page['images'][] = $imagePayloads[$imageIndex] ?? [
                'filename' => $imageFilename,
                'bbox' => $bbox,
            ];
        }

        return $page;
    }

    /**
     * @param array<string, mixed> $page
     * @return array{0: list<array{block_index: int, line_index: int, bbox: list<float>}>, 1: array<string, mixed>}
     */
    private function collectImageBlocks(array $page, float $intersectionThreshold, bool $clearIntersectingLines): array
    {
        $blocks = array_values(array_filter(
            $page['blocks'] ?? [],
            static fn (mixed $block): bool => is_array($block)
        ));
        $imageRegions = $this->imageRegions($page, $intersectionThreshold);
        $insertPoints = [];

        foreach ($imageRegions as $regionIndex => $region) {
            foreach ($blocks as $blockIndex => $block) {
                foreach (($block['lines'] ?? []) as $lineIndex => $line) {
                    $lineBbox = $this->lineBbox($line);
                    if ($lineBbox === null) {
                        continue;
                    }
                    if ($this->layout->intersectionPct($lineBbox, $region) > $intersectionThreshold) {
                        if ($clearIntersectingLines) {
                            $page['blocks'][$blockIndex]['lines'][$lineIndex]['spans'] = [];
                        }
                        if (!isset($insertPoints[$regionIndex])) {
                            $insertPoints[$regionIndex] = [$blockIndex, $lineIndex];
                        }
                    }
                }
            }
        }

        foreach ($imageRegions as $regionIndex => $region) {
            if (isset($insertPoints[$regionIndex])) {
                continue;
            }

            $insertPoints[$regionIndex] = [$this->findInsertBlock($blocks, $region), 0];
        }

        $imageBlocks = [];
        foreach ($imageRegions as $regionIndex => $region) {
            $insertPoint = $insertPoints[$regionIndex];
            $imageBlocks[] = [
                'block_index' => $insertPoint[0],
                'line_index' => $insertPoint[1],
                'bbox' => $region,
            ];
        }

        return [$imageBlocks, $page];
    }

    /**
     * @param array<string, mixed> $page
     * @return list<list<float>>
     */
    private function imageRegions(array $page, float $intersectionThreshold): array
    {
        return $this->boundaries->rejectContainedRegions(
            $this->boundaries->layoutRegions($page, ['Figure', 'Picture']),
            [
                ...$this->boundaries->layoutRegions($page, ['Table']),
                ...$this->boundaries->layoutRegions($page, ['Formula']),
            ],
            $intersectionThreshold
        );
    }

    /**
     * @param mixed $line
     * @return list<float>|null
     */
    private function lineBbox(mixed $line): ?array
    {
        if (is_array($line)) {
            return $this->bbox($line['bbox'] ?? null);
        }

        return null;
    }

    /**
     * @param array<string, mixed> $block
     * @return list<float>|null
     */
    private function blockBbox(array $block): ?array
    {
        $bbox = $this->bbox($block['bbox'] ?? null);
        if ($bbox !== null) {
            return $bbox;
        }

        $lineBoxes = [];
        foreach (($block['lines'] ?? []) as $line) {
            $lineBbox = $this->lineBbox($line);
            if ($lineBbox !== null) {
                $lineBoxes[] = $lineBbox;
            }
        }
        if ($lineBoxes === []) {
            return null;
        }

        return [
            min(array_column($lineBoxes, 0)),
            min(array_column($lineBoxes, 1)),
            max(array_column($lineBoxes, 2)),
            max(array_column($lineBoxes, 3)),
        ];
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
