<?php

declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteRowValueDeleteUpdateSavepointCurrentSourceNextPlan;

$rows = [
    ['option_id' => 1, 'blog_id' => 1, 'option_name' => 'siteurl', 'autoload' => 'yes', 'status' => 'live', 'bytes' => 24, 'option_value' => 'https://old.test', 'checksum' => 'a1'],
    ['option_id' => 2, 'blog_id' => 1, 'option_name' => '_transient_feed', 'autoload' => 'no', 'status' => 'stale', 'bytes' => 12, 'option_value' => 'feed', 'checksum' => null],
    ['option_id' => 3, 'blog_id' => 2, 'option_name' => 'siteurl', 'autoload' => 'yes', 'status' => 'live', 'bytes' => '24', 'option_value' => 'https://network.test', 'checksum' => '24'],
    ['option_id' => 4, 'blog_id' => 2, 'option_name' => '_transient_feed', 'autoload' => 'no', 'status' => null, 'bytes' => 18, 'option_value' => 'network-feed', 'checksum' => null],
];

$statements = [
    "DELETE FROM wp_options WHERE (status, checksum) IS DISTINCT FROM ('live', 'a1') AND (blog_id, autoload) IS NOT DISTINCT FROM (1, 'no') RETURNING option_id, option_name, (status, checksum) IS NOT DISTINCT FROM ('stale', NULL) AS stale_null_pair ORDER BY option_id LIMIT 1",
    "UPDATE OR REPLACE wp_options SET (option_name, status, checksum) = ('siteurl', 'synced', option_name || ':synced') WHERE (blog_id, option_name) IS NOT DISTINCT FROM (2, '_transient_feed') AND (autoload, bytes) IS NOT DISTINCT FROM ('no', 18) RETURNING option_id, option_name, status, checksum, (bytes, checksum) IS DISTINCT FROM (18, NULL) AS storage_changed ORDER BY option_id",
];

$plan = SQLiteRowValueDeleteUpdateSavepointCurrentSourceNextPlan::executeDistinctReturningSavepoint(
    ['wp_options' => $rows],
    $statements,
    [['blog_id', 'option_name'], ['option_name']],
    'wp_options_distinct_rowvalue_batch',
);

if (($argv[1] ?? '') === '--self-test') {
    assert($plan['status'] === 'released');
    assert(array_column($plan['executed_statements'][0]['returning_rows'], 'option_id') === [2]);
    assert($plan['executed_statements'][0]['returning_rows'][0]['stale_null_pair'] === 1);
    assert($plan['executed_statements'][1]['returning_rows'][0]['option_id'] === 4);
    assert($plan['executed_statements'][1]['returning_rows'][0]['storage_changed'] === 1);
    assert(array_column($plan['deleted_conflict_rows'], 'option_id') === [3]);
    assert(array_column($plan['current_source_tables']['wp_options'], 'option_id') === [1, 4]);
    echo "wordpress-rowvalue-returning-distinct-savepoint-current-source self-test passed\n";
    return;
}

echo json_encode([
    'scenario' => 'wordpress-rowvalue-returning-distinct-savepoint-current-source',
    'status' => $plan['status'],
    'savepoint' => $plan['savepoint'],
    'returning' => $plan['yielded_returning'],
    'currentOptionIds' => array_column($plan['current_source_tables']['wp_options'], 'option_id'),
    'wordpressUse' => 'Copied wp_options cleanup/import batches can use row-value IS DISTINCT FROM and IS NOT DISTINCT FROM in UPDATE/DELETE RETURNING while a savepoint preserves the current source for rollback.',
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
