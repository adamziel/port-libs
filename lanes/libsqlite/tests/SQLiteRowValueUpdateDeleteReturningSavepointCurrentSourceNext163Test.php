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
    ['option_id' => 9, 'blog_id' => 3, 'option_name' => '_transient_plugin', 'autoload' => 'no', 'status' => 'stale', 'bytes' => 10, 'option_value' => 'plugin'],
];

$tables = ['wp_options' => $rows];
$unique = [['blog_id', 'option_name']];

$stageSql = "UPDATE wp_options SET (status, option_value, bytes) = ('draft', option_value || ':draft', bytes + 1) WHERE (blog_id, option_name) BETWEEN (2, 'home') AND (3, 'zzzz') RETURNING option_id, blog_id, option_name, status, bytes, (blog_id, option_name) BETWEEN (2, 'home') AND (3, 'zzzz') AS in_range, (blog_id, option_name) NOT BETWEEN (2, 'home') AND (3, 'zzzz') AS out_range ORDER BY option_id";
$discardDeleteSql = "DELETE FROM wp_options WHERE (blog_id, option_name) BETWEEN (1, '_transient_feed') AND (1, '_transient_timeout_feed') RETURNING option_id, option_name, (blog_id, option_name) BETWEEN (1, '_transient_feed') AND (1, '_transient_timeout_feed') AS old_range ORDER BY option_id";
$retrySql = "UPDATE wp_options SET (status, option_value, bytes) = ('retry', option_value || ':retry', bytes + 5) WHERE (blog_id, option_name) BETWEEN (2, 'home') AND (3, 'zzzz') RETURNING option_id, blog_id, option_name, status, option_value, bytes, (blog_id, option_name) BETWEEN (2, 'home') AND (3, 'zzzz') AS retry_range ORDER BY option_id";
$cleanupSql = "DELETE FROM wp_options WHERE (blog_id, option_name) BETWEEN (1, '_transient_feed') AND (1, '_transient_timeout_feed') RETURNING option_id, option_name, (blog_id, option_name) NOT BETWEEN (1, '_transient_feed') AND (1, '_transient_timeout_feed') AS outside_old_range ORDER BY option_id";

$parsedStage = static fn (): array => SQLiteUpdateDeleteReturningSql::parse($stageSql);
$stageOnly = static fn (): array => SQLiteUpdateDeleteReturningSql::execute($stageSql, $tables, 'option_id', $unique);
$deleteOnly = static function () use ($stageSql, $discardDeleteSql, $tables, $unique): array {
    $staged = SQLiteUpdateDeleteReturningSql::execute($stageSql, $tables, 'option_id', $unique);

    return SQLiteUpdateDeleteReturningSql::execute($discardDeleteSql, $staged['tables'], 'option_id', $unique);
};
$plan = static fn (): array => SQLiteRowValueUpdateDeleteReturningSavepointPlan::executeBetweenRollbackRetrySavepoint(
    $tables,
    [$stageSql, $discardDeleteSql],
    [$retrySql, $cleanupSql],
    $unique,
);
$customPlan = static fn (): array => SQLiteRowValueUpdateDeleteReturningSavepointPlan::executeBetweenRollbackRetrySavepoint(
    $tables,
    [$stageSql],
    [$retrySql],
    $unique,
    'wp_custom_between_retry',
);

$cases = [
    'parse stage where row-value between retained' => [static fn (): mixed => $parsedStage()['where'], "(blog_id, option_name) BETWEEN (2, 'home') AND (3, 'zzzz')"],
    'parse stage returning between alias retained' => [static fn (): mixed => str_contains($parsedStage()['returning'], 'AS in_range'), true],
    'parse stage returning not between alias retained' => [static fn (): mixed => str_contains($parsedStage()['returning'], 'AS out_range'), true],
    'parse stage order by option id' => [static fn (): mixed => $parsedStage()['order_by'][0]['column'], 'option_id'],
    'stage only selected ids by row-value range' => [static fn (): mixed => $stageOnly()['plan']->selectedIds, [5, 6, 7, 8, 9]],
    'stage only mutation ids source order' => [static fn (): mixed => $stageOnly()['plan']->mutationIds, [5, 6, 7, 8, 9]],
    'stage only returning ids' => [static fn (): mixed => array_column($stageOnly()['returning'], 'option_id'), [5, 6, 7, 8, 9]],
    'stage only returning between flags true' => [static fn (): mixed => array_column($stageOnly()['returning'], 'in_range'), [1, 1, 1, 1, 1]],
    'stage only returning not between flags false' => [static fn (): mixed => array_column($stageOnly()['returning'], 'out_range'), [0, 0, 0, 0, 0]],
    'stage only row seven value draft' => [static fn (): mixed => array_column($stageOnly()['tables']['wp_options'], 'option_value', 'option_id')[7], 'theme:draft'],
    'stage only row nine bytes incremented' => [static fn (): mixed => array_column($stageOnly()['tables']['wp_options'], 'bytes', 'option_id')[9], 11],
    'delete after stage selected old transient ids' => [static fn (): mixed => $deleteOnly()['plan']->selectedIds, [3, 4]],
    'delete after stage returning range flags true' => [static fn (): mixed => array_column($deleteOnly()['returning'], 'old_range'), [1, 1]],
    'delete after stage removes old transient ids' => [static fn (): mixed => array_column($deleteOnly()['tables']['wp_options'], 'option_id'), [1, 2, 5, 6, 7, 8, 9]],

    'plan status released after retry' => [static fn (): mixed => $plan()['status'], 'released-after-rowvalue-between-retry'],
    'plan savepoint name' => [static fn (): mixed => $plan()['savepoint'], 'wp_rowvalue_between_retry'],
    'plan rolled back to savepoint' => [static fn (): mixed => $plan()['rolled_back_to_savepoint'], true],
    'plan savepoint preserved after rollback to' => [static fn (): mixed => $plan()['savepoint_preserved_after_rollback_to'], true],
    'plan released true' => [static fn (): mixed => $plan()['released'], true],
    'plan pre rollback actions update delete' => [static fn (): mixed => array_column($plan()['pre_rollback_statements'], 'action'), ['update', 'delete']],
    'plan retry actions update delete' => [static fn (): mixed => array_column($plan()['retry_statements'], 'action'), ['update', 'delete']],
    'plan pre rollback phases' => [static fn (): mixed => array_column($plan()['pre_rollback_statements'], 'phase'), ['before-rollback-to', 'before-rollback-to']],
    'plan retry phases' => [static fn (): mixed => array_column($plan()['retry_statements'], 'phase'), ['after-rollback-to', 'after-rollback-to']],
    'plan pre update selected range ids' => [static fn (): mixed => $plan()['pre_rollback_statements'][0]['selected_ids'], [5, 6, 7, 8, 9]],
    'plan pre delete selected old transients' => [static fn (): mixed => $plan()['pre_rollback_statements'][1]['selected_ids'], [3, 4]],
    'plan attempted before rollback omits transient ids' => [static fn (): mixed => array_column($plan()['attempted_before_rollback_tables']['wp_options'], 'option_id'), [1, 2, 5, 6, 7, 8, 9]],
    'plan attempted before rollback row seven draft' => [static fn (): mixed => array_column($plan()['attempted_before_rollback_tables']['wp_options'], 'status', 'option_id')[7], 'draft'],
    'plan rollback source restores all ids' => [static fn (): mixed => array_column($plan()['rollback_to_current_source_tables']['wp_options'], 'option_id'), [1, 2, 3, 4, 5, 6, 7, 8, 9]],
    'plan rollback source restores row seven null status' => [static fn (): mixed => array_column($plan()['rollback_to_current_source_tables']['wp_options'], 'status', 'option_id')[7], null],
    'plan rollback source restores transient feed' => [static fn (): mixed => array_column($plan()['rollback_to_current_source_tables']['wp_options'], 'option_name', 'option_id')[3], '_transient_feed'],
    'plan retry update source rows restored statuses' => [static fn (): mixed => array_column($plan()['retry_statements'][0]['source_rows'], 'status'), ['live', 'live', null, 'queued', 'stale']],
    'plan retry update selected ids' => [static fn (): mixed => $plan()['retry_statements'][0]['selected_ids'], [5, 6, 7, 8, 9]],
    'plan retry update returning ids' => [static fn (): mixed => array_column($plan()['yielded_returning'][0]['rows'], 'option_id'), [5, 6, 7, 8, 9]],
    'plan retry returning range flags true' => [static fn (): mixed => array_column($plan()['yielded_returning'][0]['rows'], 'retry_range'), [1, 1, 1, 1, 1]],
    'plan retry row seven value from restored original' => [static fn (): mixed => $plan()['yielded_returning'][0]['rows'][2]['option_value'], 'theme:retry'],
    'plan retry row nine bytes from original' => [static fn (): mixed => $plan()['yielded_returning'][0]['rows'][4]['bytes'], 15],
    'plan retry delete sees restored old transients' => [static fn (): mixed => $plan()['retry_statements'][1]['selected_ids'], [3, 4]],
    'plan retry delete returning ids' => [static fn (): mixed => array_column($plan()['yielded_returning'][1]['rows'], 'option_id'), [3, 4]],
    'plan cleanup outside range flags false' => [static fn (): mixed => array_column($plan()['yielded_returning'][1]['rows'], 'outside_old_range'), [0, 0]],
    'plan discarded returning phases' => [static fn (): mixed => array_column($plan()['discarded_returning'], 'phase'), ['before-rollback-to', 'before-rollback-to']],
    'plan discarded update ids' => [static fn (): mixed => array_column($plan()['discarded_returning'][0]['rows'], 'option_id'), [5, 6, 7, 8, 9]],
    'plan discarded delete ids' => [static fn (): mixed => array_column($plan()['discarded_returning'][1]['rows'], 'option_id'), [3, 4]],
    'plan discarded returning count seven' => [static fn (): mixed => $plan()['discarded_returning_count'], 7],
    'plan yielded returning count seven' => [static fn (): mixed => $plan()['yielded_returning_count'], 7],
    'plan discarded changes seven' => [static fn (): mixed => $plan()['discarded_changes_before_rollback_to'], 7],
    'plan changes after release seven' => [static fn (): mixed => $plan()['changes_after_release'], 7],
    'plan final source ids after cleanup' => [static fn (): mixed => array_column($plan()['current_source_tables']['wp_options'], 'option_id'), [1, 2, 5, 6, 7, 8, 9]],
    'plan final row seven retry status' => [static fn (): mixed => array_column($plan()['current_source_tables']['wp_options'], 'status', 'option_id')[7], 'retry'],
    'plan final row eight retry value' => [static fn (): mixed => array_column($plan()['current_source_tables']['wp_options'], 'option_value', 'option_id')[8], 'rules:retry'],
    'plan final row nine retry bytes' => [static fn (): mixed => array_column($plan()['current_source_tables']['wp_options'], 'bytes', 'option_id')[9], 15],
    'plan final removed transient feed' => [static fn (): mixed => in_array(3, array_column($plan()['current_source_tables']['wp_options'], 'option_id'), true), false],
    'plan next source equals current' => [static fn (): mixed => $plan()['next_source_tables'], $plan()['current_source_tables']],
    'plan savepoint image equals original tables' => [static fn (): mixed => $plan()['savepoint_image_tables'], $tables],
    'plan row count after retry cleanup' => [static fn (): mixed => $plan()['row_counts']['wp_options'], 7],
    'plan dependency row-value between returning' => [static fn (): mixed => in_array('sqlite-row-value-between-returning-expression', $plan()['dependencies'], true), true],
    'plan dependency rollback discard' => [static fn (): mixed => in_array('sqlite-update-delete-returning-rollback-to-discards-current-stream', $plan()['dependencies'], true), true],
    'plan dependency retry source restored' => [static fn (): mixed => in_array('sqlite-retry-after-rollback-to-reads-restored-current-source', $plan()['dependencies'], true), true],
    'custom savepoint accepted' => [static fn (): mixed => $customPlan()['savepoint'], 'wp_custom_between_retry'],
    'custom plan retry rows restored' => [static fn (): mixed => array_column($customPlan()['retry_statements'][0]['source_rows'], 'option_value'), ['https://network.test', 'https://network-home.test', 'theme', 'rules', 'plugin']],
    'malformed empty pre statements rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningSavepointPlan::executeBetweenRollbackRetrySavepoint($tables, [], [$retrySql], $unique), InvalidArgumentException::class],
    'malformed empty retry statements rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningSavepointPlan::executeBetweenRollbackRetrySavepoint($tables, [$stageSql], [], $unique), InvalidArgumentException::class],
    'malformed empty unique constraints rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningSavepointPlan::executeBetweenRollbackRetrySavepoint($tables, [$stageSql], [$retrySql], []), InvalidArgumentException::class],
    'malformed bad savepoint rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningSavepointPlan::executeBetweenRollbackRetrySavepoint($tables, [$stageSql], [$retrySql], $unique, 'bad-name'), InvalidArgumentException::class],
    'malformed row list rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningSavepointPlan::executeBetweenRollbackRetrySavepoint(['wp_options' => ['bad']], [$stageSql], [$retrySql], $unique), InvalidArgumentException::class],
];

$tests = [];
foreach ($cases as $name => [$callback, $expected]) {
    $tests['rowvalue update delete returning savepoint current source next163 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        if (is_string($expected) && is_a($expected, Throwable::class, true)) {
            $t->throws($expected, $callback);
            return;
        }
        $t->same($expected, $callback());
    };
}

return $tests;
