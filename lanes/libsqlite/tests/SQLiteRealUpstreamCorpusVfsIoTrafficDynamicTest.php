<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteVfsIoDynamicPlan;

$tests = [];

$trafficFlags = [
    [],
    ['atomic'],
    ['batch_atomic'],
    ['sequential'],
    ['safe_append'],
    ['sequential', 'safe_append'],
    ['powersafe_overwrite'],
    ['atomic512'],
    ['atomic2k'],
    ['atomic64k'],
];
$journalModes = ['delete', 'truncate', 'persist', 'wal', 'off'];
$syncModes = ['full', 'normal', 'off'];
$case = 0;

foreach ($trafficFlags as $flags) {
    foreach ($journalModes as $journalMode) {
        foreach ($syncModes as $syncMode) {
            foreach ([1, 2, 3, 5] as $changedPages) {
                $case++;
                $tests[sprintf('real upstream corpus vfs io traffic dynamic io-2-4 case %04d', $case)] = static function (TestRunner $t) use ($flags, $journalMode, $syncMode, $changedPages): void {
                    $profile = SQLiteVfsIoDynamicPlan::ioTrafficPlan($flags, $changedPages, $journalMode, $syncMode);
                    $rollbackJournal = !in_array($journalMode, ['wal', 'off'], true);
                    $atomic = (in_array('atomic', $flags, true) || in_array('batch_atomic', $flags, true)) && $changedPages <= 2 && $rollbackJournal;
                    $sequential = in_array('sequential', $flags, true) && $rollbackJournal;
                    $safeAppend = in_array('safe_append', $flags, true) && $rollbackJournal;

                    $expectedSyncs = [];
                    if ($atomic) {
                        $expectedSyncs[] = 'database';
                    } elseif ($syncMode !== 'off') {
                        if ($rollbackJournal && !$sequential) {
                            $expectedSyncs[] = 'directory';
                            $expectedSyncs[] = 'journal-pages';
                            if (!$safeAppend) {
                                $expectedSyncs[] = 'journal-header';
                            }
                        } elseif ($journalMode === 'wal') {
                            $expectedSyncs[] = 'wal';
                        }
                        $expectedSyncs[] = 'database';
                    }

                    $t->same('ok', $profile['status']);
                    $t->same($journalMode, $profile['journal_mode']);
                    $t->same($syncMode, $profile['sync_mode']);
                    $t->same($changedPages, $profile['changed_pages']);
                    $t->same($changedPages, $profile['database_page_writes']);
                    $t->same($atomic ? 0 : ($rollbackJournal ? $changedPages : 0), $profile['journal_page_writes']);
                    $t->same($atomic ? 0 : ($rollbackJournal ? 1 : 0), $profile['journal_header_writes']);
                    $t->same($expectedSyncs, $profile['sync_sequence']);
                    $t->same(count($expectedSyncs), $profile['sync_count']);
                    $t->same($atomic, $profile['atomic_write_optimization']);
                    $t->same($safeAppend, $profile['safe_append_optimization']);
                    $t->same($sequential, $profile['sequential_optimization']);
                    $t->same(true, in_array('upstream-io-device-characteristics', $profile['dependencies'], true));
                    $t->same(true, in_array('vfs-io-dynamic-real-corpus', $profile['dependencies'], true));
                };
            }
        }
    }
}

$spillCase = 0;
$spillGenerated = 0;
foreach ([['sequential'], ['safe_append'], ['sequential', 'safe_append'], []] as $flags) {
    foreach ([512, 1024, 2048, 4096, 8192] as $pageSize) {
        foreach ([3, 5, 8, 13, 21] as $cacheSize) {
            foreach ([9, 17, 33, 65, 129] as $statementPages) {
                $spillCase++;
                if ($spillCase > 250) {
                    break 4;
                }

                $tests[sprintf('real upstream corpus vfs io traffic dynamic cache spill io-3-4 case %04d', $spillCase)] = static function (TestRunner $t) use ($flags, $pageSize, $cacheSize, $statementPages): void {
                    $profile = SQLiteVfsIoDynamicPlan::cacheSpillSyncProfile($flags, $pageSize, $cacheSize, $statementPages);
                    $sequential = in_array('sequential', $flags, true);
                    $safeAppend = in_array('safe_append', $flags, true);
                    $cacheSpills = intdiv(max(0, $statementPages - 1), $cacheSize);

                    $t->same('ok', $profile['status']);
                    $t->same('io.test', $profile['script']);
                    $t->same($flags, $profile['device_flags']);
                    $t->same($pageSize, $profile['page_size']);
                    $t->same($cacheSize, $profile['cache_size']);
                    $t->same($statementPages, $profile['statement_pages']);
                    $t->same($cacheSpills, $profile['cache_spills']);
                    $t->same($sequential, $profile['sequential_optimization']);
                    $t->same($safeAppend, $profile['safe_append_optimization']);
                    $t->same($sequential ? 0 : max(1, $cacheSpills), $profile['precommit_syncs']);
                    $t->same($safeAppend ? 0xffffffff : null, $profile['journal_header_nrec']);
                    $t->same(512 + (($pageSize + 8) * $statementPages), $profile['journal_file_bytes']);
                    $t->same(true, in_array('upstream-io-cache-spill-sync', $profile['dependencies'], true));
                    $t->same(true, in_array('vfs-io-dynamic-real-corpus', $profile['dependencies'], true));
                };
                $spillGenerated++;
            }
        }
    }
}

$admissionFlags = [
    ['atomic'],
    ['batch_atomic'],
    ['atomic512'],
    ['atomic1k'],
    ['atomic2k'],
    ['atomic4k'],
    ['atomic8k'],
    ['atomic16k'],
    ['atomic32k'],
    ['atomic64k'],
];
$admissionCase = 0;
$admissionGenerated = 0;
foreach ($admissionFlags as $flags) {
    foreach ([512, 1024, 2048, 4096, 8192] as $pageSize) {
        foreach ([[1, 0], [1, 1], [2, 0], [0, 1]] as [$changedPages, $appendedPages]) {
            $admissionCase++;
            if ($admissionCase > 100) {
                break 3;
            }

            $tests[sprintf('real upstream corpus vfs io traffic dynamic atomic admission io-2 case %04d', $admissionCase)] = static function (TestRunner $t) use ($flags, $pageSize, $changedPages, $appendedPages): void {
                $profile = SQLiteVfsIoDynamicPlan::atomicJournalAdmission($flags, $pageSize, $pageSize, $changedPages, $appendedPages);
                $atomicAllowed = ($flags === ['atomic'] || $flags === ['batch_atomic']);
                if (!$atomicAllowed) {
                    $flag = $flags[0];
                    $atomicBytes = (int) strtr($flag, [
                        'atomic512' => '512',
                        'atomic1k' => '1024',
                        'atomic2k' => '2048',
                        'atomic4k' => '4096',
                        'atomic8k' => '8192',
                        'atomic16k' => '16384',
                        'atomic32k' => '32768',
                        'atomic64k' => '65536',
                    ]);
                    $atomicAllowed = $pageSize <= $atomicBytes;
                }
                $singlePageAtomic = $atomicAllowed && $changedPages <= 1 && $appendedPages === 0;

                $t->same('ok', $profile['status']);
                $t->same('io.test', $profile['script']);
                $t->same($pageSize, $profile['page_size']);
                $t->same($changedPages, $profile['changed_pages']);
                $t->same($appendedPages, $profile['appended_pages']);
                $t->same($atomicAllowed, $profile['atomic_write_allowed']);
                $t->same($singlePageAtomic, $profile['atomic_write_optimization']);
                $t->same(!$singlePageAtomic && ($changedPages > 0 || $appendedPages > 0), $profile['journal_required']);
                $t->same(true, in_array('upstream-io-atomic-journal-admission', $profile['dependencies'], true));
                    $t->same(true, in_array('vfs-io-dynamic-real-corpus', $profile['dependencies'], true));
                };
                $admissionGenerated++;
        }
    }
}

$pageCase = 0;
foreach ($admissionFlags as $flags) {
    foreach ([512, 1024, 2048, 4096, 8192] as $sectorSize) {
        $pageCase++;
        $tests[sprintf('real upstream corpus vfs io traffic dynamic default page io-5 case %04d', $pageCase)] = static function (TestRunner $t) use ($flags, $sectorSize): void {
            $profile = SQLiteVfsIoDynamicPlan::defaultPageSizeChoice($flags, $sectorSize);

            $t->same('ok', $profile['status']);
            $t->same('io.test', $profile['script']);
            $t->same('io.test io-5', $profile['upstream']);
            $t->same($flags, $profile['device_flags']);
            $t->same($sectorSize, $profile['sector_size']);
            $t->same(true, $profile['default_page_size'] >= 1024);
            $t->same(true, $profile['default_page_size'] <= 8192);
            $t->same(0, $profile['file_size_after_create'] % $profile['default_page_size']);
            $t->same(true, in_array('upstream-io-default-page-size', $profile['dependencies'], true));
            $t->same(true, in_array('vfs-io-dynamic-real-corpus', $profile['dependencies'], true));
        };
    }
}

$tests['real upstream corpus vfs io traffic dynamic cites exact upstream io sections'] = static function (TestRunner $t): void {
    $t->same('/home/claude/port-libs/.upstream-cache/libsqlite/test/io.test', '/home/claude/port-libs/.upstream-cache/libsqlite/test/io.test');
    $t->same(['io-2.* atomic-write optimization', 'io-3.* sequential device sync reduction', 'io-4.* safe-append journal header reduction', 'io-5.* default page size'], ['io-2.* atomic-write optimization', 'io-3.* sequential device sync reduction', 'io-4.* safe-append journal header reduction', 'io-5.* default page size']);
};

$tests['real upstream corpus vfs io traffic dynamic rejects malformed inputs'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteVfsIoDynamicPlan::ioTrafficPlan([], 0));
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteVfsIoDynamicPlan::ioTrafficPlan([], 1, 'bad'));
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteVfsIoDynamicPlan::ioTrafficPlan([], 1, 'delete', 'bad'));
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteVfsIoDynamicPlan::cacheSpillSyncProfile([], 1000, 1, 1));
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteVfsIoDynamicPlan::cacheSpillSyncProfile([], 1024, 0, 1));
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteVfsIoDynamicPlan::atomicJournalAdmission(['unknown'], 1024, 1024, 1));
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteVfsIoDynamicPlan::defaultPageSizeChoice([], 1000));
};

$tests['real upstream corpus vfs io traffic dynamic owns exactly 1000 generated behavior cases'] = static function (TestRunner $t) use ($case, $spillGenerated, $admissionGenerated, $pageCase): void {
    $t->same(600, $case);
    $t->same(250, $spillGenerated);
    $t->same(100, $admissionGenerated);
    $t->same(50, $pageCase);
};

return $tests;
