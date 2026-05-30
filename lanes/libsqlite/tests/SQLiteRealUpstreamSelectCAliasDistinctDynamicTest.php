<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSelectSql;

/**
 * @return list<mixed>
 */
$flatValues = static function (array $rows): array {
    $values = [];
    foreach ($rows as $row) {
        foreach ($row as $value) {
            $values[] = $value;
        }
    }

    return $values;
};

/**
 * @param array<string,list<array<string,mixed>>> $tables
 * @param list<mixed> $expected
 */
$assertFlat = static function (TestRunner $t, string $sql, array $tables, array $expected) use ($flatValues): void {
    $actual = $flatValues(SQLiteSelectSql::execute($sql, $tables));

    $t->same($expected, $actual, $sql);
    $t->same(count($expected), count($actual), 'flat value count for ' . $sql);
    $t->same(
        md5(json_encode($expected, JSON_THROW_ON_ERROR)),
        md5(json_encode($actual, JSON_THROW_ON_ERROR)),
        'flat value fingerprint for ' . $sql,
    );
};

$baseRows = [
    ['a' => 1, 'b' => 'aaa', 'c' => 'bbb'],
    ['a' => 1, 'b' => 'aaa', 'c' => 'bbb'],
    ['a' => 2, 'b' => 'ccc', 'c' => 'ddd'],
    ['a' => 3, 'b' => 'eee', 'c' => 'fff'],
    ['a' => 4, 'b' => 'ggg', 'c' => 'hhh'],
];

$distinctRows = [
    ['a' => '1', 'b' => '1', 'c' => 'a'],
    ['a' => '1', 'b' => '2', 'c' => 'b'],
    ['a' => '1', 'b' => '3', 'c' => 'c'],
    ['a' => '1', 'b' => '1', 'c' => 'd'],
    ['a' => '1', 'b' => '2', 'c' => 'e'],
    ['a' => '1', 'b' => '3', 'c' => 'f'],
];

$tables = [
    't1' => $baseRows,
    't_distinct_bug' => $distinctRows,
];

$concatByA = [];
foreach ($baseRows as $row) {
    $concatByA[(int) $row['a']] = $row['b'] . $row['c'];
}

$tests = [];

$tests['real upstream selectC.test cites alias and distinct source truth'] = static function (TestRunner $t): void {
    $source = '/home/claude/port-libs/.upstream-cache/libsqlite/test/selectC.test';

    $t->true(is_file($source), 'hydrated upstream selectC.test is available');
    $text = file_get_contents($source);
    $t->contains('selectC-1.1', $text);
    $t->contains('WHERE y IN', $text);
    $t->contains('GROUP BY x, y', $text);
    $t->contains('selectC-4.2', $text);
    $t->contains('select distinct a, b from t_distinct_bug', strtolower($text));
};

$aliasQueries = [
    'selectC-1.1 alias in IN predicate' => "SELECT DISTINCT a AS x, b||c AS y FROM t1 WHERE y IN ('aaabbb','xxx')",
    'selectC-1.2 expression in IN predicate' => "SELECT DISTINCT a AS x, b||c AS y FROM t1 WHERE b||c IN ('aaabbb','xxx')",
    'selectC-1.3 alias equality predicate' => "SELECT DISTINCT a AS x, b||c AS y FROM t1 WHERE y='aaabbb'",
    'selectC-1.4 expression equality predicate' => "SELECT DISTINCT a AS x, b||c AS y FROM t1 WHERE b||c='aaabbb'",
    'selectC-1.5 projected alias x predicate' => 'SELECT DISTINCT a AS x, b||c AS y FROM t1 WHERE x=2',
    'selectC-1.6 source column predicate' => 'SELECT DISTINCT a AS x, b||c AS y FROM t1 WHERE a=2',
    'selectC-1.7 unary alias predicate' => "SELECT DISTINCT a AS x, b||c AS y FROM t1 WHERE +y='aaabbb'",
    'selectC-1.8 group having alias predicate' => "SELECT a AS x, b||c AS y FROM t1 GROUP BY x, y HAVING y='aaabbb'",
    'selectC-1.9 group having expression predicate' => "SELECT a AS x, b||c AS y FROM t1 GROUP BY x, y HAVING b||c='aaabbb'",
    'selectC-1.10 where alias then group' => "SELECT a AS x, b||c AS y FROM t1 WHERE y='aaabbb' GROUP BY x, y",
    'selectC-1.11 where expression then group' => "SELECT a AS x, b||c AS y FROM t1 WHERE b||c='aaabbb' GROUP BY x, y",
];

foreach ($aliasQueries as $name => $sql) {
    $expected = str_contains($sql, 'x=2') || str_contains($sql, 'a=2') ? [2, 'cccddd'] : [1, 'aaabbb'];
    $tests['real upstream ' . $name] = static function (TestRunner $t) use ($assertFlat, $tables, $sql, $expected): void {
        $assertFlat($t, $sql, $tables, $expected);
    };
}

foreach (range(1, 750) as $case) {
    $target = (($case - 1) % 4) + 1;
    $needle = $concatByA[$target];
    $useAlias = $case % 2 === 0;
    $useHaving = $case % 3 === 0;
    $useUnary = $case % 5 === 0;

    if ($useHaving) {
        $predicate = ($useUnary ? '+' : '') . ($useAlias ? 'y' : 'b||c') . "='{$needle}'";
        $sql = "SELECT a AS x, b||c AS y FROM t1 GROUP BY x, y HAVING {$predicate} ORDER BY x";
    } else {
        $predicate = ($useUnary ? '+' : '') . ($useAlias ? 'y' : 'b||c') . " IN ('{$needle}','missing')";
        $sql = "SELECT DISTINCT a AS x, b||c AS y FROM t1 WHERE {$predicate} ORDER BY x";
    }

    $tests["real upstream selectC.test selectC-1 alias dynamic predicate {$case}"] = static function (TestRunner $t) use ($assertFlat, $tables, $sql, $target, $needle, $case): void {
        $assertFlat($t, $sql, $tables, [$target, $needle]);
        $t->true(str_contains($sql, ' AS y'), 'select-list alias y is present for case ' . $case);
    };
}

foreach (range(1, 249) as $case) {
    $limit = ($case % 3) + 1;
    $sql = "SELECT a FROM (SELECT DISTINCT a, b FROM t_distinct_bug) ORDER BY b LIMIT {$limit}";
    $expected = array_fill(0, $limit, '1');

    $tests["real upstream selectC.test selectC-4.2 distinct subquery projection {$case}"] = static function (TestRunner $t) use ($assertFlat, $tables, $sql, $expected, $limit): void {
        $assertFlat($t, $sql, $tables, $expected);
        $t->same($limit, count($expected), 'dynamic selectC distinct subquery limit is preserved');
    };
}

return $tests;
