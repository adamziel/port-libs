<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePagerRollbackCacheSpillMasterCurrentSourceNextPlan;
use PortLibs\LibSqlite\SQLiteRollbackJournal;
use PortLibs\LibSqlite\SQLiteRollbackJournalHeader;
use PortLibs\LibSqlite\SQLiteSavepointStack;

$tests = [];

$pageSize = 512;
$sectorSize = 512;
$mainPath = '/srv/wp/database/options-next121.sqlite';
$sitePath = '/srv/wp/database/sitemeta-next121.sqlite';
$masterPath = '/srv/wp/database/options-next121.sqlite-mj';
$currentSource = 'rollback-cache-spill:current:120';
$recoveredSource = 'rollback-cache-spill:recovered:121';
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
    $stack->beginTransaction('next121 options import');
    $stack->recordPageImageWrite(1, $page('next121 outer schema before cache spill'));
    $stack->savepoint('rewrite-active-plugins');
    $stack->recordPageImageWrite(3, $page('next121 plugin setting before retry'));

    return $stack;
};

$clean1 = $page('next121 clean sqlite schema before crash');
$clean2 = $page('next121 clean active_plugins before cache spill');
$clean3 = $page('next121 clean plugin setting before retry');
$dirty1 = $page('next121 dirty sqlite schema from crashed writer');
$dirty2 = $page('next121 dirty active_plugins stale cache source');
$dirty3 = $page('next121 dirty plugin setting stale cache source');
$siteClean1 = $page('next121 clean sitemeta before attached crash');
$siteDirty1 = $page('next121 dirty sitemeta after attached crash');
$retry2 = $page('next121 retry writes active_plugins from current source');
$retry4 = $page('next121 retry appends autoload migration page');
$stale2 = $page('next121 stale active_plugins dirty cache image');
$stale5 = $page('next121 stale transient dirty cache image');

$mainDatabase = $dirty1 . $dirty2 . $dirty3;
$siteDatabase = $siteDirty1;
$mainJournal = $makeJournal([1 => $clean1, 2 => $clean2, 3 => $clean3], 3, 0x12100001);
$siteJournal = $makeJournal([1 => $siteClean1], 1, 0x12100002);
$masterBytes = $mainPath . "-journal\n" . $sitePath . "-journal\n";
$databases = [
    [
        'database_path' => $mainPath,
        'current_database_bytes' => $mainDatabase,
        'current_journal_bytes' => $mainJournal,
        'stale_database_bytes' => $stale2 . $dirty2,
    ],
    [
        'database_path' => $sitePath,
        'current_database_bytes' => $siteDatabase,
        'current_journal_bytes' => $siteJournal,
    ],
];
$retryWrites = [2 => $retry2, 4 => $retry4];
$cachePages = [
    ['page' => 2, 'bytes' => $pageSize, 'journaled' => true, 'image' => $clean2, 'source_id' => $recoveredSource, 'statement' => 'retry-active-plugins'],
    ['page' => 3, 'bytes' => $pageSize, 'journaled' => true, 'image' => $clean3, 'source_id' => $recoveredSource, 'statement' => 'retry-plugin-setting', 'pinned' => true],
    ['page' => 4, 'bytes' => $pageSize, 'journaled' => true, 'image' => str_repeat("\0", $pageSize), 'source_id' => $recoveredSource, 'statement' => 'append-autoload'],
];

$plan = static fn (
    array $pages = null,
    ?string $master = null,
    string $source = null,
    string $next = null,
    array $reads = null,
    string $mode = 'delete',
    bool $synced = true,
): array => SQLitePagerRollbackCacheSpillMasterCurrentSourceNextPlan::currentSourceNext(
    $masterPath,
    func_num_args() >= 2 ? $master : $masterBytes,
    $databases,
    $pageSize,
    $mainPath,
    'rewrite-active-plugins',
    $makeStack(),
    $retryWrites,
    $pages ?? $cachePages,
    7,
    3,
    $source ?? $currentSource,
    $next ?? $recoveredSource,
    $reads ?? [1, 2, 3, 4, 5],
    $mode,
    $synced,
    'reserved',
    true,
    2
);

$stalePlan = static fn (): array => $plan([
    ['page' => 2, 'bytes' => $pageSize, 'journaled' => true, 'image' => $stale2, 'stale_image' => $stale2, 'source_id' => $currentSource, 'statement' => 'stale-active-plugins'],
    ['page' => 4, 'bytes' => $pageSize, 'journaled' => true, 'image' => str_repeat("\0", $pageSize), 'source_id' => $recoveredSource, 'statement' => 'append-autoload'],
]);
$walPlan = static fn (): array => $plan([
    ['page' => 2, 'bytes' => $pageSize, 'journaled' => true, 'image' => $clean2, 'source_id' => $recoveredSource, 'statement' => 'retry-active-plugins'],
    ['page' => 4, 'bytes' => $pageSize, 'journaled' => true, 'image' => str_repeat("\0", $pageSize), 'source_id' => $recoveredSource, 'statement' => 'append-autoload'],
], $masterBytes, $currentSource, $recoveredSource, [2, 4], 'wal');
$blockedPlan = static fn (): array => $plan($cachePages, null);
$unsyncedPlan = static fn (): array => $plan($cachePages, $masterBytes, $currentSource, $recoveredSource, [2, 4], 'delete', false);

$cases = [
    'status' => [static fn (): mixed => $plan()['status'], 'pager_rollback_cache_spill_master_current_source_next121'],
    'reason' => [static fn (): mixed => $plan()['reason'], 'rollback_recovery_rekeys_cache_spill_pages_to_master_current_source'],
    'primary path' => [static fn (): mixed => $plan()['primary_database_path'], $mainPath],
    'savepoint' => [static fn (): mixed => $plan()['savepoint'], 'rewrite-active-plugins'],
    'journal mode' => [static fn (): mixed => $plan()['journal_mode'], 'delete'],
    'current source id' => [static fn (): mixed => $plan()['current_source_id'], $currentSource],
    'recovered source id' => [static fn (): mixed => $plan()['recovered_source_id'], $recoveredSource],
    'spill recovery status' => [static fn (): mixed => $plan()['spill_recovery']['status'], 'master_journal_cache_spill_savepoint_current_source_next114'],
    'spill recovery nested spill' => [static fn (): mixed => $plan()['spill_recovery']['spill']['status'], 'spilled'],
    'spilled pages' => [static fn (): mixed => $plan()['spilled_pages'], [2, 4]],
    'admitted pages' => [static fn (): mixed => $plan()['admitted_spill_pages'], [2, 4]],
    'stale pages empty' => [static fn (): mixed => $plan()['stale_cache_pages'], []],
    'mismatch pages empty' => [static fn (): mixed => $plan()['source_mismatch_pages'], []],
    'pinned pages' => [static fn (): mixed => $plan()['pinned_cache_pages'], [3]],
    'blocked reasons empty' => [static fn (): mixed => $plan()['blocked_reasons'], []],
    'admission count' => [static fn (): mixed => count($plan()['admission']), 2],
    'admission page two' => [static fn (): mixed => $plan()['admission'][0]['page'], 2],
    'admission page two source' => [static fn (): mixed => $plan()['admission'][0]['source_id'], $recoveredSource],
    'admission page two next source' => [static fn (): mixed => $plan()['admission'][0]['next_source_id'], $recoveredSource],
    'admission page two prefix' => [static fn (): mixed => $plan()['admission'][0]['prefix'], 'next121 clean active_plugins before cache spill'],
    'admission page two current' => [static fn (): mixed => $plan()['admission'][0]['matches_recovered_current'], true],
    'admission page two rollback' => [static fn (): mixed => $plan()['admission'][0]['matches_rollback_preview'], true],
    'admission page two stale false' => [static fn (): mixed => $plan()['admission'][0]['uses_stale_dirty_cache'], false],
    'admission page two admitted' => [static fn (): mixed => $plan()['admission'][0]['admitted_for_spill'], true],
    'admission page four rollback' => [static fn (): mixed => $plan()['admission'][1]['matches_rollback_preview'], true],
    'retry count' => [static fn (): mixed => count($plan()['retry_reads']), 5],
    'retry one seeded' => [static fn (): mixed => $plan()['retry_reads'][0]['cache_seeded'], true],
    'retry one source' => [static fn (): mixed => $plan()['retry_reads'][0]['source'], 'recovered-current-source'],
    'retry two source' => [static fn (): mixed => $plan()['retry_reads'][1]['source'], 'admitted-spill-cache'],
    'retry two source id' => [static fn (): mixed => $plan()['retry_reads'][1]['source_id'], $recoveredSource],
    'retry two prefix' => [static fn (): mixed => $plan()['retry_reads'][1]['image_prefix'], 'next121 clean active_plugins before cache spill'],
    'retry four source' => [static fn (): mixed => $plan()['retry_reads'][3]['source'], 'admitted-spill-cache'],
    'retry five misses' => [static fn (): mixed => $plan()['retry_reads'][4]['cache_seeded'], false],
    'retry five source' => [static fn (): mixed => $plan()['retry_reads'][4]['source'], 'pager-read-miss'],
    'operation count' => [static fn (): mixed => count($plan()['operations']), 29],
    'operation includes master recovery' => [static fn (): mixed => $plan()['operations'][0]['reason'], 'restore_current_source_database_from_master_hot_journal'],
    'operation admits page two' => [static fn (): mixed => in_array(['op' => 'admit_cache_spill_page', 'path' => $mainPath, 'page' => 2, 'source_id' => $recoveredSource, 'reason' => 'recovered_current_source_page_admitted_for_cache_spill'], $plan()['operations'], true), true],
    'operation admits page four' => [static fn (): mixed => in_array(['op' => 'admit_cache_spill_page', 'path' => $mainPath, 'page' => 4, 'source_id' => $recoveredSource, 'reason' => 'recovered_current_source_page_admitted_for_cache_spill'], $plan()['operations'], true), true],
    'operation retry seed' => [static fn (): mixed => in_array('seed_retry_cache_page', array_column($plan()['operations'], 'op'), true), true],
    'operation retry miss' => [static fn (): mixed => in_array('retry_cache_miss', array_column($plan()['operations'], 'op'), true), true],
    'dependency next121' => [static fn (): mixed => in_array('sqlite-pager-rollback-cache-spill-master-current-source-next121', $plan()['dependencies'], true), true],
    'dependency next114' => [static fn (): mixed => in_array('sqlite-pager-master-journal-cache-spill-savepoint-current-source-next114', $plan()['dependencies'], true), true],
    'dependency cache generation' => [static fn (): mixed => in_array('sqlite-pager-cache-generation-after-rollback', $plan()['dependencies'], true), true],
    'stale status' => [static fn (): mixed => $stalePlan()['status'], 'pager_rollback_cache_spill_master_current_source_blocked_next121'],
    'stale reason' => [static fn (): mixed => $stalePlan()['reason'], 'rollback_recovery_rejects_stale_cache_spill_generation'],
    'stale pages' => [static fn (): mixed => $stalePlan()['stale_cache_pages'], [2]],
    'stale mismatches' => [static fn (): mixed => $stalePlan()['source_mismatch_pages'], [2]],
    'stale blocked reasons' => [static fn (): mixed => $stalePlan()['blocked_reasons'], ['master_journal_current_source_not_verified', 'stale_cache_generation_for_spill_pages']],
    'stale admission false' => [static fn (): mixed => $stalePlan()['admission'][0]['admitted_for_spill'], false],
    'stale operation expires page' => [static fn (): mixed => in_array('expire_dirty_cache_page', array_column($stalePlan()['operations'], 'op'), true), true],
    'wal status' => [static fn (): mixed => $walPlan()['status'], 'pager_rollback_cache_spill_master_current_source_next121'],
    'wal mode' => [static fn (): mixed => $walPlan()['journal_mode'], 'wal'],
    'wal target' => [static fn (): mixed => $walPlan()['spill_recovery']['spill']['spill_target'], 'wal_frames'],
    'wal admitted pages' => [static fn (): mixed => $walPlan()['admitted_spill_pages'], [2, 4]],
    'blocked status' => [static fn (): mixed => $blockedPlan()['status'], 'pager_rollback_cache_spill_master_current_source_blocked_next121'],
    'blocked reasons' => [static fn (): mixed => $blockedPlan()['blocked_reasons'], ['master_journal_current_source_not_verified', 'stale_cache_generation_for_spill_pages']],
    'unsynced status' => [static fn (): mixed => $unsyncedPlan()['status'], 'pager_rollback_cache_spill_master_current_source_blocked_next121'],
    'unsynced reasons' => [static fn (): mixed => $unsyncedPlan()['blocked_reasons'], ['master_journal_current_source_not_verified', 'cache_spill_deferred']],
];

foreach ($cases as $name => [$callback, $expected]) {
    $tests['pager rollback cache spill master current source next121 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        $t->same($expected, $callback());
    };
}

$throws = [
    'empty cache rejected' => static fn () => $plan([]),
    'bad page rejected' => static fn () => $plan([['page' => 0, 'image' => $clean2, 'source_id' => $recoveredSource]]),
    'duplicate page rejected' => static fn () => $plan([$cachePages[0], $cachePages[0]]),
    'short image rejected' => static fn () => $plan([['page' => 2, 'image' => 'short', 'source_id' => $recoveredSource]]),
    'empty source rejected' => static fn () => $plan([['page' => 2, 'image' => $clean2, 'source_id' => '']]),
    'short stale rejected' => static fn () => $plan([['page' => 2, 'image' => $clean2, 'source_id' => $recoveredSource, 'stale_image' => 'short']]),
    'empty statement rejected' => static fn () => $plan([['page' => 2, 'image' => $clean2, 'source_id' => $recoveredSource, 'statement' => '']]),
    'empty current source rejected' => static fn () => $plan($cachePages, $masterBytes, ''),
    'empty next source rejected' => static fn () => $plan($cachePages, $masterBytes, $currentSource, ''),
    'same source rejected' => static fn () => $plan($cachePages, $masterBytes, $currentSource, $currentSource),
    'empty retry reads rejected' => static fn () => $plan($cachePages, $masterBytes, $currentSource, $recoveredSource, []),
    'bad retry read rejected' => static fn () => $plan($cachePages, $masterBytes, $currentSource, $recoveredSource, [0]),
    'bad journal mode rejected' => static fn () => $plan($cachePages, $masterBytes, $currentSource, $recoveredSource, [2], 'bad'),
];

foreach ($throws as $name => $callback) {
    $tests['pager rollback cache spill master current source next121 ' . $name] = static function (TestRunner $t) use ($callback): void {
        $t->throws(Throwable::class, $callback);
    };
}

return $tests;
