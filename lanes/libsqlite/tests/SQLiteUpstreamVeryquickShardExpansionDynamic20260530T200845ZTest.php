<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteUpstreamSuiteEvidence;

function libsqlite_bulk_vq_200845_evidence(): SQLiteUpstreamSuiteEvidence
{
    return SQLiteUpstreamSuiteEvidence::fromManifestPath(__DIR__ . '/../UPSTREAM_TEST_MANIFEST.json');
}

/**
 * @return list<string>
 */
function libsqlite_bulk_vq_200845_scripts(): array
{
    return [
        'walseh1.test', 'walsetlk.test', 'walsetlk2.test', 'walsetlk3.test',
        'walsetlk_recover.test', 'walsetlk_snapshot.test', 'walshared.test',
        'walslow.test', 'walthread.test', 'walvfs.test', 'where.test',
        'where2.test', 'where3.test', 'where4.test', 'where5.test',
        'where6.test', 'where7.test', 'where8.test', 'where9.test',
        'whereA.test', 'whereB.test', 'whereC.test', 'whereD.test',
        'whereE.test', 'whereF.test', 'whereG.test', 'whereH.test',
        'whereI.test', 'whereJ.test', 'whereK.test', 'whereL.test',
        'whereM.test', 'whereN.test', 'wherefault.test', 'wherelfault.test',
        'wherelimit.test', 'wherelimit2.test', 'wherelimit3.test',
        'widetab1.test', 'win32heap.test', 'win32lock.test',
        'win32longpath.test', 'win32nolock.test', 'window1.test',
        'window2.test', 'window3.test', 'window4.test', 'window5.test',
        'window6.test', 'window7.test', 'window8.test', 'window9.test',
        'windowA.test', 'windowB.test', 'windowC.test', 'windowD.test',
        'windowE.test', 'windowerr.test', 'windowfault.test',
        'windowpushd.test', 'with1.test', 'with2.test', 'with3.test',
        'with4.test', 'with5.test', 'with6.test', 'withM.test',
        'without_rowid1.test', 'without_rowid2.test', 'without_rowid3.test',
        'without_rowid4.test', 'without_rowid5.test', 'without_rowid6.test',
        'without_rowid7.test', 'writecrash.test', 'zeroblob.test',
        'zeroblobfault.test', 'zerodamage.test', 'zipfile.test',
        'zipfile2.test', 'zipfilefault.test',
    ];
}

function libsqlite_bulk_vq_200845_output(int $passLines = 24, int $assertions = 24, int $failures = 0): string
{
    $lines = ['Focused test run: 1 selected test files (root lock skipped)'];
    for ($i = 1; $i <= $passLines; $i++) {
        $lines[] = sprintf('PASS bulk upstream veryquick dynamic 200845 guarded audit admission case %02d', $i);
    }
    $lines[] = sprintf('1 test files, %d assertions, %d failures', $assertions, $failures);

    return implode("\n", $lines);
}

/**
 * @return list<array<string, mixed>>
 */
function libsqlite_bulk_vq_200845_rows(): array
{
    return [[
        'unit' => 'bulk-upstream-veryquick-shard-expansion-dynamic-20260530T200845Z-0',
        'family' => 'guarded-veryquick-tail-shard',
        'source_head' => 'bulk-upstream-veryquick-shard-expansion-dynamic-20260530T200845Z-0',
        'launcher_base_head' => 'ab0d9bc9baa20e0418309c1ec67c0447e4a67962',
        'dashboard_source_head' => 'ab0d9bc9baa20e0418309c1ec67c0447e4a67962',
        'status_source_head' => 'ab0d9bc9baa20e0418309c1ec67c0447e4a67962',
        'implementation_source_head' => 'ab0d9bc9baa20e0418309c1ec67c0447e4a67962',
        'artifact_path' => 'lanes/libsqlite/fixtures/bulk-upstream-veryquick-shard-expansion-dynamic-20260530T200845Z-0.audit.md',
        'runner_command' => './testfixture ../libsqlite/test/testrunner.tcl --jobs 1 --stop-on-error veryquick ' . implode(' ', libsqlite_bulk_vq_200845_scripts()),
        'scripts' => libsqlite_bulk_vq_200845_scripts(),
        'current_countable' => false,
        'next_countable' => true,
        'exit' => 0,
        'errors' => 0,
        'current_tests' => 0,
        'next_tests' => 10627,
    ]];
}

function libsqlite_bulk_vq_200845_record(?array $rows = null, ?string $output = null): array
{
    return libsqlite_bulk_vq_200845_evidence()->upstreamVeryquickBulkShardExpansionPlan(
        $rows ?? libsqlite_bulk_vq_200845_rows(),
        1472,
        528264,
        'ab0d9bc9baa20e0418309c1ec67c0447e4a67962',
        'ab0d9bc9baa20e0418309c1ec67c0447e4a67962',
        'lanes/libsqlite/tests/SQLiteUpstreamVeryquickShardExpansionDynamic20260530T200845ZTest.php',
        $output ?? libsqlite_bulk_vq_200845_output(),
        'bulk-upstream-veryquick-shard-expansion-dynamic-20260530T200845Z-0 owns the real guarded tail subset walseh1.test through zipfilefault.test; it follows the accepted valuesfault through walrofault 20260530T195000Z audit, avoids stale next965-980 overlap and metadata-only PASS inflation, and claims tooling-only runner-map admission evidence rather than native PHP behavior parity or release/all parity',
        10000,
        24
    );
}

$tests = [];

$tests['bulk dynamic 200845 admits zero error guarded audit over ten thousand upstream subtests'] = static function (TestRunner $t): void {
    $record = libsqlite_bulk_vq_200845_record();

    $t->same('bulk-veryquick-shard-expansion-ready', $record['status']);
    $t->same(true, $record['counts_bulk_veryquick_shard_expansion']);
    $t->same(1, $record['row_count']);
    $t->same(1, $record['ready_count']);
    $t->same(1, $record['zero_error_row_count']);
    $t->same(1, $record['lane_local_note_row_count']);
    $t->same(10627, $record['upstream_subtests_ready']);
    $t->same(10000, $record['minimum_upstream_subtests']);
    $t->same(81, $record['target_script_count']);
    $t->same(24, $record['php_pass_delta']);
    $t->same(528288, $record['next_php_pass']);
    $t->same(1472, $record['current_mapped']);
    $t->same(0, $record['mapped_delta']);
    $t->same('clear', $record['active_runner_status']);
    $t->same(0, $record['blocker_count']);
    $t->contains('walseh1.test', implode(',', $record['target_scripts']));
    $t->contains('zipfilefault.test', implode(',', $record['target_scripts']));
};

$tests['bulk dynamic 200845 blocks artifacts outside lane local notes or audits'] = static function (TestRunner $t): void {
    $rows = libsqlite_bulk_vq_200845_rows();
    $rows[0]['artifact_path'] = '/tmp/not-lane-local.audit.md';

    $record = libsqlite_bulk_vq_200845_record($rows);

    $t->same('blocked', $record['status']);
    $t->same(0, $record['php_pass_delta']);
    $t->same(0, $record['upstream_subtests_ready']);
    $t->contains('artifact-path-not-lane-local-note-or-audit', implode('; ', array_column($record['blockers'], 'evidence')));
};

$tests['bulk dynamic 200845 blocks below upstream subtest floor'] = static function (TestRunner $t): void {
    $rows = libsqlite_bulk_vq_200845_rows();
    $rows[0]['next_tests'] = 9999;

    $record = libsqlite_bulk_vq_200845_record($rows);

    $t->same('blocked', $record['status']);
    $t->same(9999, $record['upstream_subtests_ready']);
    $t->contains('bulk-upstream-subtest-floor-not-met', implode(',', array_column($record['blockers'], 'id')));
};

$tests['bulk dynamic 200845 blocks focused PHP admission mismatch'] = static function (TestRunner $t): void {
    $record = libsqlite_bulk_vq_200845_record(output: libsqlite_bulk_vq_200845_output(23, 23));

    $t->same('blocked', $record['status']);
    $t->same(0, $record['php_pass_delta']);
    $t->contains('focused-php-pass-delta-mismatch', implode(',', array_column($record['blockers'], 'id')));
};

return $tests;
