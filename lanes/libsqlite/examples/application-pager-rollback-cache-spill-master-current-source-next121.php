<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/SQLitePagerRollbackCacheSpillMasterCurrentSourceNextPlan.php';
require_once __DIR__ . '/../src/SQLitePagerMasterJournalCacheSpillSavepointCurrentSourceNextPlan.php';
require_once __DIR__ . '/../src/SQLitePagerMasterJournalSavepointCurrentSourceNextPlan.php';
require_once __DIR__ . '/../src/SQLitePagerSavepointMasterJournalCurrentSourceNextPlan.php';
require_once __DIR__ . '/../src/SQLitePagerMasterJournalHotRollbackCurrentSourceNextPlan.php';
require_once __DIR__ . '/../src/SQLitePagerDirtyPageCacheSpillPlan.php';
require_once __DIR__ . '/../src/SQLiteRollbackJournal.php';
require_once __DIR__ . '/../src/SQLiteRollbackJournalHeader.php';
require_once __DIR__ . '/../src/SQLiteRollbackJournalPage.php';
require_once __DIR__ . '/../src/SQLiteSavepointStack.php';

use PortLibs\LibSqlite\SQLitePagerRollbackCacheSpillMasterCurrentSourceNextPlan;
use PortLibs\LibSqlite\SQLiteRollbackJournal;
use PortLibs\LibSqlite\SQLiteRollbackJournalHeader;
use PortLibs\LibSqlite\SQLiteSavepointStack;

$pageSize = 512;
$sectorSize = 512;
$page = static fn (string $label): string => str_pad($label, $pageSize, '.', STR_PAD_RIGHT);
$journal = static function (array $pages, int $initialPageCount, int $nonce) use ($sectorSize, $pageSize): string {
    $bytes = str_pad(SQLiteRollbackJournalHeader::MAGIC . pack('N*', count($pages), $nonce, $initialPageCount, $sectorSize, $pageSize), $sectorSize, "\0");
    foreach ($pages as $pageNumber => $image) {
        $bytes .= pack('N', $pageNumber) . $image . pack('N', SQLiteRollbackJournal::pageChecksum($image, $nonce));
    }

    return $bytes;
};

$mainPath = '/wp-content/database/.ht.sqlite';
$sitePath = '/wp-content/database/site-meta.sqlite';
$masterPath = $mainPath . '-mj-next121';
$cleanActivePlugins = $page('next121 clean active_plugins before rollback');
$cleanOptionsRoot = $page('next121 clean options root before rollback');
$cleanSetting = $page('next121 clean setting before savepoint retry');
$dirtyOptionsRoot = $page('next121 dirty options root from crashed import');
$dirtyActivePlugins = $page('next121 stale active_plugins cache from crash');
$dirtySetting = $page('next121 dirty setting from crashed import');
$retryActivePlugins = $page('next121 retry active_plugins after recovery');
$retryAutoload = $page('next121 retry autoload index after recovery');

$stack = new SQLiteSavepointStack();
$stack->beginTransaction('application-plugin-import-next121');
$stack->recordPageImageWrite(1, $cleanOptionsRoot);
$stack->savepoint('active-plugins-retry');
$stack->recordPageImageWrite(3, $cleanSetting);

$plan = SQLitePagerRollbackCacheSpillMasterCurrentSourceNextPlan::currentSourceNext(
    $masterPath,
    $mainPath . "-journal\n" . $sitePath . "-journal\n",
    [
        [
            'database_path' => $mainPath,
            'current_database_bytes' => $dirtyOptionsRoot . $dirtyActivePlugins . $dirtySetting,
            'current_journal_bytes' => $journal([1 => $cleanOptionsRoot, 2 => $cleanActivePlugins, 3 => $cleanSetting], 3, 0x12110001),
        ],
        [
            'database_path' => $sitePath,
            'current_database_bytes' => $page('next121 dirty site meta import'),
            'current_journal_bytes' => $journal([1 => $page('next121 clean site meta before import')], 1, 0x12110002),
        ],
    ],
    $pageSize,
    $mainPath,
    'active-plugins-retry',
    $stack,
    [2 => $retryActivePlugins, 4 => $retryAutoload],
    [
        ['page' => 2, 'bytes' => $pageSize, 'journaled' => true, 'image' => $cleanActivePlugins, 'source_id' => 'next121:recovered', 'statement' => 'retry-active-plugins'],
        ['page' => 4, 'bytes' => $pageSize, 'journaled' => true, 'image' => str_repeat("\0", $pageSize), 'source_id' => 'next121:recovered', 'statement' => 'append-autoload-index'],
    ],
    5,
    2,
    'next121:current',
    'next121:recovered',
    [2, 4, 5],
    'delete',
    true,
    'reserved',
    true,
    2
);

if (($argv[1] ?? '') === '--self-test') {
    if (
        $plan['status'] !== 'pager_rollback_cache_spill_master_current_source_next121'
        || $plan['admitted_spill_pages'] !== [2, 4]
        || $plan['retry_reads'][2]['source'] !== 'pager-read-miss'
    ) {
        fwrite(STDERR, "application-pager-rollback-cache-spill-master-current-source-next121 self-test failed\n");
        exit(1);
    }

    echo "application-pager-rollback-cache-spill-master-current-source-next121 self-test passed\n";
    exit(0);
}

echo json_encode([
    'scenario' => 'application-pager-rollback-cache-spill-master-current-source-next121',
    'applicationUse' => 'A copied wp_options plugin import crash recovers attached rollback journals through a master journal, then admits only recovered-current-source dirty pages for cache spill before retrying active_plugins writes.',
    'status' => $plan['status'],
    'admittedSpillPages' => $plan['admitted_spill_pages'],
    'staleCachePages' => $plan['stale_cache_pages'],
    'retrySources' => array_column($plan['retry_reads'], 'source', 'page'),
    'dependencyClosure' => 'no new support component needed; this composes existing native rollback journal, savepoint, master-journal, and cache-spill planners',
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
