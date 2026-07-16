<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteUpstreamSuiteEvidence;

return [
    'admits current-source repro artifacts without claiming next-source or release parity' => static function (TestRunner $t): void {
        $evidence = SQLiteUpstreamSuiteEvidence::fromManifestPath(__DIR__ . '/../UPSTREAM_TEST_MANIFEST.json');
        $root = sys_get_temp_dir() . '/libsqlite-current-source-repro-repro-' . bin2hex(random_bytes(4));
        mkdir($root, 0777, true);

        $currentHead = '432eeef3a780a882f63963e1ddad168744b946dd';
        $nextHead = '271b286480bbfdef0408d3e5e495087bd433ae40';
        $sqliteCommit = '8f70ec615f4cd247d36f92a22c99f65ebbcc22a7';
        $uuid = '9ac4a33a2932d353c4871fd8e09c10addf827f1fc3fc9380037d738cf2cd0353';

        file_put_contents($root . '/current-repro.md', <<<MD
# SQLite Tcl Bounded Runner Evidence - libsqlite-current107-repro-count

- Repository HEAD: `{$currentHead}`
- Scratch: `/tmp/libsqlite-current107-repro-count`
- Log: `current-repro.log`
- SQLite git commit: `{$sqliteCommit}`
- SQLite VERSION: `3.54.0`
- SQLite manifest UUID: `{$uuid}`
- Testset: `veryquick`
- Jobs: `1`
- Timeout seconds: `900`
- Patterns: `select1.test` `json101.test` `wal.test`
- Exit: `0`
- Elapsed seconds: `9`
- Parsed summary: `0 errors out of 587 tests`
- Parsed errors: `0`
- Parsed tests: `587`
- Runner time: `00:00:09`
MD);
        file_put_contents($root . '/current-repro.log', "00:09 tcl(587/587) r0\n");

        $focusedOutput = <<<OUT
Focused test run: 1 selected test files (root lock skipped)
PASS admits current-source repro artifacts without claiming next-source or release parity

1 test files, 64 assertions, 0 failures
OUT;

        try {
            $record = $evidence->currentSourceRunnerReproCount(
                $root,
                $currentHead,
                $nextHead,
                41873,
                'lanes/libsqlite/tests/SQLiteUpstreamRunnerReproCountTest.php',
                $focusedOutput,
                'Avoids accepted batch104/105 upstream-runner gap burnup and release/all parity; this records only current-source repro countability.'
            );

            $t->same('current-source-repro-countable', $record['status']);
            $t->same($root, $record['artifact_directory']);
            $t->same($currentHead, $record['current_source_head']);
            $t->same($nextHead, $record['next_source_head']);
            $t->same(1, $record['artifact_count']);
            $t->same(1, $record['current_source_count']);
            $t->same(0, $record['next_source_count']);
            $t->same(0, $record['blocked_count']);
            $t->same([], $record['blockers']);
            $t->same(['libsqlite-current107-repro-count'], $record['current_source_labels']);
            $t->same(['json101.test', 'select1.test', 'wal.test'], $record['current_source_patterns']);
            $t->same(587, $record['current_source_tests_total']);
            $t->same(0, $record['current_source_errors_total']);
            $t->same(true, $record['counts_current_source_repro']);
            $t->same(false, $record['counts_next_source']);
            $t->same(false, $record['counts_release_parity']);
            $t->same('admitted', $record['php_pass_admission']['status']);
            $t->same(64, $record['php_pass_delta']);
            $t->same(41937, $record['next_php_pass']);
            $t->contains('current-source repro-count artifact', $record['next_gate']);
            $t->contains('current-source repro count composes lane-local guarded runner audit/log artifacts', $record['dependency_closure']);
        } finally {
            foreach (glob($root . '/*') ?: [] as $file) {
                unlink($file);
            }
            @rmdir($root);
        }
    },
    'blocks next-source artifacts from current-source repro countability' => static function (TestRunner $t): void {
        $evidence = SQLiteUpstreamSuiteEvidence::fromManifestPath(__DIR__ . '/../UPSTREAM_TEST_MANIFEST.json');
        $root = sys_get_temp_dir() . '/libsqlite-current-source-repro-next-blocked-' . bin2hex(random_bytes(4));
        mkdir($root, 0777, true);

        $currentHead = '432eeef3a780a882f63963e1ddad168744b946dd';
        $nextHead = '271b286480bbfdef0408d3e5e495087bd433ae40';
        $sqliteCommit = '8f70ec615f4cd247d36f92a22c99f65ebbcc22a7';
        $uuid = '9ac4a33a2932d353c4871fd8e09c10addf827f1fc3fc9380037d738cf2cd0353';

        file_put_contents($root . '/next.md', <<<MD
# SQLite Tcl Bounded Runner Evidence - libsqlite-current-source-repro-release

- Repository HEAD: `{$nextHead}`
- Scratch: `/tmp/libsqlite-current-source-repro-release`
- Log: `next.log`
- SQLite git commit: `{$sqliteCommit}`
- SQLite VERSION: `3.54.0`
- SQLite manifest UUID: `{$uuid}`
- Testset: `release`
- Jobs: `2`
- Timeout seconds: `7200`
- Patterns: none
- Exit: `0`
- Parsed summary: `0 errors out of 41873 tests`
- Parsed errors: `0`
- Parsed tests: `41873`
MD);
        file_put_contents($root . '/next.log', "05:20 tcl(41873/41873) r0\n");

        $focusedOutput = <<<OUT
Focused test run: 1 selected test files (root lock skipped)
PASS blocks next-source artifacts from current-source repro countability

1 test files, 48 assertions, 0 failures
OUT;

        try {
            $record = $evidence->currentSourceRunnerReproCount(
                $root,
                $currentHead,
                $nextHead,
                41873,
                'lanes/libsqlite/tests/SQLiteUpstreamRunnerReproCountTest.php',
                $focusedOutput,
                'Avoids accepted batch104/105 upstream-runner gap burnup and release/all parity; this records only current-source repro countability.'
            );

            $t->same('blocked-current-source-repro-count', $record['status']);
            $t->same(0, $record['current_source_count']);
            $t->same(1, $record['next_source_count']);
            $t->same(false, $record['counts_current_source_repro']);
            $t->same(false, $record['counts_next_source']);
            $t->same(false, $record['counts_release_parity']);
            $t->same(0, $record['php_pass_delta']);
            $t->same(41873, $record['next_php_pass']);
            $t->same(2, $record['blocked_count']);
            $t->same(['current-source-repro-artifact-not-countable', 'next-source-artifact-present'], array_column($record['blockers'], 'id'));
            $t->contains('keep current-source repro count uncounted', $record['next_gate']);
        } finally {
            foreach (glob($root . '/*') ?: [] as $file) {
                unlink($file);
            }
            @rmdir($root);
        }
    },
    'keeps stale current-source repro artifacts blocked with explicit blocker ids' => static function (TestRunner $t): void {
        $evidence = SQLiteUpstreamSuiteEvidence::fromManifestPath(__DIR__ . '/../UPSTREAM_TEST_MANIFEST.json');
        $root = sys_get_temp_dir() . '/libsqlite-current-source-repro-stale-' . bin2hex(random_bytes(4));
        mkdir($root, 0777, true);

        $currentHead = '432eeef3a780a882f63963e1ddad168744b946dd';
        $nextHead = '271b286480bbfdef0408d3e5e495087bd433ae40';
        $sqliteCommit = '8f70ec615f4cd247d36f92a22c99f65ebbcc22a7';
        $uuid = '9ac4a33a2932d353c4871fd8e09c10addf827f1fc3fc9380037d738cf2cd0353';

        file_put_contents($root . '/stale.md', <<<MD
# SQLite Tcl Bounded Runner Evidence - libsqlite-current107-stale

- Repository HEAD: `aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa`
- Scratch: `/tmp/libsqlite-current107-stale`
- Log: `stale.log`
- SQLite git commit: `{$sqliteCommit}`
- SQLite VERSION: `3.54.0`
- SQLite manifest UUID: `{$uuid}`
- Testset: `veryquick`
- Jobs: `1`
- Timeout seconds: `900`
- Patterns: `btree01.test`
- Exit: `0`
- Parsed summary: `0 errors out of 44 tests`
- Parsed errors: `0`
- Parsed tests: `44`
MD);
        file_put_contents($root . '/stale.log', "00:01 tcl(44/44) r0\n");

        $focusedOutput = <<<OUT
Focused test run: 1 selected test files (root lock skipped)
PASS keeps stale current-source repro artifacts blocked with explicit blocker ids

1 test files, 52 assertions, 0 failures
OUT;

        try {
            $record = $evidence->currentSourceRunnerReproCount(
                $root,
                $currentHead,
                $nextHead,
                41873,
                'lanes/libsqlite/tests/SQLiteUpstreamRunnerReproCountTest.php',
                $focusedOutput,
                'Avoids accepted batch104/105 upstream-runner gap burnup and release/all parity; this records only current-source repro countability.'
            );

            $t->same('blocked-current-source-repro-count', $record['status']);
            $t->same(1, $record['artifact_count']);
            $t->same(0, $record['current_source_count']);
            $t->same(0, $record['next_source_count']);
            $t->same(2, $record['blocked_count']);
            $t->same(0, $record['current_source_tests_total']);
            $t->same(0, $record['php_pass_delta']);
            $t->same(['current-source-repro-artifact-not-countable', 'blocked-runner-artifact-present'], array_column($record['blockers'], 'id'));
            $t->same(['libsqlite-current107-stale'], $record['blockers'][1]['blocked_labels']);
            $t->same('admitted', $record['php_pass_admission']['status']);
            $t->same(52, $record['php_pass_admission']['assertion_delta']);
        } finally {
            foreach (glob($root . '/*') ?: [] as $file) {
                unlink($file);
            }
            @rmdir($root);
        }
    },
];
