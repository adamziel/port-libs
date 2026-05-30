<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan;

require_once __DIR__ . '/../src/SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan.php';

$hash = static fn (string $value): string => hash('sha256', $value);
$databaseDigest = $hash('application next247 checkpoint database image');
$pageCacheDigest = $hash('application next247 clean page cache image');
$readerBaseline = [
    'status' => 'wal-hot-journal-savepoint-checkpoint-current-source-next243',
    'reader_snapshot_admitted' => true,
    'database_path' => '/srv/www/wp-content/database/wp-next247.sqlite',
    'journal_path' => '/srv/www/wp-content/database/wp-next247.sqlite-journal',
    'wal_path' => '/srv/www/wp-content/database/wp-next247.sqlite-wal',
    'source_token' => 'wp-next247-current-source',
    'commit_generation' => 247,
    'schema_cookie' => 947,
    'database_digest' => $databaseDigest,
    'page_cache_digest' => $pageCacheDigest,
    'wal_index_salt' => ['wp-next247-salt-a', 'wp-next247-salt-b'],
    'wal_index_mx_frame' => 24,
    'checkpoint_frame' => 21,
    'dirty_pages' => [1, 2, 5, 8],
    'commit_frames' => [22, 23, 24],
    'accepted_reader_names' => ['schema-reader', 'options-reader', 'autoload-reader'],
    'operation_names' => ['admit_reopened_reader_snapshot_baseline_next243'],
    'dependencies' => ['sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next243'],
];

$receipt = static function (string $name, string $kind, array $pages, array $frames, array $readers) use ($readerBaseline, $databaseDigest, $pageCacheDigest): array {
    return [
        'name' => $name,
        'kind' => $kind,
        'database_path' => $readerBaseline['database_path'],
        'journal_path' => $readerBaseline['journal_path'],
        'wal_path' => $readerBaseline['wal_path'],
        'source_token' => $readerBaseline['source_token'],
        'commit_generation' => $readerBaseline['commit_generation'],
        'schema_cookie' => $readerBaseline['schema_cookie'],
        'database_digest' => $databaseDigest,
        'page_cache_digest' => $pageCacheDigest,
        'wal_index_salt' => $readerBaseline['wal_index_salt'],
        'wal_index_mx_frame' => $readerBaseline['wal_index_mx_frame'],
        'checkpoint_frame' => $readerBaseline['checkpoint_frame'],
        'page_numbers' => $pages,
        'commit_frames' => $frames,
        'reader_names' => $readers,
        'hot_journal_unlinked' => true,
        'wal_synced' => true,
        'directory_synced' => true,
        'reader_fenced' => true,
        'savepoint_released' => true,
        'savepoint_depth' => 0,
        'page_cache_clean' => true,
        'shared_lock_held' => true,
    ];
};

$plan = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next247SealPostCheckpointCleanup($readerBaseline, [
    $receipt('hot-journal-cleanup', 'hot-journal-unlink', [1], [22], ['schema-reader']),
    $receipt('wal-sync-cleanup', 'wal-sync', [2], [23], ['options-reader']),
    $receipt('directory-sync-cleanup', 'directory-sync', [5], [24], ['autoload-reader']),
    $receipt('savepoint-release-cleanup', 'savepoint-release', [8], [22, 23, 24], ['schema-reader', 'options-reader']),
    $receipt('reader-fence-cleanup', 'reader-fence', [1, 2, 5, 8], [22, 23, 24], ['schema-reader', 'options-reader', 'autoload-reader']),
]);

$summary = [
    'scenario' => 'application-wal-hot-journal-savepoint-checkpoint-current-source-next247',
    'applicationUse' => 'A copied Application import seals post-checkpoint cleanup only after hot-journal unlink, WAL sync, directory sync, savepoint release, and reader-fence receipts all match the reopened-reader current source.',
    'status' => $plan['status'],
    'cleanupAdmitted' => $plan['cleanup_admitted'],
    'coveredReaders' => $plan['covered_reader_names'],
    'coveredDirtyPages' => $plan['covered_dirty_pages'],
    'journalAction' => $plan['journal_action'],
    'dependencyClosure' => $plan['dependency_closure'],
];

if (($argv[1] ?? null) === '--self-test') {
    if ($summary['status'] !== 'wal-hot-journal-savepoint-checkpoint-current-source-next247'
        || $summary['cleanupAdmitted'] !== true
        || $summary['coveredReaders'] !== ['autoload-reader', 'options-reader', 'schema-reader']
        || $summary['coveredDirtyPages'] !== [1, 2, 5, 8]
    ) {
        fwrite(STDERR, "application-wal-hot-journal-savepoint-checkpoint-current-source-next247 self-test failed\n");
        exit(1);
    }

    echo "application-wal-hot-journal-savepoint-checkpoint-current-source-next247 self-test passed\n";
    exit(0);
}

echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
