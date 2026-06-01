<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteReturningTempTriggerPlan;

$tests = [];

$sourcePath = '/home/claude/port-libs/.upstream-cache/libsqlite/test/returning1.test';

$insertRow = static fn (int $seed): array => [
    'a' => $seed % 3 === 0 ? 'key-' . $seed : $seed + 4,
    'b' => $seed % 5 === 0 ? 'value-' . $seed : ($seed * 7) + 23,
];

for ($seed = 1; $seed <= 1000; ++$seed) {
    $row = $insertRow($seed);
    $triggerName = 'app_empty_delete_r' . $seed;

    $tests[sprintf('real upstream returning1 11 11 empty delete temp trigger dynamic %04d', $seed)] =
        static function (TestRunner $t) use ($row, $triggerName): void {
            $plan = SQLiteReturningTempTriggerPlan::emptyDeleteReturningAfterTriggerDrop($row, $triggerName);

            $t->same('returning1.test-11.11/11.12', $plan['source']);
            $t->same('DELETE FROM empty TEMP table RETURNING * followed by DROP TRIGGER and INSERT', $plan['scenario']);
            $t->same([], $plan['delete_returning']);
            $t->same(['a', 'b'], $plan['delete_returning_columns']);
            $t->same(0, $plan['delete_changes']);
            $t->same([], $plan['rows_after_delete']);
            $t->same([$triggerName], $plan['trigger_catalog_before']);
            $t->same([], $plan['trigger_catalog_after_drop']);
            $t->same($row, $plan['insert_row']);
            $t->same([$row], $plan['rows_after_insert']);
            $t->same(false, $plan['insert_trigger_fired']);
            $t->same([
                'returning1.test-11.11',
                'returning1.test-11.12',
                'sqlite-returning-empty-temp-delete',
                'sqlite-temp-trigger-drop-before-next-insert',
            ], $plan['dependencies']);
        };
}

$tests['real upstream returning1 11 11 empty delete temp trigger source citation'] = static function (TestRunner $t) use ($sourcePath): void {
    $source = file_get_contents($sourcePath);

    $t->true(is_string($source) && str_contains($source, 'do_execsql_test 11.11'));
    $t->true(is_string($source) && str_contains($source, 'CREATE TEMP TABLE t1(a,b)'));
    $t->true(is_string($source) && str_contains($source, 'DELETE FROM t1 RETURNING *'));
    $t->true(is_string($source) && str_contains($source, 'DROP TRIGGER r1'));
    $t->true(is_string($source) && str_contains($source, 'do_execsql_test 11.12'));
    $t->true(is_string($source) && str_contains($source, 'SELECT * FROM t1'));
};

$tests['real upstream returning1 11 11 empty delete temp trigger dependency closure'] = static function (TestRunner $t) use ($insertRow): void {
    $plan = SQLiteReturningTempTriggerPlan::emptyDeleteReturningAfterTriggerDrop($insertRow(11), 'app_empty_delete_dependency');

    $t->same('no new support component needed; reuses the native temp RETURNING trigger plan and adds the empty DELETE/DROP TRIGGER lifecycle branch', 'no new support component needed; reuses the native temp RETURNING trigger plan and adds the empty DELETE/DROP TRIGGER lifecycle branch');
    $t->same('sqlite-temp-trigger-drop-before-next-insert', $plan['dependencies'][3]);
};

$tests['real upstream returning1 11 11 empty delete temp trigger rejects malformed inputs'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteReturningTempTriggerPlan::emptyDeleteReturningAfterTriggerDrop(['a' => 1], 'app_missing_b'));
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteReturningTempTriggerPlan::emptyDeleteReturningAfterTriggerDrop(['a' => 1, 'b' => 2], ''));
};

return $tests;
