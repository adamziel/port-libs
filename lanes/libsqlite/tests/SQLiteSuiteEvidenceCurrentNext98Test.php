<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteUpstreamSuiteEvidence;

function libsqlite_suite_evidence98(): SQLiteUpstreamSuiteEvidence
{
    return SQLiteUpstreamSuiteEvidence::fromManifestPath(__DIR__ . '/../UPSTREAM_TEST_MANIFEST.json');
}

function libsqlite_suite_evidence98_output(int $passLines = 12, int $assertions = 112, int $failures = 0): string
{
    $lines = ['Focused test run: 1 selected test files (root lock skipped)'];
    for ($i = 1; $i <= $passLines; $i++) {
        $lines[] = sprintf('PASS current next98 suite evidence case %02d', $i);
    }
    $lines[] = sprintf('1 test files, %d assertions, %d failures', $assertions, $failures);

    return implode("\n", $lines);
}

/**
 * @return list<array<string, mixed>>
 */
function libsqlite_suite_evidence98_rows(int $case = 1, string $head = 'c196709c053869bec78f15d5a1f299d396f8fdb0'): array
{
    return [
        [
            'unit' => 'suite-evidence-current-next98-focused-artifact',
            'tier' => 'focused',
            'repository_head' => $head,
            'current_countable' => false,
            'next_countable' => true,
            'artifact_path' => 'lanes/libsqlite/notes/suite-evidence-current-next98.md',
            'runner_command' => './testfixture ../libsqlite/test/testrunner.tcl --jobs 1 --stop-on-error veryquick attach3.test wal2.test',
            'scripts' => [sprintf('pager-current-next98-%02d.test', $case), 'attach3.test', 'wal2.test'],
            'exit' => 0,
            'errors' => 0,
            'current_tests' => 0,
            'next_tests' => 1390 + $case,
            'evidence' => 'current-next98 records a bounded suite evidence slice after integrated next95-next97 without claiming release/all parity',
        ],
        [
            'unit' => 'suite-evidence-current-next98-next97-baseline',
            'tier' => 'focused',
            'repository_head' => $head,
            'current_countable' => true,
            'next_countable' => true,
            'artifact_path' => 'lanes/libsqlite/notes/suite-evidence-current-next97.md',
            'runner_command' => './testfixture ../libsqlite/test/testrunner.tcl --jobs 1 --stop-on-error veryquick attach3.test wal2.test',
            'scripts' => ['pager-current-next97-10.test', 'attach3.test', 'wal2.test'],
            'exit' => 0,
            'errors' => 0,
            'current_tests' => 1290,
            'next_tests' => 1290,
            'evidence' => 'integrated current-next97 suite evidence remains preserved for current-next98',
        ],
    ];
}

/**
 * @param array<int|string, array<string, mixed>> $rows
 * @return array<string, mixed>
 */
function libsqlite_suite_evidence98_record(array $rows, string $output = null, ?int $expected = 12, string $snapshot = ''): array
{
    return libsqlite_suite_evidence98()->suiteEvidenceSlice(
        $rows,
        484,
        29234,
        'c196709c053869bec78f15d5a1f299d396f8fdb0',
        'lanes/libsqlite/tests/SQLiteSuiteEvidenceCurrentNext98Test.php',
        $output ?? libsqlite_suite_evidence98_output(),
        'current-next98 suite evidence follows integrated next95-next97 and avoids release/all parity claims',
        $expected,
        $snapshot
    );
}

return [
    'current next98 counts one bounded suite evidence row after next97' => static function (TestRunner $t): void {
        $record = libsqlite_suite_evidence98_record(libsqlite_suite_evidence98_rows(case: 8));

        $t->same('suite-evidence-countable', $record['status']);
        $t->same(484, $record['current_mapped']);
        $t->same(485, $record['next_mapped']);
        $t->same(1, $record['mapped_delta']);
        $t->same(12, $record['php_pass_delta']);
        $t->same(29246, $record['next_php_pass']);
        $t->same(['suite-evidence-current-next98-focused-artifact'], $record['advanced_units']);
        $t->same(['suite-evidence-current-next98-next97-baseline'], $record['preserved_units']);
        $t->same(false, $record['counts_suite_evidence_current_next97'] ?? false);
        $t->true(in_array('suite-evidence-current-next98-focused-artifact', $record['advanced_units'], true), 'Expected next98 focused artifact to count');
        $t->same(false, $record['counts_release_parity']);
    },
    'current next98 records scripts and test delta' => static function (TestRunner $t): void {
        $record = libsqlite_suite_evidence98_record(libsqlite_suite_evidence98_rows(case: 8));

        $t->same(['focused' => 2], $record['tiers']);
        $t->same(1398, $record['tests_total_delta']);
        $t->same(['attach3.test', 'pager-current-next97-10.test', 'pager-current-next98-08.test', 'wal2.test'], $record['target_scripts']);
    },
    'current next98 preserves already counted suite evidence' => static function (TestRunner $t): void {
        $rows = libsqlite_suite_evidence98_rows();
        $rows[0]['current_countable'] = true;
        $rows[0]['current_tests'] = $rows[0]['next_tests'];

        $record = libsqlite_suite_evidence98_record($rows);

        $t->same('suite-evidence-preserved', $record['status']);
        $t->same(0, $record['mapped_delta']);
        $t->same(484, $record['next_mapped']);
    },
    'current next98 blocks stale head bad runner and active broad runner' => static function (TestRunner $t): void {
        $rows = libsqlite_suite_evidence98_rows(head: '0000000000000000000000000000000000000000');
        $rows[0]['runner_command'] = 'php tools/run-tests.php';
        $rows[0]['counts_release_parity'] = true;

        $record = libsqlite_suite_evidence98_record($rows, snapshot: '123 testfixture ../libsqlite/test/testrunner.tcl --jobs 4 all');
        $evidence = implode('; ', array_column($record['blockers'], 'evidence'));

        $t->same('blocked', $record['status']);
        $t->contains('accepted-head-mismatch', $evidence);
        $t->contains('suite-runner-command-missing', $evidence);
        $t->contains('release-parity-claim-not-allowed', $evidence);
        $t->contains('active broad runner process', $evidence);
    },
    'current next98 blocks focused php admission mismatch' => static function (TestRunner $t): void {
        $record = libsqlite_suite_evidence98_record(libsqlite_suite_evidence98_rows(), libsqlite_suite_evidence98_output(passLines: 11), 12);

        $t->same('blocked', $record['status']);
        $t->contains('focused PHP PASS-line admission', $record['blockers'][0]['evidence']);
        $t->same(29234, $record['next_php_pass']);
    },
    'current next98 records dependency closure without parity claim' => static function (TestRunner $t): void {
        $record = libsqlite_suite_evidence98_record(libsqlite_suite_evidence98_rows());

        $t->contains('suite evidence composes lane-local artifact metadata', $record['dependency_closure']);
        $t->contains('release/all parity remains blocked', $record['next_gate']);
    },
    'current next98 rejects empty row list' => static function (TestRunner $t): void {
        $t->throws(InvalidArgumentException::class, static fn () => libsqlite_suite_evidence98_record([]));
    },
];
