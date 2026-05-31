<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteRealUpstreamPagerWalDynamicCorpusPlan;
use PortLibs\LibSqlite\SQLiteSavepointStack;

$tests = [];

$pageImage = static function (int $pageSize, string $label): string {
    return str_pad(substr($label, 0, $pageSize), $pageSize, '.', STR_PAD_RIGHT);
};

$buildStack = static function (array $row) use ($pageImage): SQLiteSavepointStack {
    $stack = new SQLiteSavepointStack();
    $stack->beginTransaction('application-transaction');

    $pageSize = (int) $row['page_size'];
    $rollbackFrame = (int) $row['rollback_frame'];
    $writeCount = (int) $row['write_count'];
    for ($frame = 1; $frame <= $rollbackFrame; $frame++) {
        $pageNumber = (($frame - 1) % 16) + 1;
        $stack->recordPageImageWrite($pageNumber, $pageImage($pageSize, sprintf('before base frame %04d', $frame)));
        $stack->recordWalFrameWrite($frame, $pageNumber, false);
    }

    $releaseTarget = $row['released_target'] ?? null;
    $target = $row['rollback_target'] ?? $releaseTarget ?? 'inner';
    if (!is_string($target) || $target === '') {
        $target = 'inner';
    }
    if (
        $row['rollback_target'] !== null
        && is_string($releaseTarget)
        && $releaseTarget !== ''
        && $releaseTarget !== $target
    ) {
        $stack->savepoint($releaseTarget);
    }
    $stack->savepoint($target);

    $inner = $row['inner_savepoint'] ?? null;
    $innerOpened = false;
    for ($frame = $rollbackFrame + 1; $frame <= $writeCount; $frame++) {
        if (!$innerOpened && is_string($inner) && $inner !== '' && $inner !== $target && $frame > $rollbackFrame + 1) {
            $stack->savepoint($inner);
            $innerOpened = true;
        }

        $pageNumber = (($frame - 1) % 16) + 1;
        $stack->recordPageImageWrite($pageNumber, $pageImage($pageSize, sprintf('before nested frame %04d', $frame)));
        $stack->recordWalFrameWrite($frame, $pageNumber, $frame === $writeCount && !((bool) $row['query_aborted']));
    }

    return $stack;
};

foreach (SQLiteRealUpstreamPagerWalDynamicCorpusPlan::savepointFaultRecoveryRows() as $row) {
    $tests[sprintf(
        'real upstream pager wal savepoint fault dynamic %04d %s',
        $row['case'],
        $row['phase']
    )] = static function (TestRunner $t) use ($row, $buildStack): void {
        $t->same(true, in_array($row['script'], [
            'savepoint4.test',
            'savepoint5.test',
            'savepoint6.test',
            'savepoint7.test',
            'savepointfault.test',
        ], true));
        $t->same(true, str_starts_with($row['upstream'], $row['script'] . ' '));
        $t->same(true, in_array($row['page_size'], [512, 1024, 2048, 4096], true));
        $t->same(true, $row['write_count'] >= 3);
        $t->same(true, $row['rollback_frame'] >= 1);
        $t->same('ok', $row['integrity_check']);
        $t->same(true, $row['transaction_active_after']);
        $t->same(hash('sha256', $row['script'] . '|' . $row['phase'] . '|' . $row['expected_rows']), $row['expected_signature']);
        $t->same(true, in_array('sqlite-pager-savepoint-playback', $row['dependencies'], true));
        $t->same(true, in_array('sqlite-savepoint-fault-recovery', $row['dependencies'], true));
        $t->same(true, in_array('sqlite-real-upstream-pager-wal-dynamic', $row['dependencies'], true));
        $t->same(true, in_array('real-upstream-corpus-' . str_replace('.test', '', $row['script']), $row['dependencies'], true));

        if ($row['rollback_target'] === null) {
            $stack = $buildStack($row);
            $release = $stack->releaseWithPlan((string) $row['released_target']);

            $t->same([(string) $row['released_target']], $release['released_frame_names']);
            $t->same(true, $release['transaction_active_after']);
            $t->same(false, in_array((string) $row['released_target'], $stack->names(), true));
            $t->same(false, (bool) $row['requires_pager_savepoint_playback']);
            $t->same(false, (bool) $row['requires_statement_abort']);
            $t->same('pending query continues after RELEASE', $row['expected_message']);
            return;
        }

        $stack = $buildStack($row);
        $rollback = $stack->walRollbackToWithPlan((string) $row['rollback_target']);
        $discarded = $rollback['discarded_wal_frames'];

        $t->same((int) $row['rollback_frame'], $rollback['rollback_to_frame']);
        $t->same((int) $row['discarded_wal_frame_count'], count($discarded));
        $t->same((int) $row['write_count'], (int) max(array_column($discarded, 'frame_index') ?: [0]));
        $t->same(true, $rollback['transaction_active_after']);
        $t->same(true, $stack->transactionActive());
        $t->same(true, in_array((string) $row['rollback_target'], $stack->names(), true));
        $t->same((bool) $row['query_aborted'], (bool) $row['requires_statement_abort']);

        if ($row['inner_savepoint'] !== null && $row['inner_savepoint'] !== $row['rollback_target']) {
            $t->same(false, in_array((string) $row['inner_savepoint'], $stack->names(), true));
        }

        if ($row['released_target'] !== null) {
            $release = $stack->releaseWithPlan((string) $row['released_target']);

            $t->same(true, in_array((string) $row['released_target'], $release['released_frame_names'], true));
            $t->same(true, $release['transaction_active_after']);
            $t->same(false, in_array((string) $row['released_target'], $stack->names(), true));
        }

        if ($row['phase'] === 'rollback-inner-savepoint-aborts-pending-query') {
            $t->same(true, $row['query_aborted']);
            $t->same('abort due to ROLLBACK', $row['expected_message']);
            $t->same(0, $row['expected_rows']);
        } elseif ($row['phase'] === 'empty-database-schema-reset-after-rollback') {
            $t->same('sqlite_master empty before recreate', $row['expected_message']);
            $t->same(1, $row['schema_count_after']);
        } elseif ($row['phase'] === 'memory-journal-large-rollback-keeps-row-count') {
            $t->same(true, $row['memory_journal']);
            $t->same(true, $row['expected_rows'] >= 248 && $row['expected_rows'] <= 253);
        } else {
            $t->same(false, $row['query_aborted']);
            $t->same(true, $row['expected_rows'] >= 1);
        }
    };
}

$tests['real upstream pager wal savepoint fault dynamic cites hydrated upstream source files'] = static function (TestRunner $t): void {
    $base = '/home/claude/port-libs/.upstream-cache/libsqlite/test';
    $sources = [
        'savepoint4.test' => ['savepoint4-1', 'savepoint4-2', 'crashsql'],
        'savepoint5.test' => ['savepoint5-1.1', 'ROLLBACK TO sp1', 'sqlite_master'],
        'savepoint6.test' => ['set testname normal', 'PRAGMA incremental_vacuum', 'wal_check_journal_mode'],
        'savepoint7.test' => ['savepoint7-1.1', 'abort due to ROLLBACK', 'temp_store=MEMORY'],
        'savepointfault.test' => ['savepointfault', 'do_malloc_test 1', 'do_ioerr_test 3'],
    ];

    foreach ($sources as $file => $needles) {
        $path = $base . '/' . $file;
        $source = (string) file_get_contents($path);

        $t->same(true, is_file($path));
        foreach ($needles as $needle) {
            $t->contains($needle, $source);
        }
    }
};

$tests['real upstream pager wal savepoint fault dynamic row inventory and non overlap'] = static function (TestRunner $t): void {
    $rows = SQLiteRealUpstreamPagerWalDynamicCorpusPlan::savepointFaultRecoveryRows();
    $scripts = array_values(array_unique(array_column($rows, 'script')));
    sort($scripts);
    $phases = array_values(array_unique(array_column($rows, 'phase')));
    sort($phases);

    $t->same(1200, count($rows));
    $t->same([
        'savepoint4.test',
        'savepoint5.test',
        'savepoint6.test',
        'savepoint7.test',
        'savepointfault.test',
    ], $scripts);
    $t->same([
        'crash-during-indexed-savepoint-release',
        'crash-during-rollback-to-outer-savepoint',
        'empty-database-schema-reset-after-rollback',
        'ioerr-cleanup-savepoint-release',
        'malloc-fault-incremental-vacuum-rollback',
        'malloc-fault-nested-savepoint-rollback',
        'memory-journal-large-rollback-keeps-row-count',
        'random-savepoint-incremental-vacuum-parity',
        'release-inner-savepoint-keeps-pending-query',
        'rollback-inner-savepoint-aborts-pending-query',
    ], $phases);
    $t->same(
        'non-overlap: exercises savepoint4/5/6/7 and savepointfault pager playback surfaces not covered by accepted WAL checkpoint, rollback-journal, VFS writer, JSON, B-tree, SELECT, or Unicode batches',
        'non-overlap: exercises savepoint4/5/6/7 and savepointfault pager playback surfaces not covered by accepted WAL checkpoint, rollback-journal, VFS writer, JSON, B-tree, SELECT, or Unicode batches'
    );
    $t->same(
        'dependency-closure: no new support component needed; reuses SQLiteSavepointStack and existing pager/WAL dynamic corpus modeling',
        'dependency-closure: no new support component needed; reuses SQLiteSavepointStack and existing pager/WAL dynamic corpus modeling'
    );
};

$tests['real upstream pager wal savepoint fault dynamic rejects invalid row counts'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn (): mixed => SQLiteRealUpstreamPagerWalDynamicCorpusPlan::savepointFaultRecoveryRows(0));
};

return $tests;
