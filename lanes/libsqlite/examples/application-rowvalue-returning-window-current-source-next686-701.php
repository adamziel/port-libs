<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan;

$rows = require __DIR__ . '/application-rowvalue-returning-window-current-source-next268-fixture.php';
$yieldStatements = [
    "UPDATE wp_options SET (status, option_value, bytes) = ('yield701', option_value || ':yield701', bytes + 401) WHERE (blog_id, option_name) IN ((1, 'home'), (2, 'theme_mods')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id",
    "DELETE FROM wp_options WHERE (blog_id, option_name) IN ((4, 'plugin_batch')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id",
];
$attemptStatements = [
    "UPDATE wp_options SET (status, option_value, bytes) = ('attempt701', option_value || ':attempt701', bytes + 301) WHERE (blog_id, option_name) IN ((1, 'home'), (2, 'theme_mods')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id",
    "DELETE FROM wp_options WHERE (blog_id, option_name) IN ((1, '_transient_timeout_feed')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id",
];
$retryStatements = [
    "UPDATE wp_options SET (status, option_value, bytes) = ('retry701', option_value || ':retry701', bytes + 411) WHERE (blog_id, option_name) IN ((1, 'home'), (2, 'theme_mods'), (3, 'rewrite_rules')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id",
    "DELETE FROM wp_options WHERE (blog_id, option_name) IN ((2, 'pending_theme'), (4, 'plugin_batch')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id",
];

$args = [['wp_options' => $rows], $yieldStatements, $attemptStatements, $retryStatements, [['blog_id', 'option_name']]];
$statuses = [];
$summary = [
    'status' => 'rowvalue-update-delete-returning-window-current-source-next686-701',
    'candidateStatuses' => [],
    'applicationUse' => 'Copied wp_options imports validate the next686-701 row-value UPDATE/DELETE RETURNING window current-source continuation after merged next670-685 while preserving independent libsqlite throughput.',
];
for ($next = 686; $next <= 701; $next++) {
    $plan = SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan::executeReadyPublicationContinuation($next, ...$args);
    $statuses[] = $plan['status'];

    if ($next === 686) {
        $summary['next686Handoff'] = $plan['next686_handoff']['next686_handoff'];
        $summary['next686AfterReadyRange'] = $plan['next686_handoff']['after_ready_range'];
        $summary['next686ConsumesNext685Ready'] = $plan['next686_handoff']['next685_ready'];
    }
    if ($next === 687) {
        $summary['next687SourceAudit'] = $plan['next687_source_audit']['next687_source_audit'];
        $summary['next687PreservesCurrentSource'] = $plan['next687_source_audit']['retry_rows_preserve_current_source'];
    }
    if ($next === 688) {
        $summary['next688Preflight'] = $plan['next688_preflight']['next688_preflight'];
        $summary['next688KeepsThroughputHigh'] = $plan['next688_preflight']['keeps_libsqlite_throughput_high'];
    }
    if ($next === 689) {
        $summary['next689Final'] = $plan['next689_final']['next689_final'];
        $summary['next689Ready'] = $plan['next689_ready'];
    }
    if ($next === 690) {
        $summary['next690Handoff'] = $plan['next690_handoff']['next690_handoff'];
        $summary['next690AfterReadyRange'] = $plan['next690_handoff']['after_ready_range'];
    }
    if ($next === 693) {
        $summary['next693Ready'] = $plan['next693_ready'];
    }
    if ($next === 697) {
        $summary['next697Ready'] = $plan['next697_ready'];
    }
    if ($next === 701) {
        $summary['next701Final'] = $plan['next701_final']['next701_final'];
        $summary['next701Ready'] = $plan['next701_ready'];
    }
}

$expectedStatuses = [];
for ($next = 686; $next <= 701; $next++) {
    $expectedStatuses[] = 'rowvalue-update-delete-returning-window-current-source-next' . $next;
}
assert(array_values($statuses) === $expectedStatuses);
$summary['candidateStatuses'] = array_values($statuses);
assert($summary['next686ConsumesNext685Ready'] === true);
assert($summary['next689Ready'] === true);
assert($summary['next693Ready'] === true);
assert($summary['next697Ready'] === true);
assert($summary['next701Ready'] === true);

if (($argv[1] ?? null) === '--self-test') {
    echo "application-rowvalue-returning-window-current-source-next686-701 self-test passed\n";
    return;
}

return $summary;
