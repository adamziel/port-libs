<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteUpstreamSuiteEvidence;

function libsqlite_suite_evidence100_output(int $passLines = 12): string
{
    $lines = ['Focused test run: 1 selected test files (root lock skipped)'];
    for ($i = 1; $i <= $passLines; $i++) {
        $lines[] = sprintf('PASS current next100 suite evidence case %02d', $i);
    }
    $lines[] = '1 test files, 112 assertions, 0 failures';

    return implode("\n", $lines);
}

/**
 * @return list<array<string, mixed>>
 */
function libsqlite_suite_evidence100_rows(string $head = 'c196709c053869bec78f15d5a1f299d396f8fdb0'): array
{
    return [
        [
            'unit' => 'suite-evidence-current-next100-focused-artifact',
            'tier' => 'focused',
            'repository_head' => $head,
            'current_countable' => false,
            'next_countable' => true,
            'artifact_path' => 'lanes/libsqlite/notes/suite-evidence-current-next100.md',
            'runner_command' => './testfixture ../libsqlite/test/testrunner.tcl --jobs 1 --stop-on-error veryquick attach3.test wal2.test',
            'scripts' => ['pager-current-next100-10.test', 'attach3.test', 'wal2.test'],
            'exit' => 0,
            'errors' => 0,
            'current_tests' => 0,
            'next_tests' => 1600,
            'evidence' => 'current-next100 records a bounded suite evidence slice after integrated next99 without claiming release/all parity',
        ],
        [
            'unit' => 'suite-evidence-current-next100-next99-baseline',
            'tier' => 'focused',
            'repository_head' => $head,
            'current_countable' => true,
            'next_countable' => true,
            'artifact_path' => 'lanes/libsqlite/notes/suite-evidence-current-next99.md',
            'runner_command' => './testfixture ../libsqlite/test/testrunner.tcl --jobs 1 --stop-on-error veryquick attach3.test wal2.test',
            'scripts' => ['pager-current-next99-09.test', 'attach3.test', 'wal2.test'],
            'exit' => 0,
            'errors' => 0,
            'current_tests' => 1499,
            'next_tests' => 1499,
            'evidence' => 'integrated current-next99 suite evidence remains preserved for current-next100',
        ],
    ];
}

/**
 * @param array<int|string, array<string, mixed>> $rows
 * @return array<string, mixed>
 */
function libsqlite_suite_evidence100_record(array $rows, string $output = null, ?int $expected = 12, string $snapshot = ''): array
{
    return SQLiteUpstreamSuiteEvidence::fromManifestPath(__DIR__ . '/../UPSTREAM_TEST_MANIFEST.json')
        ->suiteEvidenceSliceCurrentNext100(
            $rows,
            486,
            29258,
            'c196709c053869bec78f15d5a1f299d396f8fdb0',
            'lanes/libsqlite/tests/SQLiteSuiteEvidenceCurrentNext100Test.php',
            $output ?? libsqlite_suite_evidence100_output(),
            'current-next100 suite evidence follows integrated next99 and avoids release/all parity claims',
            $expected,
            $snapshot
        );
}

return [
    'current next100 counts one bounded suite evidence row after next99' => static function (TestRunner $t): void {
        $record = libsqlite_suite_evidence100_record(libsqlite_suite_evidence100_rows());

        $t->same('current-next100-suite-evidence-countable', $record['status']);
        $t->same(486, $record['current_mapped']);
        $t->same(487, $record['next_mapped']);
        $t->same(29270, $record['next_php_pass']);
        $t->same(1600, $record['tests_total_delta']);
        $t->same(['suite-evidence-current-next100-focused-artifact'], $record['advanced_units']);
        $t->same(['suite-evidence-current-next100-next99-baseline'], $record['preserved_units']);
        $t->same(false, $record['counts_suite_evidence_current_next99']);
        $t->same(true, $record['counts_suite_evidence_current_next100']);
        $t->same(false, $record['counts_release_parity']);
        $t->contains('current-next100 suite evidence', $record['dependency_closure']);
    },
    'current next100 blocks runner and focused php admission mismatch' => static function (TestRunner $t): void {
        $rows = libsqlite_suite_evidence100_rows(head: '0000000000000000000000000000000000000000');
        $rows[0]['runner_command'] = 'php tools/run-tests.php';

        $record = libsqlite_suite_evidence100_record($rows, libsqlite_suite_evidence100_output(passLines: 11), 12);
        $evidence = implode('; ', array_column($record['blockers'], 'evidence'));

        $t->same('blocked', $record['status']);
        $t->contains('accepted-head-mismatch', $evidence);
        $t->contains('suite-runner-command-missing', $evidence);
        $t->contains('focused PHP PASS-line admission', $evidence);
    },
    'current next100 rejects empty row list' => static function (TestRunner $t): void {
        $t->throws(InvalidArgumentException::class, static fn () => libsqlite_suite_evidence100_record([]));
    },
];
