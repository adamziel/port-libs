<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteUpstreamSuiteEvidence;

function libsqlite_release_current_next28_evidence(): SQLiteUpstreamSuiteEvidence
{
    return SQLiteUpstreamSuiteEvidence::fromManifestPath(__DIR__ . '/../UPSTREAM_TEST_MANIFEST.json');
}

function libsqlite_release_current_next28_root(string $label): string
{
    return sys_get_temp_dir() . '/libsqlite-release-current-next28-' . $label . '-' . bin2hex(random_bytes(4));
}

function libsqlite_release_current_next28_cleanup(string $root): void
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

function libsqlite_release_current_next28_audit(
    string $label,
    string $head,
    int $tests,
    int $errors = 0,
    string $testset = 'release',
    string $patterns = 'none',
    int $exit = 0,
    string $log = 'runner.log',
    string $manifestUuid = '9ac4a33a2932d353c4871fd8e09c10addf827f1fc3fc9380037d738cf2cd0353'
): string {
    return "# SQLite Tcl Bounded Runner Evidence - {$label}\n\n"
        . "- Repository HEAD: `{$head}`\n"
        . "- SQLite git commit: `8f70ec615f4cd247d36f92a22c99f65ebbcc22a7`\n"
        . "- SQLite VERSION: `3.54.0`\n"
        . "- SQLite manifest UUID: `{$manifestUuid}`\n"
        . "- Scratch: `/tmp/libsqlite-current-next28`\n"
        . "- Log: `{$log}`\n"
        . "- Testset: `{$testset}`\n"
        . "- Patterns: `{$patterns}`\n"
        . "- Exit: `{$exit}`\n"
        . "- Parsed errors: `{$errors}`\n"
        . "- Parsed tests: `{$tests}`\n";
}

function libsqlite_release_current_next28_write(
    string $dir,
    string $label,
    string $head,
    int $tests,
    array $options = []
): void {
    if (!is_dir($dir)) {
        mkdir($dir, 0777, true);
    }

    $errors = (int) ($options['errors'] ?? 0);
    $exit = (int) ($options['exit'] ?? ($errors > 0 ? 1 : 0));
    $testset = (string) ($options['testset'] ?? 'release');
    $patterns = (string) ($options['patterns'] ?? 'none');
    $manifest = (string) ($options['manifest'] ?? '9ac4a33a2932d353c4871fd8e09c10addf827f1fc3fc9380037d738cf2cd0353');
    $extension = (string) ($options['extension'] ?? 'md');
    $logName = (string) ($options['log'] ?? ($label . '.log'));
    $audit = libsqlite_release_current_next28_audit($label, $head, $tests, $errors, $testset, $patterns, $exit, $logName, $manifest);
    file_put_contents($dir . '/' . $label . '.' . $extension, $audit);

    if (($options['missing_log'] ?? false) !== true) {
        file_put_contents($dir . '/' . basename($logName), "{$errors} errors out of {$tests} tests\n");
    }
}

$scenarioCases = [
    'release-gain-one' => [1, 2, 'next-count-increased', 1, 22000],
    'release-gain-two' => [1, 3, 'next-count-increased', 2, 44000],
    'release-preserved-one' => [1, 1, 'current-count-preserved', 0, 0],
    'release-preserved-two' => [2, 2, 'current-count-preserved', 0, 0],
    'focused-gain-json' => [1, 2, 'next-count-increased', 1, 812],
    'focused-gain-wal' => [2, 3, 'next-count-increased', 1, 144],
    'mixed-focused-release-gain' => [2, 4, 'next-count-increased', 2, 22144],
    'audit-extension-gain' => [1, 2, 'next-count-increased', 1, 22000],
    'relative-log-gain' => [1, 2, 'next-count-increased', 1, 22000],
    'basename-log-gain' => [1, 2, 'next-count-increased', 1, 22000],
    'current-two-next-three' => [2, 3, 'next-count-increased', 1, 22000],
    'current-three-next-four' => [3, 4, 'next-count-increased', 1, 22000],
    'current-four-next-four' => [4, 4, 'current-count-preserved', 0, 0],
    'large-release-gain' => [1, 2, 'next-count-increased', 1, 24000],
    'large-focused-gain' => [1, 2, 'next-count-increased', 1, 4321],
    'next-all-testset-gain' => [1, 2, 'next-count-increased', 1, 31000],
    'next-release-pattern-gain' => [1, 2, 'next-count-increased', 1, 18000],
    'next-veryquick-pattern-gain' => [1, 2, 'next-count-increased', 1, 700],
    'preserve-focused-only' => [2, 2, 'current-count-preserved', 0, 0],
    'preserve-release-only' => [3, 3, 'current-count-preserved', 0, 0],
    'gain-with-sorted-labels' => [2, 4, 'next-count-increased', 2, 66000],
    'gain-with-current-audit-extension' => [1, 2, 'next-count-increased', 1, 22000],
    'gain-with-next-audit-extension' => [1, 2, 'next-count-increased', 1, 22000],
    'gain-with-label-overlap' => [2, 3, 'next-count-increased', 1, 22000],
    'gain-with-distinct-next-head' => [1, 2, 'next-count-increased', 1, 22000],
    'preserved-distinct-next-head' => [2, 2, 'current-count-preserved', 0, 0],
    'gain-after-current-baseline' => [1, 4, 'next-count-increased', 3, 66000],
    'gain-after-focused-baseline' => [1, 3, 'next-count-increased', 2, 956],
    'preserve-after-release-baseline' => [4, 4, 'current-count-preserved', 0, 0],
    'preserve-after-focused-baseline' => [3, 3, 'current-count-preserved', 0, 0],
    'next-count-five' => [3, 5, 'next-count-increased', 2, 44000],
    'next-count-six' => [4, 6, 'next-count-increased', 2, 44000],
    'current-count-six-preserved' => [6, 6, 'current-count-preserved', 0, 0],
    'release-plus-veryquick-gain' => [2, 4, 'next-count-increased', 2, 22700],
    'current-release-next-focused-gain' => [1, 2, 'next-count-increased', 1, 700],
    'current-focused-next-release-gain' => [1, 2, 'next-count-increased', 1, 22000],
    'all-runner-gain' => [1, 2, 'next-count-increased', 1, 31000],
    'all-runner-preserved' => [2, 2, 'current-count-preserved', 0, 0],
    'release-count-seven' => [5, 7, 'next-count-increased', 2, 44000],
    'release-count-eight-preserved' => [8, 8, 'current-count-preserved', 0, 0],
];

$tests = [];

foreach ($scenarioCases as $case => [$currentCount, $nextCount, $expectedStatus, $expectedDelta]) {
    $tests['current next28 counts release runner artifacts ' . $case] = static function (TestRunner $t) use ($case, $currentCount, $nextCount, $expectedStatus, $expectedDelta): void {
        $root = libsqlite_release_current_next28_root($case);
        $current = $root . '/current';
        $next = $root . '/next';

        try {
            for ($i = 1; $i <= $currentCount; $i++) {
                $testset = str_contains($case, 'focused') ? 'veryquick' : 'release';
                $patterns = $testset === 'veryquick' ? 'json101.test, wal.test' : 'none';
                $testsTotal = $testset === 'veryquick' ? 700 + $i : 22000;
                libsqlite_release_current_next28_write($current, sprintf('artifact-%02d', $i), 'current-head', $testsTotal, [
                    'testset' => $testset,
                    'patterns' => $patterns,
                    'extension' => str_contains($case, 'current-audit-extension') ? 'audit' : 'md',
                ]);
            }

            for ($i = 1; $i <= $nextCount; $i++) {
                $isNew = $i > $currentCount;
                $testset = str_contains($case, 'all-runner') ? 'all' : (str_contains($case, 'focused') || str_contains($case, 'veryquick') ? 'veryquick' : 'release');
                $patterns = $testset === 'release' ? (str_contains($case, 'release-pattern') ? 'json101.test, wal.test' : 'none') : 'json101.test, wal.test';
                $testsTotal = $testset === 'all' ? 31000 : ($testset === 'veryquick' ? 701 + $i : 22000);
                libsqlite_release_current_next28_write($next, sprintf('artifact-%02d', $i), str_contains($case, 'distinct-next-head') ? 'next-head' : 'current-head', (int) $testsTotal, [
                    'testset' => $testset,
                    'patterns' => $patterns,
                    'extension' => str_contains($case, 'next-audit-extension') ? 'audit' : 'md',
                ]);
            }

            $record = libsqlite_release_current_next28_evidence()->releaseRunnerCurrentNextCountRecord(
                $current,
                $next,
                'current-head',
                str_contains($case, 'distinct-next-head') ? 'next-head' : null
            );

            $t->same($expectedStatus, $record['status']);
            $t->same($currentCount, $record['current_countable_count']);
            $t->same($nextCount, $record['next_countable_count']);
            $t->same($expectedDelta, $record['countable_delta']);
            $t->same($expectedDelta > 0, $record['counts_next_artifacts'] && $record['status'] === 'next-count-increased');
            $t->same($expectedDelta, count($record['new_countable_labels']));
            $t->same([], $record['lost_countable_labels']);
            $t->same(0, $record['blocker_count']);
            $t->same(0, $record['current_blocked_count']);
            $t->same(0, $record['next_blocked_count']);
            $t->contains('no new support component needed', $record['dependency_closure']);
            $t->true(is_int($record['tests_total_delta']), 'Expected integer test-count delta');
        } finally {
            libsqlite_release_current_next28_cleanup($root);
        }
    };
}

$tests['current next28 blocks missing current baseline directory'] = static function (TestRunner $t): void {
    $root = libsqlite_release_current_next28_root('missing-current');
    $next = $root . '/next';
    try {
        libsqlite_release_current_next28_write($next, 'next-release', 'current-head', 22000);
        $record = libsqlite_release_current_next28_evidence()->releaseRunnerCurrentNextCountRecord($root . '/missing', $next, 'current-head');

        $t->same('blocked', $record['status']);
        $t->same(['current-artifacts-missing'], array_column($record['blockers'], 'id'));
        $t->same(0, $record['current_artifact_count']);
        $t->same(1, $record['next_countable_count']);
    } finally {
        libsqlite_release_current_next28_cleanup($root);
    }
};

$tests['current next28 blocks missing next candidate directory'] = static function (TestRunner $t): void {
    $root = libsqlite_release_current_next28_root('missing-next');
    $current = $root . '/current';
    try {
        libsqlite_release_current_next28_write($current, 'current-release', 'current-head', 22000);
        $record = libsqlite_release_current_next28_evidence()->releaseRunnerCurrentNextCountRecord($current, $root . '/missing', 'current-head');

        $t->same('blocked', $record['status']);
        $t->same(['next-artifacts-missing', 'next-count-regressed'], array_column($record['blockers'], 'id'));
        $t->same(1, $record['current_countable_count']);
        $t->same(0, $record['next_artifact_count']);
    } finally {
        libsqlite_release_current_next28_cleanup($root);
    }
};

$tests['current next28 blocks next count regression'] = static function (TestRunner $t): void {
    $root = libsqlite_release_current_next28_root('regression');
    $current = $root . '/current';
    $next = $root . '/next';
    try {
        libsqlite_release_current_next28_write($current, 'a', 'current-head', 22000);
        libsqlite_release_current_next28_write($current, 'b', 'current-head', 22000);
        libsqlite_release_current_next28_write($next, 'a', 'current-head', 22000);

        $record = libsqlite_release_current_next28_evidence()->releaseRunnerCurrentNextCountRecord($current, $next, 'current-head');

        $t->same('blocked', $record['status']);
        $t->same(['next-count-regressed'], array_column($record['blockers'], 'id'));
        $t->same(['b'], $record['lost_countable_labels']);
        $t->same(-1, $record['countable_delta']);
    } finally {
        libsqlite_release_current_next28_cleanup($root);
    }
};

$tests['current next28 blocks stale next artifact'] = static function (TestRunner $t): void {
    $root = libsqlite_release_current_next28_root('stale-next');
    $current = $root . '/current';
    $next = $root . '/next';
    try {
        libsqlite_release_current_next28_write($current, 'a', 'current-head', 22000);
        libsqlite_release_current_next28_write($next, 'a', 'current-head', 22000);
        libsqlite_release_current_next28_write($next, 'b', 'stale-head', 22000);

        $record = libsqlite_release_current_next28_evidence()->releaseRunnerCurrentNextCountRecord($current, $next, 'current-head');

        $t->same('blocked', $record['status']);
        $t->same(['next-artifacts-blocked'], array_column($record['blockers'], 'id'));
        $t->same(1, $record['next_countable_count']);
        $t->same(1, $record['next_blocked_count']);
    } finally {
        libsqlite_release_current_next28_cleanup($root);
    }
};

$tests['current next28 blocks failed next artifact'] = static function (TestRunner $t): void {
    $root = libsqlite_release_current_next28_root('failed-next');
    $current = $root . '/current';
    $next = $root . '/next';
    try {
        libsqlite_release_current_next28_write($current, 'a', 'current-head', 22000);
        libsqlite_release_current_next28_write($next, 'a', 'current-head', 22000);
        libsqlite_release_current_next28_write($next, 'b', 'current-head', 22000, ['errors' => 1]);

        $record = libsqlite_release_current_next28_evidence()->releaseRunnerCurrentNextCountRecord($current, $next, 'current-head');

        $t->same('blocked', $record['status']);
        $t->same(['next-artifacts-blocked'], array_column($record['blockers'], 'id'));
        $t->same(1, $record['next_blocked_count']);
        $t->same('partially-countable', $record['next_status']);
    } finally {
        libsqlite_release_current_next28_cleanup($root);
    }
};

$tests['current next28 blocks wrong manifest next artifact'] = static function (TestRunner $t): void {
    $root = libsqlite_release_current_next28_root('wrong-manifest');
    $current = $root . '/current';
    $next = $root . '/next';
    try {
        libsqlite_release_current_next28_write($current, 'a', 'current-head', 22000);
        libsqlite_release_current_next28_write($next, 'a', 'current-head', 22000);
        libsqlite_release_current_next28_write($next, 'b', 'current-head', 22000, ['manifest' => 'wrong-manifest']);

        $record = libsqlite_release_current_next28_evidence()->releaseRunnerCurrentNextCountRecord($current, $next, 'current-head');

        $t->same('blocked', $record['status']);
        $t->same(['next-artifacts-blocked'], array_column($record['blockers'], 'id'));
        $t->same(1, $record['next_blocked_count']);
        $t->same(0, $record['countable_delta']);
    } finally {
        libsqlite_release_current_next28_cleanup($root);
    }
};

$tests['current next28 blocks missing next log artifact'] = static function (TestRunner $t): void {
    $root = libsqlite_release_current_next28_root('missing-log');
    $current = $root . '/current';
    $next = $root . '/next';
    try {
        libsqlite_release_current_next28_write($current, 'a', 'current-head', 22000);
        libsqlite_release_current_next28_write($next, 'a', 'current-head', 22000);
        libsqlite_release_current_next28_write($next, 'b', 'current-head', 22000, ['missing_log' => true]);

        $record = libsqlite_release_current_next28_evidence()->releaseRunnerCurrentNextCountRecord($current, $next, 'current-head');

        $t->same('blocked', $record['status']);
        $t->same(['next-artifact-logs-missing'], array_column($record['blockers'], 'id'));
        $t->true($record['next']['stdout_file_count'] >= 1, 'Expected at least one paired runner log');
        $t->true($record['next_missing_log_count'] >= 1, 'Expected missing next runner log evidence');
        $t->same(2, $record['next_countable_count']);
    } finally {
        libsqlite_release_current_next28_cleanup($root);
    }
};

$tests['current next28 blocks dirty current baseline'] = static function (TestRunner $t): void {
    $root = libsqlite_release_current_next28_root('dirty-current');
    $current = $root . '/current';
    $next = $root . '/next';
    try {
        libsqlite_release_current_next28_write($current, 'a', 'current-head', 22000);
        libsqlite_release_current_next28_write($current, 'b', 'old-head', 22000);
        libsqlite_release_current_next28_write($next, 'a', 'current-head', 22000);
        libsqlite_release_current_next28_write($next, 'c', 'current-head', 22000);

        $record = libsqlite_release_current_next28_evidence()->releaseRunnerCurrentNextCountRecord($current, $next, 'current-head');

        $t->same('blocked', $record['status']);
        $t->same(['current-artifacts-blocked'], array_column($record['blockers'], 'id'));
        $t->same(1, $record['current_blocked_count']);
        $t->same(1, $record['countable_delta']);
    } finally {
        libsqlite_release_current_next28_cleanup($root);
    }
};

$tests['current next28 rejects empty current accepted head'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => libsqlite_release_current_next28_evidence()->releaseRunnerCurrentNextCountRecord('/tmp/missing-a', '/tmp/missing-b', ''));
};

$tests['current next28 rejects empty next accepted head'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => libsqlite_release_current_next28_evidence()->releaseRunnerCurrentNextCountRecord('/tmp/missing-a', '/tmp/missing-b', 'current-head', ''));
};

return $tests;
