<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan;

$rows = require __DIR__ . '/wordpress-rowvalue-returning-window-current-source-next268-fixture.php';
$yieldStatements = [
    "UPDATE wp_options SET (status, option_value, bytes) = ('yield429', option_value || ':yield429', bytes + 129) WHERE (blog_id, option_name) IN ((2, 'pending_theme'), (3, 'rewrite_rules')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id",
    "DELETE FROM wp_options WHERE (blog_id, option_name) IN ((1, '_transient_feed')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id",
];
$attemptStatements = [
    "UPDATE wp_options SET (status, option_value, bytes) = ('attempt429', option_value || ':attempt429', bytes + 83) WHERE (blog_id, option_name) IN ((2, 'pending_theme'), (3, 'rewrite_rules')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id",
    "DELETE FROM wp_options WHERE (blog_id, option_name) IN ((3, 'orphaned_cache')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id",
];
$retryStatements = [
    "UPDATE wp_options SET (status, option_value, bytes) = ('retry429', option_value || ':retry429', bytes + 127) WHERE (blog_id, option_name) IN ((2, 'pending_theme'), (3, 'rewrite_rules'), (4, 'plugin_batch')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id",
    "DELETE FROM wp_options WHERE (blog_id, option_name) IN ((1, '_transient_timeout_feed'), (4, 'home')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id",
];

$args = [['wp_options' => $rows], $yieldStatements, $attemptStatements, $retryStatements, [['blog_id', 'option_name']]];
$plans = [];
for ($next = 414; $next <= 429; $next++) {
    $method = 'executeNext' . $next;
    $plans[$next] = SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan::{$method}(...$args);
}

$statuses = array_map(static fn (array $plan): string => $plan['status'], $plans);
$expectedStatuses = [];
for ($next = 414; $next <= 429; $next++) {
    $expectedStatuses[] = 'rowvalue-update-delete-returning-window-current-source-next' . $next;
}
assert(array_values($statuses) === $expectedStatuses);
assert($plans[417]['next417_ready'] === true);
assert($plans[421]['next421_ready'] === true);
assert($plans[425]['next425_ready'] === true);
assert($plans[429]['next429_ready'] === true);

$summary = [
    'status' => 'rowvalue-update-delete-returning-window-current-source-next414-429',
    'candidateStatuses' => array_values($statuses),
    'next414Handoff' => $plans[414]['next414_handoff']['next414_handoff'],
    'next414AfterReadyRange' => $plans[414]['next414_handoff']['after_ready_range'],
    'next415SourceAudit' => $plans[415]['next415_source_audit']['next415_source_audit'],
    'next415PreservesCurrentSource' => $plans[415]['next415_source_audit']['retry_rows_preserve_current_source'],
    'next416Preflight' => $plans[416]['next416_preflight']['next416_preflight'],
    'next416KeepsThroughputHigh' => $plans[416]['next416_preflight']['keeps_libsqlite_throughput_high'],
    'next417Final' => $plans[417]['next417_final']['next417_final'],
    'next417Ready' => $plans[417]['next417_ready'],
    'next418Handoff' => $plans[418]['next418_handoff']['next418_handoff'],
    'next418AfterReadyRange' => $plans[418]['next418_handoff']['after_ready_range'],
    'next421Ready' => $plans[421]['next421_ready'],
    'next425Ready' => $plans[425]['next425_ready'],
    'next429Final' => $plans[429]['next429_final']['next429_final'],
    'next429Ready' => $plans[429]['next429_ready'],
    'wordpressUse' => 'Copied wp_options imports validate the next414-429 row-value UPDATE/DELETE RETURNING window current-source continuation after merged next398-413 while preserving independent libsqlite throughput.',
];

if (($argv[1] ?? null) === '--self-test') {
    echo "wordpress-rowvalue-returning-window-current-source-next414-429 self-test passed\n";
    return;
}

return $summary;
