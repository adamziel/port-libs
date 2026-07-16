<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSelectSql;
use PortLibs\LibSqlite\SQLiteUpstreamSuiteEvidence;

function libsqlite_root_gate_suite_evidence_window_regression_evidence(): SQLiteUpstreamSuiteEvidence
{
    return SQLiteUpstreamSuiteEvidence::fromManifestPath(__DIR__ . '/../UPSTREAM_TEST_MANIFEST.json');
}

function libsqlite_root_gate_suite_evidence_window_regression_output(): string
{
    $lines = ['Focused test run: 1 selected test files (root lock skipped)'];

    for ($i = 1; $i <= 12; $i++) {
        $lines[] = sprintf('PASS root gate current next103 suite evidence regression %02d', $i);
    }

    $lines[] = '1 test files, 1176 assertions, 0 failures';

    return implode("\n", $lines);
}

/**
 * @return list<array<string, mixed>>
 */
function libsqlite_root_gate_suite_evidence_window_regression_rows(): array
{
    $head = 'c196709c053869bec78f15d5a1f299d396f8fdb0';

    return [
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
            'evidence' => 'current-next103 records a bounded suite evidence slice after integrated next77-next102 without claiming release/all parity',
        ],
        [
            'unit' => 'suite-evidence-current-next103-next102-baseline',
            'tier' => 'focused',
            'repository_head' => $head,
            'current_countable' => true,
            'next_countable' => true,
            'artifact_path' => 'lanes/libsqlite/notes/suite-evidence-current-next102.md',
            'runner_command' => './testfixture ../libsqlite/test/testrunner.tcl --jobs 1 --stop-on-error veryquick attach3.test wal2.test',
            'scripts' => ['pager-current-next102-10.test', 'attach3.test', 'wal2.test'],
            'exit' => 0,
            'errors' => 0,
            'current_tests' => 1190,
            'next_tests' => 1190,
            'evidence' => 'integrated current-next102 suite evidence remains preserved for current-next103',
        ],
    ];
}

return [
    'root gate keeps next78 through next103 suite evidence bounded to one new row' => static function (TestRunner $t): void {
        $record = libsqlite_root_gate_suite_evidence_window_regression_evidence()->suiteEvidenceSlice(
            libsqlite_root_gate_suite_evidence_window_regression_rows(),
            489,
            29294,
            'c196709c053869bec78f15d5a1f299d396f8fdb0',
            'lanes/libsqlite/tests/SQLiteSuiteEvidenceCurrentNext103Test.php',
            libsqlite_root_gate_suite_evidence_window_regression_output(),
            'root-gate regression keeps current-next78/current-next79/current-next80/current-next81/current-next82/current-next83/current-next84/current-next85/current-next86/current-next87/current-next88/current-next89/current-next90/current-next91/current-next92/current-next93/current-next94/current-next95/current-next96/current-next97/current-next98/current-next99/current-next100/current-next101/current-next102 preserved while current-next103 advances one bounded evidence row',
            12,
            ''
        );

        $t->same('current-next103-suite-evidence-countable', $record['status']);
        $t->same(['suite-evidence-current-next103-focused-artifact'], $record['advanced_units']);
        $t->same(['suite-evidence-current-next103-next102-baseline'], $record['preserved_units']);
        $t->same(1, $record['mapped_delta']);
        $t->same(12, $record['php_pass_delta']);

        foreach (range(78, 102) as $previousNext) {
            $t->same(false, $record['counts_suite_evidence_current_next' . $previousNext]);
        }

        $t->same(true, $record['counts_suite_evidence_current_next103']);
        $t->contains('no new support component needed', $record['dependency_closure']);
    },
    'root gate keeps range and groups frame without order rejected after suite evidence record' => static function (TestRunner $t): void {
        $rows = [
            ['option_id' => 1, 'option_name' => 'alpha_cache', 'bytes' => 10],
            ['option_id' => 2, 'option_name' => 'beta_cache', 'bytes' => 20],
        ];

        $t->throws(InvalidArgumentException::class, static fn () => SQLiteSelectSql::execute('SELECT sum(bytes) OVER (GROUPS BETWEEN CURRENT ROW AND 1 FOLLOWING) FROM wp_options', ['wp_options' => $rows]));
        $t->throws(InvalidArgumentException::class, static fn () => SQLiteSelectSql::plan('SELECT json_group_object(option_name, bytes) OVER (RANGE BETWEEN CURRENT ROW AND 10 FOLLOWING) FROM wp_options', ['wp_options' => $rows]));
    },
];
