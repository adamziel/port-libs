<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteVfsIoDynamicPlan;

$tests = [];

$mmapCases = [
    1 => [67108864, 0, '8|12', true, false],
    2 => [53248, 0, '15[34]', true, true],
    3 => [0, 0, '344', false, false],
    4 => [67108864, 67108864, '8|12', true, false],
    5 => [53248, 67108864, '15[34]', true, true],
    6 => [0, 67108864, '344', false, false],
];

$caseNo = 0;
foreach (range(1, 167) as $round) {
    foreach ($mmapCases as $case => [$mmapSize, $peerMmapSize, $readPattern, $usesMmap, $partialMmap]) {
        $caseNo++;
        $tests[sprintf('real upstream corpus vfs mmap read dynamic %04d mmap1 case %d round %03d', $caseNo, $case, $round)] = static function (TestRunner $t) use ($case, $mmapSize, $peerMmapSize, $readPattern, $usesMmap, $partialMmap): void {
            $profile = SQLiteVfsIoDynamicPlan::mmapReadGrowthProfile($case, $mmapSize, $peerMmapSize);

            $t->same('ok', $profile['status']);
            $t->same('mmap1.test', $profile['script']);
            $t->same('mmap1-1.' . $case, $profile['scenario']);
            $t->same(['mmap1.test 1.' . $case . '.1', 'mmap1.test 1.' . $case . '.2', 'mmap1.test 1.' . $case . '.3', 'mmap1.test 1.' . $case . '.4', 'mmap1.test 1.' . $case . '.5'], $profile['upstream']);
            $t->same(1024, $profile['page_size']);
            $t->same(true, $profile['auto_vacuum']);
            $t->same($mmapSize, $profile['connection_mmap_size']);
            $t->same($peerMmapSize, $profile['peer_mmap_size']);
            $t->same($usesMmap, $profile['uses_mmap']);
            $t->same($partialMmap, $profile['partial_mmap']);
            $t->same(32, $profile['initial_rows']);
            $t->same(16, $profile['after_delete_rows']);
            $t->same(32, $profile['after_grow_rows']);
            $t->same(64, $profile['after_second_grow_rows']);
            $t->same(77, $profile['initial_page_count']);
            $t->same(42, $profile['after_delete_page_count']);
            $t->same(79, $profile['after_grow_page_count']);
            $t->same(149, $profile['after_second_grow_page_count']);
            $t->same(['ok', 'ok', 'ok', 'ok'], $profile['integrity_sequence']);
            $t->same($readPattern, $profile['read_count_pattern']);
            $t->same(true, $profile['stale_mapping_survives_truncate']);
            $t->same(true, $profile['mapping_extends_after_peer_growth']);
            $t->same(true, in_array('upstream-mmap1-test', $profile['dependencies'], true));
            $t->same(true, in_array('sqlite-mmap-read-counts', $profile['dependencies'], true));
            $t->same(true, in_array('vfs-io-dynamic-real-corpus', $profile['dependencies'], true));
        };
    }
}

foreach (range(1, 19) as $faultIndex) {
    foreach (['mmap', 'mremap'] as $syscall) {
        $tests[sprintf('real upstream corpus vfs mmap read dynamic mmap2 %s fault %02d logs syscall failure', $syscall, $faultIndex)] = static function (TestRunner $t) use ($syscall, $faultIndex): void {
            $profile = SQLiteVfsIoDynamicPlan::mmapSyscallFailureProfile($syscall, $faultIndex);
            $failureInjected = $syscall === 'mmap' || $faultIndex % 3 !== 0;

            $t->same('ok', $profile['status']);
            $t->same('mmap2.test', $profile['script']);
            $t->same('mmap2-1.' . $syscall . '.' . $faultIndex, $profile['scenario']);
            $t->same(['mmap2.test 1.' . $syscall . '.' . $faultIndex . '.1', 'mmap2.test 1.' . $syscall . '.' . $faultIndex . '.2', 'mmap2.test 1.' . $syscall . '.' . $faultIndex . '.3', 'mmap2.test 1.' . $syscall . '.' . $faultIndex . '.4'], $profile['upstream']);
            $t->same($syscall, $profile['syscall']);
            $t->same($faultIndex, $profile['fault_index']);
            $t->same('ENOMEM', $profile['errno']);
            $t->same(8000000, $profile['mmap_size']);
            $t->same(64, $profile['row_count']);
            $t->same('ok', $profile['integrity_check']);
            $t->same($failureInjected ? 1 : 0, $profile['n_fail']);
            $t->same($failureInjected, $profile['log_matches_syscall']);
            $t->same(true, $profile['connection_reusable_after_fault']);
            $t->same(true, in_array('upstream-mmap2-test', $profile['dependencies'], true));
            $t->same(true, in_array('sqlite-mmap-syscall-faultsim', $profile['dependencies'], true));
            $t->same(true, in_array('vfs-io-dynamic-real-corpus', $profile['dependencies'], true));
        };
    }
}

$tests['real upstream corpus vfs mmap read dynamic vacuum truncates mapped file'] = static function (TestRunner $t): void {
    $profile = SQLiteVfsIoDynamicPlan::mmapVacuumTruncationProfile(1048576);

    $t->same('ok', $profile['status']);
    $t->same('mmap1.test', $profile['script']);
    $t->same('mmap1-6', $profile['scenario']);
    $t->same(['mmap1.test 6.0', 'mmap1.test 6.1', 'mmap1.test 6.2', 'mmap1.test 6.3', 'mmap1.test 6.4', 'mmap1.test 6.5', 'mmap1.test 6.6', 'mmap1.test 6.7'], $profile['upstream']);
    $t->same(4096, $profile['page_size']);
    $t->same(false, $profile['auto_vacuum']);
    $t->same(1048576, $profile['mmap_size']);
    $t->same(1000000, $profile['blob_bytes']);
    $t->same(true, $profile['pre_delete_file_bytes'] > 1000000);
    $t->same($profile['pre_delete_file_bytes'], $profile['post_delete_file_bytes']);
    $t->same(true, $profile['post_vacuum_file_bytes'] < 1000000);
    $t->same(true, $profile['delete_does_not_truncate_file']);
    $t->same(true, $profile['vacuum_truncates_below_blob_size']);
    $t->same(true, $profile['stale_mapping_unmapped_before_truncate']);
    $t->same(true, in_array('sqlite-mmap-vacuum-truncation', $profile['dependencies'], true));
};

$tests['real upstream corpus vfs mmap read dynamic rejects malformed inputs'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteVfsIoDynamicPlan::mmapReadGrowthProfile(0, 0, 0));
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteVfsIoDynamicPlan::mmapReadGrowthProfile(7, 0, 0));
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteVfsIoDynamicPlan::mmapReadGrowthProfile(1, -1, 0));
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteVfsIoDynamicPlan::mmapReadGrowthProfile(1, 0, -1));
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteVfsIoDynamicPlan::mmapReadGrowthProfile(1, 0, 0, 1000));
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteVfsIoDynamicPlan::mmapVacuumTruncationProfile(0));
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteVfsIoDynamicPlan::mmapVacuumTruncationProfile(1, 0));
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteVfsIoDynamicPlan::mmapVacuumTruncationProfile(1, 1, 500));
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteVfsIoDynamicPlan::mmapSyscallFailureProfile('open', 1));
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteVfsIoDynamicPlan::mmapSyscallFailureProfile('mmap', 0));
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteVfsIoDynamicPlan::mmapSyscallFailureProfile('mmap', 1, 0));
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteVfsIoDynamicPlan::mmapSyscallFailureProfile('mmap', 1, 64, 0));
};

return $tests;
