<?php

declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNextPlan;

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

$summary = SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNextPlan::executeNext177(
    $tables,
    ["UPDATE wp_options SET (status, option_value, bytes) = ('outer177', option_value || ':outer177', bytes + 2) WHERE option_id IN (4, 5) RETURNING option_id, option_name, status ORDER BY option_id"],
    ["UPDATE wp_options SET (status, option_value, bytes) = ('inner177', option_value || ':inner177', bytes + 3) WHERE option_id IN (4, 6) RETURNING option_id, option_name, status ORDER BY option_id"],
    [
        "DELETE FROM wp_options WHERE (blog_id, option_name) IN ((1, '_transient_feed'), (1, '_transient_timeout_feed')) RETURNING option_id, option_name ORDER BY option_id",
        "UPDATE OR REPLACE wp_options SET (blog_id, option_name, status, option_value, bytes) = (4, 'siteurl', 'discarded177', option_value || ':discarded177', bytes + 40) WHERE option_id = 4 RETURNING option_id, blog_id, option_name, status",
    ],
    [
        "UPDATE wp_options SET (status, option_value, bytes) = ('retry177', option_value || ':retry177', bytes + 5) WHERE (blog_id, option_name) IN ((2, 'pending_theme'), (3, 'orphaned_cache')) RETURNING option_id, blog_id, option_name, status ORDER BY option_id",
        "DELETE FROM wp_options WHERE (blog_id, option_name) IN ((1, '_transient_feed'), (1, '_transient_timeout_feed')) RETURNING option_id, option_name ORDER BY option_id",
    ],
    [['blog_id', 'option_name']],
);

if (
    $summary['status'] !== 'inner-rollback-to-retry-current-source-next177'
    || $summary['outer_yielded_returning_count'] !== 2
    || $summary['inner_suppressed_by_rollback_count'] !== 5
    || $summary['inner_yielded_after_retry_count'] !== 4
    || array_column($summary['current_source_tables']['wp_options'], 'option_id') !== [1, 4, 5, 6, 7]
) {
    fwrite(STDERR, "wordpress-rowvalue-inner-rollback-current-source-next177 self-test failed\n");
    exit(1);
}

if (PHP_SAPI === 'cli' && basename((string) ($_SERVER['SCRIPT_FILENAME'] ?? '')) === basename(__FILE__)) {
    echo "wordpress-rowvalue-inner-rollback-current-source-next177 self-test passed\n";
}

return [
    'scenario' => 'wordpress-rowvalue-inner-rollback-current-source-next177',
    'summary' => [
        'status' => $summary['status'],
        'outer_yielded_returning' => $summary['outer_yielded_returning_count'],
        'inner_suppressed_by_rollback' => $summary['inner_suppressed_by_rollback_count'],
        'inner_yielded_after_retry' => $summary['inner_yielded_after_retry_count'],
        'final_option_ids' => array_column($summary['current_source_tables']['wp_options'], 'option_id'),
    ],
    'wordpressUse' => 'Copied wp_options imports can keep outer row-value UPDATE RETURNING effects, roll back a speculative inner UPDATE/DELETE RETURNING cleanup to its inner savepoint image, and retry the inner batch before release.',
    'dependencyClosure' => 'no new support component needed; this reuses lane-local row-value UPDATE/DELETE RETURNING execution and bounded nested savepoint current-source modeling',
];
