<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteVfsFileWriter;
use PortLibs\LibSqlite\SQLiteWal;
use PortLibs\LibSqlite\SQLiteWalHeader;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$pageSize = 512;
$databasePath = '/srv/www/wp-content/database/.ht.sqlite';
$page = static fn (string $label): string => str_pad($label, $pageSize, '.', STR_PAD_RIGHT);

$databaseBytes = $page('copied wp_options header before transaction recovery')
    . $page('base siteurl option before transaction recovery')
    . $page('base active_plugins option before transaction recovery')
    . $page('base autoload index before transaction recovery');

$salt1 = 0x58005800;
$salt2 = 0x00580058;
$headerPrefix = pack('N*', SQLiteWalHeader::MAGIC_BIG_ENDIAN, 3007000, $pageSize, 58, $salt1, $salt2);
$seed = SQLiteWal::checksumPair($headerPrefix, false);
$walBytes = $headerPrefix . pack('N*', $seed[0], $seed[1]);
$appendFrame = static function (string $bytes, array &$seed, int $pageNumber, int $commitPageCount, string $image) use ($salt1, $salt2): string {
    $framePrefix = pack('N*', $pageNumber, $commitPageCount, $salt1, $salt2);
    $seed = SQLiteWal::checksumPair(substr($framePrefix, 0, 8) . $image, false, $seed[0], $seed[1]);

    return $bytes . $framePrefix . pack('N*', $seed[0], $seed[1]) . $image;
};

$walBytes = $appendFrame($walBytes, $seed, 2, 0, $page('draft copied siteurl option'));
$walBytes = $appendFrame($walBytes, $seed, 2, 4, $page('committed copied siteurl option'));
$walBytes = $appendFrame($walBytes, $seed, 3, 0, $page('uncommitted copied active_plugins'));
$walBytes = $appendFrame($walBytes, $seed, 4, 0, $page('uncommitted copied autoload index'));

$root = sys_get_temp_dir() . '/port-libsqlite-application-wal-tx-recovery-' . bin2hex(random_bytes(4));
$localDatabase = $root . $databasePath;
if (!is_dir(dirname($localDatabase)) && !mkdir(dirname($localDatabase), 0777, true) && !is_dir(dirname($localDatabase))) {
    throw new RuntimeException('Unable to create temporary SQLite fixture directory');
}
file_put_contents($localDatabase, $databaseBytes);
file_put_contents($localDatabase . '-wal', $walBytes . 'stale-sidecar-tail');

$applied = (new SQLiteVfsFileWriter($root))->applyWalTransactionRecoveryBoundary(
    $walBytes,
    $databaseBytes,
    $databasePath,
    $pageSize
);

$databaseAfter = (string) file_get_contents($localDatabase);
$walAfter = (string) file_get_contents($localDatabase . '-wal');

$result = [
    'scenario' => 'application-wal-transaction-recovery-apply-current-next58',
    'applicationUse' => 'Recover a copied wp_options WAL by checkpointing the last committed transaction prefix and truncating later draft frames before the next Application import reader opens the database.',
    'status' => $applied['status'],
    'atomic' => $applied['atomic'],
    'operationReasons' => array_column($applied['operations'], 'reason'),
    'recovery' => [
        'status' => $applied['recovery']['status'],
        'reason' => $applied['recovery']['reason'],
        'committedFrameCount' => $applied['recovery']['committed_frame_count'],
        'discardedValidTailFrames' => $applied['recovery']['discarded_valid_tail_frame_count'],
        'discardedCorruptTailFrames' => $applied['recovery']['discarded_corrupt_tail_frame_count'],
    ],
    'localFiles' => [
        'databaseContainsCommittedSiteurl' => str_contains($databaseAfter, 'committed copied siteurl option'),
        'databaseContainsUncommittedPlugins' => str_contains($databaseAfter, 'uncommitted copied active_plugins'),
        'walContainsUncommittedPlugins' => str_contains($walAfter, 'uncommitted copied active_plugins'),
        'walContainsStaleTail' => str_contains($walAfter, 'stale-sidecar-tail'),
        'walBytes' => strlen($walAfter),
    ],
    'dependencies' => $applied['dependencies'],
];

if (in_array('--self-test', $argv, true)) {
    assert($result['status'] === 'applied');
    assert($result['atomic'] === true);
    assert($result['recovery']['committedFrameCount'] === 2);
    assert($result['recovery']['discardedValidTailFrames'] === 2);
    assert($result['localFiles']['databaseContainsCommittedSiteurl'] === true);
    assert($result['localFiles']['databaseContainsUncommittedPlugins'] === false);
    assert($result['localFiles']['walContainsUncommittedPlugins'] === false);
    assert($result['localFiles']['walContainsStaleTail'] === false);
    assert($result['localFiles']['walBytes'] === 1104);
}

echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
