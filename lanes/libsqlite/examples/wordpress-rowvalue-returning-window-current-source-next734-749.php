<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan;

$rows = require __DIR__ . '/wordpress-rowvalue-returning-window-current-source-next268-fixture.php';
$yieldStatements = [
    "UPDATE wp_options SET (status, option_value, bytes) = ('yield749', option_value || ':yield749', bytes + 449) WHERE (blog_id, option_name) IN ((1, 'home'), (2, 'theme_mods')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id",
    "DELETE FROM wp_options WHERE (blog_id, option_name) IN ((4, 'plugin_batch')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id",
];
$attemptStatements = [
    "UPDATE wp_options SET (status, option_value, bytes) = ('attempt749', option_value || ':attempt749', bytes + 349) WHERE (blog_id, option_name) IN ((1, 'home'), (2, 'theme_mods')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id",
    "DELETE FROM wp_options WHERE (blog_id, option_name) IN ((1, '_transient_timeout_feed')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id",
];
$retryStatements = [
    "UPDATE wp_options SET (status, option_value, bytes) = ('retry749', option_value || ':retry749', bytes + 459) WHERE (blog_id, option_name) IN ((1, 'home'), (2, 'theme_mods'), (3, 'rewrite_rules')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id",
    "DELETE FROM wp_options WHERE (blog_id, option_name) IN ((2, 'pending_theme'), (4, 'plugin_batch')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id",
];

$args = [['wp_options' => $rows], $yieldStatements, $attemptStatements, $retryStatements, [['blog_id', 'option_name']]];
$plans = [];
for ($next = 734; $next <= 749; $next++) {
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
for ($next = 734; $next <= 749; $next++) {
    $expectedStatuses[] = 'rowvalue-update-delete-returning-window-current-source-next' . $next;
}
assert(array_values($statuses) === $expectedStatuses);
assert($plans[734]['next734_handoff']['next733_ready'] === true);
assert($plans[737]['next737_ready'] === true);
assert($plans[741]['next741_ready'] === true);
assert($plans[745]['next745_ready'] === true);
assert($plans[749]['next749_ready'] === true);

$summary = [
    'status' => 'rowvalue-update-delete-returning-window-current-source-next734-749',
    'candidateStatuses' => array_values($statuses),
    'next734Handoff' => $plans[734]['next734_handoff']['next734_handoff'],
    'next734AfterReadyRange' => $plans[734]['next734_handoff']['after_ready_range'],
    'next734ConsumesNext733Ready' => $plans[734]['next734_handoff']['next733_ready'],
    'next735SourceAudit' => $plans[735]['next735_source_audit']['next735_source_audit'],
    'next735PreservesCurrentSource' => $plans[735]['next735_source_audit']['retry_rows_preserve_current_source'],
    'next736Preflight' => $plans[736]['next736_preflight']['next736_preflight'],
    'next736KeepsThroughputHigh' => $plans[736]['next736_preflight']['keeps_libsqlite_throughput_high'],
    'next737Final' => $plans[737]['next737_final']['next737_final'],
    'next737Ready' => $plans[737]['next737_ready'],
    'next738Handoff' => $plans[738]['next738_handoff']['next738_handoff'],
    'next738AfterReadyRange' => $plans[738]['next738_handoff']['after_ready_range'],
    'next741Ready' => $plans[741]['next741_ready'],
    'next745Ready' => $plans[745]['next745_ready'],
    'next749Final' => $plans[749]['next749_final']['next749_final'],
    'next749Ready' => $plans[749]['next749_ready'],
    'wordpressUse' => 'Copied wp_options imports validate the next734-749 row-value UPDATE/DELETE RETURNING window current-source continuation after integrated next718-733 while preserving independent libsqlite throughput.',
];

if (($argv[1] ?? null) === '--self-test') {
    echo "wordpress-rowvalue-returning-window-current-source-next734-749 self-test passed\n";
    return;
}

return $summary;
