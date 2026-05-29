<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan;

$rows = require __DIR__ . '/wordpress-rowvalue-returning-window-current-source-next268-fixture.php';
$yieldStatements = [
    "UPDATE wp_options SET (status, option_value, bytes) = ('yield305', option_value || ':yield305', bytes + 43) WHERE (blog_id, option_name) IN ((2, 'pending_theme'), (3, 'rewrite_rules')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id",
    "DELETE FROM wp_options WHERE (blog_id, option_name) IN ((1, '_transient_feed')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id",
];
$attemptStatements = [
    "UPDATE wp_options SET (status, option_value, bytes) = ('attempt305', option_value || ':attempt305', bytes + 13) WHERE (blog_id, option_name) IN ((2, 'pending_theme'), (3, 'rewrite_rules')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id",
    "DELETE FROM wp_options WHERE (blog_id, option_name) IN ((3, 'orphaned_cache')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id",
];
$retryStatements = [
    "UPDATE wp_options SET (status, option_value, bytes) = ('retry305', option_value || ':retry305', bytes + 37) WHERE (blog_id, option_name) IN ((2, 'pending_theme'), (3, 'rewrite_rules'), (4, 'plugin_batch')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id",
    "DELETE FROM wp_options WHERE (blog_id, option_name) IN ((1, '_transient_timeout_feed'), (4, 'home')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id",
];

$args = [['wp_options' => $rows], $yieldStatements, $attemptStatements, $retryStatements, [['blog_id', 'option_name']]];
$plan = SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan::executeCurrentSourceContinuationSeal(...$args);

assert($plan['status'] === 'rowvalue-update-delete-returning-window-current-source-continuation');
assert($plan['next305_ready'] === true);

$summary = [
    'status' => 'rowvalue-update-delete-returning-window-current-source-continuation',
    'candidateStatuses' => [$plan['status']],
    'sourceWindowHash' => $plan['next302_source_window']['next302_source_window'],
    'awaitedReadyRange' => $plan['next302_source_window']['awaited_ready_range'],
    'throughputAuditHash' => $plan['next303_throughput_audit']['next303_throughput_audit'],
    'keepsIndependentSlices' => $plan['next303_throughput_audit']['keeps_independent_follow_on_slices'],
    'isolationHash' => $plan['next304_isolation']['next304_isolation'],
    'finalSealHash' => $plan['next305_final']['next305_final'],
    'ready' => $plan['next305_ready'],
    'wordpressUse' => 'Copied wp_options imports can validate the row-value UPDATE/DELETE RETURNING window current-source continuation after the ready after-current handoff while keeping follow-on slices isolated.',
];

if (($argv[1] ?? null) === '--self-test') {
    echo "wordpress-rowvalue-returning-window-source-continuation-seal self-test passed\n";
    return;
}

return $summary;
