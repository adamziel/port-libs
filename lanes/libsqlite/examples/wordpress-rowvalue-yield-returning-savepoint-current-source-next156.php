<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNextPlan;

$rows = [
    ['option_id' => 1, 'blog_id' => 1, 'option_name' => 'siteurl', 'autoload' => 'yes', 'status' => 'live', 'bytes' => 20, 'option_value' => 'https://old.test'],
    ['option_id' => 2, 'blog_id' => 1, 'option_name' => 'home', 'autoload' => 'yes', 'status' => 'live', 'bytes' => 21, 'option_value' => 'https://home.test'],
    ['option_id' => 3, 'blog_id' => 1, 'option_name' => '_transient_feed', 'autoload' => 'no', 'status' => 'stale', 'bytes' => 12, 'option_value' => 'feed'],
    ['option_id' => 4, 'blog_id' => 1, 'option_name' => '_transient_timeout_feed', 'autoload' => 'no', 'status' => 'stale', 'bytes' => 13, 'option_value' => 'timeout'],
    ['option_id' => 5, 'blog_id' => 2, 'option_name' => 'siteurl', 'autoload' => 'yes', 'status' => 'live', 'bytes' => 25, 'option_value' => 'https://network.test'],
    ['option_id' => 6, 'blog_id' => 2, 'option_name' => 'home', 'autoload' => 'yes', 'status' => 'live', 'bytes' => 26, 'option_value' => 'https://network-home.test'],
    ['option_id' => 7, 'blog_id' => 2, 'option_name' => 'pending_theme', 'autoload' => 'no', 'status' => null, 'bytes' => 7, 'option_value' => 'theme'],
    ['option_id' => 8, 'blog_id' => 3, 'option_name' => 'orphaned_cache', 'autoload' => 'no', 'status' => 'staged', 'bytes' => 5, 'option_value' => 'cache'],
    ['option_id' => 9, 'blog_id' => 3, 'option_name' => 'rewrite_rules', 'autoload' => 'yes', 'status' => 'queued', 'bytes' => 9, 'option_value' => 'rules'],
];

$plan = SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNextPlan::executeYieldReturningSavepointBatch(
    ['wp_options' => $rows],
    [
        "UPDATE OR IGNORE wp_options SET (blog_id, option_name, status, option_value, bytes) = (blog_id, 'siteurl', option_name || ':ignored', option_value || ':ignored', bytes + 10) WHERE option_id IN (8, 7) RETURNING option_id, blog_id, option_name, status, option_value, bytes ORDER BY option_id DESC",
        "UPDATE OR REPLACE wp_options SET (blog_id, option_name, status, option_value, bytes) = (1, 'home', option_name || ':replaced', option_value || ':replaced', bytes + 20) WHERE option_id = 9 RETURNING option_id, blog_id, option_name, status, option_value, bytes",
        "DELETE FROM wp_options WHERE (blog_id, option_name) IN ((1, '_transient_feed'), (1, '_transient_timeout_feed'), (1, 'siteurl'), (3, 'siteurl')) RETURNING option_id, blog_id, option_name, status ORDER BY option_id",
    ],
    [['blog_id', 'option_name']],
);

$summary = [
    'scenario' => 'wordpress-rowvalue-yield-returning-savepoint-current-source-next156',
    'status' => $plan['status'],
    'yieldedRowIds' => array_map(
        static fn (array $stream): array => array_column($stream['rows'], 'option_id'),
        $plan['yielded_returning'],
    ),
    'ignoredRows' => $plan['ignored_row_count'],
    'deletedConflictRows' => $plan['deleted_conflict_row_count'],
    'finalOptionIds' => array_column($plan['current_source_tables']['wp_options'], 'option_id'),
    'wordpressUse' => 'A copied wp_options import savepoint can yield UPDATE OR IGNORE rows without yielding ignored conflicts, yield UPDATE OR REPLACE rows after deleting the conflicting option row, then run DELETE RETURNING against the current source produced by those row-value updates.',
    'dependencyClosure' => 'no new support component needed; this composes existing native PHP UPDATE/DELETE RETURNING row-value execution with savepoint current-source bookkeeping',
];

if (
    $summary['status'] !== 'released'
    || $summary['yieldedRowIds'] !== [[8], [9], [1, 3, 4, 8]]
    || $summary['ignoredRows'] !== 1
    || $summary['deletedConflictRows'] !== 1
    || $summary['finalOptionIds'] !== [5, 6, 7, 9]
) {
    fwrite(STDERR, "wordpress-rowvalue-yield-returning-savepoint-current-source-next156 self-test failed\n");
    exit(1);
}

echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
echo "wordpress-rowvalue-yield-returning-savepoint-current-source-next156 self-test passed\n";
