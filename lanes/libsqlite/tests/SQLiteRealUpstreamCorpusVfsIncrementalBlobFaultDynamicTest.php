<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteVfsIoTrafficPlan;

$tests = [];

$scenarios = [
    'incrblobfault-1' => [
        'reopen' => static fn (int $round): int => 1000 - ($round % 7),
        'read_bytes' => [5, 11],
        'upstream' => 'incrblobfault.test 1 sqlite3_blob_reopen high rowid faultsim returns ok or connection error',
    ],
    'incrblobfault-2' => [
        'reopen' => static fn (int $round): int => -1 - ($round % 5),
        'read_bytes' => [5, 11],
        'upstream' => 'incrblobfault.test 2 sqlite3_blob_reopen negative rowid returns no such rowid or disk I/O error',
    ],
    'incrblobfault-3' => [
        'reopen' => static fn (int $round): ?int => null,
        'read_bytes' => [1, 5, 11],
        'upstream' => 'incrblobfault.test 3 incremental blob open/read returns hello world under faultsim',
    ],
];

$operations = ['xRead', 'xWrite', 'xSync', 'xTruncate'];
$caseCount = 0;

foreach ($scenarios as $scenario => $config) {
    foreach ($config['read_bytes'] as $readBytes) {
        foreach ($operations as $operation) {
            for ($faultIndex = 1; $faultIndex <= 120; $faultIndex++) {
                $caseCount++;
                $tests[sprintf(
                    'real upstream corpus vfs incrblobfault dynamic %s %s read %02d fault %03d',
                    $scenario,
                    strtolower($operation),
                    $readBytes,
                    $faultIndex
                )] = static function (TestRunner $t) use ($scenario, $config, $readBytes, $operation, $faultIndex): void {
                    $reopenRowid = $config['reopen']($faultIndex);
                    $plan = SQLiteVfsIoTrafficPlan::incrementalBlobFaultProfile(
                        "{$scenario}.{$operation}.{$faultIndex}.{$readBytes}",
                        $faultIndex,
                        1 + ($faultIndex % 16),
                        $reopenRowid,
                        $readBytes,
                        $operation
                    );
                    $detectedReadFault = $faultIndex % 31 !== 0 && $operation === 'xRead';
                    $isReopen = $scenario !== 'incrblobfault-3';

                    $t->same('incrblobfault.test', $plan['script']);
                    $t->same("{$scenario}.{$operation}.{$faultIndex}.{$readBytes}", $plan['scenario']);
                    $t->same(1 + ($faultIndex % 16), $plan['blob_rowid']);
                    $t->same($isReopen ? $reopenRowid : null, $plan['reopen_rowid']);
                    $t->same($readBytes, $plan['read_bytes']);
                    $t->same($faultIndex, $plan['fault_index']);
                    $t->same($operation, $plan['fault_operation']);
                    $t->same(true, $plan['opened_blob']);
                    $t->same($isReopen, $plan['reopen_attempted']);
                    $t->same(!$isReopen, $plan['read_attempted']);

                    if ($scenario === 'incrblobfault-1') {
                        $t->same($detectedReadFault ? 'disk I/O error' : 'ok', $plan['reopen_result']);
                    } elseif ($scenario === 'incrblobfault-2') {
                        $t->same($detectedReadFault ? 'disk I/O error' : "no such rowid: {$reopenRowid}", $plan['reopen_result']);
                    } else {
                        $t->same('not_attempted', $plan['reopen_result']);
                    }

                    if ($scenario === 'incrblobfault-3') {
                        $t->same($detectedReadFault ? 'disk I/O error' : 'ok', $plan['read_result']);
                        $t->same($detectedReadFault ? null : substr('hello world', 0, $readBytes), $plan['result_payload']);
                    } else {
                        $t->same('not_attempted', $plan['read_result']);
                        $t->same(null, $plan['result_payload']);
                    }

                    $t->same(true, $plan['handle_must_close']);
                    $t->same($detectedReadFault ? 'disk I/O error' : 'not an error', $plan['connection_error']);
                    $t->same('ok', $plan['integrity_check']);
                    $t->same(0, $plan['open_file_count']);
                    $t->same(true, in_array('sqlite-upstream-incrblobfault-test', $plan['dependencies'], true));
                    $t->same(true, in_array('sqlite-incremental-blob-reopen', $plan['dependencies'], true));
                    $t->same(true, in_array('sqlite-vfs-dynamic-fault-recovery', $plan['dependencies'], true));
                    $t->same([$config['upstream']], $plan['upstream']);
                };
            }
        }
    }
}

$tests['real upstream corpus vfs incrblobfault dynamic source ownership and guards'] = static function (TestRunner $t) use ($caseCount): void {
    $t->same(3360, $caseCount);
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteVfsIoTrafficPlan::incrementalBlobFaultProfile('', 1));
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteVfsIoTrafficPlan::incrementalBlobFaultProfile('incrblobfault-1', 0));
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteVfsIoTrafficPlan::incrementalBlobFaultProfile('incrblobfault-1', 1, 0));
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteVfsIoTrafficPlan::incrementalBlobFaultProfile('incrblobfault-1', 1, 1, null, 0));
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteVfsIoTrafficPlan::incrementalBlobFaultProfile('incrblobfault-1', 1, 1, null, 11, 'xOpen'));
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteVfsIoTrafficPlan::incrementalBlobFaultProfile('incrblobfault-9', 1));
};

return $tests;
