<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan.php';

use PortLibs\LibSqlite\SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan;

$databasePath = '/srv/www/wp-content/database/wp-next187.sqlite';
$walPath = $databasePath . '-wal';
$postApply = [
    'status' => 'wal-hot-journal-savepoint-checkpoint-current-source-next183',
    'database_path' => $databasePath,
    'wal_path' => $walPath,
    'reader_source_token' => 'wal-hot-journal-savepoint-checkpoint-next183:current:wp-postapply187',
    'file_digest' => hash('sha256', 'wp-next187-published-files'),
    'verified_all_match' => true,
    'directory_sync_verified' => true,
    'hot_journal_deleted' => true,
    'dependencies' => ['sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next183'],
];
$reopen = [
    'status' => 'wal-hot-journal-savepoint-checkpoint-current-source-next184',
    'can_reuse_reader_marks' => true,
    'all_reader_pages_separated' => true,
    'salt_pair_rotated' => true,
    'checkpoint_sequence_advanced' => true,
    'source_transition_digest' => hash('sha256', 'wp-next187-retry-transition'),
    'next_wal_sha256' => hash('sha256', 'wp-next187-retry-wal'),
    'reader_page_numbers' => [1, 2, 3],
    'reader_next_sources' => ['checkpoint-database', 'next-wal', 'next-wal'],
    'dependencies' => ['sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next184'],
];
$retryToken = 'wal-hot-journal-savepoint-checkpoint-next187:retry:' . substr(hash('sha256', implode('|', [
    $postApply['reader_source_token'],
    $reopen['source_transition_digest'],
    $postApply['file_digest'],
    $reopen['next_wal_sha256'],
    implode(',', array_map('strval', $reopen['reader_page_numbers'])),
])), 0, 32);

$plan = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next187Plan($postApply, $reopen, [$retryToken]);

$summary = [
    'scenario' => 'application-wal-hot-journal-savepoint-checkpoint-current-source-next187',
    'applicationUse' => 'A copied Application import retires the hot-journal post-apply reader token before admitting a retry WAL checkpoint source.',
    'status' => $plan['status'],
    'canAdmitRetryCheckpointSource' => $plan['can_admit_retry_checkpoint_source'],
    'postApplyTokenRetired' => $plan['post_apply_token_retired'],
    'requiresReaderReopen' => $plan['requires_reader_reopen'],
    'retainedReaderTokens' => $plan['retained_reader_tokens'],
    'blockedReasons' => $plan['blocked_reasons'],
    'readerNextSources' => $plan['reader_next_sources'],
];

if (PHP_SAPI === 'cli' && in_array('--self-test', $argv, true)) {
    if ($summary['status'] !== 'wal-hot-journal-savepoint-checkpoint-current-source-next187'
        || $summary['canAdmitRetryCheckpointSource'] !== true
        || $summary['postApplyTokenRetired'] !== true
        || $summary['readerNextSources'] !== ['checkpoint-database', 'next-wal', 'next-wal']
    ) {
        fwrite(STDERR, "application-wal-hot-journal-savepoint-checkpoint-current-source-next187 self-test failed\n");
        exit(1);
    }

    echo "application-wal-hot-journal-savepoint-checkpoint-current-source-next187 self-test passed\n";
    exit(0);
}

echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
