<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteUpdateDeleteReturningSql;

$options = [
    ['option_id' => 1, 'option_name' => 'siteurl', 'autoload' => 'yes', 'status' => 'live', 'expected_status' => 'live', 'bytes' => 20, 'expected_bytes' => 20],
    ['option_id' => 2, 'option_name' => 'home', 'autoload' => 'yes', 'status' => 'live', 'expected_status' => 'staged', 'bytes' => 21, 'expected_bytes' => 21],
    ['option_id' => 3, 'option_name' => '_transient_feed', 'autoload' => 'no', 'status' => 'stale', 'expected_status' => 'stale', 'bytes' => 12, 'expected_bytes' => 13],
    ['option_id' => 4, 'option_name' => 'orphaned_cache', 'autoload' => 'no', 'status' => null, 'expected_status' => null, 'bytes' => 7, 'expected_bytes' => 7],
];

$drift = SQLiteUpdateDeleteReturningSql::execute(
    "DELETE FROM wp_options WHERE (status, bytes) IS DISTINCT FROM (expected_status, expected_bytes) RETURNING option_id, option_name, (status, bytes) IS NOT DISTINCT FROM (expected_status, expected_bytes) AS clean ORDER BY option_id",
    ['wp_options' => $options],
);

$clean = SQLiteUpdateDeleteReturningSql::execute(
    "UPDATE wp_options SET status = 'verified' WHERE (status, bytes) IS NOT DISTINCT FROM (expected_status, expected_bytes) RETURNING option_id, status, (status, bytes) IS DISTINCT FROM ('verified', expected_bytes) AS drifted ORDER BY option_id",
    ['wp_options' => $options],
);

if (($argv[1] ?? null) === '--self-test') {
    assert(array_column($drift['returning'], 'option_id') === [2, 3]);
    assert(array_column($drift['returning'], 'clean') === [0, 0]);
    assert(array_column($clean['returning'], 'option_id') === [1, 4]);
    assert(array_column($clean['returning'], 'drifted') === [0, 0]);
    echo "application-rowvalue-distinct-returning-current-source-next142 self-test passed\n";
    return;
}

echo json_encode(['drift' => $drift, 'clean' => $clean], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
