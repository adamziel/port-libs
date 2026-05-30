<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteUpstreamSuiteEvidence;

function libsqlite_suite_evidence99_output(int $passLines = 12): string
{
    $lines = ['Focused test run: 1 selected test files (root lock skipped)'];
    for ($i = 1; $i <= $passLines; $i++) {
        $lines[] = sprintf('PASS current next99 suite evidence case %02d', $i);
    }
    $lines[] = '1 test files, 112 assertions, 0 failures';

    return implode("\n", $lines);
}

/**
 * @return list<array<string, mixed>>
 */
function libsqlite_suite_evidence99_rows(string $head = 'c196709c053869bec78f15d5a1f299d396f8fdb0'): array
{
    return [
        [
            'unit' => 'suite-evidence-current-next99-focused-artifact',
            'tier' => 'focused',
            'repository_head' => $head,
            'current_countable' => false,
            'next_countable' => true,
            'artifact_path' => 'lanes/libsqlite/notes/suite-evidence-current-next99.md',
            'runner_command' => './testfixture ../libsqlite/test/testrunner.tcl --jobs 1 --stop-on-error veryquick attach3.test wal2.test',
            'scripts' => ['pager-current-next99-09.test', 'attach3.test', 'wal2.test'],
            'exit' => 0,
            'errors' => 0,
            'current_tests' => 0,
            'next_tests' => 1499,
            'evidence' => 'current-next99 records a bounded suite evidence slice after integrated next98 without claiming release/all parity',
        ],
        [
            'unit' => 'suite-evidence-current-next99-next98-baseline',
            'tier' => 'focused',
            'repository_head' => $head,
            'current_countable' => true,
            'next_countable' => true,
            'artifact_path' => 'lanes/libsqlite/notes/suite-evidence-current-next98.md',
            'runner_command' => './testfixture ../libsqlite/test/testrunner.tcl --jobs 1 --stop-on-error veryquick attach3.test wal2.test',
            'scripts' => ['pager-current-next98-08.test', 'attach3.test', 'wal2.test'],
            'exit' => 0,
            'errors' => 0,
            'current_tests' => 1398,
            'next_tests' => 1398,
            'evidence' => 'integrated current-next98 suite evidence remains preserved for current-next99',
        ],
    ];
}

/**
 * @param array<int|string, array<string, mixed>> $rows
 * @return array<string, mixed>
 */
function libsqlite_suite_evidence99_record(array $rows, string $output = null, ?int $expected = 12, string $snapshot = ''): array
{
    return SQLiteUpstreamSuiteEvidence::fromManifestPath(__DIR__ . '/../UPSTREAM_TEST_MANIFEST.json')
        ->suiteEvidenceSlice(
            $rows,
            485,
            29246,
            'c196709c053869bec78f15d5a1f299d396f8fdb0',
            'lanes/libsqlite/tests/SQLiteSuiteEvidenceCurrentNext99Test.php',
            $output ?? libsqlite_suite_evidence99_output(),
            'current-next99 suite evidence follows integrated next98 and avoids release/all parity claims',
            $expected,
            $snapshot
        );
}

return [
    'current next99 counts one bounded suite evidence row after next98' => static function (TestRunner $t): void {
        $record = libsqlite_suite_evidence99_record(libsqlite_suite_evidence99_rows());

        $t->same('current-next99-suite-evidence-countable', $record['status']);
        $t->same(true, $record['countable']);
        $t->same(485, $record['current_mapped']);
        $t->same(486, $record['next_mapped']);
        $t->same(1, $record['mapped_delta']);
        $t->same(12, $record['php_pass_delta']);
        $t->same(29258, $record['next_php_pass']);
        $t->same(1499, $record['tests_total_delta']);
        $t->same(['suite-evidence-current-next99-focused-artifact'], $record['advanced_units']);
        $t->same(['suite-evidence-current-next99-next98-baseline'], $record['preserved_units']);
        $t->same(false, $record['counts_suite_evidence_current_next98']);
        $t->same(true, $record['counts_suite_evidence_current_next99']);
        $t->same(false, $record['counts_release_parity']);
    },
    'current next99 records tiers scripts and test delta' => static function (TestRunner $t): void {
        $record = libsqlite_suite_evidence99_record(libsqlite_suite_evidence99_rows());

        $t->same(['focused' => 2], $record['tiers']);
        $t->same(1, $record['tier_count']);
        $t->same(1499, $record['tests_total_delta']);
        $t->same([
            'attach3.test',
            'pager-current-next98-08.test',
            'pager-current-next99-09.test',
            'wal2.test',
        ], $record['target_scripts']);
    },
    'current next99 preserves already counted suite evidence' => static function (TestRunner $t): void {
        $rows = libsqlite_suite_evidence99_rows();
        $rows[0]['current_countable'] = true;
        $rows[0]['current_tests'] = $rows[0]['next_tests'];

        $record = libsqlite_suite_evidence99_record($rows);

        $t->same('current-next99-suite-evidence-preserved', $record['status']);
        $t->same(0, $record['mapped_delta']);
        $t->same(485, $record['next_mapped']);
        $t->same([
            'suite-evidence-current-next99-focused-artifact',
            'suite-evidence-current-next99-next98-baseline',
        ], $record['preserved_units']);
    },
    'current next99 blocks runner and focused php admission mismatch' => static function (TestRunner $t): void {
        $rows = libsqlite_suite_evidence99_rows(head: '0000000000000000000000000000000000000000');
        $rows[0]['runner_command'] = 'php tools/run-tests.php';

        $record = libsqlite_suite_evidence99_record($rows, libsqlite_suite_evidence99_output(passLines: 11), 12);
        $evidence = implode('; ', array_column($record['blockers'], 'evidence'));

        $t->same('blocked', $record['status']);
        $t->contains('accepted-head-mismatch', $evidence);
        $t->contains('suite-runner-command-missing', $evidence);
        $t->contains('focused PHP PASS-line admission', $evidence);
    },
    'current next99 records dependency closure without parity claim' => static function (TestRunner $t): void {
        $record = libsqlite_suite_evidence99_record(libsqlite_suite_evidence99_rows());

        $t->contains('current-next99 suite evidence', $record['dependency_closure']);
        $t->contains('release/all parity remains blocked', $record['next_gate']);
        $t->same(false, $record['counts_release_parity']);
    },
    'current next99 rejects empty row list' => static function (TestRunner $t): void {
        $t->throws(InvalidArgumentException::class, static fn () => libsqlite_suite_evidence99_record([]));
    },
];
