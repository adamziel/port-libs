<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan;

$rows = require __DIR__ . '/wordpress-rowvalue-returning-window-current-source-next268-fixture.php';
$yieldStatements = [
    "UPDATE wp_options SET (status, option_value, bytes) = ('yield493', option_value || ':yield493', bytes + 193) WHERE (blog_id, option_name) IN ((2, 'pending_theme'), (3, 'rewrite_rules')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id",
    "DELETE FROM wp_options WHERE (blog_id, option_name) IN ((1, '_transient_feed')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id",
];
$attemptStatements = [
    "UPDATE wp_options SET (status, option_value, bytes) = ('attempt493', option_value || ':attempt493', bytes + 129) WHERE (blog_id, option_name) IN ((2, 'pending_theme'), (3, 'rewrite_rules')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id",
    "DELETE FROM wp_options WHERE (blog_id, option_name) IN ((3, 'orphaned_cache')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id",
];
$retryStatements = [
    "UPDATE wp_options SET (status, option_value, bytes) = ('retry493', option_value || ':retry493', bytes + 191) WHERE (blog_id, option_name) IN ((2, 'pending_theme'), (3, 'rewrite_rules'), (4, 'plugin_batch')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id",
    "DELETE FROM wp_options WHERE (blog_id, option_name) IN ((1, '_transient_timeout_feed'), (4, 'home')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id",
];

$args = [['wp_options' => $rows], $yieldStatements, $attemptStatements, $retryStatements, [['blog_id', 'option_name']]];
$plans = [];
for ($next = 478; $next <= 493; $next++) {
    $plans[$next] = SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan::executeWindowCurrentSourceContinuation($next, ...$args);
}

$statuses = array_map(static fn (array $plan): string => $plan['status'], $plans);
$expectedStatuses = [];
for ($next = 478; $next <= 493; $next++) {
    $expectedStatuses[] = 'rowvalue-update-delete-returning-window-current-source-next' . $next;
}
assert(array_values($statuses) === $expectedStatuses);
assert($plans[481]['next481_ready'] === true);
assert($plans[485]['next485_ready'] === true);
assert($plans[489]['next489_ready'] === true);
assert($plans[493]['next493_ready'] === true);

$summary = [
    'status' => 'rowvalue-update-delete-returning-window-current-source-next478-493',
    'candidateStatuses' => array_values($statuses),
    'next478Handoff' => $plans[478]['next478_handoff']['next478_handoff'],
    'next478AfterReadyRange' => $plans[478]['next478_handoff']['after_ready_range'],
    'next479SourceAudit' => $plans[479]['next479_source_audit']['next479_source_audit'],
    'next479PreservesCurrentSource' => $plans[479]['next479_source_audit']['retry_rows_preserve_current_source'],
    'next480Preflight' => $plans[480]['next480_preflight']['next480_preflight'],
    'next480KeepsThroughputHigh' => $plans[480]['next480_preflight']['keeps_libsqlite_throughput_high'],
    'next481Final' => $plans[481]['next481_final']['next481_final'],
    'next481Ready' => $plans[481]['next481_ready'],
    'next482Handoff' => $plans[482]['next482_handoff']['next482_handoff'],
    'next482AfterReadyRange' => $plans[482]['next482_handoff']['after_ready_range'],
    'next485Ready' => $plans[485]['next485_ready'],
    'next489Ready' => $plans[489]['next489_ready'],
    'next493Final' => $plans[493]['next493_final']['next493_final'],
    'next493Ready' => $plans[493]['next493_ready'],
    'wordpressUse' => 'Copied wp_options imports validate the next478-493 row-value UPDATE/DELETE RETURNING window current-source continuation after merged next462-477 while preserving independent libsqlite throughput.',
];

if (($argv[1] ?? null) === '--self-test') {
    echo "wordpress-rowvalue-returning-window-current-source-next478-493 self-test passed\n";
    return;
}

return $summary;
