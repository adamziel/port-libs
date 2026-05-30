<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteRollbackJournal;
use PortLibs\LibSqlite\SQLiteRollbackJournalHeader;
use PortLibs\LibSqlite\SQLiteVfsFileWriter;
use PortLibs\LibSqlite\SQLiteWal;
use PortLibs\LibSqlite\SQLiteWalHeader;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$pageSize = 512;
$sectorSize = 512;
$databasePath = '/srv/www/wp-content/database/.ht.sqlite';
$page = static fn (string $label): string => str_pad($label, $pageSize, '.', STR_PAD_RIGHT);

$cleanHeader = $page('clean copied wp_options header');
$cleanOptions = $page('clean copied wp_options rows');
$cleanIndex = $page('clean copied autoload index');
$dirtyDatabase = $page('dirty copied wp_options header') . $page('dirty copied wp_options rows') . $page('dirty copied autoload index');

$nonce = 0x27182818;
$journalHeader = SQLiteRollbackJournalHeader::MAGIC . pack('N*', 3, $nonce, 3, $sectorSize, $pageSize);
$journalBytes = str_pad($journalHeader, $sectorSize, "\0")
    . pack('N', 1) . $cleanHeader . pack('N', SQLiteRollbackJournal::pageChecksum($cleanHeader, $nonce))
    . pack('N', 2) . $cleanOptions . pack('N', SQLiteRollbackJournal::pageChecksum($cleanOptions, $nonce))
    . pack('N', 3) . $cleanIndex . pack('N', SQLiteRollbackJournal::pageChecksum($cleanIndex, $nonce));
$journal = SQLiteRollbackJournal::parse($journalBytes, true);

$salt1 = 0x10203040;
$salt2 = 0x50607080;
$headerPrefix = pack('N*', SQLiteWalHeader::MAGIC_BIG_ENDIAN, 3007000, $pageSize, 23, $salt1, $salt2);
$seed = SQLiteWal::checksumPair($headerPrefix, false);
$walBytes = $headerPrefix . pack('N*', $seed[0], $seed[1]);
$appendFrame = static function (string $bytes, array &$seed, int $pageNumber, int $commitPageCount, string $image) use ($salt1, $salt2): string {
    $framePrefix = pack('N*', $pageNumber, $commitPageCount, $salt1, $salt2);
    $seed = SQLiteWal::checksumPair(substr($framePrefix, 0, 8) . $image, false, $seed[0], $seed[1]);

    return $bytes . $framePrefix . pack('N*', $seed[0], $seed[1]) . $image;
};
$walBytes = $appendFrame($walBytes, $seed, 2, 0, $page('draft copied siteurl update'));
$walBytes = $appendFrame($walBytes, $seed, 2, 3, $page('committed copied siteurl update'));
$walBytes = $appendFrame($walBytes, $seed, 3, 0, $page('uncommitted copied autoload index'));
$walBytes .= 'corrupt-tail-after-valid-draft';

$root = sys_get_temp_dir() . '/port-libsqlite-application-hot-wal-' . bin2hex(random_bytes(4));
$localDatabase = $root . $databasePath;
if (!is_dir(dirname($localDatabase)) && !mkdir(dirname($localDatabase), 0777, true) && !is_dir(dirname($localDatabase))) {
    throw new RuntimeException('Unable to create temporary SQLite fixture directory');
}
file_put_contents($localDatabase, $dirtyDatabase);
file_put_contents($localDatabase . '-journal', $journalBytes);
file_put_contents($localDatabase . '-wal', $walBytes . 'stale-sidecar-tail');

$applied = (new SQLiteVfsFileWriter($root))->applyHotJournalWalRecovery(
    $journal,
    $dirtyDatabase,
    $journalBytes,
    $walBytes,
    $databasePath,
    $pageSize
);

$databaseAfter = (string) file_get_contents($localDatabase);
$walAfter = (string) file_get_contents($localDatabase . '-wal');

$result = [
    'scenario' => 'application-hot-journal-wal-checkpoint-recovery',
    'applicationUse' => 'Recover a copied wp_options database that has both a hot rollback journal and a WAL sidecar: restore the hot-journal image first, checkpoint the committed WAL prefix against that recovered image, discard corrupt and uncommitted WAL tails, and delete the journal without requiring ext/sqlite.',
    'status' => $applied['status'],
    'atomic' => $applied['atomic'],
    'operationReasons' => array_column($applied['operations'], 'reason'),
    'recovery' => [
        'status' => $applied['recovery']['status'],
        'hotRecovered' => $applied['recovery']['hot_recovered'],
        'walStatus' => $applied['recovery']['wal_status'],
        'committedFrameCount' => $applied['recovery']['committed_frame_count'],
        'discardedValidTailFrames' => $applied['recovery']['discarded_valid_tail_frame_count'],
        'discardedCorruptTailFrames' => $applied['recovery']['discarded_corrupt_tail_frame_count'],
    ],
    'localFiles' => [
        'journalExists' => is_file($localDatabase . '-journal'),
        'databaseContainsCleanHeader' => str_contains($databaseAfter, 'clean copied wp_options header'),
        'databaseContainsCommittedSiteurl' => str_contains($databaseAfter, 'committed copied siteurl update'),
        'databaseContainsDirtyRows' => str_contains($databaseAfter, 'dirty copied wp_options rows'),
        'walContainsUncommittedTail' => str_contains($walAfter, 'uncommitted copied autoload index'),
        'walBytes' => strlen($walAfter),
    ],
    'dependencies' => $applied['dependencies'],
];

if (in_array('--self-test', $argv, true)) {
    assert($result['status'] === 'applied');
    assert($result['atomic'] === true);
    assert($result['recovery']['hotRecovered'] === true);
    assert($result['recovery']['committedFrameCount'] === 2);
    assert($result['localFiles']['journalExists'] === false);
    assert($result['localFiles']['databaseContainsCleanHeader'] === true);
    assert($result['localFiles']['databaseContainsCommittedSiteurl'] === true);
    assert($result['localFiles']['databaseContainsDirtyRows'] === false);
    assert($result['localFiles']['walContainsUncommittedTail'] === false);
}

echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
