<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteUpstreamSuiteEvidence;

function libsqlite_suite_next389404_evidence(): SQLiteUpstreamSuiteEvidence
{
    return SQLiteUpstreamSuiteEvidence::fromManifestPath(__DIR__ . '/../UPSTREAM_TEST_MANIFEST.json');
}

function libsqlite_suite_next389404_output(int $assertions = 64, int $failures = 0): string
{
    return implode("\n", [
        'Focused test run: 1 selected test files (root lock skipped)',
        'PASS current source next389-404 suite evidence prepared',
        sprintf('1 test files, %d assertions, %d failures', $assertions, $failures),
    ]);
}

/**
 * @return list<array<string, mixed>>
 */
function libsqlite_suite_next389404_rows(
    string $launcherBase = '3baba579d7bc2e88269493208b2be99b75b78428',
    string $integrationSource = '8a447f445e5d2fd32fc9fd463117f585d1416551'
): array {
    $rows = [];
    foreach (range(389, 404) as $slice) {
        $rows[] = [
            'unit' => 'suite-upstream-veryquick-shard-current-source-next' . $slice,
            'kind' => 'bounded-upstream-veryquick-shard-runner',
            'gap_id' => 'current-source-next' . $slice . '-veryquick-shard-gap',
            'gap_status' => 'removed',
            'removed_blocker' => 'next' . $slice . ' prepared as focused veryquick suite evidence after merged next373-388',
            'tier' => 'focused-veryquick-shard',
            'source_head' => 'suite-upstream-veryquick-shard-current-source-next' . $slice,
            'launcher_base_head' => $launcherBase,
            'dashboard_source_head' => $integrationSource,
            'status_source_head' => $integrationSource,
            'implementation_source_head' => $integrationSource,
            'artifact_path' => 'lanes/libsqlite/notes/yield-suite-upstream-veryquick-shard-current-source-next' . $slice . '.md',
            'runner_command' => './testfixture ../libsqlite/test/testrunner.tcl --jobs 1 --stop-on-error veryquick veryquick-current-source-next' . $slice . '-01.test',
            'scripts' => ['testrunner.test', 'veryquick-current-source-next' . $slice . '-01.test'],
            'current_countable' => false,
            'next_countable' => false,
            'exit' => 0,
            'errors' => 0,
            'current_tests' => 0,
            'next_tests' => 0,
        ];
    }

    return $rows;
}

/**
 * @param list<array<string, mixed>> $rows
 * @return array<string, mixed>
 */
function libsqlite_suite_next389404_record(
    array $rows,
    ?string $output = null,
    ?int $expected = 64,
    string $snapshot = ''
): array {
    return libsqlite_suite_next389404_evidence()->upstreamVeryquickShardCurrentSourceNext389404(
        $rows,
        783,
        149935,
        '3baba579d7bc2e88269493208b2be99b75b78428',
        '8a447f445e5d2fd32fc9fd463117f585d1416551',
        'lanes/libsqlite/tests/SQLiteUpstreamVeryquickShardCurrentSourceNext389404Test.php',
        $output ?? libsqlite_suite_next389404_output(),
        'current-source next389-404 suite evidence follows merged next373-388 without counting individual shard rows, release/all parity, exact-shard next148, queued runner106/jsonvt104 rebase work, or live B-tree/JSON/VFS/WAL/planner/PRAGMA/ATTACH/window/VDBE work',
        $expected,
        $snapshot
    );
}

$tests = [];

$tests['current source next389-404 prepares suite evidence after next373-388'] = static function (TestRunner $t): void {
    $record = libsqlite_suite_next389404_record(libsqlite_suite_next389404_rows());

    $t->same('current-source-next389-404-suite-evidence-prepared', $record['status']);
    $t->same(16, $record['row_count']);
    $t->same(16, $record['zero_error_row_count']);
    $t->same(16, $record['lane_local_note_row_count']);
    $t->same(16, $record['slice_count']);
    $t->same([], $record['missing_slices']);
    $t->same('next389', $record['covered_slices'][0]);
    $t->same('next404', $record['covered_slices'][15]);
    $t->same(783, $record['current_mapped']);
    $t->same(783, $record['next_mapped']);
    $t->same(0, $record['mapped_delta']);
    $t->same(64, $record['php_pass_delta']);
    $t->same(149999, $record['next_php_pass']);
    $t->same(false, $record['counts_upstream_veryquick_shard_current_source_next389_404']);
    $t->same(false, $record['counts_upstream_veryquick_shard_current_source_next373_388']);
    $t->same(false, $record['counts_release_parity']);
    $t->contains('prepared upstream-suite evidence only', $record['next_gate']);
    $t->contains('current-source next389-404 evidence prep', $record['dependency_closure']);
};

$tests['current source next389-404 records concrete target scripts'] = static function (TestRunner $t): void {
    $record = libsqlite_suite_next389404_record(libsqlite_suite_next389404_rows());

    $t->same(17, $record['target_script_count']);
    $t->contains('testrunner.test', implode(',', $record['target_scripts']));
    $t->contains('veryquick-current-source-next389-01.test', implode(',', $record['target_scripts']));
    $t->contains('veryquick-current-source-next404-01.test', implode(',', $record['target_scripts']));
};

$tests['current source next389-404 blocks missing slice'] = static function (TestRunner $t): void {
    $rows = libsqlite_suite_next389404_rows();
    array_pop($rows);

    $record = libsqlite_suite_next389404_record($rows);

    $t->same('blocked', $record['status']);
    $t->same(['next404'], $record['missing_slices']);
    $t->contains('missing-next-slice', implode('; ', array_column($record['blockers'], 'id')));
};

$tests['current source next389-404 blocks stale provenance and unsafe runner'] = static function (TestRunner $t): void {
    $rows = libsqlite_suite_next389404_rows(
        launcherBase: '0000000000000000000000000000000000000000',
        integrationSource: '1111111111111111111111111111111111111111'
    );
    $rows[0]['artifact_path'] = '/tmp/next389.md';
    $rows[0]['runner_command'] = './testfixture ../libsqlite/test/testrunner.tcl all';
    $rows[0]['scripts'] = ['README.md'];
    $rows[0]['errors'] = 1;

    $record = libsqlite_suite_next389404_record($rows);
    $evidence = implode('; ', array_column($record['blockers'], 'id'));

    $t->same('blocked', $record['status']);
    $t->contains('launcher-base-head-mismatch', $evidence);
    $t->contains('dashboard_source_head-mismatch', $evidence);
    $t->contains('artifact-path-not-lane-local-note', $evidence);
    $t->contains('guarded-veryquick-command-missing', $evidence);
    $t->contains('concrete-test-scripts-missing', $evidence);
    $t->contains('runner-artifact-not-zero-error', $evidence);
};

$tests['current source next389-404 blocks focused php mismatch and duplicate broad runner'] = static function (TestRunner $t): void {
    $record = libsqlite_suite_next389404_record(
        libsqlite_suite_next389404_rows(),
        output: libsqlite_suite_next389404_output(assertions: 63),
        expected: 64,
        snapshot: "12345 ./testfixture ../libsqlite/test/testrunner.tcl release\n"
    );
    $evidence = implode('; ', array_column($record['blockers'], 'id'));

    $t->same('blocked', $record['status']);
    $t->same('blocked-active-runner', $record['active_runner_status']);
    $t->same(1, $record['active_runner_count']);
    $t->contains('duplicate-broad-runner-active', $evidence);
    $t->contains('focused-php-pass-delta-mismatch', $evidence);
};

return $tests;
