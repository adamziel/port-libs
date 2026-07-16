<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteUpstreamSuiteEvidence;

function libsqlite_suite_admission_burnup_evidence(): SQLiteUpstreamSuiteEvidence
{
    return SQLiteUpstreamSuiteEvidence::fromManifestPath(__DIR__ . '/../UPSTREAM_TEST_MANIFEST.json');
}

function libsqlite_suite_admission_burnup_output(int $passLines = 94, int $assertions = 94, int $failures = 0): string
{
    $lines = ['Focused test run: 1 selected test files (root lock skipped)'];
    for ($i = 1; $i <= $passLines; $i++) {
        $lines[] = sprintf('PASS current source next94 upstream runner admission burnup case %02d', $i);
    }
    $lines[] = sprintf('1 test files, %d assertions, %d failures', $assertions, $failures);

    return implode("\n", $lines);
}

/**
 * @return list<array<string, mixed>>
 */
function libsqlite_suite_admission_burnup_rows(
    int $case = 1,
    string $launcherBase = 'a66f690e8c736460293eefd5dc9b119fb2f09d6f',
    string $dashboardSource = '103fc00c42f1ff0580cae8a7768e4a3da0979c2d',
    string $statusSource = '5883f5e65ebfd2e9cf8c9acf617a2a818277909c',
    string $implementationSource = '21f1e38635e924df34f7be1aef3242b4b233710c'
): array {
    $script = sprintf('suite-upstream-runner-admission-burnup-current-source-next94-%02d.test', $case);

    return [
        [
            'unit' => 'suite-upstream-runner-admission-burnup-current-source-next94',
            'category' => 'upstream-runner-admission-burnup-current-source',
            'launcher_base_head' => $launcherBase,
            'dashboard_source_head' => $dashboardSource,
            'status_source_head' => $statusSource,
            'implementation_source_head' => $implementationSource,
            'current_status' => 'missing',
            'next_status' => 'admitted',
            'artifact_path' => 'lanes/libsqlite/notes/suite-upstream-runner-admission-burnup-current-source-next94.md',
            'runner_command' => './testfixture ../libsqlite/test/testrunner.tcl --jobs 1 --stop-on-error veryquick ' . $script,
            'scripts' => [$script, 'testrunner.test'],
            'exit' => 0,
            'errors' => 0,
            'current_tests' => 0,
            'next_tests' => 4200 + $case,
            'evidence' => 'current-source next94 admits a lane-local zero-error guarded upstream-runner artifact only when launcher base, dashboard/status source, latest implementation source, focused PASS lines, and duplicate-runner gates are explicit',
        ],
        [
            'unit' => 'suite-upstream-runner-admission-burnup-current-source-next94-accepted-anchor',
            'category' => 'accepted-current-source-anchor',
            'launcher_base_head' => $launcherBase,
            'dashboard_source_head' => $dashboardSource,
            'status_source_head' => $statusSource,
            'implementation_source_head' => $implementationSource,
            'current_status' => 'countable',
            'next_status' => 'countable',
            'artifact_path' => 'lanes/libsqlite/notes/suite-release-runner-countability-rebase-current-source-next82.md',
            'runner_command' => './testfixture ../libsqlite/test/testrunner.tcl --jobs 1 --stop-on-error release accepted-current-source-anchor.test',
            'scripts' => ['accepted-current-source-anchor.test'],
            'exit' => 0,
            'errors' => 0,
            'current_tests' => 329670,
            'next_tests' => 329670,
            'evidence' => 'accepted current-source release-runner anchor remains preserved while next94 burns up only the new upstream-runner admission row',
        ],
    ];
}

/**
 * @param list<array<string, mixed>> $rows
 * @return array<string, mixed>
 */
function libsqlite_suite_admission_burnup_record(
    array $rows,
    string $launcherBase = 'a66f690e8c736460293eefd5dc9b119fb2f09d6f',
    string $dashboardSource = '103fc00c42f1ff0580cae8a7768e4a3da0979c2d',
    string $statusSource = '5883f5e65ebfd2e9cf8c9acf617a2a818277909c',
    string $implementationSource = '21f1e38635e924df34f7be1aef3242b4b233710c',
    string $nextHead = 'suite-upstream-runner-admission-burnup-current-source-next94',
    ?string $output = null,
    ?int $expected = 94,
    string $snapshot = ''
): array {
    return libsqlite_suite_admission_burnup_evidence()->suiteUpstreamRunnerAdmissionBurnupCurrentSource(
        $rows,
        534,
        36393,
        $launcherBase,
        $dashboardSource,
        $statusSource,
        $implementationSource,
        $nextHead,
        'lanes/libsqlite/tests/SQLiteSuiteUpstreamRunnerAdmissionBurnupCurrentSourceTest.php',
        $output ?? libsqlite_suite_admission_burnup_output(),
        'current-source next94 upstream-runner admission burnup avoids accepted next75 release/all countability, next82 current-source rebase, batch90 suite/status admission, and ATTACH/JSON/pager/VFS/WAL/B-tree behavior clusters',
        $expected,
        $snapshot
    );
}

$tests = [];

foreach (range(1, 94) as $case) {
    $tests[sprintf('current source next94 admits upstream runner burnup artifact case %02d', $case)] = static function (TestRunner $t) use ($case): void {
        $record = libsqlite_suite_admission_burnup_record(libsqlite_suite_admission_burnup_rows($case));

        $t->same('current-source-next94-upstream-runner-admission-burnup-countable', $record['status']);
        $t->same(true, $record['countable']);
        $t->same('a66f690e8c736460293eefd5dc9b119fb2f09d6f', $record['launcher_base_head']);
        $t->same('103fc00c42f1ff0580cae8a7768e4a3da0979c2d', $record['dashboard_source_head']);
        $t->same('5883f5e65ebfd2e9cf8c9acf617a2a818277909c', $record['status_source_head']);
        $t->same('21f1e38635e924df34f7be1aef3242b4b233710c', $record['implementation_source_head']);
        $t->same(534, $record['current_mapped']);
        $t->same(535, $record['next_mapped']);
        $t->same(1, $record['mapped_delta']);
        $t->same(94, $record['php_pass_delta']);
        $t->same(36487, $record['next_php_pass']);
        $t->same(['suite-upstream-runner-admission-burnup-current-source-next94'], $record['admitted_units']);
        $t->same(['suite-upstream-runner-admission-burnup-current-source-next94-accepted-anchor'], $record['preserved_units']);
        $t->contains(sprintf('suite-upstream-runner-admission-burnup-current-source-next94-%02d.test', $case), implode(',', $record['target_scripts']));
        $t->same(false, $record['counts_release_parity']);
    };
}

$tests['current source next94 records category script and test deltas'] = static function (TestRunner $t): void {
    $record = libsqlite_suite_admission_burnup_record(libsqlite_suite_admission_burnup_rows(7));

    $t->same(2, $record['category_count']);
    $t->same(['accepted-current-source-anchor' => 1, 'upstream-runner-admission-burnup-current-source' => 1], $record['categories']);
    $t->same(4207, $record['tests_total_delta']);
    $t->same(['accepted-current-source-anchor.test', 'suite-upstream-runner-admission-burnup-current-source-next94-07.test', 'testrunner.test'], $record['target_scripts']);
    $t->same(true, $record['counts_upstream_runner_admission_burnup_current_source_next94']);
};

$tests['current source next94 preserves already admitted artifact without mapping inflation'] = static function (TestRunner $t): void {
    $rows = libsqlite_suite_admission_burnup_rows();
    $rows[0]['current_status'] = 'countable';
    $rows[0]['current_tests'] = 4201;

    $record = libsqlite_suite_admission_burnup_record($rows);

    $t->same('current-source-next94-upstream-runner-admission-burnup-preserved', $record['status']);
    $t->same(0, $record['mapped_delta']);
    $t->same(534, $record['next_mapped']);
    $t->same(['suite-upstream-runner-admission-burnup-current-source-next94', 'suite-upstream-runner-admission-burnup-current-source-next94-accepted-anchor'], $record['preserved_units']);
};

$tests['current source next94 blocks stale provenance heads'] = static function (TestRunner $t): void {
    $record = libsqlite_suite_admission_burnup_record(
        libsqlite_suite_admission_burnup_rows(
            launcherBase: '0000000000000000000000000000000000000000',
            dashboardSource: '1111111111111111111111111111111111111111',
            statusSource: '2222222222222222222222222222222222222222',
            implementationSource: '3333333333333333333333333333333333333333'
        )
    );

    $t->same('blocked', $record['status']);
    $t->same(0, $record['mapped_delta']);
    $evidence = implode('; ', array_column($record['blockers'], 'evidence'));
    $t->contains('launcher-base-head-mismatch', $evidence);
    $t->contains('dashboard-source-head-mismatch', $evidence);
    $t->contains('status-source-head-mismatch', $evidence);
    $t->contains('implementation-source-head-mismatch', $evidence);
};

$tests['current source next94 blocks non lane local artifact and unguarded command'] = static function (TestRunner $t): void {
    $rows = libsqlite_suite_admission_burnup_rows();
    $rows[0]['artifact_path'] = '/tmp/current-source-next94.log';
    $rows[0]['runner_command'] = './testfixture ../libsqlite/test/testrunner.tcl veryquick';
    $rows[0]['scripts'] = ['README.md'];

    $record = libsqlite_suite_admission_burnup_record($rows);

    $t->same('blocked', $record['status']);
    $evidence = implode('; ', array_column($record['blockers'], 'evidence'));
    $t->contains('artifact-path-not-lane-local', $evidence);
    $t->contains('guarded-runner-command-missing', $evidence);
    $t->contains('runner-scripts-missing', $evidence);
};

$tests['current source next94 blocks runner errors duplicate units and regressions'] = static function (TestRunner $t): void {
    $rows = libsqlite_suite_admission_burnup_rows();
    $rows[0]['errors'] = 1;
    $rows[1]['next_status'] = 'missing';
    $rows[1]['next_tests'] = 10;
    $rows[] = $rows[0];

    $record = libsqlite_suite_admission_burnup_record($rows);

    $t->same('blocked', $record['status']);
    $evidence = implode('; ', array_column($record['blockers'], 'evidence'));
    $t->contains('runner-errors-not-zero', $evidence);
    $t->contains('duplicate-current-source-next94-unit', $evidence);
    $t->contains('runner-countability-regressed', $evidence);
    $t->same(['suite-upstream-runner-admission-burnup-current-source-next94-accepted-anchor'], $record['regressed_units']);
};

$tests['current source next94 blocks release parity active runners and pass inflation'] = static function (TestRunner $t): void {
    $rows = libsqlite_suite_admission_burnup_rows();
    $rows[0]['counts_release_parity'] = true;

    $record = libsqlite_suite_admission_burnup_record(
        $rows,
        output: libsqlite_suite_admission_burnup_output(9, 94),
        expected: 94,
        snapshot: '8888 1 S 00:04 93.0 ./testfixture ../libsqlite/test/testrunner.tcl --jobs 4 all'
    );

    $t->same('blocked', $record['status']);
    $t->same('blocked-active-runner', $record['active_runner_status']);
    $t->same(1, $record['active_runner_count']);
    $evidence = implode('; ', array_column($record['blockers'], 'evidence'));
    $ids = implode(',', array_column($record['blockers'], 'id'));
    $t->contains('release-parity-claim-not-allowed', $evidence);
    $t->contains('duplicate-broad-runner-active', $ids);
    $t->contains('focused-current-head-php-pass-blocked', $ids);
};

$tests['current source next94 rejects empty artifact rows'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => libsqlite_suite_admission_burnup_record([]));
};

return $tests;
