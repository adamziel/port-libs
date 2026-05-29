<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteUpstreamSuiteEvidence;

function libsqlite_suite_next197212_evidence(): SQLiteUpstreamSuiteEvidence
{
    return SQLiteUpstreamSuiteEvidence::fromManifestPath(__DIR__ . '/../UPSTREAM_TEST_MANIFEST.json');
}

function libsqlite_suite_next197212_output(int $passLines = 88, int $assertions = 88, int $failures = 0): string
{
    $lines = ['Focused test run: 1 selected test files (root lock skipped)'];
    for ($i = 1; $i <= $passLines; $i++) {
        $lines[] = sprintf('PASS current source next197-212 suite evidence prep case %02d', $i);
    }
    $lines[] = sprintf('1 test files, %d assertions, %d failures', $assertions, $failures);

    return implode("\n", $lines);
}

/**
 * @return list<array<string, mixed>>
 */
function libsqlite_suite_next197212_rows(
    string $launcherBase = '6fb6f5dd462c33aefb4fbbb5ca8f85a291870d2b',
    string $integrationSource = '8a447f445e5d2fd32fc9fd463117f585d1416551'
): array {
    $rows = [];
    foreach (range(197, 212) as $slice) {
        $script = sprintf('veryquick-current-source-next%d-evidence.test', $slice);
        $rows[] = [
            'unit' => 'suite-upstream-veryquick-shard-current-source-next' . $slice,
            'kind' => 'bounded-upstream-veryquick-shard-evidence-prep',
            'tier' => 'focused-veryquick-shard',
            'launcher_base_head' => $launcherBase,
            'dashboard_source_head' => $integrationSource,
            'status_source_head' => $integrationSource,
            'implementation_source_head' => $integrationSource,
            'artifact_path' => 'lanes/libsqlite/notes/suite-upstream-veryquick-shard-current-source-next197-212.md',
            'runner_command' => './testfixture ../libsqlite/test/testrunner.tcl --jobs 1 --stop-on-error veryquick ' . $script,
            'scripts' => [$script, 'testrunner.test'],
            'exit' => 0,
            'errors' => 0,
        ];
    }

    return $rows;
}

/**
 * @param list<array<string, mixed>> $rows
 * @return array<string, mixed>
 */
function libsqlite_suite_next197212_record(
    array $rows,
    ?string $output = null,
    ?int $expected = 88,
    string $snapshot = ''
): array {
    return libsqlite_suite_next197212_evidence()->upstreamVeryquickShardCurrentSourceNext197212(
        $rows,
        614,
        83424,
        '6fb6f5dd462c33aefb4fbbb5ca8f85a291870d2b',
        '8a447f445e5d2fd32fc9fd463117f585d1416551',
        'lanes/libsqlite/tests/SQLiteUpstreamVeryquickShardCurrentSourceNext197212Test.php',
        $output ?? libsqlite_suite_next197212_output(),
        'current-source next197-212 suite evidence prep is a direct follow-on to merged next181-196, avoids individual next197 through next212 shard countability, accepted next181-196 suite evidence, exact-shard next148, release/all parity, mapped-count inflation, and live B-tree/JSON/VFS/WAL/planner/PRAGMA/ATTACH/window/VDBE work',
        $expected,
        $snapshot
    );
}

$tests = [];

$tests['current source next197-212 prepares suite evidence without mapped inflation'] = static function (TestRunner $t): void {
    $record = libsqlite_suite_next197212_record(libsqlite_suite_next197212_rows());

    $t->same('current-source-next197-212-suite-evidence-prepared', $record['status']);
    $t->same(614, $record['current_mapped']);
    $t->same(614, $record['next_mapped']);
    $t->same(0, $record['mapped_delta']);
    $t->same(88, $record['php_pass_delta']);
    $t->same(83512, $record['next_php_pass']);
    $t->same(16, $record['row_count']);
    $t->same(16, $record['zero_error_row_count']);
    $t->same(16, $record['lane_local_note_row_count']);
    $t->same(16, $record['slice_count']);
    $t->same('next197', $record['covered_slices'][0]);
    $t->same('next212', $record['covered_slices'][15]);
    $t->same([], $record['missing_slices']);
    $t->same(false, $record['counts_upstream_veryquick_shard_current_source_next197_212']);
    $t->same(false, $record['counts_upstream_veryquick_shard_current_source_next181_196']);
    $t->same(false, $record['counts_release_parity']);
    $t->contains('do not increase mapped upstream count', $record['next_gate']);
};

$tests['current source next197-212 records concrete target scripts'] = static function (TestRunner $t): void {
    $record = libsqlite_suite_next197212_record(libsqlite_suite_next197212_rows());

    $t->same(17, $record['target_script_count']);
    $t->contains('veryquick-current-source-next197-evidence.test', implode(',', $record['target_scripts']));
    $t->contains('veryquick-current-source-next212-evidence.test', implode(',', $record['target_scripts']));
    $t->contains('testrunner.test', implode(',', $record['target_scripts']));
};

$tests['current source next197-212 blocks missing slice'] = static function (TestRunner $t): void {
    $rows = libsqlite_suite_next197212_rows();
    array_splice($rows, 5, 1);
    $record = libsqlite_suite_next197212_record($rows);

    $t->same('blocked', $record['status']);
    $t->same(['next202'], $record['missing_slices']);
    $t->contains('missing-next-slice', implode('; ', array_column($record['blockers'], 'id')));
};

$tests['current source next197-212 blocks stale provenance and non local notes'] = static function (TestRunner $t): void {
    $rows = libsqlite_suite_next197212_rows(launcherBase: 'bad', integrationSource: 'stale');
    $rows[0]['artifact_path'] = '/tmp/next197.md';
    $record = libsqlite_suite_next197212_record($rows);

    $t->same('blocked', $record['status']);
    $ids = implode('; ', array_column($record['blockers'], 'id'));
    $t->contains('launcher-base-head-mismatch', $ids);
    $t->contains('dashboard_source_head-mismatch', $ids);
    $t->contains('artifact-path-not-lane-local-note', $ids);
};

$tests['current source next197-212 blocks unguarded or non zero runner rows'] = static function (TestRunner $t): void {
    $rows = libsqlite_suite_next197212_rows();
    $rows[0]['runner_command'] = './testfixture ../libsqlite/test/testrunner.tcl all';
    $rows[1]['errors'] = 1;
    $record = libsqlite_suite_next197212_record($rows);

    $t->same('blocked', $record['status']);
    $ids = implode('; ', array_column($record['blockers'], 'id'));
    $t->contains('guarded-veryquick-command-missing', $ids);
    $t->contains('runner-artifact-not-zero-error', $ids);
};

$tests['current source next197-212 blocks duplicate broad runner snapshot'] = static function (TestRunner $t): void {
    $record = libsqlite_suite_next197212_record(
        libsqlite_suite_next197212_rows(),
        snapshot: "12345 ./testfixture ../libsqlite/test/testrunner.tcl release\n"
    );

    $t->same('blocked', $record['status']);
    $t->same('blocked-active-runner', $record['active_runner_status']);
    $t->same(1, $record['active_runner_count']);
};

$tests['current source next197-212 blocks focused php mismatch'] = static function (TestRunner $t): void {
    $record = libsqlite_suite_next197212_record(
        libsqlite_suite_next197212_rows(),
        output: libsqlite_suite_next197212_output(passLines: 87, assertions: 87)
    );

    $t->same('blocked', $record['status']);
    $t->contains('focused-php-pass-delta-mismatch', implode('; ', array_column($record['blockers'], 'id')));
};

$tests['current source next197-212 rejects empty row list'] = static function (TestRunner $t): void {
    try {
        libsqlite_suite_next197212_record([]);
        $t->fail('Expected empty next197-212 row list to be rejected');
    } catch (InvalidArgumentException $exception) {
        $t->contains('next197-212 suite evidence requires at least one row', $exception->getMessage());
    }
};

return $tests;
