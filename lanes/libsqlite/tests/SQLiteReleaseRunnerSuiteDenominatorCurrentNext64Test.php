<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteUpstreamSuiteEvidence;

function libsqlite_suite_denominator64_evidence(): SQLiteUpstreamSuiteEvidence
{
    return SQLiteUpstreamSuiteEvidence::fromManifestPath(__DIR__ . '/../UPSTREAM_TEST_MANIFEST.json');
}

function libsqlite_suite_denominator64_output(int $assertions = 64, int $failures = 0): string
{
    return "Focused test run: 1 selected test files (root lock skipped)\n"
        . "1 test files, {$assertions} assertions, {$failures} failures\n";
}

function libsqlite_suite_denominator64_rows(int $case): array
{
    $tier = match ($case % 4) {
        0 => 'release-all',
        1 => 'focused-current',
        2 => 'permutation-suite',
        default => 'make-test',
    };

    return [
        [
            'unit' => 'current-next64-suite-denominator-' . sprintf('%02d', $case),
            'tier' => $tier,
            'current_status' => 'unmapped',
            'next_status' => 'countable',
            'command' => './testfixture ../libsqlite/test/testrunner.tcl --jobs 1 --stop-on-error veryquick suite64_' . $case . '.test',
            'evidence' => 'current-next64 admits a concrete suite denominator unit with parsed zero-error runner evidence',
            'current_tests' => 0,
            'next_tests' => 1000 + $case,
        ],
        [
            'unit' => 'accepted-veryquick-baseline',
            'tier' => 'veryquick',
            'current_status' => 'countable',
            'next_status' => 'countable',
            'command' => './testfixture ../libsqlite/test/testrunner.tcl --jobs 1 --stop-on-error veryquick',
            'evidence' => 'accepted veryquick zero-error baseline remains the preserved suite denominator anchor',
            'current_tests' => 329670,
            'next_tests' => 329670,
        ],
        [
            'unit' => 'release-all-parity',
            'tier' => 'release-all',
            'current_status' => 'unmapped',
            'next_status' => $case % 8 === 0 ? 'countable' : 'unmapped',
            'command' => $case % 8 === 0 ? './testfixture ../libsqlite/test/testrunner.tcl --jobs 1 --stop-on-error all' : '',
            'evidence' => $case % 8 === 0 ? 'synthetic accepted-HEAD release/all artifact is countable for this fixture row' : '',
            'current_tests' => 0,
            'next_tests' => $case % 8 === 0 ? 20000 + $case : 0,
        ],
    ];
}

$currentHead64 = 'aa5c67a8d70941079503fe746744a6952caec0a5';
$nextHead64 = 'suite-denominator-current-next64';
$currentMapped64 = 462;
$currentPhpPass64 = 23341;
$focusedPath64 = 'lanes/libsqlite/tests/SQLiteReleaseRunnerSuiteDenominatorCurrentNext64Test.php';
$nonOverlap64 = 'current-next64 suite denominator admission avoids accepted batch55 release burnup, current-next52/53/54/55 suite gap burnup, JSON/VFS/WAL/B-tree/SQL behavior clusters, and broad release parity claims';

$tests = [];

for ($i = 1; $i <= 64; $i++) {
    $tests['current next64 suite denominator admits unit ' . $i] = static function (TestRunner $t) use ($i, $currentHead64, $nextHead64, $currentMapped64, $currentPhpPass64, $focusedPath64, $nonOverlap64): void {
        $record = libsqlite_suite_denominator64_evidence()->releaseRunnerSuiteDenominatorFocusedAdmission(
            libsqlite_suite_denominator64_rows($i),
            $currentHead64,
            $nextHead64,
            $currentMapped64,
            $currentPhpPass64,
            $focusedPath64,
            libsqlite_suite_denominator64_output(64),
            $nonOverlap64
        );

        $extraRelease = $i % 8 === 0 ? 1 : 0;
        $t->same('current-next64-suite-denominator-advanced', $record['status']);
        $t->same(3, $record['row_count']);
        $t->same(462, $record['current_mapped']);
        $t->same(463 + $extraRelease, $record['next_mapped']);
        $t->same(1 + $extraRelease, $record['mapped_delta']);
        $t->same(64, $record['php_pass_delta']);
        $t->same(23405, $record['next_php_pass']);
        $t->same(true, $record['counts_suite_denominator_current_next64']);
        $t->same(false, $record['counts_release_parity']);
        $t->contains('current-next64 suite denominator admission', $record['dependency_closure']);
    };
}

$tests['current next64 suite denominator summarizes tiers and open rows'] = static function (TestRunner $t) use ($currentHead64, $nextHead64, $currentMapped64, $currentPhpPass64, $focusedPath64, $nonOverlap64): void {
    $record = libsqlite_suite_denominator64_evidence()->releaseRunnerSuiteDenominatorFocusedAdmission(
        libsqlite_suite_denominator64_rows(1),
        $currentHead64,
        $nextHead64,
        $currentMapped64,
        $currentPhpPass64,
        $focusedPath64,
        libsqlite_suite_denominator64_output(),
        $nonOverlap64
    );

    $t->same(3, $record['tier_count']);
    $t->same(1, $record['tiers']['focused-current']);
    $t->same(1, $record['tiers']['release-all']);
    $t->same(1, $record['tiers']['veryquick']);
    $t->same(['release-all-parity'], $record['open_units']);
    $t->same(['accepted-veryquick-baseline'], $record['preserved_units']);
};

$tests['current next64 suite denominator blocks missing command evidence'] = static function (TestRunner $t) use ($currentHead64, $nextHead64, $currentMapped64, $currentPhpPass64, $focusedPath64, $nonOverlap64): void {
    $rows = libsqlite_suite_denominator64_rows(2);
    $rows[0]['command'] = '';
    $rows[0]['evidence'] = '';

    $record = libsqlite_suite_denominator64_evidence()->releaseRunnerSuiteDenominatorFocusedAdmission($rows, $currentHead64, $nextHead64, $currentMapped64, $currentPhpPass64, $focusedPath64, libsqlite_suite_denominator64_output(), $nonOverlap64);

    $t->same('blocked', $record['status']);
    $t->same(0, $record['mapped_delta']);
    $t->same(462, $record['next_mapped']);
    $t->true(in_array('countable-unit-missing-command', array_column($record['blockers'], 'evidence'), true), 'Expected missing command blocker');
    $t->true(in_array('countable-unit-missing-evidence', array_column($record['blockers'], 'evidence'), true), 'Expected missing evidence blocker');
};

$tests['current next64 suite denominator blocks duplicate unit rows'] = static function (TestRunner $t) use ($currentHead64, $nextHead64, $currentMapped64, $currentPhpPass64, $focusedPath64, $nonOverlap64): void {
    $rows = libsqlite_suite_denominator64_rows(3);
    $rows[] = $rows[0];

    $record = libsqlite_suite_denominator64_evidence()->releaseRunnerSuiteDenominatorFocusedAdmission($rows, $currentHead64, $nextHead64, $currentMapped64, $currentPhpPass64, $focusedPath64, libsqlite_suite_denominator64_output(), $nonOverlap64);

    $t->same('blocked', $record['status']);
    $t->true(in_array('duplicate-suite-denominator-unit', array_column($record['blockers'], 'evidence'), true), 'Expected duplicate unit blocker');
};

$tests['current next64 suite denominator blocks regressions'] = static function (TestRunner $t) use ($currentHead64, $nextHead64, $currentMapped64, $currentPhpPass64, $focusedPath64, $nonOverlap64): void {
    $rows = libsqlite_suite_denominator64_rows(4);
    $rows[1]['next_status'] = 'unmapped';

    $record = libsqlite_suite_denominator64_evidence()->releaseRunnerSuiteDenominatorFocusedAdmission($rows, $currentHead64, $nextHead64, $currentMapped64, $currentPhpPass64, $focusedPath64, libsqlite_suite_denominator64_output(), $nonOverlap64);

    $t->same('blocked', $record['status']);
    $t->same(['accepted-veryquick-baseline'], $record['regressed_units']);
    $t->true(in_array('countable-unit-regressed', array_column($record['blockers'], 'evidence'), true), 'Expected countability regression blocker');
};

$tests['current next64 suite denominator blocks under-threshold focused output'] = static function (TestRunner $t) use ($currentHead64, $nextHead64, $currentMapped64, $currentPhpPass64, $focusedPath64, $nonOverlap64): void {
    $record = libsqlite_suite_denominator64_evidence()->releaseRunnerSuiteDenominatorFocusedAdmission(
        libsqlite_suite_denominator64_rows(5),
        $currentHead64,
        $nextHead64,
        $currentMapped64,
        $currentPhpPass64,
        $focusedPath64,
        libsqlite_suite_denominator64_output(63),
        $nonOverlap64
    );

    $t->same('blocked', $record['status']);
    $t->same(0, $record['php_pass_delta']);
    $t->true(in_array('focused-php-pass-delta-below-minimum', array_column($record['blockers'], 'id'), true), 'Expected minimum delta blocker');
};

$tests['current next64 suite denominator blocks unfocused output'] = static function (TestRunner $t) use ($currentHead64, $nextHead64, $currentMapped64, $currentPhpPass64, $focusedPath64, $nonOverlap64): void {
    $record = libsqlite_suite_denominator64_evidence()->releaseRunnerSuiteDenominatorFocusedAdmission(
        libsqlite_suite_denominator64_rows(6),
        $currentHead64,
        $nextHead64,
        $currentMapped64,
        $currentPhpPass64,
        $focusedPath64,
        "1 test files, 64 assertions, 0 failures\n",
        $nonOverlap64
    );

    $t->same('blocked', $record['status']);
    $t->same('focused-php-pass-admission-blocked', $record['blockers'][0]['id']);
};

$tests['current next64 suite denominator rejects invalid setup'] = static function (TestRunner $t) use ($currentHead64, $nextHead64, $currentMapped64, $currentPhpPass64, $focusedPath64, $nonOverlap64): void {
    $evidence = libsqlite_suite_denominator64_evidence();

    $t->throws(InvalidArgumentException::class, static fn () => $evidence->releaseRunnerSuiteDenominatorFocusedAdmission([], $currentHead64, $nextHead64, $currentMapped64, $currentPhpPass64, $focusedPath64, libsqlite_suite_denominator64_output(), $nonOverlap64));
    $t->throws(InvalidArgumentException::class, static fn () => $evidence->releaseRunnerSuiteDenominatorFocusedAdmission(libsqlite_suite_denominator64_rows(7), '', $nextHead64, $currentMapped64, $currentPhpPass64, $focusedPath64, libsqlite_suite_denominator64_output(), $nonOverlap64));
    $t->throws(InvalidArgumentException::class, static fn () => $evidence->releaseRunnerSuiteDenominatorFocusedAdmission(libsqlite_suite_denominator64_rows(7), $currentHead64, $nextHead64, -1, $currentPhpPass64, $focusedPath64, libsqlite_suite_denominator64_output(), $nonOverlap64));
    $t->throws(InvalidArgumentException::class, static fn () => $evidence->releaseRunnerSuiteDenominatorFocusedAdmission(libsqlite_suite_denominator64_rows(7), $currentHead64, $nextHead64, $currentMapped64, $currentPhpPass64, $focusedPath64, libsqlite_suite_denominator64_output(), $nonOverlap64, 0));
};

return $tests;
