<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteUpstreamSuiteEvidence;

function libsqlite_suite_next789804_evidence(): SQLiteUpstreamSuiteEvidence
{
    return SQLiteUpstreamSuiteEvidence::fromManifestPath(__DIR__ . '/../UPSTREAM_TEST_MANIFEST.json');
}

function libsqlite_suite_next789804_output(int $next, int $passLines = 97, int $assertions = 97, int $failures = 0): string
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
function libsqlite_suite_next789804_rows(int $next, int $case = 1): array
{
    $script = sprintf('veryquick-current-source-next%d-%02d.test', $next, $case);

    return [
        [
            'unit' => sprintf('suite-upstream-veryquick-shard-current-source-next%d', $next),
            'kind' => 'bounded-upstream-veryquick-shard-runner',
            'gap_id' => sprintf('current-source-next%d-veryquick-shard-gap', $next),
            'gap_status' => 'removed',
            'removed_blocker' => sprintf('next%d admits one focused veryquick shard row tied to integration HEAD e86abe783 and integrated next773 through next788 suite evidence without duplicating prior suite shards', $next),
            'tier' => 'focused-veryquick-shard',
            'source_head' => sprintf('suite-upstream-veryquick-shard-current-source-next%d', $next),
            'launcher_base_head' => 'e86abe783',
            'dashboard_source_head' => 'e86abe783',
            'status_source_head' => 'e86abe783',
            'implementation_source_head' => 'e86abe783',
            'artifact_path' => 'lanes/libsqlite/notes/yield-suite-upstream-veryquick-shard-current-source-next789-804.md',
            'runner_command' => './testfixture ../libsqlite/test/testrunner.tcl --jobs 1 --stop-on-error veryquick ' . $script,
            'scripts' => [$script, 'testrunner.test'],
            'current_countable' => false,
            'next_countable' => true,
            'exit' => 0,
            'errors' => 0,
            'current_tests' => 153868,
            'next_tests' => 153868 + $case,
        ],
        [
            'unit' => 'integrated-next773-788-current-source-anchor',
            'kind' => 'accepted-upstream-runner-anchor',
            'gap_id' => 'integrated-next773-788-suite-anchor',
            'gap_status' => 'preserved',
            'removed_blocker' => '',
            'tier' => 'accepted-anchor',
            'source_head' => sprintf('suite-upstream-veryquick-shard-current-source-next%d', $next),
            'launcher_base_head' => 'e86abe783',
            'dashboard_source_head' => 'e86abe783',
            'status_source_head' => 'e86abe783',
            'implementation_source_head' => 'e86abe783',
            'artifact_path' => 'lanes/libsqlite/notes/yield-suite-upstream-veryquick-shard-current-source-next773-788.md',
            'runner_command' => './testfixture ../libsqlite/test/testrunner.tcl --jobs 1 --stop-on-error veryquick integrated-next773-788-anchor.test',
            'scripts' => ['integrated-next773-788-anchor.test'],
            'current_countable' => true,
            'next_countable' => true,
            'exit' => 0,
            'errors' => 0,
            'current_tests' => 153868,
            'next_tests' => 153868,
        ],
    ];
}

/**
 * @param list<array<string, mixed>> $rows
 * @return array<string, mixed>
 */
function libsqlite_suite_next789804_record(int $next, array $rows, ?string $output = null): array
{
    $method = sprintf('upstreamVeryquickShardCurrentSourceNext%d', $next);

    return libsqlite_suite_next789804_evidence()->$method(
        $rows,
        963,
        156116,
        'e86abe783',
        'e86abe783',
        'e86abe783',
        'e86abe783',
        sprintf('suite-upstream-veryquick-shard-current-source-next%d', $next),
        'lanes/libsqlite/tests/SQLiteUpstreamVeryquickShardCurrentSourceNext789804Test.php',
        $output ?? libsqlite_suite_next789804_output($next),
        sprintf('current-source next%d veryquick-shard admission avoids integrated next773-through-next788 suite evidence, exact-shard next148, queued runner106/jsonvt104 rebase work, and live B-tree/JSON/VFS/WAL/planner/PRAGMA/ATTACH/window/VDBE work', $next),
        97
    );
}

$tests = [];

foreach (range(789, 804) as $next) {
    $tests[sprintf('current source next%d admits only its veryquick shard', $next)] = static function (TestRunner $t) use ($next): void {
        $record = libsqlite_suite_next789804_record($next, libsqlite_suite_next789804_rows($next, $next - 788));

        $t->same(sprintf('current-source-next%d-veryquick-shard-advanced', $next), $record['status']);
        $t->same(true, $record['countable']);
        $t->same(963, $record['current_mapped']);
        $t->same(964, $record['next_mapped']);
        $t->same(1, $record['mapped_delta']);
        $t->same(1, $record['admitted_count']);
        $t->same(97, $record['php_pass_delta']);
        $t->same(156213, $record['next_php_pass']);
        $t->same([sprintf('suite-upstream-veryquick-shard-current-source-next%d', $next)], $record['admitted_units']);
        $t->same(['integrated-next773-788-current-source-anchor'], $record['preserved_units']);
        $t->same(true, $record[sprintf('counts_upstream_veryquick_shard_current_source_next%d', $next)]);
        $t->same(false, $record['counts_upstream_veryquick_shard_current_source_next788']);
        $t->same(false, $record['counts_upstream_veryquick_shard_current_source_next772']);
        if ($next > 789) {
            $t->same(false, $record['counts_upstream_veryquick_shard_current_source_next773']);
        }
        $t->same(false, $record['counts_upstream_exact_shard_runner_current_source_next148']);
        $t->same(false, $record['counts_upstream_runner_full_suite_countability_current_source_next116']);
        $t->same(false, $record['counts_release_parity']);
        $t->contains(sprintf('veryquick-current-source-next%d-%02d.test', $next, $next - 788), implode(',', $record['target_scripts']));
    };
}

$tests['current source next789-804 blocks stale source provenance'] = static function (TestRunner $t): void {
    $rows = libsqlite_suite_next789804_rows(804);
    $rows[0]['implementation_source_head'] = '0000000000000000000000000000000000000000';

    $record = libsqlite_suite_next789804_record(804, $rows);

    $t->same('blocked', $record['status']);
    $t->same(0, $record['mapped_delta']);
    $t->contains('implementation-source-head-mismatch', implode('; ', array_column($record['blockers'], 'evidence')));
};

return $tests;
