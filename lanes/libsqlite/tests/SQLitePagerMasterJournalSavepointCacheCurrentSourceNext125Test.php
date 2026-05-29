<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePagerMasterJournalSavepointCacheCurrentSourceNextPlan;
use PortLibs\LibSqlite\SQLiteRollbackJournal;
use PortLibs\LibSqlite\SQLiteRollbackJournalHeader;
use PortLibs\LibSqlite\SQLiteSavepointStack;

$tests = [];

$pageSize = 512;
$sectorSize = 512;
$mainPath = '/srv/wp/database/main.sqlite';
$sitePath = '/srv/wp/database/site.sqlite';
$masterPath = '/srv/wp/database/main.sqlite-mj125';
$currentSourceId = 'cached-master-source-before-crash';
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
    $stack->beginTransaction('wp-options-cache-next125');
    $stack->recordPageImageWrite(1, $page('next125 outer schema before plugin retry'));
    $stack->savepoint('active-plugins');
    $stack->recordPageImageWrite(3, $page('next125 plugin settings before retry'));

    return $stack;
};

$mainClean1 = $page('next125 clean main schema from current master');
$mainClean2 = $page('next125 clean active_plugins from current master');
$mainClean3 = $page('next125 clean plugin settings from savepoint');
$mainDirty1 = $page('next125 dirty main schema after crash');
$mainDirty2 = $page('next125 dirty active_plugins after crash');
$mainDirty3 = $page('next125 dirty plugin settings after crash');
$siteClean1 = $page('next125 clean attached site schema');
$siteDirty1 = $page('next125 dirty attached site schema');
$retry2 = $page('next125 retry active_plugins write after recovery');
$retry4 = $page('next125 retry autoload index append');
$stale2 = $page('next125 stale cached active_plugins page');
$stale5 = $page('next125 stale cached missing extension page');

$mainJournal = $makeJournal([1 => $mainClean1, 2 => $mainClean2, 3 => $mainClean3], 3, 0x12500001);
$siteJournal = $makeJournal([1 => $siteClean1], 1, 0x12500002);
$staleJournal = $makeJournal([1 => $page('next125 stale cached single journal')], 1, 0x1250abcd);
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
$cachePages = [
    1 => ['image' => $mainClean1, 'source' => 'database', 'source_id' => $currentSourceId, 'epoch' => 7],
    2 => ['image' => $stale2, 'source' => 'stale-pager-cache', 'source_id' => $currentSourceId, 'epoch' => 7],
    3 => ['image' => $mainClean3, 'source' => 'dirty-savepoint-cache', 'source_id' => $currentSourceId, 'epoch' => 7, 'dirty' => true],
    4 => ['image' => $retry4, 'source' => 'retry-write-cache', 'source_id' => 'old-master-source', 'epoch' => 7],
    5 => ['image' => $stale5, 'source' => 'stale-missing-page', 'source_id' => $currentSourceId, 'epoch' => 6],
];

$plan = static fn (?string $cached = null, ?string $current = null, ?array $cache = null, array $reads = [1, 2, 3, 4, 5]): array => SQLitePagerMasterJournalSavepointCacheCurrentSourceNextPlan::currentSourceNext125(
    $masterPath,
    func_num_args() >= 1 ? $cached : $cachedMaster,
    func_num_args() >= 2 ? $current : $currentMaster,
    $databases,
    $pageSize,
    $mainPath,
    'active-plugins',
    $makeStack(),
    $retryWrites,
    $cache ?? $cachePages,
    $reads,
    $currentSourceId,
    7
);

$cases = [
    'status' => static fn (): mixed => $plan()['status'],
    'reason' => static fn (): mixed => $plan()['reason'],
    'master path' => static fn (): mixed => $plan()['master_journal_path'],
    'primary path' => static fn (): mixed => $plan()['primary_database_path'],
    'savepoint' => static fn (): mixed => $plan()['savepoint'],
    'current source id' => static fn (): mixed => $plan()['current_source']['id'],
    'current source epoch' => static fn (): mixed => $plan()['current_source']['epoch'],
    'next source epoch' => static fn (): mixed => $plan()['next_source']['epoch'],
    'next source id prefix' => static fn (): mixed => str_starts_with($plan()['next_source']['id'], 'master-journal:'),
    'source verified' => static fn (): mixed => $plan()['current_source_verified'],
    'cache stale rejected' => static fn (): mixed => $plan()['cache_stale_rejected'],
    'recovery status' => static fn (): mixed => $plan()['recovery']['status'],
    'recovery recovered count' => static fn (): mixed => $plan()['recovery']['current_recovered_database_count'],
    'recovery cache rejected' => static fn (): mixed => $plan()['recovery']['cache_stale_rejected'],
    'invalidated pages' => static fn (): mixed => $plan()['cache']['invalidated_page_numbers'],
    'invalid stale image reason' => static fn (): mixed => $plan()['cache']['invalidated_entries'][0]['reason'],
    'invalid dirty reason' => static fn (): mixed => $plan()['cache']['invalidated_entries'][1]['reason'],
    'invalid stale source reason' => static fn (): mixed => $plan()['cache']['invalidated_entries'][2]['reason'],
    'invalid stale epoch reason' => static fn (): mixed => $plan()['cache']['invalidated_entries'][3]['reason'],
    'installed pages' => static fn (): mixed => $plan()['cache']['installed_page_numbers'],
    'final pages' => static fn (): mixed => $plan()['cache']['final_page_numbers'],
    'final page one source' => static fn (): mixed => $plan()['cache']['final_sources'][1],
    'final page two source' => static fn (): mixed => $plan()['cache']['final_sources'][2],
    'final page three source' => static fn (): mixed => $plan()['cache']['final_sources'][3],
    'final page four source' => static fn (): mixed => $plan()['cache']['final_sources'][4],
    'final page one source id advanced' => static fn (): mixed => $plan()['cache']['final_source_ids'][1] === $plan()['next_source']['id'],
    'final page four source id advanced' => static fn (): mixed => $plan()['cache']['final_source_ids'][4] === $plan()['next_source']['id'],
    'dirty pages empty' => static fn (): mixed => $plan()['cache']['dirty_page_numbers'],
    'release read count' => static fn (): mixed => count($plan()['release_reads']),
    'release page one hit' => static fn (): mixed => $plan()['release_reads'][0]['cache_hit'],
    'release page one prefix' => static fn (): mixed => $plan()['release_reads'][0]['prefix'],
    'release page two hit' => static fn (): mixed => $plan()['release_reads'][1]['cache_hit'],
    'release page two prefix' => static fn (): mixed => $plan()['release_reads'][1]['prefix'],
    'release page three hit' => static fn (): mixed => $plan()['release_reads'][2]['cache_hit'],
    'release page three prefix' => static fn (): mixed => $plan()['release_reads'][2]['prefix'],
    'release page four hit' => static fn (): mixed => $plan()['release_reads'][3]['cache_hit'],
    'release page four prefix' => static fn (): mixed => $plan()['release_reads'][3]['prefix'],
    'release page five miss' => static fn (): mixed => $plan()['release_reads'][4]['cache_hit'],
    'release page five zero fill' => static fn (): mixed => $plan()['release_reads'][4]['zero_filled_short_read'],
    'operation invalidation present' => static fn (): mixed => in_array('invalidate_master_journal_savepoint_cache_page', array_column($plan()['operations'], 'op'), true),
    'operation install present' => static fn (): mixed => in_array('install_master_journal_savepoint_cache_page', array_column($plan()['operations'], 'op'), true),
    'operation release hit present' => static fn (): mixed => in_array('release_read_master_journal_cache_hit', array_column($plan()['operations'], 'op'), true),
    'operation release miss present' => static fn (): mixed => in_array('release_read_master_journal_cache_miss', array_column($plan()['operations'], 'op'), true),
    'payload final exists' => static fn (): mixed => isset($plan()['payloads'][$mainPath . '#master-savepoint-current-source-next108']),
    'payload rollback exists' => static fn (): mixed => isset($plan()['payloads'][$mainPath . '#master-savepoint-rollback-preview-next108']),
    'dependency marker' => static fn (): mixed => in_array('sqlite-pager-master-journal-savepoint-cache-current-source-next125', $plan()['dependencies'], true),
    'dependency recovery next122' => static fn (): mixed => in_array('sqlite-pager-master-journal-cache-recovery-current-source-next122', $plan()['dependencies'], true),
    'dependency cache token' => static fn (): mixed => in_array('sqlite-pager-cache-current-source-token', $plan()['dependencies'], true),
    'unchanged source still works' => static fn (): mixed => $plan($currentMaster, $currentMaster)['status'],
    'missing current blocked' => static fn (): mixed => $plan($cachedMaster, null)['status'],
    'missing current source false' => static fn (): mixed => $plan($cachedMaster, null)['current_source_verified'],
    'missing current invalid reason' => static fn (): mixed => $plan($cachedMaster, null)['cache']['invalidated_entries'][0]['reason'],
    'custom read subset' => static fn (): mixed => array_column($plan(null, null, null, [2, 4])['release_reads'], 'page_number'),
    'valid retry cache retained then source advanced' => static fn (): mixed => $plan($cachedMaster, $currentMaster, [4 => ['image' => $retry4, 'source' => 'retry-final', 'source_id' => $currentSourceId, 'epoch' => 7]], [4])['cache']['final_sources'][4],
];

$expected = [
    'status' => 'pager_master_journal_savepoint_cache_current_source_next125',
    'reason' => 'savepoint_cache_pages_rebased_to_master_journal_current_source',
    'master path' => $masterPath,
    'primary path' => $mainPath,
    'savepoint' => 'active-plugins',
    'current source id' => $currentSourceId,
    'current source epoch' => 7,
    'next source epoch' => 8,
    'next source id prefix' => true,
    'source verified' => true,
    'cache stale rejected' => true,
    'recovery status' => 'master_journal_cache_recovery_current_source_next122',
    'recovery recovered count' => 2,
    'recovery cache rejected' => true,
    'invalidated pages' => [2, 3, 4, 5],
    'invalid stale image reason' => 'cached_image_not_from_recovered_current_source',
    'invalid dirty reason' => 'dirty_cache_from_aborted_savepoint_retry',
    'invalid stale source reason' => 'stale_current_source_id',
    'invalid stale epoch reason' => 'stale_current_source_epoch',
    'installed pages' => [2, 4, 3],
    'final pages' => [1, 2, 3, 4],
    'final page one source' => 'database',
    'final page two source' => 'master-journal-savepoint-before-image',
    'final page three source' => 'master-journal-savepoint-rollback-image',
    'final page four source' => 'master-journal-savepoint-before-image',
    'final page one source id advanced' => true,
    'final page four source id advanced' => true,
    'dirty pages empty' => [],
    'release read count' => 5,
    'release page one hit' => true,
    'release page one prefix' => 'next125 clean main schema from current master',
    'release page two hit' => true,
    'release page two prefix' => 'next125 clean active_plugins from current master',
    'release page three hit' => true,
    'release page three prefix' => 'next125 plugin settings before retry',
    'release page four hit' => true,
    'release page four prefix' => '',
    'release page five miss' => false,
    'release page five zero fill' => true,
    'operation invalidation present' => true,
    'operation install present' => true,
    'operation release hit present' => true,
    'operation release miss present' => true,
    'payload final exists' => true,
    'payload rollback exists' => true,
    'dependency marker' => true,
    'dependency recovery next122' => true,
    'dependency cache token' => true,
    'unchanged source still works' => 'pager_master_journal_savepoint_cache_current_source_next125',
    'missing current blocked' => 'pager_master_journal_savepoint_cache_current_source_blocked_next125',
    'missing current source false' => false,
    'missing current invalid reason' => 'master_journal_current_source_unverified',
    'custom read subset' => [2, 4],
    'valid retry cache retained then source advanced' => 'master-journal-savepoint-before-image',
];

foreach ($cases as $name => $callback) {
    $tests['pager master journal savepoint cache current source next125 ' . $name] = static function (TestRunner $t) use ($callback, $expected, $name): void {
        $t->same($expected[$name], $callback());
    };
}

$throws = [
    'empty current source rejected' => static fn () => SQLitePagerMasterJournalSavepointCacheCurrentSourceNextPlan::currentSourceNext125($masterPath, $cachedMaster, $currentMaster, $databases, $pageSize, $mainPath, 'active-plugins', $makeStack(), $retryWrites, $cachePages, [1], ''),
    'zero epoch rejected' => static fn () => SQLitePagerMasterJournalSavepointCacheCurrentSourceNextPlan::currentSourceNext125($masterPath, $cachedMaster, $currentMaster, $databases, $pageSize, $mainPath, 'active-plugins', $makeStack(), $retryWrites, $cachePages, [1], $currentSourceId, 0),
    'empty cache rejected' => static fn () => SQLitePagerMasterJournalSavepointCacheCurrentSourceNextPlan::currentSourceNext125($masterPath, $cachedMaster, $currentMaster, $databases, $pageSize, $mainPath, 'active-plugins', $makeStack(), $retryWrites, [], [1], $currentSourceId),
    'empty read list rejected' => static fn () => SQLitePagerMasterJournalSavepointCacheCurrentSourceNextPlan::currentSourceNext125($masterPath, $cachedMaster, $currentMaster, $databases, $pageSize, $mainPath, 'active-plugins', $makeStack(), $retryWrites, $cachePages, [], $currentSourceId),
    'bad cache page rejected' => static fn () => SQLitePagerMasterJournalSavepointCacheCurrentSourceNextPlan::currentSourceNext125($masterPath, $cachedMaster, $currentMaster, $databases, $pageSize, $mainPath, 'active-plugins', $makeStack(), $retryWrites, [0 => ['image' => $mainClean1]], [1], $currentSourceId),
    'short cache image rejected' => static fn () => SQLitePagerMasterJournalSavepointCacheCurrentSourceNextPlan::currentSourceNext125($masterPath, $cachedMaster, $currentMaster, $databases, $pageSize, $mainPath, 'active-plugins', $makeStack(), $retryWrites, [1 => ['image' => 'short']], [1], $currentSourceId),
    'bad read page rejected' => static fn () => SQLitePagerMasterJournalSavepointCacheCurrentSourceNextPlan::currentSourceNext125($masterPath, $cachedMaster, $currentMaster, $databases, $pageSize, $mainPath, 'active-plugins', $makeStack(), $retryWrites, $cachePages, [0], $currentSourceId),
    'bad recovery savepoint rejected' => static fn () => SQLitePagerMasterJournalSavepointCacheCurrentSourceNextPlan::currentSourceNext125($masterPath, $cachedMaster, $currentMaster, $databases, $pageSize, $mainPath, '', $makeStack(), $retryWrites, $cachePages, [1], $currentSourceId),
];

foreach ($throws as $name => $callback) {
    $tests['pager master journal savepoint cache current source next125 ' . $name] = static function (TestRunner $t) use ($callback): void {
        $t->throws(Throwable::class, $callback);
    };
}

return $tests;
