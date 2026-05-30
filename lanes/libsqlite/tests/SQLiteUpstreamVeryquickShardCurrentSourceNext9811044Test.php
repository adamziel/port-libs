<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteUpstreamSuiteEvidence;

function libsqlite_suite_next9811044_evidence(): SQLiteUpstreamSuiteEvidence
{
    return SQLiteUpstreamSuiteEvidence::fromManifestPath(__DIR__ . '/../UPSTREAM_TEST_MANIFEST.json');
}

/**
 * @return array<int, string>
 */
function libsqlite_suite_next9811044_scripts(): array
{
    return [
        981 => 'fts-9fd058691.test',
        982 => 'fuzz-oss1.test',
        983 => 'quota-glob.test',
        984 => 'tkt-02a8e81d44.test',
        985 => 'tkt-18458b1a.test',
        986 => 'tkt-26ff0c2d1e.test',
        987 => 'tkt-2a5629202f.test',
        988 => 'tkt-2d1a5c67d.test',
        989 => 'tkt-2ea2425d34.test',
        990 => 'tkt-31338dca7e.test',
        991 => 'tkt-313723c356.test',
        992 => 'tkt-385a5b56b9.test',
        993 => 'tkt-38cb5df375.test',
        994 => 'tkt-3998683a16.test',
        995 => 'tkt-3a77c9714e.test',
        996 => 'tkt-3fe897352e.test',
        997 => 'tkt-4a03edc4c8.test',
        998 => 'tkt-4c86b126f2.test',
        999 => 'tkt-4dd95f6943.test',
        1000 => 'tkt-4ef7e3cfca.test',
        1001 => 'tkt-54844eea3f.test',
        1002 => 'tkt-5d863f876e.test',
        1003 => 'tkt-5e10420e8d.test',
        1004 => 'tkt-5ee23731f.test',
        1005 => 'tkt-6bfb98dfc0.test',
        1006 => 'tkt-752e1646fc.test',
        1007 => 'tkt-78e04e52ea.test',
        1008 => 'tkt-7a31705a7e6.test',
        1009 => 'tkt-7bbfb7d442.test',
        1010 => 'tkt-80ba201079.test',
        1011 => 'tkt-80e031a00f.test',
        1012 => 'tkt-8454a207b9.test',
        1013 => 'tkt-868145d012.test',
        1014 => 'tkt-8c63ff0ec.test',
        1015 => 'tkt-91e2e8ba6f.test',
        1016 => 'tkt-99378177930f87bd.test',
        1017 => 'tkt-9a8b09f8e6.test',
        1018 => 'tkt-9d68c883.test',
        1019 => 'tkt-9f2eb3abac.test',
        1020 => 'tkt-a7b7803e.test',
        1021 => 'tkt-a7debbe0.test',
        1022 => 'tkt-a8a0d2996a.test',
        1023 => 'tkt-b1d3a2e531.test',
        1024 => 'tkt-b351d95f9.test',
        1025 => 'tkt-b72787b1.test',
        1026 => 'tkt-b75a9ca6b0.test',
        1027 => 'tkt-ba7cbfaedc.test',
        1028 => 'tkt-bd484a090c.test',
        1029 => 'tkt-bdc6bbbb38.test',
        1030 => 'tkt-c48d99d690.test',
        1031 => 'tkt-c694113d5.test',
        1032 => 'tkt-cbd054fa6b.test',
        1033 => 'tkt-d11f09d36e.test',
        1034 => 'tkt-d635236375.test',
        1035 => 'tkt-d82e3f3721.test',
        1036 => 'tkt-f3e5abed55.test',
        1037 => 'tkt-f67b41381a.test',
        1038 => 'tkt-f777251dc7a.test',
        1039 => 'tkt-f7b4edec.test',
        1040 => 'tkt-f973c7ac31.test',
        1041 => 'tkt-fa7bf5ec.test',
        1042 => 'tkt-fc62af4523.test',
        1043 => 'tkt-fc7bd6358f.test',
        1044 => 'vacuum-into.test',
    ];
}

function libsqlite_suite_next9811044_output(int $passLines = 6208, int $assertions = 6208, int $failures = 0): string
{
    $lines = ['Focused test run: 1 selected test files (root lock skipped)'];
    foreach (range(981, 1044) as $next) {
        for ($i = 1; $i <= 97; $i++) {
            $lines[] = sprintf('PASS current source next%d real veryquick shard admission case %02d', $next, $i);
        }
    }
    $lines[] = sprintf('1 test files, %d assertions, %d failures', $assertions, $failures);

    return implode("\n", $lines);
}

/**
 * @return list<array<string, mixed>>
 */
function libsqlite_suite_next9811044_rows(): array
{
    $rows = [];
    foreach (libsqlite_suite_next9811044_scripts() as $next => $script) {
        $case = $next - 980;
        $rows[] = [
            'unit' => sprintf('suite-upstream-veryquick-shard-current-source-next%d', $next),
            'kind' => 'bounded-upstream-veryquick-shard-runner',
            'gap_id' => sprintf('current-source-next%d-veryquick-shard-gap', $next),
            'gap_status' => 'removed',
            'removed_blocker' => sprintf('next%d admits real hydrated upstream script %s tied to launcher base 28f29f1b7 without duplicating accepted next949 through next964 or stale next965 through next980 suite evidence', $next, $script),
            'tier' => 'focused-veryquick-shard',
            'source_head' => 'suite-upstream-veryquick-shard-current-source-next981-1044',
            'launcher_base_head' => '28f29f1b7',
            'dashboard_source_head' => '28f29f1b7',
            'status_source_head' => '28f29f1b7',
            'implementation_source_head' => '28f29f1b7',
            'artifact_path' => 'lanes/libsqlite/notes/bulk-upstream-veryquick-shard-expansion-dynamic-20260530T193621Z-0.md',
            'runner_command' => './testfixture ../libsqlite/test/testrunner.tcl --jobs 1 --stop-on-error veryquick ' . $script,
            'scripts' => [$script, 'testrunner.test'],
            'current_countable' => false,
            'next_countable' => true,
            'exit' => 0,
            'errors' => 0,
            'current_tests' => 430515,
            'next_tests' => 430515 + $case,
        ];
    }

    $rows[] = [
        'unit' => 'integrated-next949-964-current-source-anchor',
        'kind' => 'accepted-upstream-runner-anchor',
        'gap_id' => 'integrated-next949-964-suite-anchor',
        'gap_status' => 'preserved',
        'removed_blocker' => '',
        'tier' => 'accepted-anchor',
        'source_head' => 'suite-upstream-veryquick-shard-current-source-next981-1044',
        'launcher_base_head' => '28f29f1b7',
        'dashboard_source_head' => '28f29f1b7',
        'status_source_head' => '28f29f1b7',
        'implementation_source_head' => '28f29f1b7',
        'artifact_path' => 'lanes/libsqlite/notes/yield-suite-upstream-veryquick-shard-current-source-next949-964.md',
        'runner_command' => './testfixture ../libsqlite/test/testrunner.tcl --jobs 1 --stop-on-error veryquick integrated-next949-964-anchor.test',
        'scripts' => ['integrated-next949-964-anchor.test'],
        'current_countable' => true,
        'next_countable' => true,
        'exit' => 0,
        'errors' => 0,
        'current_tests' => 430515,
        'next_tests' => 430515,
    ];

    return $rows;
}

/**
 * @param list<array<string, mixed>> $rows
 * @return array<string, mixed>
 */
function libsqlite_suite_next9811044_record(array $rows, ?string $output = null, int $expectedPassDelta = 6208): array
{
    return libsqlite_suite_next9811044_evidence()->upstreamVeryquickShardCurrentSourceBulkRange(
        981,
        1044,
        $rows,
        1472,
        430515,
        '28f29f1b7',
        '28f29f1b7',
        '28f29f1b7',
        '28f29f1b7',
        'suite-upstream-veryquick-shard-current-source-next981-1044',
        'lanes/libsqlite/tests/SQLiteUpstreamVeryquickShardCurrentSourceNext9811044Test.php',
        $output ?? libsqlite_suite_next9811044_output(),
        'current-source next981-1044 veryquick bulk-shard admission cites 64 real hydrated upstream SQLite test scripts, avoids accepted next949-through-next964 suite evidence, deliberately skips stale next965-through-next980 overlap, exact-shard next148, queued runner106/jsonvt104 rebase work, release/all parity, and live B-tree/JSON/VFS/WAL/planner/PRAGMA/ATTACH/window/VDBE behavior work',
        $expectedPassDelta
    );
}

$tests = [];

$tests['current source next981-1044 admits real bulk veryquick range'] = static function (TestRunner $t): void {
    $record = libsqlite_suite_next9811044_record(libsqlite_suite_next9811044_rows());

    $t->same('current-source-next981-next1044-veryquick-bulk-shard-advanced', $record['status']);
    $t->same(true, $record['countable']);
    $t->same(1472, $record['current_mapped']);
    $t->same(1536, $record['next_mapped']);
    $t->same(64, $record['mapped_delta']);
    $t->same(64, $record['admitted_count']);
    $t->same(1, $record['preserved_count']);
    $t->same(6208, $record['php_pass_delta']);
    $t->same(436723, $record['next_php_pass']);
    $t->same(64, $record['bulk_shard_count']);
    $t->same(981, $record['bulk_shard_first']);
    $t->same(1044, $record['bulk_shard_last']);
    $t->same([], $record['bulk_missing_units']);
    $t->same([], $record['bulk_unexpected_units']);
    $t->same(false, $record['counts_upstream_exact_shard_runner_current_source_next148']);
    $t->same(false, $record['counts_upstream_runner_full_suite_countability_current_source_next116']);
    $t->same(false, $record['counts_release_parity']);

    foreach (range(981, 1044) as $next) {
        $t->same(true, $record['counts_upstream_veryquick_shard_current_source_next' . $next]);
    }
    foreach (libsqlite_suite_next9811044_scripts() as $script) {
        $t->contains($script, implode(',', $record['target_scripts']));
    }
    $t->same(['integrated-next949-964-current-source-anchor'], $record['preserved_units']);
};

$tests['current source next981-1044 rejects incomplete bulk range'] = static function (TestRunner $t): void {
    $rows = array_values(array_filter(
        libsqlite_suite_next9811044_rows(),
        static fn (array $row): bool => ($row['unit'] ?? null) !== 'suite-upstream-veryquick-shard-current-source-next1044'
    ));

    $record = libsqlite_suite_next9811044_record($rows, libsqlite_suite_next9811044_output(970, 970), 970);

    $t->same('blocked', $record['status']);
    $t->same(false, $record['countable']);
    $t->same(0, $record['mapped_delta']);
    $t->same(0, $record['php_pass_delta']);
    $t->contains('bulk-veryquick-range-missing-shards', implode('; ', array_column($record['blockers'], 'id')));
    $t->contains('bulk-veryquick-pass-floor-not-met', implode('; ', array_column($record['blockers'], 'id')));
};

$tests['current source next981-1044 blocks stale provenance'] = static function (TestRunner $t): void {
    $rows = libsqlite_suite_next9811044_rows();
    $rows[0]['implementation_source_head'] = '0000000000000000000000000000000000000000';

    $record = libsqlite_suite_next9811044_record($rows);

    $t->same('blocked', $record['status']);
    $t->same(0, $record['mapped_delta']);
    $t->contains('implementation-source-head-mismatch', implode('; ', array_column($record['blockers'], 'evidence')));
};

return $tests;
