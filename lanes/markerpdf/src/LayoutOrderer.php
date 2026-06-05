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

        $assignedPages = 0;
        $assignmentSlots = min(count($pages), count($orderResults));
        for ($index = 0; $index < $assignmentSlots; $index++) {
            if (PdfPageArtifactSelector::isMissingPageArtifact($orderResults[$index])) {
                continue;
            }
            if (!is_array($orderResults[$index])) {
                throw new InvalidArgumentException('Supplied ordering predictions must be arrays.');
            }
            $pages[$index]['order'] = $orderResults[$index];
            $assignedPages++;
        }

        return [
            'pages' => $pages,
            'plan' => [
                'image_count' => PdfPageArtifactSelector::countPresentArtifacts($images),
                'page_count' => count($pages),
                'layout_bbox_counts' => $bboxCounts,
                'requested_bboxes' => $requestedBboxes,
                'order_result_count' => PdfPageArtifactSelector::countPresentArtifacts($orderResults),
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
                $blockBbox = $this->blockBboxForOrdering($page, $block);
                foreach ($orderBoxes as $orderBox) {
                    $position = (int) ($orderBox['position'] ?? 0);
                    $orderBbox = $this->rescaleOrderBbox($page, $this->bbox($orderBox));
                    $intersection = $this->intersectionPct($blockBbox, $orderBbox);

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
                array_push($newBlocks, ...$this->sortBlockGroupForPage($page, $blockGroup));
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
        return $this->sortBlockGroupByBbox($blocks, $tolerance, fn (array $block): array => $this->bbox($block));
    }

    /**
     * @param array<string, mixed> $page
     * @param list<array<string, mixed>> $blocks
     * @return list<array<string, mixed>>
     */
    private function sortBlockGroupForPage(array $page, array $blocks, float $tolerance = 1.25): array
    {
        return $this->sortBlockGroupByBbox(
            $blocks,
            $tolerance,
            fn (array $block): array => $this->blockBboxForOrdering($page, $block)
        );
    }

    /**
     * @param list<array<string, mixed>> $blocks
     * @param callable(array<string, mixed>): list<float> $bboxForBlock
     * @return list<array<string, mixed>>
     */
    private function sortBlockGroupByBbox(array $blocks, float $tolerance, callable $bboxForBlock): array
    {
        $groups = [];
        foreach ($blocks as $index => $block) {
            $bbox = $bboxForBlock($block);
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
        $imageBbox = is_array($order) ? $this->bboxValue($order['image_bbox'] ?? null) : null;
        $pageBbox = $this->bboxValue($page['bbox'] ?? null);

        if ($imageBbox !== null && $pageBbox !== null) {
            $rotation = $this->normalizedRotation((int) round((float) ($page['rotation'] ?? 0)));
            if ($this->shouldRotateUnrotatedOrderImage($imageBbox, $pageBbox, $rotation)) {
                $bbox = $this->rotateUnrotatedImageBbox($bbox, $imageBbox, $rotation);
                $imageBbox = [
                    0.0,
                    0.0,
                    $this->rectHeight($imageBbox),
                    $this->rectWidth($imageBbox),
                ];
            }

            return $this->rescaleBbox($imageBbox, $pageBbox, $bbox);
        }

        return $bbox;
    }

    /**
     * Upstream pdftext normalizes 90/270-degree pages by swapping the page bbox
     * axes before layout ordering. Some native preview/order fixtures still
     * carry pre-rotation image dimensions, so rotate those model boxes into the
     * same page-local coordinates before maximum-overlap matching.
     *
     * @param list<float> $imageBbox
     * @param list<float> $pageBbox
     */
    private function shouldRotateUnrotatedOrderImage(array $imageBbox, array $pageBbox, int $rotation): bool
    {
        if (!in_array($rotation, [90, 270], true)) {
            return false;
        }

        $imageWidth = $this->rectWidth($imageBbox);
        $imageHeight = $this->rectHeight($imageBbox);
        $pageWidth = $this->rectWidth($pageBbox);
        $pageHeight = $this->rectHeight($pageBbox);
        if ($imageWidth <= 0.0 || $imageHeight <= 0.0 || $pageWidth <= 0.0 || $pageHeight <= 0.0) {
            return false;
        }

        $imageRatio = $imageWidth / $imageHeight;
        $pageRatio = $pageWidth / $pageHeight;
        $unrotatedPageRatio = $pageHeight / $pageWidth;

        return abs($imageRatio - $unrotatedPageRatio) + 0.000001 < abs($imageRatio - $pageRatio);
    }

    /**
     * @param list<float> $bbox
     * @param list<float> $imageBbox
     * @return list<float>
     */
    private function rotateUnrotatedImageBbox(array $bbox, array $imageBbox, int $rotation): array
    {
        $width = $this->rectWidth($imageBbox);
        $height = $this->rectHeight($imageBbox);
        $x1 = $bbox[0] - $imageBbox[0];
        $y1 = $bbox[1] - $imageBbox[1];
        $x2 = $bbox[2] - $imageBbox[0];
        $y2 = $bbox[3] - $imageBbox[1];

        return $this->normalizeRect(match ($rotation) {
            90 => [$height - $y2, $x1, $height - $y1, $x2],
            270 => [$y1, $width - $x2, $y2, $width - $x1],
            default => [$x1, $y1, $x2, $y2],
        });
    }

    /**
     * @param list<float> $bbox
     */
    private function rectWidth(array $bbox): float
    {
        return max(0.0, $bbox[2] - $bbox[0]);
    }

    /**
     * @param list<float> $bbox
     */
    private function rectHeight(array $bbox): float
    {
        return max(0.0, $bbox[3] - $bbox[1]);
    }

    /**
     * @param list<float> $bbox
     * @return list<float>
     */
    private function normalizeRect(array $bbox): array
    {
        return [
            min($bbox[0], $bbox[2]),
            min($bbox[1], $bbox[3]),
            max($bbox[0], $bbox[2]),
            max($bbox[1], $bbox[3]),
        ];
    }

    private function normalizedRotation(int $rotation): int
    {
        $rotation %= 360;
        if ($rotation < 0) {
            $rotation += 360;
        }

        return in_array($rotation, [0, 90, 180, 270], true) ? $rotation : 0;
    }

    /**
     * @param array<string, mixed> $block
     * @return list<float>
     */
    private function bbox(array $block): array
    {
        if (isset($block['bbox']) && is_array($block['bbox']) && count($block['bbox']) === 4) {
            return $this->normalizeRect(array_map(static fn (float|int $value): float => (float) $value, array_values($block['bbox'])));
        }

        $lineBoxes = [];
        foreach (($block['lines'] ?? []) as $line) {
            if (is_array($line) && isset($line['bbox']) && is_array($line['bbox']) && count($line['bbox']) === 4) {
                $lineBoxes[] = $this->normalizeRect(array_map(static fn (float|int $value): float => (float) $value, array_values($line['bbox'])));
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
     * @param array<string, mixed> $page
     * @param array<string, mixed> $block
     * @return list<float>
     */
    private function blockBboxForOrdering(array $page, array $block): array
    {
        $bbox = $this->bbox($block);
        if (!$this->usesPdfPageUserSpaceBlockBbox($page, $block)) {
            return $bbox;
        }

        $pageBbox = $this->bboxValue($page['pdf_page_bbox'] ?? $page['page_bbox'] ?? null);
        if ($pageBbox === null) {
            return $bbox;
        }

        $rotation = $this->normalizedRotation((int) round((float) ($block['page_rotation'] ?? $page['rotation'] ?? 0)));
        $userUnit = $this->positiveNumber($block['page_user_unit'] ?? $page['page_user_unit'] ?? $page['user_unit'] ?? null) ?? 1.0;

        return $this->pageRectToPdftextDisplayRect($bbox, $pageBbox, $rotation, $userUnit);
    }

    /**
     * @param array<string, mixed> $page
     * @param array<string, mixed> $block
     */
    private function usesPdfPageUserSpaceBlockBbox(array $page, array $block): bool
    {
        $coordinateSpace = $block['bbox_coordinate_space']
            ?? $block['block_bbox_coordinate_space']
            ?? $page['block_bbox_coordinate_space']
            ?? null;

        return in_array($coordinateSpace, ['pdf_page_user_space', 'pdf_page_space', 'page_user_space'], true);
    }

    /**
     * @param list<float> $rect
     * @param list<float> $pageBbox
     * @return list<float>
     */
    private function pageRectToPdftextDisplayRect(array $rect, array $pageBbox, int $rotation, float $userUnit): array
    {
        $pageBox = $this->normalizeRect($pageBbox);
        $sourceRect = $this->normalizeRect($rect);
        $width = $pageBox[2] - $pageBox[0];
        $height = $pageBox[3] - $pageBox[1];

        if ($width == 0.0 || $height == 0.0) {
            return $sourceRect;
        }

        $x1 = $sourceRect[0] - $pageBox[0];
        $y1 = $sourceRect[1] - $pageBox[1];
        $x2 = $sourceRect[2] - $pageBox[0];
        $y2 = $sourceRect[3] - $pageBox[1];

        $mapped = $this->normalizeRect(match ($this->normalizedRotation($rotation)) {
            90 => [$y1, $x1, $y2, $x2],
            180 => [$width - $x2, $y1, $width - $x1, $y2],
            270 => [$height - $y2, $width - $x2, $height - $y1, $width - $x1],
            default => [$x1, $height - $y2, $x2, $height - $y1],
        });

        if (abs($userUnit - 1.0) <= 0.000001) {
            return $mapped;
        }

        return [
            $mapped[0] * $userUnit,
            $mapped[1] * $userUnit,
            $mapped[2] * $userUnit,
            $mapped[3] * $userUnit,
        ];
    }

    private function positiveNumber(mixed $value): ?float
    {
        if (!is_int($value) && !is_float($value)) {
            return null;
        }

        $number = (float) $value;
        return $number > 0.0 ? $number : null;
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
