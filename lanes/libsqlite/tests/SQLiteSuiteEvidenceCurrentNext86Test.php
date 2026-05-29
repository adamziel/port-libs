<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteUpstreamSuiteEvidence;

function libsqlite_suite_evidence86(): SQLiteUpstreamSuiteEvidence
{
    return SQLiteUpstreamSuiteEvidence::fromManifestPath(__DIR__ . '/../UPSTREAM_TEST_MANIFEST.json');
}

function libsqlite_suite_evidence86_output(int $passLines = 12, int $assertions = 112, int $failures = 0): string
{
    $lines = ['Focused test run: 1 selected test files (root lock skipped)'];
    for ($i = 1; $i <= $passLines; $i++) {
        $lines[] = sprintf('PASS current next86 suite evidence case %02d', $i);
    }
    $lines[] = sprintf('1 test files, %d assertions, %d failures', $assertions, $failures);

    return implode("\n", $lines);
}

/**
 * @return list<array<string, mixed>>
 */
function libsqlite_suite_evidence86_rows(
    string $head = 'c196709c053869bec78f15d5a1f299d396f8fdb0',
    int $case = 1
): array {
    return [
        [
            'unit' => 'suite-evidence-current-next86-focused-artifact',
            'tier' => 'focused',
            'repository_head' => $head,
            'current_countable' => false,
            'next_countable' => true,
            'artifact_path' => 'lanes/libsqlite/notes/suite-evidence-current-next86.md',
            'runner_command' => './testfixture ../libsqlite/test/testrunner.tcl --jobs 1 --stop-on-error veryquick attach3.test wal2.test',
            'scripts' => [sprintf('pager-current-next86-%02d.test', $case), 'attach3.test', 'wal2.test'],
            'exit' => 0,
            'errors' => 0,
            'current_tests' => 0,
            'next_tests' => 910 + $case,
            'evidence' => 'current-next86 records a bounded suite evidence slice after integrated next77-next85 without claiming release/all parity',
        ],
        [
            'unit' => 'suite-evidence-current-next86-next85-baseline',
            'tier' => 'focused',
            'repository_head' => $head,
            'current_countable' => true,
            'next_countable' => true,
            'artifact_path' => 'lanes/libsqlite/notes/suite-evidence-current-next85.md',
            'runner_command' => './testfixture ../libsqlite/test/testrunner.tcl --jobs 1 --stop-on-error veryquick attach3.test wal2.test',
            'scripts' => ['pager-current-next85-10.test', 'attach3.test', 'wal2.test'],
            'exit' => 0,
            'errors' => 0,
            'current_tests' => 890,
            'next_tests' => 890,
            'evidence' => 'integrated current-next85 suite evidence remains preserved for current-next86',
        ],
    ];
}

/**
 * @param array<int|string, array<string, mixed>> $rows
 * @return array<string, mixed>
 */
function libsqlite_suite_evidence86_record(
    array $rows,
    string $head = 'c196709c053869bec78f15d5a1f299d396f8fdb0',
    string $output = null,
    ?int $expected = 12,
    string $snapshot = ''
): array {
    return libsqlite_suite_evidence86()->suiteEvidenceSlice(
        $rows,
        472,
        29090,
        $head,
        'lanes/libsqlite/tests/SQLiteSuiteEvidenceCurrentNext86Test.php',
        $output ?? libsqlite_suite_evidence86_output(),
        'current-next86 suite evidence follows integrated current-next77/current-next78/current-next79/current-next80/current-next81/current-next82/current-next83/current-next84/current-next85 and avoids release/all parity claims',
        $expected,
        $snapshot
    );
}

return [
    'current next86 counts one bounded suite evidence row after next85' => static function (TestRunner $t): void {
        $record = libsqlite_suite_evidence86_record(libsqlite_suite_evidence86_rows(case: 11));

        $t->same('current-next86-suite-evidence-countable', $record['status']);
        $t->same(true, $record['countable']);
        $t->same(472, $record['current_mapped']);
        $t->same(473, $record['next_mapped']);
        $t->same(1, $record['mapped_delta']);
        $t->same(12, $record['php_pass_delta']);
        $t->same(29102, $record['next_php_pass']);
        $t->same(['suite-evidence-current-next86-focused-artifact'], $record['advanced_units']);
        $t->same(['suite-evidence-current-next86-next85-baseline'], $record['preserved_units']);
        $t->same(false, $record['counts_suite_evidence_current_next85']);
        $t->same(true, $record['counts_suite_evidence_current_next86']);
        $t->same(false, $record['counts_release_parity']);
    },
    'current next86 records tiers scripts and test delta' => static function (TestRunner $t): void {
        $record = libsqlite_suite_evidence86_record(libsqlite_suite_evidence86_rows(case: 7));

        $t->same(['focused' => 2], $record['tiers']);
        $t->same(1, $record['tier_count']);
        $t->same(917, $record['tests_total_delta']);
        $t->same([
            'attach3.test',
            'pager-current-next85-10.test',
            'pager-current-next86-07.test',
            'wal2.test',
        ], $record['target_scripts']);
    },
    'current next86 preserves already counted suite evidence' => static function (TestRunner $t): void {
        $rows = libsqlite_suite_evidence86_rows();
        $rows[0]['current_countable'] = true;
        $rows[0]['current_tests'] = $rows[0]['next_tests'];

        $record = libsqlite_suite_evidence86_record($rows);

        $t->same('current-next86-suite-evidence-preserved', $record['status']);
        $t->same(0, $record['mapped_delta']);
        $t->same(472, $record['next_mapped']);
        $t->same([
            'suite-evidence-current-next86-focused-artifact',
            'suite-evidence-current-next86-next85-baseline',
        ], $record['preserved_units']);
    },
    'current next86 blocks stale head and non local artifact' => static function (TestRunner $t): void {
        $rows = libsqlite_suite_evidence86_rows(head: '0000000000000000000000000000000000000000');
        $rows[0]['artifact_path'] = '/tmp/sqlite-suite.log';

        $record = libsqlite_suite_evidence86_record($rows);

        $t->same('blocked', $record['status']);
        $t->contains('accepted-head-mismatch', implode('; ', array_column($record['blockers'], 'evidence')));
        $t->contains('artifact-path-not-lane-local', implode('; ', array_column($record['blockers'], 'evidence')));
    },
    'current next86 blocks bad runner evidence fields' => static function (TestRunner $t): void {
        $rows = libsqlite_suite_evidence86_rows();
        $rows[0]['runner_command'] = 'php tools/run-tests.php';
        $rows[0]['exit'] = 1;
        $rows[0]['errors'] = 1;
        $rows[0]['next_tests'] = 0;
        $rows[0]['scripts'] = [];
        $rows[0]['evidence'] = '';

        $record = libsqlite_suite_evidence86_record($rows);
        $evidence = implode('; ', array_column($record['blockers'], 'evidence'));

        $t->same('blocked', $record['status']);
        $t->contains('suite-runner-command-missing', $evidence);
        $t->contains('runner-exit-not-zero', $evidence);
        $t->contains('runner-errors-not-zero', $evidence);
        $t->contains('runner-test-count-missing', $evidence);
        $t->contains('suite-scripts-missing', $evidence);
        $t->contains('suite-evidence-missing', $evidence);
    },
    'current next86 blocks duplicate regression parity and active runner' => static function (TestRunner $t): void {
        $rows = libsqlite_suite_evidence86_rows();
        $rows[0]['counts_release_parity'] = true;
        $rows[1]['next_countable'] = false;
        $rows[] = $rows[0];

        $record = libsqlite_suite_evidence86_record($rows, snapshot: '123 testfixture ../libsqlite/test/testrunner.tcl --jobs 4 all');
        $evidence = implode('; ', array_column($record['blockers'], 'evidence'));

        $t->same('blocked', $record['status']);
        $t->contains('release-parity-claim-not-allowed', $evidence);
        $t->contains('suite-evidence-countability-regressed', $evidence);
        $t->contains('duplicate-suite-evidence-unit', $evidence);
        $t->contains('active broad runner process', $evidence);
        $t->same(['suite-evidence-current-next86-next85-baseline'], $record['regressed_units']);
    },
    'current next86 blocks focused php admission mismatch' => static function (TestRunner $t): void {
        $record = libsqlite_suite_evidence86_record(
            libsqlite_suite_evidence86_rows(),
            output: libsqlite_suite_evidence86_output(passLines: 11),
            expected: 12
        );

        $t->same('blocked', $record['status']);
        $t->contains('focused PHP PASS-line admission', $record['blockers'][0]['evidence']);
        $t->same(29090, $record['next_php_pass']);
    },
    'current next86 records dependency closure without parity claim' => static function (TestRunner $t): void {
        $record = libsqlite_suite_evidence86_record(libsqlite_suite_evidence86_rows());

        $t->contains('current-next86 suite evidence', $record['dependency_closure']);
        $t->contains('release/all parity remains blocked', $record['next_gate']);
        $t->same(false, $record['counts_release_parity']);
    },
    'current next86 rejects empty row list' => static function (TestRunner $t): void {
        $t->throws(InvalidArgumentException::class, static fn () => libsqlite_suite_evidence86_record([]));
    },
];
