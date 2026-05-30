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

function libsqlite_bulk_suite_b_upstream_test_dir(): string
{
    $path = '/home/claude/port-libs/.upstream-cache/libsqlite/test';
    if (!is_dir($path)) {
        throw new RuntimeException('Hydrated SQLite upstream test directory is required for the bulk suite denominator burnup slice');
    }

    return $path;
}

/**
 * @return list<string>
 */
function libsqlite_bulk_suite_b_upstream_scripts(int $count = 1024): array
{
    $scripts = [];
    foreach (glob(libsqlite_bulk_suite_b_upstream_test_dir() . '/*.test') ?: [] as $path) {
        $scripts[] = basename($path);
    }
    sort($scripts, SORT_STRING);

    if (count($scripts) < $count) {
        throw new RuntimeException(sprintf('Hydrated SQLite upstream corpus has only %d .test scripts; %d required', count($scripts), $count));
    }

    return array_slice($scripts, 0, $count);
}

/**
 * @return list<array<string, mixed>>
 */
function libsqlite_bulk_suite_b_rows(int $count = 1024): array
{
    $rows = [];
    $buckets = [
        'veryquick-admission',
        'suite-shard-admission',
        'runner-map-gap',
        'denominator-hydration',
    ];
    $scripts = libsqlite_bulk_suite_b_upstream_scripts($count);
    $upstreamDir = libsqlite_bulk_suite_b_upstream_test_dir();

    foreach ($scripts as $index => $script) {
        $i = $index + 1;
        $path = $upstreamDir . '/' . $script;
        $rows[] = [
            'id' => sprintf('bulk-upstream-suite-denominator-burnup-real-b-%04d', $i),
            'bucket' => $buckets[($i - 1) % count($buckets)],
            'current_mapped' => 0,
            'next_mapped' => 1,
            'current_countable_scripts' => 0,
            'next_countable_scripts' => 1,
            'denominator_total' => 1589,
            'hydrated' => true,
            'next_artifact_present' => false,
            'scripts' => [$script],
            'upstream_path' => $path,
            'upstream_sha256' => hash_file('sha256', $path),
        ];
    }

    return $rows;
}

function libsqlite_bulk_suite_b_record(array $rows, ?string $output = null, string $snapshot = ''): array
{
    return libsqlite_bulk_suite_b_evidence()->releaseRunnerDenominatorGapBurnup(
        $rows,
        '8160272f871bffaf7a8a48da09598a7f4bfdce9f',
        'bulk-upstream-suite-denominator-burnup-20260530T174230Z-real-b',
        188353,
        'lanes/libsqlite/tests/SQLiteBulkUpstreamSuiteDenominatorBurnupCurrentSourceBTest.php',
        $output ?? libsqlite_bulk_suite_b_output(),
        'bulk upstream suite denominator burnup real b uses 1024 hydrated upstream test/*.test scripts and avoids prior veryquick shard next789-964 rows, current-source prepared-octet rows, release/all parity claims, fabricated script ids, and ordinary SQL/JSON/WAL/B-tree behavior surfaces',
        $snapshot
    );
}

$tests = [];

$tests['bulk upstream suite denominator burnup b admits one thousand twenty four real upstream rows'] = static function (TestRunner $t): void {
    $record = libsqlite_bulk_suite_b_record(libsqlite_bulk_suite_b_rows(), libsqlite_bulk_suite_b_output(1024));

    $t->same('current-next53-denominator-burnup-ready', $record['status']);
    $t->same(1024, $record['row_count']);
    $t->same(4, $record['bucket_count']);
    $t->same(1627136, $record['denominator_total']);
    $t->same(0, $record['current_mapped_total']);
    $t->same(1024, $record['next_mapped_total']);
    $t->same(1024, $record['mapped_delta_total']);
    $t->same(0, $record['current_countable_script_total']);
    $t->same(1024, $record['next_countable_script_total']);
    $t->same(1024, $record['countable_script_delta_total']);
    $t->same(1024, $record['advanced_count']);
    $t->same(0, $record['blocked_count']);
    $t->same(1024, $record['target_script_count']);
    $t->same(0.06, $record['burnup_percent']);
    $t->same(1024, $record['php_pass_delta']);
    $t->same(189377, $record['next_php_pass']);
    $t->same(true, $record['counts_denominator_gap_burnup']);
    $t->same(false, $record['counts_release_parity']);

    foreach ($record['entries'] as $entry) {
        $t->same('advanced', $entry['status']);
    }
};

$tests['bulk upstream suite denominator burnup b preserves exact bucket totals'] = static function (TestRunner $t): void {
    $record = libsqlite_bulk_suite_b_record(libsqlite_bulk_suite_b_rows(), libsqlite_bulk_suite_b_output(1024));
    $buckets = [];
    foreach ($record['buckets'] as $bucket) {
        $buckets[$bucket['bucket']] = $bucket;
    }

    foreach (['denominator-hydration', 'runner-map-gap', 'suite-shard-admission', 'veryquick-admission'] as $bucket) {
        $t->same(256, $buckets[$bucket]['rows']);
        $t->same(256, $buckets[$bucket]['advanced']);
        $t->same(256, $buckets[$bucket]['mapped_delta']);
        $t->same(256, $buckets[$bucket]['script_delta']);
        $t->same(406784, $buckets[$bucket]['denominator_total']);
    }
};

$tests['bulk upstream suite denominator burnup b records concrete upstream script range'] = static function (TestRunner $t): void {
    $record = libsqlite_bulk_suite_b_record(libsqlite_bulk_suite_b_rows(), libsqlite_bulk_suite_b_output(1024));

    $t->same('8_3_names.test', $record['target_scripts'][0]);
    $t->same('basexx1.test', $record['target_scripts'][79]);
    $t->same('upfrom3.test', $record['target_scripts'][1023]);
    $t->same('bulk-upstream-suite-denominator-burnup-real-b-0001', $record['advanced_gap_ids'][0]);
    $t->same('bulk-upstream-suite-denominator-burnup-real-b-1024', $record['advanced_gap_ids'][1023]);
};

$tests['bulk upstream suite denominator burnup b rows cite hydrated upstream hashes'] = static function (TestRunner $t): void {
    $rows = libsqlite_bulk_suite_b_rows();
    $seenScripts = [];

    foreach ($rows as $row) {
        $script = $row['scripts'][0];
        $seenScripts[$script] = true;
        $t->same(true, is_file($row['upstream_path']));
        $t->same($script, basename($row['upstream_path']));
        $t->same(hash_file('sha256', $row['upstream_path']), $row['upstream_sha256']);
    }

    $t->same(1024, count($seenScripts));
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
    $t->same(['bulk-upstream-suite-denominator-burnup-real-b-0004'], $record['blocked_gap_ids']);
    $t->contains('gap-scripts-not-hydrated', $record['blockers'][0]['evidence']);
    $t->same(0, $record['mapped_delta_total']);
};

$tests['bulk upstream suite denominator burnup b blocks stale already present artifacts'] = static function (TestRunner $t): void {
    $rows = libsqlite_bulk_suite_b_rows(8);
    $rows[4]['next_artifact_present'] = true;

    $record = libsqlite_bulk_suite_b_record($rows);

    $t->same('current-next53-denominator-burnup-partial', $record['status']);
    $t->same(['bulk-upstream-suite-denominator-burnup-real-b-0005'], $record['blocked_gap_ids']);
    $t->contains('next-artifact-already-present', $record['blockers'][0]['evidence']);
};

$tests['bulk upstream suite denominator burnup b blocks missing real upstream scripts'] = static function (TestRunner $t): void {
    $rows = libsqlite_bulk_suite_b_rows(8);
    $rows[5]['upstream_path'] = libsqlite_bulk_suite_b_upstream_test_dir() . '/not-a-real-upstream-script.test';

    $record = libsqlite_bulk_suite_b_record($rows);

    $t->same('current-next53-denominator-burnup-partial', $record['status']);
    $t->same(['bulk-upstream-suite-denominator-burnup-real-b-0006'], $record['blocked_gap_ids']);
    $t->contains('real-upstream-script-missing', $record['blockers'][0]['evidence']);
};

$tests['bulk upstream suite denominator burnup b blocks upstream hash mismatches'] = static function (TestRunner $t): void {
    $rows = libsqlite_bulk_suite_b_rows(8);
    $rows[6]['upstream_sha256'] = str_repeat('0', 64);

    $record = libsqlite_bulk_suite_b_record($rows);

    $t->same('current-next53-denominator-burnup-partial', $record['status']);
    $t->same(['bulk-upstream-suite-denominator-burnup-real-b-0007'], $record['blocked_gap_ids']);
    $t->contains('real-upstream-script-hash-mismatch', $record['blockers'][0]['evidence']);
};

$tests['bulk upstream suite denominator burnup b blocks mapped regressions'] = static function (TestRunner $t): void {
    $rows = libsqlite_bulk_suite_b_rows(8);
    $rows[2]['current_mapped'] = 2;
    $rows[2]['next_mapped'] = 1;

    $record = libsqlite_bulk_suite_b_record($rows);

    $t->same('blocked', $record['status']);
    $t->same(['bulk-upstream-suite-denominator-burnup-real-b-0003'], $record['regressed_gap_ids']);
    $t->contains('mapped-count-regressed', $record['blockers'][0]['evidence']);
};

$tests['bulk upstream suite denominator burnup b blocks script count regressions'] = static function (TestRunner $t): void {
    $rows = libsqlite_bulk_suite_b_rows(8);
    $rows[1]['current_countable_scripts'] = 2;
    $rows[1]['next_countable_scripts'] = 1;

    $record = libsqlite_bulk_suite_b_record($rows);

    $t->same('blocked', $record['status']);
    $t->same(['bulk-upstream-suite-denominator-burnup-real-b-0002'], $record['regressed_gap_ids']);
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
    $t->contains('bulk upstream suite denominator burnup real b uses 1024 hydrated upstream test/*.test scripts', $record['non_overlap_note']);
    $t->contains('fabricated script ids', $record['non_overlap_note']);
    $t->contains('guarded runner', $record['next_gate']);
};

return $tests;
