<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteUpstreamSuiteEvidence;

function libsqlite_current_next67_output(int $passLines, int $assertions, int $failures = 0, int $selected = 1, int $summaryFiles = 1): string
{
    $lines = [
        sprintf('Focused test run: %d selected test files (root lock skipped)', $selected),
    ];
    for ($i = 1; $i <= $passLines; $i++) {
        $lines[] = sprintf('PASS current next67 suite denominator case %02d', $i);
    }
    $lines[] = sprintf('%d test files, %d assertions, %d failures', $summaryFiles, $assertions, $failures);

    return implode("\n", $lines);
}

/**
 * @return list<array<string, mixed>>
 */
function libsqlite_current_next67_rows(int $mappedDelta = 1, int $scriptDelta = 1): array
{
    return [
        [
            'unit' => 'batch65-focused-pass-anchor',
            'family' => 'accepted-focused-pass',
            'state' => 'current-preserved',
            'current_mapped' => 463,
            'next_mapped' => 463,
            'current_countable_scripts' => 113,
            'next_countable_scripts' => 113,
            'scripts' => ['select1.test', 'where.test'],
            'hydrated' => true,
            'evidence' => 'batch65 focused PASS admission remains the current accepted source anchor',
        ],
        [
            'unit' => 'suite67-current-next-denominator',
            'family' => 'suite-denominator',
            'state' => 'next-ready',
            'current_mapped' => 0,
            'next_mapped' => $mappedDelta,
            'current_countable_scripts' => 0,
            'next_countable_scripts' => $scriptDelta,
            'scripts' => ['json101.test', 'pager1.test', 'wal.test'],
            'hydrated' => true,
            'evidence' => 'current-next67 admits one queued focused denominator row with current-head TestRunner output but does not claim release/all parity',
        ],
        [
            'unit' => 'release-all-parity-still-gated',
            'family' => 'release-all',
            'state' => 'duplicate-accepted',
            'current_mapped' => 0,
            'next_mapped' => 0,
            'current_countable_scripts' => 0,
            'next_countable_scripts' => 0,
            'scripts' => ['releasetest.tcl'],
            'hydrated' => true,
            'evidence' => 'release/all parity remains gated; this row only records that stale broad-suite ledger text must not inflate mapped coverage',
        ],
    ];
}

/**
 * @param list<array<string, mixed>> $rows
 * @return array<string, mixed>
 */
function libsqlite_current_next67_record(array $rows, string $output, ?int $expected = null, string $accepted = '70ce5c6331ef3fe98a080164104ff79d76df9f44', string $evidence = '70ce5c6331ef3fe98a080164104ff79d76df9f44', string $processSnapshot = ''): array
{
    return SQLiteUpstreamSuiteEvidence::fromManifestPath(__DIR__ . '/../UPSTREAM_TEST_MANIFEST.json')
        ->suiteDenominatorCurrentNext67(
            $rows,
            463,
            25055,
            $accepted,
            $evidence,
            'lanes/libsqlite/tests/SQLiteSuiteDenominatorCurrentNext67Test.php',
            $output,
            'current-next67 suite denominator avoids accepted batch64/65 suite admission, current-next56 focused PASS admission, batch52-55 denominator burnup ledgers, release/all parity claims, and SQL/JSON/WAL/B-tree/VFS behavior clusters; it classifies current, queued, and duplicate accepted rows only.',
            $expected,
            $processSnapshot
        );
}

$tests = [
    'current next67 admits queued denominator row while preserving accepted anchors' => static function (TestRunner $t): void {
        $record = libsqlite_current_next67_record(libsqlite_current_next67_rows(), libsqlite_current_next67_output(7, 19), 7);

        $t->same('current-next67-denominator-admitted', $record['status']);
        $t->same(true, $record['countable']);
        $t->same(463, $record['current_mapped']);
        $t->same(464, $record['next_mapped']);
        $t->same(1, $record['mapped_delta']);
        $t->same(1, $record['countable_script_delta']);
        $t->same(7, $record['php_pass_delta']);
        $t->same(25062, $record['next_php_pass']);
        $t->same(false, $record['counts_release_parity']);
        $t->same(true, $record['counts_suite_denominator_current_next67']);
    },
    'current next67 separates current queued and duplicate accepted row classes' => static function (TestRunner $t): void {
        $record = libsqlite_current_next67_record(libsqlite_current_next67_rows(2, 3), libsqlite_current_next67_output(5, 17), 5);

        $t->same(['batch65-focused-pass-anchor'], $record['current_units']);
        $t->same(['suite67-current-next-denominator'], $record['next_ready_units']);
        $t->same(['release-all-parity-still-gated'], $record['duplicate_accepted_units']);
        $t->same(1, $record['current_preserved_rows']);
        $t->same(1, $record['next_ready_rows']);
        $t->same(1, $record['duplicate_accepted_rows']);
        $t->same(['accepted-focused-pass' => 1, 'release-all' => 1, 'suite-denominator' => 1], $record['families']);
    },
    'current next67 records unique target scripts across row classes' => static function (TestRunner $t): void {
        $record = libsqlite_current_next67_record(libsqlite_current_next67_rows(2, 2), libsqlite_current_next67_output(4, 12), 4);

        $t->same(['json101.test', 'pager1.test', 'select1.test', 'wal.test', 'where.test'], $record['target_scripts']);
        $t->same(5, $record['target_script_count']);
        $t->same(3, $record['row_count']);
        $t->same(3, $record['family_count']);
    },
    'current next67 blocks stale current head evidence' => static function (TestRunner $t): void {
        $record = libsqlite_current_next67_record(
            libsqlite_current_next67_rows(),
            libsqlite_current_next67_output(3, 9),
            3,
            evidence: '0000000000000000000000000000000000000000'
        );

        $t->same('blocked', $record['status']);
        $t->same(463, $record['next_mapped']);
        $t->same(0, $record['php_pass_delta']);
        $t->same(['focused-current-head-php-pass-blocked'], array_column($record['blockers'], 'id'));
        $t->same('blocked', $record['php_pass_admission']['status']);
    },
    'current next67 blocks focused pass inflation from assertion counts' => static function (TestRunner $t): void {
        $record = libsqlite_current_next67_record(libsqlite_current_next67_rows(), libsqlite_current_next67_output(4, 40), 40);

        $t->same('blocked', $record['status']);
        $t->same(4, $record['php_pass_admission']['pass_lines_observed']);
        $t->same(40, $record['php_pass_admission']['assertion_count_observed']);
        $t->same(0, $record['mapped_delta']);
        $t->same(0, $record['php_pass_delta']);
        $t->true(in_array('focused-current-head-php-pass-blocked', array_column($record['blockers'], 'id'), true), 'Expected focused admission blocker');
    },
    'current next67 blocks duplicate accepted rows that claim new movement' => static function (TestRunner $t): void {
        $rows = libsqlite_current_next67_rows();
        $rows[2]['next_mapped'] = 1;
        $rows[2]['next_countable_scripts'] = 1;

        $record = libsqlite_current_next67_record($rows, libsqlite_current_next67_output(4, 10), 4);

        $t->same('blocked', $record['status']);
        $t->same(['release-all-parity-still-gated'], $record['blocked_units']);
        $t->contains('duplicate-accepted-row-claims-new-movement', $record['blockers'][0]['evidence']);
    },
    'current next67 blocks next ready rows without countable movement' => static function (TestRunner $t): void {
        $rows = libsqlite_current_next67_rows(0, 0);

        $record = libsqlite_current_next67_record($rows, libsqlite_current_next67_output(4, 10), 4);

        $t->same('blocked', $record['status']);
        $t->same(['suite67-current-next-denominator'], $record['blocked_units']);
        $t->contains('next-ready-row-has-no-countable-delta', $record['blockers'][0]['evidence']);
    },
    'current next67 blocks missing row evidence' => static function (TestRunner $t): void {
        $rows = libsqlite_current_next67_rows();
        $rows[1]['evidence'] = '';

        $record = libsqlite_current_next67_record($rows, libsqlite_current_next67_output(4, 10), 4);

        $t->same('blocked', $record['status']);
        $t->same(['suite67-current-next-denominator'], $record['blocked_units']);
        $t->contains('missing-row-evidence', $record['blockers'][0]['evidence']);
    },
    'current next67 blocks non hydrated rows' => static function (TestRunner $t): void {
        $rows = libsqlite_current_next67_rows();
        $rows[1]['hydrated'] = false;

        $record = libsqlite_current_next67_record($rows, libsqlite_current_next67_output(4, 10), 4);

        $t->same('blocked', $record['status']);
        $t->contains('denominator-row-not-hydrated', $record['blockers'][0]['evidence']);
    },
    'current next67 blocks mapped and script regressions' => static function (TestRunner $t): void {
        $rows = libsqlite_current_next67_rows();
        $rows[0]['next_mapped'] = 462;
        $rows[0]['next_countable_scripts'] = 112;

        $record = libsqlite_current_next67_record($rows, libsqlite_current_next67_output(4, 10), 4);

        $t->same('blocked', $record['status']);
        $t->contains('mapped-count-regressed', $record['blockers'][0]['evidence']);
        $t->contains('countable-script-count-regressed', $record['blockers'][0]['evidence']);
    },
    'current next67 blocks duplicate broad runner snapshots' => static function (TestRunner $t): void {
        $record = libsqlite_current_next67_record(
            libsqlite_current_next67_rows(),
            libsqlite_current_next67_output(2, 8),
            2,
            processSnapshot: '12345 /bin/sh scripts/run-sqlite-tcl-bounded-runner.sh --testset all --foreground'
        );

        $t->same('blocked', $record['status']);
        $t->same('blocked-active-runner', $record['active_runner_status']);
        $t->same(1, $record['active_runner_count']);
        $t->true(in_array('duplicate-broad-runner-active', array_column($record['blockers'], 'id'), true), 'Expected active runner blocker');
    },
    'current next67 rejects invalid setup' => static function (TestRunner $t): void {
        $evidence = SQLiteUpstreamSuiteEvidence::fromManifestPath(__DIR__ . '/../UPSTREAM_TEST_MANIFEST.json');

        $t->throws(InvalidArgumentException::class, static fn () => libsqlite_current_next67_record([], libsqlite_current_next67_output(1, 3), 1));
        $t->throws(InvalidArgumentException::class, static fn () => $evidence->suiteDenominatorCurrentNext67(libsqlite_current_next67_rows(), -1, 25055, '70ce5c6331ef3fe98a080164104ff79d76df9f44', '70ce5c6331ef3fe98a080164104ff79d76df9f44', 'lanes/libsqlite/tests/SQLiteSuiteDenominatorCurrentNext67Test.php', libsqlite_current_next67_output(1, 3), 'non-overlap', 1));
    },
];

foreach (range(1, 55) as $delta) {
    $tests[sprintf('current next67 exact focused pass matrix %02d', $delta)] = static function (TestRunner $t) use ($delta): void {
        $record = libsqlite_current_next67_record(
            libsqlite_current_next67_rows(($delta % 4) + 1, ($delta % 5) + 1),
            libsqlite_current_next67_output($delta, $delta + 13),
            $delta
        );

        $t->same('current-next67-denominator-admitted', $record['status']);
        $t->same($delta, $record['php_pass_delta']);
        $t->same(25055 + $delta, $record['next_php_pass']);
        $t->same($delta, $record['php_pass_admission']['pass_lines_observed']);
        $t->same($delta + 13, $record['php_pass_admission']['assertion_count_observed']);
        $t->same(0, $record['blocker_count']);
        $t->same(false, $record['counts_release_parity']);
    };
}

return $tests;
