<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteRollbackJournal;
use PortLibs\LibSqlite\SQLiteRollbackJournalHeader;
use PortLibs\LibSqlite\SQLiteWal;
use PortLibs\LibSqlite\SQLiteWalHeader;
use PortLibs\LibSqlite\SQLiteWalHotJournalReaderRestartCurrentSourceNextPlan;

$pageSize = 512;
$sectorSize = 512;
$databasePath = '/srv/www/wp-content/database/wp-options-next131.sqlite';
$page = static fn (string $label): string => str_pad($label, $pageSize, '.', STR_PAD_RIGHT);

$cleanPages = [
    1 => $page('wp next131 clean sqlite header'),
    2 => $page('wp next131 clean wp_options root'),
    3 => $page('wp next131 clean active_plugins option'),
    4 => $page('wp next131 clean autoload index'),
];
$dirtyDatabase = $page('wp next131 dirty sqlite header')
    . $page('wp next131 dirty wp_options root')
    . $page('wp next131 dirty active_plugins option')
    . $page('wp next131 dirty autoload index');

$nonce = 0x2026131;
$journalBytes = str_pad(
    SQLiteRollbackJournalHeader::MAGIC . pack('N*', count($cleanPages), $nonce, count($cleanPages), $sectorSize, $pageSize),
    $sectorSize,
    "\0"
);
foreach ($cleanPages as $pageNumber => $pageImage) {
    $journalBytes .= pack('N', $pageNumber) . $pageImage . pack('N', SQLiteRollbackJournal::pageChecksum($pageImage, $nonce));
}

$salt1 = 0x13113101;
$salt2 = 0x13113102;
$prefix = pack('N*', SQLiteWalHeader::MAGIC_BIG_ENDIAN, 3007000, $pageSize, 131, $salt1, $salt2);
$seed = SQLiteWal::checksumPair($prefix, false);
$walBytes = $prefix . pack('N*', $seed[0], $seed[1]);
foreach ([
    [1, 0, 'wp next131 wal schema retained draft'],
    [2, 4, 'wp next131 wal options retained commit'],
    [3, 0, 'wp next131 wal active_plugins reader draft'],
    [4, 4, 'wp next131 wal autoload reader commit'],
] as [$pageNumber, $commit, $label]) {
    $image = $page($label);
    $framePrefix = pack('N*', $pageNumber, $commit, $salt1, $salt2);
    $seed = SQLiteWal::checksumPair(substr($framePrefix, 0, 8) . $image, false, $seed[0], $seed[1]);
    $walBytes .= $framePrefix . pack('N*', $seed[0], $seed[1]) . $image;
}
$wal = SQLiteWal::parse($walBytes, $pageSize, true);

$plan = SQLiteWalHotJournalReaderRestartCurrentSourceNextPlan::hotJournalReaderRestartPlan(
    $databasePath,
    $dirtyDatabase,
    $journalBytes,
    $wal,
    $walBytes,
    [1, 2, 3, 4],
    4
);

$summary = [
    'scenario' => 'wordpress-wal-hot-journal-reader-restart-current-source-next131',
    'wordpressUse' => 'After a copied wp_options import leaves a hot rollback journal and a pinned WAL reader, native PHP tooling can restart the current reader from the preserved WAL bytes while keeping the released restart generation separate for later readers.',
    'status' => $plan['status'],
    'currentSourceReused' => $plan['current_source_reused_for_reader_restart'],
    'restartHeaderSeparated' => $plan['restart_header_separated_for_next_reader'],
    'readerRestartSources' => $plan['reader_restart_sources'],
    'nextGenerationSources' => $plan['next_generation_sources'],
    'operationReasons' => $plan['operation_reasons'],
    'dependencyClosure' => 'no new support component needed; this composes native rollback-journal hot recovery with WAL restart checkpoint current-source admission',
];

if (PHP_SAPI === 'cli' && basename(__FILE__) === basename($_SERVER['SCRIPT_FILENAME'] ?? '')) {
    assert($summary['status'] === 'wal-hot-journal-reader-restart-current-source-next131');
    assert($summary['currentSourceReused'] === true);
    assert($summary['restartHeaderSeparated'] === true);
    assert($summary['readerRestartSources'] === ['wal', 'wal', 'wal', 'wal']);
    assert($summary['nextGenerationSources'] === ['database', 'database', 'database', 'database']);
    echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
}

return $summary;
