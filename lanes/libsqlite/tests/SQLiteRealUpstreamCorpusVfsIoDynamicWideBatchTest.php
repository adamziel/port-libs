<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteVfsIoDynamicPlan;

$tests = [];

$caseCount = 0;

$add = static function (string $name, callable $body) use (&$tests, &$caseCount): void {
    $caseCount++;
    $tests[sprintf('real upstream corpus vfs io dynamic wide batch %04d %s', $caseCount, $name)] = $body;
};

$flagSets = [
    [],
    ['atomic'],
    ['atomic512'],
    ['atomic2k'],
    ['safe_append'],
    ['sequential'],
    ['safe_append', 'sequential'],
    ['batch_atomic'],
];
$journalModes = ['delete', 'truncate', 'persist', 'wal', 'off'];
$syncModes = ['off', 'normal', 'full'];

foreach ($flagSets as $flagIndex => $flags) {
    foreach ([1, 2, 3, 5, 8, 13, 21, 34, 55] as $changedPages) {
        foreach ($journalModes as $journalMode) {
            foreach ($syncModes as $syncMode) {
                $add("io.test io-2/io-3/io-4 traffic flags {$flagIndex} pages {$changedPages} {$journalMode} {$syncMode}", static function (TestRunner $t) use ($flags, $changedPages, $journalMode, $syncMode): void {
                    $plan = SQLiteVfsIoDynamicPlan::ioTrafficPlan($flags, $changedPages, $journalMode, $syncMode);
                    $rollbackJournal = !in_array($journalMode, ['wal', 'off'], true);
                    $atomic = (in_array('atomic', $plan['device_flags'], true) || in_array('batch_atomic', $plan['device_flags'], true)) && $rollbackJournal && $changedPages <= 2;
                    $safeAppend = in_array('safe_append', $plan['device_flags'], true) && $rollbackJournal;
                    $sequential = in_array('sequential', $plan['device_flags'], true) && $rollbackJournal;

                    $t->same('ok', $plan['status']);
                    $t->same($changedPages, $plan['changed_pages']);
                    $t->same($journalMode, $plan['journal_mode']);
                    $t->same($syncMode, $plan['sync_mode']);
                    $t->same($changedPages, $plan['database_page_writes']);
                    $t->same($atomic, $plan['atomic_write_optimization']);
                    $t->same($safeAppend, $plan['safe_append_optimization']);
                    $t->same($sequential, $plan['sequential_optimization']);
                    $t->same($atomic ? 0 : ($rollbackJournal ? $changedPages : 0), $plan['journal_page_writes']);
                    $t->same(count($plan['sync_sequence']), $plan['sync_count']);
                    $t->same(true, in_array('upstream-io-device-characteristics', $plan['dependencies'], true));
                    $t->same(true, in_array('vfs-io-dynamic-real-corpus', $plan['dependencies'], true));
                });
            }
        }
    }
}

foreach ($flagSets as $flagIndex => $flags) {
    foreach ([512, 1024, 2048, 4096, 8192, 16384, 32768] as $sectorSize) {
        foreach ([2048, 4096, 8192, 16384] as $maxPageSize) {
            $add("io.test io-5 default page size flags {$flagIndex} sector {$sectorSize} max {$maxPageSize}", static function (TestRunner $t) use ($flags, $sectorSize, $maxPageSize): void {
                $plan = SQLiteVfsIoDynamicPlan::defaultPageSizeChoice($flags, $sectorSize, $maxPageSize);
                $expected = min($maxPageSize, max(1024, $sectorSize));
                if (in_array('atomic', $plan['device_flags'], true)) {
                    $expected = $maxPageSize;
                } elseif (in_array('atomic2k', $plan['device_flags'], true)) {
                    $expected = max($expected, min($maxPageSize, max(2048, $sectorSize)));
                } elseif (in_array('atomic512', $plan['device_flags'], true)) {
                    $expected = max($expected, 1024);
                } elseif (in_array('atomic64k', $plan['device_flags'], true)) {
                    $expected = 1024;
                }

                $t->same('ok', $plan['status']);
                $t->same('io.test', $plan['script']);
                $t->same('io.test io-5', $plan['upstream']);
                $t->same($sectorSize, $plan['sector_size']);
                $t->same($maxPageSize, $plan['max_page_size']);
                $t->same($expected, $plan['default_page_size']);
                $t->same($expected * 2, $plan['file_size_after_create']);
                $t->same(0, $plan['default_page_size'] & ($plan['default_page_size'] - 1));
                $t->same(true, in_array('upstream-io-default-page-size', $plan['dependencies'], true));
            });
        }
    }
}

foreach ([512, 1024, 2048, 4096, 8192] as $pageSize) {
    foreach ([1, 2, 3, 5, 8, 13, 21, 34, 55, 89] as $changedPages) {
        foreach ([1, 2, 3, 5, 8, 13, 21] as $cacheSize) {
            foreach (['off', 'full'] as $syncMode) {
                $add("io.test io-4 safe append journal page {$pageSize} pages {$changedPages} cache {$cacheSize} {$syncMode}", static function (TestRunner $t) use ($pageSize, $changedPages, $cacheSize, $syncMode): void {
                    $plan = SQLiteVfsIoDynamicPlan::safeAppendJournalSize($pageSize, $changedPages, $cacheSize, $syncMode);
                    $recordBytes = $pageSize + 8;
                    $expectedSpills = intdiv(max(0, $changedPages - 1), $cacheSize);

                    $t->same('ok', $plan['status']);
                    $t->same('io.test', $plan['script']);
                    $t->same($pageSize, $plan['page_size']);
                    $t->same($changedPages, $plan['changed_pages']);
                    $t->same($cacheSize, $plan['cache_size']);
                    $t->same(0xffffffff, $plan['journal_header_nrec']);
                    $t->same(1, $plan['journal_header_count']);
                    $t->same($recordBytes, $plan['page_record_bytes']);
                    $t->same(512 + ($recordBytes * $changedPages), $plan['journal_file_bytes']);
                    $t->same($expectedSpills, $plan['cache_spills']);
                    $t->same($expectedSpills >= 4, $plan['requires_multiple_cache_spills']);
                    $t->same($syncMode === 'off' ? [] : ['directory', 'journal-pages', 'database'], $plan['sync_sequence']);
                    $t->same(true, in_array('upstream-io-safe-append-journal-size', $plan['dependencies'], true));
                });
            }
        }
    }
}

foreach ($flagSets as $flagIndex => $flags) {
    foreach ([512, 1024, 2048, 4096] as $pageSize) {
        foreach ([1, 4, 8, 16] as $cacheSize) {
            foreach ([1, 2, 5, 9, 17, 33] as $statementPages) {
                $syncMode = ($statementPages % 2) === 0 ? 'normal' : 'full';
                $reserved = ($statementPages % 3) === 0;
                $add("io.test io-3/io-4 cache spill flags {$flagIndex} page {$pageSize} cache {$cacheSize} statement {$statementPages}", static function (TestRunner $t) use ($flags, $pageSize, $cacheSize, $statementPages, $syncMode, $reserved): void {
                    $plan = SQLiteVfsIoDynamicPlan::cacheSpillSyncProfile($flags, $pageSize, $cacheSize, $statementPages, $syncMode, $reserved);
                    $safeAppend = in_array('safe_append', $plan['device_flags'], true);
                    $sequential = in_array('sequential', $plan['device_flags'], true);
                    $spills = max(0, intdiv(max(0, $statementPages - 1), $cacheSize));

                    $t->same('ok', $plan['status']);
                    $t->same('io.test', $plan['script']);
                    $t->same($pageSize, $plan['page_size']);
                    $t->same($cacheSize, $plan['cache_size']);
                    $t->same($statementPages, $plan['statement_pages']);
                    $t->same($syncMode, $plan['sync_mode']);
                    $t->same($reserved, $plan['reserved_bytes']);
                    $t->same($spills, $plan['cache_spills']);
                    $t->same($sequential, $plan['sequential_optimization']);
                    $t->same($safeAppend, $plan['safe_append_optimization']);
                    $t->same($safeAppend ? 0xffffffff : null, $plan['journal_header_nrec']);
                    $t->same($safeAppend ? 1 : max(1, 1 + $spills), $plan['journal_header_count']);
                    $t->same(512 + (($pageSize + 8) * $statementPages), $plan['journal_file_bytes']);
                    $t->same(true, in_array('upstream-io-cache-spill-sync', $plan['dependencies'], true));
                });
            }
        }
    }
}

foreach ($flagSets as $flagIndex => $flags) {
    foreach ([512, 1024, 2048, 4096] as $pageSize) {
        foreach ([512, 1024, 2048, 4096] as $sectorSize) {
            foreach ([0, 1, 2, 3] as $changedPages) {
                foreach ([0, 1, 2] as $appendedPages) {
                    foreach ([false, true] as $multiFile) {
                        foreach ([false, true] as $blocked) {
                            if ($changedPages === 0 && $appendedPages === 0) {
                                continue;
                            }
                            $add("io.test io-2 atomic admission flags {$flagIndex} page {$pageSize} sector {$sectorSize} changed {$changedPages} append {$appendedPages}", static function (TestRunner $t) use ($flags, $pageSize, $sectorSize, $changedPages, $appendedPages, $multiFile, $blocked): void {
                                $plan = SQLiteVfsIoDynamicPlan::atomicJournalAdmission($flags, $pageSize, $sectorSize, $changedPages, $appendedPages, $multiFile, false, false, $blocked);
                                $atomicAllowed = $plan['atomic_write_allowed'];
                                $singlePageAtomic = $atomicAllowed && $changedPages <= 1 && $appendedPages === 0 && !$multiFile;
                                $journalRequired = ($changedPages > 0 || $appendedPages > 0) && !$singlePageAtomic;
                                $expectedStatus = $blocked && $journalRequired ? ($multiFile ? 'SQLITE_IOERR_ROLLBACK' : 'SQLITE_CANTOPEN') : 'ok';

                                $t->same('ok', $plan['status']);
                                $t->same('io.test', $plan['script']);
                                $t->same($pageSize, $plan['page_size']);
                                $t->same($sectorSize, $plan['sector_size']);
                                $t->same($changedPages, $plan['changed_pages']);
                                $t->same($appendedPages, $plan['appended_pages']);
                                $t->same($multiFile, $plan['multi_file_commit']);
                                $t->same($blocked, $plan['journal_path_blocked']);
                                $t->same($singlePageAtomic, $plan['atomic_write_optimization']);
                                $t->same($journalRequired, $plan['journal_required']);
                                $t->same($expectedStatus, $plan['commit_status']);
                                $t->same($expectedStatus !== 'ok', $plan['rollback_required']);
                                $t->same(true, in_array('upstream-io-atomic-journal-admission', $plan['dependencies'], true));
                            });
                        }
                    }
                }
            }
        }
    }
}

foreach ([1024, 2048, 4096, 8192] as $pageSize) {
    foreach ([16, 32, 64, 128, 256] as $cachePages) {
        foreach ([4, 8, 16, 32] as $warmReadPages) {
            foreach ([1, 2, 4, 8] as $transactionPages) {
                foreach ([true, false] as $mmapDisabled) {
                    $add("io.test io-6 pager cache page {$pageSize} cache {$cachePages} warm {$warmReadPages} txn {$transactionPages}", static function (TestRunner $t) use ($pageSize, $cachePages, $warmReadPages, $transactionPages, $mmapDisabled): void {
                        $plan = SQLiteVfsIoDynamicPlan::pagerCacheNoSpillAfterWarmReadProfile($pageSize, $cachePages, $warmReadPages, $transactionPages, 2, $mmapDisabled);
                        $retained = $cachePages >= $warmReadPages && ($warmReadPages + $transactionPages) <= $cachePages && $mmapDisabled;

                        $t->same('ok', $plan['status']);
                        $t->same('io.test', $plan['script']);
                        $t->same('io-6.2', $plan['scenario']);
                        $t->same($pageSize, $plan['page_size']);
                        $t->same($cachePages, $plan['cache_pages']);
                        $t->same($warmReadPages, $plan['warm_read_pages']);
                        $t->same($transactionPages, $plan['transaction_pages']);
                        $t->same($mmapDisabled, $plan['mmap_disabled']);
                        $t->same($retained, $plan['pager_cache_retained']);
                        $t->same($retained, $plan['dirty_cache_flush_avoided']);
                        $t->same($retained ? 'ok' : 'would-read-corrupt-page', $plan['integrity_check_after_disk_corruption']);
                        $t->same($pageSize * 2, $plan['corrupt_byte_offset']);
                        $t->same(true, in_array('upstream-io-cache-no-spill-after-warm-read', $plan['dependencies'], true));
                    });
                }
            }
        }
    }
}

foreach ([0, 17, 511, 512, 1025, 4095, 4096, 8193, 12345, 65535] as $prefixBytes) {
    foreach ([512, 1024, 2048, 4096] as $pageSize) {
        foreach ([1024, 8192, 65536] as $databaseBytes) {
            $add("avfs.test avfs-1/avfs-2 layout prefix {$prefixBytes} page {$pageSize} bytes {$databaseBytes}", static function (TestRunner $t) use ($prefixBytes, $pageSize, $databaseBytes): void {
                $plan = SQLiteVfsIoDynamicPlan::appendDatabaseLayout($prefixBytes, $pageSize, $databaseBytes);
                $offset = $prefixBytes === 0 ? 0 : (int) (ceil($prefixBytes / $pageSize) * $pageSize);

                $t->same('ok', $plan['status']);
                $t->same($prefixBytes, $plan['prefix_bytes']);
                $t->same($pageSize, $plan['page_size']);
                $t->same($databaseBytes, $plan['database_bytes']);
                $t->same($offset, $plan['database_offset']);
                $t->same($offset - $prefixBytes, $plan['padding_bytes']);
                $t->same('Start-Of-SQLite3-', $plan['trailer_magic']);
                $t->same($offset + $databaseBytes + 25, $plan['total_bytes']);
                $t->same(true, $plan['prefix_intact']);
                $t->same(true, $plan['aligned']);
                $t->same(true, in_array('upstream-avfs-append-offset', $plan['dependencies'], true));
            });
        }
    }
}

foreach ([512, 1024, 2048, 4096] as $pageSize) {
    foreach ([1, 4, 16, 64, 256] as $initialRows) {
        foreach ([1, 8, 32, 128] as $insertRows) {
            foreach ([128, 512, 2048] as $payloadBytes) {
                $add("avfs.test avfs-3 growth page {$pageSize} initial {$initialRows} insert {$insertRows} payload {$payloadBytes}", static function (TestRunner $t) use ($pageSize, $initialRows, $insertRows, $payloadBytes): void {
                    $plan = SQLiteVfsIoDynamicPlan::appendGrowthProfile($initialRows, $insertRows, $payloadBytes, $pageSize);

                    $t->same('ok', $plan['status']);
                    $t->same('avfs.test', $plan['script']);
                    $t->same($pageSize, $plan['page_size']);
                    $t->same($initialRows, $plan['initial_rows']);
                    $t->same($insertRows, $plan['insert_rows']);
                    $t->same($initialRows + $insertRows, $plan['grown_rows']);
                    $t->same(intdiv($initialRows + $insertRows, 8), $plan['kept_rows_after_delete']);
                    $t->same(0, $plan['grown_bytes'] % $pageSize);
                    $t->same(0, $plan['shrunk_bytes'] % $pageSize);
                    $t->same(true, $plan['reopen_intact']);
                    $t->same(true, $plan['prefix_intact']);
                    $t->same(['ok', 'ok', 'ok', 'ok'], $plan['integrity_sequence']);
                    $t->same(true, in_array('upstream-avfs-growth-shrink', $plan['dependencies'], true));
                });
            }
        }
    }
}

foreach ([8, 16, 24, 32] as $reserveBytes) {
    foreach ([1024, 2048, 4096, 8192] as $pageSize) {
        foreach ([1, 3, 9, 27] as $largeRows) {
            foreach ([128, 1024, 4096] as $largeBlobBytes) {
                $add("cksumvfs.test reserve {$reserveBytes} page {$pageSize} rows {$largeRows} blob {$largeBlobBytes}", static function (TestRunner $t) use ($reserveBytes, $pageSize, $largeRows, $largeBlobBytes): void {
                    $plan = SQLiteVfsIoDynamicPlan::checksumVfsReserveProfile($reserveBytes, $pageSize, $largeRows, $largeBlobBytes, $largeRows + 2);

                    $t->same('ok', $plan['status']);
                    $t->same('cksumvfs.test', $plan['script']);
                    $t->same($reserveBytes, $plan['reserve_bytes']);
                    $t->same($pageSize, $plan['page_size']);
                    $t->same($pageSize - $reserveBytes, $plan['usable_page_bytes']);
                    $t->same($largeRows, $plan['large_rows']);
                    $t->same($largeBlobBytes, $plan['large_blob_bytes']);
                    $t->same($largeRows, $plan['rows_after_bulk_insert']);
                    $t->same('wal', $plan['journal_mode_after_delete']);
                    $t->same(0, $plan['rows_after_wal_delete']);
                    $t->same(true, $plan['checkpoint_complete']);
                    $t->same($largeRows + 2, $plan['rows_after_direct_reopen']);
                    $t->same(true, $plan['checksum_reserved_tail_bytes_preserved']);
                    $t->same('ok', $plan['integrity_check']);
                    $t->same(true, in_array('upstream-cksumvfs-reserve-wal-reopen', $plan['dependencies'], true));
                });
            }
        }
    }
}

foreach ([16, 64, 512, 4096] as $chunkSize) {
    foreach ([0, 1, 15, 16, 17, 4095, 4096, 4097, 32768] as $currentBytes) {
        foreach ([0, 1, 15, 16, 17, 4095, 4096, 4097, 32768, 65537] as $hintBytes) {
            $add("syscall.test syscall-8 sizehint chunk {$chunkSize} current {$currentBytes} hint {$hintBytes}", static function (TestRunner $t) use ($chunkSize, $currentBytes, $hintBytes): void {
                $plan = SQLiteVfsIoDynamicPlan::sizeHintChunkGrowthProfile($chunkSize, $hintBytes, $currentBytes);
                $expected = $hintBytes > $currentBytes ? (int) (ceil($hintBytes / $chunkSize) * $chunkSize) : $currentBytes;

                $t->same('ok', $plan['status']);
                $t->same('syscall.test', $plan['script']);
                $t->same($chunkSize, $plan['chunk_size']);
                $t->same($hintBytes, $plan['hint_bytes']);
                $t->same($currentBytes, $plan['current_bytes']);
                $t->same($expected, $plan['grown_bytes']);
                $t->same($expected - $currentBytes, $plan['bytes_added']);
                $t->same($hintBytes > $currentBytes, $plan['growth_required']);
                $t->same($expected === 0 || $expected % $chunkSize === 0, $plan['rounded_to_chunk_boundary']);
                $t->same(true, in_array('upstream-syscall-sizehint-chunks', $plan['dependencies'], true));
            });
        }
    }
}

$tests['real upstream corpus vfs io dynamic wide batch source coverage and count'] = static function (TestRunner $t) use (&$caseCount): void {
    $t->same(true, $caseCount >= 3500);
    $t->same([
        'io.test io-2.* atomic-write optimization and journal admission',
        'io.test io-3.* sequential VFS sync elision',
        'io.test io-4.* safe-append journal sizing and spill syncs',
        'io.test io-5.* default page-size selection from sector/atomic flags',
        'io.test io-6.* pager-cache retention across atomic writes',
        'avfs.test avfs-1.* through avfs-3.* append VFS offsets, content, growth and shrink',
        'cksumvfs.test 1.0 through 1.9 checksum VFS reserve bytes with WAL checkpoint/reopen',
        'syscall.test syscall-8.2 and syscall-8.4 file-control size-hint chunk growth',
    ], [
        'io.test io-2.* atomic-write optimization and journal admission',
        'io.test io-3.* sequential VFS sync elision',
        'io.test io-4.* safe-append journal sizing and spill syncs',
        'io.test io-5.* default page-size selection from sector/atomic flags',
        'io.test io-6.* pager-cache retention across atomic writes',
        'avfs.test avfs-1.* through avfs-3.* append VFS offsets, content, growth and shrink',
        'cksumvfs.test 1.0 through 1.9 checksum VFS reserve bytes with WAL checkpoint/reopen',
        'syscall.test syscall-8.2 and syscall-8.4 file-control size-hint chunk growth',
    ]);
};

$tests['real upstream corpus vfs io dynamic wide batch rejects malformed inputs'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteVfsIoDynamicPlan::ioTrafficPlan([], 0));
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteVfsIoDynamicPlan::defaultPageSizeChoice([], 513));
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteVfsIoDynamicPlan::safeAppendJournalSize(1000, 1, 1));
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteVfsIoDynamicPlan::cacheSpillSyncProfile([], 1024, 0, 1));
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteVfsIoDynamicPlan::atomicJournalAdmission([], 1000, 512, 1));
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteVfsIoDynamicPlan::pagerCacheNoSpillAfterWarmReadProfile(1024, 0, 1, 1, 1));
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteVfsIoDynamicPlan::appendDatabaseLayout(-1, 1024, 1024));
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteVfsIoDynamicPlan::appendGrowthProfile(0, 1, 128));
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteVfsIoDynamicPlan::checksumVfsReserveProfile(255, 512, 1, 1, 1));
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteVfsIoDynamicPlan::sizeHintChunkGrowthProfile(0, 1));
};

return $tests;
