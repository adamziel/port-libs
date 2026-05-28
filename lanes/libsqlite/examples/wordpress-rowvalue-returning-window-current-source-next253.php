<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNext253Plan;

$rows = [
    ['option_id' => 1, 'blog_id' => 1, 'option_name' => '_transient_feed', 'autoload' => 'no', 'status' => 'stale', 'bytes' => 12, 'option_value' => 'feed'],
    ['option_id' => 2, 'blog_id' => 1, 'option_name' => '_transient_timeout_feed', 'autoload' => 'no', 'status' => 'stale', 'bytes' => 13, 'option_value' => 'timeout'],
    ['option_id' => 3, 'blog_id' => 2, 'option_name' => 'pending_theme', 'autoload' => 'no', 'status' => 'queued', 'bytes' => 7, 'option_value' => 'theme'],
    ['option_id' => 4, 'blog_id' => 3, 'option_name' => 'rewrite_rules', 'autoload' => 'rewrite', 'status' => 'queued', 'bytes' => 9, 'option_value' => 'rules'],
    ['option_id' => 5, 'blog_id' => 4, 'option_name' => 'plugin_batch', 'autoload' => 'no', 'status' => 'queued', 'bytes' => 11, 'option_value' => 'plugin'],
    ['option_id' => 6, 'blog_id' => 4, 'option_name' => 'home', 'autoload' => 'yes', 'status' => 'live', 'bytes' => 31, 'option_value' => 'https://home.test'],
];

$yieldUpdate = "UPDATE wp_options SET (status, option_value, bytes) = ('yield253', option_value || ':yield253', bytes + 30) WHERE (blog_id, option_name) IN ((2, 'pending_theme'), (3, 'rewrite_rules')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id";
$yieldDelete = "DELETE FROM wp_options WHERE (blog_id, option_name) IN ((1, '_transient_feed')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id";
$attemptUpdate = "UPDATE wp_options SET (status, option_value, bytes) = ('attempt253', option_value || ':attempt253', bytes + 5) WHERE (blog_id, option_name) IN ((2, 'pending_theme'), (3, 'rewrite_rules')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id";
$attemptDelete = "DELETE FROM wp_options WHERE (blog_id, option_name) IN ((4, 'home')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id";
$retryUpdate = "UPDATE wp_options SET (status, option_value, bytes) = ('retry253', option_value || ':retry253', bytes + 20) WHERE (blog_id, option_name) IN ((2, 'pending_theme'), (3, 'rewrite_rules'), (4, 'plugin_batch')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id";
$retryDelete = "DELETE FROM wp_options WHERE (blog_id, option_name) IN ((1, '_transient_timeout_feed'), (4, 'home')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id";

$plan = SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNext253Plan::execute(
    ['wp_options' => $rows],
    [$yieldUpdate, $yieldDelete],
    [$attemptUpdate, $attemptDelete],
    [$retryUpdate, $retryDelete],
    [['blog_id', 'option_name']],
);

assert($plan['status'] === 'rowvalue-update-delete-returning-window-current-source-next253');
assert($plan['window_current_source_retry_exposed_next253'] === true);
assert($plan['window_current_source_cursor_rowids_next253'] === [4, 3, 1, 5, 6, 4, 3, 2]);
assert(strlen((string) $plan['window_current_source_release_token_next253']) === 64);

if (($argv[1] ?? null) === '--self-test') {
    echo "wordpress-rowvalue-returning-window-current-source-next253 self-test passed\n";
    return;
}

return [
    'status' => $plan['status'],
    'chunkGate' => $plan['window_current_source_chunk_gate_next253'],
    'cursorRowids' => $plan['window_current_source_cursor_rowids_next253'],
    'retryExposed' => $plan['window_current_source_retry_exposed_next253'],
    'wordpressUse' => 'Copied wp_options imports can keep next-source retry RETURNING rows hidden until current-source row-value UPDATE/DELETE window chunks have been acknowledged.',
    'dependencyClosure' => $plan['dependency_closure_next253'],
];
