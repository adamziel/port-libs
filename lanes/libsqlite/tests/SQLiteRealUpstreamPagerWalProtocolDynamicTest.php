<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteRealUpstreamPagerWalDynamicPlan;

$tests = [];

foreach (SQLiteRealUpstreamPagerWalDynamicPlan::walProtocolRetrySnapshotCases() as $case) {
    $tests[sprintf(
        'real upstream pager wal protocol dynamic %04d %s %s page %d',
        $case['case'],
        $case['source_file'],
        $case['checkpoint_mode'],
        $case['page_size']
    )] = static function (TestRunner $t) use ($case): void {
        $t->same(true, $case['case'] >= 1 && $case['case'] <= 1000);
        $t->same(true, in_array($case['source_file'], ['walprotocol.test', 'walprotocol2.test'], true));
        $t->same(true, str_starts_with($case['upstream'], 'walprotocol.test') || str_starts_with($case['upstream'], 'walprotocol2.test'));
        $t->same(true, in_array($case['checkpoint_mode'], ['passive', 'full', 'restart', 'truncate'], true));
        $t->same(true, in_array($case['journal_mode'], ['wal', 'wal-persist'], true));
        $t->same(true, in_array($case['page_size'], [512, 1024, 2048, 4096, 8192], true));
        $t->same(true, in_array($case['busy_timeout_ms'], [0, 10, 250, 1000], true));
        $t->same($case['lock_count'], count(array_filter($case['lock_sequence'], static fn (array $lock): bool => ($lock['op'] ?? null) === 'lock')));
        $t->same($case['unlock_count'], count(array_filter($case['lock_sequence'], static fn (array $lock): bool => ($lock['op'] ?? null) === 'unlock')));
        $t->same(
            $case['busy_lock_count'],
            count(array_filter(
                array_column($case['lock_sequence'], 'result'),
                static fn (mixed $result): bool => in_array($result, ['SQLITE_BUSY', 'SQLITE_BUSY_SNAPSHOT'], true)
            ))
        );
        $t->same($case['row_count'], count($case['rows']));
        $t->same($case['concurrent_row_count'], is_array($case['concurrent_rows']) ? count($case['concurrent_rows']) : null);
        $t->same(true, in_array($case['expected_code'], [0, 1], true));
        $t->same(true, in_array($case['expected_extended_code'], ['SQLITE_OK', 'SQLITE_PROTOCOL', 'SQLITE_BUSY'], true));
        $t->same($case['protocol_error'], $case['expected_extended_code'] === 'SQLITE_PROTOCOL');
        $t->same($case['busy_retry_succeeds'], $case['busy_handler_invoked'] && $case['expected_code'] === 0);
        $t->same(true, $case['retry_limit'] === 0 || $case['retry_limit'] === 1 || $case['retry_limit'] === 100);
        $t->same([
            'sqlite-upstream-walprotocol-locking-protocol',
            'sqlite-upstream-walprotocol2-busy-snapshot-retry',
            'sqlite-real-upstream-pager-wal-dynamic',
        ], $case['dependencies']);

        if ($case['expected_code'] === 0) {
            $t->same(false, str_contains($case['expected_message'], 'locking protocol'));
            $t->same(false, str_contains($case['expected_message'], 'database is locked'));
        } else {
            $t->same(true, in_array($case['expected_message'], ['locking protocol', 'database is locked'], true));
            $t->same(true, $case['busy_lock_count'] > 0);
        }

        if ($case['retry_limit'] === 100) {
            $t->same(100, $case['busy_lock_count']);
            $t->same('locking protocol', $case['expected_message']);
        }
    };
}

$tests['real upstream pager wal protocol dynamic records hydrated upstream sections'] = static function (TestRunner $t): void {
    $cases = SQLiteRealUpstreamPagerWalDynamicPlan::walProtocolRetrySnapshotCases();
    $sources = array_values(array_unique(array_column($cases, 'source_file')));
    sort($sources);
    $upstream = array_values(array_unique(array_column($cases, 'upstream')));
    sort($upstream);

    $t->same(1000, count($cases));
    $t->same(['walprotocol.test', 'walprotocol2.test'], $sources);
    $t->same([
        'walprotocol.test 1.1',
        'walprotocol.test 1.3',
        'walprotocol.test 1.4',
        'walprotocol.test 1.5',
        'walprotocol.test 2.5 2.6',
        'walprotocol2.test 2.2 2.3',
        'walprotocol2.test 2.4 2.5',
    ], $upstream);
    $t->same('initial-reader-recovery-lock-sequence', $cases[0]['phase']);
    $t->same('busy-handler-retries-begin-exclusive-after-snapshot-race', $cases[6]['phase']);
    $t->same('walprotocol2.test', $cases[999]['source_file']);
    $t->same('begin-exclusive-races-with-concurrent-writer', $cases[999]['phase']);
};

return $tests;
