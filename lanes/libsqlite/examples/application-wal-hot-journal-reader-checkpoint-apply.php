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
$databasePath = '/srv/www/wp-content/database/wp-hot-journal-reader-checkpoint-apply.sqlite';
$root = sys_get_temp_dir() . '/port-libsqlite-application-wal-hot-reader-hot-journal-reader-checkpoint-apply-' . bin2hex(random_bytes(4));
$localDatabase = $root . $databasePath;
$page = static fn (string $label): string => str_pad($label, $pageSize, '.', STR_PAD_RIGHT);

if (!is_dir(dirname($localDatabase)) && !mkdir(dirname($localDatabase), 0777, true) && !is_dir(dirname($localDatabase))) {
    throw new RuntimeException('Unable to create Application WAL hot-reader fixture directory');
}

$cleanPages = [
    1 => $page('wp hot-journal-reader-checkpoint-apply clean schema before crashed import'),
    2 => $page('wp hot-journal-reader-checkpoint-apply clean wp_options before crashed import'),
    3 => $page('wp hot-journal-reader-checkpoint-apply clean plugin option before crashed import'),
    4 => $page('wp hot-journal-reader-checkpoint-apply clean autoload index before crashed import'),
];
$dirtyDatabase = $page('wp hot-journal-reader-checkpoint-apply dirty schema from crashed import')
    . $page('wp hot-journal-reader-checkpoint-apply dirty wp_options from crashed import')
    . $page('wp hot-journal-reader-checkpoint-apply dirty plugin option from crashed import')
    . $page('wp hot-journal-reader-checkpoint-apply dirty autoload index from crashed import');
$nonce = 0x12551250;
$journalHeader = SQLiteRollbackJournalHeader::MAGIC . pack('N*', count($cleanPages), $nonce, count($cleanPages), $sectorSize, $pageSize);
$journalBytes = str_pad($journalHeader, $sectorSize, "\0");
foreach ($cleanPages as $pageNumber => $image) {
    $journalBytes .= pack('N', $pageNumber) . $image . pack('N', SQLiteRollbackJournal::pageChecksum($image, $nonce));
}

$salt1 = 0x12551251;
$salt2 = 0x12551252;
$walPrefix = pack('N*', SQLiteWalHeader::MAGIC_BIG_ENDIAN, 3007000, $pageSize, 125, $salt1, $salt2);
$seed = SQLiteWal::checksumPair($walPrefix, false);
$walBytes = $walPrefix . pack('N*', $seed[0], $seed[1]);
$appendFrame = static function (string $bytes, array &$seed, int $pageNumber, int $commit, string $label) use ($page, $salt1, $salt2): string {
    $image = $page($label);
    $framePrefix = pack('N*', $pageNumber, $commit, $salt1, $salt2);
    $seed = SQLiteWal::checksumPair(substr($framePrefix, 0, 8) . $image, false, $seed[0], $seed[1]);

    return $bytes . $framePrefix . pack('N*', $seed[0], $seed[1]) . $image;
};
$walBytes = $appendFrame($walBytes, $seed, 2, 0, 'wp hot-journal-reader-checkpoint-apply wal draft siteurl after recovery');
$walBytes = $appendFrame($walBytes, $seed, 2, 4, 'wp hot-journal-reader-checkpoint-apply wal committed siteurl after recovery');
$walBytes = $appendFrame($walBytes, $seed, 3, 0, 'wp hot-journal-reader-checkpoint-apply wal draft plugin tail held by reader');
$walBytes = $appendFrame($walBytes, $seed, 4, 4, 'wp hot-journal-reader-checkpoint-apply wal committed autoload tail held by reader');

file_put_contents($localDatabase, $dirtyDatabase);
file_put_contents($localDatabase . '-journal', $journalBytes);
file_put_contents($localDatabase . '-wal', $walBytes);

$applied = (new SQLiteVfsFileWriter($root))->applyWalCheckpointHotJournalReader(
    $databasePath,
    [1, 2, 3, 4],
    'restart',
    2
);
$databaseAfter = (string) file_get_contents($localDatabase);
$walAfter = (string) file_get_contents($localDatabase . '-wal');

$summary = [
    'scenario' => 'application-wal-hot-journal-reader-checkpoint-apply-hot-journal-reader-checkpoint-apply',
    'applicationUse' => 'Apply a copied Application SQLite database after an interrupted import: recover the hot rollback journal, checkpoint the reader-visible WAL prefix, and preserve the WAL tail until active readers release.',
    'root' => $root,
    'status' => $applied['status'],
    'atomic' => $applied['atomic'],
    'appliedOperations' => $applied['applied'],
    'journalExistsAfter' => is_file($localDatabase . '-journal'),
    'pinnedCheckpointBusy' => $applied['checkpoint']['pinned_checkpoint_busy'],
    'releasedCheckpointBusy' => $applied['checkpoint']['released_checkpoint_busy'],
    'readerReleaseUnblocksCheckpoint' => $applied['checkpoint']['reader_release_unblocked_checkpoint'],
    'operationReasons' => array_column($applied['operations'], 'reason'),
    'databasePrefixes' => [
        'page1' => rtrim(substr($databaseAfter, 0, 72), ".\0"),
        'page2' => rtrim(substr($databaseAfter, $pageSize, 72), ".\0"),
        'page3' => rtrim(substr($databaseAfter, $pageSize * 2, 72), ".\0"),
    ],
    'walBytesAfter' => strlen($walAfter),
    'walTailPreserved' => str_contains($walAfter, 'wp hot-journal-reader-checkpoint-apply wal committed autoload tail held by reader'),
    'dependencies' => $applied['dependencies'],
];

if (PHP_SAPI === 'cli' && in_array('--self-test', $argv, true)) {
    assert($summary['status'] === 'applied');
    assert($summary['journalExistsAfter'] === false);
    assert($summary['pinnedCheckpointBusy'] === true);
    assert($summary['readerReleaseUnblocksCheckpoint'] === true);
    assert($summary['walTailPreserved'] === true);
    echo "application-wal-hot-journal-reader-checkpoint-apply-hot-journal-reader-checkpoint-apply self-test passed\n";
    return;
}

echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
