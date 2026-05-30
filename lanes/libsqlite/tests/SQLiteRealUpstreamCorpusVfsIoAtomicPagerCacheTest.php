<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteVfsIoDynamicPlan;

$tests = [];

$retentionCases = [];
foreach ([1024, 2048, 4096] as $pageSize) {
    foreach ([96, 128, 160, 192, 224] as $indexedRows) {
        foreach ([80, 100, 120, 160] as $payloadBytes) {
            foreach ([180, 240, 320, 420, 640] as $cacheSize) {
                $retentionCases[] = [$pageSize, $cacheSize, $indexedRows, $payloadBytes, 1, 'single_page_atomic_write'];
                $retentionCases[] = [$pageSize, $cacheSize, $indexedRows, $payloadBytes, 2, 'rollback_journal_transaction'];
            }
        }
    }
}

$tests['real upstream corpus vfs io atomic pager cache retention follows io6 warm cache'] = static function (TestRunner $t) use ($retentionCases): void {
    foreach ($retentionCases as [$pageSize, $cacheSize, $indexedRows, $payloadBytes, $tablesModified, $commitPath]) {
        $profile = SQLiteVfsIoDynamicPlan::atomicPagerCacheRetentionProfile($pageSize, $cacheSize, $indexedRows, $payloadBytes, $tablesModified);

        $t->same('ok', $profile['status']);
        $t->same('io.test', $profile['script']);
        $t->same($pageSize, $profile['page_size']);
        $t->same($cacheSize, $profile['cache_size']);
        $t->same($indexedRows, $profile['indexed_rows']);
        $t->same($payloadBytes, $profile['payload_bytes']);
        $t->same($tablesModified, $profile['tables_modified']);
        $t->same(['atomic'], $profile['device_flags']);
        $t->same(true, $profile['database_pages'] > 0);
        $t->same($commitPath, $profile['commit_path']);
        $t->same('ok', $profile['pre_commit_integrity']);
        $t->same($profile['database_fits_cache'] ? 'ok' : 'corruption-visible', $profile['post_commit_integrity']);
        $t->same(!$profile['database_fits_cache'], $profile['pager_cache_flushed_by_commit']);
        $t->same(2, $profile['corrupt_disk_pages']);
        $t->same($pageSize * 5, $profile['corrupt_offset']);
        $t->same(true, $profile['mmap_disabled']);
        $t->same(['rowid', 'index'], $profile['ordered_cache_warmup']);
        $t->same(true, in_array('io.test io-6.1', $profile['upstream'], true));
        $t->same(true, in_array($tablesModified === 1 ? 'io.test io-6.2.2.1-6.2.2.3' : 'io.test io-6.2.1.1-6.2.1.3', $profile['upstream'], true));
        $t->same(true, in_array('upstream-io-atomic-pager-cache-retention', $profile['dependencies'], true));
        $t->same(true, in_array('vfs-io-dynamic-real-corpus', $profile['dependencies'], true));
    }
};

$flagCases = [
    'atomic2k covers 1024 byte page' => [1024, 240, 128, 100, 1, ['atomic2k'], true, 'single_page_atomic_write'],
    'atomic1k covers 1024 byte page' => [1024, 240, 128, 100, 1, ['atomic1k'], true, 'single_page_atomic_write'],
    'atomic512 cannot cover 1024 byte page' => [1024, 240, 128, 100, 1, ['atomic512'], false, 'rollback_journal_transaction'],
    'no atomic flag uses rollback journal' => [1024, 240, 128, 100, 1, [], false, 'rollback_journal_transaction'],
    'atomic4k covers 4096 byte page' => [4096, 240, 128, 100, 1, ['atomic4k'], true, 'single_page_atomic_write'],
];

foreach ($flagCases as $name => [$pageSize, $cacheSize, $indexedRows, $payloadBytes, $tablesModified, $flags, $atomicAllowed, $commitPath]) {
    $tests['real upstream corpus vfs io atomic pager cache retention ' . $name] = static function (TestRunner $t) use ($pageSize, $cacheSize, $indexedRows, $payloadBytes, $tablesModified, $flags, $atomicAllowed, $commitPath): void {
        $profile = SQLiteVfsIoDynamicPlan::atomicPagerCacheRetentionProfile($pageSize, $cacheSize, $indexedRows, $payloadBytes, $tablesModified, $flags);

        $t->same($flags, $profile['device_flags']);
        $t->same($atomicAllowed, $profile['atomic_write_allowed']);
        $t->same($commitPath, $profile['commit_path']);
        $t->same($atomicAllowed ? 'ok' : 'corruption-visible', $profile['post_commit_integrity']);
        $t->same(!$atomicAllowed, $profile['pager_cache_flushed_by_commit']);
        $t->same(true, in_array('io.test io-6.2.2.1-6.2.2.3', $profile['upstream'], true));
    };
}

$tests['real upstream corpus vfs io atomic pager cache retention rejects malformed inputs'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteVfsIoDynamicPlan::atomicPagerCacheRetentionProfile(1000, 2000, 128, 100, 1));
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteVfsIoDynamicPlan::atomicPagerCacheRetentionProfile(1024, 0, 128, 100, 1));
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteVfsIoDynamicPlan::atomicPagerCacheRetentionProfile(1024, 2000, 0, 100, 1));
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteVfsIoDynamicPlan::atomicPagerCacheRetentionProfile(1024, 2000, 128, 0, 1));
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteVfsIoDynamicPlan::atomicPagerCacheRetentionProfile(1024, 2000, 128, 100, 0));
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteVfsIoDynamicPlan::atomicPagerCacheRetentionProfile(1024, 2000, 128, 100, 1, ['networked']));
};

return $tests;
