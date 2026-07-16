<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan;

$rows = require __DIR__ . '/application-rowvalue-returning-window-current-source-next268-fixture.php';
$yieldStatements = [
    "UPDATE wp_options SET (status, option_value, bytes) = ('yield605', option_value || ':yield605', bytes + 307) WHERE (blog_id, option_name) IN ((1, 'home'), (2, 'theme_mods')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id",
    "DELETE FROM wp_options WHERE (blog_id, option_name) IN ((4, 'plugin_batch')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id",
];
$attemptStatements = [
    "UPDATE wp_options SET (status, option_value, bytes) = ('attempt605', option_value || ':attempt605', bytes + 223) WHERE (blog_id, option_name) IN ((1, 'home'), (2, 'theme_mods')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id",
    "DELETE FROM wp_options WHERE (blog_id, option_name) IN ((1, '_transient_timeout_feed')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id",
];
$retryStatements = [
    "UPDATE wp_options SET (status, option_value, bytes) = ('retry605', option_value || ':retry605', bytes + 311) WHERE (blog_id, option_name) IN ((1, 'home'), (2, 'theme_mods'), (3, 'rewrite_rules')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id",
    "DELETE FROM wp_options WHERE (blog_id, option_name) IN ((2, 'pending_theme'), (4, 'plugin_batch')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id",
];

$args = [['wp_options' => $rows], $yieldStatements, $attemptStatements, $retryStatements, [['blog_id', 'option_name']]];
$plans = [];
for ($next = 590; $next <= 605; $next++) {
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
for ($next = 590; $next <= 605; $next++) {
    $expectedStatuses[] = 'rowvalue-update-delete-returning-window-current-source-next' . $next;
}
assert(array_values($statuses) === $expectedStatuses);
assert($plans[593]['next593_ready'] === true);
assert($plans[597]['next597_ready'] === true);
assert($plans[601]['next601_ready'] === true);
assert($plans[605]['next605_ready'] === true);

$summary = [
    'status' => 'rowvalue-update-delete-returning-window-current-source-next590-605',
    'candidateStatuses' => array_values($statuses),
    'next590Handoff' => $plans[590]['next590_handoff']['next590_handoff'],
    'next590AfterReadyRange' => $plans[590]['next590_handoff']['after_ready_range'],
    'next591SourceAudit' => $plans[591]['next591_source_audit']['next591_source_audit'],
    'next591PreservesCurrentSource' => $plans[591]['next591_source_audit']['retry_rows_preserve_current_source'],
    'next592Preflight' => $plans[592]['next592_preflight']['next592_preflight'],
    'next592KeepsThroughputHigh' => $plans[592]['next592_preflight']['keeps_libsqlite_throughput_high'],
    'next593Final' => $plans[593]['next593_final']['next593_final'],
    'next593Ready' => $plans[593]['next593_ready'],
    'next594Handoff' => $plans[594]['next594_handoff']['next594_handoff'],
    'next594AfterReadyRange' => $plans[594]['next594_handoff']['after_ready_range'],
    'next597Ready' => $plans[597]['next597_ready'],
    'next601Ready' => $plans[601]['next601_ready'],
    'next605Final' => $plans[605]['next605_final']['next605_final'],
    'next605Ready' => $plans[605]['next605_ready'],
    'applicationUse' => 'Copied wp_options imports validate the next590-605 row-value UPDATE/DELETE RETURNING window current-source continuation after merged next574-589 while preserving independent libsqlite throughput.',
];

if (($argv[1] ?? null) === '--self-test') {
    echo "application-rowvalue-returning-window-current-source-next590-605 self-test passed\n";
    return;
}

return $summary;
