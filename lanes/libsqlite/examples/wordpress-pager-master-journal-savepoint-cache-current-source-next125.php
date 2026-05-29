<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePagerMasterJournalSavepointCacheCurrentSourceNextPlan;
use PortLibs\LibSqlite\SQLiteRollbackJournal;
use PortLibs\LibSqlite\SQLiteRollbackJournalHeader;
use PortLibs\LibSqlite\SQLiteSavepointStack;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$pageSize = 512;
$sectorSize = 512;
$mainPath = '/srv/www/wp-content/database/main.sqlite';
$sitePath = '/srv/www/wp-content/database/site.sqlite';
$masterPath = '/srv/www/wp-content/database/main.sqlite-mj125';
$page = static fn (string $label): string => str_pad($label, $pageSize, '.', STR_PAD_RIGHT);
$makeJournal = static function (array $pages, int $initialPageCount, int $nonce) use ($sectorSize, $pageSize): string {
    $header = SQLiteRollbackJournalHeader::MAGIC . pack('N*', count($pages), $nonce, $initialPageCount, $sectorSize, $pageSize);
    $bytes = str_pad($header, $sectorSize, "\0");
    foreach ($pages as $pageNumber => $image) {
        $bytes .= pack('N', $pageNumber) . $image . pack('N', SQLiteRollbackJournal::pageChecksum($image, $nonce));
    }

    return $bytes;
};

$stack = new SQLiteSavepointStack();
$stack->beginTransaction('wp-import-cache-refresh');
$stack->recordPageImageWrite(1, $page('wp schema before plugin retry'));
$stack->savepoint('active-plugins');
$stack->recordPageImageWrite(3, $page('plugin settings before retry'));

$mainClean1 = $page('clean wp schema from current master');
$mainClean2 = $page('clean active_plugins from current master');
$mainClean3 = $page('clean plugin settings from current master');
$mainDirty1 = $page('dirty wp schema after crash');
$mainDirty2 = $page('dirty active_plugins after crash');
$mainDirty3 = $page('dirty plugin settings after crash');
$siteClean1 = $page('clean attached site schema');
$siteDirty1 = $page('dirty attached site schema');
$mainJournal = $makeJournal([1 => $mainClean1, 2 => $mainClean2, 3 => $mainClean3], 3, 0x12510001);
$siteJournal = $makeJournal([1 => $siteClean1], 1, 0x12510002);
$cachedMaster = $mainPath . "-journal\n";
$currentMaster = $mainPath . "-journal\n" . $sitePath . "-journal\n";

$plan = SQLitePagerMasterJournalSavepointCacheCurrentSourceNextPlan::currentSourceNext125(
    $masterPath,
    $cachedMaster,
    $currentMaster,
    [
        [
            'database_path' => $mainPath,
            'current_database_bytes' => $mainDirty1 . $mainDirty2 . $mainDirty3,
            'current_journal_bytes' => $mainJournal,
            'stale_journal_bytes' => $makeJournal([1 => $page('stale cached master journal')], 1, 0x1251abcd),
        ],
        [
            'database_path' => $sitePath,
            'current_database_bytes' => $siteDirty1,
            'current_journal_bytes' => $siteJournal,
        ],
    ],
    $pageSize,
    $mainPath,
    'active-plugins',
    $stack,
    [
        2 => $page('retry active_plugins after current master recovery'),
        4 => $page('retry autoload index append'),
    ],
    [
        1 => ['image' => $mainClean1, 'source' => 'database', 'source_id' => 'cached-source-before-crash', 'epoch' => 4],
        2 => ['image' => $page('stale active_plugins cache'), 'source' => 'stale-pager-cache', 'source_id' => 'cached-source-before-crash', 'epoch' => 4],
        3 => ['image' => $mainClean3, 'source' => 'dirty-savepoint-cache', 'source_id' => 'cached-source-before-crash', 'epoch' => 4, 'dirty' => true],
    ],
    [1, 2, 3, 4],
    'cached-source-before-crash',
    4
);

if (
    $plan['status'] !== 'pager_master_journal_savepoint_cache_current_source_next125'
    || $plan['cache']['invalidated_page_numbers'] !== [2, 3]
    || array_column($plan['release_reads'], 'cache_hit') !== [true, true, true, true]
) {
    fwrite(STDERR, "wordpress-pager-master-journal-savepoint-cache-current-source-next125 self-test failed\n");
    exit(1);
}

if (PHP_SAPI === 'cli' && realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    echo "wordpress-pager-master-journal-savepoint-cache-current-source-next125 self-test passed\n";
}

return [
    'scenario' => 'wordpress-pager-master-journal-savepoint-cache-current-source-next125',
    'wordpressUse' => 'Refresh copied WordPress SQLite pager cache pages after attached-database master-journal recovery and a retry inside a savepoint, so active_plugins and plugin settings reads use recovered current-source bytes instead of stale crashed cache entries.',
    'status' => $plan['status'],
    'cacheStaleRejected' => $plan['cache_stale_rejected'],
    'invalidatedPages' => $plan['cache']['invalidated_page_numbers'],
    'installedPages' => $plan['cache']['installed_page_numbers'],
    'releaseReads' => $plan['release_reads'],
    'dependencies' => $plan['dependencies'],
    'dependencyClosure' => 'no new support component needed; this reuses native rollback-journal parsing, master-journal cache recovery, savepoint current-source rollback previews, and pager cache source-token evidence',
];
