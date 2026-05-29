<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteRowValueDeleteUpdateSavepointCurrentSourceNextPlan;
use PortLibs\LibSqlite\SQLiteUpdateDeleteReturningSql;

$rows = [
    ['option_id' => 1, 'blog_id' => 1, 'option_name' => 'siteurl', 'autoload' => 'yes', 'status' => 'live', 'bytes' => 24, 'expected_bytes' => 24.0, 'option_value' => 'https://old.test', 'checksum' => '24'],
    ['option_id' => 2, 'blog_id' => 1, 'option_name' => 'home', 'autoload' => 'yes', 'status' => 'live', 'bytes' => 24.5, 'expected_bytes' => 24.5, 'option_value' => 'https://home.test', 'checksum' => '24.5'],
    ['option_id' => 3, 'blog_id' => 1, 'option_name' => '_transient_feed', 'autoload' => 'no', 'status' => 'stale', 'bytes' => 12, 'expected_bytes' => 12.0, 'option_value' => 'feed', 'checksum' => null],
    ['option_id' => 4, 'blog_id' => 1, 'option_name' => '_transient_timeout_feed', 'autoload' => 'no', 'status' => 'stale', 'bytes' => 12.25, 'expected_bytes' => 12.0, 'option_value' => 'timeout', 'checksum' => null],
    ['option_id' => 5, 'blog_id' => 2, 'option_name' => 'siteurl', 'autoload' => 'yes', 'status' => 'live', 'bytes' => '24.0', 'expected_bytes' => 24.0, 'option_value' => 'https://network.test', 'checksum' => '24'],
    ['option_id' => 6, 'blog_id' => 2, 'option_name' => '_transient_feed', 'autoload' => 'no', 'status' => null, 'bytes' => 18, 'expected_bytes' => 18.0, 'option_value' => 'network-feed', 'checksum' => null],
    ['option_id' => 7, 'blog_id' => 2, 'option_name' => 'pending_theme', 'autoload' => 'no', 'status' => null, 'bytes' => 18.5, 'expected_bytes' => 18.0, 'option_value' => 'theme', 'checksum' => 'theme'],
    ['option_id' => 8, 'blog_id' => 3, 'option_name' => 'rewrite_rules', 'autoload' => 'yes', 'status' => 'queued', 'bytes' => 1.0e3, 'expected_bytes' => 1000, 'option_value' => 'rules', 'checksum' => '1000'],
];

$tables = ['wp_options' => $rows];
$unique = [['blog_id', 'option_name'], ['option_name']];

$deleteDecimalClean = "DELETE FROM wp_options WHERE (bytes, expected_bytes) IS NOT DISTINCT FROM (12.0, 12.0) AND (status, checksum) IS DISTINCT FROM ('live', 'a1') RETURNING option_id, option_name, (bytes, expected_bytes) IS NOT DISTINCT FROM (12, 12.0) AS numeric_pair, (bytes, expected_bytes) IS DISTINCT FROM (12.0, 12.25) AS changed ORDER BY option_id";
$updateDecimalClean = "UPDATE OR REPLACE wp_options SET (option_name, status, checksum) = ('siteurl', 'synced', option_name || ':' || expected_bytes) WHERE (blog_id, option_name) IS NOT DISTINCT FROM (2.0, '_transient_feed') AND (bytes, expected_bytes) IS NOT DISTINCT FROM (18.0, 18) RETURNING option_id, option_name, status, checksum, (bytes, expected_bytes) IS DISTINCT FROM (18, 18.0) AS storage_changed ORDER BY option_id";
$deleteDecimalDrift = "DELETE FROM wp_options WHERE (bytes, expected_bytes) IS DISTINCT FROM (12.0, 12.0) AND autoload = 'no' RETURNING option_id, option_name, (bytes, expected_bytes) IS DISTINCT FROM (12.0, 12.0) AS drifted ORDER BY option_id LIMIT 2";
$abortDuplicate = "UPDATE wp_options SET (option_name, status) = ('siteurl', 'duplicate') WHERE (blog_id, option_name) IS NOT DISTINCT FROM (2.0, 'pending_theme') RETURNING option_id, option_name";

$commitStatements = [$deleteDecimalClean, $updateDecimalClean, $deleteDecimalDrift];
$rollbackStatements = [$deleteDecimalClean, $updateDecimalClean, $abortDuplicate];

$commit = static fn (): array => SQLiteRowValueDeleteUpdateSavepointCurrentSourceNextPlan::executeNext135($tables, $commitStatements, $unique, 'wp_options_real_distinct_rowvalue_batch');
$rollback = static fn (): array => SQLiteRowValueDeleteUpdateSavepointCurrentSourceNextPlan::executeNext135($tables, $rollbackStatements, $unique, 'wp_options_real_distinct_rowvalue_batch');
$parsedDelete = static fn (): array => SQLiteUpdateDeleteReturningSql::parse($deleteDecimalClean);
$parsedUpdate = static fn (): array => SQLiteUpdateDeleteReturningSql::parse($updateDecimalClean);

$direct = static fn (string $sql): array => SQLiteUpdateDeleteReturningSql::execute($sql, $tables);

$cases = [
    'parse delete preserves real not distinct where' => [static fn (): mixed => $parsedDelete()['where'], "(bytes, expected_bytes) IS NOT DISTINCT FROM (12.0, 12.0) AND (status, checksum) IS DISTINCT FROM ('live', 'a1')"],
    'parse delete returning real expressions' => [static fn (): mixed => $parsedDelete()['returning'], 'option_id, option_name, (bytes, expected_bytes) IS NOT DISTINCT FROM (12, 12.0) AS numeric_pair, (bytes, expected_bytes) IS DISTINCT FROM (12.0, 12.25) AS changed'],
    'parse delete order by option id' => [static fn (): mixed => $parsedDelete()['order_by'][0]['column'], 'option_id'],
    'parse update preserves real not distinct where' => [static fn (): mixed => $parsedUpdate()['where'], "(blog_id, option_name) IS NOT DISTINCT FROM (2.0, '_transient_feed') AND (bytes, expected_bytes) IS NOT DISTINCT FROM (18.0, 18)"],
    'parse update tuple assignment keys' => [static fn (): mixed => array_keys($parsedUpdate()['assignments']), ['option_name', 'status', 'checksum']],
    'parse update conflict replace' => [static fn (): mixed => $parsedUpdate()['conflict_action'], 'replace'],

    'commit released' => [static fn (): mixed => $commit()['status'], 'released'],
    'commit savepoint name' => [static fn (): mixed => $commit()['savepoint'], 'wp_options_real_distinct_rowvalue_batch'],
    'commit executes three statements' => [static fn (): mixed => count($commit()['executed_statements']), 3],
    'commit actions delete update delete' => [static fn (): mixed => array_column($commit()['executed_statements'], 'action'), ['delete', 'update', 'delete']],
    'commit first delete selects row three using real literals' => [static fn (): mixed => $commit()['executed_statements'][0]['selected_ids'], [3]],
    'commit first delete returning numeric pair true' => [static fn (): mixed => $commit()['yielded_returning'][0]['rows'][0]['numeric_pair'], 1],
    'commit first delete returning changed true' => [static fn (): mixed => $commit()['yielded_returning'][0]['rows'][0]['changed'], 1],
    'commit update selects network feed with 18.0 literal' => [static fn (): mixed => $commit()['executed_statements'][1]['selected_ids'], [6]],
    'commit update returning row six' => [static fn (): mixed => $commit()['yielded_returning'][1]['rows'][0]['option_id'], 6],
    'commit update replacement name siteurl' => [static fn (): mixed => $commit()['yielded_returning'][1]['rows'][0]['option_name'], 'siteurl'],
    'commit update expression treats 18 and 18.0 not distinct' => [static fn (): mixed => $commit()['yielded_returning'][1]['rows'][0]['storage_changed'], 0],
    'commit update checksum concatenates real source value' => [static fn (): mixed => $commit()['yielded_returning'][1]['rows'][0]['checksum'], '_transient_feed:18'],
    'commit replace deletes conflicting row five' => [static fn (): mixed => $commit()['deleted_conflict_rows'][0]['row']['option_id'], 5],
    'commit records conflict key network siteurl' => [static fn (): mixed => $commit()['conflicts'][0]['key'], '2|siteurl'],
    'commit final delete sees current source after prior delete update' => [static fn (): mixed => $commit()['executed_statements'][2]['selected_ids'], [4, 6]],
    'commit final delete returning drift flags' => [static fn (): mixed => array_column($commit()['yielded_returning'][2]['rows'], 'drifted'), [1, 1]],
    'commit current source ids after release' => [static fn (): mixed => array_column($commit()['current_source_tables']['wp_options'], 'option_id'), [1, 2, 7, 8]],
    'commit current source row seven remains pending after row six cleanup' => [static fn (): mixed => array_column($commit()['current_source_tables']['wp_options'], 'option_name', 'option_id')[7], 'pending_theme'],
    'commit next source equals current' => [static fn (): mixed => $commit()['next_source_tables'], $commit()['current_source_tables']],
    'commit savepoint image retains original ids' => [static fn (): mixed => array_column($commit()['savepoint_image_tables']['wp_options'], 'option_id'), [1, 2, 3, 4, 5, 6, 7, 8]],
    'commit deleted rows row ids' => [static fn (): mixed => array_column(array_column($commit()['deleted_rows'], 'row'), 'option_id'), [3, 4, 6]],
    'commit changes include delete update conflict delete and two final deletes' => [static fn (): mixed => $commit()['changes'], 5],
    'commit attempted changes equals changes' => [static fn (): mixed => $commit()['attempted_changes'], 5],

    'rollback status rolls back' => [static fn (): mixed => $rollback()['status'], 'rolled-back-to-savepoint'],
    'rollback flag true' => [static fn (): mixed => $rollback()['rolled_back'], true],
    'rollback fails on third statement' => [static fn (): mixed => $rollback()['rollback_statement_ordinal'], 2],
    'rollback reason reports duplicate after real-literal match' => [static fn (): mixed => $rollback()['rollback_reason'], 'SQLite UPDATE RETURNING unique constraint failed: blog_id,option_name=2|siteurl using OR ABORT'],
    'rollback yielded successful statements only' => [static fn (): mixed => array_column($rollback()['yielded_returning'], 'action'), ['delete', 'update']],
    'rollback attempted returning count' => [static fn (): mixed => count($rollback()['attempted_returning']), 2],
    'rollback attempted next source contains updated row six' => [static fn (): mixed => array_column($rollback()['next_source_tables']['wp_options'], 'option_name', 'option_id')[6], 'siteurl'],
    'rollback current source restores row three' => [static fn (): mixed => array_column($rollback()['current_source_tables']['wp_options'], 'option_name', 'option_id')[3], '_transient_feed'],
    'rollback current source restores row five' => [static fn (): mixed => array_column($rollback()['current_source_tables']['wp_options'], 'option_name', 'option_id')[5], 'siteurl'],
    'rollback current source restores row six' => [static fn (): mixed => array_column($rollback()['current_source_tables']['wp_options'], 'option_name', 'option_id')[6], '_transient_feed'],
    'rollback changes reset zero' => [static fn (): mixed => $rollback()['changes'], 0],
    'rollback attempted changes include delete update replace' => [static fn (): mixed => $rollback()['attempted_changes'], 3],
    'rollback savepoint image equals original tables' => [static fn (): mixed => $rollback()['savepoint_image_tables'], $tables],

    'direct real literal not distinct selects row one' => [static fn (): mixed => $direct("DELETE FROM wp_options WHERE option_id = 1 RETURNING (bytes, expected_bytes) IS NOT DISTINCT FROM (24, 24.0) AS same")['returning'][0]['same'], 1],
    'direct exponent literal not distinct selects row eight' => [static fn (): mixed => $direct("DELETE FROM wp_options WHERE option_id = 8 RETURNING (bytes, expected_bytes) IS NOT DISTINCT FROM (1.0e3, 1000.0) AS same")['returning'][0]['same'], 1],
    'direct text numeric storage distinct remains distinct' => [static fn (): mixed => $direct("DELETE FROM wp_options WHERE option_id = 5 RETURNING (bytes, expected_bytes) IS DISTINCT FROM (24.0, 24.0) AS drift")['returning'][0]['drift'], 1],
    'direct real where not distinct row ids' => [static fn (): mixed => $direct("DELETE FROM wp_options WHERE (bytes, expected_bytes) IS NOT DISTINCT FROM (24.5, 24.5) RETURNING option_id")['plan']->selectedIds, [2]],
    'direct leading decimal literal works' => [static fn (): mixed => $direct("DELETE FROM wp_options WHERE option_id = 2 RETURNING (bytes, expected_bytes) IS NOT DISTINCT FROM (.245e2, 24.5) AS same")['returning'][0]['same'], 1],
    'direct real drift where row ids' => [static fn (): mixed => $direct("DELETE FROM wp_options WHERE (bytes, expected_bytes) IS DISTINCT FROM (12.0, 12.0) AND autoload = 'no' RETURNING option_id ORDER BY option_id")['plan']->selectedIds, [4, 6, 7]],
    'malformed real arity rolls back savepoint' => [static fn (): mixed => SQLiteRowValueDeleteUpdateSavepointCurrentSourceNextPlan::executeNext135($tables, ["DELETE FROM wp_options WHERE (bytes, expected_bytes) IS DISTINCT FROM (12.0) RETURNING option_id"], $unique)['rollback_reason'], 'SQLite UPDATE/DELETE row-value expressions need at least two values'],
    'malformed non numeric literal still rejected' => [static fn (): mixed => SQLiteRowValueDeleteUpdateSavepointCurrentSourceNextPlan::executeNext135($tables, ["DELETE FROM wp_options WHERE (bytes, expected_bytes) IS DISTINCT FROM (12.0, 12.bad) RETURNING option_id"], $unique)['rollback_reason'], 'SQLite UPDATE/DELETE literal is not supported: 12.bad'],
];

$tests = [];
foreach ($cases as $name => [$callback, $expected]) {
    $tests['rowvalue returning savepoint distinct current source next149 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        $t->same($expected, $callback());
    };
}

return $tests;
