<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/SQLiteHeader.php';
require_once __DIR__ . '/../src/SQLiteWalHeader.php';
require_once __DIR__ . '/../src/SQLiteWalFrame.php';
require_once __DIR__ . '/../src/SQLiteWal.php';
require_once __DIR__ . '/../src/SQLiteWalFileWritePlan.php';
require_once __DIR__ . '/../src/SQLiteWalRecoveryPlan.php';
require_once __DIR__ . '/../src/SQLiteVfsFileWriter.php';

use PortLibs\LibSqlite\SQLiteVfsFileWriter;
use PortLibs\LibSqlite\SQLiteWal;
use PortLibs\LibSqlite\SQLiteWalHeader;

$pageSize = 512;
$page = static fn (string $label): string => str_pad($label, $pageSize, '.', STR_PAD_RIGHT);
$databasePath = '/wp-content/database/.ht.sqlite';
$databaseBytes = $page('wp database header before wal') . $page('base wp_options siteurl') . $page('base wp_options autoload');

$salt1 = 0x13572468;
$salt2 = 0x24681357;
$headerPrefix = pack('N*', SQLiteWalHeader::MAGIC_BIG_ENDIAN, 3007000, $pageSize, 17, $salt1, $salt2);
$seed = SQLiteWal::checksumPair($headerPrefix, false);
$walBytes = $headerPrefix . pack('N*', $seed[0], $seed[1]);
$appendFrame = static function (string $bytes, array &$seed, int $pageNumber, int $commitPageCount, string $image) use ($salt1, $salt2): string {
    $framePrefix = pack('N*', $pageNumber, $commitPageCount, $salt1, $salt2);
    $seed = SQLiteWal::checksumPair(substr($framePrefix, 0, 8) . $image, false, $seed[0], $seed[1]);

    return $bytes . $framePrefix . pack('N*', $seed[0], $seed[1]) . $image;
};

$walBytes = $appendFrame($walBytes, $seed, 2, 0, $page('draft wp_options siteurl'));
$walBytes = $appendFrame($walBytes, $seed, 2, 3, $page('committed wp_options siteurl'));
$walBytes = $appendFrame($walBytes, $seed, 3, 0, $page('valid but uncommitted index draft'));
$corruptWalBytes = $walBytes . 'truncated-corrupt-tail';

$root = sys_get_temp_dir() . '/port-libsqlite-application-wal-boundary-' . bin2hex(random_bytes(4));
$localDatabase = $root . $databasePath;
if (!is_dir(dirname($localDatabase)) && !mkdir(dirname($localDatabase), 0777, true) && !is_dir(dirname($localDatabase))) {
    throw new RuntimeException('Unable to create temporary SQLite fixture directory');
}
file_put_contents($localDatabase, $databaseBytes);
file_put_contents($localDatabase . '-wal', $corruptWalBytes . 'stale-local-tail');

$applied = (new SQLiteVfsFileWriter($root))->applyWalChecksumRecoveryBoundary(
    $corruptWalBytes,
    $databaseBytes,
    $databasePath,
    $pageSize
);

$checkpointedDatabase = (string) file_get_contents($localDatabase);
$recoveredWalBytes = (string) file_get_contents($localDatabase . '-wal');

$result = [
    'applicationUse' => 'Apply a checksum-bounded WAL recovery prefix through native PHP VFS handles for copied wp_options databases, checkpointing the last valid committed frame while preserving valid uncommitted WAL frames and discarding corrupt sidecar tails without ext/sqlite.',
    'status' => $applied['status'],
    'atomic' => $applied['atomic'],
    'operations' => array_column($applied['operations'], 'reason'),
    'recovery' => [
        'status' => $applied['recovery']['status'],
        'reason' => $applied['recovery']['reason'],
        'validFrameCount' => $applied['recovery']['valid_frame_count'],
        'firstInvalidFrame' => $applied['recovery']['first_invalid_frame'],
        'lastCommitFrame' => $applied['recovery']['last_commit_frame'],
        'uncommittedFrameCount' => $applied['recovery']['uncommitted_frame_count'],
        'canCheckpoint' => $applied['recovery']['can_checkpoint'],
    ],
    'localFiles' => [
        'databaseContainsCommittedSiteurl' => str_contains($checkpointedDatabase, 'committed wp_options siteurl'),
        'databaseContainsUncommittedDraft' => str_contains($checkpointedDatabase, 'valid but uncommitted index draft'),
        'walContainsUncommittedDraft' => str_contains($recoveredWalBytes, 'valid but uncommitted index draft'),
        'walContainsCorruptTail' => str_contains($recoveredWalBytes, 'truncated-corrupt-tail'),
        'walBytes' => strlen($recoveredWalBytes),
    ],
    'dependencies' => $applied['dependencies'],
];

if (in_array('--self-test', $argv, true)) {
    assert($result['status'] === 'applied');
    assert($result['atomic'] === true);
    assert($result['recovery']['status'] === 'recovered_prefix');
    assert($result['recovery']['canCheckpoint'] === true);
    assert($result['localFiles']['databaseContainsCommittedSiteurl'] === true);
    assert($result['localFiles']['databaseContainsUncommittedDraft'] === false);
    assert($result['localFiles']['walContainsUncommittedDraft'] === true);
    assert($result['localFiles']['walContainsCorruptTail'] === false);
}

echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
