<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteUpstreamSuiteEvidence;

function libsqlite_suite_evidence_dynamic(): SQLiteUpstreamSuiteEvidence
{
    return SQLiteUpstreamSuiteEvidence::fromManifestPath(__DIR__ . '/../UPSTREAM_TEST_MANIFEST.json');
}

function libsqlite_suite_evidence_dynamic_output(int $passLines = 3): string
{
    $lines = ['Focused test run: 1 selected test files (root lock skipped)'];
    for ($i = 1; $i <= $passLines; $i++) {
        $lines[] = sprintf('PASS current next104 dynamic suite evidence case %02d', $i);
    }
    $lines[] = '1 test files, 3 assertions, 0 failures';

    return implode("\n", $lines);
}

return [
    'current next dynamic evidence derives primary id from spaced note' => static function (TestRunner $t): void {
        $head = 'c196709c053869bec78f15d5a1f299d396f8fdb0';
        $record = libsqlite_suite_evidence_dynamic()->suiteEvidenceSlice(
            [
                [
                    'unit' => 'suite-evidence-dynamic-focused-artifact',
                    'tier' => 'focused',
                    'repository_head' => $head,
                    'current_countable' => false,
                    'next_countable' => true,
                    'artifact_path' => 'lanes/libsqlite/notes/suite-evidence-current-next104.md',
                    'runner_command' => './testfixture ../libsqlite/test/testrunner.tcl --jobs 1 --stop-on-error veryquick attach3.test wal2.test',
                    'scripts' => ['pager-current-next104-01.test', 'attach3.test', 'wal2.test'],
                    'exit' => 0,
                    'errors' => 0,
                    'current_tests' => 0,
                    'next_tests' => 1041,
                    'evidence' => 'current next104 records a bounded dynamic suite evidence row without release/all parity',
                ],
                [
                    'unit' => 'suite-evidence-dynamic-next103-baseline',
                    'tier' => 'focused',
                    'repository_head' => $head,
                    'current_countable' => true,
                    'next_countable' => true,
                    'artifact_path' => 'lanes/libsqlite/notes/suite-evidence-current-next103.md',
                    'runner_command' => './testfixture ../libsqlite/test/testrunner.tcl --jobs 1 --stop-on-error veryquick attach3.test wal2.test',
                    'scripts' => ['pager-current-next103-11.test', 'attach3.test', 'wal2.test'],
                    'exit' => 0,
                    'errors' => 0,
                    'current_tests' => 1301,
                    'next_tests' => 1301,
                    'evidence' => 'accepted current next103 baseline remains preserved for dynamic current next104 evidence',
                ],
            ],
            490,
            29306,
            $head,
            'lanes/libsqlite/tests/SQLiteSuiteEvidenceCurrentNextDynamicTest.php',
            libsqlite_suite_evidence_dynamic_output(),
            'current next104 dynamic suite evidence follows current next103 and preserves dependency closure without release/all parity',
            3
        );

        $t->same('current-next104-suite-evidence-countable', $record['status']);
        $t->same(true, $record['counts_suite_evidence_current_next104']);
        $t->same(false, $record['counts_suite_evidence_current_next103']);
        $t->contains('current-next104 suite evidence', $record['dependency_closure']);
        $t->same(['suite-evidence-dynamic-next103-baseline'], $record['preserved_units']);
        $t->same(1041, $record['tests_total_delta']);
    },
];
