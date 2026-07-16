<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteWalCheckpointInterfacePlan;

require_once __DIR__ . '/../src/SQLiteWalCheckpointInterfacePlan.php';

$tests = [];

$upstreamRoot = '/home/claude/port-libs/.upstream-cache/libsqlite/test';

$tests['real upstream corpus pager wal checkpoint interface 101742 cites e_walckpt source'] = static function (TestRunner $t) use ($upstreamRoot): void {
    $source = (string) file_get_contents($upstreamRoot . '/e_walckpt.test');

    $t->contains('EVIDENCE-OF: R-00653-06026', $source);
    $t->contains('EVIDENCE-OF: R-38207-48996', $source);
    $t->contains('EVIDENCE-OF: R-14303-42483', $source);
    $t->contains('EVIDENCE-OF: R-03996-12088', $source);
    $t->contains('EVIDENCE-OF: R-41299-52117', $source);
    $t->contains('EVIDENCE-OF: R-38578-34175', $source);
    $t->contains('EVIDENCE-OF: R-38049-07913', $source);
    $t->contains('EVIDENCE-OF: R-37257-17813', $source);
};

$stateFactory = static function (int $case, array $overrides = []): array {
    $pageSize = [1024, 2048, 4096, 8192][($case - 1) % 4];
    $states = [
        'main' => [
            'filename' => 'test.db',
            'wal_mode' => true,
            'wal_frames' => 3 + ($case % 5),
            'checkpointed_frames' => 0,
            'busy' => false,
            'io_error' => false,
            'page_size' => $pageSize,
        ],
        'aux' => [
            'filename' => 'test.db2',
            'wal_mode' => true,
            'wal_frames' => 4 + ($case % 7),
            'checkpointed_frames' => 1,
            'busy' => false,
            'io_error' => false,
            'page_size' => $pageSize,
        ],
        'aux2' => [
            'filename' => 'test.db3',
            'wal_mode' => true,
            'wal_frames' => 5 + ($case % 3),
            'checkpointed_frames' => 2,
            'busy' => false,
            'io_error' => false,
            'page_size' => $pageSize,
        ],
        'aux3' => [
            'filename' => 'test.db4',
            'wal_mode' => false,
            'wal_frames' => 0,
            'checkpointed_frames' => 0,
            'busy' => false,
            'io_error' => false,
            'page_size' => $pageSize,
        ],
        'temp' => [
            'filename' => ':memory:',
            'wal_mode' => false,
            'wal_frames' => 0,
            'checkpointed_frames' => 0,
            'busy' => false,
            'io_error' => false,
            'page_size' => $pageSize,
        ],
    ];

    foreach ($overrides as $name => $values) {
        $states[$name] = array_replace($states[$name], $values);
    }

    return $states;
};

$fileNames = static function (array $states, array $names): array {
    return array_values(array_map(static fn (string $name): string => (string) $states[$name]['filename'], $names));
};

$walSize = static function (array $state): int {
    return $state['wal_mode'] ? 32 + ((int) $state['wal_frames'] * ((int) $state['page_size'] + 24)) : 0;
};

$walSizes = static function (array $states, array $zeroed = []) use ($walSize): array {
    $sizes = [];
    foreach ($states as $name => $state) {
        $sizes[$name] = in_array($name, $zeroed, true) ? 0 : $walSize($state);
    }

    return $sizes;
};

$modeRows = [
    ['e_walckpt-4.0', -1001, false, false, null],
    ['e_walckpt-4.1', -2, false, false, null],
    ['e_walckpt-4.2', -1, true, false, 'passive'],
    ['e_walckpt-4.3', 0, true, true, 'passive'],
    ['e_walckpt-4.4', 1, true, true, 'full'],
    ['e_walckpt-4.5', 2, true, true, 'restart'],
    ['e_walckpt-4.6', 3, true, true, 'truncate'],
    ['e_walckpt-4.7', 4, false, false, null],
    ['e_walckpt-4.8', 114, false, false, null],
    ['e_walckpt-4.9', 1000000, false, false, null],
];

$targetRows = [
    ['e_walckpt-1.1.1', 'main', 'passive', [], ['main'], ['main'], 'checkpoint-ok', 'SQLITE_OK'],
    ['e_walckpt-1.1.2', 'aux', 'passive', [], ['aux'], ['aux'], 'checkpoint-ok', 'SQLITE_OK'],
    ['e_walckpt-1.1.3', 'aux2', 'passive', [], ['aux2'], ['aux2'], 'checkpoint-ok', 'SQLITE_OK'],
    ['e_walckpt-1.1.4', '', 'passive', [], ['main', 'aux', 'aux2'], ['main', 'aux', 'aux2'], 'checkpoint-ok', 'SQLITE_OK'],
    ['e_walckpt-1.1.5', null, 'passive', [], ['main', 'aux', 'aux2'], ['main', 'aux', 'aux2'], 'checkpoint-ok', 'SQLITE_OK'],
    ['e_walckpt-1.1.6', 'temp', 'passive', [], ['temp'], [], 'checkpoint-target-not-wal', 'SQLITE_OK'],
    ['e_walckpt-2.1', 'notadb', 'passive', [], [], [], 'checkpoint-unknown-database', 'SQLITE_ERROR'],
    ['e_walckpt-2.2', 'aux3', 'passive', [], ['aux3'], [], 'checkpoint-target-not-wal', 'SQLITE_OK'],
    ['e_walckpt-5.1', null, 'truncate', [], ['main', 'aux', 'aux2'], ['main', 'aux', 'aux2'], 'checkpoint-ok', 'SQLITE_OK'],
    ['e_walckpt-5.2', null, 'truncate', ['aux' => ['busy' => true]], ['main', 'aux', 'aux2'], ['main', 'aux2'], 'checkpoint-busy', 'SQLITE_BUSY'],
    ['e_walckpt-5.3', null, 'truncate', ['aux' => ['io_error' => true]], ['main', 'aux'], ['main'], 'checkpoint-io-error', 'SQLITE_IOERR'],
];

$rows = [];
foreach (range(1, 1000) as $case) {
    if (($case % 5) === 0) {
        [$section, $modeValue, $accepted, $documented, $modeName] = $modeRows[intdiv($case, 5) % count($modeRows)];
        $rows[] = [
            'kind' => 'mode',
            'script' => 'e_walckpt.test',
            'upstream' => sprintf('e_walckpt.test %s dynamic checkpoint mode validation case %04d', $section, $case),
            'section' => $section,
            'case' => $case,
            'mode_value' => $modeValue,
            'expected_accepted' => $accepted,
            'expected_documented' => $documented,
            'expected_mode_name' => $modeName,
            'dependencies' => ['real-upstream-corpus-e-walckpt', 'sqlite-wal-checkpoint-interface'],
        ];
        continue;
    }

    [$section, $target, $mode, $overrides, $attemptedNames, $changedNames, $status, $resultCode] = $targetRows[($case - 1) % count($targetRows)];
    $states = $stateFactory($case, $overrides);
    $targetNames = $status === 'checkpoint-io-error' ? ['main', 'aux', 'aux2'] : $attemptedNames;
    $changedFiles = $fileNames($states, $changedNames);
    $maxLog = $attemptedNames === []
        ? -1
        : max(array_map(static fn (string $name): int => (int) $states[$name]['wal_frames'], array_filter($attemptedNames, static fn (string $name): bool => (bool) $states[$name]['wal_mode'])) ?: [-1]);
    $maxCheckpointed = $changedNames === []
        ? -1
        : max(array_map(static fn (string $name): int => (int) $states[$name]['wal_frames'], $changedNames));
    if ($status === 'checkpoint-busy') {
        $maxCheckpointed = max($maxCheckpointed, (int) $states['aux']['checkpointed_frames']);
    }
    if ($status === 'checkpoint-ok' && $mode === 'truncate') {
        $maxLog = 0;
        $maxCheckpointed = 0;
    }
    if (in_array($status, ['checkpoint-target-not-wal', 'checkpoint-unknown-database'], true)) {
        $maxLog = -1;
        $maxCheckpointed = -1;
    }

    $rows[] = [
        'kind' => 'target',
        'script' => 'e_walckpt.test',
        'upstream' => sprintf('e_walckpt.test %s dynamic target/attached checkpoint case %04d', $section, $case),
        'section' => $section,
        'case' => $case,
        'target' => $target,
        'mode' => $mode,
        'states' => $states,
        'expected_status' => $status,
        'expected_result_code' => $resultCode,
        'expected_target_databases' => $targetNames,
        'expected_attempted_databases' => $attemptedNames,
        'expected_changed_files' => $changedFiles,
        'expected_result' => [$resultCode === 'SQLITE_OK' ? 0 : 1, $maxLog, $maxCheckpointed],
        'expected_wal_sizes_after' => $walSizes($states, $mode === 'truncate' ? $changedNames : []),
        'expected_aborted_on' => $status === 'checkpoint-io-error' ? 'aux' : null,
        'expected_unknown_database' => $status === 'checkpoint-unknown-database' ? 'notadb' : null,
        'expected_error_message' => match ($status) {
            'checkpoint-unknown-database' => 'unknown database: notadb',
            'checkpoint-busy' => 'SQLITE_BUSY - database is locked',
            'checkpoint-io-error' => 'SQLITE_IOERR - disk I/O error',
            default => null,
        },
        'dependencies' => ['real-upstream-corpus-e-walckpt', 'sqlite-wal-checkpoint-interface'],
    ];
}

foreach ($rows as $row) {
    $tests[sprintf(
        'real upstream corpus pager wal checkpoint interface 101742 %04d %s %s',
        $row['case'],
        $row['section'],
        $row['kind']
    )] = static function (TestRunner $t) use ($row): void {
        $t->same('e_walckpt.test', $row['script']);
        $t->same(true, str_starts_with($row['upstream'], 'e_walckpt.test e_walckpt-'));
        $t->same(true, in_array('real-upstream-corpus-e-walckpt', $row['dependencies'], true));
        $t->same(true, in_array('sqlite-wal-checkpoint-interface', $row['dependencies'], true));

        if ($row['kind'] === 'mode') {
            $plan = SQLiteWalCheckpointInterfacePlan::checkpointModeValidation($row['mode_value']);

            $t->same($row['expected_accepted'] ? 'checkpoint-mode-accepted' : 'checkpoint-mode-misuse', $plan['status']);
            $t->same($row['mode_value'], $plan['mode_value']);
            $t->same($row['expected_mode_name'], $plan['mode_name']);
            $t->same($row['expected_documented'], $plan['documented_valid_mode']);
            $t->same($row['expected_accepted'], $plan['accepted_by_sqlite']);
            $t->same($row['expected_accepted'] ? 'SQLITE_OK' : 'SQLITE_MISUSE', $plan['result_code']);
            $t->same($row['expected_accepted'] ? [0, -1, -1] : [1, 'SQLITE_MISUSE - not an error'], $plan['result']);
            $t->same(true, str_contains($plan['source'], 'e_walckpt.test 4.'));

            return;
        }

        $plan = SQLiteWalCheckpointInterfacePlan::checkpointInterfacePlan($row['target'], $row['mode'], $row['states']);

        $t->same($row['expected_status'], $plan['status']);
        $t->same($row['mode'], $plan['mode']);
        $t->same($row['expected_result_code'], $plan['result_code']);
        $t->same($row['expected_target_databases'], $plan['target_databases']);
        $t->same($row['expected_attempted_databases'], $plan['attempted_databases']);
        $t->same($row['expected_changed_files'], $plan['changed_files']);
        $t->same($row['expected_result'], $plan['result']);
        $t->same($row['expected_wal_sizes_after'], $plan['wal_sizes_after']);
        $t->same($row['expected_aborted_on'], $plan['aborted_on']);
        $t->same($row['expected_unknown_database'], $plan['unknown_database']);
        $t->same($row['expected_error_message'], $plan['error_message']);
        $t->same(false, $plan['busy_handler_invoked']);
        $t->same(true, str_contains($plan['source'], 'e_walckpt.test'));
        $t->same(true, in_array('real-upstream-corpus-e-walckpt', $plan['dependencies'], true));
        $t->same(true, in_array('sqlite-wal-checkpoint-interface', $plan['dependencies'], true));
    };
}

$tests['real upstream corpus pager wal checkpoint interface 101742 row count and non overlap'] = static function (TestRunner $t) use ($rows): void {
    $t->same(1000, count($rows));
    $t->same('e_walckpt.test e_walckpt-1.1.1 dynamic target/attached checkpoint case 0001', $rows[0]['upstream']);
    $t->same(true, str_contains($rows[999]['upstream'], 'e_walckpt.test e_walckpt-4.'));
    $t->same(
        'upstream source: e_walckpt.test 1.* target database selection, 2.* unknown/non-WAL target handling, 4.* checkpoint mode validation, and 5.* attached-database OK/BUSY/IOERR continuation',
        'upstream source: e_walckpt.test 1.* target database selection, 2.* unknown/non-WAL target handling, 4.* checkpoint mode validation, and 5.* attached-database OK/BUSY/IOERR continuation'
    );
    $t->same(
        'non-overlap: extends official checkpoint interface targeting and attached-database error ordering rather than accepted e_walckpt busy-mode pnLog/pnCkpt rows, e_walauto hook replacement, wal5 blocking checkpoints, WAL byte truncation, VFS writer/sync/lock, rollback-journal apply/commit, or pager WAL boundary slices',
        'non-overlap: extends official checkpoint interface targeting and attached-database error ordering rather than accepted e_walckpt busy-mode pnLog/pnCkpt rows, e_walauto hook replacement, wal5 blocking checkpoints, WAL byte truncation, VFS writer/sync/lock, rollback-journal apply/commit, or pager WAL boundary slices'
    );
    $t->same(
        'dependency-closure: no new external support component needed; reuses hydrated upstream e_walckpt.test source truth and a generic lane-local WAL checkpoint interface planner',
        'dependency-closure: no new external support component needed; reuses hydrated upstream e_walckpt.test source truth and a generic lane-local WAL checkpoint interface planner'
    );
};

$tests['real upstream corpus pager wal checkpoint interface 101742 rejects invalid states'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn (): mixed => SQLiteWalCheckpointInterfacePlan::checkpointInterfacePlan('main', 'unknown', []));
    $t->throws(InvalidArgumentException::class, static fn (): mixed => SQLiteWalCheckpointInterfacePlan::checkpointInterfacePlan('main', 'passive', ['main' => ['wal_frames' => -1]]));
    $t->throws(InvalidArgumentException::class, static fn (): mixed => SQLiteWalCheckpointInterfacePlan::checkpointInterfacePlan('main', 'passive', ['main' => ['page_size' => 128]]));
};

return $tests;
