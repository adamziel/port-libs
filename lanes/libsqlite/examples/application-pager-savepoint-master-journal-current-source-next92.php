<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/SQLiteRollbackJournalHeader.php';
require_once __DIR__ . '/../src/SQLiteRollbackJournalPage.php';
require_once __DIR__ . '/../src/SQLiteRollbackJournal.php';
require_once __DIR__ . '/../src/SQLitePagerMasterJournalHotRollbackCurrentSourceNextPlan.php';
require_once __DIR__ . '/../src/SQLitePagerSavepointMasterJournalCurrentSourceNextPlan.php';

use PortLibs\LibSqlite\SQLitePagerSavepointMasterJournalCurrentSourceNextPlan;
use PortLibs\LibSqlite\SQLiteRollbackJournal;
use PortLibs\LibSqlite\SQLiteRollbackJournalHeader;

$pageSize = 512;
$sectorSize = 512;
$page = static fn (string $label): string => str_pad($label, $pageSize, '.', STR_PAD_RIGHT);
$makeJournal = static function (array $pages, int $initialPageCount, int $nonce) use ($sectorSize, $pageSize): string {
    $header = SQLiteRollbackJournalHeader::MAGIC . pack('N*', count($pages), $nonce, $initialPageCount, $sectorSize, $pageSize);
    $bytes = str_pad($header, $sectorSize, "\0");
    foreach ($pages as $pageNumber => $image) {
        $bytes .= pack('N', $pageNumber) . $image . pack('N', SQLiteRollbackJournal::pageChecksum($image, $nonce));
    }

    return $bytes;
};

$mainPath = '/wp-content/database/wp.sqlite';
$sitePath = '/wp-content/database/site.sqlite';
$masterPath = '/wp-content/database/wp.sqlite-mj92';
$mainClean1 = $page('wp next92 clean schema before plugin savepoint retry');
$mainClean2 = $page('wp next92 clean active_plugins before plugin savepoint retry');
$mainDirty1 = $page('wp next92 dirty schema after crashed plugin savepoint');
$mainDirty2 = $page('wp next92 dirty active_plugins after crashed plugin savepoint');
$siteClean1 = $page('wp next92 clean site schema before attached retry');
$siteDirty1 = $page('wp next92 dirty site schema after attached retry');

$plan = SQLitePagerSavepointMasterJournalCurrentSourceNextPlan::currentSourceNext(
    $masterPath,
    $mainPath . "-journal\n" . $sitePath . "-journal\n",
    [
        [
            'database_path' => $mainPath,
            'current_database_bytes' => $mainDirty1 . $mainDirty2,
            'current_journal_bytes' => $makeJournal([1 => $mainClean1, 2 => $mainClean2], 2, 0x9200),
        ],
        [
            'database_path' => $sitePath,
            'current_database_bytes' => $siteDirty1,
            'current_journal_bytes' => $makeJournal([1 => $siteClean1], 1, 0x9201),
        ],
    ],
    $pageSize,
    $mainPath,
    'plugin-import-next92',
    [
        2 => $page('wp next92 retry rewrites active_plugins after recovery'),
        3 => $page('wp next92 retry appends plugin import marker'),
    ]
);

if (($argv[1] ?? '') === '--self-test') {
    assert($plan['status'] === 'master_journal_recovered_retry_savepoint_current_source_next');
    assert($plan['captured_before_images'][0]['source'] === 'master-journal-recovered-database');
    assert(str_contains($plan['final_database_bytes'], 'retry rewrites active_plugins'));
    fwrite(STDOUT, "application-pager-savepoint-master-journal-current-source-next92 self-test passed\n");
    return;
}

echo json_encode([
    'status' => $plan['status'],
    'savepoint' => $plan['savepoint'],
    'recovered' => $plan['master_recovery']['recovered_database_count'],
    'retry_pages' => $plan['retry_page_numbers'],
    'captured_sources' => array_column($plan['captured_before_images'], 'source'),
    'dependencies' => $plan['dependencies'],
], JSON_PRETTY_PRINT) . "\n";
