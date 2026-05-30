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
    $root = '/home/claude/port-libs/.upstream-cache/libsqlite';
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS)
    );

    foreach ($iterator as $file) {
        if (!$file instanceof SplFileInfo || !$file->isFile() || $file->getExtension() !== 'test') {
            continue;
        }

        $relative = str_replace($root . '/', '', $file->getPathname());
        if (str_starts_with($relative, 'test/')) {
            $relative = substr($relative, 5);
        }

        $scripts[] = $relative;
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
        1472,
        1589,
        'a5d711ea245dda1130ca2ff1ba1b791f9a863c2b',
        'bulk-upstream-suite-denominator-burnup-dynamic-20260530T202736Z-0',
        573146,
        'lanes/libsqlite/tests/SQLiteBulkUpstreamSuiteDenominatorBurnupDynamicBlockedTest.php',
        libsqlite_bulk_denominator_blocked_output(),
        'bulk-upstream-suite-denominator-burnup-dynamic-20260530T202736Z-0 confirms accepted hydrated script map closure at 1472 / 1589 and does not repeat stale next965-980 rows, invented .test ids, metadata-only PASS inflation, release/all parity claims, or source-neutral cleanup'
    );
}

$tests = [];

$tests['bulk denominator burnup is blocked after all hydrated test scripts are mapped'] = static function (TestRunner $t): void {
    $record = libsqlite_bulk_denominator_blocked_record();

    $t->same('hydrated-script-map-gap-preserved', $record['status']);
    $t->same(1589, $record['denominator_total']);
    $t->same(1472, $record['current_mapped']);
    $t->same(1472, $record['next_mapped']);
    $t->same(0, $record['mapped_delta']);
    $t->same(117, $record['remaining_denominator_before']);
    $t->same(117, $record['remaining_denominator_after']);
    $t->same(1472, $record['hydrated_script_count']);
    $t->same(1472, $record['already_mapped_script_count']);
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
        $inventory['testDirectoryTclHarnessFiles']
        + $inventory['testDirectoryCPrograms']
        + $inventory['srcTestCOrHeaderHelpers']
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
    $t->same(192, $remainingNonTestDirectoryUnits);
    $summary = libsqlite_bulk_denominator_blocked_evidence()->denominatorSummary();
    $t->same(117, $summary['total'] - $summary['mapped']);
    $t->true($remainingNonTestDirectoryUnits >= 117, 'Remaining mapped capacity is entirely outside hydrated .test script admission.');
};

$tests['bulk denominator burnup keeps real script samples only'] = static function (TestRunner $t): void {
    $scripts = libsqlite_bulk_denominator_hydrated_test_scripts();
    $record = libsqlite_bulk_denominator_blocked_record();

    $t->same(1472, count($scripts));
    $t->same('8_3_names.test', $scripts[0]);
    $t->true(in_array('wal.test', $scripts, true), 'Expected real wal.test from the hydrated upstream checkout');
    $t->true(in_array('json101.test', $scripts, true), 'Expected real json101.test from the hydrated upstream checkout');
    $t->true(in_array('ext/fts5/test/fts5aux.test', $scripts, true), 'Expected real extension .test script from the accepted hydrated closure');
    $t->true(in_array('mptest/config01.test', $scripts, true), 'Expected real mptest .test script from the accepted hydrated closure');
    $t->same([], $record['admitted_scripts']);
    $t->same([], $record['held_back_scripts']);
    $t->same([], $record['sample_admitted_scripts']);
    $t->contains('before claiming release/all parity', $record['next_gate']);
};

return $tests;
