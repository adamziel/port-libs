<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan;

$rows = require __DIR__ . '/application-rowvalue-returning-window-current-source-next268-fixture.php';
$yieldStatements = [
    "UPDATE wp_options SET (status, option_value, bytes) = ('yield509', option_value || ':yield509', bytes + 209) WHERE (blog_id, option_name) IN ((2, 'pending_theme'), (3, 'rewrite_rules')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id",
    "DELETE FROM wp_options WHERE (blog_id, option_name) IN ((1, '_transient_feed')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id",
];
$attemptStatements = [
    "UPDATE wp_options SET (status, option_value, bytes) = ('attempt509', option_value || ':attempt509', bytes + 145) WHERE (blog_id, option_name) IN ((2, 'pending_theme'), (3, 'rewrite_rules')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id",
    "DELETE FROM wp_options WHERE (blog_id, option_name) IN ((3, 'orphaned_cache')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id",
];
$retryStatements = [
    "UPDATE wp_options SET (status, option_value, bytes) = ('retry509', option_value || ':retry509', bytes + 207) WHERE (blog_id, option_name) IN ((2, 'pending_theme'), (3, 'rewrite_rules'), (4, 'plugin_batch')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id",
    "DELETE FROM wp_options WHERE (blog_id, option_name) IN ((1, '_transient_timeout_feed'), (4, 'home')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id",
];

$args = [['wp_options' => $rows], $yieldStatements, $attemptStatements, $retryStatements, [['blog_id', 'option_name']]];
$plans = [];
for ($next = 494; $next <= 509; $next++) {
    $plan = SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan::executeCurrentSourcePublicationHandoffStep($next, ...$args);
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
for ($next = 494; $next <= 509; $next++) {
    $expectedStatuses[] = 'rowvalue-update-delete-returning-window-current-source-next' . $next;
}
assert(array_values($statuses) === $expectedStatuses);
assert($plans[497]['next497_ready'] === true);
assert($plans[501]['next501_ready'] === true);
assert($plans[505]['next505_ready'] === true);
assert($plans[509]['next509_ready'] === true);

$summary = [
    'status' => 'rowvalue-update-delete-returning-window-current-source-next494-509',
    'candidateStatuses' => array_values($statuses),
    'next494Handoff' => $plans[494]['next494_handoff']['next494_handoff'],
    'next494AfterReadyRange' => $plans[494]['next494_handoff']['after_ready_range'],
    'next495SourceAudit' => $plans[495]['next495_source_audit']['next495_source_audit'],
    'next495PreservesCurrentSource' => $plans[495]['next495_source_audit']['retry_rows_preserve_current_source'],
    'next496Preflight' => $plans[496]['next496_preflight']['next496_preflight'],
    'next496KeepsThroughputHigh' => $plans[496]['next496_preflight']['keeps_libsqlite_throughput_high'],
    'next497Final' => $plans[497]['next497_final']['next497_final'],
    'next497Ready' => $plans[497]['next497_ready'],
    'next498Handoff' => $plans[498]['next498_handoff']['next498_handoff'],
    'next498AfterReadyRange' => $plans[498]['next498_handoff']['after_ready_range'],
    'next501Ready' => $plans[501]['next501_ready'],
    'next505Ready' => $plans[505]['next505_ready'],
    'next509Final' => $plans[509]['next509_final']['next509_final'],
    'next509Ready' => $plans[509]['next509_ready'],
    'applicationUse' => 'Copied wp_options imports validate the next494-509 row-value UPDATE/DELETE RETURNING window current-source continuation after merged next478-493 while preserving independent libsqlite throughput.',
];

if (($argv[1] ?? null) === '--self-test') {
    echo "application-rowvalue-returning-window-current-source-next494-509 self-test passed\n";
    return;
}

return $summary;
