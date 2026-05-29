<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNextPlan;
use PortLibs\LibSqlite\SQLiteUpdateDeleteReturningSql;

$rows = [
    ['option_id' => 1, 'blog_id' => 1, 'option_name' => 'siteurl', 'autoload' => 'yes', 'status' => 'live', 'bucket' => 'core', 'bytes' => 30, 'option_value' => 'https://old.test'],
    ['option_id' => 2, 'blog_id' => 1, 'option_name' => 'home', 'autoload' => 'yes', 'status' => 'live', 'bucket' => 'core', 'bytes' => 31, 'option_value' => 'https://home.test'],
    ['option_id' => 3, 'blog_id' => 1, 'option_name' => '_transient_feed', 'autoload' => 'no', 'status' => 'stale', 'bucket' => 'cache', 'bytes' => 10, 'option_value' => 'feed'],
    ['option_id' => 4, 'blog_id' => 1, 'option_name' => '_transient_timeout_feed', 'autoload' => 'no', 'status' => 'stale', 'bucket' => 'cache', 'bytes' => 11, 'option_value' => 'timeout'],
    ['option_id' => 5, 'blog_id' => 2, 'option_name' => 'siteurl', 'autoload' => 'yes', 'status' => 'live', 'bucket' => 'network', 'bytes' => 35, 'option_value' => 'https://network.test'],
    ['option_id' => 6, 'blog_id' => 2, 'option_name' => 'home', 'autoload' => 'yes', 'status' => 'live', 'bucket' => 'network', 'bytes' => 36, 'option_value' => 'https://network-home.test'],
    ['option_id' => 7, 'blog_id' => 2, 'option_name' => '_transient_feed', 'autoload' => 'no', 'status' => 'stale', 'bucket' => 'cache', 'bytes' => 12, 'option_value' => 'network-feed'],
    ['option_id' => 8, 'blog_id' => 3, 'option_name' => 'rewrite_rules', 'autoload' => 'yes', 'status' => 'queued', 'bucket' => 'rewrite', 'bytes' => 15, 'option_value' => 'rules'],
    ['option_id' => 9, 'blog_id' => 3, 'option_name' => 'orphaned_cache', 'autoload' => 'no', 'status' => 'queued', 'bucket' => 'cache', 'bytes' => 7, 'option_value' => 'orphan'],
    ['option_id' => 10, 'blog_id' => 4, 'option_name' => 'theme_mods', 'autoload' => 'yes', 'status' => null, 'bucket' => 'theme', 'bytes' => 22, 'option_value' => 'theme'],
];

$tables = ['wp_options' => $rows];
$unique = [['blog_id', 'option_name']];

$beforeSql = "UPDATE wp_options SET (status, option_value, bytes) = ('prepared', option_value || ':prepared', bytes + 1) WHERE (blog_id, option_name) IN ((3, 'orphaned_cache'), (4, 'theme_mods')) RETURNING option_id, blog_id, option_name, status, option_value, bytes ORDER BY option_id";
$protectedUpdateSql = "UPDATE wp_options SET (status, bucket, option_value, bytes) = ('protected', 'cache', option_value || ':protected', bytes + 5) WHERE (status, bucket) IS NOT DISTINCT FROM ('stale', 'cache') RETURNING option_id, blog_id, option_name, status, bucket, option_value, (status, bucket) IS NOT DISTINCT FROM ('protected', 'cache') AS protected_cache ORDER BY option_id";
$protectedDeleteSql = "DELETE FROM wp_options WHERE (status, bucket) IS DISTINCT FROM ('live', 'core') AND option_id IN (4, 7, 8, 9, 10) RETURNING option_id, blog_id, option_name, status, bucket ORDER BY option_id";
$afterUpdateSql = "UPDATE wp_options SET (status, option_value, bytes) = ('after', option_value || ':after', bytes + 2) WHERE (status, bucket) IS NOT DISTINCT FROM ('stale', 'cache') RETURNING option_id, option_name, status, bucket, option_value, bytes ORDER BY option_id LIMIT 2";
$afterDeleteSql = "DELETE FROM wp_options WHERE (status, option_name) IN (('prepared', 'orphaned_cache')) RETURNING option_id, option_name, status ORDER BY option_id";
$releaseAfterSql = "UPDATE wp_options SET (status, option_value) = ('released', option_value || ':released') WHERE (status, bucket) IS NOT DISTINCT FROM ('protected', 'cache') RETURNING option_id, option_name, status, option_value ORDER BY option_id";

$parsedProtectedDelete = static fn (): array => SQLiteUpdateDeleteReturningSql::parse($protectedDeleteSql);
$beforeOnly = static fn (): array => SQLiteUpdateDeleteReturningSql::execute($beforeSql, $tables, 'option_id', $unique);
$protectedUpdateOnly = static fn (): array => SQLiteUpdateDeleteReturningSql::execute($protectedUpdateSql, $beforeOnly()['tables'], 'option_id', $unique);
$protectedDeleteOnly = static fn (): array => SQLiteUpdateDeleteReturningSql::execute($protectedDeleteSql, $protectedUpdateOnly()['tables'], 'option_id', $unique);
$rollbackPlan = static fn (): array => SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNextPlan::executeUpdateDeleteReturningSavepointBatch(
    $tables,
    [$beforeSql],
    [$protectedUpdateSql, $protectedDeleteSql],
    [$afterUpdateSql, $afterDeleteSql],
    $unique,
    'wp_options_rowvalue_returning_savepoint',
    1,
);
$releasePlan = static fn (): array => SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNextPlan::executeUpdateDeleteReturningSavepointBatch(
    $tables,
    [$beforeSql],
    [$protectedUpdateSql],
    [$releaseAfterSql],
    $unique,
    'wp_options_rowvalue_release_savepoint',
);

$cases = [
    'parse protected delete keeps row-value distinct predicate' => [static fn (): mixed => $parsedProtectedDelete()['where'], "(status, bucket) IS DISTINCT FROM ('live', 'core') AND option_id IN (4, 7, 8, 9, 10)"],
    'parse protected delete returning list' => [static fn (): mixed => $parsedProtectedDelete()['returning'], 'option_id, blog_id, option_name, status, bucket'],
    'before only selects prepared rows' => [static fn (): mixed => $beforeOnly()['plan']->selectedIds, [9, 10]],
    'before only returns prepared values' => [static fn (): mixed => array_column($beforeOnly()['returning'], 'status'), ['prepared', 'prepared']],
    'before only row ten prepared from null' => [static fn (): mixed => array_column($beforeOnly()['tables']['wp_options'], 'status', 'option_id')[10], 'prepared'],
    'protected update selects stale cache rows from savepoint image' => [static fn (): mixed => $protectedUpdateOnly()['plan']->selectedIds, [3, 4, 7]],
    'protected update returning flags are true' => [static fn (): mixed => array_column($protectedUpdateOnly()['returning'], 'protected_cache'), [1, 1, 1]],
    'protected update row seven has attempted suffix' => [static fn (): mixed => array_column($protectedUpdateOnly()['tables']['wp_options'], 'option_value', 'option_id')[7], 'network-feed:protected'],
    'protected delete sees attempted current source' => [static fn (): mixed => $protectedDeleteOnly()['plan']->selectedIds, [4, 7, 8, 9, 10]],
    'protected delete removes attempted and prepared rows' => [static fn (): mixed => array_column($protectedDeleteOnly()['tables']['wp_options'], 'option_id'), [1, 2, 3, 5, 6]],

    'rollback plan status' => [static fn (): mixed => $rollbackPlan()['status'], 'rolled-back-to-rowvalue-returning-savepoint-current-source'],
    'rollback plan savepoint name' => [static fn (): mixed => $rollbackPlan()['savepoint'], 'wp_options_rowvalue_returning_savepoint'],
    'rollback plan flag true' => [static fn (): mixed => $rollbackPlan()['rolled_back_to_savepoint'], true],
    'rollback plan ordinal one' => [static fn (): mixed => $rollbackPlan()['rollback_protected_ordinal'], 1],
    'rollback plan before statements one' => [static fn (): mixed => count($rollbackPlan()['before_statements']), 1],
    'rollback plan protected statements two' => [static fn (): mixed => count($rollbackPlan()['protected_statements_before_rollback']), 2],
    'rollback plan after statements two' => [static fn (): mixed => count($rollbackPlan()['after_statements']), 2],
    'rollback plan before selected ids' => [static fn (): mixed => $rollbackPlan()['before_statements'][0]['selected_ids'], [9, 10]],
    'rollback plan protected update selected ids' => [static fn (): mixed => $rollbackPlan()['protected_statements_before_rollback'][0]['selected_ids'], [3, 4, 7]],
    'rollback plan protected delete selected ids' => [static fn (): mixed => $rollbackPlan()['protected_statements_before_rollback'][1]['selected_ids'], [4, 7, 8, 9, 10]],
    'rollback plan after update restarts from savepoint image' => [static fn (): mixed => $rollbackPlan()['after_statements'][0]['selected_ids'], [3, 4]],
    'rollback plan after delete sees prepared row nine restored' => [static fn (): mixed => $rollbackPlan()['after_statements'][1]['selected_ids'], [9]],
    'rollback plan before returning ids yielded' => [static fn (): mixed => array_column($rollbackPlan()['before_returning'][0]['rows'], 'option_id'), [9, 10]],
    'rollback plan protected update returning discarded' => [static fn (): mixed => array_column($rollbackPlan()['protected_returning_before_rollback'][0]['rows'], 'option_id'), [3, 4, 7]],
    'rollback plan protected delete returning discarded' => [static fn (): mixed => array_column($rollbackPlan()['protected_returning_before_rollback'][1]['rows'], 'option_id'), [4, 7, 8, 9, 10]],
    'rollback plan after returning update ids' => [static fn (): mixed => array_column($rollbackPlan()['after_returning'][0]['rows'], 'option_id'), [3, 4]],
    'rollback plan after returning delete ids' => [static fn (): mixed => array_column($rollbackPlan()['after_returning'][1]['rows'], 'option_id'), [9]],
    'rollback plan yielded phases suppress protected' => [static fn (): mixed => array_column($rollbackPlan()['yielded_returning'], 'phase'), ['before', 'after', 'after']],
    'rollback plan yielded actions' => [static fn (): mixed => array_column($rollbackPlan()['yielded_returning'], 'action'), ['update', 'update', 'delete']],
    'rollback plan discarded stream count' => [static fn (): mixed => count($rollbackPlan()['discarded_returning']), 2],
    'rollback plan discarded returning count' => [static fn (): mixed => $rollbackPlan()['discarded_returning_count'], 8],
    'rollback plan protected attempt deletes rows' => [static fn (): mixed => array_column($rollbackPlan()['protected_attempt_tables']['wp_options'], 'option_id'), [1, 2, 3, 5, 6]],
    'rollback plan after start restores row seven' => [static fn (): mixed => array_column($rollbackPlan()['after_start_tables']['wp_options'], 'option_name', 'option_id')[7], '_transient_feed'],
    'rollback plan after start preserves prepared row ten' => [static fn (): mixed => array_column($rollbackPlan()['after_start_tables']['wp_options'], 'status', 'option_id')[10], 'prepared'],
    'rollback plan final row three after' => [static fn (): mixed => array_column($rollbackPlan()['current_source_tables']['wp_options'], 'option_value', 'option_id')[3], 'feed:after'],
    'rollback plan final row four after not protected' => [static fn (): mixed => array_column($rollbackPlan()['current_source_tables']['wp_options'], 'option_value', 'option_id')[4], 'timeout:after'],
    'rollback plan final row seven restored stale' => [static fn (): mixed => array_column($rollbackPlan()['current_source_tables']['wp_options'], 'status', 'option_id')[7], 'stale'],
    'rollback plan final row nine deleted after rollback' => [static fn (): mixed => in_array(9, array_column($rollbackPlan()['current_source_tables']['wp_options'], 'option_id'), true), false],
    'rollback plan next equals current' => [static fn (): mixed => $rollbackPlan()['next_source_tables'], $rollbackPlan()['current_source_tables']],
    'rollback plan changes exclude protected' => [static fn (): mixed => $rollbackPlan()['changes'], 5],
    'rollback plan attempted changes include protected' => [static fn (): mixed => $rollbackPlan()['attempted_changes_before_rollback'], 10],
    'rollback plan row count' => [static fn (): mixed => $rollbackPlan()['row_counts']['wp_options'], 9],
    'rollback plan changed tables' => [static fn (): mixed => $rollbackPlan()['changed_tables'], ['wp_options']],
    'rollback plan source cursor phases' => [static fn (): mixed => array_column($rollbackPlan()['source_cursor'], 'phase'), ['before', 'protected', 'protected', 'after', 'after']],
    'rollback plan source cursor yielded flags' => [static fn (): mixed => array_column($rollbackPlan()['source_cursor'], 'yielded'), [true, false, false, true, true]],
    'rollback plan dependency marker' => [static fn (): mixed => in_array('sqlite-rowvalue-update-delete-returning-savepoint-current-source', $rollbackPlan()['dependencies'], true), true],
    'rollback plan dependency current source restart' => [static fn (): mixed => in_array('sqlite-current-source-after-rollback-restarts-from-savepoint-image', $rollbackPlan()['dependencies'], true), true],
    'rollback plan non-overlap documents accepted distinct retry' => [static fn (): mixed => str_contains($rollbackPlan()['non_overlap'], 'distinct retry'), true],

    'release plan status' => [static fn (): mixed => $releasePlan()['status'], 'released-rowvalue-returning-savepoint-current-source'],
    'release plan flag false' => [static fn (): mixed => $releasePlan()['rolled_back_to_savepoint'], false],
    'release plan protected yielded' => [static fn (): mixed => array_column($releasePlan()['yielded_returning'], 'phase'), ['before', 'protected', 'after']],
    'release plan no discarded rows' => [static fn (): mixed => $releasePlan()['discarded_returning_count'], 0],
    'release plan after starts from protected row seven' => [static fn (): mixed => array_column($releasePlan()['after_start_tables']['wp_options'], 'status', 'option_id')[7], 'protected'],
    'release plan final row seven released' => [static fn (): mixed => array_column($releasePlan()['current_source_tables']['wp_options'], 'status', 'option_id')[7], 'released'],
    'release plan changes include protected' => [static fn (): mixed => $releasePlan()['changes'], 8],

    'malformed empty before rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNextPlan::executeUpdateDeleteReturningSavepointBatch($tables, [], [$protectedUpdateSql], [$afterUpdateSql], $unique), InvalidArgumentException::class],
    'malformed empty protected rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNextPlan::executeUpdateDeleteReturningSavepointBatch($tables, [$beforeSql], [], [$afterUpdateSql], $unique), InvalidArgumentException::class],
    'malformed empty after rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNextPlan::executeUpdateDeleteReturningSavepointBatch($tables, [$beforeSql], [$protectedUpdateSql], [], $unique), InvalidArgumentException::class],
    'malformed empty unique rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNextPlan::executeUpdateDeleteReturningSavepointBatch($tables, [$beforeSql], [$protectedUpdateSql], [$afterUpdateSql], []), InvalidArgumentException::class],
    'malformed rollback ordinal rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNextPlan::executeUpdateDeleteReturningSavepointBatch($tables, [$beforeSql], [$protectedUpdateSql], [$afterUpdateSql], $unique, 'bad', 4), InvalidArgumentException::class],
    'malformed savepoint rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNextPlan::executeUpdateDeleteReturningSavepointBatch($tables, [$beforeSql], [$protectedUpdateSql], [$afterUpdateSql], $unique, 'bad-name'), InvalidArgumentException::class],
    'malformed row list rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNextPlan::executeUpdateDeleteReturningSavepointBatch(['wp_options' => ['bad']], [$beforeSql], [$protectedUpdateSql], [$afterUpdateSql], $unique), InvalidArgumentException::class],
];

$tests = [];
foreach ($cases as $name => [$callback, $expected]) {
    $tests['rowvalue update delete returning savepoint current source ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        if (is_string($expected) && is_a($expected, Throwable::class, true)) {
            $t->throws($expected, $callback);
            return;
        }
        $t->same($expected, $callback());
    };
}

return $tests;
