<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan;

$rows = require __DIR__ . '/wordpress-rowvalue-returning-window-current-source-next268-fixture.php';
$yieldStatements = [
    "UPDATE wp_options SET (status, option_value, bytes) = ('yield637', option_value || ':yield637', bytes + 337) WHERE (blog_id, option_name) IN ((1, 'home'), (2, 'theme_mods')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id",
    "DELETE FROM wp_options WHERE (blog_id, option_name) IN ((4, 'plugin_batch')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id",
];
$attemptStatements = [
    "UPDATE wp_options SET (status, option_value, bytes) = ('attempt637', option_value || ':attempt637', bytes + 239) WHERE (blog_id, option_name) IN ((1, 'home'), (2, 'theme_mods')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id",
    "DELETE FROM wp_options WHERE (blog_id, option_name) IN ((1, '_transient_timeout_feed')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id",
];
$retryStatements = [
    "UPDATE wp_options SET (status, option_value, bytes) = ('retry637', option_value || ':retry637', bytes + 347) WHERE (blog_id, option_name) IN ((1, 'home'), (2, 'theme_mods'), (3, 'rewrite_rules')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id",
    "DELETE FROM wp_options WHERE (blog_id, option_name) IN ((2, 'pending_theme'), (4, 'plugin_batch')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id",
];

$args = [['wp_options' => $rows], $yieldStatements, $attemptStatements, $retryStatements, [['blog_id', 'option_name']]];
$plans = [];
for ($next = 622; $next <= 637; $next++) {
    $method = 'executeNext' . $next;
    $plans[$next] = SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan::{$method}(...$args);
}

$statuses = array_map(static fn (array $plan): string => $plan['status'], $plans);
$expectedStatuses = [];
for ($next = 622; $next <= 637; $next++) {
    $expectedStatuses[] = 'rowvalue-update-delete-returning-window-current-source-next' . $next;
}
assert(array_values($statuses) === $expectedStatuses);
assert($plans[625]['next625_ready'] === true);
assert($plans[629]['next629_ready'] === true);
assert($plans[633]['next633_ready'] === true);
assert($plans[637]['next637_ready'] === true);

$summary = [
    'status' => 'rowvalue-update-delete-returning-window-current-source-next622-637',
    'candidateStatuses' => array_values($statuses),
    'next622Handoff' => $plans[622]['next622_handoff']['next622_handoff'],
    'next622AfterReadyRange' => $plans[622]['next622_handoff']['after_ready_range'],
    'next623SourceAudit' => $plans[623]['next623_source_audit']['next623_source_audit'],
    'next623PreservesCurrentSource' => $plans[623]['next623_source_audit']['retry_rows_preserve_current_source'],
    'next624Preflight' => $plans[624]['next624_preflight']['next624_preflight'],
    'next624KeepsThroughputHigh' => $plans[624]['next624_preflight']['keeps_libsqlite_throughput_high'],
    'next625Final' => $plans[625]['next625_final']['next625_final'],
    'next625Ready' => $plans[625]['next625_ready'],
    'next626Handoff' => $plans[626]['next626_handoff']['next626_handoff'],
    'next626AfterReadyRange' => $plans[626]['next626_handoff']['after_ready_range'],
    'next629Ready' => $plans[629]['next629_ready'],
    'next633Ready' => $plans[633]['next633_ready'],
    'next637Final' => $plans[637]['next637_final']['next637_final'],
    'next637Ready' => $plans[637]['next637_ready'],
    'wordpressUse' => 'Copied wp_options imports validate the next622-637 row-value UPDATE/DELETE RETURNING window current-source continuation after merged next606-621 while preserving independent libsqlite throughput.',
];

if (($argv[1] ?? null) === '--self-test') {
    echo "wordpress-rowvalue-returning-window-current-source-next622-637 self-test passed\n";
    return;
}

return $summary;
