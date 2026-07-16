<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteUpstreamSuiteEvidence;

function libsqlite_runner_map_gap_closure_evidence(): SQLiteUpstreamSuiteEvidence
{
    return SQLiteUpstreamSuiteEvidence::fromManifestPath(__DIR__ . '/../UPSTREAM_TEST_MANIFEST.json');
}

/**
 * @return list<string>
 */
function libsqlite_runner_map_gap_closure_hydrated_scripts(): array
{
    $scripts = [];
    foreach (glob('/home/claude/port-libs/.upstream-cache/libsqlite/test/*.test') ?: [] as $path) {
        $scripts[] = basename($path);
    }

    sort($scripts, SORT_STRING);

    return $scripts;
}

function libsqlite_runner_map_gap_closure_output(int $passLines = 82, int $assertions = 82, int $failures = 0): string
{
    $lines = ['Focused test run: 1 selected test files (root lock skipped)'];
    for ($i = 1; $i <= $passLines; $i++) {
        $lines[] = sprintf('PASS hydrated runner map gap closure dynamic case %02d', $i);
    }
    $lines[] = sprintf('1 test files, %d assertions, %d failures', $assertions, $failures);

    return implode("\n", $lines);
}

/**
 * @param list<string>|null $hydrated
 * @param list<string>|null $mapped
 * @return array<string, mixed>
 */
function libsqlite_runner_map_gap_closure_record(?array $hydrated = null, ?array $mapped = null, string $snapshot = '', ?string $output = null): array
{
    $hydrated ??= libsqlite_runner_map_gap_closure_hydrated_scripts();
    $mapped ??= array_slice($hydrated, 0, 958);

    return libsqlite_runner_map_gap_closure_evidence()->upstreamRunnerHydratedScriptMapGapClosure(
        $hydrated,
        $mapped,
        958,
        1589,
        'e12ceba2fd83282957420709bd781aee710bc7ca',
        'bulk-upstream-runner-map-gap-closure-dynamic-20260530T173708Z-0',
        218357,
        'lanes/libsqlite/tests/SQLiteUpstreamRunnerMapGapClosureDynamicTest.php',
        $output ?? libsqlite_runner_map_gap_closure_output(),
        'bulk-upstream-runner-map-gap-closure-dynamic uses the hydrated SQLite upstream test directory and avoids stale next965-980 overlap, fabricated script ids, release/all parity claims, source-neutral cleanup, and behavior micro-test PASS inflation',
        $snapshot
    );
}

$tests = [];

$tests['dynamic runner map gap closure uses hydrated upstream cache'] = static function (TestRunner $t): void {
    $scripts = libsqlite_runner_map_gap_closure_hydrated_scripts();

    $t->true(count($scripts) >= 1000, 'Expected at least 1000 real SQLite test/*.test scripts in the hydrated cache');
    $t->same('8_3_names.test', $scripts[0]);
    $t->true(in_array('wal.test', $scripts, true), 'Expected wal.test from upstream test directory');
    $t->true(in_array('json101.test', $scripts, true), 'Expected json101.test from upstream test directory');
};

$tests['dynamic runner map gap closure advances real script denominator rows'] = static function (TestRunner $t): void {
    $record = libsqlite_runner_map_gap_closure_record();

    $t->same('hydrated-script-map-gap-advanced', $record['status']);
    $t->same(1589, $record['denominator_total']);
    $t->same(958, $record['current_mapped']);
    $t->same(1189, $record['next_mapped']);
    $t->same(231, $record['mapped_delta']);
    $t->same(631, $record['remaining_denominator_before']);
    $t->same(400, $record['remaining_denominator_after']);
    $t->same(1189, $record['hydrated_script_count']);
    $t->same(958, $record['already_mapped_script_count']);
    $t->same(231, $record['missing_script_count']);
    $t->same(231, $record['admitted_script_count']);
    $t->same(0, $record['held_back_script_count']);
    $t->same(true, $record['counts_mapped_denominator_growth']);
    $t->same(false, $record['counts_release_parity']);
};

$tests['dynamic runner map gap closure records concrete upstream script samples'] = static function (TestRunner $t): void {
    $record = libsqlite_runner_map_gap_closure_record();

    $t->same('tkt3757.test', $record['admitted_scripts'][0]);
    $t->same('zipfilefault.test', $record['admitted_scripts'][230]);
    $t->same(array_slice($record['admitted_scripts'], 0, 20), $record['sample_missing_scripts']);
    $t->same(array_slice($record['admitted_scripts'], 0, 20), $record['sample_admitted_scripts']);
    $t->true(in_array('triggerE.test', $record['admitted_scripts'], true), 'Expected a real trigger upstream script in the admitted gap');
    $t->true(in_array('wal2.test', $record['admitted_scripts'], true), 'Expected a real WAL upstream script in the admitted gap');
};

$tests['dynamic runner map gap closure admits focused php evidence without pass growth claim'] = static function (TestRunner $t): void {
    $record = libsqlite_runner_map_gap_closure_record();

    $t->same('admitted', $record['php_pass_admission']['status']);
    $t->same(82, $record['php_pass_delta']);
    $t->same(218439, $record['next_php_pass']);
    $t->same(false, $record['counts_pass_line_growth']);
    $t->same(0, $record['blocker_count']);
    $t->contains('real upstream .test filenames', $record['dependency_closure']);
};

$tests['dynamic runner map gap closure preserves when all hydrated scripts are already mapped'] = static function (TestRunner $t): void {
    $hydrated = libsqlite_runner_map_gap_closure_hydrated_scripts();
    $record = libsqlite_runner_map_gap_closure_record($hydrated, $hydrated);

    $t->same('hydrated-script-map-gap-preserved', $record['status']);
    $t->same(0, $record['mapped_delta']);
    $t->same(958, $record['next_mapped']);
    $t->same(0, $record['missing_script_count']);
    $t->same(false, $record['counts_mapped_denominator_growth']);
};

$tests['dynamic runner map gap closure caps movement at remaining denominator'] = static function (TestRunner $t): void {
    $hydrated = libsqlite_runner_map_gap_closure_hydrated_scripts();
    $mapped = array_slice($hydrated, 0, 500);
    $record = libsqlite_runner_map_gap_closure_record($hydrated, $mapped);

    $t->same(631, $record['mapped_delta']);
    $t->same(1589, $record['next_mapped']);
    $t->same('hydrated-script-map-gap-closed', $record['status']);
    $t->same(0, $record['remaining_denominator_after']);
    $t->same(58, $record['held_back_script_count']);
};

$tests['dynamic runner map gap closure blocks fake scripts and wildcard patterns'] = static function (TestRunner $t): void {
    $hydrated = libsqlite_runner_map_gap_closure_hydrated_scripts();
    $badHydrated = $hydrated;
    $badHydrated[] = 'json*.test';
    $mapped = array_slice($hydrated, 0, 958);
    $mapped[] = 'fake-generated-suite-row.test';
    $mapped[] = 'not-hydrated-real-looking.test';

    $record = libsqlite_runner_map_gap_closure_record($badHydrated, $mapped);

    $t->same('blocked', $record['status']);
    $t->same(0, $record['mapped_delta']);
    $t->same(958, $record['next_mapped']);
    $evidence = implode('; ', array_column($record['blockers'], 'evidence'));
    $t->contains('json*.test', $evidence);
    $t->contains('fake-generated-suite-row.test', $evidence);
    $t->contains('not-hydrated-real-looking.test', $evidence);
};

$tests['dynamic runner map gap closure blocks duplicate broad runners'] = static function (TestRunner $t): void {
    $record = libsqlite_runner_map_gap_closure_record(snapshot: '999 1 R 00:09 ./testfixture ../libsqlite/test/testrunner.tcl --jobs 4 release');

    $t->same('blocked', $record['status']);
    $t->same(0, $record['mapped_delta']);
    $t->same('duplicate-broad-runner-active', $record['blockers'][0]['id']);
};

$tests['dynamic runner map gap closure blocks unfocused php output'] = static function (TestRunner $t): void {
    $record = libsqlite_runner_map_gap_closure_record(output: "1 test files, 82 assertions, 0 failures\n");

    $t->same('blocked', $record['status']);
    $t->same(0, $record['php_pass_delta']);
    $t->same('focused-php-pass-admission-blocked', $record['blockers'][0]['id']);
};

$tests['dynamic runner map gap closure rejects invalid setup'] = static function (TestRunner $t): void {
    $evidence = libsqlite_runner_map_gap_closure_evidence();

    $t->throws(InvalidArgumentException::class, static fn () => $evidence->upstreamRunnerHydratedScriptMapGapClosure([], [], 958, 1589, 'head', 'next', 218357, 'lanes/libsqlite/tests/SQLiteUpstreamRunnerMapGapClosureDynamicTest.php', libsqlite_runner_map_gap_closure_output(), 'non-overlap'));
    $t->throws(InvalidArgumentException::class, static fn () => $evidence->upstreamRunnerHydratedScriptMapGapClosure(['wal.test'], [], 1590, 1589, 'head', 'next', 218357, 'lanes/libsqlite/tests/SQLiteUpstreamRunnerMapGapClosureDynamicTest.php', libsqlite_runner_map_gap_closure_output(), 'non-overlap'));
    $t->throws(InvalidArgumentException::class, static fn () => $evidence->upstreamRunnerHydratedScriptMapGapClosure(['wal.test'], [], 958, 1589, '', 'next', 218357, 'lanes/libsqlite/tests/SQLiteUpstreamRunnerMapGapClosureDynamicTest.php', libsqlite_runner_map_gap_closure_output(), 'non-overlap'));
};

return $tests;
