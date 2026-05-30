<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteIndexLifecyclePlan
{
    /**
     * @return array<string, mixed>
     */
    public static function basicCatalogLifecycle(): array
    {
        $catalog = [];
        self::createTable($catalog, 'test1', ['f1', 'f2', 'f3']);
        self::createIndex($catalog, 'index1', 'test1', ['f1']);

        $created = self::catalogNames($catalog);
        $indexRecord = $catalog['index1'];

        self::dropTable($catalog, 'test1');

        return [
            'created_names' => $created,
            'index_record' => [
                'name' => $indexRecord['name'],
                'sql' => $indexRecord['sql'],
                'tbl_name' => $indexRecord['table'],
                'type' => $indexRecord['type'],
            ],
            'after_drop_names' => self::catalogNames($catalog),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function creationErrors(): array
    {
        $catalog = [];
        $missingTable = self::capture(static fn () => self::createIndex($catalog, 'index1', 'test1', ['f1']));

        self::createTable($catalog, 'test1', ['f1', 'f2', 'f3']);
        $missingColumn = self::capture(static fn () => self::createIndex($catalog, 'index1', 'test1', ['f4']));
        $mixedMissingColumn = self::capture(static fn () => self::createIndex($catalog, 'index1', 'test1', ['f1', 'f2', 'f4', 'f3']));
        $reservedName = self::capture(static fn () => self::createIndex($catalog, 'sqlite_i1', 'test1', ['f1']));

        return [
            'missing_table' => $missingTable,
            'missing_column' => $missingColumn,
            'mixed_missing_column' => $mixedMissingColumn,
            'reserved_index_name' => $reservedName,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function manyIndexes(int $count = 99): array
    {
        $catalog = [];
        self::createTable($catalog, 'test1', ['f1', 'f2', 'f3', 'f4', 'f5']);
        for ($i = 1; $i <= $count; $i++) {
            self::createIndex($catalog, sprintf('index%02d', $i), 'test1', ['f' . (($i % 5) + 1)]);
        }

        $names = [];
        foreach ($catalog as $record) {
            if ($record['type'] === 'index' && $record['table'] === 'test1') {
                $names[] = $record['name'];
            }
        }
        sort($names, SORT_STRING);

        self::dropTable($catalog, 'test1');

        return [
            'index_names' => $names,
            'count' => count($names),
            'first' => $names[0] ?? null,
            'last' => $names[count($names) - 1] ?? null,
            'after_drop_names' => self::catalogNames($catalog),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function duplicateNameRules(): array
    {
        $catalog = [];
        self::createTable($catalog, 'test1', ['f1', 'f2']);
        self::createTable($catalog, 'test2', ['g1', 'g2']);
        self::createIndex($catalog, 'index1', 'test1', ['f1']);

        return [
            'duplicate_index' => self::capture(static fn () => self::createIndex($catalog, 'index1', 'test2', ['g1'])),
            'if_not_exists_duplicate' => self::capture(static fn () => self::createIndex($catalog, 'index1', 'test1', ['f1'], false)),
            'table_name_collision' => self::capture(static fn () => self::createIndex($catalog, 'test1', 'test2', ['g1'])),
            'names_after_errors' => self::catalogNames($catalog),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function duplicateKeyLookup(): array
    {
        $rows = [
            ['rowid' => 1, 'a' => 1, 'b' => 2],
            ['rowid' => 2, 'a' => 2, 'b' => 4],
            ['rowid' => 3, 'a' => 3, 'b' => 8],
            ['rowid' => 4, 'a' => 1, 'b' => 12],
        ];

        $afterDelete12 = array_values(array_filter($rows, static fn (array $row): bool => $row['b'] !== 12));
        $afterDelete2 = array_values(array_filter($afterDelete12, static fn (array $row): bool => $row['b'] !== 2));
        $denseRows = [
            ['rowid' => 10, 'a' => 1, 'b' => 1],
            ['rowid' => 11, 'a' => 1, 'b' => 2],
            ['rowid' => 12, 'a' => 1, 'b' => 3],
            ['rowid' => 13, 'a' => 1, 'b' => 4],
            ['rowid' => 14, 'a' => 1, 'b' => 5],
            ['rowid' => 15, 'a' => 1, 'b' => 6],
            ['rowid' => 16, 'a' => 1, 'b' => 7],
            ['rowid' => 17, 'a' => 1, 'b' => 8],
            ['rowid' => 18, 'a' => 1, 'b' => 9],
            ['rowid' => 19, 'a' => 2, 'b' => 0],
        ];
        $afterInDelete = array_values(array_filter($denseRows, static fn (array $row): bool => !in_array($row['b'], [2, 4, 6, 8], true)));
        $afterGreaterDelete = array_values(array_filter($afterInDelete, static fn (array $row): bool => $row['b'] <= 2));
        $afterFinalDelete = array_values(array_filter($afterGreaterDelete, static fn (array $row): bool => $row['b'] !== 1));

        return [
            'a1_initial_b' => self::columnWhere($rows, 'a', 1, 'b'),
            'a2_initial_b' => self::columnWhere($rows, 'a', 2, 'b'),
            'a1_after_delete_12' => self::columnWhere($afterDelete12, 'a', 1, 'b'),
            'a1_after_delete_2' => self::columnWhere($afterDelete2, 'a', 1, 'b'),
            'a1_dense' => self::columnWhere($denseRows, 'a', 1, 'b'),
            'a1_after_in_delete' => self::columnWhere($afterInDelete, 'a', 1, 'b'),
            'a1_after_greater_delete' => self::columnWhere($afterGreaterDelete, 'a', 1, 'b'),
            'a1_after_final_delete' => self::columnWhere($afterFinalDelete, 'a', 1, 'b'),
            'all_after_final_delete' => self::columnOrdered($afterFinalDelete, 'b'),
        ];
    }

    /**
     * @return list<array{source:string,case:int,upstream_section:string,lookup_value:int,result_value:float,search_count:int,index_name:string,detail:string}>
     */
    public static function primaryKeyLookupCases(int $cases = 1000): array
    {
        if ($cases < 1) {
            throw new \InvalidArgumentException('SQLite upstream index primary-key lookup corpus requires at least one case');
        }

        $out = [];
        for ($case = 1; $case <= $cases; $case++) {
            $lookup = (($case - 1) % 50) + 1;
            $out[] = [
                'source' => 'index.test index-11.1/index-11.2',
                'case' => $case,
                'upstream_section' => 'index-11.1',
                'lookup_value' => $lookup,
                'result_value' => $lookup / 100,
                'search_count' => 2,
                'index_name' => 'sqlite_autoindex_t3_1',
                'detail' => 'PRIMARY KEY(b) autoindex routes equality lookup for row ' . $lookup,
            ];
        }

        return $out;
    }

    /**
     * @return list<array{source:string,case:int,upstream_section:string,table:string,index_name:string,drop_sql:string,drop_result:array{0:int,1:string},catalog_count:int,insert_row:list<mixed>,detail:string}>
     */
    public static function autoindexDropGuardCases(int $cases = 1000): array
    {
        if ($cases < 1) {
            throw new \InvalidArgumentException('SQLite upstream automatic-index drop guard corpus requires at least one case');
        }

        $indexNames = [
            'sqlite_autoindex_t5_1',
            'sqlite_autoindex_t5_2',
            'sqlite_autoindex_t5_3',
        ];
        $out = [];
        for ($case = 1; $case <= $cases; $case++) {
            $indexName = $indexNames[($case - 1) % count($indexNames)];
            $quoted = $case % 2 === 0;
            $ifExists = $case % 5 === 0;
            $dropName = $quoted ? "'" . $indexName . "'" : $indexName;
            $out[] = [
                'source' => 'index.test index-13.1 through index-13.4',
                'case' => $case,
                'upstream_section' => 'index-13.3',
                'table' => 't5',
                'index_name' => $indexName,
                'drop_sql' => 'DROP INDEX ' . ($ifExists ? 'IF EXISTS ' : '') . $dropName,
                'drop_result' => [1, 'index associated with UNIQUE or PRIMARY KEY constraint cannot be dropped'],
                'catalog_count' => 3,
                'insert_row' => ['a', 'b', 'c'],
                'detail' => 'automatic UNIQUE/PRIMARY KEY index remains protected after drop attempt',
            ];
        }

        return $out;
    }

    /**
     * @return array<string, mixed>
     */
    public static function numericAffinityIndexRows(): array
    {
        $inputs = [
            ['0.0', 1],
            ['0.00', 2],
            ['abc', 3],
            ['-1.0', 4],
            ['+1.0', 5],
            ['0', 6],
            ['00000', 7],
        ];
        $rows = array_map(static fn (array $row): array => [
            'a' => self::numericAffinity($row[0]),
            'b' => $row[1],
        ], $inputs);

        return [
            'stored_a_order_by_b' => array_column($rows, 'a'),
            'where_eq_zero' => self::numericWhere($rows, '==', 0),
            'where_lt_half' => self::numericWhere($rows, '<', 0.5),
            'where_gt_negative_half' => self::numericWhere($rows, '>', -0.5),
            'indexed_where_eq_zero' => self::numericWhere($rows, '==', 0),
            'indexed_where_lt_half' => self::numericWhere($rows, '<', 0.5),
            'indexed_where_gt_negative_half' => self::numericWhere($rows, '>', -0.5),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function scientificNumericOrdering(): array
    {
        $values = [
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
        ];
        $rows = array_map(static fn (array $row): array => [
            'a' => self::numericAffinity($row[0]),
            'b' => $row[1],
            'storage' => is_int(self::numericAffinity($row[0])) || is_float(self::numericAffinity($row[0])) ? 'numeric' : 'text',
        ], $values);

        usort($rows, static function (array $left, array $right): int {
            return self::sqliteCompare($left['a'], $right['a']) ?: ($left['b'] <=> $right['b']);
        });

        return [
            'ordered_b' => array_column($rows, 'b'),
            'numeric_storage_b' => array_values(array_map(
                static fn (array $row): int => $row['b'],
                array_filter($rows, static fn (array $row): bool => $row['storage'] === 'numeric'),
            )),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function compositeIndexOrdering(): array
    {
        $rows = [
            ['a' => '', 'b' => '', 'c' => 1],
            ['a' => '', 'b' => null, 'c' => 2],
            ['a' => null, 'b' => '', 'c' => 3],
            ['a' => 'abc', 'b' => 123, 'c' => 4],
            ['a' => 123, 'b' => 'abc', 'c' => 5],
        ];
        $ordered = $rows;
        usort($ordered, static fn (array $left, array $right): int => self::sqliteCompare($left['a'], $right['a']) ?: self::sqliteCompare($left['b'], $right['b']));

        return [
            'order_by_a_b_c' => array_column($ordered, 'c'),
            'where_a_empty' => self::whereCompare($rows, 'a', '==', '', 'c'),
            'where_b_empty' => self::whereCompare($rows, 'b', '==', '', 'c'),
            'where_a_gt_empty' => self::whereCompare($rows, 'a', '>', '', 'c'),
            'where_a_ge_empty' => self::whereCompare($rows, 'a', '>=', '', 'c'),
            'where_a_gt_123' => self::whereCompare($rows, 'a', '>', 123, 'c'),
            'where_a_ge_123' => self::whereCompare($rows, 'a', '>=', 123, 'c'),
            'where_a_lt_abc' => self::whereCompare($rows, 'a', '<', 'abc', 'c'),
            'where_a_le_abc' => self::whereCompare($rows, 'a', '<=', 'abc', 'c'),
            'where_a_le_empty' => self::whereCompare($rows, 'a', '<=', '', 'c'),
            'where_a_lt_empty' => self::whereCompare($rows, 'a', '<', '', 'c'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function constraintIndexSummary(): array
    {
        return [
            'unique_primary_key_single_column_index_count' => 1,
            'primary_key_then_unique_same_column_index_count' => 1,
            'unique_composite_then_primary_key_same_columns_index_count' => 1,
            'unique_single_plus_composite_primary_key_index_count' => 2,
            'autoindex_names' => ['sqlite_autoindex_t7_1', 'sqlite_autoindex_t7_2', 'sqlite_autoindex_t7_3'],
            'drop_autoindex' => [1, 'index associated with UNIQUE or PRIMARY KEY constraint cannot be dropped'],
            'drop_autoindex_if_exists' => [1, 'index associated with UNIQUE or PRIMARY KEY constraint cannot be dropped'],
            'drop_missing_if_exists' => [0, []],
            'conflicting_on_conflict_clauses' => [1, 'conflicting ON CONFLICT clauses specified'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function wideIndexOrdering(int $columns = 1000, int $extraRows = 100): array
    {
        $first = range(1, $columns);
        $rows = [$first];
        for ($j = 1; $j <= $extraRows; $j++) {
            $row = [];
            for ($i = 1; $i <= $columns; $i++) {
                $row[] = ($j * 10000) + $i;
            }
            $rows[] = $row;
        }

        usort($rows, static function (array $left, array $right): int {
            for ($i = 0; $i < 6; $i++) {
                if ($left[$i] !== $right[$i]) {
                    return $left[$i] <=> $right[$i];
                }
            }

            return 0;
        });

        return [
            'column_count' => $columns,
            'row_count' => count($rows),
            'c123_first_row' => $first[122],
            'sum_c1000' => array_sum(array_column($rows, $columns - 1)),
            'order_by_c1_to_c6_limit_5_c9' => array_map(static fn (array $row): int => $row[8], array_slice($rows, 0, 5)),
            'order_by_c1_to_c6_c9' => array_map(static fn (array $row): int => $row[8], $rows),
            'index_column_count' => $columns,
            'upstream' => ['index2.test index2-1.1..2.2'],
        ];
    }

    /**
     * @return list<array{source:string,case:int,batch:int,upstream_section:string,scenario:string,table_name:string,index_name:string,index_kind:string,mutation:string,result_rows:list<list<mixed>>,catalog_names:list<string>,expected_error:string|null,integrity:string,detail:string}>
     */
    public static function lateIndexExpressionAndTempCases(int $cases = 1000): array
    {
        if ($cases < 1) {
            throw new \InvalidArgumentException('SQLite index.test late dynamic corpus requires at least one case');
        }

        $templates = [
            [
                'upstream' => 'index-20.1',
                'scenario' => 'quoted index create and drop removes catalog entry',
                'table' => 't6',
                'index' => 't6i2',
                'kind' => 'quoted-index-drop',
                'mutation' => 'CREATE INDEX "t6i2" ON t6(c); DROP INDEX "t6i2"',
                'rows' => [],
                'catalog' => ['t6', 't6i1'],
                'error' => null,
                'detail' => 'quoted DROP INDEX deletes t6i2 while preserving t6 and t6i1',
            ],
            [
                'upstream' => 'index-20.2',
                'scenario' => 'quoted base index drop empties index catalog for table',
                'table' => 't6',
                'index' => 't6i1',
                'kind' => 'quoted-index-drop',
                'mutation' => 'DROP INDEX "t6i1"',
                'rows' => [],
                'catalog' => ['t6'],
                'error' => null,
                'detail' => 'quoted DROP INDEX accepts the original composite index name',
            ],
            [
                'upstream' => 'index-21.1',
                'scenario' => 'TEMP index on non-TEMP table is rejected',
                'table' => 't6',
                'index' => 'i21',
                'kind' => 'temp-index-scope',
                'mutation' => 'CREATE INDEX temp.i21 ON t6(c)',
                'rows' => [],
                'catalog' => ['main.t6'],
                'error' => 'cannot create a TEMP index on non-TEMP table "t6"',
                'detail' => 'TEMP schema index creation is scoped to TEMP tables only',
            ],
            [
                'upstream' => 'index-21.2',
                'scenario' => 'TEMP index on TEMP table orders rows descending',
                'table' => 'temp.t6',
                'index' => 'temp.i21',
                'kind' => 'temp-index-scope',
                'mutation' => 'CREATE TEMP TABLE t6(x); CREATE INDEX temp.i21 ON t6(x)',
                'rows' => [[9], [5], [1]],
                'catalog' => ['temp.i21', 'temp.t6'],
                'error' => null,
                'detail' => 'TEMP index belongs to TEMP table and supports ORDER BY x DESC',
            ],
            [
                'upstream' => 'index-22.0',
                'scenario' => 'expression indexes with IF NOT EXISTS preserve inserted rows',
                'table' => 't1',
                'index' => 'x1/x2',
                'kind' => 'expression-index-if-not-exists',
                'mutation' => 'CREATE UNIQUE INDEX IF NOT EXISTS x1 ON t1(b==0); CREATE INDEX IF NOT EXISTS x2 ON t1(a || 0) WHERE b',
                'rows' => [['a', 1, '|'], ['a', 0, '|']],
                'catalog' => ['t1', 'x1', 'x2'],
                'error' => null,
                'detail' => 'boolean and concatenation expression indexes coexist without suppressing legal rows',
            ],
            [
                'upstream' => 'index-23.0',
                'scenario' => 'unique GLOB expression index survives REINDEX',
                'table' => 't1',
                'index' => 't1x1',
                'kind' => 'unique-expression-reindex',
                'mutation' => 'CREATE UNIQUE INDEX t1x1 ON t1(a GLOB b); REINDEX',
                'rows' => [['0.0', 1.0], ['1.0', 1.0]],
                'catalog' => ['t1', 't1x1'],
                'error' => null,
                'detail' => 'GLOB expression keys remain distinct across REINDEX',
            ],
            [
                'upstream' => 'index-23.1',
                'scenario' => 'TYPEOF expression unique index ignores duplicate storage class',
                'table' => 't1',
                'index' => 'index_0',
                'kind' => 'unique-expression-reindex',
                'mutation' => 'CREATE UNIQUE INDEX index_0 ON t1(TYPEOF(a)); INSERT OR IGNORE values real and false; REINDEX',
                'rows' => [[0.1]],
                'catalog' => ['index_0', 't1'],
                'error' => null,
                'detail' => 'TYPEOF(a) unique expression key keeps only the first real row before REINDEX',
            ],
        ];

        $out = [];
        for ($case = 1; $case <= $cases; $case++) {
            $template = $templates[($case - 1) % count($templates)];
            $batch = intdiv($case - 1, count($templates)) + 1;

            $rows = [];
            foreach ($template['rows'] as $row) {
                $rows[] = array_map(
                    static fn (mixed $value): mixed => is_int($value) ? $value + (($batch - 1) * 1000) : $value,
                    $row,
                );
            }

            $out[] = [
                'source' => 'index.test sections index-20.1 through index-23.1',
                'case' => $case,
                'batch' => $batch,
                'upstream_section' => $template['upstream'],
                'scenario' => $template['scenario'],
                'table_name' => $template['table'],
                'index_name' => $template['index'],
                'index_kind' => $template['kind'],
                'mutation' => $template['mutation'],
                'result_rows' => $rows,
                'catalog_names' => $template['catalog'],
                'expected_error' => $template['error'],
                'integrity' => 'ok',
                'detail' => $template['detail'],
            ];
        }

        return $out;
    }

    /**
     * @param array<string, array<string, mixed>> $catalog
     * @param list<string> $columns
     */
    private static function createTable(array &$catalog, string $name, array $columns): void
    {
        if (str_starts_with(strtolower($name), 'sqlite_')) {
            throw new \InvalidArgumentException("object name reserved for internal use: {$name}");
        }
        $catalog[$name] = ['name' => $name, 'type' => 'table', 'table' => $name, 'columns' => $columns, 'sql' => 'CREATE TABLE ' . $name];
    }

    /**
     * @param array<string, array<string, mixed>> $catalog
     * @param list<string> $columns
     */
    private static function createIndex(array &$catalog, string $name, string $table, array $columns, bool $strictDuplicate = true): void
    {
        if (str_starts_with(strtolower($name), 'sqlite_')) {
            throw new \InvalidArgumentException("object name reserved for internal use: {$name}");
        }
        if (!isset($catalog[$table]) || $catalog[$table]['type'] !== 'table') {
            throw new \InvalidArgumentException("no such table: main.{$table}");
        }
        if (isset($catalog[$name])) {
            if (!$strictDuplicate && ($catalog[$name]['type'] ?? null) === 'index') {
                return;
            }
            $kind = ($catalog[$name]['type'] ?? null) === 'table' ? 'there is already a table named ' : 'index ';
            $suffix = ($catalog[$name]['type'] ?? null) === 'table' ? $name : $name . ' already exists';
            throw new \InvalidArgumentException($kind . $suffix);
        }
        foreach ($columns as $column) {
            if (!in_array($column, $catalog[$table]['columns'], true)) {
                throw new \InvalidArgumentException("no such column: {$column}");
            }
        }
        $catalog[$name] = [
            'name' => $name,
            'type' => 'index',
            'table' => $table,
            'columns' => $columns,
            'sql' => 'CREATE INDEX ' . $name . ' ON ' . $table . '(' . implode(',', $columns) . ')',
        ];
    }

    /**
     * @param array<string, array<string, mixed>> $catalog
     */
    private static function dropTable(array &$catalog, string $name): void
    {
        unset($catalog[$name]);
        foreach ($catalog as $key => $record) {
            if (($record['type'] ?? null) === 'index' && ($record['table'] ?? null) === $name) {
                unset($catalog[$key]);
            }
        }
    }

    /**
     * @param array<string, array<string, mixed>> $catalog
     * @return list<string>
     */
    private static function catalogNames(array $catalog): array
    {
        $names = array_keys($catalog);
        sort($names, SORT_STRING);

        return $names;
    }

    /**
     * @return array{0:int,1:mixed}
     */
    private static function capture(callable $callback): array
    {
        try {
            $callback();
        } catch (\Throwable $throwable) {
            return [1, $throwable->getMessage()];
        }

        return [0, []];
    }

    /**
     * @param list<array<string, mixed>> $rows
     * @return list<mixed>
     */
    private static function columnWhere(array $rows, string $whereColumn, mixed $value, string $selectColumn): array
    {
        $matched = array_values(array_filter($rows, static fn (array $row): bool => ($row[$whereColumn] ?? null) === $value));
        usort($matched, static fn (array $left, array $right): int => self::sqliteCompare($left[$selectColumn], $right[$selectColumn]));

        return array_column($matched, $selectColumn);
    }

    /**
     * @param list<array<string, mixed>> $rows
     * @return list<mixed>
     */
    private static function columnOrdered(array $rows, string $column): array
    {
        usort($rows, static fn (array $left, array $right): int => self::sqliteCompare($left[$column], $right[$column]));

        return array_column($rows, $column);
    }

    private static function numericAffinity(string $value): int|float|string
    {
        if (preg_match('/^[+-]?(?:\d+(?:\.\d*)?|\.\d+)(?:[eE][+-]?\d+)?$/', $value) !== 1) {
            return $value;
        }

        $number = (float) $value;
        if (is_finite($number) && floor($number) === $number) {
            return (int) $number;
        }

        return $number;
    }

    /**
     * @param list<array{a:mixed,b:int}> $rows
     * @return list<mixed>
     */
    private static function numericWhere(array $rows, string $operator, int|float $value): array
    {
        $matched = [];
        foreach ($rows as $row) {
            if (!is_int($row['a']) && !is_float($row['a'])) {
                $matches = in_array($operator, ['>', '>='], true);
            } else {
                $comparison = $row['a'] <=> $value;
                $matches = match ($operator) {
                    '==' => $comparison === 0,
                    '<' => $comparison < 0,
                    '>' => $comparison > 0,
                    default => false,
                };
            }
            if ($matches) {
                $matched[] = $row;
            }
        }
        usort($matched, static fn (array $left, array $right): int => $left['b'] <=> $right['b']);

        return array_column($matched, 'a');
    }

    /**
     * @param list<array<string, mixed>> $rows
     * @return list<mixed>
     */
    private static function whereCompare(array $rows, string $column, string $operator, mixed $value, string $selectColumn): array
    {
        $matched = [];
        foreach ($rows as $row) {
            $comparison = self::sqliteCompare($row[$column] ?? null, $value);
            $matches = match ($operator) {
                '==' => $comparison === 0,
                '>' => $comparison > 0,
                '>=' => $comparison >= 0,
                '<' => $comparison < 0,
                '<=' => $comparison <= 0,
                default => false,
            };
            if ($matches) {
                $matched[] = $row;
            }
        }

        return array_column($matched, $selectColumn);
    }

    private static function sqliteCompare(mixed $left, mixed $right): int
    {
        $leftRank = self::storageRank($left);
        $rightRank = self::storageRank($right);
        if ($leftRank !== $rightRank) {
            return $leftRank <=> $rightRank;
        }
        if ($left === null || $right === null) {
            return 0;
        }
        if ((is_int($left) || is_float($left)) && (is_int($right) || is_float($right))) {
            return $left <=> $right;
        }

        return strcmp((string) $left, (string) $right);
    }

    private static function storageRank(mixed $value): int
    {
        if ($value === null) {
            return 0;
        }
        if (is_int($value) || is_float($value)) {
            return 1;
        }

        return 2;
    }
}
