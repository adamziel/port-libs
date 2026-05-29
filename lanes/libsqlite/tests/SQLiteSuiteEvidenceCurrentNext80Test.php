<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteUpstreamSuiteEvidence;

function libsqlite_suite_evidence80(): SQLiteUpstreamSuiteEvidence
{
    return SQLiteUpstreamSuiteEvidence::fromManifestPath(__DIR__ . '/../UPSTREAM_TEST_MANIFEST.json');
}

function libsqlite_suite_evidence80_output(int $passLines = 12, int $assertions = 112, int $failures = 0): string
{
    $lines = ['Focused test run: 1 selected test files (root lock skipped)'];
    for ($i = 1; $i <= $passLines; $i++) {
        $lines[] = sprintf('PASS current next80 suite evidence case %02d', $i);
    }
    $lines[] = sprintf('1 test files, %d assertions, %d failures', $assertions, $failures);

    return implode("\n", $lines);
}

/**
 * @return list<array<string, mixed>>
 */
function libsqlite_suite_evidence80_rows(
    string $head = 'c196709c053869bec78f15d5a1f299d396f8fdb0',
    int $case = 1
): array {
    return [
        [
            'unit' => 'suite-evidence-current-next80-focused-artifact',
            'tier' => 'focused',
            'repository_head' => $head,
            'current_countable' => false,
            'next_countable' => true,
            'artifact_path' => 'lanes/libsqlite/notes/suite-evidence-current-next80.md',
            'runner_command' => './testfixture ../libsqlite/test/testrunner.tcl --jobs 1 --stop-on-error veryquick attach3.test wal2.test',
            'scripts' => [sprintf('pager-current-next80-%02d.test', $case), 'attach3.test', 'wal2.test'],
            'exit' => 0,
            'errors' => 0,
            'current_tests' => 0,
            'next_tests' => 880 + $case,
            'evidence' => 'current-next80 records a bounded suite evidence slice after integrated next77/next78 without claiming release/all parity',
        ],
        [
            'unit' => 'suite-evidence-current-next80-next78-baseline',
            'tier' => 'focused',
            'repository_head' => $head,
            'current_countable' => true,
            'next_countable' => true,
            'artifact_path' => 'lanes/libsqlite/notes/suite-evidence-current-next78.md',
            'runner_command' => './testfixture ../libsqlite/test/testrunner.tcl --jobs 1 --stop-on-error veryquick attach3.test wal2.test',
            'scripts' => ['wal-current-next78-08.test', 'attach3.test', 'wal2.test'],
            'exit' => 0,
            'errors' => 0,
            'current_tests' => 788,
            'next_tests' => 788,
            'evidence' => 'integrated current-next78 suite evidence remains preserved for current-next80',
        ],
    ];
}

/**
 * @param array<int|string, array<string, mixed>> $rows
 * @return array<string, mixed>
 */
function libsqlite_suite_evidence80_record(
    array $rows,
    string $head = 'c196709c053869bec78f15d5a1f299d396f8fdb0',
    string $output = null,
    ?int $expected = 12,
    string $snapshot = ''
): array {
    return libsqlite_suite_evidence80()->suiteEvidenceSlice(
        $rows,
        467,
        29030,
        $head,
        'lanes/libsqlite/tests/SQLiteSuiteEvidenceCurrentNext80Test.php',
        $output ?? libsqlite_suite_evidence80_output(),
        'current-next80 suite evidence follows integrated current-next77/current-next78 and avoids current-next75 release/all countability and accepted veryquick manifest baseline',
        $expected,
        $snapshot
    );
}

return [
    'current next80 counts one bounded suite evidence row after next78' => static function (TestRunner $t): void {
        $record = libsqlite_suite_evidence80_record(libsqlite_suite_evidence80_rows(case: 10));

        $t->same('current-next80-suite-evidence-countable', $record['status']);
        $t->same(true, $record['countable']);
        $t->same(467, $record['current_mapped']);
        $t->same(468, $record['next_mapped']);
        $t->same(1, $record['mapped_delta']);
        $t->same(12, $record['php_pass_delta']);
        $t->same(29042, $record['next_php_pass']);
        $t->same(['suite-evidence-current-next80-focused-artifact'], $record['advanced_units']);
        $t->same(['suite-evidence-current-next80-next78-baseline'], $record['preserved_units']);
        $t->same(false, $record['counts_suite_evidence_current_next77']);
        $t->same(false, $record['counts_suite_evidence_current_next78']);
        $t->same(true, $record['counts_suite_evidence_current_next80']);
        $t->same(false, $record['counts_release_parity']);
    },
    'current next80 records tiers scripts and test delta' => static function (TestRunner $t): void {
        $record = libsqlite_suite_evidence80_record(libsqlite_suite_evidence80_rows(case: 6));

        $t->same(['focused' => 2], $record['tiers']);
        $t->same(1, $record['tier_count']);
        $t->same(886, $record['tests_total_delta']);
        $t->same([
            'attach3.test',
            'pager-current-next80-06.test',
            'wal-current-next78-08.test',
            'wal2.test',
        ], $record['target_scripts']);
    },
    'current next80 preserves already counted suite evidence' => static function (TestRunner $t): void {
        $rows = libsqlite_suite_evidence80_rows();
        $rows[0]['current_countable'] = true;
        $rows[0]['current_tests'] = $rows[0]['next_tests'];

        $record = libsqlite_suite_evidence80_record($rows);

        $t->same('current-next80-suite-evidence-preserved', $record['status']);
        $t->same(0, $record['mapped_delta']);
        $t->same(467, $record['next_mapped']);
        $t->same([
            'suite-evidence-current-next80-focused-artifact',
            'suite-evidence-current-next80-next78-baseline',
        ], $record['preserved_units']);
    },
    'current next80 blocks stale head and non local artifact' => static function (TestRunner $t): void {
        $rows = libsqlite_suite_evidence80_rows(head: '0000000000000000000000000000000000000000');
        $rows[0]['artifact_path'] = '/tmp/sqlite-suite.log';

        $record = libsqlite_suite_evidence80_record($rows);

        $t->same('blocked', $record['status']);
        $t->same(0, $record['mapped_delta']);
        $t->contains('accepted-head-mismatch', implode('; ', array_column($record['blockers'], 'evidence')));
        $t->contains('artifact-path-not-lane-local', implode('; ', array_column($record['blockers'], 'evidence')));
    },
    'current next80 blocks bad runner evidence fields' => static function (TestRunner $t): void {
        $rows = libsqlite_suite_evidence80_rows();
        $rows[0]['runner_command'] = 'php tools/run-tests.php';
        $rows[0]['exit'] = 1;
        $rows[0]['errors'] = 1;
        $rows[0]['next_tests'] = 0;
        $rows[0]['scripts'] = [];
        $rows[0]['evidence'] = '';

        $record = libsqlite_suite_evidence80_record($rows);
        $evidence = implode('; ', array_column($record['blockers'], 'evidence'));

        $t->same('blocked', $record['status']);
        $t->contains('suite-runner-command-missing', $evidence);
        $t->contains('runner-exit-not-zero', $evidence);
        $t->contains('runner-errors-not-zero', $evidence);
        $t->contains('runner-test-count-missing', $evidence);
        $t->contains('suite-scripts-missing', $evidence);
        $t->contains('suite-evidence-missing', $evidence);
    },
    'current next80 blocks duplicate regression parity and active runner' => static function (TestRunner $t): void {
        $rows = libsqlite_suite_evidence80_rows();
        $rows[0]['counts_release_parity'] = true;
        $rows[1]['next_countable'] = false;
        $rows[] = $rows[0];

        $record = libsqlite_suite_evidence80_record($rows, snapshot: '123 testfixture ../libsqlite/test/testrunner.tcl --jobs 4 all');
        $evidence = implode('; ', array_column($record['blockers'], 'evidence'));

        $t->same('blocked', $record['status']);
        $t->contains('release-parity-claim-not-allowed', $evidence);
        $t->contains('suite-evidence-countability-regressed', $evidence);
        $t->contains('duplicate-suite-evidence-unit', $evidence);
        $t->contains('active broad runner process', $evidence);
        $t->same(['suite-evidence-current-next80-next78-baseline'], $record['regressed_units']);
    },
    'current next80 blocks focused php admission mismatch' => static function (TestRunner $t): void {
        $record = libsqlite_suite_evidence80_record(
            libsqlite_suite_evidence80_rows(),
            output: libsqlite_suite_evidence80_output(passLines: 11),
            expected: 12
        );

        $t->same('blocked', $record['status']);
        $t->contains('focused PHP PASS-line admission', $record['blockers'][0]['evidence']);
        $t->same(29030, $record['next_php_pass']);
    },
    'current next80 rejects empty row list' => static function (TestRunner $t): void {
        $t->throws(InvalidArgumentException::class, static fn () => libsqlite_suite_evidence80_record([]));
    },
];
