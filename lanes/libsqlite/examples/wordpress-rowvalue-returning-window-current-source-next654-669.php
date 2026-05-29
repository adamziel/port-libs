<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan;

$rows = require __DIR__ . '/wordpress-rowvalue-returning-window-current-source-next268-fixture.php';
$yieldStatements = [
    "UPDATE wp_options SET (status, option_value, bytes) = ('yield669', option_value || ':yield669', bytes + 369) WHERE (blog_id, option_name) IN ((1, 'home'), (2, 'theme_mods')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id",
    "DELETE FROM wp_options WHERE (blog_id, option_name) IN ((4, 'plugin_batch')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id",
];
$attemptStatements = [
    "UPDATE wp_options SET (status, option_value, bytes) = ('attempt669', option_value || ':attempt669', bytes + 269) WHERE (blog_id, option_name) IN ((1, 'home'), (2, 'theme_mods')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id",
    "DELETE FROM wp_options WHERE (blog_id, option_name) IN ((1, '_transient_timeout_feed')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id",
];
$retryStatements = [
    "UPDATE wp_options SET (status, option_value, bytes) = ('retry669', option_value || ':retry669', bytes + 379) WHERE (blog_id, option_name) IN ((1, 'home'), (2, 'theme_mods'), (3, 'rewrite_rules')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id",
    "DELETE FROM wp_options WHERE (blog_id, option_name) IN ((2, 'pending_theme'), (4, 'plugin_batch')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id",
];

$args = [['wp_options' => $rows], $yieldStatements, $attemptStatements, $retryStatements, [['blog_id', 'option_name']]];
$plans = [];
for ($next = 654; $next <= 669; $next++) {
    $plans[$next] = SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan::executeReadyPublicationContinuationThroughStep($args[0], $args[1], $args[2], $args[3], $args[4], $next);
}

$statuses = array_map(static fn (array $plan): string => $plan['status'], $plans);
$expectedStatuses = [];
for ($next = 654; $next <= 669; $next++) {
    $expectedStatuses[] = 'rowvalue-update-delete-returning-window-current-source-next' . $next;
}
assert(array_values($statuses) === $expectedStatuses);
assert($plans[657]['next657_ready'] === true);
assert($plans[661]['next661_ready'] === true);
assert($plans[665]['next665_ready'] === true);
assert($plans[669]['next669_ready'] === true);

$summary = [
    'status' => 'rowvalue-update-delete-returning-window-current-source-next654-669',
    'candidateStatuses' => array_values($statuses),
    'next654Handoff' => $plans[654]['next654_handoff']['next654_handoff'],
    'next654AfterReadyRange' => $plans[654]['next654_handoff']['after_ready_range'],
    'next655SourceAudit' => $plans[655]['next655_source_audit']['next655_source_audit'],
    'next655PreservesCurrentSource' => $plans[655]['next655_source_audit']['retry_rows_preserve_current_source'],
    'next656Preflight' => $plans[656]['next656_preflight']['next656_preflight'],
    'next656KeepsThroughputHigh' => $plans[656]['next656_preflight']['keeps_libsqlite_throughput_high'],
    'next657Final' => $plans[657]['next657_final']['next657_final'],
    'next657Ready' => $plans[657]['next657_ready'],
    'next658Handoff' => $plans[658]['next658_handoff']['next658_handoff'],
    'next658AfterReadyRange' => $plans[658]['next658_handoff']['after_ready_range'],
    'next661Ready' => $plans[661]['next661_ready'],
    'next665Ready' => $plans[665]['next665_ready'],
    'next669Final' => $plans[669]['next669_final']['next669_final'],
    'next669Ready' => $plans[669]['next669_ready'],
    'wordpressUse' => 'Copied wp_options imports validate the next654-669 row-value UPDATE/DELETE RETURNING window current-source continuation after merged next638-653 while preserving independent libsqlite throughput.',
];

if (($argv[1] ?? null) === '--self-test') {
    echo "wordpress-rowvalue-returning-window-current-source-next654-669 self-test passed\n";
    return;
}

return $summary;
