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

        $pageReview = $this->tablePageReviewContext($page, $region['page_bbox']);
        if ($pageReview !== []) {
            $review['page_review'] = $pageReview;
        }

        $sectionOrder = $this->tableSectionOrderReview(
            $page,
            $blocks,
            $region,
            $pageIndex,
            $pageTableIndex,
            $recognizedTableIndex,
            $inserted,
            $matchedTableBlockIndexes,
            $insertPoint,
            $section,
            $caption,
            $pageReview !== []
        );
        if ($sectionOrder !== []) {
            $review['section_order'] = $sectionOrder;
        }

        $review['has_section'] = isset($review['section']);
        $review['has_caption'] = isset($review['caption']);

        return $review;
    }

    /**
     * The upstream table formatter runs after layout reading-order sorting, then
     * removes only matched Table blocks. Preserve that sorted section/table/
     * caption relationship for WordPress review UIs before Markdown loses it.
     *
     * @param list<array<string, mixed>> $blocks
     * @param array{bbox: list<float>, page_bbox: list<float>} $region
     * @param list<int> $matchedTableBlockIndexes
     * @param array<string, mixed>|null $section
     * @param array<string, mixed>|null $caption
     * @return array<string, mixed>
     */
    private function tableSectionOrderReview(
        array $page,
        array $blocks,
        array $region,
        int $pageIndex,
        int $pageTableIndex,
        int $recognizedTableIndex,
        bool $inserted,
        array $matchedTableBlockIndexes,
        ?int $insertPoint,
        ?array $section,
        ?array $caption,
        bool $pageReviewAttached
    ): array {
        if (!$inserted || $insertPoint === null) {
            return [];
        }

        $finalOrder = $this->finalBlockIndexesAfterTableReplacement($blocks, $matchedTableBlockIndexes, $insertPoint);
        $tableFinalIndex = $finalOrder['table_final_index'];
        $sectionOriginalIndex = $this->contextOriginalBlockIndex($section);
        $captionOriginalIndex = $this->contextOriginalBlockIndex($caption);
        $sectionFinalIndex = $sectionOriginalIndex === null ? null : ($finalOrder['original_to_final'][$sectionOriginalIndex] ?? null);
        $captionFinalIndex = $captionOriginalIndex === null ? null : ($finalOrder['original_to_final'][$captionOriginalIndex] ?? null);

        $blockOrder = [];
        if ($section !== null) {
            $blockOrder[] = $this->sectionOrderBlockEntry('section', $section, $sectionFinalIndex, $page);
        }
        $blockOrder[] = $this->sectionOrderTableEntry($region, $tableFinalIndex, $page, $recognizedTableIndex);
        if ($caption !== null) {
            $blockOrder[] = $this->sectionOrderBlockEntry('caption', $caption, $captionFinalIndex, $page);
        }

        usort(
            $blockOrder,
            static fn (array $left, array $right): int => ((int) ($left['final_index'] ?? PHP_INT_MAX) <=> (int) ($right['final_index'] ?? PHP_INT_MAX))
                ?: ((string) ($left['role'] ?? '') <=> (string) ($right['role'] ?? ''))
        );

        $finalRoles = [];
        foreach ($blockOrder as $entry) {
            $role = (string) ($entry['role'] ?? '');
            if ($role !== '') {
                $finalRoles[] = $role;
            }
        }

        $review = [
            'review_target' => 'layout_table_ocr_page_review_section_order',
            'source' => 'layout_ordered_blocks_before_table_replacement',
            'upstream_stage' => 'sort_blocks_in_reading_order_then_format_tables',
            'page_index' => $pageIndex,
            'page_number' => (int) ($page['pnum'] ?? $pageIndex),
            'page_table_index' => $pageTableIndex,
            'table_index' => $recognizedTableIndex,
            'insert_point' => $insertPoint,
            'table_final_index' => $tableFinalIndex,
            'matched_table_block_indexes' => array_values(array_map('intval', $matchedTableBlockIndexes)),
            'removed_table_block_count' => count($matchedTableBlockIndexes),
            'block_order' => $blockOrder,
            'final_role_order' => $finalRoles,
            'section_before_table' => $sectionFinalIndex !== null && $sectionFinalIndex < $tableFinalIndex,
            'caption_after_table' => $captionFinalIndex !== null && $captionFinalIndex > $tableFinalIndex,
            'page_review_attached' => $pageReviewAttached,
            'visible_text_source' => false,
        ];

        if ($sectionFinalIndex !== null) {
            $review['section_final_index'] = $sectionFinalIndex;
        }
        if ($captionFinalIndex !== null) {
            $review['caption_final_index'] = $captionFinalIndex;
        }

        return $this->compactReviewRow($review);
    }

    /**
     * @param list<array<string, mixed>> $blocks
     * @param list<int> $matchedTableBlockIndexes
     * @return array{table_final_index: int, original_to_final: array<int, int>}
     */
    private function finalBlockIndexesAfterTableReplacement(array $blocks, array $matchedTableBlockIndexes, int $insertPoint): array
    {
        $removed = [];
        foreach ($matchedTableBlockIndexes as $blockIndex) {
            $removed[(int) $blockIndex] = true;
        }

        $originalToFinal = [];
        $finalIndex = 0;
        $tableFinalIndex = null;
        foreach ($blocks as $blockIndex => $_block) {
            if ($tableFinalIndex === null && $finalIndex === $insertPoint) {
                $tableFinalIndex = $finalIndex;
                $finalIndex++;
            }

            if (isset($removed[$blockIndex])) {
                continue;
            }

            $originalToFinal[$blockIndex] = $finalIndex;
            $finalIndex++;
        }

        if ($tableFinalIndex === null) {
            $tableFinalIndex = $finalIndex;
        }

        return [
            'table_final_index' => $tableFinalIndex,
            'original_to_final' => $originalToFinal,
        ];
    }

    /**
     * @param array<string, mixed>|null $context
     */
    private function contextOriginalBlockIndex(?array $context): ?int
    {
        if ($context === null) {
            return null;
        }

        $value = $context['block_index'] ?? null;
        if (is_int($value) || is_float($value) || (is_string($value) && preg_match('/^-?\d+$/', $value) === 1)) {
            return (int) $value;
        }

        return null;
    }

    /**
     * @param array<string, mixed> $context
     * @return array<string, mixed>
     */
    private function sectionOrderBlockEntry(string $role, array $context, ?int $finalIndex, array $page): array
    {
        $entry = [
            'role' => $role,
            'block_index' => (int) ($context['block_index'] ?? -1),
            'final_index' => $finalIndex,
            'type' => (string) ($context['type'] ?? ''),
            'text' => (string) ($context['text'] ?? ''),
        ];

        if (isset($context['bbox']) && is_array($context['bbox'])) {
            $entry['bbox'] = $context['bbox'];
            $entry += $this->layoutOrderMatchFields($page, $context['bbox']);
        }
        if (isset($context['position']) && is_scalar($context['position'])) {
            $entry['position'] = (string) $context['position'];
        }
        if (isset($context['heading_level']) && (is_int($context['heading_level']) || is_float($context['heading_level']))) {
            $entry['heading_level'] = (int) $context['heading_level'];
        }

        return $this->compactReviewRow($entry);
    }

    /**
     * @param array{bbox: list<float>, page_bbox: list<float>} $region
     * @return array<string, mixed>
     */
    private function sectionOrderTableEntry(array $region, int $finalIndex, array $page, int $recognizedTableIndex): array
    {
        $entry = [
            'role' => 'table',
            'table_index' => $recognizedTableIndex,
            'final_index' => $finalIndex,
            'type' => 'Table',
            'bbox' => $region['bbox'],
            'page_bbox' => $region['page_bbox'],
        ];

        $entry += $this->layoutOrderMatchFields($page, $region['page_bbox']);

        return $this->compactReviewRow($entry);
    }

    /**
     * @param list<float> $bbox
     * @return array<string, mixed>
     */
    private function layoutOrderMatchFields(array $page, array $bbox): array
    {
        $match = $this->layoutOrderMatch($page, $bbox);
        if ($match === null) {
            return [];
        }

        return [
            'order_position' => $match['position'],
            'order_intersection_pct' => $match['intersection_pct'],
            'order_bbox' => $match['bbox'],
        ];
    }

    /**
     * @param list<float> $bbox
     * @return array{position: int, intersection_pct: float, bbox: list<float>}|null
     */
    private function layoutOrderMatch(array $page, array $bbox): ?array
    {
        $orderBoxes = $this->orderBoxes($page);
        if ($orderBoxes === []) {
            return null;
        }

        $best = null;
        foreach ($orderBoxes as $orderBox) {
            $orderBbox = $this->rescaledOrderBboxForReview($page, $orderBox['bbox']);
            $intersection = round($this->layout->intersectionPct($bbox, $orderBbox), 4);
            if ($best === null || $intersection > $best['intersection_pct']) {
                $best = [
                    'position' => $orderBox['position'],
                    'intersection_pct' => $intersection,
                    'bbox' => $orderBbox,
                ];
            }
        }

        return $best;
    }

    /**
     * @return list<array{position: int, bbox: list<float>}>
     */
    private function orderBoxes(array $page): array
    {
        $order = $page['order'] ?? [];
        $boxes = is_array($order) && isset($order['bboxes']) && is_array($order['bboxes'])
            ? $order['bboxes']
            : ($page['order_bboxes'] ?? []);

        if (!is_array($boxes)) {
            return [];
        }

        $out = [];
        foreach ($boxes as $box) {
            if (!is_array($box)) {
                continue;
            }
            $bbox = $this->bbox($box['bbox'] ?? null);
            if ($bbox === null) {
                continue;
            }

            $position = $box['position'] ?? null;
            if (!is_int($position) && !is_float($position) && !(is_string($position) && preg_match('/^-?\d+$/', $position) === 1)) {
                continue;
            }

            $out[] = [
                'position' => (int) $position,
                'bbox' => $bbox,
            ];
        }

        return $out;
    }

    /**
     * @param list<float> $bbox
     * @return list<float>
     */
    private function rescaledOrderBboxForReview(array $page, array $bbox): array
    {
        $order = $page['order'] ?? [];
        $orderImageBbox = is_array($order) ? $this->bbox($order['image_bbox'] ?? null) : null;
        $pageBbox = $this->bbox($page['bbox'] ?? null);

        return $orderImageBbox !== null && $pageBbox !== null
            ? $this->layout->rescaleBbox($orderImageBbox, $pageBbox, $bbox)
            : $bbox;
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
     * @param list<float> $tablePageBbox
     * @return array<string, mixed>
     */
    private function tablePageReviewContext(array $page, array $tablePageBbox): array
    {
        $source = is_array($page['page_review_metadata'] ?? null) ? $page['page_review_metadata'] : $page;
        $context = [
            'source' => 'table_page_review_context',
            'review_only' => true,
            'visible_text_source' => false,
        ];

        foreach ([
            'pnum',
            'page',
            'page_number',
            'page_label',
            'page_object',
            'struct_parents',
            'parent_tree',
            'mark_info',
            'page_presentation',
        ] as $key) {
            if (array_key_exists($key, $source)) {
                $context[$key] = $source[$key];
            }
        }

        if (isset($source['piece_info']) && is_array($source['piece_info']) && $source['piece_info'] !== []) {
            $context['page_piece_info'] = $source['piece_info'];
            $context['page_piece_info_review_only'] = true;
        }

        if (isset($source['page_associated_files']) && is_array($source['page_associated_files']) && $source['page_associated_files'] !== []) {
            $context['page_associated_files'] = $this->compactReviewRows($source['page_associated_files']);
            $context['page_associated_file_count'] = count($context['page_associated_files']);
        }

        $structureRows = $this->compactReviewRows($source['structure_marked_content'] ?? []);
        if ($structureRows !== []) {
            $context['structure_marked_content'] = $structureRows;
            $context['structure_marked_content_count'] = count($structureRows);
            $roles = $this->stringValues(array_map(static fn (array $row): mixed => $row['role'] ?? null, $structureRows));
            if ($roles !== []) {
                $context['structure_roles'] = $roles;
            }
        }

        $annotationRows = $this->reviewRowsIntersectingTable(
            $source['annotation_structure_parent_rows'] ?? [],
            $tablePageBbox
        );
        if ($annotationRows !== []) {
            $context['annotation_structure_parent_rows'] = $annotationRows;
            $context['annotation_structure_parent_count'] = count($annotationRows);
            $context['annotation_struct_parents'] = $this->integerValues(
                array_map(static fn (array $row): mixed => $row['struct_parent'] ?? null, $annotationRows)
            );
        }

        $markupRows = $this->reviewRowsIntersectingTable(
            $source['text_markup_annotations'] ?? [],
            $tablePageBbox
        );
        if ($markupRows !== []) {
            $context['text_markup_annotations'] = $markupRows;
            $context['text_markup_annotation_count'] = count($markupRows);
        }

        $hasReviewRows = isset($context['page_piece_info'])
            || isset($context['page_associated_files'])
            || isset($context['structure_marked_content'])
            || isset($context['annotation_structure_parent_rows'])
            || isset($context['text_markup_annotations']);

        return $hasReviewRows ? $this->compactReviewRow($context) : [];
    }

    /**
     * @param mixed $rows
     * @return list<array<string, mixed>>
     */
    private function compactReviewRows(mixed $rows): array
    {
        if (!is_array($rows)) {
            return [];
        }

        $out = [];
        foreach ($rows as $row) {
            if (is_array($row)) {
                $out[] = $this->compactReviewRow($row);
            }
        }

        return $out;
    }

    /**
     * @param mixed $rows
     * @param list<float> $tablePageBbox
     * @return list<array<string, mixed>>
     */
    private function reviewRowsIntersectingTable(mixed $rows, array $tablePageBbox): array
    {
        if (!is_array($rows)) {
            return [];
        }

        $out = [];
        foreach ($rows as $row) {
            if (!is_array($row) || !$this->reviewRowIntersectsTable($row, $tablePageBbox)) {
                continue;
            }

            $out[] = $this->compactReviewRow($row);
        }

        return $out;
    }

    /**
     * @param array<string, mixed> $row
     * @param list<float> $tablePageBbox
     */
    private function reviewRowIntersectsTable(array $row, array $tablePageBbox): bool
    {
        $rect = $this->bbox($row['rect'] ?? null);
        if ($rect !== null) {
            return $this->layout->intersectionPct($rect, $tablePageBbox) > 0.0;
        }

        foreach (['quad_rects', 'pdftext_quad_rects'] as $key) {
            if (!isset($row[$key]) || !is_array($row[$key])) {
                continue;
            }

            foreach ($row[$key] as $quadRect) {
                $bbox = $this->bbox($quadRect);
                if ($bbox !== null && $this->layout->intersectionPct($bbox, $tablePageBbox) > 0.0) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function compactReviewRow(array $row): array
    {
        return array_filter($row, static fn (mixed $value): bool => $value !== null && $value !== []);
    }

    /**
     * @param list<mixed> $values
     * @return list<int>
     */
    private function integerValues(array $values): array
    {
        $out = [];
        foreach ($values as $value) {
            if (is_int($value) || is_float($value) || (is_string($value) && preg_match('/^-?\d+$/', $value) === 1)) {
                $out[] = (int) $value;
            }
        }

        return array_values(array_unique($out));
    }

    /**
     * @param list<mixed> $values
     * @return list<string>
     */
    private function stringValues(array $values): array
    {
        $out = [];
        foreach ($values as $value) {
            if (is_scalar($value) && (string) $value !== '') {
                $out[] = (string) $value;
            }
        }

        return array_values(array_unique($out));
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

            $bbox = $this->bbox($box);
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
