<?php

declare(strict_types=1);

require dirname(__DIR__) . '/../../tools/bootstrap.php';

use PortLibs\LibSqlite\SQLitePagerJournalOpenPlan;
use PortLibs\LibSqlite\SQLitePragmaLockingMode;
use PortLibs\LibSqlite\SQLiteRollbackJournalCommitPlan;
use PortLibs\LibSqlite\SQLiteVfsSyncPlan;

$pageSize = 512;
$databasePath = '/wp-content/database/.ht.sqlite';
$page = static fn (string $label): string => str_pad($label, $pageSize, '.', STR_PAD_RIGHT);
$journalBytes = str_repeat('J', 96);
$dirtyPages = [
    1 => $page('wp-options-root'),
    4 => $page('plugin-options-leaf'),
];

$pragma = new SQLitePragmaLockingMode();
$openClose = SQLitePagerJournalOpenPlan::openAndCloseWithoutDirtyPages($databasePath, $pageSize, 'persist');
$commit = SQLiteRollbackJournalCommitPlan::commit($databasePath, $journalBytes, $dirtyPages, $pageSize, 'full', 'persist');
$temporary = SQLiteRollbackJournalCommitPlan::commitTemporary(
    $databasePath,
    '/tmp/wp-options-import-journal',
    $journalBytes,
    $dirtyPages,
    $pageSize,
    'normal',
    'truncate'
);

echo json_encode([
    'scenario' => 'application-transaction-journal-mode-corpus',
    'applicationUse' => 'Plan copied wp_options rollback-journal transaction open/close, persistent-journal commit ordering, temp-journal delete-on-commit, and locking_mode preflight without ext/sqlite.',
    'lockingMode' => [
        'initial' => $pragma->execute('PRAGMA locking_mode')['locking_mode'],
        'exclusive' => $pragma->execute('PRAGMA locking_mode=exclusive')['locking_mode'],
        'temp' => $pragma->execute('PRAGMA temp.locking_mode=normal')['locking_mode'],
    ],
    'openClose' => [
        'journalMode' => $openClose['journal_mode'],
        'operationReasons' => array_column($openClose['operations'], 'reason'),
        'payloadBytes' => array_map('strlen', $openClose['payloads']),
    ],
    'commit' => [
        'syncMode' => $commit['sync_mode'],
        'journalMode' => $commit['journal_mode'],
        'databasePages' => $commit['database_pages'],
        'operationReasons' => array_column($commit['operations'], 'reason'),
    ],
    'temporaryCommit' => [
        'requestedJournalMode' => $temporary['requested_journal_mode'],
        'effectiveJournalMode' => $temporary['journal_mode'],
        'journalPath' => $temporary['journal_path'],
        'operationReasons' => array_column($temporary['operations'], 'reason'),
    ],
    'syncTargets' => array_column(SQLiteVfsSyncPlan::rollbackCommitSequence($databasePath, 'full', true), 'target'),
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
