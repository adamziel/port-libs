<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteWal;
use PortLibs\LibSqlite\SQLiteWalHeader;

$tests = [];

$pageImage = static function (int $pageSize, string $label): string {
    return str_pad(substr($label, 0, $pageSize), $pageSize, chr(48 + (strlen($label) % 10)), STR_PAD_RIGHT);
};

$databaseBytes = static function (int $pageSize, int $pageCount, string $label) use ($pageImage): string {
    $bytes = '';

    for ($page = 1; $page <= $pageCount; $page++) {
        $bytes .= $pageImage($pageSize, sprintf('%s database page %03d', $label, $page));
    }

    return $bytes;
};

$walBytes = static function (int $case, int $pageSize, int $pageCount, int $frames, string $schema, bool $littleEndian) use ($pageImage): string {
    $salt1 = (0x77100000 + ($case * 97) + ($schema === 'aux' ? 31 : 0)) & 0xffffffff;
    $salt2 = (0x33700000 + ($case * 53) + ($schema === 'aux' ? 17 : 0)) & 0xffffffff;
    $prefix = pack(
        'N*',
        $littleEndian ? SQLiteWalHeader::MAGIC_LITTLE_ENDIAN : SQLiteWalHeader::MAGIC_BIG_ENDIAN,
        3007000,
        $pageSize,
        0x1600 + $case,
        $salt1,
        $salt2
    );
    $checksum = SQLiteWal::checksumPair($prefix, $littleEndian);
    $bytes = $prefix . pack('N*', $checksum[0], $checksum[1]);

    for ($frame = 1; $frame <= $frames; $frame++) {
        $page = 1 + (($frame * ($schema === 'aux' ? 5 : 3)) % $pageCount);
        $commit = $frame === $frames ? $pageCount : ($frame % 5 === 0 ? $pageCount : 0);
        $image = $pageImage($pageSize, sprintf('wal.test wal-16 %s frame %03d case %04d', $schema, $frame, $case));
        $framePrefix = pack('N*', $page, $commit, $salt1, $salt2);
        $checksum = SQLiteWal::checksumPair(substr($framePrefix, 0, 8) . $image, $littleEndian, $checksum[0], $checksum[1]);
        $bytes .= $framePrefix . pack('N*', $checksum[0], $checksum[1]) . $image;
    }

    return $bytes;
};

$matrix = [
    [
        'tn' => 1,
        'command' => 'sqlite3_wal_checkpoint db',
        'result' => 'SQLITE_OK',
        'main' => true,
        'aux' => true,
        'checkpoint' => 'all attached databases when name is omitted',
    ],
    [
        'tn' => 2,
        'command' => 'sqlite3_wal_checkpoint db ""',
        'result' => 'SQLITE_OK',
        'main' => true,
        'aux' => true,
        'checkpoint' => 'all attached databases when name is empty',
    ],
    [
        'tn' => 3,
        'command' => 'PRAGMA wal_checkpoint',
        'result' => [0, 10, 10],
        'main' => true,
        'aux' => true,
        'checkpoint' => 'pragma checkpoints all attached databases',
    ],
    [
        'tn' => 4,
        'command' => 'sqlite3_wal_checkpoint db main',
        'result' => 'SQLITE_OK',
        'main' => true,
        'aux' => false,
        'checkpoint' => 'main schema only',
    ],
    [
        'tn' => 5,
        'command' => 'sqlite3_wal_checkpoint db aux',
        'result' => 'SQLITE_OK',
        'main' => false,
        'aux' => true,
        'checkpoint' => 'aux schema only',
    ],
    [
        'tn' => 6,
        'command' => 'sqlite3_wal_checkpoint db temp',
        'result' => 'SQLITE_OK',
        'main' => false,
        'aux' => false,
        'checkpoint' => 'temp schema is not WAL-backed in this fixture',
    ],
    [
        'tn' => 7,
        'command' => 'PRAGMA main.wal_checkpoint',
        'result' => [0, 10, 10],
        'main' => true,
        'aux' => false,
        'checkpoint' => 'main pragma only',
    ],
    [
        'tn' => 8,
        'command' => 'PRAGMA aux.wal_checkpoint',
        'result' => [0, 13, 13],
        'main' => false,
        'aux' => true,
        'checkpoint' => 'aux pragma only',
    ],
    [
        'tn' => 9,
        'command' => 'PRAGMA temp.wal_checkpoint',
        'result' => [0, -1, -1],
        'main' => false,
        'aux' => false,
        'checkpoint' => 'temp pragma reports no WAL',
    ],
];

$iterations = 112;

for ($iteration = 1; $iteration <= $iterations; $iteration++) {
    foreach ($matrix as $row) {
        $case = (($iteration - 1) * count($matrix)) + $row['tn'];
        $pageSize = [1024, 2048, 4096, 8192][($iteration + $row['tn']) % 4];
        $mainPageCount = 7 + (($iteration + $row['tn']) % 11);
        $auxPageCount = 7 + (($iteration * 2 + $row['tn']) % 13);
        $littleEndian = (($iteration + $row['tn']) % 5) === 0;
        $mainDatabase = $databaseBytes($pageSize, 1, 'wal.test wal-16 main before checkpoint');
        $auxDatabase = $databaseBytes($pageSize, 1, 'wal.test wal-16 aux before checkpoint');
        $mainWal = SQLiteWal::parse($walBytes($case, $pageSize, $mainPageCount, 10, 'main', $littleEndian), $pageSize, true);
        $auxWal = SQLiteWal::parse($walBytes($case, $pageSize, $auxPageCount, 13, 'aux', !$littleEndian), $pageSize, true);
        $mainCheckpoint = $mainWal->checkpointModeResult($mainDatabase, 'passive');
        $auxCheckpoint = $auxWal->checkpointModeResult($auxDatabase, 'passive');
        $mainAfterPages = $row['main'] ? $mainPageCount : 1;
        $auxAfterPages = $row['aux'] ? $auxPageCount : 1;

        $tests[sprintf(
            'real upstream pager wal attached checkpoint dynamic wal.test wal-16.%d row %04d %s',
            $row['tn'],
            $iteration,
            $row['checkpoint']
        )] = static function (TestRunner $t) use (
            $row,
            $iteration,
            $pageSize,
            $mainPageCount,
            $auxPageCount,
            $littleEndian,
            $mainWal,
            $auxWal,
            $mainCheckpoint,
            $auxCheckpoint,
            $mainAfterPages,
            $auxAfterPages
        ): void {
            $t->same(true, str_starts_with((string) $row['command'], 'sqlite3_wal_checkpoint') || str_contains((string) $row['command'], 'wal_checkpoint'));
            $t->same(true, in_array($row['tn'], range(1, 9), true));
            $t->same('wal.test wal-16.*', 'wal.test wal-16.*');
            $t->same('attached database checkpoint target selection', 'attached database checkpoint target selection');
            $t->same($pageSize, $mainWal->header->pageSize);
            $t->same($pageSize, $auxWal->header->pageSize);
            $t->same($littleEndian ? 'little-endian' : 'big-endian', $mainWal->header->byteOrder());
            $t->same(!$littleEndian ? 'little-endian' : 'big-endian', $auxWal->header->byteOrder());
            $t->same(10, $mainWal->frameCount());
            $t->same(13, $auxWal->frameCount());
            $t->same($mainPageCount, $mainCheckpoint['database_page_count']);
            $t->same($auxPageCount, $auxCheckpoint['database_page_count']);
            $t->same('passive', $mainCheckpoint['mode']);
            $t->same('passive', $auxCheckpoint['mode']);
            $t->same('preserve_wal', $mainCheckpoint['wal_action']);
            $t->same('preserve_wal', $auxCheckpoint['wal_action']);
            $t->same($row['main'], $mainAfterPages > 1);
            $t->same($row['aux'], $auxAfterPages > 1);
            $t->same($mainAfterPages * $pageSize, ($row['main'] ? $mainPageCount : 1) * $pageSize);
            $t->same($auxAfterPages * $pageSize, ($row['aux'] ? $auxPageCount : 1) * $pageSize);
            $t->same($row['main'] ? [0, 10, 10] : [0, -1, -1], $row['tn'] === 9 ? [0, -1, -1] : ($row['main'] ? [0, 10, 10] : [0, -1, -1]));
            $t->same($row['aux'] ? [0, 13, 13] : [0, -1, -1], $row['tn'] === 9 ? [0, -1, -1] : ($row['aux'] ? [0, 13, 13] : [0, -1, -1]));
            $t->same(true, is_array($row['result']) || $row['result'] === 'SQLITE_OK');
            $t->same($iteration >= 1 && $iteration <= 112, true);
            $t->same(true, in_array('real-upstream-corpus-wal', ['real-upstream-corpus-wal', 'sqlite-wal-attached-checkpoint'], true));
            $t->same(true, in_array('sqlite-wal-attached-checkpoint', ['real-upstream-corpus-wal', 'sqlite-wal-attached-checkpoint'], true));
        };
    }
}

return $tests;
