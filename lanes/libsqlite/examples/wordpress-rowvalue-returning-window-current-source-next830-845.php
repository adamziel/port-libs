<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan;

$rows = require __DIR__ . '/wordpress-rowvalue-returning-window-current-source-next268-fixture.php';
$yieldStatements = [
    "UPDATE wp_options SET (status, option_value, bytes) = ('yield845', option_value || ':yield845', bytes + 545) WHERE (blog_id, option_name) IN ((1, 'home'), (2, 'theme_mods')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id",
    "DELETE FROM wp_options WHERE (blog_id, option_name) IN ((4, 'plugin_batch')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id",
];
$attemptStatements = [
    "UPDATE wp_options SET (status, option_value, bytes) = ('attempt845', option_value || ':attempt845', bytes + 445) WHERE (blog_id, option_name) IN ((1, 'home'), (2, 'theme_mods')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id",
    "DELETE FROM wp_options WHERE (blog_id, option_name) IN ((1, '_transient_timeout_feed')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id",
];
$retryStatements = [
    "UPDATE wp_options SET (status, option_value, bytes) = ('retry845', option_value || ':retry845', bytes + 555) WHERE (blog_id, option_name) IN ((1, 'home'), (2, 'theme_mods'), (3, 'rewrite_rules')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id",
    "DELETE FROM wp_options WHERE (blog_id, option_name) IN ((2, 'pending_theme'), (4, 'plugin_batch')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id",
];

$args = [['wp_options' => $rows], $yieldStatements, $attemptStatements, $retryStatements, [['blog_id', 'option_name']]];
$plans = [];
for ($next = 830; $next <= 845; $next++) {
    $plans[$next] = SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan::executeReadyPublicationContinuation($next, ...$args);
}

$statuses = array_map(static fn (array $plan): string => $plan['status'], $plans);
$expectedStatuses = [];
for ($next = 830; $next <= 845; $next++) {
    $expectedStatuses[] = 'rowvalue-update-delete-returning-window-current-source-next' . $next;
}
assert(array_values($statuses) === $expectedStatuses);
assert($plans[830]['next830_handoff']['next829_ready'] === true);
assert($plans[833]['next833_ready'] === true);
assert($plans[837]['next837_ready'] === true);
assert($plans[841]['next841_ready'] === true);
assert($plans[845]['next845_ready'] === true);

$summary = [
    'status' => 'rowvalue-update-delete-returning-window-current-source-next830-845',
    'candidateStatuses' => array_values($statuses),
    'next830Handoff' => $plans[830]['next830_handoff']['next830_handoff'],
    'next830AfterReadyRange' => $plans[830]['next830_handoff']['after_ready_range'],
    'next830ConsumesNext829Ready' => $plans[830]['next830_handoff']['next829_ready'],
    'next831SourceAudit' => $plans[831]['next831_source_audit']['next831_source_audit'],
    'next831PreservesCurrentSource' => $plans[831]['next831_source_audit']['retry_rows_preserve_current_source'],
    'next832Preflight' => $plans[832]['next832_preflight']['next832_preflight'],
    'next832KeepsThroughputHigh' => $plans[832]['next832_preflight']['keeps_libsqlite_throughput_high'],
    'next833Final' => $plans[833]['next833_final']['next833_final'],
    'next833Ready' => $plans[833]['next833_ready'],
    'next834Handoff' => $plans[834]['next834_handoff']['next834_handoff'],
    'next834AfterReadyRange' => $plans[834]['next834_handoff']['after_ready_range'],
    'next837Ready' => $plans[837]['next837_ready'],
    'next841Ready' => $plans[841]['next841_ready'],
    'next845Final' => $plans[845]['next845_final']['next845_final'],
    'next845Ready' => $plans[845]['next845_ready'],
    'wordpressUse' => 'Copied wp_options imports validate the next830-845 row-value UPDATE/DELETE RETURNING window current-source continuation as the direct handoff from next829_ready.',
];

if (($argv[1] ?? null) === '--self-test') {
    echo "wordpress-rowvalue-returning-window-current-source-next830-845 self-test passed\n";
    return;
}

return $summary;
