<?php

declare(strict_types=1);

namespace PortLibs\MarkerPDF;

use InvalidArgumentException;

final class TableFormatter
{
    private const DEFAULT_INTERSECTION_THRESHOLD = 0.7;

    private LayoutOrderer $layout;

    public function __construct(?LayoutOrderer $layout = null)
    {
        $this->layout = $layout ?? new LayoutOrderer();
    }

    /**
     * @param array<string, mixed> $page
     * @return list<array{bbox: list<float>, page_bbox: list<float>}>
     */
    public function findTableBlocks(array $page): array
    {
        return array_map(
            static fn (array $region): array => [
                'bbox' => $region['bbox'],
                'page_bbox' => $region['page_bbox'],
            ],
            $this->tableRegions($page)
        );
    }

    /**
     * Native boundary for the post-recognition half of marker.tables.table::format_tables.
     *
     * @param list<array<string, mixed>> $pages
     * @param list<string> $markdownTables Markdown strings in upstream recognized-table order.
     * @return array{pages: list<array<string, mixed>>, table_count: int, inserted_tables: int}
     */
    public function formatTables(
        array $pages,
        array $markdownTables,
        float $intersectionThreshold = self::DEFAULT_INTERSECTION_THRESHOLD
    ): array {
        $processedTables = 0;
        $insertedTables = 0;

        foreach ($pages as $pageIndex => $page) {
            $blocks = array_values(array_filter(
                $page['blocks'] ?? [],
                static fn (mixed $block): bool => is_array($block)
            ));
            $page['blocks'] = $blocks;
            $tableRegions = $this->tableRegions($page);

            if ($tableRegions === []) {
                $pages[$pageIndex] = $page;
                continue;
            }

            $insertPoints = [];
            $blocksToRemove = [];
            foreach ($tableRegions as $tableIndex => $region) {
                foreach ($blocks as $blockIndex => $block) {
                    if ($this->blockType($block) !== 'Table') {
                        continue;
                    }

                    $blockBbox = $this->blockBbox($block);
                    if ($blockBbox === null) {
                        continue;
                    }

                    if ($this->layout->intersectionPct($blockBbox, $region['page_bbox']) > $intersectionThreshold) {
                        if (!isset($insertPoints[$tableIndex])) {
                            $insertPoints[$tableIndex] = max(0, $blockIndex - count($blocksToRemove));
                        }
                        $blocksToRemove[$blockIndex] = true;
                    }
                }
            }

            $newPageBlocks = [];
            foreach ($blocks as $blockIndex => $block) {
                if (!isset($blocksToRemove[$blockIndex])) {
                    $newPageBlocks[] = $block;
                }
            }

            foreach ($tableRegions as $tableIndex => $region) {
                if (!isset($insertPoints[$tableIndex])) {
                    $processedTables++;
                    continue;
                }

                if (!array_key_exists($processedTables, $markdownTables)) {
                    throw new InvalidArgumentException('Missing Markdown table for recognized table index ' . $processedTables . '.');
                }

                $tableBlock = $this->tableBlock($region['bbox'], (string) $markdownTables[$processedTables], (int) ($page['pnum'] ?? 0), $tableIndex);
                $insertPoint = min((int) $insertPoints[$tableIndex], count($newPageBlocks));
                array_splice($newPageBlocks, $insertPoint, 0, [$tableBlock]);
                $processedTables++;
                $insertedTables++;
            }

            $page['blocks'] = $newPageBlocks;
            $pages[$pageIndex] = $page;
        }

        return [
            'pages' => array_values($pages),
            'table_count' => $processedTables,
            'inserted_tables' => $insertedTables,
        ];
    }

    /**
     * @param array<string, mixed> $page
     * @return list<array{bbox: list<float>, page_bbox: list<float>}>
     */
    private function tableRegions(array $page): array
    {
        $boxes = [];
        $layout = $page['layout'] ?? [];
        if (is_array($layout) && isset($layout['bboxes']) && is_array($layout['bboxes'])) {
            $boxes = $layout['bboxes'];
        } elseif (isset($page['layout_boxes']) && is_array($page['layout_boxes'])) {
            $boxes = $page['layout_boxes'];
        }

        $layoutImageBbox = is_array($layout) ? $this->bbox($layout['image_bbox'] ?? null) : null;
        $pageBbox = $this->bbox($page['bbox'] ?? null);
        $regions = [];

        foreach ($boxes as $box) {
            if (!is_array($box) || ($box['label'] ?? '') !== 'Table') {
                continue;
            }

            $bbox = $this->bbox($box['bbox'] ?? null);
            if ($bbox === null) {
                continue;
            }

            $regions[] = [
                'bbox' => $bbox,
                'page_bbox' => $layoutImageBbox !== null && $pageBbox !== null
                    ? $this->layout->rescaleBbox($layoutImageBbox, $pageBbox, $bbox)
                    : $bbox,
            ];
        }

        return $regions;
    }

    /**
     * @param list<float> $bbox
     * @return array<string, mixed>
     */
    private function tableBlock(array $bbox, string $markdown, int $pnum, int $tableIndex): array
    {
        return [
            'bbox' => $bbox,
            'type' => 'Table',
            'block_type' => 'Table',
            'pnum' => $pnum,
            'lines' => [
                [
                    'bbox' => $bbox,
                    'spans' => [
                        [
                            'bbox' => $bbox,
                            'span_id' => $tableIndex . '_table',
                            'font' => 'Table',
                            'font_size' => 0,
                            'font_weight' => 0,
                            'block_type' => 'Table',
                            'text' => $markdown,
                        ],
                    ],
                ],
            ],
        ];
    }

    /**
     * @param array<string, mixed> $block
     */
    private function blockType(array $block): string
    {
        return (string) ($block['type'] ?? $block['block_type'] ?? '');
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
            if (!is_array($line)) {
                continue;
            }
            $lineBbox = $this->bbox($line['bbox'] ?? null);
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
        if (!is_array($value) || count($value) !== 4) {
            return null;
        }

        return array_map(static fn (float|int $coordinate): float => (float) $coordinate, array_values($value));
    }
}
