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
}
