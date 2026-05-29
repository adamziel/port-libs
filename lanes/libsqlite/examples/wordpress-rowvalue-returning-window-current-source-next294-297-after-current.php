<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan;

$rows = require __DIR__ . '/wordpress-rowvalue-returning-window-current-source-next268-fixture.php';
$yieldStatements = [
    "UPDATE wp_options SET (status, option_value, bytes) = ('yield297', option_value || ':yield297', bytes + 43) WHERE (blog_id, option_name) IN ((2, 'pending_theme'), (3, 'rewrite_rules')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id",
    "DELETE FROM wp_options WHERE (blog_id, option_name) IN ((1, '_transient_feed')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id",
];
$attemptStatements = [
    "UPDATE wp_options SET (status, option_value, bytes) = ('attempt297', option_value || ':attempt297', bytes + 13) WHERE (blog_id, option_name) IN ((2, 'pending_theme'), (3, 'rewrite_rules')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id",
    "DELETE FROM wp_options WHERE (blog_id, option_name) IN ((3, 'orphaned_cache')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id",
];
$retryStatements = [
    "UPDATE wp_options SET (status, option_value, bytes) = ('retry297', option_value || ':retry297', bytes + 37) WHERE (blog_id, option_name) IN ((2, 'pending_theme'), (3, 'rewrite_rules'), (4, 'plugin_batch')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id",
    "DELETE FROM wp_options WHERE (blog_id, option_name) IN ((1, '_transient_timeout_feed'), (4, 'home')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id",
];

$args = [['wp_options' => $rows], $yieldStatements, $attemptStatements, $retryStatements, [['blog_id', 'option_name']]];
$next294 = SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan::executeNext294(...$args);
$next295 = SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan::executeNext295(...$args);
$next296 = SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan::executeNext296(...$args);
$next297 = SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan::executeNext297(...$args);

$statuses = [$next294['status'], $next295['status'], $next296['status'], $next297['status']];
assert($statuses === [
    'rowvalue-update-delete-returning-window-current-source-next294',
    'rowvalue-update-delete-returning-window-current-source-next295',
    'rowvalue-update-delete-returning-window-current-source-next296',
    'rowvalue-update-delete-returning-window-current-source-next297',
]);
assert($next297['next297_ready'] === true);

$summary = [
    'status' => 'rowvalue-update-delete-returning-window-current-source-next294-297-after-current',
    'candidateStatuses' => $statuses,
    'next294Handoff' => $next294['next294_handoff']['next294_handoff'],
    'next295WindowAudit' => $next295['next295_window_audit']['next295_window_audit'],
    'next295RetryWindowRows' => $next295['next295_window_audit']['retry_window_rows'],
    'next296SourceAudit' => $next296['next296_source_audit']['next296_source_audit'],
    'next296CurrentEqualsNext' => $next296['next296_source_audit']['next_source_equals_current_source'],
    'next297Final' => $next297['next297_final']['next297_final'],
    'next297Ready' => $next297['next297_ready'],
    'wordpressUse' => 'Copied wp_options imports can validate the prepared next294-297 row-value UPDATE/DELETE RETURNING window current-source handoff with handoff, window-audit, source-audit, and final-seal metadata.',
];

if (($argv[1] ?? null) === '--self-test') {
    echo "wordpress-rowvalue-returning-window-current-source-next294-297-after-current self-test passed\n";
    return;
}

return $summary;
