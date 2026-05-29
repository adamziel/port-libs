<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan;

$rows = require __DIR__ . '/wordpress-rowvalue-returning-window-current-source-next268-fixture.php';
$yieldStatements = [
    "UPDATE wp_options SET (status, option_value, bytes) = ('yield813', option_value || ':yield813', bytes + 513) WHERE (blog_id, option_name) IN ((1, 'home'), (2, 'theme_mods')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id",
    "DELETE FROM wp_options WHERE (blog_id, option_name) IN ((4, 'plugin_batch')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id",
];
$attemptStatements = [
    "UPDATE wp_options SET (status, option_value, bytes) = ('attempt813', option_value || ':attempt813', bytes + 413) WHERE (blog_id, option_name) IN ((1, 'home'), (2, 'theme_mods')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id",
    "DELETE FROM wp_options WHERE (blog_id, option_name) IN ((1, '_transient_timeout_feed')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id",
];
$retryStatements = [
    "UPDATE wp_options SET (status, option_value, bytes) = ('retry813', option_value || ':retry813', bytes + 523) WHERE (blog_id, option_name) IN ((1, 'home'), (2, 'theme_mods'), (3, 'rewrite_rules')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id",
    "DELETE FROM wp_options WHERE (blog_id, option_name) IN ((2, 'pending_theme'), (4, 'plugin_batch')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id",
];

$args = [['wp_options' => $rows], $yieldStatements, $attemptStatements, $retryStatements, [['blog_id', 'option_name']]];
$plans = [];
for ($next = 798; $next <= 813; $next++) {
    $method = 'executeNext' . $next;
    $plans[$next] = SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan::{$method}(...$args);
}

$statuses = array_map(static fn (array $plan): string => $plan['status'], $plans);
$expectedStatuses = [];
for ($next = 798; $next <= 813; $next++) {
    $expectedStatuses[] = 'rowvalue-update-delete-returning-window-current-source-next' . $next;
}
assert(array_values($statuses) === $expectedStatuses);
assert($plans[798]['next798_handoff']['next797_ready'] === true);
assert($plans[801]['next801_ready'] === true);
assert($plans[805]['next805_ready'] === true);
assert($plans[809]['next809_ready'] === true);
assert($plans[813]['next813_ready'] === true);

$summary = [
    'status' => 'rowvalue-update-delete-returning-window-current-source-next798-813',
    'candidateStatuses' => array_values($statuses),
    'next798Handoff' => $plans[798]['next798_handoff']['next798_handoff'],
    'next798AfterReadyRange' => $plans[798]['next798_handoff']['after_ready_range'],
    'next798ConsumesNext797Ready' => $plans[798]['next798_handoff']['next797_ready'],
    'next799SourceAudit' => $plans[799]['next799_source_audit']['next799_source_audit'],
    'next799PreservesCurrentSource' => $plans[799]['next799_source_audit']['retry_rows_preserve_current_source'],
    'next800Preflight' => $plans[800]['next800_preflight']['next800_preflight'],
    'next800KeepsThroughputHigh' => $plans[800]['next800_preflight']['keeps_libsqlite_throughput_high'],
    'next801Final' => $plans[801]['next801_final']['next801_final'],
    'next801Ready' => $plans[801]['next801_ready'],
    'next802Handoff' => $plans[802]['next802_handoff']['next802_handoff'],
    'next802AfterReadyRange' => $plans[802]['next802_handoff']['after_ready_range'],
    'next805Ready' => $plans[805]['next805_ready'],
    'next809Ready' => $plans[809]['next809_ready'],
    'next813Final' => $plans[813]['next813_final']['next813_final'],
    'next813Ready' => $plans[813]['next813_ready'],
    'wordpressUse' => 'Copied wp_options imports validate the next798-813 row-value UPDATE/DELETE RETURNING window current-source continuation as the direct handoff from integrated next782-797.',
];

if (($argv[1] ?? null) === '--self-test') {
    echo "wordpress-rowvalue-returning-window-current-source-next798-813 self-test passed\n";
    return;
}

return $summary;
