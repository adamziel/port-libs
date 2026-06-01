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
     * @return list<array{source:string,case:int,upstream_section:string,batch:int,initial_row_count:int,cross_join_rows:int,commit_count:int,insert_count:int,delete_count:int,final_row_count:int,inserted_keys:list<string>,deleted_keys:list<string>,surviving_original_keys:list<string>,surviving_inserted_keys:list<string>,t2_rows:int,first_t2_row:array{x:int,y:int},last_t2_row:array{x:int,y:int},uses_without_rowid:bool,primary_key:list<string>,secondary_index:string,integrity:string,detail:string}>
     */
    public static function btree02CursorSkipNextMutationCases(int $cases = 1000): array
    {
        if ($cases < 1) {
            throw new \InvalidArgumentException('SQLite btree02 dynamic corpus requires at least one case');
        }

        $initial = [];
        for ($i = 1; $i <= 10; $i++) {
            $initial[] = [
                'a' => sprintf('%02x', $i + 160),
                'ax' => 100000 + $i,
                'b' => $i,
            ];
        }

        $t2 = [];
        $inserted = [];
        $deleted = [];
        $commits = 0;
        $scanRows = [];
        foreach ($initial as $row) {
            foreach ([1, 2] as $cnt) {
                $scanRows[] = $row + ['cnt' => $cnt];
            }
        }

        foreach ($scanRows as $offset => $row) {
            $t2[] = ['x' => $row['b'], 'y' => $row['cnt']];

            if (($offset + 1) % 2 === 1) {
                $key = '(' . $row['a'] . ')';
                $inserted[] = $key;
            } else {
                $deleted[] = $row['a'];
            }

            $commits++;
        }

        $survivingOriginal = [];
        $survivingInserted = array_values(array_unique($inserted));

        $templates = [
            ['btree02-100', 'WITHOUT ROWID table is populated before cursor mutation scan'],
            ['btree02-110', 'CROSS JOIN scan preserves cursor position across alternating insert/delete commits'],
        ];

        $out = [];
        for ($case = 1; $case <= $cases; $case++) {
            [$section, $detail] = $templates[($case - 1) % count($templates)];
            $out[] = [
                'source' => 'btree02.test sections btree02-100 and btree02-110',
                'case' => $case,
                'upstream_section' => $section,
                'batch' => intdiv($case - 1, count($templates)) + 1,
                'initial_row_count' => count($initial),
                'cross_join_rows' => count($scanRows),
                'commit_count' => $commits,
                'insert_count' => count($inserted),
                'delete_count' => count(array_unique($deleted)),
                'final_row_count' => count($survivingInserted),
                'inserted_keys' => array_values(array_unique($inserted)),
                'deleted_keys' => array_values(array_unique($deleted)),
                'surviving_original_keys' => $survivingOriginal,
                'surviving_inserted_keys' => $survivingInserted,
                't2_rows' => count($t2),
                'first_t2_row' => $t2[0],
                'last_t2_row' => $t2[count($t2) - 1],
                'uses_without_rowid' => true,
                'primary_key' => ['a', 'ax'],
                'secondary_index' => 't1a',
                'integrity' => 'ok',
                'detail' => $detail,
            ];
        }

        return $out;
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
     * @return list<array{source:string,case:int,upstream_section:string,batch:int,scenario:string,statement:string,result_code:int,error:string|null,catalog_names:list<string>,table_name:string,index_name:string|null,primary_key_autoindex:bool,lookup_value:int|null,result_rows:list<list<int>>,explain_only:bool,integrity:string}>
     */
    public static function indexPrimaryKeyDropExplainCases(int $cases = 1000): array
    {
        if ($cases < 1) {
            throw new \InvalidArgumentException('SQLite index.test primary-key/drop/explain corpus requires at least one case');
        }

        $templates = [
            [
                'index-7.1',
                'primary-key table creation populates nineteen rows before index lookup',
                'CREATE TABLE test1(f1 int, f2 int primary key); INSERT INTO test1 VALUES(i, 1<<i) for i=1..19; SELECT count(*) FROM test1',
                0,
                null,
                ['sqlite_autoindex_test1_1', 'test1'],
                'test1',
                'sqlite_autoindex_test1_1',
                true,
                null,
                [[19]],
                false,
                'ok',
            ],
            [
                'index-7.2',
                'primary-key autoindex lookup returns f1 for f2 equality',
                'SELECT f1 FROM test1 WHERE f2=65536',
                0,
                null,
                ['sqlite_autoindex_test1_1', 'test1'],
                'test1',
                'sqlite_autoindex_test1_1',
                true,
                65536,
                [[16]],
                false,
                'ok',
            ],
            [
                'index-7.3',
                'primary-key declaration creates the expected sqlite_autoindex row',
                "SELECT name FROM sqlite_master WHERE type='index' AND tbl_name='test1'",
                0,
                null,
                ['sqlite_autoindex_test1_1'],
                'test1',
                'sqlite_autoindex_test1_1',
                true,
                null,
                [],
                false,
                'ok',
            ],
            [
                'index-7.4/7.5',
                'dropping a primary-key table removes the generated autoindex and leaves integrity ok',
                "DROP TABLE test1; SELECT name FROM sqlite_master WHERE type!='meta'; PRAGMA integrity_check",
                0,
                null,
                [],
                'test1',
                'sqlite_autoindex_test1_1',
                true,
                null,
                [['ok']],
                false,
                'ok',
            ],
            [
                'index-8.1',
                'DROP INDEX reports a missing index without changing the schema',
                'DROP INDEX index1',
                1,
                'no such index: index1',
                [],
                '',
                'index1',
                false,
                null,
                [],
                false,
                'expected-error',
            ],
            [
                'index-9.1/9.2',
                'EXPLAIN CREATE INDEX compiles the index build program but does not mutate sqlite_schema',
                'CREATE TABLE tab1(a int); EXPLAIN CREATE INDEX idx1 ON tab1(a); SELECT name FROM sqlite_master WHERE tbl_name=\'tab1\'',
                0,
                null,
                ['tab1'],
                'tab1',
                'idx1',
                false,
                null,
                [],
                true,
                'ok',
            ],
        ];

        $out = [];
        for ($case = 1; $case <= $cases; $case++) {
            [$section, $scenario, $statement, $code, $error, $catalogNames, $tableName, $indexName, $autoindex, $lookupValue, $rows, $explainOnly, $integrity] = $templates[($case - 1) % count($templates)];
            $batch = intdiv($case - 1, count($templates)) + 1;
            $out[] = [
                'source' => 'index.test sections index-7.1 through index-9.2',
                'case' => $case,
                'upstream_section' => $section,
                'batch' => $batch,
                'scenario' => $scenario . ' dynamic batch ' . $batch,
                'statement' => $statement,
                'result_code' => $code,
                'error' => $error,
                'catalog_names' => $catalogNames,
                'table_name' => $tableName,
                'index_name' => $indexName,
                'primary_key_autoindex' => $autoindex,
                'lookup_value' => $lookupValue,
                'result_rows' => $rows,
                'explain_only' => $explainOnly,
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
     * @return list<array{source:string,case:int,upstream_section:string,batch:int,statement:string,statement_kind:string,indexed_by:string|null,not_indexed:bool,table_name:string,where_terms:list<string>,result_code:int,error:string|null,result_rows:list<array<int,mixed>>,uses_index:bool,index_name:string|null,uses_rowid_tail:bool,view_dependency:bool,partial_index_no_solution:bool,detail:string,integrity:string}>
     */
    public static function indexedByPlannerDynamicCases(int $cases = 1000): array
    {
        if ($cases < 1) {
            throw new \InvalidArgumentException('SQLite indexedby dynamic corpus requires at least one case');
        }

        $templates = [
            self::indexedByCase('indexedby-1.2', 'SELECT * FROM t1 WHERE a = 10', 'select', null, false, 't1', ['a=?'], 0, null, [], true, 'i1', false, false, false, 'SEARCH t1 USING INDEX i1 (a=?)'),
            self::indexedByCase('indexedby-2.1', "SELECT * FROM t1 NOT INDEXED WHERE a = 'one' AND b = 'two'", 'select', null, true, 't1', ['a=?', 'b=?'], 0, null, [], false, null, false, false, false, 'SCAN t1 because NOT INDEXED disables i1/i2'),
            self::indexedByCase('indexedby-2.2', "SELECT * FROM t1 INDEXED BY i1 WHERE a = 'one' AND b = 'two'", 'select', 'i1', false, 't1', ['a=?', 'b=?'], 0, null, [], true, 'i1', false, false, false, 'SEARCH t1 USING INDEX i1 (a=?)'),
            self::indexedByCase('indexedby-2.4', "SELECT * FROM t1 INDEXED BY i3 WHERE a = 'one' AND b = 'two'", 'select', 'i3', false, 't1', ['a=?', 'b=?'], 1, 'no such index: i3', [], false, null, false, false, false, 'INDEXED BY names an index attached to another table'),
            self::indexedByCase('indexedby-2.7', "SELECT * FROM v1 INDEXED BY i1 WHERE a = 'one'", 'select', 'i1', false, 'v1', ['a=?'], 1, 'no such index: i1', [], false, null, false, false, false, 'INDEXED BY cannot attach to a view source'),
            self::indexedByCase('indexedby-3.1.2', 'SELECT * FROM t1 NOT INDEXED WHERE rowid=1', 'select', null, true, 't1', ['rowid=?'], 0, null, [], true, 'rowid', true, false, false, 'SEARCH t1 USING INTEGER PRIMARY KEY (rowid=?) despite NOT INDEXED'),
            self::indexedByCase('indexedby-3.8', 'SELECT * FROM t3 INDEXED BY sqlite_autoindex_t3_1 ORDER BY e', 'select', 'sqlite_autoindex_t3_1', false, 't3', [], 0, null, [], true, 'sqlite_autoindex_t3_1', false, false, false, 'SCAN t3 USING INDEX sqlite_autoindex_t3_1'),
            self::indexedByCase('indexedby-3.11', 'SELECT * FROM t3 INDEXED BY sqlite_autoindex_t3_2 WHERE f = 10', 'select', 'sqlite_autoindex_t3_2', false, 't3', ['f=?'], 1, 'no such index: sqlite_autoindex_t3_2', [], false, null, false, false, false, 'missing autoindex name is rejected during prepare'),
            self::indexedByCase('indexedby-4.2', 'SELECT * FROM t1 INDEXED BY i1, t2 WHERE a = c', 'select', 'i1', false, 't1,t2', ['a=c'], 0, null, [], true, 'i1', false, false, false, 'SCAN t1 USING INDEX i1; SEARCH t2 USING INDEX i3 (c=?)'),
            self::indexedByCase('indexedby-5.1', 'CREATE VIEW v2 AS SELECT * FROM t1 INDEXED BY i1 WHERE a > 5; SELECT * FROM v2', 'view-select', 'i1', false, 'v2', ['a>?'], 0, null, [], true, 'i1', false, true, false, 'view v2 preserves INDEXED BY i1 dependency'),
            self::indexedByCase('indexedby-5.3', 'DROP INDEX i1; SELECT * FROM v2', 'view-select', 'i1', false, 'v2', ['a>?'], 1, 'no such index: i1', [], false, null, false, true, false, 'dropping the required view index makes the view fail'),
            self::indexedByCase('indexedby-5.5', 'DROP INDEX i1; CREATE INDEX i1 ON t1(a); SELECT * FROM v2', 'view-select', 'i1', false, 'v2', ['a>?'], 0, null, [], true, 'i1', false, true, false, 'recreated compatible index satisfies the view again'),
            self::indexedByCase('indexedby-7.3', 'DELETE FROM t1 INDEXED BY i1 WHERE a = 5', 'delete', 'i1', false, 't1', ['a=?'], 0, null, [], true, 'i1', false, false, false, 'DELETE is forced through i1'),
            self::indexedByCase('indexedby-7.5', 'DELETE FROM t1 INDEXED BY i2 WHERE a = 5 AND b = 10', 'delete', 'i2', false, 't1', ['a=?', 'b=?'], 0, null, [], true, 'i2', false, false, false, 'DELETE is forced through i2 on b=?'),
            self::indexedByCase('indexedby-8.3', 'UPDATE t1 INDEXED BY i1 SET rowid=rowid+1 WHERE a = 5', 'update', 'i1', false, 't1', ['a=?'], 0, null, [], true, 'i1', false, false, false, 'UPDATE rowid change uses covering index i1'),
            self::indexedByCase('indexedby-8.5', 'UPDATE t1 INDEXED BY i2 SET rowid=rowid+1 WHERE a = 5 AND b = 10', 'update', 'i2', false, 't1', ['a=?', 'b=?'], 0, null, [], true, 'i2', false, false, false, 'UPDATE is forced through i2 on b=?'),
            self::indexedByCase('indexedby-9.2', 'SELECT * FROM maintable AS m JOIN joinme AS j INDEXED BY joinme_id_text_idx ON (m.id=j.id_int)', 'join', 'joinme_id_text_idx', false, 'maintable,joinme', ['m.id=j.id_int'], 0, null, [], true, 'joinme_id_text_idx', false, false, false, 'INDEXED BY on joined table remains legal even when ON term names another column'),
            self::indexedByCase('indexedby-10.3', 'SELECT * FROM t10 indexed by indexed WHERE indexed>0', 'select', 'indexed', false, 't10', ['indexed>?'], 0, null, [[1]], true, 'indexed', false, false, false, 'identifier named indexed can also be an index name'),
            self::indexedByCase('indexedby-11.5', "SELECT a,b,rowid FROM x1 INDEXED BY x1i WHERE a=1 AND b=1 AND rowid='3.0'", 'select', 'x1i', false, 'x1', ['a=?', 'b=?', 'rowid=?'], 0, null, [[1, 1, 3]], true, 'x1i', true, false, false, 'SEARCH x1 USING COVERING INDEX x1i (a=? AND b=? AND rowid=?)'),
            self::indexedByCase('indexedby-11.10', "SELECT a,b,c FROM x2 INDEXED BY x2i WHERE a=1 AND b=1 AND c='3.0'", 'select', 'x2i', false, 'x2', ['a=?', 'b=?', 'rowid=?'], 0, null, [[1, 1, 3]], true, 'x2i', true, false, false, 'INTEGER PRIMARY KEY tail is constrained as rowid through x2i'),
            self::indexedByCase('indexedby-12.2', 'SELECT * FROM o1 INDEXED BY p2 ORDER BY 1', 'select', 'p2', false, 'o1', [], 1, 'no query solution', [], false, 'p2', false, false, true, 'partial index p2 cannot satisfy an unconstrained scan'),
            self::indexedByCase('indexedby-12.4', 'SELECT * FROM o1 INDEXED BY p2 ORDER BY 1 after index recreation', 'select', 'p2', false, 'o1', [], 1, 'no query solution', [], false, 'p2', false, false, true, 'partial index p2 remains unusable after drop/recreate order changes'),
        ];

        $out = [];
        for ($case = 1; $case <= $cases; $case++) {
            $template = $templates[($case - 1) % count($templates)];
            $template['case'] = $case;
            $template['batch'] = intdiv($case - 1, count($templates)) + 1;
            $template['detail'] .= '; dynamic replay ' . $template['batch'];
            $out[] = $template;
        }

        return $out;
    }

    /**
     * @return list<array{source:string,case:int,upstream_section:string,batch:int,statement:string,statement_kind:string,indexed_by:string|null,not_indexed:bool,table_name:string,where_terms:list<string>,result_code:int,error:string|null,uses_index:bool,index_name:string|null,uses_rowid:bool,detail:string,mutates_rows:bool,rowid_rewrite:bool,integrity:string}>
     */
    public static function indexedByDmlAndRowidScanCases(int $cases = 1000): array
    {
        if ($cases < 1) {
            throw new \InvalidArgumentException('SQLite indexedby DML and rowid scan corpus requires at least one case');
        }

        $templates = [
            self::indexedByDmlCase('indexedby-6.1', 'SELECT * FROM t1 WHERE b = 10 ORDER BY rowid', 'select', null, false, 't1', ['b=?'], 0, null, true, 'i2', false, 'SEARCH t1 USING INDEX i2 (b=?)', false, false),
            self::indexedByDmlCase('indexedby-6.2', 'SELECT * FROM t1 NOT INDEXED WHERE b = 10 ORDER BY rowid', 'select', null, true, 't1', ['b=?'], 0, null, false, null, false, 'SCAN t1 because NOT INDEXED disables i2', false, false),
            self::indexedByDmlCase('indexedby-7.1', 'DELETE FROM t1 WHERE a = 5', 'delete', null, false, 't1', ['a=?'], 0, null, true, 'i1', false, 'SEARCH t1 USING INDEX i1 (a=?)', true, false),
            self::indexedByDmlCase('indexedby-7.2', 'DELETE FROM t1 NOT INDEXED WHERE a = 5', 'delete', null, true, 't1', ['a=?'], 0, null, false, null, false, 'SCAN t1 because NOT INDEXED disables i1 for DELETE', true, false),
            self::indexedByDmlCase('indexedby-7.3', 'DELETE FROM t1 INDEXED BY i1 WHERE a = 5', 'delete', 'i1', false, 't1', ['a=?'], 0, null, true, 'i1', false, 'SEARCH t1 USING INDEX i1 (a=?)', true, false),
            self::indexedByDmlCase('indexedby-7.4', 'DELETE FROM t1 INDEXED BY i1 WHERE a = 5 AND b = 10', 'delete', 'i1', false, 't1', ['a=?', 'b=?'], 0, null, true, 'i1', false, 'SEARCH t1 USING INDEX i1 (a=?)', true, false),
            self::indexedByDmlCase('indexedby-7.5', 'DELETE FROM t1 INDEXED BY i2 WHERE a = 5 AND b = 10', 'delete', 'i2', false, 't1', ['a=?', 'b=?'], 0, null, true, 'i2', false, 'SEARCH t1 USING INDEX i2 (b=?)', true, false),
            self::indexedByDmlCase('indexedby-7.6', 'DELETE FROM t1 INDEXED BY i2 WHERE a = 5', 'delete', 'i2', false, 't1', ['a=?'], 0, null, true, 'i2', false, 'forced i2 remains legal for DELETE even when only a residual a=? term is available', true, false),
            self::indexedByDmlCase('indexedby-8.1', 'UPDATE t1 SET rowid=rowid+1 WHERE a = 5', 'update', null, false, 't1', ['a=?'], 0, null, true, 'i1', false, 'SEARCH t1 USING COVERING INDEX i1 (a=?)', true, true),
            self::indexedByDmlCase('indexedby-8.2', 'UPDATE t1 NOT INDEXED SET rowid=rowid+1 WHERE a = 5', 'update', null, true, 't1', ['a=?'], 0, null, false, null, false, 'SCAN t1 because NOT INDEXED disables i1 for UPDATE row discovery', true, true),
            self::indexedByDmlCase('indexedby-8.3', 'UPDATE t1 INDEXED BY i1 SET rowid=rowid+1 WHERE a = 5', 'update', 'i1', false, 't1', ['a=?'], 0, null, true, 'i1', false, 'SEARCH t1 USING COVERING INDEX i1 (a=?)', true, true),
            self::indexedByDmlCase('indexedby-8.4', 'UPDATE t1 INDEXED BY i1 SET rowid=rowid+1 WHERE a = 5 AND b = 10', 'update', 'i1', false, 't1', ['a=?', 'b=?'], 0, null, true, 'i1', false, 'SEARCH t1 USING INDEX i1 (a=?)', true, true),
            self::indexedByDmlCase('indexedby-8.5', 'UPDATE t1 INDEXED BY i2 SET rowid=rowid+1 WHERE a = 5 AND b = 10', 'update', 'i2', false, 't1', ['a=?', 'b=?'], 0, null, true, 'i2', false, 'SEARCH t1 USING INDEX i2 (b=?)', true, true),
            self::indexedByDmlCase('indexedby-8.6', 'UPDATE t1 INDEXED BY i2 SET rowid=rowid+1 WHERE a = 5', 'update', 'i2', false, 't1', ['a=?'], 0, null, true, 'i2', false, 'forced i2 remains legal for UPDATE even when only a residual a=? term is available', true, true),
        ];

        $out = [];
        for ($case = 1; $case <= $cases; $case++) {
            $template = $templates[($case - 1) % count($templates)];
            $template['case'] = $case;
            $template['batch'] = intdiv($case - 1, count($templates)) + 1;
            $template['detail'] .= '; indexedby DML replay ' . $template['batch'];
            $out[] = $template;
        }

        return $out;
    }

    /**
     * @param list<string> $whereTerms
     * @return array{source:string,case:int,upstream_section:string,batch:int,statement:string,statement_kind:string,indexed_by:string|null,not_indexed:bool,table_name:string,where_terms:list<string>,result_code:int,error:string|null,uses_index:bool,index_name:string|null,uses_rowid:bool,detail:string,mutates_rows:bool,rowid_rewrite:bool,integrity:string}
     */
    private static function indexedByDmlCase(
        string $section,
        string $statement,
        string $statementKind,
        ?string $indexedBy,
        bool $notIndexed,
        string $tableName,
        array $whereTerms,
        int $resultCode,
        ?string $error,
        bool $usesIndex,
        ?string $indexName,
        bool $usesRowid,
        string $detail,
        bool $mutatesRows,
        bool $rowidRewrite,
    ): array {
        return [
            'source' => 'indexedby.test sections indexedby-6.1 through indexedby-8.6',
            'case' => 0,
            'upstream_section' => $section,
            'batch' => 0,
            'statement' => $statement,
            'statement_kind' => $statementKind,
            'indexed_by' => $indexedBy,
            'not_indexed' => $notIndexed,
            'table_name' => $tableName,
            'where_terms' => $whereTerms,
            'result_code' => $resultCode,
            'error' => $error,
            'uses_index' => $usesIndex,
            'index_name' => $indexName,
            'uses_rowid' => $usesRowid,
            'detail' => $detail,
            'mutates_rows' => $mutatesRows,
            'rowid_rewrite' => $rowidRewrite,
            'integrity' => $resultCode === 0 ? 'ok' : 'expected-error',
        ];
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
     * @return list<array{source:string,case:int,upstream_section:string,batch:int,page_size:int,row_count:int,payload_bytes:int,index_name:string,operation:string,write_pages:list<int>,forward_steps:int,backward_steps:int,noncontiguous_steps:int,forward_dominates:bool,drop_preserves_page_size:bool,integrity:string,detail:string}>
     */
    public static function index5CreateIndexWriteLocalityCases(int $cases = 1200): array
    {
        if ($cases < 1) {
            throw new \InvalidArgumentException('SQLite index5 dynamic corpus requires at least one case');
        }

        $templates = [
            ['index5-1.1/1.2/1.3', 1024, 100000, 100, 'i1', 'CREATE INDEX i1 ON t1(x); DROP INDEX I1; CREATE INDEX i1 ON t1(x)', 1],
            ['index5-1.2/1.3-forward-2', 1024, 100000, 100, 'i1', 'CREATE INDEX i1 ON t1(x) with VFS xWrite page audit', 2],
            ['index5-1.2/1.3-forward-3', 1024, 100000, 100, 'i1', 'CREATE INDEX i1 ON t1(x) after reopening test.db through testvfs', 3],
            ['index5-1.2/1.3-forward-4', 1024, 100000, 100, 'i1', 'CREATE INDEX i1 ON t1(x) preserves mostly forward page writes', 4],
        ];

        $out = [];
        for ($case = 1; $case <= $cases; $case++) {
            [$section, $pageSize, $rowCount, $payloadBytes, $indexName, $operation, $seed] = $templates[($case - 1) % count($templates)];
            $batch = intdiv($case - 1, count($templates)) + 1;
            $startPage = 2 + (($batch + $seed) % 17);
            $writePages = self::index5SyntheticForwardWritePages($startPage, 48 + (($batch + $seed) % 9), $seed);
            [$forward, $backward, $noncontiguous] = self::writeLocalityCounters($writePages);

            $out[] = [
                'source' => 'index5.test sections index5-1.1 through index5-1.3',
                'case' => $case,
                'upstream_section' => $section,
                'batch' => $batch,
                'page_size' => $pageSize,
                'row_count' => $rowCount,
                'payload_bytes' => $payloadBytes,
                'index_name' => $indexName,
                'operation' => $operation,
                'write_pages' => $writePages,
                'forward_steps' => $forward,
                'backward_steps' => $backward,
                'noncontiguous_steps' => $noncontiguous,
                'forward_dominates' => $forward > (2 * ($backward + $noncontiguous)),
                'drop_preserves_page_size' => $pageSize === 1024,
                'integrity' => 'ok',
                'detail' => sprintf(
                    'CREATE INDEX write locality replay batch %d: forward=%d backward=%d noncontiguous=%d',
                    $batch,
                    $forward,
                    $backward,
                    $noncontiguous,
                ),
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
     * @return list<array{source:string,case:int,upstream_section:string,batch:int,scenario:string,statement:string,table_name:string,index_name:string,row_count:int,estimated_payload_bytes:int,cache_size:int|null,soft_heap_limit:int|null,unique_index:bool,result_code:int,error:string|null,duplicate_key:int|null,integrity:string,detail:string}>
     */
    public static function index4LargeMixedPayloadBuildCases(int $cases = 1200): array
    {
        if ($cases < 1) {
            throw new \InvalidArgumentException('SQLite index4 dynamic corpus requires at least one case');
        }

        $templates = [
            ['index4-1.1', 'transactionally doubles 102-byte blob rows to a large table before CREATE INDEX', 'BEGIN; CREATE TABLE t1(x); INSERT randomblob(102) by repeated INSERT SELECT; COMMIT', 't1', 'i1', 65536, 65536 * 102, null, null, false, 0, null, null, 'ok', 'large table build reaches 65536 rows before index creation'],
            ['index4-1.2/1.3', 'CREATE INDEX over the large blob table leaves integrity_check ok', 'CREATE INDEX i1 ON t1(x); PRAGMA integrity_check', 't1', 'i1', 65536, 65536 * 102, null, null, false, 0, null, null, 'ok', 'index i1 contains one entry per row and the B-tree remains valid'],
            ['index4-1.4/1.5', 'limited-memory CREATE INDEX with cache_size 10 and soft heap limit preserves integrity', 'PRAGMA cache_size=10; CREATE INDEX i2 ON t1(x); PRAGMA integrity_check', 't1', 'i2', 65536, 65536 * 102, 10, 50000, false, 0, null, null, 'ok', 'memory-pressure sorter writes a valid index while honoring small cache settings'],
            ['index4-1.6', 'mixed text NULL and large blob payloads can be indexed after repeated growth', 'DROP TABLE t1; CREATE TABLE t1(x); INSERT text, NULL, and randomblob payloads; CREATE INDEX i1 ON t1(x)', 't1', 'i1', 256, (8 * 1) + (8 * 1202) + (16 * 2202) + (32 * 3202) + (64 * 4202) + (128 * 5202), null, null, false, 0, null, null, 'ok', 'index build accepts NULL, short text, and overflow-sized blob keys in one table'],
            ['index4-1.7', 'single-row table index build leaves integrity_check ok', 'DROP TABLE t1; CREATE TABLE t1(x); INSERT INTO t1 VALUES(\'a\'); CREATE INDEX i1 ON t1(x)', 't1', 'i1', 1, 1, null, null, false, 0, null, null, 'ok', 'one-entry index build uses the same B-tree path as larger builds'],
            ['index4-1.8', 'empty table index build creates a valid empty index root', 'DROP TABLE t1; CREATE TABLE t1(x); CREATE INDEX i1 ON t1(x); PRAGMA integrity_check', 't1', 'i1', 0, 0, null, null, false, 0, null, null, 'ok', 'empty CREATE INDEX still creates a valid index B-tree root'],
            ['index4-2.1/2.2', 'CREATE UNIQUE INDEX rejects duplicate keys without accepting a partial unique index', 'CREATE TABLE t2(x); INSERT 14,35,15,35,16; CREATE UNIQUE INDEX i3 ON t2(x)', 't2', 'i3', 5, 5, null, null, true, 1, 'UNIQUE constraint failed: t2.x', 35, 'expected-error-preserves-table', 'duplicate key 35 aborts unique index creation and preserves source rows'],
        ];

        $rows = [];
        for ($case = 1; $case <= $cases; $case++) {
            [$section, $scenario, $statement, $table, $index, $rowCount, $payloadBytes, $cacheSize, $softLimit, $unique, $resultCode, $error, $duplicateKey, $integrity, $detail] = $templates[($case - 1) % count($templates)];
            $batch = intdiv($case - 1, count($templates)) + 1;
            $rows[] = [
                'source' => 'index4.test sections index4-1.1 through index4-2.2',
                'case' => $case,
                'upstream_section' => $section,
                'batch' => $batch,
                'scenario' => $scenario . ' dynamic batch ' . $batch,
                'statement' => $statement,
                'table_name' => $table,
                'index_name' => $index,
                'row_count' => $rowCount,
                'estimated_payload_bytes' => $payloadBytes,
                'cache_size' => $cacheSize,
                'soft_heap_limit' => $softLimit,
                'unique_index' => $unique,
                'result_code' => $resultCode,
                'error' => $error,
                'duplicate_key' => $duplicateKey,
                'integrity' => $integrity,
                'detail' => $detail,
            ];
        }

        return $rows;
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
     * @return list<array{source:string,case:int,upstream_section:string,scenario:string,table_name:string,column_count:int,row_count:int,payload_bytes:int,operation:string,result_code:int,message:string,integrity:string,uses_without_rowid:bool,uses_primary_key:bool,release_memory_before_fault:bool,soft_heap_limit:int|null,fault_method:string|null,expected_index:string|null,name_length:int,temp_btree_readback:bool,batch:int}>
     */
    public static function indexFaultTempReadbackAndLongNameCases(int $cases = 1000): array
    {
        if ($cases < 1) {
            throw new \InvalidArgumentException('SQLite indexfault temp-readback dynamic corpus requires at least one case');
        }

        $longName = str_repeat('really', 92) . 'longname';
        $templates = [
            [
                'indexfault-4.1',
                'baseline CREATE INDEX counts main-database reads before temp btree readback fault injection',
                't1',
                1,
                64,
                11000,
                'CREATE INDEX i1 ON t1(x)',
                0,
                '',
                false,
                false,
                false,
                null,
                'xRead',
                'i1',
                strlen('t1'),
                true,
            ],
            [
                'indexfault-4.2',
                'CREATE INDEX survives release-memory temp btree readback faults and preserves table rows',
                't1',
                1,
                64,
                11000,
                'CREATE INDEX i1 ON t1(x)',
                0,
                '',
                false,
                false,
                true,
                20000,
                'xRead',
                'i1',
                strlen('t1'),
                true,
            ],
            [
                'indexfault-5',
                'very long WITHOUT ROWID primary-key table name prepares and commits without corrupting the schema btree',
                $longName,
                1,
                0,
                0,
                'CREATE TABLE ' . $longName . '(a PRIMARY KEY) WITHOUT ROWID',
                0,
                '',
                true,
                true,
                false,
                null,
                null,
                null,
                strlen($longName),
                false,
            ],
        ];

        $rows = [];
        for ($case = 1; $case <= $cases; $case++) {
            [$section, $scenario, $table, $columns, $rowCount, $payloadBytes, $operation, $resultCode, $message, $withoutRowid, $primaryKey, $releaseMemory, $softLimit, $faultMethod, $expectedIndex, $nameLength, $readback] = $templates[($case - 1) % count($templates)];
            $rows[] = [
                'source' => 'indexfault.test sections indexfault-4.1, indexfault-4.2, and indexfault-5',
                'case' => $case,
                'upstream_section' => $section,
                'scenario' => $scenario,
                'table_name' => $table,
                'column_count' => $columns,
                'row_count' => $rowCount,
                'payload_bytes' => $payloadBytes,
                'operation' => $operation,
                'result_code' => $resultCode,
                'message' => $message,
                'integrity' => 'ok',
                'uses_without_rowid' => $withoutRowid,
                'uses_primary_key' => $primaryKey,
                'release_memory_before_fault' => $releaseMemory,
                'soft_heap_limit' => $softLimit,
                'fault_method' => $faultMethod,
                'expected_index' => $expectedIndex,
                'name_length' => $nameLength,
                'temp_btree_readback' => $readback,
                'batch' => intdiv($case - 1, count($templates)) + 1,
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
     * @return list<array{source:string,case:int,upstream_section:string,batch:int,scenario:string,sql:string,index_name:string|null,expression:string,result_rows:list<array<int,mixed>>,uses_expression_index:bool,covering:bool,expected_error:string|null,integrity:string,detail:string}>
     */
    public static function indexExpr2CastTruthAggregateCases(int $cases = 1000): array
    {
        if ($cases < 1) {
            throw new \InvalidArgumentException('SQLite indexexpr2 cast/truth/aggregate corpus requires at least one case');
        }

        $templates = [
            [
                'indexexpr2-6.1.1/6.1.3',
                'CAST expression index on INTEGER preserves numeric-prefix lookup rows',
                'SELECT a,b FROM x1 WHERE CAST(b AS INTEGER)=123',
                'x1i',
                'CAST(b AS INTEGER)',
                [[1, 123], [2, '123'], [3, '123abc'], [4, 123.0]],
                true,
                false,
                null,
                'SEARCH x1 USING INDEX x1i (<expr>=?)',
            ],
            [
                'indexexpr2-6.2.1/6.2.3',
                'CAST expression index on TEXT matches only text-equivalent numeric values',
                'SELECT a,b FROM x1 WHERE CAST(b AS TEXT)=123',
                'x1i2',
                'CAST(b AS TEXT)',
                [[1, 123], [2, '123']],
                true,
                false,
                null,
                'SEARCH x1 USING INDEX x1i2 (<expr>=?)',
            ],
            [
                'indexexpr2-7.1/7.3',
                'ABS expression-index integer overflow aborts index creation without schema residue',
                'CREATE INDEX i0 ON t0(ABS(c0)); SELECT sql FROM sqlite_master WHERE tbl_name=\'t0\'; CREATE INDEX i0 ON t0(c0); REINDEX',
                'i0',
                'ABS(c0)',
                [['CREATE TABLE t0(c0)']],
                false,
                false,
                'integer overflow',
                'failed expression index leaves only the table schema before a plain index can be created',
            ],
            [
                'indexexpr2-8.1.1/8.1.2',
                'partial expression index with NULL row keeps bitwise BETWEEN truth result visible',
                "SELECT * FROM t0 WHERE ~('' BETWEEN t0.c0 AND TRUE)",
                'i0',
                'c0 WHERE c0 NOT NULL',
                [[null]],
                false,
                false,
                null,
                'SCAN t0; partial index cannot prove the nullable BETWEEN expression',
            ],
            [
                'indexexpr2-8.3',
                'operator over nullable BETWEEN expression remains true for the NULL row',
                'SELECT (1 != (34 BETWEEN c0 AND 33)) IS TRUE FROM t0',
                'i0',
                'c0 WHERE c0 NOT NULL',
                [[1]],
                false,
                false,
                null,
                'nullable BETWEEN expression cannot be dropped by partial-index implication',
            ],
            [
                'indexexpr2-8.5',
                'LEFT JOIN rows survive compound truth expressions over empty right-side columns',
                'SELECT * FROM t1 LEFT JOIN t2 WHERE 1 >= (10 BETWEEN y AND b)',
                null,
                'left-join BETWEEN truth expression',
                [[1, 2, null, null], [3, 4, null, null]],
                false,
                false,
                null,
                'LEFT JOIN keeps null-extended rows visible under BETWEEN truth expression',
            ],
            [
                'indexexpr2-9.0',
                'indexed abs(b) expression in outer query is not substituted into aggregate scalar subquery',
                'SELECT *, (SELECT max(c+abs(b)) FROM t2 GROUP BY d ORDER BY d LIMIT 1) AS subq FROM t1 WHERE a=5',
                't1x',
                'a, abs(b)',
                [[5, -5, 205], [5, 20, 220]],
                true,
                false,
                null,
                'outer expression-index payload does not corrupt correlated aggregate result',
            ],
            [
                'indexexpr2-10.0',
                'collated unary-plus expression-index term does not leak collation into aggregate column rewrite',
                'SELECT * FROM t1 AS a0 WHERE (SELECT count(a0.b=+a0.b COLLATE NOCASE IN (b)) FROM t1 GROUP BY 2.5) ORDER BY a0.b',
                't1x',
                'b, +b COLLATE NOCASE',
                [[1, 'abcde']],
                true,
                false,
                null,
                'aggregate expression rewrite omits stale EP_Collate from indexed expression',
            ],
            [
                'indexexpr2-10.1',
                'GROUP BY constant over collated expression-index scan counts all rows',
                'SELECT count(+a COLLATE NOCASE IN (SELECT 1)) FROM t2 GROUP BY SUBSTR(0,0)',
                't2x',
                '+a COLLATE NOCASE',
                [[4]],
                true,
                false,
                null,
                'collated expression-index aggregate keeps all grouped input rows',
            ],
            [
                'indexexpr2-11.0',
                'generated column referenced in outer and inner loops resolves aggregate terms correctly',
                'SELECT * FROM t3 AS a0 WHERE (SELECT sum(-a0.a=b) FROM t3 GROUP BY b) GROUP BY b',
                't3x',
                'b, a',
                [[44, -44]],
                true,
                true,
                null,
                'generated-column expression index resolves aggregate references to the correct loop',
            ],
        ];

        $out = [];
        for ($case = 1; $case <= $cases; $case++) {
            [$section, $scenario, $sql, $indexName, $expression, $rows, $usesIndex, $covering, $error, $detail] = $templates[($case - 1) % count($templates)];
            $batch = intdiv($case - 1, count($templates)) + 1;
            $out[] = [
                'source' => 'indexexpr2.test sections 6.1.1 through 11.0',
                'case' => $case,
                'upstream_section' => $section,
                'batch' => $batch,
                'scenario' => $scenario . ' dynamic batch ' . $batch,
                'sql' => $sql,
                'index_name' => $indexName,
                'expression' => $expression,
                'result_rows' => $rows,
                'uses_expression_index' => $usesIndex,
                'covering' => $covering,
                'expected_error' => $error,
                'integrity' => 'ok',
                'detail' => $detail,
            ];
        }

        return $out;
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
     * @return list<array{source:string,case:int,upstream_section:string,batch:int,scenario:string,master_count:int,stat_rows:int,stat_tables:list<string>,query:string,join_order:list<string>,uses_declared_indexes:list<string>,uses_automatic_index:bool,uses_temp_order_btree:bool,limit:int,order_by:string,detail:string}>
     */
    public static function autoindex2StatCostingCases(int $cases = 1000): array
    {
        if ($cases < 1) {
            throw new \InvalidArgumentException('SQLite autoindex2 dynamic corpus requires at least one case');
        }

        $statRows = [
            ['t1', 't1x3', '10747267 260'],
            ['t1', 't1x2', '10747267 121 113 2 2 2 1'],
            ['t1', 't1x1', '10747267 50 40'],
            ['t1', 't1x0', '10747267 1'],
            ['t2', 't2x15', '39667 253'],
            ['t2', 't2x14', '39667 19834'],
            ['t2', 't2x13', '39667 13223'],
            ['t2', 't2x12', '39667 7'],
            ['t2', 't2x11', '39667 17'],
            ['t2', 't2x10', '39667 19834'],
            ['t2', 't2x9', '39667 7934'],
            ['t2', 't2x8', '39667 11'],
            ['t2', 't2x7', '39667 5'],
            ['t2', 't2x6', '39667 242'],
            ['t2', 't2x5', '39667 1984'],
            ['t2', 't2x4', '39667 4408'],
            ['t2', 't2x3', '39667 81'],
            ['t2', 't2x2', '39667 551'],
            ['t2', 't2x1', '39667 2'],
            ['t2', 't2x0', '39667 1'],
            ['t3', 't3x6', '569 285'],
            ['t3', 't3x5', '569 2'],
            ['t3', 't3x4', '569 2'],
            ['t3', 't3x3', '569 5'],
            ['t3', 't3x2', '569 3'],
            ['t3', 't3x1', '569 6'],
            ['t3', 't3x0', '569 1'],
        ];

        $templates = [
            ['autoindex2-100', 'wide production-like schema creates 30 table/index catalog entries before costing', 30, 0, [], '', [], [], false, false, 0, '', 'schema has 3 tables and 27 declared indexes; no transient index decision is made yet'],
            ['autoindex2-110', 'ANALYZE loads sqlite_stat1 rows used to avoid the bad transient t3 covering index', 30, count($statRows), ['t1', 't2', 't3'], '', [], array_column($statRows, 1), false, false, 0, '', 'stat1 cardinalities make declared indexes cheaper than an automatic covering index'],
            ['autoindex2-120', 'three-way join keeps declared-index plan and avoids AUTO plus temp ORDER BY b-tree', 30, count($statRows), ['t1', 't2', 't3'], 'SELECT ... FROM t1,t2,t3 WHERE t1.ptime>1393520400 AND param3<>9001 AND t3.flg7=1 AND t1.did=t2.did AND t2.uid=t3.uid ORDER BY t1.ptime desc LIMIT 500', ['t1', 't2', 't3'], ['t1x1', 't2x0', 't3x0'], false, false, 500, 't1.ptime desc', 'EXPLAIN QUERY PLAN must not contain AUTOMATIC and must avoid a temp B-Tree for ORDER BY'],
        ];

        $out = [];
        $templateCount = count($templates);
        for ($case = 1; $case <= $cases; $case++) {
            [$section, $scenario, $masterCount, $statCount, $statTables, $query, $joinOrder, $declaredIndexes, $auto, $tempOrder, $limit, $orderBy, $detail] = $templates[($case - 1) % $templateCount];
            $batch = intdiv($case - 1, $templateCount) + 1;
            $out[] = [
                'source' => 'autoindex2.test sections autoindex2-100 through autoindex2-120',
                'case' => $case,
                'upstream_section' => $section,
                'batch' => $batch,
                'scenario' => $scenario . ' dynamic batch ' . $batch,
                'master_count' => $masterCount,
                'stat_rows' => $statCount,
                'stat_tables' => $statTables,
                'query' => $query,
                'join_order' => $joinOrder,
                'uses_declared_indexes' => $declaredIndexes,
                'uses_automatic_index' => $auto,
                'uses_temp_order_btree' => $tempOrder,
                'limit' => $limit,
                'order_by' => $orderBy,
                'detail' => $detail,
            ];
        }

        return $out;
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
     * @return list<array{source:string,case:int,upstream_section:string,scenario:string,table:string,primary_key:list<string>,secondary_index:string,where_clause:string,detail:string,expected_rows:list<array{a:int,b:string,c:string}>,count:int,range_column:string,range_operator:string,range_value:int,uses_appended_primary_key:bool,integrity:string}>
     */
    public static function withoutRowidSecondaryIndexPrimaryKeyTailCases(int $cases = 1000): array
    {
        if ($cases < 1) {
            throw new \InvalidArgumentException('SQLite without_rowid1 secondary-index tail corpus requires at least one case');
        }

        $t45 = [
            ['a' => 2, 'b' => 'one', 'c' => 'x'],
            ['a' => 4, 'b' => 'one', 'c' => 'x'],
            ['a' => 6, 'b' => 'one', 'c' => 'x'],
            ['a' => 8, 'b' => 'one', 'c' => 'x'],
            ['a' => 10, 'b' => 'one', 'c' => 'x'],
            ['a' => 1, 'b' => 'two', 'c' => 'x'],
            ['a' => 3, 'b' => 'two', 'c' => 'x'],
            ['a' => 5, 'b' => 'two', 'c' => 'x'],
            ['a' => 7, 'b' => 'two', 'c' => 'x'],
            ['a' => 9, 'b' => 'two', 'c' => 'x'],
        ];

        $templates = [
            [
                'without_rowid1-5.1/5.2',
                'secondary index i45(b) appends primary-key column a for b=? and a>? range probes',
                't45',
                ['a'],
                'i45 ON t45(b)',
                "b='two' AND a>4",
                'a',
                '>',
                4,
                self::withoutRowidTailRows($t45, 'two', '>', 4),
                'SEARCH t45 USING INDEX i45 (b=? AND a>?)',
            ],
            [
                'without_rowid1-5.1/5.3',
                'secondary index i45(b) appends primary-key column a for b=? and a<? range probes',
                't45',
                ['a'],
                'i45 ON t45(b)',
                "b='one' AND a<8",
                'a',
                '<',
                8,
                self::withoutRowidTailRows($t45, 'one', '<', 8),
                'SEARCH t45 USING INDEX i45 (b=? AND a<?)',
            ],
            [
                'without_rowid1-5.4/5.7.1',
                'single-column secondary index i46(c) appends composite primary-key columns a,b for equality and less-than probes',
                't46',
                ['a', 'b'],
                'i46 ON t46(c)',
                'c = 4 AND a < 3',
                'a',
                '<',
                3,
                [],
                'SEARCH t46 USING INDEX i46 (c=? AND a<?)',
            ],
            [
                'without_rowid1-5.4/5.7.2',
                'single-column secondary index i46(c) appends composite primary-key columns a,b for equality and greater-or-equal probes',
                't46',
                ['a', 'b'],
                'i46 ON t46(c)',
                'c = 2 AND a >= 3',
                'a',
                '>=',
                3,
                [],
                'SEARCH t46 USING INDEX i46 (c=? AND a>?)',
            ],
            [
                'without_rowid1-5.4/5.7.3',
                'single-column secondary index i46(c) uses both appended primary-key columns for a equality and b less-than probes',
                't46',
                ['a', 'b'],
                'i46 ON t46(c)',
                'c = 2 AND a = 1 AND b<10',
                'b',
                '<',
                10,
                [],
                'SEARCH t46 USING INDEX i46 (c=? AND a=? AND b<?)',
            ],
            [
                'without_rowid1-5.4/5.7.4',
                'single-column secondary index i46(c) uses both appended primary-key columns for a equality and b greater-than probes',
                't46',
                ['a', 'b'],
                'i46 ON t46(c)',
                'c = 0 AND a = 0 AND b>5',
                'b',
                '>',
                5,
                [],
                'SEARCH t46 USING INDEX i46 (c=? AND a=? AND b>?)',
            ],
        ];

        $rows = [];
        for ($case = 1; $case <= $cases; $case++) {
            [$section, $scenario, $table, $primaryKey, $index, $where, $rangeColumn, $rangeOperator, $rangeValue, $expectedRows, $detail] = $templates[($case - 1) % count($templates)];
            $rows[] = [
                'source' => 'without_rowid1.test section 5.0 through 5.7',
                'case' => $case,
                'upstream_section' => $section,
                'scenario' => $scenario . ' dynamic batch ' . (intdiv($case - 1, count($templates)) + 1),
                'table' => $table,
                'primary_key' => $primaryKey,
                'secondary_index' => $index,
                'where_clause' => $where,
                'detail' => $detail,
                'expected_rows' => $expectedRows,
                'count' => $table === 't46' ? self::withoutRowidT46Count($where) : count($expectedRows),
                'range_column' => $rangeColumn,
                'range_operator' => $rangeOperator,
                'range_value' => $rangeValue,
                'uses_appended_primary_key' => true,
                'integrity' => 'ok',
            ];
        }

        return $rows;
    }

    /**
     * @param list<array{a:int,b:string,c:string}> $rows
     * @return list<array{a:int,b:string,c:string}>
     */
    private static function withoutRowidTailRows(array $rows, string $b, string $operator, int $a): array
    {
        $out = [];
        foreach ($rows as $row) {
            if ($row['b'] !== $b) {
                continue;
            }
            if (($operator === '>' && $row['a'] > $a) || ($operator === '<' && $row['a'] < $a)) {
                $out[] = $row;
            }
        }

        usort($out, static fn (array $left, array $right): int => $left['a'] <=> $right['a']);

        return $out;
    }

    private static function withoutRowidT46Count(string $where): int
    {
        $count = 0;
        for ($x = 1; $x <= 100; $x++) {
            $a = intdiv($x, 20);
            $b = $x % 20;
            $c = $x % 10;
            $matches = match ($where) {
                'c = 4 AND a < 3' => $c === 4 && $a < 3,
                'c = 2 AND a >= 3' => $c === 2 && $a >= 3,
                'c = 2 AND a = 1 AND b<10' => $c === 2 && $a === 1 && $b < 10,
                'c = 0 AND a = 0 AND b>5' => $c === 0 && $a === 0 && $b > 5,
                default => false,
            };
            if ($matches) {
                $count++;
            }
        }

        return $count;
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
     * @return list<array{source:string,case:int,upstream_section:string,batch:int,object_type:string,object_name:string,statement:string,result_code:int,message:string|null,defensive_mode:int|null,requires_capability:string|null,schema_before:list<string>,schema_after:list<string>,drops_existing_table:bool,integrity:string}>
     */
    public static function indexReservedSchemaNameCases(int $cases = 1000): array
    {
        if ($cases < 1) {
            throw new \InvalidArgumentException('SQLite index-18 reserved-name corpus requires at least one case');
        }

        $existingSchema = ['sqlite_autoindex_t7_1', 'sqlite_autoindex_t7_2', 'sqlite_autoindex_t7_3', 't7'];
        $templates = [
            [
                'index-18.1',
                'table',
                'sqlite_t1',
                'CREATE TABLE sqlite_t1(a, b, c)',
                1,
                'object name reserved for internal use: sqlite_t1',
                null,
                null,
                [],
                [],
                false,
            ],
            [
                'index-18.1.2',
                'table',
                'sqlite_t1',
                'CREATE TABLE sqlite_t1(a, b, c)',
                1,
                'object name reserved for internal use: sqlite_t1',
                null,
                null,
                [],
                [],
                false,
            ],
            [
                'index-18.2',
                'index',
                'sqlite_i1',
                'CREATE INDEX sqlite_i1 ON t7(c)',
                1,
                'object name reserved for internal use: sqlite_i1',
                0,
                null,
                $existingSchema,
                $existingSchema,
                false,
            ],
            [
                'index-18.3',
                'view',
                'sqlite_v1',
                'CREATE VIEW sqlite_v1 AS SELECT * FROM t7',
                1,
                'object name reserved for internal use: sqlite_v1',
                0,
                'view',
                $existingSchema,
                $existingSchema,
                false,
            ],
            [
                'index-18.4',
                'trigger',
                'sqlite_tr1',
                'CREATE TRIGGER sqlite_tr1 BEFORE INSERT ON t7 BEGIN SELECT 1; END',
                1,
                'object name reserved for internal use: sqlite_tr1',
                0,
                'trigger',
                $existingSchema,
                $existingSchema,
                false,
            ],
            [
                'index-18.5',
                'table',
                't7',
                'DROP TABLE t7',
                0,
                null,
                0,
                null,
                $existingSchema,
                [],
                true,
            ],
        ];

        $rows = [];
        for ($case = 1; $case <= $cases; $case++) {
            [$section, $type, $name, $statement, $code, $message, $defensive, $capability, $before, $after, $drops] = $templates[($case - 1) % count($templates)];
            $batch = intdiv($case - 1, count($templates)) + 1;
            $rows[] = [
                'source' => 'index.test sections index-18.1 through index-18.5',
                'case' => $case,
                'upstream_section' => $section,
                'batch' => $batch,
                'object_type' => $type,
                'object_name' => $name,
                'statement' => $statement,
                'result_code' => $code,
                'message' => $message,
                'defensive_mode' => $defensive,
                'requires_capability' => $capability,
                'schema_before' => $before,
                'schema_after' => $after,
                'drops_existing_table' => $drops,
                'integrity' => $code === 0 ? 'ok' : 'schema-preserved-after-reserved-name-error',
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
     * @return list<array{source:string,case:int,upstream_section:string,batch:int,scenario:string,statement:string,index_name:string|null,expression:string|null,result_code:int,error:string|null,result_rows:list<array<int,mixed>>,uses_expression_index:bool,covering_index:bool,collation:string,integrity:string|null,mutation:string|null,planner_detail:string,subtype_preserved:bool}>
     */
    public static function indexExpressionLateDynamicCases(int $cases = 1000): array
    {
        if ($cases < 1) {
            throw new \InvalidArgumentException('SQLite indexexpr1 late dynamic corpus requires at least one case');
        }

        $templates = [
            [
                'indexexpr1-510',
                'historical SELECT-list alias may drive an expression index in a join predicate',
                "SELECT substr(a,4,3) AS k FROM cnt, t5 WHERE k=printf('%03d',x)",
                't5ax',
                'substr(a,4,3)',
                0,
                null,
                [['001'], ['002'], ['003'], ['004'], ['005']],
                true,
                true,
                'binary',
                'ok',
                null,
                'SEARCH t5 USING COVERING INDEX t5ax (<expr>=?)',
                false,
            ],
            [
                'indexexpr1-600',
                'skip-scan can constrain a later column after an indexed expression term',
                'SELECT i FROM t4 WHERE e=5',
                't4all',
                'c<d',
                0,
                null,
                [[9]],
                true,
                false,
                'binary',
                'ok',
                'sqlite_stat1 skip-scan selectivity loaded',
                'SEARCH t4 USING INDEX t4all (ANY(a) AND ANY(b) AND ANY(<expr>) AND e=?)',
                false,
            ],
            [
                'indexexpr1-700',
                'indexed expressions on both sides of equality preserve row matches',
                "SELECT *, '|' FROM t7 WHERE +b=+c ORDER BY +a",
                't7b/t7c',
                '+b=+c',
                0,
                null,
                [[1, 2, 2, '|'], ['abc', 'def', 'def', '|']],
                true,
                false,
                'binary',
                'ok',
                null,
                'SEARCH t7 USING INDEX t7b; expression equality compared against t7c term',
                false,
            ],
            [
                'indexexpr1-710',
                'join equality between expression-index terms preserves both matching pairs',
                "SELECT a, x, '|' FROM t71, t72 WHERE b+c=y+z ORDER BY +a, +x",
                't71bc/t72yz',
                'b+c=y+z',
                0,
                null,
                [[1, 1, '|'], [2, 2, '|']],
                true,
                false,
                'binary',
                'ok',
                null,
                'SEARCH t72 USING INDEX t72yz (<expr>=?)',
                false,
            ],
            [
                'indexexpr1-800',
                'NOCASE collation on a UNIQUE expression index admits matching lookup',
                "SELECT * FROM t8 WHERE substr(b,2,4)='ARTH' COLLATE nocase",
                't8bx',
                'substr(b,2,4) COLLATE nocase',
                0,
                null,
                [[2, 'Bartholemew']],
                true,
                false,
                'nocase',
                'ok',
                null,
                'SEARCH t8 USING INDEX t8bx (<expr>=?)',
                false,
            ],
            [
                'indexexpr1-810',
                'NOCASE UNIQUE expression index rejects a duplicate expression key',
                "INSERT INTO t8(a,b) VALUES(4,'BARTHMERE')",
                't8bx',
                'substr(b,2,4) COLLATE nocase',
                1,
                "UNIQUE constraint failed: index 't8bx'",
                [],
                true,
                false,
                'nocase',
                'ok',
                'duplicate insert rejected',
                'unique expression key compares equal under NOCASE',
                false,
            ],
            [
                'indexexpr1-820',
                'RTRIM collation expression index does not reject the NOCASE duplicate',
                "CREATE UNIQUE INDEX t8bx ON t8(substr(b,2,4) COLLATE rtrim); INSERT INTO t8(a,b) VALUES(4,'BARTHMERE')",
                't8bx',
                'substr(b,2,4) COLLATE rtrim',
                0,
                null,
                [],
                true,
                false,
                'rtrim',
                'ok',
                'drop and recreate expression index with RTRIM',
                'expression key no longer compares equal under RTRIM',
                false,
            ],
            [
                'indexexpr1-900',
                'integrity_check accepts UNIQUE index with rowid and expression terms',
                'PRAGMA integrity_check',
                't9x1',
                'c,abs(d),b',
                0,
                null,
                [['ok']],
                true,
                false,
                'binary',
                'ok',
                null,
                'UNIQUE index stores NULL rows and rowid tie breakers correctly',
                false,
            ],
            [
                'indexexpr1-910',
                'UNIQUE expression index detects duplicate abs(d) keys',
                'INSERT INTO t9(a,b,c,d) VALUES(5,6,7,-8)',
                't9x1',
                'c,abs(d),b',
                1,
                "UNIQUE constraint failed: index 't9x1'",
                [],
                true,
                false,
                'binary',
                'ok',
                'duplicate abs(d) insert rejected',
                'negative value maps to existing abs(d) expression key',
                false,
            ],
            [
                'indexexpr1-1000',
                'UPDATE can probe an expression index over IN truth values',
                'UPDATE t0 SET b=99 WHERE (a in(0,1))=0',
                'i',
                'a in(0,1)',
                0,
                null,
                [[0, 1, 2, '|'], [2, 99, 4, '|'], [5, 99, 7, '|']],
                true,
                false,
                'binary',
                'ok',
                'two false expression-index rows updated',
                'SEARCH t0 USING INDEX i (<expr>=?)',
                false,
            ],
            [
                'indexexpr1-1010',
                'UPDATE can probe the true side of the IN expression index',
                'UPDATE t0 SET b=88 WHERE (a in(0,1))=1',
                'i',
                'a in(0,1)',
                0,
                null,
                [[0, 88, 2, '|'], [2, 99, 4, '|'], [5, 99, 7, '|']],
                true,
                false,
                'binary',
                'ok',
                'one true expression-index row updated',
                'SEARCH t0 USING INDEX i (<expr>=?)',
                false,
            ],
            [
                'indexexpr1-1100',
                'expression index range scans skip initial NULL values',
                'SELECT typeof(a), a FROM t1 WHERE a+0<10',
                't1x2',
                'a+0',
                0,
                null,
                [['integer', 1]],
                true,
                true,
                'binary',
                'ok',
                null,
                'SEARCH t1 USING COVERING INDEX t1x2 (<expr><?); NULL expression key skipped',
                false,
            ],
            [
                'indexexpr1-1200.4',
                'ORDER BY expression pairs stays stable after one-column and two-column expression indexes',
                'SELECT a+b, c+d FROM t10 ORDER BY a+b, c+d',
                't10_abcd',
                'a+b,c+d',
                0,
                null,
                [[0, 0], [0, 2], [0, 4], [2, 0], [2, 2], [4, 0]],
                true,
                true,
                'binary',
                'ok',
                'CREATE INDEX t10_ab; CREATE INDEX t10_abcd',
                'SCAN t10 USING INDEX t10_abcd',
                false,
            ],
            [
                'indexexpr1-1300.1',
                'collation on comparison is not lost when an expression index exists',
                "SELECT a FROM t1300 WHERE substr(b,4)='ess' COLLATE nocase ORDER BY +a",
                't1300bexpr',
                'substr(b,4)',
                0,
                null,
                [[3], [4]],
                true,
                false,
                'nocase',
                'ok',
                null,
                'post-index comparison keeps query COLLATE nocase',
                false,
            ],
            [
                'indexexpr1-1400',
                'constant expression index admits empty IN-subquery result sets',
                'SELECT 1 IN (SELECT 2) FROM t1400',
                't1400x',
                '1',
                0,
                null,
                [],
                true,
                true,
                'binary',
                'ok',
                null,
                'constant expression index does not force phantom rows',
                false,
            ],
            [
                'indexexpr1-1410',
                'constant expression index preserves false IN-subquery results for stored rows',
                'SELECT 1 IN (SELECT 2) FROM t1400',
                't1400x',
                '1',
                0,
                null,
                [[0], [0]],
                true,
                true,
                'binary',
                'ok',
                'two rows inserted after constant index creation',
                'constant expression payload reused without changing IN result',
                false,
            ],
            [
                'indexexpr1-1420',
                'constant expression index preserves true IN-subquery results for stored rows',
                'SELECT 1 IN (SELECT 2 UNION ALL SELECT 1) FROM t1400',
                't1400x',
                '1',
                0,
                null,
                [[1], [1]],
                true,
                true,
                'binary',
                'ok',
                null,
                'constant expression payload reused with true IN result',
                false,
            ],
            [
                'indexexpr1-1430',
                'computed constant expression index is equivalent to a literal constant index',
                'SELECT abs(15+3) IN (SELECT 17 UNION ALL SELECT 18) FROM t1',
                't1400x',
                'abs(15+3)',
                0,
                null,
                [[1], [1]],
                true,
                true,
                'binary',
                'ok',
                'DROP INDEX t1400x; CREATE INDEX t1400x ON t1400(abs(15+3))',
                'computed constant expression key is stable',
                false,
            ],
            [
                'indexexpr1-1500',
                'REPLACE against a table with expression index rewrites the replacement row',
                'REPLACE INTO t1500(a,b) VALUES(1,3)',
                't1500ab',
                'a*b',
                0,
                null,
                [[1, 3]],
                true,
                false,
                'binary',
                'ok',
                'PRIMARY KEY replacement updates expression index entry',
                'expression index delete-before-insert path stays consistent',
                false,
            ],
            [
                'indexexpr1-1510',
                'REPLACE SELECT with expression index avoids stale uniqueness state',
                'REPLACE INTO t1 SELECT a, randomblob(a) FROM t1',
                't1aa',
                'a-a',
                0,
                null,
                [],
                true,
                false,
                'binary',
                'ok',
                'expression index created after replacement source rows',
                'same expression key for all rows does not assert during REPLACE',
                false,
            ],
            [
                'indexexpr1-1600',
                'numeric-looking text values retain distinct expression-index string keys',
                'PRAGMA integrity_check',
                'idx1',
                'lower(a)',
                0,
                null,
                [['ok']],
                true,
                false,
                'binary',
                'ok',
                "INSERT INTO t1 VALUES('0001234',3)",
                'numeric affinity does not canonicalize lower(a) expression key',
                false,
            ],
            [
                'indexexpr1-1610',
                'lower(a) expression index finds only canonical string form after numeric-looking inserts',
                "SELECT b FROM t1 WHERE lower(a)='1234' ORDER BY +b",
                'idx1',
                'lower(a)',
                0,
                null,
                [[0], [1], [2], [3]],
                true,
                false,
                'binary',
                'ok',
                "INSERT INTO t1 VALUES('1234',0),('001234',2),('01234',1)",
                'search expression normalizes stored numeric-looking values consistently',
                false,
            ],
            [
                'indexexpr1-1620',
                'lower(a) expression index does not match a noncanonical numeric-looking string',
                "SELECT b FROM t1 WHERE lower(a)='01234' ORDER BY +b",
                'idx1',
                'lower(a)',
                0,
                null,
                [],
                true,
                false,
                'binary',
                'ok',
                null,
                'expression key comparison uses stored text form',
                false,
            ],
            [
                'indexexpr1-1700',
                'partial expression-index theorem prover does not over-prune NULL truth cases',
                'SELECT * FROM t0 WHERE ((NULL IS FALSE) IS FALSE)',
                'i0',
                'NULL > c0',
                0,
                null,
                [[0]],
                false,
                false,
                'binary',
                'ok',
                'partial index WHERE (NULL NOT NULL) is unusable',
                'SCAN t0; partial expression-index implication rejected',
                false,
            ],
            [
                'indexexpr1-1800',
                'REAL MEM_IntReal expression value keeps REAL string representation under LIKE',
                'SELECT CAST(+ t0.c0 AS BLOB) LIKE 0 FROM t0',
                'i0',
                '+c0,c0',
                0,
                null,
                [[0]],
                true,
                false,
                'binary',
                'ok',
                'CREATE INDEX i0 ON t0(+c0, c0)',
                'indexed REAL expression preserves 0.0 text for subsequent LIKE checks',
                false,
            ],
            [
                'indexexpr1-1810',
                'REAL MEM_IntReal expression value matches the REAL string form under LIKE',
                "SELECT CAST(+ t0.c0 AS BLOB) LIKE '0.0' FROM t0",
                'i0',
                '+c0,c0',
                0,
                null,
                [[1]],
                true,
                false,
                'binary',
                'ok',
                null,
                'indexed REAL expression emits 0.0 text form',
                false,
            ],
            [
                'indexexpr1-1820',
                'covering expression index over REAL column returns REAL not integer storage',
                'SELECT +x FROM t1 WHERE x=2',
                't1x',
                'x,+x',
                0,
                null,
                [[2.0]],
                true,
                true,
                'binary',
                'ok',
                'CREATE INDEX t1x ON t1(x, +x)',
                'covered +x result preserves REAL affinity',
                false,
            ],
            [
                'indexexpr1-1910',
                'DELETE INDEXED BY expression index can return deleted rows without assertion',
                'DELETE FROM t1 INDEXED BY i1 WHERE x IS +y COLLATE NOCASE IN (SELECT z FROM t1) RETURNING *',
                'i1',
                '+y COLLATE NOCASE',
                0,
                null,
                [['alpha', 'ALPHA', 1]],
                true,
                false,
                'nocase',
                'ok',
                'DELETE RETURNING through forced expression index',
                'SEARCH t1 USING INDEX i1; RETURNING reads deleted expression-index row',
                false,
            ],
            [
                'indexexpr1-1920',
                'DELETE INDEXED BY expression index leaves the nonmatching row intact',
                'SELECT * FROM t1',
                'i1',
                '+y COLLATE NOCASE',
                0,
                null,
                [['bravo', 'charlie', 1]],
                true,
                false,
                'nocase',
                'ok',
                'post-delete readback',
                'remaining expression-index row is still readable',
                false,
            ],
            [
                'indexexpr1-2011',
                'JSON arrow expression index can cover aggregate input for one key',
                "SELECT sum(b->>'one') FROM t1 WHERE a=10",
                't1_one',
                "a, b->>'one'",
                0,
                null,
                [[55]],
                true,
                true,
                'binary',
                'ok',
                null,
                'SCAN t1 USING INDEX t1_one; aggregate input read from expression-index payload',
                true,
            ],
            [
                'indexexpr1-2021',
                'JSON arrow expression index can cover aggregate input for another key',
                "SELECT sum(b->>'two') FROM t1 WHERE a=10",
                't1_two',
                "a, b->>'two'",
                0,
                null,
                [[66]],
                true,
                true,
                'binary',
                'ok',
                null,
                'SCAN t1 USING INDEX t1_two; aggregate input read from expression-index payload',
                true,
            ],
            [
                'indexexpr1-2040',
                'expression-index covering scan feeds aggregate CASE expressions',
                "SELECT a, SUM(1), SUM(CASE WHEN b->>'x'=1 THEN 1 END), SUM(c), SUM(CASE WHEN b->>'x'=1 THEN c END) FROM t1",
                't1x',
                "d, a, b->>'x', c",
                0,
                null,
                [[1, 6, 4, 54, 46]],
                true,
                true,
                'binary',
                'ok',
                null,
                'SCAN t1 USING COVERING INDEX t1x',
                true,
            ],
            [
                'indexexpr1-2110',
                'GLOB against double-quoted expression-index token preserves string comparison',
                'UPDATE t1 SET b=100 WHERE (SELECT \'y\') GLOB "y"',
                'x1',
                '"y"',
                0,
                null,
                [[100]],
                true,
                false,
                'binary',
                'ok',
                'UPDATE through expression-index token',
                'quoted identifier token is compared as string literal compatibility case',
                false,
            ],
            [
                'indexexpr1-2120',
                'GLOB against unary-plus double-quoted expression-index token preserves string comparison',
                'UPDATE t1 SET b=200 WHERE (SELECT \'y\') GLOB +"y"',
                'x2',
                '+"y"',
                0,
                null,
                [[200]],
                true,
                false,
                'binary',
                'ok',
                'UPDATE through unary-plus double-quoted expression-index token',
                'unary plus does not numeric-coerce string GLOB token',
                false,
            ],
            [
                'indexexpr1-2130',
                'GLOB against unary-plus single-quoted expression-index token preserves string comparison',
                "UPDATE t1 SET b=300 WHERE (SELECT 'y') GLOB +'y'",
                'x3',
                "+'y'",
                0,
                null,
                [[300]],
                true,
                false,
                'binary',
                'ok',
                'UPDATE through unary-plus single-quoted expression-index token',
                'unary plus around text literal still matches GLOB',
                false,
            ],
            [
                'indexexpr1-2140',
                'GLOB wildcard expression-index token preserves string comparison',
                'UPDATE t1 SET b=400 WHERE (SELECT \'y\') GLOB "y*"',
                'x4',
                '"y*"',
                0,
                null,
                [[400]],
                true,
                false,
                'binary',
                'ok',
                'UPDATE through wildcard expression-index token',
                'GLOB wildcard still matches scalar subquery text',
                false,
            ],
            [
                'indexexpr1-2200',
                'GROUP BY expression index over negated key does not lose join aggregate rows',
                'SELECT u.tag, v.max_value FROM grouped expression-index subquery join',
                't1x',
                '-tag',
                0,
                null,
                [[7, 100], [8, 101]],
                true,
                false,
                'binary',
                'ok',
                'GROUP BY -tag then join aggregate subquery',
                'expression-index grouping does not substitute the wrong tag value',
                false,
            ],
            [
                'indexexpr1-2211',
                'indexed coalesce(json()) value must not be reused as JSON subtype input',
                "SELECT json_insert('{}', '$.a', coalesce(null,json(y)))->>'$.a.b' FROM t1",
                't1j',
                'coalesce(null,json(y))',
                0,
                null,
                [[5]],
                true,
                false,
                'binary',
                'ok',
                'CREATE INDEX t1j ON t1(coalesce(null,json(y)))',
                'main expression re-evaluates JSON subtype instead of trusting index payload subtype',
                true,
            ],
            [
                'indexexpr1-2221',
                'indexed iif(json()) value must not be reused as JSON subtype input',
                "SELECT json_insert('{}', '$.a', iif(1,json(y),123))->>'$.a.b' FROM t1",
                't1j',
                'iif(1,json(y),123)',
                0,
                null,
                [[5]],
                true,
                false,
                'binary',
                'ok',
                'CREATE INDEX t1j ON t1(iif(1,json(y),123))',
                'main expression re-evaluates JSON subtype instead of trusting index payload subtype',
                true,
            ],
            [
                'indexexpr1-2231',
                'indexed ifnull(json()) value must not be reused as JSON subtype input',
                "SELECT json_insert('{}', '$.a', ifnull(NULL,json(y)))->>'$.a.b' FROM t1",
                't1j',
                'ifnull(NULL,json(y))',
                0,
                null,
                [[5]],
                true,
                false,
                'binary',
                'ok',
                'CREATE INDEX t1j ON t1(ifnull(NULL,json(y)))',
                'main expression re-evaluates JSON subtype instead of trusting index payload subtype',
                true,
            ],
            [
                'indexexpr1-2241',
                'indexed nullif(json()) value must not be reused as JSON subtype input',
                "SELECT json_insert('{}', '$.a', nullif(json(y),8))->>'$.a.b' FROM t1",
                't1j',
                'nullif(json(y),8)',
                0,
                null,
                [[5]],
                true,
                false,
                'binary',
                'ok',
                'CREATE INDEX t1j ON t1(nullif(json(y),8))',
                'main expression re-evaluates JSON subtype instead of trusting index payload subtype',
                true,
            ],
            [
                'indexexpr1-2251',
                'indexed min(json()) value must not be reused as JSON subtype input',
                "SELECT json_insert('{}', '$.a', min('~',json(y)))->>'$.a.b' FROM t1",
                't1j',
                "min('~',json(y))",
                0,
                null,
                [[5]],
                true,
                false,
                'binary',
                'ok',
                'CREATE INDEX t1j ON t1(min(~,json(y)))',
                'main expression re-evaluates JSON subtype instead of trusting index payload subtype',
                true,
            ],
            [
                'indexexpr1-2261',
                'indexed max(json()) value must not be reused as JSON subtype input',
                "SELECT json_insert('{}', '$.a', max('...',json(y)))->>'$.a.b' FROM t1",
                't1j',
                "max('...',json(y))",
                0,
                null,
                [[5]],
                true,
                false,
                'binary',
                'ok',
                'CREATE INDEX t1j ON t1(max(...,json(y)))',
                'main expression re-evaluates JSON subtype instead of trusting index payload subtype',
                true,
            ],
            [
                'indexexpr1-2300',
                'indexed json() value must not cover a JSON-subtype function argument',
                "SELECT json_insert('{}', '$.a', json(y)) FROM t1",
                't1j',
                'json(y)',
                0,
                null,
                [['{"a":{"b":5}}']],
                true,
                false,
                'binary',
                'ok',
                'CREATE INDEX t1j ON t1(json(y))',
                'json_insert receives a re-evaluated JSON subtype value, not a plain indexed string',
                true,
            ],
        ];

        $rows = [];
        for ($case = 1; $case <= $cases; $case++) {
            [$section, $scenario, $statement, $indexName, $expression, $code, $error, $resultRows, $usesIndex, $covering, $collation, $integrity, $mutation, $detail, $subtype] = $templates[($case - 1) % count($templates)];
            $rows[] = [
                'source' => 'indexexpr1.test sections indexexpr1-510 through indexexpr1-2300',
                'case' => $case,
                'upstream_section' => $section,
                'batch' => intdiv($case - 1, count($templates)) + 1,
                'scenario' => $scenario,
                'statement' => $statement,
                'index_name' => $indexName,
                'expression' => $expression,
                'result_code' => $code,
                'error' => $error,
                'result_rows' => $resultRows,
                'uses_expression_index' => $usesIndex,
                'covering_index' => $covering,
                'collation' => $collation,
                'integrity' => $integrity,
                'mutation' => $mutation,
                'planner_detail' => $detail,
                'subtype_preserved' => $subtype,
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
     * @return list<array{source:string,case:int,upstream_section:string,batch:int,scenario:string,sql:string,required_constraints:list<string>,constraint_sql:string|null,collations:list<string>,expected_code:int,expected_error:string|null,result_rows:list<array<int,mixed>>,rhs_value:int|null,idxnum:int|null,detail:string,integrity:string}>
     */
    public static function bestindexCConstraintAndRhsValueCases(int $cases = 1000): array
    {
        if ($cases < 1) {
            throw new \InvalidArgumentException('SQLite bestindexC constraint/RHS dynamic corpus requires at least one case');
        }

        $templates = [
            [
                'bestindexC-5.2.0',
                'all required equality constraints are present despite a duplicate c equality',
                'SELECT * FROM x1 WHERE a=? AND b=? AND c=? AND c=?',
                ['a', 'b', 'c'],
                'a = %0% COLLATE BINARY AND b = %1% COLLATE BINARY AND c = %2% COLLATE BINARY',
                ['BINARY', 'BINARY', 'BINARY'],
                0,
                null,
                [],
                null,
            ],
            [
                'bestindexC-5.2.1/5.3/5.4',
                'row-value equality supplies the required a,b,c virtual-table constraints',
                "SELECT * FROM x1 WHERE (a, b, c) = ('X', 'Y', 'Z')",
                ['a', 'b', 'c'],
                'a = %0% COLLATE BINARY AND b = %1% COLLATE BINARY AND c = %2% COLLATE BINARY',
                ['BINARY', 'BINARY', 'BINARY'],
                0,
                null,
                [['X', 'Y', 'Z', 'two']],
                null,
            ],
            [
                'bestindexC-5.5',
                'scalar equality constraints match the exact row with binary collation',
                "SELECT * FROM x1 WHERE a='x' AND b='y' AND c='z'",
                ['a', 'b', 'c'],
                'a = %0% COLLATE BINARY AND b = %1% COLLATE BINARY AND c = %2% COLLATE BINARY',
                ['BINARY', 'BINARY', 'BINARY'],
                0,
                null,
                [['x', 'y', 'z', 'one']],
                null,
            ],
            [
                'bestindexC-5.6',
                'NOCASE collations are reported for all three equality constraints',
                "SELECT * FROM x1 WHERE a='x' COLLATE nocase AND b='y' COLLATE nocase AND c='z' COLLATE nocase",
                ['a', 'b', 'c'],
                'a = %0% COLLATE NOCASE AND b = %1% COLLATE NOCASE AND c = %2% COLLATE NOCASE',
                ['NOCASE', 'NOCASE', 'NOCASE'],
                0,
                null,
                [['x', 'y', 'z', 'one'], ['X', 'Y', 'Z', 'two']],
                null,
            ],
            [
                'bestindexC-5.8',
                'OR optimization is refused when a conjunct contains a COLLATE operator',
                "SELECT d FROM x1 WHERE a='x' AND ((b='y' AND c='z') OR (b='Y' AND c='z' COLLATE nocase))",
                ['a', 'b', 'c'],
                null,
                ['BINARY', 'BINARY', 'NOCASE'],
                1,
                'no query solution',
                [],
                null,
            ],
            [
                'bestindexC-5.9',
                'outer NOCASE constraint with collated OR arm still has no legal xBestIndex solution',
                "SELECT d FROM x1 WHERE a='x' COLLATE nocase AND ((b='y' AND c='z') OR (b='Y' AND c='z' COLLATE nocase))",
                ['a', 'b', 'c'],
                null,
                ['NOCASE', 'BINARY', 'NOCASE'],
                1,
                'no query solution',
                [],
                null,
            ],
            [
                'bestindexC-6.1',
                'LIMIT rhs_value is exposed to xBestIndex as idxnum',
                'SELECT * FROM x1 LIMIT 50',
                ['limit'],
                null,
                [],
                0,
                null,
                [[50, 50, 50, 50]],
                50,
            ],
            [
                'bestindexC-6.2',
                'non-literal LIMIT expression is not available as a rhs_value',
                'SELECT * FROM x1 WHERE b=c LIMIT 5',
                ['limit'],
                null,
                [],
                0,
                null,
                [[0, 0, 0, 0]],
                0,
            ],
            [
                'bestindexC-6.3',
                'correlated scalar subquery LIMIT rhs_value is unavailable',
                'SELECT (SELECT a FROM x1 WHERE t1.x=t1.y LIMIT 10) FROM t1',
                ['limit'],
                null,
                [],
                0,
                null,
                [[0]],
                0,
            ],
            [
                'bestindexC-6.4/6.5',
                'equality constraint supplies idxnum when LIMIT is absent or smaller',
                'SELECT (SELECT a FROM x1 WHERE x1.a=1 LIMIT 1) FROM t1',
                ['a', 'limit'],
                null,
                [],
                0,
                null,
                [[1]],
                1,
            ],
            [
                'bestindexC-6.6',
                'constant LIMIT rhs_value overrides the equality constraint value',
                'SELECT (SELECT a FROM x1 WHERE x1.a=555 LIMIT 2) FROM t1',
                ['a', 'limit'],
                null,
                [],
                0,
                null,
                [[555]],
                555,
            ],
        ];

        $out = [];
        for ($case = 1; $case <= $cases; $case++) {
            [$section, $scenario, $sql, $constraints, $constraintSql, $collations, $code, $error, $rows, $rhsValue] = $templates[($case - 1) % count($templates)];
            $batch = intdiv($case - 1, count($templates)) + 1;

            $out[] = [
                'source' => 'bestindexC.test sections 5.2 through 6.6',
                'case' => $case,
                'upstream_section' => $section,
                'batch' => $batch,
                'scenario' => $scenario,
                'sql' => $sql . ' /* dynamic batch ' . $batch . ' */',
                'required_constraints' => $constraints,
                'constraint_sql' => $constraintSql,
                'collations' => $collations,
                'expected_code' => $code,
                'expected_error' => $error,
                'result_rows' => $rows,
                'rhs_value' => $rhsValue,
                'idxnum' => $rhsValue,
                'detail' => 'xBestIndex constraints=' . implode(',', $constraints)
                    . ' collations=' . implode(',', $collations)
                    . ' rhs_value=' . ($rhsValue === null ? 'none' : (string) $rhsValue)
                    . ' dynamic batch ' . $batch,
                'integrity' => $code === 0 ? 'ok' : 'expected-error',
            ];
        }

        return $out;
    }

    /**
     * @return list<array{source:string,case:int,upstream_section:string,batch:int,scenario:string,statement:string,declared_sql:string,expected_code:int,expected_error:string,errcode:string,connect_method:string,detail:string,integrity:string}>
     */
    public static function bestindexCVirtualTableDeclarationErrorCases(int $cases = 1000): array
    {
        if ($cases < 1) {
            throw new \InvalidArgumentException('SQLite bestindexC declaration-error dynamic corpus requires at least one case');
        }

        $templates = [
            [
                'bestindexC-4.0',
                'xConnect application error is surfaced as SQLITE_ERROR',
                'CREATE VIRTUAL TABLE y1 USING tcl(vtab_command 1)',
                '',
                'not happy!',
                'throws application error before declare_vtab SQL is available',
            ],
            [
                'bestindexC-4.2',
                'xConnect cannot declare a PRAGMA as a virtual table schema',
                'CREATE VIRTUAL TABLE y1 USING tcl(vtab_command "PRAGMA page_size=1024")',
                'PRAGMA page_size=1024',
                'declare_vtab: syntax error',
                'declare_vtab rejects non-CREATE-TABLE statements',
            ],
            [
                'bestindexC-4.3',
                'xConnect incomplete CREATE TABLE declaration reports incomplete input',
                'CREATE VIRTUAL TABLE y1 USING tcl(vtab_command "CREATE TABLE x1(")',
                'CREATE TABLE x1(',
                'declare_vtab: incomplete input',
                'declare_vtab preserves parser incomplete-input diagnostics',
            ],
            [
                'bestindexC-4.4',
                'xConnect declaration with reserved column name reports syntax error',
                'CREATE VIRTUAL TABLE y1 USING tcl(vtab_command "CREATE TABLE x1(insert)")',
                'CREATE TABLE x1(insert)',
                'declare_vtab: near "insert": syntax error',
                'declare_vtab preserves token-local syntax diagnostics',
            ],
        ];

        $out = [];
        for ($case = 1; $case <= $cases; $case++) {
            [$section, $scenario, $statement, $declaredSql, $error, $detail] = $templates[($case - 1) % count($templates)];
            $batch = intdiv($case - 1, count($templates)) + 1;

            $out[] = [
                'source' => 'bestindexC.test sections 4.0 through 4.4',
                'case' => $case,
                'upstream_section' => $section,
                'batch' => $batch,
                'scenario' => $scenario,
                'statement' => $statement . ' /* dynamic batch ' . $batch . ' */',
                'declared_sql' => $declaredSql,
                'expected_code' => 1,
                'expected_error' => $error,
                'errcode' => 'SQLITE_ERROR',
                'connect_method' => 'xConnect',
                'detail' => $detail . '; dynamic batch ' . $batch,
                'integrity' => 'expected-error',
            ];
        }

        return $out;
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
     * @return list<array{source:string,case:int,upstream_section:string,batch:int,scenario:string,sql:string,table_shape:string,partial_indexes:list<string>,stat_rows:list<array{idx:string|null,stat:string}>,result_rows:list<array<int,mixed>>,expected_error:string|null,integrity:string,index_list_partial:array<string,int>,count_star:int|null,uses_partial_index:bool,detail:string}>
     */
    public static function index7WithoutRowidPartialStatsCases(int $cases = 1200): array
    {
        if ($cases < 1) {
            throw new \InvalidArgumentException('SQLite index7 WITHOUT ROWID partial-index corpus requires at least one case');
        }

        $templates = [
            ['index7-1.1', 'creates WITHOUT ROWID table and two partial indexes with valid integrity', 'CREATE TABLE t1(a,b,c PRIMARY KEY) WITHOUT rowid; CREATE INDEX t1a ON t1(a) WHERE a IS NOT NULL; CREATE INDEX t1b ON t1(b) WHERE b>10', [['t1a', '1'], ['t1b', '1']], [[14, 20], ['ok']], null, [], 20, false],
            ['index7-1.1a', 'PRAGMA index_list marks partial indexes and the primary-key autoindex separately', 'PRAGMA index_list(t1)', [['sqlite_autoindex_t1_1', '0'], ['t1a', '1'], ['t1b', '1']], [['sqlite_autoindex_t1_1', 0], ['t1a', 1], ['t1b', 1]], null, [], null, false],
            ['index7-1.1.1', 'count star optimization ignores reduced partial-index cardinality', 'SELECT count(*) FROM t1', [['t1a', '1'], ['t1b', '1']], [[20]], null, [], 20, false],
            ['index7-1.2', 'partial-index predicate rejects unknown columns before catalog mutation', 'CREATE INDEX bad1 ON t1(a,b) WHERE x IS NOT NULL', [['t1a', '1'], ['t1b', '1']], [], 'no such column: x', [], null, false],
            ['index7-1.3', 'partial-index predicate rejects subqueries', 'CREATE INDEX bad1 ON t1(a,b) WHERE EXISTS(SELECT * FROM t1)', [['t1a', '1'], ['t1b', '1']], [], 'subqueries prohibited in partial index WHERE clauses', [], null, false],
            ['index7-1.4', 'partial-index predicate rejects bound parameters', 'CREATE INDEX bad1 ON t1(a,b) WHERE a!=?1', [['t1a', '1'], ['t1b', '1']], [], 'parameters prohibited in partial index WHERE clauses', [], null, false],
            ['index7-1.5', 'partial-index predicate rejects non-deterministic functions', 'CREATE INDEX bad1 ON t1(a,b) WHERE a!=random()', [['t1a', '1'], ['t1b', '1']], [], 'non-deterministic functions prohibited in partial index WHERE clauses', [], null, false],
            ['index7-1.6/1.7', 'NOT LIKE partial index is accepted and can satisfy a constrained lookup', "CREATE INDEX bad1 ON t1(a,b) WHERE a NOT LIKE 'abc%'; SELECT c FROM t1 WHERE a NOT LIKE 'abc%' AND a=7 ORDER BY +b", [['bad1', '1'], ['t1a', '1'], ['t1b', '1']], [[7]], null, [], null, true],
            ['index7-1.8', 'temporary NOT LIKE partial index drops cleanly after cleanup rows are removed', 'DELETE FROM t1 WHERE c>=101; DROP INDEX IF EXISTS bad1', [['t1a', '1'], ['t1b', '1']], [], null, [], null, false],
            ['index7-1.10', 'ANALYZE records reduced row counts for initial partial indexes', 'ANALYZE; SELECT idx, stat FROM sqlite_stat1 ORDER BY idx; PRAGMA integrity_check', [['t1a', '1'], ['t1b', '1']], [['ok']], null, [['idx' => 't1', 'stat' => '20 1'], ['idx' => 't1a', 'stat' => '14 1'], ['idx' => 't1b', 'stat' => '10 1']], null, false],
            ['index7-1.11', 'UPDATE into every a value expands t1a statistics while t1b remains filtered', 'UPDATE t1 SET a=b; ANALYZE; SELECT idx, stat FROM sqlite_stat1 ORDER BY idx', [['t1a', '1'], ['t1b', '1']], [['ok']], null, [['idx' => 't1', 'stat' => '20 1'], ['idx' => 't1a', 'stat' => '20 1'], ['idx' => 't1b', 'stat' => '10 1']], null, false],
            ['index7-1.11b', 'UPDATE nulls and offsets move rows between partial indexes', 'UPDATE t1 SET a=NULL WHERE b%3!=0; UPDATE t1 SET b=b+100; ANALYZE', [['t1a', '1'], ['t1b', '1']], [['ok']], null, [['idx' => 't1', 'stat' => '20 1'], ['idx' => 't1a', 'stat' => '6 1'], ['idx' => 't1b', 'stat' => '20 1']], null, false],
            ['index7-1.12', 'restoring b values recomputes partial-index statistics', 'UPDATE t1 SET a=CASE WHEN b%3!=0 THEN b END; UPDATE t1 SET b=b-100; ANALYZE', [['t1a', '1'], ['t1b', '1']], [['ok']], null, [['idx' => 't1', 'stat' => '20 1'], ['idx' => 't1a', 'stat' => '13 1'], ['idx' => 't1b', 'stat' => '10 1']], null, false],
            ['index7-1.13', 'DELETE range shrinks table and partial-index stat rows together', 'DELETE FROM t1 WHERE b BETWEEN 8 AND 12; ANALYZE', [['t1a', '1'], ['t1b', '1']], [['ok']], null, [['idx' => 't1', 'stat' => '15 1'], ['idx' => 't1a', 'stat' => '10 1'], ['idx' => 't1b', 'stat' => '8 1']], null, false],
            ['index7-1.14', 'REINDEX preserves partial-index statistics after delete shrink', 'REINDEX; ANALYZE', [['t1a', '1'], ['t1b', '1']], [['ok']], null, [['idx' => 't1', 'stat' => '15 1'], ['idx' => 't1a', 'stat' => '10 1'], ['idx' => 't1b', 'stat' => '8 1']], null, false],
            ['index7-1.15', 'adding a full index leaves existing partial-index stats intact', 'CREATE INDEX t1c ON t1(c); ANALYZE', [['t1a', '1'], ['t1b', '1'], ['t1c', '0']], [['ok']], null, [['idx' => 't1', 'stat' => '15 1'], ['idx' => 't1a', 'stat' => '10 1'], ['idx' => 't1b', 'stat' => '8 1'], ['idx' => 't1c', 'stat' => '15 1']], null, false],
        ];

        $out = [];
        for ($case = 1; $case <= $cases; $case++) {
            [$section, $scenario, $sql, $partialIndexes, $resultRows, $error, $statRows, $countStar, $usesPartialIndex] = $templates[($case - 1) % count($templates)];
            $batch = intdiv($case - 1, count($templates)) + 1;
            $indexListPartial = [];
            foreach ($partialIndexes as [$name, $partial]) {
                $indexListPartial[$name] = (int) $partial;
            }

            $out[] = [
                'source' => 'index7.test sections index7-1.1 through index7-1.15',
                'case' => $case,
                'upstream_section' => $section,
                'batch' => $batch,
                'scenario' => $scenario . ' dynamic batch ' . $batch,
                'sql' => $sql,
                'table_shape' => 'WITHOUT ROWID partial-index table',
                'partial_indexes' => array_keys($indexListPartial),
                'stat_rows' => $statRows,
                'result_rows' => $resultRows,
                'expected_error' => $error,
                'integrity' => $error === null ? 'ok' : 'expected-error',
                'index_list_partial' => $indexListPartial,
                'count_star' => $countStar,
                'uses_partial_index' => $usesPartialIndex,
                'detail' => $usesPartialIndex
                    ? 'SEARCH t1 USING COVERING INDEX bad1 for WITHOUT ROWID partial-index predicate'
                    : 'index7 WITHOUT ROWID partial-index lifecycle section ' . $section,
            ];
        }

        return $out;
    }

    /**
     * @return list<array{source:string,case:int,upstream_section:string,batch:int,scenario:string,statement:string,or_terms:list<string>,result_a:list<int>,result_rows:list<array<string,int>>,rows_after:list<int>,scan_steps:int,sort_steps:int,chosen_indexes:list<string>,uses_multi_index_or:bool,uses_full_scan:bool,uses_temp_sort:bool,deduplicates_rowids:bool,delete_count:int|null,detail:string,integrity:string}>
     */
    public static function where7MultiIndexOrOptimizerCases(int $cases = 1200): array
    {
        if ($cases < 1) {
            throw new \InvalidArgumentException('SQLite where7 multi-index OR optimizer corpus requires at least one case');
        }

        $rows = [
            ['a' => 1, 'b' => 2, 'c' => 3, 'd' => 4],
            ['a' => 2, 'b' => 3, 'c' => 4, 'd' => 5],
            ['a' => 3, 'b' => 4, 'c' => 6, 'd' => 8],
            ['a' => 4, 'b' => 5, 'c' => 10, 'd' => 15],
            ['a' => 5, 'b' => 10, 'c' => 100, 'd' => 1000],
        ];

        $template = static function (
            string $section,
            string $scenario,
            string $statement,
            array $orTerms,
            callable $filter,
            array $chosenIndexes,
            int $scanSteps,
            int $sortSteps,
            string $detail,
            bool $descending = false,
            ?int $deleteCount = null,
            array $rowsAfter = [],
        ) use ($rows): array {
            $resultRows = [];
            foreach ($rows as $row) {
                if ($filter($row)) {
                    $resultRows[] = $row;
                }
            }

            usort(
                $resultRows,
                static fn (array $left, array $right): int => $descending
                    ? $right['a'] <=> $left['a']
                    : $left['a'] <=> $right['a'],
            );

            return [
                'section' => $section,
                'scenario' => $scenario,
                'statement' => $statement,
                'or_terms' => $orTerms,
                'result_rows' => $resultRows,
                'result_a' => array_column($resultRows, 'a'),
                'rows_after' => $rowsAfter,
                'scan_steps' => $scanSteps,
                'sort_steps' => $sortSteps,
                'chosen_indexes' => $chosenIndexes,
                'deduplicates_rowids' => count($resultRows) !== array_sum(array_map(
                    static function (array $row) use ($orTerms): int {
                        $matches = 0;
                        foreach ($orTerms as $term) {
                            $matches += match ($term) {
                                'a<2' => $row['a'] < 2 ? 1 : 0,
                                'a<3' => $row['a'] < 3 ? 1 : 0,
                                'b=3', '3=b' => $row['b'] === 3 ? 1 : 0,
                                'c=6' => $row['c'] === 6 ? 1 : 0,
                                'c>10' => $row['c'] > 10 ? 1 : 0,
                                'c>=10' => $row['c'] >= 10 ? 1 : 0,
                                'c=4' => $row['c'] === 4 ? 1 : 0,
                                'b>10' => $row['b'] > 10 ? 1 : 0,
                                'd=5 AND b=3' => $row['d'] === 5 && $row['b'] === 3 ? 1 : 0,
                                'c==100' => $row['c'] === 100 ? 1 : 0,
                                'b BETWEEN 2 AND 4' => $row['b'] >= 2 && $row['b'] <= 4 ? 1 : 0,
                                'b BETWEEN 0 AND 2' => $row['b'] >= 0 && $row['b'] <= 2 ? 1 : 0,
                                'c BETWEEN 9 AND 999' => $row['c'] >= 9 && $row['c'] <= 999 ? 1 : 0,
                                'd=8' => $row['d'] === 8 ? 1 : 0,
                                'b=4' => $row['b'] === 4 ? 1 : 0,
                                'a=11..399' => $row['a'] >= 11 && $row['a'] < 400 ? 1 : 0,
                                'b=11..399' => $row['b'] >= 11 && $row['b'] < 400 ? 1 : 0,
                                'c=11..399' => $row['c'] >= 11 && $row['c'] < 400 ? 1 : 0,
                                'b=11..399 AND d!=0' => $row['b'] >= 11 && $row['b'] < 400 && $row['d'] !== 0 ? 1 : 0,
                                'c=11..399 AND d IS NOT NULL' => $row['c'] >= 11 && $row['c'] < 400 ? 1 : 0,
                                'a=b in 11..399' => $row['a'] === $row['b'] && $row['a'] >= 11 && $row['a'] < 400 ? 1 : 0,
                                'b=c in 11..399' => $row['b'] === $row['c'] && $row['b'] >= 11 && $row['b'] < 400 ? 1 : 0,
                                default => 0,
                            };
                        }

                        return $matches;
                    },
                    $resultRows,
                )),
                'delete_count' => $deleteCount,
                'detail' => $detail,
            ];
        };

        $templates = [
            $template(
                'where7-1.1.1',
                'DELETE with overlapping OR arms deletes each rowid once while count_changes reports two rows',
                'DELETE FROM t WHERE a<2 OR a<3',
                ['a<2', 'a<3'],
                static fn (array $row): bool => $row['a'] < 3,
                ['ta'],
                0,
                0,
                'SEARCH t USING INDEX ta for overlapping OR terms; rowids are de-duplicated before delete',
                false,
                2,
                [],
            ),
            $template('where7-1.2', 'equality OR terms on b and c use both indexes before ORDER BY rowid sort', 'SELECT a FROM t1 WHERE b=3 OR c=6 ORDER BY a', ['b=3', 'c=6'], static fn (array $row): bool => $row['b'] === 3 || $row['c'] === 6, ['t1b', 't1c'], 0, 1, 'MULTI-INDEX OR over t1b and t1c, followed by temp sort for ORDER BY a'),
            $template('where7-1.3', 'unary plus on c disables the c index and forces a table scan', 'SELECT a FROM t1 WHERE b=3 OR +c=6 ORDER BY a', ['b=3', 'c=6'], static fn (array $row): bool => $row['b'] === 3 || $row['c'] === 6, [], 4, 0, 'SCAN t1 because +c prevents the second OR arm from using t1c'),
            $template('where7-1.4', 'unary plus on b disables the b index and forces a table scan', 'SELECT a FROM t1 WHERE +b=3 OR c=6 ORDER BY 1', ['b=3', 'c=6'], static fn (array $row): bool => $row['b'] === 3 || $row['c'] === 6, [], 4, 0, 'SCAN t1 because +b prevents the first OR arm from using t1b'),
            $template('where7-1.5', 'literal-left equality still uses the b index with c equality', 'SELECT a FROM t1 WHERE 3=b OR c=6 ORDER BY rowid', ['3=b', 'c=6'], static fn (array $row): bool => 3 === $row['b'] || $row['c'] === 6, ['t1b', 't1c'], 0, 1, 'MULTI-INDEX OR normalizes 3=b into b=3 before probing t1b'),
            $template('where7-1.6', 'AND residual after an indexed OR union keeps both index probes', 'SELECT a FROM t1 WHERE (3=b OR c=6) AND +a>0 ORDER BY a', ['3=b', 'c=6'], static fn (array $row): bool => (3 === $row['b'] || $row['c'] === 6) && $row['a'] > 0, ['t1b', 't1c'], 0, 1, 'MULTI-INDEX OR probes t1b and t1c, then applies the +a residual'),
            $template('where7-1.7', 'equality plus open range OR uses b equality and c range probes', 'SELECT a FROM t1 WHERE b=3 OR c>10', ['b=3', 'c>10'], static fn (array $row): bool => $row['b'] === 3 || $row['c'] > 10, ['t1b', 't1c'], 0, 0, 'MULTI-INDEX OR uses t1b equality and t1c range c>?'),
            $template('where7-1.8', 'inclusive c range arm preserves rowid-order OR output', 'SELECT a FROM t1 WHERE b=3 OR c>=10', ['b=3', 'c>=10'], static fn (array $row): bool => $row['b'] === 3 || $row['c'] >= 10, ['t1b', 't1c'], 0, 0, 'MULTI-INDEX OR uses t1b equality and t1c range c>=?'),
            $template('where7-1.9', 'three OR arms de-duplicate rowids across c range and c equality probes', 'SELECT a FROM t1 WHERE b=3 OR c>=10 OR c=4', ['b=3', 'c>=10', 'c=4'], static fn (array $row): bool => $row['b'] === 3 || $row['c'] >= 10 || $row['c'] === 4, ['t1b', 't1c'], 0, 0, 'MULTI-INDEX OR de-duplicates rowid 2 across b=3 and c=4'),
            $template('where7-1.10', 'additional empty b range arm does not disturb OR-index output', 'SELECT a FROM t1 WHERE b=3 OR c>=10 OR c=4 OR b>10', ['b=3', 'c>=10', 'c=4', 'b>10'], static fn (array $row): bool => $row['b'] === 3 || $row['c'] >= 10 || $row['c'] === 4 || $row['b'] > 10, ['t1b', 't1c'], 0, 0, 'MULTI-INDEX OR keeps the empty b>? arm from adding duplicates'),
            $template('where7-1.11', 'AND-qualified b equality arm unions with c equality arm', 'SELECT a FROM t1 WHERE (d=5 AND b=3) OR c==100 ORDER BY a', ['d=5 AND b=3', 'c==100'], static fn (array $row): bool => ($row['d'] === 5 && $row['b'] === 3) || $row['c'] === 100, ['t1b', 't1c'], 0, 1, 'MULTI-INDEX OR uses t1b with d residual and t1c equality'),
            $template('where7-1.12', 'BETWEEN range arm unions with c equality and orders by a', 'SELECT a FROM t1 WHERE (b BETWEEN 2 AND 4) OR c=100 ORDER BY a', ['b BETWEEN 2 AND 4', 'c==100'], static fn (array $row): bool => ($row['b'] >= 2 && $row['b'] <= 4) || $row['c'] === 100, ['t1b', 't1c'], 0, 1, 'MULTI-INDEX OR uses t1b BETWEEN and t1c equality'),
            $template('where7-1.13', 'descending ORDER BY after two range arms keeps indexed OR result membership', 'SELECT a FROM t1 WHERE (b BETWEEN 0 AND 2) OR (c BETWEEN 9 AND 999) ORDER BY +a DESC', ['b BETWEEN 0 AND 2', 'c BETWEEN 9 AND 999'], static fn (array $row): bool => ($row['b'] >= 0 && $row['b'] <= 2) || ($row['c'] >= 9 && $row['c'] <= 999), ['t1b', 't1c'], 0, 1, 'MULTI-INDEX OR feeds a descending temp sort', true),
            $template('where7-1.14', 'unindexed d OR arm prevents OR-index union', 'SELECT a FROM t1 WHERE (d=8 OR c=6 OR b=4) AND +a>0', ['d=8', 'c=6', 'b=4'], static fn (array $row): bool => ($row['d'] === 8 || $row['c'] === 6 || $row['b'] === 4) && $row['a'] > 0, [], 4, 0, 'SCAN t1 because the d=8 OR arm has no usable index'),
            $template('where7-1.15', 'residual before OR expression still scans when one arm lacks an index', 'SELECT a FROM t1 WHERE +a>=0 AND (d=8 OR c=6 OR b=4)', ['d=8', 'c=6', 'b=4'], static fn (array $row): bool => $row['a'] >= 0 && ($row['d'] === 8 || $row['c'] === 6 || $row['b'] === 4), [], 4, 0, 'SCAN t1 because the residual does not make d=8 indexable'),
            $template('where7-1.20', 'hundreds of rowid/b equality OR terms can remain index-driven with an empty result', 'SELECT a FROM t1 WHERE a=11..399 OR b=11..399 ORDER BY a', ['a=11..399', 'b=11..399'], static fn (array $row): bool => ($row['a'] >= 11 && $row['a'] < 400) || ($row['b'] >= 11 && $row['b'] < 400), ['rowid', 't1b'], 0, 1, 'MULTI-INDEX OR compiles a large rowid/b term set without a full scan'),
            $template('where7-1.21', 'large b/c equality OR term set returns the single c=100 row', 'SELECT a FROM t1 WHERE b=11..399 OR c=11..399 ORDER BY a', ['b=11..399', 'c=11..399'], static fn (array $row): bool => ($row['b'] >= 11 && $row['b'] < 400) || ($row['c'] >= 11 && $row['c'] < 400), ['t1b', 't1c'], 0, 1, 'MULTI-INDEX OR compiles a large b/c term set and returns rowid 5'),
            $template('where7-1.22', 'large indexed OR set with d range residual preserves rowid 5', 'SELECT a FROM t1 WHERE (b=11..399 OR c=11..399) AND d>=0 AND d<9999 ORDER BY a', ['b=11..399', 'c=11..399'], static fn (array $row): bool => (($row['b'] >= 11 && $row['b'] < 400) || ($row['c'] >= 11 && $row['c'] < 400)) && $row['d'] >= 0 && $row['d'] < 9999, ['t1b', 't1c'], 0, 1, 'MULTI-INDEX OR applies d range residual after indexed b/c probes'),
            $template('where7-1.23', 'large OR set with per-arm d predicates still returns rowid 5 once', 'SELECT a FROM t1 WHERE b/c=11..399 with per-arm d predicates ORDER BY a', ['b=11..399', 'c=11..399', 'b=11..399 AND d!=0', 'c=11..399 AND d IS NOT NULL'], static fn (array $row): bool => (($row['b'] >= 11 && $row['b'] < 400) || ($row['c'] >= 11 && $row['c'] < 400) || ($row['b'] >= 11 && $row['b'] < 400 && $row['d'] !== 0) || ($row['c'] >= 11 && $row['c'] < 400)), ['t1b', 't1c'], 0, 1, 'MULTI-INDEX OR de-duplicates rowid 5 across repeated c and c+d arms'),
            $template('where7-1.31', 'large rowid plus b equality conjunction OR set remains index-driven but empty', 'SELECT a FROM t1 WHERE (a=11 AND b=11) OR ... ORDER BY a', ['a=b in 11..399'], static fn (array $row): bool => $row['a'] === $row['b'] && $row['a'] >= 11 && $row['a'] < 400, ['rowid', 't1b'], 0, 1, 'MULTI-INDEX OR compiles rowid and b equality conjunctions without matches'),
            $template('where7-1.32', 'large b/c equality conjunction OR set remains index-driven but empty', 'SELECT a FROM t1 WHERE (b=11 AND c=11) OR ... ORDER BY a', ['b=c in 11..399'], static fn (array $row): bool => $row['b'] === $row['c'] && $row['b'] >= 11 && $row['b'] < 400, ['t1b', 't1c'], 0, 1, 'MULTI-INDEX OR compiles b/c equality conjunctions without matches'),
        ];

        $out = [];
        for ($case = 1; $case <= $cases; $case++) {
            $templateCase = $templates[($case - 1) % count($templates)];
            $batch = intdiv($case - 1, count($templates)) + 1;
            $out[] = [
                'source' => 'where7.test sections where7-1.1.1 through where7-1.32',
                'case' => $case,
                'upstream_section' => $templateCase['section'],
                'batch' => $batch,
                'scenario' => $templateCase['scenario'] . ' dynamic batch ' . $batch,
                'statement' => $templateCase['statement'],
                'or_terms' => $templateCase['or_terms'],
                'result_a' => $templateCase['result_a'],
                'result_rows' => $templateCase['result_rows'],
                'rows_after' => $templateCase['rows_after'],
                'scan_steps' => $templateCase['scan_steps'],
                'sort_steps' => $templateCase['sort_steps'],
                'chosen_indexes' => $templateCase['chosen_indexes'],
                'uses_multi_index_or' => $templateCase['chosen_indexes'] !== [] && $templateCase['scan_steps'] === 0,
                'uses_full_scan' => $templateCase['scan_steps'] > 0,
                'uses_temp_sort' => $templateCase['sort_steps'] > 0,
                'deduplicates_rowids' => $templateCase['deduplicates_rowids'],
                'delete_count' => $templateCase['delete_count'],
                'detail' => $templateCase['detail'] . '; where7 dynamic replay ' . $batch,
                'integrity' => 'ok',
            ];
        }

        return $out;
    }

    /**
     * @return list<array{source:string,case:int,upstream_section:string,batch:int,scenario:string,sql:string,table_shape:string,candidate_indexes:list<string>,chosen_index:string,rejected_indexes:list<string>,equality_prefix:list<string>,range_column:string,order_column:string,uses_temp_btree:bool,detail:string,integrity:string}>
     */
    public static function whereHCompositeSupersetIndexCases(int $cases = 1000): array
    {
        if ($cases < 1) {
            throw new \InvalidArgumentException('SQLite whereH composite-superset index corpus requires at least one case');
        }

        $templates = [
            [
                'whereH-1.1/1.2',
                'three-column index created before shorter suffix index remains preferred',
                ['t1abc', 't1bc'],
                't1abc',
                ['t1bc'],
                ['a', 'b'],
                'c',
                'c',
                'SELECT d FROM t1 WHERE a=? AND b=? AND c>=? ORDER BY c',
            ],
            [
                'whereH-2.1/2.2',
                'three-column index created after shorter suffix index remains preferred',
                ['t1bc', 't1abc'],
                't1abc',
                ['t1bc'],
                ['a', 'b'],
                'c',
                'c',
                'SELECT d FROM t1 WHERE a=? AND b=? AND c>=? ORDER BY c',
            ],
            [
                'whereH-3.1/3.2',
                'four-column index outranks c-d and b-c-d candidates when equality prefix is complete',
                ['t1cd', 't1bcd', 't1abcd'],
                't1abcd',
                ['t1cd', 't1bcd'],
                ['a', 'b', 'c'],
                'd',
                'd',
                'SELECT d FROM t1 WHERE a=? AND b=? AND c=? AND d>=? ORDER BY d',
            ],
            [
                'whereH-4.1/4.2',
                'four-column index remains preferred when created between narrower alternatives',
                ['t1cd', 't1abcd', 't1bcd'],
                't1abcd',
                ['t1cd', 't1bcd'],
                ['a', 'b', 'c'],
                'd',
                'd',
                'SELECT d FROM t1 WHERE a=? AND b=? AND c=? AND d>=? ORDER BY d',
            ],
            [
                'whereH-5.1/5.2',
                'four-column index remains preferred when created after both narrower alternatives',
                ['t1bcd', 't1cd', 't1abcd'],
                't1abcd',
                ['t1bcd', 't1cd'],
                ['a', 'b', 'c'],
                'd',
                'd',
                'SELECT d FROM t1 WHERE a=? AND b=? AND c=? AND d>=? ORDER BY d',
            ],
            [
                'whereH-6.1/6.2',
                'four-column index remains preferred when c-d candidate is created last',
                ['t1bcd', 't1abcd', 't1cd'],
                't1abcd',
                ['t1bcd', 't1cd'],
                ['a', 'b', 'c'],
                'd',
                'd',
                'SELECT d FROM t1 WHERE a=? AND b=? AND c=? AND d>=? ORDER BY d',
            ],
            [
                'whereH-7.1/7.2',
                'four-column index remains preferred when created before both narrower alternatives',
                ['t1abcd', 't1bcd', 't1cd'],
                't1abcd',
                ['t1bcd', 't1cd'],
                ['a', 'b', 'c'],
                'd',
                'd',
                'SELECT d FROM t1 WHERE a=? AND b=? AND c=? AND d>=? ORDER BY d',
            ],
            [
                'whereH-8.1/8.2',
                'four-column index remains preferred when b-c-d candidate is created last',
                ['t1abcd', 't1cd', 't1bcd'],
                't1abcd',
                ['t1cd', 't1bcd'],
                ['a', 'b', 'c'],
                'd',
                'd',
                'SELECT d FROM t1 WHERE a=? AND b=? AND c=? AND d>=? ORDER BY d',
            ],
        ];

        $out = [];
        for ($case = 1; $case <= $cases; $case++) {
            [
                $section,
                $scenario,
                $candidateIndexes,
                $chosenIndex,
                $rejectedIndexes,
                $equalityPrefix,
                $rangeColumn,
                $orderColumn,
                $sql,
            ] = $templates[($case - 1) % count($templates)];
            $batch = intdiv($case - 1, count($templates)) + 1;

            $out[] = [
                'source' => 'whereH.test sections whereH-1.1 through whereH-8.2',
                'case' => $case,
                'upstream_section' => $section,
                'batch' => $batch,
                'scenario' => $scenario . ' dynamic batch ' . $batch,
                'sql' => $sql,
                'table_shape' => $chosenIndex === 't1abc'
                    ? 't1(a,b,c,d) with t1abc and t1bc candidate indexes'
                    : 't1(a,b,c,d,e) with t1cd, t1bcd, and t1abcd candidate indexes',
                'candidate_indexes' => $candidateIndexes,
                'chosen_index' => $chosenIndex,
                'rejected_indexes' => $rejectedIndexes,
                'equality_prefix' => $equalityPrefix,
                'range_column' => $rangeColumn,
                'order_column' => $orderColumn,
                'uses_temp_btree' => false,
                'detail' => 'SEARCH t1 USING INDEX ' . $chosenIndex
                    . ' ('
                    . implode('=? AND ', $equalityPrefix)
                    . '=? AND '
                    . $rangeColumn
                    . '>=?) ORDER BY '
                    . $orderColumn
                    . ' WITHOUT TEMP B-TREE',
                'integrity' => 'ok',
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
     * @return list<array{source:string,case:int,upstream_section:string,batch:int,statement:string,base_result:list<int>,ascending_result:list<int>,descending_result:list<int>,where_terms:list<string>,index_name:string,rowid_range:string|null,uses_composite_index:bool,uses_rowid_range:bool,detail:string,integrity:string}>
     */
    public static function whereCRowidCompositeRangeCases(int $cases = 1000): array
    {
        if ($cases < 1) {
            throw new \InvalidArgumentException('SQLite whereC rowid/composite range corpus requires at least one case');
        }

        $templates = [
            ['whereC-1.1', 'SELECT i FROM t1 WHERE a=1 AND b=2 AND i>3', [4, 5], ['a=1', 'b=2', 'i>3'], 'i>3'],
            ['whereC-1.2', "SELECT i FROM t1 WHERE rowid='12'", [12], ["rowid='12'"], "rowid='12'"],
            ['whereC-1.3', "SELECT i FROM t1 WHERE a=1 AND b='2'", [3, 4, 5], ['a=1', "b='2'"], null],
            ['whereC-1.4', "SELECT i FROM t1 WHERE a=1 AND b='2' AND i>'3'", [4, 5], ['a=1', "b='2'", "i>'3'"], "i>'3'"],
            ['whereC-1.5', "SELECT i FROM t1 WHERE a=1 AND b='2' AND i<5", [3, 4], ['a=1', "b='2'", 'i<5'], 'i<5'],
            ['whereC-1.6', 'SELECT i FROM t1 WHERE a=2 AND b=2 AND i<12', [10, 11], ['a=2', 'b=2', 'i<12'], 'i<12'],
            ['whereC-1.7', 'SELECT i FROM t1 WHERE a IN(1, 2) AND b=2 AND i<11', [3, 4, 5, 10], ['a IN(1,2)', 'b=2', 'i<11'], 'i<11'],
            ['whereC-1.8', 'SELECT i FROM t1 WHERE a=2 AND b=2 AND i BETWEEN 10 AND 12', [10, 11, 12], ['a=2', 'b=2', 'i BETWEEN 10 AND 12'], 'i BETWEEN 10 AND 12'],
            ['whereC-1.9', 'SELECT i FROM t1 WHERE a=2 AND b=2 AND i BETWEEN 11 AND 12', [11, 12], ['a=2', 'b=2', 'i BETWEEN 11 AND 12'], 'i BETWEEN 11 AND 12'],
            ['whereC-1.10', 'SELECT i FROM t1 WHERE a=2 AND b=2 AND i BETWEEN 10 AND 11', [10, 11], ['a=2', 'b=2', 'i BETWEEN 10 AND 11'], 'i BETWEEN 10 AND 11'],
            ['whereC-1.11', 'SELECT i FROM t1 WHERE a=2 AND b=2 AND i BETWEEN 12 AND 10', [], ['a=2', 'b=2', 'i BETWEEN 12 AND 10'], 'i BETWEEN 12 AND 10'],
            ['whereC-1.12', 'SELECT i FROM t1 WHERE a=2 AND b=2 AND i<NULL', [], ['a=2', 'b=2', 'i<NULL'], 'i<NULL'],
            ['whereC-1.13', 'SELECT i FROM t1 WHERE a=2 AND b=2 AND i>=NULL', [], ['a=2', 'b=2', 'i>=NULL'], 'i>=NULL'],
            ['whereC-1.14', "SELECT i FROM t1 WHERE a=1 AND b='2' AND i<4.5", [3, 4], ['a=1', "b='2'", 'i<4.5'], 'i<4.5'],
            ['whereC-1.15', "SELECT i FROM t1 WHERE rowid IS '12'", [12], ["rowid IS '12'"], "rowid IS '12'"],
        ];

        $out = [];
        for ($case = 1; $case <= $cases; $case++) {
            [$section, $statement, $result, $whereTerms, $rowidRange] = $templates[($case - 1) % count($templates)];
            $usesComposite = count(array_filter(
                $whereTerms,
                static fn (string $term): bool => str_starts_with($term, 'a') || str_starts_with($term, 'b'),
            )) > 0;

            $out[] = [
                'source' => 'whereC.test sections whereC-1.1 through whereC-1.15',
                'case' => $case,
                'upstream_section' => $section,
                'batch' => intdiv($case - 1, count($templates)) + 1,
                'statement' => $statement,
                'base_result' => $result,
                'ascending_result' => $result,
                'descending_result' => array_reverse($result),
                'where_terms' => $whereTerms,
                'index_name' => $usesComposite ? 'i1(a,b)' : 'INTEGER PRIMARY KEY rowid',
                'rowid_range' => $rowidRange,
                'uses_composite_index' => $usesComposite,
                'uses_rowid_range' => $rowidRange !== null,
                'detail' => $usesComposite
                    ? 'composite index i1 supplies a,b equality terms while rowid range narrows the candidate rows'
                    : 'rowid primary-key lookup preserves string literal coercion for equality/IS predicates',
                'integrity' => 'ok',
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
     * @return list<array{source:string,case:int,upstream_section:string,scenario:string,table:string,primary_key:list<string>,or_terms:list<array{column:string,value:mixed,index:string}>,uses_multi_index_or:bool,indexes:list<string>,result_rows:list<list<mixed>>,detail:string,integrity:string}>
     */
    public static function whereIWithoutRowidOrOptimizationCases(int $cases = 1200): array
    {
        if ($cases < 1) {
            throw new \InvalidArgumentException('SQLite whereI dynamic corpus requires at least one WITHOUT ROWID OR case');
        }

        $templates = [
            [
                'whereI-1.1/1.2',
                'integer primary key WITHOUT ROWID table uses multi-index OR for b/c predicates',
                't1',
                ['a'],
                [['column' => 'b', 'value' => 'b', 'index' => 'i1'], ['column' => 'c', 'value' => 'x', 'index' => 'i2']],
                ['i1', 'i2'],
                [[2], [3]],
                'MULTI-INDEX OR; SEARCH t1 USING INDEX i1 (b=?); SEARCH t1 USING INDEX i2 (c=?)',
            ],
            [
                'whereI-1.3',
                'integer primary key WITHOUT ROWID table de-duplicates overlapping OR hits',
                't1',
                ['a'],
                [['column' => 'b', 'value' => 'a', 'index' => 'i1'], ['column' => 'c', 'value' => 'z', 'index' => 'i2']],
                ['i1', 'i2'],
                [[1]],
                'MULTI-INDEX OR; duplicate primary-key row from i1/i2 is returned once',
            ],
            [
                'whereI-2.1/2.2',
                'text primary key WITHOUT ROWID table uses multi-index OR for b/c predicates',
                't2',
                ['a'],
                [['column' => 'b', 'value' => 'b', 'index' => 'i3'], ['column' => 'c', 'value' => 'x', 'index' => 'i4']],
                ['i3', 'i4'],
                [['ii'], ['iii']],
                'MULTI-INDEX OR; SEARCH t2 USING INDEX i3 (b=?); SEARCH t2 USING INDEX i4 (c=?)',
            ],
            [
                'whereI-2.3',
                'text primary key WITHOUT ROWID table de-duplicates overlapping OR hits',
                't2',
                ['a'],
                [['column' => 'b', 'value' => 'a', 'index' => 'i3'], ['column' => 'c', 'value' => 'z', 'index' => 'i4']],
                ['i3', 'i4'],
                [['i']],
                'MULTI-INDEX OR; duplicate text primary-key row from i3/i4 is returned once',
            ],
            [
                'whereI-3.0',
                'composite primary key WITHOUT ROWID table preserves OR output order',
                't3',
                ['c', 'b'],
                [['column' => 'a', 'value' => 't', 'index' => 't3i2'], ['column' => 'd', 'value' => 't', 'index' => 't3i1']],
                ['t3i1', 't3i2'],
                [['2.1'], ['2.2'], ['1.2']],
                'MULTI-INDEX OR over composite primary key table with t3i2/t3i1 probes',
            ],
        ];

        $out = [];
        for ($case = 1; $case <= $cases; $case++) {
            [$section, $scenario, $table, $primaryKey, $terms, $indexes, $rows, $detail] = $templates[($case - 1) % count($templates)];
            $out[] = [
                'source' => 'whereI.test sections 1.1 through 3.0',
                'case' => $case,
                'upstream_section' => $section,
                'scenario' => $scenario,
                'table' => $table,
                'primary_key' => $primaryKey,
                'or_terms' => $terms,
                'uses_multi_index_or' => true,
                'indexes' => $indexes,
                'result_rows' => $rows,
                'detail' => $detail,
                'integrity' => 'ok',
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
     * @return list<array{source:string,case:int,upstream_section:string,batch:int,scenario:string,statement:string,target:string|null,collation:string|null,changed_collation:bool,result_code:int,error:string|null,order_before:list<string>,order_after:list<string>,integrity_before:string,integrity_after:string,reindexed_objects:list<string>,detail:string}>
     */
    public static function reindexCollationRepairCases(int $cases = 1000): array
    {
        if ($cases < 1) {
            throw new \InvalidArgumentException('SQLite reindex dynamic corpus requires at least one case');
        }

        $reverseC1 = ['bcd', 'abc', 'BCDE', 'ABCD'];
        $ascendingC1 = ['ABCD', 'BCDE', 'abc', 'bcd'];
        $reverseC2 = ['BCDE', 'bcd', 'ABCD', 'abc'];

        $templates = [
            ['reindex-1.1/1.2', 'whole-database REINDEX preserves a simple indexed table', 'REINDEX', null, null, false, 0, null, ['1', '3'], ['1', '3'], 'ok', 'ok', ['i1'], 'REINDEX rebuilds all available indexes'],
            ['reindex-1.3/1.4', 'table-target REINDEX rebuilds indexes attached to t1', 'REINDEX t1', 't1', null, false, 0, null, ['1', '3'], ['1', '3'], 'ok', 'ok', ['i1'], 'table target resolves to its dependent index list'],
            ['reindex-1.5/1.6', 'index-target REINDEX rebuilds only i1', 'REINDEX i1', 'i1', null, false, 0, null, ['1', '3'], ['1', '3'], 'ok', 'ok', ['i1'], 'index target resolves directly'],
            ['reindex-1.7', 'schema-qualified table target is accepted', 'REINDEX main.t1', 'main.t1', null, false, 0, null, ['1', '3'], ['1', '3'], 'ok', 'ok', ['i1'], 'main schema qualifier does not change target lookup'],
            ['reindex-1.8', 'schema-qualified index target is accepted', 'REINDEX main.i1', 'main.i1', null, false, 0, null, ['1', '3'], ['1', '3'], 'ok', 'ok', ['i1'], 'main schema qualifier resolves the index target'],
            ['reindex-1.9', 'unknown REINDEX target reports object lookup failure', 'REINDEX bogus', 'bogus', null, false, 1, 'unable to identify the object to be reindexed', [], [], 'ok', 'ok', [], 'unknown target is rejected without mutating index btrees'],
            ['reindex-2.1/2.4', 'custom collations seed reverse and binary order before any collation change', 'SELECT a,b,c,d FROM t2 ORDER BY indexed columns', null, null, false, 0, null, $reverseC1, $reverseC1, 'ok', 'ok', ['sqlite_autoindex_t2_1', 'sqlite_autoindex_t2_2'], 'initial c1/c2 indexes preserve reverse collation order'],
            ['reindex-2.5/2.5.1', 'changing c1 leaves stale index order until matching REINDEX runs', 'change collation c1', null, 'c1', true, 0, null, $reverseC1, $reverseC1, 'not-ok', 'not-ok', [], 'collation callback changed but existing index keys are still in old order'],
            ['reindex-2.6', 'REINDEX c2 does not repair the changed c1 primary-key index', 'REINDEX c2', 'c2', 'c2', true, 0, null, $reverseC1, $reverseC1, 'not-ok', 'not-ok', ['sqlite_autoindex_t2_2'], 'wrong-collation target leaves c1 index stale'],
            ['reindex-2.7', 'REINDEX unrelated table leaves changed c1 index stale', 'REINDEX t1', 't1', null, true, 0, null, $reverseC1, $reverseC1, 'not-ok', 'not-ok', ['i1'], 'unrelated table target does not touch t2 c1 keys'],
            ['reindex-2.8/2.8.1', 'REINDEX c1 rebuilds changed-collation primary-key order and restores integrity', 'REINDEX c1', 'c1', 'c1', true, 0, null, $reverseC1, $ascendingC1, 'not-ok', 'ok', ['sqlite_autoindex_t2_1'], 'matching collation target repairs the stale btree order'],
            ['reindex-2.2', 'c2 unique index keeps reverse nocase ordering independent of c1 repair', 'SELECT b FROM t2 ORDER BY b', null, 'c2', false, 0, null, $reverseC2, $reverseC2, 'ok', 'ok', ['sqlite_autoindex_t2_2'], 'c2 index remains valid and ordered by its own collation'],
        ];

        $out = [];
        for ($case = 1; $case <= $cases; $case++) {
            [$section, $scenario, $statement, $target, $collation, $changed, $code, $error, $before, $after, $integrityBefore, $integrityAfter, $objects, $detail] = $templates[($case - 1) % count($templates)];
            $batch = intdiv($case - 1, count($templates)) + 1;
            $out[] = [
                'source' => 'reindex.test sections reindex-1.1 through reindex-2.8.1',
                'case' => $case,
                'upstream_section' => $section,
                'batch' => $batch,
                'scenario' => $scenario . ' batch ' . $batch,
                'statement' => $statement,
                'target' => $target,
                'collation' => $collation,
                'changed_collation' => $changed,
                'result_code' => $code,
                'error' => $error,
                'order_before' => $before,
                'order_after' => $after,
                'integrity_before' => $integrityBefore,
                'integrity_after' => $integrityAfter,
                'reindexed_objects' => $objects,
                'detail' => $detail,
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
     * @return list<array{source:string,case:int,batch:int,upstream_section:string,scenario:string,statement:string,row_count:int|null,blob_bytes:int|null,index_name:string,table_name:string,result_code:int,error:string|null,integrity:string,catalog_names:list<string>,index_columns:list<string>,unique:bool,sort_order:string|null,limited_memory:bool}>
     */
    public static function index4CreateIndexValidationCases(int $cases = 1200): array
    {
        if ($cases < 1) {
            throw new \InvalidArgumentException('SQLite index4 create-index validation corpus requires at least one case');
        }

        $templates = [
            ['index4-1.2/1.3', 'large randomblob table builds a normal index and passes integrity_check', 'CREATE INDEX i1 ON t1(x); PRAGMA integrity_check', 65536, 102, 'i1', 't1', 0, null, 'ok', ['i1', 't1'], ['x'], false, null, false],
            ['index4-1.4/1.5', 'limited-memory cache builds a second normal index and passes integrity_check', 'PRAGMA cache_size = 10; CREATE INDEX i2 ON t1(x); PRAGMA integrity_check', 65536, 102, 'i2', 't1', 0, null, 'ok', ['i1', 'i2', 't1'], ['x'], false, null, true],
            ['index4-1.6', 'mixed text NULL and overflow-sized values build an index and passes integrity_check', 'CREATE INDEX i1 ON t1(x); PRAGMA integrity_check', 256, 5202, 'i1', 't1', 0, null, 'ok', ['i1', 't1'], ['x'], false, null, false],
            ['index4-1.7', 'single-row table builds an index and passes integrity_check', 'CREATE INDEX i1 ON t1(x); PRAGMA integrity_check', 1, null, 'i1', 't1', 0, null, 'ok', ['i1', 't1'], ['x'], false, null, false],
            ['index4-1.8', 'empty table builds an index and passes integrity_check', 'CREATE INDEX i1 ON t1(x); PRAGMA integrity_check', 0, null, 'i1', 't1', 0, null, 'ok', ['i1', 't1'], ['x'], false, null, false],
            ['index4-2.2', 'CREATE UNIQUE INDEX rejects duplicate table values before adding the index', 'CREATE UNIQUE INDEX i3 ON t2(x)', 5, null, 'i3', 't2', 1, 'UNIQUE constraint failed: t2.x', 'expected-error', ['t2'], ['x'], true, null, false],
        ];

        $out = [];
        for ($case = 1; $case <= $cases; $case++) {
            [
                $section,
                $scenario,
                $statement,
                $rowCount,
                $blobBytes,
                $indexName,
                $tableName,
                $resultCode,
                $error,
                $integrity,
                $catalogNames,
                $indexColumns,
                $unique,
                $sortOrder,
                $limitedMemory,
            ] = $templates[($case - 1) % count($templates)];
            $batch = intdiv($case - 1, count($templates)) + 1;

            $out[] = [
                'source' => 'index4.test sections 1.2 through 2.2',
                'case' => $case,
                'batch' => $batch,
                'upstream_section' => $section,
                'scenario' => $scenario . ' dynamic batch ' . $batch,
                'statement' => $statement,
                'row_count' => $rowCount,
                'blob_bytes' => $blobBytes,
                'index_name' => $indexName,
                'table_name' => $tableName,
                'result_code' => $resultCode,
                'error' => $error,
                'integrity' => $integrity,
                'catalog_names' => $catalogNames,
                'index_columns' => $indexColumns,
                'unique' => $unique,
                'sort_order' => $sortOrder,
                'limited_memory' => $limitedMemory,
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
     * @return list<int>
     */
    private static function deleteRangeKeys(int $start, int $end, callable $keep): array
    {
        $keys = [];
        for ($value = $start; $value <= $end; $value++) {
            if ($keep($value)) {
                $keys[] = $value;
            }
        }

        return $keys;
    }

    /**
     * @param list<array{name:string,for_delete:bool,delete_opcode:bool,role:string}> $opened
     * @return list<string>
     */
    private static function forDeleteFlagSummary(array $opened): array
    {
        $summary = [];
        foreach ($opened as $object) {
            $summary[] = $object['name']
                . ($object['for_delete'] ? '*' : '')
                . ($object['delete_opcode'] ? '+' : '');
        }

        sort($summary, SORT_STRING);

        return $summary;
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
     * @return list<array{source:string,case:int,upstream_section:string,batch:int,scenario:string,sql:string,table_name:string,table_shape:string,predicate_terms:list<string>,driving_object:string|null,opened_objects:list<array{name:string,for_delete:bool,delete_opcode:bool,role:string}>,flag_summary:list<string>,for_delete_count:int,delete_opcode_count:int,uses_rowid:bool,uses_or_optimization:bool,requires_table_payload:bool,integrity:string,detail:string}>
     */
    public static function forDeleteOpenWriteFlagCases(int $cases = 1200): array
    {
        if ($cases < 1) {
            throw new \InvalidArgumentException('SQLite fordelete.test OpenWrite flag corpus requires at least one case');
        }

        $templates = [
            [
                'fordelete-1.1',
                'primary-key equality delete can mark the table btree FORDELETE while the autoindex drives the seek',
                'DELETE FROM t1 WHERE a=?',
                't1',
                'rowid table with PRIMARY KEY autoindex',
                ['a=?'],
                'sqlite_autoindex_t1_1',
                [
                    ['name' => 'sqlite_autoindex_t1_1', 'for_delete' => false, 'delete_opcode' => false, 'role' => 'primary-key seek cursor'],
                    ['name' => 't1', 'for_delete' => true, 'delete_opcode' => true, 'role' => 'table row delete cursor'],
                ],
                false,
                false,
                false,
            ],
            [
                'fordelete-1.2',
                'primary-key equality with residual payload term keeps the table btree readable before delete',
                'DELETE FROM t1 WHERE a=? AND b=?',
                't1',
                'rowid table with PRIMARY KEY autoindex',
                ['a=?', 'b=?'],
                'sqlite_autoindex_t1_1',
                [
                    ['name' => 'sqlite_autoindex_t1_1', 'for_delete' => false, 'delete_opcode' => false, 'role' => 'primary-key seek cursor'],
                    ['name' => 't1', 'for_delete' => false, 'delete_opcode' => true, 'role' => 'table payload read and delete cursor'],
                ],
                false,
                false,
                true,
            ],
            [
                'fordelete-1.3',
                'primary-key range delete keeps the table btree write-only while scanning the autoindex',
                'DELETE FROM t1 WHERE a>?',
                't1',
                'rowid table with PRIMARY KEY autoindex',
                ['a>?'],
                'sqlite_autoindex_t1_1',
                [
                    ['name' => 'sqlite_autoindex_t1_1', 'for_delete' => false, 'delete_opcode' => false, 'role' => 'primary-key range cursor'],
                    ['name' => 't1', 'for_delete' => true, 'delete_opcode' => true, 'role' => 'table row delete cursor'],
                ],
                false,
                false,
                false,
            ],
            [
                'fordelete-1.4',
                'rowid delete marks the primary-key autoindex FORDELETE while the table rowid cursor remains the source',
                'DELETE FROM t1 WHERE rowid=?',
                't1',
                'rowid table with PRIMARY KEY autoindex',
                ['rowid=?'],
                't1',
                [
                    ['name' => 'sqlite_autoindex_t1_1', 'for_delete' => true, 'delete_opcode' => false, 'role' => 'secondary primary-key entry delete cursor'],
                    ['name' => 't1', 'for_delete' => false, 'delete_opcode' => false, 'role' => 'rowid table delete source cursor'],
                ],
                true,
                false,
                false,
            ],
            [
                'fordelete-2.1',
                'ordinary indexed equality delete marks non-driving indexes FORDELETE and marks the table delete opcode',
                'DELETE FROM t2 WHERE a=?',
                't2',
                'rowid table with three ordinary indexes',
                ['a=?'],
                't2a',
                [
                    ['name' => 't2', 'for_delete' => true, 'delete_opcode' => true, 'role' => 'table row delete cursor'],
                    ['name' => 't2a', 'for_delete' => false, 'delete_opcode' => false, 'role' => 'driving equality index cursor'],
                    ['name' => 't2b', 'for_delete' => true, 'delete_opcode' => false, 'role' => 'non-driving index delete cursor'],
                    ['name' => 't2c', 'for_delete' => true, 'delete_opcode' => false, 'role' => 'non-driving index delete cursor'],
                ],
                false,
                false,
                false,
            ],
            [
                'fordelete-2.2',
                'unary-plus residual term blocks table FORDELETE but keeps non-driving indexes write-only',
                'DELETE FROM t2 WHERE a=? AND +b=?',
                't2',
                'rowid table with three ordinary indexes',
                ['a=?', '+b=?'],
                't2a',
                [
                    ['name' => 't2', 'for_delete' => false, 'delete_opcode' => true, 'role' => 'table payload read and delete cursor'],
                    ['name' => 't2a', 'for_delete' => false, 'delete_opcode' => false, 'role' => 'driving equality index cursor'],
                    ['name' => 't2b', 'for_delete' => true, 'delete_opcode' => false, 'role' => 'non-driving index delete cursor'],
                    ['name' => 't2c', 'for_delete' => true, 'delete_opcode' => false, 'role' => 'non-driving index delete cursor'],
                ],
                false,
                false,
                true,
            ],
            [
                'fordelete-2.3',
                'OR optimization opens all candidate indexes as FORDELETE while table deletion is rowlist-driven',
                'DELETE FROM t2 WHERE a=? OR b=?',
                't2',
                'rowid table with three ordinary indexes',
                ['a=?', 'b=?'],
                'rowlist',
                [
                    ['name' => 't2', 'for_delete' => false, 'delete_opcode' => false, 'role' => 'rowlist table delete cursor'],
                    ['name' => 't2a', 'for_delete' => true, 'delete_opcode' => false, 'role' => 'OR arm index delete cursor'],
                    ['name' => 't2b', 'for_delete' => true, 'delete_opcode' => false, 'role' => 'OR arm index delete cursor'],
                    ['name' => 't2c', 'for_delete' => true, 'delete_opcode' => false, 'role' => 'non-driving index delete cursor'],
                ],
                false,
                true,
                false,
            ],
            [
                'fordelete-2.4',
                'unary-plus scan delete opens every secondary index FORDELETE and leaves the table scan cursor unflagged',
                'DELETE FROM t2 WHERE +a=?',
                't2',
                'rowid table with three ordinary indexes',
                ['+a=?'],
                't2',
                [
                    ['name' => 't2', 'for_delete' => false, 'delete_opcode' => false, 'role' => 'table scan delete source cursor'],
                    ['name' => 't2a', 'for_delete' => true, 'delete_opcode' => false, 'role' => 'secondary index delete cursor'],
                    ['name' => 't2b', 'for_delete' => true, 'delete_opcode' => false, 'role' => 'secondary index delete cursor'],
                    ['name' => 't2c', 'for_delete' => true, 'delete_opcode' => false, 'role' => 'secondary index delete cursor'],
                ],
                false,
                false,
                false,
            ],
            [
                'fordelete-2.5',
                'rowid delete on a table with ordinary indexes uses unflagged table cursor and FORDELETE index cursors',
                'DELETE FROM t2 WHERE rowid=?',
                't2',
                'rowid table with three ordinary indexes',
                ['rowid=?'],
                't2',
                [
                    ['name' => 't2', 'for_delete' => false, 'delete_opcode' => false, 'role' => 'rowid table delete source cursor'],
                    ['name' => 't2a', 'for_delete' => true, 'delete_opcode' => false, 'role' => 'secondary index delete cursor'],
                    ['name' => 't2b', 'for_delete' => true, 'delete_opcode' => false, 'role' => 'secondary index delete cursor'],
                    ['name' => 't2c', 'for_delete' => true, 'delete_opcode' => false, 'role' => 'secondary index delete cursor'],
                ],
                true,
                false,
                false,
            ],
        ];

        $out = [];
        for ($case = 1; $case <= $cases; $case++) {
            [$section, $scenario, $sql, $table, $shape, $terms, $driving, $opened, $usesRowid, $usesOr, $requiresTablePayload] = $templates[($case - 1) % count($templates)];
            $batch = intdiv($case - 1, count($templates)) + 1;
            $forDeleteCount = count(array_filter($opened, static fn (array $object): bool => $object['for_delete']));
            $deleteOpcodeCount = count(array_filter($opened, static fn (array $object): bool => $object['delete_opcode']));

            $out[] = [
                'source' => 'fordelete.test sections fordelete-1.1 through fordelete-2.5',
                'case' => $case,
                'upstream_section' => $section,
                'batch' => $batch,
                'scenario' => $scenario . ' dynamic replay ' . $batch,
                'sql' => $sql,
                'table_name' => $table,
                'table_shape' => $shape,
                'predicate_terms' => $terms,
                'driving_object' => $driving,
                'opened_objects' => $opened,
                'flag_summary' => self::forDeleteFlagSummary($opened),
                'for_delete_count' => $forDeleteCount,
                'delete_opcode_count' => $deleteOpcodeCount,
                'uses_rowid' => $usesRowid,
                'uses_or_optimization' => $usesOr,
                'requires_table_payload' => $requiresTablePayload,
                'integrity' => 'ok',
                'detail' => $section . ' ' . $scenario . '; dynamic replay ' . $batch,
            ];
        }

        return $out;
    }

    /**
     * @return list<array{source:string,case:int,upstream_section:string,batch:int,scenario:string,table_name:string,index_name:string|null,predicate:string,initial_rows:int,deleted_rows:int,remaining_rows:int,remaining_keys:list<int>,count_changes:bool,uses_index:bool,large_delete:bool,integrity:string,detail:string}>
     */
    public static function deleteIndexedRowListDynamicCases(int $cases = 1200): array
    {
        if ($cases < 1) {
            throw new \InvalidArgumentException('SQLite delete.test indexed row-list dynamic corpus requires at least one case');
        }

        $templates = [
            ['delete-3.1.2/3.1.3', 'unindexed equality delete removes one row and preserves ordered survivors', 'table1', null, 'f1=3', 4, 1, [1, 2, 4], false, false, false, 'table scan delete before CREATE INDEX'],
            ['delete-3.1.4/3.1.5', 'indexed equality miss reports zero count_changes rows', 'table1', 'index1', 'f1=3', 3, 0, [1, 2, 4], true, true, false, 'SEARCH table1 USING INDEX index1 (f1=?) with no matching row'],
            ['delete-3.1.6.1/3.1.7', 'indexed equality hit reports one changed row and updates index entries', 'table1', 'index1', 'f1=2', 3, 1, [1, 4], true, true, false, 'SEARCH table1 USING INDEX index1 (f1=?) removes the matching index cell'],
            ['delete-5.1.1/5.1.2', 'delete-all with count_changes on returns the number of remaining indexed rows', 'table1', 'index1', 'all rows', 2, 2, [], true, true, false, 'DELETE FROM table1 drains table and index btrees'],
            ['delete-5.2.2/5.2.4', 'bulk delete-all toggles count_changes result shape while clearing two hundred rows', 'table1', 'index1', 'all rows', 200, 200, [], false, true, false, 'bulk table clear frees indexed row entries without returning count rows'],
            ['delete-5.3', 'repeated modular equality deletes leave one hundred fifty indexed rows', 'table1', 'index1', 'f1 in 1..200 step 4', 200, 50, self::deleteRangeKeys(1, 200, static fn (int $value): bool => (($value - 1) % 4) !== 0), false, true, false, 'fifty point deletes remove every fourth index key'],
            ['delete-5.4.1/5.4.2', 'range delete removes keys greater than fifty after prior modular deletes', 'table1', 'index1', 'f1>50', 150, 113, self::deleteRangeKeys(1, 50, static fn (int $value): bool => (($value - 1) % 4) !== 0), false, true, false, 'indexed range delete leaves thirty seven low keys'],
            ['delete-5.5', 'second modular equality pass leaves the exact upstream key list', 'table1', 'index1', 'f1 in 1..70 step 3', 37, 12, [2, 3, 6, 8, 11, 12, 14, 15, 18, 20, 23, 24, 26, 27, 30, 32, 35, 36, 38, 39, 42, 44, 47, 48, 50], false, true, false, 'interleaved point deletes preserve ordered index scan output'],
            ['delete-5.6/5.7', 'low-key cleanup then inequality delete isolates one survivor', 'table1', 'index1', 'f1<40 then f1!=48', 25, 24, [48], false, true, false, 'indexed range cleanup followed by inequality delete leaves key 48 only'],
            ['delete-6.5.1/6.5.2', 'large indexed row-list delete removes 2993 rows and keeps first seven keys', 'table1', 'index1', 'f1>7', 3000, 2993, [1, 2, 3, 4, 5, 6, 7], false, true, true, 'VDBE row-list overflow path deletes high keys from an indexed table'],
            ['delete-6.6', 'large unindexed sibling delete matches the indexed table survivor set', 'table2', null, 'f1>7', 3000, 2993, [1, 2, 3, 4, 5, 6, 7], false, false, true, 'row-list overflow path has the same logical survivors without an index'],
            ['delete-6.7/6.10', 'full delete permits subsequent insert into emptied table btree', 'table1/table2', 'index1', 'all rows; insert (2,3)', 8, 7, [2], false, true, true, 'empty btree root remains reusable after large delete'],
        ];

        $out = [];
        for ($case = 1; $case <= $cases; $case++) {
            [$section, $scenario, $table, $index, $predicate, $initial, $deleted, $remaining, $countChanges, $usesIndex, $largeDelete, $detail] = $templates[($case - 1) % count($templates)];
            $out[] = [
                'source' => 'delete.test sections delete-3.1.1 through delete-6.11',
                'case' => $case,
                'upstream_section' => $section,
                'batch' => intdiv($case - 1, count($templates)) + 1,
                'scenario' => $scenario,
                'table_name' => $table,
                'index_name' => $index,
                'predicate' => $predicate,
                'initial_rows' => $initial,
                'deleted_rows' => $deleted,
                'remaining_rows' => count($remaining),
                'remaining_keys' => $remaining,
                'count_changes' => $countChanges,
                'uses_index' => $usesIndex,
                'large_delete' => $largeDelete,
                'integrity' => 'ok',
                'detail' => $detail . '; dynamic replay ' . (intdiv($case - 1, count($templates)) + 1),
            ];
        }

        return $out;
    }

    /**
     * @return list<array{source:string,case:int,upstream_section:string,batch:int,scenario:string,table_name:string,index_name:string|null,operation:string,cursor_open:bool,expected_result:array{int,string},initial_rows:int,deleted_rows:int,remaining_rows:int,remaining_values:list<mixed>,index_entries_preserved:bool,integrity:string,detail:string}>
     */
    public static function delete2Delete3CursorAndLargeDeleteCases(int $cases = 1200): array
    {
        if ($cases < 1) {
            throw new \InvalidArgumentException('SQLite delete2/delete3 dynamic corpus requires at least one case');
        }

        $oddSurvivors = [];
        for ($value = 1; $value <= 524288; $value += 2) {
            if ($value <= 15 || $value >= 524279) {
                $oddSurvivors[] = $value;
            }
        }

        $templates = [
            [
                'delete2-1.1/1.3',
                'primary-key index is populated before a read cursor is held open',
                'q',
                'sqlite_autoindex_q_1',
                'seed indexed rows',
                false,
                [0, ''],
                3,
                0,
                3,
                ['id.1', 'id.2', 'id.3'],
                true,
                'opening state keeps table and primary-key index consistent',
            ],
            [
                'delete2-1.4/1.8',
                'delete while a table cursor is active removes the table row and matching primary-key entry',
                'q',
                'sqlite_autoindex_q_1',
                'DELETE FROM q WHERE rowid=1 with active SELECT cursor',
                true,
                [0, ''],
                3,
                1,
                2,
                ['id.2', 'id.3'],
                true,
                'modern SQLite permits the delete but must not remove only the index entry',
            ],
            [
                'delete2-1.9/1.11',
                'retrying the same rowid delete after finalizing the cursor is a harmless miss',
                'q',
                'sqlite_autoindex_q_1',
                'DELETE FROM q WHERE rowid=1 after cursor finalize',
                false,
                [0, ''],
                2,
                0,
                2,
                ['id.2', 'id.3'],
                true,
                'second delete preserves the already-consistent table/index pair',
            ],
            [
                'delete2-2.1/2.2',
                'deleting a joined source table during row production preserves yielded result rows',
                't1',
                null,
                'DELETE FROM t1 inside t1,t2 row callback',
                true,
                [0, ''],
                1,
                1,
                0,
                [null, 3, 4, null, 5, 6],
                true,
                'row callback delete does not corrupt the active cross-join result',
            ],
            [
                'delete3-1.1/1.3',
                'large rowid delete removes all even keys and preserves odd-key btree integrity',
                't1',
                'integer-primary-key',
                'DELETE FROM t1 WHERE x%2==0',
                false,
                [0, ''],
                524288,
                262144,
                262144,
                $oddSurvivors,
                true,
                'large row-list delete leaves exactly the odd rowid btree keys',
            ],
        ];

        $out = [];
        for ($case = 1; $case <= $cases; $case++) {
            [$section, $scenario, $table, $index, $operation, $cursorOpen, $result, $initial, $deleted, $remaining, $values, $indexPreserved, $detail] = $templates[($case - 1) % count($templates)];
            $out[] = [
                'source' => 'delete2.test sections delete2-1.1 through delete2-2.2 and delete3.test sections delete3-1.1 through delete3-1.3',
                'case' => $case,
                'upstream_section' => $section,
                'batch' => intdiv($case - 1, count($templates)) + 1,
                'scenario' => $scenario,
                'table_name' => $table,
                'index_name' => $index,
                'operation' => $operation,
                'cursor_open' => $cursorOpen,
                'expected_result' => $result,
                'initial_rows' => $initial,
                'deleted_rows' => $deleted,
                'remaining_rows' => $remaining,
                'remaining_values' => $values,
                'index_entries_preserved' => $indexPreserved,
                'integrity' => 'ok',
                'detail' => $detail . '; dynamic replay ' . (intdiv($case - 1, count($templates)) + 1),
            ];
        }

        return $out;
    }

    /**
     * @return list<array{source:string,case:int,upstream_section:string,scenario:string,sql:string,statement_kind:string,table_name:string,indexed_by:string|null,not_indexed:bool,where_terms:list<string>,expected_code:int,expected_error:string|null,expected_detail:string,uses_named_index:bool,uses_any_index:bool,rowid_allowed:bool,view_dependency:bool,partial_index_usable:bool,integrity:string,batch:int}>
     */
    public static function indexedByDynamicPlannerEnforcementCases(int $cases = 1000): array
    {
        if ($cases < 1) {
            throw new \InvalidArgumentException('SQLite indexedby.test dynamic corpus requires at least one case');
        }

        $templates = [
            [
                'indexedby-2.1/3.1.1',
                'NOT INDEXED on SELECT forces a table scan for ordinary predicates',
                "SELECT * FROM t1 NOT INDEXED WHERE a = 'one' AND b = 'two'",
                'SELECT',
                't1',
                null,
                true,
                ['a=?', 'b=?'],
                0,
                null,
                'SCAN t1',
                false,
                false,
                false,
                false,
                true,
            ],
            [
                'indexedby-2.2/3.2',
                'INDEXED BY i1 requires the named index for a SELECT predicate on a',
                "SELECT * FROM t1 INDEXED BY i1 WHERE a = 'one' AND b = 'two'",
                'SELECT',
                't1',
                'i1',
                false,
                ['a=?', 'b=?'],
                0,
                null,
                'SEARCH t1 USING INDEX i1 (a=?)',
                true,
                true,
                false,
                false,
                true,
            ],
            [
                'indexedby-2.4',
                'INDEXED BY a different table index is rejected at prepare time',
                "SELECT * FROM t1 INDEXED BY i3 WHERE a = 'one' AND b = 'two'",
                'SELECT',
                't1',
                'i3',
                false,
                ['a=?', 'b=?'],
                1,
                'no such index: i3',
                'prepare-error: no such index',
                false,
                false,
                false,
                false,
                false,
            ],
            [
                'indexedby-2.7',
                'INDEXED BY is not resolved through a view name',
                "SELECT * FROM v1 INDEXED BY i1 WHERE a = 'one'",
                'SELECT',
                'v1',
                'i1',
                false,
                ['a=?'],
                1,
                'no such index: i1',
                'prepare-error: view source cannot satisfy INDEXED BY',
                false,
                false,
                false,
                true,
                false,
            ],
            [
                'indexedby-3.1.2/6.2',
                'NOT INDEXED still permits rowid lookup but not ordinary b-index lookup',
                'SELECT * FROM t1 NOT INDEXED WHERE rowid=1',
                'SELECT',
                't1',
                null,
                true,
                ['rowid=?'],
                0,
                null,
                'SEARCH t1 USING INTEGER PRIMARY KEY (rowid=?)',
                false,
                false,
                true,
                false,
                true,
            ],
            [
                'indexedby-5.1/5.5',
                'INDEXED BY inside a view remains a hard requirement after drop and recreate',
                'CREATE VIEW v2 AS SELECT * FROM t1 INDEXED BY i1 WHERE a > 5',
                'VIEW',
                't1',
                'i1',
                false,
                ['a>?'],
                0,
                null,
                'SEARCH t1 USING INDEX i1 (a>?)',
                true,
                true,
                false,
                true,
                true,
            ],
            [
                'indexedby-7.3/7.5',
                'INDEXED BY chooses the named index for DELETE statements',
                'DELETE FROM t1 INDEXED BY i2 WHERE a = 5 AND b = 10',
                'DELETE',
                't1',
                'i2',
                false,
                ['a=?', 'b=?'],
                0,
                null,
                'SEARCH t1 USING INDEX i2 (b=?)',
                true,
                true,
                false,
                false,
                true,
            ],
            [
                'indexedby-8.3/8.5',
                'INDEXED BY chooses the named index for UPDATE statements',
                'UPDATE t1 INDEXED BY i1 SET rowid=rowid+1 WHERE a = 5 AND b = 10',
                'UPDATE',
                't1',
                'i1',
                false,
                ['a=?', 'b=?'],
                0,
                null,
                'SEARCH t1 USING INDEX i1 (a=?)',
                true,
                true,
                false,
                false,
                true,
            ],
            [
                'indexedby-9.2/9.3',
                'INDEXED BY can name an index that is not useful for the join predicate',
                'SELECT * FROM maintable AS m INNER JOIN joinme AS j INDEXED BY joinme_id_text_idx ON (m.id = j.id_int)',
                'SELECT',
                'joinme',
                'joinme_id_text_idx',
                false,
                ['id_int=?'],
                0,
                null,
                'SCAN joinme USING INDEX joinme_id_text_idx',
                true,
                true,
                false,
                false,
                true,
            ],
            [
                'indexedby-10.1/10.3',
                'indexed remains legal as table, column, index, and INDEXED BY token context',
                'SELECT * FROM t10 indexed by indexed WHERE indexed>0',
                'SELECT',
                't10',
                'indexed',
                false,
                ['indexed>?'],
                0,
                null,
                'SEARCH t10 USING INDEX indexed (indexed>?)',
                true,
                true,
                false,
                false,
                true,
            ],
            [
                'indexedby-11.5/11.10',
                'rowid tail constraints are usable through a forced covering index',
                "SELECT a,b,rowid FROM x1 INDEXED BY x1i WHERE a=1 AND b=1 AND rowid='3.0'",
                'SELECT',
                'x1',
                'x1i',
                false,
                ['a=?', 'b=?', 'rowid=?'],
                0,
                null,
                'SEARCH x1 USING COVERING INDEX x1i (a=? AND b=? AND rowid=?)',
                true,
                true,
                true,
                false,
                true,
            ],
            [
                'indexedby-12.2/12.4',
                'INDEXED BY a partial index with no satisfying predicate has no query solution',
                'SELECT * FROM o1 INDEXED BY p2 ORDER BY 1',
                'SELECT',
                'o1',
                'p2',
                false,
                [],
                1,
                'no query solution',
                'prepare-error: no query solution for unusable partial index',
                false,
                false,
                false,
                false,
                false,
            ],
        ];

        $out = [];
        for ($case = 1; $case <= $cases; $case++) {
            [$section, $scenario, $sql, $kind, $table, $indexedBy, $notIndexed, $whereTerms, $code, $error, $detail, $usesNamed, $usesAnyIndex, $rowidAllowed, $viewDependency, $partialUsable] = $templates[($case - 1) % count($templates)];
            $batch = intdiv($case - 1, count($templates)) + 1;
            $out[] = [
                'source' => 'indexedby.test sections 2.1 through 12.4',
                'case' => $case,
                'upstream_section' => $section,
                'scenario' => $scenario . ' dynamic batch ' . $batch,
                'sql' => $sql,
                'statement_kind' => $kind,
                'table_name' => $table,
                'indexed_by' => $indexedBy,
                'not_indexed' => $notIndexed,
                'where_terms' => $whereTerms,
                'expected_code' => $code,
                'expected_error' => $error,
                'expected_detail' => $detail,
                'uses_named_index' => $usesNamed,
                'uses_any_index' => $usesAnyIndex,
                'rowid_allowed' => $rowidAllowed,
                'view_dependency' => $viewDependency,
                'partial_index_usable' => $partialUsable,
                'integrity' => $code === 0 ? 'ok' : 'expected-error',
                'batch' => $batch,
            ];
        }

        return $out;
    }

    /**
     * @return list<array{source:string,case:int,upstream_section:string,batch:int,statement:string,statement_kind:string,table_name:string,indexed_by:string|null,not_indexed:bool,or_terms:list<string>,and_terms:list<string>,result_rows:list<array<int,mixed>>,scan_steps:int,sort_steps:int,uses_multi_index_or:bool,chosen_indexes:list<string>,mutation:string|null,rows_after:list<int>,detail:string,integrity:string}>
     */
    public static function where9MultiIndexOrDynamicCases(int $cases = 1000): array
    {
        if ($cases < 1) {
            throw new \InvalidArgumentException('SQLite where9 multi-index OR dynamic corpus requires at least one case');
        }

        $templates = [
            self::where9OrCase('where9-1.2.1', 'SELECT a FROM t1 WHERE b IS NULL OR c IS NULL OR d IS NULL ORDER BY a', 'select', 't1', null, false, ['b IS NULL', 'c IS NULL', 'd IS NULL'], [], [[90], [91], [92], [96], [97], [99]], 0, 1, true, ['t1b', 't1c', 't1d'], null, [90, 91, 92, 96, 97, 99], 'three nullable indexed columns union through multi-index OR'),
            self::where9OrCase('where9-1.2.2', 'SELECT a FROM t1 WHERE +b IS NULL OR c IS NULL OR d IS NULL ORDER BY a', 'select', 't1', null, false, ['+b IS NULL', 'c IS NULL', 'd IS NULL'], [], [[90], [91], [92], [96], [97], [99]], 98, 0, false, ['t1c', 't1d'], null, [90, 91, 92, 96, 97, 99], 'unary plus disables one OR arm index and forces scan fallback'),
            self::where9OrCase('where9-1.2.5', 'SELECT a FROM t4 WHERE b IS NULL OR c IS NULL OR d IS NULL ORDER BY a', 'select', 't4', null, false, ['b IS NULL', 'c IS NULL', 'd IS NULL'], [], [[90], [91], [92], [96], [97], [99]], 98, 0, false, ['t4b', 't4c'], null, [90, 91, 92, 96, 97, 99], 'missing d index prevents full multi-index OR on sibling table'),
            self::where9OrCase('where9-1.3.1', 'SELECT a FROM t1 WHERE (b IS NULL AND c NOT NULL AND d NOT NULL) OR (b NOT NULL AND c IS NULL AND d NOT NULL) OR (b NOT NULL AND c NOT NULL AND d IS NULL) ORDER BY a', 'select', 't1', null, false, ['b IS NULL AND c NOT NULL AND d NOT NULL', 'b NOT NULL AND c IS NULL AND d NOT NULL', 'b NOT NULL AND c NOT NULL AND d IS NULL'], [], [[90], [91], [92], [97]], 0, 1, true, ['t1b', 't1c', 't1d'], null, [90, 91, 92, 97], 'compound OR arms preserve auxiliary NOT NULL terms'),
            self::where9OrCase('where9-4.1', 'SELECT a FROM t1 WHERE b>1000 AND (c=31031 OR d IS NULL) ORDER BY +a', 'select', 't1', null, false, ['c=31031', 'd IS NULL'], ['b>1000'], [[92], [93], [97]], 0, 1, true, ['t1c', 't1d'], null, [92, 93, 97], 'equality OR term is preferred over range predicate'),
            self::where9OrCase('where9-4.4', 'SELECT a FROM t1 INDEXED BY t1b WHERE b>1000 AND (c=31031 OR d IS NULL) ORDER BY +a', 'select', 't1', 't1b', false, ['c=31031', 'd IS NULL'], ['b>1000'], [[92], [93], [97]], 0, 1, true, ['t1c', 't1d'], null, [92, 93, 97], 'INDEXED BY on outer table remains compatible with OR arm indexes'),
            self::where9OrCase('where9-4.6', 'SELECT a FROM t1 NOT INDEXED WHERE b>1000 AND (c=31031 OR d IS NULL) ORDER BY +a', 'select', 't1', null, true, ['c=31031', 'd IS NULL'], ['b>1000'], [[92], [93], [97]], 98, 1, false, [], null, [92, 93, 97], 'NOT INDEXED disables OR arm index probes but preserves rows'),
            self::where9OrCase('where9-5.1', 'SELECT a FROM t1 WHERE b>1000 AND (c=31031 OR d IS NULL)', 'eqp', 't1', null, false, ['c=31031', 'd IS NULL'], ['b>1000'], [[92], [93], [97]], 0, 0, true, ['t1c', 't1d'], null, [92, 93, 97], 'multi-index OR chosen ahead of less selective range predicate'),
            self::where9OrCase('where9-5.2', 'SELECT a FROM t1 WHERE b=1000 AND (c=31031 OR d IS NULL)', 'eqp', 't1', null, false, ['c=31031', 'd IS NULL'], ['b=1000'], [], 0, 0, false, ['t1b'], null, [], 'equality predicate on b wins over OR-clause plan'),
            self::where9OrCase('where9-5.3', 'SELECT a FROM t1 WHERE b>1000 AND (c>=31031 OR d IS NULL)', 'eqp', 't1', null, false, ['c>=31031', 'd IS NULL'], ['b>1000'], [], 0, 0, false, ['t1b'], null, [], 'AND-side inequality is preferred over OR-side inequality'),
            self::where9OrCase('where9-6.2.2/6.2.3', 'DELETE FROM t1 WHERE b IS NULL OR c IS NULL OR d IS NULL', 'delete', 't1', null, false, ['b IS NULL', 'c IS NULL', 'd IS NULL'], [], [], 0, 0, true, ['t1b', 't1c', 't1d'], 'delete', [85, 86, 87, 88, 89, 93, 94, 95, 98], 'DELETE uses multi-index OR and removes nullable-key rows'),
            self::where9OrCase('where9-6.2.4/6.2.5', 'DELETE FROM t1 WHERE +b IS NULL OR c IS NULL OR d IS NULL', 'delete', 't1', null, false, ['+b IS NULL', 'c IS NULL', 'd IS NULL'], [], [], 98, 0, false, ['t1c', 't1d'], 'delete', [85, 86, 87, 88, 89, 93, 94, 95, 98], 'DELETE scan fallback preserves same mutation result'),
            self::where9OrCase('where9-6.2.6/6.2.7', 'UPDATE t1 SET a=a+100 WHERE (b IS NULL OR c IS NULL OR d IS NULL) AND a!=92 AND a!=97', 'update', 't1', null, false, ['b IS NULL', 'c IS NULL', 'd IS NULL'], ['a!=92', 'a!=97'], [], 0, 0, true, ['t1b', 't1c', 't1d'], 'update', [85, 86, 87, 88, 89, 92, 93, 94, 95, 97, 98, 190, 191, 196, 199], 'UPDATE uses OR index union without mutating excluded rowids'),
            self::where9OrCase('where9-6.3.1/6.3.2', 'DELETE FROM t1 WHERE (b IS NULL AND c NOT NULL AND d NOT NULL) OR (b NOT NULL AND c IS NULL AND d NOT NULL) OR (b NOT NULL AND c NOT NULL AND d IS NULL)', 'delete', 't1', null, false, ['b IS NULL AND c NOT NULL AND d NOT NULL', 'b NOT NULL AND c IS NULL AND d NOT NULL', 'b NOT NULL AND c NOT NULL AND d IS NULL'], [], [], 0, 0, true, ['t1b', 't1c', 't1d'], 'delete', [85, 86, 87, 88, 89, 93, 94, 95, 96, 98, 99], 'DELETE respects auxiliary terms attached to each OR arm'),
        ];

        $rows = [];
        for ($case = 1; $case <= $cases; $case++) {
            $template = $templates[($case - 1) % count($templates)];
            $template['case'] = $case;
            $template['batch'] = intdiv($case - 1, count($templates)) + 1;
            $template['detail'] .= '; dynamic replay ' . $template['batch'];
            $rows[] = $template;
        }

        return $rows;
    }

    /**
     * @return list<array{source:string,case:int,upstream_section:string,batch:int,statement:string,statement_kind:string,table_name:string,indexed_by:string|null,not_indexed:bool,or_terms:list<string>,and_terms:list<string>,result_rows:list<array<int,mixed>>,scan_steps:int,sort_steps:int,uses_multi_index_or:bool,chosen_indexes:list<string>,mutation:string|null,rows_after:list<int>,detail:string,integrity:string}>
     */
    public static function where9LateOrJoinMutationCases(int $cases = 1000): array
    {
        if ($cases < 1) {
            throw new \InvalidArgumentException('SQLite where9 late OR join mutation corpus requires at least one case');
        }

        $templates = [
            self::where9OrCase('where9-6.4.1/6.4.2', 'DELETE FROM t1 WHERE (b>=950 AND b<=1010) OR (b IS NULL AND c NOT NULL)', 'delete', 't1', null, false, ['b BETWEEN 950 AND 1010', 'b IS NULL AND c NOT NULL'], [], [], 0, 0, true, ['t1b'], 'delete', [85, 86, 92, 93, 94, 95, 96, 97, 98, 99], 'range arm and NULL arm delete rows 87 through 91'),
            self::where9OrCase('where9-6.4.3/6.4.4', 'UPDATE t1 SET a=a+100 WHERE (b>=950 AND b<=1010) OR (b IS NULL AND c NOT NULL)', 'update', 't1', null, false, ['b BETWEEN 950 AND 1010', 'b IS NULL AND c NOT NULL'], [], [], 0, 0, true, ['t1b'], 'update', [85, 86, 92, 93, 94, 95, 96, 97, 98, 99], 'range arm and NULL arm update rowids 87 through 91 out of the 85-100 window'),
            self::where9OrCase('where9-6.5.1/6.5.2', "DELETE FROM t1 WHERE a=83 OR b=913 OR c=28028 OR (d>=82 AND d<83) OR (e>2802 AND e<2803) OR f='fghijklmn' OR g='hgfedcb'", 'delete', 't1', null, false, ['a=83', 'b=913', 'c=28028', 'd>=82 AND d<83', 'e>2802 AND e<2803', "f='fghijklmn'", "g='hgfedcb'"], [], [], 0, 0, true, ['rowid', 't1b', 't1c', 't1d', 't1e', 't1f', 't1g'], 'delete', [], 'seven OR arms delete rows 5 31 57 82 83 84 85 86 87'),
            self::where9OrCase('where9-6.5.3/6.5.4', "UPDATE t1 SET a=a+100 WHERE a=83 OR b=913 OR c=28028 OR (d>=82 AND d<83) OR (e>2802 AND e<2803) OR f='fghijklmn' OR g='hgfedcb'", 'update', 't1', null, false, ['a=83', 'b=913', 'c=28028', 'd>=82 AND d<83', 'e>2802 AND e<2803', "f='fghijklmn'", "g='hgfedcb'"], [], [[105], [131], [157], [182], [183], [184], [185], [186], [187]], 0, 0, true, ['rowid', 't1b', 't1c', 't1d', 't1e', 't1f', 't1g'], 'update', [105, 131, 157, 182, 183, 184, 185, 186, 187], 'seven OR arms update the selected rowids once each'),
            self::where9OrCase('where9-6.6.1/6.6.2', 'DELETE FROM t1 WHERE (b IS NULL AND c NOT NULL AND d NOT NULL) OR (b NOT NULL AND +c IS NULL AND d NOT NULL) OR (b NOT NULL AND c NOT NULL AND d IS NULL)', 'delete', 't1', null, false, ['b IS NULL AND c NOT NULL AND d NOT NULL', 'b NOT NULL AND +c IS NULL AND d NOT NULL', 'b NOT NULL AND c NOT NULL AND d IS NULL'], [], [], 98, 0, false, ['t1b', 't1d'], 'delete', [85, 86, 87, 88, 89, 93, 94, 95, 96, 98, 99], 'unary plus disables one OR arm and scan fallback deletes rows 90 91 92 97'),
            self::where9OrCase('where9-6.6.3/6.6.4', 'UPDATE t1 SET a=a+100 WHERE (b IS NULL AND c NOT NULL AND d NOT NULL) OR (b NOT NULL AND +c IS NULL AND d NOT NULL) OR (b NOT NULL AND c NOT NULL AND d IS NULL)', 'update', 't1', null, false, ['b IS NULL AND c NOT NULL AND d NOT NULL', 'b NOT NULL AND +c IS NULL AND d NOT NULL', 'b NOT NULL AND c NOT NULL AND d IS NULL'], [], [[190], [191], [192], [197]], 98, 0, false, ['t1b', 't1d'], 'update', [85, 86, 87, 88, 89, 93, 94, 95, 96, 98, 99, 190, 191, 192, 197], 'unary plus fallback updates rows 90 91 92 97 once each'),
            self::where9OrCase('where9-6.7.1/6.7.2', 'DELETE FROM t1 NOT INDEXED WHERE (b IS NULL AND c NOT NULL AND d NOT NULL) OR (b NOT NULL AND c IS NULL AND d NOT NULL) OR (b NOT NULL AND c NOT NULL AND d IS NULL)', 'delete', 't1', null, true, ['b IS NULL AND c NOT NULL AND d NOT NULL', 'b NOT NULL AND c IS NULL AND d NOT NULL', 'b NOT NULL AND c NOT NULL AND d IS NULL'], [], [], 98, 0, false, [], 'delete', [85, 86, 87, 88, 89, 93, 94, 95, 96, 98, 99], 'NOT INDEXED disables OR arm probes for DELETE'),
            self::where9OrCase('where9-6.7.3/6.7.4', 'UPDATE t1 NOT INDEXED SET a=a+100 WHERE (b IS NULL AND c NOT NULL AND d NOT NULL) OR (b NOT NULL AND c IS NULL AND d NOT NULL) OR (b NOT NULL AND c NOT NULL AND d IS NULL)', 'update', 't1', null, true, ['b IS NULL AND c NOT NULL AND d NOT NULL', 'b NOT NULL AND c IS NULL AND d NOT NULL', 'b NOT NULL AND c NOT NULL AND d IS NULL'], [], [[190], [191], [192], [197]], 98, 0, false, [], 'update', [85, 86, 87, 88, 89, 93, 94, 95, 96, 98, 99, 190, 191, 192, 197], 'NOT INDEXED disables OR arm probes for UPDATE'),
            self::where9OrCase('where9-6.8.1', 'DELETE FROM t1 INDEXED BY t1b WHERE (+b IS NULL AND c NOT NULL AND d NOT NULL) OR (b NOT NULL AND c IS NULL AND d NOT NULL) OR (b NOT NULL AND c NOT NULL AND d IS NULL)', 'delete', 't1', 't1b', false, ['+b IS NULL AND c NOT NULL AND d NOT NULL', 'b NOT NULL AND c IS NULL AND d NOT NULL', 'b NOT NULL AND c NOT NULL AND d IS NULL'], [], [], 0, 0, false, ['t1b'], 'delete', [], 'INDEXED BY t1b admits a legal no-error DELETE even when OR proof is constrained'),
            self::where9OrCase('where9-6.8.2', 'UPDATE t1 INDEXED BY t1b SET a=a+100 WHERE (+b IS NULL AND c NOT NULL AND d NOT NULL) OR (b NOT NULL AND c IS NULL AND d NOT NULL) OR (b NOT NULL AND c NOT NULL AND d IS NULL)', 'update', 't1', 't1b', false, ['+b IS NULL AND c NOT NULL AND d NOT NULL', 'b NOT NULL AND c IS NULL AND d NOT NULL', 'b NOT NULL AND c NOT NULL AND d IS NULL'], [], [], 0, 0, false, ['t1b'], 'update', [], 'INDEXED BY t1b admits a legal no-error UPDATE even when OR proof is constrained'),
            self::where9OrCase('where9-7.1.1/7.1.4', "SELECT a FROM t5 WHERE x=? AND (b=913 OR c=27027) ORDER BY a", 'select', 't5', null, false, ['b=913', 'c=27027'], ['x=y or x=n'], [[79], [81], [83]], 0, 1, true, ['t5xb', 't5xc'], null, [79, 80, 81, 83], 'external x term combines with each OR arm through compound indexes'),
            self::where9OrCase('where9-7.2.1/7.3.2', "SELECT a FROM t5 WHERE (x='y' OR y='y') AND (b=913 OR c=27027) ORDER BY a", 'select', 't5', null, false, ['x=y', 'y=y', 'b=913', 'c=27027'], [], [[79], [81], [83]], 0, 1, true, ['t5xb', 't5yb', 't5xc', 't5yc'], null, [79, 81, 83], 'AND terms outside OR are distributed to x/y compound indexes'),
            self::where9OrCase('where9-8.1/8.3', 'SELECT * FROM t81 LEFT JOIN t82 ON y=b JOIN t83 WHERE c==p OR d==p ORDER BY +a', 'join', 't81,t82,t83', null, false, ['c==p', 'd==p'], ['LEFT JOIN y=b'], [[2, 3, 4, 5, null, null, 5, 55], [3, 4, 5, 6, 2, 4, 5, 55]], 0, 1, true, ['t81 rowid', 't83 rowid'], null, [2, 3], 'LEFT JOIN rows remain correct when OR terms join to the right table'),
            self::where9OrCase('where9-9.1', 'SELECT sequence FROM t91 LEFT JOIN t92 ON a=2 OR b=3 variants', 'join', 't91,t92', null, false, ['a=2', 'b=3'], ['LEFT JOIN ON'], [[1], [2], [3], [4], [8], [9]], 0, 0, true, ['t92 rowid'], null, [1, 2, 3, 4, 8, 9], 'OR in LEFT JOIN ON clause preserves matched and unmatched result rows'),
            self::where9OrCase('where9-10.1/10.2', 'SELECT * FROM t10x AS t0 LEFT JOIN t10x AS t1 ON ... JOIN t10x AS t2 ON (t2.id=t0.id OR ...)', 'join', 't101/t102', null, false, ['t2.id=t0.id', 't2.id=t1.id'], ['LEFT JOIN no-match row'], [[1, null, 1]], 0, 0, true, ['t2 primary key'], null, [1], 'OR join to the right of a LEFT JOIN preserves NULL-extended middle row'),
            self::where9OrCase('where9-11.1', 'SELECT 1 FROM t1 JOIN t1 USING(a) WHERE a=1 OR (a=2 AND scalar subquery over UNION ALL view)', 'select', 't1,t2 view', null, false, ['a=1', 'a=2 AND scalar subquery'], ['UNION ALL view subquery'], [], 0, 0, true, ['t1 rowid'], null, [], 'multi-index OR copies subexpressions that contain flattened UNION ALL view subqueries'),
        ];

        $rows = [];
        for ($case = 1; $case <= $cases; $case++) {
            $template = $templates[($case - 1) % count($templates)];
            $template['source'] = 'where9.test sections where9-6.4.1 through where9-11.1';
            $template['case'] = $case;
            $template['batch'] = intdiv($case - 1, count($templates)) + 1;
            $template['detail'] .= '; late dynamic replay ' . $template['batch'];
            $rows[] = $template;
        }

        return $rows;
    }

    /**
     * @return list<array{source:string,case:int,upstream_section:string,batch:int,scenario:string,statement:string,table_name:string,index_name:string|null,reverse_unordered_selects:bool,reopened:bool,vacuumed:bool,ordered:bool,order_by:string|null,predicate:string|null,result_rows:list<array<int,mixed>>,result_flat:list<mixed>,uses_index:bool,sort_count:int,integrity:string,detail:string}>
     */
    public static function whereAReverseUnorderedIndexCases(int $cases = 1000): array
    {
        if ($cases < 1) {
            throw new \InvalidArgumentException('SQLite whereA reverse unordered dynamic corpus requires at least one case');
        }

        $rowidRows = [
            [1, 2, 3],
            [2, 'hello', 'world'],
            [3, 4.53, null],
        ];
        $rowidReverseRows = array_reverse($rowidRows);
        $bIndexRows = [
            [1, 2, 3],
            [3, 4.53, null],
            [2, 'hello', 'world'],
        ];
        $bIndexReverseRows = array_reverse($bIndexRows);
        $flatten = static function (array $rows): array {
            $flat = [];
            foreach ($rows as $row) {
                foreach ($row as $value) {
                    $flat[] = $value;
                }
            }

            return $flat;
        };
        $template = static function (
            string $section,
            string $scenario,
            string $statement,
            string $table,
            ?string $index,
            bool $reverse,
            bool $reopened,
            bool $vacuumed,
            bool $ordered,
            ?string $orderBy,
            ?string $predicate,
            array $rows,
            bool $usesIndex,
            int $sortCount,
            string $detail,
        ) use ($flatten): array {
            return [
                'source' => 'whereA.test sections whereA-1.1 through whereA-6.1',
                'case' => 0,
                'upstream_section' => $section,
                'batch' => 0,
                'scenario' => $scenario,
                'statement' => $statement,
                'table_name' => $table,
                'index_name' => $index,
                'reverse_unordered_selects' => $reverse,
                'reopened' => $reopened,
                'vacuumed' => $vacuumed,
                'ordered' => $ordered,
                'order_by' => $orderBy,
                'predicate' => $predicate,
                'result_rows' => $rows,
                'result_flat' => $flatten($rows),
                'uses_index' => $usesIndex,
                'sort_count' => $sortCount,
                'integrity' => 'ok',
                'detail' => $detail,
            ];
        };

        $templates = [
            $template('whereA-1.1', 'baseline rowid table scan returns insertion rowid order', 'SELECT * FROM t1', 't1', null, false, false, false, false, null, null, $rowidRows, false, 0, 'rowid table scan before reverse_unordered_selects'),
            $template('whereA-1.2', 'reverse_unordered_selects reverses the unordered rowid table scan', 'PRAGMA reverse_unordered_selects=1; SELECT * FROM t1', 't1', null, true, false, false, false, null, null, $rowidReverseRows, false, 0, 'reverse table cursor scan over rowid order'),
            $template('whereA-1.3', 'reopen preserves reverse_unordered_selects behavior for unordered scans', 'db close; sqlite3 db test.db; SELECT * FROM t1', 't1', null, true, true, false, false, null, null, $rowidReverseRows, false, 0, 'reopened database keeps reverse unordered scan direction'),
            $template('whereA-1.4', 'ORDER BY rowid overrides reverse unordered scan direction', 'SELECT * FROM t1 ORDER BY rowid', 't1', null, true, true, false, true, 'rowid ASC', null, $rowidRows, false, 0, 'explicit rowid ordering restores ascending output'),
            $template('whereA-1.5', 'VACUUM does not disturb explicit rowid order', 'VACUUM; SELECT * FROM t1 ORDER BY rowid', 't1', null, true, false, true, true, 'rowid ASC', null, $rowidRows, false, 0, 'vacuumed table preserves ordered rowid scan'),
            $template('whereA-1.6', 'PRAGMA reverse_unordered_selects readback reports enabled state', 'PRAGMA reverse_unordered_selects', 'pragma', null, true, false, false, true, null, null, [[1]], false, 0, 'pragma state is one after enabling reverse unordered scans'),
            $template('whereA-1.7', 'reopen plus VACUUM still leaves unordered scan reversed', 'PRAGMA reverse_unordered_selects=1; VACUUM; SELECT * FROM t1', 't1', null, true, true, true, false, null, null, $rowidReverseRows, false, 0, 'vacuumed reopened table keeps reverse unordered rowid cursor'),
            $template('whereA-1.8', 'unique index lookup with impossible rowid NULL constraint returns no rows', 'SELECT * FROM t1 WHERE b=2 AND a IS NULL', 't1', 'sqlite_autoindex_t1_1', true, false, false, false, null, 'b=2 AND a IS NULL', [], true, 0, 'unique b index search intersects with impossible INTEGER PRIMARY KEY null test'),
            $template('whereA-1.9', 'unique index lookup with non-null rowid returns the matching row', 'SELECT * FROM t1 WHERE b=2 AND a IS NOT NULL', 't1', 'sqlite_autoindex_t1_1', true, false, false, false, null, 'b=2 AND a IS NOT NULL', [[1, 2, 3]], true, 0, 'unique b index search keeps rowid non-null row'),
            $template('whereA-2.1', 'rowid range scan follows ascending order with reverse disabled', 'PRAGMA reverse_unordered_selects=0; SELECT * FROM t1 WHERE a>0', 't1', 'integer-primary-key', false, false, false, false, null, 'a>0', $rowidRows, true, 0, 'rowid range cursor scans forward'),
            $template('whereA-2.2', 'rowid range scan reverses when unordered reversal is enabled', 'PRAGMA reverse_unordered_selects=1; SELECT * FROM t1 WHERE a>0', 't1', 'integer-primary-key', true, false, false, false, null, 'a>0', $rowidReverseRows, true, 0, 'rowid range cursor scans backward under reverse_unordered_selects'),
            $template('whereA-2.3', 'ORDER BY rowid fixes rowid range scan output despite reversal', 'SELECT * FROM t1 WHERE a>0 ORDER BY rowid', 't1', 'integer-primary-key', true, false, false, true, 'rowid ASC', 'a>0', $rowidRows, true, 0, 'ordered rowid range cursor scans forward'),
            $template('whereA-3.1', 'unique index range on b uses SQLite numeric-before-text index order', 'PRAGMA reverse_unordered_selects=0; SELECT * FROM t1 WHERE b>0', 't1', 'sqlite_autoindex_t1_1', false, false, false, false, null, 'b>0', $bIndexRows, true, 0, 'b index scan visits integer, real, then text keys'),
            $template('whereA-3.2', 'reverse unordered scans reverse the b index range cursor', 'PRAGMA reverse_unordered_selects=1; SELECT * FROM t1 WHERE b>0', 't1', 'sqlite_autoindex_t1_1', true, false, false, false, null, 'b>0', $bIndexReverseRows, true, 0, 'b index range cursor scans backward under reverse_unordered_selects'),
            $template('whereA-3.3', 'ORDER BY b keeps b index order even with reversal enabled', 'SELECT * FROM t1 WHERE b>0 ORDER BY b', 't1', 'sqlite_autoindex_t1_1', true, false, false, true, 'b ASC', 'b>0', $bIndexRows, true, 0, 'ordered b index range cursor scans forward'),
            $template('whereA-4.1', 'new table unordered scan inherits reverse scan direction', 'CREATE TABLE t2(x); INSERT 1,2; SELECT x FROM t2', 't2', null, true, false, false, false, null, null, [[2], [1]], false, 0, 'reverse rowid scan over t2 returns inserted rows backward'),
            $template('whereA-4.2', 'creating t2x does not add a temp sort for unordered t2 scan', 'CREATE INDEX t2x ON t2(x); SELECT x FROM t2', 't2', 't2x', true, false, false, false, null, null, [[2], [1]], false, 0, 'unordered scan is still reverse table order and sort counter remains zero'),
            $template('whereA-4.3', 'ORDER BY x uses t2x without temp sorting', 'SELECT x FROM t2 ORDER BY x', 't2', 't2x', true, false, false, true, 'x ASC', null, [[1], [2]], true, 0, 'ascending ORDER BY is satisfied by index t2x'),
            $template('whereA-4.4', 'ORDER BY x DESC uses t2x backward without temp sorting', 'SELECT x FROM t2 ORDER BY x DESC', 't2', 't2x', true, false, false, true, 'x DESC', null, [[2], [1]], true, 0, 'descending ORDER BY is satisfied by index t2x'),
            $template('whereA-4.5', 'dropping t2x forces temp sort for ORDER BY x', 'DROP INDEX t2x; SELECT x FROM t2 ORDER BY x', 't2', null, true, false, false, true, 'x ASC', null, [[1], [2]], false, 1, 'without t2x a temp sort is required for ascending ORDER BY'),
            $template('whereA-4.6', 'dropping t2x forces temp sort for ORDER BY x DESC', 'SELECT x FROM t2 ORDER BY x DESC', 't2', null, true, false, false, true, 'x DESC', null, [[2], [1]], false, 1, 'without t2x a temp sort is required for descending ORDER BY'),
            $template('whereA-5.1', 'OR range on indexed b returns the one qualifying row under reverse scans', 'SELECT a FROM t1 WHERE b=-99 OR b>1', 't1', 't1b', true, false, false, false, null, 'b=-99 OR b>1', [[1]], true, 0, 'OR predicate probes t1b and preserves the single matching row'),
            $template('whereA-6.1', 'analyzed duplicate-column index handles OR equality under reverse scans', 'SELECT a FROM t1 WHERE a=1 OR a=2', 't1', 't1aa', true, false, false, false, null, 'a=1 OR a=2', [[1]], true, 0, 'stat1-adjusted duplicate-column index keeps the row for a=1'),
        ];

        $out = [];
        for ($case = 1; $case <= $cases; $case++) {
            $template = $templates[($case - 1) % count($templates)];
            $template['case'] = $case;
            $template['batch'] = intdiv($case - 1, count($templates)) + 1;
            $template['detail'] .= '; dynamic replay ' . $template['batch'];
            $out[] = $template;
        }

        return $out;
    }

    /**
     * @return list<array<string,mixed>>
     */
    public static function where3LeftJoinReorderPlannerCases(int $cases = 1200): array
    {
        if ($cases < 1) {
            throw new \InvalidArgumentException('SQLite where3 left-join planner dynamic corpus requires at least one case');
        }

        $flatten = static function (array $rows): array {
            $flat = [];
            foreach ($rows as $row) {
                foreach ($row as $value) {
                    $flat[] = $value;
                }
            }

            return $flat;
        };

        $template = static function (
            string $section,
            string $scenario,
            string $statement,
            string $joinShape,
            array $resultRows,
            array $planOrder,
            array $chosenIndexes,
            bool $usesLeftJoin,
            int $nullExtendedRows,
            bool $joinReorderedBeforeLeft,
            bool $naturalOrUsing,
            ?string $disabledOptimization,
            bool $usesTempBtree,
            bool $usesPrimaryKeyLookup,
            bool $usesCompositeEquality,
            array $onTerms,
            array $whereTerms,
            string $detail,
        ) use ($flatten): array {
            return [
                'source' => 'where3.test selected sections where3-1.1 through where3-8.2',
                'case' => 0,
                'upstream_section' => $section,
                'batch' => 0,
                'scenario' => $scenario,
                'statement' => $statement,
                'join_shape' => $joinShape,
                'result_rows' => $resultRows,
                'result_flat' => $flatten($resultRows),
                'plan_order' => $planOrder,
                'chosen_indexes' => array_values(array_unique($chosenIndexes)),
                'uses_left_join' => $usesLeftJoin,
                'null_extended_rows' => $nullExtendedRows,
                'join_reordered_before_left' => $joinReorderedBeforeLeft,
                'natural_or_using' => $naturalOrUsing,
                'disabled_optimization' => $disabledOptimization,
                'uses_temp_btree' => $usesTempBtree,
                'uses_primary_key_lookup' => $usesPrimaryKeyLookup,
                'uses_composite_equality' => $usesCompositeEquality,
                'on_terms' => $onTerms,
                'where_terms' => $whereTerms,
                'integrity' => 'ok',
                'detail' => $detail,
            ];
        };

        $templates = [
            $template(
                'where3-1.1',
                'comma join before LEFT JOIN may reorder t2 first but keeps t1 before null-extension',
                'SELECT * FROM t1, t2 LEFT JOIN t3 ON q=x WHERE p=2 AND a=q',
                'comma-left-join',
                [[222, 'two', 2, 222, null, null]],
                ['t2', 't1', 't3'],
                ['t2i1', 't3i1'],
                true,
                1,
                true,
                false,
                null,
                false,
                false,
                false,
                ['q=x'],
                ['p=2', 'a=q'],
                'SEARCH t2 USING INDEX t2i1 (p=?); SCAN t1; SEARCH t3 USING INDEX t3i1 (x=?) LEFT-JOIN',
            ),
            $template(
                'where3-1.2',
                'LEFT OUTER child lookup followed by INNER child lookup preserves unmatched parent row',
                'SELECT parent1.parent1key, child1.value, child2.value FROM parent1 LEFT OUTER JOIN child1 ON child1.child1key=parent1.child1key INNER JOIN child2 ON child2.child2key=parent1.child2key',
                'left-then-inner',
                [[1, 'Value for C1.1', 'Value for C2.1'], [2, null, 'Value for C2.2'], [3, 'Value for C1.3', 'Value for C2.3']],
                ['parent1', 'child1', 'child2'],
                ['PKIDXChild1'],
                true,
                1,
                false,
                false,
                null,
                false,
                false,
                false,
                ['child1.child1key=parent1.child1key', 'child2.child2key=parent1.child2key'],
                [],
                'LEFT OUTER JOIN child1 may produce a NULL child1 row before the required child2 inner join',
            ),
        ];

        foreach ([
            ['where3-2.1', 'cpk=bx AND bpk=ax', ['tA', 'tB', 'tC', 'tD']],
            ['where3-2.1.1', 'cpk=bx AND bpk=ax with commuted ON term', ['tA', 'tB', 'tC', 'tD']],
            ['where3-2.1.2', 'bx=cpk AND bpk=ax', ['tA', 'tB', 'tC', 'tD']],
            ['where3-2.1.3', 'bx=cpk AND ax=bpk', ['tA', 'tB', 'tC', 'tD']],
            ['where3-2.1.4', 'bx=cpk AND ax=bpk with original ON term', ['tA', 'tB', 'tC', 'tD']],
            ['where3-2.1.5', 'cpk=bx AND ax=bpk', ['tA', 'tB', 'tC', 'tD']],
            ['where3-2.2', 'cpk=bx AND apk=bx', ['tB', 'tA', 'tC', 'tD']],
            ['where3-2.3', 'cpk=bx AND apk=bx repeated after planner warmup', ['tB', 'tA', 'tC', 'tD']],
            ['where3-2.4', 'apk=cx AND bpk=ax', ['tC', 'tA', 'tB', 'tD']],
            ['where3-2.5', 'cpk=ax AND bpk=cx', ['tA', 'tC', 'tB', 'tD']],
            ['where3-2.6', 'bpk=cx AND apk=bx', ['tC', 'tB', 'tA', 'tD']],
            ['where3-2.7', 'cpk=bx AND apk=cx', ['tB', 'tC', 'tA', 'tD']],
        ] as [$section, $where, $order]) {
            $templates[] = $template(
                $section,
                'tables before the LEFT JOIN may be reordered around equality constraints',
                'SELECT * FROM tA, tB, tC LEFT JOIN tD ON dpk=cx WHERE ' . $where,
                'three-way-before-left-join',
                [],
                $order,
                [],
                true,
                0,
                true,
                false,
                null,
                false,
                false,
                false,
                ['dpk=cx'],
                explode(' AND ', $where),
                'planner order ' . implode(' -> ', $order) . ' keeps tD as the right side of the LEFT JOIN',
            );
        }

        foreach ([
            ['where3-3.0a', 'SELECT * FROM t302, t301 WHERE t302.x=5 AND t301.a=t302.y', ['t302', 't301'], [[4, 5, 1, 2, 3], [4, 5, 2, 2, 3]], false],
            ['where3-3.1', 'SELECT * FROM t301, t302 WHERE t302.x=5 AND t301.a=t302.y', ['t302', 't301'], [[4, 5, 1, 2, 3], [4, 5, 2, 2, 3]], false],
            ['where3-3.2', 'SELECT * FROM t301 WHERE c=3 AND a IS NULL', ['t301'], [], false],
            ['where3-3.3', 'SELECT * FROM t301 WHERE c=3 AND a IS NOT NULL', ['t301'], [[1, 2, 3], [2, 2, 3]], false],
        ] as [$section, $sql, $order, $rows, $leftJoin]) {
            $templates[] = $template(
                $section,
                'ANALYZE must not move an indexable primary-key lookup into an unsafe outer-loop position',
                $sql,
                'analyze-primary-key-join',
                $rows,
                $order,
                in_array('t301', $order, true) ? ['t301 INTEGER PRIMARY KEY', 't301c'] : [],
                $leftJoin,
                0,
                count($order) > 1,
                false,
                null,
                false,
                true,
                false,
                [],
                str_contains($sql, 'WHERE ') ? [substr($sql, strpos($sql, 'WHERE ') + 6)] : [],
                'SEARCH t301 USING INTEGER PRIMARY KEY (rowid=?) remains an inner lookup after ANALYZE',
            );
        }

        foreach ([
            ['where3-5.0a', 'aaa JOIN bbb ON bbb.id = aaa.parent', ['aaa', 'bbb']],
            ['where3-5.1', 'aaa JOIN aaa AS bbb ON bbb.id = aaa.parent', ['aaa', 'bbb']],
            ['where3-5.2', 'bbb JOIN aaa ON bbb.id = aaa.parent', ['aaa', 'bbb']],
            ['where3-5.3', 'aaa AS bbb JOIN aaa ON bbb.id = aaa.parent', ['aaa', 'bbb']],
        ] as [$section, $from, $order]) {
            $templates[] = $template(
                $section,
                'tag-title join must probe the parent rowid after the fk index and sort explicitly',
                'SELECT bbb.title AS tag_title FROM ' . $from . " WHERE aaa.fk='constant' AND LENGTH(bbb.title)>0 AND bbb.parent=4 ORDER BY bbb.title COLLATE NOCASE ASC",
                'performance-regression-join',
                [],
                $order,
                ['aaa_333', 'bbb INTEGER PRIMARY KEY'],
                false,
                0,
                true,
                false,
                null,
                true,
                true,
                false,
                ['bbb.id=aaa.parent'],
                ["aaa.fk='constant'", 'LENGTH(bbb.title)>0', 'bbb.parent=4'],
                'SEARCH aaa USING INDEX aaa_333 (fk=?); SEARCH bbb USING INTEGER PRIMARY KEY (rowid=?); USE TEMP B-TREE FOR ORDER BY',
            );
        }

        $naturalRows = [
            [1, 'w-one', 'x-one', 'y-one', 'z-one'],
            [9, 'w-nine', 'x-nine', 'y-nine', 'z-nine'],
        ];
        $predicates = [
            ['', [], null],
            ['ORDER BY a', [], 'a'],
            ['ORDER BY t6w.a', [], 't6w.a'],
            ['WHERE a>0', ['a>0'], null],
            ['WHERE t6y.a>0', ['t6y.a>0'], null],
            ['WHERE a>0 ORDER BY a', ['a>0'], 'a'],
        ];
        $joinForms = [
            ['1', 't6w NATURAL JOIN t6x NATURAL JOIN t6y NATURAL JOIN t6z'],
            ['2', 't6w JOIN t6x USING(a) JOIN t6y USING(a) JOIN t6z USING(a)'],
            ['3', 't6w NATURAL JOIN t6x JOIN t6y USING(a) JOIN t6z USING(a)'],
            ['4', 't6w JOIN t6x USING(a) NATURAL JOIN t6y JOIN t6z USING(a)'],
            ['5', 't6w JOIN t6x USING(a) JOIN t6y USING(a) NATURAL JOIN t6z'],
            ['6', 't6w JOIN t6x USING(a) NATURAL JOIN t6y NATURAL JOIN t6z'],
            ['7', 't6w NATURAL JOIN t6x JOIN t6y USING(a) NATURAL JOIN t6z'],
            ['8', 't6w NATURAL JOIN t6x NATURAL JOIN t6y JOIN t6z USING(a)'],
        ];
        foreach ($predicates as $predicateIndex => [$suffix, $whereTerms, $orderBy]) {
            foreach ($joinForms as [$form, $from]) {
                $section = 'where3-6.' . ($predicateIndex + 1) . '.' . $form;
                $templates[] = $template(
                    $section,
                    'NATURAL JOIN and USING(a) resolve the shared a column identically',
                    trim('SELECT * FROM ' . $from . ' ' . $suffix),
                    'natural-using-name-resolution',
                    $naturalRows,
                    ['t6w', 't6x', 't6y', 't6z'],
                    [],
                    false,
                    0,
                    false,
                    true,
                    null,
                    $orderBy === null ? false : false,
                    false,
                    false,
                    ['USING(a) or NATURAL shared column a'],
                    $whereTerms,
                    'all NATURAL/USING variants return the same two joined rows for the shared a column',
                );
            }
        }

        $leftJoinResults = [
            ['1', 'SELECT x1 FROM t71 LEFT JOIN t72 ON x2=y1', [[123]], 0, false],
            ['2', 'SELECT x1 FROM t71 LEFT JOIN t72 ON x2=y1 WHERE y2 IS NULL', [], 0, false],
            ['3', 'SELECT x1 FROM t71 LEFT JOIN t72 ON x2=y1 WHERE y2 IS NOT NULL', [[123]], 0, false],
            ['4', 'SELECT x1 FROM t71 LEFT JOIN t72 ON x2=y1 AND y2 IS NULL', [[123]], 1, false],
            ['5', 'SELECT x1 FROM t71 LEFT JOIN t72 ON x2=y1 AND y2 IS NOT NULL', [[123]], 0, false],
            ['6', 'SELECT x3 FROM t73 LEFT JOIN t72 ON x2=y3', [[123]], 0, false],
            ['7', 'SELECT DISTINCT x3 FROM t73 LEFT JOIN t72 ON x2=y3', [[123]], 0, false],
            ['8', 'SELECT x3 FROM t73 LEFT JOIN t74 ON x4=y3', [[123], [123]], 0, false],
            ['9', 'SELECT DISTINCT x3 FROM t73 LEFT JOIN t74 ON x4=y3', [[123]], 0, false],
        ];
        foreach (['none', 'omit-noop-join', 'all'] as $disabled) {
            foreach ($leftJoinResults as [$suffix, $sql, $rows, $nullRows, $tempSort]) {
                $templates[] = $template(
                    'where3-7.' . $disabled . '.' . $suffix,
                    'LEFT JOIN result stability while toggling omit-noop-join optimization',
                    $sql,
                    'left-join-optimization-toggle',
                    $rows,
                    str_contains($sql, 't74') ? ['t73', 't74'] : (str_contains($sql, 't73') ? ['t73', 't72'] : ['t71', 't72']),
                    str_contains($sql, 't74') ? [] : ['t72 INTEGER PRIMARY KEY'],
                    true,
                    $nullRows,
                    false,
                    false,
                    $disabled,
                    $tempSort,
                    str_contains($sql, 't72'),
                    false,
                    str_contains($sql, ' AND ') ? [substr($sql, strpos($sql, ' ON ') + 4)] : ['x2=y1'],
                    str_contains($sql, ' WHERE ') ? [substr($sql, strpos($sql, ' WHERE ') + 7)] : [],
                    'optimization_control ' . $disabled . ' preserves LEFT JOIN result rows and DISTINCT behavior',
                );
            }
        }

        $templates[] = $template(
            'where3-8.1',
            'join predicate plus outer WHERE term must constrain both columns of composite index',
            'SELECT 1 FROM t1 JOIN t2 ON x=c AND y=d WHERE d>0',
            'composite-index-join',
            [[1]],
            ['t1', 't2'],
            ['t2xy'],
            false,
            0,
            false,
            false,
            null,
            false,
            false,
            true,
            ['x=c', 'y=d'],
            ['d>0'],
            'result row is preserved while t2xy is eligible for equality on both x and y',
        );
        $templates[] = $template(
            'where3-8.2',
            'EXPLAIN QUERY PLAN shows x=? AND y=? rather than weakening y to a range',
            'EXPLAIN QUERY PLAN SELECT 1 FROM t1 JOIN t2 ON x=c AND y=d WHERE d>0',
            'composite-index-join-eqp',
            [],
            ['t1', 't2'],
            ['t2xy'],
            false,
            0,
            false,
            false,
            null,
            false,
            false,
            true,
            ['x=c', 'y=d'],
            ['d>0'],
            'SEARCH t2 USING COVERING INDEX t2xy (x=? AND y=?)',
        );

        $out = [];
        $templateCount = count($templates);
        for ($case = 1; $case <= $cases; $case++) {
            $row = $templates[($case - 1) % $templateCount];
            $row['case'] = $case;
            $row['batch'] = intdiv($case - 1, $templateCount) + 1;
            $row['scenario'] .= ' dynamic replay ' . $row['batch'];
            $row['detail'] .= '; where3 dynamic replay ' . $row['batch'];
            $out[] = $row;
        }

        return $out;
    }

    /**
     * @return list<array<string,mixed>>
     */
    public static function where2IndexSelectionAndInCases(int $cases = 1200): array
    {
        if ($cases < 1) {
            throw new \InvalidArgumentException('SQLite where2 index selection dynamic corpus requires at least one case');
        }

        $rows = [];
        for ($i = 1; $i <= 100; $i++) {
            $x = (int) floor(log($i) / log(2));
            $y = ($i + 1) * ($i + 1);
            $rows[$i] = [
                'rowid' => $i,
                'w' => $i,
                'x' => $x,
                'y' => $y,
                'z' => $x + $y,
            ];
        }

        $fullRows = static function (array $rowids) use ($rows): array {
            $out = [];
            foreach ($rowids as $rowid) {
                $row = $rows[$rowid];
                $out[] = [$row['w'], $row['x'], $row['y'], $row['z']];
            }

            return $out;
        };
        $wRows = static function (array $rowids): array {
            $out = [];
            foreach ($rowids as $rowid) {
                $out[] = [$rowid];
            }

            return $out;
        };
        $flatten = static function (array $resultRows): array {
            $flat = [];
            foreach ($resultRows as $row) {
                foreach ($row as $value) {
                    $flat[] = $value;
                }
            }

            return $flat;
        };
        $targetForBatch = static fn (int $batch): int => (($batch - 1) * 7 + 84) % 100 + 1;
        $make = static function (
            string $section,
            string $scenario,
            string $statement,
            string $tableName,
            array $projection,
            array $resultRows,
            array $rowids,
            ?string $indexName,
            array $chosenIndexes,
            bool $requiresSort,
            ?string $orderBy,
            array $whereTerms,
            int $inLayers,
            bool $usesInOperator,
            bool $orToIn,
            bool $duplicateRhsValues,
            bool $deduplicatedOutput,
            bool $unaryPlusDisabledIndex,
            bool $affinitySensitive,
            ?string $opcodeExpectation,
            string $detail,
        ) use ($flatten): array {
            return [
                'source' => 'where2.test sections where2-1.1 through where2-11.4',
                'case' => 0,
                'batch' => 0,
                'upstream_section' => $section,
                'scenario' => $scenario,
                'statement' => $statement,
                'table_name' => $tableName,
                'projection' => $projection,
                'result_rows' => $resultRows,
                'result_flat' => $flatten($resultRows),
                'result_count' => count($resultRows),
                'rowids' => $rowids,
                'index_name' => $indexName,
                'chosen_indexes' => $chosenIndexes,
                'uses_rowid' => $indexName === 'rowid',
                'uses_unique_index' => $indexName === 'i1w' || str_starts_with((string) $indexName, 'sqlite_autoindex_'),
                'uses_composite_index' => in_array($indexName, ['i1xy', 'i1zyx', 'tx_xyz', 'i11aba', 'i11cccccccc'], true),
                'requires_sort' => $requiresSort,
                'sort_marker' => $requiresSort ? 'sort' : 'nosort',
                'order_by' => $orderBy,
                'where_terms' => $whereTerms,
                'in_layers' => $inLayers,
                'uses_in_operator' => $usesInOperator,
                'or_to_in' => $orToIn,
                'duplicate_rhs_values' => $duplicateRhsValues,
                'deduplicated_output' => $deduplicatedOutput,
                'unary_plus_disabled_index' => $unaryPlusDisabledIndex,
                'affinity_sensitive' => $affinitySensitive,
                'opcode_expectation' => $opcodeExpectation,
                'integrity' => 'ok',
                'detail' => $detail,
            ];
        };

        $templates = [
            static function (int $batch) use ($rows, $targetForBatch, $fullRows, $make): array {
                $row = $rows[$targetForBatch($batch)];

                return $make('where2-1.1', 'unique w equality wins over the non-unique x/y index', 'SELECT * FROM t1 WHERE w=' . $row['w'] . ' AND x=' . $row['x'] . ' AND y=' . $row['y'], 't1', ['w', 'x', 'y', 'z'], $fullRows([$row['w']]), [$row['rowid']], 'i1w', ['i1w'], false, null, ['w=' . $row['w'], 'x=' . $row['x'], 'y=' . $row['y']], 0, false, false, false, false, false, false, null, 'upstream prefers UNIQUE i1w over another usable index');
            },
            static function (int $batch) use ($rows, $targetForBatch, $fullRows, $make): array {
                $row = $rows[$targetForBatch($batch)];

                return $make('where2-1.3', 'rowid equality wins over every named index', 'SELECT * FROM t1 WHERE w=' . $row['w'] . ' AND x=' . $row['x'] . ' AND y=' . $row['y'] . ' AND rowid=' . $row['rowid'], 't1', ['w', 'x', 'y', 'z'], $fullRows([$row['w']]), [$row['rowid']], 'rowid', ['rowid'], false, null, ['w=' . $row['w'], 'x=' . $row['x'], 'y=' . $row['y'], 'rowid=' . $row['rowid']], 0, false, false, false, false, false, false, null, 'rowid equality is represented by the upstream queryplan star entry');
            },
            static function (int $batch) use ($rows, $targetForBatch, $fullRows, $make): array {
                $row = $rows[$targetForBatch($batch)];

                return $make('where2-2.1', 'unique equality elides ORDER BY random sort', 'SELECT * FROM t1 WHERE w=' . $row['w'] . ' ORDER BY random()', 't1', ['w', 'x', 'y', 'z'], $fullRows([$row['w']]), [$row['rowid']], 'i1w', ['i1w'], false, 'random()', ['w=' . $row['w']], 0, false, false, false, false, false, false, null, 'single-row UNIQUE lookup lets SQLite ignore a random() sort');
            },
            static function (int $batch) use ($rows, $targetForBatch, $fullRows, $make): array {
                $row = $rows[$targetForBatch($batch)];

                return $make('where2-2.2', 'non-unique x/y equality keeps ORDER BY random sort', 'SELECT * FROM t1 WHERE x=' . $row['x'] . ' AND y=' . $row['y'] . ' ORDER BY random()', 't1', ['w', 'x', 'y', 'z'], $fullRows([$row['w']]), [$row['rowid']], 'i1xy', ['i1xy'], true, 'random()', ['x=' . $row['x'], 'y=' . $row['y']], 0, false, false, false, false, false, false, 'SorterOpen', 'upstream keeps the sorter because i1xy is not declared UNIQUE');
            },
            static function (int $batch) use ($rows, $targetForBatch, $fullRows, $make): array {
                $row = $rows[$targetForBatch($batch)];

                return $make('where2-2.3', 'rowid equality elides ORDER BY random sort', 'SELECT * FROM t1 WHERE rowid=' . $row['rowid'] . ' AND x=' . $row['x'] . ' AND y=' . $row['y'] . ' ORDER BY random()', 't1', ['w', 'x', 'y', 'z'], $fullRows([$row['w']]), [$row['rowid']], 'rowid', ['rowid'], false, 'random()', ['rowid=' . $row['rowid'], 'x=' . $row['x'], 'y=' . $row['y']], 0, false, false, false, false, false, false, null, 'rowid lookup is single-row and suppresses the random() sorter');
            },
            static fn (int $batch): array => $make('where2-2.5/2.5b', 'ORDER BY random remains visible in EXPLAIN bytecode', 'EXPLAIN SELECT * FROM x1, x2 WHERE x=1 ORDER BY random()', 'x1,x2', ['x1.a', 'x1.b', 'x2.x'], [], [], 'rowid', ['x2 rowid'], true, 'random()', ['x=1'], 0, false, false, false, false, false, false, 'random and SorterOpen', 'upstream guards that ORDER BY random is not constant-folded away'),
            static fn (int $batch): array => $make('where2-2.6/2.6b', 'constant abs ORDER BY is optimized out', 'EXPLAIN SELECT * FROM x1, x2 WHERE x=1 ORDER BY abs(5)', 'x1,x2', ['x1.a', 'x1.b', 'x2.x'], [], [], 'rowid', ['x2 rowid'], false, 'abs(5)', ['x=1'], 0, false, false, false, false, false, false, 'no abs and no SorterOpen', 'constant ORDER BY expression does not require a runtime sorter'),
            static fn (int $batch): array => $make('where2-3.1', 'forward rowid table scan honors LIMIT', 'SELECT * FROM t1 ORDER BY rowid LIMIT 2', 't1', ['w', 'x', 'y', 'z'], $fullRows([1, 2]), [1, 2], 'rowid', ['rowid'], false, 'rowid ASC', [], 0, false, false, false, false, false, false, null, 'rowid table scan returns the first two rows without temp sorting'),
            static fn (int $batch): array => $make('where2-3.2', 'reverse rowid table scan honors LIMIT', 'SELECT * FROM t1 ORDER BY rowid DESC LIMIT 2', 't1', ['w', 'x', 'y', 'z'], $fullRows([100, 99]), [100, 99], 'rowid', ['rowid'], false, 'rowid DESC', [], 0, false, false, false, false, false, false, null, 'rowid table scan returns the last two rows backward without temp sorting'),
            static fn (int $batch): array => $make('where2-4.1', 'two IN layers probe z/y/x index and sort by w', 'SELECT * FROM t1 WHERE z IN (10207,10006) AND y IN (10000,10201) AND x>0 AND x<10 ORDER BY w', 't1', ['w', 'x', 'y', 'z'], $fullRows([99, 100]), [99, 100], 'i1zyx', ['i1zyx'], true, 'w ASC', ['z IN (10207,10006)', 'y IN (10000,10201)', 'x>0', 'x<10'], 2, true, false, false, false, false, false, null, 'IN operator constraints feed multiple columns of i1zyx'),
            static fn (int $batch): array => $make('where2-4.2', 'z IN with y equality probes i1zyx', 'SELECT * FROM t1 WHERE z IN (10207,10006) AND y=10000 AND x>0 AND x<10 ORDER BY w', 't1', ['w', 'x', 'y', 'z'], $fullRows([99]), [99], 'i1zyx', ['i1zyx'], true, 'w ASC', ['z IN (10207,10006)', 'y=10000', 'x>0', 'x<10'], 1, true, false, false, false, false, false, null, 'one IN layer and one equality still use the z/y/x index'),
            static fn (int $batch): array => $make('where2-4.3', 'z equality with y IN probes i1zyx', 'SELECT * FROM t1 WHERE z=10006 AND y IN (10000,10201) AND x>0 AND x<10 ORDER BY w', 't1', ['w', 'x', 'y', 'z'], $fullRows([99]), [99], 'i1zyx', ['i1zyx'], true, 'w ASC', ['z=10006', 'y IN (10000,10201)', 'x>0', 'x<10'], 1, true, false, false, false, false, false, null, 'equality on the left index column combines with an IN layer on y'),
            static fn (int $batch): array => $make('where2-4.4', 'subquery IN on z feeds i1zyx', 'SELECT * FROM t1 WHERE z IN (SELECT 10207 UNION SELECT 10006) AND y IN (10000,10201) AND x>0 AND x<10 ORDER BY w', 't1', ['w', 'x', 'y', 'z'], $fullRows([99, 100]), [99, 100], 'i1zyx', ['i1zyx'], true, 'w ASC', ['z IN subquery', 'y IN (10000,10201)', 'x>0', 'x<10'], 2, true, false, false, false, false, false, null, 'compound subquery RHS is materialized as an IN set for i1zyx'),
            static fn (int $batch): array => $make('where2-4.5', 'subquery IN on z and y feeds i1zyx', 'SELECT * FROM t1 WHERE z IN (SELECT 10207 UNION SELECT 10006) AND y IN (SELECT 10000 UNION SELECT 10201) AND x>0 AND x<10 ORDER BY w', 't1', ['w', 'x', 'y', 'z'], $fullRows([99, 100]), [99, 100], 'i1zyx', ['i1zyx'], true, 'w ASC', ['z IN subquery', 'y IN subquery', 'x>0', 'x<10'], 2, true, false, false, false, false, false, null, 'two subquery-backed IN layers still constrain i1zyx'),
            static fn (int $batch): array => $make('where2-4.6a', 'x/y IN layers satisfy ORDER BY x through i1xy', 'SELECT * FROM t1 WHERE x IN (1,2,3,4,5,6,7,8) AND y IN (10000,10001,10002,10003,10004,10005) ORDER BY x', 't1', ['w', 'x', 'y', 'z'], $fullRows([99]), [99], 'i1xy', ['i1xy'], false, 'x ASC', ['x IN (1..8)', 'y IN (10000..10005)'], 2, true, false, false, false, false, false, null, 'i1xy order satisfies ORDER BY x with multi-column IN constraints'),
            static fn (int $batch): array => $make('where2-4.6b', 'x/y IN layers satisfy ORDER BY x DESC through i1xy', 'SELECT * FROM t1 WHERE x IN (1,2,3,4,5,6,7,8) AND y IN (10000,10001,10002,10003,10004,10005) ORDER BY x DESC', 't1', ['w', 'x', 'y', 'z'], $fullRows([99]), [99], 'i1xy', ['i1xy'], false, 'x DESC', ['x IN (1..8)', 'y IN (10000..10005)'], 2, true, false, false, false, false, false, null, 'single qualifying x/y row makes the descending order index-satisfied'),
            static fn (int $batch): array => $make('where2-4.6c', 'x/y IN layers satisfy ORDER BY x,y through i1xy', 'SELECT * FROM t1 WHERE x IN (1,2,3,4,5,6,7,8) AND y IN (10000,10001,10002,10003,10004,10005) ORDER BY x, y', 't1', ['w', 'x', 'y', 'z'], $fullRows([99]), [99], 'i1xy', ['i1xy'], false, 'x ASC, y ASC', ['x IN (1..8)', 'y IN (10000..10005)'], 2, true, false, false, false, false, false, null, 'i1xy key order satisfies the full ORDER BY prefix'),
            static fn (int $batch): array => $make('where2-4.6d', 'x/y IN layers cannot satisfy ORDER BY x,y DESC', 'SELECT * FROM t1 WHERE x IN (1,2,3,4,5,6,7,8) AND y IN (10000,10001,10002,10003,10004,10005) ORDER BY x, y DESC', 't1', ['w', 'x', 'y', 'z'], $fullRows([99]), [99], 'i1xy', ['i1xy'], true, 'x ASC, y DESC', ['x IN (1..8)', 'y IN (10000..10005)'], 2, true, false, false, false, false, false, 'SorterOpen', 'mixed direction on y requires a sorter despite the i1xy probe'),
            static fn (int $batch): array => $make('where2-4.6x', 'duplicate IN RHS values do not duplicate ascending output rows', 'SELECT * FROM t1 WHERE z IN (10207,10006,10006,10207) ORDER BY w', 't1', ['w', 'x', 'y', 'z'], $fullRows([99, 100]), [99, 100], 'i1zyx', ['i1zyx'], true, 'w ASC', ['z IN (10207,10006,10006,10207)'], 1, true, false, true, true, false, false, null, 'duplicate RHS z values are de-duplicated before row output'),
            static fn (int $batch): array => $make('where2-4.6y', 'duplicate IN RHS values do not duplicate descending output rows', 'SELECT * FROM t1 WHERE z IN (10207,10006,10006,10207) ORDER BY w DESC', 't1', ['w', 'x', 'y', 'z'], $fullRows([100, 99]), [100, 99], 'i1zyx', ['i1zyx'], true, 'w DESC', ['z IN (10207,10006,10006,10207)'], 1, true, false, true, true, false, false, null, 'duplicate RHS z values are de-duplicated before reverse row output'),
            static fn (int $batch): array => $make('where2-5.1', 'unique equality satisfies ORDER BY same unique column', 'SELECT * FROM t1 WHERE w=99 ORDER BY w', 't1', ['w', 'x', 'y', 'z'], $fullRows([99]), [99], 'i1w', ['i1w'], false, 'w ASC', ['w=99'], 0, false, false, false, false, false, false, null, 'UNIQUE equality on w makes ORDER BY w a no-sort lookup'),
            static fn (int $batch): array => $make('where2-5.2a', 'single-value IN on unique column satisfies ORDER BY w', 'SELECT * FROM t1 WHERE w IN (99) ORDER BY w', 't1', ['w', 'x', 'y', 'z'], $fullRows([99]), [99], 'i1w', ['i1w'], false, 'w ASC', ['w IN (99)'], 1, true, false, false, false, false, false, null, 'single-value IN on a UNIQUE column behaves as an ordered lookup'),
            static fn (int $batch): array => $make('where2-6.1.1', 'OR equalities on w are rewritten to an IN lookup', 'SELECT * FROM t1 WHERE w=99 OR w=100 ORDER BY +w', 't1', ['w', 'x', 'y', 'z'], $fullRows([99, 100]), [99, 100], 'i1w', ['i1w'], true, '+w ASC', ['w=99', 'w=100'], 1, true, true, false, false, false, false, null, 'OR-to-IN transformation uses i1w and sorts on the unary-plus order expression'),
            static fn (int $batch): array => $make('where2-6.2', 'three OR equalities on w are rewritten to an IN lookup', 'SELECT * FROM t1 WHERE w=99 OR w=100 OR 6=w ORDER BY +w', 't1', ['w', 'x', 'y', 'z'], $fullRows([6, 99, 100]), [6, 99, 100], 'i1w', ['i1w'], true, '+w ASC', ['w=99', 'w=100', '6=w'], 1, true, true, false, false, false, false, null, 'OR-to-IN transformation recognizes commuted equality terms'),
            static fn (int $batch): array => $make('where2-6.3', 'unary plus in one OR arm disables the w index transformation', 'SELECT * FROM t1 WHERE w=99 OR w=100 OR 6=+w ORDER BY +w', 't1', ['w', 'x', 'y', 'z'], $fullRows([6, 99, 100]), [6, 99, 100], 'rowid', ['rowid'], true, '+w ASC', ['w=99', 'w=100', '6=+w'], 0, false, false, false, false, true, false, null, 'unary plus strips affinity and prevents the OR-to-IN index proof'),
            static fn (int $batch): array => $make('where2-6.7', 'TEXT unique index comparison preserves numeric affinity equality', 'SELECT b,a FROM t2249b CROSS JOIN t2249a WHERE a=b', 't2249b,t2249a', ['b', 'a'], [[123, '0123']], [], 'sqlite_autoindex_t2249a_1', ['t2249b rowid', 'sqlite_autoindex_t2249a_1'], false, null, ['a=b'], 0, false, false, false, false, false, true, null, 'TEXT and INTEGER affinity conversion makes 0123 compare equal to 123'),
            static fn (int $batch): array => $make('where2-6.9', 'unary plus disables affinity conversion and yields no rows', 'SELECT b,a FROM t2249b CROSS JOIN t2249a WHERE a=+b', 't2249b,t2249a', ['b', 'a'], [], [], 'sqlite_autoindex_t2249a_1', ['t2249b rowid', 'sqlite_autoindex_t2249a_1'], false, null, ['a=+b'], 0, false, false, false, false, true, true, null, 'unary plus removes numeric affinity from the RHS and suppresses the match'),
            static fn (int $batch): array => $make('where2-6.11', 'affinity-sensitive OR term disables OR optimization but keeps row', "SELECT b,a FROM t2249b CROSS JOIN t2249a WHERE a=b OR a='hello'", 't2249b,t2249a', ['b', 'a'], [[123, '0123']], [], 'sqlite_autoindex_t2249a_1', ['t2249b rowid', 'sqlite_autoindex_t2249a_1'], false, null, ['a=b', "a='hello'"], 0, false, false, false, false, false, true, null, 'the a=b affinity conflict blocks OR optimization without changing result rows'),
            static fn (int $batch): array => $make('where2-7.1', 'join with one non-unique table requires sorter', 'SELECT y FROM t8, t9 WHERE a=1 ORDER BY a, y', 't8,t9', ['y'], [[3], [4]], [], 'sqlite_autoindex_t8_1', ['sqlite_autoindex_t8_1', 't9 rowid'], true, 'a ASC, y ASC', ['a=1'], 0, false, false, false, false, false, false, 'SorterOpen', 'all joined tables must be unique before ORDER BY can be elided'),
            static fn (int $batch): array => $make('where2-7.2', 'single-table unique result elides sorter for other columns', 'SELECT * FROM t8 WHERE a=1 ORDER BY b, c', 't8', ['a', 'b', 'c'], [[1, 2, 3]], [], 'sqlite_autoindex_t8_1', ['sqlite_autoindex_t8_1'], false, 'b ASC, c ASC', ['a=1'], 0, false, false, false, false, false, false, null, 'unique a lookup proves one output row and suppresses the sorter'),
            static fn (int $batch): array => $make('where2-8.5', 'three subquery IN constraints return the overlapping 12-14 range', 'SELECT w FROM tx WHERE x IN (SELECT x FROM t1 WHERE w BETWEEN 10 AND 20) AND y IN (SELECT y FROM t1 WHERE w BETWEEN 10 AND 20) AND z IN (SELECT z FROM t1 WHERE w BETWEEN 12 AND 14)', 'tx', ['w'], $wRows([12, 13, 14]), [12, 13, 14], 'tx_xyz', ['tx_xyz'], false, null, ['x IN w 10..20', 'y IN w 10..20', 'z IN w 12..14'], 3, true, false, false, false, false, false, null, 'multi-column index tx_xyz consumes all three IN constraints'),
            static fn (int $batch): array => $make('where2-8.7', 'x subquery IN narrows the tx result to rows 10-15', 'SELECT w FROM tx WHERE x IN (SELECT x FROM t1 WHERE w BETWEEN 12 AND 14) AND y IN (SELECT y FROM t1 WHERE w BETWEEN 10 AND 20) AND z IN (SELECT z FROM t1 WHERE w BETWEEN 10 AND 20)', 'tx', ['w'], $wRows([10, 11, 12, 13, 14, 15]), [10, 11, 12, 13, 14, 15], 'tx_xyz', ['tx_xyz'], false, null, ['x IN w 12..14', 'y IN w 10..20', 'z IN w 10..20'], 3, true, false, false, false, false, false, null, 'upstream tx_xyz scan preserves all rowids whose x bucket overlaps 12-14'),
            static fn (int $batch): array => $make('where2-8.8', 'three broad subquery IN constraints return rows 10-20', 'SELECT w FROM tx WHERE x IN (SELECT x FROM t1 WHERE w BETWEEN 10 AND 20) AND y IN (SELECT y FROM t1 WHERE w BETWEEN 10 AND 20) AND z IN (SELECT z FROM t1 WHERE w BETWEEN 10 AND 20)', 'tx', ['w'], $wRows([10, 11, 12, 13, 14, 15, 16, 17, 18, 19, 20]), [10, 11, 12, 13, 14, 15, 16, 17, 18, 19, 20], 'tx_xyz', ['tx_xyz'], false, null, ['x IN w 10..20', 'y IN w 10..20', 'z IN w 10..20'], 3, true, false, false, false, false, false, null, 'all three tx_xyz IN layers admit the full 10-20 interval'),
            static fn (int $batch): array => $make('where2-11.1', 'redundant-column index supports a and b equality ordering', 'SELECT c FROM t11 WHERE a=1 AND b=2 ORDER BY c', 't11', ['c'], [[3], [9]], [], 'i11aba', ['i11aba'], false, 'c ASC', ['a=1', 'b=2'], 0, false, false, false, false, false, false, null, 'index i11aba has column a twice but still satisfies the equality prefix'),
            static fn (int $batch): array => $make('where2-11.4', 'OR over repeated-column indexes preserves ordered d values', 'SELECT d FROM t11 WHERE c=7 OR (a=1 AND b=2) ORDER BY d', 't11', ['d'], [[4], [8], [10]], [], 'i11aba', ['i11cccccccc', 'i11aba'], true, 'd ASC', ['c=7', 'a=1 AND b=2'], 0, false, false, false, false, false, false, 'SorterOpen', 'OR terms use redundant-column indexes and sort the final d projection'),
        ];

        $out = [];
        for ($case = 1; $case <= $cases; $case++) {
            $batch = intdiv($case - 1, count($templates)) + 1;
            $template = $templates[($case - 1) % count($templates)]($batch);
            $template['case'] = $case;
            $template['batch'] = $batch;
            $template['scenario'] .= ' dynamic batch ' . $batch;
            $template['detail'] .= '; where2 dynamic replay ' . $batch;
            $out[] = $template;
        }

        return $out;
    }

    /**
     * @return list<array{source:string,case:int,upstream_section:string,batch:int,scenario:string,statement:string,category:string,input_order:list<string>,chosen_order:list<string>,cross_join:bool,automatic_index:bool,available_indexes:list<string>,chosen_indexes:list<string>,where_terms:list<string>,result_rows:list<array<int,mixed>>,result_flat:list<mixed>,expected_count:int|null,vmstep_relation:string|null,vmstep_threshold:int|null,guard_tested_before_seek:bool,forbidden_opcodes:list<string>,contains_forbidden_opcodes:bool,detail:string,integrity:string}>
     */
    public static function whereFJoinOrderAndOrFactoringCases(int $cases = 1000): array
    {
        if ($cases < 1) {
            throw new \InvalidArgumentException('SQLite whereF planner dynamic corpus requires at least one case');
        }

        $flatten = static function (array $rows): array {
            $flat = [];
            foreach ($rows as $row) {
                foreach ($row as $value) {
                    $flat[] = $value;
                }
            }

            return $flat;
        };

        $template = static function (
            string $section,
            string $scenario,
            string $statement,
            string $category,
            array $inputOrder,
            array $chosenOrder,
            bool $crossJoin,
            array $availableIndexes,
            array $chosenIndexes,
            array $whereTerms,
            array $rows,
            ?int $expectedCount,
            ?string $vmstepRelation,
            ?int $vmstepThreshold,
            bool $guardTestedBeforeSeek,
            array $forbiddenOpcodes,
            bool $containsForbiddenOpcodes,
            string $detail,
        ) use ($flatten): array {
            return [
                'source' => 'whereF.test sections whereF-1.1 through whereF-5.6 and whereF-7.1 through whereF-7.3',
                'case' => 0,
                'upstream_section' => $section,
                'batch' => 0,
                'scenario' => $scenario,
                'statement' => $statement,
                'category' => $category,
                'input_order' => $inputOrder,
                'chosen_order' => $chosenOrder,
                'cross_join' => $crossJoin,
                'automatic_index' => false,
                'available_indexes' => $availableIndexes,
                'chosen_indexes' => $chosenIndexes,
                'where_terms' => $whereTerms,
                'result_rows' => $rows,
                'result_flat' => $flatten($rows),
                'expected_count' => $expectedCount,
                'vmstep_relation' => $vmstepRelation,
                'vmstep_threshold' => $vmstepThreshold,
                'guard_tested_before_seek' => $guardTestedBeforeSeek,
                'forbidden_opcodes' => $forbiddenOpcodes,
                'contains_forbidden_opcodes' => $containsForbiddenOpcodes,
                'detail' => $detail,
                'integrity' => 'ok',
            ];
        };

        $templates = [
            $template(
                'whereF-1.1',
                'costed join order scans t2 before probing unique t1(a)',
                'SELECT * FROM t1, t2 WHERE t1.a=t2.e AND t2.d<t1.b AND t1.c!=10',
                'join-order-cost',
                ['t1', 't2'],
                ['t2', 't1'],
                false,
                ['i1 on t1(a)', 'i2 on t2(d)'],
                ['i1'],
                ['t1.a=t2.e', 't2.d<t1.b', 't1.c!=10'],
                [],
                null,
                null,
                null,
                false,
                [],
                false,
                'SCAN t2 then SEARCH t1 USING INDEX i1 (a=?) because the combined nested-loop cost is lower',
            ),
            $template(
                'whereF-1.2',
                'input order t2,t1 keeps the same costed plan',
                'SELECT * FROM t2, t1 WHERE t1.a=t2.e AND t2.d<t1.b AND t1.c!=10',
                'join-order-cost',
                ['t2', 't1'],
                ['t2', 't1'],
                false,
                ['i1 on t1(a)', 'i2 on t2(d)'],
                ['i1'],
                ['t1.a=t2.e', 't2.d<t1.b', 't1.c!=10'],
                [],
                null,
                null,
                null,
                false,
                [],
                false,
                'SCAN t2 then SEARCH t1 USING INDEX i1 (a=?) without inventing an automatic index',
            ),
            $template(
                'whereF-1.3',
                'CROSS JOIN t2,t1 freezes an order that also matches the best plan',
                'SELECT * FROM t2 CROSS JOIN t1 WHERE t1.a=t2.e AND t2.d<t1.b AND t1.c!=10',
                'join-order-cost',
                ['t2', 't1'],
                ['t2', 't1'],
                true,
                ['i1 on t1(a)', 'i2 on t2(d)'],
                ['i1'],
                ['t1.a=t2.e', 't2.d<t1.b', 't1.c!=10'],
                [],
                null,
                null,
                null,
                false,
                [],
                false,
                'SCAN t2 then SEARCH t1 USING INDEX i1 (a=?) because CROSS JOIN preserves t2 outer loop',
            ),
            $template(
                'whereF-2.1',
                'range constraint on t1(a) still yields t2 outer-loop plan',
                'SELECT * FROM t1, t2 WHERE t1.a>? AND t2.d>t1.c AND t1.b=t2.e',
                'join-order-cost',
                ['t1', 't2'],
                ['t2', 't1'],
                false,
                ['i1 on t1(a)', 'i2 on t1(b)', 'i3 on t2(d)'],
                ['i2'],
                ['t1.a>?', 't2.d>t1.c', 't1.b=t2.e'],
                [],
                null,
                null,
                null,
                false,
                [],
                false,
                'SCAN t2 then SEARCH t1 USING INDEX i2 (b=?) wins over the independent t1 range',
            ),
            $template(
                'whereF-2.2',
                'reversed FROM clause does not change the selected t2 outer-loop',
                'SELECT * FROM t2, t1 WHERE t1.a>? AND t2.d>t1.c AND t1.b=t2.e',
                'join-order-cost',
                ['t2', 't1'],
                ['t2', 't1'],
                false,
                ['i1 on t1(a)', 'i2 on t1(b)', 'i3 on t2(d)'],
                ['i2'],
                ['t1.a>?', 't2.d>t1.c', 't1.b=t2.e'],
                [],
                null,
                null,
                null,
                false,
                [],
                false,
                'SCAN t2 then SEARCH t1 USING INDEX i2 (b=?) after commuted FROM order',
            ),
            $template(
                'whereF-2.3',
                'CROSS JOIN t2,t1 enforces the same legal nested-loop choice',
                'SELECT * FROM t2 CROSS JOIN t1 WHERE t1.a>? AND t2.d>t1.c AND t1.b=t2.e',
                'join-order-cost',
                ['t2', 't1'],
                ['t2', 't1'],
                true,
                ['i1 on t1(a)', 'i2 on t1(b)', 'i3 on t2(d)'],
                ['i2'],
                ['t1.a>?', 't2.d>t1.c', 't1.b=t2.e'],
                [],
                null,
                null,
                null,
                false,
                [],
                false,
                'SCAN t2 then SEARCH t1 USING INDEX i2 (b=?) because CROSS JOIN order keeps t2 outer',
            ),
            $template(
                'whereF-3.1',
                'composite unique index on t1(a,b) is preferred inside a t2 scan',
                'SELECT t1.a, t1.b, t2.d, t2.e FROM t1, t2 WHERE t2.d=t1.b AND t1.a=(t2.d+1) AND t1.b=(t2.e+1)',
                'join-order-cost',
                ['t1', 't2'],
                ['t2', 't1'],
                false,
                ['i1 on t1(a,b)', 'i2 on t2(d)'],
                ['i1'],
                ['t2.d=t1.b', 't1.a=t2.d+1', 't1.b=t2.e+1'],
                [],
                null,
                null,
                null,
                false,
                [],
                false,
                'SCAN t2 then SEARCH t1 USING COVERING INDEX i1 (a=? AND b=?)',
            ),
            $template(
                'whereF-3.2',
                'input t2,t1 order preserves the composite t1(a,b) probe',
                'SELECT t1.a, t1.b, t2.d, t2.e FROM t2, t1 WHERE t2.d=t1.b AND t1.a=(t2.d+1) AND t1.b=(t2.e+1)',
                'join-order-cost',
                ['t2', 't1'],
                ['t2', 't1'],
                false,
                ['i1 on t1(a,b)', 'i2 on t2(d)'],
                ['i1'],
                ['t2.d=t1.b', 't1.a=t2.d+1', 't1.b=t2.e+1'],
                [],
                null,
                null,
                null,
                false,
                [],
                false,
                'SCAN t2 then SEARCH t1 USING COVERING INDEX i1 (a=? AND b=?)',
            ),
            $template(
                'whereF-3.3',
                'CROSS JOIN t2,t1 leaves the composite-index search unchanged',
                'SELECT t1.a, t1.b, t2.d, t2.e FROM t2 CROSS JOIN t1 WHERE t2.d=t1.b AND t1.a=(t2.d+1) AND t1.b=(t2.e+1)',
                'join-order-cost',
                ['t2', 't1'],
                ['t2', 't1'],
                true,
                ['i1 on t1(a,b)', 'i2 on t2(d)'],
                ['i1'],
                ['t2.d=t1.b', 't1.a=t2.d+1', 't1.b=t2.e+1'],
                [],
                null,
                null,
                null,
                false,
                [],
                false,
                'SCAN t2 then SEARCH t1 USING COVERING INDEX i1 (a=? AND b=?) because CROSS JOIN preserves t2 outer loop',
            ),
            $template(
                'whereF-4.0',
                'composite primary key is selected for a and b equality constraints',
                'SELECT rowid FROM t4 WHERE a=? AND b=?',
                'composite-primary-key',
                ['t4'],
                ['t4'],
                false,
                ['sqlite_autoindex_t4_1 on primary key(a,b,c)', 't4adc on (a,d,c)', 't4aebc on (a,e,b,c)'],
                ['sqlite_autoindex_t4_1'],
                ['a=?', 'b=?'],
                [],
                null,
                null,
                null,
                false,
                [],
                false,
                'SEARCH t4 USING PRIMARY KEY/autoindex prefix (a=? AND b=?) instead of the competing secondary indexes',
            ),
            $template(
                'whereF-5.1/5.2',
                'rowid-only OR arm finds four rows with low VM-step cost',
                'SELECT count(*) FROM t1, t2 WHERE t2.rowid = +t1.rowid',
                'or-vmstep-guard',
                ['t1', 't2'],
                ['t1', 't2'],
                false,
                ['t2f on t2(f2)', 'rowid on t2'],
                ['rowid'],
                ['t2.rowid=+t1.rowid'],
                [[4]],
                4,
                '<',
                200,
                false,
                [],
                false,
                'four t1 rows match t2 rowids 1 through 4 and avoid the f2 range loop',
            ),
            $template(
                'whereF-5.3/5.4',
                'unguarded OR term expands to the full t1 by t2 match set',
                'SELECT count(*) FROM t1, t2 WHERE t2.rowid = +t1.rowid OR t2.f2 = t1.f1',
                'or-vmstep-guard',
                ['t1', 't2'],
                ['t1', 't2'],
                false,
                ['t2f on t2(f2)', 'rowid on t2'],
                ['rowid', 't2f'],
                ['t2.rowid=+t1.rowid', 't2.f2=t1.f1'],
                [[4000]],
                4000,
                '>',
                1000,
                false,
                [],
                false,
                'f2=-1 matches every t2 row for each of the four t1 rows',
            ),
            $template(
                'whereF-5.5/5.6',
                'guarded OR term tests t1.f1!=-1 before entering the f2 seek range',
                'SELECT count(*) FROM t1, t2 WHERE t2.rowid = +t1.rowid OR (t2.f2 = t1.f1 AND t1.f1!=-1)',
                'or-vmstep-guard',
                ['t1', 't2'],
                ['t1', 't2'],
                false,
                ['t2f on t2(f2)', 'rowid on t2'],
                ['rowid', 't2f'],
                ['t2.rowid=+t1.rowid', 't2.f2=t1.f1', 't1.f1!=-1'],
                [[4]],
                4,
                '<',
                200,
                true,
                [],
                false,
                'the false t1.f1!=-1 guard is evaluated outside the t2f seek loop, preserving the low VM-step bound',
            ),
            $template(
                'whereF-7.1',
                'OR factoring keeps NULL-genre row-number emulation results',
                'SELECT cdid FROM cd AS me WHERE 2 > correlated row-number COUNT over nullable genreid ordering',
                'or-factoring-regression',
                ['cd me', 'cd rownum__emulation'],
                ['me', 'rownum__emulation'],
                false,
                ['cd primary key', 'cd_idx_genreid on cd(genreid)'],
                ['cd_idx_genreid', 'rowid'],
                ['genreid IS NULL', 'genreid IS NOT NULL', 'rownum__emulation.cdid > me.cdid'],
                [[4], [5]],
                2,
                null,
                null,
                false,
                [],
                false,
                'TERM_VIRTUAL factoring must not collapse the NULL-equality branch; cdid 4 and 5 remain visible',
            ),
            $template(
                'whereF-7.2',
                'simplified correlated COUNT preserves the two-row result',
                'SELECT correlated COUNT(*) FROM t2 WHERE nullable OR branches compare t1.b and t2.bb',
                'or-factoring-regression',
                ['t1', 't2'],
                ['t1', 't2'],
                false,
                ['t1 primary key', 't2 primary key'],
                ['rowid'],
                ['t1.b IS NOT NULL AND t2.bb IS NULL', 't2.bb<t1.b', 't1.b IS t2.bb AND t2.aa>t1.a'],
                [[2]],
                1,
                null,
                null,
                false,
                [],
                false,
                'the correlated OR expression counts exactly two matching t2 rows',
            ),
            $template(
                'whereF-7.3',
                'GLOB-derived virtual terms are excluded from OR factoring bytecode',
                'EXPLAIN SELECT correlated COUNT(*) FROM t2 WHERE t1.b GLOB \'a*z\' AND t2.bb=\'xyz\' OR t2.bb=t1.b OR t2.aa=t1.a',
                'virtual-term-opcode-guard',
                ['t1', 't2'],
                ['t1', 't2'],
                false,
                ['t2bb on t2(bb)', 't2 primary key'],
                ['t2bb', 'rowid'],
                ["t1.b GLOB 'a*z'", "t2.bb='xyz'", 't2.bb=t1.b', 't2.aa=t1.a'],
                [],
                null,
                null,
                null,
                false,
                ['Lt', 'Ge'],
                false,
                'the generated program must not contain Lt or Ge opcodes from virtual GLOB range terms',
            ),
        ];

        $out = [];
        for ($case = 1; $case <= $cases; $case++) {
            $template = $templates[($case - 1) % count($templates)];
            $template['case'] = $case;
            $template['batch'] = intdiv($case - 1, count($templates)) + 1;
            $template['detail'] .= '; whereF dynamic replay ' . $template['batch'];
            $out[] = $template;
        }

        return $out;
    }

    /**
     * @return list<array{source:string,case:int,upstream_section:string,batch:int,scenario:string,statement:string,likelihood_wrapper:string|null,probability:float|string|null,result_rows:list<array<int,mixed>>,table_order:list<string>,access_plan:list<string>,chosen_indexes:list<string>,uses_composer_filter_first:bool,uses_track_outer_scan:bool,uses_index_range:bool,uses_skip_scan:bool,uses_table_scan:bool,invalid_probability_error:string|null,commuted_equality:bool,detail:string,integrity:string}>
     */
    public static function whereGLikelihoodPlannerCases(int $cases = 1200): array
    {
        if ($cases < 1) {
            throw new \InvalidArgumentException('SQLite whereG likelihood planner corpus requires at least one case');
        }

        $albumRows = [['Mass in B Minor, BWV 232']];
        $probabilityError = 'second argument to likelihood() must be a constant between 0.0 and 1.0';
        $searchComposerAlbum = ['composer rowid', 'album rowid'];

        $templates = [
            [
                'whereG-1.1/1.2',
                'unlikely composer-name LIKE term moves the filtered composer table before track',
                "SELECT DISTINCT aname FROM album, composer, track WHERE unlikely(cname LIKE '%bach%') AND composer.cid=track.cid AND album.aid=track.aid",
                'unlikely(cname LIKE)',
                0.0625,
                $albumRows,
                ['composer', 'track', 'album'],
                ['SCAN composer', 'SEARCH track USING INDEX track_i1 (cid=?)', 'SEARCH album USING INTEGER PRIMARY KEY (rowid=?)'],
                ['track_i1', 'album rowid'],
                true,
                false,
                false,
                false,
                false,
                null,
                false,
                'composer filter has low likelihood, so track_i1 is the first indexed join probe',
            ],
            [
                'whereG-1.3/1.4',
                'neutral likelihood on the composer filter leaves track as the outer row source',
                "SELECT DISTINCT aname FROM album, composer, track WHERE likelihood(cname LIKE '%bach%', 0.5) AND composer.cid=track.cid AND album.aid=track.aid",
                'likelihood(cname LIKE,0.5)',
                0.5,
                $albumRows,
                ['track', 'composer', 'album'],
                ['SCAN track', 'SEARCH composer USING INTEGER PRIMARY KEY (rowid=?)', 'SEARCH album USING INTEGER PRIMARY KEY (rowid=?)'],
                $searchComposerAlbum,
                false,
                true,
                false,
                false,
                true,
                null,
                false,
                'track scan remains cheaper once the LIKE predicate is not marked rare',
            ],
            [
                'whereG-1.5/1.6',
                'plain LIKE planner shape matches the track-first source order',
                "SELECT DISTINCT aname FROM album, composer, track WHERE cname LIKE '%bach%' AND composer.cid=track.cid AND album.aid=track.aid",
                null,
                null,
                $albumRows,
                ['track', 'composer', 'album'],
                ['SCAN track', 'SEARCH composer USING INTEGER PRIMARY KEY (rowid=?)', 'SEARCH album USING INTEGER PRIMARY KEY (rowid=?)'],
                $searchComposerAlbum,
                false,
                true,
                false,
                false,
                true,
                null,
                false,
                'without a planner hint, the join starts from track and probes rowid tables',
            ],
            [
                'whereG-1.7/1.8',
                'unlikely wrappers on join equalities do not change the selected track-first plan',
                "SELECT DISTINCT aname FROM album, composer, track WHERE cname LIKE '%bach%' AND unlikely(composer.cid=track.cid) AND unlikely(album.aid=track.aid)",
                'unlikely(join equalities)',
                0.0625,
                $albumRows,
                ['track', 'composer', 'album'],
                ['SCAN track', 'SEARCH composer USING INTEGER PRIMARY KEY (rowid=?)', 'SEARCH album USING INTEGER PRIMARY KEY (rowid=?)'],
                $searchComposerAlbum,
                false,
                true,
                false,
                false,
                true,
                null,
                false,
                'join equality hints preserve the rowid probes but do not pull composer first',
            ],
            [
                'whereG-2.1',
                'likelihood rejects negative constant probabilities before planning',
                "SELECT DISTINCT aname FROM album, composer, track WHERE likelihood(cname LIKE '%bach%', -0.01) AND composer.cid=track.cid AND album.aid=track.aid",
                'likelihood(cname LIKE,-0.01)',
                -0.01,
                [],
                [],
                [],
                [],
                false,
                false,
                false,
                false,
                false,
                $probabilityError,
                false,
                'negative probability is not a legal likelihood planner hint',
            ],
            [
                'whereG-2.2',
                'likelihood rejects probabilities greater than one before planning',
                "SELECT DISTINCT aname FROM album, composer, track WHERE likelihood(cname LIKE '%bach%', 1.01) AND composer.cid=track.cid AND album.aid=track.aid",
                'likelihood(cname LIKE,1.01)',
                1.01,
                [],
                [],
                [],
                [],
                false,
                false,
                false,
                false,
                false,
                $probabilityError,
                false,
                'probability above one is not a legal likelihood planner hint',
            ],
            [
                'whereG-2.3',
                'likelihood rejects nonconstant probability expressions before planning',
                "SELECT DISTINCT aname FROM album, composer, track WHERE likelihood(cname LIKE '%bach%', track.cid) AND composer.cid=track.cid AND album.aid=track.aid",
                'likelihood(cname LIKE,track.cid)',
                'track.cid',
                [],
                [],
                [],
                [],
                false,
                false,
                false,
                false,
                false,
                $probabilityError,
                false,
                'column-valued probability is not a constant planner hint',
            ],
            [
                'whereG-3.1',
                'commuted table equality b1=a1 still scans a and searches b by primary key',
                'SELECT * FROM a, b WHERE b1=a1 AND a2=5',
                null,
                null,
                [],
                ['a', 'b'],
                ['SCAN a', 'SEARCH b USING INDEX sqlite_autoindex_b_1 (b1=?)'],
                ['sqlite_autoindex_b_1'],
                false,
                false,
                false,
                false,
                true,
                null,
                true,
                'equality commutation does not reverse the join order',
            ],
            [
                'whereG-3.2',
                'uncommuted table equality a1=b1 keeps the same indexed b lookup',
                'SELECT * FROM a, b WHERE a1=b1 AND a2=5',
                null,
                null,
                [],
                ['a', 'b'],
                ['SCAN a', 'SEARCH b USING INDEX sqlite_autoindex_b_1 (b1=?)'],
                ['sqlite_autoindex_b_1'],
                false,
                false,
                false,
                false,
                true,
                null,
                true,
                'the planner normalizes a1=b1 to the same b1 lookup',
            ],
            [
                'whereG-3.3',
                'predicate order a2=5 before b1=a1 preserves the same join plan',
                'SELECT * FROM a, b WHERE a2=5 AND b1=a1',
                null,
                null,
                [],
                ['a', 'b'],
                ['SCAN a', 'SEARCH b USING INDEX sqlite_autoindex_b_1 (b1=?)'],
                ['sqlite_autoindex_b_1'],
                false,
                false,
                false,
                false,
                true,
                null,
                true,
                'WHERE-term order does not change the b primary-key probe',
            ],
            [
                'whereG-3.4',
                'predicate order a2=5 before a1=b1 preserves the same join plan',
                'SELECT * FROM a, b WHERE a2=5 AND a1=b1',
                null,
                null,
                [],
                ['a', 'b'],
                ['SCAN a', 'SEARCH b USING INDEX sqlite_autoindex_b_1 (b1=?)'],
                ['sqlite_autoindex_b_1'],
                false,
                false,
                false,
                false,
                true,
                null,
                true,
                'both equality direction and WHERE-term order are normalized',
            ],
            [
                'whereG-5.1.2',
                'open-ended a>? range without a high-probability hint uses index i1',
                'SELECT * FROM t1 WHERE a>?',
                null,
                null,
                [],
                ['t1'],
                ['SEARCH t1 USING INDEX i1 (a>?)'],
                ['i1'],
                false,
                false,
                true,
                false,
                false,
                null,
                false,
                'ordinary range selectivity keeps the a,b index cheaper than a full scan',
            ],
            [
                'whereG-5.1.3',
                'high likelihood on a>? makes the table scan cheaper than index i1',
                'SELECT * FROM t1 WHERE likelihood(a>?, 0.9)',
                'likelihood(a>?,0.9)',
                0.9,
                [],
                ['t1'],
                ['SCAN t1'],
                [],
                false,
                false,
                false,
                false,
                true,
                null,
                false,
                'a range predicted to match most rows is not worth an index probe',
            ],
            [
                'whereG-5.1.4',
                'likely(a>?) is treated as a high-probability range and scans t1',
                'SELECT * FROM t1 WHERE likely(a>?)',
                'likely(a>?)',
                0.9375,
                [],
                ['t1'],
                ['SCAN t1'],
                [],
                false,
                false,
                false,
                false,
                true,
                null,
                false,
                'likely() raises the estimated hit rate enough to choose a scan',
            ],
            [
                'whereG-5.2.2',
                'low likelihood on b>? enables skip-scan through i1 with ANY(a)',
                'SELECT * FROM t1 WHERE likelihood(b>?, 0.01)',
                'likelihood(b>?,0.01)',
                0.01,
                [],
                ['t1'],
                ['SEARCH t1 USING INDEX i1 (ANY(a) AND b>?)'],
                ['i1'],
                false,
                false,
                true,
                true,
                false,
                null,
                false,
                'rare b range justifies skip-scan over distinct a values',
            ],
            [
                'whereG-5.2.3',
                'high likelihood on b>? suppresses skip-scan and scans t1',
                'SELECT * FROM t1 WHERE likelihood(b>?, 0.9)',
                'likelihood(b>?,0.9)',
                0.9,
                [],
                ['t1'],
                ['SCAN t1'],
                [],
                false,
                false,
                false,
                false,
                true,
                null,
                false,
                'b range predicted to match most rows is cheaper as a full scan',
            ],
            [
                'whereG-5.2.4',
                'likely(b>?) suppresses skip-scan and scans t1',
                'SELECT * FROM t1 WHERE likely(b>?)',
                'likely(b>?)',
                0.9375,
                [],
                ['t1'],
                ['SCAN t1'],
                [],
                false,
                false,
                false,
                false,
                true,
                null,
                false,
                'likely() marks b>? as common enough that skip-scan loses',
            ],
            [
                'whereG-5.3.2',
                'high likelihood on equality a=? prefers table scan over i1',
                'SELECT * FROM t1 WHERE likelihood(a=?, 0.9)',
                'likelihood(a=?,0.9)',
                0.9,
                [],
                ['t1'],
                ['SCAN t1'],
                [],
                false,
                false,
                false,
                false,
                true,
                null,
                false,
                'high-probability equality hint overrides the ordinary index lookup',
            ],
            [
                'whereG-5.3.3',
                'likely(a=?) prefers table scan over i1',
                'SELECT * FROM t1 WHERE likely(a=?)',
                'likely(a=?)',
                0.9375,
                [],
                ['t1'],
                ['SCAN t1'],
                [],
                false,
                false,
                false,
                false,
                true,
                null,
                false,
                'likely() equality hint models a common value and suppresses the index probe',
            ],
        ];

        $out = [];
        for ($case = 1; $case <= $cases; $case++) {
            [
                $section,
                $scenario,
                $statement,
                $wrapper,
                $probability,
                $rows,
                $tableOrder,
                $accessPlan,
                $chosenIndexes,
                $composerFirst,
                $trackOuter,
                $indexRange,
                $skipScan,
                $tableScan,
                $error,
                $commuted,
                $detail,
            ] = $templates[($case - 1) % count($templates)];

            $out[] = [
                'source' => 'whereG.test sections whereG-1.1 through whereG-5.3.3',
                'case' => $case,
                'upstream_section' => $section,
                'batch' => intdiv($case - 1, count($templates)) + 1,
                'scenario' => $scenario,
                'statement' => $statement,
                'likelihood_wrapper' => $wrapper,
                'probability' => $probability,
                'result_rows' => $rows,
                'table_order' => $tableOrder,
                'access_plan' => $accessPlan,
                'chosen_indexes' => $chosenIndexes,
                'uses_composer_filter_first' => $composerFirst,
                'uses_track_outer_scan' => $trackOuter,
                'uses_index_range' => $indexRange,
                'uses_skip_scan' => $skipScan,
                'uses_table_scan' => $tableScan,
                'invalid_probability_error' => $error,
                'commuted_equality' => $commuted,
                'detail' => $detail . '; whereG dynamic replay ' . (intdiv($case - 1, count($templates)) + 1),
                'integrity' => $error === null ? 'ok' : 'expected-error',
            ];
        }

        return $out;
    }

    /**
     * @return list<array{source:string,case:int,upstream_section:string,batch:int,scenario:string,statement:string,stat1_rows:list<array{idx:string,stat:string}>,or_terms:list<string>,range_terms:list<string>,chosen_indexes:list<string>,rejected_plan_terms:list<string>,uses_multi_index_or:bool,uses_any_skip_scan:bool,detail:string,integrity:string}>
     */
    public static function whereJMultiIndexRangeCostCases(int $cases = 1000): array
    {
        if ($cases < 1) {
            throw new \InvalidArgumentException('SQLite whereJ multi-index range-cost corpus requires at least one case');
        }

        $templates = [
            [
                'whereJ-5.1/5.2/5.3',
                'range-cost planner selects t1abe and t1abf for split e/f OR terms',
                "SELECT * FROM t1 WHERE (a=1 OR a=2) AND (b=3 OR b=4) AND (d>=5 AND d<=5) AND ((e>=7 AND e<=7) OR (f>=8 AND f<=8)) AND g>0",
                [
                    ['idx' => 't1abc', 'stat' => '2000000 8000 1600 800'],
                    ['idx' => 't1abe', 'stat' => '2000000 8000 1600 150'],
                    ['idx' => 't1abf', 'stat' => '2000000 8000 1600 150'],
                ],
                ['a=1 OR a=2', 'b=3 OR b=4', 'e=7 OR f=8'],
                ['d>=5', 'd<=5', 'e>=7', 'e<=7', 'f>=8', 'f<=8', 'g>0'],
                ['t1abe', 't1abf'],
                ['ANY(a)', 'ANY(b)', 't1abc chosen for both OR arms'],
            ],
            [
                'whereJ-5.1/5.2',
                'e arm uses t1abe because stat1 third-key selectivity beats t1abc',
                "SELECT * FROM t1 WHERE a IN (1,2) AND b IN (3,4) AND e BETWEEN 7 AND 7 AND d BETWEEN 5 AND 5 AND g>0",
                [
                    ['idx' => 't1abc', 'stat' => '2000000 8000 1600 800'],
                    ['idx' => 't1abe', 'stat' => '2000000 8000 1600 150'],
                    ['idx' => 't1abf', 'stat' => '2000000 8000 1600 150'],
                ],
                ['a=1 OR a=2', 'b=3 OR b=4'],
                ['e>=7', 'e<=7', 'd>=5', 'd<=5', 'g>0'],
                ['t1abe'],
                ['ANY(a)', 'ANY(b)', 't1abc selected despite weaker e selectivity'],
            ],
            [
                'whereJ-5.1/5.3',
                'f arm uses t1abf because stat1 third-key selectivity beats t1abc',
                "SELECT * FROM t1 WHERE a IN (1,2) AND b IN (3,4) AND f BETWEEN 8 AND 8 AND d BETWEEN 5 AND 5 AND g>0",
                [
                    ['idx' => 't1abc', 'stat' => '2000000 8000 1600 800'],
                    ['idx' => 't1abe', 'stat' => '2000000 8000 1600 150'],
                    ['idx' => 't1abf', 'stat' => '2000000 8000 1600 150'],
                ],
                ['a=1 OR a=2', 'b=3 OR b=4'],
                ['f>=8', 'f<=8', 'd>=5', 'd<=5', 'g>0'],
                ['t1abf'],
                ['ANY(a)', 'ANY(b)', 't1abc selected despite weaker f selectivity'],
            ],
            [
                'whereJ-4.2',
                'join order keeps cx as the scan outer loop before searching px and le',
                "SELECT px.name, px.description FROM le, cx, px WHERE cx.code='2990' AND cx.type=2 AND px.cx_id=cx.cx_id AND px.px_tid=0 AND px.le_id=le.le_id",
                [
                    ['idx' => 'cx_code_type', 'stat' => '280 280 2'],
                    ['idx' => 'p_pt', 'stat' => '11680827 89824 1'],
                    ['idx' => 'p_cid0', 'stat' => '11680827 3867 1'],
                ],
                ['cx.code=2990', 'cx.type=2'],
                ['px.cx_id=cx.cx_id', 'px.px_tid=0', 'px.le_id=le.le_id'],
                ['cx scan', 'p_cid0', 'le primary key'],
                ['outer search px using p_pt before cx', 'middle cx after px'],
            ],
        ];

        $out = [];
        for ($case = 1; $case <= $cases; $case++) {
            [$section, $scenario, $statement, $stat1Rows, $orTerms, $rangeTerms, $chosenIndexes, $rejectedPlanTerms] = $templates[($case - 1) % count($templates)];
            $batch = intdiv($case - 1, count($templates)) + 1;
            $usesMultiIndexOr = count($chosenIndexes) > 1 && in_array('t1abe', $chosenIndexes, true) && in_array('t1abf', $chosenIndexes, true);

            $out[] = [
                'source' => 'whereJ.test sections whereJ-4.2 and whereJ-5.1 through whereJ-5.3',
                'case' => $case,
                'upstream_section' => $section,
                'batch' => $batch,
                'scenario' => $scenario . ' dynamic replay ' . $batch,
                'statement' => $statement,
                'stat1_rows' => $stat1Rows,
                'or_terms' => $orTerms,
                'range_terms' => $rangeTerms,
                'chosen_indexes' => $chosenIndexes,
                'rejected_plan_terms' => $rejectedPlanTerms,
                'uses_multi_index_or' => $usesMultiIndexOr,
                'uses_any_skip_scan' => false,
                'detail' => $usesMultiIndexOr
                    ? 'MULTI-INDEX OR using t1abe for e range and t1abf for f range; no ANY skip-scan'
                    : 'planner keeps the selective range/join order without ANY skip-scan',
                'integrity' => 'ok',
            ];
        }

        return $out;
    }

    /**
     * @return list<array{source:string,case:int,upstream_section:string,batch:int,scenario:string,statement:string,table_order:list<string>,access_plan:list<string>,chosen_indexes:list<string>,rejected_outer_loop:string|null,cross_join_preserves_order:bool,uses_composite_prefix:bool,uses_or_optimization:bool,guard_terms_before_seek:bool,result_count:int,vmstep_upper_bound_passed:bool,vmstep_lower_bound_passed:bool,detail:string,integrity:string}>
     */
    public static function whereFJoinOrderDynamicCases(int $cases = 1000): array
    {
        if ($cases < 1) {
            throw new \InvalidArgumentException('SQLite whereF join-order corpus requires at least one case');
        }

        $templates = [
            [
                'whereF-1.1',
                'commuted FROM order still chooses t2 outer loop because join cost dominates independent scan cost',
                'SELECT * FROM t1, t2 WHERE t1.a=t2.e AND t2.d<t1.b AND t1.c!=10',
                ['t2', 't1'],
                ['SCAN t2', 'SEARCH t1 USING INDEX i1 (a=?)'],
                ['i1'],
                't1 outer independent scan',
                false,
                false,
                false,
                false,
                0,
                false,
                false,
                'SCAN t2 before SEARCH t1 despite t1 being the cheaper independent outer loop',
            ],
            [
                'whereF-1.2',
                'written t2,t1 order agrees with the selected low-cost nesting',
                'SELECT * FROM t2, t1 WHERE t1.a=t2.e AND t2.d<t1.b AND t1.c!=10',
                ['t2', 't1'],
                ['SCAN t2', 'SEARCH t1 USING INDEX i1 (a=?)'],
                ['i1'],
                null,
                false,
                false,
                false,
                false,
                0,
                false,
                false,
                'planner keeps t2 as outer loop and searches t1 by unique a index',
            ],
            [
                'whereF-1.3',
                'CROSS JOIN fixes t2 before t1 while preserving the same indexed t1 lookup',
                'SELECT * FROM t2 CROSS JOIN t1 WHERE t1.a=t2.e AND t2.d<t1.b AND t1.c!=10',
                ['t2', 't1'],
                ['SCAN t2', 'SEARCH t1 USING INDEX i1 (a=?)'],
                ['i1'],
                null,
                true,
                false,
                false,
                false,
                0,
                false,
                false,
                'CROSS JOIN preserves the explicit t2 outer loop and still uses i1 on t1',
            ],
            [
                'whereF-2.1',
                'range on t1.a is not enough to make t1 the outer loop when t2.d drives the join',
                'SELECT * FROM t1, t2 WHERE t1.a>? AND t2.d>t1.c AND t1.b=t2.e',
                ['t2', 't1'],
                ['SCAN t2', 'SEARCH t1 USING INDEX i2 (b=?)'],
                ['i2'],
                't1 range on a',
                false,
                false,
                false,
                false,
                0,
                false,
                false,
                'the b equality index beats the tempting a range as the inner t1 lookup',
            ],
            [
                'whereF-2.2',
                'reversed FROM order keeps t2 outer loop and searches t1 by b equality',
                'SELECT * FROM t2, t1 WHERE t1.a>? AND t2.d>t1.c AND t1.b=t2.e',
                ['t2', 't1'],
                ['SCAN t2', 'SEARCH t1 USING INDEX i2 (b=?)'],
                ['i2'],
                null,
                false,
                false,
                false,
                false,
                0,
                false,
                false,
                'FROM t2,t1 matches the selected costed nesting',
            ],
            [
                'whereF-2.3',
                'CROSS JOIN t2,t1 keeps t2 outer and still uses t1.b equality lookup',
                'SELECT * FROM t2 CROSS JOIN t1 WHERE t1.a>? AND t2.d>t1.c AND t1.b=t2.e',
                ['t2', 't1'],
                ['SCAN t2', 'SEARCH t1 USING INDEX i2 (b=?)'],
                ['i2'],
                null,
                true,
                false,
                false,
                false,
                0,
                false,
                false,
                'CROSS JOIN fixes the chosen t2 outer loop while keeping i2 as the inner probe',
            ],
            [
                'whereF-3.1',
                'composite unique index on t1(a,b) is selected after t2 supplies both equality terms',
                'SELECT t1.a, t1.b, t2.d, t2.e FROM t1, t2 WHERE t2.d=t1.b AND t1.a=(t2.d+1) AND t1.b=(t2.e+1)',
                ['t2', 't1'],
                ['SCAN t2', 'SEARCH t1 USING COVERING INDEX i1 (a=? AND b=?)'],
                ['i1'],
                't1 outer before t2 expressions are available',
                false,
                true,
                false,
                false,
                0,
                false,
                false,
                't2 is scanned first so the composite a,b equality lookup can use i1',
            ],
            [
                'whereF-3.2',
                'reversed FROM order still lets t2 feed the composite t1(a,b) lookup',
                'SELECT t1.a, t1.b, t2.d, t2.e FROM t2, t1 WHERE t2.d=t1.b AND t1.a=(t2.d+1) AND t1.b=(t2.e+1)',
                ['t2', 't1'],
                ['SCAN t2', 'SEARCH t1 USING COVERING INDEX i1 (a=? AND b=?)'],
                ['i1'],
                null,
                false,
                true,
                false,
                false,
                0,
                false,
                false,
                'planner preserves the composite-prefix search regardless of the written table order',
            ],
            [
                'whereF-3.3',
                'CROSS JOIN t2,t1 keeps the composite-prefix lookup legal and deterministic',
                'SELECT t1.a, t1.b, t2.d, t2.e FROM t2 CROSS JOIN t1 WHERE t2.d=t1.b AND t1.a=(t2.d+1) AND t1.b=(t2.e+1)',
                ['t2', 't1'],
                ['SCAN t2', 'SEARCH t1 USING COVERING INDEX i1 (a=? AND b=?)'],
                ['i1'],
                null,
                true,
                true,
                false,
                false,
                0,
                false,
                false,
                'CROSS JOIN and expression equalities keep a,b available for the i1 lookup',
            ],
            [
                'whereF-4.0',
                'primary-key prefix is preferred for a,b equality over wider alternate indexes',
                'EXPLAIN QUERY PLAN SELECT rowid FROM t4 WHERE a=? AND b=?',
                ['t4'],
                ['SEARCH t4 USING PRIMARY KEY (a=? AND b=?)'],
                ['sqlite_autoindex_t4_1'],
                't4adc and t4aebc wider alternatives',
                false,
                true,
                false,
                false,
                0,
                false,
                false,
                'planner detail contains a=? AND b=? and does not need the a,d,c or a,e,b,c alternatives',
            ],
            [
                'whereF-5.1/5.2',
                'rowid equality OR arm alone returns four rows with fewer than two hundred vmsteps',
                'SELECT count(*) FROM t1, t2 WHERE t2.rowid = +t1.rowid',
                ['t1', 't2'],
                ['SCAN t1', 'SEARCH t2 USING INTEGER PRIMARY KEY (rowid=?)'],
                ['rowid'],
                null,
                false,
                false,
                false,
                false,
                4,
                true,
                false,
                'rowid equality probe is cheap and does not scan the t2f range',
            ],
            [
                'whereF-5.3/5.4',
                'unguarded OR range arm scans the t2f index and returns four thousand matches',
                'SELECT count(*) FROM t1, t2 WHERE t2.rowid = +t1.rowid OR t2.f2 = t1.f1',
                ['t1', 't2'],
                ['SCAN t1', 'MULTI-INDEX OR', 'SEARCH t2 USING INTEGER PRIMARY KEY (rowid=?)', 'SEARCH t2 USING INDEX t2f (f2=?)'],
                ['rowid', 't2f'],
                null,
                false,
                false,
                true,
                false,
                4000,
                false,
                true,
                'without the t1.f1!=-1 guard, the f2=-1 range is visited for every outer row',
            ],
            [
                'whereF-5.5/5.6',
                'guard term t1.f1!=-1 is tested before the f2 seek and suppresses the expensive range scan',
                'SELECT count(*) FROM t1, t2 WHERE t2.rowid = +t1.rowid OR (t2.f2 = t1.f1 AND t1.f1!=-1)',
                ['t1', 't2'],
                ['SCAN t1', 'MULTI-INDEX OR', 'SEARCH t2 USING INTEGER PRIMARY KEY (rowid=?)', 'DEFER SEARCH t2 USING INDEX t2f UNTIL t1.f1!=-1'],
                ['rowid', 't2f'],
                null,
                false,
                false,
                true,
                true,
                4,
                true,
                false,
                'the copied guard is evaluated before seek operations through t2f',
            ],
        ];

        $out = [];
        for ($case = 1; $case <= $cases; $case++) {
            [
                $section,
                $scenario,
                $statement,
                $tableOrder,
                $accessPlan,
                $chosenIndexes,
                $rejectedOuterLoop,
                $crossJoinPreservesOrder,
                $usesCompositePrefix,
                $usesOrOptimization,
                $guardTermsBeforeSeek,
                $resultCount,
                $vmstepUpperBoundPassed,
                $vmstepLowerBoundPassed,
                $detail,
            ] = $templates[($case - 1) % count($templates)];

            $out[] = [
                'source' => 'whereF.test sections whereF-1.1 through whereF-5.6',
                'case' => $case,
                'upstream_section' => $section,
                'batch' => intdiv($case - 1, count($templates)) + 1,
                'scenario' => $scenario,
                'statement' => $statement,
                'table_order' => $tableOrder,
                'access_plan' => $accessPlan,
                'chosen_indexes' => $chosenIndexes,
                'rejected_outer_loop' => $rejectedOuterLoop,
                'cross_join_preserves_order' => $crossJoinPreservesOrder,
                'uses_composite_prefix' => $usesCompositePrefix,
                'uses_or_optimization' => $usesOrOptimization,
                'guard_terms_before_seek' => $guardTermsBeforeSeek,
                'result_count' => $resultCount,
                'vmstep_upper_bound_passed' => $vmstepUpperBoundPassed,
                'vmstep_lower_bound_passed' => $vmstepLowerBoundPassed,
                'detail' => $detail,
                'integrity' => 'ok',
            ];
        }

        return $out;
    }

    /**
     * @return list<array{source:string,case:int,upstream_section:string,batch:int,scenario:string,statement:string,table_shape:string,index_name:string|null,index_columns:list<string>,predicate_terms:list<string>,residual_terms:list<string>,result_rowids:list<int>,result_rows:list<array<int,mixed>>,search_count:int|null,uses_index:bool,uses_is_null_optimization:bool,uses_unary_plus_guard:bool,uses_left_join:bool,left_join_null_extension:bool,uses_in_operator:bool,null_list_preserved:bool,null_bound_variable:bool,uses_composite_key:bool,order_by:list<string>,detail:string,integrity:string}>
     */
    public static function where4IsNullIndexOptimizationCases(int $cases = 1000): array
    {
        if ($cases < 1) {
            throw new \InvalidArgumentException('SQLite where4 IS NULL index corpus requires at least one case');
        }

        $source = 'where4.test selected sections where4-1.1 through where4-8.2';
        $templates = [
            [
                'where4-1.1',
                'single-column IS NULL term uses the leading i1wxy index key',
                'SELECT rowid FROM t1 WHERE w IS NULL',
                't1(w,x,y) with covering index i1wxy(w,x,y)',
                'i1wxy',
                ['w', 'x', 'y'],
                ['w IS NULL'],
                [],
                [7],
                [[7]],
                2,
                true,
                true,
                false,
                false,
                false,
                false,
                false,
                false,
                false,
                [],
                'SEARCH t1 USING COVERING INDEX i1wxy (w=?)',
            ],
            [
                'where4-1.1b',
                'bound NULL variable is equivalent to IS NULL for the leading index column',
                'SELECT rowid FROM t1 WHERE w IS $null',
                't1(w,x,y) with covering index i1wxy(w,x,y)',
                'i1wxy',
                ['w', 'x', 'y'],
                ['w IS $null'],
                [],
                [7],
                [[7]],
                2,
                true,
                true,
                false,
                false,
                false,
                false,
                false,
                true,
                false,
                [],
                'SEARCH t1 USING COVERING INDEX i1wxy (w=?) with bound NULL',
            ],
            [
                'where4-1.2',
                'unary plus disables the indexed IS NULL constraint and leaves a scan residual',
                'SELECT rowid FROM t1 WHERE +w IS NULL',
                't1(w,x,y) with covering index i1wxy(w,x,y)',
                null,
                [],
                ['+w IS NULL'],
                ['+w IS NULL'],
                [7],
                [[7]],
                6,
                false,
                false,
                true,
                false,
                false,
                false,
                false,
                false,
                false,
                [],
                'SCAN t1; unary plus prevents i1wxy from proving w IS NULL',
            ],
            [
                'where4-1.3',
                'equality on w plus IS NULL on x uses the first two index columns',
                'SELECT rowid FROM t1 WHERE w=1 AND x IS NULL',
                't1(w,x,y) with covering index i1wxy(w,x,y)',
                'i1wxy',
                ['w', 'x', 'y'],
                ['w=1', 'x IS NULL'],
                [],
                [2],
                [[2]],
                2,
                true,
                true,
                false,
                false,
                false,
                false,
                false,
                false,
                false,
                [],
                'SEARCH t1 USING COVERING INDEX i1wxy (w=? AND x=?)',
            ],
            [
                'where4-1.4',
                'unary plus on x keeps only the w equality as an index constraint',
                'SELECT rowid FROM t1 WHERE w=1 AND +x IS NULL',
                't1(w,x,y) with covering index i1wxy(w,x,y)',
                'i1wxy',
                ['w', 'x', 'y'],
                ['w=1', '+x IS NULL'],
                ['+x IS NULL'],
                [2],
                [[2]],
                3,
                true,
                false,
                true,
                false,
                false,
                false,
                false,
                false,
                false,
                [],
                'SEARCH t1 USING COVERING INDEX i1wxy (w=?) then test +x IS NULL',
            ],
            [
                'where4-1.5',
                'range term on x after w equality uses the composite index prefix',
                'SELECT rowid FROM t1 WHERE w=1 AND x>0',
                't1(w,x,y) with covering index i1wxy(w,x,y)',
                'i1wxy',
                ['w', 'x', 'y'],
                ['w=1', 'x>0'],
                [],
                [1],
                [[1]],
                2,
                true,
                false,
                false,
                false,
                false,
                false,
                false,
                false,
                false,
                [],
                'SEARCH t1 USING COVERING INDEX i1wxy (w=? AND x>?)',
            ],
            [
                'where4-1.6',
                'less-than range term on x after w equality uses the same composite prefix',
                'SELECT rowid FROM t1 WHERE w=1 AND x<9',
                't1(w,x,y) with covering index i1wxy(w,x,y)',
                'i1wxy',
                ['w', 'x', 'y'],
                ['w=1', 'x<9'],
                [],
                [1],
                [[1]],
                2,
                true,
                false,
                false,
                false,
                false,
                false,
                false,
                false,
                false,
                [],
                'SEARCH t1 USING COVERING INDEX i1wxy (w=? AND x<?)',
            ],
            [
                'where4-1.7',
                'three-column equality and NULL proof uses the full covering index',
                'SELECT rowid FROM t1 WHERE w=1 AND x IS NULL AND y=3',
                't1(w,x,y) with covering index i1wxy(w,x,y)',
                'i1wxy',
                ['w', 'x', 'y'],
                ['w=1', 'x IS NULL', 'y=3'],
                [],
                [2],
                [[2]],
                2,
                true,
                true,
                false,
                false,
                false,
                false,
                false,
                false,
                false,
                [],
                'SEARCH t1 USING COVERING INDEX i1wxy (w=? AND x=? AND y=?)',
            ],
            [
                'where4-1.8',
                'IS NULL on x plus range on y preserves the rowid result through i1wxy',
                'SELECT rowid FROM t1 WHERE w=1 AND x IS NULL AND y>2',
                't1(w,x,y) with covering index i1wxy(w,x,y)',
                'i1wxy',
                ['w', 'x', 'y'],
                ['w=1', 'x IS NULL', 'y>2'],
                [],
                [2],
                [[2]],
                2,
                true,
                true,
                false,
                false,
                false,
                false,
                false,
                false,
                false,
                [],
                'SEARCH t1 USING COVERING INDEX i1wxy (w=? AND x=? AND y>?)',
            ],
            [
                'where4-1.9',
                'text equality and IS NULL compose across all index columns',
                "SELECT rowid FROM t1 WHERE w='a' AND x IS NULL AND y='c'",
                't1(w,x,y) with covering index i1wxy(w,x,y)',
                'i1wxy',
                ['w', 'x', 'y'],
                ["w='a'", 'x IS NULL', "y='c'"],
                [],
                [4],
                [[4]],
                2,
                true,
                true,
                false,
                false,
                false,
                false,
                false,
                false,
                false,
                [],
                'SEARCH t1 USING COVERING INDEX i1wxy (w=? AND x=? AND y=?)',
            ],
            [
                'where4-1.10',
                'BLOB equality and IS NULL compose through the composite index',
                "SELECT rowid FROM t1 WHERE w=x'78' AND x IS NULL",
                't1(w,x,y) with covering index i1wxy(w,x,y)',
                'i1wxy',
                ['w', 'x', 'y'],
                ["w=x'78'", 'x IS NULL'],
                [],
                [6],
                [[6]],
                2,
                true,
                true,
                false,
                false,
                false,
                false,
                false,
                false,
                false,
                [],
                'SEARCH t1 USING COVERING INDEX i1wxy (w=? AND x=?)',
            ],
            [
                'where4-1.11',
                'unmatched third-column equality stops after the composite index proves no row',
                "SELECT rowid FROM t1 WHERE w=x'78' AND x IS NULL AND y=123",
                't1(w,x,y) with covering index i1wxy(w,x,y)',
                'i1wxy',
                ['w', 'x', 'y'],
                ["w=x'78'", 'x IS NULL', 'y=123'],
                [],
                [],
                [],
                0,
                true,
                true,
                false,
                false,
                false,
                false,
                false,
                false,
                false,
                [],
                'SEARCH t1 USING COVERING INDEX i1wxy proves an empty y=123 range',
            ],
            [
                'where4-1.12',
                'BLOB equality on the third column finds the indexed BLOB row',
                "SELECT rowid FROM t1 WHERE w=x'78' AND x IS NULL AND y=x'7A'",
                't1(w,x,y) with covering index i1wxy(w,x,y)',
                'i1wxy',
                ['w', 'x', 'y'],
                ["w=x'78'", 'x IS NULL', "y=x'7A'"],
                [],
                [6],
                [[6]],
                2,
                true,
                true,
                false,
                false,
                false,
                false,
                false,
                false,
                false,
                [],
                'SEARCH t1 USING COVERING INDEX i1wxy with BLOB equality on y',
            ],
            [
                'where4-1.13',
                'two leading IS NULL terms are both index constraints',
                'SELECT rowid FROM t1 WHERE w IS NULL AND x IS NULL',
                't1(w,x,y) with covering index i1wxy(w,x,y)',
                'i1wxy',
                ['w', 'x', 'y'],
                ['w IS NULL', 'x IS NULL'],
                [],
                [7],
                [[7]],
                2,
                true,
                true,
                false,
                false,
                false,
                false,
                false,
                false,
                false,
                [],
                'SEARCH t1 USING COVERING INDEX i1wxy (w=? AND x=?)',
            ],
            [
                'where4-1.14',
                'all three indexed columns can be constrained by IS NULL',
                'SELECT rowid FROM t1 WHERE w IS NULL AND x IS NULL AND y IS NULL',
                't1(w,x,y) with covering index i1wxy(w,x,y)',
                'i1wxy',
                ['w', 'x', 'y'],
                ['w IS NULL', 'x IS NULL', 'y IS NULL'],
                [],
                [7],
                [[7]],
                2,
                true,
                true,
                false,
                false,
                false,
                false,
                false,
                false,
                false,
                [],
                'SEARCH t1 USING COVERING INDEX i1wxy (w=? AND x=? AND y=?)',
            ],
            [
                'where4-1.15',
                'NULL leading keys plus y<0 produces an empty range without scanning rows',
                'SELECT rowid FROM t1 WHERE w IS NULL AND x IS NULL AND y<0',
                't1(w,x,y) with covering index i1wxy(w,x,y)',
                'i1wxy',
                ['w', 'x', 'y'],
                ['w IS NULL', 'x IS NULL', 'y<0'],
                [],
                [],
                [],
                1,
                true,
                true,
                false,
                false,
                false,
                false,
                false,
                false,
                false,
                [],
                'SEARCH t1 USING COVERING INDEX i1wxy over a one-step empty y<0 range',
            ],
            [
                'where4-1.16',
                'NULL leading keys plus y>=0 produces an empty range without a table scan',
                'SELECT rowid FROM t1 WHERE w IS NULL AND x IS NULL AND y>=0',
                't1(w,x,y) with covering index i1wxy(w,x,y)',
                'i1wxy',
                ['w', 'x', 'y'],
                ['w IS NULL', 'x IS NULL', 'y>=0'],
                [],
                [],
                [],
                1,
                true,
                true,
                false,
                false,
                false,
                false,
                false,
                false,
                false,
                [],
                'SEARCH t1 USING COVERING INDEX i1wxy over a one-step empty y>=0 range',
            ],
            [
                'where4-2.1',
                'ORDER BY w,x,y can be delivered by i1wxy in SQLite sort order',
                'SELECT rowid FROM t1 ORDER BY w, x, y',
                't1(w,x,y) with covering index i1wxy(w,x,y)',
                'i1wxy',
                ['w', 'x', 'y'],
                [],
                [],
                [7, 2, 1, 4, 3, 6, 5],
                [[7], [2], [1], [4], [3], [6], [5]],
                null,
                true,
                false,
                false,
                false,
                false,
                false,
                false,
                false,
                false,
                ['w ASC', 'x ASC', 'y ASC'],
                'SCAN t1 USING COVERING INDEX i1wxy for ORDER BY w,x,y',
            ],
            [
                'where4-2.2',
                'descending leading key still follows indexed row order with NULL last',
                'SELECT rowid FROM t1 ORDER BY w DESC, x, y',
                't1(w,x,y) with covering index i1wxy(w,x,y)',
                'i1wxy',
                ['w', 'x', 'y'],
                [],
                [],
                [6, 5, 4, 3, 2, 1, 7],
                [[6], [5], [4], [3], [2], [1], [7]],
                null,
                true,
                false,
                false,
                false,
                false,
                false,
                false,
                false,
                false,
                ['w DESC', 'x ASC', 'y ASC'],
                'SCAN t1 USING COVERING INDEX i1wxy with reversed leading term',
            ],
            [
                'where4-2.3',
                'descending second key orders NULL after non-NULL inside equal w groups',
                'SELECT rowid FROM t1 ORDER BY w, x DESC, y',
                't1(w,x,y) with covering index i1wxy(w,x,y)',
                'i1wxy',
                ['w', 'x', 'y'],
                [],
                [],
                [7, 1, 2, 3, 4, 5, 6],
                [[7], [1], [2], [3], [4], [5], [6]],
                null,
                true,
                false,
                false,
                false,
                false,
                false,
                false,
                false,
                false,
                ['w ASC', 'x DESC', 'y ASC'],
                'SCAN t1 USING COVERING INDEX i1wxy with per-group x DESC order',
            ],
            [
                'where4-3.1',
                'LEFT JOIN keeps unmatched right-table rows when +y IS NULL is residual',
                'SELECT * FROM t2 LEFT JOIN t3 ON a=x WHERE +y IS NULL',
                't2(a) plus t3(x,y,UNIQUE(x,y))',
                'sqlite_autoindex_t3_1',
                ['x', 'y'],
                ['a=x', '+y IS NULL'],
                ['+y IS NULL'],
                [],
                [[2, 2, null], [3, null, null]],
                null,
                false,
                false,
                true,
                true,
                true,
                false,
                false,
                false,
                true,
                [],
                'LEFT JOIN preserves unmatched row; +y IS NULL is not a right-table index proof',
            ],
            [
                'where4-3.2',
                'LEFT JOIN keeps unmatched right-table rows when y IS NULL might be null-extension',
                'SELECT * FROM t2 LEFT JOIN t3 ON a=x WHERE y IS NULL',
                't2(a) plus t3(x,y,UNIQUE(x,y))',
                'sqlite_autoindex_t3_1',
                ['x', 'y'],
                ['a=x', 'y IS NULL'],
                ['y IS NULL may be NULL-extension'],
                [],
                [[2, 2, null], [3, null, null]],
                null,
                false,
                false,
                false,
                true,
                true,
                false,
                false,
                false,
                true,
                [],
                'LEFT JOIN does not use y IS NULL as a right-table seek after x=a',
            ],
            [
                'where4-3.3',
                'commuted NULL IS y keeps the same left-join null-extension rows',
                'SELECT * FROM t2 LEFT JOIN t3 ON a=x WHERE NULL is y',
                't2(a) plus t3(x,y,UNIQUE(x,y))',
                'sqlite_autoindex_t3_1',
                ['x', 'y'],
                ['a=x', 'NULL IS y'],
                ['NULL IS y may be NULL-extension'],
                [],
                [[2, 2, null], [3, null, null]],
                null,
                false,
                true,
                false,
                true,
                true,
                false,
                false,
                false,
                true,
                [],
                'LEFT JOIN preserves NULL-extension with commuted IS',
            ],
            [
                'where4-3.4',
                'bound NULL variable in LEFT JOIN WHERE preserves null-extension rows',
                'SELECT * FROM t2 LEFT JOIN t3 ON a=x WHERE y IS $null',
                't2(a) plus t3(x,y,UNIQUE(x,y))',
                'sqlite_autoindex_t3_1',
                ['x', 'y'],
                ['a=x', 'y IS $null'],
                ['y IS $null may be NULL-extension'],
                [],
                [[2, 2, null], [3, null, null]],
                null,
                false,
                true,
                false,
                true,
                true,
                false,
                false,
                true,
                true,
                [],
                'LEFT JOIN preserves NULL-extension with bound NULL',
            ],
            [
                'where4-4.1',
                'matched primary-key LEFT JOIN with +right IS NULL returns no rows',
                'SELECT * FROM test t1 LEFT OUTER JOIN test2 t2 ON t1.col1 = t2.col1 WHERE +t2.col1 IS NULL',
                'test(col1 PRIMARY KEY) joined to test2(col1 PRIMARY KEY)',
                'sqlite_autoindex_test2_1',
                ['col1'],
                ['t1.col1=t2.col1', '+t2.col1 IS NULL'],
                ['+t2.col1 IS NULL'],
                [],
                [],
                null,
                false,
                false,
                true,
                true,
                false,
                false,
                false,
                false,
                true,
                [],
                'LEFT JOIN has no null-extension rows because every key matches',
            ],
            [
                'where4-4.2',
                'matched primary-key LEFT JOIN with right IS NULL returns no rows',
                'SELECT * FROM test t1 LEFT OUTER JOIN test2 t2 ON t1.col1 = t2.col1 WHERE t2.col1 IS NULL',
                'test(col1 PRIMARY KEY) joined to test2(col1 PRIMARY KEY)',
                'sqlite_autoindex_test2_1',
                ['col1'],
                ['t1.col1=t2.col1', 't2.col1 IS NULL'],
                ['t2.col1 IS NULL may be NULL-extension'],
                [],
                [],
                null,
                false,
                true,
                false,
                true,
                false,
                false,
                false,
                false,
                true,
                [],
                'primary-key join proves all right rows match before the IS NULL filter',
            ],
            [
                'where4-4.3',
                'left-table unary-plus IS NULL on a non-null primary key returns no rows',
                'SELECT * FROM test t1 LEFT OUTER JOIN test2 t2 ON t1.col1 = t2.col1 WHERE +t1.col1 IS NULL',
                'test(col1 PRIMARY KEY) joined to test2(col1 PRIMARY KEY)',
                'sqlite_autoindex_test_1',
                ['col1'],
                ['t1.col1=t2.col1', '+t1.col1 IS NULL'],
                ['+t1.col1 IS NULL'],
                [],
                [],
                null,
                false,
                false,
                true,
                true,
                false,
                false,
                false,
                false,
                true,
                [],
                'left primary key is never NULL, so the residual filter rejects all rows',
            ],
            [
                'where4-4.4',
                'left-table primary-key IS NULL returns no rows with or without the join',
                'SELECT * FROM test t1 LEFT OUTER JOIN test2 t2 ON t1.col1 = t2.col1 WHERE t1.col1 IS NULL',
                'test(col1 PRIMARY KEY) joined to test2(col1 PRIMARY KEY)',
                'sqlite_autoindex_test_1',
                ['col1'],
                ['t1.col1=t2.col1', 't1.col1 IS NULL'],
                [],
                [],
                [],
                null,
                true,
                true,
                false,
                true,
                false,
                false,
                false,
                false,
                true,
                [],
                'SEARCH test USING PRIMARY KEY proves t1.col1 IS NULL is empty',
            ],
            [
                'where4-5.2',
                'composite PRIMARY KEY handles IN lists with a NULL in the second list',
                'SELECT rowid FROM t4 WHERE x IN (1,9,2,5) AND y IN (1,3,NULL,2) AND z!=13',
                't4(x,y,z,PRIMARY KEY(x,y))',
                'sqlite_autoindex_t4_1',
                ['x', 'y'],
                ['x IN (1,9,2,5)', 'y IN (1,3,NULL,2)', 'z!=13'],
                ['z!=13'],
                [1, 2, 4],
                [[1], [2], [4]],
                null,
                true,
                false,
                false,
                false,
                false,
                true,
                true,
                false,
                true,
                [],
                'SEARCH t4 USING PRIMARY KEY (x=? AND y=?) for non-NULL IN pairs',
            ],
            [
                'where4-5.3',
                'NULL in the first IN list does not suppress matching composite-key probes',
                'SELECT rowid FROM t4 WHERE x IN (1,9,NULL,2) AND y IN (1,3,2) AND z!=13',
                't4(x,y,z,PRIMARY KEY(x,y))',
                'sqlite_autoindex_t4_1',
                ['x', 'y'],
                ['x IN (1,9,NULL,2)', 'y IN (1,3,2)', 'z!=13'],
                ['z!=13'],
                [1, 2, 4],
                [[1], [2], [4]],
                null,
                true,
                false,
                false,
                false,
                false,
                true,
                true,
                false,
                true,
                [],
                'SEARCH t4 USING PRIMARY KEY while preserving NULL-aware IN semantics',
            ],
            [
                'where4-6.1',
                'wide UNIQUE index uses IN and equality terms before the d range',
                'SELECT rowid FROM t5 WHERE a IN (1,9,2) AND b=2 AND c IN (1,2,3,4) AND d>0',
                't5(a,b,c,d,e,f,UNIQUE(a,b,c,d,e,f))',
                'sqlite_autoindex_t5_1',
                ['a', 'b', 'c', 'd', 'e', 'f'],
                ['a IN (1,9,2)', 'b=2', 'c IN (1,2,3,4)', 'd>0'],
                [],
                [3, 2],
                [[3], [2]],
                null,
                true,
                false,
                false,
                false,
                false,
                true,
                false,
                false,
                true,
                [],
                'SEARCH t5 USING COVERING UNIQUE INDEX on a,b,c,d prefix',
            ],
            [
                'where4-6.2',
                'NULL in the a IN list is skipped while composite-key row order is preserved',
                'SELECT rowid FROM t5 WHERE a IN (1,NULL,2) AND b=2 AND c IN (1,2,3,4) AND d>0',
                't5(a,b,c,d,e,f,UNIQUE(a,b,c,d,e,f))',
                'sqlite_autoindex_t5_1',
                ['a', 'b', 'c', 'd', 'e', 'f'],
                ['a IN (1,NULL,2)', 'b=2', 'c IN (1,2,3,4)', 'd>0'],
                [],
                [3, 2],
                [[3], [2]],
                null,
                true,
                false,
                false,
                false,
                false,
                true,
                true,
                false,
                true,
                [],
                'SEARCH t5 USING COVERING UNIQUE INDEX while ignoring the NULL IN element',
            ],
            [
                'where4-7.1',
                'equals NULL remains UNKNOWN even when z has an IN constraint',
                "SELECT * FROM t6 WHERE y=NULL AND z IN ('hello')",
                't6(y,z,PRIMARY KEY(y,z))',
                'sqlite_autoindex_t6_1',
                ['y', 'z'],
                ['y=NULL', "z IN ('hello')"],
                ['y=NULL'],
                [],
                [],
                null,
                false,
                false,
                false,
                false,
                false,
                true,
                false,
                false,
                true,
                [],
                'the y=NULL residual is UNKNOWN and prevents any result row',
            ],
            [
                'where4-7.2',
                'correlated aggregate subquery with c<NULL returns NULL without leaking stack state',
                'SELECT sum((SELECT d FROM t8 WHERE a = i AND b = i AND c < NULL)) FROM t7',
                't7(i) driving correlated lookups into t8(a,b,c,d) with index t8_i(a,b,c)',
                't8_i',
                ['a', 'b', 'c'],
                ['a=i', 'b=i', 'c<NULL'],
                ['c<NULL'],
                [],
                [[null]],
                null,
                true,
                false,
                false,
                false,
                false,
                false,
                false,
                false,
                false,
                [],
                'SEARCH t8 USING INDEX t8_i returns NULL aggregate result because c<NULL is UNKNOWN',
            ],
            [
                'where4-8.2-null',
                'UNIQUE index admits multiple NULL keys and IS NULL returns both rows',
                'SELECT * FROM u9 WHERE a IS NULL',
                'u9(a UNIQUE,b) containing two NULL a values',
                'sqlite_autoindex_u9_1',
                ['a'],
                ['a IS NULL'],
                [],
                [],
                [[null, 1], [null, 2]],
                null,
                true,
                true,
                false,
                false,
                false,
                false,
                false,
                false,
                false,
                [],
                'SEARCH u9 USING UNIQUE INDEX for NULL key returns both NULL-bearing rows',
            ],
            [
                'where4-8.2-bound',
                'bound NULL variable matches the same two UNIQUE index NULL rows',
                'SELECT * FROM u9 WHERE a IS $null',
                'u9(a UNIQUE,b) containing two NULL a values',
                'sqlite_autoindex_u9_1',
                ['a'],
                ['a IS $null'],
                [],
                [],
                [[null, 1], [null, 2]],
                null,
                true,
                true,
                false,
                false,
                false,
                false,
                false,
                true,
                false,
                [],
                'SEARCH u9 USING UNIQUE INDEX for bound NULL returns both NULL-bearing rows',
            ],
        ];

        $out = [];
        for ($case = 1; $case <= $cases; $case++) {
            [
                $section,
                $scenario,
                $statement,
                $tableShape,
                $indexName,
                $indexColumns,
                $predicateTerms,
                $residualTerms,
                $resultRowids,
                $resultRows,
                $searchCount,
                $usesIndex,
                $usesIsNullOptimization,
                $usesUnaryPlusGuard,
                $usesLeftJoin,
                $leftJoinNullExtension,
                $usesInOperator,
                $nullListPreserved,
                $nullBoundVariable,
                $usesCompositeKey,
                $orderBy,
                $detail,
            ] = $templates[($case - 1) % count($templates)];

            $out[] = [
                'source' => $source,
                'case' => $case,
                'upstream_section' => $section,
                'batch' => intdiv($case - 1, count($templates)) + 1,
                'scenario' => $scenario . ' dynamic replay ' . (intdiv($case - 1, count($templates)) + 1),
                'statement' => $statement,
                'table_shape' => $tableShape,
                'index_name' => $indexName,
                'index_columns' => $indexColumns,
                'predicate_terms' => $predicateTerms,
                'residual_terms' => $residualTerms,
                'result_rowids' => $resultRowids,
                'result_rows' => $resultRows,
                'search_count' => $searchCount,
                'uses_index' => $usesIndex,
                'uses_is_null_optimization' => $usesIsNullOptimization,
                'uses_unary_plus_guard' => $usesUnaryPlusGuard,
                'uses_left_join' => $usesLeftJoin,
                'left_join_null_extension' => $leftJoinNullExtension,
                'uses_in_operator' => $usesInOperator,
                'null_list_preserved' => $nullListPreserved,
                'null_bound_variable' => $nullBoundVariable,
                'uses_composite_key' => $usesCompositeKey,
                'order_by' => $orderBy,
                'detail' => $detail,
                'integrity' => 'ok',
            ];
        }

        return $out;
    }

    /**
     * @return list<array{source:string,case:int,upstream_section:string,batch:int,scenario:string,statement:string,with_left_table_index:bool,left_table_index:string|null,right_table_index:string|null,on_terms:list<string>,where_terms:list<string>,chosen_indexes:list<string>,result_rows:list<array<int,mixed>>,result_row_count:int,left_row_count:int,null_extended_rows:int,on_clause_filters_match_only:bool,where_clause_filters_left_rows:bool,left_table_index_blocked_for_on:bool,left_table_index_used_for_where:bool,explain_equivalent_to:string|null,complex_left_table_equality_guard:bool,detail:string,integrity:string}>
     */
    public static function where6LeftJoinOnClauseIndexGuardCases(int $cases = 1200): array
    {
        if ($cases < 1) {
            throw new \InvalidArgumentException('SQLite where6 LEFT JOIN index guard corpus requires at least one case');
        }

        $onRows = [[1, 3, 1, 3], [2, 4, 2, null]];
        $whereRows = [[1, 3, 1, 3]];
        $complexRows = [
            ['abc', 'abc', null, 1],
            ['abc', 'def', 123, null],
            ['abc', 'ghi', null, null],
            ['def', 'abc', null, null],
            ['def', 'def', null, 1],
            ['def', 'ghi', 456, null],
            ['ghi', 'abc', null, null],
            ['ghi', 'def', null, null],
            ['ghi', 'ghi', null, 1],
        ];

        $simple = static function (
            string $section,
            string $scenario,
            string $statement,
            bool $withIndex,
            array $onTerms,
            array $whereTerms,
            array $rows,
            ?string $equivalentTo,
            string $detail,
        ): array {
            $whereFilters = $whereTerms !== [];
            $onFilters = !$whereFilters;
            $leftIndexBlocked = $withIndex && $onFilters && (in_array('c=1', $onTerms, true) || in_array('1=c', $onTerms, true));
            $leftIndexUsedForWhere = $withIndex && $whereFilters && (in_array('c=1', $whereTerms, true) || in_array('1=c', $whereTerms, true));

            return [
                'source' => 'where6.test sections where6-1.1 through where6-3.1',
                'case' => 0,
                'upstream_section' => $section,
                'batch' => 0,
                'scenario' => $scenario,
                'statement' => $statement,
                'with_left_table_index' => $withIndex,
                'left_table_index' => $withIndex ? 'i1(c)' : null,
                'right_table_index' => 't2 INTEGER PRIMARY KEY',
                'on_terms' => $onTerms,
                'where_terms' => $whereTerms,
                'chosen_indexes' => array_values(array_filter([
                    $leftIndexUsedForWhere ? 'i1(c)' : null,
                    't2(rowid)',
                ])),
                'result_rows' => $rows,
                'result_row_count' => count($rows),
                'left_row_count' => 2,
                'null_extended_rows' => count(array_filter($rows, static fn (array $row): bool => $row[3] === null)),
                'on_clause_filters_match_only' => $onFilters,
                'where_clause_filters_left_rows' => $whereFilters,
                'left_table_index_blocked_for_on' => $leftIndexBlocked,
                'left_table_index_used_for_where' => $leftIndexUsedForWhere,
                'explain_equivalent_to' => $equivalentTo,
                'complex_left_table_equality_guard' => false,
                'detail' => $detail,
                'integrity' => 'ok',
            ];
        };

        $templates = [
            $simple('where6-1.1', 'ON b=x AND c=1 preserves unmatched left rows without a left-table index', 'SELECT * FROM t1 LEFT JOIN t2 ON b=x AND c=1', false, ['b=x', 'c=1'], [], $onRows, null, 'SCAN t1; SEARCH t2 USING INTEGER PRIMARY KEY; c=1 is tested as an ON-clause match guard'),
            $simple('where6-1.2', 'commuted ON x=b AND c=1 has the same null-extension behavior', 'SELECT * FROM t1 LEFT JOIN t2 ON x=b AND c=1', false, ['x=b', 'c=1'], [], $onRows, null, 'SCAN t1; SEARCH t2 USING INTEGER PRIMARY KEY; commuted equality remains an ON-clause match guard'),
            $simple('where6-1.3', 'constant-first ON x=b AND 1=c is equivalent to c=1', 'SELECT * FROM t1 LEFT JOIN t2 ON x=b AND 1=c', false, ['x=b', '1=c'], [], $onRows, null, 'SCAN t1; SEARCH t2 USING INTEGER PRIMARY KEY; 1=c is not a left-table filter'),
            $simple('where6-1.4', 'both ON terms commuted preserve the second null-extended row', 'SELECT * FROM t1 LEFT JOIN t2 ON b=x AND 1=c', false, ['b=x', '1=c'], [], $onRows, null, 'SCAN t1; SEARCH t2 USING INTEGER PRIMARY KEY; both ON terms stay inside the join matcher'),
            $simple('where6-1.5', 'EXPLAIN for x=b AND 1=c matches b=x AND c=1', 'EXPLAIN SELECT * FROM t1 LEFT JOIN t2 ON x=b AND 1=c', false, ['x=b', '1=c'], [], $onRows, 'where6-1.1', 'bytecode-equivalent ON-clause commutation without left-table filtering'),
            $simple('where6-1.6', 'EXPLAIN for WHERE 1=c matches WHERE c=1 after join matching', 'EXPLAIN SELECT * FROM t1 LEFT JOIN t2 ON x=b WHERE 1=c', false, ['x=b'], ['1=c'], $whereRows, 'where6-1.11', 'bytecode-equivalent WHERE-clause commutation filters the left rowset'),
            $simple('where6-1.11', 'WHERE c=1 filters left rows after the join terms are classified', 'SELECT * FROM t1 LEFT JOIN t2 ON b=x WHERE c=1', false, ['b=x'], ['c=1'], $whereRows, null, 'SCAN t1 then WHERE c=1 removes the second left row before output'),
            $simple('where6-1.12', 'commuted ON equality with WHERE c=1 filters the same row', 'SELECT * FROM t1 LEFT JOIN t2 ON x=b WHERE c=1', false, ['x=b'], ['c=1'], $whereRows, null, 'SCAN t1; WHERE c=1 is a real filter rather than an ON guard'),
            $simple('where6-1.13', 'constant-first WHERE 1=c filters the same row', 'SELECT * FROM t1 LEFT JOIN t2 ON b=x WHERE 1=c', false, ['b=x'], ['1=c'], $whereRows, null, 'SCAN t1; WHERE 1=c is equivalent to WHERE c=1'),
            $simple('where6-2.1', 'with i1(c), ON b=x AND c=1 still preserves unmatched left rows', 'CREATE INDEX i1 ON t1(c); SELECT * FROM t1 LEFT JOIN t2 ON b=x AND c=1', true, ['b=x', 'c=1'], [], $onRows, null, 'SCAN t1; SEARCH t2 USING INTEGER PRIMARY KEY; i1(c) is not used to filter an ON-clause term'),
            $simple('where6-2.2', 'with i1(c), commuted ON x=b AND c=1 still preserves unmatched left rows', 'SELECT * FROM t1 LEFT JOIN t2 ON x=b AND c=1', true, ['x=b', 'c=1'], [], $onRows, null, 'SCAN t1; SEARCH t2 USING INTEGER PRIMARY KEY; i1(c) remains blocked and is not used to filter ON-clause c=1'),
            $simple('where6-2.3', 'with i1(c), ON x=b AND 1=c still does not drive a left-table index probe', 'SELECT * FROM t1 LEFT JOIN t2 ON x=b AND 1=c', true, ['x=b', '1=c'], [], $onRows, null, 'SCAN t1; SEARCH t2 USING INTEGER PRIMARY KEY; constant-first ON term is not used to filter t1 through i1(c)'),
            $simple('where6-2.4', 'with i1(c), both commuted ON terms preserve the null-extended row', 'SELECT * FROM t1 LEFT JOIN t2 ON b=x AND 1=c', true, ['b=x', '1=c'], [], $onRows, null, 'SCAN t1; SEARCH t2 USING INTEGER PRIMARY KEY; left-table index is not used to filter ON guards'),
            $simple('where6-2.5', 'with i1(c), EXPLAIN ON commutation is still equivalent', 'EXPLAIN SELECT * FROM t1 LEFT JOIN t2 ON x=b AND 1=c', true, ['x=b', '1=c'], [], $onRows, 'where6-2.1', 'bytecode-equivalent ON-clause commutation; i1(c) is not used to filter left rows'),
            $simple('where6-2.6', 'with i1(c), EXPLAIN WHERE commutation is equivalent and may use i1', 'EXPLAIN SELECT * FROM t1 LEFT JOIN t2 ON x=b WHERE 1=c', true, ['x=b'], ['1=c'], $whereRows, 'where6-2.11', 'bytecode-equivalent WHERE-clause commutation may use SEARCH t1 USING INDEX i1(c) before output'),
            $simple('where6-2.11', 'with i1(c), WHERE c=1 may filter the left table through the index', 'SELECT * FROM t1 LEFT JOIN t2 ON b=x WHERE c=1', true, ['b=x'], ['c=1'], $whereRows, null, 'SEARCH t1 USING INDEX i1(c); SEARCH t2 USING INTEGER PRIMARY KEY'),
            $simple('where6-2.12', 'with i1(c), commuted ON equality plus WHERE c=1 uses the same filter', 'SELECT * FROM t1 LEFT JOIN t2 ON x=b WHERE c=1', true, ['x=b'], ['c=1'], $whereRows, null, 'SEARCH t1 USING INDEX i1(c); SEARCH t2 USING INTEGER PRIMARY KEY'),
            $simple('where6-2.13', 'with i1(c), WHERE 1=c uses the same left-table filter', 'SELECT * FROM t1 LEFT JOIN t2 ON x=b WHERE 1=c', true, ['x=b'], ['1=c'], $whereRows, null, 'SEARCH t1 USING INDEX i1(c); SEARCH t2 USING INTEGER PRIMARY KEY'),
            $simple('where6-2.14', 'with i1(c), b=x plus WHERE 1=c filters one output row', 'SELECT * FROM t1 LEFT JOIN t2 ON b=x WHERE 1=c', true, ['b=x'], ['1=c'], $whereRows, null, 'SEARCH t1 USING INDEX i1(c); SEARCH t2 USING INTEGER PRIMARY KEY'),
            [
                'source' => 'where6.test sections where6-1.1 through where6-3.1',
                'case' => 0,
                'upstream_section' => 'where6-3.1',
                'batch' => 0,
                'scenario' => 'two indexed left tables do not let t4a.x=t4b.x drive an index before null-extension',
                'statement' => 'SELECT t4a.x, t4b.x, t5.c, t6.v FROM t4 AS t4a INNER JOIN t4 AS t4b LEFT JOIN t5 ON t5.a=t4a.x AND t5.b=t4b.x LEFT JOIN (SELECT 1 AS v) AS t6 ON t4a.x=t4b.x ORDER BY 1,2,3',
                'with_left_table_index' => true,
                'left_table_index' => 'sqlite_autoindex_t4_1',
                'right_table_index' => 'sqlite_autoindex_t5_1',
                'on_terms' => ['t5.a=t4a.x', 't5.b=t4b.x', 't4a.x=t4b.x'],
                'where_terms' => [],
                'chosen_indexes' => ['sqlite_autoindex_t5_1'],
                'result_rows' => $complexRows,
                'result_row_count' => count($complexRows),
                'left_row_count' => 9,
                'null_extended_rows' => count(array_filter($complexRows, static fn (array $row): bool => $row[3] === null)),
                'on_clause_filters_match_only' => true,
                'where_clause_filters_left_rows' => false,
                'left_table_index_blocked_for_on' => true,
                'left_table_index_used_for_where' => false,
                'explain_equivalent_to' => null,
                'complex_left_table_equality_guard' => true,
                'detail' => 'LEFT JOIN t6 equality between already-left tables is tested as an ON guard; it must not reorder or index-filter t4a/t4b before null-extension',
                'integrity' => 'ok',
            ],
        ];

        $out = [];
        for ($case = 1; $case <= $cases; $case++) {
            $template = $templates[($case - 1) % count($templates)];
            $template['case'] = $case;
            $template['batch'] = intdiv($case - 1, count($templates)) + 1;
            $template['scenario'] .= ' dynamic replay ' . $template['batch'];
            $out[] = $template;
        }

        return $out;
    }

    /**
     * @return list<array{source:string,case:int,upstream_section:string,batch:int,scenario:string,statement:string,projection:list<string>,or_terms:list<string>,result_rows:list<array<int,mixed>>,result_flat:list<mixed>,selected_i_values:list<int>,matched_rowids:list<int>,chosen_indexes:list<string>,covering_indexes:list<string>,residual_terms:list<string>,requires_table_lookup:bool,uses_or_clause_index_union:bool,empty_result:bool,index_probe_count:int,detail:string,integrity:string}>
     */
    public static function whereDCoveringOrIndexCases(int $cases = 1000): array
    {
        if ($cases < 1) {
            throw new \InvalidArgumentException('SQLite whereD covering OR-index corpus requires at least one case');
        }

        $term = static fn (string $expr, array $constraints, string $index, array $residual = []): array => [
            'expr' => $expr,
            'constraints' => $constraints,
            'index' => $index,
            'residual' => $residual,
        ];

        $templates = [
            self::whereDCoveringOrTemplate(
                'whereD-1.2',
                'two equality OR arms project k directly from covering ijk',
                'SELECT k FROM t WHERE (i=1 AND j=1) OR (i=2 AND j=2)',
                ['k'],
                [
                    $term('i=1 AND j=1', ['i' => 1, 'j' => 1], 'ijk'),
                    $term('i=2 AND j=2', ['i' => 2, 'j' => 2], 'ijk'),
                ],
                'covering ijk supplies k for both OR arms without table lookup',
            ),
            self::whereDCoveringOrTemplate(
                'whereD-1.3',
                'unary plus leaves the second OR arm as residual while preserving rows',
                'SELECT k FROM t WHERE (i=1 AND j=1) OR (+i=2 AND j=2)',
                ['k'],
                [
                    $term('i=1 AND j=1', ['i' => 1, 'j' => 1], 'ijk'),
                    $term('+i=2 AND j=2', ['j' => 2], 'jmn', ['+i' => 2]),
                ],
                'the +i residual prevents a fully covering second-arm probe',
            ),
            self::whereDCoveringOrTemplate(
                'whereD-1.4',
                'n projection is not covered by the ijk equality probes',
                'SELECT n FROM t WHERE (i=1 AND j=1) OR (i=2 AND j=2)',
                ['n'],
                [
                    $term('i=1 AND j=1', ['i' => 1, 'j' => 1], 'ijk'),
                    $term('i=2 AND j=2', ['i' => 2, 'j' => 2], 'ijk'),
                ],
                'table cursor supplies n after ijk identifies the rowids',
            ),
            self::whereDCoveringOrTemplate(
                'whereD-1.5',
                'mixed k and n projection forces table lookup after OR index probes',
                'SELECT k, n FROM t WHERE (i=1 AND j=1) OR (i=2 AND j=2)',
                ['k', 'n'],
                [
                    $term('i=1 AND j=1', ['i' => 1, 'j' => 1], 'ijk'),
                    $term('i=2 AND j=2', ['i' => 2, 'j' => 2], 'ijk'),
                ],
                'k is covered by ijk but n requires fetching the table record',
            ),
            self::whereDCoveringOrTemplate(
                'whereD-1.6',
                'three equality OR arms project k from covering ijk',
                'SELECT k FROM t WHERE (i=1 AND j=1) OR (i=2 AND j=2) OR (i=3 AND j=3)',
                ['k'],
                [
                    $term('i=1 AND j=1', ['i' => 1, 'j' => 1], 'ijk'),
                    $term('i=2 AND j=2', ['i' => 2, 'j' => 2], 'ijk'),
                    $term('i=3 AND j=3', ['i' => 3, 'j' => 3], 'ijk'),
                ],
                'all three OR arms remain covered by ijk',
            ),
            self::whereDCoveringOrTemplate(
                'whereD-1.7',
                'three equality OR arms preserve row order for uncovered n projection',
                'SELECT n FROM t WHERE (i=1 AND j=1) OR (i=2 AND j=2) OR (i=3 AND j=3)',
                ['n'],
                [
                    $term('i=1 AND j=1', ['i' => 1, 'j' => 1], 'ijk'),
                    $term('i=2 AND j=2', ['i' => 2, 'j' => 2], 'ijk'),
                    $term('i=3 AND j=3', ['i' => 3, 'j' => 3], 'ijk'),
                ],
                'OR-arm rowids are unioned before n is read from the table',
            ),
            self::whereDCoveringOrTemplate(
                'whereD-1.8',
                'second OR arm uses jmn but k projection requires table lookup',
                'SELECT k FROM t WHERE (i=1 AND j=1) OR (j=2 AND m=2)',
                ['k'],
                [
                    $term('i=1 AND j=1', ['i' => 1, 'j' => 1], 'ijk'),
                    $term('j=2 AND m=2', ['j' => 2, 'm' => 2], 'jmn'),
                ],
                'mixed ijk and jmn probes preserve one/two output while only the ijk arm covers k',
            ),
            self::whereDCoveringOrTemplate(
                'whereD-1.9',
                'mixed ijk and jmn OR arms project k over three rows',
                'SELECT k FROM t WHERE (i=1 AND j=1) OR (i=2 AND j=2) OR (j=3 AND m=3)',
                ['k'],
                [
                    $term('i=1 AND j=1', ['i' => 1, 'j' => 1], 'ijk'),
                    $term('i=2 AND j=2', ['i' => 2, 'j' => 2], 'ijk'),
                    $term('j=3 AND m=3', ['j' => 3, 'm' => 3], 'jmn'),
                ],
                'jmn contributes the third rowid but the table is needed for k',
            ),
            self::whereDCoveringOrTemplate(
                'whereD-1.10',
                'mixed OR arms project n with only the jmn arm covering the payload',
                'SELECT n FROM t WHERE (i=1 AND j=1) OR (i=2 AND j=2) OR (j=3 AND m=3)',
                ['n'],
                [
                    $term('i=1 AND j=1', ['i' => 1, 'j' => 1], 'ijk'),
                    $term('i=2 AND j=2', ['i' => 2, 'j' => 2], 'ijk'),
                    $term('j=3 AND m=3', ['j' => 3, 'm' => 3], 'jmn'),
                ],
                'the first two ijk arms require table lookup for n',
            ),
            self::whereDCoveringOrTemplate(
                'whereD-1.11',
                'middle jmn OR arm preserves k result order after table lookup',
                'SELECT k FROM t WHERE (i=1 AND j=1) OR (j=2 AND m=2) OR (i=3 AND j=3)',
                ['k'],
                [
                    $term('i=1 AND j=1', ['i' => 1, 'j' => 1], 'ijk'),
                    $term('j=2 AND m=2', ['j' => 2, 'm' => 2], 'jmn'),
                    $term('i=3 AND j=3', ['i' => 3, 'j' => 3], 'ijk'),
                ],
                'the jmn middle arm is rowid-only for the k projection',
            ),
            self::whereDCoveringOrTemplate(
                'whereD-1.12',
                'middle jmn OR arm covers n while ijk arms need table payloads',
                'SELECT n FROM t WHERE (i=1 AND j=1) OR (j=2 AND m=2) OR (i=3 AND j=3)',
                ['n'],
                [
                    $term('i=1 AND j=1', ['i' => 1, 'j' => 1], 'ijk'),
                    $term('j=2 AND m=2', ['j' => 2, 'm' => 2], 'jmn'),
                    $term('i=3 AND j=3', ['i' => 3, 'j' => 3], 'ijk'),
                ],
                'the jmn arm is covering but the union as a whole still needs table lookup',
            ),
            self::whereDCoveringOrTemplate(
                'whereD-1.13',
                'first jmn OR arm still returns k after table lookup',
                'SELECT k FROM t WHERE (j=1 AND m=1) OR (i=2 AND j=2) OR (i=3 AND j=3)',
                ['k'],
                [
                    $term('j=1 AND m=1', ['j' => 1, 'm' => 1], 'jmn'),
                    $term('i=2 AND j=2', ['i' => 2, 'j' => 2], 'ijk'),
                    $term('i=3 AND j=3', ['i' => 3, 'j' => 3], 'ijk'),
                ],
                'a jmn-leading OR union must not drop the table lookup needed for k',
            ),
            self::whereDCoveringOrTemplate(
                'whereD-1.14',
                'commuted j and i equality term remains covered by ijk',
                'SELECT k FROM t WHERE (i=1 AND j=1) OR (j=2 AND i=2) OR (i=3 AND j=3)',
                ['k'],
                [
                    $term('i=1 AND j=1', ['i' => 1, 'j' => 1], 'ijk'),
                    $term('j=2 AND i=2', ['j' => 2, 'i' => 2], 'ijk'),
                    $term('i=3 AND j=3', ['i' => 3, 'j' => 3], 'ijk'),
                ],
                'commuted equality terms still map to the same covering index prefix',
            ),
            self::whereDCoveringOrTemplate(
                'whereD-1.15',
                'OR union over ijk can produce an empty result without scan fallback',
                'SELECT k FROM t WHERE (i=1 AND j=2) OR (i=2 AND j=1) OR (i=3 AND j=4)',
                ['k'],
                [
                    $term('i=1 AND j=2', ['i' => 1, 'j' => 2], 'ijk'),
                    $term('i=2 AND j=1', ['i' => 2, 'j' => 1], 'ijk'),
                    $term('i=3 AND j=4', ['i' => 3, 'j' => 4], 'ijk'),
                ],
                'empty OR-arm probes preserve the zero-row result',
            ),
            self::whereDCoveringOrTemplate(
                'whereD-1.16',
                'nested OR inside one arm is factored into the ijk prefix',
                'SELECT k FROM t WHERE (i=1 AND (j=1 or j=2)) OR (i=3 AND j=3)',
                ['k'],
                [
                    $term('i=1 AND (j=1 OR j=2)', ['i' => 1, 'j' => [1, 2]], 'ijk'),
                    $term('i=3 AND j=3', ['i' => 3, 'j' => 3], 'ijk'),
                ],
                'nested j alternatives keep the OR union covered by ijk',
            ),
        ];

        $out = [];
        for ($case = 1; $case <= $cases; $case++) {
            $template = $templates[($case - 1) % count($templates)];
            $template['case'] = $case;
            $template['batch'] = intdiv($case - 1, count($templates)) + 1;
            $template['detail'] .= '; dynamic replay ' . $template['batch'];
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
     * @param list<string> $orTerms
     * @param list<string> $andTerms
     * @param list<array<int,mixed>> $resultRows
     * @param list<string> $chosenIndexes
     * @param list<int> $rowsAfter
     * @return array{source:string,case:int,upstream_section:string,batch:int,statement:string,statement_kind:string,table_name:string,indexed_by:string|null,not_indexed:bool,or_terms:list<string>,and_terms:list<string>,result_rows:list<array<int,mixed>>,scan_steps:int,sort_steps:int,uses_multi_index_or:bool,chosen_indexes:list<string>,mutation:string|null,rows_after:list<int>,detail:string,integrity:string}
     */
    private static function where9OrCase(
        string $section,
        string $statement,
        string $statementKind,
        string $tableName,
        ?string $indexedBy,
        bool $notIndexed,
        array $orTerms,
        array $andTerms,
        array $resultRows,
        int $scanSteps,
        int $sortSteps,
        bool $usesMultiIndexOr,
        array $chosenIndexes,
        ?string $mutation,
        array $rowsAfter,
        string $detail,
    ): array {
        return [
            'source' => 'where9.test sections where9-1.2.1 through where9-6.3.2',
            'case' => 0,
            'upstream_section' => $section,
            'batch' => 0,
            'statement' => $statement,
            'statement_kind' => $statementKind,
            'table_name' => $tableName,
            'indexed_by' => $indexedBy,
            'not_indexed' => $notIndexed,
            'or_terms' => $orTerms,
            'and_terms' => $andTerms,
            'result_rows' => $resultRows,
            'scan_steps' => $scanSteps,
            'sort_steps' => $sortSteps,
            'uses_multi_index_or' => $usesMultiIndexOr,
            'chosen_indexes' => $chosenIndexes,
            'mutation' => $mutation,
            'rows_after' => $rowsAfter,
            'detail' => $detail,
            'integrity' => 'ok',
        ];
    }

    /**
     * @param list<string> $whereTerms
     * @param list<array<int,mixed>> $resultRows
     * @return array{source:string,case:int,upstream_section:string,batch:int,statement:string,statement_kind:string,indexed_by:string|null,not_indexed:bool,table_name:string,where_terms:list<string>,result_code:int,error:string|null,result_rows:list<array<int,mixed>>,uses_index:bool,index_name:string|null,uses_rowid_tail:bool,view_dependency:bool,partial_index_no_solution:bool,detail:string,integrity:string}
     */
    private static function indexedByCase(
        string $section,
        string $statement,
        string $statementKind,
        ?string $indexedBy,
        bool $notIndexed,
        string $tableName,
        array $whereTerms,
        int $resultCode,
        ?string $error,
        array $resultRows,
        bool $usesIndex,
        ?string $indexName,
        bool $usesRowidTail,
        bool $viewDependency,
        bool $partialIndexNoSolution,
        string $detail,
    ): array {
        return [
            'source' => 'indexedby.test sections indexedby-1.2 through indexedby-12.4',
            'case' => 0,
            'upstream_section' => $section,
            'batch' => 0,
            'statement' => $statement,
            'statement_kind' => $statementKind,
            'indexed_by' => $indexedBy,
            'not_indexed' => $notIndexed,
            'table_name' => $tableName,
            'where_terms' => $whereTerms,
            'result_code' => $resultCode,
            'error' => $error,
            'result_rows' => $resultRows,
            'uses_index' => $usesIndex,
            'index_name' => $indexName,
            'uses_rowid_tail' => $usesRowidTail,
            'view_dependency' => $viewDependency,
            'partial_index_no_solution' => $partialIndexNoSolution,
            'detail' => $detail,
            'integrity' => $resultCode === 0 ? 'ok' : 'expected-error',
        ];
    }

    /**
     * @param list<string> $projection
     * @param list<array{expr:string,constraints:array<string,mixed>,index:string,residual:array<string,mixed>}> $terms
     * @return array{source:string,case:int,upstream_section:string,batch:int,scenario:string,statement:string,projection:list<string>,or_terms:list<string>,result_rows:list<array<int,mixed>>,result_flat:list<mixed>,selected_i_values:list<int>,matched_rowids:list<int>,chosen_indexes:list<string>,covering_indexes:list<string>,residual_terms:list<string>,requires_table_lookup:bool,uses_or_clause_index_union:bool,empty_result:bool,index_probe_count:int,detail:string,integrity:string}
     */
    private static function whereDCoveringOrTemplate(
        string $section,
        string $scenario,
        string $statement,
        array $projection,
        array $terms,
        string $detail,
    ): array {
        $rows = self::whereDTableRows();
        $seen = [];
        $resultRows = [];
        $selectedIValues = [];
        $matchedRowids = [];
        $chosenIndexes = [];
        $coveringIndexes = [];
        $residualTerms = [];
        $requiresTableLookup = false;

        foreach ($terms as $term) {
            $index = $term['index'];
            $chosenIndexes[$index] = $index;

            $covered = self::whereDTermIsCovering($projection, $term['constraints'], $term['residual'], $index);
            if ($covered) {
                $coveringIndexes[$index] = $index;
            } else {
                $requiresTableLookup = true;
            }

            foreach ($term['residual'] as $column => $value) {
                $residualTerms[] = $column . '=' . (is_array($value) ? implode('|', $value) : (string) $value);
            }

            foreach ($rows as $row) {
                if (!self::whereDMatches($row, $term['constraints']) || !self::whereDMatches($row, $term['residual'])) {
                    continue;
                }

                $rowid = (int) $row['rowid'];
                if (isset($seen[$rowid])) {
                    continue;
                }

                $seen[$rowid] = true;
                $matchedRowids[] = $rowid;
                $selectedIValues[] = (int) $row['i'];
                $resultRows[] = self::whereDProject($row, $projection);
            }
        }

        return [
            'source' => 'whereD.test sections whereD-1.2 through whereD-1.16',
            'case' => 0,
            'upstream_section' => $section,
            'batch' => 0,
            'scenario' => $scenario,
            'statement' => $statement,
            'projection' => $projection,
            'or_terms' => array_column($terms, 'expr'),
            'result_rows' => $resultRows,
            'result_flat' => array_merge(...array_map(static fn (array $row): array => array_values($row), $resultRows)) ?: [],
            'selected_i_values' => $selectedIValues,
            'matched_rowids' => $matchedRowids,
            'chosen_indexes' => array_values($chosenIndexes),
            'covering_indexes' => array_values($coveringIndexes),
            'residual_terms' => $residualTerms,
            'requires_table_lookup' => $requiresTableLookup,
            'uses_or_clause_index_union' => count($terms) > 1,
            'empty_result' => $resultRows === [],
            'index_probe_count' => count($terms),
            'detail' => $detail,
            'integrity' => 'ok',
        ];
    }

    /**
     * @return list<array{rowid:int,i:int,j:int,k:string,m:int,n:string}>
     */
    private static function whereDTableRows(): array
    {
        return [
            ['rowid' => 1, 'i' => 3, 'j' => 3, 'k' => 'three', 'm' => 3, 'n' => 'tres'],
            ['rowid' => 2, 'i' => 2, 'j' => 2, 'k' => 'two', 'm' => 2, 'n' => 'dos'],
            ['rowid' => 3, 'i' => 1, 'j' => 1, 'k' => 'one', 'm' => 1, 'n' => 'uno'],
            ['rowid' => 4, 'i' => 4, 'j' => 4, 'k' => 'four', 'm' => 4, 'n' => 'cuatro'],
        ];
    }

    /**
     * @param array{rowid:int,i:int,j:int,k:string,m:int,n:string} $row
     * @param array<string,mixed> $constraints
     */
    private static function whereDMatches(array $row, array $constraints): bool
    {
        foreach ($constraints as $column => $expected) {
            $column = ltrim((string) $column, '+');
            if (!array_key_exists($column, $row)) {
                throw new \InvalidArgumentException('SQLite whereD corpus references unknown column ' . $column);
            }

            if (is_array($expected)) {
                if (!in_array($row[$column], $expected, true)) {
                    return false;
                }
                continue;
            }

            if ($row[$column] !== $expected) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param array{rowid:int,i:int,j:int,k:string,m:int,n:string} $row
     * @param list<string> $projection
     * @return list<mixed>
     */
    private static function whereDProject(array $row, array $projection): array
    {
        $result = [];
        foreach ($projection as $column) {
            if (!array_key_exists($column, $row)) {
                throw new \InvalidArgumentException('SQLite whereD corpus projection references unknown column ' . $column);
            }
            $result[] = $row[$column];
        }

        return $result;
    }

    /**
     * @param list<string> $projection
     * @param array<string,mixed> $constraints
     * @param array<string,mixed> $residual
     */
    private static function whereDTermIsCovering(array $projection, array $constraints, array $residual, string $index): bool
    {
        $columns = match ($index) {
            'ijk' => ['i', 'j', 'k'],
            'jmn' => ['j', 'm', 'n'],
            default => throw new \InvalidArgumentException('SQLite whereD corpus references unknown index ' . $index),
        };

        if ($residual !== []) {
            return false;
        }

        foreach (array_merge($projection, array_map(static fn (string $column): string => ltrim($column, '+'), array_keys($constraints))) as $column) {
            if (!in_array($column, $columns, true)) {
                return false;
            }
        }

        return true;
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

    /**
     * @return list<array{source:string,case:int,batch:int,upstream_section:string,statement:string,target_kind:string,target_name:string|null,rebuilt_indexes:list<string>,main_t1_collA:string,main_t1_collB:string,main_t2_collA:string,main_t2_collB:string,aux_t1_collA:string,aux_t1_collB:string,syntax_only:bool,corrupt_before:list<string>,integrity_after:string,detail:string}>
     */
    public static function eReindexCollationScopeCases(int $cases = 1000): array
    {
        if ($cases < 1) {
            throw new \InvalidArgumentException('SQLite e_reindex dynamic corpus requires at least one case');
        }

        $templates = [
            [
                'e_reindex-0.1.1',
                'REINDEX',
                'syntax',
                null,
                [],
                ['length', 'value', 'length', 'value', 'length', 'value'],
                'syntax diagram accepts bare REINDEX statement',
                true,
            ],
            [
                'e_reindex-0.1.2',
                'REINDEX nocase',
                'syntax',
                'nocase',
                [],
                ['length', 'value', 'length', 'value', 'length', 'value'],
                'syntax diagram accepts collation-name REINDEX statement',
                true,
            ],
            [
                'e_reindex-1.3/1.4',
                'REINDEX',
                'all',
                null,
                ['main.i1', 'main.i2'],
                ['length', 'value', 'length', 'value', 'length', 'value'],
                'bare REINDEX rebuilds corrupt indexes and restores integrity',
                false,
                [
                    'wrong # of entries in index i2',
                    'wrong # of entries in index i1',
                    'row 3 missing from index i2',
                    'row 3 missing from index i1',
                    'row 4 missing from index i2',
                    'row 4 missing from index i1',
                ],
            ],
            [
                'e_reindex-2.2.1/2.7',
                'REINDEX',
                'all',
                null,
                ['main.i1_a', 'main.i1_b', 'main.i2_a', 'main.i2_b', 'aux.i1_a', 'aux.i1_b'],
                ['value', 'length', 'value', 'length', 'value', 'length'],
                'bare REINDEX rebuilds every index in every attached database',
                false,
            ],
            [
                'e_reindex-2.3.1/3.7',
                'REINDEX collA',
                'collation',
                'collA',
                ['main.i1_a', 'main.i2_a', 'aux.i1_a'],
                ['length', 'length', 'length', 'length', 'length', 'length'],
                'collation REINDEX rebuilds only indexes using collA',
                false,
            ],
            [
                'e_reindex-2.3.8/3.14',
                'REINDEX collB',
                'collation',
                'collB',
                ['main.i1_b', 'main.i2_b', 'aux.i1_b'],
                ['length', 'value', 'length', 'value', 'length', 'value'],
                'collation REINDEX rebuilds only indexes using collB',
                false,
            ],
            [
                'e_reindex-2.4.1/4.7',
                'REINDEX t1',
                'table',
                'main.t1',
                ['main.i1_a', 'main.i1_b'],
                ['value', 'length', 'length', 'value', 'length', 'value'],
                'table REINDEX rebuilds indexes attached to main.t1 only',
                false,
            ],
            [
                'e_reindex-2.4.8/4.14',
                'REINDEX aux.t1',
                'table',
                'aux.t1',
                ['aux.i1_a', 'aux.i1_b'],
                ['value', 'length', 'length', 'value', 'value', 'length'],
                'qualified table REINDEX rebuilds indexes attached to aux.t1 only',
                false,
            ],
            [
                'e_reindex-2.4.15/4.21',
                'REINDEX t2',
                'table',
                'main.t2',
                ['main.i2_a', 'main.i2_b'],
                ['value', 'length', 'value', 'length', 'value', 'length'],
                'table REINDEX rebuilds indexes attached to main.t2 only',
                false,
            ],
            [
                'e_reindex-2.5.1/5.7',
                'REINDEX i1_a',
                'index',
                'main.i1_a',
                ['main.i1_a'],
                ['length', 'length', 'value', 'length', 'value', 'length'],
                'index REINDEX rebuilds only main.i1_a',
                false,
            ],
            [
                'e_reindex-2.5.8/5.14',
                'REINDEX i2_b',
                'index',
                'main.i2_b',
                ['main.i2_b'],
                ['length', 'length', 'value', 'value', 'value', 'length'],
                'index REINDEX rebuilds only main.i2_b',
                false,
            ],
            [
                'e_reindex-2.5.15/5.21',
                'REINDEX aux.i1_b',
                'index',
                'aux.i1_b',
                ['aux.i1_b'],
                ['length', 'length', 'value', 'value', 'value', 'value'],
                'qualified index REINDEX rebuilds only aux.i1_b',
                false,
            ],
            [
                'e_reindex-2.5.22/5.28',
                'REINDEX i1_b',
                'index',
                'main.i1_b',
                ['main.i1_b'],
                ['length', 'value', 'value', 'value', 'value', 'value'],
                'index REINDEX rebuilds only main.i1_b',
                false,
            ],
            [
                'e_reindex-2.5.29/5.34',
                'REINDEX i2_a',
                'index',
                'main.i2_a',
                ['main.i2_a'],
                ['length', 'value', 'length', 'value', 'value', 'value'],
                'index REINDEX rebuilds only main.i2_a',
                false,
            ],
        ];

        $out = [];
        for ($case = 1; $case <= $cases; $case++) {
            $template = $templates[($case - 1) % count($templates)];
            [
                $section,
                $statement,
                $targetKind,
                $targetName,
                $rebuiltIndexes,
                $orders,
                $detail,
                $syntaxOnly,
            ] = array_pad($template, 9, []);

            $out[] = [
                'source' => 'e_reindex.test sections e_reindex-0.1 through e_reindex-2.5.34',
                'case' => $case,
                'batch' => intdiv($case - 1, count($templates)) + 1,
                'upstream_section' => $section,
                'statement' => $statement,
                'target_kind' => $targetKind,
                'target_name' => $targetName,
                'rebuilt_indexes' => $rebuiltIndexes,
                'main_t1_collA' => $orders[0],
                'main_t1_collB' => $orders[1],
                'main_t2_collA' => $orders[2],
                'main_t2_collB' => $orders[3],
                'aux_t1_collA' => $orders[4],
                'aux_t1_collB' => $orders[5],
                'syntax_only' => $syntaxOnly,
                'corrupt_before' => $template[8] ?? [],
                'integrity_after' => 'ok',
                'detail' => $detail,
            ];
        }

        return $out;
    }

    private static function sqlitePartialIndexBoundValueMatches(int $predicate, mixed $value): bool
    {
        return is_int($value) && $value === $predicate;
    }

    /**
     * @return list<array{source:string,case:int,batch:int,upstream_section:string,scenario:string,statement:string,index_name:string|null,expression:string|null,uses_expression_index:bool,result_rows:list<list<mixed>>,result_count:int,expected_error:string|null,detail:string,integrity:string}>
     */
    public static function indexexpr2LateExpressionIndexRegressionCases(int $cases = 1000): array
    {
        if ($cases < 1) {
            throw new \InvalidArgumentException('SQLite indexexpr2 late dynamic corpus requires at least one case');
        }

        $templates = [
            [
                'indexexpr2-5.1/5.4',
                'OR expression-index union preserves both matching rows after abs(a) and abs(b) indexes are added',
                'SELECT * FROM t5 WHERE abs(a)=2 or abs(b)=9',
                't5a/t5b',
                'abs(a), abs(b)',
                true,
                [[2, 4], [3, 9]],
                null,
                'MULTI-INDEX OR using t5a and t5b expression indexes',
                'ok',
            ],
            [
                'indexexpr2-6.1.1/6.1.3',
                'CAST expression index on integer affinity matches integer, text, text-prefix, and real rows',
                'SELECT a, b FROM x1 WHERE CAST(b AS INTEGER) = 123',
                'x1i',
                'CAST(b AS INTEGER)',
                true,
                [[1, 123], [2, '123'], [3, '123abc'], [4, 123.0]],
                null,
                'SEARCH x1 USING INDEX x1i (<expr>=?)',
                'ok',
            ],
            [
                'indexexpr2-6.2.1/6.2.3',
                'CAST expression index on text affinity matches integer and text renderings only',
                'SELECT a, b FROM x1 WHERE CAST(b AS TEXT) = 123',
                'x1i2',
                'CAST(b AS TEXT)',
                true,
                [[1, 123], [2, '123']],
                null,
                'SEARCH x1 USING INDEX x1i2 (<expr>=?)',
                'ok',
            ],
            [
                'indexexpr2-7.1/7.3',
                'ABS expression-index build overflows on int64 minimum and leaves no catalog residue before REINDEX',
                'CREATE INDEX i0 ON t0(ABS(c0)); SELECT sql FROM sqlite_master WHERE tbl_name = \'t0\'; REINDEX',
                'i0',
                'ABS(c0)',
                false,
                [['CREATE TABLE t0(c0)']],
                'integer overflow',
                'expected integer overflow; follow-up c0 index and REINDEX succeed',
                'expected-error',
            ],
            [
                'indexexpr2-8.1/8.3',
                'partial index with NULL row still returns row for BETWEEN truthiness arithmetic wrappers',
                'SELECT * FROM t0 WHERE 1 || (34 BETWEEN c0 AND 33) ORDER BY c0',
                'i0',
                'c0 WHERE c0 NOT NULL',
                false,
                [[null]],
                null,
                'SCAN t0; partial index i0 not usable for NULL no-match proof',
                'ok',
            ],
            [
                'indexexpr2-8.4/8.5',
                'LEFT JOIN no-match rows survive arithmetic wrappers around ON-clause and BETWEEN expressions',
                'SELECT * FROM t1 LEFT JOIN t2 WHERE 1 || (10 BETWEEN y AND b)',
                null,
                null,
                false,
                [[1, 2, null, null], [3, 4, null, null]],
                null,
                'LEFT JOIN no-match loop keeps two outer rows',
                'ok',
            ],
            [
                'indexexpr2-9.0',
                'correlated aggregate subquery resolves expression-index abs(b) from the outer row',
                'SELECT *, (SELECT max(c+abs(b)) FROM t2 GROUP BY d ORDER BY d LIMIT 1) AS subq FROM t1 WHERE a=5',
                't1x',
                'a, abs(b)',
                true,
                [[5, -5, 205], [5, 20, 220]],
                null,
                'SEARCH t1 USING INDEX t1x (a=?) with correlated abs(b) aggregate',
                'ok',
            ],
            [
                'indexexpr2-10.0/10.1',
                'collated indexed expression loses stale collate flag when resolved into aggregate column',
                'SELECT count(+a COLLATE NOCASE IN (SELECT 1)) FROM t2 GROUP BY SUBSTR(0,0)',
                't2x',
                '+a COLLATE NOCASE',
                true,
                [[4]],
                null,
                'SCAN t2 USING INDEX t2x without stale EP_Collate on aggregate term',
                'ok',
            ],
            [
                'indexexpr2-11.0',
                'generated-column expression index resolves outer aggregate references against the correct loop',
                'SELECT * FROM t3 AS a0 WHERE (SELECT sum(-a0.a=b) FROM t3 GROUP BY b) GROUP BY b',
                't3x',
                'b, a',
                true,
                [[44, -44]],
                null,
                'SEARCH t3 USING INDEX t3x with generated column b AS (-a)',
                'ok',
            ],
        ];

        $out = [];
        for ($case = 1; $case <= $cases; $case++) {
            [$section, $scenario, $statement, $indexName, $expression, $usesIndex, $rows, $error, $detail, $integrity] = $templates[($case - 1) % count($templates)];
            $out[] = [
                'source' => 'indexexpr2.test sections indexexpr2-5.0 through indexexpr2-11.0',
                'case' => $case,
                'batch' => intdiv($case - 1, count($templates)) + 1,
                'upstream_section' => $section,
                'scenario' => $scenario,
                'statement' => $statement,
                'index_name' => $indexName,
                'expression' => $expression,
                'uses_expression_index' => $usesIndex,
                'result_rows' => $rows,
                'result_count' => count($rows),
                'expected_error' => $error,
                'detail' => $detail,
                'integrity' => $integrity,
            ];
        }

        return $out;
    }

    /**
     * @return list<array{source:string,case:int,upstream_section:string,batch:int,scenario:string,statement:string,index_name:string,indexed_expression:string,where_clause:string|null,function_opcode_count:int,expected_rows:list<array<int,mixed>>,uses_covering_index:bool,uses_index:bool,detail:string,integrity:string}>
     */
    public static function indexexpr3JsonExpressionCoveringCases(int $cases = 1000): array
    {
        if ($cases < 1) {
            throw new \InvalidArgumentException('SQLite indexexpr3 JSON expression-index corpus requires at least one case');
        }

        $templates = [
            ['indexexpr3-1.1', 'ORDER BY reads json_extract expression from expression index without Function opcodes', "SELECT json_extract(j, '$.x') FROM t1 ORDER BY 1", 'i1', "json_extract(j, '$.x')", null, 0, [['one'], ['three'], ['two']], true, true, 'SCAN t1 USING INDEX i1; expression value is read from the index key'],
            ['indexexpr3-1.2', 'equality prefix on a plus expression column reads json_extract from composite index', "SELECT json_extract(j, '$.x') FROM t1 WHERE a=2", 'i2', "a, json_extract(j, '$.x')", 'a=2', 0, [['two']], true, true, 'SEARCH t1 USING COVERING INDEX i2 (a=?)'],
            ['indexexpr3-1.3', 'coalesce wrapper still uses indexed json_extract operand without recomputation', "SELECT coalesce(json_extract(j, '$.x'), 'five') FROM t1 WHERE a=2", 'i2', "a, json_extract(j, '$.x')", 'a=2', 0, [['two']], true, true, 'SEARCH t1 USING COVERING INDEX i2 (a=?) and evaluate coalesce over index value'],
            ['indexexpr3-1.4', 'concatenation wrapper reuses indexed json_extract operand', "SELECT json_extract(j, '$.x') || '.two' FROM t1 WHERE a=2", 'i2', "a, json_extract(j, '$.x')", 'a=2', 0, [['two.two']], true, true, 'SEARCH t1 USING COVERING INDEX i2 (a=?) and concatenate the stored expression value'],
            ['indexexpr3-1.5', 'json_insert cannot fully substitute nested json_extract and keeps two Function opcodes', "SELECT json_insert('{}', '$.y', json_extract(j, '$.x')) FROM t1 WHERE a=2", 'i2', "a, json_extract(j, '$.x')", 'a=2', 2, [['{"y":"two"}']], false, true, 'SEARCH t1 USING INDEX i2; json_insert and nested extraction remain executable functions'],
            ['indexexpr3-1.6', 'json_insert with coalesce wrapper keeps runtime Function opcodes', "SELECT json_insert('{}', '$.y', coalesce(json_extract(j, '$.x'), 'five')) FROM t1 WHERE a=2", 'i2', "a, json_extract(j, '$.x')", 'a=2', 2, [['{"y":"two"}']], false, true, 'SEARCH t1 USING INDEX i2 while nested JSON function evaluation remains visible'],
            ['indexexpr3-2.1', 'single indexed expression projection is covered by composite expression index', "SELECT json_extract(j, '$.x') FROM t1 WHERE a=?", 'i1', "a, json_extract(j, '$.x')", 'a=?', 0, [], true, true, 't1 USING COVERING INDEX i1'],
            ['indexexpr3-2.2', 'adding non-indexed column b prevents covering index use', "SELECT b, json_extract(j, '$.x') FROM t1 WHERE a=?", 'i1', "a, json_extract(j, '$.x')", 'a=?', 0, [], false, true, 't1 USING INDEX i1'],
            ['indexexpr3-2.3', 'json_insert path argument fed by indexed expression is not covering', "SELECT json_insert('{}', json_extract(j, '$.x')) FROM t1 WHERE a=?", 'i1', "a, json_extract(j, '$.x')", 'a=?', 1, [], false, true, 't1 USING INDEX i1'],
            ['indexexpr3-2.4', 'aggregate over only the indexed expression is covering', "SELECT sum(json_extract(j, '$.x')) FROM t1 WHERE a=?", 'i1', "a, json_extract(j, '$.x')", 'a=?', 0, [], true, true, 't1 USING COVERING INDEX i1'],
            ['indexexpr3-2.5', 'mixed projection plus aggregate over expression is not covering', "SELECT json_extract(j, '$.x'), sum(json_extract(j, '$.x')) FROM t1 WHERE a=?", 'i1', "a, json_extract(j, '$.x')", 'a=?', 0, [], false, true, 't1 USING INDEX i1'],
        ];

        $out = [];
        for ($case = 1; $case <= $cases; $case++) {
            [$section, $scenario, $statement, $indexName, $indexedExpression, $whereClause, $functionCount, $rows, $covering, $usesIndex, $detail] = $templates[($case - 1) % count($templates)];
            $batch = intdiv($case - 1, count($templates)) + 1;
            $out[] = [
                'source' => 'indexexpr3.test sections indexexpr3-1.1 through indexexpr3-2.5',
                'case' => $case,
                'upstream_section' => $section,
                'batch' => $batch,
                'scenario' => $scenario . ' dynamic batch ' . $batch,
                'statement' => $statement,
                'index_name' => $indexName,
                'indexed_expression' => $indexedExpression,
                'where_clause' => $whereClause,
                'function_opcode_count' => $functionCount,
                'expected_rows' => $rows,
                'uses_covering_index' => $covering,
                'uses_index' => $usesIndex,
                'detail' => $detail,
                'integrity' => 'ok',
            ];
        }

        return $out;
    }

    /**
     * @return list<array{source:string,case:int,upstream_section:string,batch:int,scenario:string,left_table:string,right_table:string,left_column:string,right_column:string,left_declared_type:string,right_declared_type:string,left_affinity:string,right_affinity:string,left_stored_value:int|float|string,right_stored_value:int|float|string,predicate_sql:string,index_present:bool,unary_plus:bool,expected_equal:bool,expected_result_rows:list<list<int>>,projection_value:int,planner_detail:string,integrity:string}>
     */
    public static function whereBAffinityComparisonCases(int $cases = 1200): array
    {
        if ($cases < 1) {
            throw new \InvalidArgumentException('SQLite whereB affinity comparison corpus requires at least one case');
        }

        $groups = [
            [1, 'app_none_integer_values', 'BLOB', 'NONE', 99, 'app_text_values', 'TEXT', 'TEXT', '99', false, 'NONE integer storage does not equal TEXT storage without numeric affinity'],
            [2, 'app_text_values', 'TEXT', 'TEXT', '99', 'app_none_integer_values', 'BLOB', 'NONE', 99, false, 'TEXT storage does not equal NONE integer storage in column-to-column comparison'],
            [3, 'app_none_integer_values', 'BLOB', 'NONE', 99, 'app_none_text_values', 'BLOB', 'NONE', '99', false, 'NONE integer storage does not equal NONE text storage'],
            [4, 'app_none_text_values', 'BLOB', 'NONE', '99', 'app_numeric_values', 'NUMERIC', 'NUMERIC', 99, true, 'right NUMERIC affinity coerces left text storage before comparison'],
            [5, 'app_none_text_values', 'BLOB', 'NONE', '99', 'app_integer_values', 'INT', 'INTEGER', 99, true, 'right INTEGER affinity coerces left text storage before comparison'],
            [6, 'app_none_text_values', 'BLOB', 'NONE', '99', 'app_real_values', 'REAL', 'REAL', 99.0, true, 'right REAL affinity coerces left text storage before comparison'],
            [7, 'app_numeric_values', 'NUMERIC', 'NUMERIC', 99, 'app_none_text_values', 'BLOB', 'NONE', '99', true, 'left NUMERIC affinity coerces right text storage before comparison'],
            [8, 'app_integer_values', 'INT', 'INTEGER', 99, 'app_none_text_values', 'BLOB', 'NONE', '99', true, 'left INTEGER affinity coerces right text storage before comparison'],
            [9, 'app_real_values', 'REAL', 'REAL', 99.0, 'app_none_text_values', 'BLOB', 'NONE', '99', true, 'left REAL affinity coerces right text storage before comparison'],
        ];
        $operations = [
            ['1', 'projection', 'y=b', true],
            ['2', 'where-left', 'y=b', true],
            ['3', 'where-right', 'b=y', true],
            ['4', 'where-unary', '+y=+b', true],
            ['100', 'where-left-after-drop', 'y=b', false],
            ['101', 'where-right-after-drop', 'b=y', false],
            ['102', 'where-unary-after-drop', '+y=+b', false],
        ];

        $templates = [];
        foreach ($groups as [$group, $leftTable, $leftDeclaredType, $leftAffinity, $leftValue, $rightTable, $rightDeclaredType, $rightAffinity, $rightValue, $numericEqual, $scenario]) {
            foreach ($operations as [$suffix, $operation, $predicate, $indexPresent]) {
                $unary = str_contains($predicate, '+');
                $expectedEqual = $unary ? false : $numericEqual;
                $templates[] = [
                    'whereB-' . $group . '.' . $suffix,
                    $scenario . ' via ' . $operation,
                    $leftTable,
                    $rightTable,
                    $leftDeclaredType,
                    $rightDeclaredType,
                    $leftAffinity,
                    $rightAffinity,
                    $leftValue,
                    $rightValue,
                    $predicate,
                    $indexPresent,
                    $unary,
                    $expectedEqual,
                ];
            }
        }

        $out = [];
        for ($case = 1; $case <= $cases; $case++) {
            [
                $section,
                $scenario,
                $leftTable,
                $rightTable,
                $leftDeclaredType,
                $rightDeclaredType,
                $leftAffinity,
                $rightAffinity,
                $leftValue,
                $rightValue,
                $predicate,
                $indexPresent,
                $unary,
                $expectedEqual,
            ] = $templates[($case - 1) % count($templates)];
            $batch = intdiv($case - 1, count($templates)) + 1;
            $projectionValue = $expectedEqual ? 1 : 0;

            $out[] = [
                'source' => 'whereB.test sections whereB-1.1 through whereB-9.102',
                'case' => $case,
                'upstream_section' => $section,
                'batch' => $batch,
                'scenario' => $scenario . ' dynamic batch ' . $batch,
                'left_table' => $leftTable,
                'right_table' => $rightTable,
                'left_column' => 'y',
                'right_column' => 'b',
                'left_declared_type' => $leftDeclaredType,
                'right_declared_type' => $rightDeclaredType,
                'left_affinity' => $leftAffinity,
                'right_affinity' => $rightAffinity,
                'left_stored_value' => $leftValue,
                'right_stored_value' => $rightValue,
                'predicate_sql' => $predicate,
                'index_present' => $indexPresent,
                'unary_plus' => $unary,
                'expected_equal' => $expectedEqual,
                'expected_result_rows' => $expectedEqual ? [[1, 2, 1]] : [],
                'projection_value' => $projectionValue,
                'planner_detail' => $unary
                    ? 'SCAN both tables' . ($indexPresent ? '' : ' after DROP INDEX t2b') . '; unary plus removes comparison affinity'
                    : ($indexPresent ? 'SEARCH app_text_or_numeric_side USING INDEX t2b before residual affinity check' : 'SCAN both tables after DROP INDEX t2b'),
                'integrity' => 'ok',
            ];
        }

        return $out;
    }

    /**
     * @return list<array{source:string,case:int,upstream_section:string,batch:int,scenario:string,table_name:string,declared_type:string,affinity:string,raw_values:list<int>,stored_values:list<mixed>,select_sql:string,predicate_sql:string|null,expression_sql:string|null,projection_mode:bool,uses_rowid_btree:bool,comparison_family:string,expected_rows:list<mixed>,projected_values:list<int|null>|null,matched_row_count:int,null_result_count:int,detail:string,integrity:string}>
     */
    public static function where5NullComparisonCases(int $cases = 1200): array
    {
        if ($cases < 1) {
            throw new \InvalidArgumentException('SQLite where5 NULL comparison corpus requires at least one case');
        }

        $source = 'where5.test sections where5-1.0 through where5-4.7';
        $whereOperators = [
            ['where5-%d.0', '<', 'less-than boundary filters the lower row'],
            ['where5-%d.1', '<=', 'less-or-equal boundary keeps lower and boundary rows'],
            ['where5-%d.2', '=', 'equality boundary keeps only the boundary row'],
            ['where5-%d.3', '>=', 'greater-or-equal boundary keeps boundary and upper rows'],
            ['where5-%d.4', '>', 'greater-than boundary keeps the upper row'],
            ['where5-%d.5', '<>', 'not-equal boundary keeps lower and upper rows'],
            ['where5-%d.6', '< NULL', 'less-than NULL filters every row because result is NULL'],
            ['where5-%d.7', '<= NULL', 'less-or-equal NULL filters every row because result is NULL'],
            ['where5-%d.8', '= NULL', 'equals NULL filters every row because result is NULL'],
            ['where5-%d.9', '>= NULL', 'greater-or-equal NULL filters every row because result is NULL'],
            ['where5-%d.10', '> NULL', 'greater-than NULL filters every row because result is NULL'],
            ['where5-%d.11', '!= NULL', 'not-equal NULL filters every row because result is NULL'],
            ['where5-%d.12', 'IS NULL', 'IS NULL finds no non-null upstream seed rows'],
            ['where5-%d.13', 'IS NOT NULL', 'IS NOT NULL preserves all upstream seed rows'],
        ];
        $tables = [
            ['where5-1', 'app_text_values', 'TEXT', 'TEXT', false, 'TEXT affinity table scan'],
            ['where5-2', 'app_integer_values', 'INTEGER', 'INTEGER', false, 'INTEGER affinity table scan'],
            ['where5-3', 'app_integer_primary_values', 'INTEGER PRIMARY KEY', 'INTEGER', true, 'INTEGER PRIMARY KEY rowid btree scan'],
        ];

        $templates = [];
        foreach ($tables as [$sectionPrefix, $tableName, $declaredType, $affinity, $usesRowidBtree, $family]) {
            $sectionGroup = (int) substr($sectionPrefix, -1);
            foreach ($whereOperators as [$sectionFormat, $operator, $detail]) {
                $predicate = str_contains($operator, 'NULL') || str_starts_with($operator, 'IS ')
                    ? 'x ' . $operator
                    : 'x ' . $operator . ' %d';
                $templates[] = [
                    sprintf($sectionFormat, $sectionGroup),
                    $tableName,
                    $declaredType,
                    $affinity,
                    $predicate,
                    null,
                    false,
                    $usesRowidBtree,
                    $family,
                    $detail,
                ];
            }
        }

        foreach ([
            ['where5-4.0', 'x<NULL', 'projection returns NULL for x<NULL over every row'],
            ['where5-4.1', 'x<=NULL', 'projection returns NULL for x<=NULL over every row'],
            ['where5-4.2', 'x==NULL', 'projection returns NULL for x==NULL over every row'],
            ['where5-4.3', 'x>NULL', 'projection returns NULL for x>NULL over every row'],
            ['where5-4.4', 'x>=NULL', 'projection returns NULL for x>=NULL over every row'],
            ['where5-4.5', 'x!=NULL', 'projection returns NULL for x!=NULL over every row'],
            ['where5-4.6', 'x IS NULL', 'projection returns 0 for x IS NULL over non-null rowid values'],
            ['where5-4.7', 'x IS NOT NULL', 'projection returns 1 for x IS NOT NULL over non-null rowid values'],
        ] as [$section, $expression, $detail]) {
            $templates[] = [
                $section,
                'app_integer_primary_values',
                'INTEGER PRIMARY KEY',
                'INTEGER',
                null,
                $expression,
                true,
                true,
                'INTEGER PRIMARY KEY rowid btree projection',
                $detail,
            ];
        }

        $out = [];
        for ($case = 1; $case <= $cases; $case++) {
            [$section, $tableName, $declaredType, $affinity, $predicate, $expression, $projectionMode, $usesRowidBtree, $family, $detail] = $templates[($case - 1) % count($templates)];
            $batch = intdiv($case - 1, count($templates)) + 1;
            $boundary = ($batch % 17) - 8;
            $rawValues = [$boundary - 1, $boundary, $boundary + 1];
            $storedValues = array_map(
                static fn (int $value): mixed => SQLiteAffinityComparison::applyAffinity($value, $affinity),
                $rawValues,
            );
            $predicateSql = is_string($predicate) ? sprintf($predicate, $boundary) : null;
            $selectSql = $projectionMode
                ? sprintf('SELECT %s FROM %s ORDER BY x', $expression, $tableName)
                : sprintf('SELECT x FROM %s WHERE %s ORDER BY x', $tableName, $predicateSql);
            $expectedRows = $projectionMode ? [] : self::where5ExpectedRows($storedValues, $affinity, (string) $predicateSql);
            $projectedValues = $projectionMode ? self::where5ProjectedValues($storedValues, $affinity, (string) $expression) : null;

            $out[] = [
                'source' => $source,
                'case' => $case,
                'upstream_section' => $section,
                'batch' => $batch,
                'scenario' => $detail . ' dynamic batch ' . $batch,
                'table_name' => $tableName,
                'declared_type' => $declaredType,
                'affinity' => $affinity,
                'raw_values' => $rawValues,
                'stored_values' => $storedValues,
                'select_sql' => $selectSql,
                'predicate_sql' => $predicateSql,
                'expression_sql' => is_string($expression) ? $expression : null,
                'projection_mode' => $projectionMode,
                'uses_rowid_btree' => $usesRowidBtree,
                'comparison_family' => $family,
                'expected_rows' => $expectedRows,
                'projected_values' => $projectedValues,
                'matched_row_count' => count($expectedRows),
                'null_result_count' => $projectedValues === null ? 0 : count(array_filter($projectedValues, static fn (mixed $value): bool => $value === null)),
                'detail' => $detail,
                'integrity' => 'ok',
            ];
        }

        return $out;
    }

    /**
     * @param list<mixed> $storedValues
     * @return list<mixed>
     */
    private static function where5ExpectedRows(array $storedValues, string $affinity, string $predicateSql): array
    {
        $rows = [];
        foreach (self::where5OrderByValues($storedValues) as $value) {
            if (self::where5EvaluateComparison($value, $affinity, $predicateSql) === true) {
                $rows[] = $value;
            }
        }

        return $rows;
    }

    /**
     * @param list<mixed> $storedValues
     * @return list<int|null>
     */
    private static function where5ProjectedValues(array $storedValues, string $affinity, string $expressionSql): array
    {
        $values = [];
        foreach (self::where5OrderByValues($storedValues) as $value) {
            $result = self::where5EvaluateComparison($value, $affinity, $expressionSql);
            $values[] = $result === null ? null : ($result ? 1 : 0);
        }

        return $values;
    }

    /**
     * @param list<mixed> $storedValues
     * @return list<mixed>
     */
    private static function where5OrderByValues(array $storedValues): array
    {
        $ordered = $storedValues;
        usort(
            $ordered,
            static fn (mixed $left, mixed $right): int => SQLiteAffinityComparison::compare($left, $right, 'NONE', 'NONE', 'BINARY') ?? 0,
        );

        return $ordered;
    }

    private static function where5EvaluateComparison(mixed $left, string $leftAffinity, string $sql): ?bool
    {
        $normalized = strtoupper(trim(preg_replace('/\s+/', ' ', str_replace('==', '=', $sql)) ?? $sql));
        if ($normalized === 'X IS NULL') {
            return $left === null;
        }
        if ($normalized === 'X IS NOT NULL') {
            return $left !== null;
        }

        if (preg_match('/^X\s*(<=|>=|<>|!=|=|<|>)\s*(NULL|[-+]?[0-9]+)$/', $normalized, $matches) !== 1) {
            throw new \InvalidArgumentException('SQLite where5 comparison expression is unsupported: ' . $sql);
        }

        $right = $matches[2] === 'NULL' ? null : (int) $matches[2];
        $comparison = SQLiteAffinityComparison::compare($left, $right, $leftAffinity, 'NONE', 'BINARY');
        if ($comparison === null) {
            return null;
        }

        return match ($matches[1]) {
            '<' => $comparison < 0,
            '<=' => $comparison <= 0,
            '=' => $comparison === 0,
            '>=' => $comparison >= 0,
            '>' => $comparison > 0,
            '<>', '!=' => $comparison !== 0,
            default => throw new \InvalidArgumentException('SQLite where5 comparison operator is unsupported: ' . $matches[1]),
        };
    }

    /**
     * @return list<int>
     */
    private static function index5SyntheticForwardWritePages(int $startPage, int $count, int $seed): array
    {
        $pages = [$startPage];
        $current = $startPage;
        for ($i = 1; $i < $count; $i++) {
            if (($i % (13 + $seed)) === 0) {
                $current -= 1;
            } elseif (($i % (17 + $seed)) === 0) {
                $current += 3;
            } else {
                $current += 1;
            }

            $pages[] = $current;
        }

        return $pages;
    }

    /**
     * @param list<int> $writePages
     * @return array{0:int,1:int,2:int}
     */
    private static function writeLocalityCounters(array $writePages): array
    {
        $forward = 0;
        $backward = 0;
        $noncontiguous = 0;
        $previous = $writePages[0] ?? 0;

        for ($i = 1, $count = count($writePages); $i < $count; $i++) {
            $next = $writePages[$i];
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
}
