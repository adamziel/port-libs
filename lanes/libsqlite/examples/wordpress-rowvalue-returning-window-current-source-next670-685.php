<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan;

$rows = require __DIR__ . '/wordpress-rowvalue-returning-window-current-source-next268-fixture.php';
$yieldStatements = [
    "UPDATE wp_options SET (status, option_value, bytes) = ('yield685', option_value || ':yield685', bytes + 385) WHERE (blog_id, option_name) IN ((1, 'home'), (2, 'theme_mods')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id",
    "DELETE FROM wp_options WHERE (blog_id, option_name) IN ((4, 'plugin_batch')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id",
];
$attemptStatements = [
    "UPDATE wp_options SET (status, option_value, bytes) = ('attempt685', option_value || ':attempt685', bytes + 285) WHERE (blog_id, option_name) IN ((1, 'home'), (2, 'theme_mods')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id",
    "DELETE FROM wp_options WHERE (blog_id, option_name) IN ((1, '_transient_timeout_feed')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id",
];
$retryStatements = [
    "UPDATE wp_options SET (status, option_value, bytes) = ('retry685', option_value || ':retry685', bytes + 395) WHERE (blog_id, option_name) IN ((1, 'home'), (2, 'theme_mods'), (3, 'rewrite_rules')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id",
    "DELETE FROM wp_options WHERE (blog_id, option_name) IN ((2, 'pending_theme'), (4, 'plugin_batch')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id",
];

$args = [['wp_options' => $rows], $yieldStatements, $attemptStatements, $retryStatements, [['blog_id', 'option_name']]];
$plans = [];
for ($next = 670; $next <= 685; $next++) {
    $plans[$next] = SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan::executeReadyPublicationContinuation($next, ...$args);
}

$statuses = array_map(static fn (array $plan): string => $plan['status'], $plans);
$expectedStatuses = [];
for ($next = 670; $next <= 685; $next++) {
    $expectedStatuses[] = 'rowvalue-update-delete-returning-window-current-source-next' . $next;
}
assert(array_values($statuses) === $expectedStatuses);
assert($plans[673]['next673_ready'] === true);
assert($plans[677]['next677_ready'] === true);
assert($plans[681]['next681_ready'] === true);
assert($plans[685]['next685_ready'] === true);

$summary = [
    'status' => 'rowvalue-update-delete-returning-window-current-source-next670-685',
    'candidateStatuses' => array_values($statuses),
    'next670Handoff' => $plans[670]['next670_handoff']['next670_handoff'],
    'next670AfterReadyRange' => $plans[670]['next670_handoff']['after_ready_range'],
    'next671SourceAudit' => $plans[671]['next671_source_audit']['next671_source_audit'],
    'next671PreservesCurrentSource' => $plans[671]['next671_source_audit']['retry_rows_preserve_current_source'],
    'next672Preflight' => $plans[672]['next672_preflight']['next672_preflight'],
    'next672KeepsThroughputHigh' => $plans[672]['next672_preflight']['keeps_libsqlite_throughput_high'],
    'next673Final' => $plans[673]['next673_final']['next673_final'],
    'next673Ready' => $plans[673]['next673_ready'],
    'next674Handoff' => $plans[674]['next674_handoff']['next674_handoff'],
    'next674AfterReadyRange' => $plans[674]['next674_handoff']['after_ready_range'],
    'next677Ready' => $plans[677]['next677_ready'],
    'next681Ready' => $plans[681]['next681_ready'],
    'next685Final' => $plans[685]['next685_final']['next685_final'],
    'next685Ready' => $plans[685]['next685_ready'],
    'wordpressUse' => 'Copied wp_options imports validate the next670-685 row-value UPDATE/DELETE RETURNING window current-source continuation after merged next654-669 while preserving independent libsqlite throughput.',
];

if (($argv[1] ?? null) === '--self-test') {
    echo "wordpress-rowvalue-returning-window-current-source-next670-685 self-test passed\n";
    return;
}

return $summary;
