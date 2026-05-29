<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePagerCacheSpillHotJournalReaderCurrentSourceNextPlan;
use PortLibs\LibSqlite\SQLiteRollbackJournal;
use PortLibs\LibSqlite\SQLiteRollbackJournalHeader;
use PortLibs\LibSqlite\SQLiteWal;
use PortLibs\LibSqlite\SQLiteWalHeader;

$tests = [];

$pageSize = 512;
$sectorSize = 512;
$databasePath = '/srv/www/wp-content/database/wp-next147.sqlite';
$page = static fn (string $label): string => str_pad($label, $pageSize, '.', STR_PAD_RIGHT);

$cleanPages = [
    1 => $page('next147 hot recovered sqlite header'),
    2 => $page('next147 hot recovered wp_options root'),
    3 => $page('next147 hot recovered active_plugins'),
    4 => $page('next147 hot recovered autoload index'),
    5 => $page('next147 hot recovered transient rows'),
    6 => $page('next147 hot recovered rewrite rules'),
];
$dirtyDatabase = $page('next147 dirty sqlite header')
    . $page('next147 dirty wp_options root')
    . $page('next147 dirty active_plugins')
    . $page('next147 dirty autoload index')
    . $page('next147 dirty transient rows')
    . $page('next147 dirty rewrite rules');

$makeJournalBytes = static function (array $pages, int $nonce = 0x2026147) use ($sectorSize, $pageSize): string {
    $header = SQLiteRollbackJournalHeader::MAGIC . pack('N*', count($pages), $nonce, count($pages), $sectorSize, $pageSize);
    $bytes = str_pad($header, $sectorSize, "\0");
    foreach ($pages as $pageNumber => $pageImage) {
        $bytes .= pack('N', $pageNumber) . $pageImage . pack('N', SQLiteRollbackJournal::pageChecksum($pageImage, $nonce));
    }

    return $bytes;
};

$makeWalBytes = static function (array $frames, int $checkpoint, int $salt1, int $salt2) use ($pageSize, $page): string {
    $prefix = pack('N*', SQLiteWalHeader::MAGIC_BIG_ENDIAN, 3007000, $pageSize, $checkpoint, $salt1, $salt2);
    $seed = SQLiteWal::checksumPair($prefix, false);
    $bytes = $prefix . pack('N*', $seed[0], $seed[1]);
    foreach ($frames as [$pageNumber, $commitPageCount, $label]) {
        $image = $page($label);
        $framePrefix = pack('N*', $pageNumber, $commitPageCount, $salt1, $salt2);
        $seed = SQLiteWal::checksumPair(substr($framePrefix, 0, 8) . $image, false, $seed[0], $seed[1]);
        $bytes .= $framePrefix . pack('N*', $seed[0], $seed[1]) . $image;
    }

    return $bytes;
};

$journalBytes = $makeJournalBytes($cleanPages);
$currentWalBytes = $makeWalBytes([
    [1, 0, 'next147 current schema reader draft'],
    [2, 6, 'next147 current wp_options reader commit'],
    [3, 0, 'next147 current active_plugins reader draft'],
    [4, 6, 'next147 current autoload reader commit'],
    [5, 0, 'next147 current transient reader draft'],
], 147, 0x14714701, 0x14714702);
$restartedWalBytes = $makeWalBytes([
    [2, 0, 'next147 restarted wp_options next draft'],
    [5, 0, 'next147 restarted transient next draft'],
    [6, 6, 'next147 restarted rewrite next commit'],
], 148, 0x14714801, 0x14714802);
$currentWal = SQLiteWal::parse($currentWalBytes, $pageSize, true);

$currentImage = static function (int $frameIndex) use ($currentWal): string {
    return $currentWal->frames[$frameIndex - 1]->pageImage;
};
$nextImage = static function (int $frameIndex) use ($restartedWalBytes, $pageSize): string {
    return SQLiteWal::parse($restartedWalBytes, $pageSize, true)->frames[$frameIndex - 1]->pageImage;
};

$cachePages = [
    ['page' => 2, 'image' => $page('next147 retry options cache spill'), 'current_image' => $currentImage(2), 'bytes' => $pageSize, 'walFrame' => 2],
    ['page' => 3, 'image' => $page('next147 retry active_plugins spill'), 'current_image' => $currentImage(3), 'bytes' => $pageSize, 'walFrame' => 3],
    ['page' => 4, 'image' => $page('next147 reader pinned autoload spill'), 'current_image' => $currentImage(4), 'bytes' => $pageSize, 'walFrame' => 4, 'readerPinned' => true],
    ['page' => 5, 'image' => $nextImage(2), 'current_image' => $currentImage(5), 'bytes' => $pageSize, 'walFrame' => 5, 'nextGeneration' => true],
    ['page' => 6, 'image' => $page('next147 rewrite rules cache spill'), 'current_image' => $cleanPages[6], 'bytes' => $pageSize, 'journaled' => true],
];

$plan = static fn (
    ?array $pages = null,
    int $readerEndFrame = 5,
    ?string $nextBytes = null,
    bool $reservedLock = false,
    bool $synced = true,
    bool $enabled = true,
    ?int $limit = null,
    ?string $path = null,
    ?string $databaseBytes = null,
    ?string $journal = null
): array => SQLitePagerCacheSpillHotJournalReaderCurrentSourceNextPlan::plan(
    $path ?? $databasePath,
    $databaseBytes ?? $dirtyDatabase,
    $journal ?? $journalBytes,
    $currentWal,
    $currentWalBytes,
    $nextBytes ?? $restartedWalBytes,
    $pages ?? $cachePages,
    [1, 2, 3, 4, 5, 6],
    $readerEndFrame,
    8,
    3,
    $reservedLock,
    $synced,
    $enabled,
    $limit
);

$full = static fn (): array => $plan();
$blocked = static fn (): array => $plan(null, 5, null, true);
$allRejected = static fn (): array => $plan([
    ['page' => 4, 'image' => $page('next147 pinned only'), 'current_image' => $currentImage(4), 'readerPinned' => true],
    ['page' => 5, 'image' => $nextImage(2), 'current_image' => $currentImage(5), 'nextGeneration' => true],
]);

$cases = [
    'status' => [static fn (): mixed => $full()['status'], 'pager_cache_spill_hot_journal_reader_current_source_next147'],
    'reason' => [static fn (): mixed => $full()['reason'], 'cache_spill_uses_hot_journal_reader_current_source_before_next_wal_generation'],
    'database path' => [static fn (): mixed => $full()['database_path'], $databasePath],
    'journal path' => [static fn (): mixed => $full()['journal_path'], $databasePath . '-journal'],
    'wal path' => [static fn (): mixed => $full()['wal_path'], $databasePath . '-wal'],
    'page size' => [static fn (): mixed => $full()['page_size'], 512],
    'page count' => [static fn (): mixed => $full()['page_count'], 6],
    'reader end frame' => [static fn (): mixed => $full()['reader_end_frame'], 5],
    'hot recovered' => [static fn (): mixed => $full()['hot_recovered'], true],
    'current reader preserved' => [static fn (): mixed => $full()['current_reader_preserved'], true],
    'next source separated' => [static fn (): mixed => $full()['next_source_separated'], true],
    'current sha length' => [static fn (): mixed => strlen($full()['current_wal_sha256']), 64],
    'next sha length' => [static fn (): mixed => strlen($full()['next_wal_sha256']), 64],
    'sha separated' => [static fn (): mixed => $full()['current_wal_sha256'] !== $full()['next_wal_sha256'], true],
    'admitted pages' => [static fn (): mixed => $full()['admitted_page_numbers'], [2, 3, 6]],
    'rejected pages' => [static fn (): mixed => $full()['rejected_page_numbers'], [4, 5]],
    'spilled pages' => [static fn (): mixed => $full()['spilled_page_numbers'], [2, 3, 6]],
    'next wal frame start' => [static fn (): mixed => $full()['next_wal_frame_start'], 4],
    'appended frame indexes' => [static fn (): mixed => array_column($full()['appended_wal_frames'], 'frame_index'), [4, 5, 6]],
    'appended frame pages' => [static fn (): mixed => array_column($full()['appended_wal_frames'], 'page'), [2, 3, 6]],
    'appended frame source' => [static fn (): mixed => $full()['appended_wal_frames'][0]['source'], 'cache-spill-after-hot-journal-reader-current-source'],
    'page two admitted' => [static fn (): mixed => $full()['source_checks'][2]['admitted'], true],
    'page two current source' => [static fn (): mixed => $full()['source_checks'][2]['current_source'], 'wal'],
    'page two current frame' => [static fn (): mixed => $full()['source_checks'][2]['current_frame'], 2],
    'page two next source' => [static fn (): mixed => $full()['source_checks'][2]['next_source'], 'wal'],
    'page two next frame' => [static fn (): mixed => $full()['source_checks'][2]['next_frame'], 1],
    'page three current prefix' => [static fn (): mixed => $full()['source_checks'][3]['current_prefix'], 'next147 current active_plugins reader draft'],
    'page three cache prefix' => [static fn (): mixed => $full()['source_checks'][3]['cache_prefix'], 'next147 retry active_plugins spill'],
    'page four rejected pinned reader' => [static fn (): mixed => $full()['rejected_pages'][4], ['reader_pinned_current_source_page']],
    'page four flag pinned' => [static fn (): mixed => $full()['source_checks'][4]['reader_pinned'], true],
    'page five rejected next generation' => [static fn (): mixed => $full()['rejected_pages'][5], ['cache_page_from_next_wal_generation', 'hot_journal_reader_current_source_mismatch', 'cache_image_matches_next_generation_wal']],
    'page five next source' => [static fn (): mixed => $full()['source_checks'][5]['next_source'], 'wal'],
    'page five next frame' => [static fn (): mixed => $full()['source_checks'][5]['next_frame'], 2],
    'page six current source' => [static fn (): mixed => $full()['source_checks'][6]['current_source'], 'database'],
    'page six next source' => [static fn (): mixed => $full()['source_checks'][6]['next_source'], 'wal'],
    'page six admitted' => [static fn (): mixed => $full()['source_checks'][6]['admitted'], true],
    'spill status' => [static fn (): mixed => $full()['spill']['status'], 'spilled'],
    'spill target' => [static fn (): mixed => $full()['spill']['spill_target'], 'wal_frames'],
    'spill current dirty' => [static fn (): mixed => $full()['spill']['current']['dirty_pages'], [2, 3, 6]],
    'spill current journaled' => [static fn (): mixed => $full()['spill']['current']['journaled_pages'], [2, 3, 6]],
    'spill wal frame pages' => [static fn (): mixed => $full()['spill']['next']['wal_frame_pages'], [2, 3, 6]],
    'spill database unchanged' => [static fn (): mixed => $full()['spill']['next']['database_image'], 'unchanged_until_checkpoint'],
    'spill journal rollback not required' => [static fn (): mixed => $full()['spill']['next']['journal_required_for_rollback'], false],
    'operation admit first' => [static fn (): mixed => $full()['operations'][0]['op'], 'admit_hot_journal_reader_cache_spill_page'],
    'operation defer pinned' => [static fn (): mixed => $full()['operations'][3]['page'], 4],
    'operation append frame' => [static fn (): mixed => $full()['operations'][5]['op'], 'append_wal_frame'],
    'operation mark clean' => [static fn (): mixed => $full()['operations'][6]['op'], 'mark_page_clean_in_cache'],
    'operation suffix' => [static fn (): mixed => array_slice($full()['operation_reasons'], -2), [
        'verify_cache_pages_against_hot_journal_reader_current_source_next147',
        'append_cache_spill_frames_after_next_wal_generation_next147',
    ]],
    'base reader status' => [static fn (): mixed => $full()['base_reader_plan']['status'], 'wal-hot-journal-reader-restart-current-source-next143'],
    'base row count' => [static fn (): mixed => count($full()['base_reader_plan']['rows']), 6],
    'dependency next147' => [static fn (): mixed => in_array('sqlite-pager-cache-spill-hot-journal-reader-current-source-next147', $full()['dependencies'], true), true],
    'dependency reader next143' => [static fn (): mixed => in_array('sqlite-wal-hot-journal-reader-restart-current-source-next143', $full()['dependencies'], true), true],
    'dependency spill next107' => [static fn (): mixed => in_array('sqlite-pager-cache-spill-journalmode-current-source-next107', $full()['dependencies'], true), true],
    'dependency closure' => [static fn (): mixed => str_contains($full()['dependency_closure'], 'no new support component needed'), true],
    'non overlap' => [static fn (): mixed => str_contains($full()['non_overlap'], 'pinned hot-journal current reader'), true],
    'one page limit' => [static fn (): mixed => $plan(null, 5, null, false, true, true, 1)['spilled_page_numbers'], [2]],
    'one page limit remaining dirty' => [static fn (): mixed => $plan(null, 5, null, false, true, true, 1)['spill']['next']['dirty_pages'], [3, 6]],
    'unsynced defers' => [static fn (): mixed => $plan(null, 5, null, false, false)['status'], 'pager_cache_spill_hot_journal_reader_current_source_deferred_next147'],
    'unsynced reason' => [static fn (): mixed => $plan(null, 5, null, false, false)['spill']['blocked_reasons'], ['journal_not_synced']],
    'disabled defers' => [static fn (): mixed => $plan(null, 5, null, false, true, false)['status'], 'pager_cache_spill_hot_journal_reader_current_source_deferred_next147'],
    'disabled reason' => [static fn (): mixed => $plan(null, 5, null, false, true, false)['spill']['blocked_reasons'], ['cache_spill_disabled']],
    'all rejected defers' => [static fn (): mixed => $allRejected()['status'], 'pager_cache_spill_hot_journal_reader_current_source_deferred_next147'],
    'all rejected admitted empty' => [static fn (): mixed => $allRejected()['admitted_page_numbers'], []],
    'all rejected spill reason' => [static fn (): mixed => $allRejected()['spill']['blocked_reasons'], ['no_journaled_unpinned_dirty_pages']],
    'blocked reserved status' => [static fn (): mixed => $blocked()['status'], 'pager_cache_spill_hot_journal_reader_current_source_deferred_next147'],
    'blocked reserved hot recovered false' => [static fn (): mixed => $blocked()['hot_recovered'], false],
    'clean page rejected' => [static fn (): mixed => $plan([['page' => 2, 'image' => $page('next147 clean'), 'current_image' => $currentImage(2), 'dirty' => false]])['rejected_pages'][2], ['cache_page_clean']],
    'unjournaled page rejected' => [static fn (): mixed => $plan([['page' => 2, 'image' => $page('next147 unjournaled'), 'current_image' => $currentImage(2), 'journaled' => false]])['rejected_pages'][2], ['cache_page_not_journaled']],
    'ordinary pinned page rejected' => [static fn (): mixed => $plan([['page' => 2, 'image' => $page('next147 pinned'), 'current_image' => $currentImage(2), 'pinned' => true]])['rejected_pages'][2], ['cache_page_pinned']],
    'stale source rejected' => [static fn (): mixed => $plan([['page' => 2, 'image' => $page('next147 stale'), 'current_image' => $cleanPages[2]]])['rejected_pages'][2], ['hot_journal_reader_current_source_mismatch']],
    'current source row count' => [static fn (): mixed => count($full()['source_checks']), 5],
    'source check pages' => [static fn (): mixed => array_keys($full()['source_checks']), [2, 3, 4, 5, 6]],
];

foreach ($cases as $name => [$callback, $expected]) {
    $tests['pager cache spill hot journal reader current source next147 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        $t->same($expected, $callback());
    };
}

$throws = [
    'empty cache rejected' => static fn () => $plan([]),
    'empty path rejected by base' => static fn () => $plan(null, 5, null, false, true, true, null, ''),
    'empty database rejected by base' => static fn () => $plan(null, 5, null, false, true, true, null, null, ''),
    'empty journal rejected by base' => static fn () => $plan(null, 5, null, false, true, true, null, null, null, ''),
    'bad reader rejected by base' => static fn () => $plan(null, -1),
    'empty restart wal rejected by base' => static fn () => $plan(null, 5, ''),
    'stale restart sequence rejected by base' => static fn () => $plan(null, 5, $makeWalBytes([[2, 6, 'next147 stale restart']], 147, 0x14714801, 0x14714802)),
    'same restart salt rejected by base' => static fn () => $plan(null, 5, $makeWalBytes([[2, 6, 'next147 same salt restart']], 148, 0x14714701, 0x14714702)),
    'cache page zero rejected' => static fn () => $plan([['page' => 0, 'image' => $page('bad')]]),
    'cache page outside rejected' => static fn () => $plan([['page' => 7, 'image' => $page('bad')]]),
    'duplicate cache page rejected' => static fn () => $plan([['page' => 2, 'image' => $page('one')], ['page' => 2, 'image' => $page('two')]]),
    'short cache image rejected' => static fn () => $plan([['page' => 2, 'image' => 'short']]),
    'short current image rejected' => static fn () => $plan([['page' => 2, 'image' => $page('ok'), 'current_image' => 'short']]),
    'negative bytes rejected' => static fn () => $plan([['page' => 2, 'image' => $page('ok'), 'bytes' => -1]]),
    'bad wal frame rejected' => static fn () => $plan([['page' => 2, 'image' => $page('ok'), 'walFrame' => 0]]),
    'bad threshold rejected' => static fn () => SQLitePagerCacheSpillHotJournalReaderCurrentSourceNextPlan::plan($databasePath, $dirtyDatabase, $journalBytes, $currentWal, $currentWalBytes, $restartedWalBytes, $cachePages, [1], 1, 8, 0),
    'bad max spill rejected' => static fn () => SQLitePagerCacheSpillHotJournalReaderCurrentSourceNextPlan::plan($databasePath, $dirtyDatabase, $journalBytes, $currentWal, $currentWalBytes, $restartedWalBytes, $cachePages, [1], 1, 8, 3, false, true, true, 0),
];

foreach ($throws as $name => $callback) {
    $tests['pager cache spill hot journal reader current source next147 ' . $name] = static function (TestRunner $t) use ($callback): void {
        $t->throws(Throwable::class, $callback);
    };
}

return $tests;
