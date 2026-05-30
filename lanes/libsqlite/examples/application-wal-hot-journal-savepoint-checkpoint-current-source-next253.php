<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan;

require_once __DIR__ . '/../src/SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan.php';

$digest = static fn (string $value): string => hash('sha256', $value);
$database = '/srv/www/wp-content/database/application.sqlite';
$reopenPlan = [
    'status' => 'wal-hot-journal-savepoint-checkpoint-current-source-next249',
    'reopened_current_source_admitted' => true,
    'database_path' => $database,
    'wal_path' => $database . '-wal',
    'journal_path' => $database . '-journal',
    'source_token' => 'application-import-checkpoint-source-253',
    'next_source_token' => 'application-import-retry-source-253',
    'commit_generation' => 253,
    'next_commit_generation' => 254,
    'schema_cookie' => 4253,
    'next_schema_cookie' => 4254,
    'checkpoint_frame' => 88,
    'next_checkpoint_frame' => 91,
    'database_digest' => $digest('application checkpoint source database next253'),
    'next_database_digest' => $digest('application retry source database next253'),
    'wal_digest' => $digest('application checkpoint source wal next253'),
    'next_wal_digest' => $digest('application retry source wal next253'),
    'accepted_reader_names' => ['schema-reader', 'wp-options-reader', 'autoload-index-reader'],
    'next_dirty_pages' => [1, 2, 9],
    'next_commit_frames' => [89, 90, 91],
    'operation_names' => ['verify_reopened_current_source_next249'],
    'dependencies' => ['sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next249'],
];

$receipt = static function (string $name, string $type, string $operation, array $extra = []) use ($reopenPlan): array {
    $paths = [
        'database' => $reopenPlan['database_path'],
        'wal' => $reopenPlan['wal_path'],
        'readers' => $reopenPlan['database_path'],
        'journal' => $reopenPlan['journal_path'],
        'savepoint' => $reopenPlan['database_path'],
    ];

    return array_replace([
        'name' => $name,
        'receipt_type' => $type,
        'operation' => $operation,
        'path' => $paths[$type],
        'pages' => [],
        'frames' => [],
        'reader_names' => [],
        'source_token' => $reopenPlan['next_source_token'],
        'commit_generation' => $reopenPlan['next_commit_generation'],
        'schema_cookie' => $reopenPlan['next_schema_cookie'],
        'checkpoint_frame' => $reopenPlan['next_checkpoint_frame'],
        'database_digest' => $reopenPlan['next_database_digest'],
        'wal_digest' => $reopenPlan['next_wal_digest'],
        'exclusive_lock_held' => true,
        'hot_journal_fenced' => false,
        'savepoint_released' => false,
        'io_error' => null,
    ], $extra);
};

$plan = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next253AdmitNextCurrentSource($reopenPlan, [
    $receipt('write-application-retry-pages', 'database', 'write_database_pages', ['pages' => [1, 2, 9]]),
    $receipt('sync-application-retry-wal', 'wal', 'sync_wal_frames', ['frames' => [89, 90, 91]]),
    $receipt('ack-application-retry-readers', 'readers', 'ack_readers', ['reader_names' => ['schema-reader', 'wp-options-reader', 'autoload-index-reader']]),
    $receipt('fence-application-hot-journal', 'journal', 'fence_hot_journal', ['hot_journal_fenced' => true]),
    $receipt('release-application-checkpoint-savepoint', 'savepoint', 'release_savepoint', ['savepoint_released' => true]),
]);

$summary = [
    'scenario' => 'application-wal-hot-journal-savepoint-checkpoint-current-source-next253',
    'status' => $plan['status'],
    'nextSourceAdmitted' => $plan['next_source_admitted'],
    'readerAction' => $plan['reader_action'],
    'nextSourceToken' => $plan['next_source_token'],
    'nextCommitGeneration' => $plan['next_commit_generation'],
    'writtenNextPages' => $plan['written_next_pages'],
    'syncedNextFrames' => $plan['synced_next_frames'],
    'acknowledgedReaders' => $plan['acknowledged_reader_names'],
    'applicationUse' => 'A copied wp_options import can admit retry readers to a new WAL current source only after checkpoint bytes, retry WAL frames, hot-journal fencing, and savepoint release all match the advancing source token.',
    'dependencyClosure' => $plan['dependency_closure'],
];

if (($argv[1] ?? '') === '--self-test') {
    if ($summary['status'] !== 'wal-hot-journal-savepoint-checkpoint-current-source-next253'
        || $summary['nextSourceAdmitted'] !== true
        || $summary['writtenNextPages'] !== [1, 2, 9]
        || $summary['syncedNextFrames'] !== [89, 90, 91]
    ) {
        fwrite(STDERR, "application-wal-hot-journal-savepoint-checkpoint-current-source-next253 self-test failed\n");
        exit(1);
    }

    echo "application-wal-hot-journal-savepoint-checkpoint-current-source-next253 self-test passed\n";
    return;
}

echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
