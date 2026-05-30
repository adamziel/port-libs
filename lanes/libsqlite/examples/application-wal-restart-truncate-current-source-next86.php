<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/SQLiteWalHeader.php';
require_once __DIR__ . '/../src/SQLiteWalFrame.php';
require_once __DIR__ . '/../src/SQLiteWal.php';

use PortLibs\LibSqlite\SQLiteWal;
use PortLibs\LibSqlite\SQLiteWalHeader;

$pageSize = 512;
$page = static fn (string $label): string => str_pad($label, $pageSize, "\0");
$databaseBytes = $page('wp86 schema base')
    . $page('wp86 active_plugins base')
    . $page('wp86 autoload index base')
    . $page('wp86 cron base');

$salt1 = 0x86000011;
$salt2 = 0x86000022;
$prefix = pack('N*', SQLiteWalHeader::MAGIC_BIG_ENDIAN, 3007000, $pageSize, 86, $salt1, $salt2);
$seed = SQLiteWal::checksumPair($prefix, false);
$walBytes = $prefix . pack('N*', $seed[0], $seed[1]);
$append = static function (int $pageNumber, int $commit, string $image) use (&$walBytes, &$seed, $salt1, $salt2): void {
    $framePrefix = pack('N*', $pageNumber, $commit, $salt1, $salt2);
    $seed = SQLiteWal::checksumPair(substr($framePrefix, 0, 8) . $image, false, $seed[0], $seed[1]);
    $walBytes .= $framePrefix . pack('N*', $seed[0], $seed[1]) . $image;
};

$append(2, 0, $page('wp86 active_plugins draft'));
$append(3, 4, $page('wp86 autoload index commit'));
$append(2, 0, $page('wp86 active_plugins latest'));
$append(4, 4, $page('wp86 cron commit'));

$wal = SQLiteWal::parse($walBytes, null, true);
$restart = $wal->restartTruncateReaderCurrentSourceNext($walBytes, $databaseBytes, [2, 3, 4], 'restart', 2);
$truncate = $wal->restartTruncateReaderCurrentSourceNext($walBytes, $databaseBytes, [2, 3, 4], 'truncate');

$summary = [
    'database' => 'wp-content/database/.ht.sqlite',
    'behavior' => 'wal_restart_truncate_reader_current_source_next86',
    'restart' => [
        'source_status' => $restart['source_status'],
        'current_reader_end_frame' => $restart['current_reader_end_frame'],
        'current_sources' => $restart['current_sources'],
        'next_sources' => $restart['next_sources'],
        'wal_action' => $restart['wal_action'],
        'next_uses_restarted_header' => $restart['next_uses_restarted_header'],
        'images_match' => $restart['images_match'],
    ],
    'truncate' => [
        'source_status' => $truncate['source_status'],
        'wal_action' => $truncate['wal_action'],
        'wal_bytes_length' => $truncate['wal_bytes_length'],
        'next_sources' => $truncate['next_sources'],
        'images_match' => $truncate['images_match'],
    ],
    'dependencies' => $restart['dependencies'],
];

if (($argv[1] ?? '') === '--self-test') {
    assert($summary['restart']['source_status'] === 'current-source');
    assert($summary['restart']['wal_action'] === 'restart_wal');
    assert($summary['restart']['next_uses_restarted_header'] === true);
    assert($summary['restart']['images_match'] === false);
    assert($summary['truncate']['wal_action'] === 'truncate_wal');
    assert($summary['truncate']['wal_bytes_length'] === 0);
    assert($summary['truncate']['images_match'] === true);
}

echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
