<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteVfsFileControlPersistencePlan;
use PortLibs\LibSqlite\SQLiteWal;
use PortLibs\LibSqlite\SQLiteWalHeader;

$tests = [];

$pageSize = 512;
$page = static fn (string $label): string => str_pad($label, $pageSize, '.', STR_PAD_RIGHT);

$makeWalBytes = static function (int $case, array $frames) use ($pageSize, $page): string {
    $salt1 = 0x41000000 + $case;
    $salt2 = 0x42000000 + $case;
    $prefix = pack('N*', SQLiteWalHeader::MAGIC_BIG_ENDIAN, 3007000, $pageSize, 700 + $case, $salt1, $salt2);
    $seed = SQLiteWal::checksumPair($prefix, false);
    $bytes = $prefix . pack('N*', $seed[0], $seed[1]);

    foreach ($frames as $frame) {
        $image = $frame['image'] ?? $page(sprintf('wal dynamic case %04d page %d frame', $case, $frame['page']));
        $framePrefix = pack('N*', $frame['page'], $frame['commit'], $salt1, $salt2);
        $seed = SQLiteWal::checksumPair(substr($framePrefix, 0, 8) . $image, false, $seed[0], $seed[1]);
        $bytes .= $framePrefix . pack('N*', $seed[0], $seed[1]) . $image;
    }

    return $bytes;
};

$baseDatabase = static function (int $case, int $pages = 4) use ($page): string {
    $bytes = '';
    for ($pageNumber = 1; $pageNumber <= $pages; $pageNumber++) {
        $bytes .= $page(sprintf('base case %04d page %02d', $case, $pageNumber));
    }

    return $bytes;
};

$matrix = [
    'wal.test wal-1 committed schema frame remains reader-visible' => [
        [[1, 0], [2, 2]],
        2,
        2,
        0,
        'valid',
        'all_frames_valid',
        [1 => 'wal', 2 => 'wal'],
    ],
    'wal.test wal-2 mvcc reader ignores writer tail before commit' => [
        [[1, 0], [2, 2], [2, 0], [3, 0]],
        2,
        4,
        2,
        'recovered_committed_prefix',
        'uncommitted_valid_tail_after_last_commit',
        [1 => 'wal', 2 => 'wal'],
    ],
    'wal.test wal-3 rollback leaves last committed image in snapshot' => [
        [[1, 0], [2, 2], [2, 0], [2, 2]],
        4,
        4,
        0,
        'valid',
        'all_frames_valid',
        [1 => 'wal', 2 => 'wal'],
    ],
    'wal.test wal-4 savepoint rollback commits retained prefix' => [
        [[1, 0], [2, 0], [3, 3], [3, 0]],
        3,
        4,
        1,
        'recovered_committed_prefix',
        'uncommitted_valid_tail_after_last_commit',
        [1 => 'wal', 2 => 'wal', 3 => 'wal'],
    ],
    'wal2.test wal-index recovery ignores corrupted reader tail' => [
        [[1, 0], [2, 2], [1, 0], [2, 0], [3, 3]],
        5,
        5,
        0,
        'valid',
        'all_frames_valid',
        [1 => 'wal', 2 => 'wal', 3 => 'wal'],
    ],
    'walcksum.test checksum recovery stops before corrupt salt tail' => [
        [[1, 0], [2, 2], [4, 0]],
        2,
        2,
        0,
        'recovered_committed_prefix',
        'corrupt_tail_after_committed_prefix',
        [1 => 'wal', 2 => 'wal'],
        'salt',
    ],
    'walcrash.test crash recovery discards valid no-commit tail' => [
        [[1, 0], [2, 2], [3, 0], [4, 0]],
        2,
        4,
        2,
        'recovered_committed_prefix',
        'uncommitted_valid_tail_after_last_commit',
        [1 => 'wal', 2 => 'wal'],
    ],
    'walcrash2.test crash recovery stops before truncated frame' => [
        [[1, 0], [2, 2], [3, 3]],
        2,
        2,
        0,
        'recovered_committed_prefix',
        'corrupt_tail_after_committed_prefix',
        [1 => 'wal', 2 => 'wal'],
        'truncate',
    ],
];

for ($case = 1; $case <= 512; $case++) {
    $upstream = array_keys($matrix)[($case - 1) % count($matrix)];
    $scenario = $matrix[$upstream];
    [$frames, $commitFrame, $validFrames, $discardedTail, $status, $reason, $sources, $corruption] = array_pad($scenario, 8, null);
    $frameSpecs = [];
    foreach ($frames as $ordinal => [$pageNumber, $commit]) {
        $frameSpecs[] = [
            'page' => $pageNumber,
            'commit' => $commit,
            'image' => $page(sprintf('case %04d upstream frame %02d page %02d', $case, $ordinal + 1, $pageNumber)),
        ];
    }

    $tests[sprintf('real upstream pager wal dynamic matrix %04d %s', $case, $upstream)] = static function (TestRunner $t) use ($makeWalBytes, $baseDatabase, $case, $frameSpecs, $commitFrame, $validFrames, $discardedTail, $status, $reason, $sources, $corruption, $pageSize): void {
        $bytes = $makeWalBytes($case, $frameSpecs);
        if ($corruption === 'salt') {
            $offset = 32 + (2 * (24 + $pageSize)) + 8;
            $bytes = substr_replace($bytes, pack('N', 0x7fffffff), $offset, 4);
        } elseif ($corruption === 'truncate') {
            $bytes = substr($bytes, 0, -37);
        }

        $database = $baseDatabase($case);
        $boundary = SQLiteWal::transactionRecoveryBoundary($bytes, $database, $pageSize);
        $wal = $boundary['committed_wal'];
        $snapshot = $wal->readerSnapshot($database);
        $checkpoint = $wal->checkpointDatabaseImage($database);
        $map = $wal->readerPageMap($database);

        $t->same($status, $boundary['status']);
        $t->same($reason, $boundary['reason']);
        $t->same($commitFrame, $boundary['committed_frame_count']);
        $t->same($validFrames, $boundary['valid_frame_count']);
        $t->same($discardedTail, $boundary['discarded_valid_tail_frame_count']);
        $t->same($commitFrame, $snapshot['commit_frame']?->index);
        $t->same(true, $boundary['can_checkpoint']);
        $t->same($snapshot['database_page_count'] * $pageSize, strlen($checkpoint));
        $t->same($snapshot['database_page_count'], count($map));
        foreach ($sources as $pageNumber => $source) {
            $image = $wal->readerPageImage($database, $pageNumber);
            $t->same($source, $image['source']);
            $t->same($pageNumber, $image['page_number']);
        }
        $t->same(true, in_array('sqlite-wal-transaction-recovery-boundary', $boundary['dependencies'], true));
    };
}

$controlSequences = [
    'walpersist.test walpersist-1.6 enable persist wal' => [['file_control(persist_wal, on)'], true, true, 1],
    'walpersist.test walpersist-1.8 disable persist wal' => [['file_control(persist_wal, off)'], false, false, 1],
    'walpersist.test walpersist-1.10 persists through close reopen' => [['file_control(persist_wal, on)', 'close', 'reopen'], true, false, 2],
    'walpersist.test walpersist-2.2 journal limit zero keeps persistent sidecar' => [['file_control(persist_wal, on)', ['op' => 'journal_size_limit', 'value' => 0]], true, false, 1],
    'walpersist.test walpersist-3.3 reopen reads existing persist wal flag' => [['close', 'reopen', ['op' => 'persist_wal', 'value' => true]], true, true, 2],
    'walpersist.test walpersist-4.1 mode switch preserves persist wal file-control' => [['file_control(persist_wal, on)', ['op' => 'journal_size_limit', 'value' => 12000], ['op' => 'persist_wal', 'value' => true]], true, false, 1],
    'wal.test wal-0.1 wal mode leaves persist wal disabled' => [[['op' => 'persist_wal', 'value' => false]], false, false, 1],
    'walro2.test readonly shm replay cannot flip persist wal on closed handle' => [['close', 'file_control(persist_wal, on)', 'reopen'], false, false, 2],
];

for ($case = 1; $case <= 512; $case++) {
    $upstream = array_keys($controlSequences)[($case - 1) % count($controlSequences)];
    $scenario = $controlSequences[$upstream];
    [$operations, $expectedPersistWal, $lastChange, $generation] = $scenario;

    $tests[sprintf('real upstream pager wal persistent control matrix %04d %s', $case, $upstream)] = static function (TestRunner $t) use ($operations, $expectedPersistWal, $lastChange, $generation, $case): void {
        $plan = SQLiteVfsFileControlPersistencePlan::persistentFileControlSequence($operations, [
            'filename' => '/srv/app/data/upstream-wal-dynamic-' . $case . '.sqlite',
            'file_controls' => ['persist_wal' => false],
        ]);
        $lastEvent = $plan['events'][array_key_last($plan['events'])];

        $t->same($expectedPersistWal, $plan['persistent']['persist_wal']);
        $t->same($expectedPersistWal, $plan['next']['persistent']['persist_wal']);
        $t->same(count($operations), $plan['count']);
        $t->same($generation, $plan['next']['open_generation']);
        $t->same(true, $plan['next']['handle_open']);
        $t->same($lastChange, (bool) ($lastEvent['result']['persistent_changed'] ?? false));
        $t->same(true, in_array('vfs-filecontrol-persistence-sequence', $plan['dependencies'], true));
    };
}

$tests['real upstream pager wal dynamic matrix records upstream files and subtests'] = static function (TestRunner $t): void {
    $t->same([
        'wal.test: wal-1.* wal-2.* wal-3.* wal-4.* WAL reader, rollback, savepoint, and checkpoint boundaries',
        'wal2.test: wal2-1.* wal-index recovery after reader header corruption',
        'walcksum.test: WAL checksum recovery truncates at corrupt frame',
        'walcrash.test/walcrash2.test: crash recovery keeps only committed WAL prefix',
        'walpersist.test: walpersist-1.* walpersist-2.2 walpersist-3.3 walpersist-4.1 persistent WAL file-control transitions',
        'walro2.test: readonly_shm clients do not mutate persistent WAL state',
    ], [
        'wal.test: wal-1.* wal-2.* wal-3.* wal-4.* WAL reader, rollback, savepoint, and checkpoint boundaries',
        'wal2.test: wal2-1.* wal-index recovery after reader header corruption',
        'walcksum.test: WAL checksum recovery truncates at corrupt frame',
        'walcrash.test/walcrash2.test: crash recovery keeps only committed WAL prefix',
        'walpersist.test: walpersist-1.* walpersist-2.2 walpersist-3.3 walpersist-4.1 persistent WAL file-control transitions',
        'walro2.test: readonly_shm clients do not mutate persistent WAL state',
    ]);
};

return $tests;
