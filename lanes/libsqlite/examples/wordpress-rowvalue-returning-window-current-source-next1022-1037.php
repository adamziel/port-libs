<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan;

$rows = require __DIR__ . '/wordpress-rowvalue-returning-window-current-source-next268-fixture.php';
$yieldStatements = [
    "UPDATE wp_options SET (status, option_value, bytes) = ('yield1037', option_value || ':yield1037', bytes + 727) WHERE (blog_id, option_name) IN ((1, 'home'), (2, 'theme_mods')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id",
    "DELETE FROM wp_options WHERE (blog_id, option_name) IN ((4, 'plugin_batch')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id",
];
$attemptStatements = [
    "UPDATE wp_options SET (status, option_value, bytes) = ('attempt1037', option_value || ':attempt1037', bytes + 617) WHERE (blog_id, option_name) IN ((1, 'home'), (2, 'theme_mods')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id",
    "DELETE FROM wp_options WHERE (blog_id, option_name) IN ((1, '_transient_timeout_feed')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id",
];
$retryStatements = [
    "UPDATE wp_options SET (status, option_value, bytes) = ('retry1037', option_value || ':retry1037', bytes + 733) WHERE (blog_id, option_name) IN ((1, 'home'), (2, 'theme_mods'), (3, 'rewrite_rules')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id",
    "DELETE FROM wp_options WHERE (blog_id, option_name) IN ((2, 'pending_theme'), (4, 'plugin_batch')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id",
];

$args = [['wp_options' => $rows], $yieldStatements, $attemptStatements, $retryStatements, [['blog_id', 'option_name']]];
$plans = [];
for ($next = 1022; $next <= 1037; $next++) {
    $plans[$next] = SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan::executeReadyPublicationContinuation($next, ...$args);
}

$statuses = array_map(static fn (array $plan): string => $plan['status'], $plans);
$expectedStatuses = [];
for ($next = 1022; $next <= 1037; $next++) {
    $expectedStatuses[] = 'rowvalue-update-delete-returning-window-current-source-next' . $next;
}
assert(array_values($statuses) === $expectedStatuses);
assert($plans[1022]['next1022_handoff']['next1021_ready'] === true);
assert($plans[1025]['next1025_ready'] === true);
assert($plans[1029]['next1029_ready'] === true);
assert($plans[1033]['next1033_ready'] === true);
assert($plans[1037]['next1037_ready'] === true);

$summary = [
    'status' => 'rowvalue-update-delete-returning-window-current-source-next1022-1037',
    'candidateStatuses' => array_values($statuses),
    'next1022Handoff' => $plans[1022]['next1022_handoff']['next1022_handoff'],
    'next1022AfterReadyRange' => $plans[1022]['next1022_handoff']['after_ready_range'],
    'next1022ConsumesNext1021Ready' => $plans[1022]['next1022_handoff']['next1021_ready'],
    'next1023SourceAudit' => $plans[1023]['next1023_source_audit']['next1023_source_audit'],
    'next1023PreservesCurrentSource' => $plans[1023]['next1023_source_audit']['retry_rows_preserve_current_source'],
    'next1024Preflight' => $plans[1024]['next1024_preflight']['next1024_preflight'],
    'next1024KeepsThroughputHigh' => $plans[1024]['next1024_preflight']['keeps_libsqlite_throughput_high'],
    'next1025Final' => $plans[1025]['next1025_final']['next1025_final'],
    'next1025Ready' => $plans[1025]['next1025_ready'],
    'next1026Handoff' => $plans[1026]['next1026_handoff']['next1026_handoff'],
    'next1026AfterReadyRange' => $plans[1026]['next1026_handoff']['after_ready_range'],
    'next1029Ready' => $plans[1029]['next1029_ready'],
    'next1033Ready' => $plans[1033]['next1033_ready'],
    'next1037Final' => $plans[1037]['next1037_final']['next1037_final'],
    'next1037Ready' => $plans[1037]['next1037_ready'],
    'wordpressUse' => 'Copied wp_options imports validate the next1022-1037 row-value UPDATE/DELETE RETURNING window current-source continuation as the direct handoff from next1021_ready.',
];

if (($argv[1] ?? null) === '--self-test') {
    echo "wordpress-rowvalue-returning-window-current-source-next1022-1037 self-test passed\n";
    return;
}

return $summary;
