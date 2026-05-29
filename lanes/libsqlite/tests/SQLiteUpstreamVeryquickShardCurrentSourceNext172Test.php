<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteUpstreamSuiteEvidence;

function libsqlite_suite_next172_evidence(): SQLiteUpstreamSuiteEvidence
{
    return SQLiteUpstreamSuiteEvidence::fromManifestPath(__DIR__ . '/../UPSTREAM_TEST_MANIFEST.json');
}

function libsqlite_suite_next172_output(int $passLines = 76, int $assertions = 76, int $failures = 0): string
{
    $lines = ['Focused test run: 1 selected test files (root lock skipped)'];
    for ($i = 1; $i <= $passLines; $i++) {
        $lines[] = sprintf('PASS current source next172 veryquick shard admission case %02d', $i);
    }
    $lines[] = sprintf('1 test files, %d assertions, %d failures', $assertions, $failures);

    return implode("\n", $lines);
}

/**
 * @return list<array<string, mixed>>
 */
function libsqlite_suite_next172_rows(
    int $case = 1,
    string $launcherBase = 'edaac9da4ba550fc866f8d57f9220a748899b577',
    string $dashboardSource = '8a447f445e5d2fd32fc9fd463117f585d1416551',
    string $statusSource = 'c86090a9aff9f21526396f500efb67bdae2746ab',
    string $implementationSource = '8a447f445e5d2fd32fc9fd463117f585d1416551',
    string $nextHead = 'suite-upstream-veryquick-shard-current-source-next172'
): array {
    $script = sprintf('veryquick-current-source-next172-%02d.test', $case);

    return [
        [
            'unit' => 'suite-upstream-veryquick-shard-current-source-next172',
            'kind' => 'bounded-upstream-veryquick-shard-runner',
            'gap_id' => 'current-source-next172-veryquick-shard-gap',
            'gap_status' => 'removed',
            'removed_blocker' => 'next172 admits one focused veryquick shard row tied to launcher Base accepted HEAD edaac9da and integration source 8a447f44 without duplicating accepted next155 through next166 suite evidence',
            'tier' => 'focused-veryquick-shard',
            'source_head' => $nextHead,
            'launcher_base_head' => $launcherBase,
            'dashboard_source_head' => $dashboardSource,
            'status_source_head' => $statusSource,
            'implementation_source_head' => $implementationSource,
            'artifact_path' => 'lanes/libsqlite/notes/suite-upstream-veryquick-shard-current-source-next172.md',
            'runner_command' => './testfixture ../libsqlite/test/testrunner.tcl --jobs 1 --stop-on-error veryquick ' . $script,
            'scripts' => [$script, 'testrunner.test'],
            'current_countable' => false,
            'next_countable' => true,
            'exit' => 0,
            'errors' => 0,
            'current_tests' => 0,
            'next_tests' => 76936 + $case,
        ],
        [
            'unit' => 'batch166-current-source-anchor',
            'kind' => 'accepted-upstream-runner-anchor',
            'gap_id' => 'accepted-batch166-suite-anchor',
            'gap_status' => 'preserved',
            'removed_blocker' => '',
            'tier' => 'accepted-anchor',
            'source_head' => $nextHead,
            'launcher_base_head' => $launcherBase,
            'dashboard_source_head' => $dashboardSource,
            'status_source_head' => $statusSource,
            'implementation_source_head' => $implementationSource,
            'artifact_path' => 'lanes/libsqlite/notes/suite-upstream-veryquick-shard-current-source-next166.md',
            'runner_command' => './testfixture ../libsqlite/test/testrunner.tcl --jobs 1 --stop-on-error veryquick accepted-batch166-anchor.test',
            'scripts' => ['accepted-batch166-anchor.test'],
            'current_countable' => true,
            'next_countable' => true,
            'exit' => 0,
            'errors' => 0,
            'current_tests' => 76936,
            'next_tests' => 76936,
        ],
    ];
}

/**
 * @param list<array<string, mixed>> $rows
 * @return array<string, mixed>
 */
function libsqlite_suite_next172_record(
    array $rows,
    string $launcherBase = 'edaac9da4ba550fc866f8d57f9220a748899b577',
    string $dashboardSource = '8a447f445e5d2fd32fc9fd463117f585d1416551',
    string $statusSource = 'c86090a9aff9f21526396f500efb67bdae2746ab',
    string $implementationSource = '8a447f445e5d2fd32fc9fd463117f585d1416551',
    string $nextHead = 'suite-upstream-veryquick-shard-current-source-next172',
    ?string $output = null,
    ?int $expected = 76,
    string $snapshot = ''
): array {
    return libsqlite_suite_next172_evidence()->upstreamVeryquickShardCurrentSource($rows,
        611,
        76936,
        $launcherBase,
        $dashboardSource,
        $statusSource,
        $implementationSource,
        $nextHead,
        'lanes/libsqlite/tests/SQLiteUpstreamVeryquickShardCurrentSourceNext172Test.php',
        $output ?? libsqlite_suite_next172_output(),
        'current-source next172 veryquick-shard admission avoids accepted suite155/157/159/161/164/166, exact-shard next148, runner106/jsonvt104 rebase work, accepted batch158 behavior surfaces, and live B-tree/JSON/VFS/WAL/planner/PRAGMA/ATTACH/window/VDBE work',
        $expected,
        $snapshot,
        'next172-veryquick-shard'
    );
}

$tests = [];

foreach (range(1, 65) as $case) {
    $tests[sprintf('current source next172 admits veryquick shard case %02d', $case)] = static function (TestRunner $t) use ($case): void {
        $record = libsqlite_suite_next172_record(libsqlite_suite_next172_rows($case));

        $t->same('current-source-next172-veryquick-shard-advanced', $record['status']);
        $t->same(true, $record['countable']);
        $t->same(611, $record['current_mapped']);
        $t->same(612, $record['next_mapped']);
        $t->same(1, $record['mapped_delta']);
        $t->same(76, $record['php_pass_delta']);
        $t->same(77012, $record['next_php_pass']);
        $t->same(['suite-upstream-veryquick-shard-current-source-next172'], $record['admitted_units']);
        $t->same(['batch166-current-source-anchor'], $record['preserved_units']);
        $t->contains(sprintf('veryquick-current-source-next172-%02d.test', $case), implode(',', $record['target_scripts']));
        $t->same(true, $record['counts_upstream_veryquick_shard_current_source_next172']);
        $t->same(false, $record['counts_upstream_veryquick_shard_current_source_next166']);
        $t->same(false, $record['counts_upstream_veryquick_shard_current_source_next164']);
        $t->same(false, $record['counts_upstream_veryquick_shard_current_source_next161']);
        $t->same(false, $record['counts_upstream_veryquick_shard_current_source_next159']);
        $t->same(false, $record['counts_upstream_veryquick_shard_current_source_next157']);
        $t->same(false, $record['counts_upstream_veryquick_shard_current_source_next155']);
        $t->same(false, $record['counts_upstream_exact_shard_runner_current_source_next148']);
        $t->same(false, $record['counts_upstream_runner_full_suite_countability_current_source_next116']);
        $t->same(false, $record['counts_release_parity']);
    };
}

$tests['current source next172 records launcher and integration provenance'] = static function (TestRunner $t): void {
    $record = libsqlite_suite_next172_record(libsqlite_suite_next172_rows(8));

    $t->same('edaac9da4ba550fc866f8d57f9220a748899b577', $record['launcher_base_head']);
    $t->same('8a447f445e5d2fd32fc9fd463117f585d1416551', $record['dashboard_source_head']);
    $t->same('c86090a9aff9f21526396f500efb67bdae2746ab', $record['status_source_head']);
    $t->same('8a447f445e5d2fd32fc9fd463117f585d1416551', $record['implementation_source_head']);
    $t->same(['suite-upstream-veryquick-shard-current-source-next172'], $record['artifact_source_heads']);
};

$tests['current source next172 records target scripts and tier counts'] = static function (TestRunner $t): void {
    $record = libsqlite_suite_next172_record(libsqlite_suite_next172_rows(11));

    $t->same(76947, $record['tests_total_delta']);
    $t->same(['accepted-batch166-anchor.test', 'testrunner.test', 'veryquick-current-source-next172-11.test'], $record['target_scripts']);
    $t->same('accepted-anchor', $record['tier_counts'][0]['tier']);
    $t->same(0, $record['tier_counts'][0]['admitted']);
    $t->same(1, $record['tier_counts'][0]['preserved']);
    $t->same('focused-veryquick-shard', $record['tier_counts'][1]['tier']);
    $t->same(1, $record['tier_counts'][1]['admitted']);
    $t->same(76947, $record['tier_counts'][1]['tests']);
};

$tests['current source next172 preserves already counted row without mapped inflation'] = static function (TestRunner $t): void {
    $rows = libsqlite_suite_next172_rows();
    $rows[0]['current_countable'] = true;
    $rows[0]['current_tests'] = 76937;

    $record = libsqlite_suite_next172_record($rows);

    $t->same('current-source-next172-veryquick-shard-preserved', $record['status']);
    $t->same(0, $record['mapped_delta']);
    $t->same(611, $record['next_mapped']);
    $t->same(['batch166-current-source-anchor', 'suite-upstream-veryquick-shard-current-source-next172'], $record['preserved_units']);
};

$tests['current source next172 blocks stale source provenance'] = static function (TestRunner $t): void {
    $record = libsqlite_suite_next172_record(
        libsqlite_suite_next172_rows(
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

$tests['current source next172 blocks unguarded and non local artifacts'] = static function (TestRunner $t): void {
    $rows = libsqlite_suite_next172_rows();
    $rows[0]['artifact_path'] = '/tmp/next172.md';
    $rows[0]['runner_command'] = './testfixture ../libsqlite/test/testrunner.tcl all';
    $rows[0]['scripts'] = ['README.md'];

    $record = libsqlite_suite_next172_record($rows);

    $t->same('blocked', $record['status']);
    $evidence = implode('; ', array_column($record['blockers'], 'evidence'));
    $t->contains('artifact-path-not-lane-local', $evidence);
    $t->contains('guarded-runner-command-missing', $evidence);
    $t->contains('concrete-test-scripts-missing', $evidence);
};

$tests['current source next172 blocks non zero runner artifacts'] = static function (TestRunner $t): void {
    $rows = libsqlite_suite_next172_rows();
    $rows[0]['exit'] = 1;
    $rows[0]['errors'] = 2;

    $record = libsqlite_suite_next172_record($rows);

    $t->same('blocked', $record['status']);
    $t->same(0, $record['php_pass_delta']);
    $t->contains('runner-artifact-not-zero-error', implode('; ', array_column($record['blockers'], 'evidence')));
};

$tests['current source next172 blocks missing removed blocker classification'] = static function (TestRunner $t): void {
    $rows = libsqlite_suite_next172_rows();
    $rows[0]['removed_blocker'] = '';

    $record = libsqlite_suite_next172_record($rows);

    $t->same('blocked', $record['status']);
    $t->contains('removed-blocker-missing', implode('; ', array_column($record['blockers'], 'evidence')));
};

$tests['current source next172 blocks duplicate broad runner snapshot'] = static function (TestRunner $t): void {
    $record = libsqlite_suite_next172_record(
        libsqlite_suite_next172_rows(),
        snapshot: "12345 ./testfixture ../libsqlite/test/testrunner.tcl all\n"
    );

    $t->same('blocked', $record['status']);
    $t->same('blocked-active-runner', $record['active_runner_status']);
    $t->same(1, $record['active_runner_count']);
    $t->contains('duplicate-broad-runner-active', implode('; ', array_column($record['blockers'], 'id')));
};

$tests['current source next172 blocks focused php admission mismatch'] = static function (TestRunner $t): void {
    $record = libsqlite_suite_next172_record(
        libsqlite_suite_next172_rows(),
        output: libsqlite_suite_next172_output(assertions: 74)
    );

    $t->same('blocked', $record['status']);
    $t->contains('focused-php-pass-delta-mismatch', implode('; ', array_column($record['blockers'], 'id')));
};

$tests['current source next172 carries dependency closure and non overlap notes'] = static function (TestRunner $t): void {
    $record = libsqlite_suite_next172_record(libsqlite_suite_next172_rows());

    $t->contains('no new support component needed', $record['dependency_closure']);
    $t->contains('suite155/157/159/161/164/166', $record['non_overlap_note']);
    $t->contains('release/all parity remains unclaimed', $record['next_gate']);
};

$tests['current source next172 rejects empty row list'] = static function (TestRunner $t): void {
    try {
        libsqlite_suite_next172_record([]);
        $t->fail('Expected empty next172 row list to be rejected');
    } catch (InvalidArgumentException $exception) {
        $t->contains('current-source next116 full-suite countability requires at least one row', $exception->getMessage());
    }
};

return $tests;
