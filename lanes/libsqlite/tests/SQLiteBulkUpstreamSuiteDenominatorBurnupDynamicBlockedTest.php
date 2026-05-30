<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteUpstreamSuiteEvidence;

function libsqlite_bulk_denominator_blocked_evidence(): SQLiteUpstreamSuiteEvidence
{
    return SQLiteUpstreamSuiteEvidence::fromManifestPath(__DIR__ . '/../UPSTREAM_TEST_MANIFEST.json');
}

/**
 * @return list<string>
 */
function libsqlite_bulk_denominator_hydrated_test_scripts(): array
{
    $scripts = [];
    foreach (glob('/home/claude/port-libs/.upstream-cache/libsqlite/test/*.test') ?: [] as $path) {
        $scripts[] = basename($path);
    }

    sort($scripts, SORT_STRING);

    return $scripts;
}

function libsqlite_bulk_denominator_blocked_output(int $passLines = 24, int $assertions = 24, int $failures = 0): string
{
    $lines = ['Focused test run: 1 selected test files (root lock skipped)'];
    for ($i = 1; $i <= $passLines; $i++) {
        $lines[] = sprintf('PASS bulk upstream denominator blocked audit case %02d', $i);
    }
    $lines[] = sprintf('1 test files, %d assertions, %d failures', $assertions, $failures);

    return implode("\n", $lines);
}

/**
 * @return array<string, mixed>
 */
function libsqlite_bulk_denominator_blocked_record(): array
{
    $scripts = libsqlite_bulk_denominator_hydrated_test_scripts();

    return libsqlite_bulk_denominator_blocked_evidence()->upstreamRunnerHydratedScriptMapGapClosure(
        $scripts,
        $scripts,
        1189,
        1589,
        'f66597de21a7c168178b6eec67c6e12b5daf324d',
        'bulk-upstream-suite-denominator-burnup-dynamic-20260530T175933Z-0',
        223524,
        'lanes/libsqlite/tests/SQLiteBulkUpstreamSuiteDenominatorBurnupDynamicBlockedTest.php',
        libsqlite_bulk_denominator_blocked_output(),
        'bulk-upstream-suite-denominator-burnup-dynamic-20260530T175933Z-0 rebases after accepted hydrated script map closure and does not repeat stale next965-980 rows, invented .test ids, metadata-only PASS inflation, release/all parity claims, or source-neutral cleanup'
    );
}

$tests = [];

$tests['bulk denominator burnup is blocked after all hydrated test scripts are mapped'] = static function (TestRunner $t): void {
    $record = libsqlite_bulk_denominator_blocked_record();

    $t->same('hydrated-script-map-gap-preserved', $record['status']);
    $t->same(1589, $record['denominator_total']);
    $t->same(1189, $record['current_mapped']);
    $t->same(1189, $record['next_mapped']);
    $t->same(0, $record['mapped_delta']);
    $t->same(400, $record['remaining_denominator_before']);
    $t->same(400, $record['remaining_denominator_after']);
    $t->same(1189, $record['hydrated_script_count']);
    $t->same(1189, $record['already_mapped_script_count']);
    $t->same(0, $record['missing_script_count']);
    $t->same(0, $record['admitted_script_count']);
    $t->same(false, $record['counts_mapped_denominator_growth']);
    $t->same(false, $record['counts_pass_line_growth']);
    $t->same(false, $record['counts_release_parity']);
};

$tests['bulk denominator burnup identifies remaining non test script blocker'] = static function (TestRunner $t): void {
    $summary = libsqlite_bulk_denominator_blocked_evidence()->denominatorSummary();
    $inventory = $summary['inventory_units'];
    $remainingNonTestDirectoryUnits =
        $inventory['extensionTclTests']
        + $inventory['extensionNestedTclTests']
        + $inventory['testDirectoryTclHarnessFiles']
        + $inventory['testDirectoryCPrograms']
        + $inventory['srcTestCOrHeaderHelpers']
        + $inventory['mptestFiles']
        + $inventory['toolTestPrograms']
        + $inventory['toolTestishFiles'];

    $t->same(1189, $inventory['testDirectoryTclTests']);
    $t->same(278, $inventory['extensionTclTests']);
    $t->same(146, $inventory['extensionNestedTclTests']);
    $t->same(32, $inventory['testDirectoryTclHarnessFiles']);
    $t->same(33, $inventory['testDirectoryCPrograms']);
    $t->same(47, $inventory['srcTestCOrHeaderHelpers']);
    $t->same(6, $inventory['mptestFiles']);
    $t->same(4, $inventory['toolTestPrograms']);
    $t->same(76, $inventory['toolTestishFiles']);
    $t->same(622, $remainingNonTestDirectoryUnits);
    $t->true($remainingNonTestDirectoryUnits >= 400, 'Remaining mapped capacity is entirely outside hydrated test/*.test script admission.');
};

$tests['bulk denominator burnup keeps real script samples only'] = static function (TestRunner $t): void {
    $scripts = libsqlite_bulk_denominator_hydrated_test_scripts();
    $record = libsqlite_bulk_denominator_blocked_record();

    $t->same(1189, count($scripts));
    $t->same('8_3_names.test', $scripts[0]);
    $t->true(in_array('wal.test', $scripts, true), 'Expected real wal.test from the hydrated upstream checkout');
    $t->true(in_array('json101.test', $scripts, true), 'Expected real json101.test from the hydrated upstream checkout');
    $t->same([], $record['admitted_scripts']);
    $t->same([], $record['held_back_scripts']);
    $t->same([], $record['sample_admitted_scripts']);
    $t->contains('before claiming release/all parity', $record['next_gate']);
};

return $tests;
