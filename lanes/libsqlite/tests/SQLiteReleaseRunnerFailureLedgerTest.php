<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteUpstreamSuiteEvidence;

function libsqlite_release_failure_evidence(): SQLiteUpstreamSuiteEvidence
{
    return SQLiteUpstreamSuiteEvidence::fromManifestPath(__DIR__ . '/../UPSTREAM_TEST_MANIFEST.json');
}

function libsqlite_release_failure_artifact(
    string $head,
    string $script,
    string $case,
    string $label = 'release-failure',
    string $diagnostic = 'runtime error: applying non-zero offset to null pointer',
    string $manifestUuid = '9ac4a33a2932d353c4871fd8e09c10addf827f1fc3fc9380037d738cf2cd0353',
    string $testset = 'release'
): array {
    $audit = "# SQLite Tcl Bounded Runner Evidence - {$label}\n\n"
        . "- Repository HEAD: `{$head}`\n"
        . "- SQLite git commit: `8f70ec615f4cd247d36f92a22c99f65ebbcc22a7`\n"
        . "- SQLite VERSION: `3.54.0`\n"
        . "- SQLite manifest UUID: `{$manifestUuid}`\n"
        . "- Scratch: `/tmp/libsqlite-release-failure38`\n"
        . "- Log: `/tmp/libsqlite-release-failure38.log`\n"
        . "- Testset: `{$testset}`\n"
        . "- Jobs: `2`\n"
        . "- Timeout seconds: `7200`\n"
        . "- Patterns: `none`\n"
        . "- Exit: `1`\n"
        . "- Elapsed seconds: `19`\n"
        . "- Parsed errors: `1`\n"
        . "- Parsed tests: `2048`\n"
        . "FAILED: runtime {$script}\n"
        . "{$case}... failed\n"
        . "runtime error: {$diagnostic}\n";

    return libsqlite_release_failure_evidence()->boundedRunnerArtifactRecord($audit);
}

function libsqlite_release_failure_focused(string $head, string $script, string $case): array
{
    $audit = "# SQLite Tcl Bounded Runner Evidence - focused-repro\n\n"
        . "- Repository HEAD: `{$head}`\n"
        . "- SQLite git commit: `8f70ec615f4cd247d36f92a22c99f65ebbcc22a7`\n"
        . "- SQLite VERSION: `3.54.0`\n"
        . "- SQLite manifest UUID: `9ac4a33a2932d353c4871fd8e09c10addf827f1fc3fc9380037d738cf2cd0353`\n"
        . "- Scratch: `/tmp/libsqlite-release-failure38-focused`\n"
        . "- Log: `/tmp/libsqlite-release-failure38-focused.log`\n"
        . "- Testset: `veryquick`\n"
        . "- Jobs: `1`\n"
        . "- Timeout seconds: `900`\n"
        . "- Patterns: `{$script}`\n"
        . "- Exit: `0`\n"
        . "- Parsed errors: `0`\n"
        . "- Parsed tests: `17`\n";

    return libsqlite_release_failure_evidence()->focusedFailureReproGate(
        [
            'script' => $script,
            'case' => $case,
            'category' => 'upstream-runtime-environment',
        ],
        $head,
        dirname(__DIR__, 3),
        $audit
    );
}

$currentHead38 = '28488284c6b42b08db024e7e34c788f71b24a201';
$nextHead38 = '1292ee3f49884533653e2546fdde2d89a8c0235f';

return [
    'current next38 preserves matching runtime release failure as blocker evidence' => static function (TestRunner $t) use ($currentHead38, $nextHead38): void {
        $current = [libsqlite_release_failure_artifact($currentHead38, 'fts5aux.test', 'fts5aux-3.1', 'current')];
        $next = [libsqlite_release_failure_artifact($nextHead38, 'fts5aux.test', 'fts5aux-3.1', 'next')];
        $focused = libsqlite_release_failure_focused($nextHead38, 'fts5aux.test', 'fts5aux-3.1');

        $ledger = libsqlite_release_failure_evidence()->releaseRunnerFailureLedger($current, $next, $focused, $currentHead38, $nextHead38);

        $t->same('next-failure-preserved', $ledger['status']);
        $t->same($currentHead38, $ledger['current_accepted_head']);
        $t->same($nextHead38, $ledger['next_accepted_head']);
        $t->same(1, $ledger['current_artifact_count']);
        $t->same(1, $ledger['next_artifact_count']);
        $t->same(1, $ledger['current_countable_failure_count']);
        $t->same(1, $ledger['next_countable_failure_count']);
        $t->same(1, $ledger['preserved_failure_count']);
        $t->same(0, $ledger['resolved_failure_count']);
        $t->same(0, $ledger['new_failure_count']);
        $t->same(['fts5aux.test#fts5aux-3.1#upstream-runtime-environment'], $ledger['preserved_failure_keys']);
        $t->same([], $ledger['resolved_failure_keys']);
        $t->same([], $ledger['new_failure_keys']);
        $t->same('fts5aux.test', $ledger['focused_script']);
        $t->same('fts5aux-3.1', $ledger['focused_case']);
        $t->same(1, $ledger['matching_focused_next_failure_count']);
        $t->same(0, $ledger['blocker_count']);
        $t->same(false, $ledger['counts_as_release_parity']);
        $t->same(true, $ledger['counts_as_blocker_evidence']);
        $t->contains('release/all parity remains uncounted', $ledger['next_gate']);
        $t->contains('no new support component needed', $ledger['dependency_closure']);
    },
    'current next38 reports resolved failure when next accepted head has no failed artifact' => static function (TestRunner $t) use ($currentHead38, $nextHead38): void {
        $current = [libsqlite_release_failure_artifact($currentHead38, 'fts5aux.test', 'fts5aux-3.1', 'current')];
        $focused = libsqlite_release_failure_focused($nextHead38, 'fts5aux.test', 'fts5aux-3.1');

        $ledger = libsqlite_release_failure_evidence()->releaseRunnerFailureLedger($current, [], $focused, $currentHead38, $nextHead38);

        $t->same('next-failure-resolved', $ledger['status']);
        $t->same(1, $ledger['resolved_failure_count']);
        $t->same(['fts5aux.test#fts5aux-3.1#upstream-runtime-environment'], $ledger['resolved_failure_keys']);
        $t->same(0, $ledger['next_countable_failure_count']);
        $t->same(false, $ledger['counts_as_blocker_evidence']);
        $t->contains('next zero-failure artifact', $ledger['next_gate']);
    },
    'current next38 reports expanded ledger for a new next release failure' => static function (TestRunner $t) use ($currentHead38, $nextHead38): void {
        $current = [libsqlite_release_failure_artifact($currentHead38, 'fts5aux.test', 'fts5aux-3.1', 'current')];
        $next = [
            libsqlite_release_failure_artifact($nextHead38, 'fts5aux.test', 'fts5aux-3.1', 'next-a'),
            libsqlite_release_failure_artifact($nextHead38, 'json101.test', 'json101-9.2', 'next-b'),
        ];
        $focused = libsqlite_release_failure_focused($nextHead38, 'fts5aux.test', 'fts5aux-3.1');

        $ledger = libsqlite_release_failure_evidence()->releaseRunnerFailureLedger($current, $next, $focused, $currentHead38, $nextHead38);

        $t->same('next-failure-ledger-expanded', $ledger['status']);
        $t->same(1, $ledger['preserved_failure_count']);
        $t->same(0, $ledger['resolved_failure_count']);
        $t->same(1, $ledger['new_failure_count']);
        $t->same(['json101.test#json101-9.2#upstream-runtime-environment'], $ledger['new_failure_keys']);
        $t->same(2, $ledger['next_countable_failure_count']);
        $t->same(1, $ledger['matching_focused_next_failure_count']);
    },
    'current next38 blocks stale next failure artifacts' => static function (TestRunner $t) use ($currentHead38, $nextHead38): void {
        $current = [libsqlite_release_failure_artifact($currentHead38, 'fts5aux.test', 'fts5aux-3.1', 'current')];
        $next = [libsqlite_release_failure_artifact('stale-head', 'fts5aux.test', 'fts5aux-3.1', 'next-stale')];
        $focused = libsqlite_release_failure_focused($nextHead38, 'fts5aux.test', 'fts5aux-3.1');

        $ledger = libsqlite_release_failure_evidence()->releaseRunnerFailureLedger($current, $next, $focused, $currentHead38, $nextHead38);

        $t->same('blocked', $ledger['status']);
        $t->same(1, $ledger['blocker_count']);
        $t->same(['repository-head-mismatch'], $ledger['blockers'][0]['blocker_ids']);
        $t->same(0, $ledger['next_countable_failure_count']);
        $t->same(false, $ledger['counts_as_blocker_evidence']);
    },
    'current next38 blocks failure evidence without passed focused repro' => static function (TestRunner $t) use ($currentHead38, $nextHead38): void {
        $current = [libsqlite_release_failure_artifact($currentHead38, 'fts5aux.test', 'fts5aux-3.1', 'current')];
        $next = [libsqlite_release_failure_artifact($nextHead38, 'fts5aux.test', 'fts5aux-3.1', 'next')];
        $focused = [
            'status' => 'blocked',
            'script' => 'fts5aux.test',
            'case' => 'fts5aux-3.1',
        ];

        $ledger = libsqlite_release_failure_evidence()->releaseRunnerFailureLedger($current, $next, $focused, $currentHead38, $nextHead38);

        $t->same('blocked', $ledger['status']);
        $t->same(1, $ledger['blocker_count']);
        $t->same('focused-repro-not-passed', $ledger['blockers'][0]['id']);
        $t->same(false, $ledger['counts_as_blocker_evidence']);
    },
    'current next38 blocks when focused repro does not cover next failed script' => static function (TestRunner $t) use ($currentHead38, $nextHead38): void {
        $current = [libsqlite_release_failure_artifact($currentHead38, 'fts5aux.test', 'fts5aux-3.1', 'current')];
        $next = [libsqlite_release_failure_artifact($nextHead38, 'json101.test', 'json101-9.2', 'next')];
        $focused = libsqlite_release_failure_focused($nextHead38, 'fts5aux.test', 'fts5aux-3.1');

        $ledger = libsqlite_release_failure_evidence()->releaseRunnerFailureLedger($current, $next, $focused, $currentHead38, $nextHead38);

        $t->same('blocked', $ledger['status']);
        $t->same(1, $ledger['blocker_count']);
        $t->same('next-failure-not-covered-by-focused-repro', $ledger['blockers'][0]['id']);
        $t->same(0, $ledger['matching_focused_next_failure_count']);
    },
];
