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
     * @return array{table_cells: list<list<array<string, mixed>>>, needs_ocr: list<bool>, table_text_cell_boundary_reviews: list<array<string, mixed>|null>, table_detector_cell_boundary_reviews: list<array<string, mixed>|null>}
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
        $detectorCellBoundaryReviews = [];

        for ($idx = 0; $idx < $count; $idx++) {
            $tableBbox = $this->bbox($tableBboxes[$idx]);
            $imageSize = $this->imageSize($imageSizes[$idx]);

            $textLine = $textLines[$idx];
            $textBlocks = $this->tableBlocksFromTextLine($textLine, $tableBbox, $imageSize);

            if ($textLine === null || $detectBoxes || $textBlocks['cells'] === []) {
                if (!array_key_exists($idx, $suppliedDetections)) {
                    throw new InvalidArgumentException('Missing supplied detector cells for table index ' . $idx . '.');
                }
                $detectorCropSize = $this->imageSizeFromBboxExtent($tableBbox) ?? $imageSize;
                $detectorCells = $this->detectorCellsInTableCrop(
                    $this->normalizeCells($suppliedDetections[$idx], true),
                    $tableBbox,
                    $detectorCropSize
                );
                $bounded = $this->boundedDetectorCellsForCrop(
                    $detectorCells,
                    $detectorCropSize
                );
                $tableCells[] = $bounded['cells'];
                $needsOcr[] = true;
                $cellBoundaryReviews[] = null;
                $detectorCellBoundaryReviews[] = $bounded['review'];
                continue;
            }

            $tableCells[] = $this->normalizeCells($textBlocks['cells']);
            $needsOcr[] = false;
            $cellBoundaryReviews[] = $textBlocks['boundary_review'];
            $detectorCellBoundaryReviews[] = null;
        }

        return [
            'table_cells' => $tableCells,
            'needs_ocr' => $needsOcr,
            'table_text_cell_boundary_reviews' => $cellBoundaryReviews,
            'table_detector_cell_boundary_reviews' => $detectorCellBoundaryReviews,
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
        $detectionResult = $this->canonicalizedRecognizedTableGeometryAliases($detectionResult);
        $rows = $this->normalizeRowsOrCols($detectionResult['rows'] ?? [], 'row_id');
        $cols = $this->normalizeRowsOrCols($detectionResult['cols'] ?? [], 'col_id');
        $cells = $this->normalizeCells($detectionResult['cells'] ?? []);
        $assignmentImageSize = $this->imageSize($imageSize);
        $geometryBands = $this->tableGridGeometryBoundary($rows, $cols, $assignmentImageSize);
        $rows = $geometryBands['rows'];
        $cols = $geometryBands['cols'];
        $cells = $this->boundedAssignmentCells($cells, $assignmentImageSize);

        if ($cells === []) {
            return [];
        }
        if ($rows === [] || $cols === []) {
            return $this->heuristicLayout($cells, $assignmentImageSize);
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

        return $this->withBandOrderMetadata($assigned, $rows, $cols);
    }

    /**
     * Upstream tabled assigns cells inside a cropped table image. Drop supplied
     * cells that have no positive area inside that crop while preserving
     * partially crossing cell bboxes for accepted table-local review metadata.
     *
     * @param list<array<string, mixed>> $cells
     * @param array{width: int, height: int} $imageSize
     * @return list<array<string, mixed>>
     */
    private function boundedAssignmentCells(array $cells, array $imageSize): array
    {
        $bounded = [];
        foreach ($cells as $cell) {
            $bbox = $cell['bbox'];
            $clipped = $this->clipBboxToImage($bbox, $imageSize);
            if ($this->positiveArea($bbox) <= 0.0 || $this->positiveArea($clipped) <= 0.0) {
                continue;
            }

            $bounded[] = $cell;
        }

        return $bounded;
    }

    /**
     * Native boundary for tabled.formats.formatter("markdown", cells).
     *
     * @param list<array<string, mixed>> $cells
     */
    public function markdownFormat(array $cells): string
    {
        $cells = $this->normalizeAssignedCells($cells);
        $rowOrder = $this->storedBandOrderMap($cells, 'row');
        $colOrder = $this->storedBandOrderMap($cells, 'col');
        $cells = $this->sortCells($cells, $rowOrder, $colOrder);
        if ($cells === []) {
            return '';
        }

        $rows = $this->orderedUniqueIdsFromCells($cells, 'row_ids', $rowOrder);
        $cols = $this->orderedUniqueIdsFromCells($cells, 'col_ids', $colOrder);
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
        $rows = $this->normalizeRowsOrCols($rows, 'row_id');
        $cols = $this->normalizeRowsOrCols($cols, 'col_id');
        $geometryBands = $this->tableGridGeometryBoundary($rows, $cols, $imageSize);
        $rows = $geometryBands['rows'];
        $cols = $geometryBands['cols'];
        $rowOrder = $this->bandOrderMap($rows, 'row_id');
        $colOrder = $this->bandOrderMap($cols, 'col_id');
        $cells = $this->sortCells($this->normalizeAssignedCells($cells, $rows, $cols), $rowOrder, $colOrder);
        $rowBboxes = $this->bboxesById($rows, 'row_id');
        $colBboxes = $this->bboxesById($cols, 'col_id');
        $rotated = $rows !== [] && $cols !== [] && $this->isRotated($rows, $cols);

        $geometry = [];
        foreach ($cells as $cell) {
            $rowIds = $this->nonNullOrderedIds($cell['row_ids'], $rowOrder);
            $colIds = $this->nonNullOrderedIds($cell['col_ids'], $colOrder);
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
     * @return array{rows: list<int>, cols: list<int>, column_header_rows?: list<int>, rotated: bool, orientation: string, row_axis: string, col_axis: string, render_cells: list<array<string, mixed>>, grid_cells: list<array<string, mixed>>, header_cells: list<array<string, mixed>>, data_cells: list<array<string, mixed>>, accessibility_grid: array<string, mixed>, geometry_boundary_review?: array<string, mixed>, cell_geometry_boundary_review?: array<string, mixed>}
     */
    public function spanningGridReview(array $cells, array $rows = [], array $cols = [], ?array $imageSize = null): array
    {
        $rows = $this->normalizeRowsOrCols($rows, 'row_id');
        $cols = $this->normalizeRowsOrCols($cols, 'col_id');
        $geometryBands = $this->tableGridGeometryBoundary($rows, $cols, $imageSize);
        $rows = $geometryBands['rows'];
        $cols = $geometryBands['cols'];
        $rowOrder = $this->bandOrderMap($rows, 'row_id');
        $colOrder = $this->bandOrderMap($cols, 'col_id');
        $cells = $this->sortCells($this->normalizeAssignedCells($cells, $rows, $cols), $rowOrder, $colOrder);
        if ($cells === []) {
            return $this->emptySpanningGridReview();
        }

        $geometryBoundaryReview = $geometryBands['review'];
        $cellBoundaryReview = $this->tableCellGeometryBoundary($cells, $imageSize);
        $cells = $this->cellsWithGeometryBoundary($cells, $cellBoundaryReview);
        $rowBboxes = $this->bboxesById($rows, 'row_id');
        $colBboxes = $this->bboxesById($cols, 'col_id');
        $rotated = $rows !== [] && $cols !== [] && $this->isRotated($rows, $cols);
        $axisMetadata = $this->spanningGridAxisMetadata($rotated);

        $cellGroups = $this->spanningGridAnchorGroups($cells, $rowOrder, $colOrder);
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
        $rowIds = $this->orderIdsByMap(array_values($rowIds), $rowOrder);
        $colIds = $this->orderIdsByMap(array_values($colIds), $colOrder);

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
                $entry['continuation_cells'] = $this->reviewContinuationCells(array_slice($cellGroup['cells'], 1), $rowOrder, $colOrder);
            }

            $cellBoundary = $this->cellGroupBoundarySummary($cellGroup['cells']);
            if ($cellBoundary !== null) {
                foreach ($cellBoundary as $field => $value) {
                    $entry[$field] = $value;
                }
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

        $headerReferences = $this->applyHeaderReferences($renderCells, $rowOrder, $colOrder);
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
                    foreach ([
                        'cell_boundary_status',
                        'cell_boundary_active_count',
                        'cell_boundary_clipped_count',
                        'cell_boundary_excluded_count',
                        'bounded_cell_bbox',
                        'clipped_cell_bbox',
                        'source_cell_bbox',
                        'source_cell_bboxes',
                        'source_coordinate_space',
                        'source_coordinate_spaces',
                    ] as $field) {
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
        if ($cellBoundaryReview !== null) {
            $review['cell_geometry_boundary_review'] = $cellBoundaryReview;
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
        $rows = $this->normalizeRowsOrCols($rows, 'row_id');
        $cols = $this->normalizeRowsOrCols($cols, 'col_id');
        $geometryBands = $this->tableGridGeometryBoundary($rows, $cols, $imageSize);
        $rows = $geometryBands['rows'];
        $cols = $geometryBands['cols'];
        $geometryBoundaryReview = $geometryBands['review'];
        $rowOrder = $this->bandOrderMap($rows, 'row_id');
        $colOrder = $this->bandOrderMap($cols, 'col_id');
        $assignedCells = $this->normalizeAssignedCells($assignedCells, $rows, $cols);
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
            if ($geometryBoundaryReview !== null) {
                $entry['geometry_boundary_review'] = $geometryBoundaryReview;
            }
            $candidateIndexes = $this->integerList($conflict['candidate_cell_indexes'] ?? []);
            $candidateGridCells = [];
            $candidateGridRenderCells = [];
            $candidateAnchors = [];
            foreach ($candidateIndexes as $cellIndex) {
                if (!isset($assignedCells[$cellIndex])) {
                    continue;
                }

                $cell = $assignedCells[$cellIndex];
                $rowIds = $this->nonNullOrderedIds($cell['row_ids'], $rowOrder);
                $colIds = $this->nonNullOrderedIds($cell['col_ids'], $colOrder);
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
                $candidateGridRenderCell = $this->assignedCellGridRenderReview($cellIndex, $cell, $gridCellsByPosition, $renderCells, $rowOrder, $colOrder);
                if ($candidateGridRenderCell !== null) {
                    $candidateGridRenderCells[] = $candidateGridRenderCell;
                }
            }

            if ($candidateGridCells !== []) {
                $entry['candidate_grid_cells'] = $candidateGridCells;
                $entry['candidate_grid_anchors'] = $candidateAnchors;
                $entry['candidate_row_ids'] = $this->uniqueGridIds($candidateGridCells, 'row_ids', $rowOrder);
                $entry['candidate_col_ids'] = $this->uniqueGridIds($candidateGridCells, 'col_ids', $colOrder);
                $entry['grid_border_axes'] = $this->gridBorderAxes($entry['candidate_row_ids'], $entry['candidate_col_ids']);
                $entry['grid_border_axis'] = $this->headerAxisForAxes($entry['grid_border_axes']);
            }
            if ($candidateGridRenderCells !== []) {
                $entry['candidate_grid_render_cells'] = $candidateGridRenderCells;
            }

            $assignedIndex = $this->nullableInteger($conflict['assigned_cell_index'] ?? null);
            if ($assignedIndex !== null && isset($assignedCells[$assignedIndex])) {
                $cell = $assignedCells[$assignedIndex];
                $rowIds = $this->nonNullOrderedIds($cell['row_ids'], $rowOrder);
                $colIds = $this->nonNullOrderedIds($cell['col_ids'], $colOrder);
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

                $assignedGridRenderCell = $this->assignedCellGridRenderReview($assignedIndex, $cell, $gridCellsByPosition, $renderCells, $rowOrder, $colOrder);
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
    private function assignedCellGridRenderReview(
        int $cellIndex,
        array $cell,
        array $gridCellsByPosition,
        array $renderCells,
        array $rowOrder = [],
        array $colOrder = []
    ): ?array {
        $rowIds = $this->nonNullOrderedIds($cell['row_ids'], $rowOrder);
        $colIds = $this->nonNullOrderedIds($cell['col_ids'], $colOrder);
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
            'source_cell_bbox',
            'source_cell_bboxes',
            'source_page_image_bbox',
            'source_page_image_bboxes',
            'source_coordinate_space',
            'source_coordinate_spaces',
            'source_coordinate_source',
            'source_coordinate_sources',
            'source_endpoint_order_normalized',
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
            'cell_boundary_status',
            'cell_boundary_active_count',
            'cell_boundary_clipped_count',
            'cell_boundary_excluded_count',
            'bounded_cell_bbox',
            'clipped_cell_bbox',
            'header_id',
            'headers',
            'column_header_ids',
            'row_header_ids',
            'header_texts',
            'header_text',
            'column_header_physical_axis',
            'row_header_physical_axis',
            'source_cell_bbox',
            'source_cell_bboxes',
            'source_page_image_bbox',
            'source_page_image_bboxes',
            'source_coordinate_space',
            'source_coordinate_spaces',
            'source_coordinate_source',
            'source_coordinate_sources',
            'source_endpoint_order_normalized',
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
     * @return array{assigned_cells: list<list<array<string, mixed>>>, markdown_tables: list<string>, recognized_tables: list<array<string, mixed>>, coordinate_space_reviews: list<array<string, mixed>|null>, assigned_crop_boundary_reviews: list<array<string, mixed>|null>, assigned_band_boundary_reviews: list<array<string, mixed>|null>}
     */
    public function formatRecognizedTables(array $recognizedTables, array $imageSizes): array
    {
        if (count($recognizedTables) !== count($imageSizes)) {
            throw new InvalidArgumentException('Recognized table and image size counts must match.');
        }

        $coordinateSpaceReviews = [];
        $recognitionImageSizes = [];
        foreach ($recognizedTables as $idx => $table) {
            if (!is_array($table)) {
                throw new InvalidArgumentException('Recognized table entries must be arrays.');
            }

            $table = $this->canonicalizedRecognizedTableGeometryAliases($table);
            $recognitionImageSize = $this->tableRecognitionImageSize($imageSizes[$idx], $table);
            $recognitionImageSizes[$idx] = $recognitionImageSize;
            $localized = $this->localizeRecognizedTableGeometry($table, $recognitionImageSize);
            $recognizedTables[$idx] = $localized['table'];
            $coordinateSpaceReviews[] = $localized['review'];
        }

        $assigned = [];
        $markdown = [];
        $assignedCropBoundaryReviews = [];
        $assignedBandBoundaryReviews = [];
        foreach ($recognizedTables as $idx => $table) {
            $assignedCells = $this->assignedCellsFromRecognizedTable($table);
            if ($assignedCells !== null) {
                $assignedCropBoundaryReview = $this->assignedCellCropBoundaryReview(
                    $assignedCells,
                    $recognitionImageSizes[$idx]
                );
                $assignedCropBoundaryReviews[] = $assignedCropBoundaryReview;
                $bounded = $this->boundedAssignedCellsWithActiveBands(
                    $this->activeAssignedCellsFromCropBoundary($assignedCells, $assignedCropBoundaryReview),
                    $table,
                    $recognitionImageSizes[$idx]
                );
                $tableCells = $bounded['cells'];
                $assignedBandBoundaryReviews[] = $bounded['review'];
            } else {
                $tableCells = $this->assignRowsColumns($table, $recognitionImageSizes[$idx]);
                $assignedCropBoundaryReviews[] = null;
                $assignedBandBoundaryReviews[] = null;
            }
            $assigned[] = $tableCells;
            $markdown[] = $this->markdownFormat($tableCells);
        }

        return [
            'assigned_cells' => $assigned,
            'markdown_tables' => $markdown,
            'recognized_tables' => $recognizedTables,
            'coordinate_space_reviews' => $coordinateSpaceReviews,
            'assigned_crop_boundary_reviews' => $assignedCropBoundaryReviews,
            'assigned_band_boundary_reviews' => $assignedBandBoundaryReviews,
        ];
    }

    /**
     * Saved tabled-pdf result JSON carries each table's high-resolution page
     * bbox plus the full image_bbox. When the sidecar size is absent, the
     * table bbox extent is the cropped table image size used by rows/cols/cells.
     *
     * @param array{width?: int|float, height?: int|float}|list<int|float>|array<string|int, mixed> $imageSize
     * @param array<string, mixed> $table
     * @return array<string|int, mixed>
     */
    private function tableRecognitionImageSize(array $imageSize, array $table): array
    {
        $provided = $this->nullableImageSize($imageSize);
        if ($provided !== null) {
            $imageSize['width'] = $provided['width'];
            $imageSize['height'] = $provided['height'];

            return $imageSize;
        }

        $cropCandidate = $this->tableCropBboxCandidate($table, $imageSize);
        $cropBbox = $cropCandidate['bbox'] ?? null;
        if ($cropBbox !== null) {
            $fromCropExtent = $this->imageSizeFromBboxExtent($cropBbox);
            if ($fromCropExtent !== null) {
                $imageSize['width'] = $fromCropExtent['width'];
                $imageSize['height'] = $fromCropExtent['height'];
                if (!isset($imageSize['table_bbox'])) {
                    $imageSize['table_bbox'] = $cropBbox;
                    if (isset($cropCandidate['source'])) {
                        $imageSize['table_bbox_source'] = $cropCandidate['source'];
                    }
                }
                $imageSize['image_size_source'] = 'table_crop_bbox_extent';

                return $imageSize;
            }
        }

        foreach (['image_bbox', 'page_image_bbox', 'rendered_image_bbox'] as $key) {
            $bbox = isset($table[$key]) ? $this->bboxFromValue($table[$key]) : null;
            if ($bbox === null && isset($imageSize[$key])) {
                $bbox = $this->bboxFromValue($imageSize[$key]);
            }
            if ($bbox === null) {
                continue;
            }

            $fromExtent = $this->imageSizeFromBboxExtent($bbox);
            if ($fromExtent === null) {
                continue;
            }

            $imageSize['width'] = $fromExtent['width'];
            $imageSize['height'] = $fromExtent['height'];
            $imageSize['image_size_source'] = $key . '_extent';

            return $imageSize;
        }

        return $this->imageSize($imageSize);
    }

    /**
     * Saved tabled assignments may include complete SpanTableCell row/column
     * ids while the cell bbox itself sits outside the current table crop. Keep
     * the existing fail-closed filter, but expose the exclusion before the
     * active-band pass so WordPress review metadata can explain why the saved
     * cell never reached Markdown.
     *
     * @param list<array{bbox: list<float>, text: string, row_ids: list<int|null>, col_ids: list<int|null>, order?: int}> $cells
     * @param array{width?: int|float, height?: int|float}|list<int|float> $imageSize
     * @return array<string, mixed>|null
     */
    private function assignedCellCropBoundaryReview(array $cells, array $imageSize): ?array
    {
        if ($cells === []) {
            return null;
        }

        $size = $this->imageSize($imageSize);
        $reviewRows = [];

        foreach ($cells as $index => $cell) {
            $bbox = $cell['bbox'];
            $clippedBbox = $this->clipBboxToImage($bbox, $size);
            $originalPositive = $this->positiveArea($bbox) > 0.0;
            $clippedPositive = $this->positiveArea($clippedBbox) > 0.0;
            $active = $originalPositive && $clippedPositive;
            $status = 'within_table_image';

            if (!$originalPositive) {
                $status = 'excluded_non_positive_area';
            } elseif (!$clippedPositive) {
                $status = 'excluded_outside_table_image';
            } elseif ($clippedBbox !== $bbox) {
                $status = 'clipped_to_table_image';
            }

            $reviewRow = [
                'cell_index' => $index,
                'text' => (string) $cell['text'],
                'row_ids' => $this->nonNullOrderedIds($cell['row_ids']),
                'col_ids' => $this->nonNullOrderedIds($cell['col_ids']),
                'original_bbox' => $bbox,
                'bounded_bbox' => $active ? $clippedBbox : null,
                'clipped_bbox' => $clippedBbox,
                'status' => $status,
                'active' => $active,
                'assignment_retained_after_crop_boundary' => $active,
                'assignment_excluded_before_markdown' => !$active,
            ];
            $reviewRow = $this->withSourceGeometryReviewFields($reviewRow, $cell);
            if ($reviewRow['row_ids'] !== [] && $reviewRow['col_ids'] !== []) {
                $reviewRow['anchor'] = [
                    'row_id' => $reviewRow['row_ids'][0],
                    'col_id' => $reviewRow['col_ids'][0],
                ];
            }

            $reviewRows[] = $reviewRow;
        }

        return [
            'review_target' => 'table_assigned_cell_crop_boundary',
            'upstream_boundary' => 'tabled.assignment.SpanTableCell.bbox_after_marker_table_crop',
            'image_size' => $size,
            'cell_count' => count($reviewRows),
            'active_cell_count' => count(array_filter(
                $reviewRows,
                static fn (array $row): bool => ($row['active'] ?? null) === true
            )),
            'within_cell_count' => count(array_filter(
                $reviewRows,
                static fn (array $row): bool => ($row['status'] ?? null) === 'within_table_image'
            )),
            'clipped_cell_count' => count(array_filter(
                $reviewRows,
                static fn (array $row): bool => ($row['status'] ?? null) === 'clipped_to_table_image'
            )),
            'excluded_cell_count' => count(array_filter(
                $reviewRows,
                static fn (array $row): bool => isset($row['status']) && str_starts_with((string) $row['status'], 'excluded_')
            )),
            'cells' => $reviewRows,
        ];
    }

    /**
     * @param list<array{bbox: list<float>, text: string, row_ids: list<int|null>, col_ids: list<int|null>, order?: int}> $cells
     * @param array<string, mixed>|null $review
     * @return list<array{bbox: list<float>, text: string, row_ids: list<int|null>, col_ids: list<int|null>, order?: int}>
     */
    private function activeAssignedCellsFromCropBoundary(array $cells, ?array $review): array
    {
        if ($review === null || !isset($review['cells']) || !is_array($review['cells'])) {
            return $cells;
        }

        $activeByIndex = [];
        foreach ($review['cells'] as $row) {
            if (!is_array($row) || ($row['active'] ?? null) !== true) {
                continue;
            }

            $cellIndex = $this->nullableInteger($row['cell_index'] ?? null);
            if ($cellIndex !== null) {
                $activeByIndex[$cellIndex] = true;
            }
        }

        $active = [];
        foreach ($cells as $index => $cell) {
            if (isset($activeByIndex[$index])) {
                $active[] = $cell;
            }
        }

        return $active;
    }

    /**
     * Bound saved SpanTableCell assignments to the row/column bands that still
     * exist inside the cropped table image. Upstream assignment normally runs
     * after cropping, so off-crop row/column ids from serialized fixtures must
     * not create ghost Markdown columns or rows.
     *
     * @param list<array<string, mixed>> $cells
     * @param array<string, mixed> $table
     * @param array{width?: int|float, height?: int|float}|list<int|float> $imageSize
     * @return array{cells: list<array<string, mixed>>, review: array<string, mixed>|null}
     */
    private function boundedAssignedCellsWithActiveBands(array $cells, array $table, array $imageSize): array
    {
        $table = $this->canonicalizedRecognizedTableGeometryAliases($table);
        if ($cells === [] || !isset($table['rows'], $table['cols']) || !is_array($table['rows']) || !is_array($table['cols'])) {
            return [
                'cells' => $cells,
                'review' => null,
            ];
        }

        $geometry = $this->tableGridGeometryBoundary(
            $this->normalizeRowsOrCols($table['rows'], 'row_id'),
            $this->normalizeRowsOrCols($table['cols'], 'col_id'),
            $imageSize
        );
        $rowOrder = $this->bandOrderMap($geometry['rows'], 'row_id');
        $colOrder = $this->bandOrderMap($geometry['cols'], 'col_id');
        $activeRowIds = array_keys($rowOrder);
        $activeColIds = array_keys($colOrder);
        $activeRows = array_fill_keys($activeRowIds, true);
        $activeCols = array_fill_keys($activeColIds, true);

        $boundedCells = [];
        $reviewRows = [];
        foreach ($cells as $cellIndex => $cell) {
            $originalRowIds = $this->integerList($cell['row_ids'] ?? []);
            $originalColIds = $this->integerList($cell['col_ids'] ?? []);
            $boundedRowIds = $this->orderIdsByMap(
                array_values(array_filter($originalRowIds, static fn (int $rowId): bool => isset($activeRows[$rowId]))),
                $rowOrder
            );
            $boundedColIds = $this->orderIdsByMap(
                array_values(array_filter($originalColIds, static fn (int $colId): bool => isset($activeCols[$colId]))),
                $colOrder
            );

            $missingRows = array_values(array_diff($originalRowIds, $boundedRowIds));
            $missingCols = array_values(array_diff($originalColIds, $boundedColIds));
            $active = $boundedRowIds !== [] && $boundedColIds !== [];
            $status = 'within_active_bands';
            if (!$active && $boundedRowIds === [] && $boundedColIds === []) {
                $status = 'excluded_inactive_row_and_column_bands';
            } elseif (!$active && $boundedRowIds === []) {
                $status = 'excluded_inactive_row_band';
            } elseif (!$active) {
                $status = 'excluded_inactive_column_band';
            } elseif ($missingRows !== [] || $missingCols !== []) {
                $status = 'trimmed_to_active_bands';
            }

            $reviewRow = [
                'cell_index' => $cellIndex,
                'text' => (string) ($cell['text'] ?? ''),
                'original_row_ids' => $originalRowIds,
                'original_col_ids' => $originalColIds,
                'bounded_row_ids' => $boundedRowIds,
                'bounded_col_ids' => $boundedColIds,
                'missing_row_ids' => $missingRows,
                'missing_col_ids' => $missingCols,
                'bbox' => $cell['bbox'],
                'status' => $status,
                'active' => $active,
                'upstream_assignment_retained' => $active && $status === 'within_active_bands',
            ];
            $reviewRow = $this->withSourceGeometryReviewFields($reviewRow, $cell);
            if ($active && $status === 'trimmed_to_active_bands') {
                $reviewRow['upstream_assignment_trimmed'] = true;
            }
            $reviewRows[] = $reviewRow;

            if (!$active) {
                continue;
            }

            $cell['row_ids'] = $boundedRowIds;
            $cell['col_ids'] = $boundedColIds;
            $cell['row_geometry_orders'] = array_map(
                static fn (int $rowId): ?int => $rowOrder[$rowId] ?? null,
                $boundedRowIds
            );
            $cell['col_geometry_orders'] = array_map(
                static fn (int $colId): ?int => $colOrder[$colId] ?? null,
                $boundedColIds
            );
            $boundedCells[] = $cell;
        }

        return [
            'cells' => $boundedCells,
            'review' => [
                'review_target' => 'table_assigned_band_geometry_boundary',
                'upstream_boundary' => 'tabled.schema.SpanTableCell.row_ids_col_ids_after_table_crop',
                'image_size' => $this->imageSize($imageSize),
                'active_row_ids' => $activeRowIds,
                'active_col_ids' => $activeColIds,
                'cell_count' => count($reviewRows),
                'active_cell_count' => count($boundedCells),
                'trimmed_cell_count' => count(array_filter(
                    $reviewRows,
                    static fn (array $row): bool => ($row['status'] ?? null) === 'trimmed_to_active_bands'
                )),
                'excluded_cell_count' => count(array_filter(
                    $reviewRows,
                    static fn (array $row): bool => isset($row['status']) && str_starts_with((string) $row['status'], 'excluded_')
                )),
                'cells' => $reviewRows,
            ],
        ];
    }

    /**
     * Saved tabled/marker results may already carry the SpanTableCell
     * row/column assignment. Trust that complete upstream assignment instead
     * of recomputing from geometry; formatRecognizedTables() still applies
     * the same table-crop positive-area boundary used by assignRowsColumns().
     * Raw detector cells still flow through assignRowsColumns().
     *
     * Current upstream table handoffs may serialize assignments as scalar
     * row_id/col_id plus rowspan/colspan instead of row_ids/col_ids arrays.
     * Normalize that shape into the canonical span arrays before filtering so
     * arbitrary detector band ids survive crop/band boundary review.
     *
     * @param array<string, mixed> $table
     * @return list<array<string, mixed>>|null
     */
    private function assignedCellsFromRecognizedTable(array $table): ?array
    {
        $table = $this->canonicalizedRecognizedTableGeometryAliases($table);
        $cells = $table['cells'] ?? null;
        if (!is_array($cells) || $cells === []) {
            return null;
        }

        $cells = array_values($cells);
        foreach ($cells as $cell) {
            if (!is_array($cell) || !$this->hasAssignedGridAnchor($cell)) {
                return null;
            }
        }

        $rows = isset($table['rows']) && is_array($table['rows'])
            ? $this->normalizeRowsOrCols($table['rows'], 'row_id')
            : [];
        $cols = isset($table['cols']) && is_array($table['cols'])
            ? $this->normalizeRowsOrCols($table['cols'], 'col_id')
            : [];

        return $this->normalizeAssignedCells($cells, $rows, $cols);
    }

    /**
     * @param array<string, mixed> $cell
     */
    private function hasAssignedGridAnchor(array $cell): bool
    {
        $rowIds = $cell['row_ids'] ?? null;
        $colIds = $cell['col_ids'] ?? null;

        if (is_array($rowIds)
            && is_array($colIds)
            && array_key_exists(0, $rowIds)
            && array_key_exists(0, $colIds)
            && $rowIds[0] !== null
            && $colIds[0] !== null) {
            return true;
        }

        return $this->nullableInteger($cell['row_id'] ?? null) !== null
            && $this->nullableInteger($cell['col_id'] ?? null) !== null;
    }

    /**
     * Upstream crops page images before tabled recognition, so tabled rows,
     * columns, and cells are normally table-crop-local. Supplied fixtures may
     * serialize those same model records in rendered page-image coordinates,
     * or in 1000-unit normalized page-image coordinates. Translate and
     * unnormalize only when the bundle declares that boundary explicitly.
     *
     * @param array<string, mixed> $table
     * @param array{width?: int|float, height?: int|float}|list<int|float> $imageSize
     * @return array{table: array<string, mixed>, review: array<string, mixed>|null}
     */
    private function localizeRecognizedTableGeometry(array $table, array $imageSize): array
    {
        $table = $this->canonicalizedRecognizedTableGeometryAliases($table);
        $spaces = [
            'rows' => $this->tableGeometryCoordinateSpace($table, 'rows'),
            'cols' => $this->tableGeometryCoordinateSpace($table, 'cols'),
            'cells' => $this->tableGeometryCoordinateSpace($table, 'cells'),
            'conflicts' => $this->tableGeometryCoordinateSpace($table, 'conflicts'),
        ];
        $recordSpaces = [
            'rows' => $this->tableRecordCoordinateSpaces($table, 'rows'),
            'cols' => $this->tableRecordCoordinateSpaces($table, 'cols'),
            'cells' => $this->tableRecordCoordinateSpaces($table, 'cells'),
            'conflicts' => $this->tableRecordCoordinateSpaces($table, 'conflicts'),
        ];
        $needsTranslation = false;
        $needsNormalization = false;
        $hasNormalizedPageImage = false;
        foreach ($spaces as $field => $space) {
            if ($this->isNormalizedPageImageCoordinateSpace($space)) {
                $needsTranslation = true;
                $needsNormalization = true;
                $hasNormalizedPageImage = true;
            }
            if ($this->isPageImageCoordinateSpace($space)) {
                $needsTranslation = true;
            }
            if ($this->isNormalizedTableCoordinateSpace($space)) {
                $needsNormalization = true;
            }
            foreach ($recordSpaces[$field] as $recordSpace) {
                if ($this->isNormalizedPageImageCoordinateSpace($recordSpace)) {
                    $needsTranslation = true;
                    $needsNormalization = true;
                    $hasNormalizedPageImage = true;
                }
                if ($this->isPageImageCoordinateSpace($recordSpace)) {
                    $needsTranslation = true;
                }
                if ($this->isNormalizedTableCoordinateSpace($recordSpace)) {
                    $needsNormalization = true;
                }
            }
        }
        if (!$needsTranslation && !$needsNormalization) {
            return [
                'table' => $table,
                'review' => null,
            ];
        }

        $cropBbox = $needsTranslation ? $this->tableCropBbox($table, $imageSize) : null;
        $cropBboxSource = $cropBbox === null ? null : $this->tableCropBboxSource($table, $imageSize);
        if ($needsTranslation && $cropBbox === null) {
            return [
                'table' => $table,
                'review' => [
                    'review_target' => 'table_recognition_coordinate_space_boundary',
                    'status' => 'missing_table_crop_bbox',
                    'source_coordinate_spaces' => $spaces,
                    'target_coordinate_space' => 'table_crop',
                    'translated' => false,
                ],
            ];
        }

        $size = $this->imageSize($imageSize);
        $pageImageNormalizationSize = $hasNormalizedPageImage
            ? $this->pageImageNormalizationSize($table, $imageSize, $size)
            : $size;
        $dx = $cropBbox === null ? 0.0 : -$cropBbox[0];
        $dy = $cropBbox === null ? 0.0 : -$cropBbox[1];
        $translatedCounts = [
            'rows' => 0,
            'cols' => 0,
            'cells' => 0,
            'conflicts' => 0,
        ];
        $normalizedCounts = [
            'rows' => 0,
            'cols' => 0,
            'cells' => 0,
            'conflicts' => 0,
        ];

        foreach (['rows', 'cols', 'cells'] as $field) {
            if (!isset($table[$field]) || !is_array($table[$field])) {
                continue;
            }

            $fieldSpace = $spaces[$field];
            $localizedRecords = [];
            $changed = false;
            foreach (array_values($table[$field]) as $record) {
                if (!is_array($record)) {
                    continue;
                }

                $recordSpace = $this->geometryRecordCoordinateSpace($record, $fieldSpace);
                if ($this->isNormalizedPageImageCoordinateSpace($recordSpace)) {
                    $localizedRecords[] = $this->localizedNormalizedPageImageGeometryRecord(
                        $record,
                        $pageImageNormalizationSize,
                        $dx,
                        $dy,
                        $recordSpace
                    );
                    $normalizedCounts[$field]++;
                    $translatedCounts[$field]++;
                    $changed = true;
                    continue;
                }
                if ($this->isNormalizedTableCoordinateSpace($recordSpace)) {
                    $localizedRecords[] = $this->unnormalizedGeometryRecord($record, $size, $recordSpace);
                    $normalizedCounts[$field]++;
                    $changed = true;
                    continue;
                }
                if ($this->isPageImageCoordinateSpace($recordSpace)) {
                    $localizedRecords[] = $this->translatedGeometryRecord($record, $dx, $dy, $recordSpace);
                    $translatedCounts[$field]++;
                    $changed = true;
                    continue;
                }

                $localizedRecords[] = $record;
            }
            if (!$changed) {
                continue;
            }
            $table[$field] = $localizedRecords;
            $table = $this->syncRecognizedTableGeometryAlias($table, $field);
            $table = $this->withTableGeometryCoordinateSpace($table, $field, 'table_crop');
        }

        if (isset($table['ocr_grid_border_conflicts']) && is_array($table['ocr_grid_border_conflicts'])) {
            if ($this->isNormalizedPageImageCoordinateSpace($spaces['conflicts']) || $this->recordSpacesIncludeNormalizedPageImage($recordSpaces['conflicts'])) {
                $localizedConflicts = $this->localizedNormalizedPageImageOcrGridBorderConflicts(
                    $table['ocr_grid_border_conflicts'],
                    $pageImageNormalizationSize,
                    $dx,
                    $dy,
                    $spaces['conflicts']
                );
                $normalizedCounts['conflicts'] += $localizedConflicts['count'];
                $translatedCounts['conflicts'] += $localizedConflicts['count'];
                $table['ocr_grid_border_conflicts'] = $localizedConflicts['conflicts'];
            }
            if ($this->isNormalizedTableCoordinateSpace($spaces['conflicts']) || $this->recordSpacesIncludeNormalized($recordSpaces['conflicts'])) {
                $localizedConflicts = $this->unnormalizedOcrGridBorderConflicts(
                    $table['ocr_grid_border_conflicts'],
                    $size,
                    $spaces['conflicts']
                );
                $normalizedCounts['conflicts'] += $localizedConflicts['count'];
                $table['ocr_grid_border_conflicts'] = $localizedConflicts['conflicts'];
            }
            if ($this->isPageImageCoordinateSpace($spaces['conflicts']) || $this->recordSpacesIncludePageImage($recordSpaces['conflicts'])) {
                $localizedConflicts = $this->translatedOcrGridBorderConflicts(
                    $table['ocr_grid_border_conflicts'],
                    $dx,
                    $dy,
                    $spaces['conflicts']
                );
                $translatedCounts['conflicts'] += $localizedConflicts['count'];
                $table['ocr_grid_border_conflicts'] = $localizedConflicts['conflicts'];
            }
            $table = $this->withTableGeometryCoordinateSpace($table, 'conflicts', 'table_crop');
        }

        $table['coordinate_space'] = 'table_crop';
        if ($cropBbox !== null) {
            $table['table_crop_bbox'] = $cropBbox;
        }

        $review = [
            'review_target' => 'table_recognition_coordinate_space_boundary',
            'upstream_boundary' => 'marker.tables.table.get_table_boxes crop + tabled.assignment.assign_rows_columns',
            'status' => $this->coordinateSpaceReviewStatus($needsTranslation, $needsNormalization),
            'source_coordinate_spaces' => $spaces,
            'target_coordinate_space' => 'table_crop',
            'table_crop_size' => $size,
            'translated_row_band_count' => $translatedCounts['rows'],
            'translated_col_band_count' => $translatedCounts['cols'],
            'translated_cell_count' => $translatedCounts['cells'],
            'translated_conflict_count' => $translatedCounts['conflicts'],
            'normalized_row_band_count' => $normalizedCounts['rows'],
            'normalized_col_band_count' => $normalizedCounts['cols'],
            'normalized_cell_count' => $normalizedCounts['cells'],
            'normalized_conflict_count' => $normalizedCounts['conflicts'],
            'translated' => $needsTranslation,
            'normalized' => $needsNormalization,
            'table_local_default_preserved' => true,
        ];
        if ($cropBbox !== null) {
            $review['table_bbox'] = $cropBbox;
            if ($cropBboxSource !== null) {
                $review['table_bbox_source'] = $cropBboxSource;
            }
            $review['translation'] = ['x' => $dx, 'y' => $dy];
        }
        if (isset($imageSize['image_size_source']) && is_scalar($imageSize['image_size_source'])) {
            $review['image_size_source'] = (string) $imageSize['image_size_source'];
        }
        if ($needsNormalization) {
            $review['normalization_scale'] = [
                'x' => (float) $size['width'] / 1000.0,
                'y' => (float) $size['height'] / 1000.0,
            ];
            if ($hasNormalizedPageImage) {
                $review['page_image_normalization_size'] = $pageImageNormalizationSize;
                $review['page_image_normalization_scale'] = [
                    'x' => (float) $pageImageNormalizationSize['width'] / 1000.0,
                    'y' => (float) $pageImageNormalizationSize['height'] / 1000.0,
                ];
            }
        }
        $recordSpaceReview = $this->recordCoordinateSpaceReview($recordSpaces);
        if ($recordSpaceReview !== []) {
            $review['source_record_coordinate_spaces'] = $recordSpaceReview;
        }

        return [
            'table' => $table,
            'review' => $review,
        ];
    }

    private function coordinateSpaceReviewStatus(bool $translated, bool $normalized): string
    {
        if ($translated && $normalized) {
            return 'translated_and_normalized_to_table_crop';
        }
        if ($translated) {
            return 'translated_to_table_crop';
        }

        return 'normalized_to_table_crop';
    }

    /**
     * @param array<string, mixed> $table
     */
    private function tableGeometryCoordinateSpace(array $table, string $field): string
    {
        foreach ($this->tableGeometryCoordinateSpaceKeys($field) as $key) {
            if (isset($table[$key]) && is_scalar($table[$key])) {
                return $this->normalizeCoordinateSpace((string) $table[$key]);
            }
        }

        foreach (['geometry_coordinate_space', 'bbox_coordinate_space', 'coordinate_space', 'geometry_space'] as $key) {
            if (isset($table[$key]) && is_scalar($table[$key])) {
                return $this->normalizeCoordinateSpace((string) $table[$key]);
            }
        }

        return 'table_crop';
    }

    /**
     * @return list<string>
     */
    private function tableRecordCoordinateSpaces(array $table, string $field): array
    {
        $records = $field === 'conflicts'
            ? ($table['ocr_grid_border_conflicts'] ?? null)
            : ($table[$field] ?? null);
        if (!is_array($records)) {
            return [];
        }

        $spaces = [];
        foreach ($records as $record) {
            if (!is_array($record)) {
                continue;
            }
            $space = $this->explicitGeometryRecordCoordinateSpace($record)
                ?? $this->sourceFallbackCoordinateSpaceFromRecord($record);
            if ($space !== null) {
                $spaces[] = $space;
            }
        }

        return $spaces;
    }

    private function geometryRecordCoordinateSpace(array $record, string $fallback): string
    {
        return $this->explicitGeometryRecordCoordinateSpace($record)
            ?? $this->sourceFallbackCoordinateSpaceFromRecord($record)
            ?? $fallback;
    }

    private function explicitGeometryRecordCoordinateSpace(array $record): ?string
    {
        foreach (['coordinate_space', 'bbox_coordinate_space', 'geometry_coordinate_space', 'geometry_space'] as $key) {
            if (isset($record[$key]) && is_scalar($record[$key])) {
                return $this->normalizeCoordinateSpace((string) $record[$key]);
            }
        }

        return null;
    }

    /**
     * @param array<string, mixed> $record
     */
    private function sourceFallbackCoordinateSpaceFromRecord(array $record): ?string
    {
        if (!$this->usesSourceBboxFallback($record)) {
            return null;
        }
        if (isset($record['source_coordinate_space']) && is_scalar($record['source_coordinate_space'])) {
            return $this->normalizeCoordinateSpace((string) $record['source_coordinate_space']);
        }

        return null;
    }

    /**
     * @param array<string|int, mixed> $record
     */
    private function usesSourceBboxFallback(array $record): bool
    {
        return $this->bboxFromValue($record['bbox'] ?? null) === null
            && $this->bboxFromNamedFields($record) === null
            && $this->polygonBboxFromRecord($record) === null
            && $this->sourceBboxFromRecord($record) !== null;
    }

    /**
     * @param list<string> $spaces
     */
    private function recordSpacesIncludePageImage(array $spaces): bool
    {
        foreach ($spaces as $space) {
            if ($this->isPageImageCoordinateSpace($space)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param list<string> $spaces
     */
    private function recordSpacesIncludeNormalized(array $spaces): bool
    {
        foreach ($spaces as $space) {
            if ($this->isNormalizedTableCoordinateSpace($space)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param list<string> $spaces
     */
    private function recordSpacesIncludeNormalizedPageImage(array $spaces): bool
    {
        foreach ($spaces as $space) {
            if ($this->isNormalizedPageImageCoordinateSpace($space)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<string, list<string>> $recordSpaces
     * @return array<string, array<string, int>>
     */
    private function recordCoordinateSpaceReview(array $recordSpaces): array
    {
        $review = [];
        foreach ($recordSpaces as $field => $spaces) {
            if ($spaces === []) {
                continue;
            }
            $counts = [];
            foreach ($spaces as $space) {
                $counts[$space] = ($counts[$space] ?? 0) + 1;
            }
            ksort($counts);
            $review[$field] = $counts;
        }

        return $review;
    }

    /**
     * @return list<string>
     */
    private function tableGeometryCoordinateSpaceKeys(string $field): array
    {
        $keys = [
            $field . '_coordinate_space',
            rtrim($field, 's') . '_coordinate_space',
            $field . '_geometry_space',
            rtrim($field, 's') . '_geometry_space',
        ];

        if ($field === 'conflicts') {
            return [
                'ocr_grid_border_conflicts_coordinate_space',
                'ocr_grid_border_conflict_coordinate_space',
                'ocr_grid_border_conflicts_geometry_space',
                'ocr_grid_border_conflict_geometry_space',
                'grid_border_conflicts_coordinate_space',
                'grid_border_conflict_coordinate_space',
                'grid_border_conflicts_geometry_space',
                'grid_border_conflict_geometry_space',
                ...$keys,
            ];
        }
        if ($field === 'rows') {
            return [
                'row_bboxes_coordinate_space',
                'row_bbox_coordinate_space',
                'row_boxes_coordinate_space',
                'row_box_coordinate_space',
                'row_bounds_coordinate_space',
                'row_bound_coordinate_space',
                'row_bboxes_geometry_space',
                'row_bbox_geometry_space',
                'row_boxes_geometry_space',
                'row_box_geometry_space',
                'row_bounds_geometry_space',
                'row_bound_geometry_space',
                ...$keys,
            ];
        }
        if ($field === 'cols') {
            return [
                'columns_coordinate_space',
                'column_coordinate_space',
                'column_bboxes_coordinate_space',
                'column_bbox_coordinate_space',
                'col_bboxes_coordinate_space',
                'col_bbox_coordinate_space',
                'column_boxes_coordinate_space',
                'column_box_coordinate_space',
                'col_boxes_coordinate_space',
                'col_box_coordinate_space',
                'columns_geometry_space',
                'column_geometry_space',
                'column_bboxes_geometry_space',
                'column_bbox_geometry_space',
                'col_bboxes_geometry_space',
                'col_bbox_geometry_space',
                'column_boxes_geometry_space',
                'column_box_geometry_space',
                'col_boxes_geometry_space',
                'col_box_geometry_space',
                ...$keys,
            ];
        }

        return $keys;
    }

    /**
     * Tabled sidecars commonly serialize flat row/column bands as
     * row_bboxes/columns. Keep those aliases available while adding canonical
     * rows/cols keys before assignment and WordPress grid review.
     *
     * @param array<string, mixed> $table
     * @return array<string, mixed>
     */
    private function canonicalizedRecognizedTableGeometryAliases(array $table): array
    {
        $table = $this->canonicalizedRecognizedTableGeometryAlias(
            $table,
            'rows',
            'rows_source_alias',
            ['row_bboxes', 'row_boxes', 'row_bounds']
        );

        return $this->canonicalizedRecognizedTableGeometryAlias(
            $table,
            'cols',
            'cols_source_alias',
            ['columns', 'column_bboxes', 'col_bboxes', 'column_boxes', 'col_boxes']
        );
    }

    /**
     * @param array<string, mixed> $table
     * @param list<string> $aliases
     * @return array<string, mixed>
     */
    private function canonicalizedRecognizedTableGeometryAlias(array $table, string $canonicalKey, string $sourceAliasKey, array $aliases): array
    {
        if (isset($table[$canonicalKey]) && is_array($table[$canonicalKey])) {
            return $table;
        }

        foreach ($aliases as $alias) {
            if (!isset($table[$alias]) || !is_array($table[$alias])) {
                continue;
            }

            $table[$canonicalKey] = $table[$alias];
            if (!isset($table[$sourceAliasKey])) {
                $table[$sourceAliasKey] = $alias;
            }

            return $table;
        }

        return $table;
    }

    /**
     * @param array<string, mixed> $table
     * @return array<string, mixed>
     */
    private function syncRecognizedTableGeometryAlias(array $table, string $field): array
    {
        $sourceAliasKey = $field . '_source_alias';
        $alias = $table[$sourceAliasKey] ?? null;
        if (!is_string($alias) || !isset($table[$field]) || !is_array($table[$field]) || !array_key_exists($alias, $table)) {
            return $table;
        }

        $table[$alias] = $table[$field];

        return $table;
    }

    /**
     * @param array<string, mixed> $table
     * @return array<string, mixed>
     */
    private function withTableGeometryCoordinateSpace(array $table, string $field, string $space): array
    {
        foreach ($this->tableGeometryCoordinateSpaceKeys($field) as $key) {
            if (array_key_exists($key, $table)) {
                $table[$key] = $space;
            }
        }
        $table[$field . '_coordinate_space'] = $space;

        return $table;
    }

    private function normalizeCoordinateSpace(string $space): string
    {
        $space = strtolower(trim($space));

        return str_replace(['-', ' '], '_', $space);
    }

    private function isPageImageCoordinateSpace(string $space): bool
    {
        return in_array($this->normalizeCoordinateSpace($space), [
            'page',
            'page_image',
            'rendered_page',
            'full_page',
            'pdf_page',
            'highres_page',
            'image_bbox_relative',
            'relative_image_bbox',
            'image_bbox_local',
            'local_image_bbox',
            'saved_image_bbox',
            'saved_image_bbox_relative',
        ], true);
    }

    private function isNormalizedPageImageCoordinateSpace(string $space): bool
    {
        return in_array($this->normalizeCoordinateSpace($space), [
            'normalized_page',
            'page_normalized',
            'normalized_page_image',
            'page_image_normalized',
            'normalized_rendered_page',
            'rendered_page_normalized',
            'normalized_full_page',
            'full_page_normalized',
            'normalized_pdf_page',
            'pdf_page_normalized',
            'normalized_highres_page',
            'highres_page_normalized',
        ], true);
    }

    private function isNormalizedTableCoordinateSpace(string $space): bool
    {
        return in_array($this->normalizeCoordinateSpace($space), [
            'normalized',
            'normalized_table',
            'table_normalized',
            'normalized_table_crop',
            'table_crop_normalized',
            'normalized_crop',
            'crop_normalized',
        ], true);
    }

    /**
     * @param array<string, mixed> $table
     * @param array{width?: int|float, height?: int|float}|list<int|float> $imageSize
     * @return list<float>|null
     */
    private function tableCropBbox(array $table, array $imageSize): ?array
    {
        $candidate = $this->tableCropBboxCandidate($table, $imageSize);

        return $candidate['bbox'] ?? null;
    }

    /**
     * @param array<string, mixed> $table
     * @param array{width?: int|float, height?: int|float}|list<int|float> $imageSize
     */
    private function tableCropBboxSource(array $table, array $imageSize): ?string
    {
        $candidate = $this->tableCropBboxCandidate($table, $imageSize);
        if ($candidate !== null) {
            return $candidate['source'];
        }

        return null;
    }

    /**
     * Saved recognition sidecars sometimes wrap the table crop plan instead of
     * repeating it as a top-level table bbox. Upstream still crops the rendered
     * page image before tabled assignment, so those nested crop records are
     * authoritative for localizing rows, columns, and cells.
     *
     * @param array<string, mixed> $table
     * @param array{width?: int|float, height?: int|float}|list<int|float> $imageSize
     * @return array{bbox: list<float>, source: string}|null
     */
    private function tableCropBboxCandidate(array $table, array $imageSize): ?array
    {
        foreach (['table_bbox', 'table_crop_bbox', 'crop_bbox', 'highres_bbox', 'page_table_bbox'] as $key) {
            if (isset($table[$key]) && $this->bboxFromGeometryValue($table[$key]) !== null) {
                return [
                    'bbox' => $this->bboxFromGeometryValue($table[$key]),
                    'source' => $key,
                ];
            }
            if (isset($imageSize[$key]) && $this->bboxFromGeometryValue($imageSize[$key]) !== null) {
                $source = $key;
                if ($key === 'table_bbox' && isset($imageSize['table_bbox_source']) && is_scalar($imageSize['table_bbox_source'])) {
                    $source = (string) $imageSize['table_bbox_source'];
                }

                return [
                    'bbox' => $this->bboxFromGeometryValue($imageSize[$key]),
                    'source' => $source,
                ];
            }
        }

        $polygonBbox = $this->polygonBboxFromRecord($table);
        if ($polygonBbox !== null) {
            $source = $this->polygonCoordinateSourceFromRecord($table);
            if ($source !== null) {
                return [
                    'bbox' => $polygonBbox,
                    'source' => $source,
                ];
            }
        }

        $tableNested = $this->nestedTableCropBboxCandidate($table);
        if ($tableNested !== null) {
            return $tableNested;
        }

        $imageNested = $this->nestedTableCropBboxCandidate($imageSize);
        if ($imageNested !== null) {
            return $imageNested;
        }

        if (isset($table['bbox']) && $this->bboxFromGeometryValue($table['bbox']) !== null) {
            return [
                'bbox' => $this->bboxFromGeometryValue($table['bbox']),
                'source' => is_array($table['bbox'])
                ? (
                    $this->bboxNamedFieldSource($table['bbox'])
                    ?? $this->bboxWrappedFieldSource($table['bbox'])
                    ?? $this->bboxPolygonValueSource($table['bbox'])
                    ?? 'bbox_array'
                )
                : 'bbox_array',
            ];
        }

        $wrappedBbox = $this->bboxFromWrappedValue($table);
        if ($wrappedBbox !== null) {
            return [
                'bbox' => $wrappedBbox,
                'source' => $this->bboxWrappedFieldSource($table) ?? 'bbox_array',
            ];
        }

        return null;
    }

    /**
     * @param array<string|int, mixed> $source
     * @return array{bbox: list<float>, source: string}|null
     */
    private function nestedTableCropBboxCandidate(array $source): ?array
    {
        foreach (['table_image', 'table_crop', 'crop', 'crop_image', 'table_region', 'table_box'] as $containerKey) {
            $container = $source[$containerKey] ?? null;
            if (!is_array($container)) {
                continue;
            }

            foreach (['table_bbox', 'table_crop_bbox', 'crop_bbox', 'highres_bbox', 'page_table_bbox'] as $bboxKey) {
                if (!array_key_exists($bboxKey, $container)) {
                    continue;
                }

                $bbox = $this->bboxFromGeometryValue($container[$bboxKey]);
                if ($bbox !== null) {
                    return [
                        'bbox' => $bbox,
                        'source' => $containerKey . '.' . $bboxKey,
                    ];
                }
            }

            $polygonBbox = $this->polygonBboxFromRecord($container);
            if ($polygonBbox !== null) {
                $sourceKey = $this->polygonCoordinateSourceFromRecord($container);
                if ($sourceKey !== null) {
                    return [
                        'bbox' => $polygonBbox,
                        'source' => $containerKey . '.' . $sourceKey,
                    ];
                }
            }

            foreach (array_merge(['source_bbox'], $this->wrappedGeometryKeys()) as $bboxKey) {
                if (!array_key_exists($bboxKey, $container)) {
                    continue;
                }

                $bbox = $this->bboxFromGeometryValue($container[$bboxKey]);
                if ($bbox !== null) {
                    return [
                        'bbox' => $bbox,
                        'source' => $containerKey . '.' . $bboxKey,
                    ];
                }
            }
        }

        return null;
    }

    /**
     * @return list<float>|null
     */
    private function bboxFromGeometryValue(mixed $value): ?array
    {
        if (!is_array($value)) {
            return null;
        }

        return $this->bboxFromValue($value)
            ?? $this->polygonBboxFromRecord($value)
            ?? $this->polygonBbox($value)
            ?? $this->sourceBboxFromRecord($value);
    }

    /**
     * @param array<string, mixed> $table
     * @param array{width?: int|float, height?: int|float}|list<int|float> $imageSize
     * @param array{width: int, height: int} $fallback
     * @return array{width: int, height: int}
     */
    private function pageImageNormalizationSize(array $table, array $imageSize, array $fallback): array
    {
        foreach (['image_bbox', 'page_image_bbox', 'rendered_image_bbox'] as $key) {
            $bbox = isset($table[$key]) ? $this->bboxFromValue($table[$key]) : null;
            if ($bbox === null && isset($imageSize[$key])) {
                $bbox = $this->bboxFromValue($imageSize[$key]);
            }
            if ($bbox === null) {
                continue;
            }

            $fromExtent = $this->imageSizeFromBboxExtent($bbox);
            if ($fromExtent !== null) {
                return $fromExtent;
            }
        }

        $provided = $this->nullableImageSize($imageSize);

        return $provided ?? $fallback;
    }

    /**
     * @param array<string, mixed> $record
     * @return array<string, mixed>
     */
    private function translatedGeometryRecord(array $record, float $dx, float $dy, string $sourceCoordinateSpace): array
    {
        $sourceBbox = $this->bboxFromRecord($record);
        $sourceCoordinateSource = $this->bboxCoordinateSourceFromRecord($record);
        $sourceEndpointOrderNormalized = $this->bboxEndpointOrderNormalizedFromRecord($record);
        $record['source_bbox'] = $sourceBbox;
        $record['source_coordinate_space'] = $sourceCoordinateSpace;
        $record['source_coordinate_source'] = $sourceCoordinateSource;
        $record['source_endpoint_order_normalized'] = $sourceEndpointOrderNormalized;
        $record['bbox'] = $this->translatedBbox($sourceBbox, $dx, $dy);
        $record = $this->withGeometryRecordCoordinateSpace($record, 'table_crop');

        return $record;
    }

    /**
     * @param array<string, mixed> $record
     * @param array{width: int, height: int} $pageImageSize
     * @return array<string, mixed>
     */
    private function localizedNormalizedPageImageGeometryRecord(
        array $record,
        array $pageImageSize,
        float $dx,
        float $dy,
        string $sourceCoordinateSpace
    ): array {
        $sourceBbox = $this->bboxFromRecord($record);
        $sourceCoordinateSource = $this->bboxCoordinateSourceFromRecord($record);
        $sourceEndpointOrderNormalized = $this->bboxEndpointOrderNormalizedFromRecord($record);
        $pageImageBbox = $this->unnormalizedTableBbox($sourceBbox, $pageImageSize);
        $record['source_bbox'] = $sourceBbox;
        $record['source_page_image_bbox'] = $pageImageBbox;
        $record['source_coordinate_space'] = $sourceCoordinateSpace;
        $record['source_coordinate_source'] = $sourceCoordinateSource;
        $record['source_endpoint_order_normalized'] = $sourceEndpointOrderNormalized;
        $record['bbox'] = $this->translatedBbox($pageImageBbox, $dx, $dy);
        $record = $this->withGeometryRecordCoordinateSpace($record, 'table_crop');

        return $record;
    }

    /**
     * @param array<string, mixed> $record
     * @param array{width: int, height: int} $imageSize
     * @return array<string, mixed>
     */
    private function unnormalizedGeometryRecord(array $record, array $imageSize, string $sourceCoordinateSpace): array
    {
        $sourceBbox = $this->bboxFromRecord($record);
        $sourceCoordinateSource = $this->bboxCoordinateSourceFromRecord($record);
        $sourceEndpointOrderNormalized = $this->bboxEndpointOrderNormalizedFromRecord($record);
        $record['source_bbox'] = $sourceBbox;
        $record['source_coordinate_space'] = $sourceCoordinateSpace;
        $record['source_coordinate_source'] = $sourceCoordinateSource;
        $record['source_endpoint_order_normalized'] = $sourceEndpointOrderNormalized;
        $record['bbox'] = $this->unnormalizedTableBbox($sourceBbox, $imageSize);
        $record = $this->withGeometryRecordCoordinateSpace($record, 'table_crop');

        return $record;
    }

    /**
     * @param array<string, mixed> $record
     * @return array<string, mixed>
     */
    private function withGeometryRecordCoordinateSpace(array $record, string $space): array
    {
        foreach (['coordinate_space', 'bbox_coordinate_space', 'geometry_coordinate_space', 'geometry_space'] as $key) {
            if (array_key_exists($key, $record)) {
                $record[$key] = $space;
            }
        }
        $record['coordinate_space'] = $space;

        return $record;
    }

    /**
     * @param list<array<string, mixed>> $conflicts
     * @return array{conflicts: list<array<string, mixed>>, count: int}
     */
    private function translatedOcrGridBorderConflicts(array $conflicts, float $dx, float $dy, string $sourceCoordinateSpace): array
    {
        $translated = [];
        $count = 0;
        foreach (array_values($conflicts) as $conflict) {
            if (!is_array($conflict)) {
                continue;
            }
            $recordSpace = $this->geometryRecordCoordinateSpace($conflict, $sourceCoordinateSpace);
            if (!$this->isPageImageCoordinateSpace($recordSpace)) {
                $translated[] = $conflict;
                continue;
            }

            $bbox = $this->nullableBboxFromRecord($conflict);
            if ($bbox !== null) {
                $conflict['source_bbox'] = $bbox;
                $conflict['source_coordinate_source'] = $this->bboxCoordinateSourceFromRecord($conflict);
                $conflict['source_endpoint_order_normalized'] = $this->bboxEndpointOrderNormalizedFromRecord($conflict);
                $conflict['bbox'] = $this->translatedBbox($bbox, $dx, $dy);
            }
            if (isset($conflict['candidate_cell_bboxes']) && is_array($conflict['candidate_cell_bboxes'])) {
                $candidateBboxes = [];
                foreach ($conflict['candidate_cell_bboxes'] as $bbox) {
                    $normalized = $this->bboxFromGeometryValue($bbox);
                    if ($normalized !== null) {
                        $candidateBboxes[] = $this->translatedBbox($normalized, $dx, $dy);
                    }
                }
                $conflict['source_candidate_cell_bboxes'] = $conflict['candidate_cell_bboxes'];
                $conflict['candidate_cell_bboxes'] = $candidateBboxes;
            }

            $conflict['source_coordinate_space'] = $recordSpace;
            $conflict = $this->withGeometryRecordCoordinateSpace($conflict, 'table_crop');
            $translated[] = $conflict;
            $count++;
        }

        return [
            'conflicts' => $translated,
            'count' => $count,
        ];
    }

    /**
     * @param list<array<string, mixed>> $conflicts
     * @param array{width: int, height: int} $pageImageSize
     * @return array{conflicts: list<array<string, mixed>>, count: int}
     */
    private function localizedNormalizedPageImageOcrGridBorderConflicts(
        array $conflicts,
        array $pageImageSize,
        float $dx,
        float $dy,
        string $sourceCoordinateSpace
    ): array {
        $localized = [];
        $count = 0;
        foreach (array_values($conflicts) as $conflict) {
            if (!is_array($conflict)) {
                continue;
            }
            $recordSpace = $this->geometryRecordCoordinateSpace($conflict, $sourceCoordinateSpace);
            if (!$this->isNormalizedPageImageCoordinateSpace($recordSpace)) {
                $localized[] = $conflict;
                continue;
            }

            $bbox = $this->nullableBboxFromRecord($conflict);
            if ($bbox !== null) {
                $pageImageBbox = $this->unnormalizedTableBbox($bbox, $pageImageSize);
                $conflict['source_bbox'] = $bbox;
                $conflict['source_page_image_bbox'] = $pageImageBbox;
                $conflict['source_coordinate_source'] = $this->bboxCoordinateSourceFromRecord($conflict);
                $conflict['source_endpoint_order_normalized'] = $this->bboxEndpointOrderNormalizedFromRecord($conflict);
                $conflict['bbox'] = $this->translatedBbox($pageImageBbox, $dx, $dy);
            }
            if (isset($conflict['candidate_cell_bboxes']) && is_array($conflict['candidate_cell_bboxes'])) {
                $candidateBboxes = [];
                $candidatePageImageBboxes = [];
                foreach ($conflict['candidate_cell_bboxes'] as $bbox) {
                    $normalizedBbox = $this->bboxFromGeometryValue($bbox);
                    if ($normalizedBbox !== null) {
                        $pageImageBbox = $this->unnormalizedTableBbox($normalizedBbox, $pageImageSize);
                        $candidatePageImageBboxes[] = $pageImageBbox;
                        $candidateBboxes[] = $this->translatedBbox($pageImageBbox, $dx, $dy);
                    }
                }
                $conflict['source_candidate_cell_bboxes'] = $conflict['candidate_cell_bboxes'];
                $conflict['source_candidate_page_image_bboxes'] = $candidatePageImageBboxes;
                $conflict['candidate_cell_bboxes'] = $candidateBboxes;
            }

            $conflict['source_coordinate_space'] = $recordSpace;
            $conflict = $this->withGeometryRecordCoordinateSpace($conflict, 'table_crop');
            $localized[] = $conflict;
            $count++;
        }

        return [
            'conflicts' => $localized,
            'count' => $count,
        ];
    }

    /**
     * @param list<array<string, mixed>> $conflicts
     * @param array{width: int, height: int} $imageSize
     * @return array{conflicts: list<array<string, mixed>>, count: int}
     */
    private function unnormalizedOcrGridBorderConflicts(array $conflicts, array $imageSize, string $sourceCoordinateSpace): array
    {
        $normalized = [];
        $count = 0;
        foreach (array_values($conflicts) as $conflict) {
            if (!is_array($conflict)) {
                continue;
            }
            $recordSpace = $this->geometryRecordCoordinateSpace($conflict, $sourceCoordinateSpace);
            if (!$this->isNormalizedTableCoordinateSpace($recordSpace)) {
                $normalized[] = $conflict;
                continue;
            }

            $bbox = $this->nullableBboxFromRecord($conflict);
            if ($bbox !== null) {
                $conflict['source_bbox'] = $bbox;
                $conflict['source_coordinate_source'] = $this->bboxCoordinateSourceFromRecord($conflict);
                $conflict['source_endpoint_order_normalized'] = $this->bboxEndpointOrderNormalizedFromRecord($conflict);
                $conflict['bbox'] = $this->unnormalizedTableBbox($bbox, $imageSize);
            }
            if (isset($conflict['candidate_cell_bboxes']) && is_array($conflict['candidate_cell_bboxes'])) {
                $candidateBboxes = [];
                foreach ($conflict['candidate_cell_bboxes'] as $bbox) {
                    $normalizedBbox = $this->bboxFromGeometryValue($bbox);
                    if ($normalizedBbox !== null) {
                        $candidateBboxes[] = $this->unnormalizedTableBbox($normalizedBbox, $imageSize);
                    }
                }
                $conflict['source_candidate_cell_bboxes'] = $conflict['candidate_cell_bboxes'];
                $conflict['candidate_cell_bboxes'] = $candidateBboxes;
            }

            $conflict['source_coordinate_space'] = $recordSpace;
            $conflict = $this->withGeometryRecordCoordinateSpace($conflict, 'table_crop');
            $normalized[] = $conflict;
            $count++;
        }

        return [
            'conflicts' => $normalized,
            'count' => $count,
        ];
    }

    /**
     * @param list<float> $bbox
     * @return list<float>
     */
    private function translatedBbox(array $bbox, float $dx, float $dy): array
    {
        return [
            $bbox[0] + $dx,
            $bbox[1] + $dy,
            $bbox[2] + $dx,
            $bbox[3] + $dy,
        ];
    }

    /**
     * @param list<float> $bbox
     * @param array{width: int, height: int} $imageSize
     * @return list<float>
     */
    private function unnormalizedTableBbox(array $bbox, array $imageSize): array
    {
        return [
            ((float) $imageSize['width']) * ($bbox[0] / 1000.0),
            ((float) $imageSize['height']) * ($bbox[1] / 1000.0),
            ((float) $imageSize['width']) * ($bbox[2] / 1000.0),
            ((float) $imageSize['height']) * ($bbox[3] / 1000.0),
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
            $blocks = array_values(array_filter($textLine['table_blocks'], static fn (mixed $block): bool => is_array($block)));
            if ($this->tableBlocksAreCropLocal($textLine)) {
                return $this->precomputedTableBlocks($blocks, $tableBbox);
            }

            return $this->filterTextBlocksToTable(
                $blocks,
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
     * Serialized output from surya.input.pdflines::get_table_blocks is already
     * relative to the input table crop. Full-page pdftext payloads keep using
     * the legacy filter path unless the caller marks this coordinate space.
     *
     * @param array<string, mixed> $textLine
     */
    private function tableBlocksAreCropLocal(array $textLine): bool
    {
        foreach (['table_blocks_coordinate_space', 'table_blocks_geometry_space'] as $key) {
            if (!isset($textLine[$key]) || !is_scalar($textLine[$key])) {
                continue;
            }

            return in_array($this->normalizeCoordinateSpace((string) $textLine[$key]), [
                'table_crop',
                'crop',
                'table_local',
                'local_table',
                'relative_table',
                'table_relative',
                'precomputed_get_table_blocks',
            ], true);
        }

        return false;
    }

    /**
     * @param list<array<string, mixed>> $blocks
     * @param list<float> $tableBbox
     * @return array{cells: list<array<string, mixed>>, boundary_review: array<string, mixed>|null}
     */
    private function precomputedTableBlocks(array $blocks, array $tableBbox): array
    {
        $cells = $this->sortTextCells($this->normalizeCells($blocks));

        return [
            'cells' => $cells,
            'boundary_review' => $this->tableTextCellBoundaryReview($cells, $tableBbox, 'precomputed_get_table_blocks'),
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

        return $this->canonicalBbox($out);
    }

    /**
     * @param array<string, mixed> $ocrLine
     * @return list<float>|null
     */
    private function ocrLineBbox(array $ocrLine): ?array
    {
        return $this->polygonBbox($ocrLine['polygon'] ?? null)
            ?? $this->bboxFromValue($ocrLine['bbox'] ?? null)
            ?? $this->bboxFromNamedFields($ocrLine)
            ?? null;
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
        if (!is_array($polygon)) {
            return null;
        }

        $points = $this->polygonPoints($polygon);
        if ($points === null) {
            return null;
        }

        $xs = [];
        $ys = [];
        foreach ($points as [$x, $y]) {
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
     * Supplied sidecar adapters sometimes serialize the same four-corner
     * geometry under generic point-list keys. Treat those keys as polygon
     * aliases while keeping an explicit bbox authoritative when present.
     *
     * @param array<string|int, mixed> $record
     * @return list<float>|null
     */
    private function polygonBboxFromRecord(array $record): ?array
    {
        foreach ($this->polygonGeometryKeys() as $key) {
            if (!array_key_exists($key, $record)) {
                continue;
            }

            $bbox = $this->polygonBbox($record[$key]);
            if ($bbox !== null) {
                return $bbox;
            }
        }

        return null;
    }

    /**
     * @param array<string|int, mixed> $record
     */
    private function polygonCoordinateSourceFromRecord(array $record): ?string
    {
        foreach ($this->polygonGeometryKeys() as $key) {
            if (!array_key_exists($key, $record)) {
                continue;
            }
            if ($this->polygonBbox($record[$key]) !== null) {
                return $key === 'polygon' ? 'polygon' : 'polygon_' . $key;
            }
        }

        return null;
    }

    /**
     * @param array<string|int, mixed> $polygon
     */
    private function polygonValueCoordinateSource(array $polygon): ?string
    {
        if ($this->polygonBbox($polygon) === null) {
            return null;
        }

        return count(array_values($polygon)) === 8 ? 'polygon_flat_coordinates' : 'polygon_points';
    }

    /**
     * @return list<string>
     */
    private function polygonGeometryKeys(): array
    {
        return [
            'polygon',
            'points',
            'vertices',
            'quad',
            'quadrilateral',
            'quadrilateral_points',
        ];
    }

    /**
     * @param array<string|int, mixed> $polygon
     * @return list<array{0: float, 1: float}>|null
     */
    private function polygonPoints(array $polygon): ?array
    {
        $values = array_values($polygon);
        if (count($values) === 8) {
            $points = [];
            for ($idx = 0; $idx < 8; $idx += 2) {
                $x = $this->numericScalar($values[$idx]);
                $y = $this->numericScalar($values[$idx + 1]);
                if ($x === null || $y === null) {
                    return null;
                }
                $points[] = [$x, $y];
            }

            return $points;
        }

        if (count($values) !== 4) {
            return null;
        }

        $points = [];
        foreach ($values as $point) {
            $coordinates = $this->polygonPointCoordinates($point);
            if ($coordinates === null) {
                return null;
            }
            $points[] = $coordinates;
        }

        return $points;
    }

    /**
     * @return array{0: float, 1: float}|null
     */
    private function polygonPointCoordinates(mixed $point): ?array
    {
        if (!is_array($point)) {
            return null;
        }

        if (array_key_exists('x', $point) && array_key_exists('y', $point)) {
            $x = $this->numericScalar($point['x']);
            $y = $this->numericScalar($point['y']);

            return $x === null || $y === null ? null : [$x, $y];
        }

        if (count($point) !== 2) {
            return null;
        }

        $values = array_values($point);
        $x = $this->numericScalar($values[0]);
        $y = $this->numericScalar($values[1]);

        return $x === null || $y === null ? null : [$x, $y];
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
     * @param bool $preserveSourceGeometry Preserve detector-source review metadata before table-crop localization.
     * @return list<array<string, mixed>>
     */
    private function normalizeCells(array $cells, bool $preserveSourceGeometry = false): array
    {
        $normalized = [];
        foreach ($cells as $cell) {
            if (!is_array($cell)) {
                throw new InvalidArgumentException('Table cells must be arrays.');
            }
            $entry = [
                'bbox' => $this->bboxFromRecord($cell),
                'text' => array_key_exists('text', $cell) && $cell['text'] !== null ? (string) $cell['text'] : '',
            ];
            if ($preserveSourceGeometry) {
                $entry = $this->withSourceGeometryReviewFields($entry, $cell);
                if (!isset($entry['source_coordinate_source'])) {
                    $entry['source_coordinate_source'] = $this->bboxCoordinateSourceFromRecord($cell);
                }
                if (!array_key_exists('source_endpoint_order_normalized', $entry)) {
                    $entry['source_endpoint_order_normalized'] = $this->bboxEndpointOrderNormalizedFromRecord($cell);
                }
            }
            $normalized[] = $entry;
        }

        return $normalized;
    }

    /**
     * @param list<array<string, mixed>> $cells
     * @param list<float> $tableBbox
     * @param array{width: int, height: int} $cropSize
     * @return list<array<string, mixed>>
     */
    private function detectorCellsInTableCrop(array $cells, array $tableBbox, array $cropSize): array
    {
        if ($cells === [] || $this->detectorCellsOverlapCrop($cells, $cropSize)) {
            return $cells;
        }
        if (!$this->detectorCellsOverlapPageTableBbox($cells, $tableBbox)) {
            return $cells;
        }

        $localized = [];
        foreach ($cells as $cell) {
            $sourceBbox = $cell['bbox'];
            $cell['source_bbox'] = $sourceBbox;
            $cell['source_coordinate_space'] = 'page_image';
            $cell['bbox'] = $this->relativeToTableBbox($sourceBbox, $tableBbox);
            $localized[] = $cell;
        }

        return $localized;
    }

    /**
     * @param list<array<string, mixed>> $cells
     * @param array{width: int, height: int} $cropSize
     */
    private function detectorCellsOverlapCrop(array $cells, array $cropSize): bool
    {
        foreach ($cells as $cell) {
            $bbox = $cell['bbox'];
            if ($this->positiveArea($bbox) > 0.0 && $this->positiveArea($this->clipBboxToImage($bbox, $cropSize)) > 0.0) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param list<array<string, mixed>> $cells
     * @param list<float> $tableBbox
     */
    private function detectorCellsOverlapPageTableBbox(array $cells, array $tableBbox): bool
    {
        foreach ($cells as $cell) {
            $bbox = $cell['bbox'];
            if ($this->positiveArea($bbox) > 0.0 && $this->intersectionPct($bbox, $tableBbox) > 0.0) {
                return true;
            }
        }

        return false;
    }

    /**
     * Upstream detector cells are produced from the cropped table image before
     * OCR text is zipped back by source order. Bound supplied detector cells at
     * that same crop boundary so off-crop decoys cannot shift OCR text into the
     * remaining table cells.
     *
     * @param list<array<string, mixed>> $cells
     * @param array{width: int, height: int} $imageSize
     * @return array{cells: list<array<string, mixed>>, review: array<string, mixed>|null}
     */
    private function boundedDetectorCellsForCrop(array $cells, array $imageSize): array
    {
        if ($cells === []) {
            return [
                'cells' => [],
                'review' => null,
            ];
        }

        $bounded = [];
        $reviewRows = [];
        foreach ($cells as $index => $cell) {
            $bbox = $cell['bbox'];
            $clippedBbox = $this->clipBboxToImage($bbox, $imageSize);
            $originalPositive = $this->positiveArea($bbox) > 0.0;
            $clippedPositive = $this->positiveArea($clippedBbox) > 0.0;
            $active = $originalPositive && $clippedPositive;
            $status = 'within_table_image';

            if (!$originalPositive) {
                $status = 'excluded_non_positive_area';
            } elseif (!$clippedPositive) {
                $status = 'excluded_outside_table_image';
            } elseif ($clippedBbox !== $bbox) {
                $status = 'clipped_to_table_image';
            }

            $reviewRow = [
                'cell_index' => $index,
                'text' => (string) ($cell['text'] ?? ''),
                'original_bbox' => $bbox,
                'bounded_bbox' => $active ? $clippedBbox : null,
                'clipped_bbox' => $clippedBbox,
                'status' => $status,
                'active' => $active,
                'ocr_source_order_retained_after_crop_boundary' => $active,
                'detector_cell_excluded_before_ocr' => !$active,
                'upstream_cell_bbox_retained' => $active,
            ];
            foreach (
                ['source_bbox', 'source_coordinate_space', 'source_coordinate_source', 'source_endpoint_order_normalized']
                as $field
            ) {
                if (array_key_exists($field, $cell)) {
                    $reviewRow[$field] = $cell[$field];
                }
            }
            $reviewRows[] = $reviewRow;

            if ($active) {
                $bounded[] = $cell;
            }
        }

        return [
            'cells' => $bounded,
            'review' => [
                'review_target' => 'table_detector_cell_crop_boundary',
                'upstream_boundary' => 'tabled.inference.recognition.get_cells.table_image_detector_cells',
                'image_size' => $imageSize,
                'cell_count' => count($reviewRows),
                'active_cell_count' => count($bounded),
                'within_cell_count' => count(array_filter(
                    $reviewRows,
                    static fn (array $row): bool => ($row['status'] ?? null) === 'within_table_image'
                )),
                'clipped_cell_count' => count(array_filter(
                    $reviewRows,
                    static fn (array $row): bool => ($row['status'] ?? null) === 'clipped_to_table_image'
                )),
                'excluded_cell_count' => count(array_filter(
                    $reviewRows,
                    static fn (array $row): bool => isset($row['status']) && str_starts_with((string) $row['status'], 'excluded_')
                )),
                'cells' => $reviewRows,
            ],
        ];
    }

    /**
     * @param list<array<string, mixed>> $cells
     * @param list<array<string, mixed>> $rows
     * @param list<array<string, mixed>> $cols
     * @return list<array{bbox: list<float>, text: string, row_ids: list<int|null>, col_ids: list<int|null>, order?: int}>
     */
    private function normalizeAssignedCells(array $cells, array $rows = [], array $cols = []): array
    {
        $rowOrder = $this->bandOrderMap($rows, 'row_id');
        $colOrder = $this->bandOrderMap($cols, 'col_id');
        $normalized = [];
        foreach ($cells as $cell) {
            if (!is_array($cell)) {
                throw new InvalidArgumentException('Assigned table cells must be arrays.');
            }
            $rowIds = $this->assignedRowIds($cell, $rowOrder);
            $colIds = $this->assignedColIds($cell, $colOrder);
            if (($rowIds[0] ?? null) === null || ($colIds[0] ?? null) === null) {
                throw new InvalidArgumentException('Assigned table cells must include non-null row/column assignment anchors.');
            }

            $entry = [
                'bbox' => $this->bboxFromRecord($cell),
                'text' => (string) ($cell['text'] ?? ''),
                'row_ids' => $rowIds,
                'col_ids' => $colIds,
            ];
            if (isset($cell['order'])) {
                $entry['order'] = (int) $cell['order'];
            }
            $entry = $this->withSourceGeometryReviewFields($entry, $cell);
            foreach (['row_geometry_orders', 'col_geometry_orders'] as $field) {
                if (isset($cell[$field]) && is_array($cell[$field])) {
                    $entry[$field] = array_map(
                        static fn (mixed $order): ?int => $order === null ? null : (int) $order,
                        array_values($cell[$field])
                    );
                }
            }
            $normalized[] = $entry;
        }

        return $normalized;
    }

    /**
     * @param array<string, mixed> $cell
     * @param array<int, int> $rowOrder
     * @return list<int|null>
     */
    private function assignedRowIds(array $cell, array $rowOrder): array
    {
        $rowIds = $cell['row_ids'] ?? null;
        if (is_array($rowIds) && ($rowIds[0] ?? null) !== null) {
            return array_map(
                static fn (mixed $id): ?int => $id === null ? null : (int) $id,
                array_values($rowIds)
            );
        }

        $rowId = $this->nullableInteger($cell['row_id'] ?? null);
        if ($rowId === null) {
            return [];
        }

        return $this->spanIdsFromAnchor($rowId, $this->positiveSpan($cell['rowspan'] ?? null), $rowOrder);
    }

    /**
     * @param array<string, mixed> $cell
     * @param array<int, int> $colOrder
     * @return list<int|null>
     */
    private function assignedColIds(array $cell, array $colOrder): array
    {
        $colIds = $cell['col_ids'] ?? null;
        if (is_array($colIds) && ($colIds[0] ?? null) !== null) {
            return array_map(
                static fn (mixed $id): ?int => $id === null ? null : (int) $id,
                array_values($colIds)
            );
        }

        $colId = $this->nullableInteger($cell['col_id'] ?? null);
        if ($colId === null) {
            return [];
        }

        return $this->spanIdsFromAnchor($colId, $this->positiveSpan($cell['colspan'] ?? null), $colOrder);
    }

    /**
     * @param array<int, int> $bandOrder
     * @return list<int>
     */
    private function spanIdsFromAnchor(int $anchorId, int $span, array $bandOrder): array
    {
        if ($bandOrder !== [] && array_key_exists($anchorId, $bandOrder)) {
            $bandIds = array_map('intval', array_keys($bandOrder));
            $start = $bandOrder[$anchorId];
            $ids = [];
            for ($position = $start; $position < count($bandIds) && count($ids) < $span; $position++) {
                $ids[] = $bandIds[$position];
            }

            return $ids === [] ? [$anchorId] : $ids;
        }

        $ids = [];
        for ($offset = 0; $offset < $span; $offset++) {
            $ids[] = $anchorId + $offset;
        }

        return $ids;
    }

    private function positiveSpan(mixed $value): int
    {
        $span = $this->nullableInteger($value);

        return $span === null || $span < 1 ? 1 : $span;
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
            $entry = [
                $idField => (int) $id,
                'bbox' => $bbox,
                'coordinate_source' => $this->bboxCoordinateSourceFromRecord($item),
                'endpoint_order_normalized' => $this->bboxEndpointOrderNormalizedFromRecord($item),
                'width' => $bbox[2] - $bbox[0],
                'height' => $bbox[3] - $bbox[1],
                'area' => $this->area($bbox),
            ];
            $normalized[] = $this->withSourceGeometryReviewFields($entry, $item);
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
            $cell['col_ids'] = $this->orderedIdsByBands($cell['col_ids'], $cols, 'col_id');
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
            $cell['row_ids'] = $this->orderedIdsByBands($cell['row_ids'], $rows, 'row_id');
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
            $rotated = $rows !== [] && $cols !== [] && $this->isRotated($rows, $cols);

            return [
                'rows' => $this->sortGridBandsByGeometry($rows, $rotated ? 'x' : 'y'),
                'cols' => $this->sortGridBandsByGeometry($cols, $rotated ? 'y' : 'x'),
                'review' => null,
            ];
        }

        $size = $this->imageSize($imageSize);
        $rowBoundary = $this->boundedGridBands($rows, 'row', 'row_id', $size);
        $colBoundary = $this->boundedGridBands($cols, 'column', 'col_id', $size);
        $rotated = $rowBoundary['bands'] !== [] && $colBoundary['bands'] !== []
            && $this->isRotated($rowBoundary['bands'], $colBoundary['bands']);
        $rowSortAxis = $rotated ? 'x' : 'y';
        $colSortAxis = $rotated ? 'y' : 'x';
        $rowBands = $this->sortGridBandsByGeometry($rowBoundary['bands'], $rowSortAxis);
        $colBands = $this->sortGridBandsByGeometry($colBoundary['bands'], $colSortAxis);
        $rowBoundary['review_rows'] = $this->withGridBandGeometryOrderReview($rowBoundary['review_rows'], $rowBands, 'row_id', $rowSortAxis);
        $colBoundary['review_rows'] = $this->withGridBandGeometryOrderReview($colBoundary['review_rows'], $colBands, 'col_id', $colSortAxis);
        $reviewRows = array_merge($rowBoundary['review_rows'], $colBoundary['review_rows']);
        $sourceActiveRowIds = $this->activeBandReviewIds($rowBoundary['review_rows']);
        $sourceActiveColIds = $this->activeBandReviewIds($colBoundary['review_rows']);
        $geometryActiveRowIds = $this->bandIds($rowBands, 'row_id');
        $geometryActiveColIds = $this->bandIds($colBands, 'col_id');

        return [
            'rows' => $rowBands,
            'cols' => $colBands,
            'review' => [
                'review_target' => 'table_grid_geometry_boundary',
                'image_size' => $size,
                'row_band_count' => count($rows),
                'col_band_count' => count($cols),
                'active_row_band_count' => count($rowBands),
                'active_col_band_count' => count($colBands),
                'active_row_ids' => $geometryActiveRowIds,
                'active_col_ids' => $geometryActiveColIds,
                'row_sort_axis' => $rowSortAxis,
                'col_sort_axis' => $colSortAxis,
                'row_band_order_normalized' => $sourceActiveRowIds !== $geometryActiveRowIds,
                'col_band_order_normalized' => $sourceActiveColIds !== $geometryActiveColIds,
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
     * @return list<array<string, mixed>>
     */
    private function sortGridBandsByGeometry(array $bands, string $axis): array
    {
        $indexed = [];
        foreach (array_values($bands) as $index => $band) {
            $bbox = $band['bbox'];
            $primaryStart = $axis === 'x' ? $bbox[0] : $bbox[1];
            $primaryEnd = $axis === 'x' ? $bbox[2] : $bbox[3];
            $secondaryStart = $axis === 'x' ? $bbox[1] : $bbox[0];
            $secondaryEnd = $axis === 'x' ? $bbox[3] : $bbox[2];
            $indexed[] = [
                'index' => $index,
                'band' => $band,
                'primary_start' => $primaryStart,
                'primary_center' => ($primaryStart + $primaryEnd) / 2.0,
                'secondary_start' => $secondaryStart,
                'secondary_center' => ($secondaryStart + $secondaryEnd) / 2.0,
            ];
        }

        usort($indexed, static function (array $left, array $right): int {
            return $left['primary_start'] <=> $right['primary_start']
                ?: $left['primary_center'] <=> $right['primary_center']
                ?: $left['secondary_start'] <=> $right['secondary_start']
                ?: $left['secondary_center'] <=> $right['secondary_center']
                ?: $left['index'] <=> $right['index'];
        });

        return array_map(static fn (array $entry): array => $entry['band'], $indexed);
    }

    /**
     * @param list<array<string, mixed>> $reviewRows
     * @param list<array<string, mixed>> $orderedBands
     * @return list<array<string, mixed>>
     */
    private function withGridBandGeometryOrderReview(array $reviewRows, array $orderedBands, string $idField, string $axis): array
    {
        $order = $this->bandOrderMap($orderedBands, $idField);
        foreach ($reviewRows as &$reviewRow) {
            $id = $this->nullableInteger($reviewRow['id'] ?? null);
            if ($id === null || !array_key_exists($id, $order)) {
                continue;
            }

            $reviewRow['geometry_order'] = $order[$id];
            $reviewRow['geometry_sort_axis'] = $axis;
        }
        unset($reviewRow);

        return $reviewRows;
    }

    /**
     * @param list<array<string, mixed>> $reviewRows
     * @return list<int>
     */
    private function activeBandReviewIds(array $reviewRows): array
    {
        $ids = [];
        foreach ($reviewRows as $reviewRow) {
            if (($reviewRow['active'] ?? null) !== true) {
                continue;
            }
            $id = $this->nullableInteger($reviewRow['id'] ?? null);
            if ($id !== null) {
                $ids[] = $id;
            }
        }

        return $ids;
    }

    /**
     * @param list<array<string, mixed>> $bands
     * @return list<int>
     */
    private function bandIds(array $bands, string $idField): array
    {
        $ids = [];
        foreach ($bands as $band) {
            if (isset($band[$idField])) {
                $ids[] = (int) $band[$idField];
            }
        }

        return $ids;
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
            if (($band['endpoint_order_normalized'] ?? false) === true) {
                $reviewRow['endpoint_order_normalized'] = true;
            }
            $reviewRow = $this->withSourceGeometryReviewFields($reviewRow, $band);
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
     * Upstream keeps SpanTableCell bbox coordinates even when a model cell
     * straddles the rendered table crop. Surface bounded review coordinates so
     * importers can draw crop-safe overlays without changing assignment.
     *
     * @param list<array<string, mixed>> $cells
     * @param array{width?: int|float, height?: int|float}|list<int|float>|null $imageSize
     * @return array<string, mixed>|null
     */
    private function tableCellGeometryBoundary(array $cells, ?array $imageSize): ?array
    {
        if ($imageSize === null || $cells === []) {
            return null;
        }

        $size = $this->imageSize($imageSize);
        $reviewRows = [];

        foreach ($cells as $index => $cell) {
            $bbox = $cell['bbox'];
            $clippedBbox = $this->clipBboxToImage($bbox, $size);
            $originalPositive = $this->positiveArea($bbox) > 0.0;
            $clippedPositive = $this->positiveArea($clippedBbox) > 0.0;
            $active = $originalPositive && $clippedPositive;
            $status = 'within_table_image';

            if (!$originalPositive) {
                $status = 'excluded_non_positive_area';
            } elseif (!$clippedPositive) {
                $status = 'excluded_outside_table_image';
            } elseif ($clippedBbox !== $bbox) {
                $status = 'clipped_to_table_image';
            }

            $rowIds = $this->nonNullOrderedIds($cell['row_ids']);
            $colIds = $this->nonNullOrderedIds($cell['col_ids']);
            $reviewRow = [
                'cell_index' => $index,
                'text' => (string) $cell['text'],
                'row_ids' => $rowIds,
                'col_ids' => $colIds,
                'original_bbox' => $bbox,
                'bounded_bbox' => $active ? $clippedBbox : null,
                'clipped_bbox' => $clippedBbox,
                'status' => $status,
                'active' => $active,
                'upstream_cell_bbox_retained' => true,
            ];
            $reviewRow = $this->withSourceGeometryReviewFields($reviewRow, $cell);
            if ($rowIds !== [] && $colIds !== []) {
                $reviewRow['anchor'] = [
                    'row_id' => $rowIds[0],
                    'col_id' => $colIds[0],
                ];
            }

            $reviewRows[] = $reviewRow;
        }

        return [
            'review_target' => 'table_cell_geometry_boundary',
            'upstream_boundary' => 'tabled.assignment.SpanTableCell.bbox',
            'image_size' => $size,
            'cell_count' => count($reviewRows),
            'active_cell_count' => count(array_filter(
                $reviewRows,
                static fn (array $row): bool => ($row['active'] ?? null) === true
            )),
            'within_cell_count' => count(array_filter(
                $reviewRows,
                static fn (array $row): bool => ($row['status'] ?? null) === 'within_table_image'
            )),
            'clipped_cell_count' => count(array_filter(
                $reviewRows,
                static fn (array $row): bool => ($row['status'] ?? null) === 'clipped_to_table_image'
            )),
            'excluded_cell_count' => count(array_filter(
                $reviewRows,
                static fn (array $row): bool => isset($row['status']) && str_starts_with((string) $row['status'], 'excluded_')
            )),
            'cells' => $reviewRows,
        ];
    }

    /**
     * @param list<array<string, mixed>> $cells
     * @param array<string, mixed>|null $cellBoundaryReview
     * @return list<array<string, mixed>>
     */
    private function cellsWithGeometryBoundary(array $cells, ?array $cellBoundaryReview): array
    {
        if ($cellBoundaryReview === null || !isset($cellBoundaryReview['cells']) || !is_array($cellBoundaryReview['cells'])) {
            return $cells;
        }

        $boundariesByIndex = [];
        foreach ($cellBoundaryReview['cells'] as $boundary) {
            if (!is_array($boundary)) {
                continue;
            }

            $cellIndex = $this->nullableInteger($boundary['cell_index'] ?? null);
            if ($cellIndex !== null) {
                $boundariesByIndex[$cellIndex] = $boundary;
            }
        }

        foreach ($cells as $index => &$cell) {
            $boundary = $boundariesByIndex[$index] ?? null;
            if (!is_array($boundary)) {
                continue;
            }

            $cell['cell_boundary_status'] = (string) ($boundary['status'] ?? 'within_table_image');
            $cell['cell_boundary_active'] = ($boundary['active'] ?? null) === true;
            if (isset($boundary['clipped_bbox']) && is_array($boundary['clipped_bbox'])) {
                $cell['clipped_cell_bbox'] = $boundary['clipped_bbox'];
            }
            if (isset($boundary['bounded_bbox']) && is_array($boundary['bounded_bbox'])) {
                $cell['bounded_cell_bbox'] = $boundary['bounded_bbox'];
            }
        }
        unset($cell);

        return $cells;
    }

    /**
     * @param list<array<string, mixed>> $cells
     * @return array<string, mixed>|null
     */
    private function cellGroupBoundarySummary(array $cells): ?array
    {
        $statuses = [];
        $boundedBboxes = [];
        $clippedBboxes = [];
        $sourceBboxes = [];
        $sourcePageImageBboxes = [];
        $sourceCoordinateSpaces = [];
        $sourceCoordinateSources = [];
        $sourceEndpointOrderObserved = false;
        $sourceEndpointOrderNormalized = false;
        $activeCount = 0;
        $clippedCount = 0;
        $excludedCount = 0;
        $withinCount = 0;

        foreach ($cells as $cell) {
            if (!array_key_exists('cell_boundary_status', $cell)) {
                continue;
            }

            $status = (string) $cell['cell_boundary_status'];
            $statuses[] = $status;
            if (($cell['cell_boundary_active'] ?? null) === true) {
                $activeCount++;
                if (isset($cell['bounded_cell_bbox']) && is_array($cell['bounded_cell_bbox'])) {
                    $boundedBboxes[] = $cell['bounded_cell_bbox'];
                }
            }
            if (isset($cell['clipped_cell_bbox']) && is_array($cell['clipped_cell_bbox'])) {
                $clippedBboxes[] = $cell['clipped_cell_bbox'];
            }
            if (isset($cell['source_bbox']) && is_array($cell['source_bbox'])) {
                $sourceBboxes[] = $cell['source_bbox'];
            }
            if (isset($cell['source_page_image_bbox']) && is_array($cell['source_page_image_bbox'])) {
                $sourcePageImageBboxes[] = $cell['source_page_image_bbox'];
            }
            if (isset($cell['source_coordinate_space']) && is_scalar($cell['source_coordinate_space'])) {
                $sourceCoordinateSpaces[] = (string) $cell['source_coordinate_space'];
            }
            if (isset($cell['source_coordinate_source']) && is_scalar($cell['source_coordinate_source'])) {
                $sourceCoordinateSources[] = (string) $cell['source_coordinate_source'];
            }
            if (array_key_exists('source_endpoint_order_normalized', $cell)) {
                $sourceEndpointOrderObserved = true;
                $sourceEndpointOrderNormalized = $sourceEndpointOrderNormalized || (bool) $cell['source_endpoint_order_normalized'];
            }
            if ($status === 'clipped_to_table_image') {
                $clippedCount++;
            } elseif (str_starts_with($status, 'excluded_')) {
                $excludedCount++;
            } elseif ($status === 'within_table_image') {
                $withinCount++;
            }
        }

        if ($statuses === []) {
            return null;
        }

        $status = 'within_table_image';
        if ($excludedCount > 0 && $activeCount === 0) {
            $status = $statuses[0];
        } elseif ($excludedCount > 0) {
            $status = 'mixed_table_image_boundary';
        } elseif ($clippedCount > 0) {
            $status = 'clipped_to_table_image';
        } elseif ($withinCount === 0) {
            $status = $statuses[0];
        }

        $summary = [
            'cell_boundary_status' => $status,
            'cell_boundary_active_count' => $activeCount,
            'cell_boundary_clipped_count' => $clippedCount,
            'cell_boundary_excluded_count' => $excludedCount,
        ];

        $boundedBbox = $this->mergedBboxList($boundedBboxes);
        if ($boundedBbox !== null) {
            $summary['bounded_cell_bbox'] = $boundedBbox;
        }

        $clippedBbox = $this->mergedBboxList($clippedBboxes);
        if ($clippedBbox !== null) {
            $summary['clipped_cell_bbox'] = $clippedBbox;
        }
        if ($sourceBboxes !== []) {
            $summary['source_cell_bboxes'] = $sourceBboxes;
            $sourceBbox = $this->mergedBboxList($sourceBboxes);
            if ($sourceBbox !== null) {
                $summary['source_cell_bbox'] = $sourceBbox;
            }
        }
        if ($sourcePageImageBboxes !== []) {
            $summary['source_page_image_bboxes'] = $sourcePageImageBboxes;
            $sourcePageImageBbox = $this->mergedBboxList($sourcePageImageBboxes);
            if ($sourcePageImageBbox !== null) {
                $summary['source_page_image_bbox'] = $sourcePageImageBbox;
            }
        }
        $sourceCoordinateSpaces = array_values(array_unique($sourceCoordinateSpaces));
        if ($sourceCoordinateSpaces !== []) {
            $summary['source_coordinate_spaces'] = $sourceCoordinateSpaces;
            if (count($sourceCoordinateSpaces) === 1) {
                $summary['source_coordinate_space'] = $sourceCoordinateSpaces[0];
            }
        }
        $sourceCoordinateSources = array_values(array_unique($sourceCoordinateSources));
        if ($sourceCoordinateSources !== []) {
            $summary['source_coordinate_sources'] = $sourceCoordinateSources;
            if (count($sourceCoordinateSources) === 1) {
                $summary['source_coordinate_source'] = $sourceCoordinateSources[0];
            }
        }
        if ($sourceEndpointOrderObserved) {
            $summary['source_endpoint_order_normalized'] = $sourceEndpointOrderNormalized;
        }

        return $summary;
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
     * @param list<array<string, mixed>> $items
     * @return array<int, int>
     */
    private function bandOrderMap(array $items, string $idField): array
    {
        $order = [];
        foreach (array_values($items) as $position => $item) {
            if (isset($item[$idField])) {
                $order[(int) $item[$idField]] = $position;
            }
        }

        return $order;
    }

    /**
     * @param list<array<string, mixed>> $cells
     * @return list<array<string, mixed>>
     */
    private function withBandOrderMetadata(array $cells, array $rows, array $cols): array
    {
        $rowOrder = $this->bandOrderMap($rows, 'row_id');
        $colOrder = $this->bandOrderMap($cols, 'col_id');
        foreach ($cells as &$cell) {
            $cell['row_ids'] = $this->nonNullOrderedIds($cell['row_ids'] ?? [], $rowOrder);
            $cell['col_ids'] = $this->nonNullOrderedIds($cell['col_ids'] ?? [], $colOrder);
            $cell['row_geometry_orders'] = array_map(
                static fn (int $rowId): ?int => $rowOrder[$rowId] ?? null,
                $cell['row_ids']
            );
            $cell['col_geometry_orders'] = array_map(
                static fn (int $colId): ?int => $colOrder[$colId] ?? null,
                $cell['col_ids']
            );
        }
        unset($cell);

        return $cells;
    }

    /**
     * @param list<array<string, mixed>> $cells
     * @return array<int, int>
     */
    private function storedBandOrderMap(array $cells, string $axis): array
    {
        $idField = $axis === 'row' ? 'row_ids' : 'col_ids';
        $orderField = $axis === 'row' ? 'row_geometry_orders' : 'col_geometry_orders';
        $order = [];
        foreach ($cells as $cell) {
            if (!isset($cell[$idField], $cell[$orderField]) || !is_array($cell[$idField]) || !is_array($cell[$orderField])) {
                continue;
            }
            foreach (array_values($cell[$idField]) as $index => $id) {
                if ($id === null || !isset($cell[$orderField][$index]) || $cell[$orderField][$index] === null) {
                    continue;
                }
                $order[(int) $id] = (int) $cell[$orderField][$index];
            }
        }

        return $order;
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
     * @param list<int|null> $ids
     * @return list<int>
     */
    private function nonNullOrderedIds(array $ids, array $orderMap = []): array
    {
        $out = [];
        foreach ($ids as $id) {
            if ($id !== null) {
                $out[] = (int) $id;
            }
        }

        return $this->orderIdsByMap($out, $orderMap);
    }

    /**
     * @param list<int|null> $ids
     * @param list<array<string, mixed>> $bands
     * @return list<int>
     */
    private function orderedIdsByBands(array $ids, array $bands, string $idField): array
    {
        return $this->nonNullOrderedIds($ids, $this->bandOrderMap($bands, $idField));
    }

    /**
     * @param list<int> $ids
     * @return list<int>
     */
    private function orderIdsByMap(array $ids, array $orderMap = []): array
    {
        $ids = array_values(array_unique(array_map(static fn (mixed $id): int => (int) $id, $ids)));
        usort($ids, static function (int $left, int $right) use ($orderMap): int {
            $leftKnown = array_key_exists($left, $orderMap);
            $rightKnown = array_key_exists($right, $orderMap);
            if ($leftKnown && $rightKnown) {
                return $orderMap[$left] <=> $orderMap[$right];
            }
            if ($leftKnown) {
                return -1;
            }
            if ($rightKnown) {
                return 1;
            }

            return $left <=> $right;
        });

        return $ids;
    }

    /**
     * @param list<array<string, mixed>> $cells
     * @return list<int>
     */
    private function orderedUniqueIdsFromCells(array $cells, string $idField, array $orderMap = []): array
    {
        $ids = [];
        foreach ($cells as $cell) {
            foreach (($cell[$idField] ?? []) as $id) {
                if ($id !== null) {
                    $ids[] = (int) $id;
                }
            }
        }

        return $this->orderIdsByMap($ids, $orderMap);
    }

    /**
     * @param list<int> $ids
     */
    private function minIdPosition(array $ids, array $orderMap = []): int
    {
        $positions = [];
        foreach ($ids as $id) {
            $id = (int) $id;
            $positions[] = $orderMap[$id] ?? $id;
        }

        return $positions === [] ? 0 : min($positions);
    }

    private function idSortPosition(int $id, array $orderMap = []): int
    {
        return $orderMap[$id] ?? $id;
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
    private function uniqueGridIds(array $candidateGridCells, string $field, array $orderMap = []): array
    {
        $ids = [];
        foreach ($candidateGridCells as $cell) {
            foreach (($cell[$field] ?? []) as $id) {
                if ($id !== null) {
                    $ids[] = (int) $id;
                }
            }
        }

        return $this->nonNullOrderedIds($ids, $orderMap);
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
    private function spanningGridAnchorGroups(array $cells, array $rowOrder = [], array $colOrder = []): array
    {
        $groups = [];
        $order = [];

        foreach ($cells as $cell) {
            $rowIds = $this->nonNullOrderedIds($cell['row_ids'], $rowOrder);
            $colIds = $this->nonNullOrderedIds($cell['col_ids'], $colOrder);
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
            $groups[$key]['row_ids'] = $this->mergeOrderedIds($groups[$key]['row_ids'], $rowIds, $rowOrder);
            $groups[$key]['col_ids'] = $this->mergeOrderedIds($groups[$key]['col_ids'], $colIds, $colOrder);
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
    private function mergeOrderedIds(array $left, array $right, array $orderMap = []): array
    {
        $ids = array_values(array_unique(array_merge($left, $right)));

        return $this->orderIdsByMap($ids, $orderMap);
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
    private function reviewContinuationCells(array $cells, array $rowOrder = [], array $colOrder = []): array
    {
        $continuations = [];
        foreach ($cells as $cell) {
            $entry = [
                'text' => (string) $cell['text'],
                'row_ids' => $this->nonNullOrderedIds($cell['row_ids'], $rowOrder),
                'col_ids' => $this->nonNullOrderedIds($cell['col_ids'], $colOrder),
                'bbox' => $cell['bbox'],
            ];
            foreach ([
                'cell_boundary_status',
                'cell_boundary_active',
                'bounded_cell_bbox',
                'clipped_cell_bbox',
                'source_bbox',
                'source_coordinate_space',
            ] as $field) {
                if (array_key_exists($field, $cell)) {
                    $entry[$field] = $cell[$field];
                }
            }

            $continuations[] = $entry;
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
     * @param list<list<float>> $bboxes
     * @return list<float>|null
     */
    private function mergedBboxList(array $bboxes): ?array
    {
        if ($bboxes === []) {
            return null;
        }

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
        $rowPositionById = array_flip($rowIds);
        $maxHeaderRowPosition = $rowPositionById[$topRowId] ?? 0;
        foreach ($cellGroups as $cellGroup) {
            $groupRowIds = $cellGroup['row_ids'];
            if ($groupRowIds === [] || $groupRowIds[0] !== $topRowId) {
                continue;
            }

            foreach ($groupRowIds as $rowId) {
                if (isset($rowPositionById[$rowId])) {
                    $maxHeaderRowPosition = max($maxHeaderRowPosition, (int) $rowPositionById[$rowId]);
                }
            }
        }

        $headerRows = [];
        foreach ($rowIds as $position => $rowId) {
            if ($position <= $maxHeaderRowPosition) {
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
    private function applyHeaderReferences(array $renderCells, array $rowOrder = [], array $colOrder = []): array
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

            $references = $this->headerReferencesForDataCell($renderCell, $headerCells, $rowOrder, $colOrder);
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
    private function headerReferencesForDataCell(array $dataCell, array $headerCells, array $rowOrder = [], array $colOrder = []): array
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

        $dataMinRow = $this->minIdPosition($rowIds, $rowOrder);
        $dataMinCol = $this->minIdPosition($colIds, $colOrder);
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
                && $this->minIdPosition($headerRowIds, $rowOrder) < $dataMinRow
                && array_intersect($headerColIds, $colIds) !== []
            ) {
                $columnHeaderIds[] = $headerId;
            }
            if (
                in_array('row', $headerAxes, true)
                && $this->minIdPosition($headerColIds, $colOrder) < $dataMinCol
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
    private function sortCells(array $cells, array $rowOrder = [], array $colOrder = []): array
    {
        $order = $this->sortWithinCell($cells);
        foreach ($cells as $idx => &$cell) {
            $cell['order'] = $order[$idx];
        }
        unset($cell);

        usort($cells, function (array $left, array $right) use ($rowOrder, $colOrder): int {
            $leftRows = $this->nonNullOrderedIds($left['row_ids'] ?? [], $rowOrder);
            $rightRows = $this->nonNullOrderedIds($right['row_ids'] ?? [], $rowOrder);
            $leftCols = $this->nonNullOrderedIds($left['col_ids'] ?? [], $colOrder);
            $rightCols = $this->nonNullOrderedIds($right['col_ids'] ?? [], $colOrder);
            $leftRow = $leftRows[0] ?? PHP_INT_MAX;
            $rightRow = $rightRows[0] ?? PHP_INT_MAX;
            $leftCol = $leftCols[0] ?? PHP_INT_MAX;
            $rightCol = $rightCols[0] ?? PHP_INT_MAX;

            return $this->idSortPosition($leftRow, $rowOrder) <=> $this->idSortPosition($rightRow, $rowOrder)
                ?: $this->idSortPosition($leftCol, $colOrder) <=> $this->idSortPosition($rightCol, $colOrder)
                ?: (($left['order'] ?? 0) <=> ($right['order'] ?? 0));
        });

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
        $bbox = $this->nullableBboxFromRecord($record);
        if ($bbox === null) {
            throw new InvalidArgumentException('Table geometry entries must include a four-value bbox, named bbox fields, or four-corner polygon alias.');
        }

        return $bbox;
    }

    /**
     * @param array<string, mixed> $record
     * @return list<float>|null
     */
    private function nullableBboxFromRecord(array $record): ?array
    {
        return $this->bboxFromValue($record['bbox'] ?? null)
            ?? $this->bboxFromNamedFields($record)
            ?? $this->bboxFromWrappedValue($record)
            ?? $this->polygonBboxFromRecord($record)
            ?? $this->sourceBboxFromRecord($record);
    }

    /**
     * Some saved review/sidecar records preserve geometry only as source
     * coordinates. Treat those as a fallback input shape, while keeping
     * primary bbox/named/polygon fields authoritative when present.
     *
     * @param array<string|int, mixed> $record
     * @return list<float>|null
     */
    private function sourceBboxFromRecord(array $record): ?array
    {
        foreach (['source_bbox', 'source_page_image_bbox'] as $key) {
            $bbox = $this->bboxFromValue($record[$key] ?? null);
            if ($bbox !== null) {
                return $bbox;
            }
        }

        return null;
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

        return $this->bboxFromNamedFields($bbox)
            ?? $this->bboxFromWrappedValue($bbox)
            ?? $this->polygonBbox($bbox)
            ?? $this->nullableBbox($bbox);
    }

    /**
     * Upstream tabled/Surya Pydantic dumps and saved review sidecars can
     * preserve geometry under generic wrapper keys instead of a bare list.
     *
     * @param array<string|int, mixed> $record
     * @return list<float>|null
     */
    private function bboxFromWrappedValue(array $record): ?array
    {
        foreach ($this->wrappedGeometryKeys() as $key) {
            $value = $record[$key] ?? null;
            if (!is_array($value)) {
                continue;
            }

            $bbox = $this->bboxFromNamedFields($value)
                ?? $this->nullableBbox($value)
                ?? $this->polygonBboxFromRecord($value)
                ?? $this->polygonBbox($value)
                ?? $this->sourceBboxFromRecord($value);
            if ($bbox !== null) {
                return $bbox;
            }
        }

        return null;
    }

    /**
     * @return list<string>
     */
    private function wrappedGeometryKeys(): array
    {
        return [
            'bbox',
            'box',
            'rect',
            'rectangle',
            'bounds',
            'bounding_box',
        ];
    }

    /**
     * @param array<string, mixed> $record
     * @return list<float>|null
     */
    private function bboxFromNamedFields(array $record): ?array
    {
        $sets = [
            ['x1', 'y1', 'x2', 'y2'],
            ['x0', 'y0', 'x1', 'y1'],
            ['xmin', 'ymin', 'xmax', 'ymax'],
            ['x_min', 'y_min', 'x_max', 'y_max'],
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

            $out = $this->rawBboxCoordinates([$record[$x1], $record[$y1], $record[$x2], $record[$y2]]);

            return $out === null ? null : $this->canonicalBbox($out);
        }

        foreach ([
            ['x', 'y', 'width', 'height'],
            ['x0', 'y0', 'width', 'height'],
            ['left', 'top', 'width', 'height'],
            ['x', 'y', 'w', 'h'],
            ['x0', 'y0', 'w', 'h'],
            ['left', 'top', 'w', 'h'],
        ] as $keys) {
            [$x, $y, $width, $height] = $keys;
            if (
                !array_key_exists($x, $record)
                || !array_key_exists($y, $record)
                || !array_key_exists($width, $record)
                || !array_key_exists($height, $record)
            ) {
                continue;
            }

            $out = $this->rawBboxCoordinates([$record[$x], $record[$y], $record[$width], $record[$height]]);
            if ($out === null) {
                return null;
            }

            return $this->canonicalBbox([$out[0], $out[1], $out[0] + $out[2], $out[1] + $out[3]]);
        }

        foreach ([
            ['cx', 'cy', 'width', 'height'],
            ['center_x', 'center_y', 'width', 'height'],
            ['x_center', 'y_center', 'width', 'height'],
            ['cx', 'cy', 'w', 'h'],
            ['center_x', 'center_y', 'w', 'h'],
            ['x_center', 'y_center', 'w', 'h'],
        ] as $keys) {
            [$centerX, $centerY, $width, $height] = $keys;
            if (
                !array_key_exists($centerX, $record)
                || !array_key_exists($centerY, $record)
                || !array_key_exists($width, $record)
                || !array_key_exists($height, $record)
            ) {
                continue;
            }

            $out = $this->rawBboxCoordinates([$record[$centerX], $record[$centerY], $record[$width], $record[$height]]);
            if ($out === null) {
                return null;
            }

            return $this->canonicalBbox([
                $out[0] - ($out[2] / 2.0),
                $out[1] - ($out[3] / 2.0),
                $out[0] + ($out[2] / 2.0),
                $out[1] + ($out[3] / 2.0),
            ]);
        }

        $center = $this->pointCoordinatesFromValue($record['center'] ?? null);
        if ($center !== null) {
            $extent = null;
            if (array_key_exists('width', $record) && array_key_exists('height', $record)) {
                $extent = $this->rawPointCoordinates([$record['width'], $record['height']]);
            }
            if ($extent === null) {
                foreach (['extent', 'size'] as $key) {
                    $extent = $this->pointCoordinatesFromValue($record[$key] ?? null);
                    if ($extent !== null) {
                        break;
                    }
                }
            }
            if ($extent !== null) {
                return $this->canonicalBbox([
                    $center[0] - ($extent[0] / 2.0),
                    $center[1] - ($extent[1] / 2.0),
                    $center[0] + ($extent[0] / 2.0),
                    $center[1] + ($extent[1] / 2.0),
                ]);
            }
        }

        return null;
    }

    /**
     * @param array<string, mixed> $record
     */
    private function bboxCoordinateSourceFromRecord(array $record): string
    {
        $bbox = $record['bbox'] ?? null;
        if (is_array($bbox) && $this->bboxFromValue($bbox) !== null) {
            return $this->bboxNamedFieldSource($bbox)
                ?? $this->bboxWrappedFieldSource($bbox)
                ?? $this->bboxPolygonValueSource($bbox)
                ?? 'bbox_array';
        }

        return $this->bboxNamedFieldSource($record)
            ?? $this->bboxWrappedFieldSource($record)
            ?? $this->polygonCoordinateSourceFromRecord($record)
            ?? $this->sourceBboxCoordinateSourceFromRecord($record)
            ?? 'bbox_array';
    }

    /**
     * @param array<string|int, mixed> $record
     */
    private function sourceBboxCoordinateSourceFromRecord(array $record): ?string
    {
        foreach (['source_bbox', 'source_page_image_bbox'] as $key) {
            if ($this->bboxFromValue($record[$key] ?? null) !== null) {
                return $key;
            }
        }

        return null;
    }

    /**
     * @param array<string, mixed> $target
     * @param array<string, mixed> $source
     * @return array<string, mixed>
     */
    private function withSourceGeometryReviewFields(array $target, array $source): array
    {
        foreach (['source_bbox', 'source_page_image_bbox'] as $field) {
            $bbox = $this->bboxFromValue($source[$field] ?? null);
            if ($bbox !== null) {
                $target[$field] = $bbox;
            }
        }

        if (isset($source['source_coordinate_space']) && is_scalar($source['source_coordinate_space'])) {
            $target['source_coordinate_space'] = $this->normalizeCoordinateSpace((string) $source['source_coordinate_space']);
        }
        if (isset($source['source_coordinate_source']) && is_scalar($source['source_coordinate_source'])) {
            $target['source_coordinate_source'] = (string) $source['source_coordinate_source'];
        }
        if (array_key_exists('source_endpoint_order_normalized', $source)) {
            $target['source_endpoint_order_normalized'] = (bool) $source['source_endpoint_order_normalized'];
        }

        return $target;
    }

    /**
     * @param array<string, mixed> $record
     */
    private function bboxEndpointOrderNormalizedFromRecord(array $record): bool
    {
        $raw = $this->rawBboxCoordinatesFromValue($record['bbox'] ?? null)
            ?? $this->rawBboxCoordinatesFromNamedFields($record)
            ?? $this->rawBboxCoordinatesFromWrappedValue($record)
            ?? $this->rawSourceBboxCoordinatesFromRecord($record);
        if ($raw === null) {
            return false;
        }

        return $raw[2] < $raw[0] || $raw[3] < $raw[1];
    }

    /**
     * @param array<string|int, mixed> $record
     * @return list<float>|null
     */
    private function rawSourceBboxCoordinatesFromRecord(array $record): ?array
    {
        foreach (['source_bbox', 'source_page_image_bbox'] as $key) {
            $raw = $this->rawBboxCoordinatesFromValue($record[$key] ?? null);
            if ($raw !== null) {
                return $raw;
            }
        }

        return null;
    }

    /**
     * @param array<string, mixed> $record
     */
    private function bboxNamedFieldSource(array $record): ?string
    {
        $sets = [
            'bbox_xyxy_named_fields' => ['x1', 'y1', 'x2', 'y2'],
            'bbox_x0_y0_x1_y1_fields' => ['x0', 'y0', 'x1', 'y1'],
            'bbox_xmin_ymin_xmax_ymax_fields' => ['xmin', 'ymin', 'xmax', 'ymax'],
            'bbox_x_min_y_min_x_max_y_max_fields' => ['x_min', 'y_min', 'x_max', 'y_max'],
            'bbox_x_start_y_start_fields' => ['x_start', 'y_start', 'x_end', 'y_end'],
            'bbox_left_top_right_bottom_fields' => ['left', 'top', 'right', 'bottom'],
            'bbox_xy_width_height_fields' => ['x', 'y', 'width', 'height'],
            'bbox_x0_y0_width_height_fields' => ['x0', 'y0', 'width', 'height'],
            'bbox_left_top_width_height_fields' => ['left', 'top', 'width', 'height'],
            'bbox_cx_cy_width_height_fields' => ['cx', 'cy', 'width', 'height'],
            'bbox_center_x_center_y_width_height_fields' => ['center_x', 'center_y', 'width', 'height'],
            'bbox_x_center_y_center_width_height_fields' => ['x_center', 'y_center', 'width', 'height'],
            'bbox_xy_w_h_fields' => ['x', 'y', 'w', 'h'],
            'bbox_x0_y0_w_h_fields' => ['x0', 'y0', 'w', 'h'],
            'bbox_left_top_w_h_fields' => ['left', 'top', 'w', 'h'],
            'bbox_cx_cy_w_h_fields' => ['cx', 'cy', 'w', 'h'],
            'bbox_center_x_center_y_w_h_fields' => ['center_x', 'center_y', 'w', 'h'],
            'bbox_x_center_y_center_w_h_fields' => ['x_center', 'y_center', 'w', 'h'],
        ];

        foreach ($sets as $source => $keys) {
            if (count(array_intersect($keys, array_keys($record))) === 4) {
                return $source;
            }
        }

        if ($this->pointCoordinatesFromValue($record['center'] ?? null) !== null) {
            if (array_key_exists('width', $record) && array_key_exists('height', $record)) {
                return 'bbox_center_width_height_fields';
            }
            foreach (['extent', 'size'] as $key) {
                if ($this->pointCoordinatesFromValue($record[$key] ?? null) !== null) {
                    return 'bbox_center_' . $key . '_fields';
                }
            }
        }

        return null;
    }

    /**
     * @param array<string|int, mixed> $record
     */
    private function bboxPolygonValueSource(array $record): ?string
    {
        $source = $this->polygonValueCoordinateSource($record);

        return $source === null ? null : 'bbox_' . $source;
    }

    /**
     * @param array<string|int, mixed> $record
     */
    private function bboxWrappedFieldSource(array $record): ?string
    {
        foreach ($this->wrappedGeometryKeys() as $key) {
            $value = $record[$key] ?? null;
            if (!is_array($value)) {
                continue;
            }

            $source = $this->bboxNamedFieldSource($value);
            if ($source !== null) {
                return $key . '.' . $source;
            }

            if ($this->nullableBbox($value) !== null) {
                return $key . '.bbox_array';
            }

            $source = $this->bboxPolygonValueSource($value);
            if ($source !== null) {
                return $key . '.' . $source;
            }

            $source = $this->polygonCoordinateSourceFromRecord($value);
            if ($source !== null) {
                return $key . '.' . $source;
            }

            $source = $this->sourceBboxCoordinateSourceFromRecord($value);
            if ($source !== null) {
                return $key . '.' . $source;
            }
        }

        return null;
    }

    /**
     * @param mixed $bbox
     * @return list<float>|null
     */
    private function rawBboxCoordinatesFromValue(mixed $bbox): ?array
    {
        if (!is_array($bbox)) {
            return null;
        }

        return $this->rawBboxCoordinatesFromNamedFields($bbox)
            ?? $this->rawBboxCoordinatesFromWrappedValue($bbox)
            ?? $this->rawPolygonBboxCoordinates($bbox)
            ?? $this->rawBboxCoordinates(array_values($bbox));
    }

    /**
     * @param array<string|int, mixed> $record
     * @return list<float>|null
     */
    private function rawBboxCoordinatesFromWrappedValue(array $record): ?array
    {
        foreach ($this->wrappedGeometryKeys() as $key) {
            $value = $record[$key] ?? null;
            if (!is_array($value)) {
                continue;
            }

            $raw = $this->rawBboxCoordinatesFromNamedFields($value)
                ?? $this->rawPolygonBboxCoordinates($value)
                ?? $this->rawSourceBboxCoordinatesFromRecord($value)
                ?? $this->rawBboxCoordinates(array_values($value));
            if ($raw !== null) {
                return $raw;
            }
        }

        return null;
    }

    /**
     * @param mixed $bbox
     * @return list<float>|null
     */
    private function rawPolygonBboxCoordinates(mixed $bbox): ?array
    {
        if (!is_array($bbox)) {
            return null;
        }

        return $this->polygonBbox($bbox);
    }

    /**
     * @param array<string, mixed> $record
     * @return list<float>|null
     */
    private function rawBboxCoordinatesFromNamedFields(array $record): ?array
    {
        $sets = [
            ['x1', 'y1', 'x2', 'y2'],
            ['x0', 'y0', 'x1', 'y1'],
            ['xmin', 'ymin', 'xmax', 'ymax'],
            ['x_min', 'y_min', 'x_max', 'y_max'],
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

            return $this->rawBboxCoordinates([$record[$x1], $record[$y1], $record[$x2], $record[$y2]]);
        }

        foreach ([
            ['x', 'y', 'width', 'height'],
            ['x0', 'y0', 'width', 'height'],
            ['left', 'top', 'width', 'height'],
            ['x', 'y', 'w', 'h'],
            ['x0', 'y0', 'w', 'h'],
            ['left', 'top', 'w', 'h'],
        ] as $keys) {
            [$x, $y, $width, $height] = $keys;
            if (
                !array_key_exists($x, $record)
                || !array_key_exists($y, $record)
                || !array_key_exists($width, $record)
                || !array_key_exists($height, $record)
            ) {
                continue;
            }

            $out = $this->rawBboxCoordinates([$record[$x], $record[$y], $record[$width], $record[$height]]);
            if ($out === null) {
                return null;
            }

            return [$out[0], $out[1], $out[0] + $out[2], $out[1] + $out[3]];
        }

        foreach ([
            ['cx', 'cy', 'width', 'height'],
            ['center_x', 'center_y', 'width', 'height'],
            ['x_center', 'y_center', 'width', 'height'],
            ['cx', 'cy', 'w', 'h'],
            ['center_x', 'center_y', 'w', 'h'],
            ['x_center', 'y_center', 'w', 'h'],
        ] as $keys) {
            [$centerX, $centerY, $width, $height] = $keys;
            if (
                !array_key_exists($centerX, $record)
                || !array_key_exists($centerY, $record)
                || !array_key_exists($width, $record)
                || !array_key_exists($height, $record)
            ) {
                continue;
            }

            $out = $this->rawBboxCoordinates([$record[$centerX], $record[$centerY], $record[$width], $record[$height]]);
            if ($out === null) {
                return null;
            }

            return [
                $out[0] - ($out[2] / 2.0),
                $out[1] - ($out[3] / 2.0),
                $out[0] + ($out[2] / 2.0),
                $out[1] + ($out[3] / 2.0),
            ];
        }

        $center = $this->pointCoordinatesFromValue($record['center'] ?? null);
        if ($center !== null) {
            $extent = null;
            if (array_key_exists('width', $record) && array_key_exists('height', $record)) {
                $extent = $this->rawPointCoordinates([$record['width'], $record['height']]);
            }
            if ($extent === null) {
                foreach (['extent', 'size'] as $key) {
                    $extent = $this->pointCoordinatesFromValue($record[$key] ?? null);
                    if ($extent !== null) {
                        break;
                    }
                }
            }
            if ($extent !== null) {
                return [
                    $center[0] - ($extent[0] / 2.0),
                    $center[1] - ($extent[1] / 2.0),
                    $center[0] + ($extent[0] / 2.0),
                    $center[1] + ($extent[1] / 2.0),
                ];
            }
        }

        return null;
    }

    /**
     * @return list<float>|null
     */
    private function pointCoordinatesFromValue(mixed $value): ?array
    {
        if (!is_array($value)) {
            return null;
        }

        if (array_key_exists('x', $value) && array_key_exists('y', $value)) {
            return $this->rawPointCoordinates([$value['x'], $value['y']]);
        }

        if (array_key_exists('width', $value) && array_key_exists('height', $value)) {
            return $this->rawPointCoordinates([$value['width'], $value['height']]);
        }

        if (array_key_exists('w', $value) && array_key_exists('h', $value)) {
            return $this->rawPointCoordinates([$value['w'], $value['h']]);
        }

        return $this->rawPointCoordinates(array_values($value));
    }

    /**
     * @param list<mixed> $values
     * @return list<float>|null
     */
    private function rawPointCoordinates(array $values): ?array
    {
        if (count($values) !== 2) {
            return null;
        }

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
     * @param list<mixed> $values
     * @return list<float>|null
     */
    private function rawBboxCoordinates(array $values): ?array
    {
        if (count($values) !== 4) {
            return null;
        }

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

    /**
     * @param array{width?: int|float, height?: int|float}|list<int|float> $imageSize
     * @return array{width: int, height: int}
     */
    private function imageSize(array $imageSize): array
    {
        $size = $this->nullableImageSize($imageSize);
        if ($size !== null) {
            return $size;
        }

        throw new InvalidArgumentException('Table image sizes must include positive width and height.');
    }

    /**
     * @param list<float> $bbox
     * @return array{width: int, height: int}|null
     */
    private function imageSizeFromBboxExtent(array $bbox): ?array
    {
        $width = $bbox[2] - $bbox[0];
        $height = $bbox[3] - $bbox[1];
        if ($width <= 0.0 || $height <= 0.0) {
            return null;
        }

        return ['width' => (int) round($width), 'height' => (int) round($height)];
    }

    /**
     * @param array{width?: int|float, height?: int|float}|list<int|float>|array<string|int, mixed> $imageSize
     * @return array{width: int, height: int}|null
     */
    private function nullableImageSize(array $imageSize): ?array
    {
        $width = $this->numericScalar($imageSize['width'] ?? $imageSize[0] ?? null);
        $height = $this->numericScalar($imageSize['height'] ?? $imageSize[1] ?? null);
        if ($width === null || $height === null || $width <= 0.0 || $height <= 0.0) {
            return null;
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
