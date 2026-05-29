<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/SQLiteUpdateDeleteLimitPlan.php';
require_once __DIR__ . '/../src/SQLiteSelectResult.php';
require_once __DIR__ . '/../src/SQLiteDatabase.php';
require_once __DIR__ . '/../src/SQLiteAffinityComparison.php';
require_once __DIR__ . '/../src/SQLiteUpdateDeleteReturningSql.php';
require_once __DIR__ . '/../src/SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNextPlan.php';

use PortLibs\LibSqlite\SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNextPlan;

$rows = [
    ['option_id' => 1, 'blog_id' => 1, 'option_name' => 'siteurl', 'autoload' => 'yes', 'status' => 'live', 'bytes' => 20, 'option_value' => 'https://old.test'],
    ['option_id' => 2, 'blog_id' => 1, 'option_name' => 'home', 'autoload' => 'yes', 'status' => 'live', 'bytes' => 21, 'option_value' => 'https://home.test'],
    ['option_id' => 3, 'blog_id' => 1, 'option_name' => '_transient_feed', 'autoload' => 'no', 'status' => 'stale', 'bytes' => 8, 'option_value' => 'feed'],
    ['option_id' => 4, 'blog_id' => 2, 'option_name' => '_transient_feed', 'autoload' => 'no', 'status' => 'stale', 'bytes' => 11, 'option_value' => 'network-feed'],
    ['option_id' => 5, 'blog_id' => 3, 'option_name' => 'rewrite_rules', 'autoload' => 'yes', 'status' => 'queued', 'bytes' => 14, 'option_value' => 'rules'],
    ['option_id' => 6, 'blog_id' => 4, 'option_name' => 'theme_mods', 'autoload' => 'yes', 'status' => 'queued', 'bytes' => 18, 'option_value' => 'theme'],
];

$release = "UPDATE wp_options SET (status, option_value, bytes) = ('released190', option_value || ':released190', bytes + 5) WHERE (blog_id, option_name) NOT IN ((1, 'siteurl'), (1, 'home')) AND autoload = 'yes' RETURNING option_id, blog_id, option_name, status, option_value, bytes ORDER BY option_id";
$cleanup = "DELETE FROM wp_options WHERE (blog_id, option_name) NOT BETWEEN (1, '_transient_feed') AND (1, 'zzzz') AND autoload = 'no' RETURNING option_id, blog_id, option_name ORDER BY option_id";
$speculative = "UPDATE wp_options SET (status, option_value) = ('speculative190', option_value || ':speculative190') WHERE (blog_id, option_name) NOT IN ((3, 'rewrite_rules'), (4, 'theme_mods')) AND autoload = 'yes' RETURNING option_id, blog_id, option_name, status";
$retry = "UPDATE wp_options SET (status, option_value) = ('retry190', option_value || ':retry190') WHERE (blog_id, option_name) NOT IN ((1, 'siteurl'), (3, 'rewrite_rules')) AND autoload = 'yes' RETURNING option_id, blog_id, option_name, status, option_value ORDER BY option_id";

$plan = SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNextPlan::executeNegatedRollbackRetrySavepoint(
    ['wp_options' => $rows],
    [$release],
    [$speculative],
    [$retry, $cleanup],
    [['blog_id', 'option_name']],
);

if (($argv[1] ?? null) === '--self-test') {
    assert($plan['status'] === 'rowvalue-negated-predicate-release-rollback-retry-next190');
    assert($plan['yielded_release_count'] === 2);
    assert($plan['suppressed_by_rollback_count'] === 1);
    assert($plan['yielded_after_retry_count'] === 3);
    assert(array_column($plan['current_source_tables']['wp_options'], 'option_id') === [1, 2, 3, 5, 6]);
    assert($plan['current_source_tables']['wp_options'][4]['option_value'] === 'theme:released190:retry190');
    echo "wordpress-rowvalue-negated-savepoint-current-source-next190 self-test passed\n";
    return;
}

echo json_encode([
    'status' => $plan['status'],
    'release_returning' => $plan['yielded_release_count'],
    'suppressed_returning' => $plan['suppressed_by_rollback_count'],
    'retry_returning' => $plan['yielded_after_retry_count'],
    'final_option_ids' => array_column($plan['current_source_tables']['wp_options'], 'option_id'),
], JSON_PRETTY_PRINT) . "\n";
