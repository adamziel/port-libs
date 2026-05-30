<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan;

$rows = require __DIR__ . '/application-rowvalue-returning-window-current-source-next268-fixture.php';
$yieldStatements = [
    "UPDATE wp_options SET (status, option_value, bytes) = ('yield381', option_value || ':yield381', bytes + 101) WHERE (blog_id, option_name) IN ((2, 'pending_theme'), (3, 'rewrite_rules')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id",
    "DELETE FROM wp_options WHERE (blog_id, option_name) IN ((1, '_transient_feed')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id",
];
$attemptStatements = [
    "UPDATE wp_options SET (status, option_value, bytes) = ('attempt381', option_value || ':attempt381', bytes + 61) WHERE (blog_id, option_name) IN ((2, 'pending_theme'), (3, 'rewrite_rules')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id",
    "DELETE FROM wp_options WHERE (blog_id, option_name) IN ((3, 'orphaned_cache')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id",
];
$retryStatements = [
    "UPDATE wp_options SET (status, option_value, bytes) = ('retry381', option_value || ':retry381', bytes + 89) WHERE (blog_id, option_name) IN ((2, 'pending_theme'), (3, 'rewrite_rules'), (4, 'plugin_batch')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id",
    "DELETE FROM wp_options WHERE (blog_id, option_name) IN ((1, '_transient_timeout_feed'), (4, 'home')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id",
];

$args = [['wp_options' => $rows], $yieldStatements, $attemptStatements, $retryStatements, [['blog_id', 'option_name']]];
$plans = [];
for ($step = 374; $step <= 381; $step++) {
    $plan = SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan::executeCurrentSourceReadySealStep($step, ...$args);
    $plans[$step] = array_intersect_key($plan, array_flip([
        'status',
        'next' . $step . '_handoff',
        'next' . $step . '_source_audit',
        'next' . $step . '_preflight',
        'next' . $step . '_final',
        'next' . $step . '_ready',
    ]));
    unset($plan);
}

$statuses = [
    $plans[374]['status'],
    $plans[375]['status'],
    $plans[376]['status'],
    $plans[377]['status'],
    $plans[378]['status'],
    $plans[379]['status'],
    $plans[380]['status'],
    $plans[381]['status'],
];
assert($statuses === [
    'rowvalue-update-delete-returning-window-current-source-next374',
    'rowvalue-update-delete-returning-window-current-source-next375',
    'rowvalue-update-delete-returning-window-current-source-next376',
    'rowvalue-update-delete-returning-window-current-source-next377',
    'rowvalue-update-delete-returning-window-current-source-next378',
    'rowvalue-update-delete-returning-window-current-source-next379',
    'rowvalue-update-delete-returning-window-current-source-next380',
    'rowvalue-update-delete-returning-window-current-source-next381',
]);
assert($plans[377]['next377_ready'] === true);
assert($plans[381]['next381_ready'] === true);

$summary = [
    'status' => 'rowvalue-update-delete-returning-window-current-source-next374-381',
    'candidateStatuses' => $statuses,
    'next374Handoff' => $plans[374]['next374_handoff']['next374_handoff'],
    'next374AfterReadyRange' => $plans[374]['next374_handoff']['after_ready_range'],
    'next375SourceAudit' => $plans[375]['next375_source_audit']['next375_source_audit'],
    'next375PreservesCurrentSource' => $plans[375]['next375_source_audit']['retry_rows_preserve_current_source'],
    'next376Preflight' => $plans[376]['next376_preflight']['next376_preflight'],
    'next376KeepsThroughputHigh' => $plans[376]['next376_preflight']['keeps_libsqlite_throughput_high'],
    'next377Final' => $plans[377]['next377_final']['next377_final'],
    'next377Ready' => $plans[377]['next377_ready'],
    'next378Handoff' => $plans[378]['next378_handoff']['next378_handoff'],
    'next378AfterReadyRange' => $plans[378]['next378_handoff']['after_ready_range'],
    'next379SourceAudit' => $plans[379]['next379_source_audit']['next379_source_audit'],
    'next379PreservesCurrentSource' => $plans[379]['next379_source_audit']['retry_rows_preserve_current_source'],
    'next380Preflight' => $plans[380]['next380_preflight']['next380_preflight'],
    'next380KeepsThroughputHigh' => $plans[380]['next380_preflight']['keeps_libsqlite_throughput_high'],
    'next381Final' => $plans[381]['next381_final']['next381_final'],
    'next381Ready' => $plans[381]['next381_ready'],
    'applicationUse' => 'Copied wp_options imports can validate the next374-381 row-value UPDATE/DELETE RETURNING window current-source continuation after the merged next366-373 handoff while preserving independent libsqlite throughput.',
];

if (($argv[1] ?? null) === '--self-test') {
    echo "application-rowvalue-returning-window-current-source-next374-381 self-test passed\n";
    return;
}

return $summary;
