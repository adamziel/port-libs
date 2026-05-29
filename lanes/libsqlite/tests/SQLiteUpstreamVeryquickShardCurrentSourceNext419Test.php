<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteUpstreamSuiteEvidence;

function libsqlite_suite_next419_evidence(): SQLiteUpstreamSuiteEvidence
{
    return SQLiteUpstreamSuiteEvidence::fromManifestPath(__DIR__ . '/../UPSTREAM_TEST_MANIFEST.json');
}

function libsqlite_suite_next419_output(int $passLines = 96, int $assertions = 96, int $failures = 0): string
{
    $lines = ['Focused test run: 1 selected test files (root lock skipped)'];
    for ($i = 1; $i <= $passLines; $i++) {
        $lines[] = sprintf('PASS current source next419 veryquick shard admission case %02d', $i);
    }
    $lines[] = sprintf('1 test files, %d assertions, %d failures', $assertions, $failures);

    return implode("\n", $lines);
}

/**
 * @return list<array<string, mixed>>
 */
function libsqlite_suite_next419_rows(
    int $case = 1,
    string $launcherBase = '3baba579d7bc2e88269493208b2be99b75b78428',
    string $dashboardSource = '8a447f445e5d2fd32fc9fd463117f585d1416551',
    string $statusSource = '8a447f445e5d2fd32fc9fd463117f585d1416551',
    string $implementationSource = '8a447f445e5d2fd32fc9fd463117f585d1416551',
    string $nextHead = 'suite-upstream-veryquick-shard-current-source-next419'
): array {
    $script = sprintf('veryquick-current-source-next419-%02d.test', $case);

    return [
        [
            'unit' => 'suite-upstream-veryquick-shard-current-source-next419',
            'kind' => 'bounded-upstream-veryquick-shard-runner',
            'gap_id' => 'current-source-next419-veryquick-shard-gap',
            'gap_status' => 'removed',
            'removed_blocker' => 'next419 admits one focused veryquick shard row tied to launcher Base accepted HEAD 3baba579 and current integration source 8a447f44 without duplicating accepted next155 through next381 suite evidence',
            'tier' => 'focused-veryquick-shard',
            'source_head' => $nextHead,
            'launcher_base_head' => $launcherBase,
            'dashboard_source_head' => $dashboardSource,
            'status_source_head' => $statusSource,
            'implementation_source_head' => $implementationSource,
            'artifact_path' => 'lanes/libsqlite/notes/yield-suite-upstream-veryquick-shard-current-source-next419.md',
            'runner_command' => './testfixture ../libsqlite/test/testrunner.tcl --jobs 1 --stop-on-error veryquick ' . $script,
            'scripts' => [$script, 'testrunner.test'],
            'current_countable' => false,
            'next_countable' => true,
            'exit' => 0,
            'errors' => 0,
            'current_tests' => 0,
            'next_tests' => 149839 + $case,
        ],
        [
            'unit' => 'batch227-current-source-anchor',
            'kind' => 'accepted-upstream-runner-anchor',
            'gap_id' => 'accepted-batch227-suite-anchor',
            'gap_status' => 'preserved',
            'removed_blocker' => '',
            'tier' => 'accepted-anchor',
            'source_head' => $nextHead,
            'launcher_base_head' => $launcherBase,
            'dashboard_source_head' => $dashboardSource,
            'status_source_head' => $statusSource,
            'implementation_source_head' => $implementationSource,
            'artifact_path' => 'lanes/libsqlite/notes/yield-suite-upstream-veryquick-shard-current-source-next381.md',
            'runner_command' => './testfixture ../libsqlite/test/testrunner.tcl --jobs 1 --stop-on-error veryquick accepted-batch227-anchor.test',
            'scripts' => ['accepted-batch227-anchor.test'],
            'current_countable' => true,
            'next_countable' => true,
            'exit' => 0,
            'errors' => 0,
            'current_tests' => 149839,
            'next_tests' => 149839,
        ],
    ];
}

/**
 * @param list<array<string, mixed>> $rows
 * @return array<string, mixed>
 */
function libsqlite_suite_next419_record(
    array $rows,
    string $launcherBase = '3baba579d7bc2e88269493208b2be99b75b78428',
    string $dashboardSource = '8a447f445e5d2fd32fc9fd463117f585d1416551',
    string $statusSource = '8a447f445e5d2fd32fc9fd463117f585d1416551',
    string $implementationSource = '8a447f445e5d2fd32fc9fd463117f585d1416551',
    string $nextHead = 'suite-upstream-veryquick-shard-current-source-next419',
    ?string $output = null,
    ?int $expected = 96,
    string $snapshot = ''
): array {
    return libsqlite_suite_next419_evidence()->upstreamVeryquickShardCurrentSourceShard(419,
        $rows,
        782,
        149839,
        $launcherBase,
        $dashboardSource,
        $statusSource,
        $implementationSource,
        $nextHead,
        'lanes/libsqlite/tests/SQLiteUpstreamVeryquickShardCurrentSourceNext419Test.php',
        $output ?? libsqlite_suite_next419_output(),
        'current-source next419 veryquick-shard admission avoids accepted next155 through next381 suite evidence, queued runner106/jsonvt104 rebase work, accepted batch227 behavior surfaces, and live B-tree/JSON/VFS/WAL/planner/PRAGMA/ATTACH/window/VDBE work',
        $expected,
        $snapshot
    );
}

$tests = [];

foreach (range(1, 80) as $case) {
    $tests[sprintf('current source next419 admits veryquick shard case %02d', $case)] = static function (TestRunner $t) use ($case): void {
        $record = libsqlite_suite_next419_record(libsqlite_suite_next419_rows($case));

        $t->same('current-source-next419-veryquick-shard-advanced', $record['status']);
        $t->same(true, $record['countable']);
        $t->same(782, $record['current_mapped']);
        $t->same(783, $record['next_mapped']);
        $t->same(1, $record['mapped_delta']);
        $t->same(96, $record['php_pass_delta']);
        $t->same(149935, $record['next_php_pass']);
        $t->same(['suite-upstream-veryquick-shard-current-source-next419'], $record['admitted_units']);
        $t->same(['batch227-current-source-anchor'], $record['preserved_units']);
        $t->contains(sprintf('veryquick-current-source-next419-%02d.test', $case), implode(',', $record['target_scripts']));
        $t->same(true, $record['counts_upstream_veryquick_shard_current_source_next419']);
        $t->same(false, $record['counts_upstream_veryquick_shard_current_source_next381']);
        $t->same(false, $record['counts_upstream_veryquick_shard_current_source_next357']);
        $t->same(false, $record['counts_upstream_veryquick_shard_current_source_next339']);
        $t->same(false, $record['counts_upstream_exact_shard_runner_current_source_next148']);
        $t->same(false, $record['counts_upstream_runner_full_suite_countability_current_source_next116']);
        $t->same(false, $record['counts_release_parity']);
    };
}

$tests['current source next419 records authoritative launcher and source heads'] = static function (TestRunner $t): void {
    $record = libsqlite_suite_next419_record(libsqlite_suite_next419_rows(8));

    $t->same('3baba579d7bc2e88269493208b2be99b75b78428', $record['launcher_base_head']);
    $t->same('8a447f445e5d2fd32fc9fd463117f585d1416551', $record['dashboard_source_head']);
    $t->same('8a447f445e5d2fd32fc9fd463117f585d1416551', $record['status_source_head']);
    $t->same('8a447f445e5d2fd32fc9fd463117f585d1416551', $record['implementation_source_head']);
    $t->same(['suite-upstream-veryquick-shard-current-source-next419'], $record['artifact_source_heads']);
};

$tests['current source next419 records target scripts and tier counts'] = static function (TestRunner $t): void {
    $record = libsqlite_suite_next419_record(libsqlite_suite_next419_rows(13));

    $t->same(149852, $record['tests_total_delta']);
    $t->same(['accepted-batch227-anchor.test', 'testrunner.test', 'veryquick-current-source-next419-13.test'], $record['target_scripts']);
    $t->same('accepted-anchor', $record['tier_counts'][0]['tier']);
    $t->same(0, $record['tier_counts'][0]['admitted']);
    $t->same(1, $record['tier_counts'][0]['preserved']);
    $t->same('focused-veryquick-shard', $record['tier_counts'][1]['tier']);
    $t->same(1, $record['tier_counts'][1]['admitted']);
    $t->same(149852, $record['tier_counts'][1]['tests']);
};

$tests['current source next419 preserves already counted row without mapped inflation'] = static function (TestRunner $t): void {
    $rows = libsqlite_suite_next419_rows();
    $rows[0]['current_countable'] = true;
    $rows[0]['current_tests'] = 149840;

    $record = libsqlite_suite_next419_record($rows);

    $t->same('current-source-next419-veryquick-shard-preserved', $record['status']);
    $t->same(0, $record['mapped_delta']);
    $t->same(782, $record['next_mapped']);
    $t->same(['batch227-current-source-anchor', 'suite-upstream-veryquick-shard-current-source-next419'], $record['preserved_units']);
};

$tests['current source next419 blocks stale source provenance'] = static function (TestRunner $t): void {
    $record = libsqlite_suite_next419_record(
        libsqlite_suite_next419_rows(
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

$tests['current source next419 blocks unguarded and non local artifacts'] = static function (TestRunner $t): void {
    $rows = libsqlite_suite_next419_rows();
    $rows[0]['artifact_path'] = '/tmp/next419.md';
    $rows[0]['runner_command'] = './testfixture ../libsqlite/test/testrunner.tcl all';
    $rows[0]['scripts'] = ['README.md'];

    $record = libsqlite_suite_next419_record($rows);

    $t->same('blocked', $record['status']);
    $evidence = implode('; ', array_column($record['blockers'], 'evidence'));
    $t->contains('artifact-path-not-lane-local', $evidence);
    $t->contains('guarded-runner-command-missing', $evidence);
    $t->contains('concrete-test-scripts-missing', $evidence);
};

$tests['current source next419 blocks non zero runner artifacts'] = static function (TestRunner $t): void {
    $rows = libsqlite_suite_next419_rows();
    $rows[0]['exit'] = 1;
    $rows[0]['errors'] = 2;

    $record = libsqlite_suite_next419_record($rows);

    $t->same('blocked', $record['status']);
    $t->same(0, $record['php_pass_delta']);
    $t->contains('runner-artifact-not-zero-error', implode('; ', array_column($record['blockers'], 'evidence')));
};

$tests['current source next419 blocks PASS-line drift'] = static function (TestRunner $t): void {
    $record = libsqlite_suite_next419_record(
        libsqlite_suite_next419_rows(),
        output: libsqlite_suite_next419_output(passLines: 95, assertions: 95),
        expected: 96
    );

    $t->same('blocked', $record['status']);
    $t->same(0, $record['php_pass_delta']);
    $t->contains('focused PHP assertion delta did not match current-source next116 expected PASS movement', implode('; ', array_column($record['blockers'], 'evidence')));
};

$tests['current source next419 blocks active duplicate full suite snapshot'] = static function (TestRunner $t): void {
    $snapshot = "12345 ./testfixture ../libsqlite/test/testrunner.tcl all\n";

    $record = libsqlite_suite_next419_record(libsqlite_suite_next419_rows(), snapshot: $snapshot);

    $t->same('blocked', $record['status']);
    $t->same(0, $record['mapped_delta']);
    $t->contains('active broad runner process(es) detected', implode('; ', array_column($record['blockers'], 'evidence')));
};

return $tests;
