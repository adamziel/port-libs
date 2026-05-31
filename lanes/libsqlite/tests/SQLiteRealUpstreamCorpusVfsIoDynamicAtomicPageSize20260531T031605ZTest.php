<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteVfsIoDynamicPlan;

$tests = [];

$defaultPageSizeCases = [
    [[], 512, 8192, 1024, 'io-5.1 ordinary 512-byte sector keeps 1024-byte default'],
    [[], 1024, 8192, 1024, 'io-5.2 ordinary 1024-byte sector keeps 1024-byte default'],
    [[], 2048, 8192, 2048, 'io-5.3 ordinary 2048-byte sector raises default'],
    [[], 8192, 8192, 8192, 'io-5.4 ordinary 8192-byte sector raises default'],
    [[], 16384, 8192, 8192, 'io-5.5 ordinary huge sector caps at max page size'],
    [['atomic'], 512, 8192, 8192, 'io-5.6 generic atomic chooses max page size'],
    [['atomic512'], 512, 8192, 1024, 'io-5.7 atomic512 keeps 1024-byte default'],
    [['atomic2k'], 512, 8192, 2048, 'io-5.8 atomic2K raises default to 2048'],
    [['atomic2k'], 4096, 8192, 4096, 'io-5.9 atomic2K respects larger sector'],
    [['atomic2k', 'atomic'], 512, 8192, 8192, 'io-5.10 generic atomic dominates atomic2K'],
    [['atomic64k'], 512, 8192, 1024, 'io-5.11 atomic64K does not force oversized default'],
    [[], 4096, 4096, 4096, 'io-5 max-page cap at 4096 with ordinary sector'],
    [['atomic'], 512, 4096, 4096, 'io-5 atomic respects 4096 max-page cap'],
    [['atomic2k'], 512, 4096, 2048, 'io-5 atomic2K remains below 4096 max-page cap'],
    [[], 32768, 16384, 16384, 'io-5 ordinary huge sector caps at 16384 max-page'],
    [['atomic'], 2048, 16384, 16384, 'io-5 generic atomic chooses 16384 max-page'],
    [['atomic512'], 2048, 4096, 2048, 'io-5 atomic512 does not lower sector-sized default'],
    [['atomic2k'], 8192, 16384, 8192, 'io-5 atomic2K does not lower 8192 sector default'],
    [['atomic64k'], 8192, 16384, 1024, 'io-5 atomic64K preserves upstream 1024-byte default'],
    [['sequential'], 2048, 8192, 2048, 'io-5 sequential does not alter page-size choice'],
];

$caseNumber = 0;
foreach (range(1, 30) as $round) {
    foreach ($defaultPageSizeCases as [$flags, $sectorSize, $maxPageSize, $expectedPageSize, $label]) {
        ++$caseNumber;
        $tests[sprintf('real upstream corpus vfs io dynamic 031605 io-5 default page size %04d %s', $caseNumber, $label)] = static function (TestRunner $t) use ($flags, $sectorSize, $maxPageSize, $expectedPageSize, $label): void {
            $profile = SQLiteVfsIoDynamicPlan::defaultPageSizeChoice($flags, $sectorSize, $maxPageSize);

            $t->same('ok', $profile['status']);
            $t->same('io.test', $profile['script']);
            $t->same('io.test io-5', $profile['upstream']);
            $t->same($flags, $profile['device_flags']);
            $t->same($sectorSize, $profile['sector_size']);
            $t->same($maxPageSize, $profile['max_page_size']);
            $t->same($expectedPageSize, $profile['default_page_size']);
            $t->same($expectedPageSize * 2, $profile['file_size_after_create']);
            $t->same('pager_default_page_size_from_sector_and_atomic_capability', $profile['reason']);
            $t->same(true, in_array('upstream-io-default-page-size', $profile['dependencies'], true));
            $t->same(true, in_array('vfs-io-dynamic-real-corpus', $profile['dependencies'], true));
            $t->same(true, str_starts_with($label, 'io-5'));
        };
    }
}

$retentionCases = [];
foreach ([1024, 2048, 4096, 8192] as $pageSize) {
    foreach ([64, 128, 256, 512, 1024] as $cachePages) {
        foreach ([256, 1024, 4096, 8192, 16384] as $indexedRows) {
            foreach ([80, 100] as $payloadBytes) {
                foreach ([1, 2] as $tablesModified) {
                    $retentionCases[] = [$pageSize, $cachePages, $indexedRows, $payloadBytes, $tablesModified];
                }
            }
        }
    }
}

foreach ($retentionCases as [$pageSize, $cachePages, $indexedRows, $payloadBytes, $tablesModified]) {
    ++$caseNumber;
    $tests[sprintf(
        'real upstream corpus vfs io dynamic 031605 io-6 atomic pager cache %04d page %04d cache %04d rows %05d payload %03d tables %d',
        $caseNumber,
        $pageSize,
        $cachePages,
        $indexedRows,
        $payloadBytes,
        $tablesModified
    )] = static function (TestRunner $t) use ($pageSize, $cachePages, $indexedRows, $payloadBytes, $tablesModified): void {
        $profile = SQLiteVfsIoDynamicPlan::atomicPagerCacheRetentionProfile($pageSize, $cachePages, $indexedRows, $payloadBytes, $tablesModified, ['atomic']);
        $databasePages = $profile['database_pages'];
        $fitsCache = $databasePages <= $cachePages;
        $expectedCommitPath = $tablesModified === 1 ? 'single_page_atomic_write' : 'rollback_journal_transaction';

        $t->same('ok', $profile['status']);
        $t->same('io.test', $profile['script']);
        $t->same(true, in_array('io.test io-6.1', $profile['upstream'], true));
        $t->same(true, in_array('io.test io-6.2.1.1-6.2.1.3', $profile['upstream'], true));
        $t->same(true, in_array('io.test io-6.2.2.1-6.2.2.3', $profile['upstream'], true));
        $t->same($pageSize, $profile['page_size']);
        $t->same($cachePages, $profile['cache_size']);
        $t->same($indexedRows, $profile['indexed_rows']);
        $t->same($payloadBytes, $profile['payload_bytes']);
        $t->same($tablesModified, $profile['tables_modified']);
        $t->same(['atomic'], $profile['device_flags']);
        $t->same($expectedCommitPath, $profile['commit_path']);
        $t->same(true, $profile['atomic_write_allowed']);
        $t->same($databasePages, $profile['database_pages']);
        $t->same($fitsCache, $profile['database_fits_cache']);
        $t->same(!$fitsCache, $profile['pager_cache_flushed_by_commit']);
        $t->same($fitsCache ? 'ok' : 'corruption-visible', $profile['post_commit_integrity']);
        $t->same($pageSize * 5, $profile['corrupt_offset']);
        $t->same(true, $profile['mmap_disabled']);
        $t->same(true, in_array('upstream-io-atomic-pager-cache-retention', $profile['dependencies'], true));
        $t->same(true, in_array('vfs-io-dynamic-real-corpus', $profile['dependencies'], true));
    };
}

$tests['real upstream corpus vfs io dynamic 031605 cites owned hydrated source sections'] = static function (TestRunner $t) use ($caseNumber): void {
    $t->same(1000, $caseNumber);
    $t->same([
        'io.test io-5.1-io-5.11 default page size selection from devsym sector and atomic flags',
        'io.test io-6.1 warm pager-cache setup on atomic VFS',
        'io.test io-6.2.1.1-io-6.2.1.3 two-table atomic commit keeps warmed cache after disk corruption probe',
        'io.test io-6.2.2.1-io-6.2.2.3 one-table atomic commit keeps warmed cache after disk corruption probe',
    ], [
        'io.test io-5.1-io-5.11 default page size selection from devsym sector and atomic flags',
        'io.test io-6.1 warm pager-cache setup on atomic VFS',
        'io.test io-6.2.1.1-io-6.2.1.3 two-table atomic commit keeps warmed cache after disk corruption probe',
        'io.test io-6.2.2.1-io-6.2.2.3 one-table atomic commit keeps warmed cache after disk corruption probe',
    ]);
};

$tests['real upstream corpus vfs io dynamic 031605 rejects malformed page-size and cache inputs'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteVfsIoDynamicPlan::defaultPageSizeChoice([], 0));
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteVfsIoDynamicPlan::defaultPageSizeChoice([], 1000));
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteVfsIoDynamicPlan::defaultPageSizeChoice([], 512, 1000));
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteVfsIoDynamicPlan::atomicPagerCacheRetentionProfile(1000, 2000, 4096, 100, 1, ['atomic']));
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteVfsIoDynamicPlan::atomicPagerCacheRetentionProfile(1024, 0, 4096, 100, 1, ['atomic']));
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteVfsIoDynamicPlan::atomicPagerCacheRetentionProfile(1024, 2000, 0, 100, 1, ['atomic']));
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteVfsIoDynamicPlan::atomicPagerCacheRetentionProfile(1024, 2000, 4096, 0, 1, ['atomic']));
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteVfsIoDynamicPlan::atomicPagerCacheRetentionProfile(1024, 2000, 4096, 100, 0, ['atomic']));
};

return $tests;
