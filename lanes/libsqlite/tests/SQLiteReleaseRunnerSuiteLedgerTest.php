<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteUpstreamSuiteEvidence;

function libsqlite_suite_ledger_evidence(): SQLiteUpstreamSuiteEvidence
{
    return SQLiteUpstreamSuiteEvidence::fromManifestPath(__DIR__ . '/../UPSTREAM_TEST_MANIFEST.json');
}

function libsqlite_suite_ledger_root(string $label): string
{
    return sys_get_temp_dir() . '/libsqlite-suite-ledger35-' . $label . '-' . bin2hex(random_bytes(4));
}

function libsqlite_suite_ledger_cleanup(string $root): void
{
    if (!is_dir($root)) {
        return;
    }

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($iterator as $path) {
        if ($path->isDir()) {
            @rmdir($path->getPathname());
            continue;
        }

        @chmod($path->getPathname(), 0666);
        @unlink($path->getPathname());
    }
    @rmdir($root);
}

function libsqlite_suite_ledger_php_output(int $assertions = 88, int $failures = 0): string
{
    return "Focused test run: 1 selected test files (root lock skipped)\n"
        . "ok - next35 suite ledger\n\n"
        . "1 test files, {$assertions} assertions, {$failures} failures\n";
}

function libsqlite_suite_ledger_write_artifact(
    string $directory,
    string $label,
    string $head,
    int $tests,
    array $options = []
): void {
    if (!is_dir($directory)) {
        mkdir($directory, 0777, true);
    }

    $errors = (int) ($options['errors'] ?? 0);
    $exit = (int) ($options['exit'] ?? ($errors > 0 ? 1 : 0));
    $testset = (string) ($options['testset'] ?? 'release');
    $patterns = (string) ($options['patterns'] ?? 'none');
    $manifest = (string) ($options['manifest'] ?? '9ac4a33a2932d353c4871fd8e09c10addf827f1fc3fc9380037d738cf2cd0353');
    $extension = (string) ($options['extension'] ?? 'md');
    $logName = (string) ($options['log'] ?? ($label . '.log'));
    $audit = "# SQLite Tcl Bounded Runner Evidence - {$label}\n\n"
        . "- Repository HEAD: `{$head}`\n"
        . "- SQLite git commit: `8f70ec615f4cd247d36f92a22c99f65ebbcc22a7`\n"
        . "- SQLite VERSION: `3.54.0`\n"
        . "- SQLite manifest UUID: `{$manifest}`\n"
        . "- Scratch: `/tmp/libsqlite-suite-ledger35`\n"
        . "- Log: `{$logName}`\n"
        . "- Testset: `{$testset}`\n"
        . "- Patterns: `{$patterns}`\n"
        . "- Exit: `{$exit}`\n"
        . "- Parsed errors: `{$errors}`\n"
        . "- Parsed tests: `{$tests}`\n"
        . "- Jobs: `2`\n"
        . "- Timeout seconds: `7200`\n";

    file_put_contents($directory . '/' . $label . '.' . $extension, $audit);
    if (($options['missing_log'] ?? false) !== true) {
        file_put_contents($directory . '/' . basename($logName), "{$errors} errors out of {$tests} tests\n");
    }
}

function libsqlite_suite_ledger_seed(
    string $root,
    string $acceptedHead,
    int $currentCount,
    int $nextCount,
    array $options = []
): array {
    $current = $root . '/current';
    $next = $root . '/next';
    $nextHead = (string) ($options['next_head'] ?? $acceptedHead);

    for ($i = 1; $i <= $currentCount; $i++) {
        libsqlite_suite_ledger_write_artifact($current, sprintf('artifact-%02d', $i), $acceptedHead, 20000 + $i);
    }
    for ($i = 1; $i <= $nextCount; $i++) {
        $label = sprintf('artifact-%02d', $i);
        libsqlite_suite_ledger_write_artifact($next, $label, $nextHead, 20000 + $i, [
            'testset' => ($options['focused_next'] ?? false) ? 'veryquick' : 'release',
            'patterns' => ($options['focused_next'] ?? false) ? 'json101.test, wal.test' : 'none',
            'manifest' => ($options['wrong_manifest'] ?? false) && $i === $nextCount ? 'wrong-manifest' : '9ac4a33a2932d353c4871fd8e09c10addf827f1fc3fc9380037d738cf2cd0353',
            'errors' => ($options['failed_next'] ?? false) && $i === $nextCount ? 1 : 0,
            'missing_log' => ($options['missing_next_log'] ?? false) && $i === $nextCount,
            'extension' => ($options['audit_extension'] ?? false) && $i === $nextCount ? 'audit' : 'md',
        ]);
    }

    return [$current, $next];
}

$acceptedHead35 = 'c6ffc47e5c6f426b39572103682d04f7cac57067';
$focusedPath35 = 'lanes/libsqlite/tests/SQLiteReleaseRunnerSuiteLedgerTest.php';
$nonOverlap35 = 'current-next35 suite ledger avoids accepted release-runner parity ledger, current-next count record, guarded preflight, JSON, B-tree, WAL, VFS, SELECT, and encoding behavior clusters';

$tests = [];

$increaseCases = [
    'one-to-two' => [1, 2, 1],
    'one-to-three' => [1, 3, 2],
    'two-to-four' => [2, 4, 2],
    'three-to-five' => [3, 5, 2],
    'five-to-six' => [5, 6, 1],
    'release-audit-extension' => [1, 2, 1, ['audit_extension' => true]],
    'focused-next-artifact' => [1, 2, 1, ['focused_next' => true]],
    'next-head-candidate' => [1, 2, 1, ['next_head' => 'next35-head']],
    'large-current-next' => [6, 9, 3],
    'wide-current-next' => [8, 12, 4],
    'single-current-many-next' => [1, 5, 4],
    'many-current-one-new' => [9, 10, 1],
    'two-new-release' => [4, 6, 2],
    'three-new-release' => [4, 7, 3],
    'four-new-release' => [2, 6, 4],
    'six-new-release' => [3, 9, 6],
    'ten-new-release' => [2, 12, 10],
    'preserve-two' => [2, 2, 0],
    'preserve-five' => [5, 5, 0],
    'preserve-audit' => [3, 3, 0, ['audit_extension' => true]],
    'preserve-focused' => [4, 4, 0, ['focused_next' => true]],
    'preserve-next-head' => [3, 3, 0, ['next_head' => 'next35-head']],
    'preserve-large' => [10, 10, 0],
    'preserve-one' => [1, 1, 0],
];

foreach ($increaseCases as $case => $config) {
    $tests['current next35 suite ledger classifies count movement ' . $case] = static function (TestRunner $t) use ($case, $config, $acceptedHead35, $focusedPath35, $nonOverlap35): void {
        [$currentCount, $nextCount, $expectedDelta] = $config;
        $options = is_array($config[3] ?? null) ? $config[3] : [];
        $root = libsqlite_suite_ledger_root($case);

        try {
            [$current, $next] = libsqlite_suite_ledger_seed($root, $acceptedHead35, (int) $currentCount, (int) $nextCount, $options);
            $record = libsqlite_suite_ledger_evidence()->releaseRunnerSuiteLedger(
                $current,
                $next,
                $acceptedHead35,
                12271,
                $focusedPath35,
                libsqlite_suite_ledger_php_output(88),
                $nonOverlap35,
                $options['next_head'] ?? null
            );

            $t->same($expectedDelta > 0 ? 'next35-suite-ledger-countable' : 'next35-suite-ledger-preserved', $record['status']);
            $t->same((int) $currentCount, $record['current_countable_count']);
            $t->same((int) $nextCount, $record['next_countable_count']);
            $t->same((int) $expectedDelta, $record['countable_delta']);
            $t->same($expectedDelta > 0, $record['counts_next_suite_ledger']);
            $t->same($expectedDelta === 0, $record['preserves_current_suite_ledger']);
            $t->same(88, $record['php_pass_delta']);
            $t->same(12359, $record['next_php_pass']);
            $t->same(0, $record['blocker_count']);
            $t->same(false, $record['release_parity_claimed']);
            $t->contains('no new support component needed', $record['dependency_closure']);
        } finally {
            libsqlite_suite_ledger_cleanup($root);
        }
    };
}

$blockedCases = [
    'missing-current' => [0, 2, ['count-current-artifacts-missing']],
    'missing-next' => [2, 0, ['count-next-artifacts-missing', 'count-next-count-regressed']],
    'regressed-next' => [3, 1, ['count-next-count-regressed']],
    'wrong-manifest' => [1, 2, ['count-next-artifacts-blocked'], ['wrong_manifest' => true]],
    'failed-next' => [1, 2, ['count-next-artifacts-blocked'], ['failed_next' => true]],
    'missing-next-log' => [1, 2, ['count-next-artifact-logs-missing'], ['missing_next_log' => true]],
    'active-runner' => [1, 2, ['count-current-artifacts-blocked', 'count-next-artifacts-blocked', 'active-broad-runner-present'], [], "4711 4700 S 01:22 4.0 ./testfixture ../src/test/testrunner.tcl --jobs 2 --stop-on-error all\n"],
    'php-failure' => [1, 2, ['focused-php-pass-not-admitted'], [], '', libsqlite_suite_ledger_php_output(88, 1)],
    'php-unfocused' => [1, 2, ['focused-php-pass-not-admitted'], [], '', "1 test files, 88 assertions, 0 failures\n"],
];

foreach ($blockedCases as $case => $config) {
    $tests['current next35 suite ledger blocks invalid evidence ' . $case] = static function (TestRunner $t) use ($case, $config, $acceptedHead35, $focusedPath35, $nonOverlap35): void {
        [$currentCount, $nextCount, $expectedBlockers] = $config;
        $options = is_array($config[3] ?? null) ? $config[3] : [];
        $snapshot = is_string($config[4] ?? null) ? $config[4] : '';
        $phpOutput = is_string($config[5] ?? null) ? $config[5] : libsqlite_suite_ledger_php_output(88);
        $root = libsqlite_suite_ledger_root($case);

        try {
            [$current, $next] = libsqlite_suite_ledger_seed($root, $acceptedHead35, (int) $currentCount, (int) $nextCount, $options);
            $record = libsqlite_suite_ledger_evidence()->releaseRunnerSuiteLedger(
                $current,
                $next,
                $acceptedHead35,
                12271,
                $focusedPath35,
                $phpOutput,
                $nonOverlap35,
                null,
                $snapshot
            );
            $blockerIds = array_column($record['blockers'], 'id');

            $t->same('blocked', $record['status']);
            foreach ($expectedBlockers as $expectedBlocker) {
                $t->true(in_array($expectedBlocker, $blockerIds, true), 'Expected blocker ' . $expectedBlocker);
            }
            $t->same(false, $record['counts_next_suite_ledger']);
            $t->same(false, $record['preserves_current_suite_ledger']);
            $t->same(false, $record['release_parity_claimed']);
            $t->contains('repair current/next artifact provenance', $record['next_gate']);
        } finally {
            libsqlite_suite_ledger_cleanup($root);
        }
    };
}

for ($i = 1; $i <= 21; $i++) {
    $tests['current next35 suite ledger generated countable case ' . str_pad((string) $i, 2, '0', STR_PAD_LEFT)] = static function (TestRunner $t) use ($i, $acceptedHead35, $focusedPath35, $nonOverlap35): void {
        $currentCount = ($i % 4) + 1;
        $nextCount = $currentCount + (($i % 3) + 1);
        $root = libsqlite_suite_ledger_root('generated-' . $i);

        try {
            [$current, $next] = libsqlite_suite_ledger_seed($root, $acceptedHead35, $currentCount, $nextCount);
            $record = libsqlite_suite_ledger_evidence()->releaseRunnerSuiteLedger(
                $current,
                $next,
                $acceptedHead35,
                12271,
                $focusedPath35,
                libsqlite_suite_ledger_php_output(90 + $i),
                $nonOverlap35
            );

            $t->same('next35-suite-ledger-countable', $record['status']);
            $t->same($nextCount - $currentCount, $record['countable_delta']);
            $t->same(90 + $i, $record['php_pass_delta']);
            $t->same(12271 + 90 + $i, $record['next_php_pass']);
            $t->same([], $record['lost_countable_labels']);
            $t->true($record['tests_total_delta'] > 0, 'Expected next artifact tests to increase');
        } finally {
            libsqlite_suite_ledger_cleanup($root);
        }
    };
}

return $tests;
