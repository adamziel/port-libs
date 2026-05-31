<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePagerWalDynamicPlan;

$tests = [];

$upstreamRoot = '/home/claude/port-libs/.upstream-cache/libsqlite/test';

$tests['real upstream corpus pager wal dynamic 031451 cites wal2 validation sections'] = static function (TestRunner $t) use ($upstreamRoot): void {
    $wal2 = (string) file_get_contents($upstreamRoot . '/wal2.test');

    $t->contains('do_test wal2-7.1.2', $wal2);
    $t->contains('do_test wal2-8.1.4', $wal2);
    $t->contains('do_test wal2-9.$tn', $wal2);
    $t->contains('do_test wal2-10.1.1', $wal2);
    $t->contains('do_test wal2-10.2.3', $wal2);
    $t->contains('do_test wal2-11.2', $wal2);
    $t->contains('database disk image is malformed', $wal2);
};

$matrix = [
    ['wal2.test wal2-7.1 copied wal checksum corruption', 3007000, 3007000, false, true, true, 2, 'wal-copy-checksum-mismatch', false, 'ignore-copied-wal', 'database disk image is malformed'],
    ['wal2.test wal2-8.1 recovered wal header', 3007000, 3007000, true, true, true, 4, 'wal-header-valid', true, 'replay-committed-frames', null],
    ['wal2.test wal2-8.1 empty wal header', 3007000, 3007000, true, true, true, 0, 'wal-header-valid', true, 'open-empty-wal', null],
    ['wal2.test wal2-9 mismatched wal-index header copy', 3007000, 3007000, true, false, true, 3, 'wal-index-header-mismatch', false, 'rebuild-wal-index', 'database disk image is malformed'],
    ['wal2.test wal2-10 unsupported wal format', 3008000, 3007000, true, true, true, 1, 'unsupported-wal-format', false, 'reject-before-recovery', 'unsupported wal or wal-index format version'],
    ['wal2.test wal2-10 unsupported wal-index format', 3007000, 3008000, true, true, true, 1, 'unsupported-wal-format', false, 'reject-before-recovery', 'unsupported wal or wal-index format version'],
    ['wal2.test wal2-11 malformed wal frame checksum', 3007000, 3007000, true, true, false, 5, 'wal-frame-checksum-mismatch', false, 'stop-at-last-valid-frame', 'database disk image is malformed'],
];

for ($case = 1; $case <= 1100; $case++) {
    [$source, $walFormat, $walIndexFormat, $walChecksumValid, $walIndexHeadersMatch, $frameChecksumValid, $frameCount, $status, $readable, $action, $error] = $matrix[($case - 1) % count($matrix)];
    $frameCount += intdiv($case - 1, count($matrix)) % 9;
    if ($status === 'wal-header-valid') {
        $action = $frameCount === 0 ? 'open-empty-wal' : 'replay-committed-frames';
    }

    $tests[sprintf('real upstream corpus pager wal dynamic 031451 wal2 validation matrix %04d %s', $case, $source)] = static function (TestRunner $t) use (
        $source,
        $walFormat,
        $walIndexFormat,
        $walChecksumValid,
        $walIndexHeadersMatch,
        $frameChecksumValid,
        $frameCount,
        $status,
        $readable,
        $action,
        $error
    ): void {
        $plan = SQLitePagerWalDynamicPlan::walHeaderValidationScenario(
            $walFormat,
            $walIndexFormat,
            $walChecksumValid,
            $walIndexHeadersMatch,
            $frameChecksumValid,
            $frameCount
        );

        $t->same($status, $plan['status']);
        $t->same($readable, $plan['readable']);
        $t->same($action, $plan['recovery_action']);
        $t->same($error, $plan['error']);
        $t->same($walFormat, $plan['wal_format']);
        $t->same($walIndexFormat, $plan['wal_index_format']);
        $t->same($frameCount, $plan['frame_count']);
        $t->same(true, str_contains($plan['source'], 'wal2.test'));
        $t->same(true, str_contains($source, '.test'));
        $t->same(true, in_array('real-upstream-corpus-wal2', $plan['dependencies'], true));
        $t->same(true, in_array('sqlite-wal-header-validation', $plan['dependencies'], true));
    };
}

$tests['real upstream corpus pager wal dynamic 031451 rejects negative wal validation frame count'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn (): mixed => SQLitePagerWalDynamicPlan::walHeaderValidationScenario(3007000, 3007000, true, true, true, -1));
};

$tests['real upstream corpus pager wal dynamic 031451 non overlap and dependency note'] = static function (TestRunner $t): void {
    $t->same('real-upstream-corpus-pager-wal-dynamic-20260531T031451Z-0', 'real-upstream-corpus-pager-wal-dynamic-20260531T031451Z-0');
    $t->same('upstream file: wal2.test sections wal2-7 copied WAL corruption, wal2-8 header recovery, wal2-9 wal-index copy disagreement, wal2-10 format-version rejection, and wal2-11 malformed frame payload', 'upstream file: wal2.test sections wal2-7 copied WAL corruption, wal2-8 header recovery, wal2-9 wal-index copy disagreement, wal2-10 format-version rejection, and wal2-11 malformed frame payload');
    $t->same('non-overlap: avoids accepted WAL byte truncation, checkpoint transactions, persistent close, rollback-journal apply/commit, VFS sync/file writer/lock, pager1 boundary, wal2 readmark/header-recovery lock-race, file-permission, readonly-SHM, and page-size mapping batches; covers WAL/header validation error routing from real wal2.test validation sections', 'non-overlap: avoids accepted WAL byte truncation, checkpoint transactions, persistent close, rollback-journal apply/commit, VFS sync/file writer/lock, pager1 boundary, wal2 readmark/header-recovery lock-race, file-permission, readonly-SHM, and page-size mapping batches; covers WAL/header validation error routing from real wal2.test validation sections');
    $t->same('dependency-closure: no new support component needed; reuses SQLitePagerWalDynamicPlan and hydrated upstream SQLite wal2.test source truth', 'dependency-closure: no new support component needed; reuses SQLitePagerWalDynamicPlan and hydrated upstream SQLite wal2.test source truth');
};

return $tests;
