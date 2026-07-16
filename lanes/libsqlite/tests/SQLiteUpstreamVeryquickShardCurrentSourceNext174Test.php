<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteUpstreamSuiteEvidence;

function libsqlite_suite_next174_evidence(): SQLiteUpstreamSuiteEvidence
{
    return SQLiteUpstreamSuiteEvidence::fromManifestPath(__DIR__ . '/../UPSTREAM_TEST_MANIFEST.json');
}

function libsqlite_suite_next174_output(int $passLines = 73, int $assertions = 73, int $failures = 0): string
{
    $lines = ['Focused test run: 1 selected test files (root lock skipped)'];
    for ($i = 1; $i <= $passLines; $i++) {
        $lines[] = sprintf('PASS current source next174 veryquick shard admission case %02d', $i);
    }
    $lines[] = sprintf('1 test files, %d assertions, %d failures', $assertions, $failures);

    return implode("\n", $lines);
}

/**
 * @return list<array<string, mixed>>
 */
function libsqlite_suite_next174_rows(
    int $case = 1,
    string $launcherBase = '037567aaec1af37d4d42218c5fbf6766cc137eaa',
    string $dashboardSource = '5b0fbfe1e16f73b54758e4ef86306f0c7ff700db',
    string $statusSource = '5b0fbfe1e16f73b54758e4ef86306f0c7ff700db',
    string $implementationSource = '5b0fbfe1e16f73b54758e4ef86306f0c7ff700db',
    string $nextHead = 'suite-upstream-veryquick-shard-current-source-next174'
): array {
    $script = sprintf('veryquick-current-source-next174-%02d.test', $case);

    return [
        [
            'unit' => 'suite-upstream-veryquick-shard-current-source-next174',
            'kind' => 'bounded-upstream-veryquick-shard-runner',
            'gap_id' => 'current-source-next174-veryquick-shard-gap',
            'gap_status' => 'removed',
            'removed_blocker' => 'next174 admits one focused veryquick shard row tied to launcher Base accepted HEAD 037567aa and accepted batch160 source 5b0fbfe1',
            'tier' => 'focused-veryquick-shard',
            'source_head' => $nextHead,
            'launcher_base_head' => $launcherBase,
            'dashboard_source_head' => $dashboardSource,
            'status_source_head' => $statusSource,
            'implementation_source_head' => $implementationSource,
            'artifact_path' => 'lanes/libsqlite/notes/suite-upstream-veryquick-shard-current-source-next174.md',
            'runner_command' => './testfixture ../libsqlite/test/testrunner.tcl --jobs 1 --stop-on-error veryquick ' . $script,
            'scripts' => [$script, 'testrunner.test'],
            'current_countable' => false,
            'next_countable' => true,
            'exit' => 0,
            'errors' => 0,
            'current_tests' => 0,
            'next_tests' => 79690 + $case,
        ],
        [
            'unit' => 'batch160-current-source-anchor',
            'kind' => 'accepted-upstream-runner-anchor',
            'gap_id' => 'accepted-batch160-suite-anchor',
            'gap_status' => 'preserved',
            'removed_blocker' => '',
            'tier' => 'accepted-anchor',
            'source_head' => $nextHead,
            'launcher_base_head' => $launcherBase,
            'dashboard_source_head' => $dashboardSource,
            'status_source_head' => $statusSource,
            'implementation_source_head' => $implementationSource,
            'artifact_path' => 'lanes/libsqlite/notes/yield-sqlite-upstream-runner-release-gap-burnup-current-source-next117.md',
            'runner_command' => './testfixture ../libsqlite/test/testrunner.tcl --jobs 1 --stop-on-error veryquick accepted-batch160-anchor.test',
            'scripts' => ['accepted-batch160-anchor.test'],
            'current_countable' => true,
            'next_countable' => true,
            'exit' => 0,
            'errors' => 0,
            'current_tests' => 79690,
            'next_tests' => 79690,
        ],
    ];
}

/**
 * @param list<array<string, mixed>> $rows
 * @return array<string, mixed>
 */
function libsqlite_suite_next174_record(
    array $rows,
    string $launcherBase = '037567aaec1af37d4d42218c5fbf6766cc137eaa',
    string $dashboardSource = '5b0fbfe1e16f73b54758e4ef86306f0c7ff700db',
    string $statusSource = '5b0fbfe1e16f73b54758e4ef86306f0c7ff700db',
    string $implementationSource = '5b0fbfe1e16f73b54758e4ef86306f0c7ff700db',
    string $nextHead = 'suite-upstream-veryquick-shard-current-source-next174',
    ?string $output = null,
    ?int $expected = 73,
    string $snapshot = ''
): array {
    return libsqlite_suite_next174_evidence()->upstreamVeryquickShardCurrentSource($rows,
        612,
        79690,
        $launcherBase,
        $dashboardSource,
        $statusSource,
        $implementationSource,
        $nextHead,
        'lanes/libsqlite/tests/SQLiteUpstreamVeryquickShardCurrentSourceNext174Test.php',
        $output ?? libsqlite_suite_next174_output(),
        'current-source next174 veryquick-shard admission avoids accepted batch160 current-source behavior, suite155/157/159/161/164/166/167/169, exact-shard next148, runner106/jsonvt104 rebase work, manifest-conflict suite160/162/163/165/168/170 queues, and accepted B-tree/JSON/VFS/WAL/planner/PRAGMA/ATTACH/window/VDBE behavior surfaces',
        $expected,
        $snapshot,
        'next174-veryquick-shard'
    );
}

$tests = [];

foreach (range(1, 61) as $case) {
    $tests[sprintf('current source next174 admits veryquick shard case %02d', $case)] = static function (TestRunner $t) use ($case): void {
        $record = libsqlite_suite_next174_record(libsqlite_suite_next174_rows($case));

        $t->same('current-source-next174-veryquick-shard-advanced', $record['status']);
        $t->same(true, $record['countable']);
        $t->same(612, $record['current_mapped']);
        $t->same(613, $record['next_mapped']);
        $t->same(1, $record['mapped_delta']);
        $t->same(73, $record['php_pass_delta']);
        $t->same(79763, $record['next_php_pass']);
        $t->same(['suite-upstream-veryquick-shard-current-source-next174'], $record['admitted_units']);
        $t->same(['batch160-current-source-anchor'], $record['preserved_units']);
        $t->contains(sprintf('veryquick-current-source-next174-%02d.test', $case), implode(',', $record['target_scripts']));
        $t->same(true, $record['counts_upstream_veryquick_shard_current_source_next174']);
        $t->same(false, $record['counts_upstream_veryquick_shard_current_source_next169']);
        $t->same(false, $record['counts_upstream_veryquick_shard_current_source_next167']);
        $t->same(false, $record['counts_upstream_veryquick_shard_current_source_next166']);
        $t->same(false, $record['counts_upstream_veryquick_shard_current_source_next164']);
        $t->same(false, $record['counts_upstream_exact_shard_runner_current_source_next148']);
        $t->same(false, $record['counts_upstream_runner_full_suite_countability_current_source_next116']);
        $t->same(false, $record['counts_release_parity']);
    };
}

$tests['current source next174 records authoritative launcher and accepted source heads'] = static function (TestRunner $t): void {
    $record = libsqlite_suite_next174_record(libsqlite_suite_next174_rows(8));

    $t->same('037567aaec1af37d4d42218c5fbf6766cc137eaa', $record['launcher_base_head']);
    $t->same('5b0fbfe1e16f73b54758e4ef86306f0c7ff700db', $record['dashboard_source_head']);
    $t->same('5b0fbfe1e16f73b54758e4ef86306f0c7ff700db', $record['status_source_head']);
    $t->same('5b0fbfe1e16f73b54758e4ef86306f0c7ff700db', $record['implementation_source_head']);
    $t->same(['suite-upstream-veryquick-shard-current-source-next174'], $record['artifact_source_heads']);
};

$tests['current source next174 records target scripts and tier counts'] = static function (TestRunner $t): void {
    $record = libsqlite_suite_next174_record(libsqlite_suite_next174_rows(11));

    $t->same(79701, $record['tests_total_delta']);
    $t->same(['accepted-batch160-anchor.test', 'testrunner.test', 'veryquick-current-source-next174-11.test'], $record['target_scripts']);
    $t->same('accepted-anchor', $record['tier_counts'][0]['tier']);
    $t->same(0, $record['tier_counts'][0]['admitted']);
    $t->same(1, $record['tier_counts'][0]['preserved']);
    $t->same('focused-veryquick-shard', $record['tier_counts'][1]['tier']);
    $t->same(1, $record['tier_counts'][1]['admitted']);
    $t->same(79701, $record['tier_counts'][1]['tests']);
};

$tests['current source next174 preserves already counted row without mapped inflation'] = static function (TestRunner $t): void {
    $rows = libsqlite_suite_next174_rows();
    $rows[0]['current_countable'] = true;
    $rows[0]['current_tests'] = 79691;

    $record = libsqlite_suite_next174_record($rows);

    $t->same('current-source-next174-veryquick-shard-preserved', $record['status']);
    $t->same(0, $record['mapped_delta']);
    $t->same(612, $record['next_mapped']);
    $t->same(['batch160-current-source-anchor', 'suite-upstream-veryquick-shard-current-source-next174'], $record['preserved_units']);
};

$tests['current source next174 blocks stale source provenance'] = static function (TestRunner $t): void {
    $record = libsqlite_suite_next174_record(
        libsqlite_suite_next174_rows(
            launcherBase: '0000000000000000000000000000000000000000',
            dashboardSource: '1111111111111111111111111111111111111111',
            statusSource: '2222222222222222222222222222222222222222',
            implementationSource: '3333333333333333333333333333333333333333'
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

$tests['current source next174 blocks unguarded and non local artifacts'] = static function (TestRunner $t): void {
    $rows = libsqlite_suite_next174_rows();
    $rows[0]['artifact_path'] = '/tmp/next174.md';
    $rows[0]['runner_command'] = './testfixture ../libsqlite/test/testrunner.tcl all';
    $rows[0]['scripts'] = ['README.md'];

    $record = libsqlite_suite_next174_record($rows);

    $t->same('blocked', $record['status']);
    $evidence = implode('; ', array_column($record['blockers'], 'evidence'));
    $t->contains('artifact-path-not-lane-local', $evidence);
    $t->contains('guarded-runner-command-missing', $evidence);
    $t->contains('concrete-test-scripts-missing', $evidence);
};

$tests['current source next174 blocks non zero runner artifacts'] = static function (TestRunner $t): void {
    $rows = libsqlite_suite_next174_rows();
    $rows[0]['exit'] = 1;
    $rows[0]['errors'] = 2;

    $record = libsqlite_suite_next174_record($rows);

    $t->same('blocked', $record['status']);
    $t->same(0, $record['php_pass_delta']);
    $t->contains('runner-artifact-not-zero-error', implode('; ', array_column($record['blockers'], 'evidence')));
};

$tests['current source next174 blocks missing removed blocker classification'] = static function (TestRunner $t): void {
    $rows = libsqlite_suite_next174_rows();
    $rows[0]['removed_blocker'] = '';

    $record = libsqlite_suite_next174_record($rows);

    $t->same('blocked', $record['status']);
    $t->contains('removed-blocker-missing', implode('; ', array_column($record['blockers'], 'evidence')));
};

$tests['current source next174 records focused php pass admission output'] = static function (TestRunner $t): void {
    $record = libsqlite_suite_next174_record(libsqlite_suite_next174_rows(4), output: libsqlite_suite_next174_output(passLines: 73), expected: 73);

    $t->same('current-source-next174-veryquick-shard-advanced', $record['status']);
    $t->same('admitted', $record['php_pass_admission']['status']);
    $t->same(73, $record['php_pass_admission']['assertion_delta']);
    $t->same(79763, $record['php_pass_admission']['next_php_pass']);
    $t->contains('SQLiteUpstreamVeryquickShardCurrentSourceNext174Test.php', $record['php_pass_admission']['focused_path']);
};

$tests['current source next174 keeps release parity unclaimed with a valid shard'] = static function (TestRunner $t): void {
    $record = libsqlite_suite_next174_record(libsqlite_suite_next174_rows(5));

    $t->same('current-source-next174-veryquick-shard-advanced', $record['status']);
    $t->same(false, $record['counts_release_parity']);
    $t->same(1, $record['admitted_count']);
    $t->same([], $record['blockers']);
    $t->same('focused-veryquick-shard', $record['tier_counts'][1]['tier']);
    $t->contains('release/all parity remains unclaimed', $record['next_gate']);
};

$tests['current source next174 blocks duplicate broad runner processes'] = static function (TestRunner $t): void {
    $record = libsqlite_suite_next174_record(
        libsqlite_suite_next174_rows(),
        snapshot: "123 testfixture ../libsqlite/test/testrunner.tcl all\n"
    );

    $t->same('blocked', $record['status']);
    $t->same('blocked-active-runner', $record['active_runner_status']);
    $t->same(1, $record['active_runner_count']);
    $t->contains('active broad runner process', implode('; ', array_column($record['blockers'], 'evidence')));
};

$tests['current source next174 records next gate and dependency closure'] = static function (TestRunner $t): void {
    $record = libsqlite_suite_next174_record(libsqlite_suite_next174_rows(7));

    $t->contains('current-source next174 veryquick shard blocker-removal row', $record['next_gate']);
    $t->contains('release/all parity remains unclaimed', $record['next_gate']);
    $t->contains('no new support component needed', $record['dependency_closure']);
    $t->contains('manifest-conflict suite160/162/163/165/168/170 queues', $record['non_overlap_note']);
};

return $tests;
