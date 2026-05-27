<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteUpstreamSuiteEvidence;

function libsqlite_tcl_next20_evidence(): SQLiteUpstreamSuiteEvidence
{
    return SQLiteUpstreamSuiteEvidence::fromManifestPath(__DIR__ . '/../UPSTREAM_TEST_MANIFEST.json');
}

/**
 * @param list<string> $patterns
 */
function libsqlite_tcl_next20_audit(
    string $label,
    string $head = 'accepted-head',
    string $testset = 'veryquick',
    array $patterns = ['json101.test'],
    ?int $exit = 0,
    ?int $tests = 100,
    ?int $errors = 0
): string {
    $patternText = $patterns === [] ? ' none' : ' `' . implode('` `', $patterns) . '`';
    $exitText = $exit === null ? '' : "- Exit: `{$exit}`\n";
    $testsText = $tests === null ? '' : "- Parsed tests: `{$tests}`\n";
    $errorsText = $errors === null ? '' : "- Parsed errors: `{$errors}`\n";

    return <<<MD
# SQLite Tcl Bounded Runner Evidence - {$label}

- Repository HEAD: `{$head}`
- SQLite git commit: `8f70ec615f4cd247d36f92a22c99f65ebbcc22a7`
- SQLite VERSION: `3.54.0`
- SQLite manifest UUID: `9ac4a33a2932d353c4871fd8e09c10addf827f1fc3fc9380037d738cf2cd0353`
- Scratch: `/tmp/libsqlite-next20`
- Log: `/tmp/libsqlite-next20.log`
- Testset: `{$testset}`
- Jobs: `2`
- Timeout seconds: `120`
- Patterns:{$patternText}

## Results

{$exitText}{$errorsText}{$testsText}- Runner time: `00:01`
MD;
}

$tests = [
    'parses current next20 focused artifact as passed' => static function (TestRunner $t): void {
        $record = libsqlite_tcl_next20_evidence()->boundedRunnerArtifactRecord(libsqlite_tcl_next20_audit('json-focused', tests: 812));

        $t->same('passed', $record['status']);
        $t->same('accepted-head', $record['repository_head']);
        $t->same(['json101.test'], $record['requested']['patterns']);
        $t->same(812, $record['results']['tests']);
    },
    'admits current next20 focused artifact without release parity credit' => static function (TestRunner $t): void {
        $record = libsqlite_tcl_next20_evidence()->boundedRunnerArtifactRecord(libsqlite_tcl_next20_audit('json-focused', tests: 812));
        $admission = libsqlite_tcl_next20_evidence()->focusedRunnerArtifactAdmission($record, 'accepted-head');

        $t->same('focused-evidence-countable', $admission['status']);
        $t->same(true, $admission['countable']);
        $t->same(false, $admission['counts_as_release_parity']);
        $t->same(812, $admission['tests']);
    },
    'blocks current next20 focused artifact with stale repository head' => static function (TestRunner $t): void {
        $record = libsqlite_tcl_next20_evidence()->boundedRunnerArtifactRecord(libsqlite_tcl_next20_audit('stale-json', 'stale-head'));
        $admission = libsqlite_tcl_next20_evidence()->focusedRunnerArtifactAdmission($record, 'accepted-head');

        $t->same('blocked', $admission['status']);
        $t->same(false, $admission['countable']);
        $t->same('repository-head-mismatch', $admission['blockers'][0]['id']);
    },
    'blocks broad all artifact from focused admission path' => static function (TestRunner $t): void {
        $record = libsqlite_tcl_next20_evidence()->boundedRunnerArtifactRecord(libsqlite_tcl_next20_audit('all-zero-error', testset: 'all', patterns: [], tests: 22000));
        $admission = libsqlite_tcl_next20_evidence()->focusedRunnerArtifactAdmission($record, 'accepted-head');

        $t->same('blocked', $admission['status']);
        $t->same('focused-patterns-missing', $admission['blockers'][0]['id']);
        $t->same(false, $admission['counts_as_release_parity']);
    },
    'routes broad all artifact through release countability gate instead' => static function (TestRunner $t): void {
        $record = libsqlite_tcl_next20_evidence()->boundedRunnerArtifactRecord(libsqlite_tcl_next20_audit('all-zero-error', testset: 'all', patterns: [], tests: 22000));
        $gate = libsqlite_tcl_next20_evidence()->boundedRunnerAcceptanceGate($record, 'accepted-head');

        $t->same('accepted-for-lane-evidence', $gate['status']);
        $t->same(22000, $gate['tests']);
        $t->same(0, $gate['errors']);
    },
    'blocks active broad artifact even when output is incomplete' => static function (TestRunner $t): void {
        $snapshot = '4321 4320 ./testfixture ../src/test/testrunner.tcl --jobs 2 --stop-on-error all';
        $record = libsqlite_tcl_next20_evidence()->boundedRunnerArtifactRecord(
            libsqlite_tcl_next20_audit('active-all', testset: 'all', patterns: [], exit: null, tests: null, errors: null),
            '06:40 tcl(3840/22000) r2 ETC 00:12',
            $snapshot
        );

        $t->same('active-runner-in-progress', $record['status']);
        $t->same('blocked-active-runner', $record['active_gate']['status']);
        $t->same(3840, $record['progress']['completed']);
    },
    'records timeout progress without counting release parity' => static function (TestRunner $t): void {
        $record = libsqlite_tcl_next20_evidence()->boundedRunnerArtifactRecord(
            libsqlite_tcl_next20_audit('release-timeout', testset: 'release', patterns: [], exit: 124, tests: null, errors: null),
            '06:40 tcl(18142/22000) r2 ETC 00:12'
        );
        $audit = libsqlite_tcl_next20_evidence()->boundedRunnerProgressAudit(['release-timeout' => $record]);

        $t->same('timed-out-incomplete', $record['status']);
        $t->same('blocked-progress-only', $audit['status']);
        $t->same(false, $audit['counts_as_release_parity']);
        $t->same(82.46, $audit['entries'][0]['progress_percent']);
    },
    'summarizes mixed next20 artifacts for rerun gating' => static function (TestRunner $t): void {
        $evidence = libsqlite_tcl_next20_evidence();
        $passed = $evidence->boundedRunnerArtifactRecord(libsqlite_tcl_next20_audit('json-focused', tests: 812));
        $failed = $evidence->boundedRunnerArtifactRecord(libsqlite_tcl_next20_audit('wal-failed', patterns: ['wal.test'], exit: 1, tests: 300, errors: 1));
        $timeout = $evidence->boundedRunnerArtifactRecord(libsqlite_tcl_next20_audit('release-timeout', testset: 'release', patterns: [], exit: 124, tests: null, errors: null), '06:40 tcl(11/20) r2 ETC 00:12');

        $audit = $evidence->boundedRunnerProgressAudit([
            'json-focused' => $passed,
            'wal-failed' => $failed,
            'release-timeout' => $timeout,
        ]);

        $t->same('blocked-progress-only', $audit['status']);
        $t->same(1, $audit['passed_count']);
        $t->same(1, $audit['failed_count']);
        $t->same(1, $audit['timed_out_count']);
        $t->same(1112, $audit['tests_total']);
    },
];

$focusedGroups = [
    'json-next20' => ['json101.test', 'json102.test', 'jsonb01.test'],
    'wal-next20' => ['wal.test', 'wal2.test', 'savepoint.test'],
    'pager-next20' => ['pager1.test', 'journal1.test'],
    'btree-next20' => ['btree01.test', 'delete.test'],
    'encoding-next20' => ['enc.test', 'collate1.test', 'like.test'],
    'pragma-next20' => ['pragma.test', 'pragma2.test'],
    'select-next20' => ['select1.test', 'select2.test'],
    'trigger-next20' => ['trigger1.test', 'trigger2.test'],
    'fk-next20' => ['fkey1.test', 'fkey2.test'],
    'vacuum-next20' => ['vacuum.test', 'avtrans.test'],
];

foreach ($focusedGroups as $label => $patterns) {
    $tests['admits concrete focused Tcl pattern group ' . $label] = static function (TestRunner $t) use ($label, $patterns): void {
        $record = libsqlite_tcl_next20_evidence()->boundedRunnerArtifactRecord(libsqlite_tcl_next20_audit($label, patterns: $patterns, tests: count($patterns) * 100));
        $admission = libsqlite_tcl_next20_evidence()->focusedRunnerArtifactAdmission($record, 'accepted-head');

        $t->same('focused-evidence-countable', $admission['status']);
        $t->same(count($patterns), $admission['pattern_count']);
        $t->same($patterns, $admission['patterns']);
    };

    $tests['indexes focused Tcl pattern group in artifact set ' . $label] = static function (TestRunner $t) use ($label, $patterns): void {
        $record = libsqlite_tcl_next20_evidence()->boundedRunnerArtifactRecord(libsqlite_tcl_next20_audit($label, patterns: $patterns, tests: count($patterns) * 100));
        $set = libsqlite_tcl_next20_evidence()->focusedRunnerArtifactSetAdmission([$label => $record], 'accepted-head');
        $expectedScripts = $patterns;
        sort($expectedScripts);

        $t->same('focused-evidence-countable', $set['status']);
        $t->same(1, $set['countable_count']);
        $t->same(count($patterns), $set['unique_script_count']);
        $t->same($expectedScripts, $set['unique_scripts']);
    };

    $tests['blocks stale focused Tcl pattern group ' . $label] = static function (TestRunner $t) use ($label, $patterns): void {
        $record = libsqlite_tcl_next20_evidence()->boundedRunnerArtifactRecord(libsqlite_tcl_next20_audit($label, 'old-head', patterns: $patterns, tests: count($patterns) * 100));
        $set = libsqlite_tcl_next20_evidence()->focusedRunnerArtifactSetAdmission([$label => $record], 'accepted-head');

        $t->same('blocked', $set['status']);
        $t->same(1, $set['stale_head_count']);
        $t->same(['repository-head-mismatch'], $set['entries'][0]['blocker_ids']);
    };

    $tests['keeps failed focused Tcl pattern group uncounted ' . $label] = static function (TestRunner $t) use ($label, $patterns): void {
        $record = libsqlite_tcl_next20_evidence()->boundedRunnerArtifactRecord(libsqlite_tcl_next20_audit($label, patterns: $patterns, exit: 1, tests: count($patterns) * 100, errors: 1));
        $set = libsqlite_tcl_next20_evidence()->focusedRunnerArtifactSetAdmission([$label => $record], 'accepted-head');

        $t->same('blocked', $set['status']);
        $t->same(1, $set['failed_count']);
        $t->same(0, $set['countable_count']);
    };

    $tests['preserves accepted-head provenance for focused Tcl pattern group ' . $label] = static function (TestRunner $t) use ($label, $patterns): void {
        $record = libsqlite_tcl_next20_evidence()->boundedRunnerArtifactRecord(libsqlite_tcl_next20_audit($label, patterns: $patterns, tests: count($patterns) * 100));
        $batch = libsqlite_tcl_next20_evidence()->acceptedHeadArtifactProvenanceBatch([$label => $record], 'accepted-head');

        $t->same('all-current-accepted-head', $batch['status']);
        $t->same(1, $batch['current_accepted_count']);
        $t->same(0, $batch['blocked_count']);
        $t->same($label, $batch['entries'][0]['label']);
    };
}

return $tests;
