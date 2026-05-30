<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan.php';

use PortLibs\LibSqlite\SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan;

$databasePath = '/srv/www/wp-content/database/wp-next191.sqlite';
$currentToken = ['id' => 'application-next191-current-source', 'epoch' => 191];
$plan = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next191Plan(
    [
        'status' => 'wal-hot-journal-savepoint-checkpoint-current-source-next188',
        'database_path' => $databasePath,
        'wal_path' => $databasePath . '-wal',
        'current_source_token' => $currentToken,
        'current_commit_hook' => 9100,
        'current_schema_cookie' => 52,
        'hook_digest' => hash('sha256', 'application-next191-hook'),
        'operation_names' => ['publish_commit_hook_current_source_next188'],
        'dependencies' => ['sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next188'],
    ],
    [
        [
            'name' => 'wp_options_autoload_current',
            'page' => 5,
            'source_id' => $currentToken['id'],
            'epoch' => $currentToken['epoch'],
            'observed_commit_hook' => 9100,
            'observed_schema_cookie' => 52,
        ],
        [
            'name' => 'wp_options_active_plugins_savepoint',
            'page' => 3,
            'source_id' => $currentToken['id'],
            'epoch' => $currentToken['epoch'],
            'observed_commit_hook' => 9100,
            'observed_schema_cookie' => 52,
        ],
        [
            'name' => 'wp_options_rewrite_rules_stale_hook',
            'page' => 8,
            'source_id' => $currentToken['id'],
            'epoch' => $currentToken['epoch'],
            'observed_commit_hook' => 9099,
            'observed_schema_cookie' => 52,
        ],
    ],
    [4],
    [1, 2],
    [3, 4]
);

echo 'status: ' . $plan['status'] . PHP_EOL;
echo 'retained: ' . implode(',', $plan['retained_cache_names']) . PHP_EOL;
echo 'invalidated: ' . implode(',', $plan['invalidated_cache_names']) . PHP_EOL;
