<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteRollbackJournalCommitPlan;
use PortLibs\LibSqlite\SQLiteVfsFileWriter;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$pageSize = 512;
$root = sys_get_temp_dir() . '/port-libsqlite-application-vfs-rollback-commit-' . bin2hex(random_bytes(4));
$databasePath = '/srv/www/wp-content/database/.ht.sqlite';
$journalBytes = str_pad('rollback journal with copied wp_options preimages', $pageSize, "\0");
$schemaPage = str_pad('SQLite format 3' . "\0" . 'schema after option insert', $pageSize, "\0");
$optionsPage = str_pad('wp_options committed plugin setting row', $pageSize, "\0");
$autoloadIndexPage = str_pad('autoload index committed plugin setting key', $pageSize, "\0");

$plan = SQLiteRollbackJournalCommitPlan::commit(
    $databasePath,
    $journalBytes,
    [1 => $schemaPage, 2 => $optionsPage, 5 => $autoloadIndexPage],
    $pageSize,
    'full',
    'delete'
);

$writer = new SQLiteVfsFileWriter($root);
$applied = $writer->applyRollbackJournalCommit(
    $databasePath,
    $journalBytes,
    [1 => $schemaPage, 2 => $optionsPage, 5 => $autoloadIndexPage],
    $pageSize,
    'full',
    'delete'
);

echo json_encode([
    'scenario' => 'application-vfs-rollback-commit-apply',
    'applicationUse' => 'Apply rollback-journal commit ordering for copied wp_options imports through native PHP file handles: journal bytes are written and synced before dirty database pages, database pages are synced before journal deletion, and the directory entry is persisted without ext/sqlite.',
    'root' => $root,
    'databasePath' => $databasePath,
    'localDatabaseBytes' => filesize($root . $databasePath),
    'localJournalExistsAfterCommit' => is_file($root . $databasePath . '-journal'),
    'plan' => [
        'syncMode' => $plan['sync_mode'],
        'journalMode' => $plan['journal_mode'],
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
