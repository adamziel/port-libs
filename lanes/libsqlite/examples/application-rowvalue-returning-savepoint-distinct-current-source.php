<?php

declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteRowValueDeleteUpdateSavepointCurrentSourceNextPlan;

$rows = [
    ['option_id' => 1, 'blog_id' => 1, 'option_name' => 'siteurl', 'autoload' => 'yes', 'status' => 'live', 'bytes' => 24, 'expected_bytes' => 24.0, 'option_value' => 'https://old.test', 'checksum' => '24'],
    ['option_id' => 2, 'blog_id' => 1, 'option_name' => '_transient_feed', 'autoload' => 'no', 'status' => 'stale', 'bytes' => 12, 'expected_bytes' => 12.0, 'option_value' => 'feed', 'checksum' => null],
    ['option_id' => 3, 'blog_id' => 2, 'option_name' => 'siteurl', 'autoload' => 'yes', 'status' => 'live', 'bytes' => '24.0', 'expected_bytes' => 24.0, 'option_value' => 'https://network.test', 'checksum' => '24'],
    ['option_id' => 4, 'blog_id' => 2, 'option_name' => '_transient_feed', 'autoload' => 'no', 'status' => null, 'bytes' => 18, 'expected_bytes' => 18.0, 'option_value' => 'network-feed', 'checksum' => null],
    ['option_id' => 5, 'blog_id' => 2, 'option_name' => 'pending_theme', 'autoload' => 'no', 'status' => null, 'bytes' => 18.5, 'expected_bytes' => 18.0, 'option_value' => 'theme', 'checksum' => 'theme'],
];

$statements = [
    "DELETE FROM wp_options WHERE (bytes, expected_bytes) IS NOT DISTINCT FROM (12.0, 12.0) AND (status, checksum) IS DISTINCT FROM ('live', 'a1') RETURNING option_id, option_name, (bytes, expected_bytes) IS NOT DISTINCT FROM (12, 12.0) AS numeric_pair ORDER BY option_id",
    "UPDATE OR REPLACE wp_options SET (option_name, status, checksum) = ('siteurl', 'synced', option_name || ':' || expected_bytes) WHERE (blog_id, option_name) IS NOT DISTINCT FROM (2.0, '_transient_feed') AND (bytes, expected_bytes) IS NOT DISTINCT FROM (18.0, 18) RETURNING option_id, option_name, status, checksum, (bytes, expected_bytes) IS DISTINCT FROM (18, 18.0) AS storage_changed ORDER BY option_id",
];

$plan = SQLiteRowValueDeleteUpdateSavepointCurrentSourceNextPlan::executeDistinctReturningSavepoint(
    ['wp_options' => $rows],
    $statements,
    [['blog_id', 'option_name'], ['option_name']],
    'wp_options_real_distinct_rowvalue_batch',
);

if (($argv[1] ?? '') === '--self-test') {
    assert($plan['status'] === 'released');
    assert($plan['executed_statements'][0]['returning_rows'][0]['option_id'] === 2);
    assert($plan['executed_statements'][0]['returning_rows'][0]['numeric_pair'] === 1);
    assert($plan['executed_statements'][1]['returning_rows'][0]['option_id'] === 4);
    assert($plan['executed_statements'][1]['returning_rows'][0]['storage_changed'] === 0);
    assert(array_column($plan['deleted_conflict_rows'], 'option_id') === [3]);
    assert(array_column($plan['current_source_tables']['wp_options'], 'option_id') === [1, 4, 5]);
    echo "application-rowvalue-returning-savepoint-distinct-current-source self-test passed\n";
    return;
}

echo json_encode([
    'scenario' => 'application-rowvalue-returning-savepoint-distinct-current-source',
    'status' => $plan['status'],
    'savepoint' => $plan['savepoint'],
    'returning' => $plan['yielded_returning'],
    'currentOptionIds' => array_column($plan['current_source_tables']['wp_options'], 'option_id'),
    'applicationUse' => 'Copied wp_options cleanup/import batches can use real numeric literals in row-value IS DISTINCT FROM and IS NOT DISTINCT FROM predicates while savepoints preserve the current source for rollback.',
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
