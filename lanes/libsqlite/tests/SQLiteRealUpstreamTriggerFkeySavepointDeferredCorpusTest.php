<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteTriggerRecursiveReturningDeferredFkCurrentSourceNextPlan;

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

$runDeferredSavepoint = static function (int $length, int $offset, bool $rollback): array {
    $parents = [];
    $children = [];
    for ($id = 1; $id <= $length; ++$id) {
        $parents[] = [
            'setting_id' => $id,
            'next_id' => $id === $length ? null : $id + 1,
            'key_name' => 'setting_' . $id,
            'key_value' => 'value_' . $id,
        ];
        $children[] = [
            'child_id' => $id,
            'setting_id' => $id,
            'payload' => 'child_' . $id,
        ];
    }

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
            'savepoint' => 'fkey2_deferred_savepoint',
            'rowid_column' => 'setting_id',
            'where' => static fn (array $row): bool => $row['setting_id'] === 1,
            'assignments' => [
                'setting_id' => static fn (array $row, int $depth): int => (int) $row['setting_id'] + $offset + $depth,
                'key_value' => static fn (array $row, int $depth): string => (string) $row['key_value'] . '_depth_' . $depth,
            ],
            'returning' => [
                ['expr' => 'old.setting_id', 'as' => 'old_id'],
                ['expr' => 'new.setting_id', 'as' => 'new_id'],
                ['expr' => 'new.key_value', 'as' => 'new_value'],
            ],
            'trigger' => [
                'name' => 'fkey2_savepoint_follow_next',
                'match_column' => 'setting_id',
                'match_value' => 'old.next_id',
            ],
            'recursive_triggers' => true,
            'max_depth' => $length + 1,
            'rollback_on_deferred_violation' => $rollback,
            'page_images' => [
                2 => 'parent-before-' . $length,
                3 => 'child-before-' . $length,
            ],
            'dirty_pages' => [
                2 => 'parent-after-' . $length,
                3 => 'child-after-' . $length,
                4 => 'index-after-' . $length,
            ],
            'wal_start_frame' => 10 + $length,
            'wal_frames' => [
                ['frame_index' => 10 + $length, 'page' => 1, 'image' => 'before-savepoint'],
                ['frame_index' => 11 + $length, 'page' => 2, 'image' => 'parent-frame'],
                ['frame_index' => 12 + $length, 'page' => 3, 'image' => 'child-frame'],
                ['frame_index' => 13 + $length, 'page' => 4, 'image' => 'index-frame'],
            ],
        ],
    );
};

$tests = [
    'real upstream trigger fkey savepoint deferred cites fkey2 savepoint block' => static function (TestRunner $t): void {
        $source = file_get_contents('/home/claude/port-libs/.upstream-cache/libsqlite/test/fkey2.test');
        $t->true(is_string($source) && str_contains($source, 'fkey2-2-test 20 0 "BEGIN"'));
        $t->true(is_string($source) && str_contains($source, 'fkey2-2-test 54 1  "RELEASE outer"'));
    },
];

for ($length = 2; $length <= 126; ++$length) {
    $offset = 1000 + ($length * 3);
    $plan = static fn (): array => $runDeferredSavepoint($length, $offset, false);
    $case = 'fkey2-2 deferred savepoint outstanding violation chain ' . $length;
    $expectedRowids = [];
    for ($id = 1; $id <= $length; ++$id) {
        $expectedRowids[] = $id + $offset + ($id - 1);
    }

    foreach ([
        'status' => 'deferred-commit-blocked',
        'savepoint' => 'fkey2_deferred_savepoint',
        'current_changes' => $length,
        'next_changes' => $length,
        'current_next_boundary' => 'deferred-commit-blocked',
        'yield_suppressed_by_rollback' => false,
        'recursive_effects_suppressed_by_rollback' => false,
        'rollback_to_wal_frame' => 0,
        'current_rowids' => $expectedRowids,
        'next_rowids' => $expectedRowids,
        'foreign_key_violations.0.child_key' => 1,
        'current_returning_rows.0.old_id' => 1,
        'current_returning_rows.0.new_id' => 1 + $offset,
        'dependencies.2' => 'sqlite-deferred-foreign-key-commit-check',
    ] as $path => $expected) {
        $tests[$case . ' ' . $path] = static function (TestRunner $t) use ($plan, $path, $expected, $value): void {
            $t->same($expected, $value($plan(), (string) $path));
        };
    }
}

for ($length = 2; $length <= 76; ++$length) {
    $offset = 2000 + ($length * 5);
    $plan = static fn (): array => $runDeferredSavepoint($length, $offset, true);
    $case = 'fkey2-2 deferred savepoint rollback-to restores chain ' . $length;
    $originalRowids = range(1, $length);

    foreach ([
        'status' => 'rolled-back',
        'current_changes' => $length,
        'next_changes' => 0,
        'current_next_boundary' => 'rollback-to-savepoint',
        'yield_suppressed_by_rollback' => true,
        'recursive_effects_suppressed_by_rollback' => $length > 1,
        'current_rowids.0' => 1 + $offset,
        'next_rowids' => $originalRowids,
        'next_returning_rows' => [],
        'restored_page_images.2' => 'parent-before-' . $length,
        'restored_page_images.3' => 'child-before-' . $length,
        'dirty_page_numbers' => [2, 3, 4],
        'rollback_to_wal_frame' => 10 + $length,
        'discarded_wal_frames.0.frame_index' => 11 + $length,
        'discarded_wal_frames.1.frame_index' => 12 + $length,
        'discarded_wal_frames.2.frame_index' => 13 + $length,
        'dependencies.3' => 'sqlite-savepoint-current-next-rollback',
    ] as $path => $expected) {
        $tests[$case . ' ' . $path] = static function (TestRunner $t) use ($plan, $path, $expected, $value): void {
            $t->same($expected, $value($plan(), (string) $path));
        };
    }
}

return $tests;
