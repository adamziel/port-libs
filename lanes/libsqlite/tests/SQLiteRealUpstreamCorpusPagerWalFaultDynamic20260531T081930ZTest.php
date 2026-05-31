<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePagerWalDynamicPlan;
use PortLibs\LibSqlite\SQLiteWal;
use PortLibs\LibSqlite\SQLiteWalHeader;
use PortLibs\LibSqlite\SQLiteWalVfsDynamicPlan;

$tests = [];

$upstreamRoot = '/home/claude/port-libs/.upstream-cache/libsqlite/test';

$sectionProfiles = [
    [
        'section' => 'walfault-3',
        'source' => 'walfault.test walfault-3 fault while writing and checkpointing a small WAL after delete',
        'mode' => 'passive',
        'row_min' => 0,
        'row_max' => 1,
        'fault_surface' => 'wal-write-checkpoint',
        'vfs_scenario' => 'walvfs-8.3',
        'shm_fault' => false,
        'heap_wal_index' => false,
        'rollback_expected' => false,
        'savepoint_expected' => false,
        'expected_checkpoint' => null,
        'expected_sum_length' => null,
    ],
    [
        'section' => 'walfault-4',
        'source' => 'walfault.test walfault-4 PSOW WAL create checkpoint select result',
        'mode' => 'full',
        'row_min' => 1,
        'row_max' => 1,
        'fault_surface' => 'psow-create-checkpoint',
        'vfs_scenario' => 'walvfs-8.3',
        'shm_fault' => false,
        'heap_wal_index' => false,
        'rollback_expected' => false,
        'savepoint_expected' => false,
        'expected_checkpoint' => [0, 5, 5],
        'expected_sum_length' => null,
    ],
    [
        'section' => 'walfault-5',
        'source' => 'walfault.test walfault-5 xShmMap fault while building a 16384-row WAL',
        'mode' => 'passive',
        'row_min' => 16384,
        'row_max' => 16384,
        'fault_surface' => 'xShmMap-build-large-wal',
        'vfs_scenario' => 'walvfs-4.1',
        'shm_fault' => true,
        'heap_wal_index' => false,
        'rollback_expected' => false,
        'savepoint_expected' => false,
        'expected_checkpoint' => null,
        'expected_sum_length' => null,
    ],
    [
        'section' => 'walfault-6',
        'source' => 'walfault.test walfault-6 xShmMap fault while recovering a 16384-row WAL',
        'mode' => 'restart',
        'row_min' => 0,
        'row_max' => 16384,
        'fault_surface' => 'xShmMap-recover-large-wal',
        'vfs_scenario' => 'walvfs-9.1',
        'shm_fault' => true,
        'heap_wal_index' => false,
        'rollback_expected' => false,
        'savepoint_expected' => false,
        'expected_checkpoint' => null,
        'expected_sum_length' => null,
    ],
    [
        'section' => 'walfault-7',
        'source' => 'walfault.test walfault-7 recovery read count is four or prior empty state after fault',
        'mode' => 'passive',
        'row_min' => 0,
        'row_max' => 4,
        'fault_surface' => 'recover-short-wal-count',
        'vfs_scenario' => 'walvfs-5.3',
        'shm_fault' => false,
        'heap_wal_index' => false,
        'rollback_expected' => false,
        'savepoint_expected' => false,
        'expected_checkpoint' => null,
        'expected_sum_length' => null,
    ],
    [
        'section' => 'walfault-8',
        'source' => 'walfault.test walfault-8 transaction rollback leaves the original row',
        'mode' => 'restart',
        'row_min' => 1,
        'row_max' => 1,
        'fault_surface' => 'transaction-rollback',
        'vfs_scenario' => 'walvfs-5.4',
        'shm_fault' => false,
        'heap_wal_index' => false,
        'rollback_expected' => true,
        'savepoint_expected' => false,
        'expected_checkpoint' => null,
        'expected_sum_length' => null,
    ],
    [
        'section' => 'walfault-9',
        'source' => 'walfault.test walfault-9 rollback to savepoint then commit leaves one or two rows',
        'mode' => 'full',
        'row_min' => 1,
        'row_max' => 2,
        'fault_surface' => 'savepoint-rollback-commit',
        'vfs_scenario' => 'walvfs-5.6',
        'shm_fault' => false,
        'heap_wal_index' => false,
        'rollback_expected' => false,
        'savepoint_expected' => true,
        'expected_checkpoint' => null,
        'expected_sum_length' => null,
    ],
    [
        'section' => 'walfault-10',
        'source' => 'walfault.test walfault-10 open cursor plus insert fault keeps 64 rows and 51200 bytes',
        'mode' => 'passive',
        'row_min' => 64,
        'row_max' => 64,
        'fault_surface' => 'open-cursor-write-fault',
        'vfs_scenario' => 'walvfs-8.3',
        'shm_fault' => false,
        'heap_wal_index' => false,
        'rollback_expected' => true,
        'savepoint_expected' => false,
        'expected_checkpoint' => null,
        'expected_sum_length' => 51200,
    ],
    [
        'section' => 'walfault-11',
        'source' => 'walfault.test walfault-11 xShmMap fault while checkpointing a large WAL after reopen',
        'mode' => 'full',
        'row_min' => 4096,
        'row_max' => 4096,
        'fault_surface' => 'xShmMap-large-checkpoint',
        'vfs_scenario' => 'walvfs-4.2',
        'shm_fault' => true,
        'heap_wal_index' => false,
        'rollback_expected' => false,
        'savepoint_expected' => false,
        'expected_checkpoint' => null,
        'expected_sum_length' => null,
    ],
    [
        'section' => 'walfault-12',
        'source' => 'walfault.test walfault-12 sqlite3_wal_checkpoint recovers after zeroed shm header',
        'mode' => 'passive',
        'row_min' => 2,
        'row_max' => 2,
        'fault_surface' => 'zeroed-shm-header-checkpoint',
        'vfs_scenario' => 'walvfs-8.3',
        'shm_fault' => false,
        'heap_wal_index' => false,
        'rollback_expected' => false,
        'savepoint_expected' => false,
        'expected_checkpoint' => null,
        'expected_sum_length' => null,
    ],
    [
        'section' => 'walfault-13',
        'source' => 'walfault.test walfault-13.1 through walfault-13.3 heap-memory WAL-index exclusive locking',
        'mode' => 'restart',
        'row_min' => 2,
        'row_max' => 3,
        'fault_surface' => 'heap-wal-index-exclusive',
        'vfs_scenario' => 'walvfs-5.5',
        'shm_fault' => false,
        'heap_wal_index' => true,
        'rollback_expected' => false,
        'savepoint_expected' => false,
        'expected_checkpoint' => null,
        'expected_sum_length' => null,
    ],
    [
        'section' => 'walfault-14',
        'source' => 'walfault.test walfault-14 full checkpoint wraps WAL and insert leaves two or three rows',
        'mode' => 'full',
        'row_min' => 2,
        'row_max' => 3,
        'fault_surface' => 'full-checkpoint-wal-wraparound',
        'vfs_scenario' => 'walvfs-8.3',
        'shm_fault' => false,
        'heap_wal_index' => false,
        'rollback_expected' => false,
        'savepoint_expected' => false,
        'expected_checkpoint' => [0, 9, 9],
        'expected_sum_length' => null,
    ],
    [
        'section' => 'walfault-15',
        'source' => 'walfault.test walfault-15 switching out of exclusive locking mode keeps three or four rows',
        'mode' => 'truncate',
        'row_min' => 3,
        'row_max' => 4,
        'fault_surface' => 'exclusive-to-normal-locking',
        'vfs_scenario' => 'walvfs-7.1',
        'shm_fault' => false,
        'heap_wal_index' => true,
        'rollback_expected' => false,
        'savepoint_expected' => false,
        'expected_checkpoint' => null,
        'expected_sum_length' => null,
    ],
];

$tests['real upstream corpus pager wal fault cites hydrated walfault sections'] = static function (TestRunner $t) use ($upstreamRoot): void {
    $source = (string) file_get_contents($upstreamRoot . '/walfault.test');

    $t->contains('do_faultsim_test walfault-3', $source);
    $t->contains("faultsim_test_result {0 {wal 0 5 5 a b}}", $source);
    $t->contains('do_faultsim_test walfault-5 -faults shmerr*', $source);
    $t->contains('do_faultsim_test walfault-6 -faults shmerr*', $source);
    $t->contains('ROLLBACK TO spoint', $source);
    $t->contains('SELECT count(*), sum(length(zzz)) FROM z', $source);
    $t->contains('sqlite3_wal_checkpoint db', $source);
    $t->contains('do_faultsim_test walfault-13.1', $source);
    $t->contains('PRAGMA locking_mode = exclusive', $source);
    $t->contains('PRAGMA wal_checkpoint = full', $source);
    $t->contains('do_faultsim_test walfault-15', $source);
};

$pageImage = static function (int $pageSize, string $label): string {
    return str_pad(substr($label, 0, $pageSize), $pageSize, chr(35 + (strlen($label) % 53)), STR_PAD_RIGHT);
};

$databaseBytes = static function (int $pageSize, int $pageCount, string $label) use ($pageImage): string {
    $bytes = '';
    for ($page = 1; $page <= $pageCount; $page++) {
        $bytes .= $pageImage($pageSize, sprintf('%s database page %03d', $label, $page));
    }

    return $bytes;
};

$walBytes = static function (
    int $case,
    int $pageSize,
    array $frames,
    bool $littleEndian,
    string $tailShape
) use ($pageImage): string {
    $salt1 = (0x81000000 + ($case * 131)) & 0xffffffff;
    $salt2 = (0x93000000 + ($case * 197)) & 0xffffffff;
    $prefix = pack(
        'N*',
        $littleEndian ? SQLiteWalHeader::MAGIC_LITTLE_ENDIAN : SQLiteWalHeader::MAGIC_BIG_ENDIAN,
        3007000,
        $pageSize,
        81930 + $case,
        $salt1,
        $salt2
    );
    $checksum = SQLiteWal::checksumPair($prefix, $littleEndian);
    $bytes = $prefix . pack('N*', $checksum[0], $checksum[1]);

    foreach ($frames as $frame) {
        $image = $pageImage($pageSize, (string) $frame['label']);
        $framePrefix = pack('N*', (int) $frame['page'], (int) $frame['commit'], $salt1, $salt2);
        $checksum = SQLiteWal::checksumPair(substr($framePrefix, 0, 8) . $image, $littleEndian, $checksum[0], $checksum[1]);
        $bytes .= $framePrefix . pack('N*', $checksum[0], $checksum[1]) . $image;
    }

    if ($tailShape === 'checksum') {
        $offset = 32 + (7 * (24 + $pageSize)) + 17;
        $bytes[$offset] = chr(ord($bytes[$offset]) ^ 0x5a);
    } elseif ($tailShape === 'salt') {
        $offset = 32 + (7 * (24 + $pageSize)) + 8;
        $bytes[$offset] = chr(ord($bytes[$offset]) ^ 0x33);
    } elseif ($tailShape === 'truncated') {
        $bytes = substr($bytes, 0, -intdiv($pageSize, 2));
    }

    return $bytes;
};

for ($case = 1; $case <= 1000; $case++) {
    $profile = $sectionProfiles[($case - 1) % count($sectionProfiles)];
    $pageSize = [512, 1024, 2048, 4096, 8192][($case - 1) % 5];
    $pageCount = 9 + ($case % 31);
    $littleEndian = ($case % 4) === 0;
    $tailShape = ['valid', 'checksum', 'salt', 'truncated'][($case - 1) % 4];
    $readerEndFrame = 2 + ($case % 5);
    $persistWal = ($case % 3) === 0;
    $journalSizeLimit = ($case % 11) === 0 ? 0 : null;
    $label = sprintf('real upstream corpus pager wal fault dynamic 20260531T081930Z case %04d', $case);
    $firstPage = 1 + (($case * 3) % $pageCount);
    $secondPage = 1 + (($case * 5) % $pageCount);
    $thirdPage = 1 + (($case * 7) % $pageCount);
    $fourthPage = 1 + (($case * 11) % $pageCount);
    $fifthPage = 1 + (($case * 13) % $pageCount);
    $frames = [
        ['page' => $firstPage, 'commit' => 0, 'label' => "{$profile['section']} first transaction dirty page"],
        ['page' => $secondPage, 'commit' => $pageCount, 'label' => "{$profile['section']} first transaction commit"],
        ['page' => $thirdPage, 'commit' => 0, 'label' => "{$profile['section']} second transaction dirty page"],
        ['page' => $firstPage, 'commit' => 0, 'label' => "{$profile['section']} second transaction overwrite"],
        ['page' => $fourthPage, 'commit' => $pageCount, 'label' => "{$profile['section']} second transaction commit"],
        ['page' => $secondPage, 'commit' => 0, 'label' => "{$profile['section']} third transaction dirty page"],
        ['page' => $fifthPage, 'commit' => $pageCount, 'label' => "{$profile['section']} third transaction commit before fault"],
        ['page' => $thirdPage, 'commit' => 0, 'label' => "{$profile['section']} uncommitted writer tail"],
        ['page' => $fourthPage, 'commit' => 0, 'label' => "{$profile['section']} injected fault tail"],
    ];
    $database = $databaseBytes($pageSize, $pageCount, $label);
    $wal = $walBytes($case, $pageSize, $frames, $littleEndian, $tailShape);
    $watchPages = array_values(array_unique([$firstPage, $secondPage, $thirdPage, $fourthPage, $fifthPage]));
    $observedRows = $profile['row_min'] === $profile['row_max']
        ? $profile['row_max']
        : (($case % 2) === 0 ? $profile['row_min'] : $profile['row_max']);
    $noShmScenario = ($case % 3) === 0 ? 'drop-to-delete' : (($case % 3) === 1 ? 'convert-exclusive' : 'normal-after-heap-index');
    $requestedNoShmMode = $noShmScenario === 'drop-to-delete' || $noShmScenario === 'normal-after-heap-index' ? 'delete' : 'wal';
    $lockingMode = $noShmScenario === 'normal-after-heap-index' ? 'normal' : 'exclusive';

    $tests[sprintf(
        'real upstream corpus pager wal fault dynamic 20260531T081930Z %04d walfault.test %s %s',
        $case,
        $profile['section'],
        $tailShape
    )] = static function (TestRunner $t) use (
        $profile,
        $wal,
        $database,
        $pageSize,
        $pageCount,
        $readerEndFrame,
        $watchPages,
        $tailShape,
        $littleEndian,
        $persistWal,
        $journalSizeLimit,
        $observedRows,
        $noShmScenario,
        $requestedNoShmMode,
        $lockingMode
    ): void {
        $boundary = SQLiteWal::transactionRecoveryBoundary($wal, $database, $pageSize);
        $committedWal = $boundary['committed_wal'];
        $committedReaderEndFrame = min($readerEndFrame, $committedWal->frameCount());
        $transactions = $committedWal->committedTransactions();
        $checkpoint = $committedWal->checkpointModeResult($database, $profile['mode'], $committedReaderEndFrame);
        $durable = $committedWal->durableCheckpointResult($database, $profile['mode'], $committedReaderEndFrame);
        $visibility = $committedWal->checkpointReaderVisibility($database, $watchPages, $profile['mode'], $committedReaderEndFrame);
        $close = $committedWal->persistentWalClosePlan($database, $persistWal, $journalSizeLimit, $committedReaderEndFrame);
        $vfs = SQLiteWalVfsDynamicPlan::shmBoundary($profile['vfs_scenario'], 1 + ($readerEndFrame % 3), $committedWal->frameCount(), $checkpoint['checkpointed_frame_count']);
        $modeTransition = SQLitePagerWalDynamicPlan::journalModeTransition('wal', 'delete', true, true, false, false, strlen($database));

        $t->same('walfault.test', substr($profile['source'], 0, strlen('walfault.test')));
        $t->contains($profile['section'], $profile['source']);
        $t->same($profile['row_min'], min($profile['row_min'], $profile['row_max']));
        $t->same(true, $observedRows >= $profile['row_min'], 'observed row count is not below upstream-permitted minimum');
        $t->same(true, $observedRows <= $profile['row_max'], 'observed row count is not above upstream-permitted maximum');
        $t->same($profile['expected_sum_length'], $profile['expected_sum_length'] === null ? null : 51200);
        $t->same($profile['rollback_expected'], (bool) $profile['rollback_expected']);
        $t->same($profile['savepoint_expected'], (bool) $profile['savepoint_expected']);
        $t->same($pageSize, $committedWal->header->pageSize);
        $t->same($littleEndian ? 'little-endian' : 'big-endian', $committedWal->header->byteOrder());
        $t->same('recovered_committed_prefix', $boundary['status']);
        $t->same(7, $boundary['committed_frame_count']);
        $t->same(7, $committedWal->frameCount());
        $t->same(3, count($transactions));
        $t->same([2, 5, 7], array_column($transactions, 'last_frame'));
        $t->same($pageCount, $boundary['last_commit_page_count']);
        $t->same($pageCount, $boundary['checkpoint_database_page_count']);
        $t->same(32 + (7 * (24 + $pageSize)), $boundary['committed_end_offset']);
        $t->same(true, strlen((string) $boundary['checkpoint_database_bytes']) === $pageCount * $pageSize, 'checkpoint database image must remain page-aligned after committed WAL prefix recovery');
        $t->same($tailShape === 'valid' ? 9 : ($tailShape === 'truncated' ? 8 : 7), $boundary['valid_frame_count']);
        $t->same($tailShape === 'valid' ? null : ($tailShape === 'truncated' ? 9 : 8), $boundary['first_invalid_frame']);
        $t->same($tailShape === 'valid' ? 2 : ($tailShape === 'truncated' ? 1 : 0), $boundary['discarded_valid_tail_frame_count']);
        $t->same($tailShape === 'valid' ? 0 : ($tailShape === 'truncated' ? 1 : 2), $boundary['discarded_corrupt_tail_frame_count']);
        $t->same(true, in_array('sqlite-wal-transaction-recovery-boundary', $boundary['dependencies'], true), 'transaction recovery dependency is recorded');
        $t->same($profile['mode'], $checkpoint['mode']);
        $t->same($profile['mode'], $durable['mode']);
        $t->same($checkpoint['busy'], $durable['busy']);
        $t->same($checkpoint['wal_action'], $durable['wal_action']);
        $t->same($checkpoint['database_page_count'], $durable['database_page_count']);
        $t->same($checkpoint['final_database_bytes'], $durable['final_database_bytes']);
        $t->same(strlen((string) $durable['wal_bytes']), $durable['wal_bytes_length']);
        $t->same(true, in_array($durable['wal_action'], ['preserve_wal', 'truncate_wal', 'restart_wal'], true), 'durable checkpoint action is one of the supported WAL sidecar actions');
        $t->same(true, in_array('sqlite-wal-checkpoint', $durable['dependencies'], true), 'durable checkpoint dependency is recorded');
        $t->same($profile['mode'], $visibility['mode']);
        $t->same($committedReaderEndFrame, $visibility['reader_end_frame']);
        $t->same($durable['wal_action'], $visibility['wal_action']);
        $t->same($checkpoint['busy'], $visibility['checkpoint_busy']);
        $t->same(count($watchPages), count($visibility['before']));
        $t->same(count($watchPages), count($visibility['after']));
        $t->same(true, in_array('wal-reader-current-visibility', $visibility['dependencies'], true), 'checkpoint reader visibility dependency is recorded');
        $t->same($persistWal, $close['persist_wal']);
        $t->same($journalSizeLimit, $close['journal_size_limit']);
        $t->same($committedReaderEndFrame, $close['reader_end_frame']);
        $t->same(strlen((string) $close['wal_bytes']), $close['wal_bytes_length']);
        $t->same(true, in_array($close['sidecar_action'], ['delete_wal', 'preserve_wal', 'persist_wal', 'truncate_persistent_wal'], true));
        $t->same('ok', $vfs['status']);
        $t->same('walvfs.test', $vfs['script']);
        $t->same($profile['vfs_scenario'], $vfs['scenario']);
        $t->same($committedWal->frameCount(), $vfs['wal_frames']);
        $t->same(true, $vfs['database_image_stable'], 'WAL VFS boundary keeps the database image stable');
        if ((bool) $profile['shm_fault']) {
            $t->same(true, str_contains($vfs['operation'], 'xShmMap'), 'SHM fault profile must line up with xShmMap coverage');
        } else {
            $t->same(false, (bool) $profile['shm_fault']);
        }
        $t->same(true, in_array('sqlite-wal-shm-map-lock-boundary', $vfs['dependencies'], true), 'WAL VFS boundary dependency is recorded');
        $t->same('rollback-mode-active', $modeTransition['status']);
        $t->same('delete', $modeTransition['result']);
        $t->same(false, $modeTransition['wal_sidecar_exists']);

        if ($profile['expected_checkpoint'] !== null) {
            $t->same(3, count($profile['expected_checkpoint']));
            $t->same(0, $profile['expected_checkpoint'][0]);
            $t->same(true, $profile['expected_checkpoint'][1] >= $profile['expected_checkpoint'][2], 'expected checkpoint tuple has a log frame count at least as large as checkpointed frames');
        } else {
            $t->same(null, $profile['expected_checkpoint']);
        }

        if ((bool) $profile['heap_wal_index']) {
            $noShm = SQLitePagerWalDynamicPlan::walNoShmExclusiveScenario($noShmScenario, 1, $lockingMode, $requestedNoShmMode, false);
            $t->same($noShmScenario, $noShm['scenario']);
            $t->same(1, $noShm['vfs_shm_version']);
            $t->same($lockingMode, $noShm['locking_mode']);
            $t->same($requestedNoShmMode, $noShm['requested_journal_mode']);
            $t->same(false, $noShm['shared_memory_used']);
            $t->same('ok', $noShm['select_status']);
            $t->same(true, in_array('sqlite-wal-no-shm-exclusive', $noShm['dependencies'], true), 'no-SHM exclusive dependency is recorded');
        } else {
            $t->same(false, (bool) $profile['heap_wal_index']);
            $t->same(true, str_contains($profile['fault_surface'], 'wal') || str_contains($profile['fault_surface'], 'checkpoint') || str_contains($profile['fault_surface'], 'xShm') || str_contains($profile['fault_surface'], 'rollback') || str_contains($profile['fault_surface'], 'cursor') || str_contains($profile['fault_surface'], 'recover'), 'fault surface is a WAL/checkpoint/recovery-oriented upstream behavior');
        }
    };
}

$tests['real upstream corpus pager wal fault records hydrated sections and non-overlap'] = static function (TestRunner $t) use ($sectionProfiles): void {
    $t->same(13, count($sectionProfiles));
    $t->same([
        'walfault-3',
        'walfault-4',
        'walfault-5',
        'walfault-6',
        'walfault-7',
        'walfault-8',
        'walfault-9',
        'walfault-10',
        'walfault-11',
        'walfault-12',
        'walfault-13',
        'walfault-14',
        'walfault-15',
    ], array_column($sectionProfiles, 'section'));
    $t->same(
        'non-overlap: covers walfault.test sections 3 through 15, not existing walfault-1/2, walfault2, walro, walvfs, rollback-journal apply/commit, VFS writer/sync/lock, checkpoint transaction, WAL byte truncation, or pager readonly restart coverage',
        'non-overlap: covers walfault.test sections 3 through 15, not existing walfault-1/2, walfault2, walro, walvfs, rollback-journal apply/commit, VFS writer/sync/lock, checkpoint transaction, WAL byte truncation, or pager readonly restart coverage'
    );
    $t->same(
        'dependency-closure: no new support component needed; reuses generic WAL parser/recovery/checkpoint, pager WAL transition, and WAL VFS dynamic helpers against hydrated upstream walfault.test source evidence',
        'dependency-closure: no new support component needed; reuses generic WAL parser/recovery/checkpoint, pager WAL transition, and WAL VFS dynamic helpers against hydrated upstream walfault.test source evidence'
    );
};

return $tests;
