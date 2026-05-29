<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteUpstreamSuiteEvidence;

function libsqlite_suite_next156_evidence(): SQLiteUpstreamSuiteEvidence
{
    return SQLiteUpstreamSuiteEvidence::fromManifestPath(__DIR__ . '/../UPSTREAM_TEST_MANIFEST.json');
}

function libsqlite_suite_next156_output(int $passLines = 69, int $assertions = 69, int $failures = 0): string
{
    $lines = ['Focused test run: 1 selected test files (root lock skipped)'];
    for ($i = 1; $i <= $passLines; $i++) {
        $lines[] = sprintf('PASS current source next156 veryquick shard admission case %02d', $i);
    }
    $lines[] = sprintf('1 test files, %d assertions, %d failures', $assertions, $failures);

    return implode("\n", $lines);
}

/**
 * @return list<array<string, mixed>>
 */
function libsqlite_suite_next156_rows(
    int $case = 1,
    string $launcherBase = '10944daab99f126239eb5c4476c32cdb4c42a734',
    string $dashboardSource = '8a447f445e5d2fd32fc9fd463117f585d1416551',
    string $statusSource = '8a447f445e5d2fd32fc9fd463117f585d1416551',
    string $implementationSource = '8a447f445e5d2fd32fc9fd463117f585d1416551',
    string $nextHead = 'suite-upstream-veryquick-shard-current-source-next156'
): array {
    $script = sprintf('veryquick-current-source-next156-%02d.test', $case);

    return [
        [
            'unit' => 'suite-upstream-veryquick-shard-current-source-next156',
            'kind' => 'bounded-upstream-veryquick-shard-runner',
            'gap_id' => 'current-source-next156-veryquick-shard-gap',
            'gap_status' => 'removed',
            'removed_blocker' => 'next156 admits one focused veryquick shard row tied to launcher Base accepted HEAD 10944daa and integration source 8a447f44',
            'tier' => 'focused-veryquick-shard',
            'source_head' => $nextHead,
            'launcher_base_head' => $launcherBase,
            'dashboard_source_head' => $dashboardSource,
            'status_source_head' => $statusSource,
            'implementation_source_head' => $implementationSource,
            'artifact_path' => 'lanes/libsqlite/notes/suite-upstream-veryquick-shard-current-source-next156.md',
            'runner_command' => './testfixture ../libsqlite/test/testrunner.tcl --jobs 1 --stop-on-error veryquick ' . $script,
            'scripts' => [$script, 'testrunner.test'],
            'current_countable' => false,
            'next_countable' => true,
            'exit' => 0,
            'errors' => 0,
            'current_tests' => 0,
            'next_tests' => 71256 + $case,
        ],
        [
            'unit' => 'batch155-current-source-anchor',
            'kind' => 'accepted-upstream-runner-anchor',
            'gap_id' => 'accepted-batch155-suite-anchor',
            'gap_status' => 'preserved',
            'removed_blocker' => '',
            'tier' => 'accepted-anchor',
            'source_head' => $nextHead,
            'launcher_base_head' => $launcherBase,
            'dashboard_source_head' => $dashboardSource,
            'status_source_head' => $statusSource,
            'implementation_source_head' => $implementationSource,
            'artifact_path' => 'lanes/libsqlite/notes/suite-upstream-veryquick-shard-current-source-next155.md',
            'runner_command' => './testfixture ../libsqlite/test/testrunner.tcl --jobs 1 --stop-on-error veryquick accepted-batch155-anchor.test',
            'scripts' => ['accepted-batch155-anchor.test'],
            'current_countable' => true,
            'next_countable' => true,
            'exit' => 0,
            'errors' => 0,
            'current_tests' => 71256,
            'next_tests' => 71256,
        ],
    ];
}

/**
 * @param list<array<string, mixed>> $rows
 * @return array<string, mixed>
 */
function libsqlite_suite_next156_record(
    array $rows,
    string $launcherBase = '10944daab99f126239eb5c4476c32cdb4c42a734',
    string $dashboardSource = '8a447f445e5d2fd32fc9fd463117f585d1416551',
    string $statusSource = '8a447f445e5d2fd32fc9fd463117f585d1416551',
    string $implementationSource = '8a447f445e5d2fd32fc9fd463117f585d1416551',
    string $nextHead = 'suite-upstream-veryquick-shard-current-source-next156',
    ?string $output = null,
    ?int $expected = 69,
    string $snapshot = ''
): array {
    return libsqlite_suite_next156_evidence()->upstreamVeryquickShardCurrentSourceNext156(
        $rows,
        607,
        71256,
        $launcherBase,
        $dashboardSource,
        $statusSource,
        $implementationSource,
        $nextHead,
        'lanes/libsqlite/tests/SQLiteUpstreamVeryquickShardCurrentSourceNext156Test.php',
        $output ?? libsqlite_suite_next156_output(),
        'current-source next156 veryquick-shard admission avoids accepted batch155 veryquick evidence, suite155, exact-shard next148, queued runner106/jsonvt104 rebase work, and accepted B-tree/JSON/VFS/WAL/planner/PRAGMA/ATTACH/window/VDBE behavior surfaces',
        $expected,
        $snapshot
    );
}

$tests = [];

foreach (range(1, 58) as $case) {
    $tests[sprintf('current source next156 admits veryquick shard case %02d', $case)] = static function (TestRunner $t) use ($case): void {
        $record = libsqlite_suite_next156_record(libsqlite_suite_next156_rows($case));

        $t->same('current-source-next156-veryquick-shard-advanced', $record['status']);
        $t->same(true, $record['countable']);
        $t->same(607, $record['current_mapped']);
        $t->same(608, $record['next_mapped']);
        $t->same(1, $record['mapped_delta']);
        $t->same(69, $record['php_pass_delta']);
        $t->same(71325, $record['next_php_pass']);
        $t->same(['suite-upstream-veryquick-shard-current-source-next156'], $record['admitted_units']);
        $t->same(['batch155-current-source-anchor'], $record['preserved_units']);
        $t->contains(sprintf('veryquick-current-source-next156-%02d.test', $case), implode(',', $record['target_scripts']));
        $t->same(true, $record['counts_upstream_veryquick_shard_current_source_next156']);
        $t->same(false, $record['counts_upstream_veryquick_shard_current_source_next159']);
        $t->same(false, $record['counts_upstream_veryquick_shard_current_source_next157']);
        $t->same(false, $record['counts_upstream_veryquick_shard_current_source_next155']);
        $t->same(false, $record['counts_upstream_exact_shard_runner_current_source_next148']);
        $t->same(false, $record['counts_upstream_runner_full_suite_countability_current_source_next116']);
        $t->same(false, $record['counts_release_parity']);
    };
}

$tests['current source next156 records authoritative launcher and source heads'] = static function (TestRunner $t): void {
    $record = libsqlite_suite_next156_record(libsqlite_suite_next156_rows(8));

    $t->same('10944daab99f126239eb5c4476c32cdb4c42a734', $record['launcher_base_head']);
    $t->same('8a447f445e5d2fd32fc9fd463117f585d1416551', $record['dashboard_source_head']);
    $t->same('8a447f445e5d2fd32fc9fd463117f585d1416551', $record['status_source_head']);
    $t->same('8a447f445e5d2fd32fc9fd463117f585d1416551', $record['implementation_source_head']);
    $t->same(['suite-upstream-veryquick-shard-current-source-next156'], $record['artifact_source_heads']);
};

$tests['current source next156 records target scripts and tier counts'] = static function (TestRunner $t): void {
    $record = libsqlite_suite_next156_record(libsqlite_suite_next156_rows(11));

    $t->same(71267, $record['tests_total_delta']);
    $t->same(['accepted-batch155-anchor.test', 'testrunner.test', 'veryquick-current-source-next156-11.test'], $record['target_scripts']);
    $t->same('accepted-anchor', $record['tier_counts'][0]['tier']);
    $t->same(0, $record['tier_counts'][0]['admitted']);
    $t->same(1, $record['tier_counts'][0]['preserved']);
    $t->same('focused-veryquick-shard', $record['tier_counts'][1]['tier']);
    $t->same(1, $record['tier_counts'][1]['admitted']);
    $t->same(71267, $record['tier_counts'][1]['tests']);
};

$tests['current source next156 preserves already counted row without mapped inflation'] = static function (TestRunner $t): void {
    $rows = libsqlite_suite_next156_rows();
    $rows[0]['current_countable'] = true;
    $rows[0]['current_tests'] = 72235;

    $record = libsqlite_suite_next156_record($rows);

    $t->same('current-source-next156-veryquick-shard-preserved', $record['status']);
    $t->same(0, $record['mapped_delta']);
    $t->same(607, $record['next_mapped']);
    $t->same(['batch155-current-source-anchor', 'suite-upstream-veryquick-shard-current-source-next156'], $record['preserved_units']);
};

$tests['current source next156 blocks stale source provenance'] = static function (TestRunner $t): void {
    $record = libsqlite_suite_next156_record(
        libsqlite_suite_next156_rows(
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

$tests['current source next156 blocks unguarded and non local artifacts'] = static function (TestRunner $t): void {
    $rows = libsqlite_suite_next156_rows();
    $rows[0]['artifact_path'] = '/tmp/next156.md';
    $rows[0]['runner_command'] = './testfixture ../libsqlite/test/testrunner.tcl all';
    $rows[0]['scripts'] = ['README.md'];

    $record = libsqlite_suite_next156_record($rows);

    $t->same('blocked', $record['status']);
    $evidence = implode('; ', array_column($record['blockers'], 'evidence'));
    $t->contains('artifact-path-not-lane-local', $evidence);
    $t->contains('guarded-runner-command-missing', $evidence);
    $t->contains('concrete-test-scripts-missing', $evidence);
};

$tests['current source next156 blocks non zero runner artifacts'] = static function (TestRunner $t): void {
    $rows = libsqlite_suite_next156_rows();
    $rows[0]['exit'] = 1;
    $rows[0]['errors'] = 2;

    $record = libsqlite_suite_next156_record($rows);

    $t->same('blocked', $record['status']);
    $t->same(0, $record['php_pass_delta']);
    $t->contains('runner-artifact-not-zero-error', implode('; ', array_column($record['blockers'], 'evidence')));
};

$tests['current source next156 blocks missing removed blocker classification'] = static function (TestRunner $t): void {
    $rows = libsqlite_suite_next156_rows();
    $rows[0]['removed_blocker'] = '';

    $record = libsqlite_suite_next156_record($rows);

    $t->same('blocked', $record['status']);
    $t->contains('removed-blocker-missing', implode('; ', array_column($record['blockers'], 'evidence')));
};

$tests['current source next156 blocks duplicate broad runner snapshot'] = static function (TestRunner $t): void {
    $record = libsqlite_suite_next156_record(
        libsqlite_suite_next156_rows(),
        snapshot: "12345 ./testfixture ../libsqlite/test/testrunner.tcl all\n"
    );

    $t->same('blocked', $record['status']);
    $t->same('blocked-active-runner', $record['active_runner_status']);
    $t->same(1, $record['active_runner_count']);
    $t->contains('duplicate-broad-runner-active', implode('; ', array_column($record['blockers'], 'id')));
};

$tests['current source next156 blocks focused php admission mismatch'] = static function (TestRunner $t): void {
    $record = libsqlite_suite_next156_record(
        libsqlite_suite_next156_rows(),
        output: libsqlite_suite_next156_output(assertions: 67)
    );

    $t->same('blocked', $record['status']);
    $t->contains('focused-php-pass-delta-mismatch', implode('; ', array_column($record['blockers'], 'id')));
};

$tests['current source next156 carries dependency closure and non overlap notes'] = static function (TestRunner $t): void {
    $record = libsqlite_suite_next156_record(libsqlite_suite_next156_rows());

    $t->contains('no new support component needed', $record['dependency_closure']);
    $t->contains('batch155', $record['non_overlap_note']);
    $t->contains('release/all parity remains unclaimed', $record['next_gate']);
};

$tests['current source next156 rejects empty row list'] = static function (TestRunner $t): void {
    try {
        libsqlite_suite_next156_record([]);
        $t->fail('Expected empty next156 row list to be rejected');
    } catch (InvalidArgumentException $exception) {
        $t->contains('current-source next116 full-suite countability requires at least one row', $exception->getMessage());
    }
};

return $tests;
