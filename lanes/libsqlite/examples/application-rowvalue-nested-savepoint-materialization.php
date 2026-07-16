<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/SQLiteDatabase.php';
require_once __DIR__ . '/../src/SQLiteAffinityComparison.php';
require_once __DIR__ . '/../src/SQLiteSelectResult.php';
require_once __DIR__ . '/../src/SQLiteUpdateDeleteLimitPlan.php';
require_once __DIR__ . '/../src/SQLiteUpdateDeleteReturningSql.php';
require_once __DIR__ . '/../src/SQLiteRowValueUpdateDeleteReturningSavepointPlan.php';

use PortLibs\LibSqlite\SQLiteRowValueUpdateDeleteReturningSavepointPlan;

$rows = [
    ['option_id' => 1, 'blog_id' => 1, 'option_name' => 'siteurl', 'autoload' => 'yes', 'status' => 'live', 'bytes' => 20, 'option_value' => 'https://one.test'],
    ['option_id' => 2, 'blog_id' => 1, 'option_name' => 'home', 'autoload' => 'yes', 'status' => 'live', 'bytes' => 21, 'option_value' => 'https://home.test'],
    ['option_id' => 3, 'blog_id' => 1, 'option_name' => '_transient_feed', 'autoload' => 'no', 'status' => 'stale', 'bytes' => 12, 'option_value' => 'feed'],
    ['option_id' => 4, 'blog_id' => 1, 'option_name' => '_transient_timeout_feed', 'autoload' => 'no', 'status' => 'stale', 'bytes' => 13, 'option_value' => 'timeout'],
    ['option_id' => 5, 'blog_id' => 2, 'option_name' => 'siteurl', 'autoload' => 'yes', 'status' => 'live', 'bytes' => 25, 'option_value' => 'https://two.test'],
    ['option_id' => 6, 'blog_id' => 2, 'option_name' => 'home', 'autoload' => 'yes', 'status' => 'live', 'bytes' => 26, 'option_value' => 'https://two-home.test'],
    ['option_id' => 7, 'blog_id' => 2, 'option_name' => 'pending_theme', 'autoload' => 'no', 'status' => 'queued', 'bytes' => 7, 'option_value' => 'theme'],
    ['option_id' => 8, 'blog_id' => 3, 'option_name' => 'rewrite_rules', 'autoload' => 'yes', 'status' => 'queued', 'bytes' => 9, 'option_value' => 'rules'],
    ['option_id' => 9, 'blog_id' => 3, 'option_name' => 'plugin_batch', 'autoload' => 'no', 'status' => 'queued', 'bytes' => 11, 'option_value' => 'plugin'],
    ['option_id' => 10, 'blog_id' => 4, 'option_name' => 'siteurl', 'autoload' => 'yes', 'status' => 'live', 'bytes' => 30, 'option_value' => 'https://four.test'],
];

$plan = SQLiteRowValueUpdateDeleteReturningSavepointPlan::executeNestedSavepointMaterialization(
    ['wp_options' => $rows],
    [
        "UPDATE wp_options SET (status, option_value, bytes) = ('inner224', option_value || ':inner224', bytes + 2) WHERE (blog_id, option_name) IN ((2, 'pending_theme'), (3, 'rewrite_rules')) RETURNING option_id, blog_id, option_name, status, option_value, bytes ORDER BY option_id",
        "DELETE FROM wp_options WHERE (blog_id, option_name) IN (VALUES (1, '_transient_feed')) RETURNING option_id, blog_id, option_name, status ORDER BY option_id",
    ],
    [
        "UPDATE wp_options SET (status, option_value, bytes) = ('outer224', option_value || ':outer224', bytes + 4) WHERE (status, option_name) IN (('inner224', 'pending_theme'), ('inner224', 'rewrite_rules')) RETURNING option_id, blog_id, option_name, status, option_value, bytes ORDER BY option_id",
        "DELETE FROM wp_options WHERE (blog_id, option_name) IN ((1, '_transient_timeout_feed'), (3, 'plugin_batch')) RETURNING option_id, blog_id, option_name, status ORDER BY option_id",
    ],
    [
        "UPDATE wp_options SET (status, option_value, bytes) = ('retry224', option_value || ':retry224', bytes + 1) WHERE (blog_id, option_name) IN ((2, 'pending_theme'), (3, 'rewrite_rules'), (3, 'plugin_batch')) RETURNING option_id, blog_id, option_name, status, option_value, bytes ORDER BY option_id",
        "DELETE FROM wp_options WHERE (blog_id, option_name) IN (VALUES (1, '_transient_feed'), (4, 'siteurl')) RETURNING option_id, blog_id, option_name, status ORDER BY option_id",
    ],
    [['blog_id', 'option_name']],
);

$summary = [
    'scenario' => 'application-rowvalue-nested-savepoint-current-source-next224',
    'applicationUse' => 'Copied wp_options imports can RELEASE an inner row-value UPDATE/DELETE RETURNING batch, roll back the outer savepoint after a later validation failure, suppress both inner and outer attempted RETURNING rows, and retry from the outer savepoint image.',
    'status' => $plan['status'],
    'outerSavepoint' => $plan['outer_savepoint'],
    'innerSavepoint' => $plan['inner_savepoint'],
    'suppressedReturningCount' => $plan['suppressed_returning_count'],
    'retryReturningCount' => $plan['retry_returning_count'],
    'rowIdsAfterRetry' => array_column($plan['current_source_tables']['wp_options'], 'option_id'),
    'dependencies' => $plan['dependencies'],
    'dependencyClosure' => $plan['dependency_closure_next224'],
];

if (in_array('--self-test', $argv, true)) {
    assert($summary['status'] === 'rowvalue-update-delete-returning-nested-release-rollback-current-source-next224');
    assert($summary['suppressedReturningCount'] === 7);
    assert($summary['retryReturningCount'] === 5);
    assert($summary['rowIdsAfterRetry'] === [1, 2, 4, 5, 6, 7, 8, 9]);
    echo "application-rowvalue-nested-savepoint-materialization self-test passed\n";
    return;
}

echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
