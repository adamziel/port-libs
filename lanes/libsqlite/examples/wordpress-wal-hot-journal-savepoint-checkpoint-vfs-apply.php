<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteRollbackJournal;
use PortLibs\LibSqlite\SQLiteRollbackJournalHeader;
use PortLibs\LibSqlite\SQLiteSavepointStack;
use PortLibs\LibSqlite\SQLiteVfsFileWriter;
use PortLibs\LibSqlite\SQLiteWal;
use PortLibs\LibSqlite\SQLiteWalHeader;

$pageSize = 512;
$databasePath = 'wp-content/database/.ht.sqlite';
$page = static fn (string $label): string => str_pad($label, $pageSize, '.');
$basePages = [
    1 => $page('wp vfs-apply clean schema before import crash'),
    2 => $page('wp vfs-apply clean options before checkpoint'),
    3 => $page('wp vfs-apply clean plugin batch before savepoint'),
    4 => $page('wp vfs-apply clean transient before savepoint'),
];
$dirtyDatabaseBytes = $page('wp vfs-apply dirty schema from interrupted rollback')
    . $basePages[2]
    . $page('wp vfs-apply dirty plugin from rolled back savepoint')
    . $page('wp vfs-apply dirty transient from rolled back savepoint');

$journalHeader = SQLiteRollbackJournalHeader::MAGIC . pack('N*', 2, 0x16026001, 4, 512, $pageSize);
$journalBytes = str_pad($journalHeader, 512, "\0");
foreach ([1 => $basePages[1], 3 => $basePages[3]] as $pageNumber => $image) {
    $journalBytes .= pack('N', $pageNumber) . $image . pack('N', SQLiteRollbackJournal::pageChecksum($image, 0x16026001));
}

$salt1 = 0x16026011;
$salt2 = 0x16026022;
$prefix = pack('N*', SQLiteWalHeader::MAGIC_BIG_ENDIAN, 3007000, $pageSize, 160, $salt1, $salt2);
$seed = SQLiteWal::checksumPair($prefix, false);
$walBytes = $prefix . pack('N*', $seed[0], $seed[1]);
foreach ([
    [1, 0, 'wp vfs-apply committed schema after hot journal'],
    [2, 4, 'wp vfs-apply committed active_plugins option'],
    [3, 0, 'wp vfs-apply plugin draft rolled back'],
    [4, 4, 'wp vfs-apply transient draft discarded'],
] as [$pageNumber, $commitPageCount, $label]) {
    $image = $page($label);
    $framePrefix = pack('N*', $pageNumber, $commitPageCount, $salt1, $salt2);
    $seed = SQLiteWal::checksumPair(substr($framePrefix, 0, 8) . $image, false, $seed[0], $seed[1]);
    $walBytes .= $framePrefix . pack('N*', $seed[0], $seed[1]) . $image;
}

$savepoints = new SQLiteSavepointStack();
$savepoints->beginTransaction('wordpress-import-vfs-apply');
$savepoints->recordWalFrameWrite(1, 1, false);
$savepoints->recordWalFrameWrite(2, 2, true);
$savepoints->savepoint('plugin_batch_vfs-apply');
$savepoints->recordWalFrameWrite(3, 3, false);
$savepoints->recordWalFrameWrite(4, 4, true);

$root = sys_get_temp_dir() . '/port-libsqlite-wp-hot-savepoint-checkpoint-vfs-apply-' . bin2hex(random_bytes(4));
$databaseLocal = $root . '/' . $databasePath;
$journalLocal = $databaseLocal . '-journal';
$walLocal = $databaseLocal . '-wal';
if (!is_dir(dirname($databaseLocal)) && !mkdir(dirname($databaseLocal), 0777, true) && !is_dir(dirname($databaseLocal))) {
    throw new RuntimeException('Unable to create WordPress WAL hot-journal savepoint checkpoint smoke directory');
}
file_put_contents($databaseLocal, $dirtyDatabaseBytes);
file_put_contents($journalLocal, $journalBytes);
file_put_contents($walLocal, $walBytes);

try {
    $result = (new SQLiteVfsFileWriter($root))->applyHotJournalSavepointCheckpoint(
        $savepoints,
        'plugin_batch_vfs-apply',
        $databasePath,
        [1, 2, 3, 4],
        'truncate'
    );
    $databaseBytes = (string) file_get_contents($databaseLocal);

    echo json_encode([
        'status' => $result['status'],
        'hotRecovered' => $result['hot_journal']['recovered'],
        'checkpointMode' => $result['savepoint_checkpoint']['mode'],
        'retainedFrames' => $result['savepoint_checkpoint']['retained_frame_count'],
        'discardedFrames' => $result['savepoint_checkpoint']['discarded_frame_count'],
        'journalRemoved' => !is_file($journalLocal),
        'walBytes' => is_file($walLocal) ? filesize($walLocal) : 0,
        'hasCommittedOption' => str_contains($databaseBytes, 'wp vfs-apply committed active_plugins option'),
        'discardedDraft' => !str_contains($databaseBytes, 'wp vfs-apply plugin draft rolled back'),
    ], JSON_PRETTY_PRINT) . PHP_EOL;
} finally {
    foreach ([$walLocal, $journalLocal, $databaseLocal] as $path) {
        if (is_file($path)) {
            unlink($path);
        }
    }
    @rmdir(dirname($databaseLocal));
    @rmdir(dirname(dirname($databaseLocal)));
    @rmdir($root);
}
