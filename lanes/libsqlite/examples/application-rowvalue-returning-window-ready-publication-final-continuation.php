<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan;

$rows = require __DIR__ . '/application-rowvalue-returning-window-current-source-next268-fixture.php';
$yieldStatements = [
    "UPDATE wp_options SET (status, option_value, bytes) = ('yield-final', option_value || ':yield-final', bytes + 677) WHERE (blog_id, option_name) IN ((1, 'home'), (2, 'theme_mods')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id",
    "DELETE FROM wp_options WHERE (blog_id, option_name) IN ((4, 'plugin_batch')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id",
];
$attemptStatements = [
    "UPDATE wp_options SET (status, option_value, bytes) = ('attempt-final', option_value || ':attempt-final', bytes + 577) WHERE (blog_id, option_name) IN ((1, 'home'), (2, 'theme_mods')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id",
    "DELETE FROM wp_options WHERE (blog_id, option_name) IN ((1, '_transient_timeout_feed')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id",
];
$retryStatements = [
    "UPDATE wp_options SET (status, option_value, bytes) = ('retry-final', option_value || ':retry-final', bytes + 687) WHERE (blog_id, option_name) IN ((1, 'home'), (2, 'theme_mods'), (3, 'rewrite_rules')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id",
    "DELETE FROM wp_options WHERE (blog_id, option_name) IN ((2, 'pending_theme'), (4, 'plugin_batch')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id",
];

$args = [['wp_options' => $rows], $yieldStatements, $attemptStatements, $retryStatements, [['blog_id', 'option_name']]];
$plans = [];
for ($next = 974; $next <= 989; $next++) {
    $plans[$next] = SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan::executeReadyPublicationContinuation($next, ...$args);
}

assert($plans[974]['next974_handoff']['next973_ready'] === true);
assert($plans[977]['next977_ready'] === true);
assert($plans[981]['next981_ready'] === true);
assert($plans[985]['next985_ready'] === true);
assert($plans[989]['next989_ready'] === true);

$summary = [
    'status' => 'rowvalue-update-delete-returning-window-ready-publication-final-continuation',
    'candidatePhases' => [
        'final-continuation-handoff',
        'final-continuation-source-audit',
        'final-continuation-throughput-preflight',
        'final-continuation-ready-seal',
    ],
    'handoffDigest' => $plans[974]['next974_handoff']['next974_handoff'],
    'handoffAfterReadyRange' => $plans[974]['next974_handoff']['after_ready_range'],
    'handoffConsumesPreviousReady' => $plans[974]['next974_handoff']['next973_ready'],
    'sourceAuditDigest' => $plans[975]['next975_source_audit']['next975_source_audit'],
    'sourceAuditPreservesCurrentSource' => $plans[975]['next975_source_audit']['retry_rows_preserve_current_source'],
    'preflightDigest' => $plans[976]['next976_preflight']['next976_preflight'],
    'preflightKeepsThroughputHigh' => $plans[976]['next976_preflight']['keeps_libsqlite_throughput_high'],
    'firstSealDigest' => $plans[977]['next977_final']['next977_final'],
    'firstSealReady' => $plans[977]['next977_ready'],
    'secondHandoffDigest' => $plans[978]['next978_handoff']['next978_handoff'],
    'secondHandoffAfterReadyRange' => $plans[978]['next978_handoff']['after_ready_range'],
    'middleSealReady' => $plans[981]['next981_ready'],
    'lateSealReady' => $plans[985]['next985_ready'],
    'finalSealDigest' => $plans[989]['next989_final']['next989_final'],
    'finalSealReady' => $plans[989]['next989_ready'],
    'applicationUse' => 'Copied wp_options imports validate the row-value UPDATE/DELETE RETURNING window ready-publication final continuation as the direct handoff from the prior ready seal.',
];

if (($argv[1] ?? null) === '--self-test') {
    echo "application-rowvalue-returning-window-ready-publication-final-continuation self-test passed\n";
    return;
}

return $summary;
