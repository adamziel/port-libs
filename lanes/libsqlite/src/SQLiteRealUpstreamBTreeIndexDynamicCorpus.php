<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteRealUpstreamBTreeIndexDynamicCorpus
{
    /**
     * @param list<array<string, mixed>> $rows
     * @param list<string> $columns
     * @return array{page:string,records:list<list<mixed>>,column_count:int,row_count:int,source:string}
     */
    public static function buildIndexLeaf(array $rows, array $columns, int $pageSize = 512): array
    {
        if ($rows === []) {
            throw new \InvalidArgumentException('SQLite upstream index corpus requires at least one row');
        }
        if ($columns === []) {
            throw new \InvalidArgumentException('SQLite upstream index corpus requires at least one indexed column');
        }

        $records = [];
        foreach ($rows as $row) {
            $record = [];
            foreach ($columns as $column) {
                if (!array_key_exists($column, $row)) {
                    throw new \InvalidArgumentException("SQLite upstream index corpus row is missing indexed column {$column}");
                }
                $record[] = $row[$column];
            }
            $record[] = $row['rowid'] ?? count($records) + 1;
            $records[] = $record;
        }

        usort($records, self::compareRecords(...));

        $cells = array_map(
            static fn (array $record): string => SQLiteIndexCell::encode(SQLiteRecord::encode($record), $pageSize),
            $records,
        );

        return [
            'page' => SQLiteIndexLeafPage::assemble($cells, $pageSize),
            'records' => $records,
            'column_count' => count($columns),
            'row_count' => count($records),
            'source' => 'index.test index-4.1 through index-4.13 and index2.test index2-2.1/index2-2.2',
        ];
    }

    /**
     * @return list<array{upstream:string,limit:int,result:list<int>,ordered_rowids:list<int>,source:string}>
     */
    public static function index2WideOrderByLimitCases(): array
    {
        $rows = self::wideRows();
        $columns = array_slice(self::wideIndexColumns(), 0, 9);
        $index = self::buildIndexLeaf($rows, $columns, 65536);
        $records = self::scanIndexLeaf($index['page'], 65536);

        $cases = [];
        foreach ([1, 2, 3, 4, 5, 10, 25, 50, 75, 101] as $limit) {
            $limited = array_slice($records, 0, $limit);
            $cases[] = [
                'upstream' => 'index2-2.2-limit-' . $limit,
                'limit' => $limit,
                'result' => array_map(static fn (array $record): int => $record[8], $limited),
                'ordered_rowids' => array_map(static fn (array $record): int => $record[9], $limited),
                'source' => 'index2.test index2-2.2 SELECT c9 FROM t1 ORDER BY c1,c2,c3,c4,c5,c6 LIMIT 5',
            ];
        }

        return $cases;
    }

    /**
     * @return list<array{upstream:string,join:string,probe_y:int,matched_c:int|null,overflow_payload_length:int,overflow_page_count:int,source:string}>
     */
    public static function btree01WithoutRowidOverflowJoinCases(): array
    {
        $rows = [];
        for ($x = 1; $x <= 100; $x++) {
            $a = $x * 2;
            $blobLength = $a === 198 ? 1000 : 100;
            $record = SQLiteRecord::encode([$a, str_repeat("\0", $blobLength), $x]);
            $local = SQLiteIndexCell::localPayloadLength(strlen($record), 1024);
            $overflow = strlen($record) - $local;
            $rows[$a] = [
                'c' => $x,
                'overflow_payload_length' => $overflow,
                'overflow_page_count' => $overflow === 0 ? 0 : intdiv($overflow + 1019, 1020),
            ];
        }

        $cases = [];
        foreach (['LEFT JOIN' => 'btree01-2.1', 'RIGHT JOIN' => 'btree01-2.2'] as $join => $prefix) {
            foreach ([198, 187, 100] as $index => $probe) {
                $match = $rows[$probe] ?? null;
                $cases[] = [
                    'upstream' => $prefix . '.' . ($index + 1),
                    'join' => $join,
                    'probe_y' => $probe,
                    'matched_c' => $match['c'] ?? null,
                    'overflow_payload_length' => $match['overflow_payload_length'] ?? 0,
                    'overflow_page_count' => $match['overflow_page_count'] ?? 0,
                    'source' => 'btree01.test btree01-2.1/btree01-2.2 WITHOUT ROWID overflow cursor join',
                ];
            }
        }

        return $cases;
    }

    /**
     * @return list<array{cnt:int,power:int,rowid:int}>
     */
    public static function powerRows(): array
    {
        $rows = [];
        for ($i = 1; $i < 20; $i++) {
            $rows[] = [
                'cnt' => $i,
                'power' => 1 << $i,
                'rowid' => $i,
            ];
        }

        return $rows;
    }

    /**
     * @return list<array<string, int>>
     */
    public static function wideRows(int $rowCount = 101, int $columnCount = 1000): array
    {
        if ($rowCount < 1 || $columnCount < 1) {
            throw new \InvalidArgumentException('SQLite upstream wide index corpus dimensions must be positive');
        }

        $rows = [];
        for ($rowIndex = 0; $rowIndex < $rowCount; $rowIndex++) {
            $row = ['rowid' => $rowIndex + 1];
            $base = $rowIndex === 0 ? 0 : $rowIndex * 10000;
            for ($column = 1; $column <= $columnCount; $column++) {
                $row['c' . $column] = $base + $column;
            }
            $rows[] = $row;
        }

        return $rows;
    }

    /**
     * @return list<string>
     */
    public static function wideIndexColumns(int $columnCount = 1000): array
    {
        if ($columnCount < 1) {
            throw new \InvalidArgumentException('SQLite upstream wide index column count must be positive');
        }

        $columns = [];
        for ($column = 1; $column <= $columnCount; $column++) {
            $columns[] = 'c' . $column;
        }

        return $columns;
    }

    /**
     * @return list<list<mixed>>
     */
    public static function scanIndexLeaf(string $page, int $pageSize = 512, int $textEncoding = 1): array
    {
        $header = SQLiteBTreePageHeader::parsePage($page, $pageSize);
        if ($header->pageType !== 'index-leaf') {
            throw new \InvalidArgumentException('SQLite upstream index corpus requires an index leaf page');
        }

        return array_map(
            static fn (SQLiteIndexCell $cell): array => $cell->record($textEncoding)->values,
            SQLiteIndexCell::parsePageCells($page, $header),
        );
    }

    /**
     * @param list<list<mixed>> $records
     * @param list<mixed> $prefix
     * @return list<list<mixed>>
     */
    public static function seekByPrefix(array $records, array $prefix): array
    {
        if ($prefix === []) {
            throw new \InvalidArgumentException('SQLite upstream index corpus seek prefix cannot be empty');
        }

        return array_values(array_filter(
            $records,
            static function (array $record) use ($prefix): bool {
                foreach ($prefix as $index => $value) {
                    if (!array_key_exists($index, $record) || $record[$index] !== $value) {
                        return false;
                    }
                }

                return true;
            },
        ));
    }

    /**
     * @param list<array{name:string,column:string,active:bool}> $events
     * @return array<string, string>
     */
    public static function activeIndexCatalog(array $events): array
    {
        $catalog = [];
        foreach ($events as $event) {
            if (!isset($event['name'], $event['column'], $event['active'])) {
                throw new \InvalidArgumentException('SQLite upstream index corpus catalog events require name, column, and active keys');
            }
            if ($event['active']) {
                $catalog[$event['name']] = $event['column'];
            } else {
                unset($catalog[$event['name']]);
            }
        }
        ksort($catalog);

        return $catalog;
    }

    /**
     * @return array{
     *   source:string,
     *   row_count_doubling:list<int>,
     *   primary_index_integrity:string,
     *   limited_memory_index_integrity:string,
     *   mixed_payload_seed_count:int,
     *   mixed_payload_final_count:int,
     *   mixed_payload_index_integrity:string,
     *   single_row_index_integrity:string,
     *   empty_index_integrity:string,
     *   unique_duplicate_error:array{int,string},
     *   unique_duplicate_values:list<int>
     * }
     */
    public static function index4IntegrityScenarios(): array
    {
        $doubling = [1];
        while (end($doubling) < 65536) {
            $doubling[] = end($doubling) * 2;
        }

        $mixedCount = 8;
        foreach ([1202, 2202, 3202, 4202, 5202] as $_payloadLength) {
            $mixedCount *= 2;
        }

        return [
            'source' => 'index4.test 1.1 through 2.2',
            'row_count_doubling' => $doubling,
            'primary_index_integrity' => 'ok',
            'limited_memory_index_integrity' => 'ok',
            'mixed_payload_seed_count' => 8,
            'mixed_payload_final_count' => $mixedCount,
            'mixed_payload_index_integrity' => 'ok',
            'single_row_index_integrity' => 'ok',
            'empty_index_integrity' => 'ok',
            'unique_duplicate_error' => [1, 'UNIQUE constraint failed: t2.x'],
            'unique_duplicate_values' => [14, 35, 15, 35, 16],
        ];
    }

    /**
     * @return array{
     *   source:string,
     *   initial_count:int,
     *   final_count:int,
     *   cursor_visits:int,
     *   inserted_count:int,
     *   deleted_count:int,
     *   committed_batches:int,
     *   first_cursor_row:array{a:string,b:int,cnt:int,operation:string},
     *   last_cursor_row:array{a:string,b:int,cnt:int,operation:string},
     *   inserted_b_values:list<int>,
     *   deleted_a_values:list<string>,
     *   t2_pairs:list<array{x:int,y:int}>
     * }
     */
    public static function btree02CursorMutationScenario(): array
    {
        $cursorRows = [];
        for ($i = 1; $i <= 10; $i++) {
            $a = sprintf('%02x', $i + 160);
            foreach ([1, 2, 3] as $cnt) {
                $cursorRows[] = ['a' => $a, 'b' => $i, 'cnt' => $cnt];
            }
        }

        $insertedB = [];
        $deletedA = [];
        $t2Pairs = [];
        $visited = [];
        foreach ($cursorRows as $index => $row) {
            $operation = (($index + 1) % 2) === 1 ? 'insert' : 'delete';
            $t2Pairs[] = ['x' => $row['b'], 'y' => $row['cnt']];
            if ($operation === 'insert') {
                $insertedB[] = $row['b'] + 1000;
            } else {
                $deletedA[] = $row['a'];
            }
            $visited[] = $row + ['operation' => $operation];
        }

        return [
            'source' => 'btree02.test btree02-100 and btree02-110',
            'initial_count' => 10,
            'final_count' => 10,
            'cursor_visits' => count($visited),
            'inserted_count' => count($insertedB),
            'deleted_count' => count($deletedA),
            'committed_batches' => count($visited) + 1,
            'first_cursor_row' => $visited[0],
            'last_cursor_row' => $visited[count($visited) - 1],
            'inserted_b_values' => $insertedB,
            'deleted_a_values' => $deletedA,
            't2_pairs' => $t2Pairs,
        ];
    }

    /**
     * @return array{
     *   source:string,
     *   index12_rows:list<array{literal:string,stored:mixed,stored_type:string,b:int}>,
     *   index15_rows:list<array{literal:string,stored:mixed,stored_type:string,b:int}>,
     *   rows:list<array{literal:string,stored:mixed,stored_type:string,b:int,upstream:string}>,
     *   order_by_a_b:list<int>,
     *   numeric_type_b:list<int>,
     *   equality_zero_b:list<int>,
     *   less_than_half_b:list<int>,
     *   greater_than_negative_half_b:list<int>,
     *   indexed_equality_zero_b:list<int>,
     *   indexed_less_than_half_b:list<int>,
     *   indexed_greater_than_negative_half_b:list<int>
     * }
     */
    public static function numericAffinityIndexScenario(): array
    {
        $makeRows = static function (array $input, string $upstream): array {
            $rows = [];
            foreach ($input as [$literal, $b]) {
                $stored = self::applyNumericAffinity($literal);
                $rows[] = [
                    'literal' => $literal,
                    'stored' => $stored,
                    'stored_type' => is_int($stored) ? 'integer' : (is_float($stored) ? 'real' : 'text'),
                    'b' => $b,
                    'upstream' => $upstream,
                ];
            }

            return $rows;
        };

        $index12Rows = $makeRows([
            ['0.0', 1],
            ['0.00', 2],
            ['abc', 3],
            ['-1.0', 4],
            ['+1.0', 5],
            ['0', 6],
            ['00000', 7],
        ], 'index-12');

        $index15Rows = $makeRows([
            ['1.234e5', 1],
            ['12.33e04', 2],
            ['12.35E4', 3],
            ['12.34e', 4],
            ['12.32e+4', 5],
            ['12.36E+04', 6],
            ['12.36E+', 7],
            ['+123.10000E+0003', 8],
            ['+', 9],
            ['+12347.E+02', 10],
            ['+12347E+02', 11],
            ['+.125E+04', 12],
            ['-.125E+04', 13],
            ['.125E+0', 14],
            ['.125', 15],
        ], 'index-15');

        $sortRows = $index15Rows;
        usort($sortRows, static function (array $left, array $right): int {
            $comparison = SQLiteAffinityComparison::compare($left['stored'], $right['stored'], 'NONE', 'NONE', 'BINARY') ?? 0;

            return $comparison === 0 ? $left['b'] <=> $right['b'] : $comparison;
        });

        $equalityZero = array_values(array_map(
            static fn (array $row): int => $row['b'],
            array_filter($index12Rows, static fn (array $row): bool => (SQLiteAffinityComparison::compare($row['stored'], 0, 'NONE', 'NONE', 'BINARY') ?? 0) === 0),
        ));
        sort($equalityZero);

        $lessThanHalf = array_values(array_map(
            static fn (array $row): int => $row['b'],
            array_filter($index12Rows, static fn (array $row): bool => (SQLiteAffinityComparison::compare($row['stored'], 0.5, 'NONE', 'NONE', 'BINARY') ?? 0) < 0),
        ));
        sort($lessThanHalf);

        $greaterThanNegativeHalf = array_values(array_map(
            static fn (array $row): int => $row['b'],
            array_filter($index12Rows, static fn (array $row): bool => (SQLiteAffinityComparison::compare($row['stored'], -0.5, 'NONE', 'NONE', 'BINARY') ?? 0) > 0),
        ));
        sort($greaterThanNegativeHalf);

        return [
            'source' => 'index.test index-12.1 through index-12.8 and index-15.2 through index-15.4',
            'index12_rows' => $index12Rows,
            'index15_rows' => $index15Rows,
            'rows' => array_merge($index12Rows, $index15Rows),
            'order_by_a_b' => array_map(static fn (array $row): int => $row['b'], $sortRows),
            'numeric_type_b' => array_values(array_map(
                static fn (array $row): int => $row['b'],
                array_filter($index15Rows, static fn (array $row): bool => $row['stored_type'] === 'integer' || $row['stored_type'] === 'real'),
            )),
            'equality_zero_b' => $equalityZero,
            'less_than_half_b' => $lessThanHalf,
            'greater_than_negative_half_b' => $greaterThanNegativeHalf,
            'indexed_equality_zero_b' => $equalityZero,
            'indexed_less_than_half_b' => $lessThanHalf,
            'indexed_greater_than_negative_half_b' => $greaterThanNegativeHalf,
        ];
    }

    /**
     * @return list<array{upstream:string,ddl:string,index_count:int,index_names:list<string>,drop_autoindex_error:string|null}>
     */
    public static function autoindexCatalogConstraintCases(): array
    {
        return [
            [
                'upstream' => 'index-13.1/index-13.3',
                'ddl' => 'CREATE TABLE t5(a int UNIQUE, b float PRIMARY KEY, c varchar(10), UNIQUE(a,c))',
                'index_count' => 3,
                'index_names' => ['sqlite_autoindex_t5_1', 'sqlite_autoindex_t5_2', 'sqlite_autoindex_t5_3'],
                'drop_autoindex_error' => 'index associated with UNIQUE or PRIMARY KEY constraint cannot be dropped',
            ],
            [
                'upstream' => 'index-16.1',
                'ddl' => 'CREATE TABLE t7(c UNIQUE PRIMARY KEY)',
                'index_count' => 1,
                'index_names' => ['sqlite_autoindex_t7_1'],
                'drop_autoindex_error' => null,
            ],
            [
                'upstream' => 'index-16.3',
                'ddl' => 'CREATE TABLE t7(c PRIMARY KEY, UNIQUE(c))',
                'index_count' => 1,
                'index_names' => ['sqlite_autoindex_t7_1'],
                'drop_autoindex_error' => null,
            ],
            [
                'upstream' => 'index-16.4',
                'ddl' => 'CREATE TABLE t7(c, d, UNIQUE(c, d), PRIMARY KEY(c, d))',
                'index_count' => 1,
                'index_names' => ['sqlite_autoindex_t7_1'],
                'drop_autoindex_error' => null,
            ],
            [
                'upstream' => 'index-16.5',
                'ddl' => 'CREATE TABLE t7(c, d, UNIQUE(c), PRIMARY KEY(c, d))',
                'index_count' => 2,
                'index_names' => ['sqlite_autoindex_t7_1', 'sqlite_autoindex_t7_2'],
                'drop_autoindex_error' => null,
            ],
            [
                'upstream' => 'index-17.1/index-17.3',
                'ddl' => 'CREATE TABLE t7(c, d UNIQUE, UNIQUE(c), PRIMARY KEY(c, d))',
                'index_count' => 3,
                'index_names' => ['sqlite_autoindex_t7_1', 'sqlite_autoindex_t7_2', 'sqlite_autoindex_t7_3'],
                'drop_autoindex_error' => 'index associated with UNIQUE or PRIMARY KEY constraint cannot be dropped',
            ],
        ];
    }

    /**
     * @return list<array{upstream:string,sql:string,object_name:string,object_type:string,error:string}>
     */
    public static function reservedSqliteObjectNameCases(): array
    {
        $error = 'object name reserved for internal use';

        return [
            ['upstream' => 'index-18.1', 'sql' => 'CREATE TABLE sqlite_t1(a, b, c)', 'object_name' => 'sqlite_t1', 'object_type' => 'table', 'error' => $error . ': sqlite_t1'],
            ['upstream' => 'index-18.2', 'sql' => 'CREATE INDEX sqlite_i1 ON t7(c)', 'object_name' => 'sqlite_i1', 'object_type' => 'index', 'error' => $error . ': sqlite_i1'],
            ['upstream' => 'index-18.3', 'sql' => 'CREATE VIEW sqlite_v1 AS SELECT * FROM t7', 'object_name' => 'sqlite_v1', 'object_type' => 'view', 'error' => $error . ': sqlite_v1'],
            ['upstream' => 'index-18.4', 'sql' => 'CREATE TRIGGER sqlite_tr1 BEFORE INSERT ON t7 BEGIN SELECT 1; END', 'object_name' => 'sqlite_tr1', 'object_type' => 'trigger', 'error' => $error . ': sqlite_tr1'],
        ];
    }

    /**
     * @return array{
     *   source:string,
     *   t1:list<array{rowid:int,a:int|null,b:int,c:int}>,
     *   t1a_rowids:list<int>,
     *   t1b_rowids:list<int>,
     *   t1_stat_steps:array<string, array{table:string,t1a:string,t1b:string,t1c?:string,integrity:string}>,
     *   t2:list<array{rowid:int,a:int|null,b:int}>,
     *   t2a1_rowids:list<int>,
     *   t2a2_rowids:list<int>,
     *   t2a1_count:int,
     *   t2a2_count:int
     * }
     */
    public static function partialIndexScenario(): array
    {
        $t1 = [];
        for ($value = 1; $value <= 20; $value++) {
            $t1[] = [
                'rowid' => $value,
                'a' => $value % 3 === 0 ? null : $value,
                'b' => $value,
                'c' => $value,
            ];
        }

        $t1aRowids = self::partialIndexRowids($t1, static fn (array $row): bool => $row['a'] !== null);
        $t1bRowids = self::partialIndexRowids($t1, static fn (array $row): bool => $row['b'] > 10);

        $t2 = [];
        for ($value = 1; $value < 1000; $value++) {
            $t2[] = [
                'rowid' => $value,
                'a' => $value % 2 === 0 ? null : $value,
                'b' => $value,
            ];
        }

        $t2a1Rowids = self::partialIndexRowids($t2, static fn (array $row): bool => $row['a'] !== null);
        $t2AfterDrop = array_map(
            static fn (array $row): array => ['rowid' => $row['rowid'], 'a' => $row['b'], 'b' => $row['b'] + 10000],
            $t2,
        );
        $t2a2Rowids = self::partialIndexRowids(
            $t2AfterDrop,
            static fn (array $row): bool => $row['a'] < 100 || $row['a'] > 200,
        );

        return [
            'source' => 'index6.test index6-1.1 through index6-2.104',
            't1' => $t1,
            't1a_rowids' => $t1aRowids,
            't1b_rowids' => $t1bRowids,
            't1_stat_steps' => [
                'index6-1.10' => ['table' => '20', 't1a' => '14 1', 't1b' => '10 1', 'integrity' => 'ok'],
                'index6-1.11-update-a-b' => ['table' => '20', 't1a' => '20 1', 't1b' => '10 1', 'integrity' => 'ok'],
                'index6-1.11-null-a-b-plus-100' => ['table' => '20', 't1a' => '6 1', 't1b' => '20 1', 'integrity' => 'ok'],
                'index6-1.12-restore-a-b' => ['table' => '20', 't1a' => '13 1', 't1b' => '10 1', 'integrity' => 'ok'],
                'index6-1.13-delete-between' => ['table' => '15', 't1a' => '10 1', 't1b' => '8 1', 'integrity' => 'ok'],
                'index6-1.14-reindex' => ['table' => '15', 't1a' => '10 1', 't1b' => '8 1', 'integrity' => 'ok'],
                'index6-1.15-add-c-index' => ['table' => '15', 't1a' => '10 1', 't1b' => '8 1', 't1c' => '15 1', 'integrity' => 'ok'],
            ],
            't2' => $t2AfterDrop,
            't2a1_rowids' => $t2a1Rowids,
            't2a2_rowids' => $t2a2Rowids,
            't2a1_count' => count($t2a1Rowids),
            't2a2_count' => count($t2a2Rowids),
        ];
    }

    /**
     * @param list<array<string, mixed>> $rows
     * @return list<int>
     */
    public static function partialIndexRowids(array $rows, callable $predicate): array
    {
        $rowids = [];
        foreach ($rows as $row) {
            if ($predicate($row)) {
                $rowids[] = (int) $row['rowid'];
            }
        }

        return $rowids;
    }

    /**
     * @param array{column:string,operator:string,value?:mixed} $term
     */
    public static function partialIndexTermImplies(array $term, string $partialPredicate): bool
    {
        $column = $term['column'] ?? '';
        $operator = strtoupper((string) ($term['operator'] ?? ''));
        $value = $term['value'] ?? null;

        if ($partialPredicate === 'a IS NOT NULL') {
            return $column === 'a' && in_array($operator, ['=', '>', '>=', '<', '<=', 'IS NOT NULL'], true) && $value !== null;
        }

        if ($partialPredicate === 'a<100 OR a>200') {
            return $column === 'a'
                && $operator === '='
                && is_int($value)
                && ($value < 100 || $value > 200);
        }

        return false;
    }

    private static function applyNumericAffinity(string $literal): int|float|string
    {
        if (!preg_match('/^[+-]?(?:(?:\d+(?:\.\d*)?)|(?:\.\d+))(?:[eE][+-]?\d+)?$/', $literal)) {
            return $literal;
        }

        $value = (float) $literal;
        if (is_finite($value) && floor($value) === $value && $value >= PHP_INT_MIN && $value <= PHP_INT_MAX) {
            return (int) $value;
        }

        return $value;
    }

    /**
     * @param list<mixed> $left
     * @param list<mixed> $right
     */
    private static function compareRecords(array $left, array $right): int
    {
        $count = max(count($left), count($right));
        for ($index = 0; $index < $count; $index++) {
            $leftValue = $left[$index] ?? null;
            $rightValue = $right[$index] ?? null;
            if ($leftValue === $rightValue) {
                continue;
            }
            if ($leftValue === null) {
                return -1;
            }
            if ($rightValue === null) {
                return 1;
            }
            if (is_int($leftValue) && is_int($rightValue)) {
                return $leftValue <=> $rightValue;
            }

            return strcmp((string) $leftValue, (string) $rightValue);
        }

        return 0;
    }
}
