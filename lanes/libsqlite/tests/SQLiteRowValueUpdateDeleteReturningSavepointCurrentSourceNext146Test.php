<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteRowValueUpdateDeleteReturningSavepointPlan;
use PortLibs\LibSqlite\SQLiteUpdateDeleteReturningSql;

$rows = [
    ['option_id' => 1, 'blog_id' => 1, 'option_name' => 'siteurl', 'autoload' => 'yes', 'status' => 'live', 'bytes' => 20, 'option_value' => 'https://old.test'],
    ['option_id' => 2, 'blog_id' => 1, 'option_name' => 'home', 'autoload' => 'yes', 'status' => 'live', 'bytes' => 21, 'option_value' => 'https://home.test'],
    ['option_id' => 3, 'blog_id' => 1, 'option_name' => '_transient_feed', 'autoload' => 'no', 'status' => 'stale', 'bytes' => 12, 'option_value' => 'feed'],
    ['option_id' => 4, 'blog_id' => 1, 'option_name' => '_transient_timeout_feed', 'autoload' => 'no', 'status' => 'stale', 'bytes' => 13, 'option_value' => 'timeout'],
    ['option_id' => 5, 'blog_id' => 2, 'option_name' => 'siteurl', 'autoload' => 'yes', 'status' => 'live', 'bytes' => 25, 'option_value' => 'https://network.test'],
    ['option_id' => 6, 'blog_id' => 2, 'option_name' => 'home', 'autoload' => 'yes', 'status' => 'live', 'bytes' => 26, 'option_value' => 'https://network-home.test'],
    ['option_id' => 7, 'blog_id' => 2, 'option_name' => 'pending_theme', 'autoload' => 'no', 'status' => null, 'bytes' => 7, 'option_value' => 'theme'],
    ['option_id' => 8, 'blog_id' => 3, 'option_name' => 'orphaned_cache', 'autoload' => 'no', 'status' => 'staged', 'bytes' => 5, 'option_value' => 'cache'],
    ['option_id' => 9, 'blog_id' => 3, 'option_name' => 'rewrite_rules', 'autoload' => 'yes', 'status' => 'queued', 'bytes' => 9, 'option_value' => 'rules'],
];

$tables = ['wp_options' => $rows];
$unique = [['blog_id', 'option_name']];

$stageSql = "UPDATE wp_options SET (blog_id, option_name, status, option_value, bytes) = (blog_id, option_name || ':draft', 'draft', option_value || ':draft', bytes + 1) WHERE option_id IN (7, 8) RETURNING option_id, blog_id, option_name, status, option_value, bytes, (status, option_name) = ('draft', 'pending_theme:draft') AS draft_pending ORDER BY option_id";
$cleanupSql = "DELETE FROM wp_options WHERE (blog_id, option_name) IN ((1, '_transient_feed'), (1, '_transient_timeout_feed')) RETURNING option_id, option_name ORDER BY option_id";
$rollbackSql = "UPDATE OR ROLLBACK wp_options SET (blog_id, option_name, status, option_value, bytes) = (1, 'siteurl', option_name || ':rollback', option_value || ':rollback', bytes + 100) WHERE option_id IN (9, 6) RETURNING option_id, blog_id, option_name, status, option_value, bytes ORDER BY option_id DESC";
$releaseSql = "UPDATE wp_options SET (blog_id, option_name, status) = (blog_id, option_name || ':ok', 'ok') WHERE option_id IN (7, 8) RETURNING option_id, blog_id, option_name, status ORDER BY option_id";

$parsedRollback = static fn (): array => SQLiteUpdateDeleteReturningSql::parse($rollbackSql);
$stageOnly = static fn (): array => SQLiteUpdateDeleteReturningSql::execute($stageSql, $tables, 'option_id', $unique);
$rollbackOnly = static fn (): array => SQLiteUpdateDeleteReturningSql::execute($rollbackSql, $tables, 'option_id', $unique);
$rollbackPlan = static fn (): array => SQLiteRowValueUpdateDeleteReturningSavepointPlan::executeRollbackReturningTransaction($tables, [$stageSql, $cleanupSql, $rollbackSql], $unique);
$releasePlan = static fn (): array => SQLiteRowValueUpdateDeleteReturningSavepointPlan::executeRollbackReturningTransaction($tables, [$releaseSql, $cleanupSql], $unique);

$cases = [
    'parser records rollback conflict action' => [static fn (): mixed => $parsedRollback()['conflict_action'], 'rollback'],
    'parser rollback row-value assignment columns' => [static fn (): mixed => array_keys($parsedRollback()['assignments']), ['blog_id', 'option_name', 'status', 'option_value', 'bytes']],
    'parser rollback order desc' => [static fn (): mixed => $parsedRollback()['order_by'][0]['direction'], 'DESC'],
    'parser rollback returning projection' => [static fn (): mixed => $parsedRollback()['returning'], 'option_id, blog_id, option_name, status, option_value, bytes'],
    'parser rollback status expression uses old option name' => [static fn (): mixed => $parsedRollback()['assignments']['status'], "option_name || ':rollback'"],
    'stage only selected ids' => [static fn (): mixed => $stageOnly()['plan']->selectedIds, [7, 8]],
    'stage only returns draft rows' => [static fn (): mixed => array_column($stageOnly()['returning'], 'option_id'), [7, 8]],
    'stage only row seven tuple returning true' => [static fn (): mixed => $stageOnly()['returning'][0]['draft_pending'], 1],
    'stage only row eight value draft' => [static fn (): mixed => $stageOnly()['returning'][1]['option_value'], 'cache:draft'],
    'stage only current source row eight draft' => [static fn (): mixed => array_column($stageOnly()['tables']['wp_options'], 'status', 'option_id')[8], 'draft'],
    'rollback only throws unique conflict' => [$rollbackOnly, InvalidArgumentException::class],

    'rollback plan status transaction rolled back' => [static fn (): mixed => $rollbackPlan()['status'], 'transaction-rolled-back'],
    'rollback plan transaction flag true' => [static fn (): mixed => $rollbackPlan()['transaction_rolled_back'], true],
    'rollback plan savepoint not preserved' => [static fn (): mixed => $rollbackPlan()['savepoint_preserved'], false],
    'rollback plan savepoint name' => [static fn (): mixed => $rollbackPlan()['savepoint'], 'app_settings_rowvalue_rollback_batch'],
    'rollback plan failed statement ordinal after cleanup' => [static fn (): mixed => $rollbackPlan()['rollback_statement']['ordinal'], 2],
    'rollback plan failed statement action update' => [static fn (): mixed => $rollbackPlan()['rollback_statement']['action'], 'update'],
    'rollback plan conflict action rollback' => [static fn (): mixed => $rollbackPlan()['rollback_statement']['conflict_action'], 'rollback'],
    'rollback plan reason records rollback' => [static fn (): mixed => $rollbackPlan()['rollback_statement']['reason'], 'SQLite UPDATE RETURNING unique constraint failed: blog_id,option_name=1|siteurl using OR ROLLBACK'],
    'rollback plan attempted statements before rollback' => [static fn (): mixed => count($rollbackPlan()['attempted_statements_before_rollback']), 2],
    'rollback plan attempted actions update delete' => [static fn (): mixed => array_column($rollbackPlan()['attempted_statements_before_rollback'], 'action'), ['update', 'delete']],
    'rollback plan executed statements discarded' => [static fn (): mixed => $rollbackPlan()['executed_statements'], []],
    'rollback plan yielded returning discarded' => [static fn (): mixed => $rollbackPlan()['yielded_returning'], []],
    'rollback plan attempted streams before rollback' => [static fn (): mixed => array_column($rollbackPlan()['attempted_returning_before_rollback'], 'action'), ['update', 'delete']],
    'rollback plan attempted update row ids' => [static fn (): mixed => array_column($rollbackPlan()['attempted_returning_before_rollback'][0]['rows'], 'option_id'), [7, 8]],
    'rollback plan attempted delete row ids' => [static fn (): mixed => array_column($rollbackPlan()['attempted_returning_before_rollback'][1]['rows'], 'option_id'), [3, 4]],
    'rollback plan discarded returning count four' => [static fn (): mixed => $rollbackPlan()['discarded_returning_count'], 4],
    'rollback plan attempted changes four' => [static fn (): mixed => $rollbackPlan()['attempted_changes_before_rollback'], 4],
    'rollback plan committed changes zero' => [static fn (): mixed => $rollbackPlan()['changes'], 0],
    'rollback plan pre rollback source has row seven draft' => [static fn (): mixed => array_column($rollbackPlan()['pre_rollback_current_source_tables']['wp_options'], 'option_name', 'option_id')[7], 'pending_theme:draft'],
    'rollback plan pre rollback source deleted transients' => [static fn (): mixed => array_column($rollbackPlan()['pre_rollback_current_source_tables']['wp_options'], 'option_id'), [1, 2, 5, 6, 7, 8, 9]],
    'rollback statement source equals pre rollback source' => [static fn (): mixed => $rollbackPlan()['rollback_statement']['statement_source_tables'], $rollbackPlan()['pre_rollback_current_source_tables']],
    'rollback current source restores original ids' => [static fn (): mixed => array_column($rollbackPlan()['current_source_tables']['wp_options'], 'option_id'), [1, 2, 3, 4, 5, 6, 7, 8, 9]],
    'rollback current source restores row seven name' => [static fn (): mixed => array_column($rollbackPlan()['current_source_tables']['wp_options'], 'option_name', 'option_id')[7], 'pending_theme'],
    'rollback current source restores deleted transient' => [static fn (): mixed => array_column($rollbackPlan()['current_source_tables']['wp_options'], 'option_name', 'option_id')[3], '_transient_feed'],
    'rollback current source restores row eight status' => [static fn (): mixed => array_column($rollbackPlan()['current_source_tables']['wp_options'], 'status', 'option_id')[8], 'staged'],
    'rollback current source restores row six status' => [static fn (): mixed => array_column($rollbackPlan()['current_source_tables']['wp_options'], 'status', 'option_id')[6], 'live'],
    'rollback next source equals transaction image' => [static fn (): mixed => $rollbackPlan()['next_source_tables'], $rollbackPlan()['transaction_image_tables']],
    'rollback savepoint image equals transaction image' => [static fn (): mixed => $rollbackPlan()['savepoint_image_tables'], $rollbackPlan()['transaction_image_tables']],
    'rollback row count restored' => [static fn (): mixed => $rollbackPlan()['row_counts']['wp_options'], 9],
    'rollback dependency marks transaction rollback' => [static fn (): mixed => in_array('sqlite-update-or-rollback-rolls-back-transaction', $rollbackPlan()['dependencies'], true), true],
    'rollback dependency marks returning discard' => [static fn (): mixed => in_array('sqlite-row-value-returning-discarded-by-rollback-conflict', $rollbackPlan()['dependencies'], true), true],
    'rollback dependency marks current source transaction image' => [static fn (): mixed => in_array('sqlite-current-source-reverts-to-transaction-image', $rollbackPlan()['dependencies'], true), true],

    'release plan status released' => [static fn (): mixed => $releasePlan()['status'], 'released'],
    'release plan transaction flag false' => [static fn (): mixed => $releasePlan()['transaction_rolled_back'], false],
    'release plan executes update and delete' => [static fn (): mixed => array_column($releasePlan()['executed_statements'], 'action'), ['update', 'delete']],
    'release plan yielded update and delete' => [static fn (): mixed => array_column($releasePlan()['yielded_returning'], 'action'), ['update', 'delete']],
    'release plan update rows returned' => [static fn (): mixed => array_column($releasePlan()['yielded_returning'][0]['rows'], 'option_id'), [7, 8]],
    'release plan delete rows returned' => [static fn (): mixed => array_column($releasePlan()['yielded_returning'][1]['rows'], 'option_id'), [3, 4]],
    'release plan final row ids omit transients' => [static fn (): mixed => array_column($releasePlan()['current_source_tables']['wp_options'], 'option_id'), [1, 2, 5, 6, 7, 8, 9]],
    'release plan final row seven ok' => [static fn (): mixed => array_column($releasePlan()['current_source_tables']['wp_options'], 'option_name', 'option_id')[7], 'pending_theme:ok'],
    'release plan final row eight status ok' => [static fn (): mixed => array_column($releasePlan()['current_source_tables']['wp_options'], 'status', 'option_id')[8], 'ok'],
    'release plan changes four' => [static fn (): mixed => $releasePlan()['changes'], 4],
    'release plan discarded returning zero' => [static fn (): mixed => $releasePlan()['discarded_returning_count'], 0],
    'release plan next source equals current' => [static fn (): mixed => $releasePlan()['next_source_tables'], $releasePlan()['current_source_tables']],
    'custom savepoint accepted' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningSavepointPlan::executeRollbackReturningTransaction($tables, [$releaseSql], $unique, 'wp_custom_rollback')['savepoint'], 'wp_custom_rollback'],
    'malformed empty statements rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningSavepointPlan::executeRollbackReturningTransaction($tables, [], $unique), InvalidArgumentException::class],
    'malformed empty unique constraints rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningSavepointPlan::executeRollbackReturningTransaction($tables, [$releaseSql], []), InvalidArgumentException::class],
    'malformed row list rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningSavepointPlan::executeRollbackReturningTransaction(['wp_options' => ['bad']], [$releaseSql], $unique), InvalidArgumentException::class],
];

$tests = [];
foreach ($cases as $name => [$callback, $expected]) {
    $tests['rowvalue update delete returning savepoint current source next146 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        if (is_string($expected) && is_a($expected, Throwable::class, true)) {
            $t->throws($expected, $callback);
            return;
        }
        $t->same($expected, $callback());
    };
}

return $tests;
