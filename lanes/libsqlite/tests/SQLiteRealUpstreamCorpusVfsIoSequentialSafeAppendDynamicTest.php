<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteVfsIoDynamicPlan;

$tests = [];

$flagSets = [
    ['sequential'],
    ['safe_append'],
    ['sequential', 'safe_append'],
    ['safe_append', 'atomic'],
];
$pageSizes = [512, 1024, 2048, 4096];
$cacheSizes = [4, 7, 10, 13, 16];
$syncModes = ['off', 'normal', 'full'];

$case = 0;
foreach ($flagSets as $flagIndex => $flags) {
    foreach ($pageSizes as $pageSize) {
        foreach ($cacheSizes as $cacheSize) {
            foreach ($syncModes as $syncMode) {
                foreach (range(21, 70) as $statementPages) {
                    ++$case;
                    if ($case > 1000) {
                        break 5;
                    }

                    $reservedBytes = ($case % 9) === 0;
                    $directorySync = ($case % 5) !== 0;
                    $name = sprintf(
                        'real upstream corpus vfs io dynamic io.test sequential safe-append cache spill %04d',
                        $case
                    );

                    $tests[$name] = static function (TestRunner $t) use (
                        $flags,
                        $pageSize,
                        $cacheSize,
                        $statementPages,
                        $syncMode,
                        $reservedBytes,
                        $directorySync
                    ): void {
                        $profile = SQLiteVfsIoDynamicPlan::cacheSpillSyncProfile(
                            $flags,
                            $pageSize,
                            $cacheSize,
                            $statementPages,
                            $syncMode,
                            $reservedBytes,
                            $directorySync
                        );

                        $sequential = in_array('sequential', $flags, true);
                        $safeAppend = in_array('safe_append', $flags, true);
                        $cacheSpills = intdiv($statementPages - 1, $cacheSize);
                        $expectedSyncs = [];
                        if ($syncMode !== 'off') {
                            if (!$sequential) {
                                if ($directorySync) {
                                    $expectedSyncs[] = 'directory';
                                }
                                $expectedSyncs[] = 'journal-pages';
                                if (!$safeAppend) {
                                    $expectedSyncs[] = 'journal-header';
                                }
                            }
                            $expectedSyncs[] = 'database';
                        }

                        $t->same('ok', $profile['status']);
                        $t->same('io.test', $profile['script']);
                        $t->same($flags, $profile['device_flags']);
                        $t->same($pageSize, $profile['page_size']);
                        $t->same($cacheSize, $profile['cache_size']);
                        $t->same($statementPages, $profile['statement_pages']);
                        $t->same($syncMode, $profile['sync_mode']);
                        $t->same($directorySync, $profile['directory_sync']);
                        $t->same($reservedBytes, $profile['reserved_bytes']);
                        $t->same($sequential, $profile['sequential_optimization']);
                        $t->same($safeAppend, $profile['safe_append_optimization']);
                        $t->same($cacheSpills, $profile['cache_spills']);
                        $t->same($profile['database_bytes_after_spill'] > 20000, $profile['file_grew_during_spill']);
                        $t->same($sequential || $syncMode === 'off' ? 0 : max(1, $cacheSpills), $profile['precommit_syncs']);
                        $t->same($syncMode === 'off' ? 0 : ($sequential ? 1 : count($expectedSyncs)), $profile['commit_syncs']);
                        $t->same($expectedSyncs, $profile['sync_sequence']);
                        $t->same($safeAppend ? 0xffffffff : null, $profile['journal_header_nrec']);
                        $t->same($safeAppend ? 1 : max(1, 1 + $cacheSpills), $profile['journal_header_count']);
                        $t->same(512, $profile['journal_header_bytes']);
                        $t->same($pageSize + 8, $profile['page_record_bytes']);
                        $t->same(512 + (($pageSize + 8) * $statementPages), $profile['journal_file_bytes']);
                        $t->same(0, $profile['database_bytes_after_spill'] % $pageSize);
                        $t->same($sequential && $reservedBytes ? 40960 : ($sequential ? 39936 : $profile['database_bytes_after_spill']), $profile['database_bytes_after_commit']);
                        $t->same(
                            $sequential
                                ? 'sequential_device_defers_spill_syncs_until_commit'
                                : ($safeAppend ? 'safe_append_uses_single_journal_header_across_spills' : 'full_sync_journal_headers_may_repeat_after_spills'),
                            $profile['reason']
                        );
                        $t->same(true, in_array('io.test io-3.2', $profile['upstream'], true));
                        $t->same(true, in_array('io.test io-3.3', $profile['upstream'], true));
                        $t->same(true, in_array('io.test io-4.2.2', $profile['upstream'], true));
                        $t->same(true, in_array('io.test io-4.3.4', $profile['upstream'], true));
                        $t->same(true, in_array('upstream-io-cache-spill-sync', $profile['dependencies'], true));
                        $t->same(true, in_array('upstream-io-safe-append-journal-size', $profile['dependencies'], true));
                        $t->same(true, in_array('vfs-io-dynamic-real-corpus', $profile['dependencies'], true));
                    };
                }
            }
        }
    }
}

$tests['real upstream corpus vfs io dynamic io.test sequential safe-append cites source sections'] = static function (TestRunner $t): void {
    $profile = SQLiteVfsIoDynamicPlan::cacheSpillSyncProfile(['sequential', 'safe_append'], 1024, 10, 41, 'full');

    $t->same([
        'io.test io-3.1',
        'io.test io-3.2',
        'io.test io-3.3',
        'io.test io-4.1',
        'io.test io-4.2.1',
        'io.test io-4.2.2',
        'io.test io-4.2.3',
        'io.test io-4.3.1',
        'io.test io-4.3.2',
        'io.test io-4.3.3',
        'io.test io-4.3.4',
    ], $profile['upstream']);
};

$tests['real upstream corpus vfs io dynamic io.test sequential safe-append rejects malformed inputs'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteVfsIoDynamicPlan::cacheSpillSyncProfile(['sequential'], 1000, 10, 41));
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteVfsIoDynamicPlan::cacheSpillSyncProfile(['sequential'], 1024, 0, 41));
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteVfsIoDynamicPlan::cacheSpillSyncProfile(['sequential'], 1024, 10, 0));
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteVfsIoDynamicPlan::cacheSpillSyncProfile(['sequential'], 1024, 10, 41, 'extra'));
};

return $tests;
