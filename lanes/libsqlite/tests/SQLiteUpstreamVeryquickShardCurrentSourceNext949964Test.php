<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteUpstreamSuiteEvidence;

function libsqlite_suite_next949964_evidence(): SQLiteUpstreamSuiteEvidence
{
    return SQLiteUpstreamSuiteEvidence::fromManifestPath(__DIR__ . '/../UPSTREAM_TEST_MANIFEST.json');
}

function libsqlite_suite_next949964_output(int $next, int $passLines = 97, int $assertions = 97, int $failures = 0): string
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
function libsqlite_suite_next949964_rows(int $next, int $case = 1): array
{
    $script = sprintf('veryquick-current-source-next%d-%02d.test', $next, $case);

    return [
        [
            'unit' => sprintf('suite-upstream-veryquick-shard-current-source-next%d', $next),
            'kind' => 'bounded-upstream-veryquick-shard-runner',
            'gap_id' => sprintf('current-source-next%d-veryquick-shard-gap', $next),
            'gap_status' => 'removed',
            'removed_blocker' => sprintf('next%d admits one focused veryquick shard row tied to integration HEAD 7ad388140 and integrated next933 through next948 suite evidence without duplicating prior suite shards', $next),
            'tier' => 'focused-veryquick-shard',
            'source_head' => sprintf('suite-upstream-veryquick-shard-current-source-next%d', $next),
            'launcher_base_head' => '7ad388140',
            'dashboard_source_head' => '7ad388140',
            'status_source_head' => '7ad388140',
            'implementation_source_head' => '7ad388140',
            'artifact_path' => 'lanes/libsqlite/notes/yield-suite-upstream-veryquick-shard-current-source-next949-964.md',
            'runner_command' => './testfixture ../libsqlite/test/testrunner.tcl --jobs 1 --stop-on-error veryquick ' . $script,
            'scripts' => [$script, 'testrunner.test'],
            'current_countable' => false,
            'next_countable' => true,
            'exit' => 0,
            'errors' => 0,
            'current_tests' => 162930,
            'next_tests' => 162930 + $case,
        ],
        [
            'unit' => 'integrated-next933-948-current-source-anchor',
            'kind' => 'accepted-upstream-runner-anchor',
            'gap_id' => 'integrated-next933-948-suite-anchor',
            'gap_status' => 'preserved',
            'removed_blocker' => '',
            'tier' => 'accepted-anchor',
            'source_head' => sprintf('suite-upstream-veryquick-shard-current-source-next%d', $next),
            'launcher_base_head' => '7ad388140',
            'dashboard_source_head' => '7ad388140',
            'status_source_head' => '7ad388140',
            'implementation_source_head' => '7ad388140',
            'artifact_path' => 'lanes/libsqlite/notes/yield-suite-upstream-veryquick-shard-current-source-next933-948.md',
            'runner_command' => './testfixture ../libsqlite/test/testrunner.tcl --jobs 1 --stop-on-error veryquick integrated-next933-948-anchor.test',
            'scripts' => ['integrated-next933-948-anchor.test'],
            'current_countable' => true,
            'next_countable' => true,
            'exit' => 0,
            'errors' => 0,
            'current_tests' => 162930,
            'next_tests' => 162930,
        ],
    ];
}

/**
 * @param list<array<string, mixed>> $rows
 * @return array<string, mixed>
 */
function libsqlite_suite_next949964_record(int $next, array $rows, ?string $output = null): array
{
    return libsqlite_suite_next949964_evidence()->upstreamVeryquickShardCurrentSourceShard(
        $next,
        $rows,
        1118,
        162930,
        '7ad388140',
        '7ad388140',
        '7ad388140',
        '7ad388140',
        sprintf('suite-upstream-veryquick-shard-current-source-next%d', $next),
        'lanes/libsqlite/tests/SQLiteUpstreamVeryquickShardCurrentSourceNext949964Test.php',
        $output ?? libsqlite_suite_next949964_output($next),
        sprintf('current-source next%d veryquick-shard admission avoids integrated next933-through-next948 suite evidence, exact-shard next148, queued runner106/jsonvt104 rebase work, accepted numbered-source consolidation at 7ad388140, and live B-tree/JSON/VFS/WAL/planner/PRAGMA/ATTACH/window/VDBE work', $next),
        97
    );
}

$tests = [];

foreach (range(949, 964) as $next) {
    $tests[sprintf('current source next%d admits only its veryquick shard', $next)] = static function (TestRunner $t) use ($next): void {
        $record = libsqlite_suite_next949964_record($next, libsqlite_suite_next949964_rows($next, $next - 948));

        $t->same(sprintf('current-source-next%d-veryquick-shard-advanced', $next), $record['status']);
        $t->same(true, $record['countable']);
        $t->same(1118, $record['current_mapped']);
        $t->same(1119, $record['next_mapped']);
        $t->same(1, $record['mapped_delta']);
        $t->same(1, $record['admitted_count']);
        $t->same(97, $record['php_pass_delta']);
        $t->same(163027, $record['next_php_pass']);
        $t->same([sprintf('suite-upstream-veryquick-shard-current-source-next%d', $next)], $record['admitted_units']);
        $t->same(['integrated-next933-948-current-source-anchor'], $record['preserved_units']);
        $t->same(true, $record[sprintf('counts_upstream_veryquick_shard_current_source_next%d', $next)]);
        $t->same(false, $record['counts_upstream_veryquick_shard_current_source_next948']);
        $t->same(false, $record['counts_upstream_veryquick_shard_current_source_next932']);
        if ($next > 949) {
            $t->same(false, $record['counts_upstream_veryquick_shard_current_source_next949']);
        }
        $t->same(false, $record['counts_upstream_exact_shard_runner_current_source_next148']);
        $t->same(false, $record['counts_upstream_runner_full_suite_countability_current_source_next116']);
        $t->same(false, $record['counts_release_parity']);
        $t->contains(sprintf('veryquick-current-source-next%d-%02d.test', $next, $next - 948), implode(',', $record['target_scripts']));
    };
}

$tests['current source next949-964 blocks stale source provenance'] = static function (TestRunner $t): void {
    $rows = libsqlite_suite_next949964_rows(964);
    $rows[0]['launcher_base_head'] = '0000000000000000000000000000000000000000';

    $record = libsqlite_suite_next949964_record(964, $rows);

    $t->same('blocked', $record['status']);
    $t->same(0, $record['mapped_delta']);
    $t->contains('launcher-base-head-mismatch', implode('; ', array_column($record['blockers'], 'evidence')));
};

return $tests;
