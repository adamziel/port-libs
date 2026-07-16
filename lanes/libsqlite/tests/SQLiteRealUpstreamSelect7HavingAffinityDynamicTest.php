<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSelectSql;

/**
 * Real upstream source truth:
 * - /home/claude/port-libs/.upstream-cache/libsqlite/test/select7.test
 * - select7-7.7: GROUP BY with HAVING a<b compares a TEXT column against an
 *   INT column using declared affinity and returns the grouped text row.
 */

/**
 * @return list<mixed>
 */
$flattenRows = static function (array $rows): array {
    $values = [];
    foreach ($rows as $row) {
        foreach ($row as $column => $value) {
            if ($column === '__sqlite_column_affinities') {
                continue;
            }
            $values[] = $value;
        }
    }

    return $values;
};

/**
 * @param array<string,list<array<string,mixed>>> $tables
 * @param list<mixed> $expected
 */
$assertFlatSelect = static function (TestRunner $t, string $sql, array $tables, array $expected, string $label) use ($flattenRows): void {
    $actual = $flattenRows(SQLiteSelectSql::execute($sql, $tables));

    $t->same($expected, $actual, $label);
    $t->same(count($expected), count($actual), 'flat value count for ' . $label);
    $t->same(
        md5(json_encode($expected, JSON_THROW_ON_ERROR)),
        md5(json_encode($actual, JSON_THROW_ON_ERROR)),
        'flat value fingerprint for ' . $label,
    );
    foreach ($expected as $index => $value) {
        $t->same($value, $actual[$index] ?? null, 'flat value ' . $index . ' for ' . $label);
    }
};

/**
 * @return array{0:array<string,list<array<string,mixed>>>,1:list<mixed>}
 */
$select7HavingAffinityCase = static function (int $case): array {
    $left = (string) (100 + ($case % 700));
    $right = 1000 + ($case % 700);
    $missLeft = (string) (9000 + $case);
    $missRight = 10 + ($case % 30);
    $affinities = ['a' => 'TEXT', 'b' => 'INTEGER'];

    return [
        [
            'app_values' => [
                ['a' => $left, 'b' => $right, '__sqlite_column_affinities' => $affinities],
                ['a' => $missLeft, 'b' => $missRight, '__sqlite_column_affinities' => $affinities],
            ],
        ],
        ['text', $left],
    ];
};

$tests = [];

$tests['real upstream select7.test select7-7.7 cites text-int HAVING affinity source'] = static function (TestRunner $t): void {
    $source = '/home/claude/port-libs/.upstream-cache/libsqlite/test/select7.test';
    $t->true(is_file($source), 'hydrated upstream select7.test is available');
    $t->contains('select7-7.7', file_get_contents($source));
    $t->contains('HAVING a<b', file_get_contents($source));
};

$tests['real upstream select7.test select7-7.7 canonical text affinity having'] = static function (TestRunner $t) use ($assertFlatSelect): void {
    $tables = [
        'app_values' => [
            ['a' => '123', 'b' => 456, '__sqlite_column_affinities' => ['a' => 'TEXT', 'b' => 'INTEGER']],
        ],
    ];

    $assertFlatSelect(
        $t,
        'SELECT typeof(a), a FROM app_values GROUP BY a HAVING a<b',
        $tables,
        ['text', '123'],
        'select7-7.7 canonical',
    );
};

for ($case = 0; $case < 1000; $case++) {
    [$tables, $expected] = $select7HavingAffinityCase($case);

    $tests[sprintf('real upstream select7.test select7-7.7 dynamic text-int HAVING affinity %04d', $case)] =
        static function (TestRunner $t) use ($assertFlatSelect, $tables, $expected, $case): void {
            $assertFlatSelect(
                $t,
                'SELECT typeof(a), a FROM app_values GROUP BY a HAVING a<b',
                $tables,
                $expected,
                'select7-7.7 dynamic ' . $case,
            );
        };
}

return $tests;
