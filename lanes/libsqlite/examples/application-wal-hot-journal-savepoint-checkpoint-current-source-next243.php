<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan;

require_once __DIR__ . '/../src/SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan.php';

$hash = static fn (string $value): string => hash('sha256', $value);
$databaseDigest = $hash('application next243 checkpoint database image');
$pageCacheDigest = $hash('application next243 reopened clean page cache');
$baselinePlan = [
    'status' => 'wal-hot-journal-savepoint-checkpoint-current-source-next240',
    'autocheckpoint_baseline_allowed' => true,
    'database_path' => '/srv/www/wp-content/database/wp-next243.sqlite',
    'journal_path' => '/srv/www/wp-content/database/wp-next243.sqlite-journal',
    'wal_path' => '/srv/www/wp-content/database/wp-next243.sqlite-wal',
    'source_token' => 'wp-next243-current-source',
    'commit_generation' => 243,
    'schema_cookie' => 743,
    'database_digest' => $databaseDigest,
    'page_cache_digest' => $pageCacheDigest,
    'wal_index_salt' => ['wp-next243-salt-a', 'wp-next243-salt-b'],
    'wal_index_mx_frame' => 18,
    'checkpoint_frame' => 14,
    'dirty_pages' => [1, 2, 5],
    'commit_frames' => [15, 16, 18],
    'operation_names' => ['admit_autocheckpoint_baseline_next240'],
    'dependencies' => ['sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next240'],
];

$reader = static function (string $name, array $pages, int $readmark) use ($baselinePlan, $databaseDigest, $pageCacheDigest): array {
    return [
        'name' => $name,
        'source_token' => $baselinePlan['source_token'],
        'commit_generation' => $baselinePlan['commit_generation'],
        'schema_cookie' => $baselinePlan['schema_cookie'],
        'database_digest' => $databaseDigest,
        'page_cache_digest' => $pageCacheDigest,
        'wal_index_salt' => $baselinePlan['wal_index_salt'],
        'wal_index_mx_frame' => $baselinePlan['wal_index_mx_frame'],
        'checkpoint_frame' => $baselinePlan['checkpoint_frame'],
        'readmark_frame' => $readmark,
        'observed_pages' => $pages,
        'observed_commit_frames' => $baselinePlan['commit_frames'],
        'hot_journal_visible' => false,
        'savepoint_depth' => 0,
        'page_cache_clean' => true,
        'shared_lock_held' => true,
        'reader_reopened_after_commit' => true,
    ];
};

$plan = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next243AdmitReaderSnapshotBaseline($baselinePlan, [
    $reader('wp-schema-reader', [1], 15),
    $reader('wp-options-reader', [2], 16),
    $reader('wp-index-reader', [5], 18),
]);

$summary = [
    'scenario' => 'application-wal-hot-journal-savepoint-checkpoint-current-source-next243',
    'applicationUse' => 'A copied Application import reopens schema, wp_options, and autoload-index readers after a hot-journal savepoint checkpoint only when their WAL-index readmarks, clean page-cache digest, commit frames, and shared locks match the autocheckpoint baseline.',
    'status' => $plan['status'],
    'acceptedReaders' => $plan['accepted_reader_names'],
    'observedDirtyPages' => $plan['observed_dirty_pages'],
    'readerAction' => $plan['reader_action'],
    'dependencyClosure' => $plan['dependency_closure'],
];

if (($argv[1] ?? null) === '--self-test') {
    if ($summary['status'] !== 'wal-hot-journal-savepoint-checkpoint-current-source-next243'
        || $summary['acceptedReaders'] !== ['wp-schema-reader', 'wp-options-reader', 'wp-index-reader']
        || $summary['observedDirtyPages'] !== [1, 2, 5]
    ) {
        fwrite(STDERR, "application-wal-hot-journal-savepoint-checkpoint-current-source-next243 self-test failed\n");
        exit(1);
    }

    echo "application-wal-hot-journal-savepoint-checkpoint-current-source-next243 self-test passed\n";
    exit(0);
}

echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
