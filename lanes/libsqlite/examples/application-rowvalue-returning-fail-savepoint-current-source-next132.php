<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteRowValueReturningFailSavepointCurrentSourceNextPlan;

$rows = [
    ['option_id' => 1, 'blog_id' => 1, 'option_name' => 'siteurl', 'autoload' => 'yes', 'status' => 'live', 'bytes' => 24, 'option_value' => 'https://old.test'],
    ['option_id' => 2, 'blog_id' => 1, 'option_name' => 'home', 'autoload' => 'yes', 'status' => 'live', 'bytes' => 24, 'option_value' => 'https://old.test'],
    ['option_id' => 3, 'blog_id' => 1, 'option_name' => '_transient_feed', 'autoload' => 'no', 'status' => 'stale', 'bytes' => 12, 'option_value' => 'feed'],
    ['option_id' => 4, 'blog_id' => 1, 'option_name' => '_transient_timeout_feed', 'autoload' => 'no', 'status' => 'stale', 'bytes' => 13, 'option_value' => 'timeout'],
    ['option_id' => 5, 'blog_id' => 2, 'option_name' => 'siteurl', 'autoload' => 'yes', 'status' => 'live', 'bytes' => 25, 'option_value' => 'https://network.test'],
    ['option_id' => 8, 'blog_id' => 3, 'option_name' => 'orphaned_cache', 'autoload' => 'no', 'status' => 'staged', 'bytes' => 5, 'option_value' => 'cache'],
    ['option_id' => 7, 'blog_id' => 2, 'option_name' => 'pending_theme', 'autoload' => 'no', 'status' => null, 'bytes' => 7, 'option_value' => 'theme'],
];

$plan = SQLiteRowValueReturningFailSavepointCurrentSourceNextPlan::execute(
    ['wp_options' => $rows],
    [
        "UPDATE OR FAIL wp_options SET (option_name, status, option_value) = ('siteurl', option_name || ':fail', option_value || ':next') WHERE option_id IN (8, 7) RETURNING option_id, blog_id, option_name, status, option_value ORDER BY option_id DESC",
        "DELETE FROM wp_options WHERE (blog_id, option_name) IN ((1, '_transient_feed'), (1, '_transient_timeout_feed')) RETURNING option_id, option_name ORDER BY option_id",
    ],
    [['blog_id', 'option_name']],
    'app_settings_fail_batch',
    'option_id',
);

$summary = [
    'scenario' => 'application-rowvalue-returning-fail-savepoint-current-source-next132',
    'applicationUse' => 'Model a copied wp_options import savepoint where UPDATE OR FAIL with row-value assignments yields earlier RETURNING rows, preserves those earlier row changes, restores only the conflicting row, and leaves the savepoint open for caller recovery.',
    'status' => $plan['status'],
    'failedStatement' => $plan['failed_statement_ordinal'],
    'failedConflict' => $plan['failed_conflict'],
    'yieldedOptionIds' => array_column($plan['yielded_returning'][0]['rows'], 'option_id'),
    'currentStatuses' => array_column($plan['current_source_tables']['wp_options'], 'status', 'option_id'),
    'savepointChangedTables' => $plan['savepoint_changed_tables'],
    'dependencyClosure' => 'no new support component needed; this reuses native PHP row-value UPDATE RETURNING conflict handling and savepoint current-source modeling',
];

if (PHP_SAPI === 'cli' && basename(__FILE__) === basename($_SERVER['SCRIPT_FILENAME'] ?? '')) {
    if (
        $summary['status'] !== 'failed-savepoint-preserved'
        || $summary['failedStatement'] !== 0
        || $summary['yieldedOptionIds'] !== [8]
        || ($summary['currentStatuses'][8] ?? null) !== 'orphaned_cache:fail'
        || ($summary['currentStatuses'][7] ?? null) !== null
    ) {
        fwrite(STDERR, "unexpected row-value FAIL savepoint summary\n");
        exit(1);
    }

    echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
}

return $summary;
