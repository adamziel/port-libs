<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan;

require_once __DIR__ . '/../src/SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan.php';

$pageSize = 512;
$page = static fn (string $label): string => str_pad($label, $pageSize, '.', STR_PAD_RIGHT);
$digest = static fn (string $bytes): string => hash('sha256', $bytes);
$database = $page('wp next207 schema checkpoint') . $page('wp next207 option checkpoint');
$walDigest = $digest('wp next207 checkpoint wal generation');
$lockToken = 'wp-next207-exclusive-wal-write-lock';
$pageDigests = [
    1 => $digest($page('wp next207 schema checkpoint')),
    2 => $digest($page('wp next207 option checkpoint')),
];

$plan = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::writeCursorAdmissionPlan(
    [
        'status' => 'wal-hot-journal-savepoint-checkpoint-current-source-next206',
        'database_path' => '/srv/www/wp-content/database/wp-options.sqlite',
        'journal_path' => '/srv/www/wp-content/database/wp-options.sqlite-journal',
        'wal_path' => '/srv/www/wp-content/database/wp-options.sqlite-wal',
        'page_size' => $pageSize,
        'checkpointed_database_digest' => $digest($database),
        'expected_wal_digest' => $walDigest,
        'expected_page_digests' => $pageDigests,
        'admitted_consumer_names' => ['wp-options-select-current'],
        'blocked_guard_names' => [],
        'operation_names' => ['verify_reopened_statement_generation_current_source_next206'],
        'dependencies' => ['sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next206'],
    ],
    [
        [
            'name' => 'wp-options-current-write-cursor',
            'consumer_name' => 'wp-options-select-current',
            'cursor_generation' => 207,
            'commit_generation' => 208,
            'write_lock_token' => $lockToken,
            'root_pages' => [1, 2],
            'observed_database_digest' => $digest($database),
            'observed_wal_digest' => $walDigest,
            'observed_page_digests' => $pageDigests,
        ],
        [
            'name' => 'wp-options-stale-savepoint-cursor',
            'consumer_name' => 'wp-options-select-current',
            'cursor_generation' => 206,
            'commit_generation' => 206,
            'write_lock_token' => $lockToken,
            'root_pages' => [2],
            'observed_database_digest' => $digest($database),
            'observed_wal_digest' => $walDigest,
            'observed_page_digests' => [2 => $pageDigests[2]],
            'pending_savepoint_depth' => 1,
        ],
    ],
    $lockToken,
    208
);

$summary = [
    'scenario' => 'application-wal-hot-journal-savepoint-checkpoint-current-source-next207',
    'applicationUse' => 'After a copied Application import recovers a hot journal, rolls back a failed savepoint, checkpoints WAL pages, and reprepares statements, only write cursors with the current database/WAL digests and exclusive lock token may commit wp_options changes.',
    'status' => $plan['status'],
    'admittedCursors' => $plan['admitted_cursor_names'],
    'blockedCursors' => $plan['blocked_cursor_names'],
    'dependencyClosure' => $plan['dependency_closure'],
];

if ($summary['status'] !== 'wal-hot-journal-savepoint-checkpoint-current-source-next207'
    || $summary['admittedCursors'] !== ['wp-options-current-write-cursor']
    || $summary['blockedCursors'] !== ['wp-options-stale-savepoint-cursor']
) {
    fwrite(STDERR, "application-wal-hot-journal-savepoint-checkpoint-current-source-next207 self-test failed\n");
    exit(1);
}

echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
