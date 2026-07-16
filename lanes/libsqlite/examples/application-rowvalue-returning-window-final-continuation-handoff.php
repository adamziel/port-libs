<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan;

$rows = require __DIR__ . '/application-rowvalue-returning-window-current-source-next268-fixture.php';
$yieldStatements = [
    "UPDATE wp_options SET (status, option_value, bytes) = ('yield973', option_value || ':yield973', bytes + 661) WHERE (blog_id, option_name) IN ((1, 'home'), (2, 'theme_mods')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id",
    "DELETE FROM wp_options WHERE (blog_id, option_name) IN ((4, 'plugin_batch')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id",
];
$attemptStatements = [
    "UPDATE wp_options SET (status, option_value, bytes) = ('attempt973', option_value || ':attempt973', bytes + 561) WHERE (blog_id, option_name) IN ((1, 'home'), (2, 'theme_mods')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id",
    "DELETE FROM wp_options WHERE (blog_id, option_name) IN ((1, '_transient_timeout_feed')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id",
];
$retryStatements = [
    "UPDATE wp_options SET (status, option_value, bytes) = ('retry973', option_value || ':retry973', bytes + 671) WHERE (blog_id, option_name) IN ((1, 'home'), (2, 'theme_mods'), (3, 'rewrite_rules')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id",
    "DELETE FROM wp_options WHERE (blog_id, option_name) IN ((2, 'pending_theme'), (4, 'plugin_batch')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id",
];

$args = [['wp_options' => $rows], $yieldStatements, $attemptStatements, $retryStatements, [['blog_id', 'option_name']]];
$summary = SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan::executeStableFinalContinuationHandoff(...$args);

assert($summary['handoff_consumes_previous_ready'] === true);
assert($summary['candidate_count'] === 224);
assert($summary['final_publication_step'] === 1181);
assert($summary['first_seal_ready'] === true);
assert($summary['penultimate_seal_ready'] === true);
assert($summary['final_seal_ready'] === true);

$summary['applicationUse'] = 'Copied wp_options imports validate the row-value UPDATE/DELETE RETURNING window final continuation handoff through the complete stable dynamic publication alias.';

if (($argv[1] ?? null) === '--self-test') {
    echo "application-rowvalue-returning-window-final-continuation-handoff self-test passed\n";
    return;
}

return $summary;
