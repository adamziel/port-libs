<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan;

require_once __DIR__ . '/../src/SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan.php';

$digest = static fn (string $value): string => hash('sha256', $value);

$base = [
    'status' => 'wal-hot-journal-savepoint-checkpoint-current-source-next212',
    'database_path' => '/srv/www/wp-content/database/wp.sqlite',
    'journal_path' => '/srv/www/wp-content/database/wp.sqlite-journal',
    'wal_path' => '/srv/www/wp-content/database/wp.sqlite-wal',
    'page_size' => 4096,
    'requested_checkpoint_frame' => 213,
    'checkpointed_frame' => 210,
    'busy' => true,
    'wal_action' => 'preserve_wal',
    'database_digest' => $digest('wp checkpoint database after hot journal recovery'),
    'wal_digest' => $digest('wp preserved wal after passive checkpoint'),
    'writer_digest' => $digest('wp writer generation after savepoint release'),
    'checkpoint_digest' => $digest('wp passive checkpoint reader pins'),
    'next_writer_generation' => 213,
    'minimum_statement_generation' => 209,
    'active_reader_names' => ['wp-options-front-end-reader'],
    'reopen_reader_names' => ['plugin-import-stale-reader', 'cron-cache-stale-reader'],
    'operation_names' => ['verify_passive_checkpoint_reader_pin_current_source_next212'],
    'dependencies' => ['sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next212'],
];

$receipt = static fn (string $name): array => [
    'name' => $name,
    'reader_end_frame' => 213,
    'reader_generation' => 213,
    'observed_database_digest' => $base['database_digest'],
    'observed_wal_digest' => $base['wal_digest'],
    'observed_writer_digest' => $base['writer_digest'],
    'observed_checkpoint_digest' => $base['checkpoint_digest'],
    'lock_receipt' => true,
];

$plan = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next213RestartAdmission(
    $base,
    [
        $receipt('plugin-import-stale-reader'),
        $receipt('cron-cache-stale-reader'),
    ],
    213
);

$summary = [
    'applicationUse' => 'After a copied wp_options import recovers a hot journal and a PASSIVE checkpoint preserves WAL bytes for an active reader, stale plugin/cron readers must reopen before a restart checkpoint may reset the WAL source.',
    'status' => $plan['status'],
    'restartAllowed' => $plan['restart_allowed'],
    'walAction' => $plan['wal_action'],
    'databaseAction' => $plan['database_action'],
    'requiredReopenedReaders' => $plan['required_reopen_reader_names'],
    'admittedReopenedReaders' => $plan['admitted_reopen_reader_names'],
    'blockedGuards' => $plan['blocked_guard_names'],
    'dependencyClosure' => $plan['dependency_closure'],
];

echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;

if ($summary['status'] !== 'wal-hot-journal-savepoint-checkpoint-current-source-next213'
    || $summary['restartAllowed'] !== true
    || $summary['walAction'] !== 'restart_wal_after_reopened_readers'
    || $summary['blockedGuards'] !== []
) {
    fwrite(STDERR, "Unexpected WAL restart checkpoint receipt summary\n");
    exit(1);
}
