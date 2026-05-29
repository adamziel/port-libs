<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNextPlan;

$rows = [
    ['option_id' => 1, 'blog_id' => 1, 'option_name' => 'siteurl', 'autoload' => 'yes', 'status' => 'live', 'bytes' => 20, 'option_value' => 'https://one.test'],
    ['option_id' => 2, 'blog_id' => 1, 'option_name' => 'home', 'autoload' => 'manual', 'status' => 'live', 'bytes' => 21, 'option_value' => 'https://home.test'],
    ['option_id' => 3, 'blog_id' => 1, 'option_name' => '_transient_feed', 'autoload' => 'no', 'status' => 'stale', 'bytes' => 12, 'option_value' => 'feed'],
    ['option_id' => 4, 'blog_id' => 2, 'option_name' => 'siteurl', 'autoload' => 'yes', 'status' => 'live', 'bytes' => 24, 'option_value' => 'https://two.test'],
    ['option_id' => 5, 'blog_id' => 2, 'option_name' => 'pending_theme', 'autoload' => 'no', 'status' => 'queued', 'bytes' => 7, 'option_value' => 'theme'],
    ['option_id' => 6, 'blog_id' => 3, 'option_name' => 'rewrite_rules', 'autoload' => 'rewrite', 'status' => 'queued', 'bytes' => 9, 'option_value' => 'rules'],
    ['option_id' => 7, 'blog_id' => 3, 'option_name' => 'orphaned_cache', 'autoload' => 'yes', 'status' => 'staged', 'bytes' => 5, 'option_value' => 'cache'],
    ['option_id' => 8, 'blog_id' => 4, 'option_name' => 'siteurl', 'autoload' => 'yes', 'status' => 'live', 'bytes' => 30, 'option_value' => 'https://four.test'],
    ['option_id' => 9, 'blog_id' => 5, 'option_name' => '_transient_old', 'autoload' => 'no', 'status' => 'stale', 'bytes' => 3, 'option_value' => 'old'],
];

$plan = SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNextPlan::executeReleaseFollowupReadSavepoint(
    ['wp_options' => $rows],
    [
        "UPDATE OR REPLACE wp_options SET (blog_id, autoload, status, option_value, bytes) = (4, 'yes', 'released205', option_value || ':released205', bytes + 10) WHERE (blog_id, option_name) IN ((3, 'orphaned_cache')) RETURNING option_id, blog_id, option_name, autoload, status, option_value, bytes, (blog_id, autoload) IS (4, 'yes') AS tuple_is ORDER BY option_id",
        "DELETE FROM wp_options WHERE (blog_id, autoload) IN ((1, 'manual'), (5, 'no')) RETURNING option_id, blog_id, option_name, autoload, status, (blog_id, autoload) IS DISTINCT FROM (1, 'yes') AS distinct_from_site ORDER BY option_id",
    ],
    [
        "UPDATE wp_options SET (status, option_value, bytes) = ('release_followup_read', option_value || ':release_followup_read', bytes + 1) WHERE (blog_id, autoload) IN ((4, 'yes'), (1, 'no')) RETURNING option_id, blog_id, option_name, autoload, status, option_value, bytes ORDER BY option_id",
        "DELETE FROM wp_options WHERE (blog_id, autoload) IN ((4, 'yes'), (2, 'yes')) RETURNING option_id, blog_id, option_name, autoload, status, bytes ORDER BY option_id",
    ],
    [['blog_id', 'autoload']],
);

echo json_encode([
    'scenario' => 'wordpress-rowvalue-update-delete-returning-savepoint-current-source-release_followup_read',
    'wordpressUse' => 'Model copied wp_options import cleanup where RELEASE of a row-value UPDATE/DELETE RETURNING savepoint promotes the current source for the next statement, so a follow-up UPDATE and DELETE see the released replacement row.',
    'status' => $plan['status'],
    'savepoint' => $plan['savepoint'],
    'releaseAdmitted' => $plan['release_admitted_release_followup_read'],
    'nextReadReleasedCurrentSource' => $plan['next_read_released_current_source_release_followup_read'],
    'savepointReturned' => $plan['released_returning_count'],
    'nextReturned' => $plan['next_returning_count'],
    'releasedIds' => array_column($plan['released_current_source_tables']['wp_options'], 'option_id'),
    'nextUpdateSourceIds' => array_column($plan['next_statements'][0]['source_rows'], 'option_id'),
    'nextDeleteSourceIds' => array_column($plan['next_statements'][1]['source_rows'], 'option_id'),
    'finalOptionIds' => array_column($plan['current_source_tables']['wp_options'], 'option_id'),
    'dependencies' => $plan['dependencies'],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
