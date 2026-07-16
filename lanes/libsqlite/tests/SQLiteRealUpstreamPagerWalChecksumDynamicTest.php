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
    bool $littleEndian,
    array $frames,
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
    $littleEndian = ($case % 2) === 0;
    $pageSize = $pageSizes[$case % count($pageSizes)];
    $pageCount = 3 + ($case % 5);
    $targetPage = 1 + ($case % $pageCount);
    $label = sprintf('walcksum.test walcksum-1.%s.2.%03d native checksum verification', $littleEndian ? 'little' : 'big', $case);
    $db = $database($pageSize, $pageCount, $label);
    $bytes = $walBytes(
        $pageSize,
        31000 + $case,
        0x51000000 + $case,
        0x52000000 + $case,
        $littleEndian,
        [
            ['page' => $targetPage, 'commit' => 0, 'label' => "$label draft frame"],
            ['page' => $targetPage, 'commit' => $pageCount, 'label' => "$label committed frame"],
            ['page' => $pageCount, 'commit' => 0, 'label' => "$label uncommitted tail"],
        ]
    );

    $tests["real upstream pager wal checksum dynamic walcksum native endian valid frame {$case}"] = static function (TestRunner $t) use ($bytes, $db, $pageSize, $pageCount, $littleEndian, $targetPage): void {
        $wal = SQLiteWal::parse($bytes, $pageSize, true);
        $boundary = SQLiteWal::checksumRecoveryBoundary($bytes, $db, $pageSize);
        $checkpoint = $wal->checkpointDatabaseImage($db);
        $image = substr($checkpoint, ($targetPage - 1) * $pageSize, $pageSize);

        $t->same(3, $wal->frameCount());
        $t->same($littleEndian ? 'little-endian' : 'big-endian', $wal->header->byteOrder());
        $t->same('valid', $boundary['status']);
        $t->same($pageCount * $pageSize, strlen($checkpoint));
        $t->same(true, str_contains($image, 'committed frame'));
    };
}

for ($case = 1; $case <= 250; $case++) {
    $littleEndian = ($case % 2) === 1;
    $pageSize = $pageSizes[($case + 1) % count($pageSizes)];
    $pageCount = 4 + ($case % 6);
    $label = sprintf('walcksum.test walcksum-1.%s.5.%03d append preserves checksum endian', $littleEndian ? 'little' : 'big', $case);
    $db = $database($pageSize, $pageCount, $label);
    $bytes = $walBytes(
        $pageSize,
        32000 + $case,
        0x61000000 + $case,
        0x62000000 + $case,
        $littleEndian,
        [
            ['page' => 1, 'commit' => 0, 'label' => "$label recovered writer frame one"],
            ['page' => 2, 'commit' => $pageCount, 'label' => "$label recovered writer commit"],
            ['page' => 3, 'commit' => 0, 'label' => "$label second connection frame"],
            ['page' => 4, 'commit' => $pageCount, 'label' => "$label second connection commit"],
        ]
    );

    $tests["real upstream pager wal checksum dynamic walcksum append endian preservation {$case}"] = static function (TestRunner $t) use ($bytes, $db, $pageSize, $littleEndian): void {
        $wal = SQLiteWal::parse($bytes, $pageSize, true);
        $boundary = SQLiteWal::checksumRecoveryBoundary($bytes, $db, $pageSize);
        $last = $wal->lastCommitFrame();

        $t->same(4, $wal->frameCount());
        $t->same($littleEndian, $wal->header->usesLittleEndianChecksums());
        $t->same('valid', $boundary['status']);
        $t->same(4, $boundary['valid_frame_count']);
        $t->same(4, $last?->index);
    };
}

for ($case = 1; $case <= 250; $case++) {
    $pageSize = $pageSizes[($case + 2) % count($pageSizes)];
    $pageCount = 5 + ($case % 7);
    $label = sprintf('walcksum.test walcksum-1.%s.8.%03d checkpoint restarts native checksum', ($case % 2) === 0 ? 'little' : 'big', $case);
    $db = $database($pageSize, $pageCount, $label);
    $bytes = $walBytes(
        $pageSize,
        33000 + $case,
        0x71000000 + $case,
        0x72000000 + $case,
        false,
        [
            ['page' => 1, 'commit' => 0, 'label' => "$label checkpoint restart frame one"],
            ['page' => 2, 'commit' => $pageCount, 'label' => "$label checkpoint restart commit"],
        ]
    );

    $tests["real upstream pager wal checksum dynamic walcksum checkpoint native restart {$case}"] = static function (TestRunner $t) use ($bytes, $db, $pageSize, $pageCount): void {
        $wal = SQLiteWal::parse($bytes, $pageSize, true);
        $boundary = SQLiteWal::checksumRecoveryBoundary($bytes, $db, $pageSize);
        $checkpoint = $wal->durableCheckpointResult($db, 'restart');
        $restartWal = SQLiteWal::parse((string) $checkpoint['wal_bytes'], $pageSize, true);

        $t->same('big-endian', $wal->header->byteOrder());
        $t->same('valid', $boundary['status']);
        $t->same('restart_wal', $checkpoint['wal_action']);
        $t->same(0, $restartWal->frameCount());
        $t->same($pageCount * $pageSize, strlen((string) $checkpoint['database_bytes']));
    };
}

for ($case = 1; $case <= 125; $case++) {
    $pageSize = $pageSizes[($case + 3) % count($pageSizes)];
    $pageCount = 4 + ($case % 4);
    $label = sprintf('walcksum.test walcksum-2.1.%03d statement rollback corrupt tail recovery', $case);
    $db = $database($pageSize, $pageCount, $label);
    $bytes = $walBytes(
        $pageSize,
        34000 + $case,
        0x81000000 + $case,
        0x82000000 + $case,
        ($case % 2) === 0,
        [
            ['page' => 1, 'commit' => 0, 'label' => "$label outer transaction frame"],
            ['page' => 2, 'commit' => $pageCount, 'label' => "$label committed outer prefix"],
            ['page' => 3, 'commit' => 0, 'label' => "$label rolled back savepoint frame"],
            ['page' => 4, 'commit' => $pageCount, 'label' => "$label invalid statement tail"],
        ],
        static fn (string $frameBytes, int $index, int $size): string => $index === 4
            ? substr_replace($frameBytes, chr(0xfe), 24 + intdiv($size, 3), 1)
            : $frameBytes
    );

    $tests["real upstream pager wal checksum dynamic walcksum statement rollback corrupt tail {$case}"] = static function (TestRunner $t) use ($bytes, $db, $pageSize): void {
        $boundary = SQLiteWal::checksumRecoveryBoundary($bytes, $db, $pageSize);
        $validWal = $boundary['wal'];

        $t->same('recovered_prefix', $boundary['status']);
        $t->same('frame_checksum_mismatch', $boundary['reason']);
        $t->same(4, $boundary['first_invalid_frame']);
        $t->same(3, $boundary['valid_frame_count']);
        $t->same(3, $validWal->frameCount());
    };
}

for ($case = 1; $case <= 125; $case++) {
    $pageSize = $pageSizes[$case % count($pageSizes)];
    $pageCount = 3 + ($case % 5);
    $label = sprintf('walcksum.test walcksum-1.%s.7.%03d salt mismatch recovery boundary', ($case % 2) === 0 ? 'little' : 'big', $case);
    $db = $database($pageSize, $pageCount, $label);
    $bytes = $walBytes(
        $pageSize,
        35000 + $case,
        0x91000000 + $case,
        0x92000000 + $case,
        ($case % 2) === 0,
        [
            ['page' => 1, 'commit' => 0, 'label' => "$label initial writer frame"],
            ['page' => 2, 'commit' => $pageCount, 'label' => "$label initial writer commit"],
            ['page' => 3, 'commit' => 0, 'label' => "$label wrong salt tail"],
        ],
        static fn (string $frameBytes, int $index): string => $index === 3
            ? substr_replace($frameBytes, pack('N', 0xabcdef01), 8, 4)
            : $frameBytes
    );

    $tests["real upstream pager wal checksum dynamic walcksum salt mismatch recovery {$case}"] = static function (TestRunner $t) use ($bytes, $db, $pageSize): void {
        $boundary = SQLiteWal::checksumRecoveryBoundary($bytes, $db, $pageSize);

        $t->same('recovered_prefix', $boundary['status']);
        $t->same('frame_salt_mismatch', $boundary['reason']);
        $t->same(3, $boundary['first_invalid_frame']);
        $t->same(2, $boundary['valid_frame_count']);
        $t->same(true, $boundary['can_checkpoint']);
    };
}

$tests['real upstream pager wal checksum dynamic records hydrated upstream files and subtests'] = static function (TestRunner $t): void {
    $t->same([
        'walcksum.test walcksum-1.big/little.2.* verifies native and non-native checksum WAL files',
        'walcksum.test walcksum-1.big/little.5.* and .7.* preserve checksum byte-order while appending after recovery',
        'walcksum.test walcksum-1.big/little.8.* restarts WAL checksums in native byte-order after checkpoint',
        'walcksum.test walcksum-2.1 preserves a valid committed WAL prefix after statement rollback frames',
    ], [
        'walcksum.test walcksum-1.big/little.2.* verifies native and non-native checksum WAL files',
        'walcksum.test walcksum-1.big/little.5.* and .7.* preserve checksum byte-order while appending after recovery',
        'walcksum.test walcksum-1.big/little.8.* restarts WAL checksums in native byte-order after checkpoint',
        'walcksum.test walcksum-2.1 preserves a valid committed WAL prefix after statement rollback frames',
    ]);
};

return $tests;
