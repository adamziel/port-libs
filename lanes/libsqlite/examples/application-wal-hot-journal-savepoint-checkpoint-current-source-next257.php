<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan;

require_once __DIR__ . '/../src/SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan.php';

$digest = static fn (string $value): string => hash('sha256', $value);
$database = '/srv/www/wp-content/database/application.sqlite';
$nextSourcePlan = [
    'status' => 'wal-hot-journal-savepoint-checkpoint-current-source-next253',
    'next_source_admitted' => true,
    'database_path' => $database,
    'wal_path' => $database . '-wal',
    'journal_path' => $database . '-journal',
    'source_token' => 'application-checkpoint-source-257',
    'next_source_token' => 'application-retry-source-257',
    'commit_generation' => 257,
    'next_commit_generation' => 258,
    'checkpoint_frame' => 96,
    'next_checkpoint_frame' => 101,
    'database_digest' => $digest('application checkpoint database next257'),
    'wal_digest' => $digest('application checkpoint wal next257'),
    'next_database_digest' => $digest('application retry database next257'),
    'next_wal_digest' => $digest('application retry wal next257'),
    'accepted_reader_names' => ['schema-reader', 'wp-options-reader', 'autoload-index-reader'],
    'written_next_pages' => [1, 2, 11],
    'synced_next_frames' => [97, 100, 101],
    'operation_names' => ['admit_retry_readers_to_next_current_source_next253'],
    'dependencies' => ['sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next253'],
];

$receipt = static function (string $name, string $type, string $operation, array $extra = []) use ($nextSourcePlan): array {
    $paths = [
        'reader-retire' => $nextSourcePlan['database_path'],
        'wal-retain' => $nextSourcePlan['wal_path'],
        'journal-delete' => $nextSourcePlan['journal_path'],
        'savepoint-close' => $nextSourcePlan['database_path'],
        'page-cache-seal' => $nextSourcePlan['database_path'],
    ];

    return array_replace([
        'name' => $name,
        'receipt_type' => $type,
        'operation' => $operation,
        'path' => $paths[$type],
        'retired_reader_names' => [],
        'retained_pages' => [],
        'retained_frames' => [],
        'checkpoint_source_token' => $nextSourcePlan['source_token'],
        'next_source_token' => $nextSourcePlan['next_source_token'],
        'checkpoint_commit_generation' => $nextSourcePlan['commit_generation'],
        'next_commit_generation' => $nextSourcePlan['next_commit_generation'],
        'checkpoint_frame' => $nextSourcePlan['checkpoint_frame'],
        'next_checkpoint_frame' => $nextSourcePlan['next_checkpoint_frame'],
        'checkpoint_database_digest' => $nextSourcePlan['database_digest'],
        'checkpoint_wal_digest' => $nextSourcePlan['wal_digest'],
        'next_database_digest' => $nextSourcePlan['next_database_digest'],
        'next_wal_digest' => $nextSourcePlan['next_wal_digest'],
        'exclusive_lock_held' => true,
        'hot_journal_deleted' => false,
        'savepoint_closed' => false,
        'page_cache_sealed' => false,
        'io_error' => null,
    ], $extra);
};

$plan = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next257RetireCheckpointSource($nextSourcePlan, [
    $receipt('retire-application-checkpoint-readers', 'reader-retire', 'retire_readers', ['retired_reader_names' => ['schema-reader', 'wp-options-reader', 'autoload-index-reader']]),
    $receipt('retain-application-retry-wal', 'wal-retain', 'retain_next_wal', ['retained_frames' => [97, 100, 101]]),
    $receipt('delete-application-checkpoint-journal', 'journal-delete', 'delete_checkpoint_journal', ['hot_journal_deleted' => true]),
    $receipt('close-application-checkpoint-savepoint', 'savepoint-close', 'close_checkpoint_savepoint', ['savepoint_closed' => true]),
    $receipt('seal-application-retry-page-cache', 'page-cache-seal', 'seal_page_cache', ['retained_pages' => [1, 2, 11], 'page_cache_sealed' => true]),
]);

$summary = [
    'scenario' => 'application-wal-hot-journal-savepoint-checkpoint-current-source-next257',
    'status' => $plan['status'],
    'checkpointSourceRetired' => $plan['checkpoint_source_retired'],
    'readerAction' => $plan['reader_action'],
    'walAction' => $plan['wal_action'],
    'retiredReaders' => $plan['retired_reader_names'],
    'retainedNextPages' => $plan['retained_next_pages'],
    'retainedNextFrames' => $plan['retained_next_frames'],
    'applicationUse' => 'A copied wp_options import retires the recovered checkpoint source only after retry WAL readers are admitted, old readers are retired, retry frames remain retained, and the hot journal/savepoint sidecars are removed.',
    'dependencyClosure' => $plan['dependency_closure'],
];

if (($argv[1] ?? '') === '--self-test') {
    if ($summary['status'] !== 'wal-hot-journal-savepoint-checkpoint-current-source-next257'
        || $summary['checkpointSourceRetired'] !== true
        || $summary['retiredReaders'] !== ['autoload-index-reader', 'schema-reader', 'wp-options-reader']
        || $summary['retainedNextPages'] !== [1, 2, 11]
        || $summary['retainedNextFrames'] !== [97, 100, 101]
    ) {
        fwrite(STDERR, "application-wal-hot-journal-savepoint-checkpoint-current-source-next257 self-test failed\n");
        exit(1);
    }

    echo "application-wal-hot-journal-savepoint-checkpoint-current-source-next257 self-test passed\n";
    return;
}

echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
