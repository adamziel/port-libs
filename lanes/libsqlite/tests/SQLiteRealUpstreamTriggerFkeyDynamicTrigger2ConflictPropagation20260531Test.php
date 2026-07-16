<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteUpstreamTriggerFkeyDynamicPlan;

$value = static function (array $array, string $path): mixed {
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

$sourcePath = '/home/claude/port-libs/.upstream-cache/libsqlite/test/trigger2.test';
$plan = SQLiteUpstreamTriggerFkeyDynamicPlan::trigger2ConflictPropagation();

$tests = [
    'real upstream trigger2 conflict propagation cites insert conflict section' => static function (TestRunner $t) use ($sourcePath): void {
        $source = file_get_contents($sourcePath);
        $t->true(is_string($source));
        $t->contains('Handling of ON CONFLICT by INSERT statements inside triggers', $source);
        $t->contains('INSERT OR IGNORE INTO tbl values (new.a, 0, 0)', $source);
        $t->contains('do_test trigger2-6.1g', $source);
    },
    'real upstream trigger2 conflict propagation cites update conflict section' => static function (TestRunner $t) use ($sourcePath): void {
        $source = file_get_contents($sourcePath);
        $t->true(is_string($source));
        $t->contains('Handling of ON CONFLICT by UPDATE statements inside triggers', $source);
        $t->contains('UPDATE OR IGNORE tbl SET a = new.a, c = 10', $source);
        $t->contains('do_test trigger2-6.2g', $source);
    },
    'real upstream trigger2 conflict propagation plan shape' => static function (TestRunner $t) use ($plan): void {
        $t->same('trigger2.test', $plan['source']);
        $t->same('trigger2-6.1a', $plan['scenarios'][0]);
        $t->same('trigger2-6.2g', $plan['scenarios'][10]);
        $t->same(1100, count($plan['cases']));
    },
    'real upstream trigger2 conflict propagation dependencies' => static function (TestRunner $t) use ($plan): void {
        $t->same('sqlite-upstream-trigger2-outer-conflict-policy-controls-trigger-insert-conflict', $plan['dependencies'][0]);
        $t->same('sqlite-upstream-trigger2-outer-conflict-policy-controls-trigger-update-conflict', $plan['dependencies'][1]);
        $t->same('sqlite-upstream-trigger2-fail-preserves-statement-row-before-trigger-conflict', $plan['dependencies'][2]);
        $t->same('sqlite-upstream-trigger2-rollback-conflict-rolls-back-transaction', $plan['dependencies'][3]);
        $t->same('sqlite-upstream-trigger2-replace-conflict-replaces-trigger-target-row', $plan['dependencies'][4]);
    },
];

$expectedByScenario = [
    'trigger2-6.1a' => ['trigger_kind' => 'insert-trigger', 'outer_conflict' => '', 'status' => 'commit-ok', 'rolled_back' => false, 'statement_changes_preserved' => false, 'transaction_rolled_back' => false, 'replace_changed_trigger_target' => false, 'final_rows' => [[1, 2, 3]], 'trigger_rows' => [], 'error' => null],
    'trigger2-6.1b' => ['trigger_kind' => 'insert-trigger', 'outer_conflict' => 'abort', 'status' => 'constraint-failed', 'rolled_back' => false, 'statement_changes_preserved' => false, 'transaction_rolled_back' => false, 'replace_changed_trigger_target' => false, 'final_rows' => [[1, 2, 3]], 'trigger_rows' => [], 'error' => 'UNIQUE constraint failed: tbl.a'],
    'trigger2-6.1d' => ['trigger_kind' => 'insert-trigger', 'outer_conflict' => 'fail', 'status' => 'constraint-failed', 'rolled_back' => false, 'statement_changes_preserved' => true, 'transaction_rolled_back' => false, 'replace_changed_trigger_target' => false, 'final_rows' => [[1, 2, 3], [2, 2, 3]], 'trigger_rows' => [], 'error' => 'UNIQUE constraint failed: tbl.a'],
    'trigger2-6.1f' => ['trigger_kind' => 'insert-trigger', 'outer_conflict' => 'replace', 'status' => 'commit-ok', 'rolled_back' => false, 'statement_changes_preserved' => false, 'transaction_rolled_back' => false, 'replace_changed_trigger_target' => true, 'final_rows' => [[1, 2, 3], [2, 0, 0]], 'trigger_rows' => [[2, 0, 0]], 'error' => null],
    'trigger2-6.1g' => ['trigger_kind' => 'insert-trigger', 'outer_conflict' => 'rollback', 'status' => 'constraint-failed', 'rolled_back' => true, 'statement_changes_preserved' => false, 'transaction_rolled_back' => true, 'replace_changed_trigger_target' => false, 'final_rows' => [], 'trigger_rows' => [], 'error' => 'UNIQUE constraint failed: tbl.a'],
    'trigger2-6.2a' => ['trigger_kind' => 'update-trigger', 'outer_conflict' => '', 'status' => 'commit-ok', 'rolled_back' => false, 'statement_changes_preserved' => false, 'transaction_rolled_back' => false, 'replace_changed_trigger_target' => false, 'final_rows' => [[1, 2, 10], [6, 3, 4]], 'trigger_rows' => [[1, 2, 10]], 'error' => null],
    'trigger2-6.2b' => ['trigger_kind' => 'update-trigger', 'outer_conflict' => 'abort', 'status' => 'constraint-failed', 'rolled_back' => false, 'statement_changes_preserved' => false, 'transaction_rolled_back' => false, 'replace_changed_trigger_target' => false, 'final_rows' => [[1, 2, 10], [6, 3, 4]], 'trigger_rows' => [], 'error' => 'UNIQUE constraint failed: tbl.a'],
    'trigger2-6.2d' => ['trigger_kind' => 'update-trigger', 'outer_conflict' => 'fail', 'status' => 'constraint-failed', 'rolled_back' => false, 'statement_changes_preserved' => true, 'transaction_rolled_back' => false, 'replace_changed_trigger_target' => false, 'final_rows' => [[4, 2, 10], [6, 3, 4]], 'trigger_rows' => [], 'error' => 'UNIQUE constraint failed: tbl.a'],
    'trigger2-6.2f.1' => ['trigger_kind' => 'update-trigger', 'outer_conflict' => 'replace', 'status' => 'commit-ok', 'rolled_back' => false, 'statement_changes_preserved' => false, 'transaction_rolled_back' => false, 'replace_changed_trigger_target' => true, 'final_rows' => [[1, 3, 10]], 'trigger_rows' => [[1, 3, 10]], 'error' => null],
    'trigger2-6.2f.2' => ['trigger_kind' => 'update-trigger', 'outer_conflict' => '', 'status' => 'commit-ok', 'rolled_back' => false, 'statement_changes_preserved' => false, 'transaction_rolled_back' => false, 'replace_changed_trigger_target' => false, 'final_rows' => [[1, 3, 10], [2, 3, 4]], 'trigger_rows' => [], 'error' => null],
    'trigger2-6.2g' => ['trigger_kind' => 'update-trigger', 'outer_conflict' => 'rollback', 'status' => 'constraint-failed', 'rolled_back' => true, 'statement_changes_preserved' => false, 'transaction_rolled_back' => true, 'replace_changed_trigger_target' => false, 'final_rows' => [[4, 2, 3], [6, 3, 4]], 'trigger_rows' => [], 'error' => 'UNIQUE constraint failed: tbl.a'],
];

foreach ($plan['cases'] as $case) {
    $scenario = (string) $case['case'];
    $expected = $expectedByScenario[$scenario];
    foreach ([
        'source' => 'trigger2.test',
        'section' => 'trigger2-6',
        'case' => $scenario,
        'variant' => $case['variant'],
        'trigger_kind' => $expected['trigger_kind'],
        'outer_conflict' => $expected['outer_conflict'],
        'status' => $expected['status'],
        'rolled_back' => $expected['rolled_back'],
        'statement_changes_preserved' => $expected['statement_changes_preserved'],
        'transaction_rolled_back' => $expected['transaction_rolled_back'],
        'replace_changed_trigger_target' => $expected['replace_changed_trigger_target'],
        'final_rows' => $expected['final_rows'],
        'trigger_rows' => $expected['trigger_rows'],
        'error' => $expected['error'],
    ] as $path => $expectedValue) {
        $tests[sprintf('real upstream trigger2 conflict propagation %04d %s %s', $case['variant'], $scenario, $path)] = static function (TestRunner $t) use ($case, $value, $path, $expectedValue): void {
            $t->same($expectedValue, $value($case, (string) $path));
        };
    }
}

return $tests;
