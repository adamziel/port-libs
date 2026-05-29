<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteUpstreamSuiteEvidence;

function libsqlite_suite_next450_evidence(): SQLiteUpstreamSuiteEvidence
{
    return SQLiteUpstreamSuiteEvidence::fromManifestPath(__DIR__ . '/../UPSTREAM_TEST_MANIFEST.json');
}

function libsqlite_suite_next450_output(int $passLines = 96, int $assertions = 96, int $failures = 0): string
{
    $lines = ['Focused test run: 1 selected test files (root lock skipped)'];
    for ($i = 1; $i <= $passLines; $i++) {
        $lines[] = sprintf('PASS current source next450 veryquick shard admission case %02d', $i);
    }
    $lines[] = sprintf('1 test files, %d assertions, %d failures', $assertions, $failures);

    return implode("\n", $lines);
}

/**
 * @return list<array<string, mixed>>
 */
function libsqlite_suite_next450_rows(int $case = 1): array
{
    $script = sprintf('veryquick-current-source-next450-%02d.test', $case);

    return [
        [
            'unit' => 'suite-upstream-veryquick-shard-current-source-next450',
            'kind' => 'bounded-upstream-veryquick-shard-runner',
            'gap_id' => 'current-source-next450-veryquick-shard-gap',
            'gap_status' => 'removed',
            'removed_blocker' => 'next450 admits one focused veryquick shard row tied to launcher Base accepted HEAD fca16e3d and accepted batch228 source f276db2c without duplicating accepted next343/next379/next382-through-next398 suite evidence',
            'tier' => 'focused-veryquick-shard',
            'source_head' => 'suite-upstream-veryquick-shard-current-source-next450',
            'launcher_base_head' => 'fca16e3dd1812e6fcb6dc54c4980a5fb898b24ec',
            'dashboard_source_head' => 'f276db2cadbe640018aa18d11a7721e7187e05dc',
            'status_source_head' => 'f276db2cadbe640018aa18d11a7721e7187e05dc',
            'implementation_source_head' => 'f276db2cadbe640018aa18d11a7721e7187e05dc',
            'artifact_path' => 'lanes/libsqlite/notes/yield-suite-upstream-veryquick-shard-current-source-next450.md',
            'runner_command' => './testfixture ../libsqlite/test/testrunner.tcl --jobs 1 --stop-on-error veryquick ' . $script,
            'scripts' => [$script, 'testrunner.test'],
            'current_countable' => false,
            'next_countable' => true,
            'exit' => 0,
            'errors' => 0,
            'current_tests' => 0,
            'next_tests' => 151655 + $case,
        ],
        [
            'unit' => 'batch228-current-source-anchor',
            'kind' => 'accepted-upstream-runner-anchor',
            'gap_id' => 'accepted-batch228-suite-anchor',
            'gap_status' => 'preserved',
            'removed_blocker' => '',
            'tier' => 'accepted-anchor',
            'source_head' => 'suite-upstream-veryquick-shard-current-source-next450',
            'launcher_base_head' => 'fca16e3dd1812e6fcb6dc54c4980a5fb898b24ec',
            'dashboard_source_head' => 'f276db2cadbe640018aa18d11a7721e7187e05dc',
            'status_source_head' => 'f276db2cadbe640018aa18d11a7721e7187e05dc',
            'implementation_source_head' => 'f276db2cadbe640018aa18d11a7721e7187e05dc',
            'artifact_path' => 'lanes/libsqlite/notes/yield-suite-upstream-veryquick-shard-current-source-next398.md',
            'runner_command' => './testfixture ../libsqlite/test/testrunner.tcl --jobs 1 --stop-on-error veryquick accepted-batch228-anchor.test',
            'scripts' => ['accepted-batch228-anchor.test'],
            'current_countable' => true,
            'next_countable' => true,
            'exit' => 0,
            'errors' => 0,
            'current_tests' => 151655,
            'next_tests' => 151655,
        ],
    ];
}

/**
 * @param list<array<string, mixed>> $rows
 * @return array<string, mixed>
 */
function libsqlite_suite_next450_record(array $rows, ?string $output = null, string $snapshot = ''): array
{
    return libsqlite_suite_next450_evidence()->upstreamVeryquickShardCurrentSourceShard(
        450,
        $rows,
        801,
        151655,
        'fca16e3dd1812e6fcb6dc54c4980a5fb898b24ec',
        'f276db2cadbe640018aa18d11a7721e7187e05dc',
        'f276db2cadbe640018aa18d11a7721e7187e05dc',
        'f276db2cadbe640018aa18d11a7721e7187e05dc',
        'suite-upstream-veryquick-shard-current-source-next450',
        'lanes/libsqlite/tests/SQLiteUpstreamVeryquickShardCurrentSourceNext450Test.php',
        $output ?? libsqlite_suite_next450_output(),
        'current-source next450 veryquick-shard admission avoids accepted next343/next379/next382-through-next398 suite evidence, queued runner106/jsonvt104 rebase work, accepted batch228 behavior surfaces, and live B-tree/JSON/VFS/WAL/planner/PRAGMA/ATTACH/window/VDBE work',
        96,
        $snapshot
    );
}

$tests = [];

foreach (range(1, 40) as $case) {
    $tests[sprintf('current source next450 admits veryquick shard case %02d', $case)] = static function (TestRunner $t) use ($case): void {
        $record = libsqlite_suite_next450_record(libsqlite_suite_next450_rows($case));

        $t->same('current-source-next450-veryquick-shard-advanced', $record['status']);
        $t->same(true, $record['countable']);
        $t->same(801, $record['current_mapped']);
        $t->same(802, $record['next_mapped']);
        $t->same(1, $record['mapped_delta']);
        $t->same(96, $record['php_pass_delta']);
        $t->same(151751, $record['next_php_pass']);
        $t->same(['suite-upstream-veryquick-shard-current-source-next450'], $record['admitted_units']);
        $t->same(['batch228-current-source-anchor'], $record['preserved_units']);
        $t->contains(sprintf('veryquick-current-source-next450-%02d.test', $case), implode(',', $record['target_scripts']));
        $t->same(true, $record['counts_upstream_veryquick_shard_current_source_next450']);
        $t->same(false, $record['counts_upstream_veryquick_shard_current_source_next398']);
        $t->same(false, $record['counts_upstream_runner_full_suite_countability_current_source_next116']);
        $t->same(false, $record['counts_release_parity']);
    };
}

$tests['current source next450 records authoritative source heads'] = static function (TestRunner $t): void {
    $record = libsqlite_suite_next450_record(libsqlite_suite_next450_rows(8));

    $t->same('fca16e3dd1812e6fcb6dc54c4980a5fb898b24ec', $record['launcher_base_head']);
    $t->same('f276db2cadbe640018aa18d11a7721e7187e05dc', $record['dashboard_source_head']);
    $t->same('f276db2cadbe640018aa18d11a7721e7187e05dc', $record['status_source_head']);
    $t->same('f276db2cadbe640018aa18d11a7721e7187e05dc', $record['implementation_source_head']);
    $t->same(['suite-upstream-veryquick-shard-current-source-next450'], $record['artifact_source_heads']);
};

$tests['current source next450 blocks stale source provenance'] = static function (TestRunner $t): void {
    $rows = libsqlite_suite_next450_rows();
    $rows[0]['launcher_base_head'] = '0000000000000000000000000000000000000000';

    $record = libsqlite_suite_next450_record($rows);

    $t->same('blocked', $record['status']);
    $t->same(0, $record['mapped_delta']);
    $t->contains('launcher-base-head-mismatch', implode('; ', array_column($record['blockers'], 'evidence')));
};

$tests['current source next450 blocks unguarded broad runner artifacts'] = static function (TestRunner $t): void {
    $rows = libsqlite_suite_next450_rows();
    $rows[0]['runner_command'] = './testfixture ../libsqlite/test/testrunner.tcl all';
    $rows[0]['scripts'] = ['README.md'];

    $record = libsqlite_suite_next450_record($rows);

    $t->same('blocked', $record['status']);
    $evidence = implode('; ', array_column($record['blockers'], 'evidence'));
    $t->contains('guarded-runner-command-missing', $evidence);
    $t->contains('concrete-test-scripts-missing', $evidence);
};

$tests['current source next450 blocks non zero runner artifacts'] = static function (TestRunner $t): void {
    $rows = libsqlite_suite_next450_rows();
    $rows[0]['exit'] = 1;
    $rows[0]['errors'] = 1;

    $record = libsqlite_suite_next450_record($rows);

    $t->same('blocked', $record['status']);
    $t->same(0, $record['php_pass_delta']);
    $t->contains('runner-artifact-not-zero-error', implode('; ', array_column($record['blockers'], 'evidence')));
};

$tests['current source next450 blocks focused php admission mismatch'] = static function (TestRunner $t): void {
    $record = libsqlite_suite_next450_record(
        libsqlite_suite_next450_rows(),
        libsqlite_suite_next450_output(assertions: 95)
    );

    $t->same('blocked', $record['status']);
    $t->contains('focused-php-pass-delta-mismatch', implode('; ', array_column($record['blockers'], 'id')));
};

$tests['current source next450 preserves already counted row without mapped inflation'] = static function (TestRunner $t): void {
    $rows = libsqlite_suite_next450_rows();
    $rows[0]['current_countable'] = true;
    $rows[0]['current_tests'] = 151656;

    $record = libsqlite_suite_next450_record($rows);

    $t->same('current-source-next450-veryquick-shard-preserved', $record['status']);
    $t->same(0, $record['mapped_delta']);
    $t->same(801, $record['next_mapped']);
};

$tests['current source next450 blocks duplicate broad runner snapshot'] = static function (TestRunner $t): void {
    $record = libsqlite_suite_next450_record(
        libsqlite_suite_next450_rows(),
        snapshot: "12345 ./testfixture ../libsqlite/test/testrunner.tcl release\n"
    );

    $t->same('blocked', $record['status']);
    $t->same('blocked-active-runner', $record['active_runner_status']);
    $t->contains('duplicate-broad-runner-active', implode('; ', array_column($record['blockers'], 'id')));
};

$tests['current source next450 carries dependency closure and non overlap notes'] = static function (TestRunner $t): void {
    $record = libsqlite_suite_next450_record(libsqlite_suite_next450_rows());

    $t->contains('no new support component needed', $record['dependency_closure']);
    $t->contains('current-source next450 veryquick shard admission', $record['dependency_closure']);
    $t->contains('next343/next379/next382-through-next398', $record['non_overlap_note']);
    $t->contains('release/all parity remains unclaimed', $record['next_gate']);
};

return $tests;
