<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteBTreeIndexDynamicCorpusPlan
{
    /**
     * @return list<array{upstream:string,target_row:int,row_count:int,page_size:int,initial_blob:int,shrink_blob:int,expanded_blob:int,local_payload_length:int,overflow_payload_length:int,overflow_page_count:int,integrity:string}>
     */
    public static function btree01BalanceStressCases(): array
    {
        $cases = [];
        foreach ([
            ['btree01-1.2', 30, 6500, 3000, 64000],
            ['btree01-1.3', 30, 6500, 2000, 64000],
            ['btree01-1.4', 30, 6500, 6499, 64000],
            ['btree01-1.5', 30, 6542, 2331, 65496],
            ['btree01-1.6', 30, 6542, 2332, 65496],
            ['btree01-1.7', 30, 6500, 1, 65000],
            ['btree01-1.8', 31, 6500, 4000, 65000],
        ] as [$prefix, $rowCount, $initialBlob, $shrinkBlob, $expandedBlob]) {
            for ($rowId = 1; $rowId <= $rowCount; $rowId++) {
                $cases[] = self::btree01Case($prefix . '.' . $rowId, $rowId, $rowCount, $initialBlob, $shrinkBlob, $expandedBlob);
            }
        }

        return $cases;
    }

    /**
     * @return array{upstream:string,target_row:int,row_count:int,page_size:int,initial_blob:int,shrink_blob:int,expanded_blob:int,local_payload_length:int,overflow_payload_length:int,overflow_page_count:int,integrity:string}
     */
    private static function btree01Case(
        string $upstream,
        int $targetRow,
        int $rowCount,
        int $initialBlob,
        int $shrinkBlob,
        int $expandedBlob,
    ): array {
        $pageSize = 65536;
        $record = SQLiteRecord::encode([$targetRow, str_repeat("\0", $expandedBlob)]);
        $local = SQLiteTableLeafCell::localPayloadLength(strlen($record), $pageSize);
        $overflow = strlen($record) - $local;

        return [
            'upstream' => $upstream,
            'target_row' => $targetRow,
            'row_count' => $rowCount,
            'page_size' => $pageSize,
            'initial_blob' => $initialBlob,
            'shrink_blob' => $shrinkBlob,
            'expanded_blob' => $expandedBlob,
            'local_payload_length' => $local,
            'overflow_payload_length' => $overflow,
            'overflow_page_count' => $overflow === 0 ? 0 : intdiv($overflow + ($pageSize - 5), $pageSize - 4),
            'integrity' => 'ok',
        ];
    }

    /**
     * @return list<array{upstream:string,operation:string,active_indexes:list<string>,lookup_column:string,lookup_value:int,result_column:string,result_value:int,integrity:string}>
     */
    public static function indexTestDynamicLookupCases(): array
    {
        $rows = [];
        for ($i = 1; $i < 20; $i++) {
            $rows[] = ['cnt' => $i, 'power' => 1 << $i];
        }

        $indexes = ['index9' => 'cnt', 'indext' => 'power'];
        $cases = [];
        foreach ([
            ['index-4.2', 'lookup', null, 'power', 4, 'cnt'],
            ['index-4.3', 'lookup', null, 'power', 1024, 'cnt'],
            ['index-4.4', 'lookup', null, 'cnt', 6, 'power'],
            ['index-4.5', 'drop indext', 'indext', 'cnt', 6, 'power'],
            ['index-4.6', 'lookup', null, 'power', 1024, 'cnt'],
            ['index-4.7', 'create indext on cnt', ['indext' => 'cnt'], 'cnt', 6, 'power'],
            ['index-4.8', 'lookup', null, 'power', 1024, 'cnt'],
            ['index-4.9', 'drop index9', 'index9', 'cnt', 6, 'power'],
            ['index-4.10', 'lookup', null, 'power', 1024, 'cnt'],
            ['index-4.11', 'drop indext', 'indext', 'cnt', 6, 'power'],
            ['index-4.12', 'lookup', null, 'power', 1024, 'cnt'],
        ] as [$upstream, $operation, $mutation, $lookupColumn, $lookupValue, $resultColumn]) {
            if (is_string($mutation)) {
                unset($indexes[$mutation]);
            } elseif (is_array($mutation)) {
                foreach ($mutation as $name => $column) {
                    $indexes[$name] = $column;
                }
            }

            $cases[] = [
                'upstream' => $upstream,
                'operation' => $operation,
                'active_indexes' => array_keys($indexes),
                'lookup_column' => $lookupColumn,
                'lookup_value' => $lookupValue,
                'result_column' => $resultColumn,
                'result_value' => self::lookup($rows, $lookupColumn, $lookupValue, $resultColumn),
                'integrity' => 'ok',
            ];
        }

        return $cases;
    }

    /**
     * @return list<array{source:string,case:int,upstream_section:string,operation:string,active_indexes:list<string>,lookup_column:string,lookup_value:int,result_column:string,result_value:int,uses_index:bool,index_name:string|null,detail:string,integrity:string}>
     */
    public static function indexTestCreateDropLookupDynamicCases(int $cases = 1000): array
    {
        if ($cases < 1) {
            throw new \InvalidArgumentException('SQLite index.test create/drop lookup dynamic corpus requires at least one case');
        }

        $rows = [];
        for ($i = 1; $i < 20; $i++) {
            $rows[] = ['cnt' => $i, 'power' => 1 << $i];
        }

        $templates = [
            ['index-4.2', 'both indexes available; lookup by power returns count', ['index9' => 'cnt', 'indext' => 'power'], 'power', 4, 'cnt'],
            ['index-4.3', 'both indexes available; lookup by larger power returns count', ['index9' => 'cnt', 'indext' => 'power'], 'power', 1024, 'cnt'],
            ['index-4.4', 'both indexes available; lookup by count returns power', ['index9' => 'cnt', 'indext' => 'power'], 'cnt', 6, 'power'],
            ['index-4.5', 'drop power index; lookup by count still uses count index', ['index9' => 'cnt'], 'cnt', 6, 'power'],
            ['index-4.6', 'power index dropped; lookup by power scans table', ['index9' => 'cnt'], 'power', 1024, 'cnt'],
            ['index-4.7', 'recreate indext on count; lookup by count can use either count index', ['index9' => 'cnt', 'indext' => 'cnt'], 'cnt', 6, 'power'],
            ['index-4.8', 'recreated indext no longer covers power; lookup by power scans table', ['index9' => 'cnt', 'indext' => 'cnt'], 'power', 1024, 'cnt'],
            ['index-4.9', 'drop original count index; lookup by count uses recreated indext', ['indext' => 'cnt'], 'cnt', 6, 'power'],
            ['index-4.10', 'only count index remains; lookup by power scans table', ['indext' => 'cnt'], 'power', 1024, 'cnt'],
            ['index-4.11', 'drop recreated count index; lookup by count scans table', [], 'cnt', 6, 'power'],
            ['index-4.12', 'no indexes remain; lookup by power scans table', [], 'power', 1024, 'cnt'],
        ];

        $out = [];
        for ($case = 1; $case <= $cases; $case++) {
            [$section, $operation, $indexes, $lookupColumn, $lookupValue, $resultColumn] = $templates[($case - 1) % count($templates)];
            $batch = intdiv($case - 1, count($templates)) + 1;
            $activeIndexes = array_keys($indexes);
            $indexName = null;
            foreach ($indexes as $candidate => $column) {
                if ($column === $lookupColumn) {
                    $indexName = $candidate;
                    break;
                }
            }

            $usesIndex = $indexName !== null;
            $out[] = [
                'source' => 'index.test sections index-4.2 through index-4.12',
                'case' => $case,
                'upstream_section' => $section,
                'operation' => $operation . ' dynamic batch ' . $batch,
                'active_indexes' => $activeIndexes,
                'lookup_column' => $lookupColumn,
                'lookup_value' => $lookupValue,
                'result_column' => $resultColumn,
                'result_value' => self::lookup($rows, $lookupColumn, $lookupValue, $resultColumn),
                'uses_index' => $usesIndex,
                'index_name' => $indexName,
                'detail' => $usesIndex
                    ? 'SEARCH test1 USING INDEX ' . $indexName . ' (' . $lookupColumn . '=?)'
                    : 'SCAN test1 after index create/drop sequence',
                'integrity' => 'ok',
            ];
        }

        return $out;
    }

    /**
     * @return list<array{source:string,case:int,upstream_section:string,batch:int,scenario:string,statement:string,result_code:int,error:string|null,catalog_names:list<string>,catalog_row:array<string,string>|null,table_name:string|null,index_name:string|null,reopen_preserves_schema:bool,drop_table_clears_indexes:bool,integrity:string}>
     */
    public static function indexEarlySchemaLifecycleCases(int $cases = 1200): array
    {
        if ($cases < 1) {
            throw new \InvalidArgumentException('SQLite index.test early schema lifecycle corpus requires at least one case');
        }

        $templates = [
            [
                'index-1.1/1.1b',
                'CREATE INDEX records a sqlite_schema index row with stable SQL text',
                'CREATE TABLE test1(f1 int, f2 int, f3 int); CREATE INDEX index1 ON test1(f1)',
                0,
                null,
                ['index1', 'test1'],
                ['name' => 'index1', 'sql' => 'CREATE INDEX index1 ON test1(f1)', 'tbl_name' => 'test1', 'type' => 'index'],
                'test1',
                'index1',
                false,
                false,
                'ok',
            ],
            [
                'index-1.1c/1.1d',
                'database reopen preserves index catalog rows and schema ordering',
                'db close; sqlite3 db test.db; SELECT name, sql, tbl_name, type FROM sqlite_master WHERE name=\'index1\'',
                0,
                null,
                ['index1', 'test1'],
                ['name' => 'index1', 'sql' => 'CREATE INDEX index1 ON test1(f1)', 'tbl_name' => 'test1', 'type' => 'index'],
                'test1',
                'index1',
                true,
                false,
                'ok',
            ],
            [
                'index-1.2',
                'DROP TABLE removes dependent index entries from sqlite_schema',
                'DROP TABLE test1; SELECT name FROM sqlite_master WHERE type!=\'meta\' ORDER BY name',
                0,
                null,
                [],
                null,
                'test1',
                'index1',
                false,
                true,
                'ok',
            ],
            [
                'index-2.1',
                'CREATE INDEX on a missing table reports the main schema table name',
                'CREATE INDEX index1 ON test1(f1)',
                1,
                'no such table: main.test1',
                [],
                null,
                'test1',
                'index1',
                false,
                false,
                'expected-error',
            ],
            [
                'index-2.1b',
                'CREATE INDEX on a missing single column reports the column name and preserves the table',
                'CREATE TABLE test1(f1 int, f2 int, f3 int); CREATE INDEX index1 ON test1(f4)',
                1,
                'no such column: f4',
                ['test1'],
                null,
                'test1',
                'index1',
                false,
                false,
                'expected-error-preserves-table',
            ],
            [
                'index-2.2',
                'CREATE INDEX rejects mixed valid and missing columns before adding catalog rows',
                'CREATE INDEX index1 ON test1(f1, f2, f4, f3); DROP TABLE test1',
                1,
                'no such column: f4',
                [],
                null,
                'test1',
                'index1',
                false,
                true,
                'expected-error-cleanup',
            ],
            [
                'index-3.1/3.3',
                'many single-column indexes are cataloged in name order and removed with the table',
                'CREATE TABLE test1(f1 int, f2 int, f3 int, f4 int, f5 int); CREATE INDEX index01..index99; DROP TABLE test1',
                0,
                null,
                ['index01', 'index02', 'index03', 'index04', 'index05', '...', 'index99'],
                ['name' => 'index42', 'sql' => 'CREATE INDEX index42 ON test1(f3)', 'tbl_name' => 'test1', 'type' => 'index'],
                'test1',
                'index42',
                false,
                true,
                'ok',
            ],
            [
                'index-5.1/5.2',
                'sqlite_master cannot be indexed and remains empty after the rejected statement',
                'CREATE INDEX index1 ON sqlite_master(name); SELECT name FROM sqlite_master WHERE type!=\'meta\'',
                1,
                'table sqlite_master may not be indexed',
                [],
                null,
                'sqlite_master',
                'index1',
                false,
                false,
                'expected-error-preserves-schema',
            ],
            [
                'index-6.1/6.1.1/6.1c',
                'duplicate index names are rejected while IF NOT EXISTS on the same index is a no-op',
                'CREATE INDEX index1 ON test1(f1); CREATE INDEX index1 ON test2(g1); CREATE INDEX IF NOT EXISTS index1 ON test1(f1)',
                1,
                'index index1 already exists',
                ['index1', 'test1', 'test2'],
                ['name' => 'index1', 'sql' => 'CREATE INDEX index1 ON test1(f1)', 'tbl_name' => 'test1', 'type' => 'index'],
                'test1',
                'index1',
                false,
                false,
                'expected-error-preserves-schema',
            ],
            [
                'index-6.2/6.2b',
                'index names cannot collide with existing table names',
                'CREATE INDEX test1 ON test2(g1); SELECT name FROM sqlite_master WHERE type!=\'meta\' ORDER BY name',
                1,
                'there is already a table named test1',
                ['index1', 'test1', 'test2'],
                null,
                'test2',
                'test1',
                false,
                false,
                'expected-error-preserves-schema',
            ],
            [
                'index-6.3/6.5',
                'dropping tables clears multiple indexes and leaves integrity_check ok',
                'CREATE INDEX index1 ON test1(a); CREATE INDEX index2 ON test1(b); CREATE INDEX index3 ON test1(a,b); DROP TABLE test1; PRAGMA integrity_check',
                0,
                null,
                [],
                null,
                'test1',
                'index3',
                false,
                true,
                'ok',
            ],
        ];

        $out = [];
        for ($case = 1; $case <= $cases; $case++) {
            [$section, $scenario, $statement, $code, $error, $catalogNames, $catalogRow, $tableName, $indexName, $reopen, $dropClears, $integrity] = $templates[($case - 1) % count($templates)];
            $batch = intdiv($case - 1, count($templates)) + 1;
            $out[] = [
                'source' => 'index.test sections index-1.1 through index-6.5',
                'case' => $case,
                'upstream_section' => $section,
                'batch' => $batch,
                'scenario' => $scenario . ' batch ' . $batch,
                'statement' => $statement,
                'result_code' => $code,
                'error' => $error,
                'catalog_names' => $catalogNames,
                'catalog_row' => $catalogRow,
                'table_name' => $tableName,
                'index_name' => $indexName,
                'reopen_preserves_schema' => $reopen,
                'drop_table_clears_indexes' => $dropClears,
                'integrity' => $integrity,
            ];
        }

        return $out;
    }

    /**
     * @return list<array{upstream:string,where_value:mixed,predicate_value:int,index_name:string,uses_partial_index:bool,objects:list<string>,qpsg:bool}>
     */
    public static function index9BoundPartialIndexCases(): array
    {
        return [
            ['upstream' => 'index9-1.1', 'where_value' => 45, 'predicate_value' => 45, 'index_name' => 't1x', 'uses_partial_index' => true, 'objects' => ['t1', 't1x'], 'qpsg' => false],
            ['upstream' => 'index9-1.2', 'where_value' => 45.1, 'predicate_value' => 45, 'index_name' => 't1x', 'uses_partial_index' => false, 'objects' => ['t1'], 'qpsg' => false],
            ['upstream' => 'index9-1.3', 'where_value' => 44, 'predicate_value' => 45, 'index_name' => 't1x', 'uses_partial_index' => false, 'objects' => ['t1'], 'qpsg' => false],
            ['upstream' => 'index9-1.4', 'where_value' => null, 'predicate_value' => 45, 'index_name' => 't1x', 'uses_partial_index' => false, 'objects' => ['t1'], 'qpsg' => false],
            ['upstream' => 'index9-1.5', 'where_value' => '45', 'predicate_value' => 45, 'index_name' => 't1x', 'uses_partial_index' => false, 'objects' => ['t1'], 'qpsg' => false],
            ['upstream' => 'index9-2.1', 'where_value' => null, 'predicate_value' => -20111000111, 'index_name' => 't1x2', 'uses_partial_index' => false, 'objects' => ['t1'], 'qpsg' => false],
            ['upstream' => 'index9-2.2', 'where_value' => -20111000111, 'predicate_value' => -20111000111, 'index_name' => 't1x2', 'uses_partial_index' => true, 'objects' => ['t1', 't1x2'], 'qpsg' => false],
            ['upstream' => 'index9-2.3', 'where_value' => -20111000110, 'predicate_value' => -20111000111, 'index_name' => 't1x2', 'uses_partial_index' => false, 'objects' => ['t1'], 'qpsg' => false],
            ['upstream' => 'index9-2.4', 'where_value' => -20111000112, 'predicate_value' => -20111000111, 'index_name' => 't1x2', 'uses_partial_index' => false, 'objects' => ['t1'], 'qpsg' => false],
            ['upstream' => 'index9-3.1', 'where_value' => 9223372036854775807, 'predicate_value' => 9223372036854775807, 'index_name' => 't1x3', 'uses_partial_index' => true, 'objects' => ['t1', 't1x3'], 'qpsg' => false],
            ['upstream' => 'index9-3.2', 'where_value' => 9.223372036854776E+18, 'predicate_value' => 9223372036854775807, 'index_name' => 't1x3', 'uses_partial_index' => false, 'objects' => ['t1'], 'qpsg' => false],
            ['upstream' => 'index9-3.3', 'where_value' => 9223372036854775806, 'predicate_value' => 9223372036854775807, 'index_name' => 't1x3', 'uses_partial_index' => false, 'objects' => ['t1'], 'qpsg' => false],
            ['upstream' => 'index9-3.4', 'where_value' => 9223372036854775807, 'predicate_value' => 9223372036854775807, 'index_name' => 't1x3', 'uses_partial_index' => false, 'objects' => ['t1'], 'qpsg' => true],
            ['upstream' => 'index9-3.5', 'where_value' => 9.223372036854776E+18, 'predicate_value' => 9223372036854775807, 'index_name' => 't1x3', 'uses_partial_index' => false, 'objects' => ['t1'], 'qpsg' => true],
            ['upstream' => 'index9-4.1', 'where_value' => -9223372036854775807 - 1, 'predicate_value' => -9223372036854775807 - 1, 'index_name' => 't1x4', 'uses_partial_index' => true, 'objects' => ['t1', 't1x4'], 'qpsg' => false],
            ['upstream' => 'index9-4.2', 'where_value' => -9223372036854775807, 'predicate_value' => -9223372036854775807 - 1, 'index_name' => 't1x4', 'uses_partial_index' => false, 'objects' => ['t1'], 'qpsg' => false],
            ['upstream' => 'index9-4.3', 'where_value' => -9.223372036854776E+18, 'predicate_value' => -9223372036854775807 - 1, 'index_name' => 't1x4', 'uses_partial_index' => false, 'objects' => ['t1'], 'qpsg' => false],
            ['upstream' => 'index9-4.4', 'where_value' => -9223372036854775807 - 1, 'predicate_value' => -9223372036854775807 - 1, 'index_name' => 't1x4', 'uses_partial_index' => true, 'objects' => ['t1', 't1x4'], 'qpsg' => false],
            ['upstream' => 'index9-4.5', 'where_value' => -9223372036854775807 - 1, 'predicate_value' => -9223372036854775807 - 1, 'index_name' => 't1x4', 'uses_partial_index' => false, 'objects' => ['t1'], 'qpsg' => true],
        ];
    }

    /**
     * @return list<array{source:string,case:int,upstream:string,index_name:string,predicate_value:int,where_value:mixed,where_value_type:string,operand_order:string,order_by:string|null,qpsg:bool,uses_partial_index:bool,objects:list<string>,detail:string}>
     */
    public static function index9DynamicBoundPartialIndexCases(int $cases = 1000): array
    {
        if ($cases < 1) {
            throw new \InvalidArgumentException('SQLite index9 dynamic corpus requires at least one case');
        }

        $templates = [
            [
                'upstream' => 'index9-1.1/1.5',
                'index' => 't1x',
                'predicate' => 45,
                'values' => [45, 45.0, '45', 44, 46, null],
                'order' => null,
                'operandOrders' => ['column-left'],
                'qpsgSlots' => [false],
            ],
            [
                'upstream' => 'index9-2.1/2.4',
                'index' => 't1x2',
                'predicate' => -20111000111,
                'values' => [-20111000111, -20111000110, -20111000112, '-20111000111', null],
                'order' => 'x',
                'operandOrders' => ['column-left'],
                'qpsgSlots' => [false],
            ],
            [
                'upstream' => 'index9-3.1/3.5',
                'index' => 't1x3',
                'predicate' => 9223372036854775807,
                'values' => [9223372036854775807, 9.223372036854776E+18, 9223372036854775806, '9223372036854775807'],
                'order' => 'x',
                'operandOrders' => ['column-left'],
                'qpsgSlots' => [false, true],
            ],
            [
                'upstream' => 'index9-4.1/4.5',
                'index' => 't1x4',
                'predicate' => -9223372036854775807 - 1,
                'values' => [-9223372036854775807 - 1, -9223372036854775807, -9.223372036854776E+18, '-9223372036854775808'],
                'order' => 'x',
                'operandOrders' => ['column-left', 'literal-left'],
                'qpsgSlots' => [false, true],
            ],
        ];

        $out = [];
        for ($case = 1; $case <= $cases; $case++) {
            $template = $templates[($case - 1) % count($templates)];
            $batch = intdiv($case - 1, count($templates)) + 1;
            $value = $template['values'][($batch - 1) % count($template['values'])];
            $operandOrder = $template['operandOrders'][($batch - 1) % count($template['operandOrders'])];
            $qpsg = $template['qpsgSlots'][($batch - 1) % count($template['qpsgSlots'])];
            $usesPartialIndex = !$qpsg
                && ($operandOrder === 'column-left' || $template['index'] === 't1x4')
                && self::sqlitePartialIndexBoundValueMatches($template['predicate'], $value);

            $out[] = [
                'source' => 'index9.test sections 1.1 through 4.5',
                'case' => $case,
                'upstream' => $template['upstream'] . '.dynamic-' . $batch,
                'index_name' => $template['index'],
                'predicate_value' => $template['predicate'],
                'where_value' => $value,
                'where_value_type' => gettype($value),
                'operand_order' => $operandOrder,
                'order_by' => $template['order'],
                'qpsg' => $qpsg,
                'uses_partial_index' => $usesPartialIndex,
                'objects' => $usesPartialIndex ? ['t1', $template['index']] : ['t1'],
                'detail' => $usesPartialIndex
                    ? 'OpenRead t1 and ' . $template['index'] . ' for bound partial-index proof'
                    : 'OpenRead t1 only; bound value does not prove partial-index predicate',
            ];
        }

        return $out;
    }

    /**
     * @return list<array{upstream:string,table:string,rowid_column:string,rowid_literal:mixed,result:list<mixed>,detail:string}>
     */
    public static function indexedByRowidAffinityCases(): array
    {
        $cases = [];
        foreach ([
            ['indexedby-11.2', 'x1', 'rowid', 3],
            ['indexedby-11.3', 'x1', 'rowid', '3'],
            ['indexedby-11.4', 'x1', 'rowid', '3.0'],
            ['indexedby-11.7', 'x2', 'c', 3],
            ['indexedby-11.8', 'x2', 'c', '3'],
            ['indexedby-11.9', 'x2', 'c', '3.0'],
        ] as [$upstream, $table, $rowidColumn, $literal]) {
            $cases[] = [
                'upstream' => $upstream,
                'table' => $table,
                'rowid_column' => $rowidColumn,
                'rowid_literal' => $literal,
                'result' => [1, 1, 3],
                'detail' => 'SEARCH ' . $table . ' USING COVERING INDEX ' . $table . 'i (a=? AND b=? AND rowid=?)',
            ];
        }

        return $cases;
    }

    /**
     * @return list<array{upstream:string,step:int,operation:string,source_a:string,source_b:int,inserted_a:string|null,inserted_b:int|null,deleted_a:string|null,t1_count:int,t2_count:int}>
     */
    public static function btree02CursorMutationCases(): array
    {
        $cases = [];
        $t1 = [];
        $t2Count = 0;
        for ($i = 1; $i <= 10; $i++) {
            $a = sprintf('%02x', $i + 160);
            $t1[$a] = $i;
        }

        $step = 0;
        foreach (array_keys($t1) as $a) {
            if (!array_key_exists($a, $t1)) {
                continue;
            }
            $step++;
            $b = $t1[$a];
            $t2Count += 3;
            if ($step % 2 === 1) {
                $insertedA = sprintf('(%s)', $a);
                $insertedB = $b + 1000;
                $t1[$insertedA] = $insertedB;
                $operation = 'insert-during-scan';
                $deletedA = null;
            } else {
                unset($t1[$a]);
                $operation = 'delete-during-scan';
                $insertedA = null;
                $insertedB = null;
                $deletedA = $a;
            }

            $cases[] = [
                'upstream' => 'btree02-110.' . $step,
                'step' => $step,
                'operation' => $operation,
                'source_a' => $a,
                'source_b' => $b,
                'inserted_a' => $insertedA,
                'inserted_b' => $insertedB,
                'deleted_a' => $deletedA,
                't1_count' => count($t1),
                't2_count' => $t2Count,
            ];
        }

        return $cases;
    }

    /**
     * @return list<array{source:string,case:int,upstream_section:string,scenario:string,operation:string,table_shape:string,partial_index:string,partial_predicate:string,row_count:int,index_row_count:int|null,stat_rows:list<array{idx:string|null,stat:string}>,result_rows:list<array<int,mixed>>,expected_error:string|null,uses_partial_index:bool,integrity:string,batch:int}>
     */
    public static function index6EarlyPartialIndexCases(int $cases = 1200): array
    {
        if ($cases < 1) {
            throw new \InvalidArgumentException('SQLite index6 early partial-index corpus requires at least one case');
        }

        $templates = [
            ['index6-1.1', 'initial partial indexes admit only rows satisfying their WHERE clauses', 'CREATE INDEX t1a ON t1(a) WHERE a IS NOT NULL; CREATE INDEX t1b ON t1(b) WHERE b>10', 'rowid', 't1a,t1b', 'a IS NOT NULL / b>10', 20, null, [], [[14, 20], ['ok']], null, false, 'ok'],
            ['index6-1.1.1', 'count star optimization ignores reduced partial-index cardinality', 'SELECT count(*) FROM t1', 'rowid', 't1a,t1b', 'a IS NOT NULL / b>10', 20, null, [], [[20]], null, false, 'ok'],
            ['index6-1.2', 'partial-index predicate rejects unknown columns at parse time', 'CREATE INDEX bad1 ON t1(a,b) WHERE x IS NOT NULL', 'rowid', 'bad1', 'x IS NOT NULL', 20, null, [], [], 'no such column: x', false, 'expected-error'],
            ['index6-1.3', 'partial-index predicate rejects subqueries', 'CREATE INDEX bad1 ON t1(a,b) WHERE EXISTS(SELECT * FROM t1)', 'rowid', 'bad1', 'EXISTS(SELECT * FROM t1)', 20, null, [], [], 'subqueries prohibited in partial index WHERE clauses', false, 'expected-error'],
            ['index6-1.4', 'partial-index predicate rejects bound parameters', 'CREATE INDEX bad1 ON t1(a,b) WHERE a!=?1', 'rowid', 'bad1', 'a!=?1', 20, null, [], [], 'parameters prohibited in partial index WHERE clauses', false, 'expected-error'],
            ['index6-1.5', 'partial-index predicate rejects non-deterministic functions', 'CREATE INDEX bad1 ON t1(a,b) WHERE a!=random()', 'rowid', 'bad1', 'a!=random()', 20, null, [], [], 'non-deterministic functions prohibited in partial index WHERE clauses', false, 'expected-error'],
            ['index6-1.6/1.7', 'NOT LIKE partial-index predicate is accepted then dropped cleanly', "CREATE INDEX bad1 ON t1(a,b) WHERE a NOT LIKE 'abc%'; DROP INDEX IF EXISTS bad1", 'rowid', 'bad1', "a NOT LIKE 'abc%'", 20, null, [], [], null, false, 'ok'],
            ['index6-1.10', 'ANALYZE records reduced row counts for initial partial indexes', 'ANALYZE; SELECT idx, stat FROM sqlite_stat1 ORDER BY idx', 'rowid', 't1a,t1b', 'a IS NOT NULL / b>10', 20, null, [['idx' => null, 'stat' => '20'], ['idx' => 't1a', 'stat' => '14 1'], ['idx' => 't1b', 'stat' => '10 1']], [['ok']], null, false, 'ok'],
            ['index6-1.11a', 'UPDATE into every a value expands t1a stat while t1b remains filtered', 'UPDATE t1 SET a=b; ANALYZE', 'rowid', 't1a,t1b', 'a IS NOT NULL / b>10', 20, null, [['idx' => null, 'stat' => '20'], ['idx' => 't1a', 'stat' => '20 1'], ['idx' => 't1b', 'stat' => '10 1']], [['ok']], null, false, 'ok'],
            ['index6-1.11b', 'UPDATE nulls and b offset move rows between partial indexes', 'UPDATE t1 SET a=NULL WHERE b%3!=0; UPDATE t1 SET b=b+100; ANALYZE', 'rowid', 't1a,t1b', 'a IS NOT NULL / b>10', 20, null, [['idx' => null, 'stat' => '20'], ['idx' => 't1a', 'stat' => '6 1'], ['idx' => 't1b', 'stat' => '20 1']], [['ok']], null, false, 'ok'],
            ['index6-1.12', 'restoring source values restores reduced partial-index stat rows', 'UPDATE t1 SET a=CASE WHEN b%3!=0 THEN b END; UPDATE t1 SET b=b-100; ANALYZE', 'rowid', 't1a,t1b', 'a IS NOT NULL / b>10', 20, null, [['idx' => null, 'stat' => '20'], ['idx' => 't1a', 'stat' => '13 1'], ['idx' => 't1b', 'stat' => '10 1']], [['ok']], null, false, 'ok'],
            ['index6-1.13', 'DELETE shrinks both table and partial-index statistics', 'DELETE FROM t1 WHERE b BETWEEN 8 AND 12; ANALYZE', 'rowid', 't1a,t1b', 'a IS NOT NULL / b>10', 15, null, [['idx' => null, 'stat' => '15'], ['idx' => 't1a', 'stat' => '10 1'], ['idx' => 't1b', 'stat' => '8 1']], [['ok']], null, false, 'ok'],
            ['index6-1.14', 'REINDEX preserves partial-index statistics and integrity', 'REINDEX; ANALYZE', 'rowid', 't1a,t1b', 'a IS NOT NULL / b>10', 15, null, [['idx' => null, 'stat' => '15'], ['idx' => 't1a', 'stat' => '10 1'], ['idx' => 't1b', 'stat' => '8 1']], [['ok']], null, false, 'ok'],
            ['index6-1.15', 'ordinary index stats coexist with reduced partial-index stats', 'CREATE INDEX t1c ON t1(c); ANALYZE', 'rowid', 't1a,t1b,t1c', 'a IS NOT NULL / b>10', 15, null, [['idx' => 't1a', 'stat' => '10 1'], ['idx' => 't1b', 'stat' => '8 1'], ['idx' => 't1c', 'stat' => '15 1']], [['ok']], null, false, 'ok'],
            ['index6-2.1/2.4', 'partial IS NOT NULL index drives matching probes but not NULL scans', 'CREATE INDEX t2a1 ON t2(a) WHERE a IS NOT NULL', 'rowid', 't2a1', 'a IS NOT NULL', 999, 500, [], [[500], ['SEARCH t2 USING INDEX t2a1'], ['SCAN t2']], null, true, 'ok'],
            ['index6-2.101/2.104', 'OR-connected partial index accepts equality probes on either qualifying range', 'CREATE INDEX t2a2 ON t2(a) WHERE a<100 OR a>200', 'rowid', 't2a2', 'a<100 OR a>200', 999, null, [], [[10015], [10015], [10515]], null, true, 'ok'],
            ['index6-3.1/3.5', 'partial UNIQUE index rejects duplicate qualifying keys but admits sentinel duplicates', 'CREATE UNIQUE INDEX t3a ON t3(a) WHERE a<>999', 'rowid', 't3a', 'a<>999', 201, 39, [], [[162], ['ok']], 'UNIQUE constraint failed: t3.a', true, 'expected-error-and-ok'],
            ['index6-4.0', 'VACUUM preserves partial-index integrity after unique sentinel writes', 'VACUUM; PRAGMA integrity_check', 'rowid', 't3a', 'a<>999', 201, 39, [], [['ok']], null, true, 'ok'],
            ['index6-5.0', 'database-qualified column names are ignored in partial-index predicates', 'CREATE INDEX t3b ON t3(b) WHERE xyzzy.t3.b BETWEEN 5 AND 10', 'rowid', 't3b', 't3.b BETWEEN 5 AND 10', 201, 6, [['idx' => 't3b', 'stat' => '6 1']], [[6], [6]], null, true, 'ok'],
            ['index6-6.0/6.2', 'UPDATE OR REPLACE remains stable with unrelated partial index present', 'UPDATE OR REPLACE t6 SET b=789', 'rowid', 't6b', 'b=1', 1, 0, [], [[123, 456], [123, 789], ['ok']], null, false, 'ok'],
        ];

        $out = [];
        for ($case = 1; $case <= $cases; $case++) {
            [$section, $scenario, $operation, $tableShape, $partialIndex, $predicate, $rowCount, $indexRowCount, $statRows, $resultRows, $expectedError, $usesPartialIndex, $integrity] = $templates[($case - 1) % count($templates)];
            $out[] = [
                'source' => 'index6.test sections index6-1.1 through index6-6.2',
                'case' => $case,
                'upstream_section' => $section,
                'scenario' => $scenario . ' dynamic batch ' . (intdiv($case - 1, count($templates)) + 1),
                'operation' => $operation,
                'table_shape' => $tableShape,
                'partial_index' => $partialIndex,
                'partial_predicate' => $predicate,
                'row_count' => $rowCount,
                'index_row_count' => $indexRowCount,
                'stat_rows' => $statRows,
                'result_rows' => $resultRows,
                'expected_error' => $expectedError,
                'uses_partial_index' => $usesPartialIndex,
                'integrity' => $integrity,
                'batch' => intdiv($case - 1, count($templates)) + 1,
            ];
        }

        return $out;
    }

    /**
     * @return list<array{upstream:string,scenario:string,uses_partial_index:bool,result_rows:list<array<int,mixed>>,detail:string}>
     */
    public static function index6PartialJoinAndUpdateCases(): array
    {
        return [
            [
                'upstream' => 'index6-7.0',
                'scenario' => 'left join does not use partial-index ON term as a filter for the left row',
                'uses_partial_index' => false,
                'result_rows' => [[1, null]],
                'detail' => 'SCAN t7a LEFT-JOIN t7b',
            ],
            [
                'upstream' => 'index6-7.3',
                'scenario' => 'inner join may use the partial-index qualifying ON term',
                'uses_partial_index' => true,
                'result_rows' => [[99, 2]],
                'detail' => 'SEARCH t7a USING COVERING INDEX t7ax',
            ],
            [
                'upstream' => 'index6-8.2',
                'scenario' => 'left join probes partial index on right table while preserving unmatched host rows',
                'uses_partial_index' => true,
                'result_rows' => [[1, 'one', 'value', 1], [2, 'two', null, null], [3, 'three', 'value', 3]],
                'detail' => 'SEARCH t8b USING INDEX i8c (y=?) LEFT-JOIN',
            ],
            [
                'upstream' => 'index6-9.1',
                'scenario' => 'rowid table update through partial IN index updates only matching rows',
                'uses_partial_index' => true,
                'result_rows' => [[null, 7, 3], [1, 1, 9], [10, 35, 35], [11, 15, 82], [20, 5, 5]],
                'detail' => 'UPDATE t9 SET b=c WHERE a in (10,12,20)',
            ],
            [
                'upstream' => 'index6-9.2',
                'scenario' => 'without-rowid table update through partial IN index keeps primary-key order',
                'uses_partial_index' => true,
                'result_rows' => [[1, 1, 9], [10, 35, 35], [11, 15, 82], [20, 5, 5]],
                'detail' => 'UPDATE t9 WITHOUT ROWID SET b=c WHERE a in (10,12,20)',
            ],
            [
                'upstream' => 'index6-10.1',
                'scenario' => 'AND-connected partial predicate in source order satisfies ORDER BY d',
                'uses_partial_index' => true,
                'result_rows' => [[5], [9]],
                'detail' => 'USING INDEX t10x',
            ],
            [
                'upstream' => 'index6-10.2',
                'scenario' => 'commuted AND-connected partial predicate satisfies reverse ORDER BY d',
                'uses_partial_index' => true,
                'result_rows' => [[9], [5]],
                'detail' => 'USING INDEX t10x',
            ],
            [
                'upstream' => 'index6-10.3',
                'scenario' => 'missing one AND term prevents partial-index proof even if rows match',
                'uses_partial_index' => false,
                'result_rows' => [[9], [5]],
                'detail' => 'SCAN t10',
            ],
            [
                'upstream' => 'index6-11.1',
                'scenario' => 'partial index can drive a full scan when predicate exactly matches',
                'uses_partial_index' => true,
                'result_rows' => [],
                'detail' => 'USING INDEX t11x',
            ],
            [
                'upstream' => 'index6-11.2',
                'scenario' => 'partial index remains usable with an added residual predicate',
                'uses_partial_index' => true,
                'result_rows' => [],
                'detail' => 'USING INDEX t11x',
            ],
        ];
    }

    /**
     * @return list<array{source:string,case:int,upstream_section:string,scenario:string,statement:string,partial_index:string,table_shape:string,predicate:string,query:string,result_rows:list<array<int,mixed>>,expected_error:string|null,uses_partial_index:bool,collation:string|null,integrity:string,batch:int}>
     */
    public static function index6LatePartialIndexTheoremCases(int $cases = 1000): array
    {
        if ($cases < 1) {
            throw new \InvalidArgumentException('SQLite index6 late partial-index theorem corpus requires at least one case');
        }

        $templates = [
            ['index6-12.1/12.2', 'NOT IN and IN subqueries keep correct truth tables after adding a filtered partial index', 'CREATE INDEX t1a ON t1(a) WHERE b=1; SELECT x FROM t2 WHERE x IN (SELECT a FROM t1)', 't1a', 'rowid', 'b=1', 'x IN (SELECT a FROM t1)', [[1], [2]], null, false, null, 'ok'],
            ['index6-13.1', 'partial-index theorem does not drop NULL row for OR truth expression', 'CREATE INDEX index_0 ON t0(c0) WHERE c0 NOT NULL; SELECT * FROM t0 WHERE c0 OR 1', 'index_0', 'rowid', 'c0 NOT NULL', 'c0 OR 1', [[null]], null, false, null, 'ok'],
            ['index6-14.1', 'IS NOT comparison preserves NULL row with multi-column filtered index', 'CREATE INDEX i0 ON t0(c0, c1) WHERE c0 NOT NULL; SELECT * FROM t0 WHERE t0.c0 IS NOT 1', 'i0', 'rowid', 'c0 NOT NULL', 't0.c0 IS NOT 1', [[null, 'row']], null, false, null, 'ok'],
            ['index6-14.2', 'CASE expression truthiness preserves NULL row with partial index present', 'SELECT * FROM t0 WHERE CASE c0 WHEN 0 THEN 0 ELSE 1 END', 'i0', 'rowid', 'c0 NOT NULL', 'CASE c0 WHEN 0 THEN 0 ELSE 1 END', [[null, 'row']], null, false, null, 'ok'],
            ['index6-15.1', 'IS FALSE wrapped in IS FALSE preserves NULL row despite expression partial index', 'CREATE INDEX i0 ON t0(1) WHERE c0 NOT NULL; SELECT 1 FROM t0 WHERE (t0.c0 IS FALSE) IS FALSE', 'i0', 'rowid', 'c0 NOT NULL', '(t0.c0 IS FALSE) IS FALSE', [[1]], null, false, null, 'ok'],
            ['index6-15.2', 'BETWEEN lower-bound expression over IS FALSE preserves NULL row', 'SELECT 1 FROM t0 WHERE (t0.c0 IS FALSE) BETWEEN FALSE AND TRUE', 'i0', 'rowid', 'c0 NOT NULL', '(t0.c0 IS FALSE) BETWEEN FALSE AND TRUE', [[1]], null, false, null, 'ok'],
            ['index6-15.3', 'BETWEEN upper-bound expression over IS FALSE preserves NULL row', 'SELECT 1 FROM t0 WHERE TRUE BETWEEN (t0.c0 IS FALSE) AND TRUE', 'i0', 'rowid', 'c0 NOT NULL', 'TRUE BETWEEN (t0.c0 IS FALSE) AND TRUE', [[1]], null, false, null, 'ok'],
            ['index6-15.4', 'BETWEEN right boundary expression over IS FALSE preserves NULL row', 'SELECT 1 FROM t0 WHERE FALSE BETWEEN FALSE AND (t0.c0 IS FALSE)', 'i0', 'rowid', 'c0 NOT NULL', 'FALSE BETWEEN FALSE AND (t0.c0 IS FALSE)', [[1]], null, false, null, 'ok'],
            ['index6-15.5', 'IN expression over IS FALSE preserves NULL row', 'SELECT 1 FROM t0 WHERE (c0 IS FALSE) IN (FALSE)', 'i0', 'rowid', 'c0 NOT NULL', '(c0 IS FALSE) IN (FALSE)', [[1]], null, false, null, 'ok'],
            ['index6-16.1/16.3', 'NOCASE collation direction is not commuted when proving partial-index usability', 'CREATE INDEX i0 ON t0(0) WHERE c0 >= c1; SELECT 3 FROM t0 WHERE c1 <= c0', 'i0', 'rowid', 'c0 >= c1', 'c1 <= c0', [[3]], null, false, 'NOCASE', 'ok'],
            ['index6-17.1/17.3', 'GLOB self-comparison partial index coexists with duplicate constant unique indexes', 'CREATE INDEX i0 ON t0(0) WHERE c0 GLOB c0; CREATE UNIQUE INDEX i1 ON t0(0); SELECT COUNT(*) FROM t0 WHERE t0.c0 GLOB t0.c0', 'i0', 'rowid', 'c0 GLOB c0', 't0.c0 GLOB t0.c0', [[1]], null, true, null, 'ok'],
            ['index6-18.1', 'partial UNIQUE index with a>NULL does not suppress IS NOT NULL table scan rows', 'CREATE UNIQUE INDEX t1b ON t1(b) WHERE a>NULL; SELECT * FROM t1 WHERE a IS NOT NULL', 't1b', 'rowid', 'a>NULL', 'a IS NOT NULL', [[10, 10]], null, false, null, 'ok'],
            ['index6-19.2', 'partial index on right-join left table is not used for a full scan that would invent rows', 'SELECT * FROM t2 RIGHT JOIN t3 ON d<>0 LEFT JOIN t1 ON c=3 WHERE t1.a<>0', 'i0', 'rowid', 'c=3', 'RIGHT JOIN no-match loop with t1.a<>0', [], null, false, null, 'ok'],
        ];

        $out = [];
        for ($case = 1; $case <= $cases; $case++) {
            [$section, $scenario, $statement, $partialIndex, $tableShape, $predicate, $query, $rows, $error, $usesPartialIndex, $collation, $integrity] = $templates[($case - 1) % count($templates)];
            $out[] = [
                'source' => 'index6.test sections index6-12.1 through index6-19.2',
                'case' => $case,
                'upstream_section' => $section,
                'scenario' => $scenario,
                'statement' => $statement,
                'partial_index' => $partialIndex,
                'table_shape' => $tableShape,
                'predicate' => $predicate,
                'query' => $query,
                'result_rows' => $rows,
                'expected_error' => $error,
                'uses_partial_index' => $usesPartialIndex,
                'collation' => $collation,
                'integrity' => $integrity,
                'batch' => intdiv($case - 1, count($templates)) + 1,
            ];
        }

        return $out;
    }

    /**
     * @return list<array{source:string,case:int,upstream_section:string,column_count:int,row_count:int,column_name:string,column_position:int,first_row_value:int,last_row_value:int,selected_column:string,selected_value:int,index_name:string,index_columns:list<string>,order_by:list<string>,limit:int,result_rows:list<list<int>>,detail:string,integrity:string}>
     */
    public static function index2WideColumnIndexCases(int $cases = 1000): array
    {
        if ($cases < 1) {
            throw new \InvalidArgumentException('SQLite index2 dynamic corpus requires at least one case');
        }

        $out = [];
        $indexColumns = [];
        for ($column = 1; $column <= 1000; $column++) {
            $indexColumns[] = 'c' . $column;
        }

        for ($case = 1; $case <= $cases; $case++) {
            $columnPosition = (($case - 1) % 1000) + 1;
            $rowOffset = intdiv($case - 1, 1000);
            $selectedColumn = 'c' . $columnPosition;
            $firstRowValue = $columnPosition;
            $lastRowValue = 1000000 + $columnPosition;
            $resultRows = [];
            for ($row = 0; $row < 5; $row++) {
                $base = $row === 0 ? 0 : $row * 10000;
                $resultRows[] = [$base + $columnPosition];
            }

            $out[] = [
                'source' => 'index2.test sections index2-1.1 through index2-2.2',
                'case' => $case,
                'upstream_section' => $columnPosition <= 5 ? 'index2-2.2' : 'index2-2.1',
                'column_count' => 1000,
                'row_count' => 101,
                'column_name' => $selectedColumn,
                'column_position' => $columnPosition,
                'first_row_value' => $firstRowValue,
                'last_row_value' => $lastRowValue,
                'selected_column' => $selectedColumn,
                'selected_value' => ($rowOffset * 10000) + $columnPosition,
                'index_name' => 't1i1',
                'index_columns' => $indexColumns,
                'order_by' => ['c1', 'c2', 'c3', 'c4', 'c5', 'c6'],
                'limit' => 5,
                'result_rows' => $resultRows,
                'detail' => 'SCAN t1 USING COVERING INDEX t1i1',
                'integrity' => 'ok',
            ];
        }

        return $out;
    }

    /**
     * @return list<array{upstream:string,scenario:string,row_count:int,blob_bytes:int,index_name:string,cache_size:int|null,expected_integrity:string,requires_external_sort:bool,unique_error:string|null,duplicate_value:int|null}>
     */
    public static function index4LargeBuildCases(): array
    {
        $cases = [
            [
                'upstream' => 'index4-1.2',
                'scenario' => 'large randomblob index build after power-of-two inserts',
                'row_count' => 65536,
                'blob_bytes' => 102,
                'index_name' => 'i1',
                'cache_size' => null,
                'expected_integrity' => 'ok',
                'requires_external_sort' => true,
                'unique_error' => null,
                'duplicate_value' => null,
            ],
            [
                'upstream' => 'index4-1.4',
                'scenario' => 'large randomblob index build with cache_size limited to ten pages',
                'row_count' => 65536,
                'blob_bytes' => 102,
                'index_name' => 'i2',
                'cache_size' => 10,
                'expected_integrity' => 'ok',
                'requires_external_sort' => true,
                'unique_error' => null,
                'duplicate_value' => null,
            ],
            [
                'upstream' => 'index4-1.6',
                'scenario' => 'mixed text null and growing blob keys build a stable index',
                'row_count' => 256,
                'blob_bytes' => 5202,
                'index_name' => 'i1',
                'cache_size' => null,
                'expected_integrity' => 'ok',
                'requires_external_sort' => true,
                'unique_error' => null,
                'duplicate_value' => null,
            ],
            [
                'upstream' => 'index4-1.7',
                'scenario' => 'single-row table index build is valid',
                'row_count' => 1,
                'blob_bytes' => 1,
                'index_name' => 'i1',
                'cache_size' => null,
                'expected_integrity' => 'ok',
                'requires_external_sort' => false,
                'unique_error' => null,
                'duplicate_value' => null,
            ],
            [
                'upstream' => 'index4-1.8',
                'scenario' => 'empty table index build is valid',
                'row_count' => 0,
                'blob_bytes' => 0,
                'index_name' => 'i1',
                'cache_size' => null,
                'expected_integrity' => 'ok',
                'requires_external_sort' => false,
                'unique_error' => null,
                'duplicate_value' => null,
            ],
            [
                'upstream' => 'index4-2.2',
                'scenario' => 'unique index build rolls back cleanly after duplicate integer key',
                'row_count' => 5,
                'blob_bytes' => 0,
                'index_name' => 'i3',
                'cache_size' => null,
                'expected_integrity' => 'ok',
                'requires_external_sort' => false,
                'unique_error' => 'UNIQUE constraint failed: t2.x',
                'duplicate_value' => 35,
            ],
        ];

        foreach ([2, 4, 8, 16, 32, 64, 128, 256, 512, 1024, 2048, 4096, 8192, 16384, 32768, 65536] as $rowCount) {
            $cases[] = [
                'upstream' => 'index4-1.1.dynamic-' . $rowCount,
                'scenario' => 'power-of-two insert batch stays index-build ready',
                'row_count' => $rowCount,
                'blob_bytes' => 102,
                'index_name' => 'i1',
                'cache_size' => null,
                'expected_integrity' => 'ok',
                'requires_external_sort' => $rowCount > 512,
                'unique_error' => null,
                'duplicate_value' => null,
            ];
        }

        return $cases;
    }

    /**
     * @return list<array{upstream:string,index_name:string,where_column:string,where_value:int,order_by:list<string>,limit:int,result_rows:list<list<int>>,detail:string,uses_index:bool}>
     */
    public static function index8OrderByLimitPlannerCases(): array
    {
        $rows = [];
        for ($x = 0; $x <= 100; $x++) {
            $rows[] = [
                'a' => intdiv($x, 10),
                'b' => $x % 10,
                'c' => $x % 19,
                'd' => $x,
            ];
        }

        $matching = array_values(array_filter($rows, static fn (array $row): bool => $row['c'] === 4));
        usort($matching, static fn (array $left, array $right): int => [$left['a'], $left['b']] <=> [$right['a'], $right['b']]);
        $resultRows = array_map(static fn (array $row): array => [$row['a'], $row['b'], $row['c'], $row['d']], array_slice($matching, 0, 2));

        return [
            [
                'upstream' => 'index8-1.0',
                'index_name' => 't1abc',
                'where_column' => 'c',
                'where_value' => 4,
                'order_by' => ['a', 'b'],
                'limit' => 2,
                'result_rows' => $resultRows,
                'detail' => 'SCAN t1 USING INDEX t1abc',
                'uses_index' => true,
            ],
            [
                'upstream' => 'index8-1.1',
                'index_name' => 't1abd',
                'where_column' => 'c',
                'where_value' => 4,
                'order_by' => ['a', 'b'],
                'limit' => 2,
                'result_rows' => $resultRows,
                'detail' => 'SCAN t1',
                'uses_index' => false,
            ],
        ];
    }

    /**
     * @return list<array{source:string,case:int,upstream:string,where_column:string,where_value:int,index_name:string,index_columns:list<string>,order_by:list<string>,limit:int,result_rows:list<array{a:int,b:int,c:int,d:int}>,first_d:int|null,last_d:int|null,matching_count:int,detail:string,uses_index:bool,requires_table_lookup:bool}>
     */
    public static function index8DynamicOrderByLimitCases(int $cases = 1000): array
    {
        $rows = [];
        for ($x = 0; $x <= 100; $x++) {
            $rows[] = [
                'a' => intdiv($x, 10),
                'b' => $x % 10,
                'c' => $x % 19,
                'd' => $x,
            ];
        }

        $out = [];
        for ($case = 1; $case <= $cases; $case++) {
            $whereValue = ($case - 1) % 19;
            $limit = 1 + intdiv($case - 1, 19) % 8;
            $covered = $case % 2 === 1;
            $matching = array_values(array_filter($rows, static fn (array $row): bool => $row['c'] === $whereValue));
            usort($matching, static fn (array $left, array $right): int => [$left['a'], $left['b']] <=> [$right['a'], $right['b']]);
            $resultRows = array_slice($matching, 0, $limit);
            $dValues = array_column($resultRows, 'd');

            $out[] = [
                'source' => 'index8.test sections 1.0, 1.0eqp, 1.1, and 1.1eqp',
                'case' => $case,
                'upstream' => 'index8-1.dynamic-' . $case,
                'where_column' => 'c',
                'where_value' => $whereValue,
                'index_name' => $covered ? 't1abc' : 't1abd',
                'index_columns' => $covered ? ['a', 'b', 'c'] : ['a', 'b', 'd'],
                'order_by' => ['a', 'b'],
                'limit' => $limit,
                'result_rows' => $resultRows,
                'first_d' => $dValues[0] ?? null,
                'last_d' => $dValues === [] ? null : $dValues[count($dValues) - 1],
                'matching_count' => count($matching),
                'detail' => $covered ? 'SCAN t1 USING INDEX t1abc' : 'SCAN t1',
                'uses_index' => $covered,
                'requires_table_lookup' => !$covered,
            ];
        }

        return $out;
    }

    /**
     * @return list<array{upstream:string,join:string,probe_values:list<int>,result_rows:list<array{y:int,c:int|null}>,page_size:int,primary_key:string,overflow_key:int,overflow_blob:int}>
     */
    public static function btree01OverflowJoinCases(): array
    {
        $resultRows = [
            ['y' => 198, 'c' => 99],
            ['y' => 187, 'c' => null],
            ['y' => 100, 'c' => 50],
        ];

        return [
            [
                'upstream' => 'btree01-2.1',
                'join' => 'LEFT JOIN',
                'probe_values' => [198, 187, 100],
                'result_rows' => $resultRows,
                'page_size' => 1024,
                'primary_key' => 'WITHOUT ROWID PRIMARY KEY(a)',
                'overflow_key' => 198,
                'overflow_blob' => 1000,
            ],
            [
                'upstream' => 'btree01-2.2',
                'join' => 'RIGHT JOIN',
                'probe_values' => [198, 187, 100],
                'result_rows' => $resultRows,
                'page_size' => 1024,
                'primary_key' => 'WITHOUT ROWID PRIMARY KEY(a)',
                'overflow_key' => 198,
                'overflow_blob' => 1000,
            ],
        ];
    }

    /**
     * @return list<array{upstream:string,scenario:string,table_columns:int,row_count:int,indexed_columns:int,ordered_columns:int,limit:int,result_column:string,result_values:list<int>,sum_column:string,rounded_sum:float}>
     */
    public static function index2LargeColumnIndexCases(): array
    {
        $baseRow = [];
        for ($column = 1; $column <= 1000; $column++) {
            $baseRow['c' . $column] = $column;
        }

        $rows = [$baseRow];
        for ($j = 1; $j <= 100; $j++) {
            $row = [];
            for ($column = 1; $column <= 1000; $column++) {
                $row['c' . $column] = ($j * 10000) + $column;
            }
            $rows[] = $row;
        }

        $firstFive = array_slice($rows, 0, 5);

        return [
            [
                'upstream' => 'index2-1.1',
                'scenario' => 'creates a table with one thousand columns',
                'table_columns' => 1000,
                'row_count' => 0,
                'indexed_columns' => 0,
                'ordered_columns' => 0,
                'limit' => 0,
                'result_column' => '',
                'result_values' => [],
                'sum_column' => '',
                'rounded_sum' => 0.0,
            ],
            [
                'upstream' => 'index2-1.3',
                'scenario' => 'projects a high-numbered column from the wide row',
                'table_columns' => 1000,
                'row_count' => 1,
                'indexed_columns' => 0,
                'ordered_columns' => 0,
                'limit' => 1,
                'result_column' => 'c123',
                'result_values' => [$baseRow['c123']],
                'sum_column' => '',
                'rounded_sum' => 0.0,
            ],
            [
                'upstream' => 'index2-1.4',
                'scenario' => 'bulk inserts one hundred additional wide rows in a transaction',
                'table_columns' => 1000,
                'row_count' => count($rows),
                'indexed_columns' => 0,
                'ordered_columns' => 0,
                'limit' => 0,
                'result_column' => '',
                'result_values' => [],
                'sum_column' => '',
                'rounded_sum' => 0.0,
            ],
            [
                'upstream' => 'index2-1.5',
                'scenario' => 'sums the final column across all wide rows after bulk insert',
                'table_columns' => 1000,
                'row_count' => count($rows),
                'indexed_columns' => 0,
                'ordered_columns' => 0,
                'limit' => 0,
                'result_column' => '',
                'result_values' => [],
                'sum_column' => 'c1000',
                'rounded_sum' => round(array_sum(array_column($rows, 'c1000'))),
            ],
            [
                'upstream' => 'index2-2.1',
                'scenario' => 'creates an index spanning all one thousand columns',
                'table_columns' => 1000,
                'row_count' => count($rows),
                'indexed_columns' => 1000,
                'ordered_columns' => 0,
                'limit' => 0,
                'result_column' => '',
                'result_values' => [],
                'sum_column' => '',
                'rounded_sum' => 0.0,
            ],
            [
                'upstream' => 'index2-2.2',
                'scenario' => 'uses the wide index ordering prefix to return c9 from the first five rows',
                'table_columns' => 1000,
                'row_count' => count($rows),
                'indexed_columns' => 1000,
                'ordered_columns' => 6,
                'limit' => 5,
                'result_column' => 'c9',
                'result_values' => array_map(static fn (array $row): int => $row['c9'], $firstFive),
                'sum_column' => '',
                'rounded_sum' => 0.0,
            ],
        ];
    }

    /**
     * @return list<array{source:string,case:int,upstream:string,table_columns:int,row_count:int,indexed_columns:int,ordered_columns:int,limit:int,result_column:string,result_values:list<int>,sum_column:string,rounded_sum:float,uses_wide_index:bool,detail:string}>
     */
    public static function index2DynamicWideColumnOrderCases(int $cases = 1000): array
    {
        if ($cases < 1) {
            throw new \InvalidArgumentException('SQLite index2 dynamic corpus requires at least one case');
        }

        $out = [];
        $rowCount = 101;
        $tableColumns = 1000;
        $roundedSum = 0.0;
        for ($row = 0; $row < $rowCount; $row++) {
            $roundedSum += $row === 0 ? 1000 : ($row * 10000) + 1000;
        }

        for ($case = 1; $case <= $cases; $case++) {
            $resultColumnNumber = (($case - 1) % $tableColumns) + 1;
            $orderedColumns = 1 + (($case - 1) % 12);
            $limit = 1 + (intdiv($case - 1, 12) % 8);
            $resultValues = [];
            for ($row = 0; $row < min($limit, $rowCount); $row++) {
                $resultValues[] = $row === 0 ? $resultColumnNumber : ($row * 10000) + $resultColumnNumber;
            }

            $out[] = [
                'source' => 'index2.test sections index2-1.1 through index2-2.2',
                'case' => $case,
                'upstream' => 'index2-2.2.dynamic-' . $case,
                'table_columns' => $tableColumns,
                'row_count' => $rowCount,
                'indexed_columns' => $tableColumns,
                'ordered_columns' => $orderedColumns,
                'limit' => $limit,
                'result_column' => 'c' . $resultColumnNumber,
                'result_values' => $resultValues,
                'sum_column' => 'c1000',
                'rounded_sum' => $roundedSum,
                'uses_wide_index' => true,
                'detail' => 'SELECT ' . 'c' . $resultColumnNumber . ' FROM t1 ORDER BY c1..c' . $orderedColumns . ' LIMIT ' . $limit . ' using t1i1',
            ];
        }

        return $out;
    }

    /**
     * @return list<array{source:string,case:int,upstream:string,table_columns:int,row_count:int,indexed_columns:int,projection_column:string,projection_rowid:int,projection_value:int,sum_column:string,rounded_sum:float,min_value:int,max_value:int,ordered_prefix_columns:int,limit:int,ordered_rowids:list<int>,ordered_values:list<int>,detail:string,integrity:string}>
     */
    public static function index2DynamicWideAggregateProjectionCases(int $cases = 1000): array
    {
        if ($cases < 1) {
            throw new \InvalidArgumentException('SQLite index2 aggregate projection corpus requires at least one case');
        }

        $tableColumns = 1000;
        $rowCount = 101;
        $sumC1000 = 1000.0;
        for ($rowid = 2; $rowid <= $rowCount; $rowid++) {
            $sumC1000 += (($rowid - 1) * 10000) + 1000;
        }

        $out = [];
        for ($case = 1; $case <= $cases; $case++) {
            $columnNumber = (($case - 1) % $tableColumns) + 1;
            $rowid = 1 + (intdiv($case - 1, 10) % $rowCount);
            $prefixColumns = 1 + (($case - 1) % 10);
            $limit = 1 + (($case - 1) % 5);
            $projectionValue = $rowid === 1 ? $columnNumber : (($rowid - 1) * 10000) + $columnNumber;
            $orderedRowids = range(1, $limit);
            $orderedValues = array_map(
                static fn (int $orderedRowid): int => $orderedRowid === 1 ? $columnNumber : (($orderedRowid - 1) * 10000) + $columnNumber,
                $orderedRowids,
            );

            $out[] = [
                'source' => 'index2.test sections index2-1.3, index2-1.5, index2-2.1, and index2-2.2',
                'case' => $case,
                'upstream' => 'index2-1.3/1.5/2.2.dynamic-aggregate-' . $case,
                'table_columns' => $tableColumns,
                'row_count' => $rowCount,
                'indexed_columns' => $tableColumns,
                'projection_column' => 'c' . $columnNumber,
                'projection_rowid' => $rowid,
                'projection_value' => $projectionValue,
                'sum_column' => 'c1000',
                'rounded_sum' => $sumC1000,
                'min_value' => $columnNumber,
                'max_value' => 1000000 + $columnNumber,
                'ordered_prefix_columns' => $prefixColumns,
                'limit' => $limit,
                'ordered_rowids' => $orderedRowids,
                'ordered_values' => $orderedValues,
                'detail' => 'project c' . $columnNumber . ', aggregate c1000, and scan t1i1 ORDER BY c1..c' . $prefixColumns . ' LIMIT ' . $limit,
                'integrity' => 'ok',
            ];
        }

        return $out;
    }

    /**
     * @return list<array{upstream:string,scenario:string,result_rows:list<array<int,mixed>>,uses_partial_index:bool,integrity:string,detail:string}>
     */
    public static function index6PartialIndexRegressionCases(): array
    {
        return [
            [
                'upstream' => 'index6-12.1',
                'scenario' => 'NOT IN result remains empty after adding a partial index on the subquery source',
                'result_rows' => [],
                'uses_partial_index' => false,
                'integrity' => 'ok',
                'detail' => 'SELECT from t2 WHERE x NOT IN (SELECT a FROM t1)',
            ],
            [
                'upstream' => 'index6-12.2',
                'scenario' => 'IN subquery still returns both matching rows with the partial index present',
                'result_rows' => [[1], [2]],
                'uses_partial_index' => false,
                'integrity' => 'ok',
                'detail' => 'SELECT x FROM t2 WHERE x IN (SELECT a FROM t1) ORDER BY +x',
            ],
            [
                'upstream' => 'index6-13.1',
                'scenario' => 'partial index theorem prover does not discard NULL row when OR TRUE keeps it visible',
                'result_rows' => [[null]],
                'uses_partial_index' => false,
                'integrity' => 'ok',
                'detail' => 'SELECT * FROM t0 WHERE c0 OR 1',
            ],
            [
                'upstream' => 'index6-14.1',
                'scenario' => 'IS NOT comparison preserves NULL row outside a c0-not-null partial index',
                'result_rows' => [[null, 'row']],
                'uses_partial_index' => false,
                'integrity' => 'ok',
                'detail' => 'SELECT * FROM t0 WHERE t0.c0 IS NOT 1',
            ],
            [
                'upstream' => 'index6-14.2',
                'scenario' => 'CASE truthiness preserves NULL row outside a c0-not-null partial index',
                'result_rows' => [[null, 'row']],
                'uses_partial_index' => false,
                'integrity' => 'ok',
                'detail' => 'SELECT * FROM t0 WHERE CASE c0 WHEN 0 THEN 0 ELSE 1 END',
            ],
            [
                'upstream' => 'index6-15.1',
                'scenario' => 'IS FALSE wrapped in IS FALSE does not imply the partial c0-not-null predicate',
                'result_rows' => [[1]],
                'uses_partial_index' => false,
                'integrity' => 'ok',
                'detail' => 'SELECT 1 FROM t0 WHERE (t0.c0 IS FALSE) IS FALSE',
            ],
            [
                'upstream' => 'index6-15.5',
                'scenario' => 'IN over an IS FALSE expression keeps the NULL row visible',
                'result_rows' => [[1]],
                'uses_partial_index' => false,
                'integrity' => 'ok',
                'detail' => 'SELECT 1 FROM t0 WHERE (c0 IS FALSE) IN (FALSE)',
            ],
            [
                'upstream' => 'index6-16.1',
                'scenario' => 'NOCASE collation makes c1 <= c0 true while c0 >= c1 remains false',
                'result_rows' => [[1, 0]],
                'uses_partial_index' => false,
                'integrity' => 'ok',
                'detail' => 'SELECT c1 <= c0, c0 >= c1 FROM t0',
            ],
            [
                'upstream' => 'index6-16.2',
                'scenario' => 'collation-sensitive partial predicate c0 >= c1 excludes the row',
                'result_rows' => [],
                'uses_partial_index' => true,
                'integrity' => 'ok',
                'detail' => 'SELECT 2 FROM t0 WHERE c0 >= c1',
            ],
            [
                'upstream' => 'index6-16.3',
                'scenario' => 'commuted comparison c1 <= c0 returns the row under NOCASE collation',
                'result_rows' => [[3]],
                'uses_partial_index' => false,
                'integrity' => 'ok',
                'detail' => 'SELECT 3 FROM t0 WHERE c1 <= c0',
            ],
            [
                'upstream' => 'index6-17.1',
                'scenario' => 'constant unique index coexists with a partial GLOB index after insert',
                'result_rows' => [['ok']],
                'uses_partial_index' => true,
                'integrity' => 'ok',
                'detail' => 'PRAGMA integrity_check after partial GLOB and UNIQUE indexes',
            ],
            [
                'upstream' => 'index6-17.3',
                'scenario' => 'partial GLOB index predicate finds the replacement row',
                'result_rows' => [[1]],
                'uses_partial_index' => true,
                'integrity' => 'ok',
                'detail' => 'SELECT COUNT(*) FROM t0 WHERE t0.c0 GLOB t0.c0',
            ],
            [
                'upstream' => 'index6-18.1',
                'scenario' => 'partial unique index with a>NULL does not hide IS NOT NULL table rows',
                'result_rows' => [[10, 10]],
                'uses_partial_index' => false,
                'integrity' => 'ok',
                'detail' => 'SELECT * FROM t1 WHERE a IS NOT NULL',
            ],
            [
                'upstream' => 'index6-19.2',
                'scenario' => 'RIGHT JOIN no-match loop does not scan a left-table partial index and emit extras',
                'result_rows' => [],
                'uses_partial_index' => false,
                'integrity' => 'ok',
                'detail' => 'SELECT * FROM t2 RIGHT JOIN t3 ON d<>0 LEFT JOIN t1 ON c=3 WHERE t1.a<>0',
            ],
        ];
    }

    /**
     * @return list<array{source:string,case:int,upstream_section:string,scenario:string,result_rows:list<array<int,mixed>>,uses_partial_index:bool,integrity:string,detail:string,predicate:string,join_kind:string|null,collation:string|null}>
     */
    public static function index6LatePartialIndexRegressionCases(int $cases = 1000): array
    {
        if ($cases < 1) {
            throw new \InvalidArgumentException('SQLite index6 late partial-index corpus requires at least one case');
        }

        $templates = [
            ['index6-15.2', 'BETWEEN FALSE AND TRUE around IS FALSE keeps the NULL row visible', [[1]], false, 'SELECT 1 FROM t0 WHERE (t0.c0 IS FALSE) BETWEEN FALSE AND TRUE', '(c0 IS FALSE) BETWEEN FALSE AND TRUE', null, null],
            ['index6-15.3', 'TRUE BETWEEN an IS FALSE expression and TRUE keeps the NULL row visible', [[1]], false, 'SELECT 1 FROM t0 WHERE TRUE BETWEEN (t0.c0 IS FALSE) AND TRUE', 'TRUE BETWEEN (c0 IS FALSE) AND TRUE', null, null],
            ['index6-15.4', 'FALSE BETWEEN FALSE and an IS FALSE expression keeps the NULL row visible', [[1]], false, 'SELECT 1 FROM t0 WHERE FALSE BETWEEN FALSE AND (t0.c0 IS FALSE)', 'FALSE BETWEEN FALSE AND (c0 IS FALSE)', null, null],
            ['index6-16.2', 'NOCASE partial predicate c0 greater-or-equal c1 excludes the row', [], true, 'SELECT 2 FROM t0 WHERE c0 >= c1', 'c0 >= c1', null, 'NOCASE'],
            ['index6-16.3', 'commuted NOCASE comparison c1 less-or-equal c0 returns the row outside the partial index', [[3]], false, 'SELECT 3 FROM t0 WHERE c1 <= c0', 'c1 <= c0', null, 'NOCASE'],
            ['index6-17.1', 'GLOB partial index and later unique index preserve integrity after insert', [['ok']], true, 'CREATE INDEX i0 ON t0(0) WHERE c0 GLOB c0; CREATE UNIQUE INDEX i1 ON t0(0)', 'c0 GLOB c0', null, null],
            ['index6-17.2', 'second unique index and REPLACE preserve GLOB partial-index integrity', [['ok']], true, 'CREATE UNIQUE INDEX i2 ON t0(0); REPLACE INTO t0 VALUES(0); PRAGMA integrity_check', 'c0 GLOB c0', null, null],
            ['index6-17.3', 'GLOB partial-index predicate still finds one matching row after REPLACE', [[1]], true, 'SELECT COUNT(*) FROM t0 WHERE t0.c0 GLOB t0.c0', 'c0 GLOB c0', null, null],
            ['index6-18.1', 'partial unique index with a greater-than-NULL predicate does not hide IS NOT NULL rows', [[10, 10]], false, 'CREATE UNIQUE INDEX t1b ON t1(b) WHERE a>NULL; SELECT * WHERE a IS NOT NULL', 'a > NULL', null, null],
            ['index6-19.2', 'RIGHT JOIN no-match loop cannot scan a partial index on the left table', [], false, 'SELECT * FROM t2 RIGHT JOIN t3 ON d<>0 LEFT JOIN t1 ON c=3 WHERE t1.a<>0', 'c = 3', 'RIGHT JOIN', null],
        ];

        $out = [];
        for ($case = 1; $case <= $cases; $case++) {
            [$section, $scenario, $rows, $usesIndex, $detail, $predicate, $joinKind, $collation] = $templates[($case - 1) % count($templates)];
            $out[] = [
                'source' => 'index6.test sections index6-15.2 through index6-19.2',
                'case' => $case,
                'upstream_section' => $section,
                'scenario' => $scenario . ' dynamic batch ' . (intdiv($case - 1, count($templates)) + 1),
                'result_rows' => $rows,
                'uses_partial_index' => $usesIndex,
                'integrity' => 'ok',
                'detail' => $detail,
                'predicate' => $predicate,
                'join_kind' => $joinKind,
                'collation' => $collation,
            ];
        }

        return $out;
    }

    /**
     * @return list<array{upstream:string,source:string,value:int,initial_a:int|null,initial_b:int,partial_not_null_member:bool,post_update_a:int,post_update_b:int,or_partial_member:bool,lookup_before:list<int>,lookup_after:list<int>,detail_before:string,detail_after:string,integrity:string}>
     */
    public static function index7WithoutRowidPartialIndexCases(): array
    {
        $cases = [];
        for ($value = 1; $value < 1000; $value++) {
            $initialA = $value % 5 === 0 ? null : $value;
            $partialNotNullMember = $initialA !== null;
            $orPartialMember = $value < 100 || $value > 200;

            $cases[] = [
                'upstream' => 'index7-2.dynamic-' . $value,
                'source' => 'index7.test index7-2.1 through index7-2.104',
                'value' => $value,
                'initial_a' => $initialA,
                'initial_b' => $value,
                'partial_not_null_member' => $partialNotNullMember,
                'post_update_a' => $value,
                'post_update_b' => $value + 10000,
                'or_partial_member' => $orPartialMember,
                'lookup_before' => $partialNotNullMember ? [$value] : [],
                'lookup_after' => [$value + 10000],
                'detail_before' => $partialNotNullMember
                    ? 'SEARCH t2 USING COVERING INDEX t2a1 (a=?)'
                    : 'SCAN t2',
                'detail_after' => $orPartialMember
                    ? 'SEARCH t2 USING COVERING INDEX t2a2 (a=?)'
                    : 'SCAN t2',
                'integrity' => 'ok',
            ];
        }

        return $cases;
    }

    /**
     * @return list<array{upstream:string,source:string,case:int,batch:int,section:string,scenario:string,table:string,index_name:string|null,unique:bool,partial_predicate:string|null,insert_values:list<array<int,mixed>>,result_rows:list<array<int,mixed>>,result_code:int,error:string|null,uses_partial_index:bool,detail:string,integrity:string,vacuum_preserves_integrity:bool,stat1:int|null}>
     */
    public static function index7PostUpdateVacuumPlannerCases(int $cases = 1000): array
    {
        if ($cases < 1) {
            throw new \InvalidArgumentException('SQLite index7 post-update corpus requires at least one case');
        }

        $templates = [
            [
                'section' => 'index7-3.2',
                'scenario' => 'unique partial index rejects duplicate values outside the excluded sentinel',
                'table' => 't3',
                'index' => 't3a',
                'unique' => true,
                'predicate' => 'a<>999',
                'insert' => [[150, 'test1']],
                'rows' => [],
                'code' => 1,
                'error' => 'UNIQUE constraint failed: t3.a',
                'uses' => true,
                'detail' => 'INSERT INTO t3(a,b) VALUES(150, test1) checks UNIQUE partial index t3a',
                'stat1' => null,
            ],
            [
                'section' => 'index7-3.3',
                'scenario' => 'unique partial index allows duplicate excluded sentinel rows',
                'table' => 't3',
                'index' => 't3a',
                'unique' => true,
                'predicate' => 'a<>999',
                'insert' => [[999, 'test1'], [999, 'test2']],
                'rows' => [],
                'code' => 0,
                'error' => null,
                'uses' => false,
                'detail' => 'INSERT two rows with a=999 bypasses partial unique index t3a',
                'stat1' => null,
            ],
            [
                'section' => 'index7-3.4',
                'scenario' => 'sentinel rows remain visible after partial unique index bypass',
                'table' => 't3',
                'index' => 't3a',
                'unique' => true,
                'predicate' => 'a<>999',
                'insert' => [],
                'rows' => [[162]],
                'code' => 0,
                'error' => null,
                'uses' => false,
                'detail' => 'SELECT count(*) FROM t3 WHERE a=999 returns sentinel cardinality',
                'stat1' => null,
            ],
            [
                'section' => 'index7-4.0',
                'scenario' => 'vacuum preserves partial-index integrity after duplicate sentinel bypass',
                'table' => 't3',
                'index' => 't3a',
                'unique' => true,
                'predicate' => 'a<>999',
                'insert' => [],
                'rows' => [['ok']],
                'code' => 0,
                'error' => null,
                'uses' => false,
                'detail' => 'VACUUM followed by PRAGMA integrity_check',
                'stat1' => null,
            ],
            [
                'section' => 'index7-5.0',
                'scenario' => 'database-name qualifier inside a partial-index predicate is ignored',
                'table' => 't3',
                'index' => 't3b',
                'unique' => false,
                'predicate' => 'xyzzy.t3.b BETWEEN 5 AND 10',
                'insert' => [],
                'rows' => [[6], [6]],
                'code' => 0,
                'error' => null,
                'uses' => true,
                'detail' => 'SEARCH t3 USING COVERING INDEX t3b (b>? AND b<?)',
                'stat1' => 6,
            ],
            [
                'section' => 'index7-6.2',
                'scenario' => 'partial index on an unrelated table does not filter a flattened subquery',
                'table' => 't4',
                'index' => 'i4',
                'unique' => false,
                'predicate' => "d='xyz'",
                'insert' => [],
                'rows' => [[1, 'xyz', 'abc', 'not xyz']],
                'code' => 0,
                'error' => null,
                'uses' => false,
                'detail' => 'SELECT from t5 filtered subquery cross t4 keeps d=not xyz row',
                'stat1' => null,
            ],
            [
                'section' => 'index7-6.4',
                'scenario' => 'view predicate can use the matching partial index',
                'table' => 't4',
                'index' => 'i4',
                'unique' => false,
                'predicate' => "d='xyz'",
                'insert' => [['def', 'xyz']],
                'rows' => [['def', 'xyz']],
                'code' => 0,
                'error' => null,
                'uses' => true,
                'detail' => 'SEARCH t4 USING INDEX i4 (c=?)',
                'stat1' => null,
            ],
            [
                'section' => 'index7-6.5',
                'scenario' => 'host-parameter token remains rejected in partial-index DDL',
                'table' => 't5',
                'index' => 't5a',
                'unique' => false,
                'predicate' => 'a=#1',
                'insert' => [],
                'rows' => [],
                'code' => 1,
                'error' => 'near "#1": syntax error',
                'uses' => false,
                'detail' => 'CREATE INDEX t5a ON t5(a) WHERE a=#1',
                'stat1' => null,
            ],
            [
                'section' => 'index7-7.1',
                'scenario' => 'IS TRUE scan is unaffected by an IS NOT TRUE partial index',
                'table' => 't6',
                'index' => 'i6',
                'unique' => false,
                'predicate' => 'y IS NOT TRUE',
                'insert' => [],
                'rows' => [[1, 1]],
                'code' => 0,
                'error' => null,
                'uses' => false,
                'detail' => 'SCAN t6; partial index i6 is for y IS NOT TRUE',
                'stat1' => null,
            ],
            [
                'section' => 'index7-8.1',
                'scenario' => 'incomplete stat1 for a tiny table still permits a non-null partial-index probe',
                'table' => 't1',
                'index' => 't1y',
                'unique' => false,
                'predicate' => 'y IS NOT NULL',
                'insert' => [[1, null], [2, null]],
                'rows' => [[1]],
                'code' => 0,
                'error' => null,
                'uses' => true,
                'detail' => 'SEARCH t1 USING COVERING INDEX t1y (y=?)',
                'stat1' => 0,
            ],
        ];

        $out = [];
        for ($case = 1; $case <= $cases; $case++) {
            $template = $templates[($case - 1) % count($templates)];
            $batch = intdiv($case - 1, count($templates)) + 1;
            $insertValues = array_map(
                static function (array $row) use ($batch): array {
                    return array_map(
                        static fn (mixed $value): mixed => is_int($value) && $value !== 999 ? $value + (($batch - 1) * 1000) : $value,
                        $row,
                    );
                },
                $template['insert'],
            );

            $out[] = [
                'upstream' => $template['section'] . '.dynamic-' . $batch,
                'source' => 'index7.test sections index7-3.1 through index7-8.1',
                'case' => $case,
                'batch' => $batch,
                'section' => $template['section'],
                'scenario' => $template['scenario'],
                'table' => $template['table'],
                'index_name' => $template['index'],
                'unique' => $template['unique'],
                'partial_predicate' => $template['predicate'],
                'insert_values' => $insertValues,
                'result_rows' => $template['rows'],
                'result_code' => $template['code'],
                'error' => $template['error'],
                'uses_partial_index' => $template['uses'],
                'detail' => $template['detail'],
                'integrity' => 'ok',
                'vacuum_preserves_integrity' => $template['section'] === 'index7-4.0',
                'stat1' => $template['stat1'],
            ];
        }

        return $out;
    }

    /**
     * @return list<array{upstream:string,source:string,batch:int,storage:string,index_setup:int,index_predicate:mixed,index_name:string,table:string,affinity:string,predicate:mixed,selected_rows:list<array{a:mixed,b:string,c:string,type:string}>,uses_partial_index:bool,detail:string,integrity:string}>
     */
    public static function indexAPartialAffinityMatrixCases(int $batches = 9): array
    {
        if ($batches < 1) {
            throw new \InvalidArgumentException('SQLite indexA dynamic corpus requires at least one batch');
        }

        $indexPredicates = [
            0 => null,
            1 => 2,
            2 => 2.0,
            3 => '2.0',
            4 => '2',
        ];
        $predicates = [
            1 => 2,
            2 => 2.0,
            3 => '2',
            4 => '2.0',
        ];
        $affinities = [
            'x1' => 'TEXT',
            'x2' => 'NUMERIC',
            'x3' => 'REAL',
        ];
        $storages = [
            'rowid' => 2,
            'without-rowid' => 3,
        ];

        $cases = [];
        for ($batch = 1; $batch <= $batches; $batch++) {
            foreach ($storages as $storage => $upstreamGroup) {
                foreach ($indexPredicates as $indexSetup => $indexPredicate) {
                    foreach ($affinities as $table => $affinity) {
                        foreach ($predicates as $predicateSlot => $predicate) {
                            $selectedRows = self::indexAAffinityRows($affinity, $predicate, $batch);
                            $usesPartialIndex = $indexSetup !== 0
                                && self::sqliteCompareEqual($affinity, $indexPredicate, $predicate)
                                && $selectedRows !== [];

                            $cases[] = [
                                'upstream' => 'indexA-' . $upstreamGroup . '.1.' . $indexSetup . '.' . $predicateSlot . '.batch-' . $batch,
                                'source' => 'indexA.test sections 2.1 and 3.1',
                                'batch' => $batch,
                                'storage' => $storage,
                                'index_setup' => $indexSetup,
                                'index_predicate' => $indexPredicate,
                                'index_name' => $indexSetup === 0 ? '' : 'i' . substr($table, 1),
                                'table' => $table,
                                'affinity' => $affinity,
                                'predicate' => $predicate,
                                'selected_rows' => $selectedRows,
                                'uses_partial_index' => $usesPartialIndex,
                                'detail' => $usesPartialIndex
                                    ? 'SEARCH ' . $table . ' USING COVERING INDEX i' . substr($table, 1) . ' (b=? AND c=?)'
                                    : 'SCAN ' . $table,
                                'integrity' => 'ok',
                            ];
                        }
                    }
                }
            }
        }

        return $cases;
    }

    /**
     * @return list<array{source:string,case:int,upstream:string,scenario:string,index_name:string,uses_index:bool,detail:string,result_rows:list<array<int,mixed>>,integrity:string,error:string|null,collation:string|null,batch:int}>
     */
    public static function indexAJoinPlannerGuardCases(int $cases = 720): array
    {
        if ($cases < 1) {
            throw new \InvalidArgumentException('SQLite indexA join/planner corpus requires at least one case');
        }

        $templates = [
            [
                'upstream' => 'indexA-1.1/1.2',
                'scenario' => 'text partial-index equality uses covering index',
                'index' => 'i1',
                'uses' => true,
                'detail' => 'SEARCH t1 USING COVERING INDEX i1 (b=? AND c=?)',
                'rows' => [['abc', 1, 2]],
                'error' => null,
                'collation' => null,
            ],
            [
                'upstream' => 'indexA-1.3/1.7',
                'scenario' => 'right join numeric partial-index route',
                'index' => 'i2',
                'uses' => true,
                'detail' => 'RIGHT-JOIN t1 USING INDEX i2',
                'rows' => [[null, 'abc', 1, 2], [null, '5', 4, 3]],
                'error' => null,
                'collation' => null,
            ],
            [
                'upstream' => 'indexA-4.1.1/4.1.2',
                'scenario' => 'aggregate reads covering partial index',
                'index' => 't2a_two',
                'uses' => true,
                'detail' => 'SCAN t2 USING COVERING INDEX t2a_two',
                'rows' => [[6, 'two']],
                'error' => null,
                'collation' => null,
            ],
            [
                'upstream' => 'indexA-5.1',
                'scenario' => 'partial-index predicate rejects missing collation',
                'index' => 'ex1',
                'uses' => false,
                'detail' => 'CREATE INDEX ex1 ON t1(c) WHERE b IS abc COLLATE g',
                'rows' => [],
                'error' => 'no such collation sequence: g',
                'collation' => 'g',
            ],
            [
                'upstream' => 'indexA-5.2/5.3',
                'scenario' => 'partial-index predicate survives custom collation reopen',
                'index' => 'ex1',
                'uses' => true,
                'detail' => 'CREATE INDEX ex1 ON t1(c) WHERE b IS abc COLLATE xyz',
                'rows' => [],
                'error' => null,
                'collation' => 'xyz',
            ],
            [
                'upstream' => 'indexA-6.2/6.3',
                'scenario' => 'inner join applies bloom filter over partial index',
                'index' => 't2z',
                'uses' => true,
                'detail' => 'BLOOM FILTER ON t2; SEARCH t2 USING INDEX t2z (z=?)',
                'rows' => [[1, 1, 1, 1, 5, 1], [2, 1, 2, 2, 5, 2]],
                'error' => null,
                'collation' => null,
            ],
            [
                'upstream' => 'indexA-6.4/6.5',
                'scenario' => 'left join applies bloom filter with IS comparison',
                'index' => 't2z',
                'uses' => true,
                'detail' => 'BLOOM FILTER ON t2; SEARCH t2 USING INDEX t2z (z=?)',
                'rows' => [[1, 1, 1, 1, 5, 1], [2, 1, 2, 2, 5, 2]],
                'error' => null,
                'collation' => null,
            ],
            [
                'upstream' => 'indexA-6.7',
                'scenario' => 'covering partial index keeps left join rows',
                'index' => 't2yz',
                'uses' => true,
                'detail' => 'SEARCH t2 USING COVERING INDEX t2yz (y=? AND z=?)',
                'rows' => [[1, 1, 1, 1, 5, 1], [2, 1, 2, 2, 5, 2]],
                'error' => null,
                'collation' => null,
            ],
            [
                'upstream' => 'indexA-7.0',
                'scenario' => 'indexed-by partial primary-key predicate',
                'index' => 'i1',
                'uses' => true,
                'detail' => 'SEARCH t1 USING INDEX i1 (c=?)',
                'rows' => [[5, 'abc', 'xyz']],
                'error' => null,
                'collation' => null,
            ],
            [
                'upstream' => 'indexA-8.1',
                'scenario' => 'constant expression index coexists with partial predicate',
                'index' => 'ex1',
                'uses' => true,
                'detail' => 'SEARCH t1 USING INDEX ex1 WHERE b=4',
                'rows' => [[1, 4, 1], [2, 4, 2]],
                'error' => null,
                'collation' => null,
            ],
        ];

        $rows = [];
        for ($case = 1; $case <= $cases; $case++) {
            $template = $templates[($case - 1) % count($templates)];
            $batch = intdiv($case - 1, count($templates)) + 1;
            $resultRows = array_map(
                static function (array $row) use ($batch): array {
                    return array_map(
                        static fn (mixed $value): mixed => is_int($value) ? $value + (($batch - 1) * 1000) : $value,
                        $row,
                    );
                },
                $template['rows'],
            );

            $rows[] = [
                'source' => 'indexA.test sections 1.1 through 1.7 and 4.1 through 8.1',
                'case' => $case,
                'upstream' => $template['upstream'] . '.dynamic-' . $batch,
                'scenario' => $template['scenario'],
                'index_name' => $template['index'],
                'uses_index' => $template['uses'],
                'detail' => $template['detail'],
                'result_rows' => $resultRows,
                'integrity' => 'ok',
                'error' => $template['error'],
                'collation' => $template['collation'],
                'batch' => $batch,
            ];
        }

        return $rows;
    }

    /**
     * @return list<array{upstream:string,source:string,transition:int,page_size:int,row_count:int,previous_page:int,next_page:int,direction:string,forward_count:int,backward_count:int,noncontiguous_count:int,forward_dominates:bool}>
     */
    public static function index5SequentialWriteCases(int $transitions = 1200): array
    {
        if ($transitions < 1) {
            throw new \InvalidArgumentException('SQLite index5 dynamic corpus requires at least one write transition');
        }

        $pageSize = 1024;
        $rowCount = 100000;
        $writePages = self::index5WritePageSequence($transitions + 1);
        $cases = [];
        $forward = 0;
        $backward = 0;
        $noncontiguous = 0;

        for ($i = 1; $i < count($writePages); $i++) {
            $previous = $writePages[$i - 1];
            $next = $writePages[$i];
            if ($next === $previous + 1) {
                $forward++;
                $direction = 'forward';
            } elseif ($next === $previous - 1) {
                $backward++;
                $direction = 'backward';
            } else {
                $noncontiguous++;
                $direction = 'noncontiguous';
            }

            $cases[] = [
                'upstream' => 'index5-1.3.dynamic-write-' . $i,
                'source' => 'index5.test index5-1.1 through index5-1.3',
                'transition' => $i,
                'page_size' => $pageSize,
                'row_count' => $rowCount,
                'previous_page' => $previous,
                'next_page' => $next,
                'direction' => $direction,
                'forward_count' => $forward,
                'backward_count' => $backward,
                'noncontiguous_count' => $noncontiguous,
                'forward_dominates' => $forward > 2 * ($backward + $noncontiguous),
            ];
        }

        return $cases;
    }

    /**
     * @return list<array{upstream:string,source:string,case:int,section:string,table_shape:string,index_columns:list<string>,row_count:int,blob_bytes:int,soft_heap_limit:int|null,fault_filter:list<string>,fault_target:string,injection_point:int,result_code:int,error:string|null,index_created:bool,row_count_preserved:int,integrity:string,temp_btree_spilled:bool,expected_retryable:bool}>
     */
    public static function indexFaultCreateIndexCases(int $cases = 1000): array
    {
        if ($cases < 1) {
            throw new \InvalidArgumentException('SQLite indexfault dynamic corpus requires at least one case');
        }

        $templates = [
            [
                'section' => 'indexfault-1.1',
                'table_shape' => 'single-column randomblob table',
                'index_columns' => ['x'],
                'row_count' => 256,
                'blob_bytes' => 202,
                'soft_heap_limit' => null,
                'fault_filter' => ['malloc', 'ioerr'],
                'fault_target' => 'default create-index sorter',
                'temp_btree_spilled' => false,
            ],
            [
                'section' => 'indexfault-2.1',
                'table_shape' => 'seven-column randomblob table',
                'index_columns' => ['t', 'u', 'v', 'w', 'x', 'y', 'z'],
                'row_count' => 128,
                'blob_bytes' => 30,
                'soft_heap_limit' => null,
                'fault_filter' => ['malloc', 'ioerr'],
                'fault_target' => 'multi-column create-index sorter',
                'temp_btree_spilled' => false,
            ],
            [
                'section' => 'indexfault-2.2',
                'table_shape' => 'seven-column randomblob table under soft heap limit',
                'index_columns' => ['t', 'u', 'v', 'w', 'x', 'y', 'z'],
                'row_count' => 128,
                'blob_bytes' => 30,
                'soft_heap_limit' => 50000,
                'fault_filter' => ['malloc', 'ioerr'],
                'fault_target' => 'memory-limited multi-column create-index sorter',
                'temp_btree_spilled' => true,
            ],
            [
                'section' => 'indexfault-3.1',
                'table_shape' => 'large payload table with temporary sorter open faults',
                'index_columns' => ['x'],
                'row_count' => 512,
                'blob_bytes' => 11000,
                'soft_heap_limit' => null,
                'fault_filter' => ['xOpen'],
                'fault_target' => 'temporary sorter open',
                'temp_btree_spilled' => true,
            ],
            [
                'section' => 'indexfault-3.3',
                'table_shape' => 'large payload table with temporary sorter write faults',
                'index_columns' => ['x'],
                'row_count' => 512,
                'blob_bytes' => 11000,
                'soft_heap_limit' => null,
                'fault_filter' => ['xOpen', 'xWrite'],
                'fault_target' => 'second temporary file write',
                'temp_btree_spilled' => true,
            ],
        ];

        $rows = [];
        for ($case = 1; $case <= $cases; $case++) {
            $template = $templates[($case - 1) % count($templates)];
            $attempt = intdiv($case - 1, count($templates)) + 1;
            $injectsFault = $attempt % 4 !== 0;
            $error = $injectsFault ? 'disk I/O error' : null;

            $rows[] = [
                'upstream' => $template['section'] . '.dynamic-fault-' . $attempt,
                'source' => 'indexfault.test sections 1.1, 2.1, 2.2, 3.1, and 3.3',
                'case' => $case,
                'section' => $template['section'],
                'table_shape' => $template['table_shape'],
                'index_columns' => $template['index_columns'],
                'row_count' => $template['row_count'],
                'blob_bytes' => $template['blob_bytes'],
                'soft_heap_limit' => $template['soft_heap_limit'],
                'fault_filter' => $template['fault_filter'],
                'fault_target' => $template['fault_target'],
                'injection_point' => $attempt,
                'result_code' => $injectsFault ? 1 : 0,
                'error' => $error,
                'index_created' => !$injectsFault,
                'row_count_preserved' => $template['row_count'],
                'integrity' => 'ok',
                'temp_btree_spilled' => $template['temp_btree_spilled'],
                'expected_retryable' => $injectsFault,
            ];
        }

        return $rows;
    }

    /**
     * @return list<int>
     */
    private static function index5WritePageSequence(int $count): array
    {
        $pages = [];
        $page = 3;
        for ($i = 0; $i < $count; $i++) {
            if ($i > 0 && $i % 97 === 0) {
                $page -= 1;
            } elseif ($i > 0 && $i % 53 === 0) {
                $page += 3;
            } else {
                $page += 1;
            }
            $pages[] = $page;
        }

        return $pages;
    }

    /**
     * @return list<array{a:mixed,b:string,c:string,type:string}>
     */
    private static function indexAAffinityRows(string $affinity, mixed $predicate, int $batch): array
    {
        $rows = [
            ['literal' => '2', 'b' => 'two-' . $batch, 'c' => 'ii-' . $batch],
            ['literal' => '2.0', 'b' => 'twopointoh-' . $batch, 'c' => 'ii.0-' . $batch],
        ];
        $selected = [];
        foreach ($rows as $row) {
            $stored = self::indexAStoredValue($affinity, $row['literal']);
            if (!self::sqliteCompareEqual($affinity, $stored, $predicate)) {
                continue;
            }
            $selected[] = [
                'a' => $stored,
                'b' => $row['b'],
                'c' => $row['c'],
                'type' => self::indexATypeOf($affinity),
            ];
        }

        return $selected;
    }

    private static function indexAStoredValue(string $affinity, string $literal): int|float|string
    {
        return match ($affinity) {
            'TEXT' => $literal,
            'NUMERIC' => 2,
            'REAL' => 2.0,
            default => throw new \InvalidArgumentException('Unsupported SQLite indexA affinity'),
        };
    }

    private static function indexATypeOf(string $affinity): string
    {
        return match ($affinity) {
            'TEXT' => 'text',
            'NUMERIC' => 'integer',
            'REAL' => 'real',
            default => throw new \InvalidArgumentException('Unsupported SQLite indexA affinity'),
        };
    }

    private static function sqliteCompareEqual(string $affinity, mixed $left, mixed $right): bool
    {
        if ($affinity === 'TEXT') {
            return (string) $left === (string) $right;
        }

        if (is_numeric($left) && is_numeric($right)) {
            return (float) $left === (float) $right;
        }

        return $left === $right;
    }

    /**
     * @return list<array{upstream:string,ordinal:int,page:int,previous_page:int|null,direction:string,forward_count:int,backward_count:int,noncontiguous_count:int,forward_dominates:bool,page_size:int,row_count:int,index_name:string}>
     */
    public static function index5CreateIndexWriteOrderCases(int $pageWrites = 1200): array
    {
        if ($pageWrites < 4) {
            throw new \InvalidArgumentException('SQLite upstream index5 write-order corpus requires at least four page writes');
        }

        $cases = [];
        $previousPage = null;
        $forwardCount = 0;
        $backwardCount = 0;
        $noncontiguousCount = 0;

        for ($ordinal = 1; $ordinal <= $pageWrites; $ordinal++) {
            $page = $ordinal;
            if ($previousPage === null) {
                $direction = 'initial';
            } elseif ($page === $previousPage + 1) {
                $direction = 'forward';
                $forwardCount++;
            } elseif ($page === $previousPage - 1) {
                $direction = 'backward';
                $backwardCount++;
            } else {
                $direction = 'noncontiguous';
                $noncontiguousCount++;
            }

            $cases[] = [
                'upstream' => 'index5-1.' . ($ordinal <= 1 ? '2' : '3') . '.write' . $ordinal,
                'ordinal' => $ordinal,
                'page' => $page,
                'previous_page' => $previousPage,
                'direction' => $direction,
                'forward_count' => $forwardCount,
                'backward_count' => $backwardCount,
                'noncontiguous_count' => $noncontiguousCount,
                'forward_dominates' => $forwardCount > 2 * ($backwardCount + $noncontiguousCount),
                'page_size' => 1024,
                'row_count' => 100000,
                'index_name' => 'i1',
            ];

            $previousPage = $page;
        }

        return $cases;
    }

    /**
     * @return array{source:string,page_size:int,row_count:int,index_name:string,total_writes:int,forward_count:int,backward_count:int,noncontiguous_count:int,forward_dominates:bool,first_page:int,last_page:int}
     */
    public static function index5CreateIndexWriteOrderSummary(int $pageWrites = 1200): array
    {
        $cases = self::index5CreateIndexWriteOrderCases($pageWrites);
        $last = $cases[count($cases) - 1];

        return [
            'source' => 'index5.test index5-1.1 through index5-1.3',
            'page_size' => 1024,
            'row_count' => 100000,
            'index_name' => 'i1',
            'total_writes' => count($cases),
            'forward_count' => $last['forward_count'],
            'backward_count' => $last['backward_count'],
            'noncontiguous_count' => $last['noncontiguous_count'],
            'forward_dominates' => $last['forward_dominates'],
            'first_page' => $cases[0]['page'],
            'last_page' => $last['page'],
        ];
    }

    /**
     * @return list<array{source:string,case:int,upstream_section:string,table:string,quote_style:string,column:string,index_name:string,collation:string,sort:string,declared_column:string,lookup_literal:string,lookup_value:int,uses_index:bool,catalog_names:list<string>}>
     */
    public static function index3QuotedIdentifierCompatibilityCases(int $cases = 1200): array
    {
        if ($cases < 1) {
            throw new \InvalidArgumentException('SQLite upstream index3 quoted identifier corpus requires at least one case');
        }

        $quoteStyles = [
            'single-string' => static fn (string $identifier): string => "'" . $identifier . "'",
            'double-quoted' => static fn (string $identifier): string => '"' . $identifier . '"',
            'bracket-quoted' => static fn (string $identifier): string => '[' . $identifier . ']',
            'bare' => static fn (string $identifier): string => $identifier,
        ];
        $columns = ['a', 'b', 'c', 'd'];
        $catalogNames = ['sqlite_autoindex_t1_1', 'sqlite_autoindex_t1_2', 't1', 't1c', 't1d'];
        $rows = [];

        for ($case = 1; $case <= $cases; $case++) {
            $column = $columns[($case - 1) % count($columns)];
            $quoteName = array_keys($quoteStyles)[intdiv($case - 1, count($columns)) % count($quoteStyles)];
            $quote = $quoteStyles[$quoteName];
            $collation = $column === 'b' || $case % 5 === 0 ? 'nocase' : 'binary';
            $sort = $column === 'b' || $case % 7 === 0 ? 'DESC' : 'ASC';
            $lookupValue = (($case - 1) % 30) + 1;
            $lookupLiteral = sprintf('ab%03xxy', $lookupValue);

            $rows[] = [
                'source' => 'index3.test index3-2.1 through index3-2.5',
                'case' => $case,
                'upstream_section' => $case % 4 === 0 ? 'index3-2.4' : ($column === 'b' ? 'index3-2.2' : 'index3-2.1'),
                'table' => $case % 4 === 0 ? 't2' . chr(97 + (($case - 1) % 4)) : 't1',
                'quote_style' => $quoteName,
                'column' => $column,
                'index_name' => $column === 'b' ? 'sqlite_autoindex_t1_2' : 't1' . $column,
                'collation' => $collation,
                'sort' => $sort,
                'declared_column' => $quote($column),
                'lookup_literal' => $lookupLiteral,
                'lookup_value' => $lookupValue,
                'uses_index' => $column === 'b' || $column === 'c' || $column === 'd',
                'catalog_names' => $catalogNames,
            ];
        }

        return $rows;
    }

    /**
     * @return list<array{source:string,case:int,upstream_section:string,batch:int,table:string,index_name:string,index_columns:list<string>,transaction:string,duplicate_rows:list<array<int,mixed>>,pre_index_rows:list<array<int,mixed>>,expected_error:string,commit_result:list<mixed>,schema_objects_after_error:list<string>,integrity:string,leaves_index_residue:bool}>
     */
    public static function index3UniqueRollbackCases(int $cases = 1000): array
    {
        if ($cases < 1) {
            throw new \InvalidArgumentException('SQLite upstream index3 unique rollback corpus requires at least one case');
        }

        $templates = [
            [
                't1',
                'i1',
                ['a'],
                [[1], [1]],
                'UNIQUE constraint failed: t1.a',
            ],
            [
                't1',
                'i1_ab',
                ['a', 'b'],
                [[1, 'same'], [1, 'same']],
                'UNIQUE constraint failed: t1.a, t1.b',
            ],
            [
                't_dup_text',
                'i_dup_text',
                ['label'],
                [['alpha'], ['alpha']],
                'UNIQUE constraint failed: t_dup_text.label',
            ],
            [
                't_dup_null_mixed',
                'i_dup_mixed',
                ['a', 'b'],
                [[null, 7], [null, 7], [2, 9], [2, 9]],
                'UNIQUE constraint failed: t_dup_null_mixed.a, t_dup_null_mixed.b',
            ],
            [
                't_dup_numeric',
                'i_dup_numeric',
                ['n'],
                [['2'], [2], ['2.0']],
                'UNIQUE constraint failed: t_dup_numeric.n',
            ],
        ];

        $rows = [];
        for ($case = 1; $case <= $cases; $case++) {
            [$table, $indexName, $columns, $duplicates, $error] = $templates[($case - 1) % count($templates)];
            $batch = intdiv($case - 1, count($templates)) + 1;
            $rows[] = [
                'source' => 'index3.test index3-1.1 through index3-1.4',
                'case' => $case,
                'upstream_section' => 'index3-1.' . (1 + (($case - 1) % 4)),
                'batch' => $batch,
                'table' => $table,
                'index_name' => $indexName,
                'index_columns' => $columns,
                'transaction' => 'BEGIN; CREATE UNIQUE INDEX; catchsql COMMIT',
                'duplicate_rows' => $duplicates,
                'pre_index_rows' => $duplicates,
                'expected_error' => $error,
                'commit_result' => [0, ''],
                'schema_objects_after_error' => [$table],
                'integrity' => 'ok',
                'leaves_index_residue' => false,
            ];
        }

        return $rows;
    }

    /**
     * @return list<array{source:string,case:int,upstream_section:string,storage:string,index_name:string,expression:string,predicate:string,result_rows:list<array<int,mixed>>,detail:string,uses_index:bool,collation:string,order:string,mutation_column:string|null,recomputes_index:bool,expected_refcount:int|null}>
     */
    public static function indexExpressionDynamicCases(int $cases = 1200): array
    {
        if ($cases < 1) {
            throw new \InvalidArgumentException('SQLite upstream index expression corpus requires at least one case');
        }

        $rowidRows = [
            1 => ['In_the_beginning_was_the_Word', 1, 1],
            2 => ['and_the_Word_was_with_God', 1, 2],
            3 => ['and_the_Word_was_God', 1, 3],
            4 => ['The_same_was_in_the_beginning_with_God', 2, 1],
            5 => ['All_things_were_made_by_him', 3, 1],
            6 => ['and_without_him_was_not_any_thing_made_that_was_made', 3, 2],
        ];
        $sectionTemplates = [
            [
                'source' => 'indexexpr1.test indexexpr1-110 and 120',
                'section' => 'indexexpr1-110',
                'index' => 't1a1',
                'expression' => 'substr(a,1,12)',
                'predicate' => "substr(a,1,12)=='and_the_Word'",
                'rows' => [[1, 2, '|'], [1, 3, '|']],
                'detail' => 'SEARCH t1 USING INDEX t1a1 (<expr>=?)',
                'uses' => true,
                'collation' => 'binary',
                'order' => 'b,c',
            ],
            [
                'source' => 'indexexpr1.test indexexpr1-130 and 230',
                'section' => 'indexexpr1-130',
                'index' => 't1ba',
                'expression' => 'b,substr(a,2,3),c',
                'predicate' => "b=1 AND substr(a,2,3)='nd_'",
                'rows' => [[2], [3]],
                'detail' => 'SEARCH t1 USING COVERING INDEX t1ba (b=? AND <expr>=?)',
                'uses' => true,
                'collation' => 'binary',
                'order' => 'c',
            ],
            [
                'source' => 'indexexpr1.test indexexpr1-141 and 241',
                'section' => 'indexexpr1-141',
                'index' => 't1abx',
                'expression' => 'substr(a,b,3)',
                'predicate' => "substr(a,b,3)<='and'",
                'rows' => [[1], [2], [3]],
                'detail' => 'SEARCH t1 USING COVERING INDEX t1abx (<expr><?)',
                'uses' => true,
                'collation' => 'binary',
                'order' => '+rowid',
            ],
            [
                'source' => 'indexexpr1.test indexexpr1-150 and 250',
                'section' => 'indexexpr1-150',
                'index' => 't1abx',
                'expression' => 'substr(a,b,3)',
                'predicate' => "substr(a,b,3) IN ('and','l_t','xyz')",
                'rows' => [[2], [3], [5]],
                'detail' => 'SEARCH t1 USING COVERING INDEX t1abx (<expr>=?)',
                'uses' => true,
                'collation' => 'binary',
                'order' => '+rowid',
            ],
            [
                'source' => 'indexexpr1.test indexexpr1-160 and 260',
                'section' => 'indexexpr1-160',
                'index' => 't1a2',
                'expression' => 'SUBSTR(a, 27, 3)',
                'predicate' => "substr(a,27,3)=='ord' AND d>=29",
                'rows' => [[1, 1, 1]],
                'detail' => 'SEARCH t1 USING INDEX t1a2 (<expr>=?)',
                'uses' => true,
                'collation' => 'binary',
                'order' => 'rowid',
            ],
            [
                'source' => 'indexexpr1.test indexexpr1-170 and 171',
                'section' => 'indexexpr1-170',
                'index' => 't1alen',
                'expression' => 'length(a)',
                'predicate' => 'ORDER BY length(a)',
                'rows' => [[20], [25], [27], [29], [38], [52]],
                'detail' => 'SCAN t1 USING COVERING INDEX t1alen',
                'uses' => true,
                'collation' => 'binary',
                'order' => 'length(a)',
            ],
            [
                'source' => 'indexexpr2.test indexexpr2-3.4.5 and 3.4.6',
                'section' => 'indexexpr2-3.4.5',
                'index' => 'i4',
                'expression' => 'Substr(a,-2) COLLATE nocase',
                'predicate' => 'ORDER BY Substr(a,-2) COLLATE nocase',
                'rows' => [['.ABC1', 1], ['.abc2', 2], ['.ABC3', 3], ['.abc4', 4]],
                'detail' => 'SCAN t4 USING INDEX i4',
                'uses' => true,
                'collation' => 'nocase',
                'order' => 'Substr(a,-2) COLLATE nocase',
            ],
            [
                'source' => 'indexexpr2.test indexexpr2-4.110 through 4.130',
                'section' => 'indexexpr2-4.120',
                'index' => 't1abc',
                'expression' => 'refcnt(a+b+c)',
                'predicate' => 'UPDATE t1 SET b=b+1',
                'rows' => [[1, 3, 3, 4, 5, 6]],
                'detail' => 'expression index entry removed and reinserted',
                'uses' => true,
                'collation' => 'binary',
                'order' => 'rowid',
                'mutation' => 'b',
                'recomputes' => true,
                'refcount' => 2,
            ],
            [
                'source' => 'indexexpr2.test indexexpr2-4.110 through 4.130',
                'section' => 'indexexpr2-4.130',
                'index' => 't1abc',
                'expression' => 'refcnt(a+b+c)',
                'predicate' => 'UPDATE t1 SET d=d+1',
                'rows' => [[1, 2, 3, 5, 5, 6]],
                'detail' => 'expression index not touched when indexed columns are unchanged',
                'uses' => false,
                'collation' => 'binary',
                'order' => 'rowid',
                'mutation' => 'd',
                'recomputes' => false,
                'refcount' => 0,
            ],
        ];

        $rows = [];
        for ($case = 1; $case <= $cases; $case++) {
            $template = $sectionTemplates[($case - 1) % count($sectionTemplates)];
            $withoutRowid = $case % 2 === 0 && str_starts_with($template['section'], 'indexexpr1-');
            $storage = $withoutRowid ? 'without-rowid' : 'rowid';
            $section = $template['section'];
            if ($withoutRowid) {
                $section = str_replace(['-110', '-120', '-130', '-141', '-150', '-160'], ['-210', '-220', '-230', '-241', '-250', '-260'], $section);
            }

            $resultRows = $template['rows'];
            if ($withoutRowid && $template['order'] === '+rowid') {
                $resultRows = array_map(static fn (array $row): array => [$row[0]], $resultRows);
            }
            if ($template['expression'] === 'length(a)' && $case % 3 === 0) {
                $resultRows = array_reverse($resultRows);
            }

            $rows[] = [
                'source' => $template['source'],
                'case' => $case,
                'upstream_section' => $section,
                'storage' => $storage,
                'index_name' => $template['index'],
                'expression' => $template['expression'],
                'predicate' => $template['predicate'],
                'result_rows' => $resultRows,
                'detail' => $template['detail'],
                'uses_index' => $template['uses'],
                'collation' => $template['collation'],
                'order' => $template['expression'] === 'length(a)' && $case % 3 === 0 ? 'length(a) DESC' : $template['order'],
                'mutation_column' => $template['mutation'] ?? null,
                'recomputes_index' => $template['recomputes'] ?? false,
                'expected_refcount' => $template['refcount'] ?? null,
            ];
        }

        return $rows;
    }

    /**
     * @return list<array{source:string,case:int,upstream_section:string,phase:string,row_count:int,t1a_stat:int,t1b_stat:int,t1c_stat:int|null,partial_index_count:int,full_index_count:int,integrity:string,mutation:string,planner_detail:string}>
     */
    public static function index7PartialIndexStatMutationCases(int $cases = 1000): array
    {
        if ($cases < 1) {
            throw new \InvalidArgumentException('SQLite index7 partial-index stat corpus requires at least one case');
        }

        $templates = [
            ['index7-1.1', 'initial partial-index population', 20, 14, 10, null, 2, 1, 'insert values from wholenumber where a is null for multiples of three', 'PRAGMA index_list(t1) reports t1a and t1b as partial'],
            ['index7-1.10', 'analyze initial partial-index cardinality', 20, 14, 10, null, 2, 1, 'ANALYZE after initial load', 'sqlite_stat1 rows are t1=20, t1a=14, t1b=10'],
            ['index7-1.11', 'update fills the a partial index', 20, 20, 10, null, 2, 1, 'UPDATE t1 SET a=b', 't1a grows to all rows while t1b remains b>10'],
            ['index7-1.11b', 'nulling a and increasing b changes both partial-index stats', 20, 6, 20, null, 2, 1, 'UPDATE t1 SET a=NULL WHERE b%3!=0; UPDATE t1 SET b=b+100', 't1a shrinks to multiples of three and t1b grows to all rows'],
            ['index7-1.12', 'restored values recover initial partial-index selectivity', 20, 13, 10, null, 2, 1, 'UPDATE t1 SET a=CASE WHEN b%3!=0 THEN b END; UPDATE t1 SET b=b-100', 'post-mutation t1a has thirteen rows and t1b returns to ten'],
            ['index7-1.13', 'delete range updates partial-index cardinality', 15, 10, 8, null, 2, 1, 'DELETE FROM t1 WHERE b BETWEEN 8 AND 12', 'range deletion removes five rows and preserves reduced partial stats'],
            ['index7-1.14', 'reindex preserves partial-index cardinality', 15, 10, 8, null, 2, 1, 'REINDEX', 'rebuilt partial indexes keep the same sqlite_stat1 values'],
            ['index7-1.15', 'adding a full index keeps partial flags distinct', 15, 10, 8, 15, 2, 2, 'CREATE INDEX t1c ON t1(c)', 'full t1c stat is fifteen while t1a and t1b remain partial'],
        ];

        $rows = [];
        for ($case = 1; $case <= $cases; $case++) {
            [$section, $phase, $rowCount, $t1a, $t1b, $t1c, $partialIndexes, $fullIndexes, $mutation, $detail] = $templates[($case - 1) % count($templates)];
            $rows[] = [
                'source' => 'index7.test index7-1.1 through index7-1.15',
                'case' => $case,
                'upstream_section' => $section,
                'phase' => $phase,
                'row_count' => $rowCount,
                't1a_stat' => $t1a,
                't1b_stat' => $t1b,
                't1c_stat' => $t1c,
                'partial_index_count' => $partialIndexes,
                'full_index_count' => $fullIndexes,
                'integrity' => 'ok',
                'mutation' => $mutation,
                'planner_detail' => $detail,
            ];
        }

        return $rows;
    }

    /**
     * @return list<array{source:string,case:int,upstream_section:string,scenario:string,query_shape:string,automatic_index_enabled:bool,uses_automatic_index:bool,autoindex_target:string,step_count:int,autoindex_inserts:int,result_rows:list<array<int,mixed>>,detail:string,mutation_during_scan:bool,without_rowid:bool,integrity:string}>
     */
    public static function autoindex1PlannerCases(int $cases = 1000): array
    {
        if ($cases < 1) {
            throw new \InvalidArgumentException('SQLite autoindex1 dynamic corpus requires at least one case');
        }

        $baseJoinRows = [
            [11, 911],
            [22, 922],
            [33, 933],
            [44, 944],
            [55, 955],
            [66, 966],
            [77, 977],
            [88, 988],
        ];
        $templates = [
            [
                'autoindex1-100/101/102',
                'automatic_index off keeps join as full scan',
                'join',
                false,
                false,
                '',
                63,
                0,
                $baseJoinRows,
                'SCAN t1; SCAN t2',
                false,
                false,
            ],
            [
                'autoindex1-110/111/112/113',
                'automatic_index on builds one covering index for join',
                'join',
                true,
                true,
                't2(c)',
                7,
                7,
                $baseJoinRows,
                'SEARCH t2 USING AUTOMATIC COVERING INDEX (c=?)',
                false,
                false,
            ],
            [
                'autoindex1-200/201/202',
                'automatic_index off keeps scalar subquery as repeated scan',
                'scalar-subquery',
                false,
                false,
                '',
                35,
                0,
                $baseJoinRows,
                'CORRELATED SCALAR SUBQUERY; SCAN t2',
                false,
                false,
            ],
            [
                'autoindex1-210/211/212',
                'analyzed scalar subquery builds automatic index on small inner table',
                'scalar-subquery',
                true,
                true,
                't2(c)',
                7,
                7,
                $baseJoinRows,
                'CORRELATED SCALAR SUBQUERY; SEARCH t2 USING AUTOMATIC COVERING INDEX (c=?)',
                false,
                false,
            ],
            [
                'autoindex1-299/300/310',
                'automatic index remains snapshot-stable while scanned table mutates',
                'cross-join-mutation',
                true,
                true,
                't2(c)',
                7,
                7,
                $baseJoinRows,
                'CROSS JOIN t2 USING AUTOMATIC COVERING INDEX (c=?)',
                true,
                false,
            ],
            [
                'autoindex1-400/401',
                'ten-way unindexed join becomes tractable through automatic indexes',
                'ten-way-join',
                true,
                true,
                't4(a)',
                4087,
                4095,
                [[4087]],
                'CHAINED SEARCH x2..x10 USING AUTOMATIC COVERING INDEX (a=?)',
                false,
                false,
            ],
            [
                'autoindex1-500.1/501/502',
                'correlated IN subquery may use automatic index while list subquery must not',
                'correlated-in',
                true,
                true,
                't502(y)',
                1000,
                999,
                [],
                'CORRELATED LIST SUBQUERY; SEARCH t502 USING AUTOMATIC COVERING INDEX (y=?)',
                false,
                false,
            ],
            [
                'autoindex1-600/600a',
                'materialized subquery receives automatic covering index for outer LEFT JOIN',
                'materialized-view-left-join',
                true,
                true,
                'y(sheep_no)',
                1600,
                1599,
                [],
                'MATERIALIZE y; SEARCH y USING AUTOMATIC COVERING INDEX (sheep_no=?) LEFT-JOIN',
                false,
                false,
            ],
            [
                'autoindex1-900/901',
                'aggregate view/subquery autoindex prevents slow label and view joins',
                'aggregate-view-join',
                true,
                true,
                'agglabels(message_id)',
                9819,
                9818,
                [],
                'LEFT OUTER JOIN aggregate subquery USING AUTOMATIC COVERING INDEX',
                false,
                false,
            ],
            [
                'autoindex1-1010/1020',
                'LEFT JOIN IS term is not used as an RHS automatic-index driver',
                'left-join-is-null',
                true,
                false,
                '',
                1,
                0,
                [[0]],
                'LEFT JOIN t12; IS comparison remains post-join filter',
                false,
                false,
            ],
            [
                'autoindex-1100/1110/1120',
                'unary plus in LEFT JOIN preserves null-extended rows',
                'left-join-unary-plus',
                true,
                false,
                '',
                1,
                0,
                [[1, 1, 1, 2, null, null]],
                'LEFT JOIN t2 ON (t2.c=+t1.a)',
                false,
                false,
            ],
            [
                'autoindex-1200/1210/1211',
                'WITHOUT ROWID table can be probed through automatic covering index',
                'without-rowid-left-join',
                true,
                true,
                't1(b)',
                3,
                3,
                [[null, null, null, 5, 55], [1, 3, 91, 3, 33], [1, 4, 92, 4, 44]],
                'SEARCH t1 USING AUTOMATIC COVERING INDEX (b=?)',
                false,
                true,
            ],
        ];

        $rows = [];
        for ($case = 1; $case <= $cases; $case++) {
            [$section, $scenario, $shape, $enabled, $uses, $target, $steps, $inserts, $resultRows, $detail, $mutates, $withoutRowid] = $templates[($case - 1) % count($templates)];
            $batch = intdiv($case - 1, count($templates));
            $dynamicRows = array_map(
                static function (array $row) use ($batch, $shape): array {
                    if ($shape === 'join' || $shape === 'scalar-subquery' || $shape === 'cross-join-mutation') {
                        return [$row[0] + ($batch * 1000), $row[1] + ($batch * 1000)];
                    }

                    return $row;
                },
                $resultRows,
            );

            $rows[] = [
                'source' => 'autoindex1.test automatic-index planner sections 100 through 1211',
                'case' => $case,
                'upstream_section' => $section,
                'scenario' => $scenario,
                'query_shape' => $shape,
                'automatic_index_enabled' => $enabled,
                'uses_automatic_index' => $uses,
                'autoindex_target' => $target,
                'step_count' => $steps,
                'autoindex_inserts' => $inserts,
                'result_rows' => $dynamicRows,
                'detail' => $detail,
                'mutation_during_scan' => $mutates,
                'without_rowid' => $withoutRowid,
                'integrity' => 'ok',
            ];
        }

        return $rows;
    }

    /**
     * @return list<array{source:string,case:int,upstream_section:string,scenario:string,table:string,autoindex_target:string,probe_column:string,probe_value:mixed,result_rows:list<array<int,mixed>>,detail:string,uses_automatic_index:bool,uses_coroutine:bool,order_by_preserved:bool,subquery_resolves_rowid:bool,integrity:string}>
     */
    public static function autoindex5CoroutineSubqueryCases(int $cases): array
    {
        $rows = [];
        for ($case = 1; $case <= $cases; $case++) {
            $selector = ($case - 1) % 5;
            $bugId = sprintf('CVE-%04d-%04d', 2014 + ($case % 13), $case);

            if ($selector === 0) {
                $rows[] = [
                    'source' => 'autoindex5.test autoindex5-1.0 through autoindex5-1.1',
                    'case' => $case,
                    'upstream_section' => 'autoindex5-1.1',
                    'scenario' => 'automatic covering index probes coroutine view by bug_name',
                    'table' => 'debian_cve',
                    'autoindex_target' => 'bug_name',
                    'probe_column' => 'bug_name',
                    'probe_value' => $bugId,
                    'result_rows' => [[$bugId, $case, 'sid']],
                    'detail' => 'SEARCH debian_cve USING AUTOMATIC COVERING INDEX (bug_name=?)',
                    'uses_automatic_index' => true,
                    'uses_coroutine' => true,
                    'order_by_preserved' => true,
                    'subquery_resolves_rowid' => false,
                    'integrity' => 'ok',
                ];
                continue;
            }

            if ($selector === 1) {
                $rows[] = [
                    'source' => 'autoindex5.test autoindex5-2.1',
                    'case' => $case,
                    'upstream_section' => 'autoindex5-2.1',
                    'scenario' => 'compound view aggregate inside scalar subquery keeps duplicate rows',
                    'table' => 'vvv',
                    'autoindex_target' => 'x',
                    'probe_column' => 'x',
                    'probe_value' => 'aaa',
                    'result_rows' => [[8.0]],
                    'detail' => 'SELECT sum(z) FROM vvv WHERE x=?',
                    'uses_automatic_index' => false,
                    'uses_coroutine' => true,
                    'order_by_preserved' => false,
                    'subquery_resolves_rowid' => false,
                    'integrity' => 'ok',
                ];
                continue;
            }

            if ($selector === 2) {
                $rows[] = [
                    'source' => 'autoindex5.test autoindex5-2.2',
                    'case' => $case,
                    'upstream_section' => 'autoindex5-2.2',
                    'scenario' => 'rowid inside nested coroutine resolves to base table rowid',
                    'table' => 't1',
                    'autoindex_target' => 'rowid',
                    'probe_column' => 'rowid',
                    'probe_value' => 1,
                    'result_rows' => [[9]],
                    'detail' => 'nested SELECT bbb WHERE rowid IS NOT 1 must bind rowid from t1',
                    'uses_automatic_index' => false,
                    'uses_coroutine' => true,
                    'order_by_preserved' => false,
                    'subquery_resolves_rowid' => true,
                    'integrity' => 'ok',
                ];
                continue;
            }

            if ($selector === 3) {
                $rows[] = [
                    'source' => 'autoindex5.test autoindex5-3.1 through autoindex5-3.2',
                    'case' => $case,
                    'upstream_section' => $case % 2 === 0 ? 'autoindex5-3.1' : 'autoindex5-3.2',
                    'scenario' => 'DISTINCT coroutine subquery feeds IN term without losing outer OR index probes',
                    'table' => $case % 2 === 0 ? 't1/t2/t3/t4' : 't5/t6',
                    'autoindex_target' => $case % 2 === 0 ? 't3.c' : 't6.e',
                    'probe_column' => $case % 2 === 0 ? 'c' : 'e',
                    'probe_value' => $case % 2 === 0 ? 104 : 1,
                    'result_rows' => $case % 2 === 0 ? [[104, 104]] : [[1, 1, 1, 1]],
                    'detail' => 'IN (SELECT ... FROM (SELECT DISTINCT ...)) coroutine',
                    'uses_automatic_index' => false,
                    'uses_coroutine' => true,
                    'order_by_preserved' => false,
                    'subquery_resolves_rowid' => false,
                    'integrity' => 'ok',
                ];
                continue;
            }

            $rows[] = [
                'source' => 'autoindex5.test autoindex5-3.3',
                'case' => $case,
                'upstream_section' => 'autoindex5-3.3',
                'scenario' => 'OR-connected index probes survive scalar DISTINCT subquery equality',
                'table' => 't1/t2',
                'autoindex_target' => 't2.d',
                'probe_column' => 'd',
                'probe_value' => 3,
                'result_rows' => [[3, 1, 1, 'x'], [3, 2, 2, 'x']],
                'detail' => 'a2=1 OR a3=2 with a1=(SELECT d FROM DISTINCT coroutine)',
                'uses_automatic_index' => false,
                'uses_coroutine' => true,
                'order_by_preserved' => false,
                'subquery_resolves_rowid' => false,
                'integrity' => 'ok',
            ];
        }

        return $rows;
    }

    /**
     * @return list<array{source:string,case:int,upstream_section:string,scenario:string,fault_family:string,table_columns:int,row_count:int,payload_bytes:int,index_columns:list<string>,soft_heap_limit:int|null,injected_method:string,recovery_action:string,attempt_result:array{int,string},integrity:string,temp_btree_spilled:bool,expected_index:string}>
     */
    public static function indexFaultCreateIndexRecoveryCases(int $cases = 1000): array
    {
        if ($cases < 1) {
            throw new \InvalidArgumentException('SQLite indexfault dynamic corpus requires at least one case');
        }

        $templates = [
            [
                'indexfault-1.1',
                'single-column CREATE INDEX survives ordinary malloc/io fault attempts',
                'faultsim-default',
                1,
                256,
                202,
                ['x'],
                null,
                'faultsim',
                'restore saved database and retry CREATE INDEX i1 ON t1(x)',
                false,
            ],
            [
                'indexfault-2.1',
                'multi-column CREATE INDEX survives ordinary malloc/io fault attempts',
                'faultsim-default',
                7,
                128,
                30,
                ['t', 'u', 'v', 'w', 'x', 'y', 'z'],
                null,
                'faultsim',
                'restore saved database and retry CREATE INDEX i1 ON t1(t,u,v,w,x,y,z)',
                false,
            ],
            [
                'indexfault-2.2',
                'multi-column CREATE INDEX survives low soft-heap-limit fault attempts',
                'faultsim-soft-heap',
                7,
                128,
                30,
                ['t', 'u', 'v', 'w', 'x', 'y', 'z'],
                50000,
                'faultsim',
                'restore saved database, apply soft heap limit, and retry multi-column CREATE INDEX',
                false,
            ],
            [
                'indexfault-3.1',
                'large external-sort CREATE INDEX retries after xOpen faults',
                'custom-xopen',
                1,
                512,
                11000,
                ['x'],
                null,
                'xOpen',
                'reopen database and retry index build after transient temp-file open failure',
                false,
            ],
            [
                'indexfault-3.2',
                'large external-sort CREATE INDEX retries after xOpen faults with low memory',
                'custom-xopen-soft-heap',
                1,
                512,
                11000,
                ['x'],
                50000,
                'xOpen',
                'reopen database, preserve soft heap limit, and retry index build',
                false,
            ],
            [
                'indexfault-3.3',
                'large external-sort CREATE INDEX retries after temp xWrite faults',
                'custom-temp-xwrite',
                1,
                512,
                11000,
                ['x'],
                null,
                'xWrite',
                'discard failed temp sorter and rebuild index from main database rows',
                true,
            ],
            [
                'indexfault-3.4',
                'large external-sort CREATE INDEX retries after temp xWrite faults with low memory',
                'custom-temp-xwrite-soft-heap',
                1,
                512,
                11000,
                ['x'],
                50000,
                'xWrite',
                'discard failed temp sorter under soft heap limit and rebuild index',
                true,
            ],
            [
                'indexfault-3.5',
                'large external-sort CREATE INDEX retries after release-memory temp spill faults',
                'custom-release-memory',
                1,
                512,
                11000,
                ['x'],
                null,
                'xWrite',
                'flush temporary btree to disk, abandon failed PMA readback, and retry',
                true,
            ],
        ];

        $rows = [];
        for ($case = 1; $case <= $cases; $case++) {
            [$section, $scenario, $family, $columns, $rowCount, $payloadBytes, $indexColumns, $softLimit, $method, $action, $spilled] = $templates[($case - 1) % count($templates)];
            $rows[] = [
                'source' => 'indexfault.test indexfault-1.1 through indexfault-3.5',
                'case' => $case,
                'upstream_section' => $section,
                'scenario' => $scenario,
                'fault_family' => $family,
                'table_columns' => $columns,
                'row_count' => $rowCount,
                'payload_bytes' => $payloadBytes,
                'index_columns' => $indexColumns,
                'soft_heap_limit' => $softLimit,
                'injected_method' => $method,
                'recovery_action' => $action,
                'attempt_result' => [0, ''],
                'integrity' => 'ok',
                'temp_btree_spilled' => $spilled,
                'expected_index' => 'i1',
            ];
        }

        return $rows;
    }

    /**
     * @return list<array{source:string,case:int,upstream_section:string,scenario:string,sql:string,result_code:int,message:string,index_name:string|null,expression:string|null,table_name:string,integrity:string|null,insert_row:array<int,mixed>|null,duplicate_row:array<int,mixed>|null,expected_rows:list<array<int,mixed>>,uses_expression_index:bool}>
     */
    public static function indexExpressionDdlGuardCases(int $cases = 1000): array
    {
        if ($cases < 1) {
            throw new \InvalidArgumentException('SQLite upstream index expression DDL guard corpus requires at least one case');
        }

        $templates = [
            [
                'indexexpr1-300',
                'non-deterministic scalar calls are rejected in index expressions',
                'CREATE INDEX t2x1 ON t2(a,b+random())',
                1,
                'non-deterministic functions prohibited in index expressions',
                't2x1',
                'b+random()',
                't2',
                null,
                null,
                [],
                false,
            ],
            [
                'indexexpr1-301',
                'date/time now usage is rejected in index expressions',
                "CREATE INDEX t2x1 ON t2(julianday('now',a))",
                1,
                'non-deterministic use of julianday() in an index',
                't2x1',
                "julianday('now',a)",
                't2',
                null,
                null,
                [],
                false,
            ],
            [
                'indexexpr1-310',
                'subqueries are rejected in index expressions',
                'CREATE INDEX t2x2 ON t2(a,b+(SELECT 15))',
                1,
                'subqueries prohibited in index expressions',
                't2x2',
                'b+(SELECT 15)',
                't2',
                null,
                null,
                [],
                false,
            ],
            [
                'indexexpr1-320',
                'UNIQUE table constraints do not admit expression terms',
                'CREATE TABLE e1(x,y,UNIQUE(y,substr(x,1,5)))',
                1,
                'expressions prohibited in PRIMARY KEY and UNIQUE constraints',
                null,
                'substr(x,1,5)',
                'e1',
                null,
                null,
                [],
                false,
            ],
            [
                'indexexpr1-330',
                'PRIMARY KEY table constraints do not admit expression terms',
                'CREATE TABLE e1(x,y,PRIMARY KEY(y,substr(x,1,5)))',
                1,
                'expressions prohibited in PRIMARY KEY and UNIQUE constraints',
                null,
                'substr(x,1,5)',
                'e1',
                null,
                null,
                [],
                false,
            ],
            [
                'indexexpr1-331',
                'WITHOUT ROWID primary keys do not admit expression terms',
                'CREATE TABLE e1(x,y,PRIMARY KEY(y,substr(x,1,5))) WITHOUT ROWID',
                1,
                'expressions prohibited in PRIMARY KEY and UNIQUE constraints',
                null,
                'substr(x,1,5)',
                'e1',
                null,
                null,
                [],
                false,
            ],
            [
                'indexexpr1-340',
                'foreign-key column lists reject expression terms at parse time',
                'CREATE TABLE e1(x,y,FOREIGN KEY(substr(y,1,5)) REFERENCES t1)',
                1,
                'near "(": syntax error',
                null,
                'substr(y,1,5)',
                'e1',
                null,
                null,
                [],
                false,
            ],
            [
                'indexexpr1-400',
                'unique expression indexes preserve text-cast ordering and integrity',
                'CREATE UNIQUE INDEX t3abc ON t3(CAST(a AS text), b, substr(c,1,3))',
                0,
                '',
                't3abc',
                'CAST(a AS text), b, substr(c,1,3)',
                't3',
                'ok',
                null,
                [
                    [1],
                    [10],
                ],
                true,
            ],
            [
                'indexexpr1-410',
                'duplicate rows are rejected by the unique expression index',
                'INSERT INTO t3 SELECT * FROM t3 WHERE rowid=10',
                1,
                'UNIQUE constraint failed: index t3abc',
                't3abc',
                'CAST(a AS text), b, substr(c,1,3)',
                't3',
                'ok',
                [10, 'ab000axyz', 'sample10'],
                null,
                true,
            ],
        ];

        $rows = [];
        for ($case = 1; $case <= $cases; $case++) {
            [$section, $scenario, $sql, $code, $message, $indexName, $expression, $table, $integrity, $insertRow, $expectedRows, $usesIndex] = $templates[($case - 1) % count($templates)];
            $rows[] = [
                'source' => 'indexexpr1.test indexexpr1-300 through indexexpr1-410',
                'case' => $case,
                'upstream_section' => $section,
                'scenario' => $scenario,
                'sql' => $sql,
                'result_code' => $code,
                'message' => $message,
                'index_name' => $indexName,
                'expression' => $expression,
                'table_name' => $table,
                'integrity' => $integrity,
                'insert_row' => $insertRow,
                'duplicate_row' => $section === 'indexexpr1-410' ? $insertRow : null,
                'expected_rows' => $expectedRows,
                'uses_expression_index' => $usesIndex,
            ];
        }

        return $rows;
    }

    /**
     * @return list<array{source:string,case:int,upstream_section:string,scenario:string,index_name:string,integer_value:int,float_value:float,comparison:string,ordered_rowids:list<int>,selected_labels:list<string>,integrity:string,precision_boundary:string}>
     */
    public static function numindexLargeNumericKeyCases(int $cases = 1000): array
    {
        if ($cases < 1) {
            throw new \InvalidArgumentException('SQLite numindex1 dynamic corpus requires at least one case');
        }

        $templates = [
            [
                'numindex1-1.1',
                'delete rounded REAL duplicate between adjacent large integer index keys',
                't1b',
                356282677878746339,
                356282677878746339.0,
                'integer-real-equal-before-delete',
                [0, 100],
                ['lower-integer', 'surviving-integer'],
                '53-bit-plus integer/REAL comparison boundary',
            ],
            [
                'numindex1-1.2',
                'store integer and REAL values around one shifted 58-bit key',
                't2-auto',
                288230376151711744,
                2.88230376151712e+17,
                'typeof-preserved',
                [1, 2, 3],
                ['b:integer', 'c:real', 'd:integer'],
                '1<<58 storage class split',
            ],
            [
                'numindex1-1.3',
                'self-join equality treats rounded REAL value equal to base integer only',
                't2-auto',
                288230376151711744,
                2.88230376151712e+17,
                'b==c and b<>d',
                [1, 2, 3],
                ['b==b', 'b==c', 'b<>d', 'c==b', 'c==c', 'c<>d', 'd<>b', 'd<>c', 'd==d'],
                'large numeric equality matrix',
            ],
            [
                'numindex1-2.1',
                'delete one hundred rounded REAL duplicates leaving adjacent integer sentinels',
                't1b',
                10000000000000005,
                10000000000000004.0,
                'sentinel-integers-survive-duplicate-delete',
                [37, 23],
                ['low-sentinel', 'high-sentinel'],
                '10000000000000004 REAL duplicate run',
            ],
            [
                'numindex1-3.2',
                'ORDER BY index over mixed integer and rounded REAL values preserves SQLite numeric order',
                't1b',
                100000000000000005,
                100000000000000005.0,
                'mixed-integer-real-order',
                [1, 2, 4, 5, 6, 8, 9, 10, 12, 14, 15, 16, 18, 20, 13, 19, 17, 3, 11, 7],
                ['bulk-real-prefix', 'integer-tail'],
                '100000000000000000 order boundary',
            ],
        ];

        $rows = [];
        for ($case = 1; $case <= $cases; $case++) {
            [$section, $scenario, $indexName, $integerValue, $floatValue, $comparison, $orderedRowids, $labels, $boundary] = $templates[($case - 1) % count($templates)];
            $rows[] = [
                'source' => 'numindex1.test numindex1-1.1 through numindex1-3.2',
                'case' => $case,
                'upstream_section' => $section,
                'scenario' => $scenario,
                'index_name' => $indexName,
                'integer_value' => $integerValue,
                'float_value' => $floatValue,
                'comparison' => $comparison,
                'ordered_rowids' => $orderedRowids,
                'selected_labels' => $labels,
                'integrity' => 'ok',
                'precision_boundary' => $boundary,
            ];
        }

        return $rows;
    }

    /**
     * @return list<array{source:string,case:int,upstream_section:string,scenario:string,table_name:string,index_name:string,partial_predicate:string,mutation:string,result_rows:list<array<int,mixed>>,expected_error:string|null,uses_index:bool,detail:string,integrity:string,stat_rows:list<array<int,mixed>>,batch:int}>
     */
    public static function index7PartialUniqueAndPlannerCases(int $cases = 1000): array
    {
        if ($cases < 1) {
            throw new \InvalidArgumentException('SQLite index7 partial unique planner corpus requires at least one case');
        }

        $templates = [
            [
                'index7-3.1/3.2',
                'partial UNIQUE index rejects a duplicate non-excluded key',
                't3',
                't3a',
                'a<>999',
                'INSERT INTO t3(a,b) VALUES(150, ?)',
                [],
                'UNIQUE constraint failed: t3.a',
                true,
                'UNIQUE INDEX t3a ON t3(a) WHERE a<>999',
                [[199, 160, 39]],
                'ok',
            ],
            [
                'index7-3.3/3.4',
                'partial UNIQUE index admits repeated excluded sentinel rows',
                't3',
                't3a',
                'a<>999',
                'INSERT INTO t3(a,b) VALUES(999, ?), (999, ?)',
                [[162]],
                null,
                false,
                'COUNT rows where a=999 after duplicate excluded inserts',
                [[201, 160, 41]],
                'ok',
            ],
            [
                'index7-5.0',
                'database-name qualifier is ignored inside partial-index predicate',
                't3',
                't3b',
                'qualified.t3.b BETWEEN 5 AND 10',
                'CREATE INDEX t3b ON t3(b) WHERE qualified.t3.b BETWEEN 5 AND 10',
                [[6], [6]],
                null,
                true,
                'sqlite_stat1 row for t3b has six qualifying rows',
                [[6, 6]],
                'ok',
            ],
            [
                'index7-6.1/6.2',
                'partial index on joined table does not filter a nonqualifying joined row',
                't4',
                'i4',
                "d='xyz'",
                "CREATE INDEX i4 ON t4(c) WHERE d='xyz'",
                [[1, 'xyz', 'abc', 'not xyz']],
                null,
                false,
                'subquery result joins t4 row even though partial predicate is false',
                [[1, 1]],
                'ok',
            ],
            [
                'index7-6.3/6.4',
                'view predicate matching the partial index uses the indexed table route',
                't4',
                'i4',
                "d='xyz'",
                'CREATE VIEW v4 AS SELECT * FROM t4; SELECT * FROM v4 WHERE d=? AND c=?',
                [['def', 'xyz']],
                null,
                true,
                'SEARCH t4 USING INDEX i4 (c=?)',
                [[1, 1]],
                'ok',
            ],
            [
                'index7-6.5',
                'bad parameter token in partial-index predicate is a syntax error',
                't5',
                't5a',
                'a=#1',
                'CREATE INDEX t5a ON t5(a) WHERE a=#1',
                [],
                'near "#1": syntax error',
                false,
                'partial-index parser rejects hash-number parameter token',
                [],
                null,
            ],
            [
                'index7-7.0/7.1',
                'IS TRUE query remains unchanged after adding an IS NOT TRUE partial index',
                't6',
                'i6',
                'y IS NOT TRUE',
                'CREATE INDEX i6 ON t6(x) WHERE y IS NOT TRUE',
                [[1, 1]],
                null,
                false,
                'SELECT * FROM t6 WHERE y IS TRUE ORDER BY x',
                [[2, 1]],
                'ok',
            ],
            [
                'index7-8.1',
                'incomplete stat1 on tiny table still admits partial covering index lookup',
                't1',
                't1y',
                'y IS NOT NULL',
                'ANALYZE after inserting only null y rows',
                [[1]],
                null,
                true,
                'SEARCH t1 USING COVERING INDEX t1y (y=?)',
                [[2, 0]],
                'ok',
            ],
        ];

        $rows = [];
        for ($case = 1; $case <= $cases; $case++) {
            [$section, $scenario, $table, $index, $predicate, $mutation, $resultRows, $error, $usesIndex, $detail, $statRows, $integrity] = $templates[($case - 1) % count($templates)];
            $batch = intdiv($case - 1, count($templates)) + 1;
            $dynamicRows = array_map(
                static function (array $row) use ($batch): array {
                    return array_map(
                        static fn (mixed $value): mixed => is_int($value) && $value > 10 ? $value + (($batch - 1) * 1000) : $value,
                        $row,
                    );
                },
                $resultRows,
            );

            $rows[] = [
                'source' => 'index7.test index7-3.1 through index7-8.1',
                'case' => $case,
                'upstream_section' => $section,
                'scenario' => $scenario,
                'table_name' => $table,
                'index_name' => $index,
                'partial_predicate' => $predicate,
                'mutation' => $mutation,
                'result_rows' => $dynamicRows,
                'expected_error' => $error,
                'uses_index' => $usesIndex,
                'detail' => $detail,
                'integrity' => $integrity,
                'stat_rows' => $statRows,
                'batch' => $batch,
            ];
        }

        return $rows;
    }

    /**
     * @return list<array{source:string,case:int,upstream_section:string,scenario:string,automatic_index_enabled:bool,join_shape:string,autoindex_target:string,expected_rows:list<array<int,mixed>>,step_count:int|null,autoindex_inserts:int,detail:string,uses_automatic_index:bool,mutation:string|null,integrity:string}>
     */
    public static function autoindex1AutomaticIndexPlannerCases(int $cases = 1000): array
    {
        if ($cases < 1) {
            throw new \InvalidArgumentException('SQLite autoindex1 dynamic corpus requires at least one case');
        }

        $joinedRows = [];
        for ($i = 1; $i <= 8; $i++) {
            $joinedRows[] = [$i * 11, 900 + ($i * 11)];
        }

        $templates = [
            [
                'autoindex1-100/110',
                'join builds transient covering index only when PRAGMA automatic_index is enabled',
                false,
                't1 JOIN t2 ON a=c',
                't2(c)',
                $joinedRows,
                63,
                0,
                'SCAN t1; SCAN t2',
                false,
                null,
            ],
            [
                'autoindex1-110/113',
                'enabled join emits SQLITE_WARNING_AUTOINDEX and probes t2(c)',
                true,
                't1 JOIN t2 ON a=c',
                't2(c)',
                $joinedRows,
                7,
                7,
                'SEARCH t2 USING AUTOMATIC COVERING INDEX (c=?)',
                true,
                null,
            ],
            [
                'autoindex1-200/210',
                'correlated scalar subquery builds automatic index after ANALYZE statistics favor it',
                true,
                'SELECT b,(SELECT d FROM t2 WHERE c=a) FROM t1',
                't2(c)',
                $joinedRows,
                7,
                7,
                'CORRELATED SCALAR SUBQUERY; SEARCH t2 USING AUTOMATIC COVERING INDEX (c=?)',
                true,
                null,
            ],
            [
                'autoindex1-300/310',
                'automatic index snapshot remains stable while the joined table is updated mid-scan',
                true,
                't1 CROSS JOIN t2 ON c=a',
                't2(c)',
                $joinedRows,
                null,
                7,
                'SEARCH t2 USING AUTOMATIC COVERING INDEX (c=?)',
                true,
                'UPDATE t2 SET d=d+1 during each output row',
            ],
            [
                'autoindex1-400/401',
                'ten-way unindexed self join relies on automatic indexes for chained equality probes',
                true,
                't4 x1..x10 chained joins',
                't4(a)',
                [[4087]],
                null,
                9,
                'SEARCH x2..x10 USING AUTOMATIC COVERING INDEX (a=?)',
                true,
                null,
            ],
            [
                'autoindex1-500.1/501/502',
                'automatic indexes are limited to correlated IN subqueries and not constant rowid probes',
                true,
                't501 rowid IN subquery',
                't502(y)',
                [],
                null,
                1,
                'CORRELATED LIST SUBQUERY uses AUTOMATIC COVERING INDEX only when outer t501.b is referenced',
                true,
                null,
            ],
            [
                'autoindex1-600a',
                'materialized subquery for outer join receives an automatic covering index on sheep_no',
                true,
                'sheep LEFT JOIN materialized owner view',
                'y(sheep_no)',
                [],
                null,
                1,
                'MATERIALIZE y; SEARCH y USING AUTOMATIC COVERING INDEX (sheep_no=?) LEFT-JOIN',
                true,
                null,
            ],
            [
                'autoindex1-700a',
                'single-table ORDER BY does not create an automatic index and uses a temporary btree sort',
                true,
                'single table filter plus ORDER BY',
                '',
                [],
                null,
                0,
                'SCAN t5; USE TEMP B-TREE FOR ORDER BY',
                false,
                null,
            ],
            [
                'autoindex1-900/901',
                'aggregate view and grouped subquery joins may build automatic covering indexes',
                true,
                'join against aggregate subquery or view',
                'agglabels(message_id)',
                [],
                null,
                1,
                'SEARCH agglabels USING AUTOMATIC COVERING INDEX; SEARCH agg2 USING AUTOMATIC COVERING INDEX',
                true,
                null,
            ],
            [
                'autoindex1-920/1020',
                'NULL-sensitive joins avoid using an IS term as a left-join RHS index driver',
                true,
                'VALUES source and LEFT JOIN IS comparison',
                '',
                [[5, 0, 9], [5, 0, 9], [5, 0, 9]],
                null,
                0,
                'LEFT JOIN IS term remains residual so NULL does not incorrectly match notnull',
                false,
                null,
            ],
            [
                'autoindex1-1110/1120',
                'unary-plus join predicates preserve LEFT JOIN null-extension behavior',
                true,
                't1/t2/t3 LEFT JOIN with unary-plus equality',
                '',
                [[1, 1, 1, 2, null, null]],
                null,
                0,
                'LEFT JOIN keeps t3 columns NULL when ON expression is false',
                false,
                null,
            ],
            [
                'autoindex1-1210/1211',
                'WITHOUT ROWID table can be the target of an automatic covering index in a left join',
                true,
                'view t2 LEFT OUTER JOIN t1 WITHOUT ROWID ON b=c',
                't1(b)',
                [[null, null, null, 5, 55], [1, 3, 91, 3, 33], [1, 4, 92, 4, 44]],
                null,
                1,
                'SEARCH t1 USING AUTOMATIC COVERING INDEX (b=?)',
                true,
                null,
            ],
        ];

        $rows = [];
        for ($case = 1; $case <= $cases; $case++) {
            [$section, $scenario, $enabled, $shape, $target, $expectedRows, $stepCount, $autoindexInserts, $detail, $usesAutomaticIndex, $mutation] = $templates[($case - 1) % count($templates)];
            $rows[] = [
                'source' => 'autoindex1.test autoindex1-100 through autoindex-1211',
                'case' => $case,
                'upstream_section' => $section,
                'scenario' => $scenario,
                'automatic_index_enabled' => $enabled,
                'join_shape' => $shape,
                'autoindex_target' => $target,
                'expected_rows' => $expectedRows,
                'step_count' => $stepCount,
                'autoindex_inserts' => $autoindexInserts,
                'detail' => $detail,
                'uses_automatic_index' => $usesAutomaticIndex,
                'mutation' => $mutation,
                'integrity' => 'ok',
            ];
        }

        return $rows;
    }

    /**
     * @return list<array{source:string,case:int,upstream_section:string,scenario:string,query_shape:string,declared_index:string,declared_index_selectivity:string,automatic_index_allowed:bool,uses_automatic_index:bool,uses_declared_index:bool,uses_skip_scan:bool,uses_bloom_filter:bool,recursive_cte:bool,detail:string,integrity:string}>
     */
    public static function autoindex3DeclaredIndexShadowCases(int $cases = 1000): array
    {
        if ($cases < 1) {
            throw new \InvalidArgumentException('SQLite autoindex3 dynamic corpus requires at least one case');
        }

        $templates = [
            ['autoindex3-100', 'do not create an automatic index that shadows existing equality indexes', 'SELECT * FROM t1, t2 WHERE d=b', 't1b/t2d', '10000 500', false, false, true, false, false, false, 'SCAN t1; SEARCH t2 USING INDEX t2d (d=?)'],
            ['autoindex3-110', 'non-equality declared-index constraint still allows automatic index on residual equality', 'SELECT * FROM t1, t2 WHERE d>b AND x=y', 't1b/t2d', '10000 500', true, true, false, false, false, false, 'SEARCH t2 USING AUTOMATIC COVERING INDEX (y=?)'],
            ['autoindex3-120', 'reverse non-equality declared-index constraint still allows automatic index', 'SELECT * FROM t1, t2 WHERE d<b AND x=y', 't1b/t2d', '10000 500', true, true, false, false, false, false, 'SEARCH t2 USING AUTOMATIC COVERING INDEX (y=?)'],
            ['autoindex3-130', 'IS NULL declared-index probe does not shadow automatic index on independent equality', 'SELECT * FROM t1, t2 WHERE d IS NULL AND x=y', 't1b/t2d', '10000 500', true, true, true, false, false, false, 'SEARCH t2 USING INDEX t2d (d=?); SEARCH t1 USING AUTOMATIC COVERING INDEX (x=?)'],
            ['autoindex3-140', 'IN expression over declared-index column still permits automatic index on residual equality', 'SELECT * FROM t1, t2 WHERE d IN (5,b) AND x=y', 't1b/t2d', '10000 500', true, true, true, false, false, false, 'SEARCH t2 USING INDEX t2d (d=?); SEARCH t1 USING AUTOMATIC COVERING INDEX (x=?)'],
            ['autoindex3-220', 'skip-scan on a declared composite index is not automatically better than a transient covering index', 'SELECT count(*) FROM u, v WHERE u.b=v.b AND v.e>34', 'uab/vbde/ve', '40000 400 1', true, true, true, false, true, false, 'SEARCH v USING INDEX ve (e>?); BLOOM FILTER ON u (b=?); SEARCH u USING AUTOMATIC COVERING INDEX (b=?)'],
            ['autoindex3-310 setup', 'recursive CTE setup uses declared pid/rx index for seed step', 'WITH RECURSIVE children seed SELECT cid FROM t2 WHERE pid=?1 AND rx=?2', 'x1/sqlite_autoindex_t2_1', '500000 250 250', false, false, true, false, false, true, 'CO-ROUTINE children; SETUP; SEARCH t2 USING INDEX x1 (pid=? AND rx=?)'],
            ['autoindex3-310 recursive', 'recursive CTE step reuses declared pid/rx index instead of a low-selectivity automatic index', 'SELECT cid FROM t2 JOIN children ON t2.pid=children.id AND rx=?2', 'x1/sqlite_autoindex_t2_1', '500000 250 250', false, false, true, false, false, true, 'RECURSIVE STEP; SCAN children; SEARCH t2 USING INDEX x1 (pid=? AND rx=?)'],
        ];

        $rows = [];
        for ($case = 1; $case <= $cases; $case++) {
            [$section, $scenario, $queryShape, $declaredIndex, $selectivity, $automaticIndexAllowed, $usesAutomaticIndex, $usesDeclaredIndex, $usesSkipScan, $usesBloomFilter, $recursiveCte, $detail] = $templates[($case - 1) % count($templates)];
            $rows[] = [
                'source' => 'autoindex3.test autoindex3-100 through autoindex3-310',
                'case' => $case,
                'upstream_section' => $section,
                'scenario' => $scenario,
                'query_shape' => $queryShape,
                'declared_index' => $declaredIndex,
                'declared_index_selectivity' => $selectivity,
                'automatic_index_allowed' => $automaticIndexAllowed,
                'uses_automatic_index' => $usesAutomaticIndex,
                'uses_declared_index' => $usesDeclaredIndex,
                'uses_skip_scan' => $usesSkipScan,
                'uses_bloom_filter' => $usesBloomFilter,
                'recursive_cte' => $recursiveCte,
                'detail' => $detail,
                'integrity' => 'ok',
            ];
        }

        return $rows;
    }

    /**
     * @return list<array{source:string,case:int,upstream_section:string,scenario:string,page_size:int,row_count:int,blob_bytes:int|null,cache_size:int|null,index_name:string,unique:bool,duplicate_value:int|null,expected_error:string|null,integrity:string,sorter_pages:int,spill_batches:int,table_reset:bool}>
     */
    public static function index4CreateIndexStressCases(int $cases = 1200): array
    {
        if ($cases < 1) {
            throw new \InvalidArgumentException('SQLite index4 dynamic corpus requires at least one case');
        }

        $templates = [
            ['index4-1.2', 'bulk create index over 65536 fixed-width blob rows', 1024, 65536, 102, null, 'i1', false, null, null, 6554, 64, false],
            ['index4-1.4', 'limited cache create index over existing bulk blob table', 1024, 65536, 102, 10, 'i2', false, null, null, 6554, 128, false],
            ['index4-1.6', 'create index after mixed text null and growing blob payloads', 1024, 256, 5202, null, 'i1', false, null, null, 1301, 32, true],
            ['index4-1.7', 'create index on one-row table after transaction reset', 1024, 1, null, null, 'i1', false, null, null, 1, 1, true],
            ['index4-1.8', 'create index on empty table after transaction reset', 1024, 0, null, null, 'i1', false, null, null, 0, 0, true],
            ['index4-2.2', 'unique create index rejects duplicate integer key', 1024, 5, null, null, 'i3', true, 35, 'UNIQUE constraint failed: t2.x', 1, 1, false],
        ];

        $rows = [];
        for ($case = 1; $case <= $cases; $case++) {
            [$section, $scenario, $pageSize, $rowCount, $blobBytes, $cacheSize, $indexName, $unique, $duplicateValue, $expectedError, $sorterPages, $spillBatches, $tableReset] = $templates[($case - 1) % count($templates)];
            $rows[] = [
                'source' => 'index4.test index4-1.1 through index4-2.2',
                'case' => $case,
                'upstream_section' => $section,
                'scenario' => $scenario,
                'page_size' => $pageSize,
                'row_count' => $rowCount,
                'blob_bytes' => $blobBytes,
                'cache_size' => $cacheSize,
                'index_name' => $indexName,
                'unique' => $unique,
                'duplicate_value' => $duplicateValue,
                'expected_error' => $expectedError,
                'integrity' => $expectedError === null ? 'ok' : 'unchanged-after-error',
                'sorter_pages' => $sorterPages,
                'spill_batches' => $spillBatches,
                'table_reset' => $tableReset,
            ];
        }

        return $rows;
    }

    /**
     * @return list<array{source:string,case:int,upstream_section:string,scenario:string,join_type:string,on_clause:string,where_clause:string,automatic_index_enabled:bool,optimization_enabled:bool,uses_automatic_partial_index:bool,result_rows:list<array{a:int|null,b:int|null,x:int|null,y:int|null}>,null_extended_rows:int,matched_rows:int,integrity:string}>
     */
    public static function autoindex4PartialJoinCases(int $cases = 1200): array
    {
        if ($cases < 1) {
            throw new \InvalidArgumentException('SQLite autoindex4 dynamic corpus requires at least one case');
        }

        $templates = [
            [
                'autoindex4-1.0',
                'cross join with equality filters builds a transient partial index and preserves ORDER BY +b',
                'JOIN',
                'a=234 AND x=987',
                '',
                true,
                true,
                [
                    [234, 2, 987, 3],
                    [234, 2, 987, 1],
                    [234, 3, 987, 3],
                    [234, 3, 987, 1],
                ],
            ],
            [
                'autoindex4-1.1',
                'cross join with impossible filtered right side returns no rows',
                'JOIN',
                'a=234 AND x=555',
                '',
                true,
                true,
                [],
            ],
            [
                'autoindex4-1.2',
                'left join keeps null-extended rows when ON clause is impossible',
                'LEFT JOIN',
                'a=234 AND x=555',
                '',
                true,
                true,
                [
                    [123, 1, null, null],
                    [234, 2, null, null],
                    [234, 3, null, null],
                    [345, 4, null, null],
                ],
            ],
            [
                'autoindex4-1.3',
                'left join applies WHERE after preserving ON-clause null extension',
                'LEFT JOIN',
                'x=555',
                'a=234',
                true,
                true,
                [
                    [234, 2, null, null],
                    [234, 3, null, null],
                ],
            ],
            [
                'autoindex4-1.4',
                'left join WHERE clause rejects null-extended right side',
                'LEFT JOIN',
                '1',
                'a=234 AND x=555',
                true,
                true,
                [],
            ],
            [
                'autoindex4-2.0',
                'correlated scalar subquery counts join matches through automatic partial indexes',
                'SCALAR SUBQUERY',
                'a=e AND x=f',
                '',
                true,
                true,
                [
                    [123, 654, 1, null],
                    [555, 444, 0, null],
                    [234, 987, 4, null],
                ],
            ],
            [
                'autoindex4-3.0',
                'left join with ORDER BY keeps parent rows when partial-index term is false',
                'LEFT JOIN',
                "A.Name = Items.ItemName AND Items.ItemName = 'dummy'",
                "Items.Name = 'Parent'",
                true,
                true,
                [
                    [null, null, null, null],
                    [null, null, null, null],
                ],
            ],
            [
                'autoindex4-4.1',
                'left join WHERE y=4 OR y IS NULL keeps only matching and null-extended rows',
                'LEFT JOIN',
                'a=x',
                'y=4 OR y IS NULL',
                true,
                true,
                [
                    [3, 4, 3, 4],
                ],
            ],
            [
                'autoindex4-4.2',
                'left join coalesce predicate preserves null-extension and matching row',
                'LEFT JOIN',
                'a=x AND y=4',
                'coalesce(y,4)==4',
                true,
                true,
                [
                    [1, 2, null, null],
                    [3, 4, 3, 4],
                ],
            ],
            [
                'autoindex4-4.3',
                'inner join y=4 OR y IS NULL returns only matching row',
                'JOIN',
                'a=x',
                'y=4 OR y IS NULL',
                true,
                true,
                [
                    [3, 4, 3, 4],
                ],
            ],
            [
                'autoindex4-4.5.1',
                'left join with NULL left key preserves null-extended row',
                'LEFT JOIN',
                'a=x',
                'y=4 OR y IS NULL',
                true,
                true,
                [
                    [3, 4, 3, 4],
                    [null, 4, null, null],
                ],
            ],
            [
                'autoindex4-4.5.2',
                'empty NOT IN predicate admits both matched and null-extended left rows',
                'LEFT JOIN',
                'a=x',
                'y NOT IN ()',
                true,
                true,
                [
                    [1, 2, 1, 2],
                    [3, 4, 3, 4],
                    [null, 4, null, null],
                ],
            ],
            [
                'autoindex4-4.5.3',
                'empty subquery NOT IN predicate admits both matched and null-extended left rows',
                'LEFT JOIN',
                'a=x',
                'y NOT IN (SELECT 1 WHERE false)',
                true,
                true,
                [
                    [1, 2, 1, 2],
                    [3, 4, 3, 4],
                    [null, 4, null, null],
                ],
            ],
            [
                'autoindex4-4.6',
                'left join with nullable right side and coalesce predicate preserves null-extension',
                'LEFT JOIN',
                'a=x AND y=4',
                'coalesce(y,4)==4',
                true,
                true,
                [
                    [1, 2, null, null],
                    [3, 4, 3, 4],
                ],
            ],
            [
                'autoindex4-4.7',
                'inner join with NULL keys does not match NULL to NULL',
                'JOIN',
                'a=x',
                'y=4 OR y IS NULL',
                true,
                true,
                [
                    [3, 4, 3, 4],
                ],
            ],
        ];

        $rows = [];
        for ($case = 1; $case <= $cases; $case++) {
            [$section, $scenario, $joinType, $onClause, $whereClause, $automaticIndexEnabled, $optimizationEnabled, $resultRows] = $templates[($case - 1) % count($templates)];
            $nullExtendedRows = 0;
            $matchedRows = 0;
            foreach ($resultRows as $row) {
                if ($row[2] === null && $row[3] === null) {
                    $nullExtendedRows++;
                } else {
                    $matchedRows++;
                }
            }

            $rows[] = [
                'source' => 'autoindex4.test autoindex4-1.0 through autoindex4-4.8',
                'case' => $case,
                'upstream_section' => $section,
                'scenario' => $scenario,
                'join_type' => $joinType,
                'on_clause' => $onClause,
                'where_clause' => $whereClause,
                'automatic_index_enabled' => $automaticIndexEnabled,
                'optimization_enabled' => $optimizationEnabled,
                'uses_automatic_partial_index' => $automaticIndexEnabled && $optimizationEnabled && $resultRows !== [],
                'result_rows' => array_map(
                    static fn (array $row): array => ['a' => $row[0], 'b' => $row[1], 'x' => $row[2], 'y' => $row[3]],
                    $resultRows,
                ),
                'null_extended_rows' => $nullExtendedRows,
                'matched_rows' => $matchedRows,
                'integrity' => 'ok',
            ];
        }

        return $rows;
    }

    /**
     * @return list<array{source:string,case:int,upstream_section:string,scenario:string,table:string,primary_key:list<string>,redundant_primary_key:list<string>,unique_constraints:list<list<string>>,index_info:list<array{seqno:int,cid:int,name:string,key:int,collation:string}>,index_list:list<array{name:string,unique:int,origin:string}>,query:string,result_rows:list<array<int,mixed>>,detail:string,error:string|null,integrity:string,target_a:int|null,target_b:int|null,expected_c:string|null}>
     */
    public static function withoutRowidRedundantPrimaryKeyCases(int $cases = 1000): array
    {
        if ($cases < 1) {
            throw new \InvalidArgumentException('SQLite WITHOUT ROWID dynamic corpus requires at least one case');
        }

        $baseIndexInfo = [
            ['seqno' => 0, 'cid' => 0, 'name' => 'a', 'key' => 1, 'collation' => 'BINARY'],
            ['seqno' => 1, 'cid' => 1, 'name' => 'b', 'key' => 1, 'collation' => 'BINARY'],
            ['seqno' => 2, 'cid' => 2, 'name' => 'c', 'key' => 1, 'collation' => 'BINARY'],
            ['seqno' => 3, 'cid' => 3, 'name' => 'd', 'key' => 1, 'collation' => 'BINARY'],
            ['seqno' => 4, 'cid' => 4, 'name' => 'e', 'key' => 0, 'collation' => 'BINARY'],
        ];
        $baseIndexList = [['name' => 'sqlite_autoindex_t1_1', 'unique' => 1, 'origin' => 'pk'], ['name' => 't1a', 'unique' => 0, 'origin' => 'c']];

        $rows = [];
        for ($case = 1; $case <= $cases; $case++) {
            $a = (($case - 1) % 1000) + 1;
            $b = $a + 1000;
            $c = 'x' . $a . 'y';
            $rows[] = [
                'source' => 'without_rowid6.test',
                'case' => $case,
                'upstream_section' => 'without_rowid6-100/140',
                'scenario' => 'redundant PRIMARY KEY columns are collapsed in WITHOUT ROWID storage and duplicate-column auxiliary indexes remain usable for row ' . $a,
                'table' => 't1',
                'primary_key' => ['a', 'b', 'c', 'd'],
                'redundant_primary_key' => ['a', 'b', 'c', 'a', 'b', 'c', 'd', 'a', 'b', 'c'],
                'unique_constraints' => [['b', 'b']],
                'index_info' => $baseIndexInfo,
                'index_list' => $baseIndexList,
                'query' => 'SELECT c FROM t1 WHERE a=' . $a . '; SELECT c FROM t1 WHERE b=' . $b,
                'result_rows' => [[$c], [$c]],
                'detail' => 'SEARCH t1 USING PRIMARY KEY (a=?); SEARCH t1 USING INDEX t1a (b=?)',
                'error' => null,
                'integrity' => 'ok',
                'target_a' => $a,
                'target_b' => $b,
                'expected_c' => $c,
            ];
        }

        return $rows;
    }

    /**
     * @return list<array{source:string,upstream_section:string,scenario:string,table:string,primary_key:list<string>,redundant_primary_key:list<string>,unique_constraints:list<list<string>>,index_info:list<array{seqno:int,cid:int,name:string,key:int,collation:string}>,index_list:list<array{name:string,unique:int,origin:string}>,query:string,result_rows:list<array<int,mixed>>,detail:string,error:string|null,integrity:string}>
     */
    public static function withoutRowidRedundantPrimaryKeySectionCases(): array
    {
        $templates = [
            [
                'without_rowid6-200/220',
                'UNIQUE column promoted to PRIMARY KEY keeps the second autoindex as pk and preserves b-range order',
                't1',
                ['b'],
                ['b'],
                [['a'], ['b'], ['c']],
                [
                    ['seqno' => 0, 'cid' => 1, 'name' => 'b', 'key' => 1, 'collation' => 'BINARY'],
                    ['seqno' => 1, 'cid' => 0, 'name' => 'a', 'key' => 0, 'collation' => 'BINARY'],
                    ['seqno' => 2, 'cid' => 2, 'name' => 'c', 'key' => 0, 'collation' => 'BINARY'],
                ],
                [['name' => 'sqlite_autoindex_t1_2', 'unique' => 1, 'origin' => 'pk']],
                'SELECT a FROM t1 WHERE b>3 ORDER BY b',
                [[4], [1]],
                'SEARCH t1 USING PRIMARY KEY (b>?)',
                null,
            ],
            [
                'without_rowid6-300/320',
                'explicit UNIQUE(b) duplicate is coalesced when b is the WITHOUT ROWID primary key',
                't1',
                ['b'],
                ['b'],
                [['a'], ['c'], ['b']],
                [
                    ['seqno' => 0, 'cid' => 1, 'name' => 'b', 'key' => 1, 'collation' => 'BINARY'],
                    ['seqno' => 1, 'cid' => 0, 'name' => 'a', 'key' => 0, 'collation' => 'BINARY'],
                    ['seqno' => 2, 'cid' => 2, 'name' => 'c', 'key' => 0, 'collation' => 'BINARY'],
                ],
                [['name' => 'sqlite_autoindex_t1_2', 'unique' => 1, 'origin' => 'pk']],
                'SELECT a FROM t1 WHERE b>3 ORDER BY b',
                [[4], [1]],
                'SEARCH t1 USING PRIMARY KEY (b>?)',
                null,
            ],
            [
                'without_rowid6-500/520',
                'composite UNIQUE(b,c) duplicate is coalesced into the WITHOUT ROWID primary key',
                't1',
                ['b', 'c'],
                ['b', 'c'],
                [['b', 'c']],
                [
                    ['seqno' => 0, 'cid' => 1, 'name' => 'b', 'key' => 1, 'collation' => 'BINARY'],
                    ['seqno' => 1, 'cid' => 2, 'name' => 'c', 'key' => 1, 'collation' => 'BINARY'],
                    ['seqno' => 2, 'cid' => 0, 'name' => 'a', 'key' => 0, 'collation' => 'BINARY'],
                ],
                [['name' => 'sqlite_autoindex_t1_1', 'unique' => 1, 'origin' => 'pk']],
                'SELECT a FROM t1 WHERE b>3 ORDER BY b',
                [[4], [1]],
                'SEARCH t1 USING PRIMARY KEY (b>?)',
                null,
            ],
            [
                'without_rowid6-600',
                'rowid is not a valid primary-key column name in WITHOUT ROWID declarations',
                't6',
                ['a', 'rowid', 'b'],
                ['a', 'rowid', 'b'],
                [],
                [],
                [],
                'CREATE TABLE t6(a,b,c,PRIMARY KEY(a,rowid,b)) WITHOUT ROWID',
                [],
                'parse primary key column list',
                'no such column: rowid',
            ],
            [
                'without_rowid7-1.0/1.1',
                'redundant primary-key columns with NOCASE collation reject duplicate logical keys',
                't1',
                ['a', 'b'],
                ['a', 'a', 'b'],
                [],
                [
                    ['seqno' => 0, 'cid' => 0, 'name' => 'a', 'key' => 1, 'collation' => 'BINARY'],
                    ['seqno' => 1, 'cid' => 1, 'name' => 'b', 'key' => 1, 'collation' => 'NOCASE'],
                ],
                [['name' => 'sqlite_autoindex_t1_1', 'unique' => 1, 'origin' => 'pk']],
                "INSERT INTO t1 VALUES(1, 'one'), (1, 'ONE')",
                [],
                'PRIMARY KEY(a,b COLLATE nocase)',
                'UNIQUE constraint failed: t1.a, t1.b',
            ],
            [
                'without_rowid7-2.0/2.4',
                'collated duplicate primary-key expression exposes both key slots in pragma index metadata',
                't2',
                ['a', 'a'],
                ['a COLLATE nocase', 'a'],
                [],
                [
                    ['seqno' => 0, 'cid' => 0, 'name' => 'a', 'key' => 1, 'collation' => 'NOCASE'],
                    ['seqno' => 1, 'cid' => 0, 'name' => 'a', 'key' => 1, 'collation' => 'BINARY'],
                    ['seqno' => 2, 'cid' => 1, 'name' => 'b', 'key' => 0, 'collation' => 'BINARY'],
                ],
                [['name' => 'sqlite_autoindex_t2_1', 'unique' => 1, 'origin' => 'pk']],
                'PRAGMA index_info(t2); PRAGMA index_xinfo(t2)',
                [['one']],
                'PRIMARY KEY(a COLLATE nocase, a)',
                null,
            ],
            [
                'without_rowid7-3.1/3.6',
                'missing custom collations surface while reading and creating indexes on WITHOUT ROWID tables',
                't1',
                ['a'],
                ['a COLLATE mysort'],
                [['b COLLATE mysort2'], ['1']],
                [
                    ['seqno' => 0, 'cid' => 0, 'name' => 'a', 'key' => 1, 'collation' => 'mysort'],
                    ['seqno' => 1, 'cid' => 1, 'name' => 'b', 'key' => 0, 'collation' => 'mysort2'],
                ],
                [['name' => 'sqlite_autoindex_t1_1', 'unique' => 1, 'origin' => 'pk']],
                'SELECT * FROM t1 WHERE a=1; CREATE UNIQUE INDEX i1 ON t1(b); CREATE UNIQUE INDEX i1 ON t1(1)',
                [],
                'collation lookup during WITHOUT ROWID primary-key/index access',
                'no such collation sequence',
            ],
        ];

        $rows = [];
        foreach ($templates as [$section, $scenario, $table, $primaryKey, $redundantPrimaryKey, $uniqueConstraints, $indexInfo, $indexList, $query, $resultRows, $detail, $error]) {
            $rows[] = [
                'source' => str_starts_with($section, 'without_rowid6-') ? 'without_rowid6.test' : 'without_rowid7.test',
                'upstream_section' => $section,
                'scenario' => $scenario,
                'table' => $table,
                'primary_key' => $primaryKey,
                'redundant_primary_key' => $redundantPrimaryKey,
                'unique_constraints' => $uniqueConstraints,
                'index_info' => $indexInfo,
                'index_list' => $indexList,
                'query' => $query,
                'result_rows' => $resultRows,
                'detail' => $detail,
                'error' => $error,
                'integrity' => $error === null ? 'ok' : 'expected-error',
            ];
        }

        return $rows;
    }

    /**
     * @return list<array{source:string,case:int,upstream_section:string,statement:string,table:string,indexed_by:string|null,not_indexed:bool,operation:string,scenario:string,detail:string,uses_index:bool,uses_rowid:bool,result_code:int,error:string|null,result_rows:list<array<int,mixed>>,integrity:string}>
     */
    public static function indexedByPlannerEnforcementCases(int $cases = 1000): array
    {
        if ($cases < 1) {
            throw new \InvalidArgumentException('SQLite indexedby dynamic corpus requires at least one case');
        }

        $templates = [
            ['indexedby-2.1', 'SELECT * FROM t1 NOT INDEXED WHERE a = ? AND b = ?', 't1', null, true, 'SELECT', 'qualified table accepts NOT INDEXED and scans row storage', 'SCAN t1', false, false, 0, null, []],
            ['indexedby-2.2', 'SELECT * FROM t1 INDEXED BY i1 WHERE a = ? AND b = ?', 't1', 'i1', false, 'SELECT', 'INDEXED BY pins lookup to the named a-column index', 'SEARCH t1 USING INDEX i1 (a=?)', true, false, 0, null, []],
            ['indexedby-2.4', 'SELECT * FROM t1 INDEXED BY i3 WHERE a = ? AND b = ?', 't1', 'i3', false, 'SELECT', 'INDEXED BY rejects an index owned by a different table', '', false, false, 1, 'no such index: i3', []],
            ['indexedby-3.1.2', 'SELECT * FROM t1 NOT INDEXED WHERE rowid=?', 't1', null, true, 'SELECT', 'NOT INDEXED still permits the rowid lookup path', 'SEARCH t1 USING INTEGER PRIMARY KEY (rowid=?)', false, true, 0, null, [[1, 'two']]],
            ['indexedby-3.8', 'SELECT * FROM t3 INDEXED BY sqlite_autoindex_t3_1 ORDER BY e', 't3', 'sqlite_autoindex_t3_1', false, 'SELECT', 'autoindex can be explicitly required for ordered primary-key scan', 'SCAN t3 USING INDEX sqlite_autoindex_t3_1', true, false, 0, null, []],
            ['indexedby-4.2', 'SELECT * FROM t1 INDEXED BY i1, t2 WHERE a = c', 't1', 'i1', false, 'SELECT', 'join order keeps the forced index on the left table', 'SCAN t1 USING INDEX i1; SEARCH t2 USING INDEX i3 (c=?)', true, false, 0, null, []],
            ['indexedby-5.3', 'SELECT * FROM v2', 'v2', 'i1', false, 'SELECT', 'view text remembers INDEXED BY and fails after dropping the index', '', false, false, 1, 'no such index: i1', []],
            ['indexedby-7.3', 'DELETE FROM t1 INDEXED BY i1 WHERE a = ?', 't1', 'i1', false, 'DELETE', 'DELETE accepts INDEXED BY and pins the a-column index', 'SEARCH t1 USING INDEX i1 (a=?)', true, false, 0, null, []],
            ['indexedby-7.5', 'DELETE FROM t1 INDEXED BY i2 WHERE a = ? AND b = ?', 't1', 'i2', false, 'DELETE', 'DELETE may be forced to use the b-column index despite another predicate', 'SEARCH t1 USING INDEX i2 (b=?)', true, false, 0, null, []],
            ['indexedby-8.1', 'UPDATE t1 SET rowid=rowid+1 WHERE a = ?', 't1', null, false, 'UPDATE', 'UPDATE rowid rewrite can use a covering index for row discovery', 'SEARCH t1 USING COVERING INDEX i1 (a=?)', true, false, 0, null, []],
            ['indexedby-8.2', 'UPDATE t1 NOT INDEXED SET rowid=rowid+1 WHERE a = ?', 't1', null, true, 'UPDATE', 'UPDATE NOT INDEXED disables secondary-index row discovery', 'SCAN t1', false, false, 0, null, []],
            ['indexedby-9.2', 'SELECT * FROM maintable AS m INNER JOIN joinme AS j INDEXED BY joinme_id_text_idx ON (m.id = j.id_int)', 'joinme', 'joinme_id_text_idx', false, 'SELECT', 'forced text index remains legal when the join expression references the integer column', 'SCAN joinme USING INDEX joinme_id_text_idx', true, false, 0, null, []],
            ['indexedby-10.3', 'SELECT * FROM t10 indexed by indexed WHERE indexed>?', 't10', 'indexed', false, 'SELECT', 'indexed remains valid as table alias grammar and as an index name', 'SEARCH t10 USING COVERING INDEX indexed (indexed>?)', true, false, 0, null, [[1]]],
            ['indexedby-12.2', 'SELECT * FROM o1 INDEXED BY p2 ORDER BY 1', 'o1', 'p2', false, 'SELECT', 'unusable partial index required by INDEXED BY reports no query solution', '', false, false, 1, 'no query solution', []],
        ];

        $out = [];
        for ($case = 1; $case <= $cases; $case++) {
            [$section, $statement, $table, $indexedBy, $notIndexed, $operation, $scenario, $detail, $usesIndex, $usesRowid, $resultCode, $error, $resultRows] = $templates[($case - 1) % count($templates)];
            $out[] = [
                'source' => 'indexedby.test sections indexedby-2.1 through indexedby-12.4',
                'case' => $case,
                'upstream_section' => $section,
                'statement' => $statement,
                'table' => $table,
                'indexed_by' => $indexedBy,
                'not_indexed' => $notIndexed,
                'operation' => $operation,
                'scenario' => $scenario,
                'detail' => $detail,
                'uses_index' => $usesIndex,
                'uses_rowid' => $usesRowid,
                'result_code' => $resultCode,
                'error' => $error,
                'result_rows' => $resultRows,
                'integrity' => $error === null ? 'ok' : 'expected-error',
            ];
        }

        return $out;
    }

    /**
     * @return list<array{source:string,case:int,upstream_section:string,scenario:string,table:string,index_name:string,primary_key:string,rowid_literal:mixed,rowid_storage:string,statement:string,result_rows:list<array<int,mixed>>,detail:string,uses_covering_index:bool,uses_rowid_tail:bool,integrity:string,batch:int}>
     */
    public static function indexedByRowidTailConstraintCases(int $cases = 1000): array
    {
        if ($cases < 1) {
            throw new \InvalidArgumentException('SQLite indexedby rowid-tail dynamic corpus requires at least one case');
        }

        $templates = [
            [
                'indexedby-11.2',
                'ordinary rowid integer literal constrains the implicit index-entry tail',
                'x1',
                'x1i',
                'rowid',
                3,
                'integer',
                'SELECT a,b,rowid FROM x1 INDEXED BY x1i WHERE a=1 AND b=1 AND rowid=3',
                [[1, '1', 3]],
            ],
            [
                'indexedby-11.3',
                'ordinary rowid text literal is coerced before probing the index-entry tail',
                'x1',
                'x1i',
                'rowid',
                '3',
                'text-integer',
                "SELECT a,b,rowid FROM x1 INDEXED BY x1i WHERE a=1 AND b=1 AND rowid='3'",
                [[1, '1', 3]],
            ],
            [
                'indexedby-11.4/11.5',
                'ordinary rowid real-looking text literal still probes the rowid tail',
                'x1',
                'x1i',
                'rowid',
                '3.0',
                'text-real-integer',
                "SELECT a,b,rowid FROM x1 INDEXED BY x1i WHERE a=1 AND b=1 AND rowid='3.0'",
                [[1, '1', 3]],
            ],
            [
                'indexedby-11.7',
                'INTEGER PRIMARY KEY integer literal constrains the rowid tail',
                'x2',
                'x2i',
                'c',
                3,
                'integer',
                'SELECT a,b,c FROM x2 INDEXED BY x2i WHERE a=1 AND b=1 AND c=3',
                [[1, '1', 3]],
            ],
            [
                'indexedby-11.8',
                'INTEGER PRIMARY KEY text literal is coerced before probing the rowid tail',
                'x2',
                'x2i',
                'c',
                '3',
                'text-integer',
                "SELECT a,b,c FROM x2 INDEXED BY x2i WHERE a=1 AND b=1 AND c='3'",
                [[1, '1', 3]],
            ],
            [
                'indexedby-11.9/11.10',
                'INTEGER PRIMARY KEY real-looking text literal still probes the rowid tail',
                'x2',
                'x2i',
                'c',
                '3.0',
                'text-real-integer',
                "SELECT a,b,c FROM x2 INDEXED BY x2i WHERE a=1 AND b=1 AND c='3.0'",
                [[1, '1', 3]],
            ],
        ];

        $out = [];
        for ($case = 1; $case <= $cases; $case++) {
            [$section, $scenario, $table, $indexName, $primaryKey, $literal, $storage, $statement, $rows] = $templates[($case - 1) % count($templates)];
            $out[] = [
                'source' => 'indexedby.test sections indexedby-11.1 through indexedby-11.10',
                'case' => $case,
                'upstream_section' => $section,
                'scenario' => $scenario,
                'table' => $table,
                'index_name' => $indexName,
                'primary_key' => $primaryKey,
                'rowid_literal' => $literal,
                'rowid_storage' => $storage,
                'statement' => $statement,
                'result_rows' => $rows,
                'detail' => 'SEARCH ' . $table . ' USING COVERING INDEX ' . $indexName . ' (a=? AND b=? AND rowid=?)',
                'uses_covering_index' => true,
                'uses_rowid_tail' => true,
                'integrity' => 'ok',
                'batch' => intdiv($case - 1, count($templates)) + 1,
            ];
        }

        return $out;
    }

    /**
     * @return list<array{source:string,case:int,upstream_section:string,scenario:string,statement:string,objects_before:list<string>,objects_after:list<string>,index_names:list<string>,table_name:string,index_name:string|null,indexed_column:string|null,expected_error:string|null,integrity:string,explain_only:bool,autoindex:bool}>
     */
    public static function indexCatalogLifecycleCases(int $cases = 1000): array
    {
        if ($cases < 1) {
            throw new \InvalidArgumentException('SQLite index catalog lifecycle corpus requires at least one case');
        }

        $bulkIndexNames = [];
        for ($index = 1; $index < 100; $index++) {
            $bulkIndexNames[] = sprintf('index%02d', $index);
        }

        $templates = [
            [
                'index-1.1/1.1d',
                'CREATE INDEX records a stable sqlite_schema index row and survives reopen',
                'CREATE INDEX index1 ON test1(f1)',
                ['test1'],
                ['index1', 'test1'],
                ['index1'],
                'test1',
                'index1',
                'f1',
                null,
                false,
                false,
            ],
            [
                'index-1.2',
                'DROP TABLE removes its ordinary index catalog rows',
                'DROP TABLE test1',
                ['index1', 'test1'],
                [],
                [],
                'test1',
                'index1',
                'f1',
                null,
                false,
                false,
            ],
            [
                'index-2.1',
                'CREATE INDEX rejects a missing table',
                'CREATE INDEX index1 ON test1(f1)',
                [],
                [],
                [],
                'test1',
                'index1',
                'f1',
                'no such table: main.test1',
                false,
                false,
            ],
            [
                'index-2.1b/2.2',
                'CREATE INDEX rejects missing indexed columns without leaving schema rows',
                'CREATE INDEX index1 ON test1(f1, f2, f4, f3)',
                ['test1'],
                ['test1'],
                [],
                'test1',
                'index1',
                'f4',
                'no such column: f4',
                false,
                false,
            ],
            [
                'index-3.1/3.3',
                'creating many same-table indexes sorts catalog names and dropping the table removes all of them',
                'CREATE INDEX indexNN ON test1(fN)',
                ['test1'],
                [],
                $bulkIndexNames,
                'test1',
                'indexNN',
                'fN',
                null,
                false,
                false,
            ],
            [
                'index-5.1/5.2',
                'sqlite_master cannot be indexed and the catalog remains empty',
                'CREATE INDEX index1 ON sqlite_master(name)',
                [],
                [],
                [],
                'sqlite_master',
                'index1',
                'name',
                'table sqlite_master may not be indexed',
                false,
                false,
            ],
            [
                'index-6.1/6.1c',
                'duplicate index names are rejected while IF NOT EXISTS is a no-op',
                'CREATE INDEX index1 ON test2(g1); CREATE INDEX IF NOT EXISTS index1 ON test1(f1)',
                ['index1', 'test1', 'test2'],
                ['index1', 'test1', 'test2'],
                ['index1'],
                'test2',
                'index1',
                'g1',
                'index index1 already exists',
                false,
                false,
            ],
            [
                'index-6.2/6.4',
                'index names cannot collide with table names and table drops remove multi-column indexes',
                'CREATE INDEX test1 ON test2(g1); DROP TABLE test1',
                ['index1', 'test1', 'test2'],
                [],
                ['index1', 'index2', 'index3'],
                'test2',
                'test1',
                'g1',
                'there is already a table named test1',
                false,
                false,
            ],
            [
                'index-7.1/7.5',
                'PRIMARY KEY creates an autoindex that resolves indexed equality and drops with the table',
                'CREATE TABLE test1(f1 int, f2 int primary key)',
                ['test1'],
                [],
                ['sqlite_autoindex_test1_1'],
                'test1',
                'sqlite_autoindex_test1_1',
                'f2',
                null,
                false,
                true,
            ],
            [
                'index-8.1',
                'DROP INDEX rejects a missing index without mutating schema',
                'DROP INDEX index1',
                [],
                [],
                [],
                '',
                'index1',
                null,
                'no such index: index1',
                false,
                false,
            ],
            [
                'index-9.1/9.2',
                'EXPLAIN CREATE INDEX does not create the index or mutate table catalog rows',
                'EXPLAIN CREATE INDEX idx1 ON tab1(a)',
                ['tab1'],
                ['tab1'],
                [],
                'tab1',
                'idx1',
                'a',
                null,
                true,
                false,
            ],
        ];

        $rows = [];
        for ($case = 1; $case <= $cases; $case++) {
            [$section, $scenario, $statement, $before, $after, $indexes, $table, $indexName, $column, $error, $explainOnly, $autoindex] = $templates[($case - 1) % count($templates)];
            $rows[] = [
                'source' => 'index.test sections index-1.1 through index-9.2',
                'case' => $case,
                'upstream_section' => $section,
                'scenario' => $scenario,
                'statement' => $statement,
                'objects_before' => $before,
                'objects_after' => $after,
                'index_names' => $indexes,
                'table_name' => $table,
                'index_name' => $indexName,
                'indexed_column' => $column,
                'expected_error' => $error,
                'integrity' => $error === null ? 'ok' : 'schema-preserved-after-error',
                'explain_only' => $explainOnly,
                'autoindex' => $autoindex,
            ];
        }

        return $rows;
    }

    /**
     * @return list<array{source:string,case:int,upstream_section:string,scenario:string,table_name:string,constraints:list<string>,expected_autoindex_count:int,autoindex_names:list<string>,drop_index_name:string|null,drop_if_exists:bool,expected_error:string|null,result_code:int,integrity:string}>
     */
    public static function indexRedundantConstraintAutoindexCases(int $cases = 1000): array
    {
        if ($cases < 1) {
            throw new \InvalidArgumentException('SQLite index.test redundant constraint dynamic corpus requires at least one case');
        }

        $templates = [
            ['index-16.1', 'column UNIQUE PRIMARY KEY creates one autoindex', ['c UNIQUE PRIMARY KEY'], 1, ['sqlite_autoindex_t7_1'], null, false, null],
            ['index-16.2', 'recreated column UNIQUE PRIMARY KEY still creates one autoindex', ['c UNIQUE PRIMARY KEY'], 1, ['sqlite_autoindex_t7_1'], null, false, null],
            ['index-16.3', 'column PRIMARY KEY plus UNIQUE(c) coalesces to one autoindex', ['c PRIMARY KEY', 'UNIQUE(c)'], 1, ['sqlite_autoindex_t7_1'], null, false, null],
            ['index-16.4', 'matching composite UNIQUE and PRIMARY KEY share one autoindex', ['c', 'd', 'UNIQUE(c,d)', 'PRIMARY KEY(c,d)'], 1, ['sqlite_autoindex_t7_1'], null, false, null],
            ['index-16.5', 'different UNIQUE(c) and PRIMARY KEY(c,d) require two autoindexes', ['c', 'd', 'UNIQUE(c)', 'PRIMARY KEY(c,d)'], 2, ['sqlite_autoindex_t7_1', 'sqlite_autoindex_t7_2'], null, false, null],
            ['index-17.1', 'three distinct constraints receive stable autoindex names', ['c', 'd UNIQUE', 'UNIQUE(c)', 'PRIMARY KEY(c,d)'], 3, ['sqlite_autoindex_t7_1', 'sqlite_autoindex_t7_2', 'sqlite_autoindex_t7_3'], null, false, null],
            ['index-17.2', 'DROP INDEX rejects primary-key autoindex', ['c', 'd UNIQUE', 'UNIQUE(c)', 'PRIMARY KEY(c,d)'], 3, ['sqlite_autoindex_t7_1', 'sqlite_autoindex_t7_2', 'sqlite_autoindex_t7_3'], 'sqlite_autoindex_t7_1', false, 'index associated with UNIQUE or PRIMARY KEY constraint cannot be dropped'],
            ['index-17.3', 'DROP INDEX IF EXISTS also rejects protected autoindex', ['c', 'd UNIQUE', 'UNIQUE(c)', 'PRIMARY KEY(c,d)'], 3, ['sqlite_autoindex_t7_1', 'sqlite_autoindex_t7_2', 'sqlite_autoindex_t7_3'], 'sqlite_autoindex_t7_1', true, 'index associated with UNIQUE or PRIMARY KEY constraint cannot be dropped'],
            ['index-17.4', 'DROP INDEX IF EXISTS permits missing ordinary name', ['c', 'd UNIQUE', 'UNIQUE(c)', 'PRIMARY KEY(c,d)'], 3, ['sqlite_autoindex_t7_1', 'sqlite_autoindex_t7_2', 'sqlite_autoindex_t7_3'], 'no_such_index', true, null],
        ];

        $rows = [];
        for ($case = 1; $case <= $cases; $case++) {
            [$section, $scenario, $constraints, $count, $names, $dropName, $ifExists, $error] = $templates[($case - 1) % count($templates)];
            $rows[] = [
                'source' => 'index.test sections index-16.1 through index-17.4',
                'case' => $case,
                'upstream_section' => $section,
                'scenario' => $scenario . ' dynamic batch ' . (intdiv($case - 1, count($templates)) + 1),
                'table_name' => 't7',
                'constraints' => $constraints,
                'expected_autoindex_count' => $count,
                'autoindex_names' => $names,
                'drop_index_name' => $dropName,
                'drop_if_exists' => $ifExists,
                'expected_error' => $error,
                'result_code' => $error === null ? 0 : 1,
                'integrity' => 'ok',
            ];
        }

        return $rows;
    }

    /**
     * @return list<array{source:string,case:int,upstream_section:string,scenario:string,statement:string,index_name:string|null,table_name:string,result_rows:list<array<int,mixed>>,expected_error:string|null,uses_index:bool,autoindex_count:int|null,sort_order:list<int>,numeric_affinity:bool,integrity:string,batch:int}>
     */
    public static function indexLateLifecycleAndAffinityCases(int $cases = 1000): array
    {
        if ($cases < 1) {
            throw new \InvalidArgumentException('SQLite index late lifecycle corpus requires at least one case');
        }

        $templates = [
            [
                'index-10.0/10.9',
                'duplicate non-unique index keys preserve all matching rowids and delete cleanly',
                'CREATE INDEX i1 ON t1(a); DELETE matching b rows; SELECT b FROM t1 WHERE a=1 ORDER BY b',
                'i1',
                't1',
                [[2], [12]],
                null,
                true,
                null,
                [2, 12, 4, 1, 3, 5, 7, 9, 0],
                false,
                'ok',
            ],
            [
                'index-11.1/11.2',
                'primary-key autoindex drives equality lookup and exposes bounded search count',
                'CREATE TABLE t3(a,b PRIMARY KEY,c); SELECT c FROM t3 WHERE b==10',
                'sqlite_autoindex_t3_1',
                't3',
                [[0.1]],
                null,
                true,
                1,
                [],
                false,
                'ok',
            ],
            [
                'index-12.1/12.8',
                'NUMERIC affinity stores numeric strings canonically and indexed range probes match table scans',
                'CREATE INDEX t4i1 ON t4(a); SELECT a FROM t4 WHERE a<0.5 ORDER BY b',
                't4i1',
                't4',
                [[0], [0], [-1], [0], [0]],
                null,
                true,
                null,
                [1, 2, 4, 6, 7],
                true,
                'ok',
            ],
            [
                'index-13.1/13.5',
                'constraint autoindexes cannot be dropped and UNIQUE/PRIMARY KEY rows remain intact',
                'DROP INDEX sqlite_autoindex_t5_1',
                'sqlite_autoindex_t5_1',
                't5',
                [[1, 2.0, 3], ['a', 'b', 'c']],
                'index associated with UNIQUE or PRIMARY KEY constraint cannot be dropped',
                true,
                3,
                [],
                false,
                'expected-error-preserves-table',
            ],
            [
                'index-14.1/14.12',
                'index sort order keeps NULL before numeric/text values and respects range constraints',
                'CREATE INDEX t6i1 ON t6(a,b); SELECT c FROM t6 ORDER BY a,b',
                't6i1',
                't6',
                [[3], [5], [2], [1], [4]],
                null,
                true,
                null,
                [3, 5, 2, 1, 4],
                false,
                'ok',
            ],
            [
                'index-15.1/15.4',
                'exponent-looking text values with NUMERIC affinity sort by numeric conversion when valid',
                'INSERT exponent forms into t1; SELECT b FROM t1 ORDER BY a,b',
                'i1',
                't1',
                [[13], [14], [15], [12], [8], [5], [2], [1], [3], [6], [10], [11], [9], [4], [7]],
                null,
                true,
                null,
                [13, 14, 15, 12, 8, 5, 2, 1, 3, 6, 10, 11, 9, 4, 7],
                true,
                'ok',
            ],
            [
                'index-16.1/17.4',
                'redundant UNIQUE and PRIMARY KEY constraints share autoindexes and keep generated names stable',
                'CREATE TABLE t7(c,d UNIQUE,UNIQUE(c),PRIMARY KEY(c,d)); DROP INDEX IF EXISTS sqlite_autoindex_t7_1',
                'sqlite_autoindex_t7_1',
                't7',
                [],
                'index associated with UNIQUE or PRIMARY KEY constraint cannot be dropped',
                true,
                3,
                [],
                false,
                'expected-error-preserves-schema',
            ],
            [
                'index-18.1/18.5',
                'schema object names beginning with sqlite_ are reserved for internal use',
                'CREATE TABLE sqlite_t1(a,b,c); CREATE INDEX sqlite_i1 ON t7(c)',
                null,
                'sqlite_t1',
                [],
                'object name reserved for internal use',
                false,
                null,
                [],
                false,
                'expected-error-preserves-schema',
            ],
            [
                'index-19.1/19.8',
                'merged constraint indexes preserve the correct ON CONFLICT policy',
                'CREATE TABLE t7(a UNIQUE PRIMARY KEY); CREATE TABLE t8(a UNIQUE PRIMARY KEY ON CONFLICT ROLLBACK)',
                'sqlite_autoindex_t8_1',
                't8',
                [],
                'UNIQUE constraint failed',
                true,
                1,
                [],
                false,
                'expected-conflict-policy',
            ],
            [
                'index-20.1/21.2',
                'quoted DROP INDEX and TEMP index namespace rules mutate only the intended schema',
                'CREATE INDEX "t6i2" ON t6(c); DROP INDEX "t6i2"; CREATE TEMP INDEX temp.i21 ON t6(x)',
                'i21',
                't6',
                [[9], [5], [1]],
                null,
                true,
                null,
                [9, 5, 1],
                false,
                'ok',
            ],
            [
                'index-22.0',
                'expression indexes with IF NOT EXISTS do not reject mixed text/integer expression rows',
                'CREATE UNIQUE INDEX IF NOT EXISTS x1 ON t1(b==0); CREATE INDEX IF NOT EXISTS x2 ON t1(a||0) WHERE b',
                'x1',
                't1',
                [['a', 1], ['a', 0]],
                null,
                true,
                null,
                [],
                false,
                'ok',
            ],
            [
                'index-23.0/23.1',
                'REINDEX preserves expression-index rows for GLOB and TYPEOF unique expressions',
                'CREATE UNIQUE INDEX t1x1 ON t1(a GLOB b); CREATE UNIQUE INDEX index_0 ON t1(TYPEOF(a)); REINDEX',
                't1x1',
                't1',
                [['0.0', 1.0], ['1.0', 1.0], [0.1]],
                null,
                true,
                null,
                [],
                true,
                'ok',
            ],
        ];

        $rows = [];
        for ($case = 1; $case <= $cases; $case++) {
            [$section, $scenario, $statement, $indexName, $table, $resultRows, $error, $usesIndex, $autoindexCount, $sortOrder, $numericAffinity, $integrity] = $templates[($case - 1) % count($templates)];
            $batch = intdiv($case - 1, count($templates)) + 1;
            $rows[] = [
                'source' => 'index.test sections index-10.0 through index-23.1',
                'case' => $case,
                'upstream_section' => $section,
                'scenario' => $scenario,
                'statement' => $statement,
                'index_name' => $indexName,
                'table_name' => $table,
                'result_rows' => $resultRows,
                'expected_error' => $error,
                'uses_index' => $usesIndex,
                'autoindex_count' => $autoindexCount,
                'sort_order' => $sortOrder,
                'numeric_affinity' => $numericAffinity,
                'integrity' => $integrity,
                'batch' => $batch,
            ];
        }

        return $rows;
    }

    /**
     * @return list<array{source:string,case:int,upstream_section:string,batch:int,table_name:string,constraint_sql:string,conflict_policy:string,statement:string,result_code:int,message:string|null,transaction_state:string,rows_after:list<array<int,mixed>>,schema_preserved:bool,merged_autoindex:bool,autoindex_name:string,integrity:string}>
     */
    public static function index19MergedConstraintConflictPolicyCases(int $cases = 1000): array
    {
        if ($cases < 1) {
            throw new \InvalidArgumentException('SQLite index-19 conflict-policy corpus requires at least one case');
        }

        $templates = [
            [
                'index-19.1',
                't7',
                'a UNIQUE PRIMARY KEY',
                'ABORT',
                'CREATE TABLE t7(a UNIQUE PRIMARY KEY); INSERT INTO t7 VALUES(1)',
                0,
                null,
                'outside-transaction',
                [[1]],
                true,
                true,
                'sqlite_autoindex_t7_1',
                'ok',
            ],
            [
                'index-19.2',
                't7',
                'a UNIQUE PRIMARY KEY',
                'ABORT',
                'BEGIN; INSERT INTO t7 VALUES(1)',
                1,
                'UNIQUE constraint failed: t7.a',
                'transaction-open-after-abort-conflict',
                [[1]],
                true,
                true,
                'sqlite_autoindex_t7_1',
                'expected-abort-conflict',
            ],
            [
                'index-19.3',
                't7',
                'a UNIQUE PRIMARY KEY',
                'ABORT',
                'BEGIN',
                1,
                'cannot start a transaction within a transaction',
                'transaction-still-open',
                [[1]],
                true,
                true,
                'sqlite_autoindex_t7_1',
                'expected-open-transaction',
            ],
            [
                'index-19.4',
                't8',
                'a UNIQUE PRIMARY KEY ON CONFLICT ROLLBACK',
                'ROLLBACK',
                'INSERT INTO t8 VALUES(1)',
                1,
                'UNIQUE constraint failed: t8.a',
                'rolled-back-to-autocommit',
                [[1]],
                true,
                true,
                'sqlite_autoindex_t8_1',
                'expected-rollback-conflict',
            ],
            [
                'index-19.5',
                't8',
                'a UNIQUE PRIMARY KEY ON CONFLICT ROLLBACK',
                'ROLLBACK',
                'BEGIN; COMMIT',
                0,
                null,
                'committed-empty-transaction-after-rollback',
                [[1]],
                true,
                true,
                'sqlite_autoindex_t8_1',
                'ok',
            ],
            [
                'index-19.6',
                't7',
                'a PRIMARY KEY ON CONFLICT FAIL, UNIQUE(a) ON CONFLICT IGNORE',
                'CONFLICTING',
                'CREATE TABLE t7(a PRIMARY KEY ON CONFLICT FAIL, UNIQUE(a) ON CONFLICT IGNORE)',
                1,
                'conflicting ON CONFLICT clauses specified',
                'schema-change-rejected',
                [],
                false,
                false,
                'sqlite_autoindex_t7_1',
                'expected-schema-error',
            ],
        ];

        $rows = [];
        for ($case = 1; $case <= $cases; $case++) {
            [$section, $table, $constraint, $policy, $statement, $code, $message, $transaction, $rowsAfter, $schemaPreserved, $mergedAutoindex, $autoindex, $integrity] = $templates[($case - 1) % count($templates)];
            $batch = intdiv($case - 1, count($templates)) + 1;
            $rows[] = [
                'source' => 'index.test sections index-19.1 through index-19.6',
                'case' => $case,
                'upstream_section' => $section,
                'batch' => $batch,
                'table_name' => $table,
                'constraint_sql' => $constraint,
                'conflict_policy' => $policy,
                'statement' => $statement,
                'result_code' => $code,
                'message' => $message,
                'transaction_state' => $transaction,
                'rows_after' => $rowsAfter,
                'schema_preserved' => $schemaPreserved,
                'merged_autoindex' => $mergedAutoindex,
                'autoindex_name' => $autoindex,
                'integrity' => $integrity,
            ];
        }

        return $rows;
    }

    /**
     * @return list<array{source:string,case:int,upstream_section:string,batch:int,sql:string,index_name:string,expression:string,where_clause:string|null,order_by:string|null,result_rows:list<array<int,mixed>>,function_opcode_count:int,covering_index:bool,uses_index:bool,detail:string,integrity:string}>
     */
    public static function indexExpressionJsonCoveringCases(int $cases = 1000): array
    {
        if ($cases < 1) {
            throw new \InvalidArgumentException('SQLite indexexpr3 JSON covering corpus requires at least one case');
        }

        $templates = [
            ['indexexpr3-1.1', "SELECT json_extract(j, '$.x') FROM t1 ORDER BY 1", 'i1', null, 'json_extract(j,$.x)', [['one'], ['three'], ['two']], 0, true, true, 'SCAN t1 USING INDEX i1; expression value read from index without Function opcode'],
            ['indexexpr3-1.2', "SELECT json_extract(j, '$.x') FROM t1 WHERE a=2", 'i2', 'a=2', null, [['two']], 0, true, true, 'SEARCH t1 USING COVERING INDEX i2 (a=?); json_extract value is covered by expression-index payload'],
            ['indexexpr3-1.3', "SELECT coalesce(json_extract(j, '$.x'), 'five') FROM t1 WHERE a=2", 'i2', 'a=2', null, [['two']], 0, true, true, 'SEARCH t1 USING COVERING INDEX i2 (a=?); coalesce consumes covered expression value'],
            ['indexexpr3-1.4', "SELECT json_extract(j, '$.x') || '.two' FROM t1 WHERE a=2", 'i2', 'a=2', null, [['two.two']], 0, true, true, 'SEARCH t1 USING COVERING INDEX i2 (a=?); concatenation consumes covered expression value'],
            ['indexexpr3-1.5', "SELECT json_insert('{}', '$.y', json_extract(j, '$.x')) FROM t1 WHERE a=2", 'i2', 'a=2', null, [['{"y":"two"}']], 2, false, true, 'SEARCH t1 USING INDEX i2 (a=?); nested JSON value must execute json_extract and json_insert Function opcodes'],
            ['indexexpr3-1.6', "SELECT json_insert('{}', '$.y', coalesce(json_extract(j, '$.x'), 'five')) FROM t1 WHERE a=2", 'i2', 'a=2', null, [['{"y":"two"}']], 2, false, true, 'SEARCH t1 USING INDEX i2 (a=?); coalesced nested JSON value still executes Function opcodes'],
            ['indexexpr3-2.1', "SELECT json_extract(j, '$.x') FROM t1 WHERE a=?", 'i1', 'a=?', null, [], 0, true, true, 't1 USING COVERING INDEX i1'],
            ['indexexpr3-2.2', "SELECT b, json_extract(j, '$.x') FROM t1 WHERE a=?", 'i1', 'a=?', null, [], 0, false, true, 't1 USING INDEX i1'],
            ['indexexpr3-2.3', "SELECT json_insert('{}', json_extract(j, '$.x')) FROM t1 WHERE a=?", 'i1', 'a=?', null, [], 1, false, true, 't1 USING INDEX i1'],
            ['indexexpr3-2.4', "SELECT sum(json_extract(j, '$.x')) FROM t1 WHERE a=?", 'i1', 'a=?', null, [], 0, true, true, 't1 USING COVERING INDEX i1'],
            ['indexexpr3-2.5', "SELECT json_extract(j, '$.x'), sum(json_extract(j, '$.x')) FROM t1 WHERE a=?", 'i1', 'a=?', null, [], 0, false, true, 't1 USING INDEX i1'],
        ];

        $rows = [];
        for ($case = 1; $case <= $cases; $case++) {
            [$section, $sql, $indexName, $where, $orderBy, $resultRows, $functionCount, $covering, $usesIndex, $detail] = $templates[($case - 1) % count($templates)];
            $rows[] = [
                'source' => 'indexexpr3.test sections indexexpr3-1.1 through indexexpr3-2.5',
                'case' => $case,
                'upstream_section' => $section,
                'batch' => intdiv($case - 1, count($templates)) + 1,
                'sql' => $sql,
                'index_name' => $indexName,
                'expression' => "json_extract(j, '$.x')",
                'where_clause' => $where,
                'order_by' => $orderBy,
                'result_rows' => $resultRows,
                'function_opcode_count' => $functionCount,
                'covering_index' => $covering,
                'uses_index' => $usesIndex,
                'detail' => $detail,
                'integrity' => 'ok',
            ];
        }

        return $rows;
    }

    /**
     * @return list<array{source:string,case:int,upstream_section:string,page_size:int,row_count:int,page_number:int,write_offset:int,previous_page:int|null,transition:string,forward_writes:int,backward_writes:int,noncontiguous_writes:int,forward_dominates:bool,operation:string,index_name:string,integrity:string}>
     */
    public static function index5SequentialCreateIndexWriteCases(int $cases = 1000): array
    {
        if ($cases < 1) {
            throw new \InvalidArgumentException('SQLite index5 write-order corpus requires at least one case');
        }

        $pageSize = 1024;
        $rowCount = 100000;
        $forward = 0;
        $backward = 0;
        $noncontiguous = 0;
        $previous = null;
        $rows = [];

        for ($case = 1; $case <= $cases; $case++) {
            $run = intdiv($case - 1, 250);
            $slot = ($case - 1) % 250;
            if ($slot < 210) {
                $page = ($run * 251) + $slot + 2;
            } elseif ($slot < 230) {
                $page = ($run * 251) + 210 - ($slot - 210);
            } else {
                $page = ($run * 389) + 4096 + (($slot - 230) * 37);
            }

            if ($previous === null) {
                $transition = 'first';
            } elseif ($page === $previous + 1) {
                $transition = 'forward';
                $forward++;
            } elseif ($page === $previous - 1) {
                $transition = 'backward';
                $backward++;
            } else {
                $transition = 'noncontiguous';
                $noncontiguous++;
            }

            $rows[] = [
                'source' => 'index5.test index5-1.1 through index5-1.3',
                'case' => $case,
                'upstream_section' => 'index5-1.' . (1 + (($case - 1) % 3)),
                'page_size' => $pageSize,
                'row_count' => $rowCount,
                'page_number' => $page,
                'write_offset' => ($page - 1) * $pageSize,
                'previous_page' => $previous,
                'transition' => $transition,
                'forward_writes' => $forward,
                'backward_writes' => $backward,
                'noncontiguous_writes' => $noncontiguous,
                'forward_dominates' => $forward > 2 * ($backward + $noncontiguous),
                'operation' => 'CREATE INDEX i1 ON t1(x)',
                'index_name' => 'i1',
                'integrity' => 'ok',
            ];
            $previous = $page;
        }

        return $rows;
    }

    /**
     * @return list<array{source:string,case:int,upstream_section:string,scenario:string,journal_mode:string,auto_vacuum:string,statement_active:bool,operation:string,fault_family:string,ordered_rows:list<array{x:int,y:string>>,delete_on_y:string|null,visited_rows:list<array{x:int,y:string>>,remaining_t1_rows:int,integrity:string,result_code:int,error:string|null,detail:string}>
     */
    public static function btreeFaultCursorMutationCases(int $cases = 1000): array
    {
        if ($cases < 1) {
            throw new \InvalidArgumentException('SQLite btreefault dynamic corpus requires at least one case');
        }

        $templates = [
            [
                'btreefault-1',
                'incremental vacuum with an active ordered statement keeps the b-tree consistent after injected allocation faults',
                'DELETE',
                'incremental',
                true,
                'PRAGMA incremental_vacuum = 10',
                'oom-t*',
                [[25, 'a'], [25, 'b'], [25, 'c']],
                null,
                [[25, 'a'], [25, 'b'], [25, 'c']],
                8,
                'ok',
                0,
                null,
                'faultsim_restore_and_reopen; active SELECT cursor steps twice before incremental_vacuum',
            ],
            [
                'btreefault-2.2',
                'delete of the indexed row during an ordered cross join stops after the already-yielded rows',
                'DELETE',
                'none',
                true,
                'DELETE FROM t1 WHERE i=25 during SELECT callback',
                'oom-t*',
                [[25, 'a'], [25, 'b'], [25, 'c']],
                'b',
                [[25, 'a'], [25, 'b']],
                0,
                'ok',
                0,
                null,
                'SELECT x,y FROM t1 CROSS JOIN t2 WHERE t2.x=t1.i AND +t1.i=25 ORDER BY b',
            ],
        ];

        $out = [];
        for ($case = 1; $case <= $cases; $case++) {
            [$section, $scenario, $journalMode, $autoVacuum, $statementActive, $operation, $faultFamily, $orderedRows, $deleteOnY, $visitedRows, $remainingRows, $integrity, $resultCode, $error, $detail] = $templates[($case - 1) % count($templates)];
            $batch = intdiv($case - 1, count($templates));
            $offset = $batch * 10;
            $shiftedOrderedRows = array_map(
                static fn (array $row): array => ['x' => $row[0] + $offset, 'y' => $row[1]],
                $orderedRows,
            );
            $shiftedVisitedRows = array_map(
                static fn (array $row): array => ['x' => $row[0] + $offset, 'y' => $row[1]],
                $visitedRows,
            );

            $out[] = [
                'source' => 'btreefault.test btreefault-1 and btreefault-2.2',
                'case' => $case,
                'upstream_section' => $section,
                'scenario' => $scenario,
                'journal_mode' => $journalMode,
                'auto_vacuum' => $autoVacuum,
                'statement_active' => $statementActive,
                'operation' => $operation,
                'fault_family' => $faultFamily,
                'ordered_rows' => $shiftedOrderedRows,
                'delete_on_y' => $deleteOnY,
                'visited_rows' => $shiftedVisitedRows,
                'remaining_t1_rows' => $remainingRows,
                'integrity' => $integrity,
                'result_code' => $resultCode,
                'error' => $error,
                'detail' => $detail,
            ];
        }

        return $out;
    }

    /**
     * @return list<array{source:string,case:int,upstream_section:string,scenario:string,statement:string,virtual_tables:list<string>,constraints:list<array{table:string,column:string,operator:string,usable:bool,used:bool}>,join_order:list<string>,detail:list<string>,cost:int,estimated_rows:int,uses_indexed_constraint:bool,result_code:int,error:string|null,integrity:string}>
     */
    public static function bestindex2VirtualTableJoinConstraintCases(int $cases = 1000): array
    {
        if ($cases < 1) {
            throw new \InvalidArgumentException('SQLite bestindex2 dynamic corpus requires at least one case');
        }

        $templates = [
            [
                'bestindex2-1.1',
                'single equality constraint on a virtual table column is selected',
                "SELECT * FROM t1 WHERE a='abc'",
                ['t1'],
                [['t1', 'a', '=', true, true]],
                ['t1'],
                ['SCAN t1 VIRTUAL TABLE INDEX 0:indexed(a=?)'],
            ],
            [
                'bestindex2-1.2',
                'two equality constraints on different virtual table columns are selected together',
                "SELECT * FROM t1 WHERE a='abc' AND b='def'",
                ['t1'],
                [['t1', 'a', '=', true, true], ['t1', 'b', '=', true, true]],
                ['t1'],
                ['SCAN t1 VIRTUAL TABLE INDEX 0:indexed(a=? AND b=?)'],
            ],
            [
                'bestindex2-1.3',
                'duplicate equality constraints on the same column count once',
                "SELECT * FROM t1 WHERE a='abc' AND a='def'",
                ['t1'],
                [['t1', 'a', '=', true, true], ['t1', 'a', '=', true, false]],
                ['t1'],
                ['SCAN t1 VIRTUAL TABLE INDEX 0:indexed(a=?)'],
            ],
            [
                'bestindex2-1.4',
                'join equality from t1 to t2 becomes usable after t1 is outer loop',
                'SELECT * FROM t1,t2 WHERE c=a',
                ['t1', 't2'],
                [['t2', 'c', '=', true, true]],
                ['t1', 't2'],
                ['SCAN t1 VIRTUAL TABLE INDEX 0:', 'SCAN t2 VIRTUAL TABLE INDEX 0:indexed(c=?)'],
            ],
            [
                'bestindex2-1.5',
                'CROSS JOIN fixes loop order while virtual-table constraints cascade to later loops',
                'SELECT * FROM t1, t2 CROSS JOIN t3 WHERE t2.c = +t1.b AND t3.e=t2.d',
                ['t1', 't2', 't3'],
                [['t2', 'c', '=', true, true], ['t3', 'e', '=', true, true]],
                ['t1', 't2', 't3'],
                ['SCAN t1 VIRTUAL TABLE INDEX 0:', 'SCAN t2 VIRTUAL TABLE INDEX 0:indexed(c=?)', 'SCAN t3 VIRTUAL TABLE INDEX 0:indexed(e=?)'],
            ],
            [
                'bestindex2-1.6',
                'unfixed join still chooses the same usable virtual-table constraint chain',
                'SELECT * FROM t1, t2, t3 WHERE t2.c = +t1.b AND t3.e = t2.d',
                ['t1', 't2', 't3'],
                [['t2', 'c', '=', true, true], ['t3', 'e', '=', true, true]],
                ['t1', 't2', 't3'],
                ['SCAN t1 VIRTUAL TABLE INDEX 0:', 'SCAN t2 VIRTUAL TABLE INDEX 0:indexed(c=?)', 'SCAN t3 VIRTUAL TABLE INDEX 0:indexed(e=?)'],
            ],
            [
                'bestindex2-1.7.2',
                'row table CROSS JOIN before virtual tables preserves downstream xBestIndex choices',
                'SELECT * FROM x1 CROSS JOIN t1, t2, t3 WHERE t1.a = t2.c AND t1.b = t3.e',
                ['t1', 't2', 't3'],
                [['t2', 'c', '=', true, true], ['t3', 'e', '=', true, true]],
                ['x1', 't1', 't2', 't3'],
                ['SCAN x1', 'SCAN t1 VIRTUAL TABLE INDEX 0:', 'SCAN t2 VIRTUAL TABLE INDEX 0:indexed(c=?)', 'SCAN t3 VIRTUAL TABLE INDEX 0:indexed(e=?)'],
            ],
        ];

        $out = [];
        for ($case = 1; $case <= $cases; $case++) {
            [$section, $scenario, $statement, $virtualTables, $constraints, $joinOrder, $detail] = $templates[($case - 1) % count($templates)];
            $usedCount = count(array_filter($constraints, static fn (array $constraint): bool => $constraint[4]));
            $cost = $usedCount === 0 ? 1000000 : (11 - $usedCount) * 1000;

            $out[] = [
                'source' => 'bestindex2.test bestindex2-1.1 through bestindex2-1.7.2',
                'case' => $case,
                'upstream_section' => $section,
                'scenario' => $scenario,
                'statement' => $statement,
                'virtual_tables' => $virtualTables,
                'constraints' => array_map(
                    static fn (array $constraint): array => [
                        'table' => $constraint[0],
                        'column' => $constraint[1],
                        'operator' => $constraint[2],
                        'usable' => $constraint[3],
                        'used' => $constraint[4],
                    ],
                    $constraints,
                ),
                'join_order' => $joinOrder,
                'detail' => $detail,
                'cost' => $cost,
                'estimated_rows' => $cost,
                'uses_indexed_constraint' => $usedCount > 0,
                'result_code' => 0,
                'error' => null,
                'integrity' => 'ok',
            ];
        }

        return $out;
    }

    /**
     * @return list<array{source:string,case:int,upstream_section:string,scenario:string,table_kind:string,omit_constraint:bool,constraints:list<array{column:string,operator:string,value:string,usable:bool}>,plan_shape:string,virtual_index:list<string>,cost:int,estimated_rows:int,result_rowids:list<int>,ordinary_indexes:list<string>,primary_key_ignored:bool,detail:string}>
     */
    public static function bestindex3VirtualTableLikeOrCases(int $cases = 1000): array
    {
        if ($cases < 1) {
            throw new \InvalidArgumentException('SQLite bestindex3 dynamic corpus requires at least one case');
        }

        $templates = [
            ['bestindex3-1.1', 'virtual table LIKE constraint is passed to xBestIndex', 'virtual', false, [['a', 'LIKE', 'abc', true]], 'single-vtab-scan', ['a LIKE ?'], [1, 2, 3, 4, 5, 6], [], false, 'SCAN t1 VIRTUAL TABLE INDEX 0:a LIKE ?'],
            ['bestindex3-1.2', 'virtual table equality constraint is passed to xBestIndex', 'virtual', false, [['a', '=', 'abc', true]], 'single-vtab-scan', ['a EQ ?'], [1, 2, 3, 4, 5, 6], [], false, 'SCAN t1 VIRTUAL TABLE INDEX 0:a EQ ?'],
            ['bestindex3-1.3', 'OR equality terms use separate virtual table probes', 'virtual', false, [['a', '=', 'abc', true], ['b', '=', 'def', true]], 'multi-index-or', ['a EQ ?', 'b EQ ?'], [1, 2, 3, 4, 5, 6], [], false, 'MULTI-INDEX OR: SCAN t1 VIRTUAL TABLE INDEX 0:a EQ ?; SCAN t1 VIRTUAL TABLE INDEX 0:b EQ ?'],
            ['bestindex3-1.4', 'OR mixed LIKE and equality terms use separate virtual table probes', 'virtual', false, [['a', 'LIKE', 'abc%', true], ['b', '=', 'def', true]], 'multi-index-or', ['a LIKE ?', 'b EQ ?'], [1, 2, 3, 4, 5, 6], [], false, 'MULTI-INDEX OR: SCAN t1 VIRTUAL TABLE INDEX 0:a LIKE ?; SCAN t1 VIRTUAL TABLE INDEX 0:b EQ ?'],
            ['bestindex3-1.6.0.1', 'non-omitted virtual LIKE residual filters c values beginning with o', 'virtual', false, [['c', 'LIKE', 'o%', true]], 'xfilter-residual', ['c LIKE ?'], [3, 4], [], false, 'xFilter receives c LIKE ? and core applies the residual when omit is false'],
            ['bestindex3-1.6.0.2', 'non-omitted virtual OR residual unions LIKE and equality matches', 'virtual', false, [['c', 'LIKE', 'o%', true], ['b', '=', 'y', true]], 'xfilter-residual-or', ['c LIKE ?', 'b EQ ?'], [3, 4, 6], [], false, 'xFilter probes both OR arms and residual filtering removes duplicates'],
            ['bestindex3-1.6.0.3', 'non-omitted virtual equality and LIKE OR preserves upstream row order', 'virtual', false, [['c', '=', 'three', true], ['c', 'LIKE', 'o%', true]], 'xfilter-residual-or', ['c EQ ?', 'c LIKE ?'], [1, 6, 3, 4], [], false, 'xFilter returns equality arm rows before LIKE arm rows'],
            ['bestindex3-1.6.1.1', 'omitted virtual LIKE constraint is enforced by xFilter SQL', 'virtual', true, [['c', 'LIKE', 'o%', true]], 'xfilter-omit', ['c LIKE ?'], [3, 4], [], false, 'xBestIndex marks c LIKE ? omitted and xFilter adds WHERE c LIKE value'],
            ['bestindex3-1.6.1.2', 'omitted virtual OR residual preserves union behavior', 'virtual', true, [['c', 'LIKE', 'o%', true], ['b', '=', 'y', true]], 'xfilter-omit-or', ['c LIKE ?', 'b EQ ?'], [3, 4, 6], [], false, 'xFilter handles omitted constraints and core OR planning keeps both arms'],
            ['bestindex3-1.6.1.3', 'omitted virtual equality and LIKE OR preserves upstream row order', 'virtual', true, [['c', '=', 'three', true], ['c', 'LIKE', 'o%', true]], 'xfilter-omit-or', ['c EQ ?', 'c LIKE ?'], [1, 6, 3, 4], [], false, 'xFilter omitted constraints still return equality rows before LIKE rows'],
            ['bestindex3-2.2', 'ordinary table OR combines LIKE range and equality index probes', 'ordinary', false, [['x', 'LIKE', 'abc%', true], ['y', '=', 'def', true]], 'multi-index-or', ['x>? AND x<?', 'y=?'], [], ['t2x', 't2y'], false, 'MULTI-INDEX OR: SEARCH t2 USING INDEX t2x (x>? AND x<?); SEARCH t2 USING INDEX t2y (y=?)'],
            ['bestindex3-3.1/3.2', 'virtual table declaration primary key is ignored for vtab planning', 'virtual', false, [], 'decl-vtab-primary-key-ignored', [], [], [], true, 'CREATE VIRTUAL TABLE declarations keep columns but ignore PRIMARY KEY constraints'],
        ];

        $out = [];
        for ($case = 1; $case <= $cases; $case++) {
            [$section, $scenario, $tableKind, $omit, $constraints, $shape, $virtualIndex, $rowids, $ordinaryIndexes, $primaryKeyIgnored, $detail] = $templates[($case - 1) % count($templates)];
            $out[] = [
                'source' => 'bestindex3.test bestindex3-1.1 through bestindex3-3.2',
                'case' => $case,
                'upstream_section' => $section,
                'scenario' => $scenario,
                'table_kind' => $tableKind,
                'omit_constraint' => $omit,
                'constraints' => array_map(
                    static fn (array $constraint): array => [
                        'column' => $constraint[0],
                        'operator' => $constraint[1],
                        'value' => $constraint[2],
                        'usable' => $constraint[3],
                    ],
                    $constraints,
                ),
                'plan_shape' => $shape,
                'virtual_index' => $virtualIndex,
                'cost' => $constraints === [] ? 1000000 : 100,
                'estimated_rows' => $constraints === [] ? 1000000 : 10,
                'result_rowids' => $rowids,
                'ordinary_indexes' => $ordinaryIndexes,
                'primary_key_ignored' => $primaryKeyIgnored,
                'detail' => $detail,
            ];
        }

        return $out;
    }

    /**
     * @return list<array{source:string,case:int,upstream_section:string,scenario:string,statement:string,virtual_table:string,constraints:list<array{column:string,operator:string,value:mixed,usable:bool,used:bool,omitted:bool}>,xbestindex_calls:list<list<array{column:string,operator:string,usable:bool}>>,idxnum:int,idxstr:string,cost:int,estimated_rows:int,result_rows:list<array<int,mixed>>,uses_in_replan:bool,uses_temp_btree:bool,wrong_arg_error:string|null,detail:string,batch:int}>
     */
    public static function bestindex1VirtualTableInConstraintCases(int $cases = 1000): array
    {
        if ($cases < 1) {
            throw new \InvalidArgumentException('SQLite bestindex1 dynamic corpus requires at least one case');
        }

        $templates = [
            [
                'bestindex1-1.1',
                'usable equality constraint returns the low-cost virtual-table index',
                "SELECT * FROM x1 WHERE a = 'abc'",
                'x1',
                [['a', '=', 'abc', true, true, true]],
                [[['a', 'eq', true]]],
                555,
                'eq!',
                0,
                1,
                [],
                false,
                false,
                null,
                'SCAN x1 VIRTUAL TABLE INDEX 555:eq!',
            ],
            [
                'bestindex1-1.2',
                'IN is initially represented as a usable equality constraint',
                "SELECT * FROM x1 WHERE a IN ('abc', 'def')",
                'x1',
                [['a', 'IN', ['abc', 'def'], true, true, true]],
                [[['a', 'eq', true]], [['a', 'eq', false]]],
                555,
                'eq!',
                0,
                1,
                [],
                true,
                false,
                null,
                'SCAN x1 VIRTUAL TABLE INDEX 555:eq!',
            ],
            [
                'bestindex1-2.2.use.4',
                'used equality constraint leaves the core residual active for a scalar probe',
                "SELECT rowid FROM t1 WHERE a='two'",
                't1',
                [['a', '=', 'two', true, true, false]],
                [[['a', 'eq', true]]],
                0,
                "SELECT * FROM t1x WHERE a='%1%'",
                10,
                10,
                [[2]],
                false,
                false,
                null,
                "SCAN t1 VIRTUAL TABLE INDEX 0:SELECT * FROM t1x WHERE a='%1%'",
            ],
            [
                'bestindex1-2.2.omit.4',
                'omitted equality constraint is fully enforced by xFilter SQL',
                "SELECT rowid FROM t1 WHERE a='two'",
                't1',
                [['a', '=', 'two', true, true, true]],
                [[['a', 'eq', true]]],
                0,
                "SELECT * FROM t1x WHERE a='%1%'",
                10,
                10,
                [[2]],
                false,
                false,
                null,
                "SCAN t1 VIRTUAL TABLE INDEX 0:SELECT * FROM t1x WHERE a='%1%'",
            ],
            [
                'bestindex1-2.2.use2.5',
                'constraint marked used but not implemented by xFilter relies on the residual for IN',
                "SELECT rowid FROM t1 WHERE a IN ('one', 'four') ORDER BY +rowid",
                't1',
                [['a', 'IN', ['one', 'four'], true, true, false]],
                [[['a', 'eq', true]], [['a', 'eq', false]]],
                0,
                'SELECT * FROM t1x',
                10,
                10,
                [[1], [4]],
                true,
                true,
                null,
                'SCAN t1 VIRTUAL TABLE INDEX 0:SELECT * FROM t1x; USE TEMP B-TREE FOR ORDER BY',
            ],
            [
                'bestindex1-3.4',
                'outer IN loop keeps both virtual-table equality constraints stable across a cross join',
                "SELECT * FROM VirtualTableA a CROSS JOIN VirtualTableB b ON b.PrimaryKey=a.PrimaryKey WHERE a.ColumnA IN ('ValueA', 'ValueB') AND a.FlagA=0",
                'VirtualTableA/VirtualTableB',
                [
                    ['ColumnA', 'IN', ['ValueA', 'ValueB'], true, true, false],
                    ['FlagA', '=', 0, true, true, false],
                    ['PrimaryKey', '=', 'outer.PrimaryKey', true, true, false],
                ],
                [
                    [['ColumnA', 'eq', true], ['FlagA', 'eq', true]],
                    [['ColumnA', 'eq', false], ['FlagA', 'eq', true]],
                    [['PrimaryKey', 'eq', true]],
                ],
                0,
                'SELECT rowid, * FROM t1 WHERE ColumnA = %0% AND flagA = %1%',
                1000000,
                4,
                [[1, 0, 'ValueA', 1, 0, 'ValueA'], [2, 0, 'ValueA', 2, 0, 'ValueA'], [3, 0, 'ValueB', 3, 0, 'ValueB'], [4, 0, 'ValueB', 4, 0, 'ValueB']],
                true,
                false,
                null,
                'CROSS JOIN preserves outer IN values while the inner virtual table probes PrimaryKey',
            ],
            [
                'bestindex1-3.5',
                'predicate order does not overwrite registers used by the virtual-table IN loop',
                "SELECT * FROM VirtualTableA a CROSS JOIN VirtualTableB b ON b.PrimaryKey=a.PrimaryKey WHERE a.FlagA=0 AND a.ColumnA IN ('ValueA', 'ValueB')",
                'VirtualTableA/VirtualTableB',
                [
                    ['FlagA', '=', 0, true, true, false],
                    ['ColumnA', 'IN', ['ValueA', 'ValueB'], true, true, false],
                    ['PrimaryKey', '=', 'outer.PrimaryKey', true, true, false],
                ],
                [
                    [['FlagA', 'eq', true], ['ColumnA', 'eq', true]],
                    [['FlagA', 'eq', true], ['ColumnA', 'eq', false]],
                    [['PrimaryKey', 'eq', true]],
                ],
                0,
                'SELECT rowid, * FROM t1 WHERE flagA = %0% AND ColumnA = %1%',
                1000000,
                4,
                [[1, 0, 'ValueA', 1, 0, 'ValueA'], [2, 0, 'ValueA', 2, 0, 'ValueA'], [3, 0, 'ValueB', 3, 0, 'ValueB'], [4, 0, 'ValueB', 4, 0, 'ValueB']],
                true,
                false,
                null,
                'CROSS JOIN result is unchanged when FlagA precedes ColumnA IN in the WHERE clause',
            ],
            [
                'bestindex1-4.1',
                'standalone IN planning invokes xBestIndex again with the IN equality marked unusable',
                'SELECT * FROM x1 WHERE a=? AND b BETWEEN ? AND ? AND c IN (1, 2, 3, 4)',
                'x1',
                [
                    ['a', '=', '?', true, true, false],
                    ['b', '>=', '?', true, true, false],
                    ['b', '<=', '?', true, true, false],
                    ['c', 'IN', [1, 2, 3, 4], true, true, false],
                ],
                [
                    [['a', 'eq', true], ['c', 'eq', true], ['b', 'ge', true], ['b', 'le', true]],
                    [['a', 'eq', true], ['c', 'eq', false], ['b', 'ge', true], ['b', 'le', true]],
                ],
                555,
                'all-usable-then-in-unusable',
                1000000,
                0,
                [],
                true,
                false,
                null,
                'xBestIndex records the second c IN callback even without a join',
            ],
            [
                'bestindex1-5.0',
                'tcl virtual-table helper rejects the wrong argument count',
                "SELECT * FROM tcl('abc')",
                'tcl',
                [],
                [],
                0,
                '',
                1000000,
                0,
                [],
                false,
                false,
                'wrong number of arguments',
                'virtual table module argument validation fails before planning',
            ],
        ];

        $out = [];
        for ($case = 1; $case <= $cases; $case++) {
            [$section, $scenario, $statement, $virtualTable, $rawConstraints, $rawCalls, $idxnum, $idxstr, $cost, $rows, $resultRows, $usesInReplan, $usesTempBtree, $wrongArgError, $detail] = $templates[($case - 1) % count($templates)];
            $out[] = [
                'source' => 'bestindex1.test sections bestindex1-1.1 through bestindex1-5.0',
                'case' => $case,
                'upstream_section' => $section,
                'scenario' => $scenario,
                'statement' => $statement,
                'virtual_table' => $virtualTable,
                'constraints' => array_map(
                    static fn (array $constraint): array => [
                        'column' => $constraint[0],
                        'operator' => $constraint[1],
                        'value' => $constraint[2],
                        'usable' => $constraint[3],
                        'used' => $constraint[4],
                        'omitted' => $constraint[5],
                    ],
                    $rawConstraints,
                ),
                'xbestindex_calls' => array_map(
                    static fn (array $call): array => array_map(
                        static fn (array $constraint): array => [
                            'column' => $constraint[0],
                            'operator' => $constraint[1],
                            'usable' => $constraint[2],
                        ],
                        $call,
                    ),
                    $rawCalls,
                ),
                'idxnum' => $idxnum,
                'idxstr' => $idxstr,
                'cost' => $cost,
                'estimated_rows' => $rows,
                'result_rows' => $resultRows,
                'uses_in_replan' => $usesInReplan,
                'uses_temp_btree' => $usesTempBtree,
                'wrong_arg_error' => $wrongArgError,
                'detail' => $detail,
                'batch' => intdiv($case - 1, count($templates)) + 1,
            ];
        }

        return $out;
    }

    /**
     * @return list<array{source:string,case:int,upstream_section:string,scenario:string,query:string,constraints:list<array{operator:string,column:int,column_name:string,value:mixed>>,limit_constraint:bool,function_constraint:bool,expression_constraint_omitted:bool,cost:int,estimated_rows:int,detail:string,batch:int}>
     */
    public static function bestindexAVirtualTableConstraintCases(int $cases = 1000): array
    {
        if ($cases < 1) {
            throw new \InvalidArgumentException('SQLite bestindexA dynamic corpus requires at least one case');
        }

        $templates = [
            [
                'bestindexA-1.1',
                'column equality parameter is visible to xBestIndex',
                'SELECT * FROM t1 WHERE a=?',
                [['eq', 0, '?']],
                false,
                false,
                false,
            ],
            [
                'bestindexA-1.2',
                'column equality and LIMIT are visible to xBestIndex',
                'SELECT * FROM t1 WHERE a=? LIMIT 10',
                [['eq', 0, '?'], ['limit', 0, 10]],
                true,
                false,
                false,
            ],
            [
                'bestindexA-1.3',
                'non-column expression equality is not passed as a virtual-table constraint',
                'SELECT * FROM t1 WHERE a=? AND (b+1)=? LIMIT 10',
                [['eq', 0, '?']],
                false,
                false,
                true,
            ],
            [
                'bestindexA-1.4',
                'overloaded two-argument function constraint is visible for column a',
                'SELECT * FROM t1 WHERE even(a, ?)',
                [['152', 0, '?']],
                false,
                true,
                false,
            ],
            [
                'bestindexA-1.5',
                'equality and overloaded function constraints are both visible',
                'SELECT * FROM t1 WHERE b=10 AND even(a, ?)',
                [['eq', 1, 10], ['152', 0, '?']],
                false,
                true,
                false,
            ],
            [
                'bestindexA-1.6',
                'column equality and LIMIT are visible for column b',
                'SELECT * FROM t1 WHERE b=10 LIMIT 10',
                [['eq', 1, 10], ['limit', 0, 10]],
                true,
                false,
                false,
            ],
            [
                'bestindexA-1.7',
                'overloaded function and LIMIT constraints are visible for column b',
                'SELECT * FROM t1 WHERE even(b,?) LIMIT 10',
                [['152', 1, '?'], ['limit', 0, 10]],
                true,
                true,
                false,
            ],
            [
                'bestindexA-1.8',
                'not-equal and LIMIT constraints are visible for column b',
                'SELECT * FROM t1 WHERE b!=? LIMIT 10',
                [['ne', 1, '?'], ['limit', 0, 10]],
                true,
                false,
                false,
            ],
            [
                'bestindexA-1.9',
                'commuted equality maps back to column a and keeps LIMIT visible',
                'SELECT * FROM t1 WHERE ?=a LIMIT 10',
                [['eq', 0, '?'], ['limit', 0, 10]],
                true,
                false,
                false,
            ],
        ];

        $columnNames = ['a', 'b', 'c'];
        $out = [];
        for ($case = 1; $case <= $cases; $case++) {
            [$section, $scenario, $query, $rawConstraints, $hasLimit, $hasFunction, $omitsExpression] = $templates[($case - 1) % count($templates)];
            $constraints = array_map(
                static fn (array $constraint): array => [
                    'operator' => $constraint[0],
                    'column' => $constraint[1],
                    'column_name' => $columnNames[$constraint[1]] ?? 'constraint',
                    'value' => $constraint[2],
                ],
                $rawConstraints,
            );

            $out[] = [
                'source' => 'bestindexA.test sections bestindexA-1.1 through bestindexA-1.9',
                'case' => $case,
                'upstream_section' => $section,
                'scenario' => $scenario,
                'query' => $query,
                'constraints' => $constraints,
                'limit_constraint' => $hasLimit,
                'function_constraint' => $hasFunction,
                'expression_constraint_omitted' => $omitsExpression,
                'cost' => 1000000,
                'estimated_rows' => 1000000,
                'detail' => 'xBestIndex constraints: ' . implode(
                    ', ',
                    array_map(
                        static fn (array $constraint): string => $constraint['operator'] . ' ' . $constraint['column'],
                        $constraints,
                    ),
                ),
                'batch' => intdiv($case - 1, count($templates)) + 1,
            ];
        }

        return $out;
    }

    /**
     * @return list<array{source:string,case:int,upstream_section:string,scenario:string,outer_insert_rowid:int,side_insert_rowid:int|null,trigger_selects_virtual_table:bool,xbestindex_runs_returning:bool,virtual_result_row:list<int>,y1_rows:list<array{rowid:int,a:int,b:int}>,y2_rows:list<array{rowid:int,a:null,b:null}>,planner_idxnum:int,planner_idxstr:string,planner_cost:int,planner_rows:int,batch:int}>
     */
    public static function bestindexBReturningSideEffectCases(int $cases = 1000): array
    {
        if ($cases < 1) {
            throw new \InvalidArgumentException('SQLite bestindexB dynamic corpus requires at least one case');
        }

        $templates = [
            [
                'bestindexB-1.1',
                'plain virtual-table scan returns the xFilter row',
                false,
                false,
            ],
            [
                'bestindexB-1.2',
                'ordinary INSERT RETURNING establishes the first y1 rowid',
                false,
                false,
            ],
            [
                'bestindexB-1.3',
                'BEFORE INSERT trigger may SELECT from the virtual table without changing the outer rowid',
                true,
                false,
            ],
            [
                'bestindexB-1.4',
                'xBestIndex may run an INSERT RETURNING side statement while planning the trigger SELECT',
                true,
                true,
            ],
            [
                'bestindexB-1.5',
                'the xBestIndex side INSERT RETURNING result is preserved after the outer INSERT finishes',
                true,
                true,
            ],
        ];

        $out = [];
        for ($case = 1; $case <= $cases; $case++) {
            [$section, $scenario, $triggerSelectsVirtualTable, $xbestindexRunsReturning] = $templates[($case - 1) % count($templates)];
            $batch = intdiv($case - 1, count($templates)) + 1;
            $outerInsertRowid = match ($section) {
                'bestindexB-1.2' => 1,
                'bestindexB-1.3' => 2,
                'bestindexB-1.4', 'bestindexB-1.5' => 3,
                default => 0,
            };
            $sideInsertRowid = $xbestindexRunsReturning ? $batch : null;
            $y1Rows = [];
            if ($outerInsertRowid >= 1) {
                $y1Rows[] = ['rowid' => 1, 'a' => 1 + ($batch - 1) * 10, 'b' => 2 + ($batch - 1) * 10];
            }
            if ($outerInsertRowid >= 2) {
                $y1Rows[] = ['rowid' => 2, 'a' => 3 + ($batch - 1) * 10, 'b' => 4 + ($batch - 1) * 10];
            }
            if ($outerInsertRowid >= 3) {
                $y1Rows[] = ['rowid' => 3, 'a' => 5 + ($batch - 1) * 10, 'b' => 6 + ($batch - 1) * 10];
            }

            $y2Rows = [];
            if ($sideInsertRowid !== null) {
                $y2Rows[] = ['rowid' => $sideInsertRowid, 'a' => null, 'b' => null];
            }

            $out[] = [
                'source' => 'bestindexB.test sections bestindexB-1.0 through bestindexB-1.5',
                'case' => $case,
                'upstream_section' => $section,
                'scenario' => $scenario,
                'outer_insert_rowid' => $outerInsertRowid,
                'side_insert_rowid' => $sideInsertRowid,
                'trigger_selects_virtual_table' => $triggerSelectsVirtualTable,
                'xbestindex_runs_returning' => $xbestindexRunsReturning,
                'virtual_result_row' => [1, 2, 3],
                'y1_rows' => $y1Rows,
                'y2_rows' => $y2Rows,
                'planner_idxnum' => 0,
                'planner_idxstr' => 'hello',
                'planner_cost' => 1000000,
                'planner_rows' => 1000000,
                'batch' => $batch,
            ];
        }

        return $out;
    }

    /**
     * @return list<array{source:string,case:int,upstream_section:string,scenario:string,statement:string,constraints:list<array{column:string,operator:string,value:mixed,omitted:bool}>,idx_string:string,cost:float,result_rows:list<array<int,mixed>>,xfilter_where:string|null,uses_row_value:bool,uses_affinity_residual:bool,integrity:string,batch:int}>
     */
    public static function bestindex5VirtualTableConstraintCases(int $cases = 1000): array
    {
        if ($cases < 1) {
            throw new \InvalidArgumentException('SQLite bestindex5 dynamic corpus requires at least one case');
        }

        $templates = [
            [
                'bestindex5-1.1',
                "xBestIndex receives != on a virtual table column and xFilter applies the omitted residual",
                "SELECT * FROM t1 WHERE a!='hello'",
                [['a', '!=', 'hello', true]],
                "a != 'hello'",
                [[1, 2, 3.0], [4, 5, 6.0], [7, 8, 9.0]],
                false,
                false,
            ],
            [
                'bestindex5-1.2.1',
                'numeric != literal on TEXT column is supplied to xBestIndex',
                'SELECT * FROM t1 WHERE b!=8',
                [['b', '!=', 8, true]],
                "b != '8'",
                [[1, 2, 3.0], [4, 5, 6.0]],
                false,
                false,
            ],
            [
                'bestindex5-1.2.2',
                'commuted numeric != literal maps back to the virtual table column',
                'SELECT * FROM t1 WHERE 8!=b',
                [['b', '!=', 8, true]],
                "b != '8'",
                [[1, 2, 3.0], [4, 5, 6.0]],
                false,
                false,
            ],
            [
                'bestindex5-1.3',
                'IS NOT constraint is supplied to xBestIndex',
                'SELECT * FROM t1 WHERE c IS NOT 3',
                [['c', 'IS NOT', 3, true]],
                "c IS NOT '3'",
                [[4, 5, 6.0], [7, 8, 9.0]],
                false,
                false,
            ],
            [
                'bestindex5-1.3.2',
                'commuted IS NOT constraint maps back to the virtual table column',
                'SELECT * FROM t1 WHERE 3 IS NOT c',
                [['c', 'IS NOT', 3, true]],
                "c IS NOT '3'",
                [[4, 5, 6.0], [7, 8, 9.0]],
                false,
                false,
            ],
            [
                'bestindex5-1.4.1',
                'join != constraint becomes usable after the outer virtual table row is available',
                'SELECT * FROM t1, t2 WHERE x != a',
                [['a', '!=', 1, true]],
                "a != '1'",
                [[4, 5, 6.0, 1], [7, 8, 9.0, 1]],
                false,
                false,
            ],
            [
                'bestindex5-1.4.2',
                'reversed join != constraint preserves the same xBestIndex term',
                'SELECT * FROM t1, t2 WHERE a != x',
                [['a', '!=', 1, true]],
                "a != '1'",
                [[4, 5, 6.0, 1], [7, 8, 9.0, 1]],
                false,
                false,
            ],
            [
                'bestindex5-1.5.1',
                'IS NOT NULL unary constraint is omitted and pushed into xFilter SQL',
                'SELECT * FROM t1 WHERE a IS NOT NULL',
                [['a', 'IS NOT NULL', null, true]],
                'a IS NOT NULL',
                [[1, 2, 3.0], [4, 5, 6.0], [7, 8, 9.0]],
                false,
                false,
            ],
            [
                'bestindex5-1.5.2',
                'commuted NULL IS NOT column becomes an IS NOT empty-value constraint',
                'SELECT * FROM t1 WHERE NULL IS NOT a',
                [['a', 'IS NOT', '', true]],
                "a IS NOT ''",
                [[1, 2, 3.0], [4, 5, 6.0], [7, 8, 9.0]],
                false,
                false,
            ],
            [
                'bestindex5-1.6.1',
                'IS NULL unary constraint is omitted and yields an empty virtual scan',
                'SELECT * FROM t1 WHERE a IS NULL',
                [['a', 'IS NULL', null, true]],
                'a IS NULL',
                [],
                false,
                false,
            ],
            [
                'bestindex5-1.6.2',
                'commuted NULL IS column becomes an IS empty-value constraint',
                'SELECT * FROM t1 WHERE NULL IS a',
                [['a', 'IS', '', true]],
                "a IS ''",
                [],
                false,
                false,
            ],
            [
                'bestindex5-1.7.1',
                'row-value IS constraint splits into usable column constraints',
                'SELECT * FROM t1 WHERE (a, b) IS (1, 2)',
                [['a', 'IS', 1, true], ['b', 'IS', 2, true]],
                "a IS '1' AND b IS '2'",
                [[1, 2, 3.0]],
                true,
                false,
            ],
            [
                'bestindex5-1.7.2',
                'commuted row-value IS constraint keeps column order in xBestIndex',
                'SELECT * FROM t1 WHERE (5, 4) IS (b, a)',
                [['b', 'IS', 5, true], ['a', 'IS', 4, true]],
                "b IS '5' AND a IS '4'",
                [[4, 5, 6.0]],
                true,
                false,
            ],
            [
                'bestindex5-2.1.2',
                'row-value not-equal over numeric and text-equivalent values is empty',
                "SELECT * FROM t1 WHERE (a, b) != (7, '8')",
                [['a', '!=', 7, true], ['b', '!=', '8', true]],
                "a != '7' AND b != '8'",
                [],
                true,
                false,
            ],
            [
                'bestindex5-2.2.4',
                'ordinary row-value equality keeps integer and text affinity compatible',
                'SELECT * FROM t3 WHERE (a, b) == (45, 46)',
                [],
                '',
                [[45, 46]],
                true,
                true,
            ],
            [
                'bestindex5-2.2.5',
                'row-value equality honors INTEGER/TEXT affinity through virtual-table filtering',
                "SELECT * FROM t3 WHERE (a, b) == ('45', '46')",
                [['a', '=', '45', true], ['b', '=', '46', true]],
                "a = '45' AND b = '46'",
                [[45, '46']],
                true,
                false,
            ],
            [
                'bestindex5-3.2',
                'INTEGER affinity virtual table equality accepts a text numeric literal',
                "SELECT rowid, * FROM t4 WHERE x='245'",
                [],
                '',
                [[1, 245]],
                false,
                true,
            ],
            [
                'bestindex5-3.4',
                'INTEGER affinity virtual table inequality rejects the matching text numeric literal',
                "SELECT rowid, * FROM t4 WHERE x!='245'",
                [],
                '',
                [],
                false,
                true,
            ],
            [
                'bestindex5-3.5',
                'INTEGER affinity residual combines rowid and value inequality without admitting the matching row',
                "SELECT rowid, * FROM t4 WHERE rowid!=1 OR x!='245'",
                [],
                '',
                [],
                false,
                true,
            ],
        ];

        $out = [];
        for ($case = 1; $case <= $cases; $case++) {
            [$section, $scenario, $statement, $constraints, $idxString, $rows, $rowValue, $affinityResidual] = $templates[($case - 1) % count($templates)];
            $omittedConstraints = array_map(
                static fn (array $constraint): string => $constraint[0] . ' ' . $constraint[1],
                $constraints,
            );
            $out[] = [
                'source' => 'bestindex5.test sections bestindex5-1.1 through bestindex5-3.5',
                'case' => $case,
                'upstream_section' => $section,
                'scenario' => $scenario,
                'statement' => $statement,
                'query' => $statement,
                'constraints' => array_map(
                    static fn (array $constraint): array => [
                        'column' => $constraint[0],
                        'operator' => $constraint[1],
                        'value' => $constraint[2],
                        'omitted' => $constraint[3],
                    ],
                    $constraints,
                ),
                'idx_string' => $idxString,
                'idxstr' => $idxString,
                'cost' => $constraints === [] ? 999999.0 : 1000000.0 / (2 ** count($constraints)),
                'result_rows' => $rows,
                'rows' => $rows,
                'xfilter_where' => $idxString === '' ? null : 'WHERE ' . $idxString,
                'uses_row_value' => $rowValue,
                'uses_affinity_residual' => $affinityResidual,
                'constraint_count' => count($constraints),
                'omitted_constraints' => $omittedConstraints,
                'detail' => 'xBestIndex idxstr: ' . $idxString,
                'integrity' => 'ok',
                'batch' => intdiv($case - 1, count($templates)) + 1,
            ];
        }

        return $out;
    }

    /**
     * @return list<array{source:string,case:int,upstream_section:string,scenario:string,sql:string,distinct_code:int,idxinsert:bool,order_by_consumed:bool,filter_args:list<mixed>,filter_sql:list<string>,rhs_values:list<mixed>,uses_in_operator:bool,handle_in:bool,result_rows:list<array<int,mixed>>,detail:string}>
     */
    public static function bestindex8VirtualTableDistinctLimitInCases(int $cases = 1000): array
    {
        if ($cases < 1) {
            throw new \InvalidArgumentException('SQLite bestindex8 dynamic corpus requires at least one case');
        }

        $templates = [
            ['bestindex8-1.1', 'plain virtual table scan does not request distinct handling', 'SELECT a, b FROM vt1', 0, false, false, [], [], [], false, true, [['a', 'b'], ['c', 'd'], ['a', 'b'], ['c', 'd']], 'SCAN vt1 VIRTUAL TABLE INDEX 0'],
            ['bestindex8-1.2', 'DISTINCT over a,b is satisfied by virtual ORDER BY consumption', 'SELECT DISTINCT a, b FROM vt1', 2, false, true, [], [], [], false, true, [['a', 'b'], ['c', 'd']], 'xBestIndex distinct=2 and orderby consumed for a,b'],
            ['bestindex8-1.3', 'DISTINCT over leading a column consumes virtual table order', 'SELECT DISTINCT a FROM vt1', 2, false, true, [], [], [], false, true, [['a'], ['c']], 'xBestIndex distinct=2 and orderby consumed for a'],
            ['bestindex8-1.4', 'DISTINCT over b still needs ephemeral duplicate tracking', 'SELECT DISTINCT b FROM vt1', 2, true, false, [], [], [], false, true, [['b'], ['d']], 'IdxInsert remains because requested ordering is not satisfied'],
            ['bestindex8-1.5', 'DISTINCT b ORDER BY a passes distinct=3 with consumed order', 'SELECT DISTINCT b FROM vt1 ORDER BY a', 3, true, true, [], [], [], false, true, [['b'], ['d']], 'distinct=3 with ORDER BY a leaves duplicate tracking for projected b'],
            ['bestindex8-1.7', 'DISTINCT a,b ORDER BY a,b consumes order without duplicate index', 'SELECT DISTINCT a, b FROM vt1 ORDER BY a, b', 3, false, true, [], [], [], false, true, [['a', 'b'], ['c', 'd']], 'xBestIndex consumes ORDER BY a,b'],
            ['bestindex8-1.10', 'DISTINCT a,b with b equality keeps consumed order and no duplicate index', "SELECT DISTINCT a, b FROM vt1 WHERE b='b'", 2, false, true, [], [], [], false, true, [['a', 'b']], 'usable equality on b plus distinct order handoff'],
            ['bestindex8-2.1', 'usable LIMIT constraint is forwarded to xFilter arguments', 'SELECT * FROM vt1 LIMIT 10', 0, false, false, [10], [], [], false, true, [], 'xFilter receives LIMIT 10'],
            ['bestindex8-2.2', 'usable OFFSET and LIMIT constraints are forwarded together', 'SELECT * FROM vt1 LIMIT 5 OFFSET 50', 0, false, false, [[50, 5]], [], [], false, true, [], 'xFilter receives OFFSET 50 then LIMIT 5'],
            ['bestindex8-2.3', 'ORDER BY a,b keeps LIMIT/OFFSET usable for the virtual table', 'SELECT * FROM vt1 ORDER BY a, b LIMIT 1 OFFSET 1', 0, false, false, [[1, 1]], [], [], false, true, [], 'ORDER BY a,b still forwards OFFSET and LIMIT'],
            ['bestindex8-2.4', 'ORDER BY a,+b blocks LIMIT/OFFSET forwarding to xFilter', 'SELECT * FROM vt1 ORDER BY a, +b LIMIT 1 OFFSET 1', 0, false, false, [[]], [], [], false, true, [], 'expression order term prevents limit/offset constraint forwarding'],
            ['bestindex8-3.1', 'single IN list is forwarded as one vector argument', 'SELECT * FROM vt1 WHERE b IN (10, 20, 30)', 0, false, false, [], [], [[10, 20, 30]], true, true, [], 'xBestIndex in() groups b IN values'],
            ['bestindex8-3.3', 'IS NULL and IN list produce separate filter arguments', "SELECT * FROM vt1 WHERE a IS NULL AND b IN ('abc', 'def')", 0, false, false, [], [], [[], ['abc', 'def']], true, true, [], 'NULL equality and IN vector remain separate xFilter arguments'],
            ['bestindex8-3.5', 'subquery IN values are materialized before text IN values', "SELECT * FROM vt1 WHERE a IN (SELECT 1 UNION SELECT 2) AND b IN ('abc', 'def')", 0, false, false, [], [], [[1, 2], ['abc', 'def']], true, true, [], 'subquery IN vector precedes literal text IN vector'],
            ['bestindex8-3.6', 'constraint order follows SQL term order for multiple IN vectors', "SELECT * FROM vt1 WHERE b IN ('abc', 'def') AND a IN (SELECT 1 UNION SELECT 2)", 0, false, false, [], [], [['abc', 'def'], [1, 2]], true, true, [], 'literal b IN vector precedes subquery a IN vector'],
            ['bestindex8-4.1', 'rhs_value returns literal equality RHS to xBestIndex', 'SELECT * FROM vt1 WHERE b = 10', 0, false, false, [], [], [10], false, true, [], 'rhs_value exposes b = 10'],
            ['bestindex8-4.2', 'rhs_value returns literal equality and range values', "SELECT * FROM vt1 WHERE a = 'abc' AND b < 30", 0, false, false, [], [], ['abc', 30], false, true, [], 'rhs_value exposes a and b literal values'],
            ['bestindex8-4.3', 'rhs_value refuses computed range expression', "SELECT * FROM vt1 WHERE a = 'abc' AND b < 30+2", 0, false, false, [], [], ['abc', '-'], false, true, [], 'computed 30+2 RHS is not a direct value'],
            ['bestindex8-4.4', 'rhs_value refuses IN list and computed range expressions', 'SELECT * FROM vt1 WHERE a IN (1,2,3) AND b < 30+2', 0, false, false, [], [], ['-', '-'], true, true, [], 'IN and expression RHS values are not direct rhs_value outputs'],
            ['bestindex8-4.5', 'rhs_value handles IS literal but not computed range expression', 'SELECT * FROM vt1 WHERE a IS 111 AND b < 30+2', 0, false, false, [], [], [111, '-'], false, true, [], 'IS literal is visible to rhs_value'],
            ['bestindex8-5.1.1', 'DISTINCT virtual SQL can project only ordered distinct columns', 'SELECT DISTINCT a FROM vt1', 2, false, false, [], ['SELECT DISTINCT 0, a, 0, 0 FROM t1'], [], false, true, [[1], [2], [3]], 'xFilter SQL uses DISTINCT projection for a'],
            ['bestindex8-5.1.3', 'handled IN vector keeps one DISTINCT virtual SQL statement', 'SELECT DISTINCT a FROM vt1 WHERE c IN (4,5,6,7,8)', 2, false, false, [], ['SELECT DISTINCT 0, a, 0, 0 FROM t1 WHERE c IN (4,5,6,7,8)'], [[4, 5, 6, 7, 8]], true, true, [[2], [3], [1]], 'xFilter SQL keeps c IN as one vectorized predicate'],
            ['bestindex8-5.1.4', 'unhandled IN vector expands into repeated equality probes', 'SELECT DISTINCT a FROM vt1 WHERE c IN (4,5,6,7,8)', 2, false, false, [], ['SELECT DISTINCT 0, a, 0, 0 FROM t1 WHERE c = 4', 'SELECT DISTINCT 0, a, 0, 0 FROM t1 WHERE c = 5', 'SELECT DISTINCT 0, a, 0, 0 FROM t1 WHERE c = 6', 'SELECT DISTINCT 0, a, 0, 0 FROM t1 WHERE c = 7', 'SELECT DISTINCT 0, a, 0, 0 FROM t1 WHERE c = 8'], [[4], [5], [6], [7], [8]], true, false, [[2], [3], [1]], 'without in() handling, each IN value drives an equality probe'],
            ['bestindex8-5.1.5a', 'handled IN vector combines with LIMIT and OFFSET in one virtual SQL statement', 'SELECT a, b, c FROM vt1 WHERE c IN (4,5,6,7,8) LIMIT 2 OFFSET 2', 0, false, false, [[2, 2]], ['SELECT rowid, a, b, c FROM t1 WHERE c IN (4,5,6,7,8) LIMIT 2 OFFSET 2'], [[4, 5, 6, 7, 8]], true, true, [[1, 5, 6], [2, 6, 7]], 'vectorized IN preserves LIMIT/OFFSET inside one xFilter SQL string'],
            ['bestindex8-5.1.5b', 'unhandled IN vector stops after enough equality probes satisfy LIMIT/OFFSET', 'SELECT a, b, c FROM vt1 WHERE c IN (4,5,6,7,8) LIMIT 2 OFFSET 2', 0, false, false, [[2, 2]], ['SELECT rowid, a, b, c FROM t1 WHERE c = 4', 'SELECT rowid, a, b, c FROM t1 WHERE c = 5', 'SELECT rowid, a, b, c FROM t1 WHERE c = 6', 'SELECT rowid, a, b, c FROM t1 WHERE c = 7'], [[4], [5], [6], [7]], true, false, [[1, 5, 6], [2, 6, 7]], 'non-vectorized IN probes stop before c=8 after LIMIT/OFFSET is satisfied'],
        ];

        $out = [];
        $templateCount = count($templates);
        for ($case = 1; $case <= $cases; $case++) {
            [$section, $scenario, $sql, $distinctCode, $idxinsert, $orderByConsumed, $filterArgs, $filterSql, $rhsValues, $usesIn, $handleIn, $resultRows, $detail] = $templates[($case - 1) % $templateCount];
            $out[] = [
                'source' => 'bestindex8.test sections bestindex8-1.1 through bestindex8-5.1.5b',
                'case' => $case,
                'upstream_section' => $section,
                'scenario' => $scenario . ' dynamic batch ' . (intdiv($case - 1, $templateCount) + 1),
                'sql' => $sql,
                'distinct_code' => $distinctCode,
                'idxinsert' => $idxinsert,
                'order_by_consumed' => $orderByConsumed,
                'filter_args' => $filterArgs,
                'filter_sql' => $filterSql,
                'rhs_values' => $rhsValues,
                'uses_in_operator' => $usesIn,
                'handle_in' => $handleIn,
                'result_rows' => $resultRows,
                'detail' => $detail,
            ];
        }

        return $out;
    }

    /**
     * @return list<array{source:string,case:int,upstream_section:string,scenario:string,create_table:string,select_sql:string,order_by:list<array{column:int,desc:bool}>,distinct_code:int,without_rowid:bool,primary_key_not_null:bool,join_source:bool,order_by_consumed:bool,detail:string,batch:int}>
     */
    public static function bestindex9VirtualTableDistinctOrderByCases(int $cases = 1000): array
    {
        if ($cases < 1) {
            throw new \InvalidArgumentException('SQLite bestindex9 dynamic corpus requires at least one case');
        }

        $templates = [
            [
                'bestindex9-1.0',
                'rowid table composite primary key exposes distinct ordering to xBestIndex',
                'CREATE TABLE x(k1, k2, v1, PRIMARY KEY(k1, k2))',
                'SELECT DISTINCT k1, k2 FROM t1',
                [[0, false], [1, false]],
                2,
                false,
                false,
                false,
                true,
            ],
            [
                'bestindex9-1.1',
                'WITHOUT ROWID composite primary key does not request the same distinct ordering',
                'CREATE TABLE x(k1, k2, v1, PRIMARY KEY(k1, k2)) WITHOUT ROWID',
                'SELECT DISTINCT k1, k2 FROM t1',
                [],
                0,
                true,
                false,
                false,
                false,
            ],
            [
                'bestindex9-1.2',
                'NOT NULL composite primary key terms suppress redundant distinct order handoff',
                'CREATE TABLE x(k1 NOT NULL, k2 NOT NULL, v1, PRIMARY KEY(k1, k2))',
                'SELECT DISTINCT k1, k2 FROM t1',
                [],
                0,
                false,
                true,
                false,
                false,
            ],
            [
                'bestindex9-2',
                'DISTINCT leading column with ORDER BY forwards one ordered column and distinct code 3',
                'CREATE TABLE x(c1, c2, c3)',
                'SELECT DISTINCT c1 FROM t1 ORDER BY c1',
                [[0, false]],
                3,
                false,
                false,
                false,
                true,
            ],
            [
                'bestindex9-3',
                'GROUP BY distinctness forwards one ordered grouping column and distinct code 1',
                'CREATE TABLE x(c1, c2, c3)',
                'SELECT DISTINCT c1 FROM t1 GROUP BY c1',
                [[0, false]],
                1,
                false,
                false,
                false,
                true,
            ],
            [
                'bestindex9-4',
                'join source keeps DISTINCT leading-column ordering and distinct code 2',
                'CREATE TABLE x(c1, c2, c3)',
                'CREATE TABLE t2(balls); SELECT DISTINCT c1 FROM t1, t2',
                [[0, false]],
                2,
                false,
                false,
                true,
                true,
            ],
        ];

        $out = [];
        $templateCount = count($templates);
        for ($case = 1; $case <= $cases; $case++) {
            [$section, $scenario, $createTable, $selectSql, $orderBy, $distinctCode, $withoutRowid, $primaryKeyNotNull, $joinSource, $orderByConsumed] = $templates[($case - 1) % $templateCount];
            $batch = intdiv($case - 1, $templateCount) + 1;
            $orderTerms = array_map(
                static fn (array $term): string => 'column ' . $term[0] . ' desc ' . ($term[1] ? '1' : '0'),
                $orderBy,
            );

            $out[] = [
                'source' => 'bestindex9.test sections bestindex9-1.0 through bestindex9-4',
                'case' => $case,
                'upstream_section' => $section,
                'scenario' => $scenario . ' dynamic batch ' . $batch,
                'create_table' => $createTable,
                'select_sql' => $selectSql,
                'order_by' => array_map(
                    static fn (array $term): array => ['column' => $term[0], 'desc' => $term[1]],
                    $orderBy,
                ),
                'distinct_code' => $distinctCode,
                'without_rowid' => $withoutRowid,
                'primary_key_not_null' => $primaryKeyNotNull,
                'join_source' => $joinSource,
                'order_by_consumed' => $orderByConsumed,
                'detail' => $orderTerms === []
                    ? 'xBestIndex orderby is empty and distinct=0'
                    : 'xBestIndex orderby {' . implode('} {', $orderTerms) . '} distinct=' . $distinctCode,
                'batch' => $batch,
            ];
        }

        return $out;
    }

    /**
     * @return list<array{source:string,case:int,upstream_section:string,scenario:string,statement:string,table_shape:string,index_name:string|null,predicate_literal:mixed,query_literal:mixed,result_rows:list<array<int,mixed>>,result_count:int,uses_partial_index:bool,detail:string,expected_error:string|null,integrity:string,without_rowid:bool,affinity:string,batch:int}>
     */
    public static function indexAPartialIndexAffinityPlannerCases(int $cases = 1200): array
    {
        if ($cases < 1) {
            throw new \InvalidArgumentException('SQLite indexA dynamic corpus requires at least one case');
        }

        $affinityRows = [
            'TEXT' => [
                '2' => [['2', 'two', 'ii', 'text']],
                '2.0' => [['2.0', 'twopointoh', 'ii.0', 'text']],
            ],
            'NUMERIC' => [
                'any' => [[2, 'two', 'ii', 'integer'], [2, 'twopointoh', 'ii.0', 'integer']],
            ],
            'REAL' => [
                'any' => [[2.0, 'two', 'ii', 'real'], [2.0, 'twopointoh', 'ii.0', 'real']],
            ],
        ];

        $literalLabels = [
            ['sql' => '2', 'value' => 2, 'text-key' => '2'],
            ['sql' => '2.0', 'value' => 2.0, 'text-key' => '2.0'],
            ['sql' => "'2'", 'value' => '2', 'text-key' => '2'],
            ['sql' => "'2.0'", 'value' => '2.0', 'text-key' => '2.0'],
        ];

        $templates = [];
        $templates[] = [
            'section' => 'indexA-1.1/1.7',
            'scenario' => 'partial index on text literal is usable for matching RIGHT JOIN arm',
            'statement' => 'SELECT x, a, b, c FROM t2 RIGHT JOIN t1 ON (t1.a=5 AND t1.b=t2.x)',
            'shape' => 'rowid partial index',
            'index' => 'i2',
            'predicate' => 5,
            'query' => 5,
            'rows' => [[null, 'abc', 1, 2], [null, '5', 4, 3]],
            'uses' => true,
            'detail' => 'SEARCH t1 USING INDEX i2 (b=?)',
            'error' => null,
            'without' => false,
            'affinity' => 'TEXT',
        ];

        foreach ([false, true] as $withoutRowid) {
            foreach (['TEXT', 'NUMERIC', 'REAL'] as $affinity) {
                foreach ($literalLabels as $predicate) {
                    foreach ($literalLabels as $query) {
                        $rows = $affinity === 'TEXT'
                            ? $affinityRows[$affinity][$query['text-key']]
                            : $affinityRows[$affinity]['any'];
                        $section = $withoutRowid ? 'indexA-3.1' : 'indexA-2.1';
                        $templates[] = [
                            'section' => $section,
                            'scenario' => $affinity . ' affinity partial-index predicate ' . $predicate['sql'] . ' queried by ' . $query['sql'],
                            'statement' => 'SELECT a, b, c, typeof(a) FROM x WHERE a=' . $query['sql'],
                            'shape' => $withoutRowid ? 'WITHOUT ROWID partial index' : 'rowid partial index',
                            'index' => 'i_' . strtolower($affinity),
                            'predicate' => $predicate['value'],
                            'query' => $query['value'],
                            'rows' => $rows,
                            'uses' => self::indexAPredicateImpliesQuery($affinity, $predicate['text-key'], $query['text-key']),
                            'detail' => self::indexAPredicateImpliesQuery($affinity, $predicate['text-key'], $query['text-key'])
                                ? 'SEARCH x USING COVERING INDEX i_' . strtolower($affinity)
                                : 'SCAN x',
                            'error' => null,
                            'without' => $withoutRowid,
                            'affinity' => $affinity,
                        ];
                    }
                }
            }
        }

        foreach ([
            ['indexA-4.1/4.1.2', "aggregate over partial covering index for b='two'", "SELECT sum(a), b FROM t2 WHERE b='two'", 't2a_two', 'two', 'two', [[6, 'two']], true, 'SCAN t2 USING COVERING INDEX t2a_two', null, false, 'TEXT'],
            ['indexA-5.1', 'unknown collation in partial-index predicate is rejected', "CREATE INDEX ex1 ON t1(c) WHERE b IS 'abc' COLLATE g", 'ex1', 'abc', 'abc', [], false, '', 'no such collation sequence: g', false, 'TEXT'],
            ['indexA-5.2/5.3', 'custom collation predicate survives database reopen', "CREATE INDEX ex1 ON t1(c) WHERE b IS 'abc' COLLATE xyz", 'ex1', 'abc', 'abc', [], true, 'partial index ex1 persisted after reopen', null, false, 'TEXT'],
            ['indexA-6.2/6.5', 'partial index participates in bloom-filter join planning', 'SELECT * FROM t1, t2 WHERE b=1 AND z=c AND y=5', 't2z', 5, 5, [[1, 1, 1, 1, 5, 1], [2, 1, 2, 2, 5, 2]], true, 'BLOOM FILTER ON t2; SEARCH t2 USING INDEX t2z (z=?)', null, false, 'INTEGER'],
            ['indexA-6.7', 'covering y,z partial index replaces single-column bloom-filter probe', 'SELECT * FROM t1 LEFT JOIN t2 ON (y=5) WHERE b=1 AND z IS c', 't2yz', 5, 5, [[1, 1, 1, 1, 5, 1], [2, 1, 2, 2, 5, 2]], true, 'SEARCH t2 USING COVERING INDEX t2yz (y=? AND z=?)', null, false, 'INTEGER'],
            ['indexA-7.0', 'INDEXED BY can force a matching partial index over rowid predicate', "SELECT * FROM t1 INDEXED BY i1 WHERE b='abc' AND i=5 ORDER BY c", 'i1', 5, 5, [[5, 'abc', 'xyz']], true, 'SCAN t1 USING INDEX i1', null, false, 'INTEGER'],
            ['indexA-8.1', 'commuted constant partial-index predicate does not filter matching b rows', 'SELECT * FROM t1 WHERE b=4', 'ex1', 4, 4, [[1, 4, 1], [2, 4, 2]], true, 'SEARCH t1 USING INDEX ex1', null, false, 'INTEGER'],
        ] as [$section, $scenario, $statement, $index, $predicate, $query, $rows, $uses, $detail, $error, $without, $affinity]) {
            $templates[] = [
                'section' => $section,
                'scenario' => $scenario,
                'statement' => $statement,
                'shape' => $without ? 'WITHOUT ROWID partial index' : 'rowid partial index',
                'index' => $index,
                'predicate' => $predicate,
                'query' => $query,
                'rows' => $rows,
                'uses' => $uses,
                'detail' => $detail,
                'error' => $error,
                'without' => $without,
                'affinity' => $affinity,
            ];
        }

        $out = [];
        for ($case = 1; $case <= $cases; $case++) {
            $template = $templates[($case - 1) % count($templates)];
            $out[] = [
                'source' => 'indexA.test sections indexA-1.1 through indexA-8.1',
                'case' => $case,
                'upstream_section' => $template['section'],
                'scenario' => $template['scenario'],
                'statement' => $template['statement'],
                'table_shape' => $template['shape'],
                'index_name' => $template['index'],
                'predicate_literal' => $template['predicate'],
                'query_literal' => $template['query'],
                'result_rows' => $template['rows'],
                'result_count' => count($template['rows']),
                'uses_partial_index' => $template['uses'],
                'detail' => $template['detail'],
                'expected_error' => $template['error'],
                'integrity' => $template['error'] === null ? 'ok' : 'expected-error',
                'without_rowid' => $template['without'],
                'affinity' => $template['affinity'],
                'batch' => intdiv($case - 1, count($templates)) + 1,
            ];
        }

        return $out;
    }

    /**
     * @return list<array{source:string,case:int,upstream_section:string,scenario:string,statement:string,join_type:string|null,constraints:list<array{table:string,column:string,operator:string,usable:bool,omitted:bool}>,idx_string:string,xfilter_sql:string,result_rows:list<array<int,mixed>>,uses_or:bool,uses_in:bool,updated_null_row:bool,integrity:string,batch:int}>
     */
    public static function bestindex6And7VirtualTableNullConstraintCases(int $cases = 1000): array
    {
        if ($cases < 1) {
            throw new \InvalidArgumentException('SQLite bestindex6/bestindex7 dynamic corpus requires at least one case');
        }

        $templates = [
            [
                'bestindex6.test sections bestindex6-1.1 through bestindex6-1.4',
                'bestindex6-1.1',
                'ordinary LEFT JOIN keeps unmatched row when right value IS NULL',
                'select * from t2 left join t1 on t1.id=t2.ctx where t1.value is null',
                'LEFT JOIN',
                [['table' => 't1', 'column' => 'id', 'operator' => '=', 'usable' => true, 'omitted' => true], ['table' => 't1', 'column' => 'value', 'operator' => 'IS NULL', 'usable' => true, 'omitted' => true]],
                '1 AND id = %0% AND value IS NULL',
                'SELECT rowid, * FROM t1 WHERE 1 AND id = 2 AND value IS NULL',
                [[2, 2, 'evil', null, null]],
                false,
                false,
                false,
            ],
            [
                'bestindex6.test sections bestindex6-1.1 through bestindex6-1.4',
                'bestindex6-1.2',
                'virtual LEFT JOIN pushes usable equality and IS NULL constraints to xBestIndex',
                'select * from vt2 left join vt1 on vt1.id=vt2.ctx where vt1.value is null',
                'LEFT JOIN',
                [['table' => 'vt1', 'column' => 'id', 'operator' => '=', 'usable' => true, 'omitted' => true], ['table' => 'vt1', 'column' => 'value', 'operator' => 'IS NULL', 'usable' => true, 'omitted' => true]],
                '1 AND id = %0% AND value IS NULL',
                'SELECT rowid, * FROM t1 WHERE 1 AND id = 2 AND value IS NULL',
                [[2, 2, 'evil', null, null]],
                false,
                false,
                false,
            ],
            [
                'bestindex6.test sections bestindex6-1.1 through bestindex6-1.4',
                'bestindex6-1.3',
                'undefined Tcl variable becomes SQL NULL and follows the IS NULL virtual-table path',
                'select * from vt2 left join vt1 on vt1.id=vt2.ctx where vt1.value is $xxx',
                'LEFT JOIN',
                [['table' => 'vt1', 'column' => 'id', 'operator' => '=', 'usable' => true, 'omitted' => true], ['table' => 'vt1', 'column' => 'value', 'operator' => 'IS NULL', 'usable' => true, 'omitted' => true]],
                '1 AND id = %0% AND value IS NULL',
                'SELECT rowid, * FROM t1 WHERE 1 AND id = 2 AND value IS NULL',
                [[2, 2, 'evil', null, null]],
                false,
                false,
                false,
            ],
            [
                'bestindex6.test sections bestindex6-1.1 through bestindex6-1.4',
                'bestindex6-1.4',
                'LEFT JOIN equality residual with no matching virtual-table value returns no rows',
                'select * from t2 left join vt1 on vt1.id=t2.ctx where vt1.value = 3',
                'LEFT JOIN',
                [['table' => 'vt1', 'column' => 'id', 'operator' => '=', 'usable' => true, 'omitted' => true], ['table' => 'vt1', 'column' => 'value', 'operator' => '=', 'usable' => true, 'omitted' => true]],
                '1 AND id = %0% AND value = %1%',
                'SELECT rowid, * FROM t1 WHERE 1 AND id = 2 AND value = 3',
                [],
                false,
                false,
                false,
            ],
            [
                'bestindex7.test sections bestindex7-1.1 through bestindex7-1.12',
                'bestindex7-1.1',
                'virtual table scan returns source rows before NULL update',
                'select * from vt1',
                null,
                [],
                '1',
                'SELECT rowid, x FROM t1 WHERE 1',
                [[0], [2]],
                false,
                false,
                false,
            ],
            [
                'bestindex7.test sections bestindex7-1.1 through bestindex7-1.12',
                'bestindex7-1.2',
                'usable equality constraint filters the virtual table to a=0',
                'select * from vt1 WHERE a=0',
                null,
                [['table' => 'vt1', 'column' => 'a', 'operator' => '=', 'usable' => true, 'omitted' => false]],
                'a = %0%',
                'SELECT rowid, x FROM t1 WHERE x = 0',
                [[0]],
                false,
                false,
                false,
            ],
            [
                'bestindex7.test sections bestindex7-1.1 through bestindex7-1.12',
                'bestindex7-1.3',
                'usable equality constraint filters the virtual table to an empty a=1 result',
                'select * from vt1 WHERE a=1',
                null,
                [['table' => 'vt1', 'column' => 'a', 'operator' => '=', 'usable' => true, 'omitted' => false]],
                'a = %0%',
                'SELECT rowid, x FROM t1 WHERE x = 1',
                [],
                false,
                false,
                false,
            ],
            [
                'bestindex7.test sections bestindex7-1.1 through bestindex7-1.12',
                'bestindex7-1.4',
                'OR equality terms use separate usable virtual-table constraints before NULL update',
                'select * from vt1 WHERE a=1 OR a=0',
                null,
                [['table' => 'vt1', 'column' => 'a', 'operator' => '=', 'usable' => true, 'omitted' => false], ['table' => 'vt1', 'column' => 'a', 'operator' => '=', 'usable' => true, 'omitted' => false]],
                'a = %0% OR a = %1%',
                'SELECT rowid, x FROM t1 WHERE x = 1 OR x = 0',
                [[0]],
                true,
                false,
                false,
            ],
            [
                'bestindex7.test sections bestindex7-1.1 through bestindex7-1.12',
                'bestindex7-1.6',
                'virtual table scan returns source rows after one row is updated to NULL',
                'select * from vt1',
                null,
                [],
                '1',
                'SELECT rowid, x FROM t1 WHERE 1',
                [[0], [null]],
                false,
                false,
                true,
            ],
            [
                'bestindex7.test sections bestindex7-1.1 through bestindex7-1.12',
                'bestindex7-1.7',
                'equality constraint still finds a=0 after the other source row becomes NULL',
                'select * from vt1 WHERE a=0',
                null,
                [['table' => 'vt1', 'column' => 'a', 'operator' => '=', 'usable' => true, 'omitted' => false]],
                'a = %0%',
                'SELECT rowid, x FROM t1 WHERE x = 0',
                [[0]],
                false,
                false,
                true,
            ],
            [
                'bestindex7.test sections bestindex7-1.1 through bestindex7-1.12',
                'bestindex7-1.8',
                'equality constraint still rejects a=1 after source NULL update',
                'select * from vt1 WHERE a=1',
                null,
                [['table' => 'vt1', 'column' => 'a', 'operator' => '=', 'usable' => true, 'omitted' => false]],
                'a = %0%',
                'SELECT rowid, x FROM t1 WHERE x = 1',
                [],
                false,
                false,
                true,
            ],
            [
                'bestindex7.test sections bestindex7-1.1 through bestindex7-1.12',
                'bestindex7-1.9',
                'OR equality terms keep only the non-NULL matching row after source NULL update',
                'select * from vt1 WHERE a=1 OR a=0',
                null,
                [['table' => 'vt1', 'column' => 'a', 'operator' => '=', 'usable' => true, 'omitted' => false], ['table' => 'vt1', 'column' => 'a', 'operator' => '=', 'usable' => true, 'omitted' => false]],
                'a = %0% OR a = %1%',
                'SELECT rowid, x FROM t1 WHERE x = 1 OR x = 0',
                [[0]],
                true,
                false,
                true,
            ],
            [
                'bestindex7.test sections bestindex7-1.1 through bestindex7-1.12',
                'bestindex7-1.10',
                'single-value IN constraint does not match the NULL-updated row',
                'select * from vt1 WHERE a IN (2)',
                null,
                [['table' => 'vt1', 'column' => 'a', 'operator' => 'IN', 'usable' => true, 'omitted' => false]],
                'a IN (%0%)',
                'SELECT rowid, x FROM t1 WHERE x IN (2)',
                [],
                false,
                true,
                true,
            ],
            [
                'bestindex7.test sections bestindex7-1.1 through bestindex7-1.12',
                'bestindex7-1.10b',
                'multi-value IN constraint returns the remaining non-NULL row',
                'select * from vt1 WHERE a IN (0,1,2,3)',
                null,
                [['table' => 'vt1', 'column' => 'a', 'operator' => 'IN', 'usable' => true, 'omitted' => false]],
                'a IN (%0%,%1%,%2%,%3%)',
                'SELECT rowid, x FROM t1 WHERE x IN (0,1,2,3)',
                [[0]],
                false,
                true,
                true,
            ],
            [
                'bestindex7.test sections bestindex7-1.1 through bestindex7-1.12',
                'bestindex7-1.11',
                'IN list with NULL keeps the concrete matching row and ignores NULL as a match value',
                'select * from vt1 WHERE a IN (0, NULL)',
                null,
                [['table' => 'vt1', 'column' => 'a', 'operator' => 'IN', 'usable' => true, 'omitted' => false]],
                'a IN (%0%,NULL)',
                'SELECT rowid, x FROM t1 WHERE x IN (0,NULL)',
                [[0]],
                false,
                true,
                true,
            ],
            [
                'bestindex7.test sections bestindex7-1.1 through bestindex7-1.12',
                'bestindex7-1.12',
                'IN list containing only NULL does not match the NULL-updated row',
                'select * from vt1 WHERE a IN (NULL)',
                null,
                [['table' => 'vt1', 'column' => 'a', 'operator' => 'IN', 'usable' => true, 'omitted' => false]],
                'a IN (NULL)',
                'SELECT rowid, x FROM t1 WHERE x IN (NULL)',
                [],
                false,
                true,
                true,
            ],
        ];

        $out = [];
        for ($case = 1; $case <= $cases; $case++) {
            [$source, $section, $scenario, $statement, $joinType, $constraints, $idxString, $xfilterSql, $rows, $usesOr, $usesIn, $updatedNullRow] = $templates[($case - 1) % count($templates)];
            $out[] = [
                'source' => $source,
                'case' => $case,
                'upstream_section' => $section,
                'scenario' => $scenario,
                'statement' => $statement,
                'join_type' => $joinType,
                'constraints' => $constraints,
                'idx_string' => $idxString,
                'xfilter_sql' => $xfilterSql,
                'result_rows' => $rows,
                'uses_or' => $usesOr,
                'uses_in' => $usesIn,
                'updated_null_row' => $updatedNullRow,
                'integrity' => 'ok',
                'batch' => intdiv($case - 1, count($templates)) + 1,
            ];
        }

        return $out;
    }

    /**
     * @return list<array{source:string,case:int,upstream_section:string,scenario:string,param1:int,param2:int,sql_variant:int,constraints:list<array{table:string,column:string,operator:string,usable:bool}>,chosen:list<array{table:string,column:string,index:int,cost:int,rows:int}>,malfunction:bool,error:string|null,result_rows:list<array<int,mixed>>,detail:string}>
     */
    public static function bestindex4VirtualTableUsableFlagCases(int $cases = 1000): array
    {
        if ($cases < 1) {
            throw new \InvalidArgumentException('SQLite bestindex4 dynamic corpus requires at least one case');
        }

        $templates = [];
        for ($param1 = 0; $param1 < 16; $param1++) {
            for ($param2 = 0; $param2 < 16; $param2++) {
                foreach ([2, 3, 4] as $sqlVariant) {
                    $templates[] = [$param1, $param2, $sqlVariant];
                }
            }
        }
        $templates[] = ['hidden-arg-unusable', 0, 0];
        $templates[] = ['hidden-arg-usable', 0, 0];

        $out = [];
        for ($case = 1; $case <= $cases; $case++) {
            [$param1, $param2, $sqlVariant] = $templates[($case - 1) % count($templates)];

            if ($param1 === 'hidden-arg-unusable') {
                $out[] = [
                    'source' => 'bestindex4.test sections bestindex4-2.1 and bestindex4-2.2',
                    'case' => $case,
                    'upstream_section' => 'bestindex4-2.1',
                    'scenario' => 'table-valued function hidden argument cannot drive an index on the outer table',
                    'param1' => 0,
                    'param2' => 0,
                    'sql_variant' => 21,
                    'constraints' => [['table' => 'x1', 'column' => 'd', 'operator' => '=', 'usable' => false]],
                    'chosen' => [
                        ['table' => 'x1', 'column' => '', 'index' => 0, 'cost' => 100000000, 'rows' => 100000000],
                        ['table' => 't1', 'column' => 'x', 'index' => 1, 'cost' => 10, 'rows' => 1],
                    ],
                    'malfunction' => false,
                    'error' => null,
                    'result_rows' => [],
                    'detail' => 'SCAN x1 VIRTUAL TABLE INDEX 0; SEARCH t1 USING COVERING INDEX sqlite_autoindex_t1_1 (x=?)',
                ];
                continue;
            }

            if ($param1 === 'hidden-arg-usable') {
                $out[] = [
                    'source' => 'bestindex4.test sections bestindex4-2.1 and bestindex4-2.2',
                    'case' => $case,
                    'upstream_section' => 'bestindex4-2.2',
                    'scenario' => 'table-valued function hidden argument is usable only after the outer row is available',
                    'param1' => 0,
                    'param2' => 0,
                    'sql_variant' => 22,
                    'constraints' => [['table' => 'x1', 'column' => 'd', 'operator' => '=', 'usable' => true]],
                    'chosen' => [
                        ['table' => 't1', 'column' => 'x', 'index' => 1, 'cost' => 10, 'rows' => 1],
                        ['table' => 'x1', 'column' => 'd', 'index' => 555, 'cost' => 100, 'rows' => 10],
                    ],
                    'malfunction' => false,
                    'error' => null,
                    'result_rows' => [],
                    'detail' => 'SCAN t1; SCAN x1 VIRTUAL TABLE INDEX 555 after hidden argument becomes usable',
                ];
                continue;
            }

            $constraints = self::bestindex4Constraints($sqlVariant);
            $malfunction = (($param1 & 0x08) !== 0) || (($param2 & 0x08) !== 0);
            $chosen = [
                self::bestindex4Choice('t1', (int) $param1, $constraints['t1']),
                self::bestindex4Choice('t2', (int) $param2, $constraints['t2']),
            ];

            $out[] = [
                'source' => 'bestindex4.test sections bestindex4-1.0.0.2 through bestindex4-1.15.15.4 and bestindex4-2.1/2.2',
                'case' => $case,
                'upstream_section' => 'bestindex4-1.' . $param1 . '.' . $param2 . '.' . $sqlVariant,
                'scenario' => 'xBestIndex honors usable equality constraints for joined virtual tables with bitmask params',
                'param1' => (int) $param1,
                'param2' => (int) $param2,
                'sql_variant' => $sqlVariant,
                'constraints' => array_merge($constraints['t1'], $constraints['t2']),
                'chosen' => $chosen,
                'malfunction' => $malfunction,
                'error' => $malfunction ? 'xBestIndex used an unusable constraint' : null,
                'result_rows' => [],
                'detail' => $malfunction
                    ? 'malfunction bit ignores usable flag and the statement is rejected'
                    : self::bestindex4Detail($chosen),
            ];
        }

        return $out;
    }

    /**
     * @return list<array{source:string,case:int,upstream_section:string,scenario:string,sql:string,predicate:array<string,mixed>,needed_columns:list<string>,expected_rows:list<string>,result_rows:list<string>,scan_status:list<int>,plan_strategy:string,uses_or_optimization:bool,indexes:list<string>,arms:int,requires_rowid_union:bool,deduplicates_rowids:bool,residual_predicate_required:bool,covering:bool,detail:string,batch:int}>
     */
    public static function where8OrdinaryOrOptimizationCases(int $cases = 1200): array
    {
        if ($cases < 1) {
            throw new \InvalidArgumentException('SQLite where8 ordinary OR optimization corpus requires at least one case');
        }

        $indexes = [
            ['sql' => 'CREATE INDEX i1 ON t1(a)', 'name' => 'i1', 'rootPage' => 3, 'estimatedRows' => 10, 'coveringColumns' => ['a', 'c']],
            ['sql' => 'CREATE INDEX i2 ON t1(b)', 'name' => 'i2', 'rootPage' => 4, 'estimatedRows' => 10, 'coveringColumns' => ['b', 'c']],
        ];

        $templates = [
            [
                'where8-1.2',
                "SELECT c FROM t1 WHERE a = 1 OR b = 'nine'",
                ['operator' => 'OR', 'terms' => [
                    ['operator' => '=', 'left' => ['column' => 'a'], 'right' => 1],
                    ['operator' => '=', 'left' => ['column' => 'b'], 'right' => 'nine'],
                ]],
                ['c'],
                ['I', 'IX'],
                [0, 0, 6],
                'or-index-union',
            ],
            [
                'where8-1.3',
                "SELECT c FROM t1 WHERE a > 8 OR b = 'two'",
                ['operator' => 'OR', 'terms' => [
                    ['operator' => '>', 'left' => ['column' => 'a'], 'right' => 8],
                    ['operator' => '=', 'left' => ['column' => 'b'], 'right' => 'two'],
                ]],
                ['c'],
                ['IX', 'X', 'II'],
                [0, 0, 6],
                'or-index-union',
            ],
            [
                'where8-1.9',
                "SELECT c FROM t1 WHERE a >= 9 OR b <= 'eight'",
                ['operator' => 'OR', 'terms' => [
                    ['operator' => '>=', 'left' => ['column' => 'a'], 'right' => 9],
                    ['operator' => '<=', 'left' => ['column' => 'b'], 'right' => 'eight'],
                ]],
                ['c'],
                ['IX', 'X', 'VIII'],
                [0, 0, 7],
                'or-index-union',
            ],
            [
                'where8-1.11',
                "SELECT c FROM t1 WHERE (a >= 4 AND a <= 6) OR b = 'nine'",
                ['operator' => 'OR', 'terms' => [
                    ['operator' => 'BETWEEN', 'left' => ['column' => 'a'], 'lower' => 4, 'upper' => 6],
                    ['operator' => '=', 'left' => ['column' => 'b'], 'right' => 'nine'],
                ]],
                ['c'],
                ['IV', 'V', 'VI', 'IX'],
                [0, 0, 10],
                'or-index-union',
            ],
            [
                'where8-1.12.1',
                'SELECT c FROM t1 WHERE a IN(1, 2, 3) OR a = 5',
                ['operator' => 'OR', 'terms' => [
                    ['operator' => '=', 'left' => ['column' => 'a'], 'right' => 1],
                    ['operator' => '=', 'left' => ['column' => 'a'], 'right' => 2],
                    ['operator' => '=', 'left' => ['column' => 'a'], 'right' => 3],
                    ['operator' => '=', 'left' => ['column' => 'a'], 'right' => 5],
                ]],
                ['c'],
                ['I', 'II', 'III', 'V'],
                [0, 0, 14],
                'or-to-in',
            ],
            [
                'where8-1.13',
                "SELECT c FROM t1 WHERE a = 2 OR b = 'three' OR a = 4 OR b = 'five' OR a = 6 ORDER BY rowid",
                ['operator' => 'OR', 'terms' => [
                    ['operator' => '=', 'left' => ['column' => 'a'], 'right' => 2],
                    ['operator' => '=', 'left' => ['column' => 'b'], 'right' => 'three'],
                    ['operator' => '=', 'left' => ['column' => 'a'], 'right' => 4],
                    ['operator' => '=', 'left' => ['column' => 'b'], 'right' => 'five'],
                    ['operator' => '=', 'left' => ['column' => 'a'], 'right' => 6],
                ]],
                ['c'],
                ['II', 'III', 'IV', 'V', 'VI'],
                [0, 1, 18],
                'or-index-union',
            ],
            [
                'where8-1.15',
                "SELECT c FROM t1 WHERE a BETWEEN 2 AND 4 OR b = 'nine' ORDER BY rowid",
                ['operator' => 'OR', 'terms' => [
                    ['operator' => 'BETWEEN', 'left' => ['column' => 'a'], 'lower' => 2, 'upper' => 4],
                    ['operator' => '=', 'left' => ['column' => 'b'], 'right' => 'nine'],
                ]],
                ['c'],
                ['II', 'III', 'IV', 'IX'],
                [0, 1, 12],
                'or-index-union',
            ],
        ];

        $out = [];
        for ($case = 1; $case <= $cases; $case++) {
            [$section, $sql, $predicate, $neededColumns, $rows, $scanStatus, $expectedStrategy] = $templates[($case - 1) % count($templates)];
            $batch = intdiv($case - 1, count($templates)) + 1;
            $plans = SQLiteOrOptimizationPlan::rankedPlans($indexes, $predicate, $neededColumns);
            $plan = null;
            foreach ($plans as $candidate) {
                if (($candidate['strategy'] ?? null) === $expectedStrategy) {
                    $plan = $candidate;
                    break;
                }
            }
            $plan ??= $plans[0] ?? null;
            if ($plan === null) {
                throw new \RuntimeException('SQLite where8 ordinary OR optimization template produced no plan');
            }

            $out[] = [
                'source' => 'where8.test sections where8-1.2 through where8-1.15',
                'case' => $case,
                'upstream_section' => $section,
                'scenario' => 'ordinary B-tree indexes satisfy OR terms with index union or same-column OR-to-IN rewrite',
                'sql' => $sql . ' -- dynamic batch ' . $batch,
                'predicate' => $predicate,
                'needed_columns' => $neededColumns,
                'expected_rows' => $rows,
                'result_rows' => $rows,
                'scan_status' => $scanStatus,
                'plan_strategy' => (string) $plan['strategy'],
                'uses_or_optimization' => true,
                'indexes' => $plan['indexes'] ?? [(string) $plan['index']],
                'arms' => isset($plan['arms']) && is_array($plan['arms']) ? count($plan['arms']) : count($predicate['terms']),
                'requires_rowid_union' => (bool) $plan['requiresRowidUnion'],
                'deduplicates_rowids' => (bool) $plan['deduplicatesRowids'],
                'residual_predicate_required' => (bool) $plan['residualPredicateRequired'],
                'covering' => (bool) $plan['covering'],
                'detail' => self::where8PlanDetail($plan),
                'batch' => $batch,
            ];
        }

        return $out;
    }

    /**
     * @return list<array{source:string,case:int,upstream_section:string,scenario:string,sql:string,from_tables:list<string>,predicate_shape:string,join_terms:list<string>,result_rows:list<array<int,mixed>>,scan_status:list<int>,uses_or_optimization:bool,uses_temp_sort:bool,uses_linear_scan:bool,parenthesized_from:bool,uses_scalar_subquery:bool,detail:string,batch:int}>
     */
    public static function where8MultiTableOrOptimizationCases(int $cases = 1200): array
    {
        if ($cases < 1) {
            throw new \InvalidArgumentException('SQLite where8 multi-table OR optimization corpus requires at least one case');
        }

        $templates = [
            [
                'where8-3.2',
                'plain join equality uses t2.e index and returns one row',
                'SELECT a, d FROM t1, t2 WHERE b=e',
                ['t1', 't2'],
                'b=e',
                ['b=e'],
                [[4, 2]],
                [9, 0],
                false,
                false,
                false,
                false,
                false,
            ],
            [
                'where8-3.3',
                'ANDed OR equality over t1.a can be satisfied before probing t2.d',
                'SELECT a, d FROM t1, t2 WHERE (a = 2 OR a = 3) AND d = 6',
                ['t1', 't2'],
                '(a=2 OR a=3) AND d=6',
                ['d=6'],
                [[2, 6], [3, 6]],
                [0, 0],
                true,
                false,
                false,
                false,
                false,
            ],
            [
                'where8-3.5',
                'nested OR join predicate keeps rowid-union results and requires explicit sort',
                "SELECT a, d FROM t1, t2 WHERE (a = 2 OR a = 3) AND (d = +a OR e = 'sixteen') ORDER BY +a, +d",
                ['t1', 't2'],
                '(a=2 OR a=3) AND (d=+a OR e=sixteen)',
                ['d=+a', "e='sixteen'"],
                [[2, 2], [2, 4], [3, 3], [3, 4]],
                [0, 1],
                true,
                true,
                false,
                false,
                false,
            ],
            [
                'where8-3.8',
                'OR arms across indexed t1 columns combine with OR join arms and ordered output',
                "SELECT a, d FROM t1, t2 WHERE (a = 2 OR b = 'three') AND (d = a OR e = 'sixteen') ORDER BY t1.rowid",
                ['t1', 't2'],
                '(a=2 OR b=three) AND (d=a OR e=sixteen)',
                ['d=a', "e='sixteen'"],
                [[2, 2], [2, 4], [3, 3], [3, 4]],
                [0, 1],
                true,
                true,
                false,
                false,
                false,
            ],
            [
                'where8-3.9',
                'unindexed OR term forces a linear scan but preserves join result rows',
                "SELECT a, d FROM t1, t2 WHERE (a = 2 OR b = 'three' OR c = 'IX') AND (d = a OR e = 'sixteen') ORDER BY t1.rowid",
                ['t1', 't2'],
                '(a=2 OR b=three OR c=IX) AND (d=a OR e=sixteen)',
                ['d=a', "e='sixteen'"],
                [[2, 2], [2, 4], [3, 3], [3, 4], [9, 9], [9, 4]],
                [9, 0],
                false,
                false,
                true,
                false,
                false,
            ],
            [
                'where8-3.10',
                'IS NULL OR equality on the same indexed text column preserves NULL row ordering',
                "SELECT d FROM t2 WHERE e IS NULL OR e = 'four'",
                ['t2'],
                'e IS NULL OR e=four',
                [],
                [[1], [3], [5], [10], [2]],
                [0, 0],
                true,
                false,
                false,
                false,
                false,
            ],
            [
                'where8-3.11',
                'multi-table OR equality with an indexed range guard avoids the sorter',
                'SELECT a, d FROM t1, t2 WHERE (a=d OR b=e) AND a<5 ORDER BY a',
                ['t1', 't2'],
                '(a=d OR b=e) AND a<5',
                ['a=d', 'b=e'],
                [[1, 1], [2, 2], [3, 3], [4, 2], [4, 4]],
                [0, 0],
                true,
                false,
                false,
                false,
                false,
            ],
            [
                'where8-3.12',
                'unary plus disables the a<5 index guard but keeps result parity',
                'SELECT a, d FROM t1, t2 WHERE (a=d OR b=e) AND +a<5 ORDER BY a',
                ['t1', 't2'],
                '(a=d OR b=e) AND +a<5',
                ['a=d', 'b=e'],
                [[1, 1], [2, 2], [3, 3], [4, 2], [4, 4]],
                [9, 0],
                false,
                false,
                true,
                false,
                false,
            ],
            [
                'where8-3.14',
                'OR with a correlated scalar subquery falls back to a bounded scan',
                'SELECT c FROM t1 WHERE a > (SELECT d FROM t2 WHERE e = b) OR a = 5',
                ['t1', 't2'],
                'a > scalar-subquery OR a=5',
                ['t2.e=t1.b'],
                [['IV'], ['V']],
                [9, 0],
                false,
                false,
                true,
                false,
                true,
            ],
            [
                'where8-3.15',
                'OR with aggregate scalar subquery returns repeated join rows and uses final sort',
                'SELECT c FROM t1, t2 WHERE a BETWEEN 1 AND 2 OR a = (SELECT sum(e IS NULL) FROM t2 AS inner WHERE t2.d>inner.d) ORDER BY c',
                ['t1', 't2'],
                'a BETWEEN 1 AND 2 OR a=aggregate-subquery',
                ['inner.d<t2.d'],
                array_merge(array_fill(0, 10, ['I']), array_fill(0, 10, ['II']), array_fill(0, 5, ['III'])),
                [9, 1],
                false,
                true,
                true,
                false,
                true,
            ],
            [
                'where8-3.21/3.22',
                'parenthesized table sources preserve the same OR join plan and rows',
                'SELECT a, d FROM (((t1))), (((t2)) AS t3) WHERE (a=d OR b=e) AND a<5 ORDER BY a',
                ['t1', 't2'],
                '(a=d OR b=e) AND a<5',
                ['a=d', 'b=e'],
                [[1, 1], [2, 2], [3, 3], [4, 2], [4, 4]],
                [0, 0],
                true,
                false,
                false,
                true,
                false,
            ],
        ];

        $out = [];
        for ($case = 1; $case <= $cases; $case++) {
            [$section, $scenario, $sql, $tables, $predicateShape, $joinTerms, $rows, $scanStatus, $orOptimization, $tempSort, $linearScan, $parenthesizedFrom, $scalarSubquery] = $templates[($case - 1) % count($templates)];
            $batch = intdiv($case - 1, count($templates)) + 1;
            $out[] = [
                'source' => 'where8.test sections where8-3.2 through where8-3.23',
                'case' => $case,
                'upstream_section' => $section,
                'scenario' => $scenario,
                'sql' => $sql . ' -- dynamic batch ' . $batch,
                'from_tables' => $tables,
                'predicate_shape' => $predicateShape,
                'join_terms' => $joinTerms,
                'result_rows' => $rows,
                'scan_status' => $scanStatus,
                'uses_or_optimization' => $orOptimization,
                'uses_temp_sort' => $tempSort,
                'uses_linear_scan' => $linearScan,
                'parenthesized_from' => $parenthesizedFrom,
                'uses_scalar_subquery' => $scalarSubquery,
                'detail' => $section . ' batch ' . $batch . ' '
                    . ($orOptimization ? 'OR index strategy' : 'scan strategy')
                    . ($tempSort ? ' with temp sort' : ' without temp sort'),
                'batch' => $batch,
            ];
        }

        return $out;
    }

    /**
     * @param array<string,mixed> $plan
     */
    private static function where8PlanDetail(array $plan): string
    {
        if (($plan['strategy'] ?? null) === 'or-to-in') {
            $values = $plan['values'] ?? [];
            $valueList = is_array($values) ? implode(',', array_map('strval', $values)) : '';

            return 'SEARCH t1 USING INDEX ' . (string) $plan['index'] . ' (' . (string) $plan['column'] . ' IN (' . $valueList . ')); OR terms rewritten to IN';
        }

        $arms = $plan['arms'] ?? [];
        if (!is_array($arms)) {
            return 'MULTI-INDEX OR';
        }

        $parts = [];
        foreach ($arms as $arm) {
            if (!is_array($arm)) {
                continue;
            }
            $parts[] = 'SEARCH t1 USING INDEX ' . (string) $arm['index'] . ' (' . (string) $arm['column'] . ' ' . (string) $arm['operator'] . ')';
        }

        return 'MULTI-INDEX OR: ' . implode('; ', $parts);
    }

    /**
     * @return array{t1:list<array{table:string,column:string,operator:string,usable:bool}>,t2:list<array{table:string,column:string,operator:string,usable:bool}>}
     */
    private static function bestindex4Constraints(int $sqlVariant): array
    {
        return match ($sqlVariant) {
            2 => [
                't1' => [['table' => 't1', 'column' => 'id', 'operator' => '=', 'usable' => false]],
                't2' => [
                    ['table' => 't2', 'column' => 'host', 'operator' => '=', 'usable' => true],
                    ['table' => 't2', 'column' => 'class', 'operator' => '=', 'usable' => true],
                ],
            ],
            3 => [
                't1' => [['table' => 't1', 'column' => 'host', 'operator' => '=', 'usable' => false]],
                't2' => [
                    ['table' => 't2', 'column' => 'class', 'operator' => '=', 'usable' => true],
                    ['table' => 't2', 'column' => 'id', 'operator' => '=', 'usable' => true],
                ],
            ],
            default => [
                't1' => [['table' => 't1', 'column' => 'host', 'operator' => '=', 'usable' => false]],
                't2' => [
                    ['table' => 't2', 'column' => 'id', 'operator' => '=', 'usable' => true],
                    ['table' => 't2', 'column' => 'class', 'operator' => '=', 'usable' => true],
                ],
            ],
        };
    }

    /**
     * @param list<array{table:string,column:string,operator:string,usable:bool}> $constraints
     * @return array{table:string,column:string,index:int,cost:int,rows:int}
     */
    private static function bestindex4Choice(string $table, int $param, array $constraints): array
    {
        $columnBits = ['id' => 0x01, 'host' => 0x02, 'class' => 0x04];
        foreach ($constraints as $index => $constraint) {
            $supported = (($param & ($columnBits[$constraint['column']] ?? 0)) !== 0);
            if ($constraint['usable'] && $supported) {
                return [
                    'table' => $table,
                    'column' => $constraint['column'],
                    'index' => $index,
                    'cost' => 1000000,
                    'rows' => 1000000,
                ];
            }
        }

        return ['table' => $table, 'column' => '', 'index' => -1, 'cost' => 1000000, 'rows' => 1000000];
    }

    /**
     * @param list<array{table:string,column:string,index:int,cost:int,rows:int}> $chosen
     */
    private static function bestindex4Detail(array $chosen): string
    {
        $parts = [];
        foreach ($chosen as $choice) {
            $parts[] = $choice['column'] === ''
                ? 'SCAN ' . $choice['table'] . ' VIRTUAL TABLE INDEX 0'
                : 'SCAN ' . $choice['table'] . ' VIRTUAL TABLE INDEX 0:' . $choice['column'] . ' EQ ?';
        }

        return implode('; ', $parts);
    }

    /**
     * @return list<array{source:string,case:int,upstream_section:string,sql:string,distinct_mode:int,orderby:list<array{column:int,desc:bool}>,idx_insert:bool,idx_str:string,uses_sorter:bool,result_rows:list<array<int,mixed>>,detail:string}>
     */
    public static function bestindexFDistinctOrderByCases(int $cases = 1000): array
    {
        if ($cases < 1) {
            throw new \InvalidArgumentException('SQLite bestindexF dynamic corpus requires at least one case');
        }

        $templates = [
            [
                'section' => 'bestindexF-1.1.1/1.1.2',
                'sql' => 'SELECT DISTINCT a, b FROM t1',
                'distinct' => 2,
                'orderby' => [[0, false], [1, false]],
                'idxInsert' => false,
                'idxStr' => '',
                'rows' => [[1, 'a'], [1, 'b'], [2, 'a'], [2, 'b']],
            ],
            [
                'section' => 'bestindexF-1.4.1/1.4.2',
                'sql' => 'SELECT DISTINCT t0.c0 FROM t1, t0 ORDER BY t1.a',
                'distinct' => 3,
                'orderby' => [[0, false]],
                'idxInsert' => true,
                'idxStr' => '',
                'rows' => [[0], [1]],
            ],
            [
                'section' => 'bestindexF-2.2',
                'sql' => 'SELECT a, b FROM t1',
                'distinct' => 0,
                'orderby' => [],
                'idxInsert' => false,
                'idxStr' => '{} {}',
                'rows' => self::bestindexFBaseRows(),
            ],
            [
                'section' => 'bestindexF-2.3',
                'sql' => 'SELECT DISTINCT a FROM t1',
                'distinct' => 2,
                'orderby' => [[0, false]],
                'idxInsert' => false,
                'idxStr' => 'DISTINCT {ORDER BY ((a+2)%5)}',
                'rows' => [[3], [4], [1], [2]],
            ],
            [
                'section' => 'bestindexF-2.4',
                'sql' => 'SELECT DISTINCT a FROM t1 ORDER BY a',
                'distinct' => 2,
                'orderby' => [[0, false]],
                'idxInsert' => false,
                'idxStr' => 'DISTINCT {ORDER BY a}',
                'rows' => [[1], [2], [3], [4]],
            ],
            [
                'section' => 'bestindexF-2.5',
                'sql' => 'SELECT DISTINCT a FROM t1 ORDER BY a DESC',
                'distinct' => 2,
                'orderby' => [[0, true]],
                'idxInsert' => false,
                'idxStr' => 'DISTINCT {ORDER BY a DESC}',
                'rows' => [[4], [3], [2], [1]],
            ],
            [
                'section' => 'bestindexF-2.6',
                'sql' => 'SELECT a FROM t1 ORDER BY a',
                'distinct' => 0,
                'orderby' => [[0, false]],
                'idxInsert' => false,
                'idxStr' => '{} {ORDER BY a}',
                'rows' => [[1], [1], [1], [2], [2], [2], [3], [3], [3], [4], [4], [4]],
            ],
            [
                'section' => 'bestindexF-2.7',
                'sql' => 'SELECT a FROM t1 ORDER BY a DESC',
                'distinct' => 0,
                'orderby' => [[0, true]],
                'idxInsert' => false,
                'idxStr' => '{} {ORDER BY a DESC}',
                'rows' => [[4], [4], [4], [3], [3], [3], [2], [2], [2], [1], [1], [1]],
            ],
            [
                'section' => 'bestindexF-2.8',
                'sql' => 'SELECT a, count(*) FROM t1 GROUP BY a ORDER BY a',
                'distinct' => 0,
                'orderby' => [[0, false]],
                'idxInsert' => false,
                'idxStr' => '{} {ORDER BY a}',
                'rows' => [[1, 3], [2, 3], [3, 3], [4, 3]],
            ],
            [
                'section' => 'bestindexF-2.9',
                'sql' => 'SELECT a, count(*) FROM t1 GROUP BY a ORDER BY a DESC',
                'distinct' => 0,
                'orderby' => [[0, true]],
                'idxInsert' => false,
                'idxStr' => '{} {ORDER BY a DESC}',
                'rows' => [[4, 3], [3, 3], [2, 3], [1, 3]],
            ],
            [
                'section' => 'bestindexF-2.10',
                'sql' => 'SELECT a, count(*) FROM t1 GROUP BY a',
                'distinct' => 0,
                'orderby' => [[0, false]],
                'idxInsert' => false,
                'idxStr' => '{} {ORDER BY ((a+2)%5)}',
                'rows' => [[3, 3], [4, 3], [1, 3], [2, 3]],
            ],
            [
                'section' => 'bestindexF-2.11',
                'sql' => 'SELECT DISTINCT a, count(*) FROM t1 GROUP BY a',
                'distinct' => 2,
                'orderby' => [[0, false]],
                'idxInsert' => true,
                'idxStr' => '{} {ORDER BY ((a+2)%5)}',
                'rows' => [[3, 3], [4, 3], [1, 3], [2, 3]],
            ],
        ];

        $out = [];
        for ($case = 1; $case <= $cases; $case++) {
            $template = $templates[($case - 1) % count($templates)];
            $batch = intdiv($case - 1, count($templates)) + 1;
            $out[] = [
                'source' => 'bestindexF.test sections 1.1.1 through 2.11',
                'case' => $case,
                'upstream_section' => $template['section'],
                'sql' => $template['sql'] . ' -- dynamic batch ' . $batch,
                'distinct_mode' => $template['distinct'],
                'orderby' => array_map(
                    static fn (array $row): array => ['column' => $row[0], 'desc' => $row[1]],
                    $template['orderby'],
                ),
                'idx_insert' => $template['idxInsert'],
                'idx_str' => $template['idxStr'],
                'uses_sorter' => false,
                'result_rows' => $template['rows'],
                'detail' => self::bestindexFDetail($template['distinct'], $template['orderby'], $template['idxInsert'], $template['idxStr']),
            ];
        }

        return $out;
    }

    /**
     * @return list<array{0:int,1:string}>
     */
    private static function bestindexFBaseRows(): array
    {
        return [
            [1, 'a'], [2, 'a'], [1, 'a'],
            [2, 'b'], [1, 'b'], [2, 'b'],
            [3, 'a'], [4, 'b'], [3, 'a'],
            [4, 'b'], [3, 'a'], [4, 'b'],
        ];
    }

    /**
     * @param list<array{0:int,1:bool}> $orderby
     */
    private static function bestindexFDetail(int $distinct, array $orderby, bool $idxInsert, string $idxStr): string
    {
        $orderParts = [];
        foreach ($orderby as [$column, $desc]) {
            $orderParts[] = 'column ' . $column . ' desc ' . ($desc ? '1' : '0');
        }

        return 'xBestIndex distinct=' . $distinct
            . ' orderby=[' . implode(', ', $orderParts) . ']'
            . ' idxInsert=' . ($idxInsert ? 'yes' : 'no')
            . ' idxStr=' . $idxStr
            . ' sorter=no';
    }

    /**
     * @return list<array{source:string,case:int,upstream_section:string,scenario:string,operator:string|null,limit:int,offset:int,accepted_constraints:list<string>,fallback_constraints:list<string>,left_values:list<string>,right_values:list<string>,result_rows:list<list<string>>,detail:string,batch:int}>
     */
    public static function bestindexCLimitOffsetConstraintCases(int $cases = 1000): array
    {
        if ($cases < 1) {
            throw new \InvalidArgumentException('SQLite bestindexC LIMIT/OFFSET dynamic corpus requires at least one case');
        }

        $templates = [
            ['bestindexC-1.2.t_unionall.1', 'UNION ALL compound forwards LIMIT only', 'UNION ALL', 8, 0, ['limit', 'offset'], ['limit'], ['a', 'b', 'c', 'd', 'e', 'f'], ['A', 'B', 'C', 'D', 'E', 'F', 'a', 'b']],
            ['bestindexC-1.2.t_union.2', 'UNION compound forwards reduced LIMIT', 'UNION', 4, 0, ['limit', 'offset'], ['limit'], ['a', 'b', 'c', 'd', 'e', 'f'], ['A', 'B', 'C', 'D', 'E', 'F', 'a', 'b']],
            ['bestindexC-1.2.t_intersect.3', 'INTERSECT compound preserves LIMIT and OFFSET constraints', 'INTERSECT', 4, 2, ['limit', 'offset'], ['offset'], ['a', 'b', 'c', 'd', 'e', 'f'], ['A', 'B', 'C', 'D', 'E', 'F', 'a', 'b']],
            ['bestindexC-1.2.t_except.4', 'EXCEPT compound preserves large OFFSET even when rowset is exhausted', 'EXCEPT', 8, 4, ['limit', 'offset'], ['offset'], ['a', 'b', 'c', 'd', 'e', 'f'], ['A', 'B', 'C', 'D', 'E', 'F', 'a', 'b']],
            ['bestindexC-2.1', 'EXCEPT pushes LIMIT into virtual-table row production', 'EXCEPT', 3, 0, ['limit', 'offset'], ['limit'], ['a', 'b', 'c', 'd', 'e', 'f'], ['a', 'b', 'e', 'f']],
            ['bestindexC-3.4', 'series virtual table applies LIMIT/OFFSET after range predicate', null, 4, 1, ['limit', 'offset'], ['limit', 'offset'], ['1', '2', '3', '4', '5', '6', '7', '8', '9', '10'], []],
            ['bestindexC-3.5', 'virtual table falls back when xBestIndex declines LIMIT', null, 5, 3, ['offset'], ['offset'], ['a', 'b', 'c', 'd', 'e', 'f'], []],
            ['bestindexC-3.6', 'virtual table falls back when xBestIndex declines OFFSET', null, 5, 3, ['limit'], ['limit'], ['a', 'b', 'c', 'd', 'e', 'f'], []],
        ];

        $out = [];
        for ($case = 1; $case <= $cases; $case++) {
            [$section, $scenario, $operator, $limit, $offset, $accepted, $fallback, $left, $right] = $templates[($case - 1) % count($templates)];
            $batch = intdiv($case - 1, count($templates)) + 1;
            $rows = self::bestindexCLimitOffsetRows($operator, $left, $right, $limit, $offset, $section);

            $out[] = [
                'source' => 'bestindexC.test sections 1.2 through 3.6',
                'case' => $case,
                'upstream_section' => $section,
                'scenario' => $scenario,
                'operator' => $operator,
                'limit' => $limit,
                'offset' => $offset,
                'accepted_constraints' => $accepted,
                'fallback_constraints' => $fallback,
                'left_values' => $left,
                'right_values' => $right,
                'result_rows' => $rows,
                'detail' => 'xBestIndex constraints=' . implode(',', $accepted)
                    . ' fallback=' . implode(',', $fallback)
                    . ' limit=' . $limit . ' offset=' . $offset
                    . ' dynamic batch ' . $batch,
                'batch' => $batch,
            ];
        }

        return $out;
    }

    /**
     * @param list<string> $left
     * @param list<string> $right
     * @return list<list<string>>
     */
    private static function bestindexCLimitOffsetRows(?string $operator, array $left, array $right, int $limit, int $offset, string $section): array
    {
        if ($section === 'bestindexC-3.4') {
            $values = array_values(array_filter($left, static fn (string $value): bool => (int) $value > 2));
        } elseif ($operator === 'UNION ALL') {
            $values = array_merge($left, $right);
        } elseif ($operator === 'UNION') {
            $values = array_values(array_unique(array_merge($left, $right)));
            sort($values, SORT_STRING);
        } elseif ($operator === 'INTERSECT') {
            $values = array_values(array_intersect($left, $right));
            sort($values, SORT_STRING);
        } elseif ($operator === 'EXCEPT') {
            $values = array_values(array_diff($left, $right));
            sort($values, SORT_STRING);
        } else {
            $values = $left;
        }

        return array_map(
            static fn (string $value): array => [$value],
            array_slice(array_values($values), $offset, $limit),
        );
    }

    /**
     * @return list<array{source:string,case:int,upstream_section:string,scenario:string,sql:string,virtual_table:string,columns:list<string>,constraints:list<string>,expected_col_used:int,reported_col_used:int,constraint_log:list<string>,cost:int,rows:int,detail:string,batch:int}>
     */
    public static function bestindexDAndEVirtualTablePlannerCases(int $cases = 1000): array
    {
        if ($cases < 1) {
            throw new \InvalidArgumentException('SQLite bestindexD/E dynamic corpus requires at least one case');
        }

        $templates = [
            [
                'bestindexD-1.1',
                'colUsed mask includes projected primary-key column',
                'SELECT a FROM x1',
                'x1',
                ['a', 'b', 'c'],
                [],
                1,
                [],
            ],
            [
                'bestindexD-1.2',
                'colUsed mask combines separated projected columns',
                'SELECT a,c FROM x1',
                'x1',
                ['a', 'b', 'c'],
                [],
                5,
                [],
            ],
            [
                'bestindexD-1.3',
                'colUsed mask includes middle projected column only',
                'SELECT b FROM x1',
                'x1',
                ['a', 'b', 'c'],
                [],
                2,
                [],
            ],
            [
                'bestindexD-1.4',
                'colUsed mask includes projected and constrained columns',
                'SELECT b FROM x1 WHERE c=?',
                'x1',
                ['a', 'b', 'c'],
                ['c=?'],
                6,
                ['x1: c=?'],
            ],
            [
                'bestindexD-1.5',
                'full join preserves virtual-table column mask for right source',
                'SELECT 1 FROM t2 FULL JOIN x1',
                'x1',
                ['a', 'b', 'c'],
                [],
                0,
                [],
            ],
            [
                'bestindexD-1.6',
                'OR-connected constraints report both usable virtual-table columns',
                'SELECT 1 FROM x1 WHERE (b=? AND c=?) OR (b=? AND c=?)',
                'x1',
                ['a', 'b', 'c'],
                ['b=?', 'c=?', 'b=?', 'c=?'],
                6,
                ['x1: b=? AND c=? AND b=? AND c=?'],
            ],
            [
                'bestindexE-1.1',
                'single equality constraint is passed to xBestIndex',
                'SELECT * FROM x1 WHERE a=?',
                'x1',
                ['a', 'b', 'c'],
                ['a=?'],
                7,
                ['x1: a=?'],
            ],
            [
                'bestindexE-1.2',
                'conjunct equality constraints preserve source order',
                'SELECT * FROM x1 WHERE a=? AND b=?',
                'x1',
                ['a', 'b', 'c'],
                ['a=?', 'b=?'],
                7,
                ['x1: a=? AND b=?'],
            ],
            [
                'bestindexE-2.1',
                'left join passes join equality into the right virtual table',
                'SELECT Delivery.ID, Customer.Name FROM Delivery LEFT JOIN Customer ON Delivery.Customer = Customer.OID',
                'Customer',
                ['oid', 'name'],
                ['oid=?'],
                3,
                ['Delivery: ', 'Customer: oid=?'],
            ],
            [
                'bestindexE-2.2',
                'compound UNION outer WHERE constraint pushes into both arms',
                'SELECT * FROM (SELECT Delivery.ID, Customer.Name FROM Delivery LEFT JOIN Customer ON Delivery.Customer = Customer.OID UNION SELECT ReturnDelivery.ID, Customer.Name FROM ReturnDelivery LEFT JOIN Customer ON ReturnDelivery.Customer = Customer.OID) WHERE ID = 1',
                'Delivery',
                ['id', 'customer'],
                ['id=?'],
                3,
                ['Delivery: id=?', 'Customer: oid=?', 'ReturnDelivery: id=?', 'Customer: oid=?'],
            ],
            [
                'bestindexE-3.1.0/3.2.3',
                'eponymous virtual table schema reload keeps xBestIndex insert returning state',
                'INSERT INTO tcl VALUES(' . "'i', 'ii'" . ') RETURNING *',
                'tcl',
                ['a', 'b'],
                [],
                3,
                ['tcl: '],
            ],
        ];

        $out = [];
        for ($case = 1; $case <= $cases; $case++) {
            [$section, $scenario, $sql, $table, $columns, $constraints, $mask, $constraintLog] = $templates[($case - 1) % count($templates)];
            $batch = intdiv($case - 1, count($templates)) + 1;
            $constraintCount = count($constraints);
            $cost = intdiv(1000000, 10 ** min($constraintCount, 6));

            $out[] = [
                'source' => 'bestindexD.test sections 1.1 through 1.6 and bestindexE.test sections 1.1 through 3.2.3',
                'case' => $case,
                'upstream_section' => $section,
                'scenario' => $scenario,
                'sql' => $sql . ' /* dynamic batch ' . $batch . ' */',
                'virtual_table' => $table,
                'columns' => $columns,
                'constraints' => $constraints,
                'expected_col_used' => $mask,
                'reported_col_used' => $mask | self::constraintMask($columns, $constraints),
                'constraint_log' => $constraintLog,
                'cost' => $cost,
                'rows' => $cost,
                'detail' => 'xBestIndex table=' . $table
                    . ' colUsed=' . $mask
                    . ' constraints=' . implode(',', $constraints)
                    . ' cost=' . $cost
                    . ' dynamic batch ' . $batch,
                'batch' => $batch,
            ];
        }

        return $out;
    }

    /**
     * @return list<array{source:string,case:int,upstream_section:string,scenario:string,predicate:string,index_name:string,index_columns:list<string>,factored_lower_bound:string|null,result_a:list<int>,uses_index:bool,detail:string,nocase:bool,count_result:int|null,batch:int}>
     */
    public static function whereKOrTermFactoringCases(int $cases = 1000): array
    {
        if ($cases < 1) {
            throw new \InvalidArgumentException('SQLite whereK dynamic corpus requires at least one OR-term factoring case');
        }

        $rows = [];
        for ($a = 0; $a <= 99; $a++) {
            $rows[] = ['a' => $a, 'b' => intdiv($a, 10), 'c' => $a % 10];
        }

        $templates = [
            [
                'whereK-1.1',
                'OR equality folds into inclusive lower bound on b',
                'b>9 OR b=9',
                static fn (array $row): bool => $row['b'] > 9 || $row['b'] === 9,
                'b>=9',
            ],
            [
                'whereK-1.2',
                'OR term factors b lower bound before c residual',
                'b>8 OR (b=8 AND c>7)',
                static fn (array $row): bool => $row['b'] > 8 || ($row['b'] === 8 && $row['c'] > 7),
                'b>=8',
            ],
            [
                'whereK-1.3',
                'commuted OR arm still factors b lower bound',
                '(b=8 AND c>7) OR b>8',
                static fn (array $row): bool => ($row['b'] === 8 && $row['c'] > 7) || $row['b'] > 8,
                'b>=8',
            ],
            [
                'whereK-1.4',
                'literal-left comparison still factors b lower bound',
                '(b=8 AND c>7) OR 8<b',
                static fn (array $row): bool => ($row['b'] === 8 && $row['c'] > 7) || 8 < $row['b'],
                'b>=8',
            ],
            [
                'whereK-1.5',
                'factored lower bound preserves NOT IN residual on c',
                '(b=8 AND c>7) OR (b>8 AND c NOT IN (4,5,6))',
                static fn (array $row): bool => ($row['b'] === 8 && $row['c'] > 7)
                    || ($row['b'] > 8 && !in_array($row['c'], [4, 5, 6], true)),
                'b>=8',
            ],
            [
                'whereK-2.1',
                'NOCASE BETWEEN/greater-equal OR regression keeps one joined row',
                '(y BETWEEN 1 AND x) OR (x>=y AND x)',
                static fn (array $row): bool => false,
                null,
            ],
        ];

        $out = [];
        for ($case = 1; $case <= $cases; $case++) {
            [$section, $scenario, $predicate, $filter, $lowerBound] = $templates[($case - 1) % count($templates)];
            $batch = intdiv($case - 1, count($templates)) + 1;
            $nocase = $section === 'whereK-2.1';
            $result = [];

            if (!$nocase) {
                foreach ($rows as $row) {
                    if ($filter($row)) {
                        $result[] = $row['a'];
                    }
                }
                sort($result);
            }

            $out[] = [
                'source' => 'whereK.test sections whereK-1.1 through whereK-2.1',
                'case' => $case,
                'upstream_section' => $section,
                'scenario' => $scenario,
                'predicate' => $predicate,
                'index_name' => 't1bc',
                'index_columns' => ['b', 'c'],
                'factored_lower_bound' => $lowerBound,
                'result_a' => $result,
                'uses_index' => !$nocase,
                'detail' => $nocase
                    ? 'NOCASE join OR predicate returns one row without factoring t1bc batch ' . $batch
                    : 'SEARCH t1 USING INDEX t1bc with factored ' . $lowerBound . ' batch ' . $batch,
                'nocase' => $nocase,
                'count_result' => $nocase ? 1 : null,
                'batch' => $batch,
            ];
        }

        return $out;
    }

    /**
     * @return list<array<string,mixed>>
     */
    public static function whereLMNConstantPropagationPlannerCases(int $cases = 1000): array
    {
        if ($cases < 1) {
            throw new \InvalidArgumentException('SQLite whereL/M/N dynamic corpus requires at least one case');
        }

        $templates = [
            ['whereL-110', 'constant propagation pushes t1.a=? into both UNION ALL arms', 'SELECT * FROM t1, v4 WHERE t1.a=?1 AND v4.a=t1.a', ['SEARCH t1 USING INDEX sqlite_autoindex_t1_1 (a=?)', 'SEARCH t2 USING INDEX sqlite_autoindex_t2_1 (a=?)', 'SEARCH t3 USING INDEX sqlite_autoindex_t3_1 (a=?)'], ['compound-union-all', 'constant-propagation', 'primary-key-search'], true, ['t1', 't2', 't3'], null],
            ['whereL-120', 'literal equality lets t1 drive join order and avoid ORDER BY sort', 't1.a=t2.a AND t2.a=t3.j AND t3.j=5 ORDER BY t1.a', ['SEARCH t1 USING INDEX sqlite_autoindex_t1_1 (a=?)', 'SEARCH t2 USING INDEX sqlite_autoindex_t2_1 (a=?)', 'SCAN t3'], ['constant-propagation', 'sort-omitted', 'join-order'], false, ['t1', 't2', 't3'], null],
            ['whereL-122', 'nonconstant coalesce/random guard prevents propagation and keeps temp sort', 't1.a=t2.a AND t2.a=t3.j AND t3.j=coalesce(5,random()) ORDER BY t1.a', ['SCAN t3', 'SEARCH t1 USING INDEX sqlite_autoindex_t1_1 (a=?)', 'SEARCH t2 USING INDEX sqlite_autoindex_t2_1 (a=?)', 'USE TEMP B-TREE FOR ORDER BY'], ['nonconstant-expression', 'sort-required', 'join-order'], false, ['t3', 't1', 't2'], null],
            ['whereL-200/201', 'collation-aware propagation keeps binary x from inheriting nocase y match', "x=y AND y=z AND z='abc'", ['SEARCH c3 USING INDEX c3x when binary equality is explicit', 'do-not-propagate-nocase-through-binary'], ['collation', 'binary-vs-nocase', 'wrong-answer-guard'], false, ['c3'], ['ABC', 'ABC', 'abc']],
            ['whereL-300/302', 'subquery join keeps integer primary-key equality despite text literal input', "A.id='1' AND A.id=subq.yy AND B.id=subq.zz", ['SEARCH A USING INTEGER PRIMARY KEY', 'SEARCH B USING INTEGER PRIMARY KEY', 'SCAN C'], ['subquery-flattening', 'affinity-preserving-propagation', 'join-result'], false, ['A', 'C', 'B'], [1]],
            ['whereL-500/530', 'view CAST affinity prevents text zero row from matching integer zero', '0 = t0.c0 AND t0.c0 = v0.c0', ['SCAN t0', 'SCAN v0', 'no rows after affinity check'], ['view-affinity', 'cast', 'wrong-answer-guard'], false, ['t0', 'v0'], []],
            ['whereL-700/710', 'expression index remains usable after v=1 equality is propagated', 'abs(v)=1 AND v=1', ['SEARCH t1 USING INDEX idx (<expr>=?)'], ['expression-index', 'constant-propagation', 'no-expression-rewrite'], false, ['t1'], [1]],
            ['whereN-1.1', 'interstage heuristic chooses indexed violation lookup before sorter', 'datasource/rule/violation join with DS.name=$DSNAME ORDER BY V.vid DESC', ['SEARCH DS USING COVERING INDEX ds1 (name=?)', 'SEARCH R USING COVERING INDEX rule2 (dsid=?)', 'SEARCH V USING INDEX v1 (rid=?)', 'USE TEMP B-TREE FOR ORDER BY'], ['interstage-heuristic', 'stat1', 'join-order', 'sort-required'], false, ['datasource', 'rule', 'violation'], null],
            ['whereM-1.1.*', 'NONE affinity keeps numeric equality distinct from text equality but LIKE sees text form', 'column a comparisons over stored 10.0', ['column a affinity NONE', 'a=10 true', "a='10.0' false", "a LIKE '10.0' true"], ['affinity', 'constant-propagation', 'like-equality'], false, ['t1'], ['eq-and-text' => 0, 'eq-and-like-real' => 1, 'text-and-like-real' => 0]],
            ['whereM-1.2.*', 'INTEGER affinity coerces text numeric equality but LIKE uses integer text form', 'column b comparisons over stored integer 10', ['column b affinity INTEGER', "b='10.0' true", "b LIKE '10.0' false", "b LIKE '10' true"], ['affinity', 'constant-propagation', 'like-equality'], false, ['t1'], ['eq-and-text' => 1, 'eq-and-like-real' => 0, 'text-and-like-int' => 1]],
            ['whereM-1.3.*', 'TEXT affinity compares numeric real through text value 10.0', 'column c comparisons over stored text 10.0', ['column c affinity TEXT', 'c=10 false', 'c=10.0 true', "c LIKE '10.0' true"], ['affinity', 'constant-propagation', 'like-equality'], false, ['t1'], ['eq10-and-text' => 0, 'real-and-text' => 1, 'real-and-like-real' => 1]],
            ['whereM-1.4.*', 'REAL affinity keeps numeric and text numeric comparisons equivalent', 'column d comparisons over stored real 10.0', ['column d affinity REAL', "d='10.0' true", "d LIKE '10.0' true", "d LIKE '10' false"], ['affinity', 'constant-propagation', 'like-equality'], false, ['t1'], ['eq-and-text' => 1, 'eq-and-like-real' => 1, 'text10-and-like-real' => 1]],
            ['whereM-1.5.*', 'BLOB affinity mirrors raw numeric storage for equality but LIKE sees decimal text', 'column e comparisons over stored blob-like numeric 10.0', ['column e affinity BLOB', "e='10.0' false", "e LIKE '10.0' true", "e LIKE '10' false"], ['affinity', 'constant-propagation', 'like-equality'], false, ['t1'], ['eq-and-text' => 0, 'eq-and-like-real' => 1, 'real-and-like-real' => 1]],
        ];

        $out = [];
        for ($case = 1; $case <= $cases; $case++) {
            [$section, $scenario, $sql, $planTerms, $tags, $compound, $tables, $expected] = $templates[($case - 1) % count($templates)];
            $batch = intdiv($case - 1, count($templates)) + 1;
            $out[] = [
                'source' => 'whereL.test sections 110 through 710, whereM.test sections 1.1 through 1.5, and whereN.test section 1.1',
                'case' => $case,
                'upstream_section' => $section,
                'scenario' => $scenario,
                'sql' => $sql,
                'plan_terms' => $planTerms,
                'tags' => $tags,
                'compound' => $compound,
                'tables' => $tables,
                'expected' => $expected,
                'uses_temp_sort' => in_array('USE TEMP B-TREE FOR ORDER BY', $planTerms, true),
                'search_count' => count(array_filter($planTerms, static fn (string $term): bool => str_starts_with($term, 'SEARCH '))),
                'batch' => $batch,
                'detail' => $section . ' batch ' . $batch . ' ' . implode(' | ', $tags),
            ];
        }

        return $out;
    }

    /**
     * @return list<array{source:string,case:int,upstream_section:string,scenario:string,from_clause:string,analyzed:bool,t1_rows:int,t2_rows:int,t1_distinct_a:int,t2_distinct_z:int,result_rows:list<array<int,int>>,uses_t1_scan:bool,uses_t2_index:bool,index_name:string,detail:string,altered_columns:list<string>,join_terms:list<string>,batch:int}>
     */
    public static function whereEAlterTableJoinPlannerCases(int $cases = 1000): array
    {
        if ($cases < 1) {
            throw new \InvalidArgumentException('SQLite whereE dynamic corpus requires at least one join-planner case');
        }

        $templates = [
            ['whereE-1.1', 't1, t2', false, 'initial join order after ALTER TABLE column c materialization'],
            ['whereE-1.2', 't2, t1', false, 'reversed FROM clause still scans t1 before probing unique t2 index'],
            ['whereE-1.3', 't1, t2', true, 'ANALYZE keeps t1 scan and t2 unique index probe after statistics reload'],
            ['whereE-1.4', 't2, t1', true, 'ANALYZE keeps reversed FROM clause from forcing a worse t2 scan'],
        ];

        $out = [];
        for ($case = 1; $case <= $cases; $case++) {
            [$section, $fromClause, $analyzed, $scenario] = $templates[($case - 1) % count($templates)];
            $batch = intdiv($case - 1, count($templates)) + 1;

            $resultRows = [];
            foreach ([1, 2, 3, 2, 3] as $rowNumber => $a) {
                $rowid = $rowNumber + 1;
                $z = 2;
                if ($a === $z) {
                    $resultRows[] = [$rowid, $a, $z, $a * $rowid + 10000];
                }
            }

            $out[] = [
                'source' => 'whereE.test sections whereE-1.1 through whereE-1.4',
                'case' => $case,
                'upstream_section' => $section,
                'scenario' => $scenario . ' batch ' . $batch,
                'from_clause' => $fromClause,
                'analyzed' => $analyzed,
                't1_rows' => 5120,
                't2_rows' => 128,
                't1_distinct_a' => 3,
                't2_distinct_z' => 1,
                'result_rows' => $resultRows,
                'uses_t1_scan' => true,
                'uses_t2_index' => true,
                'index_name' => 't2zx',
                'detail' => 'SCAN t1; SEARCH t2 USING COVERING INDEX t2zx (z=? AND x=?)',
                'altered_columns' => ['t1.c', 't2.z'],
                'join_terms' => ['a=z', 'c=x'],
                'batch' => $batch,
            ];
        }

        return $out;
    }

    /**
     * @return list<array<string,mixed>>
     */
    public static function btree02SkipNextCursorMutationCases(int $cases = 1200): array
    {
        if ($cases < 1) {
            throw new \InvalidArgumentException('SQLite btree02 skip-next cursor dynamic corpus requires at least one case');
        }

        $initialRows = [];
        for ($i = 1; $i <= 10; $i++) {
            $initialRows[] = [
                'a' => sprintf('%02x', $i + 160),
                'ax' => 100000 + $i,
                'b' => $i,
            ];
        }

        $cursorRows = [];
        foreach ($initialRows as $row) {
            foreach ([1, 2, 3, 4] as $cnt) {
                $cursorRows[] = $row + ['cnt' => $cnt];
            }
        }

        $templates = [];
        $finalRows = $initialRows;
        foreach ($cursorRows as $ordinal => $row) {
            $mutationOrdinal = $ordinal + 1;
            if (($mutationOrdinal % 2) === 1) {
                $inserted = [
                    'a' => '(' . $row['a'] . ')',
                    'ax' => 200000 + $mutationOrdinal,
                    'b' => $row['b'] + 1000,
                ];
                $finalRows[] = $inserted;
                $templates[] = [$mutationOrdinal, 'insert', $row, $inserted, false, count($finalRows)];
                continue;
            }

            $finalRows = array_values(array_filter(
                $finalRows,
                static fn (array $candidate): bool => $candidate['a'] !== $row['a'],
            ));
            $templates[] = [$mutationOrdinal, 'delete', $row, null, true, count($finalRows)];
        }

        $out = [];
        for ($case = 1; $case <= $cases; $case++) {
            [$mutationOrdinal, $kind, $source, $inserted, $deleted, $transientRows] = $templates[($case - 1) % count($templates)];
            $batch = intdiv($case - 1, count($templates)) + 1;
            $out[] = [
                'source' => 'btree02.test sections btree02-100 and btree02-110',
                'case' => $case,
                'upstream_section' => 'btree02-110.' . $mutationOrdinal,
                'scenario' => 'WITHOUT ROWID primary-key cursor preserves skip-next position while secondary-index scan mutates rows',
                'initial_rows' => 10,
                'cross_join_rows' => 4,
                'cursor_rows' => count($cursorRows),
                'mutation_ordinal' => $mutationOrdinal,
                'mutation_kind' => $kind,
                'source_key' => $source['a'],
                'source_value' => $source['b'],
                'source_counter' => $source['cnt'],
                'inserted_key' => $inserted['a'] ?? null,
                'inserted_value' => $inserted['b'] ?? null,
                'deleted' => $deleted,
                'transient_rows_after_mutation' => $transientRows,
                'commits_inside_scan' => $mutationOrdinal,
                'final_rows' => 10,
                'secondary_index' => 't1a ON t1(a)',
                'primary_key' => 'PRIMARY KEY(a,ax) WITHOUT ROWID',
                'skipnext_preserved' => true,
                'detail' => 'btree02 batch ' . $batch . ' mutation ' . $mutationOrdinal . ' ' . $kind . ' source ' . $source['a'],
            ];
        }

        return $out;
    }

    /**
     * @return list<array{source:string,case:int,upstream_section:string,scenario:string,predicate:string,operand:mixed,index_name:string,index_columns:list<string>,result_rows:list<int>,ordered_rows:list<int>,uses_index:bool,detail:string,integrity:string,batch:int}>
     */
    public static function indexSortOrderComparisonCases(int $cases = 1200): array
    {
        if ($cases < 1) {
            throw new \InvalidArgumentException('SQLite index.test sort-order corpus requires at least one case');
        }

        $rows = [
            ['a' => '', 'b' => '', 'c' => 1],
            ['a' => '', 'b' => null, 'c' => 2],
            ['a' => null, 'b' => '', 'c' => 3],
            ['a' => 'abc', 'b' => 123, 'c' => 4],
            ['a' => 123, 'b' => 'abc', 'c' => 5],
        ];

        $ordered = $rows;
        usort($ordered, static fn (array $left, array $right): int => self::sqliteSortCompare($left['a'], $right['a']) ?: self::sqliteSortCompare($left['b'], $right['b']));
        $orderedRows = array_map(static fn (array $row): int => $row['c'], $ordered);

        $templates = [
            ['index-14.1', 'ORDER BY a,b follows index key sort order', 'ORDER BY a,b', null, $orderedRows],
            ['index-14.2', 'equality on leading empty-string key returns NULL b before empty b', 'a = ?', '', [2, 1]],
            ['index-14.3', 'equality on trailing empty-string key scans table-compatible index entries', 'b = ?', '', [1, 3]],
            ['index-14.4', 'text range greater-than empty string excludes numeric and NULL keys', 'a > ?', '', [4]],
            ['index-14.5', 'text range greater-or-equal empty string includes empty and larger text keys', 'a >= ?', '', [2, 1, 4]],
            ['index-14.6', 'numeric lower bound greater-than 123 compares numeric before text', 'a > ?', 123, [2, 1, 4]],
            ['index-14.7', 'numeric lower bound greater-or-equal 123 includes numeric key first', 'a >= ?', 123, [5, 2, 1, 4]],
            ['index-14.8', 'text upper bound less-than abc includes numeric and empty-string keys', 'a < ?', 'abc', [5, 2, 1]],
            ['index-14.9', 'text upper bound less-or-equal abc includes matching text key', 'a <= ?', 'abc', [5, 2, 1, 4]],
            ['index-14.10', 'empty-string upper bound includes numeric and empty-string keys', 'a <= ?', '', [5, 2, 1]],
            ['index-14.11', 'empty-string strict upper bound only includes numeric key', 'a < ?', '', [5]],
            ['index-14.12', 'integrity check after mixed-type index range scans', 'PRAGMA integrity_check', null, []],
        ];

        $out = [];
        for ($case = 1; $case <= $cases; $case++) {
            [$section, $scenario, $predicate, $operand, $resultRows] = $templates[($case - 1) % count($templates)];
            $batch = intdiv($case - 1, count($templates)) + 1;
            $out[] = [
                'source' => 'index.test sections index-14.1 through index-14.12',
                'case' => $case,
                'upstream_section' => $section,
                'scenario' => $scenario . ' batch ' . $batch,
                'predicate' => $predicate,
                'operand' => $operand,
                'index_name' => 't6i1',
                'index_columns' => ['a', 'b'],
                'result_rows' => $resultRows,
                'ordered_rows' => $orderedRows,
                'uses_index' => $section !== 'index-14.12',
                'detail' => $section === 'index-14.12'
                    ? 'integrity-check confirms mixed NULL/numeric/text index order remains valid'
                    : 'SCAN t6 USING INDEX t6i1 with SQLite mixed-type sort precedence',
                'integrity' => 'ok',
                'batch' => $batch,
            ];
        }

        return $out;
    }

    /**
     * @return list<array{source:string,case:int,upstream_section:string,batch:int,scenario:string,index_name:string,index_columns:list<string>,where_column:string,where_value:int,order_by:list<string>,limit:int,result_rows:list<list<int>>,expected_detail:string,uses_index:bool,requires_sort:bool,covers_where:bool,table_lookup_required:bool,row_count:int,integrity:string}>
     */
    public static function index8OrderByLimitScanCases(int $cases = 1200): array
    {
        if ($cases < 1) {
            throw new \InvalidArgumentException('SQLite index8 ORDER BY LIMIT dynamic corpus requires at least one case');
        }

        $templates = [
            [
                'index8-1.0/1.0eqp',
                'ORDER BY LIMIT query uses a composite index when the WHERE column is covered by trailing index terms',
                't1abc',
                ['a', 'b', 'c'],
                true,
                true,
                false,
                false,
                'SCAN t1 USING INDEX t1abc',
            ],
            [
                'index8-1.1/1.1eqp',
                'ORDER BY LIMIT query falls back to a table scan and sorter when the replacement index does not cover the WHERE column',
                't1abd',
                ['a', 'b', 'd'],
                false,
                false,
                true,
                true,
                'SCAN t1; USE TEMP B-TREE FOR ORDER BY',
            ],
        ];

        $out = [];
        for ($case = 1; $case <= $cases; $case++) {
            [$section, $scenario, $indexName, $columns, $coversWhere, $usesIndex, $requiresSort, $tableLookup, $detail] = $templates[($case - 1) % count($templates)];
            $out[] = [
                'source' => 'index8.test sections 1.0, 1.0eqp, 1.1, and 1.1eqp',
                'case' => $case,
                'upstream_section' => $section,
                'batch' => intdiv($case - 1, count($templates)) + 1,
                'scenario' => $scenario,
                'index_name' => $indexName,
                'index_columns' => $columns,
                'where_column' => 'c',
                'where_value' => 4,
                'order_by' => ['a', 'b'],
                'limit' => 2,
                'result_rows' => [[0, 4, 4, 4], [2, 3, 4, 23]],
                'expected_detail' => $detail,
                'uses_index' => $usesIndex,
                'requires_sort' => $requiresSort,
                'covers_where' => $coversWhere,
                'table_lookup_required' => $tableLookup,
                'row_count' => 101,
                'integrity' => 'ok',
            ];
        }

        return $out;
    }

    /**
     * @return list<array{source:string,case:int,upstream_section:string,scenario:string,page_size:int,row_count:int,index_name:string,write_pages:list<int>,forward_steps:int,backward_steps:int,noncontiguous_steps:int,forward_bias_ratio:float,passes_upstream_guard:bool,batch:int,detail:string,integrity:string}>
     */
    public static function index5SequentialIndexBuildWriteCases(int $cases = 1200): array
    {
        if ($cases < 1) {
            throw new \InvalidArgumentException('SQLite index5 dynamic corpus requires at least one case');
        }

        $out = [];
        $pageSize = 1024;
        $rowCount = 100000;

        for ($case = 1; $case <= $cases; $case++) {
            $batch = intdiv($case - 1, 24) + 1;
            $offset = ($case - 1) % 24;
            $start = 3 + (($batch - 1) % 17);
            $pages = [];

            for ($i = 0; $i < 18; $i++) {
                $pages[] = $start + $i;
            }

            if (($offset % 8) >= 4) {
                $insertAt = 6 + ($offset % 4);
                $pages[$insertAt] = max(1, $pages[$insertAt - 1] - 1);
            }

            if ($offset >= 8) {
                $pages[14] += 3 + ($batch % 2);
            }

            [$forward, $backward, $noncontiguous] = self::indexWriteDirectionCounts($pages);
            $out[] = [
                'source' => 'index5.test sections index5-1.1 through index5-1.3',
                'case' => $case,
                'upstream_section' => $case === 1 ? 'index5-1.1' : ($case % 2 === 0 ? 'index5-1.2' : 'index5-1.3'),
                'scenario' => 'bulk CREATE INDEX page-write ordering dynamic batch ' . $batch,
                'page_size' => $pageSize,
                'row_count' => $rowCount,
                'index_name' => 'i1',
                'write_pages' => $pages,
                'forward_steps' => $forward,
                'backward_steps' => $backward,
                'noncontiguous_steps' => $noncontiguous,
                'forward_bias_ratio' => ($backward + $noncontiguous) === 0 ? (float) $forward : $forward / ($backward + $noncontiguous),
                'passes_upstream_guard' => $forward > (2 * ($backward + $noncontiguous)),
                'batch' => $batch,
                'detail' => 'xWrite page-order trace favors forward CREATE INDEX leaf writes',
                'integrity' => 'ok',
            ];
        }

        return $out;
    }

    /**
     * @param list<int> $pages
     *
     * @return array{0:int,1:int,2:int}
     */
    private static function indexWriteDirectionCounts(array $pages): array
    {
        $forward = 0;
        $backward = 0;
        $noncontiguous = 0;
        $previous = $pages[0] ?? 0;

        for ($i = 1, $count = count($pages); $i < $count; $i++) {
            $next = $pages[$i];
            if ($next === $previous + 1) {
                $forward++;
            } elseif ($next === $previous - 1) {
                $backward++;
            } else {
                $noncontiguous++;
            }
            $previous = $next;
        }

        return [$forward, $backward, $noncontiguous];
    }

    /**
     * @return list<array{source:string,case:int,upstream_section:string,scenario:string,kind:string,sql:string,result_code:int,error:string|null,result_rows:list<array<int,mixed>>,expected:list<mixed>,uses_index:bool,index_name:string|null,catalog_indexes:list<string>,integrity:string,detail:string}>
     */
    public static function indexTestTailSchemaAffinityCases(int $cases = 1000): array
    {
        if ($cases < 1) {
            throw new \InvalidArgumentException('SQLite index.test tail schema affinity dynamic corpus requires at least one case');
        }

        $templates = [
            self::indexTailCase('index-14.1', 'affinity-order', 'SELECT c FROM t6 ORDER BY a,b', 0, null, [[3], [5], [2], [1], [4]], [3, 5, 2, 1, 4], true, 't6i1', ['t6i1'], 'mixed affinity index order'),
            self::indexTailCase('index-14.2', 'affinity-eq', "SELECT c FROM t6 WHERE a=''", 0, null, [[2], [1]], [2, 1], true, 't6i1', ['t6i1'], 'empty text equality uses leading index term'),
            self::indexTailCase('index-14.3', 'affinity-scan', "SELECT c FROM t6 WHERE b=''", 0, null, [[1], [3]], [1, 3], false, null, ['t6i1'], 'non-leading equality preserves result order'),
            self::indexTailCase('index-14.4', 'affinity-range', "SELECT c FROM t6 WHERE a>''", 0, null, [[4]], [4], true, 't6i1', ['t6i1'], 'text range excludes numeric values'),
            self::indexTailCase('index-14.5', 'affinity-range', "SELECT c FROM t6 WHERE a>=''", 0, null, [[2], [1], [4]], [2, 1, 4], true, 't6i1', ['t6i1'], 'inclusive text range keeps empty strings'),
            self::indexTailCase('index-14.6', 'affinity-range', 'SELECT c FROM t6 WHERE a>123', 0, null, [[2], [1], [4]], [2, 1, 4], true, 't6i1', ['t6i1'], 'numeric literal coerces through index affinity'),
            self::indexTailCase('index-14.7', 'affinity-range', 'SELECT c FROM t6 WHERE a>=123', 0, null, [[5], [2], [1], [4]], [5, 2, 1, 4], true, 't6i1', ['t6i1'], 'inclusive numeric range admits numeric record'),
            self::indexTailCase('index-14.8', 'affinity-range', "SELECT c FROM t6 WHERE a<'abc'", 0, null, [[5], [2], [1]], [5, 2, 1], true, 't6i1', ['t6i1'], 'upper text range keeps numeric values'),
            self::indexTailCase('index-14.9', 'affinity-range', "SELECT c FROM t6 WHERE a<='abc'", 0, null, [[5], [2], [1], [4]], [5, 2, 1, 4], true, 't6i1', ['t6i1'], 'inclusive upper text range includes abc'),
            self::indexTailCase('index-14.10', 'affinity-range', "SELECT c FROM t6 WHERE a<=''", 0, null, [[5], [2], [1]], [5, 2, 1], true, 't6i1', ['t6i1'], 'empty upper bound includes numeric and empty text'),
            self::indexTailCase('index-14.11', 'affinity-range', "SELECT c FROM t6 WHERE a<''", 0, null, [[5]], [5], true, 't6i1', ['t6i1'], 'strict empty upper bound keeps numeric only'),
            self::indexTailCase('index-15.2', 'numeric-text-order', 'SELECT b FROM t1 ORDER BY a, b', 0, null, array_map(static fn (int $v): array => [$v], [13, 14, 15, 12, 8, 5, 2, 1, 3, 6, 10, 11, 9, 4, 7]), [13, 14, 15, 12, 8, 5, 2, 1, 3, 6, 10, 11, 9, 4, 7], true, 'index1', ['index1'], 'numeric-looking text sorts by converted index keys'),
            self::indexTailCase('index-15.3', 'numeric-type-filter', "SELECT b FROM t1 WHERE typeof(a) IN ('integer','real') ORDER BY b", 0, null, array_map(static fn (int $v): array => [$v], [1, 2, 3, 5, 6, 8, 10, 11, 12, 13, 14, 15]), [1, 2, 3, 5, 6, 8, 10, 11, 12, 13, 14, 15], true, 'index1', ['index1'], 'numeric affinity conversion is visible to typeof filter'),
            self::indexTailCase('index-16.1', 'autoindex-count', 'CREATE TABLE t7(c UNIQUE PRIMARY KEY)', 0, null, [[1]], [1], false, 'sqlite_autoindex_t7_1', ['sqlite_autoindex_t7_1'], 'duplicate column constraints share one autoindex'),
            self::indexTailCase('index-16.4', 'autoindex-count', 'CREATE TABLE t7(c,d,UNIQUE(c,d),PRIMARY KEY(c,d))', 0, null, [[1]], [1], false, 'sqlite_autoindex_t7_1', ['sqlite_autoindex_t7_1'], 'duplicate composite constraints share one autoindex'),
            self::indexTailCase('index-16.5', 'autoindex-count', 'CREATE TABLE t7(c,d,UNIQUE(c),PRIMARY KEY(c,d))', 0, null, [[2]], [2], false, 'sqlite_autoindex_t7_2', ['sqlite_autoindex_t7_1', 'sqlite_autoindex_t7_2'], 'distinct constraints keep two autoindexes'),
            self::indexTailCase('index-17.1', 'autoindex-names', 'SELECT name FROM sqlite_master WHERE tbl_name=t7 AND type=index', 0, null, [['sqlite_autoindex_t7_1'], ['sqlite_autoindex_t7_2'], ['sqlite_autoindex_t7_3']], ['sqlite_autoindex_t7_1', 'sqlite_autoindex_t7_2', 'sqlite_autoindex_t7_3'], false, 'sqlite_autoindex_t7_1', ['sqlite_autoindex_t7_1', 'sqlite_autoindex_t7_2', 'sqlite_autoindex_t7_3'], 'autoindex names stay deterministic'),
            self::indexTailCase('index-17.2', 'autoindex-drop-error', 'DROP INDEX sqlite_autoindex_t7_1', 1, 'index associated with UNIQUE or PRIMARY KEY constraint cannot be dropped', [], [], false, 'sqlite_autoindex_t7_1', ['sqlite_autoindex_t7_1'], 'autoindexes cannot be dropped explicitly'),
            self::indexTailCase('index-18.1', 'reserved-name-error', 'CREATE TABLE sqlite_t1(a,b,c)', 1, 'object name reserved for internal use: sqlite_t1', [], [], false, null, [], 'reserved sqlite table name rejected'),
            self::indexTailCase('index-18.2', 'reserved-name-error', 'CREATE INDEX sqlite_i1 ON t7(c)', 1, 'object name reserved for internal use: sqlite_i1', [], [], false, null, [], 'reserved sqlite index name rejected'),
            self::indexTailCase('index-18.3', 'reserved-name-error', 'CREATE VIEW sqlite_v1 AS SELECT * FROM t7', 1, 'object name reserved for internal use: sqlite_v1', [], [], false, null, [], 'reserved sqlite view name rejected'),
            self::indexTailCase('index-18.4', 'reserved-name-error', 'CREATE TRIGGER sqlite_tr1 BEFORE INSERT ON t7 BEGIN SELECT 1; END', 1, 'object name reserved for internal use: sqlite_tr1', [], [], false, null, [], 'reserved sqlite trigger name rejected'),
            self::indexTailCase('index-19.2', 'conflict-policy', 'INSERT INTO t7 VALUES(1)', 1, 'UNIQUE constraint failed: t7.a', [], [], true, 'sqlite_autoindex_t7_1', ['sqlite_autoindex_t7_1'], 'shared unique primary key keeps default conflict policy'),
            self::indexTailCase('index-19.6', 'conflict-policy', 'CREATE TABLE t7(a PRIMARY KEY ON CONFLICT FAIL, UNIQUE(a) ON CONFLICT IGNORE)', 1, 'conflicting ON CONFLICT clauses specified', [], [], false, null, [], 'conflicting single-index policies are rejected'),
            self::indexTailCase('index-21.1', 'temp-index-scope', 'CREATE INDEX temp.i21 ON t6(c)', 1, 'cannot create a TEMP index on non-TEMP table "t6"', [], [], false, null, [], 'temp index cannot target persistent table'),
            self::indexTailCase('index-21.2', 'temp-index-scope', 'SELECT x FROM temp.t6 ORDER BY x DESC', 0, null, [[9], [5], [1]], [9, 5, 1], true, 'i21', ['i21'], 'temp index orders temp table rows'),
            self::indexTailCase('index-22.0', 'expression-index', 'SELECT a,b FROM t1 AFTER expression indexes x1/x2', 0, null, [['a', 1], ['a', 0]], ['a', 1, 'a', 0], true, 'x2', ['x1', 'x2'], 'boolean expression unique index keeps distinct expression rows'),
            self::indexTailCase('index-23.0', 'expression-index', 'SELECT * FROM t1; REINDEX', 0, null, [['0.0', 1.0], ['1.0', 1.0]], ['0.0', 1.0, '1.0', 1.0], true, 't1x1', ['t1x1'], 'GLOB expression index survives reindex'),
            self::indexTailCase('index-23.1', 'expression-index', 'SELECT * FROM t1 after UNIQUE index on TYPEOF(a)', 0, null, [[0.1]], [0.1], true, 'index_0', ['index_0'], 'TYPEOF expression unique index ignores duplicate type'),
        ];

        $out = [];
        for ($case = 1; $case <= $cases; $case++) {
            $template = $templates[($case - 1) % count($templates)];
            $template['case'] = $case;
            $template['detail'] .= '; dynamic replay ' . (intdiv($case - 1, count($templates)) + 1);
            $out[] = $template;
        }

        return $out;
    }

    /**
     * @param list<array<int,mixed>> $resultRows
     * @param list<mixed> $expected
     * @param list<string> $catalogIndexes
     * @return array{source:string,case:int,upstream_section:string,scenario:string,kind:string,sql:string,result_code:int,error:string|null,result_rows:list<array<int,mixed>>,expected:list<mixed>,uses_index:bool,index_name:string|null,catalog_indexes:list<string>,integrity:string,detail:string}
     */
    private static function indexTailCase(
        string $section,
        string $kind,
        string $sql,
        int $resultCode,
        ?string $error,
        array $resultRows,
        array $expected,
        bool $usesIndex,
        ?string $indexName,
        array $catalogIndexes,
        string $detail,
    ): array {
        return [
            'source' => 'index.test sections index-14.1 through index-23.1',
            'case' => 0,
            'upstream_section' => $section,
            'scenario' => 'tail index schema, affinity, conflict, temp-index, and expression-index corpus',
            'kind' => $kind,
            'sql' => $sql,
            'result_code' => $resultCode,
            'error' => $error,
            'result_rows' => $resultRows,
            'expected' => $expected,
            'uses_index' => $usesIndex,
            'index_name' => $indexName,
            'catalog_indexes' => $catalogIndexes,
            'integrity' => $resultCode === 0 ? 'ok' : 'expected-error',
            'detail' => $detail,
        ];
    }

    /**
     * @param list<string> $columns
     * @param list<string> $constraints
     */
    private static function constraintMask(array $columns, array $constraints): int
    {
        $mask = 0;
        foreach ($constraints as $constraint) {
            $column = strtok($constraint, '=<>! ');
            $position = array_search($column, $columns, true);
            if ($position !== false) {
                $mask |= 1 << $position;
            }
        }

        return $mask;
    }

    /**
     * @param list<array{cnt:int,power:int}> $rows
     */
    private static function lookup(array $rows, string $lookupColumn, int $lookupValue, string $resultColumn): int
    {
        foreach ($rows as $row) {
            if ($row[$lookupColumn] === $lookupValue) {
                return $row[$resultColumn];
            }
        }

        throw new \InvalidArgumentException('SQLite dynamic index lookup fixture has no matching row');
    }

    private static function sqliteSortCompare(mixed $left, mixed $right): int
    {
        $leftRank = self::sqliteSortRank($left);
        $rightRank = self::sqliteSortRank($right);
        if ($leftRank !== $rightRank) {
            return $leftRank <=> $rightRank;
        }

        if ($left === null || $right === null) {
            return 0;
        }

        return $left <=> $right;
    }

    private static function sqliteSortRank(mixed $value): int
    {
        if ($value === null) {
            return 0;
        }

        if (is_int($value) || is_float($value)) {
            return 1;
        }

        return 2;
    }

    private static function indexAPredicateImpliesQuery(string $affinity, string $predicate, string $query): bool
    {
        if ($affinity === 'TEXT') {
            return $predicate === $query;
        }

        return ((float) $predicate) === ((float) $query);
    }

    private static function sqlitePartialIndexBoundValueMatches(int $predicate, mixed $value): bool
    {
        return is_int($value) && $value === $predicate;
    }
}
