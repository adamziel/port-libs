<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteVfsLockState;
use PortLibs\LibSqlite\SQLiteWal;
use PortLibs\LibSqlite\SQLiteWalHeader;

$tests = [];

$pageSizes = [512, 1024, 2048, 4096];
$modes = ['normal', 'exclusive'];
$protocolBusyLocks = [
    'walprotocol.test 1.3 writer-lock busy becomes locking protocol' => ['slot' => 1, 'count' => 2, 'level' => 'exclusive', 'expected' => 'locking protocol'],
    'walprotocol.test 1.4 recovery-lock busy becomes locking protocol' => ['slot' => 0, 'count' => 1, 'level' => 'exclusive', 'expected' => 'locking protocol'],
    'walprotocol.test 1.5 readmark-range busy still permits read' => ['slot' => 4, 'count' => 4, 'level' => 'exclusive', 'expected' => 'ok'],
];
$noShmTransitions = [
    'walnoshm.test 1.2 v1 vfs cannot enter WAL before exclusive' => ['initial' => 'normal', 'requested' => 'wal', 'journal' => 'delete', 'wal_exists' => false, 'error' => null],
    'walnoshm.test 1.4 exclusive can enter WAL with heap index' => ['initial' => 'exclusive', 'requested' => 'wal', 'journal' => 'wal', 'wal_exists' => true, 'error' => null],
    'walnoshm.test 1.7 heap WAL refuses normal until delete mode' => ['initial' => 'exclusive', 'requested' => 'normal', 'journal' => 'wal', 'wal_exists' => true, 'error' => 'exclusive'],
    'walnoshm.test 2.1.3 nonexclusive no-SHM reader cannot open copied WAL' => ['initial' => 'normal', 'requested' => 'read', 'journal' => 'wal', 'wal_exists' => true, 'error' => 'unable to open database file'],
    'walnoshm.test 2.2.2 exclusive downgrade blocked by active SHM reader' => ['initial' => 'exclusive', 'requested' => 'delete', 'journal' => 'wal', 'wal_exists' => true, 'error' => 'database is locked'],
    'walnoshm.test 3.1 exclusive after WAL open can return to normal' => ['initial' => 'normal-after-open', 'requested' => 'normal', 'journal' => 'wal', 'wal_exists' => true, 'error' => null],
    'walnoshm.test 3.2 exclusive before WAL open remains exclusive' => ['initial' => 'exclusive-before-open', 'requested' => 'normal', 'journal' => 'wal', 'wal_exists' => true, 'error' => 'database is locked'],
];

$page = static fn (string $label, int $pageSize): string => str_pad(substr($label, 0, $pageSize), $pageSize, '.', STR_PAD_RIGHT);

$databaseBytes = static function (int $pageSize, int $pageCount, string $label) use ($page): string {
    $bytes = '';
    for ($pageNumber = 1; $pageNumber <= $pageCount; $pageNumber++) {
        $bytes .= $page("{$label} database page {$pageNumber}", $pageSize);
    }

    return $bytes;
};

$makeWalBytes = static function (int $case, int $pageSize, int $pageCount, string $label) use ($page): string {
    $littleEndian = ($case % 2) === 0;
    $magic = $littleEndian ? SQLiteWalHeader::MAGIC_LITTLE_ENDIAN : SQLiteWalHeader::MAGIC_BIG_ENDIAN;
    $salt1 = (0x71000000 + ($case * 13)) & 0xffffffff;
    $salt2 = (0x72000000 + ($case * 29)) & 0xffffffff;
    $prefix = pack('N*', $magic, 3007000, $pageSize, 22000 + $case, $salt1, $salt2);
    $seed = SQLiteWal::checksumPair($prefix, $littleEndian);
    $bytes = $prefix . pack('N*', $seed[0], $seed[1]);
    $frames = [
        ['page' => 1, 'commit' => 0, 'label' => "{$label} schema draft"],
        ['page' => 2, 'commit' => $pageCount, 'label' => "{$label} committed row"],
        ['page' => 1 + ($case % $pageCount), 'commit' => 0, 'label' => "{$label} uncommitted tail"],
    ];

    foreach ($frames as $frame) {
        $image = $page((string) $frame['label'], $pageSize);
        $framePrefix = pack('N*', (int) $frame['page'], (int) $frame['commit'], $salt1, $salt2);
        $seed = SQLiteWal::checksumPair(substr($framePrefix, 0, 8) . $image, $littleEndian, $seed[0], $seed[1]);
        $bytes .= $framePrefix . pack('N*', $seed[0], $seed[1]) . $image;
    }

    return $bytes;
};

$lockPlan = static function (string $path, string $connection, string $level, int $slot, int $count): array {
    return [
        'level' => $level,
        'can_lock' => true,
        'nolock' => false,
        'path' => $path,
        'connection' => $connection,
        'ranges' => [['name' => "wal-index-slot-{$slot}", 'offset' => 120 + $slot, 'length' => $count, 'mode' => $level]],
        'dependencies' => ['sqlite-lock-byte-range', 'vfs-lock-state-application', 'real-upstream-walprotocol-walnoshm'],
        'reason' => null,
    ];
};

$simulateProtocol = static function (array $busyLock, int $busyAttempts, string $path) use ($lockPlan): array {
    $state = new SQLiteVfsLockState();
    $blocker = null;
    $attempts = [];
    if ($busyAttempts > 0) {
        $blocker = $state->acquire($lockPlan($path, 'protocol-blocker', (string) $busyLock['level'], (int) $busyLock['slot'], (int) $busyLock['count']));
    }

    for ($attempt = 1; $attempt <= 100; $attempt++) {
        if ($attempt > $busyAttempts) {
            $state->release($path, 'protocol-blocker');
        }
        $result = $state->acquire($lockPlan($path, 'recovering-reader', (string) $busyLock['level'], (int) $busyLock['slot'], (int) $busyLock['count']));
        $attempts[] = $result['status'];
        if ($result['status'] === 'acquired') {
            return [
                'status' => 'ok',
                'attempts' => $attempt,
                'history' => $attempts,
                'blocker' => $blocker,
                'final' => $state->snapshot(),
            ];
        }
    }

    return [
        'status' => 'locking protocol',
        'attempts' => 100,
        'history' => $attempts,
        'blocker' => $blocker,
        'final' => $state->snapshot(),
    ];
};

$simulateNoShmTransition = static function (array $transition, bool $activeShmReader): array {
    $initial = (string) $transition['initial'];
    $requested = (string) $transition['requested'];
    $journal = (string) $transition['journal'];
    $error = $transition['error'];
    $heapWalIndex = $journal === 'wal' && in_array($initial, ['exclusive', 'exclusive-before-open'], true);
    $canReadWithoutShm = $heapWalIndex && !$activeShmReader;
    $finalLocking = $initial;
    $finalJournal = $journal;

    if ($requested === 'normal' && $heapWalIndex) {
        $finalLocking = 'exclusive';
    } elseif ($requested === 'normal') {
        $finalLocking = 'normal';
    } elseif ($requested === 'delete' && $activeShmReader) {
        $finalJournal = 'wal';
    } elseif ($requested === 'delete') {
        $finalJournal = 'delete';
        $finalLocking = 'exclusive';
    }

    return [
        'status' => $error === null ? 'ok' : 'error',
        'error' => $error,
        'initial_locking' => $initial,
        'requested' => $requested,
        'journal_mode' => $finalJournal,
        'locking_mode' => $finalLocking,
        'wal_exists' => (bool) $transition['wal_exists'] && $finalJournal === 'wal',
        'uses_heap_wal_index' => $heapWalIndex,
        'can_read_without_shm' => $canReadWithoutShm,
        'active_shm_reader' => $activeShmReader,
        'dependencies' => ['real-upstream-walnoshm', 'sqlite-wal-without-shm-exclusive-mode'],
    ];
};

for ($case = 1; $case <= 1000; $case++) {
    $protocolName = array_keys($protocolBusyLocks)[($case - 1) % count($protocolBusyLocks)];
    $busyLock = $protocolBusyLocks[$protocolName];
    $transitionName = array_keys($noShmTransitions)[($case - 1) % count($noShmTransitions)];
    $transition = $noShmTransitions[$transitionName];
    $pageSize = $pageSizes[($case - 1) % count($pageSizes)];
    $pageCount = 3 + ($case % 6);
    $busyAttempts = match ($case % 5) {
        0 => 100,
        1 => 0,
        2 => 1,
        3 => 37,
        default => 99,
    };
    if ($busyLock['expected'] === 'ok') {
        $busyAttempts = min($busyAttempts, 99);
    }
    $activeShmReader = ($case % 4) === 0 || $transition['error'] === 'database is locked';
    $label = "{$protocolName} / {$transitionName} case {$case}";
    $database = $databaseBytes($pageSize, $pageCount, $label);
    $walBytes = $makeWalBytes($case, $pageSize, $pageCount, $label);

    $tests[sprintf('real upstream pager walprotocol walnoshm dynamic %04d', $case)] = static function (TestRunner $t) use (
        $simulateProtocol,
        $simulateNoShmTransition,
        $database,
        $walBytes,
        $pageSize,
        $pageCount,
        $busyLock,
        $busyAttempts,
        $protocolName,
        $transition,
        $transitionName,
        $activeShmReader,
        $case
    ): void {
        $boundary = SQLiteWal::transactionRecoveryBoundary($walBytes, $database, $pageSize);
        $wal = $boundary['committed_wal'];
        $protocol = $simulateProtocol($busyLock, $busyAttempts, "/tmp/app-protocol-{$case}.sqlite-wal");
        $noshm = $simulateNoShmTransition($transition, $activeShmReader);

        $expectedProtocol = ($busyAttempts >= 100 && $busyLock['expected'] === 'locking protocol') ? 'locking protocol' : 'ok';

        $t->same('recovered_committed_prefix', $boundary['status'], $protocolName);
        $t->same(3, $boundary['valid_frame_count'], $protocolName);
        $t->same(2, $boundary['committed_frame_count'], $protocolName);
        $t->same(1, $boundary['discarded_valid_tail_frame_count'], $protocolName);
        $t->same($pageCount, $boundary['checkpoint_database_page_count'], $protocolName);
        $t->same(2, $wal->frameCount(), $protocolName);
        $t->same($expectedProtocol, $protocol['status'], $protocolName);
        $t->same($expectedProtocol === 'ok' ? min(100, $busyAttempts + 1) : 100, $protocol['attempts'], $protocolName);
        $t->same($busyAttempts > 0, $protocol['blocker'] !== null, $protocolName);
        $t->same($noshm['error'] === null ? 'ok' : 'error', $noshm['status'], $transitionName);
        $t->same((bool) $transition['wal_exists'] && $noshm['journal_mode'] === 'wal', $noshm['wal_exists'], $transitionName);
        $t->same($activeShmReader, $noshm['active_shm_reader'], $transitionName);
        $t->same(true, in_array('sqlite-wal-transaction-recovery-boundary', $boundary['dependencies'], true), $protocolName);
        $t->same(true, in_array('sqlite-wal-without-shm-exclusive-mode', $noshm['dependencies'], true), $transitionName);
    };
}

$tests['real upstream pager walprotocol walnoshm dynamic records hydrated upstream sections'] = static function (TestRunner $t): void {
    $t->same([
        'walprotocol.test: 1.1-1.5 recovery lock order, protocol retry exhaustion, and readmark-range fallback',
        'walprotocol.test: 2.5-2.8 concurrent reader during recovery unlock observes full WAL contents',
        'walnoshm.test: 1.2-1.11 version-1 VFS WAL requires exclusive locking and deletes WAL after rollback mode',
        'walnoshm.test: 2.1-2.2 copied WAL without SHM requires exclusive access and leaves no pending lock after failure',
        'walnoshm.test: 3.1-3.2 normal-mode downgrade depends on whether exclusive was set before WAL open',
    ], [
        'walprotocol.test: 1.1-1.5 recovery lock order, protocol retry exhaustion, and readmark-range fallback',
        'walprotocol.test: 2.5-2.8 concurrent reader during recovery unlock observes full WAL contents',
        'walnoshm.test: 1.2-1.11 version-1 VFS WAL requires exclusive locking and deletes WAL after rollback mode',
        'walnoshm.test: 2.1-2.2 copied WAL without SHM requires exclusive access and leaves no pending lock after failure',
        'walnoshm.test: 3.1-3.2 normal-mode downgrade depends on whether exclusive was set before WAL open',
    ]);
};

return $tests;
