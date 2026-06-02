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
     * Native boundary for tabled.heuristics.cells::find_column_separators.
     *
     * The locked upstream helper normalizes x coordinates, filters values that
     * occur only once, clusters the remaining left/right/center coordinates
     * with one-dimensional DBSCAN, then chooses the coordinate family that
     * yields the most separators.
     *
     * @param list<list<array<string, mixed>>> $rows
     * @param array{width?: int|float, height?: int|float}|list<int|float> $imageSize
     * @return list<float>
     */
    public function heuristicColumnSeparators(array $rows, array $imageSize, float $roundFactor = 0.002, int $minCount = 1): array
    {
        $normalizedRows = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                throw new InvalidArgumentException('Heuristic table rows must be arrays.');
            }
            $normalizedRows[] = $this->normalizeCells($row);
        }

        return $this->findColumnSeparators($normalizedRows, $this->imageSize($imageSize), $roundFactor, $minCount);
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
            $tableBbox = $this->bbox($tableBboxes[$idx]);
            $imageSize = $this->imageSize($imageSizes[$idx]);

            $textLine = $textLines[$idx];
            $textBlocks = $this->tableBlocksFromTextLine($textLine, $tableBbox, $imageSize);

            if ($textLine === null || $detectBoxes || $textBlocks === []) {
                if (!array_key_exists($idx, $suppliedDetections)) {
                    throw new InvalidArgumentException('Missing supplied detector cells for table index ' . $idx . '.');
                }
                $tableCells[] = $this->normalizePositiveAreaCells($suppliedDetections[$idx]);
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
     * @param array<int, list<string|array{text?: string}>|array{text_lines?: list<string|array{text?: string}>, lines?: list<string|array{text?: string}>}> $suppliedOcrTextLines
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

        $initialAssigned = $this->initialAssignment(['cells' => $cells, 'rows' => $rows, 'cols' => $cols]);
        $rows = $this->mergeMultilineRows($rows, $initialAssigned);
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
     * Review metadata for tabled.assignment SpanTableCell row/column spans.
     *
     * Upstream keeps merged cells as row_ids/col_ids on SpanTableCell, while
     * Markdown output uses only the first row and column. WordPress imports need
     * the full grid geometry to emit stable rowspan/colspan attributes.
     *
     * @param list<array<string, mixed>> $cells Assigned cells from assignRowsColumns().
     * @param list<array<string, mixed>> $rows Optional model row bands in table-image coordinates.
     * @param list<array<string, mixed>> $cols Optional model column bands in table-image coordinates.
     * @return list<array<string, mixed>>
     */
    public function mergedCellGeometry(array $cells, array $rows = [], array $cols = []): array
    {
        $cells = $this->sortCells($this->normalizeAssignedCells($cells));
        $rows = $this->normalizeRowsOrCols($rows, 'row_id');
        $cols = $this->normalizeRowsOrCols($cols, 'col_id');
        $rowBboxes = $this->bboxesById($rows, 'row_id');
        $colBboxes = $this->bboxesById($cols, 'col_id');
        $rotated = $rows !== [] && $cols !== [] && $this->isRotated($rows, $cols);

        $geometry = [];
        foreach ($cells as $cell) {
            $rowIds = $this->nonNullSortedIds($cell['row_ids']);
            $colIds = $this->nonNullSortedIds($cell['col_ids']);
            if (count($rowIds) <= 1 && count($colIds) <= 1) {
                continue;
            }

            $entry = [
                'text' => (string) $cell['text'],
                'row_ids' => $rowIds,
                'col_ids' => $colIds,
                'rowspan' => count($rowIds),
                'colspan' => count($colIds),
                'anchor' => [
                    'row_id' => $rowIds[0],
                    'col_id' => $colIds[0],
                ],
                'grid_cells' => $this->gridCellsForSpan($rowIds, $colIds),
                'cell_bbox' => $cell['bbox'],
            ];

            $gridBbox = $this->gridBboxForSpan($rowIds, $colIds, $rowBboxes, $colBboxes, $rotated);
            if ($gridBbox !== null) {
                $entry['grid_bbox'] = $gridBbox;
            }

            $geometry[] = $entry;
        }

        return $geometry;
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
        foreach ($this->ocrTextLineItems($ocrTextLines) as $idx => $ocrLine) {
            if (!isset($cells[$idx])) {
                break;
            }
            $cells[$idx]['text'] = is_array($ocrLine) ? (string) ($ocrLine['text'] ?? '') : (string) $ocrLine;
        }

        return $cells;
    }

    /**
     * @param list<string|array{text?: string}>|array{text_lines?: list<string|array{text?: string}>, lines?: list<string|array{text?: string}>} $ocrTextLines
     * @return list<string|array{text?: string}>
     */
    private function ocrTextLineItems(array $ocrTextLines): array
    {
        if (array_is_list($ocrTextLines)) {
            return $ocrTextLines;
        }

        foreach (['text_lines', 'lines'] as $field) {
            if (!array_key_exists($field, $ocrTextLines)) {
                continue;
            }
            if (!is_array($ocrTextLines[$field]) || !array_is_list($ocrTextLines[$field])) {
                throw new InvalidArgumentException('Supplied OCR prediction ' . $field . ' must be a list.');
            }

            return $ocrTextLines[$field];
        }

        throw new InvalidArgumentException('Supplied OCR prediction must be a list or include text_lines.');
    }

    /**
     * @param mixed $textLine
     * @param list<float> $tableBbox
     * @param array{width: int, height: int} $imageSize
     * @return list<array<string, mixed>>
     */
    private function tableBlocksFromTextLine(mixed $textLine, array $tableBbox, array $imageSize): array
    {
        if (!is_array($textLine)) {
            return [];
        }

        $rotation = (int) ($textLine['rotation'] ?? 0);
        if (isset($textLine['table_blocks']) && is_array($textLine['table_blocks'])) {
            return $this->filterTextBlocksToTable(
                array_values(array_filter($textLine['table_blocks'], static fn (mixed $block): bool => is_array($block))),
                $tableBbox,
                $imageSize,
                $rotation
            );
        }
        if (isset($textLine['blocks']) && is_array($textLine['blocks'])) {
            return $this->filterTextBlocksToTable(
                array_values(array_filter($textLine['blocks'], static fn (mixed $block): bool => is_array($block))),
                $tableBbox,
                $imageSize,
                $rotation
            );
        }

        $isList = array_is_list($textLine);
        if ($isList) {
            return $this->filterTextBlocksToTable(
                array_values(array_filter($textLine, static fn (mixed $block): bool => is_array($block))),
                $tableBbox,
                $imageSize
            );
        }

        return [];
    }

    /**
     * @param list<array<string, mixed>> $blocks
     * @param list<float> $tableBbox
     * @param array{width: int, height: int} $imageSize
     * @return list<array<string, mixed>>
     */
    private function filterTextBlocksToTable(array $blocks, array $tableBbox, array $imageSize, int $rotation = 0): array
    {
        $tableBbox = [
            max(0.0, min((float) $imageSize['width'], $tableBbox[0])),
            max(0.0, min((float) $imageSize['height'], $tableBbox[1])),
            max(0.0, min((float) $imageSize['width'], $tableBbox[2])),
            max(0.0, min((float) $imageSize['height'], $tableBbox[3])),
        ];

        if ($this->containsPdfTextLines($blocks)) {
            return $this->pdfTextCellsForTable($blocks, $tableBbox, $imageSize, $rotation);
        }

        $filtered = [];
        foreach ($this->normalizeCells($blocks) as $block) {
            if ($this->intersectionPct($block['bbox'], $tableBbox) >= 0.8) {
                $block['bbox'] = $this->relativeToTableBbox($block['bbox'], $tableBbox);
                $filtered[] = $block;
            }
        }

        return $this->sortTextCells($filtered);
    }

    /**
     * @param list<array<string, mixed>> $blocks
     */
    private function containsPdfTextLines(array $blocks): bool
    {
        foreach ($blocks as $block) {
            if (is_array($block) && isset($block['lines']) && is_array($block['lines'])) {
                return true;
            }
        }

        return false;
    }

    /**
     * Native boundary for surya.input.pdflines::get_table_blocks.
     *
     * Upstream filters high-resolution pdftext lines by table overlap, splits
     * their character streams into table cells, then rewrites coordinates to
     * be relative to the cropped table image before tabled recognition.
     *
     * @param list<array<string, mixed>> $blocks
     * @param list<float> $tableBbox
     * @param array{width: int, height: int} $imageSize
     * @return list<array<string, mixed>>
     */
    private function pdfTextCellsForTable(array $blocks, array $tableBbox, array $imageSize, int $rotation): array
    {
        $cells = [];
        $spaceThresh = $this->dynamicGapThreshold($blocks, $imageSize, $rotation);

        foreach ($blocks as $block) {
            if (!is_array($block) || !isset($block['lines']) || !is_array($block['lines'])) {
                continue;
            }

            foreach ($block['lines'] as $line) {
                if (!is_array($line)) {
                    continue;
                }

                $lineBbox = $this->lineBbox($line);
                if ($lineBbox === null || $this->intersectionPct($lineBbox, $tableBbox) < 0.8) {
                    continue;
                }

                $lineCells = $this->cellsFromPdfTextLine($line, $imageSize, $spaceThresh, $rotation);
                if ($lineCells === []) {
                    $text = $this->lineText($line);
                    if (trim($text) !== '') {
                        $lineCells[] = [
                            'bbox' => $lineBbox,
                            'text' => $text,
                        ];
                    }
                }

                foreach ($lineCells as $cell) {
                    $cell['bbox'] = $this->relativeToTableBbox($cell['bbox'], $tableBbox);
                    $cells[] = $cell;
                }
            }
        }

        return $this->sortTextCells($cells);
    }

    /**
     * @param array<string, mixed> $line
     * @param array{width: int, height: int} $imageSize
     * @return list<array{bbox: list<float>, text: string}>
     */
    private function cellsFromPdfTextLine(array $line, array $imageSize, float $spaceThresh, int $rotation): array
    {
        $cells = [];
        $currentText = null;
        $currentBbox = null;

        foreach ($this->lineChars($line) as $char) {
            $text = (string) $char['text'];
            $bbox = $char['bbox'];

            if ($currentText === null || $currentBbox === null) {
                $currentText = $text;
                $currentBbox = $bbox;
                continue;
            }

            if ($this->samePdfTextSpan($bbox, $currentBbox, $imageSize, $spaceThresh, $rotation)) {
                $currentText .= $text;
                $currentBbox = [
                    min($currentBbox[0], $bbox[0]),
                    min($currentBbox[1], $bbox[1]),
                    max($currentBbox[2], $bbox[2]),
                    max($currentBbox[3], $bbox[3]),
                ];
                continue;
            }

            if (trim($currentText) !== '') {
                $cells[] = [
                    'bbox' => $currentBbox,
                    'text' => $currentText,
                ];
            }
            $currentText = $text;
            $currentBbox = $bbox;
        }

        if ($currentText !== null && $currentBbox !== null && trim($currentText) !== '') {
            $cells[] = [
                'bbox' => $currentBbox,
                'text' => $currentText,
            ];
        }

        return $cells;
    }

    /**
     * @param array<string, mixed> $line
     * @return list<array{bbox: list<float>, text: string}>
     */
    private function lineChars(array $line): array
    {
        $chars = [];
        foreach (($line['spans'] ?? []) as $span) {
            if (!is_array($span) || !isset($span['chars']) || !is_array($span['chars'])) {
                continue;
            }

            foreach ($span['chars'] as $char) {
                if (!is_array($char)) {
                    continue;
                }
                $bbox = $this->nullableBbox($char['bbox'] ?? null);
                if ($bbox === null) {
                    continue;
                }
                $chars[] = [
                    'bbox' => $bbox,
                    'text' => (string) ($char['char'] ?? $char['text'] ?? ''),
                ];
            }
        }

        return $chars;
    }

    /**
     * @param array<string, mixed> $line
     * @return list<float>|null
     */
    private function lineBbox(array $line): ?array
    {
        $bbox = $this->nullableBbox($line['bbox'] ?? null);
        if ($bbox !== null) {
            return $bbox;
        }

        $charBoxes = array_column($this->lineChars($line), 'bbox');
        if ($charBoxes === []) {
            return null;
        }

        return [
            min(array_column($charBoxes, 0)),
            min(array_column($charBoxes, 1)),
            max(array_column($charBoxes, 2)),
            max(array_column($charBoxes, 3)),
        ];
    }

    /**
     * @param array<string, mixed> $line
     */
    private function lineText(array $line): string
    {
        if (array_key_exists('text', $line) && is_scalar($line['text'])) {
            return (string) $line['text'];
        }

        $parts = [];
        foreach (($line['spans'] ?? []) as $span) {
            if (is_array($span) && array_key_exists('text', $span) && is_scalar($span['text'])) {
                $parts[] = (string) $span['text'];
            }
        }

        return implode('', $parts);
    }

    /**
     * @param list<array<string, mixed>> $blocks
     * @param array{width: int, height: int} $imageSize
     */
    private function dynamicGapThreshold(array $blocks, array $imageSize, int $rotation, float $default = 0.01, int $minChars = 100): float
    {
        $spaceDists = [];
        foreach ($blocks as $block) {
            if (!is_array($block) || !isset($block['lines']) || !is_array($block['lines'])) {
                continue;
            }

            foreach ($block['lines'] as $line) {
                if (!is_array($line)) {
                    continue;
                }

                foreach (($line['spans'] ?? []) as $span) {
                    if (!is_array($span) || !isset($span['chars']) || !is_array($span['chars'])) {
                        continue;
                    }
                    $chars = array_values(array_filter(
                        $span['chars'],
                        static fn (mixed $char): bool => is_array($char) && is_array($char['bbox'] ?? null)
                    ));
                    for ($idx = 1; $idx < count($chars); $idx++) {
                        $prev = $this->nullableBbox($chars[$idx - 1]['bbox'] ?? null);
                        $curr = $this->nullableBbox($chars[$idx]['bbox'] ?? null);
                        if ($prev === null || $curr === null) {
                            continue;
                        }
                        if ($rotation === 90) {
                            $spaceDists[] = ($curr[0] - $prev[2]) / max(1.0, (float) $imageSize['width']);
                        } elseif ($rotation === 180) {
                            $spaceDists[] = ($curr[1] - $prev[3]) / max(1.0, (float) $imageSize['height']);
                        } elseif ($rotation === 270) {
                            $spaceDists[] = ($prev[0] - $curr[2]) / max(1.0, (float) $imageSize['width']);
                        } else {
                            $spaceDists[] = ($prev[1] - $curr[3]) / max(1.0, (float) $imageSize['height']);
                        }
                    }
                }
            }
        }

        if (count($spaceDists) <= $minChars) {
            return $default;
        }

        sort($spaceDists, SORT_NUMERIC);
        $index = (int) floor((count($spaceDists) - 1) * 0.8);

        return max($default, (float) $spaceDists[$index]);
    }

    /**
     * @param list<float> $bbox
     * @param list<float> $currentBbox
     * @param array{width: int, height: int} $imageSize
     */
    private function samePdfTextSpan(array $bbox, array $currentBbox, array $imageSize, float $spaceThresh, int $rotation): bool
    {
        $normalizedDiff = static function (
            float $left,
            float $right,
            int $dimension,
            float $multiplier = 1.0,
            bool $absolute = true
        ) use ($imageSize, $spaceThresh): bool {
            $size = $dimension === 0 ? (float) $imageSize['width'] : (float) $imageSize['height'];
            $diff = $left - $right;
            if ($absolute) {
                $diff = abs($diff);
            }

            return ($diff / max(1.0, $size)) < ($spaceThresh * $multiplier);
        };

        if ($rotation === 90) {
            return $normalizedDiff($bbox[0], $currentBbox[0], 0, 1.0, false)
                && $normalizedDiff($bbox[1], $currentBbox[3], 1)
                && $normalizedDiff($bbox[0], $currentBbox[0], 0, 5.0);
        }
        if ($rotation === 180) {
            return $normalizedDiff($bbox[2], $currentBbox[0], 0, 1.0, false)
                && $normalizedDiff($bbox[1], $currentBbox[1], 1)
                && $normalizedDiff($bbox[2], $currentBbox[0], 1, 5.0);
        }
        if ($rotation === 270) {
            return $normalizedDiff($bbox[0], $currentBbox[0], 0, 1.0, false)
                && $normalizedDiff($bbox[3], $currentBbox[1], 1)
                && $normalizedDiff($bbox[0], $currentBbox[0], 1, 5.0);
        }

        return $normalizedDiff($bbox[0], $currentBbox[2], 0, 1.0, false)
            && $normalizedDiff($bbox[1], $currentBbox[1], 1)
            && $normalizedDiff($bbox[0], $currentBbox[2], 1, 5.0);
    }

    /**
     * @param list<array<string, mixed>> $cells
     * @return list<array<string, mixed>>
     */
    private function sortTextCells(array $cells): array
    {
        $groups = [];
        foreach ($cells as $cell) {
            $bbox = $cell['bbox'];
            $groupKey = (string) (round($bbox[1] / 1.25) * 1.25);
            $groups[$groupKey][] = $cell;
        }
        ksort($groups, SORT_NUMERIC);

        $sorted = [];
        foreach ($groups as $group) {
            usort($group, static fn (array $left, array $right): int => ($left['bbox'][0] <=> $right['bbox'][0]));
            array_push($sorted, ...$group);
        }

        return $sorted;
    }

    /**
     * @param mixed $bbox
     * @return list<float>|null
     */
    private function nullableBbox(mixed $bbox): ?array
    {
        if (!is_array($bbox) || count($bbox) !== 4) {
            return null;
        }

        $values = array_values($bbox);
        foreach ($values as $value) {
            if (!is_int($value) && !is_float($value)) {
                return null;
            }
        }

        return array_map(static fn (int|float $value): float => (float) $value, $values);
    }

    /**
     * @param list<float> $bbox
     * @param list<float> $tableBbox
     * @return list<float>
     */
    private function relativeToTableBbox(array $bbox, array $tableBbox): array
    {
        return [
            $bbox[0] - $tableBbox[0],
            $bbox[1] - $tableBbox[1],
            $bbox[2] - $tableBbox[0],
            $bbox[3] - $tableBbox[1],
        ];
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
     * @return list<array<string, mixed>>
     */
    private function normalizePositiveAreaCells(array $cells): array
    {
        return array_values(array_filter(
            $this->normalizeCells($cells),
            fn (array $cell): bool => $this->area($cell['bbox']) > 0.0
        ));
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
     * @param list<array<string, mixed>> $assignedCells
     * @return list<array<string, mixed>>
     */
    private function mergeMultilineRows(array $rows, array $assignedCells): array
    {
        if (count($rows) < 2 || $assignedCells === []) {
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
        foreach ($assignedCells as $cell) {
            $allCols[] = $cell['col_ids'][0] ?? null;
        }
        $allColCount = max(count(array_unique($allCols, SORT_REGULAR)), 1);

        $remove = [];
        for ($i = 1; $i < count($rows); $i++) {
            $prev = $rows[$i - 1];
            $row = $rows[$i];
            if ($this->rowGap($prev, $row) > $gapThresh) {
                continue;
            }

            $prevCells = $this->cellsAssignedToRow($assignedCells, (int) $prev['row_id']);
            $rowCells = $this->cellsAssignedToRow($assignedCells, (int) $row['row_id']);
            if ($rowCells === []) {
                continue;
            }

            $prevCols = $this->cellColumnIds($prevCells);
            $rowCols = $this->cellColumnIds($rowCells);
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
    private function cellsAssignedToRow(array $cells, int $rowId): array
    {
        $out = [];
        foreach ($cells as $cell) {
            if (($cell['row_ids'][0] ?? null) === $rowId) {
                $out[] = $cell;
            }
        }

        return $out;
    }

    /**
     * @param list<array<string, mixed>> $cells
     * @return list<int|null>
     */
    private function cellColumnIds(array $cells): array
    {
        $ids = [];
        foreach ($cells as $cell) {
            $id = $cell['col_ids'][0] ?? null;
            $ids[] = $id === null ? null : (int) $id;
        }

        return array_values(array_unique($ids, SORT_REGULAR));
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

        foreach ($cells as $cellIndex => &$cell) {
            foreach ($cols as $col) {
                $pct = $rotated ? $this->intersectionYPct($cell['bbox'], $col['bbox']) : $this->intersectionXPct($cell['bbox'], $col['bbox']);
                if ($pct <= $thresh || in_array((int) $col['col_id'], $cell['col_ids'], true)) {
                    continue;
                }

                if (!$this->hasOtherCellAt($cells, $cellIndex, (int) $cell['row_ids'][0], (int) $col['col_id'])) {
                    $cell['col_ids'][] = (int) $col['col_id'];
                }
            }
            sort($cell['col_ids']);
        }
        unset($cell);

        foreach ($cells as $cellIndex => &$cell) {
            foreach ($rows as $row) {
                $pct = $rotated ? $this->intersectionXPct($cell['bbox'], $row['bbox']) : $this->intersectionYPct($cell['bbox'], $row['bbox']);
                if ($pct <= $thresh || in_array((int) $row['row_id'], $cell['row_ids'], true)) {
                    continue;
                }

                if (!$this->hasOtherCellAt($cells, $cellIndex, (int) $row['row_id'], (int) $cell['col_ids'][0])) {
                    $cell['row_ids'][] = (int) $row['row_id'];
                }
            }
            sort($cell['row_ids']);
        }
        unset($cell);
    }

    /**
     * @param list<array<string, mixed>> $cells
     */
    private function hasOtherCellAt(array $cells, int $selfIndex, int $rowId, int $colId): bool
    {
        foreach ($cells as $candidateIndex => $candidate) {
            if ($candidateIndex === $selfIndex) {
                continue;
            }

            if (($candidate['row_ids'][0] ?? null) === $rowId && ($candidate['col_ids'][0] ?? null) === $colId) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param list<array<string, mixed>> $items
     * @return array<int, list<float>>
     */
    private function bboxesById(array $items, string $idField): array
    {
        $byId = [];
        foreach ($items as $item) {
            $byId[(int) $item[$idField]] = $item['bbox'];
        }

        return $byId;
    }

    /**
     * @param list<int|null> $ids
     * @return list<int>
     */
    private function nonNullSortedIds(array $ids): array
    {
        $out = [];
        foreach ($ids as $id) {
            if ($id !== null) {
                $out[] = (int) $id;
            }
        }
        $out = array_values(array_unique($out));
        sort($out, SORT_NUMERIC);

        return $out;
    }

    /**
     * @param list<int> $rowIds
     * @param list<int> $colIds
     * @return list<array{row_id: int, col_id: int}>
     */
    private function gridCellsForSpan(array $rowIds, array $colIds): array
    {
        $grid = [];
        foreach ($rowIds as $rowId) {
            foreach ($colIds as $colId) {
                $grid[] = [
                    'row_id' => $rowId,
                    'col_id' => $colId,
                ];
            }
        }

        return $grid;
    }

    /**
     * @param list<int> $rowIds
     * @param list<int> $colIds
     * @param array<int, list<float>> $rowBboxes
     * @param array<int, list<float>> $colBboxes
     * @return list<float>|null
     */
    private function gridBboxForSpan(array $rowIds, array $colIds, array $rowBboxes, array $colBboxes, bool $rotated): ?array
    {
        $rowBand = $this->mergedBandBbox($rowIds, $rowBboxes);
        $colBand = $this->mergedBandBbox($colIds, $colBboxes);
        if ($rowBand === null || $colBand === null) {
            return null;
        }

        if ($rotated) {
            return [$rowBand[0], $colBand[1], $rowBand[2], $colBand[3]];
        }

        return [$colBand[0], $rowBand[1], $colBand[2], $rowBand[3]];
    }

    /**
     * @param list<int> $ids
     * @param array<int, list<float>> $bboxes
     * @return list<float>|null
     */
    private function mergedBandBbox(array $ids, array $bboxes): ?array
    {
        $selected = [];
        foreach ($ids as $id) {
            if (isset($bboxes[$id])) {
                $selected[] = $bboxes[$id];
            }
        }
        if ($selected === []) {
            return null;
        }

        return [
            min(array_column($selected, 0)),
            min(array_column($selected, 1)),
            max(array_column($selected, 2)),
            max(array_column($selected, 3)),
        ];
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

        return $this->assignCellsToColumns($rows, $imageSize);
    }

    /**
     * @param list<list<array<string, mixed>>> $rows
     * @param array{width: int, height: int} $imageSize
     * @return list<array{bbox: list<float>, text: string, row_ids: list<int|null>, col_ids: list<int|null>}>
     */
    private function assignCellsToColumns(array $rows, array $imageSize, float $roundFactor = 0.002, float $tolerance = 0.01): array
    {
        $separators = $this->findColumnSeparators($rows, $imageSize, $roundFactor);
        $rowDicts = [];

        foreach ($rows as $rowIndex => $row) {
            $newRow = [];
            $lastColumnIndex = -1;
            $additionalColumnIndex = 0;

            foreach ($row as $cell) {
                $leftEdge = $cell['bbox'][0] / max(1.0, (float) $imageSize['width']);
                $columnIndex = -1;
                foreach ($separators as $index => $separator) {
                    if (($leftEdge - $tolerance) < $separator && $lastColumnIndex < $index) {
                        $columnIndex = $index;
                        break;
                    }
                }

                if ($columnIndex === -1) {
                    $columnIndex = count($separators) + $additionalColumnIndex;
                    $additionalColumnIndex++;
                }

                $newRow[$columnIndex] = $cell;
                $lastColumnIndex = $columnIndex;
            }

            ksort($newRow, SORT_NUMERIC);
            $rowDicts[$rowIndex] = $newRow;
        }

        $assigned = [];
        foreach ($rowDicts as $rowIndex => $row) {
            $column = 0;
            foreach ($row as $cell) {
                $assigned[] = [
                    'bbox' => $cell['bbox'],
                    'text' => (string) $cell['text'],
                    'row_ids' => [$rowIndex],
                    'col_ids' => [$column],
                ];
                $column++;
            }
        }

        return $assigned;
    }

    /**
     * @param list<list<array<string, mixed>>> $rows
     * @param array{width: int, height: int} $imageSize
     * @return list<float>
     */
    private function findColumnSeparators(array $rows, array $imageSize, float $roundFactor = 0.002, int $minCount = 1): array
    {
        $leftEdges = [];
        $rightEdges = [];
        $centers = [];

        foreach ($rows as $row) {
            foreach ($row as $cell) {
                $bbox = $this->bbox($cell['bbox'] ?? null);
                $normalized = [
                    $bbox[0] / max(1.0, (float) $imageSize['width']),
                    $bbox[1] / max(1.0, (float) $imageSize['height']),
                    $bbox[2] / max(1.0, (float) $imageSize['width']),
                    $bbox[3] / max(1.0, (float) $imageSize['height']),
                ];

                // Upstream's round_factor arithmetic is algebraically neutral;
                // keep the same shape so this boundary stays traceable.
                $leftEdges[] = $normalized[0] / $roundFactor * $roundFactor;
                $rightEdges[] = $normalized[2] / $roundFactor * $roundFactor;
                $centers[] = (($normalized[0] + $normalized[2]) / 2.0) * $roundFactor / $roundFactor;
            }
        }

        $clusteredSets = [
            $this->clusterCoords($this->coordinatesAboveMinCount($leftEdges, $minCount), count($rows)),
            $this->clusterCoords($this->coordinatesAboveMinCount($rightEdges, $minCount), count($rows)),
            $this->clusterCoords($this->coordinatesAboveMinCount($centers, $minCount), count($rows)),
        ];

        $separators = [];
        foreach ($clusteredSets as $candidate) {
            if (count($candidate) > count($separators)) {
                $separators = $candidate;
            }
        }

        $separators[] = 1.0;
        array_unshift($separators, 0.0);

        return $separators;
    }

    /**
     * @param list<float> $coords
     * @return list<float>
     */
    private function clusterCoords(array $coords, int $rowCount): array
    {
        if ($coords === []) {
            return [];
        }

        $coords = array_values(array_unique($coords, SORT_REGULAR));
        sort($coords, SORT_NUMERIC);

        $labels = array_fill(0, count($coords), null);
        $visited = array_fill(0, count($coords), false);
        $clusterId = 0;
        $minSamples = max(2, intdiv($rowCount, 4));

        foreach (array_keys($coords) as $pointIndex) {
            if ($visited[$pointIndex]) {
                continue;
            }
            $visited[$pointIndex] = true;
            $neighbors = $this->regionQuery($coords, $pointIndex);
            if (count($neighbors) < $minSamples) {
                $labels[$pointIndex] = -1;
                continue;
            }

            $this->expandCluster($coords, $labels, $visited, $pointIndex, $neighbors, $clusterId, $minSamples);
            $clusterId++;
        }

        $byLabel = [];
        foreach ($labels as $index => $label) {
            $label ??= -1;
            $byLabel[$label][] = $coords[$index];
        }

        $separators = [];
        foreach ($byLabel as $points) {
            $separators[] = array_sum($points) / count($points);
        }
        sort($separators, SORT_NUMERIC);

        return $separators;
    }

    /**
     * @param list<float> $coords
     * @param list<int|null> $labels
     * @param list<bool> $visited
     * @param list<int> $neighbors
     */
    private function expandCluster(array $coords, array &$labels, array &$visited, int $pointIndex, array $neighbors, int $clusterId, int $minSamples): void
    {
        $labels[$pointIndex] = $clusterId;
        $queue = array_values($neighbors);
        for ($cursor = 0; $cursor < count($queue); $cursor++) {
            $neighborIndex = $queue[$cursor];
            if (!$visited[$neighborIndex]) {
                $visited[$neighborIndex] = true;
                $neighborNeighbors = $this->regionQuery($coords, $neighborIndex);
                if (count($neighborNeighbors) >= $minSamples) {
                    foreach ($neighborNeighbors as $candidate) {
                        if (!in_array($candidate, $queue, true)) {
                            $queue[] = $candidate;
                        }
                    }
                }
            }

            if ($labels[$neighborIndex] === null || $labels[$neighborIndex] === -1) {
                $labels[$neighborIndex] = $clusterId;
            }
        }
    }

    /**
     * @param list<float> $coords
     * @return list<int>
     */
    private function regionQuery(array $coords, int $pointIndex, float $eps = 0.01): array
    {
        $neighbors = [];
        foreach ($coords as $index => $coord) {
            if (abs($coord - $coords[$pointIndex]) <= $eps) {
                $neighbors[] = $index;
            }
        }

        return $neighbors;
    }

    /**
     * @param list<float> $coords
     * @return list<float>
     */
    private function coordinatesAboveMinCount(array $coords, int $minCount): array
    {
        $counts = [];
        foreach ($coords as $coord) {
            $key = (string) $coord;
            $counts[$key] = ($counts[$key] ?? 0) + 1;
        }

        $out = [];
        foreach ($coords as $coord) {
            if (($counts[(string) $coord] ?? 0) > $minCount) {
                $out[] = $coord;
            }
        }

        return $out;
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
                $widths[$idx] = max($widths[$idx], $this->displayWidth($cell));
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
            $cells[] = ' ' . $this->padRight($cell, $widths[$idx]) . ' ';
        }

        return '|' . implode('|', $cells) . '|';
    }

    private function padRight(string $text, int $width): string
    {
        $padding = max(0, $width - $this->displayWidth($text));

        return $text . str_repeat(' ', $padding);
    }

    private function displayWidth(string $text): int
    {
        if ($text === '') {
            return 0;
        }

        preg_match_all('/./us', $text, $matches);

        return count($matches[0]);
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
