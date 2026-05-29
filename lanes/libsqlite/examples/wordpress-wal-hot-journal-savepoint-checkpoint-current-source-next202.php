<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan.php';

use PortLibs\LibSqlite\SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan;

$databasePath = '/srv/www/wp-content/database/wp-next202.sqlite';
$databaseBytes = str_repeat('wp next202 checkpointed database page ', 18);
$databaseDigest = hash('sha256', $databaseBytes);
$walDigest = hash('sha256', 'wp next202 restarted wal sidecar');
$sidecarDigest = hash('sha256', 'wp next202 sidecar publication');
$publication = [
    'status' => 'wal-hot-journal-savepoint-checkpoint-current-source-next196',
    'database_path' => $databasePath,
    'journal_path' => $databasePath . '-journal',
    'wal_path' => $databasePath . '-wal',
    'mode' => 'restart',
    'checkpointed_database_digest' => $databaseDigest,
    'persisted_wal_digest' => $walDigest,
    'sidecar_digest' => $sidecarDigest,
    'operation_names' => ['publish_wal_sidecar_current_source_next196'],
    'dependencies' => ['sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next196'],
];
$plan = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next202Plan(
    $publication,
    $databaseBytes,
    '',
    true,
    true,
    true,
    true,
    true,
    [
        ['name' => 'wp-options-import-select', 'kind' => 'statement', 'observed_database_digest' => $databaseDigest, 'observed_wal_digest' => $walDigest, 'observed_sidecar_digest' => $sidecarDigest, 'observed_mode' => 'restart'],
        ['name' => 'wp-postmeta-stale-reader', 'kind' => 'reader', 'observed_database_digest' => hash('sha256', 'old database'), 'observed_wal_digest' => $walDigest, 'observed_sidecar_digest' => $sidecarDigest, 'observed_mode' => 'restart'],
    ]
);

$summary = [
    'scenario' => 'wordpress-wal-hot-journal-savepoint-checkpoint-current-source-next202',
    'wordpressUse' => 'A copied WordPress import reuses wp_options handles after hot-journal recovery, savepoint release, checkpoint publication, WAL sidecar sync, and directory sync while reopening stale readers.',
    'status' => $plan['status'],
    'admittedHandles' => $plan['admitted_handle_names'],
    'reopenHandles' => $plan['reopen_handle_names'],
    'guardMatches' => $plan['guard_matches'],
    'dependencyClosure' => $plan['dependency_closure'],
];

if ($summary['status'] !== 'wal-hot-journal-savepoint-checkpoint-current-source-next202'
    || $summary['admittedHandles'] !== ['wp-options-import-select']
    || $summary['reopenHandles'] !== ['wp-postmeta-stale-reader']
) {
    fwrite(STDERR, "wordpress-wal-hot-journal-savepoint-checkpoint-current-source-next202 self-test failed\n");
    exit(1);
}

echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
