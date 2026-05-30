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
require_once __DIR__ . '/../src/SQLiteVfsFileWriter.php';

use PortLibs\LibSqlite\SQLiteRollbackJournal;
use PortLibs\LibSqlite\SQLiteRollbackJournalHeader;
use PortLibs\LibSqlite\SQLiteVfsFileWriter;
use PortLibs\LibSqlite\SQLiteWal;
use PortLibs\LibSqlite\SQLiteWalHeader;

$pageSize = 512;
$sectorSize = 512;
$root = sys_get_temp_dir() . '/sqlite-wordpress-super-master-74-' . bin2hex(random_bytes(4));
$mainPath = '/wp-content/database/main.sqlite';
$sitePath = '/wp-content/database/site.sqlite';
$superPath = '/wp-content/database/main.sqlite-mj74';
$page = static fn (string $label): string => str_pad($label, $pageSize, '.', STR_PAD_RIGHT);
$local = static fn (string $path): string => rtrim($root, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . ltrim($path, '/');
$removeTree = static function (string $path) use (&$removeTree): void {
    if (!file_exists($path)) {
        return;
    }
    if (is_file($path) || is_link($path)) {
        unlink($path);
        return;
    }
    foreach (scandir($path) ?: [] as $entry) {
        if ($entry === '.' || $entry === '..') {
            continue;
        }
        $removeTree($path . DIRECTORY_SEPARATOR . $entry);
    }
    rmdir($path);
};
$makeJournal = static function (array $pages, int $nonce) use ($pageSize, $sectorSize): string {
    $header = SQLiteRollbackJournalHeader::MAGIC . pack('N*', count($pages), $nonce, count($pages), $sectorSize, $pageSize);
    $bytes = str_pad($header, $sectorSize, "\0");
    foreach ($pages as $pageNumber => $image) {
        $bytes .= pack('N', $pageNumber) . $image . pack('N', SQLiteRollbackJournal::pageChecksum($image, $nonce));
    }

    return $bytes;
};
$makeWal = static function (int $salt, string $committedOption) use ($pageSize): string {
    $salt1 = 0x74000000 + $salt;
    $salt2 = 0x74100000 + $salt;
    $prefix = pack('N*', SQLiteWalHeader::MAGIC_BIG_ENDIAN, 3007000, $pageSize, $salt, $salt1, $salt2);
    $seed = SQLiteWal::checksumPair($prefix, false);
    $bytes = $prefix . pack('N*', $seed[0], $seed[1]);
    $image = str_pad($committedOption, $pageSize, '.', STR_PAD_RIGHT);
    $framePrefix = pack('N*', 2, 2, $salt1, $salt2);
    $seed = SQLiteWal::checksumPair(substr($framePrefix, 0, 8) . $image, false, $seed[0], $seed[1]);
    $bytes .= $framePrefix . pack('N*', $seed[0], $seed[1]) . $image;
    $tail = str_pad('uncommitted schema tail discarded during recovery', $pageSize, '.', STR_PAD_RIGHT);
    $tailPrefix = pack('N*', 1, 0, $salt1, $salt2);
    $seed = SQLiteWal::checksumPair(substr($tailPrefix, 0, 8) . $tail, false, $seed[0], $seed[1]);

    return $bytes . $tailPrefix . pack('N*', $seed[0], $seed[1]) . $tail;
};

$removeTree($root);
mkdir($local('/wp-content/database'), 0777, true);

$mainJournalBytes = $makeJournal([
    1 => $page('main clean schema before master-super recovery'),
    2 => $page('main clean active_plugins before recovery'),
], 0x74020001);
$siteJournalBytes = $makeJournal([
    1 => $page('site clean schema before master-super recovery'),
    2 => $page('site clean wp_2_options before recovery'),
], 0x74020002);
$mainWalBytes = $makeWal(84, 'main wal committed active_plugins after recovery');
$siteWalBytes = $makeWal(85, 'site wal committed active_plugins after recovery');
$superBytes = $mainPath . "-journal\n" . $sitePath . "-journal\n";

file_put_contents($local($mainPath), $page('main dirty schema after crash') . $page('main dirty active_plugins after crash'));
file_put_contents($local($mainPath . '-journal'), $mainJournalBytes);
file_put_contents($local($mainPath . '-wal'), $mainWalBytes);
file_put_contents($local($sitePath), $page('site dirty schema after crash') . $page('site dirty wp_2_options after crash'));
file_put_contents($local($sitePath . '-journal'), $siteJournalBytes);
file_put_contents($local($sitePath . '-wal'), $siteWalBytes);
file_put_contents($local($superPath), $superBytes);

$writer = new SQLiteVfsFileWriter($root);
$applied = $writer->applyMasterSuperJournalHotRecovery($superPath, $superBytes, [
    [
        'database_path' => $mainPath,
        'database_bytes' => $page('main dirty schema after crash') . $page('main dirty active_plugins after crash'),
        'journal' => SQLiteRollbackJournal::parse($mainJournalBytes, true),
        'journal_bytes' => $mainJournalBytes,
        'wal_bytes' => $mainWalBytes,
        'page_numbers' => [1, 2],
        'database_page_size' => $pageSize,
    ],
    [
        'database_path' => $sitePath,
        'database_bytes' => $page('site dirty schema after crash') . $page('site dirty wp_2_options after crash'),
        'journal' => SQLiteRollbackJournal::parse($siteJournalBytes, true),
        'journal_bytes' => $siteJournalBytes,
        'wal_bytes' => $siteWalBytes,
        'page_numbers' => [1, 2],
        'database_page_size' => $pageSize,
    ],
]);

$summary = [
    'status' => $applied['status'],
    'recoveredDatabases' => $applied['recovery']['recovered_database_count'],
    'superJournalDeleted' => !is_file($local($superPath)),
    'mainJournalDeleted' => !is_file($local($mainPath . '-journal')),
    'siteJournalDeleted' => !is_file($local($sitePath . '-journal')),
    'mainRecoveredActivePlugins' => str_contains((string) file_get_contents($local($mainPath)), 'main wal committed active_plugins'),
    'siteRecoveredActivePlugins' => str_contains((string) file_get_contents($local($sitePath)), 'site wal committed active_plugins'),
    'operationsApplied' => $applied['applied'],
    'durableSyncs' => $applied['durable_syncs'],
];

if (in_array('--self-test', $argv, true)) {
    foreach ([
        $summary['status'] === 'applied',
        $summary['recoveredDatabases'] === 2,
        $summary['superJournalDeleted'] === true,
        $summary['mainRecoveredActivePlugins'] === true,
        $summary['siteRecoveredActivePlugins'] === true,
    ] as $passed) {
        if (!$passed) {
            $removeTree($root);
            throw new RuntimeException('WordPress pager hot-journal super/master recovery current smoke failed');
        }
    }
}

echo json_encode($summary, JSON_PRETTY_PRINT) . PHP_EOL;
$removeTree($root);
