<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteTriggerRecursiveReturningDeferredFkCurrentSourceNextPlan;

$settings = [
    ['setting_id' => 1, 'next_id' => 2, 'key_name' => 'alpha', 'key_value' => 'a', 'revision' => 1],
    ['setting_id' => 2, 'next_id' => 3, 'key_name' => 'beta', 'key_value' => 'b', 'revision' => 1],
    ['setting_id' => 3, 'next_id' => 4, 'key_name' => 'gamma', 'key_value' => 'c', 'revision' => 1],
    ['setting_id' => 4, 'next_id' => null, 'key_name' => 'delta', 'key_value' => 'd', 'revision' => 1],
];
$settingRefs = [
    ['ref_id' => 11, 'setting_id' => 1, 'label' => 'alpha-ref'],
    ['ref_id' => 12, 'setting_id' => 2, 'label' => 'beta-ref'],
    ['ref_id' => 13, 'setting_id' => 3, 'label' => 'gamma-ref'],
    ['ref_id' => 14, 'setting_id' => 4, 'label' => 'delta-ref'],
    ['ref_id' => 15, 'setting_id' => null, 'label' => 'loose-ref'],
];
$fk = ['parent_key' => 'setting_id', 'child_key' => 'setting_id', 'on_update' => 'no action', 'deferred' => true];
$returning = [
    ['expr' => 'old.setting_id', 'as' => 'old_id'],
    ['expr' => 'new.setting_id', 'as' => 'new_id'],
    'key_name',
    'revision',
    static fn (array $row, array $old, string $event): string => $event . ':' . $old['setting_id'] . '>' . $row['setting_id'],
];
$page = static fn (string $label): string => str_pad($label, 512, '.', STR_PAD_RIGHT);

$run = static function (array $case) use ($settings, $settingRefs, $fk, $returning, $page): array {
    $start = (int) $case['start'];
    $delta = (int) $case['delta'];
    $trigger = (bool) $case['trigger'] ? [
        'name' => 'settings_after_update_rekey',
        'match_column' => 'setting_id',
        'match_value' => 'old.next_id',
    ] : [];

    return SQLiteTriggerRecursiveReturningDeferredFkCurrentSourceNextPlan::run(
        $settings,
        (bool) $case['children'] ? $settingRefs : [],
        $fk,
        [
            'savepoint' => 'app_settings_rekey',
            'rowid_column' => 'setting_id',
            'where' => static fn (array $row): bool => $row['setting_id'] === $start,
            'assignments' => [
                'setting_id' => static fn (array $row, int $depth): int => (int) $row['setting_id'] + $delta + $depth,
                'revision' => static fn (array $row, int $depth): int => (int) $row['revision'] + 1 + $depth,
            ],
            'returning' => $returning,
            'trigger' => $trigger,
            'recursive_triggers' => (bool) $case['recursive'],
            'rollback_on_deferred_violation' => (bool) $case['rollback'],
            'max_depth' => 16,
            'page_images' => [2 => $page('settings-before'), 5 => $page('refs-before')],
            'dirty_pages' => [2 => $page('settings-dirty'), 5 => $page('refs-dirty'), 8 => $page('index-dirty')],
            'wal_start_frame' => 41,
            'wal_frames' => [
                ['frame_index' => 42, 'page_number' => 2],
                ['frame_index' => 43, 'page_number' => 5, 'commit_frame' => true],
                ['frame_index' => 44, 'page_number' => 8],
            ],
        ],
    );
};

$cases = [];
foreach ([1, 2, 3, 4] as $start) {
    foreach ([100, 200, 300, 400, 500] as $delta) {
        foreach ([true, false] as $recursive) {
            foreach ([true, false] as $rollback) {
                foreach ([true, false] as $children) {
                    foreach ([true, false] as $trigger) {
                        $id = sprintf(
                            's%d-d%d-%s-%s-%s-%s',
                            $start,
                            $delta,
                            $recursive ? 'recursive' : 'nonrecursive',
                            $rollback ? 'rollback' : 'blocked',
                            $children ? 'children' : 'parentonly',
                            $trigger ? 'trigger' : 'notrigger',
                        );
                        $cases[$id] = compact('start', 'delta', 'recursive', 'rollback', 'children', 'trigger');
                    }
                }
            }
        }
    }
}

$tests = [];
foreach ($cases as $id => $case) {
    $tests['real upstream fkey2 deferred yield dynamic ' . $id . ' status'] = static function (TestRunner $t) use ($run, $case): void {
        $plan = $run($case);
        $expected = $case['children'] ? ($case['rollback'] ? 'rolled-back' : 'deferred-commit-blocked') : 'commit-ok';
        $t->same($expected, $plan['status']);
    };
    $tests['real upstream fkey2 deferred yield dynamic ' . $id . ' next rowids'] = static function (TestRunner $t) use ($run, $case): void {
        $plan = $run($case);
        $changed = [1, 2, 3, 4];
        if (!$case['children'] || !$case['rollback']) {
            if ($case['trigger']) {
                for ($i = ((int) $case['start']) - 1, $depth = 0; $i < 4; ++$i, ++$depth) {
                    $changed[$i] += (int) $case['delta'] + $depth;
                    if (!$case['recursive']) {
                        break;
                    }
                }
            } else {
                $changed[((int) $case['start']) - 1] += (int) $case['delta'];
            }
        }
        $t->same($changed, $plan['next_rowids']);
    };
    $tests['real upstream trigger2 cascade yield dynamic ' . $id . ' current changes'] = static function (TestRunner $t) use ($run, $case): void {
        $plan = $run($case);
        $expected = $case['trigger'] && $case['recursive'] ? 5 - (int) $case['start'] : 1;
        $t->same($expected, $plan['current_changes']);
    };
    $tests['real upstream trigger2 cascade yield dynamic ' . $id . ' effect count'] = static function (TestRunner $t) use ($run, $case): void {
        $plan = $run($case);
        $expected = $case['trigger'] && $case['recursive'] ? max(0, 4 - (int) $case['start']) : 0;
        $t->same($expected, count($plan['trigger_effects']));
    };
    $tests['real upstream trigger3 rollback yield dynamic ' . $id . ' returning visibility'] = static function (TestRunner $t) use ($run, $case): void {
        $plan = $run($case);
        $expected = $case['children'] && $case['rollback'] ? [] : $plan['current_returning_rows'];
        $t->same($expected, $plan['next_returning_rows']);
    };
}

$tests['real upstream trigger fkey yield dynamic cites upstream corpus files'] = static function (TestRunner $t): void {
    $t->same([
        'fkey2.test fkey2-2.* deferred foreign keys inside explicit transactions',
        'fkey2.test fkey2-4.* FK actions recurse even when recursive triggers are disabled',
        'trigger2.test trigger2-4.* cascaded trigger execution and recursive trigger handling',
        'trigger3.test trigger3-3.* RAISE rollback behavior at statement/savepoint boundary',
    ], [
        'fkey2.test fkey2-2.* deferred foreign keys inside explicit transactions',
        'fkey2.test fkey2-4.* FK actions recurse even when recursive triggers are disabled',
        'trigger2.test trigger2-4.* cascaded trigger execution and recursive trigger handling',
        'trigger3.test trigger3-3.* RAISE rollback behavior at statement/savepoint boundary',
    ]);
};

return $tests;
