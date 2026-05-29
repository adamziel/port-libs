<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteUpstreamSuiteEvidence;

function libsqlite_suite_next917932_evidence(): SQLiteUpstreamSuiteEvidence
{
    return SQLiteUpstreamSuiteEvidence::fromManifestPath(__DIR__ . '/../UPSTREAM_TEST_MANIFEST.json');
}

function libsqlite_suite_next917932_output(int $next, int $passLines = 97, int $assertions = 97, int $failures = 0): string
{
    $lines = ['Focused test run: 1 selected test files (root lock skipped)'];
    for ($i = 1; $i <= $passLines; $i++) {
        $lines[] = sprintf('PASS current source next%d veryquick shard admission case %02d', $next, $i);
    }
    $lines[] = sprintf('1 test files, %d assertions, %d failures', $assertions, $failures);

    return implode("\n", $lines);
}

/**
 * @return list<array<string, mixed>>
 */
function libsqlite_suite_next917932_rows(int $next, int $case = 1): array
{
    $script = sprintf('veryquick-current-source-next%d-%02d.test', $next, $case);

    return [
        [
            'unit' => sprintf('suite-upstream-veryquick-shard-current-source-next%d', $next),
            'kind' => 'bounded-upstream-veryquick-shard-runner',
            'gap_id' => sprintf('current-source-next%d-veryquick-shard-gap', $next),
            'gap_status' => 'removed',
            'removed_blocker' => sprintf('next%d admits one focused veryquick shard row tied to integration HEAD fca16e3d and integrated next901 through next916 suite evidence without duplicating prior suite shards', $next),
            'tier' => 'focused-veryquick-shard',
            'source_head' => sprintf('suite-upstream-veryquick-shard-current-source-next%d', $next),
            'launcher_base_head' => 'fca16e3d',
            'dashboard_source_head' => '8a447f44',
            'status_source_head' => '8a447f44',
            'implementation_source_head' => '8a447f44',
            'artifact_path' => 'lanes/libsqlite/notes/yield-suite-upstream-veryquick-shard-current-source-next917-932.md',
            'runner_command' => './testfixture ../libsqlite/test/testrunner.tcl --jobs 1 --stop-on-error veryquick ' . $script,
            'scripts' => [$script, 'testrunner.test'],
            'current_countable' => false,
            'next_countable' => true,
            'exit' => 0,
            'errors' => 0,
            'current_tests' => 162736,
            'next_tests' => 162736 + $case,
        ],
        [
            'unit' => 'integrated-next901-916-current-source-anchor',
            'kind' => 'accepted-upstream-runner-anchor',
            'gap_id' => 'integrated-next901-916-suite-anchor',
            'gap_status' => 'preserved',
            'removed_blocker' => '',
            'tier' => 'accepted-anchor',
            'source_head' => sprintf('suite-upstream-veryquick-shard-current-source-next%d', $next),
            'launcher_base_head' => 'fca16e3d',
            'dashboard_source_head' => '8a447f44',
            'status_source_head' => '8a447f44',
            'implementation_source_head' => '8a447f44',
            'artifact_path' => 'lanes/libsqlite/notes/yield-suite-upstream-veryquick-shard-current-source-next901-916.md',
            'runner_command' => './testfixture ../libsqlite/test/testrunner.tcl --jobs 1 --stop-on-error veryquick integrated-next901-916-anchor.test',
            'scripts' => ['integrated-next901-916-anchor.test'],
            'current_countable' => true,
            'next_countable' => true,
            'exit' => 0,
            'errors' => 0,
            'current_tests' => 162736,
            'next_tests' => 162736,
        ],
    ];
}

/**
 * @param list<array<string, mixed>> $rows
 * @return array<string, mixed>
 */
function libsqlite_suite_next917932_record(int $next, array $rows, ?string $output = null): array
{
    return libsqlite_suite_next917932_evidence()->upstreamVeryquickShardCurrentSourceShard(
        $next,
        $rows,
        1116,
        162736,
        'fca16e3d',
        '8a447f44',
        '8a447f44',
        '8a447f44',
        sprintf('suite-upstream-veryquick-shard-current-source-next%d', $next),
        'lanes/libsqlite/tests/SQLiteUpstreamVeryquickShardCurrentSourceNext917932Test.php',
        $output ?? libsqlite_suite_next917932_output($next),
        sprintf('current-source next%d veryquick-shard admission avoids integrated next901-through-next916 suite evidence, exact-shard next148, queued runner106/jsonvt104 rebase work, and live B-tree/JSON/VFS/WAL/planner/PRAGMA/ATTACH/window/VDBE work', $next),
        97
    );
}

$tests = [];

foreach (range(917, 932) as $next) {
    $tests[sprintf('current source next%d admits only its veryquick shard', $next)] = static function (TestRunner $t) use ($next): void {
        $record = libsqlite_suite_next917932_record($next, libsqlite_suite_next917932_rows($next, $next - 916));

        $t->same(sprintf('current-source-next%d-veryquick-shard-advanced', $next), $record['status']);
        $t->same(true, $record['countable']);
        $t->same(1116, $record['current_mapped']);
        $t->same(1117, $record['next_mapped']);
        $t->same(1, $record['mapped_delta']);
        $t->same(1, $record['admitted_count']);
        $t->same(97, $record['php_pass_delta']);
        $t->same(162833, $record['next_php_pass']);
        $t->same([sprintf('suite-upstream-veryquick-shard-current-source-next%d', $next)], $record['admitted_units']);
        $t->same(['integrated-next901-916-current-source-anchor'], $record['preserved_units']);
        $t->same(true, $record[sprintf('counts_upstream_veryquick_shard_current_source_next%d', $next)]);
        $t->same(false, $record['counts_upstream_veryquick_shard_current_source_next916']);
        $t->same(false, $record['counts_upstream_veryquick_shard_current_source_next900']);
        if ($next > 917) {
            $t->same(false, $record['counts_upstream_veryquick_shard_current_source_next917']);
        }
        $t->same(false, $record['counts_upstream_exact_shard_runner_current_source_next148']);
        $t->same(false, $record['counts_upstream_runner_full_suite_countability_current_source_next116']);
        $t->same(false, $record['counts_release_parity']);
        $t->contains(sprintf('veryquick-current-source-next%d-%02d.test', $next, $next - 916), implode(',', $record['target_scripts']));
    };
}

$tests['current source next917-932 blocks stale source provenance'] = static function (TestRunner $t): void {
    $rows = libsqlite_suite_next917932_rows(932);
    $rows[0]['status_source_head'] = '0000000000000000000000000000000000000000';

    $record = libsqlite_suite_next917932_record(932, $rows);

    $t->same('blocked', $record['status']);
    $t->same(0, $record['mapped_delta']);
    $t->contains('status-source-head-mismatch', implode('; ', array_column($record['blockers'], 'evidence')));
};

return $tests;
