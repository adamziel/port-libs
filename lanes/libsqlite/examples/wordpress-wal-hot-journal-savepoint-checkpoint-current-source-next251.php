<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan.php';

use PortLibs\LibSqlite\SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan;

$databasePath = '/srv/www/wp-content/database/wp-next251.sqlite';
$hash = static fn (string $value): string => hash('sha256', $value);
$handoffPlan = [
    'status' => 'wal-hot-journal-savepoint-checkpoint-current-source-next246',
    'durable_handoff_admitted' => true,
    'database_path' => $databasePath,
    'wal_path' => $databasePath . '-wal',
    'source_token' => 'wp-next251-current-source',
    'commit_generation' => 251,
    'checkpoint_frame' => 36,
    'commit_frames' => [32, 34, 36],
    'accepted_reader_names' => ['schema-reader', 'options-reader', 'terms-reader'],
    'database_digest' => $hash('wordpress next251 checkpoint database image'),
    'page_cache_digest' => $hash('wordpress next251 clean page cache'),
    'operation_names' => ['admit_durable_current_source_handoff_next246'],
    'dependencies' => ['sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next246'],
];
$receipt = static function (string $name, string $operation, array $override = []) use ($handoffPlan): array {
    return array_replace([
        'name' => $name,
        'operation' => $operation,
        'path' => $handoffPlan['wal_path'],
        'source_token' => $handoffPlan['source_token'],
        'commit_generation' => $handoffPlan['commit_generation'],
        'checkpoint_frame' => $handoffPlan['checkpoint_frame'],
        'exclusive_lock_held' => true,
        'released_reader_names' => [],
        'next_wal_salt' => null,
        'restart_frame' => 0,
        'truncate_bytes' => 0,
        'retired_commit_frames' => [],
        'synced' => false,
        'io_error' => null,
    ], $override);
};
$plan = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next251AdmitWalSidecarReset($handoffPlan, [
    $receipt('release-schema-reader', 'release_readmark', ['released_reader_names' => ['schema-reader']]),
    $receipt('release-options-readers', 'release_readmark', ['released_reader_names' => ['options-reader', 'terms-reader']]),
    $receipt('rewrite-empty-wal-header', 'rewrite_wal_header', ['next_wal_salt' => ['next251-salt-a', 'next251-salt-b']]),
    $receipt('truncate-retired-wal-frames', 'truncate_wal', ['retired_commit_frames' => [32, 34, 36]]),
    $receipt('sync-empty-wal-sidecar', 'sync_wal', ['synced' => true]),
]);
$summary = [
    'scenario' => 'wordpress-wal-hot-journal-savepoint-checkpoint-current-source-next251',
    'wordpressUse' => 'A copied WordPress database import can restart and truncate the WAL sidecar only after durable checkpoint handoff and all reader read-marks release.',
    'status' => $plan['status'],
    'walResetAdmitted' => $plan['wal_reset_admitted'],
    'releasedReaders' => $plan['released_reader_names'],
    'nextWalSalt' => $plan['next_wal_salt'],
    'truncateBytes' => $plan['truncate_bytes'],
    'dependencyClosure' => $plan['dependency_closure'],
];

if ($summary['status'] !== 'wal-hot-journal-savepoint-checkpoint-current-source-next251'
    || $summary['walResetAdmitted'] !== true
    || $summary['releasedReaders'] !== ['options-reader', 'schema-reader', 'terms-reader']
    || $summary['truncateBytes'] !== 0
) {
    fwrite(STDERR, "wordpress-wal-hot-journal-savepoint-checkpoint-current-source-next251 self-test failed\n");
    exit(1);
}

echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
