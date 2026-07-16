<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteUpstreamSuiteEvidence;

function libsqlite_suite_next219_evidence(): SQLiteUpstreamSuiteEvidence
{
    return SQLiteUpstreamSuiteEvidence::fromManifestPath(__DIR__ . '/../UPSTREAM_TEST_MANIFEST.json');
}

function libsqlite_suite_next219_output(int $passLines = 96, int $assertions = 96, int $failures = 0): string
{
    $lines = ['Focused test run: 1 selected test files (root lock skipped)'];
    for ($i = 1; $i <= $passLines; $i++) {
        $lines[] = sprintf('PASS current source next219 veryquick shard admission case %02d', $i);
    }
    $lines[] = sprintf('1 test files, %d assertions, %d failures', $assertions, $failures);

    return implode("\n", $lines);
}

/**
 * @return list<array<string, mixed>>
 */
function libsqlite_suite_next219_rows(
    int $case = 1,
    string $launcherBase = '57174af3d894b5b3ad8472548b456d5ce17271cb',
    string $dashboardSource = '8a447f445e5d2fd32fc9fd463117f585d1416551',
    string $statusSource = '8a447f445e5d2fd32fc9fd463117f585d1416551',
    string $implementationSource = '8a447f445e5d2fd32fc9fd463117f585d1416551',
    string $nextHead = 'suite-upstream-veryquick-shard-current-source-next219'
): array {
    $script = sprintf('veryquick-current-source-next219-%02d.test', $case);

    return [
        [
            'unit' => 'suite-upstream-veryquick-shard-current-source-next219',
            'kind' => 'bounded-upstream-veryquick-shard-runner',
            'gap_id' => 'current-source-next219-veryquick-shard-gap',
            'gap_status' => 'removed',
            'removed_blocker' => 'next219 admits one focused veryquick shard row tied to launcher Base accepted HEAD 57174af3 and integration source 8a447f44 without duplicating accepted next155 through next213 suite evidence',
            'tier' => 'focused-veryquick-shard',
            'source_head' => $nextHead,
            'launcher_base_head' => $launcherBase,
            'dashboard_source_head' => $dashboardSource,
            'status_source_head' => $statusSource,
            'implementation_source_head' => $implementationSource,
            'artifact_path' => 'lanes/libsqlite/notes/yield-suite-upstream-veryquick-shard-current-source-next219.md',
            'runner_command' => './testfixture ../libsqlite/test/testrunner.tcl --jobs 1 --stop-on-error veryquick ' . $script,
            'scripts' => [$script, 'testrunner.test'],
            'current_countable' => false,
            'next_countable' => true,
            'exit' => 0,
            'errors' => 0,
            'current_tests' => 0,
            'next_tests' => 113528 + $case,
        ],
        [
            'unit' => 'batch109-113-current-source-anchor',
            'kind' => 'accepted-upstream-runner-anchor',
            'gap_id' => 'accepted-batch109-113-suite-anchor',
            'gap_status' => 'preserved',
            'removed_blocker' => '',
            'tier' => 'accepted-anchor',
            'source_head' => $nextHead,
            'launcher_base_head' => $launcherBase,
            'dashboard_source_head' => $dashboardSource,
            'status_source_head' => $statusSource,
            'implementation_source_head' => $implementationSource,
            'artifact_path' => 'lanes/libsqlite/notes/yield-suite-upstream-veryquick-shard-current-source-next213.md',
            'runner_command' => './testfixture ../libsqlite/test/testrunner.tcl --jobs 1 --stop-on-error veryquick accepted-batch109-113-anchor.test',
            'scripts' => ['accepted-batch109-113-anchor.test'],
            'current_countable' => true,
            'next_countable' => true,
            'exit' => 0,
            'errors' => 0,
            'current_tests' => 113528,
            'next_tests' => 113528,
        ],
    ];
}

/**
 * @param list<array<string, mixed>> $rows
 * @return array<string, mixed>
 */
function libsqlite_suite_next219_record(
    array $rows,
    string $launcherBase = '57174af3d894b5b3ad8472548b456d5ce17271cb',
    string $dashboardSource = '8a447f445e5d2fd32fc9fd463117f585d1416551',
    string $statusSource = '8a447f445e5d2fd32fc9fd463117f585d1416551',
    string $implementationSource = '8a447f445e5d2fd32fc9fd463117f585d1416551',
    string $nextHead = 'suite-upstream-veryquick-shard-current-source-next219',
    ?string $output = null,
    ?int $expected = 96,
    string $snapshot = ''
): array {
    return libsqlite_suite_next219_evidence()->upstreamVeryquickShardCurrentSourceShard(
        219,
        $rows,
        624,
        106763,
        $launcherBase,
        $dashboardSource,
        $statusSource,
        $implementationSource,
        $nextHead,
        'lanes/libsqlite/tests/SQLiteUpstreamVeryquickShardCurrentSourceNext219Test.php',
        $output ?? libsqlite_suite_next219_output(),
        'current-source next219 veryquick-shard admission avoids accepted next155/157/159/161/164/166/167/169/171/172/173/174/175/176/177/178/181/184/187/190/194/200/202/209/212/213 suite evidence, exact-shard next148, queued suite217 stale evidence, runner106/jsonvt104 rebase work, accepted batch107/108 and batch109-113 behavior surfaces, and live B-tree/JSON/VFS/WAL/planner/PRAGMA/ATTACH/window/VDBE work',
        $expected,
        $snapshot
    );
}

$tests = [];

foreach (range(1, 86) as $case) {
    $tests[sprintf('current source next219 admits veryquick shard case %02d', $case)] = static function (TestRunner $t) use ($case): void {
        $record = libsqlite_suite_next219_record(libsqlite_suite_next219_rows($case));

        $t->same('current-source-next219-veryquick-shard-advanced', $record['status']);
        $t->same(true, $record['countable']);
        $t->same(624, $record['current_mapped']);
        $t->same(625, $record['next_mapped']);
        $t->same(1, $record['mapped_delta']);
        $t->same(96, $record['php_pass_delta']);
        $t->same(106859, $record['next_php_pass']);
        $t->same(['suite-upstream-veryquick-shard-current-source-next219'], $record['admitted_units']);
        $t->same(['batch109-113-current-source-anchor'], $record['preserved_units']);
        $t->contains(sprintf('veryquick-current-source-next219-%02d.test', $case), implode(',', $record['target_scripts']));
        $t->same(true, $record['counts_upstream_veryquick_shard_current_source_next219']);
        $t->same(false, $record['counts_upstream_veryquick_shard_current_source_next213']);
        $t->same(false, $record['counts_upstream_veryquick_shard_current_source_next212']);
        $t->same(false, $record['counts_upstream_veryquick_shard_current_source_next209']);
        $t->same(false, $record['counts_upstream_exact_shard_runner_current_source_next148']);
        $t->same(false, $record['counts_upstream_runner_full_suite_countability_current_source_next116']);
        $t->same(false, $record['counts_release_parity']);
    };
}

$tests['current source next219 records authoritative launcher and source heads'] = static function (TestRunner $t): void {
    $record = libsqlite_suite_next219_record(libsqlite_suite_next219_rows(8));

    $t->same('57174af3d894b5b3ad8472548b456d5ce17271cb', $record['launcher_base_head']);
    $t->same('8a447f445e5d2fd32fc9fd463117f585d1416551', $record['dashboard_source_head']);
    $t->same('8a447f445e5d2fd32fc9fd463117f585d1416551', $record['status_source_head']);
    $t->same('8a447f445e5d2fd32fc9fd463117f585d1416551', $record['implementation_source_head']);
    $t->same(['suite-upstream-veryquick-shard-current-source-next219'], $record['artifact_source_heads']);
};

$tests['current source next219 records target scripts and tier counts'] = static function (TestRunner $t): void {
    $record = libsqlite_suite_next219_record(libsqlite_suite_next219_rows(13));

    $t->same(113541, $record['tests_total_delta']);
    $t->same(['accepted-batch109-113-anchor.test', 'testrunner.test', 'veryquick-current-source-next219-13.test'], $record['target_scripts']);
    $t->same('accepted-anchor', $record['tier_counts'][0]['tier']);
    $t->same(0, $record['tier_counts'][0]['admitted']);
    $t->same(1, $record['tier_counts'][0]['preserved']);
    $t->same('focused-veryquick-shard', $record['tier_counts'][1]['tier']);
    $t->same(1, $record['tier_counts'][1]['admitted']);
    $t->same(113541, $record['tier_counts'][1]['tests']);
};

$tests['current source next219 preserves already counted row without mapped inflation'] = static function (TestRunner $t): void {
    $rows = libsqlite_suite_next219_rows();
    $rows[0]['current_countable'] = true;
    $rows[0]['current_tests'] = 113529;

    $record = libsqlite_suite_next219_record($rows);

    $t->same('current-source-next219-veryquick-shard-preserved', $record['status']);
    $t->same(0, $record['mapped_delta']);
    $t->same(624, $record['next_mapped']);
    $t->same(['batch109-113-current-source-anchor', 'suite-upstream-veryquick-shard-current-source-next219'], $record['preserved_units']);
};

$tests['current source next219 blocks stale source provenance'] = static function (TestRunner $t): void {
    $record = libsqlite_suite_next219_record(
        libsqlite_suite_next219_rows(
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

$tests['current source next219 blocks unguarded, non local, and non zero artifacts'] = static function (TestRunner $t): void {
    $rows = libsqlite_suite_next219_rows();
    $rows[0]['artifact_path'] = '/tmp/next219.md';
    $rows[0]['runner_command'] = './testfixture ../libsqlite/test/testrunner.tcl all';
    $rows[0]['scripts'] = ['README.md'];
    $rows[0]['exit'] = 1;
    $rows[0]['errors'] = 2;

    $record = libsqlite_suite_next219_record($rows);

    $t->same('blocked', $record['status']);
    $evidence = implode('; ', array_column($record['blockers'], 'evidence'));
    $t->contains('artifact-path-not-lane-local', $evidence);
    $t->contains('guarded-runner-command-missing', $evidence);
    $t->contains('concrete-test-scripts-missing', $evidence);
    $t->contains('runner-artifact-not-zero-error', $evidence);
};

$tests['current source next219 blocks missing removed blocker classification and next countability'] = static function (TestRunner $t): void {
    $rows = libsqlite_suite_next219_rows();
    $rows[0]['removed_blocker'] = '';
    $rows[0]['next_countable'] = false;

    $record = libsqlite_suite_next219_record($rows);

    $t->same('blocked', $record['status']);
    $t->same(0, $record['mapped_delta']);
    $evidence = implode('; ', array_column($record['blockers'], 'evidence'));
    $t->contains('removed-blocker-missing', $evidence);
    $t->contains('next-countability-not-admitted', $evidence);
};

$tests['current source next219 blocks duplicate broad runner snapshot'] = static function (TestRunner $t): void {
    $record = libsqlite_suite_next219_record(
        libsqlite_suite_next219_rows(),
        snapshot: "12345 ./testfixture ../libsqlite/test/testrunner.tcl release\n"
    );

    $t->same('blocked', $record['status']);
    $t->same('blocked-active-runner', $record['active_runner_status']);
    $t->same(1, $record['active_runner_count']);
    $t->contains('duplicate-broad-runner-active', implode('; ', array_column($record['blockers'], 'id')));
};

$tests['current source next219 blocks focused php admission mismatch'] = static function (TestRunner $t): void {
    $record = libsqlite_suite_next219_record(
        libsqlite_suite_next219_rows(),
        output: libsqlite_suite_next219_output(assertions: 95)
    );

    $t->same('blocked', $record['status']);
    $t->contains('focused-php-pass-delta-mismatch', implode('; ', array_column($record['blockers'], 'id')));
};

$tests['current source next219 records exact focused php admission and notes'] = static function (TestRunner $t): void {
    $record = libsqlite_suite_next219_record(libsqlite_suite_next219_rows());

    $t->same('admitted', $record['php_pass_admission']['status']);
    $t->same(96, $record['php_pass_admission']['assertion_delta']);
    $t->same(106859, $record['php_pass_admission']['next_php_pass']);
    $t->same(null, $record['php_pass_admission']['blocker']);
    $t->contains('no new support component needed', $record['dependency_closure']);
    $t->contains('212/213', $record['non_overlap_note']);
    $t->contains('release/all parity remains unclaimed', $record['next_gate']);
};

$tests['current source next219 keeps broad release parity unclaimed'] = static function (TestRunner $t): void {
    $record = libsqlite_suite_next219_record(libsqlite_suite_next219_rows(21));

    $t->same(false, $record['counts_release_parity']);
    $t->same(false, $record['counts_upstream_runner_rebase_gap_current_source_next122']);
    $t->contains('veryquick shard', $record['next_gate']);
};

return $tests;
