<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan;

$rows = require __DIR__ . '/application-rowvalue-returning-window-current-source-next268-fixture.php';
$yieldStatements = [
    "UPDATE wp_options SET (status, option_value, bytes) = ('yield541', option_value || ':yield541', bytes + 241) WHERE (blog_id, option_name) IN ((2, 'pending_theme'), (3, 'rewrite_rules')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id",
    "DELETE FROM wp_options WHERE (blog_id, option_name) IN ((1, '_transient_feed')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id",
];
$attemptStatements = [
    "UPDATE wp_options SET (status, option_value, bytes) = ('attempt541', option_value || ':attempt541', bytes + 177) WHERE (blog_id, option_name) IN ((2, 'pending_theme'), (3, 'rewrite_rules')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id",
    "DELETE FROM wp_options WHERE (blog_id, option_name) IN ((3, 'orphaned_cache')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id",
];
$retryStatements = [
    "UPDATE wp_options SET (status, option_value, bytes) = ('retry541', option_value || ':retry541', bytes + 239) WHERE (blog_id, option_name) IN ((2, 'pending_theme'), (3, 'rewrite_rules'), (4, 'plugin_batch')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id",
    "DELETE FROM wp_options WHERE (blog_id, option_name) IN ((1, '_transient_timeout_feed'), (4, 'home')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id",
];

$args = [['wp_options' => $rows], $yieldStatements, $attemptStatements, $retryStatements, [['blog_id', 'option_name']]];
$plans = [];
for ($next = 526; $next <= 541; $next++) {
    $plans[$next] = SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan::executeCurrentSourcePublicationSealStep($next, ...$args);
}

$statuses = array_map(static fn (array $plan): string => $plan['status'], $plans);
$expectedStatuses = [];
for ($next = 526; $next <= 541; $next++) {
    $expectedStatuses[] = 'rowvalue-update-delete-returning-window-current-source-next' . $next;
}
assert(array_values($statuses) === $expectedStatuses);
assert($plans[529]['next529_ready'] === true);
assert($plans[533]['next533_ready'] === true);
assert($plans[537]['next537_ready'] === true);
assert($plans[541]['next541_ready'] === true);

$summary = [
    'status' => 'rowvalue-update-delete-returning-window-current-source-next526-541',
    'candidateStatuses' => array_values($statuses),
    'next526Handoff' => $plans[526]['next526_handoff']['next526_handoff'],
    'next526AfterReadyRange' => $plans[526]['next526_handoff']['after_ready_range'],
    'next527SourceAudit' => $plans[527]['next527_source_audit']['next527_source_audit'],
    'next527PreservesCurrentSource' => $plans[527]['next527_source_audit']['retry_rows_preserve_current_source'],
    'next528Preflight' => $plans[528]['next528_preflight']['next528_preflight'],
    'next528KeepsThroughputHigh' => $plans[528]['next528_preflight']['keeps_libsqlite_throughput_high'],
    'next529Final' => $plans[529]['next529_final']['next529_final'],
    'next529Ready' => $plans[529]['next529_ready'],
    'next530Handoff' => $plans[530]['next530_handoff']['next530_handoff'],
    'next530AfterReadyRange' => $plans[530]['next530_handoff']['after_ready_range'],
    'next533Ready' => $plans[533]['next533_ready'],
    'next537Ready' => $plans[537]['next537_ready'],
    'next541Final' => $plans[541]['next541_final']['next541_final'],
    'next541Ready' => $plans[541]['next541_ready'],
    'applicationUse' => 'Copied wp_options imports validate the next526-541 row-value UPDATE/DELETE RETURNING window current-source continuation after merged next510-525 while preserving independent libsqlite throughput.',
];

if (($argv[1] ?? null) === '--self-test') {
    echo "application-rowvalue-returning-window-publication-seal-continuation self-test passed\n";
    return;
}

return $summary;
