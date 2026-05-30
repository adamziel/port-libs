<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteUpstreamSuiteEvidence;

function libsqlite_suite_evidence_dynamic_range_output(int $slice, int $passLines = 12): string
{
    $lines = ['Focused test run: 1 selected test files (root lock skipped)'];
    for ($i = 1; $i <= $passLines; $i++) {
        $lines[] = sprintf('PASS current next%d dynamic suite evidence case %02d', $slice, $i);
    }
    $lines[] = '1 test files, 112 assertions, 0 failures';

    return implode("\n", $lines);
}

/**
 * @return list<array<string, mixed>>
 */
function libsqlite_suite_evidence_dynamic_range_rows(int $slice, string $head = 'c196709c053869bec78f15d5a1f299d396f8fdb0'): array
{
    $previous = $slice - 1;

    return [
        [
            'unit' => sprintf('suite-evidence-current-next%d-focused-artifact', $slice),
            'tier' => 'focused',
            'repository_head' => $head,
            'current_countable' => false,
            'next_countable' => true,
            'artifact_path' => sprintf('lanes/libsqlite/notes/suite-evidence-current-next%d.md', $slice),
            'runner_command' => './testfixture ../libsqlite/test/testrunner.tcl --jobs 1 --stop-on-error veryquick attach3.test wal2.test',
            'scripts' => [sprintf('dynamic-current-next%d.test', $slice), 'attach3.test', 'wal2.test'],
            'exit' => 0,
            'errors' => 0,
            'current_tests' => 0,
            'next_tests' => 700 + $slice,
            'evidence' => sprintf('current-next%d records a bounded suite evidence row without claiming release/all parity', $slice),
        ],
        [
            'unit' => sprintf('suite-evidence-current-next%d-next%d-baseline', $slice, $previous),
            'tier' => 'focused',
            'repository_head' => $head,
            'current_countable' => true,
            'next_countable' => true,
            'artifact_path' => sprintf('lanes/libsqlite/notes/suite-evidence-current-next%d.md', $previous),
            'runner_command' => './testfixture ../libsqlite/test/testrunner.tcl --jobs 1 --stop-on-error veryquick attach3.test wal2.test',
            'scripts' => [sprintf('dynamic-current-next%d.test', $previous), 'attach3.test', 'wal2.test'],
            'exit' => 0,
            'errors' => 0,
            'current_tests' => 600 + $previous,
            'next_tests' => 600 + $previous,
            'evidence' => sprintf('current-next%d baseline remains preserved for current-next%d', $previous, $slice),
        ],
    ];
}

function libsqlite_suite_evidence_dynamic_range_record(int $slice): array
{
    return SQLiteUpstreamSuiteEvidence::fromManifestPath(__DIR__ . '/../UPSTREAM_TEST_MANIFEST.json')
        ->suiteEvidenceSlice(
            libsqlite_suite_evidence_dynamic_range_rows($slice),
            466 + ($slice - 78),
            29018 + (($slice - 78) * 12),
            'c196709c053869bec78f15d5a1f299d396f8fdb0',
            'lanes/libsqlite/tests/SQLiteSuiteEvidenceCurrentNextDynamicRangeTest.php',
            libsqlite_suite_evidence_dynamic_range_output($slice),
            sprintf('current-next%d dynamic suite evidence follows current-next%d and avoids release/all parity claims', $slice, $slice - 1),
            12
        );
}

return [
    'current next78 through next103 preserve dynamic count keys and dependency closure' => static function (TestRunner $t): void {
        for ($slice = 78; $slice <= 103; $slice++) {
            $record = libsqlite_suite_evidence_dynamic_range_record($slice);
            $primaryKey = sprintf('counts_suite_evidence_current_next%d', $slice);
            $previousKey = sprintf('counts_suite_evidence_current_next%d', $slice - 1);
            $expectedStatus = sprintf('current-next%d-suite-evidence-countable', $slice);

            $t->same($expectedStatus, $record['status']);
            $t->same(true, $record['countable']);
            $t->same(1, $record['mapped_delta']);
            $t->same(12, $record['php_pass_delta']);
            $t->same(true, $record[$primaryKey]);
            $t->same(false, $record[$previousKey]);
            $t->contains(sprintf('current-next%d suite evidence', $slice), $record['dependency_closure']);
            $t->contains('release/all parity remains blocked', $record['next_gate']);
        }
    },
];
