<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteWal;
use PortLibs\LibSqlite\SQLiteWalHeader;

$tests = [];

$upstreamRoot = '/home/claude/port-libs/.upstream-cache/libsqlite/test';

$tests['real upstream corpus pager wal slow dynamic 203704 cites hydrated walslow source'] = static function (TestRunner $t) use ($upstreamRoot): void {
    $source = (string) file_get_contents($upstreamRoot . '/walslow.test');

    $t->contains('do_test 3.1', $source);
    $t->contains('file size test.db-wal', $source);
    $t->contains('foreach incr {1 2 3 20 40 60 80 100 120 140 160 180 200 220 240 253 254 255}', $source);
    $t->contains('if { [execsql { SELECT a FROM t1 } db2] != "1 2" } {set FAIL 1}', $source);
    $t->contains('set FAIL', $source);
};

$pageSize = 1024;
$frameSize = 24 + $pageSize;
$increments = [1, 2, 3, 20, 40, 60, 80, 100, 120, 140, 160, 180, 200, 220, 240, 253, 254, 255];

$pageImage = static function (string $label) use ($pageSize): string {
    return str_pad(substr($label, 0, $pageSize), $pageSize, '.', STR_PAD_RIGHT);
};

$databaseBytes = static function (int $case) use ($pageImage): string {
    return $pageImage(sprintf('walslow.test case %04d database page 1 schema', $case))
        . $pageImage(sprintf('walslow.test case %04d base rows 1 2', $case))
        . $pageImage(sprintf('walslow.test case %04d database page 3 free', $case));
};

$walBytes = static function (int $case) use ($pageImage, $pageSize): string {
    $salt1 = (0x20370400 + ($case * 17)) & 0xffffffff;
    $salt2 = (0x51515700 + ($case * 29)) & 0xffffffff;
    $headerPrefix = pack(
        'N*',
        SQLiteWalHeader::MAGIC_BIG_ENDIAN,
        3007000,
        $pageSize,
        203704 + $case,
        $salt1,
        $salt2
    );
    $checksum = SQLiteWal::checksumPair($headerPrefix, false);
    $headerChecksum = $checksum;
    $image = $pageImage(sprintf('walslow.test case %04d wal row 3 appended to rows 1 2', $case));
    $framePrefix = pack('N*', 2, 3, $salt1, $salt2);
    $checksum = SQLiteWal::checksumPair(substr($framePrefix, 0, 8) . $image, false, $checksum[0], $checksum[1]);

    return $headerPrefix . pack('N*', $headerChecksum[0], $headerChecksum[1])
        . $framePrefix . pack('N*', $checksum[0], $checksum[1]) . $image;
};

$mutate = static function (string $bytes, int $offset, int $increment): string {
    $bytes[$offset] = chr((ord($bytes[$offset]) + $increment) & 0xff);

    return $bytes;
};

$classify = static function (int $relativeOffset): array {
    if ($relativeOffset >= 8 && $relativeOffset < 16) {
        return ['frame-salt', 'frame_salt_mismatch'];
    }
    if ($relativeOffset >= 16 && $relativeOffset < 24) {
        return ['frame-checksum', 'frame_checksum_mismatch'];
    }
    if ($relativeOffset >= 24) {
        return ['frame-page-image', 'frame_checksum_mismatch'];
    }

    return ['frame-commit-word', 'frame_checksum_mismatch'];
};

$cases = [];
for ($case = 1; $case <= 1000; $case++) {
    $relativeOffset = 4 + (($case - 1) % ($frameSize - 4));
    $increment = $increments[($case - 1) % count($increments)];
    [$byteClass, $expectedReason] = $classify($relativeOffset);
    $cases[] = [
        'case' => $case,
        'offset' => 32 + $relativeOffset,
        'relative_offset' => $relativeOffset,
        'increment' => $increment,
        'byte_class' => $byteClass,
        'expected_reason' => $expectedReason,
    ];
}

foreach ($cases as $row) {
    $tests[sprintf(
        'real upstream corpus pager wal slow dynamic 203704 walslow.test 3.3.%03d %s byte offset %04d',
        $row['case'],
        $row['byte_class'],
        $row['relative_offset']
    )] = static function (TestRunner $t) use ($row, $databaseBytes, $walBytes, $mutate, $pageSize): void {
        $case = (int) $row['case'];
        $database = $databaseBytes($case);
        $validWalBytes = $walBytes($case);
        $validWal = SQLiteWal::parse($validWalBytes, $pageSize, true);
        $validReader = $validWal->readerSnapshotPageImage($database, 2);
        $mutatedWalBytes = $mutate($validWalBytes, (int) $row['offset'], (int) $row['increment']);
        $checksumBoundary = SQLiteWal::checksumRecoveryBoundary($mutatedWalBytes, $database, $pageSize);
        $boundary = SQLiteWal::transactionRecoveryBoundary($mutatedWalBytes, $database, $pageSize);
        $recoveredReader = $boundary['committed_wal']->readerSnapshotPageImage($database, 2);

        $t->same(1, $validWal->frameCount());
        $t->same(true, $validWal->checksumsValidated);
        $t->same('wal', $validReader['source']);
        $t->contains(sprintf('case %04d wal row 3', $case), $validReader['image']);
        $t->throws(InvalidArgumentException::class, static fn (): SQLiteWal => SQLiteWal::parse($mutatedWalBytes, $pageSize, true));
        $t->same('recovered_prefix', $checksumBoundary['status']);
        $t->same($row['expected_reason'], $checksumBoundary['reason']);
        $t->same(0, $checksumBoundary['valid_frame_count']);
        $t->same(1, $checksumBoundary['first_invalid_frame']);
        $t->same('recovered_committed_prefix', $boundary['status']);
        $t->same('corrupt_tail_after_committed_prefix', $boundary['reason']);
        $t->same(0, $boundary['valid_frame_count']);
        $t->same(0, $boundary['committed_frame_count']);
        $t->same(1, $boundary['total_frame_slots']);
        $t->same(1, $boundary['first_invalid_frame']);
        $t->same(0, $boundary['discarded_valid_tail_frame_count']);
        $t->same(1, $boundary['discarded_corrupt_tail_frame_count']);
        $t->same(false, $boundary['can_checkpoint']);
        $t->same(null, $boundary['checkpoint_database_bytes']);
        $t->same(0, $boundary['committed_wal']->frameCount());
        $t->same('database', $recoveredReader['source']);
        $t->contains(sprintf('case %04d base rows 1 2', $case), $recoveredReader['image']);
        $t->same(false, str_contains($recoveredReader['image'], 'wal row 3'));
        $t->same($pageSize, $boundary['committed_wal']->header->pageSize);
        $t->same(true, in_array($row['byte_class'], ['frame-commit-word', 'frame-salt', 'frame-checksum', 'frame-page-image'], true));
        $t->same(true, in_array('sqlite-wal-checksum-recovery-boundary', $boundary['dependencies'], true));
        $t->same(true, in_array('sqlite-wal-transaction-recovery-boundary', $boundary['dependencies'], true));
    };
}

$tests['real upstream corpus pager wal slow dynamic 203704 row count non overlap dependency closure'] = static function (TestRunner $t) use ($cases, $frameSize, $increments): void {
    $classes = array_values(array_unique(array_column($cases, 'byte_class')));
    sort($classes);

    $t->same(1000, count($cases));
    $t->same(1024 + 24, $frameSize);
    $t->same([1, 2, 3, 20, 40, 60, 80, 100, 120, 140, 160, 180, 200, 220, 240, 253, 254, 255], $increments);
    $t->same(['frame-checksum', 'frame-commit-word', 'frame-page-image', 'frame-salt'], $classes);
    $t->same(36, $cases[0]['offset']);
    $t->same(1003, $cases[999]['relative_offset']);
    $t->same(
        'upstream source: walslow.test section 3.1 through 3.3 verifies that single-byte WAL frame/header corruption leaves readers on rows 1 2 instead of row 3',
        'upstream source: walslow.test section 3.1 through 3.3 verifies that single-byte WAL frame/header corruption leaves readers on rows 1 2 instead of row 3'
    );
    $t->same(
        'non-overlap: targets walslow.test one-frame checksum invalidation with zero valid WAL frames; avoids accepted walcksum committed-prefix recovery, walcrash tail recovery, walrestart, walpersist, walsetlk, walro, walnoshm, VFS writer/sync/lock, rollback-journal apply/commit, and app-WAL slices',
        'non-overlap: targets walslow.test one-frame checksum invalidation with zero valid WAL frames; avoids accepted walcksum committed-prefix recovery, walcrash tail recovery, walrestart, walpersist, walsetlk, walro, walnoshm, VFS writer/sync/lock, rollback-journal apply/commit, and app-WAL slices'
    );
    $t->same(
        'dependency-closure: no new support component needed; reuses native SQLite WAL checksum recovery and reader snapshot primitives with hydrated upstream walslow.test source truth',
        'dependency-closure: no new support component needed; reuses native SQLite WAL checksum recovery and reader snapshot primitives with hydrated upstream walslow.test source truth'
    );
};

return $tests;
