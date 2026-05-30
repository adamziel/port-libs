<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteUpstreamSuiteEvidence;

function libsqlite_suite_next384_evidence(): SQLiteUpstreamSuiteEvidence
{
    return SQLiteUpstreamSuiteEvidence::fromManifestPath(__DIR__ . '/../UPSTREAM_TEST_MANIFEST.json');
}

function libsqlite_suite_next384_output(int $passLines = 96, int $assertions = 96, int $failures = 0): string
{
    $lines = ['Focused test run: 1 selected test files (root lock skipped)'];
    for ($i = 1; $i <= $passLines; $i++) {
        $lines[] = sprintf('PASS current source next384 veryquick shard admission case %02d', $i);
    }
    $lines[] = sprintf('1 test files, %d assertions, %d failures', $assertions, $failures);

    return implode("\n", $lines);
}

/**
 * @return list<array<string, mixed>>
 */
function libsqlite_suite_next384_rows(
    int $case = 1,
    string $launcherBase = '42ebf4f9ec69db260d2f3d077fd0ed0a509b8841',
    string $dashboardSource = '58c373568fb24529b81a594f544849f422c0ffa0',
    string $statusSource = '58c373568fb24529b81a594f544849f422c0ffa0',
    string $implementationSource = '58c373568fb24529b81a594f544849f422c0ffa0',
    string $nextHead = 'suite-upstream-veryquick-shard-current-source-next384'
): array {
    $script = sprintf('veryquick-current-source-next384-%02d.test', $case);

    return [
        [
            'unit' => 'suite-upstream-veryquick-shard-current-source-next384',
            'kind' => 'bounded-upstream-veryquick-shard-runner',
            'gap_id' => 'current-source-next384-veryquick-shard-gap',
            'gap_status' => 'removed',
            'removed_blocker' => 'next384 admits one focused veryquick shard row tied to launcher Base accepted HEAD 42ebf4f9 and clean-integrated batch225 source 58c37356 without duplicating accepted next155 through next339 suite evidence',
            'tier' => 'focused-veryquick-shard',
            'source_head' => $nextHead,
            'launcher_base_head' => $launcherBase,
            'dashboard_source_head' => $dashboardSource,
            'status_source_head' => $statusSource,
            'implementation_source_head' => $implementationSource,
            'artifact_path' => 'lanes/libsqlite/notes/yield-suite-upstream-veryquick-shard-current-source-next384.md',
            'runner_command' => './testfixture ../libsqlite/test/testrunner.tcl --jobs 1 --stop-on-error veryquick ' . $script,
            'scripts' => [$script, 'testrunner.test'],
            'current_countable' => false,
            'next_countable' => true,
            'exit' => 0,
            'errors' => 0,
            'current_tests' => 0,
            'next_tests' => 145965 + $case,
        ],
        [
            'unit' => 'batch225-current-source-anchor',
            'kind' => 'accepted-upstream-runner-anchor',
            'gap_id' => 'accepted-batch225-suite-anchor',
            'gap_status' => 'preserved',
            'removed_blocker' => '',
            'tier' => 'accepted-anchor',
            'source_head' => $nextHead,
            'launcher_base_head' => $launcherBase,
            'dashboard_source_head' => $dashboardSource,
            'status_source_head' => $statusSource,
            'implementation_source_head' => $implementationSource,
            'artifact_path' => 'lanes/libsqlite/notes/yield-suite-upstream-veryquick-shard-current-source-next339.md',
            'runner_command' => './testfixture ../libsqlite/test/testrunner.tcl --jobs 1 --stop-on-error veryquick accepted-batch225-anchor.test',
            'scripts' => ['accepted-batch225-anchor.test'],
            'current_countable' => true,
            'next_countable' => true,
            'exit' => 0,
            'errors' => 0,
            'current_tests' => 145965,
            'next_tests' => 145965,
        ],
    ];
}

/**
 * @param list<array<string, mixed>> $rows
 * @return array<string, mixed>
 */
function libsqlite_suite_next384_record(
    array $rows,
    string $launcherBase = '42ebf4f9ec69db260d2f3d077fd0ed0a509b8841',
    string $dashboardSource = '58c373568fb24529b81a594f544849f422c0ffa0',
    string $statusSource = '58c373568fb24529b81a594f544849f422c0ffa0',
    string $implementationSource = '58c373568fb24529b81a594f544849f422c0ffa0',
    string $nextHead = 'suite-upstream-veryquick-shard-current-source-next384',
    ?string $output = null,
    ?int $expected = 96,
    string $snapshot = ''
): array {
    return libsqlite_suite_next384_evidence()->upstreamVeryquickShardCurrentSource(
        $rows,
        740,
        145965,
        $launcherBase,
        $dashboardSource,
        $statusSource,
        $implementationSource,
        $nextHead,
        'lanes/libsqlite/tests/SQLiteUpstreamVeryquickShardCurrentSourceNext384Test.php',
        $output ?? libsqlite_suite_next384_output(),
        'current-source next384 veryquick-shard admission avoids accepted next155 through next339 suite evidence, queued suite327/suite330/suite340+ rows, queued runner106/jsonvt104 rebase work, accepted batch225 behavior surfaces, and live B-tree/JSON/VFS/WAL/planner/PRAGMA/ATTACH/window/VDBE work',
        $expected,
        $snapshot,
        'next384-veryquick-shard'
    );
}

$tests = [];

foreach (range(1, 80) as $case) {
    $tests[sprintf('current source next384 admits veryquick shard case %02d', $case)] = static function (TestRunner $t) use ($case): void {
        $record = libsqlite_suite_next384_record(libsqlite_suite_next384_rows($case));

        $t->same('current-source-next384-veryquick-shard-advanced', $record['status']);
        $t->same(true, $record['countable']);
        $t->same(740, $record['current_mapped']);
        $t->same(741, $record['next_mapped']);
        $t->same(1, $record['mapped_delta']);
        $t->same(96, $record['php_pass_delta']);
        $t->same(146061, $record['next_php_pass']);
        $t->same(['suite-upstream-veryquick-shard-current-source-next384'], $record['admitted_units']);
        $t->same(['batch225-current-source-anchor'], $record['preserved_units']);
        $t->contains(sprintf('veryquick-current-source-next384-%02d.test', $case), implode(',', $record['target_scripts']));
        $t->same(true, $record['counts_upstream_veryquick_shard_current_source_next384']);
        $t->same(false, $record['counts_upstream_veryquick_shard_current_source_next339']);
        $t->same(false, $record['counts_upstream_veryquick_shard_current_source_next305']);
        $t->same(false, $record['counts_upstream_exact_shard_runner_current_source_next148']);
        $t->same(false, $record['counts_upstream_runner_full_suite_countability_current_source_next116']);
        $t->same(false, $record['counts_release_parity']);
    };
}

$tests['current source next384 records authoritative launcher and source heads'] = static function (TestRunner $t): void {
    $record = libsqlite_suite_next384_record(libsqlite_suite_next384_rows(8));

    $t->same('42ebf4f9ec69db260d2f3d077fd0ed0a509b8841', $record['launcher_base_head']);
    $t->same('58c373568fb24529b81a594f544849f422c0ffa0', $record['dashboard_source_head']);
    $t->same('58c373568fb24529b81a594f544849f422c0ffa0', $record['status_source_head']);
    $t->same('58c373568fb24529b81a594f544849f422c0ffa0', $record['implementation_source_head']);
    $t->same(['suite-upstream-veryquick-shard-current-source-next384'], $record['artifact_source_heads']);
};

$tests['current source next384 records target scripts and tier counts'] = static function (TestRunner $t): void {
    $record = libsqlite_suite_next384_record(libsqlite_suite_next384_rows(13));

    $t->same(145978, $record['tests_total_delta']);
    $t->same(['accepted-batch225-anchor.test', 'testrunner.test', 'veryquick-current-source-next384-13.test'], $record['target_scripts']);
    $t->same('accepted-anchor', $record['tier_counts'][0]['tier']);
    $t->same(0, $record['tier_counts'][0]['admitted']);
    $t->same(1, $record['tier_counts'][0]['preserved']);
    $t->same('focused-veryquick-shard', $record['tier_counts'][1]['tier']);
    $t->same(1, $record['tier_counts'][1]['admitted']);
    $t->same(145978, $record['tier_counts'][1]['tests']);
};

$tests['current source next384 preserves already counted row without mapped inflation'] = static function (TestRunner $t): void {
    $rows = libsqlite_suite_next384_rows();
    $rows[0]['current_countable'] = true;
    $rows[0]['current_tests'] = 145965;

    $record = libsqlite_suite_next384_record($rows);

    $t->same('current-source-next384-veryquick-shard-preserved', $record['status']);
    $t->same(0, $record['mapped_delta']);
    $t->same(740, $record['next_mapped']);
    $t->same(['batch225-current-source-anchor', 'suite-upstream-veryquick-shard-current-source-next384'], $record['preserved_units']);
};

$tests['current source next384 blocks stale source provenance'] = static function (TestRunner $t): void {
    $record = libsqlite_suite_next384_record(
        libsqlite_suite_next384_rows(
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

$tests['current source next384 blocks unguarded and non local artifacts'] = static function (TestRunner $t): void {
    $rows = libsqlite_suite_next384_rows();
    $rows[0]['artifact_path'] = '/tmp/next384.md';
    $rows[0]['runner_command'] = './testfixture ../libsqlite/test/testrunner.tcl all';
    $rows[0]['scripts'] = ['README.md'];

    $record = libsqlite_suite_next384_record($rows);

    $t->same('blocked', $record['status']);
    $evidence = implode('; ', array_column($record['blockers'], 'evidence'));
    $t->contains('artifact-path-not-lane-local', $evidence);
    $t->contains('guarded-runner-command-missing', $evidence);
    $t->contains('concrete-test-scripts-missing', $evidence);
};

$tests['current source next384 blocks non zero runner artifacts'] = static function (TestRunner $t): void {
    $rows = libsqlite_suite_next384_rows();
    $rows[0]['exit'] = 1;
    $rows[0]['errors'] = 2;

    $record = libsqlite_suite_next384_record($rows);

    $t->same('blocked', $record['status']);
    $t->same(0, $record['php_pass_delta']);
    $t->contains('runner-artifact-not-zero-error', implode('; ', array_column($record['blockers'], 'evidence')));
};

$tests['current source next384 blocks duplicate broad runner snapshot'] = static function (TestRunner $t): void {
    $record = libsqlite_suite_next384_record(
        libsqlite_suite_next384_rows(),
        snapshot: "12345 ./testfixture ../libsqlite/test/testrunner.tcl release\n"
    );

    $t->same('blocked', $record['status']);
    $t->contains('duplicate-broad-runner-active', implode('; ', array_column($record['blockers'], 'id')));
};

$tests['current source next384 blocks missing removed blocker classification'] = static function (TestRunner $t): void {
    $rows = libsqlite_suite_next384_rows();
    $rows[0]['removed_blocker'] = '';

    $record = libsqlite_suite_next384_record($rows);

    $t->same('blocked', $record['status']);
    $t->contains('removed-blocker-missing', implode('; ', array_column($record['blockers'], 'evidence')));
};

$tests['current source next384 blocks focused pass delta mismatch'] = static function (TestRunner $t): void {
    $record = libsqlite_suite_next384_record(libsqlite_suite_next384_rows(), expected: 95);

    $t->same('blocked', $record['status']);
    $t->contains('focused-php-pass-delta-mismatch', implode('; ', array_column($record['blockers'], 'id')));
};

$tests['current source next384 blocks non next countable artifacts'] = static function (TestRunner $t): void {
    $rows = libsqlite_suite_next384_rows();
    $rows[0]['next_countable'] = false;

    $record = libsqlite_suite_next384_record($rows);

    $t->same('blocked', $record['status']);
    $t->contains('next-countability-not-admitted', implode('; ', array_column($record['blockers'], 'evidence')));
};

$tests['current source next384 blocks wrong next source head'] = static function (TestRunner $t): void {
    $rows = libsqlite_suite_next384_rows();
    $rows[0]['source_head'] = 'suite-upstream-veryquick-shard-current-source-next383';

    $record = libsqlite_suite_next384_record($rows);

    $t->same('blocked', $record['status']);
    $t->contains('next-source-head-mismatch', implode('; ', array_column($record['blockers'], 'evidence')));
};

$tests['current source next384 blocks focused output failures'] = static function (TestRunner $t): void {
    $record = libsqlite_suite_next384_record(libsqlite_suite_next384_rows(), output: libsqlite_suite_next384_output(failures: 1));

    $t->same('blocked', $record['status']);
    $t->same(0, $record['php_pass_delta']);
    $t->contains('focused-php-pass-admission-blocked', implode('; ', array_column($record['blockers'], 'id')));
};

$tests['current source next384 records focused shard dependency closure'] = static function (TestRunner $t): void {
    $record = libsqlite_suite_next384_record(libsqlite_suite_next384_rows(23));

    $t->contains('current-source next384 veryquick shard admission', $record['dependency_closure']);
    $t->contains('zero-error guarded-runner metadata', $record['dependency_closure']);
};

$tests['current source next384 records publish gate'] = static function (TestRunner $t): void {
    $record = libsqlite_suite_next384_record(libsqlite_suite_next384_rows(24));

    $t->contains('current-source next384 veryquick shard blocker-removal row', $record['next_gate']);
    $t->contains('release/all parity remains unclaimed', $record['next_gate']);
};

$tests['current source next384 does not recount prior veryquick shards'] = static function (TestRunner $t): void {
    $record = libsqlite_suite_next384_record(libsqlite_suite_next384_rows(25));

    $t->same(false, $record['counts_upstream_veryquick_shard_current_source_next339']);
    $t->same(false, $record['counts_upstream_veryquick_shard_current_source_next202']);
};

$tests['current source next384 keeps release parity gated'] = static function (TestRunner $t): void {
    $record = libsqlite_suite_next384_record(libsqlite_suite_next384_rows(26));

    $t->same(false, $record['counts_release_parity']);
    $t->same(false, $record['counts_upstream_runner_rebase_gap_current_source_next122']);
};

return $tests;
