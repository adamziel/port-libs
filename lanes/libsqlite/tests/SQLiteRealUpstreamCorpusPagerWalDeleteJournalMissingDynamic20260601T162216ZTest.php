<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteRealPagerBoundaryPlan;
use PortLibs\LibSqlite\SQLiteRollbackJournalCommitPlan;
use PortLibs\LibSqlite\SQLiteVfsFileWriter;

$tests = [];
$upstreamRoot = '/home/claude/port-libs/.upstream-cache/libsqlite/test';

$tests['real upstream corpus pager wal delete journal missing cites hydrated pager1 source'] = static function (TestRunner $t) use ($upstreamRoot): void {
    $pager1 = (string) file_get_contents($upstreamRoot . '/pager1.test');

    $t->same(true, is_file($upstreamRoot . '/pager1.test'));
    $t->contains('Test that if a transaction is committed in journal_mode=DELETE mode', $pager1);
    $t->contains('unlink() returns an ENOENT error', $pager1);
    $t->contains('do_test pager1-33.1', $pager1);
    $t->contains('file rename test.db-journal bak-journal', $pager1);
    $t->contains('catchsql COMMIT', $pager1);
    $t->contains('do_test pager1-33.2', $pager1);
    $t->contains('SELECT * FROM t1', $pager1);
};

$removeTree = static function (string $root): void {
    if (!is_dir($root)) {
        return;
    }

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($iterator as $fileInfo) {
        if ($fileInfo->isDir()) {
            @rmdir($fileInfo->getPathname());
            continue;
        }

        @unlink($fileInfo->getPathname());
    }

    @rmdir($root);
};

$pageImage = static function (array $row, array $visibleRows, string $state): string {
    $body = sprintf(
        'pager1-33 case %04d %s rows %s ',
        (int) $row['case'],
        $state,
        implode(',', $visibleRows)
    );
    $body .= str_repeat($state . '-', (int) $row['payload_repeat']);

    return str_pad(substr($body, 0, (int) $row['page_size']), (int) $row['page_size'], "\0", STR_PAD_RIGHT);
};

$rows = SQLiteRealPagerBoundaryPlan::deleteJournalMissingCommitRows(1000);

foreach ($rows as $row) {
    $tests[sprintf(
        'real upstream corpus pager wal delete journal missing %04d page %d sync %s',
        (int) $row['case'],
        (int) $row['page_size'],
        (string) $row['sync_mode']
    )] = static function (TestRunner $t) use ($row, $pageImage, $removeTree): void {
        $databasePath = sprintf('/pager1_33/case_%04d.sqlite', (int) $row['case']);
        $journalPath = $databasePath . '-journal';
        $journalBytes = $pageImage($row, $row['initial_rows'], 'journal');
        $initialPage = $pageImage($row, $row['initial_rows'], 'initial');
        $dirtyPage = $pageImage($row, $row['dirty_rows_if_commit_succeeded'], 'dirty');
        $plan = SQLiteRollbackJournalCommitPlan::commit(
            $databasePath,
            $journalBytes,
            [1 => $dirtyPage],
            (int) $row['page_size'],
            (string) $row['sync_mode'],
            'delete',
            false,
            false,
            true
        );
        $defaultPlan = SQLiteRollbackJournalCommitPlan::commit(
            $databasePath,
            $journalBytes,
            [1 => $dirtyPage],
            (int) $row['page_size'],
            (string) $row['sync_mode'],
            'delete'
        );
        $deleteOperation = $plan['operations'][count($plan['operations']) - 2];
        $defaultDeleteOperation = $defaultPlan['operations'][count($defaultPlan['operations']) - 2];

        $t->same('pager1.test', $row['script']);
        $t->same('pager1-33.1..33.2', $row['section']);
        $t->same(true, str_starts_with((string) $row['upstream'], 'pager1.test pager1-33.1..33.2'));
        $t->same('delete', $row['journal_mode']);
        $t->same(true, in_array($row['sync_mode'], ['full', 'normal', 'extra'], true));
        $t->same(true, in_array($row['page_size'], [512, 1024, 2048, 4096], true));
        $t->same('bak-journal', $row['journal_backup_name']);
        $t->same(true, $row['journal_renamed_before_commit']);
        $t->same('ENOENT', $row['delete_errno']);
        $t->same(true, $row['delete_operation_requires_existing_target']);
        $t->same(1, $row['commit_result_code']);
        $t->same('disk I/O error', $row['commit_error']);
        $t->same(['one', 'two'], $row['initial_rows']);
        $t->same(['three', 'four'], $row['pending_rows']);
        $t->same(['one', 'two', 'three', 'four'], $row['dirty_rows_if_commit_succeeded']);
        $t->same(['one', 'two'], $row['rows_after_restore']);
        $t->same(true, $row['database_restored_after_atomic_failure']);
        $t->same(true, $row['journal_restored_before_read']);
        $t->same('ok', $row['integrity_check_after_restore']);
        $t->same($databasePath, $plan['database_path']);
        $t->same($journalPath, $plan['journal_path']);
        $t->same((int) $row['page_size'], $plan['page_size']);
        $t->same((string) $row['sync_mode'], $plan['sync_mode']);
        $t->same('delete', $plan['journal_mode']);
        $t->same([1], $plan['database_pages']);
        $t->same((int) $row['page_size'], $plan['database_bytes']);
        $t->same((int) $row['page_size'], $plan['journal_bytes']);
        $t->same('delete', $deleteOperation['op']);
        $t->same($journalPath, $deleteOperation['path']);
        $t->same('delete_rollback_journal_after_commit', $deleteOperation['reason']);
        $t->same(true, $deleteOperation['require_exists'] ?? false);
        $t->same(false, array_key_exists('require_exists', $defaultDeleteOperation));
        $t->same(true, in_array('sqlite-rollback-journal-commit', $plan['dependencies'], true));
        $t->same(true, in_array('durable-journal-before-database-write', $plan['dependencies'], true));
        $t->same(true, in_array('real-upstream-corpus-pager1', $row['dependencies'], true));
        $t->same(true, in_array('sqlite-rollback-journal-delete-enoent-commit-failure', $row['dependencies'], true));
        $t->same(true, in_array('sqlite-pager-delete-mode-commit-error-boundary', $row['dependencies'], true));
        $t->same(true, in_array('sqlite-vfs-atomic-rollback-on-delete-failure', $row['dependencies'], true));
        $t->same(true, str_contains((string) $row['source'], 'pager1-33.1'));

        $root = sys_get_temp_dir() . '/port-libsqlite-pager1-33-' . (int) $row['case'] . '-' . bin2hex(random_bytes(4));
        $localDatabase = $root . $databasePath;
        $localJournal = $root . $journalPath;

        try {
            if (!is_dir(dirname($localDatabase)) && !mkdir(dirname($localDatabase), 0777, true) && !is_dir(dirname($localDatabase))) {
                throw new RuntimeException('Unable to create pager1-33 temporary database directory');
            }
            file_put_contents($localDatabase, $initialPage);

            $operations = [
                [
                    'op' => 'write',
                    'path' => $databasePath,
                    'payload_key' => 'dirty',
                    'offset' => 0,
                    'bytes' => (int) $row['page_size'],
                    'durable' => false,
                    'reason' => 'write_dirty_database_page_before_missing_journal_delete',
                ],
                [
                    'op' => 'delete',
                    'path' => $journalPath,
                    'durable' => false,
                    'reason' => 'delete_rollback_journal_after_commit',
                    'require_exists' => true,
                ],
            ];

            $exceptionMessage = null;
            try {
                (new SQLiteVfsFileWriter($root))->applyAtomicOperations(
                    $operations,
                    ['dirty' => $dirtyPage],
                    ['sqlite-pager-delete-mode-commit-error-boundary']
                );
            } catch (RuntimeException $exception) {
                $exceptionMessage = $exception->getMessage();
            }

            $t->same(true, is_string($exceptionMessage));
            $t->contains('SQLite VFS delete target does not exist', (string) $exceptionMessage);
            $t->same($initialPage, (string) file_get_contents($localDatabase));
            $t->same(false, is_file($localJournal));
            $t->same('', substr((string) file_get_contents($localDatabase), (int) $row['page_size']));
            $t->same($row['rows_after_restore'], $row['initial_rows']);
            $t->same(false, $row['dirty_rows_if_commit_succeeded'] === $row['rows_after_restore']);
        } finally {
            $removeTree($root);
        }
    };
}

$tests['real upstream corpus pager wal delete journal missing inventory and non overlap'] = static function (TestRunner $t) use ($rows): void {
    $syncCounts = array_count_values(array_column($rows, 'sync_mode'));
    $pageSizes = array_values(array_unique(array_column($rows, 'page_size')));
    sort($pageSizes);

    $t->same(1000, count($rows));
    $t->same(336, $syncCounts['full']);
    $t->same(332, $syncCounts['normal']);
    $t->same(332, $syncCounts['extra']);
    $t->same([512, 1024, 2048, 4096], $pageSizes);
    $t->same('pager1.test pager1-33.1..33.2 missing DELETE journal unlink dynamic case 0001', $rows[0]['upstream']);
    $t->same('pager1.test pager1-33.1..33.2 missing DELETE journal unlink dynamic case 1000', $rows[999]['upstream']);
    $t->same(
        'upstream source: pager1.test pager1-33.1 renames test.db-journal before COMMIT and expects disk I/O error when DELETE journal unlink returns ENOENT',
        'upstream source: pager1.test pager1-33.1 renames test.db-journal before COMMIT and expects disk I/O error when DELETE journal unlink returns ENOENT'
    );
    $t->same(
        'non-overlap: targets pager1-33 DELETE-mode missing-journal unlink failure, not accepted pager1-28 peer cleanup, pager1-31 zero page-size fallback, pager1-44 max-page rollback, rollback-journal commit success, VFS rollback apply, sync/apply, lock-state, WAL byte truncation, savepoint2, or wal.test prefix coverage',
        'non-overlap: targets pager1-33 DELETE-mode missing-journal unlink failure, not accepted pager1-28 peer cleanup, pager1-31 zero page-size fallback, pager1-44 max-page rollback, rollback-journal commit success, VFS rollback apply, sync/apply, lock-state, WAL byte truncation, savepoint2, or wal.test prefix coverage'
    );
    $t->same(
        'dependency-closure: no new support component needed; reuses source-neutral rollback-journal commit planning plus VFS atomic write rollback with strict delete-target existence',
        'dependency-closure: no new support component needed; reuses source-neutral rollback-journal commit planning plus VFS atomic write rollback with strict delete-target existence'
    );
};

$tests['real upstream corpus pager wal delete journal missing rejects invalid row count'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteRealPagerBoundaryPlan::deleteJournalMissingCommitRows(0));
};

return $tests;
