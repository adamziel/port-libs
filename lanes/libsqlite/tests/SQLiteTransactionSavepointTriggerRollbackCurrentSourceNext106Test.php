<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteTransactionSavepointTriggerRollbackCurrentSourceNextPlan;

$rows = [
    ['option_id' => 1, 'option_name' => '_transient_feed', 'option_value' => 'cached', 'autoload' => 'no', 'revision' => 1],
    ['option_id' => 2, 'option_name' => '_transient_timeout_feed', 'option_value' => '1700000000', 'autoload' => 'no', 'revision' => 1],
    ['option_id' => 3, 'option_name' => 'siteurl', 'option_value' => 'https://example.test', 'autoload' => 'yes', 'revision' => 4],
    ['option_id' => 4, 'option_name' => 'home', 'option_value' => 'https://example.test', 'autoload' => 'yes', 'revision' => 5],
    ['option_id' => 5, 'option_name' => 'active_plugins', 'option_value' => 'a:1:{i:0;s:10:"bad/plugin";}', 'autoload' => 'yes', 'revision' => 9],
];

$deleteTriggers = [
    [
        'name' => 'wp_options_bd_skip_siteurl',
        'timing' => 'before',
        'event' => 'delete',
        'action' => 'raise',
        'raise' => 'ignore',
        'when' => ['old.option_name', '=', 'siteurl'],
        'reason' => 'siteurl cannot be purged by transient cleanup',
    ],
    [
        'name' => 'wp_options_ad_audit_deleted',
        'timing' => 'after',
        'event' => 'delete',
        'action' => 'audit',
        'values' => ['name' => 'old.option_name', 'revision' => 'old.revision'],
    ],
];

$deleteRollbackTriggers = $deleteTriggers;
$deleteRollbackTriggers[] = [
    'name' => 'wp_options_ad_home_rollback',
    'timing' => 'after',
    'event' => 'delete',
    'action' => 'raise',
    'raise' => 'rollback',
    'when' => ['old.option_name', '=', 'home'],
    'reason' => 'home deletion aborts transaction',
];

$deleteWhere = static fn (array $row): bool => str_starts_with((string) $row['option_name'], '_transient') || in_array($row['option_name'], ['siteurl', 'home'], true);
$deleteCommit = static fn (): array => SQLiteTransactionSavepointTriggerRollbackCurrentSourceNextPlan::deleteRows('wp_current_import', $rows, $deleteWhere, $deleteTriggers);
$deleteRollback = static fn (): array => SQLiteTransactionSavepointTriggerRollbackCurrentSourceNextPlan::deleteRows('wp_current_import', $rows, $deleteWhere, $deleteRollbackTriggers);

$updateTriggers = [
    [
        'name' => 'wp_options_bu_mark_imported_autoload',
        'timing' => 'before',
        'event' => 'update',
        'action' => 'set-new',
        'when' => ['old.autoload', '=', 'yes'],
        'set' => ['autoload' => 'imported'],
        'values' => ['name' => 'old.option_name', 'autoload' => 'new.autoload'],
    ],
    [
        'name' => 'wp_options_au_active_plugins_rollback',
        'timing' => 'after',
        'event' => 'update',
        'action' => 'raise',
        'raise' => 'rollback',
        'when' => ['new.option_name', '=', 'active_plugins'],
        'reason' => 'plugin activation rewrite aborts transaction',
    ],
];

$assignments = [
    'option_value' => static fn (array $row): string => 'imported:' . $row['option_name'],
    'revision' => static fn (array $row): int => (int) $row['revision'] + 1,
];
$updateRollback = static fn (): array => SQLiteTransactionSavepointTriggerRollbackCurrentSourceNextPlan::updateRows(
    'wp_current_import',
    $rows,
    $assignments,
    static fn (array $row): bool => $row['autoload'] === 'yes',
    $updateTriggers,
);

$cases = [
    'delete commit status' => [static fn (): mixed => $deleteCommit()['status'], 'commit-ok'],
    'delete commit rows names' => [static fn (): mixed => array_column($deleteCommit()['rows'], 'option_name'), ['siteurl', 'active_plugins']],
    'delete commit current source equals rows' => [static fn (): mixed => $deleteCommit()['current_source_rows'], $deleteCommit()['rows']],
    'delete commit next source equals attempted' => [static fn (): mixed => $deleteCommit()['next_source_rows'], $deleteCommit()['attempted_rows']],
    'delete commit deleted names' => [static fn (): mixed => array_column($deleteCommit()['deleted'], 'option_name'), ['_transient_feed', '_transient_timeout_feed', 'home']],
    'delete commit skipped siteurl' => [static fn (): mixed => $deleteCommit()['skipped'][0]['row']['option_name'], 'siteurl'],
    'delete commit skipped timing' => [static fn (): mixed => $deleteCommit()['skipped'][0]['timing'], 'before'],
    'delete commit changes count' => [static fn (): mixed => $deleteCommit()['changes'], 3],
    'delete commit attempted changes count' => [static fn (): mixed => $deleteCommit()['attempted_changes'], 3],
    'delete commit not rolled back' => [static fn (): mixed => $deleteCommit()['rolled_back'], false],
    'delete commit rollback scope none' => [static fn (): mixed => $deleteCommit()['rollback_scope'], 'none'],
    'delete commit savepoint not preserved' => [static fn (): mixed => $deleteCommit()['savepoint_preserved'], false],
    'delete commit trigger effect names' => [static fn (): mixed => array_column($deleteCommit()['trigger_effects'], 'trigger'), ['wp_options_ad_audit_deleted', 'wp_options_ad_audit_deleted', 'wp_options_ad_audit_deleted']],
    'delete commit trigger projection names' => [static fn (): mixed => array_column(array_column($deleteCommit()['trigger_effects'], 'row'), 'name'), ['_transient_feed', '_transient_timeout_feed', 'home']],
    'delete commit source trace ordinals' => [static fn (): mixed => array_column($deleteCommit()['source_trace'], 'ordinal'), [0, 1, 3]],
    'delete commit source trace current counts' => [static fn (): mixed => array_column($deleteCommit()['source_trace'], 'current_source_count'), [5, 4, 3]],
    'delete commit source trace next counts' => [static fn (): mixed => array_column($deleteCommit()['source_trace'], 'next_source_count'), [4, 3, 2]],
    'delete commit first current source names' => [static fn (): mixed => $deleteCommit()['source_trace'][0]['current_source_names'], ['_transient_feed', '_transient_timeout_feed', 'siteurl', 'home', 'active_plugins']],
    'delete commit final next source names' => [static fn (): mixed => $deleteCommit()['source_trace'][2]['next_source_names'], ['siteurl', 'active_plugins']],
    'delete commit discarded empty' => [static fn (): mixed => $deleteCommit()['discarded'], []],

    'delete rollback status' => [static fn (): mixed => $deleteRollback()['status'], 'rolled-back'],
    'delete rollback restores rows' => [static fn (): mixed => $deleteRollback()['rows'], $rows],
    'delete rollback current source restores savepoint' => [static fn (): mixed => $deleteRollback()['current_source_rows'], $rows],
    'delete rollback next source keeps attempted image' => [static fn (): mixed => array_column($deleteRollback()['next_source_rows'], 'option_name'), ['siteurl', 'active_plugins']],
    'delete rollback deleted suppressed' => [static fn (): mixed => $deleteRollback()['deleted'], []],
    'delete rollback changes reset' => [static fn (): mixed => $deleteRollback()['changes'], 0],
    'delete rollback attempted changes retained' => [static fn (): mixed => $deleteRollback()['attempted_changes'], 3],
    'delete rollback true' => [static fn (): mixed => $deleteRollback()['rolled_back'], true],
    'delete rollback reason' => [static fn (): mixed => $deleteRollback()['rollback_reason'], 'home deletion aborts transaction'],
    'delete rollback ordinal' => [static fn (): mixed => $deleteRollback()['rollback_at_ordinal'], 3],
    'delete rollback scope' => [static fn (): mixed => $deleteRollback()['rollback_scope'], 'transaction-savepoint'],
    'delete rollback savepoint preserved' => [static fn (): mixed => $deleteRollback()['savepoint_preserved'], true],
    'delete rollback discarded indexes' => [static fn (): mixed => array_column($deleteRollback()['discarded'], 'row_index'), [0, 1, 3]],
    'delete rollback discarded savepoint names' => [static fn (): mixed => array_column(array_column($deleteRollback()['discarded'], 'savepoint_row'), 'option_name'), ['_transient_feed', '_transient_timeout_feed', 'home']],
    'delete rollback final trigger action' => [static fn (): mixed => $deleteRollback()['trigger_effects'][array_key_last($deleteRollback()['trigger_effects'])]['action'], 'rollback-to-savepoint'],
    'delete rollback final trigger discarded count' => [static fn (): mixed => $deleteRollback()['trigger_effects'][array_key_last($deleteRollback()['trigger_effects'])]['discarded_count'], 3],
    'delete rollback trace retained after rollback' => [static fn (): mixed => array_column($deleteRollback()['source_trace'], 'next_source_count'), [4, 3, 2]],

    'update rollback status' => [static fn (): mixed => $updateRollback()['status'], 'rolled-back'],
    'update rollback restores option values' => [static fn (): mixed => array_column($updateRollback()['rows'], 'option_value'), array_column($rows, 'option_value')],
    'update rollback next source attempted values' => [static fn (): mixed => array_column($updateRollback()['next_source_rows'], 'option_value'), ['cached', '1700000000', 'imported:siteurl', 'imported:home', 'imported:active_plugins']],
    'update rollback attempted revisions' => [static fn (): mixed => array_column($updateRollback()['attempted_rows'], 'revision'), [1, 1, 5, 6, 10]],
    'update rollback changes reset' => [static fn (): mixed => $updateRollback()['changes'], 0],
    'update rollback attempted changes' => [static fn (): mixed => $updateRollback()['attempted_changes'], 3],
    'update rollback reason' => [static fn (): mixed => $updateRollback()['rollback_reason'], 'plugin activation rewrite aborts transaction'],
    'update rollback ordinal' => [static fn (): mixed => $updateRollback()['rollback_at_ordinal'], 2],
    'update rollback source trace names stable' => [static fn (): mixed => $updateRollback()['source_trace'][2]['current_source_names'], ['_transient_feed', '_transient_timeout_feed', 'siteurl', 'home', 'active_plugins']],
    'update rollback source trace next names stable' => [static fn (): mixed => $updateRollback()['source_trace'][2]['next_source_names'], ['_transient_feed', '_transient_timeout_feed', 'siteurl', 'home', 'active_plugins']],
    'update rollback before trigger effects' => [static fn (): mixed => array_column($updateRollback()['trigger_effects'], 'trigger'), ['wp_options_bu_mark_imported_autoload', 'wp_options_bu_mark_imported_autoload', 'wp_options_bu_mark_imported_autoload', null]],
    'update rollback autoload attempted imported' => [static fn (): mixed => array_column($updateRollback()['next_source_rows'], 'autoload'), ['no', 'no', 'imported', 'imported', 'imported']],
    'update rollback discarded indexes' => [static fn (): mixed => array_column($updateRollback()['discarded'], 'row_index'), [2, 3, 4]],
    'update rollback updated suppressed' => [static fn (): mixed => $updateRollback()['updated'], []],
    'dependencies include trigger rollback marker' => [static fn (): mixed => in_array('sqlite-trigger-raise-rollback-current-source-next106', $deleteRollback()['dependencies'], true), true],
    'dependencies include savepoint marker' => [static fn (): mixed => in_array('sqlite-savepoint-transaction-current-source-rollback', $deleteRollback()['dependencies'], true), true],
    'dependencies include application marker' => [static fn (): mixed => in_array('sqlite-application-trigger-rollback-import', $deleteRollback()['dependencies'], true), true],

    'bad savepoint throws' => [static fn (): mixed => SQLiteTransactionSavepointTriggerRollbackCurrentSourceNextPlan::deleteRows('bad-name', $rows, static fn (): bool => true), InvalidArgumentException::class],
    'bad raise action throws' => [static fn (): mixed => SQLiteTransactionSavepointTriggerRollbackCurrentSourceNextPlan::deleteRows('ok_name', $rows, static fn (): bool => true, [['timing' => 'before', 'event' => 'delete', 'action' => 'raise', 'raise' => 'abort']]), InvalidArgumentException::class],
    'delete set new throws' => [static fn (): mixed => SQLiteTransactionSavepointTriggerRollbackCurrentSourceNextPlan::deleteRows('ok_name', $rows, static fn (): bool => true, [['timing' => 'before', 'event' => 'delete', 'action' => 'set-new', 'set' => ['x' => 1]]]), InvalidArgumentException::class],
    'missing update assignments throws' => [static fn (): mixed => SQLiteTransactionSavepointTriggerRollbackCurrentSourceNextPlan::updateRows('ok_name', $rows, [], static fn (): bool => true), InvalidArgumentException::class],
    'bad when operator throws' => [static fn (): mixed => SQLiteTransactionSavepointTriggerRollbackCurrentSourceNextPlan::deleteRows('ok_name', $rows, static fn (): bool => true, [['timing' => 'before', 'event' => 'delete', 'action' => 'audit', 'when' => ['old.option_name', 'like', 'x']]]), InvalidArgumentException::class],
];

$tests = [];
foreach ($cases as $name => [$callback, $expected]) {
    $tests['transaction savepoint trigger rollback current source next106 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        if (is_string($expected) && is_a($expected, Throwable::class, true)) {
            $t->throws($expected, $callback);
            return;
        }
        $t->same($expected, $callback());
    };
}

return $tests;
