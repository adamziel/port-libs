<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteUpstreamSuiteEvidence;

function libsqlite_suite_denominator66_evidence(): SQLiteUpstreamSuiteEvidence
{
    return SQLiteUpstreamSuiteEvidence::fromManifestPath(__DIR__ . '/../UPSTREAM_TEST_MANIFEST.json');
}

function libsqlite_suite_denominator66_output(int $passLines = 76, int $assertions = 200, int $failures = 0, int $selected = 1, int $summaryFiles = 1): string
{
    $lines = [
        sprintf('Focused test run: %d selected test files (root lock skipped)', $selected),
    ];
    for ($i = 1; $i <= $passLines; $i++) {
        $lines[] = sprintf('PASS current next66 focused denominator case %02d', $i);
    }
    $lines[] = sprintf('%d test files, %d assertions, %d failures', $summaryFiles, $assertions, $failures);

    return implode("\n", $lines);
}

function libsqlite_suite_denominator66_rows(int $case): array
{
    $tier = match ($case % 5) {
        0 => 'release-admission',
        1 => 'focused-current',
        2 => 'permutation-suite',
        3 => 'make-test',
        default => 'hydrated-cache',
    };

    return [
        [
            'unit' => 'current-next66-denominator-ready-' . sprintf('%02d', $case),
            'tier' => $tier,
            'status' => 'ready',
            'scripts' => [
                'suite66_' . sprintf('%02d', $case) . '.test',
                'suite66_' . sprintf('%02d', $case) . '_wp.test',
            ],
            'tests' => 600 + $case,
            'command' => './testfixture ../libsqlite/test/testrunner.tcl --jobs 1 --stop-on-error veryquick suite66_' . sprintf('%02d', $case) . '.test suite66_' . sprintf('%02d', $case) . '_wp.test',
            'artifact' => 'lanes/libsqlite/notes/yield-sqlite-suite-denominator-current-next66.md#case-' . sprintf('%02d', $case),
            'evidence' => 'current-next66 records accepted-head focused suite denominator readiness without release/all parity or mapped coverage movement',
        ],
        [
            'unit' => 'accepted-veryquick-zero-error-anchor',
            'tier' => 'veryquick',
            'status' => 'preserved',
            'scripts' => ['select1.test'],
            'tests' => 329670,
            'command' => './testfixture ../libsqlite/test/testrunner.tcl --jobs 1 --stop-on-error veryquick',
            'artifact' => 'lanes/libsqlite/UPSTREAM_TEST_MANIFEST.json#runnerStatus.results.fullVeryquick',
            'evidence' => 'accepted veryquick zero-error runner evidence remains the countable denominator anchor',
        ],
        [
            'unit' => 'release-all-broad-parity',
            'tier' => 'release-all',
            'status' => 'open',
            'scripts' => ['all'],
            'tests' => 0,
            'command' => '',
            'artifact' => '',
            'evidence' => 'release/all remains open until a fresh zero-error broad runner artifact is produced',
        ],
    ];
}

$currentHead66 = 'b1646f365a6d6101159247a1bfa613fde41b6d64';
$currentMapped66 = 463;
$currentPhpPass66 = 24610;
$focusedPath66 = 'lanes/libsqlite/tests/SQLiteReleaseRunnerSuiteDenominatorCurrentNext66Test.php';
$nonOverlap66 = 'current-next66 suite denominator admission avoids batch64 suite-denominator current-next64, release/all parity claims, accepted JSON/VFS/WAL/B-tree/SQL behavior clusters, and stale mapped-coverage movement';

$tests = [];

foreach (range(1, 66) as $case) {
    $tests[sprintf('current next66 admits accepted head denominator ready row %02d', $case)] = static function (TestRunner $t) use ($case, $currentHead66, $currentMapped66, $currentPhpPass66, $focusedPath66, $nonOverlap66): void {
        $record = libsqlite_suite_denominator66_evidence()->releaseRunnerSuiteDenominatorCurrentNext66(
            libsqlite_suite_denominator66_rows($case),
            $currentHead66,
            $currentHead66,
            $currentMapped66,
            $currentPhpPass66,
            $focusedPath66,
            libsqlite_suite_denominator66_output(),
            $nonOverlap66
        );

        $t->same('current-next66-suite-denominator-ready', $record['status']);
        $t->same(3, $record['row_count']);
        $t->same(3, $record['tier_count']);
        $t->same(1, $record['ready_count']);
        $t->same(['current-next66-denominator-ready-' . sprintf('%02d', $case)], $record['ready_units']);
        $t->same(['accepted-veryquick-zero-error-anchor'], $record['preserved_units']);
        $t->same(['release-all-broad-parity'], $record['open_units']);
        $t->same(3, $record['script_count']);
        $t->same(600 + $case, $record['ready_tests']);
        $t->same(329670, $record['preserved_tests']);
        $t->same(463, $record['current_mapped']);
        $t->same(463, $record['next_mapped']);
        $t->same(0, $record['mapped_delta']);
        $t->same(false, $record['counts_release_parity']);
        $t->same(76, $record['php_pass_delta']);
        $t->same(24686, $record['next_php_pass']);
        $t->same('clear', $record['active_runner_status']);
        $t->same(true, $record['counts_suite_denominator_current_next66']);
        $t->contains('accepted-head TestRunner PASS lines', $record['dependency_closure']);
    };
}

$tests['current next66 summarizes tiers without moving mapped coverage'] = static function (TestRunner $t) use ($currentHead66, $currentMapped66, $currentPhpPass66, $focusedPath66, $nonOverlap66): void {
    $record = libsqlite_suite_denominator66_evidence()->releaseRunnerSuiteDenominatorCurrentNext66(
        libsqlite_suite_denominator66_rows(4),
        $currentHead66,
        $currentHead66,
        $currentMapped66,
        $currentPhpPass66,
        $focusedPath66,
        libsqlite_suite_denominator66_output(),
        $nonOverlap66
    );

    $t->same(1, $record['tiers']['hydrated-cache']);
    $t->same(1, $record['tiers']['release-all']);
    $t->same(1, $record['tiers']['veryquick']);
    $t->same(0, $record['mapped_delta']);
    $t->contains('mapped coverage/release-all parity unchanged', $record['next_gate']);
};

$tests['current next66 blocks stale accepted head evidence'] = static function (TestRunner $t) use ($currentHead66, $currentMapped66, $currentPhpPass66, $focusedPath66, $nonOverlap66): void {
    $record = libsqlite_suite_denominator66_evidence()->releaseRunnerSuiteDenominatorCurrentNext66(
        libsqlite_suite_denominator66_rows(7),
        $currentHead66,
        '1111111111111111111111111111111111111111',
        $currentMapped66,
        $currentPhpPass66,
        $focusedPath66,
        libsqlite_suite_denominator66_output(),
        $nonOverlap66
    );

    $t->same('blocked', $record['status']);
    $t->same(0, $record['php_pass_delta']);
    $t->same(24610, $record['next_php_pass']);
    $t->true(in_array('focused-php-pass-admission-blocked', array_column($record['blockers'], 'id'), true), 'Expected stale head blocker');
    $t->same('repository-head-mismatch', $record['php_pass_admission']['blockers'][0]['id']);
};

$tests['current next66 blocks duplicate broad runner process'] = static function (TestRunner $t) use ($currentHead66, $currentMapped66, $currentPhpPass66, $focusedPath66, $nonOverlap66): void {
    $snapshot = "1234 1 S 00:10 91.5 ./testfixture ../libsqlite/test/testrunner.tcl --jobs 1 --stop-on-error all\n";
    $record = libsqlite_suite_denominator66_evidence()->releaseRunnerSuiteDenominatorCurrentNext66(
        libsqlite_suite_denominator66_rows(8),
        $currentHead66,
        $currentHead66,
        $currentMapped66,
        $currentPhpPass66,
        $focusedPath66,
        libsqlite_suite_denominator66_output(),
        $nonOverlap66,
        $snapshot
    );

    $t->same('blocked', $record['status']);
    $t->same('blocked-active-runner', $record['active_runner_status']);
    $t->same(1, $record['active_runner_count']);
    $t->true(in_array('duplicate-broad-runner-active', array_column($record['blockers'], 'id'), true), 'Expected active runner blocker');
};

$tests['current next66 blocks missing concrete scripts'] = static function (TestRunner $t) use ($currentHead66, $currentMapped66, $currentPhpPass66, $focusedPath66, $nonOverlap66): void {
    $rows = libsqlite_suite_denominator66_rows(9);
    $rows[0]['scripts'] = [];

    $record = libsqlite_suite_denominator66_evidence()->releaseRunnerSuiteDenominatorCurrentNext66($rows, $currentHead66, $currentHead66, $currentMapped66, $currentPhpPass66, $focusedPath66, libsqlite_suite_denominator66_output(), $nonOverlap66);

    $t->same('blocked', $record['status']);
    $t->same(['current-next66-denominator-ready-09'], $record['blocked_units']);
    $t->true(in_array('countable-unit-missing-concrete-script', array_column($record['blockers'], 'evidence'), true), 'Expected missing script blocker');
};

$tests['current next66 blocks missing artifact and command'] = static function (TestRunner $t) use ($currentHead66, $currentMapped66, $currentPhpPass66, $focusedPath66, $nonOverlap66): void {
    $rows = libsqlite_suite_denominator66_rows(10);
    $rows[0]['artifact'] = '';
    $rows[0]['command'] = '';

    $record = libsqlite_suite_denominator66_evidence()->releaseRunnerSuiteDenominatorCurrentNext66($rows, $currentHead66, $currentHead66, $currentMapped66, $currentPhpPass66, $focusedPath66, libsqlite_suite_denominator66_output(), $nonOverlap66);

    $t->same('blocked', $record['status']);
    $evidence = array_column($record['blockers'], 'evidence');
    $t->true(in_array('countable-unit-missing-artifact', $evidence, true), 'Expected missing artifact blocker');
    $t->true(in_array('countable-unit-missing-command', $evidence, true), 'Expected missing command blocker');
};

$tests['current next66 blocks duplicate denominator units'] = static function (TestRunner $t) use ($currentHead66, $currentMapped66, $currentPhpPass66, $focusedPath66, $nonOverlap66): void {
    $rows = libsqlite_suite_denominator66_rows(11);
    $rows[] = $rows[0];

    $record = libsqlite_suite_denominator66_evidence()->releaseRunnerSuiteDenominatorCurrentNext66($rows, $currentHead66, $currentHead66, $currentMapped66, $currentPhpPass66, $focusedPath66, libsqlite_suite_denominator66_output(), $nonOverlap66);

    $t->same('blocked', $record['status']);
    $t->true(in_array('duplicate-suite-denominator-unit', array_column($record['blockers'], 'evidence'), true), 'Expected duplicate unit blocker');
};

$tests['current next66 blocks under threshold pass output'] = static function (TestRunner $t) use ($currentHead66, $currentMapped66, $currentPhpPass66, $focusedPath66, $nonOverlap66): void {
    $record = libsqlite_suite_denominator66_evidence()->releaseRunnerSuiteDenominatorCurrentNext66(
        libsqlite_suite_denominator66_rows(12),
        $currentHead66,
        $currentHead66,
        $currentMapped66,
        $currentPhpPass66,
        $focusedPath66,
        libsqlite_suite_denominator66_output(65, 180),
        $nonOverlap66
    );

    $t->same('blocked', $record['status']);
    $t->same(0, $record['php_pass_delta']);
    $t->true(in_array('focused-pass-delta-below-minimum', array_column($record['blockers'], 'id'), true), 'Expected minimum pass blocker');
};

$tests['current next66 blocks failing focused output'] = static function (TestRunner $t) use ($currentHead66, $currentMapped66, $currentPhpPass66, $focusedPath66, $nonOverlap66): void {
    $record = libsqlite_suite_denominator66_evidence()->releaseRunnerSuiteDenominatorCurrentNext66(
        libsqlite_suite_denominator66_rows(13),
        $currentHead66,
        $currentHead66,
        $currentMapped66,
        $currentPhpPass66,
        $focusedPath66,
        libsqlite_suite_denominator66_output(76, 200, 1),
        $nonOverlap66
    );

    $t->same('blocked', $record['status']);
    $t->same('focused-output-has-failures', $record['php_pass_admission']['blockers'][0]['id']);
};

$tests['current next66 rejects invalid setup'] = static function (TestRunner $t) use ($currentHead66, $currentMapped66, $currentPhpPass66, $focusedPath66, $nonOverlap66): void {
    $evidence = libsqlite_suite_denominator66_evidence();

    $t->throws(InvalidArgumentException::class, static fn () => $evidence->releaseRunnerSuiteDenominatorCurrentNext66([], $currentHead66, $currentHead66, $currentMapped66, $currentPhpPass66, $focusedPath66, libsqlite_suite_denominator66_output(), $nonOverlap66));
    $t->throws(InvalidArgumentException::class, static fn () => $evidence->releaseRunnerSuiteDenominatorCurrentNext66(libsqlite_suite_denominator66_rows(14), '', $currentHead66, $currentMapped66, $currentPhpPass66, $focusedPath66, libsqlite_suite_denominator66_output(), $nonOverlap66));
    $t->throws(InvalidArgumentException::class, static fn () => $evidence->releaseRunnerSuiteDenominatorCurrentNext66(libsqlite_suite_denominator66_rows(14), $currentHead66, '', $currentMapped66, $currentPhpPass66, $focusedPath66, libsqlite_suite_denominator66_output(), $nonOverlap66));
    $t->throws(InvalidArgumentException::class, static fn () => $evidence->releaseRunnerSuiteDenominatorCurrentNext66(libsqlite_suite_denominator66_rows(14), $currentHead66, $currentHead66, -1, $currentPhpPass66, $focusedPath66, libsqlite_suite_denominator66_output(), $nonOverlap66));
    $t->throws(InvalidArgumentException::class, static fn () => $evidence->releaseRunnerSuiteDenominatorCurrentNext66(libsqlite_suite_denominator66_rows(14), $currentHead66, $currentHead66, $currentMapped66, $currentPhpPass66, $focusedPath66, libsqlite_suite_denominator66_output(), $nonOverlap66, '', 0));
};

return $tests;
