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
    ['option_id' => 8, 'blog_id' => 3, 'option_name' => 'rewrite_rules', 'autoload' => 'yes', 'status' => 'queued', 'bytes' => 9, 'option_value' => 'rules'],
];

$tables = ['wp_options' => $rows];
$unique = [['blog_id', 'option_name']];

$stageSql = "UPDATE wp_options SET (option_name, status, option_value, bytes) = (option_name || ':stage', 'stage', option_value || ':stage', bytes + 1) WHERE option_id IN (7, 8) RETURNING option_id, blog_id, option_name, status, option_value, bytes, (status, option_name) = ('stage', 'pending_theme:stage') AS pending_stage ORDER BY option_id";
$rollbackSql = "UPDATE OR ROLLBACK wp_options SET (blog_id, option_name, status, option_value) = (2, 'siteurl', option_name || ':rollback', option_value || ':rollback') WHERE option_id = 7 RETURNING option_id, blog_id, option_name, status, option_value";
$retryUpdateSql = "UPDATE wp_options SET (option_name, status, option_value, bytes) = (option_name || ':retry164', 'retry', option_value || ':retry164', bytes + 10) WHERE option_id IN (7, 8) RETURNING option_id, option_name, status, option_value, bytes, (status, option_name) = ('retry', 'pending_theme:retry164') AS pending_retry ORDER BY option_id";
$retryDeleteSql = "DELETE FROM wp_options WHERE (blog_id, option_name) IN ((1, '_transient_feed'), (1, '_transient_timeout_feed')) RETURNING option_id, option_name, status ORDER BY option_id";
$cleanSql = "UPDATE wp_options SET (option_name, status) = (option_name || ':clean', 'clean') WHERE option_id = 8 RETURNING option_id, option_name, status";

$parsedRollback = static fn (): array => SQLiteUpdateDeleteReturningSql::parse($rollbackSql);
$stageOnly = static fn (): array => SQLiteUpdateDeleteReturningSql::execute($stageSql, $tables, 'option_id', $unique);
$rollbackOnly = static fn (): array => SQLiteUpdateDeleteReturningSql::execute($rollbackSql, $tables, 'option_id', $unique);
$plan = static fn (): array => SQLiteRowValueUpdateDeleteReturningSavepointPlan::executeNullInequalityRetrySavepointBatch(
    $tables,
    [$stageSql, $rollbackSql, $retryDeleteSql],
    [$retryUpdateSql, $retryDeleteSql],
    $unique,
);
$cleanPlan = static fn (): array => SQLiteRowValueUpdateDeleteReturningSavepointPlan::executeNullInequalityRetrySavepointBatch(
    $tables,
    [$cleanSql],
    [$retryDeleteSql],
    $unique,
    'wp_options_clean_next164',
);

$cases = [
    'parser records rollback conflict action' => [static fn (): mixed => $parsedRollback()['conflict_action'], 'rollback'],
    'parser rollback row value assignment columns' => [static fn (): mixed => array_keys($parsedRollback()['assignments']), ['blog_id', 'option_name', 'status', 'option_value']],
    'parser rollback where id predicate' => [static fn (): mixed => $parsedRollback()['where'], 'option_id = 7'],
    'standalone rollback conflict throws' => [$rollbackOnly, InvalidArgumentException::class],
    'stage selected ids' => [static fn (): mixed => $stageOnly()['plan']->selectedIds, [7, 8]],
    'stage yields two returning rows' => [static fn (): mixed => array_column($stageOnly()['returning'], 'option_id'), [7, 8]],
    'stage row seven predicate true' => [static fn (): mixed => $stageOnly()['returning'][0]['pending_stage'], 1],
    'stage row eight value staged' => [static fn (): mixed => $stageOnly()['returning'][1]['option_value'], 'rules:stage'],

    'plan status transaction rolled back retried' => [static fn (): mixed => $plan()['status'], 'transaction-rolled-back-retried-current-source-next164'],
    'plan savepoint default name' => [static fn (): mixed => $plan()['savepoint'], 'wp_options_rowvalue_rollback_batch'],
    'plan transaction rolled back' => [static fn (): mixed => $plan()['transaction_rolled_back'], true],
    'plan savepoint not preserved after rollback conflict' => [static fn (): mixed => $plan()['savepoint_preserved_after_rollback'], false],
    'plan rollback statement ordinal one' => [static fn (): mixed => $plan()['rollback_statement_ordinal'], 1],
    'plan rollback reason names unique columns' => [static fn (): mixed => str_contains((string) $plan()['rollback_reason'], 'blog_id,option_name=2|siteurl using OR ROLLBACK'), true],
    'plan attempt statements stop before retry delete' => [static fn (): mixed => count($plan()['attempt_statements']), 1],
    'plan attempt statement phase before rollback' => [static fn (): mixed => $plan()['attempt_statements'][0]['phase'], 'before-rollback'],
    'plan attempt statement action update' => [static fn (): mixed => $plan()['attempt_statements'][0]['action'], 'update'],
    'plan attempt conflict action abort for stage' => [static fn (): mixed => $plan()['attempt_statements'][0]['conflict_action'], 'abort'],
    'plan attempted selected ids' => [static fn (): mixed => $plan()['attempt_statements'][0]['selected_ids'], [7, 8]],
    'plan attempted mutation ids source order' => [static fn (): mixed => $plan()['attempt_statements'][0]['mutation_ids'], [7, 8]],
    'plan attempted source rows original names' => [static fn (): mixed => array_column($plan()['attempt_statements'][0]['source_rows'], 'option_name'), ['pending_theme', 'rewrite_rules']],
    'plan attempted returning rows staged' => [static fn (): mixed => array_column($plan()['attempted_returning_before_rollback'][0]['rows'], 'status'), ['stage', 'stage']],
    'plan attempted returning phases' => [static fn (): mixed => array_column($plan()['attempted_returning_before_rollback'], 'phase'), ['before-rollback']],
    'plan discarded returning equals attempted stream' => [static fn (): mixed => $plan()['discarded_returning'], $plan()['attempted_returning_before_rollback']],
    'plan discarded returning count two' => [static fn (): mixed => $plan()['discarded_returning_count'], 2],
    'plan attempted changes before rollback two' => [static fn (): mixed => $plan()['attempted_changes_before_rollback'], 2],
    'plan attempted current source has staged row seven' => [static fn (): mixed => array_column($plan()['attempted_current_source_tables']['wp_options'], 'option_name', 'option_id')[7], 'pending_theme:stage'],
    'plan attempted current source has staged row eight' => [static fn (): mixed => array_column($plan()['attempted_current_source_tables']['wp_options'], 'option_name', 'option_id')[8], 'rewrite_rules:stage'],
    'plan rollback current source restores original ids' => [static fn (): mixed => array_column($plan()['rollback_current_source_tables']['wp_options'], 'option_id'), [1, 2, 3, 4, 5, 6, 7, 8]],
    'plan rollback current source restores row seven name' => [static fn (): mixed => array_column($plan()['rollback_current_source_tables']['wp_options'], 'option_name', 'option_id')[7], 'pending_theme'],
    'plan rollback current source restores row eight value' => [static fn (): mixed => array_column($plan()['rollback_current_source_tables']['wp_options'], 'option_value', 'option_id')[8], 'rules'],
    'plan transaction image is original tables' => [static fn (): mixed => $plan()['transaction_image_tables'], $tables],
    'plan retry statement phases' => [static fn (): mixed => array_column($plan()['retry_statements'], 'phase'), ['after-rollback', 'after-rollback']],
    'plan retry statement actions' => [static fn (): mixed => array_column($plan()['retry_statements'], 'action'), ['update', 'delete']],
    'plan retry update source rows restored names' => [static fn (): mixed => array_column($plan()['retry_statements'][0]['source_rows'], 'option_name'), ['pending_theme', 'rewrite_rules']],
    'plan retry update selected ids restored' => [static fn (): mixed => $plan()['retry_statements'][0]['selected_ids'], [7, 8]],
    'plan retry update yields two rows' => [static fn (): mixed => array_column($plan()['yielded_returning'][0]['rows'], 'option_id'), [7, 8]],
    'plan retry row seven predicate true' => [static fn (): mixed => $plan()['yielded_returning'][0]['rows'][0]['pending_retry'], 1],
    'plan retry row eight bytes from original source' => [static fn (): mixed => $plan()['yielded_returning'][0]['rows'][1]['bytes'], 19],
    'plan retry delete source rows stale' => [static fn (): mixed => array_column($plan()['retry_statements'][1]['source_rows'], 'status'), ['stale', 'stale']],
    'plan retry delete yields transient ids' => [static fn (): mixed => array_column($plan()['yielded_returning'][1]['rows'], 'option_id'), [3, 4]],
    'plan yielded returning count four' => [static fn (): mixed => $plan()['yielded_returning_count'], 4],
    'plan changes after retry four' => [static fn (): mixed => $plan()['changes_after_retry'], 4],
    'plan final ids omit transient rows' => [static fn (): mixed => array_column($plan()['current_source_tables']['wp_options'], 'option_id'), [1, 2, 5, 6, 7, 8]],
    'plan final row seven retry name' => [static fn (): mixed => array_column($plan()['current_source_tables']['wp_options'], 'option_name', 'option_id')[7], 'pending_theme:retry164'],
    'plan final row eight retry status' => [static fn (): mixed => array_column($plan()['current_source_tables']['wp_options'], 'status', 'option_id')[8], 'retry'],
    'plan next source equals current source' => [static fn (): mixed => $plan()['next_source_tables'], $plan()['current_source_tables']],
    'plan changed tables reports wp options' => [static fn (): mixed => $plan()['changed_tables_after_retry'], ['wp_options']],
    'plan row count after retry' => [static fn (): mixed => $plan()['row_counts']['wp_options'], 6],
    'plan dependency records rollback cancellation' => [static fn (): mixed => in_array('sqlite-update-or-rollback-rowvalue-returning-cancels-savepoint-transaction', $plan()['dependencies'], true), true],
    'plan dependency records discarded returning' => [static fn (): mixed => in_array('sqlite-rollback-conflict-discards-attempted-returning-streams', $plan()['dependencies'], true), true],
    'plan dependency records retry source' => [static fn (): mixed => in_array('sqlite-rowvalue-update-delete-returning-retry-starts-from-transaction-image-next164', $plan()['dependencies'], true), true],

    'clean plan status without rollback' => [static fn (): mixed => $cleanPlan()['status'], 'released-without-rollback-current-source-next164'],
    'clean plan custom savepoint' => [static fn (): mixed => $cleanPlan()['savepoint'], 'wp_options_clean_next164'],
    'clean plan no rollback ordinal' => [static fn (): mixed => $cleanPlan()['rollback_statement_ordinal'], null],
    'clean plan discarded returning zero' => [static fn (): mixed => $cleanPlan()['discarded_returning_count'], 0],
    'clean plan retry starts from attempted clean row' => [static fn (): mixed => array_column($cleanPlan()['retry_statements'][0]['source_rows'], 'option_name'), ['_transient_feed', '_transient_timeout_feed']],

    'malformed empty attempt statements rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningSavepointPlan::executeNullInequalityRetrySavepointBatch($tables, [], [$retryUpdateSql], $unique), InvalidArgumentException::class],
    'malformed empty retry statements rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningSavepointPlan::executeNullInequalityRetrySavepointBatch($tables, [$stageSql], [], $unique), InvalidArgumentException::class],
    'malformed empty unique constraints rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningSavepointPlan::executeNullInequalityRetrySavepointBatch($tables, [$stageSql], [$retryUpdateSql], []), InvalidArgumentException::class],
    'malformed row list rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningSavepointPlan::executeNullInequalityRetrySavepointBatch(['wp_options' => ['bad']], [$stageSql], [$retryUpdateSql], $unique), InvalidArgumentException::class],
];

$tests = [];
foreach ($cases as $name => [$callback, $expected]) {
    $tests['rowvalue update delete returning savepoint current source next164 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        if (is_string($expected) && is_a($expected, Throwable::class, true)) {
            $t->throws($expected, $callback);
            return;
        }
        $t->same($expected, $callback());
    };
}

return $tests;
