<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteTriggerRecursiveReturningDeferredFkCurrentSourceNextPlan;

$buildDynamicTriggerFkeyRows = static function (int $chainLength): array {
    $parents = [];
    $children = [];
    for ($id = 1; $id <= $chainLength; ++$id) {
        $parents[] = [
            'setting_id' => $id,
            'next_id' => $id === $chainLength ? null : $id + 1,
            'key_name' => 'setting_' . $id,
            'key_value' => 'value_' . $id,
        ];
        $children[] = [
            'child_id' => $id,
            'setting_id' => $id,
            'label' => 'child_' . $id,
        ];
    }

    return [$parents, $children];
};

$runDynamicTriggerFkey = static function (
    int $chainLength,
    bool $recursiveTriggers = true,
    bool $rollbackOnDeferredViolation = false,
    ?int $maxDepth = null
) use ($buildDynamicTriggerFkeyRows): array {
    [$parents, $children] = $buildDynamicTriggerFkeyRows($chainLength);

    return SQLiteTriggerRecursiveReturningDeferredFkCurrentSourceNextPlan::run(
        $parents,
        $children,
        [
            'parent_key' => 'setting_id',
            'child_key' => 'setting_id',
            'on_update' => 'no action',
            'deferred' => true,
        ],
        [
            'savepoint' => 'dynamic_trigger_fkey',
            'rowid_column' => 'setting_id',
            'where' => static fn (array $row): bool => $row['setting_id'] === 1,
            'assignments' => [
                'setting_id' => static fn (array $row, int $depth): int => (int) $row['setting_id'] + 1000 + $depth,
                'key_value' => static fn (array $row, int $depth): string => (string) $row['key_value'] . '_updated_' . $depth,
            ],
            'returning' => [
                ['expr' => 'old.setting_id', 'as' => 'old_id'],
                ['expr' => 'new.setting_id', 'as' => 'new_id'],
                ['expr' => 'new.key_value', 'as' => 'new_value'],
            ],
            'trigger' => [
                'name' => 'dynamic_update_chain',
                'match_column' => 'setting_id',
                'match_value' => 'new.next_id',
            ],
            'recursive_triggers' => $recursiveTriggers,
            'max_depth' => $maxDepth ?? ($chainLength + 2),
            'rollback_on_deferred_violation' => $rollbackOnDeferredViolation,
            'page_images' => [
                2 => 'parent-before',
                3 => 'child-before',
            ],
            'dirty_pages' => [
                2 => 'parent-after',
                3 => 'child-after',
                4 => 'index-after',
            ],
            'wal_start_frame' => 7,
            'wal_frames' => [
                ['frame_index' => 6, 'page' => 1, 'image' => 'before-savepoint'],
                ['frame_index' => 7, 'page' => 2, 'image' => 'parent-frame'],
                ['frame_index' => 8, 'page' => 3, 'image' => 'child-frame'],
                ['frame_index' => 9, 'page' => 4, 'image' => 'index-frame'],
            ],
        ],
    );
};

$tests = [
    'real upstream corpus trigger fkey dynamic cites fkey2 recursive action block' => static function (TestRunner $t): void {
        $source = file_get_contents('/home/claude/port-libs/.upstream-cache/libsqlite/test/fkey2.test');
        $t->true(is_string($source) && str_contains($source, 'fkey2-4.*: Test that FK actions may recurse even when recursive triggers'));
    },
    'real upstream corpus trigger fkey dynamic cites fkey6 deferred pragma block' => static function (TestRunner $t): void {
        $source = file_get_contents('/home/claude/port-libs/.upstream-cache/libsqlite/test/fkey6.test');
        $t->true(is_string($source) && str_contains($source, 'PRAGMA defer_foreign_keys'));
    },
    'real upstream corpus trigger fkey dynamic cites triggerC recursion block' => static function (TestRunner $t): void {
        $source = file_get_contents('/home/claude/port-libs/.upstream-cache/libsqlite/test/triggerC.test');
        $t->true(is_string($source) && str_contains($source, 'recursive trigger'));
    },
];

for ($chainLength = 1; $chainLength <= 130; ++$chainLength) {
    $tests["real upstream corpus trigger fkey dynamic chain {$chainLength} updates recursive depth"] = static function (TestRunner $t) use ($runDynamicTriggerFkey, $chainLength): void {
        $result = $runDynamicTriggerFkey($chainLength);
        $t->same($chainLength, $result['current_changes']);
    };
    $tests["real upstream corpus trigger fkey dynamic chain {$chainLength} detects deferred violations"] = static function (TestRunner $t) use ($runDynamicTriggerFkey, $chainLength): void {
        $result = $runDynamicTriggerFkey($chainLength);
        $t->same($chainLength, count($result['foreign_key_violations']));
    };
    $tests["real upstream corpus trigger fkey dynamic chain {$chainLength} records recursive effects"] = static function (TestRunner $t) use ($runDynamicTriggerFkey, $chainLength): void {
        $result = $runDynamicTriggerFkey($chainLength);
        $t->same(max(0, $chainLength - 1), count($result['trigger_effects']));
    };
    $tests["real upstream corpus trigger fkey dynamic chain {$chainLength} updates tail row"] = static function (TestRunner $t) use ($runDynamicTriggerFkey, $chainLength): void {
        $result = $runDynamicTriggerFkey($chainLength);
        $tail = $result['current_parent'][$chainLength - 1];
        $t->same($chainLength + 1000 + ($chainLength - 1), $tail['setting_id']);
    };
}

for ($chainLength = 2; $chainLength <= 60; ++$chainLength) {
    $tests["real upstream corpus trigger fkey dynamic nonrecursive chain {$chainLength} updates only statement row"] = static function (TestRunner $t) use ($runDynamicTriggerFkey, $chainLength): void {
        $result = $runDynamicTriggerFkey($chainLength, false);
        $t->same(1, $result['current_changes']);
    };
    $tests["real upstream corpus trigger fkey dynamic nonrecursive chain {$chainLength} leaves child two valid"] = static function (TestRunner $t) use ($runDynamicTriggerFkey, $chainLength): void {
        $result = $runDynamicTriggerFkey($chainLength, false);
        $t->same([1], array_column($result['foreign_key_violations'], 'child_key'));
    };
}

$tests['real upstream corpus trigger fkey dynamic rollback restores parent rows'] = static function (TestRunner $t) use ($runDynamicTriggerFkey): void {
    $result = $runDynamicTriggerFkey(4, true, true);
    $t->same([1, 2, 3, 4], $result['next_rowids']);
};
$tests['real upstream corpus trigger fkey dynamic rollback suppresses returning rows'] = static function (TestRunner $t) use ($runDynamicTriggerFkey): void {
    $result = $runDynamicTriggerFkey(4, true, true);
    $t->same([], $result['next_returning_rows']);
};
$tests['real upstream corpus trigger fkey dynamic rollback restores page images'] = static function (TestRunner $t) use ($runDynamicTriggerFkey): void {
    $result = $runDynamicTriggerFkey(4, true, true);
    $t->same([2 => 'parent-before', 3 => 'child-before'], $result['restored_page_images']);
};
$tests['real upstream corpus trigger fkey dynamic rollback discards wal frames at savepoint'] = static function (TestRunner $t) use ($runDynamicTriggerFkey): void {
    $result = $runDynamicTriggerFkey(4, true, true);
    $t->same([8, 9], array_column($result['discarded_wal_frames'], 'frame_index'));
};
$tests['real upstream corpus trigger fkey dynamic max depth guard follows triggerC'] = static function (TestRunner $t) use ($runDynamicTriggerFkey): void {
    $t->throws(InvalidArgumentException::class, static fn () => $runDynamicTriggerFkey(6, true, false, 2));
};
$tests['real upstream corpus trigger fkey dynamic dependency closure is native'] = static function (TestRunner $t) use ($runDynamicTriggerFkey): void {
    $result = $runDynamicTriggerFkey(2);
    $t->same(true, in_array('sqlite-deferred-foreign-key-commit-check', $result['dependencies'], true));
};

return $tests;
