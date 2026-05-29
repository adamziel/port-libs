<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteRowValueUpdateDeleteReturningSavepointPlan;

$rows = [
    ['option_id' => 1, 'blog_id' => 1, 'option_name' => 'siteurl', 'autoload' => 'yes', 'status' => 'live', 'bytes' => 20, 'option_value' => 'https://one.test'],
    ['option_id' => 2, 'blog_id' => 1, 'option_name' => 'home', 'autoload' => 'yes', 'status' => 'live', 'bytes' => 21, 'option_value' => 'https://home.test'],
    ['option_id' => 3, 'blog_id' => 1, 'option_name' => '_transient_feed', 'autoload' => 'no', 'status' => 'stale', 'bytes' => 12, 'option_value' => 'feed'],
    ['option_id' => 4, 'blog_id' => 1, 'option_name' => '_transient_timeout_feed', 'autoload' => 'no-cache', 'status' => 'stale', 'bytes' => 13, 'option_value' => 'timeout'],
    ['option_id' => 5, 'blog_id' => 2, 'option_name' => 'siteurl', 'autoload' => 'yes', 'status' => 'live', 'bytes' => 25, 'option_value' => 'https://two.test'],
    ['option_id' => 6, 'blog_id' => 2, 'option_name' => 'home', 'autoload' => 'yes', 'status' => 'live', 'bytes' => 26, 'option_value' => 'https://two-home.test'],
    ['option_id' => 7, 'blog_id' => 2, 'option_name' => 'pending_theme', 'autoload' => 'no', 'status' => null, 'bytes' => 7, 'option_value' => 'theme'],
    ['option_id' => 8, 'blog_id' => 3, 'option_name' => 'rewrite_rules', 'autoload' => 'rewrite', 'status' => 'queued', 'bytes' => 9, 'option_value' => 'rules'],
    ['option_id' => 9, 'blog_id' => 3, 'option_name' => 'plugin_batch', 'autoload' => 'no', 'status' => 'queued', 'bytes' => 11, 'option_value' => 'plugin'],
    ['option_id' => 10, 'blog_id' => 4, 'option_name' => 'siteurl', 'autoload' => 'yes', 'status' => 'live', 'bytes' => 30, 'option_value' => 'https://four.test'],
];

$plan = SQLiteRowValueUpdateDeleteReturningSavepointPlan::executeParenthesizedRollbackRetrySavepoint(
    ['wp_options' => $rows],
    [
        "UPDATE wp_options SET (status, option_value, bytes) = ('attempt202', option_value || ':attempt202', bytes + 2) WHERE (((blog_id, option_name) = (1, 'siteurl')) OR ((blog_id, option_name) = (2, 'home'))) RETURNING option_id, blog_id, option_name, status, option_value, bytes, (((blog_id, option_name) = (blog_id, option_name))) AS same_tuple ORDER BY option_id",
        "DELETE FROM wp_options WHERE (((blog_id, option_name) IN (VALUES (1, '_transient_feed'), (1, '_transient_timeout_feed')))) RETURNING option_id, blog_id, option_name, (((blog_id, option_name) IN (VALUES (1, '_transient_feed'), (1, '_transient_timeout_feed')))) AS deleted_tuple ORDER BY option_id",
    ],
    [
        "UPDATE wp_options SET (status, option_value, bytes) = ('retry202', option_value || ':retry202', bytes + 5) WHERE (((blog_id, option_name) IS (1, 'siteurl')) OR ((blog_id, option_name) IS (2, 'home'))) RETURNING option_id, blog_id, option_name, status, option_value, bytes, (((blog_id, option_name) IS NOT DISTINCT FROM (blog_id, option_name))) AS stable_tuple ORDER BY option_id",
        "DELETE FROM wp_options WHERE (((blog_id, option_name) NOT IN (VALUES (1, 'siteurl'), (2, 'siteurl'), (2, 'home'), (3, 'rewrite_rules'), (3, 'plugin_batch'), (4, 'siteurl')))) RETURNING option_id, blog_id, option_name, (((blog_id, option_name) NOT IN (VALUES (1, 'siteurl'), (2, 'home')))) AS outside_keep ORDER BY option_id",
    ],
);

$output = [
    'scenario' => 'wordpress-rowvalue-parenthesized-savepoint-current-source-next202',
    'wordpressUse' => 'Executes generated WordPress wp_options cleanup SQL whose row-value predicates and RETURNING expressions are wrapped in extra parentheses, then rolls back the attempted batch and retries from the savepoint image.',
    'status' => $plan['status'],
    'savepoint' => $plan['savepoint'],
    'attemptReturnedIds' => array_merge(
        array_column($plan['attempt_statements'][0]['returning_rows'], 'option_id'),
        array_column($plan['attempt_statements'][1]['returning_rows'], 'option_id'),
    ),
    'retryReturnedIds' => array_merge(
        array_column($plan['retry_statements'][0]['returning_rows'], 'option_id'),
        array_column($plan['retry_statements'][1]['returning_rows'], 'option_id'),
    ),
    'finalOptionIds' => array_column($plan['current_source_tables']['wp_options'], 'option_id'),
    'dependencies' => $plan['dependencies'],
];

if (($argv[1] ?? '') === '--self-test') {
    assert($output['status'] === 'rowvalue-parenthesized-returning-savepoint-current-source-next202');
    assert($output['attemptReturnedIds'] === [1, 6, 3, 4]);
    assert($output['retryReturnedIds'] === [1, 6, 2, 3, 4, 7]);
    assert($output['finalOptionIds'] === [1, 5, 6, 8, 9, 10]);
}

echo json_encode($output, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
