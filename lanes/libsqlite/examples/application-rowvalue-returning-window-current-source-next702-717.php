<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan;

$rows = require __DIR__ . '/application-rowvalue-returning-window-current-source-next268-fixture.php';
$yieldStatements = [
    "UPDATE wp_options SET (status, option_value, bytes) = ('yield717', option_value || ':yield717', bytes + 417) WHERE (blog_id, option_name) IN ((1, 'home'), (2, 'theme_mods')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id",
    "DELETE FROM wp_options WHERE (blog_id, option_name) IN ((4, 'plugin_batch')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id",
];
$attemptStatements = [
    "UPDATE wp_options SET (status, option_value, bytes) = ('attempt717', option_value || ':attempt717', bytes + 317) WHERE (blog_id, option_name) IN ((1, 'home'), (2, 'theme_mods')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id",
    "DELETE FROM wp_options WHERE (blog_id, option_name) IN ((1, '_transient_timeout_feed')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id",
];
$retryStatements = [
    "UPDATE wp_options SET (status, option_value, bytes) = ('retry717', option_value || ':retry717', bytes + 427) WHERE (blog_id, option_name) IN ((1, 'home'), (2, 'theme_mods'), (3, 'rewrite_rules')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id",
    "DELETE FROM wp_options WHERE (blog_id, option_name) IN ((2, 'pending_theme'), (4, 'plugin_batch')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id",
];

$args = [['wp_options' => $rows], $yieldStatements, $attemptStatements, $retryStatements, [['blog_id', 'option_name']]];
$statuses = [];
$summary = [
    'status' => 'rowvalue-update-delete-returning-window-current-source-next702-717',
    'candidateStatuses' => [],
    'applicationUse' => 'Copied wp_options imports validate the next702-717 row-value UPDATE/DELETE RETURNING window current-source continuation after integrated next686-701 while preserving independent libsqlite throughput.',
];
for ($next = 702; $next <= 717; $next++) {
    $plan = SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan::executeReadyPublicationContinuation($next, ...$args);
    $statuses[] = $plan['status'];

    if ($next === 702) {
        $summary['next702Handoff'] = $plan['next702_handoff']['next702_handoff'];
        $summary['next702AfterReadyRange'] = $plan['next702_handoff']['after_ready_range'];
        $summary['next702ConsumesNext701Ready'] = $plan['next702_handoff']['next701_ready'];
    }
    if ($next === 703) {
        $summary['next703SourceAudit'] = $plan['next703_source_audit']['next703_source_audit'];
        $summary['next703PreservesCurrentSource'] = $plan['next703_source_audit']['retry_rows_preserve_current_source'];
    }
    if ($next === 704) {
        $summary['next704Preflight'] = $plan['next704_preflight']['next704_preflight'];
        $summary['next704KeepsThroughputHigh'] = $plan['next704_preflight']['keeps_libsqlite_throughput_high'];
    }
    if ($next === 705) {
        $summary['next705Final'] = $plan['next705_final']['next705_final'];
        $summary['next705Ready'] = $plan['next705_ready'];
    }
    if ($next === 706) {
        $summary['next706Handoff'] = $plan['next706_handoff']['next706_handoff'];
        $summary['next706AfterReadyRange'] = $plan['next706_handoff']['after_ready_range'];
    }
    if ($next === 709) {
        $summary['next709Ready'] = $plan['next709_ready'];
    }
    if ($next === 713) {
        $summary['next713Ready'] = $plan['next713_ready'];
    }
    if ($next === 717) {
        $summary['next717Final'] = $plan['next717_final']['next717_final'];
        $summary['next717Ready'] = $plan['next717_ready'];
    }
}

$expectedStatuses = [];
for ($next = 702; $next <= 717; $next++) {
    $expectedStatuses[] = 'rowvalue-update-delete-returning-window-current-source-next' . $next;
}
assert(array_values($statuses) === $expectedStatuses);
$summary['candidateStatuses'] = array_values($statuses);
assert($summary['next702ConsumesNext701Ready'] === true);
assert($summary['next705Ready'] === true);
assert($summary['next709Ready'] === true);
assert($summary['next713Ready'] === true);
assert($summary['next717Ready'] === true);

if (($argv[1] ?? null) === '--self-test') {
    echo "application-rowvalue-returning-window-current-source-next702-717 self-test passed\n";
    return;
}

return $summary;
