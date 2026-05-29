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
        ['option_id' => 6, 'blog_id' => 3, 'option_name' => 'orphaned_cache', 'autoload' => 'no', 'status' => 'staged', 'bytes' => 5, 'option_value' => 'cache'],
        ['option_id' => 7, 'blog_id' => 4, 'option_name' => 'siteurl', 'autoload' => 'yes', 'status' => 'live', 'bytes' => 30, 'option_value' => 'https://four.test'],
    ],
];

$summary = SQLiteRowValueUpdateDeleteReturningSavepointPlan::executeReleasedInnerRollbackRetrySavepoint(
    $tables,
    ["UPDATE wp_options SET (status, option_value, bytes) = ('outer174', option_value || ':outer174', bytes + 2) WHERE option_id IN (4, 5) RETURNING option_id, option_name, status ORDER BY option_id"],
    [
        "UPDATE OR REPLACE wp_options SET (blog_id, option_name, status, option_value, bytes) = (4, 'siteurl', 'released174', option_value || ':released174', bytes + 50) WHERE option_id = 4 RETURNING option_id, blog_id, option_name, status",
        "DELETE FROM wp_options WHERE (blog_id, option_name) IN ((1, '_transient_feed'), (1, '_transient_timeout_feed')) RETURNING option_id, option_name ORDER BY option_id",
    ],
    [
        "UPDATE wp_options SET (status, option_value, bytes) = ('retry174', option_value || ':retry174', bytes + 5) WHERE (blog_id, option_name) IN ((2, 'pending_theme'), (3, 'orphaned_cache')) RETURNING option_id, blog_id, option_name, status ORDER BY option_id",
        "DELETE FROM wp_options WHERE (blog_id, option_name) IN ((1, '_transient_feed'), (1, '_transient_timeout_feed')) RETURNING option_id, option_name ORDER BY option_id",
    ],
    [['blog_id', 'option_name']],
);

if (
    $summary['status'] !== 'inner-released-outer-rollback-to-retry-current-source-next174'
    || $summary['discarded_outer_returning_count'] !== 2
    || $summary['discarded_inner_released_returning_count'] !== 3
    || $summary['yielded_retry_returning_count'] !== 4
    || array_column($summary['current_source_tables']['wp_options'], 'option_id') !== [1, 4, 5, 6, 7]
) {
    fwrite(STDERR, "wordpress-rowvalue-released-inner-rollback-current-source-next174 self-test failed\n");
    exit(1);
}

if (PHP_SAPI === 'cli' && basename((string) ($_SERVER['SCRIPT_FILENAME'] ?? '')) === basename(__FILE__)) {
    echo "wordpress-rowvalue-released-inner-rollback-current-source-next174 self-test passed\n";
}

return [
    'scenario' => 'wordpress-rowvalue-released-inner-rollback-current-source-next174',
    'summary' => [
        'status' => $summary['status'],
        'discarded_outer_returning' => $summary['discarded_outer_returning_count'],
        'discarded_inner_released_returning' => $summary['discarded_inner_released_returning_count'],
        'yielded_retry_returning' => $summary['yielded_retry_returning_count'],
        'final_option_ids' => array_column($summary['current_source_tables']['wp_options'], 'option_id'),
    ],
    'wordpressUse' => 'Copied wp_options import cleanup can release an inner row-value UPDATE/DELETE RETURNING segment into an outer savepoint, roll back the outer batch, discard those released inner rows, and retry from the original current source.',
    'dependencyClosure' => 'no new support component needed; this reuses lane-local row-value UPDATE/DELETE RETURNING execution and bounded savepoint current-source modeling',
];
