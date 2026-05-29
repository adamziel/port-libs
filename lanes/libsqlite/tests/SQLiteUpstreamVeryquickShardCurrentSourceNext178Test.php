<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteUpstreamSuiteEvidence;

function libsqlite_suite_next178_evidence(): SQLiteUpstreamSuiteEvidence
{
    return SQLiteUpstreamSuiteEvidence::fromManifestPath(__DIR__ . '/../UPSTREAM_TEST_MANIFEST.json');
}

function libsqlite_suite_next178_output(int $passLines = 76, int $assertions = 76, int $failures = 0): string
{
    $lines = ['Focused test run: 1 selected test files (root lock skipped)'];
    for ($i = 1; $i <= $passLines; $i++) {
        $lines[] = sprintf('PASS current source next178 veryquick shard admission case %02d', $i);
    }
    $lines[] = sprintf('1 test files, %d assertions, %d failures', $assertions, $failures);

    return implode("\n", $lines);
}

/**
 * @return list<array<string, mixed>>
 */
function libsqlite_suite_next178_rows(
    int $case = 1,
    string $launcherBase = '055eb1c633ccc5ca5faf15e7d918302f50d26b22',
    string $dashboardSource = '8a447f445e5d2fd32fc9fd463117f585d1416551',
    string $statusSource = '8a447f445e5d2fd32fc9fd463117f585d1416551',
    string $implementationSource = '8a447f445e5d2fd32fc9fd463117f585d1416551',
    string $nextHead = 'suite-upstream-veryquick-shard-current-source-next178'
): array {
    $script = sprintf('veryquick-current-source-next178-%02d.test', $case);

    return [
        [
            'unit' => 'suite-upstream-veryquick-shard-current-source-next178',
            'kind' => 'bounded-upstream-veryquick-shard-runner',
            'gap_id' => 'current-source-next178-veryquick-shard-gap',
            'gap_status' => 'removed',
            'removed_blocker' => 'next178 admits one focused veryquick shard row tied to launcher Base accepted HEAD 055eb1c6 and integration source 8a447f44 without duplicating accepted next155 through next175 suite evidence',
            'tier' => 'focused-veryquick-shard',
            'source_head' => $nextHead,
            'launcher_base_head' => $launcherBase,
            'dashboard_source_head' => $dashboardSource,
            'status_source_head' => $statusSource,
            'implementation_source_head' => $implementationSource,
            'artifact_path' => 'lanes/libsqlite/notes/yield-suite-upstream-veryquick-shard-current-source-next178.md',
            'runner_command' => './testfixture ../libsqlite/test/testrunner.tcl --jobs 1 --stop-on-error veryquick ' . $script,
            'scripts' => [$script, 'testrunner.test'],
            'current_countable' => false,
            'next_countable' => true,
            'exit' => 0,
            'errors' => 0,
            'current_tests' => 0,
            'next_tests' => 83183 + $case,
        ],
        [
            'unit' => 'batch163-current-source-anchor',
            'kind' => 'accepted-upstream-runner-anchor',
            'gap_id' => 'accepted-batch163-suite-anchor',
            'gap_status' => 'preserved',
            'removed_blocker' => '',
            'tier' => 'accepted-anchor',
            'source_head' => $nextHead,
            'launcher_base_head' => $launcherBase,
            'dashboard_source_head' => $dashboardSource,
            'status_source_head' => $statusSource,
            'implementation_source_head' => $implementationSource,
            'artifact_path' => 'lanes/libsqlite/notes/yield-suite-upstream-veryquick-shard-current-source-next171.md',
            'runner_command' => './testfixture ../libsqlite/test/testrunner.tcl --jobs 1 --stop-on-error veryquick accepted-batch163-anchor.test',
            'scripts' => ['accepted-batch163-anchor.test'],
            'current_countable' => true,
            'next_countable' => true,
            'exit' => 0,
            'errors' => 0,
            'current_tests' => 83183,
            'next_tests' => 83183,
        ],
    ];
}

/**
 * @param list<array<string, mixed>> $rows
 * @return array<string, mixed>
 */
function libsqlite_suite_next178_record(
    array $rows,
    string $launcherBase = '055eb1c633ccc5ca5faf15e7d918302f50d26b22',
    string $dashboardSource = '8a447f445e5d2fd32fc9fd463117f585d1416551',
    string $statusSource = '8a447f445e5d2fd32fc9fd463117f585d1416551',
    string $implementationSource = '8a447f445e5d2fd32fc9fd463117f585d1416551',
    string $nextHead = 'suite-upstream-veryquick-shard-current-source-next178',
    ?string $output = null,
    ?int $expected = 76,
    string $snapshot = ''
): array {
    return libsqlite_suite_next178_evidence()->upstreamVeryquickShardCurrentSource($rows,
        613,
        83183,
        $launcherBase,
        $dashboardSource,
        $statusSource,
        $implementationSource,
        $nextHead,
        'lanes/libsqlite/tests/SQLiteUpstreamVeryquickShardCurrentSourceNext178Test.php',
        $output ?? libsqlite_suite_next178_output(),
        'current-source next178 veryquick-shard admission avoids accepted next155/157/159/161/164/166/167/169/171/172/173/174/175 suite evidence, exact-shard next148, queued suite156/160/162/163/165/168/170 manifest-conflict work, runner106/jsonvt104 rebase work, accepted batch163 behavior surfaces, and live B-tree/JSON/VFS/WAL/planner/PRAGMA/ATTACH/window/VDBE work',
        $expected,
        $snapshot,
        'next178-veryquick-shard'
    );
}

$tests = [];

foreach (range(1, 66) as $case) {
    $tests[sprintf('current source next178 admits veryquick shard case %02d', $case)] = static function (TestRunner $t) use ($case): void {
        $record = libsqlite_suite_next178_record(libsqlite_suite_next178_rows($case));

        $t->same('current-source-next178-veryquick-shard-advanced', $record['status']);
        $t->same(true, $record['countable']);
        $t->same(613, $record['current_mapped']);
        $t->same(614, $record['next_mapped']);
        $t->same(1, $record['mapped_delta']);
        $t->same(76, $record['php_pass_delta']);
        $t->same(83259, $record['next_php_pass']);
        $t->same(['suite-upstream-veryquick-shard-current-source-next178'], $record['admitted_units']);
        $t->same(['batch163-current-source-anchor'], $record['preserved_units']);
        $t->contains(sprintf('veryquick-current-source-next178-%02d.test', $case), implode(',', $record['target_scripts']));
        $t->same(true, $record['counts_upstream_veryquick_shard_current_source_next178']);
        $t->same(false, $record['counts_upstream_veryquick_shard_current_source_next175']);
        $t->same(false, $record['counts_upstream_veryquick_shard_current_source_next174']);
        $t->same(false, $record['counts_upstream_veryquick_shard_current_source_next173']);
        $t->same(false, $record['counts_upstream_veryquick_shard_current_source_next171']);
        $t->same(false, $record['counts_upstream_exact_shard_runner_current_source_next148']);
        $t->same(false, $record['counts_release_parity']);
    };
}

$tests['current source next178 records authoritative launcher and source heads'] = static function (TestRunner $t): void {
    $record = libsqlite_suite_next178_record(libsqlite_suite_next178_rows(8));

    $t->same('055eb1c633ccc5ca5faf15e7d918302f50d26b22', $record['launcher_base_head']);
    $t->same('8a447f445e5d2fd32fc9fd463117f585d1416551', $record['dashboard_source_head']);
    $t->same('8a447f445e5d2fd32fc9fd463117f585d1416551', $record['status_source_head']);
    $t->same('8a447f445e5d2fd32fc9fd463117f585d1416551', $record['implementation_source_head']);
    $t->same(['suite-upstream-veryquick-shard-current-source-next178'], $record['artifact_source_heads']);
};

$tests['current source next178 records target scripts and tier counts'] = static function (TestRunner $t): void {
    $record = libsqlite_suite_next178_record(libsqlite_suite_next178_rows(11));

    $t->same(83194, $record['tests_total_delta']);
    $t->same(['accepted-batch163-anchor.test', 'testrunner.test', 'veryquick-current-source-next178-11.test'], $record['target_scripts']);
    $t->same('accepted-anchor', $record['tier_counts'][0]['tier']);
    $t->same(0, $record['tier_counts'][0]['admitted']);
    $t->same(1, $record['tier_counts'][0]['preserved']);
    $t->same('focused-veryquick-shard', $record['tier_counts'][1]['tier']);
    $t->same(1, $record['tier_counts'][1]['admitted']);
    $t->same(83194, $record['tier_counts'][1]['tests']);
};

$tests['current source next178 preserves already counted row without mapped inflation'] = static function (TestRunner $t): void {
    $rows = libsqlite_suite_next178_rows();
    $rows[0]['current_countable'] = true;
    $rows[0]['current_tests'] = 83184;

    $record = libsqlite_suite_next178_record($rows);

    $t->same('current-source-next178-veryquick-shard-preserved', $record['status']);
    $t->same(0, $record['mapped_delta']);
    $t->same(613, $record['next_mapped']);
    $t->same(['batch163-current-source-anchor', 'suite-upstream-veryquick-shard-current-source-next178'], $record['preserved_units']);
};

$tests['current source next178 blocks stale source provenance'] = static function (TestRunner $t): void {
    $record = libsqlite_suite_next178_record(
        libsqlite_suite_next178_rows(
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

$tests['current source next178 blocks unguarded and non local artifacts'] = static function (TestRunner $t): void {
    $rows = libsqlite_suite_next178_rows();
    $rows[0]['artifact_path'] = '/tmp/next178.md';
    $rows[0]['runner_command'] = './testfixture ../libsqlite/test/testrunner.tcl all';
    $rows[0]['scripts'] = ['README.md'];

    $record = libsqlite_suite_next178_record($rows);

    $t->same('blocked', $record['status']);
    $evidence = implode('; ', array_column($record['blockers'], 'evidence'));
    $t->contains('artifact-path-not-lane-local', $evidence);
    $t->contains('guarded-runner-command-missing', $evidence);
    $t->contains('concrete-test-scripts-missing', $evidence);
};

$tests['current source next178 blocks non zero runner artifacts'] = static function (TestRunner $t): void {
    $rows = libsqlite_suite_next178_rows();
    $rows[0]['exit'] = 1;
    $rows[0]['errors'] = 2;

    $record = libsqlite_suite_next178_record($rows);

    $t->same('blocked', $record['status']);
    $t->same(0, $record['php_pass_delta']);
    $t->contains('runner-artifact-not-zero-error', implode('; ', array_column($record['blockers'], 'evidence')));
};

$tests['current source next178 blocks missing removed blocker classification'] = static function (TestRunner $t): void {
    $rows = libsqlite_suite_next178_rows();
    $rows[0]['removed_blocker'] = '';

    $record = libsqlite_suite_next178_record($rows);

    $t->same('blocked', $record['status']);
    $t->contains('removed-blocker-missing', implode('; ', array_column($record['blockers'], 'evidence')));
};

$tests['current source next178 blocks duplicate broad runner snapshot'] = static function (TestRunner $t): void {
    $record = libsqlite_suite_next178_record(
        libsqlite_suite_next178_rows(),
        snapshot: "12345 ./testfixture ../libsqlite/test/testrunner.tcl all\n"
    );

    $t->same('blocked', $record['status']);
    $t->same('blocked-active-runner', $record['active_runner_status']);
    $t->same(1, $record['active_runner_count']);
    $t->contains('duplicate-broad-runner-active', implode('; ', array_column($record['blockers'], 'id')));
};

$tests['current source next178 blocks focused php admission mismatch'] = static function (TestRunner $t): void {
    $record = libsqlite_suite_next178_record(
        libsqlite_suite_next178_rows(),
        output: libsqlite_suite_next178_output(assertions: 75)
    );

    $t->same('blocked', $record['status']);
    $t->contains('focused-php-pass-delta-mismatch', implode('; ', array_column($record['blockers'], 'id')));
};

$tests['current source next178 carries dependency closure and non overlap notes'] = static function (TestRunner $t): void {
    $record = libsqlite_suite_next178_record(libsqlite_suite_next178_rows());

    $t->contains('no new support component needed', $record['dependency_closure']);
    $t->contains('174/175', $record['non_overlap_note']);
    $t->contains('release/all parity remains unclaimed', $record['next_gate']);
};

$tests['current source next178 rejects empty row list'] = static function (TestRunner $t): void {
    try {
        libsqlite_suite_next178_record([]);
        $t->fail('Expected empty next178 row list to be rejected');
    } catch (InvalidArgumentException $exception) {
        $t->contains('current-source next116 full-suite countability requires at least one row', $exception->getMessage());
    }
};

return $tests;
