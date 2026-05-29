<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan;

$rows = require __DIR__ . '/wordpress-rowvalue-returning-window-current-source-next268-fixture.php';
$yieldStatements = [
    "UPDATE wp_options SET (status, option_value, bytes) = ('yield861', option_value || ':yield861', bytes + 561) WHERE (blog_id, option_name) IN ((1, 'home'), (2, 'theme_mods')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id",
    "DELETE FROM wp_options WHERE (blog_id, option_name) IN ((4, 'plugin_batch')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id",
];
$attemptStatements = [
    "UPDATE wp_options SET (status, option_value, bytes) = ('attempt861', option_value || ':attempt861', bytes + 461) WHERE (blog_id, option_name) IN ((1, 'home'), (2, 'theme_mods')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id",
    "DELETE FROM wp_options WHERE (blog_id, option_name) IN ((1, '_transient_timeout_feed')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id",
];
$retryStatements = [
    "UPDATE wp_options SET (status, option_value, bytes) = ('retry861', option_value || ':retry861', bytes + 571) WHERE (blog_id, option_name) IN ((1, 'home'), (2, 'theme_mods'), (3, 'rewrite_rules')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id",
    "DELETE FROM wp_options WHERE (blog_id, option_name) IN ((2, 'pending_theme'), (4, 'plugin_batch')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id",
];

$args = [['wp_options' => $rows], $yieldStatements, $attemptStatements, $retryStatements, [['blog_id', 'option_name']]];
$plans = [];
for ($next = 846; $next <= 861; $next++) {
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
for ($next = 846; $next <= 861; $next++) {
    $expectedStatuses[] = 'rowvalue-update-delete-returning-window-current-source-next' . $next;
}
assert(array_values($statuses) === $expectedStatuses);
assert($plans[846]['next846_handoff']['next845_ready'] === true);
assert($plans[849]['next849_ready'] === true);
assert($plans[853]['next853_ready'] === true);
assert($plans[857]['next857_ready'] === true);
assert($plans[861]['next861_ready'] === true);

$summary = [
    'status' => 'rowvalue-update-delete-returning-window-current-source-next846-861',
    'candidateStatuses' => array_values($statuses),
    'next846Handoff' => $plans[846]['next846_handoff']['next846_handoff'],
    'next846AfterReadyRange' => $plans[846]['next846_handoff']['after_ready_range'],
    'next846ConsumesNext845Ready' => $plans[846]['next846_handoff']['next845_ready'],
    'next847SourceAudit' => $plans[847]['next847_source_audit']['next847_source_audit'],
    'next847PreservesCurrentSource' => $plans[847]['next847_source_audit']['retry_rows_preserve_current_source'],
    'next848Preflight' => $plans[848]['next848_preflight']['next848_preflight'],
    'next848KeepsThroughputHigh' => $plans[848]['next848_preflight']['keeps_libsqlite_throughput_high'],
    'next849Final' => $plans[849]['next849_final']['next849_final'],
    'next849Ready' => $plans[849]['next849_ready'],
    'next850Handoff' => $plans[850]['next850_handoff']['next850_handoff'],
    'next850AfterReadyRange' => $plans[850]['next850_handoff']['after_ready_range'],
    'next853Ready' => $plans[853]['next853_ready'],
    'next857Ready' => $plans[857]['next857_ready'],
    'next861Final' => $plans[861]['next861_final']['next861_final'],
    'next861Ready' => $plans[861]['next861_ready'],
    'wordpressUse' => 'Copied wp_options imports validate the next846-861 row-value UPDATE/DELETE RETURNING window current-source continuation as the direct handoff from next845_ready.',
];

if (($argv[1] ?? null) === '--self-test') {
    echo "wordpress-rowvalue-returning-window-current-source-next846-861 self-test passed\n";
    return;
}

return $summary;
