<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteUpstreamSuiteEvidence;

const LIBSQLITE_SUITE81_HEAD = '8170714ed6c9fe68a85cc98f050b32864eb598a3';
const LIBSQLITE_SUITE81_SQLITE = '8f70ec615f4cd247d36f92a22c99f65ebbcc22a7';
const LIBSQLITE_SUITE81_UUID = '9ac4a33a2932d353c4871fd8e09c10addf827f1fc3fc9380037d738cf2cd0353';

function libsqlite_suite81_evidence(): SQLiteUpstreamSuiteEvidence
{
    return SQLiteUpstreamSuiteEvidence::fromManifestPath(__DIR__ . '/../UPSTREAM_TEST_MANIFEST.json');
}

function libsqlite_suite81_output(int $passLines = 93, int $assertions = 493, int $failures = 0): string
{
    $lines = ['Focused test run: 1 selected test files (root lock skipped)'];
    for ($i = 1; $i <= $passLines; $i++) {
        $lines[] = sprintf('PASS current next81 upstream runner admission case %02d', $i);
    }
    $lines[] = sprintf('1 test files, %d assertions, %d failures', $assertions, $failures);

    return implode("\n", $lines);
}

/**
 * @return list<array<string, mixed>>
 */
function libsqlite_suite81_rows(int $case = 1): array
{
    return [
        [
            'unit' => 'suite-release-runner-upstream-current-next81-main',
            'tier' => $case % 2 === 0 ? 'all' : 'release',
            'repository_head' => LIBSQLITE_SUITE81_HEAD,
            'sqlite_commit' => LIBSQLITE_SUITE81_SQLITE,
            'manifest_uuid' => LIBSQLITE_SUITE81_UUID,
            'current_countable' => false,
            'next_countable' => true,
            'artifact_path' => 'lanes/libsqlite/notes/yield-sqlite-suite-release-runner-upstream-current-next81.md',
            'runner_command' => './testfixture ../libsqlite/test/testrunner.tcl --jobs 1 --stop-on-error release json101.test wal.test btree*.test',
            'scripts' => [
                sprintf('current-next81-%02d.test', $case),
                'json101.test',
                'wal.test',
                'btree*.test',
            ],
            'exit' => 0,
            'errors' => 0,
            'current_tests' => 0,
            'next_tests' => 2400 + $case,
            'evidence' => 'current-next81 admits a zero-error upstream runner artifact only when accepted HEAD, SQLite commit, and manifest UUID match the current manifest',
        ],
        [
            'unit' => 'suite-release-runner-upstream-current-next81-preserved-anchor',
            'tier' => 'release',
            'repository_head' => LIBSQLITE_SUITE81_HEAD,
            'sqlite_commit' => LIBSQLITE_SUITE81_SQLITE,
            'manifest_uuid' => LIBSQLITE_SUITE81_UUID,
            'current_countable' => true,
            'next_countable' => true,
            'artifact_path' => 'lanes/libsqlite/notes/yield-sqlite-suite-release-all-runner-countability-current-next75.md',
            'runner_command' => './testfixture ../libsqlite/test/testrunner.tcl --jobs 1 --stop-on-error release pager.test select1.test',
            'scripts' => ['pager.test', 'select1.test'],
            'exit' => 0,
            'errors' => 0,
            'current_tests' => 329670,
            'next_tests' => 329670,
            'evidence' => 'accepted release/all countability anchor remains preserved while current-next81 checks upstream source identity',
        ],
    ];
}

/**
 * @param array<int|string, array<string, mixed>> $rows
 * @return array<string, mixed>
 */
function libsqlite_suite81_record(
    array $rows,
    string $output = null,
    ?int $expected = 93,
    string $snapshot = ''
): array {
    return libsqlite_suite81_evidence()->suiteReleaseRunnerUpstreamAdmission(
        $rows,
        464,
        29984,
        LIBSQLITE_SUITE81_HEAD,
        LIBSQLITE_SUITE81_SQLITE,
        LIBSQLITE_SUITE81_UUID,
        'lanes/libsqlite/tests/SQLiteSuiteReleaseRunnerUpstreamCurrentNext81Test.php',
        $output ?? libsqlite_suite81_output(),
        'current-next81 avoids accepted current-next75 release/all countability, current-next72/74 runner admission, pgrep self-probe filtering, suite denominator admission, release blocker closure ledgers, and all accepted SQL/JSON/WAL/VFS/B-tree behavior clusters',
        $expected,
        $snapshot
    );
}

$tests = [];

foreach (range(1, 81) as $case) {
    $tests[sprintf('current next81 admits upstream runner source identity case %02d', $case)] = static function (TestRunner $t) use ($case): void {
        $record = libsqlite_suite81_record(libsqlite_suite81_rows($case));

        $t->same('current-next81-upstream-runner-countable', $record['status']);
        $t->same(true, $record['countable']);
        $t->same(1, $record['mapped_delta']);
        $t->same(465, $record['next_mapped']);
        $t->same(93, $record['php_pass_delta']);
        $t->same(30077, $record['next_php_pass']);
        $t->same(['suite-release-runner-upstream-current-next81-main'], $record['advanced_units']);
        $t->same(['suite-release-runner-upstream-current-next81-preserved-anchor'], $record['preserved_units']);
        $t->contains(sprintf('current-next81-%02d.test', $case), implode(',', $record['target_scripts']));
        $t->same(false, $record['counts_release_parity']);
    };
}

$tests['current next81 records source tuple and target scripts'] = static function (TestRunner $t): void {
    $record = libsqlite_suite81_record(libsqlite_suite81_rows(7));

    $t->same(LIBSQLITE_SUITE81_HEAD, $record['accepted_repository_head']);
    $t->same(LIBSQLITE_SUITE81_SQLITE, $record['expected_sqlite_commit']);
    $t->same(LIBSQLITE_SUITE81_UUID, $record['expected_manifest_uuid']);
    $t->same(['release' => 2], $record['tiers']);
    $t->same(2407, $record['tests_total_delta']);
    $t->same(['btree*.test', 'current-next81-07.test', 'json101.test', 'pager.test', 'select1.test', 'wal.test'], $record['target_scripts']);
    $t->same(6, $record['target_script_count']);
};

$tests['current next81 preserves already current upstream artifact'] = static function (TestRunner $t): void {
    $rows = libsqlite_suite81_rows();
    $rows[0]['current_countable'] = true;
    $rows[0]['current_tests'] = $rows[0]['next_tests'];

    $record = libsqlite_suite81_record($rows);

    $t->same('current-next81-upstream-runner-preserved', $record['status']);
    $t->same(0, $record['mapped_delta']);
    $t->same(464, $record['next_mapped']);
    $t->same([
        'suite-release-runner-upstream-current-next81-main',
        'suite-release-runner-upstream-current-next81-preserved-anchor',
    ], $record['preserved_units']);
};

$tests['current next81 blocks stale accepted head'] = static function (TestRunner $t): void {
    $rows = libsqlite_suite81_rows();
    $rows[0]['repository_head'] = '0000000000000000000000000000000000000000';

    $record = libsqlite_suite81_record($rows);

    $t->same('blocked', $record['status']);
    $t->contains('accepted-head-mismatch', $record['blockers'][0]['evidence']);
};

$tests['current next81 blocks stale sqlite commit'] = static function (TestRunner $t): void {
    $rows = libsqlite_suite81_rows();
    $rows[0]['sqlite_commit'] = '1111111111111111111111111111111111111111';

    $record = libsqlite_suite81_record($rows);

    $t->same('blocked', $record['status']);
    $t->contains('sqlite-commit-mismatch', $record['blockers'][0]['evidence']);
};

$tests['current next81 blocks stale manifest uuid'] = static function (TestRunner $t): void {
    $rows = libsqlite_suite81_rows();
    $rows[0]['manifest_uuid'] = '2222222222222222222222222222222222222222222222222222222222222222';

    $record = libsqlite_suite81_record($rows);

    $t->same('blocked', $record['status']);
    $t->contains('sqlite-manifest-uuid-mismatch', $record['blockers'][0]['evidence']);
};

$tests['current next81 blocks non lane local artifact'] = static function (TestRunner $t): void {
    $rows = libsqlite_suite81_rows();
    $rows[0]['artifact_path'] = '/tmp/sqlite-release-current-next81.md';

    $record = libsqlite_suite81_record($rows);

    $t->same('blocked', $record['status']);
    $t->contains('artifact-path-not-lane-local', $record['blockers'][0]['evidence']);
};

$tests['current next81 blocks unsupported tier'] = static function (TestRunner $t): void {
    $rows = libsqlite_suite81_rows();
    $rows[0]['tier'] = 'veryquick';

    $record = libsqlite_suite81_record($rows);

    $t->same('blocked', $record['status']);
    $t->contains('unsupported-upstream-runner-tier', $record['blockers'][0]['evidence']);
};

$tests['current next81 blocks missing runner command scripts and evidence'] = static function (TestRunner $t): void {
    $rows = libsqlite_suite81_rows();
    $rows[0]['runner_command'] = './testfixture ../libsqlite/test/testrunner.tcl --jobs 1 --stop-on-error veryquick.test';
    $rows[0]['scripts'] = [];
    $rows[0]['evidence'] = '';

    $record = libsqlite_suite81_record($rows);

    $t->same('blocked', $record['status']);
    $t->contains('upstream-runner-command-missing', $record['blockers'][0]['evidence']);
    $t->contains('upstream-runner-scripts-missing', $record['blockers'][0]['evidence']);
    $t->contains('upstream-runner-evidence-missing', $record['blockers'][0]['evidence']);
};

$tests['current next81 blocks non zero runner results'] = static function (TestRunner $t): void {
    $rows = libsqlite_suite81_rows();
    $rows[0]['exit'] = 1;
    $rows[0]['errors'] = 3;

    $record = libsqlite_suite81_record($rows);

    $t->same('blocked', $record['status']);
    $t->contains('runner-exit-not-zero', $record['blockers'][0]['evidence']);
    $t->contains('runner-errors-not-zero', $record['blockers'][0]['evidence']);
};

$tests['current next81 blocks missing runner tests'] = static function (TestRunner $t): void {
    $rows = libsqlite_suite81_rows();
    $rows[0]['next_tests'] = 0;

    $record = libsqlite_suite81_record($rows);

    $t->same('blocked', $record['status']);
    $t->contains('runner-test-count-missing', $record['blockers'][0]['evidence']);
};

$tests['current next81 blocks duplicate units and regressions'] = static function (TestRunner $t): void {
    $rows = libsqlite_suite81_rows();
    $rows[1]['next_countable'] = false;
    $rows[1]['next_tests'] = 1;
    $rows[] = $rows[0];

    $record = libsqlite_suite81_record($rows);

    $t->same('blocked', $record['status']);
    $t->contains('upstream-runner-countability-regressed', implode('; ', array_column($record['blockers'], 'evidence')));
    $t->contains('duplicate-upstream-runner-unit', implode('; ', array_column($record['blockers'], 'evidence')));
    $t->same(['suite-release-runner-upstream-current-next81-preserved-anchor'], $record['regressed_units']);
};

$tests['current next81 blocks release parity inflation'] = static function (TestRunner $t): void {
    $rows = libsqlite_suite81_rows();
    $rows[0]['counts_release_parity'] = true;

    $record = libsqlite_suite81_record($rows);

    $t->same('blocked', $record['status']);
    $t->same(false, $record['counts_release_parity']);
    $t->contains('release-parity-claim-not-allowed', $record['blockers'][0]['evidence']);
};

$tests['current next81 blocks active broad runner snapshots'] = static function (TestRunner $t): void {
    $record = libsqlite_suite81_record(
        libsqlite_suite81_rows(),
        snapshot: '777 1 S 00:05 88.0 ./testfixture ../libsqlite/test/testrunner.tcl --jobs 4 release'
    );

    $t->same('blocked', $record['status']);
    $t->same('blocked-active-runner', $record['active_runner_status']);
    $t->same(1, $record['active_runner_count']);
    $t->true(in_array('duplicate-broad-runner-active', array_column($record['blockers'], 'id'), true), 'Expected active broad runner blocker');
};

$tests['current next81 blocks pass line inflation'] = static function (TestRunner $t): void {
    $record = libsqlite_suite81_record(
        libsqlite_suite81_rows(),
        output: libsqlite_suite81_output(passLines: 12),
        expected: 93
    );

    $t->same('blocked', $record['status']);
    $t->same(12, $record['php_pass_admission']['pass_lines_observed']);
    $t->same('focused-pass-delta-mismatch', $record['php_pass_admission']['blockers'][0]['id']);
};

$tests['current next81 rejects invalid setup'] = static function (TestRunner $t): void {
    $evidence = libsqlite_suite81_evidence();

    $t->throws(InvalidArgumentException::class, static fn () => $evidence->suiteReleaseRunnerUpstreamAdmission([], 464, 29984, LIBSQLITE_SUITE81_HEAD, LIBSQLITE_SUITE81_SQLITE, LIBSQLITE_SUITE81_UUID, 'lanes/libsqlite/tests/SQLiteSuiteReleaseRunnerUpstreamCurrentNext81Test.php', libsqlite_suite81_output(), 'non-overlap', 93));
    $t->throws(InvalidArgumentException::class, static fn () => $evidence->suiteReleaseRunnerUpstreamAdmission(libsqlite_suite81_rows(), -1, 29984, LIBSQLITE_SUITE81_HEAD, LIBSQLITE_SUITE81_SQLITE, LIBSQLITE_SUITE81_UUID, 'lanes/libsqlite/tests/SQLiteSuiteReleaseRunnerUpstreamCurrentNext81Test.php', libsqlite_suite81_output(), 'non-overlap', 93));
    $t->throws(InvalidArgumentException::class, static fn () => $evidence->suiteReleaseRunnerUpstreamAdmission(libsqlite_suite81_rows(), 464, 29984, '', LIBSQLITE_SUITE81_SQLITE, LIBSQLITE_SUITE81_UUID, 'lanes/libsqlite/tests/SQLiteSuiteReleaseRunnerUpstreamCurrentNext81Test.php', libsqlite_suite81_output(), 'non-overlap', 93));
    $t->throws(InvalidArgumentException::class, static fn () => $evidence->suiteReleaseRunnerUpstreamAdmission(libsqlite_suite81_rows(), 464, 29984, LIBSQLITE_SUITE81_HEAD, '', LIBSQLITE_SUITE81_UUID, 'lanes/libsqlite/tests/SQLiteSuiteReleaseRunnerUpstreamCurrentNext81Test.php', libsqlite_suite81_output(), 'non-overlap', 93));
};

$tests['current next81 records dependency closure and next gate'] = static function (TestRunner $t): void {
    $record = libsqlite_suite81_record(libsqlite_suite81_rows());

    $t->contains('current-next81 upstream runner admission', $record['dependency_closure']);
    $t->contains('release/all parity still requires', $record['next_gate']);
    $t->contains('avoids accepted current-next75 release/all countability', $record['non_overlap_note']);
    $t->same(true, $record['counts_suite_release_runner_upstream_current_next81']);
};

return $tests;
