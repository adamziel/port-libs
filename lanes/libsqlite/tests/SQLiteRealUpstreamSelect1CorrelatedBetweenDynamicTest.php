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
 * @param array<string,list<array<string,mixed>>> $tables
 * @param list<mixed> $expected
 */
$assertFlatSelect = static function (TestRunner $t, string $sql, array $tables, array $expected, string $label) use ($flattenRows): void {
    $actual = $flattenRows(SQLiteSelectSql::execute($sql, $tables));

    $t->same($expected, $actual, $label . ' flat result');
    $t->same(count($expected), count($actual), $label . ' flat result count');
    $t->same(
        md5(json_encode($expected, JSON_THROW_ON_ERROR)),
        md5(json_encode($actual, JSON_THROW_ON_ERROR)),
        $label . ' flat result fingerprint',
    );
};

$select1182NestedSql = static function (int $highExpression): string {
    return <<<SQL
SELECT x FROM (
  SELECT x COLLATE rtrim AS x FROM t2, t1
  WHERE x BETWEEN c AND ({$highExpression}) OR x AND x IN (c)
), t1 WHERE x BETWEEN c AND ({$highExpression}) OR x AND x IN (c)
SQL;
};

$tests = [];

$tests['real upstream select1.test select1-18.2 cites correlated nested source truth'] = static function (TestRunner $t): void {
    $source = '/home/claude/port-libs/.upstream-cache/libsqlite/test/select1.test';

    $t->true(is_file($source), 'hydrated upstream select1.test is available');
    $text = file_get_contents($source);
    $t->contains('select1-18.2', $text);
    $t->contains('x BETWEEN c AND (c+1)', $text);
    $t->contains('x COLLATE rtrim', $text);
    $t->contains('x AND x IN (c)', $text);
};

for ($case = 0; $case < 1000; $case++) {
    $base = 100 + ($case * 3);
    $tables = [
        't1' => [
            ['c' => $base],
        ],
        't2' => [
            ['x' => $base, 'y' => null],
        ],
    ];
    $sql = $select1182NestedSql($base + 1);

    $tests[sprintf('real upstream select1.test select1-18.2 nested correlated BETWEEN true dynamic %04d', $case)] =
        static function (TestRunner $t) use ($assertFlatSelect, $sql, $tables, $base, $case): void {
            $assertFlatSelect($t, $sql, $tables, [$base], 'select1-18.2 nested true branch case ' . $case);
            $t->contains('SELECT x COLLATE rtrim', $sql, 'select1-18.2 keeps collated nested projection');
            $t->contains('), t1 WHERE', $sql, 'select1-18.2 keeps repeated outer table source');
        };
}

for ($case = 0; $case < 250; $case++) {
    $base = 5000 + ($case * 5);
    $tables = [
        't1' => [
            ['c' => $base],
        ],
        't2' => [
            ['x' => $base + 7, 'y' => null],
        ],
    ];
    $sql = $select1182NestedSql($base + 1);

    $tests[sprintf('real upstream select1.test select1-18.2 nested correlated BETWEEN false dynamic %04d', $case)] =
        static function (TestRunner $t) use ($assertFlatSelect, $sql, $tables, $case): void {
            $assertFlatSelect($t, $sql, $tables, [], 'select1-18.2 nested false branch case ' . $case);
            $t->contains('x IN (c)', $sql, 'select1-18.2 preserves correlated IN predicate');
        };
}

return $tests;
