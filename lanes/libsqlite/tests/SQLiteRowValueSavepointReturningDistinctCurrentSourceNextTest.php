<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteRowValueSavepointReturningDistinctCurrentSourceNextPlan;
use PortLibs\LibSqlite\SQLiteUpdateDeleteReturningSql;

$rows = [
    ['option_id' => 1, 'blog_id' => 1, 'option_name' => 'siteurl', 'autoload' => 'yes', 'status' => 'live', 'bucket' => 'core', 'revision' => 1, 'bytes' => 40, 'option_value' => 'https://old.test'],
    ['option_id' => 2, 'blog_id' => 1, 'option_name' => 'home', 'autoload' => 'yes', 'status' => 'live', 'bucket' => 'core', 'revision' => 1, 'bytes' => 41, 'option_value' => 'https://home.test'],
    ['option_id' => 3, 'blog_id' => 1, 'option_name' => '_transient_feed', 'autoload' => 'no', 'status' => 'stale', 'bucket' => 'cache', 'revision' => 2, 'bytes' => 12, 'option_value' => 'feed'],
    ['option_id' => 4, 'blog_id' => 1, 'option_name' => '_transient_timeout_feed', 'autoload' => 'no', 'status' => 'stale', 'bucket' => 'cache', 'revision' => 2, 'bytes' => 13, 'option_value' => 'timeout'],
    ['option_id' => 5, 'blog_id' => 2, 'option_name' => 'siteurl', 'autoload' => 'yes', 'status' => 'live', 'bucket' => 'network', 'revision' => 1, 'bytes' => 44, 'option_value' => 'https://network.test'],
    ['option_id' => 6, 'blog_id' => 2, 'option_name' => 'home', 'autoload' => 'yes', 'status' => 'live', 'bucket' => 'network', 'revision' => 1, 'bytes' => 45, 'option_value' => 'https://network-home.test'],
    ['option_id' => 7, 'blog_id' => 2, 'option_name' => '_transient_feed', 'autoload' => 'no', 'status' => 'stale', 'bucket' => 'cache', 'revision' => 2, 'bytes' => 15, 'option_value' => 'network-feed'],
    ['option_id' => 8, 'blog_id' => 3, 'option_name' => 'rewrite_rules', 'autoload' => 'yes', 'status' => null, 'bucket' => 'rewrite', 'revision' => null, 'bytes' => 20, 'option_value' => 'rules'],
    ['option_id' => 9, 'blog_id' => 3, 'option_name' => 'orphaned_cache', 'autoload' => 'no', 'status' => 'queued', 'bucket' => 'cache', 'revision' => null, 'bytes' => 8, 'option_value' => 'orphan'],
    ['option_id' => 10, 'blog_id' => 4, 'option_name' => 'theme_mods', 'autoload' => 'yes', 'status' => 'queued', 'bucket' => 'theme', 'revision' => 3, 'bytes' => 30, 'option_value' => 'theme'],
];

$tables = ['wp_options' => $rows];
$unique = [['blog_id', 'option_name']];
$distinct = ['status', 'bucket'];

$releaseSql = "UPDATE wp_options SET (status, bucket, option_value, bytes) = ('reviewed', 'cache', option_value || ':reviewed', bytes + 1) WHERE (status, bucket) IS NOT DISTINCT FROM ('stale', 'cache') RETURNING option_id, blog_id, option_name, status, bucket, (status, bucket) IS NOT DISTINCT FROM ('reviewed', 'cache') AS reviewed_cache ORDER BY option_id";
$rollbackSql = "UPDATE wp_options SET (status, bucket, option_value, bytes) = ('retry', 'cache', option_value || ':attempt', bytes + 2) WHERE (status, bucket) IS DISTINCT FROM ('live', 'core') RETURNING option_id, blog_id, option_name, status, bucket, (status, bucket) IS DISTINCT FROM ('live', 'core') AS not_core ORDER BY option_id";
$failingSql = "DELETE FROM wp_options WHERE (status) IS DISTINCT FROM ('live') RETURNING option_id";
$retrySql = "UPDATE wp_options SET (status, bucket, option_value, bytes) = ('retry', 'cache', option_value || ':retry', bytes + 5) WHERE (status, bucket) IS DISTINCT FROM ('live', 'core') RETURNING option_id, blog_id, option_name, status, bucket, (status, bucket) IS DISTINCT FROM ('live', 'core') AS not_core ORDER BY option_id LIMIT 4";
$deleteRetrySql = "DELETE FROM wp_options WHERE (status, bucket) IS NOT DISTINCT FROM ('retry', 'cache') RETURNING option_id, blog_id, option_name, status, bucket ORDER BY option_id LIMIT 1";

$parsedRelease = static fn (): array => SQLiteUpdateDeleteReturningSql::parse($releaseSql);
$parsedRollback = static fn (): array => SQLiteUpdateDeleteReturningSql::parse($rollbackSql);
$releaseOnly = static fn (): array => SQLiteUpdateDeleteReturningSql::execute($releaseSql, $tables, 'option_id', $unique);
$rollbackOnly = static function () use ($releaseSql, $rollbackSql, $tables, $unique): array {
    $released = SQLiteUpdateDeleteReturningSql::execute($releaseSql, $tables, 'option_id', $unique);

    return SQLiteUpdateDeleteReturningSql::execute($rollbackSql, $released['tables'], 'option_id', $unique);
};
$plan = static fn (): array => SQLiteRowValueSavepointReturningDistinctCurrentSourceNextPlan::execute(
    $tables,
    [$releaseSql],
    [$rollbackSql, $failingSql],
    [$retrySql, $deleteRetrySql],
    $unique,
    $distinct,
);
$cleanPlan = static fn (): array => SQLiteRowValueSavepointReturningDistinctCurrentSourceNextPlan::execute(
    $tables,
    [$releaseSql],
    [$rollbackSql],
    [$retrySql],
    $unique,
    $distinct,
);

$cases = [
    'release parser retains is not distinct where' => [static fn (): mixed => $parsedRelease()['where'], "(status, bucket) IS NOT DISTINCT FROM ('stale', 'cache')"],
    'release parser retains row-value returning expression' => [static fn (): mixed => $parsedRelease()['returning'], "option_id, blog_id, option_name, status, bucket, (status, bucket) IS NOT DISTINCT FROM ('reviewed', 'cache') AS reviewed_cache"],
    'release parser row-value assignment columns' => [static fn (): mixed => array_keys($parsedRelease()['assignments']), ['status', 'bucket', 'option_value', 'bytes']],
    'rollback parser retains is distinct where' => [static fn (): mixed => $parsedRollback()['where'], "(status, bucket) IS DISTINCT FROM ('live', 'core')"],
    'rollback parser returning expression' => [static fn (): mixed => $parsedRollback()['returning'], "option_id, blog_id, option_name, status, bucket, (status, bucket) IS DISTINCT FROM ('live', 'core') AS not_core"],
    'release only selects stale cache rows' => [static fn (): mixed => $releaseOnly()['plan']->selectedIds, [3, 4, 7]],
    'release only returns reviewed cache flags' => [static fn (): mixed => array_column($releaseOnly()['returning'], 'reviewed_cache'), [1, 1, 1]],
    'release only mutates current source row three' => [static fn (): mixed => array_column($releaseOnly()['tables']['wp_options'], 'status', 'option_id')[3], 'reviewed'],
    'release only preserves row eight null state' => [static fn (): mixed => array_column($releaseOnly()['tables']['wp_options'], 'status', 'option_id')[8], null],
    'rollback only selects current-source non-core rows after release' => [static fn (): mixed => $rollbackOnly()['plan']->selectedIds, [3, 4, 5, 6, 7, 8, 9, 10]],
    'rollback only returns duplicate retry cache rows' => [static fn (): mixed => array_column($rollbackOnly()['returning'], 'status'), ['retry', 'retry', 'retry', 'retry', 'retry', 'retry', 'retry', 'retry']],
    'rollback only current source has attempted retry row five' => [static fn (): mixed => array_column($rollbackOnly()['tables']['wp_options'], 'option_value', 'option_id')[5], 'https://network.test:attempt'],

    'plan status rolled back distinct returning retried' => [static fn (): mixed => $plan()['status'], 'rolled-back-distinct-returning-retried'],
    'plan rolled back flag true' => [static fn (): mixed => $plan()['rolled_back'], true],
    'plan savepoint name' => [static fn (): mixed => $plan()['savepoint'], 'app_settings_rowvalue_distinct_current_source'],
    'plan rollback reason records malformed row-value arity' => [static fn (): mixed => $plan()['rollback_reason'], 'SQLite UPDATE/DELETE row-value expressions need at least two columns'],
    'plan rollback statement ordinal one' => [static fn (): mixed => $plan()['rollback_statement_ordinal'], 1],
    'plan distinct columns surfaced' => [static fn (): mixed => $plan()['distinct_columns'], ['status', 'bucket']],
    'plan released statement count' => [static fn (): mixed => count($plan()['released_executed_statements']), 1],
    'plan rollback executed count before failure' => [static fn (): mixed => count($plan()['rollback_executed_statements']), 1],
    'plan retry executed count' => [static fn (): mixed => count($plan()['retry_executed_statements']), 2],
    'plan released selected ids' => [static fn (): mixed => $plan()['released_executed_statements'][0]['selected_ids'], [3, 4, 7]],
    'plan rollback attempted selected ids from released current source' => [static fn (): mixed => $plan()['rollback_executed_statements'][0]['selected_ids'], [3, 4, 5, 6, 7, 8, 9, 10]],
    'plan retry selected ids from rollback image' => [static fn (): mixed => $plan()['retry_executed_statements'][0]['selected_ids'], [3, 4, 5, 6]],
    'plan retry delete selected first retry cache' => [static fn (): mixed => $plan()['retry_executed_statements'][1]['selected_ids'], [3]],
    'plan released distinct count collapses reviewed cache duplicates' => [static fn (): mixed => $plan()['released_returning'][0]['distinct_count'], 1],
    'plan released duplicate count' => [static fn (): mixed => $plan()['released_returning'][0]['duplicate_count'], 2],
    'plan rollback attempted distinct count' => [static fn (): mixed => $plan()['rollback_attempted_returning'][0]['distinct_count'], 1],
    'plan rollback attempted duplicate count' => [static fn (): mixed => $plan()['rollback_attempted_returning'][0]['duplicate_count'], 7],
    'plan retry update distinct count' => [static fn (): mixed => $plan()['retry_returning'][0]['distinct_count'], 1],
    'plan retry update duplicate count' => [static fn (): mixed => $plan()['retry_returning'][0]['duplicate_count'], 3],
    'plan retry delete distinct count' => [static fn (): mixed => $plan()['retry_returning'][1]['distinct_count'], 1],
    'plan yielded phases skip rolled-back attempted stream' => [static fn (): mixed => array_column($plan()['yielded_returning'], 'phase'), ['released', 'retry', 'retry']],
    'plan attempted phases include rollback stream' => [static fn (): mixed => array_column($plan()['attempted_returning_before_rollback'], 'phase'), ['released', 'rollback']],
    'plan released current source retains reviewed rows' => [static fn (): mixed => array_column($plan()['released_current_source_tables']['wp_options'], 'status', 'option_id')[7], 'reviewed'],
    'plan attempted current source has retry row eight' => [static fn (): mixed => array_column($plan()['attempted_current_source_tables']['wp_options'], 'status', 'option_id')[8], 'retry'],
    'plan retry source restores row eight null status' => [static fn (): mixed => array_column($plan()['retry_source_tables']['wp_options'], 'status', 'option_id')[8], null],
    'plan retry source equals rollback image' => [static fn (): mixed => $plan()['retry_source_tables'], $plan()['rollback_image_tables']],
    'plan current source deletes retry row three only' => [static fn (): mixed => array_column($plan()['current_source_tables']['wp_options'], 'option_id'), [1, 2, 4, 5, 6, 7, 8, 9, 10]],
    'plan current row four retry value from restored source' => [static fn (): mixed => array_column($plan()['current_source_tables']['wp_options'], 'option_value', 'option_id')[4], 'timeout:reviewed:retry'],
    'plan current row five retry value no attempted suffix' => [static fn (): mixed => array_column($plan()['current_source_tables']['wp_options'], 'option_value', 'option_id')[5], 'https://network.test:retry'],
    'plan yielded distinct keys' => [static fn (): mixed => $plan()['yielded_distinct_keys'], ['string:reviewed|string:cache', 'string:retry|string:cache', 'string:retry|string:cache']],
    'plan attempted distinct keys include rollback attempted' => [static fn (): mixed => $plan()['attempted_distinct_keys'], ['string:reviewed|string:cache', 'string:retry|string:cache']],
    'plan discarded distinct row is attempted retry row three' => [static fn (): mixed => $plan()['discarded_distinct_rows'][0]['row']['option_id'], 3],
    'plan duplicate rows include released and retry duplicates' => [static fn (): mixed => count($plan()['duplicate_returning_rows']), 12],
    'plan changes exclude rolled-back attempted update' => [static fn (): mixed => $plan()['changes'], 8],
    'plan attempted changes before rollback include attempted update' => [static fn (): mixed => $plan()['attempted_changes_before_rollback'], 11],
    'plan row count after retry delete' => [static fn (): mixed => $plan()['row_counts']['wp_options'], 9],
    'plan dependency marks row-value distinct' => [static fn (): mixed => in_array('sqlite-row-value-is-distinct-from-current-source', $plan()['dependencies'], true), true],
    'plan dependency marks returning stream distinct' => [static fn (): mixed => in_array('sqlite-returning-distinct-savepoint-stream', $plan()['dependencies'], true), true],
    'plan non overlap names prior accepted rollback surfaces' => [static fn (): mixed => str_contains($plan()['non_overlap'], 'conflict-retry') && str_contains($plan()['non_overlap'], 'DELETE-only rollback'), true],

    'clean plan status released distinct returning retried' => [static fn (): mixed => $cleanPlan()['status'], 'released-distinct-returning-retried'],
    'clean plan not rolled back' => [static fn (): mixed => $cleanPlan()['rolled_back'], false],
    'clean plan retry source equals attempted current source' => [static fn (): mixed => $cleanPlan()['retry_source_tables'], $cleanPlan()['attempted_current_source_tables']],
    'clean plan yielded phases include rollback stream' => [static fn (): mixed => array_column($cleanPlan()['yielded_returning'], 'phase'), ['released', 'rollback', 'retry']],
    'clean plan current row three gets second retry suffix' => [static fn (): mixed => array_column($cleanPlan()['current_source_tables']['wp_options'], 'option_value', 'option_id')[3], 'feed:reviewed:attempt:retry'],
    'custom savepoint accepted' => [static fn (): mixed => SQLiteRowValueSavepointReturningDistinctCurrentSourceNextPlan::execute($tables, [$releaseSql], [$rollbackSql], [$retrySql], $unique, $distinct, 'wp_distinct_custom')['savepoint'], 'wp_distinct_custom'],
    'malformed empty released statements rejected' => [static fn (): mixed => SQLiteRowValueSavepointReturningDistinctCurrentSourceNextPlan::execute($tables, [], [$rollbackSql], [$retrySql], $unique, $distinct), InvalidArgumentException::class],
    'malformed empty rollback statements rejected' => [static fn (): mixed => SQLiteRowValueSavepointReturningDistinctCurrentSourceNextPlan::execute($tables, [$releaseSql], [], [$retrySql], $unique, $distinct), InvalidArgumentException::class],
    'malformed empty retry statements rejected' => [static fn (): mixed => SQLiteRowValueSavepointReturningDistinctCurrentSourceNextPlan::execute($tables, [$releaseSql], [$rollbackSql], [], $unique, $distinct), InvalidArgumentException::class],
    'malformed empty unique constraints rejected' => [static fn (): mixed => SQLiteRowValueSavepointReturningDistinctCurrentSourceNextPlan::execute($tables, [$releaseSql], [$rollbackSql], [$retrySql], [], $distinct), InvalidArgumentException::class],
    'malformed empty distinct columns rejected' => [static fn (): mixed => SQLiteRowValueSavepointReturningDistinctCurrentSourceNextPlan::execute($tables, [$releaseSql], [$rollbackSql], [$retrySql], $unique, []), InvalidArgumentException::class],
    'malformed row list rejected' => [static fn (): mixed => SQLiteRowValueSavepointReturningDistinctCurrentSourceNextPlan::execute(['wp_options' => ['bad']], [$releaseSql], [$rollbackSql], [$retrySql], $unique, $distinct), InvalidArgumentException::class],
    'malformed missing distinct column rejected' => [static fn (): mixed => SQLiteRowValueSavepointReturningDistinctCurrentSourceNextPlan::execute($tables, [$releaseSql], [$rollbackSql], [$retrySql], $unique, ['missing_column']), InvalidArgumentException::class],
];

$tests = [];
foreach ($cases as $name => [$callback, $expected]) {
    $tests['rowvalue savepoint returning distinct current source ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        if (is_string($expected) && is_a($expected, Throwable::class, true)) {
            $t->throws($expected, $callback);
            return;
        }
        $t->same($expected, $callback());
    };
}

return $tests;
