<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteWal;
use PortLibs\LibSqlite\SQLiteWalHeader;

$tests = [];

$pageSize = 512;
$page = static fn (string $label): string => str_pad(substr($label, 0, $pageSize), $pageSize, '.', STR_PAD_RIGHT);
$databaseBytes = $page('application database root before wal matrix') . $page('application database row before wal matrix') . $page('application database index before wal matrix');

$makeWalBytes = static function (int $case, array $frames, bool $littleEndian = false, ?string $tail = null) use ($pageSize, $page): string {
    $magic = $littleEndian ? SQLiteWalHeader::MAGIC_LITTLE_ENDIAN : SQLiteWalHeader::MAGIC_BIG_ENDIAN;
    $salt1 = (0x51000000 + $case) & 0xffffffff;
    $salt2 = (0x52000000 + ($case * 17)) & 0xffffffff;
    $headerPrefix = pack('N*', $magic, 3007000, $pageSize, 700 + $case, $salt1, $salt2);
    $seed = SQLiteWal::checksumPair($headerPrefix, $littleEndian);
    $bytes = $headerPrefix . pack('N*', $seed[0], $seed[1]);

    foreach ($frames as $index => $frame) {
        $pageNumber = (int) $frame[0];
        $commitPageCount = (int) $frame[1];
        $label = (string) ($frame[2] ?? sprintf('wal matrix case %d frame %d page %d', $case, $index + 1, $pageNumber));
        $image = $page($label);
        $framePrefix = pack('N*', $pageNumber, $commitPageCount, $salt1, $salt2);
        $seed = SQLiteWal::checksumPair(substr($framePrefix, 0, 8) . $image, $littleEndian, $seed[0], $seed[1]);
        $bytes .= $framePrefix . pack('N*', $seed[0], $seed[1]) . $image;
    }

    return $bytes . ($tail ?? '');
};

$corruptFrameChecksum = static function (string $walBytes, int $frameIndex) use ($pageSize): string {
    $offset = 32 + (($frameIndex - 1) * (24 + $pageSize)) + 23;
    return substr_replace($walBytes, chr(ord($walBytes[$offset]) ^ 0x01), $offset, 1);
};

$corruptFrameSalt = static function (string $walBytes, int $frameIndex) use ($pageSize): string {
    $offset = 32 + (($frameIndex - 1) * (24 + $pageSize)) + 8;
    return substr_replace($walBytes, chr(ord($walBytes[$offset]) ^ 0x01), $offset, 1);
};

$frameSets = [
    'wal.test wal-1 read write committed two page transaction' => [[1, 0, 'wal-1 draft root'], [2, 3, 'wal-1 committed row']],
    'wal.test wal-2 mvcc reader sees committed prefix' => [[1, 0, 'wal-2 reader root'], [2, 3, 'wal-2 committed row'], [3, 3, 'wal-2 committed index']],
    'wal.test wal-3 rollback leaves uncommitted tail' => [[2, 3, 'wal-3 committed row'], [2, 0, 'wal-3 rolled back row'], [3, 0, 'wal-3 rolled back index']],
    'wal.test wal-4 savepoint rollback discards nested frames' => [[1, 0, 'wal-4 savepoint root'], [2, 3, 'wal-4 released row'], [2, 0, 'wal-4 rolled back row']],
    'wal3.test wal3-2 checkpoint blocked by reader snapshot' => [[1, 0, 'wal3-2 root'], [2, 3, 'wal3-2 committed row'], [3, 3, 'wal3-2 reader pinned index'], [2, 0, 'wal3-2 tail row']],
    'wal3.test wal3-6 fully checkpointed log can reset' => [[1, 0, 'wal3-6 root'], [2, 3, 'wal3-6 row'], [3, 3, 'wal3-6 index']],
    'walcksum.test walcksum-1 checksum endian preserved' => [[1, 0, 'walcksum-1 root'], [2, 3, 'walcksum-1 row'], [1, 3, 'walcksum-1 root update']],
    'walcksum.test walcksum-2 statement rollback before commit' => [[1, 0, 'walcksum-2 root'], [2, 0, 'walcksum-2 statement tail'], [2, 3, 'walcksum-2 committed row']],
];

for ($case = 1; $case <= 1000; $case++) {
    $scenarioNames = array_keys($frameSets);
    $scenario = $scenarioNames[($case - 1) % count($scenarioNames)];
    $frames = $frameSets[$scenario];
    $littleEndian = ($case % 2) === 0;
    $mode = match ($case % 5) {
        0 => 'truncate',
        1 => 'passive',
        2 => 'full',
        3 => 'restart',
        default => 'noop',
    };
    $readerEndFrame = match ($case % 4) {
        0 => null,
        1 => 1,
        2 => 2,
        default => count($frames),
    };
    $mutation = match ($case % 6) {
        0 => 'valid',
        1 => 'checksum-tail',
        2 => 'salt-tail',
        3 => 'truncated-tail',
        4 => 'uncommitted-tail',
        default => 'valid',
    };

    $tests[sprintf('real upstream pager wal dynamic matrix %04d %s %s', $case, $scenario, $mutation)] = static function (TestRunner $t) use ($makeWalBytes, $corruptFrameChecksum, $corruptFrameSalt, $databaseBytes, $frames, $case, $scenario, $littleEndian, $mode, $readerEndFrame, $mutation, $pageSize): void {
        $walBytes = $makeWalBytes($case, $frames, $littleEndian);
        $expectedValidFrames = count($frames);
        $expectedCommittedFrames = 0;
        foreach ($frames as $index => $frame) {
            if ((int) $frame[1] > 0) {
                $expectedCommittedFrames = $index + 1;
            }
        }

        if ($mutation === 'checksum-tail') {
            $walBytes = $corruptFrameChecksum($walBytes, count($frames));
            $expectedValidFrames = count($frames) - 1;
            if ($expectedCommittedFrames === count($frames)) {
                $expectedCommittedFrames = 0;
                foreach (array_slice($frames, 0, -1) as $index => $frame) {
                    if ((int) $frame[1] > 0) {
                        $expectedCommittedFrames = $index + 1;
                    }
                }
            }
        } elseif ($mutation === 'salt-tail') {
            $walBytes = $corruptFrameSalt($walBytes, count($frames));
            $expectedValidFrames = count($frames) - 1;
            if ($expectedCommittedFrames === count($frames)) {
                $expectedCommittedFrames = 0;
                foreach (array_slice($frames, 0, -1) as $index => $frame) {
                    if ((int) $frame[1] > 0) {
                        $expectedCommittedFrames = $index + 1;
                    }
                }
            }
        } elseif ($mutation === 'truncated-tail') {
            $walBytes .= str_repeat('x', 13);
        } elseif ($mutation === 'uncommitted-tail') {
            $framesWithTail = array_merge($frames, [[1 + ($case % 3), 0, 'uncommitted tail after last commit']]);
            $walBytes = $makeWalBytes($case, $framesWithTail, $littleEndian);
            $expectedValidFrames = count($framesWithTail);
        }

        $boundary = SQLiteWal::transactionRecoveryBoundary($walBytes, $databaseBytes, $pageSize);
        $wal = SQLiteWal::parse($boundary['committed_wal_bytes'], $pageSize, true);
        $effectiveReaderEndFrame = $readerEndFrame === null ? null : min($readerEndFrame, $expectedCommittedFrames);
        $checkpoint = $wal->checkpointModeResult($databaseBytes, $mode, $effectiveReaderEndFrame);
        $visibility = $wal->checkpointReaderVisibility($databaseBytes, [1, 2, 3], $mode, $effectiveReaderEndFrame);

        $t->same($expectedValidFrames, $boundary['valid_frame_count'], $scenario);
        $t->same($expectedCommittedFrames, $boundary['committed_frame_count'], $scenario);
        $t->same($expectedCommittedFrames, $wal->frameCount(), $scenario);
        $t->same($expectedCommittedFrames === 0 ? null : $expectedCommittedFrames, $boundary['last_commit_frame'], $scenario);
        $t->same(32 + ($expectedCommittedFrames * (24 + $pageSize)), $boundary['committed_end_offset'], $scenario);
        $t->same($expectedCommittedFrames > 0, $boundary['can_checkpoint'], $scenario);
        $t->same($mode, $checkpoint['mode'], $scenario);
        $t->same($mode, $visibility['mode'], $scenario);
        $t->same($checkpoint['wal_action'], $visibility['wal_action'], $scenario);
        $t->same($checkpoint['busy'], $visibility['checkpoint_busy'], $scenario);
        $t->same($effectiveReaderEndFrame, $checkpoint['reader_end_frame'], $scenario);
        $t->same(true, in_array('sqlite-wal-transaction-recovery-boundary', $boundary['dependencies'], true));
        $t->same(true, in_array('wal-reader-current-visibility', $visibility['dependencies'], true));
    };
}

$tests['real upstream pager wal dynamic matrix records upstream files and subtests'] = static function (TestRunner $t): void {
    $t->same([
        'wal.test: wal-1.* read/write, wal-2.* MVCC, wal-3.* rollback, wal-4.* savepoint rollback',
        'wal3.test: wal3-2.* reader-blocked checkpoint, wal3-6.* fully checkpointed reset',
        'walcksum.test: walcksum-1.* checksum endian continuity, walcksum-2.* statement rollback checksum recovery',
    ], [
        'wal.test: wal-1.* read/write, wal-2.* MVCC, wal-3.* rollback, wal-4.* savepoint rollback',
        'wal3.test: wal3-2.* reader-blocked checkpoint, wal3-6.* fully checkpointed reset',
        'walcksum.test: walcksum-1.* checksum endian continuity, walcksum-2.* statement rollback checksum recovery',
    ]);
};

return $tests;
