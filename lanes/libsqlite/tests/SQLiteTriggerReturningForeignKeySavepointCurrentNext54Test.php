<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteTriggerReturningForeignKeySavepointPlan;

$parents = [
    ['option_id' => 1, 'option_name' => 'siteurl', 'option_value' => 'https://old.test', 'autoload' => 'yes', 'revision' => 1],
    ['option_id' => 2, 'option_name' => 'home', 'option_value' => 'https://home.test', 'autoload' => 'yes', 'revision' => 1],
    ['option_id' => 3, 'option_name' => 'plugin_cache', 'option_value' => 'stale', 'autoload' => 'no', 'revision' => 4],
];
$children = [
    ['meta_id' => 10, 'option_id' => 1, 'meta_key' => 'source'],
    ['meta_id' => 11, 'option_id' => 2, 'meta_key' => 'source'],
    ['meta_id' => 12, 'option_id' => 3, 'meta_key' => 'source'],
];
$triggers = [
    [
        'name' => 'wp_options_bu_touch',
        'timing' => 'before',
        'event' => 'update',
        'action' => 'set-new',
        'when' => ['new.autoload', '=', 'yes'],
        'set' => ['option_value' => 'triggered'],
        'values' => ['old_key' => 'old.option_id', 'new_key' => 'new.option_id'],
    ],
    [
        'name' => 'wp_options_au_audit',
        'timing' => 'after',
        'event' => 'update',
        'action' => 'insert-child',
        'row' => ['meta_id' => 'new.option_id', 'option_id' => 'new.parent_key', 'meta_key' => 'returning_seen'],
        'values' => ['key' => 'new.option_id', 'value' => 'new.option_value'],
    ],
    [
        'name' => 'wp_options_ad_audit',
        'timing' => 'after',
        'event' => 'delete',
        'action' => 'insert-child',
        'row' => ['meta_id' => 900, 'option_id' => null, 'meta_key' => 'deleted'],
        'values' => ['old_key' => 'old.option_id', 'name' => 'old.option_name'],
    ],
];
$cascadeFk = ['parent_key' => 'option_id', 'child_key' => 'option_id', 'on_update' => 'cascade', 'on_delete' => 'cascade', 'deferred' => false];
$deferredFk = ['parent_key' => 'option_id', 'child_key' => 'option_id', 'on_update' => 'no action', 'on_delete' => 'no action', 'deferred' => true];
$page = static fn (string $label): string => str_pad($label, 256, '.', STR_PAD_RIGHT);
$storage = [
    'page_images' => [2 => $page('before-options'), 3 => $page('before-meta')],
    'dirty_pages' => [2 => $page('dirty-options'), 3 => $page('dirty-meta'), 4 => $page('dirty-overflow')],
    'wal_start_frame' => 6,
    'wal_frames' => [
        ['frame_index' => 7, 'page_number' => 2],
        ['frame_index' => 8, 'page_number' => 3, 'commit_frame' => true],
    ],
];

$cascadeUpdate = static fn (): array => SQLiteTriggerReturningForeignKeySavepointPlan::run($parents, $children, $cascadeFk, $triggers, $storage + [
    'operation' => 'update',
    'savepoint' => 'wp_import',
    'where' => static fn (array $row): bool => $row['autoload'] === 'yes',
    'assignments' => [
        'option_id' => static fn (array $row): int => (int) $row['option_id'] + 100,
        'revision' => static fn (array $row): int => (int) $row['revision'] + 1,
    ],
    'returning' => ['option_id', 'option_name', 'option_value', ['expr' => 'old.option_id', 'as' => 'old_option_id']],
    'rowid_column' => 'option_id',
]);
$blockedUpdate = static fn (): array => SQLiteTriggerReturningForeignKeySavepointPlan::run($parents, $children, $deferredFk, [], $storage + [
    'operation' => 'update',
    'savepoint' => 'wp_import',
    'where' => static fn (array $row): bool => $row['option_id'] === 3,
    'assignments' => ['option_id' => 303],
    'returning' => ['option_id', ['expr' => 'old.option_id', 'as' => 'old_option_id']],
    'rowid_column' => 'option_id',
]);
$rollbackUpdate = static fn (): array => SQLiteTriggerReturningForeignKeySavepointPlan::run($parents, $children, $deferredFk, [], $storage + [
    'operation' => 'update',
    'savepoint' => 'wp_import',
    'where' => static fn (array $row): bool => $row['option_id'] === 3,
    'assignments' => ['option_id' => 303],
    'returning' => ['option_id', ['expr' => 'old.option_id', 'as' => 'old_option_id']],
    'rowid_column' => 'option_id',
    'rollback_on_deferred_violation' => true,
]);
$deleteCascade = static fn (): array => SQLiteTriggerReturningForeignKeySavepointPlan::run($parents, $children, $cascadeFk, $triggers, [
    'operation' => 'delete',
    'savepoint' => 'delete_pass',
    'where' => static fn (array $row): bool => $row['autoload'] === 'no',
    'returning' => ['option_id', 'option_name'],
    'rowid_column' => 'option_id',
]);

$cases = [
    'cascade update status commits' => [static fn (): mixed => $cascadeUpdate()['status'], 'commit-ok'],
    'cascade update savepoint name' => [static fn (): mixed => $cascadeUpdate()['savepoint'], 'wp_import'],
    'cascade update operation' => [static fn (): mixed => $cascadeUpdate()['operation'], 'update'],
    'cascade update changes two rows' => [static fn (): mixed => $cascadeUpdate()['changes'], 2],
    'cascade update attempted changes match changes' => [static fn (): mixed => $cascadeUpdate()['attempted_changes'], 2],
    'cascade update parent keys commit' => [static fn (): mixed => array_column($cascadeUpdate()['parent'], 'option_id'), [101, 102, 3]],
    'cascade update child keys follow cascade and audit rows' => [static fn (): mixed => array_column($cascadeUpdate()['child'], 'option_id'), [101, 102, 3, 101, 102]],
    'cascade update attempted parent equals committed' => [static fn (): mixed => $cascadeUpdate()['attempted_parent'], $cascadeUpdate()['parent']],
    'cascade update yielded count' => [static fn (): mixed => count($cascadeUpdate()['yielded']), 2],
    'cascade update attempted yielded equals yielded' => [static fn (): mixed => $cascadeUpdate()['attempted_yielded'], $cascadeUpdate()['yielded']],
    'cascade update returning first new key' => [static fn (): mixed => $cascadeUpdate()['yielded'][0]['returning']['option_id'], 101],
    'cascade update returning first old key' => [static fn (): mixed => $cascadeUpdate()['yielded'][0]['returning']['old_option_id'], 1],
    'cascade update returning trigger image' => [static fn (): mixed => $cascadeUpdate()['yielded'][0]['returning']['option_value'], 'triggered'],
    'cascade update trigger order' => [static fn (): mixed => array_column($cascadeUpdate()['trigger_effects'], 'trigger'), ['wp_options_bu_touch', 'wp_options_au_audit', 'wp_options_bu_touch', 'wp_options_au_audit']],
    'cascade update fk action names' => [static fn (): mixed => array_column($cascadeUpdate()['foreign_key_actions'], 'action'), ['cascade', 'cascade']],
    'cascade update fk action targets' => [static fn (): mixed => array_column($cascadeUpdate()['foreign_key_actions'], 'to'), [101, 102]],
    'cascade update no violations' => [static fn (): mixed => $cascadeUpdate()['foreign_key_violations'], []],
    'cascade update no rollback' => [static fn (): mixed => $cascadeUpdate()['rolled_back_to_savepoint'], false],
    'cascade update no rollback pages' => [static fn (): mixed => $cascadeUpdate()['rollback_page_numbers'], []],
    'cascade update no discarded wal frames' => [static fn (): mixed => $cascadeUpdate()['discarded_wal_frames'], []],

    'blocked update status commit blocked' => [static fn (): mixed => $blockedUpdate()['status'], 'commit-blocked'],
    'blocked update keeps attempted parent image' => [static fn (): mixed => array_column($blockedUpdate()['attempted_parent'], 'option_id'), [1, 2, 303]],
    'blocked update committed preview is attempted image' => [static fn (): mixed => array_column($blockedUpdate()['parent'], 'option_id'), [1, 2, 303]],
    'blocked update child remains orphaned' => [static fn (): mixed => $blockedUpdate()['child'][2]['option_id'], 3],
    'blocked update yields attempted returning' => [static fn (): mixed => $blockedUpdate()['yielded'][0]['returning'], ['option_id' => 303, 'old_option_id' => 3]],
    'blocked update violation count' => [static fn (): mixed => count($blockedUpdate()['foreign_key_violations']), 2],
    'blocked update violation phases' => [static fn (): mixed => array_column($blockedUpdate()['foreign_key_violations'], 'phase'), ['statement', 'after-trigger']],
    'blocked update changes remain one' => [static fn (): mixed => $blockedUpdate()['changes'], 1],
    'blocked update does not rollback' => [static fn (): mixed => $blockedUpdate()['rolled_back_to_savepoint'], false],
    'blocked update rollback reason empty' => [static fn (): mixed => $blockedUpdate()['rollback_reason'], null],

    'rollback update status rolled back' => [static fn (): mixed => $rollbackUpdate()['status'], 'rolled-back'],
    'rollback update restores parent keys' => [static fn (): mixed => array_column($rollbackUpdate()['parent'], 'option_id'), [1, 2, 3]],
    'rollback update restores children' => [static fn (): mixed => $rollbackUpdate()['child'], $children],
    'rollback update preserves attempted parent' => [static fn (): mixed => array_column($rollbackUpdate()['attempted_parent'], 'option_id'), [1, 2, 303]],
    'rollback update clears committed yielding' => [static fn (): mixed => $rollbackUpdate()['yielded'], []],
    'rollback update preserves attempted returning' => [static fn (): mixed => $rollbackUpdate()['attempted_yielded'][0]['returning'], ['option_id' => 303, 'old_option_id' => 3]],
    'rollback update clears changes' => [static fn (): mixed => $rollbackUpdate()['changes'], 0],
    'rollback update attempted changes one' => [static fn (): mixed => $rollbackUpdate()['attempted_changes'], 1],
    'rollback update marks rollback' => [static fn (): mixed => $rollbackUpdate()['rolled_back_to_savepoint'], true],
    'rollback update reason' => [static fn (): mixed => $rollbackUpdate()['rollback_reason'], 'deferred-foreign-key-violation'],
    'rollback update page numbers merge images and dirty pages' => [static fn (): mixed => $rollbackUpdate()['rollback_page_numbers'], [2, 3, 4]],
    'rollback update restored page numbers' => [static fn (): mixed => array_keys($rollbackUpdate()['restored_page_images']), [2, 3]],
    'rollback update dirty page numbers' => [static fn (): mixed => $rollbackUpdate()['dirty_page_numbers'], [2, 3, 4]],
    'rollback update wal frame' => [static fn (): mixed => $rollbackUpdate()['rollback_to_wal_frame'], 6],
    'rollback update discarded wal frames' => [static fn (): mixed => array_column($rollbackUpdate()['discarded_wal_frames'], 'frame_index'), [7, 8]],
    'rollback update keeps commit frame marker' => [static fn (): mixed => $rollbackUpdate()['discarded_wal_frames'][1]['commit_frame'], true],
    'rollback update keeps violation evidence' => [static fn (): mixed => count($rollbackUpdate()['foreign_key_violations']), 2],
    'rollback update dependencies include trigger returning' => [static fn (): mixed => in_array('sqlite-trigger-returning-current-row', $rollbackUpdate()['dependencies'], true), true],
    'rollback update dependencies include deferred fk' => [static fn (): mixed => in_array('sqlite-foreign-key-deferred-check', $rollbackUpdate()['dependencies'], true), true],
    'rollback update dependencies include savepoint' => [static fn (): mixed => in_array('sqlite-savepoint-current-rollback', $rollbackUpdate()['dependencies'], true), true],

    'delete cascade status ok' => [static fn (): mixed => $deleteCascade()['status'], 'commit-ok'],
    'delete cascade changes one row' => [static fn (): mixed => $deleteCascade()['changes'], 1],
    'delete cascade remaining parent names' => [static fn (): mixed => array_column($deleteCascade()['parent'], 'option_name'), ['siteurl', 'home']],
    'delete cascade child audit row is appended' => [static fn (): mixed => array_column($deleteCascade()['child'], 'meta_key'), ['source', 'source', 'deleted']],
    'delete cascade action deletes child' => [static fn (): mixed => $deleteCascade()['foreign_key_actions'][0]['action'], 'cascade-delete'],
    'delete cascade returning old row' => [static fn (): mixed => $deleteCascade()['yielded'][0]['returning'], ['option_id' => 3, 'option_name' => 'plugin_cache']],

    'bad operation throws' => [static fn (): mixed => SQLiteTriggerReturningForeignKeySavepointPlan::run($parents, $children, $cascadeFk, [], ['operation' => 'insert', 'where' => static fn (): bool => true]), InvalidArgumentException::class],
    'bad savepoint throws' => [static fn (): mixed => SQLiteTriggerReturningForeignKeySavepointPlan::run($parents, $children, $cascadeFk, [], ['operation' => 'delete', 'savepoint' => 'bad-name', 'where' => static fn (): bool => true]), InvalidArgumentException::class],
    'missing where throws' => [static fn (): mixed => SQLiteTriggerReturningForeignKeySavepointPlan::run($parents, $children, $cascadeFk, [], ['operation' => 'delete']), InvalidArgumentException::class],
    'bad page image key throws' => [static fn (): mixed => SQLiteTriggerReturningForeignKeySavepointPlan::run($parents, $children, $deferredFk, [], ['operation' => 'update', 'where' => static fn (): bool => true, 'assignments' => ['option_id' => 9], 'rollback_on_deferred_violation' => true, 'page_images' => ['x' => 'bad']]), InvalidArgumentException::class],
    'bad dirty page bytes throws' => [static fn (): mixed => SQLiteTriggerReturningForeignKeySavepointPlan::run($parents, $children, $deferredFk, [], ['operation' => 'update', 'where' => static fn (): bool => true, 'assignments' => ['option_id' => 9], 'rollback_on_deferred_violation' => true, 'dirty_pages' => [2 => '']]), InvalidArgumentException::class],
    'bad wal start throws' => [static fn (): mixed => SQLiteTriggerReturningForeignKeySavepointPlan::run($parents, $children, $deferredFk, [], ['operation' => 'update', 'where' => static fn (): bool => true, 'assignments' => ['option_id' => 9], 'wal_start_frame' => -1]), InvalidArgumentException::class],
    'bad wal frame throws' => [static fn (): mixed => SQLiteTriggerReturningForeignKeySavepointPlan::run($parents, $children, $deferredFk, [], ['operation' => 'update', 'where' => static fn (): bool => true, 'assignments' => ['option_id' => 9], 'rollback_on_deferred_violation' => true, 'wal_frames' => [['frame_index' => 0]]]), InvalidArgumentException::class],
];

$tests = [];
foreach ($cases as $name => [$callback, $expected]) {
    $tests['trigger returning foreignkey savepoint current next54 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        if (is_string($expected) && is_a($expected, Throwable::class, true)) {
            $t->throws($expected, $callback);
            return;
        }
        $t->same($expected, $callback());
    };
}

return $tests;
