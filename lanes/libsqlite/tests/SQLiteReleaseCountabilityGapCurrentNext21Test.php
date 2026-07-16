<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteUpstreamSuiteEvidence;

return [
    'admits focused phpPass delta only from a zero-failure lane test run' => static function (TestRunner $t): void {
        $evidence = SQLiteUpstreamSuiteEvidence::fromManifestPath(__DIR__ . '/../UPSTREAM_TEST_MANIFEST.json');
        $output = "Focused test run: 1 selected test files (root lock skipped)\n"
            . "PASS admits focused phpPass delta only from a zero-failure lane test run\n"
            . "\n1 test files, 42 assertions, 0 failures\n";

        $admission = $evidence->focusedPhpPassAdmission(
            7262,
            'lanes/libsqlite/tests/SQLiteReleaseCountabilityGapCurrentNext21Test.php',
            $output,
            'avoids accepted WAL, B-tree, JSON table, VFS, grouped SELECT, subquery, and Unicode GLOB clusters'
        );

        $t->same('admitted', $admission['status']);
        $t->same('lanes/libsqlite/tests/SQLiteReleaseCountabilityGapCurrentNext21Test.php', $admission['focused_path']);
        $t->same(7262, $admission['current_php_pass']);
        $t->same(42, $admission['assertion_delta']);
        $t->same(7304, $admission['next_php_pass']);
        $t->same(1, $admission['selected_test_files']);
        $t->same(1, $admission['summary_test_files']);
        $t->same(0, $admission['failures']);
        $t->same(true, $admission['focused_output_seen']);
        $t->same(null, $admission['blocker']);
        $t->contains('no new support component needed', $admission['dependency_closure']);
        $t->contains('avoids accepted WAL', $admission['non_overlap_note']);
    },
    'blocks phpPass movement when focused output has failures' => static function (TestRunner $t): void {
        $evidence = SQLiteUpstreamSuiteEvidence::fromManifestPath(__DIR__ . '/../UPSTREAM_TEST_MANIFEST.json');
        $output = "Focused test run: 1 selected test files (root lock skipped)\n"
            . "FAIL blocked case (lanes/libsqlite/tests/SQLiteReleaseCountabilityGapCurrentNext21Test.php)\n"
            . "\n1 test files, 41 assertions, 1 failures\n";

        $admission = $evidence->focusedPhpPassAdmission(
            7262,
            'lanes/libsqlite/tests/SQLiteReleaseCountabilityGapCurrentNext21Test.php',
            $output,
            'non-overlap note'
        );

        $t->same('blocked', $admission['status']);
        $t->same(0, $admission['assertion_delta']);
        $t->same(7262, $admission['next_php_pass']);
        $t->same(1, $admission['failures']);
        $t->same('focused TestRunner output contains failures', $admission['blocker']);
    },
    'blocks phpPass movement for root harness output without focused marker' => static function (TestRunner $t): void {
        $evidence = SQLiteUpstreamSuiteEvidence::fromManifestPath(__DIR__ . '/../UPSTREAM_TEST_MANIFEST.json');
        $output = "215 test files, 32110 assertions, 0 failures\n";

        $admission = $evidence->focusedPhpPassAdmission(
            7262,
            'lanes/libsqlite/tests/SQLiteReleaseCountabilityGapCurrentNext21Test.php',
            $output,
            'non-overlap note'
        );

        $t->same('blocked', $admission['status']);
        $t->same(0, $admission['assertion_delta']);
        $t->same(7262, $admission['next_php_pass']);
        $t->same(false, $admission['focused_output_seen']);
        $t->same('missing focused TestRunner output', $admission['blocker']);
    },
    'blocks phpPass movement for multi-file focused output' => static function (TestRunner $t): void {
        $evidence = SQLiteUpstreamSuiteEvidence::fromManifestPath(__DIR__ . '/../UPSTREAM_TEST_MANIFEST.json');
        $output = "Focused test run: 2 selected test files (root lock skipped)\n"
            . "\n2 test files, 90 assertions, 0 failures\n";

        $admission = $evidence->focusedPhpPassAdmission(
            7262,
            'lanes/libsqlite/tests/SQLiteReleaseCountabilityGapCurrentNext21Test.php',
            $output,
            'non-overlap note'
        );

        $t->same('blocked', $admission['status']);
        $t->same(0, $admission['assertion_delta']);
        $t->same(7262, $admission['next_php_pass']);
        $t->same(2, $admission['selected_test_files']);
        $t->same(2, $admission['summary_test_files']);
        $t->same('phpPass admission requires exactly one focused lane test file for this slice', $admission['blocker']);
    },
    'blocks phpPass movement when no assertions are present' => static function (TestRunner $t): void {
        $evidence = SQLiteUpstreamSuiteEvidence::fromManifestPath(__DIR__ . '/../UPSTREAM_TEST_MANIFEST.json');
        $output = "Focused test run: 1 selected test files (root lock skipped)\n"
            . "\n1 test files, 0 assertions, 0 failures\n";

        $admission = $evidence->focusedPhpPassAdmission(
            7262,
            'lanes/libsqlite/tests/SQLiteReleaseCountabilityGapCurrentNext21Test.php',
            $output,
            'non-overlap note'
        );

        $t->same('blocked', $admission['status']);
        $t->same(0, $admission['assertion_delta']);
        $t->same(7262, $admission['next_php_pass']);
        $t->same('focused TestRunner output has no assertions to count', $admission['blocker']);
    },
    'rejects non lane-local focused paths before counting output' => static function (TestRunner $t): void {
        $evidence = SQLiteUpstreamSuiteEvidence::fromManifestPath(__DIR__ . '/../UPSTREAM_TEST_MANIFEST.json');

        $t->throws(
            InvalidArgumentException::class,
            static fn () => $evidence->focusedPhpPassAdmission(
                7262,
                'tools/SQLiteReleaseCountabilityGapCurrentNext21Test.php',
                "Focused test run: 1 selected test files (root lock skipped)\n\n1 test files, 1 assertions, 0 failures\n",
                'non-overlap note'
            )
        );
    },
    'rejects missing non-overlap notes before counting output' => static function (TestRunner $t): void {
        $evidence = SQLiteUpstreamSuiteEvidence::fromManifestPath(__DIR__ . '/../UPSTREAM_TEST_MANIFEST.json');

        $t->throws(
            InvalidArgumentException::class,
            static fn () => $evidence->focusedPhpPassAdmission(
                7262,
                'lanes/libsqlite/tests/SQLiteReleaseCountabilityGapCurrentNext21Test.php',
                "Focused test run: 1 selected test files (root lock skipped)\n\n1 test files, 1 assertions, 0 failures\n",
                ''
            )
        );
    },
    'rejects negative current phpPass values before counting output' => static function (TestRunner $t): void {
        $evidence = SQLiteUpstreamSuiteEvidence::fromManifestPath(__DIR__ . '/../UPSTREAM_TEST_MANIFEST.json');

        $t->throws(
            InvalidArgumentException::class,
            static fn () => $evidence->focusedPhpPassAdmission(
                -1,
                'lanes/libsqlite/tests/SQLiteReleaseCountabilityGapCurrentNext21Test.php',
                "Focused test run: 1 selected test files (root lock skipped)\n\n1 test files, 1 assertions, 0 failures\n",
                'non-overlap note'
            )
        );
    },
];
