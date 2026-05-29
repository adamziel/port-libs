<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePagerMasterJournalCacheSpillSavepointCurrentSourceNextPlan;
use PortLibs\LibSqlite\SQLiteRollbackJournal;
use PortLibs\LibSqlite\SQLiteRollbackJournalHeader;
use PortLibs\LibSqlite\SQLiteSavepointStack;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$pageSize = 512;
$sectorSize = 512;
$mainPath = '/srv/wp/database/main.sqlite';
$sitePath = '/srv/wp/database/site.sqlite';
$masterPath = '/srv/wp/database/main.sqlite-mj114';
$page = static fn (string $label, string $pad = '.'): string => str_pad($label, $pageSize, $pad, STR_PAD_RIGHT);
$makeJournal = static function (array $pages, int $initialPageCount, int $nonce) use ($sectorSize, $pageSize): string {
    $header = SQLiteRollbackJournalHeader::MAGIC . pack('N*', count($pages), $nonce, $initialPageCount, $sectorSize, $pageSize);
    $bytes = str_pad($header, $sectorSize, "\0");
    foreach ($pages as $pageNumber => $image) {
        $bytes .= pack('N', $pageNumber) . $image . pack('N', SQLiteRollbackJournal::pageChecksum($image, $nonce));
    }

    return $bytes;
};

$stack = new SQLiteSavepointStack();
$stack->beginTransaction('wp-options-master-cache-spill');
$stack->recordPageImageWrite(1, $page('next114 outer schema before attached import'));
$stack->savepoint('plugin-settings');
$stack->recordPageImageWrite(3, $page('next114 plugin settings before retry import'));

$mainClean1 = $page('next114 clean main schema before attached cache spill');
$mainClean2 = $page('next114 clean main active_plugins before cache spill');
$mainClean3 = $page('next114 clean main plugin settings before retry');
$mainDirty1 = $page('next114 dirty main schema after crashed cache spill');
$mainDirty2 = $page('next114 dirty main active_plugins stale cache spill');
$mainDirty3 = $page('next114 dirty main plugin settings stale cache spill');
$siteClean1 = $page('next114 clean site schema before attached import');
$siteDirty1 = $page('next114 dirty site schema after attached import');
$retry2 = $page('next114 retry writes active_plugins after recovery');
$retry4 = $page('next114 retry appends migration autoload option');
$stale3 = $page('next114 stale dirty plugin cache image');

$plan = SQLitePagerMasterJournalCacheSpillSavepointCurrentSourceNextPlan::currentSourceNext(
    $masterPath,
    $mainPath . "-journal\n" . $sitePath . "-journal\n",
    [
        [
            'database_path' => $mainPath,
            'current_database_bytes' => $mainDirty1 . $mainDirty2 . $mainDirty3,
            'current_journal_bytes' => $makeJournal([1 => $mainClean1, 2 => $mainClean2, 3 => $mainClean3], 3, 0x11400001),
        ],
        [
            'database_path' => $sitePath,
            'current_database_bytes' => $siteDirty1,
            'current_journal_bytes' => $makeJournal([1 => $siteClean1], 1, 0x11400002),
        ],
    ],
    $pageSize,
    $mainPath,
    'plugin-settings',
    $stack,
    [2 => $retry2, 4 => $retry4],
    [
        ['page' => 2, 'bytes' => $pageSize, 'journaled' => true, 'image' => $mainClean2],
        ['page' => 3, 'bytes' => $pageSize, 'journaled' => true, 'image' => $page('next114 plugin settings before retry import'), 'stale_image' => $stale3, 'pinned' => true],
        ['page' => 4, 'bytes' => $pageSize, 'journaled' => true, 'image' => str_repeat("\0", $pageSize)],
    ],
    6,
    3,
    'delete',
    true,
    'reserved',
    true,
    2
);

$summary = [
    'status' => $plan['status'],
    'savepoint' => $plan['savepoint'],
    'journal_mode' => $plan['journal_mode'],
    'current_source_verified' => $plan['current_source_verified'],
    'spilled_pages' => $plan['spill']['next']['spilled_pages'],
    'stale_rejected_pages' => $plan['stale_rejected_pages'],
    'source_mismatch_pages' => $plan['source_mismatch_pages'],
    'wordpressUse' => 'Recover attached WordPress database images through a master journal, then spill retry-savepoint cache pages only from the recovered current source.',
];

if (($argv[1] ?? null) === '--self-test') {
    if ($summary['status'] !== 'master_journal_cache_spill_savepoint_current_source_next114') {
        fwrite(STDERR, "Unexpected status\n");
        exit(1);
    }
    if ($summary['current_source_verified'] !== true || $summary['spilled_pages'] !== [2, 4] || $summary['stale_rejected_pages'] !== [3]) {
        fwrite(STDERR, "Unexpected cache-spill current-source summary\n");
        exit(1);
    }
    echo "wordpress-pager-master-journal-cache-spill-savepoint-current-source-next114 self-test passed\n";
    exit(0);
}

echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
