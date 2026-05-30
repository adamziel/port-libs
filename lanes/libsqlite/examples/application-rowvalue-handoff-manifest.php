<?php

declare(strict_types=1);

$next268OriginalArgv = $argv ?? [];
$argv = [];
$next267 = require __DIR__ . '/application-rowvalue-handoff-batch-admission.php';
$argv = $next268OriginalArgv;

require_once __DIR__ . '/../../../tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan;

$rows = require __DIR__ . '/application-rowvalue-returning-window-current-source-next268-fixture.php';
$plan = SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan::executeHandoffManifest(
    ['wp_options' => $rows],
    ["UPDATE wp_options SET (status, option_value, bytes) = ('yield268', option_value || ':yield268', bytes + 30) WHERE (blog_id, option_name) IN ((2, 'pending_theme'), (3, 'rewrite_rules')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id", "DELETE FROM wp_options WHERE (blog_id, option_name) IN ((1, '_transient_feed')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id"],
    ["UPDATE wp_options SET (status, option_value, bytes) = ('attempt268', option_value || ':attempt268', bytes + 5) WHERE (blog_id, option_name) IN ((2, 'pending_theme'), (3, 'rewrite_rules')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id", "DELETE FROM wp_options WHERE (blog_id, option_name) IN ((3, 'orphaned_cache')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id"],
    ["UPDATE wp_options SET (status, option_value, bytes) = ('retry268', option_value || ':retry268', bytes + 20) WHERE (blog_id, option_name) IN ((2, 'pending_theme'), (3, 'rewrite_rules'), (4, 'plugin_batch')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id", "DELETE FROM wp_options WHERE (blog_id, option_name) IN ((1, '_transient_timeout_feed'), (4, 'home')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id"],
    [['blog_id', 'option_name']],
);

assert($next267['batchCount'] === 3);
assert($plan['status'] === 'rowvalue-update-delete-returning-window-current-source-next268');
assert($plan['handoff_complete_next268'] === true);

if (($argv[1] ?? null) === '--self-test') {
    echo "application-rowvalue-handoff-manifest self-test passed\n";
    return;
}

return [
    'status' => $plan['status'],
    'handoffComplete' => $plan['handoff_complete_next268'],
    'manifestReceipt' => $plan['handoff_manifest_next268']['manifest_receipt_next268'],
    'dependencyClosure' => $plan['dependency_closure_next268'],
];
