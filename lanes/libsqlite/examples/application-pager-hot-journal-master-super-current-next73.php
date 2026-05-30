<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/SQLiteHeader.php';
require_once __DIR__ . '/../src/SQLiteRollbackJournalHeader.php';
require_once __DIR__ . '/../src/SQLiteRollbackJournalPage.php';
require_once __DIR__ . '/../src/SQLiteRollbackJournal.php';
require_once __DIR__ . '/../src/SQLiteWalHeader.php';
require_once __DIR__ . '/../src/SQLiteWalFrame.php';
require_once __DIR__ . '/../src/SQLiteWal.php';
require_once __DIR__ . '/../src/SQLitePagerHotJournalWalRecoveryPlan.php';

use PortLibs\LibSqlite\SQLitePagerHotJournalWalRecoveryPlan;
use PortLibs\LibSqlite\SQLiteRollbackJournal;
use PortLibs\LibSqlite\SQLiteRollbackJournalHeader;
use PortLibs\LibSqlite\SQLiteWal;
use PortLibs\LibSqlite\SQLiteWalHeader;

$pageSize = 512;
$sectorSize = 512;
$page = static fn (string $label): string => str_pad($label, $pageSize, '.', STR_PAD_RIGHT);
$makeJournal = static function (array $pages, int $nonce) use ($pageSize, $sectorSize): string {
    $header = SQLiteRollbackJournalHeader::MAGIC . pack('N*', count($pages), $nonce, count($pages), $sectorSize, $pageSize);
    $bytes = str_pad($header, $sectorSize, "\0");
    foreach ($pages as $pageNumber => $image) {
        $bytes .= pack('N', $pageNumber) . $image . pack('N', SQLiteRollbackJournal::pageChecksum($image, $nonce));
    }

    return $bytes;
};
$makeWal = static function (int $salt, string $committedOption) use ($pageSize): string {
    $salt1 = 0x73000000 + $salt;
    $salt2 = 0x73100000 + $salt;
    $prefix = pack('N*', SQLiteWalHeader::MAGIC_BIG_ENDIAN, 3007000, $pageSize, $salt, $salt1, $salt2);
    $seed = SQLiteWal::checksumPair($prefix, false);
    $headerChecksum = $seed;
    $framePrefix = pack('N*', 2, 2, $salt1, $salt2);
    $image = str_pad($committedOption, $pageSize, '.', STR_PAD_RIGHT);
    $seed = SQLiteWal::checksumPair(substr($framePrefix, 0, 8) . $image, false, $seed[0], $seed[1]);

    return $prefix . pack('N*', $headerChecksum[0], $headerChecksum[1]) . $framePrefix . pack('N*', $seed[0], $seed[1]) . $image;
};

$mainPath = '/wp-content/database/main.sqlite';
$sitePath = '/wp-content/database/site.sqlite';
$mainJournalBytes = $makeJournal([
    1 => $page('main clean schema before master-journal crash'),
    2 => $page('main clean wp_options before master-journal crash'),
], 0x73020001);
$siteJournalBytes = $makeJournal([
    1 => $page('site clean schema before master-journal crash'),
    2 => $page('site clean wp_2_options before master-journal crash'),
], 0x73020002);

$plan = SQLitePagerHotJournalWalRecoveryPlan::masterSuperJournalCurrentNext(
    '/wp-content/database/main.sqlite-mj73',
    $mainPath . "-journal\n" . $sitePath . "-journal\n",
    [
        [
            'database_path' => $mainPath,
            'database_bytes' => $page('main dirty schema after crash') . $page('main dirty wp_options after crash'),
            'journal' => SQLiteRollbackJournal::parse($mainJournalBytes, true),
            'journal_bytes' => $mainJournalBytes,
            'wal_bytes' => $makeWal(81, 'main wal committed active_plugins after recovery'),
            'page_numbers' => [1, 2],
            'database_page_size' => $pageSize,
        ],
        [
            'database_path' => $sitePath,
            'database_bytes' => $page('site dirty schema after crash') . $page('site dirty wp_2_options after crash'),
            'journal' => SQLiteRollbackJournal::parse($siteJournalBytes, true),
            'journal_bytes' => $siteJournalBytes,
            'wal_bytes' => $makeWal(82, 'site wal committed active_plugins after recovery'),
            'page_numbers' => [1, 2],
            'database_page_size' => $pageSize,
        ],
    ],
);

if (in_array('--self-test', $argv, true)) {
    foreach ([
        $plan['status'] === 'super_journal_hot_recovery_current_next',
        $plan['recovered_database_count'] === 2,
        $plan['super_journal_action'] === 'delete_super_journal_after_named_hot_journals',
        str_contains($plan['databases'][$mainPath]['next_reader'][0]['image'], 'main clean schema'),
        str_contains($plan['databases'][$sitePath]['next_reader'][1]['image'], 'site wal committed active_plugins'),
    ] as $passed) {
        if (!$passed) {
            throw new RuntimeException('Application pager hot-journal master/super current-next smoke failed');
        }
    }
}

echo json_encode([
    'status' => $plan['status'],
    'recoveredDatabases' => $plan['recovered_database_count'],
    'superJournalAction' => $plan['super_journal_action'],
    'mainNextSources' => $plan['next_reader_sources'][$mainPath],
    'siteNextSources' => $plan['next_reader_sources'][$sitePath],
], JSON_PRETTY_PRINT) . PHP_EOL;
