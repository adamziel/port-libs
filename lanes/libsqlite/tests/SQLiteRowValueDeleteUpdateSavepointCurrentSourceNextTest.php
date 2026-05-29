<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteRowValueDeleteUpdateSavepointCurrentSourceNextPlan;
use PortLibs\LibSqlite\SQLiteUpdateDeleteReturningSql;

$rows = [
    ['option_id' => 1, 'blog_id' => 1, 'option_name' => 'siteurl', 'autoload' => 'yes', 'status' => 'live', 'bucket' => 'core', 'bytes' => 40, 'option_value' => 'https://old.test'],
    ['option_id' => 2, 'blog_id' => 1, 'option_name' => 'home', 'autoload' => 'yes', 'status' => 'live', 'bucket' => 'core', 'bytes' => 42, 'option_value' => 'https://old.test'],
    ['option_id' => 3, 'blog_id' => 1, 'option_name' => '_transient_feed', 'autoload' => 'no', 'status' => 'stale', 'bucket' => 'cache', 'bytes' => 12, 'option_value' => 'feed'],
    ['option_id' => 4, 'blog_id' => 1, 'option_name' => '_transient_timeout_feed', 'autoload' => 'no', 'status' => 'stale', 'bucket' => 'cache', 'bytes' => 13, 'option_value' => 'timeout'],
    ['option_id' => 5, 'blog_id' => 1, 'option_name' => '_site_transient_update_plugins', 'autoload' => 'no', 'status' => 'stale', 'bucket' => 'network-cache', 'bytes' => 80, 'option_value' => 'plugins'],
    ['option_id' => 6, 'blog_id' => 2, 'option_name' => '_transient_feed', 'autoload' => 'no', 'status' => 'stale', 'bucket' => 'cache', 'bytes' => 15, 'option_value' => 'network-feed'],
    ['option_id' => 7, 'blog_id' => 2, 'option_name' => 'siteurl', 'autoload' => 'yes', 'status' => 'live', 'bucket' => 'network', 'bytes' => 44, 'option_value' => 'https://network.test'],
    ['option_id' => 8, 'blog_id' => 2, 'option_name' => 'rewrite_rules', 'autoload' => 'yes', 'status' => 'queued', 'bucket' => 'rewrite', 'bytes' => 20, 'option_value' => 'rules'],
    ['option_id' => 9, 'blog_id' => 3, 'option_name' => 'orphaned_cache', 'autoload' => 'no', 'status' => 'staged', 'bucket' => 'cache', 'bytes' => 8, 'option_value' => 'orphan'],
];

$tables = ['wp_options' => $rows];
$unique = [['blog_id', 'option_name']];

$deleteBetweenSql = "DELETE FROM wp_options WHERE (blog_id, option_name) BETWEEN (1, '_transient_feed') AND (1, '_transient_timeout_feed') RETURNING option_id, option_name, (blog_id, option_name) BETWEEN (1, '_transient_feed') AND (1, '_transient_timeout_feed') AS in_cleanup_range ORDER BY option_id LIMIT 1";
$updateNotBetweenSql = "UPDATE wp_options SET (autoload, status, option_value, bytes) = ('yes', 'reviewed', option_name || ':reviewed', bytes + 5) WHERE (blog_id, option_name) NOT BETWEEN (1, '_transient_feed') AND (2, 'siteurl') RETURNING option_id, option_name, status, bytes, (blog_id, option_name) NOT BETWEEN (1, '_transient_feed') AND (2, 'siteurl') AS still_outside_range ORDER BY option_id";
$deleteAfterUpdateSql = "DELETE FROM wp_options WHERE (status, bucket) BETWEEN ('reviewed', 'cache') AND ('reviewed', 'rewrite') RETURNING option_id, option_name, (status, bucket) BETWEEN ('reviewed', 'cache') AND ('reviewed', 'rewrite') AS reviewed_bucket ORDER BY option_id";
$rollbackSql = "UPDATE wp_options SET (blog_id, option_name, status) = (1, 'siteurl', 'duplicate') WHERE option_id = 8 RETURNING option_id, blog_id, option_name, status";

$commitStatements = [$deleteBetweenSql, $updateNotBetweenSql, $deleteAfterUpdateSql];
$rollbackStatements = [$deleteBetweenSql, $updateNotBetweenSql, $rollbackSql];

$parsedDelete = static fn (): array => SQLiteUpdateDeleteReturningSql::parse($deleteBetweenSql);
$parsedUpdate = static fn (): array => SQLiteUpdateDeleteReturningSql::parse($updateNotBetweenSql);
$deleteOnly = static fn (): array => SQLiteUpdateDeleteReturningSql::execute($deleteBetweenSql, $tables);
$updateAfterDelete = static function () use ($deleteBetweenSql, $updateNotBetweenSql, $tables): array {
    $deleted = SQLiteUpdateDeleteReturningSql::execute($deleteBetweenSql, $tables);

    return SQLiteUpdateDeleteReturningSql::execute($updateNotBetweenSql, $deleted['tables']);
};
$commit = static fn (): array => SQLiteRowValueDeleteUpdateSavepointCurrentSourceNextPlan::executeBetweenCleanupSavepoint($tables, $commitStatements, $unique);
$rollback = static fn (): array => SQLiteRowValueDeleteUpdateSavepointCurrentSourceNextPlan::executeBetweenCleanupSavepoint($tables, $rollbackStatements, $unique);

$cases = [
    'delete parser preserves between predicate' => [static fn (): mixed => $parsedDelete()['where'], "(blog_id, option_name) BETWEEN (1, '_transient_feed') AND (1, '_transient_timeout_feed')"],
    'delete parser returning between expression' => [static fn (): mixed => $parsedDelete()['returning'], "option_id, option_name, (blog_id, option_name) BETWEEN (1, '_transient_feed') AND (1, '_transient_timeout_feed') AS in_cleanup_range"],
    'delete parser order column' => [static fn (): mixed => $parsedDelete()['order_by'][0]['column'], 'option_id'],
    'delete parser limit one' => [static fn (): mixed => $parsedDelete()['limit'], 1],
    'update parser preserves not between predicate' => [static fn (): mixed => $parsedUpdate()['where'], "(blog_id, option_name) NOT BETWEEN (1, '_transient_feed') AND (2, 'siteurl')"],
    'update parser assignment columns' => [static fn (): mixed => array_keys($parsedUpdate()['assignments']), ['autoload', 'status', 'option_value', 'bytes']],
    'update parser returning predicate expression' => [static fn (): mixed => $parsedUpdate()['returning'], "option_id, option_name, status, bytes, (blog_id, option_name) NOT BETWEEN (1, '_transient_feed') AND (2, 'siteurl') AS still_outside_range"],
    'delete only selects first transient range row' => [static fn (): mixed => $deleteOnly()['plan']->selectedIds, [3]],
    'delete only returning marks range true' => [static fn (): mixed => $deleteOnly()['returning'][0]['in_cleanup_range'], 1],
    'delete only removes row three from next source' => [static fn (): mixed => array_column($deleteOnly()['tables']['wp_options'], 'option_id'), [1, 2, 4, 5, 6, 7, 8, 9]],
    'update after delete sees row three gone' => [static fn (): mixed => $updateAfterDelete()['plan']->selectedIds, [5, 9]],
    'update after delete mutates in current source order' => [static fn (): mixed => $updateAfterDelete()['plan']->mutationIds, [5, 9]],
    'update after delete returning names outside range rows' => [static fn (): mixed => array_column($updateAfterDelete()['returning'], 'option_name'), ['_site_transient_update_plugins', 'orphaned_cache']],
    'update after delete returning predicates true' => [static fn (): mixed => array_column($updateAfterDelete()['returning'], 'still_outside_range'), [1, 1]],
    'update after delete increments bytes per source row' => [static fn (): mixed => array_column($updateAfterDelete()['returning'], 'bytes'), [85, 13]],
    'commit status released' => [static fn (): mixed => $commit()['status'], 'released'],
    'commit savepoint name' => [static fn (): mixed => $commit()['savepoint'], 'wp_options_rowvalue_between_cleanup'],
    'commit executed action chain' => [static fn (): mixed => array_column($commit()['executed_statements'], 'action'), ['delete', 'update', 'delete']],
    'commit first selected row three' => [static fn (): mixed => $commit()['executed_statements'][0]['selected_ids'], [3]],
    'commit second selected ids after delete' => [static fn (): mixed => $commit()['executed_statements'][1]['selected_ids'], [5, 9]],
    'commit second source before ids exclude row three' => [static fn (): mixed => $commit()['executed_statements'][1]['current_source_before_ids'], [1, 2, 4, 5, 6, 7, 8, 9]],
    'commit third delete sees reviewed rows' => [static fn (): mixed => $commit()['executed_statements'][2]['selected_ids'], [5, 9]],
    'commit third delete returning reviewed buckets true' => [static fn (): mixed => array_column($commit()['yielded_returning'][2]['rows'], 'reviewed_bucket'), [1, 1]],
    'commit yielded streams count' => [static fn (): mixed => count($commit()['yielded_returning']), 3],
    'commit deleted rows include original and reviewed cleanup' => [static fn (): mixed => array_column(array_column($commit()['deleted_rows'], 'row'), 'option_id'), [3, 5, 9]],
    'commit updated rows include network cache and orphan' => [static fn (): mixed => array_column(array_column($commit()['updated_rows'], 'row'), 'option_id'), [5, 9]],
    'commit final source ids' => [static fn (): mixed => array_column($commit()['current_source_tables']['wp_options'], 'option_id'), [1, 2, 4, 6, 7, 8]],
    'commit final row four remains stale timeout' => [static fn (): mixed => array_column($commit()['current_source_tables']['wp_options'], 'status', 'option_id')[4], 'stale'],
    'commit final network siteurl remains' => [static fn (): mixed => array_column($commit()['current_source_tables']['wp_options'], 'option_name', 'option_id')[7], 'siteurl'],
    'commit next source equals current source' => [static fn (): mixed => $commit()['next_source_tables'], $commit()['current_source_tables']],
    'commit changes include delete update delete' => [static fn (): mixed => $commit()['changes'], 5],
    'commit attempted changes equals changes' => [static fn (): mixed => $commit()['attempted_changes'], 5],
    'commit changed table recorded' => [static fn (): mixed => $commit()['changed_tables'], ['wp_options']],
    'commit dependency records between cleanup cluster' => [static fn (): mixed => in_array('sqlite-row-value-between-delete-update-current-source', $commit()['dependencies'], true), true],
    'rollback status rolls back' => [static fn (): mixed => $rollback()['status'], 'rolled-back-to-savepoint'],
    'rollback flag true' => [static fn (): mixed => $rollback()['rolled_back'], true],
    'rollback statement ordinal failing update' => [static fn (): mixed => $rollback()['rollback_statement_ordinal'], 2],
    'rollback reason names duplicate siteurl' => [static fn (): mixed => $rollback()['rollback_reason'], 'SQLite UPDATE RETURNING unique constraint failed: blog_id,option_name=1|siteurl using OR ABORT'],
    'rollback yielded only pre-failure streams' => [static fn (): mixed => array_column($rollback()['yielded_returning'], 'action'), ['delete', 'update']],
    'rollback attempted returning includes prior streams' => [static fn (): mixed => array_column($rollback()['attempted_returning'], 'action'), ['delete', 'update']],
    'rollback current source restores original ids' => [static fn (): mixed => array_column($rollback()['current_source_tables']['wp_options'], 'option_id'), [1, 2, 3, 4, 5, 6, 7, 8, 9]],
    'rollback current source restores row three' => [static fn (): mixed => array_column($rollback()['current_source_tables']['wp_options'], 'option_name', 'option_id')[3], '_transient_feed'],
    'rollback current source restores row eight status' => [static fn (): mixed => array_column($rollback()['current_source_tables']['wp_options'], 'status', 'option_id')[8], 'queued'],
    'rollback next source retains attempted delete' => [static fn (): mixed => in_array(3, array_column($rollback()['next_source_tables']['wp_options'], 'option_id'), true), false],
    'rollback next source leaves in-range row eight queued before failing statement' => [static fn (): mixed => array_column($rollback()['next_source_tables']['wp_options'], 'status', 'option_id')[8], 'queued'],
    'rollback changes reset to zero' => [static fn (): mixed => $rollback()['changes'], 0],
    'rollback attempted changes include successful statements only' => [static fn (): mixed => $rollback()['attempted_changes'], 3],
    'rollback savepoint image equals input tables' => [static fn (): mixed => $rollback()['savepoint_image_tables'], $tables],
    'malformed empty statements rejected' => [static fn (): mixed => SQLiteRowValueDeleteUpdateSavepointCurrentSourceNextPlan::executeBetweenCleanupSavepoint($tables, [], $unique), InvalidArgumentException::class],
    'malformed empty unique constraints rejected' => [static fn (): mixed => SQLiteRowValueDeleteUpdateSavepointCurrentSourceNextPlan::executeBetweenCleanupSavepoint($tables, [$deleteBetweenSql], []), InvalidArgumentException::class],
    'malformed between arity rejected' => [static fn (): mixed => SQLiteUpdateDeleteReturningSql::execute("DELETE FROM wp_options WHERE (blog_id, option_name) BETWEEN (1) AND (2) RETURNING option_id", $tables), InvalidArgumentException::class],
    'malformed row list rejected' => [static fn (): mixed => SQLiteRowValueDeleteUpdateSavepointCurrentSourceNextPlan::executeBetweenCleanupSavepoint(['wp_options' => ['bad']], [$deleteBetweenSql], $unique), InvalidArgumentException::class],
];

$tests = [];
foreach ($cases as $name => [$callback, $expected]) {
    $tests['rowvalue delete update savepoint current source between cleanup ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        if (is_string($expected) && is_a($expected, Throwable::class, true)) {
            $t->throws($expected, $callback);
            return;
        }
        $t->same($expected, $callback());
    };
}

return $tests;
