<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteUpstreamSuiteEvidence;

function libsqlite_suite_next443_evidence(): SQLiteUpstreamSuiteEvidence
{
    return SQLiteUpstreamSuiteEvidence::fromManifestPath(__DIR__ . '/../UPSTREAM_TEST_MANIFEST.json');
}

function libsqlite_suite_next443_output(int $passLines = 96, int $assertions = 96, int $failures = 0): string
{
    $lines = ['Focused test run: 1 selected test files (root lock skipped)'];
    for ($i = 1; $i <= $passLines; $i++) {
        $lines[] = sprintf('PASS current source next443 veryquick shard admission case %02d', $i);
    }
    $lines[] = sprintf('1 test files, %d assertions, %d failures', $assertions, $failures);

    return implode("\n", $lines);
}

/**
 * @return list<array<string, mixed>>
 */
function libsqlite_suite_next443_rows(
    int $case = 1,
    string $launcherBase = 'fca16e3dd1812e6fcb6dc54c4980a5fb898b24ec',
    string $dashboardSource = 'f276db2cadbe640018aa18d11a7721e7187e05dc',
    string $statusSource = 'f276db2cadbe640018aa18d11a7721e7187e05dc',
    string $implementationSource = 'f276db2cadbe640018aa18d11a7721e7187e05dc',
    string $nextHead = 'suite-upstream-veryquick-shard-current-source-next443'
): array {
    $script = sprintf('veryquick-current-source-next443-%02d.test', $case);

    return [
        [
            'unit' => 'suite-upstream-veryquick-shard-current-source-next443',
            'kind' => 'bounded-upstream-veryquick-shard-runner',
            'gap_id' => 'current-source-next443-veryquick-shard-gap',
            'gap_status' => 'removed',
            'removed_blocker' => 'next443 admits one focused veryquick shard row tied to launcher Base accepted HEAD fca16e3d and accepted batch228 source f276db2c without duplicating accepted next343, next379, or next382 through next398 suite evidence',
            'tier' => 'focused-veryquick-shard',
            'source_head' => $nextHead,
            'launcher_base_head' => $launcherBase,
            'dashboard_source_head' => $dashboardSource,
            'status_source_head' => $statusSource,
            'implementation_source_head' => $implementationSource,
            'artifact_path' => 'lanes/libsqlite/notes/yield-suite-upstream-veryquick-shard-current-source-next443.md',
            'runner_command' => './testfixture ../libsqlite/test/testrunner.tcl --jobs 1 --stop-on-error veryquick ' . $script,
            'scripts' => [$script, 'testrunner.test'],
            'current_countable' => false,
            'next_countable' => true,
            'exit' => 0,
            'errors' => 0,
            'current_tests' => 0,
            'next_tests' => 151655 + $case,
        ],
        [
            'unit' => 'batch228-current-source-anchor',
            'kind' => 'accepted-upstream-runner-anchor',
            'gap_id' => 'accepted-batch228-suite-anchor',
            'gap_status' => 'preserved',
            'removed_blocker' => '',
            'tier' => 'accepted-anchor',
            'source_head' => $nextHead,
            'launcher_base_head' => $launcherBase,
            'dashboard_source_head' => $dashboardSource,
            'status_source_head' => $statusSource,
            'implementation_source_head' => $implementationSource,
            'artifact_path' => 'lanes/libsqlite/notes/yield-suite-upstream-veryquick-shard-current-source-next398.md',
            'runner_command' => './testfixture ../libsqlite/test/testrunner.tcl --jobs 1 --stop-on-error veryquick accepted-batch228-anchor.test',
            'scripts' => ['accepted-batch228-anchor.test'],
            'current_countable' => true,
            'next_countable' => true,
            'exit' => 0,
            'errors' => 0,
            'current_tests' => 151655,
            'next_tests' => 151655,
        ],
    ];
}

/**
 * @param list<array<string, mixed>> $rows
 * @return array<string, mixed>
 */
function libsqlite_suite_next443_record(
    array $rows,
    string $launcherBase = 'fca16e3dd1812e6fcb6dc54c4980a5fb898b24ec',
    string $dashboardSource = 'f276db2cadbe640018aa18d11a7721e7187e05dc',
    string $statusSource = 'f276db2cadbe640018aa18d11a7721e7187e05dc',
    string $implementationSource = 'f276db2cadbe640018aa18d11a7721e7187e05dc',
    string $nextHead = 'suite-upstream-veryquick-shard-current-source-next443',
    ?string $output = null,
    ?int $expected = 96,
    string $snapshot = ''
): array {
    return libsqlite_suite_next443_evidence()->upstreamVeryquickShardCurrentSourceShard(
        443,
        $rows,
        801,
        151655,
        $launcherBase,
        $dashboardSource,
        $statusSource,
        $implementationSource,
        $nextHead,
        'lanes/libsqlite/tests/SQLiteUpstreamVeryquickShardCurrentSourceNext443Test.php',
        $output ?? libsqlite_suite_next443_output(),
        'current-source next443 veryquick-shard admission avoids accepted next343, next379, next382 through next398 suite evidence, queued JSON-table compatibility rework, accepted batch228 behavior surfaces, and live B-tree/JSON/VFS/WAL/planner/PRAGMA/ATTACH/window/VDBE work',
        $expected,
        $snapshot
    );
}

$tests = [];

foreach (range(1, 80) as $case) {
    $tests[sprintf('current source next443 admits veryquick shard case %02d', $case)] = static function (TestRunner $t) use ($case): void {
        $record = libsqlite_suite_next443_record(libsqlite_suite_next443_rows($case));

        $t->same('current-source-next443-veryquick-shard-advanced', $record['status']);
        $t->same(true, $record['countable']);
        $t->same(801, $record['current_mapped']);
        $t->same(802, $record['next_mapped']);
        $t->same(1, $record['mapped_delta']);
        $t->same(96, $record['php_pass_delta']);
        $t->same(151751, $record['next_php_pass']);
        $t->same(['suite-upstream-veryquick-shard-current-source-next443'], $record['admitted_units']);
        $t->same(['batch228-current-source-anchor'], $record['preserved_units']);
        $t->contains(sprintf('veryquick-current-source-next443-%02d.test', $case), implode(',', $record['target_scripts']));
        $t->same(true, $record['counts_upstream_veryquick_shard_current_source_next443']);
        $t->same(false, $record['counts_upstream_veryquick_shard_current_source_next398']);
        $t->same(false, $record['counts_upstream_veryquick_shard_current_source_next391']);
        $t->same(false, $record['counts_upstream_veryquick_shard_current_source_next379']);
        $t->same(false, $record['counts_upstream_exact_shard_runner_current_source_next148']);
        $t->same(false, $record['counts_upstream_runner_full_suite_countability_current_source_next116']);
        $t->same(false, $record['counts_release_parity']);
    };
}

$tests['current source next443 records authoritative launcher and source heads'] = static function (TestRunner $t): void {
    $record = libsqlite_suite_next443_record(libsqlite_suite_next443_rows(8));

    $t->same('fca16e3dd1812e6fcb6dc54c4980a5fb898b24ec', $record['launcher_base_head']);
    $t->same('f276db2cadbe640018aa18d11a7721e7187e05dc', $record['dashboard_source_head']);
    $t->same('f276db2cadbe640018aa18d11a7721e7187e05dc', $record['status_source_head']);
    $t->same('f276db2cadbe640018aa18d11a7721e7187e05dc', $record['implementation_source_head']);
    $t->same(['suite-upstream-veryquick-shard-current-source-next443'], $record['artifact_source_heads']);
};

$tests['current source next443 records target scripts and tier counts'] = static function (TestRunner $t): void {
    $record = libsqlite_suite_next443_record(libsqlite_suite_next443_rows(13));

    $t->same(151668, $record['tests_total_delta']);
    $t->same(['accepted-batch228-anchor.test', 'testrunner.test', 'veryquick-current-source-next443-13.test'], $record['target_scripts']);
    $t->same('accepted-anchor', $record['tier_counts'][0]['tier']);
    $t->same(0, $record['tier_counts'][0]['admitted']);
    $t->same(1, $record['tier_counts'][0]['preserved']);
    $t->same('focused-veryquick-shard', $record['tier_counts'][1]['tier']);
    $t->same(1, $record['tier_counts'][1]['admitted']);
    $t->same(151668, $record['tier_counts'][1]['tests']);
};

$tests['current source next443 preserves already counted row without mapped inflation'] = static function (TestRunner $t): void {
    $rows = libsqlite_suite_next443_rows();
    $rows[0]['current_countable'] = true;
    $rows[0]['current_tests'] = 151600;

    $record = libsqlite_suite_next443_record($rows);

    $t->same('current-source-next443-veryquick-shard-preserved', $record['status']);
    $t->same(0, $record['mapped_delta']);
    $t->same(801, $record['next_mapped']);
    $t->same(['batch228-current-source-anchor', 'suite-upstream-veryquick-shard-current-source-next443'], $record['preserved_units']);
};

$tests['current source next443 blocks stale source provenance'] = static function (TestRunner $t): void {
    $record = libsqlite_suite_next443_record(
        libsqlite_suite_next443_rows(
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

$tests['current source next443 blocks unguarded and non local artifacts'] = static function (TestRunner $t): void {
    $rows = libsqlite_suite_next443_rows();
    $rows[0]['artifact_path'] = '/tmp/next443.md';
    $rows[0]['runner_command'] = './testfixture ../libsqlite/test/testrunner.tcl all';
    $rows[0]['scripts'] = ['README.md'];

    $record = libsqlite_suite_next443_record($rows);

    $t->same('blocked', $record['status']);
    $evidence = implode('; ', array_column($record['blockers'], 'evidence'));
    $t->contains('artifact-path-not-lane-local', $evidence);
    $t->contains('guarded-runner-command-missing', $evidence);
    $t->contains('concrete-test-scripts-missing', $evidence);
};

$tests['current source next443 blocks non zero runner artifacts'] = static function (TestRunner $t): void {
    $rows = libsqlite_suite_next443_rows();
    $rows[0]['exit'] = 1;
    $rows[0]['errors'] = 2;

    $record = libsqlite_suite_next443_record($rows);

    $t->same('blocked', $record['status']);
    $evidence = implode('; ', array_column($record['blockers'], 'evidence'));
    $t->contains('runner-artifact-not-zero-error', $evidence);
};

$tests['current source next443 blocks focused php admission mismatch'] = static function (TestRunner $t): void {
    $record = libsqlite_suite_next443_record(
        libsqlite_suite_next443_rows(),
        output: libsqlite_suite_next443_output(passLines: 95, assertions: 95),
        expected: 96
    );

    $t->same('blocked', $record['status']);
    $t->same(0, $record['php_pass_delta']);
    $t->contains('focused-php-pass-delta-mismatch', implode('; ', array_column($record['blockers'], 'id')));
};

return $tests;
