<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteRowValueDeleteUpdateSavepointCurrentSourceNextPlan;
use PortLibs\LibSqlite\SQLiteUpdateDeleteReturningSql;

$rows = [
    ['option_id' => 1, 'blog_id' => 1, 'option_name' => 'siteurl', 'autoload' => 'yes', 'status' => 'live', 'bytes' => 24, 'option_value' => 'https://old.test'],
    ['option_id' => 2, 'blog_id' => 1, 'option_name' => 'home', 'autoload' => 'yes', 'status' => 'live', 'bytes' => 24, 'option_value' => 'https://old.test'],
    ['option_id' => 3, 'blog_id' => 1, 'option_name' => '_transient_feed', 'autoload' => 'no', 'status' => 'stale', 'bytes' => 12, 'option_value' => 'feed'],
    ['option_id' => 4, 'blog_id' => 1, 'option_name' => '_transient_timeout_feed', 'autoload' => 'no', 'status' => 'stale', 'bytes' => 13, 'option_value' => 'timeout'],
    ['option_id' => 5, 'blog_id' => 2, 'option_name' => '_transient_feed', 'autoload' => 'no', 'status' => 'stale', 'bytes' => 14, 'option_value' => 'network-feed'],
    ['option_id' => 6, 'blog_id' => 2, 'option_name' => 'network_siteurl', 'autoload' => 'yes', 'status' => 'live', 'bytes' => 25, 'option_value' => 'https://network.test'],
    ['option_id' => 7, 'blog_id' => 2, 'option_name' => 'pending_theme', 'autoload' => 'no', 'status' => null, 'bytes' => 7, 'option_value' => 'theme'],
];

$tables = ['wp_options' => $rows];
$unique = [['option_name']];

$deleteStale = "DELETE FROM wp_options WHERE (blog_id, status) = (1, 'stale') RETURNING option_id, option_name, (blog_id, status) = (1, 'stale') AS stale_key ORDER BY option_id LIMIT 1";
$promote = "UPDATE OR REPLACE wp_options SET (option_name, status, bytes, option_value) = ('siteurl', option_name || ':promoted', bytes + 10, option_value || ':next') WHERE (blog_id, option_name) IN ((1, 'home'), (1, '_transient_feed'), (2, '_transient_feed')) RETURNING option_id, option_name, status, bytes, option_value, (blog_id, option_name) NOT IN ((1, '_transient_feed')) AS deleted_source_missing ORDER BY option_id";
$deleteRemainder = "DELETE FROM wp_options WHERE (blog_id, option_name) NOT IN ((2, 'siteurl'), (2, 'network_siteurl'), (2, 'pending_theme')) RETURNING option_id, option_name ORDER BY option_id";
$abort = "UPDATE wp_options SET (option_name, status) = ('network_siteurl', 'duplicate') WHERE option_id = 7 RETURNING option_id, option_name";

$commitStatements = [$deleteStale, $promote, $deleteRemainder];
$rollbackStatements = [$deleteStale, $promote, $abort];

$commit = static fn (): array => SQLiteRowValueDeleteUpdateSavepointCurrentSourceNextPlan::executeDistinctReturningSavepoint($tables, $commitStatements, $unique, 'wp_options_cleanup_batch');
$rollback = static fn (): array => SQLiteRowValueDeleteUpdateSavepointCurrentSourceNextPlan::executeDistinctReturningSavepoint($tables, $rollbackStatements, $unique, 'wp_options_cleanup_batch');
$parsedDelete = static fn (): array => SQLiteUpdateDeleteReturningSql::parse($deleteStale);
$parsedUpdate = static fn (): array => SQLiteUpdateDeleteReturningSql::parse($promote);

$cases = [
    'delete parse action' => [static fn (): mixed => $parsedDelete()['action'], 'delete'],
    'delete parse row-value where' => [static fn (): mixed => $parsedDelete()['where'], "(blog_id, status) = (1, 'stale')"],
    'delete parse returning expression preserved' => [static fn (): mixed => $parsedDelete()['returning'], "option_id, option_name, (blog_id, status) = (1, 'stale') AS stale_key"],
    'delete parse order column' => [static fn (): mixed => $parsedDelete()['order_by'][0]['column'], 'option_id'],
    'delete parse limit one' => [static fn (): mixed => $parsedDelete()['limit'], 1],
    'update parse action' => [static fn (): mixed => $parsedUpdate()['action'], 'update'],
    'update parse conflict action replace' => [static fn (): mixed => $parsedUpdate()['conflict_action'], 'replace'],
    'update parse row-value assignment columns' => [static fn (): mixed => array_keys($parsedUpdate()['assignments']), ['option_name', 'status', 'bytes', 'option_value']],
    'update parse row-value in where' => [static fn (): mixed => $parsedUpdate()['where'], "(blog_id, option_name) IN ((1, 'home'), (1, '_transient_feed'), (2, '_transient_feed'))"],
    'commit status released' => [static fn (): mixed => $commit()['status'], 'released'],
    'commit not rolled back' => [static fn (): mixed => $commit()['rolled_back'], false],
    'commit savepoint name' => [static fn (): mixed => $commit()['savepoint'], 'wp_options_cleanup_batch'],
    'commit executed three statements' => [static fn (): mixed => count($commit()['executed_statements']), 3],
    'commit statement actions delete update delete' => [static fn (): mixed => array_column($commit()['executed_statements'], 'action'), ['delete', 'update', 'delete']],
    'commit first delete selected id three' => [static fn (): mixed => $commit()['executed_statements'][0]['selected_ids'], [3]],
    'commit first delete mutation id three' => [static fn (): mixed => $commit()['executed_statements'][0]['mutation_ids'], [3]],
    'commit first delete returning row id' => [static fn (): mixed => $commit()['yielded_returning'][0]['rows'][0]['option_id'], 3],
    'commit first delete returning row-value predicate' => [static fn (): mixed => $commit()['yielded_returning'][0]['rows'][0]['stale_key'], 1],
    'commit records deleted row name from savepoint current source' => [static fn (): mixed => $commit()['deleted_rows'][0]['row']['option_name'], '_transient_feed'],
    'commit update sees delete current source and skips row three' => [static fn (): mixed => $commit()['executed_statements'][1]['selected_ids'], [2, 5]],
    'commit update mutation ids use current source order' => [static fn (): mixed => $commit()['executed_statements'][1]['mutation_ids'], [2, 5]],
    'commit update returning ids exclude deleted row three' => [static fn (): mixed => array_column($commit()['yielded_returning'][1]['rows'], 'option_id'), [2, 5]],
    'commit update returning assigned siteurl twice' => [static fn (): mixed => array_column($commit()['yielded_returning'][1]['rows'], 'option_name'), ['siteurl', 'siteurl']],
    'commit update returning statuses use per-row old names' => [static fn (): mixed => array_column($commit()['yielded_returning'][1]['rows'], 'status'), ['home:promoted', '_transient_feed:promoted']],
    'commit update returning bytes incremented' => [static fn (): mixed => array_column($commit()['yielded_returning'][1]['rows'], 'bytes'), [34, 24]],
    'commit update returning value concatenation' => [static fn (): mixed => array_column($commit()['yielded_returning'][1]['rows'], 'option_value'), ['https://old.test:next', 'network-feed:next']],
    'commit update returning row-value not-in expression true after prior delete' => [static fn (): mixed => array_column($commit()['yielded_returning'][1]['rows'], 'deleted_source_missing'), [1, 1]],
    'commit update conflicts include original and current-source rows' => [static fn (): mixed => array_column($commit()['conflicts'], 'conflicting_row_ids'), [[1], [2]]],
    'commit conflict keys are siteurl' => [static fn (): mixed => array_column($commit()['conflicts'], 'key'), ['siteurl', 'siteurl']],
    'commit replacement deletes original then earlier updated row' => [static fn (): mixed => array_column(array_column($commit()['deleted_conflict_rows'], 'row'), 'option_id'), [1, 2]],
    'commit final delete sees updated current source' => [static fn (): mixed => $commit()['executed_statements'][2]['selected_ids'], [4]],
    'commit final delete returning timeout row only' => [static fn (): mixed => array_column($commit()['yielded_returning'][2]['rows'], 'option_name'), ['_transient_timeout_feed']],
    'commit current source ids after release' => [static fn (): mixed => array_column($commit()['current_source_tables']['wp_options'], 'option_id'), [5, 6, 7]],
    'commit final siteurl is network row five' => [static fn (): mixed => array_column($commit()['current_source_tables']['wp_options'], 'status', 'option_id')[5], '_transient_feed:promoted'],
    'commit final network row remains' => [static fn (): mixed => array_column($commit()['current_source_tables']['wp_options'], 'option_name', 'option_id')[6], 'network_siteurl'],
    'commit pending row remains' => [static fn (): mixed => array_column($commit()['current_source_tables']['wp_options'], 'option_name', 'option_id')[7], 'pending_theme'],
    'commit next source equals current source' => [static fn (): mixed => $commit()['next_source_tables'], $commit()['current_source_tables']],
    'commit savepoint image retains original rows' => [static fn (): mixed => array_column($commit()['savepoint_image_tables']['wp_options'], 'option_id'), [1, 2, 3, 4, 5, 6, 7]],
    'commit changes include returned delete update and replacement deletes' => [static fn (): mixed => $commit()['changes'], 6],
    'commit attempted changes equals changes' => [static fn (): mixed => $commit()['attempted_changes'], 6],
    'commit attempted returning tracks all statements' => [static fn (): mixed => array_column($commit()['attempted_returning'], 'action'), ['delete', 'update', 'delete']],
    'commit updated row diagnostics keep ordinals' => [static fn (): mixed => array_column($commit()['updated_rows'], 'ordinal'), [1, 1]],
    'commit deleted row diagnostics include first and third delete' => [static fn (): mixed => array_column(array_column($commit()['deleted_rows'], 'row'), 'option_id'), [3, 4]],
    'rollback status rolls back' => [static fn (): mixed => $rollback()['status'], 'rolled-back-to-savepoint'],
    'rollback flag true' => [static fn (): mixed => $rollback()['rolled_back'], true],
    'rollback statement ordinal is aborting update' => [static fn (): mixed => $rollback()['rollback_statement_ordinal'], 2],
    'rollback reason includes unique failure' => [static fn (): mixed => $rollback()['rollback_reason'], 'SQLite UPDATE RETURNING unique constraint failed: option_name=network_siteurl using OR ABORT'],
    'rollback yielded only pre-failure statements' => [static fn (): mixed => array_column($rollback()['yielded_returning'], 'action'), ['delete', 'update']],
    'rollback attempted returning omits thrown statement' => [static fn (): mixed => count($rollback()['attempted_returning']), 2],
    'rollback current source restores original ids' => [static fn (): mixed => array_column($rollback()['current_source_tables']['wp_options'], 'option_id'), [1, 2, 3, 4, 5, 6, 7]],
    'rollback current source restores deleted transient' => [static fn (): mixed => array_column($rollback()['current_source_tables']['wp_options'], 'option_name', 'option_id')[3], '_transient_feed'],
    'rollback current source restores original siteurl' => [static fn (): mixed => array_column($rollback()['current_source_tables']['wp_options'], 'option_name', 'option_id')[1], 'siteurl'],
    'rollback current source restores pending theme' => [static fn (): mixed => array_column($rollback()['current_source_tables']['wp_options'], 'status', 'option_id')[7], null],
    'rollback next source keeps attempted delete' => [static fn (): mixed => in_array(3, array_column($rollback()['next_source_tables']['wp_options'], 'option_id'), true), false],
    'rollback next source keeps attempted final siteurl row five' => [static fn (): mixed => array_column($rollback()['next_source_tables']['wp_options'], 'option_name', 'option_id')[5], 'siteurl'],
    'rollback next source keeps pending theme before failed statement' => [static fn (): mixed => array_column($rollback()['next_source_tables']['wp_options'], 'option_name', 'option_id')[7], 'pending_theme'],
    'rollback changes reset to zero' => [static fn (): mixed => $rollback()['changes'], 0],
    'rollback attempted changes count successful statement effects' => [static fn (): mixed => $rollback()['attempted_changes'], 5],
    'rollback savepoint image equals original tables' => [static fn (): mixed => $rollback()['savepoint_image_tables'], $tables],
    'rollback dependencies include delete marker' => [static fn (): mixed => in_array('sqlite-delete-returning-current-source', $rollback()['dependencies'], true), true],
    'rollback dependencies include row-value update marker' => [static fn (): mixed => in_array('sqlite-row-value-update-after-delete', $rollback()['dependencies'], true), true],
    'rollback dependencies include savepoint marker' => [static fn (): mixed => in_array('sqlite-savepoint-current-source-delete-update-rollback', $rollback()['dependencies'], true), true],
    'malformed empty statement list rejected' => [static fn (): mixed => SQLiteRowValueDeleteUpdateSavepointCurrentSourceNextPlan::executeDistinctReturningSavepoint($tables, [], $unique), InvalidArgumentException::class],
    'malformed empty unique constraints rejected' => [static fn (): mixed => SQLiteRowValueDeleteUpdateSavepointCurrentSourceNextPlan::executeDistinctReturningSavepoint($tables, $commitStatements, []), InvalidArgumentException::class],
    'malformed missing table rolls back savepoint' => [static fn (): mixed => SQLiteRowValueDeleteUpdateSavepointCurrentSourceNextPlan::executeDistinctReturningSavepoint($tables, ["DELETE FROM missing WHERE option_id = 1 RETURNING option_id"], $unique)['rollback_reason'], 'SQLite UPDATE/DELETE RETURNING table missing is missing'],
    'malformed bad rowid rolls back savepoint' => [static fn (): mixed => SQLiteRowValueDeleteUpdateSavepointCurrentSourceNextPlan::executeDistinctReturningSavepoint(['wp_options' => [['option_name' => 'siteurl']]], ["DELETE FROM wp_options WHERE option_name = 'siteurl' RETURNING option_name"], $unique)['rollback_reason'], 'SQLite UPDATE/DELETE LIMIT row is missing rowid column setting_id'],
];

$tests = [];
foreach ($cases as $name => [$callback, $expected]) {
    $tests['rowvalue delete update savepoint current source ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        if (is_string($expected) && is_a($expected, Throwable::class, true)) {
            $t->throws($expected, $callback);
            return;
        }
        $t->same($expected, $callback());
    };
}

return $tests;
