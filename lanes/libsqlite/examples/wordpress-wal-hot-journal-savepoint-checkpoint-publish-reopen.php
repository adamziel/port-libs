<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/SQLiteWalHeader.php';
require_once __DIR__ . '/../src/SQLiteWalFrame.php';
require_once __DIR__ . '/../src/SQLiteWal.php';
require_once __DIR__ . '/../src/SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan.php';
require_once __DIR__ . '/../src/SQLiteVfsFileWriter.php';

use PortLibs\LibSqlite\SQLiteVfsFileWriter;
use PortLibs\LibSqlite\SQLiteWal;
use PortLibs\LibSqlite\SQLiteWalHeader;
use PortLibs\LibSqlite\SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan;

$pageSize = 512;
$databasePath = '/srv/www/wp-content/database/wp-next178.sqlite';
$page = static fn (string $label): string => str_pad($label, $pageSize, '.', STR_PAD_RIGHT);
$databaseBytes = $page('wp next178 dirty options root') . $page('wp next178 dirty active_plugins');
$journalBytes = 'wp-next178-hot-journal';
$makeWal = static function (array $frames, int $checkpoint, int $salt1, int $salt2) use ($pageSize, $page): string {
    $prefix = pack('N*', SQLiteWalHeader::MAGIC_BIG_ENDIAN, 3007000, $pageSize, $checkpoint, $salt1, $salt2);
    $seed = SQLiteWal::checksumPair($prefix, false);
    $bytes = $prefix . pack('N*', $seed[0], $seed[1]);
    foreach ($frames as [$pageNumber, $commitPageCount, $label]) {
        $image = $page($label);
        $framePrefix = pack('N*', $pageNumber, $commitPageCount, $salt1, $salt2);
        $seed = SQLiteWal::checksumPair(substr($framePrefix, 0, 8) . $image, false, $seed[0], $seed[1]);
        $bytes .= $framePrefix . pack('N*', $seed[0], $seed[1]) . $image;
    }

    return $bytes;
};

$currentWalBytes = $makeWal([[1, 2, 'wp next178 current options commit']], 178, 0x17800201, 0x17800202);
$nextWalBytes = $makeWal([[2, 2, 'wp next178 retry active_plugins']], 179, 0x17900201, 0x17900202);
$currentWal = SQLiteWal::parse($currentWalBytes, $pageSize, true);
$nextWal = SQLiteWal::parse($nextWalBytes, $pageSize, true);
$bootstrap = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next167Plan(
    $databasePath,
    $databaseBytes,
    $pageSize,
    'wp-plugin-import-next178',
    [1 => $page('wp next178 hot rollback options')],
    [2 => $page('wp next178 savepoint before active_plugins')],
    $currentWal,
    $currentWalBytes,
    $nextWal,
    $nextWalBytes,
    [1 => ['image' => $page('wp next178 current options commit'), 'source_id' => 'bootstrap', 'epoch' => 1]],
    [1, 2],
    [['name' => 'bootstrap', 'source_id' => 'bootstrap', 'epoch' => 1]],
    null,
    null,
    null,
    'restart',
    1,
    178
);
$current = $bootstrap['current_source_token'];
$next = $bootstrap['next_source_token'];
$prepared = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next167Plan(
    $databasePath,
    $databaseBytes,
    $pageSize,
    'wp-plugin-import-next178',
    [1 => $page('wp next178 hot rollback options')],
    [2 => $page('wp next178 savepoint before active_plugins')],
    $currentWal,
    $currentWalBytes,
    $nextWal,
    $nextWalBytes,
    [
        1 => ['image' => $page('wp next178 current options commit'), 'source_id' => $current['id'], 'epoch' => $current['epoch']],
        2 => ['image' => $page('wp next178 savepoint before active_plugins'), 'source_id' => $current['id'], 'epoch' => $current['epoch']],
    ],
    [1, 2],
    [
        ['name' => 'wp-current', 'source_id' => $current['id'], 'epoch' => $current['epoch']],
        ['name' => 'wp-next', 'source_id' => $next['id'], 'epoch' => $next['epoch']],
    ],
    $current,
    $next,
    null,
    'restart',
    1,
    178
);

$root = sys_get_temp_dir() . '/port-libs-wp-next178-' . bin2hex(random_bytes(3));
$local = $root . '/' . ltrim($databasePath, '/');
mkdir(dirname($local), 0777, true);
file_put_contents($local, $databaseBytes);
file_put_contents($local . '-journal', $journalBytes);
file_put_contents($local . '-wal', $currentWalBytes);

try {
    $applied = (new SQLiteVfsFileWriter($root))->publishWalHotJournalSavepointCheckpoint($prepared);
    $receipt = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next178Plan(
        $prepared,
        $applied,
        (string) file_get_contents($local),
        is_file($local . '-journal') ? (string) file_get_contents($local . '-journal') : null,
        (string) file_get_contents($local . '-wal')
    );
    echo json_encode([
        'status' => $receipt['status'],
        'can_publish_receipt' => $receipt['can_publish_receipt'],
        'matched_source_names' => $receipt['matched_source_names'],
        'blocked_reasons' => $receipt['blocked_reasons'],
        'receipt_digest' => $receipt['receipt_digest'],
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
} finally {
    foreach ([$local . '-journal', $local . '-wal', $local] as $file) {
        if (is_file($file)) {
            unlink($file);
        }
    }
    @rmdir(dirname($local));
    @rmdir(dirname(dirname($local)));
    @rmdir(dirname(dirname(dirname($local))));
    @rmdir($root);
}
