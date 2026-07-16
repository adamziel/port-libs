<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteUpstreamSuiteEvidence;

return [
    'admits current next34 denominator movement only from focused php pass and clean runner counts' => static function (TestRunner $t): void {
        $evidence = SQLiteUpstreamSuiteEvidence::fromManifestPath(__DIR__ . '/../UPSTREAM_TEST_MANIFEST.json');
        $record = $evidence->releaseRunnerDenominatorAudit(
            [
                'total' => 1589,
                'mapped' => 461,
                'runner_countable_count' => 3,
                'runner_blocked_count' => 0,
            ],
            [
                'total' => 1589,
                'mapped' => 462,
                'runner_countable_count' => 4,
                'runner_blocked_count' => 0,
            ],
            11752,
            'lanes/libsqlite/tests/SQLiteReleaseRunnerDenominatorAuditTest.php',
            "Focused test run: 1 selected test files (root lock skipped)\n\n1 test files, 64 assertions, 0 failures\n",
            'Avoids accepted batch23 runner preflight and release-runner current/next count records; audits only next34 denominator admission.'
        );

        $t->same('countable-current-next34-denominator-movement', $record['status']);
        $t->same(1589, $record['current_total']);
        $t->same(1589, $record['next_total']);
        $t->same(461, $record['current_mapped']);
        $t->same(462, $record['next_mapped']);
        $t->same(1, $record['mapped_delta']);
        $t->same(3, $record['current_runner_countable_count']);
        $t->same(4, $record['next_runner_countable_count']);
        $t->same(1, $record['runner_countable_delta']);
        $t->same(0, $record['current_runner_blocked_count']);
        $t->same(0, $record['next_runner_blocked_count']);
        $t->same(64, $record['php_pass_delta']);
        $t->same(11816, $record['next_php_pass']);
        $t->same(true, $record['counts_dashboard_movement']);
        $t->same(true, $record['counts_runner_denominator_movement']);
        $t->same(0, $record['blocker_count']);
        $t->same([], $record['blockers']);
        $t->contains('next34', $record['next_gate']);
        $t->contains('no new support component needed', $record['dependency_closure']);

        $admission = $record['php_pass_admission'];
        $t->same('admitted', $admission['status']);
        $t->same(11752, $admission['current_php_pass']);
        $t->same(64, $admission['assertion_delta']);
        $t->same(11816, $admission['next_php_pass']);
        $t->same(1, $admission['selected_test_files']);
        $t->same(1, $admission['summary_test_files']);
        $t->same(0, $admission['failures']);
        $t->same(true, $admission['focused_output_seen']);
        $t->contains('batch23', $admission['non_overlap_note']);
    },
    'blocks next34 movement when focused output is not a single passing lane test file' => static function (TestRunner $t): void {
        $evidence = SQLiteUpstreamSuiteEvidence::fromManifestPath(__DIR__ . '/../UPSTREAM_TEST_MANIFEST.json');
        $record = $evidence->releaseRunnerDenominatorAudit(
            [
                'total' => 1589,
                'mapped' => 461,
                'runner_countable_count' => 3,
                'runner_blocked_count' => 0,
            ],
            [
                'total' => 1589,
                'mapped' => 462,
                'runner_countable_count' => 4,
                'runner_blocked_count' => 0,
            ],
            11752,
            'lanes/libsqlite/tests/SQLiteReleaseRunnerDenominatorAuditTest.php',
            "Focused test run: 2 selected test files (root lock skipped)\n\n2 test files, 128 assertions, 0 failures\n",
            'Current-next34 denominator audit rejects repeated focused assertions from multiple files.'
        );

        $t->same('blocked', $record['status']);
        $t->same(0, $record['mapped_delta']);
        $t->same(0, $record['runner_countable_delta']);
        $t->same(0, $record['php_pass_delta']);
        $t->same(11752, $record['next_php_pass']);
        $t->same(false, $record['counts_dashboard_movement']);
        $t->same(false, $record['counts_runner_denominator_movement']);
        $t->same(1, $record['blocker_count']);
        $t->same('focused-php-pass-not-admitted', $record['blockers'][0]['id']);
        $t->contains('exactly one focused lane test file', $record['blockers'][0]['evidence']);
        $t->contains('uncounted', $record['next_gate']);
        $t->same('blocked', $record['php_pass_admission']['status']);
        $t->same(0, $record['php_pass_admission']['assertion_delta']);
        $t->same(11752, $record['php_pass_admission']['next_php_pass']);
    },
    'blocks denominator movement when mapped inventory regresses or totals drift' => static function (TestRunner $t): void {
        $evidence = SQLiteUpstreamSuiteEvidence::fromManifestPath(__DIR__ . '/../UPSTREAM_TEST_MANIFEST.json');
        $record = $evidence->releaseRunnerDenominatorAudit(
            [
                'total' => 1589,
                'mapped' => 461,
                'runner_countable_count' => 3,
                'runner_blocked_count' => 0,
            ],
            [
                'total' => 1590,
                'mapped' => 460,
                'runner_countable_count' => 4,
                'runner_blocked_count' => 0,
            ],
            11752,
            'lanes/libsqlite/tests/SQLiteReleaseRunnerDenominatorAuditTest.php',
            "Focused test run: 1 selected test files (root lock skipped)\n\n1 test files, 64 assertions, 0 failures\n",
            'Current-next34 denominator audit keeps stale mapped regressions out of phpPass publication.'
        );

        $t->same('blocked', $record['status']);
        $t->same(1589, $record['current_total']);
        $t->same(1590, $record['next_total']);
        $t->same(461, $record['current_mapped']);
        $t->same(460, $record['next_mapped']);
        $t->same(0, $record['mapped_delta']);
        $t->same(0, $record['runner_countable_delta']);
        $t->same(0, $record['php_pass_delta']);
        $t->same(11752, $record['next_php_pass']);
        $t->same(2, $record['blocker_count']);
        $t->same(['mapped-count-regressed', 'denominator-total-changed'], array_column($record['blockers'], 'id'));
        $t->same(461, $record['blockers'][0]['current_mapped']);
        $t->same(460, $record['blockers'][0]['next_mapped']);
        $t->same(1589, $record['blockers'][1]['current_total']);
        $t->same(1590, $record['blockers'][1]['next_total']);
    },
    'blocks next34 runner denominator movement for regressed or blocked artifacts' => static function (TestRunner $t): void {
        $evidence = SQLiteUpstreamSuiteEvidence::fromManifestPath(__DIR__ . '/../UPSTREAM_TEST_MANIFEST.json');
        $record = $evidence->releaseRunnerDenominatorAudit(
            [
                'total' => 1589,
                'mapped' => 461,
                'runner_countable_count' => 5,
                'runner_blocked_count' => 1,
            ],
            [
                'total' => 1589,
                'mapped' => 462,
                'runner_countable_count' => 4,
                'runner_blocked_count' => 2,
            ],
            11752,
            'lanes/libsqlite/tests/SQLiteReleaseRunnerDenominatorAuditTest.php',
            "Focused test run: 1 selected test files (root lock skipped)\n\n1 test files, 64 assertions, 0 failures\n",
            'Current-next34 denominator audit refuses stale runner artifact directories.'
        );

        $t->same('blocked', $record['status']);
        $t->same(5, $record['current_runner_countable_count']);
        $t->same(4, $record['next_runner_countable_count']);
        $t->same(1, $record['current_runner_blocked_count']);
        $t->same(2, $record['next_runner_blocked_count']);
        $t->same(0, $record['mapped_delta']);
        $t->same(0, $record['runner_countable_delta']);
        $t->same(0, $record['php_pass_delta']);
        $t->same(3, $record['blocker_count']);
        $t->same([
            'runner-count-regressed',
            'current-runner-evidence-blocked',
            'next-runner-evidence-blocked',
        ], array_column($record['blockers'], 'id'));
        $t->same(5, $record['blockers'][0]['current_runner_countable_count']);
        $t->same(4, $record['blockers'][0]['next_runner_countable_count']);
        $t->same(1, $record['blockers'][1]['blocked_count']);
        $t->same(2, $record['blockers'][2]['blocked_count']);
    },
    'preserves current next34 denominator when clean evidence has no new movement' => static function (TestRunner $t): void {
        $evidence = SQLiteUpstreamSuiteEvidence::fromManifestPath(__DIR__ . '/../UPSTREAM_TEST_MANIFEST.json');
        $record = $evidence->releaseRunnerDenominatorAudit(
            [
                'total' => '1589',
                'mapped' => '461',
                'runner_countable_count' => '3',
                'runner_blocked_count' => '0',
            ],
            [
                'total' => '1589',
                'mapped' => '461',
                'runner_countable_count' => '3',
                'runner_blocked_count' => '0',
            ],
            11752,
            'lanes/libsqlite/tests/SQLiteReleaseRunnerDenominatorAuditTest.php',
            "Focused test run: 1 selected test files (root lock skipped)\n\n1 test files, 64 assertions, 0 failures\n",
            'Current-next34 denominator audit records preservation without inventing mapped or runner movement.'
        );

        $t->same('countable-current-next34-denominator-movement', $record['status']);
        $t->same(1589, $record['current_total']);
        $t->same(1589, $record['next_total']);
        $t->same(461, $record['current_mapped']);
        $t->same(461, $record['next_mapped']);
        $t->same(0, $record['mapped_delta']);
        $t->same(3, $record['current_runner_countable_count']);
        $t->same(3, $record['next_runner_countable_count']);
        $t->same(0, $record['runner_countable_delta']);
        $t->same(64, $record['php_pass_delta']);
        $t->same(11816, $record['next_php_pass']);
        $t->same(true, $record['counts_dashboard_movement']);
        $t->same(false, $record['counts_runner_denominator_movement']);
        $t->same(0, $record['blocker_count']);
        $t->same([], $record['blockers']);
    },
    'rejects missing denominator integers before counting next34 php pass output' => static function (TestRunner $t): void {
        $evidence = SQLiteUpstreamSuiteEvidence::fromManifestPath(__DIR__ . '/../UPSTREAM_TEST_MANIFEST.json');
        $record = $evidence->releaseRunnerDenominatorAudit(
            [
                'total' => 1589,
                'mapped' => 'not-a-count',
                'runner_countable_count' => 3,
                'runner_blocked_count' => 0,
            ],
            [
                'total' => 1589,
                'mapped' => 462,
                'runner_countable_count' => null,
                'runner_blocked_count' => 0,
            ],
            11752,
            'lanes/libsqlite/tests/SQLiteReleaseRunnerDenominatorAuditTest.php',
            "Focused test run: 1 selected test files (root lock skipped)\n\n1 test files, 64 assertions, 0 failures\n",
            'Current-next34 denominator audit requires numeric mapped and runner counts.'
        );

        $t->same('blocked', $record['status']);
        $t->same(-1, $record['current_mapped']);
        $t->same(462, $record['next_mapped']);
        $t->same(3, $record['current_runner_countable_count']);
        $t->same(-1, $record['next_runner_countable_count']);
        $t->same(0, $record['mapped_delta']);
        $t->same(0, $record['runner_countable_delta']);
        $t->same(0, $record['php_pass_delta']);
        $t->same(2, $record['blocker_count']);
        $t->same(['mapped-count-missing', 'runner-count-missing'], array_column($record['blockers'], 'id'));
        $t->contains('mapped counts', $record['blockers'][0]['evidence']);
        $t->contains('runner countable counts', $record['blockers'][1]['evidence']);
    },
];
