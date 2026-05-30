<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteRollbackJournalCommitPlan;
use PortLibs\LibSqlite\SQLiteVfsFileWriter;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$pageSize = 512;
$root = sys_get_temp_dir() . '/port-libsqlite-application-vfs-temp-journal-' . bin2hex(random_bytes(4));
$databasePath = '/tmp/etilqs_wp_options_sort';
$journalPath = '/tmp/etilqs_wp_options_sort-journal-a1b2';
$journalBytes = str_pad('temporary rollback journal for copied wp_options sort', $pageSize, "\0");
$schemaPage = str_pad('SQLite format 3' . "\0" . 'temp schema for import sort', $pageSize, "\0");
$sortPage = str_pad('temporary wp_options option_name sort btree', $pageSize, "\0");

$plan = SQLiteRollbackJournalCommitPlan::commitTemporary(
    $databasePath,
    $journalPath,
    $journalBytes,
    [1 => $schemaPage, 3 => $sortPage],
    $pageSize,
    'normal',
    'persist'
);

$applied = (new SQLiteVfsFileWriter($root))->applyTemporaryRollbackJournalCommit(
    $databasePath,
    $journalPath,
    $journalBytes,
    [1 => $schemaPage, 3 => $sortPage],
    $pageSize,
    'normal',
    'persist'
);

echo json_encode([
    'scenario' => 'application-vfs-temp-journal-commit',
    'applicationUse' => 'Apply a copied wp_options temporary b-tree commit through native PHP file handles: the temporary rollback journal is synced before temp database pages, then deleted on commit even when a persistent journal mode was requested, without requiring ext/sqlite.',
    'root' => $root,
    'databasePath' => $databasePath,
    'journalPath' => $journalPath,
    'localDatabaseBytes' => filesize($root . $databasePath),
    'localTempJournalExistsAfterCommit' => is_file($root . $journalPath),
    'plan' => [
        'temporary' => $plan['temporary'],
        'requestedJournalMode' => $plan['requested_journal_mode'],
        'effectiveJournalMode' => $plan['journal_mode'],
        'databasePages' => $plan['database_pages'],
        'operationReasons' => array_column($plan['operations'], 'reason'),
        'dependencies' => $plan['dependencies'],
    ],
    'applied' => [
        'status' => $applied['status'],
        'operations' => $applied['applied'],
        'bytesWritten' => $applied['bytes_written'],
        'durableSyncs' => $applied['durable_syncs'],
        'directorySyncs' => $applied['directory_syncs'],
        'filesDeleted' => $applied['files_deleted'],
        'dependencies' => $applied['dependencies'],
    ],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
