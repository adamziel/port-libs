<?php

declare(strict_types=1);

namespace PortLibs\Pandoc;

final class TableGeometry
{
    private const WIDTH_EPSILON = 0.000001;

    public static function columnCount(AstNode $table): int
    {
        $columnCount = max(
            self::tableAttributeColumnCount($table->attr('alignments', [])),
            self::tableAttributeColumnCount($table->attr('widths', []))
        );

        foreach ($table->children as $section) {
            if ($section->type === 'table_body') {
                $columnCount = max($columnCount, self::columnCountForRows(self::bodyRows($section)));
                continue;
            }

            if ($section->type !== 'table_head' && $section->type !== 'table_foot') {
                continue;
            }

            $columnCount = max($columnCount, self::columnCountForRows(self::sectionRows($section)));
        }

        return $columnCount;
    }

    /**
     * @param list<AstNode> $rows
     */
    public static function columnCountForRows(array $rows): int
    {
        $rows = array_values(array_filter(
            $rows,
            static fn (AstNode $row): bool => $row->type === 'table_row'
        ));
        $rowCount = count($rows);
        $columnCount = 0;
        $activeRowspans = [];

        foreach ($rows as $rowIndex => $row) {
            $previousActiveColumns = self::activeColumns($activeRowspans);
            $consumedActiveColumns = [];
            $column = 0;

            foreach ($row->children as $cell) {
                if ($cell->type !== 'table_cell') {
                    continue;
                }

                self::skipCoveredColumns($activeRowspans, $column, $consumedActiveColumns);

                $colspan = self::cellColspan($cell);
                $rowspan = self::cellRowspanForRows($cell, $rowIndex, $rowCount);
                if ($rowspan > 1) {
                    self::activateRowspan($activeRowspans, $column, $colspan, $rowspan);
                }

                $column += $colspan;
                $columnCount = max($columnCount, $column);
            }

            self::consumeUnusedActiveColumns($activeRowspans, $previousActiveColumns, $consumedActiveColumns);
            $columnCount = max($columnCount, $column);
        }

        return $columnCount;
    }

    /**
     * @param list<AstNode> $rows
     * @return list<array{row:AstNode,cells:list<array{node:AstNode,column:int,colspan:int,rowspan:int,rowspanToEnd:bool,sourceCell:int,sourceColumn:int}>}>
     */
    public static function layoutRows(array $rows, int $columnCount): array
    {
        $rows = array_values(array_filter(
            $rows,
            static fn (AstNode $row): bool => $row->type === 'table_row'
        ));
        $rowCount = count($rows);
        $columnCount = max(0, $columnCount);
        if ($columnCount === 0) {
            $emptyRows = [];
            foreach ($rows as $row) {
                $emptyRows[] = [
                    'row' => $row,
                    'cells' => [],
                ];
            }

            return $emptyRows;
        }

        $layoutRows = [];
        $activeRowspans = [];

        foreach ($rows as $rowIndex => $row) {
            $previousActiveColumns = self::activeColumns($activeRowspans);
            $consumedActiveColumns = [];
            $layoutCells = [];
            $column = 0;
            $sourceCell = 0;
            $sourceColumn = 0;

            foreach ($row->children as $cell) {
                if ($cell->type !== 'table_cell') {
                    continue;
                }

                $cellSourceCell = $sourceCell;
                $cellSourceColumn = $sourceColumn;
                $rawColspan = self::cellColspan($cell);
                self::skipCoveredColumns($activeRowspans, $column, $consumedActiveColumns);
                if ($column >= $columnCount) {
                    break;
                }

                $colspan = min($rawColspan, $columnCount - $column);
                $rowspanToEnd = self::cellRowspanToEnd($cell);
                $rowspan = min(self::cellRowspanForRows($cell, $rowIndex, $rowCount), max(1, $rowCount - $rowIndex));
                $layoutCells[] = [
                    'node' => $cell,
                    'column' => $column,
                    'colspan' => $colspan,
                    'rowspan' => $rowspan,
                    'rowspanToEnd' => $rowspanToEnd,
                    'sourceCell' => $cellSourceCell,
                    'sourceColumn' => $cellSourceColumn,
                ];

                if ($rowspan > 1) {
                    self::activateRowspan($activeRowspans, $column, $colspan, $rowspan);
                }

                $column += $colspan;
                $sourceColumn += $rawColspan;
                $sourceCell++;
            }

            self::consumeUnusedActiveColumns($activeRowspans, $previousActiveColumns, $consumedActiveColumns);
            $layoutRows[] = [
                'row' => $row,
                'cells' => $layoutCells,
            ];
        }

        return $layoutRows;
    }

    /**
     * @return list<array{
     *     section:string,
     *     columnCount:int,
     *     rowEntries:list<array{row:AstNode,header:bool,rowHeadColumns:int,rowRole:string}>,
     *     rows:list<list<array<string, mixed>>>
     * }>
     */
    public static function sectionGrids(AstNode $table): array
    {
        $columnCount = self::columnCount($table);
        $sectionGrids = [];

        foreach (self::sectionRowGroups($table, $columnCount) as $group) {
            $sectionGrids[] = [
                'section' => $group['section'],
                'node' => $group['node'],
                'columnCount' => $columnCount,
                'rowEntries' => $group['rowEntries'],
                'rows' => self::sectionGridForEntries($group['rowEntries'], $columnCount),
            ];
        }

        return $sectionGrids;
    }

    /**
     * @return list<array{
     *     section:string,
     *     rows:list<AstNode>,
     *     rowEntries:list<array{row:AstNode,header:bool,rowHeadColumns:int,rowRole:string}>
     * }>
     */
    public static function sectionRowEntryGroups(AstNode $table, ?int $columnCount = null): array
    {
        return self::sectionRowGroups($table, $columnCount ?? self::columnCount($table));
    }

    /**
     * @param list<AstNode> $rows
     * @return list<list<array<string, mixed>>>
     */
    public static function sectionGrid(array $rows, int $columnCount): array
    {
        $layoutRows = self::layoutRows($rows, $columnCount);
        $columnCount = max(0, $columnCount);
        $grid = [];

        foreach ($layoutRows as $rowIndex => $_layoutRow) {
            $grid[$rowIndex] = [];
            for ($column = 0; $column < $columnCount; $column++) {
                $grid[$rowIndex][$column] = self::missingGridSlot($rowIndex, $column);
            }
        }

        foreach ($layoutRows as $rowIndex => $layoutRow) {
            foreach ($layoutRow['cells'] as $cell) {
                $anchorColumn = $cell['column'];
                if ($anchorColumn >= $columnCount) {
                    continue;
                }

                $grid[$rowIndex][$anchorColumn] = self::cellGridSlot($rowIndex, $cell, count($layoutRows), $columnCount);
                for ($column = $anchorColumn + 1; $column < $anchorColumn + $cell['colspan'] && $column < $columnCount; $column++) {
                    $grid[$rowIndex][$column] = self::coveredGridSlot($rowIndex, $column, $rowIndex, $anchorColumn, 'colspan', $cell);
                }

                $rowLimit = min(count($layoutRows), $rowIndex + $cell['rowspan']);
                for ($coveredRow = $rowIndex + 1; $coveredRow < $rowLimit; $coveredRow++) {
                    for ($column = $anchorColumn; $column < $anchorColumn + $cell['colspan'] && $column < $columnCount; $column++) {
                        $covering = $column === $anchorColumn ? 'rowspan' : 'rowspan-colspan';
                        $grid[$coveredRow][$column] = self::coveredGridSlot(
                            $coveredRow,
                            $column,
                            $rowIndex,
                            $anchorColumn,
                            $covering,
                            $cell
                        );
                    }
                }
            }
        }

        return $grid;
    }

    /**
     * @return list<array{
     *     section:string,
     *     row:int,
     *     column:int,
     *     endColumn:int,
     *     rawEndColumn:int,
     *     columns:list<int>,
     *     sourceCell:int,
     *     sourceColumn:int,
     *     sourceEndColumn:int,
     *     sourceColumns:list<int>,
     *     visualShift:int,
     *     colspan:int,
     *     rawColspan:int,
     *     rowspan:int,
     *     rawRowspan:int,
     *     rowspanToEnd?:bool,
     *     alignment:string,
     *     columnAlignments:list<string>,
     *     widths:list<?float>,
     *     declaredColumns:list<bool>,
     *     columnSources?:list<array<string, mixed>|null>,
     *     occupiedSlots:list<array{row:int,column:int,covering:string}>,
     *     node:AstNode
     * }>
     */
    public static function cellCoverage(AstNode $table): array
    {
        $columnCount = self::columnCount($table);
        if ($columnCount <= 0) {
            return [];
        }

        $columnSpecs = self::columnSpecs($table, $columnCount);
        $coverage = [];
        foreach (self::sectionRowGroups($table, $columnCount) as $group) {
            $layoutRows = self::layoutRows($group['rows'], $columnCount);
            $sectionRowCount = count($layoutRows);
            foreach ($layoutRows as $rowIndex => $layoutRow) {
                $rowEntry = $group['rowEntries'][$rowIndex] ?? [
                    'header' => false,
                    'rowHeadColumns' => 0,
                    'rowRole' => $group['section'],
                ];
                $headerRow = (bool) $rowEntry['header'];
                $rowHeadColumns = (int) $rowEntry['rowHeadColumns'];
                foreach ($layoutRow['cells'] as $cell) {
                    $columns = [];
                    $columnAlignments = [];
                    $widths = [];
                    $declaredColumns = [];
                    $columnSources = [];
                    $hasColumnSources = false;
                    for ($column = $cell['column']; $column < $cell['column'] + $cell['colspan'] && $column < $columnCount; $column++) {
                        $spec = $columnSpecs[$column] ?? [
                            'alignment' => 'default',
                            'width' => null,
                            'declared' => false,
                        ];
                        $columns[] = $column;
                        $columnAlignments[] = (string) $spec['alignment'];
                        $widths[] = $spec['width'];
                        $declaredColumns[] = (bool) $spec['declared'];
                        $source = isset($spec['source']) && is_array($spec['source']) ? $spec['source'] : null;
                        $columnSources[] = $source;
                        $hasColumnSources = $hasColumnSources || $source !== null;
                    }

                    $rawColspan = self::cellColspan($cell['node']);
                    $rawRowspan = self::cellRowspanForRows($cell['node'], $rowIndex, $sectionRowCount);
                    $sourceEndColumn = $cell['sourceColumn'] + $rawColspan;
                    $record = [
                        'section' => $group['section'],
                        'row' => $rowIndex,
                        'column' => $cell['column'],
                        'endColumn' => $cell['column'] + $cell['colspan'],
                        'rawEndColumn' => $cell['column'] + $rawColspan,
                        'columns' => $columns,
                        'rowRole' => (string) $rowEntry['rowRole'],
                        'headerRow' => $headerRow,
                        'rowHeadColumns' => $rowHeadColumns,
                        'headerCell' => self::isHeaderCell($headerRow, $rowHeadColumns, $cell['column'], $cell['node']),
                        'sourceCell' => $cell['sourceCell'],
                        'sourceColumn' => $cell['sourceColumn'],
                        'sourceEndColumn' => $sourceEndColumn,
                        'sourceColumns' => self::sourceColumns($cell['sourceColumn'], $rawColspan),
                        'visualShift' => $cell['column'] - $cell['sourceColumn'],
                        'colspan' => $cell['colspan'],
                        'rawColspan' => $rawColspan,
                        'rowspan' => $cell['rowspan'],
                        'rawRowspan' => $rawRowspan,
                        'alignment' => self::cellAlignment($table, $cell['column'], $cell['node']),
                        'columnAlignments' => $columnAlignments,
                        'widths' => $widths,
                        'declaredColumns' => $declaredColumns,
                        'occupiedSlots' => self::occupiedSlotRecords(
                            $rowIndex,
                            $cell['column'],
                            $cell['colspan'],
                            $cell['rowspan'],
                            $sectionRowCount,
                            $columnCount
                        ),
                        'node' => $cell['node'],
                    ];
                    if (($cell['rowspanToEnd'] ?? false) === true) {
                        $record['rowspanToEnd'] = true;
                    }
                    if ($hasColumnSources) {
                        $record['columnSources'] = $columnSources;
                    }

                    $coverage[] = $record;
                }
            }
        }

        return $coverage;
    }

    /**
     * @return array<string, array{id?:string,scope?:string,headers?:list<string>}>
     */
    public static function accessibilityAttributes(AstNode $table, string $idPrefix = 'pandoc-table'): array
    {
        $idPrefix = self::normalizeHtmlId($idPrefix);
        $sectionGrids = self::sectionGrids($table);
        $headers = [];
        $attributes = [];

        foreach ($sectionGrids as $sectionGrid) {
            $section = (string) $sectionGrid['section'];
            foreach ($sectionGrid['rows'] as $rowIndex => $slots) {
                foreach ($slots as $slot) {
                    if (($slot['kind'] ?? '') !== 'cell' || ($slot['headerCell'] ?? false) !== true) {
                        continue;
                    }

                    $key = self::accessibilityKey(
                        $section,
                        (int) $rowIndex,
                        (int) ($slot['sourceCell'] ?? 0),
                        (int) ($slot['sourceColumn'] ?? 0)
                    );
                    $node = $slot['node'] ?? null;
                    $sourceId = self::cellSourceHtmlId($node);
                    $id = $sourceId !== '' ? $sourceId : $idPrefix . '-' . self::normalizeHtmlId($section)
                        . '-r' . ((int) $rowIndex + 1)
                        . 'c' . ((int) ($slot['anchorColumn'] ?? $slot['column'] ?? 0) + 1);
                    $sourceScope = self::cellSourceHtmlScope($node);
                    $scope = $sourceScope === '' ? self::headerScope($slot) : $sourceScope;
                    $sourceHeaders = self::cellSourceHtmlHeaders($node);
                    $columns = [];
                    $startColumn = (int) ($slot['anchorColumn'] ?? $slot['column'] ?? 0);
                    $colspan = max(1, (int) ($slot['colspan'] ?? 1));
                    for ($column = $startColumn; $column < $startColumn + $colspan; $column++) {
                        $columns[] = $column;
                    }

                    $record = [
                        'key' => $key,
                        'id' => $id,
                        'section' => $section,
                        'row' => (int) $rowIndex,
                        'columns' => $columns,
                        'rowspan' => max(1, (int) ($slot['rowspan'] ?? 1)),
                        'scope' => $scope,
                    ];
                    $headers[] = $record;
                    $attributes[$key] = [
                        'id' => $id,
                        'scope' => $scope,
                        'headers' => $sourceHeaders,
                    ];
                }
            }
        }

        foreach ($sectionGrids as $sectionGrid) {
            $section = (string) $sectionGrid['section'];
            foreach ($sectionGrid['rows'] as $rowIndex => $slots) {
                foreach ($slots as $slot) {
                    if (($slot['kind'] ?? '') !== 'cell' || ($slot['headerCell'] ?? false) === true) {
                        continue;
                    }

                    $columns = [];
                    $startColumn = (int) ($slot['anchorColumn'] ?? $slot['column'] ?? 0);
                    $colspan = max(1, (int) ($slot['colspan'] ?? 1));
                    for ($column = $startColumn; $column < $startColumn + $colspan; $column++) {
                        $columns[] = $column;
                    }

                    $headerIds = [];
                    foreach ($headers as $header) {
                        $scope = (string) $header['scope'];
                        $applies = false;
                        if (($scope === 'col' || $scope === 'colgroup') && self::columnsOverlap($columns, $header['columns'])) {
                            $applies = $header['section'] === 'head'
                                || ($header['section'] === $section && (int) $header['row'] <= (int) $rowIndex);
                        } elseif ($scope === 'row' && $header['section'] === $section) {
                            $applies = (int) $rowIndex === (int) $header['row'];
                        } elseif ($scope === 'rowgroup' && $header['section'] === $section) {
                            $headerRow = (int) $header['row'];
                            $applies = (int) $rowIndex >= $headerRow && (int) $rowIndex < $headerRow + (int) $header['rowspan'];
                        }

                        if ($applies) {
                            $headerIds[] = (string) $header['id'];
                        }
                    }

                    $key = self::accessibilityKey(
                        $section,
                        (int) $rowIndex,
                        (int) ($slot['sourceCell'] ?? 0),
                        (int) ($slot['sourceColumn'] ?? 0)
                    );
                    $sourceHeaders = self::cellSourceHtmlHeaders($slot['node'] ?? null);
                    $attributes[$key] = [
                        'headers' => $sourceHeaders === [] ? array_values(array_unique($headerIds)) : $sourceHeaders,
                    ];
                }
            }
        }

        return $attributes;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function diagnostics(AstNode $table): array
    {
        $diagnostics = self::columnDiagnostics($table);
        array_push($diagnostics, ...self::widthDiagnostics($table));
        $declaredColumnCount = self::declaredColumnCount($table);
        foreach (self::sectionRowGroups($table, null) as $group) {
            $rows = $group['rows'];
            $rowCount = count($rows);
            array_push($diagnostics, ...self::rowspanOverlapDiagnostics($group['section'], $rows, $declaredColumnCount));
            $layoutColumnCount = max(1, $declaredColumnCount, self::columnCountForRows($rows));
            $layoutRows = self::layoutRows($rows, $layoutColumnCount);
            foreach ($layoutRows as $rowIndex => $layoutRow) {
                $availableRows = max(1, $rowCount - $rowIndex);
                foreach ($layoutRow['cells'] as $cell) {
                    $rowspan = self::cellRowspanForRows($cell['node'], $rowIndex, $rowCount);
                    if ($rowspan <= $availableRows) {
                        if ($declaredColumnCount > 0) {
                            self::appendDeclaredColumnDiagnostic(
                                $diagnostics,
                                $group['section'],
                                $rowIndex,
                                $cell,
                                $declaredColumnCount
                            );
                        }

                        continue;
                    }

                    $diagnostics[] = [
                        'code' => 'rowspan-crosses-section-boundary',
                        'section' => $group['section'],
                        'row' => $rowIndex,
                        'column' => $cell['column'],
                        'sourceCell' => $cell['sourceCell'],
                        'sourceColumn' => $cell['sourceColumn'],
                        'rowspan' => $rowspan,
                        'availableRows' => $availableRows,
                    ];

                    if ($declaredColumnCount > 0) {
                        self::appendDeclaredColumnDiagnostic(
                            $diagnostics,
                            $group['section'],
                            $rowIndex,
                            $cell,
                            $declaredColumnCount
                        );
                    }
                }
            }
        }

        return $diagnostics;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function widthDiagnostics(AstNode $table): array
    {
        $summary = self::columnWidthSummary($table);
        if (($summary['overfull'] ?? false) !== true) {
            return [];
        }

        $columns = [];
        foreach ($summary['percentWidths'] as $column => $percentWidth) {
            if ($percentWidth !== null) {
                $columns[] = (int) $column;
            }
        }

        return [[
            'code' => 'table-widths-exceed-full-width',
            'source' => 'table-widths',
            'columnCount' => (int) $summary['columnCount'],
            'columns' => $columns,
            'widthTotal' => (float) $summary['widthTotal'],
            'overflowAmount' => (float) $summary['overflowAmount'],
            'normalizedWidths' => $summary['normalizedWidths'],
            'percentWidths' => $summary['percentWidths'],
        ]];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function columnDiagnostics(AstNode $table): array
    {
        $diagnostics = $table->attr('columnDiagnostics', []);
        if (!is_array($diagnostics)) {
            return [];
        }

        $records = [];
        foreach ($diagnostics as $diagnostic) {
            if (!is_array($diagnostic)) {
                continue;
            }

            $record = [];
            foreach ($diagnostic as $key => $value) {
                $key = trim((string) $key);
                if ($key === '') {
                    continue;
                }

                if (is_scalar($value) || $value === null) {
                    $record[$key] = $value;
                    continue;
                }

                if (!is_array($value) || !array_is_list($value)) {
                    continue;
                }

                $list = [];
                foreach ($value as $item) {
                    if (is_scalar($item) || $item === null) {
                        $list[] = $item;
                    }
                }
                $record[$key] = $list;
            }

            if (isset($record['code']) && is_scalar($record['code']) && trim((string) $record['code']) !== '') {
                $records[] = $record;
            }
        }

        return $records;
    }

    /**
     * @return list<string>
     */
    public static function alignments(AstNode $table, int $columnCount): array
    {
        $alignments = $table->attr('alignments', []);
        if (!is_array($alignments)) {
            $alignments = [];
        }

        $normalized = [];
        for ($index = 0; $index < max(0, $columnCount); $index++) {
            $alignment = (string) ($alignments[$index] ?? 'default');
            $normalized[] = self::normalizeAlignment($alignment);
        }

        return $normalized;
    }

    public static function cellAlignment(AstNode $table, int $column, AstNode $cell): string
    {
        $alignment = (string) $cell->attr('align', '');
        if ($alignment !== '') {
            return self::normalizeAlignment($alignment);
        }

        $alignments = $table->attr('alignments', []);
        if (is_array($alignments)) {
            return self::normalizeAlignment((string) ($alignments[$column] ?? 'default'));
        }

        return 'default';
    }

    /**
     * @return list<array{column:int,alignment:string,width:?float,declared:bool,source?:array<string, mixed>}>
     */
    public static function columnSpecs(AstNode $table, int $columnCount): array
    {
        $columnCount = max(0, $columnCount);
        $alignments = self::alignments($table, $columnCount);
        $widths = $table->attr('widths', []);
        if (!is_array($widths)) {
            $widths = [];
        } else {
            $widths = array_values($widths);
        }

        $declaredColumnCount = self::declaredColumnCount($table);
        $columnSources = self::columnSources($table);
        $widthTotal = self::positiveWidthTotal($widths, $columnCount);
        $specs = [];
        for ($column = 0; $column < $columnCount; $column++) {
            $width = null;
            if (array_key_exists($column, $widths) && is_numeric($widths[$column]) && (float) $widths[$column] > 0.0) {
                $width = (float) $widths[$column];
            }

            $spec = [
                'column' => $column,
                'alignment' => $alignments[$column] ?? 'default',
                'width' => $width,
                'normalizedWidth' => $width !== null && $widthTotal > 0.0 ? self::roundWidth($width / $widthTotal) : null,
                'percentWidth' => $width !== null ? self::roundWidth($width * 100.0) : null,
                'declared' => $column < $declaredColumnCount,
            ];
            if (isset($columnSources[$column])) {
                $spec['source'] = $columnSources[$column];
            }

            $specs[] = $spec;
        }

        return $specs;
    }

    /**
     * @return array{
     *     columnCount:int,
     *     hasExplicitWidths:bool,
     *     explicitWidthCount:int,
     *     validWidthCount:int,
     *     missingWidthCount:int,
     *     hasCompleteWidths:bool,
     *     hasPartialWidths:bool,
     *     widthTotal:float,
     *     normalizedWidths:list<?float>,
     *     percentWidths:list<?float>,
     *     missingColumns:list<int>,
     *     overfull:bool,
     *     underfull:bool,
     *     overflowAmount:float
     * }
     */
    public static function columnWidthSummary(AstNode $table, ?int $columnCount = null): array
    {
        $columnCount = max(0, $columnCount ?? self::columnCount($table));
        $rawWidths = $table->attr('widths', []);
        $hasExplicitWidths = is_array($rawWidths) && $rawWidths !== [];
        $explicitWidthCount = is_array($rawWidths) ? count($rawWidths) : 0;
        $specs = self::columnSpecs($table, $columnCount);

        $widthTotal = 0.0;
        $validWidthCount = 0;
        $missingColumns = [];
        foreach ($specs as $spec) {
            $width = $spec['width'];
            if ($width === null) {
                $missingColumns[] = (int) $spec['column'];
                continue;
            }

            $widthTotal += $width;
            $validWidthCount++;
        }

        $normalizedWidths = [];
        $percentWidths = [];
        foreach ($specs as $spec) {
            $width = $spec['width'];
            $normalizedWidths[] = $width !== null && $widthTotal > 0.0 ? self::roundWidth($width / $widthTotal) : null;
            $percentWidths[] = $width !== null ? self::roundWidth($width * 100.0) : null;
        }

        $hasCompleteWidths = $columnCount > 0 && $validWidthCount === $columnCount;
        $hasPartialWidths = $hasExplicitWidths && $validWidthCount > 0 && !$hasCompleteWidths;
        $overfull = $widthTotal > 1.0 + self::WIDTH_EPSILON;
        $underfull = $hasCompleteWidths && $widthTotal > self::WIDTH_EPSILON && $widthTotal < 1.0 - self::WIDTH_EPSILON;

        return [
            'columnCount' => $columnCount,
            'hasExplicitWidths' => $hasExplicitWidths,
            'explicitWidthCount' => $explicitWidthCount,
            'validWidthCount' => $validWidthCount,
            'missingWidthCount' => count($missingColumns),
            'hasCompleteWidths' => $hasCompleteWidths,
            'hasPartialWidths' => $hasPartialWidths,
            'widthTotal' => self::roundWidth($widthTotal),
            'normalizedWidths' => $normalizedWidths,
            'percentWidths' => $percentWidths,
            'missingColumns' => $missingColumns,
            'overfull' => $overfull,
            'underfull' => $underfull,
            'overflowAmount' => $overfull ? self::roundWidth($widthTotal - 1.0) : 0.0,
        ];
    }

    public static function rowHeadColumns(AstNode $body, int $columnCount): int
    {
        $value = $body->attr('rowHeadColumns', 0);
        if (is_string($value)) {
            $value = trim($value);
            if ($value === '' || !is_numeric($value)) {
                return 0;
            }
        } elseif (!is_int($value) && !is_float($value)) {
            return 0;
        }

        $count = (int) $value;
        if ($count <= 0) {
            return 0;
        }

        return min($count, max(0, $columnCount));
    }

    public static function isHeaderCell(bool $headerRow, int $rowHeadColumns, int $column, AstNode $cell): bool
    {
        return $headerRow || $cell->attr('header') === true || ($rowHeadColumns > 0 && $column < $rowHeadColumns);
    }

    public static function accessibilityKey(string $section, int $row, int $sourceCell, int $sourceColumn): string
    {
        return $section . ':' . $row . ':' . $sourceCell . ':' . $sourceColumn;
    }

    /**
     * @param array{accessibility?:bool,idPrefix?:string,writers?:list<string>} $options
     * @return array{
     *     caption:string,
     *     columnCount:int,
     *     declaredColumnCount:int,
     *     columns:list<array{column:int,alignment:string,width:?float,declared:bool}>,
     *     captions:array<string, array<string, mixed>>,
     *     sections:list<array<string, mixed>>,
     *     coverage:list<array<string, mixed>>,
     *     diagnostics:list<array<string, mixed>>,
     *     accessibility:array<string, array{id?:string,scope?:string,headers?:list<string>}>,
     *     summary:array<string, mixed>
     * }
     */
    public static function reviewPacket(AstNode $table, array $options = []): array
    {
        $columnCount = self::columnCount($table);
        $sections = self::sectionGrids($table);
        $coverageRecords = self::cellCoverage($table);
        $coverage = self::serializableCoverage($coverageRecords);
        $diagnostics = self::diagnostics($table);
        $captions = self::captionMetadata($table);
        $widthSummary = self::columnWidthSummary($table, $columnCount);
        $writerDowngrades = [];
        foreach (self::reviewPacketWriters($options['writers'] ?? ['markdown']) as $writer) {
            $writerDowngrades[$writer] = self::writerDowngradeDiagnosticsFromCoverage($coverageRecords, $writer);
        }
        $includeAccessibility = ($options['accessibility'] ?? true) !== false;
        $accessibility = $includeAccessibility
            ? self::accessibilityAttributes($table, self::reviewPacketIdPrefix($table, $options))
            : [];

        $packet = [
            'caption' => (string) $table->attr('caption', ''),
            'captions' => $captions,
            'columnCount' => $columnCount,
            'declaredColumnCount' => self::declaredColumnCount($table),
            'columns' => self::columnSpecs($table, $columnCount),
            'widthSummary' => $widthSummary,
            'sections' => self::serializableSectionGrids($sections),
            'coverage' => $coverage,
            'diagnostics' => $diagnostics,
            'writerDowngrades' => $writerDowngrades,
            'accessibility' => $accessibility,
            'summary' => self::reviewPacketSummary($sections, $coverage, $diagnostics, $writerDowngrades, $captions),
        ];

        $sourceAttributes = self::sourceAttributeSummary($table);
        if ($sourceAttributes !== []) {
            $packet['sourceAttributes'] = $sourceAttributes;
        }

        return $packet;
    }

    /**
     * @param array{accessibility?:bool,idPrefix?:string} $options
     */
    public static function withReviewPacket(AstNode $table, array $options = []): AstNode
    {
        if ($table->type !== 'table') {
            return $table;
        }

        return new AstNode(
            $table->type,
            array_replace($table->attrs, ['tableGeometry' => self::reviewPacket($table, $options)]),
            $table->children
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function writerDowngradeDiagnostics(AstNode $table, string $writer): array
    {
        return self::writerDowngradeDiagnosticsFromCoverage(self::cellCoverage($table), $writer);
    }

    private static function normalizeAlignment(string $alignment): string
    {
        return in_array($alignment, ['left', 'right', 'center'], true) ? $alignment : 'default';
    }

    /**
     * @param array<string, mixed> $slot
     */
    private static function headerScope(array $slot): string
    {
        $headerRow = (bool) ($slot['headerRow'] ?? false);
        $rowHeadColumns = (int) ($slot['rowHeadColumns'] ?? 0);
        $anchorColumn = (int) ($slot['anchorColumn'] ?? $slot['column'] ?? 0);
        $colspan = max(1, (int) ($slot['colspan'] ?? 1));
        $rowspan = max(1, (int) ($slot['rowspan'] ?? 1));

        if (!$headerRow && $rowHeadColumns > 0 && $anchorColumn < $rowHeadColumns) {
            return $rowspan > 1 ? 'rowgroup' : 'row';
        }

        if ($headerRow) {
            return $colspan > 1 ? 'colgroup' : 'col';
        }

        return $rowspan > 1 ? 'rowgroup' : 'row';
    }

    /**
     * @param list<int> $left
     * @param list<int> $right
     */
    private static function columnsOverlap(array $left, array $right): bool
    {
        foreach ($left as $column) {
            if (in_array($column, $right, true)) {
                return true;
            }
        }

        return false;
    }

    private static function normalizeHtmlId(string $value): string
    {
        $id = strtolower(trim($value));
        $id = preg_replace('/[^a-z0-9_-]+/', '-', $id) ?? '';
        $id = trim($id, '-');
        if ($id === '') {
            return 'pandoc-table';
        }

        if (preg_match('/^[a-z]/', $id) !== 1) {
            return 'pandoc-' . $id;
        }

        return $id;
    }

    /**
     * @param array{accessibility?:bool,idPrefix?:string} $options
     */
    private static function reviewPacketIdPrefix(AstNode $table, array $options): string
    {
        $prefix = trim((string) ($options['idPrefix'] ?? ''));
        if ($prefix !== '') {
            return $prefix;
        }

        $prefix = trim((string) $table->attr('accessibilityIdPrefix', ''));
        if ($prefix !== '') {
            return $prefix;
        }

        $htmlAttributes = $table->attr('htmlAttributes', []);
        if (is_array($htmlAttributes) && isset($htmlAttributes['id'])) {
            $prefix = trim((string) $htmlAttributes['id']);
            if ($prefix !== '') {
                return $prefix;
            }
        }

        $prefix = trim((string) $table->attr('id', ''));

        return $prefix === '' ? 'pandoc-table' : $prefix;
    }

    private static function cellSourceHtmlId(mixed $node): string
    {
        if (!$node instanceof AstNode) {
            return '';
        }

        $htmlAttributes = $node->attr('htmlAttributes', []);
        if (is_array($htmlAttributes) && isset($htmlAttributes['id'])) {
            $id = trim((string) $htmlAttributes['id']);
            if ($id !== '') {
                return $id;
            }
        }

        return trim((string) $node->attr('id', ''));
    }

    private static function cellSourceHtmlScope(mixed $node): string
    {
        $scope = strtolower(self::sourceHtmlAttribute($node, 'scope'));

        return in_array($scope, ['col', 'row', 'colgroup', 'rowgroup'], true) ? $scope : '';
    }

    /**
     * @return list<string>
     */
    private static function cellSourceHtmlHeaders(mixed $node): array
    {
        $headers = preg_split('/\s+/', self::sourceHtmlAttribute($node, 'headers'), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        return array_values(array_unique(array_map(
            static fn (string $header): string => trim($header),
            $headers
        )));
    }

    private static function sourceHtmlAttribute(mixed $node, string $name): string
    {
        if (!$node instanceof AstNode) {
            return '';
        }

        foreach (['htmlAttributes', 'attributes'] as $attributeName) {
            $attributes = $node->attr($attributeName, []);
            if (!is_array($attributes)) {
                continue;
            }

            $attributes = array_change_key_case($attributes, CASE_LOWER);
            if (isset($attributes[$name]) && is_scalar($attributes[$name])) {
                return trim((string) $attributes[$name]);
            }
        }

        return '';
    }

    /**
     * @param list<array{
     *     section:string,
     *     columnCount:int,
     *     rowEntries:list<array{row:AstNode,header:bool,rowHeadColumns:int,rowRole:string}>,
     *     rows:list<list<array<string, mixed>>>
     * }> $sections
     * @return list<array<string, mixed>>
     */
    private static function serializableSectionGrids(array $sections): array
    {
        $reports = [];
        foreach ($sections as $section) {
            $rows = [];
            foreach ($section['rowEntries'] as $rowIndex => $entry) {
                $slots = [];
                foreach ($section['rows'][$rowIndex] ?? [] as $slot) {
                    $slots[] = self::serializableGridSlot($slot);
                }

                $rowRecord = [
                    'row' => $rowIndex,
                    'rowRole' => (string) $entry['rowRole'],
                    'header' => (bool) $entry['header'],
                    'rowHeadColumns' => (int) $entry['rowHeadColumns'],
                    'slots' => $slots,
                ];
                $sourceAttributes = self::sourceAttributeSummary($entry['row'] ?? null);
                if ($sourceAttributes !== []) {
                    $rowRecord['sourceAttributes'] = $sourceAttributes;
                }

                $rows[] = $rowRecord;
            }

            $report = [
                'section' => (string) $section['section'],
                'columnCount' => (int) $section['columnCount'],
                'rowCount' => count($rows),
                'summary' => self::sectionGridSummary($section['rows']),
                'rows' => $rows,
            ];
            $sourceAttributes = self::sourceAttributeSummary($section['node'] ?? null);
            if ($sourceAttributes !== []) {
                $report['sourceAttributes'] = $sourceAttributes;
            }

            $reports[] = $report;
        }

        return $reports;
    }

    /**
     * @param array<string, mixed> $slot
     * @return array<string, mixed>
     */
    private static function serializableGridSlot(array $slot): array
    {
        $node = $slot['node'] ?? null;
        unset($slot['node']);
        if ($node instanceof AstNode) {
            $slot['text'] = self::plainText($node);
            $sourceAttributes = self::sourceAttributeSummary($node);
            if ($sourceAttributes !== []) {
                $slot['sourceAttributes'] = $sourceAttributes;
            }
        }

        return $slot;
    }

    /**
     * @return array{long:array<string, mixed>,short:array<string, mixed>}
     */
    private static function captionMetadata(AstNode $table): array
    {
        return [
            'long' => self::captionRecord($table, 'caption', 'captionInlines'),
            'short' => self::captionRecord($table, 'shortCaption', 'shortCaptionInlines'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function captionRecord(AstNode $table, string $textAttribute, string $inlineAttribute): array
    {
        $rawText = trim((string) $table->attr($textAttribute, ''));
        $inlines = self::inlineNodes($table->attr($inlineAttribute, []));
        $inlineText = self::plainTextFromNodes($inlines);
        $text = $inlines === [] ? $rawText : $inlineText;
        $source = $inlines === [] ? ($rawText === '' ? 'none' : $textAttribute) : $inlineAttribute;

        $record = [
            'text' => $text,
            'source' => $source,
            'inlineCount' => count($inlines),
            'inlineTypes' => self::inlineTypes($inlines),
            'hasInlineFormatting' => self::inlinesHaveFormatting($inlines),
            'inlines' => self::serializableInlines($inlines),
        ];
        if ($rawText !== '' && $rawText !== $text) {
            $record['rawText'] = $rawText;
        }

        return $record;
    }

    /**
     * @return list<AstNode>
     */
    private static function inlineNodes(mixed $nodes): array
    {
        if (!is_array($nodes)) {
            return [];
        }

        return array_values(array_filter($nodes, static fn (mixed $node): bool => $node instanceof AstNode));
    }

    /**
     * @param list<AstNode> $nodes
     * @return list<string>
     */
    private static function inlineTypes(array $nodes): array
    {
        $types = [];
        foreach ($nodes as $node) {
            $type = trim($node->type);
            if ($type !== '') {
                $types[] = $type;
            }
        }

        return array_values(array_unique($types));
    }

    /**
     * @param list<AstNode> $nodes
     */
    private static function inlinesHaveFormatting(array $nodes): bool
    {
        foreach ($nodes as $node) {
            if (!in_array($node->type, ['text', 'space', 'softbreak', 'linebreak'], true)) {
                return true;
            }

            if (self::inlinesHaveFormatting($node->children)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param list<AstNode> $nodes
     * @return list<array<string, mixed>>
     */
    private static function serializableInlines(array $nodes): array
    {
        $records = [];
        foreach ($nodes as $node) {
            $records[] = self::serializableInline($node);
        }

        return $records;
    }

    /**
     * @return array<string, mixed>
     */
    private static function serializableInline(AstNode $node): array
    {
        $record = [
            'type' => $node->type,
            'text' => self::plainText($node),
        ];
        foreach (['url', 'title', 'alt', 'tex', 'format', 'html'] as $attribute) {
            $value = $node->attr($attribute, null);
            if (is_scalar($value) && (string) $value !== '') {
                $record[$attribute] = (string) $value;
            }
        }

        $sourceAttributes = self::sourceAttributeSummary($node);
        if ($sourceAttributes !== []) {
            $record['sourceAttributes'] = $sourceAttributes;
        }
        if ($node->children !== []) {
            $record['children'] = self::serializableInlines($node->children);
        }

        return $record;
    }

    /**
     * @param list<list<array<string, mixed>>> $rows
     * @return array<string, mixed>
     */
    private static function sectionGridSummary(array $rows): array
    {
        $cellCount = 0;
        $headerCellCount = 0;
        $coveredSlotCount = 0;
        $missingSlotCount = 0;
        $nestedTableCount = 0;
        $nestedTableCellCount = 0;
        $nestedTableDiagnosticCount = 0;
        $nestedTableCaptions = [];
        $nestedTableDescendantCaptions = [];
        $nestedTableDiagnosticCodes = [];

        foreach ($rows as $slots) {
            foreach ($slots as $slot) {
                $kind = (string) ($slot['kind'] ?? '');
                if ($kind === 'covered') {
                    $coveredSlotCount++;
                    continue;
                }

                if ($kind === 'missing') {
                    $missingSlotCount++;
                    continue;
                }

                if ($kind !== 'cell') {
                    continue;
                }

                $cellCount++;
                if (($slot['headerCell'] ?? false) === true) {
                    $headerCellCount++;
                }

                $node = $slot['node'] ?? null;
                if (!$node instanceof AstNode) {
                    continue;
                }

                $nestedTables = self::nestedTableSummaries($node);
                if ($nestedTables === []) {
                    continue;
                }

                $nestedTableCellCount++;
                $nestedTableCount += count($nestedTables);
                foreach ($nestedTables as $nestedTable) {
                    $caption = (string) ($nestedTable['caption'] ?? '');
                    if ($caption !== '') {
                        $nestedTableCaptions[] = $caption;
                        $path = $nestedTable['path'] ?? [];
                        if (is_array($path) && count($path) > 1) {
                            $nestedTableDescendantCaptions[] = $caption;
                        }
                    }

                    $diagnosticCount = (int) ($nestedTable['diagnosticCount'] ?? 0);
                    $nestedTableDiagnosticCount += $diagnosticCount;
                    $diagnosticCodes = $nestedTable['diagnosticCodes'] ?? [];
                    if (is_array($diagnosticCodes)) {
                        foreach ($diagnosticCodes as $code) {
                            $code = (string) $code;
                            if ($code !== '') {
                                $nestedTableDiagnosticCodes[] = $code;
                            }
                        }
                    }
                }
            }
        }

        sort($nestedTableCaptions);
        sort($nestedTableDescendantCaptions);
        sort($nestedTableDiagnosticCodes);

        return [
            'cellCount' => $cellCount,
            'headerCellCount' => $headerCellCount,
            'coveredSlotCount' => $coveredSlotCount,
            'missingSlotCount' => $missingSlotCount,
            'nestedTableCount' => $nestedTableCount,
            'nestedTableCellCount' => $nestedTableCellCount,
            'hasNestedTables' => $nestedTableCount > 0,
            'nestedTableCaptions' => array_values(array_unique($nestedTableCaptions)),
            'nestedTableDescendantCaptions' => array_values(array_unique($nestedTableDescendantCaptions)),
            'nestedTableDiagnosticCount' => $nestedTableDiagnosticCount,
            'nestedTableDiagnosticCodes' => array_values(array_unique($nestedTableDiagnosticCodes)),
        ];
    }

    /**
     * @param list<array<string, mixed>> $coverage
     * @return list<array<string, mixed>>
     */
    private static function serializableCoverage(array $coverage): array
    {
        $records = [];
        foreach ($coverage as $record) {
            $node = $record['node'] ?? null;
            unset($record['node']);
            if ($node instanceof AstNode) {
                $record['text'] = self::plainText($node);
                $sourceAttributes = self::sourceAttributeSummary($node);
                if ($sourceAttributes !== []) {
                    $record['sourceAttributes'] = $sourceAttributes;
                }
                $nestedTables = self::nestedTableSummaries($node);
                if ($nestedTables !== []) {
                    $record['nestedTables'] = $nestedTables;
                }
            }

            $records[] = $record;
        }

        return $records;
    }

    /**
     * @param list<array{
     *     section:string,
     *     columnCount:int,
     *     rowEntries:list<array{row:AstNode,header:bool,rowHeadColumns:int,rowRole:string}>,
     *     rows:list<list<array<string, mixed>>>
     * }> $sections
     * @param list<array<string, mixed>> $coverage
     * @param list<array<string, mixed>> $diagnostics
     * @param array<string, list<array<string, mixed>>> $writerDowngrades
     * @param array{long:array<string, mixed>,short:array<string, mixed>} $captions
     * @return array<string, mixed>
     */
    private static function reviewPacketSummary(
        array $sections,
        array $coverage,
        array $diagnostics,
        array $writerDowngrades,
        array $captions
    ): array
    {
        $rowCount = 0;
        $coveredSlotCount = 0;
        $missingSlotCount = 0;
        foreach ($sections as $section) {
            $rowCount += count($section['rowEntries']);
            foreach ($section['rows'] as $slots) {
                foreach ($slots as $slot) {
                    if (($slot['kind'] ?? '') === 'covered') {
                        $coveredSlotCount++;
                    } elseif (($slot['kind'] ?? '') === 'missing') {
                        $missingSlotCount++;
                    }
                }
            }
        }

        $headerCellCount = 0;
        $hasSpans = false;
        $sourceCoordinateShiftCount = 0;
        $maxVisualShift = 0;
        $nestedTableCellCount = 0;
        $nestedTableCount = 0;
        foreach ($coverage as $record) {
            if (($record['headerCell'] ?? false) === true) {
                $headerCellCount++;
            }

            if ((int) ($record['rawColspan'] ?? 1) > 1 || (int) ($record['rawRowspan'] ?? 1) > 1) {
                $hasSpans = true;
            }

            $visualShift = abs((int) ($record['visualShift'] ?? 0));
            if ($visualShift > 0) {
                $sourceCoordinateShiftCount++;
                $maxVisualShift = max($maxVisualShift, $visualShift);
            }

            $nestedTables = $record['nestedTables'] ?? [];
            if (is_array($nestedTables) && $nestedTables !== []) {
                $nestedTableCellCount++;
                $nestedTableCount += count($nestedTables);
            }
        }

        $diagnosticCodes = [];
        foreach ($diagnostics as $diagnostic) {
            $code = (string) ($diagnostic['code'] ?? '');
            if ($code !== '') {
                $diagnosticCodes[] = $code;
            }
        }

        $writerDowngradeCount = 0;
        $writerDowngradeCodes = [];
        $writerDowngradeWriters = [];
        foreach ($writerDowngrades as $writer => $diagnosticsForWriter) {
            $writer = (string) $writer;
            if ($diagnosticsForWriter === []) {
                continue;
            }

            $writerDowngradeWriters[] = $writer;
            foreach ($diagnosticsForWriter as $diagnostic) {
                $writerDowngradeCount++;
                $code = (string) ($diagnostic['code'] ?? '');
                if ($code !== '') {
                    $writerDowngradeCodes[] = $code;
                }
            }
        }
        sort($writerDowngradeWriters);

        return [
            'sectionCount' => count($sections),
            'rowCount' => $rowCount,
            'cellCount' => count($coverage),
            'headerCellCount' => $headerCellCount,
            'coveredSlotCount' => $coveredSlotCount,
            'missingSlotCount' => $missingSlotCount,
            'diagnosticCount' => count($diagnostics),
            'diagnosticCodes' => array_values(array_unique($diagnosticCodes)),
            'hasCaption' => (string) ($captions['long']['text'] ?? '') !== '',
            'hasShortCaption' => (string) ($captions['short']['text'] ?? '') !== '',
            'captionInlineTypes' => array_values(array_map(
                static fn (mixed $type): string => (string) $type,
                is_array($captions['long']['inlineTypes'] ?? null) ? $captions['long']['inlineTypes'] : []
            )),
            'shortCaptionInlineTypes' => array_values(array_map(
                static fn (mixed $type): string => (string) $type,
                is_array($captions['short']['inlineTypes'] ?? null) ? $captions['short']['inlineTypes'] : []
            )),
            'hasSpans' => $hasSpans,
            'hasSourceCoordinateShifts' => $sourceCoordinateShiftCount > 0,
            'sourceCoordinateShiftCount' => $sourceCoordinateShiftCount,
            'maxVisualShift' => $maxVisualShift,
            'nestedTableCount' => $nestedTableCount,
            'nestedTableCellCount' => $nestedTableCellCount,
            'writerDowngradeCount' => $writerDowngradeCount,
            'writerDowngradeCodes' => array_values(array_unique($writerDowngradeCodes)),
            'writerDowngradeWriters' => array_values(array_unique($writerDowngradeWriters)),
        ];
    }

    /**
     * @param list<array<string, mixed>> $coverage
     * @return list<array<string, mixed>>
     */
    private static function writerDowngradeDiagnosticsFromCoverage(array $coverage, string $writer): array
    {
        $writer = self::normalizeWriterName($writer);
        if ($writer !== 'markdown') {
            if ($writer !== 'rst') {
                return [];
            }

            $diagnostics = [];
            foreach ($coverage as $record) {
                $rawRowspan = max(1, (int) ($record['rawRowspan'] ?? 1));
                if ($rawRowspan <= 1) {
                    continue;
                }

                $diagnostics[] = self::writerRequirementRecord(
                    'rst-grid-table-required',
                    $writer,
                    $record,
                    'rowspan',
                    'grid-table',
                    self::flattenedSlotRecords($record, 'rowspan')
                );
            }

            return $diagnostics;
        }

        $diagnostics = [];
        foreach ($coverage as $record) {
            $rawColspan = max(1, (int) ($record['rawColspan'] ?? 1));
            $rawRowspan = max(1, (int) ($record['rawRowspan'] ?? 1));
            if ($rawColspan > 1) {
                $diagnostics[] = self::writerDowngradeRecord(
                    'markdown-colspan-flattened',
                    $writer,
                    $record,
                    self::flattenedSlotRecords($record, 'colspan')
                );
            }

            if ($rawRowspan > 1) {
                $diagnostics[] = self::writerDowngradeRecord(
                    'markdown-rowspan-flattened',
                    $writer,
                    $record,
                    self::flattenedSlotRecords($record, 'rowspan')
                );
            }
        }

        return $diagnostics;
    }

    private static function normalizeWriterName(string $writer): string
    {
        $writer = strtolower(trim(str_replace('_', '-', $writer)));
        if (in_array($writer, ['rst', 'rst-grid-table', 'restructuredtext', 'restructured-text', 'restructured-text-grid-table'], true)) {
            return 'rst';
        }

        return in_array($writer, ['markdown', 'markdown-pipe-table', 'pipe-table'], true) ? 'markdown' : $writer;
    }

    /**
     * @param array<string, mixed> $record
     * @param list<array{row:int,column:int,covering:string}> $flattenedSlots
     * @return array<string, mixed>
     */
    private static function writerDowngradeRecord(string $code, string $writer, array $record, array $flattenedSlots): array
    {
        return [
            'code' => $code,
            'writer' => $writer,
            'section' => (string) ($record['section'] ?? ''),
            'row' => (int) ($record['row'] ?? 0),
            'column' => (int) ($record['column'] ?? 0),
            'endColumn' => (int) ($record['endColumn'] ?? 0),
            'sourceCell' => (int) ($record['sourceCell'] ?? 0),
            'sourceColumn' => (int) ($record['sourceColumn'] ?? 0),
            'columns' => self::intList($record['columns'] ?? []),
            'rawColspan' => max(1, (int) ($record['rawColspan'] ?? 1)),
            'colspan' => max(1, (int) ($record['colspan'] ?? 1)),
            'rawRowspan' => max(1, (int) ($record['rawRowspan'] ?? 1)),
            'rowspan' => max(1, (int) ($record['rowspan'] ?? 1)),
            'flattenedSlots' => $flattenedSlots,
        ];
    }

    /**
     * @param array<string, mixed> $record
     * @param list<array{row:int,column:int,covering:string}> $requiredSlots
     * @return array<string, mixed>
     */
    private static function writerRequirementRecord(
        string $code,
        string $writer,
        array $record,
        string $reason,
        string $requiredFeature,
        array $requiredSlots
    ): array {
        $writerRecord = self::writerDowngradeRecord($code, $writer, $record, $requiredSlots);
        unset($writerRecord['flattenedSlots']);
        $writerRecord['reason'] = $reason;
        $writerRecord['requiredFeature'] = $requiredFeature;
        $writerRecord['requiredSlots'] = $requiredSlots;

        return $writerRecord;
    }

    /**
     * @param array<string, mixed> $record
     * @return list<array{row:int,column:int,covering:string}>
     */
    private static function flattenedSlotRecords(array $record, string $spanAxis): array
    {
        $anchorRow = (int) ($record['row'] ?? 0);
        $slots = $record['occupiedSlots'] ?? [];
        if (!is_array($slots)) {
            return [];
        }

        $flattened = [];
        foreach ($slots as $slot) {
            if (!is_array($slot)) {
                continue;
            }

            $covering = (string) ($slot['covering'] ?? '');
            $row = (int) ($slot['row'] ?? 0);
            if ($spanAxis === 'colspan') {
                $include = $row === $anchorRow && in_array($covering, ['colspan', 'rowspan-colspan'], true);
            } else {
                $include = $row !== $anchorRow && in_array($covering, ['rowspan', 'rowspan-colspan'], true);
            }

            if (!$include) {
                continue;
            }

            $flattened[] = [
                'row' => $row,
                'column' => (int) ($slot['column'] ?? 0),
                'covering' => $covering,
            ];
        }

        return $flattened;
    }

    /**
     * @return list<string>
     */
    private static function reviewPacketWriters(mixed $writers): array
    {
        if (!is_array($writers)) {
            return ['markdown'];
        }

        $normalized = [];
        foreach ($writers as $writer) {
            if (!is_scalar($writer)) {
                continue;
            }

            $writer = self::normalizeWriterName((string) $writer);
            if ($writer !== '') {
                $normalized[] = $writer;
            }
        }

        return array_values(array_unique($normalized));
    }

    /**
     * @return list<int>
     */
    private static function intList(mixed $values): array
    {
        if (!is_array($values)) {
            return [];
        }

        return array_values(array_map(static fn (mixed $value): int => (int) $value, $values));
    }

    /**
     * @return list<int>
     */
    private static function sourceColumns(int $sourceColumn, int $colspan): array
    {
        $columns = [];
        for ($column = $sourceColumn; $column < $sourceColumn + max(1, $colspan); $column++) {
            $columns[] = $column;
        }

        return $columns;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function nestedTableSummaries(AstNode $node): array
    {
        $summaries = [];
        foreach ($node->children as $index => $child) {
            self::collectNestedTableSummaries($child, [(int) $index], $summaries);
        }

        return $summaries;
    }

    /**
     * @param list<int> $path
     * @param list<array<string, mixed>> $summaries
     */
    private static function collectNestedTableSummaries(AstNode $node, array $path, array &$summaries): void
    {
        if ($node->type === 'table') {
            $packet = $node->attr('tableGeometry', null);
            if (!is_array($packet)) {
                $packet = self::reviewPacket($node, ['accessibility' => false]);
            }

            $summary = $packet['summary'] ?? [];
            if (!is_array($summary)) {
                $summary = [];
            }
            $diagnosticCodes = $summary['diagnosticCodes'] ?? [];
            if (!is_array($diagnosticCodes)) {
                $diagnosticCodes = [];
            }
            $nestedTableCount = (int) ($summary['nestedTableCount'] ?? 0);

            $summaries[] = [
                'path' => array_values($path),
                'caption' => (string) ($packet['caption'] ?? ''),
                'columnCount' => (int) ($packet['columnCount'] ?? self::columnCount($node)),
                'declaredColumnCount' => (int) ($packet['declaredColumnCount'] ?? self::declaredColumnCount($node)),
                'sectionCount' => (int) ($summary['sectionCount'] ?? 0),
                'rowCount' => (int) ($summary['rowCount'] ?? 0),
                'cellCount' => (int) ($summary['cellCount'] ?? 0),
                'headerCellCount' => (int) ($summary['headerCellCount'] ?? 0),
                'coveredSlotCount' => (int) ($summary['coveredSlotCount'] ?? 0),
                'missingSlotCount' => (int) ($summary['missingSlotCount'] ?? 0),
                'diagnosticCount' => (int) ($summary['diagnosticCount'] ?? 0),
                'diagnosticCodes' => array_values(array_map(
                    static fn (mixed $code): string => (string) $code,
                    $diagnosticCodes
                )),
                'hasSpans' => (bool) ($summary['hasSpans'] ?? false),
                'nestedTableCount' => $nestedTableCount,
                'hasNestedTables' => $nestedTableCount > 0,
            ];
        }

        foreach ($node->children as $index => $child) {
            $childPath = $path;
            $childPath[] = (int) $index;
            self::collectNestedTableSummaries($child, $childPath, $summaries);
        }
    }

    private static function plainText(AstNode $node): string
    {
        if ($node->type === 'text') {
            return (string) $node->attr('text', '');
        }

        if ($node->type === 'space' || $node->type === 'softbreak') {
            return ' ';
        }

        if ($node->type === 'linebreak') {
            return "\n";
        }

        if ($node->children !== []) {
            $text = '';
            foreach ($node->children as $child) {
                $text .= self::plainText($child);
            }

            return $text;
        }

        foreach (['text', 'alt', 'caption', 'tex'] as $attr) {
            $value = $node->attr($attr, null);
            if (is_scalar($value)) {
                return (string) $value;
            }
        }

        return '';
    }

    /**
     * @param list<AstNode> $nodes
     */
    private static function plainTextFromNodes(array $nodes): string
    {
        $text = '';
        foreach ($nodes as $node) {
            $text .= self::plainText($node);
        }

        return $text;
    }

    /**
     * @return array{id?:string,classes?:list<string>,attributes?:array<string, string>,htmlAttributes?:array<string, string>}
     */
    private static function sourceAttributeSummary(mixed $node): array
    {
        if (!$node instanceof AstNode) {
            return [];
        }

        $summary = [];
        $id = trim((string) $node->attr('id', ''));
        if ($id === '') {
            $htmlAttributes = $node->attr('htmlAttributes', []);
            if (is_array($htmlAttributes) && isset($htmlAttributes['id']) && is_scalar($htmlAttributes['id'])) {
                $id = trim((string) $htmlAttributes['id']);
            }
        }
        if ($id !== '') {
            $summary['id'] = $id;
        }

        $classes = self::sourceClasses($node);
        if ($classes !== []) {
            $summary['classes'] = $classes;
        }

        $attributes = self::stringAttributeMap($node->attr('attributes', []), false);
        if ($attributes !== []) {
            $summary['attributes'] = $attributes;
        }

        $htmlAttributes = self::stringAttributeMap($node->attr('htmlAttributes', []), true);
        if ($htmlAttributes !== []) {
            $summary['htmlAttributes'] = $htmlAttributes;
        }

        return $summary;
    }

    /**
     * @return list<string>
     */
    private static function sourceClasses(AstNode $node): array
    {
        $classes = $node->attr('classes', []);
        if (is_array($classes)) {
            $normalized = [];
            foreach ($classes as $class) {
                if (!is_scalar($class)) {
                    continue;
                }

                $class = trim((string) $class);
                if ($class !== '') {
                    $normalized[] = $class;
                }
            }

            if ($normalized !== []) {
                return array_values(array_unique($normalized));
            }
        }

        $htmlAttributes = $node->attr('htmlAttributes', []);
        if (!is_array($htmlAttributes) || !isset($htmlAttributes['class']) || !is_scalar($htmlAttributes['class'])) {
            return [];
        }

        $classes = preg_split('/\s+/', trim((string) $htmlAttributes['class']), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        return array_values(array_unique($classes));
    }

    /**
     * @return array<string, string>
     */
    private static function stringAttributeMap(mixed $attributes, bool $lowercaseKeys): array
    {
        if (!is_array($attributes)) {
            return [];
        }

        $normalized = [];
        foreach ($attributes as $name => $value) {
            $name = trim((string) $name);
            if ($name === '' || !is_scalar($value)) {
                continue;
            }

            if ($lowercaseKeys) {
                $name = strtolower($name);
            }
            $normalized[$name] = (string) $value;
        }

        ksort($normalized);

        return $normalized;
    }

    private static function tableAttributeColumnCount(mixed $columns): int
    {
        return is_array($columns) ? count($columns) : 0;
    }

    private static function declaredColumnCount(AstNode $table): int
    {
        return max(
            self::tableAttributeColumnCount($table->attr('alignments', [])),
            self::tableAttributeColumnCount($table->attr('widths', []))
        );
    }

    /**
     * @param list<mixed> $widths
     */
    private static function positiveWidthTotal(array $widths, int $columnCount): float
    {
        $total = 0.0;
        for ($column = 0; $column < max(0, $columnCount); $column++) {
            $width = $widths[$column] ?? null;
            if (is_numeric($width) && (float) $width > 0.0) {
                $total += (float) $width;
            }
        }

        return $total;
    }

    private static function roundWidth(float $width): float
    {
        return round($width, 6);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private static function columnSources(AstNode $table): array
    {
        $sources = $table->attr('columnSources', []);
        if (!is_array($sources)) {
            return [];
        }

        $normalized = [];
        foreach ($sources as $index => $source) {
            if (!is_array($source)) {
                continue;
            }

            $record = self::serializableColumnSource($source);
            if ($record === []) {
                continue;
            }

            $column = isset($record['column']) && is_numeric($record['column'])
                ? (int) $record['column']
                : (int) $index;
            if ($column < 0) {
                continue;
            }

            $record['column'] = $column;
            $normalized[$column] = $record;
        }

        ksort($normalized);

        return $normalized;
    }

    /**
     * @param array<string|int, mixed> $source
     * @return array<string, mixed>
     */
    private static function serializableColumnSource(array $source): array
    {
        $record = [];
        foreach ($source as $key => $value) {
            $key = trim((string) $key);
            if ($key === '') {
                continue;
            }

            $normalized = self::serializableColumnSourceValue($value);
            if ($normalized['valid'] === true) {
                $record[$key] = $normalized['value'];
            }
        }

        return $record;
    }

    /**
     * @return array{valid:bool,value:mixed}
     */
    private static function serializableColumnSourceValue(mixed $value): array
    {
        if ($value === null || is_scalar($value)) {
            return ['valid' => true, 'value' => $value];
        }

        if (!is_array($value)) {
            return ['valid' => false, 'value' => null];
        }

        $normalized = [];
        foreach ($value as $key => $nestedValue) {
            $key = is_int($key) ? $key : trim((string) $key);
            if ($key === '') {
                continue;
            }

            $nested = self::serializableColumnSourceValue($nestedValue);
            if ($nested['valid'] === true) {
                $normalized[$key] = $nested['value'];
            }
        }

        return ['valid' => true, 'value' => $normalized];
    }

    /**
     * @return array{kind:string,row:int,column:int}
     */
    private static function missingGridSlot(int $row, int $column): array
    {
        return [
            'kind' => 'missing',
            'row' => $row,
            'column' => $column,
        ];
    }

    /**
     * @param array{node:AstNode,column:int,colspan:int,rowspan:int,rowspanToEnd:bool,sourceCell:int,sourceColumn:int} $cell
     * @return array<string, mixed>
     */
    private static function cellGridSlot(int $row, array $cell, int $rowCount, int $columnCount): array
    {
        $slot = [
            'kind' => 'cell',
            'row' => $row,
            'column' => $cell['column'],
            'node' => $cell['node'],
            'sourceCell' => $cell['sourceCell'],
            'sourceColumn' => $cell['sourceColumn'],
            'colspan' => $cell['colspan'],
            'rowspan' => $cell['rowspan'],
            'anchorRow' => $row,
            'anchorColumn' => $cell['column'],
            'occupiedSlots' => self::occupiedSlotRecords(
                $row,
                $cell['column'],
                $cell['colspan'],
                $cell['rowspan'],
                $rowCount,
                $columnCount
            ),
        ];
        if (($cell['rowspanToEnd'] ?? false) === true) {
            $slot['rowspanToEnd'] = true;
        }

        return $slot;
    }

    /**
     * @param array{node:AstNode,column:int,colspan:int,rowspan:int,rowspanToEnd:bool,sourceCell:int,sourceColumn:int} $cell
     * @return array<string, mixed>
     */
    private static function coveredGridSlot(
        int $row,
        int $column,
        int $anchorRow,
        int $anchorColumn,
        string $covering,
        array $cell
    ): array {
        $slot = [
            'kind' => 'covered',
            'row' => $row,
            'column' => $column,
            'node' => $cell['node'],
            'sourceCell' => $cell['sourceCell'],
            'sourceColumn' => $cell['sourceColumn'],
            'colspan' => $cell['colspan'],
            'rowspan' => $cell['rowspan'],
            'anchorRow' => $anchorRow,
            'anchorColumn' => $anchorColumn,
            'covering' => $covering,
        ];
        if (($cell['rowspanToEnd'] ?? false) === true) {
            $slot['rowspanToEnd'] = true;
        }

        return $slot;
    }

    /**
     * @return list<array{row:int,column:int,covering:string}>
     */
    private static function occupiedSlotRecords(
        int $anchorRow,
        int $anchorColumn,
        int $colspan,
        int $rowspan,
        int $rowCount,
        int $columnCount
    ): array {
        $slots = [];
        $rowLimit = min(max(0, $rowCount), $anchorRow + max(1, $rowspan));
        $columnLimit = min(max(0, $columnCount), $anchorColumn + max(1, $colspan));

        for ($row = $anchorRow; $row < $rowLimit; $row++) {
            for ($column = $anchorColumn; $column < $columnLimit; $column++) {
                if ($row === $anchorRow && $column === $anchorColumn) {
                    $covering = 'anchor';
                } elseif ($row === $anchorRow) {
                    $covering = 'colspan';
                } elseif ($column === $anchorColumn) {
                    $covering = 'rowspan';
                } else {
                    $covering = 'rowspan-colspan';
                }

                $slots[] = [
                    'row' => $row,
                    'column' => $column,
                    'covering' => $covering,
                ];
            }
        }

        return $slots;
    }

    /**
     * @param list<array<string, mixed>> $diagnostics
     * @param array{node:AstNode,column:int,colspan:int,rowspan:int,rowspanToEnd:bool,sourceCell:int,sourceColumn:int} $cell
     */
    private static function appendDeclaredColumnDiagnostic(
        array &$diagnostics,
        string $section,
        int $rowIndex,
        array $cell,
        int $declaredColumnCount
    ): void {
        $rawColspan = self::cellColspan($cell['node']);
        $endColumn = $cell['column'] + $rawColspan;
        if ($endColumn <= $declaredColumnCount) {
            return;
        }

        $diagnostics[] = [
            'code' => 'cell-exceeds-declared-columns',
            'section' => $section,
            'row' => $rowIndex,
            'column' => $cell['column'],
            'sourceCell' => $cell['sourceCell'],
            'sourceColumn' => $cell['sourceColumn'],
            'colspan' => $rawColspan,
            'declaredColumns' => $declaredColumnCount,
            'endColumn' => $endColumn,
        ];
    }

    /**
     * @param list<AstNode> $rows
     * @return list<array<string, mixed>>
     */
    private static function rowspanOverlapDiagnostics(string $section, array $rows, int $declaredColumnCount): array
    {
        $diagnostics = [];
        $activeRowspans = [];
        $rowCount = count($rows);

        foreach ($rows as $rowIndex => $row) {
            if ($row->type !== 'table_row') {
                continue;
            }

            $rowActiveRowspans = $activeRowspans;
            $previousActiveColumns = self::activeRichColumns($activeRowspans);
            $consumedActiveColumns = [];
            $column = 0;
            $sourceCell = 0;
            $sourceColumn = 0;

            foreach ($row->children as $cell) {
                if ($cell->type !== 'table_cell') {
                    continue;
                }

                $rawColspan = self::cellColspan($cell);
                $overlapColumns = [];
                $coveredBy = [];
                for ($coveredColumn = $sourceColumn; $coveredColumn < $sourceColumn + $rawColspan; $coveredColumn++) {
                    if (($rowActiveRowspans[$coveredColumn]['remainingRows'] ?? 0) <= 0) {
                        continue;
                    }

                    $coveringCell = $rowActiveRowspans[$coveredColumn];
                    $overlapColumns[] = $coveredColumn;
                    $coveredBy[] = [
                        'row' => (int) $coveringCell['anchorRow'],
                        'column' => (int) $coveringCell['anchorColumn'],
                        'sourceCell' => (int) $coveringCell['sourceCell'],
                        'sourceColumn' => (int) $coveringCell['sourceColumn'],
                        'colspan' => (int) $coveringCell['colspan'],
                        'rowspan' => (int) $coveringCell['rowspan'],
                    ];
                }

                self::skipRichCoveredColumns($activeRowspans, $column, $consumedActiveColumns);
                $endColumn = $column + $rawColspan;
                if ($overlapColumns !== [] && $declaredColumnCount > 0 && $endColumn > $declaredColumnCount) {
                    $diagnostics[] = [
                        'code' => 'cell-overlaps-rowspan',
                        'section' => $section,
                        'row' => $rowIndex,
                        'column' => $column,
                        'endColumn' => $endColumn,
                        'sourceCell' => $sourceCell,
                        'sourceColumn' => $sourceColumn,
                        'sourceEndColumn' => $sourceColumn + $rawColspan,
                        'visualShift' => $column - $sourceColumn,
                        'colspan' => $rawColspan,
                        'declaredColumns' => $declaredColumnCount,
                        'overlapColumns' => $overlapColumns,
                        'overlapColumnCount' => count($overlapColumns),
                        'coveredBy' => $coveredBy,
                    ];
                }

                $rowspan = min(self::cellRowspanForRows($cell, $rowIndex, $rowCount), max(1, $rowCount - $rowIndex));
                if ($rowspan > 1) {
                    self::activateRichRowspan(
                        $activeRowspans,
                        $column,
                        $rawColspan,
                        $rowspan,
                        $rowIndex,
                        $sourceCell,
                        $sourceColumn
                    );
                }

                $column += $rawColspan;
                $sourceColumn += $rawColspan;
                $sourceCell++;
            }

            self::consumeUnusedRichActiveColumns($activeRowspans, $previousActiveColumns, $consumedActiveColumns);
        }

        return $diagnostics;
    }

    /**
     * @param list<array{row:AstNode,header:bool,rowHeadColumns:int,rowRole:string}> $rowEntries
     * @return list<list<array<string, mixed>>>
     */
    private static function sectionGridForEntries(array $rowEntries, int $columnCount): array
    {
        $rows = [];
        foreach ($rowEntries as $entry) {
            $rows[] = $entry['row'];
        }

        $grid = self::sectionGrid($rows, $columnCount);
        foreach ($grid as $rowIndex => $slots) {
            $entry = $rowEntries[$rowIndex] ?? [
                'header' => false,
                'rowHeadColumns' => 0,
                'rowRole' => '',
            ];
            $headerRow = (bool) $entry['header'];
            $rowHeadColumns = (int) $entry['rowHeadColumns'];
            foreach ($slots as $column => $slot) {
                $slot['rowRole'] = (string) $entry['rowRole'];
                $slot['headerRow'] = $headerRow;
                $slot['rowHeadColumns'] = $rowHeadColumns;
                $slot['headerCell'] = false;
                if (($slot['kind'] ?? '') !== 'missing' && ($slot['node'] ?? null) instanceof AstNode) {
                    $slot['headerCell'] = self::isHeaderCell(
                        $headerRow,
                        $rowHeadColumns,
                        (int) ($slot['anchorColumn'] ?? $slot['column'] ?? $column),
                        $slot['node']
                    );
                }
                $grid[$rowIndex][$column] = $slot;
            }
        }

        return $grid;
    }

    /**
     * @return list<array{
     *     section:string,
     *     rows:list<AstNode>,
     *     rowEntries:list<array{row:AstNode,header:bool,rowHeadColumns:int,rowRole:string}>
     * }>
     */
    private static function sectionRowGroups(AstNode $table, ?int $columnCount): array
    {
        $groups = [];
        $bodyIndex = 0;
        foreach ($table->children as $section) {
            if ($section->type === 'table_head') {
                $entries = self::rowEntries(self::sectionRows($section), true, 0, 'head');
                $groups[] = [
                    'section' => 'head',
                    'node' => $section,
                    'rows' => self::entryRows($entries),
                    'rowEntries' => $entries,
                ];
                continue;
            }

            if ($section->type === 'table_body') {
                $entries = [];
                $bodyHeadRows = $section->attr('headRows', []);
                if (is_array($bodyHeadRows)) {
                    foreach ($bodyHeadRows as $row) {
                        if ($row instanceof AstNode && $row->type === 'table_row') {
                            $entries[] = [
                                'row' => $row,
                                'header' => true,
                                'rowHeadColumns' => 0,
                                'rowRole' => 'body-head',
                            ];
                        }
                    }
                }
                $rowHeadColumns = self::rowHeadColumns($section, max(0, $columnCount ?? self::columnCountForRows(self::bodyRows($section))));
                array_push($entries, ...self::rowEntries(self::sectionRows($section), false, $rowHeadColumns, 'body'));
                $groups[] = [
                    'section' => 'body' . ($bodyIndex === 0 ? '' : (string) $bodyIndex),
                    'node' => $section,
                    'rows' => self::entryRows($entries),
                    'rowEntries' => $entries,
                ];
                $bodyIndex++;
                continue;
            }

            if ($section->type === 'table_foot') {
                $entries = self::rowEntries(self::sectionRows($section), false, 0, 'foot');
                $groups[] = [
                    'section' => 'foot',
                    'node' => $section,
                    'rows' => self::entryRows($entries),
                    'rowEntries' => $entries,
                ];
            }
        }

        return $groups;
    }

    /**
     * @return list<AstNode>
     */
    private static function sectionRows(AstNode $section): array
    {
        return array_values(array_filter(
            $section->children,
            static fn (AstNode $row): bool => $row->type === 'table_row'
        ));
    }

    /**
     * @return list<AstNode>
     */
    private static function bodyRows(AstNode $body): array
    {
        $rows = [];
        $bodyHeadRows = $body->attr('headRows', []);
        if (is_array($bodyHeadRows)) {
            foreach ($bodyHeadRows as $row) {
                if ($row instanceof AstNode && $row->type === 'table_row') {
                    $rows[] = $row;
                }
            }
        }

        array_push($rows, ...self::sectionRows($body));

        return $rows;
    }

    /**
     * @param list<AstNode> $rows
     * @return list<array{row:AstNode,header:bool,rowHeadColumns:int,rowRole:string}>
     */
    private static function rowEntries(array $rows, bool $header, int $rowHeadColumns, string $rowRole): array
    {
        $entries = [];
        foreach ($rows as $row) {
            $entries[] = [
                'row' => $row,
                'header' => $header,
                'rowHeadColumns' => $rowHeadColumns,
                'rowRole' => $rowRole,
            ];
        }

        return $entries;
    }

    /**
     * @param list<array{row:AstNode,header:bool,rowHeadColumns:int,rowRole:string}> $entries
     * @return list<AstNode>
     */
    private static function entryRows(array $entries): array
    {
        $rows = [];
        foreach ($entries as $entry) {
            $rows[] = $entry['row'];
        }

        return $rows;
    }

    private static function cellColspan(AstNode $cell): int
    {
        return max(1, (int) $cell->attr('colspan', 1));
    }

    private static function cellRowspan(AstNode $cell): int
    {
        return max(1, self::cellRawRowspan($cell));
    }

    private static function cellRowspanForRows(AstNode $cell, int $rowIndex, int $rowCount): int
    {
        if (self::cellRowspanToEnd($cell)) {
            return max(1, $rowCount - $rowIndex);
        }

        return self::cellRowspan($cell);
    }

    private static function cellRowspanToEnd(AstNode $cell): bool
    {
        return self::cellRawRowspan($cell) === 0;
    }

    private static function cellRawRowspan(AstNode $cell): int
    {
        $value = $cell->attr('rowspan', 1);
        if (is_string($value)) {
            $value = trim($value);
            if (preg_match('/^-?\d+$/', $value) !== 1) {
                return 1;
            }

            return (int) $value;
        }

        if (!is_int($value) && !is_float($value)) {
            return 1;
        }

        return (int) $value;
    }

    /**
     * @param array<int, int> $activeRowspans
     * @return list<int>
     */
    private static function activeColumns(array $activeRowspans): array
    {
        $columns = [];
        foreach ($activeRowspans as $column => $remainingRows) {
            if ($remainingRows > 0) {
                $columns[] = (int) $column;
            }
        }

        return $columns;
    }

    /**
     * @param array<int, array<string, int>> $activeRowspans
     * @return list<int>
     */
    private static function activeRichColumns(array $activeRowspans): array
    {
        $columns = [];
        foreach ($activeRowspans as $column => $rowspan) {
            if (($rowspan['remainingRows'] ?? 0) > 0) {
                $columns[] = (int) $column;
            }
        }

        return $columns;
    }

    /**
     * @param array<int, int> $activeRowspans
     * @param array<int, bool> $consumedActiveColumns
     */
    private static function skipCoveredColumns(array &$activeRowspans, int &$column, array &$consumedActiveColumns): void
    {
        while (($activeRowspans[$column] ?? 0) > 0) {
            $activeRowspans[$column]--;
            $consumedActiveColumns[$column] = true;
            if ($activeRowspans[$column] <= 0) {
                unset($activeRowspans[$column]);
            }
            $column++;
        }
    }

    /**
     * @param array<int, array<string, int>> $activeRowspans
     * @param array<int, bool> $consumedActiveColumns
     */
    private static function skipRichCoveredColumns(array &$activeRowspans, int &$column, array &$consumedActiveColumns): void
    {
        while (($activeRowspans[$column]['remainingRows'] ?? 0) > 0) {
            $activeRowspans[$column]['remainingRows']--;
            $consumedActiveColumns[$column] = true;
            if (($activeRowspans[$column]['remainingRows'] ?? 0) <= 0) {
                unset($activeRowspans[$column]);
            }
            $column++;
        }
    }

    /**
     * @param array<int, int> $activeRowspans
     */
    private static function activateRowspan(array &$activeRowspans, int $startColumn, int $colspan, int $rowspan): void
    {
        for ($column = $startColumn; $column < $startColumn + $colspan; $column++) {
            $activeRowspans[$column] = max($activeRowspans[$column] ?? 0, $rowspan - 1);
        }
    }

    /**
     * @param array<int, array<string, int>> $activeRowspans
     */
    private static function activateRichRowspan(
        array &$activeRowspans,
        int $startColumn,
        int $colspan,
        int $rowspan,
        int $anchorRow,
        int $sourceCell,
        int $sourceColumn
    ): void {
        for ($column = $startColumn; $column < $startColumn + $colspan; $column++) {
            if (($activeRowspans[$column]['remainingRows'] ?? 0) >= $rowspan - 1) {
                continue;
            }

            $activeRowspans[$column] = [
                'remainingRows' => $rowspan - 1,
                'anchorRow' => $anchorRow,
                'anchorColumn' => $startColumn,
                'sourceCell' => $sourceCell,
                'sourceColumn' => $sourceColumn,
                'colspan' => $colspan,
                'rowspan' => $rowspan,
            ];
        }
    }

    /**
     * @param array<int, int> $activeRowspans
     * @param list<int> $previousActiveColumns
     * @param array<int, bool> $consumedActiveColumns
     */
    private static function consumeUnusedActiveColumns(
        array &$activeRowspans,
        array $previousActiveColumns,
        array $consumedActiveColumns
    ): void
    {
        foreach ($previousActiveColumns as $column) {
            if (($consumedActiveColumns[$column] ?? false) || ($activeRowspans[$column] ?? 0) <= 0) {
                continue;
            }

            $activeRowspans[$column]--;
            if ($activeRowspans[$column] <= 0) {
                unset($activeRowspans[$column]);
            }
        }
    }

    /**
     * @param array<int, array<string, int>> $activeRowspans
     * @param list<int> $previousActiveColumns
     * @param array<int, bool> $consumedActiveColumns
     */
    private static function consumeUnusedRichActiveColumns(
        array &$activeRowspans,
        array $previousActiveColumns,
        array $consumedActiveColumns
    ): void {
        foreach ($previousActiveColumns as $column) {
            if (($consumedActiveColumns[$column] ?? false) || ($activeRowspans[$column]['remainingRows'] ?? 0) <= 0) {
                continue;
            }

            $activeRowspans[$column]['remainingRows']--;
            if (($activeRowspans[$column]['remainingRows'] ?? 0) <= 0) {
                unset($activeRowspans[$column]);
            }
        }
    }
}
