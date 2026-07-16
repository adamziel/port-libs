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

$stageSql = "UPDATE wp_options SET (option_name, status, option_value, bytes) = (option_name || ':draft', 'draft', option_value || ':draft', bytes + 1) WHERE option_id IN (7, 8) RETURNING option_id, blog_id, option_name, status, option_value, bytes, (status, option_name) = ('draft', 'pending_theme:draft') AS pending_draft ORDER BY option_id";
$discardDeleteSql = "DELETE FROM wp_options WHERE (blog_id, option_name) IN ((1, '_transient_feed'), (1, '_transient_timeout_feed')) RETURNING option_id, option_name, status ORDER BY option_id";
$retryUpdateSql = "UPDATE wp_options SET (option_name, status, option_value, bytes) = (option_name || ':retry', 'retry', option_value || ':retry', bytes + 10) WHERE option_id IN (7, 8) RETURNING option_id, option_name, status, option_value, bytes, (status, option_name) = ('retry', 'pending_theme:retry') AS pending_retry ORDER BY option_id";
$retryDeleteSql = "DELETE FROM wp_options WHERE (blog_id, option_name) IN ((1, '_transient_feed'), (1, '_transient_timeout_feed')) RETURNING option_id, option_name, status ORDER BY option_id";

$parsedStage = static fn (): array => SQLiteUpdateDeleteReturningSql::parse($stageSql);
$parsedRetry = static fn (): array => SQLiteUpdateDeleteReturningSql::parse($retryUpdateSql);
$stageOnly = static fn (): array => SQLiteUpdateDeleteReturningSql::execute($stageSql, $tables, 'option_id', $unique);
$discardDeleteAfterStage = static function () use ($stageSql, $discardDeleteSql, $tables, $unique): array {
    $staged = SQLiteUpdateDeleteReturningSql::execute($stageSql, $tables, 'option_id', $unique);

    return SQLiteUpdateDeleteReturningSql::execute($discardDeleteSql, $staged['tables'], 'option_id', $unique);
};
$plan = static fn (): array => SQLiteRowValueUpdateDeleteReturningSavepointPlan::executePreflightRetrySavepointBatch(
    $tables,
    [$stageSql, $discardDeleteSql],
    [$retryUpdateSql, $retryDeleteSql],
    $unique,
);
$customPlan = static fn (): array => SQLiteRowValueUpdateDeleteReturningSavepointPlan::executePreflightRetrySavepointBatch(
    $tables,
    [$stageSql],
    [$retryUpdateSql],
    $unique,
    'wp_custom_rowvalue_retry',
);

$cases = [
    'parser stage action update' => [static fn (): mixed => $parsedStage()['action'], 'update'],
    'parser stage assignment columns' => [static fn (): mixed => array_keys($parsedStage()['assignments']), ['option_name', 'status', 'option_value', 'bytes']],
    'parser stage returning row value expression' => [static fn (): mixed => $parsedStage()['returning'], "option_id, blog_id, option_name, status, option_value, bytes, (status, option_name) = ('draft', 'pending_theme:draft') AS pending_draft"],
    'parser stage order by option id' => [static fn (): mixed => $parsedStage()['order_by'][0]['column'], 'option_id'],
    'parser retry returns retry predicate' => [static fn (): mixed => $parsedRetry()['returning'], "option_id, option_name, status, option_value, bytes, (status, option_name) = ('retry', 'pending_theme:retry') AS pending_retry"],
    'stage only selected ids' => [static fn (): mixed => $stageOnly()['plan']->selectedIds, [7, 8]],
    'stage only returns draft rows' => [static fn (): mixed => array_column($stageOnly()['returning'], 'option_id'), [7, 8]],
    'stage only row seven draft predicate true' => [static fn (): mixed => $stageOnly()['returning'][0]['pending_draft'], 1],
    'stage only row eight value draft' => [static fn (): mixed => $stageOnly()['returning'][1]['option_value'], 'rules:draft'],
    'discard delete after stage sees staged source' => [static fn (): mixed => array_column($discardDeleteAfterStage()['tables']['wp_options'], 'option_id'), [1, 2, 5, 6, 7, 8]],

    'plan status released after rollback retry' => [static fn (): mixed => $plan()['status'], 'released-after-rollback-to-retry'],
    'plan savepoint name' => [static fn (): mixed => $plan()['savepoint'], 'app_settings_rowvalue_retry_batch'],
    'plan rolled back to savepoint' => [static fn (): mixed => $plan()['rolled_back_to_savepoint'], true],
    'plan savepoint remains active after rollback to' => [static fn (): mixed => $plan()['savepoint_preserved_after_rollback_to'], true],
    'plan released true' => [static fn (): mixed => $plan()['released'], true],
    'plan pre rollback action chain' => [static fn (): mixed => array_column($plan()['pre_rollback_statements'], 'action'), ['update', 'delete']],
    'plan retry action chain' => [static fn (): mixed => array_column($plan()['retry_statements'], 'action'), ['update', 'delete']],
    'plan pre rollback phases' => [static fn (): mixed => array_column($plan()['pre_rollback_statements'], 'phase'), ['before-rollback', 'before-rollback']],
    'plan retry phases' => [static fn (): mixed => array_column($plan()['retry_statements'], 'phase'), ['after-rollback', 'after-rollback']],
    'plan pre update selected ids' => [static fn (): mixed => $plan()['pre_rollback_statements'][0]['selected_ids'], [7, 8]],
    'plan pre delete selected ids' => [static fn (): mixed => $plan()['pre_rollback_statements'][1]['selected_ids'], [3, 4]],
    'plan attempted before rollback omits deleted transient ids' => [static fn (): mixed => array_column($plan()['attempted_before_rollback_tables']['wp_options'], 'option_id'), [1, 2, 5, 6, 7, 8]],
    'plan attempted before rollback staged row seven' => [static fn (): mixed => array_column($plan()['attempted_before_rollback_tables']['wp_options'], 'option_name', 'option_id')[7], 'pending_theme:draft'],
    'plan rollback current source restores ids' => [static fn (): mixed => array_column($plan()['rollback_to_current_source_tables']['wp_options'], 'option_id'), [1, 2, 3, 4, 5, 6, 7, 8]],
    'plan rollback current source restores row seven name' => [static fn (): mixed => array_column($plan()['rollback_to_current_source_tables']['wp_options'], 'option_name', 'option_id')[7], 'pending_theme'],
    'plan rollback current source restores transient feed' => [static fn (): mixed => array_column($plan()['rollback_to_current_source_tables']['wp_options'], 'option_name', 'option_id')[3], '_transient_feed'],
    'plan retry update source rows restored names' => [static fn (): mixed => array_column($plan()['retry_statements'][0]['source_rows'], 'option_name'), ['pending_theme', 'rewrite_rules']],
    'plan retry update selected ids' => [static fn (): mixed => $plan()['retry_statements'][0]['selected_ids'], [7, 8]],
    'plan retry update returns ids' => [static fn (): mixed => array_column($plan()['yielded_returning'][0]['rows'], 'option_id'), [7, 8]],
    'plan retry row seven predicate true' => [static fn (): mixed => $plan()['yielded_returning'][0]['rows'][0]['pending_retry'], 1],
    'plan retry row eight bytes from original source' => [static fn (): mixed => $plan()['yielded_returning'][0]['rows'][1]['bytes'], 19],
    'plan retry delete sees restored transient ids' => [static fn (): mixed => $plan()['retry_statements'][1]['selected_ids'], [3, 4]],
    'plan retry delete source rows restored stale statuses' => [static fn (): mixed => array_column($plan()['retry_statements'][1]['source_rows'], 'status'), ['stale', 'stale']],
    'plan retry delete returning ids' => [static fn (): mixed => array_column($plan()['yielded_returning'][1]['rows'], 'option_id'), [3, 4]],
    'plan discarded returning phases' => [static fn (): mixed => array_column($plan()['discarded_returning'], 'phase'), ['before-rollback', 'before-rollback']],
    'plan discarded update ids' => [static fn (): mixed => array_column($plan()['discarded_returning'][0]['rows'], 'option_id'), [7, 8]],
    'plan discarded delete ids' => [static fn (): mixed => array_column($plan()['discarded_returning'][1]['rows'], 'option_id'), [3, 4]],
    'plan discarded returning count four' => [static fn (): mixed => $plan()['discarded_returning_count'], 4],
    'plan discarded changes four' => [static fn (): mixed => $plan()['discarded_changes_before_rollback_to'], 4],
    'plan changes after release four' => [static fn (): mixed => $plan()['changes_after_release'], 4],
    'plan final source ids after retry delete' => [static fn (): mixed => array_column($plan()['current_source_tables']['wp_options'], 'option_id'), [1, 2, 5, 6, 7, 8]],
    'plan final row seven retry name' => [static fn (): mixed => array_column($plan()['current_source_tables']['wp_options'], 'option_name', 'option_id')[7], 'pending_theme:retry'],
    'plan final row eight retry status' => [static fn (): mixed => array_column($plan()['current_source_tables']['wp_options'], 'status', 'option_id')[8], 'retry'],
    'plan final row eight retry value from original' => [static fn (): mixed => array_column($plan()['current_source_tables']['wp_options'], 'option_value', 'option_id')[8], 'rules:retry'],
    'plan final source removed transient feed' => [static fn (): mixed => in_array(3, array_column($plan()['current_source_tables']['wp_options'], 'option_id'), true), false],
    'plan next source equals current' => [static fn (): mixed => $plan()['next_source_tables'], $plan()['current_source_tables']],
    'plan savepoint image equals original tables' => [static fn (): mixed => $plan()['savepoint_image_tables'], $tables],
    'plan row count after retry delete' => [static fn (): mixed => $plan()['row_counts']['wp_options'], 6],
    'plan dependency rollback to active savepoint' => [static fn (): mixed => in_array('sqlite-rollback-to-savepoint-keeps-savepoint-active', $plan()['dependencies'], true), true],
    'plan dependency returning discarded' => [static fn (): mixed => in_array('sqlite-row-value-update-delete-returning-discarded-on-rollback-to', $plan()['dependencies'], true), true],
    'plan dependency retry current source' => [static fn (): mixed => in_array('sqlite-retry-statements-read-restored-current-source', $plan()['dependencies'], true), true],
    'custom savepoint accepted' => [static fn (): mixed => $customPlan()['savepoint'], 'wp_custom_rowvalue_retry'],
    'custom plan retry source uses restored name' => [static fn (): mixed => array_column($customPlan()['retry_statements'][0]['source_rows'], 'option_name'), ['pending_theme', 'rewrite_rules']],
    'malformed empty pre statements rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningSavepointPlan::executePreflightRetrySavepointBatch($tables, [], [$retryUpdateSql], $unique), InvalidArgumentException::class],
    'malformed empty retry statements rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningSavepointPlan::executePreflightRetrySavepointBatch($tables, [$stageSql], [], $unique), InvalidArgumentException::class],
    'malformed empty unique constraints rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningSavepointPlan::executePreflightRetrySavepointBatch($tables, [$stageSql], [$retryUpdateSql], []), InvalidArgumentException::class],
    'malformed row list rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningSavepointPlan::executePreflightRetrySavepointBatch(['wp_options' => ['bad']], [$stageSql], [$retryUpdateSql], $unique), InvalidArgumentException::class],
];

$tests = [];
foreach ($cases as $name => [$callback, $expected]) {
    $tests['rowvalue update delete returning savepoint preflight retry ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        if (is_string($expected) && is_a($expected, Throwable::class, true)) {
            $t->throws($expected, $callback);
            return;
        }
        $t->same($expected, $callback());
    };
}

return $tests;
