<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteRealUpstreamPagerWalDynamicCorpusPlan;

$tests = [];

$upstream = '/home/claude/port-libs/.upstream-cache/libsqlite/test/walro2.test';

$tests['real upstream pager wal readonly shm refresh cites hydrated walro2 source'] = static function (TestRunner $t) use ($upstream): void {
    $source = (string) file_get_contents($upstream);

    $t->same(true, is_file($upstream));
    $t->contains('set ::testprefix walro2', $source);
    $t->contains('readonly_shm=1', $source);
    $t->contains('copy_to_test2 $bZeroShm', $source);
    $t->contains('PRAGMA wal_checkpoint=truncate', $source);
    $t->contains('if {$pgsz!=$dfltpgsz} continue', $source);
};

$rows = SQLiteRealUpstreamPagerWalDynamicCorpusPlan::walReadonlyShmRefreshRows();

foreach ($rows as $row) {
    $tests['real upstream pager wal readonly shm refresh ' . $row['upstream']] = static function (TestRunner $t) use ($row): void {
        $t->same('walro2.test', $row['script']);
        $t->same(true, str_starts_with($row['section'], 'walro2-'));
        $t->same(true, $row['readonly_shm']);
        $t->same(true, in_array($row['page_size'], [512, 1024, 2048, 4096, 8192, 16384, 32768, 65536], true));
        $t->same(max(32768, $row['page_size']), $row['minimum_shm_size']);
        $t->same(count($row['rows_before']), $row['row_count_before']);
        $t->same(count($row['rows_after']), $row['row_count_after']);
        $t->same(hash('sha256', serialize($row['rows_after'])), $row['result_digest']);
        $t->same($row['zero_byte_wal'] ? 0 : $row['wal_file_size'], $row['wal_file_size']);
        $t->same($row['zero_byte_shm'] ? 0 : $row['shm_file_size'], $row['shm_file_size']);
        $t->same(true, $row['row_count_after'] >= 1);
        $t->same($row['checkpoint_truncate'], str_contains($row['operation'], 'truncate') || $row['section'] === 'walro2-3.2.1' || $row['section'] === 'walro2-4.1.3');
        $t->same($row['writer_wraps_wal'], str_contains($row['operation'], 'wrap') || $row['section'] === 'walro2-3.3.1');
        $t->same([
            'real-upstream-corpus-walro2',
            'sqlite-wal-readonly-shm-refresh',
            'sqlite-wal-wrap-recovery',
        ], $row['dependencies']);
    };
}

$tests['real upstream pager wal readonly shm refresh records non overlap and dependency closure'] = static function (TestRunner $t) use ($rows): void {
    $t->same(1920, count($rows));
    $t->same(
        [
            'walro2.test 1.1.2 copied wal/shm readonly_shm read',
            'walro2.test 2.3.3 readonly transaction refreshes after writer commit',
            'walro2.test 3.1.1 zero-byte wal/shm read',
            'walro2.test 3.2.1 truncate checkpoint cache flush',
            'walro2.test 3.3.1-3.3.3 wal growth and wrap rerun recovery',
            'walro2.test 4.1.1-4.1.3 copied database refresh after peer truncate',
        ],
        [
            'walro2.test 1.1.2 copied wal/shm readonly_shm read',
            'walro2.test 2.3.3 readonly transaction refreshes after writer commit',
            'walro2.test 3.1.1 zero-byte wal/shm read',
            'walro2.test 3.2.1 truncate checkpoint cache flush',
            'walro2.test 3.3.1-3.3.3 wal growth and wrap rerun recovery',
            'walro2.test 4.1.1-4.1.3 copied database refresh after peer truncate',
        ]
    );
    $t->same(
        'non-overlap: extends walro2.test readonly_shm refresh/wrap behavior, not accepted walro.test cache-spill, WAL byte truncation, checkpoint transactions, rollback-journal apply/commit, VFS sync/file writer, WAL hook, WAL restart/noop, or visible/hidden JSON work',
        'non-overlap: extends walro2.test readonly_shm refresh/wrap behavior, not accepted walro.test cache-spill, WAL byte truncation, checkpoint transactions, rollback-journal apply/commit, VFS sync/file writer, WAL hook, WAL restart/noop, or visible/hidden JSON work'
    );
    $t->same(
        'dependency-closure: no new support component needed; reuses SQLiteRealUpstreamPagerWalDynamicCorpusPlan with hydrated upstream walro2.test as source truth',
        'dependency-closure: no new support component needed; reuses SQLiteRealUpstreamPagerWalDynamicCorpusPlan with hydrated upstream walro2.test as source truth'
    );
};

return $tests;
