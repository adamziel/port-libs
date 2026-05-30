<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteUpstreamSuiteEvidence;

function libsqlite_release_gap55_evidence(): SQLiteUpstreamSuiteEvidence
{
    return SQLiteUpstreamSuiteEvidence::fromManifestPath(__DIR__ . '/../UPSTREAM_TEST_MANIFEST.json');
}

function libsqlite_release_gap55_output(int $assertions = 55, int $failures = 0): string
{
    return "Focused test run: 1 selected test files (root lock skipped)\n"
        . "1 test files, {$assertions} assertions, {$failures} failures\n";
}

function libsqlite_release_gap55_write_artifact(
    string $dir,
    string $file,
    string $label,
    string $head,
    string $testset,
    int $exit,
    int $tests,
    int $errors,
    string $patterns = 'none',
    string $tail = ''
): void {
    $uuid = '9ac4a33a2932d353c4871fd8e09c10addf827f1fc3fc9380037d738cf2cd0353';
    $body = <<<MD
# SQLite Tcl Bounded Runner Evidence - {$label}

- Repository HEAD: `{$head}`
- Scratch: `/tmp/{$label}`
- Log: `{$file}.log`
- SQLite git commit: `8f70ec615f4cd247d36f92a22c99f65ebbcc22a7`
- SQLite VERSION: `3.54.0`
- SQLite manifest UUID: `{$uuid}`
- Testset: `{$testset}`
- Jobs: `2`
- Timeout seconds: `1800`
- Patterns: {$patterns}
- Exit: `{$exit}`
- Elapsed seconds: `41`
- Parsed summary: `{$errors} errors out of {$tests} tests`
- Parsed errors: `{$errors}`
- Parsed tests: `{$tests}`
- Runner time: `00:00:41`
MD;
    if ($tail !== '') {
        $body .= "\n\n## Tail\n\n```text\n{$tail}\n```\n";
    }

    file_put_contents($dir . '/' . $file . '.md', $body);
    file_put_contents($dir . '/' . $file . '.log', "{$errors} errors out of {$tests} tests in 00:00:41\n");
}

function libsqlite_release_gap55_dirs(string $currentHead, string $nextHead, int $case): array
{
    $root = sys_get_temp_dir() . '/libsqlite-release-gap55-' . bin2hex(random_bytes(4));
    $current = $root . '/current';
    $next = $root . '/next';
    mkdir($current, 0777, true);
    mkdir($next, 0777, true);

    libsqlite_release_gap55_write_artifact($current, 'veryquick', 'gap55-current-veryquick-' . $case, $currentHead, 'veryquick', 0, 600 + $case, 0);
    libsqlite_release_gap55_write_artifact($next, 'veryquick', 'gap55-next-veryquick-' . $case, $nextHead, 'veryquick', 0, 620 + $case, 0);
    libsqlite_release_gap55_write_artifact($next, 'focused-json', 'gap55-next-focused-json-' . $case, $nextHead, 'veryquick', 0, 80 + $case, 0, '`json101.test json102.test`');

    if ($case % 4 !== 0) {
        libsqlite_release_gap55_write_artifact($current, 'focused-json', 'gap55-current-focused-json-' . $case, $currentHead, 'veryquick', 0, 70 + $case, 0, '`json101.test json102.test`');
    }
    if ($case % 5 === 0) {
        libsqlite_release_gap55_write_artifact($next, 'release', 'gap55-next-release-' . $case, $nextHead, 'release', 0, 20000 + $case, 0);
    }
    if ($case % 11 === 0) {
        libsqlite_release_gap55_write_artifact($current, 'mptest', 'gap55-current-mptest-' . $case, $currentHead, 'mptest', 0, 900 + $case, 0);
        libsqlite_release_gap55_write_artifact($next, 'mptest', 'gap55-next-mptest-' . $case, $nextHead, 'mptest', 1, 100 + $case, 1, 'none', 'FAILED: mptest');
    }
    if ($case % 17 === 0) {
        libsqlite_release_gap55_write_artifact($next, 'stale-release', 'gap55-stale-release-' . $case, str_repeat('1', 40), 'release', 0, 21000 + $case, 0);
    }

    return [$root, $current, $next];
}

function libsqlite_release_gap55_cleanup(string $root): void
{
    foreach (['current', 'next'] as $dir) {
        foreach (glob($root . '/' . $dir . '/*') ?: [] as $file) {
            unlink($file);
        }
        @rmdir($root . '/' . $dir);
    }
    @rmdir($root);
}

$currentHead55 = 'aa5c67a8d70941079503fe746744a6952caec0a5';
$nextHead55 = 'yield-sqlite-release-runner-upstream-suite-gap-burnup-current-next55';
$focusedPath55 = 'lanes/libsqlite/tests/SQLiteReleaseRunnerSuiteGapBurnupCurrentNext55Test.php';
$nonOverlap55 = 'current-next55 release-runner suite gap burnup avoids accepted artifact directory countability, current-next50 burnup, release blocker closure, and all accepted VFS/WAL/B-tree/JSON/SQL behavior clusters';

$tests = [];

for ($i = 1; $i <= 55; $i++) {
    $tests['current next55 artifact gap burnup classifies directory pair ' . $i] = static function (TestRunner $t) use ($i, $currentHead55, $nextHead55, $focusedPath55, $nonOverlap55): void {
        [$root, $current, $next] = libsqlite_release_gap55_dirs($currentHead55, $nextHead55, $i);
        try {
            $record = libsqlite_release_gap55_evidence()->releaseRunnerUpstreamSuiteGapBurnup(
                $current,
                $next,
                $currentHead55,
                $nextHead55,
                20008,
                $focusedPath55,
                libsqlite_release_gap55_output(55),
                $nonOverlap55
            );

            $expectedCurrent = 1 + ($i % 4 !== 0 ? 1 : 0) + ($i % 11 === 0 ? 1 : 0);
            $expectedNext = 2 + ($i % 5 === 0 ? 1 : 0);
            $t->same($expectedCurrent, $record['current_countable_count']);
            $t->same($expectedNext, $record['next_countable_count']);
            $t->same($expectedNext - $expectedCurrent, $record['countable_delta']);
            $t->same(55, $record['php_pass_delta']);
            $t->same(20063, $record['next_php_pass']);
            $t->same(false, $record['counts_as_release_parity']);
            $t->contains('current-next55 suite gap burnup', $record['dependency_closure']);
        } finally {
            libsqlite_release_gap55_cleanup($root);
        }
    };
}

$tests['current next55 artifact gap burnup advances newly countable release artifact'] = static function (TestRunner $t) use ($currentHead55, $nextHead55, $focusedPath55, $nonOverlap55): void {
    [$root, $current, $next] = libsqlite_release_gap55_dirs($currentHead55, $nextHead55, 5);
    try {
        $record = libsqlite_release_gap55_evidence()->releaseRunnerUpstreamSuiteGapBurnup($current, $next, $currentHead55, $nextHead55, 20008, $focusedPath55, libsqlite_release_gap55_output(55), $nonOverlap55);
        $t->same('next55-suite-gap-burnup-advanced', $record['status']);
        $t->same(true, $record['counts_next_suite_gap_burnup']);
        $t->true(in_array('release', $record['advanced_ids'], true), 'Expected release artifact to advance');
        $t->contains('accepted-HEAD zero-error next55', $record['next_gate']);
    } finally {
        libsqlite_release_gap55_cleanup($root);
    }
};

$tests['current next55 artifact gap burnup preserves open failed and stale rows uncounted'] = static function (TestRunner $t) use ($currentHead55, $nextHead55, $focusedPath55, $nonOverlap55): void {
    [$root, $current, $next] = libsqlite_release_gap55_dirs($currentHead55, $nextHead55, 17);
    try {
        $record = libsqlite_release_gap55_evidence()->releaseRunnerUpstreamSuiteGapBurnup($current, $next, $currentHead55, $nextHead55, 20008, $focusedPath55, libsqlite_release_gap55_output(55), $nonOverlap55);
        $t->same('current-suite-gap-burnup-preserved-with-open-gaps', $record['status']);
        $t->same(1, $record['next_directory_record']['blocked_count']);
        $t->true(in_array('release', $record['open_ids'], true), 'Expected stale release row to stay open');
        $t->true(in_array('suite-artifact-blocked', array_column($record['blockers'], 'id'), true), 'Expected stale artifact blocker');
    } finally {
        libsqlite_release_gap55_cleanup($root);
    }
};

$tests['current next55 artifact gap burnup blocks next countability regression'] = static function (TestRunner $t) use ($currentHead55, $nextHead55, $focusedPath55, $nonOverlap55): void {
    [$root, $current, $next] = libsqlite_release_gap55_dirs($currentHead55, $nextHead55, 11);
    try {
        $record = libsqlite_release_gap55_evidence()->releaseRunnerUpstreamSuiteGapBurnup($current, $next, $currentHead55, $nextHead55, 20008, $focusedPath55, libsqlite_release_gap55_output(55), $nonOverlap55);
        $t->same('blocked', $record['status']);
        $t->true(in_array('mptest', $record['regressed_ids'], true), 'Expected mptest regression');
        $mptest = null;
        foreach ($record['entries'] as $entry) {
            if (($entry['id'] ?? null) === 'mptest') {
                $mptest = $entry;
            }
        }
        $t->true(is_array($mptest), 'Expected mptest entry');
        $t->true(in_array('next-countability-regressed', $mptest['blockers'], true), 'Expected countability regression blocker');
    } finally {
        libsqlite_release_gap55_cleanup($root);
    }
};

$tests['current next55 artifact gap burnup blocks under threshold php evidence'] = static function (TestRunner $t) use ($currentHead55, $nextHead55, $focusedPath55, $nonOverlap55): void {
    [$root, $current, $next] = libsqlite_release_gap55_dirs($currentHead55, $nextHead55, 5);
    try {
        $record = libsqlite_release_gap55_evidence()->releaseRunnerUpstreamSuiteGapBurnup($current, $next, $currentHead55, $nextHead55, 20008, $focusedPath55, libsqlite_release_gap55_output(39), $nonOverlap55, 40);
        $t->same('blocked', $record['status']);
        $t->same(39, $record['php_pass_delta']);
        $t->true(in_array('focused-php-pass-delta-below-minimum', array_column($record['blockers'], 'id'), true), 'Expected under-threshold focused evidence blocker');
    } finally {
        libsqlite_release_gap55_cleanup($root);
    }
};

$tests['current next55 artifact gap burnup blocks unfocused php output'] = static function (TestRunner $t) use ($currentHead55, $nextHead55, $focusedPath55, $nonOverlap55): void {
    [$root, $current, $next] = libsqlite_release_gap55_dirs($currentHead55, $nextHead55, 5);
    try {
        $record = libsqlite_release_gap55_evidence()->releaseRunnerUpstreamSuiteGapBurnup($current, $next, $currentHead55, $nextHead55, 20008, $focusedPath55, "1 test files, 55 assertions, 0 failures\n", $nonOverlap55);
        $t->same('blocked', $record['status']);
        $t->same('blocked', $record['php_pass_admission']['status']);
    } finally {
        libsqlite_release_gap55_cleanup($root);
    }
};

$tests['current next55 artifact gap burnup rejects missing heads'] = static function (TestRunner $t) use ($focusedPath55, $nonOverlap55): void {
    $t->throws(InvalidArgumentException::class, static fn () => libsqlite_release_gap55_evidence()->releaseRunnerUpstreamSuiteGapBurnup('/tmp/a', '/tmp/b', '', 'next', 20008, $focusedPath55, libsqlite_release_gap55_output(55), $nonOverlap55));
};

$tests['current next55 artifact gap burnup rejects missing directories'] = static function (TestRunner $t) use ($currentHead55, $nextHead55, $focusedPath55, $nonOverlap55): void {
    $t->throws(InvalidArgumentException::class, static fn () => libsqlite_release_gap55_evidence()->releaseRunnerUpstreamSuiteGapBurnup('', '/tmp/b', $currentHead55, $nextHead55, 20008, $focusedPath55, libsqlite_release_gap55_output(55), $nonOverlap55));
};

$tests['current next55 artifact gap burnup rejects zero minimum'] = static function (TestRunner $t) use ($currentHead55, $nextHead55, $focusedPath55, $nonOverlap55): void {
    $t->throws(InvalidArgumentException::class, static fn () => libsqlite_release_gap55_evidence()->releaseRunnerUpstreamSuiteGapBurnup('/tmp/a', '/tmp/b', $currentHead55, $nextHead55, 20008, $focusedPath55, libsqlite_release_gap55_output(55), $nonOverlap55, 0));
};

$tests['current next55 artifact gap burnup reports missing artifact directories explicitly'] = static function (TestRunner $t) use ($currentHead55, $nextHead55, $focusedPath55, $nonOverlap55): void {
    $record = libsqlite_release_gap55_evidence()->releaseRunnerUpstreamSuiteGapBurnup('/tmp/missing-current-gap55', '/tmp/missing-next-gap55', $currentHead55, $nextHead55, 20008, $focusedPath55, libsqlite_release_gap55_output(55), $nonOverlap55);
    $t->same('blocked', $record['status']);
    $t->same('blocked-missing-artifact-directory', $record['current_directory_record']['status']);
    $t->same('blocked-missing-artifact-directory', $record['next_directory_record']['status']);
    $t->same(0, $record['row_count']);
};

return $tests;
