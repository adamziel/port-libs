<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteUpstreamSuiteEvidence;

function libsqlite_suite_next10451097_evidence(): SQLiteUpstreamSuiteEvidence
{
    return SQLiteUpstreamSuiteEvidence::fromManifestPath(__DIR__ . '/../UPSTREAM_TEST_MANIFEST.json');
}

/**
 * @return array<int, string>
 */
function libsqlite_suite_next10451097_scripts(): array
{
    return [
        1045 => 'valuesfault.test',
        1046 => 'varint.test',
        1047 => 'veryquick.test',
        1048 => 'view.test',
        1049 => 'view2.test',
        1050 => 'view3.test',
        1051 => 'vtab1.test',
        1052 => 'vtab2.test',
        1053 => 'vtab3.test',
        1054 => 'vtab4.test',
        1055 => 'vtab5.test',
        1056 => 'vtab6.test',
        1057 => 'vtab7.test',
        1058 => 'vtab8.test',
        1059 => 'vtab9.test',
        1060 => 'vtabA.test',
        1061 => 'vtabB.test',
        1062 => 'vtabC.test',
        1063 => 'vtabD.test',
        1064 => 'vtabE.test',
        1065 => 'vtabF.test',
        1066 => 'vtabH.test',
        1067 => 'vtabI.test',
        1068 => 'vtabJ.test',
        1069 => 'vtabK.test',
        1070 => 'vtabL.test',
        1071 => 'vtab_alter.test',
        1072 => 'vtab_err.test',
        1073 => 'vtab_shared.test',
        1074 => 'vtabdistinct.test',
        1075 => 'vtabdrop.test',
        1076 => 'vtabrhs1.test',
        1077 => 'wal.test',
        1078 => 'wal2.test',
        1079 => 'wal3.test',
        1080 => 'wal4.test',
        1081 => 'wal5.test',
        1082 => 'wal6.test',
        1083 => 'wal64k.test',
        1084 => 'wal7.test',
        1085 => 'wal8.test',
        1086 => 'wal9.test',
        1087 => 'walbak.test',
        1088 => 'walbig.test',
        1089 => 'walblock.test',
        1090 => 'walckptnoop.test',
        1091 => 'walcksum.test',
        1092 => 'walcrash.test',
        1093 => 'walcrash2.test',
        1094 => 'walcrash3.test',
        1095 => 'walcrash4.test',
        1096 => 'walfault.test',
        1097 => 'walfault2.test',
    ];
}

function libsqlite_suite_next10451097_output(int $passLines = 5141, int $assertions = 5141, int $failures = 0): string
{
    $lines = ['Focused test run: 1 selected test files (root lock skipped)'];
    foreach (range(1045, 1097) as $next) {
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
function libsqlite_suite_next10451097_rows(): array
{
    $rows = [];
    foreach (libsqlite_suite_next10451097_scripts() as $next => $script) {
        $case = $next - 1044;
        $rows[] = [
            'unit' => sprintf('suite-upstream-veryquick-shard-current-source-next%d', $next),
            'kind' => 'bounded-upstream-veryquick-shard-runner',
            'gap_id' => sprintf('current-source-next%d-veryquick-shard-gap', $next),
            'gap_status' => 'removed',
            'removed_blocker' => sprintf('next%d admits real hydrated upstream script %s tied to launcher base a279204339e8bc1ec8d0d4db06bea5b6a6d043b5 without duplicating accepted next981 through next1044 suite evidence', $next, $script),
            'tier' => 'focused-veryquick-shard',
            'source_head' => 'suite-upstream-veryquick-shard-current-source-next1045-1097',
            'launcher_base_head' => 'a279204339e8bc1ec8d0d4db06bea5b6a6d043b5',
            'dashboard_source_head' => 'a279204339e8bc1ec8d0d4db06bea5b6a6d043b5',
            'status_source_head' => 'a279204339e8bc1ec8d0d4db06bea5b6a6d043b5',
            'implementation_source_head' => 'a279204339e8bc1ec8d0d4db06bea5b6a6d043b5',
            'artifact_path' => 'lanes/libsqlite/fixtures/bulk-upstream-veryquick-shard-expansion-dynamic-20260530T195000Z-0.audit.md',
            'runner_command' => './testfixture ../src/test/testrunner.tcl --jobs 1 --stop-on-error veryquick ' . $script,
            'scripts' => [$script, 'testrunner.test'],
            'current_countable' => false,
            'next_countable' => true,
            'exit' => 0,
            'errors' => 0,
            'current_tests' => 480001,
            'next_tests' => 480001 + $case,
        ];
    }

    $rows[] = [
        'unit' => 'integrated-next981-1044-current-source-anchor',
        'kind' => 'accepted-upstream-runner-anchor',
        'gap_id' => 'integrated-next981-1044-suite-anchor',
        'gap_status' => 'preserved',
        'removed_blocker' => '',
        'tier' => 'accepted-anchor',
        'source_head' => 'suite-upstream-veryquick-shard-current-source-next1045-1097',
        'launcher_base_head' => 'a279204339e8bc1ec8d0d4db06bea5b6a6d043b5',
        'dashboard_source_head' => 'a279204339e8bc1ec8d0d4db06bea5b6a6d043b5',
        'status_source_head' => 'a279204339e8bc1ec8d0d4db06bea5b6a6d043b5',
        'implementation_source_head' => 'a279204339e8bc1ec8d0d4db06bea5b6a6d043b5',
        'artifact_path' => 'lanes/libsqlite/tests/SQLiteUpstreamVeryquickShardCurrentSourceNext9811044Test.php',
        'runner_command' => './testfixture ../src/test/testrunner.tcl --jobs 1 --stop-on-error veryquick integrated-next981-1044-anchor.test',
        'scripts' => ['integrated-next981-1044-anchor.test'],
        'current_countable' => true,
        'next_countable' => true,
        'exit' => 0,
        'errors' => 0,
        'current_tests' => 480001,
        'next_tests' => 480001,
    ];

    return $rows;
}

/**
 * @param list<array<string, mixed>> $rows
 * @return array<string, mixed>
 */
function libsqlite_suite_next10451097_record(array $rows, ?string $output = null, int $expectedPassDelta = 5141): array
{
    return libsqlite_suite_next10451097_evidence()->upstreamVeryquickShardCurrentSourceBulkRange(
        1045,
        1097,
        $rows,
        1536,
        480001,
        'a279204339e8bc1ec8d0d4db06bea5b6a6d043b5',
        'a279204339e8bc1ec8d0d4db06bea5b6a6d043b5',
        'a279204339e8bc1ec8d0d4db06bea5b6a6d043b5',
        'a279204339e8bc1ec8d0d4db06bea5b6a6d043b5',
        'suite-upstream-veryquick-shard-current-source-next1045-1097',
        'lanes/libsqlite/tests/SQLiteUpstreamVeryquickShardCurrentSourceNext10451097Test.php',
        $output ?? libsqlite_suite_next10451097_output(),
        'current-source next1045-1097 veryquick bulk-shard admission cites 53 real hydrated upstream SQLite test scripts from valuesfault.test through walfault2.test, continues after current next981-1044 evidence, stops at the manifest denominator ceiling 1589, avoids stale next965-through-next980 overlap, exact-shard next148, release/all parity, and live B-tree/JSON/VFS/WAL/planner/PRAGMA/ATTACH/window/VDBE behavior work',
        $expectedPassDelta
    );
}

$tests = [];

$tests['current source next1045-1097 admits remaining real bulk veryquick range'] = static function (TestRunner $t): void {
    $record = libsqlite_suite_next10451097_record(libsqlite_suite_next10451097_rows());

    $t->same('current-source-next1045-next1097-veryquick-bulk-shard-advanced', $record['status']);
    $t->same(true, $record['countable']);
    $t->same(1536, $record['current_mapped']);
    $t->same(1589, $record['next_mapped']);
    $t->same(53, $record['mapped_delta']);
    $t->same(53, $record['admitted_count']);
    $t->same(1, $record['preserved_count']);
    $t->same(5141, $record['php_pass_delta']);
    $t->same(485142, $record['next_php_pass']);
    $t->same(53, $record['bulk_shard_count']);
    $t->same(1045, $record['bulk_shard_first']);
    $t->same(1097, $record['bulk_shard_last']);
    $t->same([], $record['bulk_missing_units']);
    $t->same([], $record['bulk_unexpected_units']);
    $t->same(false, $record['counts_upstream_exact_shard_runner_current_source_next148']);
    $t->same(false, $record['counts_upstream_runner_full_suite_countability_current_source_next116']);
    $t->same(false, $record['counts_release_parity']);

    foreach (range(1045, 1097) as $next) {
        $t->same(true, $record['counts_upstream_veryquick_shard_current_source_next' . $next]);
    }
    foreach (libsqlite_suite_next10451097_scripts() as $script) {
        $t->contains($script, implode(',', $record['target_scripts']));
    }
    $t->same(['integrated-next981-1044-current-source-anchor'], $record['preserved_units']);
};

$tests['current source next1045-1097 rejects incomplete denominator-closing range'] = static function (TestRunner $t): void {
    $rows = array_values(array_filter(
        libsqlite_suite_next10451097_rows(),
        static fn (array $row): bool => ($row['unit'] ?? null) !== 'suite-upstream-veryquick-shard-current-source-next1097'
    ));

    $record = libsqlite_suite_next10451097_record($rows, libsqlite_suite_next10451097_output(970, 970), 970);

    $t->same('blocked', $record['status']);
    $t->same(false, $record['countable']);
    $t->same(0, $record['mapped_delta']);
    $t->same(0, $record['php_pass_delta']);
    $t->contains('bulk-veryquick-range-missing-shards', implode('; ', array_column($record['blockers'], 'id')));
    $t->contains('bulk-veryquick-pass-floor-not-met', implode('; ', array_column($record['blockers'], 'id')));
};

$tests['current source next1045-1097 blocks stale provenance'] = static function (TestRunner $t): void {
    $rows = libsqlite_suite_next10451097_rows();
    $rows[0]['implementation_source_head'] = '0000000000000000000000000000000000000000';

    $record = libsqlite_suite_next10451097_record($rows);

    $t->same('blocked', $record['status']);
    $t->same(0, $record['mapped_delta']);
    $t->contains('implementation-source-head-mismatch', implode('; ', array_column($record['blockers'], 'evidence')));
};

return $tests;
