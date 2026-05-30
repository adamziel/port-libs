<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan;

$rows = require __DIR__ . '/application-rowvalue-returning-window-current-source-next268-fixture.php';
$yieldStatements = [
    "UPDATE wp_options SET (status, option_value, bytes) = ('yield621', option_value || ':yield621', bytes + 313) WHERE (blog_id, option_name) IN ((1, 'home'), (2, 'theme_mods')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id",
    "DELETE FROM wp_options WHERE (blog_id, option_name) IN ((4, 'plugin_batch')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id",
];
$attemptStatements = [
    "UPDATE wp_options SET (status, option_value, bytes) = ('attempt621', option_value || ':attempt621', bytes + 227) WHERE (blog_id, option_name) IN ((1, 'home'), (2, 'theme_mods')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id",
    "DELETE FROM wp_options WHERE (blog_id, option_name) IN ((1, '_transient_timeout_feed')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id",
];
$retryStatements = [
    "UPDATE wp_options SET (status, option_value, bytes) = ('retry621', option_value || ':retry621', bytes + 317) WHERE (blog_id, option_name) IN ((1, 'home'), (2, 'theme_mods'), (3, 'rewrite_rules')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id",
    "DELETE FROM wp_options WHERE (blog_id, option_name) IN ((2, 'pending_theme'), (4, 'plugin_batch')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id",
];

$args = [['wp_options' => $rows], $yieldStatements, $attemptStatements, $retryStatements, [['blog_id', 'option_name']]];
$plans = [];
for ($next = 606; $next <= 621; $next++) {
    $plan = SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan::executeReadyPublicationContinuationThroughStep($args[0], $args[1], $args[2], $args[3], $args[4], $next);
    $plans[$next] = array_intersect_key($plan, array_flip([
        'status',
        'next' . $next . '_handoff',
        'next' . $next . '_source_audit',
        'next' . $next . '_preflight',
        'next' . $next . '_final',
        'next' . $next . '_ready',
    ]));
    unset($plan);
}

$statuses = array_map(static fn (array $plan): string => $plan['status'], $plans);
$expectedStatuses = [];
for ($next = 606; $next <= 621; $next++) {
    $expectedStatuses[] = 'rowvalue-update-delete-returning-window-current-source-next' . $next;
}
assert(array_values($statuses) === $expectedStatuses);
assert($plans[609]['next609_ready'] === true);
assert($plans[613]['next613_ready'] === true);
assert($plans[617]['next617_ready'] === true);
assert($plans[621]['next621_ready'] === true);

$summary = [
    'status' => 'rowvalue-update-delete-returning-window-current-source-next606-621',
    'candidateStatuses' => array_values($statuses),
    'next606Handoff' => $plans[606]['next606_handoff']['next606_handoff'],
    'next606AfterReadyRange' => $plans[606]['next606_handoff']['after_ready_range'],
    'next607SourceAudit' => $plans[607]['next607_source_audit']['next607_source_audit'],
    'next607PreservesCurrentSource' => $plans[607]['next607_source_audit']['retry_rows_preserve_current_source'],
    'next608Preflight' => $plans[608]['next608_preflight']['next608_preflight'],
    'next608KeepsThroughputHigh' => $plans[608]['next608_preflight']['keeps_libsqlite_throughput_high'],
    'next609Final' => $plans[609]['next609_final']['next609_final'],
    'next609Ready' => $plans[609]['next609_ready'],
    'next610Handoff' => $plans[610]['next610_handoff']['next610_handoff'],
    'next610AfterReadyRange' => $plans[610]['next610_handoff']['after_ready_range'],
    'next613Ready' => $plans[613]['next613_ready'],
    'next617Ready' => $plans[617]['next617_ready'],
    'next621Final' => $plans[621]['next621_final']['next621_final'],
    'next621Ready' => $plans[621]['next621_ready'],
    'applicationUse' => 'Copied wp_options imports validate the next606-621 row-value UPDATE/DELETE RETURNING window current-source continuation after merged next590-605 while preserving independent libsqlite throughput.',
];

if (($argv[1] ?? null) === '--self-test') {
    echo "application-rowvalue-returning-window-current-source-next606-621 self-test passed\n";
    return;
}

return $summary;
