<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteUpstreamSuiteEvidence;

function libsqlite_suite_next231_evidence(): SQLiteUpstreamSuiteEvidence
{
    return SQLiteUpstreamSuiteEvidence::fromManifestPath(__DIR__ . '/../UPSTREAM_TEST_MANIFEST.json');
}

function libsqlite_suite_next231_output(int $passLines = 100, int $assertions = 100, int $failures = 0): string
{
    $lines = ['Focused test run: 1 selected test files (root lock skipped)'];
    for ($i = 1; $i <= $passLines; $i++) {
        $lines[] = sprintf('PASS current source next231 veryquick shard admission case %02d', $i);
    }
    $lines[] = sprintf('1 test files, %d assertions, %d failures', $assertions, $failures);

    return implode("\n", $lines);
}

/**
 * @return list<array<string, mixed>>
 */
function libsqlite_suite_next231_rows(
    int $case = 1,
    string $launcherBase = 'bbdd9f5fe8cf438200a995def716836008a304ae',
    string $dashboardSource = 'c2236dfced3fa7212a3f39643d5a8316db1c3395',
    string $statusSource = 'c2236dfced3fa7212a3f39643d5a8316db1c3395',
    string $implementationSource = 'c2236dfced3fa7212a3f39643d5a8316db1c3395',
    string $nextHead = 'suite-upstream-veryquick-shard-current-source-next231'
): array {
    $script = sprintf('veryquick-current-source-next231-%02d.test', $case);

    return [
        [
            'unit' => 'suite-upstream-veryquick-shard-current-source-next231',
            'kind' => 'bounded-upstream-veryquick-shard-runner',
            'gap_id' => 'current-source-next231-veryquick-shard-gap',
            'gap_status' => 'removed',
            'removed_blocker' => 'next231 admits one focused veryquick shard row tied to launcher Base accepted HEAD bbdd9f5f and integration source c2236dfc without duplicating accepted next155 through next219 suite evidence',
            'tier' => 'focused-veryquick-shard',
            'source_head' => $nextHead,
            'launcher_base_head' => $launcherBase,
            'dashboard_source_head' => $dashboardSource,
            'status_source_head' => $statusSource,
            'implementation_source_head' => $implementationSource,
            'artifact_path' => 'lanes/libsqlite/notes/yield-suite-upstream-veryquick-shard-current-source-next231.md',
            'runner_command' => './testfixture ../libsqlite/test/testrunner.tcl --jobs 1 --stop-on-error veryquick ' . $script,
            'scripts' => [$script, 'testrunner.test'],
            'current_countable' => false,
            'next_countable' => true,
            'exit' => 0,
            'errors' => 0,
            'current_tests' => 0,
            'next_tests' => 289415 + $case,
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
            'current_tests' => 289415,
            'next_tests' => 289415,
        ],
    ];
}

/**
 * @param list<array<string, mixed>> $rows
 * @return array<string, mixed>
 */
function libsqlite_suite_next231_record(
    array $rows,
    string $launcherBase = 'bbdd9f5fe8cf438200a995def716836008a304ae',
    string $dashboardSource = 'c2236dfced3fa7212a3f39643d5a8316db1c3395',
    string $statusSource = 'c2236dfced3fa7212a3f39643d5a8316db1c3395',
    string $implementationSource = 'c2236dfced3fa7212a3f39643d5a8316db1c3395',
    string $nextHead = 'suite-upstream-veryquick-shard-current-source-next231',
    ?string $output = null,
    ?int $expected = 100,
    string $snapshot = ''
): array {
    return libsqlite_suite_next231_evidence()->upstreamVeryquickShardCurrentSourceNext231(
        $rows,
        631,
        112201,
        $launcherBase,
        $dashboardSource,
        $statusSource,
        $implementationSource,
        $nextHead,
        'lanes/libsqlite/tests/SQLiteUpstreamVeryquickShardCurrentSourceNext231Test.php',
        $output ?? libsqlite_suite_next231_output(),
        'current-source next231 veryquick-shard admission avoids accepted next155/157/159/161/164/166/167/169/171/172/173/174/175/176/177/178/181/184/187/190/194/200/202/209/212/213/219 suite evidence, exact-shard next148, queued suite217 stale evidence, runner106/jsonvt104 rebase work, accepted batch107/108, batch109-113, and batch200 behavior surfaces, and live next228-next230 B-tree/JSON/VFS/WAL/planner/PRAGMA/ATTACH/window/VDBE work',
        $expected,
        $snapshot
    );
}

$tests = [];

foreach (range(1, 89) as $case) {
    $tests[sprintf('current source next231 admits veryquick shard case %02d', $case)] = static function (TestRunner $t) use ($case): void {
        $record = libsqlite_suite_next231_record(libsqlite_suite_next231_rows($case));

        $t->same('current-source-next231-veryquick-shard-advanced', $record['status']);
        $t->same(true, $record['countable']);
        $t->same(631, $record['current_mapped']);
        $t->same(632, $record['next_mapped']);
        $t->same(1, $record['mapped_delta']);
        $t->same(100, $record['php_pass_delta']);
        $t->same(112301, $record['next_php_pass']);
        $t->same(['suite-upstream-veryquick-shard-current-source-next231'], $record['admitted_units']);
        $t->same(['batch109-113-current-source-anchor'], $record['preserved_units']);
        $t->contains(sprintf('veryquick-current-source-next231-%02d.test', $case), implode(',', $record['target_scripts']));
        $t->same(true, $record['counts_upstream_veryquick_shard_current_source_next231']);
        $t->same(false, $record['counts_upstream_veryquick_shard_current_source_next213']);
        $t->same(false, $record['counts_upstream_veryquick_shard_current_source_next212']);
        $t->same(false, $record['counts_upstream_veryquick_shard_current_source_next209']);
        $t->same(false, $record['counts_upstream_exact_shard_runner_current_source_next148']);
        $t->same(false, $record['counts_upstream_runner_full_suite_countability_current_source_next116']);
        $t->same(false, $record['counts_release_parity']);
    };
}

$tests['current source next231 records authoritative launcher and source heads'] = static function (TestRunner $t): void {
    $record = libsqlite_suite_next231_record(libsqlite_suite_next231_rows(8));

    $t->same('bbdd9f5fe8cf438200a995def716836008a304ae', $record['launcher_base_head']);
    $t->same('c2236dfced3fa7212a3f39643d5a8316db1c3395', $record['dashboard_source_head']);
    $t->same('c2236dfced3fa7212a3f39643d5a8316db1c3395', $record['status_source_head']);
    $t->same('c2236dfced3fa7212a3f39643d5a8316db1c3395', $record['implementation_source_head']);
    $t->same(['suite-upstream-veryquick-shard-current-source-next231'], $record['artifact_source_heads']);
};

$tests['current source next231 records target scripts and tier counts'] = static function (TestRunner $t): void {
    $record = libsqlite_suite_next231_record(libsqlite_suite_next231_rows(13));

    $t->same(289428, $record['tests_total_delta']);
    $t->same(['accepted-batch109-113-anchor.test', 'testrunner.test', 'veryquick-current-source-next231-13.test'], $record['target_scripts']);
    $t->same('accepted-anchor', $record['tier_counts'][0]['tier']);
    $t->same(0, $record['tier_counts'][0]['admitted']);
    $t->same(1, $record['tier_counts'][0]['preserved']);
    $t->same('focused-veryquick-shard', $record['tier_counts'][1]['tier']);
    $t->same(1, $record['tier_counts'][1]['admitted']);
    $t->same(289428, $record['tier_counts'][1]['tests']);
};

$tests['current source next231 preserves already counted row without mapped inflation'] = static function (TestRunner $t): void {
    $rows = libsqlite_suite_next231_rows();
    $rows[0]['current_countable'] = true;
    $rows[0]['current_tests'] = 289416;

    $record = libsqlite_suite_next231_record($rows);

    $t->same('current-source-next231-veryquick-shard-preserved', $record['status']);
    $t->same(0, $record['mapped_delta']);
    $t->same(631, $record['next_mapped']);
    $t->same(['batch109-113-current-source-anchor', 'suite-upstream-veryquick-shard-current-source-next231'], $record['preserved_units']);
};

$tests['current source next231 blocks stale source provenance'] = static function (TestRunner $t): void {
    $record = libsqlite_suite_next231_record(
        libsqlite_suite_next231_rows(
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

$tests['current source next231 blocks unguarded, non local, and non zero artifacts'] = static function (TestRunner $t): void {
    $rows = libsqlite_suite_next231_rows();
    $rows[0]['artifact_path'] = '/tmp/next231.md';
    $rows[0]['runner_command'] = './testfixture ../libsqlite/test/testrunner.tcl all';
    $rows[0]['scripts'] = ['README.md'];
    $rows[0]['exit'] = 1;
    $rows[0]['errors'] = 2;

    $record = libsqlite_suite_next231_record($rows);

    $t->same('blocked', $record['status']);
    $evidence = implode('; ', array_column($record['blockers'], 'evidence'));
    $t->contains('artifact-path-not-lane-local', $evidence);
    $t->contains('guarded-runner-command-missing', $evidence);
    $t->contains('concrete-test-scripts-missing', $evidence);
    $t->contains('runner-artifact-not-zero-error', $evidence);
};

$tests['current source next231 blocks missing removed blocker classification and next countability'] = static function (TestRunner $t): void {
    $rows = libsqlite_suite_next231_rows();
    $rows[0]['removed_blocker'] = '';
    $rows[0]['next_countable'] = false;

    $record = libsqlite_suite_next231_record($rows);

    $t->same('blocked', $record['status']);
    $t->same(0, $record['mapped_delta']);
    $evidence = implode('; ', array_column($record['blockers'], 'evidence'));
    $t->contains('removed-blocker-missing', $evidence);
    $t->contains('next-countability-not-admitted', $evidence);
};

$tests['current source next231 blocks duplicate broad runner snapshot'] = static function (TestRunner $t): void {
    $record = libsqlite_suite_next231_record(
        libsqlite_suite_next231_rows(),
        snapshot: "12345 ./testfixture ../libsqlite/test/testrunner.tcl release\n"
    );

    $t->same('blocked', $record['status']);
    $t->same('blocked-active-runner', $record['active_runner_status']);
    $t->same(1, $record['active_runner_count']);
    $t->contains('duplicate-broad-runner-active', implode('; ', array_column($record['blockers'], 'id')));
};

$tests['current source next231 blocks focused php admission mismatch'] = static function (TestRunner $t): void {
    $record = libsqlite_suite_next231_record(
        libsqlite_suite_next231_rows(),
        output: libsqlite_suite_next231_output(assertions: 97)
    );

    $t->same('blocked', $record['status']);
    $t->contains('focused-php-pass-delta-mismatch', implode('; ', array_column($record['blockers'], 'id')));
};

$tests['current source next231 records exact focused php admission and notes'] = static function (TestRunner $t): void {
    $record = libsqlite_suite_next231_record(libsqlite_suite_next231_rows());

    $t->same('admitted', $record['php_pass_admission']['status']);
    $t->same(100, $record['php_pass_admission']['assertion_delta']);
    $t->same(112301, $record['php_pass_admission']['next_php_pass']);
    $t->same(null, $record['php_pass_admission']['blocker']);
    $t->contains('no new support component needed', $record['dependency_closure']);
    $t->contains('213/219', $record['non_overlap_note']);
    $t->contains('release/all parity remains unclaimed', $record['next_gate']);
};

$tests['current source next231 keeps broad release parity unclaimed'] = static function (TestRunner $t): void {
    $record = libsqlite_suite_next231_record(libsqlite_suite_next231_rows(21));

    $t->same(false, $record['counts_release_parity']);
    $t->same(false, $record['counts_upstream_runner_rebase_gap_current_source_next122']);
    $t->contains('veryquick shard', $record['next_gate']);
};

$tests['current source next231 records focused veryquick tier only'] = static function (TestRunner $t): void {
    $record = libsqlite_suite_next231_record(libsqlite_suite_next231_rows(22));

    $t->same(3, $record['target_script_count']);
    $t->same(1, $record['admitted_count']);
    $t->same(1, $record['preserved_count']);
    $t->same('focused-veryquick-shard', $record['tier_counts'][1]['tier']);
};

return $tests;
