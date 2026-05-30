<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteRowValueAbortReturningSavepointCurrentSourceNextPlan;

$rows = [
    ['option_id' => 1, 'blog_id' => 1, 'option_name' => 'siteurl', 'autoload' => 'yes', 'status' => 'live', 'bytes' => 20, 'option_value' => 'https://old.test'],
    ['option_id' => 2, 'blog_id' => 1, 'option_name' => 'home', 'autoload' => 'yes', 'status' => 'live', 'bytes' => 21, 'option_value' => 'https://home.test'],
    ['option_id' => 5, 'blog_id' => 2, 'option_name' => 'siteurl', 'autoload' => 'yes', 'status' => 'live', 'bytes' => 25, 'option_value' => 'https://network.test'],
    ['option_id' => 7, 'blog_id' => 2, 'option_name' => 'pending_theme', 'autoload' => 'no', 'status' => null, 'bytes' => 7, 'option_value' => 'theme'],
    ['option_id' => 8, 'blog_id' => 3, 'option_name' => 'orphaned_cache', 'autoload' => 'no', 'status' => 'staged', 'bytes' => 5, 'option_value' => 'cache'],
    ['option_id' => 9, 'blog_id' => 3, 'option_name' => 'rewrite_rules', 'autoload' => 'yes', 'status' => 'queued', 'bytes' => 9, 'option_value' => 'rules'],
];

$stageSql = "UPDATE wp_options SET (blog_id, option_name, status, option_value, bytes) = (blog_id, option_name || ':staged', 'staged', option_value || ':staged', bytes + 10) WHERE option_id IN (7, 8) RETURNING option_id, blog_id, option_name, status, option_value, bytes ORDER BY option_id";
$abortSql = "UPDATE OR ABORT wp_options SET (blog_id, option_name, status, option_value, bytes) = (1, 'siteurl', option_name || ':abort', option_value || ':abort', bytes + 100) WHERE option_id IN (9, 6) RETURNING option_id, blog_id, option_name, status, option_value, bytes ORDER BY option_id DESC";

$plan = SQLiteRowValueAbortReturningSavepointCurrentSourceNextPlan::execute(
    ['wp_options' => $rows],
    [$stageSql, $abortSql],
    [['blog_id', 'option_name']],
);

$payload = [
    'scenario' => 'application-rowvalue-abort-returning-current-source-next140',
    'applicationUse' => 'Model a copied wp_options import savepoint where row-value UPDATE RETURNING yields staged rows, a later UPDATE OR ABORT unique conflict backs out only its own statement, and prior RETURNING/current-source rows remain visible for PHP recovery without ext/sqlite.',
    'status' => $plan['status'],
    'abortReason' => $plan['abort_reason'],
    'yieldedReturningIds' => array_map(static fn (array $stream): array => array_column($stream['rows'], 'option_id'), $plan['yielded_returning']),
    'currentOptionNames' => array_column($plan['current_source_tables']['wp_options'], 'option_name', 'option_id'),
    'rowCount' => $plan['row_counts']['wp_options'],
    'dependencyClosure' => 'no new support component needed; reuses native PHP row-value UPDATE RETURNING, unique-conflict, and savepoint current-source primitives',
];

if (($argv[1] ?? null) === '--self-test') {
    assert($payload['status'] === 'statement-aborted-savepoint-active');
    assert($payload['yieldedReturningIds'] === [[7, 8]]);
    assert($payload['currentOptionNames'][7] === 'pending_theme:staged');
    assert($payload['currentOptionNames'][9] === 'rewrite_rules');
    echo "application-rowvalue-abort-returning-current-source-next140 self-test passed\n";
    return;
}

echo json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
