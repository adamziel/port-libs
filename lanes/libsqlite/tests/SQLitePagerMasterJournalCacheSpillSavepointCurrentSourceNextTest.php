<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePagerMasterJournalCacheSpillSavepointCurrentSourceNextPlan;
use PortLibs\LibSqlite\SQLiteRollbackJournal;
use PortLibs\LibSqlite\SQLiteRollbackJournalHeader;
use PortLibs\LibSqlite\SQLiteSavepointStack;

$tests = [];

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
$makeStack = static function () use ($page): SQLiteSavepointStack {
    $stack = new SQLiteSavepointStack();
    $stack->beginTransaction('wp-options-master-cache-spill');
    $stack->recordPageImageWrite(1, $page('next114 outer schema before attached import'));
    $stack->savepoint('plugin-settings');
    $stack->recordPageImageWrite(3, $page('next114 plugin settings before retry import'));

    return $stack;
};

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
$stale2 = $page('next114 stale dirty active_plugins cache image');
$stale3 = $page('next114 stale dirty plugin cache image');

$mainDatabase = $mainDirty1 . $mainDirty2 . $mainDirty3;
$siteDatabase = $siteDirty1;
$mainJournalBytes = $makeJournal([1 => $mainClean1, 2 => $mainClean2, 3 => $mainClean3], 3, 0x11400001);
$siteJournalBytes = $makeJournal([1 => $siteClean1], 1, 0x11400002);
$masterBytes = $mainPath . "-journal\n" . $sitePath . "-journal\n";
$databases = [
    [
        'database_path' => $mainPath,
        'current_database_bytes' => $mainDatabase,
        'current_journal_bytes' => $mainJournalBytes,
        'stale_database_bytes' => $stale2 . $mainDirty2,
    ],
    [
        'database_path' => $sitePath,
        'current_database_bytes' => $siteDatabase,
        'current_journal_bytes' => $siteJournalBytes,
    ],
];
$retryWrites = [2 => $retry2, 4 => $retry4];
$cachePages = [
    ['page' => 2, 'bytes' => $pageSize, 'journaled' => true, 'image' => $mainClean2],
    ['page' => 3, 'bytes' => $pageSize, 'journaled' => true, 'image' => $page('next114 plugin settings before retry import'), 'stale_image' => $stale3, 'pinned' => true],
    ['page' => 4, 'bytes' => $pageSize, 'journaled' => true, 'image' => str_repeat("\0", $pageSize)],
];

$plan = static fn (?string $master = null, array $pages = null, string $journalMode = 'delete', bool $synced = true): array => SQLitePagerMasterJournalCacheSpillSavepointCurrentSourceNextPlan::currentSourceNext(
    $masterPath,
    func_num_args() >= 1 ? $master : $masterBytes,
    $databases,
    $pageSize,
    $mainPath,
    'plugin-settings',
    $makeStack(),
    $retryWrites,
    $pages ?? $cachePages,
    6,
    3,
    $journalMode,
    $synced,
    'reserved',
    true,
    2
);

$walPlan = static fn (): array => SQLitePagerMasterJournalCacheSpillSavepointCurrentSourceNextPlan::currentSourceNext(
    $masterPath,
    $masterBytes,
    $databases,
    $pageSize,
    $mainPath,
    'plugin-settings',
    $makeStack(),
    $retryWrites,
    [
        ['page' => 2, 'bytes' => $pageSize, 'journaled' => true, 'image' => $mainClean2],
        ['page' => 4, 'bytes' => $pageSize, 'journaled' => true, 'image' => str_repeat("\0", $pageSize)],
    ],
    5,
    2,
    'wal',
    true,
    'shared',
    true,
    2
);

$mismatchPlan = static fn (): array => $plan($masterBytes, [
    ['page' => 2, 'bytes' => $pageSize, 'journaled' => true, 'image' => $stale2, 'stale_image' => $stale2],
]);
$blockedPlan = static fn (): array => $plan(null);
$unsyncedPlan = static fn (): array => $plan($masterBytes, $cachePages, 'delete', false);

$cases = [
    'status' => static fn (): mixed => $plan()['status'],
    'reason' => static fn (): mixed => $plan()['reason'],
    'primary path' => static fn (): mixed => $plan()['primary_database_path'],
    'savepoint' => static fn (): mixed => $plan()['savepoint'],
    'journal mode' => static fn (): mixed => $plan()['journal_mode'],
    'current source verified' => static fn (): mixed => $plan()['current_source_verified'],
    'recovery status' => static fn (): mixed => $plan()['recovery']['status'],
    'recovery current source verified' => static fn (): mixed => $plan()['recovery']['current_source_verified'],
    'spill status' => static fn (): mixed => $plan()['spill']['status'],
    'spill target' => static fn (): mixed => $plan()['spill']['spill_target'],
    'spilled pages' => static fn (): mixed => $plan()['spill']['next']['spilled_pages'],
    'spilled count' => static fn (): mixed => $plan()['spilled_page_count'],
    'spilled source keys' => static fn (): mixed => array_keys($plan()['spilled_page_sources']),
    'page two prefix' => static fn (): mixed => $plan()['spilled_page_sources'][2]['prefix'],
    'page two recovered source' => static fn (): mixed => $plan()['spilled_page_sources'][2]['matches_recovered_current'],
    'page two rollback source' => static fn (): mixed => $plan()['spilled_page_sources'][2]['matches_rollback_preview'],
    'page two stale false' => static fn (): mixed => $plan()['spilled_page_sources'][2]['uses_stale_dirty_cache'],
    'page four rollback source' => static fn (): mixed => $plan()['spilled_page_sources'][4]['matches_rollback_preview'],
    'page four recovered source' => static fn (): mixed => $plan()['spilled_page_sources'][4]['matches_recovered_current'],
    'stale rejected pages' => static fn (): mixed => $plan()['stale_rejected_pages'],
    'source mismatch pages empty' => static fn (): mixed => $plan()['source_mismatch_pages'],
    'rollback preview bytes' => static fn (): mixed => $plan()['rollback_preview_bytes'],
    'operations include master recovery' => static fn (): mixed => $plan()['operations'][0]['reason'],
    'operations include spill write' => static fn (): mixed => $plan()['operations'][17]['reason'],
    'dependencies include next114' => static fn (): mixed => in_array('sqlite-pager-master-journal-cache-spill-savepoint-current-source-next114', $plan()['dependencies'], true),
    'dependencies include next108' => static fn (): mixed => in_array('sqlite-pager-master-journal-savepoint-current-source-next108', $plan()['dependencies'], true),
    'dependencies include cache spill next107' => static fn (): mixed => in_array('sqlite-pager-cache-spill-journalmode-current-source-next107', $plan()['dependencies'], true),
    'wal status' => static fn (): mixed => $walPlan()['status'],
    'wal target' => static fn (): mixed => $walPlan()['spill']['spill_target'],
    'wal pages' => static fn (): mixed => $walPlan()['spill']['next']['wal_frame_pages'],
    'wal database unchanged' => static fn (): mixed => $walPlan()['spill']['next']['database_image'],
    'wal page two source verified' => static fn (): mixed => $walPlan()['spilled_page_sources'][2]['matches_recovered_current'],
    'wal page four rollback source' => static fn (): mixed => $walPlan()['spilled_page_sources'][4]['matches_rollback_preview'],
    'mismatch status' => static fn (): mixed => $mismatchPlan()['status'],
    'mismatch reason' => static fn (): mixed => $mismatchPlan()['reason'],
    'mismatch current source false' => static fn (): mixed => $mismatchPlan()['current_source_verified'],
    'mismatch page' => static fn (): mixed => $mismatchPlan()['source_mismatch_pages'],
    'mismatch uses stale' => static fn (): mixed => $mismatchPlan()['spilled_page_sources'][2]['uses_stale_dirty_cache'],
    'blocked status' => static fn (): mixed => $blockedPlan()['status'],
    'blocked recovery status' => static fn (): mixed => $blockedPlan()['recovery']['status'],
    'blocked source false' => static fn (): mixed => $blockedPlan()['current_source_verified'],
    'blocked spill still planned' => static fn (): mixed => $blockedPlan()['spill']['status'],
    'unsynced status' => static fn (): mixed => $unsyncedPlan()['status'],
    'unsynced spill deferred' => static fn (): mixed => $unsyncedPlan()['spill']['status'],
    'unsynced reason' => static fn (): mixed => $unsyncedPlan()['spill']['blocked_reasons'],
];

$expected = [
    'status' => 'master_journal_cache_spill_savepoint_current_source_next114',
    'reason' => 'cache_spill_pages_use_master_journal_savepoint_current_source',
    'primary path' => $mainPath,
    'savepoint' => 'plugin-settings',
    'journal mode' => 'delete',
    'current source verified' => true,
    'recovery status' => 'master_journal_savepoint_current_source_next',
    'recovery current source verified' => true,
    'spill status' => 'spilled',
    'spill target' => 'database_pages_after_rollback_journal',
    'spilled pages' => [2, 4],
    'spilled count' => 2,
    'spilled source keys' => [2, 4],
    'page two prefix' => 'next114 clean main active_plugins before cache spill',
    'page two recovered source' => true,
    'page two rollback source' => true,
    'page two stale false' => false,
    'page four rollback source' => true,
    'page four recovered source' => true,
    'stale rejected pages' => [3],
    'source mismatch pages empty' => [],
    'rollback preview bytes' => $pageSize * 4,
    'operations include master recovery' => 'restore_current_source_database_from_master_hot_journal',
    'operations include spill write' => 'cache_spill_requires_exclusive_lock_after_master_journal_savepoint_current_source',
    'dependencies include next114' => true,
    'dependencies include next108' => true,
    'dependencies include cache spill next107' => true,
    'wal status' => 'master_journal_cache_spill_savepoint_current_source_next114',
    'wal target' => 'wal_frames',
    'wal pages' => [2, 4],
    'wal database unchanged' => 'unchanged_until_checkpoint',
    'wal page two source verified' => true,
    'wal page four rollback source' => true,
    'mismatch status' => 'master_journal_cache_spill_savepoint_current_source_blocked_next114',
    'mismatch reason' => 'cache_spill_source_mismatch_after_master_journal_savepoint',
    'mismatch current source false' => false,
    'mismatch page' => [2],
    'mismatch uses stale' => true,
    'blocked status' => 'master_journal_cache_spill_savepoint_current_source_blocked_next114',
    'blocked recovery status' => 'master_journal_savepoint_current_source_blocked',
    'blocked source false' => false,
    'blocked spill still planned' => 'spilled',
    'unsynced status' => 'master_journal_cache_spill_savepoint_current_source_blocked_next114',
    'unsynced spill deferred' => 'deferred',
    'unsynced reason' => ['journal_not_synced'],
];

foreach ($cases as $name => $callback) {
    $tests['pager master journal cache spill savepoint current source next114 ' . $name] = static function (TestRunner $t) use ($callback, $expected, $name): void {
        $t->same($expected[$name], $callback());
    };
}

$throws = [
    'empty cache pages rejected' => static fn () => SQLitePagerMasterJournalCacheSpillSavepointCurrentSourceNextPlan::currentSourceNext($masterPath, $masterBytes, $databases, $pageSize, $mainPath, 'plugin-settings', $makeStack(), $retryWrites, [], 4, 2),
    'bad page rejected' => static fn () => $plan($masterBytes, [['page' => 0, 'image' => $mainClean2]]),
    'short image rejected' => static fn () => $plan($masterBytes, [['page' => 2, 'image' => 'short']]),
    'short stale image rejected' => static fn () => $plan($masterBytes, [['page' => 2, 'image' => $mainClean2, 'stale_image' => 'short']]),
    'unknown savepoint rejected' => static fn () => SQLitePagerMasterJournalCacheSpillSavepointCurrentSourceNextPlan::currentSourceNext($masterPath, $masterBytes, $databases, $pageSize, $mainPath, 'missing', $makeStack(), $retryWrites, $cachePages, 4, 2),
    'bad journal mode rejected' => static fn () => $plan($masterBytes, $cachePages, 'bad'),
];

foreach ($throws as $name => $callback) {
    $tests['pager master journal cache spill savepoint current source next114 ' . $name] = static function (TestRunner $t) use ($callback): void {
        $t->throws(Throwable::class, $callback);
    };
}

return $tests;
