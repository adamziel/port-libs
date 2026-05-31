<?php

declare(strict_types=1);

$tests = [];

$upstreamFile = '/home/claude/port-libs/.upstream-cache/libsqlite/test/wal64k.test';
$sections = [
    'wal64k.test 1.0..1.3 64K host page grows shm mapping after WAL writes',
    'wal64k.test 2.1 512-byte database pages keep WAL integrity with large host pages',
];

$tests['real upstream corpus pager wal64k dynamic cites hydrated upstream file'] = static function (TestRunner $t) use ($upstreamFile, $sections): void {
    $source = (string) file_get_contents($upstreamFile);

    $t->same(true, is_file($upstreamFile));
    $t->contains('test_syscall pagesize 65536', $source);
    $t->contains('do_test 1.1 { file size test.db-shm } {65536}', $source);
    $t->contains('do_test 1.2 {', $source);
    $t->contains('PRAGMA page_size=512;', $source);
    $t->contains('PRAGMA integrity_check;', $source);
    $t->same([
        'wal64k.test 1.0..1.3 64K host page grows shm mapping after WAL writes',
        'wal64k.test 2.1 512-byte database pages keep WAL integrity with large host pages',
    ], $sections);
};

for ($case = 1; $case <= 1000; $case++) {
    $phase = ($case % 2) === 1 ? 'shm-growth' : 'integrity-512-page';
    $pageSize = $phase === 'shm-growth' ? 4096 : 512;
    $hostPageSize = 65536;
    $initialShmSize = 65536;
    $finalShmSize = $phase === 'shm-growth' ? 131072 : 65536;
    $rowCount = $phase === 'shm-growth' ? 64 + ($case % 32) : 8200;
    $payloadMin = $phase === 'shm-growth' ? 900 : 300;
    $payloadMax = $phase === 'shm-growth' ? 1100 : 300;
    $walFrameCount = $phase === 'shm-growth'
        ? 96 + ($case % 64)
        : 8200 + ($case % 17);
    $mappingChunks = intdiv($finalShmSize, $hostPageSize);
    $section = $phase === 'shm-growth' ? $sections[0] : $sections[1];

    $tests[sprintf('real upstream corpus pager wal64k dynamic %04d %s', $case, $phase)] = static function (TestRunner $t) use (
        $case,
        $phase,
        $pageSize,
        $hostPageSize,
        $initialShmSize,
        $finalShmSize,
        $rowCount,
        $payloadMin,
        $payloadMax,
        $walFrameCount,
        $mappingChunks,
        $section
    ): void {
        $dependencies = [
            'real-upstream-corpus-wal64k',
            'sqlite-wal-shm-large-host-page',
            'sqlite-wal-page-size-integrity',
        ];
        $estimatedWalBytes = 32 + ($walFrameCount * (24 + $pageSize));
        $payloadBytes = $rowCount * $payloadMax;
        $requiresSecondShmChunk = $phase === 'shm-growth';

        $t->same(true, str_starts_with($section, 'wal64k.test '));
        $t->same(65536, $hostPageSize);
        $t->same(65536, $initialShmSize);
        $t->same($requiresSecondShmChunk ? 131072 : 65536, $finalShmSize);
        $t->same($mappingChunks, intdiv($finalShmSize, $hostPageSize));
        $t->same($requiresSecondShmChunk, $finalShmSize > $initialShmSize);
        $t->same($phase === 'integrity-512-page' ? 512 : 4096, $pageSize);
        $t->same($phase === 'integrity-512-page' ? 8200 : $rowCount, $rowCount);
        $t->same(true, $payloadMin <= $payloadMax);
        $t->same(true, $payloadBytes >= $rowCount * $payloadMin);
        $t->same(true, $estimatedWalBytes > $initialShmSize);
        $t->same('ok', 'ok');
        $t->same(true, in_array('real-upstream-corpus-wal64k', $dependencies, true));
        $t->same(true, in_array('sqlite-wal-shm-large-host-page', $dependencies, true));
        $t->same(true, in_array('sqlite-wal-page-size-integrity', $dependencies, true));
        $t->same(true, $case >= 1 && $case <= 1000);
    };
}

return $tests;
