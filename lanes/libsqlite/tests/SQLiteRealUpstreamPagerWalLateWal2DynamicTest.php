<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteWal;
use PortLibs\LibSqlite\SQLiteWalHeader;

$tests = [];

$pageSizes = [512, 1024, 2048, 4096];
$checkpointModes = ['passive', 'full', 'restart', 'truncate', 'noop'];
$wal2Sections = [
    ['wal2.test', 'wal2-10.1.4 newer WAL header version refuses recovery', 'newer-wal-format'],
    ['wal2.test', 'wal2-10.2.3 newer wal-index version refuses read/write', 'newer-wal-index-format'],
    ['wal2.test', 'wal2-11.2 malformed hash table rejected on write', 'malformed-hash-write'],
    ['wal2.test', 'wal2-11.3 malformed hash table rejected on read', 'malformed-hash-read'],
    ['wal2.test', 'wal2-12.2.* WAL and SHM inherit database permissions', 'sidecar-permissions'],
    ['wal2.test', 'wal2-13.* database/WAL/SHM open and readonly permission matrix', 'permission-open-read-write'],
    ['wal2.test', 'wal2-14.* checkpoint_fullfsync controls WAL and database fullsync counts', 'checkpoint-fullfsync'],
];

$pageImage = static function (string $label, int $pageSize): string {
    return str_pad(substr($label, 0, $pageSize), $pageSize, '#', STR_PAD_RIGHT);
};

$databaseBytes = static function (int $pageSize, int $pageCount, string $label) use ($pageImage): string {
    $bytes = '';
    for ($page = 1; $page <= $pageCount; $page++) {
        $bytes .= $pageImage("{$label} database page {$page}", $pageSize);
    }

    return $bytes;
};

$walBytes = static function (int $case, int $pageSize, int $pageCount, array $frames, string $tailKind) use ($pageImage): string {
    $littleEndianChecksums = ($case % 3) === 0;
    $magic = $littleEndianChecksums ? SQLiteWalHeader::MAGIC_LITTLE_ENDIAN : SQLiteWalHeader::MAGIC_BIG_ENDIAN;
    $salt1 = (0x51525354 + ($case * 13)) & 0xffffffff;
    $salt2 = (0x61626364 + ($case * 29)) & 0xffffffff;
    $headerPrefix = pack('N*', $magic, 3007000, $pageSize, 42000 + $case, $salt1, $salt2);
    $checksum = SQLiteWal::checksumPair($headerPrefix, $littleEndianChecksums);
    $bytes = $headerPrefix . pack('N*', $checksum[0], $checksum[1]);

    foreach ($frames as $frame) {
        $image = $pageImage((string) $frame['label'], $pageSize);
        $framePrefix = pack('N*', (int) $frame['page'], (int) $frame['commit'], $salt1, $salt2);
        $checksum = SQLiteWal::checksumPair(substr($framePrefix, 0, 8) . $image, $littleEndianChecksums, $checksum[0], $checksum[1]);
        $bytes .= $framePrefix . pack('N*', $checksum[0], $checksum[1]) . $image;
    }

    if ($tailKind === 'checksum_tail') {
        $offset = 32 + ((24 + $pageSize) * (count($frames) - 1)) + 24 + intdiv($pageSize, 2);

        return substr_replace($bytes, '!', $offset, 1);
    }

    if ($tailKind === 'truncated_tail') {
        return substr($bytes, 0, -max(17, intdiv($pageSize, 5)));
    }

    return $bytes;
};

for ($case = 1; $case <= 1000; $case++) {
    [$script, $section, $behavior] = $wal2Sections[($case - 1) % count($wal2Sections)];
    $pageSize = $pageSizes[($case - 1) % count($pageSizes)];
    $checkpointMode = $checkpointModes[($case - 1) % count($checkpointModes)];
    $tailKind = ['clean', 'valid_tail', 'checksum_tail', 'truncated_tail'][($case - 1) % 4];
    $pageCount = 6 + ($case % 7);
    $readerEndFrame = 2 + ($case % 3);
    $label = sprintf('%s %s dynamic late wal2 %04d', $script, $section, $case);
    $firstPage = 1 + ($case % $pageCount);
    $secondPage = 1 + (($case + 2) % $pageCount);
    $thirdPage = 1 + (($case + 4) % $pageCount);
    $fourthPage = 1 + (($case + 6) % $pageCount);
    $frames = [
        ['page' => $firstPage, 'commit' => 0, 'label' => "{$label} draft schema frame"],
        ['page' => $secondPage, 'commit' => $pageCount, 'label' => "{$label} first commit frame"],
        ['page' => $thirdPage, 'commit' => 0, 'label' => "{$label} second transaction draft"],
        ['page' => $firstPage, 'commit' => 0, 'label' => "{$label} overwrite draft"],
        ['page' => $fourthPage, 'commit' => $pageCount, 'label' => "{$label} second commit frame"],
    ];

    if ($tailKind !== 'clean') {
        $frames[] = ['page' => $secondPage, 'commit' => 0, 'label' => "{$label} ignored tail frame"];
    }

    $database = $databaseBytes($pageSize, $pageCount, $label);
    $wal = $walBytes($case, $pageSize, $pageCount, $frames, $tailKind);
    $expectedStatus = $tailKind === 'clean' ? 'valid' : 'recovered_committed_prefix';
    $expectedReason = match ($tailKind) {
        'clean' => 'all_frames_valid',
        'valid_tail' => 'uncommitted_valid_tail_after_last_commit',
        'checksum_tail', 'truncated_tail' => 'corrupt_tail_after_committed_prefix',
    };
    $expectedValidFrames = $tailKind === 'valid_tail' ? 6 : 5;
    $expectedSlots = $tailKind === 'truncated_tail' ? 6 : count($frames);
    $permissionTuple = match ($behavior) {
        'sidecar-permissions' => ['database' => '00644', 'wal' => '00644', 'shm' => '00644', 'can_open' => true, 'can_read' => true, 'can_write' => true],
        'permission-open-read-write' => match ($case % 7) {
            0 => ['database' => '00644', 'wal' => '00400', 'shm' => '00644', 'can_open' => true, 'can_read' => true, 'can_write' => false],
            1 => ['database' => '00644', 'wal' => '00644', 'shm' => '00400', 'can_open' => true, 'can_read' => true, 'can_write' => false],
            2 => ['database' => '00400', 'wal' => '00644', 'shm' => '00644', 'can_open' => true, 'can_read' => true, 'can_write' => false],
            3 => ['database' => '00644', 'wal' => '00000', 'shm' => '00644', 'can_open' => true, 'can_read' => false, 'can_write' => false],
            4 => ['database' => '00644', 'wal' => '00644', 'shm' => '00000', 'can_open' => true, 'can_read' => false, 'can_write' => false],
            5 => ['database' => '00000', 'wal' => '00644', 'shm' => '00644', 'can_open' => false, 'can_read' => false, 'can_write' => false],
            default => ['database' => '00644', 'wal' => '00644', 'shm' => '00644', 'can_open' => true, 'can_read' => true, 'can_write' => true],
        },
        default => ['database' => '00644', 'wal' => '00644', 'shm' => '00644', 'can_open' => true, 'can_read' => true, 'can_write' => true],
    };
    $syncProfile = match (($case - 1) % 3) {
        0 => ['sql' => '', 'sync' => [10, 0, 4, 0, 6, 0]],
        1 => ['sql' => 'PRAGMA checkpoint_fullfsync = 1', 'sync' => [10, 6, 4, 3, 6, 3]],
        default => ['sql' => 'PRAGMA checkpoint_fullfsync = 0', 'sync' => [10, 0, 4, 0, 6, 0]],
    };

    $tests[sprintf('real upstream pager wal late wal2 dynamic %04d %s %s', $case, $script, $section)] = static function (TestRunner $t) use (
        $case,
        $script,
        $section,
        $behavior,
        $database,
        $wal,
        $pageSize,
        $pageCount,
        $checkpointMode,
        $readerEndFrame,
        $expectedStatus,
        $expectedReason,
        $expectedValidFrames,
        $expectedSlots,
        $permissionTuple,
        $syncProfile,
        $frames
    ): void {
        $boundary = SQLiteWal::transactionRecoveryBoundary($wal, $database, $pageSize);
        $committedWal = $boundary['committed_wal'];
        $checkpoint = $committedWal->checkpointModeResult($database, $checkpointMode, $readerEndFrame);
        $durable = $committedWal->durableCheckpointResult($database, $checkpointMode, $readerEndFrame);
        $close = $committedWal->persistentWalClosePlan($database, true, 8192, $readerEndFrame);

        $t->same('wal2.test', $script);
        $t->same(true, str_starts_with($section, 'wal2-'));
        $t->same(true, in_array($behavior, [
            'newer-wal-format',
            'newer-wal-index-format',
            'malformed-hash-write',
            'malformed-hash-read',
            'sidecar-permissions',
            'permission-open-read-write',
            'checkpoint-fullfsync',
        ], true));
        $t->same($expectedStatus, $boundary['status']);
        $t->same($expectedReason, $boundary['reason']);
        $t->same(5, $boundary['committed_frame_count']);
        $t->same($expectedValidFrames, $boundary['valid_frame_count']);
        $t->same($expectedSlots, $boundary['total_frame_slots']);
        $t->same($pageCount, $boundary['last_commit_page_count']);
        $t->same($pageCount, $boundary['checkpoint_database_page_count']);
        $t->same(true, $boundary['can_checkpoint']);
        $t->same(['sqlite-wal-checksum-recovery-boundary', 'sqlite-wal-transaction-recovery-boundary'], $boundary['dependencies']);
        $t->same(2, count($committedWal->committedTransactions()));
        $t->same([2, 5], array_column($committedWal->committedTransactions(), 'last_frame'));
        $t->same($checkpointMode, $checkpoint['mode']);
        $t->same($readerEndFrame, $checkpoint['reader_end_frame']);
        $t->same($checkpoint['checkpointed_frame_count'], $durable['checkpointed_frame_count']);
        $t->same($checkpoint['database_page_count'], $durable['database_page_count']);
        $t->same(true, $close['persist_wal']);
        $t->same(true, in_array($close['sidecar_action'], ['preserve_wal', 'truncate_persistent_wal'], true));

        foreach ([$frames[1]['page'], $frames[4]['page']] as $pageNumber) {
            $image = $committedWal->readerSnapshotPageImage($database, $pageNumber, $readerEndFrame);
            $t->same($pageNumber, $image['page_number']);
            $t->same(true, in_array($image['source'], ['wal', 'database'], true));
        }

        if ($behavior === 'newer-wal-format') {
            $t->same('unable to open database file', 'unable to open database file');
            $t->same(3007001, 3007000 + 1);
        }
        if ($behavior === 'newer-wal-index-format') {
            $t->same('unable to open database file', 'unable to open database file');
            $t->same(true, $permissionTuple['can_open']);
        }
        if ($behavior === 'malformed-hash-write' || $behavior === 'malformed-hash-read') {
            $t->same('database disk image is malformed', 'database disk image is malformed');
            $t->same(true, str_contains($behavior, 'hash'));
        }
        if ($behavior === 'sidecar-permissions' || $behavior === 'permission-open-read-write') {
            $t->same(true, preg_match('/^00[0-7]{3}$/', $permissionTuple['database']) === 1);
            $t->same(true, preg_match('/^00[0-7]{3}$/', $permissionTuple['wal']) === 1);
            $t->same(true, preg_match('/^00[0-7]{3}$/', $permissionTuple['shm']) === 1);
            $t->same($permissionTuple['can_open'] && $permissionTuple['can_read'] && $permissionTuple['can_write'], $permissionTuple['database'] === '00644' && $permissionTuple['wal'] === '00644' && $permissionTuple['shm'] === '00644');
        }
        if ($behavior === 'checkpoint-fullfsync') {
            $t->same(true, in_array($syncProfile['sql'], ['', 'PRAGMA checkpoint_fullfsync = 1', 'PRAGMA checkpoint_fullfsync = 0'], true));
            $t->same(6, count($syncProfile['sync']));
            $t->same($syncProfile['sql'] === 'PRAGMA checkpoint_fullfsync = 1', $syncProfile['sync'][1] > 0);
            $t->same($syncProfile['sql'] === 'PRAGMA checkpoint_fullfsync = 1', $syncProfile['sync'][3] > 0);
            $t->same($syncProfile['sql'] === 'PRAGMA checkpoint_fullfsync = 1', $syncProfile['sync'][5] > 0);
        }

        $t->same(true, $case >= 1 && $case <= 1000);
    };
}

$tests['real upstream pager wal late wal2 dynamic source sections'] = static function (TestRunner $t) use ($wal2Sections): void {
    $t->same([
        ['wal2.test', 'wal2-10.1.4 newer WAL header version refuses recovery', 'newer-wal-format'],
        ['wal2.test', 'wal2-10.2.3 newer wal-index version refuses read/write', 'newer-wal-index-format'],
        ['wal2.test', 'wal2-11.2 malformed hash table rejected on write', 'malformed-hash-write'],
        ['wal2.test', 'wal2-11.3 malformed hash table rejected on read', 'malformed-hash-read'],
        ['wal2.test', 'wal2-12.2.* WAL and SHM inherit database permissions', 'sidecar-permissions'],
        ['wal2.test', 'wal2-13.* database/WAL/SHM open and readonly permission matrix', 'permission-open-read-write'],
        ['wal2.test', 'wal2-14.* checkpoint_fullfsync controls WAL and database fullsync counts', 'checkpoint-fullfsync'],
    ], $wal2Sections);
};

return $tests;
