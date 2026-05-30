<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteVfsIoDynamicPlan;

$tests = [];

$scenarioStatements = [
    'transaction' => [
        'CREATE TABLE t1(a,b,c)',
        'BEGIN TRANSACTION',
        'INSERT INTO t1 VALUES(1,2,3)',
        'INSERT INTO t1 VALUES(4,5,6)',
        'ROLLBACK',
        'COMMIT',
        'DELETE FROM t1 WHERE a<100',
    ],
    'vacuum' => [
        'CREATE TABLE t1(a,b,c)',
        'INSERT INTO t1 VALUES(1, randomblob(600), randomblob(600))',
        'DROP TABLE t2',
        'VACUUM',
    ],
    'overflow-read' => [
        'CREATE TABLE abc(a1, a2, many_columns)',
        'INSERT INTO abc (a1) VALUES(NULL)',
        'SELECT * FROM abc',
    ],
    'hot-journal' => [
        'CREATE TABLE t1(a,b)',
        'BEGIN',
        'INSERT INTO t1 VALUES(3,4)',
        'SELECT * FROM t1',
    ],
    'statement-playback' => [
        'CREATE TABLE t1(a PRIMARY KEY, b)',
        'BEGIN',
        'INSERT INTO t1 VALUES("abc",123)',
        'INSERT INTO t1 SELECT (a+500)%900, "good string" FROM t1',
    ],
    'pointer-map' => [
        'PRAGMA auto_vacuum = incremental',
        'CREATE TABLE t1(x)',
        'CREATE TABLE t2(x)',
        'DELETE FROM t2 WHERE rowid = 3',
        'INSERT INTO t1 VALUES(randomblob(2000))',
    ],
];

$scenarioExpectations = [
    'transaction' => ['checkpoint' => 'refcount', 'result' => 'SQLITE_IOERR'],
    'vacuum' => ['checkpoint' => 'checksum', 'result' => 'SQLITE_IOERR'],
    'overflow-read' => ['checkpoint' => 'record-header', 'result' => 'SQLITE_IOERR'],
    'hot-journal' => ['checkpoint' => 'hot-journal', 'result' => 'SQLITE_IOERR'],
    'statement-playback' => ['checkpoint' => 'statement-journal', 'result' => 'constraint'],
    'pointer-map' => ['checkpoint' => 'pointer-map', 'result' => 'SQLITE_IOERR'],
];

$case = 0;
foreach (array_keys($scenarioStatements) as $scenario) {
    foreach (range(1, 90) as $failAt) {
        $case++;
        $autoVacuum = $failAt % 3 === 0;
        $multiFileCommit = $scenario === 'transaction' && $failAt % 5 === 0;
        $tests[sprintf('real upstream corpus vfs ioerr recovery dynamic %03d %s fail at %02d', $case, $scenario, $failAt)] = static function (TestRunner $t) use ($scenarioStatements, $scenarioExpectations, $scenario, $failAt, $autoVacuum, $multiFileCommit): void {
            $profile = SQLiteVfsIoDynamicPlan::ioErrorRecoveryProfile($scenario, $failAt, $scenarioStatements[$scenario], $autoVacuum, $multiFileCommit);
            $expectation = $scenarioExpectations[$scenario];
            $suppressed = ($scenario === 'transaction' && $autoVacuum && $failAt === 8)
                || ($scenario === 'vacuum' && ($failAt === 1 || ($autoVacuum && $failAt === 12)));

            $t->same('ok', $profile['status']);
            $t->same('ioerr.test', $profile['script']);
            $t->same($scenario, $profile['scenario']);
            $t->same($failAt, $profile['fail_at']);
            $t->same(count($scenarioStatements[$scenario]), $profile['statement_count']);
            $t->same($autoVacuum, $profile['auto_vacuum']);
            $t->same($multiFileCommit, $profile['multi_file_commit']);
            $t->same($suppressed ? 'suppressed' : $expectation['result'], $profile['expected_result']);
            $t->same(!$suppressed && $scenario !== 'overflow-read', $profile['rollback_required']);
            $t->same($expectation['checkpoint'], $profile['checkpoint']);
            $t->same('ok', $profile['integrity_check']);
            $t->same(true, $profile['rows_preserved']);
            $t->same($scenario === 'hot-journal' && $failAt > 1, $profile['hot_journal_replayed']);
            $t->same($scenario === 'pointer-map', $profile['pointer_map_checked']);
            $t->same($scenario === 'overflow-read', $profile['overflow_read_retried']);
            $t->same($multiFileCommit ? 2 : ($scenario === 'vacuum' ? 2 : 1), $profile['journal_files_touched']);
            $t->same(true, in_array('upstream-ioerr-recovery-profile', $profile['dependencies'], true));
            $t->same(true, in_array('vfs-io-dynamic-real-corpus', $profile['dependencies'], true));
            $t->same(true, in_array('ioerr.test ioerr-1', $profile['upstream'], true));
            $t->same(true, in_array('ioerr.test ioerr-14', $profile['upstream'], true));
        };
    }
}

$tests['real upstream corpus vfs ioerr recovery dynamic records upstream sections'] = static function (TestRunner $t): void {
    $profile = SQLiteVfsIoDynamicPlan::ioErrorRecoveryProfile('pointer-map', 14, [
        'PRAGMA auto_vacuum = incremental',
        'INSERT INTO t1 VALUES(randomblob(2000))',
    ], true);

    $t->same([
        'ioerr.test ioerr-1',
        'ioerr.test ioerr-2',
        'ioerr.test ioerr-4',
        'ioerr.test ioerr-7',
        'ioerr.test ioerr-10',
        'ioerr.test ioerr-12',
        'ioerr.test ioerr-13',
        'ioerr.test ioerr-14',
    ], $profile['upstream']);
    $t->same('autovacuum_pointer_map_io_error_keeps_tree_consistent', $profile['reason']);
    $t->same(true, $profile['pointer_map_checked']);
};

$tests['real upstream corpus vfs ioerr recovery dynamic rejects malformed inputs'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteVfsIoDynamicPlan::ioErrorRecoveryProfile('missing', 1, ['SELECT 1']));
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteVfsIoDynamicPlan::ioErrorRecoveryProfile('transaction', 0, ['SELECT 1']));
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteVfsIoDynamicPlan::ioErrorRecoveryProfile('transaction', 1, []));
};

return $tests;
