<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/SQLiteRollbackJournalHeader.php';
require_once __DIR__ . '/../src/SQLiteRollbackJournalPage.php';
require_once __DIR__ . '/../src/SQLiteRollbackJournal.php';
require_once __DIR__ . '/../src/SQLitePagerMasterJournalHotRollbackCurrentSourceNextPlan.php';
require_once __DIR__ . '/../src/SQLiteVfsFileWriter.php';

use PortLibs\LibSqlite\SQLiteRollbackJournal;
use PortLibs\LibSqlite\SQLiteRollbackJournalHeader;
use PortLibs\LibSqlite\SQLiteVfsFileWriter;

$pageSize = 512;
$sectorSize = 512;
$root = sys_get_temp_dir() . '/sqlite-wordpress-master-hot-89-' . bin2hex(random_bytes(4));
$mainPath = '/wp-content/database/main.sqlite';
$sitePath = '/wp-content/database/site.sqlite';
$masterPath = '/wp-content/database/main.sqlite-mj89';
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
        if ($entry !== '.' && $entry !== '..') {
            $removeTree($path . DIRECTORY_SEPARATOR . $entry);
        }
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

$removeTree($root);
mkdir(dirname($local($mainPath)), 0777, true);

$mainJournalBytes = $makeJournal([
    1 => $page('clean main schema before interrupted plugin import'),
    2 => $page('clean active_plugins option before interrupted plugin import'),
], 0x89010001);
$siteJournalBytes = $makeJournal([
    1 => $page('clean site schema before interrupted network import'),
    2 => $page('clean upload_path option before interrupted network import'),
], 0x89010002);
$masterBytes = $mainPath . "-journal\n" . $sitePath . "-journal\n";

file_put_contents($local($mainPath), $page('dirty main schema after interrupted plugin import') . $page('dirty active_plugins option after interrupted plugin import') . $page('dirty overflow page to truncate'));
file_put_contents($local($mainPath . '-journal'), $mainJournalBytes);
file_put_contents($local($sitePath), $page('dirty site schema after interrupted network import') . $page('dirty upload_path option after interrupted network import'));
file_put_contents($local($sitePath . '-journal'), $siteJournalBytes);
file_put_contents($local($masterPath), $masterBytes);

$applied = (new SQLiteVfsFileWriter($root))->applyMasterJournalHotRollbackCurrentSource89($masterPath, [
    [
        'database_path' => $mainPath,
        'stale_database_bytes' => $page('stale pre-open main schema that must not win') . $page('stale active_plugins that must not win'),
        'stale_journal_bytes' => $makeJournal([1 => $page('stale clean schema that must not win')], 0x89019999),
    ],
    ['database_path' => $sitePath],
], $pageSize);

$summary = [
    'status' => $applied['status'],
    'recoveredDatabases' => $applied['recovery']['recovered_database_count'],
    'staleCandidatesIgnored' => $applied['recovery']['stale_candidate_count'],
    'masterDeleted' => !is_file($local($masterPath)),
    'mainJournalDeleted' => !is_file($local($mainPath . '-journal')),
    'siteJournalDeleted' => !is_file($local($sitePath . '-journal')),
    'mainActivePluginsRecovered' => str_contains((string) file_get_contents($local($mainPath)), 'clean active_plugins option'),
    'staleSnapshotIgnored' => !str_contains((string) file_get_contents($local($mainPath)), 'stale active_plugins'),
    'mainTruncatedBytes' => filesize($local($mainPath)),
    'operationsApplied' => $applied['applied'],
    'durableSyncs' => $applied['durable_syncs'],
];

if (in_array('--self-test', $argv, true)) {
    foreach ([
        $summary['status'] === 'applied',
        $summary['recoveredDatabases'] === 2,
        $summary['staleCandidatesIgnored'] === 1,
        $summary['masterDeleted'] === true,
        $summary['mainActivePluginsRecovered'] === true,
        $summary['staleSnapshotIgnored'] === true,
        $summary['mainTruncatedBytes'] === $pageSize * 2,
    ] as $passed) {
        if (!$passed) {
            $removeTree($root);
            throw new RuntimeException('WordPress pager master-journal hot rollback current-source next89 smoke failed');
        }
    }
}

echo json_encode($summary, JSON_PRETTY_PRINT) . PHP_EOL;
$removeTree($root);
