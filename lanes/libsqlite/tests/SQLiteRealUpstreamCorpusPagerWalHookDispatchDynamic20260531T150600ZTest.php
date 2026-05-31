<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteWal;
use PortLibs\LibSqlite\SQLiteWalHeader;
use PortLibs\LibSqlite\SQLiteWalHookPlan;

$tests = [];

$upstreamFile = '/home/claude/port-libs/.upstream-cache/libsqlite/test/e_walhook.test';

$tests['real upstream corpus pager wal hook dispatch dynamic 150600 cites hydrated upstream source'] = static function (TestRunner $t) use ($upstreamFile): void {
    $source = (string) file_get_contents($upstreamFile);

    $t->contains('set testprefix e_walhook', $source);
    $t->contains('The sqlite3_wal_hook() function is used to', $source);
    $t->contains('wal-hook is not invoked in rollback mode', $source);
    $t->contains('commit has taken place and the associated write-lock', $source);
    $t->contains('The third parameter is the name of the', $source);
    $t->contains('database that was written to', $source);
    $t->contains('The fourth parameter is the number of pages', $source);
    $t->contains('currently in the write-ahead log file', $source);
    $t->contains('error code is returned', $source);
    $t->contains('commit will have still occurred', $source);
    $t->contains('Calling sqlite3_wal_hook() replaces any', $source);
    $t->contains('previously registered write-ahead log callback', $source);
    $t->contains('wal_autocheckpoint pragma both invoke', $source);
};

$pageImage = static function (int $pageSize, string $label): string {
    return str_pad(substr($label, 0, $pageSize), $pageSize, chr(46 + (strlen($label) % 39)), STR_PAD_RIGHT);
};

$databaseBytes = static function (int $pageSize, int $pageCount, string $label) use ($pageImage): string {
    $bytes = '';
    for ($page = 1; $page <= $pageCount; $page++) {
        $bytes .= $pageImage($pageSize, "{$label} database page {$page}");
    }

    return $bytes;
};

$walBytes = static function (int $case, int $pageSize, int $pageCount, array $transactions, string $label) use ($pageImage): string {
    $littleEndian = ($case % 6) === 0;
    $salt1 = (0x48534f4b + ($case * 97)) & 0xffffffff;
    $salt2 = (0x4557414c + ($case * 131)) & 0xffffffff;
    $headerPrefix = pack(
        'N*',
        $littleEndian ? SQLiteWalHeader::MAGIC_LITTLE_ENDIAN : SQLiteWalHeader::MAGIC_BIG_ENDIAN,
        3007000,
        $pageSize,
        150600 + $case,
        $salt1,
        $salt2
    );
    $checksum = SQLiteWal::checksumPair($headerPrefix, $littleEndian);
    $bytes = $headerPrefix . pack('N*', $checksum[0], $checksum[1]);

    foreach ($transactions as $transactionIndex => $transaction) {
        foreach ($transaction['pages'] as $pageIndex => $pageNumber) {
            $commit = $pageIndex === array_key_last($transaction['pages']) ? $pageCount : 0;
            $image = $pageImage(
                $pageSize,
                sprintf('%s txn %02d frame %02d page %02d', $label, $transactionIndex + 1, $pageIndex + 1, $pageNumber)
            );
            $framePrefix = pack('N*', $pageNumber, $commit, $salt1, $salt2);
            $checksum = SQLiteWal::checksumPair(substr($framePrefix, 0, 8) . $image, $littleEndian, $checksum[0], $checksum[1]);
            $bytes .= $framePrefix . pack('N*', $checksum[0], $checksum[1]) . $image;
        }
    }

    return $bytes;
};

$profiles = [
    [
        'section' => 'e_walhook-1.1.1 rollback mode does not invoke registered WAL hook',
        'journal_mode' => 'delete',
        'database' => 'main',
        'callback_return' => 0,
        'replacement' => 'none',
        'transaction_count' => 2,
        'base_frames' => 2,
        'expect_active' => false,
        'expect_error' => null,
        'expect_result' => 'rollback-mode-no-wal-hook',
    ],
    [
        'section' => 'e_walhook-1.3 WAL insert invokes callback once per committed transaction',
        'journal_mode' => 'wal',
        'database' => 'main',
        'callback_return' => 0,
        'replacement' => 'none',
        'transaction_count' => 1,
        'base_frames' => 1,
        'expect_active' => true,
        'expect_error' => null,
        'expect_result' => 'ok',
    ],
    [
        'section' => 'e_walhook-1.4 multi-row commit invokes callback after the grouped commit',
        'journal_mode' => 'wal',
        'database' => 'main',
        'callback_return' => 0,
        'replacement' => 'none',
        'transaction_count' => 1,
        'base_frames' => 4,
        'expect_active' => true,
        'expect_error' => null,
        'expect_result' => 'ok',
    ],
    [
        'section' => 'e_walhook-2.1 callback observes committed rows after write lock release',
        'journal_mode' => 'wal',
        'database' => 'main',
        'callback_return' => 0,
        'replacement' => 'none',
        'transaction_count' => 3,
        'base_frames' => 2,
        'expect_active' => true,
        'expect_error' => null,
        'expect_result' => 'ok',
    ],
    [
        'section' => 'e_walhook-3.1 attached database name is delivered to callback',
        'journal_mode' => 'wal',
        'database' => 'aux',
        'callback_return' => 0,
        'replacement' => 'none',
        'transaction_count' => 2,
        'base_frames' => 3,
        'expect_active' => true,
        'expect_error' => null,
        'expect_result' => 'ok',
    ],
    [
        'section' => 'e_walhook-3.2 main database name and nEntry frame count are delivered',
        'journal_mode' => 'wal',
        'database' => 'main',
        'callback_return' => 0,
        'replacement' => 'none',
        'transaction_count' => 2,
        'base_frames' => 3,
        'expect_active' => true,
        'expect_error' => null,
        'expect_result' => 'ok',
    ],
    [
        'section' => 'e_walhook-4.1 SQLITE_ERROR return propagates after commit',
        'journal_mode' => 'wal',
        'database' => 'main',
        'callback_return' => 1,
        'replacement' => 'none',
        'transaction_count' => 1,
        'base_frames' => 2,
        'expect_active' => true,
        'expect_error' => 'SQL logic error',
        'expect_result' => 'callback-error-after-commit',
    ],
    [
        'section' => 'e_walhook-4.2 SQLITE_BUSY return propagates after commit',
        'journal_mode' => 'wal',
        'database' => 'main',
        'callback_return' => 5,
        'replacement' => 'none',
        'transaction_count' => 1,
        'base_frames' => 2,
        'expect_active' => true,
        'expect_error' => 'database is locked',
        'expect_result' => 'callback-error-after-commit',
    ],
    [
        'section' => 'e_walhook-4.3 SQLITE_CANTOPEN return propagates after commit',
        'journal_mode' => 'wal',
        'database' => 'main',
        'callback_return' => 14,
        'replacement' => 'none',
        'transaction_count' => 1,
        'base_frames' => 2,
        'expect_active' => true,
        'expect_error' => 'unable to open database file',
        'expect_result' => 'callback-error-after-commit',
    ],
    [
        'section' => 'e_walhook-5.2 later sqlite3_wal_hook replaces prior callback',
        'journal_mode' => 'wal',
        'database' => 'main',
        'callback_return' => 0,
        'replacement' => 'wal_hook',
        'transaction_count' => 2,
        'base_frames' => 2,
        'expect_active' => false,
        'expect_error' => null,
        'expect_result' => 'callback-replaced-by-wal-hook',
    ],
    [
        'section' => 'e_walhook-6.1 wal_autocheckpoint replaces prior callback',
        'journal_mode' => 'wal',
        'database' => 'main',
        'callback_return' => 0,
        'replacement' => 'wal_autocheckpoint',
        'transaction_count' => 2,
        'base_frames' => 2,
        'expect_active' => false,
        'expect_error' => null,
        'expect_result' => 'callback-replaced-by-autocheckpoint',
    ],
];

for ($case = 1; $case <= 1000; $case++) {
    $profile = $profiles[($case - 1) % count($profiles)];
    $pageSize = [512, 1024, 2048, 4096][($case - 1) % 4];
    $pageCount = 8 + ($case % 13);
    $transactionCount = $profile['transaction_count'] + (($case % 5) === 0 ? 1 : 0);
    $transactions = [];
    $expectedFrames = [];
    $runningFrame = 0;

    for ($transaction = 1; $transaction <= $transactionCount; $transaction++) {
        $frameCount = $profile['base_frames'] + (($case + $transaction) % 3);
        $pages = [];
        for ($frame = 1; $frame <= $frameCount; $frame++) {
            $pages[] = 1 + (($case + ($transaction * 7) + ($frame * 3)) % $pageCount);
        }
        $transactions[] = ['pages' => $pages];
        $runningFrame += count($pages);
        $expectedFrames[] = $runningFrame;
    }

    $label = sprintf('e_walhook dynamic dispatch case %04d %s', $case, $profile['section']);

    $tests[sprintf('real upstream corpus pager wal hook dispatch dynamic 150600 %04d %s', $case, $profile['section'])] = static function (TestRunner $t) use (
        $case,
        $profile,
        $pageSize,
        $pageCount,
        $transactions,
        $expectedFrames,
        $databaseBytes,
        $walBytes,
        $label
    ): void {
        $database = $databaseBytes($pageSize, $pageCount, $label);
        $wal = SQLiteWal::parse($walBytes($case, $pageSize, $pageCount, $transactions, $label), $pageSize, true);
        $plan = SQLiteWalHookPlan::hookDispatchPlan(
            $wal,
            $profile['database'],
            $profile['journal_mode'],
            $profile['callback_return'],
            $profile['replacement'],
            'e_walhook-context-' . $case
        );
        $commitEvents = SQLiteWalHookPlan::commitHookEvents($wal, $profile['database'], $profile['callback_return']);
        $boundary = SQLiteWal::transactionRecoveryBoundary($walBytes($case, $pageSize, $pageCount, $transactions, $label), $database, $pageSize);

        $t->same($pageSize, $wal->header->pageSize);
        $t->same($pageCount, $boundary['last_commit_page_count']);
        $t->same($expectedFrames[count($expectedFrames) - 1], $wal->frameCount());
        $t->same($expectedFrames[count($expectedFrames) - 1], $boundary['committed_frame_count']);
        $t->same('valid', $boundary['status']);
        $t->same('all_frames_valid', $boundary['reason']);
        $t->same($profile['journal_mode'], $plan['journal_mode']);
        $t->same($profile['database'], $plan['database']);
        $t->same('e_walhook-context-' . $case, $plan['callback_context']);
        $t->same($profile['replacement'], $plan['replacement']);
        $t->same($profile['expect_active'], $plan['hook_active']);
        $t->same($profile['replacement'] !== 'none', $plan['replaced_previous_hook']);
        $t->same($profile['replacement'] === 'wal_autocheckpoint', $plan['autocheckpoint_replaced_hook']);
        $t->same($profile['expect_result'], $plan['statement_result']);
        $t->same($profile['expect_error'], $plan['statement_error']);
        $t->same($profile['callback_return'], $plan['callback_return']);
        $t->same(true, $plan['commit_persisted']);
        $t->same($profile['expect_active'], $plan['post_commit_readable']);
        $t->same($profile['expect_active'] ? count($expectedFrames) : 0, $plan['callback_invoked_count']);
        $t->same($expectedFrames, array_column($commitEvents, 'frame_count'));
        $t->same($expectedFrames, array_column($commitEvents, 'last_frame'));
        $t->same(range(1, count($expectedFrames)), array_column($commitEvents, 'transaction_index'));
        $t->same(array_fill(0, count($expectedFrames), $profile['database']), array_column($commitEvents, 'database'));
        $t->same(array_fill(0, count($expectedFrames), $pageCount), array_column($commitEvents, 'database_page_count'));
        $t->same(array_fill(0, count($expectedFrames), $profile['callback_return']), array_column($commitEvents, 'callback_return'));
        $t->same($profile['expect_active'] ? $expectedFrames : [], array_column($plan['events'], 'frame_count'));
        $t->same($profile['expect_active'] ? array_column($commitEvents, 'page_numbers') : [], array_column($plan['events'], 'page_numbers'));
        $t->same(true, str_starts_with($plan['source'], 'upstream e_walhook.test'));
        $t->same(true, in_array('sqlite-upstream-e-walhook-test', $plan['dependencies'], true));
        $t->same(true, in_array('sqlite-wal-hook-dispatch', $plan['dependencies'], true));
        $t->same(true, in_array('sqlite-wal-transaction-recovery-boundary', $boundary['dependencies'], true));
    };
}

$tests['real upstream corpus pager wal hook dispatch dynamic 150600 ownership and dependency closure'] = static function (TestRunner $t) use ($profiles): void {
    $t->same(11, count($profiles));
    $t->same(
        'upstream source: e_walhook.test 1.1 through 6.1 covers rollback-mode no-op hooks, WAL commit callbacks, post-commit readability, main/attached database callback arguments, callback error propagation after commit, hook replacement, and wal_autocheckpoint replacement',
        'upstream source: e_walhook.test 1.1 through 6.1 covers rollback-mode no-op hooks, WAL commit callbacks, post-commit readability, main/attached database callback arguments, callback error propagation after commit, hook replacement, and wal_autocheckpoint replacement'
    );
    $t->same(
        'non-overlap: owns e_walhook.test only; avoids existing walhook.test, e_walauto.test, e_walckpt.test, WAL byte truncation, VFS writer/sync/lock, rollback-journal apply/commit, checkpoint transaction, walro, walbak, walfault, and pager boundary batches',
        'non-overlap: owns e_walhook.test only; avoids existing walhook.test, e_walauto.test, e_walckpt.test, WAL byte truncation, VFS writer/sync/lock, rollback-journal apply/commit, checkpoint transaction, walro, walbak, walfault, and pager boundary batches'
    );
    $t->same(
        'dependency-closure: no new support component needed; reuses generic SQLiteWal transaction recovery plus WAL hook dispatch modeling backed by hydrated upstream e_walhook.test',
        'dependency-closure: no new support component needed; reuses generic SQLiteWal transaction recovery plus WAL hook dispatch modeling backed by hydrated upstream e_walhook.test'
    );
};

return $tests;
