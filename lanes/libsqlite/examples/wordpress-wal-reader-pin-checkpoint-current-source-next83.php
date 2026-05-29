<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';
require_once __DIR__ . '/../src/SQLiteShmIndex.php';
require_once __DIR__ . '/../src/SQLiteWal.php';
require_once __DIR__ . '/../src/SQLiteWalFrame.php';
require_once __DIR__ . '/../src/SQLiteWalHeader.php';

use PortLibs\LibSqlite\SQLiteShmIndex;
use PortLibs\LibSqlite\SQLiteWal;
use PortLibs\LibSqlite\SQLiteWalHeader;

$pageSize = 512;
$salt1 = 0x83112233;
$salt2 = 0x83445566;
$page = static fn (string $label): string => str_pad($label, $pageSize, '.', STR_PAD_RIGHT);
$databaseBytes = $page('wp schema baseline')
    . $page('wp_options siteurl baseline')
    . $page('wp_options autoload baseline')
    . $page('wp plugin settings baseline')
    . $page('wp transient baseline');

$prefix = pack('N*', SQLiteWalHeader::MAGIC_BIG_ENDIAN, 3007000, $pageSize, 83, $salt1, $salt2);
$seed = SQLiteWal::checksumPair($prefix, false);
$walBytes = $prefix . pack('N*', $seed[0], $seed[1]);
foreach ([
    [2, 0, $page('wp_options siteurl before old reader')],
    [3, 3, $page('wp_options autoload first commit')],
    [2, 0, $page('wp_options siteurl after old reader')],
    [4, 0, $page('wp plugin settings draft')],
    [5, 0, $page('wp transient draft')],
    [4, 5, $page('wp plugin settings committed')],
    [2, 5, $page('wp_options siteurl final')],
] as [$pageNumber, $commitPageCount, $image]) {
    $framePrefix = pack('N*', $pageNumber, $commitPageCount, $salt1, $salt2);
    $seed = SQLiteWal::checksumPair(substr($framePrefix, 0, 8) . $image, false, $seed[0], $seed[1]);
    $walBytes .= $framePrefix . pack('N*', $seed[0], $seed[1]) . $image;
}

$makeShm = static function (array $readMarks, array $readLocks, int $backfill, int $attempted) use ($pageSize): string {
    $pageSizeField = (1 << 24) | $pageSize;
    $header = pack('V*', 3007000, $backfill, 183, $pageSizeField, 7, 5, 1, 2, 0x83112233, 0x83445566, 5, 6);
    $marks = array_map(static fn ($value): int => $value === null ? 0xffffffff : $value, array_pad(array_values($readMarks), SQLiteShmIndex::READER_COUNT, null));
    $locks = array_pad(array_map(static fn (bool $held): string => $held ? "\x01" : "\x00", array_values($readLocks)), 8, "\x00");
    $checkpoint = pack('V*', $backfill, $marks[0], $marks[1], $marks[2], $marks[3], $marks[4])
        . implode('', array_slice($locks, 0, 8))
        . pack('V*', $attempted, 0);

    return $header . $header . $checkpoint;
};

$wal = SQLiteWal::parse($walBytes, null, true);
$plan = $wal->checkpointReaderPinRestartCurrentSourceNext(
    $databaseBytes,
    SQLiteShmIndex::parse($makeShm([0, 2, null, null, null], [false, true, false, false, false], 1, 4)),
    SQLiteShmIndex::parse($makeShm([0, 2, 7, null, null], [false, true, true, false, false], 1, 6)),
    SQLiteShmIndex::parse($makeShm([0, null, 7, null, null], [false, false, true, false, false], 2, 7)),
    SQLiteShmIndex::parse($makeShm([0, null, null, null, null], [false, false, false, false, false], 7, 7)),
    [2, 3, 4, 5],
    'restart'
);

$summary = [
    'scenario' => 'wordpress-wal-reader-pin-checkpoint-current-source-next83',
    'wordpressUse' => 'During a copied wp_options WAL restart checkpoint, report whether the current reader still uses the preserved WAL, the next reader can source checkpointed pages from the database image, and all later readers use the reset database image without requiring ext/sqlite.',
    'status' => $plan['status'],
    'currentReaderSources' => $plan['current_source_names'],
    'nextReaderSourcesAfterCurrentRelease' => $plan['next_source_names_after_current_release'],
    'finalSourcesAfterAllRelease' => $plan['final_source_names_after_all_release'],
    'nextReaderBlocksReset' => $plan['next_reader_blocks_reset'],
    'finalResetReady' => $plan['final_reset_ready'],
    'finalUsesResetDatabaseOnly' => $plan['final_uses_reset_database_only'],
    'dependency' => in_array('wal-reader-pin-checkpoint-restart-current-source-next83', $plan['dependencies'], true),
];

if (($argv[1] ?? '') === '--self-test') {
    assert($summary['status'] === 'reader-pin-next-reader-blocks-restart-current-source-next83');
    assert($summary['currentReaderSources'] === ['preserved-wal', 'preserved-wal', 'missing', 'missing']);
    assert($summary['nextReaderSourcesAfterCurrentRelease'] === ['checkpoint-database', 'checkpoint-database', 'checkpoint-database', 'checkpoint-database']);
    assert($summary['finalSourcesAfterAllRelease'] === ['reset-database', 'reset-database', 'reset-database', 'reset-database']);
    assert($summary['nextReaderBlocksReset'] === true);
    assert($summary['finalResetReady'] === true);
    assert($summary['finalUsesResetDatabaseOnly'] === true);
    assert($summary['dependency'] === true);
}

echo json_encode($summary, JSON_PRETTY_PRINT) . PHP_EOL;
