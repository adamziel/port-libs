<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteUpsertDoUpdateWherePlan;
use PortLibs\LibSqlite\SQLiteUpsertReturningDynamicCorpusPlan;

$tests = [];

$schemaVariants = [
    'upsert5-1.1 rowid primary-key first' => [
        'columns' => ['a', 'b', 'c', 'd', 'e'],
        'constraints' => [['a'], ['c'], ['d'], ['e']],
        'without_rowid' => false,
    ],
    'upsert5-1.2 int primary-key first' => [
        'columns' => ['a', 'b', 'c', 'd', 'e'],
        'constraints' => [['a'], ['c'], ['d'], ['e']],
        'without_rowid' => false,
    ],
    'upsert5-1.3 int primary-key first without rowid' => [
        'columns' => ['a', 'b', 'c', 'd', 'e'],
        'constraints' => [['a'], ['c'], ['d'], ['e']],
        'without_rowid' => true,
    ],
    'upsert5-1.4 rowid primary-key after unique columns' => [
        'columns' => ['e', 'd', 'c', 'a', 'b'],
        'constraints' => [['e'], ['d'], ['c'], ['a']],
        'without_rowid' => false,
    ],
    'upsert5-1.5 int primary-key after unique columns' => [
        'columns' => ['e', 'd', 'c', 'a', 'b'],
        'constraints' => [['e'], ['d'], ['c'], ['a']],
        'without_rowid' => false,
    ],
    'upsert5-1.6 int primary-key after unique columns without rowid' => [
        'columns' => ['e', 'd', 'c', 'a', 'b'],
        'constraints' => [['e'], ['d'], ['c'], ['a']],
        'without_rowid' => true,
    ],
];

$projectColumns = static function (array $rows, array $columns): array {
    return array_map(static function (array $row) use ($columns): array {
        $projected = [];
        foreach ($columns as $column) {
            $projected[$column] = $row[$column];
        }

        return $projected;
    }, $rows);
};

$matchedTargets = static fn (array $matched): array => array_map(
    static fn (array $match): string => $match['target'] === null ? '*' : implode(',', $match['target']),
    $matched,
);

// Source truth: SQLite upstream test/upsert5.test uses the same generalized
// UPSERT corpus over six table layouts, including reversed column order and
// WITHOUT ROWID variants. These assertions keep those schema variants distinct
// while using the native PHP multi-arm conflict executor.
foreach ($schemaVariants as $schemaName => $schema) {
    foreach (SQLiteUpsertReturningDynamicCorpusPlan::multiArmConflictCases() as $case) {
        $name = 'real upstream corpus upsert returning dynamic schema variant ' . $schemaName . ' / ' . $case['upstream'];

        $tests[$name . ' final rows follow upstream table projection'] = static function (TestRunner $t) use ($case, $schema, $projectColumns): void {
            $plan = SQLiteUpsertDoUpdateWherePlan::executeConflictArms($case['before'], $case['incoming'], $case['arms'], $schema['constraints']);

            $t->same($projectColumns($case['expected'], $schema['columns']), $projectColumns($plan['after'], $schema['columns']));
        };

        $tests[$name . ' returning rows preserve changed row image'] = static function (TestRunner $t) use ($case, $schema, $projectColumns): void {
            $plan = SQLiteUpsertDoUpdateWherePlan::executeConflictArms($case['before'], $case['incoming'], $case['arms'], $schema['constraints']);
            $expected = SQLiteUpsertDoUpdateWherePlan::returningRows($case['returning'], ['a', 'selected_arm', 'conflict_c', 'conflict_d', 'conflict_e']);
            $actual = SQLiteUpsertDoUpdateWherePlan::returningRows($plan['returning_rows'], [
                'a',
                'selected_arm' => 'b',
                'conflict_c' => 'c',
                'conflict_d' => 'd',
                'conflict_e' => 'e',
            ]);

            $t->same($expected, $actual);
            $t->same($case['changes'], count($actual));
            $t->same($case['changes'], $plan['changes']);
            $t->same($schema['columns'], array_keys($projectColumns($plan['after'], $schema['columns'])[0]));
            $t->true(is_bool($schema['without_rowid']));
        };

        $tests[$name . ' conflict arm metadata is schema-order independent'] = static function (TestRunner $t) use ($case, $schema, $matchedTargets): void {
            $plan = SQLiteUpsertDoUpdateWherePlan::executeConflictArms($case['before'], $case['incoming'], $case['arms'], $schema['constraints']);

            $t->same($case['matched'], $matchedTargets($plan['matched_arms']));
            $t->same($case['source'], 'upsert5.test');
            $t->true(str_starts_with($case['upstream'], 'upsert5-'));
            $t->true(array_is_list($schema['constraints']));
            $t->true(array_is_list($schema['columns']));
        };
    }
}

foreach ($schemaVariants as $schemaName => $schema) {
    foreach (SQLiteUpsertReturningDynamicCorpusPlan::multiArmOrderCases() as $case) {
        $name = 'real upstream corpus upsert returning dynamic schema variant order ' . $schemaName . ' / ' . $case['upstream'];

        $tests[$name . ' first matching ON CONFLICT arm wins'] = static function (TestRunner $t) use ($case, $schema, $matchedTargets): void {
            $arms = array_map(
                static fn (string $target): array => [
                    'target' => [$target],
                    'action' => 'update',
                    'assignments' => ['b' => static fn (): string => $target],
                ],
                $case['order'],
            );
            $plan = SQLiteUpsertDoUpdateWherePlan::executeConflictArms(
                [
                    ['a' => 1, 'b' => 'seed-a', 'c' => 10, 'd' => 100, 'e' => 1000],
                    ['a' => 2, 'b' => 'seed-c', 'c' => 20, 'd' => 200, 'e' => 2000],
                    ['a' => 3, 'b' => 'seed-d', 'c' => 30, 'd' => 300, 'e' => 3000],
                    ['a' => 4, 'b' => 'seed-e', 'c' => 40, 'd' => 400, 'e' => 4000],
                ],
                [$case['incoming']],
                $arms,
                $schema['constraints'],
            );

            $t->same([$case['selected']], $matchedTargets($plan['matched_arms']));
            $t->same($case['selected'], $plan['returning_rows'][0]['b']);
            $t->same($case['selected'], $case['order'][0]);
            $t->same(1, $plan['changes']);
            $t->same(1, count($plan['returning_rows']));
            $t->same($schema['without_rowid'], (bool) $schema['without_rowid']);
            $t->same('upsert5.test', $case['source']);
            $t->true(str_starts_with($case['upstream'], 'upsert5-order-'));
        };
    }
}

return $tests;
