<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteRowValueUpdateDeleteReturningSavepointPlan;

$rows = [
    ['option_id' => 1, 'blog_id' => 1, 'option_name' => 'siteurl', 'autoload' => 'yes', 'status' => 'live', 'bytes' => 20, 'option_value' => 'https://one.test'],
    ['option_id' => 2, 'blog_id' => 1, 'option_name' => 'home', 'autoload' => 'yes', 'status' => 'live', 'bytes' => 21, 'option_value' => 'https://home.test'],
    ['option_id' => 3, 'blog_id' => 1, 'option_name' => '_transient_feed', 'autoload' => 'no', 'status' => 'stale', 'bytes' => 12, 'option_value' => 'feed'],
    ['option_id' => 4, 'blog_id' => 1, 'option_name' => '_transient_timeout_feed', 'autoload' => 'no', 'status' => 'stale', 'bytes' => 13, 'option_value' => 'timeout'],
    ['option_id' => 7, 'blog_id' => 2, 'option_name' => 'pending_theme', 'autoload' => 'no', 'status' => null, 'bytes' => 7, 'option_value' => 'theme'],
    ['option_id' => 8, 'blog_id' => 3, 'option_name' => 'rewrite_rules', 'autoload' => 'no', 'status' => 'queued', 'bytes' => 9, 'option_value' => 'rules'],
    ['option_id' => 9, 'blog_id' => 3, 'option_name' => 'plugin_batch', 'autoload' => 'no', 'status' => 'queued', 'bytes' => 11, 'option_value' => 'plugin'],
];

$plan = SQLiteRowValueUpdateDeleteReturningSavepointPlan::executeOrderExpressionSavepointRetry(
    ['wp_options' => $rows],
    [
        "UPDATE wp_options SET (status, option_value, bytes) = ('attempt199', option_value || ':attempt199', bytes + 1) WHERE autoload = 'no' RETURNING option_id, blog_id, option_name, status, option_value, bytes ORDER BY (blog_id, option_name) IS (3, 'rewrite_rules') DESC, bytes DESC LIMIT 2",
        "DELETE FROM wp_options WHERE autoload = 'no' RETURNING option_id, blog_id, option_name, status ORDER BY (status, option_name) IS ('attempt199', 'rewrite_rules') DESC, bytes ASC LIMIT 1",
    ],
    [
        "UPDATE wp_options SET (status, option_value, bytes) = ('retry199', option_value || ':retry199', bytes + 5) WHERE autoload = 'no' RETURNING option_id, blog_id, option_name, status, option_value, bytes ORDER BY (blog_id, option_name) IS (3, 'rewrite_rules') DESC, bytes DESC LIMIT 5",
        "DELETE FROM wp_options WHERE autoload = 'no' RETURNING option_id, blog_id, option_name, status ORDER BY (status, option_name) IS ('retry199', 'plugin_batch') DESC, option_id ASC LIMIT 2",
    ],
);

$summary = [
    'scenario' => 'wordpress-rowvalue-order-expression-savepoint-current-source-next199',
    'wordpressUse' => 'A copied wp_options cleanup can prioritize UPDATE/DELETE targets with row-value ORDER BY expressions, roll back a speculative RETURNING stream, and retry from the restored current source without exposing hidden order keys.',
    'status' => $plan['status'],
    'attemptSelected' => [
        $plan['attempt_statements'][0]['selected_ids'],
        $plan['attempt_statements'][1]['selected_ids'],
    ],
    'retrySelected' => [
        $plan['retry_statements'][0]['selected_ids'],
        $plan['retry_statements'][1]['selected_ids'],
    ],
    'suppressedReturning' => $plan['suppressed_by_rollback_count'],
    'yieldedAfterRetry' => $plan['yielded_after_retry_count'],
    'finalOptionIds' => array_column($plan['current_source_tables']['wp_options'], 'option_id'),
    'dependencyClosure' => 'no new support component needed; reuses native UPDATE/DELETE RETURNING, row-value expression evaluation, and savepoint current-source modeling',
];

if (in_array('--self-test', $argv, true)) {
    if (
        $summary['status'] !== 'rowvalue-order-expression-returning-rolled-back-retried-next199'
        || $summary['attemptSelected'] !== [[8, 4], [8]]
        || $summary['retrySelected'] !== [[8, 4, 3, 9, 7], [9, 3]]
        || $summary['suppressedReturning'] !== 3
        || $summary['yieldedAfterRetry'] !== 7
        || in_array(3, $summary['finalOptionIds'], true)
        || in_array(9, $summary['finalOptionIds'], true)
    ) {
        fwrite(STDERR, "wordpress-rowvalue-order-expression-savepoint-current-source-next199 self-test failed\n");
        exit(1);
    }

    echo "wordpress-rowvalue-order-expression-savepoint-current-source-next199 self-test passed\n";
    exit(0);
}

echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
