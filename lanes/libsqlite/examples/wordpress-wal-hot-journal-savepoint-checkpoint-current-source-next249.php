<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteWalHotJournalSavepointCheckpointCurrentSourceNext249Plan;

require_once __DIR__ . '/../src/SQLiteWalHotJournalSavepointCheckpointCurrentSourceNext249Plan.php';

$hash = static fn (string $value): string => hash('sha256', $value);
$databaseDigest = $hash('wordpress next249 checkpoint database image');
$pageCacheDigest = $hash('wordpress next249 clean checkpoint page cache');
$handoffPlan = [
    'status' => 'wal-hot-journal-savepoint-checkpoint-current-source-next246',
    'durable_handoff_admitted' => true,
    'database_path' => '/srv/www/wp-content/database/wp-next249.sqlite',
    'journal_path' => '/srv/www/wp-content/database/wp-next249.sqlite-journal',
    'wal_path' => '/srv/www/wp-content/database/wp-next249.sqlite-wal',
    'source_token' => 'wp-next249-current-source',
    'commit_generation' => 249,
    'schema_cookie' => 949,
    'database_digest' => $databaseDigest,
    'page_cache_digest' => $pageCacheDigest,
    'checkpoint_frame' => 34,
    'dirty_pages' => [1, 2, 6, 11],
    'commit_frames' => [31, 32, 34],
    'accepted_reader_names' => ['wp-schema-reader', 'wp-options-reader', 'wp-autoload-reader'],
    'operation_names' => ['admit_durable_current_source_handoff_next246'],
    'dependencies' => ['sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next246'],
];

$reader = static fn (string $name): array => [
    'name' => $name,
    'source_token' => $handoffPlan['source_token'],
    'reader_generation' => $handoffPlan['commit_generation'],
    'checkpoint_frame' => $handoffPlan['checkpoint_frame'],
    'snapshot_reopened' => true,
    'readmark_cleared' => true,
];

$plan = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNext249Plan::verifyReopenedCurrentSource($handoffPlan, [
    'database_path' => $handoffPlan['database_path'],
    'journal_path' => $handoffPlan['journal_path'],
    'wal_path' => $handoffPlan['wal_path'],
    'source_token' => $handoffPlan['source_token'],
    'commit_generation' => $handoffPlan['commit_generation'],
    'schema_cookie' => $handoffPlan['schema_cookie'],
    'database_digest' => $databaseDigest,
    'page_cache_digest' => $pageCacheDigest,
    'wal_commit_frames' => [31, 32, 34],
    'clean_page_numbers' => [1, 2, 6, 11],
    'journal_exists' => false,
    'wal_exists' => true,
    'reader_states' => [
        $reader('wp-schema-reader'),
        $reader('wp-options-reader'),
        $reader('wp-autoload-reader'),
    ],
]);

$summary = [
    'scenario' => 'wordpress-wal-hot-journal-savepoint-checkpoint-current-source-next249',
    'wordpressUse' => 'A copied WordPress import reopens after a hot-journal savepoint checkpoint and confirms the database digest, clean page cache, WAL commit frames, retired journal, and reader epochs all point at the admitted current source.',
    'status' => $plan['status'],
    'checkpointAction' => $plan['checkpoint_action'],
    'walAction' => $plan['wal_action'],
    'journalAction' => $plan['journal_action'],
    'acceptedReaders' => $plan['accepted_reader_names'],
    'dependencyClosure' => $plan['dependency_closure'],
];

if (($argv[1] ?? null) === '--self-test') {
    if ($summary['status'] !== 'wal-hot-journal-savepoint-checkpoint-current-source-next249'
        || $summary['checkpointAction'] !== 'serve_checkpoint_database_as_current_source'
        || $summary['acceptedReaders'] !== ['wp-schema-reader', 'wp-options-reader', 'wp-autoload-reader']
    ) {
        fwrite(STDERR, "wordpress-wal-hot-journal-savepoint-checkpoint-current-source-next249 self-test failed\n");
        exit(1);
    }

    echo "wordpress-wal-hot-journal-savepoint-checkpoint-current-source-next249 self-test passed\n";
    exit(0);
}

echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
