<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteRowValueNestedSavepointReturningCurrentSourceNextPlan;
use PortLibs\LibSqlite\SQLiteUpdateDeleteReturningSql;

$rows = [
    ['option_id' => 1, 'blog_id' => 1, 'option_name' => 'siteurl', 'autoload' => 'yes', 'status' => 'live', 'bytes' => 20, 'option_value' => 'https://site.test'],
    ['option_id' => 2, 'blog_id' => 1, 'option_name' => 'home', 'autoload' => 'yes', 'status' => 'live', 'bytes' => 21, 'option_value' => 'https://home.test'],
    ['option_id' => 3, 'blog_id' => 1, 'option_name' => '_transient_feed', 'autoload' => 'no', 'status' => 'stale', 'bytes' => 12, 'option_value' => 'feed'],
    ['option_id' => 4, 'blog_id' => 1, 'option_name' => '_transient_timeout_feed', 'autoload' => 'no', 'status' => 'stale', 'bytes' => 13, 'option_value' => 'timeout'],
    ['option_id' => 5, 'blog_id' => 2, 'option_name' => 'active_plugins', 'autoload' => 'yes', 'status' => 'live', 'bytes' => 30, 'option_value' => 'a:1:{}'],
    ['option_id' => 6, 'blog_id' => 2, 'option_name' => 'template', 'autoload' => 'yes', 'status' => 'live', 'bytes' => 31, 'option_value' => 'twentysixteen'],
    ['option_id' => 7, 'blog_id' => 2, 'option_name' => 'theme_mods', 'autoload' => 'yes', 'status' => null, 'bytes' => 32, 'option_value' => 'mods'],
    ['option_id' => 8, 'blog_id' => 3, 'option_name' => 'rewrite_rules', 'autoload' => 'yes', 'status' => 'queued', 'bytes' => 14, 'option_value' => 'rules'],
    ['option_id' => 9, 'blog_id' => 3, 'option_name' => 'plugin_batch', 'autoload' => 'no', 'status' => 'queued', 'bytes' => 15, 'option_value' => 'plugin'],
];

$tables = ['wp_options' => $rows];
$unique = [['blog_id', 'option_name']];

$innerUpdateSql = "UPDATE wp_options SET (status, option_value, bytes) = ('inner-release', option_value || ':inner', bytes + 100) WHERE (blog_id, option_name) BETWEEN (2, 'active_plugins') AND (2, 'zzzz') RETURNING option_id, blog_id, option_name, status, option_value, bytes, (blog_id, option_name) BETWEEN (2, 'active_plugins') AND (2, 'zzzz') AS inner_range ORDER BY option_id";
$innerDeleteSql = "DELETE FROM wp_options WHERE (blog_id, option_name) IN ((1, '_transient_feed'), (1, '_transient_timeout_feed')) RETURNING option_id, blog_id, option_name, (blog_id, option_name) IN ((1, '_transient_feed'), (1, '_transient_timeout_feed')) AS inner_delete_match ORDER BY option_id";
$outerDeleteSql = "DELETE FROM wp_options WHERE (blog_id, option_name) = (3, 'rewrite_rules') RETURNING option_id, blog_id, option_name, (blog_id, option_name) IS (3, 'rewrite_rules') AS outer_delete_match ORDER BY option_id";
$retryUpdateSql = "UPDATE wp_options SET (status, option_value, bytes) = ('retry-after-outer', option_value || ':retry', bytes + 1) WHERE (blog_id, status) IS NOT DISTINCT FROM (3, 'queued') RETURNING option_id, blog_id, option_name, status, option_value, bytes, (blog_id, status) IS (3, 'retry-after-outer') AS retry_tuple ORDER BY option_id";
$retryDeleteSql = "DELETE FROM wp_options WHERE (blog_id, option_name) IN ((1, '_transient_feed'), (1, '_transient_timeout_feed')) RETURNING option_id, option_name, (blog_id, option_name) IS DISTINCT FROM (1, 'siteurl') AS retry_delete_match ORDER BY option_id LIMIT 1";

$parsedInner = static fn (): array => SQLiteUpdateDeleteReturningSql::parse($innerUpdateSql);
$innerUpdateOnly = static fn (): array => SQLiteUpdateDeleteReturningSql::execute($innerUpdateSql, $tables, 'option_id', $unique);
$innerReleasedOnly = static function () use ($innerUpdateSql, $innerDeleteSql, $tables, $unique): array {
    $updated = SQLiteUpdateDeleteReturningSql::execute($innerUpdateSql, $tables, 'option_id', $unique);

    return SQLiteUpdateDeleteReturningSql::execute($innerDeleteSql, $updated['tables'], 'option_id', $unique);
};
$plan = static fn (): array => SQLiteRowValueNestedSavepointReturningCurrentSourceNextPlan::execute(
    $tables,
    [$innerUpdateSql, $innerDeleteSql],
    [$outerDeleteSql],
    [$retryUpdateSql, $retryDeleteSql],
    $unique,
);
$customPlan = static fn (): array => SQLiteRowValueNestedSavepointReturningCurrentSourceNextPlan::execute(
    $tables,
    [$innerUpdateSql],
    [$outerDeleteSql],
    [$retryUpdateSql],
    $unique,
    'wp_outer_custom_next175',
    'wp_inner_custom_next175',
);

$cases = [
    'parse inner row-value between predicate' => [static fn (): mixed => $parsedInner()['where'], "(blog_id, option_name) BETWEEN (2, 'active_plugins') AND (2, 'zzzz')"],
    'parse inner row-value assignment columns' => [static fn (): mixed => array_keys($parsedInner()['assignments']), ['status', 'option_value', 'bytes']],
    'parse inner returning range alias retained' => [static fn (): mixed => str_contains($parsedInner()['returning'], 'AS inner_range'), true],
    'inner update only selected ids' => [static fn (): mixed => $innerUpdateOnly()['plan']->selectedIds, [5, 6, 7]],
    'inner update only returning ids' => [static fn (): mixed => array_column($innerUpdateOnly()['returning'], 'option_id'), [5, 6, 7]],
    'inner update only range flags true' => [static fn (): mixed => array_column($innerUpdateOnly()['returning'], 'inner_range'), [1, 1, 1]],
    'inner update only row seven status from null' => [static fn (): mixed => array_column($innerUpdateOnly()['tables']['wp_options'], 'status', 'option_id')[7], 'inner-release'],
    'inner update only row five bytes advanced' => [static fn (): mixed => array_column($innerUpdateOnly()['returning'], 'bytes'), [130, 131, 132]],
    'inner released only deletes transient ids' => [static fn (): mixed => $innerReleasedOnly()['plan']->selectedIds, [3, 4]],
    'inner released only current ids omit transients' => [static fn (): mixed => array_column($innerReleasedOnly()['tables']['wp_options'], 'option_id'), [1, 2, 5, 6, 7, 8, 9]],

    'plan status' => [static fn (): mixed => $plan()['status'], 'nested-release-rolled-back-retried-current-source-next175'],
    'plan outer savepoint' => [static fn (): mixed => $plan()['outer_savepoint'], 'wp_outer_import_next175'],
    'plan inner savepoint' => [static fn (): mixed => $plan()['inner_savepoint'], 'wp_inner_plugin_next175'],
    'plan inner released into outer' => [static fn (): mixed => $plan()['inner_released_into_outer'], true],
    'plan rolled back to outer' => [static fn (): mixed => $plan()['rolled_back_to_outer_savepoint'], true],
    'plan inner inactive after release' => [static fn (): mixed => $plan()['inner_savepoint_no_longer_active_after_release'], true],
    'plan outer preserved after rollback' => [static fn (): mixed => $plan()['outer_savepoint_preserved_after_rollback_to'], true],
    'plan released outer after retry' => [static fn (): mixed => $plan()['released_outer_after_retry'], true],
    'plan inner actions update delete' => [static fn (): mixed => array_column($plan()['inner_statements'], 'action'), ['update', 'delete']],
    'plan outer action delete' => [static fn (): mixed => array_column($plan()['outer_statements'], 'action'), ['delete']],
    'plan retry actions update delete' => [static fn (): mixed => array_column($plan()['retry_statements'], 'action'), ['update', 'delete']],
    'plan inner phases before release' => [static fn (): mixed => array_column($plan()['inner_statements'], 'phase'), ['inner-before-release', 'inner-before-release']],
    'plan outer phase after release' => [static fn (): mixed => $plan()['outer_statements'][0]['phase'], 'outer-after-inner-release-before-rollback'],
    'plan retry phases after rollback' => [static fn (): mixed => array_column($plan()['retry_statements'], 'phase'), ['after-outer-rollback-to', 'after-outer-rollback-to']],
    'plan inner update selected ids' => [static fn (): mixed => $plan()['inner_statements'][0]['selected_ids'], [5, 6, 7]],
    'plan inner delete selected ids' => [static fn (): mixed => $plan()['inner_statements'][1]['selected_ids'], [3, 4]],
    'plan outer delete sees inner released source ids' => [static fn (): mixed => $plan()['outer_statements'][0]['source_rows'][0]['option_id'], 8],
    'plan outer delete selected id' => [static fn (): mixed => $plan()['outer_statements'][0]['selected_ids'], [8]],
    'plan inner released current row seven changed' => [static fn (): mixed => array_column($plan()['inner_release_current_source_tables']['wp_options'], 'status', 'option_id')[7], 'inner-release'],
    'plan inner released current omits transients' => [static fn (): mixed => array_column($plan()['inner_release_current_source_tables']['wp_options'], 'option_id'), [1, 2, 5, 6, 7, 8, 9]],
    'plan outer attempt current omits rewrite row too' => [static fn (): mixed => array_column($plan()['outer_attempt_current_source_tables']['wp_options'], 'option_id'), [1, 2, 5, 6, 7, 9]],
    'plan rollback restores original ids' => [static fn (): mixed => array_column($plan()['rollback_to_outer_current_source_tables']['wp_options'], 'option_id'), [1, 2, 3, 4, 5, 6, 7, 8, 9]],
    'plan rollback restores row seven null status' => [static fn (): mixed => array_column($plan()['rollback_to_outer_current_source_tables']['wp_options'], 'status', 'option_id')[7], null],
    'plan rollback restores deleted transient four' => [static fn (): mixed => array_column($plan()['rollback_to_outer_current_source_tables']['wp_options'], 'option_name', 'option_id')[4], '_transient_timeout_feed'],
    'plan rollback restores rewrite row eight' => [static fn (): mixed => array_column($plan()['rollback_to_outer_current_source_tables']['wp_options'], 'option_name', 'option_id')[8], 'rewrite_rules'],
    'plan discarded returning phases' => [static fn (): mixed => array_column($plan()['discarded_by_outer_rollback_returning'], 'phase'), ['inner-before-release', 'inner-before-release', 'outer-after-inner-release-before-rollback']],
    'plan discarded inner update ids' => [static fn (): mixed => array_column($plan()['discarded_by_outer_rollback_returning'][0]['rows'], 'option_id'), [5, 6, 7]],
    'plan discarded inner delete ids' => [static fn (): mixed => array_column($plan()['discarded_by_outer_rollback_returning'][1]['rows'], 'option_id'), [3, 4]],
    'plan discarded outer delete id' => [static fn (): mixed => array_column($plan()['discarded_by_outer_rollback_returning'][2]['rows'], 'option_id'), [8]],
    'plan inner released returning count five' => [static fn (): mixed => $plan()['inner_released_returning_count'], 5],
    'plan outer attempt returning count one' => [static fn (): mixed => $plan()['outer_attempt_returning_count'], 1],
    'plan discarded count six' => [static fn (): mixed => $plan()['discarded_by_outer_rollback_count'], 6],
    'plan retry update source restored queued rows' => [static fn (): mixed => array_column($plan()['retry_statements'][0]['source_rows'], 'option_value'), ['rules', 'plugin']],
    'plan retry update selected ids' => [static fn (): mixed => $plan()['retry_statements'][0]['selected_ids'], [8, 9]],
    'plan retry update returning ids' => [static fn (): mixed => array_column($plan()['yielded_after_retry_returning'][0]['rows'], 'option_id'), [8, 9]],
    'plan retry update tuple flags true' => [static fn (): mixed => array_column($plan()['yielded_after_retry_returning'][0]['rows'], 'retry_tuple'), [1, 1]],
    'plan retry update bytes from original' => [static fn (): mixed => array_column($plan()['yielded_after_retry_returning'][0]['rows'], 'bytes'), [15, 16]],
    'plan retry delete selected restored transient' => [static fn (): mixed => $plan()['retry_statements'][1]['selected_ids'], [3]],
    'plan retry delete returning id' => [static fn (): mixed => array_column($plan()['yielded_after_retry_returning'][1]['rows'], 'option_id'), [3]],
    'plan retry delete match true' => [static fn (): mixed => $plan()['yielded_after_retry_returning'][1]['rows'][0]['retry_delete_match'], 1],
    'plan yielded after retry count three' => [static fn (): mixed => $plan()['yielded_after_retry_count'], 3],
    'plan attempted changes before rollback six' => [static fn (): mixed => $plan()['attempted_changes_before_outer_rollback'], 6],
    'plan changes after retry release three' => [static fn (): mixed => $plan()['changes_after_retry_release'], 3],
    'plan final ids omit one transient only' => [static fn (): mixed => array_column($plan()['current_source_tables']['wp_options'], 'option_id'), [1, 2, 4, 5, 6, 7, 8, 9]],
    'plan final row eight retry status' => [static fn (): mixed => array_column($plan()['current_source_tables']['wp_options'], 'status', 'option_id')[8], 'retry-after-outer'],
    'plan final row nine value retry' => [static fn (): mixed => array_column($plan()['current_source_tables']['wp_options'], 'option_value', 'option_id')[9], 'plugin:retry'],
    'plan final row seven remains original null' => [static fn (): mixed => array_column($plan()['current_source_tables']['wp_options'], 'status', 'option_id')[7], null],
    'plan final transient timeout survives limit one' => [static fn (): mixed => array_column($plan()['current_source_tables']['wp_options'], 'option_name', 'option_id')[4], '_transient_timeout_feed'],
    'plan next source equals current' => [static fn (): mixed => $plan()['next_source_tables'], $plan()['current_source_tables']],
    'plan row count eight' => [static fn (): mixed => $plan()['row_counts']['wp_options'], 8],
    'plan changed tables wp options' => [static fn (): mixed => $plan()['changed_tables_after_retry'], ['wp_options']],
    'plan savepoint image unchanged' => [static fn (): mixed => $plan()['outer_savepoint_image_tables'], $tables],
    'plan dependency release nested' => [static fn (): mixed => in_array('sqlite-release-nested-savepoint-merges-rowvalue-returning-next175', $plan()['dependencies'], true), true],
    'plan dependency outer rollback discards inner' => [static fn (): mixed => in_array('sqlite-rollback-to-outer-discards-released-inner-returning-next175', $plan()['dependencies'], true), true],
    'plan dependency retry current source' => [static fn (): mixed => in_array('sqlite-rowvalue-update-delete-returning-retry-after-nested-rollback-current-source-next175', $plan()['dependencies'], true), true],
    'custom outer savepoint accepted' => [static fn (): mixed => $customPlan()['outer_savepoint'], 'wp_outer_custom_next175'],
    'custom inner savepoint accepted' => [static fn (): mixed => $customPlan()['inner_savepoint'], 'wp_inner_custom_next175'],
    'custom plan yielded retry count' => [static fn (): mixed => $customPlan()['yielded_after_retry_count'], 2],
    'custom plan final retains transients without retry delete' => [static fn (): mixed => array_column($customPlan()['current_source_tables']['wp_options'], 'option_id'), [1, 2, 3, 4, 5, 6, 7, 8, 9]],
    'malformed empty inner rejected' => [static fn (): mixed => SQLiteRowValueNestedSavepointReturningCurrentSourceNextPlan::execute($tables, [], [$outerDeleteSql], [$retryUpdateSql], $unique), InvalidArgumentException::class],
    'malformed empty outer rejected' => [static fn (): mixed => SQLiteRowValueNestedSavepointReturningCurrentSourceNextPlan::execute($tables, [$innerUpdateSql], [], [$retryUpdateSql], $unique), InvalidArgumentException::class],
    'malformed empty retry rejected' => [static fn (): mixed => SQLiteRowValueNestedSavepointReturningCurrentSourceNextPlan::execute($tables, [$innerUpdateSql], [$outerDeleteSql], [], $unique), InvalidArgumentException::class],
    'malformed empty unique rejected' => [static fn (): mixed => SQLiteRowValueNestedSavepointReturningCurrentSourceNextPlan::execute($tables, [$innerUpdateSql], [$outerDeleteSql], [$retryUpdateSql], []), InvalidArgumentException::class],
    'malformed outer savepoint rejected' => [static fn (): mixed => SQLiteRowValueNestedSavepointReturningCurrentSourceNextPlan::execute($tables, [$innerUpdateSql], [$outerDeleteSql], [$retryUpdateSql], $unique, 'bad-name'), InvalidArgumentException::class],
    'malformed duplicate savepoint rejected' => [static fn (): mixed => SQLiteRowValueNestedSavepointReturningCurrentSourceNextPlan::execute($tables, [$innerUpdateSql], [$outerDeleteSql], [$retryUpdateSql], $unique, 'same_name', 'same_name'), InvalidArgumentException::class],
    'malformed row list rejected' => [static fn (): mixed => SQLiteRowValueNestedSavepointReturningCurrentSourceNextPlan::execute(['wp_options' => ['bad']], [$innerUpdateSql], [$outerDeleteSql], [$retryUpdateSql], $unique), InvalidArgumentException::class],
];

$tests = [];
foreach ($cases as $name => [$callback, $expected]) {
    $tests['rowvalue nested savepoint returning current source next175 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        if (is_string($expected) && is_a($expected, Throwable::class, true)) {
            $t->throws($expected, $callback);
            return;
        }
        $t->same($expected, $callback());
    };
}

return $tests;
