<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteVfsFileWriter;
use PortLibs\LibSqlite\SQLiteWal;
use PortLibs\LibSqlite\SQLiteWalHeader;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$pageSize = 512;
$root = sys_get_temp_dir() . '/port-libsqlite-wordpress-current-wal-recovery-' . bin2hex(random_bytes(4));
$databasePath = 'wp-content/database/.ht.sqlite';
$databaseLocal = $root . '/' . $databasePath;
$walLocal = $databaseLocal . '-wal';
$directory = dirname($databaseLocal);
if (!is_dir($directory) && !mkdir($directory, 0777, true) && !is_dir($directory)) {
    throw new RuntimeException('Unable to create WordPress WAL recovery directory');
}

$page = static fn (string $label): string => str_pad($label, $pageSize, "\0");
$databaseBytes = $page('wp schema before current wal recovery')
    . $page('wp_options before current wal recovery')
    . $page('active_plugins before current wal recovery')
    . $page('transient before current wal recovery');
file_put_contents($databaseLocal, $databaseBytes);

$salt1 = 0x51790001;
$salt2 = 0x51790002;
$prefix = pack('N*', SQLiteWalHeader::MAGIC_BIG_ENDIAN, 3007000, $pageSize, 9, $salt1, $salt2);
$seed = SQLiteWal::checksumPair($prefix, false);
$walBytes = $prefix . pack('N*', $seed[0], $seed[1]);
$appendFrame = static function (string $bytes, array &$seed, int $pageNumber, int $commit, string $image) use ($salt1, $salt2): string {
    $framePrefix = pack('N*', $pageNumber, $commit, $salt1, $salt2);
    $seed = SQLiteWal::checksumPair(substr($framePrefix, 0, 8) . $image, false, $seed[0], $seed[1]);

    return $bytes . $framePrefix . pack('N*', $seed[0], $seed[1]) . $image;
};
$walBytes = $appendFrame($walBytes, $seed, 2, 0, $page('siteurl committed before import crash'));
$walBytes = $appendFrame($walBytes, $seed, 3, 4, $page('active_plugins committed before import crash'));
$walBytes = $appendFrame($walBytes, $seed, 4, 0, $page('transient draft lost after crash'));
file_put_contents($walLocal, $walBytes);

$result = (new SQLiteVfsFileWriter($root))->applyCurrentWalTransactionRecovery($databasePath, $pageSize);
$databaseAfter = (string) file_get_contents($databaseLocal);
$walAfter = (string) file_get_contents($walLocal);

$summary = [
    'scenario' => 'wordpress-pager-wal-recovery-current',
    'wordpressUse' => 'Recover the current copied WordPress SQLite database from its local -wal sidecar, preserving committed import pages and discarding an uncommitted crash tail before another request opens the database.',
    'status' => $result['status'],
    'atomic' => $result['atomic'],
    'sourceHadWal' => $result['current_source']['had_wal'],
    'sourceWalBytes' => $result['current_source']['wal_bytes'],
    'committedFrames' => $result['recovery']['committed_frame_count'] ?? 0,
    'discardedValidTailFrames' => $result['recovery']['discarded_valid_tail_frame_count'] ?? 0,
    'pagePrefixes' => [
        'page2' => rtrim(substr($databaseAfter, $pageSize, 64), "\0"),
        'page3' => rtrim(substr($databaseAfter, $pageSize * 2, 64), "\0"),
        'page4' => rtrim(substr($databaseAfter, $pageSize * 3, 64), "\0"),
    ],
    'walBytesAfter' => strlen($walAfter),
    'dependencies' => $result['dependencies'],
];

if (in_array('--self-test', $argv, true)) {
    assert($summary['status'] === 'applied');
    assert($summary['atomic'] === true);
    assert($summary['sourceHadWal'] === true);
    assert($summary['committedFrames'] === 2);
    assert($summary['discardedValidTailFrames'] === 1);
    assert($summary['pagePrefixes']['page2'] === 'siteurl committed before import crash');
    assert($summary['pagePrefixes']['page3'] === 'active_plugins committed before import crash');
    assert($summary['pagePrefixes']['page4'] === 'transient before current wal recovery');
    assert($summary['walBytesAfter'] === 1104);
    echo "wordpress-pager-wal-recovery-current self-test passed\n";
    exit(0);
}

echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
