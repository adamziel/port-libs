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
$databasePath = '/srv/www/wp-content/database/wp-next181.sqlite';
$page = static fn (string $label): string => str_pad($label, $pageSize, '.', STR_PAD_RIGHT);
$databaseBytes = $page('wp next181 dirty options root') . $page('wp next181 dirty active_plugins');
$journalBytes = 'wp-next181-hot-journal';
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

$currentWalBytes = $makeWal([[1, 2, 'wp next181 current options commit']], 181, 0x18100201, 0x18100202);
$nextWalBytes = $makeWal([[2, 2, 'wp next181 retry active_plugins']], 182, 0x18200201, 0x18200202);
$currentWal = SQLiteWal::parse($currentWalBytes, $pageSize, true);
$nextWal = SQLiteWal::parse($nextWalBytes, $pageSize, true);
$bootstrap = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::planCheckpointSourceTransition(
    $databasePath,
    $databaseBytes,
    $pageSize,
    'wp-plugin-import-next181',
    [1 => $page('wp next181 hot rollback options')],
    [2 => $page('wp next181 savepoint before active_plugins')],
    $currentWal,
    $currentWalBytes,
    $nextWal,
    $nextWalBytes,
    [1 => ['image' => $page('wp next181 current options commit'), 'source_id' => 'bootstrap', 'epoch' => 1]],
    [1, 2],
    [['name' => 'bootstrap', 'source_id' => 'bootstrap', 'epoch' => 1]],
    null,
    null,
    null,
    'restart',
    1,
    181
);
$current = $bootstrap['current_source_token'];
$next = $bootstrap['next_source_token'];
$prepared = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::planCheckpointSourceTransition(
    $databasePath,
    $databaseBytes,
    $pageSize,
    'wp-plugin-import-next181',
    [1 => $page('wp next181 hot rollback options')],
    [2 => $page('wp next181 savepoint before active_plugins')],
    $currentWal,
    $currentWalBytes,
    $nextWal,
    $nextWalBytes,
    [
        1 => ['image' => $page('wp next181 current options commit'), 'source_id' => $current['id'], 'epoch' => $current['epoch']],
        2 => ['image' => $page('wp next181 savepoint before active_plugins'), 'source_id' => $current['id'], 'epoch' => $current['epoch']],
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
    181
);

$root = sys_get_temp_dir() . '/port-libs-wp-next181-' . bin2hex(random_bytes(3));
$local = $root . '/' . ltrim($databasePath, '/');
mkdir(dirname($local), 0777, true);
file_put_contents($local, $databaseBytes);
file_put_contents($local . '-journal', $journalBytes);
file_put_contents($local . '-wal', $currentWalBytes);

try {
    $applied = (new SQLiteVfsFileWriter($root))->publishWalHotJournalSavepointCheckpoint($prepared);
    $databaseAfter = (string) file_get_contents($local);
    $journalAfter = is_file($local . '-journal') ? (string) file_get_contents($local . '-journal') : null;
    $walAfter = (string) file_get_contents($local . '-wal');
    $receipt = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::verifyAtomicApplyReceipt($prepared, $applied, $databaseAfter, $journalAfter, $walAfter);
    $reopen = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next181Plan(
        $prepared,
        $receipt,
        $databaseAfter,
        $journalAfter,
        $walAfter,
        SQLiteWal::parse($walAfter, $pageSize, true)
    );
    echo json_encode([
        'status' => $reopen['status'],
        'can_reopen_publish' => $reopen['can_reopen_publish'],
        'matched_source_names' => $reopen['matched_source_names'],
        'wal_frame_count' => $reopen['wal_frame_count'],
        'wal_last_commit_page_count' => $reopen['wal_last_commit_page_count'],
        'blocked_reasons' => $reopen['blocked_reasons'],
        'reopen_digest' => $reopen['reopen_digest'],
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
