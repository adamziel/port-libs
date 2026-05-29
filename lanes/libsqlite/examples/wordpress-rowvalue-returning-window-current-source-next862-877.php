<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan;

$rows = require __DIR__ . '/wordpress-rowvalue-returning-window-current-source-next268-fixture.php';
$yieldStatements = [
    "UPDATE wp_options SET (status, option_value, bytes) = ('yield877', option_value || ':yield877', bytes + 577) WHERE (blog_id, option_name) IN ((1, 'home'), (2, 'theme_mods')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id",
    "DELETE FROM wp_options WHERE (blog_id, option_name) IN ((4, 'plugin_batch')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id",
];
$attemptStatements = [
    "UPDATE wp_options SET (status, option_value, bytes) = ('attempt877', option_value || ':attempt877', bytes + 477) WHERE (blog_id, option_name) IN ((1, 'home'), (2, 'theme_mods')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id",
    "DELETE FROM wp_options WHERE (blog_id, option_name) IN ((1, '_transient_timeout_feed')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id",
];
$retryStatements = [
    "UPDATE wp_options SET (status, option_value, bytes) = ('retry877', option_value || ':retry877', bytes + 587) WHERE (blog_id, option_name) IN ((1, 'home'), (2, 'theme_mods'), (3, 'rewrite_rules')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id",
    "DELETE FROM wp_options WHERE (blog_id, option_name) IN ((2, 'pending_theme'), (4, 'plugin_batch')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id",
];

$args = [['wp_options' => $rows], $yieldStatements, $attemptStatements, $retryStatements, [['blog_id', 'option_name']]];
$plans = [];
for ($next = 862; $next <= 877; $next++) {
    $method = 'executeNext' . $next;
    $plans[$next] = SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan::{$method}(...$args);
}

$statuses = array_map(static fn (array $plan): string => $plan['status'], $plans);
$expectedStatuses = [];
for ($next = 862; $next <= 877; $next++) {
    $expectedStatuses[] = 'rowvalue-update-delete-returning-window-current-source-next' . $next;
}
assert(array_values($statuses) === $expectedStatuses);
assert($plans[862]['next862_handoff']['next861_ready'] === true);
assert($plans[865]['next865_ready'] === true);
assert($plans[869]['next869_ready'] === true);
assert($plans[873]['next873_ready'] === true);
assert($plans[877]['next877_ready'] === true);

$summary = [
    'status' => 'rowvalue-update-delete-returning-window-current-source-next862-877',
    'candidateStatuses' => array_values($statuses),
    'next862Handoff' => $plans[862]['next862_handoff']['next862_handoff'],
    'next862AfterReadyRange' => $plans[862]['next862_handoff']['after_ready_range'],
    'next862ConsumesNext861Ready' => $plans[862]['next862_handoff']['next861_ready'],
    'next863SourceAudit' => $plans[863]['next863_source_audit']['next863_source_audit'],
    'next863PreservesCurrentSource' => $plans[863]['next863_source_audit']['retry_rows_preserve_current_source'],
    'next864Preflight' => $plans[864]['next864_preflight']['next864_preflight'],
    'next864KeepsThroughputHigh' => $plans[864]['next864_preflight']['keeps_libsqlite_throughput_high'],
    'next865Final' => $plans[865]['next865_final']['next865_final'],
    'next865Ready' => $plans[865]['next865_ready'],
    'next866Handoff' => $plans[866]['next866_handoff']['next866_handoff'],
    'next866AfterReadyRange' => $plans[866]['next866_handoff']['after_ready_range'],
    'next869Ready' => $plans[869]['next869_ready'],
    'next873Ready' => $plans[873]['next873_ready'],
    'next877Final' => $plans[877]['next877_final']['next877_final'],
    'next877Ready' => $plans[877]['next877_ready'],
    'wordpressUse' => 'Copied wp_options imports validate the next862-877 row-value UPDATE/DELETE RETURNING window current-source continuation as the direct handoff from next861_ready.',
];

if (($argv[1] ?? null) === '--self-test') {
    echo "wordpress-rowvalue-returning-window-current-source-next862-877 self-test passed\n";
    return;
}

return $summary;
