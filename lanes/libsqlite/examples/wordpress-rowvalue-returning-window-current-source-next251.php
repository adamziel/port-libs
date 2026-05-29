<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan;

$rows = [
    ['option_id' => 1, 'blog_id' => 1, 'option_name' => '_transient_feed', 'autoload' => 'no', 'status' => 'stale', 'bytes' => 12, 'option_value' => 'feed'],
    ['option_id' => 2, 'blog_id' => 1, 'option_name' => '_transient_timeout_feed', 'autoload' => 'no', 'status' => 'stale', 'bytes' => 13, 'option_value' => 'timeout'],
    ['option_id' => 3, 'blog_id' => 2, 'option_name' => 'pending_theme', 'autoload' => 'no', 'status' => 'queued', 'bytes' => 7, 'option_value' => 'theme'],
    ['option_id' => 4, 'blog_id' => 3, 'option_name' => 'rewrite_rules', 'autoload' => 'rewrite', 'status' => 'queued', 'bytes' => 9, 'option_value' => 'rules'],
    ['option_id' => 5, 'blog_id' => 4, 'option_name' => 'plugin_batch', 'autoload' => 'no', 'status' => 'queued', 'bytes' => 11, 'option_value' => 'plugin'],
    ['option_id' => 6, 'blog_id' => 4, 'option_name' => 'home', 'autoload' => 'yes', 'status' => 'live', 'bytes' => 31, 'option_value' => 'https://home.test'],
];

$yieldUpdate = "UPDATE wp_options SET (status, option_value, bytes) = ('yield251', option_value || ':yield251', bytes + 30) WHERE (blog_id, option_name) IN ((2, 'pending_theme'), (3, 'rewrite_rules')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id";
$yieldDelete = "DELETE FROM wp_options WHERE (blog_id, option_name) IN ((1, '_transient_feed')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id";
$attemptUpdate = "UPDATE wp_options SET (status, option_value, bytes) = ('attempt251', option_value || ':attempt251', bytes + 5) WHERE (blog_id, option_name) IN ((2, 'pending_theme'), (3, 'rewrite_rules')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id";
$attemptDelete = "DELETE FROM wp_options WHERE (blog_id, option_name) IN ((4, 'home')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id";
$retryUpdate = "UPDATE wp_options SET (status, option_value, bytes) = ('retry251', option_value || ':retry251', bytes + 20) WHERE (blog_id, option_name) IN ((2, 'pending_theme'), (3, 'rewrite_rules'), (4, 'plugin_batch')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id";
$retryDelete = "DELETE FROM wp_options WHERE (blog_id, option_name) IN ((1, '_transient_timeout_feed'), (4, 'home')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id";

$plan = SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan::executeNext251(
    ['wp_options' => $rows],
    [$yieldUpdate, $yieldDelete],
    [$attemptUpdate, $attemptDelete],
    [$retryUpdate, $retryDelete],
    [['blog_id', 'option_name']],
);

assert($plan['status'] === 'rowvalue-update-delete-returning-window-current-source-next251');
assert($plan['source_handoff_barrier_next251']['next_source_ready'] === true);
assert(array_column($plan['source_handoff_rows_next251'], 'option_id') === [4, 3, 1, 5, 6, 4, 3, 2]);
assert($plan['source_handoff_barrier_next251']['retry_visible_count'] === 5);

return [
    'status' => $plan['status'],
    'handoffState' => $plan['source_handoff_state_next251'],
    'handoffTickets' => $plan['source_handoff_tickets_next251'],
    'sourceEpochs' => array_column($plan['source_handoff_rows_next251'], 'source_epoch_next251'),
    'wordpressUse' => 'Copied wp_options import retries advance to next-source RETURNING rows only when the current-source window stream is drained and the next source digest matches the handoff watermark.',
    'dependencyClosure' => $plan['dependency_closure_next251'],
];
