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
$next302 = SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan::executeNext302(...$args);
$next303 = SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan::executeNext303(...$args);
$next304 = SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan::executeNext304(...$args);
$next305 = SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan::executeNext305(...$args);

$statuses = [$next302['status'], $next303['status'], $next304['status'], $next305['status']];
assert($statuses === [
    'rowvalue-update-delete-returning-window-current-source-next302',
    'rowvalue-update-delete-returning-window-current-source-next303',
    'rowvalue-update-delete-returning-window-current-source-next304',
    'rowvalue-update-delete-returning-window-current-source-next305',
]);
assert($next305['next305_ready'] === true);

$summary = [
    'status' => 'rowvalue-update-delete-returning-window-current-source-next302-305',
    'candidateStatuses' => $statuses,
    'next302SourceWindow' => $next302['next302_source_window']['next302_source_window'],
    'next302AwaitedReadyRange' => $next302['next302_source_window']['awaited_ready_range'],
    'next303ThroughputAudit' => $next303['next303_throughput_audit']['next303_throughput_audit'],
    'next303KeepsIndependentSlices' => $next303['next303_throughput_audit']['keeps_independent_follow_on_slices'],
    'next304Isolation' => $next304['next304_isolation']['next304_isolation'],
    'next305Final' => $next305['next305_final']['next305_final'],
    'next305Ready' => $next305['next305_ready'],
    'wordpressUse' => 'Copied wp_options imports can validate the next302-305 row-value UPDATE/DELETE RETURNING window current-source continuation after the ready next298-301 handoff while keeping follow-on slices isolated.',
];

if (($argv[1] ?? null) === '--self-test') {
    echo "wordpress-rowvalue-returning-window-current-source-next302-305 self-test passed\n";
    return;
}

return $summary;
