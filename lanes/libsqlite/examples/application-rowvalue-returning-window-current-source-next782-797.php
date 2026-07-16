<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan;

$rows = require __DIR__ . '/application-rowvalue-returning-window-current-source-next268-fixture.php';
$yieldStatements = [
    "UPDATE wp_options SET (status, option_value, bytes) = ('yield797', option_value || ':yield797', bytes + 497) WHERE (blog_id, option_name) IN ((1, 'home'), (2, 'theme_mods')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id",
    "DELETE FROM wp_options WHERE (blog_id, option_name) IN ((4, 'plugin_batch')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id",
];
$attemptStatements = [
    "UPDATE wp_options SET (status, option_value, bytes) = ('attempt797', option_value || ':attempt797', bytes + 397) WHERE (blog_id, option_name) IN ((1, 'home'), (2, 'theme_mods')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id",
    "DELETE FROM wp_options WHERE (blog_id, option_name) IN ((1, '_transient_timeout_feed')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id",
];
$retryStatements = [
    "UPDATE wp_options SET (status, option_value, bytes) = ('retry797', option_value || ':retry797', bytes + 507) WHERE (blog_id, option_name) IN ((1, 'home'), (2, 'theme_mods'), (3, 'rewrite_rules')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id",
    "DELETE FROM wp_options WHERE (blog_id, option_name) IN ((2, 'pending_theme'), (4, 'plugin_batch')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id",
];

$args = [['wp_options' => $rows], $yieldStatements, $attemptStatements, $retryStatements, [['blog_id', 'option_name']]];
$plans = [];
for ($next = 782; $next <= 797; $next++) {
    $plan = SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan::executeReadyPublicationContinuation($next, ...$args);
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
for ($next = 782; $next <= 797; $next++) {
    $expectedStatuses[] = 'rowvalue-update-delete-returning-window-current-source-next' . $next;
}
assert(array_values($statuses) === $expectedStatuses);
assert($plans[782]['next782_handoff']['next781_ready'] === true);
assert($plans[785]['next785_ready'] === true);
assert($plans[789]['next789_ready'] === true);
assert($plans[793]['next793_ready'] === true);
assert($plans[797]['next797_ready'] === true);

$summary = [
    'status' => 'rowvalue-update-delete-returning-window-current-source-next782-797',
    'candidateStatuses' => array_values($statuses),
    'next782Handoff' => $plans[782]['next782_handoff']['next782_handoff'],
    'next782AfterReadyRange' => $plans[782]['next782_handoff']['after_ready_range'],
    'next782ConsumesNext781Ready' => $plans[782]['next782_handoff']['next781_ready'],
    'next783SourceAudit' => $plans[783]['next783_source_audit']['next783_source_audit'],
    'next783PreservesCurrentSource' => $plans[783]['next783_source_audit']['retry_rows_preserve_current_source'],
    'next784Preflight' => $plans[784]['next784_preflight']['next784_preflight'],
    'next784KeepsThroughputHigh' => $plans[784]['next784_preflight']['keeps_libsqlite_throughput_high'],
    'next785Final' => $plans[785]['next785_final']['next785_final'],
    'next785Ready' => $plans[785]['next785_ready'],
    'next786Handoff' => $plans[786]['next786_handoff']['next786_handoff'],
    'next786AfterReadyRange' => $plans[786]['next786_handoff']['after_ready_range'],
    'next789Ready' => $plans[789]['next789_ready'],
    'next793Ready' => $plans[793]['next793_ready'],
    'next797Final' => $plans[797]['next797_final']['next797_final'],
    'next797Ready' => $plans[797]['next797_ready'],
    'applicationUse' => 'Copied wp_options imports validate the next782-797 row-value UPDATE/DELETE RETURNING window current-source continuation as the direct handoff from integrated next766-781.',
];

if (($argv[1] ?? null) === '--self-test') {
    echo "application-rowvalue-returning-window-current-source-next782-797 self-test passed\n";
    return;
}

return $summary;
