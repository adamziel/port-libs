<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan;

$rows = require __DIR__ . '/wordpress-rowvalue-returning-window-current-source-next268-fixture.php';
$yieldStatements = [
    "UPDATE wp_options SET (status, option_value, bytes) = ('yield1149', option_value || ':yield1149', bytes + 853) WHERE (blog_id, option_name) IN ((1, 'home'), (2, 'theme_mods')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id",
    "DELETE FROM wp_options WHERE (blog_id, option_name) IN ((4, 'plugin_batch')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id",
];
$attemptStatements = [
    "UPDATE wp_options SET (status, option_value, bytes) = ('attempt1149', option_value || ':attempt1149', bytes + 733) WHERE (blog_id, option_name) IN ((1, 'home'), (2, 'theme_mods')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id",
    "DELETE FROM wp_options WHERE (blog_id, option_name) IN ((1, '_transient_timeout_feed')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id",
];
$retryStatements = [
    "UPDATE wp_options SET (status, option_value, bytes) = ('retry1149', option_value || ':retry1149', bytes + 857) WHERE (blog_id, option_name) IN ((1, 'home'), (2, 'theme_mods'), (3, 'rewrite_rules')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id",
    "DELETE FROM wp_options WHERE (blog_id, option_name) IN ((2, 'pending_theme'), (4, 'plugin_batch')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id",
];

$args = [['wp_options' => $rows], $yieldStatements, $attemptStatements, $retryStatements, [['blog_id', 'option_name']]];
$plans = [];
for ($next = 1134; $next <= 1149; $next++) {
    $method = 'executeNext' . $next;
    $plans[$next] = SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan::{$method}(...$args);
}

$statuses = array_map(static fn (array $plan): string => $plan['status'], $plans);
$expectedStatuses = [];
for ($next = 1134; $next <= 1149; $next++) {
    $expectedStatuses[] = 'rowvalue-update-delete-returning-window-current-source-next' . $next;
}
assert(array_values($statuses) === $expectedStatuses);
assert($plans[1134]['next1134_handoff']['next1133_ready'] === true);
assert($plans[1137]['next1137_ready'] === true);
assert($plans[1141]['next1141_ready'] === true);
assert($plans[1145]['next1145_ready'] === true);
assert($plans[1149]['next1149_ready'] === true);

$summary = [
    'status' => 'rowvalue-update-delete-returning-window-current-source-next1134-1149',
    'candidateStatuses' => array_values($statuses),
    'next1134Handoff' => $plans[1134]['next1134_handoff']['next1134_handoff'],
    'next1134AfterReadyRange' => $plans[1134]['next1134_handoff']['after_ready_range'],
    'next1134ConsumesNext1133Ready' => $plans[1134]['next1134_handoff']['next1133_ready'],
    'next1135SourceAudit' => $plans[1135]['next1135_source_audit']['next1135_source_audit'],
    'next1135PreservesCurrentSource' => $plans[1135]['next1135_source_audit']['retry_rows_preserve_current_source'],
    'next1136Preflight' => $plans[1136]['next1136_preflight']['next1136_preflight'],
    'next1136KeepsThroughputHigh' => $plans[1136]['next1136_preflight']['keeps_libsqlite_throughput_high'],
    'next1137Final' => $plans[1137]['next1137_final']['next1137_final'],
    'next1137Ready' => $plans[1137]['next1137_ready'],
    'next1138Handoff' => $plans[1138]['next1138_handoff']['next1138_handoff'],
    'next1138AfterReadyRange' => $plans[1138]['next1138_handoff']['after_ready_range'],
    'next1141Ready' => $plans[1141]['next1141_ready'],
    'next1145Ready' => $plans[1145]['next1145_ready'],
    'next1149Final' => $plans[1149]['next1149_final']['next1149_final'],
    'next1149Ready' => $plans[1149]['next1149_ready'],
    'wordpressUse' => 'Copied wp_options imports validate the next1134-1149 row-value UPDATE/DELETE RETURNING window current-source continuation as the direct handoff from next1133_ready.',
];

if (($argv[1] ?? null) === '--self-test') {
    echo "wordpress-rowvalue-returning-window-current-source-next1134-1149 self-test passed\n";
    return;
}

return $summary;
