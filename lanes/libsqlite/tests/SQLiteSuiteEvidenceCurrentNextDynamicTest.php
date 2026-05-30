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
    'current next78 through next103 preserve dynamic count keys and dependency closure' => static function (TestRunner $t): void {
        $head = 'c196709c053869bec78f15d5a1f299d396f8fdb0';
        $rows = [
            [
                'unit' => 'suite-evidence-current-next103-focused-artifact',
                'tier' => 'focused',
                'repository_head' => $head,
                'current_countable' => false,
                'next_countable' => true,
                'artifact_path' => 'lanes/libsqlite/notes/suite-evidence-current-next103.md',
                'runner_command' => './testfixture ../libsqlite/test/testrunner.tcl --jobs 1 --stop-on-error veryquick attach3.test wal2.test',
                'scripts' => ['pager-current-next103-11.test', 'attach3.test', 'wal2.test'],
                'exit' => 0,
                'errors' => 0,
                'current_tests' => 0,
                'next_tests' => 1301,
                'evidence' => 'current-next103 records one bounded suite evidence row after current-next78 through current-next102 were already accepted',
            ],
        ];

        foreach (range(78, 102) as $next) {
            $rows[] = [
                'unit' => sprintf('suite-evidence-current-next%d-preserved-baseline', $next),
                'tier' => 'focused',
                'repository_head' => $head,
                'current_countable' => true,
                'next_countable' => true,
                'artifact_path' => sprintf('lanes/libsqlite/notes/suite-evidence-current-next%d.md', $next),
                'runner_command' => './testfixture ../libsqlite/test/testrunner.tcl --jobs 1 --stop-on-error veryquick attach3.test wal2.test',
                'scripts' => [sprintf('pager-current-next%d-preserved.test', $next), 'attach3.test', 'wal2.test'],
                'exit' => 0,
                'errors' => 0,
                'current_tests' => 700 + $next,
                'next_tests' => 700 + $next,
                'evidence' => sprintf('accepted current-next%d suite evidence remains preserved for current-next103 dependency closure', $next),
            ];
        }

        $record = libsqlite_suite_evidence_dynamic()->suiteEvidenceSlice(
            $rows,
            489,
            29294,
            $head,
            'lanes/libsqlite/tests/SQLiteSuiteEvidenceCurrentNext103Test.php',
            libsqlite_suite_evidence_dynamic_output(passLines: 12),
            'root gate current-next103 bounded evidence preserves current-next78 current-next79 current-next80 current-next81 current-next82 current-next83 current-next84 current-next85 current-next86 current-next87 current-next88 current-next89 current-next90 current-next91 current-next92 current-next93 current-next94 current-next95 current-next96 current-next97 current-next98 current-next99 current-next100 current-next101 current-next102 dependency closure without release/all parity',
            12
        );

        $t->same('current-next103-suite-evidence-countable', $record['status']);
        $t->same(26, $record['row_count']);
        $t->same(1, $record['mapped_delta']);
        $t->same(12, $record['php_pass_delta']);
        $t->same(['suite-evidence-current-next103-focused-artifact'], $record['advanced_units']);
        $t->same(25, count($record['preserved_units']));
        $t->same(1301, $record['tests_total_delta']);
        $t->same(true, $record['counts_suite_evidence_current_next103']);
        foreach (range(78, 102) as $next) {
            $t->same(false, $record['counts_suite_evidence_current_next' . $next], 'current-next' . $next . ' remains preserved');
        }
        $t->contains('current-next103 suite evidence', $record['dependency_closure']);
        $t->contains('release/all parity remains blocked', $record['next_gate']);
        $t->same(false, $record['counts_release_parity']);
    },
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
    'current next dynamic evidence derives primary id from artifact and script rows' => static function (TestRunner $t): void {
        $head = '6fee21f03a85ab614d3e639763183bc5480347a3';
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
                    'evidence' => 'bounded dynamic suite evidence row without release/all parity',
                ],
                [
                    'unit' => 'suite-evidence-dynamic-preserved-baseline',
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
                    'evidence' => 'accepted baseline remains preserved',
                ],
            ],
            490,
            29306,
            $head,
            'lanes/libsqlite/tests/SQLiteSuiteEvidenceCurrentNextDynamicTest.php',
            libsqlite_suite_evidence_dynamic_output(),
            'dynamic suite evidence preserves dependency closure without release/all parity',
            3
        );

        $t->same('current-next104-suite-evidence-countable', $record['status']);
        $t->same(true, $record['counts_suite_evidence_current_next104']);
        $t->same(false, $record['counts_suite_evidence_current_next103']);
        $t->contains('current-next104 suite evidence', $record['dependency_closure']);
    },
    'current next dynamic evidence preserves mixed separator dependency keys' => static function (TestRunner $t): void {
        $head = '6fee21f03a85ab614d3e639763183bc5480347a3';
        $record = libsqlite_suite_evidence_dynamic()->suiteEvidenceSlice(
            [
                [
                    'unit' => 'suite_evidence_current_next106_focused_artifact',
                    'tier' => 'focused',
                    'repository_head' => $head,
                    'current_countable' => false,
                    'next_countable' => true,
                    'artifact_path' => 'lanes/libsqlite/notes/suite-evidence-current-next106.md',
                    'runner_command' => './testfixture ../libsqlite/test/testrunner.tcl --jobs 1 --stop-on-error veryquick attach3.test wal2.test',
                    'scripts' => ['pager-current_next106-01.test', 'attach3.test', 'wal2.test'],
                    'exit' => 0,
                    'errors' => 0,
                    'current_tests' => 0,
                    'next_tests' => 1061,
                    'evidence' => 'current next106 records a bounded dynamic suite evidence row without release/all parity',
                ],
                [
                    'unit' => 'suite-evidence-current next105-preserved-baseline',
                    'tier' => 'focused',
                    'repository_head' => $head,
                    'current_countable' => true,
                    'next_countable' => true,
                    'artifact_path' => 'lanes/libsqlite/notes/suite-evidence-current_next105.md',
                    'runner_command' => './testfixture ../libsqlite/test/testrunner.tcl --jobs 1 --stop-on-error veryquick attach3.test wal2.test',
                    'scripts' => ['pager-current next105-01.test', 'attach3.test', 'wal2.test'],
                    'exit' => 0,
                    'errors' => 0,
                    'current_tests' => 1051,
                    'next_tests' => 1051,
                    'evidence' => 'accepted current next105 baseline remains preserved',
                ],
            ],
            492,
            29312,
            $head,
            'lanes/libsqlite/tests/SQLiteSuiteEvidenceCurrentNextDynamicTest.php',
            libsqlite_suite_evidence_dynamic_output(),
            'current next106 dynamic suite evidence follows current_next105 and preserves dependency closure without release/all parity',
            3
        );

        $t->same('current-next106-suite-evidence-countable', $record['status']);
        $t->same(true, $record['counts_suite_evidence_current_next106']);
        $t->same(false, $record['counts_suite_evidence_current_next105']);
        $t->contains('current-next106 suite evidence', $record['dependency_closure']);
        $t->same(['suite-evidence-current next105-preserved-baseline'], $record['preserved_units']);
        $t->same(1061, $record['tests_total_delta']);
    },
    'current next dynamic evidence exposes blocked row count keys without claiming closure' => static function (TestRunner $t): void {
        $head = '6fee21f03a85ab614d3e639763183bc5480347a3';
        $record = libsqlite_suite_evidence_dynamic()->suiteEvidenceSlice(
            [
                [
                    'unit' => 'suite-evidence-dynamic-blocked-artifact',
                    'tier' => 'focused',
                    'repository_head' => '0000000000000000000000000000000000000000',
                    'current_countable' => false,
                    'next_countable' => true,
                    'artifact_path' => 'lanes/libsqlite/notes/suite-evidence-current-next105.md',
                    'runner_command' => './testfixture ../libsqlite/test/testrunner.tcl --jobs 1 --stop-on-error veryquick attach3.test wal2.test',
                    'scripts' => ['pager-current-next105-01.test', 'attach3.test', 'wal2.test'],
                    'exit' => 0,
                    'errors' => 0,
                    'current_tests' => 0,
                    'next_tests' => 1051,
                    'evidence' => 'current next105 blocked row keeps root-gate dependency keys stable without countability',
                ],
                [
                    'unit' => 'suite-evidence-dynamic-next104-baseline',
                    'tier' => 'focused',
                    'repository_head' => $head,
                    'current_countable' => true,
                    'next_countable' => true,
                    'artifact_path' => 'lanes/libsqlite/notes/suite-evidence-current-next104.md',
                    'runner_command' => './testfixture ../libsqlite/test/testrunner.tcl --jobs 1 --stop-on-error veryquick attach3.test wal2.test',
                    'scripts' => ['pager-current-next104-01.test', 'attach3.test', 'wal2.test'],
                    'exit' => 0,
                    'errors' => 0,
                    'current_tests' => 1041,
                    'next_tests' => 1041,
                    'evidence' => 'accepted current next104 baseline remains preserved',
                ],
            ],
            491,
            29309,
            $head,
            'lanes/libsqlite/tests/SQLiteSuiteEvidenceCurrentNextDynamicTest.php',
            libsqlite_suite_evidence_dynamic_output(),
            'current next105 dynamic suite evidence remains blocked while current next104 stays preserved',
            3
        );

        $t->same('blocked', $record['status']);
        $t->same(false, $record['countable']);
        $t->same(0, $record['mapped_delta']);
        $t->same(false, $record['counts_suite_evidence_current_next105']);
        $t->same(false, $record['counts_suite_evidence_current_next104']);
        $t->same(['suite-evidence-dynamic-blocked-artifact'], $record['blocked_units']);
        $t->same(['suite-evidence-dynamic-next104-baseline'], $record['preserved_units']);
        $t->contains('accepted-head-mismatch', implode('; ', array_column($record['blockers'], 'evidence')));
        $t->contains('composes lane-local artifact metadata', $record['dependency_closure']);
    },
    'current next dynamic evidence preserves ids mentioned only in evidence text' => static function (TestRunner $t): void {
        $head = 'c196709c053869bec78f15d5a1f299d396f8fdb0';
        $record = libsqlite_suite_evidence_dynamic()->suiteEvidenceSlice(
            [
                [
                    'unit' => 'suite-evidence-dynamic-focused-artifact',
                    'tier' => 'focused',
                    'repository_head' => $head,
                    'current_countable' => false,
                    'next_countable' => true,
                    'artifact_path' => 'lanes/libsqlite/notes/suite-evidence-dynamic-focused.md',
                    'runner_command' => './testfixture ../libsqlite/test/testrunner.tcl --jobs 1 --stop-on-error veryquick attach3.test wal2.test',
                    'scripts' => ['pager-dynamic-focused.test', 'attach3.test', 'wal2.test'],
                    'exit' => 0,
                    'errors' => 0,
                    'current_tests' => 0,
                    'next_tests' => 1041,
                    'evidence' => 'current next104 records a bounded dynamic suite evidence row',
                ],
                [
                    'unit' => 'suite-evidence-dynamic-preserved-baseline',
                    'tier' => 'focused',
                    'repository_head' => $head,
                    'current_countable' => true,
                    'next_countable' => true,
                    'artifact_path' => 'lanes/libsqlite/notes/suite-evidence-dynamic-preserved.md',
                    'runner_command' => './testfixture ../libsqlite/test/testrunner.tcl --jobs 1 --stop-on-error veryquick attach3.test wal2.test',
                    'scripts' => ['pager-dynamic-preserved.test', 'attach3.test', 'wal2.test'],
                    'exit' => 0,
                    'errors' => 0,
                    'current_tests' => 778,
                    'next_tests' => 778,
                    'evidence' => 'accepted current-next78 suite evidence remains preserved for current-next104 dependency closure',
                ],
            ],
            490,
            29306,
            $head,
            'lanes/libsqlite/tests/SQLiteSuiteEvidenceCurrentNextDynamicTest.php',
            libsqlite_suite_evidence_dynamic_output(),
            'dynamic suite evidence follows the current accepted source and preserves dependency closure without release/all parity',
            3
        );

        $t->same('current-next104-suite-evidence-countable', $record['status']);
        $t->same(true, array_key_exists('counts_suite_evidence_current_next78', $record));
        $t->same(false, $record['counts_suite_evidence_current_next78']);
        $t->same(true, $record['counts_suite_evidence_current_next104']);
        $t->contains('current-next104 suite evidence', $record['dependency_closure']);
    },
];
