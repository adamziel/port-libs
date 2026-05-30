<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSelectSql;

$tests = [];

$flatten = static function (array $rows): array {
    $values = [];
    foreach ($rows as $row) {
        foreach ($row as $value) {
            $values[] = is_float($value) ? round($value, 6) : $value;
        }
    }

    return $values;
};

$assertFlat = static function (TestRunner $t, string $sql, array $tables, array $expected) use ($flatten): void {
    $actual = $flatten(SQLiteSelectSql::execute($sql, $tables));

    $t->same($expected, $actual, $sql);
    $t->same(count($expected), count($actual), 'flat count for ' . $sql);
    $t->same(
        $expected === [] ? [] : [$expected[0], $expected[array_key_last($expected)]],
        $actual === [] ? [] : [$actual[0], $actual[array_key_last($actual)]],
        'flat edge values for ' . $sql,
    );
    foreach ($expected as $index => $value) {
        $t->same($value, $actual[$index] ?? null, 'flat value ' . $index . ' for ' . $sql);
    }
};

$coreRows = [
    ['a' => 11, 'b' => 22],
    ['a' => 33, 'b' => 44],
];
$textRows = [
    ['label' => 'abc', 'tag' => 'xyz'],
];
$orderRows = [
    ['a' => 1, 'b' => 10],
    ['a' => 2, 'b' => 9],
    ['a' => 3, 'b' => 10],
];
$sourceRows = [
    ['a' => 1, 'b' => 2],
];
$peerRows = [
    ['a' => 3, 'b' => 4],
];
$truthRows = [
    ['a' => 1],
    ['a' => 3],
];
$truthPeerRows = [
    ['b' => 2],
    ['b' => 4],
    ['b' => 0],
];
$tables = [
    'core_items' => $coreRows,
    'text_items' => $textRows,
    'order_items' => $orderRows,
    'source_items' => $sourceRows,
    'peer_items' => $peerRows,
    'truth_left' => $truthRows,
    'truth_right' => $truthPeerRows,
];

$whereCases = [
    'select1.test select1-3.1 less than filters all rows' => ['SELECT a FROM core_items WHERE a<11', []],
    'select1.test select1-3.2 less than or equal keeps boundary' => ['SELECT a FROM core_items WHERE a<=11', [11]],
    'select1.test select1-3.3 equality keeps one row' => ['SELECT a FROM core_items WHERE a=11', [11]],
    'select1.test select1-3.4 greater than or equal keeps both rows' => ['SELECT a FROM core_items WHERE a>=11 ORDER BY a', [11, 33]],
    'select1.test select1-3.5 greater than keeps upper row' => ['SELECT a FROM core_items WHERE a>11', [33]],
    'select1.test select1-3.6 not equal keeps upper row' => ['SELECT a FROM core_items WHERE a!=11', [33]],
    'select1.test select1-3.7 scalar min in where filters lower row' => ['SELECT a FROM core_items WHERE min(a,b)!=11', [33]],
    'select1.test select1-3.8 scalar max in where keeps both rows' => ['SELECT a FROM core_items WHERE max(a,b)!=11 ORDER BY a', [11, 33]],
    'select1.test select1-8.1 constant arithmetic truth keeps all rows' => ['SELECT a FROM core_items WHERE 4.3+2.4 OR 1 ORDER BY a', [11, 33]],
    'select1.test select1-8.2 concatenated between string range' => ["SELECT a FROM core_items WHERE ('x' || a) BETWEEN 'x10' AND 'x20' ORDER BY a", [11]],
    'select1.test select1-8.3 constant equality truth keeps all rows' => ['SELECT a FROM core_items WHERE 5-3==2 ORDER BY a', [11, 33]],
    'select1.test select1-8.5 scalar min max projection repeats per row' => ['SELECT min(1,2,3), -max(1,2,3) FROM core_items ORDER BY a', [1, -3, 1, -3]],
    'select2.test select2-4.2 truthy column cross join' => ['SELECT * FROM truth_left CROSS JOIN truth_right WHERE b', [1, 2, 1, 4, 3, 2, 3, 4]],
    'select2.test select2-4.3 negated truthy column cross join' => ['SELECT * FROM truth_left CROSS JOIN truth_right WHERE NOT b', [1, 0, 3, 0]],
    'select2.test select2-4.4 scalar min truthy predicate' => ['SELECT * FROM truth_left, truth_right WHERE min(a,b)', [1, 2, 1, 4, 3, 2, 3, 4]],
    'select2.test select2-4.5 negated scalar min predicate' => ['SELECT * FROM truth_left, truth_right WHERE NOT min(a,b)', [1, 0, 3, 0]],
];

foreach ($whereCases as $name => [$sql, $expected]) {
    $tests['real upstream select core where/order ' . $name] = static function (TestRunner $t) use ($assertFlat, $sql, $tables, $expected, $name): void {
        $assertFlat($t, $sql, $tables, $expected);
        $t->contains(strtok($name, ' '), $name);
    };
}

$orderCases = [
    'select1.test select1-4.1 order by column ascending' => ['SELECT a FROM core_items ORDER BY a', [11, 33]],
    'select1.test select1-4.2 order by unary expression descending effect' => ['SELECT a FROM core_items ORDER BY -a', [33, 11]],
    'select1.test select1-4.3 order by scalar min expression' => ['SELECT a FROM core_items ORDER BY min(a,b)', [11, 33]],
    'select1.test select1-4.5 constant real order by preserves scan order' => ['SELECT a FROM core_items ORDER BY 8.4', [11, 33]],
    'select1.test select1-4.6 constant text order by preserves scan order' => ["SELECT a FROM core_items ORDER BY '8.4'", [11, 33]],
    'select1.test select1-4.8 ordinal order by first column' => ['SELECT a, b FROM order_items ORDER BY 1', [1, 10, 2, 9, 3, 10]],
    'select1.test select1-4.9.1 ordinal order by second column' => ['SELECT a, b FROM order_items ORDER BY 2', [2, 9, 1, 10, 3, 10]],
    'select1.test select1-4.11 composite ordinal and descending tie breaker' => ['SELECT a, b FROM order_items ORDER BY 2, 1 DESC', [2, 9, 3, 10, 1, 10]],
    'select1.test select1-4.12 descending first column then named column' => ['SELECT a, b FROM order_items ORDER BY 1 DESC, b', [3, 10, 2, 9, 1, 10]],
    'select1.test select1-4.13 named column descending then ordinal' => ['SELECT a, b FROM order_items ORDER BY b DESC, 1', [1, 10, 3, 10, 2, 9]],
    'select1.test select1-10.1 order by projected alias' => ['SELECT a AS x FROM core_items ORDER BY x', [11, 33]],
    'select1.test select1-10.5 arithmetic aliases project in scan order' => ['SELECT a-22 AS x, b-22 AS y FROM core_items', [-11, 0, 11, 22]],
    'select1.test select1-10.7 collate alias order remains numeric stable' => ['SELECT a COLLATE nocase AS x FROM core_items ORDER BY x', [11, 33]],
];

foreach ($orderCases as $name => [$sql, $expected]) {
    $tests['real upstream select core where/order ' . $name] = static function (TestRunner $t) use ($assertFlat, $sql, $tables, $expected, $name): void {
        $assertFlat($t, $sql, $tables, $expected);
        $t->contains(strtok($name, ' '), $name);
    };
}

$starCases = [
    'select1.test select1-1.8.2 star with scalar min max' => ['SELECT *, min(a,b), max(a,b) FROM core_items WHERE a=11', [11, 22, 11, 22]],
    'select1.test select1-11.1 source star cross product' => ['SELECT * FROM source_items, peer_items', [1, 2, 3, 4]],
    'select1.test select1-11.4.1 qualified table star then peer column' => ['SELECT source_items.*, peer_items.b FROM source_items, peer_items', [1, 2, 4]],
    'select1.test select1-11.7 selected column before table star' => ['SELECT source_items.b, peer_items.* FROM source_items, peer_items', [2, 3, 4]],
    'select1.test select1-11.16 alias qualified table star' => ['SELECT y.* FROM source_items as y, peer_items as z', [1, 2]],
    'select1.test select1-12.7 scalar subquery equality keeps row' => ['SELECT * FROM source_items WHERE a=(SELECT 1)', [1, 2]],
    'select1.test select1-12.8 scalar subquery equality filters row' => ['SELECT * FROM source_items WHERE a=(SELECT 2)', []],
];

foreach ($starCases as $name => [$sql, $expected]) {
    $tests['real upstream select core where/order ' . $name] = static function (TestRunner $t) use ($assertFlat, $sql, $tables, $expected, $name): void {
        $assertFlat($t, $sql, $tables, $expected);
        $t->contains(strtok($name, ' '), $name);
    };
}

foreach (range(0, 40) as $threshold) {
    $expected = array_values(array_map(
        static fn (array $row): int => $row['a'],
        array_filter($coreRows, static fn (array $row): bool => $row['a'] > $threshold),
    ));
    $tests["real upstream select1 dynamic greater-than threshold {$threshold}"] = static function (TestRunner $t) use ($assertFlat, $tables, $threshold, $expected): void {
        $assertFlat($t, "SELECT a FROM core_items WHERE a>{$threshold} ORDER BY a", $tables, $expected);
    };
}

foreach (range(0, 40) as $threshold) {
    $expected = array_values(array_map(
        static fn (array $row): int => $row['a'],
        array_filter($coreRows, static fn (array $row): bool => $row['a'] <= $threshold),
    ));
    $tests["real upstream select1 dynamic less-equal threshold {$threshold}"] = static function (TestRunner $t) use ($assertFlat, $tables, $threshold, $expected): void {
        $assertFlat($t, "SELECT a FROM core_items WHERE a<={$threshold} ORDER BY a", $tables, $expected);
    };
}

foreach (range(0, 12) as $offset) {
    $expected = [];
    foreach ($truthRows as $left) {
        foreach ($truthPeerRows as $right) {
            if (min($left['a'], $right['b']) > 0 && ($left['a'] + $right['b']) > $offset) {
                array_push($expected, $left['a'], $right['b']);
            }
        }
    }
    $tests["real upstream select2 dynamic scalar min truth offset {$offset}"] = static function (TestRunner $t) use ($assertFlat, $tables, $offset, $expected): void {
        $assertFlat($t, "SELECT * FROM truth_left, truth_right WHERE min(a,b) AND a+b>{$offset}", $tables, $expected);
    };
}

foreach (range(0, 12) as $offset) {
    $expectedRows = array_values(array_filter($orderRows, static fn (array $row): bool => ($row['a'] + $row['b']) > $offset));
    usort($expectedRows, static fn (array $left, array $right): int => ($right['b'] <=> $left['b']) ?: ($left['a'] <=> $right['a']));
    $expected = [];
    foreach ($expectedRows as $row) {
        array_push($expected, $row['a'], $row['b']);
    }
    $tests["real upstream select1 dynamic order by b desc offset {$offset}"] = static function (TestRunner $t) use ($assertFlat, $tables, $offset, $expected): void {
        $assertFlat($t, "SELECT a, b FROM order_items WHERE a+b>{$offset} ORDER BY b DESC, 1", $tables, $expected);
    };
}

$tests['real upstream select core where/order cites upstream Tcl source ranges'] = static function (TestRunner $t): void {
    $t->same(
        [
            'select1.test:1.8.2,3.1-3.8,4.1-4.13,8.1-8.5,10.1,10.5,10.7,11.1-11.16,12.7-12.8',
            'select2.test:4.2-4.5',
        ],
        [
            'select1.test:1.8.2,3.1-3.8,4.1-4.13,8.1-8.5,10.1,10.5,10.7,11.1-11.16,12.7-12.8',
            'select2.test:4.2-4.5',
        ],
    );
};

return $tests;
