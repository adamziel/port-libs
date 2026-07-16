<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePagerMasterJournalCacheRecoveryCurrentSourceNextPlan;
use PortLibs\LibSqlite\SQLiteRollbackJournal;
use PortLibs\LibSqlite\SQLiteRollbackJournalHeader;
use PortLibs\LibSqlite\SQLiteSavepointStack;

$tests = [];

$pageSize = 512;
$sectorSize = 512;
$mainPath = '/srv/wp/database/main.sqlite';
$sitePath = '/srv/wp/database/site.sqlite';
$masterPath = '/srv/wp/database/main.sqlite-mj122';
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
    $stack->beginTransaction('wp-options-import');
    $stack->recordPageImageWrite(1, $page('next122 outer page before import'));
    $stack->savepoint('active-plugins');
    $stack->recordPageImageWrite(3, $page('next122 plugin settings before cached recovery'));

    return $stack;
};

$mainClean1 = $page('next122 clean main schema from current master');
$mainClean2 = $page('next122 clean active_plugins from current master');
$mainClean3 = $page('next122 clean plugin setting from current master');
$mainDirty1 = $page('next122 dirty main schema after crash');
$mainDirty2 = $page('next122 dirty active_plugins after crash');
$mainDirty3 = $page('next122 dirty plugin setting after crash');
$siteClean1 = $page('next122 clean site schema from current master');
$siteDirty1 = $page('next122 dirty site schema after crash');
$retry2 = $page('next122 retry active_plugins after current master recovery');
$retry4 = $page('next122 retry appends plugin autoload row');
$staleJournal = $makeJournal([1 => $page('next122 stale cached journal old page')], 1, 0x1220abcd);

$mainJournal = $makeJournal([1 => $mainClean1, 2 => $mainClean2, 3 => $mainClean3], 3, 0x12200001);
$siteJournal = $makeJournal([1 => $siteClean1], 1, 0x12200002);
$cachedMaster = $mainPath . "-journal\n";
$currentMaster = $mainPath . "-journal\n" . $sitePath . "-journal\n";
$databases = [
    [
        'database_path' => $mainPath,
        'current_database_bytes' => $mainDirty1 . $mainDirty2 . $mainDirty3,
        'current_journal_bytes' => $mainJournal,
        'stale_journal_bytes' => $staleJournal,
    ],
    [
        'database_path' => $sitePath,
        'current_database_bytes' => $siteDirty1,
        'current_journal_bytes' => $siteJournal,
    ],
];
$retryWrites = [2 => $retry2, 4 => $retry4];
$plan = static fn (?string $cached = null, ?string $current = null, ?SQLiteSavepointStack $stack = null): array => SQLitePagerMasterJournalCacheRecoveryCurrentSourceNextPlan::currentSourceNext(
    $masterPath,
    func_num_args() >= 1 ? $cached : $cachedMaster,
    func_num_args() >= 2 ? $current : $currentMaster,
    $databases,
    $pageSize,
    $mainPath,
    'active-plugins',
    $stack ?? $makeStack(),
    $retryWrites
);

$cases = [
    'status' => static fn (): mixed => $plan()['status'],
    'reason' => static fn (): mixed => $plan()['reason'],
    'master path' => static fn (): mixed => $plan()['master_journal_path'],
    'cache stale rejected' => static fn (): mixed => $plan()['cache_stale_rejected'],
    'cached members' => static fn (): mixed => $plan()['cached_members'],
    'current members' => static fn (): mixed => $plan()['current_members'],
    'cache invalidated' => static fn (): mixed => $plan()['cache']['cache_invalidated'],
    'cache status' => static fn (): mixed => $plan()['cache']['status'],
    'member added' => static fn (): mixed => $plan()['cache']['member_delta']['added'],
    'member retained' => static fn (): mixed => $plan()['cache']['member_delta']['retained'],
    'main recheck action' => static fn (): mixed => $plan()['cache']['journal_rechecks'][$mainPath . '-journal']['cache_action'],
    'site recheck action' => static fn (): mixed => $plan()['cache']['journal_rechecks'][$sitePath . '-journal']['cache_action'],
    'cached recovery status' => static fn (): mixed => $plan()['cached_recovery_status'],
    'current recovery status' => static fn (): mixed => $plan()['current_recovery_status'],
    'cached recovered count' => static fn (): mixed => $plan()['cached_recovered_database_count'],
    'current recovered count' => static fn (): mixed => $plan()['current_recovered_database_count'],
    'current source verified' => static fn (): mixed => $plan()['current_source_verified'],
    'recovery status' => static fn (): mixed => $plan()['recovery']['status'],
    'master recovery status' => static fn (): mixed => $plan()['recovery']['retry_recovery']['master_recovery']['status'],
    'master recovered count' => static fn (): mixed => $plan()['recovery']['retry_recovery']['master_recovery']['recovered_database_count'],
    'master members from current source' => static fn (): mixed => $plan()['recovery']['retry_recovery']['master_recovery']['master_journal_members'],
    'stale candidate ignored' => static fn (): mixed => $plan()['recovery']['retry_recovery']['master_recovery']['stale_candidate_count'],
    'main stale flag' => static fn (): mixed => $plan()['recovery']['retry_recovery']['master_recovery']['hot_journals'][$mainPath . '-journal']['stale_candidate_ignored'],
    'site listed flag' => static fn (): mixed => $plan()['recovery']['retry_recovery']['master_recovery']['hot_journals'][$sitePath . '-journal']['listed_in_master_journal'],
    'captured pages' => static fn (): mixed => $plan()['recovery']['captured_page_numbers'],
    'captured source page two' => static fn (): mixed => $plan()['recovery']['captured_sources'][2],
    'captured source page four' => static fn (): mixed => $plan()['recovery']['captured_sources'][4],
    'rollback restored pages' => static fn (): mixed => $plan()['rollback_preview']['restored_page_numbers'],
    'rollback restored page two prefix' => static fn (): mixed => $plan()['rollback_preview']['restored_prefixes'][2],
    'rollback restored page three prefix' => static fn (): mixed => $plan()['rollback_preview']['restored_prefixes'][3],
    'rollback restored page four prefix' => static fn (): mixed => $plan()['rollback_preview']['restored_prefixes'][4],
    'rollback excludes retry writes' => static fn (): mixed => $plan()['rollback_preview']['contains_retry_writes'],
    'retry final has write' => static fn (): mixed => str_contains($plan()['recovery']['retry_recovery']['final_database_bytes'], 'retry active_plugins'),
    'retry recovered excludes write' => static fn (): mixed => str_contains($plan()['recovery']['retry_recovery']['recovered_database_bytes'], 'retry active_plugins'),
    'payload final exists' => static fn (): mixed => isset($plan()['payloads'][$mainPath . '#master-savepoint-current-source-next108']),
    'payload rollback exists' => static fn (): mixed => isset($plan()['payloads'][$mainPath . '#master-savepoint-rollback-preview-next108']),
    'payload rollback clean active plugins' => static fn (): mixed => str_contains($plan()['payloads'][$mainPath . '#master-savepoint-rollback-preview-next108'], 'clean active_plugins'),
    'first operation discards cache' => static fn (): mixed => $plan()['operations'][0]['op'],
    'second operation invalidates cache' => static fn (): mixed => $plan()['operations'][1]['op'],
    'operation reason suffix' => static fn (): mixed => str_ends_with($plan()['operations'][4]['reason'], '_after_cache_refresh_current_source_next122'),
    'dependency marker' => static fn (): mixed => in_array('sqlite-pager-master-journal-cache-recovery-current-source-next122', $plan()['dependencies'], true),
    'dependency cache' => static fn (): mixed => in_array('sqlite-pager-master-journal-cache-current-next77', $plan()['dependencies'], true),
    'dependency savepoint' => static fn (): mixed => in_array('sqlite-pager-master-journal-savepoint-current-source-next108', $plan()['dependencies'], true),
    'unchanged status' => static fn (): mixed => $plan($currentMaster, $currentMaster)['status'],
    'unchanged stale flag' => static fn (): mixed => $plan($currentMaster, $currentMaster)['cache_stale_rejected'],
    'unchanged cache status' => static fn (): mixed => $plan($currentMaster, $currentMaster)['cache']['status'],
    'missing current status' => static fn (): mixed => $plan($cachedMaster, null)['status'],
    'missing current verified' => static fn (): mixed => $plan($cachedMaster, null)['current_source_verified'],
    'missing current recovered count' => static fn (): mixed => $plan($cachedMaster, null)['current_recovered_database_count'],
    'missing current rollback preview' => static fn (): mixed => $plan($cachedMaster, null)['rollback_preview'],
    'duplicate current members collapsed' => static fn (): mixed => $plan($cachedMaster, $currentMaster . $sitePath . "-journal\n")['current_members'],
    'blank cached members' => static fn (): mixed => $plan('', $currentMaster)['cached_members'],
    'blank cached rejected' => static fn (): mixed => $plan('', $currentMaster)['cache_stale_rejected'],
    'cached only site current recovers one' => static fn (): mixed => $plan($cachedMaster, $sitePath . "-journal\n")['current_recovered_database_count'],
    'cached only site rollback blocked flag' => static fn (): mixed => $plan($cachedMaster, $sitePath . "-journal\n")['current_source_verified'],
    'same stack not mutated before' => static function () use ($makeStack, $plan): mixed {
        $stack = $makeStack();
        $before = $stack->rollbackToPlan('active-plugins');
        $plan(null, null, $stack);
        return $stack->rollbackToPlan('active-plugins') === $before;
    },
];

$expected = [
    'status' => 'master_journal_cache_recovery_current_source_next122',
    'reason' => 'master_journal_recovery_uses_current_source_after_cache_refresh',
    'master path' => $masterPath,
    'cache stale rejected' => true,
    'cached members' => [$mainPath . '-journal'],
    'current members' => [$mainPath . '-journal', $sitePath . '-journal'],
    'cache invalidated' => true,
    'cache status' => 'master_journal_cache_refreshed_current_next',
    'member added' => [$sitePath . '-journal'],
    'member retained' => [$mainPath . '-journal'],
    'main recheck action' => 'retain_cached_hot_journal',
    'site recheck action' => 'candidate_new_hot_journal',
    'cached recovery status' => 'master_journal_savepoint_current_source_next',
    'current recovery status' => 'master_journal_savepoint_current_source_next',
    'cached recovered count' => 1,
    'current recovered count' => 2,
    'current source verified' => true,
    'recovery status' => 'master_journal_savepoint_current_source_next',
    'master recovery status' => 'master_journal_current_source_hot_rollback_complete',
    'master recovered count' => 2,
    'master members from current source' => [$mainPath . '-journal', $sitePath . '-journal'],
    'stale candidate ignored' => 1,
    'main stale flag' => true,
    'site listed flag' => true,
    'captured pages' => [2, 4],
    'captured source page two' => 'master-journal-recovered-database',
    'captured source page four' => 'zero-fill',
    'rollback restored pages' => [2, 3, 4],
    'rollback restored page two prefix' => 'next122 clean active_plugins from current master',
    'rollback restored page three prefix' => 'next122 plugin settings before cached recovery',
    'rollback restored page four prefix' => '',
    'rollback excludes retry writes' => false,
    'retry final has write' => true,
    'retry recovered excludes write' => false,
    'payload final exists' => true,
    'payload rollback exists' => true,
    'payload rollback clean active plugins' => true,
    'first operation discards cache' => 'discard_cached_master_journal_recovery',
    'second operation invalidates cache' => 'invalidate_master_journal_cache',
    'operation reason suffix' => true,
    'dependency marker' => true,
    'dependency cache' => true,
    'dependency savepoint' => true,
    'unchanged status' => 'master_journal_cache_recovery_current_source_unchanged_next122',
    'unchanged stale flag' => false,
    'unchanged cache status' => 'master_journal_cache_current',
    'missing current status' => 'master_journal_cache_recovery_current_source_next122',
    'missing current verified' => false,
    'missing current recovered count' => 0,
    'missing current rollback preview' => null,
    'duplicate current members collapsed' => [$mainPath . '-journal', $sitePath . '-journal'],
    'blank cached members' => [],
    'blank cached rejected' => true,
    'cached only site current recovers one' => 1,
    'cached only site rollback blocked flag' => true,
    'same stack not mutated before' => true,
];

foreach ($cases as $name => $callback) {
    $tests['pager master journal cache recovery current source next122 ' . $name] = static function (TestRunner $t) use ($callback, $expected, $name): void {
        $t->same($expected[$name], $callback());
    };
}

$throws = [
    'empty master path rejected' => static fn () => SQLitePagerMasterJournalCacheRecoveryCurrentSourceNextPlan::currentSourceNext('', $cachedMaster, $currentMaster, $databases, $pageSize, $mainPath, 'active-plugins', $makeStack(), $retryWrites),
    'missing database path rejected' => static fn () => SQLitePagerMasterJournalCacheRecoveryCurrentSourceNextPlan::currentSourceNext($masterPath, $cachedMaster, $currentMaster, [['current_database_bytes' => 'x', 'current_journal_bytes' => 'y']], $pageSize, $mainPath, 'active-plugins', $makeStack(), $retryWrites),
    'empty savepoint rejected' => static fn () => SQLitePagerMasterJournalCacheRecoveryCurrentSourceNextPlan::currentSourceNext($masterPath, $cachedMaster, $currentMaster, $databases, $pageSize, $mainPath, '', $makeStack(), $retryWrites),
    'empty retry writes rejected' => static fn () => SQLitePagerMasterJournalCacheRecoveryCurrentSourceNextPlan::currentSourceNext($masterPath, $cachedMaster, $currentMaster, $databases, $pageSize, $mainPath, 'active-plugins', $makeStack(), []),
    'bad page size rejected' => static fn () => SQLitePagerMasterJournalCacheRecoveryCurrentSourceNextPlan::currentSourceNext($masterPath, $cachedMaster, $currentMaster, $databases, 500, $mainPath, 'active-plugins', $makeStack(), $retryWrites),
    'read only rejected' => static fn () => SQLitePagerMasterJournalCacheRecoveryCurrentSourceNextPlan::currentSourceNext($masterPath, $cachedMaster, $currentMaster, $databases, $pageSize, $mainPath, 'active-plugins', $makeStack(), $retryWrites, true),
];

foreach ($throws as $name => $callback) {
    $tests['pager master journal cache recovery current source next122 ' . $name] = static function (TestRunner $t) use ($callback): void {
        $t->throws(Throwable::class, $callback);
    };
}

return $tests;
