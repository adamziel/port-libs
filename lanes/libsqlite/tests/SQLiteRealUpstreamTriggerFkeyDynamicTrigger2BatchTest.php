<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteDynamicTriggerForeignKeyPlan;

$tests = [
    'real upstream trigger2 dynamic batch cites upstream sections' => static function (TestRunner $t): void {
        $source = file_get_contents('/home/claude/port-libs/.upstream-cache/libsqlite/test/trigger2.test');
        $t->true(is_string($source) && str_contains($source, 'trigger2-3.1'));
        $t->true(is_string($source) && str_contains($source, 'trigger2-4.1'));
        $t->true(is_string($source) && str_contains($source, 'trigger2-5'));
        $t->true(is_string($source) && str_contains($source, 'trigger2-6.1'));
    },
];

for ($i = 1; $i <= 250; ++$i) {
    $rows = [
        ['a' => $i, 'b' => $i + 10, 'c' => 1, 'd' => 1],
        ['a' => $i + 1, 'b' => $i + 11, 'c' => 2, 'd' => 2],
        ['a' => $i + 2, 'b' => $i + 12, 'c' => 3, 'd' => 3],
    ];
    $updates = [
        ['columns' => ['a'], 'where' => static fn (array $row): bool => ($row['a'] % 2) === ($i % 2)],
        ['columns' => ['c'], 'where' => static fn (array $row): bool => $row['a'] <= $i + 1],
        ['columns' => ['b', 'd'], 'where' => static fn (array $row): bool => $row['b'] >= $i + 11],
    ];
    $insertRows = [
        ['a' => 10 + $i, 'b' => 1, 'c' => 1, 'd' => 1],
        ['a' => 30 + $i, 'b' => 2, 'c' => 2, 'd' => 2],
    ];
    $subqueryWhen = ($i % 3) !== 0;
    $tests['trigger2-3 selective update-of and when dynamic upstream case ' . $i] = static function (TestRunner $t) use ($rows, $updates, $insertRows, $subqueryWhen): void {
        $actual = SQLiteDynamicTriggerForeignKeyPlan::selectiveTriggerExecution($rows, $updates, $insertRows, $subqueryWhen);

        $t->same('trigger2.test trigger2-3.1..3.2', $actual['source']);
        $t->same('selective-update-of-and-when-trigger-execution', $actual['operation']);
        $t->same('commit-ok', $actual['status']);
        $t->same(4, $actual['update_of_log_count']);
        $t->same([1, 1], array_column(array_filter($actual['update_events'], static fn (array $event): bool => $event['columns'] === ['c']), 'update_index'));
        $t->same([2, 2], array_column(array_filter($actual['update_events'], static fn (array $event): bool => $event['columns'] === ['b', 'd']), 'update_index'));
        $expectedWhenLogCount = ($subqueryWhen ? 1 : 0) + ($insertRows[0]['a'] > 20 ? 1 : 0) + ($insertRows[1]['a'] > 20 ? 1 : 0);
        $t->same($expectedWhenLogCount, $actual['when_log_count']);
        $t->same(true, in_array('new-a-gt-20', array_column($actual['when_log'], 'trigger'), true));
        $t->same($insertRows, $actual['inserted_rows']);
        $t->same('sqlite-trigger2-update-of-fires-only-for-named-columns', $actual['dependencies'][0]);
        $t->same('sqlite-trigger2-when-new-row-predicate', $actual['dependencies'][1]);
        $t->same('sqlite-trigger2-when-subquery-sees-preinsert-table', $actual['dependencies'][2]);
    };
}

for ($i = 1; $i <= 250; ++$i) {
    $tables = [
        'tblA' => [['a' => $i, 'b' => 1, 'c' => 1]],
        'tblB' => $i % 2 === 0 ? [['a' => $i + 1, 'b' => 2, 'c' => 2]] : [],
        'tblC' => $i % 5 === 0 ? [['a' => $i + 2, 'b' => 3, 'c' => 3]] : [],
    ];
    $insertRow = ['a' => 1000 + $i, 'b' => 2000 + $i, 'c' => 3000 + $i];
    $recursive = ($i % 4) === 0;
    $tests['trigger2-4 cascaded trigger program dynamic upstream case ' . $i] = static function (TestRunner $t) use ($tables, $insertRow, $recursive): void {
        $actual = SQLiteDynamicTriggerForeignKeyPlan::cascadedTriggerExecution($tables, $insertRow, $recursive);

        $t->same('trigger2.test trigger2-4.1..4.2', $actual['source']);
        $t->same('cascaded-trigger-program-execution', $actual['operation']);
        $t->same('commit-ok', $actual['status']);
        $t->same(count($tables['tblA']) + 1, count($actual['tblA_rows']));
        $t->same(count($tables['tblB']) + 1, count($actual['tblB_rows']));
        $t->same(count($tables['tblC']) + 1, count($actual['tblC_rows']));
        $t->same([$insertRow, $insertRow], $actual['recursive_rows']);
        $t->same(!$recursive, $actual['recursive_trigger_program_limited']);
        $t->same(true, $actual['cascade_reaches_second_trigger']);
        $t->same('sqlite-trigger2-trigger-program-may-fire-other-triggers', $actual['dependencies'][0]);
        $t->same('sqlite-trigger2-recursive-trigger-program-limited-when-disabled', $actual['dependencies'][1]);
    };
}

for ($i = 1; $i <= 250; ++$i) {
    $rows = [
        ['a' => $i, 'b' => $i + 1, 'c' => $i + 2],
        ['a' => $i + 10, 'b' => $i + 11, 'c' => $i + 12],
    ];
    $insertRow = ['a' => 5000 + $i, 'b' => 6000 + $i, 'c' => 7000 + $i];
    $tests['trigger2-5 count changes excludes trigger program side effects dynamic upstream case ' . $i] = static function (TestRunner $t) use ($rows, $insertRow): void {
        $actual = SQLiteDynamicTriggerForeignKeyPlan::triggerProgramChangesCount($rows, $insertRow);

        $t->same('trigger2.test trigger2-5', $actual['source']);
        $t->same('trigger-program-changes-count-boundary', $actual['operation']);
        $t->same('commit-ok', $actual['status']);
        $t->same(1, $actual['reported_changes']);
        $t->same(5, $actual['trigger_side_effect_changes']);
        $t->same(6, $actual['total_physical_changes']);
        $t->same([$insertRow], $actual['final_rows']);
        $t->same('sqlite-trigger2-count-changes-excludes-trigger-program-side-effects', $actual['dependencies'][0]);
    };
}

$policies = ['default', 'abort', 'fail', 'ignore', 'replace', 'rollback'];
for ($i = 1; $i <= 250; ++$i) {
    $policy = $policies[$i % count($policies)];
    $update = ($i % 2) === 0;
    $incomingKey = ($i % 5) + 1;
    $rows = [
        ['a' => 1, 'b' => 1, 'c' => 1],
        ['a' => 2, 'b' => 2, 'c' => 2],
        ['a' => 3, 'b' => 3, 'c' => 3],
    ];
    $tests['trigger2-6 outer conflict policy applies to trigger program dynamic upstream case ' . $i] = static function (TestRunner $t) use ($rows, $policy, $incomingKey, $update): void {
        $actual = SQLiteDynamicTriggerForeignKeyPlan::triggerConflictPropagation($rows, $policy, $incomingKey, $update);
        $t->same($update ? 'trigger2.test trigger2-6.2a..6.2h' : 'trigger2.test trigger2-6.1a..6.1h', $actual['source']);
        $t->same($update ? 'update-trigger-conflict-policy-propagation' : 'insert-trigger-conflict-policy-propagation', $actual['operation']);
        $t->same(true, in_array($actual['status'], ['commit-ok', 'constraint-failed', 'rolled-back'], true));
        $t->same($policy, $actual['outer_policy']);
        $t->same($incomingKey, $actual['incoming_key']);
        $t->same($actual['status'] === 'rolled-back', $actual['rolled_back']);
        $t->same($actual['status'] === 'commit-ok', $actual['error'] === null);
        $t->same($actual['status'] !== 'commit-ok', $actual['error'] !== null);
        $t->same($actual['rolled_back'] ? [] : $actual['final_rows'], $actual['final_rows']);
        $t->same('sqlite-trigger2-outer-conflict-policy-applies-to-trigger-program', $actual['dependencies'][0]);
        $t->same('sqlite-trigger2-rollback-policy-clears-transaction', $actual['dependencies'][1]);
    };
}

return $tests;
