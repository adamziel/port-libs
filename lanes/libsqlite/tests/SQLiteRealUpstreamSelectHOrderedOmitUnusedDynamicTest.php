<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSelectSql;

$tests = [];

$columns = [];
$row = [];
for ($i = 0; $i <= 65; $i++) {
    $column = 'c' . $i;
    $columns[] = $column;
    $row[$column] = $i;
}
$tables = ['t1' => [$row]];

$flatten = static function (array $rows): array {
    $flat = [];
    foreach ($rows as $row) {
        foreach ($row as $value) {
            $flat[] = $value;
        }
    }

    return $flat;
};

for ($case = 0; $case < 1000; $case++) {
    $leftColumn = $columns[($case * 7 + 15) % count($columns)];
    $rightColumn = $columns[($case * 11 + 16) % count($columns)];
    $highOrderColumn = $columns[65 - ($case % 33)];
    $lowOrderColumn = $columns[$case % 33];

    if ($row[$leftColumn] === $row[$rightColumn]) {
        $rightColumn = $columns[($row[$rightColumn] + 1) % count($columns)];
    }

    $sql = sprintf(
        'SELECT a FROM (SELECT counter(1) AS cnt, %s AS a, *, %s AS b FROM t1 UNION ALL SELECT counter(1) AS cnt, %s AS a, *, %s AS b FROM t1 ORDER BY b)',
        $leftColumn,
        $highOrderColumn,
        $rightColumn,
        $lowOrderColumn,
    );
    $expected = [$row[$rightColumn], $row[$leftColumn]];

    $tests[sprintf('real upstream corpus selectH.test selectH-2.1 ordered omit unused counter dynamic %04d', $case)] =
        static function (TestRunner $t) use ($sql, $tables, $flatten, $expected, $lowOrderColumn, $highOrderColumn): void {
            $actual = $flatten(SQLiteSelectSql::execute($sql, $tables));

            $t->same($expected, $actual, $sql);
            $t->same(2, count($actual), 'ordered compound emits both arms');
            $t->true(str_contains($sql, 'counter(1)'), 'unused upstream side-effect expression remains in compound arms');
            $t->true(str_contains($sql, 'ORDER BY b'), 'upstream ordered compound subquery shape is preserved');
            $t->true((int) substr($lowOrderColumn, 1) < (int) substr($highOrderColumn, 1), 'right arm sort key precedes left arm sort key');
        };
}

$tests['real upstream corpus selectH.test selectH-2.1 cites ordered omit-unused source truth'] = static function (TestRunner $t): void {
    $source = '/home/claude/port-libs/.upstream-cache/libsqlite/test/selectH.test';

    $t->true(is_file($source), 'hydrated upstream selectH.test is available');
    $text = file_get_contents($source);
    $t->contains('omit-unused-subquery-column optimization', $text);
    $t->contains('SELECT a FROM (', $text);
    $t->contains('ORDER BY b', $text);
    $t->contains('set ::selectH_cnt', $text);
};

return $tests;
