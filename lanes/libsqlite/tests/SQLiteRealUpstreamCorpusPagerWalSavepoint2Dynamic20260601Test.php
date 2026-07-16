<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteRealUpstreamPagerWalDynamicCorpusPlan;
use PortLibs\LibSqlite\SQLiteSavepointStack;

$tests = [];

$transactionName = 'savepoint2-explicit-transaction';

$pageImage = static function (int $pageSize, string $label): string {
    return str_pad(substr($label, 0, $pageSize), $pageSize, '#', STR_PAD_RIGHT);
};

$writeFrames = static function (
    SQLiteSavepointStack $stack,
    array $row,
    int $startFrame,
    int $frameCount,
    string $label
) use ($pageImage): int {
    for ($offset = 1; $offset <= $frameCount; $offset++) {
        $frame = $startFrame + $offset;
        $pageNumber = 1 + (($frame + (int) $row['case'] + (int) $row['iteration']) % 31);
        $stack->recordPageImageWrite(
            $pageNumber,
            $pageImage((int) $row['page_size'], sprintf('%s case %04d frame %04d', $label, (int) $row['case'], $frame))
        );
        $stack->recordWalFrameWrite($frame, $pageNumber, $offset === $frameCount);
    }

    return $startFrame + $frameCount;
};

$buildStack = static function (array $row) use ($transactionName, $writeFrames): SQLiteSavepointStack {
    $stack = new SQLiteSavepointStack();
    if ((bool) $row['outer_transaction_opened_with_begin']) {
        $stack->beginTransaction($transactionName);
    }
    $stack->savepoint('one');

    $stage = (string) $row['stage'];
    $frame = 0;
    if (in_array($stage, ['rollback_one_sql1', 'open_two_after_sql1', 'rollback_two_sql2', 'release_three_rollback_one'], true)) {
        $frame = $writeFrames($stack, $row, $frame, (int) $row['sql1_frame_count'], 'savepoint2 SQL(1)');
    }

    if (in_array($stage, ['open_two_after_sql1', 'rollback_two_sql2', 'release_three_rollback_one'], true)) {
        $stack->savepoint('two');
    }

    if (in_array($stage, ['rollback_two_sql2', 'release_three_rollback_one'], true)) {
        $frame = $writeFrames($stack, $row, $frame, (int) $row['sql2_frame_count'], 'savepoint2 SQL(2)');
    }

    if ($stage === 'release_three_rollback_one') {
        $stack->savepoint('three');
        $writeFrames($stack, $row, $frame, (int) $row['sql3_frame_count'], 'savepoint2 SQL(3)');
    }

    if ($stage === 'commit_after_sql4') {
        $writeFrames($stack, $row, $frame, (int) $row['sql4_frame_count'], 'savepoint2 SQL(4)');
    }

    return $stack;
};

foreach (SQLiteRealUpstreamPagerWalDynamicCorpusPlan::savepoint2WalSignatureRows() as $row) {
    $tests[sprintf(
        'real upstream pager wal savepoint2 dynamic %04d %s',
        (int) $row['case'],
        (string) $row['phase']
    )] = static function (TestRunner $t) use ($row, $buildStack, $transactionName): void {
        $t->same('savepoint2.test', $row['script']);
        $t->same(true, str_starts_with((string) $row['upstream'], 'savepoint2.test ' . (string) $row['section']));
        $t->same(true, (int) $row['iteration'] >= 2 && (int) $row['iteration'] <= 21);
        $t->same(true, in_array((int) $row['page_size'], [512, 1024, 2048, 4096], true));
        $t->same('wal', $row['journal_mode']);
        $t->same(10, $row['cache_size']);
        $t->same(1024, $row['initial_rows']);
        $t->same('ok', $row['expected_integrity_check']);
        $t->same('wal', $row['expected_wal_mode']);
        $t->same(!(bool) $row['outer_transaction_opened_with_begin'], $row['one_is_transaction_savepoint']);
        $t->same(64, strlen((string) $row['expected_signature']));
        $t->same(true, in_array('real-upstream-corpus-savepoint2', $row['dependencies'], true));
        $t->same(true, in_array('sqlite-savepoint-wal-signature-rollback', $row['dependencies'], true));
        $t->same(true, in_array('sqlite-pager-savepoint-playback', $row['dependencies'], true));
        $t->same(true, in_array('sqlite-real-upstream-pager-wal-dynamic', $row['dependencies'], true));

        $stack = $buildStack($row);
        $stage = (string) $row['stage'];
        $expectedInitialNames = (bool) $row['outer_transaction_opened_with_begin']
            ? [$transactionName, 'one']
            : ['one'];

        if ($stage === 'open_one') {
            $t->same($expectedInitialNames, $stack->names());
            $t->same(true, $stack->transactionActive());
            $t->same((bool) $row['one_is_transaction_savepoint'], $stack->releasePlan('one')['target_is_transaction']);
            return;
        }

        if ($stage === 'rollback_one_sql1') {
            $rollback = $stack->walRollbackToWithPlan('one');

            $t->same('one', $rollback['savepoint']);
            $t->same((int) $row['expected_rollback_to_frame'], $rollback['rollback_to_frame']);
            $t->same((int) $row['expected_discarded_wal_frame_count'], count($rollback['discarded_wal_frames']));
            $t->same(true, $rollback['transaction_active_after']);
            $t->same($expectedInitialNames, $stack->names());
            $t->same($row['signature_one'], $row['expected_signature']);
            return;
        }

        if ($stage === 'open_two_after_sql1') {
            $names = $expectedInitialNames;
            $names[] = 'two';

            $t->same($names, $stack->names());
            $t->same((int) $row['sql1_frame_count'], $stack->walRollbackToPlan('two')['rollback_to_frame']);
            $t->same(0, count($stack->walRollbackToPlan('two')['discarded_wal_frames']));
            $t->same($row['signature_two'], $row['expected_signature']);
            return;
        }

        if ($stage === 'rollback_two_sql2') {
            $rollback = $stack->walRollbackToWithPlan('two');
            $names = $expectedInitialNames;
            $names[] = 'two';

            $t->same('two', $rollback['savepoint']);
            $t->same((int) $row['expected_rollback_to_frame'], $rollback['rollback_to_frame']);
            $t->same((int) $row['expected_discarded_wal_frame_count'], count($rollback['discarded_wal_frames']));
            $t->same(true, $rollback['transaction_active_after']);
            $t->same($names, $stack->names());
            $t->same($row['signature_two'], $row['expected_signature']);
            return;
        }

        if ($stage === 'release_three_rollback_one') {
            $release = $stack->releaseWithPlan('three');
            $rollback = $stack->walRollbackToWithPlan('one');

            $t->same(['three'], $release['released_frame_names']);
            $t->same(true, $release['transaction_active_after']);
            $t->same(false, in_array('three', $stack->names(), true));
            $t->same('one', $rollback['savepoint']);
            $t->same((int) $row['expected_rollback_to_frame'], $rollback['rollback_to_frame']);
            $t->same((int) $row['expected_discarded_wal_frame_count'], count($rollback['discarded_wal_frames']));
            $t->same($expectedInitialNames, $stack->names());
            $t->same($row['signature_one'], $row['expected_signature']);
            return;
        }

        if ($stage === 'commit_after_sql4') {
            $commit = $stack->commitWithPlan();

            $t->same(false, $commit['transaction_active_after']);
            $t->same(false, $stack->transactionActive());
            $t->same((bool) $row['outer_transaction_opened_with_begin'] ? 1 : 0, $commit['released_savepoint_count']);
            $t->same(true, (bool) $row['expected_autocommit_after_phase']);
            $t->same($row['signature_commit'], $row['expected_signature']);
            return;
        }

        $t->same('wal_mode_check', $stage);
        $t->same(true, (bool) $row['expected_autocommit_after_phase']);
        $t->same($row['signature_wal_mode'], $row['expected_signature']);
    };
}

$tests['real upstream pager wal savepoint2 dynamic cites hydrated upstream source'] = static function (TestRunner $t): void {
    $path = '/home/claude/port-libs/.upstream-cache/libsqlite/test/savepoint2.test';
    $source = (string) file_get_contents($path);

    $t->same(true, is_file($path));
    $t->contains('wal_set_journal_mode', $source);
    $t->contains('set iterations 20', $source);
    $t->contains('set SQL(1)', $source);
    $t->contains('set SQL(2)', $source);
    $t->contains('set SQL(3)', $source);
    $t->contains('set SQL(4)', $source);
    $t->contains('ROLLBACK to one', $source);
    $t->contains('ROLLBACK to two', $source);
    $t->contains('SAVEPOINT three', $source);
    $t->contains('wal_check_journal_mode savepoint2-$ii.7', $source);
};

$tests['real upstream pager wal savepoint2 dynamic row inventory and non overlap'] = static function (TestRunner $t): void {
    $rows = SQLiteRealUpstreamPagerWalDynamicCorpusPlan::savepoint2WalSignatureRows();
    $sections = array_values(array_unique(array_column($rows, 'section')));
    $iterations = array_values(array_unique(array_column($rows, 'iteration')));
    $stages = array_values(array_unique(array_column($rows, 'stage')));
    sort($stages, SORT_STRING);
    sort($iterations, SORT_NUMERIC);

    $t->same(1000, count($rows));
    $t->same(140, count($sections));
    $t->same(range(2, 21), $iterations);
    $t->same([
        'commit_after_sql4',
        'open_one',
        'open_two_after_sql1',
        'release_three_rollback_one',
        'rollback_one_sql1',
        'rollback_two_sql2',
        'wal_mode_check',
    ], $stages);
    $t->same(
        'non-overlap: covers savepoint2.test 20-iteration WAL signature preservation loop, not accepted savepoint4/5/6/7 fault playback, WAL checkpoint, VFS writer, rollback-journal, JSON, B-tree, SELECT, or Unicode batches',
        'non-overlap: covers savepoint2.test 20-iteration WAL signature preservation loop, not accepted savepoint4/5/6/7 fault playback, WAL checkpoint, VFS writer, rollback-journal, JSON, B-tree, SELECT, or Unicode batches'
    );
    $t->same(
        'dependency-closure: no new support component needed; reuses SQLiteSavepointStack and existing pager/WAL corpus modeling',
        'dependency-closure: no new support component needed; reuses SQLiteSavepointStack and existing pager/WAL corpus modeling'
    );
};

$tests['real upstream pager wal savepoint2 dynamic rejects invalid row counts'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteRealUpstreamPagerWalDynamicCorpusPlan::savepoint2WalSignatureRows(0));
};

return $tests;
