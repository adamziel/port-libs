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

    private static function sqlitePartialIndexBoundValueMatches(int $predicate, mixed $value): bool
    {
        return is_int($value) && $value === $predicate;
    }
}
