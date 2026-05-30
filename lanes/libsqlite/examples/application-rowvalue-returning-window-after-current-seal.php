<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan;

$rows = require __DIR__ . '/application-rowvalue-returning-window-current-source-next268-fixture.php';
$yieldStatements = [
    "UPDATE wp_options SET (status, option_value, bytes) = ('yield280', option_value || ':yield280', bytes + 33) WHERE (blog_id, option_name) IN ((2, 'pending_theme'), (3, 'rewrite_rules')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id",
    "DELETE FROM wp_options WHERE (blog_id, option_name) IN ((1, '_transient_feed')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id",
];
$attemptStatements = [
    "UPDATE wp_options SET (status, option_value, bytes) = ('attempt280', option_value || ':attempt280', bytes + 7) WHERE (blog_id, option_name) IN ((2, 'pending_theme'), (3, 'rewrite_rules')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id",
    "DELETE FROM wp_options WHERE (blog_id, option_name) IN ((3, 'orphaned_cache')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id",
];
$retryStatements = [
    "UPDATE wp_options SET (status, option_value, bytes) = ('retry280', option_value || ':retry280', bytes + 24) WHERE (blog_id, option_name) IN ((2, 'pending_theme'), (3, 'rewrite_rules'), (4, 'plugin_batch')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id",
    "DELETE FROM wp_options WHERE (blog_id, option_name) IN ((1, '_transient_timeout_feed'), (4, 'home')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id",
];

$args = [['wp_options' => $rows], $yieldStatements, $attemptStatements, $retryStatements, [['blog_id', 'option_name']]];
$next277 = SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan::executeAfterCurrentAttestation(...$args);
$next278 = SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan::executeAfterCurrentManifest(...$args);
$next279 = SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan::executeAfterCurrentBridge(...$args);
$next280 = SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan::executeAfterCurrentSeal(...$args);

$statuses = [$next277['status'], $next278['status'], $next279['status'], $next280['status']];
assert($statuses === [
    'rowvalue-update-delete-returning-window-current-source-next277',
    'rowvalue-update-delete-returning-window-current-source-next278',
    'rowvalue-update-delete-returning-window-current-source-next279',
    'rowvalue-update-delete-returning-window-current-source-next280',
]);
assert($next280['after_current_ready_next280'] === true);

$summary = [
    'status' => 'rowvalue-update-delete-returning-window-after-current-seal',
    'candidateStatuses' => $statuses,
    'attestation' => $next277['current_source_attestation_next277']['current_source_attestation_next277'],
    'manifest' => $next278['returning_manifest_next278']['returning_manifest_next278'],
    'bridge' => $next279['after_current_bridge_next279']['after_current_bridge_next279'],
    'seal' => $next280['after_current_seal_next280']['after_current_seal_next280'],
    'sealReady' => $next280['after_current_ready_next280'],
    'applicationUse' => 'Copied wp_options imports can validate the prepared after-current seal row-value UPDATE/DELETE RETURNING handoff as attestation, manifest, bridge, and final seal receipts.',
];

if (($argv[1] ?? null) === '--self-test') {
    echo "application-rowvalue-returning-window-after-current-seal self-test passed\n";
    return;
}

return $summary;
