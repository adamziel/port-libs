<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteUpstreamSuiteEvidence;

function libsqlite_release_parity29_evidence(): SQLiteUpstreamSuiteEvidence
{
    return SQLiteUpstreamSuiteEvidence::fromManifestPath(__DIR__ . '/../UPSTREAM_TEST_MANIFEST.json');
}

function libsqlite_release_parity29_artifact(
    string $head,
    string $testset,
    array $patterns,
    int $tests,
    int $errors = 0,
    int $exit = 0,
    string $manifest = '9ac4a33a2932d353c4871fd8e09c10addf827f1fc3fc9380037d738cf2cd0353',
    string $status = 'passed'
): array {
    return [
        'status' => $status,
        'label' => $testset . '-' . $tests,
        'repository_head' => $head,
        'sqlite_commit' => '8f70ec615f4cd247d36f92a22c99f65ebbcc22a7',
        'sqlite_version' => '3.54.0',
        'sqlite_manifest_uuid' => $manifest,
        'requested' => [
            'testset' => $testset,
            'patterns' => $patterns,
            'jobs' => 2,
            'timeout_seconds' => 7200,
        ],
        'results' => [
            'exit' => $exit,
            'tests' => $tests,
            'errors' => $errors,
            'failure_count' => 0,
            'failures' => [],
            'failure_blockers' => [],
        ],
        'active_gate' => [
            'status' => 'clear',
            'active_count' => 0,
            'active_tiers' => [],
        ],
    ];
}

function libsqlite_release_parity29_php_output(int $assertions = 84, int $failures = 0, int $files = 1): string
{
    return "Focused test run: {$files} selected test files (root lock skipped)\n"
        . "ok - sample\n\n"
        . "{$files} test files, {$assertions} assertions, {$failures} failures\n";
}

$acceptedHead = '28488284c6b42b08db024e7e34c788f71b24a201';
$focusedPath = 'lanes/libsqlite/tests/SQLiteReleaseRunnerParityLedgerCurrentNext29Test.php';
$nonOverlap = 'current-next29 release-runner parity ledger avoids accepted artifact hydration, guarded countability preflight, and release-blocker closure wrappers';

return [
    'current next29 counts broad zero error artifact while admitting php pass delta' => static function (TestRunner $t) use ($acceptedHead, $focusedPath, $nonOverlap): void {
        $ledger = libsqlite_release_parity29_evidence()->currentReleaseRunnerParityLedger(
            [
                'release-zero' => libsqlite_release_parity29_artifact($acceptedHead, 'all', [], 401234),
                'focused-json' => libsqlite_release_parity29_artifact($acceptedHead, 'veryquick', ['json101.test', 'jsonb01.test'], 650),
            ],
            $acceptedHead,
            10028,
            $focusedPath,
            libsqlite_release_parity29_php_output(84),
            $nonOverlap,
            '',
            true
        );

        $t->same('zero-error-release-parity-current', $ledger['status']);
        $t->same($acceptedHead, $ledger['accepted_repository_head']);
        $t->same(2, $ledger['artifact_count']);
        $t->same(2, $ledger['current_accepted_artifacts']);
        $t->same(1, $ledger['focused_countable_artifacts']);
        $t->same(1, $ledger['release_like_artifacts']);
        $t->same(1, $ledger['release_admission_count']);
        $t->same(true, $ledger['release_blocker_closed']);
        $t->same(true, $ledger['counts_as_zero_error_release_parity']);
        $t->same(false, $ledger['counts_focused_as_release_parity']);
        $t->same(84, $ledger['php_pass_delta']);
        $t->same(10112, $ledger['next_php_pass']);
        $t->same(0, $ledger['blocker_count']);
        $t->same('zero-error-release-parity-countable', $ledger['release_ledger']['status']);
        $t->same(1, $ledger['release_ledger']['zero_error_release_artifacts']);
        $t->same(401234, $ledger['release_ledger']['artifact_tests_total']);
        $t->same(0, $ledger['release_ledger']['artifact_errors_total']);
        $t->same('rerun-not-needed-zero-error-parity', $ledger['rerun_decision']['status']);
        $t->same(false, $ledger['rerun_decision']['rerun_allowed']);
        $t->same('admitted', $ledger['php_pass_admission']['status']);
        $t->same($focusedPath, $ledger['php_pass_admission']['focused_path']);
        $t->same(1, $ledger['focused']['countable_count']);
        $t->same(['focused-json'], $ledger['focused']['countable_labels']);
        $t->same(['release-zero'], $ledger['provenance']['release_like_labels']);
        $t->contains('zero-error release/all artifact', $ledger['next_gate']);
        $t->contains('no new support component needed', $ledger['dependency_closure']);
    },

    'current next29 keeps focused-only artifacts out of release parity' => static function (TestRunner $t) use ($acceptedHead, $focusedPath, $nonOverlap): void {
        $ledger = libsqlite_release_parity29_evidence()->currentReleaseRunnerParityLedger(
            [
                'focused-json' => libsqlite_release_parity29_artifact($acceptedHead, 'veryquick', ['json101.test'], 301),
                'focused-wal' => libsqlite_release_parity29_artifact($acceptedHead, 'release', ['wal.test'], 412),
            ],
            $acceptedHead,
            10028,
            $focusedPath,
            libsqlite_release_parity29_php_output(77),
            $nonOverlap
        );

        $t->same('focused-evidence-current-release-open', $ledger['status']);
        $t->same(2, $ledger['artifact_count']);
        $t->same(2, $ledger['current_accepted_artifacts']);
        $t->same(2, $ledger['focused_countable_artifacts']);
        $t->same(0, $ledger['release_like_artifacts']);
        $t->same(0, $ledger['release_admission_count']);
        $t->same(false, $ledger['release_blocker_closed']);
        $t->same(false, $ledger['counts_as_zero_error_release_parity']);
        $t->same(false, $ledger['counts_focused_as_release_parity']);
        $t->same(77, $ledger['php_pass_delta']);
        $t->same(10105, $ledger['next_php_pass']);
        $t->same('blocked', $ledger['release_ledger']['status']);
        $t->same(0, $ledger['release_ledger']['entry_count']);
        $t->same(2, $ledger['focused']['countable_count']);
        $t->same(713, $ledger['focused']['tests_total']);
        $t->same(0, $ledger['focused']['errors_total']);
        $t->contains('focused PHP PASS delta', $ledger['next_gate']);
        $t->contains('release/all parity open', $ledger['next_gate']);
    },

    'current next29 blocks stale broad artifacts and exposes provenance blockers' => static function (TestRunner $t) use ($acceptedHead, $focusedPath, $nonOverlap): void {
        $ledger = libsqlite_release_parity29_evidence()->currentReleaseRunnerParityLedger(
            [
                'stale-release' => libsqlite_release_parity29_artifact('stale-head', 'all', [], 9000),
                'wrong-manifest' => libsqlite_release_parity29_artifact($acceptedHead, 'release', [], 100, 0, 0, 'wrong-manifest'),
            ],
            $acceptedHead,
            10028,
            $focusedPath,
            libsqlite_release_parity29_php_output(61),
            $nonOverlap,
            '',
            false
        );

        $t->same('blocked', $ledger['status']);
        $t->same(2, $ledger['artifact_count']);
        $t->same(0, $ledger['current_accepted_artifacts']);
        $t->same(0, $ledger['focused_countable_artifacts']);
        $t->same(2, $ledger['provenance']['blocked_count']);
        $t->same(1, $ledger['provenance']['stale_head_count']);
        $t->same(1, $ledger['provenance']['manifest_mismatch_count']);
        $t->same(['stale-release'], $ledger['provenance']['stale_head_labels']);
        $t->same(['wrong-manifest'], $ledger['provenance']['manifest_mismatch_labels']);
        $t->same(false, $ledger['counts_as_zero_error_release_parity']);
        $t->true($ledger['blocker_count'] >= 1, 'Expected provenance blocker');
        $t->true(in_array('artifact-provenance-blocked', array_column($ledger['blockers'], 'id'), true), 'Expected provenance blocker id');
        $t->same('admitted', $ledger['php_pass_admission']['status']);
        $t->same(61, $ledger['php_pass_delta']);
        $t->contains('repair provenance', $ledger['next_gate']);
    },

    'current next29 records exclusion closure without zero error parity' => static function (TestRunner $t) use ($acceptedHead, $focusedPath, $nonOverlap): void {
        $exclusion = [
            'status' => 'accepted-non-portability-exclusion',
            'counts_as_release_blocker_closure' => true,
            'script' => 'ext/fts5/test/fts5aux.test',
            'case' => 'fts5aux-3.1',
            'blockers' => [],
        ];

        $ledger = libsqlite_release_parity29_evidence()->currentReleaseRunnerParityLedger(
            [],
            $acceptedHead,
            10028,
            $focusedPath,
            libsqlite_release_parity29_php_output(55),
            $nonOverlap,
            '',
            true,
            $exclusion
        );

        $t->same('release-blocker-closed-by-exclusion', $ledger['status']);
        $t->same(0, $ledger['artifact_count']);
        $t->same(0, $ledger['release_like_artifacts']);
        $t->same(1, $ledger['release_admission_count']);
        $t->same(true, $ledger['release_blocker_closed']);
        $t->same(false, $ledger['counts_as_zero_error_release_parity']);
        $t->same(false, $ledger['counts_focused_as_release_parity']);
        $t->same('release-blocker-closed-by-exclusion', $ledger['release_ledger']['status']);
        $t->same(1, $ledger['release_ledger']['exclusion_only_closures']);
        $t->same(0, $ledger['release_ledger']['zero_error_release_artifacts']);
        $t->same(55, $ledger['php_pass_delta']);
        $t->same('blocked', $ledger['rerun_decision']['status']);
        $t->same('release-blocker-closed-by-exclusion', $ledger['rerun_decision']['blockers'][0]['id']);
        $t->contains('exclusion', $ledger['next_gate']);
        $t->contains('zero-error parity uncounted', $ledger['next_gate']);
    },

    'current next29 blocks php pass admission for unfocused or failing output' => static function (TestRunner $t) use ($acceptedHead, $focusedPath, $nonOverlap): void {
        $ledger = libsqlite_release_parity29_evidence()->currentReleaseRunnerParityLedger(
            [
                'release-zero' => libsqlite_release_parity29_artifact($acceptedHead, 'all', [], 12345),
            ],
            $acceptedHead,
            10028,
            $focusedPath,
            "1 test files, 84 assertions, 0 failures\n",
            $nonOverlap,
            '',
            true
        );

        $t->same('zero-error-release-parity-current', $ledger['status']);
        $t->same(0, $ledger['php_pass_delta']);
        $t->same(10028, $ledger['next_php_pass']);
        $t->same('blocked', $ledger['php_pass_admission']['status']);
        $t->true(in_array('php-pass-admission-blocked', array_column($ledger['blockers'], 'id'), true), 'Expected PHP admission blocker');
        $t->contains('focused TestRunner output', $ledger['blockers'][0]['evidence']);

        $failing = libsqlite_release_parity29_evidence()->currentReleaseRunnerParityLedger(
            [],
            $acceptedHead,
            10028,
            $focusedPath,
            libsqlite_release_parity29_php_output(84, 1),
            $nonOverlap
        );
        $t->same('blocked', $failing['status']);
        $t->same('blocked', $failing['php_pass_admission']['status']);
        $t->same(0, $failing['php_pass_delta']);
        $t->same(10028, $failing['next_php_pass']);
    },

    'current next29 rerun decision remains blocked by active broad runner' => static function (TestRunner $t) use ($acceptedHead, $focusedPath, $nonOverlap): void {
        $failed = libsqlite_release_parity29_artifact($acceptedHead, 'release', [], 700, 1, 1, status: 'failed');
        $failed['results']['failure_count'] = 1;
        $failed['results']['failures'] = [
            [
                'script' => 'fts5aux.test',
                'case' => 'fts5aux-3.1',
                'kind' => 'runtime',
                'diagnostic' => 'UndefinedBehaviorSanitizer runtime error',
            ],
        ];
        $failed['results']['failure_blockers'] = [
            [
                'id' => 'upstream-runtime-sanitizer',
                'category' => 'upstream-runtime-environment',
                'script' => 'fts5aux.test',
                'case' => 'fts5aux-3.1',
            ],
        ];

        $snapshot = '577297 577296 S 02:14 9.1 ./testfixture ../src/test/testrunner.tcl --jobs 2 --stop-on-error release';
        $ledger = libsqlite_release_parity29_evidence()->currentReleaseRunnerParityLedger(
            ['failed-release' => $failed],
            $acceptedHead,
            10028,
            $focusedPath,
            libsqlite_release_parity29_php_output(52),
            $nonOverlap,
            $snapshot,
            false
        );

        $t->same('blocked', $ledger['status']);
        $t->same(false, $ledger['counts_as_zero_error_release_parity']);
        $t->same('blocked', $ledger['release_ledger']['status']);
        $t->same(1, $ledger['release_ledger']['blocked_admissions']);
        $t->same('blocked', $ledger['rerun_decision']['status']);
        $t->same(false, $ledger['rerun_decision']['rerun_allowed']);
        $t->same('blocked-active-runner', $ledger['rerun_decision']['active_gate']['status']);
        $t->same(['release'], $ledger['rerun_decision']['active_gate']['active_tiers']);
        $t->true(in_array('active-runner-still-running', array_column($ledger['rerun_decision']['blockers'], 'id'), true), 'Expected active broad runner blocker');
        $t->true(in_array('admission-artifact-not-passed', array_column($ledger['rerun_decision']['blockers'], 'id'), true), 'Expected failed artifact admission blocker');
        $t->same(52, $ledger['php_pass_delta']);
        $t->contains('repair provenance', $ledger['next_gate']);
    },
];
