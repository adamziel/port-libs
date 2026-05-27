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
        $positions = self::rowPositions($after);
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
            $jsonColumn = self::jsonColumnName($generatedColumns);
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

        $btreeIndexes = [];
        $actions = [];
        foreach ($indexPlans as $index) {
            $currentEntries = self::btreeEntries($plan['before'], $index);
            $nextEntries = self::btreeEntries($plan['after'], $index);
            $currentLeafPage = self::indexLeafPage($currentEntries, $pageSize);
            $nextLeafPage = self::indexLeafPage($nextEntries, $pageSize);
            $btreeIndexes[$index['name']] = [
                'rootPage' => $index['rootPage'],
                'column' => $index['column'],
                'path' => $index['path'],
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
     * @param list<array{name:string,generated:bool,storage:string|null,expression:string|null,dependencies:list<string>}> $columns
     * @param list<string> $order
     * @return array<string,array{name:string,source:string,path:string,storage:string}>
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
     * @return null|array{source:string,path:string}
     */
    private static function jsonExtractExpression(string $expression): ?array
    {
        if (preg_match('/^\s*jsonb?_extract\s*\(\s*("?)([A-Za-z_][A-Za-z0-9_]*)\1\s*,\s*\'((?:\'\'|[^\'])+)\'\s*\)\s*$/i', $expression, $matches) !== 1) {
            return null;
        }

        $path = str_replace("''", "'", $matches[3]);
        if (!SQLiteJsonPath::isWellFormed($path)) {
            throw new \InvalidArgumentException('SQLite generated JSON path index column path is malformed');
        }

        return ['source' => $matches[2], 'path' => $path];
    }

    /**
     * @param list<array{name?:string,sql:string,rootPage?:int,unique?:bool}> $indexes
     * @param array<string,array{name:string,source:string,path:string,storage:string}> $generatedColumns
     * @return list<array{name:string,rootPage:int|null,column:string,path:string,partial:bool,partialPredicate:?SQLiteIndexPredicate,collation:string,descending:bool,unique:bool}>
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
            if ($column === null) {
                continue;
            }
            $key = strtolower($column->columnName);
            if (!isset($generatedColumns[$key])) {
                continue;
            }

            $plans[] = [
                'name' => is_string($index['name'] ?? null) ? $index['name'] : self::indexName($sql),
                'rootPage' => isset($index['rootPage']) ? (int) $index['rootPage'] : null,
                'column' => $generatedColumns[$key]['name'],
                'path' => $generatedColumns[$key]['path'],
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
     * @param list<array<string,mixed>> $rows
     * @return array<string,int>
     */
    private static function rowPositions(array $rows): array
    {
        $positions = [];
        foreach ($rows as $position => $row) {
            $rowid = $row['option_id'] ?? $row['rowid'] ?? null;
            if (!is_int($rowid) && !is_string($rowid)) {
                throw new \InvalidArgumentException('SQLite generated JSON path index rows need option_id or rowid');
            }
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
    private static function btreeEntries(array $rows, array $index): array
    {
        $entries = [];
        foreach ($rows as $row) {
            $entry = self::indexEntry($row, $index);
            if (!$entry['present']) {
                continue;
            }

            $rowid = $row['option_id'] ?? $row['rowid'] ?? null;
            if (!is_int($rowid) && !is_string($rowid)) {
                throw new \InvalidArgumentException('SQLite generated JSON path B-tree rows need option_id or rowid');
            }

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
