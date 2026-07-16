<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePagerJournalOpenPlan;
use PortLibs\LibSqlite\SQLiteRollbackJournal;
use PortLibs\LibSqlite\SQLiteRollbackJournalHeader;
use PortLibs\LibSqlite\SQLiteVfsFileWriter;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$pageSize = 512;
$sectorSize = 512;
$nonce = 0x20260527;
$root = sys_get_temp_dir() . '/port-libsqlite-application-pager-journal-open-' . bin2hex(random_bytes(4));
$databasePath = '/srv/www/wp-content/database/.ht.sqlite';
$journalPage = str_pad('wp_options clean image before failed plugin import', $pageSize, "\0");
$hotJournalBytes = str_pad(SQLiteRollbackJournalHeader::MAGIC . pack('N*', 1, $nonce, 1, $sectorSize, $pageSize), $sectorSize, "\0")
    . pack('N', 1) . $journalPage . pack('N', SQLiteRollbackJournal::pageChecksum($journalPage, $nonce));

$blocked = SQLitePagerJournalOpenPlan::open($databasePath, $pageSize, 'delete', $hotJournalBytes);
$plan = SQLitePagerJournalOpenPlan::openAndCloseWithoutDirtyPages($databasePath, $pageSize, 'persist');

$directory = $root . dirname($databasePath);
if (!is_dir($directory) && !mkdir($directory, 0777, true) && !is_dir($directory)) {
    throw new RuntimeException('Unable to create Application pager journal directory');
}

$writer = new SQLiteVfsFileWriter($root);
$applied = $writer->applyOperations($plan['operations'], $plan['payloads'], $plan['dependencies']);

echo json_encode([
    'scenario' => 'application-pager-journal-open-closure',
    'applicationUse' => 'Open and close an unused rollback-journal transaction for copied wp_options imports through native PHP pager/VFS primitives, while refusing to start a write transaction over a hot rollback journal that must be recovered first.',
    'root' => $root,
    'databasePath' => $databasePath,
    'hotJournalBlocked' => [
        'status' => $blocked['status'],
        'reason' => $blocked['reason'],
        'hot' => $blocked['hot_journal']['hot'] ?? null,
        'journalBytes' => $blocked['hot_journal']['journal_bytes'] ?? null,
    ],
    'plan' => [
        'status' => $plan['status'],
        'journalMode' => $plan['journal_mode'],
        'operationReasons' => array_column($plan['operations'], 'reason'),
        'dependencies' => $plan['dependencies'],
    ],
    'applied' => [
        'status' => $applied['status'],
        'operations' => $applied['applied'],
        'bytesWritten' => $applied['bytes_written'],
        'journalBytesAfterClose' => filesize($root . $databasePath . '-journal'),
        'directorySyncs' => $applied['directory_syncs'],
        'dependencies' => $applied['dependencies'],
    ],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
