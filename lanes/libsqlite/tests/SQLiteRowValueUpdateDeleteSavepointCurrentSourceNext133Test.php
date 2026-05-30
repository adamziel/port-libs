<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteRowValueUpdateDeleteSavepointCurrentSourceNextPlan;
use PortLibs\LibSqlite\SQLiteUpdateDeleteReturningSql;

$rows = [
    ['option_id' => 1, 'blog_id' => 1, 'option_name' => 'siteurl', 'autoload' => 'yes', 'status' => 'live', 'bucket' => 'core', 'bytes' => 24, 'option_value' => 'https://old.test'],
    ['option_id' => 2, 'blog_id' => 1, 'option_name' => 'home', 'autoload' => 'yes', 'status' => 'live', 'bucket' => 'core', 'bytes' => 24, 'option_value' => 'https://old.test'],
    ['option_id' => 3, 'blog_id' => 1, 'option_name' => '_transient_feed', 'autoload' => 'no', 'status' => 'stale', 'bucket' => null, 'bytes' => 12, 'option_value' => 'feed'],
    ['option_id' => 4, 'blog_id' => 1, 'option_name' => '_transient_timeout_feed', 'autoload' => 'no', 'status' => 'stale', 'bucket' => null, 'bytes' => 13, 'option_value' => 'timeout'],
    ['option_id' => 5, 'blog_id' => 2, 'option_name' => '_transient_feed', 'autoload' => 'no', 'status' => 'stale', 'bucket' => null, 'bytes' => 14, 'option_value' => 'network-feed'],
    ['option_id' => 6, 'blog_id' => 2, 'option_name' => 'siteurl', 'autoload' => 'yes', 'status' => 'live', 'bucket' => 'network', 'bytes' => 25, 'option_value' => 'https://network.test'],
    ['option_id' => 7, 'blog_id' => 2, 'option_name' => 'pending_theme', 'autoload' => 'no', 'status' => null, 'bucket' => null, 'bytes' => 7, 'option_value' => 'theme'],
    ['option_id' => 8, 'blog_id' => 3, 'option_name' => 'pending_theme', 'autoload' => 'no', 'status' => null, 'bucket' => 'staged', 'bytes' => 8, 'option_value' => 'theme-three'],
];

$tables = ['wp_options' => $rows];
$unique = [['table' => 'wp_options', 'columns' => ['blog_id', 'option_name']]];

$nullSafeUpdateSql = "UPDATE wp_options SET (autoload, status, option_value, bytes) = ('yes', 'null-safe', option_name || ':restored', bytes + 10) WHERE (status, bucket) IS (NULL, NULL) RETURNING option_id, status, bucket, (status, bucket) IS ('null-safe', NULL) AS tuple_is, (status, bucket) IS NOT (NULL, NULL) AS tuple_is_not ORDER BY option_id";
$deleteSql = "DELETE FROM wp_options WHERE (status, bucket) IS (NULL, 'staged') RETURNING option_id, option_name, (status, bucket) IS (NULL, 'staged') AS old_tuple_is, (status, bucket) IS NOT (NULL, NULL) AS old_tuple_is_not";
$notIsUpdateSql = "UPDATE wp_options SET status = 'checked' WHERE (status, bucket) IS NOT ('live', 'core') RETURNING option_id, status, (status, bucket) IS NOT ('live', 'core') AS still_not_core ORDER BY option_id LIMIT 3";
$rollbackStatements = [
    $nullSafeUpdateSql,
    "UPDATE wp_options SET (blog_id, option_name, status) = (1, 'home', 'duplicate-after-is') WHERE (status, bucket) IS ('stale', NULL) RETURNING option_id, blog_id, option_name, status",
];
$commitStatements = [$nullSafeUpdateSql, $deleteSql, $notIsUpdateSql];

$nullSafeUpdate = static fn (): array => SQLiteUpdateDeleteReturningSql::execute($nullSafeUpdateSql, $tables);
$delete = static fn (): array => SQLiteUpdateDeleteReturningSql::execute($deleteSql, $tables);
$notIsUpdate = static fn (): array => SQLiteUpdateDeleteReturningSql::execute($notIsUpdateSql, $tables);
$commit = static fn (): array => SQLiteRowValueUpdateDeleteSavepointCurrentSourceNextPlan::execute($tables, $commitStatements, $unique, 'app_settings_rowvalue_is_batch');
$rollback = static fn (): array => SQLiteRowValueUpdateDeleteSavepointCurrentSourceNextPlan::execute($tables, $rollbackStatements, $unique, 'app_settings_rowvalue_is_batch');

$cases = [
    'parse row value is update where preserved' => [static fn (): mixed => SQLiteUpdateDeleteReturningSql::parse($nullSafeUpdateSql)['where'], '(status, bucket) IS (NULL, NULL)'],
    'parse row value is not update where preserved' => [static fn (): mixed => SQLiteUpdateDeleteReturningSql::parse($notIsUpdateSql)['where'], "(status, bucket) IS NOT ('live', 'core')"],
    'parse row value is returning preserved' => [static fn (): mixed => SQLiteUpdateDeleteReturningSql::parse($nullSafeUpdateSql)['returning'], "option_id, status, bucket, (status, bucket) IS ('null-safe', NULL) AS tuple_is, (status, bucket) IS NOT (NULL, NULL) AS tuple_is_not"],
    'null safe update selected null-null row' => [static fn (): mixed => $nullSafeUpdate()['plan']->selectedIds, [7]],
    'null safe update returning row id seven' => [static fn (): mixed => array_column($nullSafeUpdate()['returning'], 'option_id'), [7]],
    'null safe update returning tuple is sees updated row' => [static fn (): mixed => $nullSafeUpdate()['returning'][0]['tuple_is'], 1],
    'null safe update returning tuple is not sees updated row' => [static fn (): mixed => $nullSafeUpdate()['returning'][0]['tuple_is_not'], 1],
    'null safe update keeps bucket null' => [static fn (): mixed => $nullSafeUpdate()['returning'][0]['bucket'], null],
    'null safe update updates option value from source option name' => [static fn (): mixed => array_column($nullSafeUpdate()['tables']['wp_options'], 'option_value', 'option_id')[7], 'pending_theme:restored'],
    'null safe update does not match stale null bucket rows' => [static fn (): mixed => array_column($nullSafeUpdate()['tables']['wp_options'], 'status', 'option_id')[3], 'stale'],
    'null safe update does not match staged null status row' => [static fn (): mixed => array_column($nullSafeUpdate()['tables']['wp_options'], 'status', 'option_id')[8], null],
    'delete row value is selected staged null row' => [static fn (): mixed => $delete()['plan']->selectedIds, [8]],
    'delete row value is returning old tuple true' => [static fn (): mixed => $delete()['returning'][0]['old_tuple_is'], 1],
    'delete row value is not returning old tuple true' => [static fn (): mixed => $delete()['returning'][0]['old_tuple_is_not'], 1],
    'delete row value is removes only staged pending theme' => [static fn (): mixed => array_column($delete()['tables']['wp_options'], 'option_id'), [1, 2, 3, 4, 5, 6, 7]],
    'delete row value is keeps null null pending theme' => [static fn (): mixed => array_column($delete()['tables']['wp_options'], 'option_name', 'option_id')[7], 'pending_theme'],
    'is not update selected rows exclude live core pair' => [static fn (): mixed => $notIsUpdate()['plan']->selectedIds, [3, 4, 5]],
    'is not update mutation rows follow source order' => [static fn (): mixed => $notIsUpdate()['plan']->mutationIds, [3, 4, 5]],
    'is not update returning ids limited to three' => [static fn (): mixed => array_column($notIsUpdate()['returning'], 'option_id'), [3, 4, 5]],
    'is not update returning expression remains true after status change' => [static fn (): mixed => array_column($notIsUpdate()['returning'], 'still_not_core'), [1, 1, 1]],
    'is not update excludes row one' => [static fn (): mixed => array_column($notIsUpdate()['tables']['wp_options'], 'status', 'option_id')[1], 'live'],
    'is not update excludes row two' => [static fn (): mixed => array_column($notIsUpdate()['tables']['wp_options'], 'status', 'option_id')[2], 'live'],
    'is not update first selected transient checked' => [static fn (): mixed => array_column($notIsUpdate()['tables']['wp_options'], 'status', 'option_id')[3], 'checked'],
    'is not update null null row not selected because limit stops first three' => [static fn (): mixed => array_column($notIsUpdate()['tables']['wp_options'], 'status', 'option_id')[7], null],
    'commit status released' => [static fn (): mixed => $commit()['status'], 'released'],
    'commit savepoint named' => [static fn (): mixed => $commit()['savepoint'], 'app_settings_rowvalue_is_batch'],
    'commit executed three statements' => [static fn (): mixed => count($commit()['executed_statements']), 3],
    'commit statement actions update delete update' => [static fn (): mixed => array_column($commit()['executed_statements'], 'action'), ['update', 'delete', 'update']],
    'commit first selected id seven' => [static fn (): mixed => $commit()['executed_statements'][0]['selected_ids'], [7]],
    'commit second selected id eight' => [static fn (): mixed => $commit()['executed_statements'][1]['selected_ids'], [8]],
    'commit third selected ids after current source update and delete' => [static fn (): mixed => $commit()['executed_statements'][2]['selected_ids'], [3, 4, 5]],
    'commit yielded returning has three streams' => [static fn (): mixed => count($commit()['yielded_returning']), 3],
    'commit update returning row seven tuple is true' => [static fn (): mixed => $commit()['yielded_returning'][0]['rows'][0]['tuple_is'], 1],
    'commit delete returning row eight old tuple true' => [static fn (): mixed => $commit()['yielded_returning'][1]['rows'][0]['old_tuple_is'], 1],
    'commit final update returning checked rows' => [static fn (): mixed => array_column($commit()['yielded_returning'][2]['rows'], 'option_id'), [3, 4, 5]],
    'commit changes count update delete update' => [static fn (): mixed => $commit()['changes'], 5],
    'commit current source removed staged row' => [static fn (): mixed => array_column($commit()['current_source_tables']['wp_options'], 'option_id'), [1, 2, 3, 4, 5, 6, 7]],
    'commit current row seven survives with null-safe status' => [static fn (): mixed => array_column($commit()['current_source_tables']['wp_options'], 'status', 'option_id')[7], 'null-safe'],
    'commit current row three checked after later is not update' => [static fn (): mixed => array_column($commit()['current_source_tables']['wp_options'], 'status', 'option_id')[3], 'checked'],
    'commit next source equals current source' => [static fn (): mixed => $commit()['next_source_tables'], $commit()['current_source_tables']],
    'commit savepoint image kept original row count' => [static fn (): mixed => count($commit()['savepoint_image_tables']['wp_options']), 8],
    'commit attempted row count after delete' => [static fn (): mixed => $commit()['attempted_row_counts']['wp_options'], 7],
    'commit rollback changed tables empty' => [static fn (): mixed => $commit()['rollback_changed_tables'], []],
    'rollback status rolls back' => [static fn (): mixed => $rollback()['status'], 'rolled-back-to-savepoint'],
    'rollback reason names duplicate home' => [static fn (): mixed => $rollback()['rollback_reason'], 'unique-constraint:wp_options:blog_id,option_name:1|home'],
    'rollback statement ordinal one' => [static fn (): mixed => $rollback()['rollback_statement_ordinal'], 1],
    'rollback yielded only first statement' => [static fn (): mixed => array_column($rollback()['yielded_returning'], 'action'), ['update']],
    'rollback attempted returning includes failing update' => [static fn (): mixed => array_column($rollback()['attempted_returning'], 'action'), ['update', 'update']],
    'rollback failing update selected stale null bucket rows' => [static fn (): mixed => $rollback()['executed_statements'][1]['selected_ids'], [3, 4, 5]],
    'rollback current source restores original ids' => [static fn (): mixed => array_column($rollback()['current_source_tables']['wp_options'], 'option_id'), [1, 2, 3, 4, 5, 6, 7, 8]],
    'rollback restores row seven null status' => [static fn (): mixed => array_column($rollback()['current_source_tables']['wp_options'], 'status', 'option_id')[7], null],
    'rollback next source retains attempted row seven status' => [static fn (): mixed => array_column($rollback()['next_source_tables']['wp_options'], 'status', 'option_id')[7], 'null-safe'],
    'rollback next source retains attempted duplicate name' => [static fn (): mixed => array_column($rollback()['next_source_tables']['wp_options'], 'option_name', 'option_id')[3], 'home'],
    'rollback changes reset to zero' => [static fn (): mixed => $rollback()['changes'], 0],
    'rollback attempted changes includes first update and failing update rows' => [static fn (): mixed => $rollback()['attempted_changes'], 4],
    'rollback changed table records wp options' => [static fn (): mixed => $rollback()['rollback_changed_tables'], ['wp_options']],
    'malformed row value is arity mismatch rejected' => [static fn (): mixed => SQLiteUpdateDeleteReturningSql::execute("UPDATE wp_options SET status = 'x' WHERE (status, bucket) IS (NULL) RETURNING option_id", $tables), InvalidArgumentException::class],
    'malformed row value is not arity mismatch rejected' => [static fn (): mixed => SQLiteUpdateDeleteReturningSql::execute("DELETE FROM wp_options WHERE (status, bucket) IS NOT (NULL) RETURNING option_id", $tables), InvalidArgumentException::class],
    'malformed row value is missing column rejected' => [static fn (): mixed => SQLiteUpdateDeleteReturningSql::execute("UPDATE wp_options SET status = 'x' WHERE (missing, bucket) IS (NULL, NULL) RETURNING option_id", $tables), InvalidArgumentException::class],
];

$tests = [];
foreach ($cases as $name => [$callback, $expected]) {
    $tests['rowvalue update delete savepoint current source next133 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        if (is_string($expected) && is_a($expected, Throwable::class, true)) {
            $t->throws($expected, $callback);
            return;
        }
        $t->same($expected, $callback());
    };
}

return $tests;
