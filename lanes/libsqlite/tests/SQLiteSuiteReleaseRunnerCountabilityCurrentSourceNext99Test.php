<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteUpstreamSuiteEvidence;

function libsqlite_suite_next99_evidence(): SQLiteUpstreamSuiteEvidence
{
    return SQLiteUpstreamSuiteEvidence::fromManifestPath(__DIR__ . '/../UPSTREAM_TEST_MANIFEST.json');
}

function libsqlite_suite_next99_output(int $passLines = 99, int $assertions = 99, int $failures = 0): string
{
    $lines = ['Focused test run: 1 selected test files (root lock skipped)'];
    for ($i = 1; $i <= $passLines; $i++) {
        $lines[] = sprintf('PASS current source next99 release runner countability case %02d', $i);
    }
    $lines[] = sprintf('1 test files, %d assertions, %d failures', $assertions, $failures);

    return implode("\n", $lines);
}

/**
 * @return list<array<string, mixed>>
 */
function libsqlite_suite_next99_rows(
    int $case = 1,
    string $launcherBase = '796e75f2553d88aeff452968c875521a537dba2d',
    string $dashboardSource = '103fc00c42f1ff0580cae8a7768e4a3da0979c2d',
    string $statusSource = '5883f5e65ebfd2e9cf8c9acf617a2a818277909c',
    string $implementationSource = '21f1e38635e924df34f7be1aef3242b4b233710c'
): array {
    $script = sprintf('suite-release-runner-countability-current-source-next99-%02d.test', $case);

    return [
        [
            'unit' => 'suite-release-runner-countability-current-source-next99',
            'category' => 'release-runner-countability-current-source-next99',
            'launcher_base_head' => $launcherBase,
            'dashboard_source_head' => $dashboardSource,
            'status_source_head' => $statusSource,
            'implementation_source_head' => $implementationSource,
            'current_status' => 'missing',
            'next_status' => 'admitted',
            'artifact_path' => 'lanes/libsqlite/notes/yield-sqlite-suite-release-runner-countability-current-source-next99.md',
            'runner_command' => './testfixture ../libsqlite/test/testrunner.tcl --jobs 1 --stop-on-error release ' . $script,
            'scripts' => [$script, 'testrunner.test', 'pager.test', 'wal.test'],
            'exit' => 0,
            'errors' => 0,
            'current_tests' => 0,
            'next_tests' => 26014 + $case,
            'evidence' => 'current-source next99 admits one lane-local zero-error guarded release-runner artifact only when launcher base, dashboard/status source, latest implementation source, focused PASS lines, and duplicate-runner gates are explicit',
        ],
        [
            'unit' => 'suite-release-runner-countability-current-source-next99-accepted-anchor',
            'category' => 'accepted-current-source-anchor',
            'launcher_base_head' => $launcherBase,
            'dashboard_source_head' => $dashboardSource,
            'status_source_head' => $statusSource,
            'implementation_source_head' => $implementationSource,
            'current_status' => 'countable',
            'next_status' => 'countable',
            'artifact_path' => 'lanes/libsqlite/notes/suite-release-runner-countability-rebase-current-source-next82.md',
            'runner_command' => './testfixture ../libsqlite/test/testrunner.tcl --jobs 1 --stop-on-error release accepted-current-source-anchor.test',
            'scripts' => ['accepted-current-source-anchor.test', 'testrunner.test'],
            'exit' => 0,
            'errors' => 0,
            'current_tests' => 329670,
            'next_tests' => 329670,
            'evidence' => 'accepted release-runner current-source anchor remains preserved while next99 admits only the new guarded release-runner countability row',
        ],
    ];
}

/**
 * @param list<array<string, mixed>> $rows
 * @return array<string, mixed>
 */
function libsqlite_suite_next99_record(
    array $rows,
    string $launcherBase = '796e75f2553d88aeff452968c875521a537dba2d',
    string $dashboardSource = '103fc00c42f1ff0580cae8a7768e4a3da0979c2d',
    string $statusSource = '5883f5e65ebfd2e9cf8c9acf617a2a818277909c',
    string $implementationSource = '21f1e38635e924df34f7be1aef3242b4b233710c',
    string $nextHead = 'suite-release-runner-countability-current-source-next99',
    ?string $output = null,
    ?int $expected = 99,
    string $snapshot = ''
): array {
    return libsqlite_suite_next99_evidence()->releaseRunnerCountabilityCurrentSource(
        $rows,
        568,
        38278,
        $launcherBase,
        $dashboardSource,
        $statusSource,
        $implementationSource,
        $nextHead,
        'lanes/libsqlite/tests/SQLiteSuiteReleaseRunnerCountabilityCurrentSourceNext99Test.php',
        $output ?? libsqlite_suite_next99_output(),
        'current-source next99 release-runner countability avoids accepted next75 release/all countability, next82 current-source rebase, next93 directory split, next94 admission burnup, and batch68-94 ATTACH/JSON/pager/VFS/WAL/B-tree behavior clusters',
        $expected,
        $snapshot
    );
}

$tests = [];

foreach (range(1, 99) as $case) {
    $tests[sprintf('current source next99 admits release runner countability artifact case %02d', $case)] = static function (TestRunner $t) use ($case): void {
        $record = libsqlite_suite_next99_record(libsqlite_suite_next99_rows($case));

        $t->same('current-source-next99-release-runner-countability-countable', $record['status']);
        $t->same(true, $record['countable']);
        $t->same('796e75f2553d88aeff452968c875521a537dba2d', $record['launcher_base_head']);
        $t->same('103fc00c42f1ff0580cae8a7768e4a3da0979c2d', $record['dashboard_source_head']);
        $t->same('5883f5e65ebfd2e9cf8c9acf617a2a818277909c', $record['status_source_head']);
        $t->same('21f1e38635e924df34f7be1aef3242b4b233710c', $record['implementation_source_head']);
        $t->same(568, $record['current_mapped']);
        $t->same(569, $record['next_mapped']);
        $t->same(1, $record['mapped_delta']);
        $t->same(99, $record['php_pass_delta']);
        $t->same(38377, $record['next_php_pass']);
        $t->same(['suite-release-runner-countability-current-source-next99'], $record['admitted_units']);
        $t->same(['suite-release-runner-countability-current-source-next99-accepted-anchor'], $record['preserved_units']);
        $t->contains(sprintf('suite-release-runner-countability-current-source-next99-%02d.test', $case), implode(',', $record['target_scripts']));
        $t->same(true, $record['counts_release_runner_countability_current_source_next99']);
        $t->same(false, $record['counts_release_parity']);
    };
}

$tests['current source next99 records release scripts categories and test deltas'] = static function (TestRunner $t): void {
    $record = libsqlite_suite_next99_record(libsqlite_suite_next99_rows(7));

    $t->same(2, $record['category_count']);
    $t->same(['accepted-current-source-anchor' => 1, 'release-runner-countability-current-source-next99' => 1], $record['categories']);
    $t->same(26021, $record['tests_total_delta']);
    $t->same(['accepted-current-source-anchor.test', 'pager.test', 'suite-release-runner-countability-current-source-next99-07.test', 'testrunner.test', 'wal.test'], $record['target_scripts']);
};

$tests['current source next99 preserves already admitted release artifact without mapping inflation'] = static function (TestRunner $t): void {
    $rows = libsqlite_suite_next99_rows();
    $rows[0]['current_status'] = 'countable';
    $rows[0]['current_tests'] = 26015;

    $record = libsqlite_suite_next99_record($rows);

    $t->same('current-source-next99-release-runner-countability-preserved', $record['status']);
    $t->same(0, $record['mapped_delta']);
    $t->same(568, $record['next_mapped']);
    $t->same(['suite-release-runner-countability-current-source-next99', 'suite-release-runner-countability-current-source-next99-accepted-anchor'], $record['preserved_units']);
};

$tests['current source next99 blocks stale provenance heads'] = static function (TestRunner $t): void {
    $record = libsqlite_suite_next99_record(
        libsqlite_suite_next99_rows(
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

$tests['current source next99 blocks non lane local artifact and unguarded command'] = static function (TestRunner $t): void {
    $rows = libsqlite_suite_next99_rows();
    $rows[0]['artifact_path'] = '/tmp/current-source-next99.log';
    $rows[0]['runner_command'] = './testfixture ../libsqlite/test/testrunner.tcl release';
    $rows[0]['scripts'] = ['README.md'];

    $record = libsqlite_suite_next99_record($rows);

    $t->same('blocked', $record['status']);
    $evidence = implode('; ', array_column($record['blockers'], 'evidence'));
    $t->contains('artifact-path-not-lane-local', $evidence);
    $t->contains('guarded-runner-command-missing', $evidence);
    $t->contains('runner-scripts-missing', $evidence);
    $t->contains('guarded release-runner artifacts', $evidence);
};

$tests['current source next99 blocks veryquick artifacts from release countability'] = static function (TestRunner $t): void {
    $rows = libsqlite_suite_next99_rows();
    $rows[0]['runner_command'] = './testfixture ../libsqlite/test/testrunner.tcl --jobs 1 --stop-on-error veryquick suite-release-runner-countability-current-source-next99-01.test';

    $record = libsqlite_suite_next99_record($rows);

    $t->same('blocked', $record['status']);
    $t->contains('current-source-next99-release-runner-command-blocked', implode(',', array_column($record['blockers'], 'id')));
};

$tests['current source next99 blocks runner errors duplicate units and regressions'] = static function (TestRunner $t): void {
    $rows = libsqlite_suite_next99_rows();
    $rows[0]['errors'] = 1;
    $rows[1]['next_status'] = 'missing';
    $rows[1]['next_tests'] = 10;
    $rows[] = $rows[0];

    $record = libsqlite_suite_next99_record($rows);

    $t->same('blocked', $record['status']);
    $evidence = implode('; ', array_column($record['blockers'], 'evidence'));
    $t->contains('runner-errors-not-zero', $evidence);
    $t->contains('duplicate-current-source-next94-unit', $evidence);
    $t->contains('runner-countability-regressed', $evidence);
    $t->same(['suite-release-runner-countability-current-source-next99-accepted-anchor'], $record['regressed_units']);
};

$tests['current source next99 blocks release parity active runners and pass inflation'] = static function (TestRunner $t): void {
    $rows = libsqlite_suite_next99_rows();
    $rows[0]['counts_release_parity'] = true;

    $record = libsqlite_suite_next99_record(
        $rows,
        output: libsqlite_suite_next99_output(9, 99),
        expected: 99,
        snapshot: '9999 1 S 00:04 93.0 ./testfixture ../libsqlite/test/testrunner.tcl --jobs 4 all'
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

$tests['current source next99 rejects empty artifact rows'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => libsqlite_suite_next99_record([]));
};

return $tests;
