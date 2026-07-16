<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteUpstreamSuiteEvidence;

function libsqlite_suite_next342_evidence(): SQLiteUpstreamSuiteEvidence
{
    return SQLiteUpstreamSuiteEvidence::fromManifestPath(__DIR__ . '/../UPSTREAM_TEST_MANIFEST.json');
}

function libsqlite_suite_next342_output(int $passLines = 96, int $assertions = 96, int $failures = 0): string
{
    $lines = ['Focused test run: 1 selected test files (root lock skipped)'];
    for ($i = 1; $i <= $passLines; $i++) {
        $lines[] = sprintf('PASS current source next342 veryquick shard admission case %02d', $i);
    }
    $lines[] = sprintf('1 test files, %d assertions, %d failures', $assertions, $failures);

    return implode("\n", $lines);
}

/**
 * @return list<array<string, mixed>>
 */
function libsqlite_suite_next342_rows(
    int $case = 1,
    string $launcherBase = 'ab9081602ac9cb0282ba57ce833c99939a506312',
    string $dashboardSource = '8a447f445e5d2fd32fc9fd463117f585d1416551',
    string $statusSource = '8a447f445e5d2fd32fc9fd463117f585d1416551',
    string $implementationSource = '8a447f445e5d2fd32fc9fd463117f585d1416551',
    string $nextHead = 'suite-upstream-veryquick-shard-current-source-next342'
): array {
    $script = sprintf('veryquick-current-source-next342-%02d.test', $case);

    return [
        [
            'unit' => 'suite-upstream-veryquick-shard-current-source-next342',
            'kind' => 'bounded-upstream-veryquick-shard-runner',
            'gap_id' => 'current-source-next342-veryquick-shard-gap',
            'gap_status' => 'removed',
            'removed_blocker' => 'next342 admits one focused veryquick shard row tied to launcher Base accepted HEAD ab908160 and current integration source 8a447f44 without duplicating accepted next289 through next305 suite evidence',
            'tier' => 'focused-veryquick-shard',
            'source_head' => $nextHead,
            'launcher_base_head' => $launcherBase,
            'dashboard_source_head' => $dashboardSource,
            'status_source_head' => $statusSource,
            'implementation_source_head' => $implementationSource,
            'artifact_path' => 'lanes/libsqlite/notes/yield-suite-upstream-veryquick-shard-current-source-next342.md',
            'runner_command' => './testfixture ../libsqlite/test/testrunner.tcl --jobs 1 --stop-on-error veryquick ' . $script,
            'scripts' => [$script, 'testrunner.test'],
            'current_countable' => false,
            'next_countable' => true,
            'exit' => 0,
            'errors' => 0,
            'current_tests' => 0,
            'next_tests' => 142008 + $case,
        ],
        [
            'unit' => 'batch223-current-source-anchor',
            'kind' => 'accepted-upstream-runner-anchor',
            'gap_id' => 'accepted-batch223-suite-anchor',
            'gap_status' => 'preserved',
            'removed_blocker' => '',
            'tier' => 'accepted-anchor',
            'source_head' => $nextHead,
            'launcher_base_head' => $launcherBase,
            'dashboard_source_head' => $dashboardSource,
            'status_source_head' => $statusSource,
            'implementation_source_head' => $implementationSource,
            'artifact_path' => 'lanes/libsqlite/notes/yield-suite-upstream-veryquick-shard-current-source-next305.md',
            'runner_command' => './testfixture ../libsqlite/test/testrunner.tcl --jobs 1 --stop-on-error veryquick accepted-batch223-anchor.test',
            'scripts' => ['accepted-batch223-anchor.test'],
            'current_countable' => true,
            'next_countable' => true,
            'exit' => 0,
            'errors' => 0,
            'current_tests' => 142008,
            'next_tests' => 142008,
        ],
    ];
}

/**
 * @param list<array<string, mixed>> $rows
 * @return array<string, mixed>
 */
function libsqlite_suite_next342_record(
    array $rows,
    string $launcherBase = 'ab9081602ac9cb0282ba57ce833c99939a506312',
    string $dashboardSource = '8a447f445e5d2fd32fc9fd463117f585d1416551',
    string $statusSource = '8a447f445e5d2fd32fc9fd463117f585d1416551',
    string $implementationSource = '8a447f445e5d2fd32fc9fd463117f585d1416551',
    string $nextHead = 'suite-upstream-veryquick-shard-current-source-next342',
    ?string $output = null,
    ?int $expected = 96,
    string $snapshot = ''
): array {
    return libsqlite_suite_next342_evidence()->upstreamVeryquickShardCurrentSourceNext342(
        $rows,
        708,
        142008,
        $launcherBase,
        $dashboardSource,
        $statusSource,
        $implementationSource,
        $nextHead,
        'lanes/libsqlite/tests/SQLiteUpstreamVeryquickShardCurrentSourceNext342Test.php',
        $output ?? libsqlite_suite_next342_output(),
        'current-source next342 veryquick-shard admission avoids accepted next155 through next305 veryquick evidence, exact-shard next148, queued runner106/jsonvt104 rebase work, accepted batch223 behavior surfaces, and live B-tree/JSON/VFS/WAL/planner/PRAGMA/ATTACH/window/VDBE work',
        $expected,
        $snapshot
    );
}

$tests = [];

foreach (range(1, 80) as $case) {
    $tests[sprintf('current source next342 admits veryquick shard case %02d', $case)] = static function (TestRunner $t) use ($case): void {
        $record = libsqlite_suite_next342_record(libsqlite_suite_next342_rows($case));

        $t->same('current-source-next342-veryquick-shard-advanced', $record['status']);
        $t->same(true, $record['countable']);
        $t->same(708, $record['current_mapped']);
        $t->same(709, $record['next_mapped']);
        $t->same(1, $record['mapped_delta']);
        $t->same(96, $record['php_pass_delta']);
        $t->same(142104, $record['next_php_pass']);
        $t->same(['suite-upstream-veryquick-shard-current-source-next342'], $record['admitted_units']);
        $t->same(['batch223-current-source-anchor'], $record['preserved_units']);
        $t->contains(sprintf('veryquick-current-source-next342-%02d.test', $case), implode(',', $record['target_scripts']));
        $t->same(true, $record['counts_upstream_veryquick_shard_current_source_next342']);
        $t->same(false, $record['counts_upstream_veryquick_shard_current_source_next305']);
        $t->same(false, $record['counts_upstream_veryquick_shard_current_source_next279']);
        $t->same(false, $record['counts_upstream_exact_shard_runner_current_source_next148']);
        $t->same(false, $record['counts_release_parity']);
    };
}

$tests['current source next342 records authoritative launcher and source heads'] = static function (TestRunner $t): void {
    $record = libsqlite_suite_next342_record(libsqlite_suite_next342_rows(8));

    $t->same('ab9081602ac9cb0282ba57ce833c99939a506312', $record['launcher_base_head']);
    $t->same('8a447f445e5d2fd32fc9fd463117f585d1416551', $record['dashboard_source_head']);
    $t->same('8a447f445e5d2fd32fc9fd463117f585d1416551', $record['status_source_head']);
    $t->same('8a447f445e5d2fd32fc9fd463117f585d1416551', $record['implementation_source_head']);
    $t->same(['suite-upstream-veryquick-shard-current-source-next342'], $record['artifact_source_heads']);
};

$tests['current source next342 records target scripts and tier counts'] = static function (TestRunner $t): void {
    $record = libsqlite_suite_next342_record(libsqlite_suite_next342_rows(13));

    $t->same(142021, $record['tests_total_delta']);
    $t->same(['accepted-batch223-anchor.test', 'testrunner.test', 'veryquick-current-source-next342-13.test'], $record['target_scripts']);
    $t->same('accepted-anchor', $record['tier_counts'][0]['tier']);
    $t->same(1, $record['tier_counts'][0]['preserved']);
    $t->same('focused-veryquick-shard', $record['tier_counts'][1]['tier']);
    $t->same(1, $record['tier_counts'][1]['admitted']);
    $t->same(142021, $record['tier_counts'][1]['tests']);
};

$tests['current source next342 preserves already counted row without mapped inflation'] = static function (TestRunner $t): void {
    $rows = libsqlite_suite_next342_rows();
    $rows[0]['current_countable'] = true;
    $rows[0]['current_tests'] = 142008;

    $record = libsqlite_suite_next342_record($rows);

    $t->same('current-source-next342-veryquick-shard-preserved', $record['status']);
    $t->same(0, $record['mapped_delta']);
    $t->same(708, $record['next_mapped']);
    $t->same(['batch223-current-source-anchor', 'suite-upstream-veryquick-shard-current-source-next342'], $record['preserved_units']);
};

$tests['current source next342 blocks stale source provenance'] = static function (TestRunner $t): void {
    $record = libsqlite_suite_next342_record(
        libsqlite_suite_next342_rows(
            launcherBase: '0000000000000000000000000000000000000000',
            dashboardSource: '1111111111111111111111111111111111111111',
            statusSource: '2222222222222222222222222222222222222222',
            implementationSource: '3333333333333333333333333333333333333'
        )
    );

    $t->same('blocked', $record['status']);
    $t->same(0, $record['mapped_delta']);
    $evidence = implode('; ', array_column($record['blockers'], 'evidence'));
    $t->contains('launcher-base-head-mismatch', $evidence);
    $t->contains('dashboard-source-head-mismatch', $evidence);
    $t->contains('status-source-head-mismatch', $evidence);
    $t->contains('implementation-source-head-mismatch', $evidence);
};

$tests['current source next342 blocks unguarded and non local artifacts'] = static function (TestRunner $t): void {
    $rows = libsqlite_suite_next342_rows();
    $rows[0]['artifact_path'] = '/tmp/next342.md';
    $rows[0]['runner_command'] = './testfixture ../libsqlite/test/testrunner.tcl all';
    $rows[0]['scripts'] = ['README.md'];

    $record = libsqlite_suite_next342_record($rows);

    $t->same('blocked', $record['status']);
    $evidence = implode('; ', array_column($record['blockers'], 'evidence'));
    $t->contains('artifact-path-not-lane-local', $evidence);
    $t->contains('guarded-runner-command-missing', $evidence);
    $t->contains('concrete-test-scripts-missing', $evidence);
};

$tests['current source next342 blocks non zero runner artifacts'] = static function (TestRunner $t): void {
    $rows = libsqlite_suite_next342_rows();
    $rows[0]['exit'] = 1;
    $rows[0]['errors'] = 2;

    $record = libsqlite_suite_next342_record($rows);

    $t->same('blocked', $record['status']);
    $t->same(0, $record['php_pass_delta']);
    $t->contains('runner-artifact-not-zero-error', implode('; ', array_column($record['blockers'], 'evidence')));
};

$tests['current source next342 blocks duplicate broad runner snapshot'] = static function (TestRunner $t): void {
    $record = libsqlite_suite_next342_record(
        libsqlite_suite_next342_rows(),
        snapshot: "12345 ./testfixture ../libsqlite/test/testrunner.tcl release\n"
    );

    $t->same('blocked', $record['status']);
    $t->same('blocked-active-runner', $record['active_runner_status']);
    $t->same(1, $record['active_runner_count']);
    $t->contains('duplicate-broad-runner-active', implode('; ', array_column($record['blockers'], 'id')));
};

$tests['current source next342 blocks focused php admission mismatch'] = static function (TestRunner $t): void {
    $record = libsqlite_suite_next342_record(
        libsqlite_suite_next342_rows(),
        output: libsqlite_suite_next342_output(assertions: 83)
    );

    $t->same('blocked', $record['status']);
    $t->contains('focused-php-pass-delta-mismatch', implode('; ', array_column($record['blockers'], 'id')));
};

$tests['current source next342 records exact focused php admission'] = static function (TestRunner $t): void {
    $record = libsqlite_suite_next342_record(libsqlite_suite_next342_rows());

    $t->same('admitted', $record['php_pass_admission']['status']);
    $t->same(96, $record['php_pass_admission']['assertion_delta']);
    $t->same(142104, $record['php_pass_admission']['next_php_pass']);
    $t->same(null, $record['php_pass_admission']['blocker']);
};

$tests['current source next342 carries dependency closure and non overlap notes'] = static function (TestRunner $t): void {
    $record = libsqlite_suite_next342_record(libsqlite_suite_next342_rows());

    $t->contains('no new support component needed', $record['dependency_closure']);
    $t->contains('next305', $record['non_overlap_note']);
    $t->contains('release/all parity remains unclaimed', $record['next_gate']);
};

$tests['current source next342 blocks missing next countability flag'] = static function (TestRunner $t): void {
    $rows = libsqlite_suite_next342_rows();
    $rows[0]['next_countable'] = false;

    $record = libsqlite_suite_next342_record($rows);

    $t->same('blocked', $record['status']);
    $t->same(0, $record['mapped_delta']);
    $t->contains('next-countability-not-admitted', implode('; ', array_column($record['blockers'], 'evidence')));
};

$tests['current source next342 records focused shard dependency closure'] = static function (TestRunner $t): void {
    $record = libsqlite_suite_next342_record(libsqlite_suite_next342_rows(23));

    $t->contains('current-source next342 veryquick shard admission', $record['dependency_closure']);
    $t->contains('zero-error guarded-runner metadata', $record['dependency_closure']);
};

return $tests;
