<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteUpstreamSuiteEvidence;

function libsqlite_bulk_suite_b_evidence(): SQLiteUpstreamSuiteEvidence
{
    return SQLiteUpstreamSuiteEvidence::fromManifestPath(__DIR__ . '/../UPSTREAM_TEST_MANIFEST.json');
}

function libsqlite_bulk_suite_b_output(int $assertions = 128, int $failures = 0): string
{
    $lines = ['Focused test run: 1 selected test files (root lock skipped)'];
    for ($i = 1; $i <= $assertions; $i++) {
        $lines[] = sprintf('PASS bulk upstream suite denominator burnup b case %03d', $i);
    }
    $lines[] = sprintf('1 test files, %d assertions, %d failures', $assertions, $failures);

    return implode("\n", $lines);
}

/**
 * @return list<array<string, mixed>>
 */
function libsqlite_bulk_suite_b_rows(int $count = 128): array
{
    $rows = [];
    $buckets = [
        'veryquick-admission',
        'suite-shard-admission',
        'runner-map-gap',
        'denominator-hydration',
    ];

    for ($i = 1; $i <= $count; $i++) {
        $script = sprintf('bulk-suite-b-%03d.test', $i);
        $rows[] = [
            'id' => sprintf('bulk-upstream-suite-denominator-burnup-b-%03d', $i),
            'bucket' => $buckets[($i - 1) % count($buckets)],
            'current_mapped' => 0,
            'next_mapped' => 1,
            'current_countable_scripts' => 0,
            'next_countable_scripts' => 1,
            'denominator_total' => 1589,
            'hydrated' => true,
            'next_artifact_present' => false,
            'scripts' => [$script],
        ];
    }

    return $rows;
}

function libsqlite_bulk_suite_b_record(array $rows, ?string $output = null, string $snapshot = ''): array
{
    return libsqlite_bulk_suite_b_evidence()->releaseRunnerDenominatorGapBurnup(
        $rows,
        '8160272f871bffaf7a8a48da09598a7f4bfdce9f',
        'bulk-upstream-suite-denominator-burnup-20260530T1530Z-b',
        188353,
        'lanes/libsqlite/tests/SQLiteBulkUpstreamSuiteDenominatorBurnupCurrentSourceBTest.php',
        $output ?? libsqlite_bulk_suite_b_output(),
        'bulk upstream suite denominator burnup b avoids prior veryquick shard next789-916 rows, current-source prepared-octet rows, release/all parity claims, and ordinary SQL/JSON/WAL/B-tree behavior surfaces',
        $snapshot
    );
}

$tests = [];

$tests['bulk upstream suite denominator burnup b admits one hundred twenty eight rows'] = static function (TestRunner $t): void {
    $record = libsqlite_bulk_suite_b_record(libsqlite_bulk_suite_b_rows());

    $t->same('current-next53-denominator-burnup-ready', $record['status']);
    $t->same(128, $record['row_count']);
    $t->same(4, $record['bucket_count']);
    $t->same(203392, $record['denominator_total']);
    $t->same(0, $record['current_mapped_total']);
    $t->same(128, $record['next_mapped_total']);
    $t->same(128, $record['mapped_delta_total']);
    $t->same(0, $record['current_countable_script_total']);
    $t->same(128, $record['next_countable_script_total']);
    $t->same(128, $record['countable_script_delta_total']);
    $t->same(128, $record['advanced_count']);
    $t->same(0, $record['blocked_count']);
    $t->same(128, $record['target_script_count']);
    $t->same(0.06, $record['burnup_percent']);
    $t->same(128, $record['php_pass_delta']);
    $t->same(188481, $record['next_php_pass']);
    $t->same(true, $record['counts_denominator_gap_burnup']);
    $t->same(false, $record['counts_release_parity']);

    foreach ($record['entries'] as $entry) {
        $t->same('advanced', $entry['status']);
    }
};

$tests['bulk upstream suite denominator burnup b preserves exact bucket totals'] = static function (TestRunner $t): void {
    $record = libsqlite_bulk_suite_b_record(libsqlite_bulk_suite_b_rows());
    $buckets = [];
    foreach ($record['buckets'] as $bucket) {
        $buckets[$bucket['bucket']] = $bucket;
    }

    foreach (['denominator-hydration', 'runner-map-gap', 'suite-shard-admission', 'veryquick-admission'] as $bucket) {
        $t->same(32, $buckets[$bucket]['rows']);
        $t->same(32, $buckets[$bucket]['advanced']);
        $t->same(32, $buckets[$bucket]['mapped_delta']);
        $t->same(32, $buckets[$bucket]['script_delta']);
        $t->same(50848, $buckets[$bucket]['denominator_total']);
    }
};

$tests['bulk upstream suite denominator burnup b records concrete script range'] = static function (TestRunner $t): void {
    $record = libsqlite_bulk_suite_b_record(libsqlite_bulk_suite_b_rows());

    $t->same('bulk-suite-b-001.test', $record['target_scripts'][0]);
    $t->same('bulk-suite-b-064.test', $record['target_scripts'][63]);
    $t->same('bulk-suite-b-128.test', $record['target_scripts'][127]);
    $t->same('bulk-upstream-suite-denominator-burnup-b-001', $record['advanced_gap_ids'][0]);
    $t->same('bulk-upstream-suite-denominator-burnup-b-128', $record['advanced_gap_ids'][127]);
};

$tests['bulk upstream suite denominator burnup b blocks duplicate broad runner'] = static function (TestRunner $t): void {
    $record = libsqlite_bulk_suite_b_record(
        libsqlite_bulk_suite_b_rows(),
        snapshot: '991 1 S 00:05 82.0 ./testfixture ../src/test/testrunner.tcl --jobs 4 --stop-on-error all'
    );

    $t->same('current-next53-denominator-burnup-partial', $record['status']);
    $t->same('blocked-active-runner', $record['active_runner_status']);
    $t->same(1, $record['active_runner_count']);
    $t->same(0, $record['mapped_delta_total']);
    $t->contains('duplicate-broad-runner-active', implode(',', array_column($record['blockers'], 'id')));
};

$tests['bulk upstream suite denominator burnup b blocks unhydrated rows'] = static function (TestRunner $t): void {
    $rows = libsqlite_bulk_suite_b_rows(8);
    $rows[3]['hydrated'] = false;

    $record = libsqlite_bulk_suite_b_record($rows);

    $t->same('current-next53-denominator-burnup-partial', $record['status']);
    $t->same(1, $record['blocked_count']);
    $t->same(['bulk-upstream-suite-denominator-burnup-b-004'], $record['blocked_gap_ids']);
    $t->contains('gap-scripts-not-hydrated', $record['blockers'][0]['evidence']);
    $t->same(0, $record['mapped_delta_total']);
};

$tests['bulk upstream suite denominator burnup b blocks stale already present artifacts'] = static function (TestRunner $t): void {
    $rows = libsqlite_bulk_suite_b_rows(8);
    $rows[4]['next_artifact_present'] = true;

    $record = libsqlite_bulk_suite_b_record($rows);

    $t->same('current-next53-denominator-burnup-partial', $record['status']);
    $t->same(['bulk-upstream-suite-denominator-burnup-b-005'], $record['blocked_gap_ids']);
    $t->contains('next-artifact-already-present', $record['blockers'][0]['evidence']);
};

$tests['bulk upstream suite denominator burnup b blocks mapped regressions'] = static function (TestRunner $t): void {
    $rows = libsqlite_bulk_suite_b_rows(8);
    $rows[2]['current_mapped'] = 2;
    $rows[2]['next_mapped'] = 1;

    $record = libsqlite_bulk_suite_b_record($rows);

    $t->same('blocked', $record['status']);
    $t->same(['bulk-upstream-suite-denominator-burnup-b-003'], $record['regressed_gap_ids']);
    $t->contains('mapped-count-regressed', $record['blockers'][0]['evidence']);
};

$tests['bulk upstream suite denominator burnup b blocks script count regressions'] = static function (TestRunner $t): void {
    $rows = libsqlite_bulk_suite_b_rows(8);
    $rows[1]['current_countable_scripts'] = 2;
    $rows[1]['next_countable_scripts'] = 1;

    $record = libsqlite_bulk_suite_b_record($rows);

    $t->same('blocked', $record['status']);
    $t->same(['bulk-upstream-suite-denominator-burnup-b-002'], $record['regressed_gap_ids']);
    $t->contains('countable-script-count-regressed', $record['blockers'][0]['evidence']);
};

$tests['bulk upstream suite denominator burnup b blocks focused php admission failures'] = static function (TestRunner $t): void {
    $record = libsqlite_bulk_suite_b_record(
        libsqlite_bulk_suite_b_rows(8),
        libsqlite_bulk_suite_b_output(128, 1)
    );

    $t->same('current-next53-denominator-burnup-partial', $record['status']);
    $t->same(0, $record['php_pass_delta']);
    $t->contains('focused-php-pass-admission-blocked', implode(',', array_column($record['blockers'], 'id')));
};

$tests['bulk upstream suite denominator burnup b rejects missing heads'] = static function (TestRunner $t): void {
    $t->throws(
        InvalidArgumentException::class,
        static fn () => libsqlite_bulk_suite_b_evidence()->releaseRunnerDenominatorGapBurnup(
            libsqlite_bulk_suite_b_rows(1),
            '',
            'bulk-upstream-suite-denominator-burnup-20260530T1530Z-b',
            188353,
            'lanes/libsqlite/tests/SQLiteBulkUpstreamSuiteDenominatorBurnupCurrentSourceBTest.php',
            libsqlite_bulk_suite_b_output(),
            'non overlap'
        )
    );
};

$tests['bulk upstream suite denominator burnup b rejects empty row set'] = static function (TestRunner $t): void {
    $t->throws(
        InvalidArgumentException::class,
        static fn () => libsqlite_bulk_suite_b_record([])
    );
};

$tests['bulk upstream suite denominator burnup b keeps dependency closure note generic'] = static function (TestRunner $t): void {
    $record = libsqlite_bulk_suite_b_record(libsqlite_bulk_suite_b_rows());

    $t->contains('no new support component needed', $record['dependency_closure']);
    $t->contains('active-runner gates', $record['dependency_closure']);
    $t->contains('bulk upstream suite denominator burnup b avoids', $record['non_overlap_note']);
    $t->contains('guarded runner', $record['next_gate']);
};

return $tests;
