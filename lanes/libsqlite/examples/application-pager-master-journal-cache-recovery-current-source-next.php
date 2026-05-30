<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePagerMasterJournalCacheRecoveryCurrentSourceNextPlan;
use PortLibs\LibSqlite\SQLiteRollbackJournal;
use PortLibs\LibSqlite\SQLiteRollbackJournalHeader;
use PortLibs\LibSqlite\SQLiteSavepointStack;

require_once __DIR__ . '/../src/SQLitePagerMasterJournalCacheRecoveryCurrentSourceNextPlan.php';
require_once __DIR__ . '/../src/SQLitePagerMasterJournalCacheCurrentNextPlan.php';
require_once __DIR__ . '/../src/SQLitePagerSavepointMasterJournalCurrentSourceNextPlan.php';
require_once __DIR__ . '/../src/SQLitePagerMasterJournalSavepointCurrentSourceNextPlan.php';
require_once __DIR__ . '/../src/SQLitePagerMasterJournalHotRollbackCurrentSourceNextPlan.php';
require_once __DIR__ . '/../src/SQLiteRollbackJournal.php';
require_once __DIR__ . '/../src/SQLiteRollbackJournalHeader.php';
require_once __DIR__ . '/../src/SQLiteRollbackJournalPage.php';
require_once __DIR__ . '/../src/SQLiteSavepointStack.php';

$pageSize = 512;
$sectorSize = 512;
$mainPath = '/srv/wp/database/main.sqlite';
$sitePath = '/srv/wp/database/site.sqlite';
$masterPath = '/srv/wp/database/main.sqlite-mj122';
$page = static fn (string $label): string => str_pad($label, $pageSize, '.', STR_PAD_RIGHT);
$makeJournal = static function (array $pages, int $initialPageCount, int $nonce) use ($sectorSize, $pageSize): string {
    $header = SQLiteRollbackJournalHeader::MAGIC . pack('N*', count($pages), $nonce, $initialPageCount, $sectorSize, $pageSize);
    $bytes = str_pad($header, $sectorSize, "\0");
    foreach ($pages as $pageNumber => $image) {
        $bytes .= pack('N', $pageNumber) . $image . pack('N', SQLiteRollbackJournal::pageChecksum($image, $nonce));
    }

    return $bytes;
};

$savepoints = new SQLiteSavepointStack();
$savepoints->beginTransaction('wp-options-import');
$savepoints->savepoint('active-plugins');
$savepoints->recordPageImageWrite(3, $page('plugin settings before retry'));

$plan = SQLitePagerMasterJournalCacheRecoveryCurrentSourceNextPlan::currentSourceNext(
    $masterPath,
    $mainPath . "-journal\n",
    $mainPath . "-journal\n" . $sitePath . "-journal\n",
    [
        [
            'database_path' => $mainPath,
            'current_database_bytes' => $page('dirty main schema') . $page('dirty active plugins') . $page('dirty plugin settings'),
            'current_journal_bytes' => $makeJournal([1 => $page('clean main schema'), 2 => $page('clean active plugins'), 3 => $page('clean plugin settings')], 3, 0x12210001),
        ],
        [
            'database_path' => $sitePath,
            'current_database_bytes' => $page('dirty site schema'),
            'current_journal_bytes' => $makeJournal([1 => $page('clean site schema')], 1, 0x12210002),
        ],
    ],
    $pageSize,
    $mainPath,
    'active-plugins',
    $savepoints,
    [2 => $page('retry active plugin update after recovered source')]
);

$summary = [
    'scenario' => 'application-pager-master-journal-cache-recovery-current-source-next122',
    'status' => $plan['status'],
    'cacheStaleRejected' => $plan['cache_stale_rejected'],
    'currentRecoveredDatabases' => $plan['current_recovered_database_count'],
    'currentMembers' => $plan['current_members'],
    'rollbackRestoredPages' => $plan['rollback_preview']['restored_page_numbers'],
    'applicationUse' => 'Copied Application multisite option imports re-read the current master journal before hot rollback and savepoint retry, so stale pager cache membership cannot skip an attached site database journal or seed retry before-images from crashed bytes.',
    'dependencyClosure' => 'no new support component needed; this reuses the existing native PHP rollback-journal parser, master-journal cache planner, and savepoint current-source recovery planner',
];

if ($summary['status'] !== 'master_journal_cache_recovery_current_source_next122'
    || $summary['cacheStaleRejected'] !== true
    || $summary['currentRecoveredDatabases'] !== 2
) {
    fwrite(STDERR, "application-pager-master-journal-cache-recovery-current-source-next122 self-test failed\n");
    exit(1);
}

echo "application-pager-master-journal-cache-recovery-current-source-next122 self-test passed\n";
echo json_encode($summary, JSON_PRETTY_PRINT) . "\n";
