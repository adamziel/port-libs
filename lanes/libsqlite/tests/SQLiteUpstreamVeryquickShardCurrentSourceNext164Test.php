<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteUpstreamSuiteEvidence;

function libsqlite_suite_next164_evidence(): SQLiteUpstreamSuiteEvidence
{
    return SQLiteUpstreamSuiteEvidence::fromManifestPath(__DIR__ . '/../UPSTREAM_TEST_MANIFEST.json');
}

function libsqlite_suite_next164_output(int $passLines = 71, int $assertions = 71, int $failures = 0): string
{
    $lines = ['Focused test run: 1 selected test files (root lock skipped)'];
    for ($i = 1; $i <= $passLines; $i++) {
        $lines[] = sprintf('PASS current source next164 veryquick shard admission case %02d', $i);
    }
    $lines[] = sprintf('1 test files, %d assertions, %d failures', $assertions, $failures);

    return implode("\n", $lines);
}

/**
 * @return list<array<string, mixed>>
 */
function libsqlite_suite_next164_rows(
    int $case = 1,
    string $launcherBase = '527c4609e88266ed4ba728678115190bc13d6afa',
    string $dashboardSource = '8a447f445e5d2fd32fc9fd463117f585d1416551',
    string $statusSource = '8a447f445e5d2fd32fc9fd463117f585d1416551',
    string $implementationSource = '8a447f445e5d2fd32fc9fd463117f585d1416551',
    string $nextHead = 'suite-upstream-veryquick-shard-current-source-next164'
): array {
    $script = sprintf('veryquick-current-source-next164-%02d.test', $case);

    return [
        [
            'unit' => 'suite-upstream-veryquick-shard-current-source-next164',
            'kind' => 'bounded-upstream-veryquick-shard-runner',
            'gap_id' => 'current-source-next164-veryquick-shard-gap',
            'gap_status' => 'removed',
            'removed_blocker' => 'next164 admits one focused veryquick shard row tied to launcher Base accepted HEAD 527c4609 and integration source 8a447f44',
            'tier' => 'focused-veryquick-shard',
            'source_head' => $nextHead,
            'launcher_base_head' => $launcherBase,
            'dashboard_source_head' => $dashboardSource,
            'status_source_head' => $statusSource,
            'implementation_source_head' => $implementationSource,
            'artifact_path' => 'lanes/libsqlite/notes/suite-upstream-veryquick-shard-current-source-next164.md',
            'runner_command' => './testfixture ../libsqlite/test/testrunner.tcl --jobs 1 --stop-on-error veryquick ' . $script,
            'scripts' => [$script, 'testrunner.test'],
            'current_countable' => false,
            'next_countable' => true,
            'exit' => 0,
            'errors' => 0,
            'current_tests' => 0,
            'next_tests' => 73438 + $case,
        ],
        [
            'unit' => 'batch153-current-source-anchor',
            'kind' => 'accepted-upstream-runner-anchor',
            'gap_id' => 'accepted-batch153-suite-anchor',
            'gap_status' => 'preserved',
            'removed_blocker' => '',
            'tier' => 'accepted-anchor',
            'source_head' => $nextHead,
            'launcher_base_head' => $launcherBase,
            'dashboard_source_head' => $dashboardSource,
            'status_source_head' => $statusSource,
            'implementation_source_head' => $implementationSource,
            'artifact_path' => 'lanes/libsqlite/notes/suite-upstream-veryquick-shard-current-source-next161.md',
            'runner_command' => './testfixture ../libsqlite/test/testrunner.tcl --jobs 1 --stop-on-error veryquick accepted-batch153-anchor.test',
            'scripts' => ['accepted-batch153-anchor.test'],
            'current_countable' => true,
            'next_countable' => true,
            'exit' => 0,
            'errors' => 0,
            'current_tests' => 73438,
            'next_tests' => 73438,
        ],
    ];
}

/**
 * @param list<array<string, mixed>> $rows
 * @return array<string, mixed>
 */
function libsqlite_suite_next164_record(
    array $rows,
    string $launcherBase = '527c4609e88266ed4ba728678115190bc13d6afa',
    string $dashboardSource = '8a447f445e5d2fd32fc9fd463117f585d1416551',
    string $statusSource = '8a447f445e5d2fd32fc9fd463117f585d1416551',
    string $implementationSource = '8a447f445e5d2fd32fc9fd463117f585d1416551',
    string $nextHead = 'suite-upstream-veryquick-shard-current-source-next164',
    ?string $output = null,
    ?int $expected = 71,
    string $snapshot = ''
): array {
    return libsqlite_suite_next164_evidence()->upstreamVeryquickShardCurrentSource($rows,
        610,
        73438,
        $launcherBase,
        $dashboardSource,
        $statusSource,
        $implementationSource,
        $nextHead,
        'lanes/libsqlite/tests/SQLiteUpstreamVeryquickShardCurrentSourceNext164Test.php',
        $output ?? libsqlite_suite_next164_output(),
        'current-source next164 veryquick-shard admission avoids accepted batch153 next161 evidence, suite155/157/159, exact-shard next148, queued runner106/jsonvt104 rebase work, and accepted B-tree/JSON/VFS/WAL/planner/PRAGMA/ATTACH/window/VDBE behavior surfaces',
        $expected,
        $snapshot,
        'next164-veryquick-shard'
    );
}

$tests = [];

foreach (range(1, 60) as $case) {
    $tests[sprintf('current source next164 admits veryquick shard case %02d', $case)] = static function (TestRunner $t) use ($case): void {
        $record = libsqlite_suite_next164_record(libsqlite_suite_next164_rows($case));

        $t->same('current-source-next164-veryquick-shard-advanced', $record['status']);
        $t->same(true, $record['countable']);
        $t->same(610, $record['current_mapped']);
        $t->same(611, $record['next_mapped']);
        $t->same(1, $record['mapped_delta']);
        $t->same(71, $record['php_pass_delta']);
        $t->same(73509, $record['next_php_pass']);
        $t->same(['suite-upstream-veryquick-shard-current-source-next164'], $record['admitted_units']);
        $t->same(['batch153-current-source-anchor'], $record['preserved_units']);
        $t->contains(sprintf('veryquick-current-source-next164-%02d.test', $case), implode(',', $record['target_scripts']));
        $t->same(true, $record['counts_upstream_veryquick_shard_current_source_next164']);
        $t->same(false, $record['counts_upstream_veryquick_shard_current_source_next161']);
        $t->same(false, $record['counts_upstream_veryquick_shard_current_source_next159']);
        $t->same(false, $record['counts_upstream_veryquick_shard_current_source_next157']);
        $t->same(false, $record['counts_upstream_veryquick_shard_current_source_next155']);
        $t->same(false, $record['counts_upstream_exact_shard_runner_current_source_next148']);
        $t->same(false, $record['counts_upstream_runner_full_suite_countability_current_source_next116']);
        $t->same(false, $record['counts_release_parity']);
    };
}

$tests['current source next164 records authoritative launcher and source heads'] = static function (TestRunner $t): void {
    $record = libsqlite_suite_next164_record(libsqlite_suite_next164_rows(8));

    $t->same('527c4609e88266ed4ba728678115190bc13d6afa', $record['launcher_base_head']);
    $t->same('8a447f445e5d2fd32fc9fd463117f585d1416551', $record['dashboard_source_head']);
    $t->same('8a447f445e5d2fd32fc9fd463117f585d1416551', $record['status_source_head']);
    $t->same('8a447f445e5d2fd32fc9fd463117f585d1416551', $record['implementation_source_head']);
    $t->same(['suite-upstream-veryquick-shard-current-source-next164'], $record['artifact_source_heads']);
};

$tests['current source next164 records target scripts and tier counts'] = static function (TestRunner $t): void {
    $record = libsqlite_suite_next164_record(libsqlite_suite_next164_rows(11));

    $t->same(73449, $record['tests_total_delta']);
    $t->same(['accepted-batch153-anchor.test', 'testrunner.test', 'veryquick-current-source-next164-11.test'], $record['target_scripts']);
    $t->same('accepted-anchor', $record['tier_counts'][0]['tier']);
    $t->same(0, $record['tier_counts'][0]['admitted']);
    $t->same(1, $record['tier_counts'][0]['preserved']);
    $t->same('focused-veryquick-shard', $record['tier_counts'][1]['tier']);
    $t->same(1, $record['tier_counts'][1]['admitted']);
    $t->same(73449, $record['tier_counts'][1]['tests']);
};

$tests['current source next164 preserves already counted row without mapped inflation'] = static function (TestRunner $t): void {
    $rows = libsqlite_suite_next164_rows();
    $rows[0]['current_countable'] = true;
    $rows[0]['current_tests'] = 73439;

    $record = libsqlite_suite_next164_record($rows);

    $t->same('current-source-next164-veryquick-shard-preserved', $record['status']);
    $t->same(0, $record['mapped_delta']);
    $t->same(610, $record['next_mapped']);
    $t->same(['batch153-current-source-anchor', 'suite-upstream-veryquick-shard-current-source-next164'], $record['preserved_units']);
};

$tests['current source next164 blocks stale source provenance'] = static function (TestRunner $t): void {
    $record = libsqlite_suite_next164_record(
        libsqlite_suite_next164_rows(
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

$tests['current source next164 blocks unguarded and non local artifacts'] = static function (TestRunner $t): void {
    $rows = libsqlite_suite_next164_rows();
    $rows[0]['artifact_path'] = '/tmp/next164.md';
    $rows[0]['runner_command'] = './testfixture ../libsqlite/test/testrunner.tcl all';
    $rows[0]['scripts'] = ['README.md'];

    $record = libsqlite_suite_next164_record($rows);

    $t->same('blocked', $record['status']);
    $evidence = implode('; ', array_column($record['blockers'], 'evidence'));
    $t->contains('artifact-path-not-lane-local', $evidence);
    $t->contains('guarded-runner-command-missing', $evidence);
    $t->contains('concrete-test-scripts-missing', $evidence);
};

$tests['current source next164 blocks non zero runner artifacts'] = static function (TestRunner $t): void {
    $rows = libsqlite_suite_next164_rows();
    $rows[0]['exit'] = 1;
    $rows[0]['errors'] = 2;

    $record = libsqlite_suite_next164_record($rows);

    $t->same('blocked', $record['status']);
    $t->same(0, $record['php_pass_delta']);
    $t->contains('runner-artifact-not-zero-error', implode('; ', array_column($record['blockers'], 'evidence')));
};

$tests['current source next164 blocks missing removed blocker classification'] = static function (TestRunner $t): void {
    $rows = libsqlite_suite_next164_rows();
    $rows[0]['removed_blocker'] = '';

    $record = libsqlite_suite_next164_record($rows);

    $t->same('blocked', $record['status']);
    $t->contains('removed-blocker-missing', implode('; ', array_column($record['blockers'], 'evidence')));
};

$tests['current source next164 blocks duplicate broad runner snapshot'] = static function (TestRunner $t): void {
    $record = libsqlite_suite_next164_record(
        libsqlite_suite_next164_rows(),
        snapshot: "12345 ./testfixture ../libsqlite/test/testrunner.tcl all\n"
    );

    $t->same('blocked', $record['status']);
    $t->same('blocked-active-runner', $record['active_runner_status']);
    $t->same(1, $record['active_runner_count']);
    $t->contains('duplicate-broad-runner-active', implode('; ', array_column($record['blockers'], 'id')));
};

$tests['current source next164 blocks focused php admission mismatch'] = static function (TestRunner $t): void {
    $record = libsqlite_suite_next164_record(
        libsqlite_suite_next164_rows(),
        output: libsqlite_suite_next164_output(assertions: 69)
    );

    $t->same('blocked', $record['status']);
    $t->contains('focused-php-pass-delta-mismatch', implode('; ', array_column($record['blockers'], 'id')));
};

$tests['current source next164 carries dependency closure and non overlap notes'] = static function (TestRunner $t): void {
    $record = libsqlite_suite_next164_record(libsqlite_suite_next164_rows());

    $t->contains('no new support component needed', $record['dependency_closure']);
    $t->contains('batch153', $record['non_overlap_note']);
    $t->contains('release/all parity remains unclaimed', $record['next_gate']);
};

$tests['current source next164 rejects empty row list'] = static function (TestRunner $t): void {
    try {
        libsqlite_suite_next164_record([]);
        $t->fail('Expected empty next164 row list to be rejected');
    } catch (InvalidArgumentException $exception) {
        $t->contains('current-source next116 full-suite countability requires at least one row', $exception->getMessage());
    }
};

return $tests;
