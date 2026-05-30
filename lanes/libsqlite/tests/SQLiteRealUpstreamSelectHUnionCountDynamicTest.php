<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSelectSql;

/**
 * @return list<mixed>
 */
$flattenRows = static function (array $rows): array {
    $flat = [];
    foreach ($rows as $row) {
        foreach ($row as $value) {
            $flat[] = $value;
        }
    }

    return $flat;
};

/**
 * @return array<string,list<array<string,int>>>
 */
$wideSelectHRows = static function (int $seed): array {
    $rows = [];
    for ($row = 0; $row < 3; $row++) {
        $values = [];
        for ($column = 0; $column <= 65; $column++) {
            $values['c' . $column] = $seed + ($row * 1000) + $column;
        }
        $rows[] = $values;
    }

    return ['t1' => $rows];
};

$assertFlatSelect = static function (TestRunner $t, string $sql, array $tables, array $expected, string $label) use ($flattenRows): void {
    $actual = $flattenRows(SQLiteSelectSql::execute($sql, $tables));

    $t->same($expected, $actual, $label . ' flat result');
    $t->same(count($expected), count($actual), $label . ' result count');
    $t->same(
        md5(json_encode($expected, JSON_THROW_ON_ERROR)),
        md5(json_encode($actual, JSON_THROW_ON_ERROR)),
        $label . ' result fingerprint',
    );
    $t->contains('UNION ALL', $sql, $label . ' keeps upstream four-arm compound shape');
};

$tests = [];

$tests['real upstream selectH.test union count dynamic cites source scenarios'] = static function (TestRunner $t): void {
    $source = '/home/claude/port-libs/.upstream-cache/libsqlite/test/selectH.test';

    $t->true(is_file($source), 'hydrated upstream selectH.test is available');
    $text = file_get_contents($source);
    $t->contains('do_execsql_test 3.1', $text);
    $t->contains('SELECT count(*) FROM v1 WHERE c60=60', $text);
    $t->contains('do_execsql_test 3.3', $text);
    $t->contains('SELECT count(a) FROM v1 WHERE c60=60', $text);
};

for ($case = 0; $case < 500; $case++) {
    $seed = 10000 + ($case * 17);
    $tables = $wideSelectHRows($seed);
    $filterColumn = 'c' . (60 - ($case % 5));
    $filterValue = $tables['t1'][1][$filterColumn];
    $aColumns = [
        'c' . (16 + ($case % 10)),
        'c' . (26 + ($case % 10)),
        'c' . (36 + ($case % 10)),
        'c' . (46 + ($case % 10)),
    ];
    $expectedCount = 4;

    $unionArms = [];
    foreach ($aColumns as $column) {
        $unionArms[] = sprintf('SELECT %s AS a, * FROM t1', $column);
    }
    $compound = implode("\n  UNION ALL\n  ", $unionArms);

    $tests[sprintf('real upstream selectH.test selectH-3.1 count star four-arm union filter dynamic %03d', $case)] =
        static function (TestRunner $t) use ($assertFlatSelect, $compound, $filterColumn, $filterValue, $tables, $expectedCount, $case): void {
            $sql = sprintf(
                "SELECT count(*) FROM (\n  %s\n) WHERE %s=%d",
                $compound,
                $filterColumn,
                $filterValue,
            );

            $assertFlatSelect($t, $sql, $tables, [$expectedCount], 'selectH-3.1 dynamic ' . $case);
        };

    $tests[sprintf('real upstream selectH.test selectH-3.3 count a four-arm union filter dynamic %03d', $case)] =
        static function (TestRunner $t) use ($assertFlatSelect, $compound, $filterColumn, $filterValue, $tables, $expectedCount, $case): void {
            $sql = sprintf(
                "SELECT count(a) FROM (\n  %s\n) WHERE %s=%d",
                $compound,
                $filterColumn,
                $filterValue,
            );

            $assertFlatSelect($t, $sql, $tables, [$expectedCount], 'selectH-3.3 dynamic ' . $case);
        };
}

return $tests;
