<?php

declare(strict_types=1);

namespace PortLibs\MarkerPDF;

use InvalidArgumentException;

final class TableFormatter
{
    private const DEFAULT_INTERSECTION_THRESHOLD = 0.7;
    private const DEFAULT_TABLE_DPI = 192.0;

    private LayoutOrderer $layout;
    private PdfImageRenderer $renderer;

    public function __construct(?LayoutOrderer $layout = null, ?PdfImageRenderer $renderer = null)
    {
        $this->layout = $layout ?? new LayoutOrderer();
        $this->renderer = $renderer ?? new PdfImageRenderer();
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
     * Native boundary for marker.tables.table::get_table_boxes.
     *
     * Upstream returns cropped PIL images; this PHP slice returns deterministic
     * crop plans keyed the same way so later table recognition can be supplied
     * without shelling out to Python, pypdfium, Surya, or tabled.
     *
     * @param list<array<string, mixed>> $pages
     * @param list<mixed> $suppliedTextLines One text-line prediction payload per non-OCR table page, in upstream selected-page order.
     * @param array<int, array{width?: int|float, height?: int|float}|list<int|float>> $renderedImageSizes Optional high-res image sizes by page index.
     * @param array<int, bool> $forcedOcrPageIndexes Page indexes whose table text-line payloads should be treated like upstream OCRed pages.
     * @return array{
     *     table_images: list<array<string, mixed>>,
     *     table_bboxes: list<list<float>>,
     *     table_counts: list<int>,
     *     text_lines: list<mixed>,
     *     image_sizes: list<array{width: int, height: int}>,
     *     page_image_sizes: list<array{width: int, height: int}|null>,
     *     pnums: list<int>,
     *     doc_indexes: list<int>,
     *     table_page_indexes: list<int>
     * }
     */
    public function getTableBoxes(
        array $pages,
        array $suppliedTextLines = [],
        array $renderedImageSizes = [],
        float $tableDpi = self::DEFAULT_TABLE_DPI,
        array $forcedOcrPageIndexes = []
    ): array {
        $tableImages = [];
        $tableBboxes = [];
        $tableCounts = [];
        $pageImageSizes = [];
        $pnums = [];

        foreach ($pages as $pageIndex => $page) {
            if (!is_array($page)) {
                throw new InvalidArgumentException('Page entries must be arrays.');
            }

            $pnum = (int) ($page['pnum'] ?? $pageIndex);
            $pnums[] = $pnum;
            $pageBbox = $this->bbox($page['bbox'] ?? null) ?? [0.0, 0.0, 0.0, 0.0];
            $imageSize = $this->resolveRenderedImageSize($pageBbox, $renderedImageSizes[$pageIndex] ?? null, $tableDpi);
            $layoutImageBbox = $this->layoutImageBbox($page) ?? $pageBbox;
            $tableBoxes = $this->tableBboxes($page);

            if ($tableBoxes === []) {
                $tableCounts[] = 0;
                $pageImageSizes[] = null;
                continue;
            }

            $tableCounts[] = count($tableBoxes);
            $pageImageSizes[] = $imageSize;
            $renderedImageBbox = [0.0, 0.0, (float) $imageSize['width'], (float) $imageSize['height']];

            foreach ($tableBoxes as $tableIndex => $tableBox) {
                $highresBbox = $this->layout->rescaleBbox($layoutImageBbox, $renderedImageBbox, $tableBox);
                $tableBboxes[] = $highresBbox;
                $tableImages[] = [
                    'kind' => 'crop-plan',
                    'page_index' => $pageIndex,
                    'pnum' => $pnum,
                    'table_index' => $tableIndex,
                    'dpi' => $tableDpi,
                    'scale' => $tableDpi / 72.0,
                    'source_bbox' => $tableBox,
                    'highres_bbox' => $highresBbox,
                    'image_size' => $imageSize,
                    'crop_width' => max(0.0, $highresBbox[2] - $highresBbox[0]),
                    'crop_height' => max(0.0, $highresBbox[3] - $highresBbox[1]),
                ];
            }
        }

        $docIndexes = [];
        $tablePageIndexes = [];
        foreach ($tableCounts as $pageIndex => $tableCount) {
            if ($tableCount > 0) {
                $docIndexes[] = $pnums[$pageIndex];
                $tablePageIndexes[] = $pageIndex;
            }
        }

        $textLines = [];
        $outImageSizes = [];
        $textLineIndex = 0;
        foreach ($tableCounts as $pageIndex => $tableCount) {
            if ($tableCount === 0) {
                continue;
            }

            $page = $pages[$pageIndex];
            $pageOcred = (isset($page['ocr_method']) && $page['ocr_method'] !== null)
                || (($forcedOcrPageIndexes[$pageIndex] ?? false) === true);
            if ($pageOcred) {
                for ($i = 0; $i < $tableCount; $i++) {
                    $textLines[] = null;
                }
            } else {
                if (!array_key_exists($textLineIndex, $suppliedTextLines)) {
                    throw new InvalidArgumentException('Missing supplied text-line prediction for table page index ' . $pageIndex . '.');
                }

                for ($i = 0; $i < $tableCount; $i++) {
                    $textLines[] = $suppliedTextLines[$textLineIndex];
                }
                $textLineIndex++;
            }

            $imageSize = $pageImageSizes[$pageIndex];
            if ($imageSize === null) {
                throw new InvalidArgumentException('Internal table image size mismatch for page index ' . $pageIndex . '.');
            }
            for ($i = 0; $i < $tableCount; $i++) {
                $outImageSizes[] = $imageSize;
            }
        }

        if (count($tableImages) !== count($tableBboxes) || count($tableImages) !== count($textLines) || count($tableImages) !== count($outImageSizes)) {
            throw new InvalidArgumentException('Internal table box planning counts do not match upstream shape.');
        }
        if (array_sum($tableCounts) !== count($tableImages)) {
            throw new InvalidArgumentException('Internal table count total does not match planned crop count.');
        }

        return [
            'table_images' => $tableImages,
            'table_bboxes' => $tableBboxes,
            'table_counts' => $tableCounts,
            'text_lines' => $textLines,
            'image_sizes' => $outImageSizes,
            'page_image_sizes' => $pageImageSizes,
            'pnums' => $pnums,
            'doc_indexes' => $docIndexes,
            'table_page_indexes' => $tablePageIndexes,
        ];
    }

    /**
     * Native boundary for the post-recognition half of marker.tables.table::format_tables.
     *
     * @param list<array<string, mixed>> $pages
     * @param list<string> $markdownTables Markdown strings in upstream recognized-table order.
     * @return array{pages: list<array<string, mixed>>, table_count: int, inserted_tables: int, table_context_reviews: list<array<string, mixed>>}
     */
    public function formatTables(
        array $pages,
        array $markdownTables,
        float $intersectionThreshold = self::DEFAULT_INTERSECTION_THRESHOLD
    ): array {
        $processedTables = 0;
        $insertedTables = 0;
        $tableContextReviews = [];

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
            $matchedBlockIndexes = [];
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
                        $matchedBlockIndexes[$tableIndex][] = $blockIndex;
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
                    $tableContextReviews[] = $this->tableContextReview(
                        $page,
                        $blocks,
                        $region,
                        $pageIndex,
                        $tableIndex,
                        $processedTables,
                        false,
                        $matchedBlockIndexes[$tableIndex] ?? [],
                        null
                    );
                    $processedTables++;
                    continue;
                }

                if (!array_key_exists($processedTables, $markdownTables)) {
                    throw new InvalidArgumentException('Missing Markdown table for recognized table index ' . $processedTables . '.');
                }

                $tableBlock = $this->tableBlock($region['bbox'], (string) $markdownTables[$processedTables], (int) ($page['pnum'] ?? 0), $tableIndex);
                $insertPoint = min((int) $insertPoints[$tableIndex], count($newPageBlocks));
                array_splice($newPageBlocks, $insertPoint, 0, [$tableBlock]);
                $tableContextReviews[] = $this->tableContextReview(
                    $page,
                    $blocks,
                    $region,
                    $pageIndex,
                    $tableIndex,
                    $processedTables,
                    true,
                    $matchedBlockIndexes[$tableIndex] ?? [],
                    $insertPoint
                );
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
            'table_context_reviews' => $tableContextReviews,
        ];
    }

    /**
     * Review-only WordPress context around upstream table replacement.
     *
     * markerPDF replaces only intersecting `Table` blocks. Section headers and
     * captions stay as neighboring blocks, while tabled's Markdown/HTML
     * formatters later drop covered rowspan/colspan grid occupancy. This
     * metadata lets import code bind those surviving neighbors back to the
     * native span-grid table review without changing Marker Markdown output.
     *
     * @param array<string, mixed> $page
     * @param list<array<string, mixed>> $blocks
     * @param array{bbox: list<float>, page_bbox: list<float>} $region
     * @param list<int> $matchedTableBlockIndexes
     * @return array<string, mixed>
     */
    private function tableContextReview(
        array $page,
        array $blocks,
        array $region,
        int $pageIndex,
        int $pageTableIndex,
        int $recognizedTableIndex,
        bool $inserted,
        array $matchedTableBlockIndexes,
        ?int $insertPoint
    ): array {
        $review = [
            'table_index' => $recognizedTableIndex,
            'page_index' => $pageIndex,
            'page_number' => (int) ($page['pnum'] ?? $pageIndex),
            'page_table_index' => $pageTableIndex,
            'inserted' => $inserted,
            'insert_point' => $insertPoint,
            'table_bbox' => $region['bbox'],
            'table_page_bbox' => $region['page_bbox'],
            'matched_table_block_indexes' => array_values(array_map('intval', $matchedTableBlockIndexes)),
            'review_target' => 'table_span_grid',
        ];

        $section = $this->nearestSectionBlock($blocks, $matchedTableBlockIndexes, $insertPoint);
        if ($section !== null) {
            $review['section'] = $section;
        }

        $caption = $this->nearestCaptionBlock($blocks, $region['page_bbox'], $matchedTableBlockIndexes, $insertPoint);
        if ($caption !== null) {
            $review['caption'] = $caption;
        }

        $review['has_section'] = isset($review['section']);
        $review['has_caption'] = isset($review['caption']);

        return $review;
    }

    /**
     * @param list<array<string, mixed>> $blocks
     * @param list<int> $matchedTableBlockIndexes
     * @return array<string, mixed>|null
     */
    private function nearestSectionBlock(array $blocks, array $matchedTableBlockIndexes, ?int $insertPoint): ?array
    {
        $anchorIndex = $matchedTableBlockIndexes === []
            ? ($insertPoint ?? count($blocks))
            : min($matchedTableBlockIndexes);

        for ($blockIndex = $anchorIndex - 1; $blockIndex >= 0; $blockIndex--) {
            if (!isset($blocks[$blockIndex])) {
                continue;
            }
            $type = $this->blockType($blocks[$blockIndex]);
            if ($type !== 'Section-header' && $type !== 'Title') {
                continue;
            }

            return $this->contextBlockSummary($blocks[$blockIndex], $blockIndex, 'section');
        }

        return null;
    }

    /**
     * @param list<array<string, mixed>> $blocks
     * @param list<float> $tablePageBbox
     * @param list<int> $matchedTableBlockIndexes
     * @return array<string, mixed>|null
     */
    private function nearestCaptionBlock(array $blocks, array $tablePageBbox, array $matchedTableBlockIndexes, ?int $insertPoint): ?array
    {
        $anchorStart = $matchedTableBlockIndexes === [] ? ($insertPoint ?? 0) : min($matchedTableBlockIndexes);
        $anchorEnd = $matchedTableBlockIndexes === [] ? ($insertPoint ?? 0) : max($matchedTableBlockIndexes);
        $best = null;
        $bestScore = null;

        foreach ($blocks as $blockIndex => $block) {
            if ($this->blockType($block) !== 'Caption') {
                continue;
            }

            $bbox = $this->blockBbox($block);
            if ($bbox === null) {
                continue;
            }

            $xOverlap = $this->xOverlapPct($bbox, $tablePageBbox);
            $verticalGap = $this->verticalGap($bbox, $tablePageBbox);
            $adjacent = abs($blockIndex - $anchorEnd) <= 2 || abs($blockIndex - $anchorStart) <= 2;
            if ($xOverlap < 0.2 && !$adjacent) {
                continue;
            }
            if ($verticalGap > 120.0 && !$adjacent) {
                continue;
            }

            $position = $this->relativeCaptionPosition($bbox, $tablePageBbox, $blockIndex, $anchorStart, $anchorEnd);
            $score = $verticalGap
                + ($position === 'after' ? 0.0 : 10.0)
                + (abs($blockIndex - $anchorEnd) * 0.01)
                + ((1.0 - min(1.0, $xOverlap)) * 5.0);
            if ($bestScore === null || $score < $bestScore) {
                $bestScore = $score;
                $best = $this->contextBlockSummary($block, $blockIndex, 'caption');
                $best['position'] = $position;
                $best['vertical_gap'] = $verticalGap;
                $best['x_overlap_pct'] = round($xOverlap, 4);
            }
        }

        return $best;
    }

    /**
     * @param array<string, mixed> $block
     * @return array<string, mixed>
     */
    private function contextBlockSummary(array $block, int $blockIndex, string $role): array
    {
        $summary = [
            'role' => $role,
            'block_index' => $blockIndex,
            'type' => $this->blockType($block),
            'text' => $this->blockText($block),
        ];

        $bbox = $this->blockBbox($block);
        if ($bbox !== null) {
            $summary['bbox'] = $bbox;
        }
        if (isset($block['heading_level']) && (is_int($block['heading_level']) || is_float($block['heading_level']))) {
            $summary['heading_level'] = (int) $block['heading_level'];
        }

        return $summary;
    }

    /**
     * @param array<string, mixed> $block
     */
    private function blockText(array $block): string
    {
        $parts = [];
        foreach (($block['lines'] ?? []) as $line) {
            if (!is_array($line)) {
                continue;
            }
            if (isset($line['text']) && is_string($line['text'])) {
                $parts[] = $line['text'];
                continue;
            }

            $lineText = '';
            foreach (($line['spans'] ?? []) as $span) {
                if (is_array($span)) {
                    $lineText .= (string) ($span['text'] ?? '');
                }
            }
            if ($lineText !== '') {
                $parts[] = $lineText;
            }
        }

        return trim(implode(' ', $parts));
    }

    /**
     * @param list<float> $bbox
     * @param list<float> $tablePageBbox
     */
    private function relativeCaptionPosition(array $bbox, array $tablePageBbox, int $blockIndex, int $anchorStart, int $anchorEnd): string
    {
        if ($bbox[1] >= $tablePageBbox[3] || $blockIndex > $anchorEnd) {
            return 'after';
        }
        if ($bbox[3] <= $tablePageBbox[1] || $blockIndex < $anchorStart) {
            return 'before';
        }

        return 'overlap';
    }

    /**
     * @param list<float> $bbox
     * @param list<float> $other
     */
    private function verticalGap(array $bbox, array $other): float
    {
        if ($bbox[1] >= $other[3]) {
            return $bbox[1] - $other[3];
        }
        if ($other[1] >= $bbox[3]) {
            return $other[1] - $bbox[3];
        }

        return 0.0;
    }

    /**
     * @param list<float> $bbox
     * @param list<float> $other
     */
    private function xOverlapPct(array $bbox, array $other): float
    {
        $width = max(0.0, $bbox[2] - $bbox[0]);
        if ($width === 0.0) {
            return 0.0;
        }

        $overlap = max(0.0, min($bbox[2], $other[2]) - max($bbox[0], $other[0]));

        return $overlap / $width;
    }

    /**
     * @param array<string, mixed> $page
     * @return list<list<float>>
     */
    private function rawTableBoxes(array $page): array
    {
        $boxes = [];
        $layout = $page['layout'] ?? [];
        if (is_array($layout) && isset($layout['bboxes']) && is_array($layout['bboxes'])) {
            $boxes = $layout['bboxes'];
        } elseif (isset($page['layout_boxes']) && is_array($page['layout_boxes'])) {
            $boxes = $page['layout_boxes'];
        }

        $tableBoxes = [];
        foreach ($boxes as $box) {
            if (!is_array($box) || ($box['label'] ?? '') !== 'Table') {
                continue;
            }

            $bbox = $this->bbox($box['bbox'] ?? null);
            if ($bbox !== null) {
                $tableBoxes[] = $bbox;
            }
        }

        return $tableBoxes;
    }

    /**
     * @param array<string, mixed> $page
     * @return list<list<float>>
     */
    private function tableBboxes(array $page): array
    {
        return array_values(array_filter(
            $this->mergeTables($this->rawTableBoxes($page)),
            static fn (array $bbox): bool => ($bbox[3] - $bbox[1]) > 10.0 && ($bbox[2] - $bbox[0]) > 10.0
        ));
    }

    /**
     * Mirrors tabled.inference.detection::merge_tables for Marker's table boundary.
     *
     * @param list<list<float>> $pageTableBoxes
     * @return list<list<float>>
     */
    private function mergeTables(array $pageTableBoxes): array
    {
        $expansionFactor = 1.02;
        $shrinkFactor = 0.98;
        $ignoreBoxes = [];

        for ($i = 0; $i < count($pageTableBoxes); $i++) {
            if (isset($ignoreBoxes[$i])) {
                continue;
            }

            for ($j = $i + 1; $j < count($pageTableBoxes); $j++) {
                if (isset($ignoreBoxes[$j])) {
                    continue;
                }

                $expandedBox1 = [
                    $pageTableBoxes[$i][0] * $shrinkFactor,
                    $pageTableBoxes[$i][1],
                    $pageTableBoxes[$i][2] * $expansionFactor,
                    $pageTableBoxes[$i][3],
                ];
                $expandedBox2 = [
                    $pageTableBoxes[$j][0] * $shrinkFactor,
                    $pageTableBoxes[$j][1],
                    $pageTableBoxes[$j][2] * $expansionFactor,
                    $pageTableBoxes[$j][3],
                ];

                if ($this->layout->intersectionPct($expandedBox1, $expandedBox2) > 0.0) {
                    $pageTableBoxes[$i] = $this->mergeBoxes($pageTableBoxes[$i], $pageTableBoxes[$j]);
                    $ignoreBoxes[$j] = true;
                }
            }
        }

        $merged = [];
        foreach ($pageTableBoxes as $index => $bbox) {
            if (!isset($ignoreBoxes[$index])) {
                $merged[] = $bbox;
            }
        }

        return $merged;
    }

    /**
     * @param list<float> $box1
     * @param list<float> $box2
     * @return list<float>
     */
    private function mergeBoxes(array $box1, array $box2): array
    {
        return [
            min($box1[0], $box2[0]),
            min($box1[1], $box2[1]),
            max($box1[2], $box2[2]),
            max($box1[3], $box2[3]),
        ];
    }

    /**
     * @param array<string, mixed> $page
     * @return list<float>|null
     */
    private function layoutImageBbox(array $page): ?array
    {
        $layout = $page['layout'] ?? [];
        if (is_array($layout)) {
            $bbox = $this->bbox($layout['image_bbox'] ?? null);
            if ($bbox !== null) {
                return $bbox;
            }
        }

        return $this->bbox($page['layout_image_bbox'] ?? null);
    }

    /**
     * @param list<float> $pageBbox
     * @param mixed $override
     * @return array{width: int, height: int}
     */
    private function resolveRenderedImageSize(array $pageBbox, mixed $override, float $tableDpi): array
    {
        if ($override === null) {
            return $this->renderer->renderedImageSize($pageBbox, $tableDpi);
        }

        if (!is_array($override)) {
            throw new InvalidArgumentException('Rendered image size override must be an array.');
        }

        $width = $override['width'] ?? $override[0] ?? null;
        $height = $override['height'] ?? $override[1] ?? null;
        if ((!is_int($width) && !is_float($width)) || (!is_int($height) && !is_float($height))) {
            throw new InvalidArgumentException('Rendered image size override must include numeric width and height.');
        }
        if ($width <= 0 || $height <= 0) {
            throw new InvalidArgumentException('Rendered image size override must be greater than zero.');
        }

        return [
            'width' => (int) round($width),
            'height' => (int) round($height),
        ];
    }

    /**
     * @param array<string, mixed> $page
     * @return list<array{bbox: list<float>, page_bbox: list<float>}>
     */
    private function tableRegions(array $page): array
    {
        $layout = $page['layout'] ?? [];
        $layoutImageBbox = is_array($layout) ? $this->bbox($layout['image_bbox'] ?? null) : null;
        $pageBbox = $this->bbox($page['bbox'] ?? null);
        $regions = [];

        foreach ($this->tableBboxes($page) as $bbox) {
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
