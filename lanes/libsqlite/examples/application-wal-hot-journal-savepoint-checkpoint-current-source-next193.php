<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan.php';

use PortLibs\LibSqlite\SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan;

$databasePath = '/srv/www/wp-content/database/wp-next193.sqlite';
$retryToken = 'wal-hot-journal-savepoint-checkpoint-next187:retry:' . str_repeat('b', 32);
$handoff = [
    'status' => 'wal-hot-journal-savepoint-checkpoint-current-source-next187',
    'database_path' => $databasePath,
    'wal_path' => $databasePath . '-wal',
    'retry_reader_token' => $retryToken,
    'can_admit_retry_checkpoint_source' => true,
    'stale_reader_tokens' => [],
    'retry_transition_digest' => hash('sha256', 'application-next193-transition'),
    'next_wal_sha256' => hash('sha256', 'application-next193-wal'),
    'dependencies' => ['sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next187'],
];

$plan = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next193PublishReaderMarks(
    $handoff,
    [
        [
            'slot' => 0,
            'page_number' => 1,
            'reader_token' => $retryToken,
            'generation' => 193,
            'source' => 'checkpoint-database',
            'frame_index' => null,
        ],
        [
            'slot' => 1,
            'page_number' => 2,
            'reader_token' => $retryToken,
            'generation' => 193,
            'source' => 'next-wal',
            'frame_index' => 1,
        ],
        [
            'slot' => 2,
            'page_number' => 3,
            'reader_token' => $retryToken,
            'generation' => 193,
            'source' => 'next-wal',
            'frame_index' => 2,
        ],
    ],
    193,
    [1, 2, 3]
);

$summary = [
    'scenario' => 'application-wal-hot-journal-savepoint-checkpoint-current-source-next193',
    'status' => $plan['status'],
    'databasePath' => $plan['database_path'],
    'walPath' => $plan['wal_path'],
    'publishedPages' => $plan['published_pages'],
    'publishedSources' => $plan['published_sources'],
    'readerMarkDigest' => $plan['reader_mark_digest'],
    'canPublishReaderMarks' => $plan['can_publish_reader_marks'],
];

if (in_array('--self-test', $argv, true)) {
    if (
        $summary['status'] !== 'wal-hot-journal-savepoint-checkpoint-current-source-next193'
        || $summary['publishedPages'] !== [1, 2, 3]
        || $summary['publishedSources'] !== ['checkpoint-database', 'next-wal']
        || $summary['canPublishReaderMarks'] !== true
    ) {
        fwrite(STDERR, "application-wal-hot-journal-savepoint-checkpoint-current-source-next193 self-test failed\n");
        exit(1);
    }

    echo "application-wal-hot-journal-savepoint-checkpoint-current-source-next193 self-test passed\n";
    exit(0);
}

echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
