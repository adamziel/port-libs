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
    1 => $page('wp next163 clean schema before import crash'),
    2 => $page('wp next163 clean options before checkpoint'),
    3 => $page('wp next163 clean plugin before savepoint'),
    4 => $page('wp next163 clean transient before savepoint'),
];
$dirtyDatabaseBytes = $page('wp next163 dirty schema from interrupted rollback')
    . $basePages[2]
    . $page('wp next163 dirty plugin from rolled back savepoint')
    . $page('wp next163 dirty transient from rolled back savepoint');

$nonce = 0x16326301;
$journalHeader = SQLiteRollbackJournalHeader::MAGIC . pack('N*', 2, $nonce, 4, 512, $pageSize);
$journalBytes = str_pad($journalHeader, 512, "\0");
foreach ([1 => $basePages[1], 3 => $basePages[3]] as $pageNumber => $image) {
    $journalBytes .= pack('N', $pageNumber) . $image . pack('N', SQLiteRollbackJournal::pageChecksum($image, $nonce));
}

$salt1 = 0x16326311;
$salt2 = 0x16326322;
$prefix = pack('N*', SQLiteWalHeader::MAGIC_BIG_ENDIAN, 3007000, $pageSize, 163, $salt1, $salt2);
$seed = SQLiteWal::checksumPair($prefix, false);
$walBytes = $prefix . pack('N*', $seed[0], $seed[1]);
foreach ([
    [1, 0, 'wp next163 retained schema for pinned reader'],
    [2, 4, 'wp next163 retained active_plugins option'],
    [3, 0, 'wp next163 plugin draft rolled back'],
    [4, 4, 'wp next163 transient draft discarded'],
] as [$pageNumber, $commitPageCount, $label]) {
    $image = $page($label);
    $framePrefix = pack('N*', $pageNumber, $commitPageCount, $salt1, $salt2);
    $seed = SQLiteWal::checksumPair(substr($framePrefix, 0, 8) . $image, false, $seed[0], $seed[1]);
    $walBytes .= $framePrefix . pack('N*', $seed[0], $seed[1]) . $image;
}

$savepoints = new SQLiteSavepointStack();
$savepoints->beginTransaction('wordpress-import-next163');
$savepoints->recordWalFrameWrite(1, 1, false);
$savepoints->recordWalFrameWrite(2, 2, true);
$savepoints->savepoint('plugin_batch_next163');
$savepoints->recordWalFrameWrite(3, 3, false);
$savepoints->recordWalFrameWrite(4, 4, true);

$root = sys_get_temp_dir() . '/port-libsqlite-wp-hot-savepoint-checkpoint-next163-' . bin2hex(random_bytes(4));
$databaseLocal = $root . '/' . $databasePath;
$journalLocal = $databaseLocal . '-journal';
$walLocal = $databaseLocal . '-wal';
if (!is_dir(dirname($databaseLocal)) && !mkdir(dirname($databaseLocal), 0777, true) && !is_dir(dirname($databaseLocal))) {
    throw new RuntimeException('Unable to create WordPress WAL hot-journal savepoint checkpoint next163 smoke directory');
}
file_put_contents($databaseLocal, $dirtyDatabaseBytes);
file_put_contents($journalLocal, $journalBytes);
file_put_contents($walLocal, $walBytes);

try {
    $result = (new SQLiteVfsFileWriter($root))->applyHotJournalSavepointCheckpointPinnedReaderCurrentSourceNext163(
        $savepoints,
        'plugin_batch_next163',
        $databasePath,
        [1, 2, 3, 4],
        1
    );
    $databaseBytes = (string) file_get_contents($databaseLocal);
    $walAfter = is_file($walLocal) ? (string) file_get_contents($walLocal) : '';

    echo json_encode([
        'status' => $result['status'],
        'hotRecovered' => $result['hot_journal']['recovered'],
        'checkpointReason' => $result['pinned_reader']['checkpoint_reason'],
        'walAction' => $result['pinned_reader']['wal_action'],
        'retainedFrames' => $result['savepoint_checkpoint']['retained_frame_count'],
        'discardedFrames' => $result['savepoint_checkpoint']['discarded_frame_count'],
        'journalRemoved' => !is_file($journalLocal),
        'walBytes' => strlen($walAfter),
        'walFrames' => $walAfter === '' ? 0 : SQLiteWal::parse($walAfter, $pageSize, true)->frameCount(),
        'hasPinnedSchema' => str_contains($databaseBytes, 'wp next163 retained schema for pinned reader'),
        'discardedDraft' => !str_contains($databaseBytes, 'wp next163 plugin draft rolled back'),
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
