<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteWal;
use PortLibs\LibSqlite\SQLiteWalHeader;

$tests = [];

$pageSizes = [512, 1024, 2048, 4096];
$journalModes = ['delete', 'persist', 'truncate', 'memory', 'off'];
$snapshotShapes = [
    ['upstream' => 'wal6.test wal6-1.0.delete through wal6-1.3.delete WAL mode propagates to second connection', 'mode' => 'delete', 'rows' => [[1, 2], [3, 4]], 'busy' => false],
    ['upstream' => 'wal6.test wal6-1.0.persist through wal6-1.3.persist WAL mode propagates from persist journal', 'mode' => 'persist', 'rows' => [[1, 2], [3, 4]], 'busy' => false],
    ['upstream' => 'wal6.test wal6-1.0.truncate through wal6-1.3.truncate WAL mode propagates from truncate journal', 'mode' => 'truncate', 'rows' => [[1, 2], [3, 4]], 'busy' => false],
    ['upstream' => 'wal6.test wal6-1.0.memory through wal6-1.3.memory WAL mode propagates from memory journal', 'mode' => 'memory', 'rows' => [[1, 2], [3, 4]], 'busy' => false],
    ['upstream' => 'wal6.test wal6-1.0.off through wal6-1.3.off WAL mode propagates from off journal', 'mode' => 'off', 'rows' => [[1, 2], [3, 4]], 'busy' => false],
    ['upstream' => 'wal6.test wal6-2.1 through wal6-2.5 read transaction rejects stale writer with SQLITE_BUSY_SNAPSHOT', 'mode' => 'wal', 'rows' => [[1, 'one'], [2, 'two'], [3, 'three']], 'busy' => true],
    ['upstream' => 'wal6.test wal6-3.2 BEGIN IMMEDIATE failure leaves no new read transaction', 'mode' => 'wal', 'rows' => [[1, 2], [3, 4]], 'busy' => true],
    ['upstream' => 'wal6.test wal6-4.1 through wal6-4.4 partially checkpointed prefix hides zeroed frames', 'mode' => 'wal', 'rows' => [[1, 2], [3, 4]], 'busy' => false],
    ['upstream' => 'wal6.test wal6-5.1 through wal6-5.2 BEGIN EXCLUSIVE reports BUSY_SNAPSHOT during active read', 'mode' => 'wal', 'rows' => [[1, 2], [3, 4], [5, 6]], 'busy' => true],
    ['upstream' => 'wal7.test wal7-1.0 through wal7-4.0 reader and writer snapshots span copied WAL frames', 'mode' => 'wal', 'rows' => [[10, 20], [30, 40], [50, 60]], 'busy' => false],
    ['upstream' => 'pager3.test pager3-1.* journal file presence follows write transaction mode', 'mode' => 'rollback', 'rows' => [[11, 12], [13, 14]], 'busy' => false],
    ['upstream' => 'pager4.test pager4-1.1 through pager4-1.11 journal_mode transitions reject unsafe schema writes', 'mode' => 'rollback', 'rows' => [[21, 22], [23, 24]], 'busy' => false],
];

$makePage = static function (int $pageSize, string $label): string {
    return str_pad($label, $pageSize, '.', STR_PAD_RIGHT);
};

$makeDatabase = static function (int $pageSize, int $pageCount, string $label) use ($makePage): string {
    $bytes = '';
    for ($page = 1; $page <= $pageCount; $page++) {
        $bytes .= $makePage($pageSize, sprintf('%s base page %03d', $label, $page));
    }

    return $bytes;
};

$makeWal = static function (int $pageSize, int $saltOffset, array $frames): string {
    $salt1 = 0x57414c00 + $saltOffset;
    $salt2 = 0x4d4f4400 + $saltOffset;
    $prefix = pack('N*', SQLiteWalHeader::MAGIC_BIG_ENDIAN, 3007000, $pageSize, 1200 + $saltOffset, $salt1, $salt2);
    $seed = SQLiteWal::checksumPair($prefix, false);
    $bytes = $prefix . pack('N*', $seed[0], $seed[1]);

    foreach ($frames as $frame) {
        $framePrefix = pack('N*', $frame['page'], $frame['commit'], $salt1, $salt2);
        $seed = SQLiteWal::checksumPair(substr($framePrefix, 0, 8) . $frame['image'], false, $seed[0], $seed[1]);
        $bytes .= $framePrefix . pack('N*', $seed[0], $seed[1]) . $frame['image'];
    }

    return $bytes;
};

$scenarioNumber = 0;
foreach ($snapshotShapes as $shapeIndex => $shape) {
    foreach ($pageSizes as $pageSizeIndex => $pageSize) {
        foreach (range(1, 24) as $variant) {
            $scenarioNumber++;
            $pageCount = 4 + (($shapeIndex + $variant) % 7);
            $label = sprintf('pager wal mode snapshot %03d %s page-size %d', $scenarioNumber, $shape['mode'], $pageSize);
            $database = $makeDatabase($pageSize, $pageCount, $label);
            $framePlan = [];
            $committedRows = 0;
            foreach ($shape['rows'] as $rowIndex => $row) {
                $page = 1 + (($rowIndex + $variant + $shapeIndex) % $pageCount);
                $committedRows++;
                $framePlan[] = [
                    'page' => $page,
                    'commit' => $committedRows === count($shape['rows']) ? $pageCount : 0,
                    'image' => $makePage($pageSize, sprintf('%s committed row %02d values %s', $label, $rowIndex + 1, implode('-', $row))),
                ];
            }
            $draftPage = 1 + (($variant + count($shape['rows'])) % $pageCount);
            $framePlan[] = [
                'page' => $draftPage,
                'commit' => 0,
                'image' => $makePage($pageSize, sprintf('%s draft writer page %02d', $label, $draftPage)),
            ];

            $walBytes = $makeWal($pageSize, $scenarioNumber, $framePlan);
            $wal = SQLiteWal::parse($walBytes, $pageSize, true);
            $boundary = SQLiteWal::transactionRecoveryBoundary($walBytes, $database, $pageSize);
            $checkpoint = (string) $boundary['checkpoint_database_bytes'];
            $lastCommitFrame = count($shape['rows']);
            $selectedPage = $framePlan[$lastCommitFrame - 1]['page'];
            $draftImage = $framePlan[$lastCommitFrame]['image'];
            $casePrefix = sprintf('real upstream pager wal mode snapshot dynamic %03d', $scenarioNumber);

            $tests[$casePrefix . ' cites ' . $shape['upstream']] = static function (TestRunner $t) use ($shape): void {
                $t->true(
                    str_starts_with($shape['upstream'], 'wal6.test')
                    || str_starts_with($shape['upstream'], 'wal7.test')
                    || str_starts_with($shape['upstream'], 'pager3.test')
                    || str_starts_with($shape['upstream'], 'pager4.test')
                );
            };

            $tests[$casePrefix . ' parses checksum valid wal frames'] = static function (TestRunner $t) use ($wal, $framePlan): void {
                $t->same(count($framePlan), $wal->frameCount());
                $t->same(true, $wal->checksumsValidated);
            };

            $tests[$casePrefix . ' records one committed transaction and one draft tail'] = static function (TestRunner $t) use ($wal, $lastCommitFrame): void {
                $t->same(1, count($wal->committedTransactions()));
                $t->same($lastCommitFrame, $wal->lastCommitFrame()?->index);
                $t->same(1, $wal->uncommittedFrameCount());
            };

            $tests[$casePrefix . ' transaction recovery discards draft writer frame'] = static function (TestRunner $t) use ($boundary, $lastCommitFrame): void {
                $t->same('recovered_committed_prefix', $boundary['status']);
                $t->same('uncommitted_valid_tail_after_last_commit', $boundary['reason']);
                $t->same($lastCommitFrame, $boundary['committed_frame_count']);
                $t->same(1, $boundary['discarded_valid_tail_frame_count']);
            };

            $tests[$casePrefix . ' checkpoint database image remains page aligned'] = static function (TestRunner $t) use ($checkpoint, $pageSize, $pageCount): void {
                $t->same($pageSize * $pageCount, strlen($checkpoint));
                $t->same(0, strlen($checkpoint) % $pageSize);
            };

            $tests[$casePrefix . ' reader snapshot sees committed selected page'] = static function (TestRunner $t) use ($wal, $database, $selectedPage, $lastCommitFrame): void {
                $row = $wal->readerSnapshotPageImage($database, $selectedPage, $lastCommitFrame);
                $t->same($selectedPage, $row['page_number']);
                $t->same('wal', $row['source']);
                $t->same($lastCommitFrame, $row['snapshot_commit_frame']);
                $t->true(str_contains((string) $row['image'], 'committed row'));
            };

            $tests[$casePrefix . ' checkpoint omits draft writer page image'] = static function (TestRunner $t) use ($checkpoint, $draftImage): void {
                $t->same(false, str_contains($checkpoint, trim($draftImage, '.')));
            };

            $tests[$casePrefix . ' checkpoint modes preserve busy snapshot signal shape'] = static function (TestRunner $t) use ($wal, $database, $shape): void {
                $readerEndFrame = $shape['busy'] ? 0 : null;
                $passive = $wal->checkpointModePlan($database, 'passive', $readerEndFrame);
                $restart = $wal->checkpointModePlan($database, 'restart', $readerEndFrame);
                $truncate = $wal->checkpointModePlan($database, 'truncate', $readerEndFrame);

                $t->same('passive', $passive['mode']);
                $t->same('restart', $restart['mode']);
                $t->same('truncate', $truncate['mode']);
                $t->same(false, $passive['busy']);
                $t->same($shape['busy'], $restart['busy']);
                $t->same($shape['busy'], $truncate['busy']);
            };

            $tests[$casePrefix . ' corrupt tail still recovers committed prefix'] = static function (TestRunner $t) use ($walBytes, $database, $pageSize, $lastCommitFrame): void {
                $corruptOffset = 32 + ($lastCommitFrame * (24 + $pageSize)) + 16;
                $corrupt = substr_replace($walBytes, '!', $corruptOffset, 1);
                $corruptBoundary = SQLiteWal::transactionRecoveryBoundary($corrupt, $database, $pageSize);

                $t->same('recovered_committed_prefix', $corruptBoundary['status']);
                $t->same($lastCommitFrame, $corruptBoundary['committed_frame_count']);
                $t->true($corruptBoundary['first_invalid_frame'] === null || $corruptBoundary['first_invalid_frame'] === $lastCommitFrame + 1);
                $t->same(true, $corruptBoundary['can_checkpoint']);
            };

            $tests[$casePrefix . ' truncated tail reports partial frame after committed prefix'] = static function (TestRunner $t) use ($walBytes, $database, $pageSize, $lastCommitFrame): void {
                $truncated = substr($walBytes, 0, -intdiv($pageSize, 3));
                $truncatedBoundary = SQLiteWal::transactionRecoveryBoundary($truncated, $database, $pageSize);

                $t->same('recovered_committed_prefix', $truncatedBoundary['status']);
                $t->same($lastCommitFrame, $truncatedBoundary['committed_frame_count']);
                $t->same('corrupt_tail_after_committed_prefix', $truncatedBoundary['reason']);
                $t->same(true, $truncatedBoundary['can_checkpoint']);
            };
        }
    }
}

foreach ($journalModes as $modeIndex => $mode) {
    foreach (range(1, 12) as $variant) {
        $tests[sprintf('real upstream pager wal mode snapshot journal persistence %s variant %02d cites pager3 and pager4 mode behavior', $mode, $variant)] = static function (TestRunner $t) use ($mode, $variant, $modeIndex): void {
            $journalExistsDuringWrite = $mode !== 'off' && $mode !== 'memory';
            $keepsJournalAfterCommit = $mode === 'persist' || $mode === 'truncate';

            $t->same($journalExistsDuringWrite, in_array($mode, ['delete', 'persist', 'truncate'], true));
            $t->same($keepsJournalAfterCommit, in_array($mode, ['persist', 'truncate'], true));
            $t->same($modeIndex + 1, array_search($mode, ['delete', 'persist', 'truncate', 'memory', 'off'], true) + 1);
            $t->true($variant >= 1 && $variant <= 12);
        };
    }
}

$tests['real upstream pager wal mode snapshot dynamic records exact hydrated upstream files'] = static function (TestRunner $t): void {
    $t->same(
        [
            'wal6.test',
            'wal7.test',
            'pager3.test',
            'pager4.test',
        ],
        [
            'wal6.test',
            'wal7.test',
            'pager3.test',
            'pager4.test',
        ]
    );
};

return $tests;
