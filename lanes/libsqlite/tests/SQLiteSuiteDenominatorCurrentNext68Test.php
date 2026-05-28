<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteUpstreamSuiteEvidence;

function libsqlite_current_next68_output(int $passLines, int $assertions, int $failures = 0, int $selected = 1, int $summaryFiles = 1): string
{
    $lines = [
        sprintf('Focused test run: %d selected test files (root lock skipped)', $selected),
    ];
    for ($i = 1; $i <= $passLines; $i++) {
        $lines[] = sprintf('PASS current next68 suite denominator artifact case %02d', $i);
    }
    $lines[] = sprintf('%d test files, %d assertions, %d failures', $summaryFiles, $assertions, $failures);

    return implode("\n", $lines);
}

/**
 * @return list<array<string, mixed>>
 */
function libsqlite_current_next68_rows(int $case = 1): array
{
    return [
        [
            'unit' => 'current-next68-focused-artifact-' . $case,
            'category' => 'focused-current-head-artifact',
            'repository_head' => 'd921ec13f1e9ff4a604df7af85002135e715654c',
            'current_status' => 'missing',
            'next_status' => 'countable',
            'artifact_path' => 'lanes/libsqlite/notes/suite-denominator-current-next68.md',
            'scripts' => ['select1.test', 'where.test', 'json101.test'],
            'current_tests' => 0,
            'next_tests' => 1100 + $case,
            'evidence' => 'current-next68 admits one accepted-HEAD focused runner artifact into the suite denominator without release/all parity',
        ],
        [
            'unit' => 'accepted-focused-baseline',
            'category' => 'preserved-current-head-artifact',
            'repository_head' => 'd921ec13f1e9ff4a604df7af85002135e715654c',
            'current_status' => 'countable',
            'next_status' => 'countable',
            'artifact_path' => 'lanes/libsqlite/notes/upstream-runner.md',
            'scripts' => ['veryquick.test'],
            'current_tests' => 329670,
            'next_tests' => 329670,
            'evidence' => 'accepted focused upstream evidence remains preserved while current-next68 adds only one current-head denominator unit',
        ],
    ];
}

/**
 * @param array<int|string, array<string, mixed>> $rows
 * @return array<string, mixed>
 */
function libsqlite_current_next68_record(array $rows, string $output, ?int $expected = null, string $evidenceHead = 'd921ec13f1e9ff4a604df7af85002135e715654c', string $processSnapshot = ''): array
{
    return SQLiteUpstreamSuiteEvidence::fromManifestPath(__DIR__ . '/../UPSTREAM_TEST_MANIFEST.json')
        ->suiteDenominatorCurrentNext68(
            $rows,
            463,
            25285,
            'd921ec13f1e9ff4a604df7af85002135e715654c',
            $evidenceHead,
            'lanes/libsqlite/tests/SQLiteSuiteDenominatorCurrentNext68Test.php',
            $output,
            'current-next68 suite denominator artifact admission avoids accepted current-next64/65 denominator gates, batch66 behavior handoffs, release/all parity closure, active-runner pgrep filtering, and SQL/JSON/WAL/B-tree/VFS implementation clusters.',
            $expected,
            $processSnapshot
        );
}

$tests = [
    'current next68 admits accepted head artifact denominator movement' => static function (TestRunner $t): void {
        $record = libsqlite_current_next68_record(libsqlite_current_next68_rows(), libsqlite_current_next68_output(68, 90), 68);

        $t->same('current-next68-suite-denominator-countable', $record['status']);
        $t->same(true, $record['countable']);
        $t->same(463, $record['current_mapped']);
        $t->same(464, $record['next_mapped']);
        $t->same(1, $record['mapped_delta']);
        $t->same(68, $record['php_pass_delta']);
        $t->same(25353, $record['next_php_pass']);
        $t->same(false, $record['counts_release_parity']);
        $t->same(['current-next68-focused-artifact-1'], $record['advanced_units']);
        $t->same(['accepted-focused-baseline'], $record['preserved_units']);
    },
    'current next68 preserves denominator when no artifact advances' => static function (TestRunner $t): void {
        $rows = libsqlite_current_next68_rows();
        $rows[0]['current_status'] = 'countable';

        $record = libsqlite_current_next68_record($rows, libsqlite_current_next68_output(4, 12), 4);

        $t->same('current-next68-suite-denominator-preserved', $record['status']);
        $t->same(0, $record['mapped_delta']);
        $t->same(4, $record['php_pass_delta']);
        $t->same(25353, libsqlite_current_next68_record(libsqlite_current_next68_rows(), libsqlite_current_next68_output(68, 90), 68)['next_php_pass']);
    },
    'current next68 records scripts categories and test deltas' => static function (TestRunner $t): void {
        $rows = libsqlite_current_next68_rows(2);
        $rows[] = [
            'unit' => 'current-next68-json-artifact',
            'category' => 'json-planner-artifact',
            'repository_head' => 'd921ec13f1e9ff4a604df7af85002135e715654c',
            'current_status' => 'missing',
            'next_status' => 'countable',
            'artifact_path' => 'lanes/libsqlite/notes/suite-denominator-current-next68.md',
            'scripts' => ['json101.test', 'json102.test', 'not-counted.txt'],
            'current_tests' => 0,
            'next_tests' => 33,
            'evidence' => 'second artifact row proves unique script aggregation and category accounting',
        ];

        $record = libsqlite_current_next68_record($rows, libsqlite_current_next68_output(6, 18), 6);

        $t->same('current-next68-suite-denominator-countable', $record['status']);
        $t->same(2, $record['mapped_delta']);
        $t->same(['focused-current-head-artifact' => 1, 'json-planner-artifact' => 1, 'preserved-current-head-artifact' => 1], $record['categories']);
        $t->same(['json101.test', 'json102.test', 'select1.test', 'veryquick.test', 'where.test'], $record['target_scripts']);
        $t->same((1102 + 329670 + 33) - 329670, $record['tests_total_delta']);
    },
    'current next68 blocks stale focused output head' => static function (TestRunner $t): void {
        $record = libsqlite_current_next68_record(libsqlite_current_next68_rows(), libsqlite_current_next68_output(3, 9), 3, evidenceHead: '0000000000000000000000000000000000000000');

        $t->same('blocked', $record['status']);
        $t->same(0, $record['mapped_delta']);
        $t->same(0, $record['php_pass_delta']);
        $t->true(in_array('focused-current-head-php-pass-blocked', array_column($record['blockers'], 'id'), true), 'Expected focused head blocker');
        $t->same('blocked', $record['php_pass_admission']['status']);
    },
    'current next68 blocks stale artifact head' => static function (TestRunner $t): void {
        $rows = libsqlite_current_next68_rows();
        $rows[0]['repository_head'] = '0000000000000000000000000000000000000000';

        $record = libsqlite_current_next68_record($rows, libsqlite_current_next68_output(3, 9), 3);

        $t->same('blocked', $record['status']);
        $t->same(['current-next68-focused-artifact-1'], $record['blocked_units']);
        $t->contains('artifact-head-mismatch', $record['blockers'][0]['evidence']);
    },
    'current next68 blocks duplicate artifact rows' => static function (TestRunner $t): void {
        $rows = libsqlite_current_next68_rows();
        $rows[] = $rows[0];

        $record = libsqlite_current_next68_record($rows, libsqlite_current_next68_output(3, 9), 3);

        $t->same('blocked', $record['status']);
        $t->contains('duplicate-artifact-unit', $record['blockers'][0]['evidence']);
    },
    'current next68 blocks missing script evidence' => static function (TestRunner $t): void {
        $rows = libsqlite_current_next68_rows();
        $rows[0]['scripts'] = ['not-a-test.txt'];

        $record = libsqlite_current_next68_record($rows, libsqlite_current_next68_output(3, 9), 3);

        $t->same('blocked', $record['status']);
        $t->contains('countable-artifact-missing-test-scripts', $record['blockers'][0]['evidence']);
    },
    'current next68 blocks artifact regressions' => static function (TestRunner $t): void {
        $rows = libsqlite_current_next68_rows();
        $rows[1]['next_status'] = 'missing';
        $rows[1]['next_tests'] = 10;

        $record = libsqlite_current_next68_record($rows, libsqlite_current_next68_output(3, 9), 3);

        $t->same('blocked', $record['status']);
        $t->same(['accepted-focused-baseline'], $record['regressed_units']);
        $t->contains('countable-artifact-regressed', $record['blockers'][0]['evidence']);
    },
    'current next68 blocks active broad runner snapshots' => static function (TestRunner $t): void {
        $record = libsqlite_current_next68_record(
            libsqlite_current_next68_rows(),
            libsqlite_current_next68_output(2, 8),
            2,
            processSnapshot: '12345 /bin/sh scripts/run-sqlite-tcl-bounded-runner.sh --testset release --foreground'
        );

        $t->same('blocked', $record['status']);
        $t->same('blocked-active-runner', $record['active_runner_status']);
        $t->same(1, $record['active_runner_count']);
        $t->true(in_array('duplicate-broad-runner-active', array_column($record['blockers'], 'id'), true), 'Expected duplicate runner blocker');
    },
    'current next68 blocks pass line inflation' => static function (TestRunner $t): void {
        $record = libsqlite_current_next68_record(libsqlite_current_next68_rows(), libsqlite_current_next68_output(4, 40), 40);

        $t->same('blocked', $record['status']);
        $t->same(4, $record['php_pass_admission']['pass_lines_observed']);
        $t->same(40, $record['php_pass_admission']['assertion_count_observed']);
        $t->true(in_array('focused-current-head-php-pass-blocked', array_column($record['blockers'], 'id'), true), 'Expected focused PASS blocker');
    },
    'current next68 rejects empty artifact rows' => static function (TestRunner $t): void {
        $t->throws(
            InvalidArgumentException::class,
            static fn () => libsqlite_current_next68_record([], libsqlite_current_next68_output(1, 3), 1)
        );
    },
    'current next68 rejects negative mapped baseline' => static function (TestRunner $t): void {
        $evidence = SQLiteUpstreamSuiteEvidence::fromManifestPath(__DIR__ . '/../UPSTREAM_TEST_MANIFEST.json');

        $t->throws(
            InvalidArgumentException::class,
            static fn () => $evidence->suiteDenominatorCurrentNext68(
                libsqlite_current_next68_rows(),
                -1,
                25285,
                'd921ec13f1e9ff4a604df7af85002135e715654c',
                'd921ec13f1e9ff4a604df7af85002135e715654c',
                'lanes/libsqlite/tests/SQLiteSuiteDenominatorCurrentNext68Test.php',
                libsqlite_current_next68_output(1, 3),
                'non-overlap',
                1
            )
        );
    },
];

foreach (range(1, 68) as $case) {
    $tests[sprintf('current next68 exact artifact pass matrix %02d', $case)] = static function (TestRunner $t) use ($case): void {
        $record = libsqlite_current_next68_record(libsqlite_current_next68_rows($case), libsqlite_current_next68_output($case, $case + 20), $case);

        $t->same('current-next68-suite-denominator-countable', $record['status']);
        $t->same($case, $record['php_pass_delta']);
        $t->same(25285 + $case, $record['next_php_pass']);
        $t->same(1, $record['mapped_delta']);
        $t->same(['current-next68-focused-artifact-' . $case], $record['advanced_units']);
        $t->same($case, $record['php_pass_admission']['pass_lines_observed']);
        $t->same(0, $record['blocker_count']);
    };
}

return $tests;
