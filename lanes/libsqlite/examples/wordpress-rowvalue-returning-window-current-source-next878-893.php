<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan;

$rows = require __DIR__ . '/wordpress-rowvalue-returning-window-current-source-next268-fixture.php';
$yieldStatements = [
    "UPDATE wp_options SET (status, option_value, bytes) = ('yield893', option_value || ':yield893', bytes + 577) WHERE (blog_id, option_name) IN ((1, 'home'), (2, 'theme_mods')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id",
    "DELETE FROM wp_options WHERE (blog_id, option_name) IN ((4, 'plugin_batch')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id",
];
$attemptStatements = [
    "UPDATE wp_options SET (status, option_value, bytes) = ('attempt893', option_value || ':attempt893', bytes + 477) WHERE (blog_id, option_name) IN ((1, 'home'), (2, 'theme_mods')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id",
    "DELETE FROM wp_options WHERE (blog_id, option_name) IN ((1, '_transient_timeout_feed')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id",
];
$retryStatements = [
    "UPDATE wp_options SET (status, option_value, bytes) = ('retry893', option_value || ':retry893', bytes + 587) WHERE (blog_id, option_name) IN ((1, 'home'), (2, 'theme_mods'), (3, 'rewrite_rules')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id",
    "DELETE FROM wp_options WHERE (blog_id, option_name) IN ((2, 'pending_theme'), (4, 'plugin_batch')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id",
];

$args = [['wp_options' => $rows], $yieldStatements, $attemptStatements, $retryStatements, [['blog_id', 'option_name']]];
$plans = [];
for ($next = 878; $next <= 893; $next++) {
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
for ($next = 878; $next <= 893; $next++) {
    $expectedStatuses[] = 'rowvalue-update-delete-returning-window-current-source-next' . $next;
}
assert(array_values($statuses) === $expectedStatuses);
assert($plans[878]['next878_handoff']['next877_ready'] === true);
assert($plans[881]['next881_ready'] === true);
assert($plans[885]['next885_ready'] === true);
assert($plans[889]['next889_ready'] === true);
assert($plans[893]['next893_ready'] === true);

$summary = [
    'status' => 'rowvalue-update-delete-returning-window-current-source-next878-893',
    'candidateStatuses' => array_values($statuses),
    'next878Handoff' => $plans[878]['next878_handoff']['next878_handoff'],
    'next878AfterReadyRange' => $plans[878]['next878_handoff']['after_ready_range'],
    'next878ConsumesNext877Ready' => $plans[878]['next878_handoff']['next877_ready'],
    'next879SourceAudit' => $plans[879]['next879_source_audit']['next879_source_audit'],
    'next879PreservesCurrentSource' => $plans[879]['next879_source_audit']['retry_rows_preserve_current_source'],
    'next880Preflight' => $plans[880]['next880_preflight']['next880_preflight'],
    'next880KeepsThroughputHigh' => $plans[880]['next880_preflight']['keeps_libsqlite_throughput_high'],
    'next881Final' => $plans[881]['next881_final']['next881_final'],
    'next881Ready' => $plans[881]['next881_ready'],
    'next882Handoff' => $plans[882]['next882_handoff']['next882_handoff'],
    'next882AfterReadyRange' => $plans[882]['next882_handoff']['after_ready_range'],
    'next885Ready' => $plans[885]['next885_ready'],
    'next889Ready' => $plans[889]['next889_ready'],
    'next893Final' => $plans[893]['next893_final']['next893_final'],
    'next893Ready' => $plans[893]['next893_ready'],
    'wordpressUse' => 'Copied wp_options imports validate the next878-893 row-value UPDATE/DELETE RETURNING window current-source continuation as the direct handoff from next877_ready.',
];

if (($argv[1] ?? null) === '--self-test') {
    echo "wordpress-rowvalue-returning-window-current-source-next878-893 self-test passed\n";
    return;
}

return $summary;
