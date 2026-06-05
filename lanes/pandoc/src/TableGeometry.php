<?php

declare(strict_types=1);

namespace PortLibs\Pandoc;

final class TableGeometry
{
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
        $columnCount = 0;
        $activeRowspans = [];

        foreach ($rows as $row) {
            if ($row->type !== 'table_row') {
                continue;
            }

            $previousActiveColumns = self::activeColumns($activeRowspans);
            $consumedActiveColumns = [];
            $column = 0;

            foreach ($row->children as $cell) {
                if ($cell->type !== 'table_cell') {
                    continue;
                }

                self::skipCoveredColumns($activeRowspans, $column, $consumedActiveColumns);

                $colspan = self::cellColspan($cell);
                $rowspan = self::cellRowspan($cell);
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
     * @return list<array{row:AstNode,cells:list<array{node:AstNode,column:int,colspan:int,rowspan:int,sourceCell:int,sourceColumn:int}>}>
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
                $rowspan = min(self::cellRowspan($cell), max(1, $rowCount - $rowIndex));
                $layoutCells[] = [
                    'node' => $cell,
                    'column' => $column,
                    'colspan' => $colspan,
                    'rowspan' => $rowspan,
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
     * @return list<array{section:string,columnCount:int,rows:list<list<array<string, mixed>>>}>
     */
    public static function sectionGrids(AstNode $table): array
    {
        $columnCount = self::columnCount($table);
        $sectionGrids = [];

        foreach (self::sectionRowGroups($table) as $group) {
            $sectionGrids[] = [
                'section' => $group['section'],
                'columnCount' => $columnCount,
                'rows' => self::sectionGrid($group['rows'], $columnCount),
            ];
        }

        return $sectionGrids;
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

                $grid[$rowIndex][$anchorColumn] = self::cellGridSlot($rowIndex, $cell);
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
     *     colspan:int,
     *     rawColspan:int,
     *     rowspan:int,
     *     rawRowspan:int,
     *     alignment:string,
     *     columnAlignments:list<string>,
     *     widths:list<?float>,
     *     declaredColumns:list<bool>,
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
        foreach (self::sectionRowGroups($table) as $group) {
            foreach (self::layoutRows($group['rows'], $columnCount) as $rowIndex => $layoutRow) {
                foreach ($layoutRow['cells'] as $cell) {
                    $columns = [];
                    $columnAlignments = [];
                    $widths = [];
                    $declaredColumns = [];
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
                    }

                    $rawColspan = self::cellColspan($cell['node']);
                    $rawRowspan = self::cellRowspan($cell['node']);
                    $coverage[] = [
                        'section' => $group['section'],
                        'row' => $rowIndex,
                        'column' => $cell['column'],
                        'endColumn' => $cell['column'] + $cell['colspan'],
                        'rawEndColumn' => $cell['column'] + $rawColspan,
                        'columns' => $columns,
                        'sourceCell' => $cell['sourceCell'],
                        'sourceColumn' => $cell['sourceColumn'],
                        'colspan' => $cell['colspan'],
                        'rawColspan' => $rawColspan,
                        'rowspan' => $cell['rowspan'],
                        'rawRowspan' => $rawRowspan,
                        'alignment' => self::cellAlignment($table, $cell['column'], $cell['node']),
                        'columnAlignments' => $columnAlignments,
                        'widths' => $widths,
                        'declaredColumns' => $declaredColumns,
                        'node' => $cell['node'],
                    ];
                }
            }
        }

        return $coverage;
    }

    /**
     * @return list<array<string, int|string>>
     */
    public static function diagnostics(AstNode $table): array
    {
        $diagnostics = [];
        $declaredColumnCount = self::declaredColumnCount($table);
        foreach (self::sectionRowGroups($table) as $group) {
            $rows = $group['rows'];
            $rowCount = count($rows);
            $layoutColumnCount = max(1, $declaredColumnCount, self::columnCountForRows($rows));
            $layoutRows = self::layoutRows($rows, $layoutColumnCount);
            foreach ($layoutRows as $rowIndex => $layoutRow) {
                $availableRows = max(1, $rowCount - $rowIndex);
                foreach ($layoutRow['cells'] as $cell) {
                    $rowspan = self::cellRowspan($cell['node']);
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
     * @return list<array{column:int,alignment:string,width:?float,declared:bool}>
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
        $specs = [];
        for ($column = 0; $column < $columnCount; $column++) {
            $width = null;
            if (array_key_exists($column, $widths) && is_numeric($widths[$column]) && (float) $widths[$column] > 0.0) {
                $width = (float) $widths[$column];
            }

            $specs[] = [
                'column' => $column,
                'alignment' => $alignments[$column] ?? 'default',
                'width' => $width,
                'declared' => $column < $declaredColumnCount,
            ];
        }

        return $specs;
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

    private static function normalizeAlignment(string $alignment): string
    {
        return in_array($alignment, ['left', 'right', 'center'], true) ? $alignment : 'default';
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
     * @param array{node:AstNode,column:int,colspan:int,rowspan:int,sourceCell:int,sourceColumn:int} $cell
     * @return array<string, mixed>
     */
    private static function cellGridSlot(int $row, array $cell): array
    {
        return [
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
        ];
    }

    /**
     * @param array{node:AstNode,column:int,colspan:int,rowspan:int,sourceCell:int,sourceColumn:int} $cell
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
        return [
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
    }

    /**
     * @param list<array<string, int|string>> $diagnostics
     * @param array{node:AstNode,column:int,colspan:int,rowspan:int,sourceCell:int,sourceColumn:int} $cell
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
     * @return list<array{section:string,rows:list<AstNode>}>
     */
    private static function sectionRowGroups(AstNode $table): array
    {
        $groups = [];
        $bodyIndex = 0;
        foreach ($table->children as $section) {
            if ($section->type === 'table_head') {
                $groups[] = [
                    'section' => 'head',
                    'rows' => self::sectionRows($section),
                ];
                continue;
            }

            if ($section->type === 'table_body') {
                $groups[] = [
                    'section' => 'body' . ($bodyIndex === 0 ? '' : (string) $bodyIndex),
                    'rows' => self::bodyRows($section),
                ];
                $bodyIndex++;
                continue;
            }

            if ($section->type === 'table_foot') {
                $groups[] = [
                    'section' => 'foot',
                    'rows' => self::sectionRows($section),
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

    private static function cellColspan(AstNode $cell): int
    {
        return max(1, (int) $cell->attr('colspan', 1));
    }

    private static function cellRowspan(AstNode $cell): int
    {
        return max(1, (int) $cell->attr('rowspan', 1));
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
     * @param array<int, int> $activeRowspans
     */
    private static function activateRowspan(array &$activeRowspans, int $startColumn, int $colspan, int $rowspan): void
    {
        for ($column = $startColumn; $column < $startColumn + $colspan; $column++) {
            $activeRowspans[$column] = max($activeRowspans[$column] ?? 0, $rowspan - 1);
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
}
