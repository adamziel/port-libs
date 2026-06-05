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
        foreach (self::sectionRowGroups($table, $columnCount) as $group) {
            foreach (self::layoutRows($group['rows'], $columnCount) as $rowIndex => $layoutRow) {
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
                        'rowRole' => (string) $rowEntry['rowRole'],
                        'headerRow' => $headerRow,
                        'rowHeadColumns' => $rowHeadColumns,
                        'headerCell' => self::isHeaderCell($headerRow, $rowHeadColumns, $cell['column'], $cell['node']),
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
                    $sourceId = self::cellSourceHtmlId($slot['node'] ?? null);
                    $id = $sourceId !== '' ? $sourceId : $idPrefix . '-' . self::normalizeHtmlId($section)
                        . '-r' . ((int) $rowIndex + 1)
                        . 'c' . ((int) ($slot['anchorColumn'] ?? $slot['column'] ?? 0) + 1);
                    $scope = self::headerScope($slot);
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
                        'headers' => [],
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
                        } elseif (($scope === 'row' || $scope === 'rowgroup') && $header['section'] === $section) {
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
                    $attributes[$key] = [
                        'headers' => array_values(array_unique($headerIds)),
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
        $diagnostics = [];
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

    public static function isHeaderCell(bool $headerRow, int $rowHeadColumns, int $column, AstNode $cell): bool
    {
        return $headerRow || $cell->attr('header') === true || ($rowHeadColumns > 0 && $column < $rowHeadColumns);
    }

    public static function accessibilityKey(string $section, int $row, int $sourceCell, int $sourceColumn): string
    {
        return $section . ':' . $row . ':' . $sourceCell . ':' . $sourceColumn;
    }

    /**
     * @param array{accessibility?:bool,idPrefix?:string} $options
     * @return array{
     *     caption:string,
     *     columnCount:int,
     *     declaredColumnCount:int,
     *     columns:list<array{column:int,alignment:string,width:?float,declared:bool}>,
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
        $coverage = self::serializableCoverage(self::cellCoverage($table));
        $diagnostics = self::diagnostics($table);
        $includeAccessibility = ($options['accessibility'] ?? true) !== false;
        $accessibility = $includeAccessibility
            ? self::accessibilityAttributes($table, self::reviewPacketIdPrefix($table, $options))
            : [];

        return [
            'caption' => (string) $table->attr('caption', ''),
            'columnCount' => $columnCount,
            'declaredColumnCount' => self::declaredColumnCount($table),
            'columns' => self::columnSpecs($table, $columnCount),
            'sections' => self::serializableSectionGrids($sections),
            'coverage' => $coverage,
            'diagnostics' => $diagnostics,
            'accessibility' => $accessibility,
            'summary' => self::reviewPacketSummary($sections, $coverage, $diagnostics),
        ];
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

                $rows[] = [
                    'row' => $rowIndex,
                    'rowRole' => (string) $entry['rowRole'],
                    'header' => (bool) $entry['header'],
                    'rowHeadColumns' => (int) $entry['rowHeadColumns'],
                    'slots' => $slots,
                ];
            }

            $reports[] = [
                'section' => (string) $section['section'],
                'columnCount' => (int) $section['columnCount'],
                'rowCount' => count($rows),
                'rows' => $rows,
            ];
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
        }

        return $slot;
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
     * @return array<string, mixed>
     */
    private static function reviewPacketSummary(array $sections, array $coverage, array $diagnostics): array
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
        foreach ($coverage as $record) {
            if (($record['headerCell'] ?? false) === true) {
                $headerCellCount++;
            }

            if ((int) ($record['rawColspan'] ?? 1) > 1 || (int) ($record['rawRowspan'] ?? 1) > 1) {
                $hasSpans = true;
            }
        }

        $diagnosticCodes = [];
        foreach ($diagnostics as $diagnostic) {
            $code = (string) ($diagnostic['code'] ?? '');
            if ($code !== '') {
                $diagnosticCodes[] = $code;
            }
        }

        return [
            'sectionCount' => count($sections),
            'rowCount' => $rowCount,
            'cellCount' => count($coverage),
            'headerCellCount' => $headerCellCount,
            'coveredSlotCount' => $coveredSlotCount,
            'missingSlotCount' => $missingSlotCount,
            'diagnosticCount' => count($diagnostics),
            'diagnosticCodes' => array_values(array_unique($diagnosticCodes)),
            'hasSpans' => $hasSpans,
        ];
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
     * @param list<array<string, mixed>> $diagnostics
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

                $rowspan = min(self::cellRowspan($cell), max(1, $rowCount - $rowIndex));
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
