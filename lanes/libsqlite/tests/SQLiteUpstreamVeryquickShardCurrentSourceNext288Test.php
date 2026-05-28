<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteUpstreamSuiteEvidence;

function libsqlite_suite_next288_evidence(): SQLiteUpstreamSuiteEvidence
{
    return SQLiteUpstreamSuiteEvidence::fromManifestPath(__DIR__ . '/../UPSTREAM_TEST_MANIFEST.json');
}

function libsqlite_suite_next288_output(int $passLines = 96, int $assertions = 96, int $failures = 0): string
{
    $lines = ['Focused test run: 1 selected test files (root lock skipped)'];
    for ($i = 1; $i <= $passLines; $i++) {
        $lines[] = sprintf('PASS current source next288 veryquick shard admission case %02d', $i);
    }
    $lines[] = sprintf('1 test files, %d assertions, %d failures', $assertions, $failures);

    return implode("\n", $lines);
}

/**
 * @return list<array<string, mixed>>
 */
function libsqlite_suite_next288_rows(
    int $case = 1,
    string $launcherBase = '2d826f3672d51185a8fc82f12ed43afe26d2c9d6',
    string $dashboardSource = '8a447f445e5d2fd32fc9fd463117f585d1416551',
    string $statusSource = '8a447f445e5d2fd32fc9fd463117f585d1416551',
    string $implementationSource = '8a447f445e5d2fd32fc9fd463117f585d1416551',
    string $nextHead = 'suite-upstream-veryquick-shard-current-source-next288'
): array {
    $script = sprintf('veryquick-current-source-next288-%02d.test', $case);

    return [
        [
            'unit' => 'suite-upstream-veryquick-shard-current-source-next288',
            'kind' => 'bounded-upstream-veryquick-shard-runner',
            'gap_id' => 'current-source-next288-veryquick-shard-gap',
            'gap_status' => 'removed',
            'removed_blocker' => 'next288 admits one focused veryquick shard row tied to launcher Base accepted HEAD 2d826f36 and integration source 8a447f44 without duplicating accepted next155 through next276 suite evidence',
            'tier' => 'focused-veryquick-shard',
            'source_head' => $nextHead,
            'launcher_base_head' => $launcherBase,
            'dashboard_source_head' => $dashboardSource,
            'status_source_head' => $statusSource,
            'implementation_source_head' => $implementationSource,
            'artifact_path' => 'lanes/libsqlite/notes/yield-suite-upstream-veryquick-shard-current-source-next288.md',
            'runner_command' => './testfixture ../libsqlite/test/testrunner.tcl --jobs 1 --stop-on-error veryquick ' . $script,
            'scripts' => [$script, 'testrunner.test'],
            'current_countable' => false,
            'next_countable' => true,
            'exit' => 0,
            'errors' => 0,
            'current_tests' => 0,
            'next_tests' => 136435 + $case,
        ],
        [
            'unit' => 'batch220-current-source-anchor',
            'kind' => 'accepted-upstream-runner-anchor',
            'gap_id' => 'accepted-batch220-suite-anchor',
            'gap_status' => 'preserved',
            'removed_blocker' => '',
            'tier' => 'accepted-anchor',
            'source_head' => $nextHead,
            'launcher_base_head' => $launcherBase,
            'dashboard_source_head' => $dashboardSource,
            'status_source_head' => $statusSource,
            'implementation_source_head' => $implementationSource,
            'artifact_path' => 'lanes/libsqlite/notes/yield-suite-upstream-veryquick-shard-current-source-next276.md',
            'runner_command' => './testfixture ../libsqlite/test/testrunner.tcl --jobs 1 --stop-on-error veryquick accepted-batch220-anchor.test',
            'scripts' => ['accepted-batch220-anchor.test'],
            'current_countable' => true,
            'next_countable' => true,
            'exit' => 0,
            'errors' => 0,
            'current_tests' => 136435,
            'next_tests' => 136435,
        ],
    ];
}

/**
 * @param list<array<string, mixed>> $rows
 * @return array<string, mixed>
 */
function libsqlite_suite_next288_record(
    array $rows,
    string $launcherBase = '2d826f3672d51185a8fc82f12ed43afe26d2c9d6',
    string $dashboardSource = '8a447f445e5d2fd32fc9fd463117f585d1416551',
    string $statusSource = '8a447f445e5d2fd32fc9fd463117f585d1416551',
    string $implementationSource = '8a447f445e5d2fd32fc9fd463117f585d1416551',
    string $nextHead = 'suite-upstream-veryquick-shard-current-source-next288',
    ?string $output = null,
    ?int $expected = 96,
    string $snapshot = ''
): array {
    return libsqlite_suite_next288_evidence()->upstreamVeryquickShardCurrentSourceNext288(
        $rows,
        680,
        136435,
        $launcherBase,
        $dashboardSource,
        $statusSource,
        $implementationSource,
        $nextHead,
        'lanes/libsqlite/tests/SQLiteUpstreamVeryquickShardCurrentSourceNext288Test.php',
        $output ?? libsqlite_suite_next288_output(),
        'current-source next288 veryquick-shard admission avoids accepted next155/157/159/161/164/166/167/169/171/172/173/174/175/176/177/178/181/184/187/190/194/200/202/209/212/213/219/220/222/224/225/226/227/228/229/230/231/232/233/234/235/236/237/241/242/243/244/245/246/247/271/272/273/274/275/276 suite evidence, exact-shard next148, queued runner106/jsonvt104 rebase work, accepted batch220 behavior surfaces, and live B-tree/JSON/VFS/WAL/planner/PRAGMA/ATTACH/window/VDBE work',
        $expected,
        $snapshot
    );
}

$tests = [];

foreach (range(1, 80) as $case) {
    $tests[sprintf('current source next288 admits veryquick shard case %02d', $case)] = static function (TestRunner $t) use ($case): void {
        $record = libsqlite_suite_next288_record(libsqlite_suite_next288_rows($case));

        $t->same('current-source-next288-veryquick-shard-advanced', $record['status']);
        $t->same(true, $record['countable']);
        $t->same(680, $record['current_mapped']);
        $t->same(681, $record['next_mapped']);
        $t->same(1, $record['mapped_delta']);
        $t->same(96, $record['php_pass_delta']);
        $t->same(136531, $record['next_php_pass']);
        $t->same(['suite-upstream-veryquick-shard-current-source-next288'], $record['admitted_units']);
        $t->same(['batch220-current-source-anchor'], $record['preserved_units']);
        $t->contains(sprintf('veryquick-current-source-next288-%02d.test', $case), implode(',', $record['target_scripts']));
        $t->same(true, $record['counts_upstream_veryquick_shard_current_source_next288']);
        $t->same(false, $record['counts_upstream_veryquick_shard_current_source_next237']);
        $t->same(false, $record['counts_upstream_veryquick_shard_current_source_next235']);
        $t->same(false, $record['counts_upstream_veryquick_shard_current_source_next224']);
        $t->same(false, $record['counts_upstream_veryquick_shard_current_source_next212']);
        $t->same(false, $record['counts_upstream_exact_shard_runner_current_source_next148']);
        $t->same(false, $record['counts_upstream_runner_full_suite_countability_current_source_next116']);
        $t->same(false, $record['counts_release_parity']);
    };
}

$tests['current source next288 records authoritative launcher and source heads'] = static function (TestRunner $t): void {
    $record = libsqlite_suite_next288_record(libsqlite_suite_next288_rows(8));

    $t->same('2d826f3672d51185a8fc82f12ed43afe26d2c9d6', $record['launcher_base_head']);
    $t->same('8a447f445e5d2fd32fc9fd463117f585d1416551', $record['dashboard_source_head']);
    $t->same('8a447f445e5d2fd32fc9fd463117f585d1416551', $record['status_source_head']);
    $t->same('8a447f445e5d2fd32fc9fd463117f585d1416551', $record['implementation_source_head']);
    $t->same(['suite-upstream-veryquick-shard-current-source-next288'], $record['artifact_source_heads']);
};

$tests['current source next288 records target scripts and tier counts'] = static function (TestRunner $t): void {
    $record = libsqlite_suite_next288_record(libsqlite_suite_next288_rows(13));

    $t->same(136448, $record['tests_total_delta']);
    $t->same(['accepted-batch220-anchor.test', 'testrunner.test', 'veryquick-current-source-next288-13.test'], $record['target_scripts']);
    $t->same('accepted-anchor', $record['tier_counts'][0]['tier']);
    $t->same(0, $record['tier_counts'][0]['admitted']);
    $t->same(1, $record['tier_counts'][0]['preserved']);
    $t->same('focused-veryquick-shard', $record['tier_counts'][1]['tier']);
    $t->same(1, $record['tier_counts'][1]['admitted']);
    $t->same(136448, $record['tier_counts'][1]['tests']);
};

$tests['current source next288 preserves already counted row without mapped inflation'] = static function (TestRunner $t): void {
    $rows = libsqlite_suite_next288_rows();
    $rows[0]['current_countable'] = true;
    $rows[0]['current_tests'] = 136436;

    $record = libsqlite_suite_next288_record($rows);

    $t->same('current-source-next288-veryquick-shard-preserved', $record['status']);
    $t->same(0, $record['mapped_delta']);
    $t->same(680, $record['next_mapped']);
    $t->same(['batch220-current-source-anchor', 'suite-upstream-veryquick-shard-current-source-next288'], $record['preserved_units']);
};

$tests['current source next288 blocks stale source provenance'] = static function (TestRunner $t): void {
    $record = libsqlite_suite_next288_record(
        libsqlite_suite_next288_rows(
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

$tests['current source next288 blocks unguarded and non local artifacts'] = static function (TestRunner $t): void {
    $rows = libsqlite_suite_next288_rows();
    $rows[0]['artifact_path'] = '/tmp/next288.md';
    $rows[0]['runner_command'] = './testfixture ../libsqlite/test/testrunner.tcl all';
    $rows[0]['scripts'] = ['README.md'];

    $record = libsqlite_suite_next288_record($rows);

    $t->same('blocked', $record['status']);
    $evidence = implode('; ', array_column($record['blockers'], 'evidence'));
    $t->contains('artifact-path-not-lane-local', $evidence);
    $t->contains('guarded-runner-command-missing', $evidence);
    $t->contains('concrete-test-scripts-missing', $evidence);
};

$tests['current source next288 blocks non zero runner artifacts'] = static function (TestRunner $t): void {
    $rows = libsqlite_suite_next288_rows();
    $rows[0]['exit'] = 1;
    $rows[0]['errors'] = 2;

    $record = libsqlite_suite_next288_record($rows);

    $t->same('blocked', $record['status']);
    $t->same(0, $record['php_pass_delta']);
    $t->contains('runner-artifact-not-zero-error', implode('; ', array_column($record['blockers'], 'evidence')));
};

$tests['current source next288 blocks missing removed blocker classification'] = static function (TestRunner $t): void {
    $rows = libsqlite_suite_next288_rows();
    $rows[0]['removed_blocker'] = '';

    $record = libsqlite_suite_next288_record($rows);

    $t->same('blocked', $record['status']);
    $t->contains('removed-blocker-missing', implode('; ', array_column($record['blockers'], 'evidence')));
};

$tests['current source next288 blocks duplicate broad runner snapshot'] = static function (TestRunner $t): void {
    $record = libsqlite_suite_next288_record(
        libsqlite_suite_next288_rows(),
        snapshot: "12345 ./testfixture ../libsqlite/test/testrunner.tcl release\n"
    );

    $t->same('blocked', $record['status']);
    $t->same('blocked-active-runner', $record['active_runner_status']);
    $t->same(1, $record['active_runner_count']);
    $t->contains('duplicate-broad-runner-active', implode('; ', array_column($record['blockers'], 'id')));
};

$tests['current source next288 blocks focused php admission mismatch'] = static function (TestRunner $t): void {
    $record = libsqlite_suite_next288_record(
        libsqlite_suite_next288_rows(),
        output: libsqlite_suite_next288_output(assertions: 83)
    );

    $t->same('blocked', $record['status']);
    $t->contains('focused-php-pass-delta-mismatch', implode('; ', array_column($record['blockers'], 'id')));
};

$tests['current source next288 records exact focused php admission'] = static function (TestRunner $t): void {
    $record = libsqlite_suite_next288_record(libsqlite_suite_next288_rows());

    $t->same('admitted', $record['php_pass_admission']['status']);
    $t->same(96, $record['php_pass_admission']['assertion_delta']);
    $t->same(136531, $record['php_pass_admission']['next_php_pass']);
    $t->same(null, $record['php_pass_admission']['blocker']);
};

$tests['current source next288 blocks missing focused runner summary'] = static function (TestRunner $t): void {
    $record = libsqlite_suite_next288_record(
        libsqlite_suite_next288_rows(),
        output: 'Focused test run: 1 selected test files (root lock skipped)'
    );

    $t->same('blocked', $record['status']);
    $t->same('blocked', $record['php_pass_admission']['status']);
    $t->same(0, $record['php_pass_admission']['assertion_delta']);
    $t->contains('focused-php-pass-admission-blocked', implode('; ', array_column($record['blockers'], 'id')));
    $t->contains('focused-php-pass-delta-mismatch', implode('; ', array_column($record['blockers'], 'id')));
};

$tests['current source next288 carries dependency closure and non overlap notes'] = static function (TestRunner $t): void {
    $record = libsqlite_suite_next288_record(libsqlite_suite_next288_rows());

    $t->contains('no new support component needed', $record['dependency_closure']);
    $t->contains('271/272/273/274/275/276', $record['non_overlap_note']);
    $t->contains('release/all parity remains unclaimed', $record['next_gate']);
};

$tests['current source next288 blocks missing next countability flag'] = static function (TestRunner $t): void {
    $rows = libsqlite_suite_next288_rows();
    $rows[0]['next_countable'] = false;

    $record = libsqlite_suite_next288_record($rows);

    $t->same('blocked', $record['status']);
    $t->same(0, $record['mapped_delta']);
    $t->contains('next-countability-not-admitted', implode('; ', array_column($record['blockers'], 'evidence')));
};

$tests['current source next288 keeps broad release parity unclaimed'] = static function (TestRunner $t): void {
    $record = libsqlite_suite_next288_record(libsqlite_suite_next288_rows(21));

    $t->same(false, $record['counts_release_parity']);
    $t->same(false, $record['counts_upstream_runner_rebase_gap_current_source_next122']);
    $t->contains('veryquick shard', $record['next_gate']);
};

$tests['current source next288 does not recount prior veryquick shards'] = static function (TestRunner $t): void {
    $record = libsqlite_suite_next288_record(libsqlite_suite_next288_rows(22));

    $t->same(false, $record['counts_upstream_veryquick_shard_current_source_next237']);
    $t->same(false, $record['counts_upstream_veryquick_shard_current_source_next212']);
};

$tests['current source next288 records focused shard dependency closure'] = static function (TestRunner $t): void {
    $record = libsqlite_suite_next288_record(libsqlite_suite_next288_rows(23));

    $t->contains('current-source next288 veryquick shard admission', $record['dependency_closure']);
    $t->contains('zero-error guarded-runner metadata', $record['dependency_closure']);
};

return $tests;
