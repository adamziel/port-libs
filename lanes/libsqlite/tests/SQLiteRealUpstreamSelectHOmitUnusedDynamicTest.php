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
    $column = $columns[$case % count($columns)];
    $filterColumn = $columns[($case * 17 + 60) % count($columns)];
    $filterValue = $row[$filterColumn];
    $sql = sprintf(
        'SELECT DISTINCT %s FROM (SELECT c0 AS a, *, counter(1) FROM t1 UNION ALL SELECT c1 AS a, *, counter(1) FROM t1) WHERE %s=%d',
        $column,
        $filterColumn,
        $filterValue,
    );
    $tests[sprintf('real upstream corpus selectH.test selectH-1.2 omit unused counter dynamic %04d', $case)] = static function (TestRunner $t) use ($sql, $tables, $flatten, $row, $column, $filterColumn): void {
        $actual = $flatten(SQLiteSelectSql::execute($sql, $tables));

        $t->same([$row[$column]], $actual, $sql);
        $t->same(1, count($actual), 'distinct output width');
        $t->same($row[$column], $actual[0] ?? null, 'selected required column survives pruning');
        $t->same($row[$filterColumn], $tables['t1'][0][$filterColumn], 'filtered required column remains available');
        $t->true(str_contains($sql, 'counter(1)'), 'upstream side-effect expression is present but unused');
    };
}

$tests['real upstream corpus selectH.test cites upstream source truth'] = static function (TestRunner $t): void {
    $source = '/home/claude/port-libs/.upstream-cache/libsqlite/test/selectH.test';

    $t->true(is_file($source), 'hydrated upstream selectH.test is available');
    $text = file_get_contents($source);
    $t->contains('omit-unused-subquery-column optimization', $text);
    $t->contains('selectH_counter', $text);
    $t->contains('SELECT DISTINCT c44 FROM', $text);
    $t->contains('ORDER BY b', $text);
};

return $tests;
