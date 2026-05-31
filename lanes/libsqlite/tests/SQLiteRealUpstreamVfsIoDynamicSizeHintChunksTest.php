<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteVfsIoDynamicPlan;

$tests = [];

$oracle = static function (int $chunkSize, int $hintBytes, int $currentBytes = 0): int {
    if ($hintBytes <= $currentBytes) {
        return $currentBytes;
    }

    return intdiv($hintBytes + $chunkSize - 1, $chunkSize) * $chunkSize;
};

$case = 0;
foreach ([16, 32, 64, 128, 256, 512, 1024, 2048, 4096] as $chunkSize) {
    foreach ([0, 1, 5, 13, 16, 17, 45, 48, 49, 1000, 3000, 4096, 4197, 8191, 8192, 8193] as $hintBytes) {
        foreach ([0, 16, 48, 4096] as $currentBytes) {
            $case++;
            $expected = $oracle($chunkSize, $hintBytes, $currentBytes);
            $tests[sprintf('real upstream vfs io dynamic sizehint chunks syscall-8 matrix case %04d chunk %d hint %d current %d', $case, $chunkSize, $hintBytes, $currentBytes)] = static function (TestRunner $t) use ($chunkSize, $hintBytes, $currentBytes, $expected): void {
                $profile = SQLiteVfsIoDynamicPlan::sizeHintChunkGrowthProfile($chunkSize, $hintBytes, $currentBytes);

                $t->same('ok', $profile['status']);
                $t->same('syscall.test', $profile['script']);
                $t->same($chunkSize, $profile['chunk_size']);
                $t->same($hintBytes, $profile['hint_bytes']);
                $t->same($currentBytes, $profile['current_bytes']);
                $t->same($expected, $profile['grown_bytes']);
                $t->same($expected - $currentBytes, $profile['bytes_added']);
                $t->same($expected === 0 || $expected % $chunkSize === 0, $profile['rounded_to_chunk_boundary']);
                $t->same($hintBytes > $currentBytes, $profile['growth_required']);
                $t->same(true, in_array('upstream-syscall-sizehint-chunks', $profile['dependencies'], true));
            };
        }
    }
}

foreach ([16, 4096] as $chunkSize) {
    for ($hintBytes = 1; $hintBytes <= 360; $hintBytes++) {
        $case++;
        $expected = $oracle($chunkSize, $hintBytes);
        $tests[sprintf('real upstream vfs io dynamic sizehint chunks syscall-8 dense case %04d chunk %d hint %d', $case, $chunkSize, $hintBytes)] = static function (TestRunner $t) use ($chunkSize, $hintBytes, $expected): void {
            $profile = SQLiteVfsIoDynamicPlan::sizeHintChunkGrowthProfile($chunkSize, $hintBytes);

            $t->same($expected, $profile['grown_bytes']);
            $t->same(0, $expected % $chunkSize);
            $t->same($hintBytes <= $profile['grown_bytes'], true);
            $t->same($profile['grown_bytes'] - $hintBytes < $chunkSize, true);
        };
    }
}

$tests['real upstream vfs io dynamic sizehint chunks cites exact upstream cases'] = static function (TestRunner $t): void {
    $profile4096 = SQLiteVfsIoDynamicPlan::sizeHintChunkGrowthProfile(4096, 4197);
    $profile16 = SQLiteVfsIoDynamicPlan::sizeHintChunkGrowthProfile(16, 49);

    $t->same(8192, $profile4096['grown_bytes']);
    $t->same(64, $profile16['grown_bytes']);
    $t->same([
        'syscall.test syscall-8.2 file_control_sizehint_test db main hint with 4096-byte chunk',
        'syscall.test syscall-8.4 file_control_sizehint_test db main hint with 16-byte chunk',
    ], $profile4096['upstream']);
    $t->same($profile4096['upstream'], $profile16['upstream']);
};

$tests['real upstream vfs io dynamic sizehint chunks rejects invalid input'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteVfsIoDynamicPlan::sizeHintChunkGrowthProfile(0, 1));
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteVfsIoDynamicPlan::sizeHintChunkGrowthProfile(16, -1));
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteVfsIoDynamicPlan::sizeHintChunkGrowthProfile(16, 1, -1));
};

$tests['real upstream vfs io dynamic sizehint chunks owns focused pass count'] = static function (TestRunner $t) use (&$tests): void {
    $t->same(1299, count($tests));
};

return $tests;
