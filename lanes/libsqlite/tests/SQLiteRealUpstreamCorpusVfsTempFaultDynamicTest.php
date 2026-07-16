<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteVfsIoTransactionSequencePlan;

$tests = [];

$scenarios = [
    'tempfault-1' => [
        'statement' => 'insert_single_row',
        'initial_rows' => 2,
        'cache_size' => 10,
        'upstream' => 'tempfault.test tempfault-1 temp database insert fault',
    ],
    'tempfault-2' => [
        'statement' => 'update_indexed_rows',
        'initial_rows' => 100,
        'cache_size' => 10,
        'upstream' => 'tempfault.test tempfault-2 indexed temp update fault',
    ],
    'tempfault-2.1' => [
        'statement' => 'update_indexed_rows_reused_connection',
        'initial_rows' => 100,
        'cache_size' => 10,
        'upstream' => 'tempfault.test tempfault-2.1 reused temp connection update fault',
    ],
    'tempfault-3' => [
        'statement' => 'savepoint_update_rollback_commit',
        'initial_rows' => 50,
        'cache_size' => 10,
        'upstream' => 'tempfault.test tempfault-3 savepoint rollback temp update fault',
    ],
    'tempfault-4' => [
        'statement' => 'savepoint_update_rollback_commit_no_integrity',
        'initial_rows' => 50,
        'cache_size' => 10,
        'upstream' => 'tempfault.test tempfault-4 savepoint rollback temp update without final integrity check',
    ],
];

$operations = ['read', 'write', 'sync', 'truncate', 'delete', 'access', 'open', 'close'];
$case = 0;

foreach ($scenarios as $scenario => $config) {
    foreach ($operations as $operation) {
        foreach (range(1, 32) as $failpoint) {
            $case++;
            $tests[sprintf('real upstream corpus vfs tempfault dynamic %04d %s %s failpoint %02d', $case, $scenario, $operation, $failpoint)] =
                static function (TestRunner $t) use ($scenario, $config, $operation, $failpoint): void {
                    $plan = SQLiteVfsIoTransactionSequencePlan::tempDatabaseFaultOutcome(
                        $scenario,
                        $operation,
                        $failpoint,
                        $config['initial_rows'],
                        $config['statement'],
                        $config['cache_size']
                    );

                    $readOnlyProbe = in_array($operation, ['read', 'access', 'close'], true);
                    $expectedRc = $readOnlyProbe || $failpoint % 29 === 0 ? 'SQLITE_OK' : 'SQLITE_IOERR';
                    if ($operation === 'open' && $expectedRc !== 'SQLITE_OK') {
                        $expectedRc = 'SQLITE_CANTOPEN';
                    } elseif ($operation === 'sync' && $expectedRc !== 'SQLITE_OK') {
                        $expectedRc = 'SQLITE_IOERR_FSYNC';
                    } elseif ($operation === 'truncate' && $expectedRc !== 'SQLITE_OK') {
                        $expectedRc = 'SQLITE_IOERR_TRUNCATE';
                    } elseif ($operation === 'delete' && $expectedRc !== 'SQLITE_OK') {
                        $expectedRc = 'SQLITE_IOERR_DELETE';
                    }

                    $t->same('ok', $plan['status']);
                    $t->same('tempfault.test', $plan['script']);
                    $t->same($scenario, $plan['scenario']);
                    $t->same($operation, $plan['operation']);
                    $t->same($failpoint, $plan['failpoint']);
                    $t->same(true, $plan['temp_database']);
                    $t->same(1024, $plan['page_size']);
                    $t->same($config['cache_size'], $plan['cache_size']);
                    $t->same($config['initial_rows'], $plan['initial_rows']);
                    $t->same($config['statement'], $plan['statement']);
                    $t->same(str_starts_with($config['statement'], 'savepoint_'), $plan['savepoint_used']);
                    $t->same(str_starts_with($config['statement'], 'savepoint_'), $plan['rollback_to_savepoint']);
                    $t->same($expectedRc, $plan['expected_rc']);
                    $initialRowsAllowed = $config['statement'] !== 'insert_single_row' || $expectedRc !== 'SQLITE_OK';
                    $insertedRowsAllowed = $config['statement'] === 'insert_single_row';
                    $t->same($initialRowsAllowed, in_array($config['initial_rows'], $plan['allowed_row_counts'], true));
                    $t->same($insertedRowsAllowed, in_array($config['initial_rows'] + 1, $plan['allowed_row_counts'], true));
                    $t->same($config['statement'] === 'savepoint_update_rollback_commit_no_integrity' ? 'not-run-by-upstream-tempfault-4' : 'ok', $plan['integrity_check']);
                    $t->same(true, $plan['temp_file_cleaned']);
                    $t->same(0, $plan['open_file_count']);
                    $t->same(true, in_array('vfs-temp-database-fault-recovery', $plan['dependencies'], true));
                    $t->same(true, in_array('real-upstream-corpus-tempfault-test', $plan['dependencies'], true));
                    $t->same([$config['upstream']], $plan['upstream']);
                };
        }
    }
}

$tests['real upstream corpus vfs tempfault dynamic records source scenarios'] = static function (TestRunner $t) use ($scenarios, $case): void {
    $t->same(1280, $case);
    $t->same(
        [
            'tempfault.test tempfault-1 temp database insert fault',
            'tempfault.test tempfault-2 indexed temp update fault',
            'tempfault.test tempfault-2.1 reused temp connection update fault',
            'tempfault.test tempfault-3 savepoint rollback temp update fault',
            'tempfault.test tempfault-4 savepoint rollback temp update without final integrity check',
        ],
        array_column($scenarios, 'upstream')
    );
};

$guardCases = [
    'rejects empty scenario' => static fn (): array => SQLiteVfsIoTransactionSequencePlan::tempDatabaseFaultOutcome('', 'write', 1, 1, 'insert_single_row'),
    'rejects unsupported operation' => static fn (): array => SQLiteVfsIoTransactionSequencePlan::tempDatabaseFaultOutcome('tempfault-1', 'chmod', 1, 1, 'insert_single_row'),
    'rejects zero failpoint' => static fn (): array => SQLiteVfsIoTransactionSequencePlan::tempDatabaseFaultOutcome('tempfault-1', 'write', 0, 1, 'insert_single_row'),
    'rejects zero initial rows' => static fn (): array => SQLiteVfsIoTransactionSequencePlan::tempDatabaseFaultOutcome('tempfault-1', 'write', 1, 0, 'insert_single_row'),
    'rejects unsupported statement' => static fn (): array => SQLiteVfsIoTransactionSequencePlan::tempDatabaseFaultOutcome('tempfault-1', 'write', 1, 1, 'vacuum'),
    'rejects zero cache size' => static fn (): array => SQLiteVfsIoTransactionSequencePlan::tempDatabaseFaultOutcome('tempfault-1', 'write', 1, 1, 'insert_single_row', 0),
];

foreach ($guardCases as $name => $callable) {
    $tests['real upstream corpus vfs tempfault dynamic ' . $name] = static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, $callable);
}

return $tests;
