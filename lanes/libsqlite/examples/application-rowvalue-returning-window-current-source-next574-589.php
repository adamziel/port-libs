<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan;

$rows = require __DIR__ . '/application-rowvalue-returning-window-current-source-next268-fixture.php';
$yieldStatements = [
    "UPDATE wp_options SET (status, option_value, bytes) = ('yield589', option_value || ':yield589', bytes + 281) WHERE (blog_id, option_name) IN ((1, 'blogname'), (4, 'plugin_batch')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id",
    "DELETE FROM wp_options WHERE (blog_id, option_name) IN ((2, 'theme_mods')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id",
];
$attemptStatements = [
    "UPDATE wp_options SET (status, option_value, bytes) = ('attempt589', option_value || ':attempt589', bytes + 211) WHERE (blog_id, option_name) IN ((1, 'blogname'), (4, 'plugin_batch')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id",
    "DELETE FROM wp_options WHERE (blog_id, option_name) IN ((3, 'rewrite_rules')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id",
];
$retryStatements = [
    "UPDATE wp_options SET (status, option_value, bytes) = ('retry589', option_value || ':retry589', bytes + 283) WHERE (blog_id, option_name) IN ((1, 'blogname'), (2, 'pending_theme'), (4, 'plugin_batch')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id",
    "DELETE FROM wp_options WHERE (blog_id, option_name) IN ((1, '_transient_feed'), (3, 'orphaned_cache')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id",
];

$args = [['wp_options' => $rows], $yieldStatements, $attemptStatements, $retryStatements, [['blog_id', 'option_name']]];
$plans = [];
for ($next = 574; $next <= 589; $next++) {
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
for ($next = 574; $next <= 589; $next++) {
    $expectedStatuses[] = 'rowvalue-update-delete-returning-window-current-source-next' . $next;
}
assert(array_values($statuses) === $expectedStatuses);
assert($plans[577]['next577_ready'] === true);
assert($plans[581]['next581_ready'] === true);
assert($plans[585]['next585_ready'] === true);
assert($plans[589]['next589_ready'] === true);

$summary = [
    'status' => 'rowvalue-update-delete-returning-window-current-source-next574-589',
    'candidateStatuses' => array_values($statuses),
    'next574Handoff' => $plans[574]['next574_handoff']['next574_handoff'],
    'next574AfterReadyRange' => $plans[574]['next574_handoff']['after_ready_range'],
    'next575SourceAudit' => $plans[575]['next575_source_audit']['next575_source_audit'],
    'next575PreservesCurrentSource' => $plans[575]['next575_source_audit']['retry_rows_preserve_current_source'],
    'next576Preflight' => $plans[576]['next576_preflight']['next576_preflight'],
    'next576KeepsThroughputHigh' => $plans[576]['next576_preflight']['keeps_libsqlite_throughput_high'],
    'next577Final' => $plans[577]['next577_final']['next577_final'],
    'next577Ready' => $plans[577]['next577_ready'],
    'next578Handoff' => $plans[578]['next578_handoff']['next578_handoff'],
    'next578AfterReadyRange' => $plans[578]['next578_handoff']['after_ready_range'],
    'next581Ready' => $plans[581]['next581_ready'],
    'next585Ready' => $plans[585]['next585_ready'],
    'next589Final' => $plans[589]['next589_final']['next589_final'],
    'next589Ready' => $plans[589]['next589_ready'],
    'applicationUse' => 'Copied wp_options imports validate the next574-589 row-value UPDATE/DELETE RETURNING window current-source continuation after merged next558-573 while preserving independent libsqlite throughput.',
];

if (($argv[1] ?? null) === '--self-test') {
    echo "application-rowvalue-returning-window-current-source-next574-589 self-test passed\n";
    return;
}

return $summary;
