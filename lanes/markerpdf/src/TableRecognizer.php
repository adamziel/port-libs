<?php

declare(strict_types=1);

namespace PortLibs\MarkerPDF;

use InvalidArgumentException;

final class TableRecognizer
{
    /**
     * Native boundary for marker.tables.table::get_batch_size.
     */
    public function batchSize(?MarkerSettings $settings = null): int
    {
        $settings ??= new MarkerSettings();
        $override = $settings->get('TABLE_REC_BATCH_SIZE');
        if ($override !== null) {
            return (int) $override;
        }

        return 6;
    }

    /**
     * Native boundary for tabled.inference.recognition::get_cells.
     *
     * Upstream uses supplied pdf text lines when they produce table blocks, and
     * falls back to detector cells plus OCR when text lines are absent, forced,
     * or empty. This PHP slice keeps that routing deterministic by accepting the
     * detector cells as supplied inputs instead of loading Surya/tabled models.
     *
     * @param list<list<float|int>> $tableBboxes
     * @param list<array{width?: int|float, height?: int|float}|list<int|float>> $imageSizes
     * @param list<mixed> $textLines
     * @param array<int, list<array<string, mixed>>> $suppliedDetections
     * @return array{table_cells: list<list<array<string, mixed>>>, needs_ocr: list<bool>}
     */
    public function getCells(
        array $tableBboxes,
        array $imageSizes,
        array $textLines,
        array $suppliedDetections = [],
        bool $detectBoxes = false
    ): array {
        $count = count($tableBboxes);
        if (count($imageSizes) !== $count || count($textLines) !== $count) {
            throw new InvalidArgumentException('Table bboxes, image sizes, and text lines must have matching counts.');
        }

        $tableCells = [];
        $needsOcr = [];

        for ($idx = 0; $idx < $count; $idx++) {
            $this->bbox($tableBboxes[$idx]);
            $this->imageSize($imageSizes[$idx]);

            $textLine = $textLines[$idx];
            $textBlocks = $this->tableBlocksFromTextLine($textLine);

            if ($textLine === null || $detectBoxes || $textBlocks === []) {
                if (!array_key_exists($idx, $suppliedDetections)) {
                    throw new InvalidArgumentException('Missing supplied detector cells for table index ' . $idx . '.');
                }
                $tableCells[] = $this->normalizeCells($suppliedDetections[$idx]);
                $needsOcr[] = true;
                continue;
            }

            $tableCells[] = $this->normalizeCells($textBlocks);
            $needsOcr[] = false;
        }

        return [
            'table_cells' => $tableCells,
            'needs_ocr' => $needsOcr,
        ];
    }

    /**
     * Native boundary for tabled.inference.recognition::recognize_tables with supplied model output.
     *
     * @param list<list<array<string, mixed>>> $tableCells
     * @param list<bool> $needsOcr
     * @param list<array<string, mixed>> $suppliedTableResults
     * @param array<int, list<string|array{text?: string}>> $suppliedOcrTextLines
     * @return list<array{cells: list<array<string, mixed>>, rows: list<array<string, mixed>>, cols: list<array<string, mixed>>}>
     */
    public function recognizeTables(
        array $tableCells,
        array $needsOcr,
        array $suppliedTableResults,
        array $suppliedOcrTextLines = []
    ): array {
        $count = count($tableCells);
        if (count($needsOcr) !== $count || count($suppliedTableResults) !== $count) {
            throw new InvalidArgumentException('Table cells, needs_ocr flags, and supplied table results must have matching counts.');
        }

        $recognized = [];
        for ($idx = 0; $idx < $count; $idx++) {
            $cells = $this->normalizeCells($tableCells[$idx]);
            if ($needsOcr[$idx]) {
                if (!array_key_exists($idx, $suppliedOcrTextLines)) {
                    throw new InvalidArgumentException('Missing supplied OCR text lines for table index ' . $idx . '.');
                }
                $cells = $this->applyOcrText($cells, $suppliedOcrTextLines[$idx]);
            }

            $result = $suppliedTableResults[$idx];
            $resultCells = isset($result['cells']) && is_array($result['cells'])
                ? $this->normalizeCells(array_values($result['cells']))
                : $cells;
            foreach ($resultCells as $cellIndex => $cell) {
                if (($cell['text'] ?? null) === null && isset($cells[$cellIndex])) {
                    $resultCells[$cellIndex]['text'] = $cells[$cellIndex]['text'] ?? '';
                }
            }

            $recognized[] = [
                'cells' => $resultCells,
                'rows' => $this->normalizeRowsOrCols($result['rows'] ?? [], 'row_id'),
                'cols' => $this->normalizeRowsOrCols($result['cols'] ?? [], 'col_id'),
            ];
        }

        return $recognized;
    }

    /**
     * Native boundary for tabled.assignment::assign_rows_columns.
     *
     * @param array<string, mixed> $detectionResult
     * @param array{width?: int|float, height?: int|float}|list<int|float> $imageSize
     * @return list<array{bbox: list<float>, text: string, row_ids: list<int|null>, col_ids: list<int|null>, order?: int}>
     */
    public function assignRowsColumns(array $detectionResult, array $imageSize, float $heuristicThresh = 0.6): array
    {
        $rows = $this->normalizeRowsOrCols($detectionResult['rows'] ?? [], 'row_id');
        $cols = $this->normalizeRowsOrCols($detectionResult['cols'] ?? [], 'col_id');
        $cells = $this->normalizeCells($detectionResult['cells'] ?? []);

        if ($cells === []) {
            return [];
        }
        if ($rows === [] || $cols === []) {
            return $this->heuristicLayout($cells, $this->imageSize($imageSize));
        }

        $rows = $this->mergeMultilineRows($rows, $cells);
        $assigned = $this->initialAssignment(['cells' => $cells, 'rows' => $rows, 'cols' => $cols]);
        $this->assignOverlappers($assigned, $rows, $cols);

        $unassigned = 0;
        foreach ($assigned as $cell) {
            if ($cell['row_ids'][0] === null || $cell['col_ids'][0] === null) {
                $unassigned++;
            }
        }

        if (($unassigned / max(count($assigned), 1)) > $heuristicThresh) {
            return $this->heuristicLayout($cells, $this->imageSize($imageSize));
        }

        $this->assignUnassigned($assigned, $rows, $cols);
        $this->handleRowColSpans($assigned, $rows, $cols);

        return $assigned;
    }

    /**
     * Native boundary for tabled.formats.formatter("markdown", cells).
     *
     * @param list<array<string, mixed>> $cells
     */
    public function markdownFormat(array $cells): string
    {
        $cells = $this->sortCells($this->normalizeAssignedCells($cells));
        if ($cells === []) {
            return '';
        }

        $rows = $this->sortedUniqueIds(array_map(static fn (array $cell): int => (int) $cell['row_ids'][0], $cells));
        $cols = $this->sortedUniqueIds(array_map(static fn (array $cell): int => (int) $cell['col_ids'][0], $cells));
        $matrix = [];

        foreach ($rows as $row) {
            $outRow = [];
            foreach ($cols as $col) {
                $parts = [];
                foreach ($cells as $cell) {
                    if ((int) $cell['row_ids'][0] === $row && (int) $cell['col_ids'][0] === $col) {
                        $parts[] = (string) $cell['text'];
                    }
                }
                $outRow[] = $this->markdownReplaceAll(implode(' ', $parts));
            }
            $matrix[] = $outRow;
        }

        return $this->githubTable($matrix);
    }

    /**
     * @param list<array<string, mixed>> $recognizedTables
     * @param list<array{width?: int|float, height?: int|float}|list<int|float>> $imageSizes
     * @return array{assigned_cells: list<list<array<string, mixed>>>, markdown_tables: list<string>}
     */
    public function formatRecognizedTables(array $recognizedTables, array $imageSizes): array
    {
        if (count($recognizedTables) !== count($imageSizes)) {
            throw new InvalidArgumentException('Recognized table and image size counts must match.');
        }

        $assigned = [];
        $markdown = [];
        foreach ($recognizedTables as $idx => $table) {
            $tableCells = $this->assignRowsColumns($table, $imageSizes[$idx]);
            $assigned[] = $tableCells;
            $markdown[] = $this->markdownFormat($tableCells);
        }

        return [
            'assigned_cells' => $assigned,
            'markdown_tables' => $markdown,
        ];
    }

    /**
     * @param list<array<string, mixed>> $cells
     * @param list<string|array{text?: string}> $ocrTextLines
     * @return list<array<string, mixed>>
     */
    private function applyOcrText(array $cells, array $ocrTextLines): array
    {
        foreach ($ocrTextLines as $idx => $ocrLine) {
            if (!isset($cells[$idx])) {
                break;
            }
            $cells[$idx]['text'] = is_array($ocrLine) ? (string) ($ocrLine['text'] ?? '') : (string) $ocrLine;
        }

        return $cells;
    }

    /**
     * @param mixed $textLine
     * @return list<array<string, mixed>>
     */
    private function tableBlocksFromTextLine(mixed $textLine): array
    {
        if (!is_array($textLine)) {
            return [];
        }

        if (isset($textLine['table_blocks']) && is_array($textLine['table_blocks'])) {
            return array_values(array_filter($textLine['table_blocks'], static fn (mixed $block): bool => is_array($block)));
        }
        if (isset($textLine['blocks']) && is_array($textLine['blocks'])) {
            return array_values(array_filter($textLine['blocks'], static fn (mixed $block): bool => is_array($block)));
        }

        $isList = array_is_list($textLine);
        if ($isList) {
            return array_values(array_filter($textLine, static fn (mixed $block): bool => is_array($block)));
        }

        return [];
    }

    /**
     * @param list<array<string, mixed>> $cells
     * @return list<array<string, mixed>>
     */
    private function normalizeCells(array $cells): array
    {
        $normalized = [];
        foreach ($cells as $cell) {
            if (!is_array($cell)) {
                throw new InvalidArgumentException('Table cells must be arrays.');
            }
            $normalized[] = [
                'bbox' => $this->bbox($cell['bbox'] ?? null),
                'text' => array_key_exists('text', $cell) && $cell['text'] !== null ? (string) $cell['text'] : '',
            ];
        }

        return $normalized;
    }

    /**
     * @param list<array<string, mixed>> $cells
     * @return list<array{bbox: list<float>, text: string, row_ids: list<int|null>, col_ids: list<int|null>, order?: int}>
     */
    private function normalizeAssignedCells(array $cells): array
    {
        $normalized = [];
        foreach ($cells as $cell) {
            if (!is_array($cell)) {
                throw new InvalidArgumentException('Assigned table cells must be arrays.');
            }
            $rowIds = $cell['row_ids'] ?? null;
            $colIds = $cell['col_ids'] ?? null;
            if (!is_array($rowIds) || !is_array($colIds) || ($rowIds[0] ?? null) === null || ($colIds[0] ?? null) === null) {
                throw new InvalidArgumentException('Assigned table cells must include non-null first row_ids and col_ids.');
            }

            $entry = [
                'bbox' => $this->bbox($cell['bbox'] ?? null),
                'text' => (string) ($cell['text'] ?? ''),
                'row_ids' => array_map(static fn (mixed $id): ?int => $id === null ? null : (int) $id, array_values($rowIds)),
                'col_ids' => array_map(static fn (mixed $id): ?int => $id === null ? null : (int) $id, array_values($colIds)),
            ];
            if (isset($cell['order'])) {
                $entry['order'] = (int) $cell['order'];
            }
            $normalized[] = $entry;
        }

        return $normalized;
    }

    /**
     * @param mixed $items
     * @return list<array<string, mixed>>
     */
    private function normalizeRowsOrCols(mixed $items, string $idField): array
    {
        if (!is_array($items)) {
            throw new InvalidArgumentException('Table rows and columns must be arrays.');
        }

        $normalized = [];
        foreach (array_values($items) as $index => $item) {
            if (!is_array($item)) {
                throw new InvalidArgumentException('Table row/column entries must be arrays.');
            }

            $id = $item[$idField] ?? $item['id'] ?? $index;
            $bbox = $this->bbox($item['bbox'] ?? null);
            $normalized[] = [
                $idField => (int) $id,
                'bbox' => $bbox,
                'width' => $bbox[2] - $bbox[0],
                'height' => $bbox[3] - $bbox[1],
                'area' => $this->area($bbox),
            ];
        }

        return $normalized;
    }

    /**
     * @param array{cells: list<array<string, mixed>>, rows: list<array<string, mixed>>, cols: list<array<string, mixed>>} $detectionResult
     * @return list<array{bbox: list<float>, text: string, row_ids: list<int|null>, col_ids: list<int|null>}>
     */
    private function initialAssignment(array $detectionResult, float $thresh = 0.5): array
    {
        $overlapperRows = $this->overlapperIds($detectionResult['rows'], 'row_id');
        $overlapperCols = $this->overlapperIds($detectionResult['cols'], 'col_id');
        $assigned = [];

        foreach ($detectionResult['cells'] as $cell) {
            $rowPred = null;
            $maxIntersection = 0.0;
            foreach ($detectionResult['rows'] as $row) {
                if (isset($overlapperRows[$row['row_id']])) {
                    continue;
                }
                $intersection = $this->intersectionPct($cell['bbox'], $row['bbox']);
                if ($intersection > $maxIntersection && $intersection > $thresh) {
                    $maxIntersection = $intersection;
                    $rowPred = (int) $row['row_id'];
                }
            }

            $colPred = null;
            $maxIntersection = 0.0;
            foreach ($detectionResult['cols'] as $col) {
                if (isset($overlapperCols[$col['col_id']])) {
                    continue;
                }
                $intersection = $this->intersectionPct($cell['bbox'], $col['bbox']);
                if ($intersection > $maxIntersection && $intersection > $thresh) {
                    $maxIntersection = $intersection;
                    $colPred = (int) $col['col_id'];
                }
            }

            $assigned[] = [
                'bbox' => $cell['bbox'],
                'text' => (string) $cell['text'],
                'row_ids' => [$rowPred],
                'col_ids' => [$colPred],
            ];
        }

        return $assigned;
    }

    /**
     * @param list<array<string, mixed>> $rows
     * @return array<int, true>
     */
    private function overlapperIds(array $rows, string $field, float $thresh = 0.3): array
    {
        $overlappers = [];
        foreach ($rows as $row) {
            $rowId = (int) $row[$field];
            if (isset($overlappers[$rowId])) {
                continue;
            }

            foreach ($rows as $row2) {
                $row2Id = (int) $row2[$field];
                if ($row2Id === $rowId || isset($overlappers[$row2Id])) {
                    continue;
                }
                if ($this->intersectionPct($row['bbox'], $row2['bbox']) > $thresh) {
                    $overlappers[$row['area'] > $row2['area'] ? $rowId : $row2Id] = true;
                }
            }
        }

        return $overlappers;
    }

    /**
     * @param list<array<string, mixed>> $rows
     * @param list<array<string, mixed>> $cells
     * @return list<array<string, mixed>>
     */
    private function mergeMultilineRows(array $rows, array $cells): array
    {
        if (count($rows) < 2 || $cells === []) {
            return $rows;
        }

        $rowGaps = [];
        for ($i = 0; $i < count($rows) - 1; $i++) {
            $rowGaps[] = $this->rowGap($rows[$i], $rows[$i + 1]);
        }
        if ($rowGaps === []) {
            return $rows;
        }
        $gapThresh = $this->median($rowGaps);

        $allCols = [];
        foreach ($cells as $cell) {
            $allCols[] = $cell['bbox'][0] . ':' . $cell['bbox'][2];
        }
        $allColCount = max(count(array_unique($allCols)), 1);

        $remove = [];
        for ($i = 1; $i < count($rows); $i++) {
            $prev = $rows[$i - 1];
            $row = $rows[$i];
            if ($this->rowGap($prev, $row) > $gapThresh) {
                continue;
            }

            $prevCells = $this->cellsNearestRow($cells, $prev);
            $rowCells = $this->cellsNearestRow($cells, $row);
            if ($rowCells === []) {
                continue;
            }

            $prevCols = $this->cellColumnKeys($prevCells);
            $rowCols = $this->cellColumnKeys($rowCells);
            if (array_diff($rowCols, $prevCols) !== []) {
                continue;
            }
            if ((count($rowCols) / $allColCount) > 0.5) {
                continue;
            }

            $rows[$i - 1]['bbox'] = [
                min($rows[$i - 1]['bbox'][0], $rows[$i]['bbox'][0]),
                min($rows[$i - 1]['bbox'][1], $rows[$i]['bbox'][1]),
                max($rows[$i - 1]['bbox'][2], $rows[$i]['bbox'][2]),
                max($rows[$i - 1]['bbox'][3], $rows[$i]['bbox'][3]),
            ];
            $rows[$i - 1]['width'] = $rows[$i - 1]['bbox'][2] - $rows[$i - 1]['bbox'][0];
            $rows[$i - 1]['height'] = $rows[$i - 1]['bbox'][3] - $rows[$i - 1]['bbox'][1];
            $rows[$i - 1]['area'] = $this->area($rows[$i - 1]['bbox']);
            $remove[$i] = true;
        }

        if ($remove === []) {
            return $rows;
        }

        $newRows = [];
        foreach ($rows as $idx => $row) {
            if (isset($remove[$idx])) {
                continue;
            }
            $row['row_id'] = count($newRows);
            $newRows[] = $row;
        }

        return $newRows;
    }

    /**
     * @param list<array<string, mixed>> $cells
     * @return list<array<string, mixed>>
     */
    private function cellsNearestRow(array $cells, array $row): array
    {
        $out = [];
        foreach ($cells as $cell) {
            $center = ($cell['bbox'][1] + $cell['bbox'][3]) / 2.0;
            if ($center >= $row['bbox'][1] && $center <= $row['bbox'][3]) {
                $out[] = $cell;
            }
        }

        return $out;
    }

    /**
     * @param list<array<string, mixed>> $cells
     * @return list<string>
     */
    private function cellColumnKeys(array $cells): array
    {
        $keys = [];
        foreach ($cells as $cell) {
            $keys[] = $cell['bbox'][0] . ':' . $cell['bbox'][2];
        }

        return array_values(array_unique($keys));
    }

    /**
     * @param list<array<string, mixed>> $cells
     * @param list<array<string, mixed>> $rows
     * @param list<array<string, mixed>> $cols
     */
    private function assignOverlappers(array &$cells, array $rows, array $cols, float $thresh = 0.5): void
    {
        $overlapperRows = $this->overlapperIds($rows, 'row_id');
        $overlapperCols = $this->overlapperIds($cols, 'col_id');

        foreach ($cells as &$cell) {
            $rowPred = null;
            $maxIntersection = 0.0;
            foreach ($rows as $row) {
                if (!isset($overlapperRows[$row['row_id']])) {
                    continue;
                }
                $intersection = $this->intersectionPct($cell['bbox'], $row['bbox']);
                if ($intersection > $maxIntersection && $intersection > $thresh) {
                    $maxIntersection = $intersection;
                    $rowPred = (int) $row['row_id'];
                }
            }

            $colPred = null;
            $maxIntersection = 0.0;
            foreach ($cols as $col) {
                if (!isset($overlapperCols[$col['col_id']])) {
                    continue;
                }
                $intersection = $this->intersectionPct($cell['bbox'], $col['bbox']);
                if ($intersection > $maxIntersection && $intersection > $thresh) {
                    $maxIntersection = $intersection;
                    $colPred = (int) $col['col_id'];
                }
            }

            if ($cell['row_ids'][0] === null) {
                $cell['row_ids'] = [$rowPred];
            }
            if ($cell['col_ids'][0] === null) {
                $cell['col_ids'] = [$colPred];
            }
        }
        unset($cell);
    }

    /**
     * @param list<array<string, mixed>> $cells
     * @param list<array<string, mixed>> $rows
     * @param list<array<string, mixed>> $cols
     */
    private function assignUnassigned(array &$cells, array $rows, array $cols): void
    {
        $rotated = $this->isRotated($rows, $cols);
        foreach ($cells as &$cell) {
            if ($cell['row_ids'][0] === null) {
                $closestRow = null;
                $minDist = null;
                foreach ($rows as $row) {
                    $dist = $rotated ? $this->centerXDistance($cell['bbox'], $row['bbox']) : $this->centerYDistance($cell['bbox'], $row['bbox']);
                    if ($minDist === null || $dist < $minDist) {
                        $closestRow = (int) $row['row_id'];
                        $minDist = $dist;
                    }
                }
                $cell['row_ids'] = [$closestRow];
            }

            if ($cell['col_ids'][0] === null) {
                $closestCol = null;
                $minDist = null;
                foreach ($cols as $col) {
                    $dist = $rotated ? $this->centerYDistance($cell['bbox'], $col['bbox']) : $this->centerXDistance($cell['bbox'], $col['bbox']);
                    if ($minDist === null || $dist < $minDist) {
                        $closestCol = (int) $col['col_id'];
                        $minDist = $dist;
                    }
                }
                $cell['col_ids'] = [$closestCol];
            }
        }
        unset($cell);
    }

    /**
     * @param list<array<string, mixed>> $cells
     * @param list<array<string, mixed>> $rows
     * @param list<array<string, mixed>> $cols
     */
    private function handleRowColSpans(array &$cells, array $rows, array $cols, float $thresh = 0.25): void
    {
        $rotated = $this->isRotated($rows, $cols);

        foreach ($cells as &$cell) {
            foreach ($cols as $col) {
                $pct = $rotated ? $this->intersectionYPct($cell['bbox'], $col['bbox']) : $this->intersectionXPct($cell['bbox'], $col['bbox']);
                $otherCellExists = false;
                foreach ($cells as $candidate) {
                    if ($candidate['col_ids'][0] === $col['col_id'] && $candidate['row_ids'][0] === $cell['row_ids'][0]) {
                        $otherCellExists = true;
                        break;
                    }
                }
                if ($pct > $thresh && !$otherCellExists) {
                    $cell['col_ids'][] = (int) $col['col_id'];
                } else {
                    break;
                }
            }
            sort($cell['col_ids']);
        }
        unset($cell);

        foreach ($cells as &$cell) {
            foreach ($rows as $row) {
                $pct = $rotated ? $this->intersectionXPct($cell['bbox'], $row['bbox']) : $this->intersectionYPct($cell['bbox'], $row['bbox']);
                $otherCellExists = false;
                foreach ($cells as $candidate) {
                    if ($candidate['row_ids'][0] === $row['row_id'] && $candidate['col_ids'][0] === $cell['col_ids'][0]) {
                        $otherCellExists = true;
                        break;
                    }
                }
                if ($pct > $thresh && !$otherCellExists) {
                    $cell['row_ids'][] = (int) $row['row_id'];
                } else {
                    break;
                }
            }
        }
        unset($cell);
    }

    /**
     * @param list<array<string, mixed>> $cells
     * @param array{width: int, height: int} $imageSize
     * @return list<array{bbox: list<float>, text: string, row_ids: list<int|null>, col_ids: list<int|null>}>
     */
    private function heuristicLayout(array $cells, array $imageSize, float $rowTol = 0.01): array
    {
        usort($cells, static fn (array $left, array $right): int => (($left['bbox'][1] + $left['bbox'][3]) <=> ($right['bbox'][1] + $right['bbox'][3]))
            ?: ($left['bbox'][0] <=> $right['bbox'][0]));

        $rows = [];
        $current = [];
        $yBottom = null;

        foreach ($cells as $cell) {
            $height = max(1.0, (float) $imageSize['height']);
            $normedStart = $cell['bbox'][1] / $height;
            $normedEnd = $cell['bbox'][3] / $height;

            if ($yBottom === null) {
                $yBottom = $normedEnd;
            }

            $yDist = min(abs($normedStart - $yBottom), abs($normedEnd - $yBottom));
            if ($yDist < $rowTol) {
                $current[] = $cell;
            } else {
                if ($current !== []) {
                    $rows[] = $current;
                }
                $current = [$cell];
                $yBottom = $normedEnd;
            }
        }
        if ($current !== []) {
            $rows[] = $current;
        }

        $assigned = [];
        foreach ($rows as $rowIndex => $row) {
            usort($row, static fn (array $left, array $right): int => $left['bbox'][0] <=> $right['bbox'][0]);
            foreach ($row as $colIndex => $cell) {
                $assigned[] = [
                    'bbox' => $cell['bbox'],
                    'text' => (string) $cell['text'],
                    'row_ids' => [$rowIndex],
                    'col_ids' => [$colIndex],
                ];
            }
        }

        return $assigned;
    }

    /**
     * @param list<array<string, mixed>> $rows
     * @param list<array<string, mixed>> $cols
     */
    private function isRotated(array $rows, array $cols): bool
    {
        $rowWidths = array_sum(array_map(static fn (array $row): float => (float) $row['width'], $rows));
        $rowHeights = array_sum(array_map(static fn (array $row): float => (float) $row['height'], $rows)) + 1.0;
        $rowRatio = $rowWidths / $rowHeights;

        $colWidths = array_sum(array_map(static fn (array $col): float => (float) $col['width'], $cols));
        $colHeights = array_sum(array_map(static fn (array $col): float => (float) $col['height'], $cols)) + 1.0;
        $colRatio = $colWidths / $colHeights;

        return $rowRatio * 2.0 < $colRatio;
    }

    /**
     * @param list<array<string, mixed>> $cells
     * @return list<array<string, mixed>>
     */
    private function sortCells(array $cells): array
    {
        $order = $this->sortWithinCell($cells);
        foreach ($cells as $idx => &$cell) {
            $cell['order'] = $order[$idx];
        }
        unset($cell);

        usort($cells, static fn (array $left, array $right): int => ($left['row_ids'][0] <=> $right['row_ids'][0])
            ?: ($left['col_ids'][0] <=> $right['col_ids'][0])
            ?: (($left['order'] ?? 0) <=> ($right['order'] ?? 0)));

        return $cells;
    }

    /**
     * @param list<array<string, mixed>> $cells
     * @return list<int>
     */
    private function sortWithinCell(array $cells, float $tolerance = 5.0): array
    {
        $groups = [];
        foreach ($cells as $idx => $cell) {
            $bbox = $this->bbox($cell['bbox'] ?? null);
            $groupKey = (string) round((($bbox[1] + $bbox[3]) / 2.0) / $tolerance, 0, PHP_ROUND_HALF_EVEN);
            $groups[$groupKey][] = [$idx, $bbox[0]];
        }
        ksort($groups, SORT_NUMERIC);

        $sortedIndexes = [];
        foreach ($groups as $group) {
            usort($group, static fn (array $left, array $right): int => ($left[1] <=> $right[1]) ?: ($left[0] <=> $right[0]));
            foreach ($group as $entry) {
                $sortedIndexes[] = $entry[0];
            }
        }

        $order = [];
        foreach ($sortedIndexes as $position => $originalIndex) {
            $order[$originalIndex] = $position;
        }
        ksort($order, SORT_NUMERIC);

        return array_values($order);
    }

    /**
     * @param list<list<string>> $matrix
     */
    private function githubTable(array $matrix): string
    {
        if ($matrix === []) {
            return '';
        }

        $columns = max(array_map('count', $matrix));
        foreach ($matrix as &$row) {
            while (count($row) < $columns) {
                $row[] = '';
            }
        }
        unset($row);

        $widths = array_fill(0, $columns, 0);
        foreach ($matrix as $row) {
            foreach ($row as $idx => $cell) {
                $widths[$idx] = max($widths[$idx], strlen($cell));
            }
        }

        $lines = [];
        $lines[] = $this->githubRow($matrix[0], $widths);
        $lines[] = '|' . implode('|', array_map(static fn (int $width): string => str_repeat('-', max(3, $width + 2)), $widths)) . '|';
        foreach (array_slice($matrix, 1) as $row) {
            $lines[] = $this->githubRow($row, $widths);
        }

        return implode("\n", $lines);
    }

    /**
     * @param list<string> $row
     * @param list<int> $widths
     */
    private function githubRow(array $row, array $widths): string
    {
        $cells = [];
        foreach ($row as $idx => $cell) {
            $cells[] = ' ' . str_pad($cell, $widths[$idx], ' ', STR_PAD_RIGHT) . ' ';
        }

        return '|' . implode('|', $cells) . '|';
    }

    private function markdownReplaceAll(string $text): string
    {
        $text = preg_replace('/(?:\s*\.\s*){4,}/', ' ', $text) ?? $text;
        $text = trim(preg_replace('/[\r\n]+/', ' ', $text) ?? $text);

        return str_replace(['|', '-'], ['\\|', '\\-'], $text);
    }

    /**
     * @param list<int> $ids
     * @return list<int>
     */
    private function sortedUniqueIds(array $ids): array
    {
        $ids = array_values(array_unique($ids));
        sort($ids, SORT_NUMERIC);

        return $ids;
    }

    /**
     * @param mixed $bbox
     * @return list<float>
     */
    private function bbox(mixed $bbox): array
    {
        if (!is_array($bbox) || count($bbox) !== 4) {
            throw new InvalidArgumentException('Table geometry entries must include a four-value bbox.');
        }
        $values = array_values($bbox);
        foreach ($values as $value) {
            if (!is_int($value) && !is_float($value)) {
                throw new InvalidArgumentException('Table bbox values must be numeric.');
            }
        }

        return array_map(static fn (int|float $value): float => (float) $value, $values);
    }

    /**
     * @param array{width?: int|float, height?: int|float}|list<int|float> $imageSize
     * @return array{width: int, height: int}
     */
    private function imageSize(array $imageSize): array
    {
        $width = $imageSize['width'] ?? $imageSize[0] ?? null;
        $height = $imageSize['height'] ?? $imageSize[1] ?? null;
        if ((!is_int($width) && !is_float($width)) || (!is_int($height) && !is_float($height)) || $width <= 0 || $height <= 0) {
            throw new InvalidArgumentException('Table image sizes must include positive width and height.');
        }

        return ['width' => (int) round($width), 'height' => (int) round($height)];
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

        $area = $this->area($left);
        if ($area == 0.0) {
            return 0.0;
        }

        return (($xRight - $xLeft) * ($yBottom - $yTop)) / $area;
    }

    /**
     * @param list<float> $bbox
     */
    private function area(array $bbox): float
    {
        return ($bbox[2] - $bbox[0]) * ($bbox[3] - $bbox[1]);
    }

    private function rowGap(array $row1, array $row2): float
    {
        return min(abs($row1['bbox'][1] - $row2['bbox'][3]), abs($row2['bbox'][1] - $row1['bbox'][3]));
    }

    /**
     * @param list<float> $values
     */
    private function median(array $values): float
    {
        sort($values, SORT_NUMERIC);
        $count = count($values);
        $middle = intdiv($count, 2);
        if ($count % 2 === 1) {
            return (float) $values[$middle];
        }

        return ((float) $values[$middle - 1] + (float) $values[$middle]) / 2.0;
    }

    /**
     * @param list<float> $left
     * @param list<float> $right
     */
    private function centerXDistance(array $left, array $right): float
    {
        return abs((($left[0] + $left[2]) / 2.0) - (($right[0] + $right[2]) / 2.0));
    }

    /**
     * @param list<float> $left
     * @param list<float> $right
     */
    private function centerYDistance(array $left, array $right): float
    {
        return abs((($left[1] + $left[3]) / 2.0) - (($right[1] + $right[3]) / 2.0));
    }

    /**
     * @param list<float> $left
     * @param list<float> $right
     */
    private function intersectionXPct(array $left, array $right): float
    {
        $width = $left[2] - $left[0];
        if ($width == 0.0) {
            return 0.0;
        }

        return max(0.0, min($left[2], $right[2]) - max($left[0], $right[0])) / $width;
    }

    /**
     * @param list<float> $left
     * @param list<float> $right
     */
    private function intersectionYPct(array $left, array $right): float
    {
        $height = $left[3] - $left[1];
        if ($height == 0.0) {
            return 0.0;
        }

        return max(0.0, min($left[3], $right[3]) - max($left[1], $right[1])) / $height;
    }
}
