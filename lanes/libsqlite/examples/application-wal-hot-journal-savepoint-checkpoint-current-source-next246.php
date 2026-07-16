<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan;

require_once __DIR__ . '/../src/SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan.php';

$hash = static fn (string $value): string => hash('sha256', $value);
$readerPlan = [
    'status' => 'wal-hot-journal-savepoint-checkpoint-current-source-next243',
    'reader_snapshot_admitted' => true,
    'database_path' => '/srv/www/wp-content/database/wp-next246.sqlite',
    'journal_path' => '/srv/www/wp-content/database/wp-next246.sqlite-journal',
    'wal_path' => '/srv/www/wp-content/database/wp-next246.sqlite-wal',
    'source_token' => 'wp-next246-current-source',
    'commit_generation' => 246,
    'schema_cookie' => 946,
    'database_digest' => $hash('application next246 checkpoint database image'),
    'page_cache_digest' => $hash('application next246 clean checkpoint page cache'),
    'checkpoint_frame' => 28,
    'dirty_pages' => [1, 2, 5, 9],
    'commit_frames' => [25, 26, 28],
    'accepted_reader_names' => ['wp-schema-reader', 'wp-options-reader', 'wp-autoload-index-reader'],
    'operation_names' => ['admit_reopened_reader_snapshot_baseline_next243'],
    'dependencies' => ['sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next243'],
];

$receipt = static function (string $name, string $target, string $operation, int $sequence, array $override = []) use ($readerPlan): array {
    $paths = [
        'database' => $readerPlan['database_path'],
        'wal' => $readerPlan['wal_path'],
        'journal' => $readerPlan['journal_path'],
        'directory' => dirname($readerPlan['database_path']),
    ];

    return array_replace([
        'name' => $name,
        'target' => $target,
        'operation' => $operation,
        'path' => $paths[$target],
        'sequence' => $sequence,
        'pages' => [],
        'frames' => [],
        'source_token' => $readerPlan['source_token'],
        'commit_generation' => $readerPlan['commit_generation'],
        'exclusive_lock_held' => true,
        'savepoint_replayable' => true,
        'io_error' => null,
    ], $override);
};

$plan = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next246AdmitDurableCurrentSourceHandoff($readerPlan, [
    $receipt('wp-schema-page', 'database', 'write_database_page', 1, ['pages' => [1]]),
    $receipt('wp-options-pages', 'database', 'write_database_page', 2, ['pages' => [2, 5]]),
    $receipt('wp-autoload-index-page', 'database', 'write_database_page', 3, ['pages' => [9]]),
    $receipt('wp-wal-commit-frames', 'wal', 'mark_wal_commit_frame', 4, ['frames' => [25, 26, 28]]),
    $receipt('wp-sync-database', 'database', 'sync', 5),
    $receipt('wp-sync-wal', 'wal', 'sync', 6),
    $receipt('wp-sync-directory', 'directory', 'sync', 7),
    $receipt('wp-delete-hot-journal', 'journal', 'delete', 8),
]);

$summary = [
    'scenario' => 'application-wal-hot-journal-savepoint-checkpoint-current-source-next246',
    'applicationUse' => 'A copied Application import promotes a hot-journal savepoint checkpoint as the current source only after database pages, WAL commit-frame markers, database/WAL/directory syncs, and hot-journal deletion receipts prove a durable VFS handoff.',
    'status' => $plan['status'],
    'writtenDatabasePages' => $plan['written_database_pages'],
    'syncTargets' => $plan['sync_targets'],
    'checkpointAction' => $plan['checkpoint_action'],
    'journalAction' => $plan['journal_action'],
    'dependencyClosure' => $plan['dependency_closure'],
];

if (($argv[1] ?? null) === '--self-test') {
    if ($summary['status'] !== 'wal-hot-journal-savepoint-checkpoint-current-source-next246'
        || $summary['writtenDatabasePages'] !== [1, 2, 5, 9]
        || $summary['syncTargets'] !== ['database', 'wal', 'directory']
    ) {
        fwrite(STDERR, "application-wal-hot-journal-savepoint-checkpoint-current-source-next246 self-test failed\n");
        exit(1);
    }

    echo "application-wal-hot-journal-savepoint-checkpoint-current-source-next246 self-test passed\n";
    exit(0);
}

echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
