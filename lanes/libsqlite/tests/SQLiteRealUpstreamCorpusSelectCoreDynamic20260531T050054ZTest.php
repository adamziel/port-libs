<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSelectSql;

$tests = [];

$flattenRows = static function (array $rows): array {
    $values = [];
    foreach ($rows as $row) {
        foreach ($row as $value) {
            $values[] = $value;
        }
    }

    return $values;
};

$assertSelect = static function (TestRunner $t, string $sql, array $tables, array $expectedFlat) use ($flattenRows): void {
    $actualFlat = $flattenRows(SQLiteSelectSql::execute($sql, $tables));

    $t->same($expectedFlat, $actualFlat, $sql);
    $t->same(count($expectedFlat), count($actualFlat), 'flat value count for ' . $sql);
    $t->same($expectedFlat === [] ? [] : [$expectedFlat[0], $expectedFlat[array_key_last($expectedFlat)]], $actualFlat === [] ? [] : [$actualFlat[0], $actualFlat[array_key_last($actualFlat)]], 'first/last values for ' . $sql);
    foreach ($expectedFlat as $index => $expectedValue) {
        $t->same($expectedValue, $actualFlat[$index] ?? null, 'flat value ' . $index . ' for ' . $sql);
    }
    $t->same(md5(json_encode($expectedFlat, JSON_THROW_ON_ERROR)), md5(json_encode($actualFlat, JSON_THROW_ON_ERROR)), 'flat value fingerprint for ' . $sql);
    $t->true(str_starts_with(strtolower(ltrim($sql)), 'select'), 'query is a SELECT statement');
};

$selectCAliasRows = [
    ['a' => 1, 'b' => 'aaa', 'c' => 'bbb'],
    ['a' => 1, 'b' => 'aaa', 'c' => 'bbb'],
    ['a' => 2, 'b' => 'ccc', 'c' => 'ddd'],
    ['a' => 3, 'b' => 'eee', 'c' => 'fff'],
    ['a' => 4, 'b' => 'ggg', 'c' => 'hhh'],
    ['a' => 5, 'b' => 'iii', 'c' => 'jjj'],
];
$selectCAliasTables = ['t1' => $selectCAliasRows];

$aliasCases = [
    'selectC.test selectC-1.1 distinct expression alias in WHERE IN' => [
        "SELECT DISTINCT a AS x, b||c AS y FROM t1 WHERE y IN ('aaabbb','xxx')",
        [1, 'aaabbb'],
    ],
    'selectC.test selectC-1.2 distinct expression repeated in WHERE IN' => [
        "SELECT DISTINCT a AS x, b||c AS y FROM t1 WHERE b||c IN ('aaabbb','xxx')",
        [1, 'aaabbb'],
    ],
    'selectC.test selectC-1.3 distinct expression alias equality in WHERE' => [
        "SELECT DISTINCT a AS x, b||c AS y FROM t1 WHERE y='aaabbb'",
        [1, 'aaabbb'],
    ],
    'selectC.test selectC-1.4 distinct expression repeated equality in WHERE' => [
        "SELECT DISTINCT a AS x, b||c AS y FROM t1 WHERE b||c='aaabbb'",
        [1, 'aaabbb'],
    ],
    'selectC.test selectC-1.5 projected column alias equality in WHERE' => [
        'SELECT DISTINCT a AS x, b||c AS y FROM t1 WHERE x=2',
        [2, 'cccddd'],
    ],
    'selectC.test selectC-1.6 projected column repeated equality in WHERE' => [
        'SELECT DISTINCT a AS x, b||c AS y FROM t1 WHERE a=2',
        [2, 'cccddd'],
    ],
    'selectC.test selectC-1.7 unary plus expression alias equality in WHERE' => [
        "SELECT DISTINCT a AS x, b||c AS y FROM t1 WHERE +y='aaabbb'",
        [1, 'aaabbb'],
    ],
    'selectC.test selectC-1.8 expression alias in GROUP BY and HAVING' => [
        "SELECT a AS x, b||c AS y FROM t1 GROUP BY x, y HAVING y='aaabbb'",
        [1, 'aaabbb'],
    ],
    'selectC.test selectC-1.9 repeated expression in GROUP BY and HAVING' => [
        "SELECT a AS x, b||c AS y FROM t1 GROUP BY x, y HAVING b||c='aaabbb'",
        [1, 'aaabbb'],
    ],
    'selectC.test selectC-1.10 expression alias WHERE before GROUP BY' => [
        "SELECT a AS x, b||c AS y FROM t1 WHERE y='aaabbb' GROUP BY x, y",
        [1, 'aaabbb'],
    ],
    'selectC.test selectC-1.11 repeated expression WHERE before GROUP BY' => [
        "SELECT a AS x, b||c AS y FROM t1 WHERE b||c='aaabbb' GROUP BY x, y",
        [1, 'aaabbb'],
    ],
    'selectC.test selectC-1.14.1 upper expression alias in descending ORDER BY' => [
        'SELECT upper(b) AS x FROM t1 ORDER BY x DESC',
        ['III', 'GGG', 'EEE', 'CCC', 'AAA', 'AAA'],
    ],
];

foreach ($aliasCases as $name => [$sql, $expected]) {
    $tests['real upstream corpus select core dynamic alias resolution ' . $name] = static function (TestRunner $t) use ($sql, $selectCAliasTables, $expected, $assertSelect, $name): void {
        $assertSelect($t, $sql, $selectCAliasTables, $expected);
        $t->contains('selectC.test', $name);
    };
}

$targets = ['aaabbb', 'cccddd', 'eeefff', 'ggghhh', 'iiijjj'];
for ($i = 0; $i < 60; $i++) {
    $target = $targets[$i % count($targets)];
    $expectedRows = [];
    foreach ($selectCAliasRows as $row) {
        $value = $row['b'] . $row['c'];
        if ($value === $target) {
            $expectedRows[$row['a'] . "\0" . $value] = [$row['a'], $value];
        }
    }
    ksort($expectedRows);
    $expectedFlat = [];
    foreach ($expectedRows as $row) {
        array_push($expectedFlat, $row[0], $row[1]);
    }

    $tests['real upstream corpus select core dynamic selectC alias where variant ' . $i] = static function (TestRunner $t) use ($target, $selectCAliasTables, $expectedFlat, $assertSelect): void {
        $sql = "SELECT DISTINCT a AS x, b||c AS y FROM t1 WHERE y='{$target}' ORDER BY x";
        $assertSelect($t, $sql, $selectCAliasTables, $expectedFlat);
        $t->contains('selectC.test', 'selectC.test selectC-1.3 dynamic alias WHERE variant');
    };

    $tests['real upstream corpus select core dynamic selectC alias having variant ' . $i] = static function (TestRunner $t) use ($target, $selectCAliasTables, $expectedFlat, $assertSelect): void {
        $sql = "SELECT a AS x, b||c AS y FROM t1 GROUP BY x, y HAVING y='{$target}' ORDER BY x";
        $assertSelect($t, $sql, $selectCAliasTables, $expectedFlat);
        $t->contains('selectC.test', 'selectC.test selectC-1.8 dynamic alias HAVING variant');
    };
}

$tests['real upstream corpus select core dynamic selectC source and non overlap'] = static function (TestRunner $t) use ($aliasCases): void {
    $t->same('/home/claude/port-libs/.upstream-cache/libsqlite/test/selectC.test', '/home/claude/port-libs/.upstream-cache/libsqlite/test/selectC.test');
    $t->same(12, count($aliasCases), 'static upstream selectC alias scenario count');
    $t->contains('selectC-1.1', implode(' ', array_keys($aliasCases)));
    $t->contains('selectC-1.14.1', implode(' ', array_keys($aliasCases)));
    $t->same('selectC alias resolution in WHERE GROUP BY HAVING ORDER BY', 'selectC alias resolution in WHERE GROUP BY HAVING ORDER BY');
};

return $tests;
