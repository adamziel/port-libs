<?php

declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteRowValueUpdateDeleteReturningSavepointPlan;

$tables = [
    'wp_options' => [
        ['option_id' => 1, 'blog_id' => 1, 'option_name' => 'siteurl', 'autoload' => 'yes', 'status' => 'live', 'bytes' => 20, 'option_value' => 'https://old.test'],
        ['option_id' => 2, 'blog_id' => 1, 'option_name' => '_transient_feed', 'autoload' => 'no', 'status' => 'stale', 'bytes' => 12, 'option_value' => 'feed'],
        ['option_id' => 3, 'blog_id' => 1, 'option_name' => '_transient_timeout_feed', 'autoload' => 'no', 'status' => 'stale', 'bytes' => 13, 'option_value' => 'timeout'],
        ['option_id' => 4, 'blog_id' => 2, 'option_name' => 'pending_theme', 'autoload' => 'no', 'status' => null, 'bytes' => 7, 'option_value' => 'theme'],
        ['option_id' => 5, 'blog_id' => 3, 'option_name' => 'rewrite_rules', 'autoload' => 'yes', 'status' => 'queued', 'bytes' => 9, 'option_value' => 'rules'],
        ['option_id' => 6, 'blog_id' => 4, 'option_name' => 'siteurl', 'autoload' => 'yes', 'status' => 'live', 'bytes' => 30, 'option_value' => 'https://four.test'],
    ],
];

$summary = SQLiteRowValueUpdateDeleteReturningSavepointPlan::executeNestedIgnoreRetrySavepointBatch(
    $tables,
    ["UPDATE wp_options SET (option_name, status, option_value, bytes) = (option_name || ':outer168', 'outer', option_value || ':outer168', bytes + 2) WHERE option_id IN (4, 5) RETURNING option_id, option_name, status ORDER BY option_id"],
    [
        "UPDATE OR REPLACE wp_options SET (blog_id, option_name, status) = (4, 'siteurl', 'replace') WHERE option_id = 4 RETURNING option_id, blog_id, option_name, status",
        "DELETE FROM wp_options WHERE (blog_id, option_name) IN ((1, '_transient_feed'), (1, '_transient_timeout_feed')) RETURNING option_id, option_name ORDER BY option_id",
    ],
    [
        "UPDATE wp_options SET (option_name, status, option_value, bytes) = (option_name || ':retry168', 'retry', option_value || ':retry168', bytes + 10) WHERE option_id IN (4, 5) RETURNING option_id, option_name, status ORDER BY option_id",
        "DELETE FROM wp_options WHERE (blog_id, option_name) IN ((1, '_transient_feed'), (1, '_transient_timeout_feed')) RETURNING option_id, option_name ORDER BY option_id",
    ],
    [['blog_id', 'option_name']],
);

if (
    $summary['status'] !== 'outer-released-after-inner-rollback-to-retry-current-source-nested-ignore-retry'
    || $summary['discarded_inner_returning_count'] !== 3
    || $summary['yielded_inner_retry_returning_count'] !== 4
    || array_column($summary['current_source_tables']['wp_options'], 'option_id') !== [1, 4, 5, 6]
) {
    fwrite(STDERR, "unexpected nested row-value savepoint summary\n");
    exit(1);
}

if (PHP_SAPI === 'cli' && basename((string) ($_SERVER['SCRIPT_FILENAME'] ?? '')) === basename(__FILE__)) {
    echo "application-rowvalue-nested-savepoint-current-source-next168 self-test passed\n";
}

return [
    'scenario' => 'application-rowvalue-nested-savepoint-current-source-next168',
    'summary' => [
        'status' => $summary['status'],
        'outer_returning' => $summary['yielded_outer_returning_count'],
        'discarded_inner_returning' => $summary['discarded_inner_returning_count'],
        'yielded_retry_returning' => $summary['yielded_inner_retry_returning_count'],
        'final_option_ids' => array_column($summary['current_source_tables']['wp_options'], 'option_id'),
    ],
    'applicationUse' => 'Copied wp_options import cleanup can keep an outer savepoint batch, roll back an inner row-value UPDATE/DELETE RETURNING attempt, discard those speculative rows, and retry from the inner savepoint image without losing outer staged rows.',
    'dependencyClosure' => 'no new support component needed; this reuses native PHP row-value UPDATE/DELETE RETURNING conflict handling and bounded savepoint current-source modeling',
];
