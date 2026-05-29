<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan;

$rows = require __DIR__ . '/wordpress-rowvalue-returning-window-current-source-next268-fixture.php';
$yieldStatements = [
    "UPDATE wp_options SET (status, option_value, bytes) = ('yield1069', option_value || ':yield1069', bytes + 751) WHERE (blog_id, option_name) IN ((1, 'home'), (2, 'theme_mods')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id",
    "DELETE FROM wp_options WHERE (blog_id, option_name) IN ((4, 'plugin_batch')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id",
];
$attemptStatements = [
    "UPDATE wp_options SET (status, option_value, bytes) = ('attempt1069', option_value || ':attempt1069', bytes + 641) WHERE (blog_id, option_name) IN ((1, 'home'), (2, 'theme_mods')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id",
    "DELETE FROM wp_options WHERE (blog_id, option_name) IN ((1, '_transient_timeout_feed')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id",
];
$retryStatements = [
    "UPDATE wp_options SET (status, option_value, bytes) = ('retry1069', option_value || ':retry1069', bytes + 757) WHERE (blog_id, option_name) IN ((1, 'home'), (2, 'theme_mods'), (3, 'rewrite_rules')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id",
    "DELETE FROM wp_options WHERE (blog_id, option_name) IN ((2, 'pending_theme'), (4, 'plugin_batch')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id",
];

$args = [['wp_options' => $rows], $yieldStatements, $attemptStatements, $retryStatements, [['blog_id', 'option_name']]];
$plans = [];
for ($next = 1054; $next <= 1069; $next++) {
    $plans[$next] = SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan::executeReadyPublicationContinuation($next, ...$args);
}

$statuses = array_map(static fn (array $plan): string => $plan['status'], $plans);
$expectedStatuses = [];
for ($next = 1054; $next <= 1069; $next++) {
    $expectedStatuses[] = 'rowvalue-update-delete-returning-window-current-source-next' . $next;
}
assert(array_values($statuses) === $expectedStatuses);
assert($plans[1054]['next1054_handoff']['next1053_ready'] === true);
assert($plans[1057]['next1057_ready'] === true);
assert($plans[1061]['next1061_ready'] === true);
assert($plans[1065]['next1065_ready'] === true);
assert($plans[1069]['next1069_ready'] === true);

$summary = [
    'status' => 'rowvalue-update-delete-returning-window-current-source-next1054-1069',
    'candidateStatuses' => array_values($statuses),
    'next1054Handoff' => $plans[1054]['next1054_handoff']['next1054_handoff'],
    'next1054AfterReadyRange' => $plans[1054]['next1054_handoff']['after_ready_range'],
    'next1054ConsumesNext1053Ready' => $plans[1054]['next1054_handoff']['next1053_ready'],
    'next1055SourceAudit' => $plans[1055]['next1055_source_audit']['next1055_source_audit'],
    'next1055PreservesCurrentSource' => $plans[1055]['next1055_source_audit']['retry_rows_preserve_current_source'],
    'next1056Preflight' => $plans[1056]['next1056_preflight']['next1056_preflight'],
    'next1056KeepsThroughputHigh' => $plans[1056]['next1056_preflight']['keeps_libsqlite_throughput_high'],
    'next1057Final' => $plans[1057]['next1057_final']['next1057_final'],
    'next1057Ready' => $plans[1057]['next1057_ready'],
    'next1058Handoff' => $plans[1058]['next1058_handoff']['next1058_handoff'],
    'next1058AfterReadyRange' => $plans[1058]['next1058_handoff']['after_ready_range'],
    'next1061Ready' => $plans[1061]['next1061_ready'],
    'next1065Ready' => $plans[1065]['next1065_ready'],
    'next1069Final' => $plans[1069]['next1069_final']['next1069_final'],
    'next1069Ready' => $plans[1069]['next1069_ready'],
    'wordpressUse' => 'Copied wp_options imports validate the next1054-1069 row-value UPDATE/DELETE RETURNING window current-source continuation as the direct handoff from next1053_ready.',
];

if (($argv[1] ?? null) === '--self-test') {
    echo "wordpress-rowvalue-returning-window-current-source-next1054-1069 self-test passed\n";
    return;
}

return $summary;
