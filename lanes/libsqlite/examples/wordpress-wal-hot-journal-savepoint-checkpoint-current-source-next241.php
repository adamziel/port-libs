<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan;

require_once __DIR__ . '/../src/SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan.php';

$hash = static fn (string $value): string => hash('sha256', $value);
$databaseDigest = $hash('wordpress next241 checkpoint database image');
$writerPlan = [
    'status' => 'wal-hot-journal-savepoint-checkpoint-current-source-next238',
    'writer_admitted' => true,
    'database_path' => '/srv/www/wp-content/database/wp-next241.sqlite',
    'wal_path' => '/srv/www/wp-content/database/wp-next241.sqlite-wal',
    'journal_path' => '/srv/www/wp-content/database/wp-next241.sqlite-journal',
    'source_token' => 'wp-next241-current-source',
    'published_writer_generation' => 240,
    'next_writer_generation' => 241,
    'database_digest' => $databaseDigest,
    'expected_schema_cookie' => 24177,
    'expected_wal_salt' => '2410abcd2410dcba',
    'covered_page_numbers' => [1, 2, 3, 4, 5, 8],
    'operation_names' => ['admit_next_writer_after_restart_checkpoint_next238'],
    'dependencies' => ['sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next238'],
];

$receipt = static function (string $name, string $kind, string $path, array $overrides = []) use ($writerPlan, $databaseDigest, $hash): array {
    return array_replace([
        'name' => $name,
        'kind' => $kind,
        'path' => $path,
        'source_token' => $writerPlan['source_token'],
        'generation' => $writerPlan['next_writer_generation'],
        'schema_cookie' => $writerPlan['expected_schema_cookie'],
        'wal_salt' => $writerPlan['expected_wal_salt'],
        'database_digest' => $databaseDigest,
        'page_numbers' => [2, 5],
        'frame_numbers' => [1, 2, 3],
        'commit_marker_present' => true,
        'transaction_complete' => true,
        'commit_digest' => $hash('wordpress next241 committed autoload update frames'),
        'synced' => true,
        'frames_synced' => true,
        'hot_journal_visible' => false,
        'reserved_lock_released' => true,
        'shared_lock_preserved' => true,
        'directory_synced' => true,
        'persisted_paths' => [
            $writerPlan['database_path'],
            $writerPlan['wal_path'],
            $writerPlan['journal_path'],
        ],
    ], $overrides);
};

$plan = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next241AdmitCommittedWriter($writerPlan, [
    $receipt('wp-next241-commit', 'commit', $writerPlan['wal_path']),
    $receipt('wp-next241-wal', 'wal', $writerPlan['wal_path'], ['page_numbers' => [2, 5, 8]]),
    $receipt('wp-next241-lock', 'lock', $writerPlan['database_path'], ['page_numbers' => [1]]),
    $receipt('wp-next241-directory', 'directory', dirname($writerPlan['database_path']), ['page_numbers' => [1, 2, 5]]),
]);

$summary = [
    'scenario' => 'wordpress-wal-hot-journal-savepoint-checkpoint-current-source-next241',
    'status' => $plan['status'],
    'currentSourceAdvanced' => $plan['current_source_advanced'],
    'readerAction' => $plan['reader_action'],
    'writerAction' => $plan['writer_action'],
    'walAction' => $plan['wal_action'],
    'committedFrames' => $plan['committed_frame_numbers'],
    'blockedReasons' => $plan['blocked_reasons'],
    'dependencies' => $plan['dependencies'],
];

if (in_array('--self-test', $argv, true)) {
    if ($summary['status'] !== 'wal-hot-journal-savepoint-checkpoint-current-source-next241'
        || $summary['currentSourceAdvanced'] !== true
        || $summary['committedFrames'] !== [1, 2, 3]
        || $summary['blockedReasons'] !== []
    ) {
        fwrite(STDERR, "wordpress-wal-hot-journal-savepoint-checkpoint-current-source-next241 self-test failed\n");
        exit(1);
    }

    echo "wordpress-wal-hot-journal-savepoint-checkpoint-current-source-next241 self-test passed\n";
    return;
}

echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
