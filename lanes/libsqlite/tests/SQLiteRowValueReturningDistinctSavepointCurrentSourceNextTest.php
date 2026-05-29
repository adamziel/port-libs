<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteRowValueDeleteUpdateSavepointCurrentSourceNextPlan;
use PortLibs\LibSqlite\SQLiteUpdateDeleteReturningSql;

$rows = [
    ['option_id' => 1, 'blog_id' => 1, 'option_name' => 'siteurl', 'autoload' => 'yes', 'status' => 'live', 'bytes' => 24, 'option_value' => 'https://old.test', 'checksum' => 'a1'],
    ['option_id' => 2, 'blog_id' => 1, 'option_name' => 'home', 'autoload' => 'yes', 'status' => 'live', 'bytes' => 24, 'option_value' => 'https://home.test', 'checksum' => 'a1'],
    ['option_id' => 3, 'blog_id' => 1, 'option_name' => '_transient_feed', 'autoload' => 'no', 'status' => 'stale', 'bytes' => 12, 'option_value' => 'feed', 'checksum' => null],
    ['option_id' => 4, 'blog_id' => 1, 'option_name' => '_transient_timeout_feed', 'autoload' => 'no', 'status' => 'stale', 'bytes' => 12.0, 'option_value' => 'timeout', 'checksum' => null],
    ['option_id' => 5, 'blog_id' => 2, 'option_name' => 'siteurl', 'autoload' => 'yes', 'status' => 'live', 'bytes' => '24', 'option_value' => 'https://network.test', 'checksum' => '24'],
    ['option_id' => 6, 'blog_id' => 2, 'option_name' => '_transient_feed', 'autoload' => 'no', 'status' => null, 'bytes' => 18, 'option_value' => 'network-feed', 'checksum' => null],
    ['option_id' => 7, 'blog_id' => 2, 'option_name' => 'pending_theme', 'autoload' => 'no', 'status' => null, 'bytes' => 18, 'option_value' => 'theme', 'checksum' => 'theme'],
];

$tables = ['wp_options' => $rows];
$unique = [['blog_id', 'option_name'], ['option_name']];

$deleteDrift = "DELETE FROM wp_options WHERE (status, checksum) IS DISTINCT FROM ('live', 'a1') AND (blog_id, autoload) IS NOT DISTINCT FROM (1, 'no') RETURNING option_id, option_name, (status, checksum) IS DISTINCT FROM ('live', 'a1') AS drifted, (status, checksum) IS NOT DISTINCT FROM ('stale', NULL) AS stale_null_pair ORDER BY option_id LIMIT 1";
$updateDrift = "UPDATE OR REPLACE wp_options SET (option_name, status, checksum) = ('siteurl', 'synced', option_name || ':synced') WHERE (blog_id, option_name) IS NOT DISTINCT FROM (2, '_transient_feed') AND (autoload, bytes) IS NOT DISTINCT FROM ('no', 18) RETURNING option_id, option_name, status, checksum, (bytes, checksum) IS DISTINCT FROM (18, NULL) AS storage_changed ORDER BY option_id";
$deleteNetwork = "DELETE FROM wp_options WHERE (blog_id, status) IS NOT DISTINCT FROM (2, NULL) RETURNING option_id, option_name, (status, checksum) IS DISTINCT FROM (NULL, NULL) AS checksum_present ORDER BY option_id";
$abortDuplicate = "UPDATE wp_options SET (option_name, status) = ('siteurl', 'duplicate') WHERE (blog_id, option_name) IS NOT DISTINCT FROM (2, 'pending_theme') RETURNING option_id, option_name";

$commitStatements = [$deleteDrift, $updateDrift, $deleteNetwork];
$rollbackStatements = [$deleteDrift, $updateDrift, $abortDuplicate];

$commit = static fn (): array => SQLiteRowValueDeleteUpdateSavepointCurrentSourceNextPlan::executeDistinctReturningSavepoint($tables, $commitStatements, $unique, 'wp_options_distinct_rowvalue_batch');
$rollback = static fn (): array => SQLiteRowValueDeleteUpdateSavepointCurrentSourceNextPlan::executeDistinctReturningSavepoint($tables, $rollbackStatements, $unique, 'wp_options_distinct_rowvalue_batch');
$parsedDelete = static fn (): array => SQLiteUpdateDeleteReturningSql::parse($deleteDrift);
$parsedUpdate = static fn (): array => SQLiteUpdateDeleteReturningSql::parse($updateDrift);

$cases = [
    'delete parse preserves distinct where' => [static fn (): mixed => $parsedDelete()['where'], "(status, checksum) IS DISTINCT FROM ('live', 'a1') AND (blog_id, autoload) IS NOT DISTINCT FROM (1, 'no')"],
    'delete parse returning distinct expression' => [static fn (): mixed => $parsedDelete()['returning'], "option_id, option_name, (status, checksum) IS DISTINCT FROM ('live', 'a1') AS drifted, (status, checksum) IS NOT DISTINCT FROM ('stale', NULL) AS stale_null_pair"],
    'delete parse order by option id' => [static fn (): mixed => $parsedDelete()['order_by'][0]['column'], 'option_id'],
    'delete parse limit one' => [static fn (): mixed => $parsedDelete()['limit'], 1],
    'update parse conflict action replace' => [static fn (): mixed => $parsedUpdate()['conflict_action'], 'replace'],
    'update parse assignments include tuple columns' => [static fn (): mixed => array_keys($parsedUpdate()['assignments']), ['option_name', 'status', 'checksum']],
    'update parse distinct where' => [static fn (): mixed => $parsedUpdate()['where'], "(blog_id, option_name) IS NOT DISTINCT FROM (2, '_transient_feed') AND (autoload, bytes) IS NOT DISTINCT FROM ('no', 18)"],
    'commit status released' => [static fn (): mixed => $commit()['status'], 'released'],
    'commit savepoint name preserved' => [static fn (): mixed => $commit()['savepoint'], 'wp_options_distinct_rowvalue_batch'],
    'commit executes three statements' => [static fn (): mixed => count($commit()['executed_statements']), 3],
    'commit statement actions' => [static fn (): mixed => array_column($commit()['executed_statements'], 'action'), ['delete', 'update', 'delete']],
    'commit first delete selects stale row three only' => [static fn (): mixed => $commit()['executed_statements'][0]['selected_ids'], [3]],
    'commit first delete returning row id three' => [static fn (): mixed => $commit()['yielded_returning'][0]['rows'][0]['option_id'], 3],
    'commit first delete returns drift true' => [static fn (): mixed => $commit()['yielded_returning'][0]['rows'][0]['drifted'], 1],
    'commit first delete returns null-safe not distinct true' => [static fn (): mixed => $commit()['yielded_returning'][0]['rows'][0]['stale_null_pair'], 1],
    'commit update skips deleted row and selects network feed' => [static fn (): mixed => $commit()['executed_statements'][1]['selected_ids'], [6]],
    'commit update returning network row' => [static fn (): mixed => $commit()['yielded_returning'][1]['rows'][0]['option_id'], 6],
    'commit update returning replacement name' => [static fn (): mixed => $commit()['yielded_returning'][1]['rows'][0]['option_name'], 'siteurl'],
    'commit update returning synced status' => [static fn (): mixed => $commit()['yielded_returning'][1]['rows'][0]['status'], 'synced'],
    'commit update returning expression distinguishes checksum storage' => [static fn (): mixed => $commit()['yielded_returning'][1]['rows'][0]['storage_changed'], 1],
    'commit replace conflict records existing network siteurl' => [static fn (): mixed => $commit()['conflicts'][0]['conflicting_row_ids'], [5]],
    'commit replace deletes conflicting row five' => [static fn (): mixed => $commit()['deleted_conflict_rows'][0]['row']['option_id'], 5],
    'commit final delete sees current source pending theme only' => [static fn (): mixed => $commit()['executed_statements'][2]['selected_ids'], [7]],
    'commit final delete returning checksum present' => [static fn (): mixed => $commit()['yielded_returning'][2]['rows'][0]['checksum_present'], 1],
    'commit deleted rows include stale and pending' => [static fn (): mixed => array_column(array_column($commit()['deleted_rows'], 'row'), 'option_id'), [3, 7]],
    'commit current source ids after release' => [static fn (): mixed => array_column($commit()['current_source_tables']['wp_options'], 'option_id'), [1, 2, 4, 6]],
    'commit current source keeps row four stale' => [static fn (): mixed => array_column($commit()['current_source_tables']['wp_options'], 'status', 'option_id')[4], 'stale'],
    'commit current source row six becomes siteurl' => [static fn (): mixed => array_column($commit()['current_source_tables']['wp_options'], 'option_name', 'option_id')[6], 'siteurl'],
    'commit current source row six synced' => [static fn (): mixed => array_column($commit()['current_source_tables']['wp_options'], 'status', 'option_id')[6], 'synced'],
    'commit next source equals current' => [static fn (): mixed => $commit()['next_source_tables'], $commit()['current_source_tables']],
    'commit savepoint image retains original ids' => [static fn (): mixed => array_column($commit()['savepoint_image_tables']['wp_options'], 'option_id'), [1, 2, 3, 4, 5, 6, 7]],
    'commit changes include delete update conflict delete final delete' => [static fn (): mixed => $commit()['changes'], 4],
    'commit attempted changes equals changes' => [static fn (): mixed => $commit()['attempted_changes'], 4],
    'commit dependencies keep savepoint marker' => [static fn (): mixed => in_array('sqlite-savepoint-current-source-delete-update-rollback', $commit()['dependencies'], true), true],
    'rollback status rolls back' => [static fn (): mixed => $rollback()['status'], 'rolled-back-to-savepoint'],
    'rollback flag true' => [static fn (): mixed => $rollback()['rolled_back'], true],
    'rollback fails on third statement' => [static fn (): mixed => $rollback()['rollback_statement_ordinal'], 2],
    'rollback reason reports unique abort' => [static fn (): mixed => $rollback()['rollback_reason'], 'SQLite UPDATE RETURNING unique constraint failed: blog_id,option_name=2|siteurl using OR ABORT'],
    'rollback yielded only successful statements before abort' => [static fn (): mixed => array_column($rollback()['yielded_returning'], 'action'), ['delete', 'update']],
    'rollback attempted returning keeps successful statements' => [static fn (): mixed => count($rollback()['attempted_returning']), 2],
    'rollback attempted source contains update before abort' => [static fn (): mixed => array_column($rollback()['next_source_tables']['wp_options'], 'option_name', 'option_id')[6], 'siteurl'],
    'rollback current source restores original ids' => [static fn (): mixed => array_column($rollback()['current_source_tables']['wp_options'], 'option_id'), [1, 2, 3, 4, 5, 6, 7]],
    'rollback restores deleted transient row' => [static fn (): mixed => array_column($rollback()['current_source_tables']['wp_options'], 'option_name', 'option_id')[3], '_transient_feed'],
    'rollback restores network siteurl row five' => [static fn (): mixed => array_column($rollback()['current_source_tables']['wp_options'], 'option_name', 'option_id')[5], 'siteurl'],
    'rollback restores network feed row six' => [static fn (): mixed => array_column($rollback()['current_source_tables']['wp_options'], 'option_name', 'option_id')[6], '_transient_feed'],
    'rollback changes reset to zero' => [static fn (): mixed => $rollback()['changes'], 0],
    'rollback attempted changes include successful delete update replacement' => [static fn (): mixed => $rollback()['attempted_changes'], 3],
    'rollback savepoint image equals original tables' => [static fn (): mixed => $rollback()['savepoint_image_tables'], $tables],
    'direct row-value distinct storage class text numeric differs' => [static fn (): mixed => SQLiteUpdateDeleteReturningSql::execute("DELETE FROM wp_options WHERE option_id = 5 RETURNING (bytes, checksum) IS DISTINCT FROM (24, 24) AS changed", $tables)['returning'][0]['changed'], 1],
    'direct row-value not distinct numeric int real equal' => [static fn (): mixed => SQLiteUpdateDeleteReturningSql::execute("DELETE FROM wp_options WHERE option_id = 4 RETURNING (bytes, status) IS NOT DISTINCT FROM (12, 'stale') AS same", $tables)['returning'][0]['same'], 1],
    'direct where one-sided null is distinct selects rows' => [static fn (): mixed => SQLiteUpdateDeleteReturningSql::execute("DELETE FROM wp_options WHERE (status, checksum) IS DISTINCT FROM (NULL, NULL) RETURNING option_id ORDER BY option_id", $tables)['plan']->selectedIds, [1, 2, 3, 4, 5, 7]],
    'direct where aligned null not distinct selects row six' => [static fn (): mixed => SQLiteUpdateDeleteReturningSql::execute("DELETE FROM wp_options WHERE (status, checksum) IS NOT DISTINCT FROM (NULL, NULL) RETURNING option_id", $tables)['plan']->selectedIds, [6]],
    'malformed distinct arity rolls back savepoint' => [static fn (): mixed => SQLiteRowValueDeleteUpdateSavepointCurrentSourceNextPlan::executeDistinctReturningSavepoint($tables, ["DELETE FROM wp_options WHERE (status, checksum) IS DISTINCT FROM ('stale') RETURNING option_id"], $unique)['rollback_reason'], 'SQLite UPDATE/DELETE row-value expressions need at least two values'],
    'malformed missing column rolls back savepoint' => [static fn (): mixed => SQLiteRowValueDeleteUpdateSavepointCurrentSourceNextPlan::executeDistinctReturningSavepoint($tables, ["DELETE FROM wp_options WHERE (status, missing_column) IS DISTINCT FROM ('stale', NULL) RETURNING option_id"], $unique)['rollback_reason'], 'SQLite UPDATE/DELETE column missing_column is missing'],
];

$tests = [];
foreach ($cases as $name => [$callback, $expected]) {
    $tests['rowvalue returning distinct savepoint current source ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        $t->same($expected, $callback());
    };
}

return $tests;
