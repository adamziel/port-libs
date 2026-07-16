<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteUpstreamSuiteEvidence;

function libsqlite_suite_rebase_admission_evidence(): SQLiteUpstreamSuiteEvidence
{
    return SQLiteUpstreamSuiteEvidence::fromManifestPath(__DIR__ . '/../UPSTREAM_TEST_MANIFEST.json');
}

function libsqlite_suite_rebase_admission_output(int $passLines = 74, int $assertions = 296, int $failures = 0): string
{
    $lines = ['Focused test run: 1 selected test files (root lock skipped)'];
    for ($i = 1; $i <= $passLines; $i++) {
        $lines[] = sprintf('PASS current next74 release runner rebase admission case %02d', $i);
    }
    $lines[] = sprintf('1 test files, %d assertions, %d failures', $assertions, $failures);

    return implode("\n", $lines);
}

/**
 * @return list<array<string, mixed>>
 */
function libsqlite_suite_rebase_admission_rows(
    int $case = 1,
    string $launcherBase = '23caf4af795588a2d84150ed1585e33865ff2b76',
    string $sharedSource = '103fc00c42f1ff0580cae8a7768e4a3da0979c2d'
): array {
    $script = sprintf('current-next74-release-runner-rebase-%02d.test', $case);

    return [
        [
            'unit' => 'suite-release-runner-current-next74-rebased-artifact',
            'category' => 'release-runner-admission-rebase',
            'launcher_base_head' => $launcherBase,
            'shared_source_head' => $sharedSource,
            'current_status' => 'missing',
            'next_status' => 'admitted',
            'artifact_path' => 'lanes/libsqlite/notes/suite-release-runner-rebase-admission.md',
            'runner_command' => './testfixture ../libsqlite/test/testrunner.tcl --jobs 1 --stop-on-error ' . $script,
            'scripts' => [$script],
            'tests' => 1000 + $case,
            'errors' => 0,
            'current_tests' => 0,
            'next_tests' => 1000 + $case,
            'evidence' => 'current-next74 admits a rebased lane-local zero-error focused release-runner artifact against the launcher Base accepted HEAD while treating shared dashboard source as provenance only',
        ],
        [
            'unit' => 'suite-release-runner-current-next74-base-anchor',
            'category' => 'accepted-head-provenance',
            'launcher_base_head' => $launcherBase,
            'shared_source_head' => $sharedSource,
            'current_status' => 'countable',
            'next_status' => 'countable',
            'artifact_path' => 'lanes/libsqlite/notes/suite-release-runner-admission.md',
            'runner_command' => './testfixture ../libsqlite/test/testrunner.tcl --jobs 1 --stop-on-error pager.test wal.test',
            'scripts' => ['pager.test', 'wal.test'],
            'tests' => 329670,
            'errors' => 0,
            'current_tests' => 329670,
            'next_tests' => 329670,
            'evidence' => 'accepted base release-runner admission anchor stays preserved while current-next74 admits only the rebased lane-local artifact',
        ],
    ];
}

function libsqlite_suite_rebase_admission_record(
    array $rows,
    string $launcherBase = '23caf4af795588a2d84150ed1585e33865ff2b76',
    string $nextHead = 'suite-release-runner-rebase-admission',
    string $output = null,
    ?int $expected = 74,
    string $snapshot = ''
): array {
    return libsqlite_suite_rebase_admission_evidence()->suiteReleaseRunnerRebaseAdmission(
        $rows,
        464,
        28200,
        $launcherBase,
        $nextHead,
        'lanes/libsqlite/tests/SQLiteSuiteReleaseRunnerRebaseAdmissionTest.php',
        $output ?? libsqlite_suite_rebase_admission_output(),
        'current-next74 release-runner admission rebase avoids accepted current-next72/current-next69 suite admission, current-next68 denominator admission, batch68/69/72/73 runtime clusters, and queued suite73 overlap',
        $expected,
        $snapshot
    );
}

$tests = [];

foreach (range(1, 74) as $case) {
    $tests[sprintf('current next74 admits rebased release runner artifact case %02d', $case)] = static function (TestRunner $t) use ($case): void {
        $record = libsqlite_suite_rebase_admission_record(libsqlite_suite_rebase_admission_rows($case));

        $t->same('current-next74-release-runner-rebased-admitted', $record['status']);
        $t->same(true, $record['countable']);
        $t->same('23caf4af795588a2d84150ed1585e33865ff2b76', $record['launcher_base_head']);
        $t->same(464, $record['current_mapped']);
        $t->same(465, $record['next_mapped']);
        $t->same(1, $record['mapped_delta']);
        $t->same(74, $record['php_pass_delta']);
        $t->same(28274, $record['next_php_pass']);
        $t->same(2, $record['zero_error_artifact_count']);
        $t->same(false, $record['counts_release_parity']);
        $t->same(true, $record['counts_release_runner_admission_current_next74']);
        $t->contains(sprintf('current-next74-release-runner-rebase-%02d.test', $case), implode(',', $record['target_scripts']));
    };
}

$tests['current next74 records rebased categories scripts and provenance source'] = static function (TestRunner $t): void {
    $record = libsqlite_suite_rebase_admission_record(libsqlite_suite_rebase_admission_rows(3));

    $t->same(2, $record['category_count']);
    $t->same(['accepted-head-provenance' => 1, 'release-runner-admission-rebase' => 1], $record['categories']);
    $t->same(['suite-release-runner-current-next74-rebased-artifact'], $record['admitted_units']);
    $t->same(['suite-release-runner-current-next74-base-anchor'], $record['preserved_units']);
    $t->same(['current-next74-release-runner-rebase-03.test', 'pager.test', 'wal.test'], $record['target_scripts']);
    $t->same(['103fc00c42f1ff0580cae8a7768e4a3da0979c2d'], $record['shared_source_heads']);
    $t->same(1003, $record['tests_total_delta']);
};

$tests['current next74 preserves already countable rebased artifact rows'] = static function (TestRunner $t): void {
    $rows = libsqlite_suite_rebase_admission_rows();
    $rows[0]['current_status'] = 'countable';
    $rows[0]['current_tests'] = 1001;

    $record = libsqlite_suite_rebase_admission_record($rows);

    $t->same('current-next74-release-runner-rebased-preserved', $record['status']);
    $t->same(0, $record['mapped_delta']);
    $t->same(464, $record['next_mapped']);
    $t->same(74, $record['php_pass_delta']);
    $t->same(['suite-release-runner-current-next74-base-anchor', 'suite-release-runner-current-next74-rebased-artifact'], $record['preserved_units']);
};

$tests['current next74 blocks stale launcher base row'] = static function (TestRunner $t): void {
    $rows = libsqlite_suite_rebase_admission_rows(launcherBase: '0000000000000000000000000000000000000000');

    $record = libsqlite_suite_rebase_admission_record($rows);

    $t->same('blocked', $record['status']);
    $t->same(0, $record['mapped_delta']);
    $t->same(0, $record['php_pass_delta']);
    $t->same('launcher-base-head-mismatch', $record['blockers'][0]['id']);
};

$tests['current next74 ignores shared source drift when launcher base matches'] = static function (TestRunner $t): void {
    $record = libsqlite_suite_rebase_admission_record(
        libsqlite_suite_rebase_admission_rows(sharedSource: '5883f5e65ebfd2e9cf8c9acf617a2a818277909c')
    );

    $t->same('current-next74-release-runner-rebased-admitted', $record['status']);
    $t->same(['5883f5e65ebfd2e9cf8c9acf617a2a818277909c'], $record['shared_source_heads']);
    $t->same(1, $record['mapped_delta']);
};

$tests['current next74 blocks non lane local artifact path'] = static function (TestRunner $t): void {
    $rows = libsqlite_suite_rebase_admission_rows();
    $rows[0]['artifact_path'] = '/tmp/current-next74.log';

    $record = libsqlite_suite_rebase_admission_record($rows);

    $t->same('blocked', $record['status']);
    $t->contains('artifact-path-not-lane-local', implode('; ', array_column($record['blockers'], 'evidence')));
};

$tests['current next74 blocks missing guarded runner command'] = static function (TestRunner $t): void {
    $rows = libsqlite_suite_rebase_admission_rows();
    $rows[0]['runner_command'] = './testfixture current-next74-release-runner-rebase-01.test';

    $record = libsqlite_suite_rebase_admission_record($rows);

    $t->same('blocked', $record['status']);
    $t->contains('runner-command-missing', implode('; ', array_column($record['blockers'], 'evidence')));
};

$tests['current next74 blocks artifact errors'] = static function (TestRunner $t): void {
    $rows = libsqlite_suite_rebase_admission_rows();
    $rows[0]['errors'] = 1;

    $record = libsqlite_suite_rebase_admission_record($rows);

    $t->same('blocked', $record['status']);
    $t->contains('artifact-errors-not-zero', implode('; ', array_column($record['blockers'], 'evidence')));
};

$tests['current next74 blocks missing test scripts'] = static function (TestRunner $t): void {
    $rows = libsqlite_suite_rebase_admission_rows();
    $rows[0]['scripts'] = ['current-next74.txt'];

    $record = libsqlite_suite_rebase_admission_record($rows);

    $t->same('blocked', $record['status']);
    $t->contains('countable-artifact-missing-test-scripts', implode('; ', array_column($record['blockers'], 'evidence')));
};

$tests['current next74 blocks release parity claims'] = static function (TestRunner $t): void {
    $rows = libsqlite_suite_rebase_admission_rows();
    $rows[0]['counts_release_parity'] = true;

    $record = libsqlite_suite_rebase_admission_record($rows);

    $t->same('blocked', $record['status']);
    $t->same(false, $record['counts_release_parity']);
    $t->contains('release-parity-claim-not-allowed', implode('; ', array_column($record['blockers'], 'evidence')));
};

$tests['current next74 blocks duplicate units'] = static function (TestRunner $t): void {
    $rows = libsqlite_suite_rebase_admission_rows();
    $rows[] = $rows[0];

    $record = libsqlite_suite_rebase_admission_record($rows);

    $t->same('blocked', $record['status']);
    $t->contains('duplicate-release-runner-unit', implode('; ', array_column($record['blockers'], 'evidence')));
};

$tests['current next74 blocks active broad runner snapshots'] = static function (TestRunner $t): void {
    $record = libsqlite_suite_rebase_admission_record(
        libsqlite_suite_rebase_admission_rows(),
        snapshot: '321 ./testfixture ../libsqlite/test/testrunner.tcl --jobs 4 release'
    );

    $t->same('blocked', $record['status']);
    $t->same('blocked-active-runner', $record['active_runner_status']);
    $t->same(1, $record['active_runner_count']);
};

$tests['current next74 blocks pass line inflation'] = static function (TestRunner $t): void {
    $record = libsqlite_suite_rebase_admission_record(
        libsqlite_suite_rebase_admission_rows(),
        output: libsqlite_suite_rebase_admission_output(passLines: 12, assertions: 296),
        expected: 74
    );

    $t->same('blocked', $record['status']);
    $t->same(12, $record['php_pass_admission']['pass_lines_observed']);
    $t->same('focused-pass-delta-mismatch', $record['php_pass_admission']['blockers'][0]['id']);
};

$tests['current next74 blocks unfocused TestRunner output'] = static function (TestRunner $t): void {
    $record = libsqlite_suite_rebase_admission_record(
        libsqlite_suite_rebase_admission_rows(),
        output: "PASS current next74 unfocused\n1 test files, 1 assertions, 0 failures\n",
        expected: 1
    );

    $t->same('blocked', $record['status']);
    $t->same(false, $record['php_pass_admission']['focused_output_seen']);
};

$tests['current next74 rejects invalid setup'] = static function (TestRunner $t): void {
    $evidence = libsqlite_suite_rebase_admission_evidence();

    $t->throws(InvalidArgumentException::class, static fn () => $evidence->suiteReleaseRunnerRebaseAdmission(libsqlite_suite_rebase_admission_rows(), 464, 28200, '', 'next', 'lanes/libsqlite/tests/SQLiteSuiteReleaseRunnerRebaseAdmissionTest.php', libsqlite_suite_rebase_admission_output(), 'non-overlap', 74));
    $t->throws(InvalidArgumentException::class, static fn () => $evidence->suiteReleaseRunnerRebaseAdmission([], 464, 28200, '23caf4af795588a2d84150ed1585e33865ff2b76', 'next', 'lanes/libsqlite/tests/SQLiteSuiteReleaseRunnerRebaseAdmissionTest.php', libsqlite_suite_rebase_admission_output(), 'non-overlap', 74));
};

$tests['current next74 records dependency closure and next gate'] = static function (TestRunner $t): void {
    $record = libsqlite_suite_rebase_admission_record(libsqlite_suite_rebase_admission_rows());

    $t->contains('current-next74 release-runner rebase admission', $record['dependency_closure']);
    $t->contains('release/all parity remains gated', $record['next_gate']);
    $t->contains('avoids accepted current-next72/current-next69 suite admission', $record['non_overlap_note']);
};

return $tests;
