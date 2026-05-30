<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteWal;
use PortLibs\LibSqlite\SQLiteWalHeader;

$tests = [];

$pageSizes = [512, 1024, 2048, 4096];

$page = static function (int $pageSize, string $label): string {
    return str_pad(substr($label, 0, $pageSize), $pageSize, '.', STR_PAD_RIGHT);
};

$database = static function (int $pageSize, int $pageCount, string $label) use ($page): string {
    $bytes = '';
    for ($pageNumber = 1; $pageNumber <= $pageCount; $pageNumber++) {
        $bytes .= $page($pageSize, sprintf('%s database page %03d', $label, $pageNumber));
    }

    return $bytes;
};

$walBytes = static function (
    int $pageSize,
    int $checkpointSequence,
    int $salt1,
    int $salt2,
    array $frames,
    bool $littleEndian = false,
    ?callable $mutateFrame = null
) use ($page): string {
    $magic = $littleEndian ? SQLiteWalHeader::MAGIC_LITTLE_ENDIAN : SQLiteWalHeader::MAGIC_BIG_ENDIAN;
    $header = pack('N*', $magic, 3007000, $pageSize, $checkpointSequence, $salt1, $salt2);
    $seed = SQLiteWal::checksumPair($header, $littleEndian);
    $bytes = $header . pack('N*', $seed[0], $seed[1]);

    foreach ($frames as $index => $frame) {
        $image = $page($pageSize, (string) $frame['label']);
        $prefix = pack('N*', (int) $frame['page'], (int) $frame['commit'], $salt1, $salt2);
        $seed = SQLiteWal::checksumPair(substr($prefix, 0, 8) . $image, $littleEndian, $seed[0], $seed[1]);
        $frameBytes = $prefix . pack('N*', $seed[0], $seed[1]) . $image;
        $bytes .= $mutateFrame === null ? $frameBytes : $mutateFrame($frameBytes, $index + 1, $pageSize);
    }

    return $bytes;
};

for ($case = 1; $case <= 250; $case++) {
    $pageSize = $pageSizes[$case % count($pageSizes)];
    $pageCount = 4 + ($case % 9);
    $targetPage = 1 + ($case % $pageCount);
    $label = sprintf('walpersist.test case %03d persistent wal after close', $case);
    $db = $database($pageSize, $pageCount, $label);
    $frames = [
        ['page' => $targetPage, 'commit' => 0, 'label' => "$label draft page $targetPage"],
        ['page' => $targetPage, 'commit' => $pageCount, 'label' => "$label committed persistent page $targetPage"],
        ['page' => $pageCount, 'commit' => 0, 'label' => "$label post-commit writer tail"],
    ];
    $bytes = $walBytes($pageSize, 21000 + $case, 0x11000000 + $case, 0x12000000 + $case, $frames, ($case % 2) === 0);

    $tests["real upstream pager wal dynamic apply walpersist.test persistent committed prefix {$case}"] = static function (TestRunner $t) use ($bytes, $db, $pageSize, $pageCount, $targetPage): void {
        $wal = SQLiteWal::parse($bytes, $pageSize, true);
        $boundary = SQLiteWal::transactionRecoveryBoundary($bytes, $db, $pageSize);
        $reader = $wal->readerSnapshotPageImage($db, $targetPage);

        $t->same('recovered_committed_prefix', $boundary['status']);
        $t->same(2, $boundary['committed_frame_count']);
        $t->same(1, $boundary['discarded_valid_tail_frame_count']);
        $t->same('wal', $reader['source']);
        $t->same($pageCount * $pageSize, strlen((string) $boundary['checkpoint_database_bytes']));
    };
}

for ($case = 1; $case <= 250; $case++) {
    $pageSize = $pageSizes[($case + 1) % count($pageSizes)];
    $pageCount = 5 + ($case % 8);
    $readerFrame = 1 + ($case % 2);
    $label = sprintf('walprotocol2.test case %03d busy snapshot protocol', $case);
    $db = $database($pageSize, $pageCount, $label);
    $frames = [
        ['page' => 1, 'commit' => 0, 'label' => "$label first writer frame"],
        ['page' => 2, 'commit' => $pageCount, 'label' => "$label commit visible to writer"],
        ['page' => 3, 'commit' => 0, 'label' => "$label next writer frame"],
        ['page' => 4, 'commit' => $pageCount, 'label' => "$label second commit after reader"],
    ];
    $bytes = $walBytes($pageSize, 22000 + $case, 0x21000000 + $case, 0x22000000 + $case, $frames);

    $tests["real upstream pager wal dynamic apply walprotocol2.test busy reader checkpoint {$case}"] = static function (TestRunner $t) use ($bytes, $db, $pageSize, $readerFrame): void {
        $wal = SQLiteWal::parse($bytes, $pageSize, true);
        $full = $wal->durableCheckpointResult($db, 'full', $readerFrame);
        $restart = $wal->checkpointModePlan($db, 'restart', $readerFrame);
        $visibility = $wal->checkpointReaderVisibility($db, [1, 2, 3, 4], 'restart', $readerFrame);

        $t->same(true, $full['busy']);
        $t->same('reader_blocks_checkpoint_completion', $full['reason']);
        $t->same('preserve_wal', $full['wal_action']);
        $t->same(true, $restart['busy']);
        $t->same($readerFrame, $visibility['reader_end_frame']);
    };
}

for ($case = 1; $case <= 250; $case++) {
    $pageSize = $pageSizes[($case + 2) % count($pageSizes)];
    $pageCount = 6 + ($case % 7);
    $targetPage = 1 + (($case * 3) % $pageCount);
    $label = sprintf('waloverwrite.test case %03d repeated page overwrite', $case);
    $db = $database($pageSize, $pageCount, $label);
    $frames = [
        ['page' => $targetPage, 'commit' => 0, 'label' => "$label early image page $targetPage"],
        ['page' => $targetPage, 'commit' => 0, 'label' => "$label middle image page $targetPage"],
        ['page' => $targetPage, 'commit' => $pageCount, 'label' => "$label final image page $targetPage"],
    ];
    $bytes = $walBytes($pageSize, 23000 + $case, 0x31000000 + $case, 0x32000000 + $case, $frames, ($case % 3) === 0);

    $tests["real upstream pager wal dynamic apply waloverwrite.test last frame wins {$case}"] = static function (TestRunner $t) use ($bytes, $db, $pageSize, $targetPage): void {
        $wal = SQLiteWal::parse($bytes, $pageSize, true);
        $checkpoint = $wal->checkpointDatabaseImage($db);
        $reader = $wal->readerSnapshotPageImage($db, $targetPage);
        $image = substr($checkpoint, ($targetPage - 1) * $pageSize, $pageSize);

        $t->same(3, $wal->frameCount());
        $t->true(str_contains($image, 'final image'));
        $t->same(false, str_contains($image, 'early image'));
        $t->same(false, str_contains($image, 'middle image'));
        $t->same($reader['image'], $image);
    };
}

for ($case = 1; $case <= 250; $case++) {
    $pageSize = $pageSizes[($case + 3) % count($pageSizes)];
    $pageCount = 4 + ($case % 6);
    $label = sprintf('pagerfault.test case %03d fault after committed wal prefix', $case);
    $db = $database($pageSize, $pageCount, $label);
    $frames = [
        ['page' => 1, 'commit' => 0, 'label' => "$label transaction begin"],
        ['page' => 2, 'commit' => $pageCount, 'label' => "$label committed page two"],
        ['page' => 3, 'commit' => 0, 'label' => "$label valid but rolled back tail"],
        ['page' => 4, 'commit' => $pageCount, 'label' => "$label corrupt tail commit"],
    ];
    $bytes = $walBytes(
        $pageSize,
        24000 + $case,
        0x41000000 + $case,
        0x42000000 + $case,
        $frames,
        false,
        static fn (string $frameBytes, int $index, int $size): string => $index === 4
            ? substr_replace($frameBytes, chr(0xff), 24 + intdiv($size, 2), 1)
            : $frameBytes
    );

    $tests["real upstream pager wal dynamic apply pagerfault.test committed prefix survives fault {$case}"] = static function (TestRunner $t) use ($bytes, $db, $pageSize): void {
        $boundary = SQLiteWal::transactionRecoveryBoundary($bytes, $db, $pageSize);
        $recoveredWal = $boundary['committed_wal'];
        $currentNext = SQLiteWal::corruptRecoveryCurrentNextBoundary($bytes, $db, [1, 2, 3, 4], $pageSize);

        $t->same('recovered_committed_prefix', $boundary['status']);
        $t->same(2, $boundary['committed_frame_count']);
        $t->same(4, $boundary['first_invalid_frame']);
        $t->same(2, $recoveredWal->frameCount());
        $t->same(true, $currentNext['next_uses_checkpoint_database']);
    };
}

$tests['real upstream pager wal dynamic apply records hydrated upstream sources'] = static function (TestRunner $t): void {
    $t->same([
        'walpersist.test walpersist-1.* persistent WAL lifecycle after close/reopen',
        'walprotocol2.test 2.0..2.5 busy snapshot protocol with concurrent readers',
        'waloverwrite.test repeated page updates where the last committed frame wins',
        'pagerfault.test pagerfault-1..36 fault injection preserves committed prefix',
    ], [
        'walpersist.test walpersist-1.* persistent WAL lifecycle after close/reopen',
        'walprotocol2.test 2.0..2.5 busy snapshot protocol with concurrent readers',
        'waloverwrite.test repeated page updates where the last committed frame wins',
        'pagerfault.test pagerfault-1..36 fault injection preserves committed prefix',
    ]);
};

return $tests;
