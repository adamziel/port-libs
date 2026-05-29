<?php

declare(strict_types=1);

require __DIR__ . '/../../../tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteRowValueSavepointReturningDistinctCurrentSourceNextPlan;

$tables = [
    'wp_options' => [
        ['option_id' => 1, 'blog_id' => 1, 'option_name' => 'siteurl', 'autoload' => 'yes', 'status' => 'live', 'bucket' => 'core', 'revision' => 1, 'bytes' => 40, 'option_value' => 'https://old.test'],
        ['option_id' => 2, 'blog_id' => 1, 'option_name' => 'home', 'autoload' => 'yes', 'status' => 'live', 'bucket' => 'core', 'revision' => 1, 'bytes' => 41, 'option_value' => 'https://home.test'],
        ['option_id' => 3, 'blog_id' => 1, 'option_name' => '_transient_feed', 'autoload' => 'no', 'status' => 'stale', 'bucket' => 'cache', 'revision' => 2, 'bytes' => 12, 'option_value' => 'feed'],
        ['option_id' => 4, 'blog_id' => 1, 'option_name' => '_transient_timeout_feed', 'autoload' => 'no', 'status' => 'stale', 'bucket' => 'cache', 'revision' => 2, 'bytes' => 13, 'option_value' => 'timeout'],
        ['option_id' => 5, 'blog_id' => 2, 'option_name' => 'siteurl', 'autoload' => 'yes', 'status' => 'live', 'bucket' => 'network', 'revision' => 1, 'bytes' => 44, 'option_value' => 'https://network.test'],
        ['option_id' => 6, 'blog_id' => 2, 'option_name' => 'home', 'autoload' => 'yes', 'status' => 'live', 'bucket' => 'network', 'revision' => 1, 'bytes' => 45, 'option_value' => 'https://network-home.test'],
        ['option_id' => 7, 'blog_id' => 2, 'option_name' => '_transient_feed', 'autoload' => 'no', 'status' => 'stale', 'bucket' => 'cache', 'revision' => 2, 'bytes' => 15, 'option_value' => 'network-feed'],
        ['option_id' => 8, 'blog_id' => 3, 'option_name' => 'rewrite_rules', 'autoload' => 'yes', 'status' => null, 'bucket' => 'rewrite', 'revision' => null, 'bytes' => 20, 'option_value' => 'rules'],
        ['option_id' => 9, 'blog_id' => 3, 'option_name' => 'orphaned_cache', 'autoload' => 'no', 'status' => 'queued', 'bucket' => 'cache', 'revision' => null, 'bytes' => 8, 'option_value' => 'orphan'],
        ['option_id' => 10, 'blog_id' => 4, 'option_name' => 'theme_mods', 'autoload' => 'yes', 'status' => 'queued', 'bucket' => 'theme', 'revision' => 3, 'bytes' => 30, 'option_value' => 'theme'],
    ],
];

$releaseSql = "UPDATE wp_options SET (status, bucket, option_value, bytes) = ('reviewed', 'cache', option_value || ':reviewed', bytes + 1) WHERE (status, bucket) IS NOT DISTINCT FROM ('stale', 'cache') RETURNING option_id, blog_id, option_name, status, bucket, (status, bucket) IS NOT DISTINCT FROM ('reviewed', 'cache') AS reviewed_cache ORDER BY option_id";
$rollbackSql = "UPDATE wp_options SET (status, bucket, option_value, bytes) = ('retry', 'cache', option_value || ':attempt', bytes + 2) WHERE (status, bucket) IS DISTINCT FROM ('live', 'core') RETURNING option_id, blog_id, option_name, status, bucket, (status, bucket) IS DISTINCT FROM ('live', 'core') AS not_core ORDER BY option_id";
$failingSql = "DELETE FROM wp_options WHERE (status) IS DISTINCT FROM ('live') RETURNING option_id";
$retrySql = "UPDATE wp_options SET (status, bucket, option_value, bytes) = ('retry', 'cache', option_value || ':retry', bytes + 5) WHERE (status, bucket) IS DISTINCT FROM ('live', 'core') RETURNING option_id, blog_id, option_name, status, bucket, (status, bucket) IS DISTINCT FROM ('live', 'core') AS not_core ORDER BY option_id LIMIT 4";
$deleteRetrySql = "DELETE FROM wp_options WHERE (status, bucket) IS NOT DISTINCT FROM ('retry', 'cache') RETURNING option_id, blog_id, option_name, status, bucket ORDER BY option_id LIMIT 1";

$summary = SQLiteRowValueSavepointReturningDistinctCurrentSourceNextPlan::execute(
    $tables,
    [$releaseSql],
    [$rollbackSql, $failingSql],
    [$retrySql, $deleteRetrySql],
    [['blog_id', 'option_name']],
    ['status', 'bucket'],
);

if (($summary['status'] ?? null) !== 'rolled-back-distinct-returning-retried') {
    fwrite(STDERR, "unexpected row-value DISTINCT RETURNING savepoint status\n");
    exit(1);
}
if (($summary['changes'] ?? null) !== 8 || ($summary['attempted_changes_before_rollback'] ?? null) !== 11) {
    fwrite(STDERR, "unexpected row-value DISTINCT RETURNING savepoint change counts\n");
    exit(1);
}
if (array_column($summary['current_source_tables']['wp_options'], 'option_id') !== [1, 2, 4, 5, 6, 7, 8, 9, 10]) {
    fwrite(STDERR, "unexpected row-value DISTINCT RETURNING savepoint current source\n");
    exit(1);
}

echo "wordpress-rowvalue-savepoint-returning-distinct-current-source-next self-test passed\n";

return [
    'scenario' => 'wordpress-rowvalue-savepoint-returning-distinct-current-source-next',
    'wordpressUse' => 'Copied wp_options import cleanup can use row-value IS DISTINCT FROM / IS NOT DISTINCT FROM predicates, yield de-duplicated RETURNING stream diagnostics, roll back attempted inner savepoint rows, and retry from the restored current source without ext/sqlite.',
    'status' => $summary['status'],
    'changes' => $summary['changes'],
    'attemptedChangesBeforeRollback' => $summary['attempted_changes_before_rollback'],
    'yieldedDistinctKeys' => $summary['yielded_distinct_keys'],
    'dependencyClosure' => 'no new support component needed; reuses native UPDATE/DELETE RETURNING row-value predicate evaluation and savepoint current-source modeling',
];
