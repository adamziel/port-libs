<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNextPlan;
use PortLibs\LibSqlite\SQLiteUpdateDeleteReturningSql;

$rows204 = [
    ['option_id' => 1, 'blog_id' => 1, 'option_name' => 'siteurl', 'autoload' => 'yes', 'status' => 'live', 'bytes' => 20, 'option_value' => 'https://one.test'],
    ['option_id' => 2, 'blog_id' => 1, 'option_name' => 'home', 'autoload' => 'yes', 'status' => 'live', 'bytes' => 21, 'option_value' => 'https://home.test'],
    ['option_id' => 3, 'blog_id' => 1, 'option_name' => '_transient_feed', 'autoload' => 'no', 'status' => 'stale', 'bytes' => 12, 'option_value' => 'feed'],
    ['option_id' => 4, 'blog_id' => 1, 'option_name' => '_transient_timeout_feed', 'autoload' => 'no-cache', 'status' => 'stale', 'bytes' => 13, 'option_value' => 'timeout'],
    ['option_id' => 5, 'blog_id' => 2, 'option_name' => 'siteurl', 'autoload' => 'yes', 'status' => 'live', 'bytes' => 25, 'option_value' => 'https://two.test'],
    ['option_id' => 6, 'blog_id' => 2, 'option_name' => 'home', 'autoload' => 'manual', 'status' => 'live', 'bytes' => 26, 'option_value' => 'https://two-home.test'],
    ['option_id' => 7, 'blog_id' => 2, 'option_name' => 'pending_theme', 'autoload' => 'no', 'status' => null, 'bytes' => 7, 'option_value' => 'theme'],
    ['option_id' => 8, 'blog_id' => 3, 'option_name' => 'rewrite_rules', 'autoload' => 'rewrite', 'status' => 'queued', 'bytes' => 9, 'option_value' => 'rules'],
    ['option_id' => 9, 'blog_id' => 3, 'option_name' => 'plugin_batch', 'autoload' => 'no', 'status' => 'queued', 'bytes' => 11, 'option_value' => 'plugin'],
    ['option_id' => 10, 'blog_id' => 4, 'option_name' => 'siteurl', 'autoload' => 'yes', 'status' => 'live', 'bytes' => 30, 'option_value' => 'https://four.test'],
    ['option_id' => 11, 'blog_id' => 4, 'option_name' => 'home', 'autoload' => 'manual', 'status' => 'live', 'bytes' => 31, 'option_value' => 'https://four-home.test'],
];

$tables204 = ['wp_options' => $rows204];
$unique204 = [['blog_id', 'option_name']];

$outerSql204 = "UPDATE wp_options SET (status, option_value, bytes) = ('outer204', option_value || ':outer204', bytes + 1) WHERE (blog_id, option_name) IN (VALUES (1, 'siteurl'), (1, 'home')) RETURNING option_id, blog_id, option_name, status, option_value, bytes ORDER BY option_id";
$savepointSql204 = "UPDATE wp_options SET (status, option_value, bytes) = ('saved204', option_value || ':saved204', bytes + 2) WHERE (blog_id, option_name) IN ((2, 'pending_theme'), (3, 'rewrite_rules')) RETURNING option_id, blog_id, option_name, status, option_value, bytes, (blog_id, option_name) IS DISTINCT FROM (2, 'pending_theme') AS not_pending ORDER BY option_id";
$rollbackSql204 = "UPDATE OR ROLLBACK wp_options SET (blog_id, option_name, status, option_value, bytes) = (4, 'siteurl', 'rollback204', option_value || ':rollback204', bytes + 10) WHERE option_id IN (8, 9) RETURNING option_id, blog_id, option_name, status, option_value, bytes ORDER BY option_id";
$retryUpdateSql204 = "UPDATE wp_options SET (status, option_value, bytes) = ('retry204', option_value || ':retry204', bytes + 5) WHERE (blog_id, option_name) IN ((3, 'rewrite_rules'), (3, 'plugin_batch')) RETURNING option_id, blog_id, option_name, status, option_value, bytes ORDER BY option_id";
$retryDeleteSql204 = "DELETE FROM wp_options WHERE (blog_id, option_name) IN (VALUES (1, '_transient_feed'), (1, '_transient_timeout_feed'), (4, 'home')) RETURNING option_id, blog_id, option_name, status, (blog_id, option_name) IN (VALUES (4, 'home')) AS deleted_network_home ORDER BY option_id";

$outerResult204 = static fn (): array => SQLiteUpdateDeleteReturningSql::execute($outerSql204, $tables204, 'option_id', $unique204);
$savepointResult204 = static fn (): array => SQLiteUpdateDeleteReturningSql::execute($savepointSql204, $outerResult204()['tables'], 'option_id', $unique204);
$retryUpdateResult204 = static fn (): array => SQLiteUpdateDeleteReturningSql::execute($retryUpdateSql204, $tables204, 'option_id', $unique204);
$retryDeleteResult204 = static fn (): array => SQLiteUpdateDeleteReturningSql::execute($retryDeleteSql204, $retryUpdateResult204()['tables'], 'option_id', $unique204);
$plan204 = static fn (): array => SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNextPlan::executeRollbackConflictRetry(
    $tables204,
    [$outerSql204],
    [$savepointSql204],
    [$rollbackSql204],
    [$retryUpdateSql204, $retryDeleteSql204],
    $unique204,
);
$customPlan204 = static fn (): array => SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNextPlan::executeRollbackConflictRetry(
    $tables204,
    [$outerSql204],
    [$savepointSql204],
    [$rollbackSql204],
    [$retryUpdateSql204],
    $unique204,
    'wp_custom_txn204',
    'wp_custom_savepoint204',
);

$cases204 = [
    'parser rollback conflict action' => [static fn (): mixed => SQLiteUpdateDeleteReturningSql::parse($rollbackSql204)['conflict_action'], 'rollback'],
    'parser rollback row value assignment columns' => [static fn (): mixed => array_keys(SQLiteUpdateDeleteReturningSql::parse($rollbackSql204)['assignments']), ['blog_id', 'option_name', 'status', 'option_value', 'bytes']],
    'parser savepoint row value where' => [static fn (): mixed => SQLiteUpdateDeleteReturningSql::parse($savepointSql204)['where'], "(blog_id, option_name) IN ((2, 'pending_theme'), (3, 'rewrite_rules'))"],
    'parser retry delete values predicate' => [static fn (): mixed => str_contains(SQLiteUpdateDeleteReturningSql::parse($retryDeleteSql204)['where'] ?? '', 'VALUES'), true],
    'direct rollback statement throws' => [static fn (): mixed => SQLiteUpdateDeleteReturningSql::execute($rollbackSql204, $savepointResult204()['tables'], 'option_id', $unique204), InvalidArgumentException::class],
    'outer selected ids' => [static fn (): mixed => $outerResult204()['plan']->selectedIds, [1, 2]],
    'outer returning ids' => [static fn (): mixed => array_column($outerResult204()['returning'], 'option_id'), [1, 2]],
    'outer row one status' => [static fn (): mixed => array_column($outerResult204()['tables']['wp_options'], 'status', 'option_id')[1], 'outer204'],
    'savepoint selected ids' => [static fn (): mixed => $savepointResult204()['plan']->selectedIds, [7, 8]],
    'savepoint returning ids' => [static fn (): mixed => array_column($savepointResult204()['returning'], 'option_id'), [7, 8]],
    'savepoint returning distinct flags' => [static fn (): mixed => array_column($savepointResult204()['returning'], 'not_pending'), [0, 1]],
    'savepoint row eight value changed before rollback' => [static fn (): mixed => array_column($savepointResult204()['tables']['wp_options'], 'option_value', 'option_id')[8], 'rules:saved204'],
    'retry update direct selected ids from original' => [static fn (): mixed => $retryUpdateResult204()['plan']->selectedIds, [8, 9]],
    'retry update direct row eight original prefix' => [static fn (): mixed => $retryUpdateResult204()['returning'][0]['option_value'], 'rules:retry204'],
    'retry update direct row nine original prefix' => [static fn (): mixed => $retryUpdateResult204()['returning'][1]['option_value'], 'plugin:retry204'],
    'retry delete direct selected ids' => [static fn (): mixed => $retryDeleteResult204()['plan']->selectedIds, [3, 4, 11]],
    'retry delete direct network flag' => [static fn (): mixed => array_column($retryDeleteResult204()['returning'], 'deleted_network_home'), [0, 0, 1]],

    'plan status' => [static fn (): mixed => $plan204()['status'], 'rowvalue-update-delete-returning-rollback-savepoint-current-source-next204'],
    'plan transaction name' => [static fn (): mixed => $plan204()['transaction'], 'wp_options_rowvalue_rollback_txn_next204'],
    'plan savepoint name' => [static fn (): mixed => $plan204()['savepoint'], 'wp_options_rowvalue_rollback_savepoint_next204'],
    'plan transaction rolled back' => [static fn (): mixed => $plan204()['transaction_rolled_back'], true],
    'plan savepoint invalidated' => [static fn (): mixed => $plan204()['savepoint_invalidated_by_rollback'], true],
    'plan retry source flag' => [static fn (): mixed => $plan204()['retry_started_from_transaction_image'], true],
    'plan retry transaction released' => [static fn (): mixed => $plan204()['retry_transaction_released'], true],
    'plan rollback ordinal' => [static fn (): mixed => $plan204()['rollback_statement_ordinal'], 0],
    'plan rollback reason contains OR ROLLBACK' => [static fn (): mixed => str_contains($plan204()['rollback_reason'] ?? '', 'OR ROLLBACK'), true],
    'plan initial tables' => [static fn (): mixed => $plan204()['initial_tables'], $tables204],
    'plan outer current row one changed' => [static fn (): mixed => array_column($plan204()['outer_current_source_tables']['wp_options'], 'status', 'option_id')[1], 'outer204'],
    'plan savepoint image equals outer source' => [static fn (): mixed => $plan204()['savepoint_image_tables'], $plan204()['outer_current_source_tables']],
    'plan savepoint current row seven changed' => [static fn (): mixed => array_column($plan204()['savepoint_current_source_tables']['wp_options'], 'status', 'option_id')[7], 'saved204'],
    'plan savepoint current row eight changed' => [static fn (): mixed => array_column($plan204()['savepoint_current_source_tables']['wp_options'], 'status', 'option_id')[8], 'saved204'],
    'plan rollback attempt sees savepoint rows' => [static fn (): mixed => array_column($plan204()['rollback_statements'][0]['source_rows'], 'option_id'), [8, 9]],
    'plan rollback attempt selected ids' => [static fn (): mixed => $plan204()['rollback_statements'][0]['selected_ids'], [8, 9]],
    'plan rollback attempt suppressed returning' => [static fn (): mixed => $plan204()['rollback_statements'][0]['returning_rows'], []],
    'plan rollback table restored to transaction image' => [static fn (): mixed => $plan204()['rollback_to_transaction_tables'], $tables204],
    'plan rollback restores outer row one' => [static fn (): mixed => array_column($plan204()['rollback_to_transaction_tables']['wp_options'], 'status', 'option_id')[1], 'live'],
    'plan rollback restores savepoint row seven' => [static fn (): mixed => array_column($plan204()['rollback_to_transaction_tables']['wp_options'], 'status', 'option_id')[7], null],
    'plan rollback restores savepoint row eight' => [static fn (): mixed => array_column($plan204()['rollback_to_transaction_tables']['wp_options'], 'option_value', 'option_id')[8], 'rules'],
    'plan retry phases' => [static fn (): mixed => array_column($plan204()['retry_statements'], 'phase'), ['retry-after-transaction-rollback-next204', 'retry-after-transaction-rollback-next204']],
    'plan retry update source ids' => [static fn (): mixed => array_column($plan204()['retry_statements'][0]['source_rows'], 'option_id'), [8, 9]],
    'plan retry update source row eight original' => [static fn (): mixed => $plan204()['retry_statements'][0]['source_rows'][0]['option_value'], 'rules'],
    'plan retry delete source ids' => [static fn (): mixed => array_column($plan204()['retry_statements'][1]['source_rows'], 'option_id'), [3, 4, 11]],
    'plan current row eight retry' => [static fn (): mixed => array_column($plan204()['current_source_tables']['wp_options'], 'status', 'option_id')[8], 'retry204'],
    'plan current row nine retry' => [static fn (): mixed => array_column($plan204()['current_source_tables']['wp_options'], 'status', 'option_id')[9], 'retry204'],
    'plan current row one not outer' => [static fn (): mixed => array_column($plan204()['current_source_tables']['wp_options'], 'status', 'option_id')[1], 'live'],
    'plan current row seven not saved' => [static fn (): mixed => array_column($plan204()['current_source_tables']['wp_options'], 'status', 'option_id')[7], null],
    'plan current removes deleted retry ids' => [static fn (): mixed => array_values(array_intersect([3, 4, 11], array_column($plan204()['current_source_tables']['wp_options'], 'option_id'))), []],
    'plan next source equals current' => [static fn (): mixed => $plan204()['next_source_tables'], $plan204()['current_source_tables']],
    'plan outer returning count' => [static fn (): mixed => $plan204()['outer_yielded_returning_count'], 2],
    'plan savepoint returning count' => [static fn (): mixed => $plan204()['savepoint_yielded_returning_count'], 2],
    'plan suppressed count' => [static fn (): mixed => $plan204()['suppressed_by_transaction_rollback_count'], 4],
    'plan retry returning count' => [static fn (): mixed => $plan204()['yielded_after_retry_count'], 5],
    'plan changes before rollback' => [static fn (): mixed => $plan204()['changes_before_rollback'], 4],
    'plan changes after retry' => [static fn (): mixed => $plan204()['changes_after_rollback_retry'], 5],
    'plan changed tables' => [static fn (): mixed => $plan204()['changed_tables_after_retry'], ['wp_options']],
    'plan row count' => [static fn (): mixed => $plan204()['row_counts']['wp_options'], 8],
    'plan dependency rollback conflict' => [static fn (): mixed => in_array('sqlite-rowvalue-update-or-rollback-conflict-rolls-back-transaction-next204', $plan204()['dependencies'], true), true],
    'plan dependency suppressed returning' => [static fn (): mixed => in_array('sqlite-rowvalue-returning-stream-suppressed-by-transaction-rollback-next204', $plan204()['dependencies'], true), true],
    'plan dependency retry image' => [static fn (): mixed => in_array('sqlite-rowvalue-update-delete-retry-reads-transaction-image-next204', $plan204()['dependencies'], true), true],
    'custom transaction name' => [static fn (): mixed => $customPlan204()['transaction'], 'wp_custom_txn204'],
    'custom savepoint name' => [static fn (): mixed => $customPlan204()['savepoint'], 'wp_custom_savepoint204'],
    'custom retry count' => [static fn (): mixed => $customPlan204()['yielded_after_retry_count'], 2],

    'malformed empty outer rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNextPlan::executeRollbackConflictRetry($tables204, [], [$savepointSql204], [$rollbackSql204], [$retryUpdateSql204], $unique204), InvalidArgumentException::class],
    'malformed empty savepoint rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNextPlan::executeRollbackConflictRetry($tables204, [$outerSql204], [], [$rollbackSql204], [$retryUpdateSql204], $unique204), InvalidArgumentException::class],
    'malformed empty rollback rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNextPlan::executeRollbackConflictRetry($tables204, [$outerSql204], [$savepointSql204], [], [$retryUpdateSql204], $unique204), InvalidArgumentException::class],
    'malformed empty retry rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNextPlan::executeRollbackConflictRetry($tables204, [$outerSql204], [$savepointSql204], [$rollbackSql204], [], $unique204), InvalidArgumentException::class],
    'malformed empty unique rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNextPlan::executeRollbackConflictRetry($tables204, [$outerSql204], [$savepointSql204], [$rollbackSql204], [$retryUpdateSql204], []), InvalidArgumentException::class],
    'malformed transaction rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNextPlan::executeRollbackConflictRetry($tables204, [$outerSql204], [$savepointSql204], [$rollbackSql204], [$retryUpdateSql204], $unique204, 'bad-name'), InvalidArgumentException::class],
    'malformed savepoint rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNextPlan::executeRollbackConflictRetry($tables204, [$outerSql204], [$savepointSql204], [$rollbackSql204], [$retryUpdateSql204], $unique204, 'ok_name', 'bad-name'), InvalidArgumentException::class],
    'malformed non rollback statement rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNextPlan::executeRollbackConflictRetry($tables204, [$outerSql204], [$savepointSql204], [$retryUpdateSql204], [$retryUpdateSql204], $unique204), InvalidArgumentException::class],
    'malformed row list rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNextPlan::executeRollbackConflictRetry(['wp_options' => ['bad']], [$outerSql204], [$savepointSql204], [$rollbackSql204], [$retryUpdateSql204], $unique204), InvalidArgumentException::class],
];

$tests = [];
foreach ($cases204 as $name => [$callback, $expected]) {
    $tests['rowvalue update delete returning savepoint current source next204 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        if (is_string($expected) && is_a($expected, Throwable::class, true)) {
            $t->throws($expected, $callback);
            return;
        }
        $t->same($expected, $callback());
    };
}

return $tests;
