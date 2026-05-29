<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan;

$rows = require __DIR__ . '/wordpress-rowvalue-returning-window-current-source-next268-fixture.php';
$yieldStatements = [
    "UPDATE wp_options SET (status, option_value, bytes) = ('yield989', option_value || ':yield989', bytes + 677) WHERE (blog_id, option_name) IN ((1, 'home'), (2, 'theme_mods')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id",
    "DELETE FROM wp_options WHERE (blog_id, option_name) IN ((4, 'plugin_batch')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id",
];
$attemptStatements = [
    "UPDATE wp_options SET (status, option_value, bytes) = ('attempt989', option_value || ':attempt989', bytes + 577) WHERE (blog_id, option_name) IN ((1, 'home'), (2, 'theme_mods')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id",
    "DELETE FROM wp_options WHERE (blog_id, option_name) IN ((1, '_transient_timeout_feed')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id",
];
$retryStatements = [
    "UPDATE wp_options SET (status, option_value, bytes) = ('retry989', option_value || ':retry989', bytes + 687) WHERE (blog_id, option_name) IN ((1, 'home'), (2, 'theme_mods'), (3, 'rewrite_rules')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id",
    "DELETE FROM wp_options WHERE (blog_id, option_name) IN ((2, 'pending_theme'), (4, 'plugin_batch')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id",
];

$args = [['wp_options' => $rows], $yieldStatements, $attemptStatements, $retryStatements, [['blog_id', 'option_name']]];
$plans = [];
for ($next = 974; $next <= 989; $next++) {
    $method = 'executeNext' . $next;
    $plans[$next] = SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan::{$method}(...$args);
}

$statuses = array_map(static fn (array $plan): string => $plan['status'], $plans);
$expectedStatuses = [];
for ($next = 974; $next <= 989; $next++) {
    $expectedStatuses[] = 'rowvalue-update-delete-returning-window-current-source-next' . $next;
}
assert(array_values($statuses) === $expectedStatuses);
assert($plans[974]['next974_handoff']['next973_ready'] === true);
assert($plans[977]['next977_ready'] === true);
assert($plans[981]['next981_ready'] === true);
assert($plans[985]['next985_ready'] === true);
assert($plans[989]['next989_ready'] === true);

$summary = [
    'status' => 'rowvalue-update-delete-returning-window-current-source-next974-989',
    'candidateStatuses' => array_values($statuses),
    'next974Handoff' => $plans[974]['next974_handoff']['next974_handoff'],
    'next974AfterReadyRange' => $plans[974]['next974_handoff']['after_ready_range'],
    'next974ConsumesNext973Ready' => $plans[974]['next974_handoff']['next973_ready'],
    'next975SourceAudit' => $plans[975]['next975_source_audit']['next975_source_audit'],
    'next975PreservesCurrentSource' => $plans[975]['next975_source_audit']['retry_rows_preserve_current_source'],
    'next976Preflight' => $plans[976]['next976_preflight']['next976_preflight'],
    'next976KeepsThroughputHigh' => $plans[976]['next976_preflight']['keeps_libsqlite_throughput_high'],
    'next977Final' => $plans[977]['next977_final']['next977_final'],
    'next977Ready' => $plans[977]['next977_ready'],
    'next978Handoff' => $plans[978]['next978_handoff']['next978_handoff'],
    'next978AfterReadyRange' => $plans[978]['next978_handoff']['after_ready_range'],
    'next981Ready' => $plans[981]['next981_ready'],
    'next985Ready' => $plans[985]['next985_ready'],
    'next989Final' => $plans[989]['next989_final']['next989_final'],
    'next989Ready' => $plans[989]['next989_ready'],
    'wordpressUse' => 'Copied wp_options imports validate the next974-989 row-value UPDATE/DELETE RETURNING window current-source continuation as the direct handoff from next973_ready.',
];

if (($argv[1] ?? null) === '--self-test') {
    echo "wordpress-rowvalue-returning-window-current-source-next974-989 self-test passed\n";
    return;
}

return $summary;
