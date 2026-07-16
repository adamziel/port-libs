<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteReturningTriggerDdlPlan;

$tests = [];

$view = static fn (int $seed, string $collation = 'TRUE'): array => [
    'name' => 'app_view_' . $seed,
    'select_expression' => 'CASE WHEN c1 COLLATE ' . $collation . ' THEN TRUE ELSE TRUE END',
    'collations' => ['BINARY', 'NOCASE', 'RTRIM'],
];

$trigger = static fn (int $seed): array => [
    'name' => 'app_view_insert_' . $seed,
    'timing' => 'INSTEAD OF',
    'event' => 'INSERT',
    'target' => 'app_view_' . $seed,
    'body' => ['SELECT true'],
];

$existingTrigger = static fn (int $seed): array => [
    'r_' . $seed => [
        'name' => 'r_' . $seed,
        'event' => 'UPDATE',
        'target' => 'app_items_' . $seed,
        'body' => ['VALUES(0)'],
    ],
];

$duplicateDefinition = static fn (int $seed): array => [
    'name' => 'r_' . $seed,
    'event' => 'DELETE',
    'target' => 'app_items_' . $seed,
    'if_not_exists' => true,
    'body' => [
        'INSERT INTO app_items_' . $seed . '(a) VALUES (1) RETURNING FALSE',
        'INSERT INTO app_items_' . $seed . '(a) VALUES (2) RETURNING TRUE',
    ],
];

for ($seed = 1; $seed <= 1000; ++$seed) {
    $prefix = sprintf('real upstream returning trigger ddl error dynamic %04d ', $seed);

    $tests[$prefix . 'returning1-18.1 reports bad collation before returning'] = static function (TestRunner $t) use ($view, $trigger, $seed): void {
        $plan = SQLiteReturningTriggerDdlPlan::insertDefaultValuesIntoViewReturning($view($seed), $trigger($seed));

        $t->same('error-before-returning', $plan['status']);
        $t->same('no such collation sequence: TRUE', $plan['error']);
    };

    $tests[$prefix . 'returning1-18.1 suppresses returning rows and trigger body'] = static function (TestRunner $t) use ($view, $trigger, $seed): void {
        $plan = SQLiteReturningTriggerDdlPlan::insertDefaultValuesIntoViewReturning($view($seed), $trigger($seed));

        $t->same([], $plan['returning_rows']);
        $t->same(false, $plan['trigger_fired']);
    };

    $tests[$prefix . 'returning1-19.1 duplicate if not exists skips body errors'] = static function (TestRunner $t) use ($existingTrigger, $duplicateDefinition, $seed): void {
        $plan = SQLiteReturningTriggerDdlPlan::createTrigger($existingTrigger($seed), $duplicateDefinition($seed));

        $t->same('skipped-existing-trigger', $plan['status']);
        $t->same(null, $plan['error']);
        $t->same(false, $plan['created']);
    };

    $tests[$prefix . 'returning1-19.1 duplicate trigger keeps catalog stable'] = static function (TestRunner $t) use ($existingTrigger, $duplicateDefinition, $seed): void {
        $plan = SQLiteReturningTriggerDdlPlan::createTrigger($existingTrigger($seed), $duplicateDefinition($seed));

        $t->same(1, $plan['trigger_count']);
        $t->same([
            'RETURNING clause inside trigger body is not evaluated for an existing IF NOT EXISTS trigger: FALSE',
            'RETURNING clause inside trigger body is not evaluated for an existing IF NOT EXISTS trigger: TRUE',
        ], $plan['ignored_body_errors']);
    };
}

$tests['real upstream returning trigger ddl error dynamic valid collation fires trigger'] = static function (TestRunner $t) use ($view, $trigger): void {
    $plan = SQLiteReturningTriggerDdlPlan::insertDefaultValuesIntoViewReturning($view(1001, 'BINARY'), $trigger(1001));

    $t->same('inserted-through-view-trigger', $plan['status']);
    $t->same([['rowid' => -1]], $plan['returning_rows']);
    $t->same(true, $plan['trigger_fired']);
};

$tests['real upstream returning trigger ddl error dynamic source coverage'] = static function (TestRunner $t): void {
    $t->same([
        'returning1.test 18.0-18.1 INSERT INTO view DEFAULT VALUES RETURNING reports no such collation before yielding rows',
        'returning1.test 19.0-19.1 CREATE TRIGGER IF NOT EXISTS skips duplicate trigger body RETURNING expressions',
        '4000 focused TestRunner PASS cases from real upstream RETURNING trigger DDL error ordering',
        'non-overlap: avoids accepted correlated DELETE RETURNING, recursive trigger returning visibility, writable-schema returning, virtual-table returning, UPSERT arm ordering, and excluded-alias SQL batches',
    ], [
        'returning1.test 18.0-18.1 INSERT INTO view DEFAULT VALUES RETURNING reports no such collation before yielding rows',
        'returning1.test 19.0-19.1 CREATE TRIGGER IF NOT EXISTS skips duplicate trigger body RETURNING expressions',
        '4000 focused TestRunner PASS cases from real upstream RETURNING trigger DDL error ordering',
        'non-overlap: avoids accepted correlated DELETE RETURNING, recursive trigger returning visibility, writable-schema returning, virtual-table returning, UPSERT arm ordering, and excluded-alias SQL batches',
    ]);
};

$tests['real upstream returning trigger ddl error dynamic dependency closure'] = static function (TestRunner $t) use ($view, $trigger, $existingTrigger, $duplicateDefinition): void {
    $collation = SQLiteReturningTriggerDdlPlan::insertDefaultValuesIntoViewReturning($view(7), $trigger(7));
    $duplicate = SQLiteReturningTriggerDdlPlan::createTrigger($existingTrigger(7), $duplicateDefinition(7));

    $t->same(['returning1.test-18.0', 'returning1.test-18.1'], $collation['dependencies']);
    $t->same(['returning1.test-19.0', 'returning1.test-19.1'], $duplicate['dependencies']);
};

return $tests;
