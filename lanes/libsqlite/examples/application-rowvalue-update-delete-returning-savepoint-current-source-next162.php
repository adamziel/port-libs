<?php

declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteRowValueUpdateDeleteReturningSavepointPlan;

$rows = [
    ['option_id' => 1, 'blog_id' => 1, 'option_name' => 'siteurl', 'autoload' => 'yes', 'status' => 'live', 'bytes' => 20, 'option_value' => 'https://old.test'],
    ['option_id' => 2, 'blog_id' => 1, 'option_name' => 'home', 'autoload' => 'yes', 'status' => 'live', 'bytes' => 21, 'option_value' => 'https://home.test'],
    ['option_id' => 3, 'blog_id' => 1, 'option_name' => '_transient_feed', 'autoload' => 'no', 'status' => 'stale', 'bytes' => 12, 'option_value' => 'feed'],
    ['option_id' => 4, 'blog_id' => 1, 'option_name' => '_transient_timeout_feed', 'autoload' => 'no', 'status' => 'stale', 'bytes' => 13, 'option_value' => 'timeout'],
    ['option_id' => 5, 'blog_id' => 2, 'option_name' => 'siteurl', 'autoload' => 'yes', 'status' => 'live', 'bytes' => 25, 'option_value' => 'https://network.test'],
    ['option_id' => 6, 'blog_id' => 2, 'option_name' => 'home', 'autoload' => 'yes', 'status' => 'live', 'bytes' => 26, 'option_value' => 'https://network-home.test'],
    ['option_id' => 7, 'blog_id' => 2, 'option_name' => 'pending_theme', 'autoload' => 'no', 'status' => null, 'bytes' => 7, 'option_value' => 'theme'],
    ['option_id' => 8, 'blog_id' => 3, 'option_name' => 'rewrite_rules', 'autoload' => 'yes', 'status' => 'queued', 'bytes' => 9, 'option_value' => 'rules'],
];

$failSql = "UPDATE OR FAIL wp_options SET (blog_id, option_name, status, option_value, bytes) = (3, 'siteurl', option_name || ':fail', option_value || ':fail', bytes + 100) WHERE option_id IN (7, 8) RETURNING option_id, blog_id, option_name, status, option_value, bytes, (blog_id, option_name) = (3, 'siteurl') AS key_match ORDER BY option_id";
$deleteSql = "DELETE FROM wp_options WHERE (blog_id, option_name) IN ((1, '_transient_feed'), (1, '_transient_timeout_feed'), (1, 'siteurl')) RETURNING option_id, blog_id, option_name ORDER BY option_id";

$plan = SQLiteRowValueUpdateDeleteReturningSavepointPlan::executeFailConflictRollbackSavepoint(
    ['wp_options' => $rows],
    [$failSql, $deleteSql],
    [['blog_id', 'option_name']],
);

if (($argv[1] ?? '') === '--self-test') {
    assert($plan['status'] === 'rolled-back-after-or-fail');
    assert($plan['discarded_returning_count'] === 1);
    assert($plan['attempted_changes_before_rollback'] === 1);
    assert(array_column($plan['partial_fail']['yielded_returning'], 'option_id') === [7]);
    assert($plan['partial_fail']['conflict']['conflicting_row_ids'] === [7]);
    assert(array_column($plan['current_source_tables']['wp_options'], 'option_id') === [1, 2, 3, 4, 5, 6, 7, 8]);
    assert(array_column($plan['current_source_tables']['wp_options'], 'option_name', 'option_id')[7] === 'pending_theme');
    echo "application-rowvalue-update-delete-returning-savepoint-current-source-next162 self-test passed\n";
    return;
}

echo json_encode([
    'scenario' => 'application-rowvalue-update-delete-returning-savepoint-current-source-next162',
    'status' => $plan['status'],
    'discardedReturningCount' => $plan['discarded_returning_count'],
    'attemptedChangesBeforeRollback' => $plan['attempted_changes_before_rollback'],
    'partialFailConflict' => $plan['partial_fail']['conflict'],
    'restoredOptionIds' => array_column($plan['current_source_tables']['wp_options'], 'option_id'),
    'applicationUse' => 'Copied wp_options import batches that stream RETURNING rows from row-value UPDATE OR FAIL must roll back partial current-source mutations and discard already-yielded rows when the importer rolls back to its savepoint.',
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
