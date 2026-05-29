<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteRowValueConflictSavepointReturningCurrentSourceNextPlan;
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

$stageSql = "UPDATE wp_options SET (blog_id, option_name, status, option_value, bytes) = (blog_id, option_name || ':stage', 'stage', option_value || ':stage', bytes + 1) WHERE option_id IN (7, 8) RETURNING option_id, blog_id, option_name, status, option_value, bytes ORDER BY option_id";
$cleanupSql = "DELETE FROM wp_options WHERE (blog_id, option_name) IN ((1, '_transient_feed'), (1, '_transient_timeout_feed')) RETURNING option_id, option_name ORDER BY option_id";
$conflictSql = "UPDATE OR ABORT wp_options SET (blog_id, option_name, status, option_value) = (1, 'siteurl', option_name || ':conflict', option_value || ':conflict') WHERE option_id IN (9, 6) RETURNING option_id, blog_id, option_name, status, option_value ORDER BY option_id DESC";
$retrySql = "UPDATE wp_options SET (blog_id, option_name, status, option_value, bytes) = (blog_id, option_name || ':retry', 'retry', option_value || ':retry', bytes + 20) WHERE option_id IN (7, 8, 9) RETURNING option_id, blog_id, option_name, status, option_value, bytes ORDER BY option_id";
$retryCleanupSql = "DELETE FROM wp_options WHERE (blog_id, option_name) IN ((1, '_transient_feed'), (1, '_transient_timeout_feed')) RETURNING option_id, option_name ORDER BY option_id";

$parsedStage = static fn (): array => SQLiteUpdateDeleteReturningSql::parse($stageSql);
$parsedConflict = static fn (): array => SQLiteUpdateDeleteReturningSql::parse($conflictSql);
$stageOnly = static fn (): array => SQLiteUpdateDeleteReturningSql::execute($stageSql, $tables, 'option_id', $unique);
$conflictOnly = static fn (): array => SQLiteUpdateDeleteReturningSql::execute($conflictSql, $tables, 'option_id', $unique);
$plan = static fn (): array => SQLiteRowValueConflictSavepointReturningCurrentSourceNextPlan::execute(
    $tables,
    [$stageSql, $cleanupSql, $conflictSql],
    [$retrySql, $retryCleanupSql],
    $unique,
);
$cleanPlan = static fn (): array => SQLiteRowValueConflictSavepointReturningCurrentSourceNextPlan::execute(
    $tables,
    [$stageSql, $cleanupSql],
    [$retrySql],
    $unique,
);

$cases = [
    'stage parser conflict action defaults abort' => [static fn (): mixed => $parsedStage()['conflict_action'], 'abort'],
    'stage parser row-value assignment columns' => [static fn (): mixed => array_keys($parsedStage()['assignments']), ['blog_id', 'option_name', 'status', 'option_value', 'bytes']],
    'stage parser selected where clause' => [static fn (): mixed => $parsedStage()['where'], 'option_id IN (7, 8)'],
    'stage parser order column' => [static fn (): mixed => $parsedStage()['order_by'][0]['column'], 'option_id'],
    'conflict parser explicit abort' => [static fn (): mixed => $parsedConflict()['conflict_action'], 'abort'],
    'conflict parser order descending' => [static fn (): mixed => $parsedConflict()['order_by'][0]['direction'], 'DESC'],
    'conflict parser option value expression' => [static fn (): mixed => $parsedConflict()['assignments']['option_value'], "option_value || ':conflict'"],
    'conflict parser returning projection' => [static fn (): mixed => $parsedConflict()['returning'], 'option_id, blog_id, option_name, status, option_value'],

    'stage only selected ids' => [static fn (): mixed => $stageOnly()['plan']->selectedIds, [7, 8]],
    'stage only mutation ids' => [static fn (): mixed => $stageOnly()['plan']->mutationIds, [7, 8]],
    'stage only returns staged rows' => [static fn (): mixed => array_column($stageOnly()['returning'], 'option_id'), [7, 8]],
    'stage only row seven staged name' => [static fn (): mixed => $stageOnly()['returning'][0]['option_name'], 'pending_theme:stage'],
    'stage only row eight staged value' => [static fn (): mixed => $stageOnly()['returning'][1]['option_value'], 'cache:stage'],
    'stage only bytes incremented' => [static fn (): mixed => array_column($stageOnly()['returning'], 'bytes'), [8, 6]],
    'stage only table current has staged status' => [static fn (): mixed => array_column($stageOnly()['tables']['wp_options'], 'status', 'option_id')[7], 'stage'],
    'stage only no conflicts' => [static fn (): mixed => $stageOnly()['conflicts'], []],
    'conflict only throws abort' => [$conflictOnly, InvalidArgumentException::class],

    'plan status rolled back then retried' => [static fn (): mixed => $plan()['status'], 'rolled-back-to-savepoint-then-retried'],
    'plan rolled back flag true' => [static fn (): mixed => $plan()['rolled_back_to_savepoint'], true],
    'plan savepoint name' => [static fn (): mixed => $plan()['savepoint'], 'wp_options_rowvalue_retry_batch'],
    'plan failed statement ordinal after cleanup' => [static fn (): mixed => $plan()['failed_statement']['ordinal'], 2],
    'plan failed reason records abort' => [static fn (): mixed => $plan()['failed_statement']['reason'], 'SQLite UPDATE RETURNING unique constraint failed: blog_id,option_name=1|siteurl using OR ABORT'],
    'plan executed two statements before failure' => [static fn (): mixed => count($plan()['executed_statements']), 2],
    'plan executed actions before rollback' => [static fn (): mixed => array_column($plan()['executed_statements'], 'action'), ['update', 'delete']],
    'plan yielded streams before rollback' => [static fn (): mixed => array_column($plan()['yielded_returning_before_rollback'], 'action'), ['update', 'delete']],
    'plan yielded staged row ids before rollback' => [static fn (): mixed => array_column($plan()['yielded_returning_before_rollback'][0]['rows'], 'option_id'), [7, 8]],
    'plan yielded cleanup row ids before rollback' => [static fn (): mixed => array_column($plan()['yielded_returning_before_rollback'][1]['rows'], 'option_id'), [3, 4]],
    'plan discarded returning count before rollback' => [static fn (): mixed => $plan()['discarded_returning_count'], 4],
    'plan changes before rollback' => [static fn (): mixed => $plan()['changes_before_rollback'], 4],
    'plan pre rollback source has staged row seven' => [static fn (): mixed => array_column($plan()['pre_rollback_current_source_tables']['wp_options'], 'option_name', 'option_id')[7], 'pending_theme:stage'],
    'plan pre rollback source deleted transients' => [static fn (): mixed => array_column($plan()['pre_rollback_current_source_tables']['wp_options'], 'option_id'), [1, 2, 5, 6, 7, 8, 9]],
    'plan failed statement source equals pre rollback source' => [static fn (): mixed => $plan()['failed_statement']['statement_source_tables'], $plan()['pre_rollback_current_source_tables']],
    'plan rollback source restores original row ids' => [static fn (): mixed => array_column($plan()['rollback_current_source_tables']['wp_options'], 'option_id'), [1, 2, 3, 4, 5, 6, 7, 8, 9]],
    'plan rollback source restores row seven name' => [static fn (): mixed => array_column($plan()['rollback_current_source_tables']['wp_options'], 'option_name', 'option_id')[7], 'pending_theme'],
    'plan rollback source restores deleted transient' => [static fn (): mixed => array_column($plan()['rollback_current_source_tables']['wp_options'], 'option_name', 'option_id')[3], '_transient_feed'],
    'plan savepoint image equals rollback source' => [static fn (): mixed => $plan()['savepoint_image_tables'], $plan()['rollback_current_source_tables']],
    'plan retry executes two statements' => [static fn (): mixed => count($plan()['retry_statements']), 2],
    'plan retry actions update delete' => [static fn (): mixed => array_column($plan()['retry_statements'], 'action'), ['update', 'delete']],
    'plan retry selected ids from restored source' => [static fn (): mixed => $plan()['retry_statements'][0]['selected_ids'], [7, 8, 9]],
    'plan retry source row seven original' => [static fn (): mixed => $plan()['retry_statements'][0]['source_rows'][0]['option_name'], 'pending_theme'],
    'plan retry yielding row ids' => [static fn (): mixed => array_column($plan()['yielded_returning_after_rollback'][0]['rows'], 'option_id'), [7, 8, 9]],
    'plan retry cleanup yielding row ids' => [static fn (): mixed => array_column($plan()['yielded_returning_after_rollback'][1]['rows'], 'option_id'), [3, 4]],
    'plan retry returning count' => [static fn (): mixed => $plan()['retry_returning_count'], 5],
    'plan changes after retry' => [static fn (): mixed => $plan()['changes_after_retry'], 5],
    'plan final row ids after retry cleanup' => [static fn (): mixed => array_column($plan()['post_retry_current_source_tables']['wp_options'], 'option_id'), [1, 2, 5, 6, 7, 8, 9]],
    'plan final row seven retry name' => [static fn (): mixed => array_column($plan()['post_retry_current_source_tables']['wp_options'], 'option_name', 'option_id')[7], 'pending_theme:retry'],
    'plan final row eight retry value' => [static fn (): mixed => array_column($plan()['post_retry_current_source_tables']['wp_options'], 'option_value', 'option_id')[8], 'cache:retry'],
    'plan final row nine retry status' => [static fn (): mixed => array_column($plan()['post_retry_current_source_tables']['wp_options'], 'status', 'option_id')[9], 'retry'],
    'plan final row one unchanged' => [static fn (): mixed => array_column($plan()['post_retry_current_source_tables']['wp_options'], 'option_name', 'option_id')[1], 'siteurl'],
    'plan row count after retry cleanup' => [static fn (): mixed => $plan()['row_counts']['wp_options'], 7],
    'plan dependency marks current source restore' => [static fn (): mixed => in_array('sqlite-rollback-to-savepoint-restores-current-source', $plan()['dependencies'], true), true],
    'plan dependency marks returning discard' => [static fn (): mixed => in_array('sqlite-row-value-returning-yields-before-savepoint-rollback', $plan()['dependencies'], true), true],
    'plan dependency marks retry' => [static fn (): mixed => in_array('sqlite-row-value-conflict-retry-after-rollback-to-savepoint', $plan()['dependencies'], true), true],

    'clean plan status released without conflict' => [static fn (): mixed => $cleanPlan()['status'], 'released-without-conflict'],
    'clean plan rolled back false' => [static fn (): mixed => $cleanPlan()['rolled_back_to_savepoint'], false],
    'clean plan failed statement absent' => [static fn (): mixed => $cleanPlan()['failed_statement'], null],
    'clean plan pre rollback source equals rollback source' => [static fn (): mixed => $cleanPlan()['pre_rollback_current_source_tables'], $cleanPlan()['rollback_current_source_tables']],
    'clean plan still retries from current source' => [static fn (): mixed => $cleanPlan()['retry_statements'][0]['source_rows'][0]['option_name'], 'pending_theme:stage'],
    'clean plan final retry names include staged suffix' => [static fn (): mixed => array_column($cleanPlan()['post_retry_current_source_tables']['wp_options'], 'option_name', 'option_id')[7], 'pending_theme:stage:retry'],
    'custom savepoint accepted' => [static fn (): mixed => SQLiteRowValueConflictSavepointReturningCurrentSourceNextPlan::execute($tables, [$stageSql], [$retrySql], $unique, 'wp_retry_two')['savepoint'], 'wp_retry_two'],
    'malformed empty statements rejected' => [static fn (): mixed => SQLiteRowValueConflictSavepointReturningCurrentSourceNextPlan::execute($tables, [], [$retrySql], $unique), InvalidArgumentException::class],
    'malformed empty retry statements rejected' => [static fn (): mixed => SQLiteRowValueConflictSavepointReturningCurrentSourceNextPlan::execute($tables, [$stageSql], [], $unique), InvalidArgumentException::class],
    'malformed empty unique constraints rejected' => [static fn (): mixed => SQLiteRowValueConflictSavepointReturningCurrentSourceNextPlan::execute($tables, [$stageSql], [$retrySql], []), InvalidArgumentException::class],
    'malformed row list rejected' => [static fn (): mixed => SQLiteRowValueConflictSavepointReturningCurrentSourceNextPlan::execute(['wp_options' => ['bad']], [$stageSql], [$retrySql], $unique), InvalidArgumentException::class],
];

$tests = [];
foreach ($cases as $name => [$callback, $expected]) {
    $tests['rowvalue conflict savepoint returning current source next143 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        if (is_string($expected) && is_a($expected, Throwable::class, true)) {
            $t->throws($expected, $callback);
            return;
        }
        $t->same($expected, $callback());
    };
}

return $tests;
