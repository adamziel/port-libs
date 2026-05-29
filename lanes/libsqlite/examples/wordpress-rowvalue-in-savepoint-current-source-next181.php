<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNextPlan;

$rows = [
    ['option_id' => 1, 'blog_id' => 1, 'option_name' => 'active_plugins', 'autoload' => 'yes', 'status' => null, 'bucket' => 'plugins', 'bytes' => 20, 'option_value' => 'a:0:{}'],
    ['option_id' => 2, 'blog_id' => 1, 'option_name' => 'rewrite_rules', 'autoload' => 'yes', 'status' => null, 'bucket' => 'rules', 'bytes' => 21, 'option_value' => 'rules'],
    ['option_id' => 3, 'blog_id' => 1, 'option_name' => '_transient_feed', 'autoload' => 'no', 'status' => null, 'bucket' => 'transient', 'bytes' => 9, 'option_value' => 'feed'],
    ['option_id' => 4, 'blog_id' => 2, 'option_name' => 'rewrite_rules', 'autoload' => 'yes', 'status' => null, 'bucket' => 'rules', 'bytes' => 32, 'option_value' => 'network-rules'],
    ['option_id' => 5, 'blog_id' => 4, 'option_name' => 'siteurl', 'autoload' => 'yes', 'status' => 'queued', 'bucket' => 'core', 'bytes' => 40, 'option_value' => 'https://queued.test'],
    ['option_id' => 6, 'blog_id' => 3, 'option_name' => 'pending_theme', 'autoload' => 'no', 'status' => 'pending', 'bucket' => null, 'bytes' => 7, 'option_value' => 'theme'],
];

$plan = SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNextPlan::executeNext173(
    ['wp_options' => $rows],
    [
        "UPDATE wp_options SET (option_name, status, option_value, bytes) = (option_name || ':stage181', status, option_value || ':stage181', bytes + 1) WHERE (blog_id, status, option_name) NOT IN ((1, NULL, 'active_plugins'), (2, NULL, 'rewrite_rules'), (4, 'queued', 'siteurl')) RETURNING option_id, option_name, status, (blog_id, status, option_name) NOT IN ((1, NULL, 'active_plugins'), (1, NULL, 'siteurl')) AS tuple_not_in ORDER BY option_id",
        "UPDATE OR FAIL wp_options SET (blog_id, option_name) = (1, 'active_plugins') WHERE option_id = 6 RETURNING option_id, blog_id, option_name",
    ],
    [
        "DELETE FROM wp_options WHERE (blog_id, status, option_name) NOT IN ((1, NULL, 'active_plugins'), (2, NULL, 'rewrite_rules'), (4, 'queued', 'siteurl')) RETURNING option_id, option_name, status, (blog_id, status, option_name) NOT IN ((1, NULL, 'active_plugins'), (1, NULL, 'siteurl')) AS tuple_not_in ORDER BY option_id",
        "UPDATE wp_options SET (status, option_value) = ('kept181', option_value || ':kept181') WHERE (blog_id, status, option_name) IN ((1, NULL, 'active_plugins'), (2, NULL, 'rewrite_rules'), (4, 'queued', 'siteurl')) RETURNING option_id, option_name, status, option_value ORDER BY option_id",
    ],
    [['blog_id', 'option_name']],
    'wp_options_rowvalue_in_retry_next181',
);

$summary = [
    'scenario' => 'wordpress-rowvalue-in-savepoint-current-source-next181',
    'wordpressUse' => 'Copied wp_options imports can use nullable row-value IN/NOT IN cleanup predicates, roll back speculative RETURNING rows after an OR FAIL conflict, and retry from the restored current source without ext/sqlite.',
    'status' => $plan['status'],
    'rolledBack' => $plan['rolled_back_to_savepoint'],
    'attemptedIds' => array_column($plan['attempted_returning_before_rollback'][0]['rows'], 'option_id'),
    'retryDeletedIds' => array_column($plan['yielded_returning'][0]['rows'], 'option_id'),
    'retryUpdatedIds' => array_column($plan['yielded_returning'][1]['rows'], 'option_id'),
    'finalIds' => array_column($plan['current_source_tables']['wp_options'], 'option_id'),
    'finalStatuses' => array_column($plan['current_source_tables']['wp_options'], 'status', 'option_id'),
    'dependencyClosure' => 'no new support component needed; reuses native UPDATE/DELETE RETURNING row-value predicates and savepoint current-source retry modeling',
];

if (PHP_SAPI === 'cli' && basename(__FILE__) === basename($_SERVER['SCRIPT_FILENAME'] ?? '')) {
    if (
        $summary['status'] !== 'fail-stream-rolled-back-retried-current-source-next173'
        || $summary['rolledBack'] !== true
        || $summary['attemptedIds'] !== [2, 3, 6]
        || $summary['retryDeletedIds'] !== [2, 3, 6]
        || $summary['retryUpdatedIds'] !== [5]
        || $summary['finalIds'] !== [1, 4, 5]
        || ($summary['finalStatuses'][5] ?? null) !== 'kept181'
    ) {
        fwrite(STDERR, "wordpress-rowvalue-in-savepoint-current-source-next181 self-test failed\n");
        exit(1);
    }

    echo "wordpress-rowvalue-in-savepoint-current-source-next181 self-test passed\n";
}

return $summary;
