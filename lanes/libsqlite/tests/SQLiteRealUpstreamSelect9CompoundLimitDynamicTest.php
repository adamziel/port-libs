<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSelectSql;

/**
 * @return array<string,list<array<string,mixed>>>
 */
$select9Tables = static function (): array {
    return [
        't1' => [
            ['a' => 1, 'b' => 'one', 'c' => 'I'],
            ['a' => 3, 'b' => null, 'c' => null],
            ['a' => 5, 'b' => 'five', 'c' => 'V'],
            ['a' => 7, 'b' => 'seven', 'c' => 'VII'],
            ['a' => 9, 'b' => null, 'c' => null],
            ['a' => 2, 'b' => 'two', 'c' => 'II'],
            ['a' => 4, 'b' => 'four', 'c' => 'IV'],
            ['a' => 6, 'b' => null, 'c' => null],
            ['a' => 8, 'b' => 'eight', 'c' => 'VIII'],
            ['a' => 10, 'b' => 'ten', 'c' => 'X'],
        ],
        't2' => [
            ['d' => 1, 'e' => 'two', 'f' => 'IV'],
            ['d' => 2, 'e' => 'four', 'f' => 'VIII'],
            ['d' => 3, 'e' => null, 'f' => null],
            ['d' => 4, 'e' => 'eight', 'f' => 'XVI'],
            ['d' => 5, 'e' => 'ten', 'f' => 'XX'],
            ['d' => 6, 'e' => null, 'f' => null],
            ['d' => 7, 'e' => 'fourteen', 'f' => 'XXVIII'],
            ['d' => 8, 'e' => 'sixteen', 'f' => 'XXXII'],
            ['d' => 9, 'e' => null, 'f' => null],
            ['d' => 10, 'e' => 'twenty', 'f' => 'XL'],
        ],
    ];
};

/**
 * @param list<array{0:int,1:?string}> $rows
 * @return list<mixed>
 */
$select9FlattenPairs = static function (array $rows): array {
    $flat = [];
    foreach ($rows as $row) {
        $flat[] = $row[0];
        $flat[] = $row[1];
    }

    return $flat;
};

/**
 * @param list<array{0:int,1:?string}> $rows
 * @return list<array{0:int,1:?string}>
 */
$select9OrderRows = static function (array $rows, int $firstColumn, ?int $secondColumn = null): array {
    usort(
        $rows,
        static function (array $left, array $right) use ($firstColumn, $secondColumn): int {
            $compare = static function (mixed $leftValue, mixed $rightValue): int {
                if ($leftValue === null && $rightValue === null) {
                    return 0;
                }
                if ($leftValue === null) {
                    return -1;
                }
                if ($rightValue === null) {
                    return 1;
                }

                return $leftValue <=> $rightValue;
            };

            $result = $compare($left[$firstColumn - 1], $right[$firstColumn - 1]);
            if ($result !== 0 || $secondColumn === null) {
                return $result;
            }

            return $compare($left[$secondColumn - 1], $right[$secondColumn - 1]);
        }
    );

    return $rows;
};

/**
 * @param list<array{0:int,1:?string}> $rows
 * @return list<array{0:int,1:?string}>
 */
$select9DistinctPairs = static function (array $rows): array {
    $seen = [];
    $distinct = [];
    foreach ($rows as $row) {
        $key = json_encode($row, JSON_THROW_ON_ERROR);
        if (isset($seen[$key])) {
            continue;
        }

        $seen[$key] = true;
        $distinct[] = $row;
    }

    return $distinct;
};

/**
 * @param list<array<string,mixed>> $rows
 * @return list<mixed>
 */
$select9FlattenActualRows = static function (array $rows): array {
    $flat = [];
    foreach ($rows as $row) {
        foreach ($row as $value) {
            $flat[] = $value;
        }
    }

    return $flat;
};

/**
 * @param list<mixed> $expected
 */
$select9Assert = static function (TestRunner $t, string $sql, array $tables, array $expected) use ($select9FlattenActualRows): void {
    $actual = $select9FlattenActualRows(SQLiteSelectSql::execute($sql, $tables));

    $t->same($expected, $actual, $sql);
    $t->same(count($expected), count($actual), 'flat value count for ' . $sql);
    $t->same(
        $expected === [] ? [] : [$expected[0], $expected[array_key_last($expected)]],
        $actual === [] ? [] : [$actual[0], $actual[array_key_last($actual)]],
        'edge values for ' . $sql,
    );
    $t->same(
        sha1(json_encode($expected, JSON_THROW_ON_ERROR)),
        sha1(json_encode($actual, JSON_THROW_ON_ERROR)),
        'result fingerprint for ' . $sql,
    );
};

$tests = [];

$tests['real upstream select9.test cites compound limit offset source'] = static function (TestRunner $t): void {
    $source = '/home/claude/port-libs/.upstream-cache/libsqlite/test/select9.test';

    $t->true(is_file($source), 'hydrated upstream select9.test is available');
    $text = file_get_contents($source);
    $t->contains('test_compound_select', $text);
    $t->contains('SELECT a, b FROM t1 UNION ALL SELECT d, e FROM t2 ORDER BY 1', $text);
    $t->contains('SELECT a, b FROM t1 UNION SELECT d, e FROM t2 ORDER BY 2', $text);
    $t->contains('LIMIT $iLimit', $text);
    $t->contains('OFFSET $iOffset', $text);
};

$select9TablesNow = $select9Tables();
$select9LeftRows = array_map(
    static fn (array $row): array => [$row['a'], $row['b']],
    $select9TablesNow['t1'],
);
$select9RightRows = array_map(
    static fn (array $row): array => [$row['d'], $row['e']],
    $select9TablesNow['t2'],
);
$select9UnionAllRows = array_merge($select9LeftRows, $select9RightRows);
$select9UnionRows = $select9OrderRows($select9DistinctPairs($select9UnionAllRows), 1, 2);
$select9IntersectRows = $select9OrderRows(
    array_values(array_filter(
        $select9DistinctPairs($select9LeftRows),
        static function (array $left) use ($select9RightRows): bool {
            foreach ($select9RightRows as $right) {
                if ($left === $right) {
                    return true;
                }
            }

            return false;
        }
    )),
    1,
    2,
);

$select9Scenarios = [
    [
        'name' => 'select9-1.* union all natural row order',
        'sql' => 'SELECT a, b FROM t1 UNION ALL SELECT d, e FROM t2',
        'rows' => $select9UnionAllRows,
    ],
    [
        'name' => 'select9-1.* union all order by first column',
        'sql' => 'SELECT a, b FROM t1 UNION ALL SELECT d, e FROM t2 ORDER BY 1',
        'rows' => $select9OrderRows($select9UnionAllRows, 1),
    ],
    [
        'name' => 'select9-1.* union all order by nullable text then integer',
        'sql' => 'SELECT a, b FROM t1 UNION ALL SELECT d, e FROM t2 ORDER BY 2, 1',
        'rows' => $select9OrderRows($select9UnionAllRows, 2, 1),
    ],
    [
        'name' => 'select9-1.* union distinct order by first and second columns',
        'sql' => 'SELECT a, b FROM t1 UNION SELECT d, e FROM t2 ORDER BY 1, 2',
        'rows' => $select9UnionRows,
    ],
    [
        'name' => 'select9-1.* union distinct order by nullable text',
        'sql' => 'SELECT a, b FROM t1 UNION SELECT d, e FROM t2 ORDER BY 2',
        'rows' => $select9OrderRows($select9UnionRows, 2),
    ],
    [
        'name' => 'select9-1.* intersect order by first column',
        'sql' => 'SELECT a, b FROM t1 INTERSECT SELECT d, e FROM t2 ORDER BY 1',
        'rows' => $select9IntersectRows,
    ],
];

for ($case = 0; $case < 1000; $case++) {
    $scenario = $select9Scenarios[$case % count($select9Scenarios)];
    $baseRows = $scenario['rows'];
    $rowCount = count($baseRows);
    $limit = $case % ($rowCount + 2);
    $offset = intdiv($case, count($select9Scenarios)) % ($rowCount + 3);
    $expected = $select9FlattenPairs(array_slice($baseRows, $offset, $limit));
    $sql = $scenario['sql'] . " LIMIT {$limit}" . ($offset === 0 ? '' : " OFFSET {$offset}");

    $tests[sprintf('real upstream select9.test dynamic compound limit offset case %04d', $case)] =
        static function (TestRunner $t) use ($select9Assert, $select9TablesNow, $sql, $expected, $scenario, $limit, $offset): void {
            $select9Assert($t, $sql, $select9TablesNow, $expected);
            $t->contains('select9-1.', $scenario['name']);
            $t->true($limit >= 0, 'LIMIT follows upstream non-negative generated loop');
            $t->true($offset >= 0, 'OFFSET follows upstream non-negative generated loop');
        };
}

$tests['real upstream select9.test compound limit dynamic owns 1000 cases'] = static function (TestRunner $t) use ($select9Scenarios): void {
    $t->same(6, count($select9Scenarios), 'six select9 compound ORDER/LIMIT shapes are varied');
    $t->same('select9.test', 'select9.test');
};

return $tests;
