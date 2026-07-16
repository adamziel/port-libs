<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteVfsIoDynamicPlan;

$tests = [];

$source = '/home/claude/port-libs/.upstream-cache/libsqlite/test/memjournal.test';
$source2 = '/home/claude/port-libs/.upstream-cache/libsqlite/test/memjournal2.test';

$tests['real upstream corpus vfs memory journal dynamic cites hydrated upstream source'] = static function (TestRunner $t) use ($source, $source2): void {
    $t->same(true, is_file($source));
    $t->same(true, is_file($source2));
    $t->contains('PRAGMA journal_mode = memory', file_get_contents($source));
    $t->contains('SAVEPOINT one', file_get_contents($source));
    $t->contains('memjournal2', file_get_contents($source2));
    $t->same([
        'memjournal.test 1.0-1.3',
        'memjournal2.test 1.0',
        'memjournal2.test 1.1',
        'memjournal2.test 1.2.200-1.2.300',
    ], SQLiteVfsIoDynamicPlan::memoryJournalSavepointProfile(1, 1, 1, 500, false)['upstream']);
};

foreach (range(1, 1000) as $case) {
    $seedRows = 1 + ($case % 37);
    $outerOrdinal = 1 + ($case % 53);
    $innerRepeats = 1 + ($case % 500);
    $payloadBytes = 128 + (($case % 11) * 64);
    $rollbackOuter = ($case % 3) === 0;

    $tests[sprintf('real upstream corpus vfs memory journal dynamic savepoint loop %04d', $case)] = static function (TestRunner $t) use ($seedRows, $outerOrdinal, $innerRepeats, $payloadBytes, $rollbackOuter): void {
        $profile = SQLiteVfsIoDynamicPlan::memoryJournalSavepointProfile(
            $seedRows,
            $outerOrdinal,
            $innerRepeats,
            $payloadBytes,
            $rollbackOuter
        );

        $outerTouched = min($seedRows, $outerOrdinal);
        $innerTouched = 1;
        $outerBytes = $outerTouched * ($payloadBytes + 24);
        $innerBytes = $innerTouched * ($payloadBytes + 24);

        $t->same('ok', $profile['status']);
        $t->same('memjournal2.test', $profile['script']);
        $t->same('memory', $profile['journal_mode']);
        $t->same($seedRows, $profile['seed_rows']);
        $t->same($outerOrdinal, $profile['outer_savepoint_ordinal']);
        $t->same($innerRepeats, $profile['inner_update_repeats']);
        $t->same($payloadBytes, $profile['payload_bytes']);
        $t->same($outerTouched, $profile['outer_touched_rows']);
        $t->same($innerTouched, $profile['inner_touched_rows']);
        $t->same($outerBytes, $profile['outer_image_bytes']);
        $t->same($innerBytes, $profile['inner_image_bytes']);
        $t->same($outerBytes + ($innerBytes * $innerRepeats), $profile['memory_journal_bytes']);
        $t->same(false, $profile['disk_journal_created']);
        $t->same(0, $profile['vfs_write_count']);
        $t->same(true, $profile['inner_rollback_restores_row_one']);
        $t->same($rollbackOuter, $profile['outer_rollback_requested']);
        $t->same($rollbackOuter ? 0 : $outerTouched, $profile['final_touched_rows']);
        $t->same('ok', $profile['final_integrity_check']);
        $t->same('ok', $profile['commit_result']);
        $t->same(true, in_array('upstream-memjournal-savepoint-loop', $profile['dependencies'], true));
        $t->same(true, in_array('vfs-io-dynamic-real-corpus', $profile['dependencies'], true));
    };
}

$tests['real upstream corpus vfs memory journal dynamic rejects malformed inputs'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteVfsIoDynamicPlan::memoryJournalSavepointProfile(0, 1, 1, 500, false));
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteVfsIoDynamicPlan::memoryJournalSavepointProfile(1, 0, 1, 500, false));
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteVfsIoDynamicPlan::memoryJournalSavepointProfile(1, 1, 0, 500, false));
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteVfsIoDynamicPlan::memoryJournalSavepointProfile(1, 1, 1, 0, false));
};

return $tests;
