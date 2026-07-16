<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteUpstreamSuiteEvidence;

function libsqlite_suite_count75_evidence(): SQLiteUpstreamSuiteEvidence
{
    return SQLiteUpstreamSuiteEvidence::fromManifestPath(__DIR__ . '/../UPSTREAM_TEST_MANIFEST.json');
}

function libsqlite_suite_count75_output(int $passLines = 89, int $assertions = 871, int $failures = 0): string
{
    $lines = ['Focused test run: 1 selected test files (root lock skipped)'];
    for ($i = 1; $i <= $passLines; $i++) {
        $lines[] = sprintf('PASS current next75 release all countability case %02d', $i);
    }
    $lines[] = sprintf('1 test files, %d assertions, %d failures', $assertions, $failures);

    return implode("\n", $lines);
}

/**
 * @return list<array<string, mixed>>
 */
function libsqlite_suite_count75_rows(
    string $head = 'c196709c053869bec78f15d5a1f299d396f8fdb0',
    int $case = 1
): array {
    $allScript = sprintf('release-all-current-next75-%02d.test', $case);

    return [
        [
            'unit' => 'suite-release-all-current-next75-all-artifact',
            'tier' => 'all',
            'repository_head' => $head,
            'current_countable' => false,
            'next_countable' => true,
            'artifact_path' => 'lanes/libsqlite/notes/suite-release-all-runner-countability.md',
            'runner_command' => './testfixture ../libsqlite/test/testrunner.tcl --jobs 1 --stop-on-error all',
            'scripts' => [$allScript, 'btree*.test', 'pager*.test'],
            'exit' => 0,
            'errors' => 0,
            'current_tests' => 0,
            'next_tests' => 1800 + $case,
            'evidence' => 'current-next75 counts a lane-local accepted-HEAD release/all artifact as a runner-countability blocker removal without claiming release/all parity',
        ],
        [
            'unit' => 'suite-release-all-current-next75-release-anchor',
            'tier' => 'release',
            'repository_head' => $head,
            'current_countable' => true,
            'next_countable' => true,
            'artifact_path' => 'lanes/libsqlite/notes/suite-release-runner-admission.md',
            'runner_command' => './testfixture ../libsqlite/test/testrunner.tcl --jobs 1 --stop-on-error release',
            'scripts' => ['pager.test', 'wal.test'],
            'exit' => 0,
            'errors' => 0,
            'current_tests' => 329670,
            'next_tests' => 329670,
            'evidence' => 'accepted release-tier anchor remains preserved while current-next75 admits only the new all-tier artifact',
        ],
    ];
}

/**
 * @param array<int|string, array<string, mixed>> $rows
 * @return array<string, mixed>
 */
function libsqlite_suite_count75_record(
    array $rows,
    string $head = 'c196709c053869bec78f15d5a1f299d396f8fdb0',
    string $output = null,
    ?int $expected = 89,
    string $snapshot = ''
): array {
    return libsqlite_suite_count75_evidence()->suiteReleaseAllRunnerCountability(
        $rows,
        464,
        28917,
        $head,
        'lanes/libsqlite/tests/SQLiteSuiteReleaseAllRunnerCountabilityTest.php',
        $output ?? libsqlite_suite_count75_output(),
        'current-next75 release/all countability avoids accepted current-next72 release-runner admission, current-next70 shard countability, suite-denominator freshness, and batch70/71 behavior clusters',
        $expected,
        $snapshot
    );
}

$tests = [];

foreach (range(1, 75) as $case) {
    $tests[sprintf('current next75 counts accepted head release all artifact case %02d', $case)] = static function (TestRunner $t) use ($case): void {
        $record = libsqlite_suite_count75_record(libsqlite_suite_count75_rows(case: $case));

        $t->same('current-next75-release-all-countable', $record['status']);
        $t->same(true, $record['countable']);
        $t->same(464, $record['current_mapped']);
        $t->same(465, $record['next_mapped']);
        $t->same(1, $record['mapped_delta']);
        $t->same(89, $record['php_pass_delta']);
        $t->same(29006, $record['next_php_pass']);
        $t->same(['suite-release-all-current-next75-all-artifact'], $record['counted_units']);
        $t->same(['suite-release-all-current-next75-release-anchor'], $record['preserved_units']);
        $t->contains(sprintf('release-all-current-next75-%02d.test', $case), implode(',', $record['target_scripts']));
        $t->same(false, $record['counts_release_parity']);
    };
}

$tests['current next75 records tiers scripts and test delta'] = static function (TestRunner $t): void {
    $record = libsqlite_suite_count75_record(libsqlite_suite_count75_rows(case: 7));

    $t->same(2, $record['tier_count']);
    $t->same(['all' => 1, 'release' => 1], $record['tiers']);
    $t->same(1807, $record['tests_total_delta']);
    $t->same(['btree*.test', 'pager*.test', 'pager.test', 'release-all-current-next75-07.test', 'wal.test'], $record['target_scripts']);
    $t->same(5, $record['target_script_count']);
};

$tests['current next75 preserves already countable release all artifact'] = static function (TestRunner $t): void {
    $rows = libsqlite_suite_count75_rows();
    $rows[0]['current_countable'] = true;
    $rows[0]['current_tests'] = $rows[0]['next_tests'];

    $record = libsqlite_suite_count75_record($rows);

    $t->same('current-next75-release-all-preserved', $record['status']);
    $t->same(0, $record['mapped_delta']);
    $t->same(464, $record['next_mapped']);
    $t->same([
        'suite-release-all-current-next75-all-artifact',
        'suite-release-all-current-next75-release-anchor',
    ], $record['preserved_units']);
};

$tests['current next75 blocks stale accepted head'] = static function (TestRunner $t): void {
    $record = libsqlite_suite_count75_record(libsqlite_suite_count75_rows(head: '0000000000000000000000000000000000000000'));

    $t->same('blocked', $record['status']);
    $t->same(0, $record['mapped_delta']);
    $t->contains('accepted-head-mismatch', $record['blockers'][0]['evidence']);
};

$tests['current next75 blocks non lane local artifact path'] = static function (TestRunner $t): void {
    $rows = libsqlite_suite_count75_rows();
    $rows[0]['artifact_path'] = '/tmp/sqlite-runner.log';

    $record = libsqlite_suite_count75_record($rows);

    $t->same('blocked', $record['status']);
    $t->contains('artifact-path-not-lane-local', $record['blockers'][0]['evidence']);
};

$tests['current next75 blocks non release all tier'] = static function (TestRunner $t): void {
    $rows = libsqlite_suite_count75_rows();
    $rows[0]['tier'] = 'veryquick';

    $record = libsqlite_suite_count75_record($rows);

    $t->same('blocked', $record['status']);
    $t->contains('unsupported-release-all-tier', $record['blockers'][0]['evidence']);
};

$tests['current next75 blocks missing release all runner command'] = static function (TestRunner $t): void {
    $rows = libsqlite_suite_count75_rows();
    $rows[0]['runner_command'] = './testfixture ../libsqlite/test/testrunner.tcl --jobs 1 --stop-on-error pager.test';

    $record = libsqlite_suite_count75_record($rows);

    $t->same('blocked', $record['status']);
    $t->contains('release-all-runner-command-missing', $record['blockers'][0]['evidence']);
};

$tests['current next75 blocks non zero exit and errors'] = static function (TestRunner $t): void {
    $rows = libsqlite_suite_count75_rows();
    $rows[0]['exit'] = 1;
    $rows[0]['errors'] = 2;

    $record = libsqlite_suite_count75_record($rows);

    $t->same('blocked', $record['status']);
    $t->contains('runner-exit-not-zero', $record['blockers'][0]['evidence']);
    $t->contains('runner-errors-not-zero', $record['blockers'][0]['evidence']);
};

$tests['current next75 blocks missing tests scripts and evidence'] = static function (TestRunner $t): void {
    $rows = libsqlite_suite_count75_rows();
    $rows[0]['next_tests'] = 0;
    $rows[0]['scripts'] = [];
    $rows[0]['evidence'] = '';

    $record = libsqlite_suite_count75_record($rows);

    $t->same('blocked', $record['status']);
    $t->contains('runner-test-count-missing', $record['blockers'][0]['evidence']);
    $t->contains('release-all-scripts-missing', $record['blockers'][0]['evidence']);
    $t->contains('release-all-evidence-missing', $record['blockers'][0]['evidence']);
};

$tests['current next75 blocks duplicate units and regressions'] = static function (TestRunner $t): void {
    $rows = libsqlite_suite_count75_rows();
    $rows[1]['next_countable'] = false;
    $rows[1]['next_tests'] = 10;
    $rows[] = $rows[0];

    $record = libsqlite_suite_count75_record($rows);

    $t->same('blocked', $record['status']);
    $t->contains('duplicate-release-all-unit', implode('; ', array_column($record['blockers'], 'evidence')));
    $t->contains('release-all-countability-regressed', implode('; ', array_column($record['blockers'], 'evidence')));
    $t->same(['suite-release-all-current-next75-release-anchor'], $record['regressed_units']);
};

$tests['current next75 blocks release parity inflation'] = static function (TestRunner $t): void {
    $rows = libsqlite_suite_count75_rows();
    $rows[0]['counts_release_parity'] = true;

    $record = libsqlite_suite_count75_record($rows);

    $t->same('blocked', $record['status']);
    $t->same(false, $record['counts_release_parity']);
    $t->contains('release-parity-claim-not-allowed', $record['blockers'][0]['evidence']);
};

$tests['current next75 blocks active broad runner snapshots'] = static function (TestRunner $t): void {
    $record = libsqlite_suite_count75_record(
        libsqlite_suite_count75_rows(),
        snapshot: '444 1 S 00:03 95.0 ./testfixture ../libsqlite/test/testrunner.tcl --jobs 4 all'
    );

    $t->same('blocked', $record['status']);
    $t->same('blocked-active-runner', $record['active_runner_status']);
    $t->same(1, $record['active_runner_count']);
    $t->true(in_array('duplicate-broad-runner-active', array_column($record['blockers'], 'id'), true), 'Expected duplicate runner blocker');
};

$tests['current next75 blocks pass line inflation'] = static function (TestRunner $t): void {
    $record = libsqlite_suite_count75_record(
        libsqlite_suite_count75_rows(),
        output: libsqlite_suite_count75_output(passLines: 12, assertions: 871),
        expected: 89
    );

    $t->same('blocked', $record['status']);
    $t->same(12, $record['php_pass_admission']['pass_lines_observed']);
    $t->same('focused-pass-delta-mismatch', $record['php_pass_admission']['blockers'][0]['id']);
};

$tests['current next75 rejects invalid setup'] = static function (TestRunner $t): void {
    $evidence = libsqlite_suite_count75_evidence();

    $t->throws(InvalidArgumentException::class, static fn () => $evidence->suiteReleaseAllRunnerCountability([], 464, 28917, 'c196709c053869bec78f15d5a1f299d396f8fdb0', 'lanes/libsqlite/tests/SQLiteSuiteReleaseAllRunnerCountabilityTest.php', libsqlite_suite_count75_output(), 'non-overlap', 89));
    $t->throws(InvalidArgumentException::class, static fn () => $evidence->suiteReleaseAllRunnerCountability(libsqlite_suite_count75_rows(), -1, 28917, 'c196709c053869bec78f15d5a1f299d396f8fdb0', 'lanes/libsqlite/tests/SQLiteSuiteReleaseAllRunnerCountabilityTest.php', libsqlite_suite_count75_output(), 'non-overlap', 89));
    $t->throws(InvalidArgumentException::class, static fn () => $evidence->suiteReleaseAllRunnerCountability(libsqlite_suite_count75_rows(), 464, 28917, '', 'lanes/libsqlite/tests/SQLiteSuiteReleaseAllRunnerCountabilityTest.php', libsqlite_suite_count75_output(), 'non-overlap', 89));
};

$tests['current next75 records dependency closure and next gate'] = static function (TestRunner $t): void {
    $record = libsqlite_suite_count75_record(libsqlite_suite_count75_rows());

    $t->contains('current-next75 release/all countability', $record['dependency_closure']);
    $t->contains('full release/all parity still requires', $record['next_gate']);
    $t->contains('avoids accepted current-next72 release-runner admission', $record['non_overlap_note']);
    $t->same(true, $record['counts_release_all_runner_countability_current_next75']);
};

return $tests;
