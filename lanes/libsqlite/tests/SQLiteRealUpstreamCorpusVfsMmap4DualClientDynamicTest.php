<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteVfsIoDynamicPlan;

$tests = [];

$mmap4Cases = [
    1 => [6144, 167773],
    2 => [18432, 140399],
    3 => [43008, 401302],
    4 => [92160, 253899],
    5 => [190464, 2],
    6 => [387072, 752431],
    7 => [780288, 291143],
    8 => [1566720, 594306],
    9 => [3139584, 829137],
    10 => [6285312, 793963],
    11 => [12576768, 1015590],
];

$ownedCases = 0;
foreach ($mmap4Cases as $case => [$firstMmapSize, $secondMmapSize]) {
    foreach (range(1, 100) as $iteration) {
        $ownedCases++;
        $tests[sprintf('real upstream corpus vfs mmap4 dual client dynamic %04d case %02d iteration %03d', $ownedCases, $case, $iteration)] =
            static function (TestRunner $t) use ($case, $firstMmapSize, $secondMmapSize, $iteration): void {
                $profile = SQLiteVfsIoDynamicPlan::mmapDualClientRemapProfile($case, $firstMmapSize, $secondMmapSize, $iteration);
                $writer = ($iteration % 2) === 1 ? 'connection1' : 'connection2';
                $reader = $writer === 'connection1' ? 'connection2' : 'connection1';
                $writerMmapSize = $writer === 'connection1' ? $firstMmapSize : $secondMmapSize;
                $readerMmapSize = $reader === 'connection1' ? $firstMmapSize : $secondMmapSize;

                $t->same('ok', $profile['status']);
                $t->same('mmap4.test', $profile['script']);
                $t->same('mmap4-' . $case . '.dual-client.' . $iteration, $profile['scenario']);
                $t->same($case, $profile['case']);
                $t->same($iteration, $profile['iteration']);
                $t->same($firstMmapSize, $profile['first_mmap_size']);
                $t->same($secondMmapSize, $profile['second_mmap_size']);
                $t->same($writer, $profile['writer']);
                $t->same($reader, $profile['reader']);
                $t->same($writerMmapSize, $profile['writer_mmap_size']);
                $t->same($readerMmapSize, $profile['reader_mmap_size']);
                $t->same($writerMmapSize >= 5000, $profile['writer_uses_mmap']);
                $t->same($readerMmapSize >= 5000, $profile['reader_uses_mmap']);
                $t->same(5000, $profile['inserted_blob_bytes']);
                $t->same($iteration, $profile['row_count_after_iteration']);
                $t->same('md5sum(a)', $profile['checksum_source']);
                $t->same(true, $profile['checksum_matches_peer_read']);
                $t->same('ok', $profile['integrity_check']);
                $t->same([$iteration, 1, 'ok'], $profile['peer_result']);
                $t->same($firstMmapSize !== $secondMmapSize || ($writerMmapSize >= 5000) !== ($readerMmapSize >= 5000), $profile['remap_required']);
                $t->same($readerMmapSize < 5000, $profile['fallback_read_path']);
                $t->same(true, $profile['connection_reusable_after_remap']);
                $t->same(true, in_array('mmap4.test ' . $case . '.* dual-client mmap_size settings', $profile['upstream'], true));
                $t->same(true, in_array('mmap4.test ' . $case . '.* peer SELECT count/md5sum/integrity_check', $profile['upstream'], true));
                $t->same(true, in_array('upstream-mmap4-test', $profile['dependencies'], true));
                $t->same(true, in_array('sqlite-mmap-dual-client-remap', $profile['dependencies'], true));
                $t->same(true, in_array('vfs-io-dynamic-real-corpus', $profile['dependencies'], true));
            };
    }
}

$tests['real upstream corpus vfs mmap4 dual client dynamic owns upstream matrix count'] = static function (TestRunner $t) use ($ownedCases): void {
    $profile = SQLiteVfsIoDynamicPlan::mmapDualClientRemapProfile(11, 12576768, 1015590, 100);

    $t->same(1100, $ownedCases);
    $t->same('mmap4.test', $profile['script']);
    $t->same('mmap4-11.dual-client.100', $profile['scenario']);
    $t->same(['mmap4.test 11.* dual-client mmap_size settings', 'mmap4.test 11.* alternating INSERT/UPDATE writer', 'mmap4.test 11.* peer SELECT count/md5sum/integrity_check'], $profile['upstream']);
};

$tests['real upstream corpus vfs mmap4 dual client dynamic rejects malformed inputs'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteVfsIoDynamicPlan::mmapDualClientRemapProfile(0, 1, 1, 1));
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteVfsIoDynamicPlan::mmapDualClientRemapProfile(12, 1, 1, 1));
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteVfsIoDynamicPlan::mmapDualClientRemapProfile(1, -1, 1, 1));
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteVfsIoDynamicPlan::mmapDualClientRemapProfile(1, 1, -1, 1));
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteVfsIoDynamicPlan::mmapDualClientRemapProfile(1, 1, 1, 0));
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteVfsIoDynamicPlan::mmapDualClientRemapProfile(1, 1, 1, 101));
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteVfsIoDynamicPlan::mmapDualClientRemapProfile(1, 1, 1, 1, 0));
};

return $tests;
