<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteUpstreamSuiteEvidence;

function libsqlite_current_next56_output(int $passLines, int $assertions, int $failures = 0, int $selected = 1, int $summaryFiles = 1): string
{
    $lines = [
        sprintf('Focused test run: %d selected test files (root lock skipped)', $selected),
    ];
    for ($i = 1; $i <= $passLines; $i++) {
        $lines[] = sprintf('PASS current next56 focused case %02d', $i);
    }
    $lines[] = sprintf('%d test files, %d assertions, %d failures', $summaryFiles, $assertions, $failures);

    return implode("\n", $lines);
}

function libsqlite_current_next56_admission(string $output, string $accepted = 'aa5c67a8d70941079503fe746744a6952caec0a5', string $evidence = 'aa5c67a8d70941079503fe746744a6952caec0a5', ?int $expected = null): array
{
    return SQLiteUpstreamSuiteEvidence::fromManifestPath(__DIR__ . '/../UPSTREAM_TEST_MANIFEST.json')
        ->focusedPhpPassCurrentHeadAdmission(
            20008,
            $accepted,
            $evidence,
            'lanes/libsqlite/tests/SQLiteUpstreamSuiteAdmissionCurrentNext56Test.php',
            $output,
            'Avoids accepted release/all closure, focused runner artifact admission, artifact directory provenance, denominator audit, and behavior clusters; this admits only current-HEAD focused PASS lines.',
            $expected
        );
}

$tests = [
    'current next56 admits current head focused pass lines rather than assertion totals' => static function (TestRunner $t): void {
        $record = libsqlite_current_next56_admission(libsqlite_current_next56_output(3, 12), expected: 3);

        $t->same('current-head-focused-pass-countable', $record['status']);
        $t->same(true, $record['countable']);
        $t->same(3, $record['pass_delta']);
        $t->same(12, $record['assertion_count_observed']);
        $t->same(3, $record['pass_lines_observed']);
        $t->same(20011, $record['next_php_pass']);
        $t->same(0, $record['blocker_count']);
        $t->contains('exact focused PASS-line delta', $record['next_gate']);
    },
    'current next56 blocks stale repository head before pass movement' => static function (TestRunner $t): void {
        $record = libsqlite_current_next56_admission(
            libsqlite_current_next56_output(2, 6),
            evidence: '1111111111111111111111111111111111111111',
            expected: 2
        );

        $t->same('blocked', $record['status']);
        $t->same(false, $record['countable']);
        $t->same(0, $record['pass_delta']);
        $t->same(20008, $record['next_php_pass']);
        $t->same(['repository-head-mismatch'], array_column($record['blockers'], 'id'));
        $t->same('1111111111111111111111111111111111111111', $record['blockers'][0]['actual']);
    },
    'current next56 blocks assertion inflation when pass delta does not match expectation' => static function (TestRunner $t): void {
        $record = libsqlite_current_next56_admission(libsqlite_current_next56_output(4, 40), expected: 40);

        $t->same('blocked', $record['status']);
        $t->same(0, $record['pass_delta']);
        $t->same(4, $record['pass_lines_observed']);
        $t->same(40, $record['assertion_count_observed']);
        $t->same(['focused-pass-delta-mismatch'], array_column($record['blockers'], 'id'));
        $t->same(4, $record['blockers'][0]['actual']);
    },
    'current next56 blocks root output without focused marker' => static function (TestRunner $t): void {
        $record = libsqlite_current_next56_admission("PASS root all\n1 test files, 1 assertions, 0 failures", expected: 1);

        $t->same('blocked', $record['status']);
        $t->same(false, $record['focused_output_seen']);
        $t->same(0, $record['selected_test_files']);
        $t->true(in_array('missing-focused-testrunner-output', array_column($record['blockers'], 'id'), true), 'Expected focused marker blocker');
    },
    'current next56 blocks multi file focused output' => static function (TestRunner $t): void {
        $record = libsqlite_current_next56_admission(libsqlite_current_next56_output(2, 2, selected: 2, summaryFiles: 2), expected: 2);

        $t->same('blocked', $record['status']);
        $t->same(2, $record['selected_test_files']);
        $t->same(2, $record['summary_test_files']);
        $t->same(['focused-test-file-count-not-one'], array_column($record['blockers'], 'id'));
    },
    'current next56 blocks failing focused output' => static function (TestRunner $t): void {
        $record = libsqlite_current_next56_admission(libsqlite_current_next56_output(2, 5, failures: 1), expected: 2);

        $t->same('blocked', $record['status']);
        $t->same(1, $record['failures']);
        $t->same(['focused-output-has-failures'], array_column($record['blockers'], 'id'));
    },
    'current next56 blocks output with no pass lines' => static function (TestRunner $t): void {
        $record = libsqlite_current_next56_admission(libsqlite_current_next56_output(0, 5), expected: 0);

        $t->same('blocked', $record['status']);
        $t->same(0, $record['pass_lines_observed']);
        $t->same(['focused-pass-lines-missing'], array_column($record['blockers'], 'id'));
    },
    'current next56 preserves blocker set for combined stale failing output' => static function (TestRunner $t): void {
        $record = libsqlite_current_next56_admission(
            libsqlite_current_next56_output(1, 3, failures: 1),
            evidence: '2222222222222222222222222222222222222222',
            expected: 7
        );

        $ids = array_column($record['blockers'], 'id');
        sort($ids);
        $t->same(['focused-output-has-failures', 'focused-pass-delta-mismatch', 'repository-head-mismatch'], $ids);
        $t->same(0, $record['pass_delta']);
        $t->same(20008, $record['next_php_pass']);
    },
    'current next56 rejects missing accepted repository head' => static function (TestRunner $t): void {
        $t->throws(
            InvalidArgumentException::class,
            static fn () => libsqlite_current_next56_admission(libsqlite_current_next56_output(1, 1), accepted: '')
        );
    },
    'current next56 rejects missing evidence repository head' => static function (TestRunner $t): void {
        $t->throws(
            InvalidArgumentException::class,
            static fn () => libsqlite_current_next56_admission(libsqlite_current_next56_output(1, 1), evidence: '')
        );
    },
    'current next56 rejects negative expected pass delta' => static function (TestRunner $t): void {
        $t->throws(
            InvalidArgumentException::class,
            static fn () => libsqlite_current_next56_admission(libsqlite_current_next56_output(1, 1), expected: -1)
        );
    },
];

foreach (range(1, 45) as $passCount) {
    $tests[sprintf('current next56 matrix admits exact pass delta %02d', $passCount)] = static function (TestRunner $t) use ($passCount): void {
        $assertions = $passCount + 7;
        $record = libsqlite_current_next56_admission(
            libsqlite_current_next56_output($passCount, $assertions),
            expected: $passCount
        );

        $t->same('current-head-focused-pass-countable', $record['status']);
        $t->same($passCount, $record['pass_delta']);
        $t->same($assertions, $record['assertion_count_observed']);
        $t->same(20008 + $passCount, $record['next_php_pass']);
        $t->same(0, $record['blocker_count']);
    };
}

return $tests;
