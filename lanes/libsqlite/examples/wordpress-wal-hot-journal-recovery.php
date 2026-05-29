<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/SQLiteHeader.php';
require_once __DIR__ . '/../src/SQLiteRollbackJournalHeader.php';
require_once __DIR__ . '/../src/SQLiteRollbackJournalPage.php';
require_once __DIR__ . '/../src/SQLiteRollbackJournal.php';
require_once __DIR__ . '/../src/SQLiteWalHeader.php';
require_once __DIR__ . '/../src/SQLiteWalFrame.php';
require_once __DIR__ . '/../src/SQLiteWal.php';
require_once __DIR__ . '/../src/SQLiteVfsFileWriter.php';

use PortLibs\LibSqlite\SQLiteRollbackJournal;
use PortLibs\LibSqlite\SQLiteRollbackJournalHeader;
use PortLibs\LibSqlite\SQLiteVfsFileWriter;
use PortLibs\LibSqlite\SQLiteWal;
use PortLibs\LibSqlite\SQLiteWalHeader;

$pageSize = 512;
$databasePath = '/srv/www/wp-content/database/.ht.sqlite';
$page = static fn (string $label): string => str_pad($label, $pageSize, '.', STR_PAD_RIGHT);

$databaseBytes = $page('wp_options page one interrupted')
    . $page('wp_options siteurl interrupted')
    . $page('wp_options autoload interrupted')
    . $page('wp_options draft beyond rollback');

$nonce = 0x10203040;
$journalBytes = SQLiteRollbackJournalHeader::MAGIC . pack('N*', 3, $nonce, 3, 512, $pageSize);
$journalBytes = str_pad($journalBytes, 512, "\0");
foreach ([1 => $page('wp_options page one before hot journal'), 2 => $page('wp_options siteurl before hot journal'), 3 => $page('wp_options autoload before hot journal')] as $pageNumber => $image) {
    $journalBytes .= pack('N', $pageNumber) . $image . pack('N', SQLiteRollbackJournal::pageChecksum($image, $nonce));
}

$salt1 = 0x51525354;
$salt2 = 0x61626364;
$walHeaderPrefix = pack('N*', SQLiteWalHeader::MAGIC_BIG_ENDIAN, 3007000, $pageSize, 23, $salt1, $salt2);
$seed = SQLiteWal::checksumPair($walHeaderPrefix, false);
$walBytes = $walHeaderPrefix . pack('N*', $seed[0], $seed[1]);
$appendFrame = static function (string $bytes, array &$seed, int $pageNumber, int $commit, string $image) use ($salt1, $salt2): string {
    $framePrefix = pack('N*', $pageNumber, $commit, $salt1, $salt2);
    $seed = SQLiteWal::checksumPair(substr($framePrefix, 0, 8) . $image, false, $seed[0], $seed[1]);

    return $bytes . $framePrefix . pack('N*', $seed[0], $seed[1]) . $image;
};
$walBytes = $appendFrame($walBytes, $seed, 2, 0, $page('wp_options siteurl wal draft'));
$walBytes = $appendFrame($walBytes, $seed, 2, 3, $page('wp_options siteurl wal committed'));
$walBytes = $appendFrame($walBytes, $seed, 3, 3, $page('wp_options autoload wal committed'));
$walBytes = $appendFrame($walBytes, $seed, 4, 0, $page('wp_options plugin draft tail'));

$root = sys_get_temp_dir() . '/port-libsqlite-wp-wal-hot-journal-' . bin2hex(random_bytes(4));
$localDatabase = $root . $databasePath;
mkdir(dirname($localDatabase), 0777, true);
file_put_contents($localDatabase, $databaseBytes);
file_put_contents($localDatabase . '-journal', $journalBytes);
file_put_contents($localDatabase . '-wal', $walBytes . 'stale-tail');

$applied = (new SQLiteVfsFileWriter($root))->applyHotJournalThenWalRecovery(
    $databaseBytes,
    $journalBytes,
    $walBytes,
    $databasePath,
    false,
    false,
    null,
    $pageSize
);

echo json_encode([
    'status' => $applied['status'],
    'rollback_reason' => $applied['rollback_recovery']['reason'],
    'wal_reason' => $applied['wal_recovery']['reason'],
    'operations' => array_column($applied['operations'], 'reason'),
    'journal_exists' => is_file($localDatabase . '-journal'),
    'database_contains_wal_commit' => str_contains((string) file_get_contents($localDatabase), 'wp_options siteurl wal committed'),
    'database_contains_draft_tail' => str_contains((string) file_get_contents($localDatabase), 'wp_options plugin draft tail'),
    'wal_bytes' => filesize($localDatabase . '-wal'),
    'dependencies' => $applied['dependencies'],
], JSON_PRETTY_PRINT) . PHP_EOL;
