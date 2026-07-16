<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteUpstreamSuiteEvidence;

function libsqlite_suite_next177_evidence(): SQLiteUpstreamSuiteEvidence
{
    return SQLiteUpstreamSuiteEvidence::fromManifestPath(__DIR__ . '/../UPSTREAM_TEST_MANIFEST.json');
}

function libsqlite_suite_next177_output(int $assertions = 88, int $failures = 0): string
{
    return implode("\n", [
        'Focused test run: 1 selected test files (root lock skipped)',
        'PASS current source next177 admits veryquick shard',
        'PASS current source next177 preserves accepted current shard anchor',
        sprintf('1 test files, %d assertions, %d failures', $assertions, $failures),
    ]);
}

/**
 * @return list<array<string, mixed>>
 */
function libsqlite_suite_next177_rows(
    int $case = 1,
    string $launcherBase = '91105cd3bcf5b279cf48e4f07eba7d9688872bd0',
    string $dashboardSource = '8a447f445e5d2fd32fc9fd463117f585d1416551',
    string $statusSource = '8a447f445e5d2fd32fc9fd463117f585d1416551',
    string $implementationSource = '8a447f445e5d2fd32fc9fd463117f585d1416551',
    string $nextHead = 'suite-upstream-veryquick-shard-current-source-next177'
): array {
    $script = sprintf('veryquick-current-source-next177-%02d.test', $case);

    return [
        [
            'unit' => 'suite-upstream-veryquick-shard-current-source-next177',
            'kind' => 'bounded-upstream-veryquick-shard-runner',
            'gap_id' => 'current-source-next177-veryquick-shard-gap',
            'gap_status' => 'removed',
            'removed_blocker' => 'next177 admits one focused veryquick shard row tied to launcher Base accepted HEAD 91105cd3 and integration source 8a447f44',
            'tier' => 'focused-veryquick-shard',
            'source_head' => $nextHead,
            'launcher_base_head' => $launcherBase,
            'dashboard_source_head' => $dashboardSource,
            'status_source_head' => $statusSource,
            'implementation_source_head' => $implementationSource,
            'artifact_path' => 'lanes/libsqlite/notes/suite-upstream-veryquick-shard-current-source-next177.md',
            'runner_command' => './testfixture ../libsqlite/test/testrunner.tcl --jobs 1 --stop-on-error veryquick ' . $script,
            'scripts' => [$script, 'testrunner.test'],
            'current_countable' => false,
            'next_countable' => true,
            'exit' => 0,
            'errors' => 0,
            'current_tests' => 0,
            'next_tests' => 82000 + $case,
        ],
        [
            'unit' => 'batch174-current-source-anchor',
            'kind' => 'accepted-upstream-runner-anchor',
            'gap_id' => 'accepted-batch174-suite-anchor',
            'gap_status' => 'preserved',
            'removed_blocker' => '',
            'tier' => 'accepted-anchor',
            'source_head' => $nextHead,
            'launcher_base_head' => $launcherBase,
            'dashboard_source_head' => $dashboardSource,
            'status_source_head' => $statusSource,
            'implementation_source_head' => $implementationSource,
            'artifact_path' => 'lanes/libsqlite/notes/yield-suite-upstream-veryquick-shard-current-source-next171.md',
            'runner_command' => './testfixture ../libsqlite/test/testrunner.tcl --jobs 1 --stop-on-error veryquick accepted-batch174-anchor.test',
            'scripts' => ['accepted-batch174-anchor.test'],
            'current_countable' => true,
            'next_countable' => true,
            'exit' => 0,
            'errors' => 0,
            'current_tests' => 82000,
            'next_tests' => 82000,
        ],
    ];
}

/**
 * @param list<array<string, mixed>> $rows
 * @return array<string, mixed>
 */
function libsqlite_suite_next177_record(
    array $rows,
    string $launcherBase = '91105cd3bcf5b279cf48e4f07eba7d9688872bd0',
    string $dashboardSource = '8a447f445e5d2fd32fc9fd463117f585d1416551',
    string $statusSource = '8a447f445e5d2fd32fc9fd463117f585d1416551',
    string $implementationSource = '8a447f445e5d2fd32fc9fd463117f585d1416551',
    string $nextHead = 'suite-upstream-veryquick-shard-current-source-next177',
    ?string $output = null,
    ?int $expected = 88,
    string $snapshot = ''
): array {
    return libsqlite_suite_next177_evidence()->upstreamVeryquickShardCurrentSource($rows,
        613,
        82455,
        $launcherBase,
        $dashboardSource,
        $statusSource,
        $implementationSource,
        $nextHead,
        'lanes/libsqlite/tests/SQLiteUpstreamVeryquickShardCurrentSourceNext177Test.php',
        $output ?? libsqlite_suite_next177_output(),
        'current-source next177 veryquick-shard admission avoids accepted suite155/166/171/173/174, exact-shard next148, runner106/jsonvt104 rebase work, and accepted B-tree/JSON/VFS/WAL/planner/PRAGMA/ATTACH/window/VDBE behavior surfaces',
        $expected,
        $snapshot,
        'next177-veryquick-shard'
    );
}

$tests = [];

foreach (range(1, 54) as $case) {
    $tests[sprintf('current source next177 admits veryquick shard case %02d', $case)] = static function (TestRunner $t) use ($case): void {
        $record = libsqlite_suite_next177_record(libsqlite_suite_next177_rows($case));

        $t->same('current-source-next177-veryquick-shard-advanced', $record['status']);
        $t->same(true, $record['countable']);
        $t->same(613, $record['current_mapped']);
        $t->same(614, $record['next_mapped']);
        $t->same(1, $record['mapped_delta']);
        $t->same(88, $record['php_pass_delta']);
        $t->same(82543, $record['next_php_pass']);
        $t->same(['suite-upstream-veryquick-shard-current-source-next177'], $record['admitted_units']);
        $t->same(['batch174-current-source-anchor'], $record['preserved_units']);
        $t->contains(sprintf('veryquick-current-source-next177-%02d.test', $case), implode(',', $record['target_scripts']));
        $t->same(true, $record['counts_upstream_veryquick_shard_current_source_next177']);
        $t->same(false, $record['counts_upstream_veryquick_shard_current_source_next174']);
        $t->same(false, $record['counts_upstream_veryquick_shard_current_source_next173']);
        $t->same(false, $record['counts_upstream_veryquick_shard_current_source_next171']);
        $t->same(false, $record['counts_upstream_exact_shard_runner_current_source_next148']);
        $t->same(false, $record['counts_upstream_runner_full_suite_countability_current_source_next116']);
        $t->same(false, $record['counts_release_parity']);
    };
}

$tests['current source next177 records launcher and integration provenance'] = static function (TestRunner $t): void {
    $record = libsqlite_suite_next177_record(libsqlite_suite_next177_rows(7));

    $t->same('91105cd3bcf5b279cf48e4f07eba7d9688872bd0', $record['launcher_base_head']);
    $t->same('8a447f445e5d2fd32fc9fd463117f585d1416551', $record['dashboard_source_head']);
    $t->same('8a447f445e5d2fd32fc9fd463117f585d1416551', $record['status_source_head']);
    $t->same('8a447f445e5d2fd32fc9fd463117f585d1416551', $record['implementation_source_head']);
    $t->same(['suite-upstream-veryquick-shard-current-source-next177'], $record['artifact_source_heads']);
};

$tests['current source next177 records target scripts and tier counts'] = static function (TestRunner $t): void {
    $record = libsqlite_suite_next177_record(libsqlite_suite_next177_rows(12));

    $t->same(82012, $record['tests_total_delta']);
    $t->same(['accepted-batch174-anchor.test', 'testrunner.test', 'veryquick-current-source-next177-12.test'], $record['target_scripts']);
    $t->same('accepted-anchor', $record['tier_counts'][0]['tier']);
    $t->same(0, $record['tier_counts'][0]['admitted']);
    $t->same(1, $record['tier_counts'][0]['preserved']);
    $t->same('focused-veryquick-shard', $record['tier_counts'][1]['tier']);
    $t->same(1, $record['tier_counts'][1]['admitted']);
    $t->same(82012, $record['tier_counts'][1]['tests']);
};

$tests['current source next177 preserves already counted row without mapped inflation'] = static function (TestRunner $t): void {
    $rows = libsqlite_suite_next177_rows();
    $rows[0]['current_countable'] = true;
    $rows[0]['current_tests'] = 82001;

    $record = libsqlite_suite_next177_record($rows);

    $t->same('current-source-next177-veryquick-shard-preserved', $record['status']);
    $t->same(0, $record['mapped_delta']);
    $t->same(613, $record['next_mapped']);
    $t->same(['batch174-current-source-anchor', 'suite-upstream-veryquick-shard-current-source-next177'], $record['preserved_units']);
};

$tests['current source next177 blocks stale source provenance'] = static function (TestRunner $t): void {
    $record = libsqlite_suite_next177_record(
        libsqlite_suite_next177_rows(
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

$tests['current source next177 blocks unguarded and non local artifacts'] = static function (TestRunner $t): void {
    $rows = libsqlite_suite_next177_rows();
    $rows[0]['artifact_path'] = '/tmp/next177.md';
    $rows[0]['runner_command'] = './testfixture ../libsqlite/test/testrunner.tcl all';
    $rows[0]['scripts'] = ['README.md'];

    $record = libsqlite_suite_next177_record($rows);

    $t->same('blocked', $record['status']);
    $evidence = implode('; ', array_column($record['blockers'], 'evidence'));
    $t->contains('artifact-path-not-lane-local', $evidence);
    $t->contains('guarded-runner-command-missing', $evidence);
    $t->contains('concrete-test-scripts-missing', $evidence);
};

$tests['current source next177 blocks non zero runner artifacts'] = static function (TestRunner $t): void {
    $rows = libsqlite_suite_next177_rows();
    $rows[0]['exit'] = 1;
    $rows[0]['errors'] = 2;

    $record = libsqlite_suite_next177_record($rows);

    $t->same('blocked', $record['status']);
    $t->same(0, $record['php_pass_delta']);
    $t->contains('runner-artifact-not-zero-error', implode('; ', array_column($record['blockers'], 'evidence')));
};

$tests['current source next177 blocks missing removed blocker classification'] = static function (TestRunner $t): void {
    $rows = libsqlite_suite_next177_rows();
    $rows[0]['removed_blocker'] = '';

    $record = libsqlite_suite_next177_record($rows);

    $t->same('blocked', $record['status']);
    $t->contains('removed-blocker-missing', implode('; ', array_column($record['blockers'], 'evidence')));
};

$tests['current source next177 blocks duplicate broad runner snapshot'] = static function (TestRunner $t): void {
    $record = libsqlite_suite_next177_record(
        libsqlite_suite_next177_rows(),
        snapshot: "12345 ./testfixture ../libsqlite/test/testrunner.tcl all\n"
    );

    $t->same('blocked', $record['status']);
    $t->same('blocked-active-runner', $record['active_runner_status']);
    $t->same(1, $record['active_runner_count']);
    $t->contains('duplicate-broad-runner-active', implode('; ', array_column($record['blockers'], 'id')));
};

$tests['current source next177 blocks focused php admission mismatch'] = static function (TestRunner $t): void {
    $record = libsqlite_suite_next177_record(
        libsqlite_suite_next177_rows(),
        output: libsqlite_suite_next177_output(assertions: 87)
    );

    $t->same('blocked', $record['status']);
    $t->contains('focused-php-pass-delta-mismatch', implode('; ', array_column($record['blockers'], 'id')));
};

$tests['current source next177 carries dependency closure and non overlap notes'] = static function (TestRunner $t): void {
    $record = libsqlite_suite_next177_record(libsqlite_suite_next177_rows());

    $t->contains('no new support component needed', $record['dependency_closure']);
    $t->contains('suite155/166/171/173/174', $record['non_overlap_note']);
    $t->contains('release/all parity remains unclaimed', $record['next_gate']);
};

$tests['current source next177 rejects empty row list'] = static function (TestRunner $t): void {
    try {
        libsqlite_suite_next177_record([]);
        $t->fail('Expected empty next177 row list to be rejected');
    } catch (InvalidArgumentException $exception) {
        $t->contains('current-source next116 full-suite countability requires at least one row', $exception->getMessage());
    }
};

return $tests;
