<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan;

$rows = require __DIR__ . '/wordpress-rowvalue-returning-window-current-source-next268-fixture.php';
$yieldStatements = [
    "UPDATE wp_options SET (status, option_value, bytes) = ('yield765', option_value || ':yield765', bytes + 465) WHERE (blog_id, option_name) IN ((1, 'home'), (2, 'theme_mods')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id",
    "DELETE FROM wp_options WHERE (blog_id, option_name) IN ((4, 'plugin_batch')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id",
];
$attemptStatements = [
    "UPDATE wp_options SET (status, option_value, bytes) = ('attempt765', option_value || ':attempt765', bytes + 365) WHERE (blog_id, option_name) IN ((1, 'home'), (2, 'theme_mods')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id",
    "DELETE FROM wp_options WHERE (blog_id, option_name) IN ((1, '_transient_timeout_feed')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id",
];
$retryStatements = [
    "UPDATE wp_options SET (status, option_value, bytes) = ('retry765', option_value || ':retry765', bytes + 475) WHERE (blog_id, option_name) IN ((1, 'home'), (2, 'theme_mods'), (3, 'rewrite_rules')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id",
    "DELETE FROM wp_options WHERE (blog_id, option_name) IN ((2, 'pending_theme'), (4, 'plugin_batch')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id",
];

$args = [['wp_options' => $rows], $yieldStatements, $attemptStatements, $retryStatements, [['blog_id', 'option_name']]];
$plans = [];
for ($next = 750; $next <= 765; $next++) {
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
for ($next = 750; $next <= 765; $next++) {
    $expectedStatuses[] = 'rowvalue-update-delete-returning-window-current-source-next' . $next;
}
assert(array_values($statuses) === $expectedStatuses);
assert($plans[750]['next750_handoff']['next749_ready'] === true);
assert($plans[753]['next753_ready'] === true);
assert($plans[757]['next757_ready'] === true);
assert($plans[761]['next761_ready'] === true);
assert($plans[765]['next765_ready'] === true);

$summary = [
    'status' => 'rowvalue-update-delete-returning-window-current-source-next750-765',
    'candidateStatuses' => array_values($statuses),
    'next750Handoff' => $plans[750]['next750_handoff']['next750_handoff'],
    'next750AfterReadyRange' => $plans[750]['next750_handoff']['after_ready_range'],
    'next750ConsumesNext749Ready' => $plans[750]['next750_handoff']['next749_ready'],
    'next751SourceAudit' => $plans[751]['next751_source_audit']['next751_source_audit'],
    'next751PreservesCurrentSource' => $plans[751]['next751_source_audit']['retry_rows_preserve_current_source'],
    'next752Preflight' => $plans[752]['next752_preflight']['next752_preflight'],
    'next752KeepsThroughputHigh' => $plans[752]['next752_preflight']['keeps_libsqlite_throughput_high'],
    'next753Final' => $plans[753]['next753_final']['next753_final'],
    'next753Ready' => $plans[753]['next753_ready'],
    'next754Handoff' => $plans[754]['next754_handoff']['next754_handoff'],
    'next754AfterReadyRange' => $plans[754]['next754_handoff']['after_ready_range'],
    'next757Ready' => $plans[757]['next757_ready'],
    'next761Ready' => $plans[761]['next761_ready'],
    'next765Final' => $plans[765]['next765_final']['next765_final'],
    'next765Ready' => $plans[765]['next765_ready'],
    'wordpressUse' => 'Copied wp_options imports validate the next750-765 row-value UPDATE/DELETE RETURNING window current-source continuation as the direct handoff from integrated next734-749.',
];

if (($argv[1] ?? null) === '--self-test') {
    echo "wordpress-rowvalue-returning-window-current-source-next750-765 self-test passed\n";
    return;
}

return $summary;
