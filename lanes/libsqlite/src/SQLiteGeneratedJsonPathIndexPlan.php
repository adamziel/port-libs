<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteGeneratedJsonPathIndexPlan
{
    /**
     * @param list<array<string,mixed>> $rows
     * @param list<array{name?:string,sql:string,rootPage?:int,unique?:bool}> $indexes
     * @param list<array{rowid:int|string,mutations:list<array{function:string,path:string,value:mixed}>}> $updates
     * @return array{table:string|null,generated_columns:list<array<string,mixed>>,before:list<array<string,mixed>>,after:list<array<string,mixed>>,index_updates:list<array<string,mixed>>,changed_rows:list<array<string,mixed>>,changes:int}
     */
    public static function plan(string $createTableSql, array $rows, array $indexes, array $updates): array
    {
        $analysis = SQLiteGeneratedColumnDependencyPlan::analyze($createTableSql);
        if ($analysis['status'] !== 'ok') {
            throw new \InvalidArgumentException($analysis['message'] ?? 'SQLite generated JSON path index plan cannot evaluate cyclic generated columns');
        }
        $rowidColumn = self::rowidColumn($createTableSql);

        $generatedColumns = self::generatedJsonColumns($analysis['columns'], $analysis['order']);
        if ($generatedColumns === []) {
            throw new \InvalidArgumentException('SQLite generated JSON path index plan requires at least one json_extract generated column');
        }

        $indexPlans = self::indexPlans($indexes, $generatedColumns);
        if ($indexPlans === []) {
            throw new \InvalidArgumentException('SQLite generated JSON path index plan requires an index on a generated JSON path column');
        }

        $before = array_map(static fn (array $row): array => self::evaluateGeneratedColumns($row, $generatedColumns), array_values($rows));
        $after = $before;
        $positions = self::rowPositions($after, $rowidColumn);
        $changedRows = [];
        $indexUpdates = [];

        foreach ($updates as $update) {
            $rowid = $update['rowid'] ?? null;
            if (!is_int($rowid) && !is_string($rowid)) {
                throw new \InvalidArgumentException('SQLite generated JSON path index UPDATE rowid must be integer or text');
            }
            if (!array_key_exists((string) $rowid, $positions)) {
                continue;
            }

            $position = $positions[(string) $rowid];
            $rowBefore = $after[$position];
            $jsonColumn = self::mutationJsonColumn($update, $generatedColumns);
            $json = self::jsonValue($rowBefore, $jsonColumn);

            foreach ($update['mutations'] ?? [] as $mutation) {
                $function = strtolower((string) ($mutation['function'] ?? ''));
                $path = $mutation['path'] ?? null;
                if (!is_string($path)) {
                    throw new \InvalidArgumentException('SQLite generated JSON path index mutation path must be text');
                }
                $json = SQLiteJsonMutation::mutateSqlFunction($function, $json, $path, $mutation['value'] ?? null);
            }
            if ($json instanceof SQLiteBlobValue) {
                $json = SQLiteJsonCanonical::encodeDecodedJson(SQLiteJsonB::decode($json->bytes));
            }

            $rowAfter = $rowBefore;
            $rowAfter[$jsonColumn] = $json;
            $rowAfter = self::evaluateGeneratedColumns($rowAfter, $generatedColumns);
            $after[$position] = $rowAfter;
            $changedRows[] = $rowAfter;

            foreach ($indexPlans as $index) {
                $current = self::indexEntry($rowBefore, $index);
                $next = self::indexEntry($rowAfter, $index);
                if ($current['present'] === $next['present'] && $current['key'] === $next['key']) {
                    continue;
                }

                $indexUpdates[] = [
                    'index' => $index['name'],
                    'rootPage' => $index['rootPage'],
                    'rowid' => $rowid,
                    'column' => $index['column'],
                    'path' => $index['path'],
                    'source' => $index['source'],
                    'expressionFunction' => $index['expressionFunction'],
                    'expressionIndex' => $index['expressionIndex'],
                    'current' => $current['key'],
                    'next' => $next['key'],
                    'delete' => $current['present'],
                    'insert' => $next['present'],
                    'partial' => $index['partial'],
                    'collation' => $index['collation'],
                    'descending' => $index['descending'],
                    'unique' => $index['unique'],
                ];
            }
        }

        self::assertUniqueNextKeys($after, $indexPlans);

        return [
            'table' => $analysis['table'],
            'generated_columns' => array_values($generatedColumns),
            'before' => $before,
            'after' => $after,
            'index_updates' => $indexUpdates,
            'changed_rows' => $changedRows,
            'changes' => count($changedRows),
        ];
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @param list<array{name?:string,sql:string,rootPage?:int,unique?:bool}> $indexes
     * @param list<array{rowid:int|string,mutations:list<array{function:string,path:string,value:mixed}>}> $updates
     * @return array<string,mixed>
     */
    public static function btreeYieldPlan(string $createTableSql, array $rows, array $indexes, array $updates, int $pageSize = 512): array
    {
        if ($pageSize < 256) {
            throw new \InvalidArgumentException('SQLite generated JSON path B-tree yield plan requires a page size of at least 256 bytes');
        }

        $plan = self::plan($createTableSql, $rows, $indexes, $updates);
        $generatedColumns = [];
        foreach ($plan['generated_columns'] as $column) {
            $generatedColumns[strtolower((string) $column['name'])] = $column;
        }
        $indexPlans = self::indexPlans($indexes, $generatedColumns);
        $rowidColumn = self::rowidColumn($createTableSql);

        $btreeIndexes = [];
        $actions = [];
        foreach ($indexPlans as $index) {
            $currentEntries = self::btreeEntries($plan['before'], $index, $rowidColumn);
            $nextEntries = self::btreeEntries($plan['after'], $index, $rowidColumn);
            $currentLeafPage = self::indexLeafPage($currentEntries, $pageSize);
            $nextLeafPage = self::indexLeafPage($nextEntries, $pageSize);
            $btreeIndexes[$index['name']] = [
                'rootPage' => $index['rootPage'],
                'column' => $index['column'],
                'path' => $index['path'],
                'source' => $index['source'],
                'expressionFunction' => $index['expressionFunction'],
                'expressionIndex' => $index['expressionIndex'],
                'collation' => $index['collation'],
                'descending' => $index['descending'],
                'partial' => $index['partial'],
                'unique' => $index['unique'],
                'current_entries' => $currentEntries,
                'next_entries' => $nextEntries,
                'current_leaf_page_hex' => bin2hex($currentLeafPage),
                'next_leaf_page_hex' => bin2hex($nextLeafPage),
                'current_cell_count' => count($currentEntries),
                'next_cell_count' => count($nextEntries),
                'leaf_page_changed' => $currentLeafPage !== $nextLeafPage,
            ];
        }

        foreach ($plan['index_updates'] as $update) {
            if ($update['delete']) {
                $actions[] = self::btreeAction('delete', $update, $pageSize);
            }
            if ($update['insert']) {
                $actions[] = self::btreeAction('insert', $update, $pageSize);
            }
        }

        return $plan + [
            'btree_indexes' => $btreeIndexes,
            'btree_actions' => $actions,
            'btree_action_count' => count($actions),
            'pageSize' => $pageSize,
        ];
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @param list<array{name?:string,sql:string,rootPage?:int,unique?:bool,coveringColumns?:list<string>}> $indexes
     * @param list<int|string> $deleteRowids
     * @return array<string,mixed>
     */
    public static function coveringDeleteYieldPlan(string $createTableSql, array $rows, array $indexes, array $deleteRowids, int $pageSize = 512): array
    {
        if ($pageSize < 256) {
            throw new \InvalidArgumentException('SQLite generated JSON path covering-index DELETE yield plan requires a page size of at least 256 bytes');
        }

        $analysis = SQLiteGeneratedColumnDependencyPlan::analyze($createTableSql);
        if ($analysis['status'] !== 'ok') {
            throw new \InvalidArgumentException($analysis['message'] ?? 'SQLite generated JSON path covering-index DELETE plan cannot evaluate cyclic generated columns');
        }
        $rowidColumn = self::rowidColumn($createTableSql);

        $generatedColumns = self::generatedJsonColumns($analysis['columns'], $analysis['order']);
        if ($generatedColumns === []) {
            throw new \InvalidArgumentException('SQLite generated JSON path covering-index DELETE plan requires at least one json_extract generated column');
        }

        $indexPlans = self::indexPlans($indexes, $generatedColumns);
        if ($indexPlans === []) {
            throw new \InvalidArgumentException('SQLite generated JSON path covering-index DELETE plan requires an index on a generated JSON path column');
        }

        $before = array_map(static fn (array $row): array => self::evaluateGeneratedColumns($row, $generatedColumns), array_values($rows));
        $positions = self::rowPositions($before, $rowidColumn);
        $deletedKeys = [];
        foreach ($deleteRowids as $rowid) {
            if (!is_int($rowid) && !is_string($rowid)) {
                throw new \InvalidArgumentException('SQLite generated JSON path covering-index DELETE rowids must be integer or text');
            }
            $deletedKeys[(string) $rowid] = $rowid;
        }

        $deletedRows = [];
        $after = [];
        foreach ($before as $row) {
            $rowid = self::rowidValue($row, $rowidColumn, 'covering-index DELETE');

            if (array_key_exists((string) $rowid, $deletedKeys)) {
                $deletedRows[(string) $rowid] = $row;
                continue;
            }

            $after[] = $row;
        }

        $missingRowids = [];
        foreach ($deletedKeys as $key => $rowid) {
            if (!array_key_exists($key, $positions)) {
                $missingRowids[] = $rowid;
            }
        }

        $coveringByIndex = [];
        foreach ($indexes as $definition) {
            $name = is_string($definition['name'] ?? null) ? $definition['name'] : self::indexName((string) ($definition['sql'] ?? ''));
            $coveringByIndex[$name] = array_values(array_map('strval', $definition['coveringColumns'] ?? []));
        }

        $btreeIndexes = [];
        $deleteEntries = [];
        $actions = [];
        foreach ($indexPlans as $index) {
            $coveringColumns = $coveringByIndex[$index['name']] ?? [];
            $currentEntries = self::coveringBtreeEntries($before, $index, $coveringColumns, $rowidColumn);
            $nextEntries = self::coveringBtreeEntries($after, $index, $coveringColumns, $rowidColumn);
            $currentLeafPage = self::coveringIndexLeafPage($currentEntries, $pageSize);
            $nextLeafPage = self::coveringIndexLeafPage($nextEntries, $pageSize);
            $btreeIndexes[$index['name']] = [
                'rootPage' => $index['rootPage'],
                'column' => $index['column'],
                'path' => $index['path'],
                'source' => $index['source'],
                'expressionFunction' => $index['expressionFunction'],
                'expressionIndex' => $index['expressionIndex'],
                'collation' => $index['collation'],
                'descending' => $index['descending'],
                'partial' => $index['partial'],
                'unique' => $index['unique'],
                'coveringColumns' => $coveringColumns,
                'current_entries' => $currentEntries,
                'next_entries' => $nextEntries,
                'current_leaf_page_hex' => bin2hex($currentLeafPage),
                'next_leaf_page_hex' => bin2hex($nextLeafPage),
                'current_cell_count' => count($currentEntries),
                'next_cell_count' => count($nextEntries),
                'leaf_page_changed' => $currentLeafPage !== $nextLeafPage,
            ];

            foreach ($deletedRows as $rowid => $row) {
                $entry = self::coveringIndexEntry($row, $index, $coveringColumns, $rowidColumn);
                if (!$entry['present']) {
                    continue;
                }

                $delete = [
                    'operation' => 'delete-current',
                    'index' => $index['name'],
                    'rootPage' => $index['rootPage'],
                    'rowid' => is_numeric($rowid) ? (int) $rowid : $rowid,
                    'column' => $index['column'],
                    'path' => $index['path'],
                    'source' => $index['source'],
                    'expressionFunction' => $index['expressionFunction'],
                    'expressionIndex' => $index['expressionIndex'],
                    'key' => $entry['key'],
                    'coveringColumns' => $coveringColumns,
                    'coveringValues' => $entry['coveringValues'],
                    'record' => $entry['record'],
                    'record_hex' => bin2hex(SQLiteRecord::encode($entry['record'])),
                    'partial' => $index['partial'],
                    'collation' => $index['collation'],
                    'descending' => $index['descending'],
                    'unique' => $index['unique'],
                ];
                $cell = SQLiteIndexCell::encode(SQLiteRecord::encode($entry['record']));
                $deleteEntries[] = $delete;
                $actions[] = $delete + [
                    'action' => 'delete',
                    'cell_hex' => bin2hex($cell),
                    'cell_bytes' => strlen($cell),
                    'pageSize' => $pageSize,
                ];
            }
        }

        return [
            'table' => $analysis['table'],
            'generated_columns' => array_values($generatedColumns),
            'before' => $before,
            'after' => $after,
            'deleted_rows' => array_values($deletedRows),
            'missing_rowids' => $missingRowids,
            'delete_entries' => $deleteEntries,
            'btree_indexes' => $btreeIndexes,
            'btree_actions' => $actions,
            'btree_action_count' => count($actions),
            'changes' => count($deletedRows),
            'pageSize' => $pageSize,
        ];
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @param list<array{name?:string,sql:string,rootPage?:int,unique?:bool}> $indexes
     * @param list<int|string> $deleteRowids
     * @return array<string,mixed>
     */
    public static function deleteBtreeYieldPlan(string $createTableSql, array $rows, array $indexes, array $deleteRowids, int $pageSize = 512): array
    {
        if ($pageSize < 512) {
            throw new \InvalidArgumentException('SQLite generated JSON path DELETE B-tree yield plan requires a page size of at least 512 bytes');
        }

        $analysis = SQLiteGeneratedColumnDependencyPlan::analyze($createTableSql);
        if ($analysis['status'] !== 'ok') {
            throw new \InvalidArgumentException($analysis['message'] ?? 'SQLite generated JSON path DELETE index plan cannot evaluate cyclic generated columns');
        }
        $rowidColumn = self::rowidColumn($createTableSql);

        $generatedColumns = self::generatedJsonColumns($analysis['columns'], $analysis['order']);
        if ($generatedColumns === []) {
            throw new \InvalidArgumentException('SQLite generated JSON path DELETE index plan requires at least one json_extract generated column');
        }

        $indexPlans = self::indexPlans($indexes, $generatedColumns);
        if ($indexPlans === []) {
            throw new \InvalidArgumentException('SQLite generated JSON path DELETE index plan requires an index on a generated JSON path column');
        }

        $current = array_map(static fn (array $row): array => self::evaluateGeneratedColumns($row, $generatedColumns), array_values($rows));
        $positions = self::rowPositions($current, $rowidColumn);
        $deleteSet = [];
        foreach ($deleteRowids as $rowid) {
            if (!is_int($rowid) && !is_string($rowid)) {
                throw new \InvalidArgumentException('SQLite generated JSON path DELETE rowid must be integer or text');
            }
            $deleteSet[(string) $rowid] = $rowid;
        }

        $next = [];
        $deletedRows = [];
        $skippedRowids = [];
        foreach ($deleteSet as $key => $rowid) {
            if (!array_key_exists($key, $positions)) {
                $skippedRowids[] = $rowid;
            }
        }

        foreach ($current as $row) {
            $rowid = self::rowidValue($row, $rowidColumn, 'DELETE');

            if (array_key_exists((string) $rowid, $deleteSet)) {
                $deletedRows[] = $row;
                continue;
            }
            $next[] = $row;
        }

        self::assertUniqueNextKeys($next, $indexPlans);

        $indexDeletes = [];
        foreach ($deletedRows as $deletedRow) {
            $rowid = self::rowidValue($deletedRow, $rowidColumn, 'DELETE');
            foreach ($indexPlans as $index) {
                $entry = self::indexEntry($deletedRow, $index);
                if (!$entry['present']) {
                    continue;
                }

                $indexDeletes[] = [
                    'index' => $index['name'],
                    'rootPage' => $index['rootPage'],
                    'rowid' => $rowid,
                    'column' => $index['column'],
                    'path' => $index['path'],
                    'source' => $index['source'],
                    'expressionFunction' => $index['expressionFunction'],
                    'expressionIndex' => $index['expressionIndex'],
                    'current' => $entry['key'],
                    'next' => null,
                    'delete' => true,
                    'insert' => false,
                    'partial' => $index['partial'],
                    'collation' => $index['collation'],
                    'descending' => $index['descending'],
                    'unique' => $index['unique'],
                ];
            }
        }

        $btreeIndexes = [];
        foreach ($indexPlans as $index) {
            $currentEntries = self::btreeEntries($current, $index, $rowidColumn);
            $nextEntries = self::btreeEntries($next, $index, $rowidColumn);
            $currentLeafPage = self::indexLeafPage($currentEntries, $pageSize);
            $nextLeafPage = self::indexLeafPage($nextEntries, $pageSize);
            $btreeIndexes[$index['name']] = [
                'rootPage' => $index['rootPage'],
                'column' => $index['column'],
                'path' => $index['path'],
                'source' => $index['source'],
                'expressionFunction' => $index['expressionFunction'],
                'expressionIndex' => $index['expressionIndex'],
                'collation' => $index['collation'],
                'descending' => $index['descending'],
                'partial' => $index['partial'],
                'unique' => $index['unique'],
                'current_entries' => $currentEntries,
                'next_entries' => $nextEntries,
                'current_leaf_page_hex' => bin2hex($currentLeafPage),
                'next_leaf_page_hex' => bin2hex($nextLeafPage),
                'current_cell_count' => count($currentEntries),
                'next_cell_count' => count($nextEntries),
                'deleted_cell_count' => max(0, count($currentEntries) - count($nextEntries)),
                'leaf_page_changed' => $currentLeafPage !== $nextLeafPage,
            ];
        }

        $actions = [];
        foreach ($indexDeletes as $delete) {
            $actions[] = self::btreeAction('delete', $delete, $pageSize);
        }

        return [
            'table' => $analysis['table'],
            'generated_columns' => array_values($generatedColumns),
            'current' => $current,
            'next' => $next,
            'deleted_rows' => $deletedRows,
            'skipped_rowids' => $skippedRowids,
            'index_deletes' => $indexDeletes,
            'btree_indexes' => $btreeIndexes,
            'btree_actions' => $actions,
            'btree_action_count' => count($actions),
            'changes' => count($deletedRows),
            'pageSize' => $pageSize,
        ];
    }

    /**
     * @param list<array{name:string,generated:bool,storage:string|null,expression:string|null,dependencies:list<string>}> $columns
     * @param list<string> $order
     * @return array<string,array{name:string,source:string,path:string,functionName:string,storage:string}>
     */
    private static function generatedJsonColumns(array $columns, array $order): array
    {
        $byName = [];
        foreach ($columns as $column) {
            if (!$column['generated'] || $column['expression'] === null) {
                continue;
            }

            $json = self::jsonExtractExpression($column['expression']);
            if ($json === null) {
                continue;
            }

            $byName[strtolower($column['name'])] = [
                'name' => $column['name'],
                'source' => $json['source'],
                'path' => $json['path'],
                'functionName' => $json['functionName'],
                'storage' => $column['storage'] ?? 'VIRTUAL',
            ];
        }

        $ordered = [];
        foreach ($order as $name) {
            $key = strtolower($name);
            if (isset($byName[$key])) {
                $ordered[$key] = $byName[$key];
            }
        }
        foreach ($byName as $key => $column) {
            $ordered[$key] ??= $column;
        }

        return $ordered;
    }

    /**
     * @return null|array{source:string,path:string,functionName:string}
     */
    private static function jsonExtractExpression(string $expression): ?array
    {
        if (preg_match('/^\s*(jsonb?_extract)\s*\(\s*("?)([A-Za-z_][A-Za-z0-9_]*)\2\s*,\s*\'((?:\'\'|[^\'])+)\'\s*\)\s*$/i', $expression, $matches) !== 1) {
            return null;
        }

        $path = str_replace("''", "'", $matches[4]);
        if (!SQLiteJsonPath::isWellFormed($path)) {
            throw new \InvalidArgumentException('SQLite generated JSON path index column path is malformed');
        }

        return ['source' => $matches[3], 'path' => $path, 'functionName' => strtolower($matches[1])];
    }

    /**
     * @param list<array{name?:string,sql:string,rootPage?:int,unique?:bool}> $indexes
     * @param array<string,array{name:string,source:string,path:string,functionName:string,storage:string}> $generatedColumns
     * @return list<array{name:string,rootPage:int|null,column:string,path:string,source:string,expressionFunction:string|null,expressionIndex:bool,partial:bool,partialPredicate:?SQLiteIndexPredicate,collation:string,descending:bool,unique:bool}>
     */
    private static function indexPlans(array $indexes, array $generatedColumns): array
    {
        $plans = [];
        foreach ($indexes as $index) {
            $sql = $index['sql'] ?? null;
            if (!is_string($sql) || $sql === '') {
                throw new \InvalidArgumentException('SQLite generated JSON path index needs index SQL text');
            }
            $column = SQLiteCreateIndex::firstColumn($sql);
            $generatedColumn = null;
            $expressionFunction = null;
            $expressionIndex = false;
            if ($column !== null) {
                $key = strtolower($column->columnName);
                $generatedColumn = $generatedColumns[$key] ?? null;
            }

            if ($generatedColumn === null) {
                $expression = SQLiteCreateIndex::firstJsonExtractExpression($sql);
                if ($expression === null) {
                    continue;
                }
                foreach ($generatedColumns as $candidate) {
                    if (
                        strcasecmp($candidate['source'], $expression->columnName) === 0
                        && $candidate['path'] === $expression->path
                        && $candidate['functionName'] === $expression->functionName
                    ) {
                        $generatedColumn = $candidate;
                        $column = new SQLiteIndexColumn(
                            $candidate['name'],
                            $expression->collation,
                            $expression->descending,
                            $expression->partial,
                            $expression->partialPredicate,
                        );
                        $expressionFunction = $expression->functionName;
                        $expressionIndex = true;
                        break;
                    }
                }
            }

            if ($column === null || $generatedColumn === null) {
                continue;
            }

            $plans[] = [
                'name' => is_string($index['name'] ?? null) ? $index['name'] : self::indexName($sql),
                'rootPage' => isset($index['rootPage']) ? (int) $index['rootPage'] : null,
                'column' => $generatedColumn['name'],
                'path' => $generatedColumn['path'],
                'source' => $generatedColumn['source'],
                'expressionFunction' => $expressionFunction,
                'expressionIndex' => $expressionIndex,
                'partial' => $column->partial,
                'partialPredicate' => $column->partialPredicate,
                'collation' => strtoupper($column->collation),
                'descending' => $column->descending,
                'unique' => (bool) ($index['unique'] ?? stripos($sql, 'CREATE UNIQUE INDEX') !== false),
            ];
        }

        return $plans;
    }

    private static function indexName(string $sql): string
    {
        if (preg_match('/^\s*CREATE\s+(?:UNIQUE\s+)?INDEX\s+(?:IF\s+NOT\s+EXISTS\s+)?(?:"((?:""|[^"])+)"|`([^`]+)`|\[([^\]]+)\]|([A-Za-z_][A-Za-z0-9_]*))/i', $sql, $matches) !== 1) {
            return 'sqlite_generated_json_path_index';
        }

        foreach ([1, 2, 3, 4] as $index) {
            if (($matches[$index] ?? '') !== '') {
                return str_replace('""', '"', $matches[$index]);
            }
        }

        return 'sqlite_generated_json_path_index';
    }

    /**
     * @param array<string,mixed> $row
     * @param array<string,array{name:string,source:string,path:string,storage:string}> $generatedColumns
     * @return array<string,mixed>
     */
    private static function evaluateGeneratedColumns(array $row, array $generatedColumns): array
    {
        foreach ($generatedColumns as $column) {
            $row[$column['name']] = self::canonicalKey(SQLiteJsonExtract::extract(self::jsonValue($row, $column['source']), $column['path']));
        }

        return $row;
    }

    /**
     * @param array<string,mixed> $row
     */
    private static function jsonValue(array $row, string $column): string|SQLiteBlobValue|null
    {
        $value = $row[$column] ?? null;
        if ($value !== null && !is_string($value) && !$value instanceof SQLiteBlobValue) {
            throw new \InvalidArgumentException('SQLite generated JSON path source column must be text JSON, JSONB, or NULL');
        }

        return $value;
    }

    /**
     * @param array<string,array{name:string,source:string,path:string,storage:string}> $generatedColumns
     */
    private static function jsonColumnName(array $generatedColumns): string
    {
        $sources = array_values(array_unique(array_map(static fn (array $column): string => $column['source'], $generatedColumns)));
        if (count($sources) !== 1) {
            throw new \InvalidArgumentException('SQLite generated JSON path index UPDATE supports one JSON source column per plan');
        }

        return $sources[0];
    }

    /**
     * @param array<string,mixed> $update
     * @param array<string,array{name:string,source:string,path:string,functionName:string,storage:string}> $generatedColumns
     */
    private static function mutationJsonColumn(array $update, array $generatedColumns): string
    {
        $column = $update['column'] ?? null;
        if ($column === null) {
            return self::jsonColumnName($generatedColumns);
        }
        if (!is_string($column) || $column === '') {
            throw new \InvalidArgumentException('SQLite generated JSON path index UPDATE source column must be text');
        }

        foreach ($generatedColumns as $generatedColumn) {
            if (strcasecmp($generatedColumn['source'], $column) === 0) {
                return $generatedColumn['source'];
            }
        }

        throw new \InvalidArgumentException('SQLite generated JSON path index UPDATE source column is not generated-indexed');
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return array<string,int>
     */
    private static function rowPositions(array $rows, ?string $rowidColumn): array
    {
        $positions = [];
        foreach ($rows as $position => $row) {
            $rowid = self::rowidValue($row, $rowidColumn, 'index');
            $key = (string) $rowid;
            if (array_key_exists($key, $positions)) {
                throw new \InvalidArgumentException('SQLite generated JSON path index rows need unique rowids');
            }
            $positions[$key] = $position;
        }

        return $positions;
    }

    /**
     * @param array<string,mixed> $row
     * @param array{name:string,rootPage:int|null,column:string,path:string,partial:bool,partialPredicate:?SQLiteIndexPredicate,collation:string,descending:bool,unique:bool} $index
     * @return array{present:bool,key:mixed}
     */
    private static function indexEntry(array $row, array $index): array
    {
        $key = $row[$index['column']] ?? null;
        $present = true;
        if ($index['partial'] && $index['partialPredicate'] instanceof SQLiteIndexPredicate) {
            $present = $index['partialPredicate']->isImpliedByPointLookup($index['column'], $key, $index['collation']);
        }

        return ['present' => $present, 'key' => $present ? $key : null];
    }

    private static function rowidColumn(string $createTableSql): ?string
    {
        $body = self::parenthesizedBody($createTableSql);
        if ($body === null) {
            return null;
        }

        foreach (self::splitTopLevel($body, ',') as $definition) {
            $definition = trim($definition);
            if ($definition === '') {
                continue;
            }

            $constraint = self::stripLeadingConstraint($definition);
            if (
                self::startsWithKeyword($constraint, 'PRIMARY')
                || self::startsWithKeyword($constraint, 'UNIQUE')
                || self::startsWithKeyword($constraint, 'CHECK')
                || self::startsWithKeyword($constraint, 'FOREIGN')
            ) {
                continue;
            }

            $identifier = self::readIdentifier($definition);
            if ($identifier === null) {
                continue;
            }

            $tail = substr($definition, $identifier['end']);
            if (preg_match('/\bINTEGER\b/i', $tail) === 1 && preg_match('/\bPRIMARY\s+KEY\b/i', $tail) === 1) {
                return $identifier['identifier'];
            }
        }

        return null;
    }

    /**
     * @param array<string,mixed> $row
     */
    private static function rowidValue(array $row, ?string $rowidColumn, string $context): int|string
    {
        $candidates = [];
        if ($rowidColumn !== null) {
            $candidates[] = $rowidColumn;
        }
        array_push($candidates, 'rowid', '_rowid_', 'oid');

        foreach ($candidates as $candidate) {
            foreach ($row as $column => $value) {
                if (strcasecmp((string) $column, $candidate) !== 0) {
                    continue;
                }
                if (is_int($value) || is_string($value)) {
                    return $value;
                }
                throw new \InvalidArgumentException("SQLite generated JSON path {$context} rowid value must be integer or text");
            }
        }

        throw new \InvalidArgumentException("SQLite generated JSON path {$context} rows need the table INTEGER PRIMARY KEY rowid column or rowid alias");
    }

    private static function canonicalKey(mixed $value): mixed
    {
        if ($value instanceof SQLiteBlobValue) {
            return ['jsonb' => bin2hex($value->bytes)];
        }

        return $value;
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @param list<array{name:string,rootPage:int|null,column:string,path:string,partial:bool,partialPredicate:?SQLiteIndexPredicate,collation:string,descending:bool,unique:bool}> $indexes
     */
    private static function assertUniqueNextKeys(array $rows, array $indexes): void
    {
        foreach ($indexes as $index) {
            if (!$index['unique']) {
                continue;
            }

            $seen = [];
            foreach ($rows as $row) {
                $entry = self::indexEntry($row, $index);
                if (!$entry['present'] || $entry['key'] === null) {
                    continue;
                }

                $fingerprint = is_scalar($entry['key'])
                    ? get_debug_type($entry['key']) . ':' . (string) $entry['key']
                    : serialize($entry['key']);
                if (array_key_exists($fingerprint, $seen)) {
                    throw new \InvalidArgumentException("SQLite generated JSON path unique index {$index['name']} conflict");
                }
                $seen[$fingerprint] = true;
            }
        }
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @param array{name:string,rootPage:int|null,column:string,path:string,partial:bool,partialPredicate:?SQLiteIndexPredicate,collation:string,descending:bool,unique:bool} $index
     * @return list<array{key:mixed,rowid:int|string,record:list<mixed>,record_hex:string}>
     */
    private static function btreeEntries(array $rows, array $index, ?string $rowidColumn): array
    {
        $entries = [];
        foreach ($rows as $row) {
            $entry = self::indexEntry($row, $index);
            if (!$entry['present']) {
                continue;
            }

            $rowid = self::rowidValue($row, $rowidColumn, 'B-tree');

            $record = [$entry['key'], $rowid];
            $entries[] = [
                'key' => $entry['key'],
                'rowid' => $rowid,
                'record' => $record,
                'record_hex' => bin2hex(SQLiteRecord::encode($record)),
            ];
        }

        usort($entries, static fn (array $left, array $right): int => self::compareBtreeEntries($left, $right, $index));

        return $entries;
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @param array{name:string,rootPage:int|null,column:string,path:string,partial:bool,partialPredicate:?SQLiteIndexPredicate,collation:string,descending:bool,unique:bool} $index
     * @param list<string> $coveringColumns
     * @return list<array{key:mixed,rowid:int|string,coveringValues:array<string,mixed>,record:list<mixed>,record_hex:string}>
     */
    private static function coveringBtreeEntries(array $rows, array $index, array $coveringColumns, ?string $rowidColumn): array
    {
        $entries = [];
        foreach ($rows as $row) {
            $entry = self::coveringIndexEntry($row, $index, $coveringColumns, $rowidColumn);
            if (!$entry['present']) {
                continue;
            }

            $entries[] = [
                'key' => $entry['key'],
                'rowid' => $entry['rowid'],
                'coveringValues' => $entry['coveringValues'],
                'record' => $entry['record'],
                'record_hex' => bin2hex(SQLiteRecord::encode($entry['record'])),
            ];
        }

        usort($entries, static fn (array $left, array $right): int => self::compareBtreeEntries($left, $right, $index));

        return $entries;
    }

    /**
     * @param array<string,mixed> $row
     * @param array{name:string,rootPage:int|null,column:string,path:string,partial:bool,partialPredicate:?SQLiteIndexPredicate,collation:string,descending:bool,unique:bool} $index
     * @param list<string> $coveringColumns
     * @return array{present:bool,key:mixed,rowid:int|string,coveringValues:array<string,mixed>,record:list<mixed>}
     */
    private static function coveringIndexEntry(array $row, array $index, array $coveringColumns, ?string $rowidColumn): array
    {
        $entry = self::indexEntry($row, $index);
        $rowid = self::rowidValue($row, $rowidColumn, 'covering-index DELETE');

        $coveringValues = [];
        foreach ($coveringColumns as $column) {
            if (strcasecmp($column, $index['column']) === 0) {
                continue;
            }
            if (
                strcasecmp($column, 'rowid') === 0
                || strcasecmp($column, '_rowid_') === 0
                || strcasecmp($column, 'oid') === 0
                || ($rowidColumn !== null && strcasecmp($column, $rowidColumn) === 0)
            ) {
                continue;
            }

            $matched = false;
            foreach ($row as $rowColumn => $value) {
                if (strcasecmp((string) $rowColumn, $column) === 0) {
                    $coveringValues[$column] = $value;
                    $matched = true;
                    break;
                }
            }
            if (!$matched) {
                throw new \InvalidArgumentException("SQLite generated JSON path covering-index DELETE row is missing covering column {$column}");
            }
        }

        return [
            'present' => $entry['present'],
            'key' => $entry['key'],
            'rowid' => $rowid,
            'coveringValues' => $coveringValues,
            'record' => array_merge([$entry['key']], array_values($coveringValues), [$rowid]),
        ];
    }

    private static function parenthesizedBody(string $sql): ?string
    {
        $open = strpos($sql, '(');
        if ($open === false) {
            return null;
        }

        $close = self::matchingParen($sql, $open);

        return $close === null ? null : substr($sql, $open + 1, $close - $open - 1);
    }

    /**
     * @return list<string>
     */
    private static function splitTopLevel(string $sql, string $delimiter): array
    {
        $parts = [];
        $start = 0;
        $depth = 0;
        $quote = null;
        $length = strlen($sql);

        for ($i = 0; $i < $length; $i++) {
            $char = $sql[$i];
            if ($quote !== null) {
                if ($char === $quote) {
                    if ($quote === "'" && ($sql[$i + 1] ?? null) === "'") {
                        $i++;
                        continue;
                    }
                    $quote = null;
                }
                continue;
            }
            if ($char === "'" || $char === '"' || $char === '`') {
                $quote = $char;
                continue;
            }
            if ($char === '[') {
                $quote = ']';
                continue;
            }
            if ($char === '(') {
                $depth++;
                continue;
            }
            if ($char === ')') {
                $depth--;
                continue;
            }
            if ($depth === 0 && $char === $delimiter) {
                $parts[] = trim(substr($sql, $start, $i - $start));
                $start = $i + 1;
            }
        }
        $parts[] = trim(substr($sql, $start));

        return array_values(array_filter($parts, static fn (string $part): bool => $part !== ''));
    }

    private static function matchingParen(string $sql, int $open): ?int
    {
        $depth = 0;
        $quote = null;
        $length = strlen($sql);

        for ($i = $open; $i < $length; $i++) {
            $char = $sql[$i];
            if ($quote !== null) {
                if ($char === $quote) {
                    if ($quote === "'" && ($sql[$i + 1] ?? null) === "'") {
                        $i++;
                        continue;
                    }
                    $quote = null;
                }
                continue;
            }
            if ($char === "'" || $char === '"' || $char === '`') {
                $quote = $char;
                continue;
            }
            if ($char === '[') {
                $quote = ']';
                continue;
            }
            if ($char === '(') {
                $depth++;
                continue;
            }
            if ($char === ')') {
                $depth--;
                if ($depth === 0) {
                    return $i;
                }
            }
        }

        return null;
    }

    private static function stripLeadingConstraint(string $definition): string
    {
        if (preg_match('/^\s*CONSTRAINT\s+(?:"(?:""|[^"])+"|`[^`]+`|\[[^\]]+\]|[A-Za-z_][A-Za-z0-9_]*)\s+/i', $definition, $matches) === 1) {
            return substr($definition, strlen($matches[0]));
        }

        return $definition;
    }

    private static function startsWithKeyword(string $definition, string $keyword): bool
    {
        return preg_match('/^\s*' . preg_quote($keyword, '/') . '\b/i', $definition) === 1;
    }

    /**
     * @return null|array{identifier:string,end:int}
     */
    private static function readIdentifier(string $definition): ?array
    {
        if (preg_match('/^\s*"((?:""|[^"])+)"/', $definition, $matches, PREG_OFFSET_CAPTURE) === 1) {
            return ['identifier' => str_replace('""', '"', $matches[1][0]), 'end' => $matches[0][1] + strlen($matches[0][0])];
        }
        if (preg_match('/^\s*`([^`]+)`/', $definition, $matches, PREG_OFFSET_CAPTURE) === 1) {
            return ['identifier' => $matches[1][0], 'end' => $matches[0][1] + strlen($matches[0][0])];
        }
        if (preg_match('/^\s*\[([^\]]+)\]/', $definition, $matches, PREG_OFFSET_CAPTURE) === 1) {
            return ['identifier' => $matches[1][0], 'end' => $matches[0][1] + strlen($matches[0][0])];
        }
        if (preg_match('/^\s*([A-Za-z_][A-Za-z0-9_]*)/', $definition, $matches, PREG_OFFSET_CAPTURE) === 1) {
            return ['identifier' => $matches[1][0], 'end' => $matches[0][1] + strlen($matches[0][0])];
        }

        return null;
    }

    /**
     * @param array{key:mixed,rowid:int|string} $left
     * @param array{key:mixed,rowid:int|string} $right
     * @param array{name:string,rootPage:int|null,column:string,path:string,partial:bool,partialPredicate:?SQLiteIndexPredicate,collation:string,descending:bool,unique:bool} $index
     */
    private static function compareBtreeEntries(array $left, array $right, array $index): int
    {
        $keyComparison = self::compareIndexKeys($left['key'], $right['key'], $index['collation']);
        if ($keyComparison !== 0) {
            return $index['descending'] ? -$keyComparison : $keyComparison;
        }

        return self::compareIndexKeys($left['rowid'], $right['rowid'], 'BINARY');
    }

    private static function compareIndexKeys(mixed $left, mixed $right, string $collation): int
    {
        if ($left === null || $right === null) {
            return $left === $right ? 0 : ($left === null ? -1 : 1);
        }
        if ((is_int($left) || is_float($left)) && (is_int($right) || is_float($right))) {
            return $left <=> $right;
        }

        $leftText = (string) $left;
        $rightText = (string) $right;
        if (strtoupper($collation) === 'NOCASE') {
            $leftText = strtolower($leftText);
            $rightText = strtolower($rightText);
        }

        return $leftText <=> $rightText;
    }

    /**
     * @param list<array{record:list<mixed>}> $entries
     */
    private static function indexLeafPage(array $entries, int $pageSize): string
    {
        return SQLiteIndexLeafPage::assemble(array_map(
            static fn (array $entry): string => SQLiteIndexCell::encode(SQLiteRecord::encode($entry['record'])),
            $entries,
        ), $pageSize);
    }

    /**
     * @param list<array{record:list<mixed>}> $entries
     */
    private static function coveringIndexLeafPage(array $entries, int $pageSize): string
    {
        return self::indexLeafPage($entries, $pageSize);
    }

    /**
     * @param array<string,mixed> $update
     * @return array<string,mixed>
     */
    private static function btreeAction(string $action, array $update, int $pageSize): array
    {
        $key = $action === 'delete' ? $update['current'] : $update['next'];
        $record = [$key, $update['rowid']];
        $cell = SQLiteIndexCell::encode(SQLiteRecord::encode($record));

        return [
            'action' => $action,
            'index' => $update['index'],
            'rootPage' => $update['rootPage'],
            'rowid' => $update['rowid'],
            'column' => $update['column'],
            'path' => $update['path'],
            'source' => $update['source'] ?? null,
            'expressionFunction' => $update['expressionFunction'] ?? null,
            'expressionIndex' => (bool) ($update['expressionIndex'] ?? false),
            'key' => $key,
            'record' => $record,
            'record_hex' => bin2hex(SQLiteRecord::encode($record)),
            'cell_hex' => bin2hex($cell),
            'cell_bytes' => strlen($cell),
            'pageSize' => $pageSize,
            'collation' => $update['collation'],
            'descending' => $update['descending'],
            'partial' => $update['partial'],
            'unique' => $update['unique'],
        ];
    }
}
