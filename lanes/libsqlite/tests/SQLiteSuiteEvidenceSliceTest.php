<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteUpstreamSuiteEvidence;

function libsqlite_suite_evidence(): SQLiteUpstreamSuiteEvidence
{
    return SQLiteUpstreamSuiteEvidence::fromManifestPath(__DIR__ . '/../UPSTREAM_TEST_MANIFEST.json');
}

function libsqlite_suite_evidence_output(int $passLines = 12, int $assertions = 96, int $failures = 0): string
{
    $lines = ['Focused test run: 1 selected test files (root lock skipped)'];
    for ($i = 1; $i <= $passLines; $i++) {
        $lines[] = sprintf('PASS suite evidence case %02d', $i);
    }
    $lines[] = sprintf('1 test files, %d assertions, %d failures', $assertions, $failures);

    return implode("\n", $lines);
}

/**
 * @return list<array<string, mixed>>
 */
function libsqlite_suite_evidence_rows(
    string $head = 'c196709c053869bec78f15d5a1f299d396f8fdb0',
    int $case = 1
): array {
    return [
        [
            'unit' => 'suite-evidence-focused-artifact',
            'tier' => 'focused',
            'repository_head' => $head,
            'current_countable' => false,
            'next_countable' => true,
            'artifact_path' => 'lanes/libsqlite/notes/suite-evidence-suite-evidence.md',
            'runner_command' => './testfixture ../libsqlite/test/testrunner.tcl --jobs 1 --stop-on-error veryquick attach3.test wal2.test',
            'scripts' => [sprintf('attach-suite-evidence-%02d.test', $case), 'attach3.test', 'wal2.test'],
            'exit' => 0,
            'errors' => 0,
            'current_tests' => 0,
            'next_tests' => 700 + $case,
            'evidence' => 'suite-evidence records the next suite evidence slice after the previous focused slice without claiming release/all parity',
        ],
        [
            'unit' => 'suite-evidence-veryquick-baseline',
            'tier' => 'veryquick',
            'repository_head' => $head,
            'current_countable' => true,
            'next_countable' => true,
            'artifact_path' => 'lanes/libsqlite/UPSTREAM_TEST_MANIFEST.json',
            'runner_command' => './testfixture ../libsqlite/test/testrunner.tcl --jobs 1 --stop-on-error veryquick',
            'scripts' => ['veryquick*.test', 'main.test'],
            'exit' => 0,
            'errors' => 0,
            'current_tests' => 329670,
            'next_tests' => 329670,
            'evidence' => 'accepted veryquick zero-error baseline remains preserved for suite-evidence',
        ],
    ];
}

/**
 * @param array<int|string, array<string, mixed>> $rows
 * @return array<string, mixed>
 */
function libsqlite_suite_evidence_record(
    array $rows,
    string $head = 'c196709c053869bec78f15d5a1f299d396f8fdb0',
    string $output = null,
    ?int $expected = 12,
    string $snapshot = ''
): array {
    return libsqlite_suite_evidence()->suiteEvidenceSlice(
        $rows,
        465,
        29006,
        $head,
        'lanes/libsqlite/tests/SQLiteSuiteEvidenceSliceTest.php',
        $output ?? libsqlite_suite_evidence_output(),
        'suite evidence avoids release/all release/all countability, recent behavior behavior slices, and accepted veryquick manifest baseline',
        $expected,
        $snapshot
    );
}

return [
    'suite evidence counts one bounded suite evidence row' => static function (TestRunner $t): void {
        $record = libsqlite_suite_evidence_record(libsqlite_suite_evidence_rows(case: 7));

        $t->same('suite-evidence-countable', $record['status']);
        $t->same(true, $record['countable']);
        $t->same(465, $record['current_mapped']);
        $t->same(466, $record['next_mapped']);
        $t->same(1, $record['mapped_delta']);
        $t->same(12, $record['php_pass_delta']);
        $t->same(29018, $record['next_php_pass']);
        $t->same(['suite-evidence-focused-artifact'], $record['advanced_units']);
        $t->same(['suite-evidence-veryquick-baseline'], $record['preserved_units']);
        $t->same(false, $record['counts_release_parity']);
        $t->same(true, $record['counts_suite_evidence']);
    },
    'suite evidence records tiers scripts and test delta' => static function (TestRunner $t): void {
        $record = libsqlite_suite_evidence_record(libsqlite_suite_evidence_rows(case: 3));

        $t->same(['focused' => 1, 'veryquick' => 1], $record['tiers']);
        $t->same(2, $record['tier_count']);
        $t->same(703, $record['tests_total_delta']);
        $t->same([
            'attach-suite-evidence-03.test',
            'attach3.test',
            'main.test',
            'veryquick*.test',
            'wal2.test',
        ], $record['target_scripts']);
    },
    'suite evidence preserves already counted suite evidence' => static function (TestRunner $t): void {
        $rows = libsqlite_suite_evidence_rows();
        $rows[0]['current_countable'] = true;
        $rows[0]['current_tests'] = $rows[0]['next_tests'];

        $record = libsqlite_suite_evidence_record($rows);

        $t->same('suite-evidence-preserved', $record['status']);
        $t->same(0, $record['mapped_delta']);
        $t->same(465, $record['next_mapped']);
        $t->same([
            'suite-evidence-focused-artifact',
            'suite-evidence-veryquick-baseline',
        ], $record['preserved_units']);
    },
    'suite evidence blocks stale head and non local artifact' => static function (TestRunner $t): void {
        $rows = libsqlite_suite_evidence_rows(head: '0000000000000000000000000000000000000000');
        $rows[0]['artifact_path'] = '/tmp/sqlite-suite.log';

        $record = libsqlite_suite_evidence_record($rows);

        $t->same('blocked', $record['status']);
        $t->same(0, $record['mapped_delta']);
        $t->contains('accepted-head-mismatch', implode('; ', array_column($record['blockers'], 'evidence')));
        $t->contains('artifact-path-not-lane-local', implode('; ', array_column($record['blockers'], 'evidence')));
    },
    'suite evidence blocks bad runner evidence fields' => static function (TestRunner $t): void {
        $rows = libsqlite_suite_evidence_rows();
        $rows[0]['runner_command'] = 'php tools/run-tests.php';
        $rows[0]['exit'] = 1;
        $rows[0]['errors'] = 1;
        $rows[0]['next_tests'] = 0;
        $rows[0]['scripts'] = [];
        $rows[0]['evidence'] = '';

        $record = libsqlite_suite_evidence_record($rows);
        $evidence = implode('; ', array_column($record['blockers'], 'evidence'));

        $t->same('blocked', $record['status']);
        $t->contains('suite-runner-command-missing', $evidence);
        $t->contains('runner-exit-not-zero', $evidence);
        $t->contains('runner-errors-not-zero', $evidence);
        $t->contains('runner-test-count-missing', $evidence);
        $t->contains('suite-scripts-missing', $evidence);
        $t->contains('suite-evidence-missing', $evidence);
    },
    'suite evidence blocks duplicate regression parity and active runner' => static function (TestRunner $t): void {
        $rows = libsqlite_suite_evidence_rows();
        $rows[0]['counts_release_parity'] = true;
        $rows[1]['next_countable'] = false;
        $rows[] = $rows[0];

        $record = libsqlite_suite_evidence_record($rows, snapshot: '123 testfixture ../libsqlite/test/testrunner.tcl --jobs 4 all');
        $evidence = implode('; ', array_column($record['blockers'], 'evidence'));

        $t->same('blocked', $record['status']);
        $t->contains('release-parity-claim-not-allowed', $evidence);
        $t->contains('suite-evidence-countability-regressed', $evidence);
        $t->contains('duplicate-suite-evidence-unit', $evidence);
        $t->contains('active broad runner process', $evidence);
        $t->same(['suite-evidence-veryquick-baseline'], $record['regressed_units']);
    },
    'suite evidence blocks focused php admission mismatch' => static function (TestRunner $t): void {
        $record = libsqlite_suite_evidence_record(
            libsqlite_suite_evidence_rows(),
            output: libsqlite_suite_evidence_output(passLines: 11),
            expected: 12
        );

        $t->same('blocked', $record['status']);
        $t->contains('focused PHP PASS-line admission', $record['blockers'][0]['evidence']);
        $t->same(29006, $record['next_php_pass']);
    },
    'suite evidence rejects empty row list' => static function (TestRunner $t): void {
        $t->throws(InvalidArgumentException::class, static fn () => libsqlite_suite_evidence_record([]));
    },
];
