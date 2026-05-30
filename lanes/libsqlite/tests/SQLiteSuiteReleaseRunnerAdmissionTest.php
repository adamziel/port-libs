<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteUpstreamSuiteEvidence;

function libsqlite_suite_admission_evidence(): SQLiteUpstreamSuiteEvidence
{
    return SQLiteUpstreamSuiteEvidence::fromManifestPath(__DIR__ . '/../UPSTREAM_TEST_MANIFEST.json');
}

function libsqlite_suite_admission_output(int $passLines = 72, int $assertions = 216, int $failures = 0): string
{
    $lines = ['Focused test run: 1 selected test files (root lock skipped)'];
    for ($i = 1; $i <= $passLines; $i++) {
        $lines[] = sprintf('PASS current next72 release runner admission case %02d', $i);
    }
    $lines[] = sprintf('1 test files, %d assertions, %d failures', $assertions, $failures);

    return implode("\n", $lines);
}

/**
 * @return list<array<string, mixed>>
 */
function libsqlite_suite_admission_rows(int $case = 1, string $currentHead = 'c1b3825e121841b3669ec7027e8adbacaebb6283', string $nextHead = 'suite-release-runner-admission'): array
{
    $script = sprintf('current-next72-admission-%02d.test', $case);

    return [
        [
            'unit' => 'suite-release-runner-current-next72-focused-artifact',
            'category' => 'release-runner-admission',
            'current_head' => $currentHead,
            'next_head' => $nextHead,
            'current_status' => 'missing',
            'next_status' => 'admitted',
            'artifact_path' => 'lanes/libsqlite/notes/suite-release-runner-admission.md',
            'runner_command' => './testfixture ../libsqlite/test/testrunner.tcl --jobs 1 --stop-on-error ' . $script,
            'scripts' => [$script],
            'tests' => 900 + $case,
            'errors' => 0,
            'current_tests' => 0,
            'next_tests' => 900 + $case,
            'evidence' => 'current-next72 admits a lane-local zero-error focused release-runner artifact for countability without claiming release/all parity',
        ],
        [
            'unit' => 'suite-release-runner-current-next72-accepted-anchor',
            'category' => 'accepted-head-provenance',
            'current_head' => $currentHead,
            'next_head' => $nextHead,
            'current_status' => 'countable',
            'next_status' => 'countable',
            'artifact_path' => 'lanes/libsqlite/notes/suite-denominator-current-next69.md',
            'runner_command' => './testfixture ../libsqlite/test/testrunner.tcl --jobs 1 --stop-on-error pager.test wal.test',
            'scripts' => ['pager.test', 'wal.test'],
            'tests' => 329670,
            'errors' => 0,
            'current_tests' => 329670,
            'next_tests' => 329670,
            'evidence' => 'accepted current-head focused runner evidence stays preserved while current-next72 admits only the new lane-local artifact',
        ],
    ];
}

/**
 * @param array<int|string, array<string, mixed>> $rows
 * @return array<string, mixed>
 */
function libsqlite_suite_admission_record(
    array $rows,
    string $currentHead = 'c1b3825e121841b3669ec7027e8adbacaebb6283',
    string $nextHead = 'suite-release-runner-admission',
    string $output = null,
    ?int $expected = 72,
    string $snapshot = ''
): array {
    return libsqlite_suite_admission_evidence()->suiteReleaseRunnerAdmission(
        $rows,
        464,
        26631,
        $currentHead,
        $nextHead,
        'lanes/libsqlite/tests/SQLiteSuiteReleaseRunnerAdmissionTest.php',
        $output ?? libsqlite_suite_admission_output(),
        'current-next72 release-runner admission avoids accepted current-next68/69 suite denominator freshness, release/all parity ledgers, batch68/69 behavior clusters, and queued ATTACH/JSON/pager/select/VFS/WAL handoffs',
        $expected,
        $snapshot
    );
}

$tests = [];

foreach (range(1, 72) as $case) {
    $tests[sprintf('current next72 admits release runner artifact case %02d', $case)] = static function (TestRunner $t) use ($case): void {
        $record = libsqlite_suite_admission_record(libsqlite_suite_admission_rows($case));

        $t->same('current-next72-release-runner-admitted', $record['status']);
        $t->same(true, $record['countable']);
        $t->same(464, $record['current_mapped']);
        $t->same(465, $record['next_mapped']);
        $t->same(1, $record['mapped_delta']);
        $t->same(72, $record['php_pass_delta']);
        $t->same(26703, $record['next_php_pass']);
        $t->same(2, $record['zero_error_artifact_count']);
        $t->same(false, $record['counts_release_parity']);
        $t->same(true, $record['counts_release_runner_admission_current_next72']);
        $t->contains(sprintf('current-next72-admission-%02d.test', $case), implode(',', $record['target_scripts']));
    };
}

$tests['current next72 records categories scripts and test delta'] = static function (TestRunner $t): void {
    $record = libsqlite_suite_admission_record(libsqlite_suite_admission_rows(3));

    $t->same(2, $record['category_count']);
    $t->same(['accepted-head-provenance' => 1, 'release-runner-admission' => 1], $record['categories']);
    $t->same(['suite-release-runner-current-next72-focused-artifact'], $record['admitted_units']);
    $t->same(['suite-release-runner-current-next72-accepted-anchor'], $record['preserved_units']);
    $t->same(['current-next72-admission-03.test', 'pager.test', 'wal.test'], $record['target_scripts']);
    $t->same(903, $record['tests_total_delta']);
};

$tests['current next72 preserves already countable artifact rows'] = static function (TestRunner $t): void {
    $rows = libsqlite_suite_admission_rows();
    $rows[0]['current_status'] = 'countable';
    $rows[0]['current_tests'] = 901;

    $record = libsqlite_suite_admission_record($rows);

    $t->same('current-next72-release-runner-preserved', $record['status']);
    $t->same(0, $record['mapped_delta']);
    $t->same(464, $record['next_mapped']);
    $t->same(72, $record['php_pass_delta']);
    $t->same(['suite-release-runner-current-next72-accepted-anchor', 'suite-release-runner-current-next72-focused-artifact'], $record['preserved_units']);
};

$tests['current next72 blocks stale current head'] = static function (TestRunner $t): void {
    $rows = libsqlite_suite_admission_rows(currentHead: '0000000000000000000000000000000000000000');

    $record = libsqlite_suite_admission_record($rows);

    $t->same('blocked', $record['status']);
    $t->same(0, $record['mapped_delta']);
    $t->same(0, $record['php_pass_delta']);
    $t->contains('current-head-mismatch', $record['blockers'][0]['evidence']);
};

$tests['current next72 blocks stale next head'] = static function (TestRunner $t): void {
    $rows = libsqlite_suite_admission_rows(nextHead: 'stale-next-head');

    $record = libsqlite_suite_admission_record($rows);

    $t->same('blocked', $record['status']);
    $t->contains('next-head-mismatch', $record['blockers'][0]['evidence']);
};

$tests['current next72 blocks non lane local artifact path'] = static function (TestRunner $t): void {
    $rows = libsqlite_suite_admission_rows();
    $rows[0]['artifact_path'] = '/tmp/release-runner.log';

    $record = libsqlite_suite_admission_record($rows);

    $t->same('blocked', $record['status']);
    $t->contains('artifact-path-not-lane-local', $record['blockers'][0]['evidence']);
};

$tests['current next72 blocks missing runner command'] = static function (TestRunner $t): void {
    $rows = libsqlite_suite_admission_rows();
    $rows[0]['runner_command'] = './testfixture current-next72-admission-01.test';

    $record = libsqlite_suite_admission_record($rows);

    $t->same('blocked', $record['status']);
    $t->contains('runner-command-missing', $record['blockers'][0]['evidence']);
};

$tests['current next72 blocks non zero artifact errors'] = static function (TestRunner $t): void {
    $rows = libsqlite_suite_admission_rows();
    $rows[0]['errors'] = 1;

    $record = libsqlite_suite_admission_record($rows);

    $t->same('blocked', $record['status']);
    $t->contains('artifact-errors-not-zero', $record['blockers'][0]['evidence']);
};

$tests['current next72 blocks missing test count'] = static function (TestRunner $t): void {
    $rows = libsqlite_suite_admission_rows();
    $rows[0]['tests'] = 0;
    $rows[0]['next_tests'] = 0;

    $record = libsqlite_suite_admission_record($rows);

    $t->same('blocked', $record['status']);
    $t->contains('artifact-tests-missing', $record['blockers'][0]['evidence']);
};

$tests['current next72 blocks missing scripts'] = static function (TestRunner $t): void {
    $rows = libsqlite_suite_admission_rows();
    $rows[0]['scripts'] = ['not-a-test.txt'];

    $record = libsqlite_suite_admission_record($rows);

    $t->same('blocked', $record['status']);
    $t->contains('countable-artifact-missing-test-scripts', $record['blockers'][0]['evidence']);
};

$tests['current next72 blocks missing evidence'] = static function (TestRunner $t): void {
    $rows = libsqlite_suite_admission_rows();
    $rows[0]['evidence'] = '';

    $record = libsqlite_suite_admission_record($rows);

    $t->same('blocked', $record['status']);
    $t->contains('countable-artifact-missing-evidence', $record['blockers'][0]['evidence']);
};

$tests['current next72 blocks release parity claims'] = static function (TestRunner $t): void {
    $rows = libsqlite_suite_admission_rows();
    $rows[0]['counts_release_parity'] = true;

    $record = libsqlite_suite_admission_record($rows);

    $t->same('blocked', $record['status']);
    $t->same(false, $record['counts_release_parity']);
    $t->contains('release-parity-claim-not-allowed', $record['blockers'][0]['evidence']);
};

$tests['current next72 blocks duplicate artifact units'] = static function (TestRunner $t): void {
    $rows = libsqlite_suite_admission_rows();
    $rows[] = $rows[0];

    $record = libsqlite_suite_admission_record($rows);

    $t->same('blocked', $record['status']);
    $t->contains('duplicate-release-runner-unit', implode('; ', array_column($record['blockers'], 'evidence')));
};

$tests['current next72 blocks artifact regressions'] = static function (TestRunner $t): void {
    $rows = libsqlite_suite_admission_rows();
    $rows[1]['next_status'] = 'missing';
    $rows[1]['next_tests'] = 10;

    $record = libsqlite_suite_admission_record($rows);

    $t->same('blocked', $record['status']);
    $t->same(['suite-release-runner-current-next72-accepted-anchor'], $record['regressed_units']);
    $t->contains('countable-artifact-regressed', $record['blockers'][0]['evidence']);
};

$tests['current next72 blocks active broad runner snapshots'] = static function (TestRunner $t): void {
    $record = libsqlite_suite_admission_record(
        libsqlite_suite_admission_rows(),
        snapshot: '321 ./testfixture ../libsqlite/test/testrunner.tcl --jobs 4 all'
    );

    $t->same('blocked', $record['status']);
    $t->same('blocked-active-runner', $record['active_runner_status']);
    $t->same(1, $record['active_runner_count']);
    $t->true(in_array('duplicate-broad-runner-active', array_column($record['blockers'], 'id'), true), 'Expected active runner blocker');
};

$tests['current next72 blocks pass line inflation'] = static function (TestRunner $t): void {
    $record = libsqlite_suite_admission_record(
        libsqlite_suite_admission_rows(),
        output: libsqlite_suite_admission_output(passLines: 12, assertions: 216),
        expected: 72
    );

    $t->same('blocked', $record['status']);
    $t->same(12, $record['php_pass_admission']['pass_lines_observed']);
    $t->same('focused-pass-delta-mismatch', $record['php_pass_admission']['blockers'][0]['id']);
};

$tests['current next72 blocks unfocused TestRunner output'] = static function (TestRunner $t): void {
    $record = libsqlite_suite_admission_record(
        libsqlite_suite_admission_rows(),
        output: "PASS current next72 unfocused\n1 test files, 1 assertions, 0 failures\n",
        expected: 1
    );

    $t->same('blocked', $record['status']);
    $t->same(false, $record['php_pass_admission']['focused_output_seen']);
    $t->true(in_array('missing-focused-testrunner-output', array_column($record['php_pass_admission']['blockers'], 'id'), true), 'Expected focused output blocker');
};

$tests['current next72 rejects invalid setup'] = static function (TestRunner $t): void {
    $evidence = libsqlite_suite_admission_evidence();

    $t->throws(InvalidArgumentException::class, static fn () => $evidence->suiteReleaseRunnerAdmission([], 464, 26631, 'c1b3825e121841b3669ec7027e8adbacaebb6283', 'next', 'lanes/libsqlite/tests/SQLiteSuiteReleaseRunnerAdmissionTest.php', libsqlite_suite_admission_output(), 'non-overlap', 72));
    $t->throws(InvalidArgumentException::class, static fn () => $evidence->suiteReleaseRunnerAdmission(libsqlite_suite_admission_rows(), -1, 26631, 'c1b3825e121841b3669ec7027e8adbacaebb6283', 'next', 'lanes/libsqlite/tests/SQLiteSuiteReleaseRunnerAdmissionTest.php', libsqlite_suite_admission_output(), 'non-overlap', 72));
    $t->throws(InvalidArgumentException::class, static fn () => $evidence->suiteReleaseRunnerAdmission(libsqlite_suite_admission_rows(), 464, 26631, '', 'next', 'lanes/libsqlite/tests/SQLiteSuiteReleaseRunnerAdmissionTest.php', libsqlite_suite_admission_output(), 'non-overlap', 72));
};

$tests['current next72 records dependency closure and next gate'] = static function (TestRunner $t): void {
    $record = libsqlite_suite_admission_record(libsqlite_suite_admission_rows());

    $t->contains('current-next72 release-runner admission', $record['dependency_closure']);
    $t->contains('release/all parity remains gated', $record['next_gate']);
    $t->contains('avoids accepted current-next68/69 suite denominator freshness', $record['non_overlap_note']);
};

return $tests;
