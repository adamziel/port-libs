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
     * @return array{table_cells: list<list<array<string, mixed>>>, needs_ocr: list<bool>, table_text_cell_boundary_reviews: list<array<string, mixed>|null>}
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
        $cellBoundaryReviews = [];

        for ($idx = 0; $idx < $count; $idx++) {
            $tableBbox = $this->bbox($tableBboxes[$idx]);
            $imageSize = $this->imageSize($imageSizes[$idx]);

            $textLine = $textLines[$idx];
            $textBlocks = $this->tableBlocksFromTextLine($textLine, $tableBbox, $imageSize);

            if ($textLine === null || $detectBoxes || $textBlocks['cells'] === []) {
                if (!array_key_exists($idx, $suppliedDetections)) {
                    throw new InvalidArgumentException('Missing supplied detector cells for table index ' . $idx . '.');
                }
                $tableCells[] = $this->normalizePositiveAreaCells($suppliedDetections[$idx]);
                $needsOcr[] = true;
                $cellBoundaryReviews[] = null;
                continue;
            }

            $tableCells[] = $this->normalizeCells($textBlocks['cells']);
            $needsOcr[] = false;
            $cellBoundaryReviews[] = $textBlocks['boundary_review'];
        }

        return [
            'table_cells' => $tableCells,
            'needs_ocr' => $needsOcr,
            'table_text_cell_boundary_reviews' => $cellBoundaryReviews,
        ];
    }

    /**
     * Native boundary for tabled.inference.recognition::recognize_tables with supplied model output.
     *
     * @param list<list<array<string, mixed>>> $tableCells
     * @param list<bool> $needsOcr
     * @param list<array<string, mixed>> $suppliedTableResults
     * @param array<int, list<string|array{text?: string, bbox?: list<int|float>, polygon?: list<list<int|float>>}>|array{text_lines?: list<string|array{text?: string, bbox?: list<int|float>, polygon?: list<list<int|float>>}>, lines?: list<string|array{text?: string, bbox?: list<int|float>, polygon?: list<list<int|float>>}>}> $suppliedOcrTextLines
     * @return list<array{cells: list<array<string, mixed>>, rows: list<array<string, mixed>>, cols: list<array<string, mixed>>, ocr_text_assignment?: string, ocr_grid_border_conflicts?: list<array<string, mixed>>}>
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
            $ocrApplication = [
                'assignment_mode' => 'none',
                'grid_border_conflicts' => [],
            ];
            if ($needsOcr[$idx]) {
                if (!array_key_exists($idx, $suppliedOcrTextLines)) {
                    throw new InvalidArgumentException('Missing supplied OCR text lines for table index ' . $idx . '.');
                }
                $ocrApplication = $this->applyOcrText($cells, $suppliedOcrTextLines[$idx]);
                $cells = $ocrApplication['cells'];
            }

            $result = $suppliedTableResults[$idx];
            $resultCells = isset($result['cells']) && is_array($result['cells'])
                ? $this->mergeRecognizedCellText($this->normalizeCells(array_values($result['cells'])), $cells)
                : $cells;

            $recognizedTable = [
                'cells' => $resultCells,
                'rows' => $this->normalizeRowsOrCols($result['rows'] ?? [], 'row_id'),
                'cols' => $this->normalizeRowsOrCols($result['cols'] ?? [], 'col_id'),
            ];
            if ($ocrApplication['grid_border_conflicts'] !== []) {
                $recognizedTable['ocr_text_assignment'] = $ocrApplication['assignment_mode'];
                $recognizedTable['ocr_grid_border_conflicts'] = $ocrApplication['grid_border_conflicts'];
            }

            $recognized[] = $recognizedTable;
        }

        return $recognized;
    }

    /**
     * @param list<array<string, mixed>> $resultCells
     * @param list<array<string, mixed>> $sourceCells
     * @return list<array<string, mixed>>
     */
    private function mergeRecognizedCellText(array $resultCells, array $sourceCells): array
    {
        if ($sourceCells === []) {
            return $resultCells;
        }

        $usedSourceIndexes = [];
        foreach ($resultCells as $cellIndex => &$resultCell) {
            if (trim((string) ($resultCell['text'] ?? '')) !== '') {
                continue;
            }

            $sourceIndexes = $this->sourceCellIndexesForResultCell($resultCell, $sourceCells, $usedSourceIndexes);
            if ($sourceIndexes === [] && isset($sourceCells[$cellIndex]) && !isset($usedSourceIndexes[$cellIndex])) {
                $sourceText = trim((string) ($sourceCells[$cellIndex]['text'] ?? ''));
                if ($sourceText !== '') {
                    $sourceIndexes = [$cellIndex];
                }
            }

            if ($sourceIndexes === []) {
                continue;
            }

            $text = $this->combinedSourceCellText($sourceCells, $sourceIndexes);
            if ($text === '') {
                continue;
            }

            $resultCell['text'] = $text;
            foreach ($sourceIndexes as $sourceIndex) {
                $usedSourceIndexes[$sourceIndex] = true;
            }
        }
        unset($resultCell);

        return $resultCells;
    }

    /**
     * @param array<string, mixed> $resultCell
     * @param list<array<string, mixed>> $sourceCells
     * @param array<int, true> $usedSourceIndexes
     * @return list<int>
     */
    private function sourceCellIndexesForResultCell(array $resultCell, array $sourceCells, array $usedSourceIndexes): array
    {
        $resultBbox = $this->nullableBbox($resultCell['bbox'] ?? null);
        if ($resultBbox === null) {
            return [];
        }

        $indexes = [];
        foreach ($sourceCells as $sourceIndex => $sourceCell) {
            if (isset($usedSourceIndexes[$sourceIndex]) || trim((string) ($sourceCell['text'] ?? '')) === '') {
                continue;
            }

            $sourceBbox = $this->nullableBbox($sourceCell['bbox'] ?? null);
            if ($sourceBbox === null) {
                continue;
            }
            if ($this->sourceCellMatchesResultCell($sourceBbox, $resultBbox)) {
                $indexes[] = $sourceIndex;
            }
        }

        return $indexes;
    }

    /**
     * @param list<float> $sourceBbox
     * @param list<float> $resultBbox
     */
    private function sourceCellMatchesResultCell(array $sourceBbox, array $resultBbox, float $threshold = 0.5): bool
    {
        return $this->intersectionPct($sourceBbox, $resultBbox) >= $threshold
            || $this->intersectionPct($resultBbox, $sourceBbox) >= $threshold
            || $this->bboxCenterInside($sourceBbox, $resultBbox)
            || $this->bboxCenterInside($resultBbox, $sourceBbox);
    }

    /**
     * @param list<array<string, mixed>> $sourceCells
     * @param list<int> $sourceIndexes
     */
    private function combinedSourceCellText(array $sourceCells, array $sourceIndexes): string
    {
        $selected = [];
        foreach ($sourceIndexes as $sourceIndex) {
            if (isset($sourceCells[$sourceIndex])) {
                $selected[] = $sourceCells[$sourceIndex];
            }
        }

        $parts = [];
        foreach ($this->sortTextCells($selected) as $sourceCell) {
            $text = $this->normalizeOcrFragmentText((string) ($sourceCell['text'] ?? ''));
            if ($text !== '') {
                $parts[] = $text;
            }
        }

        return implode(' ', $parts);
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
     * @param array{width?: int|float, height?: int|float}|list<int|float>|null $imageSize Optional table crop image size for boundary clipping.
     * @return list<array<string, mixed>>
     */
    public function mergedCellGeometry(array $cells, array $rows = [], array $cols = [], ?array $imageSize = null): array
    {
        $cells = $this->sortCells($this->normalizeAssignedCells($cells));
        $rows = $this->normalizeRowsOrCols($rows, 'row_id');
        $cols = $this->normalizeRowsOrCols($cols, 'col_id');
        $geometryBands = $this->tableGridGeometryBoundary($rows, $cols, $imageSize);
        $rows = $geometryBands['rows'];
        $cols = $geometryBands['cols'];
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
     * Review metadata for rendering tabled's first-row headers and spans.
     *
     * The upstream Markdown/HTML formatters call tabulate with
     * headers="firstrow", but they still consume only each cell's first
     * row/column id. This review grid keeps the assigned spans explicit so a
     * WordPress importer can render anchor cells as th/td and skip cells
     * covered by rowspan/colspan.
     *
     * @param list<array<string, mixed>> $cells Assigned cells from assignRowsColumns().
     * @param list<array<string, mixed>> $rows Optional model row bands in table-image coordinates.
     * @param list<array<string, mixed>> $cols Optional model column bands in table-image coordinates.
     * @param array{width?: int|float, height?: int|float}|list<int|float>|null $imageSize Optional table crop image size for boundary clipping.
     * @return array{rows: list<int>, cols: list<int>, column_header_rows?: list<int>, rotated: bool, orientation: string, row_axis: string, col_axis: string, render_cells: list<array<string, mixed>>, grid_cells: list<array<string, mixed>>, header_cells: list<array<string, mixed>>, data_cells: list<array<string, mixed>>, accessibility_grid: array<string, mixed>, geometry_boundary_review?: array<string, mixed>}
     */
    public function spanningGridReview(array $cells, array $rows = [], array $cols = [], ?array $imageSize = null): array
    {
        $cells = $this->sortCells($this->normalizeAssignedCells($cells));
        if ($cells === []) {
            return $this->emptySpanningGridReview();
        }

        $rows = $this->normalizeRowsOrCols($rows, 'row_id');
        $cols = $this->normalizeRowsOrCols($cols, 'col_id');
        $geometryBands = $this->tableGridGeometryBoundary($rows, $cols, $imageSize);
        $rows = $geometryBands['rows'];
        $cols = $geometryBands['cols'];
        $geometryBoundaryReview = $geometryBands['review'];
        $rowBboxes = $this->bboxesById($rows, 'row_id');
        $colBboxes = $this->bboxesById($cols, 'col_id');
        $rotated = $rows !== [] && $cols !== [] && $this->isRotated($rows, $cols);
        $axisMetadata = $this->spanningGridAxisMetadata($rotated);

        $cellGroups = $this->spanningGridAnchorGroups($cells);
        $rowIds = [];
        $colIds = [];
        foreach ($cellGroups as $cellGroup) {
            foreach ($cellGroup['row_ids'] as $rowId) {
                $rowIds[$rowId] = $rowId;
            }
            foreach ($cellGroup['col_ids'] as $colId) {
                $colIds[$colId] = $colId;
            }
        }
        sort($rowIds, SORT_NUMERIC);
        sort($colIds, SORT_NUMERIC);

        if ($rowIds === [] || $colIds === []) {
            return $this->emptySpanningGridReview($rotated);
        }

        $topRowId = $rowIds[0];
        $leftColId = $colIds[0];
        $columnHeaderRowIds = $this->columnHeaderRowIdsForGrid($cellGroups, $rowIds, $topRowId);
        $renderCells = [];
        $anchors = [];
        $covered = [];

        foreach ($cellGroups as $cellGroup) {
            $cellRowIds = $cellGroup['row_ids'];
            $cellColIds = $cellGroup['col_ids'];
            if ($cellRowIds === [] || $cellColIds === []) {
                continue;
            }

            $scope = $this->headerScopeForGridCell($cellRowIds, $cellColIds, $columnHeaderRowIds, $leftColId);
            $headerAxes = $this->headerAxesForGridCell($cellRowIds, $cellColIds, $columnHeaderRowIds, $leftColId);
            $headerAxis = $this->headerAxisForAxes($headerAxes);
            $anchor = [
                'row_id' => $cellRowIds[0],
                'col_id' => $cellColIds[0],
            ];
            $renderIndex = count($renderCells);
            $entry = [
                'text' => $cellGroup['text'],
                'row_ids' => $cellRowIds,
                'col_ids' => $cellColIds,
                'rowspan' => count($cellRowIds),
                'colspan' => count($cellColIds),
                'anchor' => $anchor,
                'grid_cells' => $this->gridCellsForSpan($cellRowIds, $cellColIds),
                'cell_bbox' => $cellGroup['bbox'],
                'tag' => $scope === null ? 'td' : 'th',
                'scope' => $scope,
                'header' => $scope !== null,
                'header_role' => $this->headerRoleForScope($scope),
                'header_axis' => $headerAxis,
                'header_axes' => $headerAxes,
                'rotated' => $rotated,
                'orientation' => $axisMetadata['orientation'],
                'row_axis' => $axisMetadata['row_axis'],
                'col_axis' => $axisMetadata['col_axis'],
            ];

            if (count($cellGroup['cells']) > 1) {
                $entry['source_cell_count'] = count($cellGroup['cells']);
                $entry['text_parts'] = $this->reviewTextParts($cellGroup['cells']);
                $entry['anchor_cell_bbox'] = $cellGroup['cells'][0]['bbox'];
                $entry['continuation_count'] = count($cellGroup['cells']) - 1;
                $entry['continuation_cells'] = $this->reviewContinuationCells(array_slice($cellGroup['cells'], 1));
            }

            $gridBbox = $this->gridBboxForSpan($cellRowIds, $cellColIds, $rowBboxes, $colBboxes, $rotated);
            if ($gridBbox !== null) {
                $entry['grid_bbox'] = $gridBbox;
                $entry['grid_cell_bboxes'] = $this->gridCellBboxesForSpan($cellRowIds, $cellColIds, $rowBboxes, $colBboxes, $rotated);
            }

            $anchorKey = $anchor['row_id'] . ':' . $anchor['col_id'];
            $anchors[$anchorKey] = $renderIndex;
            foreach ($entry['grid_cells'] as $gridCell) {
                $key = $gridCell['row_id'] . ':' . $gridCell['col_id'];
                if ($key !== $anchorKey) {
                    $covered[$key] = [
                        'row_id' => $anchor['row_id'],
                        'col_id' => $anchor['col_id'],
                        'render_cell_index' => $renderIndex,
                    ];
                }
            }

            $renderCells[] = $entry;
        }

        $headerReferences = $this->applyHeaderReferences($renderCells);
        $renderCells = $headerReferences['render_cells'];
        $accessibilityGrid = $this->accessibilityGridReview(
            $rowIds,
            $colIds,
            $axisMetadata,
            $headerReferences['header_cells'],
            $headerReferences['data_cells']
        );
        $gridCells = [];
        foreach ($rowIds as $rowId) {
            foreach ($colIds as $colId) {
                $key = $rowId . ':' . $colId;
                $cell = [
                    'row_id' => $rowId,
                    'col_id' => $colId,
                ];
                $gridBbox = $this->gridBboxForSpan([$rowId], [$colId], $rowBboxes, $colBboxes, $rotated);
                if ($gridBbox !== null) {
                    $cell['grid_bbox'] = $gridBbox;
                }
                if (isset($anchors[$key])) {
                    $renderCell = $renderCells[$anchors[$key]];
                    $cell += [
                        'state' => 'anchor',
                        'render_cell_index' => $anchors[$key],
                        'text' => $renderCell['text'],
                        'tag' => $renderCell['tag'],
                        'scope' => $renderCell['scope'],
                        'header' => $renderCell['header'],
                        'header_role' => $renderCell['header_role'],
                        'header_axis' => $renderCell['header_axis'],
                        'header_axes' => $renderCell['header_axes'],
                        'rowspan' => $renderCell['rowspan'],
                        'colspan' => $renderCell['colspan'],
                    ];
                    foreach (['header_id', 'headers', 'column_header_ids', 'row_header_ids', 'header_texts', 'header_text', 'column_header_physical_axis', 'row_header_physical_axis'] as $field) {
                        if (array_key_exists($field, $renderCell)) {
                            $cell[$field] = $renderCell[$field];
                        }
                    }
                } elseif (isset($covered[$key])) {
                    $cell += [
                        'state' => 'covered',
                        'covered_by' => $covered[$key],
                    ];
                } else {
                    $cell['state'] = 'empty';
                }
                $gridCells[] = $cell;
            }
        }

        $review = [
            'rows' => $rowIds,
            'cols' => $colIds,
            'column_header_rows' => $columnHeaderRowIds,
            'rotated' => $rotated,
            'orientation' => $axisMetadata['orientation'],
            'row_axis' => $axisMetadata['row_axis'],
            'col_axis' => $axisMetadata['col_axis'],
            'render_cells' => $renderCells,
            'grid_cells' => $gridCells,
            'header_cells' => $headerReferences['header_cells'],
            'data_cells' => $headerReferences['data_cells'],
            'accessibility_grid' => $accessibilityGrid,
        ];
        if ($geometryBoundaryReview !== null) {
            $review['geometry_boundary_review'] = $geometryBoundaryReview;
        }

        return $review;
    }

    /**
     * Review metadata for OCR lines whose bboxes cross detector grid borders.
     *
     * Upstream tabled zips OCR text back onto the detector-cell list before
     * assign_rows_columns(), so the assigned SpanTableCell row/column ids are
     * the stable grid coordinates a WordPress importer can use for review UI.
     *
     * @param list<array<string, mixed>> $conflicts Rows emitted by ocrGridBorderConflicts().
     * @param list<array<string, mixed>> $assignedCells Assigned cells from assignRowsColumns().
     * @param list<array<string, mixed>> $rows Optional model row bands in table-image coordinates.
     * @param list<array<string, mixed>> $cols Optional model column bands in table-image coordinates.
     * @param array{width?: int|float, height?: int|float}|list<int|float>|null $imageSize Optional table crop image size for boundary clipping.
     * @return list<array<string, mixed>>
     */
    public function gridBorderConflictReview(array $conflicts, array $assignedCells, array $rows = [], array $cols = [], ?array $imageSize = null): array
    {
        $assignedCells = $this->normalizeAssignedCells($assignedCells);
        $rows = $this->normalizeRowsOrCols($rows, 'row_id');
        $cols = $this->normalizeRowsOrCols($cols, 'col_id');
        $geometryBands = $this->tableGridGeometryBoundary($rows, $cols, $imageSize);
        $rows = $geometryBands['rows'];
        $cols = $geometryBands['cols'];
        $rowBboxes = $this->bboxesById($rows, 'row_id');
        $colBboxes = $this->bboxesById($cols, 'col_id');
        $rotated = $rows !== [] && $cols !== [] && $this->isRotated($rows, $cols);
        $spanningReview = $this->spanningGridReview($assignedCells, $rows, $cols);
        $gridCellsByPosition = $this->gridReviewCellsByPosition($spanningReview['grid_cells'] ?? []);
        $renderCells = $spanningReview['render_cells'] ?? [];
        $review = [];

        foreach ($conflicts as $conflict) {
            if (!is_array($conflict)) {
                continue;
            }

            $entry = $conflict;
            $candidateIndexes = $this->integerList($conflict['candidate_cell_indexes'] ?? []);
            $candidateGridCells = [];
            $candidateGridRenderCells = [];
            $candidateAnchors = [];
            foreach ($candidateIndexes as $cellIndex) {
                if (!isset($assignedCells[$cellIndex])) {
                    continue;
                }

                $cell = $assignedCells[$cellIndex];
                $rowIds = $this->nonNullSortedIds($cell['row_ids']);
                $colIds = $this->nonNullSortedIds($cell['col_ids']);
                if ($rowIds === [] || $colIds === []) {
                    continue;
                }

                $anchor = [
                    'cell_index' => $cellIndex,
                    'row_id' => $rowIds[0],
                    'col_id' => $colIds[0],
                ];
                $candidateAnchors[] = $anchor;
                $candidateGridCell = [
                    'cell_index' => $cellIndex,
                    'row_ids' => $rowIds,
                    'col_ids' => $colIds,
                    'anchor' => [
                        'row_id' => $rowIds[0],
                        'col_id' => $colIds[0],
                    ],
                    'text' => (string) $cell['text'],
                    'bbox' => $cell['bbox'],
                ];
                $gridBbox = $this->gridBboxForSpan($rowIds, $colIds, $rowBboxes, $colBboxes, $rotated);
                if ($gridBbox !== null) {
                    $candidateGridCell['grid_bbox'] = $gridBbox;
                    $candidateGridCell['grid_cell_bboxes'] = $this->gridCellBboxesForSpan($rowIds, $colIds, $rowBboxes, $colBboxes, $rotated);
                }
                $candidateGridCells[] = $candidateGridCell;
                $candidateGridRenderCell = $this->assignedCellGridRenderReview($cellIndex, $cell, $gridCellsByPosition, $renderCells);
                if ($candidateGridRenderCell !== null) {
                    $candidateGridRenderCells[] = $candidateGridRenderCell;
                }
            }

            if ($candidateGridCells !== []) {
                $entry['candidate_grid_cells'] = $candidateGridCells;
                $entry['candidate_grid_anchors'] = $candidateAnchors;
                $entry['candidate_row_ids'] = $this->uniqueGridIds($candidateGridCells, 'row_ids');
                $entry['candidate_col_ids'] = $this->uniqueGridIds($candidateGridCells, 'col_ids');
                $entry['grid_border_axes'] = $this->gridBorderAxes($entry['candidate_row_ids'], $entry['candidate_col_ids']);
                $entry['grid_border_axis'] = $this->headerAxisForAxes($entry['grid_border_axes']);
            }
            if ($candidateGridRenderCells !== []) {
                $entry['candidate_grid_render_cells'] = $candidateGridRenderCells;
            }

            $assignedIndex = $this->nullableInteger($conflict['assigned_cell_index'] ?? null);
            if ($assignedIndex !== null && isset($assignedCells[$assignedIndex])) {
                $cell = $assignedCells[$assignedIndex];
                $rowIds = $this->nonNullSortedIds($cell['row_ids']);
                $colIds = $this->nonNullSortedIds($cell['col_ids']);
                if ($rowIds !== [] && $colIds !== []) {
                    $assignedGridCell = [
                        'cell_index' => $assignedIndex,
                        'row_id' => $rowIds[0],
                        'col_id' => $colIds[0],
                        'row_ids' => $rowIds,
                        'col_ids' => $colIds,
                        'text' => (string) $cell['text'],
                    ];
                    $gridBbox = $this->gridBboxForSpan($rowIds, $colIds, $rowBboxes, $colBboxes, $rotated);
                    if ($gridBbox !== null) {
                        $assignedGridCell['grid_bbox'] = $gridBbox;
                        $assignedGridCell['grid_cell_bboxes'] = $this->gridCellBboxesForSpan($rowIds, $colIds, $rowBboxes, $colBboxes, $rotated);
                    }
                    $entry['assigned_grid_cell'] = $assignedGridCell;
                }

                $assignedGridRenderCell = $this->assignedCellGridRenderReview($assignedIndex, $cell, $gridCellsByPosition, $renderCells);
                if ($assignedGridRenderCell !== null) {
                    $entry['assigned_grid_render_cell'] = $assignedGridRenderCell;
                }
            }

            $review[] = $entry;
        }

        return $review;
    }

    /**
     * @param list<array<string, mixed>> $gridCells
     * @return array<string, array<string, mixed>>
     */
    private function gridReviewCellsByPosition(array $gridCells): array
    {
        $byPosition = [];
        foreach ($gridCells as $gridCell) {
            if (!is_array($gridCell)) {
                continue;
            }

            $rowId = $this->nullableInteger($gridCell['row_id'] ?? null);
            $colId = $this->nullableInteger($gridCell['col_id'] ?? null);
            if ($rowId === null || $colId === null) {
                continue;
            }

            $byPosition[$rowId . ':' . $colId] = $gridCell;
        }

        return $byPosition;
    }

    /**
     * @param array{bbox: list<float>, text: string, row_ids: list<int|null>, col_ids: list<int|null>, order?: int} $cell
     * @param array<string, array<string, mixed>> $gridCellsByPosition
     * @param list<array<string, mixed>> $renderCells
     * @return array<string, mixed>|null
     */
    private function assignedCellGridRenderReview(int $cellIndex, array $cell, array $gridCellsByPosition, array $renderCells): ?array
    {
        $rowIds = $this->nonNullSortedIds($cell['row_ids']);
        $colIds = $this->nonNullSortedIds($cell['col_ids']);
        if ($rowIds === [] || $colIds === []) {
            return null;
        }

        $gridCellReviews = [];
        $renderIndexes = [];
        foreach ($this->gridCellsForSpan($rowIds, $colIds) as $gridPosition) {
            $key = $gridPosition['row_id'] . ':' . $gridPosition['col_id'];
            $gridCell = $gridCellsByPosition[$key] ?? null;
            if ($gridCell === null) {
                continue;
            }

            $renderIndex = $this->gridReviewRenderIndex($gridCell);
            if ($renderIndex !== null && !in_array($renderIndex, $renderIndexes, true)) {
                $renderIndexes[] = $renderIndex;
            }

            $gridCellReviews[] = $this->gridCellReviewSummary($gridCell, $renderIndex);
        }

        if ($gridCellReviews === []) {
            return null;
        }

        $review = [
            'cell_index' => $cellIndex,
            'row_ids' => $rowIds,
            'col_ids' => $colIds,
            'anchor' => [
                'row_id' => $rowIds[0],
                'col_id' => $colIds[0],
            ],
            'grid_cells' => $gridCellReviews,
            'render_cell_indexes' => $renderIndexes,
        ];

        if (count($renderIndexes) === 1) {
            $renderIndex = $renderIndexes[0];
            $review['render_cell_index'] = $renderIndex;
            if (isset($renderCells[$renderIndex])) {
                $review['render_cell'] = $this->renderCellReviewSummary($renderCells[$renderIndex]);
            }
        } elseif ($renderIndexes !== []) {
            $review['render_cells'] = [];
            foreach ($renderIndexes as $renderIndex) {
                if (isset($renderCells[$renderIndex])) {
                    $review['render_cells'][] = $this->renderCellReviewSummary($renderCells[$renderIndex]);
                }
            }
        }

        return $review;
    }

    /**
     * @param array<string, mixed> $gridCell
     * @return array<string, mixed>
     */
    private function gridCellReviewSummary(array $gridCell, ?int $renderIndex): array
    {
        $summary = [
            'row_id' => (int) ($gridCell['row_id'] ?? 0),
            'col_id' => (int) ($gridCell['col_id'] ?? 0),
            'state' => (string) ($gridCell['state'] ?? 'unknown'),
        ];
        if ($renderIndex !== null) {
            $summary['render_cell_index'] = $renderIndex;
        }

        foreach ([
            'grid_bbox',
            'text',
            'tag',
            'scope',
            'header',
            'header_role',
            'header_axis',
            'header_axes',
            'rowspan',
            'colspan',
            'header_id',
            'headers',
            'column_header_ids',
            'row_header_ids',
            'header_texts',
            'header_text',
            'column_header_physical_axis',
            'row_header_physical_axis',
            'covered_by',
        ] as $field) {
            if (array_key_exists($field, $gridCell)) {
                $summary[$field] = $gridCell[$field];
            }
        }

        return $summary;
    }

    /**
     * @param array<string, mixed> $gridCell
     */
    private function gridReviewRenderIndex(array $gridCell): ?int
    {
        $directIndex = $this->nullableInteger($gridCell['render_cell_index'] ?? null);
        if ($directIndex !== null) {
            return $directIndex;
        }

        $coveredBy = $gridCell['covered_by'] ?? null;
        if (is_array($coveredBy)) {
            return $this->nullableInteger($coveredBy['render_cell_index'] ?? null);
        }

        return null;
    }

    /**
     * @param array<string, mixed> $renderCell
     * @return array<string, mixed>
     */
    private function renderCellReviewSummary(array $renderCell): array
    {
        $summary = [];
        foreach ([
            'text',
            'row_ids',
            'col_ids',
            'anchor',
            'grid_cells',
            'cell_bbox',
            'grid_bbox',
            'grid_cell_bboxes',
            'tag',
            'scope',
            'header',
            'header_role',
            'header_axis',
            'header_axes',
            'rotated',
            'orientation',
            'row_axis',
            'col_axis',
            'rowspan',
            'colspan',
            'source_cell_count',
            'text_parts',
            'anchor_cell_bbox',
            'continuation_count',
            'continuation_cells',
            'header_id',
            'headers',
            'column_header_ids',
            'row_header_ids',
            'header_texts',
            'header_text',
            'column_header_physical_axis',
            'row_header_physical_axis',
        ] as $field) {
            if (array_key_exists($field, $renderCell)) {
                $summary[$field] = $renderCell[$field];
            }
        }

        return $summary;
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
     * @param list<string|array{text?: string, bbox?: list<int|float>, polygon?: list<list<int|float>>}> $ocrTextLines
     * @return array{cells: list<array<string, mixed>>, assignment_mode: string, grid_border_conflicts: list<array<string, mixed>>}
     */
    private function applyOcrText(array $cells, array $ocrTextLines): array
    {
        $ocrLines = $this->ocrTextLineItems($ocrTextLines);
        if ($this->ocrTextLinesHaveBboxes($ocrLines)) {
            $borderConflicts = $this->ocrGridBorderConflicts($cells, $ocrLines);
            if ($borderConflicts !== [] && count($ocrLines) === count($cells)) {
                foreach ($borderConflicts as &$conflict) {
                    $conflict['assignment_mode'] = 'source_order_grid_border';
                    $conflict['assigned_cell_index'] = $conflict['ocr_index'];
                }
                unset($conflict);

                return [
                    'cells' => $this->applyOcrTextByOrder($cells, $ocrLines),
                    'assignment_mode' => 'source_order_grid_border',
                    'grid_border_conflicts' => $borderConflicts,
                ];
            }

            return [
                'cells' => $this->applyOcrTextByBbox($cells, $ocrLines),
                'assignment_mode' => 'bbox',
                'grid_border_conflicts' => $borderConflicts,
            ];
        }

        return [
            'cells' => $this->applyOcrTextByOrder($cells, $ocrLines),
            'assignment_mode' => 'source_order',
            'grid_border_conflicts' => [],
        ];
    }

    /**
     * @param list<array<string, mixed>> $cells
     * @param list<string|array{text?: string, bbox?: list<int|float>, polygon?: list<list<int|float>>}> $ocrLines
     * @return list<array<string, mixed>>
     */
    private function applyOcrTextByOrder(array $cells, array $ocrLines): array
    {
        foreach ($ocrLines as $idx => $ocrLine) {
            if (!isset($cells[$idx])) {
                break;
            }
            $cells[$idx]['text'] = $this->ocrTextLineText($ocrLine);
        }

        return $cells;
    }

    /**
     * @param list<array<string, mixed>> $cells
     * @param list<string|array{text?: string, bbox?: list<int|float>, polygon?: list<list<int|float>>}> $ocrTextLines
     * @return list<array<string, mixed>>
     */
    private function ocrGridBorderConflicts(array $cells, array $ocrTextLines, float $minOverlap = 0.15): array
    {
        $conflicts = [];
        foreach ($ocrTextLines as $ocrIndex => $ocrLine) {
            if (!is_array($ocrLine)) {
                continue;
            }

            $bbox = $this->ocrLineBbox($ocrLine);
            if ($bbox === null) {
                continue;
            }

            $candidates = [];
            foreach ($cells as $cellIndex => $cell) {
                $cellBbox = $this->nullableBbox($cell['bbox'] ?? null);
                if ($cellBbox === null) {
                    continue;
                }

                $overlap = $this->intersectionPct($bbox, $cellBbox);
                if ($overlap >= $minOverlap) {
                    $candidates[] = [
                        'cell_index' => $cellIndex,
                        'overlap' => round($overlap, 4),
                        'cell_bbox' => $cellBbox,
                    ];
                }
            }

            if (count($candidates) <= 1) {
                continue;
            }

            $conflicts[] = [
                'ocr_index' => $ocrIndex,
                'text' => $this->normalizeOcrFragmentText($this->ocrTextLineText($ocrLine)),
                'bbox' => $bbox,
                'spans_grid_border' => true,
                'candidate_cell_indexes' => array_column($candidates, 'cell_index'),
                'candidate_overlaps' => array_column($candidates, 'overlap'),
                'candidate_cell_bboxes' => array_column($candidates, 'cell_bbox'),
            ];
        }

        return $conflicts;
    }

    /**
     * @param list<string|array{text?: string, bbox?: list<int|float>, polygon?: list<list<int|float>>}> $ocrTextLines
     * @return list<array<string, mixed>>
     */
    private function applyOcrTextByBbox(array $cells, array $ocrTextLines): array
    {
        $fragmentsByCell = array_fill(0, count($cells), []);
        foreach ($ocrTextLines as $order => $ocrLine) {
            if (!is_array($ocrLine)) {
                continue;
            }

            $bbox = $this->ocrLineBbox($ocrLine);
            if ($bbox === null) {
                continue;
            }

            $cellIndex = $this->cellIndexForOcrLine($cells, $bbox);
            if ($cellIndex === null) {
                continue;
            }

            $fragmentsByCell[$cellIndex][] = [
                'bbox' => $bbox,
                'text' => $this->ocrTextLineText($ocrLine),
                'order' => $order,
            ];
        }

        foreach ($fragmentsByCell as $cellIndex => $fragments) {
            if ($fragments === []) {
                continue;
            }

            $fragments = $this->sortTextCells($fragments);
            $parts = [];
            foreach ($fragments as $fragment) {
                $text = $this->normalizeOcrFragmentText((string) $fragment['text']);
                if ($text !== '') {
                    $parts[] = $text;
                }
            }

            $cells[$cellIndex]['text'] = implode(' ', $parts);
        }

        return $cells;
    }

    /**
     * @param list<array<string, mixed>> $cells
     * @param list<float> $bbox
     */
    private function cellIndexForOcrLine(array $cells, array $bbox, float $threshold = 0.5): ?int
    {
        $bestIndex = null;
        $bestOverlap = 0.0;
        foreach ($cells as $idx => $cell) {
            $overlap = $this->intersectionPct($bbox, $cell['bbox']);
            if ($overlap > $bestOverlap) {
                $bestOverlap = $overlap;
                $bestIndex = $idx;
            }
        }

        if ($bestIndex !== null && $bestOverlap >= $threshold) {
            return $bestIndex;
        }

        foreach ($cells as $idx => $cell) {
            if ($this->bboxCenterInside($bbox, $cell['bbox'])) {
                return $idx;
            }
        }

        return null;
    }

    /**
     * @param list<float> $bbox
     * @param list<float> $cellBbox
     */
    private function bboxCenterInside(array $bbox, array $cellBbox): bool
    {
        $centerX = ($bbox[0] + $bbox[2]) / 2.0;
        $centerY = ($bbox[1] + $bbox[3]) / 2.0;

        return $centerX >= $cellBbox[0]
            && $centerX <= $cellBbox[2]
            && $centerY >= $cellBbox[1]
            && $centerY <= $cellBbox[3];
    }

    /**
     * @param list<string|array{text?: string, bbox?: list<int|float>, polygon?: list<list<int|float>>}> $ocrTextLines
     */
    private function ocrTextLinesHaveBboxes(array $ocrTextLines): bool
    {
        if ($ocrTextLines === []) {
            return false;
        }

        foreach ($ocrTextLines as $ocrLine) {
            if (!is_array($ocrLine) || $this->ocrLineBbox($ocrLine) === null) {
                return false;
            }
        }

        return true;
    }

    private function ocrTextLineText(mixed $ocrLine): string
    {
        return is_array($ocrLine) ? (string) ($ocrLine['text'] ?? '') : (string) $ocrLine;
    }

    private function normalizeOcrFragmentText(string $text): string
    {
        return trim(preg_replace('/[\r\n]+/', ' ', $text) ?? $text);
    }

    /**
     * @param list<string|array{text?: string, bbox?: list<int|float>, polygon?: list<list<int|float>>}>|array{text_lines?: list<string|array{text?: string, bbox?: list<int|float>, polygon?: list<list<int|float>>}>, lines?: list<string|array{text?: string, bbox?: list<int|float>, polygon?: list<list<int|float>>}>} $ocrTextLines
     * @return list<string|array{text?: string, bbox?: list<int|float>, polygon?: list<list<int|float>>}>
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
     * @return array{cells: list<array<string, mixed>>, boundary_review: array<string, mixed>|null}
     */
    private function tableBlocksFromTextLine(mixed $textLine, array $tableBbox, array $imageSize): array
    {
        if (!is_array($textLine)) {
            return [
                'cells' => [],
                'boundary_review' => null,
            ];
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

        return [
            'cells' => [],
            'boundary_review' => null,
        ];
    }

    /**
     * @param list<array<string, mixed>> $blocks
     * @param list<float> $tableBbox
     * @param array{width: int, height: int} $imageSize
     * @return array{cells: list<array<string, mixed>>, boundary_review: array<string, mixed>|null}
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
            $cells = $this->pdfTextCellsForTable($blocks, $tableBbox, $imageSize, $rotation);

            return [
                'cells' => $cells,
                'boundary_review' => $this->tableTextCellBoundaryReview($cells, $tableBbox, 'pdftext_dictionary_lines'),
            ];
        }

        $filtered = [];
        foreach ($this->normalizeCells($blocks) as $block) {
            if ($this->intersectionPct($block['bbox'], $tableBbox) >= 0.8) {
                $block['bbox'] = $this->relativeToTableBbox($block['bbox'], $tableBbox);
                $filtered[] = $block;
            }
        }

        $filtered = $this->sortTextCells($filtered);

        return [
            'cells' => $filtered,
            'boundary_review' => $this->tableTextCellBoundaryReview($filtered, $tableBbox, 'supplied_table_blocks'),
        ];
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

        $named = $this->bboxFromNamedFields($bbox);
        if ($named !== null) {
            return $named;
        }

        $values = array_values($bbox);
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

    /**
     * @param array<string, mixed> $ocrLine
     * @return list<float>|null
     */
    private function ocrLineBbox(array $ocrLine): ?array
    {
        return $this->bboxFromValue($ocrLine['bbox'] ?? null)
            ?? $this->bboxFromNamedFields($ocrLine)
            ?? $this->polygonBbox($ocrLine['polygon'] ?? null);
    }

    /**
     * Upstream Surya TextLine objects carry four-corner polygons and expose
     * bbox as a derived property. Mirror that derivation at the supplied-input
     * boundary so OCR geometry survives array serialization.
     *
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
            $values = array_values($point);
            $x = $this->numericScalar($values[0]);
            $y = $this->numericScalar($values[1]);
            if ($x === null || $y === null) {
                return null;
            }
            $xs[] = $x;
            $ys[] = $y;
        }

        return [
            min($xs),
            min($ys),
            max($xs),
            max($ys),
        ];
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
     * Upstream returns pdftext-derived cell bboxes relative to the input table
     * crop. Preserve those bboxes for recognition while surfacing a bounded
     * WordPress review row when a cell crosses the crop image boundary.
     *
     * @param list<array<string, mixed>> $cells
     * @param list<float> $tableBbox
     * @return array<string, mixed>|null
     */
    private function tableTextCellBoundaryReview(array $cells, array $tableBbox, string $source): ?array
    {
        if ($cells === []) {
            return null;
        }

        $cropSize = [
            'width' => (int) round(max(0.0, $tableBbox[2] - $tableBbox[0])),
            'height' => (int) round(max(0.0, $tableBbox[3] - $tableBbox[1])),
        ];
        if ($cropSize['width'] <= 0 || $cropSize['height'] <= 0) {
            return null;
        }

        $imageSize = $this->imageSize($cropSize);
        $rows = [];
        $clippedCount = 0;
        $outsideCount = 0;

        foreach ($cells as $index => $cell) {
            $bbox = $this->nullableBbox($cell['bbox'] ?? null);
            if ($bbox === null) {
                continue;
            }

            $clipped = $this->clipBboxToImage($bbox, $imageSize);
            $originalPositive = $this->positiveArea($bbox) > 0.0;
            $clippedPositive = $this->positiveArea($clipped) > 0.0;
            $status = 'within_table_image';
            if (!$originalPositive) {
                $status = 'excluded_non_positive_area';
                $outsideCount++;
            } elseif (!$clippedPositive) {
                $status = 'outside_table_image';
                $outsideCount++;
            } elseif ($clipped !== $bbox) {
                $status = 'clipped_to_table_image';
                $clippedCount++;
            }

            $row = [
                'cell_index' => $index,
                'text' => (string) ($cell['text'] ?? ''),
                'original_bbox' => $bbox,
                'bounded_bbox' => $clippedPositive ? $clipped : null,
                'clipped_bbox' => $clipped,
                'status' => $status,
                'active' => $originalPositive && $clippedPositive,
                'upstream_cell_bbox_retained' => true,
            ];
            $rows[] = $row;
        }

        if ($rows === []) {
            return null;
        }

        return [
            'review_target' => 'table_text_cell_geometry_boundary',
            'source' => $source,
            'upstream_boundary' => 'surya.input.pdflines.get_table_blocks',
            'table_bbox' => $tableBbox,
            'table_crop_size' => $imageSize,
            'cell_count' => count($rows),
            'within_cell_count' => count(array_filter(
                $rows,
                static fn (array $row): bool => ($row['status'] ?? null) === 'within_table_image'
            )),
            'clipped_cell_count' => $clippedCount,
            'outside_cell_count' => $outsideCount,
            'cells' => $rows,
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
                'bbox' => $this->bboxFromRecord($cell),
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
                'bbox' => $this->bboxFromRecord($cell),
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
            $bbox = $this->bboxFromRecord($item);
            $normalized[] = [
                $idField => (int) $id,
                'bbox' => $bbox,
                'coordinate_source' => $this->bboxCoordinateSourceFromRecord($item),
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
            $spanStarted = false;
            foreach ($cols as $col) {
                $colId = (int) $col['col_id'];
                if (in_array($colId, $cell['col_ids'], true)) {
                    $spanStarted = true;
                    continue;
                }

                $pct = $rotated ? $this->intersectionYPct($cell['bbox'], $col['bbox']) : $this->intersectionXPct($cell['bbox'], $col['bbox']);
                if ($pct > $thresh && !$this->hasOtherCellAt($cells, $cellIndex, (int) $cell['row_ids'][0], $colId)) {
                    $cell['col_ids'][] = $colId;
                    $spanStarted = true;
                    continue;
                }

                if ($spanStarted) {
                    break;
                }
            }
            sort($cell['col_ids']);
        }
        unset($cell);

        foreach ($cells as $cellIndex => &$cell) {
            $spanStarted = false;
            foreach ($rows as $row) {
                $rowId = (int) $row['row_id'];
                if (in_array($rowId, $cell['row_ids'], true)) {
                    $spanStarted = true;
                    continue;
                }

                $pct = $rotated ? $this->intersectionXPct($cell['bbox'], $row['bbox']) : $this->intersectionYPct($cell['bbox'], $row['bbox']);
                if ($pct > $thresh && !$this->hasOtherCellAt($cells, $cellIndex, $rowId, (int) $cell['col_ids'][0])) {
                    $cell['row_ids'][] = $rowId;
                    $spanStarted = true;
                    continue;
                }

                if ($spanStarted) {
                    break;
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
     * Upstream tabled rows/columns are table-crop-local Bbox objects. When a
     * supplied recognition bundle crosses that crop boundary, keep assignment
     * behavior stable but bound review geometry before WordPress renders table
     * inspector overlays.
     *
     * @param list<array<string, mixed>> $rows
     * @param list<array<string, mixed>> $cols
     * @param array{width?: int|float, height?: int|float}|list<int|float>|null $imageSize
     * @return array{rows: list<array<string, mixed>>, cols: list<array<string, mixed>>, review: array<string, mixed>|null}
     */
    private function tableGridGeometryBoundary(array $rows, array $cols, ?array $imageSize): array
    {
        if ($imageSize === null) {
            return [
                'rows' => $rows,
                'cols' => $cols,
                'review' => null,
            ];
        }

        $size = $this->imageSize($imageSize);
        $rowBoundary = $this->boundedGridBands($rows, 'row', 'row_id', $size);
        $colBoundary = $this->boundedGridBands($cols, 'column', 'col_id', $size);
        $reviewRows = array_merge($rowBoundary['review_rows'], $colBoundary['review_rows']);

        return [
            'rows' => $rowBoundary['bands'],
            'cols' => $colBoundary['bands'],
            'review' => [
                'review_target' => 'table_grid_geometry_boundary',
                'image_size' => $size,
                'row_band_count' => count($rows),
                'col_band_count' => count($cols),
                'active_row_band_count' => count($rowBoundary['bands']),
                'active_col_band_count' => count($colBoundary['bands']),
                'clipped_band_count' => count(array_filter(
                    $reviewRows,
                    static fn (array $row): bool => ($row['status'] ?? null) === 'clipped_to_table_image'
                )),
                'excluded_band_count' => count(array_filter(
                    $reviewRows,
                    static fn (array $row): bool => isset($row['status']) && str_starts_with((string) $row['status'], 'excluded_')
                )),
                'row_bands' => $rowBoundary['review_rows'],
                'col_bands' => $colBoundary['review_rows'],
            ],
        ];
    }

    /**
     * @param list<array<string, mixed>> $bands
     * @param array{width: int, height: int} $imageSize
     * @return array{bands: list<array<string, mixed>>, review_rows: list<array<string, mixed>>}
     */
    private function boundedGridBands(array $bands, string $axis, string $idField, array $imageSize): array
    {
        $bounded = [];
        $reviewRows = [];

        foreach ($bands as $band) {
            $id = (int) ($band[$idField] ?? count($reviewRows));
            $originalBbox = $band['bbox'];
            $clippedBbox = $this->clipBboxToImage($originalBbox, $imageSize);
            $originalPositive = $this->positiveArea($originalBbox) > 0.0;
            $clippedPositive = $this->positiveArea($clippedBbox) > 0.0;
            $active = $originalPositive && $clippedPositive;
            $status = 'within_table_image';

            if (!$originalPositive) {
                $status = 'excluded_non_positive_area';
            } elseif (!$clippedPositive) {
                $status = 'excluded_outside_table_image';
            } elseif ($clippedBbox !== $originalBbox) {
                $status = 'clipped_to_table_image';
            }

            $reviewRow = [
                'axis' => $axis,
                'id_field' => $idField,
                'id' => $id,
                'original_bbox' => $originalBbox,
                'bounded_bbox' => $active ? $clippedBbox : null,
                'clipped_bbox' => $clippedBbox,
                'status' => $status,
                'active' => $active,
            ];
            if (isset($band['coordinate_source']) && is_string($band['coordinate_source'])) {
                $reviewRow['coordinate_source'] = $band['coordinate_source'];
            }
            $reviewRows[] = $reviewRow;

            if (!$active) {
                continue;
            }

            $band['bbox'] = $clippedBbox;
            $band['width'] = $clippedBbox[2] - $clippedBbox[0];
            $band['height'] = $clippedBbox[3] - $clippedBbox[1];
            $band['area'] = $this->positiveArea($clippedBbox);
            $bounded[] = $band;
        }

        return [
            'bands' => $bounded,
            'review_rows' => $reviewRows,
        ];
    }

    /**
     * @param list<float> $bbox
     * @param array{width: int, height: int} $imageSize
     * @return list<float>
     */
    private function clipBboxToImage(array $bbox, array $imageSize): array
    {
        $width = (float) $imageSize['width'];
        $height = (float) $imageSize['height'];

        return [
            max(0.0, min($width, $bbox[0])),
            max(0.0, min($height, $bbox[1])),
            max(0.0, min($width, $bbox[2])),
            max(0.0, min($height, $bbox[3])),
        ];
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
     * @return list<int>
     */
    private function integerList(mixed $values): array
    {
        if (!is_array($values)) {
            return [];
        }

        $out = [];
        foreach ($values as $value) {
            if (is_int($value) || is_float($value) || (is_string($value) && preg_match('/^-?\d+$/', $value) === 1)) {
                $out[] = (int) $value;
            }
        }

        return $out;
    }

    private function nullableInteger(mixed $value): ?int
    {
        if (is_int($value) || is_float($value) || (is_string($value) && preg_match('/^-?\d+$/', $value) === 1)) {
            return (int) $value;
        }

        return null;
    }

    /**
     * @param list<array<string, mixed>> $candidateGridCells
     * @return list<int>
     */
    private function uniqueGridIds(array $candidateGridCells, string $field): array
    {
        $ids = [];
        foreach ($candidateGridCells as $cell) {
            foreach (($cell[$field] ?? []) as $id) {
                if ($id !== null) {
                    $ids[] = (int) $id;
                }
            }
        }

        return $this->nonNullSortedIds($ids);
    }

    /**
     * @param list<int> $rowIds
     * @param list<int> $colIds
     * @return list<string>
     */
    private function gridBorderAxes(array $rowIds, array $colIds): array
    {
        $axes = [];
        if (count($colIds) > 1) {
            $axes[] = 'column';
        }
        if (count($rowIds) > 1) {
            $axes[] = 'row';
        }

        return $axes;
    }

    /**
     * Upstream tabled keeps only first-row/column anchors in Markdown/HTML,
     * while SpanTableCell still carries row/column span occupancy. Keep OCR
     * continuation cells visible in the WordPress span review grid when they
     * share an anchor or land in a grid position already covered by a span.
     *
     * @param list<array{bbox: list<float>, text: string, row_ids: list<int|null>, col_ids: list<int|null>, order?: int}> $cells
     * @return list<array{cells: list<array{bbox: list<float>, text: string, row_ids: list<int|null>, col_ids: list<int|null>, order?: int}>, row_ids: list<int>, col_ids: list<int>, text: string, bbox: list<float>}>
     */
    private function spanningGridAnchorGroups(array $cells): array
    {
        $groups = [];
        $order = [];

        foreach ($cells as $cell) {
            $rowIds = $this->nonNullSortedIds($cell['row_ids']);
            $colIds = $this->nonNullSortedIds($cell['col_ids']);
            if ($rowIds === [] || $colIds === []) {
                continue;
            }

            $anchorKey = $rowIds[0] . ':' . $colIds[0];
            $key = $this->coveringSpanningGroupKey($groups, $rowIds[0], $colIds[0]) ?? $anchorKey;
            if (!isset($groups[$key])) {
                $groups[$key] = [
                    'cells' => [],
                    'row_ids' => [],
                    'col_ids' => [],
                    'text' => '',
                    'bbox' => $cell['bbox'],
                ];
                $order[] = $key;
            }

            $groups[$key]['cells'][] = $cell;
            $groups[$key]['row_ids'] = $this->mergeSortedIds($groups[$key]['row_ids'], $rowIds);
            $groups[$key]['col_ids'] = $this->mergeSortedIds($groups[$key]['col_ids'], $colIds);
            $groups[$key]['text'] = $this->combinedCellText($groups[$key]['cells']);
            $groups[$key]['bbox'] = $this->mergedCellBbox($groups[$key]['cells']);
        }

        $out = [];
        foreach ($order as $key) {
            $out[] = $groups[$key];
        }

        return $out;
    }

    /**
     * @param array<string, array{row_ids: list<int>, col_ids: list<int>}> $groups
     */
    private function coveringSpanningGroupKey(array $groups, int $rowId, int $colId): ?string
    {
        $anchorKey = $rowId . ':' . $colId;
        foreach ($groups as $key => $group) {
            if ($key === $anchorKey) {
                continue;
            }
            if (in_array($rowId, $group['row_ids'], true) && in_array($colId, $group['col_ids'], true)) {
                return $key;
            }
        }

        return null;
    }

    /**
     * @param list<int> $left
     * @param list<int> $right
     * @return list<int>
     */
    private function mergeSortedIds(array $left, array $right): array
    {
        $ids = array_values(array_unique(array_merge($left, $right)));
        sort($ids, SORT_NUMERIC);

        return $ids;
    }

    /**
     * @param list<array{bbox: list<float>, text: string, row_ids: list<int|null>, col_ids: list<int|null>, order?: int}> $cells
     */
    private function combinedCellText(array $cells): string
    {
        $parts = $this->reviewTextParts($cells);

        return implode(' ', $parts);
    }

    /**
     * @param list<array{bbox: list<float>, text: string, row_ids: list<int|null>, col_ids: list<int|null>, order?: int}> $cells
     * @return list<string>
     */
    private function reviewTextParts(array $cells): array
    {
        $parts = [];
        foreach ($cells as $cell) {
            $text = trim((string) $cell['text']);
            if ($text !== '') {
                $parts[] = $text;
            }
        }

        return $parts;
    }

    /**
     * @param list<array{bbox: list<float>, text: string, row_ids: list<int|null>, col_ids: list<int|null>, order?: int}> $cells
     * @return list<array{text: string, row_ids: list<int>, col_ids: list<int>, bbox: list<float>}>
     */
    private function reviewContinuationCells(array $cells): array
    {
        $continuations = [];
        foreach ($cells as $cell) {
            $continuations[] = [
                'text' => (string) $cell['text'],
                'row_ids' => $this->nonNullSortedIds($cell['row_ids']),
                'col_ids' => $this->nonNullSortedIds($cell['col_ids']),
                'bbox' => $cell['bbox'],
            ];
        }

        return $continuations;
    }

    /**
     * @param list<array{bbox: list<float>, text: string, row_ids: list<int|null>, col_ids: list<int|null>, order?: int}> $cells
     * @return list<float>
     */
    private function mergedCellBbox(array $cells): array
    {
        $bboxes = array_column($cells, 'bbox');

        return [
            min(array_column($bboxes, 0)),
            min(array_column($bboxes, 1)),
            max(array_column($bboxes, 2)),
            max(array_column($bboxes, 3)),
        ];
    }

    /**
     * @param list<array{cells: list<array{bbox: list<float>, text: string, row_ids: list<int|null>, col_ids: list<int|null>, order?: int}>, row_ids: list<int>, col_ids: list<int>, text: string, bbox: list<float>}> $cellGroups
     * @param list<int> $rowIds
     * @return list<int>
     */
    private function columnHeaderRowIdsForGrid(array $cellGroups, array $rowIds, int $topRowId): array
    {
        $maxHeaderRowId = $topRowId;
        foreach ($cellGroups as $cellGroup) {
            $groupRowIds = $cellGroup['row_ids'];
            if ($groupRowIds === [] || $groupRowIds[0] !== $topRowId) {
                continue;
            }

            $maxHeaderRowId = max($maxHeaderRowId, max($groupRowIds));
        }

        $headerRows = [];
        foreach ($rowIds as $rowId) {
            if ($rowId <= $maxHeaderRowId) {
                $headerRows[] = $rowId;
            }
        }

        return $headerRows === [] ? [$topRowId] : $headerRows;
    }

    /**
     * @param list<int> $rowIds
     * @param list<int> $colIds
     * @param list<int> $columnHeaderRowIds
     */
    private function headerScopeForGridCell(array $rowIds, array $colIds, array $columnHeaderRowIds, int $leftColId): ?string
    {
        if (in_array($rowIds[0], $columnHeaderRowIds, true)) {
            return count($colIds) > 1 ? 'colgroup' : 'col';
        }

        if ($colIds[0] === $leftColId && count($rowIds) > 1) {
            return 'rowgroup';
        }

        return null;
    }

    /**
     * @param list<int> $rowIds
     * @param list<int> $colIds
     * @param list<int> $columnHeaderRowIds
     * @return list<string>
     */
    private function headerAxesForGridCell(array $rowIds, array $colIds, array $columnHeaderRowIds, int $leftColId): array
    {
        $axes = [];
        if (in_array($rowIds[0], $columnHeaderRowIds, true)) {
            $axes[] = 'column';
        }
        if ($colIds[0] === $leftColId && count($rowIds) > 1) {
            $axes[] = 'row';
        }

        return $axes;
    }

    /**
     * @param list<string> $axes
     */
    private function headerAxisForAxes(array $axes): ?string
    {
        if (count($axes) > 1) {
            return 'both';
        }

        return $axes[0] ?? null;
    }

    /**
     * @return array{rows: list<int>, cols: list<int>, column_header_rows: list<int>, rotated: bool, orientation: string, row_axis: string, col_axis: string, render_cells: list<array<string, mixed>>, grid_cells: list<array<string, mixed>>, header_cells: list<array<string, mixed>>, data_cells: list<array<string, mixed>>, accessibility_grid: array<string, mixed>}
     */
    private function emptySpanningGridReview(bool $rotated = false): array
    {
        $axisMetadata = $this->spanningGridAxisMetadata($rotated);

        return [
            'rows' => [],
            'cols' => [],
            'column_header_rows' => [],
            'rotated' => $rotated,
            'orientation' => $axisMetadata['orientation'],
            'row_axis' => $axisMetadata['row_axis'],
            'col_axis' => $axisMetadata['col_axis'],
            'render_cells' => [],
            'grid_cells' => [],
            'header_cells' => [],
            'data_cells' => [],
            'accessibility_grid' => $this->accessibilityGridReview([], [], $axisMetadata, [], []),
        ];
    }

    /**
     * @return array{orientation: string, row_axis: string, col_axis: string}
     */
    private function spanningGridAxisMetadata(bool $rotated): array
    {
        return [
            'orientation' => $rotated ? 'rotated' : 'normal',
            'row_axis' => $rotated ? 'x' : 'y',
            'col_axis' => $rotated ? 'y' : 'x',
        ];
    }

    private function headerRoleForScope(?string $scope): ?string
    {
        if ($scope === 'col' || $scope === 'colgroup') {
            return 'column_header';
        }
        if ($scope === 'row' || $scope === 'rowgroup') {
            return 'row_header';
        }

        return null;
    }

    /**
     * Build stable header ids and body-cell header references for WordPress table output.
     *
     * Tabled preserves merged header occupancy in row_ids/col_ids, but its
     * Markdown/HTML paths consume only anchor coordinates. This review metadata
     * lets downstream importers emit id/headers attributes without reparsing
     * Markdown or re-inferring spans.
     *
     * @param list<array<string, mixed>> $renderCells
     * @return array{render_cells: list<array<string, mixed>>, header_cells: list<array<string, mixed>>, data_cells: list<array<string, mixed>>}
     */
    private function applyHeaderReferences(array $renderCells): array
    {
        $headerCells = [];
        foreach ($renderCells as $index => &$renderCell) {
            if (($renderCell['header'] ?? false) !== true) {
                continue;
            }

            $headerId = $this->headerIdForAnchor($renderCell['anchor'] ?? []);
            $renderCell['header_id'] = $headerId;
            $headerCells[] = [
                'header_id' => $headerId,
                'render_cell_index' => $index,
                'text' => (string) ($renderCell['text'] ?? ''),
                'row_ids' => array_values($renderCell['row_ids'] ?? []),
                'col_ids' => array_values($renderCell['col_ids'] ?? []),
                'anchor' => $renderCell['anchor'] ?? null,
                'scope' => $renderCell['scope'] ?? null,
                'header_role' => $renderCell['header_role'] ?? null,
                'header_axis' => $renderCell['header_axis'] ?? null,
                'header_axes' => array_values($renderCell['header_axes'] ?? []),
                'rowspan' => (int) ($renderCell['rowspan'] ?? 1),
                'colspan' => (int) ($renderCell['colspan'] ?? 1),
            ];
        }
        unset($renderCell);

        $dataCells = [];
        foreach ($renderCells as $index => &$renderCell) {
            if (($renderCell['header'] ?? false) === true) {
                continue;
            }

            $references = $this->headerReferencesForDataCell($renderCell, $headerCells);
            $renderCell['headers'] = $references['headers'];
            $renderCell['column_header_ids'] = $references['column_header_ids'];
            $renderCell['row_header_ids'] = $references['row_header_ids'];
            $renderCell['header_texts'] = $references['header_texts'];
            $renderCell['header_text'] = $references['header_text'];
            $renderCell['column_header_physical_axis'] = (string) ($renderCell['col_axis'] ?? 'x');
            $renderCell['row_header_physical_axis'] = (string) ($renderCell['row_axis'] ?? 'y');

            $dataCells[] = [
                'render_cell_index' => $index,
                'text' => (string) ($renderCell['text'] ?? ''),
                'row_ids' => array_values($renderCell['row_ids'] ?? []),
                'col_ids' => array_values($renderCell['col_ids'] ?? []),
                'anchor' => $renderCell['anchor'] ?? null,
                'headers' => $references['headers'],
                'column_header_ids' => $references['column_header_ids'],
                'row_header_ids' => $references['row_header_ids'],
                'header_texts' => $references['header_texts'],
                'header_text' => $references['header_text'],
                'column_header_physical_axis' => $renderCell['column_header_physical_axis'],
                'row_header_physical_axis' => $renderCell['row_header_physical_axis'],
            ];
        }
        unset($renderCell);

        return [
            'render_cells' => $renderCells,
            'header_cells' => $headerCells,
            'data_cells' => $dataCells,
        ];
    }

    /**
     * @param array<string, mixed> $anchor
     */
    private function headerIdForAnchor(array $anchor): string
    {
        return 'h-r' . (int) ($anchor['row_id'] ?? 0) . '-c' . (int) ($anchor['col_id'] ?? 0);
    }

    /**
     * @param array<string, mixed> $dataCell
     * @param list<array<string, mixed>> $headerCells
     * @return array{headers: list<string>, column_header_ids: list<string>, row_header_ids: list<string>, header_texts: list<string>, header_text: string}
     */
    private function headerReferencesForDataCell(array $dataCell, array $headerCells): array
    {
        $rowIds = $this->integerList($dataCell['row_ids'] ?? []);
        $colIds = $this->integerList($dataCell['col_ids'] ?? []);
        if ($rowIds === [] || $colIds === []) {
            return [
                'headers' => [],
                'column_header_ids' => [],
                'row_header_ids' => [],
                'header_texts' => [],
                'header_text' => '',
            ];
        }

        $dataMinRow = min($rowIds);
        $dataMinCol = min($colIds);
        $columnHeaderIds = [];
        $rowHeaderIds = [];
        $headerTextsById = [];

        foreach ($headerCells as $headerCell) {
            $headerId = (string) ($headerCell['header_id'] ?? '');
            if ($headerId === '') {
                continue;
            }

            $headerRowIds = $this->integerList($headerCell['row_ids'] ?? []);
            $headerColIds = $this->integerList($headerCell['col_ids'] ?? []);
            $headerAxes = array_values(array_map('strval', $headerCell['header_axes'] ?? []));
            if ($headerRowIds === [] || $headerColIds === []) {
                continue;
            }

            $headerTextsById[$headerId] = (string) ($headerCell['text'] ?? '');
            if (
                in_array('column', $headerAxes, true)
                && min($headerRowIds) < $dataMinRow
                && array_intersect($headerColIds, $colIds) !== []
            ) {
                $columnHeaderIds[] = $headerId;
            }
            if (
                in_array('row', $headerAxes, true)
                && min($headerColIds) < $dataMinCol
                && array_intersect($headerRowIds, $rowIds) !== []
            ) {
                $rowHeaderIds[] = $headerId;
            }
        }

        $headers = $this->uniqueStrings(array_merge($columnHeaderIds, $rowHeaderIds));
        $headerTexts = [];
        foreach ($headers as $headerId) {
            $text = trim($headerTextsById[$headerId] ?? '');
            if ($text !== '') {
                $headerTexts[] = $text;
            }
        }

        return [
            'headers' => $headers,
            'column_header_ids' => $this->uniqueStrings($columnHeaderIds),
            'row_header_ids' => $this->uniqueStrings($rowHeaderIds),
            'header_texts' => $headerTexts,
            'header_text' => implode(' / ', $headerTexts),
        ];
    }

    /**
     * @param list<int> $rowIds
     * @param list<int> $colIds
     * @param array{orientation: string, row_axis: string, col_axis: string} $axisMetadata
     * @param list<array<string, mixed>> $headerCells
     * @param list<array<string, mixed>> $dataCells
     * @return array<string, mixed>
     */
    private function accessibilityGridReview(array $rowIds, array $colIds, array $axisMetadata, array $headerCells, array $dataCells): array
    {
        $columnHeaderGrid = [];
        foreach ($colIds as $colId) {
            $headers = $this->headerCoverageForGridId($headerCells, 'col_ids', $colId, 'column');
            $columnHeaderGrid[] = [
                'col_id' => $colId,
                'physical_axis' => $axisMetadata['col_axis'],
                'header_ids' => $headers['header_ids'],
                'header_texts' => $headers['header_texts'],
            ];
        }

        $rowHeaderGrid = [];
        foreach ($rowIds as $rowId) {
            $headers = $this->headerCoverageForGridId($headerCells, 'row_ids', $rowId, 'row');
            $rowHeaderGrid[] = [
                'row_id' => $rowId,
                'physical_axis' => $axisMetadata['row_axis'],
                'header_ids' => $headers['header_ids'],
                'header_texts' => $headers['header_texts'],
            ];
        }

        $dataCellHeaders = [];
        foreach ($dataCells as $dataCell) {
            $dataCellHeaders[] = [
                'render_cell_index' => (int) ($dataCell['render_cell_index'] ?? 0),
                'text' => (string) ($dataCell['text'] ?? ''),
                'row_ids' => $this->integerList($dataCell['row_ids'] ?? []),
                'col_ids' => $this->integerList($dataCell['col_ids'] ?? []),
                'anchor' => $dataCell['anchor'] ?? null,
                'headers' => $this->stringList($dataCell['headers'] ?? []),
                'column_header_ids' => $this->stringList($dataCell['column_header_ids'] ?? []),
                'row_header_ids' => $this->stringList($dataCell['row_header_ids'] ?? []),
                'header_texts' => $this->stringList($dataCell['header_texts'] ?? []),
                'header_text' => (string) ($dataCell['header_text'] ?? ''),
                'column_header_physical_axis' => $axisMetadata['col_axis'],
                'row_header_physical_axis' => $axisMetadata['row_axis'],
            ];
        }

        return [
            'review_target' => $axisMetadata['orientation'] === 'rotated'
                ? 'table_rotated_header_accessibility_grid'
                : 'table_header_accessibility_grid',
            'rotated' => $axisMetadata['orientation'] === 'rotated',
            'orientation' => $axisMetadata['orientation'],
            'row_axis' => $axisMetadata['row_axis'],
            'col_axis' => $axisMetadata['col_axis'],
            'column_header_physical_axis' => $axisMetadata['col_axis'],
            'row_header_physical_axis' => $axisMetadata['row_axis'],
            'header_ids' => $this->stringList(array_map(static fn (array $cell): mixed => $cell['header_id'] ?? null, $headerCells)),
            'column_header_grid' => $columnHeaderGrid,
            'row_header_grid' => $rowHeaderGrid,
            'data_cell_headers' => $dataCellHeaders,
            'data_cell_count' => count($dataCellHeaders),
        ];
    }

    /**
     * @param list<array<string, mixed>> $headerCells
     * @return array{header_ids: list<string>, header_texts: list<string>}
     */
    private function headerCoverageForGridId(array $headerCells, string $idField, int $gridId, string $axis): array
    {
        $headerIds = [];
        $headerTexts = [];
        foreach ($headerCells as $headerCell) {
            $headerAxes = $this->stringList($headerCell['header_axes'] ?? []);
            if (!in_array($axis, $headerAxes, true)) {
                continue;
            }
            if (!in_array($gridId, $this->integerList($headerCell[$idField] ?? []), true)) {
                continue;
            }

            $headerId = (string) ($headerCell['header_id'] ?? '');
            if ($headerId === '') {
                continue;
            }
            $headerIds[] = $headerId;
            $text = trim((string) ($headerCell['text'] ?? ''));
            if ($text !== '') {
                $headerTexts[] = $text;
            }
        }

        return [
            'header_ids' => $this->uniqueStrings($headerIds),
            'header_texts' => $this->uniqueStrings($headerTexts),
        ];
    }

    /**
     * @return list<string>
     */
    private function stringList(mixed $values): array
    {
        if (!is_array($values)) {
            return [];
        }

        $out = [];
        foreach ($values as $value) {
            if (is_scalar($value) && (string) $value !== '') {
                $out[] = (string) $value;
            }
        }

        return $this->uniqueStrings($out);
    }

    /**
     * @param list<string> $values
     * @return list<string>
     */
    private function uniqueStrings(array $values): array
    {
        $seen = [];
        $out = [];
        foreach ($values as $value) {
            if (isset($seen[$value])) {
                continue;
            }
            $seen[$value] = true;
            $out[] = $value;
        }

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
     * @param list<int> $rowIds
     * @param list<int> $colIds
     * @param array<int, list<float>> $rowBboxes
     * @param array<int, list<float>> $colBboxes
     * @return list<array{row_id: int, col_id: int, bbox: list<float>}>
     */
    private function gridCellBboxesForSpan(array $rowIds, array $colIds, array $rowBboxes, array $colBboxes, bool $rotated): array
    {
        $cells = [];
        foreach ($rowIds as $rowId) {
            foreach ($colIds as $colId) {
                $bbox = $this->gridBboxForSpan([$rowId], [$colId], $rowBboxes, $colBboxes, $rotated);
                if ($bbox === null) {
                    continue;
                }
                $cells[] = [
                    'row_id' => $rowId,
                    'col_id' => $colId,
                    'bbox' => $bbox,
                ];
            }
        }

        return $cells;
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
                $bbox = $this->bboxFromRecord($cell);
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
        $normalized = $this->bboxFromValue($bbox);
        if ($normalized === null) {
            throw new InvalidArgumentException('Table geometry entries must include a four-value bbox or named bbox fields.');
        }

        return $normalized;
    }

    /**
     * @param array<string, mixed> $record
     * @return list<float>
     */
    private function bboxFromRecord(array $record): array
    {
        $bbox = $this->bboxFromValue($record['bbox'] ?? null)
            ?? $this->bboxFromNamedFields($record)
            ?? $this->polygonBbox($record['polygon'] ?? null);
        if ($bbox === null) {
            throw new InvalidArgumentException('Table geometry entries must include a four-value bbox, named bbox fields, or four-corner polygon.');
        }

        return $bbox;
    }

    /**
     * @param mixed $bbox
     * @return list<float>|null
     */
    private function bboxFromValue(mixed $bbox): ?array
    {
        if (!is_array($bbox)) {
            return null;
        }

        return $this->nullableBbox($bbox)
            ?? $this->bboxFromNamedFields($bbox);
    }

    /**
     * @param array<string, mixed> $record
     * @return list<float>|null
     */
    private function bboxFromNamedFields(array $record): ?array
    {
        $sets = [
            ['x1', 'y1', 'x2', 'y2'],
            ['x_start', 'y_start', 'x_end', 'y_end'],
            ['left', 'top', 'right', 'bottom'],
        ];

        foreach ($sets as $keys) {
            [$x1, $y1, $x2, $y2] = $keys;
            if (
                !array_key_exists($x1, $record)
                || !array_key_exists($y1, $record)
                || !array_key_exists($x2, $record)
                || !array_key_exists($y2, $record)
            ) {
                continue;
            }

            $values = [$record[$x1], $record[$y1], $record[$x2], $record[$y2]];
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

        return null;
    }

    /**
     * @param array<string, mixed> $record
     */
    private function bboxCoordinateSourceFromRecord(array $record): string
    {
        $bbox = $record['bbox'] ?? null;
        if (is_array($bbox)) {
            return $this->bboxNamedFieldSource($bbox) ?? 'bbox_array';
        }

        return $this->bboxNamedFieldSource($record)
            ?? (is_array($record['polygon'] ?? null) ? 'polygon' : 'bbox_array');
    }

    /**
     * @param array<string, mixed> $record
     */
    private function bboxNamedFieldSource(array $record): ?string
    {
        $sets = [
            'bbox_xyxy_named_fields' => ['x1', 'y1', 'x2', 'y2'],
            'bbox_x_start_y_start_fields' => ['x_start', 'y_start', 'x_end', 'y_end'],
            'bbox_left_top_right_bottom_fields' => ['left', 'top', 'right', 'bottom'],
        ];

        foreach ($sets as $source => $keys) {
            if (count(array_intersect($keys, array_keys($record))) === 4) {
                return $source;
            }
        }

        return null;
    }

    /**
     * @param array{width?: int|float, height?: int|float}|list<int|float> $imageSize
     * @return array{width: int, height: int}
     */
    private function imageSize(array $imageSize): array
    {
        $width = $this->numericScalar($imageSize['width'] ?? $imageSize[0] ?? null);
        $height = $this->numericScalar($imageSize['height'] ?? $imageSize[1] ?? null);
        if ($width === null || $height === null || $width <= 0.0 || $height <= 0.0) {
            throw new InvalidArgumentException('Table image sizes must include positive width and height.');
        }

        return ['width' => (int) round($width), 'height' => (int) round($height)];
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

    /**
     * @param list<float> $bbox
     */
    private function positiveArea(array $bbox): float
    {
        return max(0.0, $bbox[2] - $bbox[0]) * max(0.0, $bbox[3] - $bbox[1]);
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
