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
    ['option_id' => 9, 'blog_id' => 3, 'option_name' => 'orphaned_cache', 'autoload' => 'no', 'status' => 'staged', 'bytes' => 5, 'option_value' => 'cache'],
];

$tables = ['wp_options' => $rows];
$unique = [['blog_id', 'option_name']];

$innerUpdateSql = "UPDATE wp_options SET (option_name, status, option_value, bytes) = (option_name || ':inner', 'inner', option_value || ':inner', bytes + 1) WHERE (blog_id, option_name) IN ((2, 'pending_theme'), (3, 'rewrite_rules')) RETURNING option_id, blog_id, option_name, status, option_value, bytes, (status, option_name) = ('inner', 'pending_theme:inner') AS pending_inner ORDER BY option_id";
$innerDeleteSql = "DELETE FROM wp_options WHERE (blog_id, option_name) IN ((1, '_transient_feed'), (1, '_transient_timeout_feed')) RETURNING option_id, blog_id, option_name, status ORDER BY option_id";
$outerUpdateSql = "UPDATE wp_options SET (option_name, status, option_value, bytes) = (option_name || ':outer', 'outer', option_value || ':outer', bytes + 10) WHERE option_id IN (7, 8) RETURNING option_id, blog_id, option_name, status, option_value, bytes, (status, option_name) = ('outer', 'pending_theme:inner:outer') AS pending_outer ORDER BY option_id";
$outerDeleteSql = "DELETE FROM wp_options WHERE (blog_id, option_name) IN ((3, 'orphaned_cache'), (1, 'siteurl')) RETURNING option_id, blog_id, option_name, status ORDER BY option_id";
$retryUpdateSql = "UPDATE wp_options SET (option_name, status, option_value, bytes) = (option_name || ':retry', 'retry', option_value || ':retry', bytes + 100) WHERE option_id IN (7, 8, 9) RETURNING option_id, blog_id, option_name, status, option_value, bytes, (status, option_name) = ('retry', 'pending_theme:retry') AS pending_retry ORDER BY option_id";
$retryDeleteSql = "DELETE FROM wp_options WHERE (blog_id, option_name) IN ((1, '_transient_feed'), (1, '_transient_timeout_feed')) RETURNING option_id, blog_id, option_name, status ORDER BY option_id";

$parsedInner = static fn (): array => SQLiteUpdateDeleteReturningSql::parse($innerUpdateSql);
$innerOnly = static fn (): array => SQLiteUpdateDeleteReturningSql::execute($innerUpdateSql, $tables, 'option_id', $unique);
$innerDeleteAfterUpdate = static function () use ($innerUpdateSql, $innerDeleteSql, $tables, $unique): array {
    $updated = SQLiteUpdateDeleteReturningSql::execute($innerUpdateSql, $tables, 'option_id', $unique);

    return SQLiteUpdateDeleteReturningSql::execute($innerDeleteSql, $updated['tables'], 'option_id', $unique);
};
$plan = static fn (): array => SQLiteRowValueUpdateDeleteReturningSavepointPlan::executeNestedRetrySavepointBatch(
    $tables,
    [$innerUpdateSql, $innerDeleteSql],
    [$outerUpdateSql, $outerDeleteSql],
    [$retryUpdateSql, $retryDeleteSql],
    $unique,
);
$customPlan = static fn (): array => SQLiteRowValueUpdateDeleteReturningSavepointPlan::executeNestedRetrySavepointBatch(
    $tables,
    [$innerUpdateSql],
    [$outerDeleteSql],
    [$retryUpdateSql],
    $unique,
    'outer_custom_nested_retry',
    'inner_custom_nested_retry',
);

$cases = [
    'parser records row value where' => [static fn (): mixed => $parsedInner()['where'], "(blog_id, option_name) IN ((2, 'pending_theme'), (3, 'rewrite_rules'))"],
    'parser records returning alias' => [static fn (): mixed => str_contains($parsedInner()['returning'], 'pending_inner'), true],
    'parser records order by' => [static fn (): mixed => $parsedInner()['order_by'][0]['column'], 'option_id'],
    'inner only selected ids' => [static fn (): mixed => $innerOnly()['plan']->selectedIds, [7, 8]],
    'inner only mutation ids' => [static fn (): mixed => $innerOnly()['plan']->mutationIds, [7, 8]],
    'inner only returning ids' => [static fn (): mixed => array_column($innerOnly()['returning'], 'option_id'), [7, 8]],
    'inner only row seven predicate true' => [static fn (): mixed => $innerOnly()['returning'][0]['pending_inner'], 1],
    'inner only row eight value inner' => [static fn (): mixed => $innerOnly()['returning'][1]['option_value'], 'rules:inner'],
    'inner delete after update omits transient ids' => [static fn (): mixed => array_column($innerDeleteAfterUpdate()['tables']['wp_options'], 'option_id'), [1, 2, 5, 6, 7, 8, 9]],
    'inner delete after update returns transient ids' => [static fn (): mixed => array_column($innerDeleteAfterUpdate()['returning'], 'option_id'), [3, 4]],

    'plan status' => [static fn (): mixed => $plan()['status'], 'inner-release-discarded-by-outer-rollback-retried'],
    'plan outer savepoint' => [static fn (): mixed => $plan()['outer_savepoint'], 'app_settings_outer_import_nested_retry'],
    'plan inner savepoint' => [static fn (): mixed => $plan()['inner_savepoint'], 'app_settings_inner_cleanup_nested_retry'],
    'plan inner released true' => [static fn (): mixed => $plan()['inner_released'], true],
    'plan outer rolled back true' => [static fn (): mixed => $plan()['outer_rolled_back_to_savepoint'], true],
    'plan outer savepoint preserved' => [static fn (): mixed => $plan()['outer_savepoint_preserved_after_rollback_to'], true],
    'plan released after retry' => [static fn (): mixed => $plan()['released_after_retry'], true],
    'plan inner statement actions' => [static fn (): mixed => array_column($plan()['inner_released_statements'], 'action'), ['update', 'delete']],
    'plan outer statement actions' => [static fn (): mixed => array_column($plan()['outer_attempted_statements'], 'action'), ['update', 'delete']],
    'plan retry statement actions' => [static fn (): mixed => array_column($plan()['retry_statements'], 'action'), ['update', 'delete']],
    'plan statement phases' => [static fn (): mixed => [
        array_column($plan()['inner_released_statements'], 'phase'),
        array_column($plan()['outer_attempted_statements'], 'phase'),
        array_column($plan()['retry_statements'], 'phase'),
    ], [['inner-released', 'inner-released'], ['outer-before-rollback', 'outer-before-rollback'], ['after-outer-rollback', 'after-outer-rollback']]],
    'plan inner update selected ids' => [static fn (): mixed => $plan()['inner_released_statements'][0]['selected_ids'], [7, 8]],
    'plan inner delete selected ids' => [static fn (): mixed => $plan()['inner_released_statements'][1]['selected_ids'], [3, 4]],
    'plan inner released source deletes transients' => [static fn (): mixed => array_column($plan()['inner_released_current_source_tables']['wp_options'], 'option_id'), [1, 2, 5, 6, 7, 8, 9]],
    'plan inner released source row seven inner' => [static fn (): mixed => array_column($plan()['inner_released_current_source_tables']['wp_options'], 'option_name', 'option_id')[7], 'pending_theme:inner'],
    'plan inner released source row eight inner' => [static fn (): mixed => array_column($plan()['inner_released_current_source_tables']['wp_options'], 'status', 'option_id')[8], 'inner'],
    'plan outer update source rows read inner release' => [static fn (): mixed => array_column($plan()['outer_attempted_statements'][0]['source_rows'], 'option_name'), ['pending_theme:inner', 'rewrite_rules:inner']],
    'plan outer update returning ids' => [static fn (): mixed => array_column($plan()['outer_attempted_returning'][0]['rows'], 'option_id'), [7, 8]],
    'plan outer update predicate true' => [static fn (): mixed => $plan()['outer_attempted_returning'][0]['rows'][0]['pending_outer'], 1],
    'plan outer delete selected original siteurl and orphan' => [static fn (): mixed => $plan()['outer_attempted_statements'][1]['selected_ids'], [1, 9]],
    'plan outer delete sees inner released source ids' => [static fn (): mixed => array_column($plan()['outer_attempted_statements'][1]['source_rows'], 'option_id'), [1, 9]],
    'plan outer attempted current omits siteurl and orphan' => [static fn (): mixed => array_column($plan()['outer_attempted_current_source_tables']['wp_options'], 'option_id'), [2, 5, 6, 7, 8]],
    'plan outer attempted row seven outer' => [static fn (): mixed => array_column($plan()['outer_attempted_current_source_tables']['wp_options'], 'option_name', 'option_id')[7], 'pending_theme:inner:outer'],
    'plan rollback restores original ids' => [static fn (): mixed => array_column($plan()['rollback_to_outer_current_source_tables']['wp_options'], 'option_id'), [1, 2, 3, 4, 5, 6, 7, 8, 9]],
    'plan rollback restores row seven original name' => [static fn (): mixed => array_column($plan()['rollback_to_outer_current_source_tables']['wp_options'], 'option_name', 'option_id')[7], 'pending_theme'],
    'plan rollback restores transient feed' => [static fn (): mixed => array_column($plan()['rollback_to_outer_current_source_tables']['wp_options'], 'option_name', 'option_id')[3], '_transient_feed'],
    'plan discarded returning phases include released inner and outer' => [static fn (): mixed => array_column($plan()['discarded_returning'], 'phase'), ['inner-released', 'inner-released', 'outer-before-rollback', 'outer-before-rollback']],
    'plan discarded inner update ids' => [static fn (): mixed => array_column($plan()['discarded_returning'][0]['rows'], 'option_id'), [7, 8]],
    'plan discarded inner delete ids' => [static fn (): mixed => array_column($plan()['discarded_returning'][1]['rows'], 'option_id'), [3, 4]],
    'plan discarded outer update ids' => [static fn (): mixed => array_column($plan()['discarded_returning'][2]['rows'], 'option_id'), [7, 8]],
    'plan discarded outer delete ids' => [static fn (): mixed => array_column($plan()['discarded_returning'][3]['rows'], 'option_id'), [1, 9]],
    'plan discarded returning count eight' => [static fn (): mixed => $plan()['discarded_returning_count'], 8],
    'plan discarded changes before rollback eight' => [static fn (): mixed => $plan()['discarded_changes_before_outer_rollback_to'], 8],
    'plan retry source rows restored original names' => [static fn (): mixed => array_column($plan()['retry_statements'][0]['source_rows'], 'option_name'), ['pending_theme', 'rewrite_rules', 'orphaned_cache']],
    'plan retry update returning ids' => [static fn (): mixed => array_column($plan()['yielded_returning'][0]['rows'], 'option_id'), [7, 8, 9]],
    'plan retry row seven predicate true' => [static fn (): mixed => $plan()['yielded_returning'][0]['rows'][0]['pending_retry'], 1],
    'plan retry row eight value original plus retry' => [static fn (): mixed => $plan()['yielded_returning'][0]['rows'][1]['option_value'], 'rules:retry'],
    'plan retry row nine bytes original plus retry' => [static fn (): mixed => $plan()['yielded_returning'][0]['rows'][2]['bytes'], 105],
    'plan retry delete selected restored transient ids' => [static fn (): mixed => $plan()['retry_statements'][1]['selected_ids'], [3, 4]],
    'plan retry delete returning ids' => [static fn (): mixed => array_column($plan()['yielded_returning'][1]['rows'], 'option_id'), [3, 4]],
    'plan yielded returning count five' => [static fn (): mixed => $plan()['yielded_returning_count'], 5],
    'plan changes after retry five' => [static fn (): mixed => $plan()['changes_after_retry_release'], 5],
    'plan final source ids' => [static fn (): mixed => array_column($plan()['current_source_tables']['wp_options'], 'option_id'), [1, 2, 5, 6, 7, 8, 9]],
    'plan final row seven retry not inner' => [static fn (): mixed => array_column($plan()['current_source_tables']['wp_options'], 'option_name', 'option_id')[7], 'pending_theme:retry'],
    'plan final row eight retry from original' => [static fn (): mixed => array_column($plan()['current_source_tables']['wp_options'], 'option_value', 'option_id')[8], 'rules:retry'],
    'plan final row nine retry retained' => [static fn (): mixed => array_column($plan()['current_source_tables']['wp_options'], 'status', 'option_id')[9], 'retry'],
    'plan next source equals current' => [static fn (): mixed => $plan()['next_source_tables'], $plan()['current_source_tables']],
    'plan outer image equals original' => [static fn (): mixed => $plan()['outer_savepoint_image_tables'], $tables],
    'plan row count seven' => [static fn (): mixed => $plan()['row_counts']['wp_options'], 7],
    'plan changed tables after retry' => [static fn (): mixed => $plan()['changed_tables_after_retry'], ['wp_options']],
    'plan dependency inner release merge' => [static fn (): mixed => in_array('sqlite-release-inner-savepoint-merges-rowvalue-returning-into-outer-savepoint-nested-retry', $plan()['dependencies'], true), true],
    'plan dependency outer rollback discards released inner' => [static fn (): mixed => in_array('sqlite-rollback-to-outer-savepoint-discards-released-inner-returning-nested-retry', $plan()['dependencies'], true), true],
    'plan dependency retry original source' => [static fn (): mixed => in_array('sqlite-rowvalue-update-delete-retry-after-outer-rollback-reads-original-current-source-nested-retry', $plan()['dependencies'], true), true],

    'custom savepoints accepted' => [static fn (): mixed => [$customPlan()['outer_savepoint'], $customPlan()['inner_savepoint']], ['outer_custom_nested_retry', 'inner_custom_nested_retry']],
    'custom plan discards inner update and outer delete' => [static fn (): mixed => array_column($customPlan()['discarded_returning'], 'action'), ['update', 'delete']],
    'custom plan retry starts from original row seven' => [static fn (): mixed => array_column($customPlan()['retry_statements'][0]['source_rows'], 'option_name'), ['pending_theme', 'rewrite_rules', 'orphaned_cache']],
    'custom plan final row nine retry' => [static fn (): mixed => array_column($customPlan()['current_source_tables']['wp_options'], 'option_name', 'option_id')[9], 'orphaned_cache:retry'],

    'malformed empty inner statements rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningSavepointPlan::executeNestedRetrySavepointBatch($tables, [], [$outerUpdateSql], [$retryUpdateSql], $unique), InvalidArgumentException::class],
    'malformed empty outer statements rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningSavepointPlan::executeNestedRetrySavepointBatch($tables, [$innerUpdateSql], [], [$retryUpdateSql], $unique), InvalidArgumentException::class],
    'malformed empty retry statements rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningSavepointPlan::executeNestedRetrySavepointBatch($tables, [$innerUpdateSql], [$outerUpdateSql], [], $unique), InvalidArgumentException::class],
    'malformed empty unique rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningSavepointPlan::executeNestedRetrySavepointBatch($tables, [$innerUpdateSql], [$outerUpdateSql], [$retryUpdateSql], []), InvalidArgumentException::class],
    'malformed row list rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningSavepointPlan::executeNestedRetrySavepointBatch(['wp_options' => ['bad']], [$innerUpdateSql], [$outerUpdateSql], [$retryUpdateSql], $unique), InvalidArgumentException::class],
    'malformed same savepoint names rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningSavepointPlan::executeNestedRetrySavepointBatch($tables, [$innerUpdateSql], [$outerUpdateSql], [$retryUpdateSql], $unique, 'same', 'same'), InvalidArgumentException::class],
];

$tests = [];
foreach ($cases as $name => [$callback, $expected]) {
    $tests['rowvalue update delete returning savepoint current source nested retry ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        if (is_string($expected) && is_a($expected, Throwable::class, true)) {
            $t->throws($expected, $callback);
            return;
        }
        $t->same($expected, $callback());
    };
}

return $tests;
