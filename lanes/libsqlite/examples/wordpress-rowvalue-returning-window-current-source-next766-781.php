<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan;

$rows = require __DIR__ . '/wordpress-rowvalue-returning-window-current-source-next268-fixture.php';
$yieldStatements = [
    "UPDATE wp_options SET (status, option_value, bytes) = ('yield781', option_value || ':yield781', bytes + 481) WHERE (blog_id, option_name) IN ((1, 'home'), (2, 'theme_mods')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id",
    "DELETE FROM wp_options WHERE (blog_id, option_name) IN ((4, 'plugin_batch')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id",
];
$attemptStatements = [
    "UPDATE wp_options SET (status, option_value, bytes) = ('attempt781', option_value || ':attempt781', bytes + 381) WHERE (blog_id, option_name) IN ((1, 'home'), (2, 'theme_mods')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id",
    "DELETE FROM wp_options WHERE (blog_id, option_name) IN ((1, '_transient_timeout_feed')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id",
];
$retryStatements = [
    "UPDATE wp_options SET (status, option_value, bytes) = ('retry781', option_value || ':retry781', bytes + 491) WHERE (blog_id, option_name) IN ((1, 'home'), (2, 'theme_mods'), (3, 'rewrite_rules')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id",
    "DELETE FROM wp_options WHERE (blog_id, option_name) IN ((2, 'pending_theme'), (4, 'plugin_batch')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id",
];

$args = [['wp_options' => $rows], $yieldStatements, $attemptStatements, $retryStatements, [['blog_id', 'option_name']]];
$plans = [];
for ($next = 766; $next <= 781; $next++) {
    $plans[$next] = SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan::executeReadyPublicationContinuation($next, ...$args);
}

$statuses = array_map(static fn (array $plan): string => $plan['status'], $plans);
$expectedStatuses = [];
for ($next = 766; $next <= 781; $next++) {
    $expectedStatuses[] = 'rowvalue-update-delete-returning-window-current-source-next' . $next;
}
assert(array_values($statuses) === $expectedStatuses);
assert($plans[766]['next766_handoff']['next765_ready'] === true);
assert($plans[769]['next769_ready'] === true);
assert($plans[773]['next773_ready'] === true);
assert($plans[777]['next777_ready'] === true);
assert($plans[781]['next781_ready'] === true);

$summary = [
    'status' => 'rowvalue-update-delete-returning-window-current-source-next766-781',
    'candidateStatuses' => array_values($statuses),
    'next766Handoff' => $plans[766]['next766_handoff']['next766_handoff'],
    'next766AfterReadyRange' => $plans[766]['next766_handoff']['after_ready_range'],
    'next766ConsumesNext765Ready' => $plans[766]['next766_handoff']['next765_ready'],
    'next767SourceAudit' => $plans[767]['next767_source_audit']['next767_source_audit'],
    'next767PreservesCurrentSource' => $plans[767]['next767_source_audit']['retry_rows_preserve_current_source'],
    'next768Preflight' => $plans[768]['next768_preflight']['next768_preflight'],
    'next768KeepsThroughputHigh' => $plans[768]['next768_preflight']['keeps_libsqlite_throughput_high'],
    'next769Final' => $plans[769]['next769_final']['next769_final'],
    'next769Ready' => $plans[769]['next769_ready'],
    'next770Handoff' => $plans[770]['next770_handoff']['next770_handoff'],
    'next770AfterReadyRange' => $plans[770]['next770_handoff']['after_ready_range'],
    'next773Ready' => $plans[773]['next773_ready'],
    'next777Ready' => $plans[777]['next777_ready'],
    'next781Final' => $plans[781]['next781_final']['next781_final'],
    'next781Ready' => $plans[781]['next781_ready'],
    'wordpressUse' => 'Copied wp_options imports validate the next766-781 row-value UPDATE/DELETE RETURNING window current-source continuation as the direct handoff from integrated next750-765.',
];

if (($argv[1] ?? null) === '--self-test') {
    echo "wordpress-rowvalue-returning-window-current-source-next766-781 self-test passed\n";
    return;
}

return $summary;
