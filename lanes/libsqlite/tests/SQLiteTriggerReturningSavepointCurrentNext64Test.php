<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteTriggerReturningForeignKeySavepointPlan;

$parents = [
    ['option_id' => 1, 'option_name' => 'siteurl', 'option_value' => 'https://example.test', 'autoload' => 'yes', 'revision' => 1],
    ['option_id' => 2, 'option_name' => 'home', 'option_value' => 'https://example.test/home', 'autoload' => 'yes', 'revision' => 1],
    ['option_id' => 3, 'option_name' => '_transient_plugin_batch', 'option_value' => 'stale', 'autoload' => 'no', 'revision' => 7],
];
$children = [
    ['meta_id' => 10, 'option_id' => 1, 'meta_key' => '_origin'],
    ['meta_id' => 11, 'option_id' => 2, 'meta_key' => '_origin'],
    ['meta_id' => 12, 'option_id' => 3, 'meta_key' => '_origin'],
];
$deferredNoAction = [
    'parent_key' => 'option_id',
    'child_key' => 'option_id',
    'on_update' => 'no action',
    'on_delete' => 'no action',
    'deferred' => true,
];
$cascade = [
    'parent_key' => 'option_id',
    'child_key' => 'option_id',
    'on_update' => 'cascade',
    'on_delete' => 'cascade',
    'deferred' => false,
];
$triggers = [
    [
        'name' => 'wp_options_bu_returning_touch',
        'timing' => 'before',
        'event' => 'update',
        'action' => 'set-new',
        'when' => ['new.autoload', '=', 'yes'],
        'set' => ['option_value' => 'triggered-before-returning'],
        'values' => ['old_key' => 'old.option_id', 'new_key' => 'new.option_id'],
    ],
    [
        'name' => 'wp_options_au_returning_audit',
        'timing' => 'after',
        'event' => 'update',
        'action' => 'insert-child',
        'row' => ['meta_id' => 800, 'option_id' => 'new.parent_key', 'meta_key' => '_returning_seen'],
        'values' => ['option_id' => 'new.option_id', 'value' => 'new.option_value'],
    ],
];
$page = static fn (string $label): string => str_pad($label, 512, '.', STR_PAD_RIGHT);
$storage = [
    'page_images' => [2 => $page('savepoint-options-before'), 5 => $page('savepoint-meta-before')],
    'dirty_pages' => [2 => $page('dirty-options-current'), 5 => $page('dirty-meta-current'), 6 => $page('dirty-overflow-current')],
    'wal_start_frame' => 12,
    'wal_frames' => [
        ['frame_index' => 13, 'page_number' => 2],
        ['frame_index' => 14, 'page_number' => 5, 'commit_frame' => true],
        ['frame_index' => 15, 'page_number' => 6],
    ],
];
$returning = [
    ['expr' => 'old.option_id', 'as' => 'old_id'],
    ['expr' => 'new.option_id', 'as' => 'new_id'],
    'option_name',
    'option_value',
    static fn (array $row, array $old, string $event): string => $event . ':' . $old['option_id'] . '>' . $row['option_id'],
];

$rollbackPlan = static fn (): array => SQLiteTriggerReturningForeignKeySavepointPlan::savepointBoundaryYield(
    $parents,
    $children,
    $deferredNoAction,
    $triggers,
    $storage + [
        'operation' => 'update',
        'savepoint' => 'wp_batch',
        'where' => static fn (array $row): bool => $row['autoload'] === 'yes',
        'assignments' => [
            'option_id' => static fn (array $row): int => (int) $row['option_id'] + 100,
            'revision' => static fn (array $row): int => (int) $row['revision'] + 1,
        ],
        'returning' => $returning,
        'rollback_on_deferred_violation' => true,
    ],
);
$blockedPlan = static fn (): array => SQLiteTriggerReturningForeignKeySavepointPlan::savepointBoundaryYield(
    $parents,
    $children,
    $deferredNoAction,
    $triggers,
    $storage + [
        'operation' => 'update',
        'savepoint' => 'wp_batch',
        'where' => static fn (array $row): bool => $row['option_id'] === 3,
        'assignments' => ['option_id' => 303],
        'returning' => [['expr' => 'old.option_id', 'as' => 'old_id'], ['expr' => 'new.option_id', 'as' => 'new_id']],
    ],
);
$commitPlan = static fn (): array => SQLiteTriggerReturningForeignKeySavepointPlan::savepointBoundaryYield(
    $parents,
    $children,
    $cascade,
    $triggers,
    [
        'operation' => 'update',
        'savepoint' => 'wp_batch',
        'where' => static fn (array $row): bool => $row['autoload'] === 'yes',
        'assignments' => ['option_id' => static fn (array $row): int => (int) $row['option_id'] + 20],
        'returning' => $returning,
    ],
);

$tests = [
    'trigger returning savepoint current next64 rollback status' => static function (TestRunner $t) use ($rollbackPlan): void {
        $t->same('rolled-back', $rollbackPlan()['status']);
    },
    'trigger returning savepoint current next64 rollback boundary' => static function (TestRunner $t) use ($rollbackPlan): void {
        $t->same('rollback-to-savepoint', $rollbackPlan()['current_next_boundary']);
    },
    'trigger returning savepoint current next64 rollback savepoint name' => static function (TestRunner $t) use ($rollbackPlan): void {
        $t->same('wp_batch', $rollbackPlan()['savepoint']);
    },
    'trigger returning savepoint current next64 current attempted rowids' => static function (TestRunner $t) use ($rollbackPlan): void {
        $t->same([101, 102, 3], $rollbackPlan()['current_rowids']);
    },
    'trigger returning savepoint current next64 next restored rowids' => static function (TestRunner $t) use ($rollbackPlan): void {
        $t->same([1, 2, 3], $rollbackPlan()['next_rowids']);
    },
    'trigger returning savepoint current next64 restored parent image' => static function (TestRunner $t) use ($rollbackPlan, $parents): void {
        $t->same($parents, $rollbackPlan()['next_parent']);
    },
    'trigger returning savepoint current next64 restored child image' => static function (TestRunner $t) use ($rollbackPlan, $children): void {
        $t->same($children, $rollbackPlan()['next_child']);
    },
    'trigger returning savepoint current next64 current parent keeps trigger value' => static function (TestRunner $t) use ($rollbackPlan): void {
        $t->same('triggered-before-returning', $rollbackPlan()['current_parent'][0]['option_value']);
    },
    'trigger returning savepoint current next64 current child has audit rows' => static function (TestRunner $t) use ($rollbackPlan): void {
        $t->same(['_origin', '_origin', '_origin', '_returning_seen', '_returning_seen'], array_column($rollbackPlan()['current_child'], 'meta_key'));
    },
    'trigger returning savepoint current next64 current yielded count' => static function (TestRunner $t) use ($rollbackPlan): void {
        $t->same(2, count($rollbackPlan()['current_yielded']));
    },
    'trigger returning savepoint current next64 next yielded suppressed' => static function (TestRunner $t) use ($rollbackPlan): void {
        $t->same([], $rollbackPlan()['next_yielded']);
    },
    'trigger returning savepoint current next64 attempted yielding preserved' => static function (TestRunner $t) use ($rollbackPlan): void {
        $t->same($rollbackPlan()['current_yielded'], $rollbackPlan()['attempted_yielded']);
    },
    'trigger returning savepoint current next64 committed yielding empty' => static function (TestRunner $t) use ($rollbackPlan): void {
        $t->same([], $rollbackPlan()['yielded']);
    },
    'trigger returning savepoint current next64 first returning old id' => static function (TestRunner $t) use ($rollbackPlan): void {
        $t->same(1, $rollbackPlan()['current_returning_rows'][0]['old_id']);
    },
    'trigger returning savepoint current next64 first returning new id' => static function (TestRunner $t) use ($rollbackPlan): void {
        $t->same(101, $rollbackPlan()['current_returning_rows'][0]['new_id']);
    },
    'trigger returning savepoint current next64 first returning name' => static function (TestRunner $t) use ($rollbackPlan): void {
        $t->same('siteurl', $rollbackPlan()['current_returning_rows'][0]['option_name']);
    },
    'trigger returning savepoint current next64 first returning trigger value' => static function (TestRunner $t) use ($rollbackPlan): void {
        $t->same('triggered-before-returning', $rollbackPlan()['current_returning_rows'][0]['option_value']);
    },
    'trigger returning savepoint current next64 first returning callable' => static function (TestRunner $t) use ($rollbackPlan): void {
        $t->same('update:1>101', $rollbackPlan()['current_returning_rows'][0]['expr4']);
    },
    'trigger returning savepoint current next64 second returning old id' => static function (TestRunner $t) use ($rollbackPlan): void {
        $t->same(2, $rollbackPlan()['current_returning_rows'][1]['old_id']);
    },
    'trigger returning savepoint current next64 second returning new id' => static function (TestRunner $t) use ($rollbackPlan): void {
        $t->same(102, $rollbackPlan()['current_returning_rows'][1]['new_id']);
    },
    'trigger returning savepoint current next64 next returning rows empty after rollback' => static function (TestRunner $t) use ($rollbackPlan): void {
        $t->same([], $rollbackPlan()['next_returning_rows']);
    },
    'trigger returning savepoint current next64 current changes attempted' => static function (TestRunner $t) use ($rollbackPlan): void {
        $t->same(2, $rollbackPlan()['current_changes']);
    },
    'trigger returning savepoint current next64 next changes cleared' => static function (TestRunner $t) use ($rollbackPlan): void {
        $t->same(0, $rollbackPlan()['next_changes']);
    },
    'trigger returning savepoint current next64 attempted changes retained' => static function (TestRunner $t) use ($rollbackPlan): void {
        $t->same(2, $rollbackPlan()['attempted_changes']);
    },
    'trigger returning savepoint current next64 changes cleared' => static function (TestRunner $t) use ($rollbackPlan): void {
        $t->same(0, $rollbackPlan()['changes']);
    },
    'trigger returning savepoint current next64 suppressed flag' => static function (TestRunner $t) use ($rollbackPlan): void {
        $t->same(true, $rollbackPlan()['yield_suppressed_by_rollback']);
    },
    'trigger returning savepoint current next64 restored savepoint flag' => static function (TestRunner $t) use ($rollbackPlan): void {
        $t->same(true, $rollbackPlan()['next_restored_savepoint_image']);
    },
    'trigger returning savepoint current next64 rollback page numbers' => static function (TestRunner $t) use ($rollbackPlan): void {
        $t->same([2, 5, 6], $rollbackPlan()['rollback_page_numbers']);
    },
    'trigger returning savepoint current next64 restored page image keys' => static function (TestRunner $t) use ($rollbackPlan): void {
        $t->same([2, 5], array_keys($rollbackPlan()['restored_page_images']));
    },
    'trigger returning savepoint current next64 dirty page numbers' => static function (TestRunner $t) use ($rollbackPlan): void {
        $t->same([2, 5, 6], $rollbackPlan()['dirty_page_numbers']);
    },
    'trigger returning savepoint current next64 rollback wal frame' => static function (TestRunner $t) use ($rollbackPlan): void {
        $t->same(12, $rollbackPlan()['rollback_to_wal_frame']);
    },
    'trigger returning savepoint current next64 discarded wal frames' => static function (TestRunner $t) use ($rollbackPlan): void {
        $t->same([13, 14, 15], array_column($rollbackPlan()['discarded_wal_frames'], 'frame_index'));
    },
    'trigger returning savepoint current next64 discarded wal pages' => static function (TestRunner $t) use ($rollbackPlan): void {
        $t->same([2, 5, 6], array_column($rollbackPlan()['discarded_wal_frames'], 'page_number'));
    },
    'trigger returning savepoint current next64 discarded commit frame kept' => static function (TestRunner $t) use ($rollbackPlan): void {
        $t->same(true, $rollbackPlan()['discarded_wal_frames'][1]['commit_frame']);
    },
    'trigger returning savepoint current next64 trigger effects names' => static function (TestRunner $t) use ($rollbackPlan): void {
        $t->same(['wp_options_bu_returning_touch', 'wp_options_au_returning_audit', 'wp_options_bu_returning_touch', 'wp_options_au_returning_audit'], array_column($rollbackPlan()['trigger_effects'], 'trigger'));
    },
    'trigger returning savepoint current next64 before trigger timing' => static function (TestRunner $t) use ($rollbackPlan): void {
        $t->same('before', $rollbackPlan()['trigger_effects'][0]['timing']);
    },
    'trigger returning savepoint current next64 after trigger timing' => static function (TestRunner $t) use ($rollbackPlan): void {
        $t->same('after', $rollbackPlan()['trigger_effects'][1]['timing']);
    },
    'trigger returning savepoint current next64 fk violations counted' => static function (TestRunner $t) use ($rollbackPlan): void {
        $t->same(6, count($rollbackPlan()['foreign_key_violations']));
    },
    'trigger returning savepoint current next64 fk violation phases' => static function (TestRunner $t) use ($rollbackPlan): void {
        $t->same(['statement', 'after-trigger', 'statement', 'statement', 'after-trigger', 'after-trigger'], array_column($rollbackPlan()['foreign_key_violations'], 'phase'));
    },
    'trigger returning savepoint current next64 fk violation child keys' => static function (TestRunner $t) use ($rollbackPlan): void {
        $t->same([1, 1, 1, 2, 1, 2], array_column($rollbackPlan()['foreign_key_violations'], 'child_key'));
    },
    'trigger returning savepoint current next64 no-action fk actions are recorded' => static function (TestRunner $t) use ($rollbackPlan): void {
        $t->same(['no action', 'no action'], array_column($rollbackPlan()['foreign_key_actions'], 'action'));
    },
    'trigger returning savepoint current next64 dependency marker' => static function (TestRunner $t) use ($rollbackPlan): void {
        $t->same(true, in_array('sqlite-trigger-returning-savepoint-current-next64', $rollbackPlan()['dependencies'], true));
    },
    'trigger returning savepoint current next64 savepoint dependency kept' => static function (TestRunner $t) use ($rollbackPlan): void {
        $t->same(true, in_array('sqlite-savepoint-current-rollback', $rollbackPlan()['dependencies'], true));
    },
    'trigger returning savepoint current next64 blocked status' => static function (TestRunner $t) use ($blockedPlan): void {
        $t->same('commit-blocked', $blockedPlan()['status']);
    },
    'trigger returning savepoint current next64 blocked boundary' => static function (TestRunner $t) use ($blockedPlan): void {
        $t->same('deferred-commit-blocked', $blockedPlan()['current_next_boundary']);
    },
    'trigger returning savepoint current next64 blocked current equals next rowids' => static function (TestRunner $t) use ($blockedPlan): void {
        $t->same($blockedPlan()['current_rowids'], $blockedPlan()['next_rowids']);
    },
    'trigger returning savepoint current next64 blocked keeps attempted row' => static function (TestRunner $t) use ($blockedPlan): void {
        $t->same([1, 2, 303], $blockedPlan()['next_rowids']);
    },
    'trigger returning savepoint current next64 blocked yields next returning' => static function (TestRunner $t) use ($blockedPlan): void {
        $t->same([['old_id' => 3, 'new_id' => 303]], $blockedPlan()['next_returning_rows']);
    },
    'trigger returning savepoint current next64 blocked no suppression' => static function (TestRunner $t) use ($blockedPlan): void {
        $t->same(false, $blockedPlan()['yield_suppressed_by_rollback']);
    },
    'trigger returning savepoint current next64 blocked not restored' => static function (TestRunner $t) use ($blockedPlan): void {
        $t->same(false, $blockedPlan()['next_restored_savepoint_image']);
    },
    'trigger returning savepoint current next64 blocked changes remain' => static function (TestRunner $t) use ($blockedPlan): void {
        $t->same(1, $blockedPlan()['next_changes']);
    },
    'trigger returning savepoint current next64 blocked rollback pages empty' => static function (TestRunner $t) use ($blockedPlan): void {
        $t->same([], $blockedPlan()['rollback_page_numbers']);
    },
    'trigger returning savepoint current next64 commit status' => static function (TestRunner $t) use ($commitPlan): void {
        $t->same('commit-ok', $commitPlan()['status']);
    },
    'trigger returning savepoint current next64 commit boundary' => static function (TestRunner $t) use ($commitPlan): void {
        $t->same('commit', $commitPlan()['current_next_boundary']);
    },
    'trigger returning savepoint current next64 commit current equals next returning' => static function (TestRunner $t) use ($commitPlan): void {
        $t->same($commitPlan()['current_returning_rows'], $commitPlan()['next_returning_rows']);
    },
    'trigger returning savepoint current next64 commit rowids' => static function (TestRunner $t) use ($commitPlan): void {
        $t->same([21, 22, 3], $commitPlan()['next_rowids']);
    },
    'trigger returning savepoint current next64 commit cascade actions' => static function (TestRunner $t) use ($commitPlan): void {
        $t->same(['cascade', 'cascade'], array_column($commitPlan()['foreign_key_actions'], 'action'));
    },
    'trigger returning savepoint current next64 commit no violations' => static function (TestRunner $t) use ($commitPlan): void {
        $t->same([], $commitPlan()['foreign_key_violations']);
    },
    'trigger returning savepoint current next64 commit no restored flag' => static function (TestRunner $t) use ($commitPlan): void {
        $t->same(false, $commitPlan()['next_restored_savepoint_image']);
    },
    'trigger returning savepoint current next64 commit no suppression' => static function (TestRunner $t) use ($commitPlan): void {
        $t->same(false, $commitPlan()['yield_suppressed_by_rollback']);
    },
    'trigger returning savepoint current next64 commit changes' => static function (TestRunner $t) use ($commitPlan): void {
        $t->same(2, $commitPlan()['next_changes']);
    },
    'trigger returning savepoint current next64 bad rowid column throws' => static function (TestRunner $t) use ($parents, $children, $cascade): void {
        $t->throws(InvalidArgumentException::class, static fn (): mixed => SQLiteTriggerReturningForeignKeySavepointPlan::savepointBoundaryYield($parents, $children, $cascade, [], [
            'operation' => 'delete',
            'where' => static fn (): bool => true,
            'rowid_column' => 'bad-name',
        ]));
    },
    'trigger returning savepoint current next64 missing where throws' => static function (TestRunner $t) use ($parents, $children, $cascade): void {
        $t->throws(InvalidArgumentException::class, static fn (): mixed => SQLiteTriggerReturningForeignKeySavepointPlan::savepointBoundaryYield($parents, $children, $cascade, [], [
            'operation' => 'delete',
        ]));
    },
    'trigger returning savepoint current next64 malformed wal frame throws' => static function (TestRunner $t) use ($parents, $children, $deferredNoAction): void {
        $t->throws(InvalidArgumentException::class, static fn (): mixed => SQLiteTriggerReturningForeignKeySavepointPlan::savepointBoundaryYield($parents, $children, $deferredNoAction, [], [
            'operation' => 'update',
            'where' => static fn (): bool => true,
            'assignments' => ['option_id' => 9],
            'rollback_on_deferred_violation' => true,
            'wal_frames' => [['frame_index' => 0]],
        ]));
    },
];

return $tests;
