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
