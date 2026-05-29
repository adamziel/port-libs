<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan;

$originalArgv = $argv ?? [];
$argv = [];
$next263 = require __DIR__ . '/wordpress-rowvalue-peer-checkpoint-admission.php';
$argv = $originalArgv;

$rows = [
    ['option_id' => 1, 'blog_id' => 1, 'option_name' => 'siteurl', 'autoload' => 'yes', 'status' => 'live', 'bytes' => 20, 'option_value' => 'https://one.test'],
    ['option_id' => 2, 'blog_id' => 1, 'option_name' => 'home', 'autoload' => 'yes', 'status' => 'live', 'bytes' => 19, 'option_value' => 'https://home.test'],
    ['option_id' => 3, 'blog_id' => 1, 'option_name' => '_transient_feed', 'autoload' => 'no', 'status' => 'stale', 'bytes' => 12, 'option_value' => 'feed'],
    ['option_id' => 4, 'blog_id' => 1, 'option_name' => '_transient_timeout_feed', 'autoload' => 'no', 'status' => 'stale', 'bytes' => 13, 'option_value' => 'timeout'],
    ['option_id' => 5, 'blog_id' => 2, 'option_name' => 'pending_theme', 'autoload' => 'no', 'status' => 'queued', 'bytes' => 7, 'option_value' => 'theme'],
    ['option_id' => 6, 'blog_id' => 2, 'option_name' => 'siteurl', 'autoload' => 'yes', 'status' => 'live', 'bytes' => 25, 'option_value' => 'https://two.test'],
    ['option_id' => 7, 'blog_id' => 3, 'option_name' => 'rewrite_rules', 'autoload' => 'rewrite', 'status' => 'queued', 'bytes' => 9, 'option_value' => 'rules'],
    ['option_id' => 8, 'blog_id' => 3, 'option_name' => 'orphaned_cache', 'autoload' => 'yes', 'status' => 'orphaned', 'bytes' => 5, 'option_value' => 'cache'],
    ['option_id' => 9, 'blog_id' => 4, 'option_name' => 'plugin_batch', 'autoload' => 'no', 'status' => 'queued', 'bytes' => 11, 'option_value' => 'plugin'],
    ['option_id' => 10, 'blog_id' => 4, 'option_name' => 'home', 'autoload' => 'yes', 'status' => 'live', 'bytes' => 31, 'option_value' => 'https://four-home.test'],
];

$yieldUpdate = "UPDATE wp_options SET (status, option_value, bytes) = ('yield264', option_value || ':yield264', bytes + 30) WHERE (blog_id, option_name) IN ((2, 'pending_theme'), (3, 'rewrite_rules')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id";
$yieldDelete = "DELETE FROM wp_options WHERE (blog_id, option_name) IN ((1, '_transient_feed')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id";
$attemptUpdate = "UPDATE wp_options SET (status, option_value, bytes) = ('attempt264', option_value || ':attempt264', bytes + 5) WHERE (blog_id, option_name) IN ((2, 'pending_theme'), (3, 'rewrite_rules')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id";
$attemptDelete = "DELETE FROM wp_options WHERE (blog_id, option_name) IN ((3, 'orphaned_cache')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id";
$retryUpdate = "UPDATE wp_options SET (status, option_value, bytes) = ('retry264', option_value || ':retry264', bytes + 20) WHERE (blog_id, option_name) IN ((2, 'pending_theme'), (3, 'rewrite_rules'), (4, 'plugin_batch')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id";
$retryDelete = "DELETE FROM wp_options WHERE (blog_id, option_name) IN ((1, '_transient_timeout_feed'), (4, 'home')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id";

$plan = SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan::executeFinalReceiptAdmission(
    ['wp_options' => $rows],
    [$yieldUpdate, $yieldDelete],
    [$attemptUpdate, $attemptDelete],
    [$retryUpdate, $retryDelete],
    [['blog_id', 'option_name']],
);

assert($next263['checkpointCount'] === 4);
assert($plan['status'] === 'rowvalue-update-delete-returning-window-current-source-next264');
assert($plan['final_receipt_admission_next264']['final_receipts_complete'] === true);
assert($plan['final_receipt_admission_next264']['final_receipt_count'] === 8);

if (($argv[1] ?? null) === '--self-test') {
    echo "wordpress-rowvalue-final-receipt-admission self-test passed\n";
    return;
}

return [
    'status' => $plan['status'],
    'finalReceiptCount' => $plan['final_receipt_admission_next264']['final_receipt_count'],
    'finalReceiptsComplete' => $plan['final_receipt_admission_next264']['final_receipts_complete'],
    'finalState' => $plan['final_state_next264'],
    'dependencyClosure' => $plan['dependency_closure_next264'],
];
