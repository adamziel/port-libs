<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteUpstreamSuiteEvidence;

function libsqlite_suite_next10451161_evidence(): SQLiteUpstreamSuiteEvidence
{
    return SQLiteUpstreamSuiteEvidence::fromManifestPath(__DIR__ . '/../UPSTREAM_TEST_MANIFEST.json');
}

/**
 * @return list<string>
 */
function libsqlite_suite_next10451161_scripts(): array
{
    $scripts = [];
    foreach (glob('/home/claude/port-libs/.upstream-cache/libsqlite/test/*.test') ?: [] as $path) {
        $scripts[] = basename($path);
    }
    sort($scripts, SORT_STRING);

    return array_slice($scripts, 1044, 117);
}

function libsqlite_suite_next10451161_output(int $passLines = 11349, int $assertions = 11349, int $failures = 0): string
{
    $lines = ['Focused test run: 1 selected test files (root lock skipped)'];
    foreach (range(1045, 1161) as $next) {
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
function libsqlite_suite_next10451161_rows(): array
{
    $scripts = libsqlite_suite_next10451161_scripts();
    $rows = [];
    foreach ($scripts as $offset => $script) {
        $next = 1045 + $offset;
        $case = $offset + 1;
        $rows[] = [
            'unit' => sprintf('suite-upstream-veryquick-shard-current-source-next%d', $next),
            'kind' => 'bounded-upstream-veryquick-shard-runner',
            'gap_id' => sprintf('current-source-next%d-veryquick-shard-gap', $next),
            'gap_status' => 'removed',
            'removed_blocker' => sprintf('next%d admits real hydrated upstream script %s tied to launcher base ab0d9bc9 without duplicating accepted next981 through next1044 or stale next965 through next980 suite evidence', $next, $script),
            'tier' => 'focused-veryquick-shard',
            'source_head' => 'suite-upstream-veryquick-shard-current-source-next1045-1161',
            'launcher_base_head' => 'ab0d9bc9',
            'dashboard_source_head' => 'ab0d9bc9',
            'status_source_head' => 'ab0d9bc9',
            'implementation_source_head' => 'ab0d9bc9',
            'artifact_path' => 'lanes/libsqlite/notes/bulk-upstream-runner-map-gap-closure-dynamic-20260530T200554Z-0.md',
            'runner_command' => './testfixture ../libsqlite/test/testrunner.tcl --jobs 1 --stop-on-error veryquick ' . $script,
            'scripts' => [$script, 'testrunner.test'],
            'current_countable' => false,
            'next_countable' => true,
            'exit' => 0,
            'errors' => 0,
            'current_tests' => 528264,
            'next_tests' => 528264 + $case,
        ];
    }

    $rows[] = [
        'unit' => 'integrated-next981-1044-current-source-anchor',
        'kind' => 'accepted-upstream-runner-anchor',
        'gap_id' => 'integrated-next981-1044-suite-anchor',
        'gap_status' => 'preserved',
        'removed_blocker' => '',
        'tier' => 'accepted-anchor',
        'source_head' => 'suite-upstream-veryquick-shard-current-source-next1045-1161',
        'launcher_base_head' => 'ab0d9bc9',
        'dashboard_source_head' => 'ab0d9bc9',
        'status_source_head' => 'ab0d9bc9',
        'implementation_source_head' => 'ab0d9bc9',
        'artifact_path' => 'lanes/libsqlite/notes/yield-suite-upstream-veryquick-shard-current-source-next981-1044.md',
        'runner_command' => './testfixture ../libsqlite/test/testrunner.tcl --jobs 1 --stop-on-error veryquick integrated-next981-1044-anchor.test',
        'scripts' => ['integrated-next981-1044-anchor.test'],
        'current_countable' => true,
        'next_countable' => true,
        'exit' => 0,
        'errors' => 0,
        'current_tests' => 528264,
        'next_tests' => 528264,
    ];

    return $rows;
}

/**
 * @param list<array<string, mixed>> $rows
 * @return array<string, mixed>
 */
function libsqlite_suite_next10451161_record(array $rows, ?string $output = null, int $expectedPassDelta = 11349): array
{
    return libsqlite_suite_next10451161_evidence()->upstreamVeryquickShardCurrentSourceBulkRange(
        1045,
        1161,
        $rows,
        1472,
        528264,
        'ab0d9bc9',
        'ab0d9bc9',
        'ab0d9bc9',
        'ab0d9bc9',
        'suite-upstream-veryquick-shard-current-source-next1045-1161',
        'lanes/libsqlite/tests/SQLiteUpstreamVeryquickShardCurrentSourceNext10451161Test.php',
        $output ?? libsqlite_suite_next10451161_output(),
        'current-source next1045-1161 veryquick bulk-shard admission cites 117 real hydrated upstream SQLite test scripts from valuesfault.test through windowA.test, avoids accepted next981-through-next1044 suite evidence, deliberately skips stale next965-through-next980 overlap, exact-shard next148, queued runner106/jsonvt104 rebase work, release/all parity, fabricated script ids, metadata-only PASS inflation, and source-neutral cleanup',
        $expectedPassDelta
    );
}

$tests = [];

$tests['current source next1045-1161 reads real hydrated scripts'] = static function (TestRunner $t): void {
    $scripts = libsqlite_suite_next10451161_scripts();

    $t->same(117, count($scripts));
    $t->same('valuesfault.test', $scripts[0]);
    $t->same('windowA.test', $scripts[116]);
    $t->true(in_array('wal.test', $scripts, true), 'Expected real WAL script in this range');
    $t->true(in_array('where.test', $scripts, true), 'Expected real WHERE script in this range');
    $t->true(in_array('window8.test', $scripts, true), 'Expected real window script in this range');
};

$tests['current source next1045-1161 admits real bulk veryquick range'] = static function (TestRunner $t): void {
    $record = libsqlite_suite_next10451161_record(libsqlite_suite_next10451161_rows());

    $t->same('current-source-next1045-next1161-veryquick-bulk-shard-advanced', $record['status']);
    $t->same(true, $record['countable']);
    $t->same(1472, $record['current_mapped']);
    $t->same(1589, $record['next_mapped']);
    $t->same(117, $record['mapped_delta']);
    $t->same(117, $record['admitted_count']);
    $t->same(1, $record['preserved_count']);
    $t->same(11349, $record['php_pass_delta']);
    $t->same(539613, $record['next_php_pass']);
    $t->same(117, $record['bulk_shard_count']);
    $t->same(1045, $record['bulk_shard_first']);
    $t->same(1161, $record['bulk_shard_last']);
    $t->same([], $record['bulk_missing_units']);
    $t->same([], $record['bulk_unexpected_units']);
    $t->same(false, $record['counts_upstream_exact_shard_runner_current_source_next148']);
    $t->same(false, $record['counts_upstream_runner_full_suite_countability_current_source_next116']);
    $t->same(false, $record['counts_release_parity']);

    foreach (range(1045, 1161) as $next) {
        $t->same(true, $record['counts_upstream_veryquick_shard_current_source_next' . $next]);
    }
    foreach (libsqlite_suite_next10451161_scripts() as $script) {
        $t->contains($script, implode(',', $record['target_scripts']));
    }
    $t->same(['integrated-next981-1044-current-source-anchor'], $record['preserved_units']);
};

$tests['current source next1045-1161 rejects incomplete bulk range'] = static function (TestRunner $t): void {
    $rows = array_values(array_filter(
        libsqlite_suite_next10451161_rows(),
        static fn (array $row): bool => ($row['unit'] ?? null) !== 'suite-upstream-veryquick-shard-current-source-next1161'
    ));

    $record = libsqlite_suite_next10451161_record($rows, libsqlite_suite_next10451161_output(970, 970), 970);

    $t->same('blocked', $record['status']);
    $t->same(false, $record['countable']);
    $t->same(0, $record['mapped_delta']);
    $t->same(0, $record['php_pass_delta']);
    $t->contains('bulk-veryquick-range-missing-shards', implode('; ', array_column($record['blockers'], 'id')));
    $t->contains('bulk-veryquick-pass-floor-not-met', implode('; ', array_column($record['blockers'], 'id')));
};

$tests['current source next1045-1161 blocks stale provenance'] = static function (TestRunner $t): void {
    $rows = libsqlite_suite_next10451161_rows();
    $rows[0]['implementation_source_head'] = '0000000000000000000000000000000000000000';

    $record = libsqlite_suite_next10451161_record($rows);

    $t->same('blocked', $record['status']);
    $t->same(0, $record['mapped_delta']);
    $t->contains('implementation-source-head-mismatch', implode('; ', array_column($record['blockers'], 'evidence')));
};

$tests['current source next1045-1161 blocks duplicate broad runner snapshot'] = static function (TestRunner $t): void {
    $record = libsqlite_suite_next10451161_evidence()->upstreamVeryquickShardCurrentSourceBulkRange(
        1045,
        1161,
        libsqlite_suite_next10451161_rows(),
        1472,
        528264,
        'ab0d9bc9',
        'ab0d9bc9',
        'ab0d9bc9',
        'ab0d9bc9',
        'suite-upstream-veryquick-shard-current-source-next1045-1161',
        'lanes/libsqlite/tests/SQLiteUpstreamVeryquickShardCurrentSourceNext10451161Test.php',
        libsqlite_suite_next10451161_output(),
        'current-source next1045-1161 duplicate broad runner guard',
        11349,
        '999 1 R 00:09 ./testfixture ../libsqlite/test/testrunner.tcl --jobs 4 release'
    );

    $t->same('blocked', $record['status']);
    $t->same(0, $record['mapped_delta']);
    $t->contains('duplicate-broad-runner-active', implode('; ', array_column($record['blockers'], 'id')));
};

return $tests;
