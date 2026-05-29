<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteTriggerRecursiveReturningDeferredFkCurrentSourceNextPlan;

$parents = [
    ['option_id' => 1, 'next_id' => 2, 'option_name' => 'siteurl', 'option_value' => 'https://old.test', 'revision' => 1],
    ['option_id' => 2, 'next_id' => 3, 'option_name' => 'home', 'option_value' => 'https://old.test/home', 'revision' => 1],
    ['option_id' => 3, 'next_id' => null, 'option_name' => 'blogname', 'option_value' => 'Old Site', 'revision' => 1],
];
$children = [
    ['meta_id' => 11, 'option_id' => 1, 'meta_key' => '_origin'],
    ['meta_id' => 12, 'option_id' => 2, 'meta_key' => '_origin'],
    ['meta_id' => 13, 'option_id' => 3, 'meta_key' => '_origin'],
];
$fk = [
    'parent_key' => 'option_id',
    'child_key' => 'option_id',
    'on_update' => 'no action',
    'deferred' => true,
];
$returning = [
    ['expr' => 'old.option_id', 'as' => 'old_id'],
    ['expr' => 'new.option_id', 'as' => 'new_id'],
    'option_name',
    'revision',
    static fn (array $row, array $old, string $event): string => $event . ':' . $old['option_id'] . '>' . $row['option_id'],
];
$page = static fn (string $label): string => str_pad($label, 512, '.', STR_PAD_RIGHT);
$baseStatement = [
    'savepoint' => 'wp_options_import',
    'where' => static fn (array $row): bool => $row['option_id'] === 1,
    'assignments' => [
        'option_id' => static fn (array $row, int $depth): int => (int) $row['option_id'] + 100 + $depth,
        'revision' => static fn (array $row, int $depth): int => (int) $row['revision'] + 1 + $depth,
    ],
    'returning' => $returning,
    'trigger' => [
        'name' => 'wp_options_au_recursive_rekey',
        'match_column' => 'option_id',
        'match_value' => 'old.next_id',
    ],
    'page_images' => [2 => $page('options-before'), 4 => $page('meta-before')],
    'dirty_pages' => [2 => $page('options-dirty'), 4 => $page('meta-dirty'), 7 => $page('overflow-dirty')],
    'wal_start_frame' => 31,
    'wal_frames' => [
        ['frame_index' => 32, 'page_number' => 2],
        ['frame_index' => 33, 'page_number' => 4, 'commit_frame' => true],
        ['frame_index' => 34, 'page_number' => 7],
    ],
];

$rollbackPlan = static fn (): array => SQLiteTriggerRecursiveReturningDeferredFkCurrentSourceNextPlan::run(
    $parents,
    $children,
    $fk,
    $baseStatement + ['rollback_on_deferred_violation' => true],
);
$blockedPlan = static fn (): array => SQLiteTriggerRecursiveReturningDeferredFkCurrentSourceNextPlan::run($parents, $children, $fk, $baseStatement);
$commitPlan = static function () use ($parents, $children, $fk, $baseStatement): array {
    $withoutChildren = [];

    return SQLiteTriggerRecursiveReturningDeferredFkCurrentSourceNextPlan::run($parents, $withoutChildren, $fk, $baseStatement + ['rollback_on_deferred_violation' => true]);
};
$recursiveOffPlan = static fn (): array => SQLiteTriggerRecursiveReturningDeferredFkCurrentSourceNextPlan::run(
    $parents,
    $children,
    $fk,
    $baseStatement + ['recursive_triggers' => false, 'rollback_on_deferred_violation' => true],
);

$tests = [
    'trigger recursive returning deferred fk current source next111 rollback status' => static function (TestRunner $t) use ($rollbackPlan): void {
        $t->same('rolled-back', $rollbackPlan()['status']);
    },
    'trigger recursive returning deferred fk current source next111 rollback boundary' => static function (TestRunner $t) use ($rollbackPlan): void {
        $t->same('rollback-to-savepoint', $rollbackPlan()['current_next_boundary']);
    },
    'trigger recursive returning deferred fk current source next111 savepoint name' => static function (TestRunner $t) use ($rollbackPlan): void {
        $t->same('wp_options_import', $rollbackPlan()['savepoint']);
    },
    'trigger recursive returning deferred fk current source next111 recursive flag' => static function (TestRunner $t) use ($rollbackPlan): void {
        $t->same(true, $rollbackPlan()['recursive_triggers']);
    },
    'trigger recursive returning deferred fk current source next111 current rowids include recursive updates' => static function (TestRunner $t) use ($rollbackPlan): void {
        $t->same([101, 103, 105], $rollbackPlan()['current_rowids']);
    },
    'trigger recursive returning deferred fk current source next111 next rowids restored' => static function (TestRunner $t) use ($rollbackPlan): void {
        $t->same([1, 2, 3], $rollbackPlan()['next_rowids']);
    },
    'trigger recursive returning deferred fk current source next111 current parent first revision' => static function (TestRunner $t) use ($rollbackPlan): void {
        $t->same(2, $rollbackPlan()['current_parent'][0]['revision']);
    },
    'trigger recursive returning deferred fk current source next111 current parent recursive revision' => static function (TestRunner $t) use ($rollbackPlan): void {
        $t->same(3, $rollbackPlan()['current_parent'][1]['revision']);
    },
    'trigger recursive returning deferred fk current source next111 current parent second recursive revision' => static function (TestRunner $t) use ($rollbackPlan): void {
        $t->same(4, $rollbackPlan()['current_parent'][2]['revision']);
    },
    'trigger recursive returning deferred fk current source next111 next parent restored' => static function (TestRunner $t) use ($rollbackPlan, $parents): void {
        $t->same($parents, $rollbackPlan()['next_parent']);
    },
    'trigger recursive returning deferred fk current source next111 next child restored' => static function (TestRunner $t) use ($rollbackPlan, $children): void {
        $t->same($children, $rollbackPlan()['next_child']);
    },
    'trigger recursive returning deferred fk current source next111 top level returning count' => static function (TestRunner $t) use ($rollbackPlan): void {
        $t->same(1, count($rollbackPlan()['current_yielded']));
    },
    'trigger recursive returning deferred fk current source next111 trigger effects count' => static function (TestRunner $t) use ($rollbackPlan): void {
        $t->same(2, count($rollbackPlan()['trigger_effects']));
    },
    'trigger recursive returning deferred fk current source next111 returning old id' => static function (TestRunner $t) use ($rollbackPlan): void {
        $t->same(1, $rollbackPlan()['current_returning_rows'][0]['old_id']);
    },
    'trigger recursive returning deferred fk current source next111 returning new id' => static function (TestRunner $t) use ($rollbackPlan): void {
        $t->same(101, $rollbackPlan()['current_returning_rows'][0]['new_id']);
    },
    'trigger recursive returning deferred fk current source next111 returning option name' => static function (TestRunner $t) use ($rollbackPlan): void {
        $t->same('siteurl', $rollbackPlan()['current_returning_rows'][0]['option_name']);
    },
    'trigger recursive returning deferred fk current source next111 returning revision' => static function (TestRunner $t) use ($rollbackPlan): void {
        $t->same(2, $rollbackPlan()['current_returning_rows'][0]['revision']);
    },
    'trigger recursive returning deferred fk current source next111 returning callable' => static function (TestRunner $t) use ($rollbackPlan): void {
        $t->same('update:1>101', $rollbackPlan()['current_returning_rows'][0]['expr4']);
    },
    'trigger recursive returning deferred fk current source next111 next returning suppressed' => static function (TestRunner $t) use ($rollbackPlan): void {
        $t->same([], $rollbackPlan()['next_returning_rows']);
    },
    'trigger recursive returning deferred fk current source next111 yielded suppressed flag' => static function (TestRunner $t) use ($rollbackPlan): void {
        $t->same(true, $rollbackPlan()['yield_suppressed_by_rollback']);
    },
    'trigger recursive returning deferred fk current source next111 recursive effects suppressed flag' => static function (TestRunner $t) use ($rollbackPlan): void {
        $t->same(true, $rollbackPlan()['recursive_effects_suppressed_by_rollback']);
    },
    'trigger recursive returning deferred fk current source next111 current changes include recursive changes' => static function (TestRunner $t) use ($rollbackPlan): void {
        $t->same(3, $rollbackPlan()['current_changes']);
    },
    'trigger recursive returning deferred fk current source next111 next changes reset' => static function (TestRunner $t) use ($rollbackPlan): void {
        $t->same(0, $rollbackPlan()['next_changes']);
    },
    'trigger recursive returning deferred fk current source next111 first effect trigger name' => static function (TestRunner $t) use ($rollbackPlan): void {
        $t->same('wp_options_au_recursive_rekey', $rollbackPlan()['trigger_effects'][0]['trigger']);
    },
    'trigger recursive returning deferred fk current source next111 first effect depth' => static function (TestRunner $t) use ($rollbackPlan): void {
        $t->same(1, $rollbackPlan()['trigger_effects'][0]['depth']);
    },
    'trigger recursive returning deferred fk current source next111 second effect depth' => static function (TestRunner $t) use ($rollbackPlan): void {
        $t->same(2, $rollbackPlan()['trigger_effects'][1]['depth']);
    },
    'trigger recursive returning deferred fk current source next111 effect old keys' => static function (TestRunner $t) use ($rollbackPlan): void {
        $t->same([2, 3], array_column($rollbackPlan()['trigger_effects'], 'old_key'));
    },
    'trigger recursive returning deferred fk current source next111 effect new keys' => static function (TestRunner $t) use ($rollbackPlan): void {
        $t->same([103, 105], array_column($rollbackPlan()['trigger_effects'], 'new_key'));
    },
    'trigger recursive returning deferred fk current source next111 violation count' => static function (TestRunner $t) use ($rollbackPlan): void {
        $t->same(3, count($rollbackPlan()['foreign_key_violations']));
    },
    'trigger recursive returning deferred fk current source next111 violation child keys' => static function (TestRunner $t) use ($rollbackPlan): void {
        $t->same([1, 2, 3], array_column($rollbackPlan()['foreign_key_violations'], 'child_key'));
    },
    'trigger recursive returning deferred fk current source next111 violation phase' => static function (TestRunner $t) use ($rollbackPlan): void {
        $t->same(['deferred-commit', 'deferred-commit', 'deferred-commit'], array_column($rollbackPlan()['foreign_key_violations'], 'phase'));
    },
    'trigger recursive returning deferred fk current source next111 fk action count' => static function (TestRunner $t) use ($rollbackPlan): void {
        $t->same(3, count($rollbackPlan()['foreign_key_actions']));
    },
    'trigger recursive returning deferred fk current source next111 rollback page numbers' => static function (TestRunner $t) use ($rollbackPlan): void {
        $t->same([2, 4, 7], $rollbackPlan()['rollback_page_numbers']);
    },
    'trigger recursive returning deferred fk current source next111 restored page image keys' => static function (TestRunner $t) use ($rollbackPlan): void {
        $t->same([2, 4], array_keys($rollbackPlan()['restored_page_images']));
    },
    'trigger recursive returning deferred fk current source next111 dirty page numbers' => static function (TestRunner $t) use ($rollbackPlan): void {
        $t->same([2, 4, 7], $rollbackPlan()['dirty_page_numbers']);
    },
    'trigger recursive returning deferred fk current source next111 rollback wal frame' => static function (TestRunner $t) use ($rollbackPlan): void {
        $t->same(31, $rollbackPlan()['rollback_to_wal_frame']);
    },
    'trigger recursive returning deferred fk current source next111 discarded wal frames' => static function (TestRunner $t) use ($rollbackPlan): void {
        $t->same([32, 33, 34], array_column($rollbackPlan()['discarded_wal_frames'], 'frame_index'));
    },
    'trigger recursive returning deferred fk current source next111 discarded commit frame preserved' => static function (TestRunner $t) use ($rollbackPlan): void {
        $t->same(true, $rollbackPlan()['discarded_wal_frames'][1]['commit_frame']);
    },
    'trigger recursive returning deferred fk current source next111 dependency marker' => static function (TestRunner $t) use ($rollbackPlan): void {
        $t->same(true, in_array('sqlite-recursive-triggers-current-source', $rollbackPlan()['dependencies'], true));
    },
    'trigger recursive returning deferred fk current source next111 blocked status' => static function (TestRunner $t) use ($blockedPlan): void {
        $t->same('deferred-commit-blocked', $blockedPlan()['status']);
    },
    'trigger recursive returning deferred fk current source next111 blocked keeps next rows' => static function (TestRunner $t) use ($blockedPlan): void {
        $t->same([101, 103, 105], $blockedPlan()['next_rowids']);
    },
    'trigger recursive returning deferred fk current source next111 blocked returning remains visible' => static function (TestRunner $t) use ($blockedPlan): void {
        $t->same([['old_id' => 1, 'new_id' => 101, 'option_name' => 'siteurl', 'revision' => 2, 'expr4' => 'update:1>101']], $blockedPlan()['next_returning_rows']);
    },
    'trigger recursive returning deferred fk current source next111 blocked keeps changes' => static function (TestRunner $t) use ($blockedPlan): void {
        $t->same(3, $blockedPlan()['next_changes']);
    },
    'trigger recursive returning deferred fk current source next111 commit status without children' => static function (TestRunner $t) use ($commitPlan): void {
        $t->same('commit-ok', $commitPlan()['status']);
    },
    'trigger recursive returning deferred fk current source next111 commit keeps returning' => static function (TestRunner $t) use ($commitPlan): void {
        $t->same(1, count($commitPlan()['next_returning_rows']));
    },
    'trigger recursive returning deferred fk current source next111 commit has no violations' => static function (TestRunner $t) use ($commitPlan): void {
        $t->same([], $commitPlan()['foreign_key_violations']);
    },
    'trigger recursive returning deferred fk current source next111 recursive off status' => static function (TestRunner $t) use ($recursiveOffPlan): void {
        $t->same('rolled-back', $recursiveOffPlan()['status']);
    },
    'trigger recursive returning deferred fk current source next111 recursive off only top row changes' => static function (TestRunner $t) use ($recursiveOffPlan): void {
        $t->same([101, 2, 3], $recursiveOffPlan()['current_rowids']);
    },
    'trigger recursive returning deferred fk current source next111 recursive off no effects' => static function (TestRunner $t) use ($recursiveOffPlan): void {
        $t->same([], $recursiveOffPlan()['trigger_effects']);
    },
    'trigger recursive returning deferred fk current source next111 recursive off change count' => static function (TestRunner $t) use ($recursiveOffPlan): void {
        $t->same(1, $recursiveOffPlan()['current_changes']);
    },
    'trigger recursive returning deferred fk current source next111 bad rowid column throws' => static function (TestRunner $t) use ($parents, $children, $fk, $baseStatement): void {
        $t->throws(InvalidArgumentException::class, static fn () => SQLiteTriggerRecursiveReturningDeferredFkCurrentSourceNextPlan::run($parents, $children, $fk, $baseStatement + ['rowid_column' => 'bad-column']));
    },
    'trigger recursive returning deferred fk current source next111 missing where throws' => static function (TestRunner $t) use ($parents, $children, $fk, $baseStatement): void {
        $statement = $baseStatement;
        unset($statement['where']);
        $t->throws(InvalidArgumentException::class, static fn () => SQLiteTriggerRecursiveReturningDeferredFkCurrentSourceNextPlan::run($parents, $children, $fk, $statement));
    },
    'trigger recursive returning deferred fk current source next111 empty assignments throws' => static function (TestRunner $t) use ($parents, $children, $fk, $baseStatement): void {
        $t->throws(InvalidArgumentException::class, static fn () => SQLiteTriggerRecursiveReturningDeferredFkCurrentSourceNextPlan::run($parents, $children, $fk, array_replace($baseStatement, ['assignments' => []])));
    },
    'trigger recursive returning deferred fk current source next111 max depth throws' => static function (TestRunner $t) use ($parents, $children, $fk, $baseStatement): void {
        $t->throws(InvalidArgumentException::class, static fn () => SQLiteTriggerRecursiveReturningDeferredFkCurrentSourceNextPlan::run($parents, $children, $fk, $baseStatement + ['max_depth' => 1]));
    },
    'trigger recursive returning deferred fk current source next111 bad page image throws' => static function (TestRunner $t) use ($parents, $children, $fk, $baseStatement): void {
        $t->throws(InvalidArgumentException::class, static fn () => SQLiteTriggerRecursiveReturningDeferredFkCurrentSourceNextPlan::run($parents, $children, $fk, array_replace($baseStatement, ['page_images' => [0 => 'bad']])));
    },
    'trigger recursive returning deferred fk current source next111 bad wal frame throws' => static function (TestRunner $t) use ($parents, $children, $fk, $baseStatement): void {
        $t->throws(InvalidArgumentException::class, static fn () => SQLiteTriggerRecursiveReturningDeferredFkCurrentSourceNextPlan::run($parents, $children, $fk, array_replace($baseStatement, ['wal_frames' => [['frame_index' => 0]]])));
    },
];

return $tests;
