<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNextPlan;
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
    ['option_id' => 9, 'blog_id' => 3, 'option_name' => '_site_transient_update_plugins', 'autoload' => 'no', 'status' => 'stale', 'bytes' => 15, 'option_value' => 'updates'],
];

$tables = ['wp_options' => $rows];
$unique = [['blog_id', 'option_name']];

$yieldUpdateSql = "UPDATE wp_options SET (status, option_value, bytes) = ('yielded', option_value || ':yielded', bytes + 10) WHERE (blog_id, option_name) BETWEEN (2, 'active_plugins') AND (2, 'zzzz') RETURNING option_id, blog_id, option_name, status, option_value, bytes, (blog_id, option_name) BETWEEN (2, 'active_plugins') AND (2, 'zzzz') AS yielded_range ORDER BY option_id";
$discardDeleteSql = "DELETE FROM wp_options WHERE (blog_id, option_name) IN ((1, '_transient_feed'), (1, '_transient_timeout_feed'), (3, '_site_transient_update_plugins')) RETURNING option_id, blog_id, option_name, (blog_id, option_name) IN ((1, '_transient_feed'), (1, '_transient_timeout_feed'), (3, '_site_transient_update_plugins')) AS delete_tuple_match ORDER BY option_id";
$discardUpdateSql = "UPDATE wp_options SET (status, option_value, bytes) = ('discarded', option_value || ':discarded', bytes + 20) WHERE (blog_id, option_name) NOT BETWEEN (2, 'active_plugins') AND (2, 'zzzz') RETURNING option_id, blog_id, option_name, status, bytes, (blog_id, option_name) NOT BETWEEN (2, 'active_plugins') AND (2, 'zzzz') AS outside_yield_range ORDER BY option_id";
$retryDeleteSql = "DELETE FROM wp_options WHERE (blog_id, option_name) IN ((1, '_transient_feed'), (1, '_transient_timeout_feed'), (3, '_site_transient_update_plugins')) RETURNING option_id, blog_id, option_name, (blog_id, option_name) IN ((1, '_transient_feed'), (1, '_transient_timeout_feed'), (3, '_site_transient_update_plugins')) AS retry_delete_match ORDER BY option_id";
$retryUpdateSql = "UPDATE wp_options SET (status, option_value, bytes) = ('retry', option_value || ':retry', bytes + 5) WHERE (blog_id, option_name) BETWEEN (2, 'active_plugins') AND (2, 'zzzz') RETURNING option_id, blog_id, option_name, status, option_value, bytes, (blog_id, option_name) BETWEEN (2, 'active_plugins') AND (2, 'zzzz') AS retry_range ORDER BY option_id";

$parsedYield = static fn (): array => SQLiteUpdateDeleteReturningSql::parse($yieldUpdateSql);
$yieldOnly = static fn (): array => SQLiteUpdateDeleteReturningSql::execute($yieldUpdateSql, $tables, 'option_id', $unique);
$discardAfterYield = static function () use ($yieldUpdateSql, $discardDeleteSql, $discardUpdateSql, $tables, $unique): array {
    $yielded = SQLiteUpdateDeleteReturningSql::execute($yieldUpdateSql, $tables, 'option_id', $unique);
    $deleted = SQLiteUpdateDeleteReturningSql::execute($discardDeleteSql, $yielded['tables'], 'option_id', $unique);

    return SQLiteUpdateDeleteReturningSql::execute($discardUpdateSql, $deleted['tables'], 'option_id', $unique);
};
$plan = static fn (): array => SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNextPlan::executeYieldCheckpointSavepointBatch(
    $tables,
    [$yieldUpdateSql],
    [$discardDeleteSql, $discardUpdateSql],
    [$retryDeleteSql, $retryUpdateSql],
    $unique,
);
$customPlan = static fn (): array => SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNextPlan::executeYieldCheckpointSavepointBatch(
    $tables,
    [$yieldUpdateSql],
    [$discardDeleteSql],
    [$retryUpdateSql],
    $unique,
    'wp_custom_yield_retry_next172',
);

$cases = [
    'parse yielded where row-value between retained' => [static fn (): mixed => $parsedYield()['where'], "(blog_id, option_name) BETWEEN (2, 'active_plugins') AND (2, 'zzzz')"],
    'parse yielded returning range alias retained' => [static fn (): mixed => str_contains($parsedYield()['returning'], 'AS yielded_range'), true],
    'parse yielded order by option id' => [static fn (): mixed => $parsedYield()['order_by'][0]['column'], 'option_id'],
    'yield only selected plugin option ids' => [static fn (): mixed => $yieldOnly()['plan']->selectedIds, [5, 6, 7]],
    'yield only mutation ids source order' => [static fn (): mixed => $yieldOnly()['plan']->mutationIds, [5, 6, 7]],
    'yield only returning ids' => [static fn (): mixed => array_column($yieldOnly()['returning'], 'option_id'), [5, 6, 7]],
    'yield only returning range flags true' => [static fn (): mixed => array_column($yieldOnly()['returning'], 'yielded_range'), [1, 1, 1]],
    'yield only row seven yielded from null status' => [static fn (): mixed => array_column($yieldOnly()['tables']['wp_options'], 'status', 'option_id')[7], 'yielded'],
    'yield only row six value yielded' => [static fn (): mixed => array_column($yieldOnly()['tables']['wp_options'], 'option_value', 'option_id')[6], 'twentysixteen:yielded'],
    'yield only row five bytes advanced' => [static fn (): mixed => array_column($yieldOnly()['tables']['wp_options'], 'bytes', 'option_id')[5], 40],
    'discard after yield final ids before rollback' => [static fn (): mixed => array_column($discardAfterYield()['tables']['wp_options'], 'option_id'), [1, 2, 5, 6, 7, 8]],
    'discard after yield outside range ids' => [static fn (): mixed => $discardAfterYield()['plan']->selectedIds, [1, 2, 8]],
    'discard after yield outside range flags true' => [static fn (): mixed => array_column($discardAfterYield()['returning'], 'outside_yield_range'), [1, 1, 1]],

    'plan status' => [static fn (): mixed => $plan()['status'], 'yielded-rowvalue-returning-stream-rolled-back-and-retried'],
    'plan savepoint' => [static fn (): mixed => $plan()['savepoint'], 'wp_rowvalue_yield_retry_next172'],
    'plan returning observable before rollback' => [static fn (): mixed => $plan()['returning_stream_was_observable_before_rollback'], true],
    'plan observable returning not durable' => [static fn (): mixed => $plan()['observable_returning_is_not_durable_after_rollback_to'], true],
    'plan rolled back to savepoint' => [static fn (): mixed => $plan()['rolled_back_to_savepoint'], true],
    'plan savepoint preserved' => [static fn (): mixed => $plan()['savepoint_preserved_after_rollback_to'], true],
    'plan released after retry' => [static fn (): mixed => $plan()['released_after_retry'], true],
    'plan yielded action update' => [static fn (): mixed => array_column($plan()['yielded_before_rollback_statements'], 'action'), ['update']],
    'plan discarded actions delete update' => [static fn (): mixed => array_column($plan()['discarded_before_rollback_statements'], 'action'), ['delete', 'update']],
    'plan retry actions delete update' => [static fn (): mixed => array_column($plan()['retry_statements'], 'action'), ['delete', 'update']],
    'plan yielded phase' => [static fn (): mixed => $plan()['yielded_before_rollback_statements'][0]['phase'], 'yielded-before-rollback'],
    'plan discarded phases' => [static fn (): mixed => array_column($plan()['discarded_before_rollback_statements'], 'phase'), ['discarded-before-rollback', 'discarded-before-rollback']],
    'plan retry phases' => [static fn (): mixed => array_column($plan()['retry_statements'], 'phase'), ['after-rollback-to', 'after-rollback-to']],
    'plan yielded selected ids' => [static fn (): mixed => $plan()['yielded_before_rollback_statements'][0]['selected_ids'], [5, 6, 7]],
    'plan yielded delivered ids' => [static fn (): mixed => array_column($plan()['delivered_before_rollback_returning'][0]['rows'], 'option_id'), [5, 6, 7]],
    'plan yielded delivered statuses' => [static fn (): mixed => array_column($plan()['delivered_before_rollback_returning'][0]['rows'], 'status'), ['yielded', 'yielded', 'yielded']],
    'plan yielded current row seven status yielded' => [static fn (): mixed => array_column($plan()['yielded_attempt_current_source_tables']['wp_options'], 'status', 'option_id')[7], 'yielded'],
    'plan discard delete selected ids' => [static fn (): mixed => $plan()['discarded_before_rollback_statements'][0]['selected_ids'], [3, 4, 9]],
    'plan discard update selected ids after delete' => [static fn (): mixed => $plan()['discarded_before_rollback_statements'][1]['selected_ids'], [1, 2, 8]],
    'plan discard attempted omits deleted ids' => [static fn (): mixed => array_column($plan()['discard_attempt_current_source_tables']['wp_options'], 'option_id'), [1, 2, 5, 6, 7, 8]],
    'plan discard attempted row one discarded' => [static fn (): mixed => array_column($plan()['discard_attempt_current_source_tables']['wp_options'], 'status', 'option_id')[1], 'discarded'],
    'plan rollback restores all ids' => [static fn (): mixed => array_column($plan()['rollback_to_current_source_tables']['wp_options'], 'option_id'), [1, 2, 3, 4, 5, 6, 7, 8, 9]],
    'plan rollback restores yielded row seven null' => [static fn (): mixed => array_column($plan()['rollback_to_current_source_tables']['wp_options'], 'status', 'option_id')[7], null],
    'plan rollback restores deleted transient' => [static fn (): mixed => array_column($plan()['rollback_to_current_source_tables']['wp_options'], 'option_name', 'option_id')[4], '_transient_timeout_feed'],
    'plan retry delete source rows original statuses' => [static fn (): mixed => array_column($plan()['retry_statements'][0]['source_rows'], 'status'), ['stale', 'stale', 'stale']],
    'plan retry delete ids restored' => [static fn (): mixed => $plan()['retry_statements'][0]['selected_ids'], [3, 4, 9]],
    'plan retry delete returning flags true' => [static fn (): mixed => array_column($plan()['yielded_after_retry_returning'][0]['rows'], 'retry_delete_match'), [1, 1, 1]],
    'plan retry update selected ids after delete' => [static fn (): mixed => $plan()['retry_statements'][1]['selected_ids'], [5, 6, 7]],
    'plan retry update source rows restored values' => [static fn (): mixed => array_column($plan()['retry_statements'][1]['source_rows'], 'option_value'), ['a:1:{}', 'twentysixteen', 'mods']],
    'plan retry update returning ids' => [static fn (): mixed => array_column($plan()['yielded_after_retry_returning'][1]['rows'], 'option_id'), [5, 6, 7]],
    'plan retry update returning statuses' => [static fn (): mixed => array_column($plan()['yielded_after_retry_returning'][1]['rows'], 'status'), ['retry', 'retry', 'retry']],
    'plan retry update range flags true' => [static fn (): mixed => array_column($plan()['yielded_after_retry_returning'][1]['rows'], 'retry_range'), [1, 1, 1]],
    'plan retry row five bytes from original' => [static fn (): mixed => $plan()['yielded_after_retry_returning'][1]['rows'][0]['bytes'], 35],
    'plan retry row six value from original' => [static fn (): mixed => $plan()['yielded_after_retry_returning'][1]['rows'][1]['option_value'], 'twentysixteen:retry'],
    'plan final ids after retry delete' => [static fn (): mixed => array_column($plan()['current_source_tables']['wp_options'], 'option_id'), [1, 2, 5, 6, 7, 8]],
    'plan final row seven retry status' => [static fn (): mixed => array_column($plan()['current_source_tables']['wp_options'], 'status', 'option_id')[7], 'retry'],
    'plan final row five retry value' => [static fn (): mixed => array_column($plan()['current_source_tables']['wp_options'], 'option_value', 'option_id')[5], 'a:1:{}:retry'],
    'plan final row eight unchanged queued' => [static fn (): mixed => array_column($plan()['current_source_tables']['wp_options'], 'status', 'option_id')[8], 'queued'],
    'plan next source equals current' => [static fn (): mixed => $plan()['next_source_tables'], $plan()['current_source_tables']],
    'plan savepoint image unchanged' => [static fn (): mixed => $plan()['savepoint_image_tables'], $tables],
    'plan delivered before rollback count' => [static fn (): mixed => $plan()['delivered_before_rollback_count'], 3],
    'plan discarded before rollback count' => [static fn (): mixed => $plan()['discarded_before_rollback_count'], 6],
    'plan suppressed by rollback count' => [static fn (): mixed => $plan()['suppressed_by_rollback_count'], 9],
    'plan yielded after retry count' => [static fn (): mixed => $plan()['yielded_after_retry_count'], 6],
    'plan attempted changes before rollback' => [static fn (): mixed => $plan()['attempted_changes_before_rollback_to'], 9],
    'plan changes after retry release' => [static fn (): mixed => $plan()['changes_after_retry_release'], 6],
    'plan row count after retry' => [static fn (): mixed => $plan()['row_counts']['wp_options'], 6],
    'plan changed tables after retry' => [static fn (): mixed => $plan()['changed_tables_after_retry'], ['wp_options']],
    'plan suppressed phases include yielded and discarded' => [static fn (): mixed => array_column($plan()['suppressed_by_rollback_returning'], 'phase'), ['yielded-before-rollback', 'discarded-before-rollback', 'discarded-before-rollback']],
    'plan suppressed yielded ids retained as non durable stream' => [static fn (): mixed => array_column($plan()['suppressed_by_rollback_returning'][0]['rows'], 'option_id'), [5, 6, 7]],
    'plan suppressed delete ids retained as non durable stream' => [static fn (): mixed => array_column($plan()['suppressed_by_rollback_returning'][1]['rows'], 'option_id'), [3, 4, 9]],
    'plan suppressed discarded update ids retained as non durable stream' => [static fn (): mixed => array_column($plan()['suppressed_by_rollback_returning'][2]['rows'], 'option_id'), [1, 2, 8]],
    'plan dependency yielded stream' => [static fn (): mixed => in_array('sqlite-rowvalue-returning-yield-before-savepoint-rollback-next172', $plan()['dependencies'], true), true],
    'plan dependency suppress durability' => [static fn (): mixed => in_array('sqlite-rollback-to-suppresses-yielded-returning-durability-next172', $plan()['dependencies'], true), true],
    'plan dependency retry current source' => [static fn (): mixed => in_array('sqlite-rowvalue-update-delete-retry-current-source-after-yield-rollback-next172', $plan()['dependencies'], true), true],
    'custom savepoint accepted' => [static fn (): mixed => $customPlan()['savepoint'], 'wp_custom_yield_retry_next172'],
    'custom plan yielded after retry count' => [static fn (): mixed => $customPlan()['yielded_after_retry_count'], 3],
    'custom plan final retains deleted transients without retry delete' => [static fn (): mixed => array_column($customPlan()['current_source_tables']['wp_options'], 'option_id'), [1, 2, 3, 4, 5, 6, 7, 8, 9]],
    'malformed empty yielded statements rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNextPlan::executeYieldCheckpointSavepointBatch($tables, [], [$discardDeleteSql], [$retryUpdateSql], $unique), InvalidArgumentException::class],
    'malformed empty discarded statements rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNextPlan::executeYieldCheckpointSavepointBatch($tables, [$yieldUpdateSql], [], [$retryUpdateSql], $unique), InvalidArgumentException::class],
    'malformed empty retry statements rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNextPlan::executeYieldCheckpointSavepointBatch($tables, [$yieldUpdateSql], [$discardDeleteSql], [], $unique), InvalidArgumentException::class],
    'malformed empty unique constraints rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNextPlan::executeYieldCheckpointSavepointBatch($tables, [$yieldUpdateSql], [$discardDeleteSql], [$retryUpdateSql], []), InvalidArgumentException::class],
    'malformed bad savepoint rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNextPlan::executeYieldCheckpointSavepointBatch($tables, [$yieldUpdateSql], [$discardDeleteSql], [$retryUpdateSql], $unique, 'bad-name'), InvalidArgumentException::class],
    'malformed row list rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNextPlan::executeYieldCheckpointSavepointBatch(['wp_options' => ['bad']], [$yieldUpdateSql], [$discardDeleteSql], [$retryUpdateSql], $unique), InvalidArgumentException::class],
];

$tests = [];
foreach ($cases as $name => [$callback, $expected]) {
    $tests['rowvalue update delete returning savepoint current source next172 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        if (is_string($expected) && is_a($expected, Throwable::class, true)) {
            $t->throws($expected, $callback);
            return;
        }
        $t->same($expected, $callback());
    };
}

return $tests;
