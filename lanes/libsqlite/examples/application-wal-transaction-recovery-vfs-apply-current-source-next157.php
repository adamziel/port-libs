<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/SQLiteWalHeader.php';
require_once __DIR__ . '/../src/SQLiteWalFrame.php';
require_once __DIR__ . '/../src/SQLiteWal.php';
require_once __DIR__ . '/../src/SQLiteVfsFileWriter.php';

use PortLibs\LibSqlite\SQLiteVfsFileWriter;
use PortLibs\LibSqlite\SQLiteWal;
use PortLibs\LibSqlite\SQLiteWalHeader;

$pageSize = 512;
$databasePath = 'wp-content/database/.ht.sqlite';
$page = static fn (string $label): string => str_pad($label, $pageSize, '.');
$databaseBytes = $page('wp schema before next157 transaction recovery')
    . $page('wp options before next157 transaction recovery')
    . $page('wp plugin before next157 transaction recovery');
$salt1 = 0x15715711;
$salt2 = 0x15715712;
$prefix = pack('N*', SQLiteWalHeader::MAGIC_BIG_ENDIAN, 3007000, $pageSize, 157, $salt1, $salt2);
$seed = SQLiteWal::checksumPair($prefix, false);
$walBytes = $prefix . pack('N*', $seed[0], $seed[1]);
$append = static function (string $bytes, array &$seed, int $pageNumber, int $commit, string $label) use ($page, $salt1, $salt2): string {
    $image = $page($label);
    $framePrefix = pack('N*', $pageNumber, $commit, $salt1, $salt2);
    $seed = SQLiteWal::checksumPair(substr($framePrefix, 0, 8) . $image, false, $seed[0], $seed[1]);

    return $bytes . $framePrefix . pack('N*', $seed[0], $seed[1]) . $image;
};

$walBytes = $append($walBytes, $seed, 1, 0, 'wp schema committed before next157 tail');
$walBytes = $append($walBytes, $seed, 2, 3, 'wp active_plugins committed before next157 tail');
$walBytes = $append($walBytes, $seed, 3, 0, 'wp plugin draft uncommitted next157 tail');

$root = sys_get_temp_dir() . '/port-libsqlite-application-wal-txn-next157-' . bin2hex(random_bytes(4));
$databaseLocal = $root . '/' . $databasePath;
$walLocal = $databaseLocal . '-wal';
if (!is_dir(dirname($databaseLocal)) && !mkdir(dirname($databaseLocal), 0777, true) && !is_dir(dirname($databaseLocal))) {
    throw new RuntimeException('Unable to create Application WAL transaction recovery example directory');
}
file_put_contents($databaseLocal, $databaseBytes);
file_put_contents($walLocal, $walBytes);

$applied = (new SQLiteVfsFileWriter($root))->applyWalTransactionRecoveryBoundary(
    $walBytes,
    $databaseBytes,
    $databasePath,
    $pageSize
);
$databaseAfter = (string) file_get_contents($databaseLocal);
$walAfter = (string) file_get_contents($walLocal);

$summary = [
    'applicationUse' => 'Recover a copied Application SQLite database after a crash leaves valid but uncommitted WAL frames: checkpoint only the committed wp_options transaction, truncate the WAL to the committed prefix, and sync both handles before the next importer opens the file.',
    'status' => $applied['status'],
    'recoveryReason' => $applied['recovery']['reason'],
    'committedFrames' => $applied['recovery']['committed_frame_count'],
    'discardedValidTailFrames' => $applied['recovery']['discarded_valid_tail_frame_count'],
    'databaseHasCommittedOption' => str_contains($databaseAfter, 'wp active_plugins committed'),
    'databaseHasUncommittedDraft' => str_contains($databaseAfter, 'wp plugin draft uncommitted'),
    'walBytesAfter' => strlen($walAfter),
    'durableSyncs' => $applied['durable_syncs'],
    'directorySyncs' => $applied['directory_syncs'],
    'dependencyClosure' => 'no new support component needed; reuses native PHP WAL transaction recovery boundaries and VFS atomic file-handle writes',
];

if (
    $summary['status'] !== 'applied'
    || $summary['recoveryReason'] !== 'uncommitted_valid_tail_after_last_commit'
    || $summary['committedFrames'] !== 2
    || $summary['discardedValidTailFrames'] !== 1
    || $summary['databaseHasCommittedOption'] !== true
    || $summary['databaseHasUncommittedDraft'] !== false
    || $summary['walBytesAfter'] !== 1104
) {
    throw new RuntimeException('Unexpected Application WAL transaction recovery VFS apply next157 summary');
}

echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;

@unlink($walLocal);
@unlink($databaseLocal);
@rmdir(dirname($databaseLocal));
@rmdir(dirname(dirname($databaseLocal)));
@rmdir($root);
