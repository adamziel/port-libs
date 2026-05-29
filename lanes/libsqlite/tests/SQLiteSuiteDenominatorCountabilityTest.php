<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteUpstreamSuiteEvidence;

function libsqlite_suite_denominator_output(int $passLines, int $assertions, int $failures = 0, int $selected = 1, int $summaryFiles = 1): string
{
    $lines = [
        sprintf('Focused test run: %d selected test files (root lock skipped)', $selected),
    ];
    for ($i = 1; $i <= $passLines; $i++) {
        $lines[] = sprintf('PASS suite denominator suite denominator case %02d', $i);
    }
    $lines[] = sprintf('%d test files, %d assertions, %d failures', $summaryFiles, $assertions, $failures);

    return implode("\n", $lines);
}

/**
 * @param array<int|string, array<string, mixed>> $rows
 * @return array<string, mixed>
 */
function libsqlite_suite_denominator_record(array $rows, string $output, ?int $expected = null, string $accepted = 'f80e282cd4ea9d875cc8342239dcd8f34ba23e74', string $evidence = 'f80e282cd4ea9d875cc8342239dcd8f34ba23e74', string $processSnapshot = ''): array
{
    return SQLiteUpstreamSuiteEvidence::fromManifestPath(__DIR__ . '/../UPSTREAM_TEST_MANIFEST.json')
        ->suiteDenominatorCountability(
            $rows,
            463,
            23976,
            $accepted,
            $evidence,
            'lanes/libsqlite/tests/SQLiteSuiteDenominatorCountabilityTest.php',
            $output,
            'suite-denominator suite denominator avoids accepted release/all closure, current-next56 focused PASS admission, batch52-55 denominator burnup ledgers, and SQL/JSON/WAL/B-tree/VFS behavior clusters; it admits only current-head denominator/script movement with exact focused PASS lines.',
            $expected,
            $processSnapshot
        );
}

/**
 * @return list<array<string, mixed>>
 */
function libsqlite_suite_denominator_rows(int $delta = 1, int $scriptDelta = 1): array
{
    return [
        [
            'unit' => 'suite-current-head-focused-denominator',
            'category' => 'suite-denominator',
            'current_mapped' => 463,
            'next_mapped' => 463 + $delta,
            'current_countable_scripts' => 96,
            'next_countable_scripts' => 96 + $scriptDelta,
            'denominator_total' => 1589,
            'scripts' => ['select1.test', 'where.test', 'json101.test'],
            'hydrated' => true,
            'evidence' => 'suite-denominator preserves accepted-head provenance and makes the next focused script set countable without claiming release/all parity',
        ],
    ];
}

$tests = [
    'suite denominator admits denominator and focused pass movement together' => static function (TestRunner $t): void {
        $record = libsqlite_suite_denominator_record(libsqlite_suite_denominator_rows(), libsqlite_suite_denominator_output(5, 17), 5);

        $t->same('suite-denominator-countable', $record['status']);
        $t->same(true, $record['countable']);
        $t->same(463, $record['current_mapped']);
        $t->same(464, $record['next_mapped']);
        $t->same(1, $record['mapped_delta']);
        $t->same(1, $record['countable_script_delta']);
        $t->same(5, $record['php_pass_delta']);
        $t->same(23981, $record['next_php_pass']);
        $t->same(false, $record['counts_release_parity']);
        $t->same(['suite-current-head-focused-denominator'], $record['advanced_units']);
    },
    'suite denominator preserves denominator without movement but admits focused output' => static function (TestRunner $t): void {
        $record = libsqlite_suite_denominator_record(libsqlite_suite_denominator_rows(0, 0), libsqlite_suite_denominator_output(2, 8), 2);

        $t->same('suite-denominator-preserved', $record['status']);
        $t->same(false, $record['countable']);
        $t->same(0, $record['mapped_delta']);
        $t->same(0, $record['countable_script_delta']);
        $t->same(2, $record['php_pass_delta']);
        $t->same(['suite-current-head-focused-denominator'], $record['preserved_units']);
    },
    'suite denominator blocks stale accepted head evidence' => static function (TestRunner $t): void {
        $record = libsqlite_suite_denominator_record(
            libsqlite_suite_denominator_rows(),
            libsqlite_suite_denominator_output(3, 9),
            3,
            evidence: '0000000000000000000000000000000000000000'
        );

        $t->same('blocked', $record['status']);
        $t->same(463, $record['next_mapped']);
        $t->same(0, $record['php_pass_delta']);
        $t->same(23976, $record['next_php_pass']);
        $ids = array_column($record['blockers'], 'id');
        $t->true(in_array('focused-current-head-php-pass-blocked', $ids, true), 'Expected current-head blocker');
        $t->same('blocked', $record['php_pass_admission']['status']);
    },
    'suite denominator blocks pass delta inflation' => static function (TestRunner $t): void {
        $record = libsqlite_suite_denominator_record(libsqlite_suite_denominator_rows(), libsqlite_suite_denominator_output(4, 40), 40);

        $t->same('blocked', $record['status']);
        $t->same(4, $record['php_pass_admission']['pass_lines_observed']);
        $t->same(40, $record['php_pass_admission']['assertion_count_observed']);
        $t->same(0, $record['mapped_delta']);
        $t->same(0, $record['php_pass_delta']);
        $t->true(in_array('focused-current-head-php-pass-blocked', array_column($record['blockers'], 'id'), true), 'Expected PHP blocker');
    },
    'suite denominator blocks denominator row evidence gaps' => static function (TestRunner $t): void {
        $rows = libsqlite_suite_denominator_rows();
        $rows[0]['evidence'] = '';

        $record = libsqlite_suite_denominator_record($rows, libsqlite_suite_denominator_output(3, 12), 3);

        $t->same('blocked', $record['status']);
        $t->same(['suite-current-head-focused-denominator'], $record['blocked_units']);
        $t->contains('missing-row-evidence', $record['blockers'][0]['evidence']);
        $t->same(0, $record['mapped_delta']);
    },
    'suite denominator blocks denominator regressions' => static function (TestRunner $t): void {
        $rows = libsqlite_suite_denominator_rows();
        $rows[0]['next_mapped'] = 462;
        $rows[0]['next_countable_scripts'] = 95;

        $record = libsqlite_suite_denominator_record($rows, libsqlite_suite_denominator_output(3, 12), 3);

        $t->same('blocked', $record['status']);
        $t->same(['suite-current-head-focused-denominator'], $record['regressed_units']);
        $t->contains('mapped-count-regressed', $record['blockers'][0]['evidence']);
        $t->contains('countable-script-count-regressed', $record['blockers'][0]['evidence']);
    },
    'suite denominator blocks duplicate broad runner snapshots' => static function (TestRunner $t): void {
        $record = libsqlite_suite_denominator_record(
            libsqlite_suite_denominator_rows(),
            libsqlite_suite_denominator_output(2, 8),
            2,
            processSnapshot: '12345 /bin/sh scripts/run-sqlite-tcl-bounded-runner.sh --testset release --foreground'
        );

        $t->same('blocked', $record['status']);
        $t->same('blocked-active-runner', $record['active_runner_status']);
        $t->same(1, $record['active_runner_count']);
        $t->true(in_array('duplicate-broad-runner-active', array_column($record['blockers'], 'id'), true), 'Expected active broad runner blocker');
    },
    'suite denominator records unique target scripts' => static function (TestRunner $t): void {
        $rows = libsqlite_suite_denominator_rows();
        $rows[] = [
            'unit' => 'suite-json-planner-denominator',
            'category' => 'json-planner',
            'current_mapped' => 0,
            'next_mapped' => 1,
            'current_countable_scripts' => 0,
            'next_countable_scripts' => 2,
            'denominator_total' => 278,
            'scripts' => ['json101.test', 'json102.test', 'not-a-script.txt'],
            'hydrated' => true,
            'evidence' => 'suite-denominator JSON planner scripts are hydrated and countable as focused upstream rows',
        ];

        $record = libsqlite_suite_denominator_record($rows, libsqlite_suite_denominator_output(6, 20), 6);

        $t->same('suite-denominator-countable', $record['status']);
        $t->same(2, $record['mapped_delta']);
        $t->same(3, $record['countable_script_delta']);
        $t->same(['json101.test', 'json102.test', 'select1.test', 'where.test'], $record['target_scripts']);
        $t->same(['json-planner' => 1, 'suite-denominator' => 1], $record['categories']);
    },
    'suite denominator rejects empty denominator rows' => static function (TestRunner $t): void {
        $t->throws(
            InvalidArgumentException::class,
            static fn () => libsqlite_suite_denominator_record([], libsqlite_suite_denominator_output(1, 3), 1)
        );
    },
    'suite denominator rejects negative current mapped count' => static function (TestRunner $t): void {
        $evidence = SQLiteUpstreamSuiteEvidence::fromManifestPath(__DIR__ . '/../UPSTREAM_TEST_MANIFEST.json');

        $t->throws(
            InvalidArgumentException::class,
            static fn () => $evidence->suiteDenominatorCountability(
                libsqlite_suite_denominator_rows(),
                -1,
                23976,
                'f80e282cd4ea9d875cc8342239dcd8f34ba23e74',
                'f80e282cd4ea9d875cc8342239dcd8f34ba23e74',
                'lanes/libsqlite/tests/SQLiteSuiteDenominatorCountabilityTest.php',
                libsqlite_suite_denominator_output(1, 3),
                'non-overlap',
                1
            )
        );
    },
];

foreach (range(1, 50) as $delta) {
    $tests[sprintf('suite denominator exact focused pass matrix %02d', $delta)] = static function (TestRunner $t) use ($delta): void {
        $rows = libsqlite_suite_denominator_rows(($delta % 3) + 1, ($delta % 4) + 1);
        $record = libsqlite_suite_denominator_record($rows, libsqlite_suite_denominator_output($delta, $delta + 11), $delta);

        $t->same('suite-denominator-countable', $record['status']);
        $t->same($delta, $record['php_pass_delta']);
        $t->same(23976 + $delta, $record['next_php_pass']);
        $t->same($delta, $record['php_pass_admission']['pass_lines_observed']);
        $t->same($delta + 11, $record['php_pass_admission']['assertion_count_observed']);
        $t->same(0, $record['blocker_count']);
        $t->same(['suite-current-head-focused-denominator'], $record['advanced_units']);
    };
}

return $tests;
