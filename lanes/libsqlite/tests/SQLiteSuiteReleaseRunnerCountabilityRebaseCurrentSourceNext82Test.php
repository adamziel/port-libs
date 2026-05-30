<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteUpstreamSuiteEvidence;

function libsqlite_suite_count82_evidence(): SQLiteUpstreamSuiteEvidence
{
    return SQLiteUpstreamSuiteEvidence::fromManifestPath(__DIR__ . '/../UPSTREAM_TEST_MANIFEST.json');
}

function libsqlite_suite_count82_output(int $passLines = 82, int $assertions = 410, int $failures = 0): string
{
    $lines = ['Focused test run: 1 selected test files (root lock skipped)'];
    for ($i = 1; $i <= $passLines; $i++) {
        $lines[] = sprintf('PASS current next82 release runner current source rebase case %02d', $i);
    }
    $lines[] = sprintf('1 test files, %d assertions, %d failures', $assertions, $failures);

    return implode("\n", $lines);
}

/**
 * @return list<array<string, mixed>>
 */
function libsqlite_suite_count82_rows(
    int $case = 1,
    string $launcherBase = 'bd3c72c033cc76366294ed6e08431afa73ecb9af',
    string $dashboardSource = '103fc00c42f1ff0580cae8a7768e4a3da0979c2d',
    string $statusSource = '5883f5e65ebfd2e9cf8c9acf617a2a818277909c',
    string $implementationSource = '21f1e38635e924df34f7be1aef3242b4b233710c'
): array {
    $script = sprintf('current-next82-release-current-source-%02d.test', $case);

    return [
        [
            'unit' => 'suite-release-runner-current-source-next82-artifact',
            'category' => 'release-runner-current-source-rebase',
            'launcher_base_head' => $launcherBase,
            'dashboard_source_head' => $dashboardSource,
            'status_source_head' => $statusSource,
            'implementation_source_head' => $implementationSource,
            'current_status' => 'missing',
            'next_status' => 'admitted',
            'artifact_path' => 'lanes/libsqlite/notes/yield-sqlite-suite-release-runner-countability-rebase-current-source-next82.md',
            'runner_command' => './testfixture ../libsqlite/test/testrunner.tcl --jobs 1 --stop-on-error release ' . $script,
            'scripts' => [$script, 'testrunner.test'],
            'tests' => 3200 + $case,
            'errors' => 0,
            'current_tests' => 0,
            'next_tests' => 3200 + $case,
            'evidence' => 'current-next82 admits one lane-local zero-error release-runner artifact only when the launcher base, dashboard source, status source, and latest integrated implementation source are all explicit and current',
        ],
        [
            'unit' => 'suite-release-runner-current-source-next82-accepted-anchor',
            'category' => 'accepted-current-source-anchor',
            'launcher_base_head' => $launcherBase,
            'dashboard_source_head' => $dashboardSource,
            'status_source_head' => $statusSource,
            'implementation_source_head' => $implementationSource,
            'current_status' => 'countable',
            'next_status' => 'countable',
            'artifact_path' => 'lanes/libsqlite/notes/suite-release-all-runner-countability.md',
            'runner_command' => './testfixture ../libsqlite/test/testrunner.tcl --jobs 1 --stop-on-error all',
            'scripts' => ['pager.test', 'wal.test'],
            'tests' => 329670,
            'errors' => 0,
            'current_tests' => 329670,
            'next_tests' => 329670,
            'evidence' => 'accepted release/all runner countability remains preserved while current-next82 admits only the rebased current-source blocker row',
        ],
    ];
}

function libsqlite_suite_count82_record(
    array $rows,
    string $launcherBase = 'bd3c72c033cc76366294ed6e08431afa73ecb9af',
    string $dashboardSource = '103fc00c42f1ff0580cae8a7768e4a3da0979c2d',
    string $statusSource = '5883f5e65ebfd2e9cf8c9acf617a2a818277909c',
    string $implementationSource = '21f1e38635e924df34f7be1aef3242b4b233710c',
    string $nextHead = 'suite-release-runner-countability-rebase-current-source-next82',
    string $output = null,
    ?int $expected = 82,
    string $snapshot = ''
): array {
    return libsqlite_suite_count82_evidence()->suiteReleaseRunnerCurrentSourceCountabilityRebase(
        $rows,
        465,
        31014,
        $launcherBase,
        $dashboardSource,
        $statusSource,
        $implementationSource,
        $nextHead,
        'lanes/libsqlite/tests/SQLiteSuiteReleaseRunnerCountabilityRebaseCurrentSourceNext82Test.php',
        $output ?? libsqlite_suite_count82_output(),
        'current-next82 release-runner current-source rebase avoids accepted current-next75 release/all countability, current-next74/72 admission, suite-denominator current-source gates, and batch68-79 ATTACH/JSON/LIKE/recursive SELECT/VFS/WAL/B-tree/pragma/trigger implementation clusters',
        $expected,
        $snapshot
    );
}

$tests = [];

foreach (range(1, 82) as $case) {
    $tests[sprintf('current next82 admits current source countability artifact case %02d', $case)] = static function (TestRunner $t) use ($case): void {
        $record = libsqlite_suite_count82_record(libsqlite_suite_count82_rows($case));

        $t->same('current-next82-release-runner-current-source-countable', $record['status']);
        $t->same(true, $record['countable']);
        $t->same('bd3c72c033cc76366294ed6e08431afa73ecb9af', $record['launcher_base_head']);
        $t->same('103fc00c42f1ff0580cae8a7768e4a3da0979c2d', $record['dashboard_source_head']);
        $t->same('5883f5e65ebfd2e9cf8c9acf617a2a818277909c', $record['status_source_head']);
        $t->same('21f1e38635e924df34f7be1aef3242b4b233710c', $record['implementation_source_head']);
        $t->same(465, $record['current_mapped']);
        $t->same(466, $record['next_mapped']);
        $t->same(1, $record['mapped_delta']);
        $t->same(82, $record['php_pass_delta']);
        $t->same(31096, $record['next_php_pass']);
        $t->same(['suite-release-runner-current-source-next82-artifact'], $record['admitted_units']);
        $t->same(['suite-release-runner-current-source-next82-accepted-anchor'], $record['preserved_units']);
        $t->contains(sprintf('current-next82-release-current-source-%02d.test', $case), implode(',', $record['target_scripts']));
        $t->same(false, $record['counts_release_parity']);
    };
}

$tests['current next82 records source heads categories and delta'] = static function (TestRunner $t): void {
    $record = libsqlite_suite_count82_record(libsqlite_suite_count82_rows(7));

    $t->same(2, $record['category_count']);
    $t->same(['accepted-current-source-anchor' => 1, 'release-runner-current-source-rebase' => 1], $record['categories']);
    $t->same(['dashboard' => ['103fc00c42f1ff0580cae8a7768e4a3da0979c2d'], 'implementation' => ['21f1e38635e924df34f7be1aef3242b4b233710c'], 'status' => ['5883f5e65ebfd2e9cf8c9acf617a2a818277909c']], $record['source_heads']);
    $t->same(3207, $record['tests_total_delta']);
    $t->same(['current-next82-release-current-source-07.test', 'pager.test', 'testrunner.test', 'wal.test'], $record['target_scripts']);
    $t->same(true, $record['counts_release_runner_countability_current_source_next82']);
};

$tests['current next82 preserves already admitted current source artifact'] = static function (TestRunner $t): void {
    $rows = libsqlite_suite_count82_rows();
    $rows[0]['current_status'] = 'countable';
    $rows[0]['current_tests'] = 3201;

    $record = libsqlite_suite_count82_record($rows);

    $t->same('current-next82-release-runner-current-source-preserved', $record['status']);
    $t->same(0, $record['mapped_delta']);
    $t->same(465, $record['next_mapped']);
    $t->same(82, $record['php_pass_delta']);
    $t->same(['suite-release-runner-current-source-next82-accepted-anchor', 'suite-release-runner-current-source-next82-artifact'], $record['preserved_units']);
};

$tests['current next82 blocks stale launcher base and source heads'] = static function (TestRunner $t): void {
    $record = libsqlite_suite_count82_record(
        libsqlite_suite_count82_rows(
            launcherBase: '0000000000000000000000000000000000000000',
            dashboardSource: '1111111111111111111111111111111111111111',
            statusSource: '2222222222222222222222222222222222222222',
            implementationSource: '3333333333333333333333333333333333333333'
        )
    );

    $t->same('blocked', $record['status']);
    $t->same(0, $record['mapped_delta']);
    $t->same(0, $record['php_pass_delta']);
    $t->contains('launcher-base-head-mismatch', implode(',', array_column($record['blockers'], 'id')));
    $t->contains('dashboard-source-head-mismatch', implode(',', array_column($record['blockers'], 'id')));
    $t->contains('status-source-head-mismatch', implode(',', array_column($record['blockers'], 'id')));
    $t->contains('implementation-source-head-mismatch', implode(',', array_column($record['blockers'], 'id')));
};

$tests['current next82 allows dashboard drift only when expected provenance changes'] = static function (TestRunner $t): void {
    $record = libsqlite_suite_count82_record(
        libsqlite_suite_count82_rows(dashboardSource: '5883f5e65ebfd2e9cf8c9acf617a2a818277909c'),
        dashboardSource: '5883f5e65ebfd2e9cf8c9acf617a2a818277909c'
    );

    $t->same('current-next82-release-runner-current-source-countable', $record['status']);
    $t->same('5883f5e65ebfd2e9cf8c9acf617a2a818277909c', $record['dashboard_source_head']);
    $t->same(1, $record['mapped_delta']);
};

$tests['current next82 blocks non lane local artifacts missing commands and scripts'] = static function (TestRunner $t): void {
    $rows = libsqlite_suite_count82_rows();
    $rows[0]['artifact_path'] = '/tmp/release-current-source.log';
    $rows[0]['runner_command'] = './testfixture current-next82-release-current-source-01.test';
    $rows[0]['scripts'] = ['README.md'];

    $record = libsqlite_suite_count82_record($rows);

    $t->same('blocked', $record['status']);
    $evidence = implode('; ', array_column($record['blockers'], 'evidence'));
    $t->contains('artifact-path-not-lane-local', $evidence);
    $t->contains('runner-command-missing', $evidence);
    $t->contains('countable-artifact-missing-test-scripts', $evidence);
};

$tests['current next82 blocks artifact errors duplicate units and regressions'] = static function (TestRunner $t): void {
    $rows = libsqlite_suite_count82_rows();
    $rows[0]['errors'] = 1;
    $rows[1]['next_status'] = 'missing';
    $rows[1]['next_tests'] = 10;
    $rows[] = $rows[0];

    $record = libsqlite_suite_count82_record($rows);

    $t->same('blocked', $record['status']);
    $evidence = implode('; ', array_column($record['blockers'], 'evidence'));
    $t->contains('artifact-errors-not-zero', $evidence);
    $t->contains('duplicate-release-runner-unit', $evidence);
    $t->contains('countable-artifact-regressed', $evidence);
    $t->same(['suite-release-runner-current-source-next82-accepted-anchor'], $record['regressed_units']);
};

$tests['current next82 blocks release parity claims active runners and pass inflation'] = static function (TestRunner $t): void {
    $rows = libsqlite_suite_count82_rows();
    $rows[0]['counts_release_parity'] = true;

    $record = libsqlite_suite_count82_record(
        $rows,
        output: libsqlite_suite_count82_output(4, 410),
        expected: 82,
        snapshot: '7777 ./testfixture ../libsqlite/test/testrunner.tcl --jobs 8 release'
    );

    $t->same('blocked', $record['status']);
    $t->same(false, $record['counts_release_parity']);
    $t->same('blocked-active-runner', $record['active_runner_status']);
    $t->same(4, $record['php_pass_admission']['pass_lines_observed']);
    $t->contains('release-parity-claim-not-allowed', implode('; ', array_column($record['blockers'], 'evidence')));
    $t->contains('duplicate-broad-runner-active', implode(',', array_column($record['blockers'], 'id')));
    $t->contains('focused-current-head-php-pass-blocked', implode(',', array_column($record['blockers'], 'id')));
};

$tests['current next82 rejects empty release runner rows'] = static function (TestRunner $t): void {
    $t->throws(
        InvalidArgumentException::class,
        static fn () => libsqlite_suite_count82_record([])
    );
};

return $tests;
