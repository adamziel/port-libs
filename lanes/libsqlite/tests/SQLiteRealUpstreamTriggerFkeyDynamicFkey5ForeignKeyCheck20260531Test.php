<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteUpstreamTriggerFkeyDynamicPlan;

$valueAt = static function (array $array, string $path): mixed {
    $cursor = $array;
    foreach (explode('.', $path) as $part) {
        if (is_array($cursor) && array_key_exists($part, $cursor)) {
            $cursor = $cursor[$part];
            continue;
        }
        if (is_array($cursor) && ctype_digit($part) && array_key_exists((int) $part, $cursor)) {
            $cursor = $cursor[(int) $part];
            continue;
        }

        throw new RuntimeException("Missing assertion path {$path}");
    }

    return $cursor;
};

$sourcePath = '/home/claude/port-libs/.upstream-cache/libsqlite/test/fkey5.test';
$plan = SQLiteUpstreamTriggerFkeyDynamicPlan::fkey5ForeignKeyCheckMatrix();

$tests = [
    'real upstream fkey5 foreign key check cites hydrated source' => static function (TestRunner $t) use ($sourcePath): void {
        $source = file_get_contents($sourcePath);
        $t->true(is_string($source));
        $t->contains('PRAGMA foreign_key_check;', $source);
        $t->contains('do_test fkey5-7.1', $source);
        $t->contains('do_execsql_test 10.3', $source);
        $t->contains('do_execsql_test 13.12', $source);
    },
    'real upstream fkey5 foreign key check plan metadata' => static function (TestRunner $t) use ($plan): void {
        $t->same('fkey5.test', $plan['source']);
        $t->same(24, count($plan['scenarios']));
        $t->same(720, count($plan['cases']));
        $t->same('fkey5-1.2', $plan['scenarios'][0]);
        $t->same('fkey5-13.12', $plan['scenarios'][23]);
    },
    'real upstream fkey5 foreign key check dependency markers' => static function (TestRunner $t) use ($plan): void {
        $t->same('sqlite-upstream-fkey5-foreign-key-check-result-shape', $plan['dependencies'][0]);
        $t->same('sqlite-upstream-fkey5-foreign-key-check-collation-and-affinity', $plan['dependencies'][1]);
        $t->same('sqlite-upstream-fkey5-foreign-key-check-without-rowid-null-rowid', $plan['dependencies'][2]);
        $t->same('sqlite-upstream-fkey5-foreign-key-check-schema-table-argument', $plan['dependencies'][3]);
        $t->same('sqlite-upstream-fkey5-foreign-key-check-virtual-table-arguments', $plan['dependencies'][4]);
    },
];

$expected = [
    'fkey5-1.2' => ['scope' => 'database', 'schema' => 'main', 'child' => 'c1', 'parent' => 'p1', 'count' => 2, 'first' => ['table' => 'c1', 'rowid' => 87, 'parent' => 'p1', 'fkid' => 0], 'last' => ['table' => 'c1', 'rowid' => 90, 'parent' => 'p1', 'fkid' => 0], 'columns' => 4],
    'fkey5-5.2' => ['scope' => 'database', 'schema' => 'main', 'child' => 'c7', 'parent' => 'p3', 'count' => 4, 'first' => ['table' => 'c7', 'rowid' => 1, 'parent' => 'p3', 'fkid' => 0], 'last' => ['table' => 'c7', 'rowid' => 6, 'parent' => 'p3', 'fkid' => 0], 'columns' => 4],
    'fkey5-7.1' => ['scope' => 'database', 'schema' => 'main', 'child' => 'c13/c14', 'parent' => 'p3/p4', 'count' => 9, 'first' => ['table' => 'c13', 'rowid' => 1, 'parent' => 'p3', 'fkid' => 0], 'last' => ['table' => 'c14', 'rowid' => 6, 'parent' => 'p4', 'fkid' => 0], 'columns' => 4],
    'fkey5-8.6' => ['scope' => 'table', 'schema' => 'main', 'child' => 'c22', 'parent' => 'p6', 'count' => 1, 'first' => ['table' => 'c22', 'rowid' => 1, 'parent' => 'p6', 'fkid' => 0], 'last' => ['table' => 'c22', 'rowid' => 1, 'parent' => 'p6', 'fkid' => 0], 'columns' => 4],
    'fkey5-9.4' => ['scope' => 'table', 'schema' => 'main', 'child' => 'k2', 'parent' => 's1', 'count' => 1, 'first' => ['table' => 'k2', 'rowid' => 3, 'parent' => 's1', 'fkid' => 0], 'last' => ['table' => 'k2', 'rowid' => 3, 'parent' => 's1', 'fkid' => 0], 'columns' => 4],
    'fkey5-10.3' => ['scope' => 'database', 'schema' => 'main', 'child' => 'c30', 'parent' => 'p30', 'count' => 1, 'first' => ['table' => 'c30', 'rowid' => null, 'parent' => 'p30', 'fkid' => 0], 'last' => ['table' => 'c30', 'rowid' => null, 'parent' => 'p30', 'fkid' => 0], 'columns' => 4],
    'fkey5-11.1' => ['scope' => 'database', 'schema' => 'main', 'child' => 'c11', 'parent' => 'tt', 'count' => 0, 'first' => null, 'last' => null, 'columns' => 0, 'ok' => false, 'error' => 'foreign key mismatch - "c11" referencing "tt"'],
    'fkey5-12.0' => ['scope' => 'schema-table', 'schema' => 'aux', 'child' => 't1', 'parent' => 't2', 'count' => 3, 'first' => ['table' => 't1', 'rowid' => 5, 'parent' => 't2', 'fkid' => 0], 'last' => ['table' => 't1', 'rowid' => 9, 'parent' => 't2', 'fkid' => 0], 'columns' => 4],
    'fkey5-13.12' => ['scope' => 'virtual-table', 'schema' => 'main', 'child' => 't1/t3', 'parent' => 't2/t1', 'count' => 2, 'first' => ['table' => 't1', 'rowid' => 9, 'parent' => 't2', 'fkid' => 0], 'last' => ['table' => 't3', 'rowid' => 2, 'parent' => 't1', 'fkid' => 0], 'columns' => 5],
];

foreach ($plan['cases'] as $case) {
    $scenario = (string) $case['case'];
    $expect = $expected[$scenario] ?? null;
    foreach ([
        'source' => 'fkey5.test',
        'case' => $scenario,
        'variant' => $case['variant'],
        'all_fkids_zero' => true,
        'ok' => $expect['ok'] ?? true,
        'error' => $expect['error'] ?? null,
    ] as $path => $expectedValue) {
        $tests[sprintf('real upstream fkey5 foreign key check %04d %s %s', $case['variant'], $scenario, $path)] = static function (TestRunner $t) use ($case, $valueAt, $path, $expectedValue): void {
            $t->same($expectedValue, $valueAt($case, (string) $path));
        };
    }

    if ($expect !== null) {
        foreach ([
            'scope' => $expect['scope'],
            'schema' => $expect['schema'],
            'child' => $expect['child'],
            'parent' => $expect['parent'],
            'violation_count' => $expect['count'],
            'first_violation' => $expect['first'],
            'last_violation' => $expect['last'],
            'result_columns' => $expect['columns'],
        ] as $path => $expectedValue) {
            $tests[sprintf('real upstream fkey5 foreign key check sampled %04d %s %s', $case['variant'], $scenario, $path)] = static function (TestRunner $t) use ($case, $valueAt, $path, $expectedValue): void {
                $t->same($expectedValue, $valueAt($case, (string) $path));
            };
        }
    }

    if ($scenario === 'fkey5-10.3') {
        $tests['real upstream fkey5 foreign key check without rowid null rowid ' . $case['variant']] = static function (TestRunner $t) use ($case): void {
            $t->same(true, $case['without_rowid_child_reports_null_rowid']);
            $t->same([null], $case['rowids']);
        };
    }

    if ($scenario === 'fkey5-13.12') {
        $tests['real upstream fkey5 foreign key check virtual table ordered rows ' . $case['variant']] = static function (TestRunner $t) use ($case): void {
            $t->same(true, $case['orders_by_table']);
            $t->same(['t1', 't3'], $case['tables']);
        };
    }
}

return $tests;
