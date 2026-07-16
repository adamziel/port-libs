<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePagerMasterJournalCacheCurrentNextPlan;
use PortLibs\LibSqlite\SQLiteRollbackJournal;
use PortLibs\LibSqlite\SQLiteRollbackJournalHeader;

$tests = [];

$pageSize = 512;
$sectorSize = 512;
$masterPath = '/srv/www/wp-content/database/.ht.sqlite-mj77';
$mainDb = '/srv/www/wp-content/database/wp.sqlite';
$metaDb = '/srv/www/wp-content/database/wp_meta.sqlite';
$cacheDb = '/srv/www/wp-content/database/wp_cache.sqlite';
$orphanDb = '/srv/www/wp-content/database/wp_orphan.sqlite';
$mainJournalPath = $mainDb . '-journal';
$metaJournalPath = $metaDb . '-journal';
$cacheJournalPath = $cacheDb . '-journal';
$orphanJournalPath = $orphanDb . '-journal';
$page = static fn (string $label): string => str_pad($label, $pageSize, '.', STR_PAD_RIGHT);

$makeJournalBytes = static function (array $pages, int $initialPageCount, int $nonce) use ($sectorSize, $pageSize): string {
    $header = SQLiteRollbackJournalHeader::MAGIC . pack('N*', count($pages), $nonce, $initialPageCount, $sectorSize, $pageSize);
    $bytes = str_pad($header, $sectorSize, "\0");
    foreach ($pages as $pageNumber => $image) {
        $bytes .= pack('N', $pageNumber) . $image . pack('N', SQLiteRollbackJournal::pageChecksum($image, $nonce));
    }

    return $bytes;
};

$mainJournalBytes = $makeJournalBytes([1 => $page('main clean schema before crash'), 2 => $page('main clean wp_options before crash')], 2, 0x77010001);
$metaJournalBytes = $makeJournalBytes([1 => $page('meta clean schema before crash'), 2 => $page('meta clean rows before crash')], 2, 0x77010002);
$cacheJournalBytes = $makeJournalBytes([1 => $page('cache clean schema before crash')], 1, 0x77010003);
$orphanJournalBytes = $makeJournalBytes([1 => $page('orphan clean schema before crash')], 1, 0x77010004);
$currentMaster = $mainJournalPath . "\n" . $metaJournalPath . "\n" . $mainJournalPath . "\n";
$nextMaster = $metaJournalPath . "\n" . $cacheJournalPath . "\n";

$journals = [
    [
        'database_path' => $mainDb,
        'current_journal_bytes' => $mainJournalBytes,
        'next_journal_bytes' => null,
    ],
    [
        'journal_path' => $metaJournalPath,
        'database_path' => $metaDb,
        'current_journal_bytes' => $metaJournalBytes,
        'next_journal_bytes' => $metaJournalBytes,
        'current_reserved_lock' => false,
        'next_reserved_lock' => true,
    ],
    [
        'database_path' => $cacheDb,
        'current_journal_bytes' => null,
        'next_journal_bytes' => $cacheJournalBytes,
    ],
    [
        'database_path' => $orphanDb,
        'current_journal_bytes' => $orphanJournalBytes,
        'next_journal_bytes' => $orphanJournalBytes,
    ],
];

$refresh = static fn (): array => SQLitePagerMasterJournalCacheCurrentNextPlan::currentNext($masterPath, $currentMaster, $nextMaster, $journals);
$cleared = static fn (): array => SQLitePagerMasterJournalCacheCurrentNextPlan::currentNext($masterPath, $currentMaster, null, $journals);
$current = static fn (): array => SQLitePagerMasterJournalCacheCurrentNextPlan::currentNext($masterPath, $currentMaster, $currentMaster, $journals);
$created = static fn (): array => SQLitePagerMasterJournalCacheCurrentNextPlan::currentNext($masterPath, null, $cacheJournalPath . "\n", [$journals[2]]);
$missingJournals = static fn (): array => SQLitePagerMasterJournalCacheCurrentNextPlan::currentNext($masterPath, $currentMaster, $nextMaster, []);

$cases = [
    'refresh status' => static fn (): mixed => $refresh()['status'],
    'refresh reason' => static fn (): mixed => $refresh()['reason'],
    'refresh path' => static fn (): mixed => $refresh()['master_journal_path'],
    'refresh invalidates cache' => static fn (): mixed => $refresh()['cache_invalidated'],
    'current exists' => static fn (): mixed => $refresh()['current']['exists'],
    'next exists' => static fn (): mixed => $refresh()['next']['exists'],
    'current deduped member count' => static fn (): mixed => $refresh()['current']['member_count'],
    'next member count' => static fn (): mixed => $refresh()['next']['member_count'],
    'current first member' => static fn (): mixed => $refresh()['current']['members'][0],
    'current second member' => static fn (): mixed => $refresh()['current']['members'][1],
    'next first member' => static fn (): mixed => $refresh()['next']['members'][0],
    'next second member' => static fn (): mixed => $refresh()['next']['members'][1],
    'current hot candidate count' => static fn (): mixed => $refresh()['current']['hot_candidate_count'],
    'next hot candidate count' => static fn (): mixed => $refresh()['next']['hot_candidate_count'],
    'delta added cache journal' => static fn (): mixed => $refresh()['member_delta']['added'],
    'delta removed main journal' => static fn (): mixed => $refresh()['member_delta']['removed'],
    'delta retained meta journal' => static fn (): mixed => $refresh()['member_delta']['retained'],
    'first operation invalidates cache' => static fn (): mixed => $refresh()['operations'][0]['op'],
    'first operation reason' => static fn (): mixed => $refresh()['operations'][0]['reason'],
    'operation carries current cache key' => static fn (): mixed => is_string($refresh()['operations'][0]['current_cache_key']),
    'operation carries next cache key' => static fn (): mixed => is_string($refresh()['operations'][0]['next_cache_key']),
    'main database inferred' => static fn (): mixed => $refresh()['journal_rechecks'][$mainJournalPath]['database_path'],
    'main current member' => static fn (): mixed => $refresh()['journal_rechecks'][$mainJournalPath]['current_member'],
    'main next not member' => static fn (): mixed => $refresh()['journal_rechecks'][$mainJournalPath]['next_member'],
    'main current journal exists' => static fn (): mixed => $refresh()['journal_rechecks'][$mainJournalPath]['current_journal_exists'],
    'main next journal missing' => static fn (): mixed => $refresh()['journal_rechecks'][$mainJournalPath]['next_journal_exists'],
    'main current hot' => static fn (): mixed => $refresh()['journal_rechecks'][$mainJournalPath]['current_hot']['hot'],
    'main next hot false' => static fn (): mixed => $refresh()['journal_rechecks'][$mainJournalPath]['next_hot']['hot'],
    'main next reason missing' => static fn (): mixed => $refresh()['journal_rechecks'][$mainJournalPath]['next_hot']['reason'],
    'main action clears cached hot journal' => static fn (): mixed => $refresh()['journal_rechecks'][$mainJournalPath]['cache_action'],
    'meta retained current member' => static fn (): mixed => $refresh()['journal_rechecks'][$metaJournalPath]['current_member'],
    'meta retained next member' => static fn (): mixed => $refresh()['journal_rechecks'][$metaJournalPath]['next_member'],
    'meta current hot' => static fn (): mixed => $refresh()['journal_rechecks'][$metaJournalPath]['current_hot']['hot'],
    'meta next reserved not hot' => static fn (): mixed => $refresh()['journal_rechecks'][$metaJournalPath]['next_hot']['hot'],
    'meta next reason reserved lock' => static fn (): mixed => $refresh()['journal_rechecks'][$metaJournalPath]['next_hot']['reason'],
    'meta action refreshes reason' => static fn (): mixed => $refresh()['journal_rechecks'][$metaJournalPath]['cache_action'],
    'cache current not member' => static fn (): mixed => $refresh()['journal_rechecks'][$cacheJournalPath]['current_member'],
    'cache next member' => static fn (): mixed => $refresh()['journal_rechecks'][$cacheJournalPath]['next_member'],
    'cache current missing reason' => static fn (): mixed => $refresh()['journal_rechecks'][$cacheJournalPath]['current_hot']['reason'],
    'cache next hot' => static fn (): mixed => $refresh()['journal_rechecks'][$cacheJournalPath]['next_hot']['hot'],
    'cache action candidate new hot' => static fn (): mixed => $refresh()['journal_rechecks'][$cacheJournalPath]['cache_action'],
    'orphan current not member' => static fn (): mixed => $refresh()['journal_rechecks'][$orphanJournalPath]['current_member'],
    'orphan next not member' => static fn (): mixed => $refresh()['journal_rechecks'][$orphanJournalPath]['next_member'],
    'orphan reason missing super' => static fn (): mixed => $refresh()['journal_rechecks'][$orphanJournalPath]['current_hot']['reason'],
    'orphan action reuses non hot' => static fn (): mixed => $refresh()['journal_rechecks'][$orphanJournalPath]['cache_action'],
    'recheck operations skip orphan reuse' => static fn (): mixed => count($refresh()['operations']),
    'dependencies include slice' => static fn (): mixed => in_array('sqlite-pager-master-journal-cache-current-next77', $refresh()['dependencies'], true),
    'dependencies include hot candidate' => static fn (): mixed => in_array('sqlite-rollback-journal-hot-candidate', $refresh()['dependencies'], true),
    'cleared status' => static fn (): mixed => $cleared()['status'],
    'cleared next exists false' => static fn (): mixed => $cleared()['next']['exists'],
    'cleared removed both members' => static fn (): mixed => $cleared()['member_delta']['removed'],
    'cleared next member count zero' => static fn (): mixed => $cleared()['next']['member_count'],
    'current status unchanged' => static fn (): mixed => $current()['status'],
    'current cache not invalidated' => static fn (): mixed => $current()['cache_invalidated'],
    'current retained both members' => static fn (): mixed => $current()['member_delta']['retained'],
    'created status' => static fn (): mixed => $created()['status'],
    'created current exists false' => static fn (): mixed => $created()['current']['exists'],
    'created added cache member' => static fn (): mixed => $created()['member_delta']['added'],
    'created cache action candidate' => static fn (): mixed => $created()['journal_rechecks'][$cacheJournalPath]['cache_action'],
    'missing journal list still tracks members' => static fn (): mixed => count($missingJournals()['journal_rechecks']),
    'missing journal list main reason missing' => static fn (): mixed => $missingJournals()['journal_rechecks'][$mainJournalPath]['current_hot']['reason'],
    'empty master path rejected' => static function () use ($currentMaster): mixed {
        try {
            SQLitePagerMasterJournalCacheCurrentNextPlan::currentNext('', $currentMaster, null, []);
        } catch (InvalidArgumentException) {
            return 'rejected';
        }
        return 'accepted';
    },
    'journal input path required' => static function () use ($masterPath, $currentMaster): mixed {
        try {
            SQLitePagerMasterJournalCacheCurrentNextPlan::currentNext($masterPath, $currentMaster, null, [['current_journal_bytes' => 'x']]);
        } catch (InvalidArgumentException) {
            return 'rejected';
        }
        return 'accepted';
    },
    'duplicate journal input rejected' => static function () use ($masterPath, $currentMaster, $mainDb): mixed {
        try {
            SQLitePagerMasterJournalCacheCurrentNextPlan::currentNext($masterPath, $currentMaster, null, [['database_path' => $mainDb], ['database_path' => $mainDb]]);
        } catch (InvalidArgumentException) {
            return 'rejected';
        }
        return 'accepted';
    },
];

$expected = [
    'refresh status' => 'master_journal_cache_refreshed_current_next',
    'refresh reason' => 'pager_hot_journal_master_cache_current_next77',
    'refresh path' => $masterPath,
    'refresh invalidates cache' => true,
    'current exists' => true,
    'next exists' => true,
    'current deduped member count' => 2,
    'next member count' => 2,
    'current first member' => $mainJournalPath,
    'current second member' => $metaJournalPath,
    'next first member' => $metaJournalPath,
    'next second member' => $cacheJournalPath,
    'current hot candidate count' => 2,
    'next hot candidate count' => 1,
    'delta added cache journal' => [$cacheJournalPath],
    'delta removed main journal' => [$mainJournalPath],
    'delta retained meta journal' => [$metaJournalPath],
    'first operation invalidates cache' => 'invalidate_master_journal_cache',
    'first operation reason' => 'master_journal_membership_changed_between_current_and_next',
    'operation carries current cache key' => true,
    'operation carries next cache key' => true,
    'main database inferred' => $mainDb,
    'main current member' => true,
    'main next not member' => false,
    'main current journal exists' => true,
    'main next journal missing' => false,
    'main current hot' => true,
    'main next hot false' => false,
    'main next reason missing' => 'journal_missing',
    'main action clears cached hot journal' => 'clear_cached_hot_journal',
    'meta retained current member' => true,
    'meta retained next member' => true,
    'meta current hot' => true,
    'meta next reserved not hot' => false,
    'meta next reason reserved lock' => 'database_has_reserved_lock',
    'meta action refreshes reason' => 'refresh_hot_journal_reason',
    'cache current not member' => false,
    'cache next member' => true,
    'cache current missing reason' => 'journal_missing',
    'cache next hot' => true,
    'cache action candidate new hot' => 'candidate_new_hot_journal',
    'orphan current not member' => false,
    'orphan next not member' => false,
    'orphan reason missing super' => 'missing_super_journal',
    'orphan action reuses non hot' => 'reuse_cached_non_hot_state',
    'recheck operations skip orphan reuse' => 4,
    'dependencies include slice' => true,
    'dependencies include hot candidate' => true,
    'cleared status' => 'master_journal_cache_cleared_current_next',
    'cleared next exists false' => false,
    'cleared removed both members' => [$mainJournalPath, $metaJournalPath],
    'cleared next member count zero' => 0,
    'current status unchanged' => 'master_journal_cache_current',
    'current cache not invalidated' => false,
    'current retained both members' => [$mainJournalPath, $metaJournalPath],
    'created status' => 'master_journal_cache_refreshed_current_next',
    'created current exists false' => false,
    'created added cache member' => [$cacheJournalPath],
    'created cache action candidate' => 'candidate_new_hot_journal',
    'missing journal list still tracks members' => 3,
    'missing journal list main reason missing' => 'journal_missing',
    'empty master path rejected' => 'rejected',
    'journal input path required' => 'rejected',
    'duplicate journal input rejected' => 'rejected',
];

foreach ($cases as $name => $callback) {
    $tests['pager master-journal cache current next77 ' . $name] = static function (TestRunner $t) use ($callback, $expected, $name): void {
        $t->same($expected[$name], $callback());
    };
}

return $tests;
