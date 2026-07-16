<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteUpstreamSuiteEvidence;

function libsqlite_suite_runner73_evidence(): SQLiteUpstreamSuiteEvidence
{
    return SQLiteUpstreamSuiteEvidence::fromManifestPath(__DIR__ . '/../UPSTREAM_TEST_MANIFEST.json');
}

function libsqlite_suite_runner73_output(int $passLines = 73, int $assertions = 360, int $failures = 0, int $selected = 1, int $summaryFiles = 1): string
{
    $lines = [sprintf('Focused test run: %d selected test files (root lock skipped)', $selected)];
    for ($i = 1; $i <= $passLines; $i++) {
        $lines[] = sprintf('PASS current next73 runner artifact-pair case %02d', $i);
    }
    $lines[] = sprintf('%d test files, %d assertions, %d failures', $summaryFiles, $assertions, $failures);

    return implode("\n", $lines);
}

/**
 * @return list<array<string, mixed>>
 */
function libsqlite_suite_runner73_rows(int $case = 1, string $head = 'c1b3825e121841b3669ec7027e8adbacaebb6283'): array
{
    $script = sprintf('current-next73-runner-%02d.test', $case);
    $tests = 600 + $case;

    return [
        [
            'unit' => 'current-next73-focused-runner-' . $case,
            'category' => 'focused-runner-artifact-pair',
            'run_id' => 'sqlite-focused-current-next73-' . $case,
            'source_head' => $head,
            'current_countable' => false,
            'next_countable' => true,
            'current_tests' => 0,
            'next_tests' => $tests,
            'audit_tests' => $tests,
            'log_tests' => $tests,
            'audit_errors' => 0,
            'log_errors' => 0,
            'scripts' => [$script],
            'audit_path' => 'lanes/libsqlite/notes/yield-sqlite-suite-runner-current-next73.md',
            'log_path' => 'lanes/libsqlite/fixtures/suite-runner-current-next73.log',
            'runner_command' => './testfixture ../libsqlite/test/testrunner.tcl --jobs 1 --stop-on-error ' . $script,
            'artifact_status' => 'focused-pass',
            'evidence' => 'current-next73 admits one lane-local focused runner audit/log pair with matching zero-error parsed counts',
        ],
        [
            'unit' => 'current-next73-accepted-runner-anchor',
            'category' => 'accepted-current-runner-anchor',
            'run_id' => 'sqlite-accepted-current-anchor-69',
            'source_head' => $head,
            'current_countable' => true,
            'next_countable' => true,
            'current_tests' => 329670,
            'next_tests' => 329670,
            'audit_tests' => 329670,
            'log_tests' => 329670,
            'audit_errors' => 0,
            'log_errors' => 0,
            'scripts' => ['veryquick.test'],
            'audit_path' => 'lanes/libsqlite/notes/yield-sqlite-suite-runner-current-next73.md',
            'log_path' => 'lanes/libsqlite/fixtures/suite-runner-current-next73.log',
            'runner_command' => './testfixture ../libsqlite/test/testrunner.tcl --jobs 1 --stop-on-error veryquick.test',
            'artifact_status' => 'preserved-current-head',
            'evidence' => 'accepted current runner anchor remains preserved while the current-next73 row admits only one focused artifact pair',
        ],
    ];
}

/**
 * @param array<int|string, array<string, mixed>> $rows
 * @return array<string, mixed>
 */
function libsqlite_suite_runner73_record(array $rows, string $output = null, ?int $expected = 73, string $accepted = 'c1b3825e121841b3669ec7027e8adbacaebb6283', string $evidence = 'c1b3825e121841b3669ec7027e8adbacaebb6283', string $snapshot = ''): array
{
    return libsqlite_suite_runner73_evidence()->suiteDenominatorRunnerAdmission(
        $rows,
        464,
        26631,
        $accepted,
        $evidence,
        'lanes/libsqlite/tests/SQLiteSuiteDenominatorRunnerCurrentNext73Test.php',
        $output ?? libsqlite_suite_runner73_output(),
        'current-next73 runner artifact-pair admission avoids current-next65/68/69 denominator ledgers, accepted-head suite-denominator admission, release/all parity closure, active-runner pgrep filtering, and SQL/JSON/WAL/B-tree/VFS runtime clusters',
        $expected,
        $snapshot
    );
}

$tests = [];

foreach (range(1, 73) as $case) {
    $tests[sprintf('current next73 admits runner artifact pair case %02d', $case)] = static function (TestRunner $t) use ($case): void {
        $record = libsqlite_suite_runner73_record(libsqlite_suite_runner73_rows($case));

        $t->same('current-next73-runner-artifact-pair-countable', $record['status']);
        $t->same(true, $record['countable']);
        $t->same(0, $record['mapped_delta']);
        $t->same(464, $record['next_mapped']);
        $t->same(73, $record['php_pass_delta']);
        $t->same(26704, $record['next_php_pass']);
        $t->same(1, $record['runner_countable_delta']);
        $t->same(2, $record['next_runner_countable_count']);
        $t->same(2, $record['parsed_audit_row_count']);
        $t->same(2, $record['parsed_log_row_count']);
        $t->same(['current-next73-focused-runner-' . $case], $record['advanced_units']);
        $t->same(['current-next73-accepted-runner-anchor'], $record['preserved_units']);
        $t->contains(sprintf('current-next73-runner-%02d.test', $case), implode(',', $record['target_scripts']));
        $t->same(0, $record['blocker_count']);
    };
}

$tests['current next73 preserves runner countability when no row advances'] = static function (TestRunner $t): void {
    $rows = libsqlite_suite_runner73_rows();
    $rows[0]['current_countable'] = true;
    $rows[0]['current_tests'] = $rows[0]['next_tests'];

    $record = libsqlite_suite_runner73_record($rows, libsqlite_suite_runner73_output(5, 20), 5);

    $t->same('current-next73-runner-artifact-pair-preserved', $record['status']);
    $t->same(0, $record['runner_countable_delta']);
    $t->same(5, $record['php_pass_delta']);
    $t->same(26636, $record['next_php_pass']);
};

$tests['current next73 records categories scripts and test deltas'] = static function (TestRunner $t): void {
    $rows = libsqlite_suite_runner73_rows(2);
    $rows[] = [
        'unit' => 'current-next73-json-runner',
        'category' => 'json-focused-runner-artifact-pair',
        'run_id' => 'sqlite-focused-json-current-next73',
        'source_head' => 'c1b3825e121841b3669ec7027e8adbacaebb6283',
        'current_countable' => false,
        'next_countable' => true,
        'current_tests' => 0,
        'next_tests' => 44,
        'audit_tests' => 44,
        'log_tests' => 44,
        'audit_errors' => 0,
        'log_errors' => 0,
        'scripts' => ['json101.test', 'json102.test'],
        'audit_path' => 'lanes/libsqlite/notes/yield-sqlite-suite-runner-current-next73.md',
        'log_path' => 'lanes/libsqlite/fixtures/suite-runner-current-next73.log',
        'runner_command' => './testfixture ../libsqlite/test/testrunner.tcl --jobs 1 --stop-on-error json101.test json102.test',
        'artifact_status' => 'focused-pass',
        'evidence' => 'second focused runner row proves current-next73 category and script aggregation',
    ];

    $record = libsqlite_suite_runner73_record($rows, libsqlite_suite_runner73_output(6, 22), 6);

    $t->same('current-next73-runner-artifact-pair-countable', $record['status']);
    $t->same(2, $record['runner_countable_delta']);
    $t->same(['accepted-current-runner-anchor' => 1, 'focused-runner-artifact-pair' => 1, 'json-focused-runner-artifact-pair' => 1], $record['categories']);
    $t->same(['current-next73-runner-02.test', 'json101.test', 'json102.test', 'veryquick.test'], $record['target_scripts']);
    $t->same(602 + 44, $record['tests_total_delta']);
};

$tests['current next73 blocks stale source head'] = static function (TestRunner $t): void {
    $rows = libsqlite_suite_runner73_rows();
    $rows[0]['source_head'] = '1111111111111111111111111111111111111111';

    $record = libsqlite_suite_runner73_record($rows);

    $t->same('blocked', $record['status']);
    $t->same(0, $record['runner_countable_delta']);
    $t->same(['current-next73-focused-runner-1'], $record['blocked_units']);
    $t->contains('source-head-mismatch', $record['blockers'][0]['evidence']);
};

$tests['current next73 blocks stale focused evidence head'] = static function (TestRunner $t): void {
    $record = libsqlite_suite_runner73_record(libsqlite_suite_runner73_rows(), evidence: '2222222222222222222222222222222222222222');

    $t->same('blocked', $record['status']);
    $t->same(0, $record['php_pass_delta']);
    $t->same('repository-head-mismatch', $record['php_pass_admission']['blockers'][0]['id']);
    $t->true(in_array('focused-current-head-php-pass-blocked', array_column($record['blockers'], 'id'), true), 'Expected focused current-head blocker');
};

$tests['current next73 blocks duplicate run ids'] = static function (TestRunner $t): void {
    $rows = libsqlite_suite_runner73_rows();
    $rows[] = $rows[0];

    $record = libsqlite_suite_runner73_record($rows);

    $t->same('blocked', $record['status']);
    $t->contains('duplicate-run-id', implode('; ', array_column($record['blockers'], 'evidence')));
};

$tests['current next73 blocks non lane local artifact paths'] = static function (TestRunner $t): void {
    $rows = libsqlite_suite_runner73_rows();
    $rows[0]['audit_path'] = '/tmp/sqlite-runner.md';
    $rows[0]['log_path'] = 'lanes/libsqlite/notes/not-a-log.md';

    $record = libsqlite_suite_runner73_record($rows);

    $t->same('blocked', $record['status']);
    $t->contains('audit-path-not-lane-local', $record['blockers'][0]['evidence']);
    $t->contains('log-path-not-lane-local', $record['blockers'][0]['evidence']);
};

$tests['current next73 blocks missing runner command'] = static function (TestRunner $t): void {
    $rows = libsqlite_suite_runner73_rows();
    $rows[0]['runner_command'] = './testfixture current-next73-runner-01.test';

    $record = libsqlite_suite_runner73_record($rows);

    $t->same('blocked', $record['status']);
    $t->contains('runner-command-missing', $record['blockers'][0]['evidence']);
};

$tests['current next73 blocks mismatched audit and log counts'] = static function (TestRunner $t): void {
    $rows = libsqlite_suite_runner73_rows();
    $rows[0]['log_tests'] = 9;

    $record = libsqlite_suite_runner73_record($rows);

    $t->same('blocked', $record['status']);
    $t->same(['current-next73-focused-runner-1'], $record['regressed_units']);
    $t->contains('audit-log-test-count-mismatch', $record['blockers'][0]['evidence']);
};

$tests['current next73 blocks runner artifact errors'] = static function (TestRunner $t): void {
    $rows = libsqlite_suite_runner73_rows();
    $rows[0]['audit_errors'] = 1;

    $record = libsqlite_suite_runner73_record($rows);

    $t->same('blocked', $record['status']);
    $t->contains('runner-artifact-has-errors', $record['blockers'][0]['evidence']);
};

$tests['current next73 blocks countability regression'] = static function (TestRunner $t): void {
    $rows = libsqlite_suite_runner73_rows();
    $rows[1]['next_countable'] = false;
    $rows[1]['next_tests'] = 10;

    $record = libsqlite_suite_runner73_record($rows);

    $t->same('blocked', $record['status']);
    $t->same(['current-next73-accepted-runner-anchor'], $record['regressed_units']);
    $t->contains('runner-countability-regressed', $record['blockers'][0]['evidence']);
};

$tests['current next73 blocks active broad runner'] = static function (TestRunner $t): void {
    $record = libsqlite_suite_runner73_record(
        libsqlite_suite_runner73_rows(),
        snapshot: "900 1 S 00:31 0.1 ./testfixture ../src/test/testrunner.tcl --jobs 2 --stop-on-error release\n"
    );

    $t->same('blocked', $record['status']);
    $t->same('blocked-active-runner', $record['active_runner_status']);
    $t->same(1, $record['active_runner_count']);
    $t->true(in_array('duplicate-broad-runner-active', array_column($record['blockers'], 'id'), true), 'Expected duplicate broad-runner blocker');
};

$tests['current next73 blocks pass delta inflation'] = static function (TestRunner $t): void {
    $record = libsqlite_suite_runner73_record(
        libsqlite_suite_runner73_rows(),
        output: libsqlite_suite_runner73_output(passLines: 8, assertions: 42),
        expected: 73
    );

    $t->same('blocked', $record['status']);
    $t->same(8, $record['php_pass_admission']['pass_lines_observed']);
    $t->same('focused-pass-delta-mismatch', $record['php_pass_admission']['blockers'][0]['id']);
};

$tests['current next73 blocks unfocused output'] = static function (TestRunner $t): void {
    $record = libsqlite_suite_runner73_record(
        libsqlite_suite_runner73_rows(),
        output: "PASS current next73 unfocused\n1 test files, 10 assertions, 0 failures\n",
        expected: 1
    );

    $t->same('blocked', $record['status']);
    $t->same(false, $record['php_pass_admission']['focused_output_seen']);
    $t->true(in_array('missing-focused-testrunner-output', array_column($record['php_pass_admission']['blockers'], 'id'), true), 'Expected focused output blocker');
};

$tests['current next73 blocks release parity claim'] = static function (TestRunner $t): void {
    $rows = libsqlite_suite_runner73_rows();
    $rows[0]['counts_release_parity'] = true;

    $record = libsqlite_suite_runner73_record($rows);

    $t->same('blocked', $record['status']);
    $t->same(false, $record['counts_release_parity']);
    $t->contains('release-parity-claim-not-allowed', $record['blockers'][0]['evidence']);
};

$tests['current next73 rejects empty rows'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => libsqlite_suite_runner73_record([]));
};

$tests['current next73 rejects negative mapped baseline'] = static function (TestRunner $t): void {
    $t->throws(
        InvalidArgumentException::class,
        static fn () => libsqlite_suite_runner73_evidence()->suiteDenominatorRunnerAdmission(
            libsqlite_suite_runner73_rows(),
            -1,
            26631,
            'c1b3825e121841b3669ec7027e8adbacaebb6283',
            'c1b3825e121841b3669ec7027e8adbacaebb6283',
            'lanes/libsqlite/tests/SQLiteSuiteDenominatorRunnerCurrentNext73Test.php',
            libsqlite_suite_runner73_output(),
            'current-next73 non-overlap',
            73
        )
    );
};

$tests['current next73 records dependency closure and next gate'] = static function (TestRunner $t): void {
    $record = libsqlite_suite_runner73_record(libsqlite_suite_runner73_rows());

    $t->contains('current-next73 runner denominator admission', $record['dependency_closure']);
    $t->contains('audit/log pairs', $record['next_gate']);
    $t->contains('release/all parity remains unclaimed', $record['next_gate']);
    $t->contains('avoids current-next65/68/69 denominator ledgers', $record['non_overlap_note']);
};

return $tests;
