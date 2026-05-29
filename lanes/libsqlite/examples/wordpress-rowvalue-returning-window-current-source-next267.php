<?php

declare(strict_types=1);

$next267OriginalArgv = $argv ?? [];
$argv = [];
$next266 = require __DIR__ . '/wordpress-rowvalue-returning-window-current-source-next266.php';
$argv = $next267OriginalArgv;

require_once __DIR__ . '/../../../tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan;

$rows = require __DIR__ . '/wordpress-rowvalue-returning-window-current-source-next268-fixture.php';
$plan = SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan::executeNext267(
    ['wp_options' => $rows],
    ["UPDATE wp_options SET (status, option_value, bytes) = ('yield267', option_value || ':yield267', bytes + 30) WHERE (blog_id, option_name) IN ((2, 'pending_theme'), (3, 'rewrite_rules')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id", "DELETE FROM wp_options WHERE (blog_id, option_name) IN ((1, '_transient_feed')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id"],
    ["UPDATE wp_options SET (status, option_value, bytes) = ('attempt267', option_value || ':attempt267', bytes + 5) WHERE (blog_id, option_name) IN ((2, 'pending_theme'), (3, 'rewrite_rules')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id", "DELETE FROM wp_options WHERE (blog_id, option_name) IN ((3, 'orphaned_cache')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id"],
    ["UPDATE wp_options SET (status, option_value, bytes) = ('retry267', option_value || ':retry267', bytes + 20) WHERE (blog_id, option_name) IN ((2, 'pending_theme'), (3, 'rewrite_rules'), (4, 'plugin_batch')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id", "DELETE FROM wp_options WHERE (blog_id, option_name) IN ((1, '_transient_timeout_feed'), (4, 'home')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id"],
    [['blog_id', 'option_name']],
);

assert($next266['currentSourceClosed'] === true);
assert($plan['status'] === 'rowvalue-update-delete-returning-window-current-source-next267');
assert($plan['handoff_batch_admission_next267']['batch_count'] === 3);

if (($argv[1] ?? null) === '--self-test') {
    echo "wordpress-rowvalue-returning-window-current-source-next267 self-test passed\n";
    return;
}

return [
    'status' => $plan['status'],
    'batchCount' => $plan['handoff_batch_admission_next267']['batch_count'],
    'dependencyClosure' => $plan['dependency_closure_next267'],
];
