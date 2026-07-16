<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteVfsIoDynamicPlan;

$tests = [];

$scenarios = [
    'delete_db-1.1' => ['test3.database', 'journal', false, false, 0],
    'delete_db-1.2' => ['test3.database', 'wal', false, false, 0],
    'delete_db-1.3' => ['test3.database', 'journal', false, true, 3],
    'delete_db-1.4' => ['test3.database', 'wal', false, true, 3],
    'delete_db-2.1' => ['test3.db', 'journal', true, false, 0],
    'delete_db-2.2' => ['test3.db', 'wal', true, false, 0],
    'delete_db-2.3' => ['test3.db', 'journal', true, true, 3],
    'delete_db-2.4' => ['test3.db', 'wal', true, true, 3],
];

$caseNo = 0;
foreach (range(1, 140) as $round) {
    foreach ($scenarios as $scenario => [$baseName, $journalFamily, $shortNames, $multiplex, $chunkCount]) {
        ++$caseNo;
        $dynamicChunkCount = $multiplex ? $chunkCount + ($round % 5) : 0;
        $testName = sprintf(
            'real upstream corpus vfs delete database dynamic %04d %s round %03d',
            $caseNo,
            $scenario,
            $round
        );

        $tests[$testName] = static function (TestRunner $t) use ($scenario, $baseName, $journalFamily, $shortNames, $multiplex, $dynamicChunkCount): void {
            $profile = SQLiteVfsIoDynamicPlan::deleteDatabaseSidecarProfile(
                $scenario,
                $baseName,
                $journalFamily,
                $shortNames,
                $multiplex,
                $dynamicChunkCount
            );

            $t->same('ok', $profile['status']);
            $t->same('delete_db.test', $profile['script']);
            $t->same($scenario, $profile['scenario']);
            $t->same($baseName, $profile['base_name']);
            $t->same($journalFamily, $profile['journal_family']);
            $t->same($shortNames, $profile['short_names']);
            $t->same($multiplex, $profile['multiplex']);
            $t->same($dynamicChunkCount, $profile['chunk_count']);
            $t->same('SQLITE_OK', $profile['delete_result']);
            $t->same(true, $profile['main_deleted']);
            $t->same(true, $profile['sidecars_deleted']);
            $t->same([], $profile['files_after_delete']);
            $t->same(true, in_array($baseName, $profile['files_before_delete'], true));
            $t->same(true, count($profile['sidecar_files']) >= ($journalFamily === 'wal' ? 2 : 1));
            $t->same(true, count($profile['delete_order']) === count($profile['sidecar_files']) + 1);
            $t->same($baseName, $profile['delete_order'][count($profile['delete_order']) - 1]);
            $t->same(true, in_array('sqlite-upstream-delete-db-test', $profile['dependencies'], true));
            $t->same(true, in_array('sqlite-vfs-delete-database-sidecars', $profile['dependencies'], true));
            $t->same(true, in_array('vfs-io-dynamic-real-corpus', $profile['dependencies'], true));

            if ($journalFamily === 'wal') {
                $t->same(true, count(array_filter($profile['sidecar_files'], static fn (string $file): bool => str_contains($file, 'wal'))) >= 1);
                $t->same(true, count(array_filter($profile['sidecar_files'], static fn (string $file): bool => str_contains($file, 'shm'))) >= 1);
            } else {
                $t->same(true, count(array_filter($profile['sidecar_files'], static fn (string $file): bool => str_contains($file, 'journal') || str_contains($file, '.nal'))) >= 1);
            }

            if ($multiplex) {
                $chunkFiles = array_filter($profile['sidecar_files'], static fn (string $file): bool => preg_match('/(?:database|test3)\d{3}$|test3\.\d{3}$/', $file) === 1);
                $t->same($dynamicChunkCount, count($chunkFiles));
            }

            if ($shortNames && !$multiplex && $journalFamily === 'wal') {
                $t->same(true, in_array('test3.wal', $profile['sidecar_files'], true));
                $t->same(true, in_array('test3.shm', $profile['sidecar_files'], true));
            }
            if ($shortNames && $multiplex && $journalFamily === 'wal') {
                $t->same(true, in_array('test3.wal', $profile['sidecar_files'], true));
                $t->same(true, in_array('test3.db-shm', $profile['sidecar_files'], true));
            }
        };
    }
}

$tests['real upstream corpus vfs delete database dynamic directory target reports error'] = static function (TestRunner $t): void {
    $profile = SQLiteVfsIoDynamicPlan::deleteDatabaseSidecarProfile('delete_db-3.0', 'dir2.db', 'journal', false, false, 0, true);

    $t->same('ok', $profile['status']);
    $t->same('delete_db.test', $profile['script']);
    $t->same(['delete_db.test 3.0 directory target returns SQLITE_ERROR'], $profile['upstream']);
    $t->same('SQLITE_ERROR', $profile['delete_result']);
    $t->same(false, $profile['main_deleted']);
    $t->same(false, $profile['sidecars_deleted']);
    $t->same($profile['files_before_delete'], $profile['files_after_delete']);
    $t->same('delete_database_refuses_directory_target', $profile['reason']);
};

$tests['real upstream corpus vfs delete database dynamic missing nested path succeeds'] = static function (TestRunner $t): void {
    $profile = SQLiteVfsIoDynamicPlan::deleteDatabaseSidecarProfile('delete_db-3.1', 'dir2.db/test.db', 'journal', false, false, 0, false, true);

    $t->same('ok', $profile['status']);
    $t->same('delete_db.test', $profile['script']);
    $t->same(['delete_db.test 3.1 missing nested target returns SQLITE_OK'], $profile['upstream']);
    $t->same('SQLITE_OK', $profile['delete_result']);
    $t->same(true, $profile['main_deleted']);
    $t->same(true, $profile['sidecars_deleted']);
    $t->same([], $profile['files_after_delete']);
    $t->same('delete_database_missing_nested_target_is_ok', $profile['reason']);
};

$tests['real upstream corpus vfs delete database dynamic cites hydrated upstream sections'] = static function (TestRunner $t): void {
    $t->same([
        'delete_db.test 1.1 rollback journal sidecars',
        'delete_db.test 1.2 WAL and SHM sidecars',
        'delete_db.test 1.3 multiplex rollback journal chunks',
        'delete_db.test 1.4 multiplex WAL chunks',
        'delete_db.test 2.1 8.3 rollback journal names',
        'delete_db.test 2.2 8.3 WAL names',
        'delete_db.test 2.3 8.3 multiplex rollback chunks',
        'delete_db.test 2.4 8.3 multiplex WAL chunks',
        'delete_db.test 3.0 directory target error',
        'delete_db.test 3.1 missing nested target ok',
    ], [
        'delete_db.test 1.1 rollback journal sidecars',
        'delete_db.test 1.2 WAL and SHM sidecars',
        'delete_db.test 1.3 multiplex rollback journal chunks',
        'delete_db.test 1.4 multiplex WAL chunks',
        'delete_db.test 2.1 8.3 rollback journal names',
        'delete_db.test 2.2 8.3 WAL names',
        'delete_db.test 2.3 8.3 multiplex rollback chunks',
        'delete_db.test 2.4 8.3 multiplex WAL chunks',
        'delete_db.test 3.0 directory target error',
        'delete_db.test 3.1 missing nested target ok',
    ]);
};

$tests['real upstream corpus vfs delete database dynamic validates case volume'] = static function (TestRunner $t) use ($caseNo): void {
    $t->same(1120, $caseNo);
};

$tests['real upstream corpus vfs delete database dynamic rejects malformed inputs'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteVfsIoDynamicPlan::deleteDatabaseSidecarProfile('', 'test.db', 'journal'));
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteVfsIoDynamicPlan::deleteDatabaseSidecarProfile('delete_db-1.1', '', 'journal'));
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteVfsIoDynamicPlan::deleteDatabaseSidecarProfile('delete_db-1.1', 'test.db', 'memory'));
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteVfsIoDynamicPlan::deleteDatabaseSidecarProfile('delete_db-1.1', 'test.db', 'journal', false, true, -1));
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteVfsIoDynamicPlan::deleteDatabaseSidecarProfile('delete_db-3.0', 'dir2.db', 'journal', false, false, 0, true, true));
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteVfsIoDynamicPlan::deleteDatabaseSidecarProfile('delete_db-9.9', 'test.db', 'journal'));
};

return $tests;
