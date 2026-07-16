<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteRowValueConflictReturningSavepointCurrentSourceNextPlan;

$rows = [
    ['option_id' => 1, 'blog_id' => 1, 'option_name' => 'siteurl', 'autoload' => 'yes', 'status' => 'live', 'bytes' => 20, 'option_value' => 'https://old.test'],
    ['option_id' => 2, 'blog_id' => 1, 'option_name' => 'home', 'autoload' => 'yes', 'status' => 'live', 'bytes' => 21, 'option_value' => 'https://old.test'],
    ['option_id' => 3, 'blog_id' => 1, 'option_name' => '_transient_feed', 'autoload' => 'no', 'status' => 'stale', 'bytes' => 12, 'option_value' => 'feed'],
    ['option_id' => 4, 'blog_id' => 1, 'option_name' => '_transient_timeout_feed', 'autoload' => 'no', 'status' => 'stale', 'bytes' => 13, 'option_value' => 'timeout'],
    ['option_id' => 5, 'blog_id' => 2, 'option_name' => 'siteurl', 'autoload' => 'yes', 'status' => 'live', 'bytes' => 25, 'option_value' => 'https://network.test'],
    ['option_id' => 6, 'blog_id' => 2, 'option_name' => 'home', 'autoload' => 'yes', 'status' => 'live', 'bytes' => 26, 'option_value' => 'https://network.test'],
    ['option_id' => 7, 'blog_id' => 2, 'option_name' => 'pending_theme', 'autoload' => 'no', 'status' => null, 'bytes' => 7, 'option_value' => 'theme'],
    ['option_id' => 8, 'blog_id' => 3, 'option_name' => 'orphaned_cache', 'autoload' => 'no', 'status' => 'staged', 'bytes' => 5, 'option_value' => 'cache'],
    ['option_id' => 9, 'blog_id' => 3, 'option_name' => 'rewrite_rules', 'autoload' => 'yes', 'status' => 'queued', 'bytes' => 9, 'option_value' => 'rules'],
];

$plan = SQLiteRowValueConflictReturningSavepointCurrentSourceNextPlan::execute(
    ['wp_options' => $rows],
    [
        "UPDATE OR IGNORE wp_options SET (blog_id, option_name, status, option_value) = (1, 'siteurl', option_name || ':ignored', option_value || ':ignored') WHERE option_id IN (8, 9) RETURNING option_id, blog_id, option_name, status, option_value ORDER BY option_id",
        "UPDATE OR REPLACE wp_options SET (blog_id, option_name, status, bytes) = (2, 'siteurl', option_name || ':replace', bytes + 100) WHERE option_id IN (7, 8) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id",
        "DELETE FROM wp_options WHERE (blog_id, option_name) IN ((1, '_transient_feed'), (1, '_transient_timeout_feed')) RETURNING option_id, option_name ORDER BY option_id",
    ],
    [['blog_id', 'option_name']],
);

$summary = [
    'scenario' => 'application-rowvalue-conflict-returning-savepoint-current-source-next138',
    'applicationUse' => 'Model a copied wp_options import savepoint where row-value UPDATE RETURNING applies SQLite conflict algorithms: OR IGNORE skips conflicting rows without RETURNING output, OR REPLACE deletes the current conflicting row before yielding replacement output, and later cleanup sees that current source.',
    'status' => $plan['status'],
    'conflictActions' => array_column($plan['executed_statements'], 'conflict_action'),
    'ignoredOptionIds' => array_column(array_column($plan['ignored_rows'], 'row'), 'option_id'),
    'replacedOptionIds' => array_column(array_column($plan['deleted_conflict_rows'], 'row'), 'option_id'),
    'yieldedStreams' => array_map(static fn (array $stream): array => array_column($stream['rows'], 'option_id'), $plan['yielded_returning']),
    'finalOptionIds' => array_column($plan['current_source_tables']['wp_options'], 'option_id'),
    'finalStatuses' => array_column($plan['current_source_tables']['wp_options'], 'status', 'option_id'),
    'dependencyClosure' => 'no new support component needed; this composes existing native PHP row-value UPDATE RETURNING conflict dispatch and savepoint current-source modeling',
];

if (PHP_SAPI === 'cli' && basename(__FILE__) === basename($_SERVER['SCRIPT_FILENAME'] ?? '')) {
    if (
        $summary['status'] !== 'released'
        || $summary['conflictActions'] !== ['ignore', 'replace', 'abort']
        || $summary['ignoredOptionIds'] !== [8, 9]
        || $summary['replacedOptionIds'] !== [5, 7]
        || $summary['yieldedStreams'] !== [[], [7, 8], [3, 4]]
        || $summary['finalOptionIds'] !== [1, 2, 6, 8, 9]
        || ($summary['finalStatuses'][8] ?? null) !== 'orphaned_cache:replace'
    ) {
        fwrite(STDERR, "unexpected row-value conflict savepoint summary\n");
        exit(1);
    }

    echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
}

return $summary;
