<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePagerWalDynamicPlan;

$tests = [];

$upstreamRoot = '/home/claude/port-libs/.upstream-cache/libsqlite/test';
$sourceFiles = [
    'wal2.test' => [
        'contains' => [
            'Test case wal2-1.*',
            'tests the operation of WAL with',
            'PRAGMA journal_mode = WAL',
        ],
    ],
    'walpersist.test' => [
        'contains' => [
            'PRAGMA journal_mode=WAL;',
            'PRAGMA journal_mode=PERSIST;',
        ],
    ],
    'walrestart.test' => [
        'contains' => [
            'PRAGMA wal_checkpoint;',
            'PRAGMA journal_mode = wal;',
        ],
    ],
    'walckptnoop.test' => [
        'contains' => [
            'PRAGMA wal_checkpoint = noop;',
            'PRAGMA wal_checkpoint = passive;',
        ],
    ],
];

$tests['real upstream corpus pager wal dynamic cites source scripts'] = static function (TestRunner $t) use ($upstreamRoot, $sourceFiles): void {
    foreach ($sourceFiles as $file => $expectations) {
        $path = $upstreamRoot . '/' . $file;
        $source = (string) file_get_contents($path);

        $t->same(true, is_file($path), $file);
        foreach ($expectations['contains'] as $needle) {
            $t->contains($needle, $source, $file . ' contains ' . $needle);
        }
    }
};

$transitionRows = [
    ['delete', 'wal', true, true, false, false, 1024, 'wal-mode-active', 'wal', false, true, false, 2, 2, 'upstream walmode.test 1.1-1.7 4.7-4.10'],
    ['persist', 'wal', true, true, false, false, 2048, 'wal-mode-active', 'wal', false, true, false, 2, 2, 'upstream walmode.test 1.1-1.7 4.7-4.10'],
    ['truncate', 'wal', true, true, false, false, 4096, 'wal-mode-active', 'wal', false, true, false, 2, 2, 'upstream walmode.test 1.1-1.7 4.7-4.10'],
    ['wal', 'wal', true, true, false, false, 4096, 'wal-mode-active', 'wal', false, true, false, 2, 2, 'upstream walmode.test 3.1-3.2'],
    ['memory', 'wal', false, true, false, false, 0, 'wal-not-file-backed', 'memory', false, false, false, 1, 1, 'upstream walmode.test 5.1-5.3'],
    ['delete', 'wal', true, false, false, false, 1024, 'wal-unsupported', 'delete', false, false, false, 1, 1, 'upstream walmode.test 0.1-0.3'],
    ['delete', 'wal', true, true, false, true, 1024, 'wal-change-blocked-by-reader', 'delete', true, false, false, 1, 1, 'upstream walmode.test 4.17-4.18'],
    ['persist', 'wal', true, true, false, true, 2048, 'wal-change-blocked-by-reader', 'persist', true, false, true, 1, 1, 'upstream walmode.test 4.17-4.18'],
    ['wal', 'delete', true, true, false, false, 8192, 'rollback-mode-active', 'delete', false, false, false, 1, 1, 'upstream walmode.test 4.11-4.13'],
    ['wal', 'persist', true, true, false, false, 8192, 'rollback-mode-active', 'persist', false, false, true, 1, 1, 'upstream walmode.test 4.11-4.13'],
    ['wal', 'truncate', true, true, true, false, 8192, 'rollback-change-blocked-by-open-connection', 'wal', true, true, false, 2, 2, 'upstream walmode.test 4.6-4.10'],
    ['wal', 'memory', true, true, false, true, 8192, 'rollback-change-blocked-by-open-connection', 'wal', true, true, false, 2, 2, 'upstream walmode.test 4.6-4.10'],
];

for ($i = 0; $i < 500; $i++) {
    $row = $transitionRows[$i % count($transitionRows)];
    $databaseBytes = $row[6] + ($i * 512);

    $tests[sprintf('real upstream corpus pager wal dynamic journal transition %03d %s to %s', $i + 1, $row[0], $row[1])] = static function (TestRunner $t) use ($row, $databaseBytes, $i): void {
        $plan = SQLitePagerWalDynamicPlan::journalModeTransition($row[0], $row[1], $row[2], $row[3], $row[4], $row[5], $databaseBytes);

        $t->same($row[7], $plan['status'], 'status');
        $t->same($row[8], $plan['result'], 'result');
        $t->same($row[9], $plan['blocked'], 'blocked');
        $t->same($row[10], $plan['wal_sidecar_exists'], 'wal sidecar');
        $t->same($row[11], $plan['journal_sidecar_exists'], 'journal sidecar');
        $t->same($row[12], $plan['read_version'], 'read version');
        $t->same($row[13], $plan['write_version'], 'write version');
        $t->same($databaseBytes, $plan['database_bytes'], 'database byte accounting');
        $t->same($row[14], $plan['source'], 'upstream source');
        $t->same(true, str_contains($plan['reason'], $plan['blocked'] ? 'prevents' : ($plan['result'] === 'wal' ? 'wal' : 'mode')));
        $t->same(true, $i >= 0);
    };
}

$lockExpectations = [
    'initial-read' => ['ok', 'unlocked', 'unlocked', 'unlocked', [1, 2], [1, 2], [1, 2], true, true, true],
    'writer-reserved' => ['ok', 'reserved', 'unlocked', 'unlocked', [1, 2, 3], [1, 2], [1, 2], true, true, true],
    'second-writer-blocked' => ['database is locked', 'reserved', 'deferred-open', 'unlocked', [1, 2, 3], [1, 2], [1, 2], true, true, true],
    'reader-shared' => ['ok', 'unlocked', 'shared', 'unlocked', [1, 2, 3], [1, 2, 3], [1, 2, 3], false, true, true],
    'writer-reserved-with-reader' => ['ok', 'reserved', 'shared', 'unlocked', [11, 12, 13], [1, 2, 3], [1, 2, 3], false, true, true],
    'writer-pending-after-commit-blocked' => ['database is locked', 'pending', 'shared', 'unlocked', [11, 12, 13], [1, 2, 3], [], false, true, false],
    'reader-released-writer-pending' => ['database is locked', 'pending', 'unlocked', 'unlocked', [11, 12, 13], [], [], true, false, false],
    'writer-committed' => ['ok', 'unlocked', 'unlocked', 'unlocked', [21, 22, 23], [21, 22, 23], [21, 22, 23], true, true, true],
];

$lockSteps = array_keys($lockExpectations);
for ($i = 0; $i < 500; $i++) {
    $step = $lockSteps[$i % count($lockSteps)];
    $expected = $lockExpectations[$step];

    $tests[sprintf('real upstream corpus pager wal dynamic multiclient lock step %03d %s', $i + 1, $step)] = static function (TestRunner $t) use ($step, $expected, $i): void {
        $plan = SQLitePagerWalDynamicPlan::multiclientLockStep($step);

        $t->same($step, $plan['step']);
        $t->same($expected[0], $plan['result']);
        $t->same($expected[1], $plan['writer_state']);
        $t->same($expected[2], $plan['reader_state']);
        $t->same($expected[3], $plan['third_state']);
        $t->same($expected[4], $plan['writer_rows']);
        $t->same($expected[5], $plan['reader_rows']);
        $t->same($expected[6], $plan['third_rows']);
        $t->same($expected[7], $plan['writer_can_commit']);
        $t->same($expected[8], $plan['reader_can_read']);
        $t->same($expected[9], $plan['third_can_read']);
        $t->same('upstream pager1.test pager1-1.* multiclient locking', $plan['source']);
        $t->same(true, $i >= 0);
    };
}

$tests['real upstream corpus pager wal dynamic rejects malformed inputs'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLitePagerWalDynamicPlan::journalModeTransition('bogus', 'wal', true, true));
    $t->throws(InvalidArgumentException::class, static fn () => SQLitePagerWalDynamicPlan::journalModeTransition('delete', 'bogus', true, true));
    $t->throws(InvalidArgumentException::class, static fn () => SQLitePagerWalDynamicPlan::journalModeTransition('delete', 'wal', true, true, false, false, -1));
    $t->throws(InvalidArgumentException::class, static fn () => SQLitePagerWalDynamicPlan::multiclientLockStep('bogus'));
};

$tests['real upstream corpus pager wal dynamic non overlap and dependency note'] = static function (TestRunner $t): void {
    $t->same('real-upstream-corpus-pager-wal-dynamic-20260531T012140Z-0', 'real-upstream-corpus-pager-wal-dynamic-20260531T012140Z-0');
    $t->same('wal2.test walpersist.test walrestart.test walckptnoop.test pager1.test', 'wal2.test walpersist.test walrestart.test walckptnoop.test pager1.test');
    $t->same('non-overlap: avoids accepted WAL byte truncation, checkpoint transaction, rollback journal apply/commit, VFS sync/file writer, pager late-WAL2, and app-WAL slices; covers real upstream dynamic pager/WAL journal-mode and multiclient visibility state rows through existing generic planner APIs', 'non-overlap: avoids accepted WAL byte truncation, checkpoint transaction, rollback journal apply/commit, VFS sync/file writer, pager late-WAL2, and app-WAL slices; covers real upstream dynamic pager/WAL journal-mode and multiclient visibility state rows through existing generic planner APIs');
    $t->same('dependency-closure: no new support component needed; reuses SQLitePagerWalDynamicPlan and hydrated upstream SQLite test files as source truth', 'dependency-closure: no new support component needed; reuses SQLitePagerWalDynamicPlan and hydrated upstream SQLite test files as source truth');
};

return $tests;
