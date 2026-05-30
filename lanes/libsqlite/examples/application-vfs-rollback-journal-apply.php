<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteRollbackJournal;
use PortLibs\LibSqlite\SQLiteRollbackJournalHeader;
use PortLibs\LibSqlite\SQLiteVfsFileWriter;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$pageSize = 512;
$sectorSize = 512;
$nonce = 0x41424344;
$root = sys_get_temp_dir() . '/port-libsqlite-application-vfs-rollback-' . bin2hex(random_bytes(4));
$databasePath = '/srv/www/wp-content/database/.ht.sqlite';
$localDatabasePath = $root . $databasePath;
$localJournalPath = $localDatabasePath . '-journal';

$makeFirstPage = static function (int $databaseSizePages) use ($pageSize): string {
    $page = str_repeat("\0", $pageSize);
    $page = substr_replace($page, "SQLite format 3\0", 0, 16);
    $page = substr_replace($page, pack('n', $pageSize), 16, 2);
    $page[18] = "\x01";
    $page[19] = "\x01";
    $page[20] = "\x00";
    $page[21] = "\x40";
    $page[22] = "\x20";
    $page[23] = "\x20";
    $page = substr_replace($page, pack('N', $databaseSizePages), 28, 4);
    $page = substr_replace($page, pack('N', 1), 56, 4);

    return $page;
};

$pageOneClean = $makeFirstPage(2);
$pageTwoClean = str_pad('clean wp_options page before failed import', $pageSize, "\0");
$dirtyDatabase = $makeFirstPage(3)
    . str_pad('dirty wp_options page from interrupted import', $pageSize, "\0")
    . str_pad('new page allocated by failed import', $pageSize, "\0");
$journalHeader = SQLiteRollbackJournalHeader::MAGIC . pack('N*', 2, $nonce, 2, $sectorSize, $pageSize);
$journalBytes = str_pad($journalHeader, $sectorSize, "\0")
    . pack('N', 1) . $pageOneClean . pack('N', SQLiteRollbackJournal::pageChecksum($pageOneClean, $nonce))
    . pack('N', 2) . $pageTwoClean . pack('N', SQLiteRollbackJournal::pageChecksum($pageTwoClean, $nonce));

if (!mkdir(dirname($localDatabasePath), 0777, true) && !is_dir(dirname($localDatabasePath))) {
    throw new RuntimeException('Unable to create Application rollback VFS smoke directory');
}
file_put_contents($localDatabasePath, $dirtyDatabase);
file_put_contents($localJournalPath, $journalBytes);

$journal = SQLiteRollbackJournal::parse($journalBytes, true);
$writer = new SQLiteVfsFileWriter($root);
$applied = $writer->applyHotRollbackJournal($journal, $dirtyDatabase, $journalBytes, $databasePath);

echo json_encode([
    'scenario' => 'application-vfs-rollback-journal-apply',
    'applicationUse' => 'Apply accepted hot rollback-journal recovery through bounded native PHP file handles for copied wp_options database repairs, including database page restoration, durable database sync, rollback-journal deletion, and directory persistence diagnostics without ext/sqlite.',
    'root' => $root,
    'databasePath' => $databasePath,
    'localDatabaseBytes' => filesize($localDatabasePath),
    'localJournalExistsAfterRecovery' => is_file($localJournalPath),
    'containsCleanOptionPage' => str_contains((string) file_get_contents($localDatabasePath), 'clean wp_options page'),
    'containsDirtyOptionPage' => str_contains((string) file_get_contents($localDatabasePath), 'dirty wp_options page'),
    'recovery' => [
        'status' => $applied['status'],
        'applied' => $applied['applied'],
        'bytesWritten' => $applied['bytes_written'],
        'filesDeleted' => $applied['files_deleted'],
        'durableSyncs' => $applied['durable_syncs'],
        'directorySyncs' => $applied['directory_syncs'],
        'reason' => $applied['recovery']['reason'],
        'journalAction' => $applied['recovery']['journal_action'],
        'dependencies' => $applied['dependencies'],
    ],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
