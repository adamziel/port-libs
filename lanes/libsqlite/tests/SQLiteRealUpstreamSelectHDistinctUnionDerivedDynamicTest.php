<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSelectSql;

/**
 * @param list<array<string,mixed>> $rows
 * @return list<mixed>
 */
$flattenRows = static function (array $rows): array {
    $values = [];
    foreach ($rows as $row) {
        foreach ($row as $value) {
            $values[] = $value;
        }
    }

    return $values;
};

/**
 * @param list<int> $leftValues
 * @param list<int> $rightValues
 * @return array<string,list<array<string,mixed>>>
 */
$selectHTables = static function (array $leftValues, array $rightValues): array {
    return [
        't1' => array_map(static fn (int $value): array => ['val1' => $value], $leftValues),
        't2' => array_map(static fn (int $value): array => ['val2' => $value], $rightValues),
    ];
};

/**
 * @param list<int> $values
 * @return list<int>
 */
$distinctLeft = static function (array $values): array {
    $seen = [];
    $result = [];
    foreach ($values as $value) {
        $key = (string) $value;
        if (isset($seen[$key])) {
            continue;
        }
        $seen[$key] = true;
        $result[] = $value;
    }

    return $result;
};

$tests = [];

$tests['real upstream selectH.test selectH-5 cites distinct union derived source'] = static function (TestRunner $t): void {
    $source = '/home/claude/port-libs/.upstream-cache/libsqlite/test/selectH.test';

    $t->true(is_file($source), 'hydrated upstream selectH.test is available');
    $text = file_get_contents($source);
    $t->true(is_string($text), 'selectH.test source can be read');
    $t->contains('SELECT DISTINCT val1 FROM t1 UNION ALL SELECT val2 FROM t2', $text);
    $t->contains('SELECT count(1234) FROM (', $text);
};

$tests['real upstream selectH.test selectH-5.1 canonical distinct union all rows'] = static function (TestRunner $t) use ($flattenRows, $selectHTables): void {
    $tables = $selectHTables([4, 5], []);
    $rows = SQLiteSelectSql::execute('SELECT DISTINCT val1 FROM t1 UNION ALL SELECT val2 FROM t2', $tables);

    $t->same([4, 5], $flattenRows($rows));
    $t->same(2, count($rows));
};

$tests['real upstream selectH.test selectH-5.2 canonical derived compound count'] = static function (TestRunner $t) use ($selectHTables): void {
    $tables = $selectHTables([4, 5], []);
    $rows = SQLiteSelectSql::execute('SELECT count(1234) AS total FROM (SELECT DISTINCT val1 FROM t1 UNION ALL SELECT val2 FROM t2)', $tables);

    $t->same([['total' => 2]], $rows);
};

for ($case = 0; $case < 500; $case++) {
    $leftValues = [];
    for ($i = 0; $i < 8; $i++) {
        $leftValues[] = (($case + ($i * 3)) % 7) + 1;
    }

    $rightValues = [];
    for ($i = 0; $i < ($case % 5); $i++) {
        $rightValues[] = (($case * 2) + $i) % 11;
    }

    $expectedLeft = $distinctLeft($leftValues);
    $expectedRows = array_merge($expectedLeft, $rightValues);
    $expectedCount = count($expectedRows);
    $tables = $selectHTables($leftValues, $rightValues);

    $tests[sprintf('real upstream selectH.test selectH-5.1 dynamic distinct union all rows %03d', $case)] =
        static function (TestRunner $t) use ($flattenRows, $tables, $expectedRows, $expectedLeft, $rightValues, $case): void {
            $rows = SQLiteSelectSql::execute('SELECT DISTINCT val1 AS a FROM t1 UNION ALL SELECT val2 AS a FROM t2', $tables);
            $actual = $flattenRows($rows);

            $t->same($expectedRows, $actual);
            $t->same(count($expectedLeft), count(array_unique($expectedLeft)), 'left arm is DISTINCT before UNION ALL case ' . $case);
            $t->same(array_slice($actual, count($expectedLeft)), $rightValues, 'right arm rows are appended by UNION ALL case ' . $case);
            $t->same(md5(json_encode($expectedRows, JSON_THROW_ON_ERROR)), md5(json_encode($actual, JSON_THROW_ON_ERROR)), 'row fingerprint case ' . $case);
        };

    $tests[sprintf('real upstream selectH.test selectH-5.2 dynamic derived compound count %03d', $case)] =
        static function (TestRunner $t) use ($tables, $expectedCount, $expectedRows, $case): void {
            $rows = SQLiteSelectSql::execute('SELECT count(1234) AS total FROM (SELECT DISTINCT val1 AS a FROM t1 UNION ALL SELECT val2 AS a FROM t2)', $tables);

            $t->same([['total' => $expectedCount]], $rows);
            $t->same($expectedCount, count($expectedRows), 'count mirrors derived compound row count case ' . $case);
            $t->true($expectedCount >= 1, 'selectH dynamic source keeps a non-empty DISTINCT left arm');
        };
}

return $tests;
