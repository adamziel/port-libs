<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteUpstreamSuiteEvidence;

function libsqlite_suite_denominator69_evidence(): SQLiteUpstreamSuiteEvidence
{
    return SQLiteUpstreamSuiteEvidence::fromManifestPath(__DIR__ . '/../UPSTREAM_TEST_MANIFEST.json');
}

function libsqlite_suite_denominator69_output(int $passLines = 69, int $assertions = 420, int $failures = 0): string
{
    $lines = ['Focused test run: 1 selected test files (root lock skipped)'];
    for ($i = 1; $i <= $passLines; $i++) {
        $lines[] = sprintf('PASS current next69 denominator freshness case %02d', $i);
    }
    $lines[] = sprintf('1 test files, %d assertions, %d failures', $assertions, $failures);

    return implode("\n", $lines);
}

function libsqlite_suite_denominator69_rows(int $case = 1, string $head = '088988b10e55d1e3ad800154b4f8e01eb3640cbb'): array
{
    $script = sprintf('current-next69-denominator-%02d.test', $case);

    return [
        [
            'unit' => 'suite-denominator-current-next69-focused-pass',
            'category' => 'suite-denominator',
            'current_mapped' => 0,
            'next_mapped' => 0,
            'current_countable_scripts' => 0,
            'next_countable_scripts' => 1,
            'denominator_total' => 1,
            'scripts' => [$script],
            'source_head' => $head,
            'runner_command' => './testfixture ../libsqlite/test/testrunner.tcl --jobs 1 --stop-on-error current-next69 ' . $script,
            'artifact_status' => 'focused-pass',
            'evidence' => 'current-next69 focused PASS output is countable for phpPass only; no release/all parity is claimed',
        ],
        [
            'unit' => 'suite-denominator-current-next69-accepted-anchor',
            'category' => 'accepted-head-provenance',
            'current_mapped' => 463,
            'next_mapped' => 463,
            'current_countable_scripts' => 8,
            'next_countable_scripts' => 8,
            'denominator_total' => 1589,
            'scripts' => ['pager.test', 'wal.test'],
            'source_head' => $head,
            'runner_command' => './testfixture ../libsqlite/test/testrunner.tcl --jobs 1 --stop-on-error pager.test wal.test',
            'artifact_status' => 'preserved-current-head',
            'evidence' => 'accepted batch68 denominator anchor stays preserved while current-next69 admits only focused PASS-line movement',
        ],
    ];
}

function libsqlite_suite_denominator69_record(array $rows, string $accepted = '088988b10e55d1e3ad800154b4f8e01eb3640cbb', string $evidence = '088988b10e55d1e3ad800154b4f8e01eb3640cbb', string $output = null, ?int $expected = 69, string $snapshot = ''): array
{
    return libsqlite_suite_denominator69_evidence()->suiteDenominatorShardAudit(
        $rows,
        463,
        25580,
        $accepted,
        $evidence,
        'lanes/libsqlite/tests/SQLiteSuiteDenominatorCurrentNext69Test.php',
        $output ?? libsqlite_suite_denominator69_output(),
        'current-next69 suite denominator freshness avoids accepted release/all parity ledgers, current-next65 denominator admission, current-next56 PASS admission, batch68 pager savepoint release-next, queued batch69 behavior surfaces, and SQL/JSON/WAL/B-tree/VFS runtime clusters',
        $expected,
        $snapshot
    );
}

$tests = [];

foreach (range(1, 69) as $case) {
    $tests[sprintf('current next69 admits fresh denominator focused case %02d', $case)] = static function (TestRunner $t) use ($case): void {
        $record = libsqlite_suite_denominator69_record(libsqlite_suite_denominator69_rows($case));

        $t->same('current-next69-denominator-current-source-countable', $record['status']);
        $t->same(true, $record['countable']);
        $t->same(0, $record['mapped_delta']);
        $t->same(463, $record['next_mapped']);
        $t->same(1, $record['countable_script_delta']);
        $t->same(69, $record['php_pass_delta']);
        $t->same(25649, $record['next_php_pass']);
        $t->same(2, $record['current_next69_fresh_row_count']);
        $t->same(2, $record['current_next69_command_row_count']);
        $t->same(2, $record['current_next69_artifact_row_count']);
        $t->same(0, $record['blocker_count']);
        $t->same(false, $record['counts_release_parity']);
        $t->contains(sprintf('current-next69-denominator-%02d.test', $case), implode(',', $record['target_scripts']));
    };
}

$tests['current next69 preserves denominator category totals'] = static function (TestRunner $t): void {
    $record = libsqlite_suite_denominator69_record(libsqlite_suite_denominator69_rows());

    $t->same(2, $record['category_count']);
    $t->same(1, $record['categories']['suite-denominator']);
    $t->same(1, $record['categories']['accepted-head-provenance']);
    $t->same(3, $record['target_script_count']);
    $t->same(['suite-denominator-current-next69-focused-pass'], $record['advanced_units']);
    $t->same(['suite-denominator-current-next69-accepted-anchor'], $record['preserved_units']);
};

$tests['current next69 blocks stale row source head'] = static function (TestRunner $t): void {
    $rows = libsqlite_suite_denominator69_rows();
    $rows[0]['source_head'] = '1111111111111111111111111111111111111111';

    $record = libsqlite_suite_denominator69_record($rows);

    $t->same('blocked', $record['status']);
    $t->same(0, $record['php_pass_delta']);
    $t->same(25580, $record['next_php_pass']);
    $t->same(['suite-denominator-current-next69-focused-pass'], $record['current_next69_blocked_units']);
    $t->contains('source-head-mismatch', $record['blockers'][0]['evidence']);
};

$tests['current next69 blocks stale focused evidence head'] = static function (TestRunner $t): void {
    $record = libsqlite_suite_denominator69_record(
        libsqlite_suite_denominator69_rows(),
        evidence: '2222222222222222222222222222222222222222'
    );

    $t->same('blocked', $record['status']);
    $t->same(0, $record['php_pass_delta']);
    $t->true(in_array('focused-current-head-php-pass-blocked', array_column($record['blockers'], 'id'), true), 'Expected focused current-head blocker');
    $t->same('repository-head-mismatch', $record['php_pass_admission']['blockers'][0]['id']);
};

$tests['current next69 blocks missing runner command'] = static function (TestRunner $t): void {
    $rows = libsqlite_suite_denominator69_rows();
    $rows[0]['runner_command'] = './testfixture current-next69-denominator-01.test';

    $record = libsqlite_suite_denominator69_record($rows);

    $t->same('blocked', $record['status']);
    $t->same(['suite-denominator-current-next69-focused-pass'], $record['current_next69_blocked_units']);
    $t->contains('runner-command-missing', $record['blockers'][0]['evidence']);
};

$tests['current next69 blocks non countable artifact status'] = static function (TestRunner $t): void {
    $rows = libsqlite_suite_denominator69_rows();
    $rows[0]['artifact_status'] = 'running';

    $record = libsqlite_suite_denominator69_record($rows);

    $t->same('blocked', $record['status']);
    $t->contains('artifact-status-not-countable', $record['blockers'][0]['evidence']);
};

$tests['current next69 blocks release parity claims'] = static function (TestRunner $t): void {
    $rows = libsqlite_suite_denominator69_rows();
    $rows[0]['counts_release_parity'] = true;

    $record = libsqlite_suite_denominator69_record($rows);

    $t->same('blocked', $record['status']);
    $t->same(false, $record['counts_release_parity']);
    $t->contains('release-parity-claim-not-allowed', $record['blockers'][0]['evidence']);
};

$tests['current next69 blocks duplicate unit ids'] = static function (TestRunner $t): void {
    $rows = libsqlite_suite_denominator69_rows();
    $rows[] = $rows[0];

    $record = libsqlite_suite_denominator69_record($rows);

    $t->same('blocked', $record['status']);
    $t->true(in_array('duplicate-current-next69-unit', array_column($record['blockers'], 'id'), true), 'Expected duplicate unit blocker');
};

$tests['current next69 blocks active broad runner'] = static function (TestRunner $t): void {
    $record = libsqlite_suite_denominator69_record(
        libsqlite_suite_denominator69_rows(),
        snapshot: "900 1 S 00:31 0.1 ./testfixture ../src/test/testrunner.tcl --jobs 2 --stop-on-error all\n"
    );

    $t->same('blocked', $record['status']);
    $t->same('blocked-active-runner', $record['active_runner_status']);
    $t->same(1, $record['active_runner_count']);
    $t->true(in_array('duplicate-broad-runner-active', array_column($record['blockers'], 'id'), true), 'Expected duplicate broad-runner blocker');
};

$tests['current next69 blocks pass delta inflation'] = static function (TestRunner $t): void {
    $record = libsqlite_suite_denominator69_record(
        libsqlite_suite_denominator69_rows(),
        output: libsqlite_suite_denominator69_output(passLines: 12, assertions: 420),
        expected: 69
    );

    $t->same('blocked', $record['status']);
    $t->same(12, $record['php_pass_admission']['pass_lines_observed']);
    $t->same(0, $record['php_pass_delta']);
    $t->same('focused-pass-delta-mismatch', $record['php_pass_admission']['blockers'][0]['id']);
};

$tests['current next69 blocks unfocused output'] = static function (TestRunner $t): void {
    $record = libsqlite_suite_denominator69_record(
        libsqlite_suite_denominator69_rows(),
        output: "PASS current next69 unfocused\n1 test files, 10 assertions, 0 failures\n",
        expected: 1
    );

    $t->same('blocked', $record['status']);
    $t->same(false, $record['php_pass_admission']['focused_output_seen']);
    $t->true(in_array('missing-focused-testrunner-output', array_column($record['php_pass_admission']['blockers'], 'id'), true), 'Expected focused output blocker');
};

$tests['current next69 rejects empty rows'] = static function (TestRunner $t): void {
    $t->throws(
        InvalidArgumentException::class,
        static fn () => libsqlite_suite_denominator69_record([])
    );
};

$tests['current next69 rejects negative mapped count through base gate'] = static function (TestRunner $t): void {
    $t->throws(
        InvalidArgumentException::class,
        static fn () => libsqlite_suite_denominator69_evidence()->suiteDenominatorShardAudit(
            libsqlite_suite_denominator69_rows(),
            -1,
            25580,
            '088988b10e55d1e3ad800154b4f8e01eb3640cbb',
            '088988b10e55d1e3ad800154b4f8e01eb3640cbb',
            'lanes/libsqlite/tests/SQLiteSuiteDenominatorCurrentNext69Test.php',
            libsqlite_suite_denominator69_output(),
            'current-next69 non-overlap',
            69
        )
    );
};

$tests['current next69 records dependency closure and next gate'] = static function (TestRunner $t): void {
    $record = libsqlite_suite_denominator69_record(libsqlite_suite_denominator69_rows());

    $t->contains('current-next69 denominator freshness', $record['dependency_closure']);
    $t->contains('source-head', $record['next_gate']);
    $t->contains('release/all parity remains unclaimed', $record['next_gate']);
    $t->contains('avoids accepted release/all parity ledgers', $record['non_overlap_note']);
};

return $tests;
