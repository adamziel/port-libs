<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteRowValueDeleteReturningSavepointCurrentSourceNextPlan;
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
    ['option_id' => 10, 'blog_id' => 3, 'option_name' => 'rewrite_rules', 'autoload' => 'yes', 'status' => null, 'bucket' => 'rewrite', 'bytes' => 21, 'option_value' => 'rules3'],
];

$tables = ['wp_options' => $rows];
$unique = [['blog_id', 'option_name']];

$releaseCacheSql = "DELETE FROM wp_options WHERE (blog_id, option_name) IN ((1, '_transient_feed'), (1, '_transient_timeout_feed')) RETURNING option_id, blog_id, option_name, (blog_id, option_name) IN ((1, '_transient_feed'), (1, '_transient_timeout_feed')) AS released_match ORDER BY option_id";
$releaseNetworkSql = "DELETE FROM wp_options WHERE (blog_id, option_name) = (1, '_site_transient_update_plugins') RETURNING option_id, option_name, (blog_id, option_name) = (1, '_site_transient_update_plugins') AS network_match";
$rollbackDeleteSql = "DELETE FROM wp_options WHERE (blog_id, option_name) BETWEEN (2, '_transient_feed') AND (3, 'rewrite_rules') RETURNING option_id, blog_id, option_name, (blog_id, option_name) BETWEEN (2, '_transient_feed') AND (3, 'rewrite_rules') AS rollback_match ORDER BY option_id LIMIT 2";
$failingDeleteSql = "DELETE FROM wp_options WHERE (blog_id, option_name) IN ((2)) RETURNING option_id";
$commitDeleteSql = "DELETE FROM wp_options WHERE (status, bucket) IS (NULL, 'rewrite') RETURNING option_id, option_name, (status, bucket) IS (NULL, 'rewrite') AS null_rewrite";

$releaseStatements = [$releaseCacheSql, $releaseNetworkSql];
$rollbackStatements = [$rollbackDeleteSql, $failingDeleteSql];
$commitRollbackStatements = [$rollbackDeleteSql, $commitDeleteSql];

$parsedRelease = static fn (): array => SQLiteUpdateDeleteReturningSql::parse($releaseCacheSql);
$parsedRollback = static fn (): array => SQLiteUpdateDeleteReturningSql::parse($rollbackDeleteSql);
$releaseOnly = static fn (): array => SQLiteUpdateDeleteReturningSql::execute($releaseCacheSql, $tables, 'option_id', $unique);
$releaseThenNetwork = static function () use ($releaseCacheSql, $releaseNetworkSql, $tables, $unique): array {
    $first = SQLiteUpdateDeleteReturningSql::execute($releaseCacheSql, $tables, 'option_id', $unique);

    return SQLiteUpdateDeleteReturningSql::execute($releaseNetworkSql, $first['tables'], 'option_id', $unique);
};
$rollbackDeleteOnly = static function () use ($releaseStatements, $rollbackDeleteSql, $tables, $unique): array {
    $released = SQLiteRowValueDeleteReturningSavepointCurrentSourceNextPlan::execute(
        $tables,
        $releaseStatements,
        [$rollbackDeleteSql],
        $unique,
        'app_settings_delete_returning_outer',
        'app_settings_delete_returning_released',
        'app_settings_delete_returning_rollback',
        'option_id',
    );

    return $released;
};
$rollbackPlan = static fn (): array => SQLiteRowValueDeleteReturningSavepointCurrentSourceNextPlan::execute(
    $tables,
    $releaseStatements,
    $rollbackStatements,
    $unique,
    'app_settings_delete_returning_outer',
    'app_settings_delete_returning_released',
    'app_settings_delete_returning_rollback',
    'option_id',
);
$commitPlan = static fn (): array => SQLiteRowValueDeleteReturningSavepointCurrentSourceNextPlan::execute(
    $tables,
    $releaseStatements,
    $commitRollbackStatements,
    $unique,
    'app_settings_delete_returning_outer',
    'app_settings_delete_returning_released',
    'app_settings_delete_returning_rollback',
    'option_id',
);

$cases = [
    'release parser where row-value in retained' => [static fn (): mixed => $parsedRelease()['where'], "(blog_id, option_name) IN ((1, '_transient_feed'), (1, '_transient_timeout_feed'))"],
    'release parser returning row-value expression retained' => [static fn (): mixed => $parsedRelease()['returning'], "option_id, blog_id, option_name, (blog_id, option_name) IN ((1, '_transient_feed'), (1, '_transient_timeout_feed')) AS released_match"],
    'release parser order by option id' => [static fn (): mixed => $parsedRelease()['order_by'][0]['column'], 'option_id'],
    'release parser no limit' => [static fn (): mixed => $parsedRelease()['limit'], null],
    'rollback parser between retained' => [static fn (): mixed => $parsedRollback()['where'], "(blog_id, option_name) BETWEEN (2, '_transient_feed') AND (3, 'rewrite_rules')"],
    'rollback parser returning expression retained' => [static fn (): mixed => $parsedRollback()['returning'], "option_id, blog_id, option_name, (blog_id, option_name) BETWEEN (2, '_transient_feed') AND (3, 'rewrite_rules') AS rollback_match"],
    'rollback parser limit two' => [static fn (): mixed => $parsedRollback()['limit'], 2],
    'release only selected cache rows' => [static fn (): mixed => $releaseOnly()['plan']->selectedIds, [3, 4]],
    'release only mutation rows' => [static fn (): mixed => $releaseOnly()['plan']->mutationIds, [3, 4]],
    'release only returning match flags' => [static fn (): mixed => array_column($releaseOnly()['returning'], 'released_match'), [1, 1]],
    'release only next ids omit cache rows' => [static fn (): mixed => array_column($releaseOnly()['tables']['wp_options'], 'option_id'), [1, 2, 5, 6, 7, 8, 9, 10]],
    'release then network deletes site transient' => [static fn (): mixed => $releaseThenNetwork()['plan']->selectedIds, [5]],
    'release then network next ids omit three four five' => [static fn (): mixed => array_column($releaseThenNetwork()['tables']['wp_options'], 'option_id'), [1, 2, 6, 7, 8, 9, 10]],
    'release then network returning true' => [static fn (): mixed => $releaseThenNetwork()['returning'][0]['network_match'], 1],

    'rollback status rolls back inner savepoint' => [static fn (): mixed => $rollbackPlan()['status'], 'rollback-savepoint-rolled-back'],
    'rollback flag true' => [static fn (): mixed => $rollbackPlan()['rolled_back'], true],
    'rollback statement ordinal is malformed delete' => [static fn (): mixed => $rollbackPlan()['rollback_statement_ordinal'], 1],
    'rollback reason is row value arity mismatch' => [static fn (): mixed => $rollbackPlan()['rollback_reason'], 'SQLite UPDATE/DELETE row-value expressions need at least two values'],
    'rollback released savepoint name' => [static fn (): mixed => $rollbackPlan()['released_savepoint'], 'app_settings_delete_returning_released'],
    'rollback savepoint name' => [static fn (): mixed => $rollbackPlan()['rollback_savepoint'], 'app_settings_delete_returning_rollback'],
    'rollback executed release statements count' => [static fn (): mixed => count($rollbackPlan()['released_executed_statements']), 2],
    'rollback executed rollback statements count before failure' => [static fn (): mixed => count($rollbackPlan()['rollback_executed_statements']), 1],
    'rollback all executed statements count' => [static fn (): mixed => count($rollbackPlan()['executed_statements']), 3],
    'rollback release phase names' => [static fn (): mixed => array_column($rollbackPlan()['released_executed_statements'], 'phase'), ['released', 'released']],
    'rollback inner phase name' => [static fn (): mixed => $rollbackPlan()['rollback_executed_statements'][0]['phase'], 'rollback'],
    'rollback yielded streams only released' => [static fn (): mixed => array_column($rollbackPlan()['yielded_returning'], 'phase'), ['released', 'released']],
    'rollback attempted stream includes inner delete' => [static fn (): mixed => array_column($rollbackPlan()['rollback_attempted_returning'], 'phase'), ['rollback']],
    'rollback released rows first stream ids' => [static fn (): mixed => array_column($rollbackPlan()['released_returning'][0]['rows'], 'option_id'), [3, 4]],
    'rollback released rows second stream ids' => [static fn (): mixed => array_column($rollbackPlan()['released_returning'][1]['rows'], 'option_id'), [5]],
    'rollback attempted inner row ids' => [static fn (): mixed => array_column($rollbackPlan()['rollback_attempted_returning'][0]['rows'], 'option_id'), [6, 7]],
    'rollback current ids preserve released delete' => [static fn (): mixed => array_column($rollbackPlan()['current_source_tables']['wp_options'], 'option_id'), [1, 2, 6, 7, 8, 9, 10]],
    'rollback current restores inner row six' => [static fn (): mixed => array_column($rollbackPlan()['current_source_tables']['wp_options'], 'option_name', 'option_id')[6], '_transient_feed'],
    'rollback current restores inner row seven' => [static fn (): mixed => array_column($rollbackPlan()['current_source_tables']['wp_options'], 'option_name', 'option_id')[7], 'siteurl'],
    'rollback current keeps released row three deleted' => [static fn (): mixed => in_array(3, array_column($rollbackPlan()['current_source_tables']['wp_options'], 'option_id'), true), false],
    'rollback current keeps released row five deleted' => [static fn (): mixed => in_array(5, array_column($rollbackPlan()['current_source_tables']['wp_options'], 'option_id'), true), false],
    'rollback next source has attempted inner delete' => [static fn (): mixed => array_column($rollbackPlan()['next_source_tables']['wp_options'], 'option_id'), [1, 2, 8, 9, 10]],
    'rollback released image equals rollback image' => [static fn (): mixed => $rollbackPlan()['released_source_tables'], $rollbackPlan()['rollback_image_tables']],
    'rollback image excludes released deleted ids' => [static fn (): mixed => array_column($rollbackPlan()['rollback_image_tables']['wp_options'], 'option_id'), [1, 2, 6, 7, 8, 9, 10]],
    'rollback outer image remains original' => [static fn (): mixed => array_column($rollbackPlan()['outer_image_tables']['wp_options'], 'option_id'), range(1, 10)],
    'rollback deleted rows only released after rollback' => [static fn (): mixed => array_column(array_column($rollbackPlan()['deleted_rows'], 'row'), 'option_id'), [3, 4, 5]],
    'rollback attempted inner deleted rows recorded separately' => [static fn (): mixed => array_column(array_column($rollbackPlan()['attempted_rollback_deleted_rows'], 'row'), 'option_id'), [6, 7]],
    'rollback committed changes are released only' => [static fn (): mixed => $rollbackPlan()['changes'], 3],
    'rollback attempted changes include inner delete' => [static fn (): mixed => $rollbackPlan()['attempted_changes'], 5],
    'rollback release changes three' => [static fn (): mixed => $rollbackPlan()['released_changes'], 3],
    'rollback inner attempted changes two' => [static fn (): mixed => $rollbackPlan()['rollback_attempted_changes'], 2],
    'rollback changed table current' => [static fn (): mixed => $rollbackPlan()['changed_tables'], ['wp_options']],
    'rollback changed table attempted' => [static fn (): mixed => $rollbackPlan()['attempted_changed_tables'], ['wp_options']],
    'rollback dependency marks delete returning' => [static fn (): mixed => in_array('sqlite-delete-returning-row-value-current-source', $rollbackPlan()['dependencies'], true), true],
    'rollback dependency marks released survives' => [static fn (): mixed => in_array('sqlite-released-savepoint-delete-survives-inner-rollback', $rollbackPlan()['dependencies'], true), true],

    'single rollback delete can release when no failure' => [static fn (): mixed => $rollbackDeleteOnly()['status'], 'released'],
    'single rollback delete yields release plus rollback streams' => [static fn (): mixed => array_column($rollbackDeleteOnly()['yielded_returning'], 'phase'), ['released', 'released', 'rollback']],
    'single rollback delete current ids omit inner rows' => [static fn (): mixed => array_column($rollbackDeleteOnly()['current_source_tables']['wp_options'], 'option_id'), [1, 2, 8, 9, 10]],

    'commit status released' => [static fn (): mixed => $commitPlan()['status'], 'released'],
    'commit does not roll back' => [static fn (): mixed => $commitPlan()['rolled_back'], false],
    'commit yielded phases include rollback savepoint statements' => [static fn (): mixed => array_column($commitPlan()['yielded_returning'], 'phase'), ['released', 'released', 'rollback', 'rollback']],
    'commit final ids omit released and inner deletes' => [static fn (): mixed => array_column($commitPlan()['current_source_tables']['wp_options'], 'option_id'), [1, 2, 8, 9]],
    'commit deleted rows include null rewrite' => [static fn (): mixed => array_column(array_column($commitPlan()['deleted_rows'], 'row'), 'option_id'), [3, 4, 5, 6, 7, 10]],
    'commit rollback deleted rows exposed' => [static fn (): mixed => array_column(array_column($commitPlan()['rollback_deleted_rows'], 'row'), 'option_id'), [6, 7, 10]],
    'commit changes include all deletes' => [static fn (): mixed => $commitPlan()['changes'], 6],
    'commit attempted changes equal changes' => [static fn (): mixed => $commitPlan()['attempted_changes'], 6],
    'commit current equals next source' => [static fn (): mixed => $commitPlan()['current_source_tables'], $commitPlan()['next_source_tables']],
    'commit null row-value returning true' => [static fn (): mixed => $commitPlan()['yielded_returning'][3]['rows'][0]['null_rewrite'], 1],

    'malformed empty released statements rejected' => [static fn (): mixed => SQLiteRowValueDeleteReturningSavepointCurrentSourceNextPlan::execute($tables, [], $rollbackStatements, $unique), InvalidArgumentException::class],
    'malformed empty rollback statements rejected' => [static fn (): mixed => SQLiteRowValueDeleteReturningSavepointCurrentSourceNextPlan::execute($tables, $releaseStatements, [], $unique), InvalidArgumentException::class],
    'malformed empty unique constraints rejected' => [static fn (): mixed => SQLiteRowValueDeleteReturningSavepointCurrentSourceNextPlan::execute($tables, $releaseStatements, $rollbackStatements, []), InvalidArgumentException::class],
    'malformed row list rejected' => [static fn (): mixed => SQLiteRowValueDeleteReturningSavepointCurrentSourceNextPlan::execute(['wp_options' => ['bad']], $releaseStatements, $rollbackStatements, $unique, 'app_settings_delete_returning_outer', 'app_settings_delete_returning_released', 'app_settings_delete_returning_rollback', 'option_id'), InvalidArgumentException::class],
    'malformed update statement rejected in delete-only plan' => [static fn (): mixed => SQLiteRowValueDeleteReturningSavepointCurrentSourceNextPlan::execute($tables, ["UPDATE wp_options SET status = 'bad' WHERE option_id = 1 RETURNING option_id"], $rollbackStatements, $unique, 'app_settings_delete_returning_outer', 'app_settings_delete_returning_released', 'app_settings_delete_returning_rollback', 'option_id'), InvalidArgumentException::class],
];

$tests = [];
foreach ($cases as $name => [$callback, $expected]) {
    $tests['rowvalue delete returning savepoint current source ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        if (is_string($expected) && is_a($expected, Throwable::class, true)) {
            $t->throws($expected, $callback);
            return;
        }
        $t->same($expected, $callback());
    };
}

return $tests;
