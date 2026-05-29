<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/SQLiteUpdateDeleteLimitPlan.php';
require_once __DIR__ . '/../src/SQLiteDatabase.php';
require_once __DIR__ . '/../src/SQLiteSelectResult.php';
require_once __DIR__ . '/../src/SQLiteUpdateDeleteReturningSql.php';
require_once __DIR__ . '/../src/SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNextPlan.php';

use PortLibs\LibSqlite\SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNextPlan;

$rows = [
    ['option_id' => 1, 'blog_id' => 1, 'option_name' => 'siteurl', 'autoload' => 'yes', 'status' => 'live', 'bytes' => 20, 'option_value' => 'https://one.test'],
    ['option_id' => 2, 'blog_id' => 1, 'option_name' => 'home', 'autoload' => 'yes', 'status' => 'live', 'bytes' => 21, 'option_value' => 'https://home.test'],
    ['option_id' => 3, 'blog_id' => 1, 'option_name' => '_transient_feed', 'autoload' => 'no', 'status' => 'stale', 'bytes' => 12, 'option_value' => 'feed'],
    ['option_id' => 4, 'blog_id' => 1, 'option_name' => '_transient_timeout_feed', 'autoload' => 'no', 'status' => 'stale', 'bytes' => 13, 'option_value' => 'timeout'],
    ['option_id' => 5, 'blog_id' => 2, 'option_name' => 'siteurl', 'autoload' => 'yes', 'status' => 'live', 'bytes' => 25, 'option_value' => 'https://two.test'],
    ['option_id' => 6, 'blog_id' => 2, 'option_name' => 'home', 'autoload' => 'yes', 'status' => 'live', 'bytes' => 26, 'option_value' => 'https://two-home.test'],
    ['option_id' => 7, 'blog_id' => 2, 'option_name' => 'pending_theme', 'autoload' => 'no', 'status' => null, 'bytes' => 7, 'option_value' => 'theme'],
    ['option_id' => 8, 'blog_id' => 3, 'option_name' => 'rewrite_rules', 'autoload' => 'yes', 'status' => 'queued', 'bytes' => 9, 'option_value' => 'rules'],
    ['option_id' => 9, 'blog_id' => 3, 'option_name' => 'plugin_batch', 'autoload' => 'no', 'status' => 'queued', 'bytes' => 11, 'option_value' => 'plugin'],
    ['option_id' => 10, 'blog_id' => 4, 'option_name' => 'siteurl', 'autoload' => 'yes', 'status' => 'live', 'bytes' => 30, 'option_value' => 'https://four.test'],
    ['option_id' => 11, 'blog_id' => 4, 'option_name' => 'home', 'autoload' => 'yes', 'status' => 'live', 'bytes' => 31, 'option_value' => 'https://four-home.test'],
];

$outer = "UPDATE wp_options SET (status, option_value, bytes) = ('outer200', option_value || ':outer200', bytes + 1) WHERE (blog_id, option_name) IN (VALUES (1, 'siteurl'), (1, 'home')) RETURNING option_id, blog_id, option_name, status, option_value, bytes ORDER BY option_id";
$savepointUpdate = "UPDATE wp_options SET (status, option_value, bytes) = ('saved200', option_value || ':saved200', bytes + 2) WHERE (blog_id, option_name) IN ((2, 'pending_theme'), (3, 'rewrite_rules')) RETURNING option_id, blog_id, option_name, status, option_value, bytes ORDER BY option_id";
$savepointDelete = "DELETE FROM wp_options WHERE (blog_id, option_name) IN ((1, '_transient_feed')) RETURNING option_id, blog_id, option_name, status ORDER BY option_id";
$abort = "UPDATE OR ABORT wp_options SET (blog_id, option_name, status, option_value, bytes) = (4, 'siteurl', 'abort200', option_value || ':abort200', bytes + 20) WHERE option_id IN (8, 9) RETURNING option_id, blog_id, option_name, status, option_value, bytes ORDER BY option_id";
$retryUpdate = "UPDATE wp_options SET (status, option_value, bytes) = ('retry200', option_value || ':retry200', bytes + 5) WHERE (status, option_name) IN (('saved200', 'pending_theme'), ('saved200', 'rewrite_rules')) RETURNING option_id, blog_id, option_name, status, option_value, bytes ORDER BY option_id DESC";
$retryDelete = "DELETE FROM wp_options WHERE (blog_id, option_name) IN (VALUES (1, '_transient_timeout_feed'), (4, 'home')) RETURNING option_id, blog_id, option_name, status ORDER BY option_id";

$plan = SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNextPlan::executeNext200(
    ['wp_options' => $rows],
    [$outer],
    [$savepointUpdate, $savepointDelete],
    [$abort],
    [$retryUpdate, $retryDelete],
    [['blog_id', 'option_name']],
);

if (($argv[1] ?? '') === '--self-test') {
    $checks = [
        'statement aborted' => $plan['statement_aborted'] === true,
        'savepoint not rolled back' => $plan['rolled_back_to_savepoint'] === false,
        'savepoint yielded count' => $plan['savepoint_yielded_returning_count'] === 3,
        'abort suppressed count' => $plan['abort_suppressed_returning_count'] === 0,
        'retry yielded count' => $plan['yielded_after_retry_count'] === 4,
        'retry source statuses' => array_column($plan['retry_statements'][0]['source_rows'], 'status') === ['saved200', 'saved200'],
        'final option ids' => array_column($plan['current_source_tables']['wp_options'], 'option_id') === [1, 2, 5, 6, 7, 8, 9, 10],
    ];
    foreach ($checks as $label => $ok) {
        if (!$ok) {
            throw new RuntimeException("next200 smoke failed: {$label}");
        }
    }
    echo "wordpress-rowvalue-abort-savepoint-current-source-next200 self-test passed\n";
    return;
}

echo json_encode([
    'wordpressUse' => 'Model copied wp_options import cleanup where UPDATE OR ABORT suppresses the failed RETURNING statement but keeps earlier savepoint changes visible to retry DML.',
    'savepoint' => $plan['savepoint'],
    'statementAborted' => $plan['statement_aborted'],
    'savepointYielded' => $plan['savepoint_yielded_returning_count'],
    'abortSuppressed' => $plan['abort_suppressed_returning_count'],
    'yieldedAfterRetry' => $plan['yielded_after_retry_count'],
    'retrySourceStatuses' => array_column($plan['retry_statements'][0]['source_rows'], 'status'),
    'finalOptionIds' => array_column($plan['current_source_tables']['wp_options'], 'option_id'),
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
